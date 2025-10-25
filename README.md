# Sistema de Gesti贸n Gastron贸mica

Un sistema completo de gesti贸n para restaurantes que incluye punto de venta, pedidos online, gesti贸n de mesas, cocina, delivery y reportes avanzados.

## 馃専 Caracter铆sticas Principales

### 馃捇 Panel de Administraci贸n
- **Dashboard en tiempo real** con estad铆sticas y notificaciones
- **Gesti贸n de 贸rdenes** tradicionales y online
- **Control de mesas** con estados visuales
- **Panel de cocina** con tiempos de preparaci贸n
- **Gesti贸n de delivery** con seguimiento
- **Reportes avanzados** con gr谩ficos y exportaci贸n
- **Sistema de usuarios** con roles y permisos
- **Gesti贸n de productos** y categor铆as
- **Control de inventario** con seguimiento en tiempo real
- **Configuraci贸n del sistema** centralizada
- **Instalador autom谩tico** modular en 5 pasos
- **Control de inventario** con seguimiento en tiempo real

### 馃摫 Experiencia del Cliente
- **Men煤 online** responsive con carrito de compras
- **Men煤 QR** para mesas sin contacto
- **Pedidos online** con validaci贸n de direcciones
- **Integraci贸n con Google Maps** para delivery
- **Llamada al mesero** desde c贸digo QR
- **Validaci贸n de horarios** de atenci贸n

### 馃敂 Notificaciones en Tiempo Real
- **Alertas sonoras** para nuevos pedidos
- **Notificaciones visuales** con animaciones
- **Sistema de llamadas** de mesa
- **Actualizaciones autom谩ticas** del estado

## 馃洜锔?Tecnolog铆as Utilizadas

- **Backend**: PHP 8.0+
- **Base de datos**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5.3
- **Iconos**: Font Awesome 6.0
- **Gr谩ficos**: Chart.js 3.9
- **Mapas**: Google Maps API
- **Tablas**: DataTables
- **Gesti贸n de Stock**: Sistema de inventario integrado

## 馃搨 Estructura del Proyecto

```
mi_restaurant_delivery/
鈹溾攢鈹€ index.php # P谩gina principal del men煤 online
鈹溾攢鈹€ 404.html # P谩gina de error 404
鈹溾攢鈹€ 502.html # P谩gina de error 502
鈹溾攢鈹€ menu-qr.php # Men煤 accesible por c贸digo QR
鈹溾攢鈹€ call_waiter.php # API para generar llamadas de mesero
鈹溾攢鈹€ install.php # Instalador del sistema
鈹溾攢鈹€ README.md # Documentaci贸n del proyecto
鈹溾攢鈹€ .htaccess # Reglas de Apache (URLs amigables, seguridad, etc.)
鈹溾攢鈹€ estructura de archivos.txt # Archivo de referencia con la estructura del sistema
鈹?
鈹溾攢鈹€ models/ # Modelos de datos del sistema
鈹?鈹溾攢鈹€ Product.php # Modelo de productos
鈹?鈹溾攢鈹€ Table.php # Modelo de mesas
鈹?鈹溾攢鈹€ Category.php # Modelo de categor铆as
鈹?鈹溾攢鈹€ Payment.php # Modelo de pagos
鈹?鈹斺攢鈹€ Order.php # Modelo de 贸rdenes
鈹?
鈹溾攢鈹€ config/ # Configuraci贸n global del sistema
鈹?鈹溾攢鈹€ config.php # Configuraci贸n general (constantes, variables globales)
鈹?鈹溾攢鈹€ database.php # Conexi贸n a la base de datos
鈹?鈹溾攢鈹€ auth.php # Sistema de autenticaci贸n y sesiones
鈹?鈹溾攢鈹€ functions.php # Funciones auxiliares y utilidades
鈹?鈹溾攢鈹€ whatsapp_api.php         # Clase de integraci贸n con WhatsApp Business API
鈹?鈹斺攢鈹€ theme.php # Clase ThemeManager con toda la l贸gica
鈹?
鈹溾攢鈹€ admin/ # Panel de administraci贸n
鈹?鈹溾攢鈹€ api/ # APIs internas para el frontend
鈹?鈹?鈹溾攢鈹€ products.php # API de gesti贸n de productos
鈹?鈹?鈹溾攢鈹€ stock-movements.php # Historial de movimientos de inventario
鈹?鈹?鈹溾攢鈹€ update-item-status.php # Actualizaci贸n del estado de 铆tems
鈹?鈹?鈹溾攢鈹€ delivery-stats.php # Estad铆sticas de delivery
鈹?鈹?鈹溾攢鈹€ delivery.php # API de gesti贸n de deliveries
鈹?鈹?鈹溾攢鈹€ online-orders-stats.php # Estad铆sticas de pedidos online
鈹?鈹?鈹溾攢鈹€ online-orders.php # API de pedidos online
鈹?鈹?鈹溾攢鈹€ update-delivery.php # Actualizaci贸n de estado de entregas
鈹?鈹?鈹溾攢鈹€ orders.php # API de 贸rdenes tradicionales
鈹?鈹?鈹溾攢鈹€ kitchen.php # API del panel de cocina
鈹?鈹?鈹溾攢鈹€ update-order-status.php # Actualizaci贸n del estado de 贸rdenes
鈹?鈹?鈹溾攢鈹€ create-order.php # Creaci贸n de 贸rdenes desde el sistema
鈹?鈹?鈹溾攢鈹€ tables.php # API de gesti贸n de mesas
鈹?鈹?鈹溾攢鈹€ regenerate-css.php # API para regenerar archivos CSS
鈹?鈹?鈹溾攢鈹€ whatsapp-stats.php   # API de estad铆sticas de WhatsApp
鈹?鈹?鈹斺攢鈹€ online-orders-recent.php # Listado de pedidos online recientes
鈹?鈹?
鈹?鈹溾攢鈹€ receipts/ # Archivos de recibos generados
鈹?鈹?鈹斺攢鈹€ customer_ORD-.txt # Ejemplo de recibo de cliente
鈹?鈹?
鈹?鈹溾攢鈹€ tickets/ # Tickets impresos para cocina/delivery
鈹?鈹?鈹斺攢鈹€ kitchen_ORD-.txt # Ticket de orden en cocina
鈹?鈹?
鈹?鈹溾攢鈹€ pages/ # P谩ginas est谩ticas del panel
鈹?鈹?鈹斺攢鈹€ 403.php # P谩gina de error 403 (acceso denegado)
鈹?鈹?
鈹?鈹溾攢鈹€ uploads/ # Archivos subidos en el panel
鈹?鈹?鈹斺攢鈹€ products/ # Im谩genes de productos
鈹?鈹?
鈹?鈹溾攢鈹€ products.php # Gesti贸n de productos
鈹?鈹溾攢鈹€ settings.php # Configuraci贸n general del sistema
鈹?鈹溾攢鈹€ permissions.php # Gesti贸n de permisos y roles
鈹?鈹溾攢鈹€ check_calls.php # Verificaci贸n de llamadas de mesero
鈹?鈹溾攢鈹€ delivery.php # Panel de gesti贸n de deliveries
鈹?鈹溾攢鈹€ attend_call.php # Atender llamadas de mesero
鈹?鈹溾攢鈹€ online-orders.php # Gesti贸n de pedidos online
鈹?鈹溾攢鈹€ online-order-details.php # Detalle de un pedido online
鈹?鈹溾攢鈹€ dashboard.php # Dashboard principal con estad铆sticas
鈹?鈹溾攢鈹€ reports.php # Reportes avanzados del sistema
鈹?鈹溾攢鈹€ orders.php # Gesti贸n de 贸rdenes tradicionales
鈹?鈹溾攢鈹€ kitchen.php # Panel de cocina
鈹?鈹溾攢鈹€ users.php # Gesti贸n de usuarios y roles
鈹?鈹溾攢鈹€ tables.php # Gesti贸n de mesas
鈹?鈹溾攢鈹€ profile.php # Gesti贸n del perfil de usuario con avatar y configuraci贸n personal
鈹?鈹溾攢鈹€ order-create.php # Crear o editar 贸rdenes
鈹?鈹溾攢鈹€ logout.php # Cerrar sesi贸n
鈹?鈹溾攢鈹€ order-details.php # Detalle de una orden
鈹?鈹溾攢鈹€ print-order.php # Impresi贸n de 贸rdenes
鈹?鈹溾攢鈹€ theme-settings.php # Panel principal de configuraci贸n de temas
鈹?鈹溾攢鈹€ whatsapp-answers.php      # Panel de configuraci贸n de respuestas autom谩ticas
鈹?鈹溾攢鈹€ whatsapp-settings.php    # Configuraci贸n de WhatsApp Business API
鈹?鈹溾攢鈹€ whatsapp-messages.php    # Panel de gesti贸n de conversaciones WhatsApp  
鈹?鈹溾攢鈹€ whatsapp-webhook.php     # Webhook para recibir mensajes de WhatsApp
鈹?鈹斺攢鈹€ login.php # P谩gina de login
鈹?
鈹溾攢鈹€ assets/ # Recursos est谩ticos
鈹?鈹溾攢鈹€ includes/ # Archivos de inclusi贸n
鈹?鈹溾攢鈹€ css/ # Hojas de estilo
鈹?鈹?鈹溾攢鈹€ generate-theme.php # Generador de CSS din谩mico
鈹?鈹?鈹斺攢鈹€ dynamic-theme.css # Archivo CSS generado autom谩ticamente
鈹?鈹?
鈹?鈹溾攢鈹€ images/ # Im谩genes del sistema
鈹?鈹斺攢鈹€ js/ # Scripts JavaScript
鈹?
鈹斺攢鈹€ database/ # Scripts de base de datos
鈹斺攢鈹€ bd.sql # Estructura y datos iniciales
```

