<?php
// admin/api/github-update.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireLogin();

// Solo administradores pueden actualizar el sistema
if ($_SESSION['role_name'] !== 'administrador') {
    echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
    exit();
}

$database = new Database();
$db = $database->getConnection();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'check':
    case 'check_updates':
        checkForUpdates($db);
        break;
    case 'get_changes':
        getChanges($db);
        break;
    case 'backup':
        createBackup($db);
        break;
    case 'update':
        performUpdate($db);
        break;
    case 'rollback':
        rollbackUpdate($db);
        break;
    case 'get_logs':
        getUpdateLogs($db);
        break;
    case 'verify_license':
        verifyLicense($db);
        break;
    case 'generate_system_id':
        generateSystemId($db);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida: ' . $action]);
        break;
}

/**
 * Generar ID único del sistema
 */
function generateSystemId($db) {
    try {
        // Verificar si ya existe un system_id
        $query = "SELECT setting_value FROM settings WHERE setting_key = 'system_id'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'system_id' => $result['setting_value']
            ]);
            return;
        }
        
        // Generar nuevo ID del sistema basado en varios factores
        $systemId = generateUniqueSystemId();
        
        // Guardar en la base de datos
        $query = "INSERT INTO settings (setting_key, setting_value, description) 
                  VALUES ('system_id', ?, 'ID único del sistema')";
        $stmt = $db->prepare($query);
        $stmt->execute([$systemId]);
        
        echo json_encode([
            'success' => true,
            'system_id' => $systemId,
            'message' => 'ID del sistema generado correctamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al generar ID del sistema: ' . $e->getMessage()
        ]);
    }
}

/**
 * Verificar licencia del sistema
 */
