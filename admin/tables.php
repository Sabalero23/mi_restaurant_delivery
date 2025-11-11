<?php
// admin/tables.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';
require_once '../models/Table.php';
require_once '../models/Order.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('tables');

$tableModel = new Table();
$orderModel = new Order();

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

// Get database connection for waiter queries
$database = new Database();
$db = $database->getConnection();

// Get list of waiters for assignment
$waiters_query = "SELECT id, full_name, username FROM users 
                  WHERE role_id IN (SELECT id FROM roles WHERE name IN ('mesero', 'administrador', 'gerente', 'mostrador'))
                  AND is_active = 1
                  ORDER BY full_name";
$waiters_stmt = $db->prepare($waiters_query);
$waiters_stmt->execute();
$waiters = $waiters_stmt->fetchAll();

// Handle form submissions
$message = '';
$error = '';

if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'create_table':
            $waiter_id = isset($_POST['waiter_id']) && $_POST['waiter_id'] !== '' ? intval($_POST['waiter_id']) : null;
            
            // Use direct query to include waiter_id
            $query = "INSERT INTO tables (number, capacity, location, status, waiter_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([
                sanitize($_POST['number']),
                intval($_POST['capacity']),
                sanitize($_POST['location']),
                'available',
                $waiter_id
            ])) {
                $message = 'Mesa creada exitosamente';
            } else {
                $error = 'Error al crear la mesa';
            }
            break;
            
        case 'update_table':
            $id = intval($_POST['id']);
            $waiter_id = isset($_POST['waiter_id']) && $_POST['waiter_id'] !== '' ? intval($_POST['waiter_id']) : null;
            
            // Use direct query to include waiter_id
            $query = "UPDATE tables SET number = ?, capacity = ?, location = ?, status = ?, waiter_id = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([
                sanitize($_POST['number']),
                intval($_POST['capacity']),
                sanitize($_POST['location']),
                $_POST['status'],
                $waiter_id,
                $id
            ])) {
                $message = 'Mesa actualizada exitosamente';
            } else {
                $error = 'Error al actualizar la mesa';
            }
            break;
            
        case 'delete_table':
            $id = intval($_POST['id']);
            if ($tableModel->delete($id)) {
                $message = 'Mesa eliminada exitosamente';
            } else {
                $error = 'No se puede eliminar la mesa (tiene órdenes activas)';
            }
            break;
            
        case 'change_status':
            $id = intval($_POST['table_id']);
            $status = $_POST['new_status'];
            
            if ($tableModel->updateStatus($id, $status)) {
                $message = 'Estado de mesa actualizado';
            } else {
                $error = 'Error al cambiar el estado de la mesa';
            }
            break;
    }
}

// Get tables with waiter information
$tables_query = "SELECT t.*, u.full_name as waiter_name, u.username as waiter_username
                 FROM tables t
                 LEFT JOIN users u ON t.waiter_id = u.id
                 ORDER BY t.number";
$tables_stmt = $db->prepare($tables_query);
$tables_stmt->execute();
$tables = $tables_stmt->fetchAll();

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Mesas - <?php echo $restaurant_name; ?></title>
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
/* Extensiones específicas del dashboard */
:root {
    --primary-gradient: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    --dashboard-sidebar-width: <?php echo $current_theme['sidebar_width'] ?? '280px'; ?>;
    --sidebar-mobile-width: 100%;
}

/* Main content - FORZAR COLORES CLAROS */
.main-content {
    margin-left: var(--online-sidebar-width);
    padding: 2rem;
    min-height: 100vh;
    transition: margin-left var(--transition-base, 0.3s ease-in-out);
    background: #f8f9fa !important;
    color: #212529 !important;
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
    width: var(--dashboard-sidebar-width);
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

/* Main content */
.main-content {
    margin-left: var(--dashboard-sidebar-width);
    padding: 2rem;
    min-height: 100vh;
    transition: margin-left var(--transition-base);
    background: #f8f9fa !important;
    color: #212529 !important;
}

.card {
    background: #ffffff !important;
    color: #212529 !important;
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
}

.card-header {
    background: #f8f9fa !important;
    color: #212529 !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    border-radius: var(--border-radius-large) var(--border-radius-large) 0 0 !important;
    padding: 1rem 1.5rem;
}

.card-body {
    background: #ffffff !important;
    color: #212529 !important;
    padding: 1.5rem;
}

/* Statistics cards usando variables del tema */
.stat-card {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    padding: 1.5rem;
    box-shadow: var(--shadow-base);
    transition: transform var(--transition-base);
    height: 100%;
}


.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: var(--border-radius-large);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: var(--text-white) !important;
    flex-shrink: 0;
}

