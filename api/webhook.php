<?php
// api/webhook.php
// Endpoint para recibir webhooks de Zoom

require_once __DIR__ . '/../includes/cors.php';
require_once __DIR__ . '/../includes/functions.php';

// No requiere autenticación - Zoom llama directamente
header('Content-Type: application/json');

// Obtener headers y payload
$headers = getallheaders();
$signature = $headers['X-Zm-Signature'] ?? '';
$timestamp = $headers['X-Zm-Request-Timestamp'] ?? '';
$payload = file_get_contents('php://input');

// Verificar firma (seguridad)
$secret = getenv('ZOOM_WEBHOOK_SECRET') ?: '';
if (!verifyZoomWebhook($signature, $timestamp, $payload, $secret)) {
    error_log("Webhook: Firma inválida");
    http_response_code(403);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

$data = json_decode($payload, true);
$event = $data['event'] ?? '';
$eventData = $data['payload']['object'] ?? [];

// Log para depuración
error_log("Webhook recibido: $event");

// Conectar a la base de datos
$db = getDB();

// Procesar según el tipo de evento
switch ($event) {
    case 'meeting.participant_joined':
        handleParticipantJoined($eventData, $db);
        break;
        
    case 'meeting.participant_left':
        handleParticipantLeft($eventData, $db);
        break;
        
    case 'meeting.started':
        handleMeetingStarted($eventData, $db);
        break;
        
    case 'meeting.ended':
        handleMeetingEnded($eventData, $db);
        break;
        
    case 'recording.completed':
        handleRecordingCompleted($eventData, $db);
        break;
        
    case 'meeting.created':
    case 'meeting.updated':
    case 'meeting.deleted':
        // Estos eventos pueden ignorarse o usarse para cache
        break;
        
    default:
        error_log("Webhook no manejado: $event");
}

// Responder rápidamente (Zoom espera respuesta en <3 segundos)
http_response_code(200);
echo json_encode(['status' => 'ok']);

/**
 * Verifica la firma del webhook
 */
function verifyZoomWebhook($signature, $timestamp, $payload, $secret) {
    if (empty($secret) || empty($signature) || empty($timestamp)) {
        return false;
    }
    
    $message = "v0:$timestamp:$payload";
    $hash = hash_hmac('sha256', $message, $secret);
    $expected = "v0=$hash";
    
    return hash_equals($expected, $signature);
}

/**
 * Maneja evento de participante que se une
 */
function handleParticipantJoined($data, $db) {
    $meetingUuid = $data['uuid'] ?? '';
    $participant = $data['participant'] ?? [];
    
    if (empty($meetingUuid) || empty($participant)) {
        return;
    }
    
    $userId = $participant['user_id'] ?? '';
    $userName = $participant['user_name'] ?? 'Invitado';
    $userEmail = $participant['email'] ?? '';
    $joinTime = $participant['join_time'] ?? date('Y-m-d H:i:s');
    
    // Verificar si ya existe un registro para este participante en esta reunión
    $stmt = $db->prepare("SELECT id FROM participantes_reunion 
                          WHERE reunion_uuid = ? AND user_id = ? AND join_time IS NOT NULL AND leave_time IS NULL");
    $stmt->execute([$meetingUuid, $userId]);
    
    if (!$stmt->fetch()) {
        // Crear nuevo registro
        $stmt = $db->prepare("INSERT INTO participantes_reunion 
                              (reunion_uuid, user_id, user_name, user_email, join_time) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$meetingUuid, $userId, $userName, $userEmail, $joinTime]);
    }
}

/**
 * Maneja evento de participante que sale
 */
function handleParticipantLeft($data, $db) {
    $meetingUuid = $data['uuid'] ?? '';
    $participant = $data['participant'] ?? [];
    
    if (empty($meetingUuid) || empty($participant)) {
        return;
    }
    
    $userId = $participant['user_id'] ?? '';
    $leaveTime = $participant['leave_time'] ?? date('Y-m-d H:i:s');
    
    // Buscar el registro de entrada
    $stmt = $db->prepare("SELECT id, join_time FROM participantes_reunion 
                          WHERE reunion_uuid = ? AND user_id = ? AND leave_time IS NULL 
                          ORDER BY join_time DESC LIMIT 1");
    $stmt->execute([$meetingUuid, $userId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($record) {
        $joinTime = $record['join_time'];
        $duration = strtotime($leaveTime) - strtotime($joinTime);
        
        // Actualizar con hora de salida y duración
        $stmt = $db->prepare("UPDATE participantes_reunion 
                              SET leave_time = ?, duration = ? 
                              WHERE id = ?");
        $stmt->execute([$leaveTime, $duration, $record['id']]);
    }
}

/**
 * Maneja evento de reunión iniciada
 */
function handleMeetingStarted($data, $db) {
    $meetingUuid = $data['uuid'] ?? '';
    $meetingId = $data['id'] ?? '';
    $hostId = $data['host_id'] ?? '';
    $topic = $data['topic'] ?? 'Sin título';
    $startTime = $data['start_time'] ?? date('Y-m-d H:i:s');
    
    if (empty($meetingUuid)) {
        return;
    }
    
    // Verificar si ya existe
    $stmt = $db->prepare("SELECT id FROM reuniones_historicas WHERE reunion_uuid = ?");
    $stmt->execute([$meetingUuid]);
    
    if (!$stmt->fetch()) {
        // Crear registro de reunión
        $stmt = $db->prepare("INSERT INTO reuniones_historicas 
                              (reunion_uuid, reunion_id, usuario_id, topic, start_time) 
                              VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$meetingUuid, $meetingId, $hostId, $topic, $startTime]);
    }
}

/**
 * Maneja evento de reunión finalizada
 */
function handleMeetingEnded($data, $db) {
    $meetingUuid = $data['uuid'] ?? '';
    $endTime = $data['end_time'] ?? date('Y-m-d H:i:s');
    $duration = $data['duration'] ?? 0; // minutos
    
    if (empty($meetingUuid)) {
        return;
    }
    
    // Actualizar reunión
    $stmt = $db->prepare("UPDATE reuniones_historicas 
                          SET end_time = ?, duration = ? 
                          WHERE reunion_uuid = ?");
    $stmt->execute([$endTime, $duration * 60, $meetingUuid]);
}

/**
 * Maneja evento de grabación completada
 */
function handleRecordingCompleted($data, $db) {
    $meetingId = $data['id'] ?? '';
    
    if (empty($meetingId)) {
        return;
    }
    
    // Marcar reunión como grabada
    $stmt = $db->prepare("UPDATE reuniones_historicas SET has_recording = TRUE WHERE reunion_id = ?");
    $stmt->execute([$meetingId]);
}
?>
