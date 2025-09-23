<?php
// admin/dashboard.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Order.php';
require_once '../models/Table.php';
require_once '../models/Product.php';

$auth = new Auth();
$auth->requireLogin();

// Redirección automática basada en roles
$role = $_SESSION['role_name'];

switch ($role) {
    case 'delivery':
        if ($auth->hasPermission('delivery')) {
            header("Location: delivery.php");
            exit;
        }
        break;
        
    case 'mesero':
        if ($auth->hasPermission('tables')) {
            header("Location: tables.php");
            exit;
        }
        break;
        
    case 'cocina':
        if ($auth->hasPermission('kitchen')) {
            header("Location: kitchen.php");
            exit;
        }
        break;
        
    case 'administrador':
    case 'gerente':
    case 'mostrador':
        // Estos roles permanecen en el dashboard
        break;
        
    default:
        // Para cualquier otro rol no especificado, mantener en dashboard
        break;
}

$orderModel = new Order();
$tableModel = new Table();
$productModel = new Product();

// Get user role for dashboard customization
$role = $_SESSION['role_name'];
$user_name = $_SESSION['full_name'];

// Get statistics based on role
$stats = [];
if ($auth->hasPermission('orders')) {
    $stats['pending_orders'] = count($orderModel->getByStatus('pending'));
    $stats['preparing_orders'] = count($orderModel->getByStatus('preparing'));
    $stats['ready_orders'] = count($orderModel->getByStatus('ready'));
}

if ($auth->hasPermission('tables')) {
    $tables = $tableModel->getAll();
    $stats['occupied_tables'] = count(array_filter($tables, fn($t) => $t['status'] === 'occupied'));
    $stats['available_tables'] = count(array_filter($tables, fn($t) => $t['status'] === 'available'));
}

if ($auth->hasPermission('delivery')) {
    $stats['pending_deliveries'] = count($orderModel->getByStatus('ready', 'delivery'));
}

// Obtener estadísticas de pedidos online si tiene permisos
$online_stats = [];
if ($auth->hasPermission('online_orders')) {
    $database = new Database();
    $db = $database->getConnection();
    
    $online_query = "SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_online,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_online,
        COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_online,
        COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_online
        FROM online_orders 
        WHERE DATE(created_at) = CURDATE()";
    
    $online_stmt = $db->prepare($online_query);
    $online_stmt->execute();
    $online_stats = $online_stmt->fetch();
}

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo $restaurant_name; ?></title>
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
    color: #212529 !important;
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
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

/* Statistics cards usando variables del tema */
.stat-card {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    padding: 1.5rem;
    box-shadow: var(--shadow-base);
    transition: transform var(--transition-base);
    height: 100%;
}


.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--border-radius-large);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--text-white) !important;
    flex-shrink: 0;
}

.bg-primary-gradient { 
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)) !important; 
}
.bg-success-gradient { 
    background: linear-gradient(45deg, var(--success-color), #a8e6cf) !important; 
}
.bg-warning-gradient { 
    background: linear-gradient(45deg, var(--warning-color), var(--accent-color)) !important; 
}
.bg-info-gradient { 
    background: linear-gradient(45deg, var(--info-color), #00f2fe) !important; 
}
.bg-online-gradient { 
    background: linear-gradient(45deg, var(--accent-color), var(--warning-color)) !important; 
}

.page-header {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-base);
}


/* Notification styles */
.notification-alert {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
    animation: slideInRight 0.5s ease-out;
    box-shadow: var(--shadow-large);
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.notification-shake {
    animation: shake 0.5s ease-in-out;
}

.pulsing-badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

.urgent-notification {
    border: 3px solid var(--danger-color) !important;
    background: linear-gradient(45deg, var(--danger-color), var(--warning-color)) !important;
    color: var(--text-white) !important;
}

/* Tabla con colores claros */
.table {
    background: #ffffff !important;
    color: #212529 !important;
}

.table th,
.table td {
    color: #212529 !important;
    border-bottom: 1px solid #dee2e6;
}

.table th {
    background: #f8f9fa !important;
    color: #212529 !important;
}

.table-hover tbody tr:hover {
    background: rgba(0, 0, 0, 0.02) !important;
}

/* Text colors forzados */
.text-muted {
    color: #6c757d !important;
}

h1, h2, h3, h4, h5, h6 {
    color: #212529 !important;
}

p {
    color: #212529 !important;
}

/* Mini tarjetas de mesa para dashboard */
.table-mini-card {
    border: 2px solid;
    border-radius: var(--border-radius-base);
    padding: 8px;
    transition: var(--transition-base);
    cursor: pointer;
    position: relative;
    display: flex;
    align-items: center;
    min-height: 60px;
    box-shadow: var(--shadow-small);
}

.table-mini-card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-base);
}

