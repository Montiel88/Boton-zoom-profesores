<?php
// api/test_connection.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/zoom_api.php';

$profesores = getProfesores();
if (isset($profesores['users'])) {
    echo json_encode(['success' => true, 'user_count' => count($profesores['users'])]);
} else {
    echo json_encode(['success' => false, 'error' => $profesores['error'] ?? 'Error desconocido']);
}
?>
