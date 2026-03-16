<?php
// scripts/debug_santiago.php
require_once 'includes/zoom_api.php';

$query = 'santiago';
$zoomUsers = getZoomUsers();

if (isset($zoomUsers['error'])) {
    die("Error getting users: " . $zoomUsers['message']);
}

echo "Searching for '$query' in " . count($zoomUsers) . " users...\n";

$found = false;
foreach ($zoomUsers as $user) {
    $firstName = $user['first_name'] ?? '';
    $lastName = $user['last_name'] ?? '';
    $email = $user['email'] ?? '';
    $fullName = strtolower($firstName . ' ' . $lastName);
    
    if (strpos($fullName, $query) !== false || strpos(strtolower($email), $query) !== false) {
        $found = true;
        echo "\nFOUND: $firstName $lastName ($email) [ID: {$user['id']}]\n";
        
        echo "--- Checking Past Meetings (Report API) ---\n";
        $past = getPastMeetings($user['id'], date('Y-m-d', strtotime('-30 days')));
        if (isset($past['error'])) {
            echo "PAST MEETINGS ERROR: " . $past['message'] . "\n";
        } else {
            echo "Past Meetings Found: " . (isset($past['meetings']) ? count($past['meetings']) : 0) . "\n";
            if (isset($past['meetings'][0])) {
                print_r($past['meetings'][0]);
            }
        }
        
        echo "--- Checking Regular Meetings (Meeting API) ---\n";
        $regular = getZoomMeetings($user['id']);
        if (isset($regular['error'])) {
            echo "REGULAR MEETINGS ERROR: " . $regular['message'] . "\n";
        } else {
            echo "Regular Meetings Found: " . (isset($regular['total_records']) ? $regular['total_records'] : 0) . "\n";
        }

        echo "--- Checking Recordings (Recording API) ---\n";
        $recordings = getZoomRecordings($user['id']);
        if (isset($recordings['error'])) {
            echo "RECORDINGS ERROR: " . $recordings['message'] . "\n";
        } else {
            echo "Recordings Found: " . (isset($recordings['total_records']) ? $recordings['total_records'] : 0) . "\n";
        }
    }
}

if (!$found) {
    echo "No user found matching '$query'\n";
}
?>
