<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

$auth = new Auth();
$auth->requireLogin();

if (!$auth->hasPermission('online_orders')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

try {
    if (isset($_GET['recent'])) {
        $limit = (int)$_GET['recent'];
        
        $query = "SELECT 
            id,
            order_number,
            'online' as type,
            customer_name,
            total,
            status,
            COALESCE(payment_status, 'pending') as payment_status,
            created_at
        FROM online_orders
        ORDER BY created_at DESC
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