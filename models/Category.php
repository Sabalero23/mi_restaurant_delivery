<?php
// models/Category.php
class Category {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getAll($active_only = true) {
        $where_clause = $active_only ? "WHERE c.is_active = 1" : "";
        
        $query = "SELECT c.*, 
                  COUNT(p.id) as product_count,
                  COUNT(CASE WHEN p.is_available = 1 AND p.is_active = 1 THEN 1 END) as available_products
                  FROM categories c 
                  LEFT JOIN products p ON c.id = p.category_id 
                  $where_clause 
                  GROUP BY c.id 
                  ORDER BY c.sort_order, c.name";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $query = "SELECT c.*, 
                  COUNT(p.id) as product_count
                  FROM categories c 
                  LEFT JOIN products p ON c.id = p.category_id 
                  WHERE c.id = :id 
                  GROUP BY c.id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function create($data) {
        // Get next sort order
        $query = "SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM categories";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        $data['sort_order'] = $result['next_order'];
        
        $query = "INSERT INTO categories (name, description, image, sort_order, is_active) 
                  VALUES (:name, :description, :image, :sort_order, :is_active)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($data);
    }
    
    public function update($id, $data) {
        $query = "UPDATE categories SET 
                  name = :name, 
                  description = :description, 
                  image = :image,
                  sort_order = :sort_order,
                  is_active = :is_active
                  WHERE id = :id";
        
        $data['id'] = $id;
        $stmt = $this->db->prepare($query);
        return $stmt->execute($data);
    }
    
    public function delete($id) {
        // Check if category has products
        $query = "SELECT COUNT(*) as count FROM products WHERE category_id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            // Soft delete - just mark as inactive
            $query = "UPDATE categories SET is_active = 0 WHERE id = :id";
        } else {
            // Hard delete if no products
            $query = "DELETE FROM categories WHERE id = :id";
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    public function updateSortOrder($id, $sort_order) {
        $query = "UPDATE categories SET sort_order = :sort_order WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id, 'sort_order' => $sort_order]);
    }
    
    public function getProducts($category_id, $available_only = false) {
        $where_clause = "WHERE p.category_id = :category_id";
        if ($available_only) {
            $where_clause .= " AND p.is_available = 1 AND p.is_active = 1";
        }
        
        $query = "SELECT p.* FROM products p 
                  $where_clause 
                  ORDER BY p.name";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getWithProductCount() {
        $query = "SELECT c.*, 
                  COUNT(p.id) as total_products,
                  COUNT(CASE WHEN p.is_available = 1 AND p.is_active = 1 THEN 1 END) as available_products,
                  COUNT(CASE WHEN p.is_available = 0 OR p.is_active = 0 THEN 1 END) as unavailable_products
                  FROM categories c 
                  LEFT JOIN products p ON c.id = p.category_id 
                  WHERE c.is_active = 1
                  GROUP BY c.id 
                  ORDER BY c.sort_order, c.name";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function moveUp($id) {
        $this->db->beginTransaction();
        
        try {
            // Get current sort order
            $query = "SELECT sort_order FROM categories WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $current = $stmt->fetch();
            
            if (!$current) {
                throw new Exception('Category not found');
            }
            
            // Find previous category
            $query = "SELECT id, sort_order FROM categories 
                      WHERE sort_order < :sort_order 
                      ORDER BY sort_order DESC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sort_order', $current['sort_order']);
            $stmt->execute();
            $previous = $stmt->fetch();
            
            if ($previous) {
                // Swap sort orders
                $this->updateSortOrder($id, $previous['sort_order']);
                $this->updateSortOrder($previous['id'], $current['sort_order']);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    public function moveDown($id) {
        $this->db->beginTransaction();
        
        try {
            // Get current sort order
            $query = "SELECT sort_order FROM categories WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $current = $stmt->fetch();
            
            if (!$current) {
                throw new Exception('Category not found');
            }
            
            // Find next category
            $query = "SELECT id, sort_order FROM categories 
                      WHERE sort_order > :sort_order 
                      ORDER BY sort_order ASC LIMIT 1";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':sort_order', $current['sort_order']);
            $stmt->execute();
            $next = $stmt->fetch();
            
            if ($next) {
                // Swap sort orders
                $this->updateSortOrder($id, $next['sort_order']);
                $this->updateSortOrder($next['id'], $current['sort_order']);
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollback();
            return false;
        }
    }
}