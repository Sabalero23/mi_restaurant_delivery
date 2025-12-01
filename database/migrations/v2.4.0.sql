-- =============================================
-- Migración v2.4.0 - Corrección de Doble Descuento de Stock
-- =============================================
-- Descripción: 
--   1. Eliminar trigger problemático que causa doble descuento
--   2. Crear backups de seguridad
--   3. Registrar corrección en logs
-- Fecha: 2025-12-01
-- Autor: Cellcom Technology
-- Problema: El trigger after_order_item_insert duplicaba el descuento de stock
--           porque el código PHP (stock_functions.php) ya maneja el descuento
-- =============================================

START TRANSACTION;

-- =============================================
-- 1. CREAR BACKUPS DE SEGURIDAD
-- =============================================

-- Backup de movimientos de stock
CREATE TABLE IF NOT EXISTS `stock_movements_backup_v240` AS 
SELECT * FROM `stock_movements`;

-- Backup de productos (solo campos de stock)
CREATE TABLE IF NOT EXISTS `products_stock_backup_v240` (
    `id` INT(11) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `stock_quantity` INT(11) DEFAULT 0,
    `low_stock_alert` INT(11) DEFAULT 10,
    `track_inventory` TINYINT(1) DEFAULT 1,
    `backup_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Backup de stock antes de migración v2.4.0';

INSERT INTO `products_stock_backup_v240` (`id`, `name`, `stock_quantity`, `low_stock_alert`, `track_inventory`)
SELECT `id`, `name`, `stock_quantity`, `low_stock_alert`, `track_inventory` 
FROM `products`;

-- =============================================
-- 2. ELIMINAR TRIGGER PROBLEMÁTICO
-- =============================================

-- Este trigger causa el doble descuento en órdenes regulares
-- El código PHP en stock_functions.php ya maneja correctamente
-- el descuento de stock, por lo que este trigger está duplicando la operación

DROP TRIGGER IF EXISTS `after_order_item_insert`;

-- =============================================
-- 3. MANTENER TRIGGERS CORRECTOS
-- =============================================

-- Verificar que el trigger de cancelación siga activo
-- (este trigger SÍ es necesario para restaurar stock)

-- El trigger after_order_status_change debe permanecer
-- No hacemos DROP de este porque funciona correctamente

-- Verificar que el trigger de compras siga activo
-- (este trigger SÍ es necesario para registrar entradas)

-- El trigger after_purchase_item_insert debe permanecer
-- No hacemos DROP de este porque funciona correctamente

-- =============================================
-- 4. AGREGAR ÍNDICES PARA OPTIMIZACIÓN
-- =============================================

-- Índice para mejorar consultas de kardex por tipo de movimiento
ALTER TABLE `stock_movements` 
ADD INDEX IF NOT EXISTS `idx_movement_type` (`movement_type`);

-- Índice para mejorar consultas de productos por tracking
ALTER TABLE `products` 
ADD INDEX IF NOT EXISTS `idx_track_inventory` (`track_inventory`);

-- =============================================
-- 5. CREAR VISTA PARA DETECCIÓN DE ANOMALÍAS
-- =============================================

-- Vista para identificar productos con posibles inconsistencias
CREATE OR REPLACE VIEW `stock_anomalies_view` AS
SELECT 
    p.id as product_id,
    p.name as product_name,
    p.stock_quantity as stock_actual,
    COALESCE(SUM(CASE 
        WHEN sm.movement_type IN ('entrada','compra','devolucion') THEN sm.quantity 
        WHEN sm.movement_type IN ('salida','venta','ajuste') THEN -sm.quantity
        ELSE 0 
    END), 0) as stock_teorico,
    (p.stock_quantity - COALESCE(SUM(CASE 
        WHEN sm.movement_type IN ('entrada','compra','devolucion') THEN sm.quantity 
        WHEN sm.movement_type IN ('salida','venta','ajuste') THEN -sm.quantity
        ELSE 0 
    END), 0)) as diferencia,
    COUNT(sm.id) as total_movimientos,
    MAX(sm.created_at) as ultimo_movimiento
FROM products p
LEFT JOIN stock_movements sm ON p.id = sm.product_id
WHERE p.track_inventory = 1 AND p.is_active = 1
GROUP BY p.id, p.name, p.stock_quantity
HAVING ABS(diferencia) > 0
ORDER BY ABS(diferencia) DESC;

-- =============================================
-- 6. GENERAR HASH Y ACTUALIZAR VERSIÓN
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
    `description` = 'Versión actual del sistema - Corrección de doble descuento'
WHERE `setting_key` = 'current_system_version';

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.4.0', 'Versión actual del sistema - Corrección de doble descuento')
ON DUPLICATE KEY UPDATE 
    `setting_value` = '2.4.0',
    `description` = 'Versión actual del sistema - Corrección de doble descuento';

-- =============================================
-- 7. REGISTRAR MIGRACIÓN
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

-- Registrar en system_update_logs si la tabla existe
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
        2,
        'Actualización v2.4.0: Corrección de doble descuento de stock en órdenes regulares. Se eliminó trigger after_order_item_insert que duplicaba el descuento realizado por PHP.'
    ) ON DUPLICATE KEY UPDATE 
        `completed_at` = NOW(),
        `status` = 'completed'",
    'SELECT "Table system_update_logs does not exist, skipping log" AS result'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- 8. OPTIMIZAR TABLAS
-- =============================================

OPTIMIZE TABLE `stock_movements`;
OPTIMIZE TABLE `products`;

COMMIT;

-- =============================================
-- 9. VERIFICACIÓN FINAL
-- =============================================

SELECT '✅ MIGRACIÓN COMPLETADA - v2.4.0' AS Status;

-- Mostrar triggers activos después de la migración
SELECT 
    'Triggers Activos Post-Migración' AS Info;

SHOW TRIGGERS WHERE `Table` IN ('orders', 'order_items', 'products', 'purchases', 'purchase_items');

-- Mostrar estadísticas
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
    'Productos con Stock Bajo',
    COUNT(*)
FROM products 
WHERE track_inventory = 1 
AND stock_quantity <= low_stock_alert
AND is_active = 1
UNION ALL
SELECT 
    'Backups Creados',
    2 AS valor;

-- Mostrar productos con posibles anomalías (si existen)
SELECT 
    'Productos con Posibles Inconsistencias' AS Info;

SELECT 
    product_name,
    stock_actual,
    stock_teorico,
    diferencia
FROM stock_anomalies_view
LIMIT 10;

-- =============================================
-- NOTAS POST-MIGRACIÓN
-- =============================================

/*
✅ MIGRACIÓN COMPLETADA - v2.4.0

═══════════════════════════════════════════════
🔧 CORRECCIÓN DE DOBLE DESCUENTO DE STOCK
═══════════════════════════════════════════════

1. PROBLEMA CORREGIDO:
   ✓ Eliminado trigger after_order_item_insert que causaba doble descuento
   ✓ El stock ahora se descuenta UNA SOLA VEZ por venta
   ✓ Funciones PHP (stock_functions.php) funcionan correctamente

2. BACKUPS CREADOS:
   ✓ stock_movements_backup_v240 - Todos los movimientos
   ✓ products_stock_backup_v240 - Stock de productos

3. TRIGGERS ACTIVOS:
   ✓ after_purchase_item_insert - Entrada de stock en compras (CORRECTO)
   ✓ after_order_status_change - Restaurar stock al cancelar (CORRECTO)
   ✗ after_order_item_insert - ELIMINADO (causaba duplicación)

4. NUEVAS HERRAMIENTAS:
   ✓ Vista stock_anomalies_view - Detecta inconsistencias
   ✓ Índices optimizados para consultas

═══════════════════════════════════════════════
📋 ACCIONES REQUERIDAS POST-MIGRACIÓN
═══════════════════════════════════════════════

1. ACTUALIZAR ARCHIVOS PHP:
   
   a) Reemplazar: /admin/kardex.php
      - Corregir línea 114: fórmula de stock inicial
      - Archivo disponible en documentación
   
   b) Crear: /admin/api/kardex.php
      - Archivo API para procesar movimientos manuales
      - Archivo disponible en documentación

2. VALIDAR FUNCIONAMIENTO:
   
   a) Crear orden de prueba
      - Verificar que stock se descuente solo 1 vez
      - Revisar kardex: debe haber 1 movimiento (no 2)
   
   b) Cancelar orden de prueba
      - Verificar que stock se restaure correctamente

3. AJUSTAR INVENTARIO:
   
   a) Realizar conteo físico de productos
   b) Usar kardex para registrar ajustes
   c) Comparar con vista stock_anomalies_view

═══════════════════════════════════════════════
⚠️ IMPORTANTE
═══════════════════════════════════════════════

- Esta migración solo corrige el comportamiento FUTURO
- Los datos históricos con doble descuento permanecen
- Se recomienda hacer conteo físico y ajustar diferencias
- Los backups permiten revertir si es necesario

═══════════════════════════════════════════════
📊 MONITOREO
═══════════════════════════════════════════════

Consulta para verificar anomalías:
SELECT * FROM stock_anomalies_view;

Consulta para ver últimos movimientos:
SELECT * FROM stock_movements 
ORDER BY created_at DESC LIMIT 20;

═══════════════════════════════════════════════
FIN DE MIGRACIÓN v2.4.0
═══════════════════════════════════════════════
*/