-- Migración: Agregar columna update_details a system_updates
-- Versión: 2.2.6
-- Fecha: 2025-10-26

START TRANSACTION;

-- Agregar columna para guardar detalles de archivos actualizados
ALTER TABLE `system_updates` 
ADD COLUMN `update_details` TEXT NULL COMMENT 'Detalles JSON de archivos agregados, modificados y eliminados' 
AFTER `files_deleted`;

-- Crear índice para búsqueda de actualizaciones por estado
CREATE INDEX idx_status_date ON system_updates(status, started_at);

COMMIT;