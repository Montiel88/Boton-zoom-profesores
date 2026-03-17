<?php
// api/get_professor_meetings_cached.php
// Obtiene datos desde la caché MySQL (RÁPIDO) en lugar de Zoom API (LENTO)
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de conexión a base de datos']);
    exit;
}

$userId = $_GET['userId'] ?? '';
$from = $_GET['from'] ?? date('Y-m-d', strtotime('-365 days'));
$to = $_GET['to'] ?? date('Y-m-d');

if (empty($userId)) {
    echo json_encode(['error' => 'Falta el ID del usuario']);
    exit;
}

try {
    $now = time();
    
    // Obtener reuniones desde caché MySQL
    $stmt = $pdo->prepare("
        SELECT * FROM zoom_meetings_cache 
        WHERE user_id = :user_id 
        AND DATE(start_time) BETWEEN :from AND :to
        ORDER BY start_time DESC
    ");
    $stmt->execute([
        ':user_id' => $userId,
        ':from' => $from,
        ':to' => $to
    ]);
    
    $meetings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $allPastMeetings = [];
    $allLiveMeetings = [];
    $allFutureMeetings = [];
    
    foreach ($meetings as $m) {
        $startTimeStr = $m['start_time'];
        $endTimeStr = $m['end_time'];
        $startTS = strtotime($startTimeStr);
        $endTS = $endTimeStr ? strtotime($endTimeStr) : 0;
        
        // Calcular duración real
        $durationSeconds = 0;
        if ($startTS && $endTS) {
            $durationSeconds = $endTS - $startTS;
        } else {
            $durationSeconds = ($m['duration_minutes'] ?? 0) * 60;
        }
        
        $h = floor($durationSeconds / 3600);
        $i = floor(($durationSeconds % 3600) / 60);
        $s = $durationSeconds % 60;
        $durationFormatted = sprintf('%02d:%02d:%02d', $h, $i, $s);
        
        $meetingData = [
            'reunion' => ($m['topic'] ?? 'Sin Tema'),
            'reunion_id' => $m['meeting_id'] ?? 'N/A',
            'inicio' => $startTimeStr,
            'fin' => $endTimeStr,
            'duracion' => $durationFormatted,
            'participantes' => $m['participants_count'] ?? 0,
            'grabado' => (bool)($m['has_recording'] ?? false),
            'uuid' => $m['uuid'] ?? '',
            'type' => 1, // Pasada
            'is_live' => false
        ];
        
        // Clasificar por estado
        if ($startTS > $now) {
            // Futura
            $meetingData['type'] = 2;
            $meetingData['participantes'] = 0;
            $meetingData['grabado'] = false; // Futura = sin grabación
            $allFutureMeetings[] = $meetingData;
        } else {
            // Pasada
            $allPastMeetings[] = $meetingData;
        }
    }
    
    // Ordenar
    $sortFn = function($a, $b) { 
        return strtotime($b['inicio']) - strtotime($a['inicio']); 
    };
    usort($allPastMeetings, $sortFn);
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
        ],
        'from_cache' => true,
        'cache_time' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Error de base de datos: ' . $e->getMessage()]);
}
?>
