<?php
// admin/includes/sidebar-data.php - Helper para preparar datos del sidebar
// Este archivo prepara todas las variables necesarias para el sidebar

// Obtener configuraciones básicas
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$role = $_SESSION['role_name'];
$user_name = $_SESSION['full_name'];

// Obtener estadísticas para el sidebar
$stats = [];
$online_stats = [];

try {
    // Solo cargar estadísticas si el usuario tiene permisos para verlas
    if ($auth->hasPermission('orders')) {
        // Requerimos el modelo Order solo si es necesario
        if (!class_exists('Order')) {
            require_once '../models/Order.php';
        }
        
        $orderModel = new Order();
        $stats['pending_orders'] = count($orderModel->getByStatus('pending'));
        $stats['preparing_orders'] = count($orderModel->getByStatus('preparing'));
        $stats['ready_orders'] = count($orderModel->getByStatus('ready'));
    }

    if ($auth->hasPermission('delivery')) {
        if (!isset($orderModel)) {
            if (!class_exists('Order')) {
                require_once '../models/Order.php';
            }
            $orderModel = new Order();
        }
        $stats['pending_deliveries'] = count($orderModel->getByStatus('ready', 'delivery'));
    }

    // Obtener estadísticas de pedidos online
    if ($auth->hasPermission('online_orders')) {
        $online_query = "SELECT 
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_online,
            COUNT(CASE WHEN status = 'accepted' THEN 1 END) as accepted_online,
            COUNT(CASE WHEN status = 'preparing' THEN 1 END) as preparing_online,
            COUNT(CASE WHEN status = 'ready' THEN 1 END) as ready_online
            FROM online_orders 
            WHERE DATE(created_at) = CURDATE()";
        
        $online_stmt = $db->prepare($online_query);
        $online_stmt->execute();
        $online_stats = $online_stmt->fetch();
    }
    
} catch (Exception $e) {
    // En caso de error, usar valores por defecto
    error_log("Error loading sidebar stats: " . $e->getMessage());
    $stats = ['pending_orders' => 0, 'preparing_orders' => 0, 'ready_orders' => 0, 'pending_deliveries' => 0];
    $online_stats = ['pending_online' => 0, 'accepted_online' => 0, 'preparing_online' => 0, 'ready_online' => 0];
}

// Variables ya preparadas para usar en sidebar.php:
// $restaurant_name, $user_name, $role, $auth, $stats, $online_stats
?>