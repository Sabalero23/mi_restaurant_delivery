<?php
// privacy.php - Política de Privacidad
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$restaurant_email = $settings['restaurant_email'] ?? 'contacto@restaurante.com';
$restaurant_phone = $settings['restaurant_phone'] ?? '';

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
        'accent_color' => '#ff6b6b'
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidad - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?php echo $current_theme['primary_color'] ?? '#667eea'; ?>;
            --secondary-color: <?php echo $current_theme['secondary_color'] ?? '#764ba2'; ?>;
            --accent-color: <?php echo $current_theme['accent_color'] ?? '#ff6b6b'; ?>;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: var(--text-dark);
            line-height: 1.6;
        }

        /* Header */
        .page-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 3rem 0 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
        }

        .page-header .subtitle {
            font-size: 1.1rem;
            opacity: 0.95;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1.5rem;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 500;
            margin-bottom: 1rem;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateX(-3px);
        }

        /* Contenido */
        .content-section {
            background: white;
            border-radius: 15px;
            padding: 2.5rem;
            margin: 2rem 0;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.06);
        }

        .content-section h2 {
            color: var(--primary-color);
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 3px solid var(--primary-color);
        }

        .content-section h3 {
            color: var(--secondary-color);
            font-size: 1.3rem;
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .content-section p {
            color: var(--text-dark);
            margin-bottom: 1rem;
            text-align: justify;
        }

        .content-section ul {
            margin-left: 2rem;
            margin-bottom: 1.5rem;
        }

        .content-section li {
            margin-bottom: 0.75rem;
            color: var(--text-dark);
        }

        .highlight-box {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-left: 4px solid var(--primary-color);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 8px;
        }

        .highlight-box strong {
            color: var(--primary-color);
        }

        .contact-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
        }

        .contact-info h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .contact-info p {
            margin-bottom: 0.5rem;
        }

        .contact-info i {
            color: var(--primary-color);
            width: 25px;
        }

        .last-updated {
            color: var(--text-muted);
            font-style: italic;
            text-align: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #dee2e6;
        }

        /* Footer público */
        .public-footer {
            background: linear-gradient(135deg, #212529 0%, #343a40 100%);
            color: white;
            padding: 2rem 0;
            margin-top: 3rem;
        }

        .public-footer .container {
            text-align: center;
        }

        .public-footer a {
            color: #20c997;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .public-footer a:hover {
            color: #17a2b8;
            text-decoration: underline;
        }

        .public-footer small {
            font-size: 0.9rem;
            color: #adb5bd;
        }

        .footer-links {
            margin-top: 1rem;
        }

        .footer-links a {
            margin: 0 1rem;
            font-size: 0.9rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }

            .content-section {
                padding: 1.5rem;
            }

            .content-section h2 {
                font-size: 1.5rem;
            }

            .content-section ul {
                margin-left: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <a href="index.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Volver al inicio
            </a>
            <h1><i class="fas fa-shield-alt me-2"></i>Política de Privacidad</h1>
            <p class="subtitle"><?php echo $restaurant_name; ?></p>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="container my-5">
        <div class="content-section">
            <div class="highlight-box">
                <p><strong><i class="fas fa-info-circle me-2"></i>Información importante:</strong></p>
                <p>
                    En <strong><?php echo $restaurant_name; ?></strong> nos comprometemos a proteger tu privacidad y 
                    garantizar la seguridad de tus datos personales. Esta política describe cómo recopilamos, 
                    usamos y protegemos tu información.
                </p>
            </div>

            <h2>1. Información que Recopilamos</h2>
            
            <h3>1.1 Datos de Contacto</h3>
            <p>Cuando realizas un pedido online, recopilamos:</p>
            <ul>
                <li><strong>Nombre completo:</strong> Para identificar tu pedido y comunicarnos contigo</li>
                <li><strong>Número de teléfono:</strong> Para coordinar la entrega y notificaciones</li>
                <li><strong>Dirección de entrega:</strong> Para llevar tu pedido al destino correcto</li>
                <li><strong>Correo electrónico:</strong> Para enviar confirmaciones y promociones (opcional)</li>
            </ul>

            <h3>1.2 Información del Pedido</h3>
            <p>Guardamos los detalles de tus pedidos:</p>
            <ul>
                <li>Productos ordenados y cantidades</li>
                <li>Fecha y hora del pedido</li>
                <li>Método de pago seleccionado</li>
                <li>Preferencias especiales o comentarios</li>
            </ul>

            <h3>1.3 Datos Técnicos</h3>
            <p>Recopilamos información técnica automáticamente:</p>
            <ul>
                <li>Dirección IP y tipo de dispositivo</li>
                <li>Navegador web utilizado</li>
                <li>Páginas visitadas en nuestro sitio</li>
                <li>Tiempo de permanencia y patrones de navegación</li>
            </ul>

            <h2>2. Uso de tu Información</h2>
            
            <p>Utilizamos tus datos personales únicamente para:</p>
            <ul>
                <li><strong>Procesar pedidos:</strong> Preparar y entregar tu comida</li>
                <li><strong>Comunicación:</strong> Confirmaciones, actualizaciones de estado y soporte</li>
                <li><strong>Mejorar el servicio:</strong> Analizar preferencias y optimizar la experiencia</li>
                <li><strong>Marketing:</strong> Enviar promociones y ofertas (solo si aceptaste recibirlas)</li>
                <li><strong>Cumplimiento legal:</strong> Mantener registros según lo requiere la ley</li>
            </ul>

            <div class="highlight-box">
                <p><strong><i class="fas fa-lock me-2"></i>Tu información nunca será:</strong></p>
                <ul style="margin-bottom: 0;">
                    <li>Vendida a terceros</li>
                    <li>Compartida con fines publicitarios externos</li>
                    <li>Usada para spam o comunicaciones no solicitadas</li>
                </ul>
            </div>

            <h2>3. Protección de Datos</h2>
            
            <h3>3.1 Medidas de Seguridad</h3>
            <p>Implementamos múltiples capas de seguridad:</p>
            <ul>
                <li><strong>Encriptación SSL/TLS:</strong> Todas las comunicaciones están cifradas</li>
                <li><strong>Contraseñas seguras:</strong> Almacenadas con hash irreversible</li>
                <li><strong>Acceso restringido:</strong> Solo personal autorizado puede ver tus datos</li>
                <li><strong>Copias de seguridad:</strong> Respaldos regulares y seguros</li>
                <li><strong>Monitoreo:</strong> Detección activa de intentos de acceso no autorizado</li>
            </ul>

            <h3>3.2 Retención de Datos</h3>
            <p>
                Conservamos tu información solo durante el tiempo necesario para los fines descritos 
                o según lo requiera la ley. Los datos de pedidos se mantienen por un período mínimo de 
                2 años para cumplir con regulaciones fiscales y contables.
            </p>

            <h2>4. Compartir Información</h2>
            
            <p>Podemos compartir tu información únicamente con:</p>
            <ul>
                <li><strong>Servicios de entrega:</strong> Solo los datos necesarios para completar la entrega</li>
                <li><strong>Procesadores de pago:</strong> Información requerida para procesar transacciones</li>
                <li><strong>Proveedores de servicios:</strong> Empresas que nos ayudan a operar (hosting, email, etc.)</li>
                <li><strong>Autoridades legales:</strong> Si es requerido por ley o para proteger nuestros derechos</li>
            </ul>

            <p>
                <strong>Todos estos terceros están obligados contractualmente a proteger tu información 
                y solo pueden usarla para los fines específicos que autorizamos.</strong>
            </p>

            <h2>5. Tus Derechos</h2>
            
            <p>Tienes derecho a:</p>
            <ul>
                <li><strong>Acceso:</strong> Solicitar una copia de tus datos personales</li>
                <li><strong>Corrección:</strong> Actualizar información incorrecta o incompleta</li>
                <li><strong>Eliminación:</strong> Solicitar la eliminación de tus datos (con excepciones legales)</li>
                <li><strong>Portabilidad:</strong> Recibir tus datos en formato estructurado</li>
                <li><strong>Oposición:</strong> Rechazar ciertos usos de tu información</li>
                <li><strong>Limitación:</strong> Restringir el procesamiento de tus datos</li>
            </ul>

            <div class="highlight-box">
                <p><strong><i class="fas fa-hand-paper me-2"></i>Ejercer tus derechos:</strong></p>
                <p>
                    Para ejercer cualquiera de estos derechos, contáctanos por los medios indicados al final 
                    de esta página. Responderemos a tu solicitud dentro de los 30 días hábiles.
                </p>
            </div>

            <h2>6. Cookies y Tecnologías Similares</h2>
            
            <p>Nuestro sitio utiliza cookies para:</p>
            <ul>
                <li><strong>Esenciales:</strong> Mantener tu sesión y carrito de compras</li>
                <li><strong>Funcionales:</strong> Recordar tus preferencias (idioma, ubicación)</li>
                <li><strong>Analíticas:</strong> Entender cómo usas nuestro sitio</li>
                <li><strong>Marketing:</strong> Mostrarte contenido relevante (con tu consentimiento)</li>
            </ul>

            <p>
                Puedes configurar tu navegador para rechazar cookies, pero esto podría afectar 
                la funcionalidad del sitio. Las cookies esenciales son necesarias para que el 
                sistema funcione correctamente.
            </p>

            <h2>7. Enlaces a Terceros</h2>
            
            <p>
                Nuestro sitio puede contener enlaces a sitios web de terceros (por ejemplo, redes sociales, 
                procesadores de pago). No somos responsables de las prácticas de privacidad de estos sitios. 
                Te recomendamos leer sus políticas de privacidad antes de proporcionarles información.
            </p>

            <h2>8. Menores de Edad</h2>
            
            <p>
                Nuestros servicios están dirigidos a mayores de 18 años. No recopilamos intencionalmente 
                información de menores de edad. Si eres padre o tutor y crees que tu hijo nos ha proporcionado 
                información personal, contáctanos inmediatamente para eliminarla.
            </p>

            <h2>9. Cambios a esta Política</h2>
            
            <p>
                Podemos actualizar esta política de privacidad ocasionalmente. Los cambios significativos 
                serán notificados a través de nuestro sitio web o por email. Te recomendamos revisar 
                esta página periódicamente para estar informado sobre cómo protegemos tu información.
            </p>

            <div class="highlight-box">
                <p><strong><i class="fas fa-calendar-alt me-2"></i>Cambios importantes:</strong></p>
                <p>
                    Cuando realicemos cambios sustanciales que afecten tus derechos, te notificaremos 
                    con al menos 30 días de anticipación y, cuando sea aplicable, solicitaremos tu 
                    consentimiento nuevamente.
                </p>
            </div>

            <h2>10. Transferencias Internacionales</h2>
            
            <p>
                Tus datos se almacenan y procesan en Argentina. Si utilizamos servicios en la nube 
                o proveedores internacionales, nos aseguramos de que cumplan con estándares de protección 
                adecuados mediante cláusulas contractuales estándar u otros mecanismos legales.
            </p>

            <!-- Información de contacto -->
            <div class="contact-info">
                <h4><i class="fas fa-envelope me-2"></i>Contacto para Asuntos de Privacidad</h4>
                <p>
                    Si tienes preguntas, inquietudes o solicitudes relacionadas con tu privacidad 
                    y protección de datos, puedes contactarnos por:
                </p>
                <p><i class="fas fa-building"></i> <strong><?php echo $restaurant_name; ?></strong></p>
                <?php if ($restaurant_email): ?>
                <p><i class="fas fa-envelope"></i> Email: <a href="mailto:<?php echo $restaurant_email; ?>"><?php echo $restaurant_email; ?></a></p>
                <?php endif; ?>
                <?php if ($restaurant_phone): ?>
                <p><i class="fas fa-phone"></i> Teléfono: <a href="tel:<?php echo $restaurant_phone; ?>"><?php echo $restaurant_phone; ?></a></p>
                <?php endif; ?>
            </div>

            <div class="last-updated">
                <p><i class="fas fa-clock me-2"></i>Última actualización: <?php echo date('d/m/Y'); ?></p>
                <p>Versión 2.0 - Sistema de Gestión Gastronómica</p>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="public-footer">
        <div class="container">
            <small>
                <i class="fas fa-utensils me-1"></i>
                Sistema de Gestión Gastronómica v2.1.0
            </small>
            <div class="footer-links">
                <a href="privacy.php">Privacidad</a>
                <a href="terms.php">Términos</a>
                <a href="index.php">Inicio</a>
            </div>
            <small class="d-block mt-2">
                Desarrollado por 
                <a href="https://cellcomweb.com.ar" target="_blank">
                    Cellcom Technology
                </a>
                | © <?php echo date('Y'); ?>
            </small>
        </div>
    </footer>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>