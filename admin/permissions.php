<?php
// admin/permissions.php - SISTEMA DINÁMICO DE PERMISOS
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();

// Solo administradores pueden acceder
if ($_SESSION['role_name'] !== 'administrador') {
    header('Location: pages/403.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Obtener configuraciones del sistema
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Información del usuario actual
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role_name'] ?? 'usuario';

// =============================================
// FUNCIÓN PARA OBTENER PERMISOS DESDE BD
// =============================================
function getAvailablePermissions() {
    global $db;
    
    try {
        // Verificar si existe la tabla permissions
        $check_table = "SHOW TABLES LIKE 'permissions'";
        $stmt = $db->prepare($check_table);
        $stmt->execute();
        
        if ($stmt->rowCount() == 0) {
            // Si no existe la tabla, usar permisos por defecto
            return getDefaultPermissions();
        }
        
        // Obtener permisos activos ordenados
        $query = "SELECT permission_key, permission_name, description, icon, category 
                  FROM permissions 
                  WHERE is_active = 1 
                  ORDER BY sort_order, permission_key";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $permissions = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $permissions[$row['permission_key']] = [
                'name' => $row['permission_name'],
                'description' => $row['description'],
                'icon' => $row['icon'],
                'category' => $row['category']
            ];
        }
        
        // Si no hay permisos en BD, usar defaults
        if (empty($permissions)) {
            return getDefaultPermissions();
        }
        
        return $permissions;
        
    } catch (Exception $e) {
        error_log("Error al obtener permisos: " . $e->getMessage());
        return getDefaultPermissions();
    }
}

// =============================================
// PERMISOS POR DEFECTO (FALLBACK)
// =============================================
function getDefaultPermissions() {
    return [
        'all' => [
            'name' => 'Acceso Completo',
            'description' => 'Acceso completo al sistema (solo administradores)',
            'icon' => 'fas fa-crown',
            'category' => 'system'
        ],
        'settings' => [
            'name' => 'Configuración',
            'description' => 'Configuración general del sistema',
            'icon' => 'fas fa-cog',
            'category' => 'system'
        ],
        'orders' => [
            'name' => 'Órdenes Tradicionales',
            'description' => 'Gestión de órdenes tradicionales (mesa, delivery, takeout)',
            'icon' => 'fas fa-receipt',
            'category' => 'orders'
        ],
        'online_orders' => [
            'name' => 'Pedidos Online',
            'description' => 'Gestión de pedidos online del sitio web',
            'icon' => 'fas fa-globe',
            'category' => 'orders'
        ],
        'products' => [
            'name' => 'Productos',
            'description' => 'Gestión de productos y categorías',
            'icon' => 'fas fa-utensils',
            'category' => 'management'
        ],
        'users' => [
            'name' => 'Usuarios',
            'description' => 'Gestión de usuarios y roles',
            'icon' => 'fas fa-users',
            'category' => 'management'
        ],
        'tables' => [
            'name' => 'Mesas',
            'description' => 'Gestión de mesas y reservas',
            'icon' => 'fas fa-table',
            'category' => 'management'
        ],
        'reports' => [
            'name' => 'Reportes',
            'description' => 'Reportes y estadísticas del sistema',
            'icon' => 'fas fa-chart-bar',
            'category' => 'reports'
        ],
        'kitchen' => [
            'name' => 'Cocina',
            'description' => 'Panel de cocina - ver y actualizar órdenes',
            'icon' => 'fas fa-fire',
            'category' => 'operations'
        ],
        'delivery' => [
            'name' => 'Delivery',
            'description' => 'Gestión de entregas a domicilio',
            'icon' => 'fas fa-motorcycle',
            'category' => 'operations'
        ],
        'kardex' => [
            'name' => 'Kardex de Inventario',
            'description' => 'Control de movimientos de inventario y stock',
            'icon' => 'fas fa-boxes',
            'category' => 'management'
        ],
        'bulk_edit' => [
            'name' => 'Edición Masiva',
            'description' => 'Edición masiva de productos',
            'icon' => 'fas fa-edit',
            'category' => 'management'
        ]
    ];
}

