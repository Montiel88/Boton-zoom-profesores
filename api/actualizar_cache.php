<?php
// api/actualizar_cache.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zoom_api.php';
requireLogin();

header('Content-Type: application/json');

$logger = new Logger();
$cache = new CacheManager();

try {
    // 1. Limpiar caché existente de usuarios
    $cache->delete('zoom_users_list');
    $cache->delete('zoom_access_token');
    
    // 2. Forzar nueva obtención (esto disparará logs de sincronización)
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
