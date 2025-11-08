<?php
// admin/order-create.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Order.php';
require_once '../models/Table.php';
require_once '../models/Product.php';
require_once '../models/Category.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('orders');

$orderModel = new Order();
$tableModel = new Table();
$productModel = new Product();
$categoryModel = new Category();

// Initialize session cart if not exists
if (!isset($_SESSION['temp_order'])) {
    $_SESSION['temp_order'] = [
        'items' => [],
        'order_data' => []
    ];
}

// Handle form submissions
$message = '';
$error = '';
$table_id = isset($_GET['table_id']) ? intval($_GET['table_id']) : null;
$table = null;

// Get table information if table_id is provided
if ($table_id) {
    $table = $tableModel->getById($table_id);
    if (!$table) {
        $error = 'Mesa no encontrada';
        $table_id = null;
    } else {
        // Check if table has active order
        $existing_order = $orderModel->getActiveOrderByTable($table_id);
        if ($existing_order) {
            // Redirect to existing order
            header("Location: order-details.php?id=" . $existing_order['id']);
            exit;
        }
    }
}

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'save_order_info':
            // Save order information to session
            $_SESSION['temp_order']['order_data'] = [
                'type' => sanitize($_POST['type']),
                'table_id' => $table_id,
                'customer_name' => sanitize($_POST['customer_name']),
                'customer_phone' => sanitize($_POST['customer_phone']),
                'customer_address' => sanitize($_POST['customer_address']),
                'customer_notes' => sanitize($_POST['customer_notes']),
                'notes' => sanitize($_POST['notes'])
            ];
            $message = 'Información guardada. Ahora agrega productos a la orden.';
            break;
            
        case 'add_item':
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            $notes = sanitize($_POST['item_notes']);
            
            $product = $productModel->getById($product_id);
            if ($product && $quantity > 0) {
                $item_id = uniqid(); // Generate unique ID for cart item
                
                $_SESSION['temp_order']['items'][$item_id] = [
                    'product_id' => $product_id,
                    'product_name' => $product['name'],
                    'quantity' => $quantity,
                    'unit_price' => $product['price'],
                    'subtotal' => $quantity * $product['price'],
                    'notes' => $notes
                ];
                
                $message = 'Producto agregado al carrito';
            } else {
                $error = 'Error al agregar el producto';
            }
            break;
            
        case 'update_quantity':
            $item_id = sanitize($_POST['item_id']);
            $quantity = intval($_POST['quantity']);
            
            if (isset($_SESSION['temp_order']['items'][$item_id]) && $quantity > 0) {
                $_SESSION['temp_order']['items'][$item_id]['quantity'] = $quantity;
                $_SESSION['temp_order']['items'][$item_id]['subtotal'] = 
                    $quantity * $_SESSION['temp_order']['items'][$item_id]['unit_price'];
                $message = 'Cantidad actualizada';
            } else {
                $error = 'Error al actualizar la cantidad';
            }
            break;
            
        case 'remove_item':
            $item_id = sanitize($_POST['item_id']);
            
            if (isset($_SESSION['temp_order']['items'][$item_id])) {
                unset($_SESSION['temp_order']['items'][$item_id]);
                $message = 'Producto eliminado';
            } else {
                $error = 'Error al eliminar el producto';
            }
            break;
            
        case 'finalize_order':
            // Validate that we have order data and items
            if (empty($_SESSION['temp_order']['order_data'])) {
                $error = 'Debes completar la información de la orden primero';
                break;
            }
            
            if (empty($_SESSION['temp_order']['items'])) {
                $error = 'Debes agregar al menos un producto a la orden';
                break;
            }
            
            // Calculate totals
            $subtotal = 0;
            foreach ($_SESSION['temp_order']['items'] as $item) {
                $subtotal += $item['subtotal'];
            }
            
            // Get settings for tax calculation
            $settings = getSettings();
            $tax_rate = floatval($settings['tax_rate'] ?? 0) / 100;
            $delivery_fee_amount = floatval($settings['delivery_fee'] ?? 0);
            
            $tax = $subtotal * $tax_rate;
            $delivery_fee = ($_SESSION['temp_order']['order_data']['type'] === 'delivery') ? $delivery_fee_amount : 0;
            $total = $subtotal + $tax + $delivery_fee;
            
            // Create order in database
            $order_data = $_SESSION['temp_order']['order_data'];
            $order_data['subtotal'] = $subtotal;
            $order_data['tax'] = $tax;
            $order_data['delivery_fee'] = $delivery_fee;
            $order_data['total'] = $total;
            $order_data['waiter_id'] = $_SESSION['user_id'];
            $order_data['created_by'] = $_SESSION['user_id'];
            $order_data['status'] = 'confirmed';
            
            $order_id = $orderModel->create($order_data);
            
            if ($order_id) {
                // Add items to order
                foreach ($_SESSION['temp_order']['items'] as $item) {
                    $orderModel->addItem(
                        $order_id,
                        $item['product_id'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['notes']
                    );
                }
                
                // If it's a dine-in order, mark table as occupied
                if ($order_data['type'] === 'dine_in' && $order_data['table_id']) {
                    $tableModel->occupyTable($order_data['table_id']);
                }
                
                // Clear session
                unset($_SESSION['temp_order']);
                
                // Redirect to order details
                header("Location: order-details.php?id=$order_id");
                exit;
            } else {
                $error = 'Error al crear la orden';
            }
            break;
            
        case 'cancel_order':
            // Clear session
            unset($_SESSION['temp_order']);
            header("Location: " . ($table_id ? "tables.php" : "orders.php"));
            exit;
            break;
    }
}

