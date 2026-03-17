<?php
// usuarios.php - Gestión de usuarios (Solo ADMIN)
require_once __DIR__ . '/includes/layout.php';
requireAdmin();

$db = getDB();
$usuarios = $db->query("SELECT * FROM usuarios ORDER BY nombre_completo")->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Gestión de Usuarios - TESA Zoom Monitor', 'usuarios');
?>
    <style>
        .badge-role {
            padding: 0.3rem 0.75rem;
            border-radius: 20px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
        }
        .role-admin { background: #eff6ff; color: #2563eb; border: 1px solid #dbeafe; }
        .role-super { background: #faf5ff; color: #7c3aed; border: 1px solid #f3e8ff; }
        .role-user { background: #f8fafc; color: #64748b; border: 1px solid #e2e8f0; }
        
        .btn-status-toggle {
            background: white;
            border: 1px solid #e2e8f0;
            padding: 0.4rem 0.8rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-status-toggle:hover {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .btn-delete-user {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #fecaca;
            padding: 0.4rem 0.8rem;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-delete-user:hover {
            background: #ef4444;
            color: white;
        }

        /* Estilo para el formulario del modal */
        .modern-form .form-group { margin-bottom: 1.2rem; }
        .modern-form label { 
            display: block; 
            font-size: 0.75rem; 
            font-weight: 800; 
            color: var(--gray); 
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .modern-form input, .modern-form select {
            width: 100%;
            padding: 0.8rem 1rem;
            border: 2px solid #f1f5f9;
            background: #f8fafc;
            border-radius: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .modern-form input:focus, .modern-form select:focus {
            border-color: var(--primary-light);
            background: white;
            outline: none;
            box-shadow: 0 0 0 4px rgba(123, 44, 191, 0.1);
        }
    </style>

    <main class="container">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; max-width: 1200px; margin-left: auto; margin-right: auto;">
            <div>
                <h1 style="margin:0; font-size: 1.8rem; font-weight: 900; color: var(--primary-dark);">👥 Gestión de Usuarios</h1>
                <p style="margin:0.2rem 0 0; color: var(--gray); font-weight: 500;">Administra los accesos al sistema de monitoreo</p>
            </div>
            <button class="btn-buscar" style="padding: 0.8rem 1.5rem;" onclick="abrirModal()">
                <i class="fas fa-plus"></i> Nuevo Usuario
            </button>
        </div>

        <section class="results-card">
            <div class="results-header">
                <div style="font-weight: 700; color: var(--primary-dark);">Lista de Administradores y Usuarios</div>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>NOMBRE COMPLETO</th>
                            <th>CORREO ELECTRÓNICO</th>
                            <th style="text-align: center;">ROL</th>
                            <th style="text-align: center;">ESTADO</th>
                            <th style="text-align: center;">ACCIONES</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td style="font-weight: 700; color: #1e293b;">
                                <?php echo e($u['nombre_completo']); ?>
                            </td>
                            <td style="color: var(--gray); font-size: 0.9rem;">
                                <?php echo e($u['correo']); ?>
                            </td>
                            <td style="text-align: center;">
                                <?php 
                                    $roleClass = 'role-user';
                                    if ($u['rol'] === 'ADMIN') $roleClass = 'role-admin';
                                    if ($u['rol'] === 'SUPERADMIN') $roleClass = 'role-super';
                                ?>
                                <span class="badge-role <?php echo $roleClass; ?>"><?php echo $u['rol']; ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge-status <?php echo $u['activo'] ? 'badge-active' : 'badge-inactive'; ?>">
                                    <?php echo $u['activo'] ? 'ACTIVO' : 'INACTIVO'; ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <div style="display: flex; gap: 0.5rem; justify-content: center;">
                                    <button class="btn-status-toggle" onclick="cambiarEstado(<?php echo $u['id']; ?>, <?php echo $u['activo'] ? 0 : 1; ?>)" title="Cambiar estado">
                                        <i class="fas <?php echo $u['activo'] ? 'fa-user-slash' : 'fa-user-check'; ?>"></i>
                                    </button>
                                    <?php if ($u['id'] != $_SESSION['user_id']): ?>
                                        <button class="btn-delete-user" onclick="eliminarUsuario(<?php echo $u['id']; ?>)" title="Eliminar permanentemente">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Modal para nuevo usuario -->
    <div id="modalUsuario" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header-prof" style="padding: 1.5rem;">
                <span class="modal-close" onclick="cerrarModal()">&times;</span>
                <h2 style="margin:0; font-size: 1.4rem; font-weight: 900;">✨ Crear Nuevo Usuario</h2>
                <p style="margin: 0.3rem 0 0; opacity: 0.8; font-size: 0.9rem;">Registra un nuevo administrador para el sistema</p>
            </div>
            <div class="modal-body-prof" style="padding: 2rem;">
                <form id="formUsuario" class="modern-form">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Nombre Completo</label>
                        <input type="text" name="nombre" placeholder="Ej: Juan Pérez" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Correo Institucional</label>
                        <input type="email" name="correo" placeholder="correo@tesa.edu.ec" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-lock"></i> Contraseña de Acceso</label>
                        <input type="password" name="password" placeholder="••••••••" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user-shield"></i> Rol en el Sistema</label>
                        <select name="rol">
                            <option value="USUARIO">Usuario (Solo Consultas)</option>
                            <option value="ADMIN">Administrador (Gestión Total)</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn-buscar" style="flex: 2; padding: 0.8rem;">
                            🚀 Crear Usuario
                        </button>
                        <button type="button" class="btn-cerrar-modal" style="flex: 1; padding: 0.8rem; margin:0;" onclick="cerrarModal()">
                            Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function abrirModal() { document.getElementById('modalUsuario').style.display = 'flex'; }
        function cerrarModal() { document.getElementById('modalUsuario').style.display = 'none'; }

        window.onclick = function(event) {
            const modal = document.getElementById('modalUsuario');
            if (event.target == modal) cerrarModal();
        }

        document.getElementById('formUsuario').onsubmit = async function(e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            try {
                const formData = new FormData(this);
                const res = await fetch('api/usuarios/crear_usuario.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.status === 'success') { 
                    location.reload(); 
                } else { 
                    alert("Error: " + data.error); 
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            } catch (err) {
                alert("Error de conexión");
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        };

        async function cambiarEstado(id, nuevoEstado) {
            if (!confirm('¿Deseas cambiar el estado de este usuario?')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('activo', nuevoEstado);
            const res = await fetch('api/usuarios/actualizar_usuario.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') { location.reload(); } else { alert(data.error); }
        }

        async function eliminarUsuario(id) {
            if (!confirm('⚠️ ¿Estás seguro de eliminar este usuario permanentemente? Esta acción no se puede deshacer.')) return;
            const formData = new FormData();
            formData.append('id', id);
            const res = await fetch('api/usuarios/eliminar_usuario.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.status === 'success') { location.reload(); } else { alert(data.error); }
        }
    </script>
<?php renderPageEnd(); ?>
