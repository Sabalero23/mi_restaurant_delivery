-- =============================================
-- Migración v2.2.6 - Sistema Inteligente de Eliminación de Productos
-- =============================================
UPDATE `settings` 
SET `setting_value` = '2.2.6' 
WHERE `setting_key` = 'current_system_version';

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('migration_v226_date', NOW(), 'Fecha de migración a v2.2.6')
ON DUPLICATE KEY UPDATE `setting_value` = NOW();

-- =============================================
-- 9. Log de migración
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'migration_v226_log', 
    'Sistema inteligente de eliminación implementado. Productos con pedidos se desactivan, productos sin pedidos se eliminan permanentemente.',
    'Registro de cambios v2.2.6'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`);

-- =============================================
-- 10. Verificación final
-- =============================================
SELECT 
    'Migración v2.2.6 completada exitosamente' AS status,
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS version,
    (SELECT COUNT(*) FROM products WHERE is_active = 0) AS productos_desactivados,
    (SELECT COUNT(*) FROM products WHERE track_inventory = 1) AS productos_con_inventario,
    NOW() AS fecha_migracion;

COMMIT;

-- =============================================
-- NOTAS DE LA MIGRACIÓN
-- =============================================
-- 
-- Esta migración implementa:
-- 
-- 1. ✅ Sistema de eliminación inteligente
--    - Soft delete para productos con pedidos
--    - Hard delete para productos sin pedidos
-- 
-- 2. ✅ Preservación de historial
--    - Los pedidos mantienen el nombre del producto
--    - Los datos históricos se conservan
-- 
-- 3. ✅ Gestión de inventario mejorada
--    - Tabla de movimientos de stock
--    - Alertas de stock bajo
--    - Control de inventario por producto
-- 
-- 4. ✅ Vista de productos con pedidos
--    - Facilita consultas de productos activos/inactivos
--    - Estadísticas de ventas por producto
-- 
-- 5. ✅ Procedimiento almacenado
--    - Lógica de eliminación centralizada
--    - Puede ser usado desde PHP o directamente en SQL
-- 
-- COMPATIBILIDAD:
-- - MySQL 5.7+
-- - MariaDB 10.2+
-- 
-- ROLLBACK:
-- Si necesita revertir esta migración, ejecute:
-- UPDATE settings SET setting_value = '2.2.4' WHERE setting_key = 'current_system_version';
--
-- =============================================