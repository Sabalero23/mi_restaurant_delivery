<?php
// config/whatsapp_api.php - WhatsApp Business API con soporte multimedia

class WhatsAppAPI {
    private $access_token;
    private $phone_number_id;
    private $api_version = 'v18.0';
    private $base_url = 'https://graph.facebook.com';
    private $upload_dir = '../uploads/whatsapp/';
    
    public function __construct() {
        // Obtener configuraciones desde la base de datos
        $settings = getSettings();
        $this->access_token = $settings['whatsapp_access_token'] ?? '';
        $this->phone_number_id = $settings['whatsapp_phone_number_id'] ?? '';
        
        // Crear directorio de uploads si no existe
        $this->ensureUploadDirectories();
    }
    
    /**
     * Enviar mensaje de texto
     */
    public function sendTextMessage($to, $message) {
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
     * Enviar imagen
     */
    public function sendImageMessage($to, $media_id_or_url, $caption = '') {
        $to = $this->cleanPhoneNumber($to);
        
        $image_data = [
            'id' => $media_id_or_url
        ];
        
        // Si es URL en lugar de media_id
        if (filter_var($media_id_or_url, FILTER_VALIDATE_URL)) {
            $image_data = ['link' => $media_id_or_url];
        }
        
        if (!empty($caption)) {
            $image_data['caption'] = $caption;
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => $image_data
        ];
        
        return $this->sendRequest('messages', $data);
    }
    
    /**
     * Enviar documento
     */
    public function sendDocumentMessage($to, $media_id_or_url, $filename = '', $caption = '') {
        $to = $this->cleanPhoneNumber($to);
        
        $document_data = [
            'id' => $media_id_or_url
        ];
        
        // Si es URL en lugar de media_id
        if (filter_var($media_id_or_url, FILTER_VALIDATE_URL)) {
            $document_data = ['link' => $media_id_or_url];
        }
        
        if (!empty($filename)) {
            $document_data['filename'] = $filename;
        }
        
        if (!empty($caption)) {
            $document_data['caption'] = $caption;
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'document',
            'document' => $document_data
        ];
        
        return $this->sendRequest('messages', $data);
    }
    
    /**
     * Enviar audio
     */
    public function sendAudioMessage($to, $media_id_or_url) {
        $to = $this->cleanPhoneNumber($to);
        
        $audio_data = [
            'id' => $media_id_or_url
        ];
        
        // Si es URL en lugar de media_id
        if (filter_var($media_id_or_url, FILTER_VALIDATE_URL)) {
            $audio_data = ['link' => $media_id_or_url];
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'audio',
            'audio' => $audio_data
        ];
        
        return $this->sendRequest('messages', $data);
    }
    
    /**
     * Subir archivo multimedia a WhatsApp
     */
    public function uploadMedia($file_path, $type = 'auto') {
        if (!file_exists($file_path)) {
            return [
                'success' => false,
                'error' => 'File not found: ' . $file_path
            ];
        }
        
        // Detectar tipo automáticamente
        if ($type === 'auto') {
            $mime_type = mime_content_type($file_path);
            $type = $this->getMimeTypeCategory($mime_type);
        }
        
        $url = $this->base_url . '/' . $this->api_version . '/' . $this->phone_number_id . '/media';
        
        $post_data = [
            'messaging_product' => 'whatsapp',
            'type' => $type,
            'file' => new CURLFile($file_path)
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_data,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->access_token
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'Upload error: ' . $error
            ];
        }
        
        $decoded = json_decode($response, true);
        
        if ($http_code === 200 && isset($decoded['id'])) {
            return [
                'success' => true,
                'media_id' => $decoded['id'],
                'response' => $decoded
            ];
        } else {
            return [
                'success' => false,
                'error' => $decoded['error']['message'] ?? 'Upload failed',
                'response' => $decoded
            ];
        }
    }
    
    /**
     * Descargar multimedia desde WhatsApp
     */
    public function downloadMedia($media_id) {
        // Paso 1: Obtener URL del archivo
        $url = $this->base_url . '/' . $this->api_version . '/' . $media_id;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->access_token
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            return [
                'success' => false,
                'error' => 'Failed to get media URL'
            ];
        }
        
        $data = json_decode($response, true);
        if (!isset($data['url'])) {
            return [
                'success' => false,
                'error' => 'No URL in response'
            ];
        }
        