## 馃殌 Instalaci贸n

### Requisitos del Sistema

- **PHP**: 7.4 o superior
- **MySQL**: 8.0 o superior
- **Apache/Nginx**: Servidor web
- **Extensiones PHP**:
  - PDO
  - PDO_MySQL
  - GD (para im谩genes)
  - JSON
  - Session
  - mbstring
  - openssl
  - curl

### Instalaci贸n Autom谩tica (Recomendada)

El sistema incluye un instalador web modular dividido en pasos para una instalaci贸n m谩s organizada y mantenible.

1. **Descargar y extraer** el proyecto en su servidor web
2. **Crear base de datos** MySQL vac铆a
3. **Navegar** a `http://su-dominio.com/install/`
4. **Seguir el asistente** de instalaci贸n paso a paso:

#### Pasos del Instalador

**Paso 1: Verificaci贸n de Requisitos y Configuraci贸n de BD**
- Verificaci贸n autom谩tica de requisitos del sistema
- Configuraci贸n de conexi贸n a base de datos
- Generaci贸n del archivo `config/config.php`

**Paso 2: Instalaci贸n de Estructura de BD**
- Creaci贸n autom谩tica de todas las tablas necesarias:
  - Gesti贸n de usuarios, roles y permisos
  - Sistema de productos con control de stock
  - Gesti贸n de 贸rdenes y pagos
  - Sistema de mesas y llamadas de mesero
  - Configuraci贸n de temas din谩micos
  - Integraci贸n completa de WhatsApp Business API
  - **Tabla `stock_movements`** para historial de inventario
- Inserci贸n de datos b谩sicos del sistema
- Configuraci贸n de roles y permisos
- Instalaci贸n de respuestas autom谩ticas de WhatsApp
- Configuraci贸n de temas b谩sicos

**Paso 3: Configuraci贸n del Restaurante**
- Datos b谩sicos del negocio
- Configuraci贸n de delivery y horarios
- Creaci贸n del usuario administrador
- Configuraci贸n de APIs (Google Maps, WhatsApp)