.table-mini-card.table-available {
    border-color: var(--success-color);
    background: linear-gradient(135deg, var(--success-color), #20c997);
    color: var(--text-white) !important;
}

.table-mini-card.table-occupied {
    border-color: var(--danger-color);
    background: linear-gradient(135deg, var(--danger-color), #e83e8c);
    color: var(--text-white) !important;
}

.table-mini-card.table-reserved {
    border-color: var(--warning-color);
    background: linear-gradient(135deg, var(--warning-color), #fd7e14);
    color: var(--text-primary) !important;
}

.table-mini-card.table-maintenance {
    border-color: var(--text-secondary);
    background: linear-gradient(135deg, var(--text-secondary), #495057);
    color: var(--text-white) !important;
}

/* Visual de mesa miniatura */
.table-mini-visual {
    position: relative;
    width: 32px;
    height: 32px;
    margin-right: 8px;
    flex-shrink: 0;
}

.table-mini-surface {
    width: 16px;
    height: 16px;
    background: rgba(255, 255, 255, 0.3);
    border: 2px solid rgba(255, 255, 255, 0.8);
    border-radius: 50%;
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    z-index: 2;
}

.chair-mini {
    width: 6px;
    height: 4px;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 1px;
    position: absolute;
}

.chair-mini.chair-top { 
    top: 2px; 
    left: 50%; 
    transform: translateX(-50%); 
}

.chair-mini.chair-right { 
    top: 50%; 
    right: 2px; 
    transform: translateY(-50%) rotate(90deg); 
}

.chair-mini.chair-bottom { 
    bottom: 2px; 
    left: 50%; 
    transform: translateX(-50%) rotate(180deg); 
}

.chair-mini.chair-left { 
    top: 50%; 
    left: 2px; 
    transform: translateY(-50%) rotate(270deg); 
}

.chair-mini.chair-top-left { 
    top: 6px; 
    left: 6px; 
    transform: rotate(-45deg); 
}

.chair-mini.chair-top-right { 
    top: 6px; 
    right: 6px; 
    transform: rotate(45deg); 
}

.chair-mini.chair-bottom-left { 
    bottom: 6px; 
    left: 6px; 
    transform: rotate(-135deg); 
}

.chair-mini.chair-bottom-right { 
    bottom: 6px; 
    right: 6px; 
    transform: rotate(135deg); 
}

.table-mini-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.table-mini-number {
    font-size: 0.9rem;
    font-weight: bold;
    line-height: 1.1;
    margin-bottom: 2px;
}

.table-mini-capacity {
    font-size: 0.7rem;
    opacity: 0.9;
    margin-bottom: 3px;
}

.table-mini-status {
    font-size: 0.65rem;
    padding: 1px 4px;
    align-self: flex-start;
}

.table-mini-card.has-orders {
    animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
    0%, 100% { 
        box-shadow: var(--shadow-small); 
    }
    50% { 
        box-shadow: 0 4px 12px rgba(255, 255, 255, 0.3), var(--shadow-small); 
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

    .notification-alert {
        position: fixed;
        top: 80px;
        left: 10px;
        right: 10px;
        max-width: none;
    }

    .table-mini-card {
        width: 140px;
        height: 140px;
        padding: 10px;
    }
    
    .table-mini-visual {
        width: 40px;
        height: 40px;
        margin-bottom: 6px;
    }
    
    .table-mini-surface {
        width: 24px;
        height: 24px;
    }
    
    .chair-mini {
        width: 10px;
        height: 6px;
    }
    
    .table-mini-number {
        font-size: 1rem;
    }
    
    .table-mini-capacity {
        font-size: 0.75rem;
    }
    
    .table-mini-status {
        font-size: 0.65rem;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 0.5rem;
        padding-top: 4.5rem;
    }

    .stat-card {
        padding: 0.75rem;
    }

    .stat-card .d-flex {
        flex-direction: column;
        text-align: center;
    }

    .stat-card .stat-icon {
        margin: 0 auto 0.5rem auto;
    }

    .page-header {
        padding: 0.75rem;
    }

    .page-header .d-flex {
        flex-direction: column;
        text-align: center;
    }

    .stat-card h3 {
        font-size: 1.5rem;
    }

    .sidebar {
        padding: 1rem;
    }

    .sidebar .nav-link {
        padding: 0.5rem 0.75rem;
    }

    .table-mini-card {
        width: 120px;
        height: 120px;
        padding: 8px;
    }
    
    .table-mini-visual {
        width: 36px;
        height: 36px;
        margin-bottom: 4px;
    }
    
    .table-mini-surface {
        width: 20px;
        height: 20px;
    }
    
    .chair-mini {
        width: 8px;
        height: 5px;
    }
    
    .table-mini-number {
        font-size: 0.9rem;
    }
    
    .table-mini-capacity {
        font-size: 0.7rem;
    }
    
    .table-mini-status {
        font-size: 0.6rem;
        padding: 1px 4px;
    }
}

/* Badges con variables del tema */
.badge.bg-success { 
    background: linear-gradient(45deg, var(--success-color), #20c997) !important; 
}

.badge.bg-warning { 
    background: linear-gradient(45deg, var(--warning-color), #fd7e14) !important; 
    color: var(--text-primary) !important;
}

.badge.bg-info { 
    background: linear-gradient(45deg, var(--info-color), var(--secondary-color)) !important; 
}

/* Responsive para la columna de pago */
@media (max-width: 768px) {
    .payment-column {
        display: none;
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
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">Dashboard</h2>
                    <p class="text-muted mb-0">Bienvenido de vuelta, <?php echo explode(' ', $user_name)[0]; ?></p>
                </div>
                <div class="text-muted d-none d-lg-block">
                    <i class="fas fa-clock me-1"></i>
                    <?php echo date('d/m/Y H:i'); ?>
                </div>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row g-2 g-md-2 mb-2">
            <?php if (isset($stats['pending_orders'])): ?>
                <div class="col-6 col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-primary-gradient me-3">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $stats['pending_orders']; ?></h3>
                                <p class="text-muted mb-0 small">Órdenes Pendientes</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($online_stats['pending_online'])): ?>
                <div class="col-6 col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-online-gradient me-3">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div>
                                <h3 class="mb-0" id="pending-online-count"><?php echo $online_stats['pending_online']; ?></h3>
                                <p class="text-muted mb-0 small">Pedidos Online Pendientes</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($stats['preparing_orders'])): ?>
                <div class="col-6 col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-warning-gradient me-3">
                                <i class="fas fa-fire"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $stats['preparing_orders']; ?></h3>
                                <p class="text-muted mb-0 small">En Preparación</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($stats['occupied_tables'])): ?>
                <div class="col-6 col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-success-gradient me-3">
                                <i class="fas fa-table"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $stats['occupied_tables']; ?>/<?php echo $stats['occupied_tables'] + $stats['available_tables']; ?></h3>
                                <p class="text-muted mb-0 small">Mesas Ocupadas</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (isset($stats['pending_deliveries'])): ?>
                <div class="col-6 col-md-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon bg-info-gradient me-3">
                                <i class="fas fa-motorcycle"></i>
                            </div>
                            <div>
                                <h3 class="mb-0"><?php echo $stats['pending_deliveries']; ?></h3>
                                <p class="text-muted mb-0 small">Entregas Pendientes</p>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions based on Role -->
        <div class="row g-3 g-md-4">
            <!-- Orders Panel for roles with order permissions -->
            <?php if ($auth->hasPermission('orders')): ?>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-receipt me-2"></i>
                                Órdenes Recientes
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Orden</th>
                                            <th>Tipo</th>
                                            <th class="d-none d-md-table-cell">Cliente</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th class="payment-column">Pago</th>
                                            <th>Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recent-orders">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">
                                                <div class="loading-spinner me-2"></div>
                                                Cargando órdenes...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Kitchen Panel for kitchen role -->
            <?php if ($auth->hasPermission('kitchen')): ?>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-fire me-2"></i>
                                Órdenes para Cocina
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="kitchen-orders" class="row g-3">
                                <div class="col-12 text-center text-muted py-4">
                                    <div class="loading-spinner me-2"></div>
                                    Cargando órdenes de cocina...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Tables Panel for roles with table permissions -->
            <?php if ($auth->hasPermission('tables')): ?>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-table me-2"></i>
                                Estado de Mesas
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="tables-status">
                                <div class="text-center text-muted py-3">
                                    <div class="loading-spinner me-2"></div>
                                    Cargando mesas...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Delivery Panel for delivery role -->
            <?php if ($auth->hasPermission('delivery')): ?>
                <div class="col-lg-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-motorcycle me-2"></i>
                                Entregas Pendientes
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="delivery-orders" class="row g-3">
                                <div class="col-12 text-center text-muted py-4">
                                    <div class="loading-spinner me-2"></div>
                                    Cargando entregas...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Modal Notificación Mesa -->
    <div class="modal fade" id="callModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">🚨 Llamada de Mesa</h5>
                </div>
                <div class="modal-body">
                    <h3 id="mesaNumber"></h3>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-success" onclick="attendCall()">Atender</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Notificación Pedido Online -->
    <div class="modal fade" id="onlineOrderModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content text-center urgent-notification">
                <div class="modal-header">
                    <h5 class="modal-title">
                        🌐 ¡NUEVO PEDIDO ONLINE!
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <i class="fas fa-bell fa-3x text-warning mb-3"></i>
                        <h4 id="onlineOrderInfo">Hay pedidos online pendientes</h4>
                        <p class="text-muted" id="onlineOrderDetails">Revise los pedidos en la sección de órdenes online</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-center">
                    <button class="btn btn-success me-2" onclick="goToOnlineOrders()">
                        <i class="fas fa-eye me-1"></i>Ver Pedidos
                    </button>
                    <button class="btn btn-secondary" onclick="dismissOnlineAlert()">
                        <i class="fas fa-times me-1"></i>Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // JAVASCRIPT COMPLETO CORREGIDO PARA DASHBOARD
// Reemplazar todo el JavaScript del dashboard original con este código

let currentCallId = null;
let lastOnlineOrdersCount = <?php echo isset($online_stats['pending_online']) ? $online_stats['pending_online'] : 0; ?>;
let audioContext = null;
let hasPermissionOnlineOrders = <?php echo $auth->hasPermission('online_orders') ? 'true' : 'false'; ?>;
let notificationSoundsEnabled = true;

// Inicializar audio context después de interacción del usuario
document.addEventListener('click', initializeAudio, { once: true });
document.addEventListener('touchstart', initializeAudio, { once: true });

function initializeAudio() {
    try {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        console.log('Audio context inicializado correctamente');
    } catch (e) {
        console.log('Audio context no disponible:', e);
        audioContext = null;
    }
}

function playNotificationSound(type = 'default') {
    if (!notificationSoundsEnabled) return;

    // Opción 1: Usar Web Audio API (más confiable)
    if (audioContext && audioContext.state === 'running') {
        playWebAudioSound(type);
    } else if (audioContext && audioContext.state === 'suspended') {
        // Intentar reanudar el contexto
        audioContext.resume().then(() => {
            playWebAudioSound(type);
        }).catch(() => {
            playHTML5Sound(type);
        });
    // En la función playWebAudioSound(), agregar después de type === 'urgent'
} else if (type === 'whatsapp') {
    // Sonido específico para WhatsApp - tono distintivo
    oscillator.frequency.setValueAtTime(700, audioContext.currentTime);
    oscillator.frequency.setValueAtTime(900, audioContext.currentTime + 0.15);
    oscillator.frequency.setValueAtTime(700, audioContext.currentTime + 0.3);
    
    oscillator.type = 'sine';
    gainNode.gain.setValueAtTime(0.35, audioContext.currentTime);
    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.5);
    
    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.5);
    } else {
        // Fallback a HTML5 Audio
        playHTML5Sound(type);
    }
}

function playWebAudioSound(type) {
    try {
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        if (type === 'urgent' || type === 'online') {
            // Sonido más llamativo para pedidos online - secuencia de tonos
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(1000, audioContext.currentTime + 0.1);
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.2);
            oscillator.frequency.setValueAtTime(1200, audioContext.currentTime + 0.3);
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.4);
            
            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.4, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.7);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.7);
        } else {
            // Sonido normal para llamadas de mesa
            oscillator.frequency.setValueAtTime(600, audioContext.currentTime);
            oscillator.frequency.setValueAtTime(800, audioContext.currentTime + 0.2);
            oscillator.frequency.setValueAtTime(600, audioContext.currentTime + 0.4);
            
            oscillator.type = 'sine';
            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.6);
            
            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.6);
        }
        
        console.log('Sonido reproducido via Web Audio API');
    } catch (e) {
        console.log('Error reproduciendo sonido Web Audio:', e);
        playHTML5Sound(type);
    }
}

