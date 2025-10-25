<?php
// admin/print-order-kitchen.php - Versión para Cocina
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

// Type texts
$type_texts = [
    'dine_in' => 'MESA',
    'delivery' => 'DELIVERY',
    'takeout' => 'PARA LLEVAR'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden Cocina #<?php echo $order['order_number']; ?></title>
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
            font-family: Arial, Helvetica, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            width: 80mm;
            margin: 0 auto;
            padding: 5mm;
            background: white;
            color: #000;
        }
        
        .kitchen-header {
            text-align: center;
            background: #000;
            color: #fff;
            padding: 15px;
            margin: -5mm -5mm 10px -5mm;
            border-bottom: 4px solid #ff6b6b;
        }
        
        .kitchen-header h1 {
            font-size: 24px;
            margin: 0 0 5px 0;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .kitchen-header .order-number {
            font-size: 32px;
            font-weight: bold;
            color: #ff6b6b;
            margin: 10px 0;
            letter-spacing: 3px;
        }
        
        .order-type {
            background: #ff6b6b;
            color: white;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            padding: 12px;
            margin: 10px -5mm;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        
        .order-info {
            background: #f8f9fa;
            padding: 12px;
            margin: 10px -5mm;
            border-left: 5px solid #667eea;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 5px 0;
            font-size: 15px;
        }
        
        .info-row strong {
            color: #000;
        }
        
        .section-title {
            background: #667eea;
            color: white;
            font-size: 18px;
            font-weight: bold;
            padding: 10px;
            margin: 15px -5mm 10px -5mm;
            text-transform: uppercase;
        }
        
        .item {
            background: #fff;
            border: 3px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        
        .item-quantity {
            background: #ff6b6b;
            color: white;
            font-size: 28px;
            font-weight: bold;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            float: left;
            margin-right: 15px;
            margin-top: 5px;
        }
        
        .item-name {
            font-size: 20px;
            font-weight: bold;
            color: #000;
            margin-bottom: 8px;
            text-transform: uppercase;
            line-height: 1.3;
        }
        
        .item-notes {
            background: #fff9e6;
            border-left: 4px solid #ffc107;
            padding: 10px;
            margin-top: 10px;
            font-size: 14px;
            font-weight: bold;
            color: #856404;
        }
        
        .item-notes::before {
            content: '⚠️ NOTA: ';
            font-weight: bold;
            color: #ff6b6b;
        }
        
        .separator {
            border: none;
            border-top: 3px dashed #000;
            margin: 20px -5mm;
        }
        
        .footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 3px solid #000;
            font-size: 13px;
        }
        
        .time {
            font-size: 16px;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .no-print {
            display: block;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .kitchen-header,
            .order-type,
            .section-title,
            .item-quantity,
            .item-notes {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
        
        .controls {
            position: fixed;
            top: 10px;
            right: 10px;
            background: white;
            padding: 15px;
            border: 3px solid #667eea;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        
        .controls button {
            padding: 12px 20px;
            margin: 5px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 15px;
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
        
        /* Highlight urgent info */
        .urgent {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="controls no-print">
        <button class="print-btn" onclick="window.print()">
            🖨️ IMPRIMIR
        </button>
        <button class="close-btn" onclick="window.close()">
            ✖️ CERRAR
        </button>
    </div>

    <!-- Kitchen Header -->
    <div class="kitchen-header">
        <h1>🍴 COCINA</h1>
        <div class="order-number">#<?php echo $order['order_number']; ?></div>
    </div>
    
    <!-- Order Type -->
    <div class="order-type <?php echo $order['type'] === 'delivery' ? 'urgent' : ''; ?>">
        <?php echo $type_texts[$order['type']]; ?>
        <?php if ($order['table_number']): ?>
            - MESA <?php echo $order['table_number']; ?>
        <?php endif; ?>
    </div>
    
    <!-- Order Info -->
    <div class="order-info">
        <div class="info-row">
            <strong>📅 Hora:</strong>
            <span><?php echo date('H:i', strtotime($order['created_at'])); ?></span>
        </div>
        <?php if ($order['waiter_name']): ?>
            <div class="info-row">
                <strong>👤 Mesero:</strong>
                <span><?php echo htmlspecialchars($order['waiter_name']); ?></span>
            </div>
        <?php endif; ?>
        <?php if ($order['customer_name']): ?>
            <div class="info-row">
                <strong>🧑 Cliente:</strong>
                <span><?php echo htmlspecialchars($order['customer_name']); ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Items Section -->
    <div class="section-title">
        📋 PRODUCTOS A PREPARAR
    </div>
    
    <?php foreach ($order_items as $item): ?>
        <div class="item">
            <div class="item-quantity"><?php echo $item['quantity']; ?>x</div>
            <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
            <div style="clear: both;"></div>
            
            <?php if (!empty($item['notes'])): ?>
                <div class="item-notes">
                    <?php echo nl2br(htmlspecialchars($item['notes'])); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
    <!-- Separator -->
    <div class="separator"></div>
    
    <!-- Order Notes -->
    <?php if (!empty($order['notes']) || !empty($order['customer_notes'])): ?>
        <div class="section-title">
            📝 NOTAS IMPORTANTES
        </div>
        <div class="item-notes" style="margin: 10px 0;">
            <?php if (!empty($order['notes'])): ?>
                <div style="margin-bottom: 8px;">
                    <strong>Notas de la orden:</strong><br>
                    <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($order['customer_notes'])): ?>
                <div>
                    <strong>Notas del cliente:</strong><br>
                    <?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="separator"></div>
    <?php endif; ?>
    
    <!-- Footer -->
    <div class="footer">
        <div style="font-size: 18px; font-weight: bold; margin-bottom: 10px;">
            ¡ORDEN LISTA PARA PREPARAR!
        </div>
        <div class="time">
            🕐 <?php echo date('d/m/Y H:i:s'); ?>
        </div>
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