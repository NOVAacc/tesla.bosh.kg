-- Starter catalog for cars with external charging.
CREATE TABLE IF NOT EXISTS vehicle_makes (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(70) NOT NULL UNIQUE,
 is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vehicle_models (
 id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 make_id INT UNSIGNED NOT NULL,
 name VARCHAR(90) NOT NULL,
 powertrain ENUM('BEV','PHEV','EREV') NOT NULL,
 is_active BOOLEAN NOT NULL DEFAULT TRUE,
 UNIQUE KEY (make_id, name),
 FOREIGN KEY (make_id) REFERENCES vehicle_makes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

START TRANSACTION;

INSERT INTO vehicle_makes (name) VALUES
('AITO'),
('Audi'),
('Avatr'),
('BMW'),
('BYD'),
('Chevrolet'),
('Chrysler'),
('Deepal'),
('Ford'),
('Hyundai'),
('Jeep'),
('Kia'),
('Li Auto'),
('Mercedes-Benz'),
('Mitsubishi'),
('Nissan'),
('Porsche'),
('Renault'),
('Tesla'),
('Toyota'),
('Volkswagen'),
('Volvo'),
('Xiaomi'),
('Zeekr')
ON DUPLICATE KEY UPDATE id = vehicle_makes.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'AITO'), 'M5 EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'AITO'), 'M5 EREV', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'AITO'), 'M7 EREV', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'AITO'), 'M9 EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'AITO'), 'M9 EREV', 'EREV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Audi'), 'e-tron', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Audi'), 'Q4 e-tron', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Audi'), 'Q5 TFSI e', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Audi'), 'Q6 e-tron', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Audi'), 'Q8 e-tron', 'BEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Avatr'), '07 EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Avatr'), '07 EREV', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'Avatr'), '11 EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Avatr'), '11 EREV', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'Avatr'), '12 EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Avatr'), '12 EREV', 'EREV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'i3', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'i3 REx', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'i4', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'i5', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'i7', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'iX', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'iX1', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'iX3', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), '330e', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'X3 xDrive30e', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'X5 xDrive45e', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BMW'), 'X5 xDrive50e', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'BYD'), 'Seagull', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BYD'), 'Dolphin', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BYD'), 'Yuan Plus (Atto 3)', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BYD'), 'Seal', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BYD'), 'Sealion 7', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BYD'), 'Qin Plus EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BYD'), 'Qin Plus DM-i', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BYD'), 'Song Plus EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'BYD'), 'Song Plus DM-i', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Chevrolet'), 'Bolt EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Chevrolet'), 'Bolt EUV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Chevrolet'), 'Volt', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Chevrolet'), 'Equinox EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Chevrolet'), 'Blazer EV', 'BEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Chrysler'), 'Pacifica Plug-in Hybrid', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Deepal'), 'SL03 EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Deepal'), 'SL03 EREV', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'Deepal'), 'S07 EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Deepal'), 'S07 EREV', 'EREV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Ford'), 'Mustang Mach-E', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Ford'), 'F-150 Lightning', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Ford'), 'Escape PHEV', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Hyundai'), 'Ioniq Electric', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Hyundai'), 'Ioniq Plug-in', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Hyundai'), 'Ioniq 5', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Hyundai'), 'Ioniq 6', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Hyundai'), 'Kona Electric', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Hyundai'), 'Tucson PHEV', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Hyundai'), 'Santa Fe PHEV', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Jeep'), 'Wrangler 4xe', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Jeep'), 'Grand Cherokee 4xe', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Kia'), 'EV3', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Kia'), 'EV5', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Kia'), 'EV6', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Kia'), 'EV9', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Kia'), 'Niro EV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Kia'), 'Niro PHEV', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Kia'), 'Sportage PHEV', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Kia'), 'Sorento PHEV', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Li Auto'), 'One', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'Li Auto'), 'L6', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'Li Auto'), 'L7', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'Li Auto'), 'L8', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'Li Auto'), 'L9', 'EREV'),
((SELECT id FROM vehicle_makes WHERE name = 'Li Auto'), 'Mega', 'BEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Mercedes-Benz'), 'EQA', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Mercedes-Benz'), 'EQB', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Mercedes-Benz'), 'EQC', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Mercedes-Benz'), 'EQE', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Mercedes-Benz'), 'EQS', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Mercedes-Benz'), 'GLC 350e', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Mitsubishi'), 'i-MiEV', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Mitsubishi'), 'Outlander PHEV', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Mitsubishi'), 'Eclipse Cross PHEV', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Nissan'), 'Leaf', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Nissan'), 'Ariya', 'BEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Porsche'), 'Taycan', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Porsche'), 'Macan Electric', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Porsche'), 'Cayenne E-Hybrid', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Porsche'), 'Panamera E-Hybrid', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Renault'), 'Zoe', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Renault'), 'Megane E-Tech Electric', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Renault'), 'Scenic E-Tech Electric', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Renault'), 'Renault 5 E-Tech Electric', 'BEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Tesla'), 'Model 3', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Tesla'), 'Model Y', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Tesla'), 'Model S', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Tesla'), 'Model X', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Tesla'), 'Cybertruck', 'BEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Toyota'), 'bZ4X', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Toyota'), 'Prius Plug-in Hybrid (Prius Prime)', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Toyota'), 'RAV4 Plug-in Hybrid (RAV4 Prime)', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Volkswagen'), 'e-Golf', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volkswagen'), 'ID.3', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volkswagen'), 'ID.4', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volkswagen'), 'ID.5', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volkswagen'), 'ID.6', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volkswagen'), 'ID.7', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volkswagen'), 'ID. Buzz', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volkswagen'), 'Golf GTE', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volkswagen'), 'Passat GTE', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Volvo'), 'EX30', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volvo'), 'EX40', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volvo'), 'EX90', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volvo'), 'XC60 Recharge T8', 'PHEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Volvo'), 'XC90 Recharge T8', 'PHEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Xiaomi'), 'SU7', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Xiaomi'), 'YU7', 'BEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

INSERT INTO vehicle_models (make_id, name, powertrain) VALUES
((SELECT id FROM vehicle_makes WHERE name = 'Zeekr'), '001', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Zeekr'), '007', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Zeekr'), '009', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Zeekr'), 'X', 'BEV'),
((SELECT id FROM vehicle_makes WHERE name = 'Zeekr'), '7X', 'BEV')
ON DUPLICATE KEY UPDATE id = vehicle_models.id;

COMMIT;
