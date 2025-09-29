<?php
// admin/whatsapp-answers.php - Versión limpia usando sidebar modular
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('whatsapp');

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Procesar acciones del formulario
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'add_response':
            try {
                $trigger_words = trim($_POST['trigger_words']);
                $response_message = trim($_POST['response_message']);
                $match_type = $_POST['match_type'];
                $priority = intval($_POST['priority']);
                
                if (empty($trigger_words) || empty($response_message)) {
                    throw new Exception('Las palabras clave y el mensaje de respuesta son obligatorios');
                }
                
                $query = "INSERT INTO whatsapp_auto_responses (trigger_words, response_message, match_type, priority) 
                         VALUES (?, ?, ?, ?)";
                $stmt = $db->prepare($query);
                $stmt->execute([$trigger_words, $response_message, $match_type, $priority]);
                
                $message = 'Respuesta automática agregada exitosamente';
                
            } catch (Exception $e) {
                $error = 'Error al agregar respuesta: ' . $e->getMessage();
            }
            break;
            
        case 'update_response':
            try {
                $id = intval($_POST['id']);
                $trigger_words = trim($_POST['trigger_words']);
                $response_message = trim($_POST['response_message']);
                $match_type = $_POST['match_type'];
                $priority = intval($_POST['priority']);
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                $query = "UPDATE whatsapp_auto_responses SET 
                         trigger_words = ?, response_message = ?, match_type = ?, 
                         priority = ?, is_active = ?, updated_at = NOW() 
                         WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$trigger_words, $response_message, $match_type, $priority, $is_active, $id]);
                
                $message = 'Respuesta automática actualizada exitosamente';
                
            } catch (Exception $e) {
                $error = 'Error al actualizar respuesta: ' . $e->getMessage();
            }
            break;
            
        case 'delete_response':
            try {
                $id = intval($_POST['id']);
                
                $query = "DELETE FROM whatsapp_auto_responses WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                
                $message = 'Respuesta automática eliminada exitosamente';
                
            } catch (Exception $e) {
                $error = 'Error al eliminar respuesta: ' . $e->getMessage();
            }
            break;
            
        case 'toggle_status':
            try {
                $id = intval($_POST['id']);
                
                $query = "UPDATE whatsapp_auto_responses SET 
                         is_active = NOT is_active, updated_at = NOW() 
                         WHERE id = ?";
                $stmt = $db->prepare($query);
                $stmt->execute([$id]);
                
                $message = 'Estado de respuesta actualizado';
                
            } catch (Exception $e) {
                $error = 'Error al cambiar estado: ' . $e->getMessage();
            }
            break;
    }
}

// Obtener todas las respuestas automáticas
$responses_query = "SELECT * FROM whatsapp_auto_responses ORDER BY priority DESC, created_at ASC";
$responses_stmt = $db->prepare($responses_query);
$responses_stmt->execute();
$responses = $responses_stmt->fetchAll(PDO::FETCH_ASSOC);

