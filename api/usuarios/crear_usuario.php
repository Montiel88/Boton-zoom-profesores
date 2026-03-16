<?php
// api/usuarios/crear.php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

require_once __DIR__ . '/../../includes/logger.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'USUARIO';

    if (empty($nombre) || empty($correo) || empty($password)) {
        http_response_code(400);
        echo json_encode(['error' => 'Faltan campos obligatorios']);
        exit;
    }

    try {
        $db = getDB();
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $db->prepare("INSERT INTO usuarios (nombre_completo, correo, password, rol, activo) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$nombre, $correo, $hash, $rol]);

        try {
            $logger = new Logger();
            $logger->activity('Crear Usuario', "Se creó el usuario: $correo");
        } catch (Exception $logEx) {
            error_log("Error al registrar actividad: " . $logEx->getMessage());
        }

        echo json_encode(['status' => 'success', 'message' => 'Usuario creado correctamente']);
    } catch (PDOException $e) {
        http_response_code(500);
        if ($e->getCode() == 23000) {
            echo json_encode(['error' => 'El correo ya está registrado']);
        } else {
            echo json_encode(['error' => 'Error al crear usuario: ' . $e->getMessage()]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Error interno: ' . $e->getMessage()]);
    }
}
?>
