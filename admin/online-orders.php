<?php
// admin/online-orders.php - VERSIÓN CON CONSULTA TEMPORAL SIMPLE CORREGIDA
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();

// Verificar permisos específicos para pedidos online
if (!$auth->hasPermission('online_orders')) {
    header('Location: dashboard.php');
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Procesar acciones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['ajax_action'];
        $order_id = $_POST['order_id'] ?? '';
        
        if (!$order_id) {
            throw new Exception('ID de orden requerido');
        }

        switch ($action) {
            case 'accept':
                $estimated_time = $_POST['estimated_time'] ?? 30;
                
                $query = "UPDATE online_orders SET 
                         status = 'accepted', 
                         accepted_at = NOW(), 
                         accepted_by = :user_id,
                         estimated_time = :estimated_time
                         WHERE id = :id";
                
                $stmt = $db->prepare($query);
                $result = $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'estimated_time' => $estimated_time,
                    'id' => $order_id
                ]);
                
                if ($result) {
                    // Obtener datos del pedido para WhatsApp
                    $order_query = "SELECT * FROM online_orders WHERE id = :id";
                    $order_stmt = $db->prepare($order_query);
                    $order_stmt->execute(['id' => $order_id]);
                    $order = $order_stmt->fetch();
                    
                    $whatsapp_message = generateAcceptanceMessage($order, $estimated_time);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Pedido aceptado correctamente',
                        'whatsapp_message' => $whatsapp_message,
                        'whatsapp_url' => generateWhatsAppUrl($order['customer_phone'], $whatsapp_message)
                    ]);
                } else {
                    throw new Exception('Error al aceptar el pedido');
                }
                break;
                
            case 'reject':
                $rejection_reason = $_POST['reason'] ?? 'No especificado';
                
                $query = "UPDATE online_orders SET 
                         status = 'rejected', 
                         rejection_reason = :reason,
                         rejected_at = NOW(),
                         rejected_by = :user_id
                         WHERE id = :id";
                
                $stmt = $db->prepare($query);
                $result = $stmt->execute([
                    'reason' => $rejection_reason,
                    'user_id' => $_SESSION['user_id'],
                    'id' => $order_id
                ]);
                
                if ($result) {
                    $order_query = "SELECT * FROM online_orders WHERE id = :id";
                    $order_stmt = $db->prepare($order_query);
                    $order_stmt->execute(['id' => $order_id]);
                    $order = $order_stmt->fetch();
                    
                    $whatsapp_message = generateRejectionMessage($order, $rejection_reason);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Pedido rechazado',
                        'whatsapp_message' => $whatsapp_message,
                        'whatsapp_url' => generateWhatsAppUrl($order['customer_phone'], $whatsapp_message)
                    ]);
                } else {
                    throw new Exception('Error al rechazar el pedido');
                }
                break;
                
            case 'preparing':
                $query = "UPDATE online_orders SET status = 'preparing', started_preparing_at = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $result = $stmt->execute(['id' => $order_id]);
                
                if ($result) {
                    $order_query = "SELECT * FROM online_orders WHERE id = :id";
                    $order_stmt = $db->prepare($order_query);
                    $order_stmt->execute(['id' => $order_id]);
                    $order = $order_stmt->fetch();
                    
                    $whatsapp_message = generatePreparingMessage($order);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Pedido marcado como en preparación',
                        'whatsapp_message' => $whatsapp_message,
                        'whatsapp_url' => generateWhatsAppUrl($order['customer_phone'], $whatsapp_message)
                    ]);
                } else {
                    throw new Exception('Error al actualizar estado');
                }
                break;
                
            case 'ready':
                $query = "UPDATE online_orders SET status = 'ready', ready_at = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $result = $stmt->execute(['id' => $order_id]);
                
                if ($result) {
                    $order_query = "SELECT * FROM online_orders WHERE id = :id";
                    $order_stmt = $db->prepare($order_query);
                    $order_stmt->execute(['id' => $order_id]);
                    $order = $order_stmt->fetch();
                    
                    $whatsapp_message = generateReadyMessage($order);
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Pedido listo para entrega',
                        'whatsapp_message' => $whatsapp_message,
                        'whatsapp_url' => generateWhatsAppUrl($order['customer_phone'], $whatsapp_message)
                    ]);
                } else {
                    throw new Exception('Error al actualizar estado');
                }
                break;
                
            case 'delivered':
                $query = "UPDATE online_orders SET status = 'delivered', delivered_at = NOW(), delivered_by = :user_id WHERE id = :id";
                $stmt = $db->prepare($query);
                $result = $stmt->execute([
                    'user_id' => $_SESSION['user_id'],
                    'id' => $order_id
                ]);
                
                if ($result) {
                    echo json_encode([
                        'success' => true,
                        'message' => 'Pedido marcado como entregado'
                    ]);
                } else {
                    throw new Exception('Error al marcar como entregado');
                }
                break;
                
            default:
                throw new Exception('Acción no válida');
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// ========================================
// CONSULTA TEMPORAL SUPER SIMPLE - SIN FILTROS
// ========================================
$query = "SELECT * FROM online_orders ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<!-- CONSULTA SIMPLE - Total órdenes: " . count($orders) . " -->";