function verifyLicense($db) {
    try {
        $licenseKey = $_POST['license_key'] ?? '';
        
        if (empty($licenseKey)) {
            throw new Exception('Clave de licencia no proporcionada');
        }
        
        // Obtener system_id
        $systemId = getSystemId($db);
        
        if (!$systemId) {
            throw new Exception('ID del sistema no encontrado. Genera uno primero.');
        }
        
        // Verificar la licencia
        $isValid = validateLicenseKey($licenseKey, $systemId);
        
        if ($isValid) {
            // Guardar licencia válida
            updateSetting($db, 'system_license', $licenseKey);
            updateSetting($db, 'license_verified_at', date('Y-m-d H:i:s'));
            
            echo json_encode([
                'success' => true,
                'message' => 'Licencia verificada correctamente',
                'valid_until' => date('d/m/Y', strtotime('+1 year'))
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Clave de licencia inválida'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al verificar licencia: ' . $e->getMessage()
        ]);
    }
}

/**
 * Verificar si hay actualizaciones disponibles
 */
function checkForUpdates($db) {
    try {
        $settings = getGithubSettings($db);
        
        // Verificar licencia antes de continuar
        if (!isLicenseValid($db)) {
            echo json_encode([
                'success' => false,
                'message' => 'Licencia no válida. Por favor, activa tu licencia para continuar.',
                'requires_license' => true
            ]);
            return;
        }
        
        // Obtener último commit del repositorio
        $url = "https://api.github.com/repos/{$settings['repo']}/commits/{$settings['branch']}";
        $headers = ['User-Agent: PHP-GitHub-Updater'];
        
        $response = makeGithubRequest($url, $headers);
        
        if (!$response) {
            throw new Exception('No se pudo conectar con GitHub');
        }
        
        $latestCommit = $response['sha'];
        $commitMessage = $response['commit']['message'];
        $commitDate = $response['commit']['committer']['date'];
        $commitAuthor = $response['commit']['author']['name'];
        
        // Obtener commit actual del sistema
        $currentCommit = getSystemCommit($db);
        
        $hasUpdates = ($currentCommit !== $latestCommit);
        
        // Si hay actualizaciones, obtener más detalles
        $commits = [];
        $stats = ['added' => 0, 'modified' => 0, 'removed' => 0];
        
        if ($hasUpdates && $currentCommit !== 'initial' && $currentCommit !== 'unknown') {
            $compareUrl = "https://api.github.com/repos/{$settings['repo']}/compare/{$currentCommit}...{$settings['branch']}";
            $compareResponse = makeGithubRequest($compareUrl, $headers);
            
            if ($compareResponse) {
                // Obtener commits
                if (isset($compareResponse['commits'])) {
                    $commits = array_map(function($commit) {
                        return [
                            'sha' => $commit['sha'],
                            'message' => $commit['commit']['message'],
                            'author' => $commit['commit']['author']['name'],
                            'date' => $commit['commit']['author']['date']
                        ];
                    }, array_slice($compareResponse['commits'], 0, 10));
                }
                
                // Contar archivos
                if (isset($compareResponse['files'])) {
                    foreach ($compareResponse['files'] as $file) {
                        if ($file['status'] === 'added') $stats['added']++;
                        elseif ($file['status'] === 'modified') $stats['modified']++;
                        elseif ($file['status'] === 'removed') $stats['removed']++;
                    }
                }
            }
        }
        
        // Actualizar última verificación
        updateSetting($db, 'last_update_check', date('Y-m-d H:i:s'));
        
        echo json_encode([
            'success' => true,
            'updates_available' => $hasUpdates,
            'has_updates' => $hasUpdates,
            'current_commit' => $currentCommit,
            'latest_commit' => $latestCommit,
            'commits_ahead' => count($commits),
            'commit_message' => $commitMessage,
            'commit_date' => date('d/m/Y H:i', strtotime($commitDate)),
            'commit_author' => $commitAuthor,
            'commits' => $commits,
            'stats' => $stats,
            'last_check' => date('d/m/Y H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al verificar actualizaciones: ' . $e->getMessage(),
            'current_commit' => getSystemCommit($db)
        ]);
    }
}

/**
 * Obtener lista de cambios entre versiones
 */
function getChanges($db) {
    try {
        $settings = getGithubSettings($db);
        $currentCommit = getSystemCommit($db);
        
        // Obtener comparación de commits
        $url = "https://api.github.com/repos/{$settings['repo']}/compare/{$currentCommit}...{$settings['branch']}";
        $headers = ['User-Agent: PHP-GitHub-Updater'];
        
        $response = makeGithubRequest($url, $headers);
        
        if (!$response) {
            throw new Exception('No se pudo obtener la lista de cambios');
        }
        
        $changes = [
            'added' => [],
            'modified' => [],
            'removed' => [],
            'total' => $response['total_commits'] ?? 0
        ];
        
        foreach ($response['files'] ?? [] as $file) {
            $fileInfo = [
                'name' => $file['filename'],
                'changes' => $file['changes'],
                'additions' => $file['additions'],
                'deletions' => $file['deletions']
            ];
            
            switch ($file['status']) {
                case 'added':
                    $changes['added'][] = $fileInfo;
                    break;
                case 'modified':
                    $changes['modified'][] = $fileInfo;
                    break;
                case 'removed':
                    $changes['removed'][] = $fileInfo;
                    break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'changes' => $changes,
            'commits' => array_map(function($commit) {
                return [
                    'sha' => substr($commit['sha'], 0, 7),
                    'message' => $commit['commit']['message'],
                    'author' => $commit['commit']['author']['name'],
                    'date' => date('d/m/Y H:i', strtotime($commit['commit']['author']['date']))
                ];
            }, array_slice($response['commits'] ?? [], 0, 10))
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener cambios: ' . $e->getMessage()
        ]);
    }
}

/**
 * Crear backup del sistema actual
 */
function createBackup($db) {
    try {
        $backupDir = '../../backups';
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = "{$backupDir}/backup_{$timestamp}";
        
        // Crear directorio de backup
        mkdir($backupPath, 0755, true);
        
        // Archivos y carpetas a excluir del backup
        $excludePaths = [
            'backups',
            'uploads',
            'admin/uploads',
            'whatsapp_media',
            'config/config.php',
            'admin/receipts',
            'admin/tickets',
            '.git',
            'node_modules'
        ];
        
        // Copiar archivos del sistema
        $rootPath = realpath('../../');
        $filesBackedUp = copyDirectory($rootPath, $backupPath, $excludePaths);
        
        // Crear backup de configuración (sin credenciales sensibles)
        $configBackup = [
            'timestamp' => $timestamp,
            'php_version' => PHP_VERSION,
            'system_version' => getSystemVersion($db),
            'commit' => getSystemCommit($db)
        ];
        
        file_put_contents(
            "{$backupPath}/backup_info.json",
            json_encode($configBackup, JSON_PRETTY_PRINT)
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup creado exitosamente',
            'backup_path' => basename($backupPath),
            'files_backed_up' => $filesBackedUp,
            'size' => formatBytes(getFolderSize($backupPath))
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al crear backup: ' . $e->getMessage()
        ]);
    }
}

/**
 * Realizar la actualización del sistema
 */
function performUpdate($db) {
    try {
        // Verificar licencia
        if (!isLicenseValid($db)) {
            throw new Exception('Licencia no válida. Activa tu licencia para continuar.');
        }
        
        // Iniciar transacción de log
        $updateId = createUpdateLog($db);
        
        $settings = getGithubSettings($db);
        $currentCommit = getSystemCommit($db);
        
        // 1. Crear backup si está habilitado
        if ($settings['auto_backup']) {
            updateUpdateLog($db, $updateId, 'in_progress', 'Creando backup...');
            
            $backupResult = createBackupInternal();
            if (!$backupResult['success']) {
                throw new Exception('Error al crear backup: ' . $backupResult['message']);
            }
            
            updateUpdateLog($db, $updateId, 'in_progress', 'Backup creado', $backupResult['path']);
        }
        
        // 2. Descargar archivos del repositorio
        updateUpdateLog($db, $updateId, 'in_progress', 'Descargando archivos desde GitHub...');
        
        $zipUrl = "https://github.com/{$settings['repo']}/archive/refs/heads/{$settings['branch']}.zip";
        $zipFile = '../../temp_update.zip';
        
        if (!downloadFile($zipUrl, $zipFile)) {
            throw new Exception('Error al descargar archivos de GitHub');
        }
        
        // 3. Extraer archivos
        updateUpdateLog($db, $updateId, 'in_progress', 'Extrayendo archivos...');
        
        $zip = new ZipArchive;
        if ($zip->open($zipFile) !== TRUE) {
            throw new Exception('Error al abrir archivo ZIP');
        }
        
        $extractPath = '../../temp_update';
        $zip->extractTo($extractPath);
        $zip->close();
        
        // 4. Copiar archivos actualizados
        updateUpdateLog($db, $updateId, 'in_progress', 'Instalando archivos...');
        
        // Encontrar el directorio extraído (tiene el formato: nombre-rama)
        $extractedDirs = glob($extractPath . '/*', GLOB_ONLYDIR);
        if (empty($extractedDirs)) {
            throw new Exception('No se encontraron archivos extraídos');
        }
        
        $sourceDir = $extractedDirs[0];
        $targetDir = realpath('../../');
        
        // Archivos a excluir de la actualización
        $excludeFiles = [
            'config/config.php',
            'uploads',
            'admin/uploads',
            'whatsapp_media',
            'backups',
            'admin/receipts',
            'admin/tickets'
        ];
        
        $stats = copyDirectory($sourceDir, $targetDir, $excludeFiles);
        
        // 5. Limpiar archivos temporales
        unlink($zipFile);
        deleteDirectory($extractPath);
        
        // 6. Obtener nuevo commit hash
        $newCommit = getLatestCommitHash($settings);
        updateSetting($db, 'system_commit', $newCommit);
        
        // 7. Actualizar log de actualización
        updateUpdateLog($db, $updateId, 'completed', 'Actualización completada exitosamente');
        
        $query = "UPDATE system_updates SET 
                  completed_at = NOW(),
                  from_commit = ?,
                  to_commit = ?,
                  files_added = ?,
                  files_updated = ?,
                  files_deleted = ?
                  WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $currentCommit,
            $newCommit,
            $stats['added'] ?? 0,
            $stats['modified'] ?? 0,
            $stats['deleted'] ?? 0,
            $updateId
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Sistema actualizado exitosamente',
            'stats' => [
                'added' => $stats['added'] ?? 0,
                'updated' => $stats['modified'] ?? 0,
                'deleted' => $stats['deleted'] ?? 0
            ],
            'new_commit' => substr($newCommit, 0, 7)
        ]);
        
    } catch (Exception $e) {
        if (isset($updateId)) {
            updateUpdateLog($db, $updateId, 'failed', $e->getMessage());
        }
        
        echo json_encode([
            'success' => false,
            'message' => 'Error durante la actualización: ' . $e->getMessage()
        ]);
    }
}

