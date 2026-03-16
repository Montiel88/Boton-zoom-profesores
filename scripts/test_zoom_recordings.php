<?php
// test_zoom_recordings.php
require_once 'includes/zoom_api.php';
$users = getZoomUsers();
$userId = $users[0]['id'];
echo "Checking recordings for user: " . $users[0]['email'] . " ($userId)\n";
$recordings = getZoomRecordings($userId);
print_r($recordings);
?>
