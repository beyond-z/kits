<?php
$WP_CONFIG = array();

function bzLoadWpConfig() {
	global $WP_CONFIG;

	$out = array();
	preg_match_all("/define\('([A-Z_0-9]+)', '(.*)'\);/", file_get_contents("wp-config.php"), $out, PREG_SET_ORDER);

	foreach($out as $match) {
		$WP_CONFIG[$match[1]] = $match[2];
	}
}

bzLoadWpConfig();

require_once("courses.php");

function get_lc_excused_status($event_id, $lc_email) {
	global $pdo;

	$statement = $pdo->prepare("
		SELECT
			substitute_name, substitute_email, substitute_phone
		FROM
			attendance_lc_absences
		WHERE
			event_id = ?
			AND
			lc_email = ?
	");

	$statement->execute(array($event_id, $lc_email));
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$row["excused"] = true;
		return $row;
	}

	return array("excused" => false);
}


function get_canvas_events($course_id, $start_date, $end_date) {
	global $WP_CONFIG;

	$additional = "";
	if($start_date !== null)
		$additional .= "&start_date=".urlencode($start_date);
	if($end_date !== null)
		$additional .= "&end_date=".urlencode($end_date);

	$ch = curl_init();
	$url = 'https://'.$WP_CONFIG["BRAVEN_PORTAL_DOMAIN"].'/api/v1/calendar_events?per_page=500&context_codes[]=course_'.(urlencode($course_id)). '&access_token=' . urlencode($WP_CONFIG["CANVAS_TOKEN"]) . $additional;
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$answer = curl_exec($ch);
	curl_close($ch);

	//print_r($answer);

	// trim off any cross-site get padding, if present,
	// keeping just the json object
	$answer = substr($answer, strpos($answer, "["));
	$obj = json_decode($answer, true);

	return $obj;
}

function get_canvas_learning_labs($course_id, $start_date = null, $end_date = null) {
	$list = array();
	$events = get_canvas_events($course_id, $start_date, $end_date);
	foreach($events as $event) {
		$title = $event["title"];
		// the format here is "Learning Lab #: NAME (Cohort)"
		// so gonna kinda fake-parse this. so hacky lololol

		$matches = array();
		preg_match('/[^:]+: ([^\(]+)\((.*)\)/', $title, $matches);

		if(count($matches) < 2)
			continue; // not actually one of these events

		$event_name = trim($matches[1]);
		$cohort = trim($matches[2]);
		$list[] = array("event" => $event_name, "cohort" => $cohort, "end_at" => $event["end_at"]);
	}

	return $list;
}

