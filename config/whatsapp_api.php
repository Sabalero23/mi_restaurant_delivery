<?php
// config/whatsapp_api.php - WhatsApp Business API con soporte multimedia - VERSIÓN CORREGIDA

class WhatsAppAPI {
    private $access_token;
    private $phone_number_id;
    private $api_version = 'v18.0';
    private $base_url = 'https://graph.facebook.com';
    private $upload_dir = '../uploads/whatsapp/';
    
    public function __construct() {
        // Usar el gestor específico de WhatsApp para evitar problemas de conexión
        require_once __DIR__ . '/whatsapp_database.php';
        $settings = WhatsAppDatabase::getSettingsFromFile();
        
        $this->access_token = $settings['whatsapp_access_token'] ?? '';
        $this->phone_number_id = $settings['whatsapp_phone_number_id'] ?? '';
        
        // Crear directorio de uploads si no existe
        $this->ensureUploadDirectories();
    }
    
    /**
     * Obtener configuraciones de forma segura (manejo de errores de BD)
     */
    private function getSettingsSafe() {
        try {
            return getSettings();
        } catch (Exception $e) {
            error_log("WhatsApp API: Error loading settings - " . $e->getMessage());
            // Devolver configuración por defecto o vacía
            return [
                'whatsapp_access_token' => '',
                'whatsapp_phone_number_id' => ''
            ];
        }
    }
    