**Paso 4: Datos de Ejemplo (Opcional)**
- Usuarios de ejemplo con diferentes roles
- **Productos de muestra con control de stock**:
  - Productos con y sin seguimiento de inventario
  - Configuraci贸n de alertas de stock bajo
  - Datos realistas de costos y precios
- Mesas adicionales
- **Este paso funciona independientemente** y puede ejecutarse en cualquier momento

**Paso 5: Finalizaci贸n**
- Resumen de la instalaci贸n
- Credenciales de acceso
- Enlaces directos al sistema
- Instrucciones de seguridad

### Estructura de Archivos de Instalaci贸n

```
install/
鈹溾攢鈹€ index.php              # Archivo principal de instalaci贸n
鈹溾攢鈹€ install_common.php     # Funciones compartidas y estructura de BD
鈹溾攢鈹€ step1.php             # Requisitos del sistema y configuraci贸n de BD
鈹溾攢鈹€ step2.php             # Instalaci贸n de estructura de BD
鈹溾攢鈹€ step3.php             # Configuraci贸n del restaurante
鈹溾攢鈹€ step4.php             # Datos de ejemplo (opcional)
鈹斺攢鈹€ step5.php             # Finalizaci贸n
```

### Caracter铆sticas del Instalador

- **Modular**: Cada paso es independiente y mantenible
- **Verificaci贸n autom谩tica**: Requisitos del sistema validados
- **Progreso visual**: Indicadores de progreso en cada paso
- **Navegaci贸n flexible**: Posibilidad de saltar o repetir pasos
- **Datos de ejemplo opcionales**: El paso 4 puede ejecutarse despu茅s de la instalaci贸n principal
- **Seguridad**: Verificaciones y validaciones en cada paso
- **Instalaci贸n completa**: Incluye todas las tablas necesarias para:
  - Sistema de productos con control de stock
  - Gesti贸n de inventario con historial de movimientos
  - WhatsApp Business API con respuestas autom谩ticas
  - Sistema de temas din谩micos
  - Estructura completa de 贸rdenes y pagos

### Base de Datos Instalada

El instalador crea autom谩ticamente las siguientes tablas:

**Sistema Core:**
- `users`, `roles` - Gesti贸n de usuarios y permisos
- `settings` - Configuraci贸n del sistema
- `categories`, `products` - Gesti贸n de productos
- `stock_movements` - **Historial de movimientos de inventario**

**Gesti贸n de 脫rdenes:**
- `orders`, `order_items` - 脫rdenes tradicionales
- `online_orders` - Pedidos online
- `payments`, `online_orders_payments` - Sistema de pagos
- `tables`, `waiter_calls` - Gesti贸n de mesas

**Sistema de Temas:**
- `theme_settings` - Configuraci贸n de temas
- `custom_themes` - Temas personalizados
- `theme_history` - Historial de cambios

**WhatsApp Business API:**
- `whatsapp_messages` - Conversaciones
- `whatsapp_logs` - Logs de env铆o
- `whatsapp_auto_responses` - Respuestas autom谩ticas
- `whatsapp_media_uploads` - Archivos multimedia

### Post-Instalaci贸n

**Importante para la seguridad:**
- 鈿狅笍 **Eliminar toda la carpeta `install/`** despu茅s de completar la instalaci贸n
- Cambiar todas las contrase帽as predefinidas
- Configurar HTTPS en producci贸n
- Verificar permisos de archivos y carpetas

### Soluci贸n de Problemas de Instalaci贸n

**El sistema ya est谩 instalado:**
- Si aparece este mensaje y desea reinstalar, elimine el archivo `config/installed.lock`
- Para agregar solo datos de ejemplo, acceda directamente a `install/step4.php`

**Error de conexi贸n a base de datos:**
- Verificar credenciales de MySQL
- Asegurar que la base de datos existe y est谩 accesible
- Comprobar que las extensiones PHP est谩n instaladas

**Permisos de escritura:**
- Verificar permisos 755 en carpetas de uploads
- Asegurar que el servidor web puede escribir en `config/`

**Requisitos no cumplidos:**
- Actualizar PHP a versi贸n 7.4 o superior
- Instalar extensiones PHP faltantes
- Verificar configuraci贸n del servidor web

### Instalaci贸n Manual (Avanzada)

Si prefiere instalar manualmente:

