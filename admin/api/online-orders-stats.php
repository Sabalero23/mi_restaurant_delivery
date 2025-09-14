<?php
// admin/api/online-orders-stats.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $auth = new Auth();
    $auth->requireLogin();
    $auth->requirePermission('online_orders');

    $database = new Database();
    $db = $database->getConnection();
    
    // Obtener estadísticas de pedidos online
    $query = "SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_online,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_online,
        COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_online,
        COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_online,
        COUNT(CASE WHEN status = 'delivered' THEN 1 END) as delivered_online,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_online,
        COUNT(*) as total_online
        FROM online_orders 
        WHERE DATE(created_at) = CURDATE()";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Obtener pedidos pendientes más recientes para información adicional
    $recent_query = "SELECT id, order_number, customer_name, created_at, total 
                     FROM online_orders 
                     WHERE status = 'pending' 
                     ORDER BY created_at DESC 
                     LIMIT 5";
    
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute();
    $recent_orders = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calcular tiempo promedio de pedidos pendientes
    $avg_time_query = "SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, NOW())) as avg_pending_time
                       FROM online_orders 
                       WHERE status = 'pending'";
    
    $avg_stmt = $db->prepare($avg_time_query);
    $avg_stmt->execute();
    $avg_time = $avg_stmt->fetch(PDO::FETCH_ASSOC);
    
    // Verificar si hay pedidos urgentes (más de 30 minutos pendientes)
    $urgent_query = "SELECT COUNT(*) as urgent_count
                     FROM online_orders 
                     WHERE status = 'pending' 
                     AND TIMESTAMPDIFF(MINUTE, created_at, NOW()) > 30";
    
    $urgent_stmt = $db->prepare($urgent_query);
    $urgent_stmt->execute();
    $urgent = $urgent_stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'success' => true,
        'pending_online' => (int)$stats['pending_online'],
        'accepted_online' => (int)$stats['accepted_online'],
        'preparing_online' => (int)$stats['preparing_online'],
        'ready_online' => (int)$stats['ready_online'],
        'delivered_online' => (int)$stats['delivered_online'],
        'rejected_online' => (int)$stats['rejected_online'],
        'total_online' => (int)$stats['total_online'],
        'urgent_count' => (int)$urgent['urgent_count'],
        'avg_pending_time' => round($avg_time['avg_pending_time'] ?? 0, 1),
        'recent_orders' => $recent_orders,
        'timestamp' => date('Y-m-d H:i:s'),
        'has_urgent' => $urgent['urgent_count'] > 0
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error en online-orders-stats API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'pending_online' => 0,
        'accepted_online' => 0,
        'preparing_online' => 0,
        'ready_online' => 0,
        'delivered_online' => 0,
        'rejected_online' => 0,
        'total_online' => 0,
        'urgent_count' => 0,
        'avg_pending_time' => 0,
        'recent_orders' => [],
        'has_urgent' => false
    ]);
}
?>