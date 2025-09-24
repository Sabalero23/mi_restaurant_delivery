<?php
// admin/reports.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Order.php';
require_once '../models/Payment.php';
require_once '../models/Product.php';
require_once '../models/Table.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('reports');

$orderModel = new Order();
$paymentModel = new Payment();
$productModel = new Product();
$tableModel = new Table();

// Obtener configuraciones del sistema
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// AGREGAR ESTAS LÍNEAS:
// Obtener información del usuario actual
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role_name'] ?? 'usuario';

// Verificar si hay estadísticas disponibles (opcional)
$stats = array();
$online_stats = array();

// Get database connection for custom queries
$database = new Database();
$db = $database->getConnection();

// Handle date range
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // Today
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'sales';

// Validate dates
if (strtotime($start_date) > strtotime($end_date)) {
    $temp = $start_date;
    $start_date = $end_date;
    $end_date = $temp;
}

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Generate reports based on type with online orders integration
function getSalesReport($db, $start_date, $end_date) {
    $query = "SELECT 
                DATE(date_created) as date,
                SUM(total_orders) as total_orders,
                SUM(completed_orders) as completed_orders,
                SUM(cancelled_orders) as cancelled_orders,
                SUM(total_sales) as total_sales,
                SUM(subtotal_sales) as subtotal_sales,
                SUM(tax_amount) as tax_amount,
                SUM(delivery_fees) as delivery_fees,
                AVG(total_sales / NULLIF(completed_orders, 0)) as average_order_value,
                SUM(dine_in_sales) as dine_in_sales,
                SUM(delivery_sales) as delivery_sales,
                SUM(takeout_sales) as takeout_sales,
                SUM(online_sales) as online_sales
              FROM (
                -- Órdenes tradicionales
                SELECT 
                    DATE(o.created_at) as date_created,
                    COUNT(o.id) as total_orders,
                    SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN o.status = 'delivered' THEN o.total ELSE 0 END) as total_sales,
                    SUM(CASE WHEN o.status = 'delivered' THEN o.subtotal ELSE 0 END) as subtotal_sales,
                    SUM(CASE WHEN o.status = 'delivered' THEN o.tax ELSE 0 END) as tax_amount,
                    SUM(CASE WHEN o.status = 'delivered' THEN o.delivery_fee ELSE 0 END) as delivery_fees,
                    SUM(CASE WHEN o.type = 'dine_in' AND o.status = 'delivered' THEN o.total ELSE 0 END) as dine_in_sales,
                    SUM(CASE WHEN o.type = 'delivery' AND o.status = 'delivered' THEN o.total ELSE 0 END) as delivery_sales,
                    SUM(CASE WHEN o.type = 'takeout' AND o.status = 'delivered' THEN o.total ELSE 0 END) as takeout_sales,
                    0 as online_sales
                FROM orders o 
                WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date 
                GROUP BY DATE(o.created_at)
                
                UNION ALL
                
                -- Órdenes online
                SELECT 
                    DATE(oo.created_at) as date_created,
                    COUNT(oo.id) as total_orders,
                    SUM(CASE WHEN oo.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN oo.status = 'rejected' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN oo.status = 'delivered' THEN oo.total ELSE 0 END) as total_sales,
                    SUM(CASE WHEN oo.status = 'delivered' THEN oo.subtotal ELSE 0 END) as subtotal_sales,
                    0 as tax_amount,
                    0 as delivery_fees,
                    0 as dine_in_sales,
                    0 as delivery_sales,
                    0 as takeout_sales,
                    SUM(CASE WHEN oo.status = 'delivered' THEN oo.total ELSE 0 END) as online_sales
                FROM online_orders oo 
                WHERE DATE(oo.created_at) BETWEEN :start_date AND :end_date 
                GROUP BY DATE(oo.created_at)
              ) combined
              GROUP BY DATE(date_created) 
              ORDER BY DATE(date_created) DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    return $stmt->fetchAll();
}

function getProductsReport($db, $start_date, $end_date) {
    // Primero obtenemos productos de órdenes tradicionales
    $traditional_query = "SELECT 
                p.id as product_id,
                p.name as product_name,
                p.price,
                p.cost,
                c.name as category_name,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.subtotal) as total_revenue,
                SUM(oi.quantity * p.cost) as total_cost,
                COUNT(DISTINCT o.id) as orders_count,
                AVG(oi.quantity) as avg_quantity_per_order,
                'traditional' as source_type
            FROM order_items oi
            JOIN orders o ON oi.order_id = o.id
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date 
            AND o.status = 'delivered'
            GROUP BY p.id, p.name, p.price, p.cost, c.name";
    
    $traditional_stmt = $db->prepare($traditional_query);
    $traditional_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $traditional_products = $traditional_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Luego obtenemos productos de órdenes online
    $online_query = "SELECT 
                oo.id as order_id,
                oo.items,
                oo.status
            FROM online_orders oo 
            WHERE DATE(oo.created_at) BETWEEN :start_date AND :end_date 
            AND oo.status = 'delivered'";
    
    $online_stmt = $db->prepare($online_query);
    $online_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    $online_orders = $online_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Procesar productos online manualmente
    $online_products = [];
    foreach ($online_orders as $order) {
        $items = json_decode($order['items'], true);
        if ($items) {
            foreach ($items as $item) {
                $product_id = $item['id'];
                $quantity = $item['quantity'];
                $price = $item['price'];
                $revenue = $quantity * $price;
                
                // Obtener información del producto
                $product_query = "SELECT p.id, p.name, p.price, p.cost, c.name as category_name 
                                FROM products p 
                                LEFT JOIN categories c ON p.category_id = c.id 
                                WHERE p.id = :product_id";
                $product_stmt = $db->prepare($product_query);
                $product_stmt->execute(['product_id' => $product_id]);
                $product_info = $product_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product_info) {
                    $key = $product_id;
                    if (!isset($online_products[$key])) {
                        $online_products[$key] = [
                            'product_id' => $product_info['id'],
                            'product_name' => $product_info['name'],
                            'price' => $product_info['price'],
                            'cost' => $product_info['cost'],
                            'category_name' => $product_info['category_name'],
                            'total_quantity' => 0,
                            'total_revenue' => 0,
                            'total_cost' => 0,
                            'orders_count' => 0,
                            'orders_set' => [],
                            'source_type' => 'online'
                        ];
                    }
                    
                    $online_products[$key]['total_quantity'] += $quantity;
                    $online_products[$key]['total_revenue'] += $revenue;
                    $online_products[$key]['total_cost'] += $quantity * $product_info['cost'];
                    
                    if (!in_array($order['order_id'], $online_products[$key]['orders_set'])) {
                        $online_products[$key]['orders_set'][] = $order['order_id'];
                        $online_products[$key]['orders_count']++;
                    }
                }
            }
        }
    }
    
    // Limpiar arrays temporales y calcular promedios
    foreach ($online_products as &$product) {
        $product['avg_quantity_per_order'] = $product['orders_count'] > 0 ? 
            $product['total_quantity'] / $product['orders_count'] : 0;
        unset($product['orders_set']);
    }
    
    // Combinar productos tradicionales y online
    $all_products = [];
    
    // Agregar productos tradicionales
    foreach ($traditional_products as $product) {
        $key = $product['product_id'];
        $all_products[$key] = $product;
        $all_products[$key]['profit'] = $product['total_revenue'] - $product['total_cost'];
    }
    
    // Agregar o combinar productos online
    foreach ($online_products as $product) {
        $key = $product['product_id'];
        if (isset($all_products[$key])) {
            // Combinar con producto existente
            $all_products[$key]['total_quantity'] += $product['total_quantity'];
            $all_products[$key]['total_revenue'] += $product['total_revenue'];
            $all_products[$key]['total_cost'] += $product['total_cost'];
            $all_products[$key]['orders_count'] += $product['orders_count'];
            $all_products[$key]['avg_quantity_per_order'] = 
                ($all_products[$key]['avg_quantity_per_order'] + $product['avg_quantity_per_order']) / 2;
        } else {
            // Agregar como nuevo producto
            $all_products[$key] = $product;
        }
        $all_products[$key]['profit'] = $all_products[$key]['total_revenue'] - $all_products[$key]['total_cost'];
    }
    
    // Convertir a array indexado y ordenar
    $result = array_values($all_products);
    usort($result, function($a, $b) {
        return $b['total_quantity'] - $a['total_quantity'];
    });
    
    return $result;
}

function getPaymentsReport($db, $start_date, $end_date) {
    $query = "SELECT 
                payment_type,
                method,
                COUNT(*) as transaction_count,
                SUM(amount) as total_amount,
                AVG(amount) as average_amount,
                DATE(payment_date) as date
              FROM (
                -- Pagos de órdenes tradicionales
                SELECT 
                    'traditional' as payment_type,
                    p.method,
                    p.amount,
                    p.created_at as payment_date
                FROM payments p
                JOIN orders o ON p.order_id = o.id
                WHERE DATE(p.created_at) BETWEEN :start_date AND :end_date
                
                UNION ALL
                
                -- Pagos de órdenes online
                SELECT 
                    'online' as payment_type,
                    op.method,
                    op.amount,
                    op.created_at as payment_date
                FROM online_orders_payments op
                JOIN online_orders oo ON op.online_order_id = oo.id
                WHERE DATE(op.created_at) BETWEEN :start_date AND :end_date
              ) combined_payments
              GROUP BY method, DATE(payment_date)
              ORDER BY DATE(payment_date) DESC, total_amount DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    return $stmt->fetchAll();
}

