<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$db = getDB();
$stmt = $db->query("SELECT * FROM logs_sincronizacion ORDER BY created_at DESC LIMIT 10");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1'>";
echo "<tr><th>Endpoint</th><th>Status</th><th>Time</th><th>Success</th><th>Error</th><th>Created</th></tr>";
foreach ($logs as $log) {
    echo "<tr>";
    echo "<td>" . $log['endpoint'] . "</td>";
    echo "<td>" . $log['status_code'] . "</td>";
    echo "<td>" . $log['response_time'] . "</td>";
    echo "<td>" . ($log['success'] ? '✅' : '❌') . "</td>";
    echo "<td>" . $log['error_message'] . "</td>";
    echo "<td>" . $log['created_at'] . "</td>";
    echo "</tr>";
}
echo "</table>";
?>