// ========================================
// PROCESAMIENTO CORREGIDO SIN REFERENCIAS
// ========================================
$processed_orders = [];
foreach ($orders as $order) {
    // Solo procesar items de manera segura
    if (is_string($order['items'])) {
        $decoded = json_decode($order['items'], true);
        $order['items'] = is_array($decoded) ? $decoded : [];
    } elseif (!is_array($order['items'])) {
        $order['items'] = [];
    }
    
    // CÁLCULO DINÁMICO DEL TIEMPO TRANSCURRIDO - AQUÍ ESTABA EL ERROR
    $order['time_elapsed'] = calculateTimeElapsed($order['created_at']);
    
    // CÁLCULO DEL ESTADO DE PRIORIDAD BASADO EN TIEMPO
    $order['priority_status'] = calculatePriorityStatus($order);
    
    // Campos requeridos con valores por defecto
    $order['accepted_by_name'] = '';
    $order['rejected_by_name'] = '';
    $order['delivered_by_name'] = '';
    $order['estimated_remaining'] = calculateEstimatedRemaining($order);
    
    // Agregar la orden procesada al nuevo array
    $processed_orders[] = $order;
}


// Reemplazar el array original con el procesado
$orders = $processed_orders;

$settings = getSettings();

// Funciones auxiliares para generar mensajes de WhatsApp
function generateAcceptanceMessage($order, $estimated_time) {
    $settings = getSettings();
    $restaurant_name = $settings['restaurant_name'] ?? 'Nuestro Restaurante';
    
    $message = "🍽️ *{$restaurant_name}* - PEDIDO CONFIRMADO\n\n";
    $message .= "📋 Orden: {$order['order_number']}\n";
    $message .= "👤 Cliente: {$order['customer_name']}\n";
    $message .= "💰 Total: " . formatPrice($order['total']) . "\n\n";
    $message .= "✅ *Su pedido ha sido ACEPTADO*\n";
    $message .= "⏰ Tiempo estimado: *{$estimated_time} minutos*\n\n";
    $message .= "🚚 Lo entregaremos en: {$order['customer_address']}\n\n";
    $message .= "📱 Cualquier consulta, responda este mensaje.\n";
    $message .= "¡Gracias por elegirnos! 😊";
    
    return $message;
}

function generateRejectionMessage($order, $reason) {
    $settings = getSettings();
    $restaurant_name = $settings['restaurant_name'] ?? 'Nuestro Restaurante';
    
    $message = "😞 *{$restaurant_name}* - PEDIDO NO DISPONIBLE\n\n";
    $message .= "📋 Orden: {$order['order_number']}\n";
    $message .= "👤 Cliente: {$order['customer_name']}\n\n";
    $message .= "❌ Lamentamos informarle que no podemos procesar su pedido en este momento.\n\n";
    $message .= "📝 Motivo: {$reason}\n\n";
    $message .= "🙏 Disculpe las molestias. Lo invitamos a realizar un nuevo pedido más tarde.\n";
    $message .= "📱 Para consultas, responda este mensaje.";
    
    return $message;
}

function generatePreparingMessage($order) {
    $settings = getSettings();
    $restaurant_name = $settings['restaurant_name'] ?? 'Nuestro Restaurante';
    
    $message = "👨‍🍳 *{$restaurant_name}* - EN PREPARACIÓN\n\n";
    $message .= "📋 Orden: {$order['order_number']}\n";
    $message .= "👤 Cliente: {$order['customer_name']}\n\n";
    $message .= "🔥 ¡Su pedido ya está en cocina!\n";
    $message .= "👨‍🍳 Nuestros chefs están preparando su orden con mucho cariño.\n\n";
    $message .= "📱 Le avisaremos cuando esté listo para entregar.";
    
    return $message;
}

function generateReadyMessage($order) {
    $settings = getSettings();
    $restaurant_name = $settings['restaurant_name'] ?? 'Nuestro Restaurante';
    
    $message = "🚚 *{$restaurant_name}* - PEDIDO LISTO\n\n";
    $message .= "📋 Orden: {$order['order_number']}\n";
    $message .= "👤 Cliente: {$order['customer_name']}\n\n";
    $message .= "✅ ¡Su pedido está LISTO!\n";
    $message .= "🛵 Nuestro delivery está saliendo hacia su domicilio.\n\n";
    $message .= "📍 Dirección: {$order['customer_address']}\n\n";
    $message .= "📱 Lo contactaremos al llegar.";
    
    return $message;
}

function generateWhatsAppUrl($phone, $message) {
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    return "https://wa.me/" . $clean_phone . "?text=" . urlencode($message);
}

