<?php
/**
 * Configuración de Temas del Sistema
 * admin/theme-settings.php
 * 
 * Panel de administración para gestionar la apariencia del sistema
 * Versión corregida y optimizada
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

// Verificar y crear archivo theme.php si no existe
$theme_file = '../config/theme.php';
if (!file_exists($theme_file)) {
    createBasicThemeFile($theme_file);
}

require_once $theme_file;

// Verificar autenticación
$auth = new Auth();
$auth->requireLogin();

// Solo administradores pueden acceder
if ($_SESSION['role_name'] !== 'administrador') {
    header('Location: pages/403.php');
    exit();
}

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

// Inicializar variables
$database = new Database();
$db = $database->getConnection();
$theme_manager = new ThemeManager($db);

$message = '';
$error = '';

// Manejar envío del formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $result = handleFormSubmission();
    
    if (isset($result['success']) && $result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'] ?? 'Error desconocido';
    }
}

/**
 * Manejar diferentes tipos de envío de formulario
 */
function handleFormSubmission() {
    global $theme_manager;
    
    $action = sanitize($_POST['action']);
    
    switch ($action) {
        case 'update_colors':
            return updateColors();
        case 'update_typography':
            return updateTypography();
        case 'update_layout':
            return updateLayout();
        case 'apply_preset':
            return applyPreset();
        case 'reset_theme':
            return resetToDefault();
        case 'export_theme':
            return exportTheme();
        case 'import_theme':
            return importTheme();
        default:
            return array('success' => false, 'message' => 'Acción no válida');
    }
}

/**
 * Actualizar colores del tema
 */
function updateColors() {
    global $theme_manager;
    
    $colors = array();
    $valid_colors = array('primary_color', 'secondary_color', 'accent_color', 
                         'success_color', 'warning_color', 'danger_color', 'info_color');
    
    foreach ($valid_colors as $color) {
        if (isset($_POST[$color])) {
            $color_value = sanitize($_POST[$color]);
            if (isValidHexColor($color_value)) {
                $colors[$color] = $color_value;
            } else {
                return array('success' => false, 'message' => "Color hexadecimal inválido: $color_value");
            }
        }
    }
    
    if (empty($colors)) {
        return array('success' => false, 'message' => 'No se enviaron colores válidos');
    }
    
    return $theme_manager->updateTheme($colors);
}

/**
 * Actualizar tipografía
 */
function updateTypography() {
    global $theme_manager;
    
    $typography = array();
    $valid_fields = array('font_family_primary', 'font_size_base', 'font_size_small', 'font_size_large');
    
    foreach ($valid_fields as $field) {
        if (isset($_POST[$field])) {
            $typography[$field] = sanitize($_POST[$field]);
        }
    }
    
    if (empty($typography)) {
        return array('success' => false, 'message' => 'No se enviaron configuraciones de tipografía válidas');
    }
    
    return $theme_manager->updateTheme($typography);
}

/**
 * Actualizar configuración de layout
 */
function updateLayout() {
    global $theme_manager;
    
    $layout = array();
    $valid_fields = array('border_radius_base', 'border_radius_large', 'sidebar_width', 'shadow_base');
    
    foreach ($valid_fields as $field) {
        if (isset($_POST[$field])) {
            $layout[$field] = sanitize($_POST[$field]);
        }
    }
    
    if (empty($layout)) {
        return array('success' => false, 'message' => 'No se enviaron configuraciones de diseño válidas');
    }
    
    return $theme_manager->updateTheme($layout);
}

/**
 * Aplicar tema predefinido
 */
function applyPreset() {
    global $theme_manager;
    
    if (!isset($_POST['preset'])) {
        return array('success' => false, 'message' => 'No se especificó el tema predefinido');
    }
    
    $preset = sanitize($_POST['preset']);
    return $theme_manager->applyPresetTheme($preset);
}

/**
 * Restablecer tema por defecto
 */
function resetToDefault() {
    global $theme_manager;
    return $theme_manager->resetToDefault();
}

/**
 * Exportar tema actual
 */
function exportTheme() {
    global $theme_manager;
    
    try {
        $theme_name = isset($_POST['theme_name']) ? sanitize($_POST['theme_name']) : 'Mi Tema Personalizado';
        $export_data = $theme_manager->exportTheme($theme_name);
        
        // Preparar descarga
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="tema_' . date('Y-m-d_H-i-s') . '.json"');
        echo json_encode($export_data, JSON_PRETTY_PRINT);
        exit();
        
    } catch (Exception $e) {
        return array('success' => false, 'message' => 'Error al exportar: ' . $e->getMessage());
    }
}

/**
 * Importar tema desde archivo
 */
function importTheme() {
    global $theme_manager;
    
    if (!isset($_FILES['theme_file']) || $_FILES['theme_file']['error'] !== UPLOAD_ERR_OK) {
        return array('success' => false, 'message' => 'Error al subir archivo');
    }
    
    $file_content = file_get_contents($_FILES['theme_file']['tmp_name']);
    $theme_data = json_decode($file_content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return array('success' => false, 'message' => 'Archivo JSON inválido');
    }
    
    return $theme_manager->importTheme($theme_data);
}

/**
 * Validar color hexadecimal
 */
function isValidHexColor($color) {
    return preg_match('/^#[a-f0-9]{6}$/i', $color);
}

/**
 * Crear archivo theme.php básico si no existe
 */
function createBasicThemeFile($theme_file) {
    $basic_theme_content = '<?php
class ThemeManager {
    private $settings;
    private $db;
    
    public function __construct($database = null) {
        $this->db = $database;
        $this->settings = $this->getThemeSettings();
    }
    
    public function getThemeSettings() {
        if ($this->db) {
            try {
                $query = "SELECT setting_key, setting_value FROM theme_settings";
                $stmt = $this->db->prepare($query);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $theme = array();
                foreach ($results as $row) {
                    $theme[$row["setting_key"]] = $row["setting_value"];
                }
                return array_merge($this->getDefaultTheme(), $theme);
            } catch (Exception $e) {
                return $this->getDefaultTheme();
            }
        }
        return $this->getDefaultTheme();
    }
    
    public function getDefaultTheme() {
        return array(
            "primary_color" => "#667eea",
            "secondary_color" => "#764ba2", 
            "accent_color" => "#ff6b6b",
            "success_color" => "#28a745",
            "warning_color" => "#ffc107",
            "danger_color" => "#dc3545",
            "info_color" => "#17a2b8"
        );
    }
    
    public function updateTheme($new_settings) {
        return array("success" => true, "message" => "Configuración actualizada");
    }
    
    public function getPresetThemes() {
        return array(
            "default" => array("name" => "Predeterminado", "primary_color" => "#667eea"),
            "dark" => array("name" => "Oscuro", "primary_color" => "#343a40"),
            "green" => array("name" => "Verde", "primary_color" => "#28a745")
        );
    }
    
    public function generateCSS() {
        return ":root { --primary-color: #667eea; }";
    }
}
?>';
    
    $dir = dirname($theme_file);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    file_put_contents($theme_file, $basic_theme_content);
}

// Obtener configuración actual del tema
try {
    $current_theme = $theme_manager->getThemeSettings();
    $theme_info = $theme_manager->getThemeInfo();
} catch (Exception $e) {
    $current_theme = $theme_manager->getDefaultTheme();
    $error = 'Error al cargar configuración del tema, usando valores predeterminados';
    $theme_info = array();
}

