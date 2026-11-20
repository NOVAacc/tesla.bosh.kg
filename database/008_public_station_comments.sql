ALTER TABLE station_feedback ADD COLUMN is_public TINYINT(1) NOT NULL DEFAULT 0,
 ADD INDEX idx_public_station_comments (station_hash, is_public, id);
