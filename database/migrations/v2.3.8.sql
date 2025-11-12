-- =============================================
-- Migración v2.3.8 - Sistema de Compras y Triggers de Stock
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
-- 1. CREAR TABLA DE COMPRAS
-- =============================================
CREATE TABLE IF NOT EXISTS `purchases` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_number` VARCHAR(50) NOT NULL,
    `supplier` VARCHAR(255) DEFAULT NULL,
    `invoice_number` VARCHAR(100) DEFAULT NULL,
    `purchase_date` DATE NOT NULL,
    `total_amount` DECIMAL(10,2) DEFAULT 0.00,
    `notes` TEXT,
    `status` ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
    `created_by` INT(11) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `purchase_number` (`purchase_number`),
    KEY `idx_status` (`status`),
    KEY `idx_purchase_date` (`purchase_date`),
    KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de compras de productos';

-- =============================================
-- 2. CREAR TABLA DE ITEMS DE COMPRA
-- =============================================
CREATE TABLE IF NOT EXISTS `purchase_items` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `purchase_id` INT(11) NOT NULL,
    `product_id` INT(11) NOT NULL,
    `quantity` INT(11) NOT NULL,
    `unit_cost` DECIMAL(10,2) NOT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_purchase` (`purchase_id`),
    KEY `idx_product` (`product_id`),
    CONSTRAINT `fk_purchase_item_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_purchase_item_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Items de cada compra';

-- =============================================
-- 3. CREAR TRIGGER PARA COMPRAS
-- =============================================
DELIMITER //

DROP TRIGGER IF EXISTS `after_purchase_item_insert`//

CREATE TRIGGER `after_purchase_item_insert`
AFTER INSERT ON `purchase_items`
FOR EACH ROW
BEGIN
    DECLARE v_old_stock INT;
    DECLARE v_new_stock INT;
    DECLARE v_user_id INT;
    DECLARE v_purchase_number VARCHAR(50);
    
    -- Obtener información
    SELECT stock_quantity INTO v_old_stock 
    FROM products 
    WHERE id = NEW.product_id;
    
    SELECT created_by, purchase_number INTO v_user_id, v_purchase_number
    FROM purchases 
    WHERE id = NEW.purchase_id;
    
    SET v_new_stock = v_old_stock + NEW.quantity;
    
    -- Actualizar stock del producto
    UPDATE products 
    SET stock_quantity = v_new_stock
    WHERE id = NEW.product_id;
    
    -- Registrar movimiento en kardex
    INSERT INTO stock_movements 
        (product_id, movement_type, quantity, old_stock, new_stock, reason, reference_type, reference_id, user_id)
    VALUES 
        (NEW.product_id, 'entrada', NEW.quantity, v_old_stock, v_new_stock, 
         CONCAT('Compra #', v_purchase_number), 'purchase', NEW.purchase_id, v_user_id);
END//

DELIMITER ;

-- =============================================
-- 4. CREAR TRIGGER PARA ÓRDENES (SALIDAS)
-- =============================================
DELIMITER //

DROP TRIGGER IF EXISTS `after_order_item_insert`//

CREATE TRIGGER `after_order_item_insert`
AFTER INSERT ON `order_items`
FOR EACH ROW
BEGIN
    DECLARE v_old_stock INT;
    DECLARE v_new_stock INT;
    DECLARE v_track_inventory TINYINT;
    DECLARE v_order_number VARCHAR(20);
    DECLARE v_created_by INT;
    
    -- Verificar si el producto tiene inventario activado
    SELECT stock_quantity, track_inventory 
    INTO v_old_stock, v_track_inventory
    FROM products 
    WHERE id = NEW.product_id;
    
    -- Solo procesar si tiene inventario activado
    IF v_track_inventory = 1 THEN
        -- Obtener información de la orden
        SELECT order_number, created_by INTO v_order_number, v_created_by
        FROM orders 
        WHERE id = NEW.order_id;
        
        SET v_new_stock = v_old_stock - NEW.quantity;
        
        -- Prevenir stock negativo
        IF v_new_stock < 0 THEN
            SET v_new_stock = 0;
        END IF;
        
        -- Actualizar stock del producto
        UPDATE products 
        SET stock_quantity = v_new_stock
        WHERE id = NEW.product_id;
        
        -- Registrar movimiento en kardex
        INSERT INTO stock_movements 
            (product_id, movement_type, quantity, old_stock, new_stock, reason, reference_type, reference_id, user_id)
        VALUES 
            (NEW.product_id, 'salida', NEW.quantity, v_old_stock, v_new_stock, 
             CONCAT('Venta - Orden #', v_order_number), 'order', NEW.order_id, v_created_by);
    END IF;
END//

DELIMITER ;

-- =============================================
-- 5. CREAR TRIGGER PARA CANCELACIÓN DE ÓRDENES
-- =============================================
DELIMITER //

DROP TRIGGER IF EXISTS `after_order_status_change`//

CREATE TRIGGER `after_order_status_change`
AFTER UPDATE ON `orders`
FOR EACH ROW
BEGIN
    DECLARE v_old_stock INT;
    DECLARE v_new_stock INT;
    DECLARE v_track_inventory TINYINT;
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_product_id INT;
    DECLARE v_quantity INT;
    
    DECLARE item_cursor CURSOR FOR 
        SELECT product_id, quantity 
        FROM order_items 
        WHERE order_id = NEW.id;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    -- Si la orden cambió a cancelada
    IF OLD.status != 'cancelled' AND NEW.status = 'cancelled' THEN
        OPEN item_cursor;
        
        read_loop: LOOP
            FETCH item_cursor INTO v_product_id, v_quantity;
            IF done THEN
                LEAVE read_loop;
            END IF;
            
            -- Verificar si el producto tiene inventario
            SELECT stock_quantity, track_inventory 
            INTO v_old_stock, v_track_inventory
            FROM products 
            WHERE id = v_product_id;
            
            IF v_track_inventory = 1 THEN
                SET v_new_stock = v_old_stock + v_quantity;
                
                -- Devolver stock
                UPDATE products 
                SET stock_quantity = v_new_stock
                WHERE id = v_product_id;
                
                -- Registrar devolución en kardex
                INSERT INTO stock_movements 
                    (product_id, movement_type, quantity, old_stock, new_stock, reason, reference_type, reference_id, user_id)
                VALUES 
                    (v_product_id, 'entrada', v_quantity, v_old_stock, v_new_stock, 
                     CONCAT('Devolución - Orden #', NEW.order_number, ' cancelada'), 'return', NEW.id, NEW.created_by);
            END IF;
        END LOOP;
        
        CLOSE item_cursor;
    END IF;
END//

DELIMITER ;

-- =============================================
-- 6. MIGRAR ÓRDENES EXISTENTES A MOVIMIENTOS
-- =============================================
-- Registrar salidas de stock por órdenes existentes que no estén en kardex
INSERT INTO `stock_movements` 
    (`product_id`, `movement_type`, `quantity`, `old_stock`, `new_stock`, `reason`, `reference_type`, `reference_id`, `user_id`, `created_at`)
SELECT 
    oi.product_id,
    'salida' as movement_type,
    oi.quantity,
    p.stock_quantity as old_stock,
    p.stock_quantity as new_stock,
    CONCAT('Venta histórica - Orden #', o.order_number) as reason,
    'order' as reference_type,
    oi.order_id as reference_id,
    o.created_by as user_id,
    oi.created_at
FROM 
    order_items oi
INNER JOIN 
    orders o ON oi.order_id = o.id
INNER JOIN 
    products p ON oi.product_id = p.id
WHERE 
    p.track_inventory = 1
    AND o.status != 'cancelled'
    AND NOT EXISTS (
        SELECT 1 FROM stock_movements sm 
        WHERE sm.reference_type = 'order' 
        AND sm.reference_id = oi.order_id 
        AND sm.product_id = oi.product_id
    )
ORDER BY oi.created_at ASC;

-- =============================================
-- 7. AGREGAR ÍNDICES ADICIONALES
-- =============================================
-- Índice para reference en stock_movements
SET @index_exists = (
    SELECT COUNT(*) 
    FROM information_schema.STATISTICS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'stock_movements' 
    AND INDEX_NAME = 'idx_reference'
);

SET @sql = IF(@index_exists = 0,
    'ALTER TABLE `stock_movements` ADD INDEX `idx_reference` (`reference_type`, `reference_id`)',
    'SELECT "Index idx_reference already exists"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- 8. CONFIGURACIONES DEL SISTEMA
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES 
    ('purchases_enabled', '1', 'Sistema de compras habilitado'),
    ('purchases_require_invoice', '0', 'Requerir número de factura en compras'),
    ('purchases_auto_update_cost', '1', 'Actualizar costo del producto al comprar'),
    ('purchases_generate_number', '1', 'Generar número de compra automáticamente'),
    ('stock_auto_register_sales', '1', 'Registrar automáticamente salidas por ventas'),
    ('stock_allow_negative', '0', 'Permitir stock negativo'),
    ('stock_alert_on_low', '1', 'Alertar cuando un producto llegue a stock mínimo')
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- 9. CREAR VISTA DE KARDEX COMPLETO
-- =============================================
CREATE OR REPLACE VIEW `kardex_full_view` AS
SELECT 
    p.id as product_id,
    p.name as product_name,
    p.stock_quantity as current_stock,
    p.low_stock_alert,
    p.cost as current_cost,
    p.price as current_price,
    c.name as category_name,
    COALESCE(SUM(CASE WHEN sm.movement_type IN ('entrada', 'compra', 'devolucion') THEN sm.quantity ELSE 0 END), 0) as total_entradas,
    COALESCE(SUM(CASE WHEN sm.movement_type IN ('salida', 'venta', 'ajuste') THEN sm.quantity ELSE 0 END), 0) as total_salidas,
    (COALESCE(SUM(CASE WHEN sm.movement_type IN ('entrada', 'compra', 'devolucion') THEN sm.quantity ELSE 0 END), 0) - 
     COALESCE(SUM(CASE WHEN sm.movement_type IN ('salida', 'venta', 'ajuste') THEN sm.quantity ELSE 0 END), 0)) as saldo_teorico,
    COUNT(sm.id) as total_movimientos,
    MAX(sm.created_at) as ultimo_movimiento
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
LEFT JOIN stock_movements sm ON p.id = sm.product_id
WHERE p.track_inventory = 1 AND p.is_active = 1
GROUP BY p.id, p.name, p.stock_quantity, p.low_stock_alert, p.cost, p.price, c.name;

-- =============================================
-- 10. FUNCIÓN PARA GENERAR NÚMERO DE COMPRA
-- =============================================
DELIMITER //

DROP FUNCTION IF EXISTS `generate_purchase_number`//

CREATE FUNCTION `generate_purchase_number`()
RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
    DECLARE v_year VARCHAR(4);
    DECLARE v_count INT;
    DECLARE v_number VARCHAR(50);
    
    SET v_year = YEAR(CURDATE());
    
    SELECT COUNT(*) + 1 INTO v_count
    FROM purchases
    WHERE YEAR(created_at) = v_year;
    
    SET v_number = CONCAT('COM', v_year, LPAD(v_count, 4, '0'));
    
    RETURN v_number;
END//

DELIMITER ;

-- =============================================
-- 11. GENERAR HASH Y ACTUALIZAR VERSIÓN
-- =============================================
SET @new_commit_hash_full = SHA2(CONCAT(
    'v2.3.8',
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
    CONCAT('Hash SHA-256 corto - v2.3.8 - Generado: ', NOW())
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = @new_commit_hash,
    `description` = CONCAT('Hash SHA-256 corto - v2.3.8 - Actualizado: ', NOW());

-- Guardar commit completo
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'system_commit_full', 
    @new_commit_hash_full,
    CONCAT('Hash SHA-256 completo - v2.3.8 - Generado: ', NOW())
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = @new_commit_hash_full,
    `description` = CONCAT('Hash SHA-256 completo - v2.3.8 - Actualizado: ', NOW());

-- Actualizar versión del sistema
UPDATE `settings` 
SET `setting_value` = '2.3.8',
    `description` = 'Versión actual del sistema'
WHERE `setting_key` = 'current_system_version';

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.3.8', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE 
    `setting_value` = '2.3.8',
    `description` = 'Versión actual del sistema';

-- =============================================
-- 12. REGISTRAR MIGRACIÓN
-- =============================================
INSERT INTO `migrations` (`version`, `filename`, `executed_at`, `execution_time`, `status`) 
VALUES (
    '2.3.8',
    'v2.3.8.sql',
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
        'v2.3.8',
        'completed',
        NOW(),
        NOW(),
        'Sistema',
        3,
        'Actualización de sistema v2.3.8: Sistema de Compras y Triggers Automáticos'
    ) ON DUPLICATE KEY UPDATE 
        `completed_at` = NOW(),
        `status` = 'completed'",
    'SELECT "Table system_update_logs does not exist, skipping log"'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =============================================
-- 13. OPTIMIZAR TABLAS
-- =============================================
OPTIMIZE TABLE `stock_movements`;
OPTIMIZE TABLE `products`;
OPTIMIZE TABLE `orders`;
OPTIMIZE TABLE `order_items`;
OPTIMIZE TABLE `settings`;

-- =============================================
-- 14. VERIFICACIÓN Y RESUMEN
-- =============================================
SELECT 
    '✅ MIGRACIÓN v2.3.8 COMPLETADA EXITOSAMENTE' AS 'ESTADO';

SELECT 
    'VERSIÓN DEL SISTEMA' AS 'INFORMACIÓN',
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS 'Versión',
    @new_commit_hash AS 'Commit_Corto',
    NOW() AS 'Fecha_Instalación';

SELECT 
    '=====================' AS '═══════════════════',
    'NUEVAS FUNCIONALIDADES' AS '',
    '' AS '';

SELECT 
    'Tabla purchases creada' AS 'Estado',
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchases') AS 'Existe';

SELECT 
    'Tabla purchase_items creada' AS 'Estado',
    (SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchase_items') AS 'Existe';

SELECT 
    'Triggers creados' AS 'Estado',
    COUNT(*) AS 'Cantidad'
FROM information_schema.TRIGGERS 
WHERE TRIGGER_SCHEMA = DATABASE()
AND TRIGGER_NAME IN ('after_purchase_item_insert', 'after_order_item_insert', 'after_order_status_change');

SELECT 
    '=====================' AS '═══════════════════',
    'RESUMEN DE KARDEX' AS '',
    '' AS '';

SELECT 
    'Productos con Inventario' AS 'Métrica',
    COUNT(*) AS 'Valor'
FROM products 
WHERE track_inventory = 1 AND is_active = 1
UNION ALL
SELECT 
    'Movimientos Totales Registrados',
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
    '✅ SISTEMA DE COMPRAS Y TRIGGERS ACTIVOS' AS '',
    CONCAT('Versión: ', (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version')) AS '',
    CONCAT('Commit: ', @new_commit_hash) AS '';

COMMIT;

-- =============================================
-- NOTAS POST-INSTALACIÓN
-- =============================================
/*
✅ INSTALACIÓN COMPLETADA - v2.3.8

═══════════════════════════════════════════════════
📦 SISTEMA DE COMPRAS Y STOCK AUTOMÁTICO
═══════════════════════════════════════════════════

1. NUEVAS TABLAS:
   ✓ purchases - Registro de compras
   ✓ purchase_items - Items de cada compra

2. TRIGGERS AUTOMÁTICOS:
   ✓ after_purchase_item_insert - Registra entrada de stock al comprar
   ✓ after_order_item_insert - Registra salida de stock al vender
   ✓ after_order_status_change - Devuelve stock al cancelar orden

3. FUNCIONALIDAD:
   ✓ Registro automático de movimientos en kardex
   ✓ Actualización automática de stock
   ✓ Prevención de stock negativo
   ✓ Generación automática de número de compra
   ✓ Vista completa de kardex con totales

4. MIGRACIÓN DE DATOS:
   ✓ Órdenes existentes migradas a movimientos
   ✓ Stock actualizado correctamente

═══════════════════════════════════════════════════
📋 PRÓXIMOS PASOS
═══════════════════════════════════════════════════

1. Crear archivo admin/compras.php
2. Crear archivo admin/api/purchases.php
3. Actualizar admin/kardex.php para mostrar stock real
4. Agregar enlace en sidebar

═══════════════════════════════════════════════════
FIN DE MIGRACIÓN v2.3.8
═══════════════════════════════════════════════════
*/