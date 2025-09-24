<?php
// admin/profile.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Verificar si las columnas necesarias existen y agregarlas si no existen
try {
    // Verificar columna avatar
    $check_avatar = "SHOW COLUMNS FROM users LIKE 'avatar'";
    $stmt_check = $db->prepare($check_avatar);
    $stmt_check->execute();
    $avatar_column_exists = $stmt_check->rowCount() > 0;
    
    if (!$avatar_column_exists) {
        $add_avatar = "ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email";
        $db->exec($add_avatar);
        $avatar_column_exists = true;
    }
    
    // Verificar columna last_login
    $check_last_login = "SHOW COLUMNS FROM users LIKE 'last_login'";
    $stmt_check_login = $db->prepare($check_last_login);
    $stmt_check_login->execute();
    $last_login_column_exists = $stmt_check_login->rowCount() > 0;
    
    if (!$last_login_column_exists) {
        $add_last_login = "ALTER TABLE users ADD COLUMN last_login DATETIME NULL AFTER updated_at";
        $db->exec($add_last_login);
        $last_login_column_exists = true;
    }
} catch (Exception $e) {
    // Si falla, continuar sin las funcionalidades
    $avatar_column_exists = false;
    $last_login_column_exists = false;
}

// Obtener datos actuales del usuario
$avatar_field = $avatar_column_exists ? "u.avatar," : "";
$last_login_field = $last_login_column_exists ? "u.last_login," : "";

$query = "SELECT u.id, u.username, u.email, u.full_name, u.phone, u.password, 
          u.is_active, u.created_at, u.updated_at, {$avatar_field} {$last_login_field}
          r.name as role_name 
          FROM users u 
          LEFT JOIN roles r ON u.role_id = r.id 
          WHERE u.id = :user_id";

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch();

if (!$user) {
    header('Location: dashboard.php');
    exit();
}

// Obtener avatar del usuario o de la sesión, manejando caso NULL
$user_avatar = null;
if ($avatar_column_exists) {
    $user_avatar = $user['avatar'] ?? $_SESSION['avatar'] ?? null;
} else {
    $user_avatar = $_SESSION['avatar'] ?? null;
}

