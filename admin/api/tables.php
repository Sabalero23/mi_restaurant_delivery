<?php
// admin/api/tables.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';
require_once '../../models/Table.php';

// Importante: configurar headers antes de cualquier output
header('Content-Type: application/json');
error_reporting(0); // Desactivar errores de PHP que pueden romper el JSON

try {
    $auth = new Auth();
    $auth->requireLogin();
    $auth->requirePermission('tables');

    $tableModel = new Table();

    if (isset($_GET['id'])) {
        // Get specific table details
        $table_id = intval($_GET['id']);
        
        $database = new Database();
        $db = $database->getConnection();
        
        // Query para obtener datos de la mesa específica
        $query = "SELECT t.*, 
                  (SELECT COUNT(*) FROM orders o WHERE o.table_id = t.id AND o.status NOT IN ('delivered', 'cancelled')) as active_orders_count
                  FROM tables t 
                  WHERE t.id = ?";
                  
        $stmt = $db->prepare($query);
        $stmt->execute([$table_id]);
        $table = $stmt->fetch();
        
        if ($table) {
            // Obtener órdenes activas
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
        // Get all tables
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM tables ORDER BY number";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $tables = $stmt->fetchAll();
        
        echo json_encode($tables, JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>