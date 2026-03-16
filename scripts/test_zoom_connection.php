<?php
// test_zoom_connection.php
require_once 'includes/zoom_api.php';

header('Content-Type: application/json');

$token = getZoomToken();
if (isset($token['error'])) {
    echo json_encode(['success' => false, 'error' => $token['message']]);
    exit;
}

$result = zoomGet('https://api.zoom.us/v2/users?page_size=1', $token);
if ($result['http_code'] == 200) {
    $data = json_decode($result['response'], true);
    echo json_encode(['success' => true, 'user_count' => $data['total_records'] ?? 0]);
} else {
    echo json_encode(['success' => false, 'error' => "HTTP {$result['http_code']}: {$result['response']}"]);
}
?>
