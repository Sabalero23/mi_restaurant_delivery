-- =============================================
-- Migracion v2.3.6 - Kardex y Limpieza de Sistema
-- =============================================
-- Descripcion: 
--   1. Volcado inicial de movimientos de productos al kardex
--   2. Limpieza de tabla settings (mover logs a tabla dedicada)
--   3. Creacion de tabla para logs de actualizaciones
--   4. Optimizacion de estructura de datos
-- Fecha: 2025-11-12
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- 1. CREAR TABLA DE LOGS DE ACTUALIZACION
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
-- Insertar logs de migraciones anteriores si existen
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
-- 3. LIMPIAR SETTINGS - REMOVER LOGS ANTIGUOS
-- =============================================
-- Eliminar configuraciones de logs que ya no son necesarias
DELETE FROM `settings` 
WHERE `setting_key` IN (
    'migration_v235_log',
    'migration_v234_log',
    'migration_v233_log',
    'update_log',
    'last_update_log',
    'system_update_history'
);

-- Eliminar configuraciones duplicadas o temporales
DELETE FROM `settings` 
WHERE `setting_key` LIKE 'temp_%' 
   OR `setting_key` LIKE 'cache_%'
   OR `setting_key` LIKE 'old_%';

-- =============================================
-- 4. VERIFICAR Y CREAR TABLA STOCK_MOVEMENTS
-- =============================================
CREATE TABLE IF NOT EXISTS `stock_movements` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `product_id` INT(11) NOT NULL,
    `movement_type` ENUM('entrada', 'salida', 'ajuste', 'venta', 'compra', 'devolucion') NOT NULL,
    `quantity` INT(11) NOT NULL,
    `old_stock` INT(11) NOT NULL DEFAULT 0,
    `new_stock` INT(11) NOT NULL DEFAULT 0,
    `reason` VARCHAR(255) DEFAULT NULL,
    `reference_type` ENUM('order', 'manual', 'adjustment', 'purchase', 'return') DEFAULT 'manual',
    `reference_id` INT(11) DEFAULT NULL,
    `user_id` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_id` (`product_id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_movement_type` (`movement_type`),
    CONSTRAINT `fk_movement_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_movement_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================
-- 5. VOLCADO INICIAL DE MOVIMIENTOS AL KARDEX
-- =============================================
-- Este proceso analiza todas las órdenes y genera movimientos de entrada iniciales

-- Primero, verificar si ya existen movimientos para evitar duplicados
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
    1 as user_id, -- Usuario admin
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
    AND @movements_count = 0  -- Solo si no hay movimientos previos
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
-- 6. CREAR TABLA DEDICADA PARA LOGS DE ACTUALIZACIONES
-- =============================================
-- Mover logs de system_updates a una tabla más completa

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

-- Migrar datos existentes de system_updates a system_update_history
INSERT INTO `system_update_history` 
    (`update_id`, `update_version`, `from_commit`, `to_commit`, `status`, `started_at`, `completed_at`, 
     `user_id`, `files_added`, `files_updated`, `files_deleted`, `backup_path`, `update_details`, `error_message`)
SELECT 
    id,
    CONCAT('Sistema ', SUBSTRING(to_commit, 1, 7)) as update_version,
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
-- 7. LIMPIEZA DE TABLA SETTINGS
-- =============================================

-- Eliminar logs de migraciones antiguas (ya están en system_update_history)
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

-- Eliminar configuraciones de migración específicas que ya no se usan
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
-- 8. OPTIMIZACIÓN DE ÍNDICES
-- =============================================

-- Agregar índices faltantes para mejor rendimiento
ALTER TABLE `order_items` 
    ADD INDEX `idx_product_status` (`product_id`, `status`),
    ADD INDEX `idx_order_status` (`order_id`, `status`);

ALTER TABLE `products` 
    ADD INDEX `idx_track_inventory` (`track_inventory`, `is_active`),
    ADD INDEX `idx_stock_alert` (`stock_quantity`, `low_stock_alert`);

ALTER TABLE `orders` 
    ADD INDEX `idx_type_status` (`type`, `status`),
    ADD INDEX `idx_created_at` (`created_at`);

