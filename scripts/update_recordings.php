<?php
// scripts/update_recordings.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/zoom_api.php';

echo "====================================\n";
echo "  ACTUALIZANDO GRABACIONES\n";
echo "====================================\n";

$db = getDB();
$users = getZoomUsers();

if (isset($users['error'])) {
    die("Error: " . $users['message']);
}

$totalActualizadas = 0;
$totalUsuarios = count($users);

foreach ($users as $index => $user) {
    $email = $user['email'] ?? 'unknown';
    echo "\n[" . ($index+1) . "/$totalUsuarios] Procesando: $email\n";
    
    try {
        $recordings = getZoomRecordings($user['id']);
        
        if (isset($recordings['meetings']) && is_array($recordings['meetings'])) {
            $countUser = 0;
            foreach ($recordings['meetings'] as $rec) {
                $meetingId = $rec['id'] ?? '';
                $topic = $rec['topic'] ?? 'Sin título';
                
                if ($meetingId) {
                    $stmt = $db->prepare("UPDATE reuniones_historicas SET has_recording = 1 WHERE reunion_id = ?");
                    $stmt->execute([$meetingId]);
                    $affected = $stmt->rowCount();
                    
                    if ($affected > 0) {
                        $countUser++;
                        echo "   ✅ Grabación encontrada: $topic (ID: $meetingId)\n";
                    }
                }
            }
            $totalActualizadas += $countUser;
            echo "   → $countUser grabaciones actualizadas para este usuario\n";
        } else {
            echo "   → No hay grabaciones para este usuario\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
    
    // Pausa para no saturar la API
    if ($index % 10 == 0) {
        sleep(1);
    }
}

echo "\n====================================\n";
echo "✅ ACTUALIZACIÓN COMPLETADA\n";
echo "   Total grabaciones marcadas: $totalActualizadas\n";
echo "====================================\n";

// Verificar resultado final
$total = $db->query("SELECT COUNT(*) FROM reuniones_historicas")->fetchColumn();
$conGrabacion = $db->query("SELECT COUNT(*) FROM reuniones_historicas WHERE has_recording = 1")->fetchColumn();
echo "\n📊 ESTADO FINAL:\n";
echo "   Reuniones totales: $total\n";
echo "   Con grabación: $conGrabacion\n";
?>