1. **Configurar base de datos**:
   ```sql
   CREATE DATABASE comidasm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar estructura**:
   ```bash
   mysql -u usuario -p comidasm < database/bd.sql
   ```

3. **Configurar archivo de configuraci贸n**:
   ```php
   // config/config.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'comidasm');
   define('DB_USER', 'tu_usuario');
   define('DB_PASS', 'tu_contrase帽a');
   
   define('BASE_URL', 'https://tu-dominio.com/');
   define('UPLOAD_PATH', 'uploads/');
   define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
   ```

4. **Crear carpetas con permisos**:
   ```bash
   mkdir -p config uploads uploads/products uploads/categories uploads/avatars whatsapp_media
   chmod 755 uploads/ admin/uploads/ whatsapp_media/
   ```

5. **Crear archivo de instalaci贸n completada**:
   ```bash
   echo "$(date)" > config/installed.lock
   ```

### Post-Instalaci贸n

**Importante para la seguridad:**
- 鈿狅笍 **Eliminar toda la carpeta `install/`** despu茅s de completar la instalaci贸n
- Cambiar todas las contrase帽as predefinidas
- Configurar HTTPS en producci贸n
- Verificar permisos de archivos y carpetas

### Soluci贸n de Problemas de Instalaci贸n

**El sistema ya est谩 instalado:**
- Si aparece este mensaje y desea reinstalar, elimine el archivo `config/installed.lock`
- Para agregar solo datos de ejemplo, acceda directamente a `install/step4.php`

**Error de conexi贸n a base de datos:**
- Verificar credenciales de MySQL
- Asegurar que la base de datos existe y est谩 accesible
- Comprobar que las extensiones PHP est谩n instaladas

**Permisos de escritura:**
- Verificar permisos 755 en carpetas de uploads
- Asegurar que el servidor web puede escribir en `config/`

**Requisitos no cumplidos:**
- Actualizar PHP a versi贸n 7.4 o superior
- Instalar extensiones PHP faltantes
- Verificar configuraci贸n del servidor web

## 馃敡 Configuraci贸n

### Configuraci贸n B谩sica

Acceder a **Admin > Configuraci贸n** para ajustar:

- **Datos del restaurante**: Nombre, tel茅fono, direcci贸n
- **Horarios**: Apertura, cierre, cierre de cocina
- **Delivery**: Costo, distancia m谩xima, monto m铆nimo
- **Pagos**: M茅todos aceptados, configuraci贸n de impuestos
- **Notificaciones**: Sonidos, alertas autom谩ticas

### Google Maps (Opcional)

Para habilitar autocompletado de direcciones:

1. **Obtener API Key** de Google Maps
2. **Configurar en**: Admin > Configuraci贸n > Google Maps API Key
3. **Habilitar APIs**:
   - Places API
   - Geocoding API
   - Maps JavaScript API

### Configuraci贸n de Roles

El sistema incluye roles predefinidos, pero puede:

- **Crear roles personalizados**
- **Asignar permisos espec铆ficos**:
  - `all`: Acceso completo
  - `orders`: Gesti贸n de 贸rdenes tradicionales
  - `online_orders`: Gesti贸n de pedidos online
  - `products`: Gesti贸n de productos
  - `users`: Gesti贸n de usuarios
  - `tables`: Gesti贸n de mesas
  - `reports`: Reportes y estad铆sticas
  - `kitchen`: Panel de cocina
  - `delivery`: Gesti贸n de delivery
  - `settings`: Configuraci贸n del sistema

  
### 馃摝 Control de Stock e Inventario

Sistema avanzado de gesti贸n de inventario con seguimiento en tiempo real y alertas autom谩ticas.

#### Caracter铆sticas del Sistema de Stock

- **Control opcional por producto** - Activar/desactivar gesti贸n de inventario individualmente
- **Seguimiento en tiempo real** - Actualizaci贸n autom谩tica de cantidades
- **Alertas de stock bajo** - Notificaciones configurables por producto
- **Historial de movimientos** - Registro completo de entradas y salidas
- **Ajustes manuales** - Correcciones de inventario con motivos
- **Indicadores visuales** - Barras de progreso y badges de estado
- **Estad铆sticas de inventario** - Dashboard con m茅tricas en vivo

#### Funcionalidades Principales

**Gesti贸n de Productos con Stock:**
- 鉁?**Activaci贸n selectiva** - Control de inventario opcional por producto
- 鉁?**Stock actual** - Cantidad disponible en tiempo real
- 鉁?**L铆mites de alerta** - Configuraci贸n personalizada de stock m铆nimo
- 鉁?**Estados visuales** - Sin stock, stock bajo, stock normal
- 鉁?**C谩lculos autom谩ticos** - M谩rgenes de ganancia en tiempo real
- 鉁?**Validaciones robustas** - Prevenci贸n de stock negativo

**Panel de Ajustes de Stock:**
- **Modal dedicado** para ajustes r谩pidos de inventario
- **Tipos de movimiento**: Entrada (agregar) y Salida (reducir)
- **Motivos predefinidos**:
  - Ajuste manual
  - Inventario f铆sico
  - Producto da帽ado/vencido
  - Venta directa
  - Compra/Reposici贸n
  - Correcci贸n de error
  - Motivos personalizados
- **Vista previa** del nuevo stock antes de confirmar
- **Alertas autom谩ticas** si el ajuste genera stock cr铆tico

**Dashboard de Inventario:**
- **Productos con control** - Cantidad total bajo seguimiento
- **Stock bueno** - Productos con inventario normal
- **Stock bajo** - Productos cerca del l铆mite m铆nimo
- **Sin stock** - Productos agotados
- **Alertas prominentes** para productos cr铆ticos

**Historial de Movimientos:**
- **Registro completo** de todos los cambios de stock
- **Informaci贸n detallada**: Usuario, fecha, cantidad, motivo
- **Trazabilidad total** del inventario
- **Reportes de movimientos** por producto y periodo

#### Caracter铆sticas T茅cnicas del Stock

- **Base de datos optimizada** con tabla `stock_movements`
- **Transacciones seguras** para prevenir inconsistencias
- **Validaciones m煤ltiples** en frontend y backend
- **Interfaz responsive** optimizada para m贸viles
- **Integraci贸n completa** con sistema de productos existente
- **API REST** para ajustes program谩ticos
- **Logs autom谩ticos** de todas las operaciones

#### Interfaz de Usuario Mejorada

**Tarjetas de Productos:**
- **Indicadores de stock** en esquina superior
- **Barras de progreso** mostrando nivel de inventario
- **Badges din谩micos** (Sin stock, Stock bajo, Disponible)
- **Botones de acci贸n r谩pida** para ajustar stock
- **Colores sem谩nticos** seg煤n estado del inventario

**Modal de Productos Expandido:**
- **Secci贸n dedicada** de gesti贸n de inventario
- **Switch de activaci贸n** para control de stock
- **Campos de stock actual** y l铆mite de alerta
- **Indicadores de estado** en tiempo real
- **Validaciones visuales** instant谩neas

**Alertas Inteligentes:**
- **Notificaciones autom谩ticas** de productos con stock bajo
- **Lista expandible** con acciones directas
- **Auto-actualizaci贸n** cada vez que se modifica inventario
- **Integraci贸n con dashboard** principal

#### Flujo de Trabajo del Stock

1. **Configuraci贸n inicial**:
   - Activar control de stock por producto
   - Establecer cantidad inicial
   - Configurar l铆mite de alerta

2. **Operaci贸n diaria**:
   - Visualizaci贸n autom谩tica de alertas
   - Ajustes r谩pidos desde tarjetas de productos
   - Seguimiento en dashboard de inventario

3. **Gesti贸n avanzada**:
   - Ajustes con motivos espec铆ficos
   - Revisi贸n de historial de movimientos
   - Reportes de inventario

#### Beneficios del Sistema

- **Control preciso** del inventario sin complejidad excesiva
- **Alertas proactivas** evitan quiebres de stock
- **Trazabilidad completa** para auditor铆as
- **Interfaz intuitiva** sin curva de aprendizaje
- **Integraci贸n transparente** con flujo de trabajo existente
- **Flexibilidad total** - usar solo en productos necesarios


## 馃搳 M贸dulos del Sistema

### 馃彔 Dashboard
- **Estad铆sticas en tiempo real**
- **脫rdenes recientes** de todos los tipos
- **Estado de mesas** visual
- **Notificaciones autom谩ticas**
- **Accesos r谩pidos** seg煤n el rol

### 馃搵 Gesti贸n de 脫rdenes
- **脫rdenes tradicionales**: Mesa, delivery, retiro
- **Pedidos online**: Integraci贸n completa
- **Estados de orden**: Pendiente 鈫?Confirmado 鈫?Preparando 鈫?Listo 鈫?Entregado
- **Pagos**: M煤ltiples m茅todos (efectivo, tarjeta, transferencia, QR)
- **Filtros avanzados** por fecha, estado, tipo

### 馃寪 Pedidos Online
- **Sistema completo** de pedidos por internet
- **Carrito de compras** con validaci贸n
- **Autocompletado de direcciones** con Google Maps
- **Verificaci贸n de zona** de delivery
- **Formateo autom谩tico** de tel茅fonos argentinos
- **Confirmaci贸n por WhatsApp**
- **Estados en tiempo real**
- **Panel de gesti贸n** dedicado con:
  - Aceptaci贸n/rechazo de pedidos
  - Tiempos estimados de preparaci贸n
  - Seguimiento completo del proceso
  - Integraci贸n con WhatsApp autom谩tico
  - Sistema de pagos integrado

### 馃嵔锔?Gesti贸n de Mesas
- **Vista visual** de todas las mesas
- **Estados**: Libre, ocupada, reservada, mantenimiento
- **Capacidad** y ubicaci贸n
- **Asignaci贸n autom谩tica** de 贸rdenes
- **Representaci贸n gr谩fica** con sillas seg煤n capacidad
- **Acciones r谩pidas** desde cada mesa

### 馃懆鈥嶐煃?Panel de Cocina
- **脫rdenes por preparar** en tiempo real
- **Tiempos de preparaci贸n**
- **Estados por item**
- **Priorizaci贸n autom谩tica**
- **Actualizaci贸n en vivo**

### 馃弽锔?Gesti贸n de Delivery
- **脫rdenes listas** para entrega
- **Informaci贸n del cliente** completa
- **Direcciones con mapas**
- **Tiempos de entrega**
- **Estado de entrega**

### 馃枿锔?Sistema de Impresi贸n
- **Tickets de venta** personalizables
- **Impresi贸n autom谩tica** opcional
- **Formatos m煤ltiples** (58mm, 80mm)
- **Vista previa** antes de imprimir
- **Informaci贸n completa** del pedido y pagos

### 馃搳 Reportes Avanzados
- **Ventas diarias** con gr谩ficos
- **Productos m谩s vendidos**
- **Rendimiento del personal**
- **An谩lisis de mesas**
- **M茅todos de pago**
- **Comparaci贸n de per铆odos**
- **Exportaci贸n a Excel/CSV**

### 馃摫 Men煤 QR
- **C贸digo QR** para cada mesa
- **Men煤 digital** responsive
- **Filtros por categor铆a**
- **Llamada al mesero** integrada
- **Sin instalaci贸n** de apps

### 馃懃 Gesti贸n de Usuarios
- **Roles y permisos** granulares
- **Interfaz responsive** optimizada para m贸vil
- **Vista de tarjetas** en dispositivos m贸viles
- **Filtros por rol** y estado
- **Gesti贸n de contrase帽as**
- **Activaci贸n/desactivaci贸n** de usuarios
- **Interfaz t谩ctil** optimizada

### 馃懁 Perfil de Usuario

Sistema completo de gesti贸n de perfiles personales para todos los usuarios del sistema.

#### Caracter铆sticas del Perfil

- **Informaci贸n personal completa**:
  - Edici贸n de nombre completo
  - Actualizaci贸n de email con validaci贸n
  - Gesti贸n de n煤mero de tel茅fono
  - Visualizaci贸n del rol asignado

- **Sistema de avatars avanzado**:
  - Subida de im谩genes de perfil (JPG, PNG, GIF)
  - L铆mite de 2MB por archivo
  - Generaci贸n autom谩tica de iniciales si no hay avatar
  - Vista previa antes de subir
  - Eliminaci贸n autom谩tica de avatars anteriores

- **Cambio de contrase帽a seguro**:
  - Verificaci贸n de contrase帽a actual
  - Indicador visual de fortaleza de contrase帽a
  - Validaci贸n de coincidencia en tiempo real
  - Requisito m铆nimo de 6 caracteres
  - Opci贸n de mostrar/ocultar contrase帽as

- **Estad铆sticas personales**:
  - Fecha de registro en el sistema
  - D铆as activo en la plataforma
  - 脷ltimo acceso registrado
  - Estado actual de la cuenta

#### Funcionalidades T茅cnicas

- **Validaci贸n en tiempo real** con JavaScript
- **Compatibilidad autom谩tica** con base de datos existente
- **Creaci贸n autom谩tica** de columnas `avatar` y `last_login` si no existen
- **Interfaz responsive** optimizada para dispositivos m贸viles
- **Integraci贸n completa** con sistema de temas din谩mico
- **Gesti贸n segura** de archivos subidos
- **Validaciones robustas** del lado servidor y cliente

#### Seguridad Implementada

- **Verificaci贸n de contrase帽a actual** antes de cambios
- **Validaci贸n de formato** de emails
- **Verificaci贸n de unicidad** de emails
- **L铆mites de tama帽o** y tipo de archivos
- **Sanitizaci贸n** de datos de entrada
- **Protecci贸n contra** sobrescritura de archivos

#### Interfaz de Usuario

- **Dise帽o moderno** con gradientes y efectos visuales
- **Animaciones suaves** para mejor experiencia
- **Feedback visual inmediato** en formularios
- **Indicadores de estado** para todas las acciones
- **Responsividad completa** para m贸viles y tablets
- **Accesibilidad mejorada** con labels y ARIA

Este m贸dulo proporciona a cada usuario control total sobre su informaci贸n personal y configuraci贸n de cuenta, manteniendo la seguridad y consistencia del sistema.

### 鈿欙笍 Configuraci贸n Avanzada
- **Configuraci贸n general** del restaurante
- **Configuraci贸n de negocio** (impuestos, delivery)
- **Configuraci贸n de pedidos online**
- **Horarios de atenci贸n**
- **Integraci贸n con Google Maps**
- **Configuraciones del sistema**
- **Pruebas de configuraci贸n** integradas

## 馃摓 Sistema de Llamadas de Mesero

### Funcionalidades
- **Llamada desde c贸digo QR** de mesa
- **Notificaciones en tiempo real** al personal
- **Estado de llamadas** (pendiente/atendida)
- **Hist贸rico de llamadas**
- **Integraci贸n con panel de mesas**

### Archivos del Sistema
- `call_waiter.php`: API para generar llamadas
- `attend_call.php`: Marcar llamadas como atendidas
- `check_calls.php`: Verificar llamadas pendientes

## 馃敀 Seguridad

### Medidas Implementadas
- **Autenticaci贸n** con hash seguro de contrase帽as
- **Autorizaci贸n** basada en roles y permisos
- **Protecci贸n CSRF** en formularios
- **Validaci贸n de datos** en servidor y cliente
- **Escape de HTML** para prevenir XSS
- **Sesiones seguras** con configuraci贸n httponly
- **Validaci贸n de archivos** subidos

### Recomendaciones
- **Cambiar contrase帽as** predefinidas
- **Usar HTTPS** en producci贸n
- **Backup regular** de la base de datos
- **Actualizar** PHP y MySQL regularmente
- **Monitorear logs** de acceso

## 馃帹 Personalizaci贸n

### Temas y Estilos
- **Variables CSS** para colores principales
- **Responsive design** para todos los dispositivos
- **Iconos personalizables** con Font Awesome
- **Animaciones suaves** para mejor UX
- **Interfaz optimizada** para dispositivos t谩ctiles

### 馃帹 Sistema de Gesti贸n de Estilos Din谩micos

El sistema incluye un potente m贸dulo de personalizaci贸n de temas que permite modificar la apariencia visual de toda la aplicaci贸n en tiempo real.

#### Caracter铆sticas del Sistema de Temas

- **Editor visual de colores** con color pickers interactivos
- **Vista previa en tiempo real** de los cambios
- **Temas predefinidos** profesionales (Predeterminado, Oscuro, Verde, Morado, Azul, Naranja)
- **Generador autom谩tico de paletas de colores**:
  - Colores aleatorios
  - Colores complementarios  
  - Colores an谩logos
- **Configuraci贸n de tipograf铆a** con preview en vivo
- **Personalizaci贸n de layout** (bordes, espaciado, sidebar)
- **Sistema de importaci贸n/exportaci贸n** de temas
- **Backup autom谩tico** de configuraciones
- **Validaci贸n de integridad** del tema
- **CSS din谩mico** generado autom谩ticamente


#### Uso del Sistema de Temas

1. **Acceder al configurador**: Admin > Configuraci贸n > Tema
2. **Personalizar colores**: 
   - Colores principales (primario, secundario, acento)
   - Colores de estado (茅xito, advertencia, peligro, informaci贸n)
   - Vista previa instant谩nea de cambios
3. **Configurar tipograf铆a**:
   - Selecci贸n de fuentes (Segoe UI, Inter, Roboto, Open Sans, Montserrat, Poppins)
   - Tama帽os de fuente (base, peque帽o, grande)
   - Preview en tiempo real
4. **Ajustar dise帽o**:
   - Radio de bordes (angular, normal, redondeado)
   - Ancho del sidebar
   - Intensidad de sombras
5. **Aplicar temas predefinidos** con un solo clic
6. **Generar paletas autom谩ticas**:
   - Colores aleatorios para inspiraci贸n
   - Colores complementarios para alto contraste
   - Colores an谩logos para armon铆a visual

#### Herramientas Avanzadas

- **Exportar tema**: Descarga configuraci贸n actual en formato JSON
- **Importar tema**: Carga temas previamente exportados
- **Restablecer**: Vuelve a la configuraci贸n predeterminada
- **Regenerar CSS**: Actualiza archivos CSS din谩micos
- **Crear backup**: Respaldo de seguridad de la configuraci贸n
- **Validar tema**: Verifica integridad de colores y configuraciones

#### Caracter铆sticas T茅cnicas

- **CSS Variables**: Uso de variables CSS para cambios en tiempo real
- **Responsive design**: Todos los temas se adaptan a dispositivos m贸viles
- **Validaci贸n robusta**: Verificaci贸n de colores hexadecimales y medidas CSS
- **Cache inteligente**: Optimizaci贸n de rendimiento
- **Fallback autom谩tico**: CSS de emergencia si hay errores
- **Compatibilidad total**: Funciona con todos los m贸dulos del sistema

#### Beneficios

- **Branding personalizado**: Adapta el sistema a la identidad visual del restaurante
- **Mejor experiencia de usuario**: Interface m谩s atractiva y profesional
- **Facilidad de uso**: Sin conocimientos t茅cnicos requeridos
- **Flexibilidad total**: Desde cambios sutiles hasta transformaciones completas
- **Consistencia visual**: Todos los m贸dulos mantienen el tema seleccionado
- 

### Funcionalidades Adicionales
El sistema es extensible para agregar:
- **Reservas online**
- **Programa de fidelizaci贸n**
- **Integraci贸n con redes sociales**
- **Sistemas de pago online**
- **Facturaci贸n electr贸nica**
- **M煤ltiples sucursales**
 

### 馃摫 Sistema de WhatsApp Business API

El sistema incluye integraci贸n completa con WhatsApp Business API para comunicaci贸n autom谩tica con clientes y gesti贸n de conversaciones avanzadas.

#### Caracter铆sticas del Sistema WhatsApp

- **API de WhatsApp Business** completamente integrada
- **Env铆o autom谩tico** de notificaciones de pedidos (confirmaci贸n, preparaci贸n, listo, entregado)
- **Webhook autom谩tico** para recibir mensajes entrantes con configuraci贸n segura
- **Respuestas autom谩ticas configurables** desde panel web con variables din谩micas
- **Panel de gesti贸n de conversaciones** con interface tipo chat
- **Sistema de prioridades** y tipos de coincidencia para respuestas
- **Rate limiting** y detecci贸n de duplicados
- **Logs completos** de env铆os y recepciones
- **Configuraci贸n din谩mica** del restaurante
- **Limpieza autom谩tica** de n煤meros telef贸nicos argentinos
- **Sistema de fallback** a WhatsApp Web si falla la API
- **Guardado de conversaciones completas** para seguimiento

#### Funcionalidades de Mensajer铆a

**Env铆o Autom谩tico:**
- 鉁?**Confirmaciones autom谩ticas** de pedidos online al aceptar
- 鉁?**Actualizaciones de estado** en tiempo real (preparando, listo)
- 鉁?**Notificaciones de entrega** autom谩ticas
- 鉁?**Mensajes de rechazo** con motivo especificado
- 鉁?**Guardado autom谩tico** en conversaciones para seguimiento
- 鉁?**Fallback inteligente** a WhatsApp Web si la API falla

**Sistema de Respuestas Autom谩ticas Avanzado:**
- **Editor web de respuestas** con variables din谩micas
- **Tipos de coincidencia**: Contiene, exacto, empieza con, termina con
- **Sistema de prioridades** (mayor n煤mero = mayor prioridad)
- **Variables autom谩ticas**: `{restaurant_name}`, `{restaurant_web}`, `{restaurant_phone}`, etc.
- **Estad铆sticas de uso** para cada respuesta
- **Activaci贸n/desactivaci贸n** individual
- **Contador de usos** y fechas de creaci贸n/actualizaci贸n

**Ejemplos de respuestas configurables:**

| Palabras Clave | Respuesta | Tipo |
|----------------|-----------|------|
| hola,saludos,buenos | 隆Hola! Gracias por contactar a {restaurant_name}. Para pedidos: {restaurant_web} | Contiene |
| menu,men煤,carta | Vea nuestro men煤 completo en {restaurant_web} | Contiene |
| horario,horarios | Horarios: {opening_time} - {closing_time} | Contiene |
| estado,pedido | Para consultar estado, proporcione n煤mero de orden | Contiene |
| direccion,ubicacion | Nuestra direcci贸n: {restaurant_address} | Contiene |

#### Panel de Gesti贸n de Conversaciones

- **Vista unificada** de todas las conversaciones por contacto
- **Interface tipo chat** con burbujas de mensajes cronol贸gicas
- **Identificaci贸n visual** de conversaciones nuevas/no le铆das
- **Respuestas manuales** desde el panel con guardado autom谩tico
- **Marcado autom谩tico** como le铆do
- **Filtros avanzados** por tel茅fono, fecha, estado de lectura
- **Estad铆sticas en tiempo real** de mensajes y conversaciones
- **Enlaces directos** a WhatsApp Web
- **Auto-expansi贸n** de conversaciones nuevas
- **Auto-refresh** cada 30 segundos

#### Panel de Configuraci贸n de Respuestas Autom谩ticas

- **Editor visual** con formularios intuitivos
- **Gesti贸n completa** de palabras clave y respuestas
- **Variables din谩micas** con reemplazo autom谩tico:
  - `{restaurant_name}` - Nombre del restaurante
  - `{restaurant_web}` - Sitio web
  - `{restaurant_phone}` - Tel茅fono
  - `{restaurant_email}` - Email
  - `{restaurant_address}` - Direcci贸n
  - `{opening_time}` / `{closing_time}` - Horarios
  - `{delivery_fee}` - Costo de env铆o
  - `{min_delivery_amount}` - Monto m铆nimo delivery
  - `{order_number}` / `{order_status}` - Info de pedidos
- **Vista previa** de respuestas en tiempo real
- **Estad铆sticas de uso** por respuesta
- **Sistema de backup** y exportaci贸n

#### Configuraci贸n y Seguridad

**Configuraci贸n en Meta for Developers:**
```
Callback URL: https://tu-dominio.com/admin/whatsapp-webhook.php
Verify Token: Configurable desde el panel (sin hardcodear)
Webhook Fields: messages, messaging_postbacks, message_deliveries
```

**Credenciales seguras:**
- Access Token de WhatsApp Business API (almacenado en BD)
- Phone Number ID del n煤mero WhatsApp Business
- Webhook Token para verificaci贸n (configurable)
- **Sin credenciales hardcodeadas** en el c贸digo

**Funciones de prueba integradas:**
- 鉁?Prueba de env铆o de mensajes
- 鉁?Verificaci贸n de webhook
- 鉁?Validaci贸n de configuraci贸n
- 鉁?Logs detallados de errores

#### Caracter铆sticas T茅cnicas Mejoradas

- **Configuraci贸n centralizada** usando `config/config.php` y `config/database.php`
- **Limpieza autom谩tica** de n煤meros telef贸nicos argentinos (formato 549XXXXXXXXX)
- **Detecci贸n autom谩tica** de pedidos relacionados por tel茅fono
- **Rate limiting** (m谩ximo 1 respuesta autom谩tica por minuto por n煤mero)
- **Detecci贸n de duplicados** para evitar mensajes repetidos
- **Almacenamiento seguro** de mensajes y logs en base de datos
- **Manejo de errores** robusto con fallbacks
- **Webhook seguro** con validaci贸n de origen
- **API REST** para integraci贸n con otros sistemas
- **Creaci贸n autom谩tica** de tablas si no existen

#### Archivos del Sistema WhatsApp

```
admin/
鈹溾攢鈹€ whatsapp-settings.php     # Configuraci贸n de WhatsApp Business API
鈹溾攢鈹€ whatsapp-messages.php     # Panel de gesti贸n de conversaciones  
鈹溾攢鈹€ whatsapp-answers.php      # Configuraci贸n de respuestas autom谩ticas
鈹斺攢鈹€ whatsapp-webhook.php      # Webhook para recibir mensajes

