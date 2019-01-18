<?php
/* This should be run on a cron job, probably Wednesday nights, to remind people to do attendance */

if(php_sapi_name() != 'cli')
	die();

require_once("attendance_shared.php");

// ////////////////////

function get_canvas_events($course_id) {
	global $WP_CONFIG;

	$ch = curl_init();
	$url = 'https://'.$WP_CONFIG["BRAVEN_PORTAL_DOMAIN"].'/api/v1/calendar_events?context_codes[]=course_'.(urlencode($course_id)). '&access_token=' . urlencode($WP_CONFIG["CANVAS_TOKEN"]);
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

	// I don't think we need to check withdrawns because the only way that would matter is if the ENTIRE cohort dropped the class, in which case we would also remove the LC, so no effect on the logic here.

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
		"event_id" => $event_id,
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

	echo "Twilio responded: $answer \n";
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

	if(empty($list)) {
		echo "No events today\n";
		return;
	}

	$ci = get_cohorts_info($course_id);

	foreach($list as $data) {
		foreach($ci["sections"] as $section) {
			if($section["name"] == $data["cohort"]) {
				$lc_email = "";
				$lc_login_email = "";
				$students = array();
				foreach($section["enrollments"] as $enrollment) {
					if($enrollment["type"] == "TaEnrollment") {
						$lc_email = $enrollment["contact_email"];
						$lc_login_email = $enrollment["email"];
						if($lc_email == "")
							$lc_email = $enrollment["email"];
					} else if($enrollment["type"] == "StudentEnrollment") {
						$students[] = $enrollment;
					}
				}

				// if no lc, nobody to notify, can just skip.
				//if($lc_email == "")
				if(!isset($section["lc_phone"]) || $section["lc_phone"] == "") {
					echo "No phone for " . $section["name"] . "\n";
					continue;
				}

				$res = load_attendance_result($course_id, $data["event"], $students);

				//if(gmddate(DATE_ISO8601
				$when = strtotime($data["end_at"]);
				// if the event ends in the next 15-30 mins
				if($now - $when > -20 * 60 && $now - $when < 60 * 20) {
					echo "Event {$data["event"]} happening for cohort {$section["name"]}...\n";
					if($res["percent"] == 0) {
						// nag necessary - 0% surely means no attendance was taken
						$excused = get_lc_excused_status($res["event_id"], $lc_email);
						// check both emails in case the excuse was filed under the login or contact one...
						if(!$excused["excused"])
							$excused = get_lc_excused_status($res["event_id"], $lc_login_email);

						if($excused["excused"]) {
							// if excused, just log it
							echo "Attendance needed for: " . $data["cohort"] . " " . $section["lc_phone"] . " " . $lc_email . " **LC EXCUSED**\n";
						} else {
							// and if not excused, go ahead and nag them.
							switch($notify_method) {
								case "sms":
									echo "TEXTING FOR: " . $data["cohort"] . " " . $section["lc_phone"] . " " . $lc_email . "\n";
									send_sms($section["lc_phone"], "Don't forget to record attendance for tonight's Braven event! https://kits.bebraven.org/attendance.php?event_id={$res["event_id"]}");
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
}

// I want to run the cron at the :05 of every hour.

date_default_timezone_set("UTC");

echo "*****\nRunning at " . date('r') . "\n";

foreach($braven_courses as $name => $course_id) {
	echo "Checking course $course_id\n";
	check_attendance_from_canvas($course_id, $WP_CONFIG["ATTENDANCE_TRACKER_NOTIFY_METHOD"]);
}
