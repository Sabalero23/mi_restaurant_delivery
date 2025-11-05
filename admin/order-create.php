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

// Handle form submissions
$message = '';
$error = '';
$current_order = null;
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
        $current_order = $orderModel->getActiveOrderByTable($table_id);
    }
}

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create_order':
            $order_data = [
                'type' => sanitize($_POST['type']),
                'table_id' => $table_id,
                'customer_name' => sanitize($_POST['customer_name']),
                'customer_phone' => sanitize($_POST['customer_phone']),
                'customer_address' => sanitize($_POST['customer_address']),
                'customer_notes' => sanitize($_POST['customer_notes']),
                'waiter_id' => $_SESSION['user_id'],
                'notes' => sanitize($_POST['notes']),
                'subtotal' => 0,
                'total' => 0
            ];
            
            $order_id = $orderModel->create($order_data);
            if ($order_id) {
                // If it's a dine-in order, mark table as occupied
                if ($_POST['type'] === 'dine_in' && $table_id) {
                    $tableModel->occupyTable($table_id);
                }
                
                // Redirect to add items
                header("Location: order-create.php?order_id=$order_id" . ($table_id ? "&table_id=$table_id" : ""));
                exit;
            } else {
                $error = 'Error al crear la orden';
            }
            break;
            
        case 'add_item':
            $order_id = intval($_POST['order_id']);
            $product_id = intval($_POST['product_id']);
            $quantity = intval($_POST['quantity']);
            $notes = sanitize($_POST['item_notes']);
            
            if ($orderModel->addItem($order_id, $product_id, $quantity, $notes)) {
                $orderModel->updateTotal($order_id);
                $message = 'Producto agregado exitosamente';
                
                // Reload current order
                $current_order = $orderModel->getById($order_id);
            } else {
                $error = 'Error al agregar el producto';
            }
            break;
            
        case 'update_quantity':
            $item_id = intval($_POST['item_id']);
            $quantity = intval($_POST['quantity']);
            
            if ($orderModel->updateItemQuantity($item_id, $quantity)) {
                $order_id = intval($_POST['order_id']);
                $orderModel->updateTotal($order_id);
                $message = 'Cantidad actualizada';
                
                // Reload current order
                $current_order = $orderModel->getById($order_id);
            } else {
                $error = 'Error al actualizar la cantidad';
            }
            break;
            
        case 'remove_item':
            $item_id = intval($_POST['item_id']);
            
            if ($orderModel->removeItem($item_id)) {
                $order_id = intval($_POST['order_id']);
                $orderModel->updateTotal($order_id);
                $message = 'Producto eliminado';
                
                // Reload current order
                $current_order = $orderModel->getById($order_id);
            } else {
                $error = 'Error al eliminar el producto';
            }
            break;
            
        case 'finalize_order':
            $order_id = intval($_POST['order_id']);
            
            if ($orderModel->updateStatus($order_id, 'confirmed')) {
                $message = 'Orden finalizada y confirmada';
                header("Location: order-details.php?id=$order_id");
                exit;
            } else {
                $error = 'Error al finalizar la orden';
            }
            break;
    }
}

