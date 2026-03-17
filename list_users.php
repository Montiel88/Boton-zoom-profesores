<?php
require_once __DIR__ . '/includes/zoom_api.php';
$users = getZoomUsers();
if (is_array($users) && !isset($users['error'])) {
    foreach (array_slice($users, 0, 5) as $user) {
        echo "ID: " . $user['id'] . " - Name: " . $user['display_name'] . "\n";
    }
} else {
    print_r($users);
}
?>
