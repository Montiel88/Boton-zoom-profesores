<?php
// api/get_professor_meetings.php - VERSIÓN 3.2 CORREGIDA
// Usa SOLO Report API para reuniones pasadas (como Zoom Metrics Dashboard)
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zoom_api.php';
requireLogin();

header('Content-Type: application/json');

$userId = $_GET['userId'] ?? '';
$meetingId = $_GET['meetingId'] ?? '';
$type = $_GET['type'] ?? '';
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to = $_GET['to'] ?? date('Y-m-d');

// Caso especial para lista de participantes
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
    $now = time();

    // 1. Obtener reuniones pasadas (Report API - ÚNICA fuente de verdad)
    $pastMeetings = getPastMeetings($userId, $from, $to);
    
    // 2. Obtener grabaciones (mismo rango de fechas)
    $recordingsData = getZoomRecordings($userId, $from, $to);

    // 3. Obtener reuniones EN VIVO (Metrics API)
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
     * FUNCIÓN: Buscar grabación para una reunión
     * Estrategia múltiple: UUID, ID+fecha, tema+fecha+hora
     */
    $findRecordingForMeeting = function($meeting) use ($recordingsData) {
        if (!isset($recordingsData['meetings']) || !is_array($recordingsData['meetings'])) {
            return false;
        }

        $meetingId = (string)($meeting['id'] ?? '');
        $meetingUuid = (string)($meeting['uuid'] ?? '');
        $meetingTopic = strtolower(trim($meeting['topic'] ?? ''));
        $meetingStartTime = $meeting['start_time'] ?? '';
        $meetingDate = $meetingStartTime ? date('Y-m-d', strtotime($meetingStartTime)) : '';
        $meetingHour = $meetingStartTime ? (int)date('H', strtotime($meetingStartTime)) : -1;

        // Normalizar UUID para comparación (decodificar si está encoded)
        $normalizeUuid = function($uuid) {
            if (!$uuid) return '';
            // Intentar decodificar si está encoded
            $decoded = urldecode($uuid);
            $decoded2 = urldecode($decoded);
            return trim($decoded2);
        };

        $normalizedMeetingUuid = $normalizeUuid($meetingUuid);

        foreach ($recordingsData['meetings'] as $rm) {
            $recId = (string)($rm['id'] ?? '');
            $recUuid = (string)($rm['uuid'] ?? '');
            $recTopic = strtolower(trim($rm['topic'] ?? ''));
            $recStartTime = $rm['start_time'] ?? '';
            $recDate = $recStartTime ? date('Y-m-d', strtotime($recStartTime)) : '';
            $recHour = $recStartTime ? (int)date('H', strtotime($recStartTime)) : -1;

            // Normalizar UUID de grabación
            $normalizedRecUuid = $normalizeUuid($recUuid);

            // Estrategia 1: Match por UUID (más confiable)
            if ($normalizedMeetingUuid && $normalizedRecUuid) {
                if ($normalizedMeetingUuid === $normalizedRecUuid) {
                    return true;
                }
                // UUIDs pueden variar en encoding, intentar comparación directa también
                if ($meetingUuid && $recUuid && $meetingUuid === $recUuid) {
                    return true;
                }
            }

            // Estrategia 2: Match por ID + misma fecha
            if ($meetingId && $recId && $meetingId === $recId && $meetingDate === $recDate) {
                return true;
            }

            // Estrategia 3: Match por tema + fecha + hora similar (±3 horas)
            if ($meetingTopic && $recTopic && $meetingTopic === $recTopic && $meetingDate === $recDate) {
                $hourDiff = abs($meetingHour - $recHour);
                if ($hourDiff <= 3) {
                    return true;
                }
            }

            // Estrategia 4: Match por ID sin fecha (si el ID coincide exactamente)
            if ($meetingId && $recId && $meetingId === $recId) {
                return true;
            }
        }

        return false;
    };

    // Cache para participantes
    $participantsCache = [];

    // PROCESAR REUNIONES PASADAS (Report API)
    if (isset($pastMeetings['meetings']) && is_array($pastMeetings['meetings'])) {
        foreach ($pastMeetings['meetings'] as $m) {
            $startTimeStr = $m['start_time'] ?? '';
            $endTimeStr = $m['end_time'] ?? '';
            
            // Calcular duración REAL (end_time - start_time)
            $startTS = strtotime($startTimeStr);
            $endTS = $endTimeStr ? strtotime($endTimeStr) : 0;
            
            $durationSeconds = 0;
            if ($startTS && $endTS) {
                $durationSeconds = $endTS - $startTS;
            } else {
                $durationSeconds = ($m['duration'] ?? 0) * 60;
            }

            $h = floor($durationSeconds / 3600);
            $i = floor(($durationSeconds % 3600) / 60);
            $s = $durationSeconds % 60;
            $durationFormatted = sprintf('%02d:%02d:%02d', $h, $i, $s);

            $endTime = $endTimeStr ? date('Y-m-d H:i:s', $endTS) : '';

            // Participantes (Si el reporte dice 0, forzar consulta)
            $participantsCount = $m['participants_count'] ?? 0;
            $uuid = $m['uuid'] ?? '';
            
            if ($participantsCount <= 0 && $uuid) {
                // OPTIMIZACIÓN: Solo forzar búsqueda para reuniones recientes (últimos 15 días)
                // Las reuniones más antiguas suelen ser "ghost meetings" si el reporte sigue en 0.
                $isRecent = (time() - $startTS) < (15 * 86400);
                
                if ($isRecent && !isset($participantsCache[$uuid])) {
                    // Intento 1: Past Meeting Instance Details
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

                    // Intento 2: Report API Participants
                    if (!isset($participantsCache[$uuid])) {
                        $pData = getMeetingParticipants($uuid);
                        if (!isset($pData['error']) && isset($pData['participants']) && is_array($pData['participants'])) {
                            $participantsCache[$uuid] = count($pData['participants']);
                        } else {
                            $participantsCache[$uuid] = 0;
                        }
                    }
                }
                $participantsCount = $participantsCache[$uuid] ?? 0;
            }

            $allPastMeetings[] = [
                'reunion' => ($m['topic'] ?? 'Sin Tema'),
                'reunion_id' => $m['id'] ?? 'N/A',
                'inicio' => $startTimeStr, // Fecha y hora REAL del Report API
                'fin' => $endTime,         // Fecha y hora REAL del Report API
                'duracion' => $durationFormatted, // Duración CALCULADA (no estimada)
                'participantes' => $participantsCount,
                'grabado' => $findRecordingForMeeting($m),
                'uuid' => $uuid,
                'type' => 1 // Pasada
            ];
        }
    }

    // Obtener reuniones programadas (solo para futuras/en vivo)
    $rawMeetings = getZoomMeetings($userId);
    
    // PROCESAR REUNIONES DE MEETING API (Futuras/En Vivo)
    if (isset($rawMeetings['meetings']) && is_array($rawMeetings['meetings'])) {
        foreach ($rawMeetings['meetings'] as $m) {
            $startTimeStr = $m['start_time'] ?? '';
            $startTS = $startTimeStr ? strtotime($startTimeStr) : 0;
            
            $uuidCurrent = $m['uuid'] ?? '';
            $mIdStr = (string)($m['id'] ?? '');
            
            // Verificar si está EN VIVO
            $isLive = isset($liveMeetingsMap[$mIdStr]);
            $liveParticipants = $isLive ? ($liveMeetingsMap[$mIdStr]['participants'] ?? 0) : 0;
            
            // Duración
            $durationMin = $m['duration'] ?? 0;
            $durationFormatted = sprintf('%02d:%02d:00', floor($durationMin / 60), $durationMin % 60);
            
            if ($isLive && isset($liveMeetingsMap[$mIdStr]['duration'])) {
                $durationFormatted = $liveMeetingsMap[$mIdStr]['duration'];
                if (strlen($durationFormatted) === 5) $durationFormatted = "00:" . $durationFormatted;
            }

            $endTS = $startTS + ($durationMin * 60);
            $endTimeStr = $startTimeStr ? date('Y-m-d H:i:s', $endTS) : '';

            $meetingData = [
                'reunion' => ($m['topic'] ?? 'Sin Tema'),
                'reunion_id' => $m['id'] ?? 'N/A',
                'inicio' => $startTimeStr,
                'fin' => $endTimeStr,
                'duracion' => $durationFormatted,
                'participantes' => $liveParticipants,
                'is_live' => $isLive,
                'grabado' => false, // FUTURAS y EN VIVO = NO grabado (aún no existe grabación)
                'join_url' => $m['join_url'] ?? '',
                'type' => $m['type'] ?? 2,
                'uuid' => $uuidCurrent
            ];

            if ($isLive) {
                $allLiveMeetings[] = $meetingData;
            } elseif ($startTS > $now) {
                $allFutureMeetings[] = $meetingData;
            }
            // Si ya pasó y no está en Report API, la ignoramos (el Report API es la fuente de verdad)
        }
    }

    // Agregar reuniones EN VIVO instantáneas (no programadas)
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
            if (strlen($durationStr) === 5) $durationStr = "00:" . $durationStr;
            
            $uuid = $lm['uuid'] ?? '';
            
            $allLiveMeetings[] = [
                'reunion' => ($lm['topic'] ?? 'Reunión Instantánea'),
                'reunion_id' => $id,
                'inicio' => $startTimeStr,
                'fin' => '',
                'duracion' => $durationStr,
                'participantes' => $lm['participants'] ?? 0,
                'is_live' => true,
                'grabado' => false, // En vivo = sin grabación aún
                'join_url' => '',
                'type' => 1,
                'uuid' => $uuid
            ];
        }
    }

    // Ordenar por fecha (más reciente primero)
    $sortFn = function($a, $b) { return strtotime($b['inicio']) - strtotime($a['inicio']); };
    usort($allPastMeetings, $sortFn);
    usort($allLiveMeetings, $sortFn);
    usort($allFutureMeetings, $sortFn);

    echo json_encode([
        'lists' => [
            'past' => $allPastMeetings,
            'present' => $allLiveMeetings,
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
