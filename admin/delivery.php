<?php
// admin/delivery.php - VERSIÓN LIMPIA Y FUNCIONAL
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Order.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('delivery');

$database = new Database();
$db = $database->getConnection();

// Obtener configuraciones del sistema
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// AGREGAR ESTAS LÍNEAS:
// Obtener información del usuario actual
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role_name'] ?? 'usuario';

// Verificar si hay estadísticas disponibles (opcional)
$stats = array();
$online_stats = array();

// Procesar formularios
$message = '';
$error = '';

if ($_POST && isset($_POST['action'])) {
    $order_id = intval($_POST['order_id']);
    $order_type = $_POST['order_type'] ?? 'traditional';
    
    switch ($_POST['action']) {
        case 'mark_delivered':
            try {
                if ($order_type === 'online') {
                    $query = "UPDATE online_orders SET status = 'delivered', delivered_at = NOW(), delivered_by = ? WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([$_SESSION['user_id'], $order_id]);
                } else {
                    $query = "UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute([$order_id]);
                }
                
                if ($result) {
                    $message = 'Orden marcada como entregada exitosamente';
                } else {
                    $error = 'Error al marcar la orden como entregada';
                }
            } catch (Exception $e) {
                $error = 'Error: ' . $e->getMessage();
            }
            break;
    }
}

// Obtener órdenes tradicionales para delivery
$traditionalQuery = "SELECT o.*, u.full_name as waiter_name 
                    FROM orders o 
                    LEFT JOIN users u ON o.waiter_id = u.id 
                    WHERE o.type = 'delivery' 
                    AND o.status = 'ready'
                    AND o.customer_address IS NOT NULL 
                    AND TRIM(o.customer_address) != ''
                    AND o.customer_address != 'Sin dirección especificada'
                    AND (o.order_number NOT LIKE 'WEB-%' OR o.order_number IS NULL)
                    ORDER BY o.created_at ASC";

$stmt = $db->prepare($traditionalQuery);
$stmt->execute();
$traditionalOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener órdenes online para delivery
$onlineQuery = "SELECT * FROM online_orders 
               WHERE status = 'ready' 
               AND customer_address IS NOT NULL 
               AND TRIM(customer_address) != ''
               ORDER BY created_at ASC";

$stmt = $db->prepare($onlineQuery);
$stmt->execute();
$onlineOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Procesar órdenes tradicionales
$processedTraditional = [];
foreach ($traditionalOrders as $order) {
    $order['order_type'] = 'traditional';
    $order['delivery_type'] = 'Pedido Local';
    
    // Calcular tiempo transcurrido
    $order_time = new DateTime($order['created_at']);
    $current_time = new DateTime();
    $elapsed_seconds = $current_time->getTimestamp() - $order_time->getTimestamp();
    $elapsed_minutes = max(0, floor($elapsed_seconds / 60));
    
    $order['elapsed_minutes'] = $elapsed_minutes;
    $order['is_urgent'] = $elapsed_minutes > 45;
    
    // Validar datos
    if (empty($order['customer_name'])) $order['customer_name'] = 'Cliente Sin Nombre';
    if (empty($order['customer_phone'])) $order['customer_phone'] = 'Sin teléfono';
    
    // Obtener conteo de items
    $items_query = "SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?";
    $items_stmt = $db->prepare($items_query);
    $items_stmt->execute([$order['id']]);
    $items_result = $items_stmt->fetch();
    $order['item_count'] = $items_result['item_count'] ?? 0;
    
    $processedTraditional[] = $order;
}

