# Sistema de Gestión Gastronómica

Un sistema completo de gestión para restaurantes que incluye punto de venta, pedidos online, gestión de mesas, cocina, delivery y reportes avanzados.

## 🌟 Características Principales

### 💻 Panel de Administración
- **Dashboard en tiempo real** con estadísticas y notificaciones
- **Gestión de órdenes** tradicionales y online
- **Control de mesas** con estados visuales
- **Panel de cocina** con tiempos de preparación
- **Gestión de delivery** con seguimiento
- **Reportes avanzados** con gráficos y exportación
- **Sistema de usuarios** con roles y permisos
- **Gestión de productos** y categorías
- **Control de inventario** con seguimiento en tiempo real
- **Configuración del sistema** centralizada
- **Instalador automático** modular en 5 pasos
- **Control de inventario** con seguimiento en tiempo real

### 📱 Experiencia del Cliente
- **Menú online** responsive con carrito de compras
- **Menú QR** para mesas sin contacto
- **Pedidos online** con validación de direcciones
- **Integración con Google Maps** para delivery
- **Llamada al mesero** desde código QR
- **Validación de horarios** de atención

### 🔔 Notificaciones en Tiempo Real
- **Alertas sonoras** para nuevos pedidos
- **Notificaciones visuales** con animaciones
- **Sistema de llamadas** de mesa
- **Actualizaciones automáticas** del estado

## 🛠️ Tecnologías Utilizadas

- **Backend**: PHP 8.0+
- **Base de datos**: MySQL 8.0+
- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Framework CSS**: Bootstrap 5.3
- **Iconos**: Font Awesome 6.0
- **Gráficos**: Chart.js 3.9
- **Mapas**: Google Maps API
- **Tablas**: DataTables
- **Gestión de Stock**: Sistema de inventario integrado

## 📂 Estructura del Proyecto

```
mi_restaurant_delivery/
├── index.php # Página principal del menú online
├── 404.html # Página de error 404
├── 502.html # Página de error 502
├── menu-qr.php # Menú accesible por código QR
├── call_waiter.php # API para generar llamadas de mesero
├── install.php # Instalador del sistema
├── README.md # Documentación del proyecto
├── .htaccess # Reglas de Apache (URLs amigables, seguridad, etc.)
├── estructura de archivos.txt # Archivo de referencia con la estructura del sistema
│
├── models/ # Modelos de datos del sistema
│ ├── Product.php # Modelo de productos
│ ├── Table.php # Modelo de mesas
│ ├── Category.php # Modelo de categorías
│ ├── Payment.php # Modelo de pagos
│ └── Order.php # Modelo de órdenes
│
├── config/ # Configuración global del sistema
│ ├── config.php # Configuración general (constantes, variables globales)
│ ├── database.php # Conexión a la base de datos
│ ├── auth.php # Sistema de autenticación y sesiones
│ ├── functions.php # Funciones auxiliares y utilidades
│ ├── whatsapp_api.php         # Clase de integración con WhatsApp Business API
│ └── theme.php # Clase ThemeManager con toda la lógica
│
├── admin/ # Panel de administración
│ ├── api/ # APIs internas para el frontend
│ │ ├── products.php # API de gestión de productos
│ │ ├── stock-movements.php # Historial de movimientos de inventario
│ │ ├── update-item-status.php # Actualización del estado de ítems
│ │ ├── delivery-stats.php # Estadísticas de delivery
│ │ ├── delivery.php # API de gestión de deliveries
│ │ ├── online-orders-stats.php # Estadísticas de pedidos online
│ │ ├── online-orders.php # API de pedidos online
│ │ ├── update-delivery.php # Actualización de estado de entregas
│ │ ├── orders.php # API de órdenes tradicionales
│ │ ├── kitchen.php # API del panel de cocina
│ │ ├── update-order-status.php # Actualización del estado de órdenes
│ │ ├── create-order.php # Creación de órdenes desde el sistema
│ │ ├── tables.php # API de gestión de mesas
│ │ ├── regenerate-css.php # API para regenerar archivos CSS
│ │ ├── whatsapp-stats.php   # API de estadísticas de WhatsApp
│ │ └── online-orders-recent.php # Listado de pedidos online recientes
│ │
│ ├── receipts/ # Archivos de recibos generados
│ │ └── customer_ORD-.txt # Ejemplo de recibo de cliente
│ │
│ ├── tickets/ # Tickets impresos para cocina/delivery
│ │ └── kitchen_ORD-.txt # Ticket de orden en cocina
│ │
│ ├── pages/ # Páginas estáticas del panel
│ │ └── 403.php # Página de error 403 (acceso denegado)
│ │
│ ├── uploads/ # Archivos subidos en el panel
│ │ └── products/ # Imágenes de productos
│ │
│ ├── products.php # Gestión de productos
│ ├── settings.php # Configuración general del sistema
│ ├── permissions.php # Gestión de permisos y roles
│ ├── check_calls.php # Verificación de llamadas de mesero
│ ├── delivery.php # Panel de gestión de deliveries
│ ├── attend_call.php # Atender llamadas de mesero
│ ├── online-orders.php # Gestión de pedidos online
│ ├── online-order-details.php # Detalle de un pedido online
│ ├── dashboard.php # Dashboard principal con estadísticas
│ ├── reports.php # Reportes avanzados del sistema
│ ├── orders.php # Gestión de órdenes tradicionales
│ ├── kitchen.php # Panel de cocina
│ ├── users.php # Gestión de usuarios y roles
│ ├── tables.php # Gestión de mesas
│ ├── profile.php # Gestión del perfil de usuario con avatar y configuración personal
│ ├── order-create.php # Crear o editar órdenes
│ ├── logout.php # Cerrar sesión
│ ├── order-details.php # Detalle de una orden
│ ├── print-order.php # Impresión de órdenes
│ ├── theme-settings.php # Panel principal de configuración de temas
│ ├── whatsapp-answers.php      # Panel de configuración de respuestas automáticas
│ ├── whatsapp-settings.php    # Configuración de WhatsApp Business API
│ ├── whatsapp-messages.php    # Panel de gestión de conversaciones WhatsApp  
│ ├── whatsapp-webhook.php     # Webhook para recibir mensajes de WhatsApp
│ └── login.php # Página de login
│
├── assets/ # Recursos estáticos
│ ├── includes/ # Archivos de inclusión
│ ├── css/ # Hojas de estilo
│ │ ├── generate-theme.php # Generador de CSS dinámico
│ │ └── dynamic-theme.css # Archivo CSS generado automáticamente
│ │
│ ├── images/ # Imágenes del sistema
│ └── js/ # Scripts JavaScript
│
└── database/ # Scripts de base de datos
└── bd.sql # Estructura y datos iniciales
```

