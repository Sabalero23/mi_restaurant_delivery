<?php
// admin/api/kardex.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireLogin();

// Verificar permisos
if (!$auth->hasPermission('products')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Manejar exportación
if (isset($_GET['export'])) {
    exportKardex($db);
    exit;
}

// Manejar POST - Registrar movimiento
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $movement_type = isset($_POST['movement_type']) ? trim($_POST['movement_type']) : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : null;
        $user_id = $_SESSION['user_id'];

        // Validaciones
        if ($product_id <= 0) {
            throw new Exception('Debe seleccionar un producto');
        }

        if (!in_array($movement_type, ['entrada', 'salida'])) {
            throw new Exception('Tipo de movimiento inválido');
        }

        if ($quantity <= 0) {
            throw new Exception('La cantidad debe ser mayor a cero');
        }

        // Obtener producto actual
        $query = "SELECT * FROM products WHERE id = :id AND track_inventory = 1";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception('Producto no encontrado o no tiene inventario activado');
        }

        $old_stock = intval($product['stock_quantity']);
        
        // Calcular nuevo stock
        if ($movement_type === 'entrada') {
            $new_stock = $old_stock + $quantity;
        } else {
            $new_stock = $old_stock - $quantity;
            
            // Validar que no quede stock negativo
            if ($new_stock < 0) {
                throw new Exception('No hay suficiente stock disponible. Stock actual: ' . $old_stock);
            }
        }

        // Iniciar transacción
        $db->beginTransaction();

        try {
            // Registrar movimiento
            $query = "INSERT INTO stock_movements 
                     (product_id, movement_type, quantity, old_stock, new_stock, reason, user_id) 
                     VALUES 
                     (:product_id, :movement_type, :quantity, :old_stock, :new_stock, :reason, :user_id)";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':product_id' => $product_id,
                ':movement_type' => $movement_type,
                ':quantity' => $quantity,
                ':old_stock' => $old_stock,
                ':new_stock' => $new_stock,
                ':reason' => $reason,
                ':user_id' => $user_id
            ]);

            // Actualizar stock del producto
            $query = "UPDATE products SET stock_quantity = :new_stock WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':new_stock' => $new_stock,
                ':id' => $product_id
            ]);

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Movimiento registrado exitosamente',
                'data' => [
                    'old_stock' => $old_stock,
                    'new_stock' => $new_stock,
                    'movement_type' => $movement_type,
                    'quantity' => $quantity
                ]
            ]);

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// GET - Obtener movimientos
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
        
        $query = "SELECT 
            sm.*,
            p.name as product_name,
            p.stock_quantity as current_stock,
            c.name as category_name,
            u.full_name as user_name
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE 1=1";
        
        $params = [];
        
        if ($product_id > 0) {
            $query .= " AND sm.product_id = :product_id";
            $params[':product_id'] = $product_id;
        }
        
        $query .= " ORDER BY sm.created_at DESC LIMIT :limit";
        $params[':limit'] = $limit;
        
        $stmt = $db->prepare($query);
        
        // Bind parameters
        foreach ($params as $key => $value) {
            if ($key === ':limit') {
                $stmt->bindValue($key, $value, PDO::PARAM_INT);
            } else {
                $stmt->bindValue($key, $value);
            }
        }
        
        $stmt->execute();
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => $movements
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener movimientos: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Función para exportar a Excel
function exportKardex($db) {
    try {
        $product_filter = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
        $movement_type = isset($_GET['movement_type']) ? $_GET['movement_type'] : '';

        $query = "SELECT 
            sm.created_at as fecha,
            p.name as producto,
            c.name as categoria,
            sm.movement_type as tipo,
            sm.quantity as cantidad,
            sm.old_stock as stock_anterior,
            sm.new_stock as stock_nuevo,
            sm.reason as motivo,
            u.full_name as usuario
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE 1=1";

        $params = array();

        if ($product_filter > 0) {
            $query .= " AND sm.product_id = :product_id";
            $params[':product_id'] = $product_filter;
        }

        if ($date_from) {
            $query .= " AND DATE(sm.created_at) >= :date_from";
            $params[':date_from'] = $date_from;
        }

        if ($date_to) {
            $query .= " AND DATE(sm.created_at) <= :date_to";
            $params[':date_to'] = $date_to;
        }

        if ($movement_type) {
            $query .= " AND sm.movement_type = :movement_type";
            $params[':movement_type'] = $movement_type;
        }

        $query .= " ORDER BY sm.created_at DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Configurar headers para descarga
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="kardex_' . date('Y-m-d_His') . '.csv"');
        
        // Abrir output stream
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, [
            'Fecha',
            'Producto',
            'Categoría',
            'Tipo Movimiento',
            'Cantidad',
            'Stock Anterior',
            'Stock Nuevo',
            'Motivo',
            'Usuario'
        ], ';');
        
        // Datos
        foreach ($movements as $mov) {
            fputcsv($output, [
                date('d/m/Y H:i', strtotime($mov['fecha'])),
                $mov['producto'],
                $mov['categoria'] ?? 'Sin categoría',
                ucfirst($mov['tipo']),
                $mov['cantidad'],
                $mov['stock_anterior'],
                $mov['stock_nuevo'],
                $mov['motivo'] ?? '-',
                $mov['usuario'] ?? 'Sistema'
            ], ';');
        }
        
        fclose($output);
        exit;

    } catch (Exception $e) {
        http_response_code(500);
        die('Error al exportar: ' . $e->getMessage());
    }
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método no permitido']);