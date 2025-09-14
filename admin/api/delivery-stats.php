<?php
// admin/api/delivery-stats.php - Nueva API para estadísticas unificadas
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

try {
    $auth = new Auth();
    $auth->requireLogin();
    $auth->requirePermission('delivery');

    $database = new Database();
    $db = $database->getConnection();

    // MISMA LÓGICA QUE EN delivery.php PARA OBTENER ÓRDENES
    
    // Obtener pedidos tradicionales listos para delivery
    $traditionalQuery = "SELECT o.*, u.full_name as waiter_name 
                        FROM orders o 
                        LEFT JOIN users u ON o.waiter_id = u.id 
                        WHERE o.type = 'delivery' 
                        AND o.status = 'ready'
                        AND o.customer_address IS NOT NULL 
                        AND o.customer_address != ''
                        AND o.customer_address != 'Sin dirección especificada'
                        ORDER BY o.created_at ASC";
    
    $stmt = $db->prepare($traditionalQuery);
    $stmt->execute();
    $traditionalOrders = $stmt->fetchAll();

    // Obtener pedidos online listos para delivery
    $onlineQuery = "SELECT * FROM online_orders 
                   WHERE status = 'ready' 
                   AND customer_address IS NOT NULL 
                   AND customer_address != ''
                   ORDER BY created_at ASC";
    
    $onlineStmt = $db->prepare($onlineQuery);
    $onlineStmt->execute();
    $onlineOrders = $onlineStmt->fetchAll();

    // Procesar pedidos tradicionales
    $validTraditionalOrders = [];
    foreach ($traditionalOrders as $order) {
        if (!empty($order['customer_address']) && 
            $order['customer_address'] !== 'Sin dirección especificada') {
            $validTraditionalOrders[] = $order;
        }
    }

    // Procesar pedidos online
    $validOnlineOrders = [];
    foreach ($onlineOrders as $order) {
        if (!empty($order['customer_address'])) {
            $validOnlineOrders[] = $order;
        }
    }

    // Combinar y calcular estadísticas
    $totalDeliveryOrders = count($validTraditionalOrders) + count($validOnlineOrders);
    
    // Calcular urgentes (más de 45 minutos)
    $urgentCount = 0;
    $allOrders = array_merge($validTraditionalOrders, $validOnlineOrders);
    
    foreach ($allOrders as $order) {
        $order_time = new DateTime($order['created_at']);
        $current_time = new DateTime();
        $elapsed_seconds = $current_time->getTimestamp() - $order_time->getTimestamp();
        $elapsed_minutes = max(0, floor($elapsed_seconds / 60));
        
        if ($elapsed_minutes > 45) {
            $urgentCount++;
        }
    }

    $response = [
        'success' => true,
        'total_pending' => $totalDeliveryOrders,
        'traditional_orders' => count($validTraditionalOrders),
        'online_orders' => count($validOnlineOrders),
        'urgent_orders' => $urgentCount,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'total_pending' => 0,
        'traditional_orders' => 0,
        'online_orders' => 0,
        'urgent_orders' => 0
    ]);
}
?>