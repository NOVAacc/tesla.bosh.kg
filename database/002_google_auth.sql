-- Apply once in the tesla.bosh.kg MySQL database after 001_accounts.sql.
-- Google identity uses immutable Google subject ID, never email as provider identity.
CREATE TABLE IF NOT EXISTS user_google_identities (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 google_sub VARCHAR(255) NOT NULL,
 created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY unique_google_subject (google_sub),
 UNIQUE KEY unique_google_user (user_id),
 CONSTRAINT fk_google_identity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
