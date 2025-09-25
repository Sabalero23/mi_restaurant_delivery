<?php
// admin/whatsapp-messages.php - Versión completa con soporte multimedia
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('all');

$database = new Database();
$db = $database->getConnection();

// Procesar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si es upload de archivo multimedia
    if (isset($_FILES['media_file'])) {
        $phone_number = $_POST['phone_number'] ?? '';
        $caption = $_POST['caption'] ?? '';
        
        try {
            require_once '../config/whatsapp_api.php';
            $whatsapp = new WhatsAppAPI();
            
            if (!$whatsapp->isConfigured()) {
                echo json_encode(['success' => false, 'error' => 'API no configurada']);
                exit;
            }
            
            $file = $_FILES['media_file'];
            
            // Validar archivo
            $validation = $whatsapp->validateMediaFile($file['tmp_name']);
            if (!$validation['valid']) {
                echo json_encode(['success' => false, 'error' => $validation['error']]);
                exit;
            }
            
            // Subir a WhatsApp
            $upload_result = $whatsapp->uploadMedia($file['tmp_name'], $validation['category']);
            if (!$upload_result['success']) {
                echo json_encode(['success' => false, 'error' => 'Error al subir: ' . $upload_result['error']]);
                exit;
            }
            
            $media_id = $upload_result['media_id'];
            
            // Enviar según tipo
            switch ($validation['category']) {
                case 'images':
                    $send_result = $whatsapp->sendImageMessage($phone_number, $media_id, $caption);
                    break;
                case 'documents':
                    $send_result = $whatsapp->sendDocumentMessage($phone_number, $media_id, $file['name'], $caption);
                    break;
                case 'audio':
                    $send_result = $whatsapp->sendAudioMessage($phone_number, $media_id);
                    break;
                default:
                    echo json_encode(['success' => false, 'error' => 'Tipo de archivo no soportado']);
                    exit;
            }
            
            if ($send_result['success']) {
                // Guardar en base de datos
                $message_id = $send_result['message_id'] ?? 'sent_' . time() . '_' . rand(1000, 9999);
                
                $insert_query = "INSERT INTO whatsapp_messages 
                    (message_id, phone_number, message_type, content, media_url, media_filename, media_mime_type, media_size, media_caption, is_from_customer, is_read, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 1, NOW())";
                
                $content = $caption ?: '[' . ucfirst($validation['category']) . ': ' . $file['name'] . ']';
                $message_type = $validation['category'] === 'images' ? 'image' : ($validation['category'] === 'audio' ? 'audio' : 'document');
                
                $stmt = $db->prepare($insert_query);
                $stmt->execute([
                    $message_id,
                    $phone_number,
                    $message_type,
                    $content,
                    $media_id,
                    $file['name'],
                    $validation['mime_type'],
                    $validation['file_size'],
                    $caption
                ]);
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Archivo enviado correctamente',
                    'media_id' => $media_id,
                    'message_id' => $message_id
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al enviar: ' . $send_result['error']]);
            }
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Error: ' . $e->getMessage()]);
        }
        exit;
    }
    
    // Procesar otras acciones JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
            case 'refresh_conversations':
                echo json_encode(getConversationsData($db, $input));
                exit;
                
            case 'mark_conversation_read':
                $phone_number = $input['phone_number'];
                try {
                    $query = "UPDATE whatsapp_messages SET is_read = 1 WHERE phone_number = ? AND is_from_customer = 1";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$phone_number]);
                    echo json_encode(['success' => true]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                }
                exit;
                
            case 'send_reply':
                $phone_number = trim($input['phone_number']);
                $message = trim($input['message']);
                
                require_once '../config/whatsapp_api.php';
                $whatsapp = new WhatsAppAPI();
                
                if ($whatsapp->isConfigured()) {
                    $result = $whatsapp->sendTextMessage($phone_number, $message);
                    
                    if ($result['success']) {
                        try {
                            $message_id = $result['message_id'] ?? 'sent_' . time() . '_' . rand(1000, 9999);
                            
                            $insert_query = "INSERT INTO whatsapp_messages 
                                (message_id, phone_number, message_type, content, is_from_customer, is_read, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, NOW())";
                            
                            $stmt = $db->prepare($insert_query);
                            $insert_result = $stmt->execute([
                                $message_id,
                                $phone_number,
                                'text',
                                $message,
                                0, // No es del cliente
                                1  // Ya está leído
                            ]);
                            
                            $result['saved_to_db'] = $insert_result;
                            if ($insert_result) {
                                $result['db_message_id'] = $db->lastInsertId();
                            }
                            
                        } catch (Exception $e) {
                            $result['saved_to_db'] = false;
                            $result['db_error'] = $e->getMessage();
                        }
                    }
                    
                    echo json_encode($result);
                } else {
                    echo json_encode(['success' => false, 'error' => 'API no configurada']);
                }
                exit;
        }
    }
}

