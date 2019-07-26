ALTER TABLE attendance_people ADD updated_at TIMESTAMP NOT NULL DEFAULT now();
ALTER TABLE attendance_people ADD updated_by TEXT; -- just the email address of the user logged in

CREATE TABLE attendance_courses (
	id INTEGER PRIMARY KEY, -- should match the canvas id
	late_threshold VARCHAR(40) DEFAULT '5 mins'
) DEFAULT CHARACTER SET=utf8mb4;
