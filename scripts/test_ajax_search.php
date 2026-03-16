<?php
// test_ajax_search.php
define('SKIP_AUTH', true); // Flag para saltar auth.php
$_GET['query'] = 'Anabel';
ob_start();
include 'api/search_professor.php';
$output = ob_get_clean();

$data = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: JSON inválido - " . json_last_error_msg() . "\nSalida: " . substr($output, 0, 500));
}

if (isset($data['error'])) {
    die("Error en la API: " . $data['error']);
}

echo "Búsqueda exitosa. Resultados encontrados: " . count($data['profesores'] ?? []) . "\n";
if (isset($data['profesores']) && count($data['profesores']) > 0) {
    echo "Primer resultado: " . $data['profesores'][0]['profesor'] . " (" . $data['profesores'][0]['email'] . ")\n";
}
?>
