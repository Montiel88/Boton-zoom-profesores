<?php
// scripts/sync_zoom_data.php
// Sincroniza datos desde Zoom API hacia MySQL (para CRON)
// Ejecución: php sync_zoom_data.php [--user=email@tesa.edu.ec] [--full]
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/zoom_api.php';
require_once __DIR__ . '/../includes/logger.php';

// Configuración
$syncStartTime = microtime(true);
$logger = new Logger();

// Parsear argumentos
$args = $argv ?? [];
$targetUser = null;
$fullSync = in_array('--full', $args);

foreach ($args as $arg) {
    if (strpos($arg, '--user=') === 0) {
        $targetUser = substr($arg, 7);
    }
}

echo "===========================================\n";
echo "🔄 ZOOM DATA SYNC - " . date('Y-m-d H:i:s') . "\n";
echo "===========================================\n\n";

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✅ Conectado a MySQL\n";
} catch (PDOException $e) {
    die("❌ Error MySQL: " . $e->getMessage() . "\n");
}

// Obtener configuración de caché
$stmt = $pdo->query("SELECT setting_key, setting_value FROM zoom_cache_settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$retentionDays = (int)($settings['retention_days'] ?? 365);
$fromDate = date('Y-m-d', strtotime("-{$retentionDays} days"));
$toDate = date('Y-m-d');

echo "📅 Rango: {$fromDate} a {$toDate}\n";
echo "📦 Retención: {$retentionDays} días\n\n";

// Obtener usuarios a sincronizar
if ($targetUser) {
    echo "🎯 Usuario específico: {$targetUser}\n";
    $usersToSync = [['id' => $targetUser, 'email' => $targetUser]];
} else {
    echo "🎯 Sincronizando TODOS los usuarios...\n";
    
    // Obtener usuarios de Zoom
    $zoomUsers = getZoomUsers();
    if (isset($zoomUsers['error'])) {
        die("❌ Error obteniendo usuarios: " . $zoomUsers['message'] . "\n");
    }
    
    $usersToSync = [];
    foreach ($zoomUsers as $user) {
        $usersToSync[] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?? '',
            'display_name' => $user['display_name'] ?? '',
            'status' => $user['status'] ?? 'active',
            'type' => $user['type'] ?? 'basic',
            'role' => $user['role'] ?? 'user',
            'timezone' => $user['timezone'] ?? 'America/Guayaquil',
            'verified' => $user['verified'] ?? 0
        ];
    }
    
    echo "👥 Usuarios encontrados: " . count($usersToSync) . "\n\n";
}

$totalMeetings = 0;
$totalParticipants = 0;
$totalRecordings = 0;
$syncedUsers = 0;

