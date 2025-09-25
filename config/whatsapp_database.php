<?php
// config/whatsapp_database.php - Gestor específico para WhatsApp
class WhatsAppDatabase {
    private static $connection = null;
    private static $last_used = null;
    private static $timeout = 60; // 1 minuto de timeout
    
    /**
     * Obtener configuraciones sin usar conexión a BD
     */
    public static function getSettingsFromFile() {
        // Cache en archivo para evitar consultas BD constantes
        $cache_file = __DIR__ . '/whatsapp_settings_cache.json';
        $cache_ttl = 300; // 5 minutos
        
        if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
            $cached = json_decode(file_get_contents($cache_file), true);
            if ($cached) {
                return $cached;
            }
        }
        
        // Si no hay cache válido, usar configuración por defecto
        $default_settings = [
            'restaurant_name' => 'Mi Restaurante',
            'whatsapp_access_token' => '',
            'whatsapp_phone_number_id' => '',
            'whatsapp_webhook_token' => 'whatsapp-webhook-comias',
            'whatsapp_auto_responses' => '0'
        ];
        
        // Intentar obtener de BD una sola vez
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, 
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5, // Timeout corto
                    PDO::ATTR_PERSISTENT => false
                ]
            );
            
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'whatsapp%' OR setting_key = 'restaurant_name'");
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            foreach ($results as $row) {
                $default_settings[$row['setting_key']] = $row['setting_value'];
            }
            
            // Guardar cache
            file_put_contents($cache_file, json_encode($default_settings));
            
            $pdo = null; // Cerrar conexión
            
        } catch (Exception $e) {
            error_log("WhatsApp settings error: " . $e->getMessage());
        }
        
        return $default_settings;
    }
    
    /**
     * Ejecutar query de forma segura con timeout
     */
    public static function safeQuery($query, $params = []) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, 
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 10,
                    PDO::ATTR_PERSISTENT => false
                ]
            );
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            $result = $stmt;
            $pdo = null; // Cerrar conexión inmediatamente
            
            return $result;
            
        } catch (Exception $e) {
            error_log("WhatsApp DB query error: " . $e->getMessage());
            throw new Exception("Error de base de datos");
        }
    }
    
    /**
     * Insertar mensaje de WhatsApp
     */
    /**
 * Insertar mensaje de WhatsApp con soporte completo para multimedia
 */
public static function insertMessage($data) {
    $query = "INSERT INTO whatsapp_messages 
        (message_id, phone_number, message_type, content, media_url, media_filename, media_mime_type, media_size, media_caption, is_from_customer, is_read, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
    return self::safeQuery($query, [
        $data['message_id'],
        $data['phone_number'],
        $data['message_type'],
        $data['content'],
        $data['media_url'] ?? null,
        $data['media_filename'] ?? null,
        $data['media_mime_type'] ?? null,
        $data['media_size'] ?? null,
        $data['media_caption'] ?? null, // Nuevo campo
        $data['is_from_customer'],
        $data['is_read']
    ]);
}

/**
 * Crear las tablas con el campo media_caption si no existe
 */
public static function ensureMediaCaptionColumn() {
    try {
        // Verificar si existe la columna media_caption
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER, 
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 10
            ]
        );
        
        $check_column = "SHOW COLUMNS FROM whatsapp_messages LIKE 'media_caption'";
        $result = $pdo->query($check_column);
        
        if ($result->rowCount() === 0) {
            $alter_sql = "ALTER TABLE whatsapp_messages ADD COLUMN media_caption TEXT DEFAULT NULL AFTER media_size";
            $pdo->exec($alter_sql);
            error_log("Added media_caption column to whatsapp_messages table");
        }
        
        $pdo = null;
        
    } catch (Exception $e) {
        error_log("Error ensuring media_caption column: " . $e->getMessage());
    }
}
    
    /**
     * Log de envío de WhatsApp
     */
    public static function logSend($data) {
        try {
            $query = "INSERT INTO whatsapp_logs (phone_number, message_type, message_data, status, api_response, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())";
                     
            self::safeQuery($query, [
                $data['to'],
                $data['type'] ?? 'text',
                json_encode($data),
                $data['status'],
                json_encode($data['response'])
            ]);
        } catch (Exception $e) {
            error_log("WhatsApp log error: " . $e->getMessage());
            // No lanzar excepción para logging
        }
    }
    
    /**
     * Limpiar cache de configuraciones
     */
    public static function clearSettingsCache() {
        $cache_file = __DIR__ . '/whatsapp_settings_cache.json';
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
    }
}
?>