# Sistema de Gesti車n Gastron車mica

Un sistema completo de gesti車n para restaurantes que incluye punto de venta, pedidos online, gesti車n de mesas, cocina, delivery y reportes avanzados.

## ?? Caracter赤sticas Principales

### ?? Panel de Administraci車n
- **Dashboard en tiempo real** con estad赤sticas y notificaciones
- **Gesti車n de 車rdenes** tradicionales y online
- **Control de mesas** con estados visuales
- **Panel de cocina** con tiempos de preparaci車n
- **Gesti車n de delivery** con seguimiento
- **Reportes avanzados** con gr芍ficos y exportaci車n
- **Sistema de usuarios** con roles y permisos
- **Gesti車n de productos** y categor赤as
- **Control de inventario** con seguimiento en tiempo real
- **Configuraci車n del sistema** centralizada
- **Instalador autom芍tico** modular en 5 pasos
- **Control de inventario** con seguimiento en tiempo real

### ?? Experiencia del Cliente
- **Men迆 online** responsive con carrito de compras
- **Men迆 QR** para mesas sin contacto
- **Pedidos online** con validaci車n de direcciones
- **Integraci車n con Google Maps** para delivery
- **Llamada al mesero** desde c車digo QR
- **Validaci車n de horarios** de atenci車n

### ?? Notificaciones en Tiempo Real
- **Alertas sonoras** para nuevos pedidos
- **Notificaciones visuales** con animaciones
- **Sistema de llamadas** de mesa
- **Actualizaciones autom芍ticas** del estado

## ??? Tecnolog赤as Utilizadas

- **Backend**: PHP 8.0+
- **Base de datos**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5.3
- **Iconos**: Font Awesome 6.0
- **Gr芍ficos**: Chart.js 3.9
- **Mapas**: Google Maps API
- **Tablas**: DataTables
- **Gesti車n de Stock**: Sistema de inventario integrado

## ?? Estructura del Proyecto

```
mi_restaurant_delivery/
念岸岸 index.php # P芍gina principal del men迆 online
念岸岸 404.html # P芍gina de error 404
念岸岸 502.html # P芍gina de error 502
念岸岸 menu-qr.php # Men迆 accesible por c車digo QR
念岸岸 call_waiter.php # API para generar llamadas de mesero
念岸岸 install.php # Instalador del sistema
念岸岸 README.md # Documentaci車n del proyecto
念岸岸 .htaccess # Reglas de Apache (URLs amigables, seguridad, etc.)
念岸岸 estructura de archivos.txt # Archivo de referencia con la estructura del sistema
岫
念岸岸 models/ # Modelos de datos del sistema
岫 念岸岸 Product.php # Modelo de productos
岫 念岸岸 Table.php # Modelo de mesas
岫 念岸岸 Category.php # Modelo de categor赤as
岫 念岸岸 Payment.php # Modelo de pagos
岫 弩岸岸 Order.php # Modelo de 車rdenes
岫
念岸岸 config/ # Configuraci車n global del sistema
岫 念岸岸 config.php # Configuraci車n general (constantes, variables globales)
岫 念岸岸 database.php # Conexi車n a la base de datos
岫 念岸岸 auth.php # Sistema de autenticaci車n y sesiones
岫 念岸岸 functions.php # Funciones auxiliares y utilidades
岫 念岸岸 whatsapp_api.php         # Clase de integraci車n con WhatsApp Business API
岫 弩岸岸 theme.php # Clase ThemeManager con toda la l車gica
岫
念岸岸 admin/ # Panel de administraci車n
岫 念岸岸 api/ # APIs internas para el frontend
岫 岫 念岸岸 products.php # API de gesti車n de productos
岫 岫 念岸岸 stock-movements.php # Historial de movimientos de inventario
岫 岫 念岸岸 update-item-status.php # Actualizaci車n del estado de 赤tems
岫 岫 念岸岸 delivery-stats.php # Estad赤sticas de delivery
岫 岫 念岸岸 delivery.php # API de gesti車n de deliveries
岫 岫 念岸岸 online-orders-stats.php # Estad赤sticas de pedidos online
岫 岫 念岸岸 online-orders.php # API de pedidos online
岫 岫 念岸岸 update-delivery.php # Actualizaci車n de estado de entregas
岫 岫 念岸岸 orders.php # API de 車rdenes tradicionales
岫 岫 念岸岸 kitchen.php # API del panel de cocina
岫 岫 念岸岸 update-order-status.php # Actualizaci車n del estado de 車rdenes
岫 岫 念岸岸 create-order.php # Creaci車n de 車rdenes desde el sistema
岫 岫 念岸岸 tables.php # API de gesti車n de mesas
岫 岫 念岸岸 regenerate-css.php # API para regenerar archivos CSS
岫 岫 念岸岸 whatsapp-stats.php   # API de estad赤sticas de WhatsApp
岫 岫 弩岸岸 online-orders-recent.php # Listado de pedidos online recientes
岫 岫
岫 念岸岸 receipts/ # Archivos de recibos generados
岫 岫 弩岸岸 customer_ORD-.txt # Ejemplo de recibo de cliente
岫 岫
岫 念岸岸 tickets/ # Tickets impresos para cocina/delivery
岫 岫 弩岸岸 kitchen_ORD-.txt # Ticket de orden en cocina
岫 岫
岫 念岸岸 pages/ # P芍ginas est芍ticas del panel
岫 岫 弩岸岸 403.php # P芍gina de error 403 (acceso denegado)
岫 岫
岫 念岸岸 uploads/ # Archivos subidos en el panel
岫 岫 弩岸岸 products/ # Im芍genes de productos
岫 岫
岫 念岸岸 products.php # Gesti車n de productos
岫 念岸岸 settings.php # Configuraci車n general del sistema
岫 念岸岸 permissions.php # Gesti車n de permisos y roles
岫 念岸岸 check_calls.php # Verificaci車n de llamadas de mesero
岫 念岸岸 delivery.php # Panel de gesti車n de deliveries
岫 念岸岸 attend_call.php # Atender llamadas de mesero
岫 念岸岸 online-orders.php # Gesti車n de pedidos online
岫 念岸岸 online-order-details.php # Detalle de un pedido online
岫 念岸岸 dashboard.php # Dashboard principal con estad赤sticas
岫 念岸岸 reports.php # Reportes avanzados del sistema
岫 念岸岸 orders.php # Gesti車n de 車rdenes tradicionales
岫 念岸岸 kitchen.php # Panel de cocina
岫 念岸岸 users.php # Gesti車n de usuarios y roles
岫 念岸岸 tables.php # Gesti車n de mesas
岫 念岸岸 profile.php # Gesti車n del perfil de usuario con avatar y configuraci車n personal
岫 念岸岸 order-create.php # Crear o editar 車rdenes
岫 念岸岸 logout.php # Cerrar sesi車n
岫 念岸岸 order-details.php # Detalle de una orden
岫 念岸岸 print-order.php # Impresi車n de 車rdenes
岫 念岸岸 theme-settings.php # Panel principal de configuraci車n de temas
岫 念岸岸 whatsapp-answers.php      # Panel de configuraci車n de respuestas autom芍ticas
岫 念岸岸 whatsapp-settings.php    # Configuraci車n de WhatsApp Business API
岫 念岸岸 whatsapp-messages.php    # Panel de gesti車n de conversaciones WhatsApp  
岫 念岸岸 whatsapp-webhook.php     # Webhook para recibir mensajes de WhatsApp
岫 弩岸岸 login.php # P芍gina de login
岫
念岸岸 assets/ # Recursos est芍ticos
岫 念岸岸 includes/ # Archivos de inclusi車n
岫 念岸岸 css/ # Hojas de estilo
岫 岫 念岸岸 generate-theme.php # Generador de CSS din芍mico
岫 岫 弩岸岸 dynamic-theme.css # Archivo CSS generado autom芍ticamente
岫 岫
岫 念岸岸 images/ # Im芍genes del sistema
岫 弩岸岸 js/ # Scripts JavaScript
岫
弩岸岸 database/ # Scripts de base de datos
弩岸岸 bd.sql # Estructura y datos iniciales
```

