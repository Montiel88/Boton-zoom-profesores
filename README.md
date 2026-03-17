# TESA Zoom Monitor 🎓

Sistema de monitoreo y consulta de reuniones de Zoom para el **Instituto Tecnológico San Antonio (TESA)**.

---

## 📋 Descripción

TESA Zoom Monitor es una plataforma web que permite:

- 🔍 **Buscar profesores** por nombre o correo electrónico
- 📊 **Visualizar estadísticas** de reuniones (pasadas, en vivo y programadas)
- 👥 **Consultar asistencia** detallada de cada clase
- 📹 **Verificar grabaciones** de las reuniones
- 📈 **Generar reportes** en formato Excel
- 🎯 **Dashboard en tiempo real** con métricas de Zoom

---

## 🏢 Institución

**Instituto Tecnológico San Antonio - TESA**  
📍 Quito, Ecuador  
🌐 [www.tesa.edu.ec](https://www.tesa.edu.ec)

---

## 👥 Desarrolladores

| Nombre | Rol | Contacto |
|--------|-----|----------|
| **Axel Palomino** | Desarrollador Principal | apalomino@estud.tesa.edu.ec |
| **Carlos Montiel** | Desarrollador | cmontiel@tesa.edu.ec |

---

## 📄 Licencia

© 2025 Instituto Tecnológico San Antonio (TESA). Todos los derechos reservados.

Este software fue desarrollado exclusivamente para uso interno del TESA.  
Queda prohibida su reproducción, distribución o uso sin autorización expresa de la institución.

---

## 🚀 Requisitos del Sistema

### Servidor

- **PHP:** 8.0 o superior
- **Web Server:** Apache 2.4+ / Nginx
- **Base de Datos:** MySQL 5.7+ / MariaDB 10.3+
- **Extensiones PHP:**
  - `curl`
  - `json`
  - `mbstring`
  - `openssl`
  - `pdo_mysql`
  - `session`

### Cliente

- Navegador web moderno (Chrome 90+, Firefox 88+, Edge 90+)
- JavaScript habilitado
- Conexión a internet para API de Zoom

---

## 📁 Estructura del Proyecto

```
zoom-monitor/
├── 📁 api/                          # Endpoints de la API
│   ├── get_professor_meetings.php   # Obtener reuniones de profesor
│   ├── search_professor.php         # Buscar profesores
│   └── webhook.php                  # Webhooks de Zoom
│
├── 📁 assets/                       # Recursos estáticos
│   ├── 📁 css/
│   │   └── style.css                # Estilos principales
│   └── 📁 js/
│       └── main.js                  # Lógica del frontend
│
├── 📁 config/                       # Configuración
│   └── config.php                   # Configuración principal
│
├── 📁 includes/                     # Librerías y utilidades
│   ├── auth.php                     # Autenticación y sesiones
│   ├── cache_manager.php            # Gestión de caché
│   ├── layout.php                   # Plantillas HTML
│   ├── logger.php                   # Sistema de logs
│   └── zoom_api.php                 # Cliente API de Zoom
│
├── 📁 scripts/                      # Scripts de mantenimiento
│   ├── setup_cache_tables.php       # Crear tablas de caché
│   └── sync_zoom_data.php           # Sincronización con Zoom
│
├── 📁 storage/                      # Almacenamiento temporal
│   ├── 📁 cache/                    # Caché de datos
│   └── 📁 sessions/                 # Sesiones PHP
│
├── 📁 vendor/                       # Dependencias (Composer)
│
├── .env                             # Variables de entorno (NO subir al repo)
├── .env.example                     # Ejemplo de variables de entorno
├── .gitignore                       # Archivos ignorados por Git
├── composer.json                    # Dependencias de PHP
├── index.php                        # Página principal (Dashboard)
├── monitor.php                      # Vista de monitoreo en vivo
└── README.md                        # Este archivo
```

---

## ⚙️ Instalación

### 1. Clonar el repositorio

```bash
git clone <url-del-repositorio>
cd zoom-monitor
```

### 2. Configurar variables de entorno

```bash
cp .env.example .env
```

Editar `.env` con las credenciales de Zoom:

```env
# Ver sección de Variables de Entorno más abajo
```

### 3. Instalar dependencias

```bash
composer install --no-dev --optimize-autoloader
```

### 4. Configurar permisos

```bash
# Linux/Mac
chmod -R 755 storage/
chmod -R 755 config/

# Windows (PowerShell)
icacls storage /grant Users:F /T
icacls config /grant Users:F /T
```

### 5. Configurar base de datos (si aplica)

Ejecutar el script de creación de tablas:

```bash
php scripts/setup_cache_tables.php
```

### 6. Configurar el servidor web

#### Apache

Asegúrate de que `.htaccess` esté habilitado:

```apache
<Directory "/path/to/zoom-monitor">
    AllowOverride All
    Require all granted
</Directory>
```

#### Nginx

```nginx
server {
    listen 80;
    server_name zoom.tesa.edu.ec;
    root /path/to/zoom-monitor;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Proteger archivos sensibles
    location ~ /\. {
        deny all;
    }

    location ~ /\.env {
        deny all;
    }
}
```

### 7. Verificar instalación

Acceder a `http://tu-dominio/zoom-monitor/` y verificar que:

- ✅ El dashboard carga correctamente
- ✅ Las estadísticas se muestran
- ✅ La búsqueda de profesores funciona

---

## 🔐 Variables de Entorno

Copiar `.env.example` a `.env` y configurar:

```env
# =============================================================================
# TESA ZOOM MONITOR - VARIABLES DE ENTORNO
# =============================================================================
# Instrucciones:
# 1. Copiar este archivo a .env
# 2. Reemplazar los valores con las credenciales reales
# 3. NO subir el archivo .env al repositorio Git
# =============================================================================

# -----------------------------------------------------------------------------
# ZOOM API CREDENTIALS
# Obtener en: https://marketplace.zoom.us/develop/
# -----------------------------------------------------------------------------

# Account ID de Zoom (Zoom Web → Admin → Account Management → Account Info)
ZOOM_ACCOUNT_ID=tu_account_id_aqui

# Client ID de Zoom App (Zoom Marketplace → Develop → App Credentials)
ZOOM_CLIENT_ID=tu_client_id_aqui

# Client Secret de Zoom App (Zoom Marketplace → Develop → App Credentials)
ZOOM_CLIENT_SECRET=tu_client_secret_aqui

# -----------------------------------------------------------------------------
# DATABASE CONFIGURATION (Opcional - para caché y sesiones)
# -----------------------------------------------------------------------------

DB_HOST=localhost
DB_PORT=3306
DB_NAME=zoom_monitor
DB_USER=root
DB_PASSWORD=tu_password_aqui

# -----------------------------------------------------------------------------
# APPLICATION SETTINGS
# -----------------------------------------------------------------------------

# URL base de la aplicación (sin slash al final)
APP_URL=http://localhost/zoom-monitor

# Zona horaria de la aplicación
APP_TIMEZONE=America/Guayaquil

# Modo debug (true para desarrollo, false para producción)
APP_DEBUG=false

# Clave de encriptación de sesiones (generar con: openssl rand -hex 32)
APP_KEY=

# -----------------------------------------------------------------------------
# SESSION CONFIGURATION
# -----------------------------------------------------------------------------

SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=false
SESSION_HTTP_ONLY=true

# -----------------------------------------------------------------------------
# CACHE CONFIGURATION
# -----------------------------------------------------------------------------

CACHE_DRIVER=file
CACHE_PREFIX=zoom_monitor

# -----------------------------------------------------------------------------
# LOG CONFIGURATION
# -----------------------------------------------------------------------------

LOG_LEVEL=error
LOG_PATH=storage/logs/

# -----------------------------------------------------------------------------
# SECURITY
# -----------------------------------------------------------------------------

# IPs permitidas para acceso (dejar vacío para permitir todas)
ALLOWED_IPS=

# Requerir autenticación (true para producción)
REQUIRE_AUTH=true
```

---

## 🔧 Configuración de Zoom App

### 1. Crear App en Zoom Marketplace

1. Ir a [Zoom Marketplace](https://marketplace.zoom.us/)
2. Click en **Develop** → **Build App**
3. Seleccionar **Server-to-Server OAuth**
4. Completar información de la app:
   - **App Name:** TESA Zoom Monitor
   - **Company:** Instituto Tecnológico San Antonio
   - **Desarrolladores:** Axel Palomino, Carlos Montiel

### 2. Configurar Scopes necesarios

```
meeting:read:admin
meeting:write:admin
user:read:admin
recording:read:admin
dashboard:read:admin
report:read:admin
```

### 3. Obtener credenciales

- **Account ID:** Admin → Account Management → Account Info
- **Client ID:** App Credentials → Client ID
- **Client Secret:** App Credentials → Client Secret

### 4. Activar la app

Ir a **Manage → Server-to-Server OAuth** y hacer click en **Activate**

---

## 📊 Uso del Sistema

### Dashboard Principal

- Muestra total de docentes TESA
- Clases en vivo actualmente
- Estadísticas por dominio de correo

### Búsqueda de Profesores

1. Ingresar nombre o correo del profesor
2. Click en **🚀 Buscar Ahora**
3. Ver resultados con estado y zona horaria

### Consulta Detallada

1. Click en **💎 Consultar Clases** en un profesor
2. Ver métricas:
   - ⏪ Clases Pasadas
   - 🟢 Clases En Vivo
   - ⏩ Clases Programadas
3. Filtrar por:
   - Rango de fechas
   - Módulo/Periodo
   - Nombre de clase
4. Acciones disponibles:
   - 👥 Ver lista de asistencia
   - 📊 Exportar reporte Excel

---

## 🛠️ Mantenimiento

### Limpieza de caché

```bash
php scripts/clear_cache.php
```

### Sincronización con Zoom

```bash
php scripts/sync_zoom_data.php
```

### Ver logs del sistema

```bash
# Linux/Mac
tail -f storage/logs/app.log

# Windows
Get-Content storage/logs/app.log -Tail 50 -Wait
```

---

## 🐛 Solución de Problemas

### Error: "Invalid Token"

- Verificar credenciales de Zoom en `.env`
- Reactivar la app en Zoom Marketplace
- Esperar 5 minutos y reintentar

### Error: "Permission Denied"

- Verificar permisos de carpetas `storage/` y `config/`
- En Linux: `chmod -R 755 storage/ config/`

### Error: "API Rate Limit"

- Zoom tiene límites de peticiones por hora
- Esperar unos minutos y reintentar
- Considerar aumentar caché en `config.php`

---

## 📞 Soporte

Para soporte técnico o reporte de errores:

- **Email:** soporte@tesa.edu.ec
- **Desarrolladores:**
  - Axel Palomino: apalomino@estud.tesa.edu.ec
  - Carlos Montiel: cmontiel@tesa.edu.ec

---

## 📝 Changelog

### Versión 1.0.0 (2025)

- ✅ Dashboard de estadísticas en tiempo real
- ✅ Búsqueda de profesores por nombre/email
- ✅ Consulta de clases pasadas, en vivo y programadas
- ✅ Lista de asistencia detallada
- ✅ Exportación a Excel
- ✅ Sistema de caché para mejorar rendimiento
- ✅ Autenticación y seguridad
- ✅ Interfaz moderna y responsiva

---

## 🙏 Agradecimientos

- **Instituto Tecnológico San Antonio (TESA)** - Por el apoyo institucional
- **Zoom Video Communications** - Por la API y documentación
- **Comunidad de desarrollo** - Por las librerías y herramientas utilizadas

---

<div align="center">

**TESA Zoom Monitor**  
*Desarrollado para el Instituto Tecnológico San Antonio*

© 2025 - Creado por **Axel Palomino** y **Carlos Montiel**

</div>
