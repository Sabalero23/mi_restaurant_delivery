<?php
// admin/api/kitchen.php - Versión corregida
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../models/Order.php';
require_once '../../config/functions.php';

$auth = new Auth();
$auth->requirePermission('kitchen');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Manejar actualizaciones de estado
        $input = json_decode(file_get_contents('php://input'), true);
        $action = $input['action'] ?? '';
        $order_type = $input['order_type'] ?? 'traditional';
        
        if ($order_type === 'online') {
            $order_id = $input['order_id'] ?? '';
            switch ($action) {
                case 'preparing':
                    $query = "UPDATE online_orders SET status = 'preparing', started_preparing_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute(['id' => $order_id]);
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Pedido online marcado como en preparación']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al actualizar pedido online']);
                    }
                    break;
                    
                case 'ready':
                    $query = "UPDATE online_orders SET status = 'ready', ready_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $result = $stmt->execute(['id' => $order_id]);
                    if ($result) {
                        echo json_encode(['success' => true, 'message' => 'Pedido online listo para entrega']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Error al marcar pedido como listo']);
                    }
                    break;
            }
        } else {
            // Pedidos tradicionales
            $order_id = $input['order_id'] ?? '';
            
            if ($action === 'preparing') {
                // Cambiar estado de la orden completa a preparing
                $query = "UPDATE orders SET status = 'preparing' WHERE id = :id";
                $stmt = $db->prepare($query);
                $result = $stmt->execute(['id' => $order_id]);
                
                if ($result) {
                    // Cambiar todos los items a preparing también
                    $itemQuery = "UPDATE order_items SET status = 'preparing' WHERE order_id = :order_id";
                    $itemStmt = $db->prepare($itemQuery);
                    $itemStmt->execute(['order_id' => $order_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Orden marcada como en preparación']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al actualizar orden']);
                }
                
            } elseif ($action === 'ready') {
                // Cambiar estado de la orden completa a ready
                $query = "UPDATE orders SET status = 'ready' WHERE id = :id";
                $stmt = $db->prepare($query);
                $result = $stmt->execute(['id' => $order_id]);
                
                if ($result) {
                    // Cambiar todos los items a ready también
                    $itemQuery = "UPDATE order_items SET status = 'ready' WHERE order_id = :order_id";
                    $itemStmt = $db->prepare($itemQuery);
                    $itemStmt->execute(['order_id' => $order_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Orden lista para servir/entregar']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Error al marcar orden como lista']);
                }
                
            } elseif (isset($input['item_id'])) {
                // Manejar items individuales (funcionalidad existente)
                $item_id = $input['item_id'];
                switch ($action) {
                    case 'preparing':
                        $query = "UPDATE order_items SET status = 'preparing' WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute(['id' => $item_id]);
                        if ($result) {
                            echo json_encode(['success' => true, 'message' => 'Item marcado como en preparación']);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al actualizar item']);
                        }
                        break;
                        
                    case 'ready':
                        $query = "UPDATE order_items SET status = 'ready' WHERE id = :id";
                        $stmt = $db->prepare($query);
                        $result = $stmt->execute(['id' => $item_id]);
                        
                        if ($result) {
                            // Verificar si todos los items están listos para actualizar la orden
                            $checkQuery = "SELECT COUNT(*) as total, SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_count 
                                          FROM order_items WHERE order_id = :order_id";
                            $checkStmt = $db->prepare($checkQuery);
                            $checkStmt->execute(['order_id' => $order_id]);
                            $result_check = $checkStmt->fetch();
                            
                            if ($result_check['total'] == $result_check['ready_count']) {
                                $updateOrderQuery = "UPDATE orders SET status = 'ready' WHERE id = :id";
                                $updateOrderStmt = $db->prepare($updateOrderQuery);
                                $updateOrderStmt->execute(['id' => $order_id]);
                                echo json_encode(['success' => true, 'message' => 'Item listo - Orden completa lista']);
                            } else {
                                echo json_encode(['success' => true, 'message' => 'Item marcado como listo']);
                            }
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Error al actualizar item']);
                        }
                        break;
                }
            }
        }
        exit;
    }

    // Obtener pedidos tradicionales (confirmed y preparing)
    $traditionalQuery = "SELECT o.*, t.number as table_number,
              GROUP_CONCAT(
                CONCAT(oi.id, ':', oi.quantity, ':', p.name, ':', COALESCE(oi.status, 'pending'), ':', COALESCE(p.preparation_time, 0), ':', COALESCE(oi.notes, '')) 
                SEPARATOR '|'
              ) as items_data
              FROM orders o 
              JOIN order_items oi ON o.id = oi.order_id 
              JOIN products p ON oi.product_id = p.id 
              LEFT JOIN tables t ON o.table_id = t.id
              WHERE o.status IN ('confirmed', 'preparing') 
              GROUP BY o.id 
              ORDER BY o.created_at ASC";

    $stmt = $db->prepare($traditionalQuery);
    $stmt->execute();
    $traditionalOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Obtener pedidos online (accepted y preparing)
    $onlineQuery = "SELECT * FROM online_orders 
                   WHERE status IN ('accepted', 'preparing') 
                   ORDER BY created_at ASC";
    $onlineStmt = $db->prepare($onlineQuery);
    $onlineStmt->execute();
    $onlineOrders = $onlineStmt->fetchAll(PDO::FETCH_ASSOC);

    // Procesar pedidos tradicionales
    foreach ($traditionalOrders as &$order) {
        $items = [];
        if ($order['items_data']) {
            $items_data = explode('|', $order['items_data']);
            foreach ($items_data as $item_data) {
                $parts = explode(':', $item_data, 6);
                if (count($parts) >= 6) {
                    $items[] = [
                        'id' => $parts[0],
                        'quantity' => $parts[1],
                        'product_name' => $parts[2],
                        'status' => $parts[3],
                        'preparation_time' => $parts[4],
                        'notes' => $parts[5]
                    ];
                }
            }
        }
        $order['items'] = $items;
        $order['order_type'] = 'traditional';
        $order['time_elapsed'] = getOrderTimeElapsed($order['created_at']);
        $order['priority_status'] = getOrderPriorityStatus($order);
        unset($order['items_data']);
    }

    // Procesar pedidos online
    foreach ($onlineOrders as &$order) {
        $items = json_decode($order['items'], true) ?: [];
        
        // Convertir formato de items online para compatibilidad con frontend
        $processedItems = [];
        foreach ($items as $item) {
            $processedItems[] = [
                'id' => 'online_' . $order['id'] . '_' . count($processedItems),
                'quantity' => $item['quantity'],
                'product_name' => $item['name'],
                'status' => $order['status'] === 'accepted' ? 'pending' : 'preparing',
                'preparation_time' => 15, // Tiempo estimado por defecto
                'notes' => ''
            ];
        }
        
        $order['items'] = $processedItems;
        $order['order_type'] = 'online';
        $order['customer_info'] = [
            'name' => $order['customer_name'],
            'phone' => $order['customer_phone'],
            'address' => $order['customer_address']
        ];
        $order['time_elapsed'] = getOrderTimeElapsed($order['created_at']);
        $order['priority_status'] = getOrderPriorityStatus($order);
        $order['type'] = 'delivery'; // Para mantener compatibilidad
        $order['table_number'] = null;
    }

    // Combinar ambos tipos de pedidos
    $allOrders = array_merge($traditionalOrders, $onlineOrders);
    
    // Ordenar por prioridad y tiempo
    usort($allOrders, function($a, $b) {
        $priorityA = $a['priority_status']['is_priority'] ?? false ? 1 : 0;
        $priorityB = $b['priority_status']['is_priority'] ?? false ? 1 : 0;
        
        if ($priorityA !== $priorityB) {
            return $priorityB - $priorityA; // Prioritarios primero
        }
        
        return strtotime($a['created_at']) - strtotime($b['created_at']); // Más antiguos primero
    });

    echo json_encode($allOrders);
    
} catch (Exception $e) {
    error_log("Error in kitchen API: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor', 'message' => $e->getMessage()]);
}
?>