// Procesar órdenes online
$processedOnline = [];
foreach ($onlineOrders as $order) {
    $order['order_type'] = 'online';
    $order['delivery_type'] = 'Pedido Online';
    $order['type'] = 'delivery'; // Para compatibilidad
    
    // Calcular tiempo transcurrido
    $order_time = new DateTime($order['created_at']);
    $current_time = new DateTime();
    $elapsed_seconds = $current_time->getTimestamp() - $order_time->getTimestamp();
    $elapsed_minutes = max(0, floor($elapsed_seconds / 60));
    
    $order['elapsed_minutes'] = $elapsed_minutes;
    $order['is_urgent'] = $elapsed_minutes > 45;
    
    // Procesar items
    $items = json_decode($order['items'], true) ?: [];
    $order['item_count'] = count($items);
    
    // Campos requeridos
    $order['waiter_name'] = 'Sistema Online';
    $order['delivery_fee'] = $order['delivery_fee'] ?? 0;
    
    $processedOnline[] = $order;
}

// Combinar órdenes SIN filtros complejos
$delivery_orders = array_merge($processedTraditional, $processedOnline);

// Ordenar por urgencia y tiempo
usort($delivery_orders, function($a, $b) {
    if ($a['is_urgent'] !== $b['is_urgent']) {
        return $b['is_urgent'] - $a['is_urgent'];
    }
    return strtotime($a['created_at']) - strtotime($b['created_at']);
});

// Estadísticas simples
$stats = [
    'total_orders' => count($delivery_orders),
    'urgent_orders' => count(array_filter($delivery_orders, fn($o) => $o['is_urgent'])),
    'online_orders' => count($processedOnline),
    'traditional_orders' => count($processedTraditional)
];

// Obtener entregadas hoy
$today_query = "SELECT 
    (SELECT COUNT(*) FROM orders WHERE type = 'delivery' AND status = 'delivered' AND DATE(updated_at) = CURDATE()) +
    (SELECT COUNT(*) FROM online_orders WHERE status = 'delivered' AND DATE(delivered_at) = CURDATE()) as count";
