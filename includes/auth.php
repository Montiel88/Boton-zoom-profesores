<?php
// includes/auth.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

function login($correo, $password) {
    $db = getDB();
    $query = "SELECT * FROM usuarios WHERE correo = :correo AND activo = 1";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':correo', $correo);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['correo'] = $user['correo'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['nombre'] = $user['nombre_completo'];
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
    redirect('login.php');
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['rol'] !== 'ADMIN') {
        redirect('index.php');
    }
}
?>
