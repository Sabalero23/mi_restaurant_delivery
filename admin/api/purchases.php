<?php
// admin/api/purchases.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireLogin();

// Verificar permisos
if (!$auth->hasPermission('kardex') && !$auth->hasPermission('products') && !$auth->hasPermission('all')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No tiene permisos para realizar esta acción']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Manejar exportación
if (isset($_GET['export'])) {
    exportPurchases($db);
    exit;
}

// Manejar cancelación
if (isset($_GET['action']) && $_GET['action'] === 'cancel' && isset($_GET['id'])) {
    cancelPurchase($db, intval($_GET['id']));
    exit;
}

// Manejar POST - Registrar compra
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $supplier = isset($_POST['supplier']) ? trim($_POST['supplier']) : null;
        $invoice_number = isset($_POST['invoice_number']) ? trim($_POST['invoice_number']) : null;
        $purchase_date = isset($_POST['purchase_date']) ? $_POST['purchase_date'] : date('Y-m-d');
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;
        $items = isset($_POST['items']) ? $_POST['items'] : [];
        $user_id = $_SESSION['user_id'];

        // Validaciones
        if (empty($items)) {
            throw new Exception('Debe agregar al menos un producto');
        }

        // Generar número de compra
        $query = "SELECT generate_purchase_number() as purchase_number";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $purchase_number = $result['purchase_number'];

        // Calcular total
        $total_amount = 0;
        foreach ($items as $item) {
            if (!empty($item['product_id']) && !empty($item['quantity']) && isset($item['unit_cost'])) {
                $subtotal = floatval($item['quantity']) * floatval($item['unit_cost']);
                $total_amount += $subtotal;
            }
        }

        // Iniciar transacción
        $db->beginTransaction();

        try {
            // Insertar compra
            $query = "INSERT INTO purchases 
                     (purchase_number, supplier, invoice_number, purchase_date, total_amount, notes, status, created_by) 
                     VALUES 
                     (:purchase_number, :supplier, :invoice_number, :purchase_date, :total_amount, :notes, 'completed', :created_by)";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':purchase_number' => $purchase_number,
                ':supplier' => $supplier,
                ':invoice_number' => $invoice_number,
                ':purchase_date' => $purchase_date,
                ':total_amount' => $total_amount,
                ':notes' => $notes,
                ':created_by' => $user_id
            ]);

            $purchase_id = $db->lastInsertId();

            // Insertar items
            $query_item = "INSERT INTO purchase_items 
                          (purchase_id, product_id, quantity, unit_cost, subtotal, notes) 
                          VALUES 
                          (:purchase_id, :product_id, :quantity, :unit_cost, :subtotal, :notes)";
            
            $stmt_item = $db->prepare($query_item);

            foreach ($items as $item) {
                if (!empty($item['product_id']) && !empty($item['quantity']) && isset($item['unit_cost'])) {
                    $quantity = intval($item['quantity']);
                    $unit_cost = floatval($item['unit_cost']);
                    $subtotal = $quantity * $unit_cost;
                    
                    $stmt_item->execute([
                        ':purchase_id' => $purchase_id,
                        ':product_id' => intval($item['product_id']),
                        ':quantity' => $quantity,
                        ':unit_cost' => $unit_cost,
                        ':subtotal' => $subtotal,
                        ':notes' => !empty($item['notes']) ? $item['notes'] : null
                    ]);
                    
                    // El trigger se encargará de actualizar el stock y registrar en kardex
                }
            }

            $db->commit();

            echo json_encode([
                'success' => true,
                'message' => 'Compra registrada exitosamente',
                'data' => [
                    'purchase_id' => $purchase_id,
                    'purchase_number' => $purchase_number,
                    'total_amount' => $total_amount
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

// GET - Obtener compras
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['export'])) {
    try {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        
        if ($id > 0) {
            // Obtener compra específica con items
            $query = "SELECT 
                p.*,
                u.full_name as created_by_name
                FROM purchases p
                LEFT JOIN users u ON p.created_by = u.id
                WHERE p.id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->execute([':id' => $id]);
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$purchase) {
                throw new Exception('Compra no encontrada');
            }
            
            // Obtener items
            $query_items = "SELECT 
                pi.*,
                p.name as product_name,
                p.stock_quantity as current_stock
                FROM purchase_items pi
                LEFT JOIN products p ON pi.product_id = p.id
                WHERE pi.purchase_id = :purchase_id
                ORDER BY pi.id";
            
            $stmt_items = $db->prepare($query_items);
            $stmt_items->execute([':purchase_id' => $id]);
            $purchase['items'] = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $purchase
            ]);
        } else {
            // Listar compras
            $query = "SELECT 
                p.*,
                u.full_name as created_by_name,
                (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) as total_items
                FROM purchases p
                LEFT JOIN users u ON p.created_by = u.id
                ORDER BY p.purchase_date DESC, p.id DESC
                LIMIT 100";
            
            $stmt = $db->prepare($query);
            $stmt->execute();
            $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $purchases
            ]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener compras: ' . $e->getMessage()
        ]);
    }
    exit;
}

