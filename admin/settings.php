<?php
// admin/settings.php
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

// AGREGAR ESTAS LÍNEAS:
// Obtener información del usuario actual
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role_name'] ?? 'usuario';

// Verificar si hay estadísticas disponibles (opcional)
$stats = array();
$online_stats = array();

// Obtener o crear commit hash del sistema
$commit_query = "SELECT setting_value FROM settings WHERE setting_key = 'system_commit'";
$commit_stmt = $db->prepare($commit_query);
$commit_stmt->execute();
$commit_result = $commit_stmt->fetch();

if (!$commit_result) {
    // Si no existe, crear con valor inicial
    $initial_commit = 'initial';
    $insert_commit = "INSERT INTO settings (setting_key, setting_value, description) 
                      VALUES ('system_commit', ?, 'Hash del último commit instalado')";
    $stmt = $db->prepare($insert_commit);
    $stmt->execute([$initial_commit]);
}

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_general':
            $result = updateGeneralSettings();
            break;
        case 'update_business':
            $result = updateBusinessSettings();
            break;
        case 'update_system':
            $result = updateSystemSettings();
            break;
        case 'update_online_orders':
            $result = updateOnlineOrdersSettings();
            break;
        case 'update_github_config':
            $result = updateGithubConfig();
            break;
    }
    
    if (isset($result['success']) && $result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'] ?? 'Error desconocido';
    }
}

function updateGeneralSettings() {
    global $db;
    
    $settings = [
        'restaurant_name' => sanitize($_POST['restaurant_name']),
        'restaurant_phone' => sanitize($_POST['restaurant_phone']),
        'restaurant_address' => sanitize($_POST['restaurant_address']),
        'whatsapp_number' => sanitize($_POST['whatsapp_number']),
        'restaurant_email' => sanitize($_POST['restaurant_email'] ?? ''),
        'restaurant_maps_url' => sanitize($_POST['restaurant_maps_url'] ?? '')
    ];
    
    try {
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value, $value]);
        }
        
        // Si se proporcionó una URL de Google Maps, extraer y guardar las coordenadas
        $maps_url = $settings['restaurant_maps_url'];
        if (!empty($maps_url)) {
            $coordinates = extractCoordinatesFromMapsUrl($maps_url);
            if ($coordinates) {
                // Guardar las coordenadas extraídas
                $coord_settings = [
                    'restaurant_lat' => $coordinates['lat'],
                    'restaurant_lng' => $coordinates['lng']
                ];
                
                foreach ($coord_settings as $key => $value) {
                    $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$key, $value, $value]);
                }
            }
        }
        
        return ['success' => true, 'message' => 'Configuración general actualizada. ' . 
                (isset($coordinates) ? 'Coordenadas extraídas automáticamente desde Google Maps.' : '')];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Extrae las coordenadas de una URL de Google Maps
 */
function extractCoordinatesFromMapsUrl($url) {
    if (empty($url)) {
        return null;
    }
    
    // Varios patrones para extraer coordenadas de URLs de Google Maps
    $patterns = [
        // Patrón: @lat,lng,zoom
        '/@(-?\d+\.?\d*),(-?\d+\.?\d*),/',
        // Patrón: ?q=lat,lng
        '/[?&]q=(-?\d+\.?\d*),(-?\d+\.?\d*)/',
        // Patrón: ll=lat,lng
        '/[?&]ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/',
        // Patrón: center=lat,lng
        '/[?&]center=(-?\d+\.?\d*),(-?\d+\.?\d*)/',
        // Patrón: /place/name/@lat,lng
        '/\/place\/[^\/]*\/@(-?\d+\.?\d*),(-?\d+\.?\d*)/',
        // Patrón: !3d-lat!4d-lng (formato de URLs compartidas)
        '/!3d(-?\d+\.?\d*)!4d(-?\d+\.?\d*)/',
        // Patrón: data=...lat,lng...
        '/data=[^&]*!2d(-?\d+\.?\d*)!3d(-?\d+\.?\d*)/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $matches)) {
            $lat = floatval($matches[1]);
            $lng = floatval($matches[2]);
            
            // Validar que las coordenadas estén en rangos válidos
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return [
                    'lat' => $lat,
                    'lng' => $lng
                ];
            }
        }
    }
    
    return null;
}


function updateBusinessSettings() {
    global $db;
    
    $settings = [
        'tax_rate' => floatval($_POST['tax_rate']),
        'delivery_fee' => floatval($_POST['delivery_fee']),
        'currency_symbol' => sanitize($_POST['currency_symbol']),
        'max_delivery_distance' => floatval($_POST['max_delivery_distance']),
        'min_delivery_amount' => floatval($_POST['min_delivery_amount'])
    ];
    
    try {
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value, $value]);
        }
        
        return ['success' => true, 'message' => 'Configuración de negocio actualizada'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateSystemSettings() {
    global $db;
    
    $settings = [
        'system_timezone' => sanitize($_POST['system_timezone']),
        'date_format' => sanitize($_POST['date_format']),
        'auto_logout_time' => intval($_POST['auto_logout_time']),
        'max_orders_per_day' => intval($_POST['max_orders_per_day']),
        'backup_frequency' => sanitize($_POST['backup_frequency'])
    ];
    
    try {
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value, $value]);
        }
        
        return ['success' => true, 'message' => 'Configuración del sistema actualizada'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateOnlineOrdersSettings() {
    global $db;
    
    $settings = [
        'enable_online_orders' => isset($_POST['enable_online_orders']) ? '1' : '0',
        'google_maps_api_key' => sanitize($_POST['google_maps_api_key']),
        'opening_time' => sanitize($_POST['opening_time']),
        'closing_time' => sanitize($_POST['closing_time']),
        'kitchen_closing_time' => sanitize($_POST['kitchen_closing_time']),
        'order_timeout' => intval($_POST['order_timeout']),
        'notification_sound' => isset($_POST['notification_sound']) ? '1' : '0',
        'auto_print_orders' => isset($_POST['auto_print_orders']) ? '1' : '0',
        'show_delivery_zones' => isset($_POST['show_delivery_zones']) ? '1' : '0'
    ];
    
    try {
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value, $value]);
        }
        
        return ['success' => true, 'message' => 'Configuración de pedidos online actualizada'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateGithubConfig() {
    global $db;
    
    $settings = [
        'github_repo' => sanitize($_POST['github_repo']),
        'github_branch' => sanitize($_POST['github_branch']),
        'github_token' => sanitize($_POST['github_token'] ?? ''),
        'auto_backup_before_update' => isset($_POST['auto_backup_before_update']) ? '1' : '0'
    ];
    
    try {
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value, $value]);
        }
        
        return ['success' => true, 'message' => 'Configuración de GitHub actualizada'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Obtener todas las configuraciones
$query = "SELECT setting_key, setting_value FROM settings";
$stmt = $db->prepare($query);
$stmt->execute();
$current_settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración del Sistema - <?php echo $restaurant_name; ?></title>
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
    $theme_manager = new ThemeManager($db);
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
/* Extensiones específicas para settings */
:root {
    --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --settings-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
    --sidebar-mobile-width: 100%;
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

.mobile-topbar h5 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-white) !important;
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

/* Sidebar con colores dinámicos */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--settings-sidebar-width);
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

