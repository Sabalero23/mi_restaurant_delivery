-- =============================================
-- Migración v2.3.1 - Edición Masiva de Productos
-- =============================================
-- Descripción: Implementación de funcionalidad de edición masiva
--              Permite actualizar stock, precios de costo y venta
--              de múltiples productos simultáneamente
-- Fecha: 2025-01-06
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- Verificar que existe la clave current_system_version
-- Si no existe, crearla con valor inicial
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.3.1', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =============================================
-- Actualizar versión del sistema a 2.3.1
-- =============================================
UPDATE `settings` 
SET `setting_value` = '2.3.1' 
WHERE `setting_key` = 'current_system_version';

-- =============================================
-- Registrar fecha de esta migración
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('migration_v231_date', NOW(), 'Fecha de migración a v2.3.1')
ON DUPLICATE KEY UPDATE `setting_value` = NOW();

-- =============================================
-- NUEVA FUNCIONALIDAD: Tabla de log para ediciones masivas
-- =============================================
CREATE TABLE IF NOT EXISTS `bulk_edit_logs` (
    `id` INT(11) NOT NULL AUTO_INCREMENT,
    `user_id` INT(11) NOT NULL,
    `products_updated` INT(11) NOT NULL DEFAULT 0,
    `changes_summary` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id` (`user_id`),
    KEY `idx_created_at` (`created_at`),
    CONSTRAINT `fk_bulk_edit_user` 
        FOREIGN KEY (`user_id`) 
        REFERENCES `users` (`id`) 
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de ediciones masivas de productos';

-- =============================================
-- MEJORA: Agregar índices para optimizar consultas de edición masiva
-- =============================================
-- Índice para búsqueda por estado activo
ALTER TABLE `products` 
ADD INDEX IF NOT EXISTS `idx_is_active` (`is_active`);

-- Índice para búsqueda por inventario
ALTER TABLE `products` 
ADD INDEX IF NOT EXISTS `idx_track_inventory` (`track_inventory`);

-- Índice compuesto para listado de productos activos con inventario
ALTER TABLE `products` 
ADD INDEX IF NOT EXISTS `idx_active_inventory` (`is_active`, `track_inventory`);

-- =============================================
-- CONFIGURACIÓN: Habilitar edición masiva
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('enable_bulk_edit', '1', 'Habilitar funcionalidad de edición masiva de productos')
ON DUPLICATE KEY UPDATE `setting_value` = '1';

-- Límite de productos para edición masiva
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('bulk_edit_max_products', '100', 'Número máximo de productos editables simultáneamente')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Requerir confirmación para edición masiva
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('bulk_edit_require_confirmation', '1', 'Requerir confirmación antes de aplicar cambios masivos')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =============================================
-- PERMISOS: Agregar permiso para edición masiva
-- =============================================
-- Verificar si existe el permiso, si no, crearlo
INSERT INTO `permissions` (`name`, `description`) 
VALUES ('bulk_edit_products', 'Editar múltiples productos simultáneamente')
ON DUPLICATE KEY UPDATE `description` = 'Editar múltiples productos simultáneamente';

-- Asignar permiso a rol de administrador (asumiendo role_id = 1)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 1, `id` FROM `permissions` WHERE `name` = 'bulk_edit_products'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- Asignar permiso a rol de gerente (asumiendo role_id = 2)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT 2, `id` FROM `permissions` WHERE `name` = 'bulk_edit_products'
ON DUPLICATE KEY UPDATE role_id = role_id;

-- =============================================
-- OPTIMIZACIÓN: Actualizar estadísticas de tablas
-- =============================================
ANALYZE TABLE `products`;
ANALYZE TABLE `categories`;

-- =============================================
-- VERIFICACIÓN: Mostrar productos con inventario
-- =============================================
SELECT 
    'Productos con control de inventario' AS tipo,
    COUNT(*) AS total,
    SUM(CASE WHEN stock_quantity > 0 THEN 1 ELSE 0 END) AS con_stock,
    SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END) AS sin_stock,
    SUM(CASE WHEN stock_quantity <= low_stock_alert AND stock_quantity > 0 THEN 1 ELSE 0 END) AS stock_bajo
FROM `products`
WHERE `is_active` = 1 AND `track_inventory` = 1;

-- =============================================
-- Registrar log de cambios de esta versión
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'migration_v231_log', 
    'Actualización de sistema v2.3.1: Implementación de edición masiva de productos. 
    
NUEVAS CARACTERÍSTICAS:
• Botón "Edición Masiva" en gestión de productos
• Modal con tabla editable de todos los productos activos
• Edición simultánea de: Stock actual, Precio de costo, Precio de venta
• Cálculo automático de ganancias y porcentajes
• Validación de cambios antes de guardar
• Solo actualiza productos modificados
• Tabla de logs para auditoría de cambios masivos
• Índices optimizados para mejor rendimiento

ARCHIVOS NUEVOS:
• admin/get_all_products.php - Endpoint JSON para obtener productos

ARCHIVOS MODIFICADOS:
• admin/products.php
  - Nueva función PHP: bulkUpdateProducts()
  - Nuevo case en switch: bulk_update
  - Botón "Edición Masiva" en header
  - Modal HTML de edición masiva
  - Funciones JavaScript: openBulkEditModal(), updateBulkProfit(), escapeHtml()
  - Estilos CSS para modal de edición masiva
  - Manejo de formulario con validación de cambios

SEGURIDAD:
• Validación de permisos de usuario
• Prepared statements para prevenir SQL injection
• Escape de HTML en frontend
• Validación de datos numéricos (stock, precios)
• Confirmación obligatoria antes de aplicar cambios
• Log de auditoría de todas las ediciones masivas

MEJORAS DE RENDIMIENTO:
• Índices optimizados para consultas frecuentes
• Carga asíncrona de productos vía AJAX
• Solo envía productos modificados al servidor
• Actualización en lote para reducir queries

BASE DE DATOS:
• Nueva tabla: bulk_edit_logs
• Nuevos índices en tabla products
• Nuevo permiso: bulk_edit_products
• Nuevas configuraciones del sistema',
    'Log de cambios versión 2.3.1'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- Verificación: Mostrar versión actualizada
-- =============================================
SELECT 
    'Migración v2.3.1 completada exitosamente' AS status,
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS nueva_version,
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v231_date') AS fecha_migracion,
    (SELECT setting_value FROM settings WHERE setting_key = 'enable_bulk_edit') AS edicion_masiva_habilitada,
    NOW() AS timestamp_completado;

COMMIT;

-- =============================================
-- NOTAS DE LA VERSIÓN 2.3.1
-- =============================================
-- 
-- REQUISITOS PREVIOS:
-- ✓ Versión 2.3.0 instalada
-- ✓ PHP 7.4 o superior
-- ✓ MySQL 5.7+ o MariaDB 10.2+
-- ✓ Extensión PDO habilitada
-- ✓ JavaScript habilitado en navegador
-- 
-- ARCHIVOS A DESPLEGAR:
-- 1. admin/products.php (reemplazar)
-- 2. admin/get_all_products.php (nuevo archivo)
-- 3. Ejecutar este script SQL: v2.3.1.sql
-- 
-- CARACTERÍSTICAS DE EDICIÓN MASIVA:
-- ✓ Edita hasta 100 productos simultáneamente (configurable)
-- ✓ Campos editables:
--   - Stock actual (solo productos con inventario)
--   - Precio de costo
--   - Precio de venta
-- ✓ Cálculo automático de ganancia y porcentaje
-- ✓ Indicadores visuales (verde/rojo según ganancia)
-- ✓ Validación de cambios antes de guardar
-- ✓ Confirmación obligatoria
-- ✓ Solo actualiza valores modificados
-- ✓ Tabla scrolleable para muchos productos
-- ✓ Responsive design
-- 
-- PERMISOS NECESARIOS:
-- - products (ver y gestionar productos)
-- - bulk_edit_products (edición masiva)
-- 
-- PRUEBAS RECOMENDADAS POST-INSTALACIÓN:
-- 1. Verificar que aparece el botón "Edición Masiva"
-- 2. Abrir modal y verificar que carga todos los productos
-- 3. Modificar algunos precios y stock
-- 4. Verificar cálculo de ganancias en tiempo real
-- 5. Guardar cambios y verificar actualización en BD
-- 6. Revisar que solo se actualizaron productos modificados
-- 7. Verificar logs en tabla bulk_edit_logs
-- 8. Probar con diferentes roles de usuario
-- 
-- CONFIGURACIONES DEL SISTEMA:
-- enable_bulk_edit = 1 (activar/desactivar funcionalidad)
-- bulk_edit_max_products = 100 (límite de productos)
-- bulk_edit_require_confirmation = 1 (requerir confirmación)
-- 
-- MONITOREO Y AUDITORÍA:
-- SELECT * FROM bulk_edit_logs ORDER BY created_at DESC LIMIT 10;
-- 
-- ROLLBACK (si es necesario):
-- UPDATE settings SET setting_value = '2.3.0' 
-- WHERE setting_key = 'current_system_version';
-- DROP TABLE IF EXISTS bulk_edit_logs;
-- DELETE FROM permissions WHERE name = 'bulk_edit_products';
-- 
-- SOLUCIÓN DE PROBLEMAS:
-- 
-- Problema: No aparece el botón "Edición Masiva"
-- Solución: Verificar permisos del usuario y que products.php esté actualizado
-- 
-- Problema: Modal no carga productos
-- Solución: Verificar que get_all_products.php existe y tiene permisos de ejecución
-- 
-- Problema: Error al guardar cambios
-- Solución: Revisar logs PHP, verificar función bulkUpdateProducts() en products.php
-- 
-- Problema: Ganancias no se calculan
-- Solución: Verificar que JavaScript esté habilitado y función updateBulkProfit() cargada
-- 
-- MEJORAS FUTURAS SUGERIDAS:
-- • Exportar cambios masivos a CSV
-- • Importar cambios desde Excel
-- • Deshacer última edición masiva
-- • Previsualización de cambios antes de aplicar
-- • Filtros por categoría en modal
-- • Edición de más campos (descripción, tiempo preparación)
-- • Historial de cambios por producto
-- • Notificaciones push al completar edición masiva
-- 
-- =============================================