// Get order if order_id is in URL
if (isset($_GET['order_id'])) {
    $current_order = $orderModel->getById(intval($_GET['order_id']));
    if ($current_order && !$table_id && $current_order['table_id']) {
        $table_id = $current_order['table_id'];
        $table = $tableModel->getById($table_id);
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

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

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
    <title><?php echo $current_order ? 'Editar Orden' : 'Nueva Orden'; ?> - <?php echo $restaurant_name; ?></title>
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
    /* Variables CSS para el tema */
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
        background: #f8f9fa;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Product price */
    .product-price {
        font-size: 1.1rem;
        font-weight: bold;
        color: var(--success-color);
    }

    /* Order summary */
    .order-summary {
        background: #ffffff !important;
        border-radius: var(--border-radius-large);
        box-shadow: var(--shadow-base);
        position: sticky;
        top: 2rem;
    }

    .order-item {
        border-bottom: 1px solid #f0f0f0;
        padding: 1rem;
    }

    .order-item:last-child {
        border-bottom: none;
    }

    /* Category pills */
    .category-pill {
        padding: 0.5rem 1.25rem;
        border-radius: 50px;
        border: 2px solid var(--primary-color);
        background: white;
        color: var(--primary-color);
        text-decoration: none;
        transition: var(--transition-base);
        display: inline-block;
        margin: 0.25rem;
        font-weight: 500;
    }

    .category-pill:hover,
    .category-pill.active {
        background: var(--primary-color);
        color: white;
        transform: translateY(-2px);
    }

    /* Form controls */
    .form-control:focus,
    .form-select:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    }

    /* Buttons */
    .btn-primary {
        background: var(--primary-color);
        border-color: var(--primary-color);
    }

    .btn-primary:hover {
        background: var(--secondary-color);
        border-color: var(--secondary-color);
    }

    /* Empty state */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6c757d;
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    /* Quantity controls */
    .quantity-control {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .quantity-control .btn {
        width: 32px;
        height: 32px;
        padding: 0;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .quantity-control input {
        width: 60px;
        text-align: center;
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
            padding-top: 4.5rem;
        }

        .page-header {
            padding: 1rem;
        }

        .card-body {
            padding: 1rem;
        }

        .order-summary {
            position: relative;
            top: 0;
            margin-bottom: 1.5rem;
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
                        <?php if ($current_order): ?>
                            <i class="fas fa-edit me-2"></i>Editar Orden #<?php echo $current_order['order_number']; ?>
                        <?php else: ?>
                            <i class="fas fa-plus-circle me-2"></i>Nueva Orden
                        <?php endif; ?>
                    </h2>
                    <?php if ($table): ?>
                        <p class="text-muted mb-0">
                            <i class="fas fa-chair me-1"></i>Mesa <?php echo $table['table_number']; ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <a href="<?php echo $table ? 'tables.php' : 'orders.php'; ?>" class="btn btn-secondary">
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

        <?php if (!$current_order): ?>
            <!-- Create Order Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-file-alt me-2"></i>
                    Información de la Orden
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_order">
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Tipo de Orden *</label>
                                <select name="type" class="form-select" required>
                                    <option value="dine_in" <?php echo $table ? 'selected' : ''; ?>>Consumo en Mesa</option>
                                    <option value="takeout">Para Llevar</option>
                                    <option value="delivery">Delivery</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Nombre del Cliente</label>
                                <input type="text" name="customer_name" class="form-control" placeholder="Opcional">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Teléfono</label>
                                <input type="tel" name="customer_phone" class="form-control" placeholder="Opcional">
                            </div>
                            
                            <div class="col-md-8">
                                <label class="form-label">Dirección</label>
                                <input type="text" name="customer_address" class="form-control" placeholder="Opcional - para delivery">
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Notas del Cliente</label>
                                <input type="text" name="customer_notes" class="form-control" placeholder="Opcional">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Notas Internas</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Opcional"></textarea>
                            </div>
                            
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    Crear Orden y Agregar Productos
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- Add Products to Order -->
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

                    <!-- Products Table with DataTables -->
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
                                                    <td>
                                                        <?php 
                                                        $category = array_filter($categories, function($cat) use ($product) {
                                                            return $cat['id'] == $product['category_id'];
                                                        });
                                                        $category = reset($category);
                                                        echo $category ? htmlspecialchars($category['name']) : 'Sin categoría';
                                                        ?>
                                                    </td>
                                                    <td>
                                                        <span class="product-price"><?php echo formatPrice($product['price']); ?></span>
                                                    </td>
                                                    <td>
                                                        <?php if ($product['is_available']): ?>
                                                            <span class="badge bg-success">Disponible</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">No disponible</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($product['is_available']): ?>
                                                            <button type="button" class="btn btn-sm btn-primary" onclick="addProductToOrder(<?php echo $product['id']; ?>)">
                                                                <i class="fas fa-plus me-1"></i>Agregar
                                                            </button>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-secondary" disabled>
                                                                <i class="fas fa-ban me-1"></i>No disponible
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

                <!-- Order Summary Sidebar -->
                <div class="col-lg-4">
                    <div class="order-summary">
                        <div class="card-header">
                            <i class="fas fa-shopping-cart me-2"></i>
                            Orden Actual
                        </div>
                        <div class="card-body p-0">
                            <?php 
                            $order_items = $orderModel->getItems($current_order['id']);
                            if (empty($order_items)): 
                            ?>
                                <div class="empty-state py-4">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p class="mb-0">Sin productos agregados</p>
                                </div>
                            <?php else: ?>
                                <div style="max-height: 400px; overflow-y: auto;">
                                    <?php foreach ($order_items as $item): ?>
                                        <div class="order-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="flex-grow-1">
                                                    <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                    <?php if ($item['notes']): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['notes']); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="remove_item">
                                                    <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                    <input type="hidden" name="order_id" value="<?php echo $current_order['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Eliminar este producto?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div class="quantity-control">
                                                    <form method="POST" class="d-flex align-items-center gap-2">
                                                        <input type="hidden" name="action" value="update_quantity">
                                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                        <input type="hidden" name="order_id" value="<?php echo $current_order['id']; ?>">
                                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] - 1; ?>" class="btn btn-sm btn-outline-secondary" <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                                                            <i class="fas fa-minus"></i>
                                                        </button>
                                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" class="form-control form-control-sm" min="1" readonly>
                                                        <button type="submit" name="quantity" value="<?php echo $item['quantity'] + 1; ?>" class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                                <strong><?php echo formatPrice($item['subtotal']); ?></strong>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Total:</strong>
                                <strong class="h4 mb-0 text-success"><?php echo formatPrice($current_order['total']); ?></strong>
                            </div>
                            <?php if (!empty($order_items)): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="finalize_order">
                                    <input type="hidden" name="order_id" value="<?php echo $current_order['id']; ?>">
                                    <button type="submit" class="btn btn-success w-100">
                                        <i class="fas fa-check me-1"></i>
                                        Finalizar Orden
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
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
                        <input type="hidden" name="order_id" value="<?php echo $current_order['id'] ?? ''; ?>">
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

        // Add product to order
        function addProductToOrder(productId) {
            document.getElementById('modal_product_id').value = productId;
            const modal = new bootstrap.Modal(document.getElementById('addProductModal'));
            modal.show();
        }
    </script>
</body>
</html>