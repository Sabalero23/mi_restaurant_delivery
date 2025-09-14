<?php
// Configuración inicial de zona horaria
if (!function_exists('initializeSystemTimezone')) {
    function initializeSystemTimezone() {
        static $initialized = false;
        if (!$initialized) {
            // Usar zona horaria por defecto sin acceder a la DB
            date_default_timezone_set('America/Argentina/Buenos_Aires');
            $initialized = true;
        }
    }
}

// Llamar inmediatamente
initializeSystemTimezone();

// config/functions.php
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatPrice($price) {
    // Verificar que el precio sea un número válido
    if ($price === null || $price === '' || !is_numeric($price)) {
        $price = 0;
    }
    return '$' . number_format(floatval($price), 2, ',', '.');
}



function generateOrderNumber() {
    return 'ORD-' . date('Ymd') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

function uploadImage($file, $directory = 'products') {
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return false;
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return false;
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $upload_path = UPLOAD_PATH . $directory . '/' . $filename;
    
    if (!is_dir(dirname($upload_path))) {
        mkdir(dirname($upload_path), 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        return $upload_path;
    }
    
    return false;
}

function sendWhatsAppLink($phone, $message) {
    $whatsapp_url = "https://wa.me/" . preg_replace('/[^0-9]/', '', $phone) . "?text=" . urlencode($message);
    return $whatsapp_url;
}

function getSettings() {
    static $settings = null;
    
    if ($settings === null) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT setting_key, setting_value FROM settings";
            $stmt = $db->prepare($query);
            $stmt->execute();
            
            $settings = [];
            while ($row = $stmt->fetch()) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            error_log("Error loading settings: " . $e->getMessage());
            $settings = []; // Return empty array on error
        }
    }
    
    return $settings;
}

// Función para debugear (solo en desarrollo)
function debug($data, $die = false) {
    echo '<pre>';
    print_r($data);
    echo '</pre>';
    if ($die) die();
}

// Función para validar email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Función para validar teléfono argentino
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^(\+54|54|0)?([1-9]\d{1,4}\d{6,8})$/', $phone);
}

// Función para generar slug URL-friendly
function generateSlug($text) {
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    $text = preg_replace('/\s+/', '-', $text);
    return trim($text, '-');
}

// Nueva función para obtener producto con precio
function getProductWithPrice($product_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, name, price FROM products WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $product_id);
        $stmt->execute();
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting product: " . $e->getMessage());
        return false;
    }
}

// Función para calcular el total de una orden
function calculateOrderTotal($items) {
    $subtotal = 0;
    
    foreach ($items as $item) {
        if (isset($item['unit_price']) && isset($item['quantity'])) {
            $subtotal += $item['unit_price'] * $item['quantity'];
        } elseif (isset($item['price']) && isset($item['quantity'])) {
            $subtotal += $item['price'] * $item['quantity'];
        }
    }
    
    return $subtotal;
}

/**
 * Obtiene la zona horaria configurada en el sistema
 */
function getSystemTimezone() {
    static $timezone = null;
    
    if ($timezone === null) {
        try {
            // Solo intentar cargar desde DB si Database está disponible
            if (class_exists('Database')) {
                $settings = getSettings();
                $timezone = $settings['system_timezone'] ?? 'America/Argentina/Buenos_Aires';
            } else {
                $timezone = 'America/Argentina/Buenos_Aires';
            }
            
            // Establecer la zona horaria por defecto de PHP
            date_default_timezone_set($timezone);
        } catch (Exception $e) {
            $timezone = 'America/Argentina/Buenos_Aires';
            date_default_timezone_set($timezone);
        }
    }
    
    return $timezone;
}

/**
 * Calcula el tiempo transcurrido desde la creación de una orden
 */
function getOrderTimeElapsed($created_at) {
    $timezone = getSystemTimezone();
    
    try {
        $now = new DateTime('now', new DateTimeZone($timezone));
        $created = new DateTime($created_at, new DateTimeZone($timezone));
        $diff = $now->diff($created);
        
        $hours = $diff->h + ($diff->days * 24);
        $minutes = $diff->i;
        
        if ($hours > 0) {
            return $hours . 'h ' . $minutes . 'min';
        }
        return $minutes . 'min';
    } catch (Exception $e) {
        error_log("Error calculating time elapsed: " . $e->getMessage());
        return '0min';
    }
}

/**
 * Obtiene la fecha/hora actual en la zona horaria del sistema
 */
function getCurrentDateTime($format = 'Y-m-d H:i:s') {
    $timezone = getSystemTimezone();
    $date = new DateTime('now', new DateTimeZone($timezone));
    return $date->format($format);
}

/**
 * Formatea una fecha/hora usando la zona horaria del sistema
 */
