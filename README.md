# Sistema de Gesti��n Gastron��mica

Un sistema completo de gesti��n para restaurantes que incluye punto de venta, pedidos online, gesti��n de mesas, cocina, delivery y reportes avanzados.

## ?? Caracter��sticas Principales

### ?? Panel de Administraci��n
- **Dashboard en tiempo real** con estad��sticas y notificaciones
- **Gesti��n de ��rdenes** tradicionales y online
- **Control de mesas** con estados visuales
- **Panel de cocina** con tiempos de preparaci��n
- **Gesti��n de delivery** con seguimiento
- **Reportes avanzados** con gr��ficos y exportaci��n
- **Sistema de usuarios** con roles y permisos
- **Gesti��n de productos** y categor��as
- **Control de inventario** con seguimiento en tiempo real
- **Configuraci��n del sistema** centralizada
- **Instalador autom��tico** modular en 5 pasos
- **Control de inventario** con seguimiento en tiempo real

### ?? Experiencia del Cliente
- **Men�� online** responsive con carrito de compras
- **Men�� QR** para mesas sin contacto
- **Pedidos online** con validaci��n de direcciones
- **Integraci��n con Google Maps** para delivery
- **Llamada al mesero** desde c��digo QR
- **Validaci��n de horarios** de atenci��n

### ?? Notificaciones en Tiempo Real
- **Alertas sonoras** para nuevos pedidos
- **Notificaciones visuales** con animaciones
- **Sistema de llamadas** de mesa
- **Actualizaciones autom��ticas** del estado

## ??? Tecnolog��as Utilizadas

- **Backend**: PHP 8.0+
- **Base de datos**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5.3
- **Iconos**: Font Awesome 6.0
- **Gr��ficos**: Chart.js 3.9
- **Mapas**: Google Maps API
- **Tablas**: DataTables
- **Gesti��n de Stock**: Sistema de inventario integrado

## ?? Estructura del Proyecto

```
mi_restaurant_delivery/
������ index.php # P��gina principal del men�� online
������ 404.html # P��gina de error 404
������ 502.html # P��gina de error 502
������ menu-qr.php # Men�� accesible por c��digo QR
������ call_waiter.php # API para generar llamadas de mesero
������ install.php # Instalador del sistema
������ README.md # Documentaci��n del proyecto
������ .htaccess # Reglas de Apache (URLs amigables, seguridad, etc.)
������ estructura de archivos.txt # Archivo de referencia con la estructura del sistema
��
������ models/ # Modelos de datos del sistema
�� ������ Product.php # Modelo de productos
�� ������ Table.php # Modelo de mesas
�� ������ Category.php # Modelo de categor��as
�� ������ Payment.php # Modelo de pagos
�� ������ Order.php # Modelo de ��rdenes
��
������ config/ # Configuraci��n global del sistema
�� ������ config.php # Configuraci��n general (constantes, variables globales)
�� ������ database.php # Conexi��n a la base de datos
�� ������ auth.php # Sistema de autenticaci��n y sesiones
�� ������ functions.php # Funciones auxiliares y utilidades
�� ������ whatsapp_api.php         # Clase de integraci��n con WhatsApp Business API
�� ������ theme.php # Clase ThemeManager con toda la l��gica
��
������ admin/ # Panel de administraci��n
�� ������ api/ # APIs internas para el frontend
�� �� ������ products.php # API de gesti��n de productos
�� �� ������ stock-movements.php # Historial de movimientos de inventario
�� �� ������ update-item-status.php # Actualizaci��n del estado de ��tems
�� �� ������ delivery-stats.php # Estad��sticas de delivery
�� �� ������ delivery.php # API de gesti��n de deliveries
�� �� ������ online-orders-stats.php # Estad��sticas de pedidos online
�� �� ������ online-orders.php # API de pedidos online
�� �� ������ update-delivery.php # Actualizaci��n de estado de entregas
�� �� ������ orders.php # API de ��rdenes tradicionales
�� �� ������ kitchen.php # API del panel de cocina
�� �� ������ update-order-status.php # Actualizaci��n del estado de ��rdenes
�� �� ������ create-order.php # Creaci��n de ��rdenes desde el sistema
�� �� ������ tables.php # API de gesti��n de mesas
�� �� ������ regenerate-css.php # API para regenerar archivos CSS
�� �� ������ whatsapp-stats.php   # API de estad��sticas de WhatsApp
�� �� ������ online-orders-recent.php # Listado de pedidos online recientes
�� ��
�� ������ receipts/ # Archivos de recibos generados
�� �� ������ customer_ORD-.txt # Ejemplo de recibo de cliente
�� ��
�� ������ tickets/ # Tickets impresos para cocina/delivery
�� �� ������ kitchen_ORD-.txt # Ticket de orden en cocina
�� ��
�� ������ pages/ # P��ginas est��ticas del panel
�� �� ������ 403.php # P��gina de error 403 (acceso denegado)
�� ��
�� ������ uploads/ # Archivos subidos en el panel
�� �� ������ products/ # Im��genes de productos
�� ��
�� ������ products.php # Gesti��n de productos
�� ������ settings.php # Configuraci��n general del sistema
�� ������ permissions.php # Gesti��n de permisos y roles
�� ������ check_calls.php # Verificaci��n de llamadas de mesero
�� ������ delivery.php # Panel de gesti��n de deliveries
�� ������ attend_call.php # Atender llamadas de mesero
�� ������ online-orders.php # Gesti��n de pedidos online
�� ������ online-order-details.php # Detalle de un pedido online
�� ������ dashboard.php # Dashboard principal con estad��sticas
�� ������ reports.php # Reportes avanzados del sistema
�� ������ orders.php # Gesti��n de ��rdenes tradicionales
�� ������ kitchen.php # Panel de cocina
�� ������ users.php # Gesti��n de usuarios y roles
�� ������ tables.php # Gesti��n de mesas
�� ������ profile.php # Gesti��n del perfil de usuario con avatar y configuraci��n personal
�� ������ order-create.php # Crear o editar ��rdenes
�� ������ logout.php # Cerrar sesi��n
�� ������ order-details.php # Detalle de una orden
�� ������ print-order.php # Impresi��n de ��rdenes
�� ������ theme-settings.php # Panel principal de configuraci��n de temas
�� ������ whatsapp-answers.php      # Panel de configuraci��n de respuestas autom��ticas
�� ������ whatsapp-settings.php    # Configuraci��n de WhatsApp Business API
�� ������ whatsapp-messages.php    # Panel de gesti��n de conversaciones WhatsApp  
�� ������ whatsapp-webhook.php     # Webhook para recibir mensajes de WhatsApp
�� ������ login.php # P��gina de login
��
������ assets/ # Recursos est��ticos
�� ������ includes/ # Archivos de inclusi��n
�� ������ css/ # Hojas de estilo
�� �� ������ generate-theme.php # Generador de CSS din��mico
�� �� ������ dynamic-theme.css # Archivo CSS generado autom��ticamente
�� ��
�� ������ images/ # Im��genes del sistema
�� ������ js/ # Scripts JavaScript
��
������ database/ # Scripts de base de datos
������ bd.sql # Estructura y datos iniciales
```