    /**
     * Función alternativa para detectar tipo MIME cuando mime_content_type() no está disponible
     */
    private function getMimeType($file_path) {
        // Obtener extensión primero para validación
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        // Método 1: Usar finfo si está disponible
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $mime = finfo_file($finfo, $file_path);
                finfo_close($finfo);
                
                // Validar que el MIME detectado sea consistente con la extensión
                if ($mime && $mime !== 'application/octet-stream') {
                    return $mime;
                }
            }
        }
        
        // Método 2: mime_content_type si está disponible
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($file_path);
            if ($mime && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }
        
        // Método 3: Lectura de headers del archivo
        $mime_from_header = $this->getMimeFromFileHeader($file_path);
        if ($mime_from_header) {
            return $mime_from_header;
        }
        
        // Método 4: Basarse en la extensión (fallback confiable)
        $mime_types = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'bmp' => 'image/bmp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            'rtf' => 'application/rtf',
            'mp3' => 'audio/mpeg',
            'm4a' => 'audio/mp4',
            'aac' => 'audio/aac',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'mp4' => 'video/mp4',
            '3gp' => 'video/3gpp',
            'avi' => 'video/x-msvideo',
            'mov' => 'video/quicktime'
        ];
        
        if (isset($mime_types[$extension])) {
            return $mime_types[$extension];
        }
        
        // Log para debugging
        error_log("Could not determine MIME type for file: $file_path (extension: $extension)");
        
        // Por defecto, asumir documento si no podemos detectar
        return 'application/pdf'; // Cambiar default a PDF en lugar de octet-stream
    }
    
    /**
     * Detectar MIME type leyendo los primeros bytes del archivo
     */
    private function getMimeFromFileHeader($file_path) {
        if (!is_readable($file_path)) {
            return false;
        }
        
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return false;
        }
        
        $header = fread($handle, 12);
        fclose($handle);
        
        if (strlen($header) < 4) {
            return false;
        }
        
        // Signatures de archivos comunes
        $signatures = [
            "\xFF\xD8\xFF" => 'image/jpeg',                    // JPEG
            "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" => 'image/png', // PNG
            "\x47\x49\x46\x38" => 'image/gif',                 // GIF
            "\x52\x49\x46\x46" => 'audio/wav',                 // WAV (primeros 4 bytes)
            "\x25\x50\x44\x46" => 'application/pdf',           // PDF
            "\x50\x4B\x03\x04" => 'application/zip',           // ZIP/Office docs
            "\xD0\xCF\x11\xE0" => 'application/msword',        // DOC
            "\x49\x44\x33" => 'audio/mpeg'                     // MP3
        ];
        
        foreach ($signatures as $signature => $mime_type) {
            if (strpos($header, $signature) === 0) {
                return $mime_type;
            }
        }
        
        // Para archivos Office modernos (que son ZIP)
        if (strpos($header, "\x50\x4B\x03\x04") === 0) {
            $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            switch ($extension) {
                case 'docx':
                    return 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
                case 'xlsx':
                    return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                case 'pptx':
                    return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
                default:
                    return 'application/zip';
            }
        }
        
        return false;
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
     * Enviar video
     */
    public function sendVideoMessage($to, $media_id_or_url, $caption = '') {
        $to = $this->cleanPhoneNumber($to);
        
        $video_data = [
            'id' => $media_id_or_url
        ];
        
        // Si es URL en lugar de media_id
        if (filter_var($media_id_or_url, FILTER_VALIDATE_URL)) {
            $video_data = ['link' => $media_id_or_url];
        }
        
        if (!empty($caption)) {
            $video_data['caption'] = $caption;
        }
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'video',
            'video' => $video_data
        ];
        
        return $this->sendRequest('messages', $data);
    }
    
    /**
     * Subir archivo multimedia a WhatsApp - VERSIÓN CORREGIDA
     */
    public function uploadMedia($file_path, $type = 'auto') {
        if (!file_exists($file_path)) {
            return [
                'success' => false,
                'error' => 'File not found: ' . $file_path
            ];
        }
        
        // Detectar tipo automáticamente usando nuestra función segura
        $detected_mime = $this->getMimeType($file_path);
        if ($type === 'auto') {
            $type = $this->getMimeTypeCategory($detected_mime);
        }
        
        // Validar que el MIME type sea aceptado por WhatsApp
        $whatsapp_accepted_types = [
            'audio/aac', 'audio/mp4', 'audio/mpeg', 'audio/amr', 'audio/ogg', 'audio/opus',
            'application/vnd.ms-powerpoint', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/pdf', 'text/plain', 'application/vnd.ms-excel',
            'image/jpeg', 'image/png', 'image/webp',
            'video/mp4', 'video/3gpp'
        ];
        
        // Si no es un tipo aceptado, intentar corregirlo por extensión
        if (!in_array($detected_mime, $whatsapp_accepted_types)) {
            $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            $corrected_mime = $this->getWhatsAppCompatibleMime($extension);
            
            if ($corrected_mime) {
                $detected_mime = $corrected_mime;
                error_log("MIME type corrected from octet-stream to $corrected_mime based on extension: $extension");
            } else {
                return [
                    'success' => false,
                    'error' => "Tipo de archivo no soportado por WhatsApp: $detected_mime (extensión: $extension)"
                ];
            }
        }
        
        $url = $this->base_url . '/' . $this->api_version . '/' . $this->phone_number_id . '/media';
        
        // Crear CURLFile con el MIME type correcto
        $curlFile = new CURLFile($file_path, $detected_mime);
        
        $post_data = [
            'messaging_product' => 'whatsapp',
            'type' => $type,
            'file' => $curlFile
        ];
        
        error_log("Uploading to WhatsApp - Type: $type, MIME: $detected_mime, File: $file_path");
        
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
            error_log("WhatsApp upload failed - HTTP: $http_code, Response: $response");
            return [
                'success' => false,
                'error' => $decoded['error']['message'] ?? 'Upload failed',
                'response' => $decoded
            ];
        }
    }
    
    /**
     * Obtener MIME type compatible con WhatsApp basado en extensión
     */
    private function getWhatsAppCompatibleMime($extension) {
        $whatsapp_mime_map = [
            // Images
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            
            // Documents
            'pdf' => 'application/pdf',
            'txt' => 'text/plain',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            
            // Audio
            'aac' => 'audio/aac',
            'm4a' => 'audio/mp4',
            'mp3' => 'audio/mpeg',
            'amr' => 'audio/amr',
            'ogg' => 'audio/ogg',
            'opus' => 'audio/opus',
            
            // Video
            'mp4' => 'video/mp4',
            '3gp' => 'video/3gpp'
        ];
        
        return $whatsapp_mime_map[$extension] ?? null;
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
     * Registrar envío en la base de datos con manejo de errores
     */
    private function logWhatsAppSend($to, $data, $status, $response) {
        try {
            require_once __DIR__ . '/whatsapp_database.php';
            
            WhatsAppDatabase::logSend([
                'to' => $to,
                'type' => $data['type'] ?? 'text',
                'status' => $status,
                'response' => $response
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
                @mkdir($dir, 0755, true);
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
     * Validar archivo multimedia - VERSIÓN CORREGIDA PARA DETECCIÓN DE EXTENSIÓN
     */
    public function validateMediaFile($file_path, $type = 'auto') {
        if (!file_exists($file_path)) {
            return ['valid' => false, 'error' => 'Archivo no encontrado'];
        }
        
        $file_size = filesize($file_path);
        
        // Obtener extensión de múltiples formas
        $extension = $this->getFileExtension($file_path);
        
        if (empty($extension)) {
            return [
                'valid' => false, 
                'error' => 'No se pudo determinar la extensión del archivo: ' . basename($file_path)
            ];
        }
        
        // Primero determinar el MIME correcto para WhatsApp
        $whatsapp_mime = $this->getWhatsAppCompatibleMime($extension);
        
        if (!$whatsapp_mime) {
            return [
                'valid' => false, 
                'error' => "Extensión de archivo no soportada: .$extension (archivo: " . basename($file_path) . ")"
            ];
        }
        
        // Determinar categoría por extensión (más confiable que por MIME)
        if ($type === 'auto') {
            if (in_array($extension, ['jpg', 'jpeg', 'png', 'webp'])) {
                $type = 'images';
            } elseif (in_array($extension, ['pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
                $type = 'documents';
            } elseif (in_array($extension, ['aac', 'm4a', 'mp3', 'amr', 'ogg', 'opus'])) {
                $type = 'audio';
            } elseif (in_array($extension, ['mp4', '3gp'])) {
                $type = 'video';
            } else {
                return [
                    'valid' => false,
                    'error' => "Tipo de archivo no determinado para extensión: .$extension"
                ];
            }
        }
        
        // Límites de tamaño por tipo (según WhatsApp)
        $size_limits = [
            'images' => 5 * 1024 * 1024,    // 5MB
            'documents' => 100 * 1024 * 1024, // 100MB  
            'audio' => 16 * 1024 * 1024,     // 16MB
            'video' => 16 * 1024 * 1024      // 16MB
        ];
        
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
            'mime_type' => $whatsapp_mime, // Devolver el MIME correcto para WhatsApp
            'file_size' => $file_size,
            'category' => $type,
            'extension' => $extension
        ];
    }
    
    /**
     * Obtener extensión de archivo de forma robusta
     */
    private function getFileExtension($file_path) {
        // Método 1: pathinfo normal
        $extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        
        if (!empty($extension)) {
            return $extension;
        }
        
        // Método 2: Usar basename y buscar el último punto
        $basename = basename($file_path);
        $pos = strrpos($basename, '.');
        
        if ($pos !== false && $pos < strlen($basename) - 1) {
            $extension = strtolower(substr($basename, $pos + 1));
            if (!empty($extension)) {
                return $extension;
            }
        }
        
        // Método 3: Detectar por contenido del archivo
        $content_extension = $this->detectExtensionByContent($file_path);
        if ($content_extension) {
            return $content_extension;
        }
        
        // Log para debugging
        error_log("Could not determine extension for file: $file_path (basename: $basename)");
        
        return '';
    }
    
    /**
     * Detectar extensión por contenido del archivo
     */
    private function detectExtensionByContent($file_path) {
        if (!is_readable($file_path)) {
            return false;
        }
        
        $handle = fopen($file_path, 'rb');
        if (!$handle) {
            return false;
        }
        
        $header = fread($handle, 12);
        fclose($handle);
        
        if (strlen($header) < 4) {
            return false;
        }
        
        // Signatures de archivos comunes
        $signatures = [
            "\xFF\xD8\xFF" => 'jpg',                    // JPEG
            "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A" => 'png', // PNG
            "\x47\x49\x46\x38" => 'gif',                 // GIF
            "\x25\x50\x44\x46" => 'pdf',                 // PDF
            "\x50\x4B\x03\x04" => 'zip',                 // ZIP/Office docs
            "\xD0\xCF\x11\xE0" => 'doc',                 // DOC
            "\x49\x44\x33" => 'mp3'                      // MP3
        ];
        
        foreach ($signatures as $signature => $extension) {
            if (strpos($header, $signature) === 0) {
                return $extension;
            }
        }
        
        return false;
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
            'upload_dir_writable' => is_writable($this->upload_dir),
            'php_finfo_available' => function_exists('finfo_open'),
            'php_mime_content_type_available' => function_exists('mime_content_type')
        ];
    }
}
?>