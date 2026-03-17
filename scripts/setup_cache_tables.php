<?php
// scripts/setup_cache_tables.php
// Crea las tablas para caché de Zoom en MySQL
require_once __DIR__ . '/../config/config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✅ Conectado a la base de datos\n\n";

    // Tabla: zoom_meetings_cache
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zoom_meetings_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id VARCHAR(50) NOT NULL,
            uuid VARCHAR(200) NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            topic VARCHAR(500),
            start_time DATETIME NOT NULL,
            end_time DATETIME,
            duration_minutes INT,
            participants_count INT DEFAULT 0,
            has_recording TINYINT(1) DEFAULT 0,
            meeting_type VARCHAR(20),
            status VARCHAR(20) DEFAULT 'completed',
            raw_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_meeting (meeting_id, user_id),
            INDEX idx_user_id (user_id),
            INDEX idx_start_time (start_time),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabla zoom_meetings_cache creada\n";

    // Tabla: zoom_participants_cache
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zoom_participants_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id VARCHAR(50) NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            name VARCHAR(200),
            email VARCHAR(200),
            join_time DATETIME,
            leave_time DATETIME,
            duration_seconds INT,
            raw_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_meeting_id (meeting_id),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabla zoom_participants_cache creada\n";

    // Tabla: zoom_recordings_cache
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zoom_recordings_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            meeting_id VARCHAR(50) NOT NULL,
            uuid VARCHAR(200) NOT NULL,
            user_id VARCHAR(100) NOT NULL,
            topic VARCHAR(500),
            start_time DATETIME NOT NULL,
            recording_count INT DEFAULT 0,
            total_size BIGINT DEFAULT 0,
            recording_files JSON,
            raw_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_recording (meeting_id, user_id),
            INDEX idx_user_id (user_id),
            INDEX idx_start_time (start_time)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabla zoom_recordings_cache creada\n";

    // Tabla: zoom_users_cache
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zoom_users_cache (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(100) NOT NULL UNIQUE,
            email VARCHAR(200) NOT NULL,
            first_name VARCHAR(100),
            last_name VARCHAR(100),
            display_name VARCHAR(200),
            status VARCHAR(20),
            type VARCHAR(20),
            role VARCHAR(20),
            timezone VARCHAR(50),
            verified INT DEFAULT 0,
            raw_data JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (email),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabla zoom_users_cache creada\n";

    // Tabla: zoom_sync_log
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zoom_sync_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sync_type VARCHAR(50) NOT NULL,
            user_id VARCHAR(100),
            meetings_synced INT DEFAULT 0,
            participants_synced INT DEFAULT 0,
            recordings_synced INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'success',
            error_message TEXT,
            started_at DATETIME NOT NULL,
            completed_at DATETIME,
            duration_seconds INT,
            INDEX idx_sync_type (sync_type),
            INDEX idx_started_at (started_at),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabla zoom_sync_log creada\n";

    // Tabla: zoom_cache_settings
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS zoom_cache_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT,
            description TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ Tabla zoom_cache_settings creada\n";

    // Insertar configuración por defecto
    $pdo->exec("
        INSERT INTO zoom_cache_settings (setting_key, setting_value, description) 
        VALUES 
            ('cache_ttl_meetings', '3600', 'Tiempo de vida de reuniones en segundos (1 hora)'),
            ('cache_ttl_participants', '7200', 'Tiempo de vida de participantes en segundos (2 horas)'),
            ('cache_ttl_recordings', '3600', 'Tiempo de vida de grabaciones en segundos (1 hora)'),
            ('cache_ttl_users', '86400', 'Tiempo de vida de usuarios en segundos (24 horas)'),
            ('sync_interval_hours', '6', 'Intervalo de sincronización automática en horas'),
            ('retention_days', '365', 'Días de retención de datos históricos'),
            ('last_full_sync', NULL, 'Fecha de última sincronización completa'),
            ('auto_sync_enabled', '1', 'Sincronización automática habilitada (1=si, 0=no)')
        ON DUPLICATE KEY UPDATE setting_key = setting_key
    ");
    echo "✅ Configuración por defecto insertada\n\n";

    echo "===========================================\n";
    echo "✅ BASE DE DATOS CONFIGURADA EXITOSAMENTE\n";
    echo "===========================================\n\n";

    echo "Tablas creadas:\n";
    echo "  - zoom_meetings_cache (reuniones)\n";
    echo "  - zoom_participants_cache (participantes)\n";
    echo "  - zoom_recordings_cache (grabaciones)\n";
    echo "  - zoom_users_cache (usuarios)\n";
    echo "  - zoom_sync_log (logs de sincronización)\n";
    echo "  - zoom_cache_settings (configuración)\n\n";

    echo "Próximo paso: Ejecutar scripts/sync_zoom_data.php\n";
    echo "Para cron automático: Configurar en Windows Task Scheduler\n\n";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
