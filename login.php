<?php
// login.php - Versión Split Screen Institucional
require_once 'includes/auth.php';

if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';

    if (login($correo, $password)) {
        redirect('index.php');
    } else {
        $error = 'Usuario o contraseña incorrectos';
    }
}

$loginCssVersion = file_exists(__DIR__ . '/assets/css/login.css') ? filemtime(__DIR__ . '/assets/css/login.css') : time();
$loginJsVersion = file_exists(__DIR__ . '/assets/js/login.js') ? filemtime(__DIR__ . '/assets/js/login.js') : time();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - TESA Zoom Monitor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css?v=<?php echo $loginCssVersion; ?>">
</head>
<body>
    <div class="bg-mesh"></div>
    <div class="particles" id="particle-container"></div>
    <div class="login-wrapper">
        <!-- Lado Izquierdo -->
        <div class="login-info-side">
            <h1>¡Bienvenido!</h1>
            <h2>TESA Zoom Monitor</h2>
            <p class="description">Sistema de gestión profesional para el control de auditoría y métricas de clases virtuales del Tecnológico San Antonio.</p>

            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-users-viewfinder"></i></div>
                <div class="feature-text">
                    <h4>Auditoría de Clases</h4>
                    <p>Control total de reuniones programadas y recurrentes.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                <div class="feature-text">
                    <h4>Métricas de Asistencia</h4>
                    <p>Seguimiento detallado de participantes en tiempo real.</p>
                </div>
            </div>

            <div class="feature-item">
                <div class="feature-icon"><i class="fas fa-file-video"></i></div>
                <div class="feature-text">
                    <h4>Historial de Sesiones</h4>
                    <p>Acceso rápido a clases pasadas y reportes de conexión.</p>
                </div>
            </div>
        </div>

        <!-- Lado Derecho -->
        <div class="login-form-side">
            <div class="logo-container">
                <div class="logo-placeholder">
                    <span style="color:#7b2cbf">TECNOLÓGICO</span> <span style="color:#FFD700">SAN ANTONIO</span>
                </div>
            </div>

            <div class="form-header">
                <h3>Iniciar Sesión</h3>
                <p>Ingresa tus credenciales para acceder</p>
            </div>

            <?php if ($error): ?>
                <div class="error-msg">
                    <i class="fas fa-circle-exclamation"></i> <?php echo e($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="input-group">
                    <label>Email Institucional</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope"></i>
                        <input type="email" name="correo" required placeholder="ejemplo@tesa.edu.ec" autofocus>
                    </div>
                </div>

                <div class="input-group">
                    <label>Contraseña</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    <i class="fas fa-right-to-bracket"></i> Ingresar al Sistema
                </button>
            </form>

            <div class="login-footer-links">
                <a href="#"><i class="fas fa-shield-halved"></i> ¿Problemas para acceder?</a>
                <a href="#"><i class="fas fa-circle-info"></i> Ayuda</a>
            </div>
        </div>
    </div>
    <script src="assets/js/login.js?v=<?php echo $loginJsVersion; ?>"></script>
</body>
</html>
