<?php
/**
 * Plantillas base para las pantallas autenticadas.
 * Centraliza el <head>, la barra de navegación y el cierre del documento
 * para evitar duplicación en index.php, usuarios.php y logs.php.
 */
require_once __DIR__ . '/auth.php';

/**
 * Imprime el inicio del documento HTML y la barra de navegación.
 *
 * @param string $title     Título de la página.
 * @param string $activeNav Sección activa: dashboard|usuarios|logs.
 */
function renderPageStart(string $title, string $activeNav = 'dashboard'): void
{
    $cssVersion = file_exists(__DIR__ . '/../assets/css/style.css')
        ? filemtime(__DIR__ . '/../assets/css/style.css')
        : time();

    $dashboardClass = ($activeNav === 'dashboard') ? 'active' : '';
    $usersClass     = ($activeNav === 'usuarios') ? 'active' : '';
    $logsClass      = ($activeNav === 'logs') ? 'active' : '';

    $faCdn = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
    $fontCdn = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap';

    echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <link rel="icon" type="image/x-icon" href="assets/img/favicon.ico">
    <link rel="stylesheet" href="assets/css/style.css?v={$cssVersion}">
    <link rel="stylesheet" href="{$faCdn}">
    <link href="{$fontCdn}" rel="stylesheet">
</head>
<body>
    <div class="bg-blobs">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
        <div class="blob blob-3"></div>
        <div class="blob blob-4"></div>
    </div>
    <nav class="navbar-modern">
        <div class="nav-container-modern">
            <div class="nav-left-pill">
                <div class="logo-container">
                    <img src="assets/img/logo-tesa.png" alt="Logo TESA">
                </div>
                <span class="brand-text">TESA Zoom Monitor</span>
                <span class="admin-badge">👑 {$_SESSION['rol']}</span>
            </div>
            <div class="hamburger-menu" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
            <div class="nav-right-menu" id="navMenu">
                <a href="index.php" class="nav-btn {$dashboardClass}">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a href="usuarios.php" class="nav-btn {$usersClass}">
                    <i class="fas fa-users"></i> Usuarios
                </a>
                <a href="logs.php" class="nav-btn {$logsClass}">
                    <i class="fas fa-history"></i> Logs
                </a>
                <div class="nav-divider"></div>
                <a href="logout.php" class="nav-btn btn-logout"><i class="fas fa-sign-out-alt"></i> Salir</a>
            </div>
        </div>
    </nav>
HTML;
}

/**
 * Imprime scripts comunes y cierra el documento.
 *
 * @param array<string> $extraScripts Rutas adicionales a cargar después del base.js
 */
function renderPageEnd(array $extraScripts = []): void
{
    $baseJsVersion = file_exists(__DIR__ . '/../assets/js/base.js')
        ? filemtime(__DIR__ . '/../assets/js/base.js')
        : time();

    echo '<script src="assets/js/base.js?v=' . $baseJsVersion . '"></script>';

    foreach ($extraScripts as $script) {
        echo '<script src="' . $script . '"></script>';
    }

    echo "\n</body>\n</html>";
}
