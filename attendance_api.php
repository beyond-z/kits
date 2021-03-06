<?php
require_once("attendance_shared.php");
date_default_timezone_set("UTC");

function get_attendance_api_result($course_id, $users) {

    // I want events and statuses.

    $response = array("events" => get_all_events($course_id), "statuses" => array());
    $events_in_the_past = 0;
    foreach($response["events"] as $event) {
        if($event["event_time"] && strtotime($event["event_time"]) < time())
            $events_in_the_past++;
    }

    foreach($response["events"] as &$event) {
        $event["statuses"] = load_student_status($event["id"], $users)["result"];
        foreach($event["statuses"] as $user_id => $user_status) {
            if(!isset($response["statuses"][$user_id]))
                $response["statuses"][$user_id] = array("events" => array(), "total_present" => 0, "total_events" => $events_in_the_past);
            $response["statuses"][$user_id]["events"][] = $user_status;
            if($user_status === "true" || $user_status === "late") {
                // only if it is in the past do we want to set this count
                if($event["event_time"] && strtotime($event["event_time"]) < time())
                    $response["statuses"][$user_id]["total_present"] += 1;
            }
        }
    }

    return $response;
}

if(php_sapi_name() == 'cli') {
    // if calling it from the command line, instead sync it with Canvas
    // via the api bridges

    echo "\n*****\nRunning at " . date('r') . "\n";

    function set_canvas_attendance_info($course_id, $column_number, $uid, $text) {
        $ch = curl_init();
    $baseUrl = getPortalBaseUrl();
    $url = $baseUrl . '/api/v1/courses/'.$course_id.'/custom_gradebook_columns/'.$column_number.'/data/'.$uid;

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, urlencode("column_data[content]")."=".urlencode($text) . '&access_token='.urlencode(CANVAS_TOKEN));
        $answer = curl_exec($ch);
        curl_close($ch);
    }

    function get_canvas_attendance_column_id($course_id) {
        // first, try to get an existing one called Attendance and return ID
        // and if it isn't there, go ahead and create one and return the ID
        $ch = curl_init();
    $baseUrl = getPortalBaseUrl();
    $url = $baseUrl . '/api/v1/courses/'.$course_id.'/custom_gradebook_columns?access_token='.urlencode(CANVAS_TOKEN);

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $answer = curl_exec($ch);
        curl_close($ch);

        $obj = json_decode($answer, true);
        foreach($obj as $item) {
            if($item["title"] == "Attendance")
                return $item["id"];
        }

        // not there, create a column

        $ch = curl_init();
    $baseUrl = getPortalBaseUrl();
    $url = $baseUrl . '/api/v1/courses/'.$course_id.'/custom_gradebook_columns';

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS,
            urlencode("column[title]")."=Attendance" .
            '&'.urlencode("column[read_only]")."=true" .
            '&access_token='.urlencode(CANVAS_TOKEN));
        $answer = curl_exec($ch);
        curl_close($ch);

        $obj = json_decode($answer, true);

        return $obj["id"];
    }

    foreach($braven_courses as $course_id) {

        // Note: this is also painfully slow, but this script is only run once a week in the middle
        // of the night from a cronjob on the Kits server, so for now it's fine.
        echo "###  Calling: populate_times_from_canvas(course_id=".$course_id.")\n";
        populate_times_from_canvas($course_id);

        echo "###  Calling: get_cohorts_info(course_id=".$course_id.")\n";
        $cohort_info = get_cohorts_info($course_id);

        $column_number = get_canvas_attendance_column_id($course_id);

        echo "###  Loading attendance data to send for course_id=".$course_id.")\n";
        $result = get_attendance_api_result($course_id, $cohort_info["students"]);


        // TODO: this is brutally slow since it loops over all students in all active courses and updates the value through the API one at a time.
        // Either figure out how to set the values in bulk or maybe check timestamps of last time 
        // data was sent (or query canvas for last time value was updated on the canvas end)
        // vs last time attendance status was updated and only send new ones.
        // Or maybe just pull all attendance data from canvas in one go per course, then only send updates for changed values.
        foreach($result["statuses"] as $uid => $status) {
            $attendance_data = $status["total_present"] . "/". $status["total_events"];
            echo "  Calling set_canvas_attendance_info(course_id=" . $course_id . ", uid=" . $uid . ", attendance=". $attendance_data .")\n";
            set_canvas_attendance_info($course_id, $column_number, $uid, $attendance_data);
        }
    }
} else {
    // if calling via web, we do an access token chck

    if(!isset($_REQUEST["access_token"]) || $_REQUEST["access_token"] != ATTENDANCE_API_KEY) {
        die("Unauthorized");
    }

    // given a list of student user ids and a course...

    $course_id = (int) $_REQUEST["course_id"];
    $users = array();
    foreach($_REQUEST["user_id"] as $uid)
        $users[] = array("id" => (int) $uid);

    $response = get_attendance_api_result($course_id, $users);

    header("Content-Type: application/json; charset=utf-8");

    echo json_encode($response);
}
