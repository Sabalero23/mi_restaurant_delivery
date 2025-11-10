<?php
// admin/ajax_handler.php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

try {
    $auth = new Auth();
    $auth->requireLogin();

    if (!isset($_POST['action'])) {
        echo json_encode(['success' => false, 'error' => 'Acción no especificada']);
        exit;
    }

    $database = new Database();
    $db = $database->getConnection();

    switch ($_POST['action']) {
        
        case 'occupy_table':
            if (!$auth->hasPermission('tables')) {
                echo json_encode(['success' => false, 'error' => 'Sin permisos']);
                exit;
            }
            
            $table_id = intval($_POST['table_id']);
            
            $checkQuery = "SELECT id, status FROM tables WHERE id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$table_id]);
            $table = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$table) {
                echo json_encode(['success' => false, 'error' => 'Mesa no encontrada']);
                exit;
            }
            
            if ($table['status'] == 'reserved' || $table['status'] == 'occupied') {
                echo json_encode(['success' => false, 'error' => 'La mesa ya está ocupada/reservada']);
                exit;
            }
            
            $updateQuery = "UPDATE tables SET status = 'reserved' WHERE id = ?";
            $updateStmt = $db->prepare($updateQuery);
            
            if ($updateStmt->execute([$table_id])) {
                echo json_encode(['success' => true, 'message' => 'Mesa marcada como reservada']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar la mesa']);
            }
            break;
        
        case 'free_table':
            if (!$auth->hasPermission('tables')) {
                echo json_encode(['success' => false, 'error' => 'Sin permisos']);
                exit;
            }
            
            $table_id = intval($_POST['table_id']);
            
            $checkQuery = "SELECT id, status FROM tables WHERE id = ?";
            $checkStmt = $db->prepare($checkQuery);
            $checkStmt->execute([$table_id]);
            $table = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$table) {
                echo json_encode(['success' => false, 'error' => 'Mesa no encontrada']);
                exit;
            }
            
            $ordersQuery = "SELECT COUNT(*) as count 
                            FROM orders 
                            WHERE table_id = ? 
                            AND status NOT IN ('delivered', 'cancelled')";
            $ordersStmt = $db->prepare($ordersQuery);
            $ordersStmt->execute([$table_id]);
            $ordersResult = $ordersStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ordersResult['count'] > 0) {
                echo json_encode([
                    'success' => false, 
                    'error' => 'No se puede liberar la mesa porque tiene órdenes activas'
                ]);
                exit;
            }
            
            if ($table['status'] == 'available') {
                echo json_encode(['success' => false, 'error' => 'La mesa ya está libre']);
                exit;
            }
            
            $updateQuery = "UPDATE tables SET status = 'available' WHERE id = ?";
            $updateStmt = $db->prepare($updateQuery);
            
            if ($updateStmt->execute([$table_id])) {
                echo json_encode(['success' => true, 'message' => 'Mesa liberada exitosamente']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar la mesa']);
            }
            break;
        
        default:
            echo json_encode(['success' => false, 'error' => 'Acción no válida']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Error del servidor: ' . $e->getMessage()]);
}
?>