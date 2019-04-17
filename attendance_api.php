<?php
require_once("attendance_shared.php");
date_default_timezone_set("UTC");

if(!isset($_REQUEST["access_token"]) || $_REQUEST["access_token"] != $WP_CONFIG["ATTENDANCE_API_KEY"]) {
	die("Unauthorized");
}

// given a list of student user ids and a course...

$course_id = (int) $_REQUEST["course_id"];
$users = array();
foreach($_REQUEST["user_id"] as $uid)
	$users[] = array("id" => (int) $uid);

// I want events and statuses.

$response = array("events" => get_all_events($course_id), "statuses" => array());

$events_in_the_past = 0;
foreach($response["events"] as $event) {
	if($event["event_time"] && strtotime($event["event_time"]) < time())
		$events_in_the_past++;
}

foreach($response["events"] as &$event) {
	$event["statuses"] = load_student_status($event["id"], $users);
	foreach($event["statuses"] as $user_id => $user_status) {
		if(!isset($response["statuses"][$user_id]))
			$response["statuses"][$user_id] = array("events" => array(), "total" => 0, "events" => $events_in_the_past);
		$response["statuses"][$user_id]["events"][] = $user_status;
		if($user_status == "true") {
			// only if it is in the past do we want to set this count
			if($event["event_time"] && strtotime($event["event_time"]) < time())
				$response["statuses"][$user_id]["total"] += 1;
		}
	}
}

header("Content-Type: application/json; charset=utf-8");

echo json_encode($response);
