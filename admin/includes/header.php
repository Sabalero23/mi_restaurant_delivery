<?php
// admin/includes/header.php
if (!isset($_SESSION)) {
    session_start();
}

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_name = $_SESSION['full_name'] ?? 'Usuario';
$user_role = $_SESSION['role_name'] ?? 'Sin rol';
$user_email = $_SESSION['email'] ?? '';
$user_avatar = $_SESSION['avatar'] ?? null;

// Obtener las iniciales del usuario para el avatar por defecto
$initials = '';
if ($user_name) {
    $names = explode(' ', trim($user_name));
    $initials = strtoupper(substr($names[0], 0, 1));
    if (isset($names[1])) {
        $initials .= strtoupper(substr($names[1], 0, 1));
    }
}
?>

<!-- Header del Sistema -->
<div class="system-header">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center">
            <!-- Logo/Nombre del Sistema (Lado Izquierdo) -->
            <div class="header-brand">
                <div class="d-flex align-items-center">
                    <i class="fas fa-utensils me-2 text-primary d-none d-md-inline"></i>
                    <span class="fw-bold text-primary d-none d-lg-inline">Sistema Restaurante</span>
                    <span class="fw-bold text-primary d-lg-none">SR</span>
                </div>
            </div>
            
            <!-- Notificación de Actualización (Centro) - Solo para Administradores -->
            <?php if ($user_role === 'administrador'): ?>
            <div id="updateNotificationContainer" class="flex-grow-1 d-flex justify-content-center" style="display: none !important;">
                <!-- Se llenará dinámicamente -->
            </div>
            <?php endif; ?>
            
            <!-- Información del Usuario (Lado Derecho) -->
            <div class="header-user-info">
                <div class="dropdown">
                    <button class="btn btn-header-user dropdown-toggle d-flex align-items-center" 
                            type="button" 
                            id="userDropdown" 
                            data-bs-toggle="dropdown" 
                            aria-expanded="false">
                        <!-- Avatar del Usuario -->
                        <div class="user-avatar me-2">
                            <?php if ($user_avatar && file_exists("../uploads/avatars/" . $user_avatar)): ?>
                                <img src="../uploads/avatars/<?php echo htmlspecialchars($user_avatar); ?>" 
                                     alt="Avatar" 
                                     class="avatar-img">
                            <?php else: ?>
                                <div class="avatar-initials">
                                    <?php echo $initials; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Información del Usuario - Solo en desktop -->
                        <div class="user-details d-none d-md-block text-start">
                            <div class="user-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="user-role"><?php echo htmlspecialchars($user_role); ?></div>
                        </div>
                        
                        <!-- Icono dropdown solo en móvil -->
                        <i class="fas fa-chevron-down ms-1 d-md-none" style="font-size: 0.7rem;"></i>
                    </button>
                    
                    <!-- Menú Desplegable -->
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <li>
                            <div class="dropdown-header">
                                <div class="fw-bold"><?php echo htmlspecialchars($user_name); ?></div>
                                <div class="text-muted small"><?php echo htmlspecialchars($user_email); ?></div>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user me-2"></i>
                                Mi Perfil
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="#" onclick="toggleNotifications()">
                                <i class="fas fa-bell me-2"></i>
                                Notificaciones
                                <span class="badge bg-primary ms-auto" id="notification-toggle">ON</span>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Cerrar Sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.system-header {
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-bottom: 1px solid #e9ecef;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    position: sticky;
    top: 0;
    margin-left: var(--sidebar-width, 280px);
    z-index: 1020;
    min-height: 60px;
}

.system-header .container-fluid {
    height: 60px;
    display: flex;
    align-items: center;
    padding: 0 1rem;
}

.system-header .d-flex {
    height: 100%;
    width: 100%;
}

.header-brand {
    flex-shrink: 0;
    display: flex;
    align-items: center;
    font-size: 1.1rem;
}

.header-user-info {
    flex-shrink: 0;
    margin-left: auto;
}

/* Estilos para la notificación de actualización */
.update-notification-badge {
    display: inline-flex;
    align-items: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 500;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    animation: pulseGlow 2s ease-in-out infinite;
}

.update-notification-badge:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    color: white;
    text-decoration: none;
}

.update-notification-badge i {
    margin-right: 0.5rem;
    animation: bounce 1s ease-in-out infinite;
}

.update-notification-badge .badge {
    background: rgba(255, 255, 255, 0.3);
    color: white;
    margin-left: 0.5rem;
    padding: 0.2rem 0.5rem;
    border-radius: 10px;
    font-size: 0.75rem;
}

@keyframes pulseGlow {
    0%, 100% {
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
    }
    50% {
        box-shadow: 0 2px 16px rgba(102, 126, 234, 0.6);
    }
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}

.btn-header-user {
    background: none;
    border: 1px solid #e9ecef;
    color: #495057;
    border-radius: 30px;
    padding: 0.375rem 0.75rem;
    height: 40px;
    display: flex;
    align-items: center;
    transition: all 0.2s ease;
}