/**
 * Revertir actualización usando backup
 */
function rollbackUpdate($db) {
    try {
        $backupPath = $_POST['backup_path'] ?? '';
        
        if (empty($backupPath)) {
            throw new Exception('No se especificó el backup a restaurar');
        }
        
        $fullBackupPath = "../../backups/{$backupPath}";
        
        if (!file_exists($fullBackupPath)) {
            throw new Exception('El backup especificado no existe');
        }
        
        // Restaurar archivos desde backup
        $rootPath = realpath('../../');
        $filesRestored = copyDirectory($fullBackupPath, $rootPath, ['backups']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Sistema restaurado exitosamente',
            'files_restored' => $filesRestored
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al restaurar: ' . $e->getMessage()
        ]);
    }
}

/**
 * Obtener logs de actualizaciones
 */
function getUpdateLogs($db) {
    try {
        $query = "SELECT su.*, u.username 
                  FROM system_updates su
                  LEFT JOIN users u ON su.updated_by = u.id
                  ORDER BY su.started_at DESC
                  LIMIT 20";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $logs = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'logs' => $logs
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener logs: ' . $e->getMessage()
        ]);
    }
}

// ===== FUNCIONES AUXILIARES =====

function generateUniqueSystemId() {
    // Generar ID único basado en varios factores del servidor
    $factors = [
        php_uname('n'), // hostname
        $_SERVER['SERVER_ADDR'] ?? '',
        $_SERVER['DOCUMENT_ROOT'] ?? '',
        date('Ymd')
    ];
    
    $combined = implode('|', $factors);
    $hash = hash('sha256', $combined);
    
    // Tomar los primeros 16 caracteres y formatear
    $systemId = substr($hash, 0, 4) . '-' . 
                substr($hash, 4, 4) . '-' . 
                substr($hash, 8, 4) . '-' . 
                substr($hash, 12, 4);
    
    return strtoupper($systemId);
}

