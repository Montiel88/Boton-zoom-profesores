<?php
// api/usuarios/eliminar.php
require_once __DIR__ . '/../../includes/auth.php';
requireAdmin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';

    if (empty($id)) {
        echo json_encode(['error' => 'Falta ID de usuario']);
        exit;
    }

    // No permitir eliminarse a sí mismo
    if ($id == $_SESSION['user_id']) {
        echo json_encode(['error' => 'No puedes eliminar tu propia cuenta']);
        exit;
    }

    $db = getDB();
    try {
        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);

        $logger = new Logger();
        $logger->activity('Eliminar Usuario', "ID: $id");

        echo json_encode(['status' => 'success', 'message' => 'Usuario eliminado correctamente']);
    } catch (PDOException $e) {
        echo json_encode(['error' => 'Error al eliminar usuario: ' . $e->getMessage()]);
    }
}
?>
