<?php
// index.php - Dashboard con filtros y estadísticas
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config.php';
requireLogin();

// Obtener periodos y carreras desde la BD
$db = getDB();
$periodos = $db->query("SELECT * FROM periodos ORDER BY nombre DESC")->fetchAll(PDO::FETCH_ASSOC);
$carreras = $db->query("SELECT * FROM carreras ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESA Zoom Monitor - Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <header class="header">
        <div class="logo-area">
            <div class="logo-icon">📹</div>
            <h1>TESA Zoom Monitor</h1>
        </div>
        <div class="user-info">
            <span class="user-name">👤 <?php echo e($_SESSION['nombre'] ?? $_SESSION['correo']); ?></span>
            <?php if (($_SESSION['rol'] ?? '') === 'ADMIN'): ?>
                <a href="usuarios.php" class="btn-test" style="background: #27ae60; text-decoration: none;">Usuarios</a>
                <a href="logs.php" class="btn-test" style="background: #8e44ad; text-decoration: none;">Logs</a>
            <?php endif; ?>
            <a href="logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <main class="container">
        <!-- Dashboard Stats -->
        <section class="stats-container">
            <div class="stat-card">
                <span class="stat-icon">👨‍🏫</span>
                <span id="stat-profesores" class="stat-value">0</span>
                <span class="stat-label">Profesores en Zoom</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">💾</span>
                <span id="stat-cache" class="stat-value">0</span>
                <span class="stat-label">Elementos en Caché</span>
            </div>
            <div class="stat-card">
                <span class="stat-icon">🔄</span>
                <span id="stat-sync" class="stat-value">--:--</span>
                <span class="stat-label">Última Sincronización</span>
            </div>
            <div class="stat-card">
                <button onclick="forzarSincronizacion()" class="btn-test" style="padding: 8px 15px; font-size: 0.8rem; margin-top: 10px;">🔄 Sincronizar</button>
                <span class="stat-label">Actualizar Datos</span>
            </div>
        </section>

        <!-- Charts Section -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
            <div style="background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <h3 style="margin-top: 0; color: #2c3e50;">📊 Actividad de Sincronización</h3>
                <canvas id="syncChart" height="100"></canvas>
            </div>
            <div style="background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
                <h3 style="margin-top: 0; color: #2c3e50;">📈 Estado Profesores</h3>
                <canvas id="statusChart"></canvas>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filters-section" style="background: white; border-radius: 15px; padding: 25px; margin-bottom: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);">
            <div style="display: grid; grid-template-columns: 1fr 1fr auto; gap: 20px; align-items: end;">
                <div class="form-group" style="display: flex; flex-direction: column;">
                    <label style="font-weight: 600; margin-bottom: 8px;">📅 Período / Semestre</label>
                    <select id="periodo" style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <option value="">Seleccione...</option>
                        <?php foreach ($periodos as $p): ?>
                            <option value="<?php echo $p['id']; ?>"><?php echo e($p['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display: flex; flex-direction: column;">
                    <label style="font-weight: 600; margin-bottom: 8px;">🎓 Carrera</label>
                    <select id="carrera" disabled style="padding: 12px; border: 1px solid #ddd; border-radius: 8px;">
                        <option value="">Primero seleccione un período</option>
                        <?php foreach ($carreras as $c): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo e($c['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button id="btn-actualizar" class="btn-test" disabled style="padding: 12px 30px;">🔍 Buscar Profesores</button>
            </div>
        </div>

        <!-- Results Section -->
        <div class="results-section">
            <div class="results-header">
                <h2>👨‍🏫 Listado de Profesores</h2>
                <span class="stat-badge" style="font-size: 1rem; padding: 8px 20px; background: var(--secondary); color: white;">
                    Encontrados: <span id="result-count">0</span>
                </span>
            </div>
            <div id="profesores-container">
                <div class="empty-state" style="text-align: center; padding: 60px; background: white; border-radius: 15px; color: #7f8c8d;">
                    <div class="empty-state-icon" style="font-size: 48px; margin-bottom: 15px;">📋</div>
                    <h3>Filtros de Búsqueda</h3>
                    <p>Seleccione un período y una carrera para visualizar los datos de Zoom</p>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Detalle Profesor -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="modal-close" style="position: absolute; right: 20px; top: 15px; font-size: 28px; cursor: pointer; z-index: 10;" onclick="cerrarModal()">&times;</span>
            <div id="modal-content">
                <!-- Se carga vía AJAX -->
            </div>
        </div>
    </div>

    <script src="assets/js/main.js"></script>
</body>
</html>