## ?? Instalaci��n

### Requisitos del Sistema

- **PHP**: 7.4 o superior
- **MySQL**: 8.0 o superior
- **Apache/Nginx**: Servidor web
- **Extensiones PHP**:
  - PDO
  - PDO_MySQL
  - GD (para im��genes)
  - JSON
  - Session
  - mbstring
  - openssl
  - curl

### Instalaci��n Autom��tica (Recomendada)

El sistema incluye un instalador web modular dividido en pasos para una instalaci��n m��s organizada y mantenible.

1. **Descargar y extraer** el proyecto en su servidor web
2. **Crear base de datos** MySQL vac��a
3. **Navegar** a `http://su-dominio.com/install/`
4. **Seguir el asistente** de instalaci��n paso a paso:

#### Pasos del Instalador

**Paso 1: Verificaci��n de Requisitos y Configuraci��n de BD**
- Verificaci��n autom��tica de requisitos del sistema
- Configuraci��n de conexi��n a base de datos
- Generaci��n del archivo `config/config.php`

**Paso 2: Instalaci��n de Estructura de BD**
- Creaci��n autom��tica de todas las tablas necesarias:
  - Gesti��n de usuarios, roles y permisos
  - Sistema de productos con control de stock
  - Gesti��n de ��rdenes y pagos
  - Sistema de mesas y llamadas de mesero
  - Configuraci��n de temas din��micos
  - Integraci��n completa de WhatsApp Business API
  - **Tabla `stock_movements`** para historial de inventario
- Inserci��n de datos b��sicos del sistema
- Configuraci��n de roles y permisos
- Instalaci��n de respuestas autom��ticas de WhatsApp
- Configuraci��n de temas b��sicos

**Paso 3: Configuraci��n del Restaurante**
- Datos b��sicos del negocio
- Configuraci��n de delivery y horarios
- Creaci��n del usuario administrador
- Configuraci��n de APIs (Google Maps, WhatsApp)

**Paso 4: Datos de Ejemplo (Opcional)**
- Usuarios de ejemplo con diferentes roles
- **Productos de muestra con control de stock**:
  - Productos con y sin seguimiento de inventario
  - Configuraci��n de alertas de stock bajo
  - Datos realistas de costos y precios
- Mesas adicionales
- **Este paso funciona independientemente** y puede ejecutarse en cualquier momento

**Paso 5: Finalizaci��n**
- Resumen de la instalaci��n
- Credenciales de acceso
- Enlaces directos al sistema
- Instrucciones de seguridad

### Estructura de Archivos de Instalaci��n

```
install/
������ index.php              # Archivo principal de instalaci��n
������ install_common.php     # Funciones compartidas y estructura de BD
������ step1.php             # Requisitos del sistema y configuraci��n de BD
������ step2.php             # Instalaci��n de estructura de BD
������ step3.php             # Configuraci��n del restaurante
������ step4.php             # Datos de ejemplo (opcional)
������ step5.php             # Finalizaci��n
```

### Caracter��sticas del Instalador

- **Modular**: Cada paso es independiente y mantenible
- **Verificaci��n autom��tica**: Requisitos del sistema validados
- **Progreso visual**: Indicadores de progreso en cada paso
- **Navegaci��n flexible**: Posibilidad de saltar o repetir pasos
- **Datos de ejemplo opcionales**: El paso 4 puede ejecutarse despu��s de la instalaci��n principal
- **Seguridad**: Verificaciones y validaciones en cada paso
- **Instalaci��n completa**: Incluye todas las tablas necesarias para:
  - Sistema de productos con control de stock
  - Gesti��n de inventario con historial de movimientos
  - WhatsApp Business API con respuestas autom��ticas
  - Sistema de temas din��micos
  - Estructura completa de ��rdenes y pagos

