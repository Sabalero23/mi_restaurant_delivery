<?php
// config/config.php - Generado automáticamente el 2025-09-27 01:36:56

// Configuración de base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'restaurant');
define('DB_USER', 'restaurant');
define('DB_PASS', 'f2367cc9cb499');

// Configuración del sitio
define('BASE_URL', 'https://comidas.ordenes.com.ar/');
define('UPLOAD_PATH', 'uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// Configuración de seguridad
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);
session_set_cookie_params(0, '/', '', true, true);

// Configuración regional
date_default_timezone_set('America/Argentina/Buenos_Aires');
setlocale(LC_TIME, 'es_AR.UTF-8', 'es_ES.UTF-8', 'Spanish');

// Configuración de errores (cambiar en producción)
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
