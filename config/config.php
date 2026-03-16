<?php
// config.php

function loadEnv() {
    // Intentar primero con el .env en la raíz del proyecto
    $envFile = dirname(__DIR__) . '/.env';
    if (!file_exists($envFile)) {
        // Fallback para instalaciones que lo coloquen dentro de /config
        $envFile = __DIR__ . '/.env';
    }

    if (!file_exists($envFile)) {
        die('Error: Archivo .env no encontrado');
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            putenv("$name=$value");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}
loadEnv();

// Constantes de base de datos (acepta ambos formatos .env)
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: getenv('DB_DATABASE') ?: 'zoom_monitor');
define('DB_USER', getenv('DB_USER') ?: getenv('DB_USERNAME') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: getenv('DB_PASSWORD') ?: '');

// Credenciales de Zoom
define('ZOOM_ACCOUNT_ID', getenv('ZOOM_ACCOUNT_ID') ?: '');
define('ZOOM_CLIENT_ID', getenv('ZOOM_CLIENT_ID') ?: '');
define('ZOOM_CLIENT_SECRET', getenv('ZOOM_CLIENT_SECRET') ?: '');

// Validar credenciales de Zoom
if (empty(ZOOM_ACCOUNT_ID) || empty(ZOOM_CLIENT_ID) || empty(ZOOM_CLIENT_SECRET)) {
    die('Error: Credenciales de Zoom no configuradas. Verifica el archivo .env');
}

// Zona horaria
date_default_timezone_set('America/Guayaquil');

// Forzar ruta de sesiones dentro del proyecto para evitar problemas de permisos (especialmente en Windows/XAMPP)
$sessionPath = __DIR__ . '/../storage/sessions';
if (!is_dir($sessionPath)) {
    @mkdir($sessionPath, 0777, true);
}
if (is_dir($sessionPath) && is_writable($sessionPath)) {
    session_save_path($sessionPath);
}

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
