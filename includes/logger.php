<?php
// includes/logger.php
require_once __DIR__ . '/functions.php';

class Logger {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Log de actividad de usuario
     */
    public function activity($accion, $detalle = '') {
        $usuario_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $stmt = $this->db->prepare("INSERT INTO logs_actividad (usuario_id, accion, detalle, ip_address) 
                                    VALUES (?, ?, ?, ?)");
        return $stmt->execute([$usuario_id, $accion, $detalle, $ip]);
    }

    /**
     * Log de sincronización con la API de Zoom
     */
    public function sync($endpoint, $status_code, $response_time, $success, $error_message = null) {
        // Redondear tiempo de respuesta para ahorrar espacio y evitar números infinitos
        $response_time = round($response_time, 4);
        
        $stmt = $this->db->prepare("INSERT INTO logs_sincronizacion (endpoint, status_code, response_time, success, error_message) 
                                    VALUES (?, ?, ?, ?, ?)");
        $result = $stmt->execute([$endpoint, $status_code, $response_time, $success ? 1 : 0, $error_message]);
        
        // Limpieza automática preventiva: mantener solo los últimos 100 logs de sincronización
        // para evitar que la tabla crezca infinitamente
        $this->db->exec("DELETE FROM logs_sincronizacion WHERE id NOT IN (
            SELECT id FROM (
                SELECT id FROM logs_sincronizacion ORDER BY created_at DESC LIMIT 100
            ) as tmp
        )");
        
        return $result;
    }

    /**
     * Limpiar logs antiguos (más de 30 días)
     */
    public function cleanup($days = 30) {
        $stmt = $this->db->prepare("DELETE FROM logs_actividad WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
        
        $stmt = $this->db->prepare("DELETE FROM logs_sincronizacion WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$days]);
    }
}
?>
