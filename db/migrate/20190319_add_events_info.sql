-- sort modes are 0 == by cohort, 1 == by name
ALTER TABLE attendance_events ADD sort_mode INTEGER NOT NULL DEFAULT 0;
ALTER TABLE attendance_events ADD send_nags BOOLEAN NOT NULL DEFAULT TRUE;
ALTER TABLE attendance_events ADD display_order INTEGER NOT NULL DEFAULT 0;
