<?php
// admin/api/delivery.php - Versión actualizada
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';
require_once '../../models/Order.php';

try {
    $auth = new Auth();
    $auth->requireLogin();
    $auth->requirePermission('delivery');

    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Manejar actualizaciones de estado de delivery
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $order_id = $input['order_id'] ?? '';
        $order_type = $input['order_type'] ?? 'traditional';

        if ($action === 'mark_delivered') {
            if ($order_type === 'online') {
                $query = "UPDATE online_orders SET status = 'delivered', delivered_at = NOW(), delivered_by = :user_id WHERE id = :id";
            } else {
                $query = "UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE id = :id";
            }

            $stmt = $db->prepare($query);
            $params = ['id' => $order_id];
            
            if ($order_type === 'online') {
                $params['user_id'] = $_SESSION['user_id'];
            }
            
            $result = $stmt->execute($params);

            echo json_encode(['success' => $result]);
            exit;
        }
    }

    // Obtener pedidos tradicionales listos para delivery
    $traditionalQuery = "SELECT o.*, u.full_name as waiter_name 
                        FROM orders o 
                        LEFT JOIN users u ON o.waiter_id = u.id 
                        WHERE o.type = 'delivery' 
                        AND o.status = 'ready'
                        ORDER BY o.created_at ASC";
    
    $stmt = $db->prepare($traditionalQuery);
    $stmt->execute();
    $traditionalOrders = $stmt->fetchAll();

    // Obtener pedidos online listos para delivery
    $onlineQuery = "SELECT * FROM online_orders 
                   WHERE status = 'ready' 
                   ORDER BY created_at ASC";
    
    $onlineStmt = $db->prepare($onlineQuery);
    $onlineStmt->execute();
    $onlineOrders = $onlineStmt->fetchAll();

    // Procesar pedidos tradicionales
    foreach ($traditionalOrders as &$order) {
        $order['order_type'] = 'traditional';
        $order['delivery_type'] = 'Pedido Local';
        
        // Calcular tiempo transcurrido
        $order_time = new DateTime($order['created_at']);
        $current_time = new DateTime();
        $elapsed_seconds = $current_time->getTimestamp() - $order_time->getTimestamp();
        $elapsed_minutes = max(0, floor($elapsed_seconds / 60));
        
        $order['elapsed_minutes'] = $elapsed_minutes;
        $order['is_urgent'] = $elapsed_minutes > 45;
        $order['delivery_status'] = 'ready';
        
        // Validar datos de cliente
        if (empty($order['customer_name'])) {
            $order['customer_name'] = 'Cliente Sin Nombre';
        }
        if (empty($order['customer_phone'])) {
            $order['customer_phone'] = 'Sin teléfono';
        }
        if (empty($order['customer_address'])) {
            $order['customer_address'] = 'Sin dirección especificada';
        }

        // Obtener conteo de items
        $items_query = "SELECT COUNT(*) as item_count FROM order_items WHERE order_id = ?";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->execute([$order['id']]);
        $items_result = $items_stmt->fetch();
        $order['item_count'] = $items_result['item_count'];
    }

    // Procesar pedidos online
    foreach ($onlineOrders as &$order) {
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
        $order['delivery_status'] = 'ready';
        
        // Procesar items del pedido online
        $items = json_decode($order['items'], true) ?: [];
        $order['item_count'] = count($items);
        
        // Asegurar que los campos requeridos existen
        $order['waiter_name'] = 'Sistema Online';
        $order['delivery_fee'] = $order['delivery_fee'] ?? 0;
        
        // Formatear números de teléfono si es necesario
        if (!empty($order['customer_phone'])) {
            $order['customer_phone'] = formatPhoneForDelivery($order['customer_phone']);
        }
    }

    // Combinar ambos tipos de pedidos
    $allOrders = array_merge($traditionalOrders, $onlineOrders);
    
    // Filtrar solo pedidos que tienen dirección
    $delivery_orders = array_filter($allOrders, function($order) {
        return !empty($order['customer_address']) && 
               $order['customer_address'] !== 'Sin dirección especificada';
    });
    
    // Ordenar por urgencia y tiempo
    usort($delivery_orders, function($a, $b) {
        if ($a['is_urgent'] !== $b['is_urgent']) {
            return $b['is_urgent'] - $a['is_urgent']; // Urgentes primero
        }
        return strtotime($a['created_at']) - strtotime($b['created_at']); // Más antiguos primero
    });
    
    // Re-indexar array
    $delivery_orders = array_values($delivery_orders);
    
    // Calcular estadísticas
    $stats = [
        'total_orders' => count($delivery_orders),
        'ready_orders' => count(array_filter($delivery_orders, fn($o) => $o['delivery_status'] === 'ready')),
        'urgent_orders' => count(array_filter($delivery_orders, fn($o) => $o['is_urgent'])),
        'online_orders' => count(array_filter($delivery_orders, fn($o) => $o['order_type'] === 'online')),
        'traditional_orders' => count(array_filter($delivery_orders, fn($o) => $o['order_type'] === 'traditional')),
        'average_wait_time' => count($delivery_orders) > 0 ? 
            round(array_sum(array_column($delivery_orders, 'elapsed_minutes')) / count($delivery_orders), 1) : 0
    ];
    
    $response = [
        'success' => true,
        'orders' => $delivery_orders,
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage(),
        'orders' => [],
        'stats' => [
            'total_orders' => 0,
            'ready_orders' => 0,
            'urgent_orders' => 0,
            'online_orders' => 0,
            'traditional_orders' => 0,
            'average_wait_time' => 0
        ]
    ]);
}

// Función auxiliar para formatear teléfonos
function formatPhoneForDelivery($phone) {
    // Si ya está formateado con +54, dejarlo así
    if (strpos($phone, '+54') === 0) {
        return $phone;
    }
    
    // Si empieza con 54, agregar el +
    if (strpos($phone, '54') === 0) {
        return '+' . $phone;
    }
    
    // Si no tiene código de país, agregarlo
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($cleanPhone) >= 10) {
        return '+54' . $cleanPhone;
    }
    
    return $phone; // Devolver original si no se puede formatear
}
?>