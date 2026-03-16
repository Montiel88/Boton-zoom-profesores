<?php
// api/get_profesores.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zoom_api.php';
require_once __DIR__ . '/../config/config.php';
requireLogin();

header('Content-Type: application/json');

$carrera_id = $_GET['carrera_id'] ?? 0;
if (!$carrera_id) {
    echo json_encode(['error' => 'Falta carrera_id']);
    exit;
}

// Obtener emails de profesores de esa carrera desde la BD
$db = getDB();
$stmt = $db->prepare("SELECT profesor_email FROM profesores_carreras WHERE carrera_id = ?");
$stmt->execute([$carrera_id]);
$emails = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($emails)) {
    echo json_encode(['profesores' => []]);
    exit;
}

// Obtener todos los usuarios de Zoom
$users = getZoomUsers();
if (isset($users['error'])) {
    echo json_encode(['error' => $users['error']]);
    exit;
}

// Filtrar por email
$profesores = array_filter($users, function($user) use ($emails) {
    return in_array($user['email'], $emails);
});

// Reindexar array
$profesores = array_values($profesores);

echo json_encode(['profesores' => $profesores]);
?>