function getTablesReport($db, $start_date, $end_date) {
    $query = "SELECT 
                t.id,
                t.number,
                t.capacity,
                COUNT(o.id) as total_orders,
                SUM(CASE WHEN o.status = 'delivered' THEN o.total ELSE 0 END) as total_revenue,
                AVG(CASE WHEN o.status = 'delivered' THEN o.total ELSE NULL END) as average_order_value,
                SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders
              FROM tables t
              LEFT JOIN orders o ON t.id = o.table_id 
              AND DATE(o.created_at) BETWEEN :start_date AND :end_date
              GROUP BY t.id, t.number, t.capacity
              ORDER BY total_revenue DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    return $stmt->fetchAll();
}

function getStaffReport($db, $start_date, $end_date) {
    $query = "SELECT 
                u.id,
                u.full_name,
                u.username,
                r.name as role_name,
                COALESCE(traditional_stats.total_orders, 0) + COALESCE(online_stats.total_orders, 0) as total_orders,
                COALESCE(traditional_stats.total_sales, 0) + COALESCE(online_stats.total_sales, 0) as total_sales,
                (COALESCE(traditional_stats.total_sales, 0) + COALESCE(online_stats.total_sales, 0)) / 
                NULLIF(COALESCE(traditional_stats.completed_orders, 0) + COALESCE(online_stats.completed_orders, 0), 0) as average_sale,
                COALESCE(traditional_stats.completed_orders, 0) + COALESCE(online_stats.completed_orders, 0) as completed_orders,
                COALESCE(traditional_stats.cancelled_orders, 0) + COALESCE(online_stats.cancelled_orders, 0) as cancelled_orders,
                COALESCE(online_stats.accepted_orders, 0) as online_accepted_orders,
                COALESCE(online_stats.rejected_orders, 0) as online_rejected_orders
              FROM users u
              LEFT JOIN roles r ON u.role_id = r.id
              LEFT JOIN (
                SELECT 
                    o.waiter_id,
                    COUNT(o.id) as total_orders,
                    SUM(CASE WHEN o.status = 'delivered' THEN o.total ELSE 0 END) as total_sales,
                    SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders
                FROM orders o 
                WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
                GROUP BY o.waiter_id
              ) traditional_stats ON u.id = traditional_stats.waiter_id
              LEFT JOIN (
                SELECT 
                    COALESCE(oo.accepted_by, oo.delivered_by) as user_id,
                    COUNT(oo.id) as total_orders,
                    SUM(CASE WHEN oo.status = 'delivered' THEN oo.total ELSE 0 END) as total_sales,
                    SUM(CASE WHEN oo.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                    0 as cancelled_orders,
                    SUM(CASE WHEN oo.status IN ('accepted', 'preparing', 'ready', 'delivered') THEN 1 ELSE 0 END) as accepted_orders,
                    SUM(CASE WHEN oo.status = 'rejected' THEN 1 ELSE 0 END) as rejected_orders
                FROM online_orders oo 
                WHERE DATE(oo.created_at) BETWEEN :start_date AND :end_date
                AND (oo.accepted_by IS NOT NULL OR oo.delivered_by IS NOT NULL)
                GROUP BY COALESCE(oo.accepted_by, oo.delivered_by)
              ) online_stats ON u.id = online_stats.user_id
              WHERE r.name IN ('mesero', 'mostrador', 'administrador', 'gerente', 'delivery')
              AND (traditional_stats.total_orders > 0 OR online_stats.total_orders > 0 OR r.name IN ('administrador', 'gerente'))
              ORDER BY total_sales DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    return $stmt->fetchAll();
}

function getSummaryStats($db, $start_date, $end_date) {
    $query = "SELECT 
                SUM(total_orders) as total_orders,
                SUM(total_revenue) as total_revenue,
                SUM(completed_orders) as completed_orders,
                SUM(cancelled_orders) as cancelled_orders,
                AVG(total_revenue / NULLIF(completed_orders, 0)) as average_order_value,
                SUM(dine_in_revenue) as dine_in_revenue,
                SUM(delivery_revenue) as delivery_revenue,
                SUM(takeout_revenue) as takeout_revenue,
                SUM(online_revenue) as online_revenue,
                COUNT(DISTINCT active_date) as active_days
              FROM (
                -- Órdenes tradicionales
                SELECT 
                    DATE(o.created_at) as active_date,
                    COUNT(o.id) as total_orders,
                    SUM(CASE WHEN o.status = 'delivered' THEN o.total ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_orders,
                    SUM(CASE WHEN o.type = 'dine_in' AND o.status = 'delivered' THEN o.total ELSE 0 END) as dine_in_revenue,
                    SUM(CASE WHEN o.type = 'delivery' AND o.status = 'delivered' THEN o.total ELSE 0 END) as delivery_revenue,
                    SUM(CASE WHEN o.type = 'takeout' AND o.status = 'delivered' THEN o.total ELSE 0 END) as takeout_revenue,
                    0 as online_revenue
                FROM orders o 
                WHERE DATE(o.created_at) BETWEEN :start_date AND :end_date
                GROUP BY DATE(o.created_at)
                
                UNION ALL
                
                -- Órdenes online
                SELECT 
                    DATE(oo.created_at) as active_date,
                    COUNT(oo.id) as total_orders,
                    SUM(CASE WHEN oo.status = 'delivered' THEN oo.total ELSE 0 END) as total_revenue,
                    SUM(CASE WHEN oo.status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
                    SUM(CASE WHEN oo.status = 'rejected' THEN 1 ELSE 0 END) as cancelled_orders,
                    0 as dine_in_revenue,
                    0 as delivery_revenue,
                    0 as takeout_revenue,
                    SUM(CASE WHEN oo.status = 'delivered' THEN oo.total ELSE 0 END) as online_revenue
                FROM online_orders oo 
                WHERE DATE(oo.created_at) BETWEEN :start_date AND :end_date
                GROUP BY DATE(oo.created_at)
              ) combined";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    return $stmt->fetch();
}

function getOnlineOrdersStats($db, $start_date, $end_date) {
    $query = "SELECT 
                COUNT(*) as total_online_orders,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
                SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_orders,
                SUM(CASE WHEN status = 'preparing' THEN 1 ELSE 0 END) as preparing_orders,
                SUM(CASE WHEN status = 'ready' THEN 1 ELSE 0 END) as ready_orders,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
                SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_orders,
                SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) as total_revenue,
                AVG(CASE WHEN status = 'delivered' THEN total ELSE NULL END) as average_order_value,
                AVG(CASE WHEN delivered_at IS NOT NULL AND created_at IS NOT NULL 
                     THEN TIMESTAMPDIFF(MINUTE, created_at, delivered_at) 
                     ELSE NULL END) as avg_delivery_time,
                AVG(CASE WHEN accepted_at IS NOT NULL AND created_at IS NOT NULL 
                     THEN TIMESTAMPDIFF(MINUTE, created_at, accepted_at) 
                     ELSE NULL END) as avg_acceptance_time
              FROM online_orders 
              WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    return $stmt->fetch();
}

function getDeliveryReport($db, $start_date, $end_date) {
    $query = "SELECT 
                delivery_type,
                COUNT(*) as total_deliveries,
                SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_deliveries,
                SUM(CASE WHEN status IN ('cancelled', 'rejected') THEN 1 ELSE 0 END) as cancelled_deliveries,
                SUM(CASE WHEN status = 'delivered' THEN total_amount ELSE 0 END) as total_revenue,
                AVG(CASE WHEN status = 'delivered' THEN total_amount ELSE NULL END) as average_order_value,
                AVG(CASE WHEN status = 'delivered' AND delivery_time IS NOT NULL THEN delivery_time ELSE NULL END) as avg_delivery_time,
                SUM(delivery_fees) as total_delivery_fees
              FROM (
                -- Delivery tradicional
                SELECT 
                    'traditional' as delivery_type,
                    o.status,
                    o.total as total_amount,
                    o.delivery_fee as delivery_fees,
                    NULL as delivery_time
                FROM orders o 
                WHERE o.type = 'delivery'
                AND DATE(o.created_at) BETWEEN :start_date AND :end_date
                
                UNION ALL
                
                -- Delivery online
                SELECT 
                    'online' as delivery_type,
                    oo.status,
                    oo.total as total_amount,
                    0 as delivery_fees,
                    CASE 
                        WHEN oo.delivered_at IS NOT NULL AND oo.created_at IS NOT NULL 
                        THEN TIMESTAMPDIFF(MINUTE, oo.created_at, oo.delivered_at)
                        ELSE NULL 
                    END as delivery_time
                FROM online_orders oo 
                WHERE DATE(oo.created_at) BETWEEN :start_date AND :end_date
              ) combined
              GROUP BY delivery_type
              ORDER BY total_revenue DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
    return $stmt->fetchAll();
}

// Get report data
$summary_stats = getSummaryStats($db, $start_date, $end_date);
$sales_report = getSalesReport($db, $start_date, $end_date);
$products_report = getProductsReport($db, $start_date, $end_date);
$payments_report = getPaymentsReport($db, $start_date, $end_date);
$tables_report = getTablesReport($db, $start_date, $end_date);
$staff_report = getStaffReport($db, $start_date, $end_date);

// Get online orders specific data
$online_stats = getOnlineOrdersStats($db, $start_date, $end_date);
$delivery_report = getDeliveryReport($db, $start_date, $end_date);

// Get list of online orders for the period
$online_orders_query = "SELECT 
    oo.order_number,
    oo.customer_name,
    oo.customer_phone,
    oo.total,
    oo.status,
    oo.created_at,
    CASE 
        WHEN oo.accepted_at IS NOT NULL AND oo.created_at IS NOT NULL 
        THEN TIMESTAMPDIFF(MINUTE, oo.created_at, oo.accepted_at)
        ELSE NULL 
    END as acceptance_time,
    CASE 
        WHEN oo.delivered_at IS NOT NULL AND oo.created_at IS NOT NULL 
        THEN TIMESTAMPDIFF(MINUTE, oo.created_at, oo.delivered_at)
        ELSE NULL 
    END as delivery_time
FROM online_orders oo 
WHERE DATE(oo.created_at) BETWEEN :start_date AND :end_date 
ORDER BY oo.created_at DESC";

$online_orders_stmt = $db->prepare($online_orders_query);
$online_orders_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$online_orders_list = $online_orders_stmt->fetchAll();

// Calcular resumen de pagos (incluye tradicionales y online)
$payment_summary_query = "SELECT 
    method,
    COUNT(*) as count,
    SUM(amount) as total
FROM (
    SELECT method, amount FROM payments p
    JOIN orders o ON p.order_id = o.id
    WHERE DATE(p.created_at) BETWEEN :start_date AND :end_date
    
    UNION ALL
    
    SELECT method, amount FROM online_orders_payments op
    JOIN online_orders oo ON op.online_order_id = oo.id
    WHERE DATE(op.created_at) BETWEEN :start_date AND :end_date
) combined_payments
GROUP BY method";

$payment_summary_stmt = $db->prepare($payment_summary_query);
$payment_summary_stmt->execute(['start_date' => $start_date, 'end_date' => $end_date]);
$payment_summary_results = $payment_summary_stmt->fetchAll(PDO::FETCH_ASSOC);

$payment_summary = [];
foreach ($payment_summary_results as $row) {
    $payment_summary[$row['method']] = [
        'count' => $row['count'],
        'total' => $row['total']
    ];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/datatables/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
    <!-- Tema dinámico -->
<?php if (file_exists('../assets/css/generate-theme.php')): ?>
    <link rel="stylesheet" href="../assets/css/generate-theme.php?v=<?php echo time(); ?>">
<?php endif; ?>

<?php
// Incluir sistema de temas
$theme_file = '../config/theme.php';
if (file_exists($theme_file)) {
    require_once $theme_file;
    try {
        $database = new Database();
        $db_theme = $database->getConnection();
        $theme_manager = new ThemeManager($db_theme);
        $current_theme = $theme_manager->getThemeSettings();
    } catch (Exception $e) {
        $current_theme = array(
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'sidebar_width' => '280px'
        );
    }
} else {
    $current_theme = array(
        'primary_color' => '#667eea',
        'secondary_color' => '#764ba2',
        'sidebar_width' => '280px'
    );
}
?>
    <style>
/* Extensiones específicas para reportes */
:root {
    --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --reports-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
}

/* Mobile Top Bar */
.mobile-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: var(--primary-gradient);
    color: var(--text-white) !important;
    padding: 1rem;
    display: none;
}

.menu-toggle {
    background: none;
    border: none;
    color: var(--text-white) !important;
    font-size: 1.2rem;
}

/* Sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: var(--reports-sidebar-width);
    height: 100vh;
    background: var(--primary-gradient);
    color: var(--text-white) !important;
    z-index: 1030;
    transition: transform var(--transition-base);
    overflow-y: auto;
    padding: 1.5rem;
}

.sidebar-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1020;
    display: none;
}

.sidebar .nav-link {
    color: rgba(255, 255, 255, 0.8) !important;
    padding: 0.75rem 1rem;
    border-radius: var(--border-radius-base);
    margin-bottom: 0.25rem;
    transition: var(--transition-base);
    display: flex;
    align-items: center;
    text-decoration: none;
}

.sidebar .nav-link:hover,
.sidebar .nav-link.active {
    background: rgba(255, 255, 255, 0.1);
    color: var(--text-white) !important;
}

.sidebar-close {
    position: absolute;
    top: 1rem;
    right: 1rem;
    background: rgba(255, 255, 255, 0.1);
    border: none;
    color: var(--text-white) !important;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: none;
}

/* Main content - FORZAR COLORES CLAROS */
.main-content {
    margin-left: var(--reports-sidebar-width);
    padding: 2rem;
    min-height: 100vh;
    transition: margin-left var(--transition-base);
    background: #f8f9fa !important;
    color: #212529 !important;
}

/* Page header - COLORES FIJOS */
.page-header {
            background: var(--primary-gradient) !important;
            color: var(--text-white, white) !important;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0,0,0,0.05);
        }

/* Report cards - COLORES FIJOS */
.report-card {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
    transition: transform 0.3s;
}

.report-card:hover {
    transform: translateY(-3px);
}

/* Statistics cards - COLORES FIJOS */
.stat-card {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    text-align: center;
    transition: transform 0.3s;
    height: 100%;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--text-white) !important;
    margin: 0 auto 1rem auto;
}