        $file_url = $data['url'];
        $mime_type = $data['mime_type'] ?? 'application/octet-stream';
        $file_size = $data['file_size'] ?? 0;
        
        // Paso 2: Descargar el archivo
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $file_url,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->access_token
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 60
        ]);
        
        $file_data = curl_exec($ch);
        $download_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($download_http_code !== 200 || !$file_data) {
            return [
                'success' => false,
                'error' => 'Failed to download file'
            ];
        }
        
        // Paso 3: Guardar archivo
        $category = $this->getMimeTypeCategory($mime_type);
        $extension = $this->getExtensionFromMimeType($mime_type);
        $filename = $media_id . '_' . time() . '.' . $extension;
        $file_path = $this->upload_dir . $category . '/' . $filename;
        
        if (!file_put_contents($file_path, $file_data)) {
            return [
                'success' => false,
                'error' => 'Failed to save file'
            ];
        }
        
        return [
            'success' => true,
            'file_path' => $file_path,
            'filename' => $filename,
            'mime_type' => $mime_type,
            'file_size' => $file_size,
            'category' => $category
        ];
    }
    
    /**
     * Limpiar número de teléfono - Corregido para Argentina
     */
    private function cleanPhoneNumber($phone) {
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
            $this->logWhatsAppSend($data['to'], $data, 'success', $decoded);
            
            return [
                'success' => true,
                'message_id' => $decoded['messages'][0]['id'] ?? null,
                'response' => $decoded
            ];
        } else {
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
     * Crear directorios de upload
     */
    private function ensureUploadDirectories() {
        $directories = [
            $this->upload_dir,
            $this->upload_dir . 'images/',
            $this->upload_dir . 'documents/',
            $this->upload_dir . 'audio/',
            $this->upload_dir . 'video/',
            $this->upload_dir . 'thumbnails/'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
    
    /**
     * Obtener categoría desde tipo MIME
     */
    private function getMimeTypeCategory($mime_type) {
        if (strpos($mime_type, 'image/') === 0) return 'images';
        if (strpos($mime_type, 'audio/') === 0) return 'audio';
        if (strpos($mime_type, 'video/') === 0) return 'video';
        return 'documents';
    }
    
    /**
     * Obtener extensión desde tipo MIME
     */
    private function getExtensionFromMimeType($mime_type) {
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
    
    /**
     * Validar archivo multimedia
     */
    public function validateMediaFile($file_path, $type = 'auto') {
        if (!file_exists($file_path)) {
            return ['valid' => false, 'error' => 'Archivo no encontrado'];
        }
        
        $file_size = filesize($file_path);
        $mime_type = mime_content_type($file_path);
        
        if ($type === 'auto') {
            $type = $this->getMimeTypeCategory($mime_type);
        }
        
        // Límites de tamaño por tipo
        $size_limits = [
            'images' => 5 * 1024 * 1024,    // 5MB
            'documents' => 100 * 1024 * 1024, // 100MB  
            'audio' => 16 * 1024 * 1024,     // 16MB
            'video' => 16 * 1024 * 1024      // 16MB
        ];
        
        $allowed_types = [
            'images' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
            'documents' => [
                'application/pdf', 'text/plain', 'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            ],
            'audio' => ['audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg'],
            'video' => ['video/mp4', 'video/3gpp']
        ];
        
        // Validar tipo MIME
        if (!in_array($mime_type, $allowed_types[$type] ?? [])) {
            return [
                'valid' => false, 
                'error' => "Tipo de archivo no permitido: $mime_type para categoría $type"
            ];
        }
        
        // Validar tamaño
        if ($file_size > ($size_limits[$type] ?? 1024 * 1024)) {
            $limit_mb = round(($size_limits[$type] ?? 1024 * 1024) / 1024 / 1024, 1);
            return [
                'valid' => false,
                'error' => "Archivo muy grande. Límite para $type: {$limit_mb}MB"
            ];
        }
        
        return [
            'valid' => true,
            'mime_type' => $mime_type,
            'file_size' => $file_size,
            'category' => $type
        ];
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
            'has_phone_id' => !empty($this->phone_number_id),
            'upload_dir_writable' => is_writable($this->upload_dir)
        ];
    }
}
?>