## ?? Instalaci車n

### Requisitos del Sistema

- **PHP**: 7.4 o superior
- **MySQL**: 8.0 o superior
- **Apache/Nginx**: Servidor web
- **Extensiones PHP**:
  - PDO
  - PDO_MySQL
  - GD (para im芍genes)
  - JSON
  - Session
  - mbstring
  - openssl
  - curl

### Instalaci車n Autom芍tica (Recomendada)

El sistema incluye un instalador web modular dividido en pasos para una instalaci車n m芍s organizada y mantenible.

1. **Descargar y extraer** el proyecto en su servidor web
2. **Crear base de datos** MySQL vac赤a
3. **Navegar** a `http://su-dominio.com/install/`
4. **Seguir el asistente** de instalaci車n paso a paso:

#### Pasos del Instalador

**Paso 1: Verificaci車n de Requisitos y Configuraci車n de BD**
- Verificaci車n autom芍tica de requisitos del sistema
- Configuraci車n de conexi車n a base de datos
- Generaci車n del archivo `config/config.php`

**Paso 2: Instalaci車n de Estructura de BD**
- Creaci車n autom芍tica de todas las tablas necesarias:
  - Gesti車n de usuarios, roles y permisos
  - Sistema de productos con control de stock
  - Gesti車n de 車rdenes y pagos
  - Sistema de mesas y llamadas de mesero
  - Configuraci車n de temas din芍micos
  - Integraci車n completa de WhatsApp Business API
  - **Tabla `stock_movements`** para historial de inventario
- Inserci車n de datos b芍sicos del sistema
- Configuraci車n de roles y permisos
- Instalaci車n de respuestas autom芍ticas de WhatsApp
- Configuraci車n de temas b芍sicos

**Paso 3: Configuraci車n del Restaurante**
- Datos b芍sicos del negocio
- Configuraci車n de delivery y horarios
- Creaci車n del usuario administrador
- Configuraci車n de APIs (Google Maps, WhatsApp)

**Paso 4: Datos de Ejemplo (Opcional)**
- Usuarios de ejemplo con diferentes roles
- **Productos de muestra con control de stock**:
  - Productos con y sin seguimiento de inventario
  - Configuraci車n de alertas de stock bajo
  - Datos realistas de costos y precios
- Mesas adicionales
- **Este paso funciona independientemente** y puede ejecutarse en cualquier momento

**Paso 5: Finalizaci車n**
- Resumen de la instalaci車n
- Credenciales de acceso
- Enlaces directos al sistema
- Instrucciones de seguridad

### Estructura de Archivos de Instalaci車n

```
install/
念岸岸 index.php              # Archivo principal de instalaci車n
念岸岸 install_common.php     # Funciones compartidas y estructura de BD
念岸岸 step1.php             # Requisitos del sistema y configuraci車n de BD
念岸岸 step2.php             # Instalaci車n de estructura de BD
念岸岸 step3.php             # Configuraci車n del restaurante
念岸岸 step4.php             # Datos de ejemplo (opcional)
弩岸岸 step5.php             # Finalizaci車n
```

### Caracter赤sticas del Instalador

