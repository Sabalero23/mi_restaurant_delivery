<?php
// install.php - Script de instalación del sistema
// IMPORTANTE: Eliminar este archivo después de la instalación

if (file_exists('config/installed.lock')) {
    die('El sistema ya está instalado. Elimine el archivo config/installed.lock para reinstalar.');
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? intval($_GET['step']) : 1;

if ($_POST) {
    if ($step === 1) {
        // Verificar conexión a la base de datos
        try {
            $host = $_POST['db_host'];
            $dbname = $_POST['db_name'];
            $username = $_POST['db_user'];
            $password = $_POST['db_pass'];
            
            $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Guardar configuración
            $config_content = "<?php\n";
            $config_content .= "// config/config.php - Generado automáticamente\n";
            $config_content .= "define('DB_HOST', '$host');\n";
            $config_content .= "define('DB_NAME', '$dbname');\n";
            $config_content .= "define('DB_USER', '$username');\n";
            $config_content .= "define('DB_PASS', '" . addslashes($password) . "');\n\n";
            $config_content .= "define('BASE_URL', 'http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/');\n";
            $config_content .= "define('UPLOAD_PATH', 'uploads/');\n";
            $config_content .= "define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB\n\n";
            $config_content .= "ini_set('session.cookie_httponly', 1);\n";
            $config_content .= "ini_set('session.use_only_cookies', 1);\n";
            $config_content .= "date_default_timezone_set('America/Argentina/Buenos_Aires');\n";
            
            if (!is_dir('config')) {
                mkdir('config', 0755, true);
            }
            
            file_put_contents('config/config.php', $config_content);
            
            $step = 2;
            $success = 'Conexión exitosa. Configurando base de datos...';
            
        } catch (Exception $e) {
            $error = 'Error de conexión: ' . $e->getMessage();
        }
    }
    
    if ($step === 2) {
        // Crear estructura de base de datos usando el archivo SQL
        try {
            require_once 'config/config.php';
            
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Buscar el archivo SQL en posibles ubicaciones
            $sql_files = ['bd.sql', 'database/bd.sql', 'sql/bd.sql'];
            $sql_file = null;
            
            foreach ($sql_files as $file) {
                if (file_exists($file)) {
                    $sql_file = $file;
                    break;
                }
            }
            
            if (!$sql_file) {
                // Si no existe el archivo SQL, crear estructura manualmente
                createDatabaseStructure($pdo);
            } else {
                // Usar el archivo SQL existente
                executeSQLFile($pdo, $sql_file);
            }
            
            $step = 3;
            $success = 'Base de datos configurada correctamente.';
            
        } catch (Exception $e) {
            $error = 'Error configurando base de datos: ' . $e->getMessage();
        }
    }
    
    if ($step === 3) {
        // Configurar datos del restaurante
        try {
            require_once 'config/config.php';
            
            $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
            
            $restaurant_data = [
                'restaurant_name' => $_POST['restaurant_name'] ?? 'Mi Restaurante',
                'restaurant_phone' => $_POST['restaurant_phone'] ?? '',
                'restaurant_address' => $_POST['restaurant_address'] ?? '',
                'restaurant_email' => $_POST['restaurant_email'] ?? '',
                'whatsapp_number' => $_POST['whatsapp_number'] ?? '',
                'delivery_fee' => $_POST['delivery_fee'] ?? '3000',
                'tax_rate' => $_POST['tax_rate'] ?? '21',
                'opening_time' => $_POST['opening_time'] ?? '08:00',
                'closing_time' => $_POST['closing_time'] ?? '23:59',
                'kitchen_closing_time' => $_POST['kitchen_closing_time'] ?? '23:59',
                'max_delivery_distance' => $_POST['max_delivery_distance'] ?? '10',
                'min_delivery_amount' => $_POST['min_delivery_amount'] ?? '1500',
                'google_maps_api_key' => $_POST['google_maps_api_key'] ?? ''
            ];
            
            foreach ($restaurant_data as $key => $value) {
                $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?) 
                                     ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
                $descriptions = [
                    'restaurant_name' => 'Nombre del restaurante',
                    'restaurant_phone' => 'Teléfono del restaurante',
                    'restaurant_address' => 'Dirección del restaurante',
                    'restaurant_email' => 'Email del restaurante',
                    'whatsapp_number' => 'Número de WhatsApp',
                    'delivery_fee' => 'Costo de envío',
                    'tax_rate' => 'Porcentaje de IVA',
                    'opening_time' => 'Hora de apertura',
                    'closing_time' => 'Hora de cierre',
                    'kitchen_closing_time' => 'Hora de cierre de cocina',
                    'max_delivery_distance' => 'Distancia máxima de delivery (km)',
                    'min_delivery_amount' => 'Monto mínimo para delivery',
                    'google_maps_api_key' => 'Clave de API de Google Maps'
                ];
                $stmt->execute([$key, $value, $descriptions[$key] ?? '']);
            }
            
            // Crear carpetas necesarias
            $folders = ['uploads', 'uploads/products', 'uploads/categories', 'admin/uploads', 'admin/uploads/products'];
            foreach ($folders as $folder) {
                if (!is_dir($folder)) {
                    mkdir($folder, 0755, true);
                }
            }
            
            // Crear usuario administrador
            createAdminUser($pdo, $_POST['admin_password'] ?? 'password');
            
            // Crear archivo de instalación completada
            file_put_contents('config/installed.lock', date('Y-m-d H:i:s'));
            
            $step = 4;
            $success = 'Instalación completada exitosamente.';
            
        } catch (Exception $e) {
            $error = 'Error configurando restaurante: ' . $e->getMessage();
        }
    }
}

