<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// admin/products.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Product.php';
require_once '../models/Category.php';

// === Normalize image path: ensure it includes 'admin/' prefix ===
function normalizeImagePath($p) {
    if (empty($p)) return $p;
    // remove leading slashes
    $p = ltrim($p, '/');
    if (strpos($p, 'admin/') === 0) {
        return $p;
    }
    return 'admin/' . $p;
}

// Obtener configuraciones del sistema
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Obtener información del usuario actual
$user_name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role_name'] ?? 'usuario';

// Verificar si hay estadísticas disponibles
$stats = array();
$online_stats = array();

$auth = new Auth();
$auth->requirePermission('products');

$productModel = new Product();
$categoryModel = new Category();

$categories = $categoryModel->getAll();
$products = $productModel->getAll(); // Solo mostrar productos activos
$inactive_products = $productModel->getAll(null, false); // Todos los productos
$inactive_products = array_filter($inactive_products, function($p) {
    return $p['is_active'] == 0; // Solo inactivos
});

// Obtener base de datos para funciones adicionales
$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create':
            $result = createProduct();
            break;
        case 'update':
            $result = updateProduct();
            break;
        case 'delete':
            $result = deleteProduct();
            break;
        case 'toggle_availability':
            $result = toggleAvailability();
            break;
        case 'adjust_stock':
            $result = adjustStock();
            break;
                case 'reactivate':
            $result = reactivateProduct();
            break;
            
        case 'force_delete':
            $result = forceDeleteProduct();
            break;
        case 'bulk_update':
            $result = bulkUpdateProducts();
            break;
    }
    
    if (isset($result['success']) && $result['success']) {
        header('Location: products.php?success=' . urlencode($result['message']));
        exit();
    } else {
        $error = $result['message'] ?? 'Error desconocido';
    }
}

function createProduct() {
    global $productModel;
    
    $data = [
        'category_id' => $_POST['category_id'] ?: null,
        'name' => trim($_POST['name']),
        'description' => trim($_POST['description']),
        'price' => floatval($_POST['price']),
        'cost' => floatval($_POST['cost']),
        'preparation_time' => intval($_POST['preparation_time']),
        'is_available' => isset($_POST['is_available']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'track_inventory' => isset($_POST['track_inventory']) ? 1 : 0,
        'stock_quantity' => null,
        'low_stock_alert' => 10,
        'image' => null
    ];
    
    // Gestión de inventario
    if (isset($_POST['track_inventory'])) {
        $data['stock_quantity'] = intval($_POST['stock_quantity']) ?: 0;
        $data['low_stock_alert'] = intval($_POST['low_stock_alert']) ?: 10;
    }
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_path = uploadImage($_FILES['image'], 'products');
        if ($image_path) {
            $data['image'] = normalizeImagePath($image_path);
        }
    }
    
    if ($productModel->create($data)) {
        return ['success' => true, 'message' => 'Producto creado exitosamente'];
    } else {
        return ['success' => false, 'message' => 'Error al crear el producto'];
    }
}

function updateProduct() {
    global $productModel;
    
    $id = intval($_POST['id']);
    $product = $productModel->getById($id);
    
    if (!$product) {
        return ['success' => false, 'message' => 'Producto no encontrado'];
    }
    
    $data = [
        'category_id' => $_POST['category_id'] ?: null,
        'name' => trim($_POST['name']),
        'description' => trim($_POST['description']),
        'price' => floatval($_POST['price']),
        'cost' => floatval($_POST['cost']),
        'preparation_time' => intval($_POST['preparation_time']),
        'is_available' => isset($_POST['is_available']) ? 1 : 0,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'track_inventory' => isset($_POST['track_inventory']) ? 1 : 0,
        'image' => $product['image'] // Keep existing image by default
    ];
    
    // Gestión de inventario
    if (isset($_POST['track_inventory'])) {
        $data['stock_quantity'] = intval($_POST['stock_quantity']) ?: 0;
        $data['low_stock_alert'] = intval($_POST['low_stock_alert']) ?: 10;
    } else {
        // Si se desactiva el tracking, limpiar los campos
        $data['stock_quantity'] = null;
        $data['low_stock_alert'] = 10;
    }
    
    // Handle new image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_path = uploadImage($_FILES['image'], 'products');
        if ($image_path) {
            // Delete old image if exists
            $oldImagePath = normalizeImagePath($product['image'] ?? null);
            if (!empty($product['image']) && file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
            $data['image'] = normalizeImagePath($image_path);
        }
    }
    
    if ($productModel->update($id, $data)) {
        return ['success' => true, 'message' => 'Producto actualizado exitosamente'];
    } else {
        return ['success' => false, 'message' => 'Error al actualizar el producto'];
    }
}

function deleteProduct() {
    global $productModel;
    
    $id = intval($_POST['id']);
    
    try {
        $result = $productModel->delete($id);
        return $result;
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ];
    }
}

/**
 * Reactiva un producto desactivado
 */
function reactivateProduct() {
    global $productModel;
    
    $id = intval($_POST['id']);
    
    // Verificar que el producto existe
    $product = $productModel->getById($id);
    if (!$product) {
        return ['success' => false, 'message' => 'Producto no encontrado'];
    }
    
    // Usar el método updateActiveStatus para reactivar
    if ($productModel->updateActiveStatus($id, 1)) {
        return ['success' => true, 'message' => 'Producto reactivado exitosamente'];
    } else {
        return ['success' => false, 'message' => 'Error al reactivar el producto'];
    }
}

/**
 * Fuerza la eliminación permanente de un producto (hard delete)
 * Incluso si tiene pedidos asociados
 */
