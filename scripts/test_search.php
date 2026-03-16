<?php
// test_search.php
require_once 'includes/zoom_api.php';

$query = 'a'; // Un término común
$zoomUsers = getZoomUsers();

if (isset($zoomUsers['error'])) {
    die("Error: " . $zoomUsers['message']);
}

echo "Total usuarios: " . count($zoomUsers) . "\n";

$matchingUsers = [];
foreach ($zoomUsers as $user) {
    $fullName = strtolower(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $email = strtolower($user['email'] ?? '');
    
    if (strpos($fullName, $query) !== false || strpos($email, $query) !== false) {
        $matchingUsers[] = $user['email'];
        if (count($matchingUsers) >= 5) break;
    }
}

echo "Primeros 5 matches para '$query':\n";
print_r($matchingUsers);
?>