// Función para cancelar compra
function cancelPurchase($db, $purchase_id) {
    try {
        $db->beginTransaction();
        
        // Verificar que la compra existe y está completada
        $query = "SELECT status FROM purchases WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $purchase_id]);
        $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$purchase) {
            throw new Exception('Compra no encontrada');
        }
        
        if ($purchase['status'] !== 'completed') {
            throw new Exception('Solo se pueden cancelar compras completadas');
        }
        
        // Obtener items de la compra
        $query = "SELECT product_id, quantity FROM purchase_items WHERE purchase_id = :purchase_id";
        $stmt = $db->prepare($query);
        $stmt->execute([':purchase_id' => $purchase_id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Revertir stock
        foreach ($items as $item) {
            $query = "UPDATE products 
                     SET stock_quantity = stock_quantity - :quantity 
                     WHERE id = :product_id AND stock_quantity >= :quantity";
            $stmt = $db->prepare($query);
            $stmt->execute([
                ':quantity' => $item['quantity'],
                ':product_id' => $item['product_id']
            ]);
            
            if ($stmt->rowCount() === 0) {
                throw new Exception('No se puede cancelar: stock insuficiente para revertir');
            }
        }
        
        // Actualizar estado de la compra
        $query = "UPDATE purchases SET status = 'cancelled' WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute([':id' => $purchase_id]);
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Compra cancelada exitosamente'
        ]);
        
    } catch (Exception $e) {
        $db->rollBack();
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}

// Función para exportar a Excel
function exportPurchases($db) {
    try {
        $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
        $date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
        $date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

        $query = "SELECT 
            p.purchase_date as fecha,
            p.purchase_number as numero_compra,
            p.supplier as proveedor,
            p.invoice_number as numero_factura,
            p.total_amount as monto_total,
            p.status as estado,
            u.full_name as creado_por,
            p.notes as notas
            FROM purchases p
            LEFT JOIN users u ON p.created_by = u.id
            WHERE 1=1";

        $params = array();

        if ($status_filter) {
            $query .= " AND p.status = :status";
            $params[':status'] = $status_filter;
        }

        if ($date_from) {
            $query .= " AND p.purchase_date >= :date_from";
            $params[':date_from'] = $date_from;
        }

        if ($date_to) {
            $query .= " AND p.purchase_date <= :date_to";
            $params[':date_to'] = $date_to;
        }

        $query .= " ORDER BY p.purchase_date DESC";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Configurar headers para descarga
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="compras_' . date('Y-m-d_His') . '.csv"');
        
        // Abrir output stream
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Encabezados
        fputcsv($output, [
            'Fecha',
            'Número Compra',
            'Proveedor',
            'Nº Factura',
            'Monto Total',
            'Estado',
            'Creado Por',
            'Notas'
        ], ';');
        
        // Datos
        foreach ($purchases as $purchase) {
            fputcsv($output, [
                date('d/m/Y', strtotime($purchase['fecha'])),
                $purchase['numero_compra'],
                $purchase['proveedor'] ?? '-',
                $purchase['numero_factura'] ?? '-',
                number_format($purchase['monto_total'], 2, ',', '.'),
                ucfirst($purchase['estado']),
                $purchase['creado_por'] ?? 'Sistema',
                $purchase['notas'] ?? '-'
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