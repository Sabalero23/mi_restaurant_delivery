-- =============================================
-- Migración v2.3.3 - Actualización de Versión
-- =============================================
-- Descripción: Actualización de sistema con correcciones y mejoras
--              Sistema de actualización mejorado con contadores
--              Modal de detalles de actualización implementado
-- Fecha: 2025-11-10
-- Autor: Cellcom Technology
-- =============================================

-- Desactivar verificación de claves foráneas temporalmente
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
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
-- Actualizar hash del último commit
-- =============================================
UPDATE `settings` 
SET `setting_value` = 'MANUAL_v2.3.3' 
WHERE `setting_key` = 'system_commit';

-- Si no existe el campo system_commit, crearlo
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('system_commit', 'MANUAL_v2.3.3', 'Hash del último commit instalado')
ON DUPLICATE KEY UPDATE `setting_value` = 'MANUAL_v2.3.3';

-- =============================================
-- Registrar fecha de esta migración
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('migration_v233_date', NOW(), 'Fecha de migración a v2.3.3')
ON DUPLICATE KEY UPDATE `setting_value` = NOW();

-- =============================================
-- Registrar log de cambios de esta versión
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'migration_v233_log', 
    'Actualización de sistema v2.3.3: Asignación de Mesas a Meseros

NUEVAS CARACTERÍSTICAS:
• Campo waiter_id en tabla tables para asociar mesas con meseros
• Índice idx_waiter para optimizar búsquedas
• Clave foránea fk_table_waiter con referencias a usuarios
• Sistema de asignación de meseros en gestión de mesas

ARCHIVOS MODIFICADOS:
• admin/order-create.php
  - Se agregó datatable
  - Mejora la visualización en móvil
  
• admin/tables.php
  - Selector de mesero en crear/editar mesa
  - Visualización de mesero asignado en tarjetas de mesa

CAMBIOS EN BASE DE DATOS:
• Nueva columna: tables.waiter_id (INT NULL)
• Nuevo índice: idx_waiter
• Nueva clave foránea: fk_table_waiter
• Relación: tables.waiter_id -> users.id (ON DELETE SET NULL)',
    'Log de cambios versión 2.3.3'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================	
-- Agregar campo waiter_id a la tabla tables (con verificación)
-- =============================================

-- Verificar si la columna waiter_id ya existe
SET @column_exists := (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tables' 
    AND COLUMN_NAME = 'waiter_id'
);

-- Si no existe, agregar la columna
SET @sql_add_column := IF(@column_exists = 0,
    'ALTER TABLE `tables` 
     ADD COLUMN `waiter_id` INT NULL AFTER `location`',
    'SELECT "Columna waiter_id ya existe, omitiendo..." AS info'
);

PREPARE stmt_col FROM @sql_add_column;
EXECUTE stmt_col;
DEALLOCATE PREPARE stmt_col;

-- Agregar índice idx_waiter si no existe
SET @index_waiter_exists := (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tables' 
    AND INDEX_NAME = 'idx_waiter'
);

SET @sql_add_index := IF(@index_waiter_exists = 0 AND @column_exists = 0,
    'ALTER TABLE `tables` ADD INDEX `idx_waiter` (`waiter_id`)',
    'SELECT "Índice idx_waiter ya existe o columna no creada, omitiendo..." AS info'
);

PREPARE stmt_idx FROM @sql_add_index;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- Agregar clave foránea fk_table_waiter si no existe
SET @fk_exists := (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'tables' 
    AND CONSTRAINT_NAME = 'fk_table_waiter' 
    AND CONSTRAINT_TYPE = 'FOREIGN KEY'
);

SET @sql_add_fk := IF(@fk_exists = 0 AND @column_exists = 0,
    'ALTER TABLE `tables` 
     ADD CONSTRAINT `fk_table_waiter` 
     FOREIGN KEY (`waiter_id`) 
     REFERENCES `users` (`id`) 
     ON DELETE SET NULL 
     ON UPDATE CASCADE',
    'SELECT "Clave foránea fk_table_waiter ya existe o columna no creada, omitiendo..." AS info'
);

PREPARE stmt_fk FROM @sql_add_fk;
EXECUTE stmt_fk;
DEALLOCATE PREPARE stmt_fk;

-- =============================================
-- Verificación: Mostrar versión actualizada
-- =============================================
SELECT 
    'Migración v2.3.3 completada exitosamente' AS status,
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS nueva_version,
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v233_date') AS fecha_migracion,
    CASE 
        WHEN @column_exists = 0 THEN 'Campo waiter_id agregado exitosamente'
        ELSE 'Campo waiter_id ya existía'
    END AS resultado_waiter_id,
    CASE 
        WHEN @index_waiter_exists = 0 AND @column_exists = 0 THEN 'Índice idx_waiter creado'
        ELSE 'Índice idx_waiter ya existía'
    END AS resultado_indice,
    CASE 
        WHEN @fk_exists = 0 AND @column_exists = 0 THEN 'Clave foránea fk_table_waiter creada'
        ELSE 'Clave foránea fk_table_waiter ya existía'
    END AS resultado_fk,
    NOW() AS timestamp_completado;

COMMIT;

-- Reactivar verificación de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- NOTAS DE LA VERSIÓN 2.3.3
-- =============================================
-- 
-- ARCHIVOS MODIFICADOS:
-- ✓ admin/order-create.php
--   - Se agregó datatable
--   - Mejora la visualización en móvil
--
-- ✓ admin/tables.php
--   - Selector de mesero en formulario de mesa
--   - Visualización de mesero asignado
-- 
-- COMPATIBILIDAD:
-- - Requiere MySQL 5.7+ o MariaDB 10.2+
-- - Compatible con PHP 7.4+
-- - Requiere versión 2.3.2 o superior previamente instalada
-- 
-- SEGURIDAD:
-- - Script es idempotente (se puede ejecutar múltiples veces)
-- - Verifica existencia antes de crear columnas/índices
-- - Usa prepared statements para prevenir errores
-- 
-- ROLLBACK (si es necesario):
-- ```sql
-- -- Eliminar clave foránea
-- ALTER TABLE tables DROP FOREIGN KEY fk_table_waiter;
-- -- Eliminar índice
-- ALTER TABLE tables DROP INDEX idx_waiter;
-- -- Eliminar columna
-- ALTER TABLE tables DROP COLUMN waiter_id;
-- -- Revertir versión
-- UPDATE settings SET setting_value = '2.3.2' 
-- WHERE setting_key = 'current_system_version';
-- ```
--
-- =============================================