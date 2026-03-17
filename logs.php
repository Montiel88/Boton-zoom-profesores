<?php
// logs.php - Visualización de logs (Solo ADMIN)
require_once __DIR__ . '/includes/layout.php';
requireAdmin();

$db = getDB();

// Obtener logs de actividad
$actividad = $db->query("SELECT l.*, u.nombre_completo 
                         FROM logs_actividad l 
                         LEFT JOIN usuarios u ON l.usuario_id = u.id 
                         ORDER BY l.created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

// Obtener logs de sincronización
$sync = $db->query("SELECT * FROM logs_sincronizacion ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

renderPageStart('Logs del Sistema - TESA Zoom Monitor', 'logs');
?>
    <style>
        .tabs-modern {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            background: rgba(255, 255, 255, 0.5);
            padding: 0.5rem;
            border-radius: 100px;
            width: fit-content;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .tab-btn-modern {
            padding: 0.8rem 1.8rem;
            border-radius: 100px;
            border: none;
            cursor: pointer;
            background: transparent;
            font-weight: 700;
            color: var(--gray);
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .tab-btn-modern.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(123, 44, 191, 0.2);
        }
        .tab-content-modern {
            display: none;
            animation: fadeIn 0.4s ease;
        }
        .tab-content-modern.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .status-ok { color: #10b981; font-weight: 800; }
        .status-error { color: #ef4444; font-weight: 800; }
        
        code {
            background: #f1f5f9;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            color: var(--primary-dark);
        }
    </style>
    <main class="container">
        <div style="margin-bottom: 2rem; max-width: 1200px; margin-left: auto; margin-right: auto;">
            <h1 style="margin:0; font-size: 1.8rem; font-weight: 900; color: var(--primary-dark);">📜 Logs del Sistema</h1>
            <p style="margin:0.2rem 0 0; color: var(--gray); font-weight: 500;">Auditoría de actividad y sincronización con Zoom API</p>
        </div>

        <div style="display: flex; justify-content: center;">
            <div class="tabs-modern">
                <button class="tab-btn-modern active" onclick="showTab('actividad', this)">
                    <i class="fas fa-user-clock"></i> Actividad de Usuarios
                </button>
                <button class="tab-btn-modern" onclick="showTab('sync', this)">
                    <i class="fas fa-sync"></i> Sincronización API
                </button>
            </div>
        </div>

        <div id="actividad" class="tab-content-modern active">
            <section class="results-card">
                <div class="results-header">
                    <div style="font-weight: 700; color: var(--primary-dark);">Últimas 50 acciones registradas</div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>FECHA Y HORA</th>
                                <th>USUARIO</th>
                                <th>ACCIÓN</th>
                                <th>DETALLE</th>
                                <th style="text-align: center;">IP ORIGEN</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actividad as $a): ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 0.85rem; color: var(--gray);">
                                    <?php echo date('d/m/Y H:i:s', strtotime($a['created_at'])); ?>
                                </td>
                                <td style="font-weight: 700; color: #1e293b;">
                                    <?php echo e($a['nombre_completo'] ?? 'Sistema/Automático'); ?>
                                </td>
                                <td>
                                    <span style="font-weight: 800; color: var(--primary);"><?php echo e($a['accion']); ?></span>
                                </td>
                                <td style="font-size: 0.9rem; color: #475569;">
                                    <?php echo e($a['detalle']); ?>
                                </td>
                                <td style="text-align: center;">
                                    <code><?php echo $a['ip_address']; ?></code>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div id="sync" class="tab-content-modern">
            <section class="results-card">
                <div class="results-header">
                    <div style="font-weight: 700; color: var(--primary-dark);">Historial de peticiones a Zoom Cloud</div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>FECHA</th>
                                <th>ENDPOINT API</th>
                                <th style="text-align: center;">STATUS</th>
                                <th style="text-align: center;">TIEMPO</th>
                                <th style="text-align: center;">RESULTADO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sync as $s): ?>
                            <tr>
                                <td style="white-space: nowrap; font-size: 0.85rem; color: var(--gray);">
                                    <?php echo date('H:i:s d/m/y', strtotime($s['created_at'])); ?>
                                </td>
                                <td>
                                    <code><?php echo e($s['endpoint']); ?></code>
                                </td>
                                <td style="text-align: center; font-weight: 700;">
                                    <?php echo $s['status_code']; ?>
                                </td>
                                <td style="text-align: center; font-family: monospace;">
                                    <?php echo number_format($s['response_time'], 3); ?>s
                                </td>
                                <td style="text-align: center;">
                                    <?php if($s['success']): ?>
                                        <span class="status-ok"><i class="fas fa-check-circle"></i> ÉXITO</span>
                                    <?php else: ?>
                                        <span class="status-error" title="<?php echo e($s['error_message']); ?>">
                                            <i class="fas fa-exclamation-triangle"></i> ERROR
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <script>
        function showTab(tabId, btn) {
            document.querySelectorAll('.tab-content-modern').forEach(c => c.classList.remove('active'));
            document.querySelectorAll('.tab-btn-modern').forEach(b => b.classList.remove('active'));
            
            document.getElementById(tabId).classList.add('active');
            btn.classList.add('active');
        }
    </script>
<?php renderPageEnd(); ?>