/* Main content con colores forzados claros */
.main-content {
    margin-left: var(--settings-sidebar-width);
    padding: 2rem;
    min-height: 100vh;
    transition: margin-left var(--transition-base);
    background: #f8f9fa !important;
    color: #212529 !important;
}

/* Forzar colores claros para el contenido */
.page-header {
            background: var(--primary-gradient) !important;
            color: var(--text-white, white) !important;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }

.stat-card {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    padding: 1.5rem;
    box-shadow: var(--shadow-base);
    text-align: center;
    height: 100%;
    transition: transform var(--transition-base);
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-number {
    font-size: 2.5rem;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

/* Settings section */
.settings-section {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
    overflow: hidden;
    margin-bottom: 2rem;
}

.nav-tabs {
    border-bottom: none;
    background: #f8f9fa !important;
    padding: 0;
}

.nav-tabs .nav-link {
    border-radius: 0;
    border: none;
    color: #6c757d !important;
    font-weight: 500;
    padding: 1rem 1.5rem;
    position: relative;
}

.nav-tabs .nav-link.active {
    background: #ffffff !important;
    color: #495057 !important;
    border-bottom: 3px solid var(--primary-color);
}

.tab-content {
    background: #ffffff !important;
    color: #212529 !important;
    padding: 2rem;
}

/* Form improvements */
.form-label {
    font-weight: 500;
    color: #495057 !important;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border-radius: var(--border-radius-base);
    border: 1px solid #dee2e6;
    padding: 0.75rem 1rem;
    transition: var(--transition-base);
    background: #ffffff !important;
    color: #212529 !important;
}

.form-control:focus, .form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    background: #ffffff !important;
    color: #212529 !important;
}

.form-text {
    color: #6c757d !important;
}

/* Button improvements */
.btn {
    border-radius: var(--border-radius-base);
    padding: 0.75rem 1.5rem;
    font-weight: 500;
    transition: var(--transition-base);
}

.btn-primary {
    background: var(--primary-gradient);
    border: none;
    color: var(--text-white) !important;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    color: var(--text-white) !important;
}

.btn-success {
    background: linear-gradient(45deg, var(--success-color), #20c997) !important;
    border: none;
    color: var(--text-white) !important;
}

.btn-warning {
    background: linear-gradient(45deg, var(--warning-color), #fd7e14) !important;
    border: none;
    color: #212529 !important;
}

.btn-info {
    background: linear-gradient(45deg, var(--info-color), #6f42c1) !important;
    border: none;
    color: var(--text-white) !important;
}

/* Switch styles */
.form-switch .form-check-input {
    width: 3rem;
    height: 1.5rem;
}

.form-switch .form-check-input:checked {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

/* API Key field */
.api-key-field {
    position: relative;
}

.api-key-toggle {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
}

.alert {
    border-radius: var(--border-radius-base);
    border: none;
    background: #ffffff !important;
    color: #212529 !important;
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

.alert-info {
    background: rgba(23, 162, 184, 0.1) !important;
    color: var(--info-color) !important;
    border-left: 4px solid var(--info-color);
}

.card {
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
    background: #ffffff !important;
    color: #212529 !important;
}

/* Text colors forzados */
.text-muted {
    color: #6c757d !important;
}

.text-primary {
    color: var(--primary-color) !important;
}

.text-success {
    color: var(--success-color) !important;
}

.text-warning {
    color: #856404 !important;
}

.text-danger {
    color: var(--danger-color) !important;
}

.text-info {
    color: var(--info-color) !important;
}

.text-secondary {
    color: #6c757d !important;
}

h1, h2, h3, h4, h5, h6 {
    color: #212529 !important;
}

p {
    color: #212529 !important;
}

.loading-spinner {
    display: inline-block;
    width: 20px;
    height: 20px;
    border: 3px solid rgba(255,255,255,.3);
    border-radius: 50%;
    border-top-color: #fff;
    animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

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

/* Responsive */
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
    }

    .main-content {
        margin-left: 0;
        padding: 1rem;
        padding-top: 5rem;
    }

    .page-header {
        padding: 1rem;
    }

    .page-header h2 {
        font-size: 1.5rem;
    }

    .stat-card {
        margin-bottom: 1rem;
    }

    .stat-number {
        font-size: 2rem;
    }

    .tab-content {
        padding: 1rem;
    }

    .nav-tabs .nav-link {
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 0.5rem;
        padding-top: 4.5rem;
    }

    .page-header {
        padding: 0.75rem;
    }

    .page-header .d-flex {
        flex-direction: column;
        text-align: center;
        gap: 0.5rem;
    }

    .stat-number {
        font-size: 1.8rem;
    }

    .tab-content {
        padding: 0.75rem;
    }

    .nav-tabs .nav-link {
        padding: 0.5rem 0.75rem;
        font-size: 0.85rem;
    }

    .row > [class*="col-"] {
        margin-bottom: 1rem;
    }

    .btn {
        width: 100%;
        margin-bottom: 0.5rem;
    }
}

.step-by-step {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.step {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
}

.step-number {
    background: var(--primary-color);
    color: white;
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
}

.step-content p {
    margin: 0.25rem 0 0 0;
    color: #6c757d;
}
.system-header .container-fluid {
    height: 60px;
    display: flex;
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

<!-- Navigation Tabs -->
<div class="main-content">
    <div class="container-fluid">
        <div class="page-header mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="fas fa-cog me-2"></i>Configuración del Sistema
                    </h1>
                    <p class="text-muted mb-0 mt-2">Administra las configuraciones de tu restaurante</p>
                </div>
                <div>
                    <button class="btn btn-outline-secondary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-1"></i>Recargar
                    </button>
                </div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                    <i class="fas fa-store me-2"></i>General
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="business-tab" data-bs-toggle="tab" data-bs-target="#business" type="button">
                    <i class="fas fa-calculator me-2"></i>Negocio
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">
                    <i class="fas fa-server me-2"></i>Sistema
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="online-tab" data-bs-toggle="tab" data-bs-target="#online" type="button">
                    <i class="fas fa-globe me-2"></i>Pedidos Online
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="update-tab" data-bs-toggle="tab" data-bs-target="#update" type="button">
                    <i class="fas fa-sync-alt me-2"></i>Actualizar Sistema
                </button>
            </li>
        </ul>

        <div class="tab-content" id="settingsTabContent">
            <!-- General Settings Tab -->
            <div class="tab-pane fade show active" id="general" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-store me-2"></i>Información General del Restaurante</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_general">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre del Restaurante</label>
                                        <input type="text" class="form-control" name="restaurant_name" 
                                               value="<?php echo htmlspecialchars($current_settings['restaurant_name'] ?? ''); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Teléfono</label>
                                        <input type="tel" class="form-control" name="restaurant_phone" 
                                               value="<?php echo htmlspecialchars($current_settings['restaurant_phone'] ?? ''); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Dirección</label>
                                <input type="text" class="form-control" name="restaurant_address" 
                                       value="<?php echo htmlspecialchars($current_settings['restaurant_address'] ?? ''); ?>" required>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">WhatsApp</label>
                                        <input type="tel" class="form-control" name="whatsapp_number" 
                                               value="<?php echo htmlspecialchars($current_settings['whatsapp_number'] ?? ''); ?>" 
                                               placeholder="+549XXXXXXXXXX">
                                        <div class="form-text">Formato internacional: +549 seguido del número con código de área</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="restaurant_email" 
                                               value="<?php echo htmlspecialchars($current_settings['restaurant_email'] ?? ''); ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">
                                    URL de Google Maps
                                    <button type="button" class="btn btn-sm btn-link p-0" onclick="showMapsInstructions()">
                                        <i class="fas fa-question-circle"></i> ¿Cómo obtenerla?
                                    </button>
                                </label>
                                <input type="url" class="form-control" name="restaurant_maps_url" 
                                       value="<?php echo htmlspecialchars($current_settings['restaurant_maps_url'] ?? ''); ?>" 
                                       placeholder="https://maps.google.com/...">
                                <div class="form-text">
                                    <i class="fas fa-info-circle me-1"></i>
                                    El sistema extraerá automáticamente las coordenadas de ubicación desde esta URL
                                </div>
                                <div id="maps-coordinates-preview" class="alert alert-success mt-2" style="display: none;">
                                    <i class="fas fa-check-circle me-1"></i>
                                    <span id="coordinates-text"></span>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Business Settings Tab -->
            <div class="tab-pane fade" id="business" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-calculator me-2"></i>Configuración de Negocio</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_business">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tasa de Impuesto (%)</label>
                                        <input type="number" step="0.01" class="form-control" name="tax_rate" 
                                               value="<?php echo htmlspecialchars($current_settings['tax_rate'] ?? '0'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tarifa de Delivery</label>
                                        <input type="number" step="0.01" class="form-control" name="delivery_fee" 
                                               value="<?php echo htmlspecialchars($current_settings['delivery_fee'] ?? '0'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Símbolo de Moneda</label>
                                        <input type="text" class="form-control" name="currency_symbol" 
                                               value="<?php echo htmlspecialchars($current_settings['currency_symbol'] ?? '$'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Distancia Máxima (km)</label>
                                        <input type="number" step="0.1" class="form-control" name="max_delivery_distance" 
                                               value="<?php echo htmlspecialchars($current_settings['max_delivery_distance'] ?? '5'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Monto Mínimo de Pedido</label>
                                        <input type="number" step="0.01" class="form-control" name="min_delivery_amount" 
                                               value="<?php echo htmlspecialchars($current_settings['min_delivery_amount'] ?? '0'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Settings Tab -->
            <div class="tab-pane fade" id="system" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-server me-2"></i>Configuración del Sistema</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_system">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Zona Horaria</label>
                                        <select class="form-select" name="system_timezone" required>
                                            <option value="America/Argentina/Buenos_Aires" 
                                                    <?php echo ($current_settings['system_timezone'] ?? '') === 'America/Argentina/Buenos_Aires' ? 'selected' : ''; ?>>
                                                Buenos Aires (GMT-3)
                                            </option>
                                            <option value="America/Cordoba" 
                                                    <?php echo ($current_settings['system_timezone'] ?? '') === 'America/Cordoba' ? 'selected' : ''; ?>>
                                                Córdoba (GMT-3)
                                            </option>
                                            <option value="America/Argentina/Mendoza" 
                                                    <?php echo ($current_settings['system_timezone'] ?? '') === 'America/Argentina/Mendoza' ? 'selected' : ''; ?>>
                                                Mendoza (GMT-3)
                                            </option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Formato de Fecha</label>
                                        <select class="form-select" name="date_format" required>
                                            <option value="d/m/Y" <?php echo ($current_settings['date_format'] ?? '') === 'd/m/Y' ? 'selected' : ''; ?>>
                                                DD/MM/AAAA
                                            </option>
                                            <option value="m/d/Y" <?php echo ($current_settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>
                                                MM/DD/AAAA
                                            </option>
                                            <option value="Y-m-d" <?php echo ($current_settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>
                                                AAAA-MM-DD
                                            </option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Tiempo de Auto-Cierre de Sesión (minutos)</label>
                                        <input type="number" class="form-control" name="auto_logout_time" 
                                               value="<?php echo htmlspecialchars($current_settings['auto_logout_time'] ?? '30'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Máximo de Pedidos por Día</label>
                                        <input type="number" class="form-control" name="max_orders_per_day" 
                                               value="<?php echo htmlspecialchars($current_settings['max_orders_per_day'] ?? '100'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Frecuencia de Backup</label>
                                <select class="form-select" name="backup_frequency" required>
                                    <option value="daily" <?php echo ($current_settings['backup_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>
                                        Diario
                                    </option>
                                    <option value="weekly" <?php echo ($current_settings['backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>
                                        Semanal
                                    </option>
                                    <option value="monthly" <?php echo ($current_settings['backup_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>
                                        Mensual
                                    </option>
                                </select>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Online Orders Settings Tab -->
            <div class="tab-pane fade" id="online" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-globe me-2"></i>Configuración de Pedidos Online</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_online_orders">
                            
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Nota:</strong> Para habilitar pedidos online, debes configurar tu API Key de Google Maps.
                            </div>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" id="enable_online_orders" 
                                       name="enable_online_orders" 
                                       <?php echo ($current_settings['enable_online_orders'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_online_orders">
                                    <strong>Habilitar Pedidos Online</strong>
                                </label>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Google Maps API Key</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="google_maps_api_key" 
                                           name="google_maps_api_key" 
                                           value="<?php echo htmlspecialchars($current_settings['google_maps_api_key'] ?? ''); ?>" 
                                           placeholder="AIza...">
                                    <button class="btn btn-outline-secondary" type="button" onclick="toggleApiKeyVisibility()">
                                        <i class="fas fa-eye" id="api-key-icon"></i>
                                    </button>
                                    <button class="btn btn-outline-info" type="button" onclick="testGoogleMapsAPI()">
                                        <i class="fas fa-vial me-1"></i>Probar
                                    </button>
                                </div>
                                <div class="form-text">
                                    Necesitas una API Key de Google Maps con Places API habilitado. 
                                    <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">
                                        Obtener API Key
                                    </a>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Hora de Apertura</label>
                                        <input type="time" class="form-control" name="opening_time" 
                                               value="<?php echo htmlspecialchars($current_settings['opening_time'] ?? '11:00'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Hora de Cierre</label>
                                        <input type="time" class="form-control" name="closing_time" 
                                               value="<?php echo htmlspecialchars($current_settings['closing_time'] ?? '23:00'); ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Cierre de Cocina</label>
                                        <input type="time" class="form-control" name="kitchen_closing_time" 
                                               value="<?php echo htmlspecialchars($current_settings['kitchen_closing_time'] ?? '22:30'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tiempo Límite de Pedido (minutos)</label>
                                <input type="number" class="form-control" name="order_timeout" 
                                       value="<?php echo htmlspecialchars($current_settings['order_timeout'] ?? '15'); ?>" required>
                                <div class="form-text">Tiempo máximo para confirmar un pedido antes de que expire</div>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="notification_sound" 
                                       name="notification_sound" 
                                       <?php echo ($current_settings['notification_sound'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="notification_sound">
                                    Sonido de notificación para nuevos pedidos
                                </label>
                                <button type="button" class="btn btn-sm btn-link" onclick="testNotificationSound()">
                                    <i class="fas fa-volume-up"></i> Probar sonido
                                </button>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="auto_print_orders" 
                                       name="auto_print_orders" 
                                       <?php echo ($current_settings['auto_print_orders'] ?? '0') === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="auto_print_orders">
                                    Imprimir automáticamente pedidos nuevos
                                </label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="show_delivery_zones" 
                                       name="show_delivery_zones" 
                                       <?php echo ($current_settings['show_delivery_zones'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="show_delivery_zones">
                                    Mostrar zonas de delivery en mapa
                                </label>
                            </div>

                            <div class="text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Update System Tab -->
            <div class="tab-pane fade" id="update" role="tabpanel">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Sistema de Actualización Automática</strong><br>
                    Esta herramienta descarga y aplica automáticamente las últimas actualizaciones desde GitHub.
                    Se creará un backup antes de actualizar para poder revertir cambios si es necesario.
                </div>

                <!-- Estado de actualización -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-code-branch me-2"></i>
                                    Versión Actual
                                </h5>
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <p class="mb-1 text-muted small">Versión del Sistema</p>
                                        <h3 class="mb-0" id="current-version">
                                            <?php echo $current_settings['current_system_version'] ?? '2.1.0'; ?>
                                        </h3>
                                    </div>
                                    <div class="text-end">
                                        <p class="mb-1 text-muted small">Commit</p>
                                        <code id="current-commit">Verificando...</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-cloud-download-alt me-2"></i>
                                    Estado de Actualizaciones
                                </h5>
                                <div id="update-status">
                                    <p class="text-muted">
                                        <i class="fas fa-spinner fa-spin me-2"></i>
                                        Verificando actualizaciones...
                                    </p>
                                </div>
                                <button class="btn btn-primary btn-sm" onclick="checkForUpdates()">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Verificar Ahora
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Panel de cambios -->
                <div id="changes-panel" class="card mb-4" style="display: none;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-list-ul me-2"></i>
                            Cambios Disponibles
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center p-3 bg-success rounded">
                                    <h2 class="text-white mb-0" id="files-added">0</h2>
                                    <small class="text-white">Archivos Nuevos</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 bg-warning rounded">
                                    <h2 class="text-white mb-0" id="files-modified">0</h2>
                                    <small class="text-white">Archivos Modificados</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center p-3 bg-danger rounded">
                                    <h2 class="text-white mb-0" id="files-removed">0</h2>
                                    <small class="text-white">Archivos Eliminados</small>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <h6>Últimos Commits:</h6>
                            <div id="commits-list" class="list-group">
                                <!-- Se llenará dinámicamente -->
                            </div>
                        </div>

                        <div class="mt-4">
                            <button class="btn btn-success btn-lg" onclick="performUpdate()">
                                <i class="fas fa-download me-2"></i>
                                Actualizar Sistema Ahora
                            </button>
                            <button class="btn btn-outline-secondary" onclick="showAvailableChanges()">
                                <i class="fas fa-eye me-1"></i>
                                Ver Detalles
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Panel de progreso -->
                <div id="progress-panel" class="card mb-4" style="display: none;">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-spinner fa-spin me-2"></i>
                            Actualizando Sistema
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="progress mb-3" style="height: 30px;">
                            <div id="update-progress" class="progress-bar progress-bar-striped progress-bar-animated" 
                                 role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div id="progress-status" class="text-center">
                            <p class="mb-0">Iniciando actualización...</p>
                        </div>
                    </div>
                </div>

                <!-- Activación de Licencia -->
<div class="card mb-4">
    <div class="card-header bg-warning text-dark">
        <h5 class="mb-0">
            <i class="fas fa-key me-2"></i>
            Activación de Licencia
        </h5>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            <strong>¿Cómo obtener tu licencia?</strong><br>
            1. Copia tu System ID de abajo<br>
            2. Envíalo al desarrollador por WhatsApp o Email<br>
            3. El desarrollador te enviará tu clave de licencia<br>
            4. Pega la clave aquí y verifica
        </div>

        <!-- System ID -->
        <div class="mb-4">
            <label class="form-label"><strong>System ID de tu instalación:</strong></label>
            <div class="input-group">
                <input type="text" class="form-control form-control-lg" id="system-id-display" 
                       value="Generando..." readonly style="font-family: monospace; font-weight: bold;">
                <button class="btn btn-outline-secondary" type="button" onclick="copySystemId()">
                    <i class="fas fa-copy"></i> Copiar
                </button>
            </div>
            <div class="form-text">
                <i class="fas fa-fingerprint me-1"></i>
                Este es el identificador único de tu sistema. Envíalo al desarrollador para obtener tu licencia.
            </div>
        </div>

        <!-- License Input -->
        <form id="license-form" onsubmit="verifyLicense(event)">
            <div class="mb-3">
                <label class="form-label"><strong>Clave de Licencia:</strong></label>
                <input type="text" class="form-control form-control-lg" id="license-key-input" 
                       placeholder="XXXXX-XXXXX-XXXXX-XXXXX"
                       pattern="[A-F0-9]{5}-[A-F0-9]{5}-[A-F0-9]{5}-[A-F0-9]{5}"
                       style="font-family: monospace; text-transform: uppercase;"
                       value="<?php echo htmlspecialchars($current_settings['system_license'] ?? ''); ?>">
                <div class="form-text">
                    Pega aquí la clave de licencia que te proporcionó el desarrollador
                </div>
            </div>

            <div id="license-status" class="mb-3"></div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-check-circle me-1"></i>
                Verificar Licencia
            </button>
        </form>
    </div>
</div>

<!-- Configuración de Repositorio -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fab fa-github me-2"></i>
            Configuración de Repositorio
        </h5>
    </div>
    <div class="card-body">
        <form method="POST" id="github-config-form">
            <input type="hidden" name="action" value="update_github_config">
            
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Repositorio</label>
                        <input type="text" class="form-control" name="github_repo" 
                               value="<?php echo htmlspecialchars($current_settings['github_repo'] ?? 'Sabalero23/mi_restaurant_delivery'); ?>" 
                               readonly>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Rama</label>
                        <input type="text" class="form-control" name="github_branch" 
                               value="<?php echo htmlspecialchars($current_settings['github_branch'] ?? 'main'); ?>" 
                               readonly>
                    </div>
                </div>
            </div>

            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="auto_backup" name="auto_backup_before_update" 
                       <?php echo ($current_settings['auto_backup_before_update'] ?? '1') === '1' ? 'checked' : ''; ?>>
                <label class="form-check-label" for="auto_backup">
                    <strong>Crear backup automático antes de actualizar</strong>
                </label>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>
                Guardar Configuración
            </button>
        </form>
    </div>
</div>

                <!-- Backups y Historial -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-database me-2"></i>
                                    Backups
                                </h5>
                            </div>
                            <div class="card-body">
                                <button class="btn btn-primary mb-3" onclick="createManualBackup()">
                                    <i class="fas fa-save me-1"></i>
                                    Crear Backup Manual
                                </button>
                                <div id="backups-list">
                                    <p class="text-muted">Cargando backups...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>
                                    Historial de Actualizaciones
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Usuario</th>
                                                <th>Estado</th>
                                                <th>Archivos</th>
                                                <th>Commit</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="updates-history">
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">Cargando...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Instrucciones de Google Maps -->
<div class="modal fade" id="mapsInstructionsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-map-marked-alt me-2"></i>
                    Cómo obtener la URL de Google Maps
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <i class="fas fa-lightbulb me-2"></i>
                    Sigue estos pasos para obtener la URL correcta de tu ubicación en Google Maps:
                </div>

                <ol class="mb-4">
                    <li class="mb-3">
                        <strong>Abre Google Maps</strong> en tu navegador: 
                        <a href="https://maps.google.com" target="_blank">maps.google.com</a>
                    </li>
                    <li class="mb-3">
                        <strong>Busca tu restaurante</strong> o la dirección exacta
                    </li>
                    <li class="mb-3">
                        <strong>Haz clic en "Compartir"</strong> (botón con el ícono de compartir)
                    </li>
                    <li class="mb-3">
                        <strong>Selecciona "Copiar enlace"</strong>
                    </li>
                    <li class="mb-3">
                        <strong>Pega el enlace</strong> en el campo "URL de Google Maps" de arriba
                    </li>
                </ol>

                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>¡Listo!</strong> El sistema extraerá automáticamente tus coordenadas de latitud y longitud desde esa URL.
                </div>

                <div class="card bg-light">
                    <div class="card-body">
                        <p class="mb-1"><strong>Ejemplo de URL válida:</strong></p>
                        <code class="small">https://maps.google.com/?q=-31.4176,-64.1890</code><br>
                        <code class="small">https://www.google.com/maps/@-31.4176,-64.1890,15z</code>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<style>
    .nav-tabs .nav-link {
        color: #6c757d;
        border: none;
        border-bottom: 3px solid transparent;
    }
    
    .nav-tabs .nav-link:hover {
        border-color: #dee2e6;
        color: #495057;
    }
    
    .nav-tabs .nav-link.active {
        color: var(--primary-color);
        border-color: var(--primary-color);
        background-color: transparent;
    }
    
    .card {
        border: none;
        box-shadow: 0 0 20px rgba(0,0,0,0.08);
        margin-bottom: 20px;
    }
    
    .card-header {
        background-color: #f8f9fa;
        border-bottom: 2px solid #e9ecef;
        font-weight: 600;
    }
    
    .form-label {
        font-weight: 500;
        color: #495057;
    }
    
    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(var(--primary-rgb), 0.25);
    }
    
    .alert {
        border-left: 4px solid;
    }
    
    .alert-info {
        border-left-color: #0dcaf0;
    }
    
    .alert-success {
        border-left-color: #198754;
    }
    
    .alert-danger {
        border-left-color: #dc3545;
    }
</style>

    <script>
        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            
            if (sidebar && sidebarBackdrop) {
                sidebar.classList.toggle('show');
                sidebarBackdrop.classList.toggle('show');
                
                if (sidebar.classList.contains('show')) {
                    document.body.style.overflow = 'hidden';
                } else {
                    document.body.style.overflow = '';
                }
            }
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            
            if (sidebar) sidebar.classList.remove('show');
            if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
            document.body.style.overflow = '';
        }

        function updateCurrentTime() {
            setInterval(() => {
                const now = new Date();
                const timeString = now.toLocaleTimeString('es-AR', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
                
                const clockElements = document.querySelectorAll('.fas.fa-clock + *');
                clockElements.forEach(el => {
                    if (el.textContent && el.textContent.includes(':')) {
                        el.textContent = timeString;
                    }
                });
            }, 60000);
        }

        // Toggle API Key visibility
        function toggleApiKeyVisibility() {
            const apiKeyInput = document.getElementById('google_maps_api_key');
            const apiKeyIcon = document.getElementById('api-key-icon');
            
            if (apiKeyInput.type === 'password') {
                apiKeyInput.type = 'text';
                apiKeyIcon.className = 'fas fa-eye-slash';
            } else {
                apiKeyInput.type = 'password';
                apiKeyIcon.className = 'fas fa-eye';
            }
        }

        // Test Google Maps API
        function testGoogleMapsAPI() {
            const apiKey = document.getElementById('google_maps_api_key').value;
            
            if (!apiKey || apiKey === 'TU_API_KEY_AQUI') {
                alert('Por favor ingrese una API Key válida de Google Maps');
                return;
            }
            
            // Test simple de la API
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${apiKey}&libraries=places&callback=testMapsCallback`;
            script.onerror = function() {
                alert('Error: La API Key de Google Maps no es válida o no tiene permisos para Places API');
            };
            
            window.testMapsCallback = function() {
                alert('¡Excelente! La API Key de Google Maps está funcionando correctamente');
                document.head.removeChild(script);
            };
            
            document.head.appendChild(script);
        }

        // Test notification sound
        function testNotificationSound() {
            // Crear un contexto de audio simple para prueba
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();
            
            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);
            
            oscillator.frequency.value = 800;
            oscillator.type = 'sine';
            
            gainNode.gain.setValueAtTime(0, audioContext.currentTime);
            gainNode.gain.linearRampToValueAtTime(0.1, audioContext.currentTime + 0.1);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.5);
            
            alert('¡Sonido de prueba reproducido!');
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                if (alert.classList.contains('alert-dismissible')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);

        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
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
                    e.preventDefault();
                    alert('Por favor complete todos los campos requeridos');
                }
            });
        });

        // Real-time API key validation
        document.getElementById('google_maps_api_key').addEventListener('input', function() {
            const value = this.value;
            if (value && value !== 'TU_API_KEY_AQUI' && value.length > 20) {
                this.classList.add('is-valid');
                this.classList.remove('is-invalid');
            } else if (value) {
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                this.classList.remove('is-valid', 'is-invalid');
            }
        });

        // GitHub Update System Functions
        let updateInProgress = false;

        // Verificar actualizaciones disponibles
        function checkForUpdates() {
            const btn = event.target;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Verificando...';
            btn.disabled = true;

            fetch('api/github-update.php?action=check')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.updates_available) {
                            showUpdatesAvailable(data);
                        } else {
                            document.getElementById('update-status').innerHTML = `
                                <div class="alert alert-success mb-0">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <strong>Sistema actualizado</strong><br>
                                    Estás usando la última versión disponible
                                </div>
                            `;
                            document.getElementById('changes-panel').style.display = 'none';
                        }
                        document.getElementById('current-commit').textContent = data.current_commit ? data.current_commit.substring(0, 7) : 'N/A';
                    } else {
                        document.getElementById('update-status').innerHTML = `
                            <div class="alert alert-danger mb-0">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                Error: ${data.message}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('update-status').innerHTML = `
                        <div class="alert alert-danger mb-0">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error de conexión: ${error.message}
                        </div>
                    `;
                })
                .finally(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                });
        }

        // Mostrar actualizaciones disponibles
        function showUpdatesAvailable(data) {
            document.getElementById('update-status').innerHTML = `
                <div class="alert alert-warning mb-0">
                    <i class="fas fa-download me-2"></i>
                    <strong>¡Hay actualizaciones disponibles!</strong><br>
                    ${data.commits_ahead} nuevos commits desde ${data.latest_commit.substring(0, 7)}
                </div>
            `;

            document.getElementById('files-added').textContent = data.stats.added || 0;
            document.getElementById('files-modified').textContent = data.stats.modified || 0;
            document.getElementById('files-removed').textContent = data.stats.removed || 0;

            let commitsHTML = '';
            if (data.commits && data.commits.length > 0) {
                data.commits.forEach(commit => {
                    commitsHTML += `
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">${commit.message}</h6>
                                <small><code>${commit.sha.substring(0, 7)}</code></small>
                            </div>
                            <p class="mb-1 small text-muted">${commit.author}</p>
                            <small class="text-muted">${new Date(commit.date).toLocaleString('es-AR')}</small>
                        </div>
                    `;
                });
            }
            document.getElementById('commits-list').innerHTML = commitsHTML;
            document.getElementById('changes-panel').style.display = 'block';
        }

        // Realizar actualización
        function performUpdate() {
            if (updateInProgress) {
                alert('Ya hay una actualización en progreso');
                return;
            }

            if (!confirm('¿Está seguro de que desea actualizar el sistema?\n\nSe creará un backup automático antes de continuar.')) {
                return;
            }

            updateInProgress = true;
            document.getElementById('changes-panel').style.display = 'none';
            document.getElementById('progress-panel').style.display = 'block';

            updateProgress(10, 'Creando backup del sistema...');

            fetch('api/github-update.php?action=update', {
                method: 'POST'
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateProgress(100, '¡Actualización completada exitosamente!');
                        
                        setTimeout(() => {
                            alert(`Sistema actualizado exitosamente!\n\nArchivos actualizados: ${data.stats.updated}\nArchivos nuevos: ${data.stats.added}\nArchivos eliminados: ${data.stats.deleted}\n\nLa página se recargará para aplicar los cambios.`);
                            location.reload();
                        }, 2000);
                    } else {
                        throw new Error(data.message);
                    }
                })
                .catch(error => {
                    updateInProgress = false;
                    updateProgress(0, '');
                    document.getElementById('progress-panel').style.display = 'none';
                    alert('Error durante la actualización: ' + error.message);
                });
        }

        // Actualizar barra de progreso
        function updateProgress(percent, message) {
            const progressBar = document.getElementById('update-progress');
            const statusDiv = document.getElementById('progress-status');
            
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%';
            statusDiv.innerHTML = `<p class="mb-0">${message}</p>`;
        }

        // Guardar configuración de GitHub
        function saveGithubConfig(event) {
            event.preventDefault();
            
            const formData = new FormData(document.getElementById('github-config-form'));
            
            fetch('', {
                method: 'POST',
                body: formData
            })
                .then(response => response.text())
                .then(() => {
                    alert('Configuración guardada exitosamente');
                    location.reload();
                })
                .catch(error => {
                    alert('Error al guardar: ' + error.message);
                });
        }

        // Toggle visibilidad del token
        function toggleGithubToken() {
            const input = document.getElementById('github-token');
            const icon = document.getElementById('github-token-icon');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }

        // Crear backup manual
        function createManualBackup() {
            if (!confirm('¿Crear un backup manual del sistema actual?')) {
                return;
            }
            
            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Creando...';
            btn.disabled = true;
            
            fetch('api/github-update.php?action=backup')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Backup creado exitosamente!\n\nUbicación: ${data.backup_path}\nArchivos: ${data.files_backed_up}\nTamaño: ${data.size}`);
                        loadBackupsList();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error al crear backup: ' + error.message);
                })
                .finally(() => {
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                });
        }

        // Cargar lista de backups
        function loadBackupsList() {
            document.getElementById('backups-list').innerHTML = `
                <p class="text-muted">Los backups se guardan en la carpeta <code>backups/</code></p>
                <p class="small">Puedes acceder a ellos mediante FTP o el administrador de archivos de tu servidor.</p>
            `;
        }

        // Cargar historial de actualizaciones
        function loadUpdateHistory() {
            fetch('api/github-update.php?action=get_logs')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.logs.length > 0) {
                        let html = '';
                        data.logs.forEach(log => {
                            const statusBadge = getStatusBadge(log.status);
                            const date = new Date(log.started_at).toLocaleString('es-AR');
                            const hasDetails = log.update_details && log.update_details !== 'null';
                            
                            html += `
                                <tr>
                                    <td>${date}</td>
                                    <td>${log.username || 'Sistema'}</td>
                                    <td>${statusBadge}</td>
                                    <td>
                                        <span class="badge bg-success">${log.files_added || 0} nuevos</span>
                                        <span class="badge bg-warning">${log.files_updated || 0} modificados</span>
                                        ${(log.files_deleted || 0) > 0 ? `<span class="badge bg-danger">${log.files_deleted} eliminados</span>` : ''}
                                    </td>
                                    <td><code>${log.commit_hash || 'N/A'}</code></td>
                                    <td>
                                        ${hasDetails ? `<button class="btn btn-sm btn-outline-info me-1" onclick="viewUpdateDetails(${log.id})"><i class="fas fa-info-circle me-1"></i>Ver detalles</button>` : ''}
                                        ${log.backup_path ? `<button class="btn btn-sm btn-outline-primary" onclick="rollbackToBackup('${log.backup_path}')"><i class="fas fa-undo me-1"></i>Revertir</button>` : ''}
                                    </td>
                                </tr>
                            `;
                        });
                        document.getElementById('updates-history').innerHTML = html;
                    } else {
                        document.getElementById('updates-history').innerHTML = '<tr><td colspan="6" class="text-center text-muted">No hay actualizaciones registradas</td></tr>';
                    }
                });
        }

        // Ver detalles de una actualización
        function viewUpdateDetails(updateId) {
            fetch(`api/github-update.php?action=get_update_details&id=${updateId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showUpdateDetailsModal(data.update, data.details);
                    } else {
                        alert('Error al cargar detalles: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        // Mostrar modal con detalles de la actualización
        function showUpdateDetailsModal(update, details) {
            let modalHTML = `
                <div class="modal fade" id="updateDetailsModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Detalles de la Actualización
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong><i class="fas fa-calendar-alt me-2"></i>Información General</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Fecha:</strong> ${new Date(update.started_at).toLocaleString('es-AR')}</p>
                                                <p><strong>Usuario:</strong> ${update.username || 'Sistema'}</p>
                                                <p><strong>Estado:</strong> ${getStatusBadge(update.status)}</p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Commit anterior:</strong> <code>${update.from_commit ? update.from_commit.substring(0, 7) : 'N/A'}</code></p>
                                                <p><strong>Commit nuevo:</strong> <code>${update.to_commit ? update.to_commit.substring(0, 7) : 'N/A'}</code></p>
                                                ${update.backup_path ? `<p><strong>Backup:</strong> ${update.backup_path}</p>` : ''}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong><i class="fas fa-chart-bar me-2"></i>Resumen de Cambios</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <div class="rounded p-3 bg-success">
                                                    <h3 class="text-white mb-0">${update.files_added || 0}</h3>
                                                    <small class="text-white">Archivos Nuevos</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="rounded p-3 bg-warning">
                                                    <h3 class="text-white mb-0">${update.files_updated || 0}</h3>
                                                    <small class="text-white">Archivos Modificados</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="rounded p-3 bg-danger">
                                                    <h3 class="text-white mb-0">${update.files_deleted || 0}</h3>
                                                    <small class="text-white">Archivos Eliminados</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
            `;

            if (details) {
                if (details.added && details.added.length > 0) {
                    modalHTML += `
                        <div class="card mb-3">
                            <div class="card-header bg-success text-white">
                                <strong><i class="fas fa-plus-circle me-2"></i>Archivos Nuevos (${details.added.length})</strong>
                            </div>
                            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                <ul class="list-unstyled mb-0">
                    `;
                    details.added.forEach(file => {
                        modalHTML += `<li class="py-1"><i class="fas fa-file text-success me-2"></i><code>${file}</code></li>`;
                    });
                    modalHTML += `
                                </ul>
                            </div>
                        </div>
                    `;
                }

                if (details.modified && details.modified.length > 0) {
                    modalHTML += `
                        <div class="card mb-3">
                            <div class="card-header bg-warning">
                                <strong><i class="fas fa-edit me-2"></i>Archivos Modificados (${details.modified.length})</strong>
                            </div>
                            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                <ul class="list-unstyled mb-0">
                    `;
                    details.modified.forEach(file => {
                        modalHTML += `<li class="py-1"><i class="fas fa-file-code text-warning me-2"></i><code>${file}</code></li>`;
                    });
                    modalHTML += `
                                </ul>
                            </div>
                        </div>
                    `;
                }

                if (details.removed && details.removed.length > 0) {
                    modalHTML += `
                        <div class="card mb-3">
                            <div class="card-header bg-danger text-white">
                                <strong><i class="fas fa-trash-alt me-2"></i>Archivos Eliminados (${details.removed.length})</strong>
                            </div>
                            <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                                <ul class="list-unstyled mb-0">
                    `;
                    details.removed.forEach(file => {
                        modalHTML += `<li class="py-1"><i class="fas fa-times-circle text-danger me-2"></i><code>${file}</code></li>`;
                    });
                    modalHTML += `
                                </ul>
                            </div>
                        </div>
                    `;
                }
            } else {
                modalHTML += `
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No hay detalles específicos de archivos disponibles para esta actualización.
                    </div>
                `;
            }

            modalHTML += `
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cerrar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const existingModal = document.getElementById('updateDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }

            document.body.insertAdjacentHTML('beforeend', modalHTML);

            const modal = new bootstrap.Modal(document.getElementById('updateDetailsModal'));
            modal.show();

            document.getElementById('updateDetailsModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }

        // Obtener badge de estado
        function getStatusBadge(status) {
            const badges = {
                'completed': '<span class="badge bg-success">Completada</span>',
                'failed': '<span class="badge bg-danger">Fallida</span>',
                'in_progress': '<span class="badge bg-info">En progreso</span>',
                'rolled_back': '<span class="badge bg-warning">Revertida</span>'
            };
            return badges[status] || '<span class="badge bg-secondary">Desconocido</span>';
        }

        // Revertir a un backup
        function rollbackToBackup(backupPath) {
            if (!confirm('¿Está seguro de que desea revertir el sistema a este backup?\n\nEsto sobrescribirá todos los archivos actuales.')) {
                return;
            }
            
            const formData = new FormData();
            formData.append('backup_path', backupPath);
            
            fetch('api/github-update.php?action=rollback', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`Sistema revertido exitosamente!\n\nArchivos restaurados: ${data.files_restored}\n\nLa página se recargará.`);
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error al revertir: ' + error.message);
                });
        }

        // Inicializar al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            checkForUpdates();
            loadBackupsList();
            loadUpdateHistory();
        });

        // Mostrar cambios disponibles antes de actualizar
        function showAvailableChanges() {
            fetch('api/github-update.php?action=get_changes')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showChangesModal(data.changes, data.commits);
                    } else {
                        alert('Error al obtener cambios: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                });
        }

        // Mostrar modal con cambios disponibles
        function showChangesModal(changes, commits) {
            let modalHTML = `
                <div class="modal fade" id="changesModal" tabindex="-1">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header bg-info text-white">
                                <h5 class="modal-title">
                                    <i class="fas fa-code-branch me-2"></i>
                                    Cambios Disponibles
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Los siguientes archivos se actualizarán cuando ejecutes la actualización del sistema.
                                </div>

                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong><i class="fas fa-chart-bar me-2"></i>Resumen</strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-md-4">
                                                <div class="rounded p-3 bg-success">
                                                    <h3 class="text-white mb-0">${changes.added.length}</h3>
                                                    <small class="text-white">Archivos Nuevos</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="rounded p-3 bg-warning">
                                                    <h3 class="text-white mb-0">${changes.modified.length}</h3>
                                                    <small class="text-white">Archivos Modificados</small>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="rounded p-3 bg-danger">
                                                    <h3 class="text-white mb-0">${changes.removed.length}</h3>
                                                    <small class="text-white">Archivos Eliminados</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
            `;

            if (commits && commits.length > 0) {
                modalHTML += `
                    <div class="card mb-3">
                        <div class="card-header">
                            <strong><i class="fas fa-history me-2"></i>Commits Recientes (${commits.length})</strong>
                        </div>
                        <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                            <ul class="list-unstyled mb-0">
                `;
                commits.forEach(commit => {
                    modalHTML += `
                        <li class="mb-2">
                            <code class="text-primary">${commit.sha}</code> - ${commit.message}
                            <br><small class="text-muted">${commit.author} • ${commit.date}</small>
                        </li>
                    `;
                });
                modalHTML += `
                            </ul>
                        </div>
                    </div>
                `;
            }

            if (changes.added.length > 0) {
                modalHTML += `
                    <div class="card mb-3">
                        <div class="card-header bg-success text-white">
                            <strong><i class="fas fa-plus-circle me-2"></i>Archivos Nuevos (${changes.added.length})</strong>
                        </div>
                        <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                            <ul class="list-unstyled mb-0">
                `;
                changes.added.forEach(file => {
                    modalHTML += `<li class="py-1"><i class="fas fa-file text-success me-2"></i><code>${file.name}</code> <small class="text-muted">(+${file.additions})</small></li>`;
                });
                modalHTML += `
                            </ul>
                        </div>
                    </div>
                `;
            }

            if (changes.modified.length > 0) {
                modalHTML += `
                    <div class="card mb-3">
                        <div class="card-header bg-warning">
                            <strong><i class="fas fa-edit me-2"></i>Archivos Modificados (${changes.modified.length})</strong>
                        </div>
                        <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                            <ul class="list-unstyled mb-0">
                `;
                changes.modified.forEach(file => {
                    modalHTML += `<li class="py-1"><i class="fas fa-file-code text-warning me-2"></i><code>${file.name}</code> <small class="text-muted">(+${file.additions} -${file.deletions})</small></li>`;
                });
                modalHTML += `
                            </ul>
                        </div>
                    </div>
                `;
            }

            if (changes.removed.length > 0) {
                modalHTML += `
                    <div class="card mb-3">
                        <div class="card-header bg-danger text-white">
                            <strong><i class="fas fa-trash-alt me-2"></i>Archivos Eliminados (${changes.removed.length})</strong>
                        </div>
                        <div class="card-body" style="max-height: 200px; overflow-y: auto;">
                            <ul class="list-unstyled mb-0">
                `;
                changes.removed.forEach(file => {
                    modalHTML += `<li class="py-1"><i class="fas fa-times-circle text-danger me-2"></i><code>${file.name}</code></li>`;
                });
                modalHTML += `
                            </ul>
                        </div>
                    </div>
                `;
            }

            modalHTML += `
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cerrar
                                </button>
                                <button type="button" class="btn btn-success" data-bs-dismiss="modal" onclick="performUpdate()">
                                    <i class="fas fa-download me-1"></i>Actualizar Ahora
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            const existingModal = document.getElementById('changesModal');
            if (existingModal) {
                existingModal.remove();
            }

            document.body.insertAdjacentHTML('beforeend', modalHTML);

            const modal = new bootstrap.Modal(document.getElementById('changesModal'));
            modal.show();

            document.getElementById('changesModal').addEventListener('hidden.bs.modal', function () {
                this.remove();
            });
        }
    </script>
    <script>
function showMapsInstructions() {
    const modal = new bootstrap.Modal(document.getElementById('mapsInstructionsModal'));
    modal.show();
}

// Validación en tiempo real de URL de Google Maps
document.addEventListener('DOMContentLoaded', function() {
    const mapsUrlInput = document.querySelector('input[name="restaurant_maps_url"]');
    const preview = document.getElementById('maps-coordinates-preview');
    const coordinatesText = document.getElementById('coordinates-text');
    
    if (mapsUrlInput) {
        mapsUrlInput.addEventListener('input', function() {
            const url = this.value;
            
            if (url && (url.includes('maps.google.com') || url.includes('google.com/maps'))) {
                const coordinates = extractCoordinatesFromUrl(url);
                
                if (coordinates) {
                    coordinatesText.textContent = `Coordenadas encontradas: ${coordinates.lat}, ${coordinates.lng}`;
                    preview.style.display = 'block';
                    this.classList.add('is-valid');
                    this.classList.remove('is-invalid');
                } else {
                    preview.style.display = 'none';
                    this.classList.add('is-invalid');
                    this.classList.remove('is-valid');
                }
            } else if (url) {
                preview.style.display = 'none';
                this.classList.add('is-invalid');
                this.classList.remove('is-valid');
            } else {
                preview.style.display = 'none';
                this.classList.remove('is-valid', 'is-invalid');
            }
        });
        
        // Verificar URL inicial si ya existe
        if (mapsUrlInput.value) {
            mapsUrlInput.dispatchEvent(new Event('input'));
        }
    }
});

function extractCoordinatesFromUrl(url) {
    // Varios patrones para extraer coordenadas de URLs de Google Maps
    const patterns = [
        // Patrón: @lat,lng,zoom
        /@(-?\d+\.?\d*),(-?\d+\.?\d*),/,
        // Patrón: ?q=lat,lng
        /[?&]q=(-?\d+\.?\d*),(-?\d+\.?\d*)/,
        // Patrón: ll=lat,lng
        /[?&]ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/,
        // Patrón: center=lat,lng
        /[?&]center=(-?\d+\.?\d*),(-?\d+\.?\d*)/,
        // Patrón: /place/name/@lat,lng
        /\/place\/[^/]*\/@(-?\d+\.?\d*),(-?\d+\.?\d*)/
    ];
    
    for (const pattern of patterns) {
        const match = url.match(pattern);
        if (match) {
            return {
                lat: parseFloat(match[1]),
                lng: parseFloat(match[2])
            };
        }
    }
    
    return null;
}

// Obtener y mostrar System ID
function loadSystemId() {
    fetch('api/github-update.php?action=generate_system_id')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('system-id-display').value = data.system_id;
            } else {
                document.getElementById('system-id-display').value = 'Error al generar ID';
            }
        })
        .catch(error => {
            document.getElementById('system-id-display').value = 'Error de conexión';
        });
}

// Copiar System ID
function copySystemId() {
    const systemId = document.getElementById('system-id-display').value;
    navigator.clipboard.writeText(systemId).then(function() {
        const btn = event.target.closest('button');
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
        setTimeout(() => {
            btn.innerHTML = originalHTML;
        }, 2000);
    });
}

// Verificar licencia
function verifyLicense(event) {
    event.preventDefault();
    
    const licenseKey = document.getElementById('license-key-input').value.toUpperCase().trim();
    const statusDiv = document.getElementById('license-status');
    
    if (!licenseKey) {
        statusDiv.innerHTML = '<div class="alert alert-warning">Por favor ingresa una clave de licencia</div>';
        return;
    }
    
    statusDiv.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin me-2"></i>Verificando licencia...</div>';
    
    const formData = new FormData();
    formData.append('license_key', licenseKey);
    
    fetch('api/github-update.php?action=verify_license', {
        method: 'POST',
        body: formData
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>¡Licencia válida!</strong><br>
                        ${data.message}
                    </div>
                `;
                // Recargar para aplicar cambios
                setTimeout(() => location.reload(), 2000);
            } else {
                statusDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-times-circle me-2"></i>
                        <strong>Licencia inválida</strong><br>
                        ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            statusDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Error al verificar: ${error.message}
                </div>
            `;
        });
}

// Auto-formato del input de licencia
document.getElementById('license-key-input').addEventListener('input', function(e) {
    let value = e.target.value.replace(/[^A-F0-9-]/gi, '').toUpperCase();
    value = value.replace(/-/g, '');
    
    let formatted = '';
    for (let i = 0; i < value.length && i < 20; i++) {
        if (i > 0 && i % 5 === 0) {
            formatted += '-';
        }
        formatted += value[i];
    }
    
    e.target.value = formatted;
});

// Cargar System ID al iniciar
document.addEventListener('DOMContentLoaded', function() {
    loadSystemId();
});
</script>

<?php include 'footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>