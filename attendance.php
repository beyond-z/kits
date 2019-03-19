<?php
require_once("attendance_shared.php");

/*

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
CREATE DATABASE IF NOT EXISTS `braven_attendance` DEFAULT CHARACTER SET latin1 COLLATE latin1_swedish_ci;
USE `braven_attendance`;

START TRANSACTION;

SET NAMES utf8mb4;

	CREATE TABLE attendance_events (
		id INTEGER NOT NULL AUTO_INCREMENT,

		course_id INTEGER,
		cohort TEXT NULL, -- if not null, it applies only to this cohort

		name TEXT NOT NULL,
		event_time DATETIME,

		sort_mode INTEGER NOT NULL DEFAULT 0,
		send_nags BOOLEAN NOT NULL DEFAULT TRUE,

		PRIMARY KEY (id)
	) DEFAULT CHARACTER SET=utf8mb4;

	CREATE TABLE attendance_people (
		event_id INTEGER NOT NULL,
		person_id INTEGER NOT NULL,
		present INTEGER NULL, -- null means unknown, 0 means no, 1 means there, 2 means late
		dummy_id INTEGER NOT NULL AUTO_INCREMENT,
		PRIMARY_KEY (dummy_id),
		UNIQUE KEY unique_index (event_id, person_id),
		FOREIGN KEY (event_id) REFERENCES attendance_events(id) ON DELETE CASCADE
	) DEFAULT CHARACTER SET=utf8mb4;

	CREATE TABLE attendance_lc_absences (
		event_id INTEGER NOT NULL,
		lc_email VARCHAR(80) NOT NULL,

		substitute_name TEXT NULL,
		substitute_email TEXT NULL,
		substitute_phone TEXT NULL,
		dummy_id INTEGER NOT NULL AUTO_INCREMENT,

		PRIMARY KEY (dummy_id),
		UNIQUE KEY unique_index (event_id, lc_email),
		FOREIGN KEY (event_id) REFERENCES attendance_events(id) ON DELETE CASCADE
	) DEFAULT CHARACTER SET=utf8mb4;

	CREATE TABLE attendance_people_statuses (
		id INTEGER AUTO_INCREMENT,
		person_id INTEGER NOT NULL,
		course_id INTEGER,
		as_of DATETIME NOT NULL,
		status VARCHAR(16),

		PRIMARY KEY(id)
	) DEFAULT CHARACTER SET=utf8mb4;

	CREATE TABLE attendance_nag_log (
		id INTEGER AUTO_INCREMENT,

		event_id INTEGER NULL,
		date_sent TIMESTAMP NOT NULL,

		lc_email VARCHAR(80) NOT NULL,

		raw_response TEXT NULL,

		-- I want to keep the log even if the association is lost so we know who got spammed for deleted stuff.
		FOREIGN KEY (event_id) REFERENCES attendance_events(id) ON DELETE SET NULL,

		PRIMARY KEY(id)
	) DEFAULT CHARACTER SET=utf8mb4;

	CREATE INDEX nag_by_email ON attendance_nag_log(lc_email);
	CREATE INDEX nag_by_time ON attendance_nag_log(date_sent);

	COMMIT;
*/

session_start();

date_default_timezone_set("UTC");

