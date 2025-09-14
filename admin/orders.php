<?php
// admin/orders.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Order.php';
require_once '../models/Table.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('orders');

$orderModel = new Order();
$tableModel = new Table();

// Handle status updates
$message = '';
$error = '';

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_status':
            $order_id = intval($_POST['order_id']);
            $new_status = $_POST['status'];
            
            if ($orderModel->updateStatus($order_id, $new_status)) {
                // If marking as delivered and it's a dine-in order, free the table
                if ($new_status === 'delivered') {
                    $order = $orderModel->getById($order_id);
                    if ($order['type'] === 'dine_in' && $order['table_id']) {
                        $tableModel->releaseTable($order['table_id']);
                    }
                }
                $message = 'Estado de orden actualizado exitosamente';
            } else {
                $error = 'Error al actualizar el estado de la orden';
            }
            break;
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_filter = $_GET['date'] ?? '';
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;

// Build query conditions
$conditions = [];
$params = [];

if ($status_filter) {
    $conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if ($type_filter) {
    $conditions[] = "o.type = ?";
    $params[] = $type_filter;
}

if ($date_filter) {
    $conditions[] = "DATE(o.created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get orders with pagination
$database = new Database();
$db = $database->getConnection();

$count_query = "SELECT COUNT(*) FROM orders o $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_orders = $count_stmt->fetchColumn();

$total_pages = ceil($total_orders / $per_page);
$offset = ($page - 1) * $per_page;

$orders_query = "SELECT o.*, u.full_name as waiter_name, t.number as table_number 
                 FROM orders o 
                 LEFT JOIN users u ON o.waiter_id = u.id 
                 LEFT JOIN tables t ON o.table_id = t.id 
                 $where_clause 
                 ORDER BY o.created_at DESC 
                 LIMIT $per_page OFFSET $offset";

$orders_stmt = $db->prepare($orders_query);
$orders_stmt->execute($params);
$orders = $orders_stmt->fetchAll();

// Get statistics
$stats_query = "SELECT 
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed,
    COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing,
    COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready,
    COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered,
    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled,
    COALESCE(SUM(CASE WHEN status IN ('delivered') THEN total END), 0) as total_sales
    FROM orders 
    WHERE DATE(created_at) = CURDATE()";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch();

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Órdenes - <?php echo $restaurant_name; ?></title>
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

        .mobile-topbar h5 {
            margin: 0;
            font-size: 1.1rem;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
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
            opacity: 0;
            transition: opacity 0.3s ease-in-out;
        }

        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
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

        .sidebar .nav-link .badge {
            margin-left: auto;
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
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        /* Main content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out;
        }

        /* Statistics cards */
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            flex-shrink: 0;
        }

        .bg-primary-gradient { background: linear-gradient(45deg, #667eea, #764ba2); }
        .bg-success-gradient { background: linear-gradient(45deg, #56ab2f, #a8e6cf); }
        .bg-warning-gradient { background: linear-gradient(45deg, #f093fb, #f5576c); }
        .bg-info-gradient { background: linear-gradient(45deg, #4facfe, #00f2fe); }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        /* Order cards */
        .order-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 4px solid;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        }

        .order-pending { border-left-color: #6c757d; }
        .order-confirmed { border-left-color: #17a2b8; }
        .order-preparing { border-left-color: #ffc107; }
        .order-ready { border-left-color: #28a745; }
        .order-delivered { border-left-color: #007bff; }
        .order-cancelled { border-left-color: #dc3545; }

        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .order-number {
            font-weight: bold;
            color: #007bff;
        }

        .order-time {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .order-customer {
            font-size: 0.9rem;
            color: #495057;
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
                padding-top: 5rem;
            }

            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .stat-icon {
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }

            .page-header {
                padding: 1rem;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .page-header .d-flex {
                flex-direction: column;
                align-items: flex-start !important;
            }

            .page-header .btn {
                margin-top: 1rem;
                width: 100%;
            }

            .filter-card {
                padding: 1rem;
            }

            .filter-card .row {
                row-gap: 0.5rem;
            }

            .order-card {
                margin-bottom: 1rem;
            }

            .order-card .card-body {
                padding: 1rem;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn-group .btn {
                border-radius: 0.375rem !important;
                margin-bottom: 0.25rem;
            }

            /* Pagination on mobile */
            .pagination {
                flex-wrap: wrap;
                justify-content: center;
            }

            .pagination .page-item {
                margin: 0.125rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
                padding-top: 4.5rem;
            }

            .stat-card {
                padding: 0.75rem;
                text-align: center;
            }

            .stat-card .d-flex {
                flex-direction: column;
                align-items: center;
            }

            .stat-icon {
                margin-bottom: 0.5rem;
                margin-right: 0 !important;
            }

            .page-header {
                padding: 0.75rem;
            }

            .filter-card .row {
                flex-direction: column;
            }

            .filter-card .col-md-2,
            .filter-card .col-md-3 {
                margin-bottom: 0.5rem;
            }

            /* Stack order info vertically on very small screens */
            .order-card .d-flex {
                flex-direction: column !important;
                align-items: flex-start !important;
            }

            .order-card .text-end {
                text-align: start !important;
                margin-top: 0.5rem;
            }

            .sidebar {
                padding: 1rem;
            }

            .sidebar .nav-link {
                padding: 0.5rem 0.75rem;
            }
        }

        /* Loading animation */
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

        /* Badge animation */
        .badge {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background: #1a1a1a;
            }

            .stat-card,
            .filter-card,
            .order-card,
            .page-header {
                background: #2d2d2d;
                color: white;
            }

            .text-muted {
                color: #aaa !important;
            }
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
                    <i class="fas fa-receipt me-2"></i>
                    Órdenes
                </h5>
            </div>
            <div class="d-flex align-items-center">
                <small class="me-3 d-none d-sm-inline">
                    <i class="fas fa-user me-1"></i>
                    <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>
                </small>
                <small>
                    <i class="fas fa-clock me-1"></i>
                    <?php echo date('H:i'); ?>
                </small>
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
            <small>Gestión de Órdenes</small>
        </div>

        <div class="mb-4">
            <div class="d-flex align-items-center">
                <div class="bg-white bg-opacity-20 rounded-circle p-2 me-2">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="fw-bold"><?php echo $_SESSION['full_name']; ?></div>
                    <small class="opacity-75"><?php echo ucfirst($_SESSION['role_name']); ?></small>
                </div>
            </div>
        </div>

        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>
                Dashboard
            </a>
            <a class="nav-link active" href="orders.php">
                <i class="fas fa-receipt me-2"></i>
                Órdenes
            </a>
            <a class="nav-link" href="order-create.php">
                <i class="fas fa-plus me-2"></i>
                Nueva Orden
            </a>
            <a class="nav-link" href="tables.php">
                <i class="fas fa-table me-2"></i>
                Mesas
            </a>
            
            <?php if ($auth->hasPermission('products')): ?>
                <a class="nav-link" href="products.php">
                    <i class="fas fa-utensils me-2"></i>
                    Productos
                </a>
            <?php endif; ?>
            
            <?php if ($auth->hasPermission('users')): ?>
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>
                    Usuarios
                </a>
            <?php endif; ?>
            
            <?php if ($auth->hasPermission('kitchen')): ?>
                <a class="nav-link" href="kitchen.php">
                    <i class="fas fa-fire me-2"></i>
                    Cocina
                </a>
            <?php endif; ?>
            
            <?php if ($auth->hasPermission('delivery')): ?>
                <a class="nav-link" href="delivery.php">
                    <i class="fas fa-motorcycle me-2"></i>
                    Delivery
                </a>
            <?php endif; ?>
            
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
                    <h2 class="mb-0">Gestión de Órdenes</h2>
                    <p class="text-muted mb-0">Control y seguimiento de todas las órdenes</p>
                </div>
                <div>
                    <a href="order-create.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>
                        Nueva Orden
                    </a>
                    <button class="btn btn-info" onclick="location.reload()">
                        <i class="fas fa-sync me-1"></i>
                        Actualizar
                    </button>
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
        
        <!-- Statistics -->
        <div class="row g-3 g-md-4 mb-4">
            <div class="col-6 col-md-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary-gradient me-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['pending']; ?></h3>
                            <p class="text-muted mb-0 small">Pendientes</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning-gradient me-3">
                            <i class="fas fa-fire"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['preparing']; ?></h3>
                            <p class="text-muted mb-0 small">Preparando</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success-gradient me-3">
                            <i class="fas fa-check"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['ready']; ?></h3>
                            <p class="text-muted mb-0 small">Listos</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info-gradient me-3">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['delivered']; ?></h3>
                            <p class="text-muted mb-0 small">Entregados</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning-gradient me-3">
                            <i class="fas fa-times"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo $stats['cancelled']; ?></h3>
                            <p class="text-muted mb-0 small">Cancelados</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success-gradient me-3">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div>
                            <h3 class="mb-0"><?php echo formatPrice($stats['total_sales']); ?></h3>
                            <p class="text-muted mb-0 small">Ventas Hoy</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-card">
            <form method="GET" class="row align-items-end g-3">
                <div class="col-md-2">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="status">
                        <option value="">Todos los estados</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmado</option>
                        <option value="preparing" <?php echo $status_filter === 'preparing' ? 'selected' : ''; ?>>Preparando</option>
                        <option value="ready" <?php echo $status_filter === 'ready' ? 'selected' : ''; ?>>Listo</option>
                        <option value="delivered" <?php echo $status_filter === 'delivered' ? 'selected' : ''; ?>>Entregado</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Tipo</label>
                    <select class="form-select" name="type">
                        <option value="">Todos los tipos</option>
                        <option value="dine_in" <?php echo $type_filter === 'dine_in' ? 'selected' : ''; ?>>Mesa</option>
                        <option value="delivery" <?php echo $type_filter === 'delivery' ? 'selected' : ''; ?>>Delivery</option>
                        <option value="takeout" <?php echo $type_filter === 'takeout' ? 'selected' : ''; ?>>Retiro</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fecha</label>
                    <input type="date" class="form-control" name="date" value="<?php echo $date_filter; ?>">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-filter me-1"></i>
                        Filtrar
                    </button>
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-1"></i>
                        Limpiar
                    </a>
                </div>
                <div class="col-md-3 text-end">
                    <small class="text-muted">
                        Mostrando <?php echo count($orders); ?> de <?php echo $total_orders; ?> órdenes
                    </small>
                </div>
            </form>
        </div>
        
        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="text-center py-5">
                <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No se encontraron órdenes</h5>
                <p class="text-muted">Prueba ajustando los filtros o crea una nueva orden</p>
                <a href="order-create.php" class="btn btn-success">
                    <i class="fas fa-plus me-2"></i>
                    Nueva Orden
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($orders as $order): ?>
                    <div class="col-lg-6 col-xl-4 mb-3">
                        <div class="card order-card order-<?php echo $order['status']; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="order-number">#<?php echo $order['order_number']; ?></div>
                                <div>
                                    <?php
                                    $status_colors = [
                                        'pending' => 'secondary',
                                        'confirmed' => 'info',
                                        'preparing' => 'warning',
                                        'ready' => 'success',
                                        'delivered' => 'primary',
                                        'cancelled' => 'danger'
                                    ];
                                    
                                    $status_texts = [
                                        'pending' => 'Pendiente',
                                        'confirmed' => 'Confirmado',
                                        'preparing' => 'Preparando',
                                        'ready' => 'Listo',
                                        'delivered' => 'Entregado',
                                        'cancelled' => 'Cancelado'
                                    ];
                                    
                                    $type_icons = [
                                        'dine_in' => 'table',
                                        'delivery' => 'motorcycle',
                                        'takeout' => 'shopping-bag'
                                    ];
                                    
                                    $type_texts = [
                                        'dine_in' => 'Mesa',
                                        'delivery' => 'Delivery',
                                        'takeout' => 'Retiro'
                                    ];
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$order['status']]; ?>">
                                        <?php echo $status_texts[$order['status']]; ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-<?php echo $type_icons[$order['type']]; ?> me-2"></i>
                                    <strong><?php echo $type_texts[$order['type']]; ?></strong>
                                    <?php if ($order['type'] === 'dine_in' && $order['table_number']): ?>
                                        <span class="ms-2 text-muted"><?php echo $order['table_number']; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($order['customer_name']): ?>
                                    <div class="order-customer mb-1">
                                        <i class="fas fa-user me-2"></i>
                                        <?php echo htmlspecialchars($order['customer_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['customer_phone']): ?>
                                    <div class="order-customer mb-2">
                                        <i class="fas fa-phone me-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
    <div class="order-time">
        <i class="fas fa-clock me-1"></i>
        <?php echo formatDateTime($order['created_at']); ?>
        <br>
        <?php 
$priorityStatus = getOrderPriorityStatus($order);
echo $priorityStatus['time'];
if ($priorityStatus['is_priority']): ?>
    <span class="<?php echo $priorityStatus['class']; ?> ms-1">
        <?php echo $priorityStatus['label']; ?>
    </span>
<?php endif; ?>
    </div>
    <div class="h5 text-success mb-0">
        <?php echo formatPrice($order['total']); ?>
    </div>
</div>
                                
                                <?php if ($order['waiter_name']): ?>
                                    <div class="text-muted small">
                                        <i class="fas fa-user-tie me-1"></i>
                                        Atendido por: <?php echo htmlspecialchars($order['waiter_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <div class="d-flex gap-2">
                                    <a href="order-details.php?id=<?php echo $order['id']; ?>" 
                                       class="btn btn-sm btn-outline-primary flex-fill">
                                        <i class="fas fa-eye me-1"></i>
                                        Ver
                                    </a>
                                    
                                    <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                        <div class="btn-group flex-fill">
                                            <button class="btn btn-sm btn-success dropdown-toggle" 
                                                    type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-cog me-1"></i>
                                                Estado
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($order['status'] === 'pending'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button class="dropdown-item" type="submit">
                                                                <i class="fas fa-check text-info me-2"></i>Confirmar
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php if ($order['status'] === 'confirmed'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <input type="hidden" name="status" value="preparing">
                                                            <button class="dropdown-item" type="submit">
                                                                <i class="fas fa-fire text-warning me-2"></i>Preparando
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php if ($order['status'] === 'preparing'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <input type="hidden" name="status" value="ready">
                                                            <button class="dropdown-item" type="submit">
                                                                <i class="fas fa-bell text-success me-2"></i>Listo
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <?php if ($order['status'] === 'ready'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_status">
                                                            <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                            <input type="hidden" name="status" value="delivered">
                                                            <button class="dropdown-item" type="submit">
                                                                <i class="fas fa-<?php echo $order['type'] === 'delivery' ? 'motorcycle' : 'utensils'; ?> text-primary me-2"></i>
                                                                <?php echo $order['type'] === 'delivery' ? 'Entregado' : 'Servido'; ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="update_status">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button class="dropdown-item text-danger" type="submit"
                                                                onclick="return confirm('¿Cancelar esta orden?')">
                                                            <i class="fas fa-times me-2"></i>Cancelar
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo ($page - 1) . ($status_filter ? '&status=' . $status_filter : '') . ($type_filter ? '&type=' . $type_filter : '') . ($date_filter ? '&date=' . $date_filter : ''); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($total_pages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i . ($status_filter ? '&status=' . $status_filter : '') . ($type_filter ? '&type=' . $type_filter : '') . ($date_filter ? '&date=' . $date_filter : ''); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo ($page + 1) . ($status_filter ? '&status=' . $status_filter : '') . ($type_filter ? '&type=' . $type_filter : '') . ($date_filter ? '&date=' . $date_filter : ''); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
        });

        function initializeMobileMenu() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const sidebarClose = document.getElementById('sidebarClose');

            // Toggle mobile menu
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('show');
                    sidebarBackdrop.classList.toggle('show');
                    document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
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
                        setTimeout(closeSidebar, 100);
                    }
                });
            });

            // Handle window resize
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

        // Auto-refresh every 60 seconds
        setTimeout(() => {
            location.reload();
        }, 60000);
        
        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Prevent zoom on double tap for better mobile UX
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function (event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        // Add pull-to-refresh functionality for mobile
        let startY = 0;
        let currentY = 0;
        let isPulling = false;

        if ('ontouchstart' in window) {
            document.addEventListener('touchstart', (e) => {
                if (window.scrollY === 0) {
                    startY = e.touches[0].pageY;
                    isPulling = true;
                }
            });

            document.addEventListener('touchmove', (e) => {
                if (isPulling && window.scrollY === 0) {
                    currentY = e.touches[0].pageY;
                    if (currentY > startY + 100) {
                        const header = document.querySelector('.page-header');
                        if (header && !header.classList.contains('refreshing')) {
                            header.style.transform = 'translateY(10px)';
                            header.style.transition = 'transform 0.3s ease';
                        }
                    }
                }
            });

            document.addEventListener('touchend', (e) => {
                if (isPulling && currentY > startY + 100) {
                    const header = document.querySelector('.page-header');
                    if (header) {
                        header.classList.add('refreshing');
                        header.style.transform = '';
                        location.reload();
                    }
                }
                
                isPulling = false;
                startY = 0;
                currentY = 0;
                
                const header = document.querySelector('.page-header');
                if (header) {
                    header.style.transform = '';
                }
            });
        }

        // Add keyboard shortcuts for desktop users
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
            
            if (e.altKey) {
                switch (e.key) {
                    case '1':
                        e.preventDefault();
                        window.location.href = 'dashboard.php';
                        break;
                    case '2':
                        e.preventDefault();
                        window.location.href = 'orders.php';
                        break;
                    case '3':
                        e.preventDefault();
                        window.location.href = 'order-create.php';
                        break;
                    case 'r':
                        e.preventDefault();
                        location.reload();
                        break;
                }
            }
        });
    </script>

<?php include 'footer.php'; ?>
</body>
</html>