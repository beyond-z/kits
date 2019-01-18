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

