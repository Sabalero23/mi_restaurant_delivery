<?php
// admin/get_all_products.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../models/Product.php';

header('Content-Type: application/json');

$auth = new Auth();
$auth->requirePermission('products');

$productModel = new Product();
$products = $productModel->getAll(); // Solo productos activos

// Devolver productos en formato JSON
echo json_encode($products);
?>