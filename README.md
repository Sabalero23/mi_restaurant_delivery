# Sistema de Gesti¨®n de Restaurante

Un sistema completo de gesti¨®n para restaurantes que incluye punto de venta, pedidos online, gesti¨®n de mesas, cocina, delivery y reportes avanzados.

## ?? Caracter¨ªsticas Principales

### ?? Panel de Administraci¨®n
- **Dashboard en tiempo real** con estad¨ªsticas y notificaciones
- **Gesti¨®n de ¨®rdenes** tradicionales y online
- **Control de mesas** con estados visuales
- **Panel de cocina** con tiempos de preparaci¨®n
- **Gesti¨®n de delivery** con seguimiento
- **Reportes avanzados** con gr¨¢ficos y exportaci¨®n
- **Sistema de usuarios** con roles y permisos
- **Gesti¨®n de productos** y categor¨ªas
- **Configuraci¨®n del sistema** centralizada

### ?? Experiencia del Cliente
- **Men¨² online** responsive con carrito de compras
- **Men¨² QR** para mesas sin contacto
- **Pedidos online** con validaci¨®n de direcciones
- **Integraci¨®n con Google Maps** para delivery
- **Llamada al mesero** desde c¨®digo QR
- **Validaci¨®n de horarios** de atenci¨®n

### ?? Notificaciones en Tiempo Real
- **Alertas sonoras** para nuevos pedidos
- **Notificaciones visuales** con animaciones
- **Sistema de llamadas** de mesa
- **Actualizaciones autom¨¢ticas** del estado

## ??? Tecnolog¨ªas Utilizadas

- **Backend**: PHP 8.0+
- **Base de datos**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5.3
- **Iconos**: Font Awesome 6.0
- **Gr¨¢ficos**: Chart.js 3.9
- **Mapas**: Google Maps API
- **Tablas**: DataTables

## ?? Estructura del Proyecto

