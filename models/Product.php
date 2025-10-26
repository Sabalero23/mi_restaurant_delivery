<?php
// models/Product.php
class Product {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getAll($category_id = null, $active_only = true) {
        $where_clause = $active_only ? "WHERE p.is_active = 1" : "WHERE 1=1";
        if ($category_id) {
            $where_clause .= " AND p.category_id = :category_id";
        }
        
        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  $where_clause 
                  ORDER BY c.sort_order, p.name";
        
        $stmt = $this->db->prepare($query);
        if ($category_id) {
            $stmt->bindParam(':category_id', $category_id);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    /**
     * Crea un nuevo producto con soporte completo para inventario
     */
    public function create($data) {
        // Construir la consulta dinámicamente según los campos disponibles
        $query = "INSERT INTO products (
                    category_id, 
                    name, 
                    description, 
                    price, 
                    cost, 
                    image, 
                    preparation_time, 
                    is_available, 
                    is_active, 
                    track_inventory, 
                    stock_quantity, 
                    low_stock_alert
                  ) VALUES (
                    :category_id, 
                    :name, 
                    :description, 
                    :price, 
                    :cost, 
                    :image, 
                    :preparation_time, 
                    :is_available, 
                    :is_active, 
                    :track_inventory, 
                    :stock_quantity, 
                    :low_stock_alert
                  )";
        
        try {
            $stmt = $this->db->prepare($query);
            
            // Asegurar que todos los campos tengan valores por defecto
            $params = [
                ':category_id' => $data['category_id'] ?? null,
                ':name' => $data['name'] ?? '',
                ':description' => $data['description'] ?? '',
                ':price' => $data['price'] ?? 0,
                ':cost' => $data['cost'] ?? 0,
                ':image' => $data['image'] ?? null,
                ':preparation_time' => $data['preparation_time'] ?? 0,
                ':is_available' => $data['is_available'] ?? 1,
                ':is_active' => $data['is_active'] ?? 1,
                ':track_inventory' => $data['track_inventory'] ?? 0,
                ':stock_quantity' => $data['stock_quantity'] ?? null,
                ':low_stock_alert' => $data['low_stock_alert'] ?? 10
            ];
            
            // Log para debugging
            error_log("Product create params: " . print_r($params, true));
            
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Error creating product: " . $e->getMessage());
            error_log("SQL State: " . $e->getCode());
            throw new Exception("Error al crear producto: " . $e->getMessage());
        }
    }
    
