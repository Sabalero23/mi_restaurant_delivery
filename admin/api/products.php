<?php
// admin/api/products.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../config/functions.php';
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
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $product = $productModel->getById($_GET['id']);
                if (!$product) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Producto no encontrado']);
                    return;
                }
                echo json_encode($product);
            } else {
                $category_id = $_GET['category_id'] ?? null;
                $active_only = isset($_GET['active_only']) ? (bool)$_GET['active_only'] : false;
                $products = $productModel->getAll($category_id, $active_only);
                echo json_encode($products);
            }
            break;
            
        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input) {
                http_response_code(400);
                echo json_encode(['error' => 'Datos inválidos']);
                return;
            }
            
            $result = $productModel->create($input);
            echo json_encode(['success' => $result]);
            break;
            
        case 'PUT':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!$input || !isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID requerido']);
                return;
            }
            
            $id = $input['id'];
            unset($input['id']);
            
            $result = $productModel->update($id, $input);
            echo json_encode(['success' => $result]);
            break;
            
        case 'DELETE':
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'ID requerido']);
                return;
            }
            
            $result = $productModel->delete($input['id']);
            echo json_encode(['success' => $result]);
            break;
            
        default:
            throw new Exception('Método no permitido');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}