function populate_times_from_canvas($course_id) {
	// just want all times ever
	$list = get_canvas_learning_labs($course_id, '2000-01-01', '2300-12-31');
	foreach($list as $data) {
		global $pdo;

		// translate to mysql format
		$data["end_at"] = str_replace("T", " ", str_replace("Z", "", $data["end_at"])); 

		echo "{$data["end_at"]} $course_id / {$data["event"]}";

		$statement = $pdo->prepare("
			UPDATE
				attendance_events
			SET
				event_time = ?
			WHERE
				course_id = ?
				AND
				name = ?
		");

		$statement->execute(array(
			$data["end_at"],
			$course_id,
			$data["event"]
		));
	}
}

function isTa($user_email, $cohort_info) {
	return in_array($user_email, $cohort_info["tas"]);
}

function get_cohorts_info($course_id) {
	global $WP_CONFIG;

	if(isset($_SESSION["cohort_course_info_$course_id"]))
		return $_SESSION["cohort_course_info_$course_id"];

	$ch = curl_init();
	$url = 'https://'.$WP_CONFIG["BRAVEN_PORTAL_DOMAIN"].'/bz/course_cohort_information?course_ids[]='.((int) $course_id). '&access_token=' . urlencode($WP_CONFIG["CANVAS_TOKEN"]);

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	$answer = curl_exec($ch);
	curl_close($ch);

	// trim off any cross-site get padding, if present,
	// keeping just the json object
	$answer = substr($answer, strpos($answer, "{"));
	$obj = json_decode($answer, TRUE);

	$sections = $obj["courses"][0]["sections"];
	$lcs = array();
	$students = array();
	$tas = array();
	foreach($sections as $section) {
		foreach($section["enrollments"] as $enrollment) {
			if($enrollment["type"] == "TaEnrollment")
				$lcs[] = $enrollment;
			if($enrollment["type"] == "StudentEnrollment")
				$students[] = $enrollment;
		}
		if(isset($section["ta_email"]))
			$tas[] = $section["ta_email"];
	}

	return $_SESSION["cohort_course_info_$course_id"] = array(
		"lcs" => $lcs,
		"tas" => $tas,
		"students" => $students,
		"sections" => $sections
	);
}

function log_nag($event_id, $lc_email, $answer) {
	global $pdo;

	$statement = $pdo->prepare("
		INSERT INTO
			attendance_nag_log
			(event_id, date_sent, lc_email, raw_response)
		VALUES
			(?, NOW(), ?, ?)
	");
	$statement->execute(array($event_id, $lc_email, $answer));
}

function get_nag_info($lc_email) {
	global $pdo;

	$statement = $pdo->prepare("
		SELECT
			count(id) AS count,
			max(date_sent) AS last,
			DATE_ADD(max(date_sent),INTERVAL 7 DAY) > NOW() AS recent
		FROM
			attendance_nag_log
		WHERE
			lc_email = ?
	");

	$statement->execute(array($lc_email));
	return $statement->fetch();
}

function load_student_status($event_id, $students_info) {
	if(count($students_info) == 0)
		return array();

	global $pdo;

	$students = array();
	foreach($students_info as $student)
		$students[] = $student["id"];

	$statement = $pdo->prepare("
		SELECT
			person_id, present, reason
		FROM
			attendance_people
		WHERE
			event_id = ?
			AND
			person_id IN  (".str_repeat('?,', count($students) - 1)."?)

	");

	$args = array($event_id);
	$args = array_merge($args, $students);

	$reasons = array();
	$result = array();
	$original_result = array();

	foreach($students as $student) {
		$result[$student] = "null";
		$original_result[$student] = "null";
	}

	$statement->execute($args);
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		// format it as a bool string here so we don't have to get ambiguity later with override strings
		$value = "null";
		if($row["present"] !== null)
		switch($row["present"]) {
			case 0:
				$value = "false";
			break;
			case 1:
				$value = "true";
			break;
			case 2:
				$value = "late";
			break;
		}
		$result[$row["person_id"]] = $value;
		$original_result[$row["person_id"]] = $value;
		$reasons[$row["person_id"]] = $row["reason"];
	}

	// and now we need to load overrides for withdrawn students, etc.

	$statement = $pdo->prepare("
		SELECT
			person_id,
			status
		FROM
			attendance_people_statuses
		WHERE
			course_id = (SELECT course_id FROM attendance_events WHERE id = ?)
			AND
			person_id IN  (".str_repeat('?,', count($students) - 1)."?)
			AND
			as_of < (SELECT event_time FROM attendance_events WHERE id = ?)
		ORDER BY
			as_of ASC -- the purpose here is to get the latest one last, so the loop below will defer to it. i really only want the max as_of that fits the othe requirements per person but idk how to express that in this version of mysql. meh.
	");

	$args[] = $event_id; // same args as before except for one more event id reference
	$statement->execute($args);

	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		if(strlen($row["status"]) > 0) {
			$result[$row["person_id"]] = $row["status"];
		} else {
			// if the override is blank, it means it was undone since now
			$result[$row["person_id"]] = $original_result[$row["person_id"]];
		}
	}

	return array(
		"reasons" => $reasons,
		"result" => $result
	);
}

function get_all_events($course_id) {
	global $pdo;

	$statement = $pdo->prepare("
		SELECT
			id, name, event_time, course_id
		FROM
			attendance_events
		WHERE
			course_id = ?
		ORDER BY
			display_order, event_time
	");

	$result = array();
	$statement->execute(array($course_id));
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$result[] = $row;
	}

	return $result;
}

$pdo_opt = [
	PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = new PDO("mysql:host={$WP_CONFIG["DB_HOST"]};dbname={$WP_CONFIG["DB_ATTENDANCE_NAME"]};charset=utf8mb4", $WP_CONFIG["DB_USER"], $WP_CONFIG["DB_PASSWORD"], $pdo_opt);