    /**
     * Actualiza un producto con soporte completo para inventario
     */
    public function update($id, $data) {
        $query = "UPDATE products SET 
                  category_id = :category_id, 
                  name = :name, 
                  description = :description, 
                  price = :price, 
                  cost = :cost, 
                  image = :image, 
                  preparation_time = :preparation_time, 
                  is_available = :is_available,
                  is_active = :is_active,
                  track_inventory = :track_inventory,
                  stock_quantity = :stock_quantity,
                  low_stock_alert = :low_stock_alert,
                  updated_at = CURRENT_TIMESTAMP
                  WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($query);
            
            // Preparar parámetros con valores por defecto
            $params = [
                ':id' => $id,
                ':category_id' => $data['category_id'] ?? null,
                ':name' => $data['name'] ?? '',
                ':description' => $data['description'] ?? '',
                ':price' => $data['price'] ?? 0,
                ':cost' => $data['cost'] ?? 0,
                ':image' => $data['image'] ?? null,
                ':preparation_time' => $data['preparation_time'] ?? 0,
                ':is_available' => $data['is_available'] ?? 1,
                ':is_active' => $data['is_active'] ?? 1,
                ':track_inventory' => $data['track_inventory'] ?? 0,
                ':stock_quantity' => $data['stock_quantity'] ?? null,
                ':low_stock_alert' => $data['low_stock_alert'] ?? 10
            ];
            
            error_log("Product update params: " . print_r($params, true));
            
            return $stmt->execute($params);
            
        } catch (PDOException $e) {
            error_log("Error updating product: " . $e->getMessage());
            throw new Exception("Error al actualizar producto: " . $e->getMessage());
        }
    }
    
/**
 * Verifica si un producto tiene pedidos asociados
 */
public function hasOrders($id) {
    try {
        $query = "SELECT COUNT(*) as count 
                  FROM order_items 
                  WHERE product_id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch();
        return $result['count'] > 0;
        
    } catch (PDOException $e) {
        error_log("Error checking product orders: " . $e->getMessage());
        // Si hay error, asumir que tiene pedidos (seguro)
        return true;
    }
}

/**
 * Soft Delete - Solo marca el producto como inactivo
 */
public function softDelete($id) {
    $query = "UPDATE products 
              SET is_active = 0, 
                  updated_at = CURRENT_TIMESTAMP 
              WHERE id = :id";
    
    try {
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (PDOException $e) {
        error_log("Error soft deleting product: " . $e->getMessage());
        return false;
    }
}

/**
 * Hard Delete - Elimina permanentemente el producto
 */
public function hardDelete($id) {
    try {
        // Primero obtener la información del producto para eliminar la imagen
        $product = $this->getById($id);
        
        if ($product && !empty($product['image'])) {
            // Eliminar la imagen física del servidor
            $imagePath = '../' . ltrim($product['image'], '/');
            if (file_exists($imagePath)) {
                unlink($imagePath);
                error_log("Deleted product image: " . $imagePath);
            }
        }
        
        // Eliminar el producto de la base de datos
        $query = "DELETE FROM products WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        
        $result = $stmt->execute();
        
        if ($result) {
            error_log("Product ID $id deleted permanently from database");
        }
        
        return $result;
        
    } catch (PDOException $e) {
        error_log("Error hard deleting product: " . $e->getMessage());
        throw new Exception("Error al eliminar producto: " . $e->getMessage());
    }
}

/**
 * Eliminación inteligente:
 * - Si tiene pedidos: Soft delete (desactivar)
 * - Si NO tiene pedidos: Hard delete (eliminar permanentemente)
 */
public function delete($id) {
    try {
        // Verificar si el producto tiene pedidos asociados
        if ($this->hasOrders($id)) {
            // Tiene pedidos: Solo desactivar
            if ($this->softDelete($id)) {
                return [
                    'success' => true,
                    'type' => 'soft',
                    'message' => 'Producto desactivado correctamente (tiene pedidos asociados)'
                ];
            } else {
                return [
                    'success' => false,
                    'type' => 'soft',
                    'message' => 'Error al desactivar el producto'
                ];
            }
        } else {
            // NO tiene pedidos: Eliminar permanentemente
            if ($this->hardDelete($id)) {
                return [
                    'success' => true,
                    'type' => 'hard',
                    'message' => 'Producto eliminado permanentemente de la base de datos'
                ];
            } else {
                return [
                    'success' => false,
                    'type' => 'hard',
                    'message' => 'Error al eliminar el producto'
                ];
            }
        }
        
    } catch (Exception $e) {
        error_log("Error in smart delete: " . $e->getMessage());
        return [
            'success' => false,
            'type' => 'error',
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}
    
    /**
     * Obtiene todos los productos activos (compatible con order-create.php)
     */
    public function getAllActive() {
        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.is_active = 1 AND p.is_available = 1
                  ORDER BY c.sort_order, p.name";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Actualiza solo el estado de disponibilidad de un producto
     */
    public function updateAvailability($id, $is_available) {
        $query = "UPDATE products SET 
                  is_available = :is_available,
                  updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':is_available', $is_available, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating availability: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Actualiza solo el estado activo de un producto
     */
    public function updateActiveStatus($id, $is_active) {
        $query = "UPDATE products SET 
                  is_active = :is_active,
                  updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':is_active', $is_active, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating active status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Actualiza solo el stock de un producto
     */
    public function updateStock($id, $stock_quantity) {
        $query = "UPDATE products SET 
                  stock_quantity = :stock_quantity,
                  updated_at = CURRENT_TIMESTAMP 
                  WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':stock_quantity', $stock_quantity, PDO::PARAM_INT);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating stock: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Verifica si un producto tiene stock bajo
     */
    public function hasLowStock($id) {
        $product = $this->getById($id);
        
        if (!$product || !$product['track_inventory']) {
            return false;
        }
        
        $stock = intval($product['stock_quantity']);
        $alert = intval($product['low_stock_alert']);
        
        return $stock <= $alert && $stock > 0;
    }
    
    /**
     * Verifica si un producto está sin stock
     */
    public function isOutOfStock($id) {
        $product = $this->getById($id);
        
        if (!$product || !$product['track_inventory']) {
            return false;
        }
        
        return intval($product['stock_quantity']) <= 0;
    }
    
    /**
     * Obtiene estadísticas de inventario
     */
    public function getInventoryStats() {
        $query = "SELECT 
                    COUNT(*) as total_products,
                    SUM(CASE WHEN track_inventory = 1 THEN 1 ELSE 0 END) as tracked_products,
                    SUM(CASE WHEN track_inventory = 1 AND stock_quantity > low_stock_alert THEN 1 ELSE 0 END) as good_stock,
                    SUM(CASE WHEN track_inventory = 1 AND stock_quantity <= low_stock_alert AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                    SUM(CASE WHEN track_inventory = 1 AND stock_quantity <= 0 THEN 1 ELSE 0 END) as out_of_stock
                  FROM products 
                  WHERE is_active = 1";
        
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error getting inventory stats: " . $e->getMessage());
            return [
                'total_products' => 0,
                'tracked_products' => 0,
                'good_stock' => 0,
                'low_stock' => 0,
                'out_of_stock' => 0
            ];
        }
    }
}
