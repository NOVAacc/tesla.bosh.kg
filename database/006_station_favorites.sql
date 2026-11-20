CREATE TABLE IF NOT EXISTS user_station_favorites (
 user_id BIGINT UNSIGNED NOT NULL,
 station_hash BINARY(32) NOT NULL,
 station_id VARCHAR(1024) NOT NULL,
 PRIMARY KEY (user_id, station_hash),
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
