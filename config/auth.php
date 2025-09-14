<?php
// config/auth.php
session_start();

class Auth {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    public function login($username, $password) {
        try {
            $query = "SELECT u.*, r.name as role_name, r.permissions 
                      FROM users u 
                      LEFT JOIN roles r ON u.role_id = r.id 
                      WHERE (u.username = :username OR u.email = :username) 
                      AND u.is_active = 1";
            
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role_id'] = $user['role_id'];
                $_SESSION['role_name'] = $user['role_name'];
                $_SESSION['permissions'] = json_decode($user['permissions'] ?? '[]', true);
                $_SESSION['logged_in'] = true;
                $_SESSION['login_time'] = time();
                
                // Update last login
                $this->updateLastLogin($user['id']);
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    public function logout() {
        session_unset();
        session_destroy();
        session_start();
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
        
        // Check session timeout (4 hours)
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time'] > 14400)) {
            $this->logout();
            header('Location: login.php?timeout=1');
            exit();
        }
    }
    
    public function hasPermission($permission) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $permissions = $_SESSION['permissions'] ?? [];
        
        // Admin has all permissions
        if (in_array('all', $permissions)) {
            return true;
        }
        
        return in_array($permission, $permissions);
    }
    
    public function requirePermission($permission) {
        if (!$this->hasPermission($permission)) {
            header('Location: pages/403.php');
            exit();
        }
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function getUserRole() {
        return $_SESSION['role_name'] ?? null;
    }
    
    public function getUserName() {
        return $_SESSION['full_name'] ?? null;
    }
    
    private function updateLastLogin($user_id) {
        try {
            $query = "UPDATE users SET updated_at = CURRENT_TIMESTAMP WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
        } catch (Exception $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }
    
    public function changePassword($user_id, $old_password, $new_password) {
        try {
            // Verify old password
            $query = "SELECT password FROM users WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if (!$user || !password_verify($old_password, $user['password'])) {
                return false;
            }
            
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query = "UPDATE users SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':user_id', $user_id);
            
            return $stmt->execute();
        } catch (Exception $e) {
            error_log("Change password error: " . $e->getMessage());
            return false;
        }
    }
    
    public function createUser($data) {
        try {
            $query = "INSERT INTO users (username, email, password, full_name, phone, role_id) 
                      VALUES (:username, :email, :password, :full_name, :phone, :role_id)";
            
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare($query);
            return $stmt->execute($data);
        } catch (Exception $e) {
            error_log("Create user error: " . $e->getMessage());
            return false;
        }
    }
    
    public function getRoles() {
        try {
            $query = "SELECT * FROM roles ORDER BY name";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (Exception $e) {
            error_log("Get roles error: " . $e->getMessage());
            return [];
        }
    }
}