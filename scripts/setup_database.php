<?php
// setup_database.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

try {
    $db = getDB();
    echo "Conectado a la base de datos...\n";

    // Tabla para caché de Zoom
    $db->exec("CREATE TABLE IF NOT EXISTS zoom_cache (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cache_key VARCHAR(255) UNIQUE NOT NULL,
        content LONGTEXT NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabla 'zoom_cache' creada o ya existe.\n";

    // Tabla para logs de actividad
    $db->exec("CREATE TABLE IF NOT EXISTS logs_actividad (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT,
        accion VARCHAR(255) NOT NULL,
        detalle TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
    )");
    echo "Tabla 'logs_actividad' creada o ya existe.\n";

    // Tabla para logs de sincronización API
    $db->exec("CREATE TABLE IF NOT EXISTS logs_sincronizacion (
        id INT AUTO_INCREMENT PRIMARY KEY,
        endpoint VARCHAR(255) NOT NULL,
        status_code INT,
        response_time FLOAT,
        success BOOLEAN,
        error_message TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "Tabla 'logs_sincronizacion' creada o ya existe.\n";

    // Asegurar que la tabla usuarios tenga los campos necesarios para admin panel
    // (Ya vimos que tiene correo, password, rol, nombre_completo, activo)
    
    echo "Estructura de base de datos actualizada con éxito.\n";

} catch (Exception $e) {
    die("Error actualizando la base de datos: " . $e->getMessage() . "\n");
}
?>
