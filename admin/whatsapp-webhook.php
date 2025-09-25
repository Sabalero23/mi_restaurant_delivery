<?php
// admin/whatsapp-webhook.php - VERSIÓN CORREGIDA con descarga automática de multimedia
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

// Función para obtener configuración de WhatsApp API
function getWhatsAppCredentials() {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT setting_key, setting_value FROM settings 
                  WHERE setting_key IN ('whatsapp_access_token', 'whatsapp_phone_number_id')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        
        $credentials = ['access_token' => '', 'phone_number_id' => ''];
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row['setting_key'] === 'whatsapp_access_token') {
                $credentials['access_token'] = $row['setting_value'];
            } elseif ($row['setting_key'] === 'whatsapp_phone_number_id') {
                $credentials['phone_number_id'] = $row['setting_value'];
            }
        }
        
        return $credentials;
        
    } catch (Exception $e) {
        error_log("Error getting WhatsApp credentials: " . $e->getMessage());
        return ['access_token' => '', 'phone_number_id' => ''];
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

// Crear directorios de uploads si no existen
function ensureUploadDirectories() {
    $base_dir = '../uploads/whatsapp/';
    $directories = [
        $base_dir,
        $base_dir . 'images/',
        $base_dir . 'documents/',
        $base_dir . 'audio/',
        $base_dir . 'video/'
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
            logWebhook("Created directory: $dir");
        }
    }
}

// NUEVA FUNCIÓN: Descargar archivo multimedia desde WhatsApp
function downloadMediaFromWhatsApp($media_id, $access_token) {
    try {
        // Paso 1: Obtener URL del archivo
        $url = "https://graph.facebook.com/v18.0/$media_id";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            logWebhook("Failed to get media URL for $media_id: HTTP $http_code");
            return false;
        }
        
        $data = json_decode($response, true);
        if (!isset($data['url'])) {
            logWebhook("No URL in media response for $media_id");
            return false;
        }
        
        $file_url = $data['url'];
        $mime_type = $data['mime_type'] ?? 'application/octet-stream';
        $file_size = $data['file_size'] ?? 0;
        
        logWebhook("Got media URL for $media_id: $file_url (type: $mime_type)");
        
        // Paso 2: Descargar el archivo
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $file_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $file_data = curl_exec($ch);
        $download_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($download_http_code !== 200 || !$file_data) {
            logWebhook("Failed to download media file for $media_id: HTTP $download_http_code");
            return false;
        }
        
        // Paso 3: Guardar archivo
        $category = getMimeTypeCategory($mime_type);
        $extension = getExtensionFromMimeType($mime_type);
        $filename = $media_id . '_' . time() . '.' . $extension;
        $upload_dir = '../uploads/whatsapp/' . $category . '/';
        $file_path = $upload_dir . $filename;
        
        // Asegurar que el directorio existe
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        if (!file_put_contents($file_path, $file_data)) {
            logWebhook("Failed to save media file: $file_path");
            return false;
        }
        
        logWebhook("Media file saved successfully: $file_path (" . strlen($file_data) . " bytes)");
        
        return [
            'success' => true,
            'filename' => $filename,
            'file_path' => $file_path,
            'mime_type' => $mime_type,
            'file_size' => strlen($file_data),
            'category' => $category
        ];
        
    } catch (Exception $e) {
        logWebhook("Exception downloading media $media_id: " . $e->getMessage());
        return false;
    }
}

// Función auxiliar para determinar categoría desde MIME type
function getMimeTypeCategory($mime_type) {
    if (strpos($mime_type, 'image/') === 0) return 'images';
    if (strpos($mime_type, 'audio/') === 0) return 'audio';
    if (strpos($mime_type, 'video/') === 0) return 'video';
    return 'documents';
}

