-- Add BYD Yuan Up to the existing rechargeable vehicle catalog.
INSERT INTO vehicle_models (make_id, name, powertrain)
SELECT id, 'Yuan Up', 'BEV' FROM vehicle_makes WHERE name = 'BYD'
ON DUPLICATE KEY UPDATE id = vehicle_models.id;
