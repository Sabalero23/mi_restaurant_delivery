<?php
// models/Table.php
class Table {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function getAll() {
        $query = "SELECT t.*, 
                  COUNT(CASE WHEN o.status IN ('pending', 'confirmed', 'preparing') THEN 1 END) as active_orders,
                  MAX(o.created_at) as last_order_time
                  FROM tables t 
                  LEFT JOIN orders o ON t.id = o.table_id AND o.status IN ('pending', 'confirmed', 'preparing', 'ready')
                  GROUP BY t.id 
                  ORDER BY t.number";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getById($id) {
        $query = "SELECT t.*, 
                  COUNT(CASE WHEN o.status IN ('pending', 'confirmed', 'preparing') THEN 1 END) as active_orders
                  FROM tables t 
                  LEFT JOIN orders o ON t.id = o.table_id AND o.status IN ('pending', 'confirmed', 'preparing', 'ready')
                  WHERE t.id = :id 
                  GROUP BY t.id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function getByNumber($number) {
        $query = "SELECT * FROM tables WHERE number = :number";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':number', $number);
        $stmt->execute();
        
        return $stmt->fetch();
    }
    
    public function create($data) {
        $query = "INSERT INTO tables (number, capacity, location, status) 
                  VALUES (:number, :capacity, :location, :status)";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute($data);
    }
    
    public function update($id, $data) {
        $query = "UPDATE tables SET 
                  number = :number, 
                  capacity = :capacity, 
                  location = :location,
                  status = :status
                  WHERE id = :id";
        
        $data['id'] = $id;
        $stmt = $this->db->prepare($query);
        return $stmt->execute($data);
    }
    
    public function updateStatus($id, $status) {
        $query = "UPDATE tables SET status = :status WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $id, 'status' => $status]);
    }
    
    public function delete($id) {
        // Check if table has active orders
        $query = "SELECT COUNT(*) as count FROM orders 
                  WHERE table_id = :id AND status IN ('pending', 'confirmed', 'preparing')";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            return false; // Cannot delete table with active orders
        }
        
        $query = "DELETE FROM tables WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    public function getActiveOrders($table_id) {
        $query = "SELECT o.*, 
                  SUM(oi.quantity * oi.unit_price) as calculated_total,
                  COUNT(oi.id) as item_count
                  FROM orders o
                  LEFT JOIN order_items oi ON o.id = oi.order_id
                  WHERE o.table_id = :table_id 
                  AND o.status IN ('pending', 'confirmed', 'preparing', 'ready')
                  GROUP BY o.id
                  ORDER BY o.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':table_id', $table_id);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function getTotalSales($table_id, $date_from = null, $date_to = null) {
        $where_clause = "WHERE o.table_id = :table_id AND o.status = 'delivered' AND o.payment_status = 'paid'";
        
        if ($date_from) {
            $where_clause .= " AND DATE(o.created_at) >= :date_from";
        }
        if ($date_to) {
            $where_clause .= " AND DATE(o.created_at) <= :date_to";
        }
        
        $query = "SELECT 
                  COUNT(*) as order_count,
                  COALESCE(SUM(o.total), 0) as total_sales,
                  COALESCE(AVG(o.total), 0) as average_sale
                  FROM orders o 
                  $where_clause";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':table_id', $table_id);
        
        if ($date_from) {
            $stmt->bindParam(':date_from', $date_from);
        }
        if ($date_to) {
            $stmt->bindParam(':date_to', $date_to);
        }
        
        $stmt->execute();
        return $stmt->fetch();
    }
    
    public function getAvailableTables() {
        $query = "SELECT * FROM tables WHERE status = 'available' ORDER BY number";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    public function occupyTable($table_id, $waiter_id = null) {
        $query = "UPDATE tables SET status = 'occupied' WHERE id = :id AND status = 'available'";
        $stmt = $this->db->prepare($query);
        return $stmt->execute(['id' => $table_id]);
    }
    
    public function releaseTable($table_id) {
        // Check if table has any pending orders
        $query = "SELECT COUNT(*) as count FROM orders 
                  WHERE table_id = :id AND status IN ('pending', 'confirmed', 'preparing')";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $table_id);
        $stmt->execute();
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            $query = "UPDATE tables SET status = 'available' WHERE id = :id";
            $stmt = $this->db->prepare($query);
            return $stmt->execute(['id' => $table_id]);
        }
        
        return false;
    }
    
    /**
 * Get available tables (alias for getAvailableTables to match the new code)
 */
public function getAvailable() {
    return $this->getAvailableTables();
}
}