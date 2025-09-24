<?php
// config/whatsapp_api.php - ConfiguraciÃ³n WhatsApp Business API - VERSIÓN CORREGIDA

class WhatsAppAPI {
    private $access_token;
    private $phone_number_id;
    private $api_version = 'v18.0';
    private $base_url = 'https://graph.facebook.com';
    
    public function __construct() {
        // Obtener configuraciones desde la base de datos o config
        $settings = getSettings();
        $this->access_token = $settings['whatsapp_access_token'] ?? '';
        $this->phone_number_id = $settings['whatsapp_phone_number_id'] ?? '';
    }
    
    /**
     * Enviar mensaje de texto
     */
    public function sendTextMessage($to, $message) {
        // Limpiar número de teléfono
        $to = $this->cleanPhoneNumber($to);
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        return $this->sendRequest('messages', $data);
    }
    
    /**
     * Enviar mensaje con template (opcional para el futuro)
     */
    public function sendTemplateMessage($to, $template_name, $parameters = []) {
        $to = $this->cleanPhoneNumber($to);
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $template_name,
                'language' => [
                    'code' => 'es_AR'
                ]
            ]
        ];
        
        if (!empty($parameters)) {
            $data['template']['components'] = [
                [
                    'type' => 'body',
                    'parameters' => $parameters
                ]
            ];
        }
        
        return $this->sendRequest('messages', $data);
    }
    
    /**
     * Limpiar número de teléfono - Corregido para Argentina
     */
    private function cleanPhoneNumber($phone) {
        // Remover todo excepto números
        $clean = preg_replace('/[^0-9]/', '', $phone);
        
        // Remover 0 inicial si existe
        $clean = ltrim($clean, '0');
        
        // Para Argentina: convertir a formato 549XXXXXXXXX
        
        // Si ya tiene formato correcto
        if (preg_match('/^549\d{9}$/', $clean)) {
            return $clean;
        }
        
        // Corregir formato con 9 duplicado: 5493482599994 -> 549348259994
        if (preg_match('/^549(\d{3})9(\d{6})$/', $clean, $matches)) {
            return '549' . $matches[1] . $matches[2];
        }
        
        // Sin código de país: 3482599994 -> 549348259994
        if (preg_match('/^9?(\d{3})(\d{6})$/', $clean, $matches)) {
            return '549' . $matches[1] . $matches[2];
        }
        
        // Con 54 pero formato incorrecto
        if (preg_match('/^54(\d+)$/', $clean, $matches)) {
            $remaining = $matches[1];
            if (preg_match('/^9?(\d{3})(\d{6})$/', $remaining, $submatches)) {
                return '549' . $submatches[1] . $submatches[2];
            }
        }
        
        return $clean;
    }
    
    /**
     * Realizar petición HTTP a la API
     */
    private function sendRequest($endpoint, $data) {
        if (empty($this->access_token) || empty($this->phone_number_id)) {
            return [
                'success' => false,
                'error' => 'API no configurada. Falta access_token o phone_number_id'
            ];
        }
        
        $url = $this->base_url . '/' . $this->api_version . '/' . $this->phone_number_id . '/' . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->access_token,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'Error de conexión: ' . $error
            ];
        }
        
        $decoded = json_decode($response, true);
        
        if ($http_code === 200 && isset($decoded['messages'])) {
            // Log del envío exitoso
            $this->logWhatsAppSend($data['to'], $data, 'success', $decoded);
            
            return [
                'success' => true,
                'message_id' => $decoded['messages'][0]['id'] ?? null,
                'response' => $decoded
            ];
        } else {
            // Log del error
            $error_message = $decoded['error']['message'] ?? 'Error desconocido';
            $this->logWhatsAppSend($data['to'], $data, 'error', $decoded);
            
            return [
                'success' => false,
                'error' => $error_message,
                'response' => $decoded
            ];
        }
    }
    
    /**
     * Registrar envío en la base de datos
     */
    private function logWhatsAppSend($to, $data, $status, $response) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "INSERT INTO whatsapp_logs (phone_number, message_type, message_data, status, api_response, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())";
            
            $stmt = $db->prepare($query);
            $stmt->execute([
                $to,
                $data['type'] ?? 'text',
                json_encode($data),
                $status,
                json_encode($response)
            ]);
        } catch (Exception $e) {
            error_log('Error logging WhatsApp send: ' . $e->getMessage());
        }
    }
    
    /**
     * Verificar si la API está configurada
     */
    public function isConfigured() {
        return !empty($this->access_token) && !empty($this->phone_number_id);
    }
    
    /**
     * Obtener estado de la configuración
     */
    public function getConfigStatus() {
        return [
            'configured' => $this->isConfigured(),
            'has_token' => !empty($this->access_token),
            'has_phone_id' => !empty($this->phone_number_id)
        ];
    }
}

// TODAS LAS FUNCIONES HELPER ESTÁN EN online-orders.php PARA EVITAR DUPLICADOS
// ESTE ARCHIVO SOLO CONTIENE LA CLASE WhatsAppAPI
?>