### Base de Datos Instalada

El instalador crea autom��ticamente las siguientes tablas:

**Sistema Core:**
- `users`, `roles` - Gesti��n de usuarios y permisos
- `settings` - Configuraci��n del sistema
- `categories`, `products` - Gesti��n de productos
- `stock_movements` - **Historial de movimientos de inventario**

**Gesti��n de ��rdenes:**
- `orders`, `order_items` - ��rdenes tradicionales
- `online_orders` - Pedidos online
- `payments`, `online_orders_payments` - Sistema de pagos
- `tables`, `waiter_calls` - Gesti��n de mesas

**Sistema de Temas:**
- `theme_settings` - Configuraci��n de temas
- `custom_themes` - Temas personalizados
- `theme_history` - Historial de cambios

**WhatsApp Business API:**
- `whatsapp_messages` - Conversaciones
- `whatsapp_logs` - Logs de env��o
- `whatsapp_auto_responses` - Respuestas autom��ticas
- `whatsapp_media_uploads` - Archivos multimedia

### Post-Instalaci��n

**Importante para la seguridad:**
- ?? **Eliminar toda la carpeta `install/`** despu��s de completar la instalaci��n
- Cambiar todas las contrase?as predefinidas
- Configurar HTTPS en producci��n
- Verificar permisos de archivos y carpetas

### Soluci��n de Problemas de Instalaci��n

**El sistema ya est�� instalado:**
- Si aparece este mensaje y desea reinstalar, elimine el archivo `config/installed.lock`
- Para agregar solo datos de ejemplo, acceda directamente a `install/step4.php`

**Error de conexi��n a base de datos:**
- Verificar credenciales de MySQL
- Asegurar que la base de datos existe y est�� accesible
- Comprobar que las extensiones PHP est��n instaladas

**Permisos de escritura:**
- Verificar permisos 755 en carpetas de uploads
- Asegurar que el servidor web puede escribir en `config/`

**Requisitos no cumplidos:**
- Actualizar PHP a versi��n 7.4 o superior
- Instalar extensiones PHP faltantes
- Verificar configuraci��n del servidor web

### Instalaci��n Manual (Avanzada)

Si prefiere instalar manualmente:

1. **Configurar base de datos**:
   ```sql
   CREATE DATABASE comidasm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar estructura**:
   ```bash
   mysql -u usuario -p comidasm < database/bd.sql
   ```

3. **Configurar archivo de configuraci��n**:
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

5. **Crear archivo de instalaci��n completada**:
   ```bash
   echo "$(date)" > config/installed.lock
   ```

### Post-Instalaci��n

**Importante para la seguridad:**
- ?? **Eliminar toda la carpeta `install/`** despu��s de completar la instalaci��n
- Cambiar todas las contrase?as predefinidas
- Configurar HTTPS en producci��n
- Verificar permisos de archivos y carpetas

### Soluci��n de Problemas de Instalaci��n

**El sistema ya est�� instalado:**
- Si aparece este mensaje y desea reinstalar, elimine el archivo `config/installed.lock`
- Para agregar solo datos de ejemplo, acceda directamente a `install/step4.php`

**Error de conexi��n a base de datos:**
- Verificar credenciales de MySQL
- Asegurar que la base de datos existe y est�� accesible
- Comprobar que las extensiones PHP est��n instaladas

**Permisos de escritura:**
- Verificar permisos 755 en carpetas de uploads
- Asegurar que el servidor web puede escribir en `config/`

**Requisitos no cumplidos:**
- Actualizar PHP a versi��n 7.4 o superior
- Instalar extensiones PHP faltantes
- Verificar configuraci��n del servidor web

## ?? Configuraci��n

### Configuraci��n B��sica

Acceder a **Admin > Configuraci��n** para ajustar:

- **Datos del restaurante**: Nombre, tel��fono, direcci��n
- **Horarios**: Apertura, cierre, cierre de cocina
- **Delivery**: Costo, distancia m��xima, monto m��nimo
- **Pagos**: M��todos aceptados, configuraci��n de impuestos
- **Notificaciones**: Sonidos, alertas autom��ticas

### Google Maps (Opcional)

Para habilitar autocompletado de direcciones:

1. **Obtener API Key** de Google Maps
2. **Configurar en**: Admin > Configuraci��n > Google Maps API Key
3. **Habilitar APIs**:
   - Places API
   - Geocoding API
   - Maps JavaScript API

### Configuraci��n de Roles

El sistema incluye roles predefinidos, pero puede:

- **Crear roles personalizados**
- **Asignar permisos espec��ficos**:
  - `all`: Acceso completo
  - `orders`: Gesti��n de ��rdenes tradicionales
  - `online_orders`: Gesti��n de pedidos online
  - `products`: Gesti��n de productos
  - `users`: Gesti��n de usuarios
  - `tables`: Gesti��n de mesas
  - `reports`: Reportes y estad��sticas
  - `kitchen`: Panel de cocina
  - `delivery`: Gesti��n de delivery
  - `settings`: Configuraci��n del sistema

  
### ?? Control de Stock e Inventario

Sistema avanzado de gesti��n de inventario con seguimiento en tiempo real y alertas autom��ticas.

#### Caracter��sticas del Sistema de Stock

- **Control opcional por producto** - Activar/desactivar gesti��n de inventario individualmente
- **Seguimiento en tiempo real** - Actualizaci��n autom��tica de cantidades
- **Alertas de stock bajo** - Notificaciones configurables por producto
- **Historial de movimientos** - Registro completo de entradas y salidas
- **Ajustes manuales** - Correcciones de inventario con motivos
- **Indicadores visuales** - Barras de progreso y badges de estado
- **Estad��sticas de inventario** - Dashboard con m��tricas en vivo

#### Funcionalidades Principales

**Gesti��n de Productos con Stock:**
- ? **Activaci��n selectiva** - Control de inventario opcional por producto
- ? **Stock actual** - Cantidad disponible en tiempo real
- ? **L��mites de alerta** - Configuraci��n personalizada de stock m��nimo
- ? **Estados visuales** - Sin stock, stock bajo, stock normal
- ? **C��lculos autom��ticos** - M��rgenes de ganancia en tiempo real
- ? **Validaciones robustas** - Prevenci��n de stock negativo

**Panel de Ajustes de Stock:**
- **Modal dedicado** para ajustes r��pidos de inventario
- **Tipos de movimiento**: Entrada (agregar) y Salida (reducir)
- **Motivos predefinidos**:
  - Ajuste manual
  - Inventario f��sico
  - Producto da?ado/vencido
  - Venta directa
  - Compra/Reposici��n
  - Correcci��n de error
  - Motivos personalizados
- **Vista previa** del nuevo stock antes de confirmar
- **Alertas autom��ticas** si el ajuste genera stock cr��tico

**Dashboard de Inventario:**
- **Productos con control** - Cantidad total bajo seguimiento
- **Stock bueno** - Productos con inventario normal
- **Stock bajo** - Productos cerca del l��mite m��nimo
- **Sin stock** - Productos agotados
- **Alertas prominentes** para productos cr��ticos

**Historial de Movimientos:**
- **Registro completo** de todos los cambios de stock
- **Informaci��n detallada**: Usuario, fecha, cantidad, motivo
- **Trazabilidad total** del inventario
- **Reportes de movimientos** por producto y periodo

#### Caracter��sticas T��cnicas del Stock

- **Base de datos optimizada** con tabla `stock_movements`
- **Transacciones seguras** para prevenir inconsistencias
- **Validaciones m��ltiples** en frontend y backend
- **Interfaz responsive** optimizada para m��viles
- **Integraci��n completa** con sistema de productos existente
- **API REST** para ajustes program��ticos
- **Logs autom��ticos** de todas las operaciones

#### Interfaz de Usuario Mejorada

**Tarjetas de Productos:**
- **Indicadores de stock** en esquina superior
- **Barras de progreso** mostrando nivel de inventario
- **Badges din��micos** (Sin stock, Stock bajo, Disponible)
- **Botones de acci��n r��pida** para ajustar stock
- **Colores sem��nticos** seg��n estado del inventario

**Modal de Productos Expandido:**
- **Secci��n dedicada** de gesti��n de inventario
- **Switch de activaci��n** para control de stock
- **Campos de stock actual** y l��mite de alerta
- **Indicadores de estado** en tiempo real
- **Validaciones visuales** instant��neas

**Alertas Inteligentes:**
- **Notificaciones autom��ticas** de productos con stock bajo
- **Lista expandible** con acciones directas
- **Auto-actualizaci��n** cada vez que se modifica inventario
- **Integraci��n con dashboard** principal

#### Flujo de Trabajo del Stock

1. **Configuraci��n inicial**:
   - Activar control de stock por producto
   - Establecer cantidad inicial
   - Configurar l��mite de alerta

2. **Operaci��n diaria**:
   - Visualizaci��n autom��tica de alertas
   - Ajustes r��pidos desde tarjetas de productos
   - Seguimiento en dashboard de inventario

3. **Gesti��n avanzada**:
   - Ajustes con motivos espec��ficos
   - Revisi��n de historial de movimientos
   - Reportes de inventario

#### Beneficios del Sistema

- **Control preciso** del inventario sin complejidad excesiva
- **Alertas proactivas** evitan quiebres de stock
- **Trazabilidad completa** para auditor��as
- **Interfaz intuitiva** sin curva de aprendizaje
- **Integraci��n transparente** con flujo de trabajo existente
- **Flexibilidad total** - usar solo en productos necesarios


## ?? M��dulos del Sistema

### ?? Dashboard
- **Estad��sticas en tiempo real**
- **��rdenes recientes** de todos los tipos
- **Estado de mesas** visual
- **Notificaciones autom��ticas**
- **Accesos r��pidos** seg��n el rol

### ?? Gesti��n de ��rdenes
- **��rdenes tradicionales**: Mesa, delivery, retiro
- **Pedidos online**: Integraci��n completa
- **Estados de orden**: Pendiente �� Confirmado �� Preparando �� Listo �� Entregado
- **Pagos**: M��ltiples m��todos (efectivo, tarjeta, transferencia, QR)
- **Filtros avanzados** por fecha, estado, tipo

### ?? Pedidos Online
- **Sistema completo** de pedidos por internet
- **Carrito de compras** con validaci��n
- **Autocompletado de direcciones** con Google Maps
- **Verificaci��n de zona** de delivery
- **Formateo autom��tico** de tel��fonos argentinos
- **Confirmaci��n por WhatsApp**
- **Estados en tiempo real**
- **Panel de gesti��n** dedicado con:
  - Aceptaci��n/rechazo de pedidos
  - Tiempos estimados de preparaci��n
  - Seguimiento completo del proceso
  - Integraci��n con WhatsApp autom��tico
  - Sistema de pagos integrado

### ??? Gesti��n de Mesas
- **Vista visual** de todas las mesas
- **Estados**: Libre, ocupada, reservada, mantenimiento
- **Capacidad** y ubicaci��n
- **Asignaci��n autom��tica** de ��rdenes
- **Representaci��n gr��fica** con sillas seg��n capacidad
- **Acciones r��pidas** desde cada mesa

### ????? Panel de Cocina
- **��rdenes por preparar** en tiempo real
- **Tiempos de preparaci��n**
- **Estados por item**
- **Priorizaci��n autom��tica**
- **Actualizaci��n en vivo**

### ??? Gesti��n de Delivery
- **��rdenes listas** para entrega
- **Informaci��n del cliente** completa
- **Direcciones con mapas**
- **Tiempos de entrega**
- **Estado de entrega**

### ??? Sistema de Impresi��n
- **Tickets de venta** personalizables
- **Impresi��n autom��tica** opcional
- **Formatos m��ltiples** (58mm, 80mm)
- **Vista previa** antes de imprimir
- **Informaci��n completa** del pedido y pagos

### ?? Reportes Avanzados
- **Ventas diarias** con gr��ficos
- **Productos m��s vendidos**
- **Rendimiento del personal**
- **An��lisis de mesas**
- **M��todos de pago**
- **Comparaci��n de per��odos**
- **Exportaci��n a Excel/CSV**

### ?? Men�� QR
- **C��digo QR** para cada mesa
- **Men�� digital** responsive
- **Filtros por categor��a**
- **Llamada al mesero** integrada
- **Sin instalaci��n** de apps

### ?? Gesti��n de Usuarios
- **Roles y permisos** granulares
- **Interfaz responsive** optimizada para m��vil
- **Vista de tarjetas** en dispositivos m��viles
- **Filtros por rol** y estado
- **Gesti��n de contrase?as**
- **Activaci��n/desactivaci��n** de usuarios
- **Interfaz t��ctil** optimizada

### ?? Perfil de Usuario

Sistema completo de gesti��n de perfiles personales para todos los usuarios del sistema.

#### Caracter��sticas del Perfil

- **Informaci��n personal completa**:
  - Edici��n de nombre completo
  - Actualizaci��n de email con validaci��n
  - Gesti��n de n��mero de tel��fono
  - Visualizaci��n del rol asignado

- **Sistema de avatars avanzado**:
  - Subida de im��genes de perfil (JPG, PNG, GIF)
  - L��mite de 2MB por archivo
  - Generaci��n autom��tica de iniciales si no hay avatar
  - Vista previa antes de subir
  - Eliminaci��n autom��tica de avatars anteriores

- **Cambio de contrase?a seguro**:
  - Verificaci��n de contrase?a actual
  - Indicador visual de fortaleza de contrase?a
  - Validaci��n de coincidencia en tiempo real
  - Requisito m��nimo de 6 caracteres
  - Opci��n de mostrar/ocultar contrase?as

- **Estad��sticas personales**:
  - Fecha de registro en el sistema
  - D��as activo en la plataforma
  - ��ltimo acceso registrado
  - Estado actual de la cuenta

#### Funcionalidades T��cnicas

- **Validaci��n en tiempo real** con JavaScript
- **Compatibilidad autom��tica** con base de datos existente
- **Creaci��n autom��tica** de columnas `avatar` y `last_login` si no existen
- **Interfaz responsive** optimizada para dispositivos m��viles
- **Integraci��n completa** con sistema de temas din��mico
- **Gesti��n segura** de archivos subidos
- **Validaciones robustas** del lado servidor y cliente

#### Seguridad Implementada

- **Verificaci��n de contrase?a actual** antes de cambios
- **Validaci��n de formato** de emails
- **Verificaci��n de unicidad** de emails
- **L��mites de tama?o** y tipo de archivos
- **Sanitizaci��n** de datos de entrada
- **Protecci��n contra** sobrescritura de archivos

#### Interfaz de Usuario

- **Dise?o moderno** con gradientes y efectos visuales
- **Animaciones suaves** para mejor experiencia
- **Feedback visual inmediato** en formularios
- **Indicadores de estado** para todas las acciones
- **Responsividad completa** para m��viles y tablets
- **Accesibilidad mejorada** con labels y ARIA

Este m��dulo proporciona a cada usuario control total sobre su informaci��n personal y configuraci��n de cuenta, manteniendo la seguridad y consistencia del sistema.

### ?? Configuraci��n Avanzada
- **Configuraci��n general** del restaurante
- **Configuraci��n de negocio** (impuestos, delivery)
- **Configuraci��n de pedidos online**
- **Horarios de atenci��n**
- **Integraci��n con Google Maps**
- **Configuraciones del sistema**
- **Pruebas de configuraci��n** integradas

## ?? Sistema de Llamadas de Mesero

### Funcionalidades
- **Llamada desde c��digo QR** de mesa
- **Notificaciones en tiempo real** al personal
- **Estado de llamadas** (pendiente/atendida)
- **Hist��rico de llamadas**
- **Integraci��n con panel de mesas**

### Archivos del Sistema
- `call_waiter.php`: API para generar llamadas
- `attend_call.php`: Marcar llamadas como atendidas
- `check_calls.php`: Verificar llamadas pendientes

## ?? Seguridad

### Medidas Implementadas
- **Autenticaci��n** con hash seguro de contrase?as
- **Autorizaci��n** basada en roles y permisos
- **Protecci��n CSRF** en formularios
- **Validaci��n de datos** en servidor y cliente
- **Escape de HTML** para prevenir XSS
- **Sesiones seguras** con configuraci��n httponly
- **Validaci��n de archivos** subidos

### Recomendaciones
- **Cambiar contrase?as** predefinidas
- **Usar HTTPS** en producci��n
- **Backup regular** de la base de datos
- **Actualizar** PHP y MySQL regularmente
- **Monitorear logs** de acceso

## ?? Personalizaci��n

### Temas y Estilos
- **Variables CSS** para colores principales
- **Responsive design** para todos los dispositivos
- **Iconos personalizables** con Font Awesome
- **Animaciones suaves** para mejor UX
- **Interfaz optimizada** para dispositivos t��ctiles

### ?? Sistema de Gesti��n de Estilos Din��micos

El sistema incluye un potente m��dulo de personalizaci��n de temas que permite modificar la apariencia visual de toda la aplicaci��n en tiempo real.

#### Caracter��sticas del Sistema de Temas

- **Editor visual de colores** con color pickers interactivos
- **Vista previa en tiempo real** de los cambios
- **Temas predefinidos** profesionales (Predeterminado, Oscuro, Verde, Morado, Azul, Naranja)
- **Generador autom��tico de paletas de colores**:
  - Colores aleatorios
  - Colores complementarios  
  - Colores an��logos
- **Configuraci��n de tipograf��a** con preview en vivo
- **Personalizaci��n de layout** (bordes, espaciado, sidebar)
- **Sistema de importaci��n/exportaci��n** de temas
- **Backup autom��tico** de configuraciones
- **Validaci��n de integridad** del tema
- **CSS din��mico** generado autom��ticamente


#### Uso del Sistema de Temas

1. **Acceder al configurador**: Admin > Configuraci��n > Tema
2. **Personalizar colores**: 
   - Colores principales (primario, secundario, acento)
   - Colores de estado (��xito, advertencia, peligro, informaci��n)
   - Vista previa instant��nea de cambios
3. **Configurar tipograf��a**:
   - Selecci��n de fuentes (Segoe UI, Inter, Roboto, Open Sans, Montserrat, Poppins)
   - Tama?os de fuente (base, peque?o, grande)
   - Preview en tiempo real
4. **Ajustar dise?o**:
   - Radio de bordes (angular, normal, redondeado)
   - Ancho del sidebar
   - Intensidad de sombras
5. **Aplicar temas predefinidos** con un solo clic
6. **Generar paletas autom��ticas**:
   - Colores aleatorios para inspiraci��n
   - Colores complementarios para alto contraste
   - Colores an��logos para armon��a visual

#### Herramientas Avanzadas

- **Exportar tema**: Descarga configuraci��n actual en formato JSON
- **Importar tema**: Carga temas previamente exportados
- **Restablecer**: Vuelve a la configuraci��n predeterminada
- **Regenerar CSS**: Actualiza archivos CSS din��micos
- **Crear backup**: Respaldo de seguridad de la configuraci��n
- **Validar tema**: Verifica integridad de colores y configuraciones

#### Caracter��sticas T��cnicas

- **CSS Variables**: Uso de variables CSS para cambios en tiempo real
- **Responsive design**: Todos los temas se adaptan a dispositivos m��viles
- **Validaci��n robusta**: Verificaci��n de colores hexadecimales y medidas CSS
- **Cache inteligente**: Optimizaci��n de rendimiento
- **Fallback autom��tico**: CSS de emergencia si hay errores
- **Compatibilidad total**: Funciona con todos los m��dulos del sistema

#### Beneficios

- **Branding personalizado**: Adapta el sistema a la identidad visual del restaurante
- **Mejor experiencia de usuario**: Interface m��s atractiva y profesional
- **Facilidad de uso**: Sin conocimientos t��cnicos requeridos
- **Flexibilidad total**: Desde cambios sutiles hasta transformaciones completas
- **Consistencia visual**: Todos los m��dulos mantienen el tema seleccionado
- 

### Funcionalidades Adicionales
El sistema es extensible para agregar:
- **Reservas online**
- **Programa de fidelizaci��n**
- **Integraci��n con redes sociales**
- **Sistemas de pago online**
- **Facturaci��n electr��nica**
- **M��ltiples sucursales**
 

### ?? Sistema de WhatsApp Business API

El sistema incluye integraci��n completa con WhatsApp Business API para comunicaci��n autom��tica con clientes y gesti��n de conversaciones avanzadas.

#### Caracter��sticas del Sistema WhatsApp

- **API de WhatsApp Business** completamente integrada
- **Env��o autom��tico** de notificaciones de pedidos (confirmaci��n, preparaci��n, listo, entregado)
- **Webhook autom��tico** para recibir mensajes entrantes con configuraci��n segura
- **Respuestas autom��ticas configurables** desde panel web con variables din��micas
- **Panel de gesti��n de conversaciones** con interface tipo chat
- **Sistema de prioridades** y tipos de coincidencia para respuestas
- **Rate limiting** y detecci��n de duplicados
- **Logs completos** de env��os y recepciones
- **Configuraci��n din��mica** del restaurante
- **Limpieza autom��tica** de n��meros telef��nicos argentinos
- **Sistema de fallback** a WhatsApp Web si falla la API
- **Guardado de conversaciones completas** para seguimiento

#### Funcionalidades de Mensajer��a

**Env��o Autom��tico:**
- ? **Confirmaciones autom��ticas** de pedidos online al aceptar
- ? **Actualizaciones de estado** en tiempo real (preparando, listo)
- ? **Notificaciones de entrega** autom��ticas
- ? **Mensajes de rechazo** con motivo especificado
- ? **Guardado autom��tico** en conversaciones para seguimiento
- ? **Fallback inteligente** a WhatsApp Web si la API falla

**Sistema de Respuestas Autom��ticas Avanzado:**
- **Editor web de respuestas** con variables din��micas
- **Tipos de coincidencia**: Contiene, exacto, empieza con, termina con
- **Sistema de prioridades** (mayor n��mero = mayor prioridad)
- **Variables autom��ticas**: `{restaurant_name}`, `{restaurant_web}`, `{restaurant_phone}`, etc.
- **Estad��sticas de uso** para cada respuesta
- **Activaci��n/desactivaci��n** individual
- **Contador de usos** y fechas de creaci��n/actualizaci��n

**Ejemplos de respuestas configurables:**

| Palabras Clave | Respuesta | Tipo |
|----------------|-----------|------|
| hola,saludos,buenos | ?Hola! Gracias por contactar a {restaurant_name}. Para pedidos: {restaurant_web} | Contiene |
| menu,men��,carta | Vea nuestro men�� completo en {restaurant_web} | Contiene |
| horario,horarios | Horarios: {opening_time} - {closing_time} | Contiene |
| estado,pedido | Para consultar estado, proporcione n��mero de orden | Contiene |
| direccion,ubicacion | Nuestra direcci��n: {restaurant_address} | Contiene |

#### Panel de Gesti��n de Conversaciones

- **Vista unificada** de todas las conversaciones por contacto
- **Interface tipo chat** con burbujas de mensajes cronol��gicas
- **Identificaci��n visual** de conversaciones nuevas/no le��das
- **Respuestas manuales** desde el panel con guardado autom��tico
- **Marcado autom��tico** como le��do
- **Filtros avanzados** por tel��fono, fecha, estado de lectura
- **Estad��sticas en tiempo real** de mensajes y conversaciones
- **Enlaces directos** a WhatsApp Web
- **Auto-expansi��n** de conversaciones nuevas
- **Auto-refresh** cada 30 segundos

#### Panel de Configuraci��n de Respuestas Autom��ticas

- **Editor visual** con formularios intuitivos
- **Gesti��n completa** de palabras clave y respuestas
- **Variables din��micas** con reemplazo autom��tico:
  - `{restaurant_name}` - Nombre del restaurante
  - `{restaurant_web}` - Sitio web
  - `{restaurant_phone}` - Tel��fono
  - `{restaurant_email}` - Email
  - `{restaurant_address}` - Direcci��n
  - `{opening_time}` / `{closing_time}` - Horarios
  - `{delivery_fee}` - Costo de env��o
  - `{min_delivery_amount}` - Monto m��nimo delivery
  - `{order_number}` / `{order_status}` - Info de pedidos
- **Vista previa** de respuestas en tiempo real
- **Estad��sticas de uso** por respuesta
- **Sistema de backup** y exportaci��n

#### Configuraci��n y Seguridad

**Configuraci��n en Meta for Developers:**
```
Callback URL: https://tu-dominio.com/admin/whatsapp-webhook.php
Verify Token: Configurable desde el panel (sin hardcodear)
Webhook Fields: messages, messaging_postbacks, message_deliveries
```

**Credenciales seguras:**
- Access Token de WhatsApp Business API (almacenado en BD)
- Phone Number ID del n��mero WhatsApp Business
- Webhook Token para verificaci��n (configurable)
- **Sin credenciales hardcodeadas** en el c��digo

**Funciones de prueba integradas:**
- ? Prueba de env��o de mensajes
- ? Verificaci��n de webhook
- ? Validaci��n de configuraci��n
- ? Logs detallados de errores

#### Caracter��sticas T��cnicas Mejoradas

- **Configuraci��n centralizada** usando `config/config.php` y `config/database.php`
- **Limpieza autom��tica** de n��meros telef��nicos argentinos (formato 549XXXXXXXXX)
- **Detecci��n autom��tica** de pedidos relacionados por tel��fono
- **Rate limiting** (m��ximo 1 respuesta autom��tica por minuto por n��mero)
- **Detecci��n de duplicados** para evitar mensajes repetidos
- **Almacenamiento seguro** de mensajes y logs en base de datos
- **Manejo de errores** robusto con fallbacks
- **Webhook seguro** con validaci��n de origen
- **API REST** para integraci��n con otros sistemas
- **Creaci��n autom��tica** de tablas si no existen

#### Archivos del Sistema WhatsApp

```
admin/
������ whatsapp-settings.php     # Configuraci��n de WhatsApp Business API
������ whatsapp-messages.php     # Panel de gesti��n de conversaciones  
������ whatsapp-answers.php      # Configuraci��n de respuestas autom��ticas
������ whatsapp-webhook.php      # Webhook para recibir mensajes

