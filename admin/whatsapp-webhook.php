<?php
// admin/whatsapp-webhook.php - Versión mejorada usando configuración centralizada
require_once '../config/config.php';
require_once '../config/database.php';

// Función para obtener configuración del webhook desde la base de datos
function getWebhookToken() {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_webhook_token'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : 'whatsapp-webhook-comias';
        
    } catch (Exception $e) {
        // Log error but continue with fallback
        error_log("Error getting webhook token: " . $e->getMessage());
        return 'whatsapp-webhook-comias';
    }
}

// Función para obtener configuración del restaurante
function getRestaurantSettings() {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT setting_key, setting_value FROM settings 
                  WHERE setting_key IN ('restaurant_name', 'restaurant_web')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $settings = [
            'restaurant_name' => 'Mi Restaurante',
            'restaurant_web' => 'https://comidas.ordenes.com.ar'
        ];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
        
    } catch (Exception $e) {
        error_log("Error getting restaurant settings: " . $e->getMessage());
        return [
            'restaurant_name' => 'Mi Restaurante',
            'restaurant_web' => 'https://comidas.ordenes.com.ar'
        ];
    }
}

// Log para debugging
function logWebhook($message) {
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents('webhook.log', "[$timestamp] $message\n", FILE_APPEND);
}

// Log detallado para debugging
function logWebhookDetailed($data) {
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = [
        'timestamp' => $timestamp,
        'method' => $_SERVER['REQUEST_METHOD'],
        'url' => $_SERVER['REQUEST_URI'] ?? '',
        'get_params' => $_GET,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'data' => $data
    ];
    file_put_contents('webhook_detailed.log', json_encode($log_entry) . "\n", FILE_APPEND);
}

// Verificación inicial del webhook (Meta lo llama con GET)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $verify_token = getWebhookToken();
    
    // Log petición GET
    logWebhookDetailed('GET request received');
    
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    logWebhook("GET Request - Mode: '$mode', Token: '$token', Expected: '$verify_token', Challenge: '$challenge'");
    
    if ($mode === 'subscribe' && $token === $verify_token) {
        logWebhook("Verification successful - sending challenge: $challenge");
        // Solo enviar el challenge, sin headers adicionales
        echo $challenge;
        exit;
    } else {
        logWebhook("Verification failed");
        http_response_code(403);
        echo json_encode([
            'error' => 'Invalid verification token',
            'received_mode' => $mode,
            'received_token' => $token,
            'expected_token' => $verify_token
        ]);
        exit;
    }
}

// Procesamiento de mensajes entrantes (Meta envía POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    
    // Log petición POST
    logWebhookDetailed(['post_data' => $input]);
    logWebhook("POST received: " . substr($input, 0, 100) . "...");
    
    $data = json_decode($input, true);
    
    if ($data && isset($data['entry'])) {
        foreach ($data['entry'] as $entry) {
            if (isset($entry['changes'])) {
                foreach ($entry['changes'] as $change) {
                    if ($change['field'] === 'messages') {
                        processIncomingMessage($change['value']);
                    }
                }
            }
        }
    }
    
    // Responder 200 OK a Meta
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

function processIncomingMessage($value) {
    try {
        // Conectar usando la clase Database centralizada
        $database = new Database();
        $db = $database->getConnection();
        
        // Verificar si las tablas existen
        createTablesIfNotExist($db);
        
        // Procesar mensajes recibidos
        if (isset($value['messages'])) {
            foreach ($value['messages'] as $message) {
                handleIncomingMessage($db, $message);
            }
        }
        
        // Procesar cambios de estado de mensajes enviados
        if (isset($value['statuses'])) {
            foreach ($value['statuses'] as $status) {
                handleMessageStatus($db, $status);
            }
        }
        
    } catch (Exception $e) {
        logWebhook("Error processing message: " . $e->getMessage());
        error_log("Error processing WhatsApp message: " . $e->getMessage());
    }
}