.bg-primary-gradient { 
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color)) !important; 
}
.bg-success-gradient { 
    background: linear-gradient(45deg, var(--success-color), #a8e6cf) !important; 
}
.bg-warning-gradient { 
    background: linear-gradient(45deg, var(--warning-color), var(--accent-color)) !important; 
}
.bg-info-gradient { 
    background: linear-gradient(45deg, var(--info-color), #00f2fe) !important; 
}
.bg-online-gradient { 
    background: linear-gradient(45deg, var(--accent-color), var(--warning-color)) !important; 
}

.page-header {
    background: #ffffff !important;
    color: #212529 !important;
    border-radius: var(--border-radius-large);
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--shadow-base);
}


/* Notification styles */
.notification-alert {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
    animation: slideInRight 0.5s ease-out;
    box-shadow: var(--shadow-large);
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.notification-shake {
    animation: shake 0.5s ease-in-out;
}

.pulsing-badge {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.1); opacity: 0.7; }
    100% { transform: scale(1); opacity: 1; }
}

.urgent-notification {
    border: 3px solid var(--danger-color) !important;
    background: linear-gradient(45deg, var(--danger-color), var(--warning-color)) !important;
    color: var(--text-white) !important;
}
        /* Table cards with visual icons */
        .table-card {
            border: 2px solid;
            border-radius: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            min-height: 180px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        
        .table-available {
            border-color: #28a745;
            background: #28a745;
            color: white;
        }
        
        .table-occupied {
            border-color: #dc3545;
            background: #dc3545;
            color: white;
        }
        
        .table-reserved {
            border-color: #ffc107;
            background: #ffc107;
            color: #212529;
        }
        
        .table-maintenance {
            border-color: #6c757d;
            background: #6c757d;
            color: white;
        }
        
        .table-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.2);
        }

        /* Table visual representation */
        .table-visual {
            position: relative;
            width: 80px;
            height: 80px;
            margin-bottom: 10px;
        }

        .table-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }

        /* Table (circle in center) */
        .table-surface {
            width: 35px;
            height: 35px;
            background: rgba(255, 255, 255, 0.3);
            border: 3px solid rgba(255, 255, 255, 0.8);
            border-radius: 50%;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 2;
        }

        /* Chairs around table */
        .chair {
            width: 12px;
            height: 8px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 2px;
            position: absolute;
        }

        .chair-top { top: 5px; left: 50%; transform: translateX(-50%); }
        .chair-right { top: 50%; right: 5px; transform: translateY(-50%) rotate(90deg); }
        .chair-bottom { bottom: 5px; left: 50%; transform: translateX(-50%) rotate(180deg); }
        .chair-left { top: 50%; left: 5px; transform: translateY(-50%) rotate(270deg); }

        /* Additional chairs for larger capacity */
        .chair-top-left { top: 15px; left: 15px; transform: rotate(-45deg); }
        .chair-top-right { top: 15px; right: 15px; transform: rotate(45deg); }
        .chair-bottom-left { bottom: 15px; left: 15px; transform: rotate(-135deg); }
        .chair-bottom-right { bottom: 15px; right: 15px; transform: rotate(135deg); }
        
        .table-number {
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
            margin-bottom: 5px;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }

        .table-capacity {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 8px;
        }
        
        .table-status-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            font-size: 0.7rem;
            padding: 2px 6px;
        }

        .table-actions {
            position: absolute;
            bottom: 8px;
            right: 8px;
            opacity: 0;
            transition: opacity 0.3s;
        }

        .table-card:hover .table-actions {
            opacity: 1;
        }

        /* Card improvements */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .card-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.125);
            border-radius: 15px 15px 0 0 !important;
            background: white !important;
            padding: 1rem 1.5rem;
        }

        .order-item {
            background: white;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            border-left: 4px solid #007bff;
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
                padding-top: 5rem; /* Space for mobile topbar */
            }

            .page-header {
                padding: 1rem;
            }

            .page-header h2 {
                font-size: 1.5rem;
            }

            .stat-card {
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .table-card {
                min-height: 120px;
            }

            .table-number {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                padding: 0.5rem;
                padding-top: 4.5rem;
            }

            .page-header {
                padding: 0.75rem;
            }

            .page-header .d-flex {
                flex-direction: column;
                text-align: center;
            }

            .stat-card {
                padding: 0.75rem;
            }

            .table-card {
                min-height: 100px;
            }

            .table-number {
                font-size: 1.2rem;
            }

            .sidebar {
                padding: 1rem;
            }

            .sidebar .nav-link {
                padding: 0.5rem 0.75rem;
            }
        }

        /* Improved scrollbar for sidebar */
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
                        <i class="fas fa-table me-2"></i>
                        Gestión de Mesas
                    </h2>
                    <p class="text-muted mb-0">Control de mesas y ocupación del restaurante</p>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#tableModal" onclick="newTable()">
                        <i class="fas fa-plus me-1"></i>
                        <span class="d-none d-sm-inline">Nueva Mesa</span>
                    </button>
                    <button class="btn btn-info" onclick="refreshTables()">
                        <i class="fas fa-sync"></i>
                        <span class="d-none d-sm-inline ms-1">Actualizar</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check me-2"></i>
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="row mb-4 g-3">
            <?php
            $stats = [
                'available' => count(array_filter($tables, fn($t) => $t['status'] === 'available')),
                'occupied' => count(array_filter($tables, fn($t) => $t['status'] === 'occupied')),
                'reserved' => count(array_filter($tables, fn($t) => $t['status'] === 'reserved')),
                'maintenance' => count(array_filter($tables, fn($t) => $t['status'] === 'maintenance'))
            ];
            ?>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-success"><?php echo $stats['available']; ?></div>
                    <p class="text-muted mb-0 small">Disponibles</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-danger"><?php echo $stats['occupied']; ?></div>
                    <p class="text-muted mb-0 small">Ocupadas</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-warning"><?php echo $stats['reserved']; ?></div>
                    <p class="text-muted mb-0 small">Reservadas</p>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-card">
                    <div class="stat-number text-secondary"><?php echo $stats['maintenance']; ?></div>
                    <p class="text-muted mb-0 small">Mantenimiento</p>
                </div>
            </div>
        </div>

        <!-- Tables Grid -->
        <div class="row g-3" id="tablesContainer">
            <?php foreach ($tables as $table): ?>
                <div class="col-sm-6 col-lg-4 col-xl-3 col-xxl-2">
                    <div class="table-card table-<?php echo $table['status']; ?>" 
                         onclick="selectTable(<?php echo $table['id']; ?>)">
                         
                        <span class="table-status-badge">
                            <?php
                            $status_badges = [
                                'available' => '<span class="badge bg-light text-dark">Libre</span>',
                                'occupied' => '<span class="badge bg-light text-dark">Ocupada</span>',
                                'reserved' => '<span class="badge bg-light text-dark">Reservada</span>',
                                'maintenance' => '<span class="badge bg-light text-dark">Mantto</span>'
                            ];
                            echo $status_badges[$table['status']];
                            ?>
                        </span>

                        <!-- Visual representation of table -->
                        <div class="table-visual">
                            <!-- Table surface -->
                            <div class="table-surface"></div>
                            
                            <!-- Chairs based on capacity -->
                            <?php 
                            $capacity = intval($table['capacity']);
                            if ($capacity >= 1): ?>
                                <div class="chair chair-top"></div>
                            <?php endif; ?>
                            
                            <?php if ($capacity >= 2): ?>
                                <div class="chair chair-right"></div>
                            <?php endif; ?>
                            
                            <?php if ($capacity >= 3): ?>
                                <div class="chair chair-bottom"></div>
                            <?php endif; ?>
                            
                            <?php if ($capacity >= 4): ?>
                                <div class="chair chair-left"></div>
                            <?php endif; ?>
                            
                            <?php if ($capacity >= 5): ?>
                                <div class="chair chair-top-left"></div>
                            <?php endif; ?>
                            
                            <?php if ($capacity >= 6): ?>
                                <div class="chair chair-top-right"></div>
                            <?php endif; ?>
                            
                            <?php if ($capacity >= 7): ?>
                                <div class="chair chair-bottom-left"></div>
                            <?php endif; ?>
                            
                            <?php if ($capacity >= 8): ?>
                                <div class="chair chair-bottom-right"></div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="table-number"><?php echo htmlspecialchars($table['number']); ?></div>
                        
                        <div class="table-capacity">
                            <?php echo $table['capacity']; ?> personas
                        </div>
                        
                        <?php if ($table['location']): ?>
                            <div class="table-location" style="font-size: 0.7rem; color: rgba(255, 255, 255, 0.8);">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($table['location']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($table['waiter_name']) && $table['waiter_name']): ?>
                            <div class="mt-1" style="font-size: 0.75rem; color: rgba(255, 255, 255, 0.9); font-weight: 500;">
                                <i class="fas fa-user-tie me-1"></i>
                                <?php echo htmlspecialchars($table['waiter_name']); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($table['active_orders']) && $table['active_orders'] > 0): ?>
                            <div class="mt-2" style="font-size: 0.7rem;">
                                <i class="fas fa-receipt me-1"></i>
                                <?php echo $table['active_orders']; ?> orden(es)
                            </div>
                        <?php endif; ?>
                        
                        <div class="table-actions">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-light" 
                                        onclick="event.stopPropagation(); editTable(<?php echo htmlspecialchars(json_encode($table)); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <button class="btn btn-sm btn-light dropdown-toggle dropdown-toggle-split" 
                                        type="button" data-bs-toggle="dropdown" 
                                        onclick="event.stopPropagation()">
                                    <span class="visually-hidden">Toggle Dropdown</span>
                                </button>
                                <ul class="dropdown-menu">
                                    <?php if ($table['status'] !== 'available'): ?>
                                        <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $table['id']; ?>, 'available')">
                                            <i class="fas fa-check text-success me-2"></i>Marcar libre
                                        </a></li>
                                    <?php endif; ?>
                                    
                                    <?php if ($table['status'] !== 'occupied'): ?>
                                        <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $table['id']; ?>, 'occupied')">
                                            <i class="fas fa-user text-danger me-2"></i>Marcar ocupada
                                        </a></li>
                                    <?php endif; ?>
                                    
                                    <?php if ($table['status'] !== 'reserved'): ?>
                                        <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $table['id']; ?>, 'reserved')">
                                            <i class="fas fa-calendar text-warning me-2"></i>Reservar
                                        </a></li>
                                    <?php endif; ?>
                                    
                                    <?php if ($table['status'] !== 'maintenance'): ?>
                                        <li><a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $table['id']; ?>, 'maintenance')">
                                            <i class="fas fa-wrench text-secondary me-2"></i>Mantenimiento
                                        </a></li>
                                    <?php endif; ?>
                                    
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="order-create.php?table_id=<?php echo $table['id']; ?>">
                                        <i class="fas fa-plus text-primary me-2"></i>Nueva orden
                                    </a></li>
                                    <li><a class="dropdown-item text-danger" href="#" onclick="deleteTable(<?php echo $table['id']; ?>, '<?php echo htmlspecialchars($table['number']); ?>')">
                                        <i class="fas fa-trash me-2"></i>Eliminar mesa
                                    </a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Table Details Modal -->
    <div class="modal fade" id="tableDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tableDetailsTitle">
                        <i class="fas fa-table me-2"></i>
                        Detalles de Mesa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="tableDetailsContent">
                    <!-- Content loaded via JavaScript -->
                </div>
            </div>
        </div>
    </div>
    
    <!-- Table Modal (Create/Edit) -->
    <div class="modal fade" id="tableModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="tableForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="tableModalTitle">
                            <i class="fas fa-plus me-2"></i>
                            Nueva Mesa
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="tableAction" value="create_table">
                        <input type="hidden" name="id" id="tableId">
                        
                        <div class="mb-3">
                            <label class="form-label">Número/Nombre de Mesa *</label>
                            <input type="text" class="form-control" name="number" id="tableNumber" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Capacidad *</label>
                            <input type="number" class="form-control" name="capacity" id="tableCapacity" min="1" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Ubicación</label>
                            <input type="text" class="form-control" name="location" id="tableLocation" placeholder="Ej: Salón principal, Terraza, etc.">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Mesero Asignado</label>
                            <select class="form-select" name="waiter_id" id="tableWaiter">
                                <option value="">Sin asignar</option>
                                <?php foreach ($waiters as $waiter): ?>
                                    <option value="<?php echo $waiter['id']; ?>">
                                        <?php echo htmlspecialchars($waiter['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Selecciona el mesero responsable de esta mesa</div>
                        </div>
                        
                        <div class="mb-3" id="statusField" style="display: none;">
                            <label class="form-label">Estado</label>
                            <select class="form-select" name="status" id="tableStatus">
                                <option value="available">Disponible</option>
                                <option value="occupied">Ocupada</option>
                                <option value="reserved">Reservada</option>
                                <option value="maintenance">Mantenimiento</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" id="tableSubmitBtn">
                            <i class="fas fa-save me-1"></i>
                            Guardar Mesa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<script>
    // Define all functions first before they are used
    function selectTable(tableId) {
        loadTableDetails(tableId);
    }
    
    function editTable(table) {
        document.getElementById('tableModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Mesa';
        document.getElementById('tableAction').value = 'update_table';
        document.getElementById('tableSubmitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Actualizar Mesa';
        
        document.getElementById('tableId').value = table.id;
        document.getElementById('tableNumber').value = table.number;
        document.getElementById('tableCapacity').value = table.capacity;
        document.getElementById('tableLocation').value = table.location || '';
        document.getElementById('tableStatus').value = table.status;
        document.getElementById('tableWaiter').value = table.waiter_id || '';
        document.getElementById('statusField').style.display = 'block';
        
        new bootstrap.Modal(document.getElementById('tableModal')).show();
    }

    function changeStatus(tableId, newStatus) {
        event.preventDefault();
        event.stopPropagation();
        
        if (confirm(`¿Confirmar cambio de estado?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="change_status">
                <input type="hidden" name="table_id" value="${tableId}">
                <input type="hidden" name="new_status" value="${newStatus}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function deleteTable(tableId, tableName) {
        event.preventDefault();
        event.stopPropagation();
        
        if (confirm(`¿Está seguro de eliminar la mesa "${tableName}"?`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_table">
                <input type="hidden" name="id" value="${tableId}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }

    function newTable() {
        document.getElementById('tableModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Nueva Mesa';
        document.getElementById('tableAction').value = 'create_table';
        document.getElementById('tableSubmitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Guardar Mesa';
        document.getElementById('tableForm').reset();
        document.getElementById('tableId').value = '';
        document.getElementById('statusField').style.display = 'none';
    }

    function loadTableDetails(tableId) {
    fetch(`api/tables.php?id=${tableId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(table => {
            if (table.error) {
                throw new Error(table.error);
            }
            
            document.getElementById('tableDetailsTitle').innerHTML = 
                `<i class="fas fa-table me-2"></i>${table.number || 'Mesa'} - Detalles`;
            
            const hasActiveOrders = table.active_orders && table.active_orders.length > 0;
            const isOccupied = table.status == 'occupied' || table.status == 'reserved';
            
            let content = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Información de la Mesa</h6>
                        <p><strong>Número:</strong> ${table.number || 'N/A'}</p>
                        <p><strong>Capacidad:</strong> ${table.capacity || 'N/A'} personas</p>
                        <p><strong>Ubicación:</strong> ${table.location || 'No especificada'}</p>
                        <p><strong>Estado:</strong> 
                            <span class="badge bg-${getStatusColor(table.status)}">
                                ${getStatusText(table.status)}
                            </span>
                        </p>
            `;
            
            if (hasActiveOrders) {
                content += `
                        <h6 class="mt-3">Órdenes Activas</h6>
                        <div class="list-group">
                `;
                table.active_orders.forEach(order => {
                    content += `
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Orden #${order.order_number}</strong>
                                        <br><small class="text-muted">${order.item_count} producto(s) - $${parseFloat(order.calculated_total).toFixed(2)}</small>
                                    </div>
                                    <span class="badge bg-${getOrderStatusColor(order.status)}">${getOrderStatusText(order.status)}</span>
                                </div>
                            </div>
                    `;
                });
                content += `
                        </div>
                `;
            }
            
            content += `
                    </div>
                    <div class="col-md-6">
                        <h6>Acciones Rápidas</h6>
                        <div class="d-grid gap-2">
            `;
            
            if (hasActiveOrders) {
                const firstOrder = table.active_orders[0];
                content += `
                            <a href="order-create.php?order_id=${firstOrder.id}&table_id=${table.id}" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit me-1"></i>Editar Orden
                            </a>
                `;
            } else {
                content += `
                            <a href="order-create.php?table_id=${table.id}" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-1"></i>Nueva Orden
                            </a>
                `;
            }
            
            if (hasActiveOrders) {
                content += `
                            <button class="btn btn-secondary btn-sm" disabled>
                                <i class="fas fa-lock me-1"></i>No se puede liberar (orden activa)
                            </button>
                `;
            } else {
                if (table.status == 'available') {
                    content += `
                            <button class="btn btn-info btn-sm" onclick="reserveTable(${table.id})">
                                <i class="fas fa-calendar-check me-1"></i>Reservar Mesa
                            </button>
                    `;
                } else {
                    content += `
                            <button class="btn btn-success btn-sm" onclick="freeTable(${table.id})">
                                <i class="fas fa-unlock me-1"></i>Liberar Mesa
                            </button>
                    `;
                }
            }
            
            content += `
                        </div>
                    </div>
                </div>
            `;
            
            document.getElementById('tableDetailsContent').innerHTML = content;
            new bootstrap.Modal(document.getElementById('tableDetailsModal')).show();
        })
        .catch(error => {
            console.error('Error loading table details:', error);
            alert('Error al cargar los detalles de la mesa: ' + error.message);
        });
}
    
    function createOrder(tableId) {
        window.location.href = `order-create.php?table_id=${tableId}`;
    }
    
    function viewOrder(orderId) {
        window.location.href = `order-details.php?id=${orderId}`;
    }
    
    function refreshTables() {
        location.reload();
    }

    // Mobile menu functionality - Initialize immediately
    document.addEventListener('DOMContentLoaded', function() {
        initializeMobileMenu();
        setupDropdownEvents();
    });

    function setupDropdownEvents() {
        // Asegurar que los enlaces del dropdown funcionen correctamente
        document.addEventListener('click', function(e) {
            // Si el click es en un enlace del dropdown, manejarlo
            if (e.target.closest('.dropdown-item')) {
                const item = e.target.closest('.dropdown-item');
                const onclick = item.getAttribute('onclick');
                
                if (onclick) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Evaluar la función onclick
                    try {
                        eval(onclick);
                    } catch (error) {
                        console.error('Error executing onclick:', error);
                    }
                }
            }
        });
    }

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
                    setTimeout(closeSidebar, 100); // Small delay to allow navigation
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
    
    function reserveTable(tableId) {
        if (!confirm('¿Deseas marcar esta mesa como RESERVADA/OCUPADA?')) {
            return;
        }
        
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=occupy_table&table_id=${tableId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Mesa marcada como RESERVADA');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'No se pudo reservar la mesa'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }

    function freeTable(tableId) {
        if (!confirm('¿Deseas LIBERAR esta mesa?')) {
            return;
        }
        
        fetch('ajax_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=free_table&table_id=${tableId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Mesa liberada exitosamente');
                location.reload();
            } else {
                alert('Error: ' + (data.error || 'No se pudo liberar la mesa'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al procesar la solicitud');
        });
    }
    
    // Helper functions
    function getStatusColor(status) {
        const colors = {
            'available': 'success',
            'occupied': 'danger',
            'reserved': 'warning',
            'maintenance': 'secondary'
        };
        return colors[status] || 'secondary';
    }
    
    function getStatusText(status) {
        const texts = {
            'available': 'Libre',
            'occupied': 'Ocupada',
            'reserved': 'Reservada',
            'maintenance': 'Mantenimiento'
        };
        return texts[status] || status;
    }
    
    function getOrderStatusColor(status) {
        const colors = {
            'pending': 'secondary',
            'confirmed': 'info',
            'preparing': 'warning',
            'ready': 'success',
            'delivered': 'primary'
        };
        return colors[status] || 'secondary';
    }
    
    function getOrderStatusText(status) {
        const texts = {
            'pending': 'Pendiente',
            'confirmed': 'Confirmado',
            'preparing': 'Preparando',
            'ready': 'Listo',
            'delivered': 'Entregado'
        };
        return texts[status] || status;
    }
    



    function formatPrice(price) {
        return '$' + parseFloat(price).toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }
    
    // Auto-refresh every 30 seconds
    setInterval(refreshTables, 30000);
    
    // Auto-dismiss alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Prevent zoom on double tap for better mobile UX
    let lastTouchEnd = 0;
    document.addEventListener('touchend', function (event) {
        const now = (new Date()).getTime();
        if (now - lastTouchEnd <= 300) {
            event.preventDefault();
        }
        lastTouchEnd = now;
    }, false);
</script>

<?php include 'footer.php'; ?>
</body>
</html>