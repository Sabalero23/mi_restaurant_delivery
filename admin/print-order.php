<?php
// admin/print-order-minimal.php - Ultra Minimalista
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Order.php';

$auth = new Auth();
$auth->requireLogin();

$orderModel = new Order();

// Get order ID
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id) {
    die('Orden no encontrada');
}

// Get order details
$order = $orderModel->getById($order_id);
if (!$order) {
    die('Orden no encontrada');
}

// Get order items
$order_items = $orderModel->getItems($order_id);

// Get settings
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Payment status texts
$payment_status_texts = [
    'pending' => 'PENDIENTE',
    'partial' => 'PARCIAL',
    'paid' => 'PAGADO',
    'cancelled' => 'CANCELADO'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo $order['order_number']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @page {
            size: 80mm auto;
            margin: 0;
        }
        
        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.2;
            width: 80mm;
            margin: 0 auto;
            padding: 3mm;
            background: white;
            color: #000;
        }
        
        .center {
            text-align: center;
        }
        
        .bold {
            font-weight: bold;
        }
        
        .line {
            border-top: 1px dashed #000;
            margin: 5px 0;
        }
        
        .double-line {
            border-top: 2px solid #000;
            margin: 5px 0;
        }
        
        h1 {
            font-size: 18px;
            margin: 5px 0;
        }
        
        .row {
            display: flex;
            justify-content: space-between;
            margin: 2px 0;
        }
        
        .item {
            margin: 3px 0;
        }
        
        .no-print {
            display: block;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
        }
        
        .controls {
            position: fixed;
            top: 10px;
            right: 10px;
            background: white;
            padding: 15px;
            border: 2px solid #667eea;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        
        .controls button {
            padding: 8px 12px;
            margin: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            font-weight: bold;
        }
        
        .print-btn {
            background: #667eea;
            color: white;
        }
        
        .print-btn:hover {
            background: #5568d3;
        }
        
        .close-btn {
            background: #6c757d;
            color: white;
        }
        
        .close-btn:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="controls no-print">
        <button class="print-btn" onclick="window.print()">
            🖨️ Imprimir
        </button>
        <button class="close-btn" onclick="window.close()">
            ✖️ Cerrar
        </button>
    </div>

    <div class="center">
        <h1><?php echo strtoupper($restaurant_name); ?></h1>
    </div>
    
    <div class="line"></div>
    
    <div class="row bold">
        <span>ORDEN: <?php echo $order['order_number']; ?></span>
    </div>
    
    <!-- Fecha y Hora -->
    <div style="margin: 2px 0;">
        Fecha: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
    </div>
    
    <!-- Atendido por -->
    <?php if ($order['waiter_name']): ?>
        <div style="margin: 2px 0;">
            Atendido por: <?php echo $order['waiter_name']; ?>
        </div>
    <?php endif; ?>
    
    <div class="line"></div>
    
    <!-- Items - Solo nombre y precio -->
    <?php foreach ($order_items as $item): ?>
        <div class="item">
            <div class="row">
                <span><?php echo $item['quantity']; ?>x <?php echo $item['product_name']; ?></span>
                <span><?php echo formatPrice($item['subtotal']); ?></span>
            </div>
        </div>
    <?php endforeach; ?>
    
    <div class="double-line"></div>
    
    <!-- Total -->
    <div class="row bold" style="font-size: 16px;">
        <span>TOTAL:</span>
        <span><?php echo formatPrice($order['total']); ?></span>
    </div>
    
    <div class="line"></div>
    
    <!-- Estado de Pago - Solo el estado -->
    <div class="center bold" style="margin: 5px 0;">
        ESTADO: <?php echo $payment_status_texts[$order['payment_status']]; ?>
    </div>
    
    <div class="line"></div>
    
    <!-- Footer -->
    <div class="center" style="font-size: 11px; margin-top: 8px;">
        <p>Gracias por elegir <?php echo $restaurant_name; ?></p>
    </div>
    
    <script>
        // Auto-imprimir solo si se pasa el parámetro
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoprint') === '1') {
            window.addEventListener('load', function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            });
        }
    </script>
</body>
</html>
