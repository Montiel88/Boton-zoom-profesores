<?php
// get_zoom_data.php
require_once __DIR__ . '/zoom_api.php';

$token = getZoomToken();
if (is_array($token) && isset($token['error'])) {
    die(json_encode($token, JSON_PRETTY_PRINT));
}

// Probar endpoint de usuarios
$result = zoomGet('https://api.zoom.us/v2/users?page_size=1', $token);
if ($result['http_code'] == 200) {
    echo "<h1>Conexión exitosa</h1>";
    echo "<pre>" . json_encode(json_decode($result['response']), JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "Error: " . $result['http_code'] . " - " . $result['response'];
}
?>