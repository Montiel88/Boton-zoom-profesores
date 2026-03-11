<?php
// api/get_reuniones.php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/zoom_api.php';

$userId = $_GET['user_id'] ?? '';
if (!$userId) {
    echo json_encode(['error' => 'Falta user_id']);
    exit;
}

$meetings = getReunionesProfesor($userId);
$recordings = getGrabacionesProfesor($userId); // opcional

$response = [
    'meetings' => isset($meetings['meetings']) ? $meetings['meetings'] : [],
    'recordings' => isset($recordings['meetings']) ? $recordings['meetings'] : [] // cuidado: el endpoint de grabaciones devuelve 'meetings' también
];

// Si hay error en meetings, mostrar
if (isset($meetings['error'])) {
    $response['error'] = $meetings['message'] ?? 'Error al obtener reuniones';
} elseif (isset($recordings['error'])) {
    $response['error'] = $recordings['message'] ?? 'Error al obtener grabaciones';
}

echo json_encode($response);
?>
