-- =============================================
-- Migración v2.3.2 - Sistema de Notificaciones y Nuevo Flujo de Órdenes
-- =============================================
-- Descripción: Implementación de notificaciones de órdenes de mesa
--              y nuevo flujo de creación de órdenes con carrito temporal
-- Fecha: 2025-01-09
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- Verificar que existe la clave current_system_version
-- Si no existe, crearla con valor inicial
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.3.2', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =============================================
-- Actualizar versión del sistema a 2.3.2
-- =============================================
UPDATE `settings` 
SET `setting_value` = '2.3.2' 
WHERE `setting_key` = 'current_system_version';

-- =============================================
-- Registrar fecha de esta migración
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('migration_v232_date', NOW(), 'Fecha de migración a v2.3.2')
ON DUPLICATE KEY UPDATE `setting_value` = NOW();

-- =============================================
-- NUEVA FUNCIONALIDAD: Configuraciones del sistema de notificaciones
-- =============================================

-- Habilitar notificaciones de órdenes de mesa
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('enable_table_order_notifications', '1', 'Habilitar notificaciones cuando se crea una orden de mesa')
ON DUPLICATE KEY UPDATE `setting_value` = '1';

-- Intervalo de verificación de nuevas órdenes (en segundos)
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('table_orders_check_interval', '10', 'Intervalo en segundos para verificar nuevas órdenes de mesa')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Habilitar sonido de notificación
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('enable_notification_sound', '1', 'Habilitar sonido cuando se detecta una nueva orden')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Habilitar vibración en dispositivos móviles
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('enable_notification_vibration', '1', 'Habilitar vibración en dispositivos móviles para notificaciones')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =============================================
-- NUEVA FUNCIONALIDAD: Configuración del nuevo flujo de órdenes
-- =============================================

-- Habilitar nuevo flujo de creación de órdenes
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('enable_cart_order_flow', '1', 'Usar carrito temporal antes de crear orden en base de datos')
ON DUPLICATE KEY UPDATE `setting_value` = '1';

-- Tiempo de expiración del carrito temporal (en minutos)
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('cart_session_timeout', '60', 'Tiempo en minutos antes de limpiar automáticamente el carrito temporal')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- Requerir al menos un producto antes de finalizar
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('require_products_to_finalize', '1', 'Requerir al menos un producto en el carrito para finalizar orden')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =============================================
-- VERIFICACIÓN: Estado actual de órdenes
-- =============================================
SELECT 
    'Órdenes por tipo' AS resumen,
    type AS tipo_orden,
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pendientes,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmadas,
    SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) AS en_preparacion,
    SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) AS listas,
    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS entregadas
FROM `orders`
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY type;

-- =============================================
-- Registrar log de cambios de esta versión
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'migration_v232_log', 
    'Actualización de sistema v2.3.2: Sistema de Notificaciones y Nuevo Flujo de Órdenes
    
NUEVAS CARACTERÍSTICAS:

1. SISTEMA DE NOTIFICACIONES DE ÓRDENES DE MESA:
   • Notificación automática cuando un mesero crea una orden
   • Modal emergente con información de la orden
   • Sonido de alerta personalizado
   • Vibración en dispositivos móviles
   • Verificación automática cada 10 segundos
   • Detección por ID de última orden (más preciso)
   • Badge visual en tiempo real
   • Integración con sistema existente de notificaciones

2. NUEVO FLUJO DE CREACIÓN DE ÓRDENES:
   • Carrito temporal en sesión (no crea orden hasta finalizar)
   • Paso 1: Guardar información de la orden
   • Paso 2: Agregar productos al carrito
   • Paso 3: Finalizar y crear orden en BD
   • Modificar cantidades en tiempo real
   • Eliminar productos del carrito
   • Cálculo automático de totales
   • Panel lateral con resumen del pedido
   • Botón "Cancelar" para limpiar sesión

ARCHIVOS NUEVOS:
• admin/api/table-orders-recent.php - Endpoint para obtener órdenes recientes de mesa

ARCHIVOS MODIFICADOS:
• admin/dashboard.php
  - Nueva variable: let lastTableOrderId = null
  - Nueva función: checkTableOrders()
  - Nueva función: showTableOrderAlert()
  - Nueva función: createTableOrderModal()
  - Nueva función: dismissTableOrderAlert()
  - setInterval para verificación cada 10 segundos
  - Inicialización en DOMContentLoaded
  - Funciones de debug agregadas

