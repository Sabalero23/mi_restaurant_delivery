<?php
// admin/whatsapp-messages.php - Versión limpia sin duplicados
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
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action'])) {
        switch ($input['action']) {
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

// Parámetros de filtros
$phone_filter = $_GET['phone'] ?? '';
$date_filter = $_GET['date'] ?? '';
$unread_only = isset($_GET['unread_only']) ? 1 : 0;

// Verificar si la tabla existe
$table_exists = false;
try {
    $check_table = $db->query("SHOW TABLES LIKE 'whatsapp_messages'");
    $table_exists = $check_table->rowCount() > 0;
} catch (Exception $e) {
    $table_exists = false;
}

$conversations = [];

if ($table_exists) {
    // Construir filtros
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

    // CONSULTA MEJORADA - Normaliza números y agrupa exactamente
    // Primero limpiamos los números en la base de datos para asegurar consistencia
    $normalize_query = "UPDATE whatsapp_messages SET phone_number = TRIM(REPLACE(REPLACE(REPLACE(phone_number, ' ', ''), '-', ''), '+', ''))";
    $db->exec($normalize_query);
    
    $conversations_query = "
        SELECT 
            TRIM(phone_number) as phone_number,
            COUNT(*) as message_count,
            SUM(CASE WHEN is_read = 0 AND is_from_customer = 1 THEN 1 ELSE 0 END) as unread_count,
            MAX(created_at) as last_message_at,
            MIN(created_at) as first_message_at,
            -- Conversación nueva si tiene mensajes no leídos del cliente
            CASE WHEN SUM(CASE WHEN is_read = 0 AND is_from_customer = 1 THEN 1 ELSE 0 END) > 0 THEN 1 ELSE 0 END as has_new_messages
        FROM whatsapp_messages 
        $where_sql
        GROUP BY TRIM(phone_number)
        ORDER BY has_new_messages DESC, last_message_at DESC
    ";

    $stmt = $db->prepare($conversations_query);
    $stmt->execute($params);
    $all_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Aplicar filtro de no leídos si está activado
    if ($unread_only) {
        $all_conversations = array_filter($all_conversations, function($conv) {
            return $conv['unread_count'] > 0;
        });
    }

    // IMPORTANTE: Usar los datos tal como vienen de la DB
    $conversations = $all_conversations;

    // Obtener mensajes para cada conversación - ORDENADOS CRONOLÓGICAMENTE
    foreach ($conversations as $index => $conversation) {
        $messages_query = "
            SELECT * FROM whatsapp_messages 
            WHERE TRIM(phone_number) = ? 
            ORDER BY created_at ASC, id ASC
        ";
        $msg_stmt = $db->prepare($messages_query);
        $msg_stmt->execute([trim($conversation['phone_number'])]);
        $conversations[$index]['messages'] = $msg_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: mostrar cuántos mensajes tiene cada conversación
        error_log("WhatsApp: Conversation " . $conversation['phone_number'] . " has " . count($conversations[$index]['messages']) . " messages");
        
        // Último mensaje para preview (el más reciente)
        if (!empty($conversations[$index]['messages'])) {
            $conversations[$index]['last_message'] = end($conversations[$index]['messages']);
            error_log("WhatsApp: Last message for " . $conversation['phone_number'] . " is: " . substr($conversations[$index]['last_message']['content'], 0, 30));
        }
    }
}

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Estadísticas simples
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

// DEBUG: Contar conversaciones reales
$debug_count = count($conversations);
error_log("WhatsApp Debug: Found $debug_count conversations in PHP array");
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
    $database = new Database();
    $db = $database->getConnection();
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

/* Statistics cards usando variables del tema */
.stat-card {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    padding: 1.5rem;
    box-shadow: var(--shadow-base);
    transition: transform var(--transition-base);
    height: 100%;
}


.stat-card:hover {
    transform: translateY(-5px);
}

.form-control {
    background: #fff !important;
    color: #212529 !important;
}


.form-label {
    font-weight: 500;
    color: #212529 !important;
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--border-radius-large);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--text-white) !important;
    flex-shrink: 0;
}