function forceDeleteProduct() {
    global $productModel;
    
    $id = intval($_POST['id']);
    
    // Verificar que el producto existe
    $product = $productModel->getById($id);
    if (!$product) {
        return ['success' => false, 'message' => 'Producto no encontrado'];
    }
    
    try {
        // Forzar eliminación permanente usando hardDelete
        if ($productModel->hardDelete($id)) {
            return [
                'success' => true, 
                'message' => 'Producto eliminado permanentemente de la base de datos'
            ];
        } else {
            return ['success' => false, 'message' => 'Error al eliminar el producto'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Actualización masiva de productos
 */
function bulkUpdateProducts() {
    global $productModel;
    
    if (!isset($_POST['products']) || !is_array($_POST['products'])) {
        return ['success' => false, 'message' => 'No se recibieron productos para actualizar'];
    }
    
    $updated = 0;
    $errors = [];
    
    foreach ($_POST['products'] as $productData) {
        $id = intval($productData['id']);
        $product = $productModel->getById($id);
        
        if (!$product) {
            $errors[] = "Producto ID $id no encontrado";
            continue;
        }
        
        $data = [
            'stock_quantity' => isset($productData['stock']) ? intval($productData['stock']) : $product['stock_quantity'],
            'cost' => isset($productData['cost']) ? floatval($productData['cost']) : $product['cost'],
            'price' => isset($productData['price']) ? floatval($productData['price']) : $product['price'],
            // Mantener los demás campos sin cambios
            'category_id' => $product['category_id'],
            'name' => $product['name'],
            'description' => $product['description'],
            'preparation_time' => $product['preparation_time'],
            'is_available' => $product['is_available'],
            'is_active' => $product['is_active'],
            'track_inventory' => $product['track_inventory'],
            'low_stock_alert' => $product['low_stock_alert'],
            'image' => $product['image']
        ];
        
        if ($productModel->update($id, $data)) {
            $updated++;
        } else {
            $errors[] = "Error al actualizar producto ID $id";
        }
    }
    
    if ($updated > 0) {
        $message = "Se actualizaron $updated producto(s) exitosamente";
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(", ", $errors);
        }
        return ['success' => true, 'message' => $message];
    } else {
        return ['success' => false, 'message' => 'No se pudo actualizar ningún producto. ' . implode(", ", $errors)];
    }
}


function toggleAvailability() {
    global $productModel;
    
    $id = intval($_POST['id']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    // Verificar que el producto existe
    $product = $productModel->getById($id);
    if (!$product) {
        return ['success' => false, 'message' => 'Producto no encontrado'];
    }
    
    // Usar el nuevo método específico para actualizar disponibilidad
    if ($productModel->updateAvailability($id, $is_available)) {
        $status = $is_available ? 'disponible' : 'no disponible';
        return ['success' => true, 'message' => "Producto marcado como {$status}"];
    } else {
        return ['success' => false, 'message' => 'Error al actualizar disponibilidad'];
    }
}

function adjustStock() {
    global $productModel;
    
    $id = intval($_POST['id']);
    $adjustment = intval($_POST['adjustment']); // Puede ser positivo (agregar) o negativo (quitar)
    $reason = sanitize($_POST['reason']) ?: 'Ajuste manual';
    
    $product = $productModel->getById($id);
    
    if (!$product) {
        return ['success' => false, 'message' => 'Producto no encontrado'];
    }
    
    if (!$product['track_inventory']) {
        return ['success' => false, 'message' => 'Este producto no tiene control de inventario activado'];
    }
    
    $current_stock = intval($product['stock_quantity']);
    $new_stock = $current_stock + $adjustment;
    
    // No permitir stock negativo
    if ($new_stock < 0) {
        return ['success' => false, 'message' => 'No se puede reducir el stock por debajo de 0'];
    }
    
    // ===== CORRECCIÓN: Mantener todos los campos del producto =====
    $data = [
        'category_id' => $product['category_id'],
        'name' => $product['name'],
        'description' => $product['description'],
        'price' => $product['price'],
        'cost' => $product['cost'],
        'stock_quantity' => $new_stock,  // Solo cambiar el stock
        'low_stock_alert' => $product['low_stock_alert'],
        'track_inventory' => $product['track_inventory'],
        'image' => $product['image'],
        'preparation_time' => $product['preparation_time'],
        'is_available' => $product['is_available'],
        'is_active' => $product['is_active']
    ];
    
    // Actualizar producto completo
    $result = $productModel->update($id, $data);
    
    if ($result) {
        // Opcional: Registrar el movimiento en un log de inventario
        logStockMovement($id, $adjustment, $current_stock, $new_stock, $reason);
        
        $action = $adjustment > 0 ? 'agregado' : 'reducido';
        return ['success' => true, 'message' => "Stock {$action} exitosamente. Stock actual: {$new_stock}"];
    } else {
        return ['success' => false, 'message' => 'Error al ajustar el stock'];
    }
}



// Función para registrar movimientos de stock (opcional)
function logStockMovement($product_id, $adjustment, $old_stock, $new_stock, $reason) {
    global $db;
    
    try {
        // Verificar si la tabla existe antes de insertar
        $stmt = $db->prepare("SHOW TABLES LIKE 'stock_movements'");
        $stmt->execute();
        if ($stmt->rowCount() == 0) {
            // La tabla no existe, crear log en archivo o simplemente retornar
            error_log("Stock movement: Product ID {$product_id}, Adjustment: {$adjustment}, Reason: {$reason}");
            return;
        }
        
        $stmt = $db->prepare("
            INSERT INTO stock_movements 
            (product_id, movement_type, quantity, old_stock, new_stock, reason, user_id, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $movement_type = $adjustment > 0 ? 'entrada' : 'salida';
        $user_id = $_SESSION['user_id'] ?? 1;
        
        $stmt->execute([
            $product_id, 
            $movement_type, 
            abs($adjustment), 
            $old_stock, 
            $new_stock, 
            $reason, 
            $user_id
        ]);
    } catch (Exception $e) {
        // Log error but don't stop the process
        error_log("Error logging stock movement: " . $e->getMessage());
    }
}

// Función para obtener productos con stock bajo
function getLowStockProducts() {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT p.*, c.name as category_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE p.track_inventory = 1 
            AND p.is_active = 1
            AND (p.stock_quantity <= p.low_stock_alert OR p.stock_quantity = 0)
            ORDER BY p.stock_quantity ASC, p.name ASC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting low stock products: " . $e->getMessage());
        return [];
    }
}

// Función para obtener estadísticas de inventario
function getInventoryStats() {
    global $db;
    
    try {
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_products,
                SUM(CASE WHEN track_inventory = 1 THEN 1 ELSE 0 END) as tracked_products,
                SUM(CASE WHEN track_inventory = 1 AND stock_quantity = 0 THEN 1 ELSE 0 END) as out_of_stock,
                SUM(CASE WHEN track_inventory = 1 AND stock_quantity <= low_stock_alert AND stock_quantity > 0 THEN 1 ELSE 0 END) as low_stock,
                SUM(CASE WHEN track_inventory = 1 AND stock_quantity > low_stock_alert THEN 1 ELSE 0 END) as good_stock
            FROM products 
            WHERE is_active = 1
        ");
        
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Error getting inventory stats: " . $e->getMessage());
        return [
            'total_products' => 0,
            'tracked_products' => 0,
            'out_of_stock' => 0,
            'low_stock' => 0,
            'good_stock' => 0
        ];
    }
}

// Obtener estadísticas de inventario para mostrar en el dashboard
$inventory_stats = getInventoryStats();
$low_stock_products = getLowStockProducts();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Tema dinámico -->
    <?php if (file_exists('../assets/css/generate-theme.php')): ?>
        <link rel="stylesheet" href="../assets/css/generate-theme.php?v=<?php echo time(); ?>">
    <?php endif; ?>
    
    <!-- CSS del Autocompletado de Productos -->
    <link rel="stylesheet" href="css/product-autocomplete.css">

    <?php
    // Incluir sistema de temas
    $theme_file = '../config/theme.php';
    if (file_exists($theme_file)) {
        require_once $theme_file;
        $database = new Database();
        $db = $database->getConnection();
        $theme_manager = new ThemeManager($db);
        $current_theme = $theme_manager->getThemeSettings();
    } else {
        $current_theme = array(
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'accent_color' => '#ff6b6b',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8'
        );
    }
    ?>
    
    <style>
    /* Variables CSS para el tema */
    :root {
        --primary-color: <?php echo $current_theme['primary_color'] ?? '#667eea'; ?>;
        --secondary-color: <?php echo $current_theme['secondary_color'] ?? '#764ba2'; ?>;
        --accent-color: <?php echo $current_theme['accent_color'] ?? '#ff6b6b'; ?>;
        --success-color: <?php echo $current_theme['success_color'] ?? '#28a745'; ?>;
        --warning-color: <?php echo $current_theme['warning_color'] ?? '#ffc107'; ?>;
        --danger-color: <?php echo $current_theme['danger_color'] ?? '#dc3545'; ?>;
        --info-color: <?php echo $current_theme['info_color'] ?? '#17a2b8'; ?>;
        
        --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        --products-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
        --sidebar-mobile-width: 100%;
        --border-radius-base: 0.375rem;
        --border-radius-large: 0.75rem;
        --transition-base: all 0.3s ease;
        --shadow-base: 0 2px 4px rgba(0,0,0,0.1);
        --shadow-large: 0 4px 12px rgba(0,0,0,0.15);
        --text-white: #ffffff;
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

    .mobile-topbar h5 {
        margin: 0;
        font-size: 1.1rem;
        color: var(--text-white) !important;
    }

    .menu-toggle {
        background: none;
        border: none;
        color: var(--text-white) !important;
        font-size: 1.2rem;
        padding: 0.5rem;
        border-radius: var(--border-radius-base);
        transition: var(--transition-base);
    }

    .menu-toggle:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    /* Sidebar */
    .sidebar {
        position: fixed;
        top: 0;
        left: 0;
        width: var(--products-sidebar-width);
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
        opacity: 0;
        transition: opacity var(--transition-base);
    }

    .sidebar-backdrop.show {
        display: block;
        opacity: 1;
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

    .sidebar .nav-link .badge {
        margin-left: auto;
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
        align-items: center;
        justify-content: center;
        font-size: 1.1rem;
    }

    /* Main content - FORZAR COLORES CLAROS */
    .main-content {
        margin-left: var(--products-sidebar-width);
        padding: 2rem;
        min-height: 100vh;
        transition: margin-left var(--transition-base);
        background: #f8f9fa !important;
        color: #212529 !important;
    }

    /* Page header - FORZAR COLORES CLAROS */
    .page-header {
        background: #ffffff !important;
        color: #212529 !important;
        border-radius: var(--border-radius-large);
        padding: 1.5rem;
        margin-bottom: 2rem;
        box-shadow: var(--shadow-base);
    }

    /* Statistics cards - FORZAR COLORES CLAROS */
    .stats-row {
        margin-bottom: 2rem;
    }

    .stat-card {
        background: #ffffff !important;
        color: #212529 !important;
        border-radius: var(--border-radius-large);
        padding: 1.5rem;
        box-shadow: var(--shadow-base);
        text-align: center;
        transition: transform var(--transition-base);
        height: 100%;
        border: none;
    }

    .stat-card:hover {
        transform: translateY(-5px);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: bold;
        margin-bottom: 0.5rem;
        line-height: 1;
    }

    /* Product cards - FORZAR COLORES CLAROS */
    .product-card {
        transition: transform var(--transition-base);
        border: none;
        box-shadow: var(--shadow-base);
        border-radius: var(--border-radius-large);
        overflow: hidden;
        height: 100%;
        background: #ffffff !important;
        color: #212529 !important;
        position: relative;
    }

    .product-card:hover {
        transform: translateY(-5px);
    }

    .product-image {
        height: 200px;
        object-fit: cover;
        background: #f8f9fa;
    }

    .product-unavailable {
        opacity: 0.6;
    }

    .product-inactive {
        border-left: 5px solid var(--danger-color);
    }

    /* Filter card - FORZAR COLORES CLAROS */
    .filter-card {
        background: #ffffff !important;
        color: #212529 !important;
        border-radius: var(--border-radius-large);
        padding: 1.5rem;
        box-shadow: var(--shadow-base);
        margin-bottom: 2rem;
    }

    /* Card improvements - FORZAR COLORES CLAROS */
    .card {
        border: none;
        border-radius: var(--border-radius-large);
        box-shadow: var(--shadow-base);
        background: #ffffff !important;
        color: #212529 !important;
    }

    .card-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.125);
        border-radius: var(--border-radius-large) var(--border-radius-large) 0 0 !important;
        background: #f8f9fa !important;
        color: #212529 !important;
        padding: 0.75rem 1rem;
    }

    .card-header h6 {
        color: #495057;
        font-weight: 600;
        margin: 0;
    }

    .card-body {
        background: #ffffff !important;
        color: #212529 !important;
    }

    .card-title {
        color: #212529 !important;
    }

    .card-text {
        color: #6c757d !important;
    }

    /* Forms - FORZAR COLORES CLAROS */
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

    /* Modal - FORZAR COLORES CLAROS */
    .modal-content {
        background: #ffffff !important;
        color: #212529 !important;
        border: none;
        border-radius: var(--border-radius-large);
        box-shadow: var(--shadow-large);
    }

    .modal-header {
        background: #f8f9fa !important;
        color: #212529 !important;
        border-bottom: 1px solid #dee2e6;
        border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
    }

    .modal-title {
        color: #212529 !important;
    }

    .modal-body {
        background: #ffffff !important;
        color: #212529 !important;
    }

    .modal-footer {
        background: #ffffff !important;
        border-top: 1px solid #dee2e6;
        border-radius: 0 0 var(--border-radius-large) var(--border-radius-large);
    }

    /* Modal para productos - Extra Large */
    .modal-xl {
        max-width: 1200px;
    }

    /* Text colors */
    .text-muted {
        color: #6c757d !important;
    }

    h1, h2, h3, h4, h5, h6 {
        color: #212529 !important;
    }

    p {
        color: #212529 !important;
    }

    /* Badges usando variables del tema */
    .badge.bg-primary {
        background: var(--primary-color) !important;
        color: var(--text-white) !important;
    }

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

    /* Buttons usando variables del tema */
    .btn-primary {
        background: var(--primary-color) !important;
        border-color: var(--primary-color) !important;
        color: var(--text-white) !important;
    }

    .btn-primary:hover {
        background: var(--secondary-color) !important;
        border-color: var(--secondary-color) !important;
        color: var(--text-white) !important;
    }

    .btn-success {
        background: var(--success-color) !important;
        border-color: var(--success-color) !important;
        color: var(--text-white) !important;
    }

    .btn-danger {
        background: var(--danger-color) !important;
        border-color: var(--danger-color) !important;
        color: var(--text-white) !important;
    }

    .btn-warning {
        background: var(--warning-color) !important;
        border-color: var(--warning-color) !important;
        color: #212529 !important;
    }

    .btn-info {
        background: var(--info-color) !important;
        border-color: var(--info-color) !important;
        color: var(--text-white) !important;
    }

    /* Estilos específicos para gestión de stock */
    .form-switch .form-check-input {
        width: 2em;
        height: 1em;
    }

    .progress {
        background-color: #e9ecef;
        border-radius: 2px;
        height: 4px;
    }

    .stock-indicator {
        transition: color 0.3s ease;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .btn-group .btn {
        border-radius: 0.375rem;
    }

    .btn-group .btn:not(:last-child) {
        margin-right: 2px;
    }

    .gap-1 > * + * {
        margin-left: 0.25rem !important;
    }

    /* Indicadores de posición absoluta para stock */
    .product-card .position-absolute {
        z-index: 10;
    }

    /* Sidebar scrollbar */
    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }

    .system-header .container-fluid {
        height: 60px;
        display: flex;
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

    /* Mobile responsive styles */
    @media (max-width: 1199.98px) {
        .modal-xl {
            max-width: 95%;
        }
    }

    @media (max-width: 991.98px) {
        .mobile-topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .sidebar {
            transform: translateX(-100%);
            width: var(--sidebar-mobile-width);
            max-width: 350px;
        }

        .sidebar.show {
            transform: translateX(0);
        }

        .sidebar-close {
            display: flex;
        }

        .main-content {
            margin-left: 0;
            padding: 1rem;
            padding-top: 5rem;
        }

        .stat-card {
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 1.5rem;
        }

        .page-header {
            padding: 1rem;
        }

        .page-header h2 {
            font-size: 1.5rem;
        }

        .page-header .d-flex {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }

        .filter-card {
            padding: 1rem;
        }

        .product-image {
            height: 150px;
        }
    }

    @media (max-width: 767.98px) {
        .modal-xl {
            max-width: 100%;
            margin: 0.5rem;
        }
        
        .modal-body {
            padding: 1rem;
        }
        
        .card-body {
            padding: 0.75rem;
        }

        .main-content {
            padding: 0.5rem;
            padding-top: 4.5rem;
        }

        .stat-card {
            padding: 0.75rem;
        }

        .stat-number {
            font-size: 1.25rem;
        }

        .page-header {
            padding: 0.75rem;
        }

        .page-header h2 {
            font-size: 1.25rem;
        }

        .filter-card {
            padding: 0.75rem;
        }

        .sidebar {
            padding: 1rem;
        }

        .sidebar .nav-link {
            padding: 0.5rem 0.75rem;
        }

        .product-image {
            height: 120px;
        }

        .btn-group .btn {
            padding: 0.25rem 0.4rem;
            font-size: 0.8rem;
        }
        
        .badge {
            font-size: 0.65rem;
            padding: 0.2rem 0.3rem;
        }
        
        .progress {
            height: 3px !important;
        }
    }

    @media (max-width: 576px) {
        .stat-card {
            padding: 0.75rem;
        }

        .stat-number {
            font-size: 1.4rem;
        }
    }
    
    /* Estilos para productos inactivos */
.product-inactive {
    border: 2px solid #6c757d !important;
    opacity: 0.85;
}

.product-inactive:hover {
    opacity: 1;
}

.inactive-product-item .card-title {
    color: #6c757d !important;
}

.inactive-product-item .badge {
    opacity: 0.8;
}

/* Animación suave al mostrar/ocultar */
#inactiveProducts {
    animation: fadeIn 0.3s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Botón de toggle mejorado */
#toggleInactiveBtn {
    transition: all 0.3s ease;
}

#toggleInactiveBtn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Estilos para el modal de edición masiva */
.bulk-edit-modal .modal-dialog {
    max-width: 90%;
}

.bulk-edit-table {
    font-size: 0.9rem;
}

.bulk-edit-table th {
    background-color: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
}

.bulk-edit-table input {
    padding: 0.375rem 0.5rem;
    font-size: 0.9rem;
}

.table-container {
    max-height: 60vh;
    overflow-y: auto;
}

.bulk-edit-input {
    width: 100%;
    min-width: 80px;
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
                    <h2 class="mb-0">Gestión de Productos</h2>
                    <p class="text-muted mb-0">Administra el menú de tu restaurante</p>
                </div>
                <div>
                    <button class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#productModal" onclick="newProduct()">
                        <i class="fas fa-plus me-1"></i>
                        <span class="d-none d-sm-inline">Nuevo </span>Producto
                    </button>
                    <button class="btn btn-info" onclick="openBulkEditModal()">
                        <i class="fas fa-edit me-1"></i>
                        <span class="d-none d-sm-inline">Edición </span>Masiva
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($_GET['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Alerta de Productos con Stock Bajo -->
        <?php if (!empty($low_stock_products)): ?>
        <div class="alert alert-warning alert-dismissible fade show mb-4" role="alert">
            <div class="d-flex align-items-start">
                <i class="fas fa-exclamation-triangle me-2 mt-1"></i>
                <div class="flex-grow-1">
                    <h6 class="alert-heading mb-2">Productos con Stock Bajo</h6>
                    <div class="row g-2">
                        <?php foreach ($low_stock_products as $product): ?>
                            <div class="col-12 col-md-6">
                                <div class="d-flex justify-content-between align-items-center bg-white p-2 rounded">
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo $product['category_name'] ?? 'Sin categoría'; ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge bg-<?php echo $product['stock_quantity'] == 0 ? 'danger' : 'warning'; ?>">
                                            <?php echo $product['stock_quantity']; ?> unidades
                                        </span>
                                        <br>
                                        <button class="btn btn-sm btn-outline-primary mt-1" onclick="adjustStock(<?php echo $product['id']; ?>)">
                                            Ajustar
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics -->
        <div class="stats-row">
            <div class="row g-3 g-md-4">
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number text-primary"><?php echo count(array_filter($products, fn($p) => $p['is_active'])); ?></div>
                        <div class="text-muted small">Productos Activos</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number text-success"><?php echo count(array_filter($products, fn($p) => $p['is_available'])); ?></div>
                        <div class="text-muted small">Disponibles</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number text-warning"><?php echo count(array_filter($products, fn($p) => !$p['is_available'])); ?></div>
                        <div class="text-muted small">No Disponibles</div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="stat-card">
                        <div class="stat-number text-info"><?php echo count($categories); ?></div>
                        <div class="text-muted small">Categorías</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Estadísticas de Inventario -->
        <div class="row g-3 mb-4" id="inventoryStatsRow">
            <div class="col-6 col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <div class="stat-number text-primary"><?php echo $inventory_stats['tracked_products']; ?></div>
                        <div class="text-muted small">Con Control</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <div class="stat-number text-success"><?php echo $inventory_stats['good_stock']; ?></div>
                        <div class="text-muted small">Stock Bueno</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <div class="stat-number text-warning"><?php echo $inventory_stats['low_stock']; ?></div>
                        <div class="text-muted small">Stock Bajo</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card h-100">
                    <div class="card-body text-center">
                        <div class="stat-number text-danger"><?php echo $inventory_stats['out_of_stock']; ?></div>
                        <div class="text-muted small">Sin Stock</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="filter-card">
            <div class="row align-items-end g-3">
                <div class="col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Nombre del producto...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Categoría</label>
                    <select class="form-select" id="categoryFilter">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Estado</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">Todos los estados</option>
                        <option value="available">Disponible</option>
                        <option value="unavailable">No disponible</option>
                        <option value="inactive">Inactivo</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button class="btn btn-outline-secondary w-100" onclick="clearFilters()">
                        <i class="fas fa-times me-1"></i>
                        Limpiar
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Products Grid -->
        <div class="row g-3 g-md-4" id="productsContainer">
            <?php foreach ($products as $product): ?>
                <div class="col-6 col-md-4 col-lg-3 product-item" 
                     data-category="<?php echo $product['category_id']; ?>"
                     data-status="<?php echo $product['is_active'] ? ($product['is_available'] ? 'available' : 'unavailable') : 'inactive'; ?>"
                     data-name="<?php echo strtolower($product['name']); ?>"
                     data-product-id="<?php echo $product['id']; ?>">
                    <div class="card product-card <?php echo !$product['is_available'] ? 'product-unavailable' : ''; ?> <?php echo !$product['is_active'] ? 'product-inactive' : ''; ?>">
                        
                        <!-- Imagen del producto -->
                        <?php if ($product['image']): ?>
                            <img src="../<?php echo htmlspecialchars($product['image']); ?>" 
                                 class="card-img-top product-image" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="product-image d-flex align-items-center justify-content-center bg-light">
                                <i class="fas fa-utensils fa-3x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Indicador de stock bajo (si aplica) -->
                        <?php if ($product['track_inventory'] && $product['stock_quantity'] !== null): ?>
                            <?php 
                            $stock_status = '';
                            $stock_class = '';
                            if ($product['stock_quantity'] == 0) {
                                $stock_status = 'SIN STOCK';
                                $stock_class = 'bg-danger';
                            } elseif ($product['stock_quantity'] <= $product['low_stock_alert']) {
                                $stock_status = 'STOCK BAJO';
                                $stock_class = 'bg-warning';
                            }
                            ?>
                            <?php if ($stock_status): ?>
                                <div class="position-absolute top-0 start-0 m-2">
                                    <span class="badge <?php echo $stock_class; ?> small">
                                        <?php echo $stock_status; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column p-2 p-md-3">
                            <!-- Header con nombre y opciones -->
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="card-title mb-0 flex-grow-1"><?php echo htmlspecialchars($product['name']); ?></h6>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary p-1" type="button" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" onclick="editProduct(<?php echo $product['id']; ?>)">
                                            <i class="fas fa-edit me-2"></i>Editar
                                        </a></li>
                                        
                                        <!-- Opciones de stock (solo si tiene control de inventario) -->
                                        <?php if ($product['track_inventory']): ?>
                                            <li><a class="dropdown-item" onclick="adjustStock(<?php echo $product['id']; ?>)">
                                                <i class="fas fa-boxes me-2"></i>Ajustar Stock
                                            </a></li>
                                            <li><hr class="dropdown-divider"></li>
                                        <?php endif; ?>
                                        
                                        <li><a class="dropdown-item" onclick="toggleAvailability(<?php echo $product['id']; ?>, <?php echo $product['is_available'] ? 'false' : 'true'; ?>)">
                                            <i class="fas fa-<?php echo $product['is_available'] ? 'eye-slash' : 'eye'; ?> me-2"></i>
                                            <?php echo $product['is_available'] ? 'No disponible' : 'Disponible'; ?>
                                        </a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item text-danger" onclick="deleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                            <i class="fas fa-trash me-2"></i>Eliminar
                                        </a></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <!-- Descripción -->
                            <?php if (!empty($product['description'])): ?>
                                <p class="card-text text-muted small mb-2 d-none d-md-block">
                                    <?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>...
                                </p>
                            <?php endif; ?>
                            
                            <!-- Categoría -->
                            <div class="mb-2">
                                <span class="badge bg-primary small">
                                    <?php echo $product['category_name'] ?? 'Sin categoría'; ?>
                                </span>
                                
                                <!-- Indicador de control de inventario -->
                                <?php if ($product['track_inventory']): ?>
                                    <span class="badge bg-info small ms-1">
                                        <i class="fas fa-boxes me-1"></i>Inventario
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Información de stock (si tiene control de inventario) -->
                            <?php if ($product['track_inventory'] && $product['stock_quantity'] !== null): ?>
                                <div class="mb-2">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">Stock:</small>
                                        <span class="stock-indicator small fw-bold <?php 
                                            if ($product['stock_quantity'] == 0) {
                                                echo 'text-danger';
                                            } elseif ($product['stock_quantity'] <= $product['low_stock_alert']) {
                                                echo 'text-warning';
                                            } else {
                                                echo 'text-success';
                                            }
                                        ?>">
                                            <?php echo $product['stock_quantity']; ?> unidades
                                        </span>
                                    </div>
                                    
                                    <!-- Barra de progreso del stock -->
                                    <?php 
                                    $stock_percentage = 0;
                                    $max_display_stock = max($product['low_stock_alert'] * 3, 50); // Usar 3x el límite bajo o mínimo 50
                                    if ($max_display_stock > 0) {
                                        $stock_percentage = min(($product['stock_quantity'] / $max_display_stock) * 100, 100);
                                    }
                                    ?>
                                    <div class="progress" style="height: 4px;">
                                        <div class="progress-bar <?php 
                                            if ($product['stock_quantity'] == 0) {
                                                echo 'bg-danger';
                                            } elseif ($product['stock_quantity'] <= $product['low_stock_alert']) {
                                                echo 'bg-warning';
                                            } else {
                                                echo 'bg-success';
                                            }
                                        ?>" style="width: <?php echo $stock_percentage; ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Precio y tiempo -->
                            <div class="mt-auto">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="h6 text-success mb-0">
                                        <?php echo formatPrice($product['price']); ?>
                                    </span>
                                    <?php if ($product['preparation_time']): ?>
                                        <small class="text-muted d-none d-sm-inline">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo $product['preparation_time']; ?>min
                                        </small>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Estados y acciones -->
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="d-flex flex-wrap gap-1">
                                        <?php if (!$product['is_active']): ?>
                                            <span class="badge bg-danger small">Inactivo</span>
                                        <?php elseif (!$product['is_available']): ?>
                                            <span class="badge bg-warning small">No disponible</span>
                                        <?php else: ?>
                                            <span class="badge bg-success small">Disponible</span>
                                        <?php endif; ?>
                                        
                                        <!-- Indicador de stock crítico -->
                                        <?php if ($product['track_inventory'] && $product['stock_quantity'] == 0): ?>
                                            <span class="badge bg-danger small">Sin stock</span>
                                        <?php elseif ($product['track_inventory'] && $product['stock_quantity'] <= $product['low_stock_alert']): ?>
                                            <span class="badge bg-warning small">Stock bajo</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="btn-group" role="group">
                                        <?php if ($product['track_inventory']): ?>
                                            <button class="btn btn-sm btn-outline-info" onclick="adjustStock(<?php echo $product['id']; ?>)" title="Ajustar Stock">
                                                <i class="fas fa-boxes"></i>
                                            </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['id']; ?>)" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
                <!-- Sección de Productos Desactivados -->
        <?php if (!empty($inactive_products)): ?>
        <div class="mt-5 pt-4 border-top">
            <div class="page-header mb-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1">
                            <i class="fas fa-archive me-2 text-muted"></i>
                            Productos Desactivados
                        </h4>
                        <p class="text-muted small mb-0">
                            <?php echo count($inactive_products); ?> producto<?php echo count($inactive_products) != 1 ? 's' : ''; ?> desactivado<?php echo count($inactive_products) != 1 ? 's' : ''; ?> 
                            (con pedidos asociados)
                        </p>
                    </div>
                    <button class="btn btn-outline-secondary" onclick="toggleInactive()" id="toggleInactiveBtn">
                        <i class="fas fa-eye me-1"></i>
                        <span id="toggleText">Mostrar</span>
                    </button>
                </div>
            </div>
            
            <div id="inactiveProducts" style="display: none;">
                <!-- Alerta informativa -->
                <div class="alert alert-info d-flex align-items-start mb-4">
                    <i class="fas fa-info-circle me-3 mt-1"></i>
                    <div>
                        <strong>¿Por qué están aquí estos productos?</strong>
                        <p class="mb-0 mt-1">
                            Estos productos fueron desactivados automáticamente porque tienen pedidos asociados. 
                            No se eliminaron para preservar el historial de ventas. 
                            Puede <strong>reactivarlos</strong> editándolos o <strong>eliminarlos permanentemente</strong> desde aquí 
                            (se eliminará el producto pero se mantendrán los registros de pedidos).
                        </p>
                    </div>
                </div>
                
                <!-- Grid de productos inactivos -->
                <div class="row g-3 g-md-4">
                    <?php foreach ($inactive_products as $product): ?>
                        <div class="col-6 col-md-4 col-lg-3 inactive-product-item" 
                             data-category="<?php echo $product['category_id']; ?>"
                             data-name="<?php echo strtolower($product['name']); ?>"
                             data-product-id="<?php echo $product['id']; ?>">
                            <div class="card product-card product-inactive">
                                
                                <!-- Imagen del producto -->
                                <?php if ($product['image']): ?>
                                    <img src="../<?php echo htmlspecialchars($product['image']); ?>" 
                                         class="card-img-top product-image" 
                                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                                         style="opacity: 0.6;">
                                <?php else: ?>
                                    <div class="product-image d-flex align-items-center justify-content-center bg-light" style="opacity: 0.6;">
                                        <i class="fas fa-utensils fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Badge de desactivado -->
                                <div class="position-absolute top-0 start-0 m-2">
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-ban me-1"></i>
                                        DESACTIVADO
                                    </span>
                                </div>
                                
                                <div class="card-body d-flex flex-column p-2 p-md-3">
                                    <!-- Header con nombre y opciones -->
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0 flex-grow-1 text-muted">
                                            <?php echo htmlspecialchars($product['name']); ?>
                                        </h6>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary p-1" type="button" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <li><a class="dropdown-item text-success" onclick="reactivateProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                                    <i class="fas fa-check-circle me-2"></i>Reactivar
                                                </a></li>
                                                <li><a class="dropdown-item" onclick="editProduct(<?php echo $product['id']; ?>)">
                                                    <i class="fas fa-edit me-2"></i>Editar
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" onclick="forceDeleteProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')">
                                                    <i class="fas fa-trash-alt me-2"></i>Eliminar definitivamente
                                                </a></li>
                                            </ul>
                                        </div>
                                    </div>
                                    
                                    <!-- Descripción -->
                                    <?php if (!empty($product['description'])): ?>
                                        <p class="card-text text-muted small mb-2 d-none d-md-block">
                                            <?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>...
                                        </p>
                                    <?php endif; ?>
                                    
                                    <!-- Categoría -->
                                    <div class="mb-2">
                                        <span class="badge bg-secondary small">
                                            <?php echo $product['category_name'] ?? 'Sin categoría'; ?>
                                        </span>
                                        <?php if ($product['track_inventory']): ?>
                                            <span class="badge bg-secondary small ms-1">
                                                <i class="fas fa-boxes me-1"></i>Inventario
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Info de stock si aplica -->
                                    <?php if ($product['track_inventory'] && $product['stock_quantity'] !== null): ?>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">Stock:</small>
                                                <span class="small text-muted">
                                                    <?php echo $product['stock_quantity']; ?> unidades
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Precio -->
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="h6 text-muted mb-0">
                                                <?php echo formatPrice($product['price']); ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Acciones -->
                                        <div class="d-flex gap-2">
                                            <button class="btn btn-sm btn-success flex-fill" 
                                                    onclick="reactivateProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')"
                                                    title="Reactivar producto">
                                                <i class="fas fa-check-circle me-1"></i>
                                                Reactivar
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary" 
                                                    onclick="editProduct(<?php echo $product['id']; ?>)" 
                                                    title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="productForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="fas fa-plus me-2"></i>
                            Nuevo Producto
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="productId">
                        
                        <div class="row">
                            <!-- Columna Principal - Información Básica -->
                            <div class="col-md-8">
                                <!-- Información Básica del Producto -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Información Básica</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Nombre *</label>
                                            <div class="autocomplete-container">
                                                <input type="text" class="form-control" name="name" id="productName" required autocomplete="off" placeholder="Escribe el nombre del producto...">
                                                <!-- Contenedor para las sugerencias de autocompletado -->
                                                <div id="product-suggestions" class="autocomplete-suggestions"></div>
                                            </div>
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle"></i>
                                                Mientras escribes, verás productos existentes para evitar duplicados
                                            </small>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Descripción</label>
                                            <textarea class="form-control" name="description" id="productDescription" rows="3"></textarea>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Categoría</label>
                                                    <select class="form-select" name="category_id" id="productCategory">
                                                        <option value="">Seleccionar categoría...</option>
                                                        <?php foreach ($categories as $category): ?>
                                                            <option value="<?php echo $category['id']; ?>">
                                                                <?php echo htmlspecialchars($category['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Tiempo de Preparación</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="preparation_time" id="productPrepTime" min="0">
                                                        <span class="input-group-text">min</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Precios y Costos -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-dollar-sign me-2"></i>Precios y Costos</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Precio de Venta *</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" name="price" id="productPrice" step="0.01" min="0" required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Costo</label>
                                                    <div class="input-group">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" class="form-control" name="cost" id="productCost" step="0.01" min="0">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Margen de Ganancia (calculado automáticamente) -->
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-0">
                                                    <label class="form-label text-muted small">Margen de Ganancia</label>
                                                    <div class="text-success fw-bold" id="profitMargin">-</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-0">
                                                    <label class="form-label text-muted small">Porcentaje de Ganancia</label>
                                                    <div class="text-info fw-bold" id="profitPercentage">-</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Gestión de Inventario -->
                                <div class="card mb-3">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <h6 class="mb-0"><i class="fas fa-boxes me-2"></i>Gestión de Inventario</h6>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="track_inventory" id="trackInventory" onchange="toggleInventoryFields()">
                                            <label class="form-check-label small" for="trackInventory">
                                                Controlar stock
                                            </label>
                                        </div>
                                    </div>
                                    <div class="card-body" id="inventoryFields" style="display: none;">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Stock Actual</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="stock_quantity" id="stockQuantity" min="0">
                                                        <span class="input-group-text">unidades</span>
                                                    </div>
                                                    <div class="form-text">Cantidad actual en inventario</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Alerta de Stock Bajo</label>
                                                    <div class="input-group">
                                                        <input type="number" class="form-control" name="low_stock_alert" id="lowStockAlert" min="0" value="10">
                                                        <span class="input-group-text">unidades</span>
                                                    </div>
                                                    <div class="form-text">Notificar cuando el stock sea menor a esta cantidad</div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Indicador de Estado del Stock -->
                                        <div class="alert alert-info d-none" id="stockStatus">
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-info-circle me-2"></i>
                                                <span id="stockStatusText">El stock actual está en buen estado</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Columna Lateral - Imagen y Configuración -->
                            <div class="col-md-4">
                                <!-- Imagen del Producto -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-image me-2"></i>Imagen</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <div id="imagePreview" class="mb-3" style="display: none;">
                                            <img id="previewImg" src="" class="img-fluid rounded" style="max-height: 200px;">
                                        </div>
                                        <div id="imagePlaceholder" class="mb-3 d-flex align-items-center justify-content-center bg-light rounded" style="height: 200px;">
                                            <i class="fas fa-utensils fa-3x text-muted"></i>
                                        </div>
                                        <input type="file" class="form-control mb-2" name="image" id="productImage" accept="image/*" onchange="previewImage()">
                                        <small class="text-muted">JPG, PNG, GIF. Máximo 5MB</small>
                                    </div>
                                </div>

                                <!-- Estado y Disponibilidad -->
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-toggle-on me-2"></i>Estado</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" name="is_available" id="productAvailable" checked>
                                            <label class="form-check-label" for="productAvailable">
                                                <strong>Disponible para venta</strong>
                                            </label>
                                            <div class="form-text">El producto aparecerá en el menú</div>
                                        </div>
                                        
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="is_active" id="productActive" checked>
                                            <label class="form-check-label" for="productActive">
                                                <strong>Producto activo</strong>
                                            </label>
                                            <div class="form-text">Mantener en el sistema</div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Resumen del Producto -->
                                <div class="card">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Resumen</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row text-center">
                                            <div class="col-6">
                                                <div class="border-end">
                                                    <div class="h6 text-success mb-0" id="summaryPrice">$0.00</div>
                                                    <small class="text-muted">Precio</small>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="h6 text-primary mb-0" id="summaryStock">-</div>
                                                <small class="text-muted">Stock</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="pointer-events: none;">
                        <button type="button" class="btn btn-secondary" id="cancelModalBtn" style="pointer-events: auto;">
                            <i class="fas fa-times me-1"></i>
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-success" id="submitBtn" style="pointer-events: auto;">
                            <i class="fas fa-save me-1"></i>
                            Guardar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para Ajuste de Stock -->
    <div class="modal fade" id="stockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="stockForm">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-boxes me-2"></i>
                            Ajustar Stock
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="adjust_stock">
                        <input type="hidden" name="id" id="stockProductId">
                        
                        <div class="mb-3">
                            <label class="form-label">Producto</label>
                            <div class="p-2 bg-light rounded">
                                <strong id="stockProductName">-</strong>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-6">
                                <label class="form-label">Stock Actual</label>
                                <div class="p-2 bg-light rounded text-center">
                                    <span class="h5 text-primary" id="currentStock">0</span>
                                    <small class="text-muted d-block">unidades</small>
                                </div>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Alerta en</label>
                                <div class="p-2 bg-light rounded text-center">
                                    <span class="h6 text-warning" id="lowStockLimit">10</span>
                                    <small class="text-muted d-block">unidades</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Tipo de Ajuste</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <button type="button" class="btn btn-success w-100" onclick="setAdjustmentType('add')">
                                        <i class="fas fa-plus me-1"></i>
                                        Agregar Stock
                                    </button>
                                </div>
                                <div class="col-6">
                                    <button type="button" class="btn btn-warning w-100" onclick="setAdjustmentType('subtract')">
                                        <i class="fas fa-minus me-1"></i>
                                        Reducir Stock
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Cantidad</label>
                            <div class="input-group">
                                <span class="input-group-text" id="adjustmentSign">+</span>
                                <input type="number" class="form-control" name="adjustment_quantity" id="adjustmentQuantity" min="1" required>
                                <span class="input-group-text">unidades</span>
                            </div>
                            <div class="form-text">
                                Nuevo stock: <span class="fw-bold" id="newStockPreview">0</span> unidades
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Motivo del Ajuste</label>
                            <select class="form-select" name="reason" id="adjustmentReason">
                                <option value="Ajuste manual">Ajuste manual</option>
                                <option value="Inventario físico">Inventario físico</option>
                                <option value="Producto dañado">Producto dañado</option>
                                <option value="Producto vencido">Producto vencido</option>
                                <option value="Venta directa">Venta directa</option>
                                <option value="Compra/Reposición">Compra/Reposición</option>
                                <option value="Corrección de error">Corrección de error</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                        
                        <div class="mb-3" id="customReasonGroup" style="display: none;">
                            <label class="form-label">Especificar motivo</label>
                            <input type="text" class="form-control" id="customReason" placeholder="Describe el motivo...">
                        </div>
                        
                        <!-- Alerta de stock bajo -->
                        <div class="alert alert-warning d-none" id="stockWarning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <span id="stockWarningText"></span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            Cancelar
                        </button>
                        <button type="submit" class="btn btn-primary" id="submitStockBtn">
                            <i class="fas fa-save me-1"></i>
                            Ajustar Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal de Edición Masiva -->
    <div class="modal fade bulk-edit-modal" id="bulkEditModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edición Masiva de Productos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="bulkEditForm" method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="bulk_update">
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Edita los campos que desees modificar. Solo se actualizarán los productos cuyos valores cambies.
                        </div>

                        <div class="table-container">
                            <table class="table table-striped table-hover bulk-edit-table">
                                <thead>
                                    <tr>
                                        <th style="width: 30%">Producto</th>
                                        <th style="width: 15%">Stock Actual</th>
                                        <th style="width: 20%">Precio Costo</th>
                                        <th style="width: 20%">Precio Venta</th>
                                        <th style="width: 15%">Ganancia</th>
                                    </tr>
                                </thead>
                                <tbody id="bulkEditTableBody">
                                    <!-- Se llenará dinámicamente con JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-1"></i>Cancelar
                        </button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>Guardar Todos los Cambios
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    
    <!-- JavaScript del Autocompletado de Productos -->
    <script src="js/product-autocomplete.js"></script>
    
    <script>
        // JavaScript completo para products.php con gestión de stock
document.addEventListener('DOMContentLoaded', function() {
    initializeMobileMenu();
    setupFilters();
    setupModalHandlers();
    setupFormValidation();
    setupStockModalHandlers();
    setupCalculations();
    autoCloseAlerts();
    preventDoubleTap();
});

// Variables globales para el ajuste de stock
let currentAdjustmentType = 'add';
let currentProductStock = 0;

// ====== MENÚ MÓVIL ======
function initializeMobileMenu() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarClose = document.getElementById('sidebarClose');

    // Toggle mobile menu
    if (mobileMenuToggle) {
        mobileMenuToggle.addEventListener('click', function(e) {
            e.preventDefault();
            sidebar.classList.toggle('show');
            sidebarBackdrop.classList.toggle('show');
            document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
        });
    }

    // Close sidebar when clicking close button
    if (sidebarClose) {
        sidebarClose.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    // Close sidebar when clicking backdrop
    if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function(e) {
            e.preventDefault();
            closeSidebar();
        });
    }

    // Close sidebar when clicking a nav link on mobile
    document.querySelectorAll('.sidebar .nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            if (window.innerWidth < 992) {
                setTimeout(closeSidebar, 100);
            }
        });
    });

    // Handle window resize
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

// ====== FILTROS ======
function setupFilters() {
    const searchInput = document.getElementById('searchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const statusFilter = document.getElementById('statusFilter');

    if (searchInput) searchInput.addEventListener('input', filterProducts);
    if (categoryFilter) categoryFilter.addEventListener('change', filterProducts);
    if (statusFilter) statusFilter.addEventListener('change', filterProducts);
}

function filterProducts() {
    const search = document.getElementById('searchInput').value.toLowerCase();
    const category = document.getElementById('categoryFilter').value;
    const status = document.getElementById('statusFilter').value;
    
    document.querySelectorAll('.product-item').forEach(item => {
        const name = item.dataset.name;
        const itemCategory = item.dataset.category;
        const itemStatus = item.dataset.status;
        
        let show = true;
        
        // Filter by search
        if (search && !name.includes(search)) {
            show = false;
        }
        
        // Filter by category
        if (category && itemCategory !== category) {
            show = false;
        }
        
        // Filter by status
        if (status && itemStatus !== status) {
            show = false;
        }
        
        item.style.display = show ? 'block' : 'none';
    });
}

function clearFilters() {
    document.getElementById('searchInput').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('statusFilter').value = '';
    filterProducts();
}

// ====== MANEJO DEL MODAL PRINCIPAL ======
function setupModalHandlers() {
    const modal = document.getElementById('productModal');
    const form = document.getElementById('productForm');

    // Manejar botón cancelar específico
    const cancelBtn = document.getElementById('cancelModalBtn');
    if (cancelBtn) {
        cancelBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            closeModal();
        });
    }

    // Manejar clicks en el modal de forma general
    if (modal) {
        modal.addEventListener('click', function(e) {
            // Si se hace click en el backdrop (fuera del contenido)
            if (e.target === modal) {
                // No hacer nada (mantener el modal abierto)
                e.preventDefault();
                return;
            }
            
            // Si se hace click dentro del contenido del modal, detener propagación
            if (e.target.closest('.modal-content')) {
                e.stopPropagation();
            }
        });
    }

    // Prevenir que el formulario cierre el modal al enviarse
    if (form) {
        form.addEventListener('submit', function(e) {
            e.stopPropagation();
        });
    }

    // Configurar modal para que no se cierre accidentalmente
    if (modal) {
        modal.addEventListener('show.bs.modal', function() {
            // Desactivar cierre por teclado y backdrop
            const modalInstance = bootstrap.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance._config.backdrop = 'static';
                modalInstance._config.keyboard = false;
            }
        });
    }
}

function closeModal() {
    const modal = bootstrap.Modal.getInstance(document.getElementById('productModal'));
    if (modal) {
        modal.hide();
    }
}

// ====== CÁLCULOS AUTOMÁTICOS ======
function setupCalculations() {
    // Configurar eventos para cálculos automáticos
    const priceInput = document.getElementById('productPrice');
    const costInput = document.getElementById('productCost');
    const stockInput = document.getElementById('stockQuantity');
    const lowStockInput = document.getElementById('lowStockAlert');

    if (priceInput) priceInput.addEventListener('input', updateCalculations);
    if (costInput) costInput.addEventListener('input', updateCalculations);
    if (stockInput) stockInput.addEventListener('input', updateStockStatus);
    if (lowStockInput) lowStockInput.addEventListener('input', updateStockStatus);

    // Inicializar cálculos
    updateCalculations();
    updateStockStatus();
}

function updateCalculations() {
    const price = parseFloat(document.getElementById('productPrice')?.value) || 0;
    const cost = parseFloat(document.getElementById('productCost')?.value) || 0;
    
    // Calcular margen de ganancia
    const profit = price - cost;
    const profitPercentage = cost > 0 ? ((profit / cost) * 100) : 0;
    
    // Actualizar campos calculados
    const profitMarginEl = document.getElementById('profitMargin');
    const profitPercentageEl = document.getElementById('profitPercentage');
    const summaryPriceEl = document.getElementById('summaryPrice');
    
    if (profitMarginEl) {
        profitMarginEl.textContent = profit > 0 ? `$${profit.toFixed(2)}` : '$0.00';
    }
    if (profitPercentageEl) {
        profitPercentageEl.textContent = profitPercentage > 0 ? `${profitPercentage.toFixed(1)}%` : '0%';
    }
    if (summaryPriceEl) {
        summaryPriceEl.textContent = `$${price.toFixed(2)}`;
    }
    
    // Cambiar color según rentabilidad
    if (profitMarginEl && profitPercentageEl) {
        if (profit > 0) {
            profitMarginEl.className = 'text-success fw-bold';
            profitPercentageEl.className = 'text-success fw-bold';
        } else if (profit === 0) {
            profitMarginEl.className = 'text-warning fw-bold';
            profitPercentageEl.className = 'text-warning fw-bold';
        } else {
            profitMarginEl.className = 'text-danger fw-bold';
            profitPercentageEl.className = 'text-danger fw-bold';
        }
    }
}

function toggleInventoryFields() {
    const trackInventory = document.getElementById('trackInventory')?.checked;
    const inventoryFields = document.getElementById('inventoryFields');
    const stockQuantity = document.getElementById('stockQuantity');
    const lowStockAlert = document.getElementById('lowStockAlert');
    
    if (trackInventory) {
        if (inventoryFields) inventoryFields.style.display = 'block';
        if (stockQuantity) stockQuantity.required = true;
        updateStockStatus();
    } else {
        if (inventoryFields) inventoryFields.style.display = 'none';
        if (stockQuantity) {
            stockQuantity.required = false;
            stockQuantity.value = '';
        }
        if (lowStockAlert) lowStockAlert.value = '10';
        // Ocultar stock en resumen
        const summaryStock = document.getElementById('summaryStock');
        if (summaryStock) summaryStock.textContent = '-';
        const stockStatus = document.getElementById('stockStatus');
        if (stockStatus) stockStatus.classList.add('d-none');
    }
}

function updateStockStatus() {
    const trackInventory = document.getElementById('trackInventory')?.checked;
    
    if (!trackInventory) {
        const summaryStock = document.getElementById('summaryStock');
        const stockStatus = document.getElementById('stockStatus');
        if (summaryStock) summaryStock.textContent = '-';
        if (stockStatus) stockStatus.classList.add('d-none');
        return;
    }
    
    const stock = parseInt(document.getElementById('stockQuantity')?.value) || 0;
    const lowStockLimit = parseInt(document.getElementById('lowStockAlert')?.value) || 10;
    
    // Actualizar resumen
    const summaryStock = document.getElementById('summaryStock');
    if (summaryStock) summaryStock.textContent = `${stock} und`;
    
    // Actualizar estado del stock
    const statusDiv = document.getElementById('stockStatus');
    const statusText = document.getElementById('stockStatusText');
    
    if (statusDiv && statusText) {
        statusDiv.classList.remove('d-none', 'alert-success', 'alert-warning', 'alert-danger');
        
        if (stock === 0) {
            statusDiv.classList.add('alert-danger');
            statusText.textContent = '⚠️ Producto sin stock disponible';
        } else if (stock <= lowStockLimit) {
            statusDiv.classList.add('alert-warning');
            statusText.textContent = `⚠️ Stock bajo: ${stock} unidades restantes`;
        } else {
            statusDiv.classList.add('alert-success');
            statusText.textContent = `✅ Stock disponible: ${stock} unidades`;
        }
    }
}

// ====== VALIDACIÓN DE FORMULARIO ======
function setupFormValidation() {
    const form = document.getElementById('productForm');
    if (!form) return;

    form.addEventListener('submit', function(e) {
        const name = document.getElementById('productName')?.value.trim();
        const price = document.getElementById('productPrice')?.value;

        if (!name) {
            e.preventDefault();
            alert('El nombre del producto es requerido');
            document.getElementById('productName')?.focus();
            return false;
        }

        if (!price || parseFloat(price) <= 0) {
            e.preventDefault();
            alert('El precio debe ser mayor a 0');
            document.getElementById('productPrice')?.focus();
            return false;
        }

        // Mostrar indicador de carga en el botón
        const submitBtn = document.getElementById('submitBtn');
        if (submitBtn) {
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Guardando...';
            submitBtn.disabled = true;

            // Restaurar botón si hay error (el formulario se envía normalmente si es válido)
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 3000);
        }
    });
}

// ====== FUNCIONES DE PRODUCTOS ======
function newProduct() {
    // Resetear formulario
    const form = document.getElementById('productForm');
    if (form) form.reset();

    // Configurar modal para nuevo producto
    const modalTitle = document.getElementById('modalTitle');
    const formAction = document.getElementById('formAction');
    const submitBtn = document.getElementById('submitBtn');
    const productId = document.getElementById('productId');
    
    if (modalTitle) modalTitle.innerHTML = '<i class="fas fa-plus me-2"></i>Nuevo Producto';
    if (formAction) formAction.value = 'create';
    if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Guardar Producto';
    if (productId) productId.value = '';
    
    // Resetear campos específicos
    resetImagePreview();
    toggleInventoryFields(); // Esto ocultará los campos de inventario
    updateCalculations();
    
    // Valores por defecto
    const productAvailable = document.getElementById('productAvailable');
    const productActive = document.getElementById('productActive');
    const lowStockAlert = document.getElementById('lowStockAlert');
    
    if (productAvailable) productAvailable.checked = true;
    if (productActive) productActive.checked = true;
    if (lowStockAlert) lowStockAlert.value = '10';

    // Mostrar modal
    const modalElement = document.getElementById('productModal');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: 'static',
            keyboard: false
        });
        modal.show();
    }
}