function handleIncomingMessage($db, $message) {
    $from = $message['from'];
    $message_id = $message['id'];
    $timestamp = $message['timestamp'];
    $type = $message['type'];
    
    // Extraer el contenido del mensaje según el tipo
    $content = '';
    $media_url = null;
    
    switch ($type) {
        case 'text':
            $content = $message['text']['body'];
            break;
        case 'image':
            $content = $message['image']['caption'] ?? '[Imagen]';
            $media_url = $message['image']['id'];
            break;
        case 'document':
            $content = $message['document']['filename'] ?? '[Documento]';
            $media_url = $message['document']['id'];
            break;
        case 'audio':
            $content = '[Audio]';
            $media_url = $message['audio']['id'];
            break;
        default:
            $content = "[Mensaje de tipo: $type]";
    }
    
    // Verificar si es respuesta a un pedido existente
    $order_id = findRelatedOrder($db, $from);
    
    try {
        // Verificar duplicados usando la conexión centralizada
        $check_query = "SELECT id FROM whatsapp_messages WHERE message_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$message_id]);
        
        if ($check_stmt->fetch()) {
            logWebhook("Duplicate message ignored: $message_id");
            return;
        }
        
        // Guardar mensaje en la base de datos
        $query = "INSERT INTO whatsapp_messages (
            message_id, phone_number, message_type, content, media_url, 
            order_id, is_from_customer, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 1, FROM_UNIXTIME(?))";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $message_id,
            $from,
            $type,
            $content,
            $media_url,
            $order_id,
            $timestamp
        ]);
        
        logWebhook("Message saved: From $from, Type: $type, Content: " . substr($content, 0, 50));
        
        // Procesar respuestas automáticas si está habilitado
        processAutoResponse($db, $from, $content, $order_id);
        
    } catch (Exception $e) {
        logWebhook("Error saving message: " . $e->getMessage());
    }
}

function handleMessageStatus($db, $status) {
    $message_id = $status['id'];
    $status_type = $status['status'];
    $timestamp = $status['timestamp'];
    
    try {
        $query = "UPDATE whatsapp_logs SET 
                  delivery_status = ?, 
                  status_updated_at = FROM_UNIXTIME(?) 
                  WHERE message_id = ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$status_type, $timestamp, $message_id]);
        
        logWebhook("Status updated: $message_id -> $status_type");
    } catch (Exception $e) {
        // Ignorar errores de status si la tabla no existe aún
        logWebhook("Status update failed (table may not exist): " . $e->getMessage());
    }
}

function findRelatedOrder($db, $phone) {
    $clean_phone = preg_replace('/[^0-9]/', '', $phone);
    
    try {
        // Buscar en órdenes online
        $query = "SELECT id FROM online_orders 
                  WHERE REPLACE(REPLACE(customer_phone, ' ', ''), '-', '') LIKE ? 
                  ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['%' . substr($clean_phone, -8) . '%']);
        $result = $stmt->fetch();
        
        if ($result) {
            return 'online_' . $result['id'];
        }
        
        // Buscar en órdenes tradicionales
        $query = "SELECT id FROM orders 
                  WHERE REPLACE(REPLACE(customer_phone, ' ', ''), '-', '') LIKE ? 
                  ORDER BY created_at DESC LIMIT 1";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['%' . substr($clean_phone, -8) . '%']);
        $result = $stmt->fetch();
        
        if ($result) {
            return 'traditional_' . $result['id'];
        }
    } catch (Exception $e) {
        logWebhook("Order lookup failed: " . $e->getMessage());
    }
    
    return null;
}

