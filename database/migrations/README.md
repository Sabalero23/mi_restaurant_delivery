# 📁 Carpeta de Migraciones SQL

Esta carpeta contiene los archivos de migración de base de datos que se ejecutan automáticamente durante las actualizaciones del sistema.

---

## 📝 FORMATO DE NOMBRES

Los archivos **DEBEN** seguir este formato:

```
vX.X.X.sql

Ejemplos:
✅ v2.2.0.sql
✅ v2.3.0.sql
✅ v2.3.1.sql
✅ v3.0.0.sql

❌ version-2.2.0.sql
❌ 2.2.0.sql
❌ v2.2.sql
❌ migration_2_2_0.sql
```

---

## 🎯 ORDEN DE EJECUCIÓN

Las migraciones se ejecutan en **orden de versión**:

```
v2.2.0.sql → v2.2.1.sql → v2.3.0.sql → v3.0.0.sql
```

El sistema **detecta automáticamente** qué migraciones faltan y las ejecuta en orden.

---

## 📋 TEMPLATE BÁSICO

Copiar y pegar para crear nuevas migraciones:

```sql
-- =============================================
-- Migración a versión X.X.X
-- Fecha: YYYY-MM-DD
-- Autor: Tu Nombre
-- Descripción: Breve descripción de los cambios
-- =============================================

START TRANSACTION;

-- ====================================
-- CAMBIOS DE BASE DE DATOS
-- ====================================

-- Ejemplo: Crear tabla
CREATE TABLE IF NOT EXISTS `nombre_tabla` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `campo` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ejemplo: Agregar columna
ALTER TABLE `tabla_existente` 
ADD COLUMN IF NOT EXISTS `nueva_columna` varchar(100) DEFAULT NULL;

-- Ejemplo: Insertar configuración
INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES ('nueva_config', 'valor', 'Descripción')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

-- ====================================
-- ACTUALIZAR VERSIÓN DEL SISTEMA
-- (SIEMPRE AL FINAL)
-- ====================================

UPDATE `settings` 
SET `setting_value` = 'X.X.X' 
WHERE `setting_key` = 'current_system_version';

COMMIT;

-- =============================================
-- Fin de migración vX.X.X
-- =============================================
```

---

## ⚠️ REGLAS IMPORTANTES

### ✅ SIEMPRE:

1. Usar `START TRANSACTION;` al inicio
2. Usar `COMMIT;` al final
3. Actualizar la versión del sistema
4. Usar `IF NOT EXISTS` al crear tablas/columnas
5. Testear en desarrollo antes de subir

### ❌ NUNCA:

1. Modificar migraciones ya ejecutadas
2. Usar nombres de archivo incorrectos
3. Olvidar la transacción
4. Hacer cambios destructivos sin planificar
5. Duplicar números de versión

---

## 📊 VERSIONADO SEMÁNTICO

```
vMAYOR.MENOR.PARCHE

MAYOR (v2 → v3):
  - Cambios incompatibles
  - Reestructuración importante
  
MENOR (v2.2 → v2.3):
  - Nuevas funcionalidades
  - Compatibles con versión anterior
  
PARCHE (v2.3.0 → v2.3.1):
  - Corrección de bugs
  - Mejoras menores
```

**Ejemplos:**
- `v2.2.0` → Versión inicial
- `v2.3.0` → Nueva funcionalidad (notificaciones)
- `v2.3.1` → Bug fix o mejora menor
- `v3.0.0` → Cambio mayor de arquitectura

---

## 🔄 ¿CÓMO FUNCIONA?

1. **Desarrollador** crea `v2.3.0.sql` y sube a GitHub
2. **Cliente** actualiza desde el panel
3. **Sistema** detecta la migración nueva
4. **Sistema** la ejecuta automáticamente
5. **Sistema** registra en tabla `migrations`
6. **Cliente** ve: "1 migración ejecutada correctamente"

---

## 📁 ARCHIVOS ACTUALES

| Archivo | Versión | Estado | Descripción |
|---------|---------|--------|-------------|
| v2.2.0.sql | 2.2.0 | ✅ Ejemplo | Sistema de licencias |
| v2.2.1.sql | 2.2.1 | 📝 Ejemplo | Template de ejemplo |

---

## 💡 EJEMPLOS COMUNES

### Crear Tabla

```sql
START TRANSACTION;

CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario_id` int(11) NOT NULL,
  `mensaje` text NOT NULL,
  `leida` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

UPDATE `settings` SET `setting_value` = '2.3.0' 
WHERE `setting_key` = 'current_system_version';

COMMIT;
```

### Agregar Columna

```sql
START TRANSACTION;

ALTER TABLE `products` 
ADD COLUMN IF NOT EXISTS `destacado` tinyint(1) DEFAULT 0 
AFTER `active`;

UPDATE `settings` SET `setting_value` = '2.3.1' 
WHERE `setting_key` = 'current_system_version';

COMMIT;
```

### Insertar Datos

```sql
START TRANSACTION;

INSERT INTO `settings` (`setting_key`, `setting_value`, `description`) 
VALUES 
  ('nueva_feature', '1', 'Habilitar nueva funcionalidad'),
  ('otra_config', 'valor', 'Otra configuración')
ON DUPLICATE KEY UPDATE `description` = VALUES(`description`);

UPDATE `settings` SET `setting_value` = '2.3.2' 
WHERE `setting_key` = 'current_system_version';

COMMIT;
```

### Modificar Datos

```sql
START TRANSACTION;

-- Corregir datos incorrectos
UPDATE `orders` 
SET `status` = 'completed' 
WHERE `status` = 'completado';

-- Agregar constraint
ALTER TABLE `orders` 
MODIFY COLUMN `status` enum('pending','confirmed','completed','cancelled') 
NOT NULL DEFAULT 'pending';

UPDATE `settings` SET `setting_value` = '2.3.3' 
WHERE `setting_key` = 'current_system_version';

COMMIT;
```

---

## 🆘 SI ALGO FALLA

El sistema automáticamente:
- ✅ Hace **rollback** (revierte cambios)
- ✅ **Registra el error** en la tabla `migrations`
- ✅ **NO actualiza** la versión
- ✅ El sistema **queda estable**

Para solucionarlo:
1. Ver el error en el panel de migraciones
2. Corregir el SQL
3. Crear nueva versión (incrementar número)
4. Subir a GitHub
5. Cliente actualiza nuevamente

---

## 📞 MÁS INFORMACIÓN

Ver documentación completa en:
- `DOCUMENTACION_MIGRACIONES.md`
- `INSTRUCCIONES_LICENCIAS.md`

---

**Sistema de Migraciones v2.2.0**
**Desarrollado por:** Cellcom Technology
