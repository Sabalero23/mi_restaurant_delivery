<?php
// admin/whatsapp-settings.php - Configuración de WhatsApp Business API con Webhook Token
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../config/whatsapp_api.php';
require_once '../models/Order.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('all'); // Solo administradores

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Procesar formulario
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'save_settings':
            try {
                $access_token = trim($_POST['whatsapp_access_token']);
                $phone_number_id = trim($_POST['whatsapp_phone_number_id']);
                $webhook_token = trim($_POST['whatsapp_webhook_token']);
                $enabled = $_POST['whatsapp_enabled'] ?? '0';
                $fallback_enabled = $_POST['whatsapp_fallback_enabled'] ?? '1';
                $auto_responses = $_POST['whatsapp_auto_responses'] ?? '0';
                
                // Actualizar configuraciones
                $settings_to_update = [
                    'whatsapp_access_token' => $access_token,
                    'whatsapp_phone_number_id' => $phone_number_id,
                    'whatsapp_webhook_token' => $webhook_token,
                    'whatsapp_enabled' => $enabled,
                    'whatsapp_fallback_enabled' => $fallback_enabled,
                    'whatsapp_auto_responses' => $auto_responses
                ];
                
                foreach ($settings_to_update as $key => $value) {
                    $query = "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$key, $value]);
                }
                
                $message = 'Configuración guardada exitosamente';
                
            } catch (Exception $e) {
                $error = 'Error al guardar la configuración: ' . $e->getMessage();
            }
            break;
            
        case 'test_api':
            try {
                $test_phone = trim($_POST['test_phone']);
                if (empty($test_phone)) {
                    throw new Exception('Debe ingresar un número de teléfono para la prueba');
                }
                
                $whatsapp = new WhatsAppAPI();
                if (!$whatsapp->isConfigured()) {
                    throw new Exception('La API no está configurada correctamente');
                }
                
                $test_message = "🧪 Mensaje de prueba desde " . ($settings['restaurant_name'] ?? 'Mi Restaurante') . "\n\nSi recibiste este mensaje, la API de WhatsApp está funcionando correctamente.\n\nFecha: " . date('d/m/Y H:i:s');
                
                $result = $whatsapp->sendTextMessage($test_phone, $test_message);
                
                if ($result['success']) {
                    $message = 'Mensaje de prueba enviado exitosamente. Revise el WhatsApp del número: ' . $test_phone;
                } else {
                    $error = 'Error al enviar mensaje de prueba: ' . $result['error'];
                }
                
            } catch (Exception $e) {
                $error = 'Error en la prueba: ' . $e->getMessage();
            }
            break;
            
        case 'test_webhook':
            try {
                $webhook_url = 'https://comidas.ordenes.com.ar/admin/whatsapp-webhook.php';
                $settings = getSettings();
                $webhook_token = $settings['whatsapp_webhook_token'] ?? '';
                
                if (empty($webhook_token)) {
                    throw new Exception('Debe configurar el token del webhook primero');
                }
                
                // Simular verificación de webhook
                $test_url = $webhook_url . '?hub.mode=subscribe&hub.verify_token=' . urlencode($webhook_token) . '&hub.challenge=test123';
                
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $test_url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                
                $response = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($http_code === 200 && $response === 'test123') {
                    $message = 'Webhook funcionando correctamente. La verificación fue exitosa.';
                } else {
                    $error = "Error en webhook. Código HTTP: $http_code, Respuesta: $response";
                }
                
            } catch (Exception $e) {
                $error = 'Error al probar webhook: ' . $e->getMessage();
            }
            break;
    }
}

// Obtener configuraciones actuales
$settings = getSettings();
$whatsapp = new WhatsAppAPI();
$api_status = $whatsapp->getConfigStatus();

// Verificar estado del webhook
$webhook_status = 'unknown';
$webhook_last_activity = null;

