<?php
/**
 * api/get_professor_meetings.php
 * Versión depurada con logs para identificar el problema de grabaciones.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zoom_api.php';
requireLogin();

header('Content-Type: application/json');

$userId = $_GET['userId'] ?? '';
$meetingId = $_GET['meetingId'] ?? '';
$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to = $_GET['to'] ?? date('Y-m-d');

if (empty($userId)) {
    http_response_code(400);
    echo json_encode(['error' => 'Falta el ID del usuario']);
    exit;
}

// Caso especial: lista de participantes
if ($type === 'participants_list' && !empty($meetingId)) {
    try {
        $data = getMeetingParticipants($meetingId);
        $participants = $data['participants'] ?? [];
        $filtered = array_map(function($p) {
            return [
                'name' => $p['name'] ?? 'Invitado',
                'email' => $p['user_email'] ?? 'N/A',
                'duration' => round(($p['duration'] ?? 0) / 60, 1) . ' min',
                'join_time' => $p['join_time'] ?? 'N/A'
            ];
        }, $participants);
        echo json_encode(['participants' => $filtered]);
        exit;
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al obtener participantes: ' . $e->getMessage()]);
        exit;
    }
}

try {
    // Fechas para clasificar reuniones
    $todayStart = strtotime('today');
    $todayEnd = strtotime('tomorrow') - 1;

    // 1. Obtener todas las grabaciones del usuario en el rango de fechas
    $recordingsByUuid = [];
    $recordingsData = getZoomRecordings($userId, $from, $to);
    error_log("[DEBUG] Grabaciones obtenidas: " . print_r($recordingsData, true)); // Log para depuración

    if (isset($recordingsData['meetings']) && is_array($recordingsData['meetings'])) {
        foreach ($recordingsData['meetings'] as $rec) {
            if (!empty($rec['uuid'])) {
                $recordingsByUuid[$rec['uuid']] = true;
                error_log("[DEBUG] UUID con grabación: " . $rec['uuid']);
            }
        }
    }

    // Función auxiliar para verificar grabación (con caché local)
    $hasRecording = function($uuid) use (&$recordingsByUuid) {
        if (empty($uuid)) {
            error_log("[DEBUG] UUID vacío, se considera no grabado");
            return false;
        }
        if (array_key_exists($uuid, $recordingsByUuid)) {
            error_log("[DEBUG] UUID encontrado en mapa: $uuid => grabado");
            return true;
        }
        error_log("[DEBUG] UUID no encontrado en mapa, consultando API: $uuid");
        $recData = getMeetingRecordings($uuid);
        $has = isset($recData['recording_files']) && count($recData['recording_files']) > 0;
        error_log("[DEBUG] Resultado de getMeetingRecordings para $uuid: " . ($has ? 'SÍ' : 'NO') . " - " . print_r($recData, true));
        $recordingsByUuid[$uuid] = $has;
        return $has;
    };

    // 2. Obtener reuniones pasadas (Report API)
    $pastMeetings = [];
    $reportData = getPastMeetings($userId, $from, $to);
    error_log("[DEBUG] ReportData: " . print_r($reportData, true));

    if (isset($reportData['meetings']) && is_array($reportData['meetings'])) {
        foreach ($reportData['meetings'] as $m) {
            $uuid = $m['uuid'] ?? '';
            $startTime = $m['start_time'] ?? '';
            $duration = $m['duration'] ?? 0;
            $startTS = strtotime($startTime);
            $endTime = $startTS ? date('Y-m-d H:i:s', $startTS + $duration * 60) : '';

            $grabado = $hasRecording($uuid);

            $pastMeetings[] = [
                'reunion' => $m['topic'] ?? 'Sin Tema',
                'reunion_id' => $m['id'] ?? 'N/A',
                'inicio' => $startTime,
                'duracion' => sprintf('%02d:%02d:00', floor($duration / 60), $duration % 60),
                'fin' => $endTime,
                'participantes' => $m['participants_count'] ?? 0,
                'grabado' => $grabado,
                'uuid' => $uuid,
                'type' => 'past'
            ];
        }
    }

    // 3. Obtener reuniones actuales y futuras (Meeting API)
    $presentMeetings = [];
    $futureMeetings = [];
    $meetingsData = getZoomMeetings($userId);
    error_log("[DEBUG] MeetingsData: " . print_r($meetingsData, true));

    if (isset($meetingsData['meetings']) && is_array($meetingsData['meetings'])) {
        foreach ($meetingsData['meetings'] as $m) {
            $startTime = $m['start_time'] ?? '';
            $startTS = strtotime($startTime);
            $duration = $m['duration'] ?? 0;
            $endTS = $startTS + $duration * 60;
            $endTime = $startTS ? date('Y-m-d H:i:s', $endTS) : '';
            $uuid = $m['uuid'] ?? '';

            $grabado = $hasRecording($uuid);

            $meeting = [
                'reunion' => $m['topic'] ?? 'Sin Tema',
                'reunion_id' => $m['id'] ?? 'N/A',
                'inicio' => $startTime,
                'duracion' => sprintf('%02d:%02d:00', floor($duration / 60), $duration % 60),
                'fin' => $endTime,
                'participantes' => 0,
                'grabado' => $grabado,
                'join_url' => $m['join_url'] ?? '',
                'uuid' => $uuid,
            ];

            if ($startTS >= $todayStart && $startTS <= $todayEnd) {
                $meeting['type'] = 'present';
                $presentMeetings[] = $meeting;
            } elseif ($startTS > $todayEnd) {
                $meeting['type'] = 'future';
                $futureMeetings[] = $meeting;
            } else {
                // Reunión antigua que no apareció en reportes
                $found = false;
                foreach ($pastMeetings as $pm) {
                    if ($pm['reunion_id'] == $m['id'] && $pm['inicio'] == $startTime) {
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    $meeting['type'] = 'past';
                    $pastMeetings[] = $meeting;
                }
            }
        }
    }

    // Ordenar por fecha descendente
    usort($pastMeetings, fn($a, $b) => strtotime($b['inicio']) - strtotime($a['inicio']));
    usort($presentMeetings, fn($a, $b) => strtotime($b['inicio']) - strtotime($a['inicio']));
    usort($futureMeetings, fn($a, $b) => strtotime($b['inicio']) - strtotime($a['inicio']));

    echo json_encode([
        'lists' => [
            'past' => $pastMeetings,
            'present' => $presentMeetings,
            'future' => $futureMeetings
        ],
        'stats' => [
            'past' => count($pastMeetings),
            'present' => count($presentMeetings),
            'future' => count($futureMeetings)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}
?>