// Obtener permisos disponibles
$available_permissions = getAvailablePermissions();

// Roles predefinidos con sus permisos típicos
$default_role_permissions = [
    'administrador' => ['all'],
    'gerente' => ['orders', 'online_orders', 'products', 'users', 'reports', 'tables', 'kitchen', 'delivery', 'kardex', 'bulk_edit'],
    'mostrador' => ['orders', 'online_orders', 'products', 'tables', 'kitchen', 'delivery'],
    'mesero' => ['orders', 'tables'],
    'cocina' => ['kitchen', 'online_orders'],
    'delivery' => ['delivery']
];

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_role_permissions':
            $result = updateRolePermissions();
            break;
        case 'update_user_permissions':
            $result = updateUserPermissions();
            break;
        case 'create_role':
            $result = createRole();
            break;
        case 'delete_role':
            $result = deleteRole();
            break;
        case 'reset_role_permissions':
            $result = resetRolePermissions();
            break;
    }
    
    if (isset($result['success']) && $result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'] ?? 'Error desconocido';
    }
}

function updateRolePermissions() {
    global $db;
    
    $role_id = intval($_POST['role_id']);
    $permissions = $_POST['permissions'] ?? [];
    
    if (empty($role_id)) {
        return ['success' => false, 'message' => 'ID de rol requerido'];
    }
    
    // Validar que 'all' no se combine con otros permisos
    if (in_array('all', $permissions) && count($permissions) > 1) {
        $permissions = ['all']; // Solo 'all'
    }
    
    try {
        $permissions_json = json_encode($permissions);
        
        $query = "UPDATE roles SET permissions = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$permissions_json, $role_id])) {
            return ['success' => true, 'message' => 'Permisos del rol actualizados exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar permisos del rol'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateUserPermissions() {
    global $db;
    
    $user_id = intval($_POST['user_id']);
    $new_role_id = intval($_POST['new_role_id']);
    
    if (empty($user_id) || empty($new_role_id)) {
        return ['success' => false, 'message' => 'Datos requeridos faltantes'];
    }
    
    // Prevent changing own role
    if ($user_id == $_SESSION['user_id']) {
        return ['success' => false, 'message' => 'No puedes cambiar tu propio rol'];
    }
    
    try {
        $query = "UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$new_role_id, $user_id])) {
            return ['success' => true, 'message' => 'Rol del usuario actualizado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar rol del usuario'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function createRole() {
    global $db;
    
    $name = sanitize($_POST['role_name']);
    $description = sanitize($_POST['role_description']);
    $permissions = $_POST['role_permissions'] ?? [];
    
    if (empty($name)) {
        return ['success' => false, 'message' => 'El nombre del rol es requerido'];
    }
    
    // Validar que 'all' no se combine con otros permisos
    if (in_array('all', $permissions) && count($permissions) > 1) {
        $permissions = ['all']; // Solo 'all'
    }
    
    try {
        // Check if role name already exists
        $check_query = "SELECT COUNT(*) FROM roles WHERE name = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$name]);
        
        if ($check_stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'El nombre del rol ya existe'];
        }
        
        $permissions_json = json_encode($permissions);
        
        $query = "INSERT INTO roles (name, description, permissions, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$name, $description, $permissions_json])) {
            return ['success' => true, 'message' => 'Rol creado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al crear el rol'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function deleteRole() {
    global $db;
    
    $role_id = intval($_POST['role_id']);
    
    if (empty($role_id)) {
        return ['success' => false, 'message' => 'ID de rol requerido'];
    }
    
    // Check if role has users assigned
    $users_query = "SELECT COUNT(*) FROM users WHERE role_id = ?";
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute([$role_id]);
    
    if ($users_stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'No se puede eliminar un rol que tiene usuarios asignados'];
    }
    
    try {
        $query = "DELETE FROM roles WHERE id = ? AND name NOT IN ('administrador', 'gerente', 'mesero', 'cocina', 'delivery', 'mostrador')";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$role_id])) {
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Rol eliminado exitosamente'];
            } else {
                return ['success' => false, 'message' => 'No se puede eliminar un rol del sistema'];
            }
        } else {
            return ['success' => false, 'message' => 'Error al eliminar el rol'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function resetRolePermissions() {
    global $db, $default_role_permissions;
    
    $role_name = $_POST['role_name'];
    
    if (!isset($default_role_permissions[$role_name])) {
        return ['success' => false, 'message' => 'Rol no válido para resetear'];
    }
    
    try {
        $permissions_json = json_encode($default_role_permissions[$role_name]);
        
        $query = "UPDATE roles SET permissions = ?, updated_at = NOW() WHERE name = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$permissions_json, $role_name])) {
            return ['success' => true, 'message' => "Permisos del rol '$role_name' restablecidos a los valores por defecto"];
        } else {
            return ['success' => false, 'message' => 'Error al restablecer permisos del rol'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Load roles and users
$roles_query = "SELECT r.*, COUNT(u.id) as user_count 
                FROM roles r 
                LEFT JOIN users u ON r.id = u.role_id 
                GROUP BY r.id 
                ORDER BY r.name";
$roles_stmt = $db->prepare($roles_query);
$roles_stmt->execute();
$roles = $roles_stmt->fetchAll();

$users_query = "SELECT u.*, r.name as role_name, r.permissions 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.is_active = 1 
                ORDER BY u.full_name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll();

// Agrupar permisos por categoría para mejor visualización
$permissions_by_category = [];
foreach ($available_permissions as $key => $perm) {
    $category = $perm['category'] ?? 'system';
    if (!isset($permissions_by_category[$category])) {
        $permissions_by_category[$category] = [];
    }
    $permissions_by_category[$category][$key] = $perm;
}

$category_names = [
    'system' => 'Sistema',
    'orders' => 'Órdenes',
    'management' => 'Gestión',
    'reports' => 'Reportes',
    'operations' => 'Operaciones'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Tema dinámico -->
    <?php if (file_exists('../assets/css/generate-theme.php')): ?>
        <link rel="stylesheet" href="../assets/css/generate-theme.php?v=<?php echo time(); ?>">
    <?php endif; ?>

    <?php
    // Incluir sistema de temas
    $theme_file = '../config/theme.php';
    if (file_exists($theme_file)) {
        require_once $theme_file;
        $database_theme = new Database();
        $db_theme = $database_theme->getConnection();
        $theme_manager = new ThemeManager($db_theme);
        $current_theme = $theme_manager->getThemeSettings();
    } else {
        $current_theme = array(
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'sidebar_width' => '280px'
        );
    }
    ?>
    
   <style>
/* Extensiones específicas de permissions */
:root {
    --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --permissions-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
}

/* Mobile Top Bar */
.mobile-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: var(--primary-gradient);
    color: var(--text-white) !important;
    padding: 1rem;
    display: none;
}

.menu-toggle {
    background: none;
    border: none;
    color: var(--text-white) !important;
    font-size: 1.2rem;
    padding: 0.5rem;
    border-radius: var(--border-radius-base);
    transition: var(--transition-base);
}

.menu-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
}

.page-header {
    background: var(--primary-gradient) !important;
    color: var(--text-white, white) !important;
    border-radius: var(--border-radius-large);
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
    border: 1px solid rgba(0,0,0,0.05);
}

/* Sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--permissions-sidebar-width);
    height: 100vh;
    background: var(--primary-gradient);
    color: var(--text-white) !important;
    z-index: 1030;
    transition: transform var(--transition-base);
    overflow-y: auto;
    padding: 1.5rem;
}

.sidebar-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1020;
    display: none;
    opacity: 0;
    transition: opacity var(--transition-base);
}

.sidebar-backdrop.show {
    display: block;
    opacity: 1;
}

.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8) !important;
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius-base);
    margin-bottom: 0.25rem;
    transition: var(--transition-base);
    display: flex;
    align-items: center;
    text-decoration: none;
}

.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-white) !important;
}

.sidebar-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: var(--text-white) !important;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

/* Main content - colores claros forzados */
.main-content {
    margin-left: var(--permissions-sidebar-width);
    padding: 2rem;
    min-height: 100vh;
    transition: margin-left var(--transition-base);
    background: #f8f9fa !important;
    color: #212529 !important;
}

/* Contenido forzado a colores claros */
.permissions-section {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
    margin-bottom: 2rem;
}

.role-card {
    border-left: 4px solid var(--primary-color);
    transition: var(--transition-base);
    background: #ffffff !important;
    color: #212529 !important;
}

.role-card:hover {
    box-shadow: var(--shadow-base);
    transform: translateY(-2px);
}

.permission-badge {
    font-size: 0.75rem;
    margin: 2px;
    background: var(--text-secondary) !important;
    color: var(--text-white) !important;
}

.permission-check {
    margin: 0.25rem 0;
}

.nav-tabs {
    border-bottom: none;
    background: #f8f9fa !important;
    border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
}

.nav-tabs .nav-link {
    border-radius: 0;
    border: none;
    color: #6c757d !important;
    font-weight: 600;
    padding: 1.25rem 1.5rem;
    background: transparent;
    transition: var(--transition-base);
}

.nav-tabs .nav-link.active {
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)) !important;
    color: var(--text-white) !important;
    border: none;
}

.nav-tabs .nav-link:hover {
    color: var(--primary-color) !important;
}

.tab-content {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: 0 0 var(--border-radius-large) var(--border-radius-large);
    padding: 2rem;
}

.system-role {
    border-left-color: var(--success-color) !important;
}

.custom-role {
    border-left-color: var(--warning-color) !important;
}

.warning-text {
    color: var(--danger-color) !important;
    font-size: 0.9rem;
}

/* Cards y elementos con colores claros */
.card {
    background: #ffffff !important;
    color: #212529 !important;
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-small);
}

.card-header {
    background: #f8f9fa !important;
    color: #212529 !important;
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
}

.card-body {
    background: #ffffff !important;
    color: #212529 !important;
    padding: 1.5rem;
}

/* Títulos y textos de cards */
.card h6,
.card .card-title {
    color: #212529 !important;
}

/* CORREGIDO: Textos de descripción visibles */
.card p,
.card .card-text {
    color: #495057 !important;
    font-size: 0.9rem !important;
}

.card-text small {
    color: #495057 !important;
}

/* Específicamente para la sección de permisos disponibles */
#permissions-list .card-body p {
    color: #495057 !important;
    font-size: 0.9rem !important;
    line-height: 1.4;
}

#permissions-list .card-text {
    color: #495057 !important;
}

/* Modal con colores claros */
.modal-content {
    background: #ffffff !important;
    color: #212529 !important;
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-large);
}

.modal-header {
    background: #f8f9fa !important;
    color: #212529 !important;
    border-bottom: 1px solid #dee2e6;
    border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
}

.modal-body {
    background: #ffffff !important;
    color: #212529 !important;
}

.modal-footer {
    background: #ffffff !important;
    border-top: 1px solid #dee2e6;
    border-radius: 0 0 var(--border-radius-large) var(--border-radius-large);
}

/* Table con colores claros */
.table {
    background: #ffffff !important;
    color: #212529 !important;
}

.table th,
.table td {
    color: #212529 !important;
    border-bottom: 1px solid #dee2e6;
}

.table th {
    background: #f8f9fa !important;
    color: #212529 !important;
}

.table-hover tbody tr:hover {
    background: rgba(0, 0, 0, 0.02) !important;
}

/* Forms con colores claros */
.form-control, .form-select {
    background: #ffffff !important;
    color: #212529 !important;
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius-base);
    transition: var(--transition-base);
}

.form-control:focus, .form-select:focus {
    background: #ffffff !important;
    color: #212529 !important;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.form-label {
    color: #212529 !important;
    font-weight: 500;
}

.form-text {
    color: #6c757d !important;
}

.form-check-label {
    color: #212529 !important;
}

/* Asegurar visibilidad en modales */
.modal-body .form-text,
.modal-body small,
.modal-body .text-muted {
    color: #6c757d !important;
}

/* Labels y textos en forms */
.form-check-label small.text-muted {
    color: #6c757d !important;
}

/* Alerts con colores claros */
.alert {
    border-radius: var(--border-radius-base);
    border: none;
}

.alert-success {
    background: rgba(40, 167, 69, 0.1) !important;
    color: var(--success-color) !important;
    border-left: 4px solid var(--success-color);
}

.alert-danger {
    background: rgba(220, 53, 69, 0.1) !important;
    color: var(--danger-color) !important;
    border-left: 4px solid var(--danger-color);
}

.alert-warning {
    background: rgba(255, 193, 7, 0.1) !important;
    color: #856404 !important;
    border-left: 4px solid var(--warning-color);
}

.alert-info {
    background: rgba(23, 162, 184, 0.1) !important;
    color: var(--info-color) !important;
    border-left: 4px solid var(--info-color);
}

/* Text colors forzados */
.text-muted {
    color: #6c757d !important;
}

h1, h2, h3, h4, h5, h6 {
    color: #212529 !important;
}

p {
    color: #212529 !important;
}

/* Badges con tema dinámico */
.badge.bg-primary {
    background: var(--primary-color) !important;
    color: var(--text-white) !important;
}

.badge.bg-success {
    background: var(--success-color) !important;
    color: var(--text-white) !important;
}

.badge.bg-warning {
    background: var(--warning-color) !important;
    color: #212529 !important;
}

.badge.bg-danger {
    background: var(--danger-color) !important;
    color: var(--text-white) !important;
}

.badge.bg-info {
    background: var(--info-color) !important;
    color: var(--text-white) !important;
}

.badge.bg-secondary {
    background: var(--text-secondary) !important;
    color: var(--text-white) !important;
}

/* Buttons con tema dinámico */
.btn-primary {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: var(--text-white) !important;
}

.btn-primary:hover {
    background: var(--secondary-color) !important;
    border-color: var(--secondary-color) !important;
    color: var(--text-white) !important;
}

.btn-success {
    background: var(--success-color) !important;
    border-color: var(--success-color) !important;
    color: var(--text-white) !important;
}

.btn-warning {
    background: var(--warning-color) !important;
    border-color: var(--warning-color) !important;
    color: #212529 !important;
}

.btn-outline-primary {
    color: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    background: transparent;
}

.btn-outline-primary:hover {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: var(--text-white) !important;
}

.btn-outline-warning {
    color: var(--warning-color) !important;
    border-color: var(--warning-color) !important;
    background: transparent;
}

.btn-outline-warning:hover {
    background: var(--warning-color) !important;
    border-color: var(--warning-color) !important;
    color: #212529 !important;
}

.btn-outline-danger {
    color: var(--danger-color) !important;
    border-color: var(--danger-color) !important;
    background: transparent;
}

.btn-outline-danger:hover {
    background: var(--danger-color) !important;
    border-color: var(--danger-color) !important;
    color: var(--text-white) !important;
}

/* Responsive adjustments */
@media (max-width: 991.98px) {
    .mobile-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar {
        transform: translateX(-100%);
        width: 100%;
        max-width: 350px;
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .sidebar-close {
        display: flex;
    }

    .main-content {
        margin-left: 0;
        padding: 1rem;
        padding-top: 5rem;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 0.5rem;
        padding-top: 4.5rem;
    }

    .tab-content {
        padding: 1rem;
    }
}
.system-header .container-fluid {
    height: 60px;
    display: flex
;
    align-items: center;
    padding: 0 1rem;
    background-color: white;
}
.dropdown-menu.show {
    display: block;
    background: var(--primary-gradient);
}

.dropdown-header {
    padding: 0.75rem 1rem;
    background: var(--primary-gradient) !important;
    border-radius: 10px 10px 0 0;
}
</style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        Gestión de Permisos
                    </h2>
                    <p class="text-muted mb-0">Administra roles y permisos del sistema</p>
                </div>
                <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                    <i class="fas fa-plus me-2"></i>
                    Crear Rol
                </button>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Permissions Tabs -->
        <div class="permissions-section">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button">
                        <i class="fas fa-user-tag me-2"></i>Roles
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-permissions" type="button">
                        <i class="fas fa-users me-2"></i>Usuarios
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions-list" type="button">
                        <i class="fas fa-list me-2"></i>Permisos Disponibles
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <!-- Roles Tab -->
                <div class="tab-pane fade show active" id="roles" role="tabpanel">
                    <!-- EL CONTENIDO DE ROLES SE MANTIENE IGUAL -->
                    <!-- ... (copiar del archivo original) ... -->
                </div>

                <!-- Users Tab -->
                <div class="tab-pane fade" id="users-permissions" role="tabpanel">
                    <!-- EL CONTENIDO DE USERS SE MANTIENE IGUAL -->
                    <!-- ... (copiar del archivo original) ... -->
                </div>

                <!-- Available Permissions - ACTUALIZADO CON CATEGORÍAS -->
                <div class="tab-pane fade" id="permissions-list" role="tabpanel">
                    <h5 class="mb-4">Permisos Disponibles en el Sistema</h5>
                    
                    <?php foreach ($permissions_by_category as $category => $perms): ?>
                        <h6 class="text-uppercase text-muted mt-4 mb-3">
                            <i class="fas fa-folder me-2"></i>
                            <?php echo $category_names[$category] ?? ucfirst($category); ?>
                        </h6>
                        <div class="row mb-4">
                            <?php foreach ($perms as $perm_key => $perm): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <span class="badge bg-secondary me-2">
                                                    <i class="<?php echo $perm['icon']; ?> me-1"></i>
                                                    <?php echo $perm_key; ?>
                                                </span>
                                            </h6>
                                            <strong><?php echo $perm['name']; ?></strong>
                                            <p class="card-text small mb-0"><?php echo $perm['description']; ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="alert alert-info mt-4">
                        <h6><i class="fas fa-info-circle me-2"></i>Información sobre Permisos</h6>
                        <ul class="mb-0">
                            <li><strong>all:</strong> Otorga acceso completo al sistema. No debe combinarse con otros permisos.</li>
                            <li><strong>online_orders:</strong> Permite gestionar pedidos del sitio web público.</li>
                            <li><strong>orders:</strong> Permite gestionar órdenes tradicionales (mesa, delivery, takeout).</li>
                            <li><strong>kitchen:</strong> Acceso al panel de cocina para ver y actualizar estado de preparación.</li>
                            <li><strong>delivery:</strong> Gestión específica de entregas a domicilio.</li>
                            <li><strong>kardex:</strong> Control completo de inventario y movimientos de stock.</li>
                            <li><strong>bulk_edit:</strong> Permite editar múltiples productos simultáneamente.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modals y Scripts se mantienen igual -->
    <!-- ... (copiar del archivo original) ... -->
    
    <?php include 'footer.php'; ?>
</body>
</html>