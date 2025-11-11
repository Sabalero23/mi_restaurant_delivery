<?php
// admin/api/tables.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';
require_once '../../models/Table.php';

header('Content-Type: application/json');
error_reporting(0);

try {
    $auth = new Auth();
    $auth->requireLogin();
    $auth->requirePermission('tables');

    $tableModel = new Table();
    $database = new Database();
    $db = $database->getConnection();

    // Handle POST request to update waiter assignment
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (isset($input['table_id']) && isset($input['action'])) {
            $table_id = intval($input['table_id']);
            
            if ($input['action'] === 'assign_waiter') {
                $waiter_id = isset($input['waiter_id']) && $input['waiter_id'] !== '' ? intval($input['waiter_id']) : null;
                
                $query = "UPDATE tables SET waiter_id = ? WHERE id = ?";
                $stmt = $db->prepare($query);
                
                if ($stmt->execute([$waiter_id, $table_id])) {
                    echo json_encode([
                        'success' => true,
                        'message' => $waiter_id ? 'Mesero asignado correctamente' : 'Mesero desasignado correctamente'
                    ], JSON_UNESCAPED_UNICODE);
                } else {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Error al actualizar la mesa'
                    ], JSON_UNESCAPED_UNICODE);
                }
                exit;
            }
        }
    }

    if (isset($_GET['id'])) {
        // Get specific table details
        $table_id = intval($_GET['id']);
        
        $query = "SELECT t.*, 
                  u.full_name as waiter_name,
                  u.username as waiter_username,
                  (SELECT COUNT(*) FROM orders o WHERE o.table_id = t.id AND o.status NOT IN ('delivered', 'cancelled')) as active_orders_count
                  FROM tables t 
                  LEFT JOIN users u ON t.waiter_id = u.id
                  WHERE t.id = ?";
                  
        $stmt = $db->prepare($query);
        $stmt->execute([$table_id]);
        $table = $stmt->fetch();
        
        if ($table) {
            $orders_query = "SELECT o.id, o.order_number, o.status, o.created_at,
                            COUNT(oi.id) as item_count, 
                            COALESCE(SUM(oi.subtotal), 0) as calculated_total
                            FROM orders o 
                            LEFT JOIN order_items oi ON o.id = oi.order_id
                            WHERE o.table_id = ? AND o.status NOT IN ('delivered', 'cancelled')
                            GROUP BY o.id
                            ORDER BY o.created_at DESC";
            
            $orders_stmt = $db->prepare($orders_query);
            $orders_stmt->execute([$table_id]);
            $active_orders = $orders_stmt->fetchAll();
            
            $table['active_orders'] = $active_orders;
            
            echo json_encode($table, JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['error' => 'Mesa no encontrada'], JSON_UNESCAPED_UNICODE);
        }
        
    } else {
        // Get all tables with waiter info
        $query = "SELECT t.*, 
                  u.full_name as waiter_name,
                  u.username as waiter_username,
                  (SELECT COUNT(*) FROM orders o WHERE o.table_id = t.id AND o.status NOT IN ('delivered', 'cancelled')) as active_orders_count
                  FROM tables t 
                  LEFT JOIN users u ON t.waiter_id = u.id
                  ORDER BY t.number";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $tables = $stmt->fetchAll();
        
        echo json_encode($tables, JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>