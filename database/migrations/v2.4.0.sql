-- =============================================
-- 4. AGREGAR ÍNDICES PARA OPTIMIZACIÓN
-- =============================================

-- Índice para mejorar consultas de kardex por tipo de movimiento
-- Verificar si existe antes de crear
SET @index_exists_movement = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'stock_movements'
    AND INDEX_NAME = 'idx_movement_type'
);

SET @sql_movement = IF(@index_exists_movement = 0,
    'ALTER TABLE `stock_movements` ADD INDEX `idx_movement_type` (`movement_type`)',
    'SELECT "Index idx_movement_type already exists" AS result'
);

PREPARE stmt FROM @sql_movement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Índice para mejorar consultas de productos por tracking
SET @index_exists_track = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'products'
    AND INDEX_NAME = 'idx_track_inventory'
);

SET @sql_track = IF(@index_exists_track = 0,
    'ALTER TABLE `products` ADD INDEX `idx_track_inventory` (`track_inventory`)',
    'SELECT "Index idx_track_inventory already exists" AS result'
);

PREPARE stmt FROM @sql_track;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;