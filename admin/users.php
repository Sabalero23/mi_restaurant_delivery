<?php
// admin/users.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('users');

$message = '';
$error = '';

// Obtener configuraciones del sistema
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// AGREGAR ESTAS LÍNEAS:
// Obtener información del usuario actual
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role_name'] ?? 'usuario';

// Verificar si hay estadísticas disponibles (opcional)
$stats = array();
$online_stats = array();

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
            $result = createUser();
            break;
        case 'update':
            $result = updateUser();
            break;
        case 'delete':
            $result = deleteUser();
            break;
        case 'toggle_status':
            $result = toggleUserStatus();
            break;
        case 'change_password':
            $result = changeUserPassword();
            break;
    }
    
    if (isset($result['success']) && $result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'] ?? 'Error desconocido';
    }
}

function createUser() {
    global $auth;
    
    // Validate input
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $role_id = intval($_POST['role_id']);
    
    if (empty($username) || empty($email) || empty($password) || empty($full_name) || empty($role_id)) {
        return ['success' => false, 'message' => 'Todos los campos son requeridos'];
    }
    
    if (!isValidEmail($email)) {
        return ['success' => false, 'message' => 'Email inválido'];
    }
    
    if (strlen($password) < 6) {
        return ['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres'];
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if username or email already exists
        $check_query = "SELECT COUNT(*) FROM users WHERE username = ? OR email = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$username, $email]);
        
        if ($check_stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'El usuario o email ya existe'];
        }
        
        // Create user
        $insert_query = "INSERT INTO users (username, email, password, full_name, phone, role_id, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, NOW())";
        
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $insert_stmt = $db->prepare($insert_query);
        
        if ($insert_stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $role_id])) {
            return ['success' => true, 'message' => 'Usuario creado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al crear el usuario'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateUser() {
    $user_id = intval($_POST['user_id']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $full_name = sanitize($_POST['full_name']);
    $phone = sanitize($_POST['phone']);
    $role_id = intval($_POST['role_id']);
    
    if (empty($username) || empty($email) || empty($full_name) || empty($role_id)) {
        return ['success' => false, 'message' => 'Todos los campos son requeridos'];
    }
    
    if (!isValidEmail($email)) {
        return ['success' => false, 'message' => 'Email inválido'];
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if username or email already exists (excluding current user)
        $check_query = "SELECT COUNT(*) FROM users WHERE (username = ? OR email = ?) AND id != ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$username, $email, $user_id]);
        
        if ($check_stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'El usuario o email ya existe'];
        }
        
        // Update user
        $update_query = "UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, role_id = ?, updated_at = NOW() 
                        WHERE id = ?";
        
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([$username, $email, $full_name, $phone, $role_id, $user_id])) {
            return ['success' => true, 'message' => 'Usuario actualizado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar el usuario'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function deleteUser() {
    $user_id = intval($_POST['user_id']);
    
    // Prevent deleting own account
    if ($user_id == $_SESSION['user_id']) {
        return ['success' => false, 'message' => 'No puedes eliminar tu propia cuenta'];
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if user has orders (soft delete in that case)
        $orders_query = "SELECT COUNT(*) FROM orders WHERE waiter_id = ? OR created_by = ?";
        $orders_stmt = $db->prepare($orders_query);
        $orders_stmt->execute([$user_id, $user_id]);
        
        if ($orders_stmt->fetchColumn() > 0) {
            // Soft delete - mark as inactive
            $delete_query = "UPDATE users SET is_active = 0, updated_at = NOW() WHERE id = ?";
            $message = 'Usuario desactivado (tenía órdenes asociadas)';
        } else {
            // Hard delete
            $delete_query = "DELETE FROM users WHERE id = ?";
            $message = 'Usuario eliminado exitosamente';
        }
        
        $delete_stmt = $db->prepare($delete_query);
        
        if ($delete_stmt->execute([$user_id])) {
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => 'Error al eliminar el usuario'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function toggleUserStatus() {
    $user_id = intval($_POST['user_id']);
    $current_status = intval($_POST['current_status']);
    $new_status = $current_status ? 0 : 1;
    
    // Prevent deactivating own account
    if ($user_id == $_SESSION['user_id'] && $new_status == 0) {
        return ['success' => false, 'message' => 'No puedes desactivar tu propia cuenta'];
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $update_query = "UPDATE users SET is_active = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([$new_status, $user_id])) {
            $status_text = $new_status ? 'activado' : 'desactivado';
            return ['success' => true, 'message' => "Usuario {$status_text} exitosamente"];
        } else {
            return ['success' => false, 'message' => 'Error al cambiar el estado del usuario'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function changeUserPassword() {
    $user_id = intval($_POST['user_id']);
    $new_password = $_POST['new_password'];
    
    if (strlen($new_password) < 6) {
        return ['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres'];
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $update_query = "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([$hashed_password, $user_id])) {
            return ['success' => true, 'message' => 'Contraseña actualizada exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar la contraseña'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Get users and roles
$database = new Database();
$db = $database->getConnection();

$users_query = "SELECT u.*, r.name as role_name, r.description as role_description
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                ORDER BY u.created_at DESC";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll();

$roles_query = "SELECT * FROM roles ORDER BY name";
$roles_stmt = $db->prepare($roles_query);
$roles_stmt->execute();
$roles = $roles_stmt->fetchAll();

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Get user role for sidebar customization
$role = $_SESSION['role_name'];
$user_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, maximum-scale=1.0">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#667eea">
    <title>Gestión de Usuarios - <?php echo $restaurant_name; ?></title>
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
    try {
        $database = new Database();
        $db = $database->getConnection();
        $theme_manager = new ThemeManager($db);
        $current_theme = $theme_manager->getThemeSettings();
    } catch (Exception $e) {
        $current_theme = array(
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'sidebar_width' => '280px'
        );
    }
} else {
    $current_theme = array(
        'primary_color' => '#667eea',
        'secondary_color' => '#764ba2',
        'sidebar_width' => '280px'
    );
}
?>
    <style>
        :root {
    --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --users-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
    --header-height: 70px;
    --border-radius: var(--border-radius-large, 12px);
}

        * {
            box-sizing: border-box;
        }

        body {
            background: #f8f9fa;
            overflow-x: hidden;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Mobile optimizations */
        body.mobile-menu-open {
            overflow: hidden;
            position: fixed;
            width: 100%;
        }

        /* Mobile Top Bar */
        .mobile-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            background: var(--primary-gradient);
            color: white;
            padding: 0.75rem 1rem;
            display: none;
            height: var(--header-height);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .mobile-topbar h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.25rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 44px;
            height: 44px;
        }

        .menu-toggle:hover,
        .menu-toggle:focus {
            background: rgba(255, 255, 255, 0.15);
            outline: none;
        }

        .menu-toggle:active {
            transform: scale(0.95);
        }

        /* Sidebar */
        .sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--users-sidebar-width);
    height: 100vh;
    background: var(--primary-gradient);
    color: var(--text-white) !important;
    z-index: 1030;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-y: auto;
    padding: 1.5rem;
    -webkit-overflow-scrolling: touch;
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
            transition: opacity 0.3s ease;
        }

        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 0.875rem 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 0.25rem;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            text-decoration: none;
            font-weight: 500;
            min-height: 48px;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.15);
            color: white;
            transform: translateX(4px);
        }

        .sidebar .nav-link i {
            width: 20px;
            text-align: center;
        }

        .sidebar-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.2s;
        }

        .sidebar-close:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        /* Main content */
        .main-content {
    margin-left: var(--users-sidebar-width);
    padding: 1.5rem;
    min-height: 100vh;
    transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: #f8f9fa !important;
    color: #212529 !important;
}



/* Forzar colores claros para el contenido */
.stat-card,
.card,

.user-mobile-card {
    background: #ffffff !important;
    color: #212529 !important;
    border: 1px solid rgba(0,0,0,0.05);
}

.card-header {
    background: #ffffff !important;
    color: #212529 !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.table {
    background: #ffffff !important;
    color: #212529 !important;
}

.table th,
.table td {
    color: #212529 !important;
    border-bottom: 1px solid #f0f0f0;
}

.table th {
    background: #f8f9fa !important;
    color: #212529 !important;
    border-bottom: 2px solid #dee2e6;
}

.text-muted {
    color: #6c757d !important;
}

h1, h2, h3, h4, h5, h6 {
    color: #212529 !important;
}

.form-control,
.form-select {
    background: #ffffff !important;
    color: #212529 !important;
    border: 1px solid #d1d5db;
}

.form-control:focus,
.form-select:focus {
    background: #ffffff !important;
    color: #212529 !important;
}

.modal-content {
    background: #ffffff !important;
    color: #212529 !important;
}

.modal-header,
.modal-body,
.modal-footer {
    background: #ffffff !important;
    color: #212529 !important;
}

.dropdown-menu {
    background: #ffffff !important;
    color: #212529 !important;
}

.dropdown-item {
    color: #212529 !important;
}

.filter-container {
    background: #f8f9fa !important;
    color: #212529 !important;
}

        /* Enhanced touch targets for mobile */
        .btn {
            min-height: 44px;
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn:active {
            transform: scale(0.98);
        }

        .btn-sm {
            min-height: 36px;
            padding: 0.375rem 0.75rem;
        }

        /* Statistics cards */
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.25rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            height: 100%;
            text-align: center;
            border: 1px solid rgba(0,0,0,0.05);
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            line-height: 1;
        }

        .page-header {
            background: var(--primary-gradient) !important;
            color: var(--text-white, white) !important;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* Card improvements */
        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .card-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: var(--border-radius) var(--border-radius) 0 0 !important;
            background: white !important;
            padding: 1.25rem 1.5rem;
            font-weight: 600;
        }
        
        .card-body {
    background: #ffffff !important;
    color: #212529 !important;
    padding: 1.5rem;
}

.card-title {
    color: #212529 !important;
}

.card-text {
    color: #212529 !important;
}

/* Asegurar que todos los elementos dentro de cards sean blancos */
div.card-header,
div.card-body {
    background-color: #ffffff !important;
    background: #ffffff !important;
    color: #212529 !important;
}

.card .card-header *,
.card .card-body * {
    color: inherit !important;
}


        .user-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }

        .user-card:hover {
            box-shadow: 0 4px 20px rgba(0,0,0,0.12);
        }

        .user-inactive {
            opacity: 0.6;
            border-left-color: #6c757d;
        }

        .role-badge {
            font-size: 0.75rem;
            padding: 0.375rem 0.625rem;
            border-radius: 6px;
            font-weight: 600;
        }

        /* Enhanced table for mobile */
        .table-responsive {
            border-radius: var(--border-radius);
            border: 1px solid rgba(0,0,0,0.05);
            margin: 0;
        }

        .table {
            margin-bottom: 0;
            font-size: 0.9rem;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            padding: 1rem 0.75rem;
        }

        .table td {
            padding: 1rem 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Mobile card view for users */
        .user-mobile-card {
            display: none;
            background: white;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            padding: 1.25rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.2s;
        }

        .user-mobile-card:active {
            transform: scale(0.98);
        }

        /* Modal improvements */
        .modal-dialog {
            margin: 1rem;
        }

        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            padding: 1.25rem 1.5rem;
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            padding: 1rem 1.5rem;
        }

        /* Form improvements */
        .form-control,
        .form-select {
            border-radius: var(--border-radius);
            border: 1px solid #d1d5db;
            padding: 0.75rem;
            font-size: 0.95rem;
            transition: all 0.2s;
            min-height: 48px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        /* Dropdown improvements */
        .dropdown-menu {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 6px 25px rgba(0, 0, 0, 0.15);
            padding: 0.5rem 0;
        }

        .dropdown-item {
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .dropdown-item:hover {
            background: #f8f9fa;
        }

        /* Filter buttons */
        .filter-container {
            margin: 1rem 0;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: var(--border-radius);
            border: 1px solid rgba(0,0,0,0.05);
        }

        /* Alert improvements */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 1rem 1.25rem;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        /* Loading states */
        .btn.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn.loading::after {
            content: "";
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 1s linear infinite;
            margin-left: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Mobile responsive styles */
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
                padding-top: calc(var(--header-height) + 1rem);
            }

            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .stat-number {
                font-size: 1.75rem;
            }

            .page-header {
                padding: 1.25rem;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            /* Show mobile cards, hide table */
            .table-responsive {
                display: none;
            }

            .user-mobile-card {
                display: block;
            }

            /* Modal adjustments */
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100vw - 1rem);
            }

            .modal-body {
                padding: 1.25rem;
            }

            /* Form adjustments */
            .row .col-md-6 {
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.75rem;
                padding-top: calc(var(--header-height) + 0.75rem);
            }

            .stat-card {
                padding: 0.875rem;
            }

            .page-header {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .sidebar {
                padding: 1rem;
            }

            .sidebar .nav-link {
                padding: 0.75rem;
                font-size: 0.9rem;
            }

            .btn-group .btn {
                font-size: 0.8rem;
                padding: 0.375rem 0.5rem;
            }

            .user-mobile-card {
                padding: 1rem;
            }

            .modal-header,
            .modal-body,
            .modal-footer {
                padding: 1rem;
            }
        }

        /* Very small screens */
        @media (max-width: 390px) {
            .main-content {
                padding: 0.5rem;
                padding-top: calc(var(--header-height) + 0.5rem);
            }

            .stat-card {
                padding: 0.75rem;
            }

            .stat-number {
                font-size: 1.25rem;
            }

            .btn {
                font-size: 0.8rem;
                padding: 0.5rem 0.75rem;
            }

            .user-mobile-card {
                padding: 0.875rem;
            }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background: #1a1a1a;
                color: #e5e5e5;
            }

            .stat-card,
            .card,
            .page-header,
            .user-mobile-card {
                background: #2d2d2d;
                color: #e5e5e5;
                border-color: #404040;
            }

            .card-header {
                background: #2d2d2d !important;
                border-bottom-color: #404040;
            }

            .text-muted {
                color: #fff !important;
            }

            .table {
                color: #e5e5e5;
            }

            .table th {
                background: #333;
                border-color: #404040;
            }

            .table td {
                border-color: #404040;
            }

            .form-control,
            .form-select {
                background: #333;
                border-color: #404040;
                color: #e5e5e5;
            }

            .dropdown-menu {
                background: #2d2d2d;
                border-color: #404040;
            }

            .dropdown-item {
                color: #e5e5e5;
            }

            .dropdown-item:hover {
                background: #404040;
            }
        }

        /* Improved scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }

        /* Touch feedback */
        .btn:active,
        .nav-link:active,
        .card:active {
            transform: scale(0.98);
        }

        /* Accessibility improvements */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }

        /* High contrast mode */
        @media (prefers-contrast: high) {
            .card,
            .stat-card,
            .page-header {
                border: 2px solid #000;
            }

            .btn {
                border: 2px solid currentColor;
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
                        <i class="fas fa-users me-2"></i>
                        Gestión de Usuarios
                    </h2>
                    <p class="text-muted mb-0">Administra los usuarios del sistema</p>
                </div>
                <div class="d-none d-lg-block">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userModal" onclick="newUser()">
                        <i class="fas fa-plus me-2"></i>
                        Nuevo Usuario
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile New User Button -->
        <div class="d-lg-none mb-3">
            <button class="btn btn-success w-100" data-bs-toggle="modal" data-bs-target="#userModal" onclick="newUser()">
                <i class="fas fa-plus me-2"></i>
                Nuevo Usuario
            </button>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row g-3 g-md-4 mb-4">
            <?php
            $stats = [
                'total' => count($users),
                'active' => count(array_filter($users, fn($u) => $u['is_active'])),
                'inactive' => count(array_filter($users, fn($u) => !$u['is_active'])),
                'admins' => count(array_filter($users, fn($u) => $u['role_name'] === 'administrador'))
            ];
            ?>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-primary"><?php echo $stats['total']; ?></div>
                    <div class="text-muted">Total Usuarios</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $stats['active']; ?></div>
                    <div class="text-muted">Activos</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo $stats['inactive']; ?></div>
                    <div class="text-muted">Inactivos</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-info"><?php echo $stats['admins']; ?></div>
                    <div class="text-muted">Administradores</div>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-container d-lg-none">
            <div class="d-flex flex-wrap gap-2">
                <button type="button" class="btn btn-outline-secondary btn-sm filter-btn active" onclick="filterByRole('all')">
                    Todos
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm filter-btn" onclick="filterByRole('administrador')">
                    Admins
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm filter-btn" onclick="filterByRole('gerente')">
                    Gerentes
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm filter-btn" onclick="filterByRole('mesero')">
                    Meseros
                </button>
                <button type="button" class="btn btn-outline-secondary btn-sm filter-btn" onclick="filterByRole('cocina')">
                    Cocina
                </button>
            </div>
        </div>

        <!-- Users Table (Desktop) -->
        <div class="card d-none d-lg-block">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list me-2"></i>
                        Lista de Usuarios (<?php echo count($users); ?>)
                    </h5>
                    <div class="btn-group btn-group-sm" role="group">
                        <button type="button" class="btn btn-outline-secondary filter-btn active" onclick="filterByRole('all')">
                            Todos
                        </button>
                        <button type="button" class="btn btn-outline-secondary filter-btn" onclick="filterByRole('administrador')">
                            Admins
                        </button>
                        <button type="button" class="btn btn-outline-secondary filter-btn" onclick="filterByRole('gerente')">
                            Gerentes
                        </button>
                        <button type="button" class="btn btn-outline-secondary filter-btn" onclick="filterByRole('mesero')">
                            Meseros
                        </button>
                        <button type="button" class="btn btn-outline-secondary filter-btn" onclick="filterByRole('cocina')">
                            Cocina
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Nombre</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Creado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="<?php echo !$user['is_active'] ? 'table-secondary' : ''; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-2 flex-shrink-0">
                                                <i class="fas fa-user text-primary"></i>
                                            </div>
                                            <div>
                                                <strong class="d-block"><?php echo htmlspecialchars($user['username']); ?></strong>
                                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                    <span class="badge bg-info">Tú</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <div>
                                            <?php echo htmlspecialchars($user['full_name']); ?>
                                            <?php if ($user['phone']): ?>
                                                <br><small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i>
                                                    <?php echo htmlspecialchars($user['phone']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $role_colors = [
                                            'administrador' => 'danger',
                                            'gerente' => 'warning',
                                            'mostrador' => 'info',
                                            'mesero' => 'primary',
                                            'cocina' => 'success',
                                            'delivery' => 'secondary'
                                        ];
                                        $color = $role_colors[$user['role_name']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?> role-badge">
                                            <?php echo htmlspecialchars($user['role_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <span class="badge bg-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo formatDateTime($user['created_at']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                        type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="fas fa-cog"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <button class="dropdown-item" 
                                                                onclick="changePassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                            <i class="fas fa-key text-warning me-2"></i>Cambiar Contraseña
                                                        </button>
                                                    </li>
                                                    
                                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                        <li>
                                                            <button class="dropdown-item" 
                                                                    onclick="toggleStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban text-warning' : 'check text-success'; ?> me-2"></i>
                                                                <?php echo $user['is_active'] ? 'Desactivar' : 'Activar'; ?>
                                                            </button>
                                                        </li>
                                                        
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <button class="dropdown-item text-danger" 
                                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                                <i class="fas fa-trash me-2"></i>Eliminar
                                                            </button>
                                                        </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Mobile Users Cards -->
        <div class="d-lg-none">
            <?php foreach ($users as $user): ?>
                <div class="user-mobile-card <?php echo !$user['is_active'] ? 'user-inactive' : ''; ?>" data-role="<?php echo $user['role_name']; ?>">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 rounded-circle p-2 me-3">
                                <i class="fas fa-user text-primary"></i>
                            </div>
                            <div>
                                <h6 class="mb-1 fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                                <div class="text-muted small">@<?php echo htmlspecialchars($user['username']); ?></div>
                                <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                    <span class="badge bg-info mt-1">Tú</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <?php
                            $role_colors = [
                                'administrador' => 'danger',
                                'gerente' => 'warning', 
                                'mostrador' => 'info',
                                'mesero' => 'primary',
                                'cocina' => 'success',
                                'delivery' => 'secondary'
                            ];
                            $color = $role_colors[$user['role_name']] ?? 'secondary';
                            ?>
                            <span class="badge bg-<?php echo $color; ?> role-badge mb-2">
                                <?php echo htmlspecialchars($user['role_name']); ?>
                            </span>
                            <br>
                            <?php if ($user['is_active']): ?>
                                <span class="badge bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactivo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="small text-muted mb-1">
                            <i class="fas fa-envelope me-2"></i>
                            <?php echo htmlspecialchars($user['email']); ?>
                        </div>
                        <?php if ($user['phone']): ?>
                            <div class="small text-muted">
                                <i class="fas fa-phone me-2"></i>
                                <?php echo htmlspecialchars($user['phone']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                        </small>
                        
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary" 
                                    onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)"
                                    aria-label="Editar usuario">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        aria-label="Más opciones">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button class="dropdown-item" 
                                                onclick="changePassword(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                            <i class="fas fa-key text-warning me-2"></i>Cambiar Contraseña
                                        </button>
                                    </li>
                                    
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                        <li>
                                            <button class="dropdown-item" 
                                                    onclick="toggleStatus(<?php echo $user['id']; ?>, <?php echo $user['is_active']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban text-warning' : 'check text-success'; ?> me-2"></i>
                                                <?php echo $user['is_active'] ? 'Desactivar' : 'Activar'; ?>
                                            </button>
                                        </li>
                                        
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" 
                                                    onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                                <i class="fas fa-trash me-2"></i>Eliminar
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- User Modal -->
    <div class="modal fade" id="userModal" tabindex="-1" aria-labelledby="userModalTitle" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="userForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="userModalTitle">
                            <i class="fas fa-plus me-2"></i>
                            Nuevo Usuario
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="userAction" value="create">
                        <input type="hidden" name="user_id" id="userId">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="username">Nombre de Usuario *</label>
                                    <input type="text" class="form-control" name="username" id="username" required autocomplete="username">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="email">Email *</label>
                                    <input type="email" class="form-control" name="email" id="email" required autocomplete="email">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="fullName">Nombre Completo *</label>
                            <input type="text" class="form-control" name="full_name" id="fullName" required autocomplete="name">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="phone">Teléfono</label>
                                    <input type="tel" class="form-control" name="phone" id="phone" autocomplete="tel">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label" for="roleId">Rol *</label>
                                    <select class="form-select" name="role_id" id="roleId" required>
                                        <option value="">Seleccionar rol...</option>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?php echo $role['id']; ?>">
                                                <?php echo htmlspecialchars($role['name']); ?>
                                                <?php if ($role['description']): ?>
                                                    - <?php echo htmlspecialchars($role['description']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3" id="passwordField">
                            <label class="form-label" for="password">Contraseña *</label>
                            <input type="password" class="form-control" name="password" id="password" minlength="6" autocomplete="new-password">
                            <div class="form-text">Mínimo 6 caracteres</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" id="userSubmitBtn">
                            <i class="fas fa-save me-1"></i>
                            Crear Usuario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Password Modal -->
    <div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalTitle" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="passwordForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="passwordModalTitle">
                            <i class="fas fa-key me-2"></i>
                            Cambiar Contraseña
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="user_id" id="passwordUserId">
                        
                        <div class="mb-3">
                            <label class="form-label">Usuario:</label>
                            <strong id="passwordUsername"></strong>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label" for="newPassword">Nueva Contraseña *</label>
                            <input type="password" class="form-control" name="new_password" id="newPassword" minlength="6" required autocomplete="new-password">
                            <div class="form-text">Mínimo 6 caracteres</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-1"></i>
                            Cambiar Contraseña
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Hidden Forms for Actions -->
    <form id="statusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="user_id" id="statusUserId">
        <input type="hidden" name="current_status" id="currentStatus">
    </form>
    
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="user_id" id="deleteUserId">
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let isInitialized = false;

        // Initialize on DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            if (!isInitialized) {
                initializeApp();
                isInitialized = true;
            }
        });

        function initializeApp() {
            initializeMobileMenu();
            initializeModals();
            initializeForms();
            initializeAlerts();
            initializeTouch();
        }

        function initializeMobileMenu() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const sidebarClose = document.getElementById('sidebarClose');

            // Toggle mobile menu
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleSidebar();
                });
            }

            // Close sidebar when clicking close button
            if (sidebarClose) {
                sidebarClose.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeSidebar();
                });
            }

            // Close sidebar when clicking backdrop
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeSidebar();
                });
            }

            // Close sidebar when clicking a nav link on mobile
            document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        setTimeout(closeSidebar, 150);
                    }
                });
            });

            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    closeSidebar();
                }
            });

            // Handle keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                    closeSidebar();
                }
            });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const body = document.body;

            const isOpen = sidebar.classList.contains('show');
            
            if (isOpen) {
                closeSidebar();
            } else {
                sidebar.classList.add('show');
                sidebarBackdrop.classList.add('show');
                body.classList.add('mobile-menu-open');
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const body = document.body;
            
            if (sidebar) sidebar.classList.remove('show');
            if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
            body.classList.remove('mobile-menu-open');
        }

        function initializeModals() {
            // Reset forms when modals are hidden
            const userModal = document.getElementById('userModal');
            const passwordModal = document.getElementById('passwordModal');

            if (userModal) {
                userModal.addEventListener('hidden.bs.modal', function () {
                    resetUserForm();
                });
            }

            if (passwordModal) {
                passwordModal.addEventListener('hidden.bs.modal', function () {
                    document.getElementById('passwordForm').reset();
                    clearPasswordStrength();
                });
            }
        }

        function initializeForms() {
            // Form validation
            const userForm = document.getElementById('userForm');
            const passwordForm = document.getElementById('passwordForm');

            if (userForm) {
                userForm.addEventListener('submit', function(e) {
                    if (!validateUserForm()) {
                        e.preventDefault();
                        return false;
                    }
                    showButtonLoading('userSubmitBtn');
                });
            }

            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    if (!validatePasswordForm()) {
                        e.preventDefault();
                        return false;
                    }
                    showButtonLoading('passwordSubmitBtn');
                });
            }

            // Password strength indicator
            const passwordInput = document.getElementById('password');
            const newPasswordInput = document.getElementById('newPassword');

            if (passwordInput) {
                passwordInput.addEventListener('input', function() {
                    showPasswordStrength(this, 'password-strength-create');
                });
            }

            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    showPasswordStrength(this, 'password-strength-change');
                });
            }
        }

        function initializeAlerts() {
            // Auto-dismiss alerts after 5 seconds
            setTimeout(() => {
                document.querySelectorAll('.alert').forEach(alert => {
                    const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                    bsAlert.close();
                });
            }, 5000);
        }

        function initializeTouch() {
            // Prevent zoom on double tap for better mobile UX
            let lastTouchEnd = 0;
            document.addEventListener('touchend', function (event) {
                const now = (new Date()).getTime();
                if (now - lastTouchEnd <= 300) {
                    event.preventDefault();
                }
                lastTouchEnd = now;
            }, false);

            // Add haptic feedback for mobile devices
            if ('vibrate' in navigator) {
                document.querySelectorAll('.btn, .nav-link').forEach(element => {
                    element.addEventListener('touchstart', function() {
                        navigator.vibrate(10);
                    });
                });
            }
        }

        // User management functions
        function newUser() {
            document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Nuevo Usuario';
            document.getElementById('userAction').value = 'create';
            document.getElementById('userSubmitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Crear Usuario';
            resetUserForm();
            document.getElementById('passwordField').style.display = 'block';
            document.getElementById('password').required = true;
            
            // Focus first input on desktop
            if (window.innerWidth >= 768) {
                setTimeout(() => {
                    document.getElementById('username').focus();
                }, 300);
            }
        }
        
        function editUser(user) {
            document.getElementById('userModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Usuario';
            document.getElementById('userAction').value = 'update';
            document.getElementById('userSubmitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Actualizar Usuario';
            
            document.getElementById('userId').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('fullName').value = user.full_name;
            document.getElementById('phone').value = user.phone || '';
            document.getElementById('roleId').value = user.role_id;
            
            // Hide password field when editing
            document.getElementById('passwordField').style.display = 'none';
            document.getElementById('password').required = false;
            
            const modal = new bootstrap.Modal(document.getElementById('userModal'));
            modal.show();
        }
        
        function changePassword(userId, username) {
            document.getElementById('passwordUserId').value = userId;
            document.getElementById('passwordUsername').textContent = username;
            document.getElementById('newPassword').value = '';
            clearPasswordStrength();
            
            const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
            modal.show();
            
            // Focus password input on desktop
            if (window.innerWidth >= 768) {
                setTimeout(() => {
                    document.getElementById('newPassword').focus();
                }, 300);
            }
        }
        
        function toggleStatus(userId, currentStatus, username) {
            const action = currentStatus ? 'desactivar' : 'activar';
            
            showConfirmDialog(
                `¿Está seguro de ${action} al usuario "${username}"?`,
                function() {
                    document.getElementById('statusUserId').value = userId;
                    document.getElementById('currentStatus').value = currentStatus;
                    document.getElementById('statusForm').submit();
                }
            );
        }
        
        function deleteUser(userId, username) {
            showConfirmDialog(
                `¿Está seguro de eliminar al usuario "${username}"?\n\nEsta acción no se puede deshacer.`,
                function() {
                    document.getElementById('deleteUserId').value = userId;
                    document.getElementById('deleteForm').submit();
                },
                'danger'
            );
        }

        // Filter function
        function filterByRole(role) {
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filter desktop table
            const tableRows = document.querySelectorAll('tbody tr');
            tableRows.forEach(row => {
                if (role === 'all') {
                    row.style.display = '';
                } else {
                    const roleCell = row.cells[3].textContent.toLowerCase();
                    if (roleCell.includes(role.toLowerCase())) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                }
            });
            
            // Filter mobile cards
            const mobileCards = document.querySelectorAll('.user-mobile-card');
            mobileCards.forEach(card => {
                const cardRole = card.getAttribute('data-role');
                if (role === 'all' || cardRole === role) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Validation functions
        function validateUserForm() {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const fullName = document.getElementById('fullName').value.trim();
            const roleId = document.getElementById('roleId').value;
            const password = document.getElementById('password').value;
            const isCreating = document.getElementById('userAction').value === 'create';
            
            if (!username || !email || !fullName || !roleId) {
                showNotification('Todos los campos obligatorios deben estar completados', 'error');
                return false;
            }
            
            if (isCreating && password.length < 6) {
                showNotification('La contraseña debe tener al menos 6 caracteres', 'error');
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showNotification('Por favor ingrese un email válido', 'error');
                return false;
            }
            
            return true;
        }
        
        function validatePasswordForm() {
            const newPassword = document.getElementById('newPassword').value;
            
            if (newPassword.length < 6) {
                showNotification('La contraseña debe tener al menos 6 caracteres', 'error');
                return false;
            }
            
            return true;
        }

        // Utility functions
        function resetUserForm() {
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            clearPasswordStrength();
        }

        function showPasswordStrength(input, containerId) {
            const password = input.value;
            const strength = getPasswordStrength(password);
            
            // Remove existing indicator
            const existingIndicator = document.querySelector(`.${containerId}`);
            if (existingIndicator) {
                existingIndicator.remove();
            }
            
            if (password.length > 0) {
                const indicator = document.createElement('div');
                indicator.className = `${containerId} mt-2`;
                indicator.innerHTML = `
                    <div class="progress" style="height: 6px;">
                        <div class="progress-bar bg-${strength.color}" 
                             style="width: ${strength.percentage}%; transition: width 0.3s ease;"></div>
                    </div>
                    <small class="text-${strength.color} d-block mt-1">
                        <i class="fas fa-shield-alt me-1"></i>
                        Seguridad: ${strength.text}
                    </small>
                `;
                
                input.parentNode.appendChild(indicator);
            }
        }
        
        function clearPasswordStrength() {
            document.querySelectorAll('[class*="password-strength"]').forEach(el => el.remove());
        }
        
        function getPasswordStrength(password) {
            let score = 0;
            
            if (password.length >= 6) score += 1;
            if (password.length >= 10) score += 1;
            if (/[a-z]/.test(password)) score += 1;
            if (/[A-Z]/.test(password)) score += 1;
            if (/[0-9]/.test(password)) score += 1;
            if (/[^A-Za-z0-9]/.test(password)) score += 1;
            
            if (score < 3) {
                return { percentage: 33, color: 'danger', text: 'Débil' };
            } else if (score < 5) {
                return { percentage: 66, color: 'warning', text: 'Media' };
            } else {
                return { percentage: 100, color: 'success', text: 'Fuerte' };
            }
        }

        function showButtonLoading(buttonId) {
            const button = document.getElementById(buttonId);
            if (button) {
                button.classList.add('loading');
                button.disabled = true;
            }
        }

        function hideButtonLoading(buttonId) {
            const button = document.getElementById(buttonId);
            if (button) {
                button.classList.remove('loading');
                button.disabled = false;
            }
        }

        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const alertType = type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info';
            
            notification.className = `alert alert-${alertType} alert-dismissible position-fixed`;
            notification.style.cssText = `
                top: 20px;
                right: 20px;
                z-index: 9999;
                max-width: 350px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.15);
                border: none;
                border-radius: 12px;
                animation: slideInRight 0.3s ease-out;
            `;
            
            notification.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="fas fa-${type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                    <div>${message}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.style.animation = 'slideOutRight 0.3s ease-out';
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        function showConfirmDialog(message, onConfirm, type = 'warning') {
            const isNativeConfirm = !window.bootstrap || window.innerWidth < 576;
            
            if (isNativeConfirm) {
                if (confirm(message)) {
                    onConfirm();
                }
                return;
            }

            // Create custom modal for better UX
            const modalId = 'confirmModal' + Date.now();
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = modalId;
            modal.innerHTML = `
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header border-0 pb-0">
                            <h5 class="modal-title">
                                <i class="fas fa-${type === 'danger' ? 'exclamation-triangle text-danger' : 'question-circle text-warning'} me-2"></i>
                                Confirmar acción
                            </h5>
                        </div>
                        <div class="modal-body">
                            <p class="mb-0">${message.replace(/\n/g, '<br>')}</p>
                        </div>
                        <div class="modal-footer border-0 pt-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-${type === 'danger' ? 'danger' : 'warning'}" id="${modalId}Confirm">
                                Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            const bsModal = new bootstrap.Modal(modal);
            
            document.getElementById(modalId + 'Confirm').addEventListener('click', function() {
                onConfirm();
                bsModal.hide();
            });
            
            modal.addEventListener('hidden.bs.modal', function() {
                modal.remove();
            });
            
            bsModal.show();
        }

        // Add CSS animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            
            .user-mobile-card {
                transition: all 0.2s ease;
            }
            
            .user-mobile-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            }
            
            @media (max-width: 768px) {
                .user-mobile-card:active {
                    transform: scale(0.98);
                }
            }
        `;
        document.head.appendChild(style);

        // Service Worker registration for PWA-like experience
        if ('serviceWorker' in navigator && window.location.protocol === 'https:') {
            window.addEventListener('load', function() {
                // Only register if we have a service worker file
                fetch('/sw.js').then(response => {
                    if (response.ok) {
                        navigator.serviceWorker.register('/sw.js')
                            .then(registration => console.log('SW registered'))
                            .catch(error => console.log('SW registration failed'));
                    }
                }).catch(() => {
                    // Service worker file doesn't exist, that's fine
                });
            });
        }

        // Handle online/offline status
        window.addEventListener('online', function() {
            showNotification('Conexión restaurada', 'success');
        });

        window.addEventListener('offline', function() {
            showNotification('Sin conexión a internet', 'error');
        });

        // Performance optimization: Lazy load images if any
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        observer.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }

        // Prevent form resubmission on page refresh
        if (window.history.replaceState) {
            window.history.replaceState(null, null, window.location.href);
        }
    </script>

<?php include 'footer.php'; ?>
</body>
</html>