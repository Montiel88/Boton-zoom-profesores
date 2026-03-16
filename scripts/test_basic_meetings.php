<?php
// test_basic_meetings.php
require_once 'includes/zoom_api.php';
$users = getZoomUsers();
$userId = $users[0]['id'];
echo "Checking basic meetings for user: " . $users[0]['email'] . " ($userId)\n";
$meetings = getZoomMeetings($userId);
print_r($meetings);
?>