/* Usar variables del tema para gradientes de iconos */
.bg-revenue { 
    background: linear-gradient(45deg, var(--success-color), #20c997) !important; 
}
.bg-orders { 
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)) !important; 
}
.bg-products { 
    background: linear-gradient(45deg, var(--warning-color), #fd7e14) !important; 
}
.bg-customers { 
    background: linear-gradient(45deg, var(--accent-color), var(--secondary-color)) !important; 
}

/* Filter section - COLORES FIJOS */
.filter-section {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: 15px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
}

/* Report tabs - COLORES FIJOS */
.report-tabs {
    background: #ffffff !important;
    border-radius: 15px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
}

.report-tabs .nav-tabs {
    border-bottom: none;
    background: #f8f9fa !important;
}

.report-tabs .nav-link {
    border: none;
    border-radius: 0;
    color: #6c757d !important;
    font-weight: 500;
}

.report-tabs .nav-link.active {
    background: #ffffff !important;
    color: var(--primary-color) !important;
    border-bottom: 3px solid var(--primary-color);
}

.tab-content {
    padding: 2rem;
    background: #ffffff !important;
    color: #212529 !important;
}

/* Formularios - COLORES FIJOS */
.form-control, .form-select {
    background: #ffffff !important;
    color: #212529 !important;
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius-base);
}

.form-control:focus, .form-select:focus {
    background: #ffffff !important;
    color: #212529 !important;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.form-label {
    color: #212529 !important;
    font-weight: 500;
}

/* Tablas - COLORES FIJOS */
.table {
    background: #ffffff !important;
    color: #212529 !important;
    margin-bottom: 0;
}

.table th,
.table td {
    color: #212529 !important;
    border-bottom: 1px solid #dee2e6;
}

.table th {
    background: #f8f9fa !important;
    color: #212529 !important;
}

.table-hover tbody tr:hover {
    background: rgba(0, 0, 0, 0.02) !important;
}

.table-striped tbody tr:nth-of-type(odd) {
    background: rgba(0, 0, 0, 0.01) !important;
}

/* Chart containers */
.chart-container {
    position: relative;
    height: 400px;
    margin-bottom: 2rem;
}

.chart-small {
    height: 300px;
}

/* DataTables customization */
.dataTables_wrapper {
    padding: 0;
    color: #212529 !important;
}

.dataTables_wrapper .dataTables_filter input {
    background: #ffffff !important;
    color: #212529 !important;
    border: 1px solid #dee2e6;
}

.dataTables_wrapper .dataTables_length select {
    background: #ffffff !important;
    color: #212529 !important;
    border: 1px solid #dee2e6;
}

/* Text colors - FORZADOS */
h1, h2, h3, h4, h5, h6 {
    color: #212529 !important;
}

p, span, div {
    color: inherit;
}

.text-muted {
    color: #fff !important;
}

/* Badges con colores del tema */
.badge.bg-success { 
    background: var(--success-color) !important; 
    color: var(--text-white) !important;
}

.badge.bg-warning { 
    background: var(--warning-color) !important; 
    color: #212529 !important;
}