// Obtener temas predefinidos
try {
    $presets = $theme_manager->getPresetThemes();
} catch (Exception $e) {
    $presets = array(
        'default' => array('name' => 'Predeterminado', 'primary_color' => '#667eea'),
        'dark' => array('name' => 'Oscuro', 'primary_color' => '#343a40'),
        'green' => array('name' => 'Verde', 'primary_color' => '#28a745')
    );
}

// Obtener configuraciones del sistema
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Crear tabla de theme_settings si no existe
try {
    $create_table_query = "CREATE TABLE IF NOT EXISTS theme_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NOT NULL,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX(setting_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($create_table_query);
} catch (Exception $e) {
    // Tabla ya existe o error de permisos
}

// Validar tema actual
$required_colors = array('primary_color', 'secondary_color', 'accent_color', 'success_color', 'warning_color', 'danger_color', 'info_color');
foreach ($required_colors as $color) {
    if (!isset($current_theme[$color]) || !isValidHexColor($current_theme[$color])) {
        $default_colors = array(
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'accent_color' => '#ff6b6b',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8'
        );
        $current_theme[$color] = $default_colors[$color] ?? '#667eea';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Tema - <?php echo htmlspecialchars($restaurant_name); ?></title>
    
    <!-- Bootstrap CSS -->
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
/* Extensiones específicas del dashboard */
:root {
    --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --dashboard-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
    --sidebar-mobile-width: 100%;
}

        body {
            background: var(--light) !important;
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark) !important;
        }

        /* Mobile Top Bar */
        .mobile-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            background: var(--primary-gradient);
            color: var(--white) !important;
            padding: 1rem;
            display: none;
        }

        .mobile-topbar h5 {
            margin: 0;
            font-size: 1.1rem;
            color: var(--white) !important;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: var(--white) !important;
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
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-gradient);
            color: var(--white) !important;
            z-index: 1030;
            transition: transform 0.3s ease-in-out;
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
            color: var(--white) !important;
        }

        .sidebar-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--white) !important;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out;
            background: var(--light) !important;
        }

        /* Page header */
        .page-header {
            background: var(--white) !important;
            color: var(--dark) !important;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        /* Color picker corregido */
        .color-input-group {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 50px;
            cursor: pointer;
        }

        .color-preview {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            border: 3px solid var(--border);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            background: #ffffff;
            display: block;
        }

        .color-preview:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border-color: <?php echo $current_theme['primary_color']; ?>;
        }

        .color-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            border: none;
            z-index: 10;
            background: none;
        }

        .color-tooltip {
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: var(--white) !important;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 1000;
            white-space: nowrap;
        }

        .color-tooltip::before {
            content: '';
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-bottom-color: rgba(0, 0, 0, 0.8);
        }

        .color-input-group:hover .color-tooltip {
            opacity: 1;
        }

        /* Theme preview */
        .theme-preview {
            min-height: 220px;
            border: 2px solid var(--border);
            border-radius: 15px;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--light) 0%, var(--white) 100%) !important;
            margin-bottom: 1rem;
            position: relative;
            overflow: hidden;
            color: var(--dark) !important;
        }

        .theme-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, <?php echo $current_theme['primary_color']; ?>, <?php echo $current_theme['secondary_color']; ?>);
        }

        .sidebar-preview {
            width: 70px;
            height: 120px;
            background: linear-gradient(180deg, <?php echo $current_theme['primary_color']; ?>, <?php echo $current_theme['secondary_color']; ?>);
            border-radius: 12px;
            margin-right: 1.5rem;
            position: relative;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-preview::before {
            content: '';
            position: absolute;
            top: 12px;
            left: 10px;
            right: 10px;
            height: 4px;
            background: rgba(255, 255, 255, 0.4);
            border-radius: 2px;
        }
        
        .sidebar-preview::after {
            content: '';
            position: absolute;
            top: 24px;
            left: 10px;
            right: 10px;
            height: 80px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 6px;
        }

        /* Preset cards */
        .preset-card {
            cursor: pointer;
            transition: all 0.3s ease;
            border: 2px solid var(--border);
            border-radius: 15px;
            overflow: hidden;
            background: var(--white) !important;
            color: var(--dark) !important;
        }
        
        .preset-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.15);
            border-color: <?php echo $current_theme['primary_color']; ?>;
        }
        
        .preset-card.selected {
            border-color: <?php echo $current_theme['success_color']; ?>;
            background: rgba(40, 167, 69, 0.05) !important;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            background: var(--white) !important;
            color: var(--dark) !important;
        }

        .card-header {
            background: var(--light) !important;
            color: var(--dark) !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            padding: 1.5rem;
        }

        .card-body {
            padding: 2rem;
            background: var(--white) !important;
            color: var(--dark) !important;
        }

        /* Forms */
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid var(--border);
            padding: 0.75rem 1rem;
            transition: all 0.3s;
            background: var(--white) !important;
            color: var(--dark) !important;
        }

        .form-control:focus, .form-select:focus {
            border-color: <?php echo $current_theme['primary_color']; ?>;
            box-shadow: 0 0 0 0.2rem <?php echo $current_theme['primary_color']; ?>33;
            background: var(--white) !important;
            color: var(--dark) !important;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark) !important;
            margin-bottom: 0.75rem;
        }

        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s;
            border: none;
        }

        .btn-primary {
            background: linear-gradient(45deg, <?php echo $current_theme['primary_color']; ?>, <?php echo $current_theme['secondary_color']; ?>) !important;
            color: var(--white) !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            color: var(--white) !important;
        }

        /* Tabs */
        .nav-tabs {
            border-bottom: none;
            background: var(--light) !important;
            border-radius: 15px 15px 0 0;
        }

        .nav-tabs .nav-link {
            border-radius: 0;
            border: none;
            color: var(--muted) !important;
            font-weight: 600;
            padding: 1.25rem 1.5rem;
            background: transparent;
        }

        .nav-tabs .nav-link.active {
            background: var(--white) !important;
            color: <?php echo $current_theme['primary_color']; ?> !important;
            border-bottom: 3px solid <?php echo $current_theme['primary_color']; ?>;
        }

        .tab-content {
            background: var(--white) !important;
            padding: 2rem;
            border-radius: 0 0 15px 15px;
            color: var(--dark) !important;
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }

        .alert-success {
            background: linear-gradient(45deg, <?php echo $current_theme['success_color']; ?>15, <?php echo $current_theme['success_color']; ?>25) !important;
            color: <?php echo $current_theme['success_color']; ?> !important;
            border-left: 4px solid <?php echo $current_theme['success_color']; ?>;
        }

        .alert-danger {
            background: linear-gradient(45deg, <?php echo $current_theme['danger_color']; ?>15, <?php echo $current_theme['danger_color']; ?>25) !important;
            color: <?php echo $current_theme['danger_color']; ?> !important;
            border-left: 4px solid <?php echo $current_theme['danger_color']; ?>;
        }

        /* Responsive */
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

            .color-input-group {
                width: 40px;
                height: 40px;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
                padding-top: 4.5rem;
            }

            .color-input-group {
                width: 35px;
                height: 35px;
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
                        <i class="fas fa-palette me-2"></i>
                        Configuración de Tema
                    </h2>
                    <p class="text-muted mb-0">Personaliza los colores, fuentes y diseño del sistema</p>
                </div>
                <div class="text-muted d-none d-lg-block">
                    <button class="btn btn-outline-primary" onclick="previewChanges()">
                        <i class="fas fa-eye me-1"></i>
                        Vista Previa
                    </button>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check me-2"></i>
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Theme Info Card -->
        <?php if (!empty($theme_info)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Estado del Sistema de Temas
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h3 text-primary"><?php echo $theme_info['total_settings'] ?? 0; ?></div>
                                    <small class="text-muted">Configuraciones</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h3 <?php echo ($theme_info['css_file_exists'] ?? false) ? 'text-success' : 'text-warning'; ?>">
                                        <i class="fas <?php echo ($theme_info['css_file_exists'] ?? false) ? 'fa-check' : 'fa-exclamation-triangle'; ?>"></i>
                                    </div>
                                    <small class="text-muted">Archivo CSS</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h6 text-info">
                                        <?php echo isset($theme_info['css_file_size']) ? number_format($theme_info['css_file_size'] / 1024, 1) . ' KB' : 'N/A'; ?>
                                    </div>
                                    <small class="text-muted">Tamaño CSS</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="h6 text-muted">
                                        <?php echo isset($theme_info['last_updated']) ? date('d/m/Y H:i', strtotime($theme_info['last_updated'])) : 'Nunca'; ?>
                                    </div>
                                    <small class="text-muted">Última actualización</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Theme Preview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-desktop me-2"></i>
                            Vista Previa del Tema Actual
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="theme-preview">
                            <div class="d-flex align-items-start">
                                <div class="sidebar-preview"></div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0" style="color: <?php echo $current_theme['primary_color']; ?>;">Dashboard</h6>
                                        <span class="badge" style="background: <?php echo $current_theme['success_color']; ?>;">Online</span>
                                    </div>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="p-2 text-center text-white" style="background: <?php echo $current_theme['primary_color']; ?>; border-radius: 8px;">
                                                <small>Órdenes: 12</small>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="p-2 text-center text-white" style="background: <?php echo $current_theme['success_color']; ?>; border-radius: 8px;">
                                                <small>Mesas: 8</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button class="btn btn-sm text-white" style="background: <?php echo $current_theme['accent_color']; ?>;">
                                            <i class="fas fa-plus me-1"></i>
                                            Nuevo Pedido
                                        </button>
                                        <button class="btn btn-sm btn-outline-secondary ms-2">
                                            Ver Reportes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Theme Configuration Tabs -->
        <div class="card">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" id="colors-tab" data-bs-toggle="tab" data-bs-target="#colors">
                        <i class="fas fa-palette me-2"></i>Colores
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="typography-tab" data-bs-toggle="tab" data-bs-target="#typography">
                        <i class="fas fa-font me-2"></i>Tipografía
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="layout-tab" data-bs-toggle="tab" data-bs-target="#layout">
                        <i class="fas fa-th-large me-2"></i>Diseño
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="presets-tab" data-bs-toggle="tab" data-bs-target="#presets">
                        <i class="fas fa-magic me-2"></i>Temas Predefinidos
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" id="tools-tab" data-bs-toggle="tab" data-bs-target="#tools">
                        <i class="fas fa-tools me-2"></i>Herramientas
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- Colors Tab -->
                <div class="tab-pane fade show active" id="colors" role="tabpanel">
                    <div class="card-body">
                        <form method="POST" id="colorsForm">
                            <input type="hidden" name="action" value="update_colors">
                            
                            <h5 class="mb-4">
                                <i class="fas fa-palette me-2"></i>
                                Colores Principales
                            </h5>
                            <div class="row">
                                <div class="col-md-6 col-lg-4">
                                    <div class="mb-4">
                                        <label class="form-label">Color Primario</label>
                                        <div class="color-input-group">
                                            <div class="color-preview" style="background-color: <?php echo $current_theme['primary_color']; ?>"></div>
                                            <input type="color" class="color-input" name="primary_color" 
                                                   value="<?php echo $current_theme['primary_color']; ?>" 
                                                   onchange="updateColorPreview(this)">
                                            <div class="color-tooltip"><?php echo $current_theme['primary_color']; ?></div>
                                        </div>
                                        <small class="text-muted d-block mt-2">Color principal del sistema</small>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <div class="mb-4">
                                        <label class="form-label">Color Secundario</label>
                                        <div class="color-input-group">
                                            <div class="color-preview" style="background-color: <?php echo $current_theme['secondary_color']; ?>"></div>
                                            <input type="color" class="color-input" name="secondary_color" 
                                                   value="<?php echo $current_theme['secondary_color']; ?>"
                                                   onchange="updateColorPreview(this)">
                                            <div class="color-tooltip"><?php echo $current_theme['secondary_color']; ?></div>
                                        </div>
                                        <small class="text-muted d-block mt-2">Color para gradientes</small>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-4">
                                    <div class="mb-4">
                                        <label class="form-label">Color de Acento</label>
                                        <div class="color-input-group">
                                            <div class="color-preview" style="background-color: <?php echo $current_theme['accent_color']; ?>"></div>
                                            <input type="color" class="color-input" name="accent_color" 
                                                   value="<?php echo $current_theme['accent_color']; ?>"
                                                   onchange="updateColorPreview(this)">
                                            <div class="color-tooltip"><?php echo $current_theme['accent_color']; ?></div>
                                        </div>
                                        <small class="text-muted d-block mt-2">Color para destacar</small>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="mb-4 mt-5">
                                <i class="fas fa-traffic-light me-2"></i>
                                Colores de Estado
                            </h5>
                            <div class="row">
                                <div class="col-md-6 col-lg-3">
                                    <div class="mb-4">
                                        <label class="form-label">Éxito</label>
                                        <div class="color-input-group">
                                            <div class="color-preview" style="background-color: <?php echo $current_theme['success_color']; ?>"></div>
                                            <input type="color" class="color-input" name="success_color" 
                                                   value="<?php echo $current_theme['success_color']; ?>"
                                                   onchange="updateColorPreview(this)">
                                            <div class="color-tooltip"><?php echo $current_theme['success_color']; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="mb-4">
                                        <label class="form-label">Advertencia</label>
                                        <div class="color-input-group">
                                            <div class="color-preview" style="background-color: <?php echo $current_theme['warning_color']; ?>"></div>
                                            <input type="color" class="color-input" name="warning_color" 
                                                   value="<?php echo $current_theme['warning_color']; ?>"
                                                   onchange="updateColorPreview(this)">
                                            <div class="color-tooltip"><?php echo $current_theme['warning_color']; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="mb-4">
                                        <label class="form-label">Peligro</label>
                                        <div class="color-input-group">
                                            <div class="color-preview" style="background-color: <?php echo $current_theme['danger_color']; ?>"></div>
                                            <input type="color" class="color-input" name="danger_color" 
                                                   value="<?php echo $current_theme['danger_color']; ?>"
                                                   onchange="updateColorPreview(this)">
                                            <div class="color-tooltip"><?php echo $current_theme['danger_color']; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6 col-lg-3">
                                    <div class="mb-4">
                                        <label class="form-label">Información</label>
                                        <div class="color-input-group">
                                            <div class="color-preview" style="background-color: <?php echo $current_theme['info_color']; ?>"></div>
                                            <input type="color" class="color-input" name="info_color" 
                                                   value="<?php echo $current_theme['info_color']; ?>"
                                                   onchange="updateColorPreview(this)">
                                            <div class="color-tooltip"><?php echo $current_theme['info_color']; ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>
                                    Guardar Colores
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="resetColors()">
                                    <i class="fas fa-undo me-2"></i>
                                    Restablecer
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="generateRandomColors()">
                                    <i class="fas fa-random me-2"></i>
                                    Colores Aleatorios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Typography Tab -->
                <div class="tab-pane fade" id="typography" role="tabpanel">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_typography">
                            
                            <h5 class="mb-4">
                                <i class="fas fa-font me-2"></i>
                                Configuración de Fuentes
                            </h5>
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-4">
                                        <label class="form-label">Fuente Principal</label>
                                        <select class="form-select" name="font_family_primary" onchange="updateFontPreview()">
                                            <option value="'Segoe UI', Tahoma, Geneva, Verdana, sans-serif" 
                                                <?php echo (($current_theme['font_family_primary'] ?? '') === "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif") ? 'selected' : ''; ?>>
                                                Segoe UI (Predeterminada)
                                            </option>
                                            <option value="'Inter', -apple-system, BlinkMacSystemFont, sans-serif"
                                                <?php echo (($current_theme['font_family_primary'] ?? '') === "'Inter', -apple-system, BlinkMacSystemFont, sans-serif") ? 'selected' : ''; ?>>
                                                Inter (Moderna)
                                            </option>
                                            <option value="'Roboto', sans-serif"
                                                <?php echo (($current_theme['font_family_primary'] ?? '') === "'Roboto', sans-serif") ? 'selected' : ''; ?>>
                                                Roboto (Google)
                                            </option>
                                            <option value="'Open Sans', sans-serif"
                                                <?php echo (($current_theme['font_family_primary'] ?? '') === "'Open Sans', sans-serif") ? 'selected' : ''; ?>>
                                                Open Sans
                                            </option>
                                            <option value="'Montserrat', sans-serif"
                                                <?php echo (($current_theme['font_family_primary'] ?? '') === "'Montserrat', sans-serif") ? 'selected' : ''; ?>>
                                                Montserrat
                                            </option>
                                            <option value="'Poppins', sans-serif"
                                                <?php echo (($current_theme['font_family_primary'] ?? '') === "'Poppins', sans-serif") ? 'selected' : ''; ?>>
                                                Poppins
                                            </option>
                                        </select>
                                    </div>
                                    
                                    <div class="card mb-4" id="fontPreview" style="font-family: <?php echo $current_theme['font_family_primary'] ?? "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif"; ?>;">
                                        <div class="card-body">
                                            <h5 class="card-title">Vista Previa de la Fuente</h5>
                                            <p class="card-text">
                                                Este es un ejemplo de cómo se verá el texto con la fuente seleccionada. 
                                                Incluye números como 1234567890 y caracteres especiales como áéíóú ñÑ.
                                            </p>
                                            <h6>Título Secundario</h6>
                                            <small class="text-muted">Texto pequeño y contenido detallado</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <h6 class="mb-3">Tamaños de Fuente</h6>
                                    <div class="mb-3">
                                        <label class="form-label">Tamaño Base</label>
                                        <select class="form-select" name="font_size_base">
                                            <option value="14px" <?php echo (($current_theme['font_size_base'] ?? '') === '14px') ? 'selected' : ''; ?>>14px - Pequeño</option>
                                            <option value="16px" <?php echo (($current_theme['font_size_base'] ?? '16px') === '16px') ? 'selected' : ''; ?>>16px - Normal</option>
                                            <option value="18px" <?php echo (($current_theme['font_size_base'] ?? '') === '18px') ? 'selected' : ''; ?>>18px - Grande</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tamaño Pequeño</label>
                                        <select class="form-select" name="font_size_small">
                                            <option value="12px" <?php echo (($current_theme['font_size_small'] ?? '') === '12px') ? 'selected' : ''; ?>>12px</option>
                                            <option value="14px" <?php echo (($current_theme['font_size_small'] ?? '14px') === '14px') ? 'selected' : ''; ?>>14px</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Tamaño Grande</label>
                                        <select class="form-select" name="font_size_large">
                                            <option value="18px" <?php echo (($current_theme['font_size_large'] ?? '18px') === '18px') ? 'selected' : ''; ?>>18px</option>
                                            <option value="20px" <?php echo (($current_theme['font_size_large'] ?? '') === '20px') ? 'selected' : ''; ?>>20px</option>
                                            <option value="22px" <?php echo (($current_theme['font_size_large'] ?? '') === '22px') ? 'selected' : ''; ?>>22px</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-2"></i>
                                Guardar Tipografía
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Layout Tab -->
                <div class="tab-pane fade" id="layout" role="tabpanel">
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_layout">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h5 class="mb-4">
                                        <i class="fas fa-border-style me-2"></i>
                                        Bordes y Espaciado
                                    </h5>
                                    <div class="mb-3">
                                        <label class="form-label">Radio de Bordes Base</label>
                                        <select class="form-select" name="border_radius_base">
                                            <option value="4px" <?php echo (($current_theme['border_radius_base'] ?? '') === '4px') ? 'selected' : ''; ?>>4px - Angular</option>
                                            <option value="8px" <?php echo (($current_theme['border_radius_base'] ?? '8px') === '8px') ? 'selected' : ''; ?>>8px - Normal</option>
                                            <option value="12px" <?php echo (($current_theme['border_radius_base'] ?? '') === '12px') ? 'selected' : ''; ?>>12px - Redondeado</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Radio de Bordes Grande</label>
                                        <select class="form-select" name="border_radius_large">
                                            <option value="12px" <?php echo (($current_theme['border_radius_large'] ?? '') === '12px') ? 'selected' : ''; ?>>12px</option>
                                            <option value="15px" <?php echo (($current_theme['border_radius_large'] ?? '15px') === '15px') ? 'selected' : ''; ?>>15px</option>
                                            <option value="20px" <?php echo (($current_theme['border_radius_large'] ?? '') === '20px') ? 'selected' : ''; ?>>20px</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h5 class="mb-4">
                                        <i class="fas fa-columns me-2"></i>
                                        Sidebar
                                    </h5>
                                    <div class="mb-3">
                                        <label class="form-label">Ancho del Sidebar</label>
                                        <select class="form-select" name="sidebar_width">
                                            <option value="250px" <?php echo (($current_theme['sidebar_width'] ?? '') === '250px') ? 'selected' : ''; ?>>250px - Estrecho</option>
                                            <option value="280px" <?php echo (($current_theme['sidebar_width'] ?? '280px') === '280px') ? 'selected' : ''; ?>>280px - Normal</option>
                                            <option value="320px" <?php echo (($current_theme['sidebar_width'] ?? '') === '320px') ? 'selected' : ''; ?>>320px - Amplio</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <h5 class="mb-4 mt-4">
                                <i class="fas fa-layer-group me-2"></i>
                                Efectos Visuales
                            </h5>
                            <div class="mb-4">
                                <label class="form-label">Intensidad de Sombras</label>
                                <select class="form-select" name="shadow_base">
                                    <option value="0 2px 8px rgba(0, 0, 0, 0.05)" 
                                        <?php echo (($current_theme['shadow_base'] ?? '') === '0 2px 8px rgba(0, 0, 0, 0.05)') ? 'selected' : ''; ?>>
                                        Sutil
                                    </option>
                                    <option value="0 5px 15px rgba(0, 0, 0, 0.08)" 
                                        <?php echo (($current_theme['shadow_base'] ?? '0 5px 15px rgba(0, 0, 0, 0.08)') === '0 5px 15px rgba(0, 0, 0, 0.08)') ? 'selected' : ''; ?>>
                                        Normal
                                    </option>
                                    <option value="0 8px 25px rgba(0, 0, 0, 0.12)" 
                                        <?php echo (($current_theme['shadow_base'] ?? '') === '0 8px 25px rgba(0, 0, 0, 0.12)') ? 'selected' : ''; ?>>
                                        Pronunciada
                                    </option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save me-2"></i>
                                Guardar Diseño
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Presets Tab -->
                <div class="tab-pane fade" id="presets" role="tabpanel">
                    <div class="card-body">
                        <h5 class="mb-4">
                            <i class="fas fa-magic me-2"></i>
                            Temas Predefinidos
                        </h5>
                        <p class="text-muted mb-4">Selecciona uno de los temas predefinidos para aplicar una combinación profesional de colores.</p>
                        
                        <div class="row">
                            <?php foreach ($presets as $key => $preset): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card preset-card" onclick="selectPreset('<?php echo $key; ?>')">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center mb-3">
                                                <div class="me-2" style="width: 32px; height: 32px; background: <?php echo $preset['primary_color']; ?>; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                                                <div class="me-2" style="width: 32px; height: 32px; background: <?php echo $preset['secondary_color'] ?? $preset['primary_color']; ?>; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                                                <div class="me-2" style="width: 32px; height: 32px; background: <?php echo $preset['accent_color'] ?? $preset['primary_color']; ?>; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);"></div>
                                            </div>
                                            <h6 class="card-title"><?php echo htmlspecialchars($preset['name']); ?></h6>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><?php echo ucfirst($key); ?></small>
                                                <button type="button" class="btn btn-sm btn-outline-primary" onclick="applyPreset('<?php echo $key; ?>', event)">
                                                    <i class="fas fa-paint-brush me-1"></i>
                                                    Aplicar
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Tools Tab -->
                <div class="tab-pane fade" id="tools" role="tabpanel">
                    <div class="card-body">
                        <h5 class="mb-4">
                            <i class="fas fa-tools me-2"></i>
                            Herramientas Avanzadas
                        </h5>
                        
                        <!-- Import/Export Section -->
                        <div class="row mb-5">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-download me-2"></i>
                                            Exportar Tema
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted small">Descarga la configuración actual del tema para respaldo o compartir.</p>
                                        <form method="POST" class="mb-3">
                                            <input type="hidden" name="action" value="export_theme">
                                            <div class="mb-3">
                                                <label class="form-label">Nombre del Tema</label>
                                                <input type="text" class="form-control" name="theme_name" value="Mi Tema Personalizado" placeholder="Nombre para el tema">
                                            </div>
                                            <button type="submit" class="btn btn-info">
                                                <i class="fas fa-download me-2"></i>
                                                Exportar Tema
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-upload me-2"></i>
                                            Importar Tema
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="text-muted small">Carga un archivo de tema previamente exportado.</p>
                                        <form method="POST" enctype="multipart/form-data" id="importForm">
                                            <input type="hidden" name="action" value="import_theme">
                                            <div class="mb-3">
                                                <label class="form-label">Archivo de Tema (.json)</label>
                                                <input type="file" class="form-control" name="theme_file" accept=".json" required>
                                            </div>
                                            <button type="submit" class="btn btn-secondary">
                                                <i class="fas fa-upload me-2"></i>
                                                Importar Tema
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Tools -->
                        <div class="row mb-5">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-cogs me-2"></i>
                                            Herramientas del Sistema
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <button type="button" class="btn btn-warning w-100" onclick="resetTheme()">
                                                    <i class="fas fa-undo me-2"></i>
                                                    Restablecer
                                                </button>
                                                <small class="text-muted d-block mt-1">Volver a configuración por defecto</small>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <button type="button" class="btn btn-success w-100" onclick="generateCSS()">
                                                    <i class="fas fa-code me-2"></i>
                                                    Regenerar CSS
                                                </button>
                                                <small class="text-muted d-block mt-1">Actualizar archivo CSS</small>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <button type="button" class="btn btn-info w-100" onclick="testNotifications()">
                                                    <i class="fas fa-bell me-2"></i>
                                                    Probar Alertas
                                                </button>
                                                <small class="text-muted d-block mt-1">Verificar notificaciones</small>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <button type="button" class="btn btn-primary w-100" onclick="createBackup()">
                                                    <i class="fas fa-save me-2"></i>
                                                    Crear Backup
                                                </button>
                                                <small class="text-muted d-block mt-1">Respaldo de seguridad</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Color Tools -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0">
                                            <i class="fas fa-palette me-2"></i>
                                            Herramientas de Color
                                        </h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <button type="button" class="btn btn-outline-primary w-100" onclick="generateRandomColors()">
                                                    <i class="fas fa-random me-2"></i>
                                                    Colores Aleatorios
                                                </button>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <button type="button" class="btn btn-outline-secondary w-100" onclick="generateComplementaryColors()">
                                                    <i class="fas fa-adjust me-2"></i>
                                                    Colores Complementarios
                                                </button>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <button type="button" class="btn btn-outline-success w-100" onclick="generateAnalogousColors()">
                                                    <i class="fas fa-circle-notch me-2"></i>
                                                    Colores Análogos
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="alert alert-info mt-3">
                                            <h6><i class="fas fa-lightbulb me-2"></i>Consejos de Color</h6>
                                            <ul class="mb-0 small">
                                                <li><strong>Aleatorios:</strong> Genera una paleta completamente nueva</li>
                                                <li><strong>Complementarios:</strong> Colores opuestos para alto contraste</li>
                                                <li><strong>Análogos:</strong> Colores adyacentes para armonía visual</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-rocket me-2"></i>
                            Acciones Rápidas
                        </h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="../index.php" target="_blank" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-external-link-alt me-1"></i>
                                Ver Sitio Web
                            </a>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="previewChanges()">
                                <i class="fas fa-eye me-1"></i>
                                Vista Previa
                            </button>
                            <a href="settings.php" class="btn btn-outline-secondary btn-sm">
                                <i class="fas fa-cog me-1"></i>
                                Configuración General
                            </a>
                            <button type="button" class="btn btn-outline-info btn-sm" onclick="validateTheme()">
                                <i class="fas fa-check-circle me-1"></i>
                                Validar Tema
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmación -->
    <div class="modal fade" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-question-circle me-2"></i>
                        Confirmar Acción
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p id="confirmMessage">¿Está seguro de que desea realizar esta acción?</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="confirmButton">Confirmar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de Vista Previa -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-eye me-2"></i>
                        Vista Previa del Tema
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <iframe id="previewFrame" src="../index.php" style="width: 100%; height: 600px; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <a href="../index.php" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt me-1"></i>
                        Abrir en Nueva Ventana
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer">
        <!-- Los toasts se insertarán aquí dinámicamente -->
    </div>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Variables globales
        let selectedPreset = null;
        let hasUnsavedChanges = false;
        let originalColors = {};
        
        // Colores del tema actual desde PHP
        const currentTheme = {
            primary: '<?php echo $current_theme['primary_color']; ?>',
            secondary: '<?php echo $current_theme['secondary_color']; ?>',
            accent: '<?php echo $current_theme['accent_color']; ?>',
            success: '<?php echo $current_theme['success_color']; ?>',
            warning: '<?php echo $current_theme['warning_color']; ?>',
            danger: '<?php echo $current_theme['danger_color']; ?>',
            info: '<?php echo $current_theme['info_color']; ?>'
        };

        // Inicialización cuando el DOM está listo
        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
            initializeColorPickers();
            initializeFontPreview();
            initializeFormValidation();
            initializeTooltips();
            saveOriginalColors();
            
            // Auto-dismiss alerts después de 5 segundos
            setTimeout(() => {
                document.querySelectorAll('.alert-dismissible').forEach(alert => {
                    const bsAlert = new bootstrap.Alert(alert);
                    if (bsAlert) bsAlert.close();
                });
            }, 5000);
            
            console.log('Sistema de temas inicializado correctamente');
        });

        // ========================================
        // MENÚ MÓVIL
        // ========================================
        function initializeMobileMenu() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const sidebarClose = document.getElementById('sidebarClose');

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleSidebar();
                });
            }

            if (sidebarClose) {
                sidebarClose.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeSidebar();
                });
            }

            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeSidebar();
                });
            }

            // Cerrar sidebar al hacer clic en enlaces (mobile)
            document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        setTimeout(closeSidebar, 100);
                    }
                });
            });

            // Cerrar sidebar en resize a desktop
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    closeSidebar();
                }
            });
        }

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            
            if (sidebar) sidebar.classList.remove('show');
            if (backdrop) backdrop.classList.remove('show');
            document.body.style.overflow = '';
        }

        // ========================================
        // COLOR PICKERS
        // ========================================
        function initializeColorPickers() {
            document.querySelectorAll('.color-input-group').forEach(group => {
                const input = group.querySelector('input[type="color"]');
                const preview = group.querySelector('.color-preview');
                
                if (input && preview) {
                    // Aplicar color inicial
                    preview.style.backgroundColor = input.value;
                    
                    // Event listeners
                    input.addEventListener('change', function() {
                        updateColorPreview(this);
                        updateLivePreview();
                        markAsUnsaved();
                    });

                    input.addEventListener('input', function() {
                        updateColorPreview(this);
                        updateLivePreview();
                    });
                    
                    // Hacer que el contenedor sea clickeable
                    group.addEventListener('click', function() {
                        input.click();
                    });
                }
            });
        }

        function updateColorPreview(input) {
            const colorGroup = input.closest('.color-input-group');
            const preview = colorGroup.querySelector('.color-preview');
            const tooltip = colorGroup.querySelector('.color-tooltip');
            
            if (preview) {
                preview.style.backgroundColor = input.value;
                
                // Actualizar tooltip
                if (tooltip) {
                    tooltip.textContent = input.value.toUpperCase();
                }
                
                // Actualizar CSS variables en tiempo real
                const cssVar = '--' + input.name.replace(/_/g, '-');
                document.documentElement.style.setProperty(cssVar, input.value);
                
                // Actualizar vista previa del tema
                updateThemePreview();
            }
        }

        function updateThemePreview() {
            const previewElements = {
                sidebar: document.querySelector('.sidebar-preview'),
                primaryText: document.querySelector('.theme-preview h6'),
                successBadge: document.querySelector('.theme-preview .badge'),
                primaryButton: document.querySelector('.theme-preview .btn')
            };

            const colors = getCurrentColors();

            if (previewElements.sidebar) {
                previewElements.sidebar.style.background = `linear-gradient(180deg, ${colors.primary} 0%, ${colors.secondary} 100%)`;
            }

            if (previewElements.primaryText) {
                previewElements.primaryText.style.color = colors.primary;
            }

            if (previewElements.successBadge) {
                previewElements.successBadge.style.background = colors.success;
            }

            if (previewElements.primaryButton) {
                previewElements.primaryButton.style.background = colors.accent;
            }
        }

        function getCurrentColors() {
            const form = document.getElementById('colorsForm');
            const colors = {};
            
            if (form) {
                const inputs = form.querySelectorAll('input[type="color"]');
                inputs.forEach(input => {
                    const key = input.name.replace('_color', '');
                    colors[key] = input.value;
                });
            }
            
            return colors;
        }

        function updateLivePreview() {
            const root = document.documentElement;
            const colorInputs = document.querySelectorAll('#colorsForm input[type="color"]');
            
            colorInputs.forEach(input => {
                const cssVar = '--' + input.name.replace(/_/g, '-');
                root.style.setProperty(cssVar, input.value);
            });
        }

        function saveOriginalColors() {
            document.querySelectorAll('input[type="color"]').forEach(input => {
                originalColors[input.name] = input.value;
            });
        }

        function resetColors() {
            if (Object.keys(originalColors).length === 0) {
                showToast('No hay colores originales para restaurar', 'warning');
                return;
            }

            showConfirmModal(
                '¿Restablecer colores originales?',
                'Se perderán los cambios no guardados en los colores.',
                function() {
                    Object.keys(originalColors).forEach(name => {
                        const input = document.querySelector(`input[name="${name}"]`);
                        if (input) {
                            input.value = originalColors[name];
                            updateColorPreview(input);
                        }
                    });
                    updateLivePreview();
                    updateThemePreview();
                    hasUnsavedChanges = false;
                    showToast('Colores restablecidos correctamente', 'success');
                }
            );
        }

        // ========================================
        // GENERADORES DE COLOR
        // ========================================
        function generateRandomColors() {
            const colorInputs = document.querySelectorAll('#colorsForm input[type="color"]');
            
            colorInputs.forEach(input => {
                const randomColor = '#' + Math.floor(Math.random()*16777215).toString(16).padStart(6, '0');
                input.value = randomColor;
                updateColorPreview(input);
            });
            
            updateLivePreview();
            updateThemePreview();
            markAsUnsaved();
            showToast('Colores aleatorios generados', 'success');
        }

        function generateComplementaryColors() {
            const primaryInput = document.querySelector('input[name="primary_color"]');
            if (!primaryInput) return;
            
            const primaryColor = hexToHsl(primaryInput.value);
            const complementaryHue = (primaryColor.h + 180) % 360;
            
            // Generar colores basados en el complementario
            const colors = {
                'secondary_color': hslToHex(complementaryHue, primaryColor.s, Math.max(primaryColor.l - 10, 10)),
                'accent_color': hslToHex((primaryColor.h + 30) % 360, primaryColor.s, primaryColor.l),
                'success_color': hslToHex(120, 60, 45),
                'warning_color': hslToHex(45, 100, 50),
                'danger_color': hslToHex(0, 75, 55),
                'info_color': hslToHex(200, 75, 50)
            };
            
            Object.entries(colors).forEach(([name, color]) => {
                const input = document.querySelector(`input[name="${name}"]`);
                if (input) {
                    input.value = color;
                    updateColorPreview(input);
                }
            });
            
            updateLivePreview();
            updateThemePreview();
            markAsUnsaved();
            showToast('Colores complementarios generados', 'success');
        }

        function generateAnalogousColors() {
            const primaryInput = document.querySelector('input[name="primary_color"]');
            if (!primaryInput) return;
            
            const primaryColor = hexToHsl(primaryInput.value);
            
            // Generar colores análogos (30 grados de diferencia)
            const colors = {
                'secondary_color': hslToHex((primaryColor.h + 30) % 360, primaryColor.s, Math.max(primaryColor.l - 15, 10)),
                'accent_color': hslToHex((primaryColor.h - 30 + 360) % 360, primaryColor.s, Math.min(primaryColor.l + 15, 90)),
                'success_color': hslToHex((primaryColor.h + 60) % 360, 60, 45),
                'warning_color': hslToHex((primaryColor.h + 90) % 360, 80, 50),
                'danger_color': hslToHex((primaryColor.h + 180) % 360, 75, 55),
                'info_color': hslToHex((primaryColor.h - 60 + 360) % 360, 70, 50)
            };
            
            Object.entries(colors).forEach(([name, color]) => {
                const input = document.querySelector(`input[name="${name}"]`);
                if (input) {
                    input.value = color;
                    updateColorPreview(input);
                }
            });
            
            updateLivePreview();
            updateThemePreview();
            markAsUnsaved();
            showToast('Colores análogos generados', 'success');
        }

        // ========================================
        // UTILIDADES DE COLOR
        // ========================================
        function hexToHsl(hex) {
            const r = parseInt(hex.slice(1, 3), 16) / 255;
            const g = parseInt(hex.slice(3, 5), 16) / 255;
            const b = parseInt(hex.slice(5, 7), 16) / 255;
            
            const max = Math.max(r, g, b);
            const min = Math.min(r, g, b);
            let h, s, l = (max + min) / 2;
            
            if (max === min) {
                h = s = 0;
            } else {
                const d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                switch (max) {
                    case r: h = (g - b) / d + (g < b ? 6 : 0); break;
                    case g: h = (b - r) / d + 2; break;
                    case b: h = (r - g) / d + 4; break;
                }
                h /= 6;
            }
            
            return {
                h: Math.round(h * 360),
                s: Math.round(s * 100),
                l: Math.round(l * 100)
            };
        }

        function hslToHex(h, s, l) {
            h /= 360;
            s /= 100;
            l /= 100;
            
            const hue2rgb = (p, q, t) => {
                if (t < 0) t += 1;
                if (t > 1) t -= 1;
                if (t < 1/6) return p + (q - p) * 6 * t;
                if (t < 1/2) return q;
                if (t < 2/3) return p + (q - p) * (2/3 - t) * 6;
                return p;
            };
            
            let r, g, b;
            
            if (s === 0) {
                r = g = b = l;
            } else {
                const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
                const p = 2 * l - q;
                r = hue2rgb(p, q, h + 1/3);
                g = hue2rgb(p, q, h);
                b = hue2rgb(p, q, h - 1/3);
            }
            
            const toHex = (c) => {
                const hex = Math.round(c * 255).toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            };
            
            return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
        }

        // ========================================
        // TIPOGRAFÍA
        // ========================================
        function initializeFontPreview() {
            const fontSelect = document.querySelector('select[name="font_family_primary"]');
            const preview = document.getElementById('fontPreview');
            
            if (fontSelect && preview) {
                updateFontPreview();
                
                fontSelect.addEventListener('change', function() {
                    updateFontPreview();
                    markAsUnsaved();
                });
            }
        }

        function updateFontPreview() {
            const fontSelect = document.querySelector('select[name="font_family_primary"]');
            const preview = document.getElementById('fontPreview');
            
            if (fontSelect && preview) {
                preview.style.fontFamily = fontSelect.value;
                preview.style.transition = 'font-family 0.3s ease';
            }
        }

        // ========================================
        // TEMAS PREDEFINIDOS
        // ========================================
        function selectPreset(presetKey) {
            selectedPreset = presetKey;
            
            // Remover clase selected de todas las tarjetas
            document.querySelectorAll('.preset-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Agregar clase selected a la tarjeta clickeada
            event.currentTarget.classList.add('selected');
            
            showToast(`Tema "${presetKey}" seleccionado`, 'info');
        }

        function applyPreset(presetKey, event) {
            event.stopPropagation();
            
            const presetNames = {
                'default': 'Predeterminado',
                'dark': 'Oscuro',
                'green': 'Verde',
                'purple': 'Morado',
                'blue': 'Azul',
                'orange': 'Naranja'
            };
            
            const presetName = presetNames[presetKey] || presetKey;
            
            showConfirmModal(
                `¿Aplicar tema "${presetName}"?`,
                'Se aplicará el tema seleccionado y se perderán los cambios no guardados.',
                function() {
                    showLoadingState();
                    
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = `
                        <input type="hidden" name="action" value="apply_preset">
                        <input type="hidden" name="preset" value="${presetKey}">
                    `;
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        }

        function resetTheme() {
            showConfirmModal(
                '¿Restablecer tema predeterminado?',
                'Se restablecerán todos los valores a la configuración predeterminada. Esta acción no se puede deshacer.',
                function() {
                    showLoadingState();
                    
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.innerHTML = '<input type="hidden" name="action" value="reset_theme">';
                    document.body.appendChild(form);
                    form.submit();
                }
            );
        }

// ========================================
        // HERRAMIENTAS AVANZADAS
        // ========================================
        function createBackup() {
            showLoadingState();
            
            // Simular creación de backup
            setTimeout(() => {
                hideLoadingState();
                showToast('Backup creado correctamente', 'success');
            }, 2000);
        }

        function validateTheme() {
            let issues = [];
            
            // Validar colores
            const colorInputs = document.querySelectorAll('input[type="color"]');
            colorInputs.forEach(input => {
                if (!isValidHexColor(input.value)) {
                    issues.push(`Color inválido: ${input.name}`);
                }
            });
            
            // Validar que hay configuraciones básicas
            if (colorInputs.length === 0) {
                issues.push('No se encontraron configuraciones de color');
            }
            
            if (issues.length === 0) {
                showToast('Tema validado correctamente - Sin problemas encontrados', 'success');
            } else {
                showToast(`Problemas encontrados: ${issues.join(', ')}`, 'warning');
            }
        }

        function generateCSS() {
            showLoadingState();
            
            fetch('api/regenerate-css.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'regenerate'
                })
            })
            .then(response => response.json())
            .then(data => {
                hideLoadingState();
                if (data.success) {
                    showToast('CSS regenerado correctamente', 'success');
                    // Recargar CSS
                    location.reload();
                } else {
                    showToast('Error al regenerar CSS: ' + (data.message || 'Error desconocido'), 'error');
                }
            })
            .catch(error => {
                hideLoadingState();
                console.error('Error:', error);
                showToast('Error de conexión al regenerar CSS', 'error');
            });
        }

        function testNotifications() {
            showToast('Prueba de notificación exitosa', 'success');
            
            setTimeout(() => {
                showToast('Esta es una notificación de información', 'info');
            }, 1000);
            
            setTimeout(() => {
                showToast('Esta es una notificación de advertencia', 'warning');
            }, 2000);
            
            setTimeout(() => {
                showToast('Esta es una notificación de error', 'error');
            }, 3000);
        }

        // ========================================
        // VALIDACIÓN Y UTILIDADES
        // ========================================
        function isValidHexColor(color) {
            return /^#[a-f0-9]{6}$/i.test(color);
        }

        function initializeFormValidation() {
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitButton = form.querySelector('button[type="submit"]');
                    
                    if (submitButton) {
                        showLoadingButton(submitButton);
                    }
                    
                    // Validaciones específicas
                    if (!validateForm(form)) {
                        e.preventDefault();
                        if (submitButton) {
                            hideLoadingButton(submitButton);
                        }
                        return false;
                    }
                });
            });

            // Detectar cambios para marcar como no guardado
            document.querySelectorAll('input, select, textarea').forEach(input => {
                input.addEventListener('change', function() {
                    markAsUnsaved();
                });
            });
        }

        function validateForm(form) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                showToast('Por favor complete todos los campos requeridos', 'error');
            }
            
            return isValid;
        }

        function markAsUnsaved() {
            hasUnsavedChanges = true;
            
            // Agregar indicador visual si no existe
            if (!document.querySelector('.unsaved-indicator')) {
                const indicator = document.createElement('span');
                indicator.className = 'badge bg-warning unsaved-indicator ms-2';
                indicator.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i>Cambios sin guardar';
                
                const pageTitle = document.querySelector('.page-header h2');
                if (pageTitle) {
                    pageTitle.appendChild(indicator);
                }
            }
        }

        function clearUnsavedIndicator() {
            const indicator = document.querySelector('.unsaved-indicator');
            if (indicator) {
                indicator.remove();
            }
            hasUnsavedChanges = false;
        }

        // ========================================
        // VISTA PREVIA
        // ========================================
        function previewChanges() {
            const modal = new bootstrap.Modal(document.getElementById('previewModal'));
            const iframe = document.getElementById('previewFrame');
            
            // Recargar iframe con timestamp para evitar cache
            iframe.src = '../index.php?preview=1&t=' + Date.now();
            
            modal.show();
            
            showToast('Cargando vista previa...', 'info');
        }

        // ========================================
        // MODALES Y TOOLTIPS
        // ========================================
        function initializeTooltips() {
            // Inicializar tooltips de Bootstrap si están disponibles
            if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            }
        }

        function showConfirmModal(title, message, onConfirm) {
            const modal = document.getElementById('confirmModal');
            const titleEl = modal.querySelector('.modal-title');
            const messageEl = modal.querySelector('#confirmMessage');
            const confirmBtn = modal.querySelector('#confirmButton');
            
            titleEl.innerHTML = '<i class="fas fa-question-circle me-2"></i>' + title;
            messageEl.textContent = message;
            
            // Limpiar eventos previos
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
            
            // Agregar nuevo evento
            newConfirmBtn.addEventListener('click', function() {
                const bsModal = bootstrap.Modal.getInstance(modal);
                bsModal.hide();
                if (typeof onConfirm === 'function') {
                    onConfirm();
                }
            });
            
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        }

        // ========================================
        // SISTEMA DE NOTIFICACIONES (TOASTS)
        // ========================================
        function showToast(message, type = 'info', duration = 5000) {
            const toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) return;
            
            const toastId = 'toast_' + Date.now();
            const iconClasses = {
                'success': 'fas fa-check-circle text-success',
                'error': 'fas fa-exclamation-circle text-danger',
                'warning': 'fas fa-exclamation-triangle text-warning',
                'info': 'fas fa-info-circle text-info'
            };
            
            const backgroundClasses = {
                'success': 'bg-success',
                'error': 'bg-danger',
                'warning': 'bg-warning',
                'info': 'bg-info'
            };
            
            const toastHtml = `
                <div id="${toastId}" class="toast align-items-center text-white ${backgroundClasses[type] || 'bg-info'}" role="alert">
                    <div class="d-flex">
                        <div class="toast-body d-flex align-items-center">
                            <i class="${iconClasses[type] || 'fas fa-info-circle'} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            toastContainer.insertAdjacentHTML('beforeend', toastHtml);
            
            const toastElement = document.getElementById(toastId);
            const toast = new bootstrap.Toast(toastElement, {
                delay: duration
            });
            
            toast.show();
            
            // Limpiar después de que se oculte
            toastElement.addEventListener('hidden.bs.toast', function() {
                toastElement.remove();
            });
        }

        // ========================================
        // ESTADOS DE CARGA
        // ========================================
        function showLoadingState() {
            const overlay = document.createElement('div');
            overlay.id = 'loadingOverlay';
            overlay.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                           background: rgba(0,0,0,0.5); display: flex; justify-content: center; 
                           align-items: center; z-index: 9999;">
                    <div style="background: white; padding: 2rem; border-radius: 15px; text-align: center;">
                        <div class="spinner-border text-primary mb-3" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <div>Procesando cambios...</div>
                    </div>
                </div>
            `;
            document.body.appendChild(overlay);
        }

        function hideLoadingState() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.remove();
            }
        }

        function showLoadingButton(button) {
            const originalText = button.innerHTML;
            button.dataset.originalText = originalText;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Guardando...';
        }

        function hideLoadingButton(button) {
            if (button.dataset.originalText) {
                button.innerHTML = button.dataset.originalText;
                button.disabled = false;
                delete button.dataset.originalText;
            }
        }

        // ========================================
        // ATAJOS DE TECLADO
        // ========================================
        document.addEventListener('keydown', function(e) {
            // Ctrl+S para guardar
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                const activeForm = document.querySelector('.tab-pane.active form');
                if (activeForm) {
                    activeForm.submit();
                }
                return false;
            }
            
            // Ctrl+Z para deshacer cambios de colores
            if (e.ctrlKey && e.key === 'z') {
                e.preventDefault();
                resetColors();
                return false;
            }
            
            // Escape para cerrar modales
            if (e.key === 'Escape') {
                closeSidebar();
            }
        });

        // ========================================
        // PREVENCIÓN DE PÉRDIDA DE DATOS
        // ========================================
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = '¿Seguro que desea salir? Hay cambios sin guardar.';
                return e.returnValue;
            }
        });

        // Limpiar indicador cuando se guarda exitosamente
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                setTimeout(() => {
                    // Si no hay errores después del submit, limpiar indicador
                    if (!document.querySelector('.alert-danger')) {
                        clearUnsavedIndicator();
                    }
                }, 1000);
            });
        });

        // ========================================
        // FUNCIONES DE DEBUG
        // ========================================
        window.themeDebug = {
            getCurrentColors: getCurrentColors,
            showToast: showToast,
            resetColors: resetColors,
            testNotifications: testNotifications,
            validateTheme: validateTheme,
            generateRandomColors: generateRandomColors,
            generateComplementaryColors: generateComplementaryColors,
            generateAnalogousColors: generateAnalogousColors,
            showLoadingState: showLoadingState,
            hideLoadingState: hideLoadingState
        };

        console.log('Sistema de configuración de temas cargado completamente');
        console.log('Funciones de debug disponibles en: window.themeDebug');
    </script>
</body>
</html>