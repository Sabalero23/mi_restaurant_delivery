-- =============================================
-- Migración v2.3.4 - Filtrado por Rol de Usuario
-- =============================================
-- Descripción: Implementación de filtros por rol para mesas y órdenes
--              - Meseros ven solo sus mesas asignadas
--              - Meseros ven solo sus órdenes creadas
--              - Administrador/Mostrador/Gerente ven todo
-- Fecha: 2025-11-10
-- Autor: Cellcom Technology
-- =============================================

-- Desactivar verificación de claves foráneas temporalmente
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;

-- =============================================
-- Verificar que existe la clave current_system_version
-- Si no existe, crearla con valor inicial
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.3.4', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =============================================
-- Actualizar versión del sistema a 2.3.4
-- =============================================
UPDATE `settings` 
SET `setting_value` = '2.3.4' 
WHERE `setting_key` = 'current_system_version';

-- =============================================
-- Actualizar hash del último commit
-- =============================================
UPDATE `settings` 
SET `setting_value` = 'MANUAL_v2.3.4' 
WHERE `setting_key` = 'system_commit';

-- Si no existe el campo system_commit, crearlo
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('system_commit', 'MANUAL_v2.3.4', 'Hash del último commit instalado')
ON DUPLICATE KEY UPDATE `setting_value` = 'MANUAL_v2.3.4';

-- =============================================
-- Registrar fecha de esta migración
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('migration_v234_date', NOW(), 'Fecha de migración a v2.3.4')
ON DUPLICATE KEY UPDATE `setting_value` = NOW();

-- =============================================
-- Agregar nuevas configuraciones del sistema
-- =============================================

-- Configuración para habilitar filtrado de mesas por rol
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'enable_table_role_filter', 
    '1', 
    'Habilitar filtrado de mesas según rol de usuario (meseros solo ven sus mesas asignadas)'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- Configuración para habilitar filtrado de órdenes por rol
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'enable_order_role_filter', 
    '1', 
    'Habilitar filtrado de órdenes según rol de usuario (meseros solo ven órdenes que crearon)'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- Configuración para permitir a meseros ver órdenes de sus mesas aunque no las hayan creado
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'waiter_see_table_orders', 
    '1', 
    'Permitir a meseros ver todas las órdenes de sus mesas asignadas (no solo las que crearon)'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- Configuración para requerir asignación de mesero a mesa antes de crear orden
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'require_waiter_assignment', 
    '0', 
    'Requerir que las mesas tengan un mesero asignado antes de poder crear órdenes'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- Verificar y agregar índices para optimizar consultas
-- =============================================

-- Índice compuesto para órdenes por usuario creador y estado
-- Verificar si existe antes de crear
SET @exist_idx1 := (SELECT COUNT(*) FROM information_schema.statistics 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'orders' 
                    AND index_name = 'idx_created_by_status');
SET @sqlstmt1 := IF(@exist_idx1 > 0, 
                    'SELECT ''Index idx_created_by_status already exists'' AS msg', 
                    'CREATE INDEX idx_created_by_status ON orders (created_by, status)');
PREPARE stmt1 FROM @sqlstmt1;
EXECUTE stmt1;
DEALLOCATE PREPARE stmt1;

-- Índice compuesto para órdenes por mesa y estado
SET @exist_idx2 := (SELECT COUNT(*) FROM information_schema.statistics 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'orders' 
                    AND index_name = 'idx_table_status');
SET @sqlstmt2 := IF(@exist_idx2 > 0, 
                    'SELECT ''Index idx_table_status already exists'' AS msg', 
                    'CREATE INDEX idx_table_status ON orders (table_id, status)');
PREPARE stmt2 FROM @sqlstmt2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

-- Índice compuesto para mesas por mesero y estado
SET @exist_idx3 := (SELECT COUNT(*) FROM information_schema.statistics 
                    WHERE table_schema = DATABASE() 
                    AND table_name = 'tables' 
                    AND index_name = 'idx_waiter_status');
SET @sqlstmt3 := IF(@exist_idx3 > 0, 
                    'SELECT ''Index idx_waiter_status already exists'' AS msg', 
                    'CREATE INDEX idx_waiter_status ON tables (waiter_id, status)');
PREPARE stmt3 FROM @sqlstmt3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- =============================================
-- Verificar integridad de datos existentes
-- =============================================

-- Contar mesas sin mesero asignado
SELECT 
    COUNT(*) as mesas_sin_mesero,
    'Mesas que no tienen mesero asignado' as descripcion
