<?php
// index.php
require_once __DIR__ . '/includes/layout.php';
requireLogin();

$mainVersion = file_exists(__DIR__ . '/assets/js/main.js') ? filemtime(__DIR__ . '/assets/js/main.js') : time();

renderPageStart('TESA Zoom Monitor - Dashboard', 'dashboard');
?>
    <div class="brand-banner">
        <div class="brand-inner">
            <div class="brand-title">INSTITUTO TECNOLÓGICO SAN ANTONIO</div>
            <div class="brand-subtitle">TESA</div>
            <div class="brand-divider"></div>
        </div>
    </div>

    <main class="container">
        <!-- Dashboard de Dominios -->
        <div id="dashboard-stats" class="dashboard-stats">
            <div class="stat-card-modern" onclick="filterByDomain('tesa.edu.ec')" style="cursor: pointer;">
                <div class="stat-icon tesa-blue">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Docentes TESA</div>
                    <div id="stat-tesa" class="stat-number">0</div>
                    <div class="stat-sub">@tesa.edu.ec</div>
                </div>
            </div>
            
            <div class="stat-card-modern">
                <div class="stat-icon zoom-green">
                    <i class="fas fa-video"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Clases en Vivo</div>
                    <div id="stat-live" class="stat-number">0</div>
                    <div class="stat-sub">Reuniones activas</div>
                </div>
            </div>

            <div class="stat-card-modern" onclick="filterByDomain('estud.itsa.edu.ec')" style="cursor: pointer;">
                <div class="stat-icon itsa-pink">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <div class="stat-label">Docente ITSA</div>
                    <div id="stat-itsa" class="stat-number">0</div>
                    <div class="stat-sub">estud.itsa.edu.ec</div>
                </div>
            </div>
        </div>

        <div class="search-section">
            <div class="search-card">
                <div class="search-header">
                    <h2 style="margin: 0; font-size: 1.4rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
                        🔍 Buscador de Profesores
                    </h2>
                    <p style="margin: 0.5rem 0 0; color: var(--gray); font-size: 0.9rem;">
                        Ingresa el nombre o correo del profesor para ver sus reuniones de Zoom.
                    </p>
                </div>
                <div class="search-input-group" style="display: flex; gap: 1rem; align-items: center; margin-top: 1.5rem;">
                    <input type="text" id="profesor-search" class="search-input" placeholder="Ej: Anabel Paredes o aparedes@itsa.edu.ec" style="flex: 1; padding: 0.8rem 1.5rem;">
                    <button id="btn-buscar" class="btn-buscar" style="white-space: nowrap; padding: 0.8rem 2rem;">
                        🚀 Buscar Ahora
                    </button>
                </div>
            </div>
        </div>

        <!-- Results Section -->
        <section id="results-section" class="results-card" style="display: none; margin-top: 2rem;">
            <div class="results-header" style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.5rem; font-weight: 700; color: var(--primary-dark);">
                        📊 Resultados
                    </div>
                    <div id="result-count" style="font-size: 0.85rem; color: #64748b; margin-top: 0.25rem;">0 resultados</div>
                </div>
                <button onclick="volverAlDashboard()" class="btn-action" style="background: #f1f5f9; color: #475569; border: 1px solid #e2e8f0;">
                    <i class="fas fa-arrow-left"></i> Volver al Dashboard
                </button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 80px; text-align: center;">AVATAR</th>
                            <th>NOMBRE DEL PROFESOR</th>
                            <th>CORREO ELECTRÓNICO</th>
                            <th style="text-align: center;">ESTADO</th>
                            <th style="text-align: center;">ZONA HORARIA</th>
                            <th style="text-align: center;">ACCIÓN</th>
                        </tr>
                    </thead>
                    <tbody id="profesores-container">
                        <tr>
                            <td colspan="6" style="text-align:center; padding: 5rem; color: #94a3b8;">
                                Ingrese un nombre para buscar profesores
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Pantalla de Carga Profesional (Diferente a Jeremy) -->
    <div id="premium-loader" class="premium-loader">
        <div class="loader-icon"></div>
        <div class="loader-status">Analizando Métricas de Zoom...</div>
        <div class="loader-bar-container">
            <div id="loader-fill" class="loader-bar-fill"></div>
        </div>
        <div class="loader-timer">Procesando: <span id="timer">0s</span></div>
    </div>

    <!-- Modal para ver reuniones -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div id="modal-body"></div>
        </div>
    </div>

    <!-- Modal para ver participantes -->
    <div id="modal-participantes" class="modal" style="z-index: 3000;">
        <div class="modal-content" style="max-width: 1200px; height: 90vh; max-height: 90vh;">
            <div class="modal-header-prof" style="padding: 2rem; background: var(--primary-dark); display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 style="margin:0; font-size: 1.8rem; font-weight: 900;">👥 Lista de Asistencia</h3>
                    <p id="meeting-topic-title" style="margin: 0.5rem 0 0; font-size: 1.1rem; opacity: 0.8; font-weight: 500;"></p>
                </div>
                <span class="modal-close" onclick="cerrarModalParticipantes()" style="position: static;">&times;</span>
            </div>
            <div class="modal-body-prof" style="padding: 2rem 4rem;">
                <div id="participantes-content" class="table-responsive" style="max-height: none;"></div>
                <div style="margin-top: 2rem; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 1.5rem;">
                    <button class="btn-cerrar-modal" onclick="cerrarModalParticipantes()" style="padding: 0.8rem 3rem;">Regresar</button>
                </div>
            </div>
        </div>
    </div>

<?php renderPageEnd(["assets/js/main.js?v={$mainVersion}"]); ?>