function editProduct(id) {
    // Mostrar indicador de carga
    showLoadingInModal();

    fetch(`api/products.php?id=${id}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(product => {
            // Configurar modal para edición
            const modalTitle = document.getElementById('modalTitle');
            const formAction = document.getElementById('formAction');
            const submitBtn = document.getElementById('submitBtn');
            
            if (modalTitle) modalTitle.innerHTML = '<i class="fas fa-edit me-2"></i>Editar Producto';
            if (formAction) formAction.value = 'update';
            if (submitBtn) submitBtn.innerHTML = '<i class="fas fa-save me-1"></i>Actualizar Producto';
            
            // Llenar campos básicos
            const fields = {
                'productId': product.id,
                'productName': product.name || '',
                'productDescription': product.description || '',
                'productPrice': product.price || '',
                'productCost': product.cost || '',
                'productCategory': product.category_id || '',
                'productPrepTime': product.preparation_time || ''
            };
            
            Object.keys(fields).forEach(fieldId => {
                const element = document.getElementById(fieldId);
                if (element) element.value = fields[fieldId];
            });
            
            const productAvailable = document.getElementById('productAvailable');
            const productActive = document.getElementById('productActive');
            
            if (productAvailable) productAvailable.checked = product.is_available == 1;
            if (productActive) productActive.checked = product.is_active == 1;
            
            // Campos de inventario
            const trackInventory = product.track_inventory == 1;
            const trackInventoryEl = document.getElementById('trackInventory');
            
            if (trackInventoryEl) trackInventoryEl.checked = trackInventory;
            
            if (trackInventory) {
                const stockQuantity = document.getElementById('stockQuantity');
                const lowStockAlert = document.getElementById('lowStockAlert');
                
                if (stockQuantity) stockQuantity.value = product.stock_quantity || '';
                if (lowStockAlert) lowStockAlert.value = product.low_stock_alert || '10';
            }
            
            toggleInventoryFields();
            
            // Manejar imagen
            if (product.image) {
                showImagePreview('../' + product.image);
            } else {
                resetImagePreview();
            }
            
            // Actualizar cálculos
            updateCalculations();
            updateStockStatus();
            
            // Mostrar modal
            const modalElement = document.getElementById('productModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement, {
                    backdrop: 'static',
                    keyboard: false
                });
                modal.show();
            }
        })
        .catch(error => {
            console.error('Error loading product:', error);
            alert('Error al cargar el producto. Inténtelo de nuevo.');
        });
}

function deleteProduct(id, name) {
    const confirmMessage = `¿Está seguro de que desea eliminar el producto "${name}"?\n\nEsta acción no se puede deshacer.`;
    
    if (confirm(confirmMessage)) {
        // Crear formulario para envío
        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';
        
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        
        document.body.appendChild(form);
        form.submit();
    }
}

function toggleAvailability(id, available) {
    // Mostrar confirmación
    const action = available === 'true' ? 'disponible' : 'no disponible';
    if (!confirm(`¿Está seguro de marcar este producto como ${action}?`)) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    // Crear inputs correctamente
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'toggle_availability';
    
    const idInput = document.createElement('input');
    idInput.type = 'hidden';
    idInput.name = 'id';
    idInput.value = id;
    
    // Solo agregar is_available si es true
    form.appendChild(actionInput);
    form.appendChild(idInput);
    
    if (available === 'true') {
        const availableInput = document.createElement('input');
        availableInput.type = 'hidden';
        availableInput.name = 'is_available';
        availableInput.value = '1';
        form.appendChild(availableInput);
    }
    
    document.body.appendChild(form);
    form.submit();
}

function toggleAvailabilityAjax(id, available) {
    const action = available === 'true' ? 'disponible' : 'no disponible';
    if (!confirm(`¿Está seguro de marcar este producto como ${action}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'toggle_availability');
    formData.append('id', id);
    
    if (available === 'true') {
        formData.append('is_available', '1');
    }
    
    // Mostrar loading
    const button = event.target.closest('button') || event.target.closest('a');
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    button.disabled = true;
    
    fetch('products.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        if (data.includes('success')) {
            // Actualizar UI en lugar de recargar
            location.reload();
        } else {
            alert('Error al actualizar el estado del producto');
            console.error('Server response:', data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error de conexión al actualizar el producto');
    })
    .finally(() => {
        button.innerHTML = originalContent;
        button.disabled = false;
    });
}


