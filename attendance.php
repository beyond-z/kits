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
		reason TEXT NULL,
		updated_at TIMESTAMP NOT NULL DEFAULT now(),
		updated_by TEXT,
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

	CREATE TABLE attendance_courses (
		id INTEGER PRIMARY KEY, -- should match the canvas id
		late_threshold VARCHAR(40) DEFAULT '15 mins'
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

function set_reason($event_id, $person_id, $reason) {
	global $pdo;

	$statement = $pdo->prepare("
		INSERT INTO attendance_people
			(event_id, person_id, reason, updated_at, updated_by)
		VALUES
			(?, ?, ?, now(), ?)
		ON DUPLICATE KEY UPDATE
			reason = ?,
			updated_at = now(),
			updated_by = ?
	");

	$statement->execute(array(
		$event_id,
		$person_id,
		$reason,
		$_SESSION["user"],
		$reason,
		$_SESSION["user"]
	));
}

function set_attendance($event_id, $person_id, $present) {
	global $pdo;

	if($present == '')
		$present = null;

	$statement = $pdo->prepare("
		INSERT INTO attendance_people
			(event_id, person_id, present, updated_at, updated_by)
		VALUES
			(?, ?, ?, now(), ?)
		ON DUPLICATE KEY UPDATE
			present = ?,
			updated_at = now(),
			updated_by = ?
	");

	$statement->execute(array(
		$event_id,
		$person_id,
		$present,
		$_SESSION["user"],
		$present,
		$_SESSION["user"]
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
		$_SESSION["user"] = strtolower($user);
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

	$is_staff = strpos($_SESSION["user"], "@bebraven.org") !== FALSE || strpos($_SESSION["user"], "@beyondz.org") !== FALSE;

	if(isset($_POST["operation"])) {
		switch($_POST["operation"]) {
			case "reason":
				set_reason($_POST["event_id"], $_POST["student_id"], $_POST["reason"]);
			break;
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
			case "masquerade":
				$_SESSION["masquerading_user"] = $_SESSION["user"];
				$_SESSION["user"] = $_POST["as"];

				header("Location: attendance.php");
				exit;
			break;
			case "stop_masquerade":
				$_SESSION["user"] = $_SESSION["masquerading_user"];
				unset($_SESSION["masquerading_user"]);

				header("Location: {$_SERVER["HTTP_REFERER"]}");
				exit;
			break;
			default:
				// this space intentionally left blank
		}
		exit;
	}

	if((isset($_SESSION["masquerading_user"]) || $is_staff) && isset($_GET["operation"]) && $_GET["operation"] == "masquerade") {
		?>
		<form method="POST">
			<input type="hidden" name="operation" value="masquerade" />

			<input type="email" name="as" placeholder="user email" />

			<button type="submit">Masquerade</button>
		<?php
		exit;
	}

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

	function get_course_late_definition($course_id) {
		global $pdo;

		$statement = $pdo->prepare("
			SELECT
				late_threshold
			FROM
				attendance_courses
			WHERE
				id = ?
		");

		$statement->execute(array($course_id));
		while($row = $statement->fetch(PDO::FETCH_ASSOC)) {
			return $row["late_threshold"];
		}

		return "15 min";
	}

	$event_id = 0;
	$event_name = "";
	$event_info = null;
	$late_definition = get_course_late_definition($course_id);
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

		$lc = strtolower($lc);

		$already_there = array();

		$list = array();
		$keep_this_one = false;
		foreach($cohort_info["sections"] as $section) {
			$students = array();
			foreach($section["enrollments"] as $enrollment) {
				$enrollment["lc_name"] = $section["lc_name"];
				$enrollment["lc_email"] = strtolower($section["lc_email"]);
				$enrollment["section_name"] = $section["name"];
				// need to check non-enrollments for schwab's duo-LC setup
				if($lc != null && $enrollment["lc_email"] == $lc) {
					$keep_this_one = true;
				}
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
		$student_reasons = array();
		$student_list = get_student_list(((!isset($_GET["lc"]) || $_GET["lc"] == "All") && $can_see_all) ? null : $lc_email, $event_info ? $event_info["sort_mode"] : 0);
		if($event_id) {
			$tmp = load_student_status($event_id, $student_list);
			$student_status[$event_id] = $tmp["result"];
			$student_reasons[$event_id] = $tmp["reasons"];
		} else {
			$events = get_all_events($course_id);
			foreach($events as $event) {
				$student_status[$event["id"]] = load_student_status($event["id"], $student_list)["result"];
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
				$student_status[$event["id"]] = load_student_status($event["id"], $student_list)["result"];
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

	function status_is_changeable_by_user($sta) {
		return ($sta == "true" || $sta == "false" || $sta == "late" || $sta == "null" || $sta == "");
	}

?><!DOCTYPE html>
<html>
<head>
<title>Attendance Tracker</title>
<style>
	.main-attendance-view tr th:nth-child(4), .main-attendance-view tr:not(:last-child) td:nth-child(4) {
		background-color: rgba(226,226,226, 0.3);
	}
	.main-attendance-view tr th {
		padding-top: 15px;
	}
	.late.checked {
		background-color: white;
		border-color: #cecdcd;
	}
	.undo {
		border: none;
		background: transparent;
		color: #999;
		text-transform: uppercase;
		text-decoration: underline;
		cursor: pointer;
		visibility: hidden;
	}
	.boxes-container:hover .undo.possible {
		visibility: visible;
	}
	.attendance-individual > span:first-child {
		width: 10em;
	}
	.attendance-individual > span {
		display: inline-block;
		overflow: hidden;
		margin-right: 4px;
		vertical-align: top;
	}
	@media(min-width: 25em) {
		.attendance-individual > span {
			text-align: right;
		}
	}
	.attendance-individual input[type=radio] {
		vertical-align: baseline;
	}
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
		margin: 12px 0px;
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

	label {
		cursor: pointer;
	}

	.boxes-container {
		white-space: nowrap;
		display: block; /* fallback in case flex isn't supported */
		display: flex;
		font-size: 90%;
	}

	.boxes-container input {
		display: none;
	}

	.boxes-container input + span {
		display: inline-block;
		padding: 0.5em 0.75em;
		background-color: white;
		border-color: #cecdcd;
		border-style: solid;
	}

	.boxes-container .present span {
		border-width: 1px 1px 1px 1px;
		border-radius: .5em 0 0 .5em;
	}
	.boxes-container .absent span {
		border-width: 1px 1px 1px 1px;
		border-radius: 0 .5em .5em 0;
	}

	.boxes-container .present input:checked + span {
		background-color: #43cc6e;
		border-color: #2cb155;
	}

	.boxes-container .absent input:checked + span {
		background-color: #fb6a6a;
		border-color:#e04646;
	}

	.late{
		display: inline-block;
		padding: 0.5em 0.75em;
		background-color: transparent;
		border-color: transparent;
		border-radius: .5em;
		border-style: solid;
		border-width: 1px;
		font-size: 90%;
	}

	.late span {
		visibility: hidden;
	}

	.late:hover span,
	.late input:checked + span {
		visibility: visible;
	}

	input[name=reason] {
		box-sizing: border-box;
		width: 100%;
		font-size:90%;
	}

	tr.reason-description, 	tr.reason-description td {
		background-color:white;
		color: #999;
		font-style: italic;
	}

	input[name=reason]:not(:focus) {
		background: transparent;
		background-repeat: no-repeat;
		background-position: right center;
		padding-right: 24px;
		background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHg9IjBweCIgeT0iMHB4Igp3aWR0aD0iMjQiIGhlaWdodD0iMjQiCnZpZXdCb3g9IjAgMCAyNCAyNCIKc3R5bGU9IiBmaWxsOiMwMDAwMDA7Ij4gICAgPHBhdGggZD0iTSA3LjQyOTY4NzUgOS41IEwgNS45Mjk2ODc1IDExIEwgMTIgMTcuMDcwMzEyIEwgMTguMDcwMzEyIDExIEwgMTYuNTcwMzEyIDkuNSBMIDEyIDE0LjA3MDMxMiBMIDcuNDI5Njg3NSA5LjUgeiI+PC9wYXRoPjwvc3ZnPg==');
		border: none;
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

	function recordReason(ele, event_id, student_id, reason) {
		ele.parentNode.classList.add("saving");
		ele.parentNode.classList.remove("error-saving");
		var http = new XMLHttpRequest();
		http.open("POST", location.href, true);

		var data = "";
		data += "operation=" + encodeURIComponent("reason");
		data += "&";
		data += "event_id=" + encodeURIComponent(event_id);
		data += "&";
		data += "student_id=" + encodeURIComponent(student_id);
		data += "&";
		data += "reason=" + encodeURIComponent(reason);

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
		};
		http.send(data);
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
		};
		http.send(data);
	}
</script>
<style>
	.saving {
		transition: all ease-out 1s;
		background-color: #43cc6e;
	}

	.saved {

		transition: all ease-out 1s;
		background-color: transparent;
	}

	.error-saving {
		transition: all ease-out 1s;
		background-color: #fb6a6a;
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

	.main-attendance-view {
		font-weight: normal;
		font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
	}

	.main-attendance-view .when {
		display: block;
		font-weight: normal;
		text-transform: uppercase;
		font-size: 80%;
	}

	.main-attendance-view .what, .main-attendance-view .when {
		white-space: nowrap;
	}

	.main-attendance-view td,
	.main-attendance-view th {
		text-align: left;
		border: none;
		padding: 0.5em;
	}

	.main-attendance-view tbody tr:nth-child(2n + 1) {
		background-color: #eee;
	}

	#save-button-holder input{
		background: #eb3b45;
		-webkit-border-radius: 5px;
		-moz-border-radius: 5px;
		border-radius: 5px;
		color: #fff !important;
		cursor: pointer;
		display: inline-block;
		text-align: center;
		vertical-align: middle;
		font-family: "TradeGothicNo.20-CondBold", "Oswald", "Arial Narrow", sans-serif;
		text-transform: uppercase;
		font-size: 21px;
		font-weight: 400;
		letter-spacing: 1px;
		line-height: 1.333em;
		margin: 30px auto 0;
		display: block;
		padding-bottom: 10px;
		padding-left: 50px;
		padding-right: 50px;
		padding-top: 10px;
		text-transform: uppercase;
		text-shadow: none;
		: all 0.5s ease 0s;
		border-color:white;
	}

	#save-button-holder input:hover, #save-button-holder input:hover, #save-button-holder input:hover {
		background: #000000;
		color: #fff !important;
	}
</style>
</head>
<body>
	<!--
		There's three views: read only grid of all info with links and withdrawn statuses,
		attendance of single event, and checklist.
	-->

	<?php
		if(count($student_list) != 0) {
			echo htmlentities($single_event ? $event_name : "All LLs/events");
		}

		if($can_see_all) {
	?>
		<form>
			<input type="hidden" name="course_id" value="<?php echo (int) $course_id; ?>" />
			<input type="hidden" name="event_name" value="<?php echo htmlentities($event_name); ?>" />
			<select name="lc" onchange="this.form.submit();">
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
			<noscript>
				<input type="submit" value="Switch Cohort" />
			</noscript>
		</form>
	<?php
		}

		if(count($student_list) == 0) {
			echo "<p>The cohort leader should now take attendance.</p>";
		} else {
			// FIXME: move this logic to an explicit DB column
			$checklist_view = strpos($event_name, ":") !== false;
			if($single_event) {
				if($checklist_view) {
					// the simple checklist like for 1:1s
					echo "<ol class=\"checklist-style\">";

					foreach($student_list as $student) {
						if($event_info && $event_info["sort_mode"] == 0 && $student["lc_name"] != $last_lc) {
							echo "<h4>".(htmlentities($student["section_name"]))."</h4>";
							$last_lc = $student["lc_name"];
						}

						echo "<li class=\"attendance-individual\">";//<label>";

						global $student_status;
						$sta = $student_status[$event_id][$student["id"]];
						if(status_is_changeable_by_user($sta)) {
						?>
							<label>
							<input
								onchange="recordChange(this, this.getAttribute('data-event-id'), this.getAttribute('data-student-id'), this.checked ? 1 : 0);"
								type="checkbox"
								value="true"
								name="<?php echo $event_id . '_' . $student["id"]; ?>"
								data-event-id="<?php echo $event_id; ?>"
								data-student-name="<?php echo htmlentities($student["name"]); ?>"
								data-student-id="<?php echo htmlentities($student["id"]); ?>"
								<?php if($sta === "true") echo "checked=\"checked\""; ?>
							/>
							<?php echo "<span>" . htmlentities($student["name"]) . "</span>"; ?>
							</label>
						<?php
						} else {
							echo $sta;
						}
						echo "</li>";//"</label></li>";
					}
					echo "</ol>";
				} else {
					// the main attendance view
					?>
					<table class="main-attendance-view">
					<thead>
					<tr>
						<th></th>
						<th>
							<span class="when">Start of Learning Lab</span>
							<span class="what">Take Attendance</span>
						</th>
						<th>
							<span class="when">Over <?php echo htmlentities($late_definition); ?> late?</span>
							<span class="what">Track Punctuality</span>
						</th>
						<th>
							<span class="when">Prior to Learning Lab</span>
							<span class="what">Add messages from Fellows</span>
						</th>
					</tr>
					</thead>
					<tbody>
					<?php

					foreach($student_list as $student) {
						/*
						if($event_info && $event_info["sort_mode"] == 0 && $student["lc_name"] != $last_lc) {
							echo "<h4>".(htmlentities($student["section_name"]))."</h4>";
							$last_lc = $student["lc_name"];
						}
						*/

						echo "<tr>";
						echo "<td>" . htmlentities($student["name"]) . "</td>";

						global $student_status;
						global $student_reasons;
						$sta = $student_status[$event_id][$student["id"]];
						$reason = isset($student_reasons[$event_id][$student["id"]]) ? $student_reasons[$event_id][$student["id"]] : '';
						if(status_is_changeable_by_user($sta)) {
						?>
							<td>
							<span class="boxes-container">
							<label class="present">
							<input
								onchange="
									recordChange(this, this.getAttribute('data-event-id'), this.getAttribute('data-student-id'), this.checked ? 1 : 0);
									this.parentNode.parentNode.parentNode.querySelector('.undo').classList.add('possible');
								"
								type="radio"
								value="true"
								name="<?php echo $event_id . '_' . $student["id"]; ?>"
								data-event-id="<?php echo $event_id; ?>"
								data-student-name="<?php echo htmlentities($student["name"]); ?>"
								data-student-id="<?php echo htmlentities($student["id"]); ?>"
								<?php if($sta === "true" || $sta === "late") echo "checked=\"checked\""; ?>
							/>
								<span>Present</span>
							</label>

							<label class="absent">
							<input
								onchange="
									recordChange(this, this.getAttribute('data-event-id'), this.getAttribute('data-student-id'), this.checked ? 0 : 1);
									if(this.checked) {
										this.parentNode.parentNode.parentNode.parentNode.querySelector('input[value=&quot;late&quot;]').checked = false;
										this.parentNode.parentNode.parentNode.parentNode.querySelector('.late').classList.remove('checked');
									}
									this.parentNode.parentNode.parentNode.querySelector('.undo').classList.add('possible');
								"
								type="radio"
								value="false"
								name="<?php echo $event_id . '_' . $student["id"]; ?>"
								data-event-id="<?php echo $event_id; ?>"
								data-student-name="<?php echo htmlentities($student["name"]); ?>"
								data-student-id="<?php echo htmlentities($student["id"]); ?>"
								<?php if($sta === "false") echo "checked=\"checked\""; ?>
							/>
								<span>Absent</span>
							</label>
							<button class="undo <?php if($sta != "null") echo "possible";?>" type="button"
								data-event-id="<?php echo $event_id; ?>"
								data-student-name="<?php echo htmlentities($student["name"]); ?>"
								data-student-id="<?php echo htmlentities($student["id"]); ?>"
								onclick="
									recordChange(this, this.getAttribute('data-event-id'), this.getAttribute('data-student-id'), '');
									this.parentNode.parentNode.parentNode.querySelector('input[value=&quot;late&quot;]').checked = false;
									this.parentNode.parentNode.parentNode.querySelector('input[value=&quot;true&quot;]').checked = false;
									this.parentNode.parentNode.parentNode.querySelector('input[value=&quot;false&quot;]').checked = false;

									this.classList.remove('possible');
								"
							>Undo</button>
							</span>
							</td>
							<td>
								<label class="late <?php if($sta === "late") echo 'checked'; ?>"><input type="checkbox" onchange="
									recordChange(this, this.getAttribute('data-event-id'), this.getAttribute('data-student-id'), this.checked ? 2 : 1);
									this.parentNode.parentNode.parentNode.querySelector('input[value=&quot;false&quot;]').checked = false;
									this.parentNode.parentNode.parentNode.querySelector('input[value=&quot;true&quot;]').checked = true;
									if(this.checked) this.parentNode.classList.add('checked'); else this.parentNode.classList.remove('checked');
								"
								name="<?php echo $event_id . '_' . $student["id"] . '_late'; ?>"
								data-event-id="<?php echo $event_id; ?>"
								data-student-name="<?php echo htmlentities($student["name"]); ?>"
								data-student-id="<?php echo htmlentities($student["id"]); ?>"
								value="late"
								<?php if($sta === "late") echo "checked=\"checked\""; ?>
								/> <span>Late</span></label>
							</td>
							<td>
								<input type="text" name="reason" list="reason_list"
								data-event-id="<?php echo $event_id; ?>"
								data-student-name="<?php echo htmlentities($student["name"]); ?>"
								data-student-id="<?php echo htmlentities($student["id"]); ?>"
								value="<?php echo htmlentities($reason) ?>"
								onfocus="this.oldValue = this.value; this.value = '';"
								onblur="if(this.oldValue) this.value = this.oldValue; else if(this.value == '') this.onchange();"
								onkeydown="this.oldValue = null;"
								oninput="this.oldValue = null; autocompleteHacks(this);"
								placeholder="Reason for absence"
								onchange="
									recordReason(this, this.getAttribute('data-event-id'), this.getAttribute('data-student-id'), this.value);
								" />
							</td>
						<?php
						} else {
							echo "<td colspan=\"3\">";
							echo $sta;
							echo "</td>";
						}
						echo "</tr>";
					}
					echo "<tr class='reason-description'><td></td><td></td><td></td><td><small>Only use this field if a student reached out to you before missing or being late to a class.</small></td></tr>";
					echo "</tbody>";
					echo "</table>";

					echo '<div id="save-button-holder"><input type="button" onclick="alert(&quot;Your changes are saved, thank you.&quot;);" value="Save" /></div>';
					echo '<br><br>';
				}

				echo "<a href=\"attendance.php?course_id=$course_id&amp;lc=".urlencode($lc_email)."\" target=\"_TOP\">See All LLs/Events</a>";
				if($is_staff) echo " | ";
			} else {
				// the read-only table view
				echo "<table>";
				echo "<tr><th>Student</th>";
				foreach($events as $event) {
					echo "<th><a href=\"attendance.php?course_id=".urlencode($course_id)."&amp;lc=".urlencode($lc_email)."&amp;event_name=".urlencode($event["name"])."\">".htmlentities($event["name"])."</a></th>";
				}
				echo "</tr>";
				$tag = "td";

				$last_lc = "";

				foreach($student_list as $student) {
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

					foreach($events as $event) {
						echo "<td>";
						global $student_status;
						echo $student_status[$event["id"]][$student["id"]];
						echo "</td>";
					}
					echo "</tr>";
				}

				echo "</table>";
			}

			if($is_staff) { ?>
				<a href="attendance.php?course_id=<?php echo (int) $course_id;?>&amp;download=csv">Download CSV</a>
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
			?>

			<datalist id="reason_list">
				<option value="Sick / Dr. Appt" />
				<option value="Work" />
				<option value="School" />
				<option value="Caregiving" />
				<option value="Bereavement / Family Emergency" />
				<option value="Transportation" />
				<option value="Professional Development" />
				<option value="Vacation" />
			</datalist>
			<script>
				function autocompleteHacks(ele) {
					if(ele.length < 4)
						return;
					var list = document.querySelectorAll("#reason_list option");
					for(var i = 0; i < list.length; i++) {
						if(list[i].getAttribute("value") == ele.value) {
							// it was selected
							ele.blur();
							return;
						}
					}
				}

				function showAutocompleteHack(ele) {
					ele.setAttribute("placeholder", "Click for options...");
				}
			</script>
			<?php
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
</div>
<?php
	if(isset($_SESSION["masquerading_user"])) {
?>
	<form method="POST">
		<input type="hidden" name="operation" value="stop_masquerade" />
		<button type="submit">Stop masquerading as <?php echo htmlentities($_SESSION["masquerading_user"]); ?></button>
	</form>
<?php
	}
?>
</body>
</html>