.bg-primary-gradient { 
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)) !important; 
}
.bg-success-gradient { 
    background: linear-gradient(45deg, var(--success-color), #a8e6cf) !important; 
}
.bg-warning-gradient { 
    background: linear-gradient(45deg, var(--warning-color), var(--accent-color)) !important; 
}
.bg-info-gradient { 
    background: linear-gradient(45deg, var(--info-color), #00f2fe) !important; 
}
.bg-online-gradient { 
    background: linear-gradient(45deg, var(--accent-color), var(--warning-color)) !important; 
}

.page-header {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-base);
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
        
        /* CONVERSACIONES NUEVAS/NO LEÍDAS - Color llamativo */
        .conversation-new {
            border-left-color: #28a745 !important;
            background: linear-gradient(135deg, #f8fff9 0%, #e8fced 100%) !important;
        }
        
        .conversation-new .conversation-header {
            background: linear-gradient(135deg, #e8fced 0%, #d1f2d9 100%) !important;
        }
        
        /* CONVERSACIONES LEÍDAS - Color neutro */
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
        
        .conversation-header {
            background: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            border-radius: 8px 8px 0 0;
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
        
        /* Preview más destacado en conversaciones nuevas */
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
        
        .reply-form {
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
        
        /* Diferentes fondos para el container de mensajes */
        .conversation-new .messages-container {
            background: linear-gradient(135deg, #f8fff9 0%, #f0f8f0 100%);
        }
        
        .conversation-read .messages-container {
            background: #f5f5f5;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
    
    <!-- Ya está dentro de .main-content, solo necesita el contenido -->
        <div class="row">
            <div class="col-12">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fab fa-whatsapp text-success me-2"></i>Conversaciones WhatsApp</h2>
                        <p class="text-muted">
                            Conversaciones agrupadas por contacto 
                            <small class="badge bg-info"><?php echo count($conversations); ?> conversaciones encontradas</small>
                        </p>
                    </div>
                    <div>
                        <a href="whatsapp-settings.php" class="btn btn-outline-success me-2">
                            <i class="fas fa-cog me-1"></i>Configuración
                        </a>
                        <button class="btn btn-primary" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Actualizar
                        </button>
                    </div>
                </div>

                <!-- Statistics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4 class="text-primary"><?php echo $stats['total']; ?></h4>
                            <small>Total Mensajes</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4 class="text-warning"><?php echo $stats['unread']; ?></h4>
                            <small>No Leídos</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4 class="text-info"><?php echo $stats['unique_contacts']; ?></h4>
                            <small>Conversaciones</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-box">
                            <h4 class="text-success"><?php echo $stats['today']; ?></h4>
                            <small>Hoy</small>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">Número de Teléfono</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo htmlspecialchars($phone_filter); ?>" 
                                       placeholder="Ej: 549348259994">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="date" 
                                       value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Filtros</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="unread_only" 
                                           <?php echo $unread_only ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Solo no leídos</label>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>Filtrar
                                    </button>
                                    <a href="whatsapp-messages.php" class="btn btn-outline-secondary">
                                        Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- DEBUG INFO -->
                <div class="alert alert-info">
                    <strong style="display: block; text-align: center; font-size: 2rem;">CONVERSACIONES</strong>
                </div>

                <!-- Conversations -->
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
                    <?php 
                    // DEBUG: Mostrar qué conversaciones vamos a iterar
                    error_log("WhatsApp: About to iterate through " . count($conversations) . " conversations");
                    foreach ($conversations as $conv_index => $conversation): 
                        error_log("WhatsApp: Processing conversation $conv_index - Phone: " . $conversation['phone_number'] . " - Unread: " . $conversation['unread_count']);
                        
                        // Determinar el estado de la conversación
                        $conversation_class = '';
                        $status_text = '';
                        $status_icon = '';
                        
                        if ($conversation['unread_count'] > 0) {
                            $conversation_class = 'conversation-new';
                            $status_text = 'Nueva - ' . $conversation['unread_count'] . ' mensaje(s) sin leer';
                            $status_icon = '<i class="fas fa-circle text-success me-1" style="font-size: 0.6rem;"></i>';
                        } else {
                            $conversation_class = 'conversation-read';
                            $status_text = 'Leída';
                            $status_icon = '<i class="fas fa-check-double text-muted me-1"></i>';
                        }
                        
                        $conversation_id = 'conv-' . $conv_index;
                    ?>
                        <div class="card conversation-card <?php echo $conversation_class; ?>" id="<?php echo $conversation_id; ?>">
                            <!-- Header de la conversación (siempre visible, clickeable) -->
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

                            <!-- Contenido de la conversación (inicialmente oculto) -->
                            <div class="conversation-content">
                                <!-- Mensajes -->
                                <div class="messages-container">
                                    <?php if (isset($conversation['messages']) && is_array($conversation['messages'])): ?>
                                        <?php foreach ($conversation['messages'] as $message): ?>
                                            <div class="message-bubble <?php echo $message['is_from_customer'] ? 'message-incoming' : 'message-outgoing'; ?>">
                                                <div class="message-content">
                                                    <?php echo nl2br(htmlspecialchars($message['content'])); ?>
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

                                    <!-- Formulario de respuesta -->
                                    <div class="reply-form" id="reply-form-<?php echo $conversation_id; ?>">
                                        <div class="input-group">
                                            <input type="text" class="form-control" 
                                                   placeholder="Escribe tu respuesta..." 
                                                   id="reply-input-<?php echo $conversation_id; ?>"
                                                   onkeypress="if(event.key==='Enter') sendReply('<?php echo htmlspecialchars($conversation['phone_number']); ?>')">
                                            <button class="btn btn-success" type="button" 
                                                    onclick="sendReply('<?php echo htmlspecialchars($conversation['phone_number']); ?>')">
                                                <i class="fas fa-paper-plane"></i>
                                            </button>
                                        </div>
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
        // Función para alternar conversaciones (acordeón)
        function toggleConversation(conversationId) {
            const conversation = document.getElementById(conversationId);
            const allConversations = document.querySelectorAll('.conversation-card');
            
            // Si la conversación ya está expandida, cerrarla
            if (conversation.classList.contains('conversation-expanded')) {
                conversation.classList.remove('conversation-expanded');
                return;
            }
            
            // Cerrar todas las conversaciones
            allConversations.forEach(conv => {
                conv.classList.remove('conversation-expanded');
            });
            
            // Abrir la conversación clickeada
            conversation.classList.add('conversation-expanded');
            
            // Auto-scroll a los mensajes más recientes
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
                form.style.display = 'block';
                if (input) input.focus();
            }
        }

        function sendReply(phoneNumber) {
            // Encontrar el input correspondiente a este número
            const conversationCards = document.querySelectorAll('.conversation-card');
            let targetInput = null;
            let targetForm = null;
            
            conversationCards.forEach(card => {
                const phoneElement = card.querySelector('.phone-number');
                if (phoneElement && phoneElement.textContent.trim() === phoneNumber) {
                    targetInput = card.querySelector('input[placeholder="Escribe tu respuesta..."]');
                    targetForm = card.querySelector('.reply-form');
                }
            });
            
            if (!targetInput) {
                console.error('Input not found for phone:', phoneNumber);
                alert('Error: No se encontró el campo de entrada');
                return;
            }
            
            const message = targetInput.value.trim();
            
            if (!message) {
                alert('Por favor escribe un mensaje');
                return;
            }

            // Deshabilitar botón mientras se envía
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
                    targetInput.value = '';
                    if (targetForm) {
                        targetForm.style.display = 'none';
                    }
                    
                    // Mostrar mensaje temporal de éxito
                    const successAlert = document.createElement('div');
                    successAlert.className = 'alert alert-success alert-dismissible fade show position-fixed';
                    successAlert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
                    successAlert.innerHTML = `
                        <i class="fas fa-check-circle me-2"></i>Mensaje enviado a ${phoneNumber}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(successAlert);
                    
                    // Remover alerta después de 3 segundos y recargar
                    setTimeout(() => {
                        if (successAlert.parentNode) {
                            successAlert.remove();
                        }
                        location.reload();
                    }, 3000);
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
                    // Mostrar feedback inmediato
                    const alert = document.createElement('div');
                    alert.className = 'alert alert-info alert-dismissible fade show position-fixed';
                    alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999; max-width: 300px;';
                    alert.innerHTML = `
                        <i class="fas fa-check me-2"></i>Conversación marcada como leída
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    `;
                    document.body.appendChild(alert);
                    
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
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

        // Inicialización al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            // Expandir automáticamente la primera conversación nueva (si existe)
            const firstNewConversation = document.querySelector('.conversation-new');
            if (firstNewConversation) {
                firstNewConversation.classList.add('conversation-expanded');
                
                // Auto-scroll después de expandir
                setTimeout(() => {
                    const messagesContainer = firstNewConversation.querySelector('.messages-container');
                    if (messagesContainer) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    }
                }, 300);
            }
        });

        // Auto-refresh cada 30 segundos (solo si no hay conversaciones abiertas)
        setInterval(() => {
            const expandedConversation = document.querySelector('.conversation-expanded');
            if (!expandedConversation) {
                location.reload();
            }
        }, 30000);
        
        // Funcionalidad del menú móvil y sidebar
document.addEventListener('DOMContentLoaded', function() {
    initializeMobileMenu();
    
    // Expandir automáticamente la primera conversación nueva (si existe)
    const firstNewConversation = document.querySelector('.conversation-new');
    if (firstNewConversation) {
        firstNewConversation.classList.add('conversation-expanded');
        
        // Auto-scroll después de expandir
        setTimeout(() => {
            const messagesContainer = firstNewConversation.querySelector('.messages-container');
            if (messagesContainer) {
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }
        }, 300);
    }
});

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
    </script>
    </script>
</body>
</html>