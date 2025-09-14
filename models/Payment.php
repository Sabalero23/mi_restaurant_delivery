<?php
// models/Payment.php
class Payment {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Create new payment
     */
    public function create($data) {
        $query = "INSERT INTO payments (order_id, method, amount, reference, user_id) 
                  VALUES (:order_id, :method, :amount, :reference, :user_id)";
        
        try {
            $stmt = $this->db->prepare($query);
            
            if ($stmt->execute($data)) {
                return $this->db->lastInsertId();
            }
            return false;
        } catch (Exception $e) {
            error_log("Error creating payment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payments by order ID
     */
    public function getByOrderId($order_id) {
        $query = "SELECT p.*, u.full_name as user_name 
                  FROM payments p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  WHERE p.order_id = :order_id 
                  ORDER BY p.created_at ASC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get total paid amount for an order
     */
    public function getTotalPaidByOrder($order_id) {
        $query = "SELECT COALESCE(SUM(amount), 0) as total_paid FROM payments WHERE order_id = :order_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? floatval($result['total_paid']) : 0;
    }
    
    /**
     * Get payment by ID
     */
    public function getById($id) {
        $query = "SELECT p.*, u.full_name as user_name 
                  FROM payments p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  WHERE p.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch();
    }
    
    /**
     * Update payment
     */
    public function update($id, $data) {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "{$key} = :{$key}";
        }
        
        $query = "UPDATE payments SET " . implode(', ', $fields) . " WHERE id = :id";
        $data['id'] = $id;
        
        try {
            $stmt = $this->db->prepare($query);
            return $stmt->execute($data);
        } catch (Exception $e) {
            error_log("Error updating payment: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete payment
     */
    public function delete($id) {
        $query = "DELETE FROM payments WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    /**
     * Get all payments with pagination
     */
    public function getAll($limit = null, $offset = null) {
        $query = "SELECT p.*, u.full_name as user_name, o.order_number 
                  FROM payments p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  LEFT JOIN orders o ON p.order_id = o.id 
                  ORDER BY p.created_at DESC";
        
        if ($limit) {
            $query .= " LIMIT " . intval($limit);
            if ($offset) {
                $query .= " OFFSET " . intval($offset);
            }
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get payments by date range
     */
    public function getByDateRange($start_date, $end_date) {
        $query = "SELECT p.*, u.full_name as user_name, o.order_number 
                  FROM payments p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  LEFT JOIN orders o ON p.order_id = o.id 
                  WHERE DATE(p.created_at) BETWEEN :start_date AND :end_date 
                  ORDER BY p.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':start_date', $start_date);
        $stmt->bindParam(':end_date', $end_date);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Get payment statistics
     */
    public function getStats($date = null) {
        $date = $date ?: date('Y-m-d');
        
        $query = "SELECT 
                    method,
                    COUNT(*) as count,
                    SUM(amount) as total
                  FROM payments 
                  WHERE DATE(created_at) = :date
                  GROUP BY method";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':date', $date);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
?>