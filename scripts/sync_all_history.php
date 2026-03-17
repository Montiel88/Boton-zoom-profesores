<?php
// scripts/sync_all_history.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/zoom_api.php';
require_once __DIR__ . '/../includes/logger.php';

echo "🔍 Sincronizando TODO el historial desde 2020...\n";
set_time_limit(0);
ini_set('memory_limit', '512M');

$logger = new Logger();
$db = getDB();

// Obtener todos los usuarios
$users = getZoomUsers();
if (isset($users['error'])) {
    die("Error: " . $users['message']);
}

echo "✅ Usuarios encontrados: " . count($users) . "\n";

$totalMeetings = 0;
$totalParticipantes = 0;

foreach ($users as $user) {
    $userId = $user['id'];
    $email = $user['email'] ?? 'unknown';
    
    echo "\n👤 Procesando: $email\n";
    
    // Sincronizar por años para no saturar la API
    $startYear = 2020;
    $currentYear = (int)date('Y');
    
    for ($year = $startYear; $year <= $currentYear; $year++) {
        $from = "$year-01-01";
        $to = ($year == $currentYear) ? date('Y-m-d') : "$year-12-31";
        
        echo "   Año $year: $from a $to\n";
        
        try {
            $pastMeetings = getPastMeetings($userId, $from, $to);
            
            if (isset($pastMeetings['meetings']) && !empty($pastMeetings['meetings'])) {
                foreach ($pastMeetings['meetings'] as $meeting) {
                    if (saveMeetingToDatabase($meeting, $userId, $db)) {
                        $totalMeetings++;
                        echo "      ✅ Reunión: " . ($meeting['topic'] ?? 'Sin título') . "\n";
                        
                        // Sincronizar participantes
                        $participants = getMeetingParticipants($meeting['uuid'] ?? '');
                        if (isset($participants['participants'])) {
                            foreach ($participants['participants'] as $p) {
                                if (saveParticipantToDatabase($meeting['uuid'], $p, $db)) {
                                    $totalParticipantes++;
                                }
                            }
                        }
                    }
                }
            }
            
            // Pequeña pausa para no saturar API
            sleep(1);
            
        } catch (Exception $e) {
            echo "      ❌ Error: " . $e->getMessage() . "\n";
        }
    }
}

// Registrar grabaciones
echo "\n🔄 Verificando grabaciones...\n";
markRecordingsInDatabase($db);

echo "\n====================================\n";
echo "✅ SINCRONIZACIÓN COMPLETA FINALIZADA\n";
echo "   Reuniones: $totalMeetings\n";
echo "   Participantes: $totalParticipantes\n";
echo "====================================\n";

function saveMeetingToDatabase($meeting, $userId, $db) {
    $uuid = $meeting['uuid'] ?? '';
    $reunionId = $meeting['id'] ?? '';
    
    if (empty($uuid)) return false;
    
    // Verificar si ya existe
    $stmt = $db->prepare("SELECT id FROM reuniones_historicas WHERE reunion_uuid = ?");
    $stmt->execute([$uuid]);
    if ($stmt->fetch()) return false;
    
    $startTime = $meeting['start_time'] ?? null;
    $durationMin = floatval($meeting['duration'] ?? 0);
    $durationSeconds = round($durationMin * 60);
    $endTime = $startTime ? date('Y-m-d H:i:s', strtotime($startTime) + $durationSeconds) : null;
    
    $stmt = $db->prepare("INSERT INTO reuniones_historicas 
        (reunion_uuid, reunion_id, usuario_id, usuario_email, topic, start_time, end_time, duration, participants_count, raw_data) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $uuid, $reunionId, $userId, $meeting['email'] ?? null,
        $meeting['topic'] ?? 'Sin título', $startTime, $endTime,
        $durationSeconds, $meeting['participants_count'] ?? 0, json_encode($meeting)
    ]);
}

function saveParticipantToDatabase($meetingUuid, $participant, $db) {
    $stmt = $db->prepare("INSERT IGNORE INTO participantes_reunion 
        (reunion_uuid, user_id, user_name, user_email, join_time, leave_time, duration, raw_data) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    
    return $stmt->execute([
        $meetingUuid,
        $participant['user_id'] ?? null,
        $participant['name'] ?? 'Invitado',
        $participant['user_email'] ?? null,
        $participant['join_time'] ?? null,
        $participant['leave_time'] ?? null,
        $participant['duration'] ?? 0,
        json_encode($participant)
    ]);
}

function markRecordingsInDatabase($db) {
    // Obtener todas las reuniones que tienen grabación
    $users = getZoomUsers();
    foreach ($users as $user) {
        $recordings = getZoomRecordings($user['id']);
        if (isset($recordings['meetings'])) {
            foreach ($recordings['meetings'] as $rec) {
                $stmt = $db->prepare("UPDATE reuniones_historicas SET has_recording = TRUE WHERE reunion_id = ?");
                $stmt->execute([$rec['id']]);
            }
        }
    }
}
?>
