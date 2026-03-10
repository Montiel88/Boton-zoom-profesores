<?php
require_once 'config.php';
require_once 'zoom_api.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['usuario'])) {
    header('Location: login.php');
    exit;
}

$zoom = new ZoomAPI();
$users = $zoom->getUsers();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TESA Zoom Monitor - Dashboard</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Estilos adicionales específicos */
        .btn-zoom-header {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #0e5c8f, #1a8cff);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-right: 15px;
            border: 2px solid rgba(255,255,255,0.3);
            cursor: pointer;
            transition: 0.3s;
        }
        
        .btn-zoom-header:hover {
            transform: translateY(-3px);
            background: linear-gradient(135deg, #1a8cff, #0e5c8f);
        }
        
        .btn-zoom-header .zoom-icon {
            font-size: 24px;
        }
        
        .btn-zoom-header .zoom-text {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-top: 2px;
        }
        
        .logo-container {
            display: flex;
            align-items: center;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="logo-container">
            <!-- EL BOTÓN DE ZOOM QUE TANTO BUSCABAS -->
            <a href="#" id="btn-zoom-header" class="btn-zoom-header" onclick="mostrarTodosLosZoom()">
                <span class="zoom-icon">🎥</span>
                <span class="zoom-text">Zoom</span>
            </a>
            <div class="logo-icon">📚</div>
            <h1>TESA Zoom Monitor</h1>
        </div>
        <div class="user-info">
            <span class="user-name">👤 <?php echo htmlspecialchars($_SESSION['usuario']); ?></span>
            <a href="logout.php" class="btn-logout">Salir</a>
        </div>
    </header>

    <main class="container">
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
                        <span class="stat">📊 <?php echo $user['status']; ?></span>
                        <span class="stat">📅 <?php echo date('d/m/Y', strtotime($user['created_at'])); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal para mostrar detalles del profesor -->
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

    <!-- Modal para mostrar TODOS los Zoom (vista general) -->
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
                    <p><strong>Fecha de creación:</strong> ${new Date(user.created_at).toLocaleDateString()}</p>
                    
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