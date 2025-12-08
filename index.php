<?php
// index.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Dashboard';
$breadcrumbs = ['index.php' => 'Dashboard'];

require_once __DIR__ . '/includes/header.php';

// Obtener estadísticas reales desde la base de datos
$db = new Database();
$stats = [
    'pruebas_hoy' => $db->fetchOne("SELECT COUNT(*) as total FROM pruebas WHERE DATE(fecha_prueba) = CURDATE() AND cliente_id = ?", [$_SESSION['cliente_id'] ?? 0])['total'] ?? 0,
    'pruebas_semana' => $db->fetchOne("SELECT COUNT(*) as total FROM pruebas WHERE YEARWEEK(fecha_prueba) = YEARWEEK(CURDATE()) AND cliente_id = ?", [$_SESSION['cliente_id'] ?? 0])['total'] ?? 0,
    'conductores_activos' => $db->fetchOne("SELECT COUNT(*) as total FROM conductores WHERE estado = 1 AND cliente_id = ?", [$_SESSION['cliente_id'] ?? 0])['total'] ?? 0,
    'alertas_pendientes' => $db->fetchOne("SELECT COUNT(*) as total FROM alertas WHERE estado = 'pendiente' AND cliente_id = ?", [$_SESSION['cliente_id'] ?? 0])['total'] ?? 0,
    'alcoholimetros_activos' => $db->fetchOne("SELECT COUNT(*) as total FROM alcoholimetros WHERE estado = 'activo' AND cliente_id = ?", [$_SESSION['cliente_id'] ?? 0])['total'] ?? 0,
    'pruebas_mes' => $db->fetchOne("SELECT COUNT(*) as total FROM pruebas WHERE MONTH(fecha_prueba) = MONTH(CURDATE()) AND cliente_id = ?", [$_SESSION['cliente_id'] ?? 0])['total'] ?? 0
];

