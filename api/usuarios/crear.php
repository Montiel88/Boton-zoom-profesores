<?php
// api/usuarios/crear.php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';
    $rol = $_POST['rol'] ?? 'USUARIO';

    if (empty($nombre) || empty($correo) || empty($password)) {
        echo json_encode(['error' => 'Faltan campos obligatorios']);
        exit;
    }

    $db = getDB();
    $hash = password_hash($password, PASSWORD_DEFAULT);

    try {
        $stmt = $db->prepare("INSERT INTO usuarios (nombre_completo, correo, password, rol, activo) VALUES (?, ?, ?, ?, 1)");
        $stmt->execute([$nombre, $correo, $hash, $rol]);

        $logger = new Logger();
        $logger->activity('Crear Usuario', "Se creó el usuario: $correo");

        echo json_encode(['status' => 'success', 'message' => 'Usuario creado correctamente']);
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
            echo json_encode(['error' => 'El correo ya está registrado']);
        } else {
            echo json_encode(['error' => 'Error al crear usuario: ' . $e->getMessage()]);
        }
    }
}
?>