```
mi_restaurant_delivery/
©À©¤©¤ index.php # P¨¢gina principal del men¨² online
©À©¤©¤ 404.html # P¨¢gina de error 404
©À©¤©¤ 502.html # P¨¢gina de error 502
©À©¤©¤ menu-qr.php # Men¨² accesible por c¨®digo QR
©À©¤©¤ call_waiter.php # API para generar llamadas de mesero
©À©¤©¤ install.php # Instalador del sistema
©À©¤©¤ README.md # Documentaci¨®n del proyecto
©À©¤©¤ .htaccess # Reglas de Apache (URLs amigables, seguridad, etc.)
©À©¤©¤ estructura de archivos.txt # Archivo de referencia con la estructura del sistema
©¦
©À©¤©¤ models/ # Modelos de datos del sistema
©¦ ©À©¤©¤ Product.php # Modelo de productos
©¦ ©À©¤©¤ Table.php # Modelo de mesas
©¦ ©À©¤©¤ Category.php # Modelo de categor¨ªas
©¦ ©À©¤©¤ Payment.php # Modelo de pagos
©¦ ©¸©¤©¤ Order.php # Modelo de ¨®rdenes
©¦
©À©¤©¤ config/ # Configuraci¨®n global del sistema
©¦ ©À©¤©¤ config.php # Configuraci¨®n general (constantes, variables globales)
©¦ ©À©¤©¤ database.php # Conexi¨®n a la base de datos
©¦ ©À©¤©¤ auth.php # Sistema de autenticaci¨®n y sesiones
©¦ ©¸©¤©¤ functions.php # Funciones auxiliares y utilidades
©¦
©À©¤©¤ admin/ # Panel de administraci¨®n
©¦ ©À©¤©¤ api/ # APIs internas para el frontend
©¦ ©¦ ©À©¤©¤ products.php # API de gesti¨®n de productos
©¦ ©¦ ©À©¤©¤ update-item-status.php # Actualizaci¨®n del estado de ¨ªtems
©¦ ©¦ ©À©¤©¤ delivery-stats.php # Estad¨ªsticas de delivery
©¦ ©¦ ©À©¤©¤ delivery.php # API de gesti¨®n de deliveries
©¦ ©¦ ©À©¤©¤ online-orders-stats.php # Estad¨ªsticas de pedidos online
©¦ ©¦ ©À©¤©¤ online-orders.php # API de pedidos online
©¦ ©¦ ©À©¤©¤ update-delivery.php # Actualizaci¨®n de estado de entregas
©¦ ©¦ ©À©¤©¤ orders.php # API de ¨®rdenes tradicionales
©¦ ©¦ ©À©¤©¤ kitchen.php # API del panel de cocina
©¦ ©¦ ©À©¤©¤ update-order-status.php # Actualizaci¨®n del estado de ¨®rdenes
©¦ ©¦ ©À©¤©¤ create-order.php # Creaci¨®n de ¨®rdenes desde el sistema
©¦ ©¦ ©À©¤©¤ tables.php # API de gesti¨®n de mesas
©¦ ©¦ ©¸©¤©¤ online-orders-recent.php # Listado de pedidos online recientes
©¦ ©¦
©¦ ©À©¤©¤ receipts/ # Archivos de recibos generados
©¦ ©¦ ©¸©¤©¤ customer_ORD-.txt # Ejemplo de recibo de cliente
©¦ ©¦
©¦ ©À©¤©¤ tickets/ # Tickets impresos para cocina/delivery
©¦ ©¦ ©¸©¤©¤ kitchen_ORD-.txt # Ticket de orden en cocina
©¦ ©¦
©¦ ©À©¤©¤ pages/ # P¨¢ginas est¨¢ticas del panel
©¦ ©¦ ©¸©¤©¤ 403.php # P¨¢gina de error 403 (acceso denegado)
©¦ ©¦
©¦ ©À©¤©¤ uploads/ # Archivos subidos en el panel
©¦ ©¦ ©¸©¤©¤ products/ # Im¨¢genes de productos
©¦ ©¦
©¦ ©À©¤©¤ products.php # Gesti¨®n de productos
©¦ ©À©¤©¤ settings.php # Configuraci¨®n general del sistema
©¦ ©À©¤©¤ permissions.php # Gesti¨®n de permisos y roles
©¦ ©À©¤©¤ check_calls.php # Verificaci¨®n de llamadas de mesero
©¦ ©À©¤©¤ delivery.php # Panel de gesti¨®n de deliveries
©¦ ©À©¤©¤ attend_call.php # Atender llamadas de mesero
©¦ ©À©¤©¤ online-orders.php # Gesti¨®n de pedidos online
©¦ ©À©¤©¤ online-order-details.php # Detalle de un pedido online
©¦ ©À©¤©¤ dashboard.php # Dashboard principal con estad¨ªsticas
©¦ ©À©¤©¤ reports.php # Reportes avanzados del sistema
©¦ ©À©¤©¤ orders.php # Gesti¨®n de ¨®rdenes tradicionales
©¦ ©À©¤©¤ kitchen.php # Panel de cocina
©¦ ©À©¤©¤ users.php # Gesti¨®n de usuarios y roles
©¦ ©À©¤©¤ tables.php # Gesti¨®n de mesas
©¦ ©À©¤©¤ order-create.php # Crear o editar ¨®rdenes
©¦ ©À©¤©¤ logout.php # Cerrar sesi¨®n
©¦ ©À©¤©¤ order-details.php # Detalle de una orden
©¦ ©À©¤©¤ print-order.php # Impresi¨®n de ¨®rdenes
©¦ ©¸©¤©¤ login.php # P¨¢gina de login
©¦
©À©¤©¤ assets/ # Recursos est¨¢ticos
©¦ ©À©¤©¤ includes/ # Archivos de inclusi¨®n
©¦ ©À©¤©¤ css/ # Hojas de estilo
©¦ ©À©¤©¤ images/ # Im¨¢genes del sistema
©¦ ©¸©¤©¤ js/ # Scripts JavaScript
©¦
©¸©¤©¤ database/ # Scripts de base de datos
©¸©¤©¤ bd.sql # Estructura y datos iniciales
```

## ?? Instalaci¨®n

### Requisitos del Sistema

- **PHP**: 8.0 o superior
- **MySQL**: 8.0 o superior
- **Apache/Nginx**: Servidor web
- **Extensiones PHP**:
  - PDO
  - PDO_MySQL
  - GD (para im¨¢genes)
  - JSON
  - Session

### Instalaci¨®n Autom¨¢tica

1. **Clonar o descargar** el proyecto en su servidor web (Solicitar Base de datos)
2. **Crear base de datos** MySQL vac¨ªa
3. **Navegar** a `http://su-dominio.com/install.php`
4. **Seguir el asistente** de instalaci¨®n paso a paso:
   - Configurar conexi¨®n a base de datos
   - Crear estructura y datos iniciales
   - Configurar datos del restaurante
   - Crear usuario administrador

