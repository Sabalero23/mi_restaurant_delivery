<?php
/**
 * MigrationManager - Sistema de Migraciones Automáticas
 * 
 * Gestiona la ejecución automática de archivos SQL de migración
 * durante el proceso de actualización del sistema.
 * 
 * @version 2.2.0
 * @author Cellcom Technology
 */

class MigrationManager {
    private $db;
    private $migrationsPath;
    
    /**
     * Constructor
     * 
     * @param PDO $db Conexión a la base de datos
     * @param string $migrationsPath Ruta a la carpeta de migraciones
     */
    public function __construct($db, $migrationsPath = '../../database/migrations') {
        $this->db = $db;
        $this->migrationsPath = $migrationsPath;
        $this->ensureMigrationsTableExists();
    }
    
    /**
     * Asegurar que existe la tabla de control de migraciones
     */
    private function ensureMigrationsTableExists() {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
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
        COMMENT='Registro de migraciones ejecutadas'";
        
        $this->db->exec($sql);
    }
    
    /**
     * Detectar migraciones nuevas que no se han ejecutado
     * 
     * @return array Lista de archivos de migración pendientes
     */
    public function detectPendingMigrations() {
        $pendingMigrations = [];
        
        // Verificar que existe la carpeta de migraciones
        if (!file_exists($this->migrationsPath)) {
            return $pendingMigrations;
        }
        
        // Obtener versiones ya ejecutadas
        $executedVersions = $this->getExecutedVersions();
        
        // Escanear archivos de migración
        $files = glob($this->migrationsPath . '/v*.sql');
        
        foreach ($files as $file) {
            $filename = basename($file);
            $version = $this->extractVersionFromFilename($filename);
            
            // Si no se ha ejecutado, agregarla a pendientes
            if ($version && !in_array($version, $executedVersions)) {
                $pendingMigrations[] = [
                    'version' => $version,
                    'filename' => $filename,
                    'filepath' => $file
                ];
            }
        }
        
        // Ordenar por versión
        usort($pendingMigrations, function($a, $b) {
            return version_compare($a['version'], $b['version']);
        });
        
        return $pendingMigrations;
    }
    
