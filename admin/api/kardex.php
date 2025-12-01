<?php
/**
 * API para procesar movimientos de kardex (entradas/salidas manuales)
 * Ubicación: /admin/api/kardex.php
 */

// Configuración para devolver JSON
header('Content-Type: application/json');

require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

// Verificar autenticación
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar permisos
if (!$auth->hasPermission('kardex') && !$auth->hasPermission('products') && !$auth->hasPermission('all')) {
    echo json_encode(['success' => false, 'message' => 'Sin permisos para esta acción']);
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Solo procesar peticiones POST (registrar movimiento)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar datos recibidos
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $movement_type = isset($_POST['movement_type']) ? $_POST['movement_type'] : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
        
        // Validaciones
        if ($product_id <= 0) {
            throw new Exception('Producto no válido');
        }
        
        if (!in_array($movement_type, ['entrada', 'salida'])) {
            throw new Exception('Tipo de movimiento no válido');
        }
        
        if ($quantity <= 0) {
            throw new Exception('La cantidad debe ser mayor a 0');
        }
        
        // Obtener información del producto
        $query = "SELECT id, name, stock_quantity, track_inventory 
                 FROM products 
                 WHERE id = :product_id AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['product_id' => $product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            throw new Exception('Producto no encontrado');
        }
        
        if ($product['track_inventory'] != 1) {
            throw new Exception('Este producto no tiene control de inventario activado');
        }
        
        // Iniciar transacción
        $db->beginTransaction();
        
        $old_stock = (int)$product['stock_quantity'];
        $new_stock = $old_stock;
        
        // Calcular nuevo stock según tipo de movimiento
        if ($movement_type === 'entrada') {
            $new_stock = $old_stock + $quantity;
        } else { // salida
            $new_stock = $old_stock - $quantity;
            
            // Advertir si queda negativo (pero permitir)
            if ($new_stock < 0) {
                error_log("ADVERTENCIA: Stock negativo para producto {$product_id}: {$new_stock}");
            }
        }
        
        // Actualizar stock en tabla products
        $update_query = "UPDATE products 
                        SET stock_quantity = :new_stock,
                            updated_at = NOW()
                        WHERE id = :product_id";
        $update_stmt = $db->prepare($update_query);
        $update_result = $update_stmt->execute([
            'new_stock' => $new_stock,
            'product_id' => $product_id
        ]);
        
        if (!$update_result) {
            throw new Exception('Error al actualizar el stock del producto');
        }
        
        // Registrar movimiento en kardex (stock_movements)
        $insert_query = "INSERT INTO stock_movements 
                        (product_id, movement_type, quantity, old_stock, new_stock, reason, reference_type, user_id, created_at)
                        VALUES 
                        (:product_id, :movement_type, :quantity, :old_stock, :new_stock, :reason, 'manual', :user_id, NOW())";
        
        $insert_stmt = $db->prepare($insert_query);
        $insert_result = $insert_stmt->execute([
            'product_id' => $product_id,
            'movement_type' => $movement_type,
            'quantity' => $quantity,
            'old_stock' => $old_stock,
            'new_stock' => $new_stock,
            'reason' => $reason,
            'user_id' => $auth->getUserId()
        ]);
        
        if (!$insert_result) {
            throw new Exception('Error al registrar el movimiento en kardex');
        }
        
        // Confirmar transacción
        $db->commit();
        
        // Respuesta exitosa
        echo json_encode([
            'success' => true,
            'message' => 'Movimiento registrado exitosamente',
            'data' => [
                'product_name' => $product['name'],
                'movement_type' => $movement_type,
                'quantity' => $quantity,
                'old_stock' => $old_stock,
                'new_stock' => $new_stock
            ]
        ]);
        
    } catch (Exception $e) {
        // Revertir transacción en caso de error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Error en kardex API: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['export'])) {
    // Exportar kardex a Excel (funcionalidad futura)
    echo json_encode([
        'success' => false,
        'message' => 'Funcionalidad de exportación en desarrollo'
    ]);
    
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
}
?>