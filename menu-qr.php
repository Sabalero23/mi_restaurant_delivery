<?php
// menu-qr.php - Versión mejorada
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';
require_once 'models/Product.php';
require_once 'models/Category.php';

$productModel = new Product();
$categoryModel = new Category();

$categories = $categoryModel->getAll();
$products = $productModel->getAll();

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $restaurant_name; ?> - Menú Digital</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Tema dinámico -->
<?php if (file_exists('assets/css/generate-theme.php')): ?>
    <link rel="stylesheet" href="assets/css/generate-theme.php?v=<?php echo time(); ?>">
<?php endif; ?>

<?php
// Incluir sistema de temas
$theme_file = 'config/theme.php';
if (file_exists($theme_file)) {
    require_once $theme_file;
    $database_theme = new Database();
    $db_theme = $database_theme->getConnection();
    $theme_manager = new ThemeManager($db_theme);
    $current_theme = $theme_manager->getThemeSettings();
} else {
    $current_theme = array(
        'primary_color' => '#667eea',
        'secondary_color' => '#764ba2',
        'accent_color' => '#ff6b6b',
        'success_color' => '#28a745'
    );
}
?>
    <style>
:root {
    --primary-color: <?php echo $current_theme['primary_color'] ?? '#667eea'; ?>;
    --secondary-color: <?php echo $current_theme['secondary_color'] ?? '#764ba2'; ?>;
    --accent-color: <?php echo $current_theme['accent_color'] ?? '#ff6b6b'; ?>;
    --success-color: <?php echo $current_theme['success_color'] ?? '#28a745'; ?>;
    --text-dark: #2c3e50;
    --text-muted: #6c757d;
    --card-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
    --hover-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
}

* {
    box-sizing: border-box;
}

body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background: #f8f9fa !important;
    line-height: 1.5;
    color: #2c3e50 !important;
    font-size: 14px;
}

/* Header más compacto */
.header {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white !important;
    padding: 1.5rem 0;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
}

.header h1 {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 0.3rem;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    color: white !important;
}

.header p {
    font-size: 1rem;
    opacity: 0.9;
    margin: 0;
    color: white !important;
}

/* Filtros de categorías más compactos */
.category-filters {
    background: white !important;
    padding: 1rem 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    position: sticky;
    top: 120px;
    z-index: 999;
}

.filter-btn {
    background: #f8f9fa !important;
    border: 2px solid #e9ecef;
    color: #2c3e50 !important;
    padding: 0.5rem 1rem;
    margin: 0.2rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.85rem;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
}

.filter-btn:hover {
    background: var(--primary-color) !important;
    border-color: var(--primary-color);
    color: white !important;
    transform: translateY(-1px);
}

.filter-btn.active {
    background: var(--primary-color) !important;
    border-color: var(--primary-color);
    color: white !important;
    box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
}

/* Tarjetas de productos más compactas */
.product-card {
    background: white !important;
    color: #2c3e50 !important;
    border: none;
    border-radius: 15px;
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
    margin-bottom: 1.5rem;
    overflow: hidden;
    height: 100%;
}

.product-card:hover {
    transform: translateY(-4px);
    box-shadow: var(--hover-shadow);
}

.product-image {
    height: 180px;
    width: 100%;
    object-fit: cover;
    transition: transform 0.3s ease;
}

.product-card:hover .product-image {
    transform: scale(1.03);
}

.product-placeholder {
    height: 180px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%) !important;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #6c757d !important;
    font-size: 2.5rem;
}

.card-body {
    padding: 1.2rem;
    background: white !important;
    color: #2c3e50 !important;
}

.card-title {
    font-size: 1.1rem;
    font-weight: 700;
    color: #2c3e50 !important;
    margin-bottom: 0.5rem;
    line-height: 1.3;
}

.card-description {
    color: #6c757d !important;
    font-size: 0.85rem;
    margin-bottom: 0.8rem;
    line-height: 1.4;
}

.price {
    color: var(--success-color) !important;
    font-weight: 700;
    font-size: 1.2rem;
    margin: 0;
}

/* Sección de categoría más compacta */
.category-section {
    margin: 2rem 0;
}