// ====== MANEJO DE IMÁGENES ======
function previewImage() {
    const fileInput = document.getElementById('productImage');
    const file = fileInput?.files[0];
    
    if (file) {
        // Validar tipo de archivo
        const validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!validTypes.includes(file.type)) {
            alert('Tipo de archivo no válido. Use JPG, PNG o GIF.');
            fileInput.value = '';
            resetImagePreview();
            return;
        }
        
        // Validar tamaño (5MB máximo)
        const maxSize = 5 * 1024 * 1024; // 5MB en bytes
        if (file.size > maxSize) {
            alert('El archivo es muy grande. Máximo 5MB.');
            fileInput.value = '';
            resetImagePreview();
            return;
        }
        
        // Mostrar preview
        const reader = new FileReader();
        reader.onload = function(e) {
            showImagePreview(e.target.result);
        };
        reader.readAsDataURL(file);
    } else {
        resetImagePreview();
    }
}

function showImagePreview(src) {
    const preview = document.getElementById('imagePreview');
    const previewImg = document.getElementById('previewImg');
    const placeholder = document.getElementById('imagePlaceholder');
    
    if (previewImg && preview && placeholder) {
        previewImg.src = src;
        preview.style.display = 'block';
        placeholder.style.display = 'none';
    }
}

