<?php
// test_new_api.php
define('SKIP_AUTH', true);
$_GET['query'] = 'rodrigo';
ob_start();
include 'api/search_professor.php';
$output = ob_get_clean();

$data = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Error: JSON inválido\nSalida: " . $output);
}

echo "Resultados: " . count($data['reuniones'] ?? []) . "\n";
if (isset($data['reuniones'][0])) {
    echo "Primer registro:\n";
    print_r($data['reuniones'][0]);
}
?>
