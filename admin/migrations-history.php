<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Migraciones - Sistema</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .stats-card {
            border-left: 4px solid #0d6efd;
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .migration-success {
            border-left: 4px solid #28a745;
        }
        .migration-failed {
            border-left: 4px solid #dc3545;
        }
        .badge-version {
            font-family: 'Courier New', monospace;
            font-size: 14px;
        }
        .execution-time {
            color: #6c757d;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1><i class="fas fa-database me-2 text-primary"></i>Historial de Migraciones</h1>
                <p class="text-muted">Control de actualizaciones de base de datos</p>
            </div>
            <div>
                <button class="btn btn-outline-primary" onclick="loadMigrations()">
                    <i class="fas fa-sync-alt me-1"></i>Actualizar
                </button>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-1"></i>Volver
                </a>
            </div>
        </div>

        <!-- Estadísticas -->
        <div class="row mb-4" id="statistics">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="text-primary mb-0" id="stat-total">0</h3>
                        <small class="text-muted">Total Migraciones</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card" style="border-left-color: #28a745;">
                    <div class="card-body text-center">
                        <h3 class="text-success mb-0" id="stat-success">0</h3>
                        <small class="text-muted">Exitosas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card" style="border-left-color: #dc3545;">
                    <div class="card-body text-center">
                        <h3 class="text-danger mb-0" id="stat-failed">0</h3>
                        <small class="text-muted">Fallidas</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card" style="border-left-color: #ffc107;">
                    <div class="card-body text-center">
                        <h3 class="text-warning mb-0" id="stat-time">0s</h3>
                        <small class="text-muted">Tiempo Total</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Migraciones Pendientes -->
        <div id="pending-migrations" class="mb-4" style="display: none;">
            <div class="alert alert-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Hay <span id="pending-count">0</span> migraciones pendientes</strong>
                    </div>
                    <a href="settings.php#update" class="btn btn-sm btn-primary">
                        Actualizar Sistema
                    </a>
                </div>
            </div>
        </div>

        <!-- Historial -->
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    Historial de Migraciones
                </h5>
            </div>
            <div class="card-body" id="migrations-list">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="text-muted mt-3">Cargando migraciones...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cargar migraciones al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            loadMigrations();
        });

        function loadMigrations() {
            fetch('api/github-update.php?action=get_migrations')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStatistics(data.statistics);
                        displayHistory(data.history);
                        displayPending(data.pending);
                    } else {
                        showError(data.message);
                    }
                })
                .catch(error => {
                    showError('Error al cargar migraciones: ' + error.message);
                });
        }

        function updateStatistics(stats) {
            document.getElementById('stat-total').textContent = stats.total || 0;
            document.getElementById('stat-success').textContent = stats.successful || 0;
            document.getElementById('stat-failed').textContent = stats.failed || 0;
            document.getElementById('stat-time').textContent = (stats.total_execution_time || 0) + 's';
        }

        function displayPending(pending) {
            const pendingDiv = document.getElementById('pending-migrations');
            const countSpan = document.getElementById('pending-count');
            
            if (pending && pending.length > 0) {
                countSpan.textContent = pending.length;
                pendingDiv.style.display = 'block';
            } else {
                pendingDiv.style.display = 'none';
            }
        }

        function displayHistory(history) {
            const listDiv = document.getElementById('migrations-list');
            
            if (!history || history.length === 0) {
                listDiv.innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>No hay migraciones registradas</p>
                    </div>
                `;
                return;
            }

            let html = '<div class="list-group">';
            
            history.forEach(migration => {
                const statusClass = migration.status === 'success' ? 'migration-success' : 'migration-failed';
                const statusIcon = migration.status === 'success' ? 
                    '<i class="fas fa-check-circle text-success"></i>' : 
                    '<i class="fas fa-times-circle text-danger"></i>';
                const statusBadge = migration.status === 'success' ? 
                    '<span class="badge bg-success">Exitosa</span>' : 
                    '<span class="badge bg-danger">Fallida</span>';

                const executionTime = migration.execution_time ? 
                    `<span class="execution-time"><i class="fas fa-clock me-1"></i>${migration.execution_time}s</span>` : '';

                const retryButton = migration.status === 'failed' ? 
                    `<button class="btn btn-sm btn-warning" onclick="retryMigration('${migration.version}')">
                        <i class="fas fa-redo me-1"></i>Reintentar
                    </button>` : '';

                html += `
                    <div class="list-group-item ${statusClass}">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center mb-2">
                                    ${statusIcon}
                                    <span class="badge badge-version bg-secondary ms-2">v${migration.version}</span>
                                    ${statusBadge}
                                </div>
                                <div class="mb-1">
                                    <strong>${migration.filename}</strong>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    ${new Date(migration.executed_at).toLocaleString('es-AR')}
                                    ${executionTime}
                                </small>
                                ${migration.error_message ? `
                                    <div class="alert alert-danger mt-2 mb-0 small">
                                        <strong>Error:</strong> ${migration.error_message}
                                    </div>
                                ` : ''}
                            </div>
                            <div>
                                ${retryButton}
                            </div>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            listDiv.innerHTML = html;
        }

        function retryMigration(version) {
            if (!confirm(`¿Deseas reintentar la migración v${version}?`)) {
                return;
            }

            const btn = event.target.closest('button');
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Reintentando...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('version', version);

            fetch('api/github-update.php?action=retry_migration', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Migración ejecutada exitosamente');
                        loadMigrations();
                    } else {
                        alert('Error: ' + data.message);
                        btn.innerHTML = originalHTML;
                        btn.disabled = false;
                    }
                })
                .catch(error => {
                    alert('Error: ' + error.message);
                    btn.innerHTML = originalHTML;
                    btn.disabled = false;
                });
        }

        function showError(message) {
            document.getElementById('migrations-list').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    ${message}
                </div>
            `;
        }
    </script>
</body>
</html>