.category-title {
    font-size: 1.6rem;
    font-weight: 700;
    color: #2c3e50 !important;
    margin-bottom: 1.5rem;
    padding-bottom: 0.4rem;
    border-bottom: 3px solid var(--primary-color);
    display: inline-block;
}

/* Botón llamar mesero más compacto */
/* Botón llamar mesero más compacto */
.btn-call {
    position: fixed;
    bottom: 25px;
    right: 20px;
    background: linear-gradient(135deg, var(--accent-color) 0%, var(--secondary-color) 100%);
    color: white !important;
    border: none;
    padding: 12px 18px;
    border-radius: 40px;
    font-size: 14px;
    font-weight: 600;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    z-index: 2000;
    transition: all 0.3s ease;
    min-width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-call:hover {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--accent-color) 100%);
    transform: scale(1.05);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.4);
    color: white !important;
}

.btn-call .btn-text {
    margin-left: 6px;
    display: none;
}

/* Animaciones */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.product-card {
    animation: fadeInUp 0.5s ease forwards;
}

.category-section:nth-child(even) .product-card {
    animation-delay: 0.1s;
}

.category-section:nth-child(odd) .product-card {
    animation-delay: 0.15s;
}

/* Responsive más compacto */
@media (max-width: 768px) {
    .header h1 {
        font-size: 1.7rem;
    }

    .header {
        padding: 1.2rem 0;
    }

    .category-filters {
        top: 100px;
        padding: 0.8rem 0;
    }

    .filter-btn {
        padding: 0.4rem 0.8rem;
        font-size: 0.8rem;
        margin: 0.15rem;
    }

    .category-title {
        font-size: 1.4rem;
    }

    .btn-call {
        bottom: 15px;
        right: 15px;
        padding: 10px;
        width: 45px;
        height: 45px;
        font-size: 13px;
    }

    .btn-call .btn-text {
        display: none;
    }

    .product-image,
    .product-placeholder {
        height: 150px;
    }

    .card-body {
        padding: 1rem;
    }

    .card-title {
        font-size: 1rem;
    }

    .card-description {
        font-size: 0.8rem;
    }

    .price {
        font-size: 1.1rem;
    }
}

@media (min-width: 769px) {
    .btn-call .btn-text {
        display: inline;
    }
}

/* Estados especiales */
.product-unavailable {
    opacity: 0.6;
    position: relative;
}

.product-unavailable::after {
    content: 'No disponible';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(220, 53, 69, 0.9);
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.8rem;
}

/* Indicador de carga */
.loading {
    text-align: center;
    padding: 1.5rem;
    color: #6c757d !important;
}

