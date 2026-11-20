-- Apply manually to the same database used by account.php before enabling station feedback.
CREATE TABLE IF NOT EXISTS station_feedback (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
 user_id BIGINT UNSIGNED NOT NULL,
 station_hash BINARY(32) NOT NULL,
 station_id VARCHAR(1024) NOT NULL,
 feedback_type ENUM('broken','incorrect_info','access','other') NOT NULL,
 message VARCHAR(1000) NOT NULL,
 status ENUM('new','reviewing','resolved','dismissed') NOT NULL DEFAULT 'new',
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY (id),
 KEY idx_station_feedback (station_hash, created_at),
 KEY idx_user_feedback (user_id, created_at),
 KEY idx_status_feedback (status, created_at),
 CONSTRAINT fk_station_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
