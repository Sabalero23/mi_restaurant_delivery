<?php
// admin/print-order.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Order.php';
require_once '../models/Payment.php';

$auth = new Auth();
$auth->requireLogin();

$orderModel = new Order();
$paymentModel = new Payment();

// Get order ID
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if (!$order_id) {
    die('ID de orden no válido');
}

// Get order details
$order = $orderModel->getById($order_id);
if (!$order) {
    die('Orden no encontrada');
}

// Get order items and payments
$order_items = $orderModel->getItems($order_id);
$payments = $paymentModel->getByOrderId($order_id);
$total_paid = $paymentModel->getTotalPaidByOrder($order_id);
$pending_amount = $order['total'] - $total_paid;

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$restaurant_address = $settings['restaurant_address'] ?? '';
$restaurant_phone = $settings['restaurant_phone'] ?? '';

// Type texts
$type_texts = [
    'dine_in' => 'Consumo en Mesa',
    'delivery' => 'Delivery',
    'takeout' => 'Para Llevar'
];

// Payment method texts
$method_texts = [
    'cash' => 'Efectivo',
    'card' => 'Tarjeta',
    'transfer' => 'Transferencia',
    'qr' => 'QR/Digital'
];

function truncateText($text, $maxLength) {
    if (strlen($text) <= $maxLength) return $text;
    return substr($text, 0, $maxLength - 3) . '...';
}

function formatPricePrint($price) {
    return '$' . number_format(floatval($price), 2, '.', ',');
}

function centerText($text, $width) {
    $textLength = strlen($text);
    if ($textLength >= $width) return $text;
    $padding = ($width - $textLength) / 2;
    return str_repeat(' ', floor($padding)) . $text . str_repeat(' ', ceil($padding));
}

function alignRight($text, $width) {
    $textLength = strlen($text);
    if ($textLength >= $width) return $text;
    return str_repeat(' ', $width - $textLength) . $text;
}

// Determine ticket width based on URL parameter or default to 58mm
$ticket_width = isset($_GET['width']) ? intval($_GET['width']) : 58;
if (!in_array($ticket_width, [58, 80])) {
    $ticket_width = 58;
}

