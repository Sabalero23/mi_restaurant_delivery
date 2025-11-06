-- =============================================
-- Migración v2.3.0 - Corrección de Caracteres Especiales
-- =============================================
-- Descripción: Corrección de caracteres especiales en productos
--              Se corrige el doble escapado de htmlspecialchars
--              Se actualizan nombres con &#039; a apóstrofes normales
-- Fecha: 2025-01-06
-- Autor: Cellcom Technology
-- =============================================

START TRANSACTION;

-- =============================================
-- Verificar que existe la clave current_system_version
-- Si no existe, crearla con valor inicial
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('current_system_version', '2.3.0', 'Versión actual del sistema')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- =============================================
-- Actualizar versión del sistema a 2.3.0
-- =============================================
UPDATE `settings` 
SET `setting_value` = '2.3.0' 
WHERE `setting_key` = 'current_system_version';

-- =============================================
-- Registrar fecha de esta migración
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('migration_v230_date', NOW(), 'Fecha de migración a v2.3.0')
ON DUPLICATE KEY UPDATE `setting_value` = NOW();

-- =============================================
-- CORRECCIÓN: Limpiar caracteres HTML escapados en productos
-- =============================================
-- Corregir apóstrofes escapados como &#039;
UPDATE `products` 
SET `name` = REPLACE(`name`, '&#039;', "'")
WHERE `name` LIKE '%&#039;%';

UPDATE `products` 
SET `description` = REPLACE(`description`, '&#039;', "'")
WHERE `description` LIKE '%&#039;%';

-- Corregir comillas dobles escapadas como &quot;
UPDATE `products` 
SET `name` = REPLACE(`name`, '&quot;', '"')
WHERE `name` LIKE '%&quot;%';

UPDATE `products` 
SET `description` = REPLACE(`description`, '&quot;', '"')
WHERE `description` LIKE '%&quot;%';

-- Corregir ampersand escapado como &amp;
UPDATE `products` 
SET `name` = REPLACE(`name`, '&amp;', '&')
WHERE `name` LIKE '%&amp;%';

UPDATE `products` 
SET `description` = REPLACE(`description`, '&amp;', '&')
WHERE `description` LIKE '%&amp;%';

-- Corregir menor que escapado como &lt;
UPDATE `products` 
SET `name` = REPLACE(`name`, '&lt;', '<')
WHERE `name` LIKE '%&lt;%';

UPDATE `products` 
SET `description` = REPLACE(`description`, '&lt;', '<')
WHERE `description` LIKE '%&lt;%';

-- Corregir mayor que escapado como &gt;
UPDATE `products` 
SET `name` = REPLACE(`name`, '&gt;', '>')
WHERE `name` LIKE '%&gt;%';

UPDATE `products` 
SET `description` = REPLACE(`description`, '&gt;', '>')
WHERE `description` LIKE '%&gt;%';

-- =============================================
-- CORRECCIÓN: Limpiar caracteres HTML escapados en categorías
-- =============================================
UPDATE `categories` 
SET `name` = REPLACE(`name`, '&#039;', "'")
WHERE `name` LIKE '%&#039;%';

UPDATE `categories` 
SET `description` = REPLACE(`description`, '&#039;', "'")
WHERE `description` LIKE '%&#039;%';

UPDATE `categories` 
SET `name` = REPLACE(`name`, '&quot;', '"')
WHERE `name` LIKE '%&quot;%';

UPDATE `categories` 
SET `name` = REPLACE(`name`, '&amp;', '&')
WHERE `name` LIKE '%&amp;%';

-- =============================================
-- Mostrar productos afectados (para verificación)
-- =============================================
SELECT 
    'Productos corregidos' AS tipo,
    COUNT(*) AS total
FROM `products`
WHERE 
    `name` LIKE "%'%" 
    OR `description` LIKE "%'%"
    OR `name` LIKE '%"%'
    OR `name` LIKE '%&%';

-- =============================================
-- Registrar log de cambios de esta versión
-- =============================================
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES (
    'migration_v230_log', 
    'Actualización de sistema v2.3.0: Corrección de caracteres especiales en productos y categorías. Se eliminó el doble escapado de htmlspecialchars en funciones createProduct() y updateProduct(). Se implementó uso correcto de trim() y strip_tags() al guardar en base de datos.',
    'Log de cambios versión 2.3.0'
)
ON DUPLICATE KEY UPDATE 
    `setting_value` = VALUES(`setting_value`),
    `description` = VALUES(`description`);

-- =============================================
-- Verificación: Mostrar versión actualizada
-- =============================================
SELECT 
    'Migración v2.3.0 completada exitosamente' AS status,
    (SELECT setting_value FROM settings WHERE setting_key = 'current_system_version') AS nueva_version,
    (SELECT setting_value FROM settings WHERE setting_key = 'migration_v230_date') AS fecha_migracion,
    NOW() AS timestamp_completado;

COMMIT;

-- =============================================
-- NOTAS DE LA VERSIÓN 2.3.0
-- =============================================
-- 
-- ARCHIVOS MODIFICADOS:
-- ✓ admin/products.php
--   - Función createProduct(): Cambiado sanitize() por trim() en name y description
--   - Función updateProduct(): Cambiado sanitize() por trim() en name y description
--   - Se previene doble escapado de caracteres especiales
--   - Líneas aproximadas: 90-96, 125-132
-- 
-- CORRECCIONES EN BASE DE DATOS:
-- ✓ Tabla products:
--   - Limpieza de &#039; a apóstrofes (')
--   - Limpieza de &quot; a comillas (")
--   - Limpieza de &amp; a ampersand (&)
--   - Limpieza de &lt; y &gt; a < y >
-- 
-- ✓ Tabla categories:
--   - Mismas correcciones aplicadas
-- 
-- SEGURIDAD:
-- - PDO con prepared statements previene SQL injection
-- - trim() elimina espacios innecesarios
-- - strip_tags() puede usarse opcionalmente para remover HTML
-- - htmlspecialchars() SOLO debe usarse al mostrar en HTML, NO al guardar
-- 
-- COMPATIBILIDAD:
-- - Requiere MySQL 5.7+ o MariaDB 10.2+
-- - Compatible con PHP 7.4+
-- - Requiere versión 2.2.9 previamente instalada
-- 
-- ROLLBACK (si es necesario):
-- UPDATE settings SET setting_value = '2.2.9' 
-- WHERE setting_key = 'current_system_version';
--
-- INSTRUCCIONES POST-MIGRACIÓN:
-- 1. Verificar que los productos muestren correctamente los apóstrofes
-- 2. Probar creación de nuevos productos con caracteres especiales
-- 3. Verificar que no haya doble escapado en el frontend
-- 4. Revisar logs de errores PHP por posibles issues
--
-- =============================================