function playHTML5Sound(type) {
    try {
        const playSimpleBeep = (frequency, duration, delay = 0) => {
            setTimeout(() => {
                try {
                    const audioContext2 = new (window.AudioContext || window.webkitAudioContext)();
                    const oscillator = audioContext2.createOscillator();
                    const gainNode = audioContext2.createGain();
                    
                    oscillator.connect(gainNode);
                    gainNode.connect(audioContext2.destination);
                    
                    oscillator.frequency.value = frequency;
                    oscillator.type = 'sine';
                    gainNode.gain.setValueAtTime(0.3, audioContext2.currentTime);
                    gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext2.currentTime + duration);
                    
                    oscillator.start();
                    oscillator.stop(audioContext2.currentTime + duration);
                } catch (e) {
                    console.log('Error en beep individual:', e);
                }
            }, delay);
        };
        
        if (type === 'urgent' || type === 'online') {
            // Secuencia de beeps para pedidos online
            playSimpleBeep(800, 0.15, 0);
            playSimpleBeep(1000, 0.15, 200);
            playSimpleBeep(1200, 0.15, 400);
        } else {
            // Beep simple para mesas
            playSimpleBeep(600, 0.4, 0);
        }
        
        console.log('Sonido reproducido via HTML5 Audio');
    } catch (e) {
        console.log('Error reproduciendo sonido HTML5:', e);
        // Último recurso: vibración
        if ('vibrate' in navigator) {
            if (type === 'urgent' || type === 'online') {
                navigator.vibrate([200, 100, 200, 100, 200]);
            } else {
                navigator.vibrate([300, 150, 300]);
            }
            console.log('Vibración activada como respaldo');
        }
    }
}

function checkCalls() {
    fetch("check_calls.php")
        .then(res => res.json())
        .then(data => {
            if (data && data.id) {
                currentCallId = data.id;
                document.getElementById("mesaNumber").innerText = "Mesa " + data.mesa + " está llamando";
                playNotificationSound('default');
                let modal = new bootstrap.Modal(document.getElementById('callModal'));
                modal.show();
            }
        })
        .catch(error => {
            console.log('Error checking calls:', error);
        });
}