// Procesar formulario de actualización
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        // Validaciones básicas
        if (empty($full_name)) {
            $error = 'El nombre completo es obligatorio';
        } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'El email es obligatorio y debe ser válido';
        } else {
            // Verificar si el email ya existe para otro usuario
            $email_check = "SELECT id FROM users WHERE email = :email AND id != :user_id";
            $email_stmt = $db->prepare($email_check);
            $email_stmt->bindParam(':email', $email);
            $email_stmt->bindParam(':user_id', $user_id);
            $email_stmt->execute();
            
            if ($email_stmt->fetch()) {
                $error = 'Este email ya está en uso por otro usuario';
            } else {
                // Actualizar datos del usuario
                $update_query = "UPDATE users SET 
                                full_name = :full_name,
                                email = :email,
                                phone = :phone,
                                updated_at = NOW()
                                WHERE id = :user_id";
                
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':full_name', $full_name);
                $update_stmt->bindParam(':email', $email);
                $update_stmt->bindParam(':phone', $phone);
                $update_stmt->bindParam(':user_id', $user_id);
                
                if ($update_stmt->execute()) {
                    // Actualizar datos en la sesión
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    
                    // Refrescar datos del usuario
                    $stmt->execute();
                    $user = $stmt->fetch();
                    
                    $message = 'Perfil actualizado correctamente';
                } else {
                    $error = 'Error al actualizar el perfil';
                }
            }
        }
    }
    
    elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Validaciones
        if (empty($current_password)) {
            $error = 'La contraseña actual es obligatoria';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'La contraseña actual es incorrecta';
        } elseif (strlen($new_password) < 6) {
            $error = 'La nueva contraseña debe tener al menos 6 caracteres';
        } elseif ($new_password !== $confirm_password) {
            $error = 'Las contraseñas no coinciden';
        } else {
            // Actualizar contraseña
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            $password_query = "UPDATE users SET 
                              password = :password,
                              updated_at = NOW()
                              WHERE id = :user_id";
            
            $password_stmt = $db->prepare($password_query);
            $password_stmt->bindParam(':password', $hashed_password);
            $password_stmt->bindParam(':user_id', $user_id);
            
            if ($password_stmt->execute()) {
                $message = 'Contraseña actualizada correctamente';
            } else {
                $error = 'Error al actualizar la contraseña';
            }
        }
    }
    
    elseif ($action === 'upload_avatar' && $avatar_column_exists) {
        if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 2 * 1024 * 1024; // 2MB
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Solo se permiten archivos JPG, PNG o GIF';
            } elseif ($file['size'] > $max_size) {
                $error = 'El archivo no debe superar los 2MB';
            } else {
                // Crear directorio si no existe
                $upload_dir = '../uploads/avatars/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generar nombre único
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Eliminar avatar anterior si existe
                    if ($user_avatar && file_exists($upload_dir . $user_avatar)) {
                        unlink($upload_dir . $user_avatar);
                    }
                    
                    // Actualizar base de datos
                    $avatar_query = "UPDATE users SET 
                                    avatar = :avatar,
                                    updated_at = NOW()
                                    WHERE id = :user_id";
                    
                    $avatar_stmt = $db->prepare($avatar_query);
                    $avatar_stmt->bindParam(':avatar', $filename);
                    $avatar_stmt->bindParam(':user_id', $user_id);
                    
                    if ($avatar_stmt->execute()) {
                        $_SESSION['avatar'] = $filename;
                        $user_avatar = $filename;
                        
                        // Refrescar datos del usuario
                        $stmt->execute();
                        $user = $stmt->fetch();
                        
                        $message = 'Avatar actualizado correctamente';
                    } else {
                        $error = 'Error al guardar el avatar en la base de datos';
                    }
                } else {
                    $error = 'Error al subir el archivo';
                }
            }
        } else {
            $error = 'No se seleccionó ningún archivo válido';
        }
    } elseif ($action === 'upload_avatar' && !$avatar_column_exists) {
        $error = 'La funcionalidad de avatar no está disponible. Contacte al administrador del sistema.';
    }
}

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Obtener iniciales para avatar por defecto
$initials = '';
if ($user['full_name']) {
    $names = explode(' ', trim($user['full_name']));
    $initials = strtoupper(substr($names[0], 0, 1));
    if (isset($names[1])) {
        $initials .= strtoupper(substr($names[1], 0, 1));
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil - <?php echo $restaurant_name; ?></title>
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
    $database = new Database();
    $db = $database->getConnection();
    $theme_manager = new ThemeManager($db);
    $current_theme = $theme_manager->getThemeSettings();
} else {
    $current_theme = array(
        'primary_color' => '#667eea',
        'secondary_color' => '#764ba2',
        'accent_color' => '#ff6b6b',
        'success_color' => '#28a745',
        'warning_color' => '#ffc107',
        'danger_color' => '#dc3545',
        'info_color' => '#17a2b8'
    );
}
?>
    <style>
        /* Variables CSS para el tema */
        :root {
            --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            --dashboard-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
            --sidebar-mobile-width: 100%;
            --profile-card-bg: #ffffff;
            --profile-border: #e9ecef;
            --profile-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        /* Body base */
        body {
            background: #f8f9fa !important;
            color: #212529 !important;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow-x: hidden;
        }

        /* Mobile Top Bar */
        .mobile-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            background: var(--primary-gradient);
            color: #ffffff !important;
            padding: 1rem;
            display: none;
        }

        .mobile-topbar h5, .mobile-topbar * {
            margin: 0;
            font-size: 1.1rem;
            color: #ffffff !important;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: #ffffff !important;
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .menu-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--dashboard-sidebar-width);
            height: 100vh;
            background: var(--primary-gradient);
            color: #ffffff !important;
            z-index: 1030;
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .sidebar * {
            color: rgba(255, 255, 255, 0.9) !important;
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
            transition: opacity 0.3s ease-in-out;
        }

        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8) !important;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: #ffffff !important;
        }

        .sidebar .nav-link .badge {
            margin-left: auto;
        }

        .sidebar-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: #ffffff !important;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        /* Main profile container - CORREGIDO */
        .profile-container {
            margin-left: var(--dashboard-sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out;
            background: #f8f9fa !important;
            color: #212529 !important;
            max-width: none; /* Remover restricción de ancho máximo */
        }

        /* Profile cards */
        .profile-card {
            background: var(--profile-card-bg);
            border: 1px solid var(--profile-border);
            border-radius: 15px;
            box-shadow: var(--profile-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
            animation: fadeInUp 0.5s ease-out;
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--secondary-color, #764ba2) 100%);
            color: white !important;
            padding: 2rem;
            text-align: center;
            position: relative;
        }

        .profile-header * {
            color: white !important;
        }

        .profile-avatar-container {
            margin-bottom: 1rem;
            position: relative;
            display: inline-block;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            border: 4px solid rgba(255, 255, 255, 0.3);
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-avatar-initials {
            font-size: 2.5rem;
            font-weight: bold;
            color: white !important;
            background: rgba(255, 255, 255, 0.2);
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .avatar-upload-btn {
            position: absolute;
            bottom: -5px;
            right: -5px;
            width: 40px;
            height: 40px;
            background: #28a745;
            border: 3px solid white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .avatar-upload-btn:hover {
            background: #218838;
            transform: scale(1.1);
        }

        .profile-info h3 {
            margin: 0;
            font-size: 1.5rem;
            font-weight: 600;
            color: white !important;
        }

        .profile-role {
            background: rgba(255, 255, 255, 0.2);
            color: white !important;
            padding: 0.25rem 0.75rem;
            border-radius: 15px;
            font-size: 0.85rem;
            text-transform: capitalize;
            margin-top: 0.5rem;
            display: inline-block;
        }

        /* Profile sections */
        .profile-section {
            padding: 2rem;
            border-bottom: 1px solid var(--profile-border);
            background: white !important;
            color: #212529 !important;
        }

        .profile-section:last-child {
            border-bottom: none;
        }

        .profile-section * {
            color: #212529 !important;
        }

        .section-title {
            color: var(--primary-color, #667eea) !important;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Form elements - FORZAR COLORES CLAROS */
        .form-control, 
        .form-select {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            background: #ffffff !important;
            color: #212529 !important;
        }

        .form-control:focus, 
        .form-select:focus {
            border-color: var(--primary-color, #667eea);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: #ffffff !important;
            color: #212529 !important;
        }
        
        /* Placeholder y texto en inputs */
        .form-control::placeholder,
        input::placeholder,
        textarea::placeholder {
            color: #6c757d !important;
            opacity: 1;
        }

        /* Labels de formulario */
        .form-label,
        label {
            color: #212529 !important;
            font-weight: 500;
        }

        /* Input groups */
        .input-group-text {
            color: #212529 !important;
            background-color: #f8f9fa !important;
            border-color: #ced4da !important;
        }

        /* Opciones de select */
        .form-select option,
        select option {
            color: #212529 !important;
            background: #ffffff !important;
        }

        /* Form text y mensajes */
        .form-text,
        .invalid-feedback,
        .valid-feedback {
            color: #6c757d !important;
        }

        /* Disabled state */
        .form-control:disabled,
        .form-select:disabled {
            color: #6c757d !important;
            background-color: #e9ecef !important;
        }

        /* Botones */
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--secondary-color, #764ba2) 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: white !important;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            color: white !important;
        }

        .btn-outline-secondary {
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            color: #6c757d !important;
            border-color: #6c757d;
        }

        .btn-outline-secondary:hover {
            color: white !important;
            background-color: #6c757d;
        }

        /* Alertas */
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1.5rem;
            color: inherit !important;
        }

        .alert-success {
            background-color: #d1e7dd;
            color: #0f5132 !important;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #842029 !important;
        }

        /* Stats grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
            padding: 1rem;
            background: #f8f9fa !important;
            color: #212529 !important;
            border-radius: 10px;
            border: 1px solid #e9ecef;
        }

        .stat-item * {
            color: #212529 !important;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color, #667eea) !important;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #6c757d !important;
            margin-top: 0.25rem;
        }

        /* Password strength indicator */
        .password-strength {
            height: 4px;
            border-radius: 2px;
            margin-top: 0.5rem;
            transition: all 0.3s ease;
        }

        .strength-weak { 
            background: #dc3545; 
        }
        
        .strength-medium { 
            background: #ffc107; 
        }
        
        .strength-strong { 
            background: #28a745; 
        }

        /* Badges */
        .badge {
            font-size: 0.75rem;
        }

        /* Responsive Design */
        @media (max-width: 991.98px) {
            .mobile-topbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .sidebar {
                transform: translateX(-100%);
                width: var(--sidebar-mobile-width);
                max-width: 350px;
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .sidebar-close {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .profile-container {
                margin-left: 0;
                padding: 1rem;
                padding-top: 5rem; /* Espacio para mobile topbar */
            }
        }

        @media (max-width: 768px) {
            .profile-container {
                padding: 0.5rem;
                padding-top: 4.5rem;
            }

            .profile-header {
                padding: 1.5rem;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
            }

            .profile-avatar-initials {
                font-size: 2rem;
            }

            .profile-section {
                padding: 1.5rem;
            }

            .btn-primary, 
            .btn-outline-secondary {
                width: 100%;
                margin-bottom: 0.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 0.5rem;
            }
        }

        /* Animaciones */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-control:focus {
            animation: focusGlow 0.3s ease-out;
        }

        @keyframes focusGlow {
            from {
                box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4);
            }
            to {
                box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            }
        }

        /* Texto forzado a colores correctos */
        h1, h2, h3, h4, h5, h6 {
            color: #212529 !important;
        }

        p, span, div:not(.sidebar *):not(.mobile-topbar *):not(.profile-header *) {
            color: #212529 !important;
        }

        .text-muted {
            color: #6c757d !important;
        }

        /* Spinner para loading del avatar */
        .spinner-border {
            width: 2rem;
            height: 2rem;
        }

        .spinner-border.text-light {
            color: #f8f9fa !important;
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
    
    <div class="profile-container">
        <!-- Alertas -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Encabezado del Perfil -->
        <div class="profile-card">
            <div class="profile-header">
                <div class="profile-avatar-container">
                    <div class="profile-avatar">
                        <?php if ($avatar_column_exists && $user_avatar && file_exists('../uploads/avatars/' . $user_avatar)): ?>
                            <img src="../uploads/avatars/<?php echo htmlspecialchars($user_avatar); ?>" alt="Avatar">
                        <?php else: ?>
                            <div class="profile-avatar-initials">
                                <?php echo $initials; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($avatar_column_exists): ?>
                        <label for="avatar-upload" class="avatar-upload-btn">
                            <i class="fas fa-camera text-white"></i>
                        </label>
                    <?php endif; ?>
                </div>
                
                <div class="profile-info">
                    <h3><?php echo htmlspecialchars($user['full_name']); ?></h3>
                    <div class="profile-role"><?php echo htmlspecialchars($user['role_name']); ?></div>
                </div>
            </div>

            <?php if ($avatar_column_exists): ?>
                <!-- Formulario de Subida de Avatar (Oculto) -->
                <form id="avatar-form" method="POST" enctype="multipart/form-data" style="display: none;">
                    <input type="hidden" name="action" value="upload_avatar">
                    <input type="file" id="avatar-upload" name="avatar" accept="image/*" onchange="uploadAvatar()">
                </form>
            <?php endif; ?>
        </div>

        <!-- Información Personal -->
        <div class="profile-card">
            <div class="profile-section">
                <h4 class="section-title">
                    <i class="fas fa-user"></i>
                    Información Personal
                </h4>
                
                <form method="POST">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="full_name" class="form-label">Nombre Completo *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" 
                                   value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Rol</label>
                            <input type="text" class="form-control" id="role" 
                                   value="<?php echo htmlspecialchars($user['role_name']); ?>" readonly>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Guardar Cambios
                        </button>
                       
                    </div>
                </form>
            </div>
        </div>

        <!-- Cambiar Contraseña -->
        <div class="profile-card">
            <div class="profile-section">
                <h4 class="section-title">
                    <i class="fas fa-key"></i>
                    Cambiar Contraseña
                </h4>
                
                <form method="POST" id="password-form">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="current_password" class="form-label">Contraseña Actual *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('current_password')">
                                    <i class="fas fa-eye" id="current_password_icon"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="new_password" class="form-label">Nueva Contraseña *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="new_password" name="new_password" 
                                       required minlength="6" oninput="checkPasswordStrength()">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('new_password')">
                                    <i class="fas fa-eye" id="new_password_icon"></i>
                                </button>
                            </div>
                            <div class="password-strength" id="password-strength"></div>
                            <div class="form-text">Mínimo 6 caracteres</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="confirm_password" class="form-label">Confirmar Nueva Contraseña *</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       required oninput="checkPasswordMatch()">
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('confirm_password')">
                                    <i class="fas fa-eye" id="confirm_password_icon"></i>
                                </button>
                            </div>
                            <div id="password-match-message" class="form-text"></div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="change-password-btn" disabled>
                        <i class="fas fa-key me-2"></i>Cambiar Contraseña
                    </button>
                </form>
            </div>
        </div>

        <!-- Estadísticas del Usuario -->
        <div class="profile-card">
            <div class="profile-section">
                <h4 class="section-title">
                    <i class="fas fa-chart-line"></i>
                    Estadísticas
                </h4>
                
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo date('d/m/Y', strtotime($user['created_at'])); ?></div>
                        <div class="stat-label">Fecha de Registro</div>
                    </div>
                    
                    <div class="stat-item">
                        <div class="stat-number">
                            <?php 
                            $days_active = ceil((time() - strtotime($user['created_at'])) / (60 * 60 * 24));
                            echo $days_active;
                            ?>
                        </div>
                        <div class="stat-label">Días Activo</div>
                    </div>
                    
                    <?php if ($last_login_column_exists): ?>
                        <div class="stat-item">
                            <div class="stat-number">
                                <?php 
                                if (isset($user['last_login']) && $user['last_login']) {
                                    echo date('d/m/Y H:i', strtotime($user['last_login']));
                                } else {
                                    echo 'Nunca';
                                }
                                ?>
                            </div>
                            <div class="stat-label">Último Acceso</div>
                        </div>
                    <?php else: ?>
                        <div class="stat-item">
                            <div class="stat-number">
                                <span class="badge bg-info">
                                    Sistema
                                </span>
                            </div>
                            <div class="stat-label">Sesión Actual</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="stat-item">
                        <div class="stat-number">
                            <span class="badge bg-<?php echo ($user['is_active'] ?? 1) ? 'success' : 'danger'; ?>">
                                <?php echo ($user['is_active'] ?? 1) ? 'Activo' : 'Inactivo'; ?>
                            </span>
                        </div>
                        <div class="stat-label">Estado de Cuenta</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Función para subir avatar
    function uploadAvatar() {
        const form = document.getElementById('avatar-form');
        const fileInput = document.getElementById('avatar-upload');
        
        if (fileInput.files.length > 0) {
            const file = fileInput.files[0];
            const maxSize = 2 * 1024 * 1024; // 2MB
            
            if (file.size > maxSize) {
                alert('El archivo no debe superar los 2MB');
                return;
            }
            
            const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                alert('Solo se permiten archivos JPG, PNG o GIF');
                return;
            }
            
            // Mostrar loading
            const avatarContainer = document.querySelector('.profile-avatar');
            const originalContent = avatarContainer.innerHTML;
            avatarContainer.innerHTML = '<div class="d-flex align-items-center justify-content-center h-100"><div class="spinner-border text-light" role="status"></div></div>';
            
            // Enviar formulario
            form.submit();
        }
    }

    // Función para mostrar/ocultar contraseñas
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '_icon');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            field.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }

    // Función para verificar la fortaleza de la contraseña
    function checkPasswordStrength() {
        const password = document.getElementById('new_password').value;
        const strengthBar = document.getElementById('password-strength');
        
        let strength = 0;
        if (password.length >= 6) strength++;
        if (password.match(/[a-z]/)) strength++;
        if (password.match(/[A-Z]/)) strength++;
        if (password.match(/[0-9]/)) strength++;
        if (password.match(/[^a-zA-Z0-9]/)) strength++;
        
        strengthBar.className = 'password-strength';
        
        if (strength <= 2) {
            strengthBar.classList.add('strength-weak');
        } else if (strength <= 3) {
            strengthBar.classList.add('strength-medium');
        } else {
            strengthBar.classList.add('strength-strong');
        }
        
        checkPasswordMatch();
    }

    // Función para verificar que las contraseñas coincidan
    function checkPasswordMatch() {
        const newPassword = document.getElementById('new_password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const messageDiv = document.getElementById('password-match-message');
        const submitBtn = document.getElementById('change-password-btn');
        
        if (confirmPassword.length > 0) {
            if (newPassword === confirmPassword) {
                messageDiv.innerHTML = '<span class="text-success"><i class="fas fa-check me-1"></i>Las contraseñas coinciden</span>';
                messageDiv.className = 'form-text';
                
                if (newPassword.length >= 6) {
                    submitBtn.disabled = false;
                }
            } else {
                messageDiv.innerHTML = '<span class="text-danger"><i class="fas fa-times me-1"></i>Las contraseñas no coinciden</span>';
                messageDiv.className = 'form-text';
                submitBtn.disabled = true;
            }
        } else {
            messageDiv.innerHTML = '';
            submitBtn.disabled = true;
        }
    }

    // Validación en tiempo real del formulario de perfil
    document.addEventListener('DOMContentLoaded', function() {
        const profileForm = document.querySelector('form[action=""]');
        const inputs = profileForm.querySelectorAll('input[required]');
        
        inputs.forEach(input => {
            input.addEventListener('input', function() {
                if (this.value.trim() === '') {
                    this.classList.add('is-invalid');
                } else {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                }
            });
        });
        
        // Validación especial para email
        const emailInput = document.getElementById('email');
        emailInput.addEventListener('input', function() {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(this.value)) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                this.classList.remove('is-invalid');
                this.classList.add('is-valid');
            }
        });
    });

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    });

    // Confirm password change
    document.getElementById('password-form').addEventListener('submit', function(e) {
        if (!confirm('¿Estás seguro de que deseas cambiar tu contraseña?')) {
            e.preventDefault();
        }
    });

    // Preview avatar before upload
    document.getElementById('avatar-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const avatarContainer = document.querySelector('.profile-avatar');
                avatarContainer.innerHTML = `<img src="${e.target.result}" alt="Preview" style="width: 100%; height: 100%; object-fit: cover;">`;
            };
            reader.readAsDataURL(file);
        }
    });
    </script>

    <?php include 'footer.php'; ?>
</body>
</html>