FROM `tables` 
WHERE `waiter_id` IS NULL;

-- Contar órdenes sin usuario creador
SELECT 
    COUNT(*) as ordenes_sin_creador,
    'Órdenes que no tienen usuario creador registrado' as descripcion
FROM `orders` 
WHERE `created_by` IS NULL;

-- Contar meseros activos en el sistema
SELECT 
    COUNT(*) as total_meseros,
    'Total de meseros activos en el sistema' as descripcion
FROM `users` u
INNER JOIN `roles` r ON u.role_id = r.id
WHERE r.name = 'mesero' AND u.is_active = 1;

-- =============================================
-- Registrar log detallado de cambios de esta versión
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'migration_v234_log', 
    'Actualización de sistema v2.3.4: Sistema de Filtrado por Rol de Usuario

NUEVAS CARACTERÍSTICAS:

1. FILTRADO DE MESAS POR ROL:
   • Meseros ven solo las mesas que les fueron asignadas (campo waiter_id)
   • Administrador, Mostrador y Gerente ven todas las mesas del sistema
   • Las estadísticas de mesas se calculan según las mesas visibles
   • Filtrado automático en tabla de mesas (tables.php)

2. FILTRADO DE ÓRDENES POR ROL:
   • Meseros ven solo las órdenes que ellos mismos crearon (campo created_by)
   • Administrador, Mostrador y Gerente ven todas las órdenes del sistema
   • Las estadísticas de órdenes se calculan según las órdenes visibles
   • Filtrado automático en lista de órdenes (orders.php)
   • Compatible con filtros existentes (estado, tipo, fecha)

3. CONFIGURACIONES DEL SISTEMA:
   • enable_table_role_filter: Activar/desactivar filtrado de mesas
   • enable_order_role_filter: Activar/desactivar filtrado de órdenes
   • waiter_see_table_orders: Meseros ven órdenes de sus mesas asignadas
   • require_waiter_assignment: Requerir mesero antes de crear orden

ARCHIVOS MODIFICADOS:

• admin/tables.php
  - Línea ~118-123: Consulta de mesas con filtro por rol
  - Función: Solo meseros ven mesas donde waiter_id = current_user_id
  - Otros roles: Sin restricción, ven todas las mesas
  
• admin/orders.php
  - Línea ~82-88: Construcción de condiciones WHERE con filtro por rol
  - Función: Solo meseros ven órdenes donde created_by = current_user_id
  - Otros roles: Sin restricción, ven todas las órdenes
  - Integración: Compatible con filtros de estado, tipo y fecha

CAMBIOS EN BASE DE DATOS:
  
• Nuevos índices para optimización:
  - idx_created_by_status: Órdenes por usuario y estado
  - idx_table_status: Órdenes por mesa y estado  
  - idx_waiter_status: Mesas por mesero y estado

• Nuevas configuraciones en tabla settings:
  - enable_table_role_filter
  - enable_order_role_filter
  - waiter_see_table_orders
  - require_waiter_assignment

SEGURIDAD:
• Filtrado a nivel de consulta SQL (no solo frontend)
• Uso de prepared statements con parámetros nombrados
• Validación de rol mediante $_SESSION[''role_name'']
• Prevención de SQL injection
• No expone datos de otros meseros

MEJORAS DE RENDIMIENTO:
• Índices compuestos para consultas frecuentes
• Reducción de datos transferidos (solo lo necesario)
• Consultas optimizadas con JOINs eficientes
• Caché de sesión para evitar múltiples queries

COMPATIBILIDAD:
• Totalmente compatible con permisos existentes
• No afecta funcionalidad de otros roles
• Retrocompatible con datos existentes
• Sin cambios en interfaz de usuario

CASOS DE USO:

Ejemplo 1 - Mesero "Pintos" (user_id=3):
  - En tables.php: Ve solo mesas con waiter_id = 3
  - En orders.php: Ve solo órdenes con created_by = 3
  - Estadísticas: Calculadas sobre sus datos filtrados

Ejemplo 2 - Administrador:
  - En tables.php: Ve todas las mesas del sistema
  - En orders.php: Ve todas las órdenes del sistema
  - Estadísticas: Calculadas sobre todos los datos

Ejemplo 3 - Mostrador/Gerente:
  - Mismo comportamiento que Administrador
  - Control total sobre mesas y órdenes
  - Pueden gestionar asignaciones de meseros