try {
    $webhook_log_file = '../admin/webhook.log';
    if (file_exists($webhook_log_file)) {
        $log_content = file_get_contents($webhook_log_file);
        $lines = explode("\n", trim($log_content));
        $last_line = end($lines);
        
        if (!empty($last_line)) {
            preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $last_line, $matches);
            if ($matches) {
                $webhook_last_activity = $matches[1];
                $last_activity_time = strtotime($webhook_last_activity);
                $current_time = time();
                
                if (($current_time - $last_activity_time) < 86400) {
                    $webhook_status = 'active';
                } else {
                    $webhook_status = 'inactive';
                }
            }
        }
    } else {
        $webhook_status = 'no_log';
    }
} catch (Exception $e) {
    $webhook_status = 'error';
}

// Obtener logs recientes
$logs_query = "SELECT * FROM whatsapp_logs ORDER BY created_at DESC LIMIT 10";
$logs_stmt = $db->prepare($logs_query);
$logs_stmt->execute();
$recent_logs = $logs_stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener estadísticas para el sidebar
$orderModel = new Order();

$stats = [];
if ($auth->hasPermission('orders')) {
    $stats['pending_orders'] = count($orderModel->getByStatus('pending'));
    $stats['preparing_orders'] = count($orderModel->getByStatus('preparing'));
    $stats['ready_orders'] = count($orderModel->getByStatus('ready'));
}

if ($auth->hasPermission('delivery')) {
    $stats['pending_deliveries'] = count($orderModel->getByStatus('ready', 'delivery'));
}

// Obtener estadísticas de pedidos online
$online_stats = [];
if ($auth->hasPermission('online_orders')) {
    $online_query = "SELECT 
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_online,
        COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_online,
        COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_online,
        COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_online
        FROM online_orders 
        WHERE DATE(created_at) = CURDATE()";
    
    $online_stmt = $db->prepare($online_query);
    $online_stmt->execute();
    $online_stats = $online_stmt->fetch();
}

