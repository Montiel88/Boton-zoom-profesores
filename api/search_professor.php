<?php
// api/search_professor.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/zoom_api.php';

if (!defined('SKIP_AUTH')) {
    requireLogin();
}

header('Content-Type: application/json');

try {
    $query = strtolower($_GET['query'] ?? '');
    
    // 1. Obtener todos los usuarios de Zoom (usando el caché interno de getZoomUsers)
    $zoomUsers = getZoomUsers();
    if (isset($zoomUsers['error'])) {
        http_response_code(500);
        echo json_encode($zoomUsers);
        exit;
    }

    // 2. Filtrar por nombre o email
    $matchingProfessors = [];
    $stats = [
        'tesa' => 0,
        'itsa' => 0
    ];
    
    foreach ($zoomUsers as $user) {
        $firstName = $user['first_name'] ?? '';
        $lastName = $user['last_name'] ?? '';
        $email = $user['email'] ?? '';
        
        $fullName = strtolower($firstName . ' ' . $lastName);
        $searchEmail = strtolower($email);

        // Contadores para el dashboard global
        if (strpos($searchEmail, '@tesa.edu.ec') !== false) $stats['tesa']++;
        if (strpos($searchEmail, 'estud.itsa.edu.ec') !== false) $stats['itsa']++;
        
        if ($query === '*' || strpos($fullName, $query) !== false || strpos($searchEmail, $query) !== false) {
            $matchingProfessors[] = [
                'id' => $user['id'],
                'profesor' => trim($firstName . ' ' . $lastName) ?: 'Usuario Zoom',
                'email' => $email,
                'status' => $user['status'] ?? 'active',
                'pic' => $user['pic_url'] ?? '',
                'timezone' => $user['timezone'] ?? 'N/A'
            ];
        }
    }

    // 3. Obtener estadísticas globales adicionales (solo si query es *)
    $liveCount = 0;
    if ($query === '*') {
        $liveData = getLiveMeetings();
        if (isset($liveData['meetings'])) {
            $liveCount = count($liveData['meetings']);
        }
    }

    echo json_encode([
        'profesores' => $matchingProfessors,
        'domain_stats' => $stats,
        'live_count' => $liveCount
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
}
?>
