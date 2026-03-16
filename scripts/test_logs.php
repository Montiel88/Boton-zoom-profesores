<?php
// test_logs.php
require_once 'config/config.php';
require_once 'includes/functions.php';

$db = getDB();
$sync = $db->query("SELECT * FROM logs_sincronizacion ORDER BY created_at DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

echo "Últimos 20 logs de sincronización:\n";
foreach ($sync as $s) {
    echo "[{$s['created_at']}] Endpoint: {$s['endpoint']}, Status: {$s['status_code']}, Time: {$s['response_time']}s, Success: " . ($s['success'] ? 'YES' : 'NO') . "\n";
}
?>
