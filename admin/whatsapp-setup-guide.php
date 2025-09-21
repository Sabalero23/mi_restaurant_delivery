<?php
// admin/whatsapp-setup-guide.php - Guía completa para configurar WhatsApp Business API
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/auth.php';
require_once '../config/functions.php';

$auth = new Auth();
$auth->requireLogin();
$auth->requirePermission('all');

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guía WhatsApp Business API - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .guide-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .step-number {
            background: linear-gradient(135deg, #25d366, #128c7e);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 1rem;
            flex-shrink: 0;
        }
        
        .sub-step {
            background: #f8f9fa;
            border-left: 4px solid #25d366;
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 0 8px 8px 0;
        }
        
        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin: 1rem 0;
            overflow-x: auto;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .success-box {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 8px;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #25d366, #128c7e);
        }
        
        .checklist-item {
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .checklist-item:last-child {
            border-bottom: none;
        }
        
        .table-of-contents {
            position: sticky;
            top: 20px;
        }
        
        @media (max-width: 991.98px) {
            .table-of-contents {
                position: relative;
                top: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar con índice -->
            <div class="col-lg-3">
                <div class="table-of-contents">
                    <div class="card guide-card">
                        <div class="card-header">
                            <h6><i class="fas fa-list me-2"></i>Índice de Contenidos</h6>
                        </div>
                        <div class="card-body">
                            <ul class="nav nav-pills flex-column">
                                <li class="nav-item">
                                    <a class="nav-link" href="#paso1">1. Crear Cuenta Meta</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#paso2">2. Crear Aplicación</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#paso3">3. Agregar WhatsApp</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#paso4">4. Configurar Número</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#paso5">5. Obtener Token</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#paso6">6. Configurar Webhooks</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#paso7">7. Configurar Sistema</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#paso8">8. Pruebas</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#problemas">Problemas Comunes</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link" href="#checklist">Checklist Final</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Contenido principal -->
            <div class="col-lg-9">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2><i class="fab fa-whatsapp text-success me-2"></i>Guía Completa WhatsApp Business API</h2>
                        <p class="text-muted">Configuración paso a paso desde cero</p>
                    </div>
                    <div>
                        <a href="whatsapp-settings.php" class="btn btn-success">
                            <i class="fas fa-cog me-1"></i>Ir a Configuración
                        </a>
                    </div>
                </div>
                
                <!-- Paso 1: Crear Cuenta -->
                <div id="paso1" class="card guide-card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="step-number">1</div>
                            <h5 class="mb-0">Crear Cuenta en Meta for Developers</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="sub-step">
                            <h6><i class="fas fa-user-plus me-2"></i>1.1 Registrarse</h6>
                            <ol>
                                <li>Ve a <strong>https://developers.facebook.com</strong></li>
                                <li>Haz clic en <strong>"Comenzar"</strong> o <strong>"Get Started"</strong></li>
                                <li>Inicia sesión con tu cuenta de Facebook personal</li>
                                <li>Si no tienes cuenta de Facebook, créala primero</li>
                                <li>Acepta los términos y condiciones de desarrollador</li>
                            </ol>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-shield-alt me-2"></i>1.2 Verificar Cuenta</h6>
                            <ol>
                                <li>Meta puede pedirte verificar tu identidad</li>
                                <li>Proporciona un número de teléfono válido</li>
                                <li>Confirma el código SMS que recibas</li>
                            </ol>
                        </div>
                        
                        <div class="warning-box">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Importante:</strong> Usa una cuenta de Facebook real y verificada. Meta es estricto con cuentas falsas.
                        </div>
                    </div>
                </div>
                
                <!-- Paso 2: Crear Aplicación -->
                <div id="paso2" class="card guide-card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="step-number">2</div>
                            <h5 class="mb-0">Crear una Aplicación Business</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="sub-step">
                            <h6><i class="fas fa-plus-circle me-2"></i>2.1 Crear Nueva App</h6>
                            <ol>
                                <li>En el dashboard principal, haz clic en <strong>"Crear app"</strong></li>
                                <li>Selecciona <strong>"Business"</strong> como tipo de aplicación</li>
                                <li><strong>NO selecciones "Consumer" o "Gaming"</strong></li>
                            </ol>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-info-circle me-2"></i>2.2 Información de la App</h6>
                            <ul>
                                <li><strong>Nombre de la app:</strong> <code><?php echo $restaurant_name; ?> WhatsApp</code></li>
                                <li><strong>Email de contacto:</strong> Tu email válido de negocio</li>
                                <li><strong>Propósito:</strong> Selecciona la opción más relevante para restaurantes</li>
                                <li>Haz clic en <strong>"Crear app"</strong></li>
                            </ul>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-building me-2"></i>2.3 Configuración Inicial</h6>
                            <ul>
                                <li>Meta te pedirá más información sobre tu negocio</li>
                                <li><strong>Tipo de negocio:</strong> Restaurante/Comida</li>
                                <li><strong>País:</strong> Argentina</li>
                                <li>Dirección comercial (si la tienes)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <!-- Paso 3: Agregar WhatsApp -->
                <div id="paso3" class="card guide-card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="step-number">3</div>
                            <h5 class="mb-0">Agregar WhatsApp como Producto</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="sub-step">
                            <h6><i class="fas fa-puzzle-piece me-2"></i>3.1 Agregar Producto</h6>
                            <ol>
                                <li>En el panel lateral izquierdo, busca <strong>"Productos"</strong> o <strong>"Products"</strong></li>
                                <li>Encuentra <strong>"WhatsApp"</strong> en la lista</li>
                                <li>Haz clic en <strong>"Configurar"</strong> o <strong>"Set up"</strong></li>
                            </ol>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-cog me-2"></i>3.2 Configuración Inicial de WhatsApp</h6>
                            <ol>
                                <li>Meta te guiará por un asistente de configuración</li>
                                <li><strong>Acepta los términos de WhatsApp Business</strong></li>
                                <li><strong>Selecciona el país:</strong> Argentina (+54)</li>
                            </ol>
                        </div>
                        
                        <div class="info-box">
                            <i class="fas fa-info-circle me-2"></i>
                            Este paso es crucial. Sin agregar WhatsApp como producto, no podrás acceder a las APIs.
                        </div>
                    </div>
                </div>
                
                <!-- Paso 4: Configurar Número -->
                <div id="paso4" class="card guide-card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="step-number">4</div>
                            <h5 class="mb-0">Configurar Número de WhatsApp</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="sub-step">
                            <h6><i class="fas fa-phone me-2"></i>4.1 Agregar Número Comercial</h6>
                            <ol>
                                <li>En la sección WhatsApp, ve a <strong>"Phone Numbers"</strong></li>
                                <li>Haz clic en <strong>"Add phone number"</strong></li>
                                <li>Introduce tu número comercial (el que usas para el restaurante)</li>
                                <li><strong>Formato:</strong> +54 9 XXX XXX XXXX</li>
                                <li><strong>Ejemplo:</strong> +54 9 348 259 9994</li>
                            </ol>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-check-circle me-2"></i>4.2 Verificación del Número</h6>
                            <ul>
                                <li><strong>Método SMS:</strong> Meta enviará un código a tu número</li>
                                <li><strong>Método llamada:</strong> Si SMS no funciona, prueba llamada</li>
                                <li><strong>Importante:</strong> El número debe estar disponible (no vinculado a otra cuenta)</li>
                            </ul>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-id-card me-2"></i>4.3 Obtener Phone Number ID</h6>
                            <ol>
                                <li>Una vez verificado, ve a <strong>"Phone Numbers"</strong></li>
                                <li><strong>Copia el "Phone Number ID"</strong> (número largo como: 805175016014101)</li>
                                <li><strong>Guarda este ID</strong> - lo necesitarás para whatsapp-settings.php</li>
                            </ol>
                            
                            <div class="code-block">
Phone Number ID de ejemplo: 805175016014101<br>
Este número lo necesitarás en la configuración del sistema
                            </div>
                        </div>
                        
                        <div class="warning-box">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Atención:</strong> Si el número ya está en uso en WhatsApp Business regular, debes migrarlo o usar otro número.
                        </div>
                    </div>
                </div>
                
                <!-- Paso 5: Obtener Token -->
                <div id="paso5" class="card guide-card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="step-number">5</div>
                            <h5 class="mb-0">Obtener Access Token</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="sub-step">
                            <h6><i class="fas fa-clock me-2"></i>5.1 Token Temporal (Para Pruebas)</h6>
                            <ol>
                                <li>Ve a <strong>"API Setup"</strong> en la sección WhatsApp</li>
                                <li>Encontrarás un <strong>"Temporary access token"</strong></li>
                                <li>Copia este token (empieza con EAA...)</li>
                                <li><strong>Válido por 24 horas</strong> - perfecto para probar</li>
                            </ol>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-infinity me-2"></i>5.2 Token Permanente (Producción)</h6>
                            <ol>
                                <li>Ve a <strong>"System Users"</strong> en el menú lateral</li>
                                <li>Haz clic en <strong>"Add"</strong> para crear un usuario del sistema</li>
                                <li><strong>Nombre:</strong> WhatsApp API User</li>
                                <li><strong>Role:</strong> Selecciona <strong>"Admin"</strong></li>
                                <li>Una vez creado, haz clic en <strong>"Generate New Token"</strong></li>
                                <li>Selecciona la app que creaste</li>
                                <li><strong>Permisos necesarios:</strong>
                                    <ul>
                                        <li>whatsapp_business_messaging</li>
                                        <li>whatsapp_business_management</li>
                                        <li>business_management</li>
                                    </ul>
                                </li>
                                <li><strong>Copia y guarda este token</strong> - no caduca</li>
                            </ol>
                        </div>
                        
                        <div class="code-block">
Ejemplo de Access Token:<br>
EAAZAujZCH9rlwBPSEDzNOQfmFVoxG7NMIFdn0CXVefaWxMiZAjyZBbDqyZB5FGpj3wHprDJdwPHLuJT0UUWts39Jwqb95iS99WdP99shUbL7ZCZAQBmF3wYJ34knPCRjmk1IHG8QlIHb45QtakxDuODaZAf17FvHqRoyad2PC4x4Vps6JO8myd79s6Cp6sC1P110hAZDZD
                        </div>
                        
                        <div class="success-box">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Recomendación:</strong> Usa el token temporal para probar, luego cambia al permanente para producción.
                        </div>
                    </div>
                </div>
                
                <!-- Paso 6: Configurar Webhooks -->
                <div id="paso6" class="card guide-card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="step-number">6</div>
                            <h5 class="mb-0">Configurar Webhooks</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="sub-step">
                            <h6><i class="fas fa-link me-2"></i>6.1 Configuración del Webhook</h6>
                            <ol>
                                <li>Ve a <strong>"Webhooks"</strong> en la sección WhatsApp</li>
                                <li>Haz clic en <strong>"Configure webhooks"</strong></li>
                                <li><strong>Callback URL:</strong></li>
                            </ol>
                            
                            <div class="code-block">
<?php echo BASE_URL; ?>admin/whatsapp-webhook.php
                            </div>
                            
                            <ol start="4">
                                <li><strong>Verify Token:</strong> <?php echo $settings['whatsapp_webhook_token'] ?? 'whatsapp-webhook-comias'; ?></li>
                            </ol>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-check-square me-2"></i>6.2 Seleccionar Campos del Webhook</h6>
                            <p><strong>IMPORTANTE:</strong> Marca estos campos:</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check-square text-success me-2"></i><strong>messages</strong> (obligatorio para recibir mensajes)</li>
                                <li><i class="fas fa-check-square text-success me-2"></i><strong>messaging_postbacks</strong> (para respuestas a botones)</li>
                                <li><i class="fas fa-check-square text-success me-2"></i><strong>message_deliveries</strong> (para estados de entrega)</li>
                                <li><i class="fas fa-check-square text-success me-2"></i><strong>messaging_optins</strong> (para nuevos suscriptores)</li>
                            </ul>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-shield-check me-2"></i>6.3 Verificar Webhook</h6>
                            <ol>
                                <li>Haz clic en <strong>"Verify and Save"</strong></li>
                                <li>Meta hará una petición GET a tu webhook</li>
                                <li><strong>Debe mostrar "Success"</strong> si todo está correcto</li>
                                <li>Si falla, revisa el token y la URL</li>
                            </ol>
                        </div>
                        
                        <div class="warning-box">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Crítico:</strong> Si no marcas "messages", Meta verificará el webhook pero no enviará mensajes entrantes.
                        </div>
                    </div>
                </div>
                
                <!-- Paso 7: Configurar Sistema -->
                <div id="paso7" class="card guide-card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="step-number">7</div>
                            <h5 class="mb-0">Configurar en whatsapp-settings.php</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="sub-step">
                            <h6><i class="fas fa-database me-2"></i>7.1 Datos para Configurar</h6>
                            <p>Con la información obtenida, configura en tu panel:</p>
                            
                            <div class="code-block">
Access Token: EAAxxxxxxxxxxxxx... (el token que copiaste)<br>
Phone Number ID: 805175016014101 (el ID del número)<br>
Webhook Token: <?php echo $settings['whatsapp_webhook_token'] ?? 'whatsapp-webhook-comias'; ?> (debe coincidir con Meta)
                            </div>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-toggle-on me-2"></i>7.2 Habilitar Configuraciones</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check-square text-success me-2"></i><strong>Habilitar Envío Automático</strong></li>
                                <li><i class="fas fa-check-square text-success me-2"></i><strong>Habilitar Fallback</strong></li>
                                <li><i class="fas fa-check-square text-warning me-2"></i><strong>Respuestas Automáticas</strong> (opcional)</li>
                            </ul>
                        </div>
                        
                        <div class="d-grid">
                            <a href="whatsapp-settings.php" class="btn btn-success btn-lg">
                                <i class="fas fa-cog me-2"></i>Ir a Configurar Ahora
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Paso 8: Pruebas -->
                <div id="paso8" class="card guide-card">
                    <div class="card-header">
                        <div class="d-flex align-items-center">
                            <div class="step-number">8</div>
                            <h5 class="mb-0">Verificaciones y Pruebas</h5>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="sub-step">
                            <h6><i class="fas fa-paper-plane me-2"></i>8.1 Probar API</h6>
                            <ol>
                                <li>En whatsapp-settings.php, usa <strong>"Probar API"</strong></li>
                                <li>Introduce tu número personal</li>
                                <li>Deberías recibir un mensaje de prueba</li>
                            </ol>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-link me-2"></i>8.2 Probar Webhook</h6>
                            <ol>
                                <li>Usa el botón <strong>"Probar Webhook"</strong></li>
                                <li>Debe mostrar "Webhook funcionando correctamente"</li>
                            </ol>
                        </div>
                        
                        <div class="sub-step">
                            <h6><i class="fas fa-comments me-2"></i>8.3 Prueba Completa</h6>
                            <ol>
                                <li><strong>Envía un mensaje</strong> desde tu panel a tu número personal</li>
                                <li><strong>Responde</strong> desde tu WhatsApp personal</li>
                                <li><strong>Verifica</strong> en <a href="whatsapp-messages.php">whatsapp-messages.php</a> que aparezca la respuesta</li>
                            </ol>
                        </div>
                        
                        <div class="success-box">
                            <i class="fas fa-check-circle me-2"></i>
                            Si todas las pruebas funcionan, tu configuración está completa.
                        </div>
                    </div>
                </div>
                
                <!-- Problemas Comunes -->
                <div id="problemas" class="card guide-card">
                    <div class="card-header">
                        <h5><i class="fas fa-exclamation-triangle text-warning me-2"></i>Problemas Comunes</h5>
                    </div>
                    <div class="card-body">
                        <div class="accordion" id="problemsAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#problem1">
                                        Error "Invalid verification token"
                                    </button>
                                </h2>
                                <div id="problem1" class="accordion-collapse collapse show" data-bs-parent="#problemsAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Verifica que el token en whatsapp-settings.php coincida exactamente con Meta</li>
                                            <li>Sin espacios extra o caracteres especiales</li>
                                            <li>Revisa mayúsculas y minúsculas</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#problem2">
                                        "Webhook verification failed"
                                    </button>
                                </h2>
                                <div id="problem2" class="accordion-collapse collapse" data-bs-parent="#problemsAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Verifica que tu URL sea accesible públicamente</li>
                                            <li>Certificado SSL debe ser válido</li>
                                            <li>Meta debe poder hacer peticiones GET a tu servidor</li>
                                            <li>Revisa firewall y restricciones de acceso</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#problem3">
                                        No llegan mensajes entrantes
                                    </button>
                                </h2>
                                <div id="problem3" class="accordion-collapse collapse" data-bs-parent="#problemsAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li><strong>Verifica que el campo "messages" esté marcado en webhooks</strong></li>
                                            <li>Revisa los logs del webhook</li>
                                            <li>Confirma que Meta puede hacer peticiones POST</li>
                                            <li>Verifica que la tabla whatsapp_messages exista</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#problem4">
                                        "Phone number already in use"
                                    </button>
                                </h2>
                                <div id="problem4" class="accordion-collapse collapse" data-bs-parent="#problemsAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>El número ya está vinculado a otra cuenta</li>
                                            <li>Contacta soporte de Meta o usa otro número</li>
                                            <li>Verifica que no esté en WhatsApp Business regular</li>
                                            <li>Puede requerir migración desde otra plataforma</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="accordion-item">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#problem5">
                                        Token expirado
                                    </button>
                                </h2>
                                <div id="problem5" class="accordion-collapse collapse" data-bs-parent="#problemsAccordion">
                                    <div class="accordion-body">
                                        <ul>
                                            <li>Los tokens temporales duran 24 horas</li>
                                            <li>Usa tokens del sistema para producción</li>
                                            <li>Renueva antes de que expire</li>
                                            <li>Configura alertas de expiración</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="info-box mt-3">
                            <i class="fas fa-life-ring me-2"></i>
                            <strong>Soporte:</strong> Si tienes problemas, contacta a Meta Business Help Center en 
                            <a href="https://business.facebook.com/help" target="_blank">business.facebook.com/help</a>
                        </div>
                    </div>
                </div>
                
                <!-- Límites y Costos -->
                <div class="card guide-card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line me-2"></i>Límites y Costos</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-gift me-2"></i>Gratuito</h6>
                                <ul>
                                    <li><strong>1,000 conversaciones</strong> por mes</li>
                                    <li>Ideal para comenzar</li>
                                    <li>Incluye envío y recepción</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-dollar-sign me-2"></i>Tarifas</h6>
                                <ul>
                                    <li><strong>$0.005-0.09 USD</strong> por conversación</li>
                                    <li>Varía según el país y tipo</li>
                                    <li>Muy económico para restaurantes</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6">
                                <h6><i class="fas fa-clock me-2"></i>Rate Limits</h6>
                                <ul>
                                    <li><strong>250 mensajes/segundo</strong> máximo</li>
                                    <li>100 mensajes/segundo por webhook</li>
                                    <li>Suficiente para la mayoría de restaurantes</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-hourglass me-2"></i>Duración de Tokens</h6>
                                <ul>
                                    <li><strong>Temporales:</strong> 24 horas</li>
                                    <li><strong>Sistema:</strong> No expiran</li>
                                    <li>Usa tokens de sistema para producción</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Checklist Final -->
                <div id="checklist" class="card guide-card">
                    <div class="card-header">
                        <h5><i class="fas fa-check-double me-2"></i>Checklist Final</h5>
                    </div>
                    <div class="card-body">
                        <p>Antes de considerar la configuración completa, verifica:</p>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Configuración en Meta</h6>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>App creada y configurada
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>WhatsApp agregado como producto
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Número verificado y Phone Number ID obtenido
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Access Token generado y guardado
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Webhook configurado y verificado
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Campo "messages" marcado en webhook
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Configuración en Sistema</h6>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Datos guardados en whatsapp-settings.php
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Envío automático habilitado
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Prueba de envío exitosa
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Prueba de recepción exitosa
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Logs del webhook funcionando
                                </div>
                                <div class="checklist-item">
                                    <i class="far fa-square me-2"></i>Mensajes aparecen en whatsapp-messages.php
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <div class="success-box">
                                <i class="fas fa-trophy me-2"></i>
                                <strong>¡Felicitaciones!</strong> Una vez completados todos estos pasos, 
                                tu sistema de WhatsApp Business API estará completamente funcional.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recursos Adicionales -->
                <div class="card guide-card">
                    <div class="card-header">
                        <h5><i class="fas fa-link me-2"></i>Recursos Adicionales</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h6><i class="fas fa-book me-2"></i>Documentación</h6>
                                <ul class="list-unstyled">
                                    <li><a href="https://developers.facebook.com/docs/whatsapp" target="_blank">WhatsApp Business API Docs</a></li>
                                    <li><a href="https://business.facebook.com/help" target="_blank">Meta Business Help</a></li>
                                    <li><a href="https://developers.facebook.com/community" target="_blank">Developer Community</a></li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="fas fa-tools me-2"></i>Herramientas</h6>
                                <ul class="list-unstyled">
                                    <li><a href="whatsapp-settings.php">Configuración WhatsApp</a></li>
                                    <li><a href="whatsapp-messages.php">Ver Mensajes</a></li>
                                    <li><a href="settings.php">Configuración General</a></li>
                                </ul>
                            </div>
                            <div class="col-md-4">
                                <h6><i class="fas fa-life-ring me-2"></i>Soporte</h6>
                                <ul class="list-unstyled">
                                    <li><a href="https://business.facebook.com/help/support" target="_blank">Soporte Meta Business</a></li>
                                    <li><a href="https://developers.facebook.com/support" target="_blank">Soporte para Desarrolladores</a></li>
                                    <li><a href="mailto:support@<?php echo parse_url(BASE_URL, PHP_URL_HOST); ?>">Soporte Técnico</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Smooth scrolling para navegación
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                    
                    // Actualizar navegación activa
                    document.querySelectorAll('.nav-link').forEach(link => {
                        link.classList.remove('active');
                    });
                    this.classList.add('active');
                }
            });
        });
        
        // Checklist interactivo
        document.querySelectorAll('.checklist-item').forEach(item => {
            item.addEventListener('click', function() {
                const icon = this.querySelector('i');
                if (icon.classList.contains('far')) {
                    icon.classList.remove('far', 'fa-square');
                    icon.classList.add('fas', 'fa-check-square', 'text-success');
                } else {
                    icon.classList.remove('fas', 'fa-check-square', 'text-success');
                    icon.classList.add('far', 'fa-square');
                }
            });
        });
        
        // Auto-scroll navigation
        window.addEventListener('scroll', function() {
            const sections = document.querySelectorAll('.card[id]');
            const navLinks = document.querySelectorAll('.nav-link[href^="#"]');
            
            let current = '';
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.clientHeight;
                if (scrollY >= (sectionTop - 200)) {
                    current = section.getAttribute('id');
                }
            });
            
            navLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href') === '#' + current) {
                    link.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>