function set_event_sort_setting($event_id, $sort_mode) {
	global $pdo;
	$statement = $pdo->prepare("
		UPDATE
			attendance_events
		SET
			sort_mode = ?
		WHERE
			id = ?
	");
	$statement->execute(array($sort_mode, $event_id));
}

function set_event_nag_setting($event_id, $send_nags) {
	global $pdo;
	$statement = $pdo->prepare("
		UPDATE
			attendance_events
		SET
			send_nags = ?
		WHERE
			id = ?
	");
	$statement->execute(array($send_nags, $event_id));
}

function set_lc_excused_status($event_id, $lc_email, $is_excused, $substitute_name, $substitute_email, $substitute_phone) {
	global $pdo;

	if($is_excused) {

		$statement = $pdo->prepare("
			INSERT INTO attendance_lc_absences
				(event_id, lc_email, substitute_name, substitute_email, substitute_phone)
			VALUES
				(?, ?, ?, ?, ?)
			ON DUPLICATE KEY UPDATE
				substitute_name = ?,
				substitute_email = ?,
				substitute_phone = ?
		");

		$statement->execute(array(
			// for the key
			$event_id,
			$lc_email,
			// for the insert
			$substitute_name,
			$substitute_email,
			$substitute_phone,
			// for the update
			$substitute_name,
			$substitute_email,
			$substitute_phone
		));
	} else {
		// not excused = delete the excused row
		$statement = $pdo->prepare("
			DELETE FROM attendance_lc_absences
			WHERE
				event_id = ?
				AND
				lc_email = ?
		");

		$statement->execute(array(
			$event_id,
			$lc_email
		));
	}
}

function set_special_status($course_id, $student_id, $override) {
	global $pdo;

	$statement = $pdo->prepare("
		INSERT INTO attendance_people_statuses
			(person_id, course_id, as_of, status)
		VALUES
			(?, ?, NOW(), ?)
	");

	$statement->execute(array(
		$student_id,
		$course_id,
		$override
	));

}

function set_attendance($event_id, $person_id, $present) {
	global $pdo;

	$statement = $pdo->prepare("
		INSERT INTO attendance_people
			(event_id, person_id, present)
		VALUES
			(?, ?, ?)
		ON DUPLICATE KEY UPDATE
			present = ?
	");

	$statement->execute(array(
		$event_id,
		$person_id,
		$present,
		$present
	));

	// We need to email staff if stuff changes after about thrusday at noon the week of the event.
	$ei = get_event_info($event_id);
	if($ei["event_time"]) {
		$dt = new DateTime($ei["event_time"]);
		$dt->modify("thursday noon"); // adjust to thursday when the report is pulled
		$dt->modify("+7 hours"); // timezone adjustment from UTC; aims for 1pm pacific

		if(time() > $dt->format("U")) {
			mail("attendance-notifications@bebraven.org", "Attendance Changed", "{$ei["name"]} on $event_id changed the attendance report (user $person_id is marked ".($present ? "present" : "not present").")");
		}
	}


}

function get_event_info($event_id) {
	global $pdo;

	$statement = $pdo->prepare("
		SELECT
			id, name, event_time, course_id, send_nags, sort_mode
		FROM
			attendance_events
		WHERE
			id = ?
	");

	$statement->execute(array($event_id));
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		return $row;
	}

	return null;
}

function get_event_info_by_name($course_id, $event_name) {
	global $pdo;

	$statement = $pdo->prepare("
		SELECT
			id, name, event_time, course_id, send_nags, sort_mode
		FROM
			attendance_events
		WHERE
			course_id = ?
			AND
			name = ?
	");

	$statement->execute(array($course_id, $event_name));
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		return $row;
	}

	create_event($course_id, $event_name);

	return get_event_info_by_name($course_id, $event_name);
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



function create_event($course_id, $event_name) {
	global $pdo;

	$statement = $pdo->prepare("
		INSERT INTO attendance_events
			(course_id, name)
		VALUES
			(?, ?)
	");

	$statement->execute(array(
		$course_id,
		$event_name
	));
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

	$result = array();
	$original_result = array();

	foreach($students as $student) {
		$result[$student] = "false";
		$original_result[$student] = "false";
	}

	$statement->execute($args);
	while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
		// format it as a bool string here so we don't have to get ambiguity later with override strings
		$result[$row["person_id"]] = $row["present"] ? "true" : "false";
		$original_result[$row["person_id"]] = $row["present"] ? "true" : "false";
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

	return $result;
}

function bz_current_full_url() {
	$url = "http";
	if(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on")
		$url .= "s";
	$url .= "://";
	$url .= $_SERVER["HTTP_HOST"];
	$url .= $_SERVER["PHP_SELF"];
	$url .= "?";
	$url .= $_SERVER["QUERY_STRING"];
	return $url;
}

function sso() {
	global $WP_CONFIG;

	if(isset($_SESSION["sso_service"]) && isset($_SESSION["coming_from"]) && isset($_GET["ticket"])) {
		// validate ticket from the SSO server

		$ticket = $_GET["ticket"];
		$service = $_SESSION["sso_service"];
		$coming_from = $_SESSION["coming_from"];
		unset($_SESSION["sso_service"]);
		unset($_SESSION["coming_from"]);

		$content = file_get_contents("https://{$WP_CONFIG["BRAVEN_SSO_DOMAIN"]}/serviceValidate?ticket=".urlencode($ticket)."&service=".urlencode($service));

		$xml = new DOMDocument();
		$xml->loadXML($content);
		$user = $xml->getElementsByTagNameNS("*", "user")->item(0)->textContent;

		// login successful
		$_SESSION["user"] = $user;
			//echo "User " . htmlentities($user) . " is not authorized. Try logging out of SSO first.";

		header("Location: " . $coming_from);
		exit;
	} else if(isset($_SESSION["coming_from"]) && !isset($_SESSION["sso_service"])) {
		$ssoService = bz_current_full_url() . "&dosso";
		$_SESSION["sso_service"] = $ssoService;
		header("Location: https://{$WP_CONFIG["BRAVEN_SSO_DOMAIN"]}/login?service=" . urlencode($ssoService));
		exit;
	} // otherwise it is just an api thing for other uses
}

// returns the currently logged in user, or redirects+exits to SSO
function requireLogin() {
	if(!isset($_SESSION["user"])) {

		if(isset($_POST["operation"]) && $_POST["operation"] == "save") {
			// this is them trying to save attendance with a bad session.
			// need to tell it to log in somehow
			echo "{\"error\":\"not_logged_in\"}";
			exit;
		}


		if((!isset($_GET["tried_reload"]) || $_GET["tried_reload"] < 20) && isset($_GET["event_name"]) && strpos($_GET["event_name"], "1:1") !== FALSE) {
			$count = 0;
			if(isset($_GET["tried_reload"]))
				$count = $_GET["tried_reload"];
			$count++;
			// this is a filthy hack, but since SSO is a shared resource, we don't
			// want to hit it twice at the same time. We put the 1:1 event right next
			// to the regular events on the page a lot, and the browser loads them
			// simultaneously. This hack just makes the 1:1 wait then refresh - allowing
			// the other one to finish first, then we get the session from that.
			echo "Please wait, confirming log in... <script>window.setTimeout(function() { location.href = location.href + '&tried_reload=$count'; }, 2000);</script>";
			exit;
		}

		if(!isset($_GET["dosso"])) {
			$_SESSION["coming_from"] = bz_current_full_url();
			unset($_SESSION["sso_service"]);
		}
		sso();
		exit;
	}
	return $_SESSION["user"];
}

requireLogin();

	function url_for_course($course) {
		$url = "attendance.php?";
		if((int) $course)
			$url .= "course_id=".urlencode($course);
		else
			$url .= "course_name=".urlencode($course);

		foreach($_GET as $k => $v) {
			if($k == "course_id")
				continue;
			if($k == "course_name")
				continue;

			$url .= "&" . urlencode($k) . "=" . urlencode($v);
		}

		return $url;
	}

	function get_user_course_id($email) {
		global $WP_CONFIG;
		global $braven_courses;

		$ch = curl_init();
		$url = 'https://'.$WP_CONFIG["BRAVEN_PORTAL_DOMAIN"].'/bz/courses_for_email?email='.(urlencode($email)). '&access_token=' . urlencode($WP_CONFIG["CANVAS_TOKEN"]);
		// Change stagingportal to portal here when going live!
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		$answer = curl_exec($ch);
		curl_close($ch);

		// trim off any cross-site get padding, if present,
		// keeping just the json object
		$answer = substr($answer, strpos($answer, "{"));
		$obj = json_decode($answer, TRUE);

		$arr = $obj["course_ids"];
		$cnt = count($arr);

		foreach($arr as $i)
			if(in_array($i, array_values($braven_courses)))
				return $i;
		if($cnt == 0)
			return 0;
		return $arr[$cnt - 1];
	}

	if(isset($_POST["operation"])) {
		switch($_POST["operation"]) {
			case "save":
				set_attendance($_POST["event_id"], $_POST["student_id"], $_POST["present"]);
			break;
			case "set_special_status":
				set_special_status($_POST["course_id"], $_POST["student_id"], $_POST["override"]);
				header("Location: attendance.php?course_id=".urlencode($_POST["course_id"]));
			break;
			case "excuse":
				set_lc_excused_status($_POST["event_id"], $_POST["lc_email"], $_POST["excused"] == "true", null, null, null);
				header("Location: attendance.php?event_id=".urlencode($_POST["event_id"])."&lc=".urlencode($_POST["lc_email"])."&course_id=".urlencode($_POST["course_id"]));
			break;
			case "change_nag_setting":
				// send_nags
				set_event_nag_setting($_POST["event_id"], $_POST["send_nags"]);
				header("Location: attendance.php?event_id=".urlencode($_POST["event_id"])."&lc=".urlencode($_POST["lc_email"])."&course_id=".urlencode($_POST["course_id"]));
			break;
			case "change_sort_mode":
				// sort_mode
				set_event_sort_setting($_POST["event_id"], $_POST["sort_mode"]);
				header("Location: attendance.php?event_id=".urlencode($_POST["event_id"])."&lc=".urlencode($_POST["lc_email"])."&course_id=".urlencode($_POST["course_id"]));
			break;
			default:
				// this space intentionally left blank
		}
		exit;
	}


	$is_staff = strpos($_SESSION["user"], "@bebraven.org") !== FALSE || strpos($_SESSION["user"], "@beyondz.org") !== FALSE;

	// this is set temporarily to figure out the course, then later lc_email is set again
	$lc_email = ($is_staff && isset($_REQUEST["lc"]) && $_REQUEST["lc"] != "") ? $_REQUEST["lc"] : $_SESSION["user"];
	$course_id = 0;
	if(isset($_GET["course_id"]))
		$course_id = $_GET["course_id"];
	else if(isset($_GET["course_name"])) {
		foreach($braven_courses as $name => $id) {
			if($name == $_GET["course_name"]) {
				$course_id = $id;
				break;
			}
		}
	}

	if($course_id == 0) {
		$course_id = get_user_course_id($lc_email);
		if($course_id == 0) {
		?>
			Select your course:<br /><br />

			<a href="<?php echo url_for_course('sjsu'); ?>">SJSU</a><br />
			<a href="<?php echo url_for_course('run'); ?>">R-UN</a><br />
			<a href="<?php echo url_for_course('nlu'); ?>">NLU</a><br />
		<?php
		} else {
			// redirect to their course
			header("Location: " . url_for_course($course_id));
		}
		exit;
	}

	$event_id = 0;
	$event_name = "";
	$event_info = null;
	if(isset($_GET["event_id"]) && $_GET["event_id"] != "") {
		$event_id = $_GET["event_id"];
		$event_info = get_event_info($event_id);
		$event_name = $event_info["name"];
		$single_event = true;
	} else if(isset($_GET["event_name"]) && $_GET["event_name"] != "") {
		$event_name = $_GET["event_name"];
		$event_info = get_event_info_by_name($course_id, $event_name);
		$event_id = $event_info["id"];
		$single_event = true;
	} else {
		$single_event = false;
	}

	$cohort_info = get_cohorts_info($course_id);

	$can_see_all = $is_staff || isTa($_SESSION["user"], $cohort_info);

	// now lc_email is set again based on the new knowledge about who is a TA in the course
	// so both sets of this lc_email variable are important!
	$lc_email = ($can_see_all && isset($_REQUEST["lc"]) && $_REQUEST["lc"] != "") ? $_REQUEST["lc"] : $_SESSION["user"];

	function get_student_list($lc, $sort_method = 1) {
		global $cohort_info;

		$already_there = array();

		$list = array();
		$keep_this_one = false;
		foreach($cohort_info["sections"] as $section) {
			$students = array();
			foreach($section["enrollments"] as $enrollment) {
				$enrollment["lc_name"] = $section["lc_name"];
				$enrollment["lc_email"] = $section["lc_email"];
				$enrollment["section_name"] = $section["name"];
				if($enrollment["type"] == "TaEnrollment") {
					if($lc != null && ($enrollment["lc_email"] == $lc || $enrollment["email"] == $lc || $enrollment["contact_email"] == $lc))
						$keep_this_one = true;
				}
				if($enrollment["type"] == "StudentEnrollment") {
					$students[] = $enrollment;
					// filter duplicate IDs; can happen on NLU for example
					// where students are in two cohorts. The above one is NOT
					// filtered because that is cohort-specific, but the bottom
					// one IS filtered because that is a global list.
					if(!isset($already_there[$enrollment["id"]])) {
						$list[] = $enrollment;
						$already_there[$enrollment["id"]] = count($list) - 1;
					} else {
						// it is there, but is the item we have now better?
						// specifically, I want to have the LC email if we can.
						$idx = $already_there[$enrollment["id"]];
						if(!isset($list[$idx]["lc_email"]) || $list[$idx]["lc_email"] == "") {
							$list[$idx] = $enrollment;
							$already_there[$enrollment["id"]] = count($list) - 1;
						}
					}
				}
			}
			if($keep_this_one) {
				usort($students, "cmp".(int)$sort_method);
				return $students;
			}
		}
		unset($section);
		usort($list, "cmp".(int)$sort_method);
		return $lc == null ? $list : array();
	}

	// note "cmp".(int)$sort_method
	function cmp0($a, $b) {
		$lc = strcmp($a["lc_name"], $b["lc_name"]);
		if($lc != 0)
			return $lc;

		return strcmp($a["name"], $b["name"]);
	}

	function cmp1($a, $b) {
		return strcmp($a["name"], $b["name"]);
	}




	if(!isset($_GET["download"])) {
		$student_list = array();
		$student_status = array();
		$student_list = get_student_list(((!isset($_GET["lc"]) || $_GET["lc"] == "All") && $can_see_all) ? null : $lc_email, $event_info ? $event_info["sort_mode"] : 0);
		if($event_id) {
			$student_status[$event_id] = load_student_status($event_id, $student_list);
		} else {
			$events = get_all_events($course_id);
			foreach($events as $event) {
				$student_status[$event["id"]] = load_student_status($event["id"], $student_list);
			}
		}
	}

	if($is_staff && isset($_GET["download"])) {
		$fp = fopen("php://output", "w");
		ob_start();

		$events = get_all_events($course_id);
		$headers = array("Student Name", "Student Email", "Course ID", "LC Name", "LC Email");
		foreach($events as $event)
			$headers[] = $event["name"];

		fputcsv($fp, $headers);

		$lcs = $cohort_info["lcs"];
		foreach($lcs as $lc) {
			$lc_email = $lc["email"];
			$student_list = get_student_list($lc_email);
			$student_status = array();
			foreach($events as $event) {
				$student_status[$event["id"]] = load_student_status($event["id"], $student_list);
			}
			foreach($student_list as $student) {
				$data = array();
				$data[] = $student["name"];
				$data[] = $student["email"];
				$data[] = $course_id;

				// use data from spreadsheet if available, use TA listing if not
				if(isset($student["lc_name"]) && $student["lc_name"] != "")
					$data[] = $lc["name"];
				else
					$data[] = $student["lc_name"];
				if(isset($student["lc_email"]) && $student["lc_email"] != "")
					$data[] = $lc["email"];
				else
					$data[] = $student["lc_email"];
				// done

				foreach($events as $event) {
					$data[] = $student_status[$event["id"]][$student["id"]];
				}

				fputcsv($fp, $data);
			}
		}

		$string = ob_get_clean();
		$filename = 'attendance_' . $course_id . "_" . date('Ymd') .'_' . date('His');
		// Output CSV-specific headers
		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Cache-Control: private",false);
		header("Content-Type: text/csv");
		header("Content-Disposition: attachment; filename=\"$filename.csv\";" );
		header("Content-Transfer-Encoding: binary");
		exit($string);
	}

	function checkbox_for($student, $event_id) {
		global $student_status;
		$sta = $student_status[$event_id][$student["id"]];
		if($sta == "true" || $sta == "false" || $sta == "") {
		?>
			<input
				onchange="recordChange(this, this.getAttribute('data-event-id'), this.getAttribute('data-student-id'), this.checked ? 1 : 0);"
				type="checkbox"
				data-event-id="<?php echo $event_id; ?>"
				data-student-name="<?php echo htmlentities($student["name"]); ?>"
				data-student-id="<?php echo htmlentities($student["id"]); ?>"
				<?php if($sta == "true") echo "checked=\"checked\""; ?>
			/>
		<?php
		} else {
			echo $sta;
		}
		return $sta == "true" || $sta == "W";
	}
?><!DOCTYPE html>
<html>
<head>
<title>Attendance Tracker</title>
<style>
	form.basic {
		display: inline;
	}

	form.basic input[type=submit] {
		padding: 0px;
		margin: 0px;
		color: #378383;
		background: none;
		border: none;
		font-size: 1rem;
		cursor: pointer;
		display: inline;
		vertical-align: initial;
	}

	body {
		font-family: Georgia, serif;
		line-height: 1.2em;
		margin: 8px;
		padding: 0;
	}

	ol {
		list-style: none;
		padding-left: 0;
	}

	li {
		margin: 8px 0px;
	}

	label, input {
		vertical-align: middle;
	}

	a {
		color: #378383;
		text-decoration: none;
	}

	a:hover {
		color: #046366;
		text-decoration: underline;
	}

	#withdrawn_dialog {
		position: fixed;
		left: 0px;
		right: 0px;
		top: 0px;
		bottom: 0px;
		background-color: rgba(0, 0, 0, 0.6);
		display: none;
	}
	#withdrawn_dialog > div {
		position: fixed;
		left: 25vw;
		right: 25vw;
		top: 25vh;
		bottom: 25vh;
		background: white;
		padding: 2em;
	}

</style>
<script>
	// FIXME: make this a multi-select UI and maybe be able to set the effective date
	function setSpecialStatus(course_id, student_id) {

		var dialog = document.getElementById("withdrawn_dialog");
		var form = dialog.querySelector("form");

		form.elements["course_id"].value = course_id;
		form.elements["student_id"].value = student_id;

		dialog.style.display = "block";

		/*
		var override = confirm("Mark the student as withdrawn from the course?");
		if(override !== null) {
			var http = new XMLHttpRequest();
			http.open("POST", location.href, true);

			var data = "";
			data += "operation=" + encodeURIComponent("set_special_status");
			data += "&";
			data += "course_id=" + encodeURIComponent(course_id);
			data += "&";
			data += "student_id=" + encodeURIComponent(student_id);
			data += "&";
			data += "override=" + encodeURIComponent("W");

			http.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");

			http.onerror = function() {
				alert("Save fail");
			};
			http.onload = function() {
			};

			http.send(data);
		}
		*/

		return false;
	}

	function recordChange(ele, event_id, student_id, present) {
		ele.parentNode.classList.add("saving");
		ele.parentNode.classList.remove("error-saving");
		var http = new XMLHttpRequest();
		http.open("POST", location.href, true);

		var data = "";
		data += "operation=" + encodeURIComponent("save");
		data += "&";
		data += "event_id=" + encodeURIComponent(event_id);
		data += "&";
		data += "student_id=" + encodeURIComponent(student_id);
		data += "&";
		data += "present=" + encodeURIComponent(present);

		http.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
		http.onerror = function() {
			ele.parentNode.classList.remove("saving");
			ele.parentNode.classList.add("error-saving");
			alert('It didn\'t save. Please make sure you are online and try again.');
		};
		http.onload = function() {
			if(http.responseText == "{\"error\":\"not_logged_in\"}") {
				alert('Your login session expired. Please refresh the page and log back in, then finish attendance.');
				return;
			}
			ele.parentNode.classList.remove("saving");
			ele.parentNode.classList.add("saved");
			setTimeout(function() {
				ele.parentNode.classList.remove("saved");
			}, 1000);


			var total = 0;
			var there = 0;
			var p = ele;
			while(p && p.tagName != "TR") {
				p = p.parentNode;
			}
			if(p) {
				var inputs = p.querySelectorAll("input");
				for(var a = 0; a < inputs.length; a++) {
					total++;
					if(inputs[a].checked)
						there++;
				}
				p.querySelector(".percent").textContent = Math.round(there * 100 / total);


				var pe = document.getElementById("percent-" + ele.getAttribute("data-event-id"));
				pe.setAttribute("data-there", (pe.getAttribute("data-there")|0) + (ele.checked ? 1 : -1));
				pe.textContent = Math.round(pe.getAttribute("data-there") * 100 / pe.getAttribute("data-total"));
			}
		};
		http.send(data);
	}
</script>
<style>
	.saving {
		transition: all ease-out 1s;
		background-color: #666;
	}

	.saved {

		transition: all ease-out 1s;
		background-color: #0f0;
	}

	.error-saving {
		transition: all ease-out 1s;
		background-color: #f00;
	}

	table {
		border-collapse: collapse;
		margin-top: 1em;
	}

	td, th {
		border: solid 1px black;
		padding: 0.25em;
	}

	td {
		text-align: center;
	}

	tr:not(:first-child) th:first-child,
	td:first-child {
		text-align: right;
	}
</style>
</head>
<body>
	<!--
	Cohort: Course 41, lc@bebraven.org
	Event: LL 1, June 21

	So they display for any given cohort lists
		Event       Event      Event 	Percentage
	Name     [x]         [x]        [x]
	Name
	Name
	Percentage

	It can also display just one column at a time.
	-->

	<?php
		if(count($student_list) != 0) {
	?>
		Attendance for <?php echo htmlentities($single_event ? $event_name : "all LLs/events"); ?>
	<?php
		}

		if($can_see_all) {
	?>
		<form>
			<input type="hidden" name="course_id" value="<?php echo (int) $course_id; ?>" />
			<input type="hidden" name="event_name" value="<?php echo htmlentities($event_name); ?>" />
			<select name="lc">
				<option>All</option>
				<?php
					usort($cohort_info["lcs"], "cmp1");
					$lcs = $cohort_info["lcs"];
					foreach($lcs as $lc) {
						?>
							<option value="<?php echo htmlentities($lc["email"]); ?>"
								<?php
									if($lc["email"] == $lc_email)
										echo "selected";
								?>
							>
								<?php echo htmlentities($lc["name"]); ?>
							</option>
						<?php
					}
				?>
			</select>
			<input type="submit" value="Switch Cohort" />
		</form>
	<?php
		}

		if(count($student_list) == 0) {
			echo "<p>The cohort leader should now take attendance.</p>";
		} else {
	?>

		<?php
			$tag = "li";
			$columns = 0;
			if($single_event) {
				$tag = "li";
				echo "<ol>";
			} else {
				echo "<table>";
				echo "<tr><th>Student</th>";
				$columns++;
				foreach($events as $event) {
					echo "<th><a href=\"attendance.php?course_id=".urlencode($course_id)."&amp;lc=".urlencode($lc_email)."&amp;event_name=".urlencode($event["name"])."\">".htmlentities($event["name"])."</a></th>";
					$columns++;
				}
				$columns++;
				echo "<th>Total</th>";
				echo "</tr>";
				$tag = "td";
			}
			$last_lc = "";
			foreach($student_list as $student) {
				if($tag == "li") {
					if($event_info && $event_info["sort_mode"] == 0 && $student["lc_name"] != $last_lc) {
						echo "<h4>".(htmlentities($student["section_name"]))."</h4>";
						$last_lc = $student["lc_name"];
					}

					echo "<li><label>";
				} else {
					if($student["lc_name"] != $last_lc) {
						echo "<tr><th style=\"text-align: left;\" colspan=\"".($columns)."\"><abbr title=\"".(htmlentities($student["lc_name"]))."\">".htmlentities($student["section_name"])."</abbr> ";

						$nag_info = get_nag_info($student["lc_email"]);

						$nag_count = $nag_info["count"];
						$last_nag = $nag_info["last"];
						$last_nag_recent = $nag_info["recent"];

						if($nag_count > 0) {
							echo "<abbr ".($last_nag_recent ? "" : "style=\"color: #999;\"")." title=\"$nag_count nag".(($nag_count == 1) ? "" : "s").", last $last_nag\">";
							echo "&#9993;";
							echo "</abbr>";
						}

						echo "</th>";
						echo "</tr>";
						$last_lc = $student["lc_name"];
					}
					echo "<tr>";
					echo "<td>";
					if($is_staff) {
						echo "<a href=\"#\" onclick=\"return setSpecialStatus(".htmlentities($course_id).", ".htmlentities(json_encode($student["id"])).");\">";
						echo htmlentities($student["name"]);
						echo "</a>";
					} else {
						echo htmlentities($student["name"]);
					}
					echo "</td>";
				}

				if($single_event)
					checkbox_for($student, $event_id);
				else {
					$sthere = 0;
					$stotal = 0;
					foreach($events as $event) {
						$stotal += 1;
						echo "<td>";
						$sthere += checkbox_for($student, $event["id"]) ? 1 : 0;
						echo "</td>";
					}

					echo "<td><span class=\"percent\">" . round($sthere * 100 / $stotal) . "</span>%</td>";
				}

				if($tag == "li")
					echo htmlentities($student["name"]);
			?>
		<?php
			if($tag == "li")
				echo "</label></li>";
			else
				echo "</tr>";
			}
			if($tag == "li") {
				echo "</ol><a href=\"attendance.php?course_id=$course_id&amp;lc=".urlencode($lc_email)."\" target=\"_BLANK\">See All LLs/Events</a>";
				if($is_staff) echo " | ";
			} else {
				echo "<tr><th>Total</th>";
				foreach($events as $event) {
					echo "<td>";
					$there = 0;
					$total = 0;
					foreach($student_status[$event["id"]] as $status) {
						if($status === "true" || $status === "false" || $status === "") {
							$total += 1;
							if($status === "true")
								$there += 1;
						}
					}
					echo "<span data-total=\"$total\" data-there=\"$there\" id=\"percent-{$event["id"]}\" class=\"percent\">" . round($there * 100 / $total) . "</span>%";
					echo "</td>";
				}
				echo "<td></td>";
				echo "</tr>";
				echo "</table>";
			}
			if($is_staff) { ?>
				<a href="attendance.php?course_id=<?php echo (int) $course_id;?>&download=csv">Download CSV</a>
				<?php if($single_event) { ?>
				|
				<form class="basic" method="POST">
					<input type="hidden" name="lc_email" value="<?php echo htmlentities($lc_email); ?>" />
					<input type="hidden" name="event_id" value="<?php echo htmlentities($event_id); ?>" />
					<input type="hidden" name="course_id" value="<?php echo htmlentities($course_id); ?>" />
					<input type="hidden" name="operation" value="excuse" />
					<?php
						$lc_excused_status = get_lc_excused_status($event_id, $lc_email);
						if($lc_excused_status["excused"] == true) {
						?>
							LC Excused
							<input type="hidden" name="excused" value="false" />
							<input type="submit" value="[Undo]" />
						<?php
						} else {
						?>
							<input type="hidden" name="excused" value="true" />
							<input type="submit" value="Excuse LC From This Event" />
					<?php
						}
					?>
				</form>
				|
				<form class="basic" method="POST">
					<input type="hidden" name="lc_email" value="<?php echo htmlentities($lc_email); ?>" />
					<input type="hidden" name="event_id" value="<?php echo htmlentities($event_id); ?>" />
					<input type="hidden" name="course_id" value="<?php echo htmlentities($course_id); ?>" />
					<input type="hidden" name="operation" value="change_sort_mode" />
					<?php if($event_info["sort_mode"] == 0) { ?>
						<input type="hidden" name="sort_mode" value="1" />
						<input type="submit" value="Sort By Name" />
					<?php } else { ?>
						<input type="hidden" name="sort_mode" value="0" />
						<input type="submit" value="Sort By LC" />
					<?php } ?>

				</form>
				|
				<form class="basic" method="POST">
					<input type="hidden" name="lc_email" value="<?php echo htmlentities($lc_email); ?>" />
					<input type="hidden" name="event_id" value="<?php echo htmlentities($event_id); ?>" />
					<input type="hidden" name="course_id" value="<?php echo htmlentities($course_id); ?>" />
					<input type="hidden" name="operation" value="change_nag_setting" />
					<?php if($event_info["send_nags"] == 0) { ?>
						<input type="hidden" name="send_nags" value="1" />
						<input type="submit" value="Enable Auto-Nags" />
					<?php } else { ?>
						<input type="hidden" name="send_nags" value="0" />
						<input type="submit" value="Disable Auto-Nags" />
					<?php } ?>

				</form>
				<?php
				}
			}
		}
		?>

<div id="withdrawn_dialog">
	<div>
		<form method="POST">
			Set status to:
			<input type="hidden" name="operation" value="set_special_status" />
			<input type="hidden" name="course_id" value="" />
			<input type="hidden" name="student_id" value="" />
			<select name="override">
				<option value="W">Withdrawn</option>
				<option value="">Normal</option>
			</select>
			<input type="submit" value="Update" />

			<br />
			<br />
			<button type="button" onclick="document.getElementById('withdrawn_dialog').style.display = '';">Cancel</button>
		</form>
	</div>

</body>
</html>