    /**
     * Obtener lista de versiones ya ejecutadas
     * 
     * @return array Lista de versiones ejecutadas
     */
    private function getExecutedVersions() {
        try {
            $stmt = $this->db->query("SELECT version FROM migrations WHERE status = 'success'");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Extraer número de versión del nombre de archivo
     * 
     * @param string $filename Nombre del archivo (ej: v2.1.1.sql)
     * @return string|null Versión extraída (ej: 2.1.1)
     */
    private function extractVersionFromFilename($filename) {
        if (preg_match('/v(\d+\.\d+\.\d+)\.sql/', $filename, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Ejecutar una migración específica
     * 
     * @param array $migration Datos de la migración
     * @return array Resultado de la ejecución
     */
    public function executeMigration($migration) {
        $startTime = microtime(true);
        
        try {
            // Leer archivo SQL
            $sql = file_get_contents($migration['filepath']);
            
            if ($sql === false) {
                throw new Exception("No se pudo leer el archivo de migración");
            }
            
            // Ejecutar SQL (los archivos SQL ya tienen sus propias transacciones)
            $this->db->exec($sql);
            
            // Calcular tiempo de ejecución
            $executionTime = microtime(true) - $startTime;
            
            // Registrar migración exitosa
            $this->recordMigration(
                $migration['version'],
                $migration['filename'],
                'success',
                null,
                $executionTime
            );
            
            return [
                'success' => true,
                'version' => $migration['version'],
                'execution_time' => round($executionTime, 2),
                'message' => "Migración {$migration['version']} ejecutada exitosamente"
            ];
            
        } catch (Exception $e) {
            $executionTime = microtime(true) - $startTime;
            
            // Registrar migración fallida
            $this->recordMigration(
                $migration['version'],
                $migration['filename'],
                'failed',
                $e->getMessage(),
                $executionTime
            );
            
            return [
                'success' => false,
                'version' => $migration['version'],
                'execution_time' => round($executionTime, 2),
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Ejecutar todas las migraciones pendientes
     * 
     * @return array Resultado de todas las migraciones
     */
    public function runPendingMigrations() {
        $pendingMigrations = $this->detectPendingMigrations();
        $results = [];
        
        if (empty($pendingMigrations)) {
            return [
                'success' => true,
                'message' => 'No hay migraciones pendientes',
                'migrations' => []
            ];
        }
        
        foreach ($pendingMigrations as $migration) {
            $result = $this->executeMigration($migration);
            $results[] = $result;
            
            // Si falla una migración, detener el proceso
            if (!$result['success']) {
                return [
                    'success' => false,
                    'message' => "Migración {$migration['version']} falló",
                    'error' => $result['error'],
                    'migrations' => $results
                ];
            }
        }
        
        return [
            'success' => true,
            'message' => count($results) . ' migraciones ejecutadas exitosamente',
            'migrations' => $results
        ];
    }
    
    /**
     * Registrar una migración en la base de datos
     * 
     * @param string $version Versión de la migración
     * @param string $filename Nombre del archivo
     * @param string $status Estado (success/failed)
     * @param string|null $errorMessage Mensaje de error si falló
     * @param float|null $executionTime Tiempo de ejecución
     */
    private function recordMigration($version, $filename, $status, $errorMessage = null, $executionTime = null) {
        try {
            $sql = "INSERT INTO migrations (version, filename, status, error_message, execution_time) 
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                        executed_at = CURRENT_TIMESTAMP,
                        status = VALUES(status),
                        error_message = VALUES(error_message),
                        execution_time = VALUES(execution_time)";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$version, $filename, $status, $errorMessage, $executionTime]);
        } catch (Exception $e) {
            // Si falla el registro, no detener el proceso principal
            error_log("Error al registrar migración: " . $e->getMessage());
        }
    }
    
    /**
     * Obtener historial completo de migraciones
     * 
     * @return array Lista de migraciones ejecutadas
     */
    public function getMigrationHistory() {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM migrations ORDER BY executed_at DESC"
            );
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Obtener estadísticas de migraciones
     * 
     * @return array Estadísticas
     */
    public function getStatistics() {
        try {
            $stats = [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'total_execution_time' => 0,
                'last_migration' => null
            ];
            
            // Contar migraciones
            $stmt = $this->db->query(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(execution_time) as total_time
                FROM migrations"
            );
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $stats['total'] = (int)$result['total'];
                $stats['successful'] = (int)$result['successful'];
                $stats['failed'] = (int)$result['failed'];
                $stats['total_execution_time'] = round((float)$result['total_time'], 2);
            }
            
            // Última migración
            $stmt = $this->db->query(
                "SELECT version, executed_at, status 
                FROM migrations 
                WHERE status = 'success'
                ORDER BY executed_at DESC 
                LIMIT 1"
            );
            $lastMigration = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($lastMigration) {
                $stats['last_migration'] = $lastMigration;
            }
            
            return $stats;
            
        } catch (Exception $e) {
            return [
                'total' => 0,
                'successful' => 0,
                'failed' => 0,
                'total_execution_time' => 0,
                'last_migration' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Verificar si una versión específica ya fue migrada
     * 
     * @param string $version Versión a verificar
     * @return bool True si ya fue migrada
     */
    public function isMigrated($version) {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM migrations WHERE version = ? AND status = 'success'"
            );
            $stmt->execute([$version]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Reintentar una migración fallida
     * 
     * @param string $version Versión a reintentar
     * @return array Resultado
     */
    public function retryFailedMigration($version) {
        // Buscar el archivo de migración
        $filepath = $this->migrationsPath . "/v{$version}.sql";
        
        if (!file_exists($filepath)) {
            return [
                'success' => false,
                'error' => "Archivo de migración no encontrado: v{$version}.sql"
            ];
        }
        
        $migration = [
            'version' => $version,
            'filename' => "v{$version}.sql",
            'filepath' => $filepath
        ];
        
        return $this->executeMigration($migration);
    }
}