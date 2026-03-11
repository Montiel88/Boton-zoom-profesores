// assets/js/main.js
document.addEventListener('DOMContentLoaded', function() {
    const periodoSelect = document.getElementById('periodo');
    const carreraSelect = document.getElementById('carrera');
    const btnActualizar = document.getElementById('btn-actualizar');
    const profesoresContainer = document.getElementById('profesores-container');
    const resultCount = document.getElementById('result-count');

    // Cargar estadísticas al inicio
    cargarStats();

    // Búsqueda en tiempo real de profesores
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.placeholder = '🔍 Filtrar por nombre o email...';
    searchInput.style.cssText = 'padding: 8px 15px; border: 1px solid #ddd; border-radius: 20px; width: 300px; outline: none; transition: 0.3s;';
    searchInput.addEventListener('focus', () => searchInput.style.borderColor = '#3498db');
    searchInput.addEventListener('blur', () => searchInput.style.borderColor = '#ddd');
    
    searchInput.addEventListener('input', function() {
        const term = this.value.toLowerCase();
        const cards = document.querySelectorAll('.profesores-grid .card');
        cards.forEach(card => {
            const text = card.textContent.toLowerCase();
            card.style.display = text.includes(term) ? 'block' : 'none';
        });
    });

    const resultsHeader = document.querySelector('.results-header');
    if (resultsHeader) {
        resultsHeader.appendChild(searchInput);
    }

    periodoSelect.addEventListener('change', function() {
        if (this.value) {
            carreraSelect.disabled = false;
            carreraSelect.innerHTML = '<option value="">Cargando carreras...</option>';
            
            // Simulación o fetch real de carreras por periodo
            // Por ahora usamos las que ya vienen en el index
            // Pero podríamos hacerlo dinámico
            carreraSelect.innerHTML = `
                <option value="">Seleccione carrera...</option>
                ${Array.from(carreraSelect.options).map(o => o.value ? `<option value="${o.value}">${o.text}</option>` : '').join('')}
            `;
        } else {
            carreraSelect.disabled = true;
            btnActualizar.disabled = true;
        }
    });

    carreraSelect.addEventListener('change', function() {
        btnActualizar.disabled = !this.value;
    });

    btnActualizar.addEventListener('click', async function() {
        const carreraId = carreraSelect.value;
        if (!carreraId) return;

        profesoresContainer.innerHTML = `
            <div class="empty-state">
                <div class="loading-spinner"></div>
                <p>Buscando profesores y datos de Zoom...</p>
            </div>
        `;

        try {
            const response = await fetch(`api/get_profesores.php?carrera_id=${carreraId}`);
            const data = await response.json();

            if (data.error) {
                profesoresContainer.innerHTML = `<div class="empty-state">Error: ${data.error}</div>`;
                return;
            }

            const profesores = data.profesores || [];
            resultCount.textContent = profesores.length;

            if (profesores.length === 0) {
                profesoresContainer.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">😕</div>
                        <h3>No hay profesores</h3>
                        <p>No se encontraron profesores para esta carrera.</p>
                    </div>
                `;
                return;
            }

            let html = '<div class="profesores-grid">';
            profesores.forEach(user => {
                const statusClass = user.status === 'active' ? 'status-active' : 'status-inactive';
                html += `
                    <div class="card" onclick='verProfesor(${JSON.stringify(user)})'>
                        <div class="card-header">
                            <span>👨‍🏫</span>
                            ${user.first_name} ${user.last_name || ''}
                        </div>
                        <div class="card-body">
                            <div class="card-email">
                                ✉️ ${user.email}
                            </div>
                            <div class="card-stats">
                                <span class="stat-badge">🆔 ${user.id.substring(0,8)}...</span>
                                <span class="stat-badge ${statusClass}">📊 ${user.status}</span>
                                <span class="stat-badge">🏢 ${user.dept || 'S/D'}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            profesoresContainer.innerHTML = html;
        } catch (e) {
            profesoresContainer.innerHTML = `<div class="empty-state">Error de conexión: ${e.message}</div>`;
        }
    });
});

async function cargarStats() {
    try {
        const res = await fetch('api/get_stats.php');
        const data = await res.json();
        if (data.status === 'OK') {
            document.getElementById('stat-profesores').textContent = data.total_profesores;
            document.getElementById('stat-cache').textContent = data.elementos_cache;
            document.getElementById('stat-sync').textContent = data.ultima_sincronizacion ? new Date(data.ultima_sincronizacion).toLocaleTimeString() : 'N/A';
            
            // Gráfico de estados
            new Chart(document.getElementById('statusChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Activos', 'Pendientes', 'Inactivos'],
                    datasets: [{
                        data: [data.statuses.active, data.statuses.pending, data.statuses.inactive],
                        backgroundColor: ['#27ae60', '#f1c40f', '#e74c3c']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });

            // Gráfico de sincronización
            const history = data.sync_history || [];
            const syncChartCtx = document.getElementById('syncChart');
            if (syncChartCtx && history.length > 0) {
                // Destruir gráfico previo si existe para evitar que crezca en memoria
                const existingChart = Chart.getChart(syncChartCtx);
                if (existingChart) existingChart.destroy();

                new Chart(syncChartCtx, {
                    type: 'line',
                    data: {
                        labels: history.map(h => new Date(h.created_at).toLocaleTimeString()),
                        datasets: [{
                            label: 'Tiempo de Respuesta (s)',
                            data: history.map(h => parseFloat(h.response_time).toFixed(3)),
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52,152,219,0.1)',
                            fill: true,
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: { beginAtZero: true, max: 2.0 } // Limitar el eje Y para evitar picos que deformen el gráfico
                        }
                    }
                });
            } else if (syncChartCtx) {
                syncChartCtx.parentElement.innerHTML += '<p style="text-align:center; color:#999; margin-top:20px;">Sin datos de sincronización recientes.</p>';
            }

            // Gráfico de estados
            const statusChartCtx = document.getElementById('statusChart');
            if (statusChartCtx) {
                const existingStatusChart = Chart.getChart(statusChartCtx);
                if (existingStatusChart) existingStatusChart.destroy();

                new Chart(statusChartCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Activos', 'Pendientes', 'Inactivos'],
                        datasets: [{
                            data: [data.statuses.active, data.statuses.pending, data.statuses.inactive],
                            backgroundColor: ['#27ae60', '#f1c40f', '#e74c3c']
                        }]
                    },
                    options: { responsive: true, maintainAspectRatio: false }
                });
            }
        }
    } catch (e) {
        console.error("Error cargando stats", e);
    }
}

