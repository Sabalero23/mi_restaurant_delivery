<?php
// admin/kardex.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();

// Verificar permisos
if (!$auth->hasPermission('kardex') && !$auth->hasPermission('products') && !$auth->hasPermission('all')) {
    header("Location: dashboard.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Obtener configuraciones
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Filtros
$product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$movement_type = isset($_GET['movement_type']) ? $_GET['movement_type'] : '';

// Obtener productos con inventario
$query_products = "SELECT id, name, stock_quantity, low_stock_alert, price, cost FROM products WHERE track_inventory = 1 AND is_active = 1 ORDER BY name";
$stmt_products = $db->prepare($query_products);
$stmt_products->execute();
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

// Construir query de movimientos
$query = "SELECT 
    sm.*,
    p.name as product_name,
    p.category_id,
    c.name as category_name,
    u.full_name as user_name,
    CASE 
        WHEN sm.movement_type = 'entrada' THEN '+' 
        ELSE '-' 
    END as sign
    FROM stock_movements sm
    LEFT JOIN products p ON sm.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON sm.user_id = u.id
    WHERE 1=1";

$params = array();

if ($product_filter > 0) {
    $query .= " AND sm.product_id = :product_id";
    $params[':product_id'] = $product_filter;
}

if ($date_from) {
    $query .= " AND DATE(sm.created_at) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND DATE(sm.created_at) <= :date_to";
    $params[':date_to'] = $date_to;
}

if ($movement_type) {
    $query .= " AND sm.movement_type = :movement_type";
    $params[':movement_type'] = $movement_type;
}

$query .= " ORDER BY sm.created_at DESC, sm.id DESC LIMIT 500";

$stmt = $db->prepare($query);
$stmt->execute($params);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_entradas = 0;
$total_salidas = 0;
foreach ($movements as $mov) {
    if ($mov['movement_type'] === 'entrada') {
        $total_entradas += $mov['quantity'];
    } else {
        $total_salidas += $mov['quantity'];
    }
}

// Obtener información del producto seleccionado si hay filtro
$selected_product = null;
if ($product_filter > 0) {
    $query_product = "SELECT * FROM products WHERE id = :id";
    $stmt_product = $db->prepare($query_product);
    $stmt_product->execute([':id' => $product_filter]);
    $selected_product = $stmt_product->fetch(PDO::FETCH_ASSOC);
}

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
    <title>Kardex de Inventario - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Tema dinámico -->
    <?php if (file_exists('../assets/css/generate-theme.php')): ?>
        <link rel="stylesheet" href="../assets/css/generate-theme.php?v=<?php echo time(); ?>">
    <?php endif; ?>

    <style>
/* Extensiones específicas del dashboard */
:root {
    --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --dashboard-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
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

/* Sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--dashboard-sidebar-width);
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
    margin-left: var(--dashboard-sidebar-width);
    padding: 2rem;
    min-height: 100vh;
    transition: margin-left var(--transition-base);
    background: #f8f9fa !important;
    color: #212529 !important;
}

        /* Cards con fondo claro */
        .card {
            background: #ffffff !important;
            color: #212529 !important;
            border: none;
            border-radius: var(--border-radius-large);
            box-shadow: var(--shadow-base);
            margin-bottom: 1.5rem;
        }

        .card-header {
            background: #f8f9fa !important;
            color: #212529 !important;
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            border-radius: var(--border-radius-large) var(--border-radius-large) 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .card-body {
            background: #ffffff !important;
            color: #212529 !important;
            padding: 1.5rem;
        }

        /* Page Header */
        .page-header {
            background: #ffffff !important;
            color: #212529 !important;
            border-radius: var(--border-radius-large);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-base);
        }

        /* Stats Cards con gradientes */
        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white !important;
            border-radius: var(--border-radius-large);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-base);
            transition: transform var(--transition-base);
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card.entrada {
            background: linear-gradient(135deg, var(--success-color), #20c997);
        }

        .stats-card.salida {
            background: linear-gradient(135deg, var(--danger-color), #e83e8c);
        }

        .stats-card.saldo {
            background: linear-gradient(135deg, var(--info-color), var(--secondary-color));
        }

        .stats-card h3 {
            font-size: 2rem;
            margin: 0;
            font-weight: bold;
            color: white !important;
        }

        .stats-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.9rem;
            color: white !important;
        }

        /* Filter Card */
        .filter-card {
            background: #f8f9fa !important;
            color: #212529 !important;
            border-radius: var(--border-radius-large);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-small);
        }

        /* Table styles */
        .table {
            margin-bottom: 0;
            background: #ffffff !important;
            color: #212529 !important;
        }

        .table thead th {
            background: #f8f9fa !important;
            color: #212529 !important;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            padding: 1rem 0.75rem;
        }

        .table tbody td {
            padding: 0.75rem;
            vertical-align: middle;
            color: #212529 !important;
            border-bottom: 1px solid #dee2e6;
        }

        .table-hover tbody tr:hover {
            background: rgba(0, 0, 0, 0.02) !important;
        }

        /* Badges */
        .badge-entrada {
            background: linear-gradient(45deg, var(--success-color), #20c997);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
        }

        .badge-salida {
            background: linear-gradient(45deg, var(--danger-color), #e83e8c);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
        }

        .movement-sign {
            font-size: 1.2rem;
            font-weight: bold;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .movement-sign.entrada {
            background: var(--success-color);
            color: white;
        }

        .movement-sign.salida {
            background: var(--danger-color);
            color: white;
        }

        /* Stock Indicators */
        .stock-indicator {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .stock-indicator.high {
            background: #d4edda;
            color: #155724;
        }

        .stock-indicator.medium {
            background: #fff3cd;
            color: #856404;
        }

        .stock-indicator.low {
            background: #f8d7da;
            color: #721c24;
        }

        /* Buttons */
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: var(--border-radius-base);
            transition: var(--transition-base);
            color: white !important;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-base);
        }

        .btn-success {
            background: linear-gradient(45deg, var(--success-color), #20c997);
            border: none;
            color: white !important;
        }

        .btn-danger {
            background: linear-gradient(45deg, var(--danger-color), #e83e8c);
            border: none;
            color: white !important;
        }

        .btn-secondary {
            background: linear-gradient(45deg, #6c757d, #495057);
            border: none;
            color: white !important;
        }

        /* Form controls */
        .form-control,
        .form-select {
            background: #ffffff !important;
            color: #212529 !important;
            border: 1px solid #ced4da;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            background: #ffffff !important;
            color: #212529 !important;
        }

        .form-label {
            color: #212529 !important;
            font-weight: 500;
        }

        /* Text colors */
        .text-muted {
            color: #6c757d !important;
        }

        h1, h2, h3, h4, h5, h6 {
            color: #212529 !important;
        }

        p {
            color: #212529 !important;
        }

        /* Empty State */
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

        /* Modal styles */
        .modal-content {
            background: #ffffff !important;
            color: #212529 !important;
        }

        .modal-header {
            background: var(--primary-gradient);
            color: white !important;
            border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
        }

        .modal-header .modal-title {
            color: white !important;
        }

        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }

        .modal-body {
            background: #ffffff !important;
            color: #212529 !important;
        }

        .product-info-box {
            background: #f8f9fa;
            border-radius: var(--border-radius-base);
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .product-info-box h6 {
            margin-bottom: 0.5rem;
            color: var(--primary-color);
            font-weight: 600;
        }

        .product-info-box p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
        }

        /* Responsive - IGUAL QUE DASHBOARD */
        @media (max-width: 991.98px) {
            .mobile-topbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 5rem;
            }

            .stats-card {
                padding: 1rem;
            }

            .stats-card h3 {
                font-size: 1.5rem;
            }

            .page-header {
                padding: 1rem;
            }

            .filter-card {
                padding: 1rem;
            }

            .table {
                font-size: 0.85rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
                padding-top: 4.5rem;
            }

            .stats-card {
                padding: 0.75rem;
            }

            .stats-card h3 {
                font-size: 1.25rem;
            }

            .page-header {
                padding: 0.75rem;
            }

            .page-header h2 {
                font-size: 1.25rem;
            }

            .filter-card {
                padding: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-boxes me-2"></i>
                        Kardex de Inventario
                    </h2>
                    <p class="text-muted mb-0">Control de entradas y salidas de productos</p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <button class="btn btn-success" onclick="openMovementModal('entrada')">
                        <i class="fas fa-plus me-1"></i>
                        <span class="d-none d-md-inline">Entrada</span>
                    </button>
                    <button class="btn btn-danger" onclick="openMovementModal('salida')">
                        <i class="fas fa-minus me-1"></i>
                        <span class="d-none d-md-inline">Salida</span>
                    </button>
                    <button class="btn btn-secondary" onclick="exportKardex()">
                        <i class="fas fa-file-excel me-1"></i>
                        <span class="d-none d-md-inline">Exportar</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stats-card entrada">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>TOTAL ENTRADAS</p>
                            <h3><?php echo number_format($total_entradas, 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-arrow-up fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card salida">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>TOTAL SALIDAS</p>
                            <h3><?php echo number_format($total_salidas, 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-arrow-down fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card saldo">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>SALDO NETO</p>
                            <h3><?php echo number_format($total_entradas - $total_salidas, 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-balance-scale fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Información del producto seleccionado -->
        <?php if ($selected_product): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="product-info-box">
                    <h6><i class="fas fa-box me-2"></i>Información del Producto</h6>
                    <div class="row">
                        <div class="col-md-3">
                            <p><strong>Producto:</strong> <?php echo htmlspecialchars($selected_product['name']); ?></p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Stock Actual:</strong> 
                                <span class="stock-indicator <?php 
                                    echo $selected_product['stock_quantity'] <= $selected_product['low_stock_alert'] ? 'low' : 
                                        ($selected_product['stock_quantity'] <= $selected_product['low_stock_alert'] * 2 ? 'medium' : 'high'); 
                                ?>">
                                    <?php echo $selected_product['stock_quantity']; ?> unidades
                                </span>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Precio:</strong> $<?php echo number_format($selected_product['price'], 2, ',', '.'); ?></p>
                        </div>
                        <div class="col-md-3">
                            <p><strong>Costo:</strong> $<?php echo number_format($selected_product['cost'], 2, ',', '.'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Filtros -->
        <div class="filter-card">
            <form method="GET" action="" id="filterForm">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Producto</label>
                        <select name="product_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                            <option value="0">Todos los productos</option>
                            <?php foreach ($products as $prod): ?>
                                <option value="<?php echo $prod['id']; ?>" 
                                    <?php echo $product_filter == $prod['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($prod['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tipo de Movimiento</label>
                        <select name="movement_type" class="form-select" onchange="document.getElementById('filterForm').submit()">
                            <option value="">Todos</option>
                            <option value="entrada" <?php echo $movement_type === 'entrada' ? 'selected' : ''; ?>>Entradas</option>
                            <option value="salida" <?php echo $movement_type === 'salida' ? 'selected' : ''; ?>>Salidas</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" name="date_from" class="form-control" 
                            value="<?php echo $date_from; ?>" 
                            onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="date_to" class="form-control" 
                            value="<?php echo $date_to; ?>"
                            onchange="document.getElementById('filterForm').submit()">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="button" class="btn btn-secondary w-100" onclick="clearFilters()" title="Limpiar filtros">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Tabla de movimientos -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Movimientos de Inventario
                    <span class="badge bg-primary ms-2"><?php echo count($movements); ?> registros</span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($movements)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5>No hay movimientos registrados</h5>
                        <p>No se encontraron movimientos con los filtros seleccionados.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>FECHA</th>
                                    <th>PRODUCTO</th>
                                    <th class="d-none d-md-table-cell">CATEGORÍA</th>
                                    <th>TIPO</th>
                                    <th class="text-center">CANTIDAD</th>
                                    <th class="text-center d-none d-lg-table-cell">STOCK ANTERIOR</th>
                                    <th class="text-center">STOCK NUEVO</th>
                                    <th class="d-none d-xl-table-cell">MOTIVO</th>
                                    <th class="d-none d-lg-table-cell">USUARIO</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($movements as $mov): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-calendar-alt text-muted me-1"></i>
                                        <span class="d-none d-md-inline"><?php echo date('d/m/Y', strtotime($mov['created_at'])); ?></span>
                                        <br class="d-md-none">
                                        <small><?php echo date('H:i', strtotime($mov['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($mov['product_name']); ?></strong>
                                    </td>
                                    <td class="d-none d-md-table-cell">
                                        <span class="badge bg-secondary">
                                            <?php echo htmlspecialchars($mov['category_name'] ?? 'Sin categoría'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge-<?php echo $mov['movement_type']; ?>">
                                            <i class="fas fa-arrow-<?php echo $mov['movement_type'] === 'entrada' ? 'up' : 'down'; ?> me-1"></i>
                                            <span class="d-none d-sm-inline"><?php echo ucfirst($mov['movement_type']); ?></span>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="movement-sign <?php echo $mov['movement_type']; ?>">
                                            <?php echo $mov['sign']; ?>
                                        </span>
                                        <strong><?php echo $mov['quantity']; ?></strong>
                                    </td>
                                    <td class="text-center d-none d-lg-table-cell">
                                        <span class="text-muted"><?php echo $mov['old_stock']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo $mov['new_stock']; ?></strong>
                                    </td>
                                    <td class="d-none d-xl-table-cell">
                                        <small><?php echo htmlspecialchars(substr($mov['reason'] ?? '-', 0, 30)); ?></small>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <i class="fas fa-user text-muted me-1"></i>
                                        <small><?php echo htmlspecialchars($mov['user_name'] ?? 'Sistema'); ?></small>
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

    <!-- Modal para registrar movimiento -->
    <div class="modal fade" id="movementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="movementModalTitle">
                        <i class="fas fa-plus me-2"></i>
                        Registrar Movimiento
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="movementForm">
                    <div class="modal-body">
                        <input type="hidden" name="movement_type" id="movement_type">
                        
                        <div class="mb-3">
                            <label class="form-label">Producto *</label>
                            <select name="product_id" id="product_id" class="form-select" required onchange="updateProductInfo()">
                                <option value="">Seleccione un producto...</option>
                                <?php foreach ($products as $prod): ?>
                                    <option value="<?php echo $prod['id']; ?>" 
                                        data-stock="<?php echo $prod['stock_quantity'] ?? 0; ?>">
                                        <?php echo htmlspecialchars($prod['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted" id="current_stock_info"></small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Cantidad *</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" 
                                min="1" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Motivo</label>
                            <textarea name="reason" id="reason" class="form-control" rows="3" 
                                placeholder="Descripción del movimiento (opcional)"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="fas fa-save me-1"></i>
                            Guardar Movimiento
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let movementModal;

        document.addEventListener('DOMContentLoaded', function() {
            movementModal = new bootstrap.Modal(document.getElementById('movementModal'));
            
            // Manejar envío del formulario
            document.getElementById('movementForm').addEventListener('submit', function(e) {
                e.preventDefault();
                saveMovement();
            });
        });

        function openMovementModal(type) {
            const modal = document.getElementById('movementModal');
            const title = document.getElementById('movementModalTitle');
            const typeInput = document.getElementById('movement_type');
            const submitBtn = document.getElementById('submitBtn');
            
            typeInput.value = type;
            
            if (type === 'entrada') {
                title.innerHTML = '<i class="fas fa-arrow-up me-2"></i>Registrar Entrada de Stock';
                submitBtn.className = 'btn btn-success';
                modal.querySelector('.modal-header').style.background = 'linear-gradient(45deg, #28a745, #20c997)';
            } else {
                title.innerHTML = '<i class="fas fa-arrow-down me-2"></i>Registrar Salida de Stock';
                submitBtn.className = 'btn btn-danger';
                modal.querySelector('.modal-header').style.background = 'linear-gradient(45deg, #dc3545, #e83e8c)';
            }
            
            document.getElementById('movementForm').reset();
            document.getElementById('current_stock_info').textContent = '';
            movementModal.show();
        }

        function updateProductInfo() {
            const select = document.getElementById('product_id');
            const option = select.options[select.selectedIndex];
            const stock = option.dataset.stock || 0;
            const info = document.getElementById('current_stock_info');
            
            if (select.value) {
                info.textContent = `Stock actual: ${stock} unidades`;
            } else {
                info.textContent = '';
            }
        }

        function saveMovement() {
            const form = document.getElementById('movementForm');
            const formData = new FormData(form);
            const submitBtn = document.getElementById('submitBtn');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Guardando...';
            
            fetch('api/kardex.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Movimiento registrado exitosamente');
                    movementModal.hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('danger', data.message || 'Error al registrar el movimiento');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Error de conexión al servidor');
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Guardar Movimiento';
            });
        }

        function clearFilters() {
            window.location.href = 'kardex.php';
        }

        function exportKardex() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'api/kardex.php?' + params.toString();
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }
    </script>
</body>
</html>