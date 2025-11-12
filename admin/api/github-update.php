/**
 * Obtener logs de actualizaciones desde la nueva tabla
 */
function getUpdateLogs($db) {
    try {
        // CAMBIO PRINCIPAL: Leer desde system_update_logs en lugar de system_updates
        
        // Primero verificar si existe la nueva tabla
        $checkTable = "SHOW TABLES LIKE 'system_update_logs'";
        $stmt = $db->query($checkTable);
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            // Usar la NUEVA tabla system_update_logs
            $query = "SELECT 
                sul.id,
                sul.update_version,
                sul.from_commit,
                sul.to_commit,
                sul.status,
                sul.started_at,
                sul.completed_at,
                sul.user_id,
                sul.username,
                sul.files_added,
                sul.files_updated,
                sul.files_deleted,
                sul.backup_path,
                sul.update_details,
                sul.error_message,
                TIMESTAMPDIFF(SECOND, sul.started_at, sul.completed_at) as duration_seconds
            FROM system_update_logs sul
            ORDER BY sul.started_at DESC
            LIMIT 20";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Si no hay logs, intentar migrar desde system_updates
            if (empty($logs)) {
                migrateLegacyLogs($db);
                
                // Intentar de nuevo después de migrar
                $stmt->execute();
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
        } else {
            // Fallback: usar tabla antigua system_updates
            $query = "SELECT 
                su.id,
                'Legacy' as update_version,
                su.from_commit,
                su.to_commit,
                su.status,
                su.started_at,
                su.completed_at,
                su.updated_by as user_id,
                u.username,
                su.files_added,
                su.files_updated,
                su.files_deleted,
                su.backup_path,
                su.update_details,
                su.error_message,
                TIMESTAMPDIFF(SECOND, su.started_at, su.completed_at) as duration_seconds
            FROM system_updates su
            LEFT JOIN users u ON su.updated_by = u.id
            ORDER BY su.started_at DESC
            LIMIT 20";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        
        // Procesar detalles JSON y formatear datos
        foreach ($logs as &$log) {
            // Decodificar detalles si existen
            if (!empty($log['update_details'])) {
                $log['details_parsed'] = json_decode($log['update_details'], true);
            }
            
            // Formatear duración
            if ($log['duration_seconds']) {
                $seconds = $log['duration_seconds'];
                if ($seconds < 60) {
                    $log['duration_formatted'] = $seconds . 's';
                } else {
                    $minutes = floor($seconds / 60);
                    $secs = $seconds % 60;
                    $log['duration_formatted'] = "{$minutes}m {$secs}s";
                }
            } else {
                $log['duration_formatted'] = '-';
            }
            
            // Formatear commit hash (primeros 7 caracteres)
            if (!empty($log['from_commit'])) {
                $log['from_commit_short'] = substr($log['from_commit'], 0, 7);
            }
            if (!empty($log['to_commit'])) {
                $log['to_commit_short'] = substr($log['to_commit'], 0, 7);
            }
        }
        
        echo json_encode([
            'success' => true,
            'logs' => $logs,
            'total' => count($logs),
            'using_new_table' => $tableExists
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener logs: ' . $e->getMessage(),
            'logs' => []
        ]);
    }
}

/**
 * Obtener detalles de una actualización específica
 */
function getUpdateDetails($db) {
    try {
        $updateId = $_GET['id'] ?? $_POST['id'] ?? '';
        
        if (empty($updateId)) {
            throw new Exception('ID de actualización no especificado');
        }
        
        // Verificar si existe la nueva tabla
        $checkTable = "SHOW TABLES LIKE 'system_update_logs'";
        $stmt = $db->query($checkTable);
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            // Buscar en la nueva tabla
            $query = "SELECT 
                sul.*,
                TIMESTAMPDIFF(SECOND, sul.started_at, sul.completed_at) as duration_seconds
            FROM system_update_logs sul
            WHERE sul.id = ?";
        } else {
            // Fallback a tabla antigua
            $query = "SELECT 
                su.*,
                u.username,
                TIMESTAMPDIFF(SECOND, su.started_at, su.completed_at) as duration_seconds
            FROM system_updates su
            LEFT JOIN users u ON su.updated_by = u.id
            WHERE su.id = ?";
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute([$updateId]);
        $update = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$update) {
            throw new Exception('Actualización no encontrada');
        }
        
        // Decodificar detalles JSON
        $details = null;
        if (!empty($update['update_details'])) {
            $details = json_decode($update['update_details'], true);
        }
        
        // Formatear duración
        if ($update['duration_seconds']) {
            $seconds = $update['duration_seconds'];
            if ($seconds < 60) {
                $update['duration_formatted'] = $seconds . 's';
            } else {
                $minutes = floor($seconds / 60);
                $secs = $seconds % 60;
                $update['duration_formatted'] = "{$minutes}m {$secs}s";
            }
        }
        
        echo json_encode([
            'success' => true,
            'update' => $update,
            'details' => $details
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener detalles: ' . $e->getMessage()
        ]);
    }
}

