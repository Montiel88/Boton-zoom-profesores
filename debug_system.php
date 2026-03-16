<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/config.php';
require_once 'includes/functions.php';

echo "<h1>Debug System</h1>";

// 1. Check .env
echo "<h2>1. .env Check</h2>";
if (file_exists('.env')) {
    echo "✅ .env exists<br>";
} else {
    echo "❌ .env NOT found<br>";
}

// 2. Check Database
echo "<h2>2. Database Check</h2>";
try {
    $db = getDB();
    echo "✅ Database connection successful<br>";
    
    $tables = ['usuarios', 'zoom_cache', 'logs_actividad', 'logs_sincronizacion'];
    foreach ($tables as $table) {
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "✅ Table '$table' exists. Count: $count<br>";
        } catch (Exception $e) {
            echo "❌ Table '$table' ERROR: " . $e->getMessage() . "<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
}

// 3. Check Sessions
echo "<h2>3. Sessions Check</h2>";
$sessionPath = session_save_path();
echo "Session Save Path: $sessionPath<br>";
if (is_writable($sessionPath)) {
    echo "✅ Session path is writable<br>";
} else {
    echo "❌ Session path is NOT writable<br>";
}

echo "Session Status: " . session_status() . "<br>";
$_SESSION['test_val'] = 'hello';
if (isset($_SESSION['test_val'])) {
    echo "✅ Session variable set successfully<br>";
} else {
    echo "❌ Session variable NOT set<br>";
}

// 4. Check Zoom API
echo "<h2>4. Zoom API Check</h2>";
require_once 'includes/zoom_api.php';
$token = getZoomToken();
if (is_array($token) && isset($token['error'])) {
    echo "❌ Zoom Token Error: " . json_encode($token) . "<br>";
} else {
    echo "✅ Zoom Token obtained successfully<br>";
}

echo "<h2>5. API File Check</h2>";
$api_files = ['api/search_professor.php', 'api/get_professor_meetings.php'];
foreach ($api_files as $api_file) {
    if (file_exists($api_file)) {
        echo "✅ $api_file exists<br>";
        $output = shell_exec("php -l $api_file");
        echo "Syntax check: $output<br>";
    } else {
        echo "❌ $api_file NOT found<br>";
    }
}
?>
