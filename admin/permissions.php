<?php
// admin/permissions.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();

// Solo administradores pueden acceder
if ($_SESSION['role_name'] !== 'administrador') {
    header('Location: pages/403.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$message = '';
$error = '';

// Available permissions and their descriptions
$available_permissions = [
    'all' => 'Acceso completo al sistema',
    'orders' => 'Gestión de órdenes tradicionales',
    'online_orders' => 'Gestión de pedidos online', // NUEVO PERMISO
    'products' => 'Gestión de productos',
    'users' => 'Gestión de usuarios',
    'tables' => 'Gestión de mesas',
    'reports' => 'Reportes y estadísticas',
    'kitchen' => 'Panel de cocina',
    'delivery' => 'Gestión de delivery',
    'settings' => 'Configuración del sistema'
];

// Handle form submissions
if ($_POST && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'update_role_permissions':
            $result = updateRolePermissions();
            break;
        case 'update_user_permissions':
            $result = updateUserPermissions();
            break;
        case 'create_role':
            $result = createRole();
            break;
        case 'delete_role':
            $result = deleteRole();
            break;
    }
    
    if (isset($result['success']) && $result['success']) {
        $message = $result['message'];
    } else {
        $error = $result['message'] ?? 'Error desconocido';
    }
}

function updateRolePermissions() {
    global $db;
    
    $role_id = intval($_POST['role_id']);
    $permissions = $_POST['permissions'] ?? [];
    
    if (empty($role_id)) {
        return ['success' => false, 'message' => 'ID de rol requerido'];
    }
    
    try {
        $permissions_json = json_encode($permissions);
        
        $query = "UPDATE roles SET permissions = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$permissions_json, $role_id])) {
            return ['success' => true, 'message' => 'Permisos del rol actualizados exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar permisos del rol'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function updateUserPermissions() {
    global $db;
    
    $user_id = intval($_POST['user_id']);
    $new_role_id = intval($_POST['new_role_id']);
    
    if (empty($user_id) || empty($new_role_id)) {
        return ['success' => false, 'message' => 'Datos requeridos faltantes'];
    }
    
    // Prevent changing own role
    if ($user_id == $_SESSION['user_id']) {
        return ['success' => false, 'message' => 'No puedes cambiar tu propio rol'];
    }
    
    try {
        $query = "UPDATE users SET role_id = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$new_role_id, $user_id])) {
            return ['success' => true, 'message' => 'Rol del usuario actualizado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al actualizar rol del usuario'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function createRole() {
    global $db;
    
    $name = sanitize($_POST['role_name']);
    $description = sanitize($_POST['role_description']);
    $permissions = $_POST['role_permissions'] ?? [];
    
    if (empty($name)) {
        return ['success' => false, 'message' => 'El nombre del rol es requerido'];
    }
    
    try {
        // Check if role name already exists
        $check_query = "SELECT COUNT(*) FROM roles WHERE name = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$name]);
        
        if ($check_stmt->fetchColumn() > 0) {
            return ['success' => false, 'message' => 'El nombre del rol ya existe'];
        }
        
        $permissions_json = json_encode($permissions);
        
        $query = "INSERT INTO roles (name, description, permissions, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$name, $description, $permissions_json])) {
            return ['success' => true, 'message' => 'Rol creado exitosamente'];
        } else {
            return ['success' => false, 'message' => 'Error al crear el rol'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

function deleteRole() {
    global $db;
    
    $role_id = intval($_POST['role_id']);
    
    if (empty($role_id)) {
        return ['success' => false, 'message' => 'ID de rol requerido'];
    }
    
    // Check if role has users assigned
    $users_query = "SELECT COUNT(*) FROM users WHERE role_id = ?";
    $users_stmt = $db->prepare($users_query);
    $users_stmt->execute([$role_id]);
    
    if ($users_stmt->fetchColumn() > 0) {
        return ['success' => false, 'message' => 'No se puede eliminar un rol que tiene usuarios asignados'];
    }
    
    try {
        $query = "DELETE FROM roles WHERE id = ? AND name NOT IN ('administrador', 'gerente', 'mesero', 'cocina', 'delivery', 'mostrador')";
        $stmt = $db->prepare($query);
        
        if ($stmt->execute([$role_id])) {
            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'message' => 'Rol eliminado exitosamente'];
            } else {
                return ['success' => false, 'message' => 'No se puede eliminar un rol del sistema'];
            }
        } else {
            return ['success' => false, 'message' => 'Error al eliminar el rol'];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Load roles and users
$roles_query = "SELECT r.*, COUNT(u.id) as user_count 
                FROM roles r 
                LEFT JOIN users u ON r.id = u.role_id 
                GROUP BY r.id 
                ORDER BY r.name";
$roles_stmt = $db->prepare($roles_query);
$roles_stmt->execute();
$roles = $roles_stmt->fetchAll();

$users_query = "SELECT u.*, r.name as role_name, r.permissions 
                FROM users u 
                LEFT JOIN roles r ON u.role_id = r.id 
                WHERE u.is_active = 1 
                ORDER BY u.full_name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$users = $users_stmt->fetchAll();

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Permisos - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .content-area {
            background: #f8f9fa;
            min-height: 100vh;
        }
        
        .permissions-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .section-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 15px 15px 0 0;
        }
        
        .permission-check {
            margin: 0.25rem 0;
        }
        
        .role-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .role-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .user-card {
            border-left: 4px solid #28a745;
        }
        
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            border: none;
            color: #6c757d;
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.active {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border: none;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 15px 15px;
            padding: 2rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="col-lg-3 col-xl-2">
                <div class="sidebar text-white p-3">
                    <div class="text-center mb-4">
                        <h4>
                            <i class="fas fa-utensils me-2"></i>
                            <?php echo $restaurant_name; ?>
                        </h4>
                        <small>Permisos</small>
                    </div>
                    
                    <div class="mb-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-white bg-opacity-20 rounded-circle p-2 me-2">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div>
                                <div class="fw-bold"><?php echo $_SESSION['full_name']; ?></div>
                                <small class="opacity-75">Administrador</small>
                            </div>
                        </div>
                    </div>
                    
                    <nav class="nav flex-column">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            Dashboard
                        </a>
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-receipt me-2"></i>
                            Órdenes
                        </a>
                        <a class="nav-link" href="products.php">
                            <i class="fas fa-utensils me-2"></i>
                            Productos
                        </a>
                        <a class="nav-link" href="users.php">
                            <i class="fas fa-users me-2"></i>
                            Usuarios
                        </a>
                        <a class="nav-link" href="settings.php">
                            <i class="fas fa-cog me-2"></i>
                            Configuración
                        </a>
                        <a class="nav-link active" href="permissions.php">
                            <i class="fas fa-shield-alt me-2"></i>
                            Permisos
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i>
                            Cerrar Sesión
                        </a>
                    </nav>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-9 col-xl-10">
                <div class="content-area p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h2>
                                <i class="fas fa-shield-alt me-2"></i>
                                Gestión de Permisos
                            </h2>
                            <p class="text-muted mb-0">Administra roles y permisos del sistema</p>
                        </div>
                        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                            <i class="fas fa-plus me-2"></i>
                            Crear Rol
                        </button>
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
                    
                    <!-- Permissions Tabs -->
                    <div class="permissions-section">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button">
                                    <i class="fas fa-user-tag me-2"></i>Roles
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users-permissions" type="button">
                                    <i class="fas fa-users me-2"></i>Usuarios
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="permissions-tab" data-bs-toggle="tab" data-bs-target="#permissions-list" type="button">
                                    <i class="fas fa-list me-2"></i>Permisos Disponibles
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- Roles Tab -->
                            <div class="tab-pane fade show active" id="roles" role="tabpanel">
                                <div class="row">
                                    <?php foreach ($roles as $role): ?>
                                        <div class="col-md-6 col-lg-4 mb-4">
                                            <div class="card role-card h-100">
                                                <div class="card-header d-flex justify-content-between align-items-center">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($role['name']); ?></h6>
                                                    <span class="badge bg-primary"><?php echo $role['user_count']; ?> usuarios</span>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text small text-muted mb-3">
                                                        <?php echo htmlspecialchars($role['description'] ?? 'Sin descripción'); ?>
                                                    </p>
                                                    
                                                    <h6>Permisos:</h6>
                                                    <div class="mb-3">
                                                        <?php 
                                                        $role_permissions = json_decode($role['permissions'] ?? '[]', true);
                                                        if (in_array('all', $role_permissions)): ?>
                                                            <span class="badge bg-danger">Acceso Completo</span>
                                                        <?php else: ?>
                                                            <?php foreach ($role_permissions as $perm): ?>
                                                                <span class="badge bg-secondary me-1 mb-1"><?php echo $perm; ?></span>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <div class="d-grid gap-2">
                                                        <button class="btn btn-sm btn-primary" 
                                                                onclick="editRolePermissions(<?php echo htmlspecialchars(json_encode($role)); ?>)">
                                                            <i class="fas fa-edit me-1"></i>Editar Permisos
                                                        </button>
                                                        
                                                        <?php if (!in_array($role['name'], ['administrador', 'gerente', 'mesero', 'cocina', 'delivery', 'mostrador'])): ?>
                                                            <button class="btn btn-sm btn-outline-danger" 
                                                                    onclick="deleteRole(<?php echo $role['id']; ?>, '<?php echo htmlspecialchars($role['name']); ?>')">
                                                                <i class="fas fa-trash me-1"></i>Eliminar
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Users Tab -->
                            <div class="tab-pane fade" id="users-permissions" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Usuario</th>
                                                <th>Nombre Completo</th>
                                                <th>Rol Actual</th>
                                                <th>Permisos</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($users as $user): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                                                        <?php if ($user['id'] == $_SESSION['user_id']): ?>
                                                            <span class="badge bg-info ms-1">Tú</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                    <td>
                                                        <span class="badge bg-primary">
                                                            <?php echo htmlspecialchars($user['role_name']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $user_permissions = json_decode($user['permissions'] ?? '[]', true);
                                                        if (in_array('all', $user_permissions)): ?>
                                                            <span class="badge bg-danger">Acceso Completo</span>
                                                        <?php else: ?>
                                                            <small><?php echo implode(', ', $user_permissions); ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                            <button class="btn btn-sm btn-outline-primary" 
                                                                    onclick="changeUserRole(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                                <i class="fas fa-user-tag"></i>
                                                                Cambiar Rol
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="text-muted">Tu cuenta</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <!-- Available Permissions -->
                            <div class="tab-pane fade" id="permissions-list" role="tabpanel">
                                <h5 class="mb-4">Permisos Disponibles en el Sistema</h5>
                                <div class="row">
                                    <?php foreach ($available_permissions as $perm => $desc): ?>
                                        <div class="col-md-6 col-lg-4 mb-3">
                                            <div class="card">
                                                <div class="card-body">
                                                    <h6 class="card-title">
                                                        <span class="badge bg-secondary me-2"><?php echo $perm; ?></span>
                                                    </h6>
                                                    <p class="card-text small"><?php echo $desc; ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Role Permissions Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="editRoleForm">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-edit me-2"></i>
                            Editar Permisos del Rol
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_role_permissions">
                        <input type="hidden" name="role_id" id="editRoleId">
                        
                        <div class="mb-3">
                            <h6 id="editRoleName"></h6>
                            <p class="text-muted" id="editRoleDescription"></p>
                        </div>
                        
                        <h6 class="mb-3">Seleccionar Permisos:</h6>
                        
                        <div class="row">
                            <?php foreach ($available_permissions as $perm => $desc): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="permissions[]" value="<?php echo $perm; ?>" 
                                               id="perm_<?php echo $perm; ?>">
                                        <label class="form-check-label" for="perm_<?php echo $perm; ?>">
                                            <strong><?php echo $perm; ?></strong><br>
                                            <small class="text-muted"><?php echo $desc; ?></small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>
                            Guardar Permisos
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change User Role Modal -->
    <div class="modal fade" id="changeRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" id="changeRoleForm">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-user-tag me-2"></i>
                            Cambiar Rol de Usuario
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_user_permissions">
                        <input type="hidden" name="user_id" id="changeUserId">
                        
                        <div class="mb-3">
                            <strong>Usuario:</strong> <span id="changeUserName"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nuevo Rol:</label>
                            <select class="form-select" name="new_role_id" id="newRoleSelect" required>
                                <option value="">Seleccionar rol...</option>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?php echo $role['id']; ?>">
                                        <?php echo htmlspecialchars($role['name']); ?>
                                        <?php if ($role['description']): ?>
                                            - <?php echo htmlspecialchars($role['description']); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-user-tag me-1"></i>
                            Cambiar Rol
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Create Role Modal -->
    <div class="modal fade" id="createRoleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" id="createRoleForm">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-plus me-2"></i>
                            Crear Nuevo Rol
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_role">
                        
                        <div class="mb-3">
                            <label class="form-label">Nombre del Rol *</label>
                            <input type="text" class="form-control" name="role_name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="role_description" rows="2"></textarea>
                        </div>
                        
                        <h6 class="mb-3">Permisos:</h6>
                        <div class="row">
                            <?php foreach ($available_permissions as $perm => $desc): ?>
                                <div class="col-md-6 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="role_permissions[]" value="<?php echo $perm; ?>" 
                                               id="create_perm_<?php echo $perm; ?>">
                                        <label class="form-check-label" for="create_perm_<?php echo $perm; ?>">
                                            <strong><?php echo $perm; ?></strong><br>
                                            <small class="text-muted"><?php echo $desc; ?></small>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-plus me-1"></i>
                            Crear Rol
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Hidden Forms -->
    <form id="deleteRoleForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_role">
        <input type="hidden" name="role_id" id="deleteRoleId">
    </form>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function editRolePermissions(role) {
            document.getElementById('editRoleId').value = role.id;
            document.getElementById('editRoleName').textContent = role.name;
            document.getElementById('editRoleDescription').textContent = role.description || 'Sin descripción';
            
            // Clear all checkboxes
            document.querySelectorAll('#editRoleModal input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
            
            // Check current permissions
            const permissions = JSON.parse(role.permissions || '[]');
            permissions.forEach(perm => {
                const checkbox = document.getElementById('perm_' + perm);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            
            new bootstrap.Modal(document.getElementById('editRoleModal')).show();
        }
        
        function changeUserRole(user) {
            document.getElementById('changeUserId').value = user.id;
            document.getElementById('changeUserName').textContent = user.full_name;
            document.getElementById('newRoleSelect').value = user.role_id;
            
            new bootstrap.Modal(document.getElementById('changeRoleModal')).show();
        }
        
        function deleteRole(roleId, roleName) {
            if (confirm(`¿Está seguro de eliminar el rol "${roleName}"?\n\nEsta acción no se puede deshacer.`)) {
                document.getElementById('deleteRoleId').value = roleId;
                document.getElementById('deleteRoleForm').submit();
            }
        }
        
        // Auto-dismiss alerts
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
        
        // Reset forms when modals close
        document.getElementById('createRoleModal').addEventListener('hidden.bs.modal', function() {
            document.getElementById('createRoleForm').reset();
        });
        
        document.getElementById('editRoleModal').addEventListener('hidden.bs.modal', function() {
            document.querySelectorAll('#editRoleModal input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
            });
        });
    </script>
</body>
</html>