.badge.bg-danger { 
    background: var(--danger-color) !important; 
    color: var(--text-white) !important;
}

.badge.bg-info { 
    background: var(--info-color) !important; 
    color: var(--text-white) !important;
}

.badge.bg-primary { 
    background: var(--primary-color) !important; 
    color: var(--text-white) !important;
}

.badge.bg-secondary { 
    background: var(--text-secondary) !important; 
    color: var(--text-white) !important;
}

/* Botones con tema */
.btn-primary {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: var(--text-white) !important;
}

.btn-success {
    background: var(--success-color) !important;
    border-color: var(--success-color) !important;
    color: var(--text-white) !important;
}

.btn-info {
    background: var(--info-color) !important;
    border-color: var(--info-color) !important;
    color: var(--text-white) !important;
}

/* Print styles */
@media print {
    .sidebar,
    .mobile-topbar,
    .no-print,
    .filter-section {
        display: none !important;
    }
    
    .main-content {
        margin-left: 0;
        padding: 0;
    }
    
    .report-card {
        break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
}

/* Mobile responsive */
@media (max-width: 991.98px) {
    .mobile-topbar {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .sidebar {
        transform: translateX(-100%);
        width: 100%;
        max-width: 350px;
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .sidebar-close {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .main-content {
        margin-left: 0;
        padding: 1rem;
        padding-top: 5rem;
    }

    .chart-container {
        height: 300px;
    }

    .chart-small {
        height: 250px;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 0.5rem;
        padding-top: 4.5rem;
    }

    .page-header,
    .filter-section,
    .report-card {
        padding: 1rem;
    }

    .stat-card {
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .chart-container {
        height: 250px;
    }
}
.system-header .container-fluid {
    height: 60px;
    display: flex
;
    align-items: center;
    padding: 0 1rem;
    background-color: white;
}
.dropdown-menu.show {
    display: block;
    background: var(--primary-gradient);
}

.dropdown-header {
    padding: 0.75rem 1rem;
    background: var(--primary-gradient) !important;
    border-radius: 10px 10px 0 0;
}
</style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-chart-bar me-2"></i>
                        Centro de Reportes
                    </h2>
                    <p class="text-muted mb-0">Análisis completo del negocio</p>
                </div>
                <div class="d-flex gap-2 no-print">
                    <button class="btn btn-success" onclick="exportReport()">
                        <i class="fas fa-file-excel me-1"></i>
                        Exportar
                    </button>
                    <button class="btn btn-info" onclick="window.print()">
                        <i class="fas fa-print me-1"></i>
                        Imprimir
                    </button>
                </div>
            </div>
        </div>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="GET" class="row align-items-end g-3">
                <div class="col-md-3">
                    <label class="form-label">Fecha Inicio</label>
                    <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Fecha Fin</label>
                    <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo de Reporte</label>
                    <select class="form-select" name="report_type">
                        <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Ventas</option>
                        <option value="products" <?php echo $report_type === 'products' ? 'selected' : ''; ?>>Productos</option>
                        <option value="payments" <?php echo $report_type === 'payments' ? 'selected' : ''; ?>>Pagos</option>
                        <option value="tables" <?php echo $report_type === 'tables' ? 'selected' : ''; ?>>Mesas</option>
                        <option value="staff" <?php echo $report_type === 'staff' ? 'selected' : ''; ?>>Personal</option>
                        <option value="online_orders" <?php echo $report_type === 'online_orders' ? 'selected' : ''; ?>>Pedidos Online</option>
                        <option value="delivery" <?php echo $report_type === 'delivery' ? 'selected' : ''; ?>>Delivery</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i>
                        Generar Reporte
                    </button>
                </div>
            </form>
            
            <!-- Quick Date Filters -->
            <div class="mt-3">
                <div class="btn-group" role="group">
                    <a href="?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>&report_type=<?php echo $report_type; ?>" 
                       class="btn btn-outline-secondary btn-sm">Hoy</a>
                    <a href="?start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>&report_type=<?php echo $report_type; ?>" 
                       class="btn btn-outline-secondary btn-sm">Última Semana</a>
                    <a href="?start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-d'); ?>&report_type=<?php echo $report_type; ?>" 
                       class="btn btn-outline-secondary btn-sm">Este Mes</a>
                    <a href="?start_date=<?php echo date('Y-m-01', strtotime('-1 month')); ?>&end_date=<?php echo date('Y-m-t', strtotime('-1 month')); ?>&report_type=<?php echo $report_type; ?>" 
                       class="btn btn-outline-secondary btn-sm">Mes Anterior</a>
                </div>
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="row g-3 g-md-4 mb-4">
            <div class="col-6 col-lg-2">
                <div class="stat-card">
                    <div class="stat-icon bg-revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h3 class="mb-1"><?php echo formatPrice($summary_stats['total_revenue'] ?? 0); ?></h3>
                    <p class="text-muted mb-0 small">Ingresos Totales</p>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card">
                    <div class="stat-icon bg-orders">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $summary_stats['completed_orders'] ?? 0; ?></h3>
                    <p class="text-muted mb-0 small">Órdenes Completadas</p>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card">
                    <div class="stat-icon bg-products">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h3 class="mb-1"><?php echo formatPrice($summary_stats['average_order_value'] ?? 0); ?></h3>
                    <p class="text-muted mb-0 small">Ticket Promedio</p>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card">
                    <div class="stat-icon bg-customers">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <h3 class="mb-1"><?php echo $summary_stats['active_days'] ?? 0; ?></h3>
                    <p class="text-muted mb-0 small">Días Activos</p>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(45deg, #ff6b6b, #ffa500);">
                        <i class="fas fa-globe"></i>
                    </div>
                    <h3 class="mb-1"><?php echo formatPrice($summary_stats['online_revenue'] ?? 0); ?></h3>
                    <p class="text-muted mb-0 small">Ventas Online</p>
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(45deg, #667eea, #764ba2);">
                        <i class="fas fa-motorcycle"></i>
                    </div>
                    <h3 class="mb-1"><?php echo formatPrice($summary_stats['delivery_revenue'] ?? 0); ?></h3>
                    <p class="text-muted mb-0 small">Delivery Total</p>
                </div>
            </div>
        </div>

        <!-- Report Content -->
        <div class="report-tabs">
            <ul class="nav nav-tabs" id="reportTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#sales" type="button">
                        <i class="fas fa-chart-line me-2"></i>Ventas Diarias
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="products-tab" data-bs-toggle="tab" data-bs-target="#products" type="button">
                        <i class="fas fa-utensils me-2"></i>Productos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="payments-tab" data-bs-toggle="tab" data-bs-target="#payments" type="button">
                        <i class="fas fa-credit-card me-2"></i>Métodos de Pago
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tables-tab" data-bs-toggle="tab" data-bs-target="#tables" type="button">
                        <i class="fas fa-table me-2"></i>Rendimiento de Mesas
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="staff-tab" data-bs-toggle="tab" data-bs-target="#staff" type="button">
                        <i class="fas fa-users me-2"></i>Personal
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="online-orders-tab" data-bs-toggle="tab" data-bs-target="#online-orders" type="button">
                        <i class="fas fa-globe me-2"></i>Pedidos Online
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="delivery-report-tab" data-bs-toggle="tab" data-bs-target="#delivery-report" type="button">
                        <i class="fas fa-motorcycle me-2"></i>Delivery Completo
                    </button>
                </li>
            </ul>
            
            <div class="tab-content" id="reportTabsContent">
                <!-- Sales Report Tab -->
                <div class="tab-pane fade show active" id="sales" role="tabpanel">
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="chart-container">
                                <canvas id="salesChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="chart-container chart-small">
                                <canvas id="orderTypeChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped" id="salesTable">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Órdenes</th>
                                    <th>Completadas</th>
                                    <th>Canceladas</th>
                                    <th>Ventas Totales</th>
                                    <th>Ticket Promedio</th>
                                    <th>Mesa</th>
                                    <th>Delivery</th>
                                    <th>Retiro</th>
                                    <th>Online</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales_report as $day): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($day['date'])); ?></td>
                                    <td><?php echo $day['total_orders']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $day['completed_orders']; ?></span></td>
                                    <td><span class="badge bg-danger"><?php echo $day['cancelled_orders']; ?></span></td>
                                    <td><strong><?php echo formatPrice($day['total_sales']); ?></strong></td>
                                    <td><?php echo formatPrice($day['average_order_value'] ?? 0); ?></td>
                                    <td><?php echo formatPrice($day['dine_in_sales']); ?></td>
                                    <td><?php echo formatPrice($day['delivery_sales']); ?></td>
                                    <td><?php echo formatPrice($day['takeout_sales']); ?></td>
                                    <td>
                                        <span class="badge bg-warning text-dark">
                                            <?php echo formatPrice($day['online_sales']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Products Report Tab -->
                <div class="tab-pane fade" id="products" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="chart-container chart-small">
                                <canvas id="topProductsChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="chart-container chart-small">
                                <canvas id="profitabilityChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Categoría</th>
                                    <th>Cantidad Vendida</th>
                                    <th>Ingresos</th>
                                    <th>Costo Total</th>
                                    <th>Ganancia</th>
                                    <th>Margen %</th>
                                    <th>Órdenes</th>
                                    <th>Promedio por Orden</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products_report as $product): ?>
                                <?php 
                                $margin = $product['total_revenue'] > 0 ? 
                                    (($product['profit'] / $product['total_revenue']) * 100) : 0;
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($product['product_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($product['category_name'] ?? 'Sin categoría'); ?></td>
                                    <td><span class="badge bg-primary"><?php echo $product['total_quantity']; ?></span></td>
                                    <td><strong><?php echo formatPrice($product['total_revenue']); ?></strong></td>
                                    <td><?php echo formatPrice($product['total_cost']); ?></td>
                                    <td class="<?php echo $product['profit'] > 0 ? 'text-success' : 'text-danger'; ?>">
                                        <strong><?php echo formatPrice($product['profit']); ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $margin > 50 ? 'success' : ($margin > 30 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($margin, 1); ?>%
                                        </span>
                                    </td>
                                    <td><?php echo $product['orders_count']; ?></td>
                                    <td><?php echo number_format($product['avg_quantity_per_order'], 1); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Payments Report Tab -->
                <div class="tab-pane fade" id="payments" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="chart-container chart-small">
                                <canvas id="paymentMethodsChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5><i class="fas fa-credit-card me-2"></i>Resumen de Pagos</h5>
                                <?php
                                $method_names = [
                                    'cash' => 'Efectivo',
                                    'card' => 'Tarjeta',
                                    'transfer' => 'Transferencia',
                                    'qr' => 'QR/Digital'
                                ];
                                ?>
                                
                                <?php foreach ($payment_summary as $method => $data): ?>
                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                    <div>
                                        <strong><?php echo $method_names[$method] ?? $method; ?></strong>
                                        <small class="text-muted d-block"><?php echo $data['count']; ?> transacciones</small>
                                    </div>
                                    <div class="text-end">
                                        <strong><?php echo formatPrice($data['total']); ?></strong>
                                        <small class="text-muted d-block">
                                            <?php echo formatPrice($data['total'] / max($data['count'], 1)); ?> promedio
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped" id="paymentsTable">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Método</th>
                                    <th>Origen</th>
                                    <th>Transacciones</th>
                                    <th>Monto Total</th>
                                    <th>Monto Promedio</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments_report as $payment): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($payment['date'])); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo $method_names[$payment['method']] ?? $payment['method']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $payment['payment_type'] === 'online' ? 'success' : 'primary'; ?>">
                                            <?php echo $payment['payment_type'] === 'online' ? 'Online' : 'Tradicional'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $payment['transaction_count']; ?></td>
                                    <td><strong><?php echo formatPrice($payment['total_amount']); ?></strong></td>
                                    <td><?php echo formatPrice($payment['average_amount']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Tables Report Tab -->
                <div class="tab-pane fade" id="tables" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="chart-container chart-small">
                                <canvas id="tablePerformanceChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5><i class="fas fa-table me-2"></i>Estadísticas de Mesas</h5>
                                <?php
                                $total_table_revenue = array_sum(array_column($tables_report, 'total_revenue'));
                                $total_table_orders = array_sum(array_column($tables_report, 'completed_orders'));
                                $avg_revenue_per_table = count($tables_report) > 0 ? $total_table_revenue / count($tables_report) : 0;
                                ?>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h4 class="text-primary"><?php echo count($tables_report); ?></h4>
                                        <small class="text-muted">Mesas Totales</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success"><?php echo $total_table_orders; ?></h4>
                                        <small class="text-muted">Órdenes</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-info"><?php echo formatPrice($avg_revenue_per_table); ?></h4>
                                        <small class="text-muted">Promedio/Mesa</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped" id="tablesTable">
                            <thead>
                                <tr>
                                    <th>Mesa</th>
                                    <th>Capacidad</th>
                                    <th>Órdenes Totales</th>
                                    <th>Órdenes Completadas</th>
                                    <th>Ingresos Totales</th>
                                    <th>Ticket Promedio</th>
                                    <th>Rendimiento</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tables_report as $table): ?>
                                <?php 
                                $performance = $table['total_revenue'] / max($table['capacity'], 1);
                                $performance_class = $performance > 1000 ? 'success' : ($performance > 500 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($table['number']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $table['capacity']; ?> pers.</span>
                                    </td>
                                    <td><?php echo $table['total_orders']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $table['completed_orders']; ?></span></td>
                                    <td><strong><?php echo formatPrice($table['total_revenue']); ?></strong></td>
                                    <td><?php echo formatPrice($table['average_order_value'] ?? 0); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $performance_class; ?>">
                                            <?php echo formatPrice($performance); ?>/cap.
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Staff Report Tab -->
                <div class="tab-pane fade" id="staff" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="chart-container chart-small">
                                <canvas id="staffPerformanceChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5><i class="fas fa-users me-2"></i>Resumen del Personal</h5>
                                <?php
                                $total_staff_sales = array_sum(array_column($staff_report, 'total_sales'));
                                $total_staff_orders = array_sum(array_column($staff_report, 'completed_orders'));
                                $active_staff = count(array_filter($staff_report, function($s) { return $s['total_orders'] > 0; }));
                                ?>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h4 class="text-primary"><?php echo $active_staff; ?></h4>
                                        <small class="text-muted">Personal Activo</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success"><?php echo $total_staff_orders; ?></h4>
                                        <small class="text-muted">Órdenes</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-info"><?php echo formatPrice($total_staff_sales); ?></h4>
                                        <small class="text-muted">Ventas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped" id="staffTable">
                            <thead>
                                <tr>
                                    <th>Empleado</th>
                                    <th>Rol</th>
                                    <th>Órdenes Totales</th>
                                    <th>Completadas</th>
                                    <th>Canceladas</th>
                                    <th>Ventas Totales</th>
                                    <th>Venta Promedio</th>
                                    <th>Online Aceptadas</th>
                                    <th>Online Rechazadas</th>
                                    <th>Eficiencia</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($staff_report as $staff): ?>
                                <?php 
                                $completion_rate = $staff['total_orders'] > 0 ? 
                                    ($staff['completed_orders'] / $staff['total_orders']) * 100 : 0;
                                $efficiency_class = $completion_rate > 90 ? 'success' : ($completion_rate > 75 ? 'warning' : 'danger');
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($staff['full_name']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-info"><?php echo ucfirst($staff['role_name']); ?></span>
                                    </td>
                                    <td><?php echo $staff['total_orders']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $staff['completed_orders']; ?></span></td>
                                    <td><span class="badge bg-danger"><?php echo $staff['cancelled_orders']; ?></span></td>
                                    <td><strong><?php echo formatPrice($staff['total_sales']); ?></strong></td>
                                    <td><?php echo formatPrice($staff['average_sale'] ?? 0); ?></td>
                                    <td>
                                        <?php if ($staff['online_accepted_orders'] > 0): ?>
                                            <span class="badge bg-success"><?php echo $staff['online_accepted_orders']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($staff['online_rejected_orders'] > 0): ?>
                                            <span class="badge bg-danger"><?php echo $staff['online_rejected_orders']; ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $efficiency_class; ?>">
                                            <?php echo number_format($completion_rate, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Online Orders Tab -->
                <div class="tab-pane fade" id="online-orders" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="chart-container chart-small">
                                <canvas id="onlineOrdersStatusChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5><i class="fas fa-globe me-2"></i>Estadísticas Online</h5>
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h4 class="text-primary"><?php echo $online_stats['total_online_orders'] ?? 0; ?></h4>
                                        <small class="text-muted">Total Pedidos</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-success"><?php echo number_format($online_stats['avg_delivery_time'] ?? 0, 1); ?>min</h4>
                                        <small class="text-muted">Tiempo Promedio</small>
                                    </div>
                                    <div class="col-4">
                                        <h4 class="text-info"><?php echo number_format(($online_stats['delivered_orders'] ?? 0) / max($online_stats['total_online_orders'] ?? 1, 1) * 100, 1); ?>%</h4>
                                        <small class="text-muted">Tasa Éxito</small>
                                    </div>
                                </div>
                                
                                <hr class="my-3">
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Entregados</span>
                                    <span class="badge bg-success"><?php echo $online_stats['delivered_orders'] ?? 0; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Pendientes</span>
                                    <span class="badge bg-warning"><?php echo $online_stats['pending_orders'] ?? 0; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>En Preparación</span>
                                    <span class="badge bg-info"><?php echo $online_stats['preparing_orders'] ?? 0; ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Rechazados</span>
                                    <span class="badge bg-danger"><?php echo $online_stats['rejected_orders'] ?? 0; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped" id="onlineOrdersTable">
                            <thead>
                                <tr>
                                    <th>Número Orden</th>
                                    <th>Cliente</th>
                                    <th>Teléfono</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>Tiempo Aceptación</th>
                                    <th>Tiempo Entrega</th>
                                    <th>Fecha</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($online_orders_list as $order): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($order['customer_phone']); ?></td>
                                    <td><strong><?php echo formatPrice($order['total']); ?></strong></td>
                                    <td>
                                        <?php
                                        $status_badges = [
                                            'pending' => 'bg-warning',
                                            'accepted' => 'bg-info',
                                            'preparing' => 'bg-primary',
                                            'ready' => 'bg-success',
                                            'delivered' => 'bg-success',
                                            'rejected' => 'bg-danger'
                                        ];
                                        $status_texts = [
                                            'pending' => 'Pendiente',
                                            'accepted' => 'Aceptado',
                                            'preparing' => 'Preparando',
                                            'ready' => 'Listo',
                                            'delivered' => 'Entregado',
                                            'rejected' => 'Rechazado'
                                        ];
                                        ?>
                                        <span class="badge <?php echo $status_badges[$order['status']] ?? 'bg-secondary'; ?>">
                                            <?php echo $status_texts[$order['status']] ?? $order['status']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($order['acceptance_time']): ?>
                                            <span class="badge bg-<?php echo $order['acceptance_time'] <= 5 ? 'success' : ($order['acceptance_time'] <= 15 ? 'warning' : 'danger'); ?>">
                                                <?php echo $order['acceptance_time']; ?> min
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['delivery_time']): ?>
                                            <span class="badge bg-<?php echo $order['delivery_time'] <= 30 ? 'success' : ($order['delivery_time'] <= 60 ? 'warning' : 'danger'); ?>">
                                                <?php echo $order['delivery_time']; ?> min
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Delivery Report Tab -->
                <div class="tab-pane fade" id="delivery-report" role="tabpanel">
                    <div class="row mb-4">
                        <div class="col-lg-6">
                            <div class="chart-container chart-small">
                                <canvas id="deliveryComparisonChart"></canvas>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="report-card">
                                <h5><i class="fas fa-motorcycle me-2"></i>Comparación Delivery</h5>
                                <?php
                                $traditional_delivery = array_filter($delivery_report, function($d) { return $d['delivery_type'] === 'traditional'; });
                                $online_delivery = array_filter($delivery_report, function($d) { return $d['delivery_type'] === 'online'; });
                                $traditional_delivery = reset($traditional_delivery) ?: ['total_deliveries' => 0, 'total_revenue' => 0, 'avg_delivery_time' => 0];
                                $online_delivery = reset($online_delivery) ?: ['total_deliveries' => 0, 'total_revenue' => 0, 'avg_delivery_time' => 0];
                                ?>
                                
                                <div class="row text-center mb-3">
                                    <div class="col-6">
                                        <h5 class="text-primary">Tradicional</h5>
                                        <p class="mb-1"><strong><?php echo $traditional_delivery['total_deliveries']; ?></strong> entregas</p>
                                        <p class="mb-0"><?php echo formatPrice($traditional_delivery['total_revenue']); ?></p>
                                    </div>
                                    <div class="col-6">
                                        <h5 class="text-success">Online</h5>
                                        <p class="mb-1"><strong><?php echo $online_delivery['total_deliveries']; ?></strong> entregas</p>
                                        <p class="mb-0"><?php echo formatPrice($online_delivery['total_revenue']); ?></p>
                                    </div>
                                </div>
                                
                                <hr>
                                
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span>Tiempo Promedio Tradicional</span>
                                    <span class="badge bg-info">
                                        <?php echo $traditional_delivery['avg_delivery_time'] ? number_format($traditional_delivery['avg_delivery_time'], 1) . ' min' : 'N/A'; ?>
                                    </span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>Tiempo Promedio Online</span>
                                    <span class="badge bg-success">
                                        <?php echo $online_delivery['avg_delivery_time'] ? number_format($online_delivery['avg_delivery_time'], 1) . ' min' : 'N/A'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped" id="deliveryTable">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Total Entregas</th>
                                    <th>Completadas</th>
                                    <th>Canceladas</th>
                                    <th>Ingresos Totales</th>
                                    <th>Ticket Promedio</th>
                                    <th>Tiempo Promedio</th>
                                    <th>Tarifas Delivery</th>
                                    <th>Tasa Éxito</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($delivery_report as $delivery): ?>
                                <?php 
                                $success_rate = $delivery['total_deliveries'] > 0 ? 
                                    ($delivery['completed_deliveries'] / $delivery['total_deliveries']) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php echo $delivery['delivery_type'] === 'online' ? 'success' : 'primary'; ?>">
                                            <?php echo $delivery['delivery_type'] === 'online' ? 'Online' : 'Tradicional'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $delivery['total_deliveries']; ?></td>
                                    <td><span class="badge bg-success"><?php echo $delivery['completed_deliveries']; ?></span></td>
                                    <td><span class="badge bg-danger"><?php echo $delivery['cancelled_deliveries']; ?></span></td>
                                    <td><strong><?php echo formatPrice($delivery['total_revenue']); ?></strong></td>
                                    <td><?php echo formatPrice($delivery['average_order_value'] ?? 0); ?></td>
                                    <td>
                                        <?php if ($delivery['avg_delivery_time']): ?>
                                            <span class="badge bg-<?php echo $delivery['avg_delivery_time'] <= 30 ? 'success' : ($delivery['avg_delivery_time'] <= 60 ? 'warning' : 'danger'); ?>">
                                                <?php echo number_format($delivery['avg_delivery_time'], 1); ?> min
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatPrice($delivery['total_delivery_fees']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $success_rate > 90 ? 'success' : ($success_rate > 75 ? 'warning' : 'danger'); ?>">
                                            <?php echo number_format($success_rate, 1); ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Analysis Cards -->
        <div class="row">
            <div class="col-lg-6">
                <div class="report-card">
                    <h5><i class="fas fa-trending-up me-2"></i>Análisis de Tendencias</h5>
                    <?php
                    $current_period_sales = array_sum(array_column($sales_report, 'total_sales'));
                    $current_period_orders = array_sum(array_column($sales_report, 'completed_orders'));
                    
                    // Calculate previous period for comparison
                    $days_diff = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24) + 1;
                    $prev_start = date('Y-m-d', strtotime($start_date . " -{$days_diff} days"));
                    $prev_end = date('Y-m-d', strtotime($start_date . " -1 day"));
                    
                    $prev_query = "SELECT 
                        SUM(CASE WHEN status = 'delivered' THEN total ELSE 0 END) as sales,
                        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as orders
                        FROM (
                            SELECT status, total FROM orders WHERE DATE(created_at) BETWEEN :prev_start AND :prev_end
                            UNION ALL
                            SELECT status, total FROM online_orders WHERE DATE(created_at) BETWEEN :prev_start AND :prev_end
                        ) combined";
                    $prev_stmt = $db->prepare($prev_query);
                    $prev_stmt->execute(['prev_start' => $prev_start, 'prev_end' => $prev_end]);
                    $prev_data = $prev_stmt->fetch();
                    
                    $sales_change = $prev_data['sales'] > 0 ? 
                        (($current_period_sales - $prev_data['sales']) / $prev_data['sales']) * 100 : 0;
                    $orders_change = $prev_data['orders'] > 0 ? 
                        (($current_period_orders - $prev_data['orders']) / $prev_data['orders']) * 100 : 0;
                    ?>
                    
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center py-3 border-end">
                                <h4 class="<?php echo $sales_change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($sales_change >= 0 ? '+' : '') . number_format($sales_change, 1); ?>%
                                </h4>
                                <small class="text-muted">Cambio en Ventas</small>
                                <div class="mt-1">
                                    <i class="fas fa-arrow-<?php echo $sales_change >= 0 ? 'up text-success' : 'down text-danger'; ?>"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center py-3">
                                <h4 class="<?php echo $orders_change >= 0 ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo ($orders_change >= 0 ? '+' : '') . number_format($orders_change, 1); ?>%
                                </h4>
                                <small class="text-muted">Cambio en Órdenes</small>
                                <div class="mt-1">
                                    <i class="fas fa-arrow-<?php echo $orders_change >= 0 ? 'up text-success' : 'down text-danger'; ?>"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="report-card">
                    <h5><i class="fas fa-bullseye me-2"></i>Objetivos y Métricas</h5>
                    <?php
                    $monthly_goal = 100000; // Meta mensual ejemplo
                    $daily_avg = $current_period_sales / max($summary_stats['active_days'], 1);
                    $goal_progress = ($current_period_sales / $monthly_goal) * 100;
                    ?>
                    
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small>Meta Mensual</small>
                            <small><?php echo number_format($goal_progress, 1); ?>%</small>
                        </div>
                        <div class="progress">
                            <div class="progress-bar bg-<?php echo $goal_progress > 75 ? 'success' : ($goal_progress > 50 ? 'warning' : 'danger'); ?>" 
                                 style="width: <?php echo min($goal_progress, 100); ?>%"></div>
                        </div>
                        <small class="text-muted">
                            <?php echo formatPrice($current_period_sales); ?> de <?php echo formatPrice($monthly_goal); ?>
                        </small>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <h5 class="text-info"><?php echo formatPrice($daily_avg); ?></h5>
                            <small class="text-muted">Promedio Diario</small>
                        </div>
                        <div class="col-6">
                            <h5 class="text-primary"><?php echo number_format($summary_stats['total_orders'] / max($summary_stats['active_days'], 1), 1); ?></h5>
                            <small class="text-muted">Órdenes/Día</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
// Inicializar todo cuando jQuery esté listo
$(document).ready(function() {
    console.log('jQuery cargado:', typeof $ !== 'undefined');
    console.log('DataTables disponible:', typeof $.fn.DataTable !== 'undefined');
    
    initializeMobileMenu();
    
    // Retrasar DataTables para asegurar carga completa
    setTimeout(function() {
        initializeDataTables();
    }, 500);
    
    initializeCharts();
});

function initializeMobileMenu() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarClose = document.getElementById('sidebarClose');

    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('show');
            sidebarBackdrop.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                setTimeout(closeSidebar, 100);
            }
        });
    });

    window.addEventListener('resize', function() {
        if (window.innerWidth >= 992) {
            closeSidebar();
        }
    });
}

function closeSidebar() {
    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    
    if (sidebar) sidebar.classList.remove('show');
    if (sidebarBackdrop) sidebarBackdrop.classList.remove('show');
    document.body.style.overflow = '';
}

function initializeDataTables() {
    console.log('Intentando inicializar DataTables...');
    
    // Verificar que DataTables esté disponible
    if (typeof $ === 'undefined') {
        console.error('jQuery no está cargado');
        return;
    }
    
    if (typeof $.fn.DataTable === 'undefined') {
        console.error('DataTables no está cargado');
        console.log('Intentando cargar DataTables dinámicamente...');
        
        // Cargar DataTables dinámicamente como respaldo
        const script1 = document.createElement('script');
        script1.src = 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js';
        script1.onload = function() {
            const script2 = document.createElement('script');
            script2.src = 'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js';
            script2.onload = function() {
                setTimeout(initializeDataTablesActual, 200);
            };
            document.head.appendChild(script2);
        };
        document.head.appendChild(script1);
        return;
    }
    
    initializeDataTablesActual();
}

function initializeDataTablesActual() {
    console.log('Inicializando DataTables con pedidos online...');
    
    const tableConfig = {
        "language": {
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando registros del _START_ al _END_ de un total de _TOTAL_ registros",
            "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
            "infoFiltered": "(filtrado de un total de _MAX_ registros)",
            "search": "Buscar:",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        "pageLength": 10,
        "searching": true,
        "ordering": true,
        "responsive": true,
        "autoWidth": false,
        "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
               '<"row"<"col-sm-12"tr>>' +
               '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>'
    };

    try {
        // Inicializar todas las tablas existentes
        if ($('#salesTable').length && !$.fn.dataTable.isDataTable('#salesTable')) {
            $('#salesTable').DataTable(tableConfig);
            console.log('salesTable inicializada');
        }
        if ($('#productsTable').length && !$.fn.dataTable.isDataTable('#productsTable')) {
            $('#productsTable').DataTable(tableConfig);
            console.log('productsTable inicializada');
        }
        if ($('#paymentsTable').length && !$.fn.dataTable.isDataTable('#paymentsTable')) {
            $('#paymentsTable').DataTable(tableConfig);
            console.log('paymentsTable inicializada');
        }
        if ($('#tablesTable').length && !$.fn.dataTable.isDataTable('#tablesTable')) {
            $('#tablesTable').DataTable(tableConfig);
            console.log('tablesTable inicializada');
        }
        if ($('#staffTable').length && !$.fn.dataTable.isDataTable('#staffTable')) {
            $('#staffTable').DataTable(tableConfig);
            console.log('staffTable inicializada');
        }
        
        // NUEVAS TABLAS: Pedidos online y delivery
        if ($('#onlineOrdersTable').length && !$.fn.dataTable.isDataTable('#onlineOrdersTable')) {
            $('#onlineOrdersTable').DataTable(tableConfig);
            console.log('onlineOrdersTable inicializada');
        }
        if ($('#deliveryTable').length && !$.fn.dataTable.isDataTable('#deliveryTable')) {
            $('#deliveryTable').DataTable(tableConfig);
            console.log('deliveryTable inicializada');
        }
        
        console.log('Todas las tablas DataTables inicializadas correctamente');
        
    } catch (error) {
        console.error('Error al inicializar DataTables:', error);
    }
}

function initializeCharts() {
    // Sales Chart
    const salesData = <?php echo json_encode($sales_report); ?>;
    if (salesData.length > 0) {
        createSalesChart(salesData);
        createOrderTypeChart(); // Actualizada para incluir online
    }

    // Products Charts
    const productsData = <?php echo json_encode($products_report); ?>;
    if (productsData.length > 0) {
        createTopProductsChart(productsData);
        createProfitabilityChart(productsData);
    }

    // Payment Methods Chart
    createPaymentMethodsChart();

    // Tables Chart
    const tablesData = <?php echo json_encode($tables_report); ?>;
    if (tablesData.length > 0) {
        createTablePerformanceChart(tablesData);
    }

    // Staff Chart
    const staffData = <?php echo json_encode($staff_report); ?>;
    if (staffData.length > 0) {
        createStaffPerformanceChart(staffData);
    }

    // NEW: Online Orders Charts
    createOnlineOrdersStatusChart();
    
    // NEW: Delivery Comparison Chart
    createDeliveryComparisonChart();
}

function createSalesChart(data) {
    const ctx = document.getElementById('salesChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(d => new Date(d.date).toLocaleDateString('es-ES')),
            datasets: [{
                label: 'Ventas Diarias',
                data: data.map(d => parseFloat(d.total_sales)),
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }, {
                label: 'Órdenes Completadas',
                data: data.map(d => parseInt(d.completed_orders)),
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Ventas ($)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Órdenes'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            },
            plugins: {
                legend: {
                    display: true,
                    position: 'top'
                },
                title: {
                    display: true,
                    text: 'Evolución de Ventas y Órdenes'
                }
            }
        }
    });
}

function createOrderTypeChart() {
    const summaryStats = <?php echo json_encode($summary_stats); ?>;
    const ctx = document.getElementById('orderTypeChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Mesa', 'Delivery Tradicional', 'Retiro', 'Online'],
            datasets: [{
                data: [
                    parseFloat(summaryStats.dine_in_revenue || 0),
                    parseFloat(summaryStats.delivery_revenue || 0),
                    parseFloat(summaryStats.takeout_revenue || 0),
                    parseFloat(summaryStats.online_revenue || 0)
                ],
                backgroundColor: [
                    '#007bff',  // Azul - Mesa
                    '#28a745',  // Verde - Delivery
                    '#ffc107',  // Amarillo - Retiro
                    '#e83e8c'   // Rosa - Online
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Ventas por Tipo de Orden'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': $' + context.parsed.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

function createTopProductsChart(data) {
    const top10 = data.slice(0, 10);
    const ctx = document.getElementById('topProductsChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: top10.map(p => p.product_name),
            datasets: [{
                label: 'Cantidad Vendida',
                data: top10.map(p => parseInt(p.total_quantity)),
                backgroundColor: '#007bff',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            plugins: {
                title: {
                    display: true,
                    text: 'Top 10 Productos Más Vendidos'
                }
            },
            scales: {
                x: {
                    beginAtZero: true
                }
            }
        }
    });
}

function createProfitabilityChart(data) {
    const profitable = data.filter(p => parseFloat(p.profit) > 0).slice(0, 10);
    const ctx = document.getElementById('profitabilityChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'scatter',
        data: {
            datasets: [{
                label: 'Rentabilidad',
                data: profitable.map(p => ({
                    x: parseInt(p.total_quantity),
                    y: parseFloat(p.profit),
                    product: p.product_name
                })),
                backgroundColor: '#28a745',
                borderColor: '#20c997',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Relación Cantidad vs Ganancia'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return context.raw.product + ': ' + 
                                   context.raw.x + ' vendidos, $' +
                                   context.raw.y.toFixed(2) + ' ganancia';
                        }
                    }
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Cantidad Vendida'
                    }
                },
                y: {
                    title: {
                        display: true,
                        text: 'Ganancia ($)'
                    }
                }
            }
        }
    });
}

function createPaymentMethodsChart() {
    const paymentSummary = <?php echo json_encode($payment_summary ?? []); ?>;
    if (Object.keys(paymentSummary).length === 0) return;
    
    const ctx = document.getElementById('paymentMethodsChart').getContext('2d');
    const methodNames = {
        'cash': 'Efectivo',
        'card': 'Tarjeta',
        'transfer': 'Transferencia',
        'qr': 'QR/Digital'
    };
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: Object.keys(paymentSummary).map(m => methodNames[m] || m),
            datasets: [{
                data: Object.values(paymentSummary).map(p => parseFloat(p.total)),
                backgroundColor: ['#28a745', '#007bff', '#17a2b8', '#6f42c1'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Distribución por Método de Pago (Todas las fuentes)'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': $' + context.parsed.toFixed(2) + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

function createTablePerformanceChart(data) {
    const top10Tables = data.slice(0, 10);
    const ctx = document.getElementById('tablePerformanceChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: top10Tables.map(t => t.number),
            datasets: [{
                label: 'Ingresos',
                data: top10Tables.map(t => parseFloat(t.total_revenue)),
                backgroundColor: '#007bff',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Rendimiento por Mesa'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Ingresos ($)'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Mesa'
                    }
                }
            }
        }
    });
}

function createStaffPerformanceChart(data) {
    const activeStaff = data.filter(s => parseFloat(s.total_sales) > 0);
    const ctx = document.getElementById('staffPerformanceChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: activeStaff.map(s => s.full_name.split(' ')[0]),
            datasets: [{
                label: 'Ventas',
                data: activeStaff.map(s => parseFloat(s.total_sales)),
                backgroundColor: '#28a745',
                borderRadius: 5
            }, {
                label: 'Órdenes',
                data: activeStaff.map(s => parseInt(s.completed_orders)),
                backgroundColor: '#007bff',
                borderRadius: 5,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Rendimiento del Personal'
                }
            },
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    title: {
                        display: true,
                        text: 'Ventas ($)'
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    title: {
                        display: true,
                        text: 'Órdenes'
                    },
                    grid: {
                        drawOnChartArea: false,
                    },
                }
            }
        }
    });
}

function createOnlineOrdersStatusChart() {
    const onlineStats = <?php echo json_encode($online_stats ?? []); ?>;
    if (!onlineStats || Object.keys(onlineStats).length === 0) return;
    
    const ctx = document.getElementById('onlineOrdersStatusChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Entregados', 'Pendientes', 'Preparando', 'Listos', 'Rechazados'],
            datasets: [{
                data: [
                    parseInt(onlineStats.delivered_orders || 0),
                    parseInt(onlineStats.pending_orders || 0),
                    parseInt(onlineStats.preparing_orders || 0),
                    parseInt(onlineStats.ready_orders || 0),
                    parseInt(onlineStats.rejected_orders || 0)
                ],
                backgroundColor: [
                    '#28a745', // Verde - Entregados
                    '#ffc107', // Amarillo - Pendientes
                    '#007bff', // Azul - Preparando
                    '#17a2b8', // Cyan - Listos
                    '#dc3545'  // Rojo - Rechazados
                ],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom'
                },
                title: {
                    display: true,
                    text: 'Distribución Estados Pedidos Online'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) : 0;
                            return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });
}

function createDeliveryComparisonChart() {
    const deliveryData = <?php echo json_encode($delivery_report ?? []); ?>;
    if (!deliveryData || deliveryData.length === 0) return;
    
    const traditional = deliveryData.find(d => d.delivery_type === 'traditional') || 
                       { total_deliveries: 0, completed_deliveries: 0, total_revenue: 0 };
    const online = deliveryData.find(d => d.delivery_type === 'online') || 
                  { total_deliveries: 0, completed_deliveries: 0, total_revenue: 0 };
    
    const ctx = document.getElementById('deliveryComparisonChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Entregas Totales', 'Entregas Completadas', 'Ingresos'],
            datasets: [{
                label: 'Delivery Tradicional',
                data: [
                    parseInt(traditional.total_deliveries),
                    parseInt(traditional.completed_deliveries),
                    parseFloat(traditional.total_revenue) / 1000 // Dividir por 1000 para mejor escala
                ],
                backgroundColor: '#007bff',
                borderRadius: 5
            }, {
                label: 'Delivery Online',
                data: [
                    parseInt(online.total_deliveries),
                    parseInt(online.completed_deliveries),
                    parseFloat(online.total_revenue) / 1000 // Dividir por 1000 para mejor escala
                ],
                backgroundColor: '#28a745',
                borderRadius: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Comparación Delivery: Tradicional vs Online'
                },
                legend: {
                    position: 'top'
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            if (context.dataIndex === 2) { // Ingresos
                                return context.dataset.label + ': $' + (context.parsed.y * 1000).toFixed(2);
                            }
                            return context.dataset.label + ': ' + context.parsed.y;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Cantidad / Ingresos (en miles)'
                    }
                }
            }
        }
    });
}