function formatDateTime($datetime, $format = null) {
    $timezone = getSystemTimezone();
    $settings = getSettings();
    
    if ($format === null) {
        $dateFormat = $settings['date_format'] ?? 'd/m/Y';
        $format = $dateFormat . ' H:i';
    }
    
    try {
        $date = new DateTime($datetime, new DateTimeZone($timezone));
        return $date->format($format);
    } catch (Exception $e) {
        error_log("Error formatting datetime: " . $e->getMessage());
        return $datetime; // Devolver original si hay error
    }
}

/**
 * Verifica si una orden debe marcarse como prioritaria
 */
function isOrderPriority($created_at, $threshold_minutes = 30) {
    $timezone = getSystemTimezone();
    
    try {
        $now = new DateTime('now', new DateTimeZone($timezone));
        $created = new DateTime($created_at, new DateTimeZone($timezone));
        $diff = $now->diff($created);
        
        $totalMinutes = ($diff->h * 60) + $diff->i + ($diff->days * 24 * 60);
        
        return $totalMinutes > $threshold_minutes;
    } catch (Exception $e) {
        error_log("Error checking order priority: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene el estado de prioridad de una orden
 */
function getOrderPriorityStatus($order) {
    $isOverdue = isOrderPriority($order['created_at'], 30);
    $timeElapsed = getOrderTimeElapsed($order['created_at']);
    
    if ($isOverdue && in_array($order['status'], ['pending', 'confirmed', 'preparing'])) {
        return [
            'is_priority' => true,
            'label' => 'PRIORITARIO',
            'class' => 'badge bg-danger',
            'time' => $timeElapsed
        ];
    }
    
    return [
        'is_priority' => false,
        'label' => ucfirst($order['status']),
        'class' => 'badge bg-secondary',
        'time' => $timeElapsed
    ];
}

/**
 * Función auxiliar para inicializar zona horaria cuando Database esté disponible
 */
function updateSystemTimezone() {
    if (class_exists('Database')) {
        try {
            $settings = getSettings();
            $timezone = $settings['system_timezone'] ?? 'America/Argentina/Buenos_Aires';
            date_default_timezone_set($timezone);
        } catch (Exception $e) {
            // Mantener zona horaria por defecto
        }
    }
    
    /**
 * Obtiene el color del estado de una orden
 */
function getStatusColor($status) {
    $colors = [
        'pending' => 'secondary',
        'confirmed' => 'info',
        'preparing' => 'warning',
        'ready' => 'success',
        'delivered' => 'primary',
        'cancelled' => 'danger'
    ];
    return $colors[$status] ?? 'secondary';
}

/**
 * Genera un número de orden para pedidos online
 */
function generateOnlineOrderNumber() {
    return 'WEB-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
}

function getProductWithPrice($product_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, name, price, is_available FROM products WHERE id = :id AND is_active = 1";
        $stmt = $db->prepare($query);
        $stmt->execute(['id' => $product_id]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting product: " . $e->getMessage());
        return false;
    }
}

/**
 * Obtiene el color del badge según el tipo de orden
 */
function getOrderTypeBadgeColor($type) {
    $colors = [
        'dine_in' => 'primary',
        'delivery' => 'warning', 
        'takeout' => 'info',
        'online' => 'success'
    ];
    return $colors[$type] ?? 'secondary';
}

/**
 * Obtiene el ícono según el tipo de orden
 */
function getOrderTypeIcon($type) {
    $icons = [
        'dine_in' => 'fas fa-chair',
        'delivery' => 'fas fa-truck',
        'takeout' => 'fas fa-shopping-bag',
        'online' => 'fas fa-globe'
    ];
    return $icons[$type] ?? 'fas fa-receipt';
}

/**
 * Genera enlace de WhatsApp para confirmación de pedido online
 */
function generateOnlineOrderWhatsApp($order) {
    $items = json_decode($order['items'], true);
    $message = "🍽️ *CONFIRMACIÓN DE PEDIDO ONLINE*\n\n";
    $message .= "📋 Número: {$order['order_number']}\n";
    $message .= "👤 Cliente: {$order['customer_name']}\n";
    $message .= "📍 Dirección: {$order['customer_address']}\n\n";
    $message .= "*DETALLE DEL PEDIDO:*\n";
    
    foreach ($items as $item) {
        $message .= "• {$item['quantity']}x {$item['name']}\n";
    }
    
    $message .= "\n💰 Total: " . formatPrice($order['total']);
    $message .= "\n\n";
    
    switch ($order['status']) {
        case 'accepted':
            $message .= "✅ Tu pedido ha sido confirmado y pronto comenzaremos a prepararlo.";
            break;
        case 'preparing':
            $message .= "👨‍🍳 Tu pedido está siendo preparado en cocina.";
            break;
        case 'ready':
            $message .= "🚚 Tu pedido está listo para ser entregado.";
            break;
    }
    
    return "https://wa.me/" . preg_replace('/[^0-9]/', '', $order['customer_phone']) . "?text=" . urlencode($message);
}

/**
 * Obtiene estadísticas de pedidos online del día
 */
function getOnlineOrdersStats($date = null) {
    if (!$date) {
        $date = date('Y-m-d');
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing,
                    SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) as revenue
                  FROM online_orders 
                  WHERE DATE(created_at) = :date";
        
        $stmt = $db->prepare($query);
        $stmt->execute(['date' => $date]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting online orders stats: " . $e->getMessage());
        return [
            'total' => 0,
            'pending' => 0,
            'accepted' => 0,
            'preparing' => 0,
            'ready' => 0,
            'delivered' => 0,
            'rejected' => 0,
            'revenue' => 0
        ];
    }
}

/**
 * Verifica si hay pedidos online pendientes de atención
 */
function hasOnlinePendingOrders() {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT COUNT(*) as count FROM online_orders WHERE status = 'pending'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch();
        
        return $result['count'] > 0;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Obtiene el tiempo promedio de preparación por tipo de producto
 */
function getAveragePreparationTime($category_id = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if ($category_id) {
            $query = "SELECT AVG(preparation_time) as avg_time FROM products WHERE category_id = :category_id AND is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->execute(['category_id' => $category_id]);
        } else {
            $query = "SELECT AVG(preparation_time) as avg_time FROM products WHERE is_active = 1";
            $stmt = $db->prepare($query);
            $stmt->execute();
        }
        
        $result = $stmt->fetch();
        return $result['avg_time'] ? round($result['avg_time']) : 15;
    } catch (Exception $e) {
        return 15; // Tiempo por defecto
    }
}

/**
 * Calcula el tiempo estimado de preparación para un pedido online
 */
function calculateOnlineOrderPreparationTime($items) {
    $total_time = 0;
    $max_time = 0;
    
    foreach ($items as $item) {
        // Obtener tiempo de preparación del producto desde la base de datos
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            $query = "SELECT preparation_time FROM products WHERE name = :name LIMIT 1";
            $stmt = $db->prepare($query);
            $stmt->execute(['name' => $item['name']]);
            $product = $stmt->fetch();
            
            $prep_time = $product ? $product['preparation_time'] : 15;
            
            // El tiempo total es el máximo tiempo de preparación (se preparan en paralelo)
            $max_time = max($max_time, $prep_time);
            
        } catch (Exception $e) {
            $max_time = max($max_time, 15); // Tiempo por defecto
        }
    }
    
    return $max_time;
}

/**
 * Formatea el estado de un pedido online para mostrar
 */
function formatOnlineOrderStatus($status) {
    $statuses = [
        'pending' => ['label' => 'Pendiente', 'class' => 'warning', 'icon' => 'clock'],
        'accepted' => ['label' => 'Aceptado', 'class' => 'info', 'icon' => 'check'],
        'rejected' => ['label' => 'Rechazado', 'class' => 'danger', 'icon' => 'times'],
        'preparing' => ['label' => 'Preparando', 'class' => 'warning', 'icon' => 'fire'],
        'ready' => ['label' => 'Listo', 'class' => 'success', 'icon' => 'check-circle'],
        'delivered' => ['label' => 'Entregado', 'class' => 'primary', 'icon' => 'truck']
    ];
    
    return $statuses[$status] ?? ['label' => 'Desconocido', 'class' => 'secondary', 'icon' => 'question'];
}

/**
 * Envía notificación de nuevo pedido online (puede extenderse para email, SMS, etc.)
 */
function notifyNewOnlineOrder($order) {
    // Por ahora solo log, pero se puede extender para notificaciones push, email, etc.
    error_log("Nuevo pedido online: {$order['order_number']} - Cliente: {$order['customer_name']}");
    
    // Aquí se puede agregar:
    // - Envío de email al restaurante
    // - Notificación push a dispositivos del personal
    // - Integración con sistemas de notificación externos
    
    return true;
}

/**
 * Valida los datos de un pedido online antes de crear
 */
function validateOnlineOrderData($data) {
    $errors = [];
    
    if (empty($data['customer_name'])) {
        $errors[] = 'El nombre del cliente es requerido';
    }
    
    if (empty($data['customer_phone'])) {
        $errors[] = 'El teléfono del cliente es requerido';
    } elseif (!isValidPhone($data['customer_phone'])) {
        $errors[] = 'El formato del teléfono no es válido';
    }
    
    if (empty($data['customer_address'])) {
        $errors[] = 'La dirección de entrega es requerida';
    }
    
    if (empty($data['items']) || !is_array($data['items'])) {
        $errors[] = 'El pedido debe contener al menos un producto';
    }
    
    // Validar items
    foreach ($data['items'] as $item) {
        if (!isset($item['id'], $item['name'], $item['price'], $item['quantity'])) {
            $errors[] = 'Formato de producto inválido';
            break;
        }
        
        if ($item['quantity'] <= 0) {
            $errors[] = 'La cantidad debe ser mayor a 0';
            break;
        }
        
        if ($item['price'] <= 0) {
            $errors[] = 'El precio debe ser mayor a 0';
            break;
        }
    }
    
    return $errors;
}
}