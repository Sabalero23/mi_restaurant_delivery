<?php
// admin/api/update-delivery.php
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

    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        // Fallback to POST data
        $input = $_POST;
    }

    if (!isset($input['order_id']) || !isset($input['action'])) {
        throw new Exception('Parámetros requeridos faltantes');
    }

    $order_id = intval($input['order_id']);
    $action = sanitize($input['action']);

    $orderModel = new Order();
    $order = $orderModel->getById($order_id);

    if (!$order) {
        throw new Exception('Orden no encontrada');
    }

    if ($order['type'] !== 'delivery') {
        throw new Exception('Esta orden no es de delivery');
    }

    $success = false;
    $message = '';

    switch ($action) {
        case 'mark_delivered':
            $success = $orderModel->updateStatus($order_id, 'delivered');
            $message = $success ? 'Orden marcada como entregada' : 'Error al marcar como entregada';
            
            // Also update payment status if order is paid
            if ($success && $order['payment_status'] === 'pending') {
                $orderModel->updatePaymentStatus($order_id, 'paid');
            }
            break;

        case 'assign_delivery':
            $delivery_person = isset($input['delivery_person']) ? sanitize($input['delivery_person']) : '';
            
            // Add delivery assignment logic here
            // For now, just confirm the order if it's pending
            if ($order['status'] === 'pending') {
                $success = $orderModel->updateStatus($order_id, 'confirmed');
            } else {
                $success = true; // Already confirmed or in progress
            }
            
            $message = $success ? 'Entrega asignada correctamente' : 'Error al asignar entrega';
            break;

        case 'start_delivery':
            // Mark as ready if preparing, or keep ready status
            if ($order['status'] === 'preparing') {
                $success = $orderModel->updateStatus($order_id, 'ready');
            } else {
                $success = true; // Already ready
            }
            
            $message = $success ? 'Entrega iniciada' : 'Error al iniciar entrega';
            break;

        case 'cancel_delivery':
            $success = $orderModel->updateStatus($order_id, 'cancelled');
            $message = $success ? 'Entrega cancelada' : 'Error al cancelar entrega';
            break;

        default:
            throw new Exception('Acción no válida');
    }

    if ($success) {
        // Get updated order data
        $updated_order = $orderModel->getById($order_id);
        
        echo json_encode([
            'success' => true,
            'message' => $message,
            'order' => $updated_order,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        throw new Exception($message);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>