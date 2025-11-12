-- ============================================
-- ACTUALIZACIÓN DE PERMISOS PARA KARDEX Y WHATSAPP
-- Fecha: 2025-11-12
-- Versión: 2.3.5
-- ============================================

-- PASO 1: Actualizar permisos del rol ADMINISTRADOR
-- Tiene acceso completo a todo el sistema
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'all',
    'orders',
    'online_orders',
    'products',
    'users',
    'reports',
    'tables',
    'kitchen',
    'delivery',
    'kardex',
    'whatsapp'
)
WHERE `name` = 'administrador';

-- PASO 2: Actualizar permisos del rol GERENTE
-- Gestión completa excepto configuración de sistema
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'orders',
    'online_orders',
    'products',
    'users',
    'reports',
    'tables',
    'kitchen',
    'delivery',
    'kardex',
    'whatsapp'
),
`description` = 'Gestión completa del restaurante excepto configuración del sistema'
WHERE `name` = 'gerente';

-- PASO 3: Actualizar permisos del rol MOSTRADOR
-- Gestión de órdenes, productos y delivery
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'orders',
    'online_orders',
    'products',
    'tables',
    'kitchen',
    'delivery',
    'kardex',
    'whatsapp'
),
`description` = 'Gestión de órdenes, productos, mesas y delivery'
WHERE `name` = 'mostrador';

-- PASO 4: Actualizar permisos del rol MESERO
-- Solo gestión de mesas y pedidos
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'orders',
    'tables'
),
`description` = 'Gestión de mesas y pedidos de clientes'
WHERE `name` = 'mesero';

-- PASO 5: Actualizar permisos del rol COCINA
-- Visualización de pedidos y acceso a Kardex para verificar ingredientes
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'kitchen',
    'online_orders',
    'kardex'
),
`description` = 'Visualización y actualización de pedidos en cocina'
WHERE `name` = 'cocina';

-- PASO 6: Actualizar permisos del rol DELIVERY
-- Solo gestión de entregas
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'delivery'
),
`description` = 'Gestión de entregas a domicilio'
WHERE `name` = 'delivery';

-- PASO 7: Crear rol INVENTARIO (opcional)
-- Rol específico para control de inventario
INSERT INTO `roles` (`name`, `description`, `permissions`) 
VALUES (
    'inventario',
    'Control exclusivo de inventario y stock',
    JSON_ARRAY('products', 'kardex', 'reports')
)
ON DUPLICATE KEY UPDATE 
    `description` = VALUES(`description`),
    `permissions` = VALUES(`permissions`);

-- PASO 8: Crear rol ATENCIÓN AL CLIENTE (opcional)
-- Rol específico para WhatsApp y pedidos online
INSERT INTO `roles` (`name`, `description`, `permissions`) 
VALUES (
    'atencion_cliente',
    'Atención al cliente vía WhatsApp y pedidos online',
    JSON_ARRAY('online_orders', 'whatsapp', 'orders')
)
ON DUPLICATE KEY UPDATE 
    `description` = VALUES(`description`),
    `permissions` = VALUES(`permissions`);

-- ============================================
-- VERIFICACIÓN DE PERMISOS
-- ============================================

-- Ver todos los roles y sus permisos actualizados
SELECT 
    id,
    name as 'Rol',
    description as 'Descripción',
    JSON_EXTRACT(permissions, '$') as 'Permisos',
    created_at as 'Creado',
    updated_at as 'Actualizado'
FROM `roles`
ORDER BY 
    CASE name
        WHEN 'administrador' THEN 1
        WHEN 'gerente' THEN 2
        WHEN 'mostrador' THEN 3
        WHEN 'mesero' THEN 4
        WHEN 'cocina' THEN 5
        WHEN 'delivery' THEN 6
        WHEN 'inventario' THEN 7
        WHEN 'atencion_cliente' THEN 8
        ELSE 9
    END;

-- ============================================
-- TABLA DE RESUMEN DE PERMISOS
-- ============================================

