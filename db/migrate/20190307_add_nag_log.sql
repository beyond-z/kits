
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