// Función auxiliar para obtener extensión desde MIME type
function getExtensionFromMimeType($mime_type) {
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/aac' => 'aac',
        'audio/ogg' => 'ogg',
        'video/mp4' => 'mp4',
        'video/3gpp' => '3gp',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'application/msword' => 'doc',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'
    ];
    
    return $extensions[$mime_type] ?? 'bin';
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
        ensureUploadDirectories();
        
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
    
    // Obtener credenciales de WhatsApp para descargas
    $credentials = getWhatsAppCredentials();
    $access_token = $credentials['access_token'];
    
    // Extraer el contenido del mensaje según el tipo
    $content = '';
    $media_url = null;
    $media_filename = null;
    $media_mime_type = null;
    $media_size = null;
    $media_caption = null;
    
    switch ($type) {
        case 'text':
            $content = $message['text']['body'];
            break;
            
        case 'image':
            $content = '[Imagen]';
            $media_url = $message['image']['id'];
            $media_caption = $message['image']['caption'] ?? '';
            
            // DESCARGAR IMAGEN AUTOMÁTICAMENTE
            if (!empty($access_token) && $media_url) {
                $download_result = downloadMediaFromWhatsApp($media_url, $access_token);
                if ($download_result && $download_result['success']) {
                    $media_filename = $download_result['filename'];
                    $media_mime_type = $download_result['mime_type'];
                    $media_size = $download_result['file_size'];
                    logWebhook("Image downloaded successfully: " . $media_filename);
                } else {
                    logWebhook("Failed to download image: " . $media_url);
                }
            }
            break;
            
        case 'document':
            $content = '[Documento]';
            $media_url = $message['document']['id'];
            $media_caption = $message['document']['caption'] ?? '';
            $original_filename = $message['document']['filename'] ?? 'documento';
            
            // DESCARGAR DOCUMENTO AUTOMÁTICAMENTE
            if (!empty($access_token) && $media_url) {
                $download_result = downloadMediaFromWhatsApp($media_url, $access_token);
                if ($download_result && $download_result['success']) {
                    $media_filename = $download_result['filename'];
                    $media_mime_type = $download_result['mime_type'];
                    $media_size = $download_result['file_size'];
                    $content = '[Documento: ' . $original_filename . ']';
                    logWebhook("Document downloaded successfully: " . $media_filename);
                } else {
                    logWebhook("Failed to download document: " . $media_url);
                }
            }
            break;
            
        case 'audio':
            $content = '[Audio]';
            $media_url = $message['audio']['id'];
            
            // DESCARGAR AUDIO AUTOMÁTICAMENTE
            if (!empty($access_token) && $media_url) {
                $download_result = downloadMediaFromWhatsApp($media_url, $access_token);
                if ($download_result && $download_result['success']) {
                    $media_filename = $download_result['filename'];
                    $media_mime_type = $download_result['mime_type'];
                    $media_size = $download_result['file_size'];
                    logWebhook("Audio downloaded successfully: " . $media_filename);
                } else {
                    logWebhook("Failed to download audio: " . $media_url);
                }
            }
            break;
            
        case 'video':
            $content = '[Video]';
            $media_url = $message['video']['id'];
            $media_caption = $message['video']['caption'] ?? '';
            
            // DESCARGAR VIDEO AUTOMÁTICAMENTE
            if (!empty($access_token) && $media_url) {
                $download_result = downloadMediaFromWhatsApp($media_url, $access_token);
                if ($download_result && $download_result['success']) {
                    $media_filename = $download_result['filename'];
                    $media_mime_type = $download_result['mime_type'];
                    $media_size = $download_result['file_size'];
                    logWebhook("Video downloaded successfully: " . $media_filename);
                } else {
                    logWebhook("Failed to download video: " . $media_url);
                }
            }
            break;
            
        default:
            $content = "[Mensaje de tipo: $type]";
    }
    
    // Si hay caption, agregarlo al contenido
    if (!empty($media_caption)) {
        $content .= "\n" . $media_caption;
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
        
        // Guardar mensaje en la base de datos CON INFORMACIÓN DE MULTIMEDIA
        $query = "INSERT INTO whatsapp_messages (
            message_id, phone_number, message_type, content, media_url, 
            media_filename, media_mime_type, media_size, 
            order_id, is_from_customer, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, FROM_UNIXTIME(?))";
        
        $stmt = $db->prepare($query);
        $stmt->execute([
            $message_id,
            $from,
            $type,
            $content,
            $media_url,
            $media_filename,
            $media_mime_type,
            $media_size,
            $order_id,
            $timestamp
        ]);
        
        logWebhook("Message saved: From $from, Type: $type, Content: " . substr($content, 0, 50) . 
                   ($media_filename ? ", Media: $media_filename" : ""));
        
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
        $credentials = getWhatsAppCredentials();
        $access_token = $credentials['access_token'];
        $phone_number_id = $credentials['phone_number_id'];
        
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
        // Primero, verificar si la tabla existe y tiene las columnas necesarias
        $check_table = "SHOW TABLES LIKE 'whatsapp_messages'";
        $result = $db->query($check_table);
        
        if ($result->rowCount() === 0) {
            // Tabla no existe, crearla completa
            $sql = "CREATE TABLE whatsapp_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id VARCHAR(255) UNIQUE NOT NULL,
                phone_number VARCHAR(20) NOT NULL,
                message_type ENUM('text', 'image', 'document', 'audio', 'video', 'sticker', 'location', 'contact') DEFAULT 'text',
                content MEDIUMTEXT,
                media_url VARCHAR(500),
                order_id VARCHAR(50),
                is_from_customer TINYINT(1) DEFAULT 1,
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                media_filename VARCHAR(255),
                media_mime_type VARCHAR(100),
                media_size INT,
                INDEX idx_phone (phone_number),
                INDEX idx_order (order_id),
                INDEX idx_unread (is_read),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->exec($sql);
            logWebhook("Created whatsapp_messages table with multimedia support");
        } else {
            // Tabla existe, verificar/agregar columnas multimedia
            $columns_to_add = [
                'media_filename' => 'ALTER TABLE whatsapp_messages ADD COLUMN media_filename VARCHAR(255) DEFAULT NULL',
                'media_mime_type' => 'ALTER TABLE whatsapp_messages ADD COLUMN media_mime_type VARCHAR(100) DEFAULT NULL', 
                'media_size' => 'ALTER TABLE whatsapp_messages ADD COLUMN media_size INT DEFAULT NULL'
            ];
            
            foreach ($columns_to_add as $column => $alter_sql) {
                try {
                    $check_column = "SHOW COLUMNS FROM whatsapp_messages LIKE '$column'";
                    $column_result = $db->query($check_column);
                    
                    if ($column_result->rowCount() === 0) {
                        $db->exec($alter_sql);
                        logWebhook("Added column $column to whatsapp_messages table");
                    }
                } catch (Exception $e) {
                    logWebhook("Error adding column $column: " . $e->getMessage());
                }
            }
        }
        
        // Tabla de logs
        $sql_logs = "CREATE TABLE IF NOT EXISTS whatsapp_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone_number VARCHAR(20) NOT NULL,
            message_type VARCHAR(50) DEFAULT 'text',
            message_data MEDIUMTEXT,
            status ENUM('success', 'error', 'pending') DEFAULT 'pending',
            api_response MEDIUMTEXT,
            message_id VARCHAR(255),
            delivery_status ENUM('sent','delivered','read','failed') DEFAULT 'sent',
            status_updated_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone (phone_number),
            INDEX idx_message_id (message_id),
            INDEX idx_status (delivery_status),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql_logs);
        
        // Tabla de respuestas automáticas
        $sql_responses = "CREATE TABLE IF NOT EXISTS whatsapp_auto_responses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            trigger_words TEXT NOT NULL COMMENT 'Palabras que disparan la respuesta (separadas por coma)',
            response_message TEXT NOT NULL COMMENT 'Mensaje de respuesta automática',
            match_type ENUM('contains','exact','starts_with','ends_with') DEFAULT 'contains' COMMENT 'Tipo de coincidencia',
            is_active TINYINT(1) DEFAULT 1 COMMENT 'Si la respuesta está activa',
            priority INT DEFAULT 0 COMMENT 'Prioridad de la respuesta (mayor número = mayor prioridad)',
            use_count INT DEFAULT 0 COMMENT 'Contador de veces que se ha usado',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_active (is_active),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql_responses);
        
        // Tabla para uploads de multimedia (opcional, para tracking)
        $sql_uploads = "CREATE TABLE IF NOT EXISTS whatsapp_media_uploads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_filename VARCHAR(255) NOT NULL,
            stored_filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(100) NOT NULL,
            file_size INT NOT NULL,
            upload_path VARCHAR(500) NOT NULL,
            whatsapp_media_id VARCHAR(255),
            uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_sent TINYINT(1) DEFAULT 0,
            sent_at TIMESTAMP NULL,
            INDEX idx_media_id (whatsapp_media_id),
            INDEX idx_uploaded (uploaded_at)
        )";
        
        $db->exec($sql_uploads);
        
    } catch (Exception $e) {
        logWebhook("Error creating tables: " . $e->getMessage());
    }
}

// Si no es GET ni POST, error
http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
?>