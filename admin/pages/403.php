<?php
// admin/pages/403.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/functions.php';

// Obtener configuraciones del sistema
$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$restaurant_phone = $settings['phone'] ?? '';
$restaurant_email = $settings['email'] ?? '';

// Incluir sistema de temas
$theme_file = '../../config/theme.php';
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
        'sidebar_width' => '280px'
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Denegado - <?php echo htmlspecialchars($restaurant_name); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Tema dinámico -->
    <?php if (file_exists('../../assets/css/generate-theme.php')): ?>
        <link rel="stylesheet" href="../../assets/css/generate-theme.php?v=<?php echo time(); ?>">
    <?php endif; ?>

    <style>
        :root {
            --primary-color: <?php echo $current_theme['primary_color'] ?? '#667eea'; ?>;
            --secondary-color: <?php echo $current_theme['secondary_color'] ?? '#764ba2'; ?>;
            --primary-gradient: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            --text-white: #ffffff;
            --shadow-large: 0 20px 40px rgba(0,0,0,0.15);
            --border-radius-large: 20px;
            --transition-base: all 0.3s ease;
            --danger-color: #dc3545;
        }

        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        
        .container {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .error-container {
            background: var(--text-white);
            border-radius: var(--border-radius-large);
            padding: 3rem;
            box-shadow: var(--shadow-large);
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .error-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--danger-color) 0%, #ff6b6b 100%);
        }
        
        .error-icon {
            font-size: 5rem;
            color: var(--danger-color);
            margin-bottom: 1.5rem;
            display: block;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        .error-title {
            color: #333;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        
        .error-message {
            color: #666;
            font-size: 1.2rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            padding: 14px 32px;
            border-radius: 25px;
            color: var(--text-white);
            font-weight: 600;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: var(--transition-base);
            margin: 0 10px 10px 0;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            color: var(--text-white);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: transparent;
            border: 2px solid #ddd;
            padding: 12px 30px;
            border-radius: 25px;
            color: #666;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            transition: var(--transition-base);
            margin: 0 10px 10px 0;
        }
        
        .btn-secondary:hover {
            background: #f8f9fa;
            color: #333;
            transform: translateY(-1px);
            text-decoration: none;
        }
        
        .restaurant-header {
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .restaurant-logo {
            width: 80px;
            height: 80px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--text-white);
            font-size: 2rem;
        }
        
        .restaurant-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .restaurant-subtitle {
            color: #888;
            font-size: 0.95rem;
        }
        
        .permission-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 15px;
            padding: 1.5rem;
            margin: 2rem 0;
            color: #856404;
        }
        
        .permission-info h5 {
            color: #856404;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .contact-info {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 0.95rem;
        }
        
        .contact-item {
            margin: 0.5rem 0;
        }
        
        .contact-item i {
            color: var(--primary-color);
            width: 20px;
        }
        
        @media (max-width: 768px) {
            .error-container {
                padding: 2rem 1.5rem;
            }
            
            .error-title {
                font-size: 1.8rem;
            }
            
            .error-message {
                font-size: 1.1rem;
            }
            
            .btn-primary,
            .btn-secondary {
                padding: 12px 24px;
                font-size: 1rem;
                margin: 5px;
                width: auto;
            }
        }
        
        @media (max-width: 480px) {
            .btn-primary,
            .btn-secondary {
                width: 100%;
                justify-content: center;
                margin: 5px 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <!-- Header del restaurante -->
            <div class="restaurant-header">
                <div class="restaurant-logo">
                    <i class="fas fa-utensils"></i>
                </div>
                <div class="restaurant-name"><?php echo htmlspecialchars($restaurant_name); ?></div>
                <div class="restaurant-subtitle">Sistema de Gestión de Restaurante</div>
            </div>
            
            <!-- Error principal -->
            <div class="error-icon">
                <i class="fas fa-ban"></i>
            </div>
            
            <h1 class="error-title">Acceso Denegado</h1>
            
            <p class="error-message">
                No tienes permisos suficientes para acceder a esta sección del sistema.
                Tu nivel de acceso actual no incluye esta funcionalidad.
            </p>
            
            <!-- Información de permisos -->
            <div class="permission-info">
                <h5><i class="fas fa-shield-alt me-2"></i>Información de Seguridad</h5>
                <p class="mb-0">
                    Para proteger la integridad del sistema, solo los usuarios con permisos específicos 
                    pueden acceder a ciertas funciones administrativas.
                </p>
            </div>
            
            <!-- Botones de acción -->
            <div class="d-flex flex-wrap justify-content-center">
                <a href="../../index.php" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>
                    Ir al Inicio
                </a>
                <a href="../dashboard.php" class="btn btn-primary">
                    <i class="fas fa-tachometer-alt me-2"></i>
                    Panel Principal
                </a>
                <button onclick="history.back()" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>
                    Volver
                </button>
            </div>
            
            <!-- Información de contacto -->
            <div class="contact-info">
                <h6 class="fw-bold mb-3">
                    <i class="fas fa-headset me-2"></i>
                    ¿Necesitas acceso adicional?
                </h6>
                <p class="mb-2">Contacta al administrador del sistema:</p>
                
                <?php if (!empty($restaurant_email)): ?>
                <div class="contact-item">
                    <i class="fas fa-envelope me-2"></i>
                    <strong>Email:</strong> <?php echo htmlspecialchars($restaurant_email); ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($restaurant_phone)): ?>
                <div class="contact-item">
                    <i class="fas fa-phone me-2"></i>
                    <strong>Teléfono:</strong> <?php echo htmlspecialchars($restaurant_phone); ?>
                </div>
                <?php endif; ?>
                
                <div class="contact-item mt-3">
                    <small class="text-muted">
                        <i class="fas fa-clock me-1"></i>
                        Respuesta típica: 24-48 horas
                    </small>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Efecto de entrada suave
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.querySelector('.error-container');
            container.style.opacity = '0';
            container.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                container.style.transition = 'all 0.5s ease';
                container.style.opacity = '1';
                container.style.transform = 'translateY(0)';
            }, 100);
        });
        
        // Track del intento de acceso para auditoría (opcional)
        console.log('403 Access Denied - Page: ' + window.location.href + ' - Time: ' + new Date().toISOString());
    </script>
</body>
</html>