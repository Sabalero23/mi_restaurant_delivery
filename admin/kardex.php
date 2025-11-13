<?php
// admin/kardex.php - VERSIÓN CON PESTAÑAS CORREGIDA
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

// Pestaña activa
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'stock-actual';

// Filtros
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$category_filter = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

// Obtener categorías
try {
    $query_categories = "SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name";
    $stmt_categories = $db->prepare($query_categories);
    $stmt_categories->execute();
    $categories = $stmt_categories->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}

// Obtener datos según la pestaña
$data = [];
$totales = [
    'productos' => 0,
    'stock_total' => 0,
    'valor_total' => 0
];

try {
    switch ($active_tab) {
        case 'stock-actual':
            // Stock actual = Stock inicial + entradas - salidas
            $query = "
                SELECT 
                    p.id,
                    p.name,
                    p.initial_stock,
                    p.stock_quantity as current_stock,
                    p.low_stock_alert,
                    p.price,
                    p.cost,
                    c.name as category_name,
                    c.id as category_id,
                    COALESCE(SUM(CASE WHEN sm.movement_type IN ('entrada', 'compra') THEN sm.quantity ELSE 0 END), 0) as total_entradas,
                    COALESCE(SUM(CASE WHEN sm.movement_type IN ('salida', 'venta') THEN sm.quantity ELSE 0 END), 0) as total_salidas,
                    (p.initial_stock + COALESCE(SUM(CASE WHEN sm.movement_type IN ('entrada', 'compra') THEN sm.quantity ELSE 0 END), 0) - 
                     COALESCE(SUM(CASE WHEN sm.movement_type IN ('salida', 'venta') THEN sm.quantity ELSE 0 END), 0)) as stock_calculado
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN stock_movements sm ON p.id = sm.product_id
                WHERE p.track_inventory = 1 AND p.is_active = 1
            ";
            
            if ($category_filter > 0) {
                $query .= " AND p.category_id = :category_id";
            }
            
            $query .= " GROUP BY p.id, p.name, p.initial_stock, p.stock_quantity, p.low_stock_alert, p.price, p.cost, c.name, c.id ORDER BY p.name";
            
            $stmt = $db->prepare($query);
            if ($category_filter > 0) {
                $stmt->execute([':category_id' => $category_filter]);
            } else {
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($data as $item) {
                $totales['productos']++;
                $totales['stock_total'] += floatval($item['stock_calculado']);
                $totales['valor_total'] += floatval($item['stock_calculado']) * floatval($item['cost']);
            }
            break;

        case 'stock-inicial':
            // Stock inicial de la tabla products
            $query = "
                SELECT 
                    p.id,
                    p.name,
                    p.initial_stock,
                    p.stock_quantity as current_stock,
                    p.low_stock_alert,
                    p.price,
                    p.cost,
                    c.name as category_name,
                    c.id as category_id
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.track_inventory = 1 AND p.is_active = 1
            ";
            
            if ($category_filter > 0) {
                $query .= " AND p.category_id = :category_id";
            }
            
            $query .= " ORDER BY p.name";
            
            $stmt = $db->prepare($query);
            if ($category_filter > 0) {
                $stmt->execute([':category_id' => $category_filter]);
            } else {
                $stmt->execute();
            }
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($data as $item) {
                $totales['productos']++;
                $totales['stock_total'] += floatval($item['initial_stock']);
                $totales['valor_total'] += floatval($item['initial_stock']) * floatval($item['cost']);
            }
            break;

        case 'ingresos':
            // Ingresos por compras (entradas y compras del sistema)
            $query = "
                SELECT 
                    p.id,
                    p.name,
                    p.cost,
                    c.name as category_name,
                    COALESCE(SUM(CASE WHEN sm.movement_type IN ('entrada', 'compra') 
                        AND DATE(sm.created_at) BETWEEN :date_from AND :date_to 
                        THEN sm.quantity ELSE 0 END), 0) as total_ingresos,
                    COALESCE(SUM(CASE WHEN sm.movement_type IN ('entrada', 'compra') 
                        AND DATE(sm.created_at) BETWEEN :date_from AND :date_to 
                        THEN sm.quantity * p.cost ELSE 0 END), 0) as valor_ingresos
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN stock_movements sm ON p.id = sm.product_id
                WHERE p.track_inventory = 1 AND p.is_active = 1
            ";
            
            if ($category_filter > 0) {
                $query .= " AND p.category_id = :category_id";
            }
            
            $query .= " GROUP BY p.id, p.name, p.cost, c.name HAVING total_ingresos > 0 ORDER BY total_ingresos DESC";
            
            $stmt = $db->prepare($query);
            $params = [':date_from' => $date_from, ':date_to' => $date_to];
            if ($category_filter > 0) {
                $params[':category_id'] = $category_filter;
            }
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($data as $item) {
                $totales['productos']++;
                $totales['stock_total'] += floatval($item['total_ingresos']);
                $totales['valor_total'] += floatval($item['valor_ingresos']);
            }
            break;

        case 'egresos':
            // Egresos por órdenes (salidas y ventas)
            $query = "
                SELECT 
                    p.id,
                    p.name,
                    p.price,
                    c.name as category_name,
                    COALESCE(SUM(CASE WHEN sm.movement_type IN ('salida', 'venta') 
                        AND DATE(sm.created_at) BETWEEN :date_from AND :date_to 
                        THEN sm.quantity ELSE 0 END), 0) as total_egresos,
                    COALESCE(SUM(CASE WHEN sm.movement_type IN ('salida', 'venta') 
                        AND DATE(sm.created_at) BETWEEN :date_from AND :date_to 
                        THEN sm.quantity * p.price ELSE 0 END), 0) as valor_egresos
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                LEFT JOIN stock_movements sm ON p.id = sm.product_id
                WHERE p.track_inventory = 1 AND p.is_active = 1
            ";
            
            if ($category_filter > 0) {
                $query .= " AND p.category_id = :category_id";
            }
            
            $query .= " GROUP BY p.id, p.name, p.price, c.name HAVING total_egresos > 0 ORDER BY total_egresos DESC";
            
            $stmt = $db->prepare($query);
            $params = [':date_from' => $date_from, ':date_to' => $date_to];
            if ($category_filter > 0) {
                $params[':category_id'] = $category_filter;
            }
            $stmt->execute($params);
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($data as $item) {
                $totales['productos']++;
                $totales['stock_total'] += floatval($item['total_egresos']);
                $totales['valor_total'] += floatval($item['valor_egresos']);
            }
            break;
    }
} catch (Exception $e) {
    $data = [];
    error_log("Error en kardex.php: " . $e->getMessage());
}


// Obtener productos para el modal
$products = [];
try {
    $query_products = "SELECT id, name, stock_quantity, low_stock_alert FROM products WHERE track_inventory = 1 AND is_active = 1 ORDER BY name";
    $stmt_products = $db->prepare($query_products);
    $stmt_products->execute();
    $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $products = [];
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
    <title>Kardex de Inventario - <?php echo htmlspecialchars($restaurant_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
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

        .card {
            background: #ffffff !important;
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .stats-card {
            background: linear-gradient(135deg, <?php echo $current_theme['primary_color']; ?>, <?php echo $current_theme['secondary_color']; ?>);
            color: white !important;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            transition: transform 0.3s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
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

        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 1.5rem;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 600;
            padding: 1rem 1.5rem;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:not(.active):hover {
    background: rgba(102, 126, 234, 0.05);
    cursor: pointer;
}

        .nav-tabs .nav-link:hover {
            color: <?php echo $current_theme['primary_color']; ?>;
            border-color: transparent;
        }

        .nav-tabs .nav-link.active {
            color: <?php echo $current_theme['primary_color']; ?>;
            border-bottom: 3px solid <?php echo $current_theme['primary_color']; ?>;
            background: transparent;
        }

        .nav-tabs .nav-link i {
            margin-right: 0.5rem;
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
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .table tbody td {
            padding: 0.75rem;
            vertical-align: middle;
            border-bottom: 1px solid #dee2e6;
        }

        .table-hover tbody tr:hover {
            background: rgba(102, 126, 234, 0.05) !important;
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

        .badge-category {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 500;
        }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 5rem;
            }

            .nav-tabs .nav-link {
                padding: 0.75rem 1rem;
                font-size: 0.9rem;
            }
        }

        @media (max-width: 767.98px) {
            .nav-tabs .nav-link {
                padding: 0.5rem 0.75rem;
                font-size: 0.85rem;
            }

            .nav-tabs .nav-link span {
                display: none;
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
                        <p class="text-muted mb-0">Control completo de inventario por productos</p>
                    </div>
                    <div class="d-flex gap-2 mt-2 mt-md-0">
                        <button class="btn btn-success" onclick="openMovementModal('entrada')">
                            <i class="fas fa-plus me-1"></i>
                            <span class="d-none d-md-inline">Nueva Entrada</span>
                        </button>
                        <button class="btn btn-danger" onclick="openMovementModal('salida')">
                            <i class="fas fa-minus me-1"></i>
                            <span class="d-none d-md-inline">Nueva Salida</span>
                        </button>
                        <button class="btn btn-secondary" onclick="exportData()">
                            <i class="fas fa-file-excel me-1"></i>
                            <span class="d-none d-md-inline">Exportar</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>PRODUCTOS</p>
                            <h3><?php echo number_format($totales['productos'], 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-box fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>UNIDADES TOTALES</p>
                            <h3><?php echo number_format($totales['stock_total'], 0, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-cubes fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card" style="background: linear-gradient(135deg, #17a2b8, #138496);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>VALOR TOTAL</p>
                            <h3>$<?php echo number_format($totales['valor_total'], 2, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-dollar-sign fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($active_tab); ?>">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Categoría</label>
                            <select name="category_id" class="form-select" onchange="document.getElementById('filterForm').submit()">
                                <option value="0">Todas las categorías</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" 
                                        <?php echo $category_filter == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if ($active_tab == 'ingresos' || $active_tab == 'egresos'): ?>
                        <div class="col-md-3">
                            <label class="form-label">Desde</label>
                            <input type="date" name="date_from" class="form-control" 
                                value="<?php echo htmlspecialchars($date_from); ?>" 
                                onchange="document.getElementById('filterForm').submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="date_to" class="form-control" 
                                value="<?php echo htmlspecialchars($date_to); ?>"
                                onchange="document.getElementById('filterForm').submit()">
                        </div>
                        <?php endif; ?>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-secondary w-100" onclick="clearFilters()" title="Limpiar filtros">
                                <i class="fas fa-times me-1"></i>
                                Limpiar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Pestañas -->
        <div class="card">
            <div class="card-body">
                <ul class="nav nav-tabs" id="kardexTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $active_tab == 'stock-actual' ? 'active' : ''; ?>" 
                type="button" onclick="changeTab('stock-actual')">
            <i class="fas fa-boxes"></i>
            <span>Stock Actual</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $active_tab == 'stock-inicial' ? 'active' : ''; ?>" 
                type="button" onclick="changeTab('stock-inicial')">
            <i class="fas fa-warehouse"></i>
            <span>Stock Inicial</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $active_tab == 'ingresos' ? 'active' : ''; ?>" 
                type="button" onclick="changeTab('ingresos')">
            <i class="fas fa-arrow-up"></i>
            <span>Ingresos</span>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?php echo $active_tab == 'egresos' ? 'active' : ''; ?>" 
                type="button" onclick="changeTab('egresos')">
            <i class="fas fa-arrow-down"></i>
            <span>Egresos</span>
        </button>
    </li>
</ul>

                <div class="tab-content mt-3">
                    <?php if (empty($data)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5>No hay datos disponibles</h5>
                            <p class="text-muted">No se encontraron registros con los filtros seleccionados.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <?php if ($active_tab == 'stock-actual'): ?>
                                <!-- Tabla Stock Actual -->
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>PRODUCTO</th>
                                            <th>CATEGORÍA</th>
                                            <th class="text-center">STOCK ACTUAL</th>
                                            <th class="text-center">ALERTA</th>
                                            <th class="text-end d-none d-md-table-cell">COSTO UNIT.</th>
                                            <th class="text-end d-none d-lg-table-cell">VALOR TOTAL</th>
                                            <th class="text-center">ESTADO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data as $item): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                            <td>
                                                <span class="badge badge-category bg-secondary">
                                                    <?php echo htmlspecialchars($item['category_name'] ?? 'Sin categoría'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
    <strong style="font-size: 1.1rem;">
        <?php echo number_format($item['stock_calculado'], 0, ',', '.'); ?>
    </strong>
</td>
                                            <td class="text-center">
                                                <?php echo number_format($item['low_stock_alert'], 0, ',', '.'); ?>
                                            </td>
                                            <td class="text-end d-none d-md-table-cell">
                                                $<?php echo number_format($item['cost'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="text-end d-none d-lg-table-cell">
                                                <strong>$<?php echo number_format($item['current_stock'] * $item['cost'], 2, ',', '.'); ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <span class="stock-indicator <?php 
                                                    echo $item['current_stock'] <= $item['low_stock_alert'] ? 'low' : 
                                                        ($item['current_stock'] <= $item['low_stock_alert'] * 2 ? 'medium' : 'high'); 
                                                ?>">
                                                    <?php 
                                                    if ($item['current_stock'] <= $item['low_stock_alert']) {
                                                        echo 'Bajo';
                                                    } elseif ($item['current_stock'] <= $item['low_stock_alert'] * 2) {
                                                        echo 'Medio';
                                                    } else {
                                                        echo 'Normal';
                                                    }
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                            <?php elseif ($active_tab == 'stock-inicial'): ?>
                                <!-- Tabla Stock Inicial -->
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>PRODUCTO</th>
                                            <th>CATEGORÍA</th>
                                            <th class="text-center">STOCK INICIAL</th>
                                            <th class="text-end d-none d-md-table-cell">COSTO UNIT.</th>
                                            <th class="text-end d-none d-lg-table-cell">VALOR INICIAL</th>
                                            <th class="text-center">STOCK ACTUAL</th>
                                            <th class="text-center d-none d-xl-table-cell">DIFERENCIA</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data as $item): ?>
                                        <?php $diferencia = $item['current_stock'] - $item['initial_stock']; ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                            <td>
                                                <span class="badge badge-category bg-secondary">
                                                    <?php echo htmlspecialchars($item['category_name'] ?? 'Sin categoría'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <strong style="font-size: 1.1rem;">
                                                    <?php echo number_format($item['initial_stock'], 0, ',', '.'); ?>
                                                </strong>
                                            </td>
                                            <td class="text-end d-none d-md-table-cell">
                                                $<?php echo number_format($item['cost'], 2, ',', '.'); ?>
                                            </td>
                                            <td class="text-end d-none d-lg-table-cell">
                                                <strong>$<?php echo number_format($item['initial_stock'] * $item['cost'], 2, ',', '.'); ?></strong>
                                            </td>
                                            <td class="text-center">
                                                <?php echo number_format($item['current_stock'], 0, ',', '.'); ?>
                                            </td>
                                            <td class="text-center d-none d-xl-table-cell">
                                                <span class="badge <?php echo $diferencia >= 0 ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo $diferencia >= 0 ? '+' : ''; ?><?php echo number_format($diferencia, 0, ',', '.'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                            <?php elseif ($active_tab == 'ingresos'): ?>
                                <!-- Tabla Ingresos -->
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>PRODUCTO</th>
                                            <th>CATEGORÍA</th>
                                            <th class="text-center">TOTAL INGRESOS</th>
                                            <th class="text-end d-none d-lg-table-cell">VALOR TOTAL</th>
                                            <th class="text-center d-none d-md-table-cell">PERÍODO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data as $item): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                            <td>
                                                <span class="badge badge-category bg-secondary">
                                                    <?php echo htmlspecialchars($item['category_name'] ?? 'Sin categoría'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-success" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                                    <i class="fas fa-arrow-up me-1"></i>
                                                    <?php echo number_format($item['total_ingresos'], 0, ',', '.'); ?>
                                                </span>
                                            </td>
                                            <td class="text-end d-none d-lg-table-cell">
                                                <strong>$<?php echo number_format($item['valor_ingresos'], 2, ',', '.'); ?></strong>
                                            </td>
                                            <td class="text-center d-none d-md-table-cell">
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($date_from)); ?> - 
                                                    <?php echo date('d/m/Y', strtotime($date_to)); ?>
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>

                            <?php elseif ($active_tab == 'egresos'): ?>
                                <!-- Tabla Egresos -->
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>PRODUCTO</th>
                                            <th>CATEGORÍA</th>
                                            <th class="text-center">TOTAL EGRESOS</th>
                                            <th class="text-end d-none d-lg-table-cell">VALOR TOTAL</th>
                                            <th class="text-center d-none d-md-table-cell">PERÍODO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($data as $item): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                            <td>
                                                <span class="badge badge-category bg-secondary">
                                                    <?php echo htmlspecialchars($item['category_name'] ?? 'Sin categoría'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-danger" style="font-size: 1rem; padding: 0.5rem 1rem;">
                                                    <i class="fas fa-arrow-down me-1"></i>
                                                    <?php echo number_format($item['total_egresos'], 0, ',', '.'); ?>
                                                </span>
                                            </td>
                                            <td class="text-end d-none d-lg-table-cell">
                                                <strong>$<?php echo number_format($item['valor_egresos'], 2, ',', '.'); ?></strong>
                                            </td>
                                            <td class="text-center d-none d-md-table-cell">
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($date_from)); ?> - 
                                                    <?php echo date('d/m/Y', strtotime($date_to)); ?>
                                                </small>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
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
                                <?php
                                // Obtener productos para el modal
                                $query_products = "SELECT id, name, stock_quantity, low_stock_alert FROM products WHERE track_inventory = 1 AND is_active = 1 ORDER BY name";
                                $stmt_products = $db->prepare($query_products);
                                $stmt_products->execute();
                                $products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($products as $prod): ?>
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
                                min="1" step="1" required>
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
                modal.querySelector('.modal-header').style.color = 'white';
            } else {
                title.innerHTML = '<i class="fas fa-arrow-down me-2"></i>Registrar Salida de Stock';
                submitBtn.className = 'btn btn-danger';
                modal.querySelector('.modal-header').style.background = 'linear-gradient(45deg, #dc3545, #e83e8c)';
                modal.querySelector('.modal-header').style.color = 'white';
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
                    warning.className = 'text-danger';
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
            const tab = '<?php echo $active_tab; ?>';
            window.location.href = 'kardex.php?tab=' + tab;
        }

        function exportData() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'api/kardex_export.php?' + params.toString();
        }
        
        function changeTab(tab) {
    // Actualizar URL sin recargar
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    
    // Mantener filtros existentes
    const categoryId = new URLSearchParams(window.location.search).get('category_id');
    if (categoryId) {
        url.searchParams.set('category_id', categoryId);
    }
    
    // Si es pestaña con fechas, mantener fechas
    if (tab === 'ingresos' || tab === 'egresos') {
        const dateFrom = new URLSearchParams(window.location.search).get('date_from');
        const dateTo = new URLSearchParams(window.location.search).get('date_to');
        if (dateFrom) url.searchParams.set('date_from', dateFrom);
        if (dateTo) url.searchParams.set('date_to', dateTo);
    }
    
    // Recargar página con nuevos parámetros
    window.location.href = url.toString();
}

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.style.minWidth = '300px';
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