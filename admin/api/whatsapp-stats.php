<?php
// admin/api/whatsapp-stats.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';

header('Content-Type: application/json');

$auth = new Auth();

// Verificar que el usuario esté logueado
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Verificar permisos para WhatsApp
if (!$auth->hasPermission('all') && !$auth->hasPermission('online_orders')) {
    echo json_encode(['success' => false, 'error' => 'Sin permisos']);
    exit;
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar si la tabla existe
    $check_table = $db->query("SHOW TABLES LIKE 'whatsapp_messages'");
    if ($check_table->rowCount() == 0) {
        echo json_encode([
            'success' => true,
            'unread_messages' => 0,
            'total_conversations' => 0,
            'recent_messages' => []
        ]);
        exit;
    }
    
    // Obtener mensajes no leídos (solo de clientes)
    $unread_query = "SELECT COUNT(*) as unread_count FROM whatsapp_messages 
                     WHERE is_read = 0 AND is_from_customer = 1";
    $unread_stmt = $db->prepare($unread_query);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->fetch(PDO::FETCH_ASSOC);
    $unread_count = $unread_result['unread_count'];
    
    // Obtener total de conversaciones
    $conversations_query = "SELECT COUNT(DISTINCT phone_number) as total_conversations 
                           FROM whatsapp_messages";
    $conversations_stmt = $db->prepare($conversations_query);
    $conversations_stmt->execute();
    $conversations_result = $conversations_stmt->fetch(PDO::FETCH_ASSOC);
    $total_conversations = $conversations_result['total_conversations'];
    
    // Obtener últimos mensajes no leídos para notificaciones
    $recent_query = "SELECT phone_number, content, created_at 
                     FROM whatsapp_messages 
                     WHERE is_read = 0 AND is_from_customer = 1 
                     ORDER BY created_at DESC LIMIT 3";
    $recent_stmt = $db->prepare($recent_query);
    $recent_stmt->execute();
    $recent_messages = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Verificar si hay mensajes nuevos desde la última verificación
    $last_check_time = $_GET['last_check'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
    $new_messages_query = "SELECT COUNT(*) as new_count FROM whatsapp_messages 
                          WHERE is_read = 0 AND is_from_customer = 1 AND created_at > ?";
    $new_stmt = $db->prepare($new_messages_query);
    $new_stmt->execute([$last_check_time]);
    $new_result = $new_stmt->fetch(PDO::FETCH_ASSOC);
    $new_messages_count = $new_result['new_count'];
    
    echo json_encode([
        'success' => true,
        'unread_messages' => (int)$unread_count,
        'total_conversations' => (int)$total_conversations,
        'new_messages_count' => (int)$new_messages_count,
        'recent_messages' => $recent_messages,
        'last_check' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error del servidor: ' . $e->getMessage()
    ]);
}
?>