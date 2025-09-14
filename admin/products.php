<?php
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

$auth = new Auth();
$auth->requirePermission('products');

$productModel = new Product();
$categoryModel = new Category();

$categories = $categoryModel->getAll();
$products = $productModel->getAll(null, false); // Include inactive products

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';

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
        'name' => sanitize($_POST['name']),
        'description' => sanitize($_POST['description']),
        'price' => floatval($_POST['price']),
        'cost' => floatval($_POST['cost']),
        'preparation_time' => intval($_POST['preparation_time']),
        'is_available' => isset($_POST['is_available']) ? 1 : 0,
        'image' => null
    ];
    
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_path = uploadImage($_FILES['image'], 'products');
        if ($image_path) {
            // Normalize so DB stores 'admin/uploads/...' not 'uploads/...'
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
        'name' => sanitize($_POST['name']),
        'description' => sanitize($_POST['description']),
        'price' => floatval($_POST['price']),
        'cost' => floatval($_POST['cost']),
        'preparation_time' => intval($_POST['preparation_time']),
        'is_available' => isset($_POST['is_available']) ? 1 : 0,
        'image' => $product['image'] // Keep existing image by default
    ];
    
    // Handle new image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image_path = uploadImage($_FILES['image'], 'products');
        if ($image_path) {
            // Delete old image if exists (use normalized path)
            $oldImagePath = normalizeImagePath($product['image'] ?? null);
            if (!empty($product['image']) && file_exists($oldImagePath)) {
                unlink($oldImagePath);
            }
            // Normalize new image path before saving to DB
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
    
    if ($productModel->delete($id)) {
        return ['success' => true, 'message' => 'Producto eliminado exitosamente'];
    } else {
        return ['success' => false, 'message' => 'Error al eliminar el producto'];
    }
}

