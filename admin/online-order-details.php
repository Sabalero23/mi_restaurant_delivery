<?php
// admin/online-order-details.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();

if (!$auth->hasPermission('online_orders')) {
    header('Location: dashboard.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['id'] ?? 0;
if (!$order_id) {
    header('Location: online-orders.php');
    exit;
}

// Obtener datos de la orden
$query = "SELECT o.*, u1.full_name as accepted_by_name, u2.full_name as rejected_by_name, 
          u3.full_name as delivered_by_name
          FROM online_orders o
          LEFT JOIN users u1 ON o.accepted_by = u1.id
          LEFT JOIN users u2 ON o.rejected_by = u2.id  
          LEFT JOIN users u3 ON o.delivered_by = u3.id
          WHERE o.id = :id";

$stmt = $db->prepare($query);
$stmt->execute(['id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header('Location: online-orders.php');
    exit;
}

// Decodificar items JSON
$order['items'] = json_decode($order['items'], true);

// Obtener configuración para tax_rate
$settings = getSettings();
$tax_rate = floatval($settings['tax_rate'] ?? 0);

// Calcular impuestos si el tax_rate es mayor a 0
$subtotal_without_tax = 0;
$tax_amount = 0;

if ($tax_rate > 0) {
    // El total incluye impuestos, calculamos el subtotal sin impuestos
    $subtotal_without_tax = $order['total'] / (1 + ($tax_rate / 100));
    $tax_amount = $order['total'] - $subtotal_without_tax;
} else {
    // Si no hay impuestos, el subtotal es igual al total
    $subtotal_without_tax = $order['total'];
    $tax_amount = 0;
}

// Procesar pagos si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'add_payment') {
            $amount = floatval($_POST['amount']);
            $method = $_POST['method'];
            $reference = $_POST['reference'] ?? '';
            
            if ($amount <= 0) {
                throw new Exception('El monto debe ser mayor a 0');
            }
            
            // Insertar pago en tabla online_orders_payments (crear tabla si no existe)
            $create_table_query = "CREATE TABLE IF NOT EXISTS online_orders_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                online_order_id INT NOT NULL,
                method ENUM('cash','card','transfer','qr') NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                reference VARCHAR(255) DEFAULT NULL,
                user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (online_order_id) REFERENCES online_orders(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )";
            $db->exec($create_table_query);
            
            $payment_query = "INSERT INTO online_orders_payments (online_order_id, method, amount, reference, user_id) 
                             VALUES (:order_id, :method, :amount, :reference, :user_id)";
            $payment_stmt = $db->prepare($payment_query);
            $payment_stmt->execute([
                'order_id' => $order_id,
                'method' => $method,
                'amount' => $amount,
                'reference' => $reference,
                'user_id' => $_SESSION['user_id']
            ]);
            
            // Actualizar estado de pago de la orden
            $total_payments_query = "SELECT SUM(amount) as total_paid FROM online_orders_payments WHERE online_order_id = :order_id";
            $total_stmt = $db->prepare($total_payments_query);
            $total_stmt->execute(['order_id' => $order_id]);
            $total_paid = $total_stmt->fetchColumn() ?? 0;
            
            $payment_status = 'pending';
            if ($total_paid >= $order['total']) {
                $payment_status = 'paid';
            } elseif ($total_paid > 0) {
                $payment_status = 'partial';
            }
            
            $update_query = "UPDATE online_orders SET payment_status = :payment_status WHERE id = :id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->execute([
                'payment_status' => $payment_status,
                'id' => $order_id
            ]);
            
            $_SESSION['success_message'] = 'Pago registrado correctamente';
            header("Location: online-order-details.php?id=$order_id");
            exit;
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
}

// Obtener pagos de la orden
$payments_query = "SELECT p.*, u.full_name as user_name 
                   FROM online_orders_payments p 
                   LEFT JOIN users u ON p.user_id = u.id 
                   WHERE p.online_order_id = :order_id 
                   ORDER BY p.created_at DESC";
$payments_stmt = $db->prepare($payments_query);
$payments_stmt->execute(['order_id' => $order_id]);
$payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_paid = array_sum(array_column($payments, 'amount'));
$remaining = $order['total'] - $total_paid;

function getPaymentMethodText($method) {
    $methods = [
        'cash' => 'Efectivo',
        'card' => 'Tarjeta',
        'transfer' => 'Transferencia',
        'qr' => 'QR/Digital'
    ];
    return $methods[$method] ?? $method;
}

function getStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'accepted' => 'info', 
        'preparing' => 'warning',
        'ready' => 'success',
        'delivered' => 'primary',
        'rejected' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

