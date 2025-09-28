<?php
// check_calls.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación y permisos
$auth = new Auth();
$auth->requireLogin();

// Solo usuarios con permiso 'tables' pueden verificar llamadas de mesero
if (!$auth->hasPermission('tables')) {
    http_response_code(403);
    echo json_encode([
        "status" => "error", 
        "message" => "No tienes permisos para gestionar llamadas de mesero"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();

    $stmt = $conn->prepare("SELECT * FROM waiter_calls WHERE status = 'pending' ORDER BY created_at ASC LIMIT 1");
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(null);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Error al verificar llamadas: " . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}