config/
鈹斺攢鈹€ whatsapp_api.php         # Clase de integraci贸n con WhatsApp Business API
```

#### Variables de Configuraci贸n

```php
// Configuraci贸n en la base de datos
'whatsapp_enabled' => '1'                    // Habilitar env铆o autom谩tico
'whatsapp_fallback_enabled' => '1'          // Fallback a WhatsApp Web
'whatsapp_auto_responses' => '1'             // Respuestas autom谩ticas
'whatsapp_access_token' => 'EAAxxxxxxxxx'    // Token de Meta
'whatsapp_phone_number_id' => '123456789'    // ID del n煤mero de WhatsApp
'whatsapp_webhook_token' => 'mi-token-123'   // Token del webhook
```



## 馃洜 Soluci贸n de Problemas

### Problemas Comunes

1. **Error de conexi贸n a base de datos**:
   - Verificar credenciales en `config/config.php`
   - Comprobar que el servidor MySQL est茅 activo

2. **No aparecen im谩genes**:
   - Verificar permisos de carpeta `uploads/`
   - Comprobar rutas en la base de datos

3. **Notificaciones no funcionan**:
   - Verificar configuraci贸n de JavaScript
   - Comprobar permisos del navegador

4. **Google Maps no funciona**:
   - Verificar API Key v谩lida
   - Comprobar APIs habilitadas en Google Console

5. **Pedidos online no funcionan**:
   - Verificar configuraci贸n en Admin > Configuraci贸n
   - Comprobar horarios de atenci贸n
   - Verificar conexi贸n a base de datos

### Logs y Depuraci贸n
- **Logs de errores**: Activar error_log en PHP
- **Console del navegador**: Para errores de JavaScript
- **Network tab**: Para problemas de APIs

## 馃摓 Soporte

### Archivos de Configuraci贸n Importantes
- `config/config.php`: Configuraci贸n principal
- `admin/api/`: APIs del sistema
- `database/comidasm.sql`: Estructura de base de datos

### Informaci贸n del Sistema
- **Versi贸n**: 1.0.0
- **Licencia**: MIT
- **PHP m铆nimo**: 8.0
- **MySQL m铆nimo**: 8.0

### Contacto y Desarrollo
- **Desarrollador**: Cellcom Technology  
- **Sitio Web**: [www.cellcomweb.com.ar](http://www.cellcomweb.com.ar)  
- **Tel茅fono / WhatsApp**: +54 3482 549555  
- **Direcci贸n**: Calle 9 N掳 539, Avellaneda, Santa Fe, Argentina  
- **Soporte T茅cnico**: Disponible v铆a WhatsApp y web

## 馃殌 Puesta en Producci贸n

### Lista de Verificaci贸n

- [ ] Cambiar todas las contrase帽as predefinidas
- [ ] Configurar datos reales del restaurante
- [ ] Subir im谩genes de productos
- [ ] Configurar Google Maps API (opcional)
- [ ] Probar pedidos online completos
- [ ] Verificar horarios de atenci贸n
- [ ] Configurar m茅todos de pago
- [ ] Probar notificaciones
- [ ] Backup de base de datos
- [ ] Certificado SSL configurado
- [ ] Probar sistema de llamadas de mesero
- [ ] Verificar impresi贸n de tickets
- [ ] Configurar usuarios del personal
- [ ] Configurar control de stock en productos necesarios
- [ ] Establecer l铆mites de alerta de inventario
- [ ] Verificar funcionamiento de ajustes de stock
- [ ] Ejecutar instalador completo desde `/install/`
- [ ] Configurar control de stock en productos necesarios
- [ ] Establecer l铆mites de alerta de inventario
- [ ] Verificar funcionamiento de ajustes de stock
- [ ] **Eliminar carpeta `install/`** por seguridad

### Variables de Entorno Recomendadas

```php
// Producci贸n
define('DEBUG_MODE', false);
define('ENVIRONMENT', 'production');

