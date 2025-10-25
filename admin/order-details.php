<?php
// admin/order-details.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Order.php';
require_once '../models/Table.php';
require_once '../models/Payment.php';

$auth = new Auth();
$auth->requireLogin();

$orderModel = new Order();
$tableModel = new Table();
$paymentModel = new Payment();

// Get order ID
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id) {
    header('Location: orders.php');
    exit;
}

// Get order details
$order = $orderModel->getById($order_id);
if (!$order) {
    header('Location: orders.php');
    exit;
}

// Get settings for tax calculation
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$tax_rate = floatval($settings['tax_rate'] ?? 0);

// Calculate tax breakdown if tax_rate is configured
$calculated_subtotal_without_tax = 0;
$calculated_tax_amount = 0;

// If the order already has tax field populated, use it
if ($order['tax'] > 0) {
    $calculated_subtotal_without_tax = $order['subtotal'];
    $calculated_tax_amount = $order['tax'];
} else {
    // If no tax in order but tax_rate is configured, calculate it
    if ($tax_rate > 0) {
        // Assume the total includes tax, calculate backwards
        $calculated_subtotal_without_tax = $order['total'] / (1 + ($tax_rate / 100));
        $calculated_tax_amount = $order['total'] - $calculated_subtotal_without_tax;
    } else {
        // No tax configured
        $calculated_subtotal_without_tax = $order['subtotal'];
        $calculated_tax_amount = 0;
    }
}

// Check permissions based on role
$can_edit = $auth->hasPermission('orders');
$can_payment = $auth->hasPermission('orders') || $auth->hasPermission('all');
$can_kitchen = $auth->hasPermission('kitchen');
$can_delivery = $auth->hasPermission('delivery');

// Handle form submissions
$message = '';
$error = '';

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_status':
            if ($can_edit) {
                $new_status = $_POST['status'];
                
                if ($orderModel->updateStatus($order_id, $new_status)) {
                    // If marking as delivered and it's a dine-in order, free the table
                    if ($new_status === 'delivered' && $order['type'] === 'dine_in' && $order['table_id']) {
                        $tableModel->releaseTable($order['table_id']);
                    }
                    $message = 'Estado actualizado exitosamente';
                    $order = $orderModel->getById($order_id); // Reload order
                } else {
                    $error = 'Error al actualizar el estado';
                }
            }
            break;
            
        case 'add_payment':
            if ($can_payment) {
                $payment_data = [
                    'order_id' => $order_id,
                    'method' => $_POST['payment_method'],
                    'amount' => floatval($_POST['amount']),
                    'reference' => sanitize($_POST['reference']),
                    'user_id' => $_SESSION['user_id']
                ];
                
                if ($paymentModel->create($payment_data)) {
                    // Check if order is fully paid
                    $total_paid = $paymentModel->getTotalPaidByOrder($order_id);
                    if ($total_paid >= $order['total']) {
                        $orderModel->updatePaymentStatus($order_id, 'paid');
                    } else {
                        $orderModel->updatePaymentStatus($order_id, 'partial');
                    }
                    
                    $message = 'Pago registrado exitosamente';
                    $order = $orderModel->getById($order_id); // Reload order
                } else {
                    $error = 'Error al registrar el pago';
                }
            }
            break;
            
        case 'update_item_status':
            if ($can_kitchen) {
                $item_id = intval($_POST['item_id']);
                $status = $_POST['item_status'];
                
                if ($orderModel->updateItemStatus($item_id, $status)) {
                    $message = 'Estado del producto actualizado';
                } else {
                    $error = 'Error al actualizar estado del producto';
                }
            }
            break;
            
        case 'mark_delivered':
            if ($can_delivery) {
                if ($orderModel->updateStatus($order_id, 'delivered')) {
                    $message = 'Orden marcada como entregada';
                    $order = $orderModel->getById($order_id); // Reload order
                } else {
                    $error = 'Error al marcar como entregada';
                }
            }
            break;
    }
}

// Get order items and payments
$order_items = $orderModel->getItems($order_id);
$payments = $paymentModel->getByOrderId($order_id);
$total_paid = $paymentModel->getTotalPaidByOrder($order_id);
$pending_amount = $order['total'] - $total_paid;

// Type texts
$type_texts = [
    'dine_in' => 'Consumo en Mesa',
    'delivery' => 'Delivery',
    'takeout' => 'Para Llevar'
];

// Status texts
$status_texts = [
    'pending' => 'Pendiente',
    'confirmed' => 'Confirmada',
    'preparing' => 'En Preparación',
    'ready' => 'Lista',
    'delivered' => 'Entregada',
    'cancelled' => 'Cancelada'
];

// Payment status texts
$payment_status_texts = [
    'pending' => 'Pendiente',
    'partial' => 'Parcial',
    'paid' => 'Pagado',
    'cancelled' => 'Cancelado'
];