config/
������ whatsapp_api.php         # Clase de integraci��n con WhatsApp Business API
```

#### Variables de Configuraci��n

```php
// Configuraci��n en la base de datos
'whatsapp_enabled' => '1'                    // Habilitar env��o autom��tico
'whatsapp_fallback_enabled' => '1'          // Fallback a WhatsApp Web
'whatsapp_auto_responses' => '1'             // Respuestas autom��ticas
'whatsapp_access_token' => 'EAAxxxxxxxxx'    // Token de Meta
'whatsapp_phone_number_id' => '123456789'    // ID del n��mero de WhatsApp
'whatsapp_webhook_token' => 'mi-token-123'   // Token del webhook
```



## ?? Soluci��n de Problemas

### Problemas Comunes

1. **Error de conexi��n a base de datos**:
   - Verificar credenciales en `config/config.php`
   - Comprobar que el servidor MySQL est�� activo

2. **No aparecen im��genes**:
   - Verificar permisos de carpeta `uploads/`
   - Comprobar rutas en la base de datos

3. **Notificaciones no funcionan**:
   - Verificar configuraci��n de JavaScript
   - Comprobar permisos del navegador

4. **Google Maps no funciona**:
   - Verificar API Key v��lida
   - Comprobar APIs habilitadas en Google Console

5. **Pedidos online no funcionan**:
   - Verificar configuraci��n en Admin > Configuraci��n
   - Comprobar horarios de atenci��n
   - Verificar conexi��n a base de datos

### Logs y Depuraci��n
- **Logs de errores**: Activar error_log en PHP
- **Console del navegador**: Para errores de JavaScript
- **Network tab**: Para problemas de APIs

## ?? Soporte

### Archivos de Configuraci��n Importantes
- `config/config.php`: Configuraci��n principal
- `admin/api/`: APIs del sistema
- `database/comidasm.sql`: Estructura de base de datos

### Informaci��n del Sistema
- **Versi��n**: 1.0.0
- **Licencia**: MIT
- **PHP m��nimo**: 8.0
- **MySQL m��nimo**: 8.0

### Contacto y Desarrollo
- **Desarrollador**: Cellcom Technology  
- **Sitio Web**: [www.cellcomweb.com.ar](http://www.cellcomweb.com.ar)  
- **Tel��fono / WhatsApp**: +54 3482 549555  
- **Direcci��n**: Calle 9 N�� 539, Avellaneda, Santa Fe, Argentina  
- **Soporte T��cnico**: Disponible v��a WhatsApp y web

## ?? Puesta en Producci��n

### Lista de Verificaci��n

- [ ] Cambiar todas las contrase?as predefinidas
- [ ] Configurar datos reales del restaurante
- [ ] Subir im��genes de productos
- [ ] Configurar Google Maps API (opcional)
- [ ] Probar pedidos online completos
- [ ] Verificar horarios de atenci��n
- [ ] Configurar m��todos de pago
- [ ] Probar notificaciones
- [ ] Backup de base de datos
- [ ] Certificado SSL configurado
- [ ] Probar sistema de llamadas de mesero
- [ ] Verificar impresi��n de tickets
- [ ] Configurar usuarios del personal
- [ ] Configurar control de stock en productos necesarios
- [ ] Establecer l��mites de alerta de inventario
- [ ] Verificar funcionamiento de ajustes de stock
- [ ] Ejecutar instalador completo desde `/install/`
- [ ] Configurar control de stock en productos necesarios
- [ ] Establecer l��mites de alerta de inventario
- [ ] Verificar funcionamiento de ajustes de stock
- [ ] **Eliminar carpeta `install/`** por seguridad

### Variables de Entorno Recomendadas

```php
// Producci��n
define('DEBUG_MODE', false);
define('ENVIRONMENT', 'production');