## 🚀 Instalación

### Requisitos del Sistema

- **PHP**: 7.4 o superior
- **MySQL**: 8.0 o superior
- **Apache/Nginx**: Servidor web
- **Extensiones PHP**:
  - PDO
  - PDO_MySQL
  - GD (para imágenes)
  - JSON
  - Session
  - mbstring
  - openssl
  - curl

### Instalación Automática (Recomendada)

El sistema incluye un instalador web modular dividido en pasos para una instalación más organizada y mantenible.

1. **Descargar y extraer** el proyecto en su servidor web
2. **Crear base de datos** MySQL vacía
3. **Navegar** a `http://su-dominio.com/install/`
4. **Seguir el asistente** de instalación paso a paso:

#### Pasos del Instalador

**Paso 1: Verificación de Requisitos y Configuración de BD**
- Verificación automática de requisitos del sistema
- Configuración de conexión a base de datos
- Generación del archivo `config/config.php`

**Paso 2: Instalación de Estructura de BD**
- Creación automática de todas las tablas necesarias:
  - Gestión de usuarios, roles y permisos
  - Sistema de productos con control de stock
  - Gestión de órdenes y pagos
  - Sistema de mesas y llamadas de mesero
  - Configuración de temas dinámicos
  - Integración completa de WhatsApp Business API
  - **Tabla `stock_movements`** para historial de inventario
- Inserción de datos básicos del sistema
- Configuración de roles y permisos
- Instalación de respuestas automáticas de WhatsApp
- Configuración de temas básicos

**Paso 3: Configuración del Restaurante**
- Datos básicos del negocio
- Configuración de delivery y horarios
- Creación del usuario administrador
- Configuración de APIs (Google Maps, WhatsApp)

**Paso 4: Datos de Ejemplo (Opcional)**
- Usuarios de ejemplo con diferentes roles
- **Productos de muestra con control de stock**:
  - Productos con y sin seguimiento de inventario
  - Configuración de alertas de stock bajo
  - Datos realistas de costos y precios
- Mesas adicionales
- **Este paso funciona independientemente** y puede ejecutarse en cualquier momento

**Paso 5: Finalización**
- Resumen de la instalación
- Credenciales de acceso
- Enlaces directos al sistema
- Instrucciones de seguridad

### Estructura de Archivos de Instalación

```
install/
├── index.php              # Archivo principal de instalación
├── install_common.php     # Funciones compartidas y estructura de BD
├── step1.php             # Requisitos del sistema y configuración de BD
├── step2.php             # Instalación de estructura de BD
├── step3.php             # Configuración del restaurante
├── step4.php             # Datos de ejemplo (opcional)
└── step5.php             # Finalización
```

### Características del Instalador

- **Modular**: Cada paso es independiente y mantenible
- **Verificación automática**: Requisitos del sistema validados
- **Progreso visual**: Indicadores de progreso en cada paso
- **Navegación flexible**: Posibilidad de saltar o repetir pasos
- **Datos de ejemplo opcionales**: El paso 4 puede ejecutarse después de la instalación principal
- **Seguridad**: Verificaciones y validaciones en cada paso
- **Instalación completa**: Incluye todas las tablas necesarias para:
  - Sistema de productos con control de stock
  - Gestión de inventario con historial de movimientos
  - WhatsApp Business API con respuestas automáticas
  - Sistema de temas dinámicos
  - Estructura completa de órdenes y pagos

