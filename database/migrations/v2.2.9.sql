-- =============================================
-- Migración v2.2.9 - Actualización de Versión
-- =============================================
-- Descripción: Actualización de sistema con correcciones y mejoras
--              Sistema de actualización mejorado con contadores
--              Modal de detalles de actualización implementado
-- Fecha: 2025-11-01
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- Verificar que existe la clave current_system_version
-- Si no existe, crearla con valor inicial
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.2.9', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =============================================
-- Actualizar versión del sistema a 2.2.9
-- =============================================
UPDATE `settings` 
SET `setting_value` = '2.2.9' 
WHERE `setting_key` = 'current_system_version';

-- =============================================
-- Registrar fecha de esta migración
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('migration_v228_date', NOW(), 'Fecha de migración a v2.2.9')
ON DUPLICATE KEY UPDATE `setting_value` = NOW();

-- =============================================
-- Registrar log de cambios de esta versión
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'migration_v228_log', 
    'Actualización de sistema v2.2.9 Correcciones en contadores de archivos durante actualización, implementación de modal de detalles, mejoras en tracking de cambios.',
    'Log de cambios versión 2.2.9'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- Verificación: Mostrar versión actualizada
-- =============================================
SELECT 
    'Migración v2.2.9 completada exitosamente' AS status,
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS nueva_version,
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v228_date') AS fecha_migracion,
    NOW() AS timestamp_completado;

COMMIT;

-- =============================================
-- NOTAS DE LA VERSIÓN 2.2.9
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
-- - Requiere versión 2.2.9 o superior previamente instalada
-- 
-- ROLLBACK (si es necesario):
-- UPDATE settings SET setting_value = '2.2.8' 
-- WHERE setting_key = 'current_system_version';
--
-- =============================================