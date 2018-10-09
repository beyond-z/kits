<?php
/* This should be run on a cron job, probably Wednesday nights, to remind people to do attendance */

if(php_sapi_name() != 'cli')
	die();

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

// ////////////////////
// note: copy paste from attendance.php


$pdo_opt = [
	PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
	PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
	PDO::ATTR_EMULATE_PREPARES   => false,
];

$pdo = new PDO("mysql:host={$WP_CONFIG["DB_HOST"]};dbname={$WP_CONFIG["DB_ATTENDANCE_NAME"]};charset=utf8mb4", $WP_CONFIG["DB_USER"], $WP_CONFIG["DB_PASSWORD"], $pdo_opt);

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

// end copy/paste

function get_canvas_events($course_id) {
	global $WP_CONFIG;

	$ch = curl_init();
	$url = 'https://'.$WP_CONFIG["BRAVEN_PORTAL_DOMAIN"].'/api/v1/calendar_events?start_date=2018-10-09&context_codes[]=course_'.(urlencode($course_id)). '&access_token=' . urlencode($WP_CONFIG["CANVAS_TOKEN"]);
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

function load_attendance_result($course_id, $event_name, $students_info) {
	if(count($students_info) == 0)
		return array (
			"present" => 0,
			"recorded" => 0,
			"student_count" => 0,
			"percent" => 100 // everybody of the nobody was there lol
		);

	global $pdo;

	$statement = $pdo->prepare("
		SELECT
			id
		FROM
			attendance_events
		WHERE
			course_id = ?
			AND
			name = ?
	");

	$event_id = 0;

	$statement->execute(array($course_id, $event_name));
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		$event_id = $row["id"];
	}

	if($event_id == 0)
		throw new Exception("no such event " . $event_name);

	$students = array();
	foreach($students_info as $student)
		$students[] = $student["id"];

	$statement = $pdo->prepare("
		SELECT
			person_id, present
		FROM
			attendance_people
		WHERE
			event_id = ?
			AND
			person_id IN  (".str_repeat('?,', count($students) - 1)."?)

	");

	$args = array($event_id);
	$args = array_merge($args, $students);

	$student_count = count($students);
	$result = 0;
	$recorded = 0;

	$statement->execute($args);
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		if($row["present"])
			$result++;
		$recorded++;
	}

	return array(
		"present" => $result,
		"recorded" => $recorded,
		"student_count" => $student_count,
		"percent" => (int)($result * 100 / $student_count)
	);
}

function send_sms($to, $message) {
	global $WP_CONFIG;

	$to = preg_replace('/[^0-9]/', '', $to);
	if(strlen($to) == 10)
		$to = "1" . $to;
	$to = "+" . $to;

	if(strlen($to) != 12)
		throw new Exception("bad phone number $to");

	$ch = curl_init();
	$url = "https://api.twilio.com/2010-04-01/Accounts/".$WP_CONFIG["TWILIO_SID"]."/Messages.json";
	$auth = $WP_CONFIG["TWILIO_SID"] . ":" . $WP_CONFIG["TWILIO_TOKEN"];

	$post  = "From=".urlencode($WP_CONFIG['TWILIO_FROM'])."&";
	$post .= "To=".urlencode($to)."&";
	$post .= "Body=".urlencode($message);

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_POST, true);
	curl_setopt($ch, CURLOPT_USERPWD, $auth);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

	$answer = curl_exec($ch);
	curl_close($ch);

	//echo $answer;
}


function check_attendance_from_canvas($course_id, $notify_method) {
	$now = time();
	$list = array();

	$events = get_canvas_events($course_id);
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

	if(empty($list))
		return;

	$ci = get_cohorts_info($course_id);

	foreach($list as $data) {
		foreach($ci["sections"] as $section) {
			if($section["name"] == $data["cohort"]) {
				$lc_email = "";
				$students = array();
				foreach($section["enrollments"] as $enrollment) {
					if($enrollment["type"] == "TaEnrollment") {
						$lc_email = $enrollment["contact_email"];
						if($lc_email == "")
							$lc_email = $enrollment["email"];
					} else if($enrollment["type"] == "StudentEnrollment") {
						$students[] = $enrollment;
					}
				}

				// if no lc, nobody to notify, can just skip.
				//if($lc_email == "")
				if($section["lc_phone"] == "")
					continue;

				$res = load_attendance_result($course_id, $data["event"], $students);

				//if(gmddate(DATE_ISO8601
				$when = strtotime($data["end_at"]);
				// if the event ended less than 30 minutes in the past...
				if($now - $when > 0 && $now - $when < 60 * 30) {
					if($res["percent"] == 0) {
						// nag necessary - 0% surely means no attendance was taken
						switch($notify_method) {
							case "sms":
								send_sms($section["lc_phone"], "Don't forget to record attendance for tonight's Braven event! https://kits.bebraven.org/");

							break;
							case "echo":
							default:
								echo "Attendance needed for: " . $data["cohort"] . " " . $section["lc_phone"] . " " . $lc_email . "\n";
						}
					}
				}

			}
		}
	}
}

// I want to run the cron at the :05 of every hour.

date_default_timezone_set("UTC");

foreach(array(45, 49) as $course_id) {
	check_attendance_from_canvas($course_id, $WP_CONFIG["ATTENDANCE_TRACKER_NOTIFY_METHOD"]);
}