.spinner {
    border: 2px solid #f3f3f3;
    border-top: 2px solid var(--primary-color);
    border-radius: 50%;
    width: 35px;
    height: 35px;
    animation: spin 1s linear infinite;
    margin: 0 auto 0.8rem;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Mensaje vacío */
.empty-category {
    text-align: center;
    padding: 2rem 1rem;
    color: #6c757d !important;
    background: white !important;
    border-radius: 15px;
    margin: 1rem 0;
}

.empty-category i {
    font-size: 3rem;
    margin-bottom: 0.8rem;
    opacity: 0.5;
    color: #6c757d !important;
}

.empty-category h4 {
    color: #2c3e50 !important;
    font-size: 1.2rem;
    margin-bottom: 0.5rem;
}

.empty-category p {
    color: #6c757d !important;
    font-size: 0.9rem;
    margin: 0;
}

/* Container más compacto */
.container {
    max-width: 1140px;
}

.py-4 {
    padding-top: 2rem !important;
    padding-bottom: 2rem !important;
}

/* Espaciado entre productos */
.row.g-4 {
    --bs-gutter-x: 1rem;
    --bs-gutter-y: 1rem;
}

/* Texto adicional para asegurar visibilidad */
.text-muted {
    color: #6c757d !important;
}

small.text-muted {
    color: #6c757d !important;
    font-size: 0.8rem;
}
</style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <div class="container">
            <h1>
                <i class="fas fa-utensils me-3"></i>
                <?php echo htmlspecialchars($restaurant_name); ?>
            </h1>
            <p>Descubre nuestra deliciosa selección de platos</p>
        </div>
    </div>

    <!-- Filtros de categorías -->
    <div class="category-filters">
        <div class="container">
            <div class="text-center">
                <button class="filter-btn active" onclick="filterCategory('all')">
                    <i class="fas fa-th-large me-2"></i>
                    Todos
                </button>
                <?php foreach ($categories as $category): ?>
                    <button class="filter-btn" onclick="filterCategory(<?php echo $category['id']; ?>)">
                        <i class="fas fa-utensils me-2"></i>
                        <?php echo htmlspecialchars($category['name']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="container py-4">
        <!-- Mostrar todas las categorías inicialmente -->
        <?php foreach ($categories as $category): ?>
            <div class="category-section" id="category-<?php echo $category['id']; ?>">
                <h2 class="category-title">
                    <i class="fas fa-utensils me-3"></i>
                    <?php echo htmlspecialchars($category['name']); ?>
                </h2>
                
                <?php
                $categoryProducts = array_filter($products, function($prod) use ($category) {
                    return $prod['category_id'] == $category['id'] && $prod['is_available'];
                });
                ?>
                
                <?php if (empty($categoryProducts)): ?>
                    <div class="empty-category">
                        <i class="fas fa-utensils"></i>
                        <h4>No hay productos disponibles</h4>
                        <p>Esta categoría está temporalmente sin productos disponibles.</p>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <?php foreach ($categoryProducts as $product): ?>
                            <div class="col-lg-3 col-md-6 col-sm-12 product-item" data-category="<?php echo $category['id']; ?>">
                                <div class="card product-card <?php echo !$product['is_available'] ? 'product-unavailable' : ''; ?>">
                                    <?php if (!empty($product['image']) && file_exists($product['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($product['image']); ?>" 
                                             class="product-image" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>"
                                             loading="lazy">
                                    <?php else: ?>
                                        <div class="product-placeholder">
                                            <i class="fas fa-utensils"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                        
                                        <?php if (!empty($product['description'])): ?>
                                            <p class="card-description"><?php echo htmlspecialchars($product['description']); ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <p class="price">
                                                $<?php echo number_format($product['price'], 2, ',', '.'); ?>
                                            </p>
                                            
                                            <?php if ($product['preparation_time'] > 0): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    <?php echo $product['preparation_time']; ?> min
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Botón llamar mesero -->
    <button class="btn-call" onclick="callWaiter()">
        <i class="fas fa-bell"></i>
        <span class="btn-text">Llamar mesero</span>
    </button>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Variables globales
        let currentFilter = 'all';

        // Función para filtrar por categoría
        function filterCategory(categoryId) {
            currentFilter = categoryId;
            
            // Actualizar botones activos
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Mostrar/ocultar secciones de categorías
            document.querySelectorAll('.category-section').forEach(section => {
                if (categoryId === 'all') {
                    section.style.display = 'block';
                    // Animar la entrada
                    section.style.opacity = '0';
                    setTimeout(() => {
                        section.style.opacity = '1';
                        section.style.transition = 'opacity 0.3s ease';
                    }, 100);
                } else {
                    if (section.id === `category-${categoryId}`) {
                        section.style.display = 'block';
                        section.style.opacity = '0';
                        setTimeout(() => {
                            section.style.opacity = '1';
                            section.style.transition = 'opacity 0.3s ease';
                        }, 100);
                    } else {
                        section.style.display = 'none';
                    }
                }
            });

            // Scroll suave al inicio del contenido
            setTimeout(() => {
                document.querySelector('.container.py-4').scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }, 200);
        }

        // Función mejorada para llamar al mesero
        function callWaiter() {
            // Crear modal personalizado
            const modal = document.createElement('div');
            modal.innerHTML = `
                <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                    <div style="background: white; padding: 2rem; border-radius: 15px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <h4 style="color: #2c3e50; margin-bottom: 1rem;">
                            <i class="fas fa-bell" style="color: #ff6b6b; margin-right: 0.5rem;"></i>
                            Llamar al mesero
                        </h4>
                        <p style="color: #6c757d; margin-bottom: 1.5rem;">Por favor, ingrese su número de mesa:</p>
                        <input type="number" id="tableNumber" placeholder="Ej: 5" style="width: 100%; padding: 0.75rem; border: 2px solid #e9ecef; border-radius: 8px; margin-bottom: 1.5rem; font-size: 1.1rem; text-align: center;">
                        <div style="display: flex; gap: 0.5rem;">
                            <button onclick="this.closest('div').parentNode.remove()" style="flex: 1; padding: 0.75rem; background: #6c757d; color: white; border: none; border-radius: 8px; cursor: pointer;">Cancelar</button>
                            <button onclick="confirmCall()" style="flex: 1; padding: 0.75rem; background: #ff6b6b; color: white; border: none; border-radius: 8px; cursor: pointer;">Llamar</button>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            document.getElementById('tableNumber').focus();
            
            // Permitir llamar con Enter
            document.getElementById('tableNumber').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    confirmCall();
                }
            });
        }

        function confirmCall() {
            const tableNumber = document.getElementById('tableNumber').value;
            
            if (!tableNumber || tableNumber.trim() === "") {
                alert("Por favor, ingrese un número de mesa válido");
                return;
            }

            // Mostrar indicador de carga
            const modal = document.querySelector('[style*="position: fixed"]');
            modal.innerHTML = `
                <div style="background: white; padding: 2rem; border-radius: 15px; max-width: 300px; width: 90%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                    <div style="border: 3px solid #f3f3f3; border-top: 3px solid #ff6b6b; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 1rem;"></div>
                    <p style="color: #6c757d;">Notificando al mesero...</p>
                </div>
            `;

            // Enviar solicitud
            fetch("call_waiter.php", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "mesa=" + encodeURIComponent(tableNumber)
            })
            .then(response => response.text())
            .then(data => {
                // Mostrar confirmación
                modal.innerHTML = `
                    <div style="background: white; padding: 2rem; border-radius: 15px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <div style="color: #28a745; font-size: 3rem; margin-bottom: 1rem;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h4 style="color: #2c3e50; margin-bottom: 1rem;">¡Mesero notificado!</h4>
                        <p style="color: #6c757d; margin-bottom: 1.5rem;">El mesero de la mesa ${tableNumber} ha sido notificado y se acercará en breve.</p>
                        <button onclick="this.closest('div').parentNode.remove()" style="padding: 0.75rem 2rem; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer;">Entendido</button>
                    </div>
                `;
                
                // Auto-cerrar después de 3 segundos
                setTimeout(() => {
                    if (modal && modal.parentNode) {
                        modal.remove();
                    }
                }, 3000);
            })
            .catch(error => {
                console.error('Error:', error);
                modal.innerHTML = `
                    <div style="background: white; padding: 2rem; border-radius: 15px; max-width: 400px; width: 90%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
                        <div style="color: #dc3545; font-size: 3rem; margin-bottom: 1rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <h4 style="color: #2c3e50; margin-bottom: 1rem;">Error de conexión</h4>
                        <p style="color: #6c757d; margin-bottom: 1.5rem;">No se pudo notificar al mesero. Por favor, intente nuevamente.</p>
                        <button onclick="this.closest('div').parentNode.remove()" style="padding: 0.75rem 2rem; background: #dc3545; color: white; border: none; border-radius: 8px; cursor: pointer;">Cerrar</button>
                    </div>
                `;
            });
        }

        // Inicialización
        document.addEventListener('DOMContentLoaded', function() {
            // Agregar lazy loading a las imágenes
            const images = document.querySelectorAll('.product-image');
            if ('IntersectionObserver' in window) {
                const imageObserver = new IntersectionObserver((entries, observer) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const img = entry.target;
                            img.src = img.dataset.src || img.src;
                            img.classList.remove('lazy');
                            observer.unobserve(img);
                        }
                    });
                });

                images.forEach(img => imageObserver.observe(img));
            }

            // Smooth scroll para los filtros
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    // Agregar clase activa con animación
                    this.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        this.style.transform = 'scale(1)';
                    }, 150);
                });
            });
        });

        // Agregar estilos CSS para la animación de spin
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>

<?php include 'footer.php'; ?>
</body>
</html>