// Función para obtener datos de conversaciones
function getConversationsData($db, $filters = []) {
    $phone_filter = $filters['phone'] ?? '';
    $date_filter = $filters['date'] ?? '';
    $unread_only = $filters['unread_only'] ?? false;

    $table_exists = false;
    try {
        $check_table = $db->query("SHOW TABLES LIKE 'whatsapp_messages'");
        $table_exists = $check_table->rowCount() > 0;
    } catch (Exception $e) {
        $table_exists = false;
    }

    $conversations = [];

    if ($table_exists) {
        $where_conditions = [];
        $params = [];

        if ($phone_filter) {
            $where_conditions[] = "phone_number LIKE ?";
            $params[] = "%$phone_filter%";
        }

        if ($date_filter) {
            $where_conditions[] = "DATE(created_at) = ?";
            $params[] = $date_filter;
        }

        $where_sql = empty($where_conditions) ? "" : "WHERE " . implode(" AND ", $where_conditions);

        $normalize_query = "UPDATE whatsapp_messages SET phone_number = TRIM(REPLACE(REPLACE(REPLACE(phone_number, ' ', ''), '-', ''), '+', ''))";
        $db->exec($normalize_query);
        
        $conversations_query = "
            SELECT 
                TRIM(phone_number) as phone_number,
                COUNT(*) as message_count,
                SUM(CASE WHEN is_read = 0 AND is_from_customer = 1 THEN 1 ELSE 0 END) as unread_count,
                MAX(created_at) as last_message_at,
                MIN(created_at) as first_message_at,
                CASE WHEN SUM(CASE WHEN is_read = 0 AND is_from_customer = 1 THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END as has_new_messages
            FROM whatsapp_messages 
            $where_sql
            GROUP BY TRIM(phone_number)
            ORDER BY has_new_messages DESC, last_message_at DESC
        ";

        $stmt = $db->prepare($conversations_query);
        $stmt->execute($params);
        $all_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($unread_only) {
            $all_conversations = array_filter($all_conversations, function($conv) {
                return $conv['unread_count'] > 0;
            });
        }

        $conversations = $all_conversations;

        foreach ($conversations as $index => $conversation) {
            $messages_query = "
                SELECT * FROM whatsapp_messages 
                WHERE TRIM(phone_number) = ? 
                ORDER BY created_at ASC, id ASC
            ";
            $msg_stmt = $db->prepare($messages_query);
            $msg_stmt->execute([trim($conversation['phone_number'])]);
            $conversations[$index]['messages'] = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($conversations[$index]['messages'])) {
                $conversations[$index]['last_message'] = end($conversations[$index]['messages']);
            }
        }
    }

    $stats = ['total' => 0, 'unread' => 0, 'unique_contacts' => 0, 'today' => 0];

    if ($table_exists) {
        $stats_query = "SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN is_read = 0 AND is_from_customer = 1 THEN 1 ELSE 0 END) as unread,
            COUNT(DISTINCT phone_number) as unique_contacts,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
            FROM whatsapp_messages";
        $stats_stmt = $db->prepare($stats_query);
        $stats_stmt->execute();
        $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    }

    return [
        'success' => true,
        'conversations' => $conversations,
        'stats' => $stats,
        'count' => count($conversations)
    ];
}

// Función para formatear bytes
function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

