<?php
/**
 * Funciones auxiliares para gestión de permisos dinámicos
 * Archivo: config/permission_functions.php
 */

/**
 * Agrega un nuevo permiso a la base de datos
 */
function addPermission($permission_key, $permission_name, $description, $icon = 'fas fa-key', $category = 'system') {
    global $db;
    
    try {
        $query = "INSERT INTO permissions (permission_key, permission_name, description, icon, category) 
                  VALUES (?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE 
                      permission_name = VALUES(permission_name),
                      description = VALUES(description),
                      icon = VALUES(icon),
                      category = VALUES(category)";
        
        $stmt = $db->prepare($query);
        return $stmt->execute([$permission_key, $permission_name, $description, $icon, $category]);
        
    } catch (Exception $e) {
        error_log("Error al agregar permiso: " . $e->getMessage());
        return false;
    }
}

/**
 * Desactiva un permiso (no lo elimina, solo lo oculta)
 */
function deactivatePermission($permission_key) {
    global $db;
    
    try {
        $query = "UPDATE permissions SET is_active = 0 WHERE permission_key = ?";
        $stmt = $db->prepare($query);
        return $stmt->execute([$permission_key]);
        
    } catch (Exception $e) {
        error_log("Error al desactivar permiso: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene todos los permisos de un rol específico
 */
function getRolePermissions($role_id) {
    global $db;
    
    try {
        $query = "SELECT permissions FROM roles WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$role_id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return json_decode($result['permissions'] ?? '[]', true);
        
    } catch (Exception $e) {
        error_log("Error al obtener permisos del rol: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica si un usuario tiene un permiso específico
 */
function userHasPermission($user_id, $permission_key) {
    global $db;
    
    try {
        $query = "SELECT r.permissions 
                  FROM users u 
                  JOIN roles r ON u.role_id = r.id 
                  WHERE u.id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $permissions = json_decode($result['permissions'] ?? '[]', true);
        
        // Si tiene 'all', tiene todos los permisos
        if (in_array('all', $permissions)) {
            return true;
        }
        
        return in_array($permission_key, $permissions);
        
    } catch (Exception $e) {
        error_log("Error al verificar permiso: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene permisos agrupados por categoría
 */
function getPermissionsByCategory() {
    global $db;
    
    try {
        $query = "SELECT permission_key, permission_name, description, icon, category 
                  FROM permissions 
                  WHERE is_active = 1 
                  ORDER BY category, sort_order, permission_key";
        
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $category = $row['category'];
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][$row['permission_key']] = [
                'name' => $row['permission_name'],
                'description' => $row['description'],
                'icon' => $row['icon']
            ];
        }
        
        return $grouped;
        
    } catch (Exception $e) {
        error_log("Error al agrupar permisos: " . $e->getMessage());
        return [];
    }
}

/**
 * Sincroniza permisos de roles después de agregar/remover permisos del sistema
 */
function syncRolePermissions() {
    global $db;
    
    try {
        // Obtener todos los permisos activos
        $query = "SELECT permission_key FROM permissions WHERE is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $active_permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $active_permissions[] = $row['permission_key'];
        }
        
        // Obtener todos los roles
        $roles_query = "SELECT id, permissions FROM roles";
        $roles_stmt = $db->prepare($roles_query);
        $roles_stmt->execute();
        
        $update_query = "UPDATE roles SET permissions = ? WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        
        // Limpiar permisos obsoletos de cada rol
        while ($role = $roles_stmt->fetch(PDO::FETCH_ASSOC)) {
            $role_permissions = json_decode($role['permissions'], true);
            
            // Filtrar solo permisos activos (excepto 'all')
            $cleaned_permissions = array_filter($role_permissions, function($perm) use ($active_permissions) {
                return $perm === 'all' || in_array($perm, $active_permissions);
            });
            
            // Actualizar si hubo cambios
            if (count($cleaned_permissions) != count($role_permissions)) {
                $update_stmt->execute([
                    json_encode(array_values($cleaned_permissions)),
                    $role['id']
                ]);
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error al sincronizar permisos: " . $e->getMessage());
        return false;
    }
}

/**
 * Exporta configuración de permisos a JSON
 */
function exportPermissionsConfig() {
    global $db;
    
    try {
        $query = "SELECT * FROM permissions WHERE is_active = 1 ORDER BY sort_order";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return json_encode([
            'version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'permissions' => $permissions
        ], JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        error_log("Error al exportar permisos: " . $e->getMessage());
        return false;
    }
}

/**
 * Importa configuración de permisos desde JSON
 */
function importPermissionsConfig($json_data) {
    global $db;
    
    try {
        $data = json_decode($json_data, true);
        
        if (!isset($data['permissions'])) {
            return false;
        }
        
        $query = "INSERT INTO permissions 
                  (permission_key, permission_name, description, icon, category, sort_order) 
                  VALUES (?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE 
                      permission_name = VALUES(permission_name),
                      description = VALUES(description),
                      icon = VALUES(icon),
                      category = VALUES(category),
                      sort_order = VALUES(sort_order)";
        
        $stmt = $db->prepare($query);
        
        foreach ($data['permissions'] as $perm) {
            $stmt->execute([
                $perm['permission_key'],
                $perm['permission_name'],
                $perm['description'],
                $perm['icon'],
                $perm['category'],
                $perm['sort_order']
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error al importar permisos: " . $e->getMessage());
        return false;
    }
}
?>