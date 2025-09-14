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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_order ? 'Editar Orden' : 'Nueva Orden'; ?> - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            --sidebar-width: 280px;
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
            color: white;
            padding: 1rem;
            display: none;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-gradient);
            color: white;
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
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
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
            color: white;
        }

        .sidebar-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: none;
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out;
        }

        /* Product cards */
        .product-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            height: 100%;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .product-image {
            height: 120px;
            object-fit: cover;
            width: 100%;
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: bold;
            color: #28a745;
        }

        /* Order summary */
        .order-summary {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
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

        /* Page header */
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        /* Category pills */
        .category-pill {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            border: 2px solid #007bff;
            background: white;
            color: #007bff;
            text-decoration: none;
            transition: all 0.3s;
        }

        .category-pill:hover,
        .category-pill.active {
            background: #007bff;
            color: white;
        }

        /* Mobile responsive */
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
                align-items: center;
                justify-content: center;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 5rem;
            }

            .order-summary {
                position: relative;
                top: auto;
                margin-top: 2rem;
            }

            .product-image {
                height: 100px;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
                padding-top: 4.5rem;
            }

            .page-header {
                padding: 1rem;
            }

            .product-card .card-body {
                padding: 0.75rem;
            }
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-controls button {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid #ddd;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .quantity-controls input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 0.25rem;
        }
    </style>
