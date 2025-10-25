START TRANSACTION;

-- Migración de prueba
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('test_migration', '1', 'Prueba del sistema de migraciones')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

-- Actualizar versión
UPDATE `settings` 
SET `setting_value` = '2.2.2' 
WHERE `setting_key` = 'current_system_version';

COMMIT;