function exportReport() {
    const reportType = new URLSearchParams(window.location.search).get('report_type') || 'sales';
    let csvContent = '';
    let filename = '';
    
    switch(reportType) {
        case 'sales':
            csvContent = generateSalesCSV();
            filename = 'reporte_ventas_' + new Date().toISOString().split('T')[0] + '.csv';
            break;
        case 'products':
            csvContent = generateProductsCSV();
            filename = 'reporte_productos_' + new Date().toISOString().split('T')[0] + '.csv';
            break;
        case 'online_orders':
            csvContent = generateOnlineOrdersCSV();
            filename = 'reporte_pedidos_online_' + new Date().toISOString().split('T')[0] + '.csv';
            break;
        case 'delivery':
            csvContent = generateDeliveryCSV();
            filename = 'reporte_delivery_' + new Date().toISOString().split('T')[0] + '.csv';
            break;
        default:
            alert('Exportación no disponible para este tipo de reporte');
            return;
    }
    
    downloadCSV(csvContent, filename);
}

function generateSalesCSV() {
    const salesData = <?php echo json_encode($sales_report); ?>;
    let csv = 'Fecha,Órdenes Totales,Completadas,Canceladas,Ventas Totales,Ticket Promedio,Mesa,Delivery,Retiro,Online\n';
    
    salesData.forEach(row => {
        csv += [
            row.date,
            row.total_orders,
            row.completed_orders,
            row.cancelled_orders,
            row.total_sales,
            row.average_order_value || 0,
            row.dine_in_sales,
            row.delivery_sales,
            row.takeout_sales,
            row.online_sales
        ].join(',') + '\n';
    });
    
    return csv;
}