// Get products and categories
$categories = $categoryModel->getAll();
$products = $productModel->getAllActive();

// Group products by category
$products_by_category = [];
foreach ($products as $product) {
    $products_by_category[$product['category_id']][] = $product;
}

// Calculate cart totals
$cart_subtotal = 0;
$cart_items = $_SESSION['temp_order']['items'] ?? [];
foreach ($cart_items as $item) {
    $cart_subtotal += $item['subtotal'];
}

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$tax_rate = floatval($settings['tax_rate'] ?? 0) / 100;
$delivery_fee_amount = floatval($settings['delivery_fee'] ?? 0);

$cart_tax = $cart_subtotal * $tax_rate;
$cart_delivery = (isset($_SESSION['temp_order']['order_data']['type']) && 
                  $_SESSION['temp_order']['order_data']['type'] === 'delivery') ? $delivery_fee_amount : 0;
$cart_total = $cart_subtotal + $cart_tax + $cart_delivery;

$has_order_data = !empty($_SESSION['temp_order']['order_data']);

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
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Orden - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    
    <!-- Tema dinámico -->
    <?php if (file_exists('../assets/css/generate-theme.php')): ?>
        <link rel="stylesheet" href="../assets/css/generate-theme.php?v=<?php echo time(); ?>">
    <?php endif; ?>
    
    <style>
    :root {
        --primary-color: <?php echo $current_theme['primary_color'] ?? '#667eea'; ?>;
        --secondary-color: <?php echo $current_theme['secondary_color'] ?? '#764ba2'; ?>;
        --accent-color: <?php echo $current_theme['accent_color'] ?? '#ff6b6b'; ?>;
        --success-color: <?php echo $current_theme['success_color'] ?? '#28a745'; ?>;
        --warning-color: <?php echo $current_theme['warning_color'] ?? '#ffc107'; ?>;
        --danger-color: <?php echo $current_theme['danger_color'] ?? '#dc3545'; ?>;
        --info-color: <?php echo $current_theme['info_color'] ?? '#17a2b8'; ?>;
        
        --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        --sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
        --sidebar-mobile-width: 100%;
        --border-radius-base: 0.375rem;
        --border-radius-large: 0.75rem;
        --transition-base: all 0.3s ease;
        --shadow-base: 0 2px 4px rgba(0,0,0,0.1);
        --shadow-large: 0 4px 12px rgba(0,0,0,0.15);
        --text-white: #ffffff;
    }

    body {
        background: #f8f9fa;
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

    /* Sidebar */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--sidebar-width);
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

    .sidebar .nav-link .badge {
        margin-left: auto;
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

    /* Main content */
    .main-content {
        margin-left: var(--sidebar-width);
        padding: 2rem;
        min-height: 100vh;
        transition: margin-left var(--transition-base);
        background: #f8f9fa !important;
        color: #212529 !important;
    }

    /* Page header */
    .page-header {
        background: #ffffff !important;
        color: #212529 !important;
        border-radius: var(--border-radius-large);
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-base);
    }

    /* Cards */
    .card {
        border: none;
        border-radius: var(--border-radius-large);
        box-shadow: var(--shadow-base);
        background: #ffffff !important;
        color: #212529 !important;
        margin-bottom: 1.5rem;
    }

    .card-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        border-radius: var(--border-radius-large) var(--border-radius-large) 0 0 !important;
        background: #f8f9fa !important;
        color: #212529 !important;
        padding: 1rem 1.25rem;
        font-weight: 600;
    }

    /* DataTables customization */
    .dataTables_wrapper {
        padding: 0;
    }

    .dataTables_filter input {
        border-radius: var(--border-radius-base);
        border-color: #dee2e6;
    }

    .dataTables_filter input:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    table.dataTable thead th {
        background: #f8f9fa !important;
        border-bottom: 2px solid #dee2e6 !important;
        font-weight: 600;
        color: #212529 !important;
    }

    table.dataTable tbody tr {
        transition: var(--transition-base);
    }

    table.dataTable tbody tr:hover {
        background-color: #f8f9fa !important;
    }

    .product-image-small {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 8px;
    }

    .product-image-placeholder {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f8f9fa;
        border-radius: 8px;
        border: 2px dashed #dee2e6;
    }

    .product-price {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--success-color);
    }

    .category-pill {
        display: inline-block;
        padding: 0.5rem 1.25rem;
        margin: 0.25rem;
        border-radius: 2rem;
        background: #f8f9fa;
        color: #495057;
        text-decoration: none;
        transition: var(--transition-base);
        border: 2px solid #dee2e6;
    }

    .category-pill:hover,
    .category-pill.active {
        background: var(--primary-color);
        color: var(--text-white) !important;
        border-color: var(--primary-color);
        transform: translateY(-2px);
    }

    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    /* Cart summary styling */
    .cart-summary {
        position: sticky;
        top: 2rem;
    }

    .cart-item {
        border-bottom: 1px solid #dee2e6;
        padding: 0.75rem 0;
    }

    .cart-item:last-child {
        border-bottom: none;
    }

    .btn-xs {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }

    /* Mobile Responsive */
    @media (max-width: 991.98px) {
        .sidebar {
            transform: translateX(-100%);
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-close {
            display: flex;
        }

        .mobile-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .main-content {
            margin-left: 0;
            padding: 0.5rem;
            padding-top: 4.5rem;
        }

        .page-header {
            padding: 0.75rem;
        }

        .page-header h2 {
            font-size: 1.25rem;
        }

        .card-body {
            padding: 0.75rem;
        }

        .product-price {
            font-size: 1rem;
        }

        .category-pill {
            padding: 0.4rem 1rem;
            font-size: 0.875rem;
        }

        .cart-summary {
            position: relative;
            top: 0;
        }
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
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div class="mb-2 mb-md-0">
                    <h2 class="mb-0">
                        <i class="fas fa-plus-circle me-2"></i>Nueva Orden
                    </h2>
                    <?php if ($table): ?>
                        <p class="text-muted mb-0">
                            <i class="fas fa-chair me-1"></i>Mesa <?php echo $table['number']; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Seguro que deseas cancelar? Se perderán todos los datos.');">
                        <input type="hidden" name="action" value="cancel_order">
                        <button type="submit" class="btn btn-secondary me-2">
                            <i class="fas fa-times me-1"></i>
                            Cancelar
                        </button>
                    </form>
                    <a href="<?php echo $table ? 'tables.php' : 'orders.php'; ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Volver
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
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

        <!-- Order Information Form -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-file-alt me-2"></i>
                Información de la Orden
                <?php if ($has_order_data): ?>
                    <span class="badge bg-success ms-2">
                        <i class="fas fa-check me-1"></i>Guardado
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="action" value="save_order_info">
                    
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Tipo de Orden *</label>
                            <select name="type" class="form-select" required>
                                <option value="dine_in" <?php echo ($table || (isset($_SESSION['temp_order']['order_data']['type']) && $_SESSION['temp_order']['order_data']['type'] === 'dine_in')) ? 'selected' : ''; ?>>Consumo en Mesa</option>
                                <option value="takeout" <?php echo (isset($_SESSION['temp_order']['order_data']['type']) && $_SESSION['temp_order']['order_data']['type'] === 'takeout') ? 'selected' : ''; ?>>Para Llevar</option>
                                <option value="delivery" <?php echo (isset($_SESSION['temp_order']['order_data']['type']) && $_SESSION['temp_order']['order_data']['type'] === 'delivery') ? 'selected' : ''; ?>>Delivery</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Nombre del Cliente</label>
                            <input type="text" name="customer_name" class="form-control" 
                                   value="<?php echo htmlspecialchars($_SESSION['temp_order']['order_data']['customer_name'] ?? ''); ?>"
                                   placeholder="Opcional">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" name="customer_phone" class="form-control" 
                                   value="<?php echo htmlspecialchars($_SESSION['temp_order']['order_data']['customer_phone'] ?? ''); ?>"
                                   placeholder="Opcional">
                        </div>
                        
                        <div class="col-md-8">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="customer_address" class="form-control" 
                                   value="<?php echo htmlspecialchars($_SESSION['temp_order']['order_data']['customer_address'] ?? ''); ?>"
                                   placeholder="Opcional - para delivery">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label">Notas del Cliente</label>
                            <input type="text" name="customer_notes" class="form-control" 
                                   value="<?php echo htmlspecialchars($_SESSION['temp_order']['order_data']['customer_notes'] ?? ''); ?>"
                                   placeholder="Opcional">
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Notas Internas</label>
                            <textarea name="notes" class="form-control" rows="2" placeholder="Opcional"><?php echo htmlspecialchars($_SESSION['temp_order']['order_data']['notes'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>
                                <?php echo $has_order_data ? 'Actualizar Información' : 'Guardar y Agregar Productos'; ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products Section -->
        <?php if ($has_order_data): ?>
            <div class="row">
                <div class="col-lg-8">
                    <!-- Category Filter -->
                    <?php if (!empty($categories)): ?>
                        <div class="card mb-4">
                            <div class="card-body">
                                <h6 class="mb-3">
                                    <i class="fas fa-filter me-2"></i>
                                    Filtrar por Categoría
                                </h6>
                                <div class="d-flex flex-wrap">
                                    <a href="#" class="category-pill active" data-category="all">
                                        <i class="fas fa-th me-1"></i>Todas
                                    </a>
                                    <?php foreach ($categories as $category): ?>
                                        <a href="#" class="category-pill" data-category="<?php echo $category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Products Table -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-utensils me-2"></i>
                            Productos Disponibles
                        </div>
                        <div class="card-body">
                            <?php if (empty($products)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-box-open"></i>
                                    <p>No hay productos disponibles</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table id="productsTable" class="table table-hover" style="width:100%">
                                        <thead>
                                            <tr>
                                                <th>Imagen</th>
                                                <th>Producto</th>
                                                <th>Categoría</th>
                                                <th>Precio</th>
                                                <th>Estado</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($products as $product): ?>
                                                <tr class="<?php echo !$product['is_available'] ? 'table-secondary' : ''; ?>">
                                                    <td>
                                                        <?php if ($product['image']): ?>
                                                            <img src="../<?php echo htmlspecialchars($product['image']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($product['name']); ?>" 
                                                                 class="product-image-small">
                                                        <?php else: ?>
                                                            <div class="product-image-placeholder">
                                                                <i class="fas fa-utensils text-muted"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                                        <?php if ($product['description']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?><?php echo strlen($product['description']) > 50 ? '...' : ''; ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                                    <td>
                                                        <span class="product-price">
                                                            <?php echo formatPrice($product['price']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php if ($product['is_available']): ?>
                                                            <span class="badge bg-success">Disponible</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">No Disponible</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($product['is_available']): ?>
                                                            <button class="btn btn-sm btn-primary" 
                                                                    onclick="addProductToCart(<?php echo $product['id']; ?>)">
                                                                <i class="fas fa-plus me-1"></i>
                                                                Agregar
                                                            </button>
                                                        <?php else: ?>
                                                            <button class="btn btn-sm btn-secondary" disabled>
                                                                No Disponible
                                                            </button>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Cart Summary -->
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <div class="card">
                            <div class="card-header">
                                <i class="fas fa-shopping-cart me-2"></i>
                                Resumen del Pedido
                            </div>
                            <div class="card-body">
                                <?php if (empty($cart_items)): ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-cart-plus fa-3x mb-3 opacity-50"></i>
                                        <p>No hay productos en el carrito</p>
                                    </div>
                                <?php else: ?>
                                    <div class="mb-3">
                                        <?php foreach ($cart_items as $item_id => $item): ?>
                                            <div class="cart-item">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div class="flex-grow-1">
                                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                        <?php if ($item['notes']): ?>
                                                            <br><small class="text-muted"><?php echo htmlspecialchars($item['notes']); ?></small>
                                                        <?php endif; ?>
                                                    </div>
                                                    <form method="POST" style="display:inline;">
                                                        <input type="hidden" name="action" value="remove_item">
                                                        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                                                        <button type="submit" class="btn btn-xs btn-outline-danger ms-2" title="Eliminar">
                                                            <i class="fas fa-times"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <form method="POST" class="d-flex align-items-center">
                                                        <input type="hidden" name="action" value="update_quantity">
                                                        <input type="hidden" name="item_id" value="<?php echo $item_id; ?>">
                                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] - 1; ?>" 
                                                                class="btn btn-xs btn-outline-secondary" 
                                                                <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                        <span class="mx-2"><strong><?php echo $item['quantity']; ?></strong></span>
                                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" 
                                                                class="btn btn-xs btn-outline-secondary">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </form>
                                                    <strong><?php echo formatPrice($item['subtotal']); ?></strong>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($cart_items)): ?>
                                <div class="card-footer bg-light">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Subtotal:</span>
                                        <strong><?php echo formatPrice($cart_subtotal); ?></strong>
                                    </div>
                                    <?php if ($cart_tax > 0): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Impuestos (<?php echo number_format($tax_rate * 100, 1); ?>%):</span>
                                            <strong><?php echo formatPrice($cart_tax); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($cart_delivery > 0): ?>
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Envío:</span>
                                            <strong><?php echo formatPrice($cart_delivery); ?></strong>
                                        </div>
                                    <?php endif; ?>
                                    <hr>
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <strong>Total:</strong>
                                        <strong class="h4 mb-0 text-success"><?php echo formatPrice($cart_total); ?></strong>
                                    </div>
                                    <form method="POST">
                                        <input type="hidden" name="action" value="finalize_order">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-check me-1"></i>
                                            Finalizar Orden
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                Completa la información de la orden para poder agregar productos.
            </div>
        <?php endif; ?>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Agregar Producto</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_item">
                        <input type="hidden" name="product_id" id="modal_product_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" name="quantity" class="form-control" min="1" value="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notas</label>
                            <textarea name="item_notes" class="form-control" rows="2" placeholder="Ej: Sin cebolla, extra queso..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>
                            Agregar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- jQuery (required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <!-- Bootstrap Bundle -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    
    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
            initializeCategoryFilter();
            initializeDataTable();
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

        // Initialize DataTable
        function initializeDataTable() {
            if (document.getElementById('productsTable')) {
                $('#productsTable').DataTable({
                    responsive: true,
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                    },
                    pageLength: 10,
                    order: [[1, 'asc']], // Ordenar por nombre de producto
                    columnDefs: [
                        { orderable: false, targets: [0, 5] }, // Deshabilitar ordenamiento en imagen y acciones
                        { responsivePriority: 1, targets: 1 }, // Prioridad en responsive para nombre
                        { responsivePriority: 2, targets: 5 }  // Prioridad en responsive para acciones
                    ],
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>rtip'
                });
            }
        }

        // Category filter
        function initializeCategoryFilter() {
            const categoryPills = document.querySelectorAll('.category-pill');

            categoryPills.forEach(pill => {
                pill.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Update active state
                    categoryPills.forEach(p => p.classList.remove('active'));
                    this.classList.add('active');
                    
                    const category = this.dataset.category;
                    
                    // Filter DataTable
                    const table = $('#productsTable').DataTable();
                    
                    if (category === 'all') {
                        table.column(2).search('').draw();
                    } else {
                        // Get category name from the pill text
                        const categoryName = this.textContent.trim();
                        table.column(2).search(categoryName).draw();
                    }
                });
            });
        }

        // Add product to cart
        function addProductToCart(productId) {
            document.getElementById('modal_product_id').value = productId;
            const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
            modal.show();
        }
    </script>
</body>
</html>