// Función mejorada para calcular tiempo transcurrido
function calculateTimeElapsed($created_at) {
    try {
        $created = new DateTime($created_at);
        $now = new DateTime();
        $diff = $now->diff($created);
        
        $total_minutes = ($diff->h * 60) + $diff->i;
        
        if ($diff->days > 0) {
            return $diff->days . 'd ' . $diff->h . 'h';
        } elseif ($diff->h > 0) {
            return $diff->h . 'h ' . $diff->i . 'm';
        } elseif ($diff->i > 0) {
            return $diff->i . 'm';
        } else {
            return 'Recién';
        }
    } catch (Exception $e) {
        return 'N/A';
    }
}

// Nueva función para calcular prioridad basada en tiempo
function calculatePriorityStatus($order) {
    try {
        $created = new DateTime($order['created_at']);
        $now = new DateTime();
        $diff = $now->diff($created);
        $minutes_elapsed = ($diff->h * 60) + $diff->i + ($diff->days * 24 * 60);
        
        $is_priority = false;
        $urgency_level = 'normal';
        
        // Determinar prioridad según estado y tiempo
        switch ($order['status']) {
            case 'pending':
                if ($minutes_elapsed > 10) {
                    $is_priority = true;
                    $urgency_level = $minutes_elapsed > 20 ? 'urgent' : 'high';
                }
                break;
            case 'accepted':
            case 'preparing':
                if ($minutes_elapsed > 30) {
                    $is_priority = true;
                    $urgency_level = $minutes_elapsed > 45 ? 'urgent' : 'high';
                }
                break;
            case 'ready':
                if ($minutes_elapsed > 60) {
                    $is_priority = true;
                    $urgency_level = 'urgent';
                }
                break;
        }
        
        return [
            'is_priority' => $is_priority,
            'urgency_level' => $urgency_level,
            'minutes_elapsed' => $minutes_elapsed
        ];
    } catch (Exception $e) {
        return ['is_priority' => false, 'urgency_level' => 'normal', 'minutes_elapsed' => 0];
    }
}

// Nueva función para calcular tiempo restante estimado
function calculateEstimatedRemaining($order) {
    if (!isset($order['estimated_time']) || !$order['accepted_at']) {
        return 0;
    }
    
    try {
        $accepted = new DateTime($order['accepted_at']);
        $now = new DateTime();
        $elapsed_since_accepted = $now->diff($accepted);
        $minutes_elapsed = ($elapsed_since_accepted->h * 60) + $elapsed_since_accepted->i;
        
        $remaining = $order['estimated_time'] - $minutes_elapsed;
        return max(0, $remaining);
    } catch (Exception $e) {
        return 0;
    }
}

// Función mejorada para obtener clase CSS del tiempo
function getTimeClass($order) {
    if (!isset($order['priority_status'])) {
        return 'time-normal';
    }
    
    switch ($order['priority_status']['urgency_level']) {
        case 'urgent':
            return 'time-urgent';
        case 'high':
            return 'time-warning';
        default:
            return 'time-normal';
    }
}

