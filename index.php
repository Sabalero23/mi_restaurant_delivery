<?php
// index.php - Con verificación de instalación
// Verificar si el sistema está instalado
function checkInstallation() {
    // 1. Verificar si existe el archivo de configuración
    if (!file_exists('config/config.php')) {
        return false;
    }
    
    // 2. Verificar si existe el archivo lock de instalación
    if (!file_exists('config/installed.lock')) {
        return false;
    }
    
    try {
        // 3. Intentar cargar la configuración
        require_once 'config/config.php';
        
        // 4. Verificar constantes esenciales
        if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER')) {
            return false;
        }
        
        // 5. Intentar conectar a la base de datos
        require_once 'config/database.php';
        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // 6. Verificar que existan las tablas básicas
        $requiredTables = ['users', 'roles', 'categories', 'products', 'settings', 'tables'];
        foreach ($requiredTables as $table) {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                return false;
            }
        }
        
        // 7. Verificar que exista al menos un usuario administrador
        $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 1 AND is_active = 1");
        if ($stmt->fetchColumn() === 0) {
            return false;
        }
        
        // 8. Verificar configuraciones básicas
        $stmt = $pdo->query("SELECT COUNT(*) FROM settings WHERE setting_key IN ('restaurant_name', 'restaurant_phone')");
        if ($stmt->fetchColumn() < 2) {
            return false;
        }
        
        return true;
        
    } catch (Exception $e) {
        // Error de conexión o consulta indica que la instalación no está completa
        error_log("Error verificando instalación: " . $e->getMessage());
        return false;
    }
}

// Realizar la verificación al inicio
if (!checkInstallation()) {
    // Si la instalación no está completa, redirigir al instalador
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $base_url = $protocol . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']);
    $install_url = rtrim($base_url, '/') . '/install.php';
    
    header("Location: $install_url");
    exit('Redirigiendo al instalador...');
}

// Si llegamos aquí, el sistema está correctamente instalado
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'models/Product.php';
require_once 'models/Category.php';

$productModel = new Product();
$categoryModel = new Category();

$categories = $categoryModel->getAll();
$products = $productModel->getAll();

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$whatsapp_number = $settings['whatsapp_number'] ?? '';
$google_maps_api_key = $settings['google_maps_api_key'] ?? '';

// Verificar si los pedidos online están habilitados
$online_orders_enabled = $settings['enable_online_orders'] ?? '1';
if ($online_orders_enabled !== '1') {
    $message = "Los pedidos online están temporalmente deshabilitados. Por favor contacte al restaurante directamente.";
}

// Verificar horarios de atención
$opening_time = $settings['opening_time'] ?? '11:00';
$closing_time = $settings['closing_time'] ?? '23:30';
$kitchen_closing_time = $settings['kitchen_closing_time'] ?? '23:00';

$current_time = date('H:i');
$is_open = ($current_time >= $opening_time && $current_time <= $kitchen_closing_time);

?>
<?php
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $restaurant_name; ?> - Menú Online</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Tema dinámico -->
<?php if (file_exists('assets/css/generate-theme.php')): ?>
    <link rel="stylesheet" href="assets/css/generate-theme.php?v=<?php echo time(); ?>">
<?php endif; ?>

<?php
// Incluir sistema de temas
$theme_file = 'config/theme.php';
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
/* Variables CSS usando el sistema de temas dinámico */
:root {
    /* Usar variables del sistema de temas dinámico o valores por defecto */
    --primary-color: <?php echo $current_theme['primary_color'] ?? '#667eea'; ?>;
    --secondary-color: <?php echo $current_theme['secondary_color'] ?? '#764ba2'; ?>;
    --accent-color: <?php echo $current_theme['accent_color'] ?? '#ff6b6b'; ?>;
    --success-color: <?php echo $current_theme['success_color'] ?? '#28a745'; ?>;
    --warning-color: <?php echo $current_theme['warning_color'] ?? '#ffc107'; ?>;
    --danger-color: <?php echo $current_theme['danger_color'] ?? '#dc3545'; ?>;
    --info-color: <?php echo $current_theme['info_color'] ?? '#17a2b8'; ?>;
    --text-dark: #212529;
    --text-muted: #6c757d;
    --bg-white: #ffffff;
    --bg-light: #f8f9fa;
    --border-radius-base: <?php echo $current_theme['border_radius_base'] ?? '8px'; ?>;
    --border-radius-large: <?php echo $current_theme['border_radius_large'] ?? '15px'; ?>;
    --shadow-base: <?php echo $current_theme['shadow_base'] ?? '0 8px 25px rgba(0, 0, 0, 0.08)'; ?>;
    --shadow-hover: 0 12px 35px rgba(0, 0, 0, 0.15);
    --transition-base: <?php echo $current_theme['transition_base'] ?? '0.3s ease'; ?>;
}

* {
    box-sizing: border-box;
}

/* Header mejorado con variables dinámicas */
.header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 2rem 0;
    text-align: center;
    position: relative;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.header h1 {
    font-size: 2.5rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    color: white !important;
}

.header p {
    font-size: 1.1rem;
    opacity: 0.9;
    margin: 0;
    color: white !important;
}

body {
    font-family: var(--font-family-primary, 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif);
    background: #f8f9fa !important;
    line-height: 1.6;
    color: #212529 !important;
}

/* Navbar mejorado */
.navbar {
    background: linear-gradient(135deg, #212529 0%, #343a40 100%) !important;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
}

.navbar-brand {
    font-weight: 700;
    font-size: 1.5rem;
    color: white !important;
}

.status-indicator {
    padding: 8px 16px;
    border-radius: 25px;
    font-size: 0.9rem;
    font-weight: 600;
    display: inline-block;
    transition: var(--transition-base);
}

.status-open {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    color: var(--success-color);
    box-shadow: 0 2px 10px rgba(21, 87, 36, 0.2);
}

.status-closed {
    background: linear-gradient(135deg, #f8d7da 0%, #f1b0b7 100%);
    color: var(--danger-color);
    box-shadow: 0 2px 10px rgba(114, 28, 36, 0.2);
}

/* Hero Section mejorado */
.hero-section {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    padding: 4rem 0;
    text-align: center;
    position: relative;
    overflow: hidden;
}

.hero-section::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" style="stop-color:rgba(255,255,255,0.1)"/><stop offset="100%" style="stop-color:rgba(255,255,255,0)"/></radialGradient></defs><circle cx="10" cy="10" r="10" fill="url(%23a)"/><circle cx="50" cy="5" r="5" fill="url(%23a)"/><circle cx="80" cy="15" r="8" fill="url(%23a)"/></svg>') repeat;
    opacity: 0.1;
}

.hero-section .container {
    position: relative;
    z-index: 1;
}

.hero-section h1 {
    font-size: 3rem;
    font-weight: 700;
    margin-bottom: 1rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    color: white !important;
}

.hero-section .lead {
    font-size: 1.3rem;
    opacity: 0.95;
    margin-bottom: 2rem;
    color: white !important;
}

.hero-section p {
    font-size: 1.1rem;
    opacity: 0.9;
    color: white !important;
}

/* Banners de estado */
.closed-banner {
    background: linear-gradient(45deg, var(--danger-color), #c82333);
    color: white;
    padding: 15px 0;
    text-align: center;
    font-weight: bold;
    box-shadow: 0 2px 10px rgba(220, 53, 69, 0.3);
}

.alert {
    border: none;
    border-radius: var(--border-radius-base);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

/* Navegación de categorías mejorada */
.category-nav {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.03) 0%, rgba(118, 75, 162, 0.05) 100%) !important;
    padding: 2rem 0;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    border-bottom: 1px solid #e9ecef;
}

.category-filter {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    border: 2px solid #dee2e6 !important;
    color: #212529 !important;
    padding: 0.75rem 1.5rem;
    margin: 0.25rem;
    border-radius: 25px;
    font-weight: 600;
    transition: var(--transition-base);
    position: relative;
    overflow: hidden;
}

.category-filter::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
    transition: left 0.5s;
}

