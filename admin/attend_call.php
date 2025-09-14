<?php
// attend_call.php
require_once '../config/config.php';
require_once '../config/database.php';

header('Content-Type: application/json; charset=utf-8');

if (isset($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $id = intval($_GET['id']);
        
        $stmt = $conn->prepare("UPDATE waiter_calls SET status = 'attended' WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(["status" => "success", "message" => "Llamada atendida"]);
        } else {
            echo json_encode(["status" => "warning", "message" => "No se encontró la llamada o ya fue atendida"]);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Error al atender la llamada: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "ID de llamada no especificado"]);
}