foreach ($usersToSync as $user) {
    $userId = $user['id'];
    $userEmail = $user['email'];
    
    echo "-------------------------------------------\n";
    echo "📧 Usuario: {$userEmail}\n";
    echo "-------------------------------------------\n";
    
    // 1. Sincronizar usuario
    try {
        $stmt = $pdo->prepare("
            INSERT INTO zoom_users_cache 
            (user_id, email, first_name, last_name, display_name, status, type, role, timezone, verified, raw_data)
            VALUES (:user_id, :email, :first_name, :last_name, :display_name, :status, :type, :role, :timezone, :verified, :raw_data)
            ON DUPLICATE KEY UPDATE
                email = :email,
                first_name = :first_name,
                last_name = :last_name,
                display_name = :display_name,
                status = :status,
                type = :type,
                role = :role,
                timezone = :timezone,
                verified = :verified,
                raw_data = :raw_data,
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            ':user_id' => $userId,
            ':email' => $userEmail,
            ':first_name' => $user['first_name'] ?? null,
            ':last_name' => $user['last_name'] ?? null,
            ':display_name' => $user['display_name'] ?? null,
            ':status' => $user['status'] ?? null,
            ':type' => $user['type'] ?? null,
            ':role' => $user['role'] ?? null,
            ':timezone' => $user['timezone'] ?? null,
            ':verified' => $user['verified'] ?? 0,
            ':raw_data' => json_encode($user, JSON_UNESCAPED_UNICODE)
        ]);
        
        echo "  ✅ Usuario sincronizado\n";
        $syncedUsers++;
    } catch (PDOException $e) {
        echo "  ❌ Error usuario: " . $e->getMessage() . "\n";
        continue;
    }
    
    // 2. Sincronizar reuniones (Report API)
    echo "  📊 Obteniendo reuniones...\n";
    $meetingsData = getPastMeetings($userId, $fromDate, $toDate);
    
    if (isset($meetingsData['meetings']) && is_array($meetingsData['meetings'])) {
        $meetingsCount = 0;
        
        foreach ($meetingsData['meetings'] as $meeting) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO zoom_meetings_cache 
                    (meeting_id, uuid, user_id, topic, start_time, end_time, duration_minutes, 
                     participants_count, has_recording, meeting_type, status, raw_data)
                    VALUES (:meeting_id, :uuid, :user_id, :topic, :start_time, :end_time, :duration_minutes,
                            :participants_count, :has_recording, :meeting_type, :status, :raw_data)
                    ON DUPLICATE KEY UPDATE
                        uuid = :uuid,
                        topic = :topic,
                        start_time = :start_time,
                        end_time = :end_time,
                        duration_minutes = :duration_minutes,
                        participants_count = :participants_count,
                        has_recording = :has_recording,
                        meeting_type = :meeting_type,
                        status = :status,
                        raw_data = :raw_data,
                        updated_at = CURRENT_TIMESTAMP
                ");
                
                $startTime = isset($meeting['start_time']) ? date('Y-m-d H:i:s', strtotime($meeting['start_time'])) : null;
                $endTime = isset($meeting['end_time']) ? date('Y-m-d H:i:s', strtotime($meeting['end_time'])) : null;
                
                $stmt->execute([
                    ':meeting_id' => $meeting['id'] ?? '',
                    ':uuid' => $meeting['uuid'] ?? '',
                    ':user_id' => $userId,
                    ':topic' => $meeting['topic'] ?? '',
                    ':start_time' => $startTime,
                    ':end_time' => $endTime,
                    ':duration_minutes' => $meeting['duration'] ?? 0,
                    ':participants_count' => $meeting['participants_count'] ?? 0,
                    ':has_recording' => 0, // Se actualizará después con grabaciones
                    ':meeting_type' => $meeting['type'] ?? null,
                    ':status' => 'completed',
                    ':raw_data' => json_encode($meeting, JSON_UNESCAPED_UNICODE)
                ]);
                
                $meetingsCount++;
            } catch (PDOException $e) {
                echo "    ❌ Error reunión {$meeting['id']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "  ✅ Reuniones sincronizadas: {$meetingsCount}\n";
        $totalMeetings += $meetingsCount;
    }
    
    // 3. Sincronizar grabaciones
    echo "  🎥 Obteniendo grabaciones...\n";
    $recordingsData = getZoomRecordings($userId, $fromDate, $toDate);
    
    if (isset($recordingsData['meetings']) && is_array($recordingsData['meetings'])) {
        $recordingsCount = 0;
        
        foreach ($recordingsData['meetings'] as $recording) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO zoom_recordings_cache 
                    (meeting_id, uuid, user_id, topic, start_time, recording_count, total_size, 
                     recording_files, raw_data)
                    VALUES (:meeting_id, :uuid, :user_id, :topic, :start_time, :recording_count, :total_size,
                            :recording_files, :raw_data)
                    ON DUPLICATE KEY UPDATE
                        topic = :topic,
                        start_time = :start_time,
                        recording_count = :recording_count,
                        total_size = :total_size,
                        recording_files = :recording_files,
                        raw_data = :raw_data,
                        updated_at = CURRENT_TIMESTAMP
                ");
                
                $startTime = isset($recording['start_time']) ? date('Y-m-d H:i:s', strtotime($recording['start_time'])) : null;
                $recordingFiles = $recording['recording_files'] ?? [];
                $totalSize = 0;
                
                foreach ($recordingFiles as $file) {
                    $totalSize += $file['file_size'] ?? 0;
                }
                
                // Actualizar has_recording en meetings_cache
                $updateStmt = $pdo->prepare("
                    UPDATE zoom_meetings_cache 
                    SET has_recording = 1 
                    WHERE meeting_id = :meeting_id AND user_id = :user_id
                ");
                $updateStmt->execute([
                    ':meeting_id' => $recording['id'] ?? '',
                    ':user_id' => $userId
                ]);
                
                $stmt->execute([
                    ':meeting_id' => $recording['id'] ?? '',
                    ':uuid' => $recording['uuid'] ?? '',
                    ':user_id' => $userId,
                    ':topic' => $recording['topic'] ?? '',
                    ':start_time' => $startTime,
                    ':recording_count' => count($recordingFiles),
                    ':total_size' => $totalSize,
                    ':recording_files' => json_encode($recordingFiles, JSON_UNESCAPED_UNICODE),
                    ':raw_data' => json_encode($recording, JSON_UNESCAPED_UNICODE)
                ]);
                
                $recordingsCount++;
            } catch (PDOException $e) {
                echo "    ❌ Error grabación {$recording['id']}: " . $e->getMessage() . "\n";
            }
        }
        
        echo "  ✅ Grabaciones sincronizadas: {$recordingsCount}\n";
        $totalRecordings += $recordingsCount;
    }
    
    // 4. Obtener participantes de reuniones recientes (últimos 30 días para no saturar)
    echo "  👥 Obteniendo participantes (últimos 30 días)...\n";
    $recentMeetingsStmt = $pdo->prepare("
        SELECT meeting_id, uuid FROM zoom_meetings_cache 
        WHERE user_id = :user_id 
        AND start_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        AND participants_count = 0
        LIMIT 100
    ");
    $recentMeetingsStmt->execute([':user_id' => $userId]);
    $recentMeetings = $recentMeetingsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $participantsCount = 0;
    foreach ($recentMeetings as $meeting) {
        try {
            $participantsData = getMeetingParticipants($meeting['uuid']);
            
            if (isset($participantsData['participants']) && is_array($participantsData['participants'])) {
                foreach ($participantsData['participants'] as $participant) {
                    $pStmt = $pdo->prepare("
                        INSERT INTO zoom_participants_cache 
                        (meeting_id, user_id, name, email, join_time, leave_time, duration_seconds, raw_data)
                        VALUES (:meeting_id, :user_id, :name, :email, :join_time, :leave_time, :duration_seconds, :raw_data)
                    ");
                    
                    $pStmt->execute([
                        ':meeting_id' => $meeting['meeting_id'],
                        ':user_id' => $userId,
                        ':name' => $participant['name'] ?? '',
                        ':email' => $participant['user_email'] ?? '',
                        ':join_time' => isset($participant['join_time']) ? date('Y-m-d H:i:s', strtotime($participant['join_time'])) : null,
                        ':leave_time' => isset($participant['leave_time']) ? date('Y-m-d H:i:s', strtotime($participant['leave_time'])) : null,
                        ':duration_seconds' => $participant['duration'] ?? 0,
                        ':raw_data' => json_encode($participant, JSON_UNESCAPED_UNICODE)
                    ]);
                    
                    $participantsCount++;
                }
                
                // Actualizar participants_count en meetings_cache
                $updateStmt = $pdo->prepare("
                    UPDATE zoom_meetings_cache 
                    SET participants_count = :count 
                    WHERE meeting_id = :meeting_id AND user_id = :user_id
                ");
                $updateStmt->execute([
                    ':count' => count($participantsData['participants']),
                    ':meeting_id' => $meeting['meeting_id'],
                    ':user_id' => $userId
                ]);
            }
        } catch (Exception $e) {
            // Ignorar errores de participantes individuales
        }
    }
    
    echo "  ✅ Participantes sincronizados: {$participantsCount}\n";
    $totalParticipants += $participantsCount;
    
    echo "\n";
}

// Registrar log de sincronización
$syncDuration = microtime(true) - $syncStartTime;
$logStmt = $pdo->prepare("
    INSERT INTO zoom_sync_log 
    (sync_type, user_id, meetings_synced, participants_synced, recordings_synced, 
     status, started_at, completed_at, duration_seconds)
    VALUES ('auto', :user_id, :meetings, :participants, :recordings, 
            'success', :started, :completed, :duration)
");

$logStmt->execute([
    ':user_id' => $targetUser ?: 'all',
    ':meetings' => $totalMeetings,
    ':participants' => $totalParticipants,
    ':recordings' => $totalRecordings,
    ':started' => date('Y-m-d H:i:s', $syncStartTime),
    ':completed' => date('Y-m-d H:i:s'),
    ':duration' => round($syncDuration, 2)
]);

// Actualizar última sincronización completa
if ($fullSync) {
    $pdo->exec("UPDATE zoom_cache_settings SET setting_value = NOW() WHERE setting_key = 'last_full_sync'");
}

echo "===========================================\n";
echo "✅ SINCRONIZACIÓN COMPLETADA\n";
echo "===========================================\n\n";

echo "📊 RESUMEN:\n";
echo "  - Usuarios sincronizados: {$syncedUsers}\n";
echo "  - Reuniones sincronizadas: {$totalMeetings}\n";
echo "  - Participantes sincronizados: {$totalParticipants}\n";
echo "  - Grabaciones sincronizadas: {$totalRecordings}\n";
echo "  - Tiempo total: " . round($syncDuration, 2) . " segundos\n\n";

echo "📅 PRÓXIMA SINCRONIZACIÓN:\n";
$nextSync = date('Y-m-d H:i:s', strtotime("+6 hours"));
echo "  - Programada: {$nextSync}\n\n";

echo "💡 Para ejecutar manualmente:\n";
echo "  php sync_zoom_data.php --user=email@tesa.edu.ec\n";
echo "  php sync_zoom_data.php --full\n\n";

?>
