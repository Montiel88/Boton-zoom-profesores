<?php
require_once 'config.php';
require_once 'zoom_api.php';

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$zoom = new ZoomAPI();
$users = $zoom->getUsers();
$resultado = [];

foreach ($users as $user) {
    $meetings = $zoom->getUserMeetings($user['id']);
    $recordings = $zoom->getUserRecordings($user['id']);
    
    $resultado[] = [
        'profesor' => $user['first_name'] . ' ' . ($user['last_name'] ?? ''),
        'email' => $user['email'],
        'meetings' => array_slice($meetings, 0, 5),  // Últimas 5 reuniones
        'recordings' => array_slice($recordings, 0, 5)  // Últimas 5 grabaciones
    ];
}

echo json_encode($resultado);
?>