// Set character limits based on ticket width
$char_limit = $ticket_width == 58 ? 32 : 48;
$item_name_limit = $ticket_width == 58 ? 18 : 30;
$line_separator = str_repeat('-', $char_limit);
$double_line = str_repeat('=', $char_limit);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket - Orden #<?php echo $order['order_number']; ?></title>
    <style>
        @media print {
            @page {
                size: <?php echo $ticket_width; ?>mm auto;
                margin: 0;
            }
        }
        
        body {
            font-family: 'Courier New', 'Consolas', 'Monaco', monospace;
            font-size: <?php echo $ticket_width == 58 ? '10px' : '11px'; ?>;
            line-height: 1.2;
            margin: 0;
            padding: 5mm;
            background: white;
            color: black;
            width: <?php echo $ticket_width; ?>mm;
            max-width: <?php echo $ticket_width; ?>mm;
            box-sizing: border-box;
        }
        
        .ticket {
            width: 100%;
            max-width: <?php echo $char_limit; ?>ch;
        }
        
        .header {
            text-align: center;
            margin-bottom: 3mm;
        }
        
        .restaurant-name {
            font-weight: bold;
            font-size: <?php echo $ticket_width == 58 ? '12px' : '14px'; ?>;
            margin-bottom: 1mm;
        }
        
        .restaurant-info {
            font-size: <?php echo $ticket_width == 58 ? '9px' : '10px'; ?>;
            margin-bottom: 0.5mm;
        }
        
        .order-info {
            margin: 3mm 0;
        }
        
        .order-info div {
            margin-bottom: 1mm;
        }
        
        .items-section {
            margin: 3mm 0;
        }
        
        .item {
            margin-bottom: 1mm;
            white-space: pre-line;
        }
        
        .item-line {
            display: flex;
            justify-content: space-between;
            white-space: pre;
        }
        
        .totals-section {
            margin-top: 3mm;
            border-top: 1px dashed #000;
            padding-top: 2mm;
        }
        
        .total-line {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1mm;
            white-space: pre;
        }
        
        .total-final {
            border-top: 1px solid #000;
            padding-top: 1mm;
            margin-top: 2mm;
            font-weight: bold;
        }
        
        .payments-section {
            margin-top: 3mm;
            border-top: 1px dashed #000;
            padding-top: 2mm;
        }
        
        .footer {
            text-align: center;
            margin-top: 5mm;
            border-top: 1px dashed #000;
            padding-top: 2mm;
            font-size: <?php echo $ticket_width == 58 ? '8px' : '9px'; ?>;
        }
        
        .line-separator {
            border: none;
            border-top: 1px dashed #000;
            margin: 2mm 0;
        }
        
        .double-line {
            border: none;
            border-top: 2px solid #000;
            margin: 2mm 0;
        }
        
        pre {
            font-family: inherit;
            margin: 0;
            white-space: pre-wrap;
            word-wrap: break-word;
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
        }
        
        .print-controls {
            position: fixed;
            top: 10px;
            right: 10px;
            background: white;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .width-selector {
            margin-bottom: 10px;
        }
        
        .width-selector select {
            margin-left: 5px;
            padding: 2px 5px;
        }
    </style>
</head>
<body>
    <!-- Print Controls -->
    <div class="print-controls no-print">
        <div class="width-selector">
            <label>Ancho del ticket:</label>
            <select onchange="changeWidth(this.value)">
                <option value="58" <?php echo $ticket_width == 58 ? 'selected' : ''; ?>>58mm</option>
                <option value="80" <?php echo $ticket_width == 80 ? 'selected' : ''; ?>>80mm</option>
            </select>
        </div>
        <button onclick="window.print()" style="padding: 5px 10px; margin-right: 5px;">
            <i class="fas fa-print"></i> Imprimir
        </button>
        <button onclick="window.close()" style="padding: 5px 10px;">
            Cerrar
        </button>
    </div>

    <div class="ticket">
        <!-- Header -->
        <div class="header">
            <div class="restaurant-name"><?php echo centerText(strtoupper($restaurant_name), $char_limit); ?></div>
            <?php if ($restaurant_address): ?>
                <div class="restaurant-info"><?php echo centerText($restaurant_address, $char_limit); ?></div>
            <?php endif; ?>
            <?php if ($restaurant_phone): ?>
                <div class="restaurant-info"><?php echo centerText("Tel: $restaurant_phone", $char_limit); ?></div>
            <?php endif; ?>
        </div>
        
        <hr class="line-separator">
        
        <!-- Order Information -->
        <div class="order-info">
            <pre>ORDEN: #<?php echo $order['order_number']; ?>
FECHA: <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
TIPO:  <?php echo $type_texts[$order['type']]; ?></pre>
            
            <?php if ($order['table_number']): ?>
                <pre>MESA:  <?php echo $order['table_number']; ?></pre>
            <?php endif; ?>
            
            <?php if ($order['customer_name']): ?>
                <pre>CLIENTE: <?php echo truncateText($order['customer_name'], $char_limit - 9); ?></pre>
            <?php endif; ?>
            
            <?php if ($order['customer_phone']): ?>
                <pre>TEL: <?php echo $order['customer_phone']; ?></pre>
            <?php endif; ?>
            
            <?php if ($order['customer_address']): ?>
                <pre>DIR: <?php echo truncateText($order['customer_address'], $char_limit - 5); ?></pre>
            <?php endif; ?>
            
            <?php if ($order['waiter_name']): ?>
                <pre>MESERO: <?php echo truncateText($order['waiter_name'], $char_limit - 8); ?></pre>
            <?php endif; ?>
        </div>
        
        <hr class="line-separator">
        
        <!-- Items -->
        <div class="items-section">
            <?php foreach ($order_items as $item): ?>
                <div class="item">
                    <?php 
                    $item_name = truncateText($item['product_name'], $item_name_limit);
                    $quantity = $item['quantity'] . 'x';
                    $price = formatPricePrint($item['subtotal']);
                    $price_pos = $char_limit - strlen($price);
                    $qty_pos = max(0, $price_pos - strlen($quantity) - 1);
                    
                    // First line: product name
                    echo $item_name . "\n";
                    
                    // Second line: quantity and price
                    $second_line = str_repeat(' ', $qty_pos) . $quantity . ' ' . $price;
                    echo $second_line . "\n";
                    
                    // Notes if any
                    if ($item['notes']): 
                        echo "  " . truncateText($item['notes'], $char_limit - 2) . "\n";
                    endif;
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <hr class="line-separator">
        
        <!-- Totals -->
        <div class="totals-section">
            <?php
            $subtotal_text = "SUBTOTAL:";
            $subtotal_price = formatPricePrint($order['subtotal']);
            $subtotal_line = $subtotal_text . str_repeat(' ', $char_limit - strlen($subtotal_text) - strlen($subtotal_price)) . $subtotal_price;
            echo "<pre>$subtotal_line</pre>";
            
            if ($order['tax'] > 0):
                $tax_rate = $settings['tax_rate'] ?? 21;
                $tax_text = "IVA ($tax_rate%):";
                $tax_price = formatPricePrint($order['tax']);
                $tax_line = $tax_text . str_repeat(' ', $char_limit - strlen($tax_text) - strlen($tax_price)) . $tax_price;
                echo "<pre>$tax_line</pre>";
            endif;
            
            if ($order['delivery_fee'] > 0):
                $delivery_text = "ENVIO:";
                $delivery_price = formatPricePrint($order['delivery_fee']);
                $delivery_line = $delivery_text . str_repeat(' ', $char_limit - strlen($delivery_text) - strlen($delivery_price)) . $delivery_price;
                echo "<pre>$delivery_line</pre>";
            endif;
            
            if ($order['discount'] > 0):
                $discount_text = "DESCUENTO:";
                $discount_price = "-" . formatPricePrint($order['discount']);
                $discount_line = $discount_text . str_repeat(' ', $char_limit - strlen($discount_text) - strlen($discount_price)) . $discount_price;
                echo "<pre>$discount_line</pre>";
            endif;
            ?>
            
            <div class="total-final">
                <?php
                $total_text = "TOTAL:";
                $total_price = formatPricePrint($order['total']);
                $total_line = $total_text . str_repeat(' ', $char_limit - strlen($total_text) - strlen($total_price)) . $total_price;
                echo "<pre>$total_line</pre>";
                ?>
            </div>
        </div>
        
        <!-- Payments -->
        <?php if (!empty($payments)): ?>
            <div class="payments-section">
                <pre><?php echo centerText("PAGOS", $char_limit); ?></pre>
                <?php foreach ($payments as $payment): ?>
                    <?php
                    $method_text = $method_texts[$payment['method']] . ":";
                    $payment_price = formatPricePrint($payment['amount']);
                    $payment_line = $method_text . str_repeat(' ', $char_limit - strlen($method_text) - strlen($payment_price)) . $payment_price;
                    echo "<pre>$payment_line</pre>";
                    
                    if ($payment['reference']):
                        echo "<pre>  Ref: " . truncateText($payment['reference'], $char_limit - 7) . "</pre>";
                    endif;
                    ?>
                <?php endforeach; ?>
                
                <?php if ($pending_amount > 0): ?>
                    <hr class="line-separator">
                    <?php
                    $pending_text = "SALDO PENDIENTE:";
                    $pending_price = formatPricePrint($pending_amount);
                    $pending_line = $pending_text . str_repeat(' ', $char_limit - strlen($pending_text) - strlen($pending_price)) . $pending_price;
                    echo "<pre><strong>$pending_line</strong></pre>";
                    ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Footer -->
        <div class="footer">
            <pre><?php echo centerText("¡Gracias por su preferencia!", $char_limit); ?></pre>
            <?php if ($order['customer_notes']): ?>
                <pre><?php echo centerText("Notas: " . truncateText($order['customer_notes'], $char_limit - 8), $char_limit); ?></pre>
            <?php endif; ?>
            <pre><?php echo centerText(date('d/m/Y H:i:s'), $char_limit); ?></pre>
        </div>
    </div>

    <script>
        function changeWidth(width) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('width', width);
            window.location.href = currentUrl.toString();
        }
        
        // Auto print on load if requested
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('autoprint') === '1') {
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>