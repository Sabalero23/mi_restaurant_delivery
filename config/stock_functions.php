<?php
/**
 * Funciones para gestión de inventario y stock
 */

/**
 * Obtiene productos con stock bajo
 */
function getLowStockProducts($db) {
    try {
        $query = "SELECT id, name, stock_quantity, low_stock_alert 
                 FROM products 
                 WHERE track_inventory = 1 
                 AND is_active = 1 
                 AND stock_quantity <= low_stock_alert 
                 ORDER BY stock_quantity ASC";
        $stmt = $db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Error en getLowStockProducts: " . $e->getMessage());
        return [];
    }
}

/**
 * Verifica disponibilidad de stock antes de confirmar orden
 */
function checkStockAvailability($db, $items, $order_type = 'online') {
    $unavailable_items = [];
    
    foreach ($items as $item) {
        if ($order_type === 'online') {
            $product_id = $item['id'] ?? $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? 0;
            $product_name = $item['name'] ?? 'Producto';
        } else {
            $product_id = $item['product_id'] ?? null;
            $quantity = $item['quantity'] ?? 0;
            $product_name = $item['product_name'] ?? 'Producto';
        }
        
        if (!$product_id) continue;
        
        $query = "SELECT stock_quantity, track_inventory, name 
                 FROM products 
                 WHERE id = :product_id AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['product_id' => $product_id]);
        $product = $stmt->fetch();
        
        if ($product && $product['track_inventory'] == 1) {
            if ($product['stock_quantity'] < $quantity) {
                $unavailable_items[] = [
                    'name' => $product['name'],
                    'requested' => $quantity,
                    'available' => $product['stock_quantity']
                ];
            }
        }
    }
    
    return [
        'available' => empty($unavailable_items),
        'unavailable_items' => $unavailable_items
    ];
}

/**
 * Descuenta el stock de los productos de una orden
 */
function decreaseProductStock($db, $items, $order_type = 'online', $order_id = null) {
    try {
        $db->beginTransaction();
        
        $processed_items = [];
        $low_stock_alerts = [];
        $out_of_stock_items = [];
        
        foreach ($items as $item) {
            if ($order_type === 'online') {
                $product_id = $item['id'] ?? $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? 0;
                $product_name = $item['name'] ?? 'Producto';
            } else {
                $product_id = $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? 0;
                $product_name = $item['product_name'] ?? 'Producto';
            }
            
            if (!$product_id || $quantity <= 0) {
                continue;
            }
            
            $query = "SELECT id, name, stock_quantity, track_inventory, low_stock_alert 
                     FROM products 
                     WHERE id = :product_id AND is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->execute(['product_id' => $product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                continue;
            }
            
            if ($product['track_inventory'] == 1) {
                $current_stock = (int)$product['stock_quantity'];
                $new_stock = $current_stock - $quantity;
                
                if ($current_stock < $quantity) {
                    $out_of_stock_items[] = [
                        'name' => $product['name'],
                        'requested' => $quantity,
                        'available' => $current_stock
                    ];
                    continue;
                }
                
                $update_query = "UPDATE products 
                               SET stock_quantity = :new_stock, 
                                   updated_at = NOW() 
                               WHERE id = :product_id";
                $update_stmt = $db->prepare($update_query);
                $update_result = $update_stmt->execute([
                    'new_stock' => $new_stock,
                    'product_id' => $product_id
                ]);
                
                if ($update_result) {
                    $processed_items[] = [
                        'product_id' => $product_id,
                        'name' => $product['name'],
                        'quantity_decreased' => $quantity,
                        'previous_stock' => $current_stock,
                        'new_stock' => $new_stock
                    ];
                    
                    if ($new_stock <= $product['low_stock_alert']) {
                        $low_stock_alerts[] = [
                            'product_id' => $product_id,
                            'name' => $product['name'],
                            'current_stock' => $new_stock,
                            'alert_level' => $product['low_stock_alert']
                        ];
                    }
                } else {
                    throw new Exception("Error al actualizar stock del producto: " . $product['name']);
                }
            }
        }
        
        if (!empty($out_of_stock_items)) {
            $db->rollBack();
            return [
                'success' => false,
                'error' => 'stock_insufficient',
                'message' => 'Stock insuficiente para algunos productos',
                'out_of_stock_items' => $out_of_stock_items
            ];
        }
        
        $db->commit();
        
        return [
            'success' => true,
            'processed_items' => $processed_items,
            'low_stock_alerts' => $low_stock_alerts,
            'message' => 'Stock actualizado correctamente'
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error en decreaseProductStock: " . $e->getMessage());
        
        return [
            'success' => false,
            'error' => 'system_error',
            'message' => 'Error del sistema al actualizar stock: ' . $e->getMessage()
        ];
    }
}

/**
 * Restaura el stock cuando se cancela una orden
 */
function restoreProductStock($db, $items, $order_type = 'online', $order_id = null) {
    try {
        $db->beginTransaction();
        
        $restored_items = [];
        
        foreach ($items as $item) {
            if ($order_type === 'online') {
                $product_id = $item['id'] ?? $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? 0;
            } else {
                $product_id = $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? 0;
            }
            
            if (!$product_id || $quantity <= 0) continue;
            
            $query = "SELECT id, name, stock_quantity, track_inventory FROM products WHERE id = :product_id";
            $stmt = $db->prepare($query);
            $stmt->execute(['product_id' => $product_id]);
            $product = $stmt->fetch();
            
            if ($product && $product['track_inventory'] == 1) {
                $current_stock = (int)$product['stock_quantity'];
                $new_stock = $current_stock + $quantity;
                
                $update_query = "UPDATE products SET stock_quantity = :new_stock WHERE id = :product_id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->execute(['new_stock' => $new_stock, 'product_id' => $product_id]);
                
                $restored_items[] = [
                    'product_id' => $product_id,
                    'name' => $product['name'],
                    'quantity_restored' => $quantity,
                    'new_stock' => $new_stock
                ];
            }
        }
        
        $db->commit();
        
        return [
            'success' => true,
            'restored_items' => $restored_items
        ];
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error en restoreProductStock: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>