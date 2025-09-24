<?php
// admin/kitchen.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Order.php';

$auth = new Auth();
$auth->requirePermission('kitchen');

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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cocina - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Tema dinámico -->
<?php if (file_exists('../assets/css/generate-theme.php')): ?>
    <link rel="stylesheet" href="../assets/css/generate-theme.php?v=<?php echo time(); ?>">
<?php endif; ?>

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

/* Kitchen specific styles con colores fijos claros */
.order-card {
    border: none;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    margin-bottom: 20px;
    transition: transform var(--transition-base);
    background: #ffffff !important;
    color: #212529 !important;
}

.order-card:hover {
    transform: translateY(-3px);
}

.order-pending {
    border-left: 5px solid var(--warning-color);
}

.order-preparing {
    border-left: 5px solid var(--accent-color);
}

.order-priority {
    border-left: 5px solid var(--danger-color);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
}

.item-card {
    background: #ffffff !important;
    color: #212529 !important;
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius-base);
    padding: 12px;
    margin-bottom: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.item-ready {
    background: #d4edda !important;
    border-color: var(--success-color);
    color: #212529 !important;
}

.item-preparing {
    background: #fff3cd !important;
    border-color: var(--warning-color);
    color: #212529 !important;
}

.time-indicator {
    font-size: 0.9rem;
    padding: 4px 8px;
    border-radius: 12px;
    font-weight: bold;
}

.time-normal {
    background: #e3f2fd;
    color: #1976d2;
}

.time-warning {
    background: #fff3e0;
    color: #f57c00;
}

.time-critical {
    background: #ffebee;
    color: #d32f2f;
    animation: blink 1s infinite;
}

@keyframes blink {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0.5; }
}

.stats-card {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    padding: 20px;
    text-align: center;
    box-shadow: var(--shadow-base);
}

.stats-icon {
    font-size: 2.5rem;
    margin-bottom: 10px;
}

.page-header {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-base);
}

.card {
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
    background: #ffffff !important;
    color: #212529 !important;
}

.card-header {
    background: #f8f9fa !important;
    color: #212529 !important;
    border-bottom: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
}

.card-body {
    background: #ffffff !important;
    color: #212529 !important;
    padding: 1.5rem;
}

.order-number {
    font-size: 1.2rem;
    font-weight: bold;
    color: var(--primary-color) !important;
}

.table-info {
    background: #f8f9fa !important;
    color: #212529 !important;
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.85rem;
    font-weight: 500;
}

.preparation-time {
    background: #e3f2fd;
    color: #1976d2;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 0.8rem;
    font-weight: 500;
}

/* Botones usando variables del tema */
.btn-success {
    background: var(--success-color) !important;
    border-color: var(--success-color) !important;
    color: #ffffff !important;
}

.btn-warning {
    background: var(--warning-color) !important;
    border-color: var(--warning-color) !important;
    color: #212529 !important;
}

.btn-danger {
    background: var(--danger-color) !important;
    border-color: var(--danger-color) !important;
    color: #ffffff !important;
}

.btn-primary {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: #ffffff !important;
}

/* Badges usando variables del tema */
.badge.bg-success {
    background: var(--success-color) !important;
    color: #ffffff !important;
}

.badge.bg-warning {
    background: var(--warning-color) !important;
    color: #212529 !important;
}

.badge.bg-danger {
    background: var(--danger-color) !important;
    color: #ffffff !important;
}

.badge.bg-info {
    background: var(--info-color) !important;
    color: #ffffff !important;
}

/* Text colors forzados a claros */
h1, h2, h3, h4, h5, h6 {
    color: #212529 !important;
}

p, span, div {
    color: #212529 !important;
}

.text-muted {
    color: #6c757d !important;
}

/* Mobile responsive styles */
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

    .stats-card {
        padding: 1rem;
        margin-bottom: 1rem;
    }

    .stats-icon {
        width: 50px;
        height: 50px;
        font-size: 1.2rem;
    }

    .page-header {
        padding: 1rem;
    }

    .page-header h2 {
        font-size: 1.5rem;
    }

    .order-card {
        margin-bottom: 1rem;
    }

    .item-card {
        padding: 8px;
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
    }

    .item-card .d-flex {
        width: 100%;
        justify-content: space-between;
        align-items: center;
    }
}