function checkOnlineOrders() {
    if (!hasPermissionOnlineOrders) {
        console.log('Sin permisos para verificar pedidos online');
        return;
    }

    console.log('Verificando pedidos online...');

    fetch('api/online-orders-stats.php', {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Respuesta API pedidos online:', data);
        
        if (data && data.success) {
            const currentCount = parseInt(data.pending_online) || 0;
            const urgentCount = parseInt(data.urgent_count) || 0;
            
            console.log(`Pedidos online - Actual: ${currentCount}, Anterior: ${lastOnlineOrdersCount}`);
            
            // Actualizar contador en sidebar
            const badge = document.getElementById('online-orders-count');
            if (badge) {
                badge.textContent = currentCount;
                if (currentCount > 0) {
                    badge.classList.add('pulsing-badge');
                } else {
                    badge.classList.remove('pulsing-badge');
                }
            }

            // Actualizar contador en estadísticas
            const statCount = document.getElementById('pending-online-count');
            if (statCount) {
                statCount.textContent = currentCount;
            }

            // CORRECCIÓN CRÍTICA: Verificar si hay nuevos pedidos
            if (currentCount > lastOnlineOrdersCount) {
                const newOrders = currentCount - lastOnlineOrdersCount;
                console.log(`¡NUEVOS PEDIDOS DETECTADOS! Cantidad: ${newOrders}`);
                
                // Mostrar alerta inmediatamente
                showOnlineOrderAlert(currentCount, newOrders, data);
                
                // Reproducir sonido
                playNotificationSound('online');
                
                // Vibración si está disponible
                if ('vibrate' in navigator) {
                    navigator.vibrate([200, 100, 200, 100, 200]);
                }
            }

            // Actualizar contador para próxima verificación
            lastOnlineOrdersCount = currentCount;
            
            // Verificar pedidos urgentes
            if (data.has_urgent && urgentCount > 0) {
                showVisualNotification(`⚠️ ${urgentCount} pedido${urgentCount > 1 ? 's' : ''} urgente${urgentCount > 1 ? 's' : ''} (>30 min)`, 'danger');
            }
        } else {
            console.error('Error en respuesta API:', data);
        }
    })
    .catch(error => {
        console.error('Error verificando pedidos online:', error);
    });
}

// Verificar mensajes de WhatsApp nuevos
let lastWhatsAppCheck = new Date().toISOString();
let hasPermissionWhatsApp = <?php echo $auth->hasPermission('all') || $auth->hasPermission('online_orders') ? 'true' : 'false'; ?>;

function checkWhatsAppMessages() {
    if (!hasPermissionWhatsApp) {
        return;
    }

    console.log('Verificando mensajes de WhatsApp...');

    fetch('api/whatsapp-stats.php?last_check=' + encodeURIComponent(lastWhatsAppCheck), {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Cache-Control': 'no-cache'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('Respuesta WhatsApp:', data);
        
        if (data && data.success) {
            const unreadCount = parseInt(data.unread_messages) || 0;
            const newMessagesCount = parseInt(data.new_messages_count) || 0;
            
            // Actualizar badge en el sidebar (si existe enlace a WhatsApp)
            updateWhatsAppBadge(unreadCount);
            
            // Si hay mensajes nuevos desde la última verificación
            if (newMessagesCount > 0) {
                console.log(`¡NUEVOS MENSAJES WHATSAPP! Cantidad: ${newMessagesCount}`);
                
                // Mostrar alerta
                showWhatsAppAlert(unreadCount, newMessagesCount, data.recent_messages);
                
                // Reproducir sonido
                playNotificationSound('whatsapp');
                
                // Vibración
                if ('vibrate' in navigator) {
                    navigator.vibrate([150, 100, 150]);
                }
            }
            
            // Actualizar timestamp para próxima verificación
            lastWhatsAppCheck = data.last_check;
        }
    })
    .catch(error => {
        console.error('Error verificando mensajes WhatsApp:', error);
    });
}

function updateWhatsAppBadge(unreadCount) {
    // Buscar enlace de WhatsApp en el sidebar
    const whatsappLink = document.querySelector('.sidebar .nav-link[href*="whatsapp"]');
    if (whatsappLink) {
        let badge = whatsappLink.querySelector('.badge');
        
        if (unreadCount > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge bg-success ms-auto';
                whatsappLink.appendChild(badge);
            }
            badge.textContent = unreadCount;
            badge.classList.add('pulsing-badge');
        } else if (badge) {
            badge.remove();
        }
    }
}

function showWhatsAppAlert(totalUnread, newMessages, recentMessages) {
    console.log(`Mostrando alerta WhatsApp: ${newMessages} nuevos mensajes`);
    
    // Crear modal dinámicamente si no existe
    let modal = document.getElementById('whatsappMessageModal');
    if (!modal) {
        modal = createWhatsAppModal();
        document.body.appendChild(modal);
    }
    
    const info = modal.querySelector('#whatsappMessageInfo');
    const details = modal.querySelector('#whatsappMessageDetails');
    const messagesList = modal.querySelector('#whatsappMessagesList');
    
    // Configurar contenido
    if (newMessages === 1) {
        info.textContent = `¡Nuevo mensaje de WhatsApp!`;
    } else {
        info.textContent = `¡${newMessages} nuevos mensajes de WhatsApp!`;
    }
    
    details.textContent = `Total no leídos: ${totalUnread}`;
    
    // Mostrar últimos mensajes
    if (recentMessages && recentMessages.length > 0) {
        let messagesHtml = '';
        recentMessages.slice(0, 3).forEach(msg => {
            const time = new Date(msg.created_at).toLocaleTimeString('es-AR', {
                hour: '2-digit',
                minute: '2-digit'
            });
            const content = msg.content.substring(0, 50) + (msg.content.length > 50 ? '...' : '');
            messagesHtml += `
                <div class="border-bottom pb-2 mb-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong class="small">${msg.phone_number}</strong>
                        <small class="text-muted">${time}</small>
                    </div>
                    <div class="small text-muted">${content}</div>
                </div>
            `;
        });
        messagesList.innerHTML = messagesHtml;
    } else {
        messagesList.innerHTML = '<div class="text-muted small">No hay vista previa disponible</div>';
    }

    // Mostrar notificación visual flotante
    const message = `📱 ${newMessages === 1 ? 'Nuevo' : newMessages + ' nuevos'} mensaje${newMessages > 1 ? 's' : ''} de WhatsApp`;
    showVisualNotification(message, 'success');

    // Mostrar modal
    try {
        const bsModal = new bootstrap.Modal(modal, {
            backdrop: 'static',
            keyboard: false
        });
        bsModal.show();
        
        console.log('Modal de WhatsApp mostrado');
    } catch (e) {
        console.error('Error mostrando modal WhatsApp:', e);
    }
}