### Instalaci¨®n Manual

Si prefiere instalar manualmente:

1. **Configurar base de datos**:
   ```sql
   CREATE DATABASE comidasm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar estructura**:
   ```bash
   mysql -u usuario -p comidasm < database/comidasm.sql
   ```

3. **Configurar archivo de configuraci¨®n**:
   ```php
   // config/config.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'comidasm');
   define('DB_USER', 'tu_usuario');
   define('DB_PASS', 'tu_contrase?a');
   ```

4. **Crear carpetas de permisos**:
   ```bash
   chmod 755 uploads/
   chmod 755 admin/uploads/
   ```

## ?? Usuarios Predefinidos

El sistema incluye usuarios de ejemplo para cada rol:

| Usuario | Contrase?a | Rol | Permisos |
|---------|------------|-----|----------|
| admin | password | Administrador | Acceso completo |
| gerente | password | Gerente | Gesti¨®n completa excepto configuraci¨®n |
| mostrador | password | Mostrador | ¨®rdenes, mesas, cocina, delivery |
| mesero | password | Mesero | ¨®rdenes y mesas |
| cocina | password | Cocina | Panel de cocina |
| delivery | password | Delivery | Gesti¨®n de entregas |

**?? IMPORTANTE**: Cambiar todas las contrase?as despu¨¦s de la instalaci¨®n.

## ?? Configuraci¨®n

### Configuraci¨®n B¨¢sica

Acceder a **Admin > Configuraci¨®n** para ajustar:

- **Datos del restaurante**: Nombre, tel¨¦fono, direcci¨®n
- **Horarios**: Apertura, cierre, cierre de cocina
- **Delivery**: Costo, distancia m¨¢xima, monto m¨ªnimo
- **Pagos**: M¨¦todos aceptados, configuraci¨®n de impuestos
- **Notificaciones**: Sonidos, alertas autom¨¢ticas

### Google Maps (Opcional)

Para habilitar autocompletado de direcciones:

1. **Obtener API Key** de Google Maps
2. **Configurar en**: Admin > Configuraci¨®n > Google Maps API Key
3. **Habilitar APIs**:
   - Places API
   - Geocoding API
   - Maps JavaScript API

### Configuraci¨®n de Roles

El sistema incluye roles predefinidos, pero puede:

- **Crear roles personalizados**
- **Asignar permisos espec¨ªficos**:
  - `all`: Acceso completo
  - `orders`: Gesti¨®n de ¨®rdenes tradicionales
  - `online_orders`: Gesti¨®n de pedidos online
  - `products`: Gesti¨®n de productos
  - `users`: Gesti¨®n de usuarios
  - `tables`: Gesti¨®n de mesas
  - `reports`: Reportes y estad¨ªsticas
  - `kitchen`: Panel de cocina
  - `delivery`: Gesti¨®n de delivery
  - `settings`: Configuraci¨®n del sistema

## ?? M¨®dulos del Sistema

### ?? Dashboard
- **Estad¨ªsticas en tiempo real**
- **¨®rdenes recientes** de todos los tipos
- **Estado de mesas** visual
- **Notificaciones autom¨¢ticas**
- **Accesos r¨¢pidos** seg¨²n el rol

### ?? Gesti¨®n de ¨®rdenes
- **¨®rdenes tradicionales**: Mesa, delivery, retiro
- **Pedidos online**: Integraci¨®n completa
- **Estados de orden**: Pendiente ¡ú Confirmado ¡ú Preparando ¡ú Listo ¡ú Entregado
- **Pagos**: M¨²ltiples m¨¦todos (efectivo, tarjeta, transferencia, QR)
- **Filtros avanzados** por fecha, estado, tipo

### ?? Pedidos Online
- **Sistema completo** de pedidos por internet
- **Carrito de compras** con validaci¨®n
- **Autocompletado de direcciones** con Google Maps
- **Verificaci¨®n de zona** de delivery
- **Formateo autom¨¢tico** de tel¨¦fonos argentinos
- **Confirmaci¨®n por WhatsApp**
- **Estados en tiempo real**
- **Panel de gesti¨®n** dedicado con:
  - Aceptaci¨®n/rechazo de pedidos
  - Tiempos estimados de preparaci¨®n
  - Seguimiento completo del proceso
  - Integraci¨®n con WhatsApp autom¨¢tico
  - Sistema de pagos integrado

### ??? Gesti¨®n de Mesas
- **Vista visual** de todas las mesas
- **Estados**: Libre, ocupada, reservada, mantenimiento
- **Capacidad** y ubicaci¨®n
- **Asignaci¨®n autom¨¢tica** de ¨®rdenes
- **Representaci¨®n gr¨¢fica** con sillas seg¨²n capacidad
- **Acciones r¨¢pidas** desde cada mesa

### ????? Panel de Cocina
- **¨®rdenes por preparar** en tiempo real
- **Tiempos de preparaci¨®n**
- **Estados por item**
- **Priorizaci¨®n autom¨¢tica**
- **Actualizaci¨®n en vivo**

### ??? Gesti¨®n de Delivery
- **¨®rdenes listas** para entrega
- **Informaci¨®n del cliente** completa
- **Direcciones con mapas**
- **Tiempos de entrega**
- **Estado de entrega**

### ??? Sistema de Impresi¨®n
- **Tickets de venta** personalizables
- **Impresi¨®n autom¨¢tica** opcional
- **Formatos m¨²ltiples** (58mm, 80mm)
- **Vista previa** antes de imprimir
- **Informaci¨®n completa** del pedido y pagos

### ?? Reportes Avanzados
- **Ventas diarias** con gr¨¢ficos
- **Productos m¨¢s vendidos**
- **Rendimiento del personal**
- **An¨¢lisis de mesas**
- **M¨¦todos de pago**
- **Comparaci¨®n de per¨ªodos**
- **Exportaci¨®n a Excel/CSV**

### ?? Men¨² QR
- **C¨®digo QR** para cada mesa
- **Men¨² digital** responsive
- **Filtros por categor¨ªa**
- **Llamada al mesero** integrada
- **Sin instalaci¨®n** de apps

### ?? Gesti¨®n de Usuarios
- **Roles y permisos** granulares
- **Interfaz responsive** optimizada para m¨®vil
- **Vista de tarjetas** en dispositivos m¨®viles
- **Filtros por rol** y estado
- **Gesti¨®n de contrase?as**
- **Activaci¨®n/desactivaci¨®n** de usuarios
- **Interfaz t¨¢ctil** optimizada

### ?? Configuraci¨®n Avanzada
- **Configuraci¨®n general** del restaurante
- **Configuraci¨®n de negocio** (impuestos, delivery)
- **Configuraci¨®n de pedidos online**
- **Horarios de atenci¨®n**
- **Integraci¨®n con Google Maps**
- **Configuraciones del sistema**
- **Pruebas de configuraci¨®n** integradas

## ?? Sistema de Llamadas de Mesero

### Funcionalidades
- **Llamada desde c¨®digo QR** de mesa
- **Notificaciones en tiempo real** al personal
- **Estado de llamadas** (pendiente/atendida)
- **Hist¨®rico de llamadas**
- **Integraci¨®n con panel de mesas**

### Archivos del Sistema
- `call_waiter.php`: API para generar llamadas
- `attend_call.php`: Marcar llamadas como atendidas
- `check_calls.php`: Verificar llamadas pendientes

## ?? Seguridad

### Medidas Implementadas
- **Autenticaci¨®n** con hash seguro de contrase?as
- **Autorizaci¨®n** basada en roles y permisos
- **Protecci¨®n CSRF** en formularios
- **Validaci¨®n de datos** en servidor y cliente
- **Escape de HTML** para prevenir XSS
- **Sesiones seguras** con configuraci¨®n httponly
- **Validaci¨®n de archivos** subidos

### Recomendaciones
- **Cambiar contrase?as** predefinidas
- **Usar HTTPS** en producci¨®n
- **Backup regular** de la base de datos
- **Actualizar** PHP y MySQL regularmente
- **Monitorear logs** de acceso

## ?? Personalizaci¨®n

### Temas y Estilos
- **Variables CSS** para colores principales
- **Responsive design** para todos los dispositivos
- **Iconos personalizables** con Font Awesome
- **Animaciones suaves** para mejor UX
- **Interfaz optimizada** para dispositivos t¨¢ctiles

### Funcionalidades Adicionales
El sistema es extensible para agregar:
- **Reservas online**
- **Programa de fidelizaci¨®n**
- **Integraci¨®n con redes sociales**
- **Sistemas de pago online**
- **Facturaci¨®n electr¨®nica**
- **M¨²ltiples sucursales**

## ?? Optimizaci¨®n

### Rendimiento
- **Consultas SQL optimizadas** con ¨ªndices apropiados
- **Caching** de configuraciones
- **Lazy loading** de im¨¢genes
- **Minificaci¨®n** de assets
- **Compresi¨®n** de respuestas

### Escalabilidad
- **Arquitectura modular**
- **APIs REST** para integraci¨®n
- **Base de datos normalizada**
- **C¨®digo reutilizable**

## ?? Soluci¨®n de Problemas

### Problemas Comunes

1. **Error de conexi¨®n a base de datos**:
   - Verificar credenciales en `config/config.php`
   - Comprobar que el servidor MySQL est¨¦ activo

2. **No aparecen im¨¢genes**:
   - Verificar permisos de carpeta `uploads/`
   - Comprobar rutas en la base de datos

3. **Notificaciones no funcionan**:
   - Verificar configuraci¨®n de JavaScript
   - Comprobar permisos del navegador

4. **Google Maps no funciona**:
   - Verificar API Key v¨¢lida
   - Comprobar APIs habilitadas en Google Console

5. **Pedidos online no funcionan**:
   - Verificar configuraci¨®n en Admin > Configuraci¨®n
   - Comprobar horarios de atenci¨®n
   - Verificar conexi¨®n a base de datos

### Logs y Depuraci¨®n
- **Logs de errores**: Activar error_log en PHP
- **Console del navegador**: Para errores de JavaScript
- **Network tab**: Para problemas de APIs

## ?? Soporte

### Archivos de Configuraci¨®n Importantes
- `config/config.php`: Configuraci¨®n principal
- `admin/api/`: APIs del sistema
- `database/comidasm.sql`: Estructura de base de datos

### Informaci¨®n del Sistema
- **Versi¨®n**: 1.0.0
- **Licencia**: MIT
- **PHP m¨ªnimo**: 8.0
- **MySQL m¨ªnimo**: 8.0

### Contacto y Desarrollo
- **Desarrollador**: Cellcom Technology  
- **Sitio Web**: [www.cellcomweb.com.ar](http://www.cellcomweb.com.ar)  
- **Tel¨¦fono / WhatsApp**: +54 3482 549555  
- **Direcci¨®n**: Calle 9 N¡ã 539, Avellaneda, Santa Fe, Argentina  
- **Soporte T¨¦cnico**: Disponible v¨ªa WhatsApp y web

## ?? Puesta en Producci¨®n

### Lista de Verificaci¨®n

- [ ] Cambiar todas las contrase?as predefinidas
- [ ] Configurar datos reales del restaurante
- [ ] Subir im¨¢genes de productos
- [ ] Configurar Google Maps API (opcional)
- [ ] Probar pedidos online completos
- [ ] Verificar horarios de atenci¨®n
- [ ] Configurar m¨¦todos de pago
- [ ] Probar notificaciones
- [ ] Backup de base de datos
- [ ] Certificado SSL configurado
- [ ] Probar sistema de llamadas de mesero
- [ ] Verificar impresi¨®n de tickets
- [ ] Configurar usuarios del personal

### Variables de Entorno Recomendadas

```php
// Producci¨®n
define('DEBUG_MODE', false);
define('ENVIRONMENT', 'production');

// Desarrollo
define('DEBUG_MODE', true);
define('ENVIRONMENT', 'development');
```

## ?? Changelog

### Versi¨®n 1.0.0
- Sistema completo de gesti¨®n de restaurante
- Pedidos online integrados con panel dedicado
- Panel de administraci¨®n responsive
- Reportes con gr¨¢ficos avanzados
- Sistema de roles y permisos granular
- Notificaciones en tiempo real
- Men¨² QR para mesas
- Integraci¨®n con Google Maps
- Sistema de llamadas de mesero
- Gesti¨®n completa de usuarios con interfaz m¨®vil
- Sistema de impresi¨®n de tickets personalizable
- Configuraci¨®n avanzada del sistema
- Interfaz optimizada para dispositivos t¨¢ctiles

---

**?Bienvenido al futuro de la gesti¨®n de restaurantes!** ???

Para soporte adicional o consultas, revise la documentaci¨®n t¨¦cnica en los comentarios del c¨®digo fuente.# mi_restaurant_delivery