$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$role = $_SESSION['role_name'];
$user_name = $_SESSION['full_name'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración WhatsApp - <?php echo $restaurant_name; ?></title>
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
        /* Variables CSS para temas */
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


        .config-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            background: #ffffff !important;
            color: #212529 !important;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
        }
        
        .log-item {
            border-left: 3px solid #dee2e6;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0 8px 8px 0;
        }
        
        .log-success { border-left-color: #28a745; background: #f8fff9; }
        .log-error { border-left-color: #dc3545; background: #fff8f8; }
        
        .webhook-info {
            background: #e7f3ff;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .webhook-url {
            font-family: monospace;
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            word-break: break-all;
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

        /* Scrollbar del sidebar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
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

            .config-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
                padding-top: 4.5rem;
            }

            .config-card {
                padding: 0.75rem;
            }

            .sidebar {
                padding: 1rem;
            }

            .sidebar .nav-link {
                padding: 0.5rem 0.75rem;
            }
        }

        /* Estilos para elementos del formulario */
        .form-control, .form-select {
            background: #ffffff !important;
            color: #212529 !important;
            border: 1px solid #dee2e6;
        }

        .form-control:focus, .form-select:focus {
            background: #ffffff !important;
            color: #212529 !important;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(var(--primary-color-rgb), 0.25);
        }

        /* Texto y elementos */
        h1, h2, h3, h4, h5, h6, p, span, div {
            color: #212529 !important;
        }

        .text-muted {
            color: #6c757d !important;
        }

        /* Page header específico */
        .page-header {
            background: #ffffff !important;
            color: #212529 !important;
            border-radius: var(--border-radius-large);
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-base);
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">
                        <i class="fab fa-whatsapp text-success me-2"></i>
                        Configuración WhatsApp Business API
                    </h2>
                    <p class="text-muted mb-0">Configure el envío automático de mensajes por WhatsApp</p>
                </div>
                <div class="d-flex align-items-center">
                    <a href="whatsapp-setup-guide.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-book me-1"></i>Ver Guía
                    </a>
                    <small class="text-muted d-none d-lg-block">
                        <i class="fas fa-clock me-1"></i>
                        <?php echo date('d/m/Y H:i'); ?>
                    </small>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Estado Actual -->
            <div class="col-lg-4">
                <div class="card config-card">
                    <div class="card-header">
                        <h5><i class="fas fa-info-circle me-2"></i>Estado de la API</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div><strong>Estado General:</strong></div>
                            <?php if ($api_status['configured']): ?>
                                <span class="status-badge bg-success text-white mt-2">
                                    <i class="fas fa-check me-1"></i>Configurada
                                </span>
                            <?php else: ?>
                                <span class="status-badge bg-danger text-white mt-2">
                                    <i class="fas fa-times me-1"></i>No Configurada
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="mb-2">
                            <strong>Token de Acceso:</strong>
                            <?php if ($api_status['has_token']): ?>
                                <i class="fas fa-check text-success ms-2"></i>
                                <small class="text-muted">Configurado</small>
                            <?php else: ?>
                                <i class="fas fa-times text-danger ms-2"></i>
                                <small class="text-muted">Falta configurar</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-2">
                            <strong>ID del Número:</strong>
                            <?php if ($api_status['has_phone_id']): ?>
                                <i class="fas fa-check text-success ms-2"></i>
                                <small class="text-muted">Configurado</small>
                            <?php else: ?>
                                <i class="fas fa-times text-danger ms-2"></i>
                                <small class="text-muted">Falta configurar</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <strong>Token Webhook:</strong>
                            <?php if (!empty($settings['whatsapp_webhook_token'])): ?>
                                <i class="fas fa-check text-success ms-2"></i>
                                <small class="text-muted">Configurado</small>
                            <?php else: ?>
                                <i class="fas fa-times text-danger ms-2"></i>
                                <small class="text-muted">Falta configurar</small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <strong>Estado Webhook:</strong>
                            <?php
                            $webhook_badge_class = 'secondary';
                            $webhook_text = 'Desconocido';
                            
                            switch ($webhook_status) {
                                case 'active':
                                    $webhook_badge_class = 'success';
                                    $webhook_text = 'Activo';
                                    break;
                                case 'inactive':
                                    $webhook_badge_class = 'warning';
                                    $webhook_text = 'Inactivo';
                                    break;
                                case 'no_log':
                                    $webhook_badge_class = 'danger';
                                    $webhook_text = 'Sin logs';
                                    break;
                                case 'error':
                                    $webhook_badge_class = 'danger';
                                    $webhook_text = 'Error';
                                    break;
                            }
                            ?>
                            <span class="badge bg-<?php echo $webhook_badge_class; ?> ms-2"><?php echo $webhook_text; ?></span>
                        </div>

                        <!-- Pruebas -->
                        <hr>
                        
                        <!-- Prueba de API -->
                        <?php if ($api_status['configured']): ?>
                            <form method="POST" class="mb-3">
                                <input type="hidden" name="action" value="test_api">
                                <div class="mb-2">
                                    <label class="form-label"><small><strong>Probar API:</strong></small></label>
                                    <input type="tel" name="test_phone" class="form-control form-control-sm" 
                                           placeholder="Ej: 543424123456" required>
                                </div>
                                <button type="submit" class="btn btn-info btn-sm w-100">
                                    <i class="fas fa-paper-plane me-1"></i>Enviar Prueba
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Prueba de Webhook -->
                        <?php if (!empty($settings['whatsapp_webhook_token'])): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="test_webhook">
                                <button type="submit" class="btn btn-outline-info btn-sm w-100">
                                    <i class="fas fa-link me-1"></i>Probar Webhook
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Configuración -->
            <div class="col-lg-8">
                <div class="card config-card">
                    <div class="card-header">
                        <h5><i class="fas fa-cog me-2"></i>Configuración de la API</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="save_settings">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <strong>Access Token</strong>
                                            <i class="fas fa-question-circle text-info ms-1" 
                                               title="Token de acceso de WhatsApp Business API"></i>
                                        </label>
                                        <input type="password" name="whatsapp_access_token" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['whatsapp_access_token'] ?? ''); ?>"
                                               placeholder="EAAxxxxxxxxx...">
                                        <small class="text-muted">Token obtenido desde Meta for Developers</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <strong>Phone Number ID</strong>
                                            <i class="fas fa-question-circle text-info ms-1" 
                                               title="ID del número de WhatsApp Business"></i>
                                        </label>
                                        <input type="text" name="whatsapp_phone_number_id" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['whatsapp_phone_number_id'] ?? ''); ?>"
                                               placeholder="123456789012345">
                                        <small class="text-muted">ID del número desde la consola de Meta</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label">
                                            <strong>Token de Verificación del Webhook</strong>
                                            <i class="fas fa-question-circle text-info ms-1" 
                                               title="Token secreto para verificar webhooks de Meta"></i>
                                        </label>
                                        <input type="text" name="whatsapp_webhook_token" class="form-control" 
                                               value="<?php echo htmlspecialchars($settings['whatsapp_webhook_token'] ?? ''); ?>"
                                               placeholder="mi-token-secreto-123">
                                        <small class="text-muted">Token secreto que debe coincidir con Meta for Developers</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="whatsapp_enabled" value="1"
                                                   <?php echo (($settings['whatsapp_enabled'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                <strong>Habilitar Envío Automático</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted">Enviar mensajes automáticamente</small>
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="whatsapp_fallback_enabled" value="1"
                                                   <?php echo (($settings['whatsapp_fallback_enabled'] ?? '1') === '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                <strong>Habilitar Fallback</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted">Usar WhatsApp Web si falla API</small>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="whatsapp_auto_responses" value="1"
                                                   <?php echo (($settings['whatsapp_auto_responses'] ?? '0') === '1') ? 'checked' : ''; ?>>
                                            <label class="form-check-label">
                                                <strong>Respuestas Automáticas</strong>
                                            </label>
                                        </div>
                                        <small class="text-muted">Responder automáticamente</small>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save me-1"></i>
                                Guardar Configuración
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Información del Webhook -->
                <div class="card config-card">
                    <div class="card-header">
                        <h6><i class="fas fa-link me-2"></i>Configuración del Webhook</h6>
                    </div>
                    <div class="card-body">
                        <div class="webhook-info">
                            <h6>Configurar en Meta for Developers:</h6>
                            
                            <div class="mb-2">
                                <strong>Callback URL:</strong>
                                <div class="webhook-url">https://comidas.ordenes.com.ar/admin/whatsapp-webhook.php</div>
                            </div>
                            
                            <div class="mb-2">
                                <strong>Verify Token:</strong>
                                <div class="webhook-url">
                                    <?php echo htmlspecialchars($settings['whatsapp_webhook_token'] ?? 'Configurar token arriba'); ?>
                                </div>
                            </div>
                            
                            <div class="mb-2">
                                <strong>Webhook Fields:</strong>
                                <ul class="mb-0">
                                    <li>messages</li>
                                    <li>messaging_postbacks</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning mt-3">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> Después de guardar el token del webhook, debe configurarlo también en Meta for Developers para que coincida exactamente.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Logs Recientes -->
        <?php if (!empty($recent_logs)): ?>
            <div class="card config-card">
                <div class="card-header">
                    <h6><i class="fas fa-history me-2"></i>Últimos Envíos</h6>
                </div>
                <div class="card-body">
                    <?php foreach ($recent_logs as $log): ?>
                        <div class="log-item log-<?php echo $log['status']; ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($log['phone_number']); ?></strong>
                                    <span class="badge bg-<?php echo $log['status'] === 'success' ? 'success' : 'danger'; ?> ms-2">
                                        <?php echo $log['status']; ?>
                                    </span>
                                    <?php if ($log['status'] === 'error' && $log['api_response']): ?>
                                        <?php $response = json_decode($log['api_response'], true); ?>
                                        <br><small class="text-danger">
                                            Error: <?php echo htmlspecialchars($response['error']['message'] ?? 'Error desconocido'); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?php echo formatDateTime($log['created_at']); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
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
</body>
</html>