async function forzarSincronizacion() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '🔄 Sincronizando...';

    try {
        const res = await fetch('api/actualizar_cache.php');
        const data = await res.json();
        if (data.status === 'success') {
            alert('Sincronización completada: ' + data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    } catch (e) {
        alert('Error de conexión');
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalText;
    }
}

async function verProfesor(user) {
    const modal = document.getElementById('modal');
    const modalContent = document.getElementById('modal-content');
    modal.style.display = 'block';
    modalContent.innerHTML = `
        <div class="loading">
            <div class="loading-spinner"></div>
            <p>Obteniendo reuniones y grabaciones desde Zoom...</p>
        </div>
    `;

    try {
        const response = await fetch(`api/get_reuniones.php?user_id=${user.id}`);
        const data = await response.json();

        let meetingsHtml = '';
        if (data.meetings && data.meetings.length > 0) {
            meetingsHtml = data.meetings.map(m => `
                <div class="meeting-item">
                    <div class="meeting-info">
                        <strong>${m.topic}</strong><br>
                        <small>📅 ${new Date(m.start_time).toLocaleString()}</small>
                    </div>
                    <a href="${m.join_url}" target="_blank" class="btn-join">Unirse</a>
                </div>
            `).join('');
        } else {
            meetingsHtml = '<p class="no-data">No hay reuniones programadas.</p>';
        }

        let recordingsHtml = '';
        if (data.recordings && data.recordings.length > 0) {
            recordingsHtml = data.recordings.map(r => `
                <div class="recording-item">
                    <span>📹 ${r.topic}</span>
                    <small>${new Date(r.start_time).toLocaleDateString()}</small>
                </div>
            `).join('');
        } else {
            recordingsHtml = '<p class="no-data">No hay grabaciones recientes.</p>';
        }

        modalContent.innerHTML = `
            <div class="modal-header-info">
                <h2>${user.first_name} ${user.last_name || ''}</h2>
                <p>✉️ ${user.email} | 🏢 ${user.dept || 'Departamento No Asignado'}</p>
            </div>
            <div class="modal-grid">
                <div class="modal-column">
                    <h3>📅 Próximas Reuniones</h3>
                    <div class="scroll-area">${meetingsHtml}</div>
                </div>
                <div class="modal-column">
                    <h3>📹 Grabaciones en la Nube</h3>
                    <div class="scroll-area">${recordingsHtml}</div>
                </div>
            </div>
        `;
    } catch (e) {
        modalContent.innerHTML = `<div class="empty-state">Error: ${e.message}</div>`;
    }
}

function cerrarModal() {
    document.getElementById('modal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('modal');
    if (event.target == modal) {
        cerrarModal();
    }
}