@media (max-width: 576px) {
    .main-content {
        padding: 0.5rem;
        padding-top: 4.5rem;
    }

    .stats-card {
        padding: 0.75rem;
    }

    .page-header {
        padding: 0.75rem;
    }

    .page-header .d-flex {
        flex-direction: column;
        text-align: center;
    }

    .order-card {
        margin-bottom: 1rem;
    }

    .order-number {
        font-size: 1rem;
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
                    <h2 class="mb-0">Cocina</h2>
                    <p class="text-muted mb-0">Gestión de órdenes de cocina</p>
                </div>
                <div class="text-muted d-none d-lg-block">
                    <i class="fas fa-clock me-1"></i>
                    <span id="current-time-desktop"></span>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row g-3 g-md-4 mb-4">
            <div class="col-6 col-md-6 col-xl-3">
                <div class="stats-card">
                    <div class="stats-icon text-warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="mb-0" id="pending-count">0</h3>
                    <p class="text-muted mb-0 small">Pendientes</p>
                </div>
            </div>
            <div class="col-6 col-md-6 col-xl-3">
                <div class="stats-card">
                    <div class="stats-icon text-info">
                        <i class="fas fa-fire"></i>
                    </div>
                    <h3 class="mb-0" id="preparing-count">0</h3>
                    <p class="text-muted mb-0 small">En Preparación</p>
                </div>
            </div>
            <div class="col-6 col-md-6 col-xl-3">
                <div class="stats-card">
                    <div class="stats-icon text-success">
                        <i class="fas fa-check"></i>
                    </div>
                    <h3 class="mb-0" id="ready-count">0</h3>
                    <p class="text-muted mb-0 small">Listos</p>
                </div>
            </div>
            <div class="col-6 col-md-6 col-xl-3">
                <div class="stats-card">
                    <div class="stats-icon text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h3 class="mb-0" id="priority-count">0</h3>
                    <p class="text-muted mb-0 small">Prioritarios</p>
                </div>
            </div>
        </div>

        <!-- Orders Grid -->
        <div class="row" id="orders-container">
            <div class="col-12 text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <p class="mt-2 text-muted">Cargando órdenes...</p>
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div class="modal fade" id="orderDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="orderDetailTitle">
                        <i class="fas fa-receipt me-2"></i>
                        Detalle de Orden
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailBody">
                    <!-- Order details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
// Mobile menu functionality
document.addEventListener('DOMContentLoaded', function() {
    initializeMobileMenu();
    updateCurrentTime();
    loadKitchenOrders();
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

function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('es-AR', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    const mobileTime = document.getElementById('current-time');
    const desktopTime = document.getElementById('current-time-desktop');
    
    if (mobileTime) mobileTime.textContent = timeString;
    if (desktopTime) desktopTime.textContent = now.toLocaleString('es-AR');
}

function loadKitchenOrders() {
    fetch('api/kitchen.php')
        .then(response => response.json())
        .then(orders => {
            displayOrders(orders);
            updateStatistics(orders);
        })
        .catch(error => {
            console.error('Error loading kitchen orders:', error);
            document.getElementById('orders-container').innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Error al cargar las órdenes. 
                        <button class="btn btn-sm btn-outline-danger ms-2" onclick="loadKitchenOrders()">
                            Reintentar
                        </button>
                    </div>
                </div>
            `;
        });
}

function displayOrders(orders) {
    const container = document.getElementById('orders-container');
    
    if (orders.length === 0) {
        container.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info text-center">
                    <i class="fas fa-coffee fa-3x mb-3 d-block"></i>
                    <h5>¡Todo listo!</h5>
                    <p class="mb-0">No hay órdenes pendientes en cocina</p>
                </div>
            </div>
        `;
        return;
    }
    
    // Sort orders by priority and time
    orders.sort((a, b) => {
        const timeA = new Date(a.created_at).getTime();
        const timeB = new Date(b.created_at).getTime();
        const priorityA = isPriorityOrder(a);
        const priorityB = isPriorityOrder(b);
        
        if (priorityA !== priorityB) {
            return priorityB - priorityA; // Priority orders first
        }
        
        return timeA - timeB; // Older orders first
    });
    
    container.innerHTML = orders.map(order => createOrderCard(order)).join('');
}

function createOrderCard(order) {
    const orderTime = new Date(order.created_at);
    const currentTime = new Date();
    const elapsedMinutes = Math.floor((currentTime - orderTime) / (1000 * 60));
    
    const isPriority = isPriorityOrder(order);
    const timeClass = getTimeClass(elapsedMinutes);
    const isOnline = order.order_type === 'online';
    
    return `
        <div class="col-lg-4 col-md-6 mb-3">
            <div class="card order-card ${isPriority ? 'order-priority' : (order.status === 'preparing' ? 'order-preparing' : 'order-pending')}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="order-number">
                            ${isOnline ? '<i class="fas fa-globe me-1 text-success"></i>' : ''}
                            #${order.order_number}
                        </div>
                        ${getOrderLocationInfo(order)}
                    </div>
                    <div class="text-end">
                        <div class="time-indicator ${timeClass}">
                            <i class="fas fa-clock me-1"></i>
                            ${elapsedMinutes}min
                        </div>
                        ${isPriority ? '<div class="badge bg-danger mt-1">PRIORITARIO</div>' : ''}
                        ${isOnline ? '<div class="badge bg-success mt-1">ONLINE</div>' : ''}
                    </div>
                </div>
                <div class="card-body">
                    ${order.items.map(item => createItemCard(item, order)).join('')}
                    
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            ${formatDateTime(order.created_at)}
                        </small>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-1" onclick="viewOrderDetail('${order.id}', '${order.order_type}')">
                                <i class="fas fa-eye"></i>
                            </button>
                            ${getOrderActionButtons(order)}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
}

function getOrderLocationInfo(order) {
    if (order.order_type === 'online') {
        return `<div class="table-info">🚚 ${order.customer_info ? order.customer_info.name : order.customer_name}</div>`;
    } else if (order.table_number) {
        return `<div class="table-info">Mesa ${order.table_number}</div>`;
    } else if (order.customer_name) {
        return `<div class="table-info">${order.customer_name}</div>`;
    }
    return '';
}

function createItemCard(item, order) {
    const isOnline = order.order_type === 'online';
    const isItemReady = item.status === 'ready';
    const isItemPreparing = item.status === 'preparing';
    
    return `
        <div class="item-card ${isItemReady ? 'item-ready' : (isItemPreparing ? 'item-preparing' : '')}">
            <div class="flex-grow-1">
                <div class="fw-bold">${item.quantity}x ${item.product_name}</div>
                ${item.notes ? `<small class="text-muted">${item.notes}</small>` : ''}
            </div>
            <div class="d-flex align-items-center">
                ${item.preparation_time ? `<span class="preparation-time me-2">${item.preparation_time}min</span>` : ''}
                ${getItemActionButtons(item, order)}
            </div>
        </div>
    `;
}

function getItemActionButtons(item, order) {
    const isOnline = order.order_type === 'online';
    
    if (item.status === 'ready') {
        return `
            <span class="badge bg-success">
                <i class="fas fa-check me-1"></i>Listo
            </span>
        `;
    }
    
    if (isOnline) {
        // Para pedidos online, manejamos toda la orden como una unidad
        return `
            <button class="btn btn-sm btn-success me-1" onclick="markOnlineOrderReady('${order.id}')" title="Marcar pedido como listo">
                <i class="fas fa-check"></i>
            </button>
        `;
    } else {
        // Para pedidos tradicionales, manejamos items individuales
        return `
            <button class="btn btn-sm btn-success me-1" onclick="markItemReady('${item.id}', '${order.id}')" title="Marcar item como listo">
                <i class="fas fa-check"></i>
            </button>
        `;
    }
}

function getOrderActionButtons(order) {
    const isOnline = order.order_type === 'online';
    
    if (isOnline) {
        if (order.status === 'accepted') {
            return `
                <button class="btn btn-sm btn-warning" onclick="markOnlineOrderPreparing('${order.id}')">
                    <i class="fas fa-fire me-1"></i>
                    En Preparación
                </button>
            `;
        } else if (order.status === 'preparing') {
            return `
                <button class="btn btn-sm btn-success" onclick="markOnlineOrderReady('${order.id}')">
                    <i class="fas fa-check me-1"></i>
                    Completar
                </button>
            `;
        }
    } else {
        if (order.status === 'confirmed') {
            return `
                <button class="btn btn-sm btn-warning" onclick="markOrderPreparing('${order.id}')">
                    <i class="fas fa-fire me-1"></i>
                    En Preparación
                </button>
            `;
        } else if (order.status === 'preparing') {
            return `
                <button class="btn btn-sm btn-success" onclick="markOrderReady('${order.id}')">
                    <i class="fas fa-check me-1"></i>
                    Completar
                </button>
            `;
        }
    }
    
    return '';
}

function updateStatistics(orders) {
    let pending = 0;
    let preparing = 0;
    let ready = 0;
    let priority = 0;
    
    orders.forEach(order => {
        if (order.order_type === 'online') {
            if (order.status === 'accepted') pending++;
            if (order.status === 'preparing') preparing++;
            if (order.status === 'ready') ready++;
        } else {
            if (order.status === 'confirmed') pending++;
            if (order.status === 'preparing') preparing++;
            if (order.status === 'ready') ready++;
        }
        
        if (isPriorityOrder(order)) priority++;
    });
    
    document.getElementById('pending-count').textContent = pending;
    document.getElementById('preparing-count').textContent = preparing;
    document.getElementById('ready-count').textContent = ready;
    document.getElementById('priority-count').textContent = priority;
}

// FUNCIONES CORREGIDAS PARA MANEJAR ESTADOS

// Funciones para manejar pedidos online
function markOnlineOrderPreparing(orderId) {
    updateOrderStatus(orderId, 'preparing', 'online');
}

function markOnlineOrderReady(orderId) {
    if (confirm('¿Confirmar que el pedido online está listo para entrega?')) {
        updateOrderStatus(orderId, 'ready', 'online');
    }
}

// Funciones para manejar órdenes tradicionales
function markOrderPreparing(orderId) {
    updateOrderStatus(orderId, 'preparing', 'traditional');
}

function markOrderReady(orderId) {
    if (confirm('¿Confirmar que la orden está lista para servir/entregar?')) {
        updateOrderStatus(orderId, 'ready', 'traditional');
    }
}

// Función para manejar items individuales
function markItemReady(itemId, orderId) {
    const requestData = { 
        action: 'ready',
        item_id: itemId, 
        order_id: orderId,
        order_type: 'traditional'
    };
    
    console.log('Marking item ready:', requestData);
    
    fetch('api/kitchen.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response:', data);
        if (data.success) {
            showSuccessMessage(data.message || 'Item marcado como listo');
            loadKitchenOrders();
            playNotificationSound();
        } else {
            showErrorMessage('Error al actualizar el estado del item: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error de conexión');
    });
}

// Función unificada para actualizar estados de órdenes
function updateOrderStatus(orderId, status, orderType = 'traditional') {
    const requestData = { 
        action: status,
        order_id: orderId, 
        order_type: orderType
    };
    
    console.log('Updating order status:', requestData);
    
    fetch('api/kitchen.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response:', data);
        if (data.success) {
            const message = data.message || `Orden marcada como ${status === 'preparing' ? 'en preparación' : 'lista'}`;
            showSuccessMessage(message);
            loadKitchenOrders();
            if (status === 'ready') {
                playCompletionSound();
            } else {
                playNotificationSound();
            }
        } else {
            showErrorMessage('Error al actualizar el estado: ' + (data.message || 'Error desconocido'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showErrorMessage('Error de conexión');
    });
}

// FUNCIONES PARA MOSTRAR MENSAJES
function showSuccessMessage(message) {
    const alertHtml = `
        <div class="alert alert-success alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-dismiss after 3 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 3000);
}

function showErrorMessage(message) {
    const alertHtml = `
        <div class="alert alert-danger alert-dismissible fade show position-fixed" 
             style="top: 20px; right: 20px; z-index: 9999; min-width: 300px;" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', alertHtml);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = document.querySelector('.alert-danger');
        if (alert) {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }
    }, 5000);
}

function viewOrderDetail(orderId, orderType) {
    // Implementar vista detallada de la orden
    alert(`Ver detalles de la orden ${orderId} (${orderType})`);
}

// Helper functions
function isPriorityOrder(order) {
    const orderTime = new Date(order.created_at);
    const currentTime = new Date();
    const elapsedMinutes = Math.floor((currentTime - orderTime) / (1000 * 60));
    return elapsedMinutes > 30 || order.type === 'delivery' || order.order_type === 'online';
}

function getTimeClass(minutes) {
    if (minutes > 45) return 'time-critical';
    if (minutes > 30) return 'time-warning';
    return 'time-normal';
}

function allItemsReady(items) {
    return items.every(item => item.status === 'ready');
}

function formatDateTime(datetime) {
    return new Date(datetime).toLocaleString('es-AR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function playNotificationSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 800;
        oscillator.type = 'sine';
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.1);
    } catch (e) {
        console.log('Audio not supported');
    }
}

function playCompletionSound() {
    try {
        const audioContext = new (window.AudioContext || window.webkitAudioContext)();
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        oscillator.frequency.value = 600;
        oscillator.type = 'sine';
        gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.3);
        
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.3);
    } catch (e) {
        console.log('Audio not supported');
    }
}

// Auto-refresh and update time
setInterval(loadKitchenOrders, 15000); // Refresh every 15 seconds
setInterval(updateCurrentTime, 1000);  // Update time every second
    </script>

<?php include 'footer.php'; ?>
</body>
</html>