.category-filter:hover::before {
    left: 100%;
}

.category-filter:hover {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
    border-color: var(--primary-color) !important;
    color: white !important;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.category-filter.active {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%) !important;
    border-color: var(--primary-color) !important;
    color: white !important;
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

/* Tarjetas de productos mejoradas */
.product-card {
    background: #ffffff !important;
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
    transition: all 0.4s ease;
    margin-bottom: 2rem;
    overflow: hidden;
    height: 100%;
    position: relative;
}

.product-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, rgba(255, 255, 255, 0.3) 0%, rgba(248, 249, 250, 0.5) 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
}

.product-card:hover::before {
    opacity: 1;
}

.product-card:hover {
    transform: translateY(-10px);
    box-shadow: var(--shadow-hover);
}

.product-image {
    height: 250px;
    width: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
    border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
}

.product-card:hover .product-image {
    transform: scale(1.05);
}

.product-placeholder {
    height: 250px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d !important;
    font-size: 3rem;
    border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
}

.card-body {
    padding: 1.75rem;
    position: relative;
    z-index: 2;
    background: #ffffff !important;
    color: #212529 !important;
}

.card-title {
    font-size: 1.4rem;
    font-weight: 700;
    color: #212529 !important;
    margin-bottom: 0.75rem;
    line-height: 1.3;
}

.card-text {
    color: #6c757d !important;
    font-size: 0.95rem;
    margin-bottom: 1.25rem;
    line-height: 1.6;
}

.price {
    color: var(--success-color);
    font-weight: 700;
    font-size: 1.5rem;
    text-shadow: 1px 1px 2px rgba(40, 167, 69, 0.2);
}

/* Botones mejorados */
.btn-add-to-cart {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border: none;
    color: white;
    padding: 0.5rem 1rem;  /* Reducido de 0.75rem 1.5rem */
    border-radius: 20px;   /* Reducido de 25px */
    font-weight: 600;
    font-size: 0.9rem;     /* Agregado para reducir texto */
    transition: var(--transition-base);
    position: relative;
    overflow: hidden;
}


.btn-add-to-cart::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
    transition: left 0.6s;
}

.btn-add-to-cart:hover::before {
    left: 100%;
}

.btn-add-to-cart:hover {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    color: white !important;
}

.btn-add-to-cart:disabled {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
    transform: none;
    cursor: not-allowed;
}

.btn-add-to-cart:disabled::before {
    display: none;
}

/* Badge estados */
.badge {
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius-large);
    font-weight: 600;
}

.badge.bg-secondary {
    background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%) !important;
}

.badge.bg-warning {
    background: linear-gradient(135deg, var(--warning-color) 0%, #e0a800 100%) !important;
}

.badge.bg-danger {
    background: linear-gradient(135deg, var(--danger-color) 0%, #c82333 100%) !important;
}

/* Botón flotante del carrito mejorado */
.cart-floating {
    position: fixed;
    bottom: 25px;
    right: 25px;
    z-index: 1050;
}

.cart-floating .btn {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    border: none;
    width: 70px;
    height: 70px;
    border-radius: 50%;
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    transition: var(--transition-base);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white !important;
}

.cart-floating .btn:hover {
    background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%);
    transform: scale(1.1);
    box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
    color: white !important;
}


.cart-floating .badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: linear-gradient(135deg, var(--accent-color) 0%, #ff4040 100%);
    box-shadow: 0 2px 10px rgba(255, 107, 107, 0.5);
    min-width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
}

/* Animación pulse mejorada */
@keyframes pulse {
    0% { transform: scale(1); box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4); }
    50% { transform: scale(1.05); box-shadow: 0 12px 35px rgba(40, 167, 69, 0.6); }
    100% { transform: scale(1); box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4); }
}

.pulse {
    animation: pulse 2s infinite;
}

/* Modales mejorados */
.modal-content {
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
    overflow: hidden;
    background: #ffffff !important;
    color: #212529 !important;
}

.modal-header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    border: none;
    padding: 1.5rem 2rem;
}

.modal-title {
    font-weight: 700;
    font-size: 1.3rem;
    color: white !important;
}

.modal-body {
    padding: 2rem;
    background: #ffffff !important;
    color: #212529 !important;
}

.modal-footer {
    border: none;
    padding: 1.5rem 2rem;
    background: #f8f9fa !important;
}

/* Cart items styling */
.cart-item {
    border-bottom: 1px solid #f0f0f0;
    padding: 1rem 0;
    transition: background-color 0.3s ease;
    color: #212529 !important;
}

.cart-item:hover {
    background-color: #f8f9fa !important;
    border-radius: var(--border-radius-base);
    margin: 0 -1rem;
    padding-left: 1rem;
    padding-right: 1rem;
}

.cart-item h6 {
    color: #212529 !important;
}

.cart-item .text-muted {
    color: #6c757d !important;
}

/* Form improvements */
.form-control {
    border: 2px solid #e9ecef;
    border-radius: var(--border-radius-base);
    padding: 0.75rem 1rem;
    transition: var(--transition-base);
    background: #ffffff !important;
    color: #212529 !important;
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    background: #ffffff !important;
    color: #212529 !important;
}

.form-label {
    color: #212529 !important;
}

.form-text {
    color: #6c757d !important;
}

.input-group-text {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border: 2px solid #e9ecef;
    color: #6c757d;
}

/* Loading overlay mejorado */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(0, 0, 0, 0.7) 0%, rgba(33, 37, 41, 0.8) 100%);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
    backdrop-filter: blur(5px);
}

.loading-spinner {
    text-align: center;
    color: white;
}

.loading-spinner i {
    font-size: 3rem;
    margin-bottom: 1rem;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Success modal styling */
.order-status-modal .modal-content {
    border-radius: 25px;
    overflow: hidden;
}

.success-icon {
    color: var(--success-color);
    font-size: 5rem;
    margin-bottom: 1.5rem;
    text-shadow: 2px 2px 4px rgba(40, 167, 69, 0.3);
}

/* Estados especiales de productos */
.product-unavailable {
    opacity: 0.7;
    position: relative;
}

.product-unavailable::after {
    content: 'No disponible';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.95) 0%, rgba(200, 35, 51, 0.95) 100%);
    color: white;
    padding: 0.75rem 1.5rem;
    border-radius: 25px;
    font-weight: 600;
    font-size: 1rem;
    box-shadow: 0 4px 15px rgba(220, 53, 69, 0.4);
}

/* Animaciones de entrada */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.product-card {
    animation: fadeInUp 0.6s ease forwards;
}

.product-item:nth-child(even) .product-card {
    animation-delay: 0.1s;
}

.product-item:nth-child(odd) .product-card {
    animation-delay: 0.15s;
}

