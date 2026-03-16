<?php
// includes/functions.php

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Función para obtener la conexión a la base de datos (opcional, si la necesitas)
function getDB() {
    try {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        // Log the error for the developer
        error_log("Database Connection Error: " . $e->getMessage());
        
        // Return 500 and JSON error
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Error de conexión a la base de datos. Por favor verifica que el servidor MySQL esté activo.']);
        exit;
    }
}
?>