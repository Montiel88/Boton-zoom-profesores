<?php
// test_simple_zoom.php
require_once 'includes/zoom_api.php';
$token = getZoomToken();
if (is_array($token)) {
    print_r($token);
} else {
    echo "Token OK: " . substr($token, 0, 10) . "...\n";
    $users = getZoomUsers();
    echo "Total usuarios: " . count($users) . "\n";
}
?>
