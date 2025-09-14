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
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $restaurant_name; ?> - Menú Online</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
        }
        
        .product-card {
            border: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
            margin-bottom: 20px;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
        }
        
        .product-image {
            height: 400px;
            object-fit: cover;
            border-radius: 8px 8px 0 0;
        }
        
        .category-nav {
            background: #f8f9fa;
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }
        
        .cart-floating {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1050;
        }
        
        .cart-item {
            border-bottom: 1px solid #eee;
            padding: 10px 0;
        }
        
        .price {
            color: #28a745;
            font-weight: bold;
            font-size: 1.2em;
        }
        
        .btn-add-to-cart {
            background: #ff6b6b;
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }
        
        .btn-add-to-cart:hover {
            background: #ff5252;
            transform: scale(1.05);
        }

        .btn-add-to-cart:disabled {
            background: #6c757d;
            transform: none;
            cursor: not-allowed;
        }

        .closed-banner {
            background: linear-gradient(45deg, #dc3545, #c82333);
            color: white;
            padding: 15px 0;
            text-align: center;
            font-weight: bold;
        }

        .status-indicator {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-open {
            background: #d4edda;
            color: #155724;
        }

        .status-closed {
            background: #f8d7da;
            color: #721c24;
        }

        .order-status-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .success-icon {
            color: #28a745;
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        .loading-spinner {
            color: white;
            font-size: 2rem;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        /* Google Maps autocomplete styling */
        .pac-container {
            z-index: 10000 !important;
            border-radius: 8px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }

        .pac-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .pac-item:hover {
            background-color: #f8f9fa;
        }

        .pac-item-selected {
            background-color: #e3f2fd;
        }

        #phonePreview {
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <div class="mt-2">Procesando pedido...</div>
        </div>
    </div>

    <!-- Header -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
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
    <div class="hero-section">
        <div class="container text-center">
            <h1 class="display-4 mb-4">¡Bienvenido a <?php echo $restaurant_name; ?>!</h1>
            <p class="lead">Descubre nuestro delicioso menú y haz tu pedido online</p>
            <p class="mb-4">
                <i class="fas fa-phone me-2"></i>
                <?php echo $settings['restaurant_phone'] ?? 'Teléfono no disponible'; ?>
            </p>
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
                        <button class="btn btn-outline-primary me-2 mb-2 category-filter" data-category="all">
                            <i class="fas fa-th-large me-1"></i> Todos
                        </button>
                        <?php foreach ($categories as $category): ?>
                            <button class="btn btn-outline-primary me-2 mb-2 category-filter" data-category="<?php echo $category['id']; ?>">
                                <i class="fas fa-tag me-1"></i> <?php echo htmlspecialchars($category['name']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Products Section -->
    <div class="container my-5">
        <div class="row" id="products-container">
            <?php foreach ($products as $product): ?>
                <div class="col-md-6 col-lg-4 product-item" data-category="<?php echo $product['category_id']; ?>">
                    <div class="card product-card">
                        <?php if ($product['image']): ?>
                            <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                 class="card-img-top product-image" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="card-img-top product-image bg-light d-flex align-items-center justify-content-center">
                                <i class="fas fa-image fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                            <p class="card-text text-muted"><?php echo htmlspecialchars($product['description'] ?? ''); ?></p>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="price"><?php echo formatPrice($product['price']); ?></span>
                                
                                <?php if ($product['is_available'] && $is_open && $online_orders_enabled === '1'): ?>
                                    <button class="btn btn-add-to-cart" 
                                            onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>', <?php echo $product['price']; ?>)">
                                        <i class="fas fa-plus me-1"></i> Agregar
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
            <button class="btn btn-success rounded-circle p-3 position-relative pulse" data-bs-toggle="modal" data-bs-target="#cartModal">
                <i class="fas fa-shopping-cart fa-lg"></i>
                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="cart-count">
                    0
                </span>
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
                        <h4 class="text-success mb-3">¡Pedido Enviado!</h4>
                        <p class="mb-3">Su pedido <strong id="order-number-display"></strong> ha sido enviado correctamente.</p>
                        <p class="text-muted mb-4">Recibirá una confirmación por WhatsApp en los próximos minutos con el tiempo estimado de entrega.</p>
                        <div class="alert alert-info">
                            <i class="fas fa-clock me-2"></i>
                            Tiempo estimado: <strong>30-45 minutos</strong>
                        </div>
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
    <footer class="bg-dark text-white py-4 mt-5">
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
    </div>

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
                    // Configurar el autocomplete con restricciones para Argentina
                    const autocomplete = new google.maps.places.Autocomplete(addressInput, {
                        types: ['address'],
                        componentRestrictions: {
                            country: ['ar'] // Restringir a Argentina
                        },
                        fields: ['address_components', 'formatted_address', 'geometry']
                    });

                    // Configurar el área de búsqueda (Santa Fe, Argentina)
                    const avellanedaCenter = new google.maps.LatLng(-29.1167, -59.6500);
                    const circle = new google.maps.Circle({
                        center: santaFeCenter,
                        radius: 50000 // 50km de radio
                    });
                    autocomplete.setBounds(circle.getBounds());

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
                } catch (error) {
                    console.error('Error inicializando Google Maps:', error);
                }
            }

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
                const restaurantLocation = { lat: -29.1167, lng: -59.6500 }; // Avellaneda, Santa Fe // Ajustar a tu ubicación
                const maxDeliveryDistance = 25; // km

                const distance = calculateDistance(lat, lng, restaurantLocation.lat, restaurantLocation.lng);
                
                const deliveryStatus = document.getElementById('delivery-status');
                if (!deliveryStatus) return;

                if (distance <= maxDeliveryDistance) {
                    deliveryStatus.innerHTML = `
                        <div class="alert alert-success py-2">
                            <i class="fas fa-check-circle me-1"></i>
                            <strong>Zona de delivery válida</strong> (${distance.toFixed(1)} km)
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
            
            if (cleanPhone.startsWith('54')) {
                return cleanPhone;
            }
            
            if (cleanPhone.startsWith('9') && cleanPhone.length > 10) {
                cleanPhone = cleanPhone.substring(1);
            }
            
            if (cleanPhone.startsWith('15')) {
                cleanPhone = cleanPhone.substring(2);
            }
            
            if (cleanPhone.length === 10) {
                cleanPhone = '54' + cleanPhone;
            }
            
            if (cleanPhone.length === 8 || cleanPhone.length === 9) {
                const defaultAreaCode = '3482';
                cleanPhone = '54' + defaultAreaCode + cleanPhone;
            }
            
            return cleanPhone;
        }

        function isValidArgentinePhone(phone) {
            const cleanPhone = phone.replace(/[^0-9]/g, '');
            
            const areaCodes = [
                '11', '221', '223', '261', '341', '351', '381', '3482', '3476', '342',
                '376', '388', '299', '2966', '264', '280', '383', '385', '387', '2920', '2944'
            ];
            
            if (cleanPhone.startsWith('54')) {
                const phoneWithoutCountry = cleanPhone.substring(2);
                
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
                const displayPhone = formattedPhone.replace(/^54/, '+54 ');
                
                let prettyPhone = displayPhone;
                if (prettyPhone.length >= 13) {
                    prettyPhone = prettyPhone.substring(0, 3) + ' ' + 
                                 prettyPhone.substring(3, 7) + ' ' + 
                                 prettyPhone.substring(7);
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
                const restaurantLocation = { lat: -29.1167, lng: -59.6500 }; // Avellaneda, Santa Fe
                const maxDeliveryDistance = 25;
                
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
            if (formattedPhone.length < 12 || formattedPhone.length > 15) {
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
                
                console.log('Enviando pedido:', orderData);
                
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
                
                const result = await response.json();
                
                document.getElementById('loadingOverlay').style.display = 'none';
                
                if (result.success) {
                    document.getElementById('order-number-display').textContent = result.order_number;
                    document.getElementById('order-success').classList.remove('d-none');
                    document.getElementById('order-error').classList.add('d-none');
                    
                    clearCart();
                    document.getElementById('customerForm').reset();
                    
                    const addressDetails = document.getElementById('address-details');
                    const deliveryStatus = document.getElementById('delivery-status');
                    const phonePreview = document.getElementById('phonePreview');
                    
                    if (addressDetails) addressDetails.style.display = 'none';
                    if (deliveryStatus) deliveryStatus.innerHTML = '';
                    if (phonePreview) phonePreview.style.display = 'none';
                    
                    if (window.addressAutocomplete) {
                        window.addressAutocomplete.set('place', null);
                    }
                    
                    const statusModal = new bootstrap.Modal(document.getElementById('orderStatusModal'));
                    statusModal.show();
                    
                    trackEvent('purchase', 'ecommerce', result.order_number);
                    
                } else {
                    document.getElementById('error-message').textContent = result.message || 'Error al procesar el pedido';
                    document.getElementById('order-success').classList.add('d-none');
                    document.getElementById('order-error').classList.remove('d-none');
                    
                    const statusModal = new bootstrap.Modal(document.getElementById('orderStatusModal'));
                    statusModal.show();
                }
            } catch (error) {
                document.getElementById('loadingOverlay').style.display = 'none';
                document.getElementById('error-message').textContent = 'Error de conexión. Verifique su internet y vuelva a intentar.';
                document.getElementById('order-success').classList.add('d-none');
                document.getElementById('order-error').classList.remove('d-none');
                
                const statusModal = new bootstrap.Modal(document.getElementById('orderStatusModal'));
                statusModal.show();
                
                console.error('Error al enviar pedido:', error);
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
    </script>
</body>
</html>