- **Modular**: Cada paso es independiente y mantenible
- **Verificaci車n autom芍tica**: Requisitos del sistema validados
- **Progreso visual**: Indicadores de progreso en cada paso
- **Navegaci車n flexible**: Posibilidad de saltar o repetir pasos
- **Datos de ejemplo opcionales**: El paso 4 puede ejecutarse despu谷s de la instalaci車n principal
- **Seguridad**: Verificaciones y validaciones en cada paso
- **Instalaci車n completa**: Incluye todas las tablas necesarias para:
  - Sistema de productos con control de stock
  - Gesti車n de inventario con historial de movimientos
  - WhatsApp Business API con respuestas autom芍ticas
  - Sistema de temas din芍micos
  - Estructura completa de 車rdenes y pagos

### Base de Datos Instalada

El instalador crea autom芍ticamente las siguientes tablas:

**Sistema Core:**
- `users`, `roles` - Gesti車n de usuarios y permisos
- `settings` - Configuraci車n del sistema
- `categories`, `products` - Gesti車n de productos
- `stock_movements` - **Historial de movimientos de inventario**

**Gesti車n de 車rdenes:**
- `orders`, `order_items` - 車rdenes tradicionales
- `online_orders` - Pedidos online
- `payments`, `online_orders_payments` - Sistema de pagos
- `tables`, `waiter_calls` - Gesti車n de mesas

**Sistema de Temas:**
- `theme_settings` - Configuraci車n de temas
- `custom_themes` - Temas personalizados
- `theme_history` - Historial de cambios

**WhatsApp Business API:**
- `whatsapp_messages` - Conversaciones
- `whatsapp_logs` - Logs de env赤o
- `whatsapp_auto_responses` - Respuestas autom芍ticas
- `whatsapp_media_uploads` - Archivos multimedia

### Post-Instalaci車n

**Importante para la seguridad:**
- ?? **Eliminar toda la carpeta `install/`** despu谷s de completar la instalaci車n
- Cambiar todas las contrase?as predefinidas
- Configurar HTTPS en producci車n
- Verificar permisos de archivos y carpetas

### Soluci車n de Problemas de Instalaci車n

**El sistema ya est芍 instalado:**
- Si aparece este mensaje y desea reinstalar, elimine el archivo `config/installed.lock`
- Para agregar solo datos de ejemplo, acceda directamente a `install/step4.php`

**Error de conexi車n a base de datos:**
- Verificar credenciales de MySQL
- Asegurar que la base de datos existe y est芍 accesible
- Comprobar que las extensiones PHP est芍n instaladas

**Permisos de escritura:**
- Verificar permisos 755 en carpetas de uploads
- Asegurar que el servidor web puede escribir en `config/`

**Requisitos no cumplidos:**
- Actualizar PHP a versi車n 7.4 o superior
- Instalar extensiones PHP faltantes
- Verificar configuraci車n del servidor web

### Instalaci車n Manual (Avanzada)

Si prefiere instalar manualmente:

1. **Configurar base de datos**:
   ```sql
   CREATE DATABASE comidasm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar estructura**:
   ```bash
   mysql -u usuario -p comidasm < database/bd.sql
   ```

3. **Configurar archivo de configuraci車n**:
   ```php
   // config/config.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'comidasm');
   define('DB_USER', 'tu_usuario');
   define('DB_PASS', 'tu_contrase?a');
   
   define('BASE_URL', 'https://tu-dominio.com/');
   define('UPLOAD_PATH', 'uploads/');
   define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
   ```

4. **Crear carpetas con permisos**:
   ```bash
   mkdir -p config uploads uploads/products uploads/categories uploads/avatars whatsapp_media
   chmod 755 uploads/ admin/uploads/ whatsapp_media/
   ```

5. **Crear archivo de instalaci車n completada**:
   ```bash
   echo "$(date)" > config/installed.lock
   ```

### Post-Instalaci車n

**Importante para la seguridad:**
- ?? **Eliminar toda la carpeta `install/`** despu谷s de completar la instalaci車n
- Cambiar todas las contrase?as predefinidas
- Configurar HTTPS en producci車n
- Verificar permisos de archivos y carpetas

### Soluci車n de Problemas de Instalaci車n

**El sistema ya est芍 instalado:**
- Si aparece este mensaje y desea reinstalar, elimine el archivo `config/installed.lock`
- Para agregar solo datos de ejemplo, acceda directamente a `install/step4.php`

**Error de conexi車n a base de datos:**
- Verificar credenciales de MySQL
- Asegurar que la base de datos existe y est芍 accesible
- Comprobar que las extensiones PHP est芍n instaladas

**Permisos de escritura:**
- Verificar permisos 755 en carpetas de uploads
- Asegurar que el servidor web puede escribir en `config/`

**Requisitos no cumplidos:**
- Actualizar PHP a versi車n 7.4 o superior
- Instalar extensiones PHP faltantes
- Verificar configuraci車n del servidor web

## ?? Configuraci車n

### Configuraci車n B芍sica

Acceder a **Admin > Configuraci車n** para ajustar:

- **Datos del restaurante**: Nombre, tel谷fono, direcci車n
- **Horarios**: Apertura, cierre, cierre de cocina
- **Delivery**: Costo, distancia m芍xima, monto m赤nimo
- **Pagos**: M谷todos aceptados, configuraci車n de impuestos
- **Notificaciones**: Sonidos, alertas autom芍ticas

### Google Maps (Opcional)

Para habilitar autocompletado de direcciones:

1. **Obtener API Key** de Google Maps
2. **Configurar en**: Admin > Configuraci車n > Google Maps API Key
3. **Habilitar APIs**:
   - Places API
   - Geocoding API
   - Maps JavaScript API

### Configuraci車n de Roles

El sistema incluye roles predefinidos, pero puede:

- **Crear roles personalizados**
- **Asignar permisos espec赤ficos**:
  - `all`: Acceso completo
  - `orders`: Gesti車n de 車rdenes tradicionales
  - `online_orders`: Gesti車n de pedidos online
  - `products`: Gesti車n de productos
  - `users`: Gesti車n de usuarios
  - `tables`: Gesti車n de mesas
  - `reports`: Reportes y estad赤sticas
  - `kitchen`: Panel de cocina
  - `delivery`: Gesti車n de delivery
  - `settings`: Configuraci車n del sistema

  
### ?? Control de Stock e Inventario

Sistema avanzado de gesti車n de inventario con seguimiento en tiempo real y alertas autom芍ticas.

#### Caracter赤sticas del Sistema de Stock

- **Control opcional por producto** - Activar/desactivar gesti車n de inventario individualmente
- **Seguimiento en tiempo real** - Actualizaci車n autom芍tica de cantidades
- **Alertas de stock bajo** - Notificaciones configurables por producto
- **Historial de movimientos** - Registro completo de entradas y salidas
- **Ajustes manuales** - Correcciones de inventario con motivos
- **Indicadores visuales** - Barras de progreso y badges de estado
- **Estad赤sticas de inventario** - Dashboard con m谷tricas en vivo

#### Funcionalidades Principales

**Gesti車n de Productos con Stock:**
- ? **Activaci車n selectiva** - Control de inventario opcional por producto
- ? **Stock actual** - Cantidad disponible en tiempo real
- ? **L赤mites de alerta** - Configuraci車n personalizada de stock m赤nimo
- ? **Estados visuales** - Sin stock, stock bajo, stock normal
- ? **C芍lculos autom芍ticos** - M芍rgenes de ganancia en tiempo real
- ? **Validaciones robustas** - Prevenci車n de stock negativo

**Panel de Ajustes de Stock:**
- **Modal dedicado** para ajustes r芍pidos de inventario
- **Tipos de movimiento**: Entrada (agregar) y Salida (reducir)
- **Motivos predefinidos**:
  - Ajuste manual
  - Inventario f赤sico
  - Producto da?ado/vencido
  - Venta directa
  - Compra/Reposici車n
  - Correcci車n de error
  - Motivos personalizados
- **Vista previa** del nuevo stock antes de confirmar
- **Alertas autom芍ticas** si el ajuste genera stock cr赤tico

**Dashboard de Inventario:**
- **Productos con control** - Cantidad total bajo seguimiento
- **Stock bueno** - Productos con inventario normal
- **Stock bajo** - Productos cerca del l赤mite m赤nimo
- **Sin stock** - Productos agotados
- **Alertas prominentes** para productos cr赤ticos

**Historial de Movimientos:**
- **Registro completo** de todos los cambios de stock
- **Informaci車n detallada**: Usuario, fecha, cantidad, motivo
- **Trazabilidad total** del inventario
- **Reportes de movimientos** por producto y periodo

#### Caracter赤sticas T谷cnicas del Stock

- **Base de datos optimizada** con tabla `stock_movements`
- **Transacciones seguras** para prevenir inconsistencias
- **Validaciones m迆ltiples** en frontend y backend
- **Interfaz responsive** optimizada para m車viles
- **Integraci車n completa** con sistema de productos existente
- **API REST** para ajustes program芍ticos
- **Logs autom芍ticos** de todas las operaciones

#### Interfaz de Usuario Mejorada

**Tarjetas de Productos:**
- **Indicadores de stock** en esquina superior
- **Barras de progreso** mostrando nivel de inventario
- **Badges din芍micos** (Sin stock, Stock bajo, Disponible)
- **Botones de acci車n r芍pida** para ajustar stock
- **Colores sem芍nticos** seg迆n estado del inventario

**Modal de Productos Expandido:**
- **Secci車n dedicada** de gesti車n de inventario
- **Switch de activaci車n** para control de stock
- **Campos de stock actual** y l赤mite de alerta
- **Indicadores de estado** en tiempo real
- **Validaciones visuales** instant芍neas

**Alertas Inteligentes:**
- **Notificaciones autom芍ticas** de productos con stock bajo
- **Lista expandible** con acciones directas
- **Auto-actualizaci車n** cada vez que se modifica inventario
- **Integraci車n con dashboard** principal

#### Flujo de Trabajo del Stock

1. **Configuraci車n inicial**:
   - Activar control de stock por producto
   - Establecer cantidad inicial
   - Configurar l赤mite de alerta

2. **Operaci車n diaria**:
   - Visualizaci車n autom芍tica de alertas
   - Ajustes r芍pidos desde tarjetas de productos
   - Seguimiento en dashboard de inventario

3. **Gesti車n avanzada**:
   - Ajustes con motivos espec赤ficos
   - Revisi車n de historial de movimientos
   - Reportes de inventario

#### Beneficios del Sistema

- **Control preciso** del inventario sin complejidad excesiva
- **Alertas proactivas** evitan quiebres de stock
- **Trazabilidad completa** para auditor赤as
- **Interfaz intuitiva** sin curva de aprendizaje
- **Integraci車n transparente** con flujo de trabajo existente
- **Flexibilidad total** - usar solo en productos necesarios


## ?? M車dulos del Sistema

### ?? Dashboard
- **Estad赤sticas en tiempo real**
- **車rdenes recientes** de todos los tipos
- **Estado de mesas** visual
- **Notificaciones autom芍ticas**
- **Accesos r芍pidos** seg迆n el rol

### ?? Gesti車n de 車rdenes
- **車rdenes tradicionales**: Mesa, delivery, retiro
- **Pedidos online**: Integraci車n completa
- **Estados de orden**: Pendiente ↙ Confirmado ↙ Preparando ↙ Listo ↙ Entregado
- **Pagos**: M迆ltiples m谷todos (efectivo, tarjeta, transferencia, QR)
- **Filtros avanzados** por fecha, estado, tipo

### ?? Pedidos Online
- **Sistema completo** de pedidos por internet
- **Carrito de compras** con validaci車n
- **Autocompletado de direcciones** con Google Maps
- **Verificaci車n de zona** de delivery
- **Formateo autom芍tico** de tel谷fonos argentinos
- **Confirmaci車n por WhatsApp**
- **Estados en tiempo real**
- **Panel de gesti車n** dedicado con:
  - Aceptaci車n/rechazo de pedidos
  - Tiempos estimados de preparaci車n
  - Seguimiento completo del proceso
  - Integraci車n con WhatsApp autom芍tico
  - Sistema de pagos integrado

### ??? Gesti車n de Mesas
- **Vista visual** de todas las mesas
- **Estados**: Libre, ocupada, reservada, mantenimiento
- **Capacidad** y ubicaci車n
- **Asignaci車n autom芍tica** de 車rdenes
- **Representaci車n gr芍fica** con sillas seg迆n capacidad
- **Acciones r芍pidas** desde cada mesa

### ????? Panel de Cocina
- **車rdenes por preparar** en tiempo real
- **Tiempos de preparaci車n**
- **Estados por item**
- **Priorizaci車n autom芍tica**
- **Actualizaci車n en vivo**

### ??? Gesti車n de Delivery
- **車rdenes listas** para entrega
- **Informaci車n del cliente** completa
- **Direcciones con mapas**
- **Tiempos de entrega**
- **Estado de entrega**

### ??? Sistema de Impresi車n
- **Tickets de venta** personalizables
- **Impresi車n autom芍tica** opcional
- **Formatos m迆ltiples** (58mm, 80mm)
- **Vista previa** antes de imprimir
- **Informaci車n completa** del pedido y pagos

### ?? Reportes Avanzados
- **Ventas diarias** con gr芍ficos
- **Productos m芍s vendidos**
- **Rendimiento del personal**
- **An芍lisis de mesas**
- **M谷todos de pago**
- **Comparaci車n de per赤odos**
- **Exportaci車n a Excel/CSV**

### ?? Men迆 QR
- **C車digo QR** para cada mesa
- **Men迆 digital** responsive
- **Filtros por categor赤a**
- **Llamada al mesero** integrada
- **Sin instalaci車n** de apps

### ?? Gesti車n de Usuarios
- **Roles y permisos** granulares
- **Interfaz responsive** optimizada para m車vil
- **Vista de tarjetas** en dispositivos m車viles
- **Filtros por rol** y estado
- **Gesti車n de contrase?as**
- **Activaci車n/desactivaci車n** de usuarios
- **Interfaz t芍ctil** optimizada

### ?? Perfil de Usuario

Sistema completo de gesti車n de perfiles personales para todos los usuarios del sistema.

#### Caracter赤sticas del Perfil

- **Informaci車n personal completa**:
  - Edici車n de nombre completo
  - Actualizaci車n de email con validaci車n
  - Gesti車n de n迆mero de tel谷fono
  - Visualizaci車n del rol asignado

- **Sistema de avatars avanzado**:
  - Subida de im芍genes de perfil (JPG, PNG, GIF)
  - L赤mite de 2MB por archivo
  - Generaci車n autom芍tica de iniciales si no hay avatar
  - Vista previa antes de subir
  - Eliminaci車n autom芍tica de avatars anteriores

- **Cambio de contrase?a seguro**:
  - Verificaci車n de contrase?a actual
  - Indicador visual de fortaleza de contrase?a
  - Validaci車n de coincidencia en tiempo real
  - Requisito m赤nimo de 6 caracteres
  - Opci車n de mostrar/ocultar contrase?as

- **Estad赤sticas personales**:
  - Fecha de registro en el sistema
  - D赤as activo en la plataforma
  - 迆ltimo acceso registrado
  - Estado actual de la cuenta

#### Funcionalidades T谷cnicas

- **Validaci車n en tiempo real** con JavaScript
- **Compatibilidad autom芍tica** con base de datos existente
- **Creaci車n autom芍tica** de columnas `avatar` y `last_login` si no existen
- **Interfaz responsive** optimizada para dispositivos m車viles
- **Integraci車n completa** con sistema de temas din芍mico
- **Gesti車n segura** de archivos subidos
- **Validaciones robustas** del lado servidor y cliente

#### Seguridad Implementada

- **Verificaci車n de contrase?a actual** antes de cambios
- **Validaci車n de formato** de emails
- **Verificaci車n de unicidad** de emails
- **L赤mites de tama?o** y tipo de archivos
- **Sanitizaci車n** de datos de entrada
- **Protecci車n contra** sobrescritura de archivos

#### Interfaz de Usuario

- **Dise?o moderno** con gradientes y efectos visuales
- **Animaciones suaves** para mejor experiencia
- **Feedback visual inmediato** en formularios
- **Indicadores de estado** para todas las acciones
- **Responsividad completa** para m車viles y tablets
- **Accesibilidad mejorada** con labels y ARIA

Este m車dulo proporciona a cada usuario control total sobre su informaci車n personal y configuraci車n de cuenta, manteniendo la seguridad y consistencia del sistema.

### ?? Configuraci車n Avanzada
- **Configuraci車n general** del restaurante
- **Configuraci車n de negocio** (impuestos, delivery)
- **Configuraci車n de pedidos online**
- **Horarios de atenci車n**
- **Integraci車n con Google Maps**
- **Configuraciones del sistema**
- **Pruebas de configuraci車n** integradas

## ?? Sistema de Llamadas de Mesero

### Funcionalidades
- **Llamada desde c車digo QR** de mesa
- **Notificaciones en tiempo real** al personal
- **Estado de llamadas** (pendiente/atendida)
- **Hist車rico de llamadas**
- **Integraci車n con panel de mesas**

### Archivos del Sistema
- `call_waiter.php`: API para generar llamadas
- `attend_call.php`: Marcar llamadas como atendidas
- `check_calls.php`: Verificar llamadas pendientes

## ?? Seguridad

### Medidas Implementadas
- **Autenticaci車n** con hash seguro de contrase?as
- **Autorizaci車n** basada en roles y permisos
- **Protecci車n CSRF** en formularios
- **Validaci車n de datos** en servidor y cliente
- **Escape de HTML** para prevenir XSS
- **Sesiones seguras** con configuraci車n httponly
- **Validaci車n de archivos** subidos

### Recomendaciones
- **Cambiar contrase?as** predefinidas
- **Usar HTTPS** en producci車n
- **Backup regular** de la base de datos
- **Actualizar** PHP y MySQL regularmente
- **Monitorear logs** de acceso

## ?? Personalizaci車n

### Temas y Estilos
- **Variables CSS** para colores principales
- **Responsive design** para todos los dispositivos
- **Iconos personalizables** con Font Awesome
- **Animaciones suaves** para mejor UX
- **Interfaz optimizada** para dispositivos t芍ctiles

### ?? Sistema de Gesti車n de Estilos Din芍micos

El sistema incluye un potente m車dulo de personalizaci車n de temas que permite modificar la apariencia visual de toda la aplicaci車n en tiempo real.

#### Caracter赤sticas del Sistema de Temas

- **Editor visual de colores** con color pickers interactivos
- **Vista previa en tiempo real** de los cambios
- **Temas predefinidos** profesionales (Predeterminado, Oscuro, Verde, Morado, Azul, Naranja)
- **Generador autom芍tico de paletas de colores**:
  - Colores aleatorios
  - Colores complementarios  
  - Colores an芍logos
- **Configuraci車n de tipograf赤a** con preview en vivo
- **Personalizaci車n de layout** (bordes, espaciado, sidebar)
- **Sistema de importaci車n/exportaci車n** de temas
- **Backup autom芍tico** de configuraciones
- **Validaci車n de integridad** del tema
- **CSS din芍mico** generado autom芍ticamente


#### Uso del Sistema de Temas

1. **Acceder al configurador**: Admin > Configuraci車n > Tema
2. **Personalizar colores**: 
   - Colores principales (primario, secundario, acento)
   - Colores de estado (谷xito, advertencia, peligro, informaci車n)
   - Vista previa instant芍nea de cambios
3. **Configurar tipograf赤a**:
   - Selecci車n de fuentes (Segoe UI, Inter, Roboto, Open Sans, Montserrat, Poppins)
   - Tama?os de fuente (base, peque?o, grande)
   - Preview en tiempo real
4. **Ajustar dise?o**:
   - Radio de bordes (angular, normal, redondeado)
   - Ancho del sidebar
   - Intensidad de sombras
5. **Aplicar temas predefinidos** con un solo clic
6. **Generar paletas autom芍ticas**:
   - Colores aleatorios para inspiraci車n
   - Colores complementarios para alto contraste
   - Colores an芍logos para armon赤a visual

#### Herramientas Avanzadas

- **Exportar tema**: Descarga configuraci車n actual en formato JSON
- **Importar tema**: Carga temas previamente exportados
- **Restablecer**: Vuelve a la configuraci車n predeterminada
- **Regenerar CSS**: Actualiza archivos CSS din芍micos
- **Crear backup**: Respaldo de seguridad de la configuraci車n
- **Validar tema**: Verifica integridad de colores y configuraciones

#### Caracter赤sticas T谷cnicas

- **CSS Variables**: Uso de variables CSS para cambios en tiempo real
- **Responsive design**: Todos los temas se adaptan a dispositivos m車viles
- **Validaci車n robusta**: Verificaci車n de colores hexadecimales y medidas CSS
- **Cache inteligente**: Optimizaci車n de rendimiento
- **Fallback autom芍tico**: CSS de emergencia si hay errores
- **Compatibilidad total**: Funciona con todos los m車dulos del sistema

#### Beneficios

- **Branding personalizado**: Adapta el sistema a la identidad visual del restaurante
- **Mejor experiencia de usuario**: Interface m芍s atractiva y profesional
- **Facilidad de uso**: Sin conocimientos t谷cnicos requeridos
- **Flexibilidad total**: Desde cambios sutiles hasta transformaciones completas
- **Consistencia visual**: Todos los m車dulos mantienen el tema seleccionado
- 

### Funcionalidades Adicionales
El sistema es extensible para agregar:
- **Reservas online**
- **Programa de fidelizaci車n**
- **Integraci車n con redes sociales**
- **Sistemas de pago online**
- **Facturaci車n electr車nica**
- **M迆ltiples sucursales**
 

### ?? Sistema de WhatsApp Business API

El sistema incluye integraci車n completa con WhatsApp Business API para comunicaci車n autom芍tica con clientes y gesti車n de conversaciones avanzadas.

#### Caracter赤sticas del Sistema WhatsApp

- **API de WhatsApp Business** completamente integrada
- **Env赤o autom芍tico** de notificaciones de pedidos (confirmaci車n, preparaci車n, listo, entregado)
- **Webhook autom芍tico** para recibir mensajes entrantes con configuraci車n segura
- **Respuestas autom芍ticas configurables** desde panel web con variables din芍micas
- **Panel de gesti車n de conversaciones** con interface tipo chat
- **Sistema de prioridades** y tipos de coincidencia para respuestas
- **Rate limiting** y detecci車n de duplicados
- **Logs completos** de env赤os y recepciones
- **Configuraci車n din芍mica** del restaurante
- **Limpieza autom芍tica** de n迆meros telef車nicos argentinos
- **Sistema de fallback** a WhatsApp Web si falla la API
- **Guardado de conversaciones completas** para seguimiento

#### Funcionalidades de Mensajer赤a

**Env赤o Autom芍tico:**
- ? **Confirmaciones autom芍ticas** de pedidos online al aceptar
- ? **Actualizaciones de estado** en tiempo real (preparando, listo)
- ? **Notificaciones de entrega** autom芍ticas
- ? **Mensajes de rechazo** con motivo especificado
- ? **Guardado autom芍tico** en conversaciones para seguimiento
- ? **Fallback inteligente** a WhatsApp Web si la API falla

**Sistema de Respuestas Autom芍ticas Avanzado:**
- **Editor web de respuestas** con variables din芍micas
- **Tipos de coincidencia**: Contiene, exacto, empieza con, termina con
- **Sistema de prioridades** (mayor n迆mero = mayor prioridad)
- **Variables autom芍ticas**: `{restaurant_name}`, `{restaurant_web}`, `{restaurant_phone}`, etc.
- **Estad赤sticas de uso** para cada respuesta
- **Activaci車n/desactivaci車n** individual
- **Contador de usos** y fechas de creaci車n/actualizaci車n

**Ejemplos de respuestas configurables:**

| Palabras Clave | Respuesta | Tipo |
|----------------|-----------|------|
| hola,saludos,buenos | ?Hola! Gracias por contactar a {restaurant_name}. Para pedidos: {restaurant_web} | Contiene |
| menu,men迆,carta | Vea nuestro men迆 completo en {restaurant_web} | Contiene |
| horario,horarios | Horarios: {opening_time} - {closing_time} | Contiene |
| estado,pedido | Para consultar estado, proporcione n迆mero de orden | Contiene |
| direccion,ubicacion | Nuestra direcci車n: {restaurant_address} | Contiene |

#### Panel de Gesti車n de Conversaciones

- **Vista unificada** de todas las conversaciones por contacto
- **Interface tipo chat** con burbujas de mensajes cronol車gicas
- **Identificaci車n visual** de conversaciones nuevas/no le赤das
- **Respuestas manuales** desde el panel con guardado autom芍tico
- **Marcado autom芍tico** como le赤do
- **Filtros avanzados** por tel谷fono, fecha, estado de lectura
- **Estad赤sticas en tiempo real** de mensajes y conversaciones
- **Enlaces directos** a WhatsApp Web
- **Auto-expansi車n** de conversaciones nuevas
- **Auto-refresh** cada 30 segundos

#### Panel de Configuraci車n de Respuestas Autom芍ticas

- **Editor visual** con formularios intuitivos
- **Gesti車n completa** de palabras clave y respuestas
- **Variables din芍micas** con reemplazo autom芍tico:
  - `{restaurant_name}` - Nombre del restaurante
  - `{restaurant_web}` - Sitio web
  - `{restaurant_phone}` - Tel谷fono
  - `{restaurant_email}` - Email
  - `{restaurant_address}` - Direcci車n
  - `{opening_time}` / `{closing_time}` - Horarios
  - `{delivery_fee}` - Costo de env赤o
  - `{min_delivery_amount}` - Monto m赤nimo delivery
  - `{order_number}` / `{order_status}` - Info de pedidos
- **Vista previa** de respuestas en tiempo real
- **Estad赤sticas de uso** por respuesta
- **Sistema de backup** y exportaci車n

#### Configuraci車n y Seguridad

**Configuraci車n en Meta for Developers:**
```
Callback URL: https://tu-dominio.com/admin/whatsapp-webhook.php
Verify Token: Configurable desde el panel (sin hardcodear)
Webhook Fields: messages, messaging_postbacks, message_deliveries
```

**Credenciales seguras:**
- Access Token de WhatsApp Business API (almacenado en BD)
- Phone Number ID del n迆mero WhatsApp Business
- Webhook Token para verificaci車n (configurable)
- **Sin credenciales hardcodeadas** en el c車digo

**Funciones de prueba integradas:**
- ? Prueba de env赤o de mensajes
- ? Verificaci車n de webhook
- ? Validaci車n de configuraci車n
- ? Logs detallados de errores

#### Caracter赤sticas T谷cnicas Mejoradas

- **Configuraci車n centralizada** usando `config/config.php` y `config/database.php`
- **Limpieza autom芍tica** de n迆meros telef車nicos argentinos (formato 549XXXXXXXXX)
- **Detecci車n autom芍tica** de pedidos relacionados por tel谷fono
- **Rate limiting** (m芍ximo 1 respuesta autom芍tica por minuto por n迆mero)
- **Detecci車n de duplicados** para evitar mensajes repetidos
- **Almacenamiento seguro** de mensajes y logs en base de datos
- **Manejo de errores** robusto con fallbacks
- **Webhook seguro** con validaci車n de origen
- **API REST** para integraci車n con otros sistemas
- **Creaci車n autom芍tica** de tablas si no existen

#### Archivos del Sistema WhatsApp

```
admin/
念岸岸 whatsapp-settings.php     # Configuraci車n de WhatsApp Business API
念岸岸 whatsapp-messages.php     # Panel de gesti車n de conversaciones  
念岸岸 whatsapp-answers.php      # Configuraci車n de respuestas autom芍ticas
弩岸岸 whatsapp-webhook.php      # Webhook para recibir mensajes