### Base de Datos Instalada

El instalador crea automáticamente las siguientes tablas:

**Sistema Core:**
- `users`, `roles` - Gestión de usuarios y permisos
- `settings` - Configuración del sistema
- `categories`, `products` - Gestión de productos
- `stock_movements` - **Historial de movimientos de inventario**

**Gestión de Órdenes:**
- `orders`, `order_items` - Órdenes tradicionales
- `online_orders` - Pedidos online
- `payments`, `online_orders_payments` - Sistema de pagos
- `tables`, `waiter_calls` - Gestión de mesas

**Sistema de Temas:**
- `theme_settings` - Configuración de temas
- `custom_themes` - Temas personalizados
- `theme_history` - Historial de cambios

**WhatsApp Business API:**
- `whatsapp_messages` - Conversaciones
- `whatsapp_logs` - Logs de envío
- `whatsapp_auto_responses` - Respuestas automáticas
- `whatsapp_media_uploads` - Archivos multimedia

### Post-Instalación

**Importante para la seguridad:**
- ⚠️ **Eliminar toda la carpeta `install/`** después de completar la instalación
- Cambiar todas las contraseñas predefinidas
- Configurar HTTPS en producción
- Verificar permisos de archivos y carpetas

### Solución de Problemas de Instalación

**El sistema ya está instalado:**
- Si aparece este mensaje y desea reinstalar, elimine el archivo `config/installed.lock`
- Para agregar solo datos de ejemplo, acceda directamente a `install/step4.php`

**Error de conexión a base de datos:**
- Verificar credenciales de MySQL
- Asegurar que la base de datos existe y está accesible
- Comprobar que las extensiones PHP están instaladas

**Permisos de escritura:**
- Verificar permisos 755 en carpetas de uploads
- Asegurar que el servidor web puede escribir en `config/`

**Requisitos no cumplidos:**
- Actualizar PHP a versión 7.4 o superior
- Instalar extensiones PHP faltantes
- Verificar configuración del servidor web

### Instalación Manual (Avanzada)

Si prefiere instalar manualmente:

1. **Configurar base de datos**:
   ```sql
   CREATE DATABASE comidasm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar estructura**:
   ```bash
   mysql -u usuario -p comidasm < database/bd.sql
   ```

3. **Configurar archivo de configuración**:
   ```php
   // config/config.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'comidasm');
   define('DB_USER', 'tu_usuario');
   define('DB_PASS', 'tu_contraseña');
   
   define('BASE_URL', 'https://tu-dominio.com/');
   define('UPLOAD_PATH', 'uploads/');
   define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
   ```

4. **Crear carpetas con permisos**:
   ```bash
   mkdir -p config uploads uploads/products uploads/categories uploads/avatars whatsapp_media
   chmod 755 uploads/ admin/uploads/ whatsapp_media/
   ```

5. **Crear archivo de instalación completada**:
   ```bash
   echo "$(date)" > config/installed.lock
   ```

### Post-Instalación

**Importante para la seguridad:**
- ⚠️ **Eliminar toda la carpeta `install/`** después de completar la instalación
- Cambiar todas las contraseñas predefinidas
- Configurar HTTPS en producción
- Verificar permisos de archivos y carpetas

### Solución de Problemas de Instalación

**El sistema ya está instalado:**
- Si aparece este mensaje y desea reinstalar, elimine el archivo `config/installed.lock`
- Para agregar solo datos de ejemplo, acceda directamente a `install/step4.php`

**Error de conexión a base de datos:**
- Verificar credenciales de MySQL
- Asegurar que la base de datos existe y está accesible
- Comprobar que las extensiones PHP están instaladas

**Permisos de escritura:**
- Verificar permisos 755 en carpetas de uploads
- Asegurar que el servidor web puede escribir en `config/`

**Requisitos no cumplidos:**
- Actualizar PHP a versión 7.4 o superior
- Instalar extensiones PHP faltantes
- Verificar configuración del servidor web

## 🔧 Configuración

### Configuración Básica

Acceder a **Admin > Configuración** para ajustar:

- **Datos del restaurante**: Nombre, teléfono, dirección
- **Horarios**: Apertura, cierre, cierre de cocina
- **Delivery**: Costo, distancia máxima, monto mínimo
- **Pagos**: Métodos aceptados, configuración de impuestos
- **Notificaciones**: Sonidos, alertas automáticas

### Google Maps (Opcional)

Para habilitar autocompletado de direcciones:

1. **Obtener API Key** de Google Maps
2. **Configurar en**: Admin > Configuración > Google Maps API Key
3. **Habilitar APIs**:
   - Places API
   - Geocoding API
   - Maps JavaScript API

### Configuración de Roles

El sistema incluye roles predefinidos, pero puede:

- **Crear roles personalizados**
- **Asignar permisos específicos**:
  - `all`: Acceso completo
  - `orders`: Gestión de órdenes tradicionales
  - `online_orders`: Gestión de pedidos online
  - `products`: Gestión de productos
  - `users`: Gestión de usuarios
  - `tables`: Gestión de mesas
  - `reports`: Reportes y estadísticas
  - `kitchen`: Panel de cocina
  - `delivery`: Gestión de delivery
  - `settings`: Configuración del sistema

  
### 📦 Control de Stock e Inventario

Sistema avanzado de gestión de inventario con seguimiento en tiempo real y alertas automáticas.

#### Características del Sistema de Stock

- **Control opcional por producto** - Activar/desactivar gestión de inventario individualmente
- **Seguimiento en tiempo real** - Actualización automática de cantidades
- **Alertas de stock bajo** - Notificaciones configurables por producto
- **Historial de movimientos** - Registro completo de entradas y salidas
- **Ajustes manuales** - Correcciones de inventario con motivos
- **Indicadores visuales** - Barras de progreso y badges de estado
- **Estadísticas de inventario** - Dashboard con métricas en vivo

#### Funcionalidades Principales

**Gestión de Productos con Stock:**
- ✅ **Activación selectiva** - Control de inventario opcional por producto
- ✅ **Stock actual** - Cantidad disponible en tiempo real
- ✅ **Límites de alerta** - Configuración personalizada de stock mínimo
- ✅ **Estados visuales** - Sin stock, stock bajo, stock normal
- ✅ **Cálculos automáticos** - Márgenes de ganancia en tiempo real
- ✅ **Validaciones robustas** - Prevención de stock negativo

**Panel de Ajustes de Stock:**
- **Modal dedicado** para ajustes rápidos de inventario
- **Tipos de movimiento**: Entrada (agregar) y Salida (reducir)
- **Motivos predefinidos**:
  - Ajuste manual
  - Inventario físico
  - Producto dañado/vencido
  - Venta directa
  - Compra/Reposición
  - Corrección de error
  - Motivos personalizados
- **Vista previa** del nuevo stock antes de confirmar
- **Alertas automáticas** si el ajuste genera stock crítico

**Dashboard de Inventario:**
- **Productos con control** - Cantidad total bajo seguimiento
- **Stock bueno** - Productos con inventario normal
- **Stock bajo** - Productos cerca del límite mínimo
- **Sin stock** - Productos agotados
- **Alertas prominentes** para productos críticos

**Historial de Movimientos:**
- **Registro completo** de todos los cambios de stock
- **Información detallada**: Usuario, fecha, cantidad, motivo
- **Trazabilidad total** del inventario
- **Reportes de movimientos** por producto y periodo

#### Características Técnicas del Stock

- **Base de datos optimizada** con tabla `stock_movements`
- **Transacciones seguras** para prevenir inconsistencias
- **Validaciones múltiples** en frontend y backend
- **Interfaz responsive** optimizada para móviles
- **Integración completa** con sistema de productos existente
- **API REST** para ajustes programáticos
- **Logs automáticos** de todas las operaciones

#### Interfaz de Usuario Mejorada

**Tarjetas de Productos:**
- **Indicadores de stock** en esquina superior
- **Barras de progreso** mostrando nivel de inventario
- **Badges dinámicos** (Sin stock, Stock bajo, Disponible)
- **Botones de acción rápida** para ajustar stock
- **Colores semánticos** según estado del inventario

**Modal de Productos Expandido:**
- **Sección dedicada** de gestión de inventario
- **Switch de activación** para control de stock
- **Campos de stock actual** y límite de alerta
- **Indicadores de estado** en tiempo real
- **Validaciones visuales** instantáneas

**Alertas Inteligentes:**
- **Notificaciones automáticas** de productos con stock bajo
- **Lista expandible** con acciones directas
- **Auto-actualización** cada vez que se modifica inventario
- **Integración con dashboard** principal

#### Flujo de Trabajo del Stock

1. **Configuración inicial**:
   - Activar control de stock por producto
   - Establecer cantidad inicial
   - Configurar límite de alerta

2. **Operación diaria**:
   - Visualización automática de alertas
   - Ajustes rápidos desde tarjetas de productos
   - Seguimiento en dashboard de inventario

3. **Gestión avanzada**:
   - Ajustes con motivos específicos
   - Revisión de historial de movimientos
   - Reportes de inventario

#### Beneficios del Sistema

- **Control preciso** del inventario sin complejidad excesiva
- **Alertas proactivas** evitan quiebres de stock
- **Trazabilidad completa** para auditorías
- **Interfaz intuitiva** sin curva de aprendizaje
- **Integración transparente** con flujo de trabajo existente
- **Flexibilidad total** - usar solo en productos necesarios


## 📊 Módulos del Sistema

### 🏠 Dashboard
- **Estadísticas en tiempo real**
- **Órdenes recientes** de todos los tipos
- **Estado de mesas** visual
- **Notificaciones automáticas**
- **Accesos rápidos** según el rol

### 📋 Gestión de Órdenes
- **Órdenes tradicionales**: Mesa, delivery, retiro
- **Pedidos online**: Integración completa
- **Estados de orden**: Pendiente → Confirmado → Preparando → Listo → Entregado
- **Pagos**: Múltiples métodos (efectivo, tarjeta, transferencia, QR)
- **Filtros avanzados** por fecha, estado, tipo

### 🌐 Pedidos Online
- **Sistema completo** de pedidos por internet
- **Carrito de compras** con validación
- **Autocompletado de direcciones** con Google Maps
- **Verificación de zona** de delivery
- **Formateo automático** de teléfonos argentinos
- **Confirmación por WhatsApp**
- **Estados en tiempo real**
- **Panel de gestión** dedicado con:
  - Aceptación/rechazo de pedidos
  - Tiempos estimados de preparación
  - Seguimiento completo del proceso
  - Integración con WhatsApp automático
  - Sistema de pagos integrado

### 🍽️ Gestión de Mesas
- **Vista visual** de todas las mesas
- **Estados**: Libre, ocupada, reservada, mantenimiento
- **Capacidad** y ubicación
- **Asignación automática** de órdenes
- **Representación gráfica** con sillas según capacidad
- **Acciones rápidas** desde cada mesa

### 👨‍🍳 Panel de Cocina
- **Órdenes por preparar** en tiempo real
- **Tiempos de preparación**
- **Estados por item**
- **Priorización automática**
- **Actualización en vivo**

### 🏍️ Gestión de Delivery
- **Órdenes listas** para entrega
- **Información del cliente** completa
- **Direcciones con mapas**
- **Tiempos de entrega**
- **Estado de entrega**

### 🖨️ Sistema de Impresión
- **Tickets de venta** personalizables
- **Impresión automática** opcional
- **Formatos múltiples** (58mm, 80mm)
- **Vista previa** antes de imprimir
- **Información completa** del pedido y pagos

### 📊 Reportes Avanzados
- **Ventas diarias** con gráficos
- **Productos más vendidos**
- **Rendimiento del personal**
- **Análisis de mesas**
- **Métodos de pago**
- **Comparación de períodos**
- **Exportación a Excel/CSV**

### 📱 Menú QR
- **Código QR** para cada mesa
- **Menú digital** responsive
- **Filtros por categoría**
- **Llamada al mesero** integrada
- **Sin instalación** de apps

### 👥 Gestión de Usuarios
- **Roles y permisos** granulares
- **Interfaz responsive** optimizada para móvil
- **Vista de tarjetas** en dispositivos móviles
- **Filtros por rol** y estado
- **Gestión de contraseñas**
- **Activación/desactivación** de usuarios
- **Interfaz táctil** optimizada

### 👤 Perfil de Usuario

Sistema completo de gestión de perfiles personales para todos los usuarios del sistema.

#### Características del Perfil

- **Información personal completa**:
  - Edición de nombre completo
  - Actualización de email con validación
  - Gestión de número de teléfono
  - Visualización del rol asignado

- **Sistema de avatars avanzado**:
  - Subida de imágenes de perfil (JPG, PNG, GIF)
  - Límite de 2MB por archivo
  - Generación automática de iniciales si no hay avatar
  - Vista previa antes de subir
  - Eliminación automática de avatars anteriores

- **Cambio de contraseña seguro**:
  - Verificación de contraseña actual
  - Indicador visual de fortaleza de contraseña
  - Validación de coincidencia en tiempo real
  - Requisito mínimo de 6 caracteres
  - Opción de mostrar/ocultar contraseñas

- **Estadísticas personales**:
  - Fecha de registro en el sistema
  - Días activo en la plataforma
  - Último acceso registrado
  - Estado actual de la cuenta

#### Funcionalidades Técnicas

- **Validación en tiempo real** con JavaScript
- **Compatibilidad automática** con base de datos existente
- **Creación automática** de columnas `avatar` y `last_login` si no existen
- **Interfaz responsive** optimizada para dispositivos móviles
- **Integración completa** con sistema de temas dinámico
- **Gestión segura** de archivos subidos
- **Validaciones robustas** del lado servidor y cliente

#### Seguridad Implementada

- **Verificación de contraseña actual** antes de cambios
- **Validación de formato** de emails
- **Verificación de unicidad** de emails
- **Límites de tamaño** y tipo de archivos
- **Sanitización** de datos de entrada
- **Protección contra** sobrescritura de archivos

#### Interfaz de Usuario

- **Diseño moderno** con gradientes y efectos visuales
- **Animaciones suaves** para mejor experiencia
- **Feedback visual inmediato** en formularios
- **Indicadores de estado** para todas las acciones
- **Responsividad completa** para móviles y tablets
- **Accesibilidad mejorada** con labels y ARIA

Este módulo proporciona a cada usuario control total sobre su información personal y configuración de cuenta, manteniendo la seguridad y consistencia del sistema.

### ⚙️ Configuración Avanzada
- **Configuración general** del restaurante
- **Configuración de negocio** (impuestos, delivery)
- **Configuración de pedidos online**
- **Horarios de atención**
- **Integración con Google Maps**
- **Configuraciones del sistema**
- **Pruebas de configuración** integradas

## 📞 Sistema de Llamadas de Mesero

### Funcionalidades
- **Llamada desde código QR** de mesa
- **Notificaciones en tiempo real** al personal
- **Estado de llamadas** (pendiente/atendida)
- **Histórico de llamadas**
- **Integración con panel de mesas**

### Archivos del Sistema
- `call_waiter.php`: API para generar llamadas
- `attend_call.php`: Marcar llamadas como atendidas
- `check_calls.php`: Verificar llamadas pendientes

## 🔒 Seguridad

### Medidas Implementadas
- **Autenticación** con hash seguro de contraseñas
- **Autorización** basada en roles y permisos
- **Protección CSRF** en formularios
- **Validación de datos** en servidor y cliente
- **Escape de HTML** para prevenir XSS
- **Sesiones seguras** con configuración httponly
- **Validación de archivos** subidos

### Recomendaciones
- **Cambiar contraseñas** predefinidas
- **Usar HTTPS** en producción
- **Backup regular** de la base de datos
- **Actualizar** PHP y MySQL regularmente
- **Monitorear logs** de acceso

## 🎨 Personalización

### Temas y Estilos
- **Variables CSS** para colores principales
- **Responsive design** para todos los dispositivos
- **Iconos personalizables** con Font Awesome
- **Animaciones suaves** para mejor UX
- **Interfaz optimizada** para dispositivos táctiles

### 🎨 Sistema de Gestión de Estilos Dinámicos

El sistema incluye un potente módulo de personalización de temas que permite modificar la apariencia visual de toda la aplicación en tiempo real.

#### Características del Sistema de Temas

- **Editor visual de colores** con color pickers interactivos
- **Vista previa en tiempo real** de los cambios
- **Temas predefinidos** profesionales (Predeterminado, Oscuro, Verde, Morado, Azul, Naranja)
- **Generador automático de paletas de colores**:
  - Colores aleatorios
  - Colores complementarios  
  - Colores análogos
- **Configuración de tipografía** con preview en vivo
- **Personalización de layout** (bordes, espaciado, sidebar)
- **Sistema de importación/exportación** de temas
- **Backup automático** de configuraciones
- **Validación de integridad** del tema
- **CSS dinámico** generado automáticamente


#### Uso del Sistema de Temas

1. **Acceder al configurador**: Admin > Configuración > Tema
2. **Personalizar colores**: 
   - Colores principales (primario, secundario, acento)
   - Colores de estado (éxito, advertencia, peligro, información)
   - Vista previa instantánea de cambios
3. **Configurar tipografía**:
   - Selección de fuentes (Segoe UI, Inter, Roboto, Open Sans, Montserrat, Poppins)
   - Tamaños de fuente (base, pequeño, grande)
   - Preview en tiempo real
4. **Ajustar diseño**:
   - Radio de bordes (angular, normal, redondeado)
   - Ancho del sidebar
   - Intensidad de sombras
5. **Aplicar temas predefinidos** con un solo clic
6. **Generar paletas automáticas**:
   - Colores aleatorios para inspiración
   - Colores complementarios para alto contraste
   - Colores análogos para armonía visual

#### Herramientas Avanzadas

- **Exportar tema**: Descarga configuración actual en formato JSON
- **Importar tema**: Carga temas previamente exportados
- **Restablecer**: Vuelve a la configuración predeterminada
- **Regenerar CSS**: Actualiza archivos CSS dinámicos
- **Crear backup**: Respaldo de seguridad de la configuración
- **Validar tema**: Verifica integridad de colores y configuraciones

#### Características Técnicas

- **CSS Variables**: Uso de variables CSS para cambios en tiempo real
- **Responsive design**: Todos los temas se adaptan a dispositivos móviles
- **Validación robusta**: Verificación de colores hexadecimales y medidas CSS
- **Cache inteligente**: Optimización de rendimiento
- **Fallback automático**: CSS de emergencia si hay errores
- **Compatibilidad total**: Funciona con todos los módulos del sistema

#### Beneficios

- **Branding personalizado**: Adapta el sistema a la identidad visual del restaurante
- **Mejor experiencia de usuario**: Interface más atractiva y profesional
- **Facilidad de uso**: Sin conocimientos técnicos requeridos
- **Flexibilidad total**: Desde cambios sutiles hasta transformaciones completas
- **Consistencia visual**: Todos los módulos mantienen el tema seleccionado
- 

### Funcionalidades Adicionales
El sistema es extensible para agregar:
- **Reservas online**
- **Programa de fidelización**
- **Integración con redes sociales**
- **Sistemas de pago online**
- **Facturación electrónica**
- **Múltiples sucursales**
 

### 📱 Sistema de WhatsApp Business API

El sistema incluye integración completa con WhatsApp Business API para comunicación automática con clientes y gestión de conversaciones avanzadas.

#### Características del Sistema WhatsApp

- **API de WhatsApp Business** completamente integrada
- **Envío automático** de notificaciones de pedidos (confirmación, preparación, listo, entregado)
- **Webhook automático** para recibir mensajes entrantes con configuración segura
- **Respuestas automáticas configurables** desde panel web con variables dinámicas
- **Panel de gestión de conversaciones** con interface tipo chat
- **Sistema de prioridades** y tipos de coincidencia para respuestas
- **Rate limiting** y detección de duplicados
- **Logs completos** de envíos y recepciones
- **Configuración dinámica** del restaurante
- **Limpieza automática** de números telefónicos argentinos
- **Sistema de fallback** a WhatsApp Web si falla la API
- **Guardado de conversaciones completas** para seguimiento

#### Funcionalidades de Mensajería

**Envío Automático:**
- ✅ **Confirmaciones automáticas** de pedidos online al aceptar
- ✅ **Actualizaciones de estado** en tiempo real (preparando, listo)
- ✅ **Notificaciones de entrega** automáticas
- ✅ **Mensajes de rechazo** con motivo especificado
- ✅ **Guardado automático** en conversaciones para seguimiento
- ✅ **Fallback inteligente** a WhatsApp Web si la API falla

**Sistema de Respuestas Automáticas Avanzado:**
- **Editor web de respuestas** con variables dinámicas
- **Tipos de coincidencia**: Contiene, exacto, empieza con, termina con
- **Sistema de prioridades** (mayor número = mayor prioridad)
- **Variables automáticas**: `{restaurant_name}`, `{restaurant_web}`, `{restaurant_phone}`, etc.
- **Estadísticas de uso** para cada respuesta
- **Activación/desactivación** individual
- **Contador de usos** y fechas de creación/actualización

**Ejemplos de respuestas configurables:**

| Palabras Clave | Respuesta | Tipo |
|----------------|-----------|------|
| hola,saludos,buenos | ¡Hola! Gracias por contactar a {restaurant_name}. Para pedidos: {restaurant_web} | Contiene |
| menu,menú,carta | Vea nuestro menú completo en {restaurant_web} | Contiene |
| horario,horarios | Horarios: {opening_time} - {closing_time} | Contiene |
| estado,pedido | Para consultar estado, proporcione número de orden | Contiene |
| direccion,ubicacion | Nuestra dirección: {restaurant_address} | Contiene |

#### Panel de Gestión de Conversaciones

- **Vista unificada** de todas las conversaciones por contacto
- **Interface tipo chat** con burbujas de mensajes cronológicas
- **Identificación visual** de conversaciones nuevas/no leídas
- **Respuestas manuales** desde el panel con guardado automático
- **Marcado automático** como leído
- **Filtros avanzados** por teléfono, fecha, estado de lectura
- **Estadísticas en tiempo real** de mensajes y conversaciones
- **Enlaces directos** a WhatsApp Web
- **Auto-expansión** de conversaciones nuevas
- **Auto-refresh** cada 30 segundos

#### Panel de Configuración de Respuestas Automáticas

- **Editor visual** con formularios intuitivos
- **Gestión completa** de palabras clave y respuestas
- **Variables dinámicas** con reemplazo automático:
  - `{restaurant_name}` - Nombre del restaurante
  - `{restaurant_web}` - Sitio web
  - `{restaurant_phone}` - Teléfono
  - `{restaurant_email}` - Email
  - `{restaurant_address}` - Dirección
  - `{opening_time}` / `{closing_time}` - Horarios
  - `{delivery_fee}` - Costo de envío
  - `{min_delivery_amount}` - Monto mínimo delivery
  - `{order_number}` / `{order_status}` - Info de pedidos
- **Vista previa** de respuestas en tiempo real
- **Estadísticas de uso** por respuesta
- **Sistema de backup** y exportación

#### Configuración y Seguridad

**Configuración en Meta for Developers:**
```
Callback URL: https://tu-dominio.com/admin/whatsapp-webhook.php
Verify Token: Configurable desde el panel (sin hardcodear)
Webhook Fields: messages, messaging_postbacks, message_deliveries
```

**Credenciales seguras:**
- Access Token de WhatsApp Business API (almacenado en BD)
- Phone Number ID del número WhatsApp Business
- Webhook Token para verificación (configurable)
- **Sin credenciales hardcodeadas** en el código

**Funciones de prueba integradas:**
- ✅ Prueba de envío de mensajes
- ✅ Verificación de webhook
- ✅ Validación de configuración
- ✅ Logs detallados de errores

#### Características Técnicas Mejoradas

- **Configuración centralizada** usando `config/config.php` y `config/database.php`
- **Limpieza automática** de números telefónicos argentinos (formato 549XXXXXXXXX)
- **Detección automática** de pedidos relacionados por teléfono
- **Rate limiting** (máximo 1 respuesta automática por minuto por número)
- **Detección de duplicados** para evitar mensajes repetidos
- **Almacenamiento seguro** de mensajes y logs en base de datos
- **Manejo de errores** robusto con fallbacks
- **Webhook seguro** con validación de origen
- **API REST** para integración con otros sistemas
- **Creación automática** de tablas si no existen

#### Archivos del Sistema WhatsApp

```
admin/
├── whatsapp-settings.php     # Configuración de WhatsApp Business API
├── whatsapp-messages.php     # Panel de gestión de conversaciones  
├── whatsapp-answers.php      # Configuración de respuestas automáticas
└── whatsapp-webhook.php      # Webhook para recibir mensajes