function validateLicenseKey($licenseKey, $systemId) {
    // Algoritmo para validar la licencia
    // La licencia se genera como: HASH(system_id + secret_key)
    
    $secretKey = 'MRD2025'; // Clave secreta del desarrollador
    
    // Generar el hash esperado
    $expectedHash = hash('sha256', $systemId . $secretKey);
    $expectedLicense = strtoupper(substr($expectedHash, 0, 20));
    
    // Formatear la licencia esperada con guiones
    $formattedExpected = substr($expectedLicense, 0, 5) . '-' .
                         substr($expectedLicense, 5, 5) . '-' .
                         substr($expectedLicense, 10, 5) . '-' .
                         substr($expectedLicense, 15, 5);
    
    return $licenseKey === $formattedExpected;
}

function isLicenseValid($db) {
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'system_license'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch();
    
    if (!$result) {
        return false;
    }
    
    $systemId = getSystemId($db);
    if (!$systemId) {
        return false;
    }
    
    return validateLicenseKey($result['setting_value'], $systemId);
}

function getSystemId($db) {
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'system_id'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result['setting_value'] ?? null;
}

function getGithubSettings($db) {
    $query = "SELECT setting_key, setting_value FROM settings 
              WHERE setting_key IN ('github_repo', 'github_branch', 'auto_backup_before_update')";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $results = $stmt->fetchAll();
    
    $settings = [
        'repo' => 'Sabalero23/mi_restaurant_delivery',
        'branch' => 'main',
        'auto_backup' => '1'
    ];
    
    foreach ($results as $row) {
        $key = str_replace('github_', '', $row['setting_key']);
        $key = str_replace('auto_backup_before_update', 'auto_backup', $key);
        $settings[$key] = $row['setting_value'];
    }
    
    return $settings;
}

