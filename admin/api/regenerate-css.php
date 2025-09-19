<?php
/**
 * API para regenerar archivos CSS dinámicos
 * admin/api/regenerate-css.php
 * 
 * Endpoint para forzar la regeneración de archivos CSS del sistema de temas
 */

// Headers para API JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Configurar manejo de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Función para respuesta JSON
function jsonResponse($success, $message, $data = null) {
    $response = array(
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    );
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

try {
    // Verificar método de solicitud
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Método no permitido. Use POST.');
    }
    
    // Incluir archivos necesarios
    require_once '../../config/config.php';
    require_once '../../config/database.php';
    require_once '../../config/auth.php';
    
    // Verificar autenticación
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        jsonResponse(false, 'No autenticado. Inicie sesión.');
    }
    
    // Verificar permisos (solo administradores pueden regenerar CSS)
    if (!$auth->hasPermission('settings') && $_SESSION['role_name'] !== 'administrador') {
        jsonResponse(false, 'Permisos insuficientes. Solo administradores pueden regenerar CSS.');
    }
    
    // Verificar si existe el archivo theme.php
    $theme_file = '../../config/theme.php';
    if (!file_exists($theme_file)) {
        // Crear archivo theme.php básico si no existe
        createBasicThemeFile($theme_file);
    }
    
    require_once $theme_file;
    
    // Obtener datos de entrada
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(false, 'Formato JSON inválido en la solicitud.');
    }
    
    $action = $data['action'] ?? '';
    
    if ($action !== 'regenerate') {
        jsonResponse(false, 'Acción no válida. Use "regenerate".');
    }
    
    // Conectar a base de datos
    $database = new Database();
    $db = $database->getConnection();
    
    if (!$db) {
        jsonResponse(false, 'Error de conexión a la base de datos.');
    }
    
    // Crear instancia del ThemeManager
    $theme_manager = new ThemeManager($db);
    
    // Información de archivos CSS
    $css_files = array();
    $errors = array();
    
    // 1. Regenerar archivo CSS dinámico principal
    try {
        $dynamic_css_path = $theme_manager->generateCSSFile();
        $css_files['dynamic'] = array(
            'path' => $dynamic_css_path,
            'size' => file_exists($dynamic_css_path) ? filesize($dynamic_css_path) : 0,
            'modified' => file_exists($dynamic_css_path) ? filemtime($dynamic_css_path) : null
        );
    } catch (Exception $e) {
        $errors[] = 'Error generando CSS dinámico: ' . $e->getMessage();
    }
    
    // 2. Verificar archivo generate-theme.php
    $generate_theme_path = '../../assets/css/generate-theme.php';
    if (file_exists($generate_theme_path)) {
        $css_files['generator'] = array(
            'path' => $generate_theme_path,
            'size' => filesize($generate_theme_path),
            'modified' => filemtime($generate_theme_path)
        );
    } else {
        $errors[] = 'Archivo generate-theme.php no encontrado';
    }
    
    // 3. Limpiar cache si existe
    try {
        // Limpiar cache de opcache si está habilitado
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
        
        // Limpiar cache de theme manager
        if (method_exists($theme_manager, 'clearCache')) {
            $theme_manager->clearCache();
        }
    } catch (Exception $e) {
        $errors[] = 'Warning: Error limpiando cache: ' . $e->getMessage();
    }
    
    // 4. Validar integridad del tema
    $theme_validation = array();
    try {
        if (method_exists($theme_manager, 'validateThemeIntegrity')) {
            $validation_result = $theme_manager->validateThemeIntegrity();
            $theme_validation = $validation_result;
        }
    } catch (Exception $e) {
        $errors[] = 'Error validando tema: ' . $e->getMessage();
    }
    
    // 5. Obtener estadísticas del tema
    $theme_stats = array();
    try {
        if (method_exists($theme_manager, 'getThemeStatistics')) {
            $theme_stats = $theme_manager->getThemeStatistics();
        } else {
            $current_theme = $theme_manager->getThemeSettings();
            $theme_stats = array(
                'total_settings' => count($current_theme),
                'colors_count' => count(array_filter(array_keys($current_theme), function($key) {
                    return strpos($key, '_color') !== false;
                }))
            );
        }
    } catch (Exception $e) {
        $errors[] = 'Error obteniendo estadísticas: ' . $e->getMessage();
    }
    
    // 6. Crear backup automático si es posible
    $backup_info = null;
    try {
        if (method_exists($theme_manager, 'createBackup')) {
            $backup_result = $theme_manager->createBackup();
            if ($backup_result['success']) {
                $backup_info = $backup_result;
            }
        }
    } catch (Exception $e) {
        $errors[] = 'Warning: Error creando backup: ' . $e->getMessage();
    }
    
    // Preparar respuesta
    $response_data = array(
        'css_files' => $css_files,
        'theme_validation' => $theme_validation,
        'theme_stats' => $theme_stats,
        'backup_info' => $backup_info,
        'errors' => $errors,
        'regeneration_time' => date('Y-m-d H:i:s')
    );
    
    // Determinar mensaje de respuesta
    if (empty($errors)) {
        $message = 'CSS regenerado correctamente. Archivos actualizados.';
    } else {
        $critical_errors = array_filter($errors, function($error) {
            return strpos($error, 'Warning:') !== 0;
        });
        
        if (empty($critical_errors)) {
            $message = 'CSS regenerado con advertencias menores.';
        } else {
            $message = 'CSS regenerado con errores: ' . implode('; ', $critical_errors);
        }
    }
    
    jsonResponse(true, $message, $response_data);
    
} catch (Exception $e) {
    error_log("Error in regenerate-css.php: " . $e->getMessage());
    jsonResponse(false, 'Error interno del servidor: ' . $e->getMessage());
}