/* Mejoras en alerts */
.alert {
    border-radius: var(--border-radius-large);
    padding: 1rem 1.5rem;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.alert-info {
    background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
    border-color: #97cadb;
    color: #0c5460;
}

.alert-warning {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-color: #f0c674;
    color: #856404;
}

.alert-success {
    background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
    border-color: #a3cfbb;
    color: #155724;
}

/* Footer mejorado */
footer {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    margin-top: 4rem;
}

footer h5 {
    color: #fff;
    font-weight: 700;
    margin-bottom: 1rem;
}

footer p {
    color: #adb5bd;
    margin-bottom: 0.5rem;
}

footer a {
    color: #20c997;
    transition: color 0.3s ease;
}

footer a:hover {
    color: #17a2b8;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .header h1 {
        font-size: 2rem;
    }

    .header {
        padding: 1.5rem 0;
    }
    .hero-section {
        padding: 2.5rem 0;
    }

    .hero-section h1 {
        font-size: 2.2rem;
    }

    .hero-section .lead {
        font-size: 1.1rem;
    }

    .category-nav {
        padding: 1.5rem 0;
    }

    .category-filter {
        padding: 0.6rem 1.2rem;
        font-size: 0.9rem;
        margin: 0.2rem;
    }

    .product-image,
    .product-placeholder {
        height: 200px;
    }

    .card-body {
        padding: 1.25rem;
    }

    .card-title {
        font-size: 1.2rem;
    }

    .cart-floating .btn {
        width: 60px;
        height: 60px;
    }

    .modal-body {
        padding: 1.5rem;
    }
        .btn-add-to-cart {
        padding: 0.4rem 0.8rem;
        font-size: 0.85rem;
    }
}

/* Google Maps styling */
.pac-container {
    z-index: 10000 !important;
    border-radius: var(--border-radius-base);
    border: 1px solid #ddd;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
    overflow: hidden;
}

.pac-item {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.pac-item:hover {
    background-color: #f8f9fa;
}

.pac-item-selected {
    background-color: rgba(102, 126, 234, 0.1);
}

/* Phone preview animation */
#phonePreview {
    animation: slideInUp 0.4s ease-out;
}

@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Textos adicionales */
h1, h2, h3, h4, h5, h6 {
    color: #fff !important;
}

p {
    color: #fff !important;
}

.text-muted {
    color: #6c757d !important;
}

/* Nav links */
.nav-link {
    color: white !important;
}

.nav-link:hover {
    color: #f8f9fa !important;
}