// Desarrollo
define('DEBUG_MODE', true);
define('ENVIRONMENT', 'development');
```

## ?? Changelog

### Versi��n 2.1.0
- Sistema completo de gesti��n de restaurante
- Pedidos online integrados con panel dedicado
- Panel de administraci��n responsive
- Reportes con gr��ficos avanzados
- Sistema de roles y permisos granular
- Notificaciones en tiempo real
- Men�� QR para mesas
- Integraci��n con Google Maps
- Sistema de llamadas de mesero
- Gesti��n completa de usuarios con interfaz m��vil
- Sistema de impresi��n de tickets personalizable
- Configuraci��n avanzada del sistema
- Interfaz optimizada para dispositivos t��ctiles
- Instalador autom��tico modular con verificaci��n de requisitos
- Sistema completo de control de stock e inventario
- Tabla `stock_movements` para historial de movimientos
- Integraci��n de WhatsApp Business API en instalaci��n
- Configuraci��n autom��tica de temas y respuestas autom��ticas


### Pr��ximas Versiones
- **v2.1.1** (En desarrollo):
  - Integraci��n completa con Mercado Pago API
  - Sistema de backup autom��tico de base de datos
  - Mejoras en la interfaz de pagos
  - Panel de gesti��n de transacciones
  - 
---

**?Bienvenido al futuro de la gesti��n de restaurantes!** ???

Para soporte adicional o consultas, revise la documentaci��n t��cnica en los comentarios del c��digo fuente.# mi_restaurant_delivery