.btn-header-user:hover {
    background: #f8f9fa;
    border-color: var(--primary-color, #667eea);
    color: var(--primary-color, #667eea);
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.btn-header-user:focus {
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.user-avatar {
    position: relative;
    flex-shrink: 0;
}

.avatar-img {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.avatar-initials {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--primary-color, #667eea) 0%, var(--secondary-color, #764ba2) 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.75rem;
    border: 2px solid #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.2);
}

.user-details {
    line-height: 1.2;
    max-width: 150px;
    overflow: hidden;
}

.user-name {
    font-size: 0.85rem;
    font-weight: 600;
    color: #212529;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 0.7rem;
    color: #6c757d;
    text-transform: capitalize;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.dropdown-menu {
    min-width: 250px;
    border: none;
    box-shadow: 0 10px 30px rgba(0,0,0,0.15);
    border-radius: 10px;
    margin-top: 0.5rem;
}

.dropdown-header {
    padding: 0.75rem 1rem;
    background: #f8f9fa;
    border-radius: 10px 10px 0 0;
}

.dropdown-item {
    padding: 0.5rem 1rem;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
    padding-left: 1.25rem;
}

.dropdown-item.text-danger:hover {
    background-color: #fff5f5;
    color: #dc3545 !important;
}

.dropdown-item i {
    width: 20px;
    text-align: center;
}

.badge {
    font-size: 0.7rem;
    padding: 0.25rem 0.5rem;
}

/* Responsive para Móviles */
@media (max-width: 768px) {
    .system-header .container-fluid {
        padding: 0 0.75rem;
    }
    
    .header-brand {
        font-size: 1rem;
    }
    
    .update-notification-badge {
        font-size: 0.75rem;
        padding: 0.3rem 0.6rem;
    }
    
    .update-notification-badge .badge {
        font-size: 0.65rem;
        padding: 0.15rem 0.4rem;
    }
    
    .btn-header-user {
        padding: 0.25rem 0.5rem;
        height: 36px;
    }
    
    .avatar-img,
    .avatar-initials {
        width: 28px;
        height: 28px;
    }
    
    .avatar-initials {
        font-size: 0.7rem;
    }
    
    .dropdown-menu {
        min-width: 220px;
        margin-top: 0.25rem;
    }
    
    .dropdown-header {
        padding: 0.5rem 0.75rem;
    }
    
    .dropdown-item {
        padding: 0.4rem 0.75rem;
        font-size: 0.9rem;
    }
    
    .dropdown-item:hover {
        padding-left: 1rem;
    }
}

@media (max-width: 576px) {
    .system-header .container-fluid {
        padding: 0 0.5rem;
    }
    
    .header-brand span {
        font-size: 0.9rem;
    }
    
    .update-notification-badge {
        font-size: 0.7rem;
        padding: 0.25rem 0.5rem;
    }
    
    .update-notification-badge span:not(.badge) {
        display: none;
    }
    
    .update-notification-badge i {
        margin-right: 0;
    }
    
    .btn-header-user {
        padding: 0.25rem 0.4rem;
        height: 34px;
        border-radius: 25px;
    }
    
    .avatar-img,
    .avatar-initials {
        width: 26px;
        height: 26px;
    }
    
    .avatar-initials {
        font-size: 0.65rem;
    }
    
    .user-avatar {
        margin-right: 0.5rem !important;
    }
    
    .dropdown-menu {
        min-width: 200px;
        font-size: 0.85rem;
        transform: translateX(-20px);
    }
    
    .dropdown-header {
        padding: 0.4rem 0.6rem;
    }
    
    .dropdown-item {
        padding: 0.35rem 0.6rem;
    }
    
    .dropdown-item:hover {
        padding-left: 0.85rem;
    }
    
    .badge {
        font-size: 0.65rem;
        padding: 0.15rem 0.35rem;
    }
}

@media (max-width: 360px) {
    .system-header .container-fluid {
        padding: 0 0.4rem;
    }
    
    .header-brand span {
        font-size: 0.8rem;
    }
    
    .btn-header-user {
        height: 32px;
        padding: 0.2rem 0.35rem;
    }
    
    .avatar-img,
    .avatar-initials {
        width: 24px;
        height: 24px;
    }
    
    .avatar-initials {
        font-size: 0.6rem;
    }
    
    .dropdown-menu {
        min-width: 180px;
        transform: translateX(-30px);
    }
}

/* Animación para el dropdown */
.dropdown-menu {
    animation: fadeInUp 0.2s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Estados especiales */
.user-status-indicator {
    position: absolute;
    bottom: -1px;
    right: -1px;
    width: 10px;
    height: 10px;
    background: #28a745;
    border-radius: 50%;
    border: 2px solid white;
}

.notification-indicator {
    position: absolute;
    top: -2px;
    right: -2px;
    width: 8px;
    height: 8px;
    background: #dc3545;
    border-radius: 50%;
    border: 1px solid white;
}

/* Modo oscuro (si se implementa) */
@media (prefers-color-scheme: dark) {
    .system-header {
        background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
        border-bottom-color: #4a5568;
    }
    
    .btn-header-user {
        border-color: #4a5568;
        color: #e2e8f0;
    }
    
    .btn-header-user:hover {
        background: #4a5568;
        border-color: var(--primary-color, #667eea);
    }
    
    .dropdown-menu {
        background: #2d3748;
        color: #e2e8f0;
    }
    
    .dropdown-header {
        background: #4a5568;
    }
    
    .dropdown-item {
        color: #e2e8f0;
    }
    
    .dropdown-item:hover {
        background: #4a5568;
    }
}

/* Transiciones suaves */
.system-header,
.btn-header-user,
.dropdown-menu,
.dropdown-item {
    transition: all 0.2s ease;
}

/* Fix para z-index en móviles */
@media (max-width: 768px) {
    .dropdown-menu {
        z-index: 1050;
        position: absolute !important;
    }
}
</style>

<script>
// Función para toggle de notificaciones
function toggleNotifications() {
    const toggle = document.getElementById('notification-toggle');
    const isEnabled = localStorage.getItem('notifications_enabled') !== 'false';
    
    if (isEnabled) {
        localStorage.setItem('notifications_enabled', 'false');
        toggle.textContent = 'OFF';
        toggle.classList.remove('bg-primary');
        toggle.classList.add('bg-secondary');
        
        if (typeof window.notificationSoundsEnabled !== 'undefined') {
            window.notificationSoundsEnabled = false;
        }
        
        showNotification('Notificaciones deshabilitadas', 'info');
    } else {
        localStorage.setItem('notifications_enabled', 'true');
        toggle.textContent = 'ON';
        toggle.classList.remove('bg-secondary');
        toggle.classList.add('bg-primary');
        
        if (typeof window.notificationSoundsEnabled !== 'undefined') {
            window.notificationSoundsEnabled = true;
        }
        
        showNotification('Notificaciones habilitadas', 'success');
    }
}

// Función auxiliar para mostrar notificaciones
function showNotification(message, type = 'info') {
    if (typeof showVisualNotification === 'function') {
        showVisualNotification(message, type);
    } else {
        console.log(`Notificación [${type}]: ${message}`);
    }
}

// Función para verificar actualizaciones
async function checkForSystemUpdates() {
    try {
        const response = await fetch('api/github-update.php?action=check_updates');
        const data = await response.json();
        
        if (data.success && data.updates_available && !data.requires_license) {
            showUpdateNotification(data);
        } else {
            hideUpdateNotification();
        }
    } catch (error) {
        console.error('Error al verificar actualizaciones:', error);
    }
}

// Función para mostrar la notificación de actualización
function showUpdateNotification(data) {
    const container = document.getElementById('updateNotificationContainer');
    
    if (!container) return;
    
    const commitsText = data.commits_ahead > 1 ? `${data.commits_ahead} actualizaciones` : '1 actualización';
    
    container.innerHTML = `
        <a href="system-updates.php" class="update-notification-badge">
            <i class="fas fa-download"></i>
            <span>Nueva actualización disponible</span>
            <span class="badge">${commitsText}</span>
        </a>
    `;
    
    container.style.display = 'flex !important';
    
    // Guardar en localStorage para no molestar constantemente
    const lastNotified = localStorage.getItem('last_update_notification');
    const currentCommit = data.latest_commit;
    
    if (lastNotified !== currentCommit) {
        localStorage.setItem('last_update_notification', currentCommit);
        
        // Mostrar notificación visual si está habilitada
        if (localStorage.getItem('notifications_enabled') !== 'false') {
            showNotification('¡Nueva actualización del sistema disponible!', 'info');
        }
    }
}

// Función para ocultar la notificación de actualización
function hideUpdateNotification() {
    const container = document.getElementById('updateNotificationContainer');
    if (container) {
        container.style.display = 'none !important';
        container.innerHTML = '';
    }
}

// Inicializar estado de notificaciones al cargar
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('notification-toggle');
    const isEnabled = localStorage.getItem('notifications_enabled') !== 'false';
    
    if (!isEnabled) {
        toggle.textContent = 'OFF';
        toggle.classList.remove('bg-primary');
        toggle.classList.add('bg-secondary');
    }
    
    if (typeof window.notificationSoundsEnabled !== 'undefined') {
        window.notificationSoundsEnabled = isEnabled;
    }
    
    // Verificar actualizaciones solo si es administrador
    <?php if ($_SESSION['role_name'] === 'administrador'): ?>
        // Verificar inmediatamente
        checkForSystemUpdates();
        
        // Verificar cada 30 minutos
        setInterval(checkForSystemUpdates, 30 * 60 * 1000);
    <?php endif; ?>
});

// Mostrar estado de conexión
window.addEventListener('online', function() {
    showNotification('Conexión restaurada', 'success');
    // Verificar actualizaciones cuando se restaura la conexión
    <?php if ($_SESSION['role_name'] === 'administrador'): ?>
        checkForSystemUpdates();
    <?php endif; ?>
});

window.addEventListener('offline', function() {
    showNotification('Sin conexión a internet', 'danger');
});
</script>