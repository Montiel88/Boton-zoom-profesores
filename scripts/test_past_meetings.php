<?php
// test_past_meetings.php
require_once 'includes/zoom_api.php';
$users = getZoomUsers();
$userId = $users[0]['id'];
echo "Checking past meetings for user: " . $users[0]['email'] . " ($userId)\n";
$meetings = getPastMeetings($userId);
print_r($meetings);
?>
