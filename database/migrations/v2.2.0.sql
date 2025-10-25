-- =============================================
-- Migración a versión 2.2.0
-- Fecha: 2025-10-25
-- Descripción: Sistema de actualización automática con licencias
-- =============================================

START TRANSACTION;

-- Crear tabla de control de migraciones (si no existe)
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `executed_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `execution_time` float DEFAULT NULL COMMENT 'Tiempo de ejecución en segundos',
  `status` enum('success','failed') DEFAULT 'success',
  `error_message` text,
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`),
  KEY `idx_status` (`status`),
  KEY `idx_executed_at` (`executed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registro de migraciones ejecutadas';

-- Agregar configuraciones del sistema de licencias
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) VALUES 
('system_id', '', 'ID único del sistema'),
('system_license', '', 'Clave de licencia del sistema'),
('system_commit', 'initial', 'Hash del último commit instalado'),
('license_verified_at', NULL, 'Fecha de última verificación de licencia')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- Actualizar versión del sistema
UPDATE `settings` 
SET `setting_value` = '2.2.0' 
WHERE `setting_key` = 'current_system_version';

COMMIT;

-- =============================================
-- Fin de migración v2.2.0
-- =============================================
