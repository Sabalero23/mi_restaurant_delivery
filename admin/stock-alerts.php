// =============================================================================
// ARCHIVO 4: Nuevo archivo para gestión de stock bajo (opcional)
// =============================================================================
// admin/stock-alerts.php

<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../config/stock_functions.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('products');

$database = new Database();
$db = $database->getConnection();

// Obtener productos con stock bajo
$low_stock_products = getLowStockProducts($db);

// HTML para mostrar alertas (puedes integrarlo en el dashboard)
if (!empty($low_stock_products)): ?>
    <div class="alert alert-warning">
        <h5><i class="fas fa-exclamation-triangle"></i> Productos con Stock Bajo</h5>
        <ul class="mb-0">
            <?php foreach ($low_stock_products as $product): ?>
                <li>
                    <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                    - Stock actual: <?php echo $product['stock_quantity']; ?>
                    (Alerta: <?php echo $product['low_stock_alert']; ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif;