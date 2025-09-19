<?php
/**
 * Sistema de Gestión de Temas
 * config/theme.php
 * 
 * Clase para manejar la configuración y generación de temas dinámicos
 * Versión corregida y optimizada
 */

class ThemeManager {
    private $db;
    private $current_theme;
    private $cache_prefix = 'theme_';
    private $css_file_path;
    
    public function __construct($database = null) {
        $this->db = $database;
        $this->css_file_path = __DIR__ . '/../assets/css/dynamic-theme.css';
        $this->loadCurrentTheme();
    }
    
    /**
     * Cargar tema actual desde la base de datos o usar valores por defecto
     */
    private function loadCurrentTheme() {
        try {
            $this->current_theme = $this->getThemeSettings();
        } catch (Exception $e) {
            error_log("Error loading theme: " . $e->getMessage());
            $this->current_theme = $this->getDefaultTheme();
        }
    }
    
    /**
     * Obtener configuración del tema desde la base de datos
     */
    public function getThemeSettings() {
        if ($this->db) {
            try {
                $query = "SELECT setting_key, setting_value FROM theme_settings ORDER BY setting_key";
                $stmt = $this->db->prepare($query);
                $stmt->execute();
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $theme = array();
                foreach ($results as $row) {
                    $theme[$row['setting_key']] = $row['setting_value'];
                }
                
                // Combinar con valores por defecto para campos faltantes
                return array_merge($this->getDefaultTheme(), $theme);
                
            } catch (PDOException $e) {
                error_log("Database error in getThemeSettings: " . $e->getMessage());
                return $this->getDefaultTheme();
            }
        }
        
        return $this->getDefaultTheme();
    }
    
    /**
     * Tema por defecto con todos los valores necesarios
     */
    public function getDefaultTheme() {
        return array(
            // Colores principales
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'accent_color' => '#ff6b6b',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8',
            'dark_color' => '#343a40',
            'light_color' => '#f8f9fa',
            
            // Colores de texto
            'text_primary' => '#212529',
            'text_secondary' => '#6c757d',
            'text_muted' => '#868e96',
            'text_white' => '#ffffff',
            
            // Colores de fondo
            'bg_body' => '#f8f9fa',
            'bg_white' => '#ffffff',
            'bg_light' => '#f8f9fa',
            'bg_dark' => '#343a40',
            
            // Tipografías
            'font_family_primary' => "'Segoe UI', Tahoma, Geneva, Verdana, sans-serif",
            'font_family_secondary' => "'Inter', -apple-system, BlinkMacSystemFont, sans-serif",
            'font_family_monospace' => "'Courier New', monospace",
            
            // Tamaños de fuente
            'font_size_base' => '16px',
            'font_size_small' => '14px',
            'font_size_large' => '18px',
            'font_size_xl' => '24px',
            'font_size_xxl' => '32px',
            
            // Espaciado y bordes
            'border_radius_base' => '8px',
            'border_radius_large' => '15px',
            'border_radius_small' => '4px',
            'border_radius_pill' => '50px',
            
            // Sombras
            'shadow_base' => '0 5px 15px rgba(0, 0, 0, 0.08)',
            'shadow_large' => '0 10px 30px rgba(0, 0, 0, 0.15)',
            'shadow_small' => '0 2px 8px rgba(0, 0, 0, 0.05)',
            
            // Layout
            'sidebar_width' => '280px',
            'sidebar_bg' => 'linear-gradient(180deg, #667eea 0%, #764ba2 100%)',
            
            // Animaciones
            'transition_base' => '0.3s ease',
            'transition_fast' => '0.15s ease',
            'transition_slow' => '0.5s ease',
            
            // Breakpoints
            'breakpoint_sm' => '576px',
            'breakpoint_md' => '768px',
            'breakpoint_lg' => '992px',
            'breakpoint_xl' => '1200px'
        );
    }
    
    /**
     * Actualizar configuración del tema
     */
    public function updateTheme($new_settings) {
        if (!$this->db) {
            return array('success' => false, 'message' => 'Base de datos no disponible');
        }
        
        // Validar configuraciones
        $validated_settings = $this->validateSettings($new_settings);
        if (!$validated_settings['valid']) {
            return array('success' => false, 'message' => $validated_settings['error']);
        }
        
        try {
            $this->db->beginTransaction();
            
            foreach ($new_settings as $key => $value) {
                // Validar clave
                if (!$this->isValidSettingKey($key)) {
                    continue;
                }
                
                $query = "INSERT INTO theme_settings (setting_key, setting_value, updated_at) 
                         VALUES (?, ?, NOW()) 
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()";
                $stmt = $this->db->prepare($query);
                $stmt->execute(array($key, $value));
            }
            
            $this->db->commit();
            
            // Actualizar tema en memoria
            $this->current_theme = array_merge($this->current_theme, $new_settings);
            
            // Regenerar archivo CSS
            $this->generateCSSFile();
            
            return array('success' => true, 'message' => 'Tema actualizado correctamente');
            
        } catch (PDOException $e) {
            $this->db->rollback();
            error_log("Error updating theme: " . $e->getMessage());
            return array('success' => false, 'message' => 'Error al actualizar tema: ' . $e->getMessage());
        }
    }
    
    /**
     * Validar configuraciones de entrada
     */
    private function validateSettings($settings) {
        foreach ($settings as $key => $value) {
            // Validar colores hexadecimales
            if (strpos($key, '_color') !== false) {
                if (!$this->isValidHexColor($value)) {
                    return array('valid' => false, 'error' => "Color hexadecimal inválido: $value");
                }
            }
            
            // Validar tamaños de fuente
            if (strpos($key, 'font_size') !== false) {
                if (!$this->isValidFontSize($value)) {
                    return array('valid' => false, 'error' => "Tamaño de fuente inválido: $value");
                }
            }
            
            // Validar medidas CSS
            if (in_array($key, array('sidebar_width', 'border_radius_base', 'border_radius_large'))) {
                if (!$this->isValidCSSMeasure($value)) {
                    return array('valid' => false, 'error' => "Medida CSS inválida: $value");
                }
            }
        }
        
        return array('valid' => true);
    }
    
    /**
     * Validar si una clave de configuración es válida
     */
    private function isValidSettingKey($key) {
        $valid_keys = array_keys($this->getDefaultTheme());
        return in_array($key, $valid_keys);
    }
    
    /**
     * Validar color hexadecimal
     */
    private function isValidHexColor($color) {
        return preg_match('/^#[a-f0-9]{6}$/i', $color);
    }
    
    /**
     * Validar tamaño de fuente
     */
    private function isValidFontSize($size) {
        return preg_match('/^\d+(\.\d+)?(px|em|rem|%)$/', $size);
    }
    
    /**
     * Validar medida CSS
     */
    private function isValidCSSMeasure($measure) {
        return preg_match('/^\d+(\.\d+)?(px|em|rem|%|vh|vw)$/', $measure);
    }
    
    /**
     * Generar CSS completo basado en el tema actual
     */
    public function generateCSS() {
        $theme = $this->current_theme;
        
        // Generar variables CSS
        $css = ":root {\n";
        foreach ($theme as $key => $value) {
            $css_var = '--' . str_replace('_', '-', $key);
            $css .= "    {$css_var}: {$value};\n";
        }
        $css .= "}\n\n";
        
        // Agregar CSS base
        $css .= $this->getBaseCSS();
        
        return $css;
    }
    
    /**
     * CSS base del sistema
     */
    private function getBaseCSS() {
        return "
/* ========================================
   CSS BASE DEL SISTEMA DE TEMAS
   ======================================== */

/* Reset y configuración base */
* {
    box-sizing: border-box;
}

body {
    font-family: var(--font-family-primary);
    font-size: var(--font-size-base);
    background: var(--bg-body) !important;
    color: var(--text-primary) !important;
    line-height: 1.6;
    margin: 0;
    padding: 0;
}

/* CARDS - Configuración principal */
.card {
    background: var(--bg-white) !important;
    color: var(--text-primary) !important;
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-base);
    overflow: hidden;
    transition: var(--transition-base);
    margin-bottom: 1rem;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-large);
}

.card-header {
    background: var(--bg-light) !important;
    color: var(--text-primary) !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
    border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
    padding: 1.5rem;
    font-weight: 600;
}

.card-body {
    background: var(--bg-white) !important;
    color: var(--text-primary) !important;
    padding: 1.5rem;
}

.card-footer {
    background: var(--bg-light) !important;
    color: var(--text-primary) !important;
    border-top: 1px solid rgba(0, 0, 0, 0.125);
    padding: 1rem 1.5rem;
}

.card-title {
    color: var(--text-primary) !important;
    margin-bottom: 1rem;
}

.card-text {
    color: var(--text-primary) !important;
}

/* BOTONES */
.btn {
    display: inline-block;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: var(--border-radius-base);
    font-weight: 500;
    text-decoration: none;
    text-align: center;
    cursor: pointer;
    transition: var(--transition-base);
    font-family: inherit;
    font-size: var(--font-size-base);
    line-height: 1.5;
}

.btn:hover {
    transform: translateY(-1px);
    text-decoration: none;
}

.btn-primary {
    background: var(--primary-color) !important;
    color: var(--text-white) !important;
    border-color: var(--primary-color) !important;
}

.btn-primary:hover {
    background: var(--secondary-color) !important;
    border-color: var(--secondary-color) !important;
    color: var(--text-white) !important;
    box-shadow: var(--shadow-base);
}

.btn-secondary {
    background: var(--text-secondary) !important;
    color: var(--text-white) !important;
    border-color: var(--text-secondary) !important;
}

.btn-success {
    background: var(--success-color) !important;
    color: var(--text-white) !important;
    border-color: var(--success-color) !important;
}

.btn-warning {
    background: var(--warning-color) !important;
    color: var(--text-primary) !important;
    border-color: var(--warning-color) !important;
}

.btn-danger {
    background: var(--danger-color) !important;
    color: var(--text-white) !important;
    border-color: var(--danger-color) !important;
}

.btn-info {
    background: var(--info-color) !important;
    color: var(--text-white) !important;
    border-color: var(--info-color) !important;
}

.btn-light {
    background: var(--light-color) !important;
    color: var(--text-primary) !important;
    border-color: var(--light-color) !important;
}

.btn-dark {
    background: var(--dark-color) !important;
    color: var(--text-white) !important;
    border-color: var(--dark-color) !important;
}

/* Botones outline */
.btn-outline-primary {
    background: transparent !important;
    color: var(--primary-color) !important;
    border: 2px solid var(--primary-color) !important;
}

.btn-outline-primary:hover {
    background: var(--primary-color) !important;
    color: var(--text-white) !important;
}

.btn-outline-secondary {
    background: transparent !important;
    color: var(--text-secondary) !important;
    border: 2px solid var(--text-secondary) !important;
}

.btn-outline-secondary:hover {
    background: var(--text-secondary) !important;
    color: var(--text-white) !important;
}

.btn-outline-success {
    background: transparent !important;
    color: var(--success-color) !important;
    border: 2px solid var(--success-color) !important;
}

.btn-outline-success:hover {
    background: var(--success-color) !important;
    color: var(--text-white) !important;
}

/* FORMULARIOS */
.form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius-base);
    font-family: inherit;
    font-size: var(--font-size-base);
    transition: var(--transition-base);
    background: var(--bg-white) !important;
    color: var(--text-primary) !important;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    background: var(--bg-white) !important;
    color: var(--text-primary) !important;
}

.form-control::placeholder {
    color: var(--text-muted) !important;
}

.form-select {
    background: var(--bg-white) !important;
    color: var(--text-primary) !important;
    border: 1px solid #dee2e6;
    border-radius: var(--border-radius-base);
    padding: 0.75rem 1rem;
    font-size: var(--font-size-base);
}

.form-select:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
    outline: none;
}

.form-label {
    font-weight: 500;
    color: var(--text-primary) !important;
    margin-bottom: 0.5rem;
    display: block;
}

.form-text {
    color: var(--text-muted) !important;
    font-size: var(--font-size-small);
}

/* ALERTAS */
.alert {
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius-base);
    border: none;
    margin-bottom: 1rem;
    color: var(--text-primary) !important;
}

.alert-success {
    background: rgba(40, 167, 69, 0.1) !important;
    color: var(--success-color) !important;
    border-left: 4px solid var(--success-color);
}

.alert-warning {
    background: rgba(255, 193, 7, 0.1) !important;
    color: #856404 !important;
    border-left: 4px solid var(--warning-color);
}

.alert-danger {
    background: rgba(220, 53, 69, 0.1) !important;
    color: var(--danger-color) !important;
    border-left: 4px solid var(--danger-color);
}

.alert-info {
    background: rgba(23, 162, 184, 0.1) !important;
    color: var(--info-color) !important;
    border-left: 4px solid var(--info-color);
}

/* BADGES */
.badge {
    border-radius: var(--border-radius-pill);
    padding: 0.5rem 0.75rem;
    font-weight: 600;
    font-size: 0.875em;
}

.badge.bg-primary {
    background: var(--primary-color) !important;
    color: var(--text-white) !important;
}

.badge.bg-success {
    background: var(--success-color) !important;
    color: var(--text-white) !important;
}

.badge.bg-warning {
    background: var(--warning-color) !important;
    color: var(--text-primary) !important;
}

.badge.bg-danger {
    background: var(--danger-color) !important;
    color: var(--text-white) !important;
}

.badge.bg-info {
    background: var(--info-color) !important;
    color: var(--text-white) !important;
}

/* MODALES */
.modal-content {
    background: var(--bg-white) !important;
    color: var(--text-primary) !important;
    border: none;
    border-radius: var(--border-radius-large);
    box-shadow: var(--shadow-large);
}

.modal-header {
    background: var(--bg-light) !important;
    color: var(--text-primary) !important;
    border-bottom: 1px solid #dee2e6;
    border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
    padding: 1.5rem;
}

.modal-title {
    color: var(--text-primary) !important;
}

.modal-body {
    padding: 1.5rem;
    color: var(--text-primary) !important;
    background: var(--bg-white) !important;
}

.modal-footer {
    background: var(--bg-white) !important;
    border-top: 1px solid #dee2e6;
    padding: 1rem 1.5rem;
    border-radius: 0 0 var(--border-radius-large) var(--border-radius-large);
}

/* TABLAS */
.table {
    width: 100%;
    border-collapse: collapse;
    background: var(--bg-white) !important;
    color: var(--text-primary) !important;
}

.table th,
.table td {
    padding: 0.75rem;
    border-bottom: 1px solid #dee2e6;
    color: var(--text-primary) !important;
}

.table th {
    background: var(--bg-light) !important;
    font-weight: 600;
    color: var(--text-primary) !important;
    border-top: none;
}

.table-hover tbody tr:hover {
    background: rgba(0, 0, 0, 0.02) !important;
}

.table-striped tbody tr:nth-of-type(odd) {
    background: rgba(0, 0, 0, 0.01) !important;
}

/* NAVEGACIÓN */
.nav-tabs {
    border-bottom: none;
    background: var(--bg-light) !important;
    border-radius: var(--border-radius-large) var(--border-radius-large) 0 0;
}

.nav-tabs .nav-link {
    border-radius: 0;
    border: none;
    color: var(--text-muted) !important;
    font-weight: 600;
    padding: 1.25rem 1.5rem;
    background: transparent;
    transition: var(--transition-base);
}

.nav-tabs .nav-link.active {
    background: var(--bg-white) !important;
    color: var(--primary-color) !important;
    border-bottom: 3px solid var(--primary-color);
}

.nav-tabs .nav-link:hover {
    color: var(--primary-color) !important;
}

.tab-content {
    background: var(--bg-white) !important;
    padding: 2rem;
    border-radius: 0 0 var(--border-radius-large) var(--border-radius-large);
    color: var(--text-primary) !important;
}

