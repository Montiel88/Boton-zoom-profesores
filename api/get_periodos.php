<?php
// api/get_periodos.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api_brightspace.php';
requireLogin();

header('Content-Type: application/json');

$periodos = getPeriodos();
echo json_encode($periodos);
?>