function generateProductsCSV() {
    const productsData = <?php echo json_encode($products_report); ?>;
    let csv = 'Producto,Categoría,Cantidad,Ingresos,Costo,Ganancia,Margen %,Órdenes\n';
    
    productsData.forEach(row => {
        const margin = row.total_revenue > 0 ? ((row.profit / row.total_revenue) * 100) : 0;
        csv += [
            '"' + row.product_name.replace(/"/g, '""') + '"',
            '"' + (row.category_name || 'Sin categoría').replace(/"/g, '""') + '"',
            row.total_quantity,
            row.total_revenue,
            row.total_cost,
            row.profit,
            margin.toFixed(2),
            row.orders_count
        ].join(',') + '\n';
    });
    
    return csv;
}

function generateOnlineOrdersCSV() {
    const onlineOrdersData = <?php echo json_encode($online_orders_list ?? []); ?>;
    let csv = 'Número Orden,Cliente,Teléfono,Total,Estado,Tiempo Aceptación (min),Tiempo Entrega (min),Fecha\n';
    
    onlineOrdersData.forEach(row => {
        csv += [
            '"' + (row.order_number || '').replace(/"/g, '""') + '"',
            '"' + (row.customer_name || '').replace(/"/g, '""') + '"',
            '"' + (row.customer_phone || '').replace(/"/g, '""') + '"',
            row.total || 0,
            '"' + (row.status || '').replace(/"/g, '""') + '"',
            row.acceptance_time || '',
            row.delivery_time || '',
            '"' + (row.created_at || '').replace(/"/g, '""') + '"'
        ].join(',') + '\n';
    });
    
    return csv;
}

function generateDeliveryCSV() {
    const deliveryData = <?php echo json_encode($delivery_report ?? []); ?>;
    let csv = 'Tipo,Total Entregas,Completadas,Canceladas,Ingresos Totales,Ticket Promedio,Tiempo Promedio,Tarifas Delivery,Tasa Éxito\n';
    
    deliveryData.forEach(row => {
        const successRate = row.total_deliveries > 0 ? 
            ((row.completed_deliveries / row.total_deliveries) * 100).toFixed(1) : 0;
        
        csv += [
            row.delivery_type === 'online' ? 'Online' : 'Tradicional',
            row.total_deliveries,
            row.completed_deliveries,
            row.cancelled_deliveries,
            row.total_revenue,
            row.average_order_value || 0,
            row.avg_delivery_time || '',
            row.total_delivery_fees,
            successRate + '%'
        ].join(',') + '\n';
    });
    
    return csv;
}

function downloadCSV(csvContent, filename) {
    const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', filename);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// Print optimizations
window.addEventListener('beforeprint', function() {
    // Hide charts that might not print well
    document.querySelectorAll('.chart-container canvas').forEach(canvas => {
        canvas.style.display = 'none';
    });
});

window.addEventListener('afterprint', function() {
    // Restore charts
    document.querySelectorAll('.chart-container canvas').forEach(canvas => {
        canvas.style.display = 'block';
    });
});

// Format price function
function formatPrice(price) {
    return '$' + parseFloat(price).toFixed(2);
}
</script>

<?php include 'footer.php'; ?>
</body>
</html>