/**
 * Crear archivo theme.php básico si no existe
 */
function createBasicThemeFile($theme_file) {
    $basic_theme_content = '<?php
/**
 * Clase ThemeManager básica de respaldo
 * Generada automáticamente por regenerate-css.php
 */
class ThemeManager {
    private $settings;
    private $db;
    
    public function __construct($database = null) {
        $this->db = $database;
        $this->settings = $this->getThemeSettings();
    }
    
    public function getThemeSettings() {
        if ($this->db) {
            try {
                $query = "SELECT setting_key, setting_value FROM theme_settings";
                $stmt = $this->db->prepare($query);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $theme = array();
                foreach ($results as $row) {
                    $theme[$row["setting_key"]] = $row["setting_value"];
                }
                return array_merge($this->getDefaultTheme(), $theme);
            } catch (Exception $e) {
                return $this->getDefaultTheme();
            }
        }
        return $this->getDefaultTheme();
    }
    
    public function getDefaultTheme() {
        return array(
            "primary_color" => "#667eea",
            "secondary_color" => "#764ba2", 
            "accent_color" => "#ff6b6b",
            "success_color" => "#28a745",
            "warning_color" => "#ffc107",
            "danger_color" => "#dc3545",
            "info_color" => "#17a2b8",
            "text_primary" => "#212529",
            "text_secondary" => "#6c757d",
            "text_muted" => "#868e96",
            "text_white" => "#ffffff",
            "bg_body" => "#f8f9fa",
            "bg_white" => "#ffffff",
            "bg_light" => "#f8f9fa",
            "font_family_primary" => "\'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif",
            "font_size_base" => "16px",
            "font_size_small" => "14px",
            "font_size_large" => "18px",
            "border_radius_base" => "8px",
            "border_radius_large" => "15px",
            "sidebar_width" => "280px",
            "shadow_base" => "0 5px 15px rgba(0, 0, 0, 0.08)"
        );
    }
    
    public function generateCSS() {
        $theme = $this->settings;
        $css = ":root {\n";
        foreach ($theme as $key => $value) {
            $css_var = "--" . str_replace("_", "-", $key);
            $css .= "    {$css_var}: {$value};\n";
        }
        $css .= "}\n\n";
        return $css . $this->getBaseCSS();
    }
    
    public function generateCSSFile() {
        $css_content = $this->generateCSS();
        $css_file = __DIR__ . "/../assets/css/dynamic-theme.css";
        
        $dir = dirname($css_file);
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        
        file_put_contents($css_file, $css_content);
        return $css_file;
    }
    
    private function getBaseCSS() {
        return "
/* CSS Base Generado Automáticamente */
body {
    font-family: var(--font-family-primary);
    font-size: var(--font-size-base);
    background: var(--bg-body);
    color: var(--text-primary);
    line-height: 1.6;
}

.card {
    background: var(--bg-white) !important;
    color: var(--text-primary) !important;
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
}

.btn-primary {
    background: var(--primary-color) !important;
    border-color: var(--primary-color) !important;
    color: var(--text-white) !important;
}

.text-primary { color: var(--primary-color) !important; }
.text-success { color: var(--success-color) !important; }
.text-danger { color: var(--danger-color) !important; }
.text-muted { color: var(--text-muted) !important; }
        ";
    }
}
?>';

    // Crear directorio si no existe
    $dir = dirname($theme_file);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    file_put_contents($theme_file, $basic_theme_content);
}
?>