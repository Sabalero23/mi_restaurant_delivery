-- =============================================
-- Migración a versión 2.2.1
-- Fecha: 2025-10-26
-- Descripción: Ejemplo de migración futura
-- =============================================

START TRANSACTION;

-- Ejemplo: Agregar nueva columna a una tabla
-- ALTER TABLE `products` ADD COLUMN `featured` tinyint(1) DEFAULT 0 AFTER `active`;

-- Ejemplo: Crear nueva configuración
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES 
('new_feature_enabled', '0', 'Habilitar nueva funcionalidad')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Actualizar versión del sistema
UPDATE `settings` 
SET `setting_value` = '2.2.1' 
WHERE `setting_key` = 'current_system_version';

COMMIT;

-- =============================================
-- Fin de migración v2.2.1
-- =============================================