function createWhatsAppModal() {
    const modalHtml = `
        <div class="modal fade" id="whatsappMessageModal" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">
                            <i class="fab fa-whatsapp me-2"></i>
                            Mensajes de WhatsApp
                        </h5>
                    </div>
                    <div class="modal-body">
                        <div class="text-center mb-3">
                            <i class="fab fa-whatsapp fa-3x text-success mb-3"></i>
                            <h4 id="whatsappMessageInfo">Nuevos mensajes</h4>
                            <p class="text-muted" id="whatsappMessageDetails">Detalles de mensajes</p>
                        </div>
                        <div id="whatsappMessagesList" class="border rounded p-3 bg-light">
                            <!-- Aquí se mostrarán los mensajes recientes -->
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center">
                        <button class="btn btn-success me-2" onclick="goToWhatsAppMessages()">
                            <i class="fas fa-comments me-1"></i>Ver Conversaciones
                        </button>
                        <button class="btn btn-outline-success me-2" onclick="openWhatsAppQuickReply()">
                            <i class="fas fa-reply me-1"></i>Respuesta Rápida
                        </button>
                        <button class="btn btn-secondary" onclick="dismissWhatsAppAlert()">
                            <i class="fas fa-times me-1"></i>Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    const tempDiv = document.createElement('div');
    tempDiv.innerHTML = modalHtml;
    return tempDiv.firstElementChild;
}

function goToWhatsAppMessages() {
    window.location.href = 'whatsapp-messages.php';
}

function openWhatsAppQuickReply() {
    // Cerrar modal actual
    const modal = bootstrap.Modal.getInstance(document.getElementById('whatsappMessageModal'));
    if (modal) modal.hide();
    
    // Abrir ventana de WhatsApp Web en nueva pestaña
    window.open('https://web.whatsapp.com/', '_blank');
}

function dismissWhatsAppAlert() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('whatsappMessageModal'));
    if (modal) modal.hide();
}

function showOnlineOrderAlert(totalPending, newOrders, data) {
    console.log(`Mostrando alerta: ${newOrders} nuevos pedidos, total: ${totalPending}`);
    
    const modal = document.getElementById('onlineOrderModal');
    const info = document.getElementById('onlineOrderInfo');
    const details = document.getElementById('onlineOrderDetails');
    
    if (!modal || !info || !details) {
        console.error('Elementos del modal no encontrados');
        return;
    }
    
    // Configurar contenido del modal
    if (newOrders === 1) {
        info.textContent = `¡Nuevo pedido online recibido!`;
    } else {
        info.textContent = `¡${newOrders} nuevos pedidos online!`;
    }
    
    let detailsText = `Total de pedidos pendientes: ${totalPending}`;
    if (data && data.avg_pending_time) {
        detailsText += ` | Tiempo promedio: ${data.avg_pending_time} min`;
    }
    details.textContent = detailsText;

    // Mostrar notificación visual flotante también
    const message = `🌐 ${newOrders === 1 ? 'Nuevo' : newOrders + ' nuevos'} pedido${newOrders > 1 ? 's' : ''} online`;
    showVisualNotification(message, 'warning');

    // Mostrar modal
    try {
        const bsModal = new bootstrap.Modal(modal, {
            backdrop: 'static',
            keyboard: false
        });
        bsModal.show();
        
        console.log('Modal de pedidos online mostrado');
    } catch (e) {
        console.error('Error mostrando modal:', e);
    }
}

function showVisualNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} notification-alert notification-shake`;
    notification.innerHTML = `
        <div class="d-flex align-items-center">
            <i class="fas fa-bell me-2"></i>
            <div class="flex-grow-1">
                <strong>${message}</strong>
                <div class="small mt-1">Hace un momento</div>
            </div>
            <button type="button" class="btn-close" onclick="this.parentElement.parentElement.remove()"></button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 8 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.animation = 'slideInRight 0.5s ease-out reverse';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 500);
        }
    }, 8000);
    
    console.log('Notificación visual mostrada:', message);
}

function attendCall() {
    if (currentCallId) {
        fetch("attend_call.php?id=" + currentCallId)
            .then(() => {
                currentCallId = null;
                const modal = bootstrap.Modal.getInstance(document.getElementById('callModal'));
                modal.hide();
                showVisualNotification('Llamada de mesa atendida', 'success');
            })
            .catch(error => {
                console.error('Error attending call:', error);
                showVisualNotification('Error al atender llamada', 'danger');
            });
    }
}

function goToOnlineOrders() {
    window.location.href = 'online-orders.php';
}

function dismissOnlineAlert() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('onlineOrderModal'));
    modal.hide();
}

// Revisar llamadas de mesa cada 10 segundos
setInterval(checkCalls, 10000);

// Revisar pedidos online cada 15 segundos
if (hasPermissionOnlineOrders) {
    setInterval(checkOnlineOrders, 15000);
}

// Mobile menu functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeMobileMenu();
    loadDashboardData();
    
    // Sistema de notificaciones
    console.log('=== INICIALIZANDO SISTEMA DE NOTIFICACIONES ===');
    
    // Configurar verificación de pedidos online
    if (hasPermissionOnlineOrders) {
        console.log('Configurando verificación automática de pedidos online...');
        console.log('Contador inicial:', lastOnlineOrdersCount);
        
        // Verificar inmediatamente después de 3 segundos
        setTimeout(() => {
            console.log('Primera verificación de pedidos online...');
            checkOnlineOrders();
        }, 3000);
        // Revisar mensajes de WhatsApp cada 20 segundos
if (hasPermissionWhatsApp) {
    setInterval(checkWhatsAppMessages, 20000);
}
    } else {
        console.log('Usuario sin permisos para pedidos online');
    }
    
    console.log('Sistema de notificaciones inicializado');
    
    // Agregar después de la configuración de pedidos online
// Configurar verificación de mensajes WhatsApp
if (hasPermissionWhatsApp) {
    console.log('Configurando verificación automática de mensajes WhatsApp...');
    
    // Verificar después de 5 segundos
    setTimeout(() => {
        console.log('Primera verificación de mensajes WhatsApp...');
        checkWhatsAppMessages();
    }, 5000);
} else {
    console.log('Usuario sin permisos para WhatsApp');
}
});



// Auto-refresh data every 30 seconds
setInterval(loadDashboardData, 30000);

function loadDashboardData() {
    <?php if ($auth->hasPermission('orders')): ?>
        loadRecentOrders();
    <?php endif; ?>

    <?php if ($auth->hasPermission('tables')): ?>
        loadTablesStatus();
    <?php endif; ?>

    <?php if ($auth->hasPermission('kitchen')): ?>
        loadKitchenOrders();
    <?php endif; ?>

    <?php if ($auth->hasPermission('delivery')): ?>
        loadDeliveryOrders();
    <?php endif; ?>
}

function loadRecentOrders() {
    fetch('api/orders.php?recent=5')
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            const tbody = document.getElementById('recent-orders');
            if (!tbody) return;
            
            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No hay órdenes recientes</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(order => 
                '<tr>' +
                    '<td><strong>#' + (order.order_number || order.id) + '</strong></td>' +
                    '<td>' +
                        '<span class="badge bg-' + getTypeColor(order.type) + '">' +
                            getTypeText(order.type) +
                        '</span>' +
                    '</td>' +
                    '<td class="d-none d-md-table-cell">' + (order.customer_name || (order.table_number ? 'Mesa ' + order.table_number : 'N/A')) + '</td>' +
                    '<td>' + formatPrice(order.total) + '</td>' +
                    '<td>' +
                        '<span class="badge bg-' + getStatusColor(order.status) + '">' +
                            getStatusText(order.status) +
                        '</span>' +
                    '</td>' +
                    '<td>' +
                        '<span class="badge bg-' + getPaymentStatusColor(order.payment_status) + '">' +
                            getPaymentStatusText(order.payment_status) +
                        '</span>' +
                    '</td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-outline-primary" onclick="viewOrder(' + order.id + ', \'' + order.type + '\')">' +
                            '<i class="fas fa-eye"></i>' +
                        '</button>' +
                    '</td>' +
                '</tr>'
            ).join('');
        })
        .catch(error => {
            console.error('Error loading orders:', error);
            const tbody = document.getElementById('recent-orders');
            if (tbody) {
                tbody.innerHTML = 
                    '<tr><td colspan="7" class="text-center text-danger py-4">Error al cargar órdenes</td></tr>';
            }
        });
}

// Actualizar función viewOrder para manejar ambos tipos
function viewOrder(orderId, orderType = 'traditional') {
    // Si es orden online o tipo 'online', ir a online-order-details
    if (orderType === 'online') {
        window.location.href = `online-order-details.php?id=${orderId}`;
    } else {
        // Para todos los demás tipos (dine_in, delivery, takeout), ir a order-details tradicional
        window.location.href = `order-details.php?id=${orderId}`;
    }
}

// Agregar función getTypeText para incluir 'online'
function getTypeText(type) {
    const texts = {
        'dine_in': 'Mesa',
        'delivery': 'Delivery',
        'takeout': 'Retiro',
        'online': 'Online'
    };
    return texts[type] || type;
}

function getTypeColor(type) {
    const colors = {
        'dine_in': 'primary',
        'delivery': 'success',
        'takeout': 'warning',
        'online': 'info'
    };
    return colors[type] || 'secondary';
}

// funciones junto a getStatusColor, getTypeColor, etc.
function getPaymentStatusColor(paymentStatus) {
    const colors = {
        'paid': 'success',
        'pending': 'warning',
        'partial': 'info',
        'cancelled': 'danger'
    };
    return colors[paymentStatus] || 'secondary';
}

function getPaymentStatusText(paymentStatus) {
    const texts = {
        'paid': 'Cobrado',
        'pending': 'Pendiente',
        'partial': 'Parcial',
        'cancelled': 'Cancelado'
    };
    return texts[paymentStatus] || 'N/A';
}

function loadTablesStatus() {
    fetch('api/tables.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            const container = document.getElementById('tables-status');
            if (!container) return;
            
            if (!data || data.length === 0) {
                container.innerHTML = '<div class="text-center text-muted py-3">No hay mesas configuradas</div>';
                return;
            }
            
            // Crear HTML con iconos de mesa cuadrados y compactos
            let html = '<div class="row g-2 justify-content-start">';
            
            data.forEach(table => {
                const statusColors = {
                    'available': { bg: 'success', text: 'Libre' },
                    'occupied': { bg: 'danger', text: 'Ocupada' },
                    'reserved': { bg: 'warning', text: 'Reservada' },
                    'maintenance': { bg: 'secondary', text: 'Mantto' }
                };
                
                const statusInfo = statusColors[table.status] || { bg: 'secondary', text: table.status };
                
                html += `
                    <div class="col-4 col-sm-3 col-md-2 mb-2">
                        <div class="table-mini-card table-${table.status}" onclick="goToTables(${table.id})">
                            <!-- Visual de mesa pequeña -->
                            <div class="table-mini-visual">
                                <div class="table-mini-surface"></div>
                                ${generateMiniChairs(table.capacity)}
                            </div>
                            
                            <div class="table-mini-info">
                                <div class="table-mini-number">${table.number}</div>
                                <div class="table-mini-capacity">${table.capacity}p</div>
                                <span class="badge bg-${statusInfo.bg} table-mini-status">${statusInfo.text}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading tables:', error);
            const container = document.getElementById('tables-status');
            if (container) {
                container.innerHTML = '<div class="text-center text-danger py-3">Error al cargar mesas</div>';
            }
        });
}

