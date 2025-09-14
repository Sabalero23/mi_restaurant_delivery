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
    
    public function create($data) {
        $query = "INSERT INTO products (category_id, name, description, price, cost, image, preparation_time, is_available) 
                  VALUES (:category_id, :name, :description, :price, :cost, :image, :preparation_time, :is_available)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($data);
    }
    
    public function update($id, $data) {
        $query = "UPDATE products SET 
                  category_id = :category_id, 
                  name = :name, 
                  description = :description, 
                  price = :price, 
                  cost = :cost, 
                  image = :image, 
                  preparation_time = :preparation_time, 
                  is_available = :is_available 
                  WHERE id = :id";
        
        $data['id'] = $id;
        $stmt = $this->db->prepare($query);
        return $stmt->execute($data);
    }
    
    public function delete($id) {
        $query = "UPDATE products SET is_active = 0 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    /**
 * Get all active products (compatible with order-create.php)
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
}