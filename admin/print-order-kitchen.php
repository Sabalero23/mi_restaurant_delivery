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
            font-size: 11px;
            line-height: 1.2;
            width: 80mm;
            margin: 0 auto;
            padding: 3mm;
            background: white;
            color: #000;
        }
        
        .kitchen-header {
            text-align: center;
            padding: 8px 0;
            margin-bottom: 5px;
            border-bottom: 2px solid #000;
        }
        
        .kitchen-header .order-number {
            font-size: 22px;
            font-weight: bold;
            color: #000;
            margin: 5px 0;
            letter-spacing: 2px;
        }
        
        .order-type {
            background: #000;
            color: white;
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            padding: 6px;
            margin: 5px -3mm;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .order-info {
            padding: 6px 0;
            margin: 5px 0;
            border-top: 1px solid #ddd;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }
        
        .info-row strong {
            color: #000;
        }
        
        .section-title {
            font-size: 12px;
            font-weight: bold;
            padding: 5px 0;
            margin: 8px 0 5px 0;
            text-transform: uppercase;
            border-bottom: 2px solid #000;
        }
        
        .item {
            background: #fff;
            border: 1px solid #000;
            border-radius: 3px;
            padding: 5px 6px;
            margin-bottom: 4px;
            page-break-inside: avoid;
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }
        
        .item-quantity {
            background: #000;
            color: white;
            font-size: 13px;
            font-weight: bold;
            min-width: 24px;
            height: 24px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .item-content {
            flex: 1;
        }
        
        .item-name {
            font-size: 12px;
            font-weight: bold;
            color: #000;
            text-transform: uppercase;
            line-height: 1.2;
        }
        
        .item-notes {
            background: #fff9e6;
            border-left: 3px solid #ffc107;
            padding: 5px;
            margin-top: 4px;
            font-size: 10px;
            font-weight: bold;
            color: #856404;
        }
        
        .item-notes::before {
            content: '⚠️ ';
            font-weight: bold;
            color: #000;
        }
        
        .separator {
            border: none;
            border-top: 2px dashed #000;
            margin: 10px -3mm;
        }
        
        .footer {
            text-align: center;
            margin-top: 10px;
            padding-top: 8px;
            border-top: 2px solid #000;
            font-size: 10px;
        }
        
        .time {
            font-size: 11px;
            font-weight: bold;
            margin-top: 5px;
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
            
            .order-type,
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
            border: 3px solid #000;
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
            background: #000;
            color: white;
        }
        
        .print-btn:hover {
            background: #333;
        }
        
        .close-btn {
            background: #6c757d;
            color: white;
        }
        
        .close-btn:hover {
            background: #5a6268;
        }
        
        .notes-section {
            margin: 8px 0;
        }
        
        .notes-section .item-notes {
            margin: 5px 0;
            padding: 6px;
            font-size: 10px;
        }
        
        .notes-section strong {
            display: block;
            margin-bottom: 3px;
            font-size: 11px;
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
        <div class="order-number">#<?php echo $order['order_number']; ?></div>
    </div>
    
    <!-- Order Type -->
    <div class="order-type">
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
        📋 PRODUCTOS
    </div>
    
    <?php foreach ($order_items as $item): ?>
        <div class="item">
            <div class="item-quantity"><?php echo $item['quantity']; ?>x</div>
            <div class="item-content">
                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                <?php if (!empty($item['notes'])): ?>
                    <div class="item-notes">
                        <?php echo nl2br(htmlspecialchars($item['notes'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <!-- Order Notes -->
    <?php if (!empty($order['notes']) || !empty($order['customer_notes'])): ?>
        <div class="separator"></div>
        <div class="section-title">
            📝 NOTAS
        </div>
        <div class="notes-section">
            <?php if (!empty($order['notes'])): ?>
                <div class="item-notes">
                    <strong>Orden:</strong>
                    <?php echo nl2br(htmlspecialchars($order['notes'])); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($order['customer_notes'])): ?>
                <div class="item-notes">
                    <strong>Cliente:</strong>
                    <?php echo nl2br(htmlspecialchars($order['customer_notes'])); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    
    <!-- Separator -->
    <div class="separator"></div>
    
    <!-- Footer -->
    <div class="footer">
        <div style="font-size: 12px; font-weight: bold; margin-bottom: 5px;">
            ¡ORDEN LISTA!
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