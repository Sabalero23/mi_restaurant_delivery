<?php
// Mostrar errores temporalmente para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// admin/api/online-orders.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Log para debugging
error_log("API online-orders llamada - Método: " . $_SERVER['REQUEST_METHOD']);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

try {
    $database = new Database();
    $db = $database->getConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Log del cuerpo de la petición
        $input_raw = file_get_contents('php://input');
        error_log("Datos raw recibidos: " . $input_raw);
        
        $input = json_decode($input_raw, true);
        error_log("Datos parseados: " . print_r($input, true));
        
        if (!$input) {
            throw new Exception('No se pudieron parsear los datos JSON');
        }
        
        if (!isset($input['customer_name'], $input['customer_phone'], $input['customer_address'], $input['items'])) {
            throw new Exception('Datos incompletos. Campos requeridos: customer_name, customer_phone, customer_address, items');
        }

        // Validar items
        if (empty($input['items'])) {
            throw new Exception('El carrito está vacío');
        }

        // Validar que los items tengan la estructura correcta
        foreach ($input['items'] as $item) {
            if (!isset($item['id'], $item['name'], $item['price'], $item['quantity'])) {
                throw new Exception('Estructura de items inválida');
            }
            if ($item['quantity'] <= 0) {
                throw new Exception('Cantidad de items debe ser mayor a 0');
            }
        }

        // Generar número de orden
        $order_number = 'WEB-' . date('Ymd') . '-' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
        error_log("Número de orden generado: " . $order_number);
        
        // Calcular totales
        $subtotal = 0;
        foreach ($input['items'] as $item) {
            $subtotal += floatval($item['price']) * intval($item['quantity']);
        }
        
        $total = $subtotal; // Por ahora sin delivery fee
        
        error_log("Subtotal calculado: " . $subtotal);

        // Procesar datos de Google Maps si están disponibles
        $address_coordinates = null;
        $address_components = null;
        $formatted_address = $input['customer_address'];
        $customer_references = $input['customer_references'] ?? '';
        $delivery_distance = null;

        if (isset($input['address_details']) && $input['address_details']) {
            $address_details = $input['address_details'];
            
            // Extraer coordenadas
            if (isset($address_details['coordinates'])) {
                $coords = $address_details['coordinates'];
                $address_coordinates = json_encode([
                    'lat' => floatval($coords['lat']),
                    'lng' => floatval($coords['lng'])
                ]);
                
                // Calcular distancia del restaurante
                $restaurant_lat = -29.1167; // Avellaneda, Santa Fe // Ajustar a tu ubicación
                $restaurant_lng = -59.6500;
                $delivery_distance = calculateDistance(
                    floatval($coords['lat']), 
                    floatval($coords['lng']), 
                    $restaurant_lat, 
                    $restaurant_lng
                );
            }
            
            // Extraer componentes de dirección
            if (isset($address_details['components'])) {
                $address_components = json_encode($address_details['components']);
            }
            
            // Usar la dirección formateada de Google Maps
            if (isset($address_details['formatted_address'])) {
                $formatted_address = $address_details['formatted_address'];
                
                // Si hay referencias, agregarlas
                if ($customer_references) {
                    $formatted_address .= ' - Referencias: ' . $customer_references;
                }
            }
            
            error_log("Datos de Google Maps procesados - Coordenadas: " . $address_coordinates);
            error_log("Distancia calculada: " . $delivery_distance . " km");
        }

        // Validar teléfono argentino
        $customer_phone = $input['customer_phone'];
        if (!validateArgentinePhone($customer_phone)) {
            error_log("Teléfono inválido recibido: " . $customer_phone);
            throw new Exception('El número de teléfono no tiene un formato válido para Argentina');
        }

        // Verificar estructura de la tabla para incluir campos adicionales
        $columns_query = "SHOW COLUMNS FROM online_orders";
        $columns_stmt = $db->prepare($columns_query);
        $columns_stmt->execute();
        $columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);

        $has_notes_column = in_array('customer_notes', $columns);
        $has_references_column = in_array('customer_references', $columns);
        $has_coordinates_column = in_array('address_coordinates', $columns);
        $has_components_column = in_array('address_components', $columns);
        $has_distance_column = in_array('delivery_distance', $columns);

        // Construir query dinámicamente según las columnas disponibles
        $query_fields = [
            'order_number', 'customer_name', 'customer_phone', 'customer_address', 
            'items', 'subtotal', 'total', 'status', 'created_at'
        ];
        $query_placeholders = [
            ':order_number', ':customer_name', ':customer_phone', ':customer_address',
            ':items', ':subtotal', ':total', "'pending'", 'NOW()'
        ];
        
        $params = [
            'order_number' => $order_number,
            'customer_name' => sanitize($input['customer_name']),
            'customer_phone' => sanitize($customer_phone),
            'customer_address' => sanitize($formatted_address),
            'items' => json_encode($input['items']),
            'subtotal' => $subtotal,
            'total' => $total
        ];

        // Agregar campos opcionales si las columnas existen
        if ($has_notes_column) {
            $query_fields[] = 'customer_notes';
            $query_placeholders[] = ':customer_notes';
            $params['customer_notes'] = sanitize($input['customer_notes'] ?? '');
        }

        if ($has_references_column) {
            $query_fields[] = 'customer_references';
            $query_placeholders[] = ':customer_references';
            $params['customer_references'] = sanitize($customer_references);
        }

        if ($has_coordinates_column && $address_coordinates) {
            $query_fields[] = 'address_coordinates';
            $query_placeholders[] = ':address_coordinates';
            $params['address_coordinates'] = $address_coordinates;
        }

        if ($has_components_column && $address_components) {
            $query_fields[] = 'address_components';
            $query_placeholders[] = ':address_components';
            $params['address_components'] = $address_components;
        }

        if ($has_distance_column && $delivery_distance !== null) {
            $query_fields[] = 'delivery_distance';
            $query_placeholders[] = ':delivery_distance';
            $params['delivery_distance'] = round($delivery_distance, 2);
        }

        $query = "INSERT INTO online_orders (" . implode(', ', $query_fields) . ") 
                  VALUES (" . implode(', ', $query_placeholders) . ")";
        
        error_log("Query a ejecutar: " . $query);
        error_log("Parámetros: " . print_r($params, true));
        
        $stmt = $db->prepare($query);
        $result = $stmt->execute($params);

        if ($result) {
            error_log("Orden insertada exitosamente");
            
            // Preparar mensaje de respuesta con información de delivery
            $delivery_info = '';
            if ($delivery_distance !== null) {
                $delivery_info = " (a " . round($delivery_distance, 1) . " km del restaurante)";
                
                // Agregar advertencia si está lejos
                if ($delivery_distance > 25) {
                    $delivery_info .= " - Fuera del área habitual de delivery";
                }
            }
            
            // Verificar si hay productos no disponibles (simulación)
            $availability_warning = '';
            // Aquí podrías agregar lógica para verificar stock real
            
            echo json_encode([
                'success' => true,
                'order_number' => $order_number,
                'message' => 'Pedido enviado correctamente. Te contactaremos pronto para confirmar.' . $delivery_info,
                'estimated_time' => '30-45 minutos',
                'delivery_distance' => $delivery_distance ? round($delivery_distance, 1) : null,
                'delivery_warning' => $delivery_distance > 25 ? 'Fuera del área habitual de delivery' : null,
                'total_amount' => $total,
                'items_count' => count($input['items']),
                'phone_formatted' => formatPhoneForDisplay($customer_phone)
            ]);
        } else {
            $error_info = $stmt->errorInfo();
            error_log("Error en la inserción: " . print_r($error_info, true));
            throw new Exception('Error al procesar el pedido: ' . $error_info[2]);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Obtener órdenes con filtros opcionales
        $status_filter = $_GET['status'] ?? '';
        $date_filter = $_GET['date'] ?? '';
        $limit = intval($_GET['limit'] ?? 50);
        
        $where_conditions = [];
        $params = [];

        if ($status_filter) {
            $where_conditions[] = "status = :status";
            $params['status'] = $status_filter;
        }

        if ($date_filter) {
            $where_conditions[] = "DATE(created_at) = :date";
            $params['date'] = $date_filter;
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
        
        $query = "SELECT *, 
                  CASE 
                    WHEN address_coordinates IS NOT NULL THEN 
                      JSON_EXTRACT(address_coordinates, '$.lat') 
                    ELSE NULL 
                  END as latitude,
                  CASE 
                    WHEN address_coordinates IS NOT NULL THEN 
                      JSON_EXTRACT(address_coordinates, '$.lng') 
                    ELSE NULL 
                  END as longitude
                  FROM online_orders 
                  $where_clause
                  ORDER BY created_at DESC 
                  LIMIT :limit";
        
        $stmt = $db->prepare($query);
        
        // Bind limit parameter
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        
        // Bind other parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        
        $stmt->execute();
        $orders = $stmt->fetchAll();

        // Procesar órdenes para agregar información calculada
        foreach ($orders as &$order) {
            // Decodificar items JSON
            $order['items'] = json_decode($order['items'], true);
            
            // Formatear teléfono para mostrar
            $order['phone_display'] = formatPhoneForDisplay($order['customer_phone']);
            
            // Calcular tiempo transcurrido
            $order['time_elapsed'] = calculateTimeElapsed($order['created_at']);
            
            // Agregar información de coordenadas si existe
            if (isset($order['latitude']) && isset($order['longitude'])) {
                $order['coordinates'] = [
                    'lat' => floatval($order['latitude']),
                    'lng' => floatval($order['longitude'])
                ];
            }
            
            // Decodificar componentes de dirección si existen
            if (isset($order['address_components']) && $order['address_components']) {
                $order['address_components'] = json_decode($order['address_components'], true);
            }
        }

        echo json_encode([
            'success' => true,
            'orders' => $orders,
            'total_count' => count($orders),
            'filters_applied' => [
                'status' => $status_filter,
                'date' => $date_filter,
                'limit' => $limit
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("Error en API online-orders: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $e->getCode(),
        'debug' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}

// ===== FUNCIONES AUXILIARES =====

function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371; // km
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
         
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earth_radius * $c;
}

function validateArgentinePhone($phone) {
    // Limpiar número
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    // Debe empezar con 54 (código de Argentina)
    if (!str_starts_with($cleanPhone, '54')) {
        return false;
    }
    
    // Códigos de área válidos en Argentina
    $valid_area_codes = [
        '11',   // Buenos Aires
        '221',  // La Plata
        '223',  // Mar del Plata
        '261',  // Mendoza
        '341',  // Rosario
        '351',  // Córdoba
        '381',  // Tucumán
        '3482', // Santa Fe - Esperanza/Rafaela
        '3476', // Santa Fe - Reconquista
        '342',  // Santa Fe Capital
        '376',  // Misiones
        '388',  // Jujuy
        '299',  // Neuquén
        '2966', // Río Gallegos
        '264',  // San Juan
        '280',  // Viedma
        '383',  // Catamarca
        '385',  // La Rioja
        '387',  // Salta
        '2920', // San Carlos de Bariloche
        '2944', // Puerto Madryn
        '2954', // Río Grande
        '2972', // El Calafate
        '3843', // La Rioja Capital
        '3844', // Chilecito
        '3855', // Villa María
        '3856', // Bell Ville
        '3858', // Marcos Juárez
    ];
    
    // Remover código de país (54)
    $phoneWithoutCountry = substr($cleanPhone, 2);
    
    // Verificar si empieza con un código de área válido
    foreach ($valid_area_codes as $areaCode) {
        if (str_starts_with($phoneWithoutCountry, $areaCode)) {
            $remaining = substr($phoneWithoutCountry, strlen($areaCode));
            // El número local debe tener entre 6 y 8 dígitos
            if (strlen($remaining) >= 6 && strlen($remaining) <= 8) {
                return true;
            }
        }
    }
    
    return false;
}

function formatPhoneForDisplay($phone) {
    $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
    
    if (str_starts_with($cleanPhone, '54')) {
        // Formato: +54 XXXX XXXXXX
        $country = '+54';
        $phoneWithoutCountry = substr($cleanPhone, 2);
        
        // Detectar código de área
        $area_codes = ['11', '221', '223', '261', '341', '351', '381', '3482', '3476', '342'];
        
        foreach ($area_codes as $areaCode) {
            if (str_starts_with($phoneWithoutCountry, $areaCode)) {
                $area = $areaCode;
                $local = substr($phoneWithoutCountry, strlen($areaCode));
                
                // Formatear número local
                if (strlen($local) == 8) {
                    $local = substr($local, 0, 4) . '-' . substr($local, 4);
                } elseif (strlen($local) == 7) {
                    $local = substr($local, 0, 3) . '-' . substr($local, 3);
                } elseif (strlen($local) == 6) {
                    $local = substr($local, 0, 3) . '-' . substr($local, 3);
                }
                
                return "$country $area $local";
            }
        }
        
        // Si no encuentra código de área conocido, formato simple
        return "$country " . substr($phoneWithoutCountry, 0, 4) . " " . substr($phoneWithoutCountry, 4);
    }
    
    return $phone; // Retornar original si no tiene formato esperado
}

function calculateTimeElapsed($created_at) {
    $created = new DateTime($created_at);
    $now = new DateTime();
    $diff = $now->diff($created);
    
    if ($diff->h > 0) {
        return $diff->h . 'h ' . $diff->i . 'm';
    } elseif ($diff->i > 0) {
        return $diff->i . 'm';
    } else {
        return 'Recién';
    }
}

// Función para validar estructura de items
function validateOrderItems($items) {
    if (!is_array($items) || empty($items)) {
        return false;
    }
    
    foreach ($items as $item) {
        if (!isset($item['id'], $item['name'], $item['price'], $item['quantity'])) {
            return false;
        }
        
        if (!is_numeric($item['id']) || !is_numeric($item['price']) || !is_numeric($item['quantity'])) {
            return false;
        }
        
        if ($item['quantity'] <= 0 || $item['price'] < 0) {
            return false;
        }
        
        if (strlen($item['name']) > 255) {
            return false;
        }
    }
    
    return true;
}

// Función para verificar límites de rate limiting (opcional)
function checkRateLimit($customer_phone) {
    // Implementar lógica de rate limiting si es necesario
    // Por ejemplo, máximo 3 pedidos por teléfono por hora
    return true;
}

// Función para log de eventos importantes
function logOrderEvent($order_number, $event, $details = '') {
    $log_entry = date('Y-m-d H:i:s') . " - Order: $order_number - Event: $event";
    if ($details) {
        $log_entry .= " - Details: $details";
    }
    error_log($log_entry);
}

?>