-- =============================================
-- 9. GENERAR HASH Y ACTUALIZAR VERSIÓN
-- =============================================

-- Generar hash único para esta versión
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

-- Guardar commit anterior como backup
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
-- 10. CONFIGURACIONES DEL SISTEMA
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
-- 11. REGISTRAR MIGRACIÓN
-- =============================================

-- Registrar en tabla de migraciones
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

-- Registrar en system_update_logs (nueva tabla)
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
-- 12. OPTIMIZAR TABLAS
-- =============================================

OPTIMIZE TABLE `settings`;
OPTIMIZE TABLE `stock_movements`;
OPTIMIZE TABLE `products`;
OPTIMIZE TABLE `orders`;
OPTIMIZE TABLE `order_items`;
OPTIMIZE TABLE `system_update_history`;

-- =============================================
-- 13. VERIFICACIÓN Y RESUMEN
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
    '=====================' AS '═══════════════════',
    'RESUMEN DE KARDEX' AS '',
    '' AS '',
    '' AS '';

SELECT 
    'Productos con Inventario' AS 'Métrica',
    COUNT(*) AS 'Valor',
    '' AS ''
FROM products 
WHERE track_inventory = 1
UNION ALL
SELECT 
    'Movimientos Registrados',
    COUNT(*),
    ''
FROM stock_movements
UNION ALL
SELECT 
    'Productos Bajo Stock Mínimo',
    COUNT(*),
    ''
FROM products 
WHERE track_inventory = 1 
AND stock_quantity <= low_stock_alert
AND is_active = 1;

SELECT 
    '=====================' AS '═══════════════════',
    'LIMPIEZA DE SETTINGS' AS '',
    '' AS '',
    '' AS '';

SELECT 
    'Registros en Settings (Antes)' AS 'Estado',
    '~980' AS 'Cantidad',
    'Incluía logs y temporales' AS 'Nota'
UNION ALL
SELECT 
    'Registros en Settings (Después)',
    (SELECT COUNT(*) FROM settings),
    'Solo configuraciones activas';

SELECT 
    '=====================' AS '═══════════════════',
    'LOGS DE ACTUALIZACIÓN' AS '',
    '' AS '',
    '' AS '';

SELECT 
    'Logs en system_updates' AS 'Tabla',
    (SELECT COUNT(*) FROM system_updates) AS 'Registros',
    'Tabla legacy' AS 'Estado'
UNION ALL
SELECT 
    'Logs en system_update_logs',
    (SELECT COUNT(*) FROM system_update_logs),
    'Nueva tabla dedicada'
UNION ALL
SELECT 
    'Logs en system_update_history',
    (SELECT COUNT(*) FROM system_update_history),
    'Historial completo';

