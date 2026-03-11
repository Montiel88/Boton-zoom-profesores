<?php
// logs.php - Visualización de logs (Solo ADMIN)
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$db = getDB();

// Obtener logs de actividad
$actividad = $db->query("SELECT l.*, u.nombre_completo 
                         FROM logs_actividad l 
                         LEFT JOIN usuarios u ON l.usuario_id = u.id 
                         ORDER BY l.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Obtener logs de sincronización
$sync = $db->query("SELECT * FROM logs_sincronizacion ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logs del Sistema - TESA Zoom Monitor</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; }
        .tab-btn { padding: 10px 20px; border-radius: 10px; border: none; cursor: pointer; background: #eee; font-weight: 600; }
        .tab-btn.active { background: #3498db; color: white; }
        .tab-content { display: none; background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); }
        .tab-content.active { display: block; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; position: sticky; top: 0; }
        .status-ok { color: #27ae60; font-weight: bold; }
        .status-error { color: #e74c3c; font-weight: bold; }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-area">
            <div class="logo-icon">📹</div>
            <h1>TESA Zoom Monitor</h1>
        </div>
        <div class="user-info">
            <a href="index.php" class="btn-test" style="background: #7f8c8d; text-decoration: none;">Dashboard</a>
            <span class="user-name">👤 <?php echo e($_SESSION['nombre']); ?></span>
            <a href="logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <main class="container">
        <h2>📜 Logs del Sistema</h2>
        
        <div class="tabs">
            <button class="tab-btn active" onclick="showTab('actividad')">Actividad de Usuarios</button>
            <button class="tab-btn" onclick="showTab('sync')">Sincronización API</button>
        </div>

        <div id="actividad" class="tab-content active">
            <h3>Últimas 50 acciones</h3>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción</th>
                        <th>Detalle</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($actividad as $a): ?>
                    <tr>
                        <td><?php echo $a['created_at']; ?></td>
                        <td><?php echo e($a['nombre_completo'] ?? 'Sistema/Anon'); ?></td>
                        <td><strong><?php echo e($a['accion']); ?></strong></td>
                        <td><?php echo e($a['detalle']); ?></td>
                        <td><code><?php echo $a['ip_address']; ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div id="sync" class="tab-content">
            <h3>Historial de conexión con Zoom</h3>
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Endpoint</th>
                        <th>Status</th>
                        <th>Tiempo (s)</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sync as $s): ?>
                    <tr>
                        <td><?php echo $s['created_at']; ?></td>
                        <td><code><?php echo e($s['endpoint']); ?></code></td>
                        <td><?php echo $s['status_code']; ?></td>
                        <td><?php echo number_format($s['response_time'], 3); ?>s</td>
                        <td class="<?php echo $s['success'] ? 'status-ok' : 'status-error'; ?>">
                            <?php echo $s['success'] ? 'Éxito' : 'Error: ' . e($s['error_message']); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        function showTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.target.classList.add('active');
        }
    </script>
</body>
</html>
