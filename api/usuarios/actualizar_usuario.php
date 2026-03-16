<?php
// api/usuarios/actualizar_estado.php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $estado = $_POST['activo'] ?? '';

    if ($id === '' || $estado === '') {
        echo json_encode(['error' => 'ID o estado faltante']);
        exit;
    }

    // No permitir desactivarse a sí mismo
    if ($id == $_SESSION['user_id']) {
        echo json_encode(['error' => 'No puedes desactivar tu propia cuenta']);
        exit;
    }

    $db = getDB();
    try {
        $stmt = $db->prepare("UPDATE usuarios SET activo = ? WHERE id = ?");
        $stmt->execute([$estado, $id]);

        $logger = new Logger();
        $logger->activity('Actualizar Estado Usuario', "ID: $id, Nuevo estado: " . ($estado ? 'Activo' : 'Inactivo'));

        echo json_encode(['status' => 'success', 'message' => 'Estado actualizado correctamente']);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error al actualizar estado: ' . $e->getMessage()]);
    }
}
?>
