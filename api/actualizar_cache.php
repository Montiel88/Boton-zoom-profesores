<?php
// api/actualizar_cache.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zoom_api.php';
requireLogin();

header('Content-Type: application/json');

$logger = new Logger();
$cache = new CacheManager();

try {
    // 1. Limpiar TODO el caché para forzar datos frescos de la API de Zoom
    $cache->clearAll();
    
    // 2. Forzar nueva obtención del listado de profesores
    $users = getZoomUsers();
    
    if (isset($users['error'])) {
        throw new Exception($users['message']);
    }

    $logger->activity('Sincronización Manual', 'El usuario forzó la actualización del listado de profesores de Zoom');

    echo json_encode([
        'status' => 'success',
        'message' => 'Caché de Zoom actualizado correctamente',
        'count' => count($users)
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al sincronizar: ' . $e->getMessage()
    ]);
}
?>
