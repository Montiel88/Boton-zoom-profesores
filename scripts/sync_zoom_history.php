<?php
// scripts/sync_zoom_history.php
// Script para sincronizar todo el historial de reuniones de Zoom
// Ejecutar diariamente vía cron o tarea programada

// NO incluir cors.php - es solo para APIs
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/zoom_api.php';
require_once __DIR__ . '/../includes/logger.php';

set_time_limit(0); // Sin límite de tiempo
ini_set('memory_limit', '512M');

$logger = new Logger();
$db = getDB();

$logger->activity('SYNC', 'Iniciando sincronización histórica');

// Obtener todos los usuarios de Zoom
$users = getZoomUsers();
if (isset($users['error'])) {
    $logger->activity('SYNC ERROR', 'No se pudieron obtener usuarios: ' . $users['message']);
    exit(1);
}

$totalUsers = count($users);
$processedUsers = 0;
$totalMeetings = 0;

foreach ($users as $user) {
    $userId = $user['id'];
    $email = $user['email'] ?? 'unknown';
    
    $logger->activity('SYNC', "Procesando usuario: $email ($userId)");
    
    // Obtener última sincronización
    $stmt = $db->prepare("SELECT last_sync_to FROM sync_control WHERE usuario_id = ?");
    $stmt->execute([$userId]);
    $lastSync = $stmt->fetchColumn();
    
    // Determinar rango de fechas a sincronizar
    $to = date('Y-m-d');
    
    if ($lastSync) {
        // Sincronizar desde el día después de la última sincronización
        $from = date('Y-m-d', strtotime($lastSync . ' +1 day'));
        $logger->activity('SYNC', "Última sincronización: $lastSync, sincronizando desde $from");
    } else {
        // Primera sincronización: últimos 90 días
        $from = date('Y-m-d', strtotime('-90 days'));
        $logger->activity('SYNC', "Primera sincronización, desde $from");
    }
    
    // Actualizar estado a processing
    $stmt = $db->prepare("INSERT INTO sync_control (usuario_id, usuario_email, last_sync_from, last_sync_to, status) 
                          VALUES (?, ?, ?, ?, 'processing') 
                          ON DUPLICATE KEY UPDATE last_sync_from = ?, last_sync_to = ?, status = 'processing'");
    $stmt->execute([$userId, $email, $from, $to, $from, $to]);
    
    try {
        $meetingsCount = syncUserMeetings($userId, $from, $to, $db);
        $totalMeetings += $meetingsCount;
        
        // Actualizar estado a completed
        $stmt = $db->prepare("UPDATE sync_control SET status = 'completed', updated_at = NOW() WHERE usuario_id = ?");
        $stmt->execute([$userId]);
        
        $logger->activity('SYNC', "Usuario $email completado. Reuniones: $meetingsCount");
        
    } catch (Exception $e) {
        // Marcar como failed
        $stmt = $db->prepare("UPDATE sync_control SET status = 'failed', error_message = ?, updated_at = NOW() WHERE usuario_id = ?");
        $stmt->execute([$e->getMessage(), $userId]);
        
        $logger->activity('SYNC ERROR', "Error en usuario $email: " . $e->getMessage());
    }
    
    $processedUsers++;
    
    // Pequeña pausa para no saturar la API
    if ($processedUsers % 10 == 0) {
        sleep(2);
    }
}

// Registrar log final
$stmt = $db->prepare("INSERT INTO sync_logs (sync_type, records_processed, status, message) VALUES (?, ?, ?, ?)");
$stmt->execute(['historical', $totalMeetings, 'completed', "Usuarios procesados: $processedUsers/$totalUsers"]);

$logger->activity('SYNC COMPLETED', "Total usuarios: $processedUsers/$totalUsers, Total reuniones: $totalMeetings");

echo "Sincronización completada. Usuarios: $processedUsers/$totalUsers, Reuniones: $totalMeetings\n";

/**
 * Sincroniza las reuniones de un usuario en un rango de fechas
 */
function syncUserMeetings($userId, $from, $to, $db) {
    $meetingsCount = 0;
    
    // Obtener reuniones pasadas del período
    $pastMeetings = getPastMeetings($userId, $from, $to);
    
    if (!isset($pastMeetings['meetings']) || empty($pastMeetings['meetings'])) {
        return 0;
    }
    
    foreach ($pastMeetings['meetings'] as $meeting) {
        if (saveMeetingToDatabase($meeting, $userId, $db)) {
            $meetingsCount++;
        }
    }
    
    return $meetingsCount;
}

/**
 * Guarda una reunión en la base de datos
 */
function saveMeetingToDatabase($meeting, $userId, $db) {
    $uuid = $meeting['uuid'] ?? '';
    $reunionId = $meeting['id'] ?? '';
    
    if (empty($uuid)) {
        return false;
    }
    
    // Verificar si ya existe
    $stmt = $db->prepare("SELECT id FROM reuniones_historicas WHERE reunion_uuid = ?");
    $stmt->execute([$uuid]);
    
    if ($stmt->fetch()) {
        return false; // Ya existe
    }
    
    // Calcular duración y end_time exactos
    $startTime = $meeting['start_time'] ?? null;
    $endTime = $meeting['end_time'] ?? null;
    $durationSeconds = 0;

    if ($startTime && $endTime) {
        $startTS = strtotime($startTime);
        $endTS = strtotime($endTime);
        $durationSeconds = $endTS - $startTS;
    } else {
        // Fallback a la duración en minutos de Zoom
        $durationMin = floatval($meeting['duration'] ?? 0);
        $durationSeconds = round($durationMin * 60);
        if ($startTime && !$endTime) {
            $endTime = date('Y-m-d H:i:s', strtotime($startTime) + $durationSeconds);
        }
    }
    
    // Verificar si tiene grabación (simplificado)
    $hasRecording = false;
    
    // Guardar reunión
    $stmt = $db->prepare("INSERT INTO reuniones_historicas 
                          (reunion_uuid, reunion_id, usuario_id, usuario_email, topic, start_time, end_time, duration, participants_count, has_recording, raw_data) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    
    $result = $stmt->execute([
        $uuid,
        $reunionId,
        $userId,
        $meeting['email'] ?? null,
        $meeting['topic'] ?? 'Sin título',
        $startTime,
        $endTime,
        $durationSeconds,
        $meeting['participants_count'] ?? 0,
        $hasRecording,
        json_encode($meeting)
    ]);
    
    return $result;
}
?>
