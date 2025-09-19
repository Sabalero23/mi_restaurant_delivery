<?php
// admin/login.php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();

// Redirect if already logged in
if ($auth->isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';

if ($_POST) {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Por favor, complete todos los campos.';
    } else {
        if ($auth->login($username, $password)) {
            header('Location: dashboard.php');
            exit();
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso - <?php echo $restaurant_name; ?></title>
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
}

body {
    background: var(--accent-color) !important;
    min-height: 100vh;
    display: flex;
    align-items: center;
    color: #212529 !important;
}

.login-card {
    background: white !important;
    color: #212529 !important;
    border-radius: 15px;
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.login-header {
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
    color: white !important;
    padding: 2rem;
    text-align: center;
}

.login-header h3 {
    color: white !important;
}

.login-header p {
    color: white !important;
}

.login-body {
    padding: 2rem;
    background: white !important;
    color: #212529 !important;
}

.form-control {
    border-radius: 10px;
    padding: 12px 20px;
    border: 1px solid #e1e5e9;
    margin-bottom: 1rem;
    background: white !important;
    color: #212529 !important;
}

.form-control:focus {
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    border-color: var(--primary-color);
    background: white !important;
    color: #212529 !important;
}

.form-control::placeholder {
    color: #6c757d !important;
}

.btn-login {
    background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
    border: none;
    border-radius: 10px;
    padding: 12px 30px;
    color: white !important;
    width: 100%;
    font-weight: 600;
    transition: transform 0.2s;
}

.btn-login:hover {
    transform: translateY(-2px);
    color: white !important;
    background: linear-gradient(45deg, var(--secondary-color), var(--accent-color));
}

.alert {
    border-radius: 10px;
}

.alert-danger {
    background: rgba(220, 53, 69, 0.1) !important;
    color: #dc3545 !important;
    border: 1px solid #dc3545;
}

.input-group-text {
    background: #f8f9fa !important;
    border-right: none;
    border-radius: 10px 0 0 10px;
    color: #6c757d !important;
    border: 1px solid #e1e5e9;
}

.input-group .form-control {
    border-left: none;
    border-radius: 0 10px 10px 0;
}

/* Toggle de contraseña mejorado */
.password-toggle {
    position: relative;
}

.password-toggle-btn {
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d !important;
    cursor: pointer;
    z-index: 10;
    padding: 0;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
    font-size: 14px;
}

.password-toggle-btn:hover {
    color: var(--primary-color) !important;
}

.password-toggle .form-control {
    padding-right: 45px;
}

.input-group.password-toggle .form-control {
    border-radius: 0 10px 10px 0;
    border-left: none;
    padding-right: 45px;
}

/* Enlaces */
.text-muted {
    color: #6c757d !important;
}

.text-decoration-none:hover {
    color: var(--primary-color) !important;
}

/* Responsive improvements */
@media (max-width: 576px) {
    .login-card {
        margin: 1rem;
    }
    
    .login-header {
        padding: 1.5rem;
    }
    
    .login-body {
        padding: 1.5rem;
    }
}
</style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-card">
                    <div class="login-header">
                        <h3 class="mb-0">
                            <i class="fas fa-utensils me-2"></i>
                            <?php echo $restaurant_name; ?>
                        </h3>
                        <p class="mb-0 mt-2">Sistema de Gestión</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="input-group mb-3">
                                <span class="input-group-text">
                                    <i class="fas fa-user"></i>
                                </span>
                                <input type="text" 
                                       class="form-control" 
                                       name="username" 
                                       placeholder="Usuario o email" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       required>
                            </div>
                            
                            <div class="input-group mb-4 password-toggle">
                                <span class="input-group-text">
                                    <i class="fas fa-lock"></i>
                                </span>
                                <input type="password" 
                                       class="form-control" 
                                       id="password"
                                       name="password" 
                                       placeholder="Contraseña" 
                                       required>
                                <button type="button" 
                                        class="password-toggle-btn" 
                                        onclick="togglePassword()"
                                        title="Mostrar/ocultar contraseña">
                                    <i class="fas fa-eye" id="toggleIcon"></i>
                                </button>
                            </div>
                            
                            <button type="submit" class="btn btn-login">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Iniciar Sesión
                            </button>
                        </form>
                        
                        <hr class="my-4">
                        
                        <div class="text-center">
                            <a href="../index.php" class="text-muted text-decoration-none">
                                <i class="fas fa-arrow-left me-1"></i>
                                Volver al menú
                            </a>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'fas fa-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'fas fa-eye';
            }
        }
        
        // Opcional: Permitir toggle con Enter cuando el botón tiene foco
        document.querySelector('.password-toggle-btn').addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                togglePassword();
            }
        });
    </script>
</body>
</html>