<?php
// admin/api/create-order.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../models/Order.php';
require_once '../../models/Product.php';

$auth = new Auth();
$auth->requirePermission('orders');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

// Validate required fields
$required_fields = ['type', 'items'];
foreach ($required_fields as $field) {
    if (!isset($input[$field])) {
        echo json_encode(['success' => false, 'message' => "Campo requerido: $field"]);
        exit();
    }
}

if (empty($input['items'])) {
    echo json_encode(['success' => false, 'message' => 'La orden debe tener al menos un item']);
    exit();
}

try {
    $orderModel = new Order();
    $productModel = new Product();
    
    // Calculate totals
    $subtotal = 0;
    $items_data = [];
    
    foreach ($input['items'] as $item) {
        $product = $productModel->getById($item['product_id']);
        if (!$product) {
            throw new Exception("Producto no encontrado: " . $item['product_id']);
        }
        
        $quantity = intval($item['quantity']);
        $unit_price = floatval($product['price']);
        $item_subtotal = $quantity * $unit_price;
        
        $subtotal += $item_subtotal;
        
        $items_data[] = [
            'product_id' => $product['id'],
            'quantity' => $quantity,
            'unit_price' => $unit_price,
            'subtotal' => $item_subtotal,
            'notes' => $item['notes'] ?? ''
        ];
    }
    
    // Get settings for tax calculation
    $settings = getSettings();
    $tax_rate = floatval($settings['tax_rate'] ?? 0) / 100;
    $delivery_fee = floatval($settings['delivery_fee'] ?? 0);
    
    $tax = $subtotal * $tax_rate;
    $delivery = ($input['type'] === 'delivery') ? $delivery_fee : 0;
    $total = $subtotal + $tax + $delivery;
    
    // Create order
    $order_data = [
        'order_number' => generateOrderNumber(),
        'type' => $input['type'],
        'table_id' => $input['table_id'] ?? null,
        'customer_name' => $input['customer_name'] ?? null,
        'customer_phone' => $input['customer_phone'] ?? null,
        'customer_address' => $input['customer_address'] ?? null,
        'customer_notes' => $input['customer_notes'] ?? null,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'delivery_fee' => $delivery,
        'total' => $total,
        'waiter_id' => $input['waiter_id'] ?? null,
        'created_by' => $_SESSION['user_id']
    ];
    
    $order_id = $orderModel->create($order_data);
    
    // Add items
    foreach ($items_data as $item) {
        $orderModel->addItem(
            $order_id, 
            $item['product_id'], 
            $item['quantity'], 
            $item['unit_price'], 
            $item['notes']
        );
    }
    
    echo json_encode([
        'success' => true, 
        'order_id' => $order_id,
        'order_number' => $order_data['order_number']
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

?>