function executeSQLFile($pdo, $sql_file) {
    $sql_content = file_get_contents($sql_file);
    
    // Procesar el SQL de manera más robusta
    $sql_content = preg_replace('/^--.*$/m', '', $sql_content); // Remover comentarios
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content); // Remover comentarios multilínea
    $sql_content = preg_replace('/^\s*$/m', '', $sql_content); // Remover líneas vacías
    
    // Dividir por ';' pero mantener declaraciones completas
    $statements = [];
    $current_statement = '';
    $lines = explode("\n", $sql_content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $current_statement .= $line . "\n";
        
        if (substr($line, -1) === ';') {
            $statements[] = trim($current_statement);
            $current_statement = '';
        }
    }
    
    // Ejecutar statements
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (empty($statement)) continue;
        
        // Saltar algunos statements problemáticos
        if (preg_match('/^(SET\s+SQL_MODE|START\s+TRANSACTION|COMMIT|SET\s+time_zone|SET\s+FOREIGN_KEY_CHECKS)/i', $statement)) {
            continue;
        }
        
        try {
            $pdo->exec($statement);
        } catch (Exception $e) {
            // Log el error pero continúa
            error_log("SQL Error: " . $e->getMessage() . " - Statement: " . substr($statement, 0, 100));
        }
    }
}

