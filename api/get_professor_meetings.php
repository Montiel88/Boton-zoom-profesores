<?php
// api/get_professor_meetings.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zoom_api.php';
requireLogin();

header('Content-Type: application/json');

$userId = $_GET['userId'] ?? '';
$meetingId = $_GET['meetingId'] ?? '';
$type = $_GET['type'] ?? '';
$from = !empty($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-180 days'));
$to = !empty($_GET['to']) ? $_GET['to'] : date('Y-m-d');

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
    // Restauramos el rango a 180 días para asegurar que no se pierdan reuniones antiguas
    $searchFrom = !empty($_GET['from']) ? $_GET['from'] : date('Y-m-d', strtotime('-180 days'));
    $searchTo = !empty($_GET['to']) ? $_GET['to'] : date('Y-m-d');
    
    // Implementar caché robusta para evitar la lentitud de la API de Zoom
    $cache = new CacheManager();
    $cacheKeyPast = "past_meetings_{$userId}_{$searchFrom}_{$searchTo}";
    $pastMeetings = $cache->get($cacheKeyPast);
    
    if (!$pastMeetings) {
        $pastMeetings = getPastMeetings($userId, $searchFrom, $searchTo);
        if ($pastMeetings && !isset($pastMeetings['error'])) {
            $cache->set($cacheKeyPast, $pastMeetings, 300); // 5 minutos de caché
        }
    }
    
    // 2. Obtener reuniones programadas y recurrentes (Meeting API)
    $rawMeetings = getZoomMeetings($userId);
    
    // 3. Obtener grabaciones - Usar caché también aquí para agilizar
    $cacheKeyRec = "recordings_{$userId}_{$searchFrom}_{$searchTo}";
    $recordingsData = $cache->get($cacheKeyRec);
    if (!$recordingsData) {
        $recordingsData = getZoomRecordings($userId, $searchFrom, $searchTo);
        if ($recordingsData && !isset($recordingsData['error'])) {
            $cache->set($cacheKeyRec, $recordingsData, 600); // 10 minutos de caché
        }
    }

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

    // Mapear reuniones pasadas por UUID para búsqueda rápida
    $pastUuidsMap = [];

    // Procesar reuniones pasadas (Auditoría - Fuente de Verdad para Sesiones Cerradas)
    if (isset($pastMeetings['meetings']) && is_array($pastMeetings['meetings'])) {
        foreach ($pastMeetings['meetings'] as $m) {
            $uuid = $m['uuid'] ?? '';
            $mId = (string)($m['id'] ?? '');
            
            $startTimeStr = $m['start_time'] ?? '';
            $endTimeStr = $m['end_time'] ?? '';
            
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

            $participantsCount = $m['participants_count'] ?? 0;
            
            // REACTIVADO: Recuperación agresiva de participantes
            // Para asegurar que NINGUNA clase antigua aparezca sin datos.
            if ($participantsCount <= 0 && $uuid) {
                if (!isset($participantsCache[$uuid])) {
                    $pData = getMeetingParticipants($uuid);
                    $participantsCache[$uuid] = (isset($pData['participants']) && is_array($pData['participants'])) ? count($pData['participants']) : 0;
                }
                $participantsCount = $participantsCache[$uuid];
            }

            $recordingMatch = false;
            $uuidStr = (string)$uuid;
            $mIdStr = (string)$mId;
            
            if ($uuidStr !== '' && (
                isset($recordingUuids[$uuidStr]) || 
                isset($recordingUuids[urlencode($uuidStr)]) || 
                isset($recordingUuids[urlencode(urlencode($uuidStr))])
            )) {
                $recordingMatch = true;
            } elseif ($mIdStr !== '' && isset($recordingIdsFallback[$mIdStr])) {
                $recordingMatch = true;
            }

            $meetingEntry = [
                'reunion' => ($m['topic'] ?? 'Sin Tema'),
                'reunion_id' => $mIdStr,
                'inicio' => $startTimeStr,
                'duracion' => $durationFormatted,
                'fin' => $endTime,
                'participantes' => $participantsCount,
                'grabado' => $recordingMatch,
                'uuid' => $uuidStr,
                'type' => 1
            ];

            $allPastMeetings[] = $meetingEntry;
            if ($uuid) $pastUuidsMap[$uuid] = true;
        }
    }

    // Procesar reuniones de la Meeting API (Para detectar futuras y gaps en reportes)
    if (isset($rawMeetings['meetings']) && is_array($rawMeetings['meetings'])) {
        foreach ($rawMeetings['meetings'] as $m) {
            $mIdStr = (string)($m['id'] ?? '');
            $uuidCurrent = $m['uuid'] ?? '';
            $startTimeStr = $m['start_time'] ?? '';
            $startTS = $startTimeStr ? strtotime($startTimeStr) : 0;
            
            // Determinar si está en vivo AHORA
            $isLive = isset($liveMeetingsMap[$mIdStr]);
            
            if ($isLive) {
                $liveParticipants = ($liveMeetingsMap[$mIdStr]['participants'] ?? 0);
                $durationFormatted = $liveMeetingsMap[$mIdStr]['duration'] ?? '00:00:00';
                if (strlen($durationFormatted) === 5) $durationFormatted = "00:" . $durationFormatted;

                $allLiveMeetings[] = [
                    'reunion' => ($m['topic'] ?? 'Sin Tema'),
                    'reunion_id' => $mIdStr,
                    'inicio' => $startTimeStr,
                    'duracion' => $durationFormatted,
                    'fin' => '',
                    'participantes' => $liveParticipants,
                    'is_live' => true,
                    'grabado' => isset($recordingUuids[(string)$uuidCurrent]) || isset($recordingIdsFallback[$mIdStr]),
                    'uuid' => $uuidCurrent,
                    'type' => $m['type'] ?? 2
                ];
                continue;
            }

            if ($startTS > $now) {
                // FUTURA
                $allFutureMeetings[] = [
                    'reunion' => ($m['topic'] ?? 'Sin Tema'),
                    'reunion_id' => $mIdStr,
                    'inicio' => $startTimeStr,
                    'duracion' => sprintf('%02d:%02d:00', floor(($m['duration']??0)/60), ($m['duration']??0)%60),
                    'fin' => date('Y-m-d H:i:s', $startTS + (($m['duration']??0)*60)),
                    'participantes' => 0,
                    'grabado' => false,
                    'uuid' => $uuidCurrent,
                    'type' => $m['type'] ?? 2
                ];
            } else {
                // PASADA - Solo agregar si NO está ya en el reporte (evitar duplicados rotos)
                $alreadyReported = false;
                if ($uuidCurrent && isset($pastUuidsMap[$uuidCurrent])) $alreadyReported = true;
                
                if (!$alreadyReported) {
                    // Verificar si existe alguna instancia en allPastMeetings con este ID
                    foreach ($allPastMeetings as $pm) {
                        if ($pm['reunion_id'] === $mIdStr) {
                            $alreadyReported = true;
                            break;
                        }
                    }
                }

                if (!$alreadyReported) {
                    // Es un gap: reunión que pasó pero no salió en Report API
                    $durationMin = $m['duration'] ?? 0;
                    $endTS = $startTS + ($durationMin * 60);
                    
                    $participantsCount = 0;
                    if ($uuidCurrent) {
                        $pData = getMeetingParticipants($uuidCurrent);
                        $participantsCount = (isset($pData['participants']) && is_array($pData['participants'])) ? count($pData['participants']) : 0;
                    }

                    $recordingMatch = false;
                    if ($uuidCurrent && (
                        isset($recordingUuids[(string)$uuidCurrent]) || 
                        isset($recordingUuids[urlencode((string)$uuidCurrent)]) || 
                        isset($recordingUuids[urlencode(urlencode((string)$uuidCurrent))])
                    )) {
                        $recordingMatch = true;
                    } elseif (isset($recordingIdsFallback[$mIdStr])) {
                        $recordingMatch = true;
                    }

                    $allPastMeetings[] = [
                        'reunion' => ($m['topic'] ?? 'Sin Tema'),
                        'reunion_id' => $mIdStr,
                        'inicio' => $startTimeStr,
                        'duracion' => sprintf('%02d:%02d:00', floor($durationMin/60), $durationMin%60),
                        'fin' => date('Y-m-d H:i:s', $endTS),
                        'participantes' => $participantsCount,
                        'grabado' => $recordingMatch,
                        'uuid' => $uuidCurrent,
                        'type' => 1
                    ];
                }
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