/*
╔════════════════════╦═══════╦═════════╦════════════╦═════════╦══════════╦═════════╦═════════╦══════════╦═════════╦══════════╗
║ ROL                ║  ALL  ║ ORDERS  ║  ONLINE    ║ PRODUCTS║  USERS   ║ REPORTS ║ TABLES  ║ KITCHEN  ║DELIVERY ║  KARDEX  ║WHATSAPP ║
╠════════════════════╬═══════╬═════════╬════════════╬═════════╬══════════╬═════════╬═════════╬══════════╬═════════╬══════════╣
║ administrador      ║   ✓   ║    ✓    ║     ✓      ║    ✓    ║    ✓     ║    ✓    ║    ✓    ║    ✓     ║    ✓    ║    ✓     ║    ✓    ║
║ gerente            ║   ✗   ║    ✓    ║     ✓      ║    ✓    ║    ✓     ║    ✓    ║    ✓    ║    ✓     ║    ✓    ║    ✓     ║    ✓    ║
║ mostrador          ║   ✗   ║    ✓    ║     ✓      ║    ✓    ║    ✗     ║    ✗    ║    ✓    ║    ✓     ║    ✓    ║    ✓     ║    ✓    ║
║ mesero             ║   ✗   ║    ✓    ║     ✗      ║    ✗    ║    ✗     ║    ✗    ║    ✓    ║    ✗     ║    ✗    ║    ✗     ║    ✗    ║
║ cocina             ║   ✗   ║    ✗    ║     ✓      ║    ✗    ║    ✗     ║    ✗    ║    ✗    ║    ✓     ║    ✗    ║    ✓     ║    ✗    ║
║ delivery           ║   ✗   ║    ✗    ║     ✗      ║    ✗    ║    ✗     ║    ✗    ║    ✗    ║    ✗     ║    ✓    ║    ✗     ║    ✗    ║
║ inventario         ║   ✗   ║    ✗    ║     ✗      ║    ✓    ║    ✗     ║    ✓    ║    ✗    ║    ✗     ║    ✗    ║    ✓     ║    ✗    ║
║ atencion_cliente   ║   ✗   ║    ✓    ║     ✓      ║    ✗    ║    ✗     ║    ✗    ║    ✗    ║    ✗     ║    ✗    ║    ✗     ║    ✓    ║
╚════════════════════╩═══════╩═════════╩════════════╩═════════╩══════════╩═════════╩═════════╩══════════╩═════════╩══════════╩═════════╝

PERMISOS:
- all: Acceso completo al sistema (solo administrador)
- orders: Gestión de órdenes tradicionales
- online_orders: Gestión de pedidos online
- products: Gestión de productos y menú
- users: Gestión de usuarios del sistema
- reports: Acceso a reportes y estadísticas
- tables: Gestión de mesas
- kitchen: Visualización y gestión de cocina
- delivery: Gestión de entregas
- kardex: Control de inventario (nuevo)
- whatsapp: Atención vía WhatsApp (nuevo)
*/

-- ============================================
-- REGISTRO DE CAMBIOS
-- ============================================

-- Insertar en log de migraciones
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'permissions_update_date',
    NOW(),
    'Fecha de actualización de permisos para Kardex y WhatsApp'
),
(
    'permissions_kardex_enabled',
    '1',
    'Permisos de Kardex habilitados en el sistema'
),
(
    'permissions_whatsapp_enabled',
    '1',
    'Permisos de WhatsApp habilitados en el sistema'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- ============================================
-- LIMPIEZA Y OPTIMIZACIÓN
-- ============================================

-- Optimizar tabla de roles
OPTIMIZE TABLE `roles`;

-- ============================================
-- CONSULTAS ÚTILES PARA VERIFICACIÓN
-- ============================================

-- Ver usuarios y sus permisos actuales
SELECT 
    u.id,
    u.username as 'Usuario',
    u.full_name as 'Nombre Completo',
    r.name as 'Rol',
    r.permissions as 'Permisos',
    u.is_active as 'Activo',
    u.last_login as 'Último Ingreso'
FROM users u
LEFT JOIN roles r ON u.role_id = r.id
ORDER BY r.name, u.username;

-- Ver cuántos usuarios tienen acceso a Kardex
SELECT 
    r.name as 'Rol',
    COUNT(u.id) as 'Cantidad de Usuarios',
    CASE 
        WHEN JSON_CONTAINS(r.permissions, '"kardex"') THEN '✓ Tiene acceso'
        WHEN JSON_CONTAINS(r.permissions, '"all"') THEN '✓ Acceso total'
        ELSE '✗ Sin acceso'
    END as 'Acceso a Kardex'
FROM roles r
LEFT JOIN users u ON u.role_id = r.id
GROUP BY r.id, r.name, r.permissions
ORDER BY COUNT(u.id) DESC;

-- Ver cuántos usuarios tienen acceso a WhatsApp
SELECT 
    r.name as 'Rol',
    COUNT(u.id) as 'Cantidad de Usuarios',
    CASE 
        WHEN JSON_CONTAINS(r.permissions, '"whatsapp"') THEN '✓ Tiene acceso'
        WHEN JSON_CONTAINS(r.permissions, '"all"') THEN '✓ Acceso total'
        ELSE '✗ Sin acceso'
    END as 'Acceso a WhatsApp'
FROM roles r
LEFT JOIN users u ON u.role_id = r.id
GROUP BY r.id, r.name, r.permissions
ORDER BY COUNT(u.id) DESC;

-- ============================================
-- FINALIZACIÓN
-- ============================================

SELECT 
    '✓ Permisos actualizados exitosamente' as 'Estado',
    COUNT(*) as 'Roles Actualizados',
    NOW() as 'Fecha de Actualización'
FROM roles;

-- Mensaje de confirmación
SELECT 
    'Actualización de permisos completada' as 'Mensaje',
    'Kardex y WhatsApp ahora tienen permisos específicos' as 'Detalles',
    'Verificar tabla de roles para confirmar cambios' as 'Acción Siguiente';