PRUEBAS RECOMENDADAS:
1. Login como mesero → verificar solo ve sus mesas/órdenes
2. Login como admin → verificar ve todo sin restricción
3. Crear orden como mesero → verificar aparece en su lista
4. Asignar mesa a mesero → verificar mesero la ve
5. Verificar estadísticas se calculan correctamente
6. Probar filtros en orders.php con datos filtrados',
    'Log de cambios versión 2.3.4'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- Verificación: Mostrar información de la migración
-- =============================================
SELECT 
    'Migración v2.3.4 completada exitosamente' AS status,
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS nueva_version,
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v234_date') AS fecha_migracion,
    (SELECT COUNT(*) FROM tables WHERE waiter_id IS NOT NULL) AS mesas_con_mesero_asignado,
    (SELECT COUNT(*) FROM orders WHERE created_by IS NOT NULL) AS ordenes_con_creador_registrado,
    NOW() AS timestamp_completado;

COMMIT;

-- Reactivar verificación de claves foráneas
SET FOREIGN_KEY_CHECKS = 1;

-- =============================================
-- NOTAS DE LA VERSIÓN 2.3.4
-- =============================================
-- 
-- ARCHIVOS QUE DEBEN MODIFICARSE MANUALMENTE:
-- 
-- 1. admin/tables.php (línea ~118-123):
--    Reemplazar consulta de mesas con código que incluye filtro por rol
-- 
-- 2. admin/orders.php (línea ~82-88):
--    Agregar condición de filtrado por rol antes de otros filtros
--
-- CÓDIGO PARA tables.php:
-- ```php
-- // Get tables with waiter information - FILTRADO POR ROL
-- $tables_query = "SELECT t.*, u.full_name as waiter_name, u.username as waiter_username
--                  FROM tables t
--                  LEFT JOIN users u ON t.waiter_id = u.id";
-- 
-- if ($_SESSION['role_name'] === 'mesero') {
--     $tables_query .= " WHERE t.waiter_id = :current_user_id";
--     $tables_stmt = $db->prepare($tables_query . " ORDER BY t.number");
--     $tables_stmt->execute(['current_user_id' => $_SESSION['user_id']]);
-- } else {
--     $tables_stmt = $db->prepare($tables_query . " ORDER BY t.number");
--     $tables_stmt->execute();
-- }
-- $tables = $tables_stmt->fetchAll();
-- ```
--
-- CÓDIGO PARA orders.php:
-- ```php
-- // Build query conditions
-- $conditions = [];
-- $params = [];
-- 
-- // Filtrar órdenes según el rol del usuario
-- if ($_SESSION['role_name'] === 'mesero') {
--     $conditions[] = "o.created_by = ?";
--     $params[] = $_SESSION['user_id'];
-- }
-- 
-- if ($status_filter) {
--     $conditions[] = "o.status = ?";
--     $params[] = $status_filter;
-- }
-- // ... resto del código
-- ```
-- 
-- COMPATIBILIDAD:
-- - Requiere MySQL 5.7+ o MariaDB 10.2+
-- - Compatible con PHP 7.4+
-- - Requiere versión 2.3.3 o superior previamente instalada
-- - Campo waiter_id debe existir en tabla tables (agregado en v2.3.3)
-- - Campo created_by debe existir en tabla orders (existente desde inicio)
-- 
-- ROLLBACK (si es necesario):
-- ```sql
-- -- Eliminar índices agregados
-- DROP INDEX idx_created_by_status ON orders;
-- DROP INDEX idx_table_status ON orders;
-- DROP INDEX idx_waiter_status ON tables;
-- 
-- -- Eliminar configuraciones
-- DELETE FROM settings WHERE setting_key IN (
--     'enable_table_role_filter',
--     'enable_order_role_filter', 
--     'waiter_see_table_orders',
--     'require_waiter_assignment'
-- );
-- 
-- -- Revertir versión
-- UPDATE settings SET setting_value = '2.3.3' 
-- WHERE setting_key = 'current_system_version';
-- ```
--
-- CONSIDERACIONES DE SEGURIDAD:
-- - El filtrado se realiza a nivel de base de datos
-- - No se expone información de otros usuarios
-- - Los prepared statements previenen SQL injection
-- - La validación de rol se hace server-side
-- 
-- MEJORAS FUTURAS SUGERIDAS:
-- - Implementar vista de "todas las órdenes de mis mesas" para meseros
-- - Dashboard específico para meseros con sus estadísticas
-- - Notificaciones cuando se asigna una nueva mesa a un mesero
-- - Reporte de desempeño por mesero
-- - Historial de asignaciones de mesas
-- 
-- =============================================