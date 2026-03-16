<?php
define('SKIP_AUTH', true);
$_GET['query'] = '*';
error_reporting(E_ALL);
ini_set('display_errors', 1);
include 'api/search_professor.php';
?>