/**
 * Migrar logs antiguos de system_updates a system_update_logs
 * Esta función solo se ejecuta una vez automáticamente
 */
function migrateLegacyLogs($db) {
    try {
        // Verificar si ya se migró
        $checkQuery = "SELECT COUNT(*) as count FROM system_update_logs";
        $stmt = $db->prepare($checkQuery);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return; // Ya hay logs migrados
        }
        
        // Migrar desde system_updates (solo los completados)
        $migrateQuery = "INSERT INTO system_update_logs 
            (update_version, from_commit, to_commit, status, started_at, completed_at, 
             user_id, username, files_added, files_updated, files_deleted, backup_path, 
             update_details, error_message)
        SELECT 
            CONCAT('v', SUBSTRING(to_commit, 1, 7)) as update_version,
            from_commit,
            to_commit,
            status,
            started_at,
            completed_at,
            updated_by as user_id,
            (SELECT username FROM users WHERE id = updated_by) as username,
            COALESCE(files_added, 0),
            COALESCE(files_updated, 0),
            COALESCE(files_deleted, 0),
            backup_path,
            update_details,
            error_message
        FROM system_updates
        WHERE status = 'completed'
        AND NOT EXISTS (
            SELECT 1 FROM system_update_logs 
            WHERE from_commit = system_updates.from_commit 
            AND to_commit = system_updates.to_commit
        )
        ORDER BY started_at ASC";
        
        $stmt = $db->prepare($migrateQuery);
        $stmt->execute();
        
        $migratedCount = $stmt->rowCount();
        
        // Registrar migración v2.3.6 si no existe
        $checkV236 = "SELECT COUNT(*) as count FROM system_update_logs 
                      WHERE update_version = 'v2.3.6'";
        $stmt = $db->prepare($checkV236);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $insertV236 = "INSERT INTO system_update_logs 
                (update_version, status, started_at, completed_at, username, 
                 files_added, files_updated, update_details)
            VALUES 
                ('v2.3.6', 'completed', NOW(), NOW(), 'Sistema', 
                 1, 0, 'Migración v2.3.6: Sistema Kardex y limpieza de base de datos')";
            $stmt = $db->prepare($insertV236);
            $stmt->execute();
            $migratedCount++;
        }
        
        error_log("Logs migrados exitosamente: {$migratedCount} registros");
        
        return $migratedCount;
        
    } catch (Exception $e) {
        error_log("Error al migrar logs: " . $e->getMessage());
        return 0;
    }
}

// AGREGAR TAMBIÉN AL FINAL DE performUpdate() para que registre en la nueva tabla:

/**
 * Actualización del registro de log al final de performUpdate()
 * REEMPLAZAR el bloque existente de actualización de log
 */
// Dentro de performUpdate(), buscar el bloque que actualiza system_updates
// y reemplazarlo por este:

// 8. Actualizar logs en AMBAS tablas (transición)
updateUpdateLog($db, $updateId, 'completed', 'Actualización completada exitosamente');

// Actualizar en system_updates (tabla legacy)
$hasDetailsColumn = false;
try {
    $checkColumn = $db->query("SHOW COLUMNS FROM system_updates LIKE 'update_details'");
    $hasDetailsColumn = $checkColumn->rowCount() > 0;
} catch (Exception $e) {
    $hasDetailsColumn = false;
}

if ($hasDetailsColumn) {
    $query = "UPDATE system_updates SET 
              completed_at = NOW(),
              from_commit = ?,
              to_commit = ?,
              files_added = ?,
              files_updated = ?,
              files_deleted = ?,
              update_details = ?
              WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $currentCommit,
        $newCommit,
        $fileStats['added'],
        $fileStats['modified'],
        $fileStats['removed'],
        $detailsJson,
        $updateId
    ]);
} else {
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
        $fileStats['added'],
        $fileStats['modified'],
        $fileStats['removed'],
        $updateId
    ]);
}

// TAMBIÉN registrar en la NUEVA tabla system_update_logs
try {
    $checkNewTable = "SHOW TABLES LIKE 'system_update_logs'";
    $stmt = $db->query($checkNewTable);
    
    if ($stmt->rowCount() > 0) {
        // Obtener versión actual
        $currentVersion = getSystemVersion($db);
        
        $insertNewLog = "INSERT INTO system_update_logs 
            (update_version, from_commit, to_commit, status, started_at, completed_at,
             user_id, username, files_added, files_updated, files_deleted, 
             backup_path, update_details)
        VALUES (?, ?, ?, 'completed', (SELECT started_at FROM system_updates WHERE id = ?), NOW(),
                ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $db->prepare($insertNewLog);
        $stmt->execute([
            'v' . $currentVersion,
            $currentCommit,
            $newCommit,
            $updateId,
            $_SESSION['user_id'],
            $_SESSION['username'] ?? 'Admin',
            $fileStats['added'],
            $fileStats['modified'],
            $fileStats['removed'],
            $backupResult['path'] ?? null,
            $detailsJson
        ]);
    }
} catch (Exception $e) {
    // Si falla, no es crítico, continuamos
    error_log("No se pudo registrar en system_update_logs: " . $e->getMessage());
}