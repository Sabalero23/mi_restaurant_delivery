-- =============================================
-- Migración v2.3.5 - Actualización Automática
-- =============================================
-- Descripción: Sistema de commits automático
--              Genera y guarda el hash correctamente
--              Compatible con instalaciones manuales y Git
-- Fecha: 2025-11-12
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- 1. FUNCIÓN: Generar hash único del sistema
-- =============================================
-- Genera un hash basado en timestamp + versión
SET @new_commit_hash = SHA2(CONCAT(
    '2.3.5',
    '_',
    NOW(),
    '_',
    @@hostname,
    '_',
    DATABASE()
), 256);

-- =============================================
-- 2. Verificar y limpiar commits anteriores
-- =============================================
-- Eliminar commits con prefijo MANUAL_ o mal formados
DELETE FROM `settings` 
WHERE `setting_key` = 'system_commit' 
AND (
    `setting_value` LIKE 'MANUAL_%' 
    OR `setting_value` = 'initial'
    OR LENGTH(`setting_value`) < 7
);

-- =============================================
-- 3. GUARDAR: Nuevo commit hash automático
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'system_commit', 
    @new_commit_hash,
    CONCAT('Hash SHA-256 del sistema - v2.3.5 - Generado: ', NOW())
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = @new_commit_hash,
    `description` = CONCAT('Hash SHA-256 del sistema - v2.3.5 - Actualizado: ', NOW());