/* SIDEBAR */
.sidebar {
    width: var(--sidebar-width);
    background: var(--sidebar-bg);
    color: var(--text-white) !important;
    transition: var(--transition-base);
    min-height: 100vh;
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

/* UTILIDADES DE COLOR */
.text-primary { color: var(--primary-color) !important; }
.text-secondary { color: var(--text-secondary) !important; }
.text-success { color: var(--success-color) !important; }
.text-warning { color: #856404 !important; }
.text-danger { color: var(--danger-color) !important; }
.text-info { color: var(--info-color) !important; }
.text-muted { color: var(--text-muted) !important; }
.text-white { color: var(--text-white) !important; }

.bg-primary { background: var(--primary-color) !important; color: var(--text-white) !important; }
.bg-secondary { background: var(--text-secondary) !important; color: var(--text-white) !important; }
.bg-success { background: var(--success-color) !important; color: var(--text-white) !important; }
.bg-warning { background: var(--warning-color) !important; color: var(--text-primary) !important; }
.bg-danger { background: var(--danger-color) !important; color: var(--text-white) !important; }
.bg-info { background: var(--info-color) !important; color: var(--text-white) !important; }
.bg-light { background: var(--bg-light) !important; color: var(--text-primary) !important; }
.bg-dark { background: var(--dark-color) !important; color: var(--text-white) !important; }
.bg-white { background: var(--bg-white) !important; color: var(--text-primary) !important; }

/* RESPONSIVE */
@media (max-width: 991.98px) {
    .sidebar {
        width: 100%;
        max-width: 350px;
        transform: translateX(-100%);
    }
    
    .sidebar.show {
        transform: translateX(0);
    }
    
    .main-content {
        margin-left: 0;
    }
}

@media (max-width: 576px) {
    .card-body {
        padding: 1rem;
    }
    
    .modal-dialog {
        margin: 0.5rem;
    }
    
    .btn {
        padding: 0.5rem 1rem;
        font-size: var(--font-size-small);
    }
    
    .table-responsive {
        font-size: var(--font-size-small);
    }
}

/* ANIMACIONES Y TRANSICIONES */
.fade-in {
    animation: fadeIn var(--transition-base);
}

.slide-in {
    animation: slideIn var(--transition-base);
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

@keyframes slideIn {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* SCROLLBAR PERSONALIZADO */
::-webkit-scrollbar {
    width: 8px;
}

::-webkit-scrollbar-track {
    background: var(--bg-light);
}

::-webkit-scrollbar-thumb {
    background: var(--text-muted);
    border-radius: var(--border-radius-base);
}

::-webkit-scrollbar-thumb:hover {
    background: var(--primary-color);
}

/* TOASTS */
.toast {
    border-radius: var(--border-radius-base);
    box-shadow: var(--shadow-base);
}

.toast-body {
    color: var(--text-white) !important;
}

/* BREADCRUMBS */
.breadcrumb {
    background: var(--bg-light) !important;
    border-radius: var(--border-radius-base);
    padding: 0.75rem 1rem;
}

.breadcrumb-item {
    color: var(--text-muted) !important;
}

.breadcrumb-item.active {
    color: var(--text-primary) !important;
}

.breadcrumb-item + .breadcrumb-item::before {
    color: var(--text-muted) !important;
}
";
    }
    
    /**
     * Generar archivo CSS y guardarlo
     */
    public function generateCSSFile() {
        $css_content = $this->generateCSS();
        
        // Crear directorio si no existe
        $dir = dirname($this->css_file_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        // Escribir archivo CSS
        $result = file_put_contents($this->css_file_path, $css_content);
        
        if ($result === false) {
            throw new Exception("No se pudo escribir el archivo CSS en: " . $this->css_file_path);
        }
        
        return $this->css_file_path;
    }
    
    /**
     * Obtener temas predefinidos
     */
    public function getPresetThemes() {
    return array(
        // TEMAS BÁSICOS
        'default' => array(
            'name' => 'Tema Predeterminado',
            'primary_color' => '#667eea',
            'secondary_color' => '#764ba2',
            'accent_color' => '#ff6b6b',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8'
        ),
        'dark' => array(
            'name' => 'Tema Oscuro',
            'primary_color' => '#343a40',
            'secondary_color' => '#495057',
            'accent_color' => '#fd7e14',
            'bg_body' => '#212529',
            'bg_white' => '#343a40',
            'bg_light' => '#495057',
            'text_primary' => '#ffffff',
            'text_secondary' => '#adb5bd',
            'text_muted' => '#6c757d'
        ),
        
        // TEMAS POR COLORES
        'blue' => array(
            'name' => 'Azul Profesional',
            'primary_color' => '#007bff',
            'secondary_color' => '#6610f2',
            'accent_color' => '#e83e8c',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8'
        ),
        'green' => array(
            'name' => 'Verde Natura',
            'primary_color' => '#28a745',
            'secondary_color' => '#20c997',
            'accent_color' => '#17a2b8',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8'
        ),
        'purple' => array(
            'name' => 'Morado Elegante',
            'primary_color' => '#6f42c1',
            'secondary_color' => '#e83e8c',
            'accent_color' => '#fd7e14',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8'
        ),
        'orange' => array(
            'name' => 'Naranja Vibrante',
            'primary_color' => '#fd7e14',
            'secondary_color' => '#e83e8c',
            'accent_color' => '#6f42c1',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8'
        ),
        'red' => array(
            'name' => 'Rojo Intenso',
            'primary_color' => '#dc3545',
            'secondary_color' => '#e83e8c',
            'accent_color' => '#fd7e14',
            'success_color' => '#28a745',
            'warning_color' => '#ffc107',
            'danger_color' => '#dc3545',
            'info_color' => '#17a2b8'
        ),
        
        // TEMAS TEMÁTICOS
        'coral' => array(
            'name' => 'Coral Tropical',
            'primary_color' => '#ff6b6b',
            'secondary_color' => '#ff8e8e',
            'accent_color' => '#4ecdc4',
            'success_color' => '#26de81',
            'warning_color' => '#fed330',
            'danger_color' => '#fd5068',
            'info_color' => '#45aaf2'
        ),
        'forest' => array(
            'name' => 'Bosque Natural',
            'primary_color' => '#2d5016',
            'secondary_color' => '#4a7c59',
            'accent_color' => '#6a994e',
            'success_color' => '#386641',
            'warning_color' => '#bc6c25',
            'danger_color' => '#d62828',
            'info_color' => '#277da1'
        ),
        'ocean' => array(
            'name' => 'Océano Profundo',
            'primary_color' => '#0077be',
            'secondary_color' => '#00a8cc',
            'accent_color' => '#7209b7',
            'success_color' => '#08a045',
            'warning_color' => '#f18f01',
            'danger_color' => '#c73e1d',
            'info_color' => '#4361ee'
        ),
        'sunset' => array(
            'name' => 'Atardecer',
            'primary_color' => '#f72585',
            'secondary_color' => '#b5179e',
            'accent_color' => '#7209b7',
            'success_color' => '#2a9d8f',
            'warning_color' => '#e9c46a',
            'danger_color' => '#e76f51',
            'info_color' => '#264653'
        ),
        'coffee' => array(
            'name' => 'Café Acogedor',
            'primary_color' => '#8b4513',
            'secondary_color' => '#a0522d',
            'accent_color' => '#daa520',
            'success_color' => '#6b8e23',
            'warning_color' => '#ff8c00',
            'danger_color' => '#dc143c',
            'info_color' => '#4682b4'
        ),
        'mint' => array(
            'name' => 'Menta Fresca',
            'primary_color' => '#00b894',
            'secondary_color' => '#00cec9',
            'accent_color' => '#6c5ce7',
            'success_color' => '#00b894',
            'warning_color' => '#fdcb6e',
            'danger_color' => '#e17055',
            'info_color' => '#0984e3'
        ),
        
        // TEMAS GASTRONÓMICOS
        'italian' => array(
            'name' => 'Italiano Clásico',
            'primary_color' => '#d4a574',
            'secondary_color' => '#8b0000',
            'accent_color' => '#228b22',
            'success_color' => '#228b22',
            'warning_color' => '#ffd700',
            'danger_color' => '#dc143c',
            'info_color' => '#4169e1'
        ),
        'mexican' => array(
            'name' => 'Mexicano Picante',
            'primary_color' => '#c8102e',
            'secondary_color' => '#ff6347',
            'accent_color' => '#ffd700',
            'success_color' => '#228b22',
            'warning_color' => '#ff8c00',
            'danger_color' => '#b22222',
            'info_color' => '#4682b4'
        ),
        'japanese' => array(
            'name' => 'Japonés Zen',
            'primary_color' => '#c8102e',
            'secondary_color' => '#2f4f4f',
            'accent_color' => '#daa520',
            'success_color' => '#228b22',
            'warning_color' => '#ff8c00',
            'danger_color' => '#dc143c',
            'info_color' => '#4682b4'
        ),
        'mediterranean' => array(
            'name' => 'Mediterráneo',
            'primary_color' => '#4682b4',
            'secondary_color' => '#20b2aa',
            'accent_color' => '#ffd700',
            'success_color' => '#32cd32',
            'warning_color' => '#ffa500',
            'danger_color' => '#dc143c',
            'info_color' => '#4169e1'
        ),
        
        // TEMAS POR AMBIENTE
        'romantic' => array(
            'name' => 'Romántico',
            'primary_color' => '#c8a2c8',
            'secondary_color' => '#dda0dd',
            'accent_color' => '#ffc0cb',
            'success_color' => '#98fb98',
            'warning_color' => '#f0e68c',
            'danger_color' => '#f08080',
            'info_color' => '#87ceeb'
        ),
        'modern' => array(
            'name' => 'Moderno',
            'primary_color' => '#2c3e50',
            'secondary_color' => '#34495e',
            'accent_color' => '#e74c3c',
            'success_color' => '#27ae60',
            'warning_color' => '#f39c12',
            'danger_color' => '#e74c3c',
            'info_color' => '#3498db'
        ),
        'vintage' => array(
            'name' => 'Vintage Retro',
            'primary_color' => '#8b4513',
            'secondary_color' => '#cd853f',
            'accent_color' => '#b8860b',
            'success_color' => '#6b8e23',
            'warning_color' => '#daa520',
            'danger_color' => '#a0522d',
            'info_color' => '#4682b4'
        ),
        'festive' => array(
            'name' => 'Festivo',
            'primary_color' => '#ff1493',
            'secondary_color' => '#ffd700',
            'accent_color' => '#00ff7f',
            'success_color' => '#32cd32',
            'warning_color' => '#ffa500',
            'danger_color' => '#dc143c',
            'info_color' => '#00bfff'
        ),
        
        // TEMAS ESTACIONALES
        'spring' => array(
            'name' => 'Primavera',
            'primary_color' => '#7fb069',
            'secondary_color' => '#c9df8a',
            'accent_color' => '#f7931e',
            'success_color' => '#7fb069',
            'warning_color' => '#f7dc6f',
            'danger_color' => '#e74c3c',
            'info_color' => '#5dade2'
        ),
        'summer' => array(
            'name' => 'Verano',
            'primary_color' => '#ff6b35',
            'secondary_color' => '#f7931e',
            'accent_color' => '#ffd23f',
            'success_color' => '#27ae60',
            'warning_color' => '#f39c12',
            'danger_color' => '#e74c3c',
            'info_color' => '#3498db'
        ),
        'autumn' => array(
            'name' => 'Otoño',
            'primary_color' => '#d2691e',
            'secondary_color' => '#cd853f',
            'accent_color' => '#ff6347',
            'success_color' => '#6b8e23',
            'warning_color' => '#daa520',
            'danger_color' => '#b22222',
            'info_color' => '#4682b4'
        ),
        'winter' => array(
            'name' => 'Invierno',
            'primary_color' => '#4682b4',
            'secondary_color' => '#708090',
            'accent_color' => '#87ceeb',
            'success_color' => '#2e8b57',
            'warning_color' => '#daa520',
            'danger_color' => '#b22222',
            'info_color' => '#4169e1'
        ),
        
        // TEMAS PREMIUM
        'golden' => array(
            'name' => 'Dorado Premium',
            'primary_color' => '#b8860b',
            'secondary_color' => '#daa520',
            'accent_color' => '#ffd700',
            'success_color' => '#228b22',
            'warning_color' => '#ff8c00',
            'danger_color' => '#dc143c',
            'info_color' => '#4169e1'
        ),
        'platinum' => array(
            'name' => 'Platino Elegante',
            'primary_color' => '#708090',
            'secondary_color' => '#778899',
            'accent_color' => '#4169e1',
            'success_color' => '#2e8b57',
            'warning_color' => '#daa520',
            'danger_color' => '#dc143c',
            'info_color' => '#4682b4'
        ),
        'neon' => array(
            'name' => 'Neón Futurista',
            'primary_color' => '#00ffff',
            'secondary_color' => '#ff00ff',
            'accent_color' => '#ffff00',
            'success_color' => '#00ff00',
            'warning_color' => '#ffa500',
            'danger_color' => '#ff0000',
            'info_color' => '#0080ff'
        )
    );
}
    
    /**
     * Aplicar un tema predefinido
     */
    public function applyPresetTheme($preset_key) {
        $presets = $this->getPresetThemes();
        
        if (!isset($presets[$preset_key])) {
            return array('success' => false, 'message' => 'Tema predefinido no encontrado');
        }
        
        $preset_settings = $presets[$preset_key];
        
        // Remover el campo 'name' si existe
        if (isset($preset_settings['name'])) {
            unset($preset_settings['name']);
        }
        
        return $this->updateTheme($preset_settings);
    }
    
    /**
     * Restablecer tema a valores por defecto
     */
    public function resetToDefault() {
        $default_theme = $this->getDefaultTheme();
        return $this->updateTheme($default_theme);
    }
    
    /**
     * Exportar configuración actual del tema
     */
    public function exportTheme($theme_name = 'Mi Tema Personalizado') {
        $export_data = array(
            'name' => $theme_name,
            'version' => '1.0',
            'created' => date('Y-m-d H:i:s'),
            'settings' => $this->current_theme
        );
        
        return $export_data;
    }
    
    /**
     * Importar configuración de tema desde array
     */
    public function importTheme($theme_data) {
        if (!is_array($theme_data) || !isset($theme_data['settings'])) {
            return array('success' => false, 'message' => 'Formato de tema inválido');
        }
        
        $settings = $theme_data['settings'];
        
        // Validar que las configuraciones sean válidas
        $validation = $this->validateSettings($settings);
        if (!$validation['valid']) {
            return array('success' => false, 'message' => $validation['error']);
        }
        
        return $this->updateTheme($settings);
    }
    
    /**
     * Obtener información del tema actual
     */
    public function getThemeInfo() {
        return array(
            'name' => 'Tema Actual',
            'last_updated' => $this->getLastUpdateTime(),
            'total_settings' => count($this->current_theme),
            'css_file_exists' => file_exists($this->css_file_path),
            'css_file_size' => file_exists($this->css_file_path) ? filesize($this->css_file_path) : 0,
            'css_file_modified' => file_exists($this->css_file_path) ? filemtime($this->css_file_path) : null
        );
    }
    
    /**
     * Obtener última fecha de actualización del tema
     */
    private function getLastUpdateTime() {
        if ($this->db) {
            try {
                $query = "SELECT MAX(updated_at) as last_update FROM theme_settings";
                $stmt = $this->db->prepare($query);
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['last_update'];
            } catch (PDOException $e) {
                return null;
            }
        }
        return null;
    }
    
    /**
     * Limpiar cache de temas (si se implementa cache)
     */
    public function clearCache() {
        // Implementar limpieza de cache si se usa
        return true;
    }
    
    /**
     * Validar integridad del tema actual
     */
    public function validateThemeIntegrity() {
        $default_keys = array_keys($this->getDefaultTheme());
        $current_keys = array_keys($this->current_theme);
        
        $missing_keys = array_diff($default_keys, $current_keys);
        $extra_keys = array_diff($current_keys, $default_keys);
        
        $issues = array();
        
        if (!empty($missing_keys)) {
            $issues[] = 'Claves faltantes: ' . implode(', ', $missing_keys);
        }
        
        if (!empty($extra_keys)) {
            $issues[] = 'Claves extra: ' . implode(', ', $extra_keys);
        }
        
        // Validar colores
        foreach ($this->current_theme as $key => $value) {
            if (strpos($key, '_color') !== false) {
                if (!$this->isValidHexColor($value)) {
                    $issues[] = "Color inválido en $key: $value";
                }
            }
        }
        
        return array(
            'valid' => empty($issues),
            'issues' => $issues
        );
    }
    
    /**
     * Crear backup del tema actual
     */
    public function createBackup() {
        if (!$this->db) {
            return array('success' => false, 'message' => 'Base de datos no disponible');
        }
        
        try {
            $backup_data = json_encode($this->current_theme);
            $backup_name = 'backup_' . date('Y-m-d_H-i-s');
            
            // Guardar en tabla de backups si existe
            $query = "INSERT INTO theme_backups (name, theme_data, created_at) VALUES (?, ?, NOW())";
            $stmt = $this->db->prepare($query);
            $stmt->execute(array($backup_name, $backup_data));
            
            return array(
                'success' => true, 
                'message' => 'Backup creado correctamente',
                'backup_name' => $backup_name
            );
            
        } catch (PDOException $e) {
            // Si no existe la tabla, crear archivo de backup
            $backup_file = __DIR__ . '/backups/' . $backup_name . '.json';
            $backup_dir = dirname($backup_file);
            
            if (!is_dir($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }
            
            $backup_data = array(
                'name' => $backup_name,
                'created' => date('Y-m-d H:i:s'),
                'theme' => $this->current_theme
            );
            
            if (file_put_contents($backup_file, json_encode($backup_data, JSON_PRETTY_PRINT))) {
                return array(
                    'success' => true,
                    'message' => 'Backup creado como archivo',
                    'backup_file' => $backup_file
                );
            } else {
                return array('success' => false, 'message' => 'Error creando backup');
            }
        }
    }
    
    /**
     * Obtener valor específico del tema
     */
    public function getThemeValue($key, $default = null) {
        return isset($this->current_theme[$key]) ? $this->current_theme[$key] : $default;
    }
    
    /**
     * Establecer valor específico del tema
     */
    public function setThemeValue($key, $value) {
        if (!$this->isValidSettingKey($key)) {
            return array('success' => false, 'message' => 'Clave de configuración inválida');
        }
        
        return $this->updateTheme(array($key => $value));
    }
    
    /**
     * Obtener estadísticas del tema
     */
    public function getThemeStatistics() {
        $colors = array();
        $fonts = array();
        $sizes = array();
        $others = array();
        
        foreach ($this->current_theme as $key => $value) {
            if (strpos($key, '_color') !== false) {
                $colors[$key] = $value;
            } elseif (strpos($key, 'font_') !== false) {
                $fonts[$key] = $value;
            } elseif (strpos($key, '_size') !== false || strpos($key, 'width') !== false) {
                $sizes[$key] = $value;
            } else {
                $others[$key] = $value;
            }
        }
        
        return array(
            'total_settings' => count($this->current_theme),
            'colors_count' => count($colors),
            'fonts_count' => count($fonts),
            'sizes_count' => count($sizes),
            'others_count' => count($others),
            'colors' => $colors,
            'fonts' => $fonts,
            'sizes' => $sizes,
            'others' => $others
        );
    }
}

/**
 * Funciones helper globales para el sistema de temas
 */

/**
 * Obtener instancia del ThemeManager
 */
function getThemeManager($database = null) {
    static $theme_manager = null;
    
    if ($theme_manager === null) {
        $theme_manager = new ThemeManager($database);
    }
    
    return $theme_manager;
}

/**
 * Incluir CSS del tema dinámicamente
 */
function includeThemeCSS() {
    $css_file = 'assets/css/dynamic-theme.css';
    
    if (file_exists($css_file)) {
        $version = filemtime($css_file);
        echo '<link rel="stylesheet" href="' . $css_file . '?v=' . $version . '">';
    } else {
        // Generar CSS si no existe
        try {
            $theme_manager = getThemeManager();
            $theme_manager->generateCSSFile();
            echo '<link rel="stylesheet" href="' . $css_file . '?v=' . time() . '">';
        } catch (Exception $e) {
            // CSS de emergencia
            echo '<style>:root { --primary-color: #667eea; --bg-white: #ffffff; --text-primary: #212529; }</style>';
        }
    }
}

/**
 * Obtener color específico del tema
 */
function getThemeColor($color_key, $default = '#000000') {
    try {
        $theme_manager = getThemeManager();
        return $theme_manager->getThemeValue($color_key, $default);
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Obtener valor específico del tema
 */
function getThemeValue($key, $default = null) {
    try {
        $theme_manager = getThemeManager();
        return $theme_manager->getThemeValue($key, $default);
    } catch (Exception $e) {
        return $default;
    }
}

/**
 * Generar variables CSS para usar en HTML
 */
function generateCSSVariables() {
    try {
        $theme_manager = getThemeManager();
        $theme = $theme_manager->getThemeSettings();
        
        $css_vars = '<style>:root {';
        foreach ($theme as $key => $value) {
            $css_var = '--' . str_replace('_', '-', $key);
            $css_vars .= $css_var . ': ' . $value . ';';
        }
        $css_vars .= '}</style>';
        
        return $css_vars;
    } catch (Exception $e) {
        return '<style>:root { --primary-color: #667eea; }</style>';
    }
}

/**
 * Verificar si el sistema de temas está funcionando correctamente
 */
function checkThemeSystem() {
    try {
        $theme_manager = getThemeManager();
        $integrity = $theme_manager->validateThemeIntegrity();
        $info = $theme_manager->getThemeInfo();
        
        return array(
            'working' => true,
            'integrity' => $integrity,
            'info' => $info
        );
    } catch (Exception $e) {
        return array(
            'working' => false,
            'error' => $e->getMessage()
        );
    }
}

?>