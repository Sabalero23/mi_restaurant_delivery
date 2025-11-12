-- =============================================
-- Migración v2.3.6 - Kardex y Limpieza de Sistema (CORREGIDO)
-- =============================================
-- Descripción: 
--   1. Volcado inicial de movimientos de productos al kardex
--   2. Limpieza de tabla settings (mover logs a tabla dedicada)
--   3. Creación de tabla para logs de actualizaciones
--   4. Optimización de estructura de datos
-- Fecha: 2025-11-12
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- 1. CREAR TABLA DE LOGS DE ACTUALIZACIÓN
-- =============================================
CREATE TABLE IF NOT EXISTS `system_update_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `update_version` VARCHAR(20) NOT NULL,
    `from_commit` VARCHAR(64) DEFAULT NULL,
    `to_commit` VARCHAR(64) DEFAULT NULL,
    `status` ENUM('pending', 'in_progress', 'completed', 'failed', 'rolled_back') DEFAULT 'pending',
    `started_at` DATETIME NOT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `username` VARCHAR(100) DEFAULT NULL,
    `files_added` INT(11) DEFAULT 0,
    `files_updated` INT(11) DEFAULT 0,
    `files_deleted` INT(11) DEFAULT 0,
    `backup_path` VARCHAR(255) DEFAULT NULL,
    `update_details` TEXT DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`),
    KEY `idx_version` (`update_version`),
    KEY `idx_started_at` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 2. MIGRAR LOGS EXISTENTES DESDE SETTINGS
-- =============================================
INSERT INTO `system_update_logs` 
    (`update_version`, `status`, `started_at`, `completed_at`, `username`, `update_details`)
SELECT 
    'v2.3.5' as version,
    'completed' as status,
    COALESCE(
        (SELECT setting_value FROM settings WHERE setting_key = 'migration_v235_date'),
        NOW()
    ) as started_at,
    COALESCE(
        (SELECT setting_value FROM settings WHERE setting_key = 'migration_v235_date'),
        NOW()
    ) as completed_at,
    'Sistema' as username,
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v235_log') as details
WHERE NOT EXISTS (
    SELECT 1 FROM system_update_logs WHERE update_version = 'v2.3.5'
);

-- =============================================
-- 3. ACTUALIZAR ESTRUCTURA DE STOCK_MOVEMENTS
-- =============================================

-- Verificar y agregar columna reference_type
SET @column_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'stock_movements' 
    AND COLUMN_NAME = 'reference_type'
);

