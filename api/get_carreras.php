<?php
// api/get_carreras.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api_brightspace.php';
requireLogin();

header('Content-Type: application/json');

$carreras = getCarreras();
echo json_encode($carreras);
?>
