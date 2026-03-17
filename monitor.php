<?php
while (true) {
    system('cls');
    require_once "config/config.php";
    require_once "includes/functions.php";
    $db = getDB();
    $count = $db->query("SELECT COUNT(*) FROM reuniones_historicas")->fetchColumn();
    $min = $db->query("SELECT MIN(start_time) FROM reuniones_historicas")->fetchColumn();
    $max = $db->query("SELECT MAX(start_time) FROM reuniones_historicas")->fetchColumn();
    echo "====================================\n";
    echo "   MONITOR DE SINCRONIZACIÓN\n";
    echo "====================================\n";
    echo "Reuniones: $count\n";
    echo "Desde: $min\n";
    echo "Hasta: $max\n";
    echo "====================================\n";
    echo "Actualizado: " . date('H:i:s') . "\n";
    echo "====================================\n";
    sleep(5);
}
?>