config/
└── whatsapp_api.php         # Clase de integración con WhatsApp Business API
```

#### Variables de Configuración

```php
// Configuración en la base de datos
'whatsapp_enabled' => '1'                    // Habilitar envío automático
'whatsapp_fallback_enabled' => '1'          // Fallback a WhatsApp Web
'whatsapp_auto_responses' => '1'             // Respuestas automáticas
'whatsapp_access_token' => 'EAAxxxxxxxxx'    // Token de Meta
'whatsapp_phone_number_id' => '123456789'    // ID del número de WhatsApp
'whatsapp_webhook_token' => 'mi-token-123'   // Token del webhook
```



## 🛠 Solución de Problemas

### Problemas Comunes

1. **Error de conexión a base de datos**:
   - Verificar credenciales en `config/config.php`
   - Comprobar que el servidor MySQL esté activo

2. **No aparecen imágenes**:
   - Verificar permisos de carpeta `uploads/`
   - Comprobar rutas en la base de datos

3. **Notificaciones no funcionan**:
   - Verificar configuración de JavaScript
   - Comprobar permisos del navegador

4. **Google Maps no funciona**:
   - Verificar API Key válida
   - Comprobar APIs habilitadas en Google Console

5. **Pedidos online no funcionan**:
   - Verificar configuración en Admin > Configuración
   - Comprobar horarios de atención
   - Verificar conexión a base de datos

### Logs y Depuración
- **Logs de errores**: Activar error_log en PHP
- **Console del navegador**: Para errores de JavaScript
- **Network tab**: Para problemas de APIs

## 📞 Soporte

### Archivos de Configuración Importantes
- `config/config.php`: Configuración principal
- `admin/api/`: APIs del sistema
- `database/comidasm.sql`: Estructura de base de datos

### Información del Sistema
- **Versión**: 1.0.0
- **Licencia**: MIT
- **PHP mínimo**: 8.0
- **MySQL mínimo**: 8.0

### Contacto y Desarrollo
- **Desarrollador**: Cellcom Technology  
- **Sitio Web**: [www.cellcomweb.com.ar](http://www.cellcomweb.com.ar)  
- **Teléfono / WhatsApp**: +54 3482 549555  
- **Dirección**: Calle 9 N° 539, Avellaneda, Santa Fe, Argentina  
- **Soporte Técnico**: Disponible vía WhatsApp y web

## 🚀 Puesta en Producción

### Lista de Verificación

- [ ] Cambiar todas las contraseñas predefinidas
- [ ] Configurar datos reales del restaurante
- [ ] Subir imágenes de productos
- [ ] Configurar Google Maps API (opcional)
- [ ] Probar pedidos online completos
- [ ] Verificar horarios de atención
- [ ] Configurar métodos de pago
- [ ] Probar notificaciones
- [ ] Backup de base de datos
- [ ] Certificado SSL configurado
- [ ] Probar sistema de llamadas de mesero
- [ ] Verificar impresión de tickets
- [ ] Configurar usuarios del personal
- [ ] Configurar control de stock en productos necesarios
- [ ] Establecer límites de alerta de inventario
- [ ] Verificar funcionamiento de ajustes de stock
- [ ] Ejecutar instalador completo desde `/install/`
- [ ] Configurar control de stock en productos necesarios
- [ ] Establecer límites de alerta de inventario
- [ ] Verificar funcionamiento de ajustes de stock
- [ ] **Eliminar carpeta `install/`** por seguridad

### Variables de Entorno Recomendadas

```php
// Producción
define('DEBUG_MODE', false);
define('ENVIRONMENT', 'production');

