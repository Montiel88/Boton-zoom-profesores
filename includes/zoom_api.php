<?php
// includes/zoom_api.php
// Funciones auxiliares para consumir la API de Zoom
require_once __DIR__ . '/../config/config.php';
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

/**
 * Hacer múltiples peticiones GET en paralelo usando curl_multi
 */
function zoomGetMulti($urls, $token) {
    $mh = curl_multi_init();
    $requests = [];
    $results = [];

    foreach ($urls as $key => $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_multi_add_handle($mh, $ch);
        $requests[$key] = $ch;
    }

    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc == CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc == CURLM_OK) {
        if (curl_multi_select($mh) != -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc == CURLM_CALL_MULTI_PERFORM);
        }
    }

    foreach ($requests as $key => $ch) {
        $results[$key] = [
            'http_code' => curl_getinfo($ch, CURLINFO_HTTP_CODE),
            'response' => curl_multi_getcontent($ch)
        ];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }

    curl_multi_close($mh);
    return $results;
}

function getZoomUsers() {
    $cache = new CacheManager();
    $cached_users = $cache->get('zoom_users_list');
    if ($cached_users) return $cached_users;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    $allUsers = [];
    $nextPageToken = '';
    
    do {
        $url = 'https://api.zoom.us/v2/users?page_size=300';
        if ($nextPageToken) {
            $url .= '&next_page_token=' . $nextPageToken;
        }

        $result = zoomGet($url, $token);

        if ($result['http_code'] === 200) {
            $data = json_decode($result['response'], true);
            if (isset($data['users']) && is_array($data['users'])) {
                $allUsers = array_merge($allUsers, $data['users']);
            }
            $nextPageToken = $data['next_page_token'] ?? '';
        } else {
            // Si falla una página, devolvemos lo que tenemos o el error si no hay nada
            if (empty($allUsers)) {
                return ['error' => true, 'message' => 'Error al obtener usuarios', 'http_code' => $result['http_code']];
            }
            break;
        }
    } while ($nextPageToken);

    $cache->set('zoom_users_list', $allUsers, 3600); 
    return $allUsers;
}

function getZoomMeetings($userId) {
    $cache = new CacheManager();
    $cache_key = "zoom_meetings_$userId";
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    $allMeetings = [];
    $nextPageToken = '';

    do {
        $url = "https://api.zoom.us/v2/users/$userId/meetings?page_size=50";
        if ($nextPageToken) {
            $url .= '&next_page_token=' . $nextPageToken;
        }

        $result = zoomGet($url, $token);

        if ($result['http_code'] !== 200) {
            if (empty($allMeetings)) {
                return ['error' => true, 'message' => 'Error al obtener reuniones', 'http_code' => $result['http_code']];
            }
            break;
        }

        $data = json_decode($result['response'], true);
        if (isset($data['meetings']) && is_array($data['meetings'])) {
            $allMeetings = array_merge($allMeetings, $data['meetings']);
        }
        $nextPageToken = $data['next_page_token'] ?? '';
    } while ($nextPageToken);

    $payload = ['meetings' => $allMeetings];
    $cache->set($cache_key, $payload, 300); 
    return $payload;
}

function getZoomRecordings($userId, $from = null, $to = null) {
    $cache = new CacheManager();
    // Reducido a 60 días por defecto para mayor velocidad
    $fromKey = $from ?: date('Y-m-d', strtotime('-60 days'));
    $toKey = $to ?: date('Y-m-d');
    $cache_key = "zoom_recordings_{$userId}_{$fromKey}_{$toKey}";
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    $startDate = new DateTime($fromKey);
    $endDate = new DateTime($toKey);
    $urls = [];

    while ($startDate < $endDate) {
        $chunkStart = $startDate->format('Y-m-d');
        $tempDate = clone $startDate;
        $tempDate->modify('+1 month');
        if ($tempDate > $endDate) $tempDate = clone $endDate;
        $chunkEnd = $tempDate->format('Y-m-d');

        $urls[] = "https://api.zoom.us/v2/users/$userId/recordings?page_size=100&from=$chunkStart&to=$chunkEnd";
        $startDate = $tempDate;
    }

    $allResults = zoomGetMulti($urls, $token);
    $allMeetings = [];

    foreach ($allResults as $result) {
        if ($result['http_code'] === 200) {
            $data = json_decode($result['response'], true);
            if (isset($data['meetings']) && is_array($data['meetings'])) {
                $allMeetings = array_merge($allMeetings, $data['meetings']);
            }
        }
    }

    $payload = ['meetings' => $allMeetings];
    $cache->set($cache_key, $payload, 600); 
    return $payload;
}

/**
 * Obtener todas las reuniones activas (en vivo) en la cuenta
 */
