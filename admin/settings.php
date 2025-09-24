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
        'auto_print_orders' => isset($_POST['auto_print_orders']) ? '1' : '0',
        'notification_sound' => isset($_POST['notification_sound']) ? '1' : '0',
        'kitchen_display_refresh' => intval($_POST['kitchen_display_refresh'])
    ];
    
    try {
        foreach ($settings as $key => $value) {
            $query = "INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE setting_value = ?";
            
            // Definir descripciones para las nuevas configuraciones
            $descriptions = [
                'enable_online_orders' => 'Habilitar/deshabilitar pedidos online',
                'google_maps_api_key' => 'Clave de API de Google Maps para autocompletado',
                'opening_time' => 'Hora de apertura del restaurante',
                'closing_time' => 'Hora de cierre del restaurante',
                'kitchen_closing_time' => 'Hora de cierre de cocina para pedidos',
                'order_timeout' => 'Tiempo límite para confirmar orden (minutos)',
                'auto_print_orders' => 'Imprimir órdenes automáticamente',
                'notification_sound' => 'Sonido de notificaciones',
                'kitchen_display_refresh' => 'Actualización pantalla cocina (segundos)'
            ];
            
            $description = $descriptions[$key] ?? '';
            $stmt = $db->prepare($query);
            $stmt->execute([$key, $value, $description, $value]);
        }
        
        return ['success' => true, 'message' => 'Configuración de pedidos online actualizada'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Load current settings
$current_settings = getSettings();

// Get system stats
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
    (SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()) as orders_today,
    (SELECT COUNT(*) FROM online_orders WHERE DATE(created_at) = CURDATE()) as online_orders_today,
    (SELECT COUNT(*) FROM products WHERE is_active = 1) as active_products,
    (SELECT COUNT(*) FROM tables) as total_tables";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$system_stats = $stats_stmt->fetch();

$restaurant_name = $current_settings['restaurant_name'] ?? 'Mi Restaurante';
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
                        <i class="fas fa-cog me-2"></i>
                        Configuración del Sistema
                    </h2>
                    <p class="text-muted mb-0">Administra la configuración general del restaurante</p>
                </div>
                <div class="text-muted d-none d-lg-block">
                    <i class="fas fa-clock me-1"></i>
                    <?php echo date('d/m/Y H:i'); ?>
                </div>
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

        <!-- System Stats -->
        <div class="row mb-4">
            <div class="col-6 col-md-3 col-xl-2">
                <div class="stat-card">
                    <div class="stat-number text-primary"><?php echo $system_stats['active_users']; ?></div>
                    <div class="text-muted small">Usuarios Activos</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $system_stats['orders_today']; ?></div>
                    <div class="text-muted small">Órdenes Hoy</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo $system_stats['online_orders_today']; ?></div>
                    <div class="text-muted small">Online Hoy</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="stat-card">
                    <div class="stat-number text-info"><?php echo $system_stats['active_products']; ?></div>
                    <div class="text-muted small">Productos</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="stat-card">
                    <div class="stat-number text-secondary"><?php echo $system_stats['total_tables']; ?></div>
                    <div class="text-muted small">Mesas</div>
                </div>
            </div>
            <div class="col-6 col-md-3 col-xl-2">
                <div class="stat-card">
                    <div class="stat-number <?php echo ($current_settings['enable_online_orders'] ?? '0') === '1' ? 'text-success' : 'text-danger'; ?>">
                        <i class="fas fa-<?php echo ($current_settings['enable_online_orders'] ?? '0') === '1' ? 'check' : 'times'; ?>"></i>
                    </div>
                    <div class="text-muted small">Pedidos Online</div>
                </div>
            </div>
        </div>
                    
        <!-- Settings Tabs -->
        <div class="settings-section">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                        <i class="fas fa-store me-2"></i>General
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="business-tab" data-bs-toggle="tab" data-bs-target="#business" type="button">
                        <i class="fas fa-dollar-sign me-2"></i>Negocio
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="online-orders-tab" data-bs-toggle="tab" data-bs-target="#online-orders" type="button">
                        <i class="fas fa-globe me-2"></i>Pedidos Online
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button">
                        <i class="fas fa-server me-2"></i>Sistema
                    </button>
                </li>
            </ul>
            
            <div class="tab-content">
                <!-- General Settings -->
<div class="tab-pane fade show active" id="general" role="tabpanel">
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
                    <label class="form-label">Email del Restaurante</label>
                    <input type="email" class="form-control" name="restaurant_email" 
                           value="<?php echo htmlspecialchars($current_settings['restaurant_email'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Teléfono</label>
                    <input type="text" class="form-control" name="restaurant_phone" 
                           value="<?php echo htmlspecialchars($current_settings['restaurant_phone'] ?? ''); ?>">
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">WhatsApp (sin +54)</label>
                    <input type="text" class="form-control" name="whatsapp_number" 
                           value="<?php echo htmlspecialchars($current_settings['whatsapp_number'] ?? ''); ?>"
                           placeholder="3482549555">
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">Dirección</label>
                    <textarea class="form-control" name="restaurant_address" rows="2"><?php echo htmlspecialchars($current_settings['restaurant_address'] ?? ''); ?></textarea>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <label class="form-label">
                        URL de Google Maps
                        <small class="text-muted">(para extraer coordenadas automáticamente)</small>
                    </label>
                    <input type="url" class="form-control" name="restaurant_maps_url" 
                           value="<?php echo htmlspecialchars($current_settings['restaurant_maps_url'] ?? ''); ?>"
                           placeholder="https://maps.google.com/...">
                    <div class="form-text">
                        <i class="fas fa-info-circle me-1"></i>
                        Pegue aquí la URL completa de Google Maps de su restaurante. 
                        <button type="button" class="btn btn-link p-0 text-decoration-none" onclick="showMapsInstructions()">
                            ¿Cómo obtener la URL?
                        </button>
                    </div>
                    <div id="maps-coordinates-preview" class="form-text text-success" style="display: none;">
                        <i class="fas fa-map-marker-alt me-1"></i>
                        <span id="coordinates-text"></span>
                    </div>
                </div>
            </div>
        </div>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-save me-2"></i>
            Guardar Configuración General
        </button>
    </form>
</div>

                
                <!-- Business Settings -->
                <div class="tab-pane fade" id="business" role="tabpanel">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_business">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">IVA (%)</label>
                                    <input type="number" class="form-control" name="tax_rate" step="0.01" 
                                           value="<?php echo $current_settings['tax_rate'] ?? '21'; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Costo de Delivery</label>
                                    <input type="number" class="form-control" name="delivery_fee" step="0.01" 
                                           value="<?php echo $current_settings['delivery_fee'] ?? '300'; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Símbolo de Moneda</label>
                                    <input type="text" class="form-control" name="currency_symbol" 
                                           value="<?php echo htmlspecialchars($current_settings['currency_symbol'] ?? '$'); ?>">
                        ); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Distancia máxima de delivery (km)</label>
                                    <input type="number" class="form-control" name="max_delivery_distance" step="0.1" 
                                           value="<?php echo $current_settings['max_delivery_distance'] ?? '25'; ?>">
                                    <div class="form-text">Radio máximo para entregas a domicilio</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Monto mínimo para delivery</label>
                                    <input type="number" class="form-control" name="min_delivery_amount" step="0.01" 
                                           value="<?php echo $current_settings['min_delivery_amount'] ?? '1500'; ?>">
                                    <div class="form-text">Monto mínimo de pedido para delivery</div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-2"></i>
                            Guardar Configuración de Negocio
                        </button>
                    </form>
                </div>
                
                <!-- Online Orders Settings -->
                <div class="tab-pane fade" id="online-orders" role="tabpanel">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_online_orders">
                        
                        <!-- Configuración General de Pedidos Online -->
                        <h5 class="mb-3">
                            <i class="fas fa-toggle-on me-2"></i>
                            Configuración General
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="enable_online_orders" 
                                               name="enable_online_orders" <?php echo ($current_settings['enable_online_orders'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enable_online_orders">
                                            <strong>Habilitar Pedidos Online</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">Permite a los clientes realizar pedidos desde la web</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="auto_print_orders" 
                                               name="auto_print_orders" <?php echo ($current_settings['auto_print_orders'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="auto_print_orders">
                                            <strong>Imprimir Órdenes Automáticamente</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">Imprime automáticamente cuando llega un pedido</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Horarios -->
                        <h5 class="mb-3 mt-4">
                            <i class="fas fa-clock me-2"></i>
                            Horarios de Atención
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Hora de Apertura</label>
                                    <input type="time" class="form-control" name="opening_time" 
                                           value="<?php echo $current_settings['opening_time'] ?? '11:00'; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Hora de Cierre</label>
                                    <input type="time" class="form-control" name="closing_time" 
                                           value="<?php echo $current_settings['closing_time'] ?? '23:30'; ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Cierre de Cocina</label>
                                    <input type="time" class="form-control" name="kitchen_closing_time" 
                                           value="<?php echo $current_settings['kitchen_closing_time'] ?? '23:00'; ?>">
                                    <div class="form-text">Hora límite para recibir pedidos online</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Google Maps API -->
                        <h5 class="mb-3 mt-4">
                            <i class="fab fa-google me-2"></i>
                            Integración con Google Maps
                        </h5>
                        
                        <div class="mb-3">
                            <label class="form-label">Clave de API de Google Maps</label>
                            <div class="api-key-field">
                                <input type="password" class="form-control" id="google_maps_api_key" name="google_maps_api_key" 
                                       value="<?php echo htmlspecialchars($current_settings['google_maps_api_key'] ?? ''); ?>"
                                       placeholder="Ingrese su API Key de Google Maps">
                                <button type="button" class="api-key-toggle" onclick="toggleApiKeyVisibility()">
                                    <i class="fas fa-eye" id="api-key-icon"></i>
                                </button>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Necesaria para el autocompletado de direcciones. 
                                <a href="https://developers.google.com/maps/documentation/javascript/get-api-key" target="_blank">
                                    ¿Cómo obtener una API Key?
                                </a>
                            </div>
                        </div>
                        
                        <!-- Configuraciones Avanzadas -->
                        <h5 class="mb-3 mt-4">
                            <i class="fas fa-cogs me-2"></i>
                            Configuraciones Avanzadas
                        </h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Tiempo límite de confirmación (minutos)</label>
                                    <input type="number" class="form-control" name="order_timeout" 
                                           value="<?php echo $current_settings['order_timeout'] ?? '30'; ?>" min="5" max="120">
                                    <div class="form-text">Tiempo máximo para confirmar un pedido</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Actualización pantalla cocina (segundos)</label>
                                    <input type="number" class="form-control" name="kitchen_display_refresh" 
                                           value="<?php echo $current_settings['kitchen_display_refresh'] ?? '5'; ?>" min="1" max="60">
                                    <div class="form-text">Frecuencia de actualización automática</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="notification_sound" 
                                               name="notification_sound" <?php echo ($current_settings['notification_sound'] ?? '1') === '1' ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="notification_sound">
                                            <strong>Sonido de Notificaciones</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">Reproduce sonido al recibir pedidos</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Test de Configuración -->
                        <div class="alert alert-info mt-4">
                            <h6><i class="fas fa-test-tube me-2"></i>Prueba tu configuración</h6>
                            <div class="d-flex gap-2 flex-wrap">
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="testGoogleMapsAPI()">
                                    <i class="fab fa-google me-1"></i>
                                    Probar Google Maps
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="testNotificationSound()">
                                    <i class="fas fa-volume-up me-1"></i>
                                    Probar Sonido
                                </button>
                                <a href="../index.php" target="_blank" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-external-link-alt me-1"></i>
                                    Ver Página de Pedidos
                                </a>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-save me-2"></i>
                            Guardar Configuración de Pedidos Online
                        </button>
                    </form>
                </div>
                
                <!-- System Settings -->
                <div class="tab-pane fade" id="system" role="tabpanel">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_system">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Zona Horaria</label>
                                    <select class="form-select" name="system_timezone">
                                        <option value="America/Argentina/Buenos_Aires" 
                                                <?php echo ($current_settings['system_timezone'] ?? '') === 'America/Argentina/Buenos_Aires' ? 'selected' : ''; ?>>
                                            Buenos Aires
                                        </option>
                                        <option value="America/Argentina/Cordoba" 
                                                <?php echo ($current_settings['system_timezone'] ?? '') === 'America/Argentina/Cordoba' ? 'selected' : ''; ?>>
                                            Córdoba
                                        </option>
                                        <option value="America/Argentina/Mendoza" 
                                                <?php echo ($current_settings['system_timezone'] ?? '') === 'America/Argentina/Mendoza' ? 'selected' : ''; ?>>
                                            Mendoza
                                        </option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Formato de Fecha</label>
                                    <select class="form-select" name="date_format">
                                        <option value="d/m/Y" 
                                                <?php echo ($current_settings['date_format'] ?? 'd/m/Y') === 'd/m/Y' ? 'selected' : ''; ?>>
                                            DD/MM/YYYY
                                        </option>
                                        <option value="Y-m-d" 
                                                <?php echo ($current_settings['date_format'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>
                                            YYYY-MM-DD
                                        </option>
                                        <option value="m/d/Y" 
                                                <?php echo ($current_settings['date_format'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>
                                            MM/DD/YYYY
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Auto-Logout (minutos)</label>
                                    <input type="number" class="form-control" name="auto_logout_time" 
                                           value="<?php echo $current_settings['auto_logout_time'] ?? '240'; ?>" min="30" max="480">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Máx. Órdenes por Día</label>
                                    <input type="number" class="form-control" name="max_orders_per_day" 
                                           value="<?php echo $current_settings['max_orders_per_day'] ?? '1000'; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Frecuencia de Backup</label>
                            <select class="form-select" name="backup_frequency">
                                <option value="daily" 
                                        <?php echo ($current_settings['backup_frequency'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>
                                    Diario
                                </option>
                                <option value="weekly" 
                                        <?php echo ($current_settings['backup_frequency'] ?? '') === 'weekly' ? 'selected' : ''; ?>>
                                    Semanal
                                </option>
                                <option value="monthly" 
                                        <?php echo ($current_settings['backup_frequency'] ?? '') === 'monthly' ? 'selected' : ''; ?>>
                                    Mensual
                                </option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-save me-2"></i>
                            Guardar Configuración del Sistema
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal con instrucciones -->
<div class="modal fade" id="mapsInstructionsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fab fa-google me-2"></i>
                    Cómo obtener la URL de Google Maps
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="step-by-step">
                    <div class="step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <strong>Abrir Google Maps</strong>
                            <p>Vaya a <a href="https://maps.google.com" target="_blank">maps.google.com</a></p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <strong>Buscar su restaurante</strong>
                            <p>Escriba el nombre y dirección de su restaurante en la barra de búsqueda</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <strong>Copiar la URL</strong>
                            <p>Una vez que aparezca su restaurante en el mapa, copie la URL completa desde la barra del navegador</p>
                        </div>
                    </div>
                    <div class="step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <strong>Pegar aquí</strong>
                            <p>Pegue la URL en el campo anterior y guarde la configuración</p>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info mt-3">
                    <i class="fas fa-lightbulb me-2"></i>
                    <strong>Ejemplo de URL válida:</strong><br>
                    <code>https://maps.google.com/maps?q=-29.1167,-59.6500</code><br>
                    <code>https://www.google.com/maps/place/Mi+Restaurante/@-29.1167,-59.6500,15z</code>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
            updateCurrentTime();
        });

        function initializeMobileMenu() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const sidebarClose = document.getElementById('sidebarClose');

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('show');
                    sidebarBackdrop.classList.toggle('show');
                    document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
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

            document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        setTimeout(closeSidebar, 100);
                    }
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    closeSidebar();
                }
            });
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
</script>

<?php include 'footer.php'; ?>
</body>
</html>