// Desarrollo
define('DEBUG_MODE', true);
define('ENVIRONMENT', 'development');
```

## 馃搵 Changelog

### Versi贸n 2.1.0
- Sistema completo de gesti贸n de restaurante
- Pedidos online integrados con panel dedicado
- Panel de administraci贸n responsive
- Reportes con gr谩ficos avanzados
- Sistema de roles y permisos granular
- Notificaciones en tiempo real
- Men煤 QR para mesas
- Integraci贸n con Google Maps
- Sistema de llamadas de mesero
- Gesti贸n completa de usuarios con interfaz m贸vil
- Sistema de impresi贸n de tickets personalizable
- Configuraci贸n avanzada del sistema
- Interfaz optimizada para dispositivos t谩ctiles
- Instalador autom谩tico modular con verificaci贸n de requisitos
- Sistema completo de control de stock e inventario
- Tabla `stock_movements` para historial de movimientos
- Integraci贸n de WhatsApp Business API en instalaci贸n
- Configuraci贸n autom谩tica de temas y respuestas autom谩ticas


### Pr贸ximas Versiones
- **v2.1.1** (En desarrollo):
  - Integraci贸n completa con Mercado Pago API
  - Sistema de backup autom谩tico de base de datos
  - Mejoras en la interfaz de pagos
  - Panel de gesti贸n de transacciones
  - 
---

**隆Bienvenido al futuro de la gesti贸n de restaurantes!** 馃嵔锔?

Para soporte adicional o consultas, revise la documentaci贸n t茅cnica en los comentarios del c贸digo fuente.# mi_restaurant_delivery