// Función para renderizar mensajes multimedia
// Función para renderizar mensajes multimedia - VERSIÓN CORREGIDA
function renderMediaMessage($message) {
    $html = '';
    
    if ($message['message_type'] === 'text') {
        $html = nl2br(htmlspecialchars($message['content']));
    } else {
        // Es multimedia
        $media_base = '../uploads/whatsapp/';
        
        // Determinar la carpeta según el tipo
        switch ($message['message_type']) {
            case 'image':
                $media_folder = 'images/';
                break;
            case 'document':
                $media_folder = 'documents/';
                break;
            case 'audio':
                $media_folder = 'audio/';
                break;
            case 'video':
                $media_folder = 'video/';
                break;
            default:
                $media_folder = 'documents/';
        }
        
        $full_media_path = $media_base . $media_folder . ($message['media_filename'] ?? '');
        $media_exists = !empty($message['media_filename']) && file_exists($full_media_path);
        
        // Para mostrar en el navegador, necesitamos la ruta relativa desde la carpeta admin
        $web_media_path = '../uploads/whatsapp/' . $media_folder . ($message['media_filename'] ?? '');
        
        switch ($message['message_type']) {
            case 'image':
                if ($media_exists) {
                    $html = '<div class="media-message image-message">
                        <img src="' . htmlspecialchars($web_media_path) . '" alt="Imagen" class="img-fluid rounded message-image" onclick="showImageModal(\'' . htmlspecialchars($web_media_path) . '\')" style="max-width: 200px; cursor: pointer;">';
                    if (!empty($message['media_caption'])) {
                        $html .= '<div class="media-caption mt-2 text-muted">' . nl2br(htmlspecialchars($message['media_caption'])) . '</div>';
                    }
                    $html .= '</div>';
                } else {
                    $html = '<div class="media-message missing-media alert alert-warning">
                        <i class="fas fa-image me-2"></i>[Imagen no disponible]';
                    if (!empty($message['media_caption'])) {
                        $html .= '<div class="media-caption mt-2">' . nl2br(htmlspecialchars($message['media_caption'])) . '</div>';
                    }
                    $html .= '</div>';
                }
                break;
                
            case 'document':
                if ($media_exists) {
                    $file_size = $message['media_size'] ? formatBytes($message['media_size']) : '';
                    $display_filename = $message['media_filename'] ?? 'Documento';
                    
                    $html = '<div class="media-message document-message alert alert-info">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-file-alt me-2 text-primary"></i>
                            <div class="flex-grow-1">
                                <a href="' . htmlspecialchars($web_media_path) . '" target="_blank" class="text-decoration-none fw-bold">
                                    ' . htmlspecialchars($display_filename) . '
                                </a>';
                    if ($file_size) {
                        $html .= '<small class="text-muted d-block">' . $file_size . '</small>';
                    }
                    $html .= '</div>
                            <a href="' . htmlspecialchars($web_media_path) . '" download class="btn btn-sm btn-outline-primary ms-2">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>';
                    if (!empty($message['media_caption'])) {
                        $html .= '<div class="media-caption mt-2">' . nl2br(htmlspecialchars($message['media_caption'])) . '</div>';
                    }
                    $html .= '</div>';
                } else {
                    $html = '<div class="media-message missing-media alert alert-warning">
                        <i class="fas fa-file me-2"></i>[Documento: ' . htmlspecialchars($message['media_filename'] ?? 'archivo') . '] (no disponible)
                    </div>';
                }
                break;
                
            case 'audio':
                if ($media_exists) {
                    $html = '<div class="media-message audio-message alert alert-success">
                        <div class="d-flex align-items-center">
                            <i class="fas fa-play-circle me-2 text-success"></i>
                            <audio controls class="flex-grow-1" style="max-width: 200px;">
                                <source src="' . htmlspecialchars($web_media_path) . '" type="' . htmlspecialchars($message['media_mime_type'] ?? 'audio/ogg') . '">
                                Tu navegador no soporta audio.
                            </audio>
                        </div>
                    </div>';
                } else {
                    $html = '<div class="media-message missing-media alert alert-warning">
                        <i class="fas fa-microphone me-2"></i>[Audio no disponible]
                    </div>';
                }
                break;
                
            case 'video':
                if ($media_exists) {
                    $html = '<div class="media-message video-message">
                        <video controls class="img-fluid rounded" style="max-width: 250px;">
                            <source src="' . htmlspecialchars($web_media_path) . '" type="' . htmlspecialchars($message['media_mime_type'] ?? 'video/mp4') . '">
                            Tu navegador no soporta video.
                        </video>';
                    if (!empty($message['media_caption'])) {
                        $html .= '<div class="media-caption mt-2">' . nl2br(htmlspecialchars($message['media_caption'])) . '</div>';
                    }
                    $html .= '</div>';
                } else {
                    $html = '<div class="media-message missing-media alert alert-warning">
                        <i class="fas fa-video me-2"></i>[Video no disponible]';
                    if (!empty($message['media_caption'])) {
                        $html .= '<div class="media-caption mt-2">' . nl2br(htmlspecialchars($message['media_caption'])) . '</div>';
                    }
                    $html .= '</div>';
                }
                break;
                
            default:
                $html = nl2br(htmlspecialchars($message['content']));
        }
    }
    
    return $html;
}

// Obtener datos iniciales
$initial_data = getConversationsData($db, $_GET);
$conversations = $initial_data['conversations'];
$stats = $initial_data['stats'];

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

$phone_filter = $_GET['phone'] ?? '';
$date_filter = $_GET['date'] ?? '';
$unread_only = isset($_GET['unread_only']) ? 1 : 0;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mensajes WhatsApp - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Tema dinámico -->
<?php if (file_exists('../assets/css/generate-theme.php')): ?>
    <link rel="stylesheet" href="../assets/css/generate-theme.php?v=<?php echo time(); ?>">
<?php endif; ?>

<?php
// Incluir sistema de temas
$theme_file = '../config/theme.php';
if (file_exists($theme_file)) {
    require_once $theme_file;
    $theme_manager = new ThemeManager($db);
    $current_theme = $theme_manager->getThemeSettings();
} else {
    $current_theme = array(
        'primary_color' => '#667eea',
        'secondary_color' => '#764ba2',
        'accent_color' => '#ff6b6b',
        'success_color' => '#28a745',
        'warning_color' => '#ffc107',
        'danger_color' => '#dc3545',
        'info_color' => '#17a2b8'
    );
}
?>
    
    <style>
