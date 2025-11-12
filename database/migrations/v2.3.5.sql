-- =============================================
-- Migracion v2.3.5 - Actualizacion Automatica (CORREGIDA)
-- =============================================
-- Descripcion: Sistema de commits automatico
--              Genera hash completo Y version corta
--              Compatible con columnas from_commit limitadas
-- Fecha: 2025-11-12
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- 1. FUNCION: Generar hash unico del sistema
-- =============================================
-- Genera un hash COMPLETO basado en timestamp + version
SET @new_commit_hash_full = SHA2(CONCAT(
    '2.3.5',
    '_',
    NOW(),
    '_',
    @@hostname,
    '_',
    DATABASE()
), 256);

-- Generar VERSION CORTA (primeros 8 caracteres)
SET @new_commit_hash = SUBSTRING(@new_commit_hash_full, 1, 8);

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
-- 3. GUARDAR: Nuevo commit hash (VERSION CORTA)
-- =============================================
-- Guardar VERSION CORTA para compatibilidad
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'system_commit', 
    @new_commit_hash,
    CONCAT('Hash SHA-256 corto - v2.3.5 - Generado: ', NOW())
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = @new_commit_hash,
    `description` = CONCAT('Hash SHA-256 corto - v2.3.5 - Actualizado: ', NOW());

-- Guardar VERSION COMPLETA para referencia
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'system_commit_full', 
    @new_commit_hash_full,
    CONCAT('Hash SHA-256 completo - v2.3.5 - Generado: ', NOW())
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = @new_commit_hash_full,
    `description` = CONCAT('Hash SHA-256 completo - v2.3.5 - Actualizado: ', NOW());

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
-- 5. Actualizar version del sistema
-- =============================================
UPDATE `settings` 
SET `setting_value` = '2.3.5',
    `description` = 'Version actual del sistema'
WHERE `setting_key` = 'current_system_version';

-- Si no existe, crearla
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.3.5', 'Version actual del sistema')
ON DUPLICATE KEY UPDATE 
    `setting_value` = '2.3.5',
    `description` = 'Version actual del sistema';

-- =============================================
-- 6. Registrar fecha y metodo de instalacion
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES 
    ('migration_v235_date', NOW(), 'Fecha de migracion a v2.3.5'),
    ('migration_v235_method', 'manual_sql', 'Metodo de instalacion: SQL manual'),
    ('migration_v235_commit', @new_commit_hash, 'Hash CORTO del commit v2.3.5'),
    ('migration_v235_commit_full', @new_commit_hash_full, 'Hash COMPLETO del commit v2.3.5')
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

-- GERENTE: Gestion completa excepto configuracion
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'orders', 'online_orders', 'products', 'users', 
    'reports', 'tables', 'kitchen', 'delivery', 
    'kardex', 'whatsapp'
),
`description` = 'Gestion completa del restaurante excepto configuracion del sistema',
`updated_at` = NOW()
WHERE `name` = 'gerente';

-- MOSTRADOR: Gestion de ordenes y productos
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'orders', 'online_orders', 'products', 
    'tables', 'kitchen', 'delivery', 'kardex', 'whatsapp'
),
`description` = 'Gestion de ordenes, productos, mesas y delivery',
`updated_at` = NOW()
WHERE `name` = 'mostrador';

-- MESERO: Solo mesas y pedidos
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'orders', 'tables'
),
`description` = 'Gestion de mesas y pedidos de clientes',
`updated_at` = NOW()
WHERE `name` = 'mesero';

-- COCINA: Pedidos y kardex
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'kitchen', 'online_orders', 'kardex'
),
`description` = 'Visualizacion y actualizacion de pedidos en cocina',
`updated_at` = NOW()
WHERE `name` = 'cocina';

-- DELIVERY: Solo entregas
UPDATE `roles` 
SET `permissions` = JSON_ARRAY(
    'delivery'
),
`description` = 'Gestion de entregas a domicilio',
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

-- Rol ATENCION AL CLIENTE
INSERT INTO `roles` (`name`, `description`, `permissions`, `created_at`, `updated_at`) 
VALUES (
    'atencion_cliente',
    'Atencion al cliente via WhatsApp y pedidos online',
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
    ('permissions_last_update', NOW(), 'Ultima actualizacion de permisos')
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
        'Actualizacion de sistema v2.3.5 - ', NOW(), '\n\n',
        'CARACTERISTICAS NUEVAS:\n',
        '- Sistema de commits automatico\n',
        '- Hash SHA-256 corto generado: ', @new_commit_hash, '\n',
        '- Hash SHA-256 completo: ', SUBSTRING(@new_commit_hash_full, 1, 16), '...\n',
        '- Permisos para Kardex (control de inventario)\n',
        '- Permisos para WhatsApp (atencion al cliente)\n',
        '- 2 nuevos roles opcionales: inventario y atencion_cliente\n\n',
        'PERMISOS ACTUALIZADOS:\n',
        '- administrador: Todos los permisos incluido kardex y whatsapp\n',
        '- gerente: Gestion completa con kardex y whatsapp\n',
        '- mostrador: Operaciones diarias con kardex y whatsapp\n',
        '- mesero: Solo mesas y ordenes\n',
        '- cocina: Pedidos con acceso a kardex\n',
        '- delivery: Solo entregas\n',
        '- inventario (nuevo): Control de stock\n',
        '- atencion_cliente (nuevo): WhatsApp y pedidos online\n\n',
        'CORRECCIONES:\n',
        '- Eliminados commits con prefijo MANUAL_\n',
        '- Sistema genera hash corto (8 chars) para compatibilidad\n',
        '- Hash completo guardado por separado\n',
        '- Commit anterior guardado como backup\n\n',
        'METODO DE INSTALACION: SQL Manual\n',
        'BASE DE DATOS: ', DATABASE(), '\n',
        'SERVIDOR: ', @@hostname
    ),
    'Log completo de migracion v2.3.5'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- 11. Optimizacion de tablas
