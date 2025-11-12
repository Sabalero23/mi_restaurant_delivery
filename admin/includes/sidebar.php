<?php
// admin/includes/sidebar.php - Componente sidebar reutilizable
// Este archivo requiere que las siguientes variables estén definidas:
// $restaurant_name, $user_name, $role, $auth, $stats, $online_stats

// Determinar la página actual para marcar el enlace activo
$current_page = basename($_SERVER['PHP_SELF']);

// Obtener productos con stock bajo para el badge
$low_stock_count = 0;
if ($auth->hasPermission('products') || $auth->hasPermission('kardex')) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $query_low = "SELECT COUNT(*) as count FROM products 
                     WHERE track_inventory = 1 
                     AND is_active = 1 
                     AND stock_quantity <= low_stock_alert";
        $stmt_low = $db->prepare($query_low);
        $stmt_low->execute();
        $result_low = $stmt_low->fetch(PDO::FETCH_ASSOC);
        $low_stock_count = $result_low['count'];
    } catch (Exception $e) {
        // Silenciar errores de conexión
    }
}
?>

<!-- Mobile Top Bar -->
<div class="mobile-topbar">
    <div class="d-flex justify-content-between align-items-center w-100">
        <div class="d-flex align-items-center">
            <button class="menu-toggle me-3" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
            <h5>
                <i class="fas fa-utensils me-2"></i>
                <?php echo $restaurant_name; ?>
            </h5>
        </div>
        <div class="d-flex align-items-center">
            <small class="me-3 d-none d-sm-inline">
                <i class="fas fa-user me-1"></i>
                <?php echo explode(' ', $user_name)[0]; ?>
            </small>
            <small>
                <i class="fas fa-clock me-1"></i>
                <?php echo date('H:i'); ?>
            </small>
        </div>
    </div>
</div>

<!-- Sidebar Backdrop -->
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
    <button class="sidebar-close" id="sidebarClose">
        <i class="fas fa-times"></i>
    </button>

    <div class="text-center mb-4">
        <h4>
            <i class="fas fa-utensils me-2"></i>
            <?php echo $restaurant_name; ?>
        </h4>
        <small>Sistema de Gestión</small>
    </div>

    <nav class="nav flex-column">
        <a class="nav-link fw-bold <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
            <i class="fas fa-tachometer-alt me-2"></i>
            Dashboard
        </a>

        <?php if ($auth->hasPermission('orders')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'orders.php') ? 'active' : ''; ?>" href="orders.php">
                <i class="fas fa-receipt me-2"></i>
                Órdenes
                <?php if (isset($stats['pending_orders']) && $stats['pending_orders'] > 0): ?>
                    <span class="badge bg-danger ms-auto pulsing-badge"><?php echo $stats['pending_orders']; ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        
        <?php if ($auth->hasPermission('online_orders')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'online-orders.php') ? 'active' : ''; ?>" href="online-orders.php">
                <i class="fas fa-globe me-2"></i>
                Órdenes Online
                <?php if (isset($online_stats['pending_online']) && $online_stats['pending_online'] > 0): ?>
                    <span class="badge bg-warning ms-auto pulsing-badge" id="online-orders-count">
                        <?php echo $online_stats['pending_online']; ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endif; ?>
        
        <?php if ($auth->hasPermission('whatsapp') || $auth->hasPermission('all') || $auth->hasPermission('online_orders')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'whatsapp-messages.php') ? 'active' : ''; ?>" href="whatsapp-messages.php">
                <i class="fab fa-whatsapp me-2"></i>
                WhatsApp
            </a>
        <?php endif; ?>

        <?php if ($auth->hasPermission('tables')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'tables.php') ? 'active' : ''; ?>" href="tables.php">
                <i class="fas fa-table me-2"></i>
                Mesas
            </a>
        <?php endif; ?>

        <?php if ($auth->hasPermission('kitchen')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'kitchen.php') ? 'active' : ''; ?>" href="kitchen.php">
                <i class="fas fa-fire me-2"></i>
                Cocina
                <?php if (isset($stats['preparing_orders']) && $stats['preparing_orders'] > 0): ?>
                    <span class="badge bg-warning ms-auto"><?php echo $stats['preparing_orders']; ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($auth->hasPermission('delivery')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'delivery.php') ? 'active' : ''; ?>" href="delivery.php">
                <i class="fas fa-motorcycle me-2"></i>
                Delivery
                <?php if (isset($stats['pending_deliveries']) && $stats['pending_deliveries'] > 0): ?>
                    <span class="badge bg-info ms-auto"><?php echo $stats['pending_deliveries']; ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($auth->hasPermission('products')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'products.php') ? 'active' : ''; ?>" href="products.php">
                <i class="fas fa-box me-2"></i>
                Productos
            </a>
        <?php endif; ?>

        <?php if ($auth->hasPermission('kardex') || $auth->hasPermission('products')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'kardex.php') ? 'active' : ''; ?>" href="kardex.php">
                <i class="fas fa-boxes me-2"></i>
                Kardex
                <?php if ($low_stock_count > 0): ?>
                    <span class="badge bg-danger ms-auto pulsing-badge" title="Productos con stock bajo">
                        <?php echo $low_stock_count; ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <?php if ($auth->hasPermission('users')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'users.php') ? 'active' : ''; ?>" href="users.php">
                <i class="fas fa-users me-2"></i>
                Usuarios
            </a>
        <?php endif; ?>

        <?php if ($auth->hasPermission('reports')): ?>
            <a class="nav-link fw-bold <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                <i class="fas fa-chart-bar me-2"></i>
                Reportes
            </a>
        <?php endif; ?>

        <?php if ($auth->hasPermission('all')): ?>
            <hr class="text-white-50 my-3">
            <small class="text-white-50 px-3 mb-2 d-block fw-bold">CONFIGURACIÓN</small>

            <a class="nav-link fw-bold <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>" href="settings.php">
                <i class="fas fa-cog me-2"></i>
                Configuración
            </a>

            <a class="nav-link fw-bold <?php echo ($current_page == 'whatsapp-settings.php') ? 'active' : ''; ?>" href="whatsapp-settings.php">
                <i class="fab fa-whatsapp me-2"></i>
                WhatsApp API
            </a>

            <a class="nav-link fw-bold <?php echo ($current_page == 'whatsapp-answers.php') ? 'active' : ''; ?>" href="whatsapp-answers.php">
                <i class="fas fa-robot me-2"></i>
                Respuestas Auto
            </a>

            <a class="nav-link fw-bold <?php echo ($current_page == 'permissions.php') ? 'active' : ''; ?>" href="permissions.php">
                <i class="fas fa-shield-alt me-2"></i>
                Permisos
            </a>
            
            <a class="nav-link fw-bold <?php echo ($current_page == 'theme-settings.php') ? 'active' : ''; ?>" href="theme-settings.php">
                <i class="fas fa-palette me-2"></i>
                Tema
            </a>
        <?php endif; ?>
        
        <hr class="text-white-50 my-3">
        <a class="nav-link fw-bold" href="logout.php">
            <i class="fas fa-sign-out-alt me-2"></i>
            Cerrar Sesión
        </a>
    </nav>
</div>

<script>
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
</script>