function resetImagePreview() {
    const preview = document.getElementById('imagePreview');
    const placeholder = document.getElementById('imagePlaceholder');
    const fileInput = document.getElementById('productImage');
    
    if (preview) preview.style.display = 'none';
    if (placeholder) placeholder.style.display = 'flex';
    if (fileInput) fileInput.value = '';
}

function showLoadingInModal() {
    const modalTitle = document.getElementById('modalTitle');
    if (modalTitle) {
        modalTitle.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Cargando...';
    }
}

// ====== GESTIÓN DE STOCK ======
function setupStockModalHandlers() {
    // Actualizar preview cuando cambie la cantidad
    const quantityInput = document.getElementById('adjustmentQuantity');
    if (quantityInput) {
        quantityInput.addEventListener('input', updateStockPreview);
    }
    
    // Mostrar campo personalizado para "Otro" motivo
    const reasonSelect = document.getElementById('adjustmentReason');
    if (reasonSelect) {
        reasonSelect.addEventListener('change', function() {
            const customGroup = document.getElementById('customReasonGroup');
            const customReason = document.getElementById('customReason');
            if (this.value === 'Otro') {
                if (customGroup) customGroup.style.display = 'block';
                if (customReason) customReason.required = true;
            } else {
                if (customGroup) customGroup.style.display = 'none';
                if (customReason) customReason.required = false;
            }
        });
    }
    
    // Manejar envío del formulario de stock
    const stockForm = document.getElementById('stockForm');
    if (stockForm) {
        stockForm.addEventListener('submit', function(e) {
            const quantity = parseInt(document.getElementById('adjustmentQuantity')?.value) || 0;
            const adjustment = currentAdjustmentType === 'add' ? quantity : -quantity;
            const newStock = currentProductStock + adjustment;
            
            if (newStock < 0) {
                e.preventDefault();
                alert('No se puede reducir el stock por debajo de 0');
                return;
            }
            
            // Crear campo hidden con el ajuste calculado
            const hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'adjustment';
            hiddenInput.value = adjustment;
            this.appendChild(hiddenInput);
            
            // Si es motivo personalizado, usar el valor del campo de texto
            const reasonSelect = document.getElementById('adjustmentReason');
            const customReason = document.getElementById('customReason');
            if (reasonSelect?.value === 'Otro' && customReason?.value.trim()) {
                reasonSelect.value = customReason.value.trim();
            }
            
            // Mostrar loading
            const submitBtn = document.getElementById('submitStockBtn');
            if (submitBtn) {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Ajustando...';
                submitBtn.disabled = true;
            }
        });
    }
}