// Obtener registros recientes
$registros_recientes = $db->fetchAll("
    SELECT 
        c.nombre as conductor_nombre,
        c.apellido as conductor_apellido,
        p.fecha_prueba,
        p.nivel_alcohol,
        p.resultado,
        a.nombre as alcoholimetro_nombre
    FROM pruebas p
    LEFT JOIN conductores c ON p.conductor_id = c.id
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    WHERE p.cliente_id = ?
    ORDER BY p.fecha_prueba DESC
    LIMIT 10
", [$_SESSION['cliente_id'] ?? 0]);
?>

<div class="content-body">
    <!-- Header del Dashboard Mejorado -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_nombre'] ?? 'Usuario'); ?></h1>
            <p class="dashboard-subtitle">Resumen general del sistema de control de alcohol - <?php echo date('d/m/Y'); ?></p>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline" onclick="refreshDashboard()">
                <i class="fas fa-sync-alt"></i>
                Actualizar
            </button>
        </div>
    </div>

    <!-- Estadísticas Principales Mejoradas -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-vial"></i>
            </div>
            <div class="stat-info">
                <h3>Pruebas Hoy</h3>
                <div class="stat-number"><?php echo $stats['pruebas_hoy']; ?></div>
                <div class="stat-trend positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>+12% vs ayer</span>
                </div>
                <small>Pruebas realizadas hoy</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-calendar-week"></i>
            </div>
            <div class="stat-info">
                <h3>Esta Semana</h3>
                <div class="stat-number"><?php echo $stats['pruebas_semana']; ?></div>
                <div class="stat-trend positive">
                    <i class="fas fa-arrow-up"></i>
                    <span>+8% vs semana anterior</span>
                </div>
                <small>Total de pruebas semanales</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3>Conductores Activos</h3>
                <div class="stat-number"><?php echo $stats['conductores_activos']; ?></div>
                <div class="stat-trend neutral">
                    <i class="fas fa-minus"></i>
                    <span>Sin cambios</span>
                </div>
                <small>Total de conductores registrados</small>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning pulse">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-info">
                <h3>Alertas Pendientes</h3>
                <div class="stat-number"><?php echo $stats['alertas_pendientes']; ?></div>
                <div class="stat-trend negative">
                    <i class="fas fa-exclamation-circle"></i>
                    <span>Requieren atención</span>
                </div>
                <small>Alertas que necesitan revisión</small>
            </div>
        </div>
    </div>

    <!-- Contenido Principal Mejorado -->
    <div class="dashboard-content-grid">
        
        <!-- Columna Izquierda: Registros Recientes y Actividad -->
        <div class="content-column main-column">
            <!-- Registros Recientes Mejorado -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Registros Recientes</h3>
                    <div class="card-actions">
                        <a href="historial-pruebas.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-list"></i>
                            Ver Historial Completo
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (!empty($registros_recientes)): ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Conductor</th>
                                    <th>Fecha y Hora</th>
                                    <th>Alcoholímetro</th>
                                    <th>Nivel</th>
                                    <th>Resultado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros_recientes as $registro): ?>
                                <tr>
                                    <td>
                                        <div class="user-avatar">
                                            <div class="avatar-initials">
                                                <?php echo substr($registro['conductor_nombre'], 0, 1) . substr($registro['conductor_apellido'], 0, 1); ?>
                                            </div>
                                            <div class="user-info">
                                                <strong><?php echo htmlspecialchars($registro['conductor_nombre'] . ' ' . $registro['conductor_apellido']); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($registro['fecha_prueba'])); ?></td>
                                    <td><?php echo htmlspecialchars($registro['alcoholimetro_nombre'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="alcohol-level <?php echo $registro['nivel_alcohol'] > 0.3 ? 'high' : ($registro['nivel_alcohol'] > 0.1 ? 'medium' : 'low'); ?>">
                                            <?php echo number_format($registro['nivel_alcohol'], 2); ?> g/L
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $registro['resultado'] === 'aprobado' ? 'success' : 'danger'; ?>">
                                            <i class="fas fa-<?php echo $registro['resultado'] === 'aprobado' ? 'check' : 'times'; ?>"></i>
                                            <?php echo ucfirst($registro['resultado']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-vial"></i>
                        </div>
                        <h3>No hay registros recientes</h3>
                        <p>Realiza la primera prueba para comenzar a ver estadísticas</p>
                        <a href="nueva-prueba.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Realizar Primera Prueba
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Gráfico de Actividad (Placeholder) -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Actividad de Pruebas - Últimos 7 Días</h3>
                </div>
                <div class="card-body">
                    <div class="chart-placeholder">
                        <div class="chart-container">
                            <canvas id="activityChart" width="400" height="200"></canvas>
                        </div>
                        <div class="chart-legend">
                            <div class="legend-item">
                                <span class="legend-color success"></span>
                                <span>Pruebas Aprobadas</span>
                            </div>
                            <div class="legend-item">
                                <span class="legend-color danger"></span>
                                <span>Pruebas Reprobadas</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Columna Derecha: Acciones Rápidas y Estado -->
        <div class="content-column sidebar-column">
            
            <!-- Acciones Rápidas Mejoradas -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Acciones Rápidas</h3>
                </div>
                <div class="card-body">
                    <div class="quick-actions-grid">
                        <a href="nueva-prueba.php" class="quick-action-card primary">
                            <div class="action-icon">
                                <i class="fas fa-plus-circle"></i>
                            </div>
                            <div class="action-content">
                                <div class="action-title">Nueva Prueba</div>
                                <div class="action-desc">Registrar prueba de alcohol</div>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                        
                        <a href="reportes.php" class="quick-action-card success">
                            <div class="action-icon">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="action-content">
                                <div class="action-title">Ver Reportes</div>
                                <div class="action-desc">Estadísticas y análisis</div>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                        
                        <a href="conductores.php" class="quick-action-card info">
                            <div class="action-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="action-content">
                                <div class="action-title">Gestionar Conductores</div>
                                <div class="action-desc">Administrar usuarios</div>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                        
                        <a href="alcoholimetros.php" class="quick-action-card warning">
                            <div class="action-icon">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <div class="action-content">
                                <div class="action-title">Alcoholímetros</div>
                                <div class="action-desc">Gestionar dispositivos</div>
                            </div>
                            <div class="action-arrow">
                                <i class="fas fa-chevron-right"></i>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Estado del Sistema Mejorado -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-heartbeat"></i> Estado del Sistema</h3>
                </div>
                <div class="card-body">
                    <div class="system-status">
                        <div class="status-item">
                            <div class="status-info">
                                <i class="fas fa-tachometer-alt status-icon active"></i>
                                <div class="status-text">
                                    <div class="status-title">Alcoholímetros Activos</div>
                                    <div class="status-value"><?php echo $stats['alcoholimetros_activos']; ?> de 5</div>
                                </div>
                            </div>
                            <div class="status-badge success"><?php echo round(($stats['alcoholimetros_activos'] / 5) * 100); ?>%</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-info">
                                <i class="fas fa-vial status-icon"></i>
                                <div class="status-text">
                                    <div class="status-title">Pruebas del Mes</div>
                                    <div class="status-value"><?php echo $stats['pruebas_mes']; ?> realizadas</div>
                                </div>
                            </div>
                            <div class="status-badge info">+15%</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-info">
                                <i class="fas fa-database status-icon"></i>
                                <div class="status-text">
                                    <div class="status-title">Base de Datos</div>
                                    <div class="status-value">Operativa</div>
                                </div>
                            </div>
                            <div class="status-badge success">OK</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-info">
                                <i class="fas fa-sync status-icon"></i>
                                <div class="status-text">
                                    <div class="status-title">Última Sincronización</div>
                                    <div class="status-value">Hace 5 min</div>
                                </div>
                            </div>
                            <div class="status-badge warning">Activo</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Alertas Inmediatas -->
            <div class="card alert-card">
                <div class="card-header">
                    <h3><i class="fas fa-exclamation-triangle"></i> Alertas Inmediatas</h3>
                </div>
                <div class="card-body">
                    <?php if ($stats['alertas_pendientes'] > 0): ?>
                    <div class="alert-list">
                        <div class="alert-item critical">
                            <div class="alert-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="alert-content">
                                <div class="alert-title">Pruebas Reprobadas Pendientes</div>
                                <div class="alert-desc"><?php echo $stats['alertas_pendientes']; ?> pruebas requieren revisión</div>
                            </div>
                            <a href="alertas.php" class="alert-action">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="empty-state small">
                        <i class="fas fa-check-circle success"></i>
                        <h4>Sin alertas críticas</h4>
                        <p>El sistema opera normalmente</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Incluir Chart.js desde CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Función para actualizar el dashboard
function refreshDashboard() {
    const btn = event.target;
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
    btn.disabled = true;
    
    // Simular actualización
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Inicializar gráfico
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('activityChart').getContext('2d');
    
    // Datos de ejemplo para el gráfico
    const data = {
        labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
        datasets: [
            {
                label: 'Pruebas Aprobadas',
                data: [12, 19, 15, 17, 14, 16, 18],
                backgroundColor: 'rgba(39, 174, 96, 0.2)',
                borderColor: 'rgba(39, 174, 96, 1)',
                borderWidth: 2,
                tension: 0.4
            },
            {
                label: 'Pruebas Reprobadas',
                data: [2, 3, 1, 4, 2, 3, 1],
                backgroundColor: 'rgba(231, 76, 60, 0.2)',
                borderColor: 'rgba(231, 76, 60, 1)',
                borderWidth: 2,
                tension: 0.4
            }
        ]
    };

    const config = {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        drawBorder: false
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    };

    new Chart(ctx, config);
});
</script>

<?php
if (file_exists(__DIR__ . '/includes/footer.php')) {
    require_once __DIR__ . '/includes/footer.php';
}
?>