.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { 
        transform: scale(1); 
        box-shadow: 0 0 0 0 rgba(37, 211, 102, 0.7); 
    }
    70% { 
        transform: scale(1.05); 
        box-shadow: 0 0 0 10px rgba(37, 211, 102, 0); 
    }
    100% { 
        transform: scale(1); 
        box-shadow: 0 0 0 0 rgba(37, 211, 102, 0); 
    }
}
</style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner"></i>
            <div class="mt-3">Procesando pedido...</div>
        </div>
    </div>

    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-utensils me-2"></i>
                <?php echo $restaurant_name; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <div class="nav-item me-3">
                    <span class="status-indicator <?php echo $is_open ? 'status-open' : 'status-closed'; ?>">
                        <i class="fas fa-<?php echo $is_open ? 'check-circle' : 'clock'; ?> me-1"></i>
                        <?php echo $is_open ? 'Abierto' : 'Cerrado'; ?>
                    </span>
                </div>
                <a class="nav-link" href="admin/login.php">
                    <i class="fas fa-user me-1"></i> Acceso Staff
                </a>
            </div>
        </div>
    </nav>

    <?php if (!$is_open): ?>
        <div class="closed-banner">
            <i class="fas fa-clock me-2"></i>
            Estamos cerrados. Horario de atención: <?php echo $opening_time; ?> - <?php echo $kitchen_closing_time; ?>
        </div>
    <?php endif; ?>

    <?php if ($online_orders_enabled !== '1'): ?>
        <div class="alert alert-warning text-center mb-0">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Hero Section -->
    <div class="header">
        <div class="container">
            <h1 class="mb-4">¡Bienvenido a <?php echo $restaurant_name; ?>!</h1>
            <i class="lead">Descubre nuestro delicioso menú y haz tu pedido online o por Whatsapp</p>
                <p class="fas fa-phone me-2"></p>
                
                <?php echo $settings['restaurant_phone'] ?? 'Teléfono no disponible'; ?>
            </i>
            <?php if ($is_open && $online_orders_enabled === '1'): ?>
                <p class="mb-0">
                    <i class="fas fa-truck me-2"></i>
                    Entregas hasta las <?php echo $closing_time; ?>
                </p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Category Navigation -->
    <div class="category-nav">
        <div class="container">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex flex-wrap justify-content-center">
                        <button class="btn category-filter active" data-category="all">
                            <i class="fas fa-th-large me-1"></i> Todos
                        </button>
                        <?php foreach ($categories as $category): ?>
                            <button class="btn category-filter" data-category="<?php echo $category['id']; ?>">
                                <i class="fas fa-utensils me-1"></i> <?php echo htmlspecialchars($category['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Section -->
    <div class="container my-5">
        <div class="row g-4" id="products-container">
            <?php foreach ($products as $product): ?>
                <div class="col-lg-3 col-md-6 col-sm-12 product-item" data-category="<?php echo $product['category_id']; ?>">
                    <div class="card product-card <?php echo !$product['is_available'] ? 'product-unavailable' : ''; ?>">
                        <?php if ($product['image']): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                 class="product-image" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>"
                                 loading="lazy">
                        <?php else: ?>
                            <div class="product-placeholder">
                                <i class="fas fa-utensils"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            
                            <?php if (!empty($product['description'])): ?>
                                <p class="card-text"><?php echo htmlspecialchars($product['description']); ?></p>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="price"><?php echo formatPrice($product['price']); ?></span>
                                
                                <?php if ($product['is_available'] && $is_open && $online_orders_enabled === '1'): ?>
                                    <button class="btn btn-add-to-cart" 
                                            onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', <?php echo $product['price']; ?>)">
                                        <i class="fas fa-plus me-1"></i> 
                                    </button>
                                <?php elseif (!$product['is_available']): ?>
                                    <span class="badge bg-secondary">No disponible</span>
                                <?php elseif (!$is_open): ?>
                                    <span class="badge bg-warning">Cerrado</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">No disponible</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Cart Floating Button -->
    <?php if ($is_open && $online_orders_enabled === '1'): ?>
        <div class="cart-floating">
            <button class="btn rounded-circle pulse" data-bs-toggle="modal" data-bs-target="#cartModal">
                <i class="fas fa-shopping-cart fa-lg"></i>
                <span class="badge rounded-pill" id="cart-count">0</span>
            </button>
        </div>
    <?php endif; ?>

    <!-- Cart Modal -->
    <div class="modal fade" id="cartModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Mi Pedido
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="cart-items">
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                            <p>Tu carrito está vacío</p>
                        </div>
                    </div>
                    
                    <div id="cart-summary" class="d-none">
                        <hr>
                        <div class="d-flex justify-content-between">
                            <strong>Total: </strong>
                            <strong id="cart-total" class="price">$0.00</strong>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" onclick="clearCart()">
                        <i class="fas fa-trash me-1"></i> Limpiar
                    </button>
                    <button type="button" class="btn btn-success" id="btn-checkout" onclick="checkout()" disabled>
                        <i class="fas fa-credit-card me-1"></i> Realizar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Customer Data Modal -->
    <div class="modal fade" id="customerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>
                        Datos para el Pedido
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="customerForm">
                        <div class="mb-3">
                            <label class="form-label">Nombre completo *</label>
                            <input type="text" class="form-control" id="customerName" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">
                                Teléfono *
                                <small class="text-muted">(se agregará automáticamente el código +54)</small>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-phone"></i>
                                </span>
                                <input type="tel" 
                                       class="form-control" 
                                       id="customerPhone" 
                                       required 
                                       placeholder="Ej: 3482549555"
                                       maxlength="15"
                                       pattern="[0-9]*"
                                       inputmode="numeric">
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle text-primary"></i>
                                Ingrese su número sin el 0 inicial ni el 15. 
                                <strong>Ejemplo:</strong> para +54 3482 549555, escriba: 3482549555
                            </div>
                            <div id="phonePreview" class="form-text text-success" style="display: none;">
                                <i class="fas fa-whatsapp"></i>
                                <span id="phonePreviewText"></span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">
                                Dirección de entrega *
                                <?php if ($google_maps_api_key && $google_maps_api_key !== 'TU_API_KEY_AQUI'): ?>
                                    <small class="text-muted">(Busque y seleccione de las sugerencias)</small>
                                <?php endif; ?>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fas fa-map-marker-alt"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       id="customerAddress" 
                                       required 
                                       placeholder="Escriba su dirección completa..."
                                       autocomplete="off">
                            </div>
                            
                            <?php if ($google_maps_api_key && $google_maps_api_key !== 'TU_API_KEY_AQUI'): ?>
                                <!-- Solo mostrar elementos de Google Maps si hay API key válida -->
                                <div id="address-warning" class="alert alert-warning py-2 mt-2" style="display: none;">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    <small>Por favor seleccione una dirección de las sugerencias de Google Maps</small>
                                </div>
                                
                                <div id="address-details" style="display: none;" class="mt-2"></div>
                                <div id="delivery-status" class="mt-2"></div>
                            <?php endif; ?>
                            
                            <div class="form-text">
                                <i class="fas fa-info-circle text-primary"></i>
                                Escriba su dirección completa con número, calle y barrio
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Referencias adicionales (opcional)</label>
                            <textarea class="form-control" 
                                      id="customerReferences" 
                                      rows="2" 
                                      placeholder="Ej: Casa azul, portón negro, timbre 2B, etc."></textarea>
                            <div class="form-text">
                                <small>Agregue referencias que ayuden al delivery a encontrar su ubicación</small>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Observaciones (opcional)</label>
                            <textarea class="form-control" id="customerNotes" rows="2" 
                                      placeholder="Aclaraciones sobre el pedido, alergia, etc."></textarea>
                        </div>
                        
                        <!-- Información adicional -->
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>Tiempo estimado:</strong> 30-45 minutos<br>
                            <strong>Área de entrega:</strong> <?php echo $settings['restaurant_address'] ?? 'Consultar al realizar pedido'; ?>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="submitOrder()">
                        <i class="fas fa-check me-1"></i> Enviar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Order Status Modal -->
    <div class="modal fade" id="orderStatusModal" tabindex="-1">
        <div class="modal-dialog order-status-modal">
            <div class="modal-content text-center">
                <div class="modal-body p-4">
                    <div id="order-success" class="d-none">
    <i class="fas fa-check-circle success-icon"></i>
    <h4 class="text-success mb-3">¡Pedido Registrado!</h4>
    <p class="mb-3">Su pedido <strong id="order-number-display"></strong> ha sido registrado correctamente.</p>
    
    <div class="alert alert-warning mb-4">
        <i class="fas fa-whatsapp me-2"></i>
        <strong>PASO FINAL OBLIGATORIO:</strong><br>
        Debe enviar el WhatsApp para confirmar su pedido y recibir actualizaciones.
    </div>
    
    <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>¿Por qué es necesario?</strong><br>
        Para poder enviarle confirmación, tiempo de entrega y actualizaciones del estado de su pedido.
    </div>
    
    <div class="alert alert-success mb-3">
        <i class="fas fa-clock me-2"></i>
        Tiempo estimado: <strong>30-45 minutos</strong> (una vez confirmado)
    </div>
    
    <!-- BOTÓN PARA ABRIR WHATSAPP -->
    <div class="text-center mb-3">
        <button id="open-whatsapp-btn" class="btn btn-success btn-lg pulse" 
                style="background: linear-gradient(135deg, #25d366 0%, #128c7e 100%); border: none;">
            <i class="fab fa-whatsapp me-2"></i>
            Enviar WhatsApp Ahora
        </button>
    </div>
    
    <small class="text-muted">Al hacer clic se abrirá WhatsApp con el mensaje preparado</small>
</div>
                    
                    <div id="order-error" class="d-none">
                        <i class="fas fa-exclamation-triangle text-danger mb-3" style="font-size: 4rem;"></i>
                        <h4 class="text-danger mb-3">Error al procesar pedido</h4>
                        <p class="mb-3" id="error-message">Ha ocurrido un error. Por favor intente nuevamente.</p>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-refresh me-1"></i> Intentar de nuevo
                        </button>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-home me-1"></i> Continuar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo $restaurant_name; ?></h5>
                    <p class="mb-1">
                        <i class="fas fa-map-marker-alt me-2"></i>
                        <?php echo $settings['restaurant_address'] ?? 'Dirección no disponible'; ?>
                    </p>
                    <p class="mb-1">
                        <i class="fas fa-phone me-2"></i>
                        <?php echo $settings['restaurant_phone'] ?? 'Teléfono no disponible'; ?>
                    </p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h5>Horarios de atención</h5>
                    <p class="mb-1">Lunes a Domingo: <?php echo $opening_time; ?> - <?php echo $closing_time; ?></p>
                    <p class="mb-0">
                        <i class="fas fa-utensils me-2"></i>
                        Cocina hasta las <?php echo $kitchen_closing_time; ?>
                    </p>
                    <?php if ($whatsapp_number): ?>
                        <p class="mt-2">
                            <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp_number); ?>" 
                               class="text-success text-decoration-none" target="_blank">
                                <i class="fab fa-whatsapp me-2"></i>
                                WhatsApp: <?php echo $whatsapp_number; ?>
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <?php if ($google_maps_api_key && $google_maps_api_key !== 'TU_API_KEY_AQUI'): ?>
        <!-- Solo cargar Google Maps si hay API key válida -->
        <script>
            // Función que se ejecuta cuando Google Maps se carga
            function initAutocomplete() {
                const addressInput = document.getElementById('customerAddress');
                if (!addressInput) return;

                try {
                    // Obtener las coordenadas del restaurante desde PHP (extraídas automáticamente de Google Maps URL)
                    const restaurantLat = <?php 
                        // Obtener coordenadas desde la base de datos o extraer de la URL si no existen
                        $restaurant_lat = $settings['restaurant_lat'] ?? null;
                        $restaurant_lng = $settings['restaurant_lng'] ?? null;
                        
                        // Si no hay coordenadas guardadas, intentar extraerlas de la URL
                        if (!$restaurant_lat || !$restaurant_lng) {
                            $maps_url = $settings['restaurant_maps_url'] ?? '';
                            if (!empty($maps_url)) {
                                $coords = extractCoordinatesFromMapsUrl($maps_url);
                                if ($coords) {
                                    $restaurant_lat = $coords['lat'];
                                    $restaurant_lng = $coords['lng'];
                                }
                            }
                        }
                        
                        echo $restaurant_lat ?? '-29.1167'; // Valor por defecto para Avellaneda, Santa Fe
                    ?>;
                    
                    const restaurantLng = <?php echo $restaurant_lng ?? '-59.6500'; ?>;
                    
                    // Configurar el autocomplete con restricciones para Argentina
                    const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                        types: ['address'],
                        componentRestrictions: {
                            country: ['ar'] // Restringir a Argentina
                        },
                        fields: ['address_components', 'formatted_address', 'geometry']
                    });

                    // Configurar el área de búsqueda usando las coordenadas reales del restaurante
                    const restaurantCenter = new google.maps.LatLng(restaurantLat, restaurantLng);
                    const circle = new google.maps.Circle({
                        center: restaurantCenter,
                        radius: <?php echo ($settings['max_delivery_distance'] ?? '25') * 1000; ?> // Convertir km a metros
                    });
                    autocomplete.setBounds(circle.getBounds());

                    console.log('Ubicación del restaurante configurada:', {
                        lat: restaurantLat, 
                        lng: restaurantLng,
                        source: '<?php echo !empty($settings['restaurant_maps_url']) ? 'Google Maps URL' : 'Coordenadas por defecto'; ?>'
                    });

                    // Variable para almacenar los detalles de la dirección
                    let selectedAddressDetails = null;

                    // Manejar la selección de una dirección
                    autocomplete.addListener('place_changed', function() {
                        const place = autocomplete.getPlace();
                        
                        if (!place.geometry) {
                            document.getElementById('address-warning').style.display = 'block';
                            selectedAddressDetails = null;
                            return;
                        }

                        const warning = document.getElementById('address-warning');
                        if (warning) warning.style.display = 'none';

                        selectedAddressDetails = {
                            formatted_address: place.formatted_address,
                            lat: place.geometry.location.lat(),
                            lng: place.geometry.location.lng(),
                            components: {}
                        };

                        // Procesar componentes de dirección
                        place.address_components.forEach(component => {
                            const types = component.types;
                            if (types.includes('street_number')) {
                                selectedAddressDetails.components.street_number = component.long_name;
                            }
                            if (types.includes('route')) {
                                selectedAddressDetails.components.street_name = component.long_name;
                            }
                            if (types.includes('locality')) {
                                selectedAddressDetails.components.city = component.long_name;
                            }
                            if (types.includes('administrative_area_level_1')) {
                                selectedAddressDetails.components.state = component.long_name;
                            }
                            if (types.includes('postal_code')) {
                                selectedAddressDetails.components.postal_code = component.long_name;
                            }
                        });

                        addressInput.value = place.formatted_address;
                        showAddressDetails(selectedAddressDetails);
                        validateDeliveryArea(selectedAddressDetails.lat, selectedAddressDetails.lng);

                        console.log('Dirección seleccionada:', selectedAddressDetails);
                    });

                    window.addressAutocomplete = autocomplete;
                    window.getSelectedAddressDetails = () => selectedAddressDetails;
                    
                    // Almacenar las coordenadas del restaurante globalmente
                    window.restaurantLocation = { lat: restaurantLat, lng: restaurantLng };
                    
                } catch (error) {
                    console.error('Error inicializando Google Maps:', error);
                }
            }

            // Funciones auxiliares para Google Maps
            function showAddressDetails(details) {
                const detailsDiv = document.getElementById('address-details');
                if (!detailsDiv) return;

                const { components } = details;
                let detailsHTML = '<small class="text-muted">';
                
                if (components.city) {
                    detailsHTML += `<i class="fas fa-map-marker-alt me-1"></i>Ciudad: ${components.city}`;
                }
                if (components.state) {
                    detailsHTML += ` • Provincia: ${components.state}`;
                }
                if (components.postal_code) {
                    detailsHTML += ` • CP: ${components.postal_code}`;
                }
                
                detailsHTML += '</small>';
                detailsDiv.innerHTML = detailsHTML;
                detailsDiv.style.display = 'block';
            }

            function validateDeliveryArea(lat, lng) {
                // Usar las coordenadas dinámicas del restaurante
                const restaurantLocation = window.restaurantLocation || { 
                    lat: <?php echo $restaurant_lat ?? '-29.1167'; ?>, 
                    lng: <?php echo $restaurant_lng ?? '-59.6500'; ?> 
                };
                
                const maxDeliveryDistance = <?php echo $settings['max_delivery_distance'] ?? '25'; ?>; // km
                
                const distance = calculateDistance(lat, lng, restaurantLocation.lat, restaurantLocation.lng);
                
                const deliveryStatus = document.getElementById('delivery-status');
                if (!deliveryStatus) return;

                if (distance <= maxDeliveryDistance) {
                    deliveryStatus.innerHTML = `
                        <div class="alert alert-success py-2">
                            <i class="fas fa-check-circle me-1"></i>
                            <strong>Zona de delivery válida</strong> (${distance.toFixed(1)} km desde el restaurante)
                        </div>
                    `;
                } else {
                    deliveryStatus.innerHTML = `
                        <div class="alert alert-warning py-2">
                            <i class="fas fa-exclamation-triangle me-1"></i>
                            <strong>Fuera del área de delivery</strong> (${distance.toFixed(1)} km)
                            <br><small>Consulte disponibilidad al restaurante</small>
                        </div>
                    `;
                }
            }

            function calculateDistance(lat1, lng1, lat2, lng2) {
                const R = 6371;
                const dLat = (lat2 - lat1) * Math.PI / 180;
                const dLng = (lng2 - lng1) * Math.PI / 180;
                const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                          Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                          Math.sin(dLng/2) * Math.sin(dLng/2);
                const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
                return R * c;
            }
        </script>
        
        <!-- Cargar Google Maps API solo si hay API key válida -->
        <script async defer 
                src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_maps_api_key; ?>&libraries=places&callback=initAutocomplete&loading=async">
        </script>
    <?php else: ?>
        <script>
            // Función vacía si no hay Google Maps
            function initAutocomplete() {
                console.log('Google Maps no configurado');
            }
            window.getSelectedAddressDetails = () => null;
        </script>
    <?php endif; ?>

    <script>
        // Variables globales
        let cart = [];
        const isOpen = <?php echo $is_open ? 'true' : 'false'; ?>;
        const onlineOrdersEnabled = <?php echo $online_orders_enabled === '1' ? 'true' : 'false'; ?>;
        let isSubmitting = false;

        // ===== FUNCIONES DE FORMATEO DE TELÉFONO =====
        function formatPhoneNumber(phone) {
    let cleanPhone = phone.replace(/[^0-9]/g, '');
    
    if (cleanPhone.startsWith('549')) {
        return cleanPhone;
    }
    
    if (cleanPhone.startsWith('54')) {
        cleanPhone = '9' + cleanPhone;
        return cleanPhone;
    }
    
    if (cleanPhone.startsWith('9') && cleanPhone.length > 10) {
        cleanPhone = cleanPhone.substring(1);
    }
    
    if (cleanPhone.startsWith('15')) {
        cleanPhone = cleanPhone.substring(2);
    }
    
    if (cleanPhone.length === 10) {
        cleanPhone = '549' + cleanPhone;
    }
    
    if (cleanPhone.length === 8 || cleanPhone.length === 9) {
        const defaultAreaCode = '3482';
        cleanPhone = '549' + defaultAreaCode + cleanPhone;
    }
    
    return cleanPhone;
}

        function isValidArgentinePhone(phone) {
    const cleanPhone = phone.replace(/[^0-9]/g, '');
    
    const areaCodes = [
        '11', '221', '223', '261', '341', '351', '381', '3482', '3476', '342',
        '376', '388', '299', '2966', '264', '280', '383', '385', '387', '2920', '2944'
    ];
    
    if (cleanPhone.startsWith('549')) {
        const phoneWithoutCountry = cleanPhone.substring(3);
        
        return areaCodes.some(areaCode => 
            phoneWithoutCountry.startsWith(areaCode) && 
            phoneWithoutCountry.length >= areaCode.length + 6 && 
            phoneWithoutCountry.length <= areaCode.length + 8
        );
    }
    
    return false;
}


        function showPhonePreview(phone) {
    const phonePreview = document.getElementById('phonePreview');
    const phonePreviewText = document.getElementById('phonePreviewText');
    
    if (!phonePreview || !phonePreviewText) return;
    
    if (phone.length >= 8) {
        const formattedPhone = formatPhoneNumber(phone);
        const displayPhone = formattedPhone.replace(/^549/, '+54 9 ');
        
        let prettyPhone = displayPhone;
        if (prettyPhone.length >= 15) {
            prettyPhone = prettyPhone.substring(0, 5) + 
                         prettyPhone.substring(5, 9) + ' ' + 
                         prettyPhone.substring(9);
        }
        
        phonePreviewText.textContent = `WhatsApp: ${prettyPhone}`;
        phonePreview.style.display = 'block';
    } else {
        phonePreview.style.display = 'none';
    }
}

        function validatePhoneBeforeSubmit(phone) {
    const formattedPhone = formatPhoneNumber(phone);
    
    if (!isValidArgentinePhone(formattedPhone)) {
        alert('El número de teléfono no parece ser válido para Argentina. Verifique el código de área.');
        return false;
    }
    
    return true;
}

        // ===== FUNCIONES DEL CARRITO =====
        function addToCart(id, name, price) {
            if (!isOpen || !onlineOrdersEnabled) {
                alert('Los pedidos online no están disponibles en este momento.');
                return;
            }
            
            if (!id || !name || !price) {
                console.error('Parámetros inválidos para agregar al carrito');
                return;
            }
            
            const existingItem = cart.find(item => item.id === id);
            
            if (existingItem) {
                existingItem.quantity += 1;
            } else {
                cart.push({ id, name, price: parseFloat(price), quantity: 1 });
            }
            
            updateCartDisplay();
            saveCartToStorage();
            
            // Feedback visual
            const button = event.target.closest('button');
            if (button) {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check me-1"></i> Agregado';
                button.disabled = true;
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.disabled = false;
                }, 1000);
            }
            
            trackEvent('add_to_cart', 'ecommerce', name);
        }

        function removeFromCart(id) {
            cart = cart.filter(item => item.id !== id);
            updateCartDisplay();
            saveCartToStorage();
        }

        function updateQuantity(id, quantity) {
            const item = cart.find(item => item.id === id);
            if (item) {
                item.quantity = Math.max(0, quantity);
                if (item.quantity === 0) {
                    removeFromCart(id);
                    return;
                }
            }
            updateCartDisplay();
            saveCartToStorage();
        }

        function updateCartDisplay() {
            const cartItems = document.getElementById('cart-items');
            const cartCount = document.getElementById('cart-count');
            const cartTotal = document.getElementById('cart-total');
            const cartSummary = document.getElementById('cart-summary');
            const btnCheckout = document.getElementById('btn-checkout');
            
            const totalItems = cart.reduce((sum, item) => sum + item.quantity, 0);
            if (cartCount) cartCount.textContent = totalItems;
            
            if (cart.length === 0) {
                cartItems.innerHTML = `
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                        <p>Tu carrito está vacío</p>
                    </div>
                `;
                if (cartSummary) cartSummary.classList.add('d-none');
                if (btnCheckout) btnCheckout.disabled = true;
            } else {
                cartItems.innerHTML = cart.map(item => `
                    <div class="cart-item">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <h6 class="mb-0">${item.name}</h6>
                                <small class="text-muted">${formatPrice(item.price)} c/u</small>
                            </div>
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-outline-secondary me-2" onclick="updateQuantity(${item.id}, ${item.quantity - 1})">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="mx-2">${item.quantity}</span>
                                <button class="btn btn-sm btn-outline-secondary me-2" onclick="updateQuantity(${item.id}, ${item.quantity + 1})">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${item.id})">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="text-end mt-2">
                            <strong>${formatPrice(item.price * item.quantity)}</strong>
                        </div>
                    </div>
                `).join('');
                
                const total = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
                if (cartTotal) cartTotal.textContent = formatPrice(total);
                if (cartSummary) cartSummary.classList.remove('d-none');
                if (btnCheckout) btnCheckout.disabled = false;
            }
        }

        function clearCart() {
            cart = [];
            updateCartDisplay();
            localStorage.removeItem('restaurant_cart');
        }

        // ===== FUNCIONES DE CHECKOUT =====
        function checkout() {
            if (cart.length === 0) {
                alert('Tu carrito está vacío');
                return;
            }
            
            if (!isOpen || !onlineOrdersEnabled) {
                alert('Los pedidos online no están disponibles en este momento.');
                return;
            }
            
            const cartModal = bootstrap.Modal.getInstance(document.getElementById('cartModal'));
            cartModal.hide();
            
            setTimeout(() => {
                const customerModal = new bootstrap.Modal(document.getElementById('customerModal'));
                customerModal.show();
            }, 300);
            
            trackEvent('begin_checkout', 'ecommerce', 'online_order');
        }

        function validateDeliveryAreaBeforeSubmit() {
            const selectedAddress = window.getSelectedAddressDetails ? window.getSelectedAddressDetails() : null;
            
            if (selectedAddress) {
                const restaurantLocation = window.restaurantLocation || { 
                    lat: <?php echo $restaurant_lat ?? '-29.1167'; ?>, 
                    lng: <?php echo $restaurant_lng ?? '-59.6500'; ?> 
                };
                const maxDeliveryDistance = <?php echo $settings['max_delivery_distance'] ?? '25'; ?>;
                
                const distance = calculateDistance(
                    selectedAddress.lat, 
                    selectedAddress.lng, 
                    restaurantLocation.lat, 
                    restaurantLocation.lng
                );
                
                if (distance > maxDeliveryDistance) {
                    const confirmDelivery = confirm(
                        `La dirección seleccionada está a ${distance.toFixed(1)} km del restaurante, ` +
                        `fuera del área de delivery habitual (${maxDeliveryDistance} km). ` +
                        `¿Desea continuar? (Se puede aplicar costo adicional)`
                    );
                    
                    if (!confirmDelivery) {
                        return false;
                    }
                }
            }
            
            return true;
        }

        function validateForm() {
            const name = document.getElementById('customerName').value.trim();
            const phone = document.getElementById('customerPhone').value.trim();
            const address = document.getElementById('customerAddress').value.trim();
            
            if (!name || name.length < 2) {
                alert('Por favor ingrese un nombre válido');
                return false;
            }
            
            if (!phone || phone.length < 8) {
                alert('Por favor ingrese un teléfono válido');
                return false;
            }
            
            const formattedPhone = formatPhoneNumber(phone);
            if (formattedPhone.length < 13 || formattedPhone.length > 16) {
    alert('El número de teléfono no tiene un formato válido. Ejemplo: 3482549555');
    return false;
}

            
            if (!validatePhoneBeforeSubmit(phone)) {
                return false;
            }
            
            if (!address || address.length < 10) {
                alert('Por favor ingrese una dirección completa');
                return false;
            }
            
            // Solo validar Google Maps si está disponible
            <?php if ($google_maps_api_key && $google_maps_api_key !== 'TU_API_KEY_AQUI'): ?>
            const selectedAddress = window.getSelectedAddressDetails ? window.getSelectedAddressDetails() : null;
            if (!selectedAddress) {
                const manualAddress = confirm(
                    'No se detectó una dirección de Google Maps. ' +
                    '¿Desea continuar con la dirección ingresada manualmente? ' +
                    '(Recomendamos usar las sugerencias de Google Maps para mayor precisión)'
                );
                
                if (!manualAddress) {
                    return false;
                }
            }
            
            if (!validateDeliveryAreaBeforeSubmit()) {
                return false;
            }
            <?php endif; ?>
            
            return true;
        }

        async function submitOrder() {
    if (isSubmitting) return;
    
    if (!validateForm()) return;
    
    isSubmitting = true;
    
    try {
        const name = document.getElementById('customerName').value.trim();
        const phoneInput = document.getElementById('customerPhone');
        const rawPhone = phoneInput.value.trim();
        const formattedPhone = formatPhoneNumber(rawPhone);
        const address = document.getElementById('customerAddress').value.trim();
        const notes = document.getElementById('customerNotes').value.trim();
        const references = document.getElementById('customerReferences') ? 
                          document.getElementById('customerReferences').value.trim() : '';
        
        const selectedAddress = window.getSelectedAddressDetails ? window.getSelectedAddressDetails() : null;
        
        let fullAddress = address;
        if (references) {
            fullAddress += ` - Referencias: ${references}`;
        }
        
        const orderData = {
            customer_name: name,
            customer_phone: formattedPhone,
            customer_address: fullAddress,
            customer_notes: notes,
            customer_references: references,
            items: cart,
            address_details: selectedAddress ? {
                formatted_address: selectedAddress.formatted_address,
                coordinates: {
                    lat: selectedAddress.lat,
                    lng: selectedAddress.lng
                },
                components: selectedAddress.components
            } : null
        };
        

        
        // Validaciones adicionales
        if (!orderData.customer_name || orderData.customer_name.length === 0) {
            throw new Error('Nombre del cliente vacío');
        }
        
        if (!orderData.customer_phone || orderData.customer_phone.length === 0) {
            throw new Error('Teléfono del cliente vacío');
        }
        
        if (!orderData.customer_address || orderData.customer_address.length === 0) {
            throw new Error('Dirección del cliente vacía');
        }
        
        if (!orderData.items || orderData.items.length === 0) {
            throw new Error('No hay items en el carrito');
        }
        
        // Validar que todos los items tengan los campos necesarios
        orderData.items.forEach((item, index) => {
            if (!item.id || !item.name || !item.price || !item.quantity) {
                throw new Error(`Item ${index + 1} tiene datos incompletos: ${JSON.stringify(item)}`);
            }
        });
        
        document.getElementById('loadingOverlay').style.display = 'flex';
        
        const customerModal = bootstrap.Modal.getInstance(document.getElementById('customerModal'));
        customerModal.hide();

        
        const response = await fetch('admin/api/online-orders.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(orderData)
        });
        
        // Intentar leer la respuesta como texto primero
        const responseText = await response.text();
        
        // Intentar parsear como JSON
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Error parseando respuesta JSON:', parseError);
            throw new Error('Respuesta del servidor no es JSON válido: ' + responseText.substring(0, 200));
        }
        
        document.getElementById('loadingOverlay').style.display = 'none';
        
        if (result.success) {
            document.getElementById('order-number-display').textContent = result.order_number;
            document.getElementById('order-success').classList.remove('d-none');
            document.getElementById('order-error').classList.add('d-none');
            
            // Preparar datos para WhatsApp
            const whatsappData = {
                orderNumber: result.order_number,
                customerName: name,
                customerPhone: formattedPhone,
                customerAddress: fullAddress,
                items: cart,
                total: cart.reduce((sum, item) => sum + (item.price * item.quantity), 0)
            };
            
            // Limpiar carrito y formulario
            clearCart();
            document.getElementById('customerForm').reset();
            
            // Limpiar elementos de la interfaz de forma segura
            const addressDetails = document.getElementById('address-details');
            const deliveryStatus = document.getElementById('delivery-status');
            const phonePreview = document.getElementById('phonePreview');
            
            if (addressDetails) addressDetails.style.display = 'none';
            if (deliveryStatus) deliveryStatus.innerHTML = '';
            if (phonePreview) phonePreview.style.display = 'none';
            
            // Limpiar Google Maps de forma segura
            try {
                if (window.addressAutocomplete && typeof window.addressAutocomplete.setComponentRestrictions === 'function') {
                    const addressInput = document.getElementById('customerAddress');
                    if (addressInput) {
                        addressInput.value = '';
                    }
                    window.getSelectedAddressDetails = () => null;
                }
            } catch (mapsError) {
                console.warn('Error al limpiar Google Maps (no crítico):', mapsError);
            }
            
            const statusModal = new bootstrap.Modal(document.getElementById('orderStatusModal'));
            statusModal.show();
            
            // Configurar botón de WhatsApp después de mostrar el modal
            setTimeout(() => {
                const whatsappBtn = document.getElementById('open-whatsapp-btn');
                if (whatsappBtn) {
                    whatsappBtn.addEventListener('click', function() {
                        sendWhatsAppNotification(window.pendingWhatsAppData);
                    });
                    
                    // Auto-click después de 1 segundo para que sea "automático"
                    setTimeout(() => {
                        whatsappBtn.click();
                    }, 1000);
                }
            }, 500);
            
            // Guardar datos para WhatsApp globalmente
            window.pendingWhatsAppData = whatsappData;
            
            trackEvent('purchase', 'ecommerce', result.order_number);
            
        } else {
            console.error('Error en la respuesta:', result);
            document.getElementById('error-message').textContent = result.message || 'Error al procesar el pedido';
            document.getElementById('order-success').classList.add('d-none');
            document.getElementById('order-error').classList.remove('d-none');
            
            const statusModal = new bootstrap.Modal(document.getElementById('orderStatusModal'));
            statusModal.show();
        }
    } catch (error) {
        console.error('Error completo:', error);
        document.getElementById('loadingOverlay').style.display = 'none';
        document.getElementById('error-message').textContent = 'Error: ' + error.message;
        document.getElementById('order-success').classList.add('d-none');
        document.getElementById('order-error').classList.remove('d-none');
        
        const statusModal = new bootstrap.Modal(document.getElementById('orderStatusModal'));
        statusModal.show();
    } finally {
        isSubmitting = false;
    }
}

        // ===== FUNCIONES DE ALMACENAMIENTO =====
        function saveCartToStorage() {
            localStorage.setItem('restaurant_cart', JSON.stringify(cart));
        }

        function loadCartFromStorage() {
            const savedCart = localStorage.getItem('restaurant_cart');
            if (savedCart) {
                try {
                    cart = JSON.parse(savedCart);
                    updateCartDisplay();
                } catch (e) {
                    console.error('Error cargando carrito guardado:', e);
                    cart = [];
                }
            }
        }

        // ===== FUNCIONES AUXILIARES =====
        function formatPrice(price) {
            return '$' + parseFloat(price).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        function trackEvent(action, category, label) {
            if (typeof gtag !== 'undefined') {
                gtag('event', action, {
                    event_category: category,
                    event_label: label
                });
            }
            console.log('Track event:', action, category, label);
        }

        function checkRestaurantStatus() {
            const currentTime = new Date();
            const currentHour = currentTime.getHours();
            const currentMinute = currentTime.getMinutes();
            const currentTimeStr = String(currentHour).padStart(2, '0') + ':' + String(currentMinute).padStart(2, '0');
            
            const openingTime = '<?php echo $opening_time; ?>';
            const kitchenClosingTime = '<?php echo $kitchen_closing_time; ?>';
            
            const isCurrentlyOpen = (currentTimeStr >= openingTime && currentTimeStr <= kitchenClosingTime);
            
            if (!isCurrentlyOpen && isOpen) {
                location.reload();
            } else if (isCurrentlyOpen && !isOpen) {
                location.reload();
            }
        }

        // ===== EVENT LISTENERS Y INICIALIZACIÓN =====
        document.addEventListener('DOMContentLoaded', function() {
            loadCartFromStorage();
            updateCartDisplay();
            
            // Filtros de categoría
            document.querySelectorAll('.category-filter').forEach(button => {
                button.addEventListener('click', function() {
                    const category = this.dataset.category;
                    
                    document.querySelectorAll('.category-filter').forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    document.querySelectorAll('.product-item').forEach(item => {
                        if (category === 'all' || item.dataset.category === category) {
                            item.style.display = 'block';
                        } else {
                            item.style.display = 'none';
                        }
                    });
                    
                    setTimeout(() => {
                        document.getElementById('products-container').scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }, 100);
                });
            });
            
            const allCategoryBtn = document.querySelector('.category-filter[data-category="all"]');
            if (allCategoryBtn) allCategoryBtn.classList.add('active');
            
            // Validación de formulario
            const customerName = document.getElementById('customerName');
            const customerPhone = document.getElementById('customerPhone');
            
            if (customerName) {
                customerName.addEventListener('input', function() {
                    this.value = this.value.replace(/[^a-zA-ZÀ-ÿ\u00f1\u00d1\s]/g, '');
                });
            }
            
            if (customerPhone) {
                customerPhone.addEventListener('input', function() {
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    if (this.value.length > 15) {
                        this.value = this.value.substring(0, 15);
                    }
                    
                    showPhonePreview(this.value);
                });
                
                customerPhone.addEventListener('blur', function() {
                    if (this.value.length > 0 && this.value.length < 8) {
                        alert('El número de teléfono debe tener al menos 8 dígitos');
                        this.focus();
                    }
                });
                
                customerPhone.addEventListener('focus', function() {
                    if (this.value.length === 0) {
                        this.placeholder = '3482549555 (sin 0 ni 15)';
                    }
                });
                
                customerPhone.addEventListener('blur', function() {
                    if (this.value.length === 0) {
                        this.placeholder = 'Ej: 3482549555';
                    }
                });
            }
            
            // Prevenir zoom en mobile
            if ('ontouchstart' in window) {
                let lastTouchEnd = 0;
                document.addEventListener('touchend', function (event) {
                    const now = (new Date()).getTime();
                    if (now - lastTouchEnd <= 300) {
                        event.preventDefault();
                    }
                    lastTouchEnd = now;
                }, false);
            }
            
            // Verificar estado del restaurante cada 5 minutos
            setInterval(checkRestaurantStatus, 5 * 60 * 1000);
        });

        // Función de testing
        function testPhoneFormatting() {
            const testNumbers = [
                '3482549555', '15549555', '543482549555', '549555', '93482549555',
                '1134567890', '35134567890'
            ];
            
            console.log('=== Probando formateo de números ===');
            testNumbers.forEach(num => {
                const formatted = formatPhoneNumber(num);
                const isValid = isValidArgentinePhone(formatted);
                console.log(`${num} -> ${formatted} (${isValid ? 'Válido' : 'Inválido'})`);
            });
        }
        
        function sendWhatsAppNotification(orderData) {
    try {
        // Número de WhatsApp del restaurante
        const restaurantWhatsApp = '<?php echo preg_replace("/[^0-9]/", "", $whatsapp_number ?? ""); ?>';
        
        console.log('Número de WhatsApp del restaurante:', restaurantWhatsApp);
        
        if (!restaurantWhatsApp || restaurantWhatsApp.length < 10) {
            alert('Error: Número de WhatsApp del restaurante no configurado correctamente.');
            return;
        }
        
        // Construir mensaje
        let message = `🍽️ *NUEVO PEDIDO ONLINE*\n\n`;
        message += `📋 *Pedido:* ${orderData.orderNumber}\n`;
        message += `👤 *Cliente:* ${orderData.customerName}\n`;
        message += `📱 *Teléfono:* ${orderData.customerPhone}\n`;
        message += `📍 *Dirección:* ${orderData.customerAddress}\n\n`;
        
        message += `🛍️ *PRODUCTOS:*\n`;
        orderData.items.forEach(item => {
            message += `• ${item.name} x${item.quantity} - ${formatPrice(item.price * item.quantity)}\n`;
        });
        
        message += `\n💰 *TOTAL: ${formatPrice(orderData.total)}*\n\n`;
        message += `⏰ Pedido realizado: ${new Date().toLocaleString('es-AR')}\n\n`;
        message += `✅ Pedido confirmado desde la página web`;
        
        // URL de WhatsApp
        const whatsappUrl = `https://wa.me/${restaurantWhatsApp}?text=${encodeURIComponent(message)}`;
        
        // Detectar dispositivo móvil
        const isMobile = /Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        
        if (isMobile) {
            // En móviles, intentar abrir la app primero
            const whatsappApp = `whatsapp://send?phone=${restaurantWhatsApp}&text=${encodeURIComponent(message)}`;
            window.location.href = whatsappApp;
            
            // Fallback a web después de 2 segundos
            setTimeout(() => {
                window.open(whatsappUrl, '_blank');
            }, 2000);
        } else {
            // En desktop, abrir WhatsApp Web
            window.open(whatsappUrl, '_blank');
        }
        
        // Cambiar texto del botón
        const btn = document.getElementById('open-whatsapp-btn');
        if (btn) {
            btn.innerHTML = '<i class="fas fa-check me-2"></i>WhatsApp Abierto';
            btn.disabled = true;
            btn.classList.remove('pulse');
        }
        
        console.log('WhatsApp abierto para pedido:', orderData.orderNumber);
        trackEvent('whatsapp_opened', 'communication', orderData.orderNumber);
        
    } catch (error) {
        console.error('Error enviando WhatsApp:', error);
        alert('Error al abrir WhatsApp: ' + error.message);
    }
}


// 3. AGREGAR FUNCIÓN AUXILIAR PARA OBTENER NÚMERO DE WHATSAPP:

function getRestaurantWhatsApp() {
    // Esta función puede ser útil para validar el número
    const whatsappNumber = '<?php echo $whatsapp_number ?? ""; ?>';
    const cleanNumber = whatsappNumber.replace(/[^0-9]/g, '');
    
    // Formatear número argentino para WhatsApp
    if (cleanNumber.startsWith('54')) {
        return cleanNumber;
    } else if (cleanNumber.startsWith('9') && cleanNumber.length > 10) {
        return '54' + cleanNumber;
    } else if (cleanNumber.length === 10) {
        return '549' + cleanNumber;
    } else if (cleanNumber.length >= 8) {
        return '5493482' + cleanNumber; // Código de área por defecto
    }
    
    return cleanNumber;
}
    </script>
    
    
</body>
</html>