// Preparar datos para el sidebar
require_once 'includes/sidebar-data.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Respuestas Automáticas WhatsApp - <?php echo $restaurant_name; ?></title>
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

        .priority-badge {
            min-width: 50px;
            text-align: center;
        }
        
        .trigger-words {
            background: #f8f9fa;
            padding: 0.5rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .response-preview {
            background: #e7f3ff;
            border-left: 3px solid #0066cc;
            padding: 0.75rem;
            margin: 0.5rem 0;
            border-radius: 0 4px 4px 0;
            font-size: 0.9rem;
        }
        
        .match-type-badge {
            font-size: 0.75rem;
        }
        
        .usage-count {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .variables-help {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .variable-tag {
            background: #e9ecef;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
            font-size: 0.85rem;
            margin: 2px;
            display: inline-block;
        }
        
        .form-floating textarea {
            min-height: 100px;
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
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
                padding-top: 4.5rem;
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

        .system-header .container-fluid {
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 1rem;
            background-color: white;
        }

        .dropdown-menu.show {
            display: block;
            background: var(--primary-gradient);
        }

        .dropdown-header {
            padding: 0.75rem 1rem;
            background: var(--primary-gradient) !important;
            border-radius: 10px 10px 0 0;
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
                        <i class="fas fa-robot text-primary me-2"></i>
                        Respuestas Automáticas WhatsApp
                    </h2>
                    <p class="text-muted mb-0">Configure las respuestas automáticas para los mensajes entrantes</p>
                </div>
                <div>
                    <a href="whatsapp-messages.php" class="btn btn-outline-success me-2">
                        <i class="fas fa-comments me-1"></i>Ver Mensajes
                    </a>
                    <a href="whatsapp-settings.php" class="btn btn-outline-primary">
                        <i class="fas fa-cog me-1"></i>Configuración
                    </a>
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
            <!-- Formulario para agregar nueva respuesta -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus me-2"></i>Nueva Respuesta Automática</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_response">
                            
                            <div class="form-floating mb-3">
                                <input type="text" class="form-control" id="trigger_words" name="trigger_words" 
                                       placeholder="hola,saludos,buenos" required>
                                <label for="trigger_words">Palabras Clave</label>
                                <div class="form-text">Separar con comas. Ej: hola,saludos,buenos</div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" id="response_message" name="response_message" 
                                         placeholder="¡Hola! Gracias por contactarnos..." required></textarea>
                                <label for="response_message">Mensaje de Respuesta</label>
                            </div>
                            
                            <div class="row">
                                <div class="col-8">
                                    <div class="form-floating mb-3">
                                        <select class="form-select" id="match_type" name="match_type">
                                            <option value="contains">Contiene</option>
                                            <option value="exact">Exacto</option>
                                            <option value="starts_with">Empieza con</option>
                                            <option value="ends_with">Termina con</option>
                                        </select>
                                        <label for="match_type">Tipo de Coincidencia</label>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="form-floating mb-3">
                                        <input type="number" class="form-control" id="priority" name="priority" 
                                               value="5" min="0" max="99">
                                        <label for="priority">Prioridad</label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success w-100">
                                <i class="fas fa-plus me-1"></i>Agregar Respuesta
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Variables disponibles -->
                <div class="variables-help">
                    <h6><i class="fas fa-info-circle me-2"></i>Variables Disponibles</h6>
                    <p class="mb-2">Puede usar estas variables en sus respuestas:</p>
                    
                    <span class="variable-tag">{restaurant_name}</span>
                    <span class="variable-tag">{restaurant_web}</span>
                    <span class="variable-tag">{restaurant_phone}</span>
                    <span class="variable-tag">{restaurant_email}</span>
                    <span class="variable-tag">{restaurant_address}</span>
                    <span class="variable-tag">{opening_time}</span>
                    <span class="variable-tag">{closing_time}</span>
                    <span class="variable-tag">{delivery_fee}</span>
                    <span class="variable-tag">{min_delivery_amount}</span>
                    
                    <div class="mt-2">
                        <small class="text-muted">Las variables se reemplazarán automáticamente por los valores configurados en el sistema.</small>
                    </div>
                </div>
            </div>

            <!-- Lista de respuestas existentes -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-list me-2"></i>Respuestas Configuradas</h5>
                        <span class="badge bg-info"><?php echo count($responses); ?> respuestas</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($responses)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-robot fa-3x text-muted mb-3"></i>
                                <h5>No hay respuestas configuradas</h5>
                                <p class="text-muted">Agregue su primera respuesta automática usando el formulario</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($responses as $response): ?>
                                <div class="card mb-3 <?php echo $response['is_active'] ? '' : 'opacity-50'; ?>" 
                                     id="response-<?php echo $response['id']; ?>">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <div class="d-flex align-items-start mb-2">
                                                    <span class="badge bg-primary priority-badge me-2"><?php echo $response['priority']; ?></span>
                                                    <div class="flex-grow-1">
                                                        <div class="trigger-words mb-2">
                                                            <i class="fas fa-key me-1"></i>
                                                            <?php echo htmlspecialchars($response['trigger_words']); ?>
                                                            <span class="badge bg-secondary match-type-badge ms-2">
                                                                <?php
                                                                $match_types = [
                                                                    'contains' => 'Contiene',
                                                                    'exact' => 'Exacto',
                                                                    'starts_with' => 'Empieza con',
                                                                    'ends_with' => 'Termina con'
                                                                ];
                                                                echo $match_types[$response['match_type']] ?? $response['match_type'];
                                                                ?>
                                                            </span>
                                                        </div>
                                                        
                                                        <div class="response-preview">
                                                            <i class="fas fa-reply me-1"></i>
                                                            <?php echo nl2br(htmlspecialchars($response['response_message'])); ?>
                                                        </div>
                                                        
                                                        <small class="usage-count">
                                                            <i class="fas fa-chart-bar me-1"></i>
                                                            Usada <?php echo $response['use_count']; ?> veces |
                                                            Creada: <?php echo date('d/m/Y H:i', strtotime($response['created_at'])); ?>
                                                            <?php if ($response['updated_at'] !== $response['created_at']): ?>
                                                                | Actualizada: <?php echo date('d/m/Y H:i', strtotime($response['updated_at'])); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-4 text-end">
                                                <div class="btn-group-vertical" role="group">
                                                    <!-- Estado -->
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="id" value="<?php echo $response['id']; ?>">
                                                        <button type="submit" class="btn btn-sm <?php echo $response['is_active'] ? 'btn-success' : 'btn-outline-secondary'; ?> mb-1">
                                                            <i class="fas fa-<?php echo $response['is_active'] ? 'toggle-on' : 'toggle-off'; ?> me-1"></i>
                                                            <?php echo $response['is_active'] ? 'Activa' : 'Inactiva'; ?>
                                                        </button>
                                                    </form>
                                                    
                                                    <!-- Editar -->
                                                    <button type="button" class="btn btn-sm btn-outline-primary mb-1" 
                                                            onclick="editResponse(<?php echo htmlspecialchars(json_encode($response)); ?>)">
                                                        <i class="fas fa-edit me-1"></i>Editar
                                                    </button>
                                                    
                                                    <!-- Eliminar -->
                                                    <form method="POST" style="display: inline;" 
                                                          onsubmit="return confirm('¿Está seguro de eliminar esta respuesta?')">
                                                        <input type="hidden" name="action" value="delete_response">
                                                        <input type="hidden" name="id" value="<?php echo $response['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                                            <i class="fas fa-trash me-1"></i>Eliminar
                                                        </button>
                                                    </form>
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
        </div>
    </div>

    <!-- Modal de edición -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Respuesta Automática</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_response">
                        <input type="hidden" name="id" id="edit_id">
                        
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" id="edit_trigger_words" name="trigger_words" required>
                            <label for="edit_trigger_words">Palabras Clave</label>
                        </div>
                        
                        <div class="form-floating mb-3">
                            <textarea class="form-control" id="edit_response_message" name="response_message" 
                                     style="min-height: 120px;" required></textarea>
                            <label for="edit_response_message">Mensaje de Respuesta</label>
                        </div>
                        
                        <div class="row">
                            <div class="col-6">
                                <div class="form-floating mb-3">
                                    <select class="form-select" id="edit_match_type" name="match_type">
                                        <option value="contains">Contiene</option>
                                        <option value="exact">Exacto</option>
                                        <option value="starts_with">Empieza con</option>
                                        <option value="ends_with">Termina con</option>
                                    </select>
                                    <label for="edit_match_type">Tipo de Coincidencia</label>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" id="edit_priority" name="priority" 
                                           min="0" max="99">
                                    <label for="edit_priority">Prioridad</label>
                                </div>
                            </div>
                            <div class="col-3">
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" id="edit_is_active" name="is_active">
                                    <label class="form-check-label" for="edit_is_active">Activa</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Guardar Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
<script>
    // Funcionalidad del menú móvil
    document.addEventListener('DOMContentLoaded', function() {
        initializeMobileMenu();
        initializeEditResponse();
        initializeAlerts();
        initializeRowHighlighting();
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

        // Cerrar sidebar al hacer click en enlaces (móvil)
        document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
            link.addEventListener('click', function() {
                if (window.innerWidth < 992) {
                    setTimeout(closeSidebar, 100);
                }
            });
        });

        // Cerrar sidebar al redimensionar ventana
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

    // Funcionalidad existente de editar respuesta
    function editResponse(response) {
        document.getElementById('edit_id').value = response.id;
        document.getElementById('edit_trigger_words').value = response.trigger_words;
        document.getElementById('edit_response_message').value = response.response_message;
        document.getElementById('edit_match_type').value = response.match_type;
        document.getElementById('edit_priority').value = response.priority;
        document.getElementById('edit_is_active').checked = response.is_active == 1;
        
        const editModal = new bootstrap.Modal(document.getElementById('editModal'));
        editModal.show();
    }

    function initializeEditResponse() {
        // La función editResponse ya está definida globalmente arriba
        // Solo necesitamos asegurar que esté disponible
        window.editResponse = editResponse;
    }

    function initializeAlerts() {
        // Auto-dismiss alerts after 5 seconds
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                if (alert && alert.parentNode) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        });
    }

    function initializeRowHighlighting() {
        // Highlight recently updated rows
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('updated')) {
            const responseId = urlParams.get('updated');
            const responseCard = document.getElementById('response-' + responseId);
            if (responseCard) {
                responseCard.style.border = '2px solid #28a745';
                setTimeout(function() {
                    responseCard.style.border = '';
                }, 3000);
            }
        }
    }
</script>
</body>
</html>