function adjustStock(productId) {
    // Buscar información del producto
    fetch(`api/products.php?id=${productId}`)
        .then(response => response.json())
        .then(product => {
            if (!product.track_inventory) {
                alert('Este producto no tiene control de inventario activado');
                return;
            }
            
            // Llenar datos del modal
            const stockProductId = document.getElementById('stockProductId');
            const stockProductName = document.getElementById('stockProductName');
            const currentStock = document.getElementById('currentStock');
            const lowStockLimit = document.getElementById('lowStockLimit');
            
            if (stockProductId) stockProductId.value = product.id;
            if (stockProductName) stockProductName.textContent = product.name;
            if (currentStock) currentStock.textContent = product.stock_quantity || 0;
            if (lowStockLimit) lowStockLimit.textContent = product.low_stock_alert || 10;
            
            // Resetear formulario
            const adjustmentQuantity = document.getElementById('adjustmentQuantity');
            const adjustmentReason = document.getElementById('adjustmentReason');
            const customReasonGroup = document.getElementById('customReasonGroup');
            
            if (adjustmentQuantity) adjustmentQuantity.value = '';
            if (adjustmentReason) adjustmentReason.value = 'Ajuste manual';
            if (customReasonGroup) customReasonGroup.style.display = 'none';
            
            // Variables globales
            currentProductStock = parseInt(product.stock_quantity) || 0;
            setAdjustmentType('add');
            
            // Mostrar modal
            const modalElement = document.getElementById('stockModal');
            if (modalElement) {
                const modal = new bootstrap.Modal(modalElement);
                modal.show();
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar la información del producto');
        });
}

function setAdjustmentType(type) {
    currentAdjustmentType = type;
    const sign = document.getElementById('adjustmentSign');
    const buttons = document.querySelectorAll('#stockModal .btn-success, #stockModal .btn-warning');
    
    // Resetear botones
    buttons.forEach(btn => {
        btn.classList.remove('active');
        btn.style.opacity = '0.6';
    });
    
    if (type === 'add') {
        if (sign) {
            sign.textContent = '+';
            sign.className = 'input-group-text text-success';
        }
        const successBtn = document.querySelector('#stockModal .btn-success');
        if (successBtn) {
            successBtn.style.opacity = '1';
            successBtn.classList.add('active');
        }
    } else {
        if (sign) {
            sign.textContent = '-';
            sign.className = 'input-group-text text-warning';
        }
        const warningBtn = document.querySelector('#stockModal .btn-warning');
        if (warningBtn) {
            warningBtn.style.opacity = '1';
            warningBtn.classList.add('active');
        }
    }
    
    updateStockPreview();
}

function updateStockPreview() {
    const quantity = parseInt(document.getElementById('adjustmentQuantity')?.value) || 0;
    const adjustment = currentAdjustmentType === 'add' ? quantity : -quantity;
    const newStock = currentProductStock + adjustment;
    const lowStockLimit = parseInt(document.getElementById('lowStockLimit')?.textContent) || 10;
    
    const newStockPreview = document.getElementById('newStockPreview');
    if (newStockPreview) {
        newStockPreview.textContent = Math.max(0, newStock);
    }
    
    // Mostrar advertencias
    const warning = document.getElementById('stockWarning');
    const warningText = document.getElementById('stockWarningText');
    
    if (warning && warningText) {
        warning.classList.add('d-none');
        warning.classList.remove('alert-success', 'alert-warning', 'alert-danger');
        
        if (newStock < 0) {
            warning.classList.remove('d-none');
            warning.classList.add('alert-danger');
            warningText.textContent = 'No se puede reducir el stock por debajo de 0';
        } else if (newStock === 0) {
            warning.classList.remove('d-none');
            warning.classList.add('alert-danger');
            warningText.textContent = 'El producto quedará sin stock disponible';
        } else if (newStock <= lowStockLimit) {
            warning.classList.remove('d-none');
            warning.classList.add('alert-warning');
            warningText.textContent = `El stock quedará por debajo del límite de alerta (${lowStockLimit} unidades)`;
        }
    }
}

// ====== UTILIDADES ======
function autoCloseAlerts() {
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            try {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            } catch (e) {
                // Ignorar errores si el alert ya fue cerrado
            }
        });
    }, 5000);
}

