<?php
// admin/api/update-order-status.php - Versión actualizada
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';

$auth = new Auth();
$auth->requireLogin();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $database = new Database();
    $db = $database->getConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Datos JSON inválidos');
    }

    $order_id = $input['order_id'] ?? '';
    $status = $input['status'] ?? '';
    $order_type = $input['order_type'] ?? 'traditional';

    if (!$order_id || !$status) {
        throw new Exception('order_id y status son requeridos');
    }

    // Validar permisos según el tipo de acción
    if ($status === 'ready' && !$auth->hasPermission('kitchen')) {
        throw new Exception('Sin permisos para marcar como listo');
    }

    if ($status === 'delivered' && !$auth->hasPermission('delivery')) {
        throw new Exception('Sin permisos para marcar como entregado');
    }

    if ($order_type === 'online') {
        // Manejar pedidos online
        $allowedStatuses = ['preparing', 'ready', 'delivered'];
        
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('Estado no válido para pedidos online: ' . $status);
        }

        // Verificar que el pedido existe
        $checkQuery = "SELECT id, status as current_status FROM online_orders WHERE id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute(['id' => $order_id]);
        $existingOrder = $checkStmt->fetch();

        if (!$existingOrder) {
            throw new Exception('Pedido online no encontrado');
        }

        // Preparar query según el estado
        switch ($status) {
            case 'preparing':
                $query = "UPDATE online_orders SET status = 'preparing', started_preparing_at = NOW() WHERE id = :id";
                $params = ['id' => $order_id];
                break;
                
            case 'ready':
                $query = "UPDATE online_orders SET status = 'ready', ready_at = NOW() WHERE id = :id";
                $params = ['id' => $order_id];
                break;
                
            case 'delivered':
                $query = "UPDATE online_orders SET status = 'delivered', delivered_at = NOW(), delivered_by = :user_id WHERE id = :id";
                $params = [
                    'id' => $order_id,
                    'user_id' => $_SESSION['user_id']
                ];
                break;
        }

        $stmt = $db->prepare($query);
        $result = $stmt->execute($params);

        if (!$result) {
            throw new Exception('Error al actualizar pedido online');
        }

        // Log del cambio
        error_log("Pedido online {$order_id} cambiado de {$existingOrder['current_status']} a {$status} por usuario {$_SESSION['user_id']}");

    } else {
        // Manejar pedidos tradicionales (tabla orders)
        $allowedStatuses = ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled'];
        
        if (!in_array($status, $allowedStatuses)) {
            throw new Exception('Estado no válido para pedidos tradicionales: ' . $status);
        }

        // Verificar que el pedido existe
        $checkQuery = "SELECT id, status as current_status FROM orders WHERE id = :id";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute(['id' => $order_id]);
        $existingOrder = $checkStmt->fetch();

        if (!$existingOrder) {
            throw new Exception('Pedido tradicional no encontrado');
        }

        // Actualizar pedido tradicional
        $query = "UPDATE orders SET status = :status, updated_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($query);
        $result = $stmt->execute([
            'status' => $status,
            'id' => $order_id
        ]);

        if (!$result) {
            throw new Exception('Error al actualizar pedido tradicional');
        }

        // Log del cambio
        error_log("Pedido tradicional {$order_id} cambiado de {$existingOrder['current_status']} a {$status} por usuario {$_SESSION['user_id']}");
    }

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'message' => 'Estado actualizado correctamente',
        'order_id' => $order_id,
        'new_status' => $status,
        'order_type' => $order_type,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Error updating order status: " . $e->getMessage());
    error_log("Request data: " . print_r($input ?? [], true));
    error_log("User: " . ($_SESSION['user_id'] ?? 'unknown'));
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
}
?>