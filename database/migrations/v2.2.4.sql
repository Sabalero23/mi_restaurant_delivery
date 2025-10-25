-- =============================================
-- Migración v2.2.4 - Test final
-- =============================================

START TRANSACTION;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('migration_system_working', '1', 'Sistema de migraciones funcionando')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

UPDATE `settings` 
SET `setting_value` = '2.2.4' 
WHERE `setting_key` = 'current_system_version';

COMMIT;