USE braven_attendance;
ALTER TABLE attendance_people DROP PRIMARY KEY, ADD dummy_id INT PRIMARY KEY AUTO_INCREMENT, ADD UNIQUE INDEX unique_index (`event_id`, `person_id`);

USE braven_attendance;
ALTER TABLE attendance_lc_absences DROP PRIMARY KEY, ADD dummy_id INT PRIMARY KEY AUTO_INCREMENT, ADD UNIQUE INDEX unique_index (`event_id`, `lc_email`);

USE wordpress;
ALTER TABLE bz_term_relationships DROP PRIMARY KEY, ADD dummy_id INT PRIMARY KEY AUTO_INCREMENT, ADD UNIQUE INDEX unique_index (`object_id`, `term_taxonomy_id`);

USE wordpress;
ALTER TABLE wp_term_relationships DROP PRIMARY KEY, ADD dummy_id INT PRIMARY KEY AUTO_INCREMENT, ADD UNIQUE INDEX unique_index (`object_id`, `term_taxonomy_id`);
