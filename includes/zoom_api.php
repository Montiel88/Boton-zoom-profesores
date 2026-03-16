<?php
// includes/zoom_api.php - Versión depurada con logs
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
        error_log("Error obteniendo token de Zoom: HTTP $http_code - $response");
        return ['error' => true, 'message' => "HTTP $http_code", 'response' => $response];
    }

    $data = json_decode($response, true);
    if (isset($data['access_token'])) {
        $cache->set('zoom_access_token', $data['access_token'], ($data['expires_in'] ?? 3600) - 60);
        return $data['access_token'];
    }

    return ['error' => true, 'message' => 'Token no encontrado en la respuesta'];
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

    if ($http_code !== 200) {
        error_log("Error en zoomGet a $url: HTTP $http_code - $response");
    }

    return ['http_code' => $http_code, 'response' => $response];
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

    $url = "https://api.zoom.us/v2/users/$userId/meetings?page_size=50";
    $result = zoomGet($url, $token);

    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $cache->set($cache_key, $data, 300);
        return $data;
    }

    error_log("Error en getZoomMeetings para $userId: HTTP " . $result['http_code']);
    return ['error' => true, 'message' => 'Error al obtener reuniones', 'http_code' => $result['http_code']];
}

function getZoomRecordings($userId, $from = null, $to = null) {
    $cache = new CacheManager();
    $cache_key = "zoom_recordings_{$userId}_" . ($from ?: 'none') . '_' . ($to ?: 'none');
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) {
        error_log("Error de token en getZoomRecordings");
        return ['meetings' => []];
    }

    $params = ['page_size' => 100];
    if ($from) $params['from'] = $from;
    if ($to) $params['to'] = $to;

    $all = [];
    $nextPage = '';

    do {
        $query = http_build_query(array_merge($params, $nextPage ? ['next_page_token' => $nextPage] : []));
        $url = "https://api.zoom.us/v2/users/$userId/recordings?$query";
        $result = zoomGet($url, $token);

        if ($result['http_code'] !== 200) {
            error_log("Error en getZoomRecordings: HTTP " . $result['http_code'] . " - " . $result['response']);
            return ['meetings' => []];
        }

        $data = json_decode($result['response'], true);
        if (isset($data['meetings']) && is_array($data['meetings'])) {
            $all = array_merge($all, $data['meetings']);
        }
        $nextPage = $data['next_page_token'] ?? '';
    } while (!empty($nextPage));

    $payload = ['meetings' => $all];
    $cache->set($cache_key, $payload, 600);
    return $payload;
}

function getMeetingRecordings($meetingId) {
    $cache = new CacheManager();
    $cache_key = "meeting_recordings_" . md5($meetingId);
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) {
        error_log("Error de token en getMeetingRecordings");
        return ['recording_files' => []];
    }

    // Codificación simple (una sola vez) para el UUID
    $encodedId = urlencode($meetingId);
    $url = "https://api.zoom.us/v2/meetings/$encodedId/recordings";
    $result = zoomGet($url, $token);

    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $cache->set($cache_key, $data, 1800);
        return $data;
    } elseif ($result['http_code'] === 404) {
        // No hay grabaciones, guardamos array vacío en caché
        $empty = ['recording_files' => []];
        $cache->set($cache_key, $empty, 1800);
        return $empty;
    } else {
        error_log("Error en getMeetingRecordings para $meetingId: HTTP " . $result['http_code'] . " - " . $result['response']);
        return ['recording_files' => []];
    }
}

function getPastMeetings($userId, $from = null, $to = null) {
    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) {
        error_log("Error de token en getPastMeetings");
        return ['meetings' => []];
    }

    $from = $from ?: date('Y-m-d', strtotime('-90 days'));
    $to = $to ?: date('Y-m-d');

    $url = "https://api.zoom.us/v2/report/users/$userId/meetings?from=$from&to=$to&page_size=100";
    $result = zoomGet($url, $token);

    if ($result['http_code'] === 200) {
        return json_decode($result['response'], true) ?: ['meetings' => []];
    }

    error_log("Error en getPastMeetings para $userId: HTTP " . $result['http_code'] . " - " . $result['response']);
    return ['meetings' => []];
}

function getMeetingParticipants($meetingId) {
    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) {
        error_log("Error de token en getMeetingParticipants");
        return ['participants' => []];
    }

    $encodedId = urlencode($meetingId);
    $url = "https://api.zoom.us/v2/report/meetings/$encodedId/participants?page_size=300";
    $result = zoomGet($url, $token);

    if ($result['http_code'] === 200) {
        return json_decode($result['response'], true) ?: ['participants' => []];
    }

    error_log("Error en getMeetingParticipants para $meetingId: HTTP " . $result['http_code'] . " - " . $result['response']);
    return ['participants' => []];
}

function getLiveMeetings() {
    $cache = new CacheManager();
    $cache_key = "zoom_live_meetings";
    $cached = $cache->get($cache_key);
    if ($cached) return $cached;

    $token = getZoomToken();
    if (is_array($token) && isset($token['error'])) {
        error_log("Error de token en getLiveMeetings");
        return ['meetings' => []];
    }

    $url = "https://api.zoom.us/v2/metrics/meetings?type=live&page_size=100";
    $result = zoomGet($url, $token);

    if ($result['http_code'] === 200) {
        $data = json_decode($result['response'], true);
        $cache->set($cache_key, $data, 60);
        return $data;
    }

    error_log("Error en getLiveMeetings: HTTP " . $result['http_code']);
    return ['meetings' => []];
}

// Alias y clase para compatibilidad
function getProfesores() { return getZoomUsers(); }
function getReunionesProfesor($userId) { return getZoomMeetings($userId); }
function getGrabacionesProfesor($userId) { return getZoomRecordings($userId); }

class ZoomAPI {
    public function getUsers() {
        $result = getZoomUsers();
        return (isset($result['error'])) ? [] : (is_array($result) ? $result : []);
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