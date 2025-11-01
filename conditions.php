<?php
// service-conditions.php - Condiciones de Servicio
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';

$settings = getSettings();
$restaurant_name = $settings['restaurant_name'] ?? 'Mi Restaurante';
$restaurant_email = $settings['restaurant_email'] ?? 'contacto@restaurante.com';
$restaurant_phone = $settings['restaurant_phone'] ?? '';
$restaurant_address = $settings['restaurant_address'] ?? '';
$whatsapp_number = $settings['whatsapp_number'] ?? '';

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
    <title>Condiciones de Servicio - <?php echo $restaurant_name; ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?php echo $current_theme['primary_color'] ?? '#667eea'; ?>;
            --secondary-color: <?php echo $current_theme['secondary_color'] ?? '#764ba2'; ?>;
            --accent-color: <?php echo $current_theme['accent_color'] ?? '#ff6b6b'; ?>;
            --success-color: #28a745;
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

        .success-box {
            background: linear-gradient(135deg, rgba(40, 167, 69, 0.1) 0%, rgba(34, 139, 34, 0.1) 100%);
            border-left: 4px solid var(--success-color);
            padding: 1.5rem;
            margin: 1.5rem 0;
            border-radius: 8px;
        }

        .success-box strong {
            color: var(--success-color);
        }

        .info-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .info-card h4 {
            color: var(--primary-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .info-card .icon-item {
            display: flex;
            align-items: start;
            margin-bottom: 1rem;
            gap: 1rem;
        }

        .info-card .icon-item i {
            color: var(--primary-color);
            font-size: 1.5rem;
            min-width: 30px;
            margin-top: 0.2rem;
        }

        .table-responsive {
            margin: 1.5rem 0;
        }

        .table {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .table thead {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(102, 126, 234, 0.05);
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

        .badge-custom {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
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

            .footer-links a {
                display: block;
                margin: 0.5rem 0;
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
            <h1><i class="fas fa-handshake me-2"></i>Condiciones de Servicio</h1>
            <p class="subtitle"><?php echo $restaurant_name; ?> - Información Operativa</p>
        </div>
    </div>

    <!-- Contenido principal -->
    <div class="container my-5">
        <div class="content-section">
            <div class="highlight-box">
                <p><strong><i class="fas fa-info-circle me-2"></i>Información importante sobre nuestro servicio:</strong></p>
                <p>
                    Estas condiciones describen cómo funciona nuestro servicio de pedidos online y entrega a domicilio 
                    en <strong><?php echo $restaurant_name; ?></strong>. Léelas cuidadosamente para aprovechar al máximo 
                    tu experiencia con nosotros.
                </p>
            </div>

            <h2>1. Horarios de Atención</h2>
            
            <h3>1.1 Horario del Local</h3>
            <div class="info-card">
                <h4><i class="fas fa-clock me-2"></i>Horarios de Apertura</h4>
                <div class="icon-item">
                    <i class="fas fa-calendar-day"></i>
                    <div>
                        <strong>Lunes a Viernes:</strong><br>
                        11:00 - 15:00 (Almuerzo) | 19:00 - 23:30 (Cena)
                    </div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-calendar-week"></i>
                    <div>
                        <strong>Sábados y Domingos:</strong><br>
                        12:00 - 16:00 | 19:00 - 00:00
                    </div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-utensils"></i>
                    <div>
                        <strong>Horario de Cocina:</strong><br>
                        La cocina cierra 30 minutos antes del cierre del local
                    </div>
                </div>
            </div>

            <h3>1.2 Pedidos Online</h3>
            <p>
                Los pedidos online se aceptan durante todo el horario de atención. Sin embargo, ten en cuenta que:
            </p>
            <ul>
                <li>Los pedidos recibidos fuera del horario se procesarán al día siguiente</li>
                <li>Durante horarios pico (12:00-14:00 y 20:00-22:00) los tiempos de entrega pueden extenderse</li>
                <li>En días festivos o eventos especiales, los horarios pueden variar</li>
                <li>Nos reservamos el derecho de pausar pedidos temporalmente por saturación</li>
            </ul>

            <h2>2. Zona de Cobertura y Entregas</h2>
            
            <h3>2.1 Áreas de Entrega</h3>
            <p>Realizamos entregas a domicilio en las siguientes zonas:</p>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Zona</th>
                            <th>Radio</th>
                            <th>Tiempo Estimado</th>
                            <th>Costo de Envío</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge bg-success badge-custom">Zona 1</span></td>
                            <td>0-2 km del local</td>
                            <td>20-30 minutos</td>
                            <td>Gratis (compra mín. $5000)</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-info badge-custom">Zona 2</span></td>
                            <td>2-4 km del local</td>
                            <td>30-45 minutos</td>
                            <td>$500 (compra mín. $6000)</td>
                        </tr>
                        <tr>
                            <td><span class="badge bg-warning badge-custom">Zona 3</span></td>
                            <td>4-6 km del local</td>
                            <td>45-60 minutos</td>
                            <td>$800 (compra mín. $7000)</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="success-box">
                <p><strong><i class="fas fa-shipping-fast me-2"></i>Envío gratis:</strong></p>
                <p>
                    Los pedidos superiores a <strong>$10.000</strong> tienen envío gratuito a todas las zonas. 
                    Aprovecha esta promoción para ahorrar en tu pedido.
                </p>
            </div>

            <h3>2.2 Verificación de Zona</h3>
            <p>Antes de confirmar tu pedido:</p>
            <ul>
                <li>El sistema verifica automáticamente si tu dirección está dentro de nuestra zona de cobertura</li>
                <li>Si tu dirección está fuera de zona, te notificaremos inmediatamente</li>
                <li>Puedes consultar disponibilidad contactándonos antes de ordenar</li>
            </ul>

            <h2>3. Tiempos de Preparación y Entrega</h2>
            
            <h3>3.1 Tiempo de Preparación</h3>
            <p>Los tiempos varían según el tipo de pedido:</p>
            
            <div class="info-card">
                <div class="icon-item">
                    <i class="fas fa-hamburger"></i>
                    <div>
                        <strong>Comidas rápidas (hamburguesas, sándwiches):</strong><br>
                        15-20 minutos de preparación
                    </div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-pizza-slice"></i>
                    <div>
                        <strong>Pizzas y empanadas:</strong><br>
                        20-30 minutos de preparación
                    </div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-drumstick-bite"></i>
                    <div>
                        <strong>Platos elaborados (carnes, pastas):</strong><br>
                        25-40 minutos de preparación
                    </div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-birthday-cake"></i>
                    <div>
                        <strong>Postres y bebidas:</strong><br>
                        5-10 minutos de preparación
                    </div>
                </div>
            </div>

            <h3>3.2 Factores que Afectan el Tiempo</h3>
            <p>Ten en cuenta que los tiempos pueden extenderse por:</p>
            <ul>
                <li><strong>Volumen de pedidos:</strong> Horarios pico o días festivos</li>
                <li><strong>Complejidad del pedido:</strong> Modificaciones especiales o pedidos grandes</li>
                <li><strong>Clima adverso:</strong> Lluvia, tormenta o condiciones peligrosas</li>
                <li><strong>Tráfico:</strong> Hora pico o eventos en la ciudad</li>
                <li><strong>Disponibilidad de personal:</strong> Ausencias o situaciones especiales</li>
            </ul>

            <h3>3.3 Seguimiento de Pedido</h3>
            <p>Una vez confirmado tu pedido, recibirás:</p>
            <ol>
                <li><strong>Confirmación inmediata:</strong> Con número de pedido y resumen</li>
                <li><strong>En preparación:</strong> Cuando la cocina comienza a trabajar en tu pedido</li>
                <li><strong>En camino:</strong> Cuando el repartidor sale hacia tu domicilio</li>
                <li><strong>Entregado:</strong> Confirmación final de entrega</li>
            </ol>

            <h2>4. Condiciones de Entrega</h2>
            
            <h3>4.1 Requisitos para la Entrega</h3>
            <div class="highlight-box">
                <p><strong><i class="fas fa-clipboard-check me-2"></i>Al momento de la entrega necesitamos:</strong></p>
                <ul style="margin-bottom: 0;">
                    <li>Que alguien esté en el domicilio para recibir el pedido</li>
                    <li>Acceso seguro al domicilio (portón abierto, timbre funcionando)</li>
                    <li>Indicaciones claras si el domicilio es difícil de encontrar</li>
                    <li>Efectivo en billetes pequeños si pagas en efectivo</li>
                    <li>DNI en caso de venta de bebidas alcohólicas (mayores de 18 años)</li>
                </ul>
            </div>

            <h3>4.2 Protocolo de Entrega</h3>
            <p>Nuestros repartidores:</p>
            <ul>
                <li>Utilizan mochilas térmicas para mantener la temperatura de la comida</li>
                <li>Portan identificación visible del restaurante</li>
                <li>Manejan con precaución y respetan las normas de tránsito</li>
                <li>Te llaman al llegar si es necesario</li>
                <li>Entregan el pedido empacado y en perfecto estado</li>
            </ul>

            <h3>4.3 Entregas sin Contacto</h3>
            <p>
                Si prefieres una entrega sin contacto, indícalo en las notas del pedido. El repartidor:
            </p>
            <ul>
                <li>Dejará el pedido en la puerta</li>
                <li>Tocará el timbre o llamará para avisar</li>
                <li>Se retirará manteniendo distancia</li>
                <li>El pago debe ser online o por transferencia</li>
            </ul>

            <h2>5. Retiro en Local</h2>
            
            <h3>5.1 Cómo Funciona</h3>
            <p>Si prefieres retirar tu pedido en nuestro local:</p>
            <ol>
                <li>Selecciona la opción "Retiro en local" al hacer el pedido</li>
                <li>Elige un horario estimado de retiro</li>
                <li>Recibirás confirmación cuando esté listo</li>
                <li>Preséntate en el local en el horario indicado</li>
                <li>Menciona tu número de pedido o nombre</li>
            </ol>

            <h3>5.2 Ventajas del Retiro</h3>
            <div class="success-box">
                <p><strong><i class="fas fa-gift me-2"></i>Beneficios del retiro en local:</strong></p>
                <ul style="margin-bottom: 0;">
                    <li><strong>Sin costo de envío:</strong> Ahorra el costo de delivery</li>
                    <li><strong>Más rápido:</strong> No dependes del tiempo de entrega</li>
                    <li><strong>Descuento especial:</strong> 5% de descuento en pedidos para retiro</li>
                    <li><strong>Asesoramiento:</strong> Puedes consultarnos sobre los productos</li>
                </ul>
            </div>

            <h3>5.3 Tiempo de Espera</h3>
            <p>
                Los pedidos para retiro tienen un tiempo máximo de espera de <strong>30 minutos</strong> 
                desde la hora coordinada. Después de este tiempo, el pedido podrá ser cancelado y no 
                se realizará reembolso si ya fue pagado online.
            </p>

            <h2>6. Métodos de Pago</h2>
            
            <h3>6.1 Pago en Efectivo</h3>
            <p>Al pagar en efectivo:</p>
            <ul>
                <li>Ten el monto exacto o billetes pequeños</li>
                <li>El repartidor puede dar vuelto hasta $2000</li>
                <li>Recibirás tu comprobante fiscal</li>
                <li>Solo disponible para entrega a domicilio o retiro en local</li>
            </ul>

            <h3>6.2 Transferencia Bancaria</h3>
            <p>Para pagar por transferencia:</p>
            <ol>
                <li>Solicita los datos bancarios al confirmar el pedido</li>
                <li>Realiza la transferencia por el monto exacto</li>
                <li>Envía el comprobante por WhatsApp</li>
                <li>Una vez verificado el pago, procesamos tu pedido</li>
            </ol>

            <h3>6.3 Mercado Pago y Tarjetas</h3>
            <p>
                Aceptamos pagos online mediante Mercado Pago, tarjetas de débito y crédito. 
                Los pagos son procesados de forma segura y recibirás confirmación inmediata.
            </p>

            <h2>7. Políticas de Calidad</h2>
            
            <h3>7.1 Garantía de Frescura</h3>
            <p>Nos comprometemos a:</p>
            <ul>
                <li>Utilizar ingredientes frescos del día</li>
                <li>Preparar cada pedido al momento</li>
                <li>Mantener la cadena de frío en todo momento</li>
                <li>Usar empaques que preserven temperatura y calidad</li>
                <li>Verificar cada pedido antes del envío</li>
            </ul>

            <h3>7.2 Control de Calidad</h3>
            <p>Cada pedido pasa por un proceso de verificación:</p>
            <ol>
                <li><strong>Preparación:</strong> Siguiendo recetas y estándares establecidos</li>
                <li><strong>Revisión:</strong> Control de temperatura y presentación</li>
                <li><strong>Empaquetado:</strong> Embalaje seguro y hermético</li>
                <li><strong>Etiquetado:</strong> Identificación clara del contenido</li>
                <li><strong>Despacho:</strong> Verificación final antes de salir</li>
            </ol>

            <h2>8. Modificaciones y Pedidos Especiales</h2>
            
            <h3>8.1 Modificaciones Permitidas</h3>
            <p>Puedes solicitar:</p>
            <ul>
                <li>Quitar ingredientes que no te gusten</li>
                <li>Cambiar nivel de cocción (carnes)</li>
                <li>Agregar ingredientes extras (con cargo adicional)</li>
                <li>Modificar nivel de picante o condimentos</li>
                <li>Solicitar salsas o aderezos adicionales</li>
            </ul>

            <h3>8.2 Limitaciones</h3>
            <p>No podemos:</p>
            <ul>
                <li>Modificar recetas o preparaciones base de forma sustancial</li>
                <li>Sustituir ingredientes principales por otros</li>
                <li>Preparar platos que no estén en nuestro menú</li>
                <li>Garantizar ausencia total de alérgenos por contaminación cruzada</li>
            </ul>

            <h2>9. Promociones y Descuentos</h2>
            
            <h3>9.1 Tipos de Promociones</h3>
            <div class="info-card">
                <div class="icon-item">
                    <i class="fas fa-percentage"></i>
                    <div>
                        <strong>Descuentos por volumen:</strong><br>
                        10% en pedidos superiores a $15.000
                    </div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-users"></i>
                    <div>
                        <strong>Cliente frecuente:</strong><br>
                        Acumula puntos y obtén beneficios especiales
                    </div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-calendar-alt"></i>
                    <div>
                        <strong>Promociones semanales:</strong><br>
                        Ofertas especiales cada semana
                    </div>
                </div>
                <div class="icon-item">
                    <i class="fas fa-birthday-cake"></i>
                    <div>
                        <strong>Cumpleaños:</strong><br>
                        Descuento especial en tu mes de cumpleaños
                    </div>
                </div>
            </div>

            <h3>9.2 Condiciones Generales</h3>
            <ul>
                <li>Las promociones no son acumulables salvo indicación expresa</li>
                <li>Validez según fechas y horarios especificados</li>
                <li>Sujetas a disponibilidad de stock</li>
                <li>No aplicables a pedidos ya realizados</li>
            </ul>

            <h2>10. Atención al Cliente</h2>
            
            <h3>10.1 Canales de Contacto</h3>
            <div class="contact-info">
                <h4><i class="fas fa-headset me-2"></i>¿Necesitas ayuda? Contáctanos</h4>
                
                <?php if ($whatsapp_number): ?>
                <p>
                    <i class="fas fa-whatsapp"></i> 
                    <strong>WhatsApp:</strong> 
                    <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $whatsapp_number); ?>" target="_blank">
                        <?php echo $whatsapp_number; ?>
                    </a>
                    <span class="badge bg-success ms-2">Respuesta rápida</span>
                </p>
                <?php endif; ?>
                
                <?php if ($restaurant_phone): ?>
                <p>
                    <i class="fas fa-phone"></i> 
                    <strong>Teléfono:</strong> 
                    <a href="tel:<?php echo $restaurant_phone; ?>"><?php echo $restaurant_phone; ?></a>
                </p>
                <?php endif; ?>
                
                <?php if ($restaurant_email): ?>
                <p>
                    <i class="fas fa-envelope"></i> 
                    <strong>Email:</strong> 
                    <a href="mailto:<?php echo $restaurant_email; ?>"><?php echo $restaurant_email; ?></a>
                </p>
                <?php endif; ?>
                
                <?php if ($restaurant_address): ?>
                <p>
                    <i class="fas fa-map-marker-alt"></i> 
                    <strong>Dirección:</strong> <?php echo $restaurant_address; ?>
                </p>
                <?php endif; ?>
                
                <p class="mt-3">
                    <strong>Horario de atención telefónica:</strong><br>
                    Lunes a Domingo: 11:00 - 23:00 hs
                </p>
            </div>

            <h3>10.2 Tiempo de Respuesta</h3>
            <ul>
                <li><strong>WhatsApp:</strong> Respuesta inmediata en horario de atención</li>
                <li><strong>Teléfono:</strong> Atención inmediata durante horarios operativos</li>
                <li><strong>Email:</strong> Respuesta en menos de 24 horas hábiles</li>
            </ul>

            <h2>11. Quejas y Sugerencias</h2>
            
            <h3>11.1 Proceso de Reclamos</h3>
            <p>Si no estás satisfecho con tu pedido:</p>
            <ol>
                <li>Contáctanos inmediatamente (dentro de las 2 horas)</li>
                <li>Proporciona tu número de pedido</li>
                <li>Describe el problema con detalle</li>
                <li>Envía fotos si es necesario</li>
                <li>Recibirás una solución en menos de 24 horas</li>
            </ol>

            <h3>11.2 Sugerencias y Comentarios</h3>
            <p>
                Valoramos tu opinión. Puedes enviarnos tus sugerencias, comentarios o ideas para mejorar 
                nuestro servicio a través de cualquiera de nuestros canales de contacto.
            </p>

            <div class="success-box">
                <p><strong><i class="fas fa-star me-2"></i>Tu satisfacción es nuestra prioridad:</strong></p>
                <p>
                    Trabajamos constantemente para mejorar nuestro servicio. Cada comentario nos ayuda a 
                    crecer y ofrecerte una mejor experiencia.
                </p>
            </div>

            <h2>12. Compromisos de Calidad</h2>
            
            <p>En <strong><?php echo $restaurant_name; ?></strong> nos comprometemos a:</p>
            <ul>
                <li><i class="fas fa-check-circle text-success me-2"></i>Ofrecer productos de la más alta calidad</li>
                <li><i class="fas fa-check-circle text-success me-2"></i>Mantener estrictas normas de higiene y seguridad</li>
                <li><i class="fas fa-check-circle text-success me-2"></i>Brindar un servicio rápido y eficiente</li>
                <li><i class="fas fa-check-circle text-success me-2"></i>Atender tus consultas y reclamos con profesionalismo</li>
                <li><i class="fas fa-check-circle text-success me-2"></i>Respetar los tiempos de entrega estimados</li>
                <li><i class="fas fa-check-circle text-success me-2"></i>Proteger tu información personal</li>
                <li><i class="fas fa-check-circle text-success me-2"></i>Mejorar continuamente basándonos en tu feedback</li>
            </ul>

            <div class="last-updated">
                <p><i class="fas fa-clock me-2"></i>Última actualización: <?php echo date('d/m/Y'); ?></p>
                <p>Versión 2.0 - Sistema de Gestión Gastronómica</p>
                <p class="mt-2">
                    Estas condiciones pueden ser actualizadas periódicamente. Te recomendamos revisarlas 
                    regularmente para estar al tanto de cualquier cambio.
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
                <a href="service-conditions.php">Condiciones de Servicio</a>
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