<?php
// get_zoom_data.php
require_once 'includes/auth.php';
require_once 'includes/zoom_api.php';
requireLogin();

header('Content-Type: application/json');

$user_id = $_GET['user_id'] ?? '';
if (!$user_id) {
    echo json_encode(['error' => 'Falta user_id']);
    exit;
}

$meetings = getZoomMeetings($user_id);
$recordings = getZoomRecordings($user_id);

echo json_encode([
    'meetings' => $meetings['meetings'] ?? [],
    'recordings' => $recordings['meetings'] ?? [] // Zoom recordings also uses 'meetings' key in its response
]);
?>