function createDatabaseStructure($pdo) {
    // Crear estructura completa si no existe el archivo SQL
    
    // Tabla categories
    $pdo->exec("CREATE TABLE IF NOT EXISTS `categories` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(100) NOT NULL,
        `description` text,
        `image` varchar(255) DEFAULT NULL,
        `sort_order` int DEFAULT '0',
        `is_active` tinyint(1) DEFAULT '1',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla roles
    $pdo->exec("CREATE TABLE IF NOT EXISTS `roles` (
        `id` int NOT NULL AUTO_INCREMENT,
        `name` varchar(50) NOT NULL,
        `description` text,
        `permissions` json DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla users
    $pdo->exec("CREATE TABLE IF NOT EXISTS `users` (
        `id` int NOT NULL AUTO_INCREMENT,
        `username` varchar(50) NOT NULL,
        `email` varchar(100) NOT NULL,
        `password` varchar(255) NOT NULL,
        `full_name` varchar(100) NOT NULL,
        `phone` varchar(20) DEFAULT NULL,
        `role_id` int DEFAULT NULL,
        `is_active` tinyint(1) DEFAULT '1',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `username` (`username`),
        UNIQUE KEY `email` (`email`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla tables
    $pdo->exec("CREATE TABLE IF NOT EXISTS `tables` (
        `id` int NOT NULL AUTO_INCREMENT,
        `number` varchar(20) NOT NULL,
        `capacity` int NOT NULL,
        `status` enum('available','occupied','reserved','maintenance') DEFAULT 'available',
        `location` varchar(100) DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `number` (`number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla products
    $pdo->exec("CREATE TABLE IF NOT EXISTS `products` (
        `id` int NOT NULL AUTO_INCREMENT,
        `category_id` int DEFAULT NULL,
        `name` varchar(100) NOT NULL,
        `description` text,
        `price` decimal(10,2) NOT NULL,
        `cost` decimal(10,2) DEFAULT '0.00',
        `stock_quantity` int DEFAULT NULL,
        `low_stock_alert` int DEFAULT '10',
        `track_inventory` tinyint(1) DEFAULT '0',
        `image` varchar(255) DEFAULT NULL,
        `preparation_time` int DEFAULT '0',
        `is_available` tinyint(1) DEFAULT '1',
        `is_active` tinyint(1) DEFAULT '1',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `fk_product_category` (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla orders
    $pdo->exec("CREATE TABLE IF NOT EXISTS `orders` (
        `id` int NOT NULL AUTO_INCREMENT,
        `order_number` varchar(20) NOT NULL,
        `type` enum('dine_in','delivery','takeout') NOT NULL,
        `table_id` int DEFAULT NULL,
        `customer_name` varchar(100) DEFAULT NULL,
        `customer_phone` varchar(20) DEFAULT NULL,
        `customer_address` text,
        `customer_notes` text,
        `notes` text,
        `subtotal` decimal(10,2) NOT NULL DEFAULT '0.00',
        `tax` decimal(10,2) NOT NULL DEFAULT '0.00',
        `delivery_fee` decimal(10,2) NOT NULL DEFAULT '0.00',
        `total` decimal(10,2) NOT NULL DEFAULT '0.00',
        `discount` decimal(10,2) DEFAULT '0.00',
        `status` enum('pending','confirmed','preparing','ready','delivered','cancelled') DEFAULT 'pending',
        `payment_status` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
        `payment_method` varchar(50) DEFAULT NULL,
        `waiter_id` int DEFAULT NULL,
        `created_by` int DEFAULT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `order_number` (`order_number`),
        KEY `fk_order_table` (`table_id`),
        KEY `fk_order_waiter` (`waiter_id`),
        KEY `fk_order_creator` (`created_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla order_items
    $pdo->exec("CREATE TABLE IF NOT EXISTS `order_items` (
        `id` int NOT NULL AUTO_INCREMENT,
        `order_id` int NOT NULL,
        `product_id` int NOT NULL,
        `quantity` int NOT NULL,
        `unit_price` decimal(10,2) NOT NULL,
        `subtotal` decimal(10,2) NOT NULL,
        `notes` text,
        `status` enum('pending','preparing','ready','served') DEFAULT 'pending',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `fk_item_order` (`order_id`),
        KEY `fk_item_product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla online_orders
    $pdo->exec("CREATE TABLE IF NOT EXISTS `online_orders` (
        `id` int NOT NULL AUTO_INCREMENT,
        `order_number` varchar(20) NOT NULL,
        `customer_name` varchar(100) NOT NULL,
        `customer_phone` varchar(20) NOT NULL,
        `customer_address` text NOT NULL,
        `address_coordinates` json DEFAULT NULL,
        `address_components` json DEFAULT NULL,
        `items` json NOT NULL,
        `subtotal` decimal(10,2) NOT NULL,
        `total` decimal(10,2) NOT NULL,
        `status` enum('pending','accepted','rejected','preparing','ready','delivered') DEFAULT 'pending',
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `accepted_at` timestamp NULL DEFAULT NULL,
        `accepted_by` int DEFAULT NULL,
        `estimated_time` int DEFAULT NULL COMMENT 'Tiempo estimado en minutos',
        `rejection_reason` text COMMENT 'Motivo de rechazo',
        `started_preparing_at` timestamp NULL DEFAULT NULL,
        `ready_at` timestamp NULL DEFAULT NULL,
        `delivered_at` timestamp NULL DEFAULT NULL,
        `rejected_at` timestamp NULL DEFAULT NULL,
        `rejected_by` int DEFAULT NULL,
        `delivered_by` int DEFAULT NULL,
        `customer_notes` text,
        `customer_references` text,
        `delivery_distance` decimal(8,2) DEFAULT NULL,
        `payment_status` enum('pending','partial','paid','cancelled') DEFAULT 'pending',
        PRIMARY KEY (`id`),
        UNIQUE KEY `order_number` (`order_number`),
        KEY `idx_status` (`status`),
        KEY `idx_created_at` (`created_at`),
        KEY `idx_accepted_by` (`accepted_by`),
        KEY `idx_rejected_by` (`rejected_by`),
        KEY `idx_delivered_by` (`delivered_by`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla payments
    $pdo->exec("CREATE TABLE IF NOT EXISTS `payments` (
        `id` int NOT NULL AUTO_INCREMENT,
        `order_id` int NOT NULL,
        `method` enum('cash','card','transfer','qr') NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `reference` varchar(255) DEFAULT NULL,
        `user_id` int NOT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `order_id` (`order_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Tabla online_orders_payments
    $pdo->exec("CREATE TABLE IF NOT EXISTS `online_orders_payments` (
        `id` int NOT NULL AUTO_INCREMENT,
        `online_order_id` int NOT NULL,
        `method` enum('cash','card','transfer','qr') NOT NULL,
        `amount` decimal(10,2) NOT NULL,
        `reference` varchar(255) DEFAULT NULL,
        `user_id` int NOT NULL,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `online_order_id` (`online_order_id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla settings
    $pdo->exec("CREATE TABLE IF NOT EXISTS `settings` (
        `id` int NOT NULL AUTO_INCREMENT,
        `setting_key` varchar(100) NOT NULL,
        `setting_value` text,
        `description` text,
        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `setting_key` (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Tabla waiter_calls
    $pdo->exec("CREATE TABLE IF NOT EXISTS `waiter_calls` (
        `id` int NOT NULL AUTO_INCREMENT,
        `mesa` int NOT NULL,
        `created_at` datetime NOT NULL,
        `status` enum('pending','attended') DEFAULT 'pending',
        PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3");

    // Insertar datos básicos
    insertBasicData($pdo);
}

function insertBasicData($pdo) {
    // Verificar e insertar roles
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    $roleCount = $stmt->fetchColumn();
    
    if ($roleCount == 0) {
        $roles = [
            ['administrador', 'Acceso completo al sistema', '["all", "online_orders"]'],
            ['gerente', 'Gestión completa excepto configuración de sistema', '["orders", "online_orders", "products", "users", "reports", "tables", "kitchen", "delivery"]'],
            ['mostrador', 'Gestión de mesas y pedidos delivery', '["orders", "online_orders", "products", "tables", "kitchen", "delivery"]'],
            ['mesero', 'Gestión de mesas y pedidos', '["orders", "tables"]'],
            ['cocina', 'Visualización y actualización de pedidos', '["kitchen", "online_orders"]'],
            ['delivery', 'Gestión de entregas', '["delivery"]']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO roles (name, description, permissions) VALUES (?, ?, ?)");
        foreach ($roles as $role) {
            try {
                $stmt->execute($role);
            } catch (Exception $e) {
                error_log("Error inserting role: " . $e->getMessage());
            }
        }
    }
    
    // Verificar e insertar categorías
    $stmt = $pdo->query("SELECT COUNT(*) FROM categories");
    $catCount = $stmt->fetchColumn();
    
    if ($catCount == 0) {
        $categories = [
            ['Entradas', 'Platos de entrada y aperitivos'],
            ['Platos Principales', 'Platos principales del menú'],
            ['Postres', 'Postres y dulces'],
            ['Bebidas', 'Bebidas frías y calientes'],
            ['Bebidas Alcohólicas', 'Cervezas, vinos y licores']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
        foreach ($categories as $category) {
            try {
                $stmt->execute($category);
            } catch (Exception $e) {
                error_log("Error inserting category: " . $e->getMessage());
            }
        }
    }
    
    // Verificar e insertar mesas
    $stmt = $pdo->query("SELECT COUNT(*) FROM tables");
    $tableCount = $stmt->fetchColumn();
    
    if ($tableCount == 0) {
        $tables = [
            ['Mesa 1', 5, 'Salón principal'],
            ['Mesa 2', 2, 'Salón principal'],
            ['Mesa 3', 10, 'Salón principal'],
            ['Mesa 4', 4, 'Terraza'],
            ['Mesa 5', 2, 'Terraza']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO tables (number, capacity, location) VALUES (?, ?, ?)");
        foreach ($tables as $table) {
            try {
                $stmt->execute($table);
            } catch (Exception $e) {
                error_log("Error inserting table: " . $e->getMessage());
            }
        }
    }
    
    // Insertar configuraciones básicas
    $settings = [
        ['system_timezone', 'America/Argentina/Buenos_Aires', 'Zona horaria del sistema'],
        ['date_format', 'd/m/Y', 'Formato de fecha'],
        ['auto_logout_time', '240', 'Tiempo de auto logout (minutos)'],
        ['max_orders_per_day', '1000', 'Máximo de órdenes por día'],
        ['backup_frequency', 'daily', 'Frecuencia de respaldo'],
        ['enable_online_orders', '1', 'Habilitar pedidos online'],
        ['enable_table_reservations', '1', 'Habilitar reservas de mesa'],
        ['notification_sound', '1', 'Sonido de notificaciones'],
        ['auto_print_orders', '1', 'Imprimir órdenes automáticamente'],
        ['loyalty_program', '0', 'Programa de fidelización'],
        ['enable_discounts', '1', 'Habilitar descuentos'],
        ['enable_tips', '1', 'Habilitar propinas'],
        ['currency_symbol', '$', 'Símbolo de moneda'],
        ['decimal_places', '2', 'Lugares decimales'],
        ['order_timeout', '15', 'Tiempo límite para confirmar orden (minutos)'],
        ['kitchen_display_refresh', '5', 'Actualización pantalla cocina (segundos)'],
        ['enable_customer_feedback', '1', 'Habilitar feedback de clientes']
    ];
    
    $stmt = $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value, description) VALUES (?, ?, ?)");
    foreach ($settings as $setting) {
        try {
            $stmt->execute($setting);
        } catch (Exception $e) {
            error_log("Error inserting setting: " . $e->getMessage());
        }
    }
}

function createAdminUser($pdo, $password) {
    // Verificar si ya existe el usuario admin
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    
    if ($stmt->fetchColumn() == 0) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, full_name, role_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute(['admin', 'admin@restaurant.com', $hashedPassword, 'Administrador', 1]);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalación - Sistema de Restaurante</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem 0;
        }
        
        .install-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            max-width: 700px;
            margin: 0 auto;
        }
        
        .install-header {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            padding: 2rem;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        
        .step-indicator {
            display: flex;
            justify-content: center;
            margin-bottom: 2rem;
        }
        
        .step {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 10px;
            font-weight: bold;
        }
        
        .step.active {
            background: #667eea;
            color: white;
        }
        
        .step.completed {
            background: #28a745;
            color: white;
        }
        
        .step.pending {
            background: #e9ecef;
            color: #6c757d;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(45deg, #667eea, #764ba2);
            border: none;
        }
        
        .btn-primary:hover {
            background: linear-gradient(45deg, #5a6fd8, #6a4190);
        }

        .form-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .form-section h6 {
            color: #667eea;
            font-weight: bold;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="install-card">
            <div class="install-header">
                <h2><i class="fas fa-utensils me-2"></i>Sistema de Restaurante</h2>
                <p class="mb-0">Instalación y Configuración Inicial</p>
            </div>
            
            <div class="p-4">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step <?php echo $step >= 1 ? 'completed' : 'pending'; ?>">1</div>
                    <div class="step <?php echo $step === 2 ? 'active' : ($step > 2 ? 'completed' : 'pending'); ?>">2</div>
                    <div class="step <?php echo $step === 3 ? 'active' : ($step > 3 ? 'completed' : 'pending'); ?>">3</div>
                    <div class="step <?php echo $step === 4 ? 'active' : 'pending'; ?>">4</div>
                </div>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($step === 1): ?>
                    <h4><i class="fas fa-database me-2"></i>Paso 1: Configuración de Base de Datos</h4>
                    <p class="text-muted mb-4">Ingrese los datos de conexión a su base de datos MySQL.</p>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-server me-2"></i>Servidor de Base de Datos</label>
                            <input type="text" class="form-control" name="db_host" value="localhost" required>
                            <div class="form-text">Generalmente 'localhost' o la IP del servidor</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-database me-2"></i>Nombre de la Base de Datos</label>
                            <input type="text" class="form-control" name="db_name" placeholder="comidasm" required>
                            <div class="form-text">La base de datos debe existir y estar vacía</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-user me-2"></i>Usuario</label>
                            <input type="text" class="form-control" name="db_user" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label"><i class="fas fa-key me-2"></i>Contraseña</label>
                            <input type="password" class="form-control" name="db_pass">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-arrow-right me-2"></i>
                            Verificar Conexión
                        </button>
                    </form>
                    
                <?php elseif ($step === 2): ?>
                    <h4><i class="fas fa-cogs me-2"></i>Paso 2: Instalación de Base de Datos</h4>
                    <p class="text-muted mb-4">Se creará la estructura de la base de datos y los datos iniciales.</p>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Este proceso puede tomar unos minutos. Se crearán todas las tablas y datos básicos necesarios.
                    </div>
                    
                    <form method="POST">
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-database me-2"></i>
                            Instalar Base de Datos
                        </button>
                    </form>
                    
                <?php elseif ($step === 3): ?>
                    <h4><i class="fas fa-store me-2"></i>Paso 3: Configuración del Restaurante</h4>
                    <p class="text-muted mb-4">Configure los datos básicos de su restaurante.</p>
                    
                    <form method="POST">
                        <!-- Información Básica -->
                        <div class="form-section">
                            <h6><i class="fas fa-info-circle me-2"></i>Información Básica</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Nombre del Restaurante *</label>
                                        <input type="text" class="form-control" name="restaurant_name" placeholder="Mi Restaurante" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Teléfono *</label>
                                        <input type="text" class="form-control" name="restaurant_phone" placeholder="3482549555" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label class="form-label">Dirección *</label>
                                        <input type="text" class="form-control" name="restaurant_address" placeholder="San Martin 1333" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="restaurant_email" placeholder="info@restaurante.com">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configuración de Delivery -->
                        <div class="form-section">
                            <h6><i class="fas fa-motorcycle me-2"></i>Configuración de Delivery</h6>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Costo de Envío</label>
                                        <input type="number" class="form-control" name="delivery_fee" value="3000" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Distancia Máxima (km)</label>
                                        <input type="number" class="form-control" name="max_delivery_distance" value="10" step="0.1">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Pedido Mínimo</label>
                                        <input type="number" class="form-control" name="min_delivery_amount" value="1500" step="0.01">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">WhatsApp (sin +)</label>
                                <input type="text" class="form-control" name="whatsapp_number" placeholder="5491112345678">
                                <div class="form-text">Incluya código de país. Ejemplo: 5491112345678</div>
                            </div>
                        </div>

                        <!-- Horarios de Atención -->
                        <div class="form-section">
                            <h6><i class="fas fa-clock me-2"></i>Horarios de Atención</h6>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Hora de Apertura</label>
                                        <input type="time" class="form-control" name="opening_time" value="08:00">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Hora de Cierre</label>
                                        <input type="time" class="form-control" name="closing_time" value="23:59">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label class="form-label">Cierre de Cocina</label>
                                        <input type="time" class="form-control" name="kitchen_closing_time" value="23:59">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Configuración Fiscal y APIs -->
                        <div class="form-section">
                            <h6><i class="fas fa-cog me-2"></i>Configuración Adicional</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">IVA (%)</label>
                                        <input type="number" class="form-control" name="tax_rate" value="21" step="0.01">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Contraseña de Administrador</label>
                                        <input type="password" class="form-control" name="admin_password" value="password" required>
                                        <div class="form-text">Puede cambiarla después desde el panel</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Google Maps API Key (Opcional)</label>
                                <input type="text" class="form-control" name="google_maps_api_key" placeholder="AIzaSy...">
                                <div class="form-text">Para autocompletado de direcciones en pedidos online</div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-success btn-lg w-100">
                            <i class="fas fa-save me-2"></i>
                            Completar Instalación
                        </button>
                    </form>
                    
                <?php elseif ($step === 4): ?>
                    <div class="text-center">
                        <div class="mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                        </div>
                        
                        <h4>¡Instalación Completada!</h4>
                        <p class="text-muted mb-4">Su sistema de restaurante ha sido instalado exitosamente.</p>
                        
                        <div class="alert alert-success">
                            <h6><i class="fas fa-user-shield me-2"></i>Datos de Acceso Administrador:</h6>
                            <p class="mb-1"><strong>Usuario:</strong> admin</p>
                            <p class="mb-0"><strong>Contraseña:</strong> <?php echo htmlspecialchars($_POST['admin_password'] ?? 'password'); ?></p>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>¡IMPORTANTE!</strong> Elimine el archivo <code>install.php</code> por seguridad.
                        </div>
                        
                        <div class="d-grid gap-3">
                            <a href="admin/login.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>
                                Acceder al Panel de Administración
                            </a>
                            <a href="index.php" class="btn btn-outline-primary">
                                <i class="fas fa-home me-2"></i>
                                Ver Menú Público
                            </a>
                        </div>
                        
                        <div class="mt-4">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                Puede crear más usuarios y configurar productos desde el panel de administración
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>