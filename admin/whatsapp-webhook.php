<?php
// admin/whatsapp-webhook.php - Versión completa con respuestas automáticas desde base de datos
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
                  WHERE setting_key IN (
                      'restaurant_name', 'restaurant_web', 'restaurant_phone', 
                      'restaurant_email', 'restaurant_address', 'opening_time', 
                      'closing_time', 'delivery_fee', 'min_delivery_amount'
                  )";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $settings = [
            'restaurant_name' => 'Mi Restaurante',
            'restaurant_web' => 'https://comidas.ordenes.com.ar',
            'restaurant_phone' => '',
            'restaurant_email' => '',
            'restaurant_address' => '',
            'opening_time' => '08:00',
            'closing_time' => '23:00',
            'delivery_fee' => '3000',
            'min_delivery_amount' => '1500'
        ];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
        
    } catch (Exception $e) {
        error_log("Error getting restaurant settings: " . $e->getMessage());
        return [
            'restaurant_name' => 'Mi Restaurante',
            'restaurant_web' => 'https://comidas.ordenes.com.ar',
            'restaurant_phone' => '',
            'restaurant_email' => '',
            'restaurant_address' => '',
            'opening_time' => '08:00',
            'closing_time' => '23:00',
            'delivery_fee' => '3000',
            'min_delivery_amount' => '1500'
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
    
    logWebhookDetailed('GET request received');
    
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    logWebhook("GET Request - Mode: '$mode', Token: '$token', Expected: '$verify_token', Challenge: '$challenge'");
    
    if ($mode === 'subscribe' && $token === $verify_token) {
        logWebhook("Verification successful - sending challenge: $challenge");
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
    
    http_response_code(200);
    echo json_encode(['status' => 'ok']);
    exit;
}

function processIncomingMessage($value) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        createTablesIfNotExist($db);
        
        if (isset($value['messages'])) {
            foreach ($value['messages'] as $message) {
                handleIncomingMessage($db, $message);
            }
        }
        
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
        // Verificar duplicados
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

/**
 * Procesar respuestas automáticas desde la base de datos
 */
function processAutoResponse($db, $from, $content, $order_id) {
    try {
        // Verificar si las respuestas automáticas están habilitadas
        $settings_query = "SELECT setting_value FROM settings WHERE setting_key = 'whatsapp_auto_responses'";
        $settings_stmt = $db->prepare($settings_query);
        $settings_stmt->execute();
        $setting = $settings_stmt->fetch();
        
        if (!$setting || $setting['setting_value'] !== '1') {
            logWebhook("Auto responses disabled");
            return;
        }
        
        // No responder a mensajes muy largos
        if (strlen($content) > 500) {
            logWebhook("Message too long, skipping auto response");
            return;
        }
        
        // Verificar rate limiting simple (no más de 1 respuesta por minuto por número)
        if (!checkRateLimit($db, $from)) {
            logWebhook("Rate limit exceeded for $from");
            return;
        }
        
        // Obtener respuestas automáticas de la base de datos, ordenadas por prioridad
        $responses_query = "SELECT * FROM whatsapp_auto_responses 
                           WHERE is_active = 1 
                           ORDER BY priority DESC, created_at ASC";
        $responses_stmt = $db->prepare($responses_query);
        $responses_stmt->execute();
        $responses = $responses_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($responses)) {
            logWebhook("No auto responses configured");
            return;
        }
        
        $content_lower = strtolower(trim($content));
        $matched_response = null;
        
        // Buscar la primera respuesta que coincida
        foreach ($responses as $response) {
            if (messageMatchesResponse($content_lower, $response)) {
                $matched_response = $response;
                break;
            }
        }
        
        if ($matched_response) {
            // Incrementar contador de uso
            $update_counter_query = "UPDATE whatsapp_auto_responses 
                                   SET use_count = use_count + 1, updated_at = NOW() 
                                   WHERE id = ?";
            $update_counter_stmt = $db->prepare($update_counter_query);
            $update_counter_stmt->execute([$matched_response['id']]);
            
            // Generar respuesta reemplazando variables
            $response_message = replaceVariablesInResponse($matched_response['response_message'], $db, $order_id);
            
            // Enviar respuesta automática
            sendAutoResponse($db, $from, $response_message);
            
            logWebhook("Auto response sent for rule ID {$matched_response['id']}: " . 
                      substr($response_message, 0, 100));
        } else {
            logWebhook("No matching auto response found for: " . substr($content, 0, 100));
        }
        
    } catch (Exception $e) {
        logWebhook("Auto response error: " . $e->getMessage());
    }
}

/**
 * Rate limiting simple - máximo 1 respuesta automática por minuto por número
 */
function checkRateLimit($db, $phone) {
    try {
        $one_minute_ago = date('Y-m-d H:i:s', strtotime('-1 minute'));
        
        $query = "SELECT COUNT(*) as count FROM whatsapp_messages 
                  WHERE phone_number = ? 
                  AND is_from_customer = 0 
                  AND created_at > ?";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$phone, $one_minute_ago]);
        $result = $stmt->fetch();
        
        return $result['count'] < 1; // Máximo 1 respuesta por minuto
        
    } catch (Exception $e) {
        logWebhook("Rate limit check error: " . $e->getMessage());
        return true; // En caso de error, permitir envío
    }
}

/**
 * Verificar si un mensaje coincide con una respuesta automática
 */
function messageMatchesResponse($content_lower, $response) {
    $trigger_words = explode(',', strtolower($response['trigger_words']));
    $match_type = $response['match_type'];
    
    foreach ($trigger_words as $trigger_word) {
        $trigger_word = trim($trigger_word);
        
        if (empty($trigger_word)) {
            continue;
        }
        
        switch ($match_type) {
            case 'contains':
                if (strpos($content_lower, $trigger_word) !== false) {
                    return true;
                }
                break;
                
            case 'exact':
                if ($content_lower === $trigger_word) {
                    return true;
                }
                break;
                
            case 'starts_with':
                if (strpos($content_lower, $trigger_word) === 0) {
                    return true;
                }
                break;
                
            case 'ends_with':
                if (substr($content_lower, -strlen($trigger_word)) === $trigger_word) {
                    return true;
                }
                break;
        }
    }
    
    return false;
}

/**
 * Reemplazar variables en el mensaje de respuesta
 */
function replaceVariablesInResponse($message, $db, $order_id = null) {
    // Obtener configuraciones del restaurante
    $restaurant_settings = getRestaurantSettings();
    
    // Variables básicas del restaurante
    $variables = [
        '{restaurant_name}' => $restaurant_settings['restaurant_name'],
        '{restaurant_web}' => $restaurant_settings['restaurant_web'],
        '{restaurant_phone}' => $restaurant_settings['restaurant_phone'],
        '{restaurant_email}' => $restaurant_settings['restaurant_email'],
        '{restaurant_address}' => $restaurant_settings['restaurant_address'],
        '{opening_time}' => $restaurant_settings['opening_time'],
        '{closing_time}' => $restaurant_settings['closing_time'],
        '{delivery_fee}' => '$' . number_format($restaurant_settings['delivery_fee'], 0, ',', '.'),
        '{min_delivery_amount}' => '$' . number_format($restaurant_settings['min_delivery_amount'], 0, ',', '.')
    ];
    
    // Si hay un pedido asociado, agregar información específica
    if ($order_id) {
        $order_info = getOrderInfo($db, $order_id);
        if ($order_info) {
            $variables['{order_number}'] = $order_info['order_number'] ?? '';
            $variables['{order_status}'] = $order_info['status_text'] ?? '';
            $variables['{order_total}'] = '$' . number_format($order_info['total'] ?? 0, 0, ',', '.');
        }
    }
    
    // Reemplazar todas las variables
    return str_replace(array_keys($variables), array_values($variables), $message);
}

/**
 * Obtener información de un pedido
 */
function getOrderInfo($db, $order_id) {
    try {
        if (strpos($order_id, 'online_') === 0) {
            $id = str_replace('online_', '', $order_id);
            $query = "SELECT order_number, status, total FROM online_orders WHERE id = ?";
        } else {
            $id = str_replace('traditional_', '', $order_id);
            $query = "SELECT order_number, status, total FROM orders WHERE id = ?";
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
            
            return [
                'order_number' => $order['order_number'],
                'status_text' => $status_texts[$order['status']] ?? $order['status'],
                'total' => $order['total']
            ];
        }
    } catch (Exception $e) {
        logWebhook("Error getting order info: " . $e->getMessage());
    }
    
    return null;
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
            
            // IMPORTANTE: Guardar respuesta automática como mensaje en la conversación
            try {
                $response_data = json_decode($response, true);
                $message_id = $response_data['messages'][0]['id'] ?? 'auto_' . time() . '_' . rand(1000, 9999);
                
                $insert_query = "INSERT INTO whatsapp_messages (
                    message_id, phone_number, message_type, content, 
                    is_from_customer, is_read, created_at
                ) VALUES (?, ?, 'text', ?, 0, 1, NOW())";
                
                $insert_stmt = $db->prepare($insert_query);
                $insert_stmt->execute([$message_id, $to, $message]);
                
                logWebhook("Auto-response saved to conversation: $message_id");
                
            } catch (Exception $e) {
                logWebhook("Error saving auto-response to database: " . $e->getMessage());
            }
        } else {
            logWebhook("Failed to send auto-response: HTTP $http_code - $response");
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
        // Tabla de mensajes
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
        
        // Tabla de logs
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
        
        // Tabla de respuestas automáticas
        $sql_responses = "CREATE TABLE IF NOT EXISTS whatsapp_auto_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            trigger_words TEXT NOT NULL,
            response_message TEXT NOT NULL,
            match_type ENUM('contains','exact','starts_with','ends_with') DEFAULT 'contains',
            is_active TINYINT(1) DEFAULT 1,
            priority INT DEFAULT 0,
            use_count INT DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_priority (priority)
        )";
        
        $db->exec($sql_responses);
        
    } catch (Exception $e) {
        logWebhook("Error creating tables: " . $e->getMessage());
    }
}

// Si no es GET ni POST, error
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>