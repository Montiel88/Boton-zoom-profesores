<?php
// find_user_with_meetings.php
require_once __DIR__ . '/includes/zoom_api.php';

$users = getZoomUsers();
$from = date('Y-m-d', strtotime('-30 days'));
$to = date('Y-m-d');

foreach ($users as $user) {
    $userId = $user['id'];
    $data = getPastMeetings($userId, $from, $to);
    $count = count($data['meetings'] ?? []);
    if ($count > 0) {
        echo "User found: " . $user['display_name'] . " (ID: $userId) with $count meetings.\n";
        exit;
    }
}
echo "No user found with meetings in the last 30 days.\n";
?>