function getLiveMeetings() {
    $cache = new CacheManager();
    $cache_key = "zoom_live_meetings";
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    // El endpoint /metrics/meetings muestra reuniones en curso
    $url = "https://api.zoom.us/v2/metrics/meetings?type=live&page_size=100";
    $result = zoomGet($url, $token);

    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        if ($data !== null && json_last_error() === JSON_ERROR_NONE) {
            $cache->set($cache_key, $data, 60); 
            return $data;
        }
    }

    error_log("Error API Zoom (Live): HTTP " . $result['http_code'] . " - " . $result['response']);
    return ['error' => true, 'message' => 'Error al obtener reuniones en vivo', 'http_code' => $result['http_code']];
}

/**
 * Obtener grabaciones de una reunión específica
 */
function getMeetingRecordings($meetingId) {
    $cache = new CacheManager();
    $cache_key = "meeting_recordings_$meetingId";
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    // Codificación para IDs que empiezan por / o contienen //
    $encodedId = urlencode(urlencode($meetingId));
    $url = "https://api.zoom.us/v2/meetings/$encodedId/recordings";
    $result = zoomGet($url, $token);

    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $cache->set($cache_key, $data, 1800); 
        return $data;
    }

    return ['error' => true, 'message' => 'No se encontraron grabaciones'];
}

/**
 * Obtener reuniones pasadas (metrics) para un usuario
 */
function getPastMeetings($userId, $from = null, $to = null) {
    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    // Reducido a 60 días por defecto para mayor velocidad
    $from = $from ?: date('Y-m-d', strtotime('-60 days'));
    $to = $to ?: date('Y-m-d');

    $startDate = new DateTime($from);
    $endDate = new DateTime($to);
    $urls = [];

    while ($startDate < $endDate) {
        $chunkStart = $startDate->format('Y-m-d');
        $tempDate = clone $startDate;
        $tempDate->modify('+1 month');
        if ($tempDate > $endDate) $tempDate = clone $endDate;
        $chunkEnd = $tempDate->format('Y-m-d');

        $urls[] = "https://api.zoom.us/v2/report/users/$userId/meetings?from=$chunkStart&to=$chunkEnd&page_size=100";
        $startDate = $tempDate;
    }

    $allResults = zoomGetMulti($urls, $token);
    $allMeetings = [];

    foreach ($allResults as $result) {
        if ($result['http_code'] === 200) {
            $data = json_decode($result['response'], true);
            if (isset($data['meetings']) && is_array($data['meetings'])) {
                $allMeetings = array_merge($allMeetings, $data['meetings']);
            }
        }
    }

    return ['meetings' => $allMeetings];
}

/**
 * Obtener participantes de una reunión específica
 * Importante: Se debe usar el UUID de la reunión, no el ID numérico.
 */
function getMeetingParticipants($meetingId) {
    $cache = new CacheManager();
    $cache_key = "meeting_participants_" . md5($meetingId);
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) return $token;

    // Si el UUID comienza con / o contiene // debe ser doblemente codificado
    $needsDoubleEncode = (strpos($meetingId, '/') !== false || strpos($meetingId, '+') !== false);
    $encodedId = $needsDoubleEncode ? urlencode(urlencode($meetingId)) : urlencode($meetingId);
    
    // Intento 1: Past Meetings API (A veces tiene mejores scopes/permisos)
    $url = "https://api.zoom.us/v2/past_meetings/$encodedId/participants?page_size=300";
    $result = zoomGet($url, $token);
    
    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $cache->set($cache_key, $data, 600);
        return $data;
    }

    // Intento 2: Report API
    $url = "https://api.zoom.us/v2/report/meetings/$encodedId/participants?page_size=300";
    $result = zoomGet($url, $token);
    
    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $cache->set($cache_key, $data, 600);
        return $data;
    }
    
    // Intento 3: Metrics API
    $url = "https://api.zoom.us/v2/metrics/meetings/$encodedId/participants?page_size=300";
    $result = zoomGet($url, $token);
    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $cache->set($cache_key, $data, 60);
        return $data;
    }

    return ['error' => true, 'message' => 'No se pudieron obtener los participantes.'];
}

// Aliases para compatibilidad
function getProfesores() { return getZoomUsers(); }
function getReunionesProfesor($userId) { return getZoomMeetings($userId); }
function getGrabacionesProfesor($userId, $from = null, $to = null) { return getZoomRecordings($userId, $from, $to); }

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

    public function getUserRecordings($userId, $from = null, $to = null) {
        $result = getZoomRecordings($userId, $from, $to);
        return (isset($result['error'])) ? [] : ($result['meetings'] ?? []);
    }
}
?>
