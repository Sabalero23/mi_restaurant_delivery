<?php
// admin/api/get_product_names.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../models/Product.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requireLogin();

if (!$auth->hasPermission('products')) {
    http_response_code(403);
    echo json_encode(['error' => 'Sin permisos']);
    exit();
}

$productModel = new Product();

// Obtener el término de búsqueda
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    if (empty($search)) {
        // Si no hay búsqueda, devolver todos los nombres
        $query = "SELECT DISTINCT name FROM products WHERE is_active = 1 ORDER BY name";
        $stmt = $db->prepare($query);
    } else {
        // Buscar nombres que contengan el término
        $query = "SELECT DISTINCT name FROM products 
                  WHERE is_active = 1 AND name LIKE :search 
                  ORDER BY name 
                  LIMIT 10";
        $stmt = $db->prepare($query);
        $searchParam = "%{$search}%";
        $stmt->bindParam(':search', $searchParam);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($results);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>