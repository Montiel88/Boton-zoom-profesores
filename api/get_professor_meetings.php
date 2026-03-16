<?php
// api/get_professor_meetings.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zoom_api.php';
requireLogin();

header('Content-Type: application/json');

$userId = $_GET['userId'] ?? '';
$meetingId = $_GET['meetingId'] ?? '';
$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-180 days'));
$to = $_GET['to'] ?? date('Y-m-d');

// Caso especial para lista de participantes (solo requiere meetingId)
if ($type === 'participants_list' && !empty($meetingId)) {
    try {
        $data = getMeetingParticipants($meetingId);
        $participants = $data['participants'] ?? [];
        $filtered = [];
        foreach ($participants as $p) {
            $filtered[] = [
                'name' => $p['name'] ?? 'Invitado',
                'email' => $p['user_email'] ?? 'N/A',
                'duration' => round(($p['duration'] ?? 0) / 60, 1) . ' min',
                'join_time' => $p['join_time'] ?? 'N/A'
            ];
        }
        echo json_encode(['participants' => $filtered]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error al obtener participantes: ' . $e->getMessage()]);
        exit;
    }
}

if (empty($userId)) {
    echo json_encode(['error' => 'Falta el ID del usuario']);
    exit;
}

try {
    $allPastMeetings = [];
    $allLiveMeetings = [];
    $allFutureMeetings = [];
    $totalParticipantsCount = 0;

    $now = time();

    // 1. Obtener reuniones pasadas con detalles de participantes (Report API)
    $pastMeetings = getPastMeetings($userId, $from, $to);
    
    // 2. Obtener reuniones programadas y recurrentes (Meeting API)
    $rawMeetings = getZoomMeetings($userId);
    
    // 3. Obtener grabaciones (con el mismo rango de fechas para evitar falsos negativos)
    $recordingsData = getZoomRecordings($userId, $from, $to);

    // 4. Obtener reuniones EN VIVO (Metrics API)
    $liveData = getLiveMeetings();
    $liveMeetingsMap = [];
    if (isset($liveData['meetings']) && is_array($liveData['meetings'])) {
        foreach ($liveData['meetings'] as $lm) {
            if (($lm['host_id'] ?? '') === $userId) {
                $liveMeetingsMap[(string)($lm['id'] ?? '')] = $lm;
            }
        }
    }

    /**
     * Procesar grabaciones para marcarlas
     */
    $recordingUuids = [];
    $recordingIdsFallback = [];
    if (isset($recordingsData['meetings']) && is_array($recordingsData['meetings'])) {
        foreach ($recordingsData['meetings'] as $rm) {
            $id = trim((string)($rm['id'] ?? ''));
            $uuid = trim((string)($rm['uuid'] ?? ''));

            $normalizeUuid = function($u) {
                return [$u, urlencode($u), urlencode(urlencode($u))];
            };

            if ($uuid !== '') {
                foreach ($normalizeUuid($uuid) as $variant) {
                    $recordingUuids[$variant] = true;
                }
            } elseif ($id !== '') {
                $recordingIdsFallback[$id] = true;
            }
        }
    }

    // Cache local en memoria para evitar múltiples llamadas al mismo meeting
    $participantsCache = [];

    // Procesar reuniones pasadas (Auditoría)
    if (isset($pastMeetings['meetings']) && is_array($pastMeetings['meetings'])) {
        foreach ($pastMeetings['meetings'] as $m) {
            $startTimeStr = $m['start_time'] ?? '';
            $endTimeStr = $m['end_time'] ?? '';
            
            $startTS = strtotime($startTimeStr);
            $endTS = $endTimeStr ? strtotime($endTimeStr) : 0;
            
            $durationSeconds = 0;
            if ($startTS && $endTS) {
                $durationSeconds = $endTS - $startTS;
            } else {
                // Fallback a la duración en minutos si no hay end_time exacto
                $durationSeconds = ($m['duration'] ?? 0) * 60;
            }

            $h = floor($durationSeconds / 3600);
            $i = floor(($durationSeconds % 3600) / 60);
            $s = $durationSeconds % 60;
            $durationFormatted = sprintf('%02d:%02d:%02d', $h, $i, $s);

            $endTime = $endTimeStr ? date('Y-m-d H:i:s', $endTS) : '';

            // Obtener participantes: si el conteo viene en cero, intentamos un fetch puntual del reporte de participantes
            $participantsCount = $m['participants_count'] ?? 0;
            $uuid = $m['uuid'] ?? '';
            
            // Si el conteo es 0 o no existe, intentamos obtenerlo por UUID desde múltiples fuentes
            if ($participantsCount <= 0 && $uuid) {
                if (!isset($participantsCache[$uuid])) {
                    // Intento 1: Past Meeting Instance Details (Muy confiable para el count)
                    $token = getZoomToken();
                    if (!is_array($token)) {
                        $needsDouble = (strpos($uuid, '/') !== false || strpos($uuid, '+') !== false);
                        $encoded = $needsDouble ? urlencode(urlencode($uuid)) : urlencode($uuid);
                        
                        $pUrl = "https://api.zoom.us/v2/past_meetings/$encoded";
                        $pRes = zoomGet($pUrl, $token);
                        if ($pRes['http_code'] === 200) {
                            $pData = json_decode($pRes['response'], true);
                            if (isset($pData['participants_count']) && $pData['participants_count'] > 0) {
                                $participantsCache[$uuid] = $pData['participants_count'];
                            }
                        }
                    }

                    // Intento 2: Report API (Si el anterior falló o no dio count > 0)
                    if (!isset($participantsCache[$uuid])) {
                        $pData = getMeetingParticipants($uuid);
                        if (!isset($pData['error']) && isset($pData['participants']) && is_array($pData['participants'])) {
                            $participantsCache[$uuid] = count($pData['participants']);
                        } else {
                            $participantsCache[$uuid] = 0;
                        }
                    }
                }
                $participantsCount = $participantsCache[$uuid];
            }

            $allPastMeetings[] = [
                'reunion' => ($m['topic'] ?? 'Sin Tema'),
                'reunion_id' => $m['id'] ?? 'N/A',
                'inicio' => $startTimeStr,
                'duracion' => $durationFormatted,
                'fin' => $endTime,
                'participantes' => $participantsCount,
                'grabado' => isset($recordingUuids[(string)($uuid ?? '')])
                             || isset($recordingUuids[urlencode((string)($uuid ?? ''))])
                             || isset($recordingUuids[urlencode(urlencode((string)($uuid ?? '')))])
                             || (empty($m['uuid']) && isset($recordingIdsFallback[(string)($m['id'] ?? '')])),
                'uuid' => $uuid,
                'type' => 1 // Pasada
            ];
            $totalParticipantsCount += $participantsCount;
        }
    }

    // Procesar reuniones de la Meeting API (Futuras o posiblemente en vivo)
    if (isset($rawMeetings['meetings']) && is_array($rawMeetings['meetings'])) {
        foreach ($rawMeetings['meetings'] as $m) {
            $startTimeStr = $m['start_time'] ?? '';
            $durationMin = $m['duration'] ?? 0;
            $startTS = $startTimeStr ? strtotime($startTimeStr) : 0;
            $endTS = $startTS + ($durationMin * 60);
            $endTimeStr = $startTimeStr ? date('Y-m-d H:i:s', $endTS) : '';

            $uuidCurrent = $m['uuid'] ?? '';
            $mIdStr = (string)($m['id'] ?? '');
            
            // Determinar si está en vivo AHORA
            $isLive = isset($liveMeetingsMap[$mIdStr]);
            $liveParticipants = $isLive ? ($liveMeetingsMap[$mIdStr]['participants'] ?? 0) : 0;
            
            // Si está en vivo, intentar usar la duración exacta de la Metrics API
            $durationFormatted = sprintf('%02d:%02d:00', floor($durationMin / 60), $durationMin % 60);
            if ($isLive && isset($liveMeetingsMap[$mIdStr]['duration'])) {
                $durationFormatted = $liveMeetingsMap[$mIdStr]['duration'];
                // Asegurar formato HH:mm:ss si solo viene mm:ss
                if (strlen($durationFormatted) === 5) $durationFormatted = "00:" . $durationFormatted;
            }

            $meetingData = [
                'reunion' => ($m['topic'] ?? 'Sin Tema'),
                'reunion_id' => $m['id'] ?? 'N/A',
                'inicio' => $startTimeStr,
                'duracion' => $durationFormatted,
                'fin' => $endTimeStr,
                'participantes' => $liveParticipants,
                'is_live' => $isLive,
                'grabado' => isset($recordingUuids[(string)($uuidCurrent ?? '')])
                             || isset($recordingUuids[urlencode((string)($uuidCurrent ?? ''))])
                             || isset($recordingUuids[urlencode(urlencode((string)($uuidCurrent ?? '')))])
                             || (empty($m['uuid']) && isset($recordingIdsFallback[(string)($m['id'] ?? '')])),
                'join_url' => $m['join_url'] ?? '',
                'type' => $m['type'] ?? 2,
                'uuid' => $uuidCurrent
            ];

            if ($isLive) {
                $allLiveMeetings[] = $meetingData;
            } elseif ($startTS > $now) {
                // Es del FUTURO (Mañana, hoy más tarde, etc.)
                $allFutureMeetings[] = $meetingData;
            } else {
                // Es antigua o de hoy que ya pasó pero no está en Report API aún
                // La agregamos a pasadas si no existe por ID
                $found = false;
                foreach($allPastMeetings as $pm) { if($pm['reunion_id'] == $m['id']) $found = true; }
                if(!$found) $allPastMeetings[] = $meetingData;
            }
        }
    }

    // 5. Agregar reuniones EN VIVO que no estén en rawMeetings (ej: reuniones instantáneas)
    foreach ($liveMeetingsMap as $id => $lm) {
        $found = false;
        foreach ($allLiveMeetings as $alm) {
            if ((string)$alm['reunion_id'] === (string)$id) {
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            $startTimeStr = $lm['start_time'] ?? '';
            $durationStr = $lm['duration'] ?? '00:00:00';
            // Normalizar a HH:mm:ss si viene mm:ss de la Metrics API
            if (strlen($durationStr) === 5) $durationStr = "00:" . $durationStr;
            
            $uuid = $lm['uuid'] ?? '';
            
            $allLiveMeetings[] = [
                'reunion' => ($lm['topic'] ?? 'Reunión Instantánea'),
                'reunion_id' => $id,
                'inicio' => $startTimeStr,
                'duracion' => $durationStr,
                'fin' => '', // Sigue en curso
                'participantes' => $lm['participants'] ?? 0,
                'is_live' => true,
                'grabado' => isset($recordingUuids[(string)$uuid]) || isset($recordingIdsFallback[$id]),
                'join_url' => '', 
                'type' => 1, 
                'uuid' => $uuid
            ];
        }
    }

    // Ordenar todas por fecha de inicio
    $sortFn = function($a, $b) { return strtotime($b['inicio']) - strtotime($a['inicio']); };
    usort($allPastMeetings, $sortFn);
    usort($allLiveMeetings, $sortFn);
    usort($allFutureMeetings, $sortFn);

    echo json_encode([
        'lists' => [
            'past' => $allPastMeetings,
            'present' => $allLiveMeetings, // Mantengo la llave 'present' para no romper el JS pero con datos LIVE
            'future' => $allFutureMeetings
        ],
        'stats' => [
            'past' => count($allPastMeetings),
            'present' => count($allLiveMeetings),
            'future' => count($allFutureMeetings)
        ]
    ]);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
