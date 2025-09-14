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

$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden #<?php echo $order['order_number']; ?> - <?php echo $restaurant_name; ?></title>
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

        /* Order status colors */
        .status-pending { color: #6c757d; }
        .status-confirmed { color: #17a2b8; }
        .status-preparing { color: #ffc107; }
        .status-ready { color: #28a745; }
        .status-delivered { color: #007bff; }
        .status-cancelled { color: #dc3545; }

        /* Order cards */
        .order-info-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }

        .order-item-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .order-item-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        /* Payment section */
        .payment-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .payment-method-cash { border-left: 4px solid #28a745; }
        .payment-method-card { border-left: 4px solid #007bff; }
        .payment-method-transfer { border-left: 4px solid #17a2b8; }
        .payment-method-qr { border-left: 4px solid #6f42c1; }

        /* Page header */
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        /* Tax breakdown styling */
        .tax-breakdown {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        /* Print styles */
        @media print {
            .sidebar,
            .mobile-topbar,
            .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .page-header {
                box-shadow: none;
                border: 1px solid #ddd;
            }
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

            .page-header {
                padding: 1rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
                padding-top: 4.5rem;
            }

            .page-header .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .page-header .btn {
                margin-top: 1rem;
                width: 100%;
            }
        }

        /* Status badges */
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
        }

        /* Ticket styles */
        .ticket {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            font-family: 'Courier New', monospace;
        }

        .ticket-header {
            text-align: center;
            border-bottom: 2px dashed #ddd;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .ticket-footer {
            text-align: center;
            border-top: 2px dashed #ddd;
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .ticket-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .ticket-total {
            border-top: 1px solid #ddd;
            padding-top: 0.5rem;
            margin-top: 0.5rem;
            font-weight: bold;
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
                    Orden #<?php echo $order['order_number']; ?>
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
            <small>Detalles de Orden</small>
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
                        <i class="fas fa-receipt me-2"></i>
                        Orden #<?php echo $order['order_number']; ?>
                    </h2>
                    <p class="text-muted mb-0">
                        <?php
                        $type_texts = [
                            'dine_in' => 'Consumo en Mesa',
                            'delivery' => 'Delivery',
                            'takeout' => 'Para Llevar'
                        ];
                        echo $type_texts[$order['type']];
                        
                        if ($order['table_number']) {
                            echo ' - ' . htmlspecialchars($order['table_number']);
                        }
                        ?>
                    </p>
                </div>
                <div class="d-flex gap-2 no-print">
                    <?php if ($can_edit && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                        <a href="order-create.php?order_id=<?php echo $order['id']; ?>" class="btn btn-warning">
                            <i class="fas fa-edit me-1"></i>
                            Editar
                        </a>
                    <?php endif; ?>
                    
                    <button class="btn btn-info" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>
                        Imprimir
                    </button>
                    
                    <a href="<?php echo $order['table_id'] ? 'tables.php' : 'orders.php'; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i>
                        Volver
                    </a>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show no-print" role="alert">
                <i class="fas fa-check me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show no-print" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Order Information -->
            <div class="col-lg-8">
                <!-- Order Header Info -->
                <div class="card order-info-card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="card-title mb-3">
                                    <i class="fas fa-info-circle me-2"></i>
                                    Información de la Orden
                                </h5>
                                
                                <div class="mb-2">
                                    <strong>Estado:</strong>
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
                                    ?>
                                    <span class="badge bg-<?php echo $status_colors[$order['status']]; ?> status-badge">
                                        <?php echo $status_texts[$order['status']]; ?>
                                    </span>
                                </div>
                                
                                <div class="mb-2">
                                    <strong>Fecha y Hora:</strong>
                                    <?php echo formatDateTime($order['created_at']); ?>
                                </div>
                                
                                <?php if ($order['waiter_name']): ?>
                                    <div class="mb-2">
                                        <strong>Atendido por:</strong>
                                        <?php echo htmlspecialchars($order['waiter_name']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($order['notes']): ?>
                                    <div class="mb-2">
                                        <strong>Notas Internas:</strong>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($order['notes']); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6">
                                <?php if ($order['customer_name'] || $order['customer_phone'] || $order['customer_address']): ?>
                                    <h5 class="card-title mb-3">
                                        <i class="fas fa-user me-2"></i>
                                        Datos del Cliente
                                    </h5>
                                    
                                    <?php if ($order['customer_name']): ?>
                                        <div class="mb-2">
                                            <strong>Nombre:</strong>
                                            <?php echo htmlspecialchars($order['customer_name']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['customer_phone']): ?>
                                        <div class="mb-2">
                                            <strong>Teléfono:</strong>
                                            <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($order['customer_phone']); ?>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['customer_address']): ?>
                                        <div class="mb-2">
                                            <strong>Dirección:</strong>
                                            <?php echo htmlspecialchars($order['customer_address']); ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($order['customer_notes']): ?>
                                        <div class="mb-2">
                                            <strong>Notas del Cliente:</strong>
                                            <p class="text-muted mb-0"><?php echo htmlspecialchars($order['customer_notes']); ?></p>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Status Update Controls -->
                        <?php if ($can_edit && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                            <hr>
                            <div class="row no-print">
                                <div class="col-md-6">
                                    <h6>Cambiar Estado</h6>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="action" value="update_status">
                                        <select class="form-select" name="status" required>
                                            <?php if ($order['status'] === 'pending'): ?>
                                                <option value="confirmed">Confirmar</option>
                                                <option value="cancelled">Cancelar</option>
                                            <?php elseif ($order['status'] === 'confirmed'): ?>
                                                <option value="preparing">Marcar Preparando</option>
                                                <option value="cancelled">Cancelar</option>
                                            <?php elseif ($order['status'] === 'preparing'): ?>
                                                <option value="ready">Marcar Listo</option>
                                                <option value="cancelled">Cancelar</option>
                                            <?php elseif ($order['status'] === 'ready'): ?>
                                                <option value="delivered">Marcar Entregado</option>
                                            <?php endif; ?>
                                        </select>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-sync me-1"></i>
                                            Actualizar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Order Items -->
                <div class="card order-info-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-list me-2"></i>
                            Productos de la Orden
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($order_items)): ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-shopping-cart fa-3x mb-3"></i>
                                <p>No hay productos en esta orden</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($order_items as $item): ?>
                                <div class="order-item-card">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-2 text-center">
                                                <?php if ($item['product_image']): ?>
                                                    <img src="../<?php echo htmlspecialchars($item['product_image']); ?>" 
                                                         class="img-fluid rounded" style="max-height: 60px;" 
                                                         alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                                <?php else: ?>
                                                    <div class="bg-light rounded p-3">
                                                        <i class="fas fa-utensils fa-2x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col-md-4">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($item['product_name']); ?></h6>
                                                <?php if ($item['notes']): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-sticky-note me-1"></i>
                                                        <?php echo htmlspecialchars($item['notes']); ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="col-md-2 text-center">
                                                <span class="badge bg-light text-dark fs-6">
                                                    <?php echo $item['quantity']; ?>x
                                                </span>
                                            </div>
                                            
                                            <div class="col-md-2 text-center">
                                                <strong><?php echo formatPrice($item['unit_price']); ?></strong>
                                            </div>
                                            
                                            <div class="col-md-2 text-end">
                                                <strong class="text-success"><?php echo formatPrice($item['subtotal']); ?></strong>
                                                
                                                <?php if ($can_kitchen && $order['status'] === 'preparing'): ?>
                                                    <div class="mt-2 no-print">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="action" value="update_item_status">
                                                            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                                            <input type="hidden" name="item_status" value="ready">
                                                            <button type="submit" class="btn btn-sm btn-success" 
                                                                    title="Marcar como listo">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <?php if ($item['status'] !== 'pending'): ?>
                                                    <div class="mt-1">
                                                        <span class="badge bg-<?php echo $item['status'] === 'ready' ? 'success' : 'warning'; ?> small">
                                                            <?php echo $item['status'] === 'ready' ? 'Listo' : ucfirst($item['status']); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- Order Totals with Improved Tax Display -->
                            <div class="row mt-4">
                                <div class="col-md-6 offset-md-6">
                                    <div class="tax-breakdown">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal (sin impuestos):</span>
                                            <span><?php echo formatPrice($calculated_subtotal_without_tax); ?></span>
                                        </div>
                                        
                                        <?php if ($tax_rate > 0 || $calculated_tax_amount > 0): ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>IVA (<?php echo number_format($tax_rate, 1); ?>%):</span>
                                                <span><?php echo formatPrice($calculated_tax_amount); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>IVA (0%):</span>
                                                <span><?php echo formatPrice(0); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['delivery_fee'] > 0): ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>Envío:</span>
                                                <span><?php echo formatPrice($order['delivery_fee']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($order['discount'] > 0): ?>
                                            <div class="d-flex justify-content-between mb-2 text-danger">
                                                <span>Descuento:</span>
                                                <span>-<?php echo formatPrice($order['discount']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <strong>Total:</strong>
                                            <strong class="text-success fs-5"><?php echo formatPrice($order['total']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Payment Section -->
            <div class="col-lg-4">
                <!-- Payment Status -->
                <div class="card payment-card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-credit-card me-2"></i>
                            Estado de Pago
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Subtotal (sin impuestos):</span>
                                <span><?php echo formatPrice($calculated_subtotal_without_tax); ?></span>
                            </div>
                            
                            <?php if ($tax_rate > 0 || $calculated_tax_amount > 0): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>IVA (<?php echo number_format($tax_rate, 1); ?>%):</span>
                                    <span><?php echo formatPrice($calculated_tax_amount); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>IVA (0%):</span>
                                    <span><?php echo formatPrice(0); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Total a Pagar:</strong>
                                <strong class="text-primary"><?php echo formatPrice($order['total']); ?></strong>
                            </div>
                            
                            <hr>
                            
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>Total Pagado:</span>
                                <strong class="text-success"><?php echo formatPrice($total_paid); ?></strong>
                            </div>
                            
                            <?php if ($pending_amount > 0): ?>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Saldo Pendiente:</span>
                                    <strong class="text-danger"><?php echo formatPrice($pending_amount); ?></strong>
                                </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <div class="text-center">
                                <?php
                                $payment_status_colors = [
                                    'pending' => 'warning',
                                    'partial' => 'info',
                                    'paid' => 'success',
                                    'cancelled' => 'danger'
                                ];
                                
                                $payment_status_texts = [
                                    'pending' => 'Pendiente',
                                    'partial' => 'Pago Parcial',
                                    'paid' => 'Pagado',
                                    'cancelled' => 'Cancelado'
                                ];
                                ?>
                                <span class="badge bg-<?php echo $payment_status_colors[$order['payment_status']]; ?> fs-6 py-2 px-3">
                                    <?php echo $payment_status_texts[$order['payment_status']]; ?>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Add Payment Form -->
                        <?php if ($can_payment && $pending_amount > 0 && $order['status'] !== 'cancelled'): ?>
                            <hr>
                            <h6>Registrar Pago</h6>
                            <form method="POST">
                                <input type="hidden" name="action" value="add_payment">
                                
                                <div class="mb-3">
                                    <label class="form-label">Método de Pago</label>
                                    <select class="form-select" name="payment_method" required>
                                        <option value="cash">Efectivo</option>
                                        <option value="card">Tarjeta</option>
                                        <option value="transfer">Transferencia</option>
                                        <option value="qr">QR/Billetera Digital</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Monto</label>
                                    <input type="number" class="form-control" name="amount" 
                                           step="0.01" max="<?php echo $pending_amount; ?>" 
                                           value="<?php echo $pending_amount; ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Referencia (opcional)</label>
                                    <input type="text" class="form-control" name="reference" 
                                           placeholder="Nº de operación, recibo, etc.">
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">
                                    <i class="fas fa-plus me-1"></i>
                                    Registrar Pago
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Payment History -->
                <?php if (!empty($payments)): ?>
                    <div class="card payment-card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-history me-2"></i>
                                Historial de Pagos
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <?php foreach ($payments as $payment): ?>
                                <div class="card-body border-bottom payment-method-<?php echo $payment['method']; ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <div>
                                            <strong><?php echo formatPrice($payment['amount']); ?></strong>
                                            <span class="badge bg-secondary ms-2">
                                                <?php
                                                $method_texts = [
                                                    'cash' => 'Efectivo',
                                                    'card' => 'Tarjeta',
                                                    'transfer' => 'Transferencia',
                                                    'qr' => 'QR/Digital'
                                                ];
                                                echo $method_texts[$payment['method']];
                                                ?>
                                            </span>
                                        </div>
                                        <small class="text-muted">
                                            <?php echo formatDateTime($payment['created_at']); ?>
                                        </small>
                                    </div>
                                    
                                    <?php if ($payment['reference']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-hashtag me-1"></i>
                                            <?php echo htmlspecialchars($payment['reference']); ?>
                                        </small>
                                    <?php endif; ?>
                                    
                                    <?php if ($payment['user_name']): ?>
                                        <div>
                                            <small class="text-muted">
                                                <i class="fas fa-user me-1"></i>
                                                <?php echo htmlspecialchars($payment['user_name']); ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card payment-card no-print">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="fas fa-bolt me-2"></i>
                            Acciones Rápidas
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <?php if ($can_delivery && $order['type'] === 'delivery' && $order['status'] === 'ready'): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="mark_delivered">
                                    <button type="submit" class="btn btn-success w-100" 
                                            onclick="return confirm('¿Marcar como entregado?')">
                                        <i class="fas fa-motorcycle me-1"></i>
                                        Marcar Entregado
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <?php if ($order['customer_phone']): ?>
                                <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" 
                                   class="btn btn-info w-100">
                                    <i class="fas fa-phone me-1"></i>
                                    Llamar Cliente
                                </a>
                                
                                <?php 
                                $whatsapp_message = "Hola! Tu pedido #{$order['order_number']} está listo para retirar/entrega.";
                                $whatsapp_url = sendWhatsAppLink($order['customer_phone'], $whatsapp_message);
                                ?>
                                <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="btn btn-success w-100">
                                    <i class="fab fa-whatsapp me-1"></i>
                                    WhatsApp
                                </a>
                            <?php endif; ?>
                            
                            <button class="btn btn-secondary w-100" onclick="printTicket()">
                                <i class="fas fa-receipt me-1"></i>
                                Imprimir Ticket
                            </button>
                            
                            <?php if ($can_edit && $order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                                <a href="order-create.php?order_id=<?php echo $order['id']; ?>" 
                                   class="btn btn-warning w-100">
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

    <!-- Ticket Modal -->
    <div class="modal fade" id="ticketModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-receipt me-2"></i>
                        Ticket de Venta
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="ticket" id="ticketContent">
                        <div class="ticket-header">
                            <h3><?php echo htmlspecialchars($restaurant_name); ?></h3>
                            <?php if ($settings['restaurant_address']): ?>
                                <p><?php echo htmlspecialchars($settings['restaurant_address']); ?></p>
                            <?php endif; ?>
                            <?php if ($settings['restaurant_phone']): ?>
                                <p>Tel: <?php echo htmlspecialchars($settings['restaurant_phone']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <div class="ticket-order-info">
                            <p><strong>Orden:</strong> #<?php echo $order['order_number']; ?></p>
                            <p><strong>Fecha:</strong> <?php echo formatDateTime($order['created_at']); ?></p>
                            <p><strong>Tipo:</strong> <?php echo $type_texts[$order['type']]; ?></p>
                            <?php if ($order['table_number']): ?>
                                <p><strong>Mesa:</strong> <?php echo htmlspecialchars($order['table_number']); ?></p>
                            <?php endif; ?>
                            <?php if ($order['customer_name']): ?>
                                <p><strong>Cliente:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <?php endif; ?>
                            <?php if ($order['waiter_name']): ?>
                                <p><strong>Mesero:</strong> <?php echo htmlspecialchars($order['waiter_name']); ?></p>
                            <?php endif; ?>
                        </div>
                        
                        <hr>
                        
                        <div class="ticket-items">
                            <?php foreach ($order_items as $item): ?>
                                <div class="ticket-item">
                                    <div>
                                        <strong><?php echo $item['quantity']; ?>x</strong> 
                                        <?php echo htmlspecialchars($item['product_name']); ?>
                                        <?php if ($item['notes']): ?>
                                            <br><small><?php echo htmlspecialchars($item['notes']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div><?php echo formatPrice($item['subtotal']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="ticket-total">
                            <div class="ticket-item">
                                <div>Subtotal (sin impuestos):</div>
                                <div><?php echo formatPrice($calculated_subtotal_without_tax); ?></div>
                            </div>
                            
                            <?php if ($tax_rate > 0 || $calculated_tax_amount > 0): ?>
                                <div class="ticket-item">
                                    <div>IVA (<?php echo number_format($tax_rate, 1); ?>%):</div>
                                    <div><?php echo formatPrice($calculated_tax_amount); ?></div>
                                </div>
                            <?php else: ?>
                                <div class="ticket-item">
                                    <div>IVA (0%):</div>
                                    <div><?php echo formatPrice(0); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['delivery_fee'] > 0): ?>
                                <div class="ticket-item">
                                    <div>Envío:</div>
                                    <div><?php echo formatPrice($order['delivery_fee']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($order['discount'] > 0): ?>
                                <div class="ticket-item">
                                    <div>Descuento:</div>
                                    <div>-<?php echo formatPrice($order['discount']); ?></div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="ticket-item" style="font-size: 1.2rem; border-top: 2px solid #000; padding-top: 0.5rem;">
                                <div><strong>TOTAL:</strong></div>
                                <div><strong><?php echo formatPrice($order['total']); ?></strong></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($payments)): ?>
                            <hr>
                            <div class="ticket-payments">
                                <p><strong>PAGOS:</strong></p>
                                <?php foreach ($payments as $payment): ?>
                                    <div class="ticket-item">
                                        <div><?php echo $method_texts[$payment['method']]; ?>:</div>
                                        <div><?php echo formatPrice($payment['amount']); ?></div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if ($pending_amount > 0): ?>
                                    <div class="ticket-item">
                                        <div>SALDO PENDIENTE:</div>
                                        <div><strong><?php echo formatPrice($pending_amount); ?></strong></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="ticket-footer">
                            <p>¡Gracias por su preferencia!</p>
                            <p><?php echo date('d/m/Y H:i:s'); ?></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" onclick="printTicketContent()">
                        <i class="fas fa-print me-1"></i>
                        Imprimir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Mobile menu functionality
        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
            initializeDataTables();
        });

        function initializeDataTables() {
            // Initialize order items table if it exists
            if ($('#orderItemsTable').length) {
                $('#orderItemsTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "pageLength": 10,
                    "searching": true,
                    "ordering": true,
                    "info": false,
                    "paging": false,
                    "responsive": true,
                    "columnDefs": [
                        { "orderable": false, "targets": -1 } // Disable ordering on last column (actions)
                    ]
                });
            }

            // Initialize payments table if it exists
            if ($('#paymentsTable').length) {
                $('#paymentsTable').DataTable({
                    "language": {
                        "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json"
                    },
                    "pageLength": 5,
                    "searching": false,
                    "ordering": true,
                    "info": false,
                    "paging": false,
                    "responsive": true
                });
            }
        }

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

        function printTicket() {
            const modal = new bootstrap.Modal(document.getElementById('ticketModal'));
            modal.show();
        }

        function printTicketContent() {
            // Redirect to print page instead of opening popup
            window.open('print-order.php?id=<?php echo $order["id"]; ?>', '_blank');
        }

        // Format price function
        function formatPrice(price) {
    return '$' + parseFloat(price).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}
    </script>
</body>
</html>