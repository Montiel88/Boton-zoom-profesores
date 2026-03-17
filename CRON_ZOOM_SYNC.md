# 🔄 CONFIGURACIÓN DE CRON JOB - ZOOM SYNC AUTOMÁTICO

**Fecha:** 2026-03-21  
**Estado:** ✅ IMPLEMENTADO  
**Plataforma:** Windows (XAMPP)

---

## 📋 ¿QUÉ HACE ESTO?

Un **CRON JOB** ejecuta automáticamente un script PHP que:

1. ✅ **Consulta la API de Zoom** (reuniones, participantes, grabaciones)
2. ✅ **Guarda en MySQL** (tablas cacheadas)
3. ✅ **Se ejecuta solo** (cada 6 horas automáticamente)
4. ✅ **App carga rápido** (usa datos de MySQL, no espera a Zoom)

---

## 🎯 BENEFICIOS

| Sin Caché | Con Caché |
|-----------|-----------|
| ❌ Lento (espera API Zoom) | ✅ Rápido (MySQL local) |
| ❌ Límite API Zoom | ✅ Sin límites |
| ❌ Solo 90 días grabaciones | ✅ Histórico ilimitado |
| ❌ Cada click llama a API | ✅ Datos precargados |

---

## 📁 ARCHIVOS CREADOS

| Archivo | Función |
|---------|---------|
| `scripts/setup_cache_tables.php` | Crea tablas en MySQL |
| `scripts/sync_zoom_data.php` | Sincroniza Zoom → MySQL |
| `api/get_professor_meetings_cached.php` | API rápida (usa caché) |
| `sync_zoom.bat` | Script para Windows Task |
| `logs/sync_log.txt` | Log de ejecuciones |

---

## 🚀 INSTALACIÓN PASO A PASO

### **Paso 1: Crear Tablas en MySQL**

```bash
cd C:\xampp\htdocs\zoom-monitor
php scripts/setup_cache_tables.php
```

**Resultado esperado:**
```
✅ Conectado a MySQL
✅ Tabla zoom_meetings_cache creada
✅ Tabla zoom_participants_cache creada
✅ Tabla zoom_recordings_cache creada
✅ Tabla zoom_users_cache creada
✅ Tabla zoom_sync_log creada
✅ Tabla zoom_cache_settings creada
✅ Configuración por defecto insertada
```

---

### **Paso 2: Ejecutar Primera Sincronización**

```bash
php scripts/sync_zoom_data.php
```

**Resultado esperado:**
```
===========================================
🔄 ZOOM DATA SYNC - 2026-03-21 12:00:00
===========================================

✅ Conectado a MySQL
📅 Rango: 2025-03-21 a 2026-03-21
📦 Retención: 365 días

🎯 Sincronizando TODOS los usuarios...
👥 Usuarios encontrados: 15

-------------------------------------------
📧 Usuario: aparedes@tesa.edu.ec
-------------------------------------------
  ✅ Usuario sincronizado
  📊 Obteniendo reuniones...
  ✅ Reuniones sincronizadas: 150
  🎥 Obteniendo grabaciones...
  ✅ Grabaciones sincronizadas: 120
  👥 Obteniendo participantes...
  ✅ Participantes sincronizados: 450
```

---

### **Paso 3: Configurar Windows Task Scheduler**

#### **3.1 Abrir Task Scheduler:**
1. Presiona `Win + R`
2. Escribe: `taskschd.msc`
3. Enter

#### **3.2 Crear Tarea Básica:**
1. Click derecho en **"Task Scheduler Library"**
2. **"Create Basic Task..."**
3. Nombre: `Zoom Monitor Sync`
4. Descripción: `Sincronizar datos de Zoom automáticamente`

#### **3.3 Configurar Trigger:**
- **Frequency:** Daily
- **Start:** Una hora (ej: 00:00:00)
- **Recur every:** 1 day

#### **3.4 Configurar Action:**
- **Action:** Start a program
- **Program/script:** `C:\xampp\php\php.exe`
- **Arguments:** `C:\xampp\htdocs\zoom-monitor\scripts\sync_zoom_data.php`
- **Start in:** `C:\xampp\htdocs\zoom-monitor`

#### **3.5 Configurar Avanzado:**
1. Click derecho en la tarea creada → **Properties**
2. **Triggers tab** → New:
   - **Begin the task:** On a schedule
   - **Settings:** Daily
   - **Advanced settings:**
     - ✅ Repeat task every: **6 hours**
     - ✅ for a duration of: **Indefinitely**
3. **Conditions tab:**
   - ❌ Desmarcar "Start only if computer is on AC power"
4. **Settings tab:**
   - ✅ Allow task to be run on demand
   - ✅ Run task as soon as possible after scheduled start is missed

---

### **Paso 4: Verificar que Funciona**

#### **Ejecutar Manualmente:**
```bash
cd C:\xampp\htdocs\zoom-monitor
php scripts/sync_zoom_data.php
```

#### **Ver Logs:**
```bash
type logs\sync_log.txt
```

#### **Ver en MySQL:**
```sql
-- Ver reuniones cacheadas
SELECT COUNT(*) FROM zoom_meetings_cache;

-- Ver última sincronización
SELECT * FROM zoom_sync_log ORDER BY id DESC LIMIT 5;

-- Ver configuración
SELECT * FROM zoom_cache_settings;
```

---

## 🔧 COMANDOS DISPONIBLES

### **Sincronizar TODO:**
```bash
php scripts/sync_zoom_data.php
```

### **Sincronizar usuario específico:**
```bash
php scripts/sync_zoom_data.php --user=aparedes@tesa.edu.ec
```