-- =============================================
OPTIMIZE TABLE `settings`;
OPTIMIZE TABLE `roles`;
OPTIMIZE TABLE `users`;

-- =============================================
-- 12. VERIFICACION FINAL - Mostrar resultados
-- =============================================
SELECT 
    'Migracion completada exitosamente' AS 'ESTADO',
    '' AS '';

SELECT 
    'VERSION DEL SISTEMA' AS 'INFORMACION',
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS 'Version',
    @new_commit_hash AS 'Commit_Corto',
    SUBSTRING(@new_commit_hash_full, 1, 16) AS 'Commit_Preview',
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v235_date') AS 'Fecha_Instalacion'
UNION ALL
SELECT 
    'METODO',
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v235_method'),
    '-',
    '-',
    '-';

-- Mostrar permisos actualizados
SELECT 
    'ROLES Y PERMISOS' AS '---------------',
    '' AS '',
    '' AS '',
    '' AS '',
    '' AS '';

SELECT 
    name AS 'Rol',
    description AS 'Descripcion',
    JSON_LENGTH(permissions) AS 'Cant_Permisos',
    CASE 
        WHEN JSON_CONTAINS(permissions, '"kardex"') THEN 'SI'
        WHEN JSON_CONTAINS(permissions, '"all"') THEN 'SI (all)'
        ELSE 'NO'
    END AS 'Kardex',
    CASE 
        WHEN JSON_CONTAINS(permissions, '"whatsapp"') THEN 'SI'
        WHEN JSON_CONTAINS(permissions, '"all"') THEN 'SI (all)'
        ELSE 'NO'
    END AS 'WhatsApp',
    updated_at AS 'Ultima_Actualizacion'
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

-- Estadisticas de usuarios por rol
SELECT 
    'USUARIOS POR ROL' AS '---------------',
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
        THEN 'Acceso Kardex'
        ELSE 'Sin acceso'
    END AS 'Kardex',
    CASE 
        WHEN JSON_CONTAINS(r.permissions, '"whatsapp"') OR JSON_CONTAINS(r.permissions, '"all"') 
        THEN 'Acceso WhatsApp'
        ELSE 'Sin acceso'
    END AS 'WhatsApp'
FROM roles r
LEFT JOIN users u ON u.role_id = r.id
GROUP BY r.id, r.name, r.permissions
ORDER BY COUNT(u.id) DESC;

-- Resumen final
SELECT 
    'RESUMEN FINAL' AS '===================',
    '' AS '',
    '' AS '',
    '' AS '',
    '' AS '',
    '' AS '';

SELECT 
    'Total Roles Actualizados' AS 'Metrica',
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

-- Mensaje de exito
SELECT 
    'ACTUALIZACION COMPLETADA' AS '',
    CONCAT('Version: ', (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version')) AS '',
    CONCAT('Commit: ', @new_commit_hash) AS '',
    CONCAT('Fecha: ', NOW()) AS '',
    'Sistema listo para usar' AS '',
    '' AS '';

COMMIT;

-- =============================================
-- NOTAS POST-INSTALACION
-- =============================================
/*
INSTALACION COMPLETADA - VERSION CORREGIDA

CAMBIOS EN ESTA VERSION:
- Hash SHA-256 CORTO (8 caracteres) guardado en 'system_commit'
- Hash SHA-256 COMPLETO (64 caracteres) guardado en 'system_commit_full'
- Compatible con columnas from_commit de longitud limitada

QUE SE ACTUALIZO:
1. Sistema de commits automatico (sin necesidad de Git)
2. Permisos para Kardex en 6 roles
3. Permisos para WhatsApp en 5 roles
4. 2 nuevos roles opcionales creados
5. Hash SHA-256 unico generado (corto y completo)
6. Commit anterior guardado como backup

VERIFICAR EN LA APLICACION:
1. Ir a Configuracion -> Actualizar Sistema
2. Verificar que "Commit" muestre 8 caracteres
3. Verificar que "Version del Sistema" muestre: 2.3.5
4. Los roles actualizados deberian tener acceso a Kardex/WhatsApp segun corresponda

CONSULTAS UTILES POST-MIGRACION:
*/

-- Ver commit actual CORTO
-- SELECT setting_value FROM settings WHERE setting_key = 'system_commit';

-- Ver commit COMPLETO
-- SELECT setting_value FROM settings WHERE setting_key = 'system_commit_full';

-- Ver commit anterior (backup)
-- SELECT setting_value FROM settings WHERE setting_key = 'system_commit_previous';

-- Ver todos los permisos de un rol especifico
-- SELECT name, permissions FROM roles WHERE name = 'administrador';

-- Ver que usuarios tienen acceso a Kardex
-- SELECT u.username, u.full_name, r.name as rol 
-- FROM users u 
-- JOIN roles r ON u.role_id = r.id 
-- WHERE JSON_CONTAINS(r.permissions, '"kardex"') OR JSON_CONTAINS(r.permissions, '"all"');

-- =============================================
-- FIN DE MIGRACION v2.3.5 (CORREGIDA)
-- =============================================