function preventDoubleTap() {
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function(event) {
        const now = (new Date()).getTime();
        if (now - lastTouchEnd <= 300) {
            event.preventDefault();
        }
        lastTouchEnd = now;
    }, false);
}

// ====== EVENTOS GLOBALES ======
// Prevenir envío de formularios con Enter en campos que no sean textarea
document.addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit') {
        const form = e.target.closest('form');
        if (form && form.id === 'productForm') {
            e.preventDefault();
        }
    }
});

// Manejar errores de red
window.addEventListener('online', function() {
    console.log('Conexión restaurada');
});

window.addEventListener('offline', function() {
    alert('Se perdió la conexión a internet. Verifique su conexión.');
});

// ====== FUNCIONES ADICIONALES DE ACTUALIZACIÓN ======
function updateProductAfterStockAdjustment(productId, newStock, lowStockAlert) {
    const productCard = document.querySelector(`[data-product-id="${productId}"]`);
    if (!productCard) return;
    
    // Actualizar indicador de stock
    const stockIndicator = productCard.querySelector('.stock-indicator');
    if (stockIndicator) {
        stockIndicator.textContent = `${newStock} unidades`;
        
        // Actualizar clases de color
        stockIndicator.classList.remove('text-success', 'text-warning', 'text-danger');
        if (newStock == 0) {
            stockIndicator.classList.add('text-danger');
        } else if (newStock <= lowStockAlert) {
            stockIndicator.classList.add('text-warning');
        } else {
            stockIndicator.classList.add('text-success');
        }
    }
    
    // Actualizar barra de progreso
    const progressBar = productCard.querySelector('.progress-bar');
    if (progressBar) {
        const maxDisplayStock = Math.max(lowStockAlert * 3, 50);
        const stockPercentage = Math.min((newStock / maxDisplayStock) * 100, 100);
        
        progressBar.style.width = `${stockPercentage}%`;
        progressBar.classList.remove('bg-success', 'bg-warning', 'bg-danger');
        
        if (newStock == 0) {
            progressBar.classList.add('bg-danger');
        } else if (newStock <= lowStockAlert) {
            progressBar.classList.add('bg-warning');
        } else {
            progressBar.classList.add('bg-success');
        }
    }
    
    // Actualizar badges de estado
    const badgeContainer = productCard.querySelector('.d-flex.flex-wrap.gap-1');
    if (badgeContainer) {
        // Remover badges de stock existentes
        const stockBadges = badgeContainer.querySelectorAll('.badge:not(.bg-primary):not(.bg-info)');
        stockBadges.forEach(badge => {
            if (badge.textContent.includes('Sin stock') || badge.textContent.includes('Stock bajo')) {
                badge.remove();
            }
        });
        
        // Agregar nuevo badge si es necesario
        if (newStock == 0) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-danger small';
            badge.textContent = 'Sin stock';
            badgeContainer.appendChild(badge);
        } else if (newStock <= lowStockAlert) {
            const badge = document.createElement('span');
            badge.className = 'badge bg-warning small';
            badge.textContent = 'Stock bajo';
            badgeContainer.appendChild(badge);
        }
    }
    
    // Actualizar indicador de stock bajo en la esquina superior izquierda
    const topBadge = productCard.querySelector('.position-absolute.top-0.start-0 .badge');
    if (topBadge) {
        if (newStock == 0) {
            topBadge.textContent = 'SIN STOCK';
            topBadge.className = 'badge bg-danger small';
        } else if (newStock <= lowStockAlert) {
            topBadge.textContent = 'STOCK BAJO';
            topBadge.className = 'badge bg-warning small';
        } else {
            topBadge.parentElement.remove(); // Remover el indicador si el stock está bien
        }
    } else if (newStock <= lowStockAlert) {
        // Crear indicador si no existe y el stock está bajo
        const indicator = document.createElement('div');
        indicator.className = 'position-absolute top-0 start-0 m-2';
        
        const badge = document.createElement('span');
        badge.className = newStock == 0 ? 'badge bg-danger small' : 'badge bg-warning small';
        badge.textContent = newStock == 0 ? 'SIN STOCK' : 'STOCK BAJO';
        
        indicator.appendChild(badge);
        productCard.querySelector('.card').appendChild(indicator);
    }
}

// Función para recargar estadísticas de inventario
function updateInventoryStats() {
    fetch('api/inventory_stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.stats) {
                // Actualizar las tarjetas de estadísticas
                const statCards = document.querySelectorAll('#inventoryStatsRow .stat-number');
                if (statCards.length >= 4) {
                    statCards[0].textContent = data.stats.tracked_products || 0;
                    statCards[1].textContent = data.stats.good_stock || 0;
                    statCards[2].textContent = data.stats.low_stock || 0;
                    statCards[3].textContent = data.stats.out_of_stock || 0;
                }
            }
        })
        .catch(error => {
            console.error('Error updating inventory stats:', error);
        });
}

// Función para manejar el clic en los botones de stock rápido
function quickStockAction(productId, action) {
    if (action === 'add') {
        // Agregar 1 unidad rápidamente
        quickAdjustStock(productId, 1, 'Reposición rápida');
    } else if (action === 'subtract') {
        // Quitar 1 unidad rápidamente
        quickAdjustStock(productId, -1, 'Venta rápida');
    }
}

// Función para ajuste rápido de stock
function quickAdjustStock(productId, adjustment, reason) {
    const formData = new FormData();
    formData.append('action', 'adjust_stock');
    formData.append('id', productId);
    formData.append('adjustment', adjustment);
    formData.append('reason', reason);
    
    fetch('products.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Si la respuesta es exitosa, recargar la página o actualizar el elemento
        if (data.includes('success')) {
            location.reload(); // Recargar para ver los cambios
        } else {
            alert('Error al ajustar el stock');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error al ajustar el stock');
    });
}

// Función para mostrar notificación de stock ajustado
function showStockNotification(message, type = 'success') {
    const alert = document.createElement('div');
    alert.className = `alert alert-${type} alert-dismissible fade show`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.main-content');
    if (container) {
        container.insertBefore(alert, container.firstChild);
        
        // Auto-cerrar después de 5 segundos
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }
}