// Desarrollo
define('DEBUG_MODE', true);
define('ENVIRONMENT', 'development');
```

## 📋 Changelog

### Versión 2.1.0
- Sistema completo de gestión de restaurante
- Pedidos online integrados con panel dedicado
- Panel de administración responsive
- Reportes con gráficos avanzados
- Sistema de roles y permisos granular
- Notificaciones en tiempo real
- Menú QR para mesas
- Integración con Google Maps
- Sistema de llamadas de mesero
- Gestión completa de usuarios con interfaz móvil
- Sistema de impresión de tickets personalizable
- Configuración avanzada del sistema
- Interfaz optimizada para dispositivos táctiles
- Instalador automático modular con verificación de requisitos
- Sistema completo de control de stock e inventario
- Tabla `stock_movements` para historial de movimientos
- Integración de WhatsApp Business API en instalación
- Configuración automática de temas y respuestas automáticas


### Próximas Versiones
- **v2.1.1** (En desarrollo):
  - Integración completa con Mercado Pago API
  - Sistema de backup automático de base de datos
  - Mejoras en la interfaz de pagos
  - Panel de gestión de transacciones
  - 
---

**¡Bienvenido al futuro de la gestión de restaurantes!** 🍽️

Para soporte adicional o consultas, revise la documentación técnica en los comentarios del código fuente.# mi_restaurant_delivery