SET @sql = IF(@column_exists = 0,
    "ALTER TABLE `stock_movements` ADD COLUMN `reference_type` ENUM('order', 'manual', 'adjustment', 'purchase', 'return') DEFAULT 'manual' AFTER `reason`",
    'SELECT "Column reference_type already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar columna reference_id
SET @column_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'stock_movements' 
    AND COLUMN_NAME = 'reference_id'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `stock_movements` ADD COLUMN `reference_id` INT(11) DEFAULT NULL AFTER `reference_type`',
    'SELECT "Column reference_id already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Actualizar ENUM de movement_type
ALTER TABLE `stock_movements`
    MODIFY COLUMN `movement_type` ENUM('entrada', 'salida', 'ajuste', 'venta', 'compra', 'devolucion') NOT NULL;

-- Verificar y agregar índice idx_product_id
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'stock_movements' 
    AND INDEX_NAME = 'idx_product_id'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `stock_movements` ADD INDEX `idx_product_id` (`product_id`)',
    'SELECT "Index idx_product_id already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar índice idx_user_id
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'stock_movements' 
    AND INDEX_NAME = 'idx_user_id'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `stock_movements` ADD INDEX `idx_user_id` (`user_id`)',
    'SELECT "Index idx_user_id already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar índice idx_created_at
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'stock_movements' 
    AND INDEX_NAME = 'idx_created_at'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `stock_movements` ADD INDEX `idx_created_at` (`created_at`)',
    'SELECT "Index idx_created_at already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verificar y agregar índice idx_movement_type
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'stock_movements' 
    AND INDEX_NAME = 'idx_movement_type'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `stock_movements` ADD INDEX `idx_movement_type` (`movement_type`)',
    'SELECT "Index idx_movement_type already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar clave foránea fk_movement_product si no existe
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'stock_movements' 
    AND CONSTRAINT_NAME = 'fk_movement_product'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `stock_movements` ADD CONSTRAINT `fk_movement_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE',
    'SELECT "FK fk_movement_product already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Agregar clave foránea fk_movement_user si no existe
SET @fk_exists = (
    SELECT COUNT(*) 
    FROM information_schema.TABLE_CONSTRAINTS 
    WHERE CONSTRAINT_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'stock_movements' 
    AND CONSTRAINT_NAME = 'fk_movement_user'
);

SET @sql = IF(@fk_exists = 0,
    'ALTER TABLE `stock_movements` ADD CONSTRAINT `fk_movement_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL',
    'SELECT "FK fk_movement_user already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- 4. VOLCADO INICIAL DE MOVIMIENTOS AL KARDEX
-- =============================================
-- Verificar si ya existen movimientos
SET @movements_count = (SELECT COUNT(*) FROM stock_movements);

-- Solo ejecutar si la tabla está vacía
INSERT INTO `stock_movements` 
    (`product_id`, `movement_type`, `quantity`, `old_stock`, `new_stock`, `reason`, `reference_type`, `reference_id`, `user_id`, `created_at`)
SELECT 
    oi.product_id,
    'entrada' as movement_type,
    SUM(oi.quantity) as quantity,
    COALESCE(p.stock_quantity, 0) as old_stock,
    COALESCE(p.stock_quantity, 0) as new_stock,
    CONCAT('Inventario inicial - Basado en órdenes históricas hasta ', DATE_FORMAT(NOW(), '%d/%m/%Y')) as reason,
    'adjustment' as reference_type,
    NULL as reference_id,
    1 as user_id,
    MIN(o.created_at) as created_at
FROM 
    order_items oi
INNER JOIN 
    orders o ON oi.order_id = o.id
INNER JOIN 
    products p ON oi.product_id = p.id
WHERE 
    p.track_inventory = 1
    AND o.status != 'cancelled'
    AND @movements_count = 0
GROUP BY 
    oi.product_id, p.stock_quantity
HAVING 
    SUM(oi.quantity) > 0;

-- Actualizar stock actual de productos sin seguimiento pero con órdenes
UPDATE products p
SET 
    stock_quantity = COALESCE(
        (SELECT SUM(oi.quantity) 
         FROM order_items oi 
         INNER JOIN orders o ON oi.order_id = o.id 
         WHERE oi.product_id = p.id 
         AND o.status != 'cancelled'),
        0
    ),
    track_inventory = 1
WHERE 
    p.track_inventory = 0 
    AND p.id IN (
        SELECT DISTINCT oi.product_id 
        FROM order_items oi 
        INNER JOIN orders o ON oi.order_id = o.id 
        WHERE o.status != 'cancelled'
    );

-- =============================================
-- 5. CREAR TABLA DE HISTORIAL DE ACTUALIZACIONES
-- =============================================
CREATE TABLE IF NOT EXISTS `system_update_history` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `update_id` INT(11) DEFAULT NULL,
    `update_version` VARCHAR(20) NOT NULL,
    `from_commit` VARCHAR(64) DEFAULT NULL,
    `to_commit` VARCHAR(64) DEFAULT NULL,
    `status` ENUM('pending', 'in_progress', 'completed', 'failed', 'rolled_back') DEFAULT 'pending',
    `started_at` DATETIME NOT NULL,
    `completed_at` DATETIME DEFAULT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `username` VARCHAR(100) DEFAULT NULL,
    `files_added` INT(11) DEFAULT 0,
    `files_updated` INT(11) DEFAULT 0,
    `files_deleted` INT(11) DEFAULT 0,
    `backup_path` VARCHAR(255) DEFAULT NULL,
    `update_details` MEDIUMTEXT DEFAULT NULL,
    `error_message` TEXT DEFAULT NULL,
    `execution_time` FLOAT DEFAULT NULL COMMENT 'Tiempo de ejecución en segundos',
    `ip_address` VARCHAR(45) DEFAULT NULL,
    `user_agent` VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_update_id` (`update_id`),
    KEY `idx_status` (`status`),
    KEY `idx_version` (`update_version`),
    KEY `idx_started_at` (`started_at`),
    KEY `idx_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar datos de system_updates a system_update_history (CORREGIDO)
INSERT INTO `system_update_history` 
    (`update_id`, `update_version`, `from_commit`, `to_commit`, `status`, `started_at`, `completed_at`, 
     `user_id`, `files_added`, `files_updated`, `files_deleted`, `backup_path`, `update_details`, `error_message`)
SELECT 
    id,
    COALESCE(
        CASE 
            WHEN to_commit IS NOT NULL AND to_commit != '' THEN CONCAT('Sistema ', SUBSTRING(to_commit, 1, 7))
            WHEN from_commit IS NOT NULL AND from_commit != '' THEN CONCAT('Sistema ', SUBSTRING(from_commit, 1, 7))
            ELSE CONCAT('Update-', id)
        END,
        CONCAT('Update-', id)
    ) as update_version,
    from_commit,
    to_commit,
    status,
    started_at,
    completed_at,
    updated_by,
    files_added,
    files_updated,
    files_deleted,
    backup_path,
    update_details,
    error_message
FROM 
    system_updates
WHERE NOT EXISTS (
    SELECT 1 FROM system_update_history WHERE update_id = system_updates.id
);

-- =============================================
-- 6. LIMPIEZA DE TABLA SETTINGS
-- =============================================
-- Eliminar logs de migraciones antiguas
DELETE FROM `settings` 
WHERE `setting_key` LIKE 'migration_%_log'
   OR `setting_key` LIKE 'migration_%_date'
   OR `setting_key` LIKE 'migration_%_method'
   OR `setting_key` LIKE 'migration_%_commit';

-- Eliminar configuraciones temporales y de cache
DELETE FROM `settings` 
WHERE `setting_key` LIKE 'temp_%' 
   OR `setting_key` LIKE 'cache_%'
   OR `setting_key` LIKE 'old_%'
   OR `setting_key` LIKE 'test_%';

-- Eliminar configuraciones obsoletas
DELETE FROM `settings`
WHERE `setting_key` IN (
    'last_update_log',
    'system_update_history',
    'update_log',
    'auto_migration_test',
    'test_migration',
    'new_feature_enabled',
    'migration_system_working'
);

-- =============================================
-- 7. OPTIMIZACIÓN DE ÍNDICES
-- =============================================

-- Índices para order_items
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'order_items' 
    AND INDEX_NAME = 'idx_product_status'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `order_items` ADD INDEX `idx_product_status` (`product_id`, `status`)',
    'SELECT "Index idx_product_status already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'order_items' 
    AND INDEX_NAME = 'idx_order_status'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `order_items` ADD INDEX `idx_order_status` (`order_id`, `status`)',
    'SELECT "Index idx_order_status already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índices para products
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'products' 
    AND INDEX_NAME = 'idx_track_inventory'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `products` ADD INDEX `idx_track_inventory` (`track_inventory`, `is_active`)',
    'SELECT "Index idx_track_inventory already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'products' 
    AND INDEX_NAME = 'idx_stock_alert'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `products` ADD INDEX `idx_stock_alert` (`stock_quantity`, `low_stock_alert`)',
    'SELECT "Index idx_stock_alert already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índices para orders
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'orders' 
    AND INDEX_NAME = 'idx_type_status'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `idx_type_status` (`type`, `status`)',
    'SELECT "Index idx_type_status already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'orders' 
    AND INDEX_NAME = 'idx_created_at'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `idx_created_at` (`created_at`)',
    'SELECT "Index idx_created_at already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- 8. GENERAR HASH Y ACTUALIZAR VERSIÓN
-- =============================================
SET @new_commit_hash_full = SHA2(CONCAT(
    'v2.3.6',
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
    CONCAT('Hash SHA-256 corto - v2.3.6 - Generado: ', NOW())
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = @new_commit_hash,
    `description` = CONCAT('Hash SHA-256 corto - v2.3.6 - Actualizado: ', NOW());

-- Guardar commit completo
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'system_commit_full', 
    @new_commit_hash_full,
    CONCAT('Hash SHA-256 completo - v2.3.6 - Generado: ', NOW())
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = @new_commit_hash_full,
    `description` = CONCAT('Hash SHA-256 completo - v2.3.6 - Actualizado: ', NOW());

-- Actualizar versión del sistema
UPDATE `settings` 
SET `setting_value` = '2.3.6',
    `description` = 'Versión actual del sistema'
WHERE `setting_key` = 'current_system_version';

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.3.6', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE 
    `setting_value` = '2.3.6',
    `description` = 'Versión actual del sistema';

-- =============================================
-- 9. CONFIGURACIONES DEL SISTEMA
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES 
    ('kardex_enabled', '1', 'Sistema de control de inventario Kardex habilitado'),
    ('kardex_auto_register_sales', '1', 'Registrar automáticamente salidas de stock por ventas'),
    ('kardex_require_reason', '0', 'Requerir motivo obligatorio en movimientos manuales'),
    ('kardex_alert_low_stock', '1', 'Alertar cuando productos lleguen a stock mínimo'),
    ('system_update_logs_retention', '365', 'Días de retención de logs de actualización')
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- 10. REGISTRAR MIGRACIÓN
-- =============================================
INSERT INTO `migrations` (`version`, `filename`, `executed_at`, `execution_time`, `status`) 
VALUES (
    '2.3.6',
    'v2.3.6.sql',
    NOW(),
    0.1,
    'success'
) ON DUPLICATE KEY UPDATE 
    `executed_at` = NOW(),
    `status` = 'success';

-- Registrar en system_update_logs
INSERT INTO `system_update_logs` 
    (`update_version`, `status`, `started_at`, `completed_at`, `username`, `files_added`, `update_details`)
VALUES (
    'v2.3.6',
    'completed',
    NOW(),
    NOW(),
    'Sistema',
    1,
    'Actualización de sistema v2.3.6: Kardex de Inventario y Limpieza de Sistema'
);

-- =============================================
-- 11. OPTIMIZAR TABLAS
-- =============================================
OPTIMIZE TABLE `settings`;
OPTIMIZE TABLE `stock_movements`;
OPTIMIZE TABLE `products`;
OPTIMIZE TABLE `orders`;
OPTIMIZE TABLE `order_items`;
OPTIMIZE TABLE `system_update_history`;

-- =============================================
-- 12. VERIFICACIÓN Y RESUMEN
-- =============================================
SELECT 
    '✅ MIGRACIÓN COMPLETADA EXITOSAMENTE' AS 'ESTADO',
    '' AS '';

SELECT 
    'VERSIÓN DEL SISTEMA' AS 'INFORMACIÓN',
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS 'Versión',
    @new_commit_hash AS 'Commit_Corto',
    SUBSTRING(@new_commit_hash_full, 1, 16) AS 'Commit_Preview',
    NOW() AS 'Fecha_Instalación';

SELECT 
    'RESUMEN DE KARDEX' AS 'Métrica',
    '' AS 'Valor';

SELECT 
    'Productos con Inventario' AS 'Métrica',
    COUNT(*) AS 'Valor'
FROM products 
WHERE track_inventory = 1
UNION ALL
SELECT 
    'Movimientos Registrados',
    COUNT(*)
FROM stock_movements
UNION ALL
SELECT 
    'Productos Bajo Stock Mínimo',
    COUNT(*)
FROM products 
WHERE track_inventory = 1 
AND stock_quantity <= low_stock_alert
AND is_active = 1;

SELECT 
    'LIMPIEZA DE SETTINGS' AS 'Estado',
    (SELECT COUNT(*) FROM settings) AS 'Cantidad';

SELECT 
    'LOGS DE ACTUALIZACIÓN' AS 'Tabla',
    (SELECT COUNT(*) FROM system_update_logs) AS 'Registros';

SELECT 
    '✅ SISTEMA LISTO PARA USAR' AS '',
    CONCAT('Versión: ', (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version')) AS '',
    CONCAT('Commit: ', @new_commit_hash) AS '';

COMMIT;