function processAutoResponse($db, $from, $content, $order_id) {
    try {
        $query = "SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_auto_responses'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $setting = $stmt->fetch();
        
        if (!$setting || $setting['setting_value'] !== '1') {
            return;
        }
    } catch (Exception $e) {
        return;
    }
    
    $content_lower = strtolower($content);
    
    if (strlen($content) > 500) {
        return; // No responder a mensajes muy largos
    }
    
    $auto_response = null;
    
    // Obtener configuración del restaurante usando la función centralizada
    $restaurant_settings = getRestaurantSettings();
    $restaurant_name = $restaurant_settings['restaurant_name'];
    $restaurant_web = $restaurant_settings['restaurant_web'];
    
    // Respuestas automáticas personalizadas
    if (strpos($content_lower, 'estado') !== false && $order_id) {
        $auto_response = getOrderStatus($db, $order_id);
    } elseif (strpos($content_lower, 'hola') !== false || strpos($content_lower, 'buenos') !== false) {
        $auto_response = "¡Hola! Gracias por contactar a {$restaurant_name}. Para realizar pedidos diríjase a {$restaurant_web}";
    } elseif (strpos($content_lower, 'pedido') !== false || strpos($content_lower, 'pedir') !== false || strpos($content_lower, 'orden') !== false) {
        $auto_response = "Para realizar un pedido diríjase a {$restaurant_web}";
    } elseif (strpos($content_lower, 'horarios') !== false || strpos($content_lower, 'horario') !== false) {
        $auto_response = getOperatingHours($db, $restaurant_name);
    } elseif (strpos($content_lower, 'direccion') !== false || strpos($content_lower, 'dirección') !== false || strpos($content_lower, 'ubicacion') !== false || strpos($content_lower, 'ubicación') !== false) {
        $auto_response = getRestaurantAddress($db, $restaurant_name);
    } elseif (strpos($content_lower, 'menu') !== false || strpos($content_lower, 'menú') !== false || strpos($content_lower, 'carta') !== false) {
        $auto_response = "Puede ver nuestro menú completo en {$restaurant_web}";
    } elseif (strpos($content_lower, 'telefono') !== false || strpos($content_lower, 'teléfono') !== false || strpos($content_lower, 'contacto') !== false) {
        $auto_response = getRestaurantContact($db, $restaurant_name);
    }
    
    if ($auto_response) {
        sendAutoResponse($db, $from, $auto_response);
    }
}

function getOperatingHours($db, $restaurant_name) {
    try {
        $query = "SELECT setting_key, setting_value FROM settings 
                  WHERE setting_key IN ('opening_time', 'closing_time')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $hours = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hours[$row['setting_key']] = $row['setting_value'];
        }
        
        $opening = $hours['opening_time'] ?? '08:00';
        $closing = $hours['closing_time'] ?? '23:00';
        
        return "Horarios de atención de {$restaurant_name}:\nLunes a Domingo: {$opening} - {$closing}";
        
    } catch (Exception $e) {
        return "Para conocer nuestros horarios, visite nuestro sitio web o contáctenos directamente.";
    }
}

function getRestaurantAddress($db, $restaurant_name) {
    try {
        $query = "SELECT setting_value FROM settings WHERE setting_key = 'restaurant_address'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $address = $result['setting_value'] ?? '';
        
        if (!empty($address)) {
            return "La dirección de {$restaurant_name} es:\n{$address}";
        } else {
            return "Para conocer nuestra ubicación, visite nuestro sitio web o contáctenos directamente.";
        }
        
    } catch (Exception $e) {
        return "Para conocer nuestra ubicación, visite nuestro sitio web o contáctenos directamente.";
    }
}

function getRestaurantContact($db, $restaurant_name) {
    try {
        $query = "SELECT setting_key, setting_value FROM settings 
                  WHERE setting_key IN ('restaurant_phone', 'restaurant_email')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $contacts = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (!empty($row['setting_value'])) {
                $contacts[$row['setting_key']] = $row['setting_value'];
            }
        }
        
        $response = "Información de contacto de {$restaurant_name}:\n";
        
        if (isset($contacts['restaurant_phone'])) {
            $response .= "📞 Teléfono: {$contacts['restaurant_phone']}\n";
        }
        
        if (isset($contacts['restaurant_email'])) {
            $response .= "📧 Email: {$contacts['restaurant_email']}\n";
        }
        
        if (empty($contacts)) {
            $response = "Para contactarnos, puede escribirnos por este medio o visitar nuestro sitio web.";
        }
        
        return trim($response);
        
    } catch (Exception $e) {
        return "Para contactarnos, puede escribirnos por este medio o visitar nuestro sitio web.";
    }
}