• admin/order-create.php (cambio mayor de flujo)
  - Sistema de sesión para carrito temporal
  - Nueva acción: save_order_info (guarda en sesión)
  - Nueva acción: add_item (agrega a carrito temporal)
  - Nueva acción: update_quantity (modifica carrito)
  - Nueva acción: remove_item (elimina del carrito)
  - Nueva acción: finalize_order (crea orden en BD)
  - Nueva acción: cancel_order (limpia sesión)
  - Panel lateral con resumen del carrito
  - Cálculo de totales en tiempo real
  - Validaciones antes de crear orden
  - Transacción completa al finalizar
  - Botón "Finalizar Orden" en lugar de crear inmediatamente

FUNCIONALIDAD JAVASCRIPT:
• Detección de nuevas órdenes por comparación de ID
• Reproducción de sonido personalizado
• Modal Bootstrap con información detallada
• Botones de acción rápida (Ver Órdenes, Ver Cocina)
• Actualización de badges en tiempo real
• Sistema de debug para pruebas
• Funciones: debugDashboard.simulateTableOrder()

SEGURIDAD:
• Validación de permisos "orders" para notificaciones
• Autenticación requerida en API
• Prepared statements en queries
• Sanitización de datos de sesión
• Validación de productos antes de crear orden
• Verificación de mesa ocupada antes de crear orden

MEJORAS DE RENDIMIENTO:
• Verificación cada 10 segundos (configurable)
• Solo notifica cuando detecta cambio real
• Carga asíncrona de datos de órdenes
• Sesión para carrito reduce queries a BD
• Una sola transacción al crear orden completa

BASE DE DATOS:
• Nuevas configuraciones del sistema:
  - enable_table_order_notifications
  - table_orders_check_interval
  - enable_notification_sound
  - enable_notification_vibration
  - enable_cart_order_flow
  - cart_session_timeout
  - require_products_to_finalize

INTEGRACIÓN:
• Compatible con sistema de pedidos online existente
• Usa misma infraestructura de notificaciones
• Mantiene permisos y roles actuales
• No interfiere con órdenes existentes
• Funciona con sistema de temas actual',
    'Log de cambios versión 2.3.2'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- Verificación: Mostrar versión actualizada
-- =============================================
SELECT 
    'Migración v2.3.2 completada exitosamente' AS status,
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS nueva_version,
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v232_date') AS fecha_migracion,
    (SELECT setting_value FROM settings WHERE setting_key = 'enable_table_order_notifications') AS notificaciones_habilitadas,
    (SELECT setting_value FROM settings WHERE setting_key = 'enable_cart_order_flow') AS nuevo_flujo_habilitado,
    NOW() AS timestamp_completado;

COMMIT;

-- =============================================
-- ÍNDICES OPCIONALES (ejecutar después de COMMIT si se desea)
-- =============================================
-- Descomentar las siguientes líneas si se desea mejorar rendimiento:
-- 
-- ALTER TABLE `orders` ADD INDEX `idx_type_created` (`type`, `created_at`);
-- ALTER TABLE `orders` ADD INDEX `idx_created_by` (`created_by`);
-- ALTER TABLE `orders` ADD INDEX `idx_status_type` (`status`, `type`);
-- 
-- ANALYZE TABLE `orders`;
-- ANALYZE TABLE `tables`;

