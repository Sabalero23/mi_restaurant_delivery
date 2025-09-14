<?php
// admin/api/orders.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../models/Order.php';
require_once '../../config/functions.php';

$auth = new Auth();
$auth->requirePermission('orders');

header('Content-Type: application/json');

try {
    $database = new Database();
    $db = $database->getConnection();

    if (isset($_GET['recent'])) {
        $limit = intval($_GET['recent']);
        
        // MODIFICAR ESTA CONSULTA PARA INCLUIR ÓRDENES ONLINE
        $query = "
            (SELECT 
                o.id,
                o.order_number,
                o.type,
                o.customer_name,
                o.total,
                o.status,
                o.payment_status,
                o.created_at,
                u.full_name as waiter_name,
                t.number as table_number,
                'traditional' as order_source
            FROM orders o 
            LEFT JOIN users u ON o.waiter_id = u.id 
            LEFT JOIN tables t ON o.table_id = t.id)
            
            UNION ALL
            
            (SELECT 
                oo.id,
                oo.order_number,
                'online' as type,
                oo.customer_name,
                oo.total,
                oo.status,
                COALESCE(oo.payment_status, 'pending') as payment_status,
                oo.created_at,
                '' as waiter_name,
                '' as table_number,
                'online' as order_source
            FROM online_orders oo)
            
            ORDER BY created_at DESC 
            LIMIT :limit";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agregar información de tiempo transcurrido
        foreach ($orders as &$order) {
            $order['time_elapsed'] = getOrderTimeElapsed($order['created_at']);
            $order['priority_status'] = getOrderPriorityStatus($order);
        }

        echo json_encode($orders);
        exit();
    }

    // Default: return only traditional orders (mantener funcionalidad original)
    $query = "SELECT o.*, u.full_name as waiter_name, t.number as table_number,
              'traditional' as order_source
              FROM orders o 
              LEFT JOIN users u ON o.waiter_id = u.id 
              LEFT JOIN tables t ON o.table_id = t.id 
              ORDER BY o.created_at DESC";

    $stmt = $db->prepare($query);
    $stmt->execute();

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    
} catch (Exception $e) {
    error_log("Error in orders API: " . $e->getMessage());
    echo json_encode([]);
}
?>