</head>
<body>
    <!-- Mobile Top Bar -->
    <div class="mobile-topbar">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div class="d-flex align-items-center">
                <button class="menu-toggle me-3" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5>
                    <i class="fas fa-plus me-2"></i>
                    <?php echo $current_order ? 'Editar Orden' : 'Nueva Orden'; ?>
                </h5>
            </div>
        </div>
    </div>

    <!-- Sidebar Backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="sidebar-close" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>

        <div class="text-center mb-4">
            <h4>
                <i class="fas fa-utensils me-2"></i>
                <?php echo $restaurant_name; ?>
            </h4>
            <small><?php echo $current_order ? 'Editar Orden' : 'Nueva Orden'; ?></small>
        </div>

        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>
                Dashboard
            </a>
            <a class="nav-link" href="orders.php">
                <i class="fas fa-receipt me-2"></i>
                Órdenes
            </a>
            <a class="nav-link active" href="order-create.php">
                <i class="fas fa-plus me-2"></i>
                Nueva Orden
            </a>
            <a class="nav-link" href="tables.php">
                <i class="fas fa-table me-2"></i>
                Mesas
            </a>
            <a class="nav-link" href="products.php">
                <i class="fas fa-utensils me-2"></i>
                Productos
            </a>
            
            <hr class="text-white-50 my-3">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>
                Cerrar Sesión
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-<?php echo $current_order ? 'edit' : 'plus'; ?> me-2"></i>
                        <?php echo $current_order ? 'Editar Orden #' . $current_order['order_number'] : 'Nueva Orden'; ?>
                    </h2>
                    <?php if ($table): ?>
                        <p class="text-muted mb-0">
                            <i class="fas fa-table me-1"></i>
                            <?php echo htmlspecialchars($table['number']); ?> 
                            (<?php echo $table['capacity']; ?> personas)
                        </p>
                    <?php endif; ?>
                </div>
                <div>
                    <?php if ($current_order): ?>
                        <a href="order-details.php?id=<?php echo $current_order['id']; ?>" class="btn btn-info">
                            <i class="fas fa-eye me-1"></i>
                            Ver Detalles
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo $table_id ? 'tables.php' : 'orders.php'; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Volver
                    </a>
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

        <div class="row">
            <!-- Products Section -->
            <div class="col-lg-8">
                <?php if (!$current_order): ?>
                    <!-- Order Type Selection -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-list me-2"></i>
                                Información de la Orden
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="create_order">
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Tipo de Orden</label>
                                        <select class="form-select" name="type" required onchange="toggleOrderFields(this.value)">
                                            <option value="dine_in" <?php echo $table_id ? 'selected' : ''; ?>>Consumo en Mesa</option>
                                            <option value="delivery">Delivery</option>
                                            <option value="takeout">Para Llevar</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre del Cliente</label>
                                        <input type="text" class="form-control" name="customer_name" 
                                               value="<?php echo $table ? htmlspecialchars($table['number']) : ''; ?>">
                                    </div>
                                </div>
                                
                                <div class="row mb-3" id="deliveryFields" style="display: none;">
                                    <div class="col-md-6">
                                        <label class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" name="customer_phone">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Dirección</label>
                                        <input type="text" class="form-control" name="customer_address">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Notas del Cliente</label>
                                    <textarea class="form-control" name="customer_notes" rows="2"></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Notas Internas</label>
                                    <textarea class="form-control" name="notes" rows="2"></textarea>
                                </div>
                                
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus me-1"></i>
                                    Crear Orden
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Category Filter -->
                    <div class="mb-4">
                        <div class="d-flex flex-wrap gap-2">
                            <a href="#" class="category-pill active" onclick="filterCategory('all')">
                                <i class="fas fa-th me-1"></i>
                                Todos
                            </a>
                            <?php foreach ($categories as $category): ?>
                                <a href="#" class="category-pill" onclick="filterCategory(<?php echo $category['id']; ?>)">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Products Grid -->
                    <?php foreach ($categories as $category): ?>
                        <?php if (isset($products_by_category[$category['id']])): ?>
                            <div class="category-section" data-category="<?php echo $category['id']; ?>">
                                <h4 class="mb-3">
                                    <i class="fas fa-utensils me-2"></i>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </h4>
                                <div class="row g-3 mb-4">
                                    <?php foreach ($products_by_category[$category['id']] as $product): ?>
                                        <div class="col-sm-6 col-lg-4 col-xl-3">
                                            <div class="card product-card">
                                                <?php if ($product['image']): ?>
                                                    <img src="../<?php echo htmlspecialchars($product['image']); ?>" 
                                                         class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                                <?php else: ?>
                                                    <div class="product-image bg-light d-flex align-items-center justify-content-center">
                                                        <i class="fas fa-utensils fa-2x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="card-body">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                                    <?php if ($product['description']): ?>
                                                        <p class="card-text small text-muted">
                                                            <?php echo htmlspecialchars(substr($product['description'], 0, 50)) . '...'; ?>
                                                        </p>
                                                    <?php endif; ?>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="product-price"><?php echo formatPrice($product['price']); ?></span>
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="addToOrder(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>)">
                                                            <i class="fas fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Order Summary -->
            <?php if ($current_order): ?>
                <div class="col-lg-4">
                    <div class="order-summary">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-receipt me-2"></i>
                                Resumen de Orden
                            </h5>
                        </div>
                        <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                            <?php 
                            $order_items = $orderModel->getItems($current_order['id']);
                            $subtotal = 0;
                            ?>
                            
                            <?php if (empty($order_items)): ?>
                                <div class="text-center p-4 text-muted">
                                    <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                    <p>No hay productos en la orden</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($order_items as $item): ?>
                                    <?php $subtotal += $item['subtotal']; ?>
                                    <div class="order-item">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                            <button class="btn btn-sm btn-outline-danger" 
                                                    onclick="removeItem(<?php echo $item['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="quantity-controls">
                                                <button onclick="updateQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] - 1; ?>)"
                                                        <?php echo $item['quantity'] <= 1 ? 'disabled' : ''; ?>>
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" value="<?php echo $item['quantity']; ?>" min="1"
                                                       onchange="updateQuantity(<?php echo $item['id']; ?>, this.value)">
                                                <button onclick="updateQuantity(<?php echo $item['id']; ?>, <?php echo $item['quantity'] + 1; ?>)">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                            <span class="fw-bold"><?php echo formatPrice($item['subtotal']); ?></span>
                                        </div>
                                        
                                        <?php if ($item['notes']): ?>
                                            <small class="text-muted d-block mt-1">
                                                <i class="fas fa-sticky-note me-1"></i>
                                                <?php echo htmlspecialchars($item['notes']); ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($order_items)): ?>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <strong>Subtotal:</strong>
                                    <strong><?php echo formatPrice($subtotal); ?></strong>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="finalize_order">
                                        <input type="hidden" name="order_id" value="<?php echo $current_order['id']; ?>">
                                        <button type="submit" class="btn btn-success w-100" 
                                                onclick="return confirm('¿Finalizar y confirmar la orden?')">
                                            <i class="fas fa-check me-1"></i>
                                            Finalizar Orden
                                        </button>
                                    </form>
                                    
                                    <a href="order-details.php?id=<?php echo $current_order['id']; ?>" 
                                       class="btn btn-info w-100">
                                        <i class="fas fa-eye me-1"></i>
                                        Ver Detalles
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Product Modal -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="addProductForm">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus me-2"></i>
                            Agregar Producto
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_item">
                        <input type="hidden" name="order_id" value="<?php echo $current_order['id'] ?? ''; ?>">
                        <input type="hidden" name="product_id" id="modalProductId">
                        
                        <div class="mb-3">
                            <label class="form-label">Producto:</label>
                            <strong id="modalProductName"></strong>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Precio:</label>
                            <strong id="modalProductPrice"></strong>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Cantidad</label>
                            <input type="number" class="form-control" name="quantity" min="1" value="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notas (opcional)</label>
                            <textarea class="form-control" name="item_notes" rows="2" placeholder="Ej: Sin cebolla, punto medio, etc."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>
                            Agregar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
            
            // Set initial order type visibility
            const orderType = document.querySelector('select[name="type"]');
            if (orderType) {
                toggleOrderFields(orderType.value);
            }
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

        function toggleOrderFields(orderType) {
            const deliveryFields = document.getElementById('deliveryFields');
            if (deliveryFields) {
                if (orderType === 'delivery') {
                    deliveryFields.style.display = 'flex';
                    deliveryFields.querySelectorAll('input').forEach(input => {
                        input.required = true;
                    });
                } else {
                    deliveryFields.style.display = 'none';
                    deliveryFields.querySelectorAll('input').forEach(input => {
                        input.required = false;
                    });
                }
            }
        }

        function filterCategory(categoryId) {
            // Update active pill
            document.querySelectorAll('.category-pill').forEach(pill => {
                pill.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Show/hide categories
            document.querySelectorAll('.category-section').forEach(section => {
                if (categoryId === 'all' || section.dataset.category == categoryId) {
                    section.style.display = 'block';
                } else {
                    section.style.display = 'none';
                }
            });
        }

        function addToOrder(productId, productName, productPrice) {
            document.getElementById('modalProductId').value = productId;
            document.getElementById('modalProductName').textContent = productName;
            document.getElementById('modalProductPrice').textContent = formatPrice(productPrice);
            
            // Reset form
            document.getElementById('addProductForm').reset();
            document.getElementById('modalProductId').value = productId;
            document.querySelector('input[name="quantity"]').value = 1;
            
            new bootstrap.Modal(document.getElementById('addProductModal')).show();
        }

        function updateQuantity(itemId, newQuantity) {
            if (newQuantity < 1) {
                if (confirm('¿Eliminar este producto de la orden?')) {
                    removeItem(itemId);
                }
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="update_quantity">
                <input type="hidden" name="item_id" value="${itemId}">
                <input type="hidden" name="quantity" value="${newQuantity}">
                <input type="hidden" name="order_id" value="<?php echo $current_order['id'] ?? ''; ?>">
            `;
            document.body.appendChild(form);
            form.submit();
        }

        function removeItem(itemId) {
            if (confirm('¿Eliminar este producto de la orden?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="remove_item">
                    <input type="hidden" name="item_id" value="${itemId}">
                    <input type="hidden" name="order_id" value="<?php echo $current_order['id'] ?? ''; ?>">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function formatPrice(price) {
            return '$' + parseFloat(price).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>