// Payment method texts
$method_texts = [
    'cash' => 'Efectivo',
    'card' => 'Tarjeta',
    'transfer' => 'Transferencia',
    'qr' => 'QR/Digital'
];

// Status colors
$status_colors = [
    'pending' => 'warning',
    'confirmed' => 'info',
    'preparing' => 'primary',
    'ready' => 'success',
    'delivered' => 'secondary',
    'cancelled' => 'danger'
];

$payment_status_colors = [
    'pending' => 'warning',
    'partial' => 'info',
    'paid' => 'success',
    'cancelled' => 'danger'
];

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
    <title>Orden #<?php echo $order['order_number']; ?> - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
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

    /* Order info */
    .order-info-card {
        background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
        color: white;
        border: none;
    }

    .order-info-card .card-body {
        padding: 1.5rem;
    }

    .order-number {
        font-size: 1.5rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
    }

    /* Status badges */
    .status-badge {
        font-size: 0.875rem;
        padding: 0.5rem 1rem;
        border-radius: 50px;
        font-weight: 600;
    }

    /* Tables */
    .table {
        color: #212529 !important;
    }

    .table th {
        background: #f8f9fa !important;
        color: #212529 !important;
        font-weight: 600;
        border-bottom: 2px solid #dee2e6;
    }

    /* Buttons */
    .btn-print {
        background: var(--primary-color);
        border-color: var(--primary-color);
        color: white;
    }

    .btn-print:hover {
        background: var(--secondary-color);
        border-color: var(--secondary-color);
        color: white;
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

        .order-number {
            font-size: 1.25rem;
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
                    <h2 class="mb-0">Detalle de Orden</h2>
                    <p class="text-muted mb-0">Orden #<?php echo $order['order_number']; ?></p>
                </div>
                <div>
                    <a href="orders.php" class="btn btn-secondary me-2">
                        <i class="fas fa-arrow-left me-1"></i>
                        Volver
                    </a>
                    <button class="btn btn-print" onclick="window.open('print-order.php?id=<?php echo $order['id']; ?>&autoprint=1', '_blank')">
                        <i class="fas fa-print me-1"></i>
                        Imprimir Ticket
                    </button>
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

        <div class="row">
            <!-- Order Information -->
            <div class="col-lg-8">
                <!-- Order Info Card -->
                <div class="card order-info-card mb-4">
                    <div class="card-body">
                        <div class="order-number">#<?php echo $order['order_number']; ?></div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-2">
                                    <i class="fas fa-calendar me-2"></i>
                                    <strong>Fecha:</strong> <?php echo formatDateTime($order['created_at']); ?>
                                </div>
                                <div class="mb-2">
                                    <i class="fas fa-utensils me-2"></i>
                                    <strong>Tipo:</strong> <?php echo $type_texts[$order['type']]; ?>
                                </div>
                                <?php if ($order['table_number']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-chair me-2"></i>
                                        <strong>Mesa:</strong> <?php echo $order['table_number']; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <?php if ($order['customer_name']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-user me-2"></i>
                                        <strong>Cliente:</strong> <?php echo htmlspecialchars($order['customer_name']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['customer_phone']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-phone me-2"></i>
                                        <?php echo htmlspecialchars($order['customer_phone']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($order['waiter_name']): ?>
                                    <div class="mb-2">
                                        <i class="fas fa-concierge-bell me-2"></i>
                                        <strong>Mesero:</strong> <?php echo htmlspecialchars($order['waiter_name']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-list me-2"></i>
                        Productos de la Orden
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-center">Cantidad</th>
                                        <th class="text-end">Precio Unit.</th>
                                        <th class="text-end">Subtotal</th>
                                        <?php if ($can_kitchen): ?>
                                            <th class="text-center">Estado</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($order_items as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                                <?php if ($item['notes']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($item['notes']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end"><?php echo formatPrice($item['unit_price']); ?></td>
                                            <td class="text-end"><strong><?php echo formatPrice($item['subtotal']); ?></strong></td>
                                            <?php if ($can_kitchen): ?>
                                                <td class="text-center">
                                                    <span class="badge bg-<?php echo $item['status'] === 'ready' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($item['status']); ?>
                                                    </span>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                                        <td class="text-end"><strong><?php echo formatPrice($order['subtotal']); ?></strong></td>
                                        <?php if ($can_kitchen): ?><td></td><?php endif; ?>
                                    </tr>
                                    <?php if ($order['tax'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end">IVA (<?php echo number_format($tax_rate, 0); ?>%):</td>
                                            <td class="text-end"><?php echo formatPrice($order['tax']); ?></td>
                                            <?php if ($can_kitchen): ?><td></td><?php endif; ?>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($order['delivery_fee'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end">Envío:</td>
                                            <td class="text-end"><?php echo formatPrice($order['delivery_fee']); ?></td>
                                            <?php if ($can_kitchen): ?><td></td><?php endif; ?>
                                        </tr>
                                    <?php endif; ?>
                                    <?php if ($order['discount'] > 0): ?>
                                        <tr>
                                            <td colspan="3" class="text-end">Descuento:</td>
                                            <td class="text-end">-<?php echo formatPrice($order['discount']); ?></td>
                                            <?php if ($can_kitchen): ?><td></td><?php endif; ?>
                                        </tr>
                                    <?php endif; ?>
                                    <tr class="table-active">
                                        <td colspan="3" class="text-end"><strong>TOTAL:</strong></td>
                                        <td class="text-end"><strong style="font-size: 1.25rem;"><?php echo formatPrice($order['total']); ?></strong></td>
                                        <?php if ($can_kitchen): ?><td></td><?php endif; ?>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Payments -->
                <?php if (!empty($payments) || $can_payment): ?>
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-credit-card me-2"></i>
                            Pagos
                        </div>
                        <div class="card-body">
                            <?php if (!empty($payments)): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Método</th>
                                                <th>Referencia</th>
                                                <th>Fecha</th>
                                                <th class="text-end">Monto</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($payments as $payment): ?>
                                                <tr>
                                                    <td><?php echo $method_texts[$payment['method']]; ?></td>
                                                    <td><?php echo htmlspecialchars($payment['reference'] ?? '-'); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($payment['created_at'])); ?></td>
                                                    <td class="text-end"><?php echo formatPrice($payment['amount']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr class="table-active">
                                                <td colspan="3" class="text-end"><strong>Total Pagado:</strong></td>
                                                <td class="text-end"><strong><?php echo formatPrice($total_paid); ?></strong></td>
                                            </tr>
                                            <?php if ($pending_amount > 0): ?>
                                                <tr class="table-warning">
                                                    <td colspan="3" class="text-end"><strong>Saldo Pendiente:</strong></td>
                                                    <td class="text-end"><strong><?php echo formatPrice($pending_amount); ?></strong></td>
                                                </tr>
                                            <?php endif; ?>
                                        </tfoot>
                                    </table>
                                </div>
                            <?php else: ?>
                                <p class="text-muted mb-0">No hay pagos registrados</p>
                            <?php endif; ?>

                            <?php if ($can_payment && $order['payment_status'] !== 'paid' && $order['status'] !== 'cancelled'): ?>
                                <hr>
                                <h6>Registrar Pago</h6>
                                <form method="POST" class="row g-3">
                                    <input type="hidden" name="action" value="add_payment">
                                    <div class="col-md-4">
                                        <label class="form-label">Método</label>
                                        <select name="payment_method" class="form-select" required>
                                            <option value="cash">Efectivo</option>
                                            <option value="card">Tarjeta</option>
                                            <option value="transfer">Transferencia</option>
                                            <option value="qr">QR/Digital</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Monto</label>
                                        <input type="number" name="amount" class="form-control" step="0.01" max="<?php echo $pending_amount; ?>" value="<?php echo $pending_amount; ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Referencia</label>
                                        <input type="text" name="reference" class="form-control" placeholder="Opcional">
                                    </div>
                                    <div class="col-12">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-plus me-1"></i>
                                            Registrar Pago
                                        </button>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Status Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle me-2"></i>
                        Estado de la Orden
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Estado de Orden</label>
                            <div>
                                <span class="badge bg-<?php echo $status_colors[$order['status']]; ?> status-badge">
                                    <?php echo $status_texts[$order['status']]; ?>
                                </span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Estado de Pago</label>
                            <div>
                                <span class="badge bg-<?php echo $payment_status_colors[$order['payment_status']]; ?> status-badge">
                                    <?php echo $payment_status_texts[$order['payment_status']]; ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($can_edit && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                            <hr>
                            <h6>Actualizar Estado</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_status">
                                <div class="mb-3">
                                    <select name="status" class="form-select">
                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                        <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmada</option>
                                        <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>En Preparación</option>
                                        <option value="ready" <?php echo $order['status'] === 'ready' ? 'selected' : ''; ?>>Lista</option>
                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Entregada</option>
                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-1"></i>
                                    Actualizar Estado
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-tasks me-2"></i>
                        Acciones
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($order['customer_phone'] && ($order['type'] === 'delivery' || $order['type'] === 'takeout')): ?>
                                <?php 
                                $whatsapp_message = "Hola! Tu pedido #{$order['order_number']} está listo para retirar/entrega.";
                                $whatsapp_url = sendWhatsAppLink($order['customer_phone'], $whatsapp_message);
                                ?>
                                <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn btn-success">
                                    <i class="fab fa-whatsapp me-1"></i>
                                    WhatsApp
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-print" onclick="window.open('print-order.php?id=<?php echo $order['id']; ?>&autoprint=1', '_blank')">
                                <i class="fas fa-receipt me-1"></i>
                                Imprimir Ticket
                            </button>
                            
                            <?php if ($can_edit && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                <a href="order-create.php?order_id=<?php echo $order['id']; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit me-1"></i>
                                    Editar Orden
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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
    </script>
</body>
</html>