$today_stmt = $db->prepare($today_query);
$today_stmt->execute();
$today_result = $today_stmt->fetch();
$stats['today_delivered'] = $today_result['count'] ?? 0;

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Funciones auxiliares
function generateMapLink($address) {
    return "https://www.google.com/maps/search/?api=1&query=" . urlencode($address . ", Avellaneda, Santa Fe, Argentina");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery - <?php echo $restaurant_name; ?></title>
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
    try {
        $theme_manager = new ThemeManager($db);
        $current_theme = $theme_manager->getThemeSettings();
    } catch (Exception $e) {
        $current_theme = array(
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'success_color' => '#28a745',
            'sidebar_width' => '280px'
        );
    }
} else {
    $current_theme = array(
        'primary_color' => '#667eea', 
        'secondary_color' => '#764ba2',
        'success_color' => '#28a745',
        'sidebar_width' => '280px'
    );
}
?>
<style>
        :root {
            --primary-gradient: linear-gradient(180deg, <?php echo $current_theme['primary_color']; ?> 0%, <?php echo $current_theme['secondary_color']; ?> 100%);
            --sidebar-width: 280px;
            --white: #ffffff;
            --light: #f8f9fa;
            --dark: #212529;
            --muted: #6c757d;
            --border: #e9ecef;
        }

        body {
            background: var(--light) !important;
            overflow-x: hidden;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--dark) !important;
        }

        /* FORZAR COLORES CLAROS EN TODOS LOS ELEMENTOS */
        * {
            color: #212529 !important;
        }

        /* Mobile Top Bar */
        .mobile-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            background: var(--primary-gradient);
            color: var(--white) !important;
            padding: 1rem;
            display: none;
        }

        .mobile-topbar h5, .mobile-topbar * {
            margin: 0;
            font-size: 1.1rem;
            color: var(--white) !important;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: var(--white) !important;
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .menu-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Sidebar - mantener colores del tema */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-gradient);
            color: var(--white) !important;
            z-index: 1030;
            transition: transform 0.3s ease-in-out;
            overflow-y: auto;
            padding: 1.5rem;
        }

        .sidebar * {
            color: rgba(255, 255, 255, 0.9) !important;
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
            color: rgba(255, 255, 255, 0.8) !important;
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
            color: var(--white) !important;
        }

        .sidebar-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: var(--white) !important;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: none;
            align-items: center;
            justify-content: center;
        }

        /* Main content - FORZAR FONDO CLARO */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out;
            background: #f8f9fa !important;
            color: #212529 !important;
        }

        .main-content * {
            color: #212529 !important;
        }

        /* Page header - FORZAR COLORES CLAROS */
        .page-header {
            background: #ffffff !important;
            color: #212529 !important;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .page-header h1, .page-header h2, .page-header h3, .page-header h4, .page-header h5, .page-header h6,
        .page-header p, .page-header span, .page-header div {
            color: #212529 !important;
        }

        /* Color picker corregido */
        .color-input-group {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 50px;
            cursor: pointer;
        }

        .color-preview {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            border: 3px solid var(--border);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            background: #ffffff;
            display: block;
        }

        .color-preview:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
            border-color: <?php echo $current_theme['primary_color']; ?>;
        }

        .color-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            border: none;
            z-index: 10;
            background: none;
        }

        .color-tooltip {
            position: absolute;
            bottom: -35px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0, 0, 0, 0.8);
            color: var(--white) !important;
            padding: 6px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.3s;
            pointer-events: none;
            z-index: 1000;
            white-space: nowrap;
        }

        .color-tooltip::before {
            content: '';
            position: absolute;
            top: -5px;
            left: 50%;
            transform: translateX(-50%);
            border: 5px solid transparent;
            border-bottom-color: rgba(0, 0, 0, 0.8);
        }

        .color-input-group:hover .color-tooltip {
            opacity: 1;
        }

        /* Statistics cards - FORZAR COLORES CLAROS */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #ffffff !important;
            color: #212529 !important;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
            border: 1px solid #e9ecef;
        }

        .stat-card * {
            color: #212529 !important;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            color: #212529 !important;
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        /* Cards genéricas - FORZAR COLORES CLAROS */
        .card {
            background: #ffffff !important;
            color: #212529 !important;
            border: 1px solid #e9ecef;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .card * {
            color: #212529 !important;
        }

        .card-header {
            background: #f8f9fa !important;
            color: #212529 !important;
            border-bottom: 1px solid #e9ecef;
            border-radius: 15px 15px 0 0 !important;
        }

        .card-header * {
            color: #212529 !important;
        }

        .card-body {
            background: #ffffff !important;
            color: #212529 !important;
        }

        .card-body * {
            color: #212529 !important;
        }

        /* Delivery cards - FORZAR COLORES CLAROS */
        .delivery-card {
            background: #ffffff !important;
            color: #212529 !important;
            border: 1px solid #e9ecef;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            border-left: 5px solid #28a745;
        }

        .delivery-card * {
            color: #212529 !important;
        }

        .delivery-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .delivery-card.urgent {
            border-left-color: #dc3545;
            animation: pulse-border 2s infinite;
        }

        @keyframes pulse-border {
            0% { border-left-color: #dc3545; }
            50% { border-left-color: #ff6b6b; }
            100% { border-left-color: #dc3545; }
        }

        /* Time badges */
        .time-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .time-normal {
            background: #d4edda;
            color: #155724 !important;
        }

        .time-warning {
            background: #fff3cd;
            color: #856404 !important;
        }

        .time-urgent {
            background: #f8d7da;
            color: #721c24 !important;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* Customer info */
        .customer-info {
            background: #f8f9fa !important;
            color: #212529 !important;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
        }

        .customer-info * {
            color: #212529 !important;
        }

        /* Action buttons */
        .action-buttons {
            gap: 0.5rem;
        }

        .btn-deliver {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            color: white !important;
            transition: all 0.3s;
        }

        .btn-deliver * {
            color: white !important;
        }

        .btn-deliver:hover {
            background: linear-gradient(135deg, #20c997, #28a745);
            color: white !important;
            transform: scale(1.05);
        }

        /* Empty state */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d !important;
            background: #ffffff !important;
            border-radius: 15px;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            color: #6c757d !important;
        }

        /* Text colors forzados */
        .text-muted {
            color: #6c757d !important;
        }

        h1, h2, h3, h4, h5, h6 {
            color: #212529 !important;
        }

        p, span, div, label, small {
            color: #212529 !important;
        }

        /* Form elements - FORZAR COLORES CLAROS */
        .form-control, .form-select {
            background: #ffffff !important;
            color: #212529 !important;
            border: 1px solid #dee2e6;
        }

        .form-control:focus, .form-select:focus {
            background: #ffffff !important;
            color: #212529 !important;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(var(--primary-color-rgb), 0.25);
        }

        .form-label {
            color: #212529 !important;
            font-weight: 500;
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

            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .stat-icon {
                font-size: 1.8rem;
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

            .delivery-card {
                margin-bottom: 1rem;
            }

            .customer-info {
                padding: 0.75rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    .system-header .container-fluid {
    height: 60px;
    display: flex
;
    align-items: center;
    padding: 0 1rem;
    background-color: white;
}
.dropdown-menu.show {
    display: block;
    background: var(--primary-gradient);
}

.dropdown-header {
    padding: 0.75rem 1rem;
    background: var(--primary-gradient) !important;
    border-radius: 10px 10px 0 0;
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
                    <h2 class="mb-0">
                        <i class="fas fa-motorcycle me-2"></i>
                        Sistema de Delivery
                    </h2>
                    <p class="text-muted mb-0">Gestión de entregas a domicilio</p>
                </div>
                <div class="d-flex align-items-center">
                    <button class="btn btn-info me-2" onclick="location.reload()">
                        <i class="fas fa-sync me-1"></i>
                        Actualizar
                    </button>
                    <div class="text-muted d-none d-lg-block">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('d/m/Y H:i'); ?>
                    </div>
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
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-success">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <div class="stat-number text-success"><?php echo $stats['total_orders']; ?></div>
                    <p class="text-muted mb-0 small">Entregas Pendientes</p>
                    <small class="text-muted" style="font-size: 10px;">
                        Tradicionales: <?php echo $stats['traditional_orders']; ?> | Online: <?php echo $stats['online_orders']; ?>
                    </small>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-number text-danger"><?php echo $stats['urgent_orders']; ?></div>
                    <p class="text-muted mb-0 small">Entregas Urgentes</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-primary">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-number text-primary"><?php echo $stats['today_delivered']; ?></div>
                    <p class="text-muted mb-0 small">Entregadas Hoy</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-icon text-info">
                        <i class="fas fa-globe"></i>
                    </div>
                    <div class="stat-number text-info"><?php echo $stats['online_orders']; ?></div>
                    <p class="text-muted mb-0 small">Pedidos Online</p>
                </div>
            </div>
        </div>

        <!-- Debug Info -->
        <div class="alert alert-info mb-4">
            <small>
                <strong>Atención:</strong> 
                Órdenes tradicionales encontradas: <?php echo count($traditionalOrders); ?> | 
                Órdenes online encontradas: <?php echo count($onlineOrders); ?> |
                Total combinadas: <?php echo count($delivery_orders); ?>
            </small>
        </div>

        <!-- Delivery Orders -->
        <?php if (empty($delivery_orders)): ?>
            <div class="empty-state">
                <i class="fas fa-motorcycle"></i>
                <h4>No hay entregas pendientes</h4>
                <p>Todas las entregas han sido completadas</p>
                <button class="btn btn-success" onclick="location.reload()">
                    <i class="fas fa-sync me-1"></i>
                    Actualizar
                </button>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($delivery_orders as $order): ?>
                    <?php
                    $elapsed_minutes = $order['elapsed_minutes'];
                    $is_urgent = $order['is_urgent'];
                    
                    if ($elapsed_minutes > 60) {
                        $time_class = 'time-urgent';
                        $time_text = intval($elapsed_minutes) . ' min (URGENTE)';
                    } elseif ($elapsed_minutes > 30) {
                        $time_class = 'time-warning';
                        $time_text = intval($elapsed_minutes) . ' min';
                    } else {
                        $time_class = 'time-normal';
                        $time_text = intval($elapsed_minutes) . ' min';
                    }
                    ?>
                    
                    <div class="col-lg-6 col-xl-4">
                        <div class="card delivery-card <?php echo $is_urgent ? 'urgent' : ''; ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0">
                                        <i class="fas fa-receipt me-1"></i>
                                        Orden #<?php echo $order['order_number']; ?>
                                        <span class="badge bg-secondary ms-2 small"><?php echo $order['delivery_type']; ?></span>
                                    </h6>
                                    <small class="text-muted"><?php echo formatDateTime($order['created_at']); ?></small>
                                </div>
                                <span class="time-badge <?php echo $time_class; ?>">
                                    <i class="fas fa-clock me-1"></i>
                                    <?php echo $time_text; ?>
                                </span>
                            </div>
                            
                            <div class="card-body">
                                <div class="customer-info">
                                    <h6 class="mb-2">
                                        <i class="fas fa-user me-2"></i>
                                        <?php echo htmlspecialchars($order['customer_name']); ?>
                                    </h6>
                                    
                                    <div class="mb-2">
                                        <i class="fas fa-phone me-2"></i>
                                        <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" 
                                           class="text-decoration-none">
                                            <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </a>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <i class="fas fa-map-marker-alt me-2"></i>
                                        <span class="small"><?php echo htmlspecialchars($order['customer_address']); ?></span>
                                    </div>
                                    
                                    <div class="mb-0">
                                        <i class="fas fa-utensils me-2"></i>
                                        <small class="text-muted"><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] > 1 ? 's' : ''; ?></small>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <strong class="text-success fs-5">
                                        Total: <?php echo formatPrice($order['total']); ?>
                                    </strong>
                                </div>
                                
                                <div class="d-flex action-buttons mb-2">
                                    <a href="<?php echo generateMapLink($order['customer_address']); ?>" 
                                       target="_blank" class="btn btn-outline-info flex-fill me-1">
                                        <i class="fas fa-map me-1"></i>
                                        Mapa
                                    </a>
                                    
                                    <form method="POST" class="flex-fill">
                                        <input type="hidden" name="action" value="mark_delivered">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="order_type" value="<?php echo $order['order_type']; ?>">
                                        <button type="submit" class="btn btn-deliver w-100"
                                                onclick="return confirm('¿Confirmar que la orden fue entregada?')">
                                            <i class="fas fa-check me-1"></i>
                                            Entregado
                                        </button>
                                    </form>
                                </div>
                                
                                <div class="mt-2">
                                    <div class="btn-group w-100">
                                        <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fas fa-phone"></i>
                                            Llamar
                                        </a>
                                        
                                        <?php 
                                        $whatsapp_message = "Hola! Soy el delivery de {$restaurant_name}. Su pedido #{$order['order_number']} está en camino.";
                                        $clean_phone = preg_replace('/[^0-9]/', '', $order['customer_phone']);
                                        $whatsapp_url = "https://wa.me/" . $clean_phone . "?text=" . urlencode($whatsapp_message);
                                        ?>
                                        <a href="<?php echo $whatsapp_url; ?>" target="_blank" 
                                           class="btn btn-sm btn-outline-success">
                                            <i class="fab fa-whatsapp"></i>
                                            WhatsApp
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
            updateTime();
            setInterval(updateTime, 60000);
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

        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('es-AR', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }

        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 10000);
    </script>

<?php include 'footer.php'; ?>
</body>
</html>