// Función auxiliar para generar sillas según capacidad
function generateMiniChairs(capacity) {
    let chairs = '';
    const positions = ['top', 'right', 'bottom', 'left', 'top-left', 'top-right', 'bottom-left', 'bottom-right'];
    
    for (let i = 0; i < Math.min(capacity, 8); i++) {
        chairs += `<div class="chair-mini chair-${positions[i]}"></div>`;
    }
    
    return chairs;
}

// Función para navegar a la página de mesas específica
function goToTables(tableId = null) {
    if (tableId) {
        window.location.href = `tables.php?highlight=${tableId}`;
    } else {
        window.location.href = 'tables.php';
    }
}

function loadKitchenOrders() {
    fetch('api/kitchen.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            const container = document.getElementById('kitchen-orders');
            if (!container) return;
            
            if (!data || data.length === 0) {
                container.innerHTML = '<div class="col-12 text-center text-muted py-4">No hay órdenes en cocina</div>';
                return;
            }

            var html = '';
            data.forEach(function(order) {
                html += '<div class="col-sm-6 col-lg-4 mb-3">' +
                    '<div class="card border-warning">' +
                        '<div class="card-header bg-warning text-dark">' +
                            '<div class="d-flex justify-content-between align-items-center">' +
                                '<strong>#' + (order.order_number || order.id) + '</strong>' +
                                '<small>' + formatTimeElapsed(order) + '</small>' +
                            '</div>' +
                        '</div>' +
                        '<div class="card-body">' +
                            '<div class="mb-3">';
                
                if (order.items && order.items.length > 0) {
                    order.items.forEach(function(item) {
                        html += '<div class="d-flex justify-content-between align-items-center mb-2 small">' +
                            '<span><strong>' + item.quantity + 'x</strong> ' + item.product_name + '</span>' +
                            '<button class="btn btn-xs btn-success" onclick="markItemReady(' + item.id + ')" title="Marcar listo">' +
                                '<i class="fas fa-check"></i>' +
                            '</button>' +
                        '</div>';
                    });
                } else {
                    html += '<div class="text-muted small">Sin items específicos</div>';
                }
                
                html += '</div>' +
                            '<hr class="my-2">' +
                            '<button class="btn btn-success btn-sm w-100" onclick="markOrderReady(' + order.id + ')">' +
                                '<i class="fas fa-check me-1"></i> Marcar como Listo' +
                            '</button>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            });
            
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading kitchen orders:', error);
            const container = document.getElementById('kitchen-orders');
            if (container) {
                container.innerHTML = '<div class="col-12 text-center text-danger py-4">Error al cargar órdenes de cocina</div>';
            }
        });
}

function loadDeliveryOrders() {
    // Primero actualizar las estadísticas
    fetch('api/delivery-stats.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(statsData => {
            console.log('Estadísticas de delivery:', statsData);
            
            // Actualizar el badge en el sidebar
            const deliveryBadge = document.querySelector('.sidebar .nav-link[href="delivery.php"] .badge');
            if (deliveryBadge && statsData.success) {
                deliveryBadge.textContent = statsData.total_pending;
                if (statsData.total_pending > 0) {
                    deliveryBadge.classList.add('pulsing-badge');
                } else {
                    deliveryBadge.classList.remove('pulsing-badge');
                }
            }
            
            // Actualizar estadística en el dashboard
            const pendingDeliveryElement = document.querySelector('.stat-card .stat-number');
            if (pendingDeliveryElement && statsData.success) {
                // Buscar el elemento específico de entregas pendientes
                const deliveryCards = document.querySelectorAll('.stat-card');
                deliveryCards.forEach(card => {
                    const iconElement = card.querySelector('.fa-motorcycle');
                    if (iconElement) {
                        const numberElement = card.querySelector('.stat-number');
                        if (numberElement) {
                            numberElement.textContent = statsData.total_pending;
                        }
                    }
                });
            }
        })
        .catch(error => {
            console.error('Error cargando estadísticas de delivery:', error);
        });

    // Luego cargar las órdenes para mostrar en el dashboard
    fetch('api/delivery.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(response => {
            const data = response.orders || response;
            const container = document.getElementById('delivery-orders');
            if (!container) return;
            
            if (!data || data.length === 0) {
                container.innerHTML = '<div class="col-12 text-center text-muted py-4">No hay entregas pendientes</div>';
                return;
            }

            var html = '';
            data.forEach(function(order) {
                html += '<div class="col-sm-6 col-lg-4 mb-3">' +
                    '<div class="card border-info">' +
                        '<div class="card-header bg-info text-white">' +
                            '<div class="d-flex justify-content-between align-items-center">' +
                                '<strong>#' + (order.order_number || order.id) + '</strong>' +
                                '<span class="badge bg-light text-dark">' + formatPrice(order.total) + '</span>' +
                            '</div>' +
                        '</div>' +
                        '<div class="card-body">' +
                            '<div class="mb-2">' +
                                '<h6 class="mb-1">' + (order.customer_name || 'Cliente') + '</h6>' +
                            '</div>' +
                            '<div class="mb-2">' +
                                '<p class="mb-1 text-muted small">' +
                                    '<i class="fas fa-map-marker-alt me-1"></i>' +
                                    (order.customer_address || order.delivery_address || 'Dirección no disponible') +
                                '</p>' +
                            '</div>' +
                            '<div class="mb-3">' +
                                '<p class="mb-0 text-muted small">' +
                                    '<i class="fas fa-phone me-1"></i>';
                
                if (order.customer_phone || order.delivery_phone) {
                    html += '<a href="tel:' + (order.customer_phone || order.delivery_phone) + '" class="text-decoration-none">' +
                                (order.customer_phone || order.delivery_phone) +
                            '</a>';
                } else {
                    html += 'Teléfono no disponible';
                }
                
                html += '</p>' +
                            '</div>';
                
                // Mostrar tiempo transcurrido si está disponible
                if (order.elapsed_minutes) {
                    let timeClass = 'badge bg-success';
                    if (order.elapsed_minutes > 45) {
                        timeClass = 'badge bg-danger';
                    } else if (order.elapsed_minutes > 30) {
                        timeClass = 'badge bg-warning';
                    }
                    html += '<div class="mb-2">' +
                                '<span class="' + timeClass + '">' +
                                    '<i class="fas fa-clock me-1"></i>' +
                                    order.elapsed_minutes + ' min' +
                                '</span>' +
                            '</div>';
                }
                
                html += '<div class="d-grid gap-2">' +
                            '<button class="btn btn-success btn-sm" onclick="markDelivered(' + order.id + ', \'' + (order.order_type || 'traditional') + '\')">' +
                                '<i class="fas fa-check me-1"></i> Marcar Entregado' +
                            '</button>';
                
                if (order.customer_phone || order.delivery_phone) {
                    html += '<a href="tel:' + (order.customer_phone || order.delivery_phone) + '" class="btn btn-outline-primary btn-sm">' +
                                '<i class="fas fa-phone me-1"></i> Llamar' +
                            '</a>';
                }
                
                html += '</div>' +
                        '</div>' +
                    '</div>' +
                '</div>';
            });
            
            container.innerHTML = html;
        })
        .catch(error => {
            console.error('Error loading delivery orders:', error);
            const container = document.getElementById('delivery-orders');
            if (container) {
                container.innerHTML = '<div class="col-12 text-center text-danger py-4">Error al cargar entregas</div>';
            }
        });
}