### **Sincronización completa (full):**
```bash
php scripts/sync_zoom_data.php --full
```

### **Ver logs en tiempo real:**
```bash
tail -f logs/sync_log.txt
```

---

## 📊 TABLAS DE BASE DE DATOS

### **zoom_meetings_cache**
- `meeting_id` - ID de reunión
- `uuid` - UUID único
- `user_id` - Profesor
- `topic` - Tema de clase
- `start_time` - Inicio
- `end_time` - Fin
- `duration_minutes` - Duración
- `participants_count` - Participantes
- `has_recording` - ¿Grabado?
- `raw_data` - JSON completo de Zoom

### **zoom_participants_cache**
- `meeting_id` - Reunión
- `name` - Nombre participante
- `email` - Email
- `join_time` - Hora ingreso
- `leave_time` - Hora salida
- `duration_seconds` - Duración

### **zoom_recordings_cache**
- `meeting_id` - Reunión grabada
- `recording_count` - Cantidad de archivos
- `total_size` - Tamaño total (bytes)
- `recording_files` - JSON con archivos

### **zoom_users_cache**
- `user_id` - ID Zoom
- `email` - Email institucional
- `display_name` - Nombre completo
- `status` - Activo/Inactivo

### **zoom_sync_log**
- `sync_type` - auto/manual
- `meetings_synced` - Cantidad
- `status` - success/error
- `duration_seconds` - Tiempo

---

## ⚙️ CONFIGURACIÓN

Editar en `zoom_cache_settings`:

| Setting | Valor | Descripción |
|---------|-------|-------------|
| `cache_ttl_meetings` | 3600 | Vida útil reuniones (1 hora) |
| `cache_ttl_participants` | 7200 | Vida útil participantes (2 horas) |
| `cache_ttl_recordings` | 3600 | Vida útil grabaciones (1 hora) |
| `cache_ttl_users` | 86400 | Vida útil usuarios (24 horas) |
| `sync_interval_hours` | 6 | Sincronizar cada 6 horas |
| `retention_days` | 365 | Días de histórico |
| `auto_sync_enabled` | 1 | Auto sync habilitado |

---

## 🔄 CAMBIAR API A CACHÉ

### **Opción 1: Usar API Caché (Rápido)**

En `main.js`, cambiar:
```javascript
// ANTES (lento, llama a Zoom)
const response = await fetch(`api/get_professor_meetings.php?userId=${userId}&from=${from}&to=${to}`);

// AHORA (rápido, usa MySQL)
const response = await fetch(`api/get_professor_meetings_cached.php?userId=${userId}&from=${from}&to=${to}`);
```

### **Opción 2: Híbrido (Recomendado)**

Mantener ambas:
- `get_professor_meetings.php` - Datos en tiempo real (Zoom API)
- `get_professor_meetings_cached.php` - Datos rápidos (MySQL)

El usuario puede elegir con un botón "🔄 Actualizar datos" para forzar sync.

---

## 📈 MONITOREO

### **Ver Estado de Caché:**

```sql
-- Cantidad de datos cacheados
SELECT 
    'Reuniones' as tipo, COUNT(*) as cantidad FROM zoom_meetings_cache
UNION ALL
SELECT 'Participantes', COUNT(*) FROM zoom_participants_cache
UNION ALL
SELECT 'Grabaciones', COUNT(*) FROM zoom_recordings_cache
UNION ALL
SELECT 'Usuarios', COUNT(*) FROM zoom_users_cache;
```

### **Ver Última Sincronización:**

```sql
SELECT 
    sync_type,
    started_at,
    completed_at,
    meetings_synced,
    participants_synced,
    recordings_synced,
    duration_seconds,
    status
FROM zoom_sync_log 
ORDER BY id DESC 
LIMIT 10;
```

### **Ver Errores:**

```sql
SELECT * FROM zoom_sync_log 
WHERE status = 'error' 
ORDER BY id DESC 
LIMIT 10;
```

---

## 🐛 SOLUCIÓN DE PROBLEMAS

### **Error: "PHP no se reconoce"**

Agregar PHP al PATH de Windows o usar ruta completa:
```bash
C:\xampp\php\php.exe scripts\sync_zoom_data.php
```

### **Error: "Access denied for user root"**

Verificar credenciales en `.env`:
```
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=zoom_monitor
DB_USER=root
DB_PASS=
```

### **Error: "Table doesn't exist"**

Ejecutar setup:
```bash
php scripts/setup_cache_tables.php
```

### **Task Scheduler no ejecuta**

1. Verificar permisos de usuario
2. Ejecutar como administrador
3. Verificar ruta de PHP
4. Revisar logs de Task Scheduler

---

## ✅ CHECKLIST DE INSTALACIÓN

- [ ] Ejecutar `setup_cache_tables.php`
- [ ] Ejecutar `sync_zoom_data.php` manualmente
- [ ] Verificar datos en MySQL
- [ ] Configurar Windows Task Scheduler
- [ ] Programar cada 6 horas
- [ ] Verificar logs después de 24 horas
- [ ] Cambiar API a versión caché (opcional)
- [ ] Monitorear primera semana

---

## 🎯 PRÓXIMOS PASOS

1. **Instalar caché** - Ejecutar setup
2. **Sincronizar datos** - Primera ejecución manual
3. **Configurar cron** - Windows Task Scheduler
4. **Monitorear** - Ver logs y MySQL
5. **Optimizar** - Ajustar intervalos si es necesario

---

**Estado:** ✅ **LISTO PARA INSTALAR**  
**Tiempo estimado:** 15 minutos  
**Dificultad:** Media
