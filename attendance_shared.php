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

function get_cohorts_info($course_id) {
	global $WP_CONFIG;

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
	foreach($sections as $section) {
		foreach($section["enrollments"] as $enrollment) {
			if($enrollment["type"] == "TaEnrollment")
				$lcs[] = $enrollment;
			if($enrollment["type"] == "StudentEnrollment")
				$students[] = $enrollment;
		}
	}

	return array(
		"lcs" => $lcs,
		"sections" => $sections
	);
}

$pdo_opt = [
	PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = new PDO("mysql:host={$WP_CONFIG["DB_HOST"]};dbname={$WP_CONFIG["DB_ATTENDANCE_NAME"]};charset=utf8mb4", $WP_CONFIG["DB_USER"], $WP_CONFIG["DB_PASSWORD"], $pdo_opt);

