<?php
// simple_check.php
require_once 'config/config.php';
require_once 'includes/functions.php';

echo "=========================================\n";
echo "    VERIFICACIÓN RÁPIDA DEL SISTEMA     \n";
echo "=========================================\n\n";

// 1. Verificar conexión BD
echo "📁 BASE DE DATOS:\n";
try {
    $db = getDB();
    echo "✅ Conexión exitosa\n";
    
    // Contar reuniones
    $count = $db->query("SELECT COUNT(*) FROM reuniones_historicas")->fetchColumn();
    echo "   Reuniones en BD: $count\n";
    
    // Contar profesores con datos
    $users = $db->query("SELECT COUNT(DISTINCT usuario_id) FROM reuniones_historicas")->fetchColumn();
    echo "   Profesores con datos: $users\n";
    
    // Rango de fechas
    $min = $db->query("SELECT MIN(start_time) FROM reuniones_historicas")->fetchColumn();
    $max = $db->query("SELECT MAX(start_time) FROM reuniones_historicas")->fetchColumn();
    echo "   Rango fechas: " . ($min ?: 'N/A') . " a " . ($max ?: 'N/A') . "\n";
    
} catch (Exception $e) {
    echo "❌ Error BD: " . $e->getMessage() . "\n";
}

// 2. Verificar archivos
echo "\n📁 ARCHIVOS:\n";
$archivos = [
    'scripts/sync_zoom_history.php',
    'api/webhook.php',
    '.env',
    'api/get_professor_meetings.php'
];

foreach ($archivos as $archivo) {
    if (file_exists($archivo)) {
        echo "✅ $archivo\n";
    } else {
        echo "❌ $archivo\n";
    }
}

// 3. Verificar últimas sincronizaciones
echo "\n📊 ÚLTIMAS SINCRONIZACIONES:\n";
try {
    $logs = $db->query("SELECT * FROM sync_logs ORDER BY created_at DESC LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($logs)) {
        echo "   No hay registros de sincronización\n";
    } else {
        foreach ($logs as $log) {
            echo "   " . $log['created_at'] . " - " . ($log['message'] ?? 'Sin mensaje') . "\n";
        }
    }
} catch (Exception $e) {
    echo "   No se pudieron leer logs\n";
}

echo "\n=========================================\n";
?>