function getOrderStatus($db, $order_id) {
    try {
        if (strpos($order_id, 'online_') === 0) {
            $id = str_replace('online_', '', $order_id);
            $query = "SELECT status, order_number FROM online_orders WHERE id = ?";
        } else {
            $id = str_replace('traditional_', '', $order_id);
            $query = "SELECT status, order_number FROM orders WHERE id = ?";
        }
        
        $stmt = $db->prepare($query);
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        
        if ($order) {
            $status_texts = [
                'pending' => 'Pendiente de confirmación',
                'accepted' => 'Confirmado',
                'preparing' => 'En preparación',
                'ready' => 'Listo para entrega',
                'delivered' => 'Entregado'
            ];
            
            $status_text = $status_texts[$order['status']] ?? $order['status'];
            return "Su pedido #{$order['order_number']} está: {$status_text}";
        }
    } catch (Exception $e) {
        logWebhook("Error getting order status: " . $e->getMessage());
    }
    
    return "No encontramos información sobre su pedido. ¿Puede proporcionarnos el número de orden?";
}

function sendAutoResponse($db, $to, $message) {
    try {
        $query = "SELECT setting_key, setting_value FROM settings 
                  WHERE setting_key IN ('whatsapp_access_token', 'whatsapp_phone_number_id')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $settings = [];
        
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        $access_token = $settings['whatsapp_access_token'] ?? '';
        $phone_number_id = $settings['whatsapp_phone_number_id'] ?? '';
        
        if (empty($access_token) || empty($phone_number_id)) {
            logWebhook("Cannot send auto-response: missing credentials");
            return;
        }
        
        $clean_phone = cleanPhoneNumber($to);
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $clean_phone,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        $url = "https://graph.facebook.com/v18.0/$phone_number_id/messages";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            logWebhook("Auto-response sent to $to");
            
            // Guardar respuesta automática en la base de datos
            try {
                $response_data = json_decode($response, true);
                $message_id = $response_data['messages'][0]['id'] ?? 'auto_' . time();
                
                $insert_query = "INSERT INTO whatsapp_messages (
                    message_id, phone_number, message_type, content, 
                    is_from_customer, is_read, created_at
                ) VALUES (?, ?, 'text', ?, 0, 1, NOW())";
                
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([$message_id, $to, $message]);
                
            } catch (Exception $e) {
                logWebhook("Error saving auto-response to database: " . $e->getMessage());
            }
        } else {
            logWebhook("Failed to send auto-response: $response");
        }
        
    } catch (Exception $e) {
        logWebhook("Auto-response error: " . $e->getMessage());
    }
}

function cleanPhoneNumber($phone) {
    $clean = preg_replace('/[^0-9]/', '', $phone);
    $clean = ltrim($clean, '0');
    
    if (preg_match('/^549\d{9}$/', $clean)) {
        return $clean;
    }
    
    if (preg_match('/^549(\d{3})9(\d{6})$/', $clean, $matches)) {
        return '549' . $matches[1] . $matches[2];
    }
    
    if (preg_match('/^9?(\d{3})(\d{6})$/', $clean, $matches)) {
        return '549' . $matches[1] . $matches[2];
    }
    
    return $clean;
}

function createTablesIfNotExist($db) {
    try {
        $sql = "CREATE TABLE IF NOT EXISTS whatsapp_messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            message_id VARCHAR(255) UNIQUE NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            message_type ENUM('text', 'image', 'document', 'audio', 'video', 'location', 'contact') DEFAULT 'text',
            content TEXT,
            media_url VARCHAR(500),
            order_id VARCHAR(50),
            is_from_customer BOOLEAN DEFAULT 1,
            is_read BOOLEAN DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone (phone_number),
            INDEX idx_order (order_id),
            INDEX idx_created (created_at)
        )";
        
        $db->exec($sql);
        
        $sql_logs = "CREATE TABLE IF NOT EXISTS whatsapp_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone_number VARCHAR(20) NOT NULL,
            message_type VARCHAR(50) DEFAULT 'text',
            message_data TEXT,
            status ENUM('success', 'error') DEFAULT 'success',
            api_response TEXT,
            message_id VARCHAR(255),
            delivery_status VARCHAR(20),
            status_updated_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone (phone_number),
            INDEX idx_message_id (message_id),
            INDEX idx_created (created_at)
        )";
        
        $db->exec($sql_logs);
        
    } catch (Exception $e) {
        logWebhook("Error creating tables: " . $e->getMessage());
    }
}

// Si no es GET ni POST, error
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>