/* Extensiones específicas del dashboard */
:root {
    --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --dashboard-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
    --sidebar-mobile-width: 100%;
}

/* Mobile Top Bar */
.mobile-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: var(--primary-gradient);
    color: var(--text-white) !important;
    padding: 1rem;
    display: none;
}

.mobile-topbar h5 {
    margin: 0;
    font-size: 1.1rem;
    color: var(--text-white) !important;
}

.menu-toggle {
    background: none;
    border: none;
    color: var(--text-white) !important;
    font-size: 1.2rem;
    padding: 0.5rem;
    border-radius: var(--border-radius-base);
    transition: var(--transition-base);
}

.menu-toggle:hover {
    background: rgba(255, 255, 255, 0.1);
}

/* Sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--dashboard-sidebar-width);
    height: 100vh;
    background: var(--primary-gradient);
    color: var(--text-white) !important;
    z-index: 1030;
    transition: transform var(--transition-base);
    overflow-y: auto;
    padding: 1.5rem;
}

.sidebar-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1020;
    display: none;
    opacity: 0;
    transition: opacity var(--transition-base);
}

.sidebar-backdrop.show {
    display: block;
    opacity: 1;
}

.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8) !important;
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius-base);
    margin-bottom: 0.25rem;
    transition: var(--transition-base);
    display: flex;
    align-items: center;
    text-decoration: none;
}

.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-white) !important;
}

.sidebar .nav-link .badge {
    margin-left: auto;
}

.sidebar-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: var(--text-white) !important;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}

/* Main content */
.main-content {
    margin-left: var(--dashboard-sidebar-width);
    padding: 2rem;
    min-height: 100vh;
    transition: margin-left var(--transition-base);
    background: #f8f9fa !important;
    color: #212529 !important;
}

.card {
    background: #ffffff !important;
    color: #212529 !important;
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
}

.card-header {
    background: #f8f9fa !important;
    color: #212529 !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    border-radius: var(--border-radius-large) var(--border-radius-large) 0 0 !important;
    padding: 1rem 1.5rem;
}

.card-body {
    background: #ffffff !important;
    color: #212529 !important;
    padding: 1.5rem;
}

.form-control {
    background: #fff !important;
    color: #212529 !important;
}

.form-label {
    font-weight: 500;
    color: #212529 !important;
}

/* Text colors forzados */
.text-muted {
    color: #6c757d !important;
}

h1, h2, h3, h4, h5, h6 {
    color: #212529 !important;
}

p {
    color: #212529 !important;
}

