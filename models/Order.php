<?php
// models/Order.php
class Order {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Create a new order with auto-generated order number
     */
    public function create($data) {
    try {
        $this->db->beginTransaction();
        
        // Establecer zona horaria en la conexión actual
        $this->db->exec("SET time_zone = '-03:00'");
        
        // Asegurar que usamos la zona horaria correcta
        require_once __DIR__ . '/../config/functions.php';
        getSystemTimezone();

        
        // Generate order number if not provided
        if (!isset($data['order_number']) || empty($data['order_number'])) {
            $data['order_number'] = $this->generateOrderNumber();
        }
        
        // Set default values to match your database structure
        $orderData = [
            'order_number' => $data['order_number'],
            'type' => $data['type'],
            'table_id' => $data['table_id'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'customer_phone' => $data['customer_phone'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
            'subtotal' => $data['subtotal'] ?? 0,
            'tax' => $data['tax'] ?? 0,
            'delivery_fee' => $data['delivery_fee'] ?? 0,
            'total' => $data['total'] ?? 0,
            'discount' => $data['discount'] ?? 0,
            'status' => 'pending',
            'payment_status' => 'pending',
            'waiter_id' => $data['waiter_id'],
            'created_by' => $data['waiter_id'],
            'notes' => $data['notes'] ?? null
        ];
        
        $query = "INSERT INTO orders (order_number, type, table_id, customer_name, customer_phone, 
                  customer_address, customer_notes, subtotal, tax, delivery_fee, total, discount,
                  status, payment_status, waiter_id, created_by, notes) 
                  VALUES (:order_number, :type, :table_id, :customer_name, :customer_phone, 
                  :customer_address, :customer_notes, :subtotal, :tax, :delivery_fee, :total, :discount,
                  :status, :payment_status, :waiter_id, :created_by, :notes)";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($orderData);
        
        $order_id = $this->db->lastInsertId();
        
        $this->db->commit();
        return $order_id;
    } catch (Exception $e) {
        $this->db->rollback();
        error_log("Error creating order: " . $e->getMessage());
        return false;
    }
}

    
    /**
     * Add item to order with automatic product price lookup and duplicate handling
     */
    public function addItem($order_id, $product_id, $quantity, $notes = '') {
        // Get product info
        $product_query = "SELECT price FROM products WHERE id = :product_id";
        $product_stmt = $this->db->prepare($product_query);
        $product_stmt->bindParam(':product_id', $product_id);
        $product_stmt->execute();
        $product = $product_stmt->fetch();
        
        if (!$product) return false;
        
        $unit_price = $product['price'];
        
        // Check if item already exists
        $check_query = "SELECT id, quantity FROM order_items WHERE order_id = :order_id AND product_id = :product_id";
        $check_stmt = $this->db->prepare($check_query);
        $check_stmt->execute(['order_id' => $order_id, 'product_id' => $product_id]);
        $existing_item = $check_stmt->fetch();
        
        if ($existing_item) {
            // Update existing item
            $new_quantity = $existing_item['quantity'] + $quantity;
            return $this->updateItemQuantity($existing_item['id'], $new_quantity);
        } else {
            // Add new item
            $subtotal = $quantity * $unit_price;
            
            $query = "INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, notes) 
                      VALUES (:order_id, :product_id, :quantity, :unit_price, :subtotal, :notes)";
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute([
                'order_id' => $order_id,
                'product_id' => $product_id,
                'quantity' => $quantity,
                'unit_price' => $unit_price,
                'subtotal' => $subtotal,
                'notes' => $notes
            ]);
        }
    }
    
    public function getById($id) {
        $query = "SELECT o.*, u.full_name as waiter_name, t.number as table_number 
                  FROM orders o 
                  LEFT JOIN users u ON o.waiter_id = u.id 
                  LEFT JOIN tables t ON o.table_id = t.id 
                  WHERE o.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function getItems($order_id) {
        $query = "SELECT oi.*, p.name as product_name, p.image as product_image 
                  FROM order_items oi 
                  JOIN products p ON oi.product_id = p.id 
                  WHERE oi.order_id = :order_id 
                  ORDER BY oi.id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getByStatus($status, $type = null) {
        $where_clause = "WHERE o.status = :status";
        if ($type) {
            $where_clause .= " AND o.type = :type";
        }
        
        $query = "SELECT o.*, u.full_name as waiter_name, t.number as table_number 
                  FROM orders o 
                  LEFT JOIN users u ON o.waiter_id = u.id 
                  LEFT JOIN tables t ON o.table_id = t.id 
                  $where_clause 
                  ORDER BY o.created_at ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':status', $status);
        if ($type) {
            $stmt->bindParam(':type', $type);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function updateStatus($id, $status) {
        $query = "UPDATE orders SET status = :status, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }
    
    public function updateItemStatus($item_id, $status) {
        $query = "UPDATE order_items SET status = :status WHERE id = :item_id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['item_id' => $item_id, 'status' => $status]);
    }
    
    public function getAll($limit = null) {
        $query = "SELECT o.*, u.full_name as waiter_name, t.number as table_number 
                  FROM orders o 
                  LEFT JOIN users u ON o.waiter_id = u.id 
                  LEFT JOIN tables t ON o.table_id = t.id 
                  ORDER BY o.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT " . intval($limit);
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function delete($id) {
        $query = "UPDATE orders SET status = 'cancelled' WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }

    public function getActiveOrderByTable($table_id) {
        $query = "SELECT o.*, u.full_name as waiter_name, t.number as table_number 
                  FROM orders o 
                  LEFT JOIN users u ON o.waiter_id = u.id 
                  LEFT JOIN tables t ON o.table_id = t.id 
                  WHERE o.table_id = :table_id 
                  AND o.status NOT IN ('delivered', 'cancelled') 
                  ORDER BY o.created_at DESC 
                  LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':table_id', $table_id);
        $stmt->execute();
        
        return $stmt->fetch();
    }

    /**
     * Get order items (alias for getItems to match the new code)
     */
    public function getOrderItems($order_id) {
        return $this->getItems($order_id);
    }

    /**
     * Update total recalculating from items
     */
    public function updateTotal($order_id) {
        $query = "UPDATE orders SET 
                  subtotal = (SELECT COALESCE(SUM(subtotal), 0) FROM order_items WHERE order_id = :order_id),
                  total = (SELECT COALESCE(SUM(subtotal), 0) FROM order_items WHERE order_id = :order_id) + tax + delivery_fee
                  WHERE id = :order_id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['order_id' => $order_id]);
    }

    /**
     * Remove item from order and adjust inventory if needed
     */
    public function removeItem($item_id) {
        try {
            $this->db->beginTransaction();
            
            // Get item info before deleting
            $query = "SELECT oi.*, o.status, o.order_number, p.name as product_name, p.track_inventory 
                      FROM order_items oi 
                      JOIN orders o ON oi.order_id = o.id 
                      JOIN products p ON oi.product_id = p.id 
                      WHERE oi.id = :item_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['item_id' => $item_id]);
            $item = $stmt->fetch();
            
            if (!$item) {
                $this->db->rollback();
                return false;
            }
            
            // Delete the item
            $query = "DELETE FROM order_items WHERE id = :item_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute(['item_id' => $item_id]);
            
            // If order was confirmed, return inventory (only for products with track_inventory = 1)
            $confirmed_statuses = ['confirmed', 'preparing', 'ready', 'delivered'];
            if (in_array($item['status'], $confirmed_statuses) && $item['track_inventory'] == 1) {
                $this->adjustInventory(
                    $item['product_id'], 
                    $item['quantity'], 
                    'return', 
                    'Item eliminado de orden ' . $item['order_number']
                );
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error removing item: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update item quantity and adjust inventory if needed
     */
    public function updateItemQuantity($item_id, $quantity) {
        try {
            $this->db->beginTransaction();
            
            if ($quantity <= 0) {
                $result = $this->removeItem($item_id);
                $this->db->commit();
                return $result;
            }
            
            // Get current item info including order status and product track_inventory
            $query = "SELECT oi.*, o.status, o.order_number, p.name as product_name, p.track_inventory 
                      FROM order_items oi 
                      JOIN orders o ON oi.order_id = o.id 
                      JOIN products p ON oi.product_id = p.id 
                      WHERE oi.id = :item_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':item_id', $item_id);
            $stmt->execute();
            $item = $stmt->fetch();
            
            if (!$item) {
                $this->db->rollback();
                return false;
            }
            
            $old_quantity = $item['quantity'];
            $new_quantity = $quantity;
            $quantity_difference = $new_quantity - $old_quantity;
            
            // Update order item
            $new_subtotal = $quantity * $item['unit_price'];
            $query = "UPDATE order_items SET quantity = :quantity, subtotal = :subtotal WHERE id = :item_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'quantity' => $quantity,
                'subtotal' => $new_subtotal,
                'item_id' => $item_id
            ]);
            
            // Adjust inventory only if:
            // 1. Order is confirmed (not pending)
            // 2. Product has track_inventory enabled
            // 3. There's an actual quantity change
            $confirmed_statuses = ['confirmed', 'preparing', 'ready', 'delivered'];
            if (in_array($item['status'], $confirmed_statuses) && 
                $item['track_inventory'] == 1 && 
                $quantity_difference != 0) {
                
                if ($quantity_difference > 0) {
                    // Increased quantity: decrease more from inventory
                    $this->adjustInventory(
                        $item['product_id'], 
                        abs($quantity_difference), 
                        'decrease', 
                        'Aumento de cantidad en orden ' . $item['order_number'] . ' (de ' . $old_quantity . ' a ' . $new_quantity . ')'
                    );
                } else {
                    // Decreased quantity: return to inventory
                    $this->adjustInventory(
                        $item['product_id'], 
                        abs($quantity_difference), 
                        'return', 
                        'Reducción de cantidad en orden ' . $item['order_number'] . ' (de ' . $old_quantity . ' a ' . $new_quantity . ')'
                    );
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error updating item quantity: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update payment status
     */
    public function updatePaymentStatus($order_id, $status) {
        $query = "UPDATE orders SET payment_status = :status WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $order_id, 'status' => $status]);
    }

    /**
     * Generate order number
     */
    private function generateOrderNumber() {
    // Asegurar zona horaria correcta
    getSystemTimezone();
    $date = date('Ymd');
    
    // Get last order number for today
    $query = "SELECT order_number FROM orders 
              WHERE DATE(created_at) = CURDATE() 
              AND order_number LIKE :pattern
              ORDER BY id DESC LIMIT 1";
    $stmt = $this->db->prepare($query);
    $stmt->execute(['pattern' => $date . '%']);
    $last_order = $stmt->fetch();
    
    if ($last_order && $last_order['order_number']) {
        $last_number = intval(substr($last_order['order_number'], -3));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    
    return $date . sprintf('%03d', $new_number);
}

    /**
     * Adjust inventory for a product
     * @param int $product_id Product ID
     * @param int $quantity Quantity to adjust (always positive)
     * @param string $operation 'decrease' or 'return'
     * @param string $notes Notes for the adjustment
     */
    private function adjustInventory($product_id, $quantity, $operation, $notes) {
        try {
            // Update product stock using stock_quantity field (compatible with stock_functions.php)
            if ($operation === 'return') {
                // Return to inventory (increase stock)
                $query = "UPDATE products 
                         SET stock_quantity = stock_quantity + :quantity, 
                             updated_at = NOW() 
                         WHERE id = :product_id 
                         AND track_inventory = 1";
            } else {
                // Decrease from inventory
                $query = "UPDATE products 
                         SET stock_quantity = stock_quantity - :quantity, 
                             updated_at = NOW() 
                         WHERE id = :product_id 
                         AND track_inventory = 1";
            }
            
            $stmt = $this->db->prepare($query);
            $result = $stmt->execute([
                'quantity' => abs($quantity),
                'product_id' => $product_id
            ]);
            
            if (!$result) {
                throw new Exception("Error al actualizar stock del producto ID: " . $product_id);
            }
            
            // Register movement in inventory_movements if table exists
            try {
                $movement_type = ($operation === 'return') ? 'entry' : 'exit';
                $query = "INSERT INTO inventory_movements (product_id, type, quantity, notes, created_at) 
                          VALUES (:product_id, :type, :quantity, :notes, NOW())";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    'product_id' => $product_id,
                    'type' => $movement_type,
                    'quantity' => abs($quantity),
                    'notes' => $notes
                ]);
            } catch (Exception $e) {
                // If inventory_movements table doesn't exist, just log it but don't fail
                error_log("Note: inventory_movements table may not exist: " . $e->getMessage());
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error in adjustInventory: " . $e->getMessage());
            throw $e;
        }
    }

}