function getStatusColor($status) {
    $colors = [
        'pending' => 'warning',
        'accepted' => 'info',
        'preparing' => 'warning',
        'ready' => 'success',
        'delivered' => 'primary',
        'rejected' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

function getStatusText($status) {
    $texts = [
        'pending' => 'Pendiente',
        'accepted' => 'Aceptado',
        'preparing' => 'Preparando',
        'ready' => 'Listo',
        'delivered' => 'Entregado',
        'rejected' => 'Rechazado'
    ];
    return $texts[$status] ?? $status;
}

function getOrderActions($order) {
    $details_btn = '<a href="online-order-details.php?id=' . $order['id'] . '" class="btn btn-outline-info btn-sm me-1">
                        <i class="fas fa-eye me-1"></i>
                        Ver Detalles
                    </a>';
    
    switch ($order['status']) {
        case 'pending':
            return '
                <div class="action-buttons mt-3">
                    ' . $details_btn . '
                    <button class="btn btn-accept" onclick="showAcceptModal(\'' . $order['id'] . '\')">
                        <i class="fas fa-check me-1"></i>
                        Aceptar Pedido
                    </button>
                    <button class="btn btn-reject" onclick="showRejectModal(\'' . $order['id'] . '\')">
                        <i class="fas fa-times me-1"></i>
                        Rechazar
                    </button>
                    <button class="btn btn-info" onclick="sendCustomWhatsApp(\'' . $order['customer_phone'] . '\')">
                        <i class="fab fa-whatsapp me-1"></i>
                        WhatsApp
                    </button>
                </div>
            ';
        
        case 'accepted':
            return '
                <div class="action-buttons mt-3">
                    ' . $details_btn . '
                    <button class="btn btn-preparing" onclick="updateOrderStatus(\'' . $order['id'] . '\', \'preparing\')">
                        <i class="fas fa-fire me-1"></i>
                        Marcar Preparando
                    </button>
                    <button class="btn btn-whatsapp" onclick="sendCustomWhatsApp(\'' . $order['customer_phone'] . '\')">
                        <i class="fab fa-whatsapp me-1"></i>
                        WhatsApp
                    </button>
                </div>
            ';
        
        case 'preparing':
            return '
                <div class="action-buttons mt-3">
                    ' . $details_btn . '
                    <button class="btn btn-success" onclick="updateOrderStatus(\'' . $order['id'] . '\', \'ready\')">
                        <i class="fas fa-check-circle me-1"></i>
                        Marcar Listo
                    </button>
                    <button class="btn btn-whatsapp" onclick="sendCustomWhatsApp(\'' . $order['customer_phone'] . '\')">
                        <i class="fab fa-whatsapp me-1"></i>
                        WhatsApp
                    </button>
                </div>
            ';
        
        case 'ready':
            return '
                <div class="action-buttons mt-3">
                    ' . $details_btn . '
                    <button class="btn btn-primary" onclick="updateOrderStatus(\'' . $order['id'] . '\', \'delivered\')">
                        <i class="fas fa-truck me-1"></i>
                        Marcar Entregado
                    </button>
                    <button class="btn btn-whatsapp" onclick="sendCustomWhatsApp(\'' . $order['customer_phone'] . '\')">
                        <i class="fab fa-whatsapp me-1"></i>
                        WhatsApp
                    </button>
                </div>
            ';
        
        case 'delivered':
        case 'rejected':
            return '
                <div class="action-buttons mt-3">
                    ' . $details_btn . '
                    <button class="btn btn-outline-secondary" disabled>
                        <i class="fas fa-check me-1"></i>
                        Completado
                    </button>
                </div>
            ';
        
        default:
            return $details_btn;
    }
}

function getOrderTimeline($order) {
    $timeline = '';
    
    if ($order['accepted_at']) {
        $timeline .= '<small class="text-muted d-block"><i class="fas fa-check text-success me-1"></i>Aceptado: ' . formatDateTime($order['accepted_at']) . '</small>';
    }
    if ($order['started_preparing_at']) {
        $timeline .= '<small class="text-muted d-block"><i class="fas fa-fire text-warning me-1"></i>Preparando: ' . formatDateTime($order['started_preparing_at']) . '</small>';
    }
    if ($order['ready_at']) {
        $timeline .= '<small class="text-muted d-block"><i class="fas fa-check-circle text-success me-1"></i>Listo: ' . formatDateTime($order['ready_at']) . '</small>';
    }
    if ($order['delivered_at']) {
        $timeline .= '<small class="text-muted d-block"><i class="fas fa-truck text-primary me-1"></i>Entregado: ' . formatDateTime($order['delivered_at']) . '</small>';
    }
    if ($order['rejected_at']) {
        $timeline .= '<small class="text-muted d-block"><i class="fas fa-times text-danger me-1"></i>Rechazado: ' . formatDateTime($order['rejected_at']) . ' - ' . htmlspecialchars($order['rejection_reason']) . '</small>';
    }
    
    return $timeline ? '<div class="mt-3 pt-2 border-top">' . $timeline . '</div>' : '';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedidos Online - <?php echo $settings['restaurant_name']; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            --warning-gradient: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            --danger-gradient: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
            --sidebar-width: 280px;
        }

        body {
            background: #f8f9fa;
            overflow-x: hidden;
        }

        /* Mobile Top Bar */
        .mobile-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            background: linear-gradient(135deg, #ff6b6b, #ffa500);
            color: white;
            padding: 1rem;
            display: none;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .menu-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-gradient);
            color: white;
            z-index: 1030;
            transition: transform 0.3s ease-in-out;
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
            transition: opacity 0.3s ease-in-out;
        }

        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
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
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out;
        }

        /* Statistics cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
        }

        /* Order cards */
        .order-card {
            background: white;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            border-left: 5px solid #6c757d;
            overflow: hidden;
        }

        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .order-card.status-pending { border-left-color: #ffc107; }
        .order-card.status-accepted { border-left-color: #17a2b8; }
        .order-card.status-preparing { border-left-color: #fd7e14; }
        .order-card.status-ready { border-left-color: #28a745; }
        .order-card.status-delivered { border-left-color: #007bff; }
        .order-card.status-rejected { border-left-color: #dc3545; }

        .order-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .order-body {
            padding: 1.5rem;
        }

        .order-number {
            font-weight: bold;
            font-size: 1.1rem;
            color: #007bff;
        }

        .time-badge {
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: bold;
        }

        .time-normal { background: #d4edda; color: #155724; }
        .time-warning { background: #fff3cd; color: #856404; }
        .time-urgent { background: #f8d7da; color: #721c24; animation: pulse 2s infinite; }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .priority-badge {
            animation: pulse 2s infinite;
        }

        .customer-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .items-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .item-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .item-row:last-child {
            border-bottom: none;
            font-weight: bold;
            margin-top: 0.5rem;
            padding-top: 1rem;
            border-top: 2px solid #dee2e6;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .btn-accept {
            background: var(--success-gradient);
            border: none;
            color: white;
        }

        .btn-reject {
            background: var(--danger-gradient);
            border: none;
            color: white;
        }

        .btn-whatsapp {
            background: #25d366;
            border: none;
            color: white;
        }

        .btn-preparing {
            background: var(--warning-gradient);
            border: none;
            color: white;
        }

        .status-badge {
            font-size: 0.9rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
        }

        .filters-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .page-header {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            background: var(--primary-gradient);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        /* Mobile responsive */
        @media (max-width: 991.98px) {
            .mobile-topbar {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .sidebar {
                transform: translateX(-100%);
                width: 100%;
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

            .stats-container {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.5rem;
            }

            .stat-card {
                padding: 1rem;
            }

            .order-body {
                padding: 1rem;
            }

            .action-buttons {
                flex-direction: column;
            }

            .action-buttons .btn {
                margin-bottom: 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
                padding-top: 4.5rem;
            }

            .page-header {
                padding: 1rem;
            }

            .order-card {
                margin-bottom: 1rem;
            }

            .customer-info, .items-list {
                padding: 0.75rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .stat-icon {
                font-size: 1.5rem;
            }
        }
        .time-urgent {
    background: #f8d7da;
    color: #721c24;
    animation: pulse 2s infinite;
    font-weight: bold;
}

.time-warning {
    background: #fff3cd;
    color: #856404;
    font-weight: bold;
}

.time-normal {
    background: #d4edda;
    color: #155724;
}

.priority-badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.8; transform: scale(1.05); }
    100% { opacity: 1; transform: scale(1); }
}

/* Agregar sonido/alerta visual para pedidos urgentes */
.order-card.urgent {
    box-shadow: 0 0 20px rgba(220, 53, 69, 0.3);
    border-left: 5px solid #dc3545 !important;
}
*/
    </style>
</head>
<body>
    <!-- Mobile Top Bar -->
    <div class="mobile-topbar">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div class="d-flex align-items-center">
                <button class="menu-toggle me-3" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5>
                    <i class="fas fa-globe me-2"></i>
                    Pedidos Online
                </h5>
            </div>
            <div class="d-flex align-items-center">
                <small class="me-3 d-none d-sm-inline">
                    <i class="fas fa-user me-1"></i>
                    <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>
                </small>
                <small>
                    <i class="fas fa-clock me-1"></i>
                    <?php echo date('H:i'); ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Sidebar Backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="sidebar-close" id="sidebarClose">
            <i class="fas fa-times"></i>
        </button>

        <div class="text-center mb-4">
            <h4>
                <i class="fas fa-utensils me-2"></i>
                <?php echo $settings['restaurant_name']; ?>
            </h4>
            <small>Pedidos Online</small>
        </div>

        <div class="mb-4">
            <div class="d-flex align-items-center">
                <div class="bg-white bg-opacity-20 rounded-circle p-2 me-2">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="fw-bold"><?php echo $_SESSION['full_name']; ?></div>
                    <small class="opacity-75"><?php echo ucfirst($_SESSION['role_name']); ?></small>
                </div>
            </div>
        </div>

        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt me-2"></i>
                Dashboard
            </a>

            <?php if ($auth->hasPermission('orders')): ?>
                <a class="nav-link" href="orders.php">
                    <i class="fas fa-receipt me-2"></i>
                    Órdenes
                </a>
            <?php endif; ?>

            <?php if ($auth->hasPermission('online_orders')): ?>
                <a class="nav-link active" href="online-orders.php">
                    <i class="fas fa-globe me-2"></i>
                    Pedidos Online
                </a>
            <?php endif; ?>

            <?php if ($auth->hasPermission('tables')): ?>
                <a class="nav-link" href="tables.php">
                    <i class="fas fa-table me-2"></i>
                    Mesas
                </a>
            <?php endif; ?>

            <?php if ($auth->hasPermission('kitchen')): ?>
                <a class="nav-link" href="kitchen.php">
                    <i class="fas fa-fire me-2"></i>
                    Cocina
                </a>
            <?php endif; ?>

            <?php if ($auth->hasPermission('delivery')): ?>
                <a class="nav-link" href="delivery.php">
                    <i class="fas fa-motorcycle me-2"></i>
                    Delivery
                </a>
            <?php endif; ?>

            <?php if ($auth->hasPermission('products')): ?>
                <a class="nav-link" href="products.php">
                    <i class="fas fa-utensils me-2"></i>
                    Productos
                </a>
            <?php endif; ?>

            <?php if ($auth->hasPermission('users')): ?>
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>
                    Usuarios
                </a>
            <?php endif; ?>

            <?php if ($auth->hasPermission('all')): ?>
                <hr class="text-white-50 my-3">
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog me-2"></i>
                    Configuración
                </a>
            <?php endif; ?>

            <hr class="text-white-50 my-3">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>
                Cerrar Sesión
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1 class="mb-2">
                        <i class="fas fa-globe me-2"></i>
                        Gestión de Pedidos Online
                    </h1>
                    <p class="mb-0 opacity-75">Administre los pedidos recibidos por la web</p>
                </div>
                <div>
                    <button class="btn btn-light" onclick="location.reload()">
                        <i class="fas fa-sync me-1"></i>
                        Actualizar
                    </button>
                </div>
            </div>
        </div>


        <!-- Statistics -->
        <div class="stats-container">
            <?php
            $stats = [
                'pending' => count(array_filter($orders, fn($o) => $o['status'] === 'pending')),
                'accepted' => count(array_filter($orders, fn($o) => $o['status'] === 'accepted')),
                'preparing' => count(array_filter($orders, fn($o) => $o['status'] === 'preparing')),
                'ready' => count(array_filter($orders, fn($o) => $o['status'] === 'ready')),
                'delivered' => count(array_filter($orders, fn($o) => $o['status'] === 'delivered')),
                'priority' => count(array_filter($orders, fn($o) => $o['priority_status']['is_priority'] ?? false))
            ];
            ?>
            
            <div class="stat-card">
                <div class="stat-icon text-warning"><i class="fas fa-clock"></i></div>
                <div class="stat-number text-warning"><?php echo $stats['pending']; ?></div>
                <p class="text-muted mb-0">Pendientes</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-info"><i class="fas fa-check"></i></div>
                <div class="stat-number text-info"><?php echo $stats['accepted']; ?></div>
                <p class="text-muted mb-0">Aceptados</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-warning"><i class="fas fa-fire"></i></div>
                <div class="stat-number text-warning"><?php echo $stats['preparing']; ?></div>
                <p class="text-muted mb-0">Preparando</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-number text-success"><?php echo $stats['ready']; ?></div>
                <p class="text-muted mb-0">Listos</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-primary"><i class="fas fa-truck"></i></div>
                <div class="stat-number text-primary"><?php echo $stats['delivered']; ?></div>
                <p class="text-muted mb-0">Entregados</p>
            </div>
            <div class="stat-card">
                <div class="stat-icon text-danger"><i class="fas fa-exclamation-triangle"></i></div>
                <div class="stat-number text-danger"><?php echo $stats['priority']; ?></div>
                <p class="text-muted mb-0">Prioritarios</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" name="status">
                        <option value="">Todos los estados</option>
                        <option value="pending">Pendientes</option>
                        <option value="accepted">Aceptados</option>
                        <option value="preparing">En preparación</option>
                        <option value="ready">Listos</option>
                        <option value="delivered">Entregados</option>
                        <option value="rejected">Rechazados</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" class="form-control" name="date">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Acciones rápidas</label>
                    <div class="d-grid">
                        <a href="?status=pending" class="btn btn-outline-success">
                            Solo pendientes
                        </a>
                    </div>
                </div>
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>
                            Filtrar
                        </button>
                        <a href="online-orders.php" class="btn btn-outline-secondary">
                            Limpiar filtros
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Orders List -->
        <?php if (empty($orders)): ?>
            <div class="empty-state">
                <i class="fas fa-search"></i>
                <h4>No se encontraron pedidos</h4>
                <p>No hay pedidos que coincidan con los filtros seleccionados</p>
                <a href="online-orders.php" class="btn btn-primary">
                    <i class="fas fa-refresh me-1"></i>
                    Ver todos los pedidos
                </a>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($orders as $order): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="order-card status-<?php echo $order['status']; ?>">
                            <div class="order-header">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                                        <small class="text-muted"><?php echo formatDateTime($order['created_at']); ?></small>
                                    </div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="time-badge <?php echo getTimeClass($order); ?>">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo htmlspecialchars($order['time_elapsed']); ?>
                                        </span>
                                        <?php if ($order['priority_status']['is_priority']): ?>
                                        <span class="badge bg-danger priority-badge ms-1">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            URGENTE
                                        </span>
                                        <?php endif; ?>
                                        <span class="status-badge bg-<?php echo getStatusColor($order['status']); ?>">
                                            <?php echo getStatusText($order['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="order-body">
                                <!-- Información del cliente -->
                                <div class="customer-info mb-3">
                                    <h6><i class="fas fa-user me-2"></i>Información del Cliente</h6>
                                    <p class="mb-1"><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                                    <p class="mb-1">
                                        <i class="fas fa-phone me-1"></i>
                                        <a href="tel:<?php echo htmlspecialchars($order['customer_phone']); ?>" class="text-decoration-none">
                                            <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </a>
                                    </p>
                                    <p class="mb-1">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($order['customer_address']); ?>
                                    </p>
                                    <?php if (isset($order['customer_notes']) && $order['customer_notes']): ?>
                                        <p class="mb-0">
                                            <i class="fas fa-sticky-note me-1"></i>
                                            <small class="text-muted"><?php echo htmlspecialchars($order['customer_notes']); ?></small>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Items del pedido -->
                                <div class="items-list mb-3">
                                    <h6><i class="fas fa-list me-2"></i>Detalle del Pedido</h6>
                                    <?php foreach ($order['items'] as $item): ?>
                                        <div class="item-row">
                                            <span><?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['name']); ?></span>
                                            <span><?php echo formatPrice($item['price'] * $item['quantity']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <div class="item-row">
                                        <strong>Total:</strong>
                                        <strong><?php echo formatPrice($order['total']); ?></strong>
                                    </div>
                                </div>
                                
                                <?php echo getOrderActions($order); ?>
                                <?php echo getOrderTimeline($order); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Accept Order Modal -->
    <div class="modal fade" id="acceptModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle text-success me-2"></i>
                        Aceptar Pedido
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Tiempo estimado de preparación (minutos)</label>
                        <select class="form-select" id="estimatedTime">
                            <option value="15">15 minutos</option>
                            <option value="20">20 minutos</option>
                            <option value="25">25 minutos</option>
                            <option value="30" selected>30 minutos</option>
                            <option value="35">35 minutos</option>
                            <option value="40">40 minutos</option>
                            <option value="45">45 minutos</option>
                            <option value="60">1 hora</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Se enviará un WhatsApp automático al cliente confirmando el pedido y el tiempo estimado.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" onclick="confirmAcceptOrder()">
                        <i class="fas fa-check me-1"></i>
                        Aceptar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Order Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-times-circle text-danger me-2"></i>
                        Rechazar Pedido
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Motivo del rechazo</label>
                        <select class="form-select" id="rejectionReason">
                            <option value="Sin stock de productos">Sin stock de productos</option>
                            <option value="Fuera del área de delivery">Fuera del área de delivery</option>
                            <option value="Cocina cerrada">Cocina cerrada</option>
                            <option value="Saturado de pedidos">Saturado de pedidos</option>
                            <option value="Datos incorretos">Datos incorretos</option>
                            <option value="Otro">Otro motivo</option>
                        </select>
                    </div>
                    <div class="mb-3" id="customReasonDiv" style="display: none;">
                        <label class="form-label">Especificar motivo</label>
                        <textarea class="form-control" id="customReason" rows="3"></textarea>
                    </div>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Se enviará un WhatsApp automático al cliente informando el rechazo.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-danger" onclick="confirmRejectOrder()">
                        <i class="fas fa-times me-1"></i>
                        Rechazar Pedido
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp Message Modal -->
    <div class="modal fade" id="whatsappModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fab fa-whatsapp text-success me-2"></i>
                        Mensaje de WhatsApp
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Mensaje generado:</label>
                        <textarea class="form-control" id="whatsappMessage" rows="8" readonly></textarea>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        El mensaje se abrirá en WhatsApp Web o la aplicación móvil para enviar.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-success" id="sendWhatsappBtn">
                        <i class="fab fa-whatsapp me-1"></i>
                        Enviar por WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentOrderForAction = null;

        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
            setupEventListeners();
            
            // Auto-refresh cada 30 segundos
            setInterval(() => location.reload(), 30000);
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

        function setupEventListeners() {
            // Cambio en el selector de motivo de rechazo
            document.getElementById('rejectionReason').addEventListener('change', function() {
                const customDiv = document.getElementById('customReasonDiv');
                if (this.value === 'Otro') {
                    customDiv.style.display = 'block';
                    document.getElementById('customReason').required = true;
                } else {
                    customDiv.style.display = 'none';
                    document.getElementById('customReason').required = false;
                }
            });
        }

        function showAcceptModal(orderId) {
            currentOrderForAction = orderId;
            const modal = new bootstrap.Modal(document.getElementById('acceptModal'));
            modal.show();
        }

        function showRejectModal(orderId) {
            currentOrderForAction = orderId;
            const modal = new bootstrap.Modal(document.getElementById('rejectModal'));
            modal.show();
        }

        async function confirmAcceptOrder() {
            if (!currentOrderForAction) return;
            
            const estimatedTime = document.getElementById('estimatedTime').value;
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'accept');
                formData.append('order_id', currentOrderForAction);
                formData.append('estimated_time', estimatedTime);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Pedido aceptado correctamente');
                    
                    // Cerrar el modal de aceptación
                    const acceptModal = bootstrap.Modal.getInstance(document.getElementById('acceptModal'));
                    if (acceptModal) {
                        acceptModal.hide();
                    }
                    
                    // Mostrar WhatsApp sin recarga automática
                    setTimeout(() => {
                        if (data.whatsapp_message) {
                            showWhatsAppMessage(data.whatsapp_message, data.whatsapp_url, true);
                        } else {
                            location.reload();
                        }
                    }, 500);
                    
                } else {
                    showError('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Error de conexión');
            }
        }

        async function confirmRejectOrder() {
            if (!currentOrderForAction) return;
            
            let reason = document.getElementById('rejectionReason').value;
            if (reason === 'Otro') {
                const customReason = document.getElementById('customReason').value.trim();
                if (!customReason) {
                    showError('Debe especificar el motivo del rechazo');
                    return;
                }
                reason = customReason;
            }
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', 'reject');
                formData.append('order_id', currentOrderForAction);
                formData.append('reason', reason);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess('Pedido rechazado correctamente');
                    
                    const rejectModal = bootstrap.Modal.getInstance(document.getElementById('rejectModal'));
                    if (rejectModal) {
                        rejectModal.hide();
                    }
                    
                    setTimeout(() => {
                        if (data.whatsapp_message) {
                            showWhatsAppMessage(data.whatsapp_message, data.whatsapp_url, true);
                        } else {
                            location.reload();
                        }
                    }, 500);
                    
                } else {
                    showError('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Error de conexión');
            }
        }

        async function updateOrderStatus(orderId, status) {
            const confirmMessages = {
                'preparing': '¿Confirmar que el pedido está en preparación?',
                'ready': '¿Confirmar que el pedido está listo para entrega?',
                'delivered': '¿Confirmar que el pedido fue entregado?'
            };
            
            if (!confirm(confirmMessages[status])) return;
            
            try {
                const formData = new FormData();
                formData.append('ajax_action', status);
                formData.append('order_id', orderId);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    showSuccess(data.message);
                    
                    if (data.whatsapp_message) {
                        showWhatsAppMessage(data.whatsapp_message, data.whatsapp_url, true);
                    } else {
                        setTimeout(() => location.reload(), 1000);
                    }
                } else {
                    showError('Error: ' + data.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Error de conexión');
            }
        }

        function showWhatsAppMessage(message, url, allowReload = false) {
            const allModals = document.querySelectorAll('.modal');
            allModals.forEach(modal => {
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) {
                    modalInstance.hide();
                }
            });
            
            setTimeout(() => {
                document.getElementById('whatsappMessage').value = message;
                
                document.getElementById('sendWhatsappBtn').onclick = () => {
                    window.open(url, '_blank');
                    
                    const whatsappModal = bootstrap.Modal.getInstance(document.getElementById('whatsappModal'));
                    if (whatsappModal) {
                        whatsappModal.hide();
                    }
                    
                    if (allowReload) {
                        setTimeout(() => location.reload(), 1000);
                    }
                };
                
                const closeBtn = document.querySelector('#whatsappModal .btn-secondary');
                if (closeBtn) {
                    closeBtn.onclick = () => {
                        const whatsappModal = bootstrap.Modal.getInstance(document.getElementById('whatsappModal'));
                        if (whatsappModal) {
                            whatsappModal.hide();
                        }
                        if (allowReload) {
                            setTimeout(() => location.reload(), 500);
                        }
                    };
                }
                
                const whatsappModalElement = document.getElementById('whatsappModal');
                whatsappModalElement.addEventListener('hidden.bs.modal', function() {
                    if (allowReload) {
                        setTimeout(() => location.reload(), 500);
                    }
                }, { once: true });
                
                const modal = new bootstrap.Modal(document.getElementById('whatsappModal'), {
                    backdrop: 'static',
                    keyboard: false
                });
                modal.show();
            }, 300);
        }

        function sendCustomWhatsApp(phone) {
            const message = 'Hola! Le escribo desde el restaurante. ¿En qué podemos ayudarle?';
            const url = `https://wa.me/${phone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(message)}`;
            window.open(url, '_blank');
        }

        function showSuccess(message) {
            alert('✅ ' + message);
        }

        function showError(message) {
            alert('❌ ' + message);
        }
    </script>
    <script>
// Función para actualizar el tiempo de cada pedido sin recargar la página
function updateOrderTimes() {
    const orderCards = document.querySelectorAll('.order-card');
    
    orderCards.forEach(card => {
        const timeElement = card.querySelector('.time-badge');
        const createdAtData = card.getAttribute('data-created-at');
        
        if (timeElement && createdAtData) {
            const createdAt = new Date(createdAtData);
            const now = new Date();
            const diff = now - createdAt;
            
            const minutes = Math.floor(diff / (1000 * 60));
            const hours = Math.floor(minutes / 60);
            const days = Math.floor(hours / 24);
            
            let timeText = '';
            if (days > 0) {
                timeText = `${days}d ${hours % 24}h`;
            } else if (hours > 0) {
                timeText = `${hours}h ${minutes % 60}m`;
            } else if (minutes > 0) {
                timeText = `${minutes}m`;
            } else {
                timeText = 'Recién';
            }
            
            timeElement.innerHTML = `<i class="fas fa-clock me-1"></i>${timeText}`;
            
            // Actualizar clases CSS según urgencia
            timeElement.className = timeElement.className.replace(/time-(normal|warning|urgent)/, '');
            if (minutes > 30) {
                timeElement.classList.add('time-urgent');
                card.classList.add('urgent');
            } else if (minutes > 15) {
                timeElement.classList.add('time-warning');
            } else {
                timeElement.classList.add('time-normal');
            }
        }
    });
}

// Actualizar cada minuto
setInterval(updateOrderTimes, 60000);

// Actualizar al cargar la página
document.addEventListener('DOMContentLoaded', updateOrderTimes);
</script>

<?php include 'footer.php'; ?>
</body>
</html>