config/
弩岸岸 whatsapp_api.php         # Clase de integraci車n con WhatsApp Business API
```

#### Variables de Configuraci車n

```php
// Configuraci車n en la base de datos
'whatsapp_enabled' => '1'                    // Habilitar env赤o autom芍tico
'whatsapp_fallback_enabled' => '1'          // Fallback a WhatsApp Web
'whatsapp_auto_responses' => '1'             // Respuestas autom芍ticas
'whatsapp_access_token' => 'EAAxxxxxxxxx'    // Token de Meta
'whatsapp_phone_number_id' => '123456789'    // ID del n迆mero de WhatsApp
'whatsapp_webhook_token' => 'mi-token-123'   // Token del webhook
```



## ?? Soluci車n de Problemas

### Problemas Comunes

1. **Error de conexi車n a base de datos**:
   - Verificar credenciales en `config/config.php`
   - Comprobar que el servidor MySQL est谷 activo

2. **No aparecen im芍genes**:
   - Verificar permisos de carpeta `uploads/`
   - Comprobar rutas en la base de datos

3. **Notificaciones no funcionan**:
   - Verificar configuraci車n de JavaScript
   - Comprobar permisos del navegador

4. **Google Maps no funciona**:
   - Verificar API Key v芍lida
   - Comprobar APIs habilitadas en Google Console

5. **Pedidos online no funcionan**:
   - Verificar configuraci車n en Admin > Configuraci車n
   - Comprobar horarios de atenci車n
   - Verificar conexi車n a base de datos

### Logs y Depuraci車n
- **Logs de errores**: Activar error_log en PHP
- **Console del navegador**: Para errores de JavaScript
- **Network tab**: Para problemas de APIs

## ?? Soporte

### Archivos de Configuraci車n Importantes
- `config/config.php`: Configuraci車n principal
- `admin/api/`: APIs del sistema
- `database/comidasm.sql`: Estructura de base de datos

### Informaci車n del Sistema
- **Versi車n**: 1.0.0
- **Licencia**: MIT
- **PHP m赤nimo**: 8.0
- **MySQL m赤nimo**: 8.0

### Contacto y Desarrollo
- **Desarrollador**: Cellcom Technology  
- **Sitio Web**: [www.cellcomweb.com.ar](http://www.cellcomweb.com.ar)  
- **Tel谷fono / WhatsApp**: +54 3482 549555  
- **Direcci車n**: Calle 9 N～ 539, Avellaneda, Santa Fe, Argentina  
- **Soporte T谷cnico**: Disponible v赤a WhatsApp y web

## ?? Puesta en Producci車n

### Lista de Verificaci車n

- [ ] Cambiar todas las contrase?as predefinidas
- [ ] Configurar datos reales del restaurante
- [ ] Subir im芍genes de productos
- [ ] Configurar Google Maps API (opcional)
- [ ] Probar pedidos online completos
- [ ] Verificar horarios de atenci車n
- [ ] Configurar m谷todos de pago
- [ ] Probar notificaciones
- [ ] Backup de base de datos
- [ ] Certificado SSL configurado
- [ ] Probar sistema de llamadas de mesero
- [ ] Verificar impresi車n de tickets
- [ ] Configurar usuarios del personal
- [ ] Configurar control de stock en productos necesarios
- [ ] Establecer l赤mites de alerta de inventario
- [ ] Verificar funcionamiento de ajustes de stock
- [ ] Ejecutar instalador completo desde `/install/`
- [ ] Configurar control de stock en productos necesarios
- [ ] Establecer l赤mites de alerta de inventario
- [ ] Verificar funcionamiento de ajustes de stock
- [ ] **Eliminar carpeta `install/`** por seguridad

### Variables de Entorno Recomendadas

```php
// Producci車n
define('DEBUG_MODE', false);
define('ENVIRONMENT', 'production');

