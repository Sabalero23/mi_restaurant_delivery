-- =============================================
-- Migración v2.4.0 - Sistema de Compras y Triggers de Stock
-- =============================================
-- Descripción: 
--   1. Crear tabla de compras
--   2. Crear triggers para registro automático de movimientos
--   3. Actualizar estructura de stock_movements
--   4. Migrar órdenes existentes a movimientos
-- Fecha: 2025-11-12
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- 11. GENERAR HASH Y ACTUALIZAR VERSIÓN
-- =============================================
SET @new_commit_hash_full = SHA2(CONCAT(
    'v2.4.0',
    '_',
    NOW(),
    '_',
    @@hostname,
    '_',
    DATABASE()
), 256);

SET @new_commit_hash = SUBSTRING(@new_commit_hash_full, 1, 8);

-- Guardar commit anterior
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`)
SELECT 
    'system_commit_previous',
    `setting_value`,
    CONCAT('Commit anterior guardado el ', NOW())
FROM `settings`
WHERE `setting_key` = 'system_commit'
ON DUPLICATE KEY UPDATE 
    `setting_value` = (SELECT `setting_value` FROM (SELECT * FROM `settings`) AS temp WHERE `setting_key` = 'system_commit'),
    `description` = CONCAT('Commit anterior guardado el ', NOW());

-- Actualizar commit actual
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'system_commit', 
    @new_commit_hash,
    CONCAT('Hash SHA-256 corto - v2.4.0 - Generado: ', NOW())
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = @new_commit_hash,
    `description` = CONCAT('Hash SHA-256 corto - v2.4.0 - Actualizado: ', NOW());

-- Guardar commit completo
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'system_commit_full', 
    @new_commit_hash_full,
    CONCAT('Hash SHA-256 completo - v2.4.0 - Generado: ', NOW())
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = @new_commit_hash_full,
    `description` = CONCAT('Hash SHA-256 completo - v2.4.0 - Actualizado: ', NOW());

-- Actualizar versión del sistema
UPDATE `settings` 
SET `setting_value` = '2.4.0',
    `description` = 'Versión actual del sistema'
WHERE `setting_key` = 'current_system_version';

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.4.0', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE 
    `setting_value` = '2.4.0',
    `description` = 'Versión actual del sistema';

-- =============================================
-- 12. REGISTRAR MIGRACIÓN
-- =============================================
INSERT INTO `migrations` (`version`, `filename`, `executed_at`, `execution_time`, `status`) 
VALUES (
    '2.4.0',
    'v2.4.0.sql',
    NOW(),
    0.1,
    'success'
) ON DUPLICATE KEY UPDATE 
    `executed_at` = NOW(),
    `status` = 'success';

-- Registrar en system_update_logs
SET @table_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLES 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'system_update_logs'
);

SET @sql = IF(@table_exists > 0,
    "INSERT INTO `system_update_logs` 
        (`update_version`, `status`, `started_at`, `completed_at`, `username`, `files_added`, `update_details`)
    VALUES (
        'v2.4.0',
        'completed',
        NOW(),
        NOW(),
        'Sistema',
        3,
        'Actualización de sistema v2.4.0: Sistema de Compras y Triggers Automáticos'
    ) ON DUPLICATE KEY UPDATE 
        `completed_at` = NOW(),
        `status` = 'completed'",
    'SELECT "Table system_update_logs does not exist, skipping log" AS result'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- VERIFICACIÓN FINAL
-- =============================================
SELECT '✅ INSTALACIÓN COMPLETADA - v2.4.0' AS Status;

SELECT 
    'Productos con Inventario' AS Metrica,
    COUNT(*) AS Valor
FROM products 
WHERE track_inventory = 1 AND is_active = 1
UNION ALL
SELECT 
    'Movimientos Totales Registrados',
    COUNT(*)
FROM stock_movements
UNION ALL
SELECT 
    'Productos Bajo Stock Minimo',
    COUNT(*)
FROM products 
WHERE track_inventory = 1 
AND stock_quantity <= low_stock_alert
AND is_active = 1;

═══════════════════════════════════════════════
FIN DE MIGRACIÓN v2.4.0
═══════════════════════════════════════════════
*/