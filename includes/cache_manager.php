<?php
// includes/cache_manager.php
require_once __DIR__ . '/functions.php';

class CacheManager {
    private $db;

    public function __construct() {
        $this->db = getDB();
    }

    /**
     * Obtener contenido desde el caché
     * @param string $key Clave única para el caché
     * @return mixed Contenido decodificado o null si no existe/expiró
     */
    public function get($key) {
        $stmt = $this->db->prepare("SELECT content FROM zoom_cache WHERE cache_key = ? AND expires_at > NOW()");
        $stmt->execute([$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            return json_decode($row['content'], true);
        }
        return null;
    }

    /**
     * Guardar contenido en el caché
     * @param string $key Clave única para el caché
     * @param mixed $content Contenido a guardar (se codificará a JSON)
     * @param int $ttl Tiempo de vida en segundos (default 1 hora)
     */
    public function set($key, $content, $ttl = 3600) {
        $expires_at = date('Y-m-d H:i:s', time() + $ttl);
        $json_content = json_encode($content);

        $stmt = $this->db->prepare("INSERT INTO zoom_cache (cache_key, content, expires_at) 
                                    VALUES (?, ?, ?) 
                                    ON DUPLICATE KEY UPDATE content = VALUES(content), expires_at = VALUES(expires_at), created_at = CURRENT_TIMESTAMP");
        return $stmt->execute([$key, $json_content, $expires_at]);
    }

    /**
     * Eliminar una clave del caché
     */
    public function delete($key) {
        $stmt = $this->db->prepare("DELETE FROM zoom_cache WHERE cache_key = ?");
        return $stmt->execute([$key]);
    }

    /**
     * Limpiar todo el caché (forzado)
     */
    public function clearAll() {
        $stmt = $this->db->prepare("DELETE FROM zoom_cache");
        return $stmt->execute();
    }

    /**
     * Limpiar todo el caché expirado
     */
    public function clearExpired() {
        $stmt = $this->db->prepare("DELETE FROM zoom_cache WHERE expires_at <= NOW()");
        return $stmt->execute();
    }
}
?>