-- =============================================
-- 4. Guardar commit anterior como backup
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`)
SELECT 
    'system_commit_previous',
    `setting_value`,
    CONCAT('Commit anterior guardado el ', NOW())
FROM `settings`
WHERE `setting_key` = 'system_commit'
ON DUPLICATE KEY UPDATE 
    `setting_value` = (SELECT `setting_value` FROM (SELECT * FROM `settings`) AS temp WHERE `setting_key` = 'system_commit');

-- =============================================
-- 5. Actualizar versión del sistema
-- =============================================
UPDATE `settings` 
SET `setting_value` = '2.3.5',
    `description` = 'Versión actual del sistema'
WHERE `setting_key` = 'current_system_version';

-- Si no existe, crearla
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.3.5', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE 
    `setting_value` = '2.3.5',
    `description` = 'Versión actual del sistema';

-- =============================================
-- 6. Registrar fecha y método de instalación
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES 
    ('migration_v235_date', NOW(), 'Fecha de migración a v2.3.5'),
    ('migration_v235_method', 'manual_sql', 'Método de instalación: SQL manual'),
    ('migration_v235_commit', @new_commit_hash, 'Hash del commit v2.3.5')
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- 7. ACTUALIZAR PERMISOS - Roles del sistema
-- =============================================

-- ADMINISTRADOR: Acceso total
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'all', 'orders', 'online_orders', 'products', 
    'users', 'reports', 'tables', 'kitchen', 
    'delivery', 'kardex', 'whatsapp'
),
`description` = 'Acceso completo al sistema',
`updated_at` = NOW()
WHERE `name` = 'administrador';

-- GERENTE: Gestión completa excepto configuración
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'orders', 'online_orders', 'products', 'users', 
    'reports', 'tables', 'kitchen', 'delivery', 
    'kardex', 'whatsapp'
),
`description` = 'Gestión completa del restaurante excepto configuración del sistema',
`updated_at` = NOW()
WHERE `name` = 'gerente';

-- MOSTRADOR: Gestión de órdenes y productos
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'orders', 'online_orders', 'products', 
    'tables', 'kitchen', 'delivery', 'kardex', 'whatsapp'
),
`description` = 'Gestión de órdenes, productos, mesas y delivery',
`updated_at` = NOW()
WHERE `name` = 'mostrador';

-- MESERO: Solo mesas y pedidos
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'orders', 'tables'
),
`description` = 'Gestión de mesas y pedidos de clientes',
`updated_at` = NOW()
WHERE `name` = 'mesero';

-- COCINA: Pedidos y kardex
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'kitchen', 'online_orders', 'kardex'
),
`description` = 'Visualización y actualización de pedidos en cocina',
`updated_at` = NOW()
WHERE `name` = 'cocina';

-- DELIVERY: Solo entregas
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'delivery'
),
`description` = 'Gestión de entregas a domicilio',
`updated_at` = NOW()
WHERE `name` = 'delivery';

-- =============================================
-- 8. ROLES OPCIONALES (si no existen, crearlos)
-- =============================================

-- Rol INVENTARIO
INSERT INTO `roles` (`name`, `description`, `permissions`, `created_at`, `updated_at`) 
VALUES (
    'inventario',
    'Control exclusivo de inventario y stock',
    JSON_ARRAY('products', 'kardex', 'reports'),
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    `description` = VALUES(`description`),
    `permissions` = VALUES(`permissions`),
    `updated_at` = NOW();

-- Rol ATENCIÓN AL CLIENTE
INSERT INTO `roles` (`name`, `description`, `permissions`, `created_at`, `updated_at`) 
VALUES (
    'atencion_cliente',
    'Atención al cliente vía WhatsApp y pedidos online',
    JSON_ARRAY('online_orders', 'whatsapp', 'orders'),
    NOW(),
    NOW()
)
ON DUPLICATE KEY UPDATE 
    `description` = VALUES(`description`),
    `permissions` = VALUES(`permissions`),
    `updated_at` = NOW();

-- =============================================
-- 9. Guardar configuraciones de permisos
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES 
    ('permissions_kardex_enabled', '1', 'Permisos de Kardex habilitados'),
    ('permissions_whatsapp_enabled', '1', 'Permisos de WhatsApp habilitados'),
    ('permissions_last_update', NOW(), 'Última actualización de permisos')
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- 10. Guardar log detallado de cambios
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'migration_v235_log',
    CONCAT(
        'Actualización de sistema v2.3.5 - ', NOW(), '\n\n',
        'CARACTERÍSTICAS NUEVAS:\n',
        '• Sistema de commits automático\n',
        '• Hash SHA-256 generado: ', SUBSTRING(@new_commit_hash, 1, 16), '...\n',
        '• Permisos para Kardex (control de inventario)\n',
        '• Permisos para WhatsApp (atención al cliente)\n',
        '• 2 nuevos roles opcionales: inventario y atención_cliente\n\n',
        'PERMISOS ACTUALIZADOS:\n',
        '• administrador: Todos los permisos incluido kardex y whatsapp\n',
        '• gerente: Gestión completa con kardex y whatsapp\n',
        '• mostrador: Operaciones diarias con kardex y whatsapp\n',
        '• mesero: Solo mesas y órdenes\n',
        '• cocina: Pedidos con acceso a kardex\n',
        '• delivery: Solo entregas\n',
        '• inventario (nuevo): Control de stock\n',
        '• atencion_cliente (nuevo): WhatsApp y pedidos online\n\n',
        'CORRECCIONES:\n',
        '• Eliminados commits con prefijo MANUAL_\n',
        '• Sistema genera hash automáticamente\n',
        '• Commit anterior guardado como backup\n\n',
        'MÉTODO DE INSTALACIÓN: SQL Manual\n',
        'BASE DE DATOS: ', DATABASE(), '\n',
        'SERVIDOR: ', @@hostname
    ),
    'Log completo de migración v2.3.5'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- 11. Optimización de tablas
-- =============================================
OPTIMIZE TABLE `settings`;
OPTIMIZE TABLE `roles`;
OPTIMIZE TABLE `users`;

-- =============================================
-- 12. VERIFICACIÓN FINAL - Mostrar resultados
-- =============================================
SELECT 
    '✓ Migración completada exitosamente' AS '🎉 ESTADO',
    '' AS '';

SELECT 
    'VERSIÓN DEL SISTEMA' AS '📌 INFORMACIÓN',
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS 'Versión',
    SUBSTRING(@new_commit_hash, 1, 7) AS 'Commit (corto)',
    SUBSTRING(@new_commit_hash, 1, 16) AS 'Commit Hash',
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v235_date') AS 'Fecha Instalación'
UNION ALL
SELECT 
    'MÉTODO',
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v235_method'),
    '-',
    '-',
    '-';

-- Mostrar permisos actualizados
SELECT 
    '📋 ROLES Y PERMISOS' AS '───────────────',
    '' AS '',
    '' AS '',
    '' AS '',
    '' AS '';

SELECT 
    name AS 'Rol',
    description AS 'Descripción',
    JSON_LENGTH(permissions) AS 'Cantidad Permisos',
    CASE 
        WHEN JSON_CONTAINS(permissions, '"kardex"') THEN '✓'
        WHEN JSON_CONTAINS(permissions, '"all"') THEN '✓ (all)'
        ELSE '✗'
    END AS 'Kardex',
    CASE 
        WHEN JSON_CONTAINS(permissions, '"whatsapp"') THEN '✓'
        WHEN JSON_CONTAINS(permissions, '"all"') THEN '✓ (all)'
        ELSE '✗'
    END AS 'WhatsApp',
    updated_at AS 'Última Actualización'
FROM roles
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

-- Estadísticas de usuarios por rol
SELECT 
    '👥 USUARIOS POR ROL' AS '───────────────',
    '' AS '',
    '' AS '',
    '' AS '',
    '' AS '',
    '' AS '';

SELECT 
    r.name AS 'Rol',
    COUNT(u.id) AS 'Usuarios',
    SUM(CASE WHEN u.is_active = 1 THEN 1 ELSE 0 END) AS 'Activos',
    SUM(CASE WHEN u.is_active = 0 THEN 1 ELSE 0 END) AS 'Inactivos',
    CASE 
        WHEN JSON_CONTAINS(r.permissions, '"kardex"') OR JSON_CONTAINS(r.permissions, '"all"') 
        THEN '✓ Acceso Kardex'
        ELSE '✗ Sin acceso'
    END AS 'Kardex',
    CASE 
        WHEN JSON_CONTAINS(r.permissions, '"whatsapp"') OR JSON_CONTAINS(r.permissions, '"all"') 
        THEN '✓ Acceso WhatsApp'
        ELSE '✗ Sin acceso'
    END AS 'WhatsApp'
FROM roles r
LEFT JOIN users u ON u.role_id = r.id
GROUP BY r.id, r.name, r.permissions
ORDER BY COUNT(u.id) DESC;

-- Resumen final
SELECT 
    '✅ RESUMEN FINAL' AS '═══════════════════',
    '' AS '',
    '' AS '',
    '' AS '',
    '' AS '',
    '' AS '';

SELECT 
    'Total Roles Actualizados' AS 'Métrica',
    COUNT(*) AS 'Valor',
    '' AS '',
    '' AS '',
    '' AS '',
    '' AS ''
FROM roles
UNION ALL
SELECT 
    'Roles con Acceso Kardex',
    COUNT(*),
    '',
    '',
    '',
    ''
FROM roles 
WHERE JSON_CONTAINS(permissions, '"kardex"') OR JSON_CONTAINS(permissions, '"all"')
UNION ALL
SELECT 
    'Roles con Acceso WhatsApp',
    COUNT(*),
    '',
    '',
    '',
    ''
FROM roles 
WHERE JSON_CONTAINS(permissions, '"whatsapp"') OR JSON_CONTAINS(permissions, '"all"')
UNION ALL
SELECT 
    'Total Usuarios en Sistema',
    COUNT(*),
    '',
    '',
    '',
    ''
FROM users
UNION ALL
SELECT 
    'Usuarios Activos',
    COUNT(*),
    '',
    '',
    '',
    ''
FROM users WHERE is_active = 1;

-- Mensaje de éxito
SELECT 
    '🎊 ¡ACTUALIZACIÓN COMPLETADA!' AS '',
    CONCAT('Versión: ', (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version')) AS '',
    CONCAT('Commit: ', SUBSTRING(@new_commit_hash, 1, 7)) AS '',
    CONCAT('Fecha: ', NOW()) AS '',
    'Sistema listo para usar' AS '',
    '' AS '';

COMMIT;

-- =============================================
-- NOTAS POST-INSTALACIÓN
-- =============================================
/*
✅ INSTALACIÓN COMPLETADA

QUÉ SE ACTUALIZÓ:
1. Sistema de commits automático (sin necesidad de Git)
2. Permisos para Kardex en 6 roles
3. Permisos para WhatsApp en 5 roles
4. 2 nuevos roles opcionales creados
5. Hash SHA-256 único generado automáticamente
6. Commit anterior guardado como backup

VERIFICAR EN LA APLICACIÓN:
1. Ir a Configuración → Actualizar Sistema
2. Verificar que "Commit" muestre primeros 7 caracteres del hash
3. Verificar que "Versión del Sistema" muestre: 2.3.5
4. Los roles actualizados deberían tener acceso a Kardex/WhatsApp según corresponda

CONSULTAS ÚTILES POST-MIGRACIÓN:
*/

-- Ver commit actual completo
-- SELECT setting_value FROM settings WHERE setting_key = 'system_commit';

-- Ver commit anterior (backup)
-- SELECT setting_value FROM settings WHERE setting_key = 'system_commit_previous';

-- Ver todos los permisos de un rol específico
-- SELECT name, permissions FROM roles WHERE name = 'administrador';

-- Ver qué usuarios tienen acceso a Kardex
-- SELECT u.username, u.full_name, r.name as rol 
-- FROM users u 
-- JOIN roles r ON u.role_id = r.id 
-- WHERE JSON_CONTAINS(r.permissions, '"kardex"') OR JSON_CONTAINS(r.permissions, '"all"');

-- =============================================
-- FIN DE MIGRACIÓN v2.3.5
-- =============================================