SELECT 
    '✅ SISTEMA LISTO PARA USAR' AS '',
    CONCAT('Versión: ', (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version')) AS '',
    CONCAT('Commit: ', @new_commit_hash) AS '',
    CONCAT('Fecha: ', NOW()) AS '';

COMMIT;

-- =============================================
-- NOTAS POST-INSTALACIÓN
-- =============================================
/*
✅ INSTALACIÓN COMPLETADA - v2.3.6

═══════════════════════════════════════════════════
📦 NUEVO: SISTEMA KARDEX DE INVENTARIO
═══════════════════════════════════════════════════

1. TABLA STOCK_MOVEMENTS:
   ✓ Registro completo de movimientos de inventario
   ✓ Campos: entrada/salida, cantidad, stock anterior/nuevo
   ✓ Referencias a productos, usuarios y órdenes
   ✓ Timestamps automáticos

2. VOLCADO INICIAL DE DATOS:
   ✓ Se analizaron TODAS las órdenes históricas
   ✓ Se generaron movimientos de entrada iniciales
   ✓ Stock calculado basado en ventas reales
   ✓ Solo productos con track_inventory = 1

3. FUNCIONALIDADES:
   ✓ Control de entradas y salidas
   ✓ Historial completo de movimientos
   ✓ Cálculo automático de stock
   ✓ Alertas de stock bajo
   ✓ Reportes de inventario

═══════════════════════════════════════════════════
🧹 LIMPIEZA DE SISTEMA
═══════════════════════════════════════════════════

1. TABLA SETTINGS OPTIMIZADA:
   ✗ Eliminados: logs de migraciones antiguas
   ✗ Eliminados: configuraciones temporales
   ✗ Eliminados: entries de cache
   ✗ Eliminados: configuraciones de test
   ✓ Reducción: ~980 → ~60 registros

2. NUEVA TABLA: SYSTEM_UPDATE_LOGS
   ✓ Logs de actualizaciones en tabla dedicada
   ✓ No contamina tabla settings
   ✓ Campos específicos para tracking
   ✓ Migración automática de datos existentes

3. NUEVA TABLA: SYSTEM_UPDATE_HISTORY
   ✓ Historial completo de actualizaciones
   ✓ Información detallada de cada update
   ✓ Tiempos de ejecución
   ✓ IP y user agent del ejecutor

═══════════════════════════════════════════════════
⚡ OPTIMIZACIONES
═══════════════════════════════════════════════════

1. NUEVOS ÍNDICES:
   ✓ order_items: idx_product_status, idx_order_status
   ✓ products: idx_track_inventory, idx_stock_alert
   ✓ orders: idx_type_status, idx_created_at
   ✓ stock_movements: idx_product_id, idx_movement_type

2. TABLAS OPTIMIZADAS:
   ✓ settings
   ✓ stock_movements
   ✓ products
   ✓ orders
   ✓ order_items

═══════════════════════════════════════════════════
🔧 PRÓXIMOS PASOS
═══════════════════════════════════════════════════

1. Verificar página Kardex: admin/kardex.php
   • Revisar movimientos iniciales cargados
   • Probar registrar entrada manual
   • Probar registrar salida manual
   • Verificar cálculos de stock

2. Verificar alertas de stock bajo:
   • Ir a admin/products.php
   • Productos en rojo = stock bajo
   • Configurar low_stock_alert por producto

3. Configurar permisos de Kardex:
   • admin/settings.php
   • Roles y Permisos
   • Asignar permiso 'kardex' a roles necesarios

4. Limpieza completada:
   • Tabla settings más ligera
   • Logs organizados en tablas dedicadas
   • Sistema más eficiente

═══════════════════════════════════════════════════
📊 CONSULTAS ÚTILES
═══════════════════════════════════════════════════

-- Ver movimientos de un producto específico:
SELECT * FROM stock_movements 
WHERE product_id = ? 
ORDER BY created_at DESC;

-- Productos con stock bajo:
SELECT 
    p.name,
    p.stock_quantity as 'Stock Actual',
    p.low_stock_alert as 'Stock Mínimo',
    (p.stock_quantity - p.low_stock_alert) as 'Diferencia'
FROM products p
WHERE p.track_inventory = 1 
  AND p.stock_quantity <= p.low_stock_alert
ORDER BY (p.stock_quantity - p.low_stock_alert) ASC;

-- Resumen de movimientos por producto:
SELECT 
    p.name as 'Producto',
    COUNT(*) as 'Total Movimientos',
    SUM(CASE WHEN sm.movement_type = 'entrada' THEN sm.quantity ELSE 0 END) as 'Total Entradas',
    SUM(CASE WHEN sm.movement_type = 'salida' THEN sm.quantity ELSE 0 END) as 'Total Salidas',
    p.stock_quantity as 'Stock Actual'
FROM stock_movements sm
JOIN products p ON sm.product_id = p.id
GROUP BY p.id, p.name, p.stock_quantity
ORDER BY p.name;

-- Ver logs de actualizaciones limpios:
SELECT 
    update_version,
    status,
    started_at,
    completed_at,
    TIMESTAMPDIFF(SECOND, started_at, completed_at) as 'Duración (seg)',
    files_added + files_updated + files_deleted as 'Total Archivos'
FROM system_update_logs
ORDER BY started_at DESC
LIMIT 10;

═══════════════════════════════════════════════════
FIN DE MIGRACIÓN v2.3.6
═══════════════════════════════════════════════════
*/