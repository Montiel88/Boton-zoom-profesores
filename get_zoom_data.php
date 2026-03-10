<?php
require_once 'config.php';
require_once 'zoom_api.php';

if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$zoom = new ZoomAPI();
$userId = $_GET['user_id'] ?? '';

$meetings = $zoom->getUserMeetings($userId);
$recordings = $zoom->getUserRecordings($userId);

echo json_encode([
    'meetings' => $meetings,
    'recordings' => $recordings
]);
?>