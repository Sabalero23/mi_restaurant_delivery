-- =============================================
-- Migración v2.3.3 - Actualización de Versión
-- =============================================
-- Descripción: Actualización de sistema con correcciones y mejoras
--              Sistema de actualización mejorado con contadores
--              Modal de detalles de actualización implementado
-- Fecha: 2025-11-10
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- Verificar que existe la clave current_system_version
-- Si no existe, crearla con valor inicial
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.3.3', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =============================================
-- Actualizar versión del sistema a 2.3.3
-- =============================================
UPDATE `settings` 
SET `setting_value` = '2.3.3' 
WHERE `setting_key` = 'current_system_version';

-- =============================================
-- Registrar fecha de esta migración
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('migration_v232_date', NOW(), 'Fecha de migración a v2.3.3')
ON DUPLICATE KEY UPDATE `setting_value` = NOW();

-- =============================================
-- Registrar log de cambios de esta versión
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'migration_v232_log', 
    'Actualización de sistema v2.3.3 Asignacion de Mezas a Meseros',
    'Log de cambios versión 2.3.3'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================	
-- Agregar campo waiter_id a la tabla tables para asociar mesas a meseros
-- =============================================
ALTER TABLE `tables` 
ADD COLUMN `waiter_id` INT NULL AFTER `location`,
ADD INDEX `idx_waiter` (`waiter_id`),
ADD CONSTRAINT `fk_table_waiter` 
    FOREIGN KEY (`waiter_id`) 
    REFERENCES `users` (`id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;

-- =============================================
-- Verificación: Mostrar versión actualizada
-- =============================================
SELECT 
    'Migración v2.3.3 completada exitosamente' AS status,
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS nueva_version,
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v232_date') AS fecha_migracion,
    NOW() AS timestamp_completado;

COMMIT;

-- =============================================
-- NOTAS DE LA VERSIÓN 2.3.3
-- =============================================
-- 
-- ARCHIVOS MODIFICADOS:
-- ✓ admin/order-create.php
--   - Se agregó datatable.
--   - Mejora la visualización en mívil.
-- 
-- 
-- COMPATIBILIDAD:
-- - Requiere MySQL 5.7+ o MariaDB 10.2+
-- - Compatible con PHP 7.4+
-- - Requiere versión 2.3.3 o superior previamente instalada
-- 
-- ROLLBACK (si es necesario):
-- UPDATE settings SET setting_value = '2.2.8' 
-- WHERE setting_key = 'current_system_version';
--
-- =============================================