// Desarrollo
define('DEBUG_MODE', true);
define('ENVIRONMENT', 'development');
```

## ?? Changelog

### Versi車n 2.1.0
- Sistema completo de gesti車n de restaurante
- Pedidos online integrados con panel dedicado
- Panel de administraci車n responsive
- Reportes con gr芍ficos avanzados
- Sistema de roles y permisos granular
- Notificaciones en tiempo real
- Men迆 QR para mesas
- Integraci車n con Google Maps
- Sistema de llamadas de mesero
- Gesti車n completa de usuarios con interfaz m車vil
- Sistema de impresi車n de tickets personalizable
- Configuraci車n avanzada del sistema
- Interfaz optimizada para dispositivos t芍ctiles
- Instalador autom芍tico modular con verificaci車n de requisitos
- Sistema completo de control de stock e inventario
- Tabla `stock_movements` para historial de movimientos
- Integraci車n de WhatsApp Business API en instalaci車n
- Configuraci車n autom芍tica de temas y respuestas autom芍ticas


### Pr車ximas Versiones
- **v2.1.1** (En desarrollo):
  - Integraci車n completa con Mercado Pago API
  - Sistema de backup autom芍tico de base de datos
  - Mejoras en la interfaz de pagos
  - Panel de gesti車n de transacciones
  - 
---

**?Bienvenido al futuro de la gesti車n de restaurantes!** ???

Para soporte adicional o consultas, revise la documentaci車n t谷cnica en los comentarios del c車digo fuente.# mi_restaurant_delivery