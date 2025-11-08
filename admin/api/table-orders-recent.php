<?php
// admin/api/table-orders-recent.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

$auth = new Auth();
$auth->requireLogin();

// Verificar que tenga permisos de orders o administrador
if (!$auth->hasPermission('orders')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    if (isset($_GET['recent'])) {
        $limit = (int)$_GET['recent'];
        
        // Obtener las órdenes más recientes de mesas (dine_in)
        $query = "SELECT 
            o.id,
            o.order_number,
            o.type,
            o.table_id,
            t.number as table_number,
            o.created_by,
            u.full_name as waiter_name,
            o.total,
            o.status,
            COALESCE(o.payment_status, 'pending') as payment_status,
            o.created_at
        FROM orders o
        LEFT JOIN tables t ON o.table_id = t.id
        LEFT JOIN users u ON o.created_by = u.id
        WHERE o.type = 'dine_in'
        ORDER BY o.created_at DESC
        LIMIT :limit";
        
        $stmt = $db->prepare($query);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($orders);
    } else {
        echo json_encode(['error' => 'Parámetro requerido']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor: ' . $e->getMessage()]);
}
?>