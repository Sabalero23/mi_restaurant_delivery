<?php
// attend_call.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar autenticación y permisos
$auth = new Auth();
$auth->requireLogin();

// Solo usuarios con permiso 'tables' pueden atender llamadas de mesero
if (!$auth->hasPermission('tables')) {
    http_response_code(403);
    echo json_encode([
        "status" => "error", 
        "message" => "No tienes permisos para atender llamadas de mesero"
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

if (isset($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $id = intval($_GET['id']);
        $user_id = $_SESSION['user_id']; // Para auditoría
        
        // Actualizar llamada y registrar quién la atendió
        $stmt = $conn->prepare("UPDATE waiter_calls SET status = 'attended', attended_by = ?, attended_at = NOW() WHERE id = ? AND status = 'pending'");
        $stmt->execute([$user_id, $id]);
        
        if ($stmt->rowCount() > 0) {
            // Log de seguridad para auditoría
            $auth->logSecurityEvent('waiter_call_attended', "Call ID: $id attended by user: $user_id");
            
            echo json_encode([
                "status" => "success", 
                "message" => "Llamada atendida correctamente",
                "attended_by" => $_SESSION['full_name'] ?? $_SESSION['username']
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                "status" => "warning", 
                "message" => "No se encontró la llamada, ya fue atendida o no está pendiente"
            ], JSON_UNESCAPED_UNICODE);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            "status" => "error", 
            "message" => "Error al atender la llamada: " . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
} else {
    http_response_code(400);
    echo json_encode([
        "status" => "error", 
        "message" => "ID de llamada no especificado"
    ], JSON_UNESCAPED_UNICODE);
}