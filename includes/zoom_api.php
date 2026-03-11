<?php
// includes/zoom_api.php
// Funciones auxiliares para consumir la API de Zoom
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/cache_manager.php';
require_once __DIR__ . '/logger.php';

function getZoomToken() {
    $cache = new CacheManager();
    $token = $cache->get('zoom_access_token');
    if ($token && !is_array($token)) return $token;

    $logger = new Logger();
    $start_time = microtime(true);

    $client_id = ZOOM_CLIENT_ID;
    $client_secret = ZOOM_CLIENT_SECRET;
    $account_id = ZOOM_ACCOUNT_ID;

    $auth = base64_encode("$client_id:$client_secret");
    $url = "https://zoom.us/oauth/token?grant_type=account_credentials&account_id=$account_id";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $auth]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $duration = microtime(true) - $start_time;
    $logger->sync('oauth/token', $http_code, $duration, $http_code === 200);

    if ($http_code !== 200) {
        return ['error' => true, 'message' => "HTTP $http_code", 'response' => $response];
    }

    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        $cache->set('zoom_access_token', $data['access_token'], ($data['expires_in'] ?? 3600) - 60);
        return $data['access_token'];
    }
    
    return ['error' => true, 'message' => 'Token no encontrado'];
}

function zoomGet($url, $token) {
    $logger = new Logger();
    $start_time = microtime(true);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $duration = microtime(true) - $start_time;
    $endpoint = parse_url($url, PHP_URL_PATH);
    $logger->sync($endpoint, $http_code, $duration, $http_code === 200);

    return ['http_code' => $http_code, 'response' => $response];
}

function getZoomUsers() {
    $cache = new CacheManager();
    $cached_users = $cache->get('zoom_users_list');
    if ($cached_users) return $cached_users;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    $result = zoomGet('https://api.zoom.us/v2/users?page_size=300', $token);

    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $users = $data['users'] ?? [];
        $cache->set('zoom_users_list', $users, 3600); 
        return $users;
    }

    return ['error' => true, 'message' => 'Error al obtener usuarios', 'http_code' => $result['http_code']];
}

function getZoomMeetings($userId) {
    $cache = new CacheManager();
    $cache_key = "zoom_meetings_$userId";
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    $url = "https://api.zoom.us/v2/users/$userId/meetings?page_size=50";
    $result = zoomGet($url, $token);

    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $cache->set($cache_key, $data, 300); 
        return $data;
    }

    return ['error' => true, 'message' => 'Error al obtener reuniones', 'http_code' => $result['http_code']];
}

function getZoomRecordings($userId) {
    $cache = new CacheManager();
    $cache_key = "zoom_recordings_$userId";
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    $url = "https://api.zoom.us/v2/users/$userId/recordings?page_size=50";
    $result = zoomGet($url, $token);

    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $cache->set($cache_key, $data, 600); 
        return $data;
    }

    return ['error' => true, 'message' => 'Error al obtener grabaciones', 'http_code' => $result['http_code']];
}

// Aliases para compatibilidad
function getProfesores() { return getZoomUsers(); }
function getReunionesProfesor($userId) { return getZoomMeetings($userId); }
function getGrabacionesProfesor($userId) { return getZoomRecordings($userId); }

// Versión orientada a objetos para compatibilidad
class ZoomAPI {
    public function getUsers() {
        $result = getZoomUsers();
        return (isset($result['error'])) ? [] : ($result['users'] ?? $result);
    }

    public function getUserMeetings($userId) {
        $result = getZoomMeetings($userId);
        return (isset($result['error'])) ? [] : ($result['meetings'] ?? []);
    }

    public function getUserRecordings($userId) {
        $result = getZoomRecordings($userId);
        return (isset($result['error'])) ? [] : ($result['meetings'] ?? []);
    }
}
?>