function getSystemCommit($db) {
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'system_commit'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result['setting_value'] ?? 'initial';
}

function getSystemVersion($db) {
    $query = "SELECT setting_value FROM settings WHERE setting_key = 'current_system_version'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $result = $stmt->fetch();
    
    return $result['setting_value'] ?? '2.1.0';
}

function updateSetting($db, $key, $value) {
    $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
              ON DUPLICATE KEY UPDATE setting_value = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$key, $value, $value]);
}

function makeGithubRequest($url, $headers) {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => implode("\r\n", $headers),
            'timeout' => 30
        ]
    ]);
    
    $response = @file_get_contents($url, false, $context);
    
    if ($response === false) {
        return null;
    }
    
    return json_decode($response, true);
}

function getLatestCommitHash($settings) {
    $url = "https://api.github.com/repos/{$settings['repo']}/commits/{$settings['branch']}";
    $headers = ['User-Agent: PHP-GitHub-Updater'];
    
    $response = makeGithubRequest($url, $headers);
    return $response['sha'] ?? 'unknown';
}

function createUpdateLog($db) {
    $query = "INSERT INTO system_updates (updated_by, status, started_at)
              VALUES (?, 'in_progress', NOW())";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id']]);
    
    return $db->lastInsertId();
}

function updateUpdateLog($db, $id, $status, $message = null, $backupPath = null) {
    $query = "UPDATE system_updates SET status = ?, error_message = ?, backup_path = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$status, $message, $backupPath, $id]);
}

function createBackupInternal() {
    $backupDir = '../../backups';
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $backupPath = "{$backupDir}/backup_{$timestamp}";
    mkdir($backupPath, 0755, true);
    
    $rootPath = realpath('../../');
    $excludePaths = ['backups', 'uploads', 'admin/uploads', 'whatsapp_media', 'config/config.php'];
    
    copyDirectory($rootPath, $backupPath, $excludePaths);
    
    return [
        'success' => true,
        'path' => basename($backupPath)
    ];
}

function downloadFile($url, $destination) {
    $context = stream_context_create([
        'http' => [
            'timeout' => 300,
            'user_agent' => 'PHP-GitHub-Updater'
        ]
    ]);
    
    return copy($url, $destination, $context);
}

function copyDirectory($source, $destination, $exclude = []) {
    $count = 0;
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $item) {
        $relativePath = str_replace($source . DIRECTORY_SEPARATOR, '', $item->getPathname());
        $relativePath = str_replace('\\', '/', $relativePath);
        
        $shouldExclude = false;
        foreach ($exclude as $excludePath) {
            if (strpos($relativePath, $excludePath) === 0) {
                $shouldExclude = true;
                break;
            }
        }
        
        if ($shouldExclude) {
            continue;
        }
        
        $targetPath = $destination . DIRECTORY_SEPARATOR . $relativePath;
        
        if ($item->isDir()) {
            if (!file_exists($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
        } else {
            copy($item->getPathname(), $targetPath);
            $count++;
        }
    }
    
    return $count;
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return;
    }
    
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    
    foreach ($files as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    
    rmdir($dir);
}

function getFolderSize($path) {
    $size = 0;
    
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
        $size += $file->getSize();
    }
    
    return $size;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
