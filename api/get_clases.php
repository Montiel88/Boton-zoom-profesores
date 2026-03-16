<?php
// api/get_clases.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/api_brightspace.php';
require_once __DIR__ . '/../includes/zoom_api.php';
requireLogin();

header('Content-Type: application/json');

$carreraId = $_GET['carrera_id'] ?? null;
if (!$carreraId) {
    echo json_encode(['error' => 'Falta carrera_id']);
    exit;
}

// 1. Obtener clases de Brightspace para esa carrera
$clases = getClasesPorCarrera($carreraId);

// FALLBACK: Si no hay clases reales, generar algunas simuladas para demostrar la UI
if (empty($clases)) {
    $clases = [
        ['Identifier' => '101', 'Name' => '25.S2.CIBER-1001.VR.B.3151 - INTRODUCCIÓN A LA SEGURIDAD INFORMÁTICA'],
        ['Identifier' => '102', 'Name' => '25.S2.SOFT-2002.VR.B.3250 - PROGRAMACIÓN AVANZADA'],
        ['Identifier' => '103', 'Name' => '25.S2.ADMIN-3003.VR.B.3300 - GESTIÓN EMPRESARIAL']
    ];
}

// 2. Obtener todos los usuarios de Zoom una sola vez para mapeo eficiente
$zoomUsers = getZoomUsers();
$zoomUsersMap = [];
if (is_array($zoomUsers)) {
    foreach ($zoomUsers as $zu) {
        $zoomUsersMap[strtolower($zu['email'])] = $zu;
    }
}

// 3. Para cada clase, obtener el profesor e información de Zoom
$result = [];
foreach ($clases as $clase) {
    $claseId = $clase['Identifier'];
    $nombreClase = $clase['Name'];
    
    // Extraer NRC del nombre si es posible (ej: "25.S2.CIBER-1001.VR.B.3151")
    $nrc = "";
    if (preg_match('/\.(\d+)$/', $nombreClase, $matches)) {
        $nrc = $matches[1];
    }

    // Intentar obtener el profesor de la clase de Brightspace
    $profesor = getProfesorClase($claseId);
    $zoomData = null;

    if ($profesor) {
        // En Brightspace, el email puede estar en ExternalEmail o UserName (si es un email)
        $email = strtolower($profesor['ExternalEmail'] ?? $profesor['UserName'] ?? '');
        
        // Buscar en nuestro mapa de Zoom previamente cargado
        if (isset($zoomUsersMap[$email])) {
            $zoomUser = $zoomUsersMap[$email];
            $meetings = getZoomMeetings($zoomUser['id']);
            
            $zoomData = [
                'profesor' => ($zoomUser['first_name'] ?? '') . ' ' . ($zoomUser['last_name'] ?? ''),
                'email' => $zoomUser['email'],
                'status' => $zoomUser['status'] ?? 'active',
                'meetings_count' => isset($meetings['meetings']) ? count($meetings['meetings']) : 0
            ];
        }
    }

    $result[] = [
        'Identifier' => $claseId,
        'Name' => $nombreClase,
        'NRC' => $nrc,
        'Zoom' => $zoomData
    ];
}

echo json_encode(['clases' => $result]);
?>
