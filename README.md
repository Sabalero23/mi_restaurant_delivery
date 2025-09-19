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
- **Configuración del sistema** centralizada

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
│ └── theme.php # Clase ThemeManager con toda la lógica
│
├── admin/ # Panel de administración
│ ├── api/ # APIs internas para el frontend
│ │ ├── products.php # API de gestión de productos
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
│ ├── order-create.php # Crear o editar órdenes
│ ├── logout.php # Cerrar sesión
│ ├── order-details.php # Detalle de una orden
│ ├── print-order.php # Impresión de órdenes
│ ├── theme-settings.php # Panel principal de configuración de temas
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

- **PHP**: 8.0 o superior
- **MySQL**: 8.0 o superior
- **Apache/Nginx**: Servidor web
- **Extensiones PHP**:
  - PDO
  - PDO_MySQL
  - GD (para imágenes)
  - JSON
  - Session

### Instalación Automática

1. **Clonar o descargar** el proyecto en su servidor web (Solicitar Base de datos)
2. **Crear base de datos** MySQL vacía
3. **Navegar** a `http://su-dominio.com/install.php`
4. **Seguir el asistente** de instalación paso a paso:
   - Configurar conexión a base de datos
   - Crear estructura y datos iniciales
   - Configurar datos del restaurante
   - Crear usuario administrador

### Instalación Manual

Si prefiere instalar manualmente:

1. **Configurar base de datos**:
   ```sql
   CREATE DATABASE comidasm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

2. **Importar estructura**:
   ```bash
   mysql -u usuario -p comidasm < database/comidasm.sql
   ```

3. **Configurar archivo de configuración**:
   ```php
   // config/config.php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'comidasm');
   define('DB_USER', 'tu_usuario');
   define('DB_PASS', 'tu_contraseña');
   ```

4. **Crear carpetas de permisos**:
   ```bash
   chmod 755 uploads/
   chmod 755 admin/uploads/
   ```

## 👥 Usuarios Predefinidos

El sistema incluye usuarios de ejemplo para cada rol:

| Usuario | Contraseña | Rol | Permisos |
|---------|------------|-----|----------|
| admin | password | Administrador | Acceso completo |
| gerente | password | Gerente | Gestión completa excepto configuración |
| mostrador | password | Mostrador | Órdenes, mesas, cocina, delivery |
| mesero | password | Mesero | Órdenes y mesas |
| cocina | password | Cocina | Panel de cocina |
| delivery | password | Delivery | Gestión de entregas |

**⚠️ IMPORTANTE**: Cambiar todas las contraseñas después de la instalación.

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

## 📈 Optimización

### Rendimiento
- **Consultas SQL optimizadas** con índices apropiados
- **Caching** de configuraciones
- **Lazy loading** de imágenes
- **Minificación** de assets
- **Compresión** de respuestas

### Escalabilidad
- **Arquitectura modular**
- **APIs REST** para integración
- **Base de datos normalizada**
- **Código reutilizable**

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