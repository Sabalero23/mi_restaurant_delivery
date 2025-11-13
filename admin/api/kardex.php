<?php
// admin/kardex.php - VERSIÓN MEJORADA
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
    p.stock_quantity as current_stock,
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

// Calcular totales y stock actual
$total_entradas = 0;
$total_salidas = 0;
$stock_inicial = 0;
$stock_actual = 0;

foreach ($movements as $mov) {
    if ($mov['movement_type'] === 'entrada') {
        $total_entradas += $mov['quantity'];
    } else {
        $total_salidas += $mov['quantity'];
    }
}

// Obtener información del producto seleccionado
$selected_product = null;
if ($product_filter > 0) {
    $query_product = "SELECT 
        p.*,
        COALESCE(SUM(CASE WHEN sm.movement_type = 'entrada' THEN sm.quantity ELSE 0 END), 0) as total_entradas_historico,
        COALESCE(SUM(CASE WHEN sm.movement_type = 'salida' THEN sm.quantity ELSE 0 END), 0) as total_salidas_historico
        FROM products p
        LEFT JOIN stock_movements sm ON p.id = sm.product_id
        WHERE p.id = :id
        GROUP BY p.id";
    $stmt_product = $db->prepare($query_product);
    $stmt_product->execute([':id' => $product_filter]);
    $selected_product = $stmt_product->fetch(PDO::FETCH_ASSOC);
    
    if ($selected_product) {
        $stock_actual = $selected_product['stock_quantity'];
        // Calcular stock inicial (stock actual + salidas históricas - entradas históricas)
        $stock_inicial = $stock_actual - $selected_product['total_entradas_historico'] + $selected_product['total_salidas_historico'];
    }
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
    
    <?php if (file_exists('../assets/css/generate-theme.php')): ?>
        <link rel="stylesheet" href="../assets/css/generate-theme.php?v=<?php echo time(); ?>">
    <?php endif; ?>

    <style>
        /* Estilos del kardex original mantenidos */
        :root {
            --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            --dashboard-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
        }

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

        .main-content {
            margin-left: var(--dashboard-sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            background: #f8f9fa !important;
        }

        .card {
            background: #ffffff !important;
            border: none;
            border-radius: var(--border-radius-large);
            box-shadow: var(--shadow-base);
            margin-bottom: 1.5rem;
        }

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

        .stats-card.stock {
            background: linear-gradient(135deg, var(--info-color), var(--secondary-color));
        }

        .stats-card.inicial {
            background: linear-gradient(135deg, #6c757d, #495057);
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

        .product-info-box {
            background: #f8f9fa;
            border-radius: var(--border-radius-base);
            padding: 1.5rem;
            margin-bottom: 1rem;
        }

        .product-info-box h6 {
            margin-bottom: 1rem;
            color: var(--primary-color);
            font-weight: 600;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #dee2e6;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 500;
            color: #6c757d;
        }

        .info-value {
            font-weight: 600;
            color: #212529;
        }

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

        .table {
            margin-bottom: 0;
            background: #ffffff !important;
        }

        .table thead th {
            background: #f8f9fa !important;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            padding: 1rem 0.75rem;
        }

        .table tbody td {
            padding: 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .table-hover tbody tr:hover {
            background: rgba(0, 0, 0, 0.02) !important;
        }

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
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="card">
            <div class="card-body">
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
        </div>

        <!-- Información del producto seleccionado -->
        <?php if ($selected_product): ?>
        <div class="card">
            <div class="card-body">
                <div class="product-info-box">
                    <h6><i class="fas fa-box me-2"></i><?php echo htmlspecialchars($selected_product['name']); ?></h6>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Stock Inicial:</span>
                                <span class="info-value"><?php echo number_format($stock_inicial, 0, ',', '.'); ?> unidades</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Entradas:</span>
                                <span class="info-value text-success">+<?php echo number_format($selected_product['total_entradas_historico'], 0, ',', '.'); ?> unidades</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Total Salidas:</span>
                                <span class="info-value text-danger">-<?php echo number_format($selected_product['total_salidas_historico'], 0, ',', '.'); ?> unidades</span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Stock Actual:</span>
                                <span class="info-value">
                                    <span class="stock-indicator <?php 
                                        echo $stock_actual <= $selected_product['low_stock_alert'] ? 'low' : 
                                            ($stock_actual <= $selected_product['low_stock_alert'] * 2 ? 'medium' : 'high'); 
                                    ?>">
                                        <?php echo number_format($stock_actual, 0, ',', '.'); ?> unidades
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-item">
                                <span class="info-label">Precio de Venta:</span>
                                <span class="info-value">$<?php echo number_format($selected_product['price'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Costo:</span>
                                <span class="info-value">$<?php echo number_format($selected_product['cost'], 2, ',', '.'); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Margen:</span>
                                <span class="info-value">
                                    <?php 
                                    $margen = $selected_product['cost'] > 0 ? 
                                        (($selected_product['price'] - $selected_product['cost']) / $selected_product['cost']) * 100 : 0;
                                    echo number_format($margen, 1, ',', '.'); 
                                    ?>%
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Alerta de Stock Bajo:</span>
                                <span class="info-value"><?php echo $selected_product['low_stock_alert']; ?> unidades</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas del producto -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stats-card inicial">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>STOCK INICIAL</p>
                            <h3><?php echo number_format($stock_inicial, 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-warehouse fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card entrada">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>ENTRADAS</p>
                            <h3>+<?php echo number_format($total_entradas, 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-arrow-up fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card salida">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>SALIDAS</p>
                            <h3>-<?php echo number_format($total_salidas, 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-arrow-down fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card stock">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>STOCK ACTUAL</p>
                            <h3><?php echo number_format($stock_actual, 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-boxes fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Estadísticas generales -->
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
                <div class="stats-card stock">
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
        <?php endif; ?>

        <!-- Filtros -->
        <div class="card">
            <div class="card-body">
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
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                        <h5>No hay movimientos registrados</h5>
                        <p class="text-muted">No se encontraron movimientos con los filtros seleccionados.</p>
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
                                    <th class="text-center d-none d-xl-table-cell">STOCK ACTUAL</th>
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
                                    <td class="text-center d-none d-xl-table-cell">
                                        <span class="stock-indicator <?php 
                                            $current = $mov['current_stock'];
                                            $alert = 10; // valor por defecto
                                            echo $current <= $alert ? 'low' : ($current <= $alert * 2 ? 'medium' : 'high'); 
                                        ?>">
                                            <?php echo $mov['current_stock']; ?>
                                        </span>
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
                                        data-stock="<?php echo $prod['stock_quantity'] ?? 0; ?>"
                                        data-alert="<?php echo $prod['low_stock_alert'] ?? 10; ?>">
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
                            <small class="text-danger" id="quantity_warning" style="display: none;">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Advertencia: Esta operación dejará el stock en negativo
                            </small>
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

            // Validar cantidad en tiempo real
            document.getElementById('quantity').addEventListener('input', function() {
                validateQuantity();
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
            document.getElementById('quantity_warning').style.display = 'none';
            movementModal.show();
        }

        function updateProductInfo() {
            const select = document.getElementById('product_id');
            const option = select.options[select.selectedIndex];
            const stock = parseInt(option.dataset.stock || 0);
            const alert = parseInt(option.dataset.alert || 10);
            const info = document.getElementById('current_stock_info');
            
            if (select.value) {
                let stockClass = stock <= alert ? 'danger' : (stock <= alert * 2 ? 'warning' : 'success');
                info.innerHTML = `Stock actual: <strong class="text-${stockClass}">${stock} unidades</strong>`;
                
                // Validar cantidad si ya está ingresada
                validateQuantity();
            } else {
                info.textContent = '';
            }
        }

        function validateQuantity() {
            const select = document.getElementById('product_id');
            const option = select.options[select.selectedIndex];
            const stock = parseInt(option.dataset.stock || 0);
            const quantity = parseInt(document.getElementById('quantity').value || 0);
            const type = document.getElementById('movement_type').value;
            const warning = document.getElementById('quantity_warning');
            
            if (type === 'salida' && quantity > 0 && select.value) {
                if (stock - quantity < 0) {
                    warning.style.display = 'block';
                    warning.innerHTML = `<i class="fas fa-exclamation-triangle"></i> 
                        Advertencia: Esta operación dejará el stock en ${stock - quantity} unidades (negativo)`;
                } else if (stock - quantity === 0) {
                    warning.style.display = 'block';
                    warning.className = 'text-warning';
                    warning.innerHTML = `<i class="fas fa-info-circle"></i> 
                        El stock quedará en 0 unidades`;
                } else {
                    warning.style.display = 'none';
                }
            } else {
                warning.style.display = 'none';
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
                const type = document.getElementById('movement_type').value;
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