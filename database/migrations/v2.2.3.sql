-- =============================================
-- Migración v2.2.3 - Prueba automática
-- =============================================

START TRANSACTION;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('auto_migration_test', '1', 'Prueba de migración automática')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

UPDATE `settings` 
SET `setting_value` = '2.2.3' 
WHERE `setting_key` = 'current_system_version';

COMMIT;