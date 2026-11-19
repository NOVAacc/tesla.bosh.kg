-- Run once via phpMyAdmin on the tesla.bosh.kg database; utf8mb4 required.
CREATE TABLE IF NOT EXISTS users (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(254) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 display_name VARCHAR(120) NOT NULL DEFAULT '',
 role ENUM('user','business','admin') NOT NULL DEFAULT 'user',
 email_verified_at DATETIME NULL,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS user_vehicles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id BIGINT UNSIGNED NOT NULL,
 make VARCHAR(70) NOT NULL,
 model VARCHAR(90) NOT NULL,
 model_year SMALLINT UNSIGNED NULL,
 connector VARCHAR(30) NULL,
 is_primary BOOLEAN NOT NULL DEFAULT FALSE,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 INDEX (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS business_categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(70) NOT NULL UNIQUE,
 name_ru VARCHAR(100) NOT NULL,
 name_ky VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO business_categories (slug,name_ru,name_ky) VALUES
('ev-service','EV-автосервисы','Электромобиль сервистери'),
('charging-accessories','Зарядные аксессуары','Кубаттоо жабдуулары'),
('parts','Запчасти','Тетиктер'),
('tire-service','Шиномонтаж','Дөңгөлөк кызматы'),
('software','Программное обслуживание','Программалык тейлөө'),
('dealership','Автосалоны','Автосалондор');
CREATE TABLE IF NOT EXISTS businesses (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 owner_user_id BIGINT UNSIGNED NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL,
 description TEXT NULL,
 phone VARCHAR(40) NULL,
 whatsapp VARCHAR(40) NULL,
 address VARCHAR(255) NULL,
 latitude DECIMAL(10,7) NULL,
 longitude DECIMAL(10,7) NULL,
 status ENUM('draft','pending','approved','rejected') NOT NULL DEFAULT 'pending',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE SET NULL,
 FOREIGN KEY (category_id) REFERENCES business_categories(id),
 INDEX (status,category_id),
 INDEX (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS business_services (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 business_id BIGINT UNSIGNED NOT NULL,
 name VARCHAR(150) NOT NULL,
 price_from DECIMAL(12,2) NULL,
 FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS login_attempts (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email_hash CHAR(64) NOT NULL,
 ip_hash CHAR(64) NOT NULL,
 attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 INDEX (email_hash, attempted_at),
 INDEX (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