// Helper functions
function getStatusColor(status) {
    const colors = {
        'pending': 'secondary',
        'confirmed': 'info',
        'preparing': 'warning',
        'ready': 'success',
        'delivered': 'primary',
        'cancelled': 'danger'
    };
    return colors[status] || 'secondary';
}

function getStatusText(status) {
    const texts = {
        'pending': 'Pendiente',
        'confirmed': 'Confirmado',
        'preparing': 'Preparando',
        'ready': 'Listo',
        'delivered': 'Entregado',
        'cancelled': 'Cancelado'
    };
    return texts[status] || status;
}

function getTypeColor(type) {
    const colors = {
        'dine_in': 'primary',
        'delivery': 'success',
        'takeout': 'warning'
    };
    return colors[type] || 'secondary';
}

function getTypeText(type) {
    const texts = {
        'dine_in': 'Mesa',
        'delivery': 'Delivery',
        'takeout': 'Retiro'
    };
    return texts[type] || type;
}

function getTableStatusColor(status) {
    const colors = {
        'available': 'success',
        'occupied': 'danger',
        'reserved': 'warning',
        'maintenance': 'secondary'
    };
    return colors[status] || 'secondary';
}

function getTableStatusText(status) {
    const texts = {
        'available': 'Libre',
        'occupied': 'Ocupada',
        'reserved': 'Reservada',
        'maintenance': 'Mantenimiento'
    };
    return texts[status] || status;
}

