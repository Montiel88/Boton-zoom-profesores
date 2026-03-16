<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$db = getDB();
$tables = ['usuarios', 'zoom_cache', 'logs_actividad', 'logs_sincronizacion'];
foreach ($tables as $table) {
    echo "<h2>Table: $table</h2>";
    $stmt = $db->query("DESCRIBE $table");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1'><tr>";
    if (!empty($rows)) {
        foreach (array_keys($rows[0]) as $key) echo "<th>$key</th>";
        echo "</tr>";
        foreach ($rows as $row) {
            echo "<tr>";
            foreach ($row as $val) echo "<td>$val</td>";
            echo "</tr>";
        }
    }
    echo "</table>";
}
?>