// Interceptar el envío del formulario de ajuste de stock para actualizar la UI
document.addEventListener('DOMContentLoaded', function() {
    const stockForm = document.getElementById('stockForm');
    if (stockForm) {
        stockForm.addEventListener('submit', function(e) {
            // Obtener datos antes del envío
            const productId = document.getElementById('stockProductId')?.value;
            const quantity = parseInt(document.getElementById('adjustmentQuantity')?.value) || 0;
            const adjustment = currentAdjustmentType === 'add' ? quantity : -quantity;
            const newStock = currentProductStock + adjustment;
            const lowStockAlert = parseInt(document.getElementById('lowStockLimit')?.textContent) || 10;
            
            // Después de un envío exitoso (simular con timeout)
            setTimeout(() => {
                // Solo actualizar si la página no se recarga
                if (document.getElementById('stockModal')) {
                    updateProductAfterStockAdjustment(productId, Math.max(0, newStock), lowStockAlert);
                    updateInventoryStats();
                }
            }, 1000);
        });
    }
});

// ====== FUNCIONES DE EXPORTACIÓN/IMPORTACIÓN (OPCIONALES) ======
function exportProducts() {
    // Función para exportar productos a CSV
    const products = [];
    document.querySelectorAll('.product-item').forEach(item => {
        const card = item.querySelector('.product-card');
        const name = item.querySelector('.card-title')?.textContent || '';
        const price = item.querySelector('.h6.text-success')?.textContent || '';
        const category = item.querySelector('.badge.bg-primary')?.textContent || '';
        const stock = item.querySelector('.stock-indicator')?.textContent || '';
        
        products.push({
            name,
            price,
            category,
            stock
        });
    });
    
    // Convertir a CSV
    const csvContent = "data:text/csv;charset=utf-8," 
        + "Nombre,Precio,Categoría,Stock\n"
        + products.map(p => `"${p.name}","${p.price}","${p.category}","${p.stock}"`).join("\n");
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "productos.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// ====== FUNCIONES DE BÚSQUEDA AVANZADA ======
function advancedSearch() {
    // Función para búsqueda avanzada con múltiples criterios
    const searchTerm = document.getElementById('searchInput')?.value.toLowerCase() || '';
    const category = document.getElementById('categoryFilter')?.value || '';
    const status = document.getElementById('statusFilter')?.value || '';
    
    let visibleCount = 0;
    
    document.querySelectorAll('.product-item').forEach(item => {
        const name = item.dataset.name || '';
        const itemCategory = item.dataset.category || '';
        const itemStatus = item.dataset.status || '';
        
        // Criterios de búsqueda
        const matchesSearch = !searchTerm || name.includes(searchTerm);
        const matchesCategory = !category || itemCategory === category;
        const matchesStatus = !status || itemStatus === status;
        
        const shouldShow = matchesSearch && matchesCategory && matchesStatus;
        
        item.style.display = shouldShow ? 'block' : 'none';
        if (shouldShow) visibleCount++;
    });
    
    // Mostrar contador de resultados
    updateSearchResults(visibleCount);
}

function updateSearchResults(count) {
    let resultCounter = document.getElementById('searchResultCounter');
    if (!resultCounter) {
        resultCounter = document.createElement('div');
        resultCounter.id = 'searchResultCounter';
        resultCounter.className = 'text-muted small mb-2';
        
        const container = document.getElementById('productsContainer');
        if (container && container.parentNode) {
            container.parentNode.insertBefore(resultCounter, container);
        }
    }
    
    if (count === 0) {
        resultCounter.innerHTML = '<i class="fas fa-search me-1"></i>No se encontraron productos';
        resultCounter.className = 'text-warning small mb-2';
    } else {
        const total = document.querySelectorAll('.product-item').length;
        resultCounter.innerHTML = `<i class="fas fa-search me-1"></i>Mostrando ${count} de ${total} productos`;
        resultCounter.className = 'text-muted small mb-2';
    }
}

// Función para mostrar/ocultar productos inactivos
function toggleInactive() {
    const container = document.getElementById('inactiveProducts');
    const text = document.getElementById('toggleText');
    const btn = document.getElementById('toggleInactiveBtn');
    const icon = btn.querySelector('i');
    
    if (container.style.display === 'none') {
        container.style.display = 'block';
        text.textContent = 'Ocultar';
        icon.className = 'fas fa-eye-slash me-1';
    } else {
        container.style.display = 'none';
        text.textContent = 'Mostrar';
        icon.className = 'fas fa-eye me-1';
    }
}

// Función para reactivar un producto
function reactivateProduct(id, name) {
    if (!confirm(`¿Desea reactivar el producto "${name}"?\n\nEl producto volverá a estar disponible en el menú.`)) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    form.innerHTML = `
        <input type="hidden" name="action" value="reactivate">
        <input type="hidden" name="id" value="${id}">
    `;
    
    document.body.appendChild(form);
    form.submit();
}

// Función para forzar eliminación permanente
function forceDeleteProduct(id, name) {
    const confirmMessage = `⚠️ ATENCIÓN: Eliminación Permanente

¿Está COMPLETAMENTE SEGURO de que desea eliminar permanentemente "${name}"?

Esta acción:
• Eliminará el producto de la base de datos
• Eliminará su imagen del servidor
• NO se puede deshacer
• Los pedidos existentes NO se verán afectados (mantendrán el nombre del producto)

¿Desea continuar?`;
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    // Segunda confirmación
    if (!confirm(`Última confirmación:\n\n¿Eliminar "${name}" PERMANENTEMENTE?`)) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    form.innerHTML = `
        <input type="hidden" name="action" value="force_delete">
        <input type="hidden" name="id" value="${id}">
    `;
    
    document.body.appendChild(form);
    form.submit();
}

// ====== FUNCIONES DE EDICIÓN MASIVA ======
let originalBulkData = {};

function openBulkEditModal() {
    // Cargar todos los productos activos
    fetch('get_all_products.php')
        .then(response => response.json())
        .then(products => {
            const tbody = document.getElementById('bulkEditTableBody');
            tbody.innerHTML = '';
            originalBulkData = {};
            
            products.forEach(product => {
                // Guardar datos originales
                originalBulkData[product.id] = {
                    stock: product.stock_quantity || 0,
                    cost: product.cost,
                    price: product.price
                };
                
                const row = document.createElement('tr');
                const profit = product.price - product.cost;
                const profitPercent = product.cost > 0 ? ((profit / product.cost) * 100).toFixed(1) : 0;
                
                row.innerHTML = `
                    <td>
                        <strong>${escapeHtml(product.name)}</strong>
                        ${product.category_name ? '<br><small class="text-muted">' + escapeHtml(product.category_name) + '</small>' : ''}
                    </td>
                    <td>
                        ${product.track_inventory ? 
                            `<input type="number" class="form-control bulk-edit-input" 
                                   data-product-id="${product.id}" 
                                   data-field="stock" 
                                   value="${product.stock_quantity || 0}" 
                                   min="0" 
                                   onchange="updateBulkProfit(${product.id})">` 
                            : '<span class="text-muted">N/A</span>'}
                    </td>
                    <td>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control bulk-edit-input" 
                                   data-product-id="${product.id}" 
                                   data-field="cost" 
                                   value="${product.cost}" 
                                   min="0" 
                                   step="0.01" 
                                   onchange="updateBulkProfit(${product.id})">
                        </div>
                    </td>
                    <td>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control bulk-edit-input" 
                                   data-product-id="${product.id}" 
                                   data-field="price" 
                                   value="${product.price}" 
                                   min="0" 
                                   step="0.01" 
                                   onchange="updateBulkProfit(${product.id})">
                        </div>
                    </td>
                    <td>
                        <span id="profit-${product.id}" class="badge ${profit >= 0 ? 'bg-success' : 'bg-danger'}">
                            $${profit.toFixed(2)} (${profitPercent}%)
                        </span>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
            
            const modal = new bootstrap.Modal(document.getElementById('bulkEditModal'));
            modal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar los productos');
        });
}

function updateBulkProfit(productId) {
    const costInput = document.querySelector(`input[data-product-id="${productId}"][data-field="cost"]`);
    const priceInput = document.querySelector(`input[data-product-id="${productId}"][data-field="price"]`);
    const profitSpan = document.getElementById(`profit-${productId}`);
    
    if (costInput && priceInput && profitSpan) {
        const cost = parseFloat(costInput.value) || 0;
        const price = parseFloat(priceInput.value) || 0;
        const profit = price - cost;
        const profitPercent = cost > 0 ? ((profit / cost) * 100).toFixed(1) : 0;
        
        profitSpan.className = `badge ${profit >= 0 ? 'bg-success' : 'bg-danger'}`;
        profitSpan.textContent = `$${profit.toFixed(2)} (${profitPercent}%)`;
    }
}

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// Interceptar envío del formulario de edición masiva
document.addEventListener('DOMContentLoaded', function() {
    const bulkEditForm = document.getElementById('bulkEditForm');
    if (bulkEditForm) {
        bulkEditForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const products = [];
            const inputs = document.querySelectorAll('.bulk-edit-input');
            
            inputs.forEach(input => {
                const productId = input.dataset.productId;
                const field = input.dataset.field;
                const value = input.value;
                
                // Solo agregar si el valor cambió
                if (originalBulkData[productId] && originalBulkData[productId][field] != value) {
                    let product = products.find(p => p.id == productId);
                    if (!product) {
                        product = { id: productId };
                        products.push(product);
                    }
                    product[field] = value;
                }
            });
            
            if (products.length === 0) {
                alert('No hay cambios para guardar');
                return;
            }
            
            if (!confirm(`¿Desea guardar los cambios en ${products.length} producto(s)?`)) {
                return;
            }
            
            // Crear formulario con los productos modificados
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            let formHtml = '<input type="hidden" name="action" value="bulk_update">';
            products.forEach((product, index) => {
                formHtml += `<input type="hidden" name="products[${index}][id]" value="${product.id}">`;
                if (product.stock !== undefined) {
                    formHtml += `<input type="hidden" name="products[${index}][stock]" value="${product.stock}">`;
                }
                if (product.cost !== undefined) {
                    formHtml += `<input type="hidden" name="products[${index}][cost]" value="${product.cost}">`;
                }
                if (product.price !== undefined) {
                    formHtml += `<input type="hidden" name="products[${index}][price]" value="${product.price}">`;
                }
            });
            
            form.innerHTML = formHtml;
            document.body.appendChild(form);
            form.submit();
        });
    }
});

// ====== INICIALIZACIÓN FINAL ======

window.reactivateProduct = reactivateProduct;
window.forceDeleteProduct = forceDeleteProduct;
window.openBulkEditModal = openBulkEditModal;
window.updateBulkProfit = updateBulkProfit;
window.newProduct = newProduct;
window.editProduct = editProduct;
window.deleteProduct = deleteProduct;
window.toggleAvailability = toggleAvailability;
window.adjustStock = adjustStock;
window.setAdjustmentType = setAdjustmentType;
window.toggleInventoryFields = toggleInventoryFields;
window.previewImage = previewImage;
window.clearFilters = clearFilters;
window.exportProducts = exportProducts;

// Log para debug
console.log('Products management system loaded successfully');
console.log('Available functions:', {
    newProduct: typeof newProduct,
    editProduct: typeof editProduct,
    adjustStock: typeof adjustStock,
    toggleInventoryFields: typeof toggleInventoryFields
});
    </script>

<?php include 'footer.php'; ?>
</body>
</html>