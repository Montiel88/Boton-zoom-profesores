<?php
require_once 'config.php';
require_once 'zoom_api.php';

// Verificar si el usuario está logueado (puedes desactivar esto para pruebas)
// if (!isset($_SESSION['usuario'])) {
//     header('Location: login.php');
//     exit;
// }

$zoom = new ZoomAPI();
$users = $zoom->getUsers();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESA Zoom Monitor - Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f7fa; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1rem 2rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .logo-container { display: flex; align-items: center; gap: 15px; }
        .btn-zoom-header { background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.3); border-radius: 10px; padding: 10px; text-align: center; cursor: pointer; transition: 0.3s; width: 60px; }
        .btn-zoom-header:hover { background: rgba(255,255,255,0.3); transform: translateY(-2px); }
        .zoom-icon { font-size: 24px; }
        .zoom-text { font-size: 10px; font-weight: bold; text-transform: uppercase; }
        .logo-icon { font-size: 40px; }
        h1 { font-size: 1.5rem; }
        .user-info { display: flex; align-items: center; gap: 20px; }
        .btn-logout { background: rgba(255,255,255,0.2); padding: 8px 15px; border-radius: 5px; text-decoration: none; color: white; }
        .btn-logout:hover { background: rgba(255,255,255,0.3); }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .section-title { font-size: 1.8rem; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
        .profesores-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); cursor: pointer; transition: 0.3s; }
        .card:hover { transform: translateY(-5px); box-shadow: 0 6px 12px rgba(0,0,0,0.15); }
        .card h3 { margin-bottom: 10px; color: #2c3e50; }
        .card p { color: #7f8c8d; margin-bottom: 15px; }
        .card-stats { display: flex; gap: 10px; font-size: 0.9rem; color: #34495e; }
        .stat { background: #ecf0f1; padding: 5px 10px; border-radius: 15px; }
        .empty-state { text-align: center; padding: 50px; background: white; border-radius: 10px; grid-column: 1/-1; }
        .empty-state-icon { font-size: 60px; margin-bottom: 20px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 5% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 800px; max-height: 80vh; overflow-y: auto; position: relative; }
        .modal-close { position: absolute; right: 20px; top: 15px; font-size: 28px; cursor: pointer; }
        .loading { text-align: center; padding: 30px; }
        .loading-spinner { border: 4px solid #f3f3f3; border-top: 4px solid #667eea; border-radius: 50%; width: 40px; height: 40px; animation: spin 1s linear infinite; margin: 0 auto 15px; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .meeting-item { background: #f8f9fa; padding: 15px; border-radius: 8px; margin: 10px 0; border-left: 4px solid #667eea; }
        .meeting-title { font-weight: bold; margin-bottom: 5px; }
        .meeting-date { font-size: 0.9rem; color: #555; margin: 2px 0; }
        .recording-links { margin-top: 10px; }
        .btn-download { display: inline-block; background: #27ae60; color: white; padding: 5px 10px; border-radius: 5px; text-decoration: none; margin-right: 5px; font-size: 0.8rem; }
        .btn-download:hover { background: #2ecc71; }
        .test-button { background: #3498db; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
        .test-button:hover { background: #2980b9; }
        .alert { padding: 15px; border-radius: 5px; margin: 20px 0; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-container">
            <!-- BOTÓN ZOOM (para vista general) -->
            <div class="btn-zoom-header" onclick="mostrarTodosLosZoom()">
                <span class="zoom-icon">🎥</span>
                <span class="zoom-text">Zoom</span>
            </div>
            <div class="logo-icon">📚</div>
            <h1>TESA Zoom Monitor</h1>
        </div>
        <div class="user-info">
            <span class="user-name">👤 <?php echo isset($_SESSION['usuario']) ? htmlspecialchars($_SESSION['usuario']) : 'Invitado'; ?></span>
            <a href="logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <main class="container">
        <!-- BOTÓN PARA PROBAR API -->
        <button id="test-api-btn" class="test-button">🔌 Probar conexión con Zoom API</button>
        <div id="test-result"></div>

        <h2 class="section-title">
            <span>📹</span>
            Profesores en Zoom (<?php echo count($users); ?>)
        </h2>
        
        <div class="profesores-grid" id="profesores-grid">
            <?php if (empty($users)): ?>
                <div class="empty-state">
                    <div class="empty-state-icon">😕</div>
                    <h3>No hay profesores en Zoom</h3>
                    <p>Verifica las credenciales de la API</p>
                </div>
            <?php else: ?>
                <?php foreach ($users as $user): ?>
                <div class="card" onclick='verProfesor(<?php echo json_encode($user); ?>)'>
                    <h3><?php echo htmlspecialchars($user['first_name'] . ' ' . ($user['last_name'] ?? '')); ?></h3>
                    <p><?php echo htmlspecialchars($user['email']); ?></p>
                    <div class="card-stats">
                        <span class="stat">🆔 <?php echo substr($user['id'], 0, 8); ?>...</span>
                        <span class="stat">📊 <?php echo $user['status'] ?? 'active'; ?></span>
                        <span class="stat">📅 <?php echo isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : 'N/A'; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal para detalles del profesor -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <span class="modal-close" onclick="cerrarModal()">&times;</span>
            <div id="modal-content">
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>Cargando reuniones y grabaciones...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para vista general de todos los Zoom -->
    <div id="modal-todos" class="modal">
        <div class="modal-content" style="max-width: 1000px;">
            <span class="modal-close" onclick="cerrarModalTodos()">&times;</span>
            <div id="modal-todos-content">
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>Cargando todos los datos de Zoom...</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Función para probar la API
        document.getElementById('test-api-btn').addEventListener('click', async function() {
            const resultDiv = document.getElementById('test-result');
            resultDiv.innerHTML = '<div class="loading"><div class="loading-spinner"></div> Probando conexión...</div>';
            
            try {
                // Llamamos a un endpoint simple que pruebe la conexión
                const response = await fetch('test_connection.php');
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `<div class="alert alert-success">✅ ${data.message}</div>`;
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-error">❌ ${data.message}</div>`;
                }
            } catch (e) {
                resultDiv.innerHTML = `<div class="alert alert-error">❌ Error de conexión: ${e.message}</div>`;
            }
        });

        async function verProfesor(user) {
            document.getElementById('modal').style.display = 'block';
            document.getElementById('modal-content').innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>Cargando reuniones y grabaciones...</p>
                </div>
            `;
            
            try {
                const response = await fetch(`get_zoom_data.php?user_id=${user.id}`);
                const data = await response.json();
                
                let html = `
                    <h2>${user.first_name} ${user.last_name || ''}</h2>
                    <p><strong>Email:</strong> ${user.email}</p>
                    <p><strong>ID Zoom:</strong> ${user.id}</p>
                    <p><strong>Fecha de creación:</strong> ${user.created_at ? new Date(user.created_at).toLocaleDateString() : 'N/A'}</p>
                    
                    <h3>📅 Reuniones programadas (${data.meetings?.length || 0})</h3>
                `;
                
                if (data.meetings && data.meetings.length > 0) {
                    data.meetings.forEach(m => {
                        const fecha = new Date(m.start_time).toLocaleString('es-EC', {
                            dateStyle: 'full',
                            timeStyle: 'short'
                        });
                        html += `
                            <div class="meeting-item">
                                <div class="meeting-title">${m.topic}</div>
                                <div class="meeting-date">📅 ${fecha}</div>
                                <div class="meeting-date">⏱️ Duración: ${m.duration} minutos</div>
                                <div class="meeting-date">🔗 ID: ${m.id}</div>
                            </div>
                        `;
                    });
                } else {
                    html += '<p>No hay reuniones programadas</p>';
                }
                
                html += `<h3 style="margin-top: 25px;">🎥 Grabaciones (${data.recordings?.length || 0})</h3>`;
                
                if (data.recordings && data.recordings.length > 0) {
                    data.recordings.forEach(r => {
                        const fecha = new Date(r.start_time).toLocaleString('es-EC', {
                            dateStyle: 'full',
                            timeStyle: 'short'
                        });
                        html += `
                            <div class="meeting-item">
                                <div class="meeting-title">${r.topic}</div>
                                <div class="meeting-date">📅 ${fecha}</div>
                                <div class="meeting-date">⏱️ Duración: ${r.duration} minutos</div>
                                <div class="recording-links">
                        `;
                        if (r.recording_files) {
                            r.recording_files.forEach(f => {
                                if (f.download_url) {
                                    html += `<a href="${f.download_url}" target="_blank" class="btn-download">📥 ${f.file_type}</a>`;
                                }
                            });
                        }
                        html += `</div></div>`;
                    });
                } else {
                    html += '<p>No hay grabaciones disponibles</p>';
                }
                
                document.getElementById('modal-content').innerHTML = html;
            } catch (e) {
                document.getElementById('modal-content').innerHTML = `
                    <div style="color: #e74c3c; text-align: center; padding: 20px;">
                        ❌ Error al cargar datos. Verifica la conexión con Zoom.
                    </div>
                `;
            }
        }
        
        async function mostrarTodosLosZoom() {
            event.preventDefault();
            document.getElementById('modal-todos').style.display = 'block';
            document.getElementById('modal-todos-content').innerHTML = `
                <div class="loading">
                    <div class="loading-spinner"></div>
                    <p>Cargando todos los datos de Zoom...</p>
                </div>
            `;
            
            try {
                const response = await fetch('get_all_zoom_data.php');
                const data = await response.json();
                
                let html = '<h2>📊 Vista General de Zoom</h2>';
                
                data.forEach(prof => {
                    html += `
                        <div style="margin: 30px 0; background: #f8f9fa; padding: 20px; border-radius: 10px;">
                            <h3 style="color: #2c3e50;">${prof.profesor}</h3>
                            <p><strong>Email:</strong> ${prof.email}</p>
                            
                            <h4 style="margin: 15px 0 10px;">📅 Recientes (${prof.meetings.length})</h4>
                    `;
                    
                    prof.meetings.forEach(m => {
                        html += `
                            <div style="margin-left: 20px; padding: 10px; border-left: 3px solid #667eea;">
                                <strong>${m.topic}</strong><br>
                                📅 ${new Date(m.start_time).toLocaleString()}<br>
                                ⏱️ ${m.duration} min
                            </div>
                        `;
                    });
                    
                    html += `<h4 style="margin: 15px 0 10px;">🎥 Grabaciones recientes (${prof.recordings.length})</h4>`;
                    
                    prof.recordings.forEach(r => {
                        html += `
                            <div style="margin-left: 20px; padding: 10px; border-left: 3px solid #27ae60;">
                                <strong>${r.topic}</strong><br>
                                📅 ${new Date(r.start_time).toLocaleString()}
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                });
                
                document.getElementById('modal-todos-content').innerHTML = html;
            } catch (e) {
                document.getElementById('modal-todos-content').innerHTML = `
                    <div style="color: #e74c3c; text-align: center; padding: 20px;">
                        ❌ Error al cargar datos generales
                    </div>
                `;
            }
        }
        
        function cerrarModal() {
            document.getElementById('modal').style.display = 'none';
        }
        
        function cerrarModalTodos() {
            document.getElementById('modal-todos').style.display = 'none';
        }
        
        window.onclick = function(event) {
            if (event.target == document.getElementById('modal')) {
                cerrarModal();
            }
            if (event.target == document.getElementById('modal-todos')) {
                cerrarModalTodos();
            }
        }
    </script>
</body>
</html>