function getStatusText($status) {
    $texts = [
        'pending' => 'Pendiente',
        'accepted' => 'Aceptado',
        'preparing' => 'Preparando', 
        'ready' => 'Listo',
        'delivered' => 'Entregado',
        'rejected' => 'Rechazado'
    ];
    return $texts[$status] ?? $status;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle Pedido Online #<?php echo $order['order_number']; ?> - <?php echo $settings['restaurant_name']; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        
        .detail-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .detail-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px 15px 0 0;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: bold;
        }
        
        .payment-card {
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            background: #f8f9fa;
        }
        
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1rem;
            padding-left: 1rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -0.5rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background: #007bff;
        }
        
        .btn-payment {
            background: linear-gradient(45deg, #28a745, #20c997);
            border: none;
            color: white;
        }
        
        .tax-breakdown {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4 py-3">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex align-items-center mb-3">
                    <a href="online-orders.php" class="btn btn-outline-secondary me-3">
                        <i class="fas fa-arrow-left me-1"></i>
                        Volver
                    </a>
                    <h1 class="mb-0">Detalle Pedido Online #<?php echo $order['order_number']; ?></h1>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Información Principal -->
            <div class="col-lg-8">
                <!-- Header de la Orden -->
                <div class="detail-card">
                    <div class="detail-header">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-2">Pedido #<?php echo $order['order_number']; ?></h2>
                                <p class="mb-1">Cliente: <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                                <p class="mb-1">Fecha: <?php echo formatDateTime($order['created_at']); ?></p>
                                <p class="mb-0">Total: <strong><?php echo formatPrice($order['total']); ?></strong></p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <span class="status-badge bg-<?php echo getStatusColor($order['status']); ?>">
                                    <?php echo getStatusText($order['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        <!-- Información del Cliente -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5><i class="fas fa-user me-2"></i>Información del Cliente</h5>
                                <p class="mb-1"><strong>Nombre:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                                <p class="mb-1"><strong>Teléfono:</strong> 
                                    <a href="tel:<?php echo $order['customer_phone']; ?>" class="text-decoration-none">
                                        <?php echo htmlspecialchars($order['customer_phone']); ?>
                                    </a>
                                </p>
                                <p class="mb-1"><strong>Dirección:</strong> <?php echo htmlspecialchars($order['customer_address']); ?></p>
                                <?php if ($order['customer_notes']): ?>
                                    <p class="mb-0"><strong>Notas:</strong> <?php echo htmlspecialchars($order['customer_notes']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h5><i class="fas fa-info-circle me-2"></i>Información de Entrega</h5>
                                <?php if ($order['delivery_distance']): ?>
                                    <p class="mb-1"><strong>Distancia:</strong> <?php echo number_format($order['delivery_distance'], 1); ?> km</p>
                                <?php endif; ?>
                                <?php if ($order['estimated_time']): ?>
                                    <p class="mb-1"><strong>Tiempo estimado:</strong> <?php echo $order['estimated_time']; ?> minutos</p>
                                <?php endif; ?>
                                <p class="mb-0"><strong>Estado de pago:</strong> 
                                    <span class="badge bg-<?php echo ($order['payment_status'] ?? 'pending') === 'paid' ? 'success' : 'warning'; ?>">
                                        <?php echo ($order['payment_status'] ?? 'pending') === 'paid' ? 'Cobrado' : 'Pendiente'; ?>
                                    </span>
                                </p>
                            </div>
                        </div>

                        <!-- Items del Pedido -->
                        <div class="mb-4">
                            <h5><i class="fas fa-list me-2"></i>Items del Pedido</h5>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unit.</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order['items'] as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                                <td><?php echo $item['quantity']; ?></td>
                                                <td><?php echo formatPrice($item['price']); ?></td>
                                                <td><?php echo formatPrice($item['price'] * $item['quantity']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Desglose de totales con impuestos -->
                            <div class="tax-breakdown">
                                <div class="row">
                                    <div class="col-md-6 offset-md-6">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span>Subtotal (sin impuestos):</span>
                                            <span><?php echo formatPrice($subtotal_without_tax); ?></span>
                                        </div>
                                        <?php if ($tax_rate > 0): ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>IVA (<?php echo number_format($tax_rate, 1); ?>%):</span>
                                                <span><?php echo formatPrice($tax_amount); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-flex justify-content-between mb-2">
                                                <span>IVA (0%):</span>
                                                <span><?php echo formatPrice(0); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <hr>
                                        <div class="d-flex justify-content-between">
                                            <strong>TOTAL:</strong>
                                            <strong><?php echo formatPrice($order['total']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Timeline de Estados -->
                        <div>
                            <h5><i class="fas fa-clock me-2"></i>Historial</h5>
                            <div class="timeline">
                                <div class="timeline-item">
                                    <strong>Pedido creado</strong>
                                    <br><small class="text-muted"><?php echo formatDateTime($order['created_at']); ?></small>
                                </div>
                                
                                <?php if ($order['accepted_at']): ?>
                                <div class="timeline-item">
                                    <strong>Aceptado</strong>
                                    <?php if ($order['accepted_by_name']): ?>
                                        por <?php echo $order['accepted_by_name']; ?>
                                    <?php endif; ?>
                                    <br><small class="text-muted"><?php echo formatDateTime($order['accepted_at']); ?></small>
                                </div>
                                <?php endif; ?>

                                <?php if ($order['started_preparing_at']): ?>
                                <div class="timeline-item">
                                    <strong>Iniciado preparación</strong>
                                    <br><small class="text-muted"><?php echo formatDateTime($order['started_preparing_at']); ?></small>
                                </div>
                                <?php endif; ?>

                                <?php if ($order['ready_at']): ?>
                                <div class="timeline-item">
                                    <strong>Listo para entrega</strong>
                                    <br><small class="text-muted"><?php echo formatDateTime($order['ready_at']); ?></small>
                                </div>
                                <?php endif; ?>

                                <?php if ($order['delivered_at']): ?>
                                <div class="timeline-item">
                                    <strong>Entregado</strong>
                                    <?php if ($order['delivered_by_name']): ?>
                                        por <?php echo $order['delivered_by_name']; ?>
                                    <?php endif; ?>
                                    <br><small class="text-muted"><?php echo formatDateTime($order['delivered_at']); ?></small>
                                </div>
                                <?php endif; ?>

                                <?php if ($order['rejected_at']): ?>
                                <div class="timeline-item">
                                    <strong>Rechazado</strong>
                                    <?php if ($order['rejected_by_name']): ?>
                                        por <?php echo $order['rejected_by_name']; ?>
                                    <?php endif; ?>
                                    <br><small class="text-muted"><?php echo formatDateTime($order['rejected_at']); ?></small>
                                    <?php if ($order['rejection_reason']): ?>
                                        <br><small class="text-danger">Motivo: <?php echo htmlspecialchars($order['rejection_reason']); ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de Pagos -->
            <div class="col-lg-4">
                <div class="detail-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-money-bill-wave me-2"></i>
                            Gestión de Pagos
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Resumen de pagos -->
                        <div class="mb-3 p-3 bg-light rounded">
                            <div class="d-flex justify-content-between mb-2">
                                <span>Subtotal:</span>
                                <span><?php echo formatPrice($subtotal_without_tax); ?></span>
                            </div>
                            <?php if ($tax_rate > 0): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>IVA (<?php echo number_format($tax_rate, 1); ?>%):</span>
                                    <span><?php echo formatPrice($tax_amount); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span>IVA (0%):</span>
                                    <span><?php echo formatPrice(0); ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between mb-2">
                                <strong>Total del pedido:</strong>
                                <strong><?php echo formatPrice($order['total']); ?></strong>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Total cobrado:</span>
                                <strong class="text-success"><?php echo formatPrice($total_paid); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span><strong>Pendiente:</strong></span>
                                <strong class="<?php echo $remaining > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo formatPrice($remaining); ?>
                                </strong>
                            </div>
                        </div>

                        <!-- Formulario para nuevo pago -->
                        <?php if ($remaining > 0): ?>
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="action" value="add_payment">
                            
                            <div class="mb-3">
                                <label class="form-label">Método de pago</label>
                                <select class="form-select" name="method" required>
                                    <option value="">Seleccionar método</option>
                                    <option value="cash">Efectivo</option>
                                    <option value="card">Tarjeta</option>
                                    <option value="transfer">Transferencia</option>
                                    <option value="qr">QR/Digital</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Monto</label>
                                <input type="number" class="form-control" name="amount" 
                                       min="0.01" step="0.01" max="<?php echo $remaining; ?>" 
                                       value="<?php echo $remaining; ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Referencia (opcional)</label>
                                <input type="text" class="form-control" name="reference" 
                                       placeholder="Número de transacción, etc.">
                            </div>

                            <button type="submit" class="btn btn-payment w-100">
                                <i class="fas fa-plus me-1"></i>
                                Registrar Pago
                            </button>
                        </form>
                        <?php else: ?>
                        <div class="alert alert-success text-center">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <h6>Pedido Completamente Cobrado</h6>
                        </div>
                        <?php endif; ?>

                        <!-- Lista de pagos -->
                        <h6>Pagos Registrados</h6>
                        <?php if (empty($payments)): ?>
                            <div class="text-muted text-center py-3">
                                <i class="fas fa-info-circle me-1"></i>
                                No hay pagos registrados
                            </div>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                            <div class="payment-card">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo getPaymentMethodText($payment['method']); ?></strong>
                                        <br>
                                        <span class="text-success fw-bold"><?php echo formatPrice($payment['amount']); ?></span>
                                        <?php if ($payment['reference']): ?>
                                            <br><small class="text-muted">Ref: <?php echo htmlspecialchars($payment['reference']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <small class="text-muted">
                                            <?php echo formatDateTime($payment['created_at']); ?>
                                            <?php if ($payment['user_name']): ?>
                                                <br>por <?php echo $payment['user_name']; ?>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>