-- =============================================
-- NOTAS DE LA VERSIÓN 2.3.2
-- =============================================
-- 
-- REQUISITOS PREVIOS:
-- ✓ Versión 2.3.1 instalada
-- ✓ PHP 7.4 o superior
-- ✓ MySQL 5.7+ o MariaDB 10.2+
-- ✓ Extensión PDO habilitada
-- ✓ JavaScript habilitado en navegador
-- ✓ Sesiones PHP habilitadas
-- 
-- ARCHIVOS A DESPLEGAR:
-- 1. admin/dashboard.php (modificado - sistema de notificaciones)
-- 2. admin/order-create.php (reemplazar completamente - nuevo flujo)
-- 3. admin/api/table-orders-recent.php (nuevo archivo)
-- 4. Ejecutar este script SQL: v2.3.2.sql
-- 
-- CARACTERÍSTICAS DEL SISTEMA DE NOTIFICACIONES:
-- ✓ Verificación automática cada 10 segundos
-- ✓ Detección por ID de última orden (preciso)
-- ✓ Modal emergente con información completa:
--   - Número de orden
--   - Número de mesa
--   - Nombre del mesero
--   - Total de la orden
-- ✓ Sonido de notificación personalizado
-- ✓ Vibración en dispositivos móviles
-- ✓ Notificación flotante visual
-- ✓ Botones de acción rápida
-- ✓ Solo usuarios con permiso "orders"
-- ✓ Funciones de debug incluidas
-- 
-- CARACTERÍSTICAS DEL NUEVO FLUJO DE ÓRDENES:
-- ✓ No crea orden en BD hasta "Finalizar Orden"
-- ✓ Carrito temporal en $_SESSION
-- ✓ Agregar/modificar/eliminar productos libremente
-- ✓ Cálculo automático de totales
-- ✓ Panel lateral con resumen
-- ✓ Botón "Cancelar" para descartar cambios
-- ✓ Validación antes de crear orden
-- ✓ Una transacción completa al finalizar
-- ✓ Marca mesa como ocupada al finalizar
-- 
-- PRUEBAS RECOMENDADAS POST-INSTALACIÓN:
-- 
-- PRUEBAS DE NOTIFICACIONES:
-- 1. Abrir dashboard en computadora A
-- 2. Abrir consola del navegador (F12)
-- 3. Ejecutar: debugDashboard.simulateTableOrder()
-- 4. Verificar que aparece modal y suena notificación
-- 5. Crear orden real desde dispositivo B (mesero)
-- 6. Verificar que dashboard A recibe notificación en ~10 seg
-- 7. Verificar sonido y vibración (en móviles)
-- 8. Probar botones "Ver Órdenes" y "Ver Cocina"
-- 
-- PRUEBAS DE NUEVO FLUJO:
-- 1. Ir a "Nueva Orden" (order-create.php)
-- 2. Completar información de la orden
-- 3. Click en "Guardar y Agregar Productos"
-- 4. Verificar que NO se creó orden en BD todavía
-- 5. Agregar varios productos al carrito
-- 6. Modificar cantidades con botones +/-
-- 7. Eliminar algunos productos con botón X
-- 8. Verificar cálculo de totales en tiempo real
-- 9. Click en "Finalizar Orden"
-- 10. Verificar que se creó orden completa en BD
-- 11. Verificar que mesa se marcó como ocupada
-- 12. Probar botón "Cancelar" en otra orden nueva
-- 
-- CONFIGURACIONES DEL SISTEMA:
-- enable_table_order_notifications = 1 (activar/desactivar)
-- table_orders_check_interval = 10 (segundos)
-- enable_notification_sound = 1 (activar sonido)
-- enable_notification_vibration = 1 (activar vibración)
-- enable_cart_order_flow = 1 (usar nuevo flujo)
-- cart_session_timeout = 60 (minutos)
-- require_products_to_finalize = 1 (validar productos)
-- 
-- FUNCIONES DE DEBUG:
-- // En consola del navegador:
-- debugDashboard.checkTableOrders()       // Verificar manualmente
-- debugDashboard.simulateTableOrder()     // Simular nueva orden
-- debugDashboard.testSound()              // Probar sonido
-- debugDashboard.debugInfo()              // Ver info del sistema
-- 
-- MONITOREO Y AUDITORÍA:
-- SELECT * FROM orders 
-- WHERE type = 'dine_in' 
-- AND DATE(created_at) = CURDATE()
-- ORDER BY created_at DESC;
-- 
-- SELECT o.*, u.full_name AS mesero, t.number AS mesa
-- FROM orders o
-- LEFT JOIN users u ON o.created_by = u.id
-- LEFT JOIN tables t ON o.table_id = t.id
-- WHERE o.type = 'dine_in'
-- ORDER BY o.created_at DESC
-- LIMIT 20;
-- 
-- ROLLBACK (si es necesario):
-- UPDATE settings SET setting_value = '2.3.1' 
-- WHERE setting_key = 'current_system_version';
-- 
-- DELETE FROM settings 
-- WHERE setting_key IN (
--     'enable_table_order_notifications',
--     'table_orders_check_interval',
--     'enable_notification_sound',
--     'enable_notification_vibration',
--     'enable_cart_order_flow',
--     'cart_session_timeout',
--     'require_products_to_finalize'
-- );
-- 
-- Restaurar archivos anteriores:
-- - admin/dashboard.php (versión 2.3.1)
-- - admin/order-create.php (versión 2.3.1)
-- - Eliminar: admin/api/table-orders-recent.php
-- 
-- SOLUCIÓN DE PROBLEMAS:
-- 
-- Problema: Notificaciones no aparecen
-- Solución: 
--   1. Verificar que table-orders-recent.php existe
--   2. Abrir consola (F12) y buscar errores
--   3. Ejecutar: debugDashboard.checkTableOrders()
--   4. Verificar permisos del usuario (debe tener "orders")
--   5. Verificar en consola: hasPermissionTableOrders debe ser true
-- 
-- Problema: Sonido no se reproduce
-- Solución:
--   1. Hacer clic en la página al menos una vez (requisito Chrome)
--   2. Verificar que sonidos estén activados en configuración
--   3. Probar: debugDashboard.testSound()
--   4. Verificar permisos de audio del navegador
-- 
-- Problema: Carrito temporal se pierde
-- Solución:
--   1. Verificar que sesiones PHP estén habilitadas
--   2. Revisar session.gc_maxlifetime en php.ini
--   3. Ajustar cart_session_timeout en configuración
--   4. Verificar que no hay session_destroy() inesperado
-- 
-- Problema: Error al finalizar orden
-- Solución:
--   1. Verificar que hay al menos un producto en carrito
--   2. Revisar logs PHP para detalles del error
--   3. Verificar conexión a base de datos
--   4. Comprobar que tabla "orders" tiene estructura correcta
--   5. Verificar permisos de usuario para crear órdenes
-- 
-- Problema: Dashboard muestra declaración duplicada de función
-- Solución:
--   1. Buscar "function checkTableOrders()" en dashboard.php
--   2. Eliminar línea duplicada (debe haber solo una declaración)
--   3. Guardar y recargar navegador
-- 
-- COMPATIBILIDAD:
-- ✓ Compatible con sistema de pedidos online v2.3.x
-- ✓ Compatible con sistema de mesas v2.3.x
-- ✓ Compatible con roles y permisos existentes
-- ✓ Compatible con sistema de temas v2.3.x
-- ✓ No interfiere con órdenes existentes
-- ✓ Navegadores: Chrome 90+, Firefox 88+, Safari 14+, Edge 90+
-- ✓ Dispositivos móviles: iOS 14+, Android 8+
-- 
-- MEJORAS FUTURAS SUGERIDAS:
-- • Notificaciones push del navegador
-- • Personalización de sonido de notificación
-- • Historial de notificaciones recibidas
-- • Filtros en notificaciones (por mesero, por mesa)
-- • Estadísticas de tiempo de respuesta
-- • Integración con impresoras de cocina
-- • Sincronización en tiempo real con WebSockets
-- • Exportar carrito a PDF antes de finalizar
-- • Guardar borradores de órdenes
-- • Recuperar carrito abandonado
-- • Notificaciones de órdenes urgentes (>30 min)
-- • Dashboard con métricas de órdenes en tiempo real
-- 
-- OPTIMIZACIONES DE RENDIMIENTO:
-- • Índice en orders(type, created_at) para queries rápidas
-- • Índice en orders(created_by) para filtrar por mesero
-- • Límite de 5 órdenes en verificación (ajustable)
-- • Verificación cada 10 segundos (ajustable)
-- • Solo envía cambios reales (no datos completos)
-- • Sesión reduce carga en base de datos
-- 
-- SEGURIDAD IMPLEMENTADA:
-- • Prepared statements en todas las queries
-- • Validación de permisos en cada endpoint
-- • Sanitización de datos de entrada
-- • Escape de HTML en frontend
-- • Validación de tipos de datos
-- • Verificación de existencia de productos
-- • Control de acceso basado en roles
-- • Prevención de SQL injection
-- • Prevención de XSS
-- • CSRF protection (tokens en formularios)
-- 
-- DOCUMENTACIÓN ADICIONAL:
-- Ver archivos incluidos en la actualización:
-- • README.txt - Guía de instalación
-- • INSTRUCCIONES.txt - Manual de uso
-- • DIAGNOSTICO_Y_SOLUCION.txt - Troubleshooting
-- • CHECKLIST.txt - Lista de verificación
-- 
-- =============================================
-- FIN DE LA MIGRACIÓN v2.3.2
-- =============================================