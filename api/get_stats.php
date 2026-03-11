<?php
// api/get_stats.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zoom_api.php';
requireLogin();

header('Content-Type: application/json');

$users = getZoomUsers();
$total_users = is_array($users) ? count($users) : 0;

// Conteo de estados para el gráfico
$statuses = [
    'active' => 0,
    'inactive' => 0,
    'pending' => 0
];
if (is_array($users)) {
    foreach ($users as $u) {
        $status = $u['status'] ?? 'unknown';
        if (isset($statuses[$status])) {
            $statuses[$status]++;
        } else {
            $statuses['inactive']++; // Fallback
        }
    }
}

$db = getDB();

// Mantenimiento ligero en cada consulta de stats (solo si ha pasado tiempo)
if (rand(1, 10) === 1) { // Probabilidad 1/10
    $cache = new CacheManager();
    $cache->clearExpired();
}

$total_cache = $db->query("SELECT COUNT(*) FROM zoom_cache")->fetchColumn();
$last_sync = $db->query("SELECT created_at FROM logs_sincronizacion ORDER BY created_at DESC LIMIT 1")->fetchColumn();

// Datos para el gráfico de sincronización (últimas 10)
$sync_logs = $db->query("SELECT created_at, success, response_time FROM logs_sincronizacion ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
$sync_logs = array_reverse($sync_logs);

echo json_encode([
    'total_profesores' => $total_users,
    'elementos_cache' => $total_cache,
    'ultima_sincronizacion' => $last_sync,
    'statuses' => $statuses,
    'sync_history' => $sync_logs,
    'status' => 'OK'
]);
?>
