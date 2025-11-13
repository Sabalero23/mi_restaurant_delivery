<?php
// admin/compras.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();

// Verificar permisos
if (!$auth->hasPermission('kardex') && !$auth->hasPermission('products') && !$auth->hasPermission('all')) {
    header("Location: dashboard.php");
    exit;
}

$database = new Database();
$db = $database->getConnection();

// Obtener configuraciones
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

// Filtros
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');

// Obtener productos con inventario
$query_products = "SELECT id, name, stock_quantity, cost, price FROM products WHERE track_inventory = 1 AND is_active = 1 ORDER BY name";
$stmt_products = $db->prepare($query_products);
$stmt_products->execute();
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);

// Construir query de compras
$query = "SELECT 
    p.*,
    u.full_name as created_by_name,
    (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) as total_items,
    (SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = p.id) as total_quantity
    FROM purchases p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE 1=1";

$params = array();

if ($status_filter) {
    $query .= " AND p.status = :status";
    $params[':status'] = $status_filter;
}

if ($date_from) {
    $query .= " AND p.purchase_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $query .= " AND p.purchase_date <= :date_to";
    $params[':date_to'] = $date_to;
}

$query .= " ORDER BY p.purchase_date DESC, p.id DESC LIMIT 100";

$stmt = $db->prepare($query);
$stmt->execute($params);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular totales
$total_compras = count($purchases);
$total_monto = array_sum(array_column($purchases, 'total_amount'));