function toggleAvailability() {
    global $productModel;
    
    $id = intval($_POST['id']);
    $is_available = isset($_POST['is_available']) ? 1 : 0;
    
    if ($productModel->update($id, ['is_available' => $is_available])) {
        $status = $is_available ? 'disponible' : 'no disponible';
        return ['success' => true, 'message' => "Producto marcado como {$status}"];
    } else {
        return ['success' => false, 'message' => 'Error al actualizar disponibilidad'];
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            --sidebar-width: 280px;
            --sidebar-mobile-width: 100%;
        }

        body {
            background: #f8f9fa;
            overflow-x: hidden;
        }

        /* Mobile Top Bar */
        .mobile-topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1040;
            background: var(--primary-gradient);
            color: white;
            padding: 1rem;
            display: none;
        }

        .mobile-topbar h5 {
            margin: 0;
            font-size: 1.1rem;
        }

        .menu-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            padding: 0.5rem;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .menu-toggle:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--primary-gradient);
            color: white;
            z-index: 1030;
            transition: transform 0.3s ease-in-out;
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
            transition: opacity 0.3s ease-in-out;
        }

        .sidebar-backdrop.show {
            display: block;
            opacity: 1;
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 0.75rem 1rem;
            border-radius: 8px;
            margin-bottom: 0.25rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link .badge {
            margin-left: auto;
        }

        /* Close button for mobile sidebar */
        .sidebar-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: none;
            color: white;
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
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
            transition: margin-left 0.3s ease-in-out;
        }

        /* Page header */
        .page-header {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        /* Statistics cards */
        .stats-row {
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
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

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        /* Product cards */
        .product-card {
            transition: transform 0.3s ease;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border-radius: 15px;
            overflow: hidden;
            height: 100%;
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
            border-left: 5px solid #dc3545;
        }

        /* Filter card */
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
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

        @media (max-width: 576px) {
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
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background: #1a1a1a;
            }

            .stat-card,
            .card,
            .page-header,
            .filter-card {
                background: #2d2d2d;
                color: white;
            }

            .card-header {
                background: #2d2d2d !important;
                border-bottom-color: #404040;
            }

            .text-muted {
                color: #aaa !important;
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
    </style>
</head>
<body>
    <!-- Mobile Top Bar -->
    <div class="mobile-topbar">
        <div class="d-flex justify-content-between align-items-center w-100">
            <div class="d-flex align-items-center">
                <button class="menu-toggle me-3" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h5>
                    <i class="fas fa-utensils me-2"></i>
                    Productos
                </h5>
            </div>
            <div class="d-flex align-items-center">
                <small class="me-3 d-none d-sm-inline">
                    <i class="fas fa-user me-1"></i>
                    <?php echo explode(' ', $_SESSION['full_name'])[0]; ?>
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
            <small>Gestión de Productos</small>
        </div>

        <div class="mb-4">
            <div class="d-flex align-items-center">
                <div class="bg-white bg-opacity-20 rounded-circle p-2 me-2">
                    <i class="fas fa-user"></i>
                </div>
                <div>
                    <div class="fw-bold"><?php echo $_SESSION['full_name']; ?></div>
                    <small class="opacity-75"><?php echo ucfirst($_SESSION['role_name']); ?></small>
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
            
            <a class="nav-link" href="tables.php">
                <i class="fas fa-table me-2"></i>
                Mesas
            </a>
            
            <a class="nav-link active" href="products.php">
                <i class="fas fa-utensils me-2"></i>
                Productos
            </a>
            
            <?php if ($auth->hasPermission('users')): ?>
                <a class="nav-link" href="users.php">
                    <i class="fas fa-users me-2"></i>
                    Usuarios
                </a>
            <?php endif; ?>
            
            <?php if ($auth->hasPermission('reports')): ?>
                <a class="nav-link" href="reports.php">
                    <i class="fas fa-chart-bar me-2"></i>
                    Reportes
                </a>
            <?php endif; ?>

            <?php if ($auth->hasPermission('all')): ?>
                <hr class="text-white-50 my-3">
                <small class="text-white-50 px-3 mb-2 d-block">CONFIGURACIÓN</small>

                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cog me-2"></i>
                    Configuración
                </a>
            <?php endif; ?>

            <hr class="text-white-50 my-3">
            <a class="nav-link" href="logout.php">
                <i class="fas fa-sign-out-alt me-2"></i>
                Cerrar Sesión
            </a>
        </nav>
    </div>

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
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#productModal" onclick="newProduct()">
                        <i class="fas fa-plus me-1"></i>
                        <span class="d-none d-sm-inline">Nuevo </span>Producto
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
                     data-name="<?php echo strtolower($product['name']); ?>">
                    <div class="card product-card <?php echo !$product['is_available'] ? 'product-unavailable' : ''; ?> <?php echo !$product['is_active'] ? 'product-inactive' : ''; ?>">
                        <?php if ($product['image']): ?>
                            <img src="../<?php echo htmlspecialchars($product['image']); ?>" 
                                 class="card-img-top product-image" 
                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                        <?php else: ?>
                            <div class="product-image d-flex align-items-center justify-content-center bg-light">
                                <i class="fas fa-image fa-2x text-muted"></i>
                            </div>
                        <?php endif; ?>
                        
                        <div class="card-body d-flex flex-column p-2 p-md-3">
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
                            
                            <?php if (!empty($product['description'])): ?>
                                <p class="card-text text-muted small mb-2 d-none d-md-block">
                                    <?php echo htmlspecialchars(substr($product['description'], 0, 50)); ?>...
                                </p>
                            <?php endif; ?>
                            
                            <div class="mb-2">
                                <span class="badge bg-primary small">
                                    <?php echo $product['category_name'] ?? 'Sin categoría'; ?>
                                </span>
                            </div>
                            
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
                                
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <?php if (!$product['is_active']): ?>
                                            <span class="badge bg-danger small">Inactivo</span>
                                        <?php elseif (!$product['is_available']): ?>
                                            <span class="badge bg-warning small">No disponible</span>
                                        <?php else: ?>
                                            <span class="badge bg-success small">Disponible</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <button class="btn btn-sm btn-primary" onclick="editProduct(<?php echo $product['id']; ?>)">
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

    <!-- Product Modal -->
    <div class="modal fade" id="productModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data" id="productForm">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">
                            <i class="fas fa-plus me-2"></i>
                            Nuevo Producto
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" id="formAction" value="create">
                        <input type="hidden" name="id" id="productId">
                        
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Nombre *</label>
                                    <input type="text" class="form-control" name="name" id="productName" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Descripción</label>
                                    <textarea class="form-control" name="description" id="productDescription" rows="3"></textarea>
                                </div>
                                
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
                            
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label class="form-label">Imagen</label>
                                    <div class="text-center">
                                        <div id="imagePreview" class="mb-3" style="display: none;">
                                            <img id="previewImg" src="" class="img-fluid rounded" style="max-height: 200px;">
                                        </div>
                                        <input type="file" class="form-control mb-2" name="image" id="productImage" accept="image/*" onchange="previewImage()">
                                        <small class="text-muted">JPG, PNG, GIF. Máximo 5MB</small>
                                    </div>
                                </div>
                                
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_available" id="productAvailable" checked>
                                    <label class="form-check-label" for="productAvailable">
                                        Disponible para venta
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success" id="submitBtn">
                            <i class="fas fa-save me-1"></i>
                            Guardar Producto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu functionality - Initialize immediately
        document.addEventListener('DOMContentLoaded', function() {
            initializeMobileMenu();
            setupFilters();
        });

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

        function setupFilters() {
            document.getElementById('searchInput').addEventListener('input', filterProducts);
            document.getElementById('categoryFilter').addEventListener('change', filterProducts);
            document.getElementById('statusFilter').addEventListener('change', filterProducts);
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
        
        function newProduct() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>Nuevo Producto';
            document.getElementById('formAction').value = 'create';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Guardar Producto';
            document.getElementById('productForm').reset();
            document.getElementById('productId').value = '';
            document.getElementById('imagePreview').style.display = 'none';
        }
        
        function editProduct(id) {
            fetch(`api/products.php?id=${id}`)
                .then(response => response.json())
                .then(product => {
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Editar Producto';
                    document.getElementById('formAction').value = 'update';
                    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save me-1"></i>Actualizar Producto';
                    
                    document.getElementById('productId').value = product.id;
                    document.getElementById('productName').value = product.name;
                    document.getElementById('productDescription').value = product.description || '';
                    document.getElementById('productPrice').value = product.price;
                    document.getElementById('productCost').value = product.cost || '';
                    document.getElementById('productCategory').value = product.category_id || '';
                    document.getElementById('productPrepTime').value = product.preparation_time || '';
                    document.getElementById('productAvailable').checked = product.is_available == 1;
                    
                    // Show image preview if exists
                    if (product.image) {
                        document.getElementById('previewImg').src = '../' + product.image;
                        document.getElementById('imagePreview').style.display = 'block';
                    } else {
                        document.getElementById('imagePreview').style.display = 'none';
                    }
                    
                    new bootstrap.Modal(document.getElementById('productModal')).show();
                })
                .catch(error => {
                    console.error('Error loading product:', error);
                    alert('Error al cargar el producto');
                });
        }
        
        function deleteProduct(id, name) {
            if (confirm(`¿Está seguro de que desea eliminar el producto "${name}"?`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="${id}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function toggleAvailability(id, available) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="toggle_availability">
                <input type="hidden" name="id" value="${id}">
                ${available === 'true' ? '<input type="hidden" name="is_available" value="1">' : ''}
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        function previewImage() {
            const file = document.getElementById('productImage').files[0];
            const preview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        }
        
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