/* Responsive */
@media (max-width: 991.98px) {
    .mobile-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar {
        transform: translateX(-100%);
        width: var(--sidebar-mobile-width);
        max-width: 350px;
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .sidebar-close {
        display: flex;
    }

    .main-content {
        margin-left: 0;
        padding: 1rem;
        padding-top: 5rem;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 0.5rem;
        padding-top: 4.5rem;
    }
    
    .stat-box {
        padding: 0.75rem;
    }
    
    .conversation-card {
        margin-bottom: 0.75rem;
    }
}
        .conversation-card {
            border-left: 4px solid #dee2e6;
            margin-bottom: 0.5rem;
            transition: all 0.3s ease;
            border-radius: 8px;
            background: white;
            overflow: hidden;
        }
        
        .conversation-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .conversation-new {
            border-left-color: #28a745 !important;
            background: linear-gradient(135deg, #f8fff9 0%, #e8fced 100%) !important;
        }
        
        .conversation-new .conversation-header {
            background: linear-gradient(135deg, #e8fced 0%, #d1f2d9 100%) !important;
        }
        
        .conversation-read {
            border-left-color: #6c757d;
            background: #f8f9fa;
        }
        
        .conversation-read .conversation-header {
            background: #e9ecef;
        }
        
        .conversation-header {
            padding: 1rem;
            cursor: pointer;
            transition: background-color 0.2s ease;
            border-bottom: none;
        }
        
        .conversation-header:hover {
            opacity: 0.9;
        }
        
        .conversation-expanded .conversation-header {
            border-bottom: 1px solid #dee2e6;
        }
        
        .conversation-content {
            display: none;
            border-top: 1px solid #dee2e6;
        }
        
        .conversation-expanded .conversation-content {
            display: block;
        }
        
        .conversation-toggle {
            transition: transform 0.3s ease;
        }
        
        .conversation-expanded .conversation-toggle {
            transform: rotate(180deg);
        }
        
        .phone-number {
            font-family: monospace;
            background: #e9ecef;
            padding: 4px 8px;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .message-bubble {
            max-width: 70%;
            margin: 0.5rem 0;
            padding: 0.75rem 1rem;
            border-radius: 18px;
            word-wrap: break-word;
        }
        
        .message-incoming {
            background: #e5e5ea;
            color: #000;
            float: left;
            clear: both;
            border-bottom-left-radius: 4px;
            margin-right: 30%;
        }
        
        .message-outgoing {
            background: #007aff;
            color: white;
            float: right;
            clear: both;
            border-bottom-right-radius: 4px;
            margin-left: 30%;
        }
        
        .message-time {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.25rem;
        }
        
        .message-incoming .message-time {
            text-align: left;
        }
        
        .message-outgoing .message-time {
            text-align: right;
        }
        
        .conversation-actions {
            padding: 1rem;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
            border-radius: 0 0 8px 8px;
        }
        
        .unread-badge {
            background: #dc3545;
            color: white;
            border-radius: 12px;
            padding: 2px 8px;
            font-size: 0.75rem;
            min-width: 20px;
            text-align: center;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .conversation-preview {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 0.25rem;
        }
        
        .conversation-new .conversation-preview {
            color: #495057;
            font-weight: 500;
        }
        
        .stat-box {
            text-align: center;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .reply-form, .media-form {
            display: none;
            margin-top: 1rem;
            padding: 1rem;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        
        .messages-container {
            max-height: 400px;
            overflow-y: auto;
            padding: 1rem;
            background: #f0f0f0;
        }
        
        .conversation-new .messages-container {
            background: linear-gradient(135deg, #f8fff9 0%, #f0f8f0 100%);
        }
        
        .conversation-read .messages-container {
            background: #f5f5f5;
        }
        
        .media-message {
            margin: 0.5rem 0;
        }
        
        .message-image {
            cursor: pointer;
            transition: transform 0.2s ease;
        }
        
        .message-image:hover {
            transform: scale(1.05);
        }
        
        .media-caption {
            font-size: 0.9em;
            color: #666;
            font-style: italic;
        }
        
        .media-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
            margin-top: 0.5rem;
        }
        
        .loading-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            z-index: 9999;
            display: none;
        }
        
        .loading-indicator.show {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Loading indicator -->
    <div id="loadingIndicator" class="loading-indicator">
        <i class="fas fa-spinner fa-spin me-2"></i>Actualizando...
    </div>

    <!-- Modal para vista previa de imágenes -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vista previa de imagen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" alt="Imagen" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2><i class="fab fa-whatsapp text-success me-2"></i>Conversaciones WhatsApp</h2>
                    <p class="text-muted">
                        Conversaciones con soporte multimedia
                        <small class="badge bg-info" id="conversationCount"><?php echo count($conversations); ?> conversaciones encontradas</small>
                    </p>
                </div>
                <div>
                    <a href="whatsapp-settings.php" class="btn btn-outline-success me-2">
                        <i class="fas fa-cog me-1"></i>Configuración
                    </a>
                    <button class="btn btn-primary" onclick="refreshConversations()">
                        <i class="fas fa-sync me-1"></i>Actualizar
                    </button>
                </div>
            </div>

            <!-- Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4 class="text-primary" id="statTotal"><?php echo $stats['total']; ?></h4>
                        <small>Total Mensajes</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4 class="text-warning" id="statUnread"><?php echo $stats['unread']; ?></h4>
                        <small>No Leídos</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4 class="text-info" id="statContacts"><?php echo $stats['unique_contacts']; ?></h4>
                        <small>Conversaciones</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-box">
                        <h4 class="text-success" id="statToday"><?php echo $stats['today']; ?></h4>
                        <small>Hoy</small>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Número de Teléfono</label>
                            <input type="text" class="form-control" id="phoneFilter"
                                   value="<?php echo htmlspecialchars($phone_filter); ?>" 
                                   placeholder="Ej: 549348259994">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha</label>
                            <input type="date" class="form-control" id="dateFilter"
                                   value="<?php echo htmlspecialchars($date_filter); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Filtros</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="unreadFilter"
                                       <?php echo $unread_only ? 'checked' : ''; ?>>
                                <label class="form-check-label">Solo no leídos</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                    <i class="fas fa-search me-1"></i>Filtrar
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearFilters()">
                                    Limpiar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Conversaciones -->
            <div id="conversationsContainer">
                <?php if (empty($conversations)): ?>
                    <div class="text-center py-5">
                        <i class="fab fa-whatsapp fa-3x text-muted mb-3"></i>
                        <h5>No hay conversaciones</h5>
                        <p class="text-muted">
                            <?php if ($phone_filter || $date_filter || $unread_only): ?>
                                No se encontraron conversaciones con los filtros seleccionados.
                            <?php else: ?>
                                Aún no has recibido mensajes de WhatsApp.
                            <?php endif; ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv_index => $conversation): 
                        $conversation_id = 'conv-' . $conv_index;
                        $conversation_class = $conversation['unread_count'] > 0 ? 'conversation-new' : 'conversation-read';
                        $status_icon = $conversation['unread_count'] > 0 ? 
                            '<i class="fas fa-circle text-success me-1" style="font-size: 0.6rem;"></i>' : 
                            '<i class="fas fa-check-double text-muted me-1"></i>';
                        $status_text = $conversation['unread_count'] > 0 ? 
                            'Nueva - ' . $conversation['unread_count'] . ' mensaje(s) sin leer' : 'Leída';
                    ?>
                        <div class="card conversation-card <?php echo $conversation_class; ?>" id="<?php echo $conversation_id; ?>">
                            <!-- Header -->
                            <div class="conversation-header" onclick="toggleConversation('<?php echo $conversation_id; ?>')">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <?php echo $status_icon; ?>
                                            <span class="phone-number me-2"><?php echo htmlspecialchars($conversation['phone_number']); ?></span>
                                            <small class="text-muted">(<?php echo $conversation['message_count']; ?> mensajes)</small>
                                        </div>
                                        <div class="conversation-preview">
                                            <?php 
                                            if (isset($conversation['last_message'])) {
                                                $last_msg = $conversation['last_message'];
                                                $preview = htmlspecialchars(substr($last_msg['content'], 0, 60));
                                                if (strlen($last_msg['content']) > 60) $preview .= '...';
                                                echo $preview;
                                            }
                                            ?>
                                        </div>
                                        <small class="text-muted"><?php echo $status_text; ?></small>
                                    </div>
                                    <div class="text-end me-3">
                                        <div class="text-muted small mb-1">
                                            <?php echo date('d/m/Y H:i', strtotime($conversation['last_message_at'])); ?>
                                        </div>
                                        <?php if ($conversation['unread_count'] > 0): ?>
                                            <span class="unread-badge"><?php echo $conversation['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="conversation-toggle">
                                        <i class="fas fa-chevron-down text-muted"></i>
                                    </div>
                                </div>
                            </div>

                            <!-- Contenido -->
                            <div class="conversation-content">
                                <!-- Mensajes -->
                                <div class="messages-container">
                                    <?php if (isset($conversation['messages']) && is_array($conversation['messages'])): ?>
                                        <?php foreach ($conversation['messages'] as $message): ?>
                                            <div class="message-bubble <?php echo $message['is_from_customer'] ? 'message-incoming' : 'message-outgoing'; ?>">
                                                <div class="message-content">
                                                    <?php echo renderMediaMessage($message); ?>
                                                </div>
                                                <div class="message-time">
                                                    <?php echo date('d/m H:i', strtotime($message['created_at'])); ?>
                                                    <?php if (!$message['is_read'] && $message['is_from_customer']): ?>
                                                        <span class="badge bg-warning ms-1">NUEVO</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div style="clear: both;"></div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <!-- Acciones -->
                                <div class="conversation-actions">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-success" 
                                                    onclick="toggleReplyForm('<?php echo $conversation_id; ?>')">
                                                <i class="fab fa-whatsapp me-1"></i>Responder
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="toggleMediaForm('<?php echo $conversation_id; ?>')">
                                                <i class="fas fa-paperclip me-1"></i>Archivo
                                            </button>
                                            <?php if ($conversation['unread_count'] > 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        onclick="markConversationRead('<?php echo htmlspecialchars($conversation['phone_number']); ?>')">
                                                    <i class="fas fa-check me-1"></i>Marcar como leída
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-sm btn-outline-info" 
                                                    onclick="openWhatsApp('<?php echo $conversation['phone_number']; ?>')">
                                                <i class="fas fa-external-link-alt me-1"></i>Abrir WhatsApp
                                            </button>
                                        </div>
                                        
                                        <small class="text-muted">
                                            Primera conversación: <?php echo date('d/m/Y', strtotime($conversation['first_message_at'])); ?>
                                        </small>
                                    </div>

                                    <!-- Formulario de respuesta de texto -->
                                    <div class="reply-form" id="reply-form-<?php echo $conversation_id; ?>">
                                        <div class="input-group">
                                            <input type="text" class="form-control" 
                                                   placeholder="Escribe tu respuesta..." 
                                                   id="reply-input-<?php echo $conversation_id; ?>"
                                                   onkeypress="if(event.key==='Enter') sendReply('<?php echo htmlspecialchars($conversation['phone_number']); ?>', '<?php echo $conversation_id; ?>')">
                                            <button class="btn btn-success" type="button" 
                                                    onclick="sendReply('<?php echo htmlspecialchars($conversation['phone_number']); ?>', '<?php echo $conversation_id; ?>')">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Formulario para envío de multimedia -->
                                    <div class="media-form" id="media-form-<?php echo $conversation_id; ?>">
                                        <form id="media-upload-form-<?php echo $conversation_id; ?>" enctype="multipart/form-data">
                                            <input type="hidden" name="phone_number" value="<?php echo htmlspecialchars($conversation['phone_number']); ?>">
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <input type="file" class="form-control" name="media_file" 
                                                           accept="image/*,audio/*,.pdf,.doc,.docx,.txt"
                                                           onchange="previewFile(this, '<?php echo $conversation_id; ?>')">
                                                    <small class="text-muted">Imagen, Audio, Documento (máx 5MB img, 16MB audio, 100MB doc)</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control" name="caption" 
                                                           placeholder="Descripción (opcional)">
                                                </div>
                                                <div class="col-md-2">
                                                    <button type="button" class="btn btn-success w-100" 
                                                            onclick="sendMediaFile('<?php echo $conversation_id; ?>')">
                                                        <i class="fas fa-paper-plane"></i>
                                                    </button>
                                                </div>
                                            </div>
                                            <div id="media-preview-<?php echo $conversation_id; ?>" class="mt-2"></div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables globales
        let refreshInterval;
        let expandedConversations = new Set();

        // Función para mostrar modal de imagen
        function showImageModal(src) {
            document.getElementById('modalImage').src = src;
            new bootstrap.Modal(document.getElementById('imageModal')).show();
        }

        // Función para previsualizar archivo
        function previewFile(input, conversationId) {
            const file = input.files[0];
            const preview = document.getElementById(`media-preview-${conversationId}`);
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (file.type.startsWith('image/')) {
                        preview.innerHTML = `<img src="${e.target.result}" class="media-preview" alt="Preview">`;
                    } else {
                        preview.innerHTML = `<div class="text-muted"><i class="fas fa-file me-2"></i>${file.name}</div>`;
                    }
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        }

        // Función para enviar archivo multimedia
        function sendMediaFile(conversationId) {
            const form = document.getElementById(`media-upload-form-${conversationId}`);
            const formData = new FormData(form);
            
            const button = event.target;
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('whatsapp-messages.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage(data.message || 'Archivo enviado correctamente');
                    
                    // Limpiar formulario
                    form.reset();
                    document.getElementById(`media-preview-${conversationId}`).innerHTML = '';
                    document.getElementById(`media-form-${conversationId}`).style.display = 'none';
                    
                    // Refrescar conversaciones
                    setTimeout(() => {
                        refreshConversations(false);
                    }, 1000);
                } else {
                    alert('Error al enviar archivo: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión: ' + error);
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalContent;
            });
        }

        // Función para alternar formulario de multimedia
        function toggleMediaForm(conversationId) {
            const formId = 'media-form-' + conversationId;
            const form = document.getElementById(formId);
            
            if (!form) {
                console.error('Media form not found:', formId);
                return;
            }
            
            if (form.style.display === 'block') {
                form.style.display = 'none';
            } else {
                // Ocultar otros formularios abiertos
                document.querySelectorAll('.reply-form').forEach(f => f.style.display = 'none');
                document.querySelectorAll('.media-form').forEach(f => f.style.display = 'none');
                form.style.display = 'block';
            }
        }

        // Función para alternar conversaciones
        function toggleConversation(conversationId) {
            const conversation = document.getElementById(conversationId);
            const allConversations = document.querySelectorAll('.conversation-card');
            
            if (expandedConversations.has(conversationId)) {
                conversation.classList.remove('conversation-expanded');
                expandedConversations.delete(conversationId);
                return;
            }
            
            // Cerrar todas las conversaciones
            expandedConversations.clear();
            allConversations.forEach(conv => {
                conv.classList.remove('conversation-expanded');
            });
            
            // Abrir la conversación clickeada
            conversation.classList.add('conversation-expanded');
            expandedConversations.add(conversationId);
            
            // Auto-scroll a mensajes más recientes
            setTimeout(() => {
                const messagesContainer = conversation.querySelector('.messages-container');
                if (messagesContainer) {
                    messagesContainer.scrollTop = messagesContainer.scrollHeight;
                }
            }, 300);
        }

        function toggleReplyForm(conversationId) {
            const formId = 'reply-form-' + conversationId;
            const form = document.getElementById(formId);
            const input = document.getElementById('reply-input-' + conversationId);
            
            if (!form) {
                console.error('Form not found:', formId);
                return;
            }
            
            if (form.style.display === 'block') {
                form.style.display = 'none';
            } else {
                // Ocultar otros formularios abiertos
                document.querySelectorAll('.reply-form').forEach(f => f.style.display = 'none');
                document.querySelectorAll('.media-form').forEach(f => f.style.display = 'none');
                form.style.display = 'block';
                if (input) input.focus();
            }
        }

        function sendReply(phoneNumber, conversationId) {
            const input = document.getElementById(`reply-input-${conversationId}`);
            const message = input.value.trim();
            
            if (!message) {
                alert('Por favor escribe un mensaje');
                return;
            }

            const button = event.target;
            const originalContent = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            fetch('whatsapp-messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'send_reply',
                    phone_number: phoneNumber,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    const form = document.getElementById(`reply-form-${conversationId}`);
                    if (form) {
                        form.style.display = 'none';
                    }
                    
                    showSuccessMessage(`Mensaje enviado a ${phoneNumber}`);
                    
                    setTimeout(() => {
                        refreshConversations(false);
                    }, 1000);
                } else {
                    alert('Error al enviar mensaje: ' + (data.error || 'Error desconocido'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión: ' + error);
            })
            .finally(() => {
                button.disabled = false;
                button.innerHTML = originalContent;
            });
        }

        function markConversationRead(phoneNumber) {
            fetch('whatsapp-messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'mark_conversation_read',
                    phone_number: phoneNumber
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showSuccessMessage('Conversación marcada como leída');
                    
                    setTimeout(() => {
                        refreshConversations(false);
                    }, 500);
                } else {
                    alert('Error al marcar como leída');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error de conexión');
            });
        }

        function openWhatsApp(phoneNumber) {
            const url = `https://wa.me/${phoneNumber}`;
            window.open(url, '_blank');
        }

        // Función para refrescar conversaciones
        function refreshConversations(showLoader = true) {
            if (showLoader) {
                document.getElementById('loadingIndicator').classList.add('show');
            }

            const filters = getActiveFilters();

            fetch('whatsapp-messages.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'refresh_conversations',
                    ...filters
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('conversationCount').textContent = 
                        `${data.count} conversaciones encontradas`;

                    updateStats(data.stats);
                    location.reload(); // Recargar para mostrar el contenido actualizado
                } else {
                    console.error('Error refreshing conversations:', data.error);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            })
            .finally(() => {
                if (showLoader) {
                    document.getElementById('loadingIndicator').classList.remove('show');
                }
            });
        }

        function updateStats(stats) {
            document.getElementById('statTotal').textContent = stats.total;
            document.getElementById('statUnread').textContent = stats.unread;
            document.getElementById('statContacts').textContent = stats.unique_contacts;
            document.getElementById('statToday').textContent = stats.today;
        }

        function getActiveFilters() {
            return {
                phone: document.getElementById('phoneFilter').value,
                date: document.getElementById('dateFilter').value,
                unread_only: document.getElementById('unreadFilter').checked
            };
        }

        function applyFilters() {
            refreshConversations();
        }

        function clearFilters() {
            document.getElementById('phoneFilter').value = '';
            document.getElementById('dateFilter').value = '';
            document.getElementById('unreadFilter').checked = false;
            refreshConversations();
        }

        function showSuccessMessage(message) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
            alert.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);
            
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.remove();
                }
            }, 3000);
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Inicializar menú móvil
            initializeMobileMenu();
            
            // Expandir automáticamente la primera conversación nueva
            const firstNewConversation = document.querySelector('.conversation-new');
            if (firstNewConversation) {
                const convId = firstNewConversation.id;
                firstNewConversation.classList.add('conversation-expanded');
                expandedConversations.add(convId);
                
                setTimeout(() => {
                    const messagesContainer = firstNewConversation.querySelector('.messages-container');
                    if (messagesContainer) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }, 300);
            }

            // Auto-refresh cada 30 segundos
            refreshInterval = setInterval(() => {
                if (expandedConversations.size === 0) {
                    refreshConversations(false);
                }
            }, 30000);

            // Event listeners para filtros
            document.getElementById('phoneFilter').addEventListener('input', debounce(applyFilters, 500));
            document.getElementById('dateFilter').addEventListener('change', applyFilters);
            document.getElementById('unreadFilter').addEventListener('change', applyFilters);
        });

        // Funcionalidad del menú móvil y sidebar
        function initializeMobileMenu() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            const sidebarClose = document.getElementById('sidebarClose');

            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    sidebar.classList.toggle('show');
                    sidebarBackdrop.classList.toggle('show');
                    document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
                });
            }

            if (sidebarClose) {
                sidebarClose.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeSidebar();
                });
            }

            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', function(e) {
                    e.preventDefault();
                    closeSidebar();
                });
            }

            document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
                link.addEventListener('click', function() {
                    if (window.innerWidth < 992) {
                        setTimeout(closeSidebar, 100);
                    }
                });
            });

            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    closeSidebar();
                }
            });
        }

        function closeSidebar() {
            const sidebar = document.getElementById('sidebar');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            
            if (sidebar) sidebar.classList.remove('show');
            if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
            document.body.style.overflow = '';
        }

        function debounce(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        }

        // Limpiar intervalos al salir
        window.addEventListener('beforeunload', function() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
        });
    </script>
</body>
</html>