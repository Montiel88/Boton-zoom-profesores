<?php
// usuarios.php - Gestión de usuarios (Solo ADMIN)
require_once __DIR__ . '/includes/auth.php';
requireAdmin();

$db = getDB();
$usuarios = $db->query("SELECT * FROM usuarios ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - TESA Zoom Monitor</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .table-container { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.05); margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #f8f9fa; font-weight: 600; }
        .badge { padding: 5px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge-admin { background: #e3f2fd; color: #1976d2; }
        .badge-user { background: #f5f5f5; color: #616161; }
        .badge-active { background: #e8f5e9; color: #2e7d32; }
        .badge-inactive { background: #ffebee; color: #c62828; }
        .btn-action { padding: 5px 10px; border-radius: 5px; text-decoration: none; font-size: 0.8rem; margin-right: 5px; border: none; cursor: pointer; color: white; }
        .btn-edit { background: #3498db; }
        .btn-status { background: #95a5a6; }
        .btn-delete { background: #e74c3c; }
        
        /* Modal simple */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 30px; border-radius: 15px; width: 400px; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px; }
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
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>👥 Gestión de Usuarios</h2>
            <button class="btn-test" style="background: #27ae60;" onclick="abrirModal()">+ Nuevo Usuario</button>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Nombre Completo</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><strong><?php echo e($u['nombre_completo']); ?></strong></td>
                        <td><?php echo e($u['correo']); ?></td>
                        <td><span class="badge <?php echo $u['rol'] === 'ADMIN' ? 'badge-admin' : 'badge-user'; ?>"><?php echo $u['rol']; ?></span></td>
                        <td><span class="badge <?php echo $u['activo'] ? 'badge-active' : 'badge-inactive'; ?>"><?php echo $u['activo'] ? 'Activo' : 'Inactivo'; ?></span></td>
                        <td>
                            <button class="btn-action btn-status" onclick="cambiarEstado(<?php echo $u['id']; ?>, <?php echo $u['activo'] ? 0 : 1; ?>)">
                                <?php echo $u['activo'] ? 'Desactivar' : 'Activar'; ?>
                            </button>
                            <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                <button class="btn-action btn-delete" onclick="eliminarUsuario(<?php echo $u['id']; ?>)">Eliminar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <!-- Modal para nuevo usuario -->
    <div id="modalUsuario" class="modal">
        <div class="modal-content">
            <h3>Nuevo Usuario</h3>
            <form id="formUsuario">
                <div class="form-group">
                    <label>Nombre Completo</label>
                    <input type="text" name="nombre" required>
                </div>
                <div class="form-group">
                    <label>Correo Institucional</label>
                    <input type="email" name="correo" required>
                </div>
                <div class="form-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required>
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select name="rol">
                        <option value="USUARIO">Usuario (Solo visualización)</option>
                        <option value="ADMIN">Administrador (Acceso total)</option>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="submit" class="btn-test" style="flex: 1; background: #27ae60;">Guardar</button>
                    <button type="button" class="btn-test" style="flex: 1; background: #e74c3c;" onclick="cerrarModal()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirModal() { document.getElementById('modalUsuario').style.display = 'block'; }
        function cerrarModal() { document.getElementById('modalUsuario').style.display = 'none'; }

        document.getElementById('formUsuario').onsubmit = async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const res = await fetch('api/usuarios/crear.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') { location.reload(); } else { alert(data.error); }
        };

        async function cambiarEstado(id, nuevoEstado) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('activo', nuevoEstado);
            const res = await fetch('api/usuarios/actualizar_estado.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') { location.reload(); } else { alert(data.error); }
        }

        async function eliminarUsuario(id) {
            if (!confirm('¿Estás seguro de eliminar este usuario permanentemente?')) return;
            const formData = new FormData();
            formData.append('id', id);
            const res = await fetch('api/usuarios/eliminar.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') { location.reload(); } else { alert(data.error); }
        }
    </script>
</body>
</html>
