<?php
// call_waiter.php
require_once 'config/config.php';
require_once 'config/database.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mesa'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $mesa = intval($_POST['mesa']);
        
        $stmt = $conn->prepare("INSERT INTO waiter_calls (mesa, created_at, status) VALUES (?, NOW(), 'pending')");
        $stmt->execute([$mesa]);
        
        echo json_encode(["status" => "success", "message" => "Llamada registrada"]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al registrar la llamada: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Método no válido o mesa no especificada"]);
}