function formatPrice(price) {
    return '$' + parseFloat(price).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

function formatTime(datetime) {
    return new Date(datetime).toLocaleTimeString('es-AR', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatTimeElapsed(order) {
    if (order.time_elapsed) {
        return order.time_elapsed;
    }
    return formatTime(order.created_at);
}


function markOrderReady(orderId) {
    if (confirm('¿Marcar orden como lista?')) {
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<div class="loading-spinner me-2"></div> Procesando...';

        fetch('api/update-order-status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ order_id: orderId, status: 'ready' })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadDashboardData();
                showVisualNotification('Orden marcada como lista', 'success');
            } else {
                showVisualNotification('Error al actualizar orden', 'danger');
                button.disabled = false;
                button.innerHTML = originalContent;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showVisualNotification('Error de conexión', 'danger');
            button.disabled = false;
            button.innerHTML = originalContent;
        });
    }
}

function markItemReady(itemId) {
    const button = event.target.closest('button');
    const originalContent = button.innerHTML;
    
    button.disabled = true;
    button.innerHTML = '<div class="loading-spinner"></div>';

    fetch('api/update-item-status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ item_id: itemId, status: 'ready' })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadDashboardData();
            showVisualNotification('Item marcado como listo', 'success');
        } else {
            showVisualNotification('Error al actualizar item', 'danger');
            button.disabled = false;
            button.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showVisualNotification('Error de conexión', 'danger');
        button.disabled = false;
        button.innerHTML = originalContent;
    });
}

function markDelivered(orderId, orderType = 'traditional') {
    if (confirm('¿Confirmar que la orden fue entregada?')) {
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        
        button.disabled = true;
        button.innerHTML = '<div class="loading-spinner me-2"></div> Procesando...';

        // Usar la API de delivery que maneja ambos tipos
        fetch('api/delivery.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'mark_delivered', 
                order_id: orderId, 
                order_type: orderType 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                loadDashboardData();
                showVisualNotification('Orden marcada como entregada', 'success');
            } else {
                showVisualNotification('Error al actualizar orden', 'danger');
                button.disabled = false;
                button.innerHTML = originalContent;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showVisualNotification('Error de conexión', 'danger');
            button.disabled = false;
            button.innerHTML = originalContent;
        });
    }
}

// Handle offline/online status
window.addEventListener('online', () => {
    showVisualNotification('Conexión restaurada', 'success');
    loadDashboardData();
});

window.addEventListener('offline', () => {
    showVisualNotification('Conexión perdida', 'danger');
});

// Funciones adicionales de debug y testing
function testSound() {
    console.log('Probando sonido...');
    playNotificationSound('online');
    showVisualNotification('🔊 Sonido de prueba reproducido', 'info');
}

function debugNotifications() {
    console.log('=== DEBUG SISTEMA DE NOTIFICACIONES ===');
    console.log('hasPermissionOnlineOrders:', hasPermissionOnlineOrders);
    console.log('lastOnlineOrdersCount:', lastOnlineOrdersCount);
    console.log('audioContext:', audioContext);
    console.log('notificationSoundsEnabled:', notificationSoundsEnabled);
    console.log('==========================================');
}

function simulateNewOrder() {
    console.log('Simulando nuevo pedido online...');
    const originalCount = lastOnlineOrdersCount;
    lastOnlineOrdersCount = Math.max(0, lastOnlineOrdersCount - 1); // Reducir para simular incremento
    
    setTimeout(() => {
        // Simular respuesta de API con más pedidos
        const simulatedData = {
            success: true,
            pending_online: originalCount + 1,
            urgent_count: 0,
            avg_pending_time: 12.5,
            has_urgent: false
        };
        
        // Procesar como si fuera respuesta real
        const currentCount = simulatedData.pending_online;
        
        // Actualizar contadores visuales
        const badge = document.getElementById('online-orders-count');
        const statCount = document.getElementById('pending-online-count');
        
        if (badge) {
            badge.textContent = currentCount;
            badge.classList.add('pulsing-badge');
        }
        if (statCount) {
            statCount.textContent = currentCount;
        }
        
        // Mostrar alerta
        if (currentCount > lastOnlineOrdersCount) {
            const newOrders = currentCount - lastOnlineOrdersCount;
            showOnlineOrderAlert(currentCount, newOrders, simulatedData);
            playNotificationSound('online');
        }
        
        lastOnlineOrdersCount = currentCount;
        console.log('Simulación completada. Nuevos pedidos:', currentCount);
    }, 500);
}

// Exponer funciones para debug en consola
window.debugDashboard = {
    testSound: testSound,
    debugInfo: debugNotifications,
    simulateOrder: simulateNewOrder,
    checkOnline: checkOnlineOrders,
    toggleSounds: function() {
        notificationSoundsEnabled = !notificationSoundsEnabled;
        console.log('Sonidos:', notificationSoundsEnabled ? 'ACTIVADOS' : 'DESACTIVADOS');
        showVisualNotification('Sonidos ' + (notificationSoundsEnabled ? 'activados' : 'desactivados'), 'info');
    }
};

// Log informativo al cargar
console.log('=== DASHBOARD SISTEMA DE NOTIFICACIONES CARGADO ===');
console.log('Funciones de debug disponibles en: window.debugDashboard');
console.log('- debugDashboard.testSound() : Probar sonido');
console.log('- debugDashboard.simulateOrder() : Simular nuevo pedido');
console.log('- debugDashboard.debugInfo() : Mostrar información del sistema');
console.log('- debugDashboard.toggleSounds() : Activar/desactivar sonidos');
console.log('- debugDashboard.checkOnline() : Verificar pedidos online manualmente');
console.log('====================================================');
    </script>

<?php include 'footer.php'; ?>
</body>
</html>