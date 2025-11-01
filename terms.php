<?php
// terms.php - Términos y Condiciones de Uso
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$restaurant_email = $settings['restaurant_email'] ?? 'contacto@restaurante.com';
$restaurant_phone = $settings['restaurant_phone'] ?? '';
$restaurant_address = $settings['restaurant_address'] ?? '';

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
    <title>Términos y Condiciones - <?php echo $restaurant_name; ?></title>
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

        .content-section ul, .content-section ol {
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

        .warning-box {
            background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 152, 0, 0.1) 100%);
            border-left: 4px solid #ffc107;
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 8px;
        }

        .warning-box strong {
            color: #f57c00;
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

            .content-section ul, .content-section ol {
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
            <h1><i class="fas fa-file-contract me-2"></i>Términos y Condiciones</h1>
            <p class="subtitle"><?php echo $restaurant_name; ?></p>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="container my-5">
        <div class="content-section">
            <div class="highlight-box">
                <p><strong><i class="fas fa-info-circle me-2"></i>Bienvenido a nuestro servicio:</strong></p>
                <p>
                    Al utilizar nuestro sitio web y realizar pedidos online en <strong><?php echo $restaurant_name; ?></strong>, 
                    aceptas los siguientes términos y condiciones. Por favor, léelos cuidadosamente antes de usar 
                    nuestros servicios.
                </p>
            </div>

            <h2>1. Aceptación de los Términos</h2>
            
            <p>
                Al acceder y utilizar este sitio web, el sistema de pedidos online, o cualquier servicio ofrecido 
                por <strong><?php echo $restaurant_name; ?></strong>, aceptas estar legalmente vinculado por estos 
                términos y condiciones, así como por nuestra Política de Privacidad.
            </p>

            <p>
                Si no estás de acuerdo con alguna parte de estos términos, por favor no utilices nuestros servicios. 
                Nos reservamos el derecho de modificar estos términos en cualquier momento, y tu uso continuado del 
                servicio constituye tu aceptación de dichos cambios.
            </p>

            <h2>2. Servicios Ofrecidos</h2>
            
            <h3>2.1 Pedidos Online</h3>
            <p>Nuestro sistema te permite:</p>
            <ul>
                <li>Visualizar nuestro menú completo con precios actualizados</li>
                <li>Realizar pedidos para entrega a domicilio o retiro en local</li>
                <li>Pagar mediante los métodos de pago habilitados</li>
                <li>Recibir confirmaciones y actualizaciones de tu pedido</li>
                <li>Acceder a promociones y ofertas especiales</li>
            </ul>

            <h3>2.2 Disponibilidad del Servicio</h3>
            <p>
                Nuestros servicios están disponibles durante nuestros horarios de atención. Nos reservamos el 
                derecho de modificar horarios, suspender temporalmente el servicio, o rechazar pedidos sin 
                previo aviso por razones operativas, técnicas o de fuerza mayor.
            </p>

            <h2>3. Proceso de Pedidos</h2>
            
            <h3>3.1 Realización de Pedidos</h3>
            <p>Para realizar un pedido debes:</p>
            <ol>
                <li>Seleccionar los productos del menú y agregarlos al carrito</li>
                <li>Proporcionar información de contacto válida (nombre, teléfono, dirección)</li>
                <li>Revisar el resumen del pedido y el total a pagar</li>
                <li>Confirmar el pedido y seleccionar el método de pago</li>
                <li>Recibir la confirmación por WhatsApp o email</li>
            </ol>

            <div class="warning-box">
                <p><strong><i class="fas fa-exclamation-triangle me-2"></i>Importante:</strong></p>
                <p>
                    Al confirmar un pedido, estás realizando una oferta de compra legalmente vinculante. 
                    Asegúrate de revisar cuidadosamente todos los detalles antes de confirmar.
                </p>
            </div>

            <h3>3.2 Confirmación de Pedidos</h3>
            <p>
                Una vez realizado el pedido, recibirás una confirmación inmediata. Sin embargo, la aceptación 
                final del pedido está sujeta a:
            </p>
            <ul>
                <li>Disponibilidad de productos en stock</li>
                <li>Verificación de datos de contacto y entrega</li>
                <li>Capacidad operativa del restaurante</li>
                <li>Validación del área de entrega (si aplica)</li>
            </ul>

            <p>
                Nos reservamos el derecho de rechazar o cancelar pedidos por motivos justificados, incluyendo 
                pero no limitado a: errores en precios, falta de stock, problemas de pago, o dirección fuera 
                de nuestra zona de cobertura.
            </p>

            <h3>3.3 Modificación y Cancelación</h3>
            <p>
                <strong>Modificación:</strong> Puedes solicitar modificaciones a tu pedido contactándonos 
                inmediatamente después de realizarlo. No garantizamos que podamos modificar pedidos que ya 
                estén en preparación.
            </p>
            <p>
                <strong>Cancelación:</strong> Las cancelaciones solo serán aceptadas si el pedido no ha comenzado 
                a prepararse. Una vez iniciada la preparación, no se aceptarán cancelaciones. Para solicitar una 
                cancelación, contáctanos lo antes posible.
            </p>

            <h2>4. Precios y Pagos</h2>
            
            <h3>4.1 Precios</h3>
            <ul>
                <li>Todos los precios están expresados en pesos argentinos (ARS)</li>
                <li>Los precios incluyen IVA cuando corresponda</li>
                <li>Nos reservamos el derecho de modificar precios sin previo aviso</li>
                <li>El precio aplicable será el vigente al momento de confirmar el pedido</li>
                <li>Los costos de envío (si aplican) se informan antes de confirmar el pedido</li>
            </ul>

            <h3>4.2 Métodos de Pago</h3>
            <p>Aceptamos los siguientes métodos de pago:</p>
            <ul>
                <li><strong>Efectivo:</strong> Al momento de la entrega o retiro</li>
                <li><strong>Transferencia bancaria:</strong> Mediante CBU/CVU proporcionado</li>
                <li><strong>Mercado Pago:</strong> A través de la plataforma integrada</li>
                <li><strong>Tarjetas de débito/crédito:</strong> Según disponibilidad</li>
            </ul>

            <h3>4.3 Facturación</h3>
            <p>
                Emitiremos comprobante fiscal (factura o ticket) según lo establecido por la AFIP. 
                Si necesitas factura A o B, debes informarlo al momento de realizar el pedido proporcionando 
                los datos fiscales necesarios.
            </p>

            <h2>5. Entrega y Retiro</h2>
            
            <h3>5.1 Entrega a Domicilio</h3>
            <ul>
                <li><strong>Zona de cobertura:</strong> Verificamos que tu dirección esté dentro de nuestra área de entrega</li>
                <li><strong>Tiempo estimado:</strong> Te informamos un tiempo aproximado, sujeto a condiciones operativas y de tránsito</li>
                <li><strong>Condiciones climáticas:</strong> El clima adverso puede afectar los tiempos de entrega</li>
                <li><strong>Responsabilidad:</strong> La entrega se considera completa al entregar el pedido en la dirección indicada</li>
            </ul>

            <h3>5.2 Retiro en Local</h3>
            <ul>
                <li>Debes presentarte en el horario coordinado</li>
                <li>Proporciona tu número de pedido o nombre</li>
                <li>Verifica el pedido antes de retirarte</li>
                <li>Los pedidos no retirados en 30 minutos pueden ser cancelados sin reembolso</li>
            </ul>

            <h3>5.3 Recepción del Pedido</h3>
            <div class="warning-box">
                <p><strong><i class="fas fa-exclamation-triangle me-2"></i>Importante - Verificación al recibir:</strong></p>
                <ul style="margin-bottom: 0;">
                    <li>Inspecciona el pedido inmediatamente al recibirlo</li>
                    <li>Verifica que todos los productos estén incluidos</li>
                    <li>Revisa que el pedido esté en buenas condiciones</li>
                    <li>Reporta cualquier problema inmediatamente al repartidor o contactándonos</li>
                </ul>
            </div>

            <p>
                No aceptaremos reclamos sobre productos faltantes o en mal estado si no se reportan 
                en el momento de la entrega o dentro de los 15 minutos posteriores.
            </p>

            <h2>6. Calidad y Seguridad Alimentaria</h2>
            
            <h3>6.1 Compromiso con la Calidad</h3>
            <p>Nos comprometemos a:</p>
            <ul>
                <li>Utilizar ingredientes frescos y de calidad</li>
                <li>Mantener estrictas normas de higiene y manipulación</li>
                <li>Cumplir con todas las regulaciones sanitarias vigentes</li>
                <li>Preparar cada pedido con estándares de calidad consistentes</li>
            </ul>

            <h3>6.2 Alergias e Intolerancias</h3>
            <p>
                <strong>¡IMPORTANTE!</strong> Si tienes alergias o intolerancias alimentarias, es tu responsabilidad:
            </p>
            <ul>
                <li>Informar al realizar el pedido sobre cualquier alergia o restricción alimentaria</li>
                <li>Verificar los ingredientes de cada producto</li>
                <li>Consultar al personal sobre la composición de los platos</li>
            </ul>

            <div class="warning-box">
                <p><strong><i class="fas fa-exclamation-triangle me-2"></i>Advertencia:</strong></p>
                <p>
                    Aunque tomamos precauciones, nuestros productos pueden contener o haber estado en contacto con 
                    alérgenos comunes (gluten, lácteos, frutos secos, mariscos, etc.). Si tienes alergias severas, 
                    consulta antes de realizar tu pedido. No nos hacemos responsables por reacciones alérgicas si 
                    no se nos informó previamente.
                </p>
            </div>

            <h2>7. Devoluciones y Reclamos</h2>
            
            <h3>7.1 Política de Devoluciones</h3>
            <p>
                Debido a la naturaleza perecedera de nuestros productos, no aceptamos devoluciones excepto en 
                los siguientes casos:
            </p>
            <ul>
                <li>Producto incorrecto o faltante</li>
                <li>Producto en mal estado o condición inadecuada</li>
                <li>Problemas de calidad evidentes</li>
                <li>Error en la preparación del pedido</li>
            </ul>

            <h3>7.2 Proceso de Reclamos</h3>
            <p>Para realizar un reclamo debes:</p>
            <ol>
                <li>Contactarnos dentro de las 2 horas posteriores a recibir el pedido</li>
                <li>Proporcionar tu número de pedido y descripción detallada del problema</li>
                <li>Enviar fotografías del producto afectado (si es posible)</li>
                <li>Mantener el producto hasta que evaluemos el reclamo</li>
            </ol>

            <h3>7.3 Soluciones</h3>
            <p>Según el caso, podemos ofrecer:</p>
            <ul>
                <li>Reemplazo del producto afectado</li>
                <li>Crédito para una compra futura</li>
                <li>Reembolso parcial o total</li>
                <li>Compensación equivalente</li>
            </ul>

            <p>
                La solución final queda a nuestro criterio profesional y buena fe, buscando siempre la 
                satisfacción del cliente dentro de lo razonable.
            </p>

            <h2>8. Uso del Sitio Web</h2>
            
            <h3>8.1 Cuenta de Usuario (si aplica)</h3>
            <p>Si creamos un sistema de cuentas de usuario, eres responsable de:</p>
            <ul>
                <li>Mantener la confidencialidad de tu contraseña</li>
                <li>Todas las actividades realizadas bajo tu cuenta</li>
                <li>Notificarnos inmediatamente sobre cualquier uso no autorizado</li>
                <li>Proporcionar información precisa y actualizada</li>
            </ul>

            <h3>8.2 Conducta Prohibida</h3>
            <p>Al usar nuestro sitio web, NO debes:</p>
            <ul>
                <li>Intentar acceder a áreas restringidas del sistema</li>
                <li>Interferir con el funcionamiento normal del sitio</li>
                <li>Usar el servicio para actividades ilegales o fraudulentas</li>
                <li>Realizar pedidos falsos o con información fraudulenta</li>
                <li>Abusar del sistema de promociones o descuentos</li>
                <li>Copiar, modificar o distribuir contenido del sitio sin autorización</li>
            </ul>

            <h3>8.3 Propiedad Intelectual</h3>
            <p>
                Todo el contenido del sitio (textos, imágenes, logos, diseño, código, etc.) es propiedad de 
                <strong><?php echo $restaurant_name; ?></strong> o de sus licenciantes y está protegido por 
                leyes de propiedad intelectual. No puedes reproducir, distribuir o usar este contenido sin 
                autorización explícita.
            </p>

            <h2>9. Limitación de Responsabilidad</h2>
            
            <h3>9.1 Disponibilidad del Servicio</h3>
            <p>
                No garantizamos que el sitio web esté disponible de forma ininterrumpida o libre de errores. 
                Podemos suspender, modificar o discontinuar cualquier aspecto del servicio sin previo aviso.
            </p>

            <h3>9.2 Exclusión de Garantías</h3>
            <p>
                El servicio se proporciona "tal cual" y "según disponibilidad". No garantizamos que el sitio 
                esté libre de virus, malware o componentes dañinos. Es tu responsabilidad implementar medidas 
                de seguridad adecuadas.
            </p>

            <h3>9.3 Limitación de Daños</h3>
            <p>
                En la medida máxima permitida por la ley, <strong><?php echo $restaurant_name; ?></strong> 
                no será responsable por daños indirectos, incidentales, especiales, consecuentes o punitivos, 
                incluyendo pero no limitado a pérdida de beneficios, datos, uso, o cualquier otra pérdida 
                intangible.
            </p>

            <p>
                Nuestra responsabilidad máxima en cualquier caso no excederá el monto total pagado por el 
                pedido específico que dio origen al reclamo.
            </p>

            <h2>10. Fuerza Mayor</h2>
            
            <p>
                No seremos responsables por incumplimientos causados por circunstancias fuera de nuestro 
                control razonable, incluyendo:
            </p>
            <ul>
                <li>Desastres naturales (tormentas, inundaciones, terremotos, etc.)</li>
                <li>Cortes de energía eléctrica o fallas de servicios públicos</li>
                <li>Huelgas, manifestaciones o disturbios civiles</li>
                <li>Pandemias o emergencias sanitarias</li>
                <li>Actos de guerra, terrorismo o vandalismo</li>
                <li>Fallas en sistemas informáticos o de telecomunicaciones</li>
                <li>Restricciones gubernamentales o regulatorias</li>
            </ul>

            <h2>11. Promociones y Descuentos</h2>
            
            <p>Las promociones y descuentos:</p>
            <ul>
                <li>Están sujetos a términos y condiciones específicos que se informan al momento de la oferta</li>
                <li>Pueden tener limitaciones de tiempo, cantidad o productos aplicables</li>
                <li>No son acumulables con otras promociones salvo indicación expresa</li>
                <li>Pueden ser modificados o cancelados sin previo aviso</li>
                <li>No son canjeables por efectivo</li>
            </ul>

            <h2>12. Protección de Datos Personales</h2>
            
            <p>
                El tratamiento de tus datos personales está regulado por nuestra 
                <a href="privacy.php" style="color: var(--primary-color); font-weight: 600;">Política de Privacidad</a>, 
                la cual forma parte integral de estos términos. Te recomendamos leerla cuidadosamente.
            </p>

            <p>
                Cumplimos con la Ley 25.326 de Protección de Datos Personales de Argentina y nos comprometemos 
                a proteger tu información personal.
            </p>

            <h2>13. Modificaciones de los Términos</h2>
            
            <p>
                Nos reservamos el derecho de modificar estos términos y condiciones en cualquier momento. 
                Las modificaciones entrarán en vigor inmediatamente después de su publicación en el sitio web.
            </p>

            <p>
                Tu uso continuado del servicio después de cualquier modificación constituye tu aceptación de 
                los nuevos términos. Te recomendamos revisar periódicamente esta página.
            </p>

            <div class="highlight-box">
                <p><strong><i class="fas fa-bell me-2"></i>Notificación de cambios importantes:</strong></p>
                <p>
                    Cuando realicemos cambios sustanciales que afecten tus derechos u obligaciones, 
                    haremos esfuerzos razonables para notificarte por email o mediante un aviso destacado 
                    en el sitio web.
                </p>
            </div>

            <h2>14. Legislación Aplicable y Jurisdicción</h2>
            
            <p>
                Estos términos se rigen por las leyes de la República Argentina. Cualquier disputa relacionada 
                con estos términos o con el uso de nuestros servicios será sometida a la jurisdicción exclusiva 
                de los tribunales ordinarios de <?php echo $restaurant_address ? 'la localidad de ' . $restaurant_address : 'nuestra jurisdicción'; ?>, 
                Argentina.
            </p>

            <h2>15. Resolución de Disputas</h2>
            
            <h3>15.1 Proceso Amistoso</h3>
            <p>
                Nos comprometemos a resolver cualquier disputa o reclamo de manera amistosa. Si tienes algún 
                problema, contáctanos primero para intentar resolverlo directamente.
            </p>

            <h3>15.2 Mediación</h3>
            <p>
                Si no podemos resolver una disputa de manera amistosa, ambas partes acuerdan intentar la 
                mediación antes de iniciar cualquier acción legal.
            </p>

            <h3>15.3 Defensa del Consumidor</h3>
            <p>
                Tienes derecho a presentar reclamos ante la Dirección Nacional de Defensa del Consumidor 
                o los organismos provinciales correspondientes según la Ley 24.240 de Defensa del Consumidor.
            </p>

            <h2>16. Divisibilidad</h2>
            
            <p>
                Si cualquier disposición de estos términos se considera inválida, ilegal o inaplicable, 
                las demás disposiciones permanecerán en pleno vigor y efecto. La disposición inválida será 
                reemplazada por una válida que se acerque lo más posible a la intención original.
            </p>

            <h2>17. Acuerdo Completo</h2>
            
            <p>
                Estos términos y condiciones, junto con la Política de Privacidad, constituyen el acuerdo 
                completo entre tú y <strong><?php echo $restaurant_name; ?></strong> con respecto al uso de 
                nuestros servicios, y reemplazan cualquier acuerdo previo verbal o escrito.
            </p>

            <h2>18. Renuncia</h2>
            
            <p>
                La falta de ejercicio por nuestra parte de cualquier derecho o disposición de estos términos 
                no constituye una renuncia a dicho derecho o disposición. La renuncia a cualquier derecho o 
                disposición debe ser por escrito y firmada por nosotros.
            </p>

            <!-- Información de contacto -->
            <div class="contact-info">
                <h4><i class="fas fa-envelope me-2"></i>Contacto Legal y Consultas</h4>
                <p>
                    Para consultas, dudas o cuestiones relacionadas con estos términos y condiciones, 
                    puedes contactarnos por:
                </p>
                <p><i class="fas fa-building"></i> <strong><?php echo $restaurant_name; ?></strong></p>
                <?php if ($restaurant_address): ?>
                <p><i class="fas fa-map-marker-alt"></i> Dirección: <?php echo $restaurant_address; ?></p>
                <?php endif; ?>
                <?php if ($restaurant_email): ?>
                <p><i class="fas fa-envelope"></i> Email: <a href="mailto:<?php echo $restaurant_email; ?>"><?php echo $restaurant_email; ?></a></p>
                <?php endif; ?>
                <?php if ($restaurant_phone): ?>
                <p><i class="fas fa-phone"></i> Teléfono: <a href="tel:<?php echo $restaurant_phone; ?>"><?php echo $restaurant_phone; ?></a></p>
                <?php endif; ?>
                <p class="mt-3">
                    <strong>Horario de atención para consultas:</strong><br>
                    Lunes a Viernes: 10:00 - 20:00 hs<br>
                    Sábados: 10:00 - 14:00 hs
                </p>
            </div>

            <div class="last-updated">
                <p><i class="fas fa-clock me-2"></i>Última actualización: <?php echo date('d/m/Y'); ?></p>
                <p>Versión 2.0 - Sistema de Gestión Gastronómica</p>
                <p class="mt-2">
                    Al realizar un pedido o utilizar nuestros servicios, confirmas que has leído, 
                    entendido y aceptado estos términos y condiciones en su totalidad.
                </p>
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