// Incluir sistema de temas
$theme_file = '../config/theme.php';
if (file_exists($theme_file)) {
    require_once $theme_file;
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
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compras - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <?php if (file_exists('../assets/css/generate-theme.php')): ?>
        <link rel="stylesheet" href="../assets/css/generate-theme.php?v=<?php echo time(); ?>">
    <?php endif; ?>

   <style>
/* Extensiones específicas de cocina usando variables del tema */
:root {
    --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --kitchen-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
    --sidebar-mobile-width: 100%;
}

/* Mobile Top Bar para cocina */
.mobile-topbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1040;
    background: linear-gradient(135deg, var(--warning-color), var(--accent-color));
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
    width: var(--kitchen-sidebar-width);
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

/* Main content forzado a colores claros */
.main-content {
    margin-left: var(--kitchen-sidebar-width);
    padding: 2rem;
    min-height: 100vh;
    transition: margin-left var(--transition-base);
    background: #f8f9fa !important;
    color: #212529 !important;
}
        .card {
            background: #ffffff !important;
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
        }

        .stats-card {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white !important;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: transform 0.2s;
        }

        .stats-card:hover {
            transform: translateY(-5px);
        }

        .stats-card h3 {
            font-size: 2rem;
            margin: 0;
            font-weight: bold;
            color: white !important;
        }

        .badge-pending {
            background: #ffc107;
            color: #000;
        }

        .badge-completed {
            background: #28a745;
            color: white;
        }

        .badge-cancelled {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2 class="mb-0">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Compras de Productos
                    </h2>
                    <p class="text-muted mb-0">Registro de compras e ingresos de stock</p>
                </div>
                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <button class="btn btn-success" onclick="openPurchaseModal()">
                        <i class="fas fa-plus me-1"></i>
                        Nueva Compra
                    </button>
                    <button class="btn btn-secondary" onclick="exportPurchases()">
                        <i class="fas fa-file-excel me-1"></i>
                        Exportar
                    </button>
                </div>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="stats-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>TOTAL COMPRAS</p>
                            <h3><?php echo $total_compras; ?></h3>
                        </div>
                        <i class="fas fa-shopping-cart fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-card" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p>MONTO TOTAL</p>
                            <h3>$<?php echo number_format($total_monto, 2, ',', '.'); ?></h3>
                        </div>
                        <i class="fas fa-dollar-sign fa-3x" style="opacity: 0.3;"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filtros -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="" id="filterForm">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="status" class="form-select" onchange="document.getElementById('filterForm').submit()">
                                <option value="">Todos</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completada</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelada</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Desde</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>" onchange="document.getElementById('filterForm').submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Hasta</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>" onchange="document.getElementById('filterForm').submit()">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-secondary w-100" onclick="window.location.href='compras.php'">
                                <i class="fas fa-times me-1"></i>
                                Limpiar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla de compras -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Registro de Compras
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($purchases)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-shopping-cart fa-4x text-muted mb-3"></i>
                        <h5>No hay compras registradas</h5>
                        <p class="text-muted">Comienza registrando tu primera compra</p>
                        <button class="btn btn-primary" onclick="openPurchaseModal()">
                            <i class="fas fa-plus me-1"></i>
                            Nueva Compra
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>FECHA</th>
                                    <th>Nº COMPRA</th>
                                    <th>PROVEEDOR</th>
                                    <th>Nº FACTURA</th>
                                    <th class="text-center">ITEMS</th>
                                    <th class="text-center">CANTIDAD</th>
                                    <th class="text-end">MONTO</th>
                                    <th>ESTADO</th>
                                    <th>ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($purchases as $purchase): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($purchase['purchase_date'])); ?></td>
                                    <td><strong><?php echo $purchase['purchase_number']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($purchase['supplier'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($purchase['invoice_number'] ?? '-'); ?></td>
                                    <td class="text-center"><?php echo $purchase['total_items']; ?></td>
                                    <td class="text-center"><?php echo $purchase['total_quantity']; ?></td>
                                    <td class="text-end"><strong>$<?php echo number_format($purchase['total_amount'], 2, ',', '.'); ?></strong></td>
                                    <td>
                                        <span class="badge badge-<?php echo $purchase['status']; ?>">
                                            <?php echo ucfirst($purchase['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info" onclick="viewPurchase(<?php echo $purchase['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($purchase['status'] === 'pending'): ?>
                                        <button class="btn btn-sm btn-danger" onclick="cancelPurchase(<?php echo $purchase['id']; ?>)">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal Nueva Compra -->
    <div class="modal fade" id="purchaseModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: var(--primary-gradient); color: white;">
                    <h5 class="modal-title">
                        <i class="fas fa-shopping-cart me-2"></i>
                        Nueva Compra
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="purchaseForm">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Proveedor</label>
                                <input type="text" name="supplier" class="form-control" placeholder="Nombre del proveedor">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Nº Factura</label>
                                <input type="text" name="invoice_number" class="form-control">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Fecha *</label>
                                <input type="date" name="purchase_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notas</label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>

                        <hr>
                        <h6>Productos</h6>
                        
                        <div id="purchaseItems">
                            <div class="purchase-item row mb-2">
                                <div class="col-md-5">
                                    <select class="form-select product-select" name="items[0][product_id]" required onchange="updateProductInfo(this, 0)">
                                        <option value="">Seleccionar producto...</option>
                                        <?php foreach ($products as $prod): ?>
                                            <option value="<?php echo $prod['id']; ?>" 
                                                data-cost="<?php echo $prod['cost']; ?>"
                                                data-stock="<?php echo $prod['stock_quantity']; ?>">
                                                <?php echo htmlspecialchars($prod['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control quantity-input" name="items[0][quantity]" placeholder="Cant." min="1" required onchange="calculateItem(0)">
                                </div>
                                <div class="col-md-2">
                                    <input type="number" class="form-control cost-input" name="items[0][unit_cost]" placeholder="Costo" step="0.01" min="0" required onchange="calculateItem(0)">
                                </div>
                                <div class="col-md-2">
                                    <input type="text" class="form-control subtotal-display" readonly placeholder="Subtotal" id="subtotal_0">
                                    <input type="hidden" name="items[0][subtotal]" id="subtotal_value_0">
                                </div>
                                <div class="col-md-1">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="button" class="btn btn-secondary btn-sm" onclick="addItem()">
                            <i class="fas fa-plus me-1"></i>
                            Agregar Producto
                        </button>

                        <div class="alert alert-info mt-3">
                            <strong>Total: $<span id="totalAmount">0.00</span></strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save me-1"></i>
                            Guardar Compra
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let purchaseModal;
        let itemCount = 0;

        document.addEventListener('DOMContentLoaded', function() {
            purchaseModal = new bootstrap.Modal(document.getElementById('purchaseModal'));
            
            document.getElementById('purchaseForm').addEventListener('submit', function(e) {
                e.preventDefault();
                savePurchase();
            });
        });

        function openPurchaseModal() {
            document.getElementById('purchaseForm').reset();
            document.getElementById('purchaseItems').innerHTML = '';
            itemCount = 0;
            addItem();
            purchaseModal.show();
        }

        function addItem() {
            const template = `
                <div class="purchase-item row mb-2" data-index="${itemCount}">
                    <div class="col-md-5">
                        <select class="form-select product-select" name="items[${itemCount}][product_id]" required onchange="updateProductInfo(this, ${itemCount})">
                            <option value="">Seleccionar producto...</option>
                            <?php foreach ($products as $prod): ?>
                                <option value="<?php echo $prod['id']; ?>" 
                                    data-cost="<?php echo $prod['cost']; ?>"
                                    data-stock="<?php echo $prod['stock_quantity']; ?>">
                                    <?php echo htmlspecialchars($prod['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control quantity-input" name="items[${itemCount}][quantity]" placeholder="Cant." min="1" required onchange="calculateItem(${itemCount})">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control cost-input" name="items[${itemCount}][unit_cost]" placeholder="Costo" step="0.01" min="0" required onchange="calculateItem(${itemCount})">
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control subtotal-display" readonly placeholder="Subtotal" id="subtotal_${itemCount}">
                        <input type="hidden" name="items[${itemCount}][subtotal]" id="subtotal_value_${itemCount}">
                    </div>
                    <div class="col-md-1">
                        <button type="button" class="btn btn-danger btn-sm" onclick="removeItem(this)">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;
            
            document.getElementById('purchaseItems').insertAdjacentHTML('beforeend', template);
            itemCount++;
        }

        function removeItem(button) {
            const item = button.closest('.purchase-item');
            item.remove();
            calculateTotal();
        }

        function updateProductInfo(select, index) {
            const option = select.options[select.selectedIndex];
            const cost = option.getAttribute('data-cost');
            
            if (cost) {
                const costInput = select.closest('.purchase-item').querySelector('.cost-input');
                costInput.value = cost;
                calculateItem(index);
            }
        }

        function calculateItem(index) {
            const item = document.querySelector(`[data-index="${index}"]`);
            if (!item) return;
            
            const quantity = parseFloat(item.querySelector('.quantity-input').value) || 0;
            const cost = parseFloat(item.querySelector('.cost-input').value) || 0;
            const subtotal = quantity * cost;
            
            document.getElementById(`subtotal_${index}`).value = subtotal.toFixed(2);
            document.getElementById(`subtotal_value_${index}`).value = subtotal.toFixed(2);
            
            calculateTotal();
        }

        function calculateTotal() {
            let total = 0;
            document.querySelectorAll('.subtotal-display').forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            document.getElementById('totalAmount').textContent = total.toFixed(2);
        }

        function savePurchase() {
            const form = document.getElementById('purchaseForm');
            const formData = new FormData(form);
            
            fetch('api/purchases.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Compra registrada exitosamente');
                    purchaseModal.hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('danger', data.message || 'Error al registrar la compra');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'Error de conexión al servidor');
            });
        }

        function viewPurchase(id) {
            window.location.href = `purchase-detail.php?id=${id}`;
        }

        function cancelPurchase(id) {
            if (!confirm('¿Está seguro de cancelar esta compra? Esta acción no se puede deshacer.')) return;
            
            fetch(`api/purchases.php?id=${id}&action=cancel`, {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('success', 'Compra cancelada');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert('danger', data.message || 'Error al cancelar');
                }
            });
        }

        function exportPurchases() {
            const params = new URLSearchParams(window.location.search);
            params.set('export', '1');
            window.location.href = 'api/purchases.php?' + params.toString();
        }

        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => alertDiv.remove(), 3000);
        }
    </script>
</body>
</html>