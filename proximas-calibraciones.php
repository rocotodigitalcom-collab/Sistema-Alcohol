<?php
// proximas-calibraciones.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Próximas Calibraciones';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'proximas-calibraciones.php' => 'Próximas Calibraciones'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// Obtener calibraciones próximas y vencidas (próximos 30 días y vencidas)
$calibraciones_proximas = $db->fetchAll("
    SELECT *, 
           DATEDIFF(proxima_calibracion, CURDATE()) as dias_restantes,
           CASE 
               WHEN proxima_calibracion < CURDATE() THEN 'vencida'
               WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 7 THEN 'critica'
               WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 30 THEN 'advertencia'
               ELSE 'normal'
           END as prioridad
    FROM alcoholimetros 
    WHERE cliente_id = ? 
      AND proxima_calibracion IS NOT NULL
      AND (proxima_calibracion < CURDATE() OR DATEDIFF(proxima_calibracion, CURDATE()) <= 30)
    ORDER BY 
        CASE 
            WHEN proxima_calibracion < CURDATE() THEN 0
            WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 7 THEN 1
            ELSE 2
        END,
        proxima_calibracion ASC
", [$cliente_id]);

// Obtener calibraciones recientes (últimos 30 días)
$calibraciones_recientes = $db->fetchAll("
    SELECT *, 
           DATEDIFF(CURDATE(), fecha_calibracion) as dias_desde_calibracion
    FROM alcoholimetros 
    WHERE cliente_id = ? 
      AND fecha_calibracion IS NOT NULL
      AND fecha_calibracion >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY fecha_calibracion DESC
", [$cliente_id]);

// Estadísticas para el dashboard
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_equipos,
        SUM(CASE WHEN proxima_calibracion < CURDATE() THEN 1 ELSE 0 END) as vencidas,
        SUM(CASE WHEN DATEDIFF(proxima_calibracion, CURDATE()) BETWEEN 1 AND 7 THEN 1 ELSE 0 END) as criticas,
        SUM(CASE WHEN DATEDIFF(proxima_calibracion, CURDATE()) BETWEEN 8 AND 30 THEN 1 ELSE 0 END) as advertencias,
        SUM(CASE WHEN fecha_calibracion >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recientes
    FROM alcoholimetros 
    WHERE cliente_id = ?
", [$cliente_id]);

// Alertas críticas (vencidas y próximas 7 días)
$alertas_criticas = array_filter($calibraciones_proximas, function($calibracion) {
    return $calibracion['prioridad'] === 'vencida' || $calibracion['prioridad'] === 'critica';
});
?>

<div class="content-body">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Monitoreo de calibraciones próximas a vencer y vencidas</p>
        </div>
        <div class="header-actions">
            <a href="calibraciones.php" class="btn btn-primary">
                <i class="fas fa-calendar-alt"></i>
                Gestión Completa
            </a>
        </div>
    </div>

    <!-- Alertas Críticas -->
    <?php if (!empty($alertas_criticas)): ?>
    <div class="alert alert-danger">
        <div class="alert-header">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Alertas Críticas</strong>
            <span class="badge"><?php echo count($alertas_criticas); ?></span>
        </div>
        <p>Tienes <?php echo count($alertas_criticas); ?> calibración(es) vencidas o próximas a vencer en menos de 7 días que requieren atención inmediata.</p>
    </div>
    <?php endif; ?>

    <!-- Panel de Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas['vencidas']; ?></h3>
                <p>Vencidas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas['criticas']; ?></h3>
                <p>Críticas (≤7 días)</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon info">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas['advertencias']; ?></h3>
                <p>Advertencia (8-30 días)</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-double"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas['recientes']; ?></h3>
                <p>Recientes (30 días)</p>
            </div>
        </div>
    </div>

    <div class="calendar-container">
        <!-- Calibraciones Próximas y Vencidas -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-times"></i> Calibraciones que Requieren Atención</h3>
                <div class="card-actions">
                    <span class="badge"><?php echo count($calibraciones_proximas); ?> equipos</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($calibraciones_proximas)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Prioridad</th>
                                <th>N° Serie</th>
                                <th>Nombre Activo</th>
                                <th>Modelo/Marca</th>
                                <th>Última Calibración</th>
                                <th>Próxima Calibración</th>
                                <th>Días</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calibraciones_proximas as $calibracion): 
                                $dias_restantes = $calibracion['dias_restantes'];
                                $prioridad = $calibracion['prioridad'];
                                
                                // Configuración según prioridad
                                switch ($prioridad) {
                                    case 'vencida':
                                        $clase_prioridad = 'danger';
                                        $texto_prioridad = 'Vencida';
                                        $icono_prioridad = 'fa-exclamation-triangle';
                                        $texto_dias = abs($dias_restantes) . ' días de retraso';
                                        break;
                                    case 'critica':
                                        $clase_prioridad = 'danger';
                                        $texto_prioridad = 'Crítica';
                                        $icono_prioridad = 'fa-exclamation-circle';
                                        $texto_dias = $dias_restantes . ' días';
                                        break;
                                    case 'advertencia':
                                        $clase_prioridad = 'warning';
                                        $texto_prioridad = 'Advertencia';
                                        $icono_prioridad = 'fa-clock';
                                        $texto_dias = $dias_restantes . ' días';
                                        break;
                                    default:
                                        $clase_prioridad = 'info';
                                        $texto_prioridad = 'Normal';
                                        $icono_prioridad = 'fa-info-circle';
                                        $texto_dias = $dias_restantes . ' días';
                                }
                            ?>
                            <tr class="priority-<?php echo $prioridad; ?>">
                                <td>
                                    <span class="priority-badge <?php echo $clase_prioridad; ?>">
                                        <i class="fas <?php echo $icono_prioridad; ?>"></i>
                                        <?php echo $texto_prioridad; ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($calibracion['numero_serie']); ?></td>
                                <td><?php echo htmlspecialchars($calibracion['nombre_activo']); ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($calibracion['modelo'] ?? 'N/A'); ?> / 
                                        <?php echo htmlspecialchars($calibracion['marca'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($calibracion['fecha_calibracion']): ?>
                                        <?php echo date('d/m/Y', strtotime($calibracion['fecha_calibracion'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No calibrado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($calibracion['proxima_calibracion'])); ?>
                                </td>
                                <td>
                                    <span class="dias-counter <?php echo $clase_prioridad; ?>">
                                        <?php echo $texto_dias; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $calibracion['estado'] === 'activo' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($calibracion['estado']); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="calibraciones.php?editar=<?php echo $calibracion['id']; ?>" class="btn-icon primary" title="Programar Calibración">
                                        <i class="fas fa-calendar-plus"></i>
                                    </a>
                                    <a href="calibraciones.php?calibrar=<?php echo $calibracion['id']; ?>" 
                                       class="btn-icon success" 
                                       title="Registrar Calibración Hoy"
                                       onclick="return confirm('¿Registrar calibración para hoy?')">
                                        <i class="fas fa-sync-alt"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state success">
                    <div class="empty-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>¡Excelente! No hay calibraciones pendientes</h3>
                    <p>Todos los alcoholímetros están al día con sus calibraciones</p>
                    <a href="calibraciones.php" class="btn btn-primary">
                        <i class="fas fa-eye"></i>
                        Ver Todas las Calibraciones
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Calibraciones Recientes -->
        <?php if (!empty($calibraciones_recientes)): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Calibraciones Recientes (Últimos 30 días)</h3>
                <div class="card-actions">
                    <span class="badge"><?php echo count($calibraciones_recientes); ?> equipos</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>N° Serie</th>
                                <th>Nombre Activo</th>
                                <th>Modelo/Marca</th>
                                <th>Fecha Calibración</th>
                                <th>Próxima Calibración</th>
                                <th>Días desde Calibración</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($calibraciones_recientes as $calibracion): 
                                $dias_desde = $calibracion['dias_desde_calibracion'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($calibracion['numero_serie']); ?></td>
                                <td><?php echo htmlspecialchars($calibracion['nombre_activo']); ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($calibracion['modelo'] ?? 'N/A'); ?> / 
                                        <?php echo htmlspecialchars($calibracion['marca'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <?php echo date('d/m/Y', strtotime($calibracion['fecha_calibracion'])); ?>
                                </td>
                                <td>
                                    <?php if ($calibracion['proxima_calibracion']): ?>
                                        <?php echo date('d/m/Y', strtotime($calibracion['proxima_calibracion'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No programada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-success">
                                        <i class="fas fa-check-circle"></i>
                                        <?php echo $dias_desde; ?> días
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $calibracion['estado'] === 'activo' ? 'success' : 'secondary'; ?>">
                                        <?php echo ucfirst($calibracion['estado']); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Panel de Resumen -->
        <div class="summary-cards">
            <div class="summary-card total">
                <div class="summary-icon">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <div class="summary-content">
                    <h3><?php echo $estadisticas['total_equipos']; ?></h3>
                    <p>Total de Alcoholímetros</p>
                </div>
            </div>
            <div class="summary-card attention">
                <div class="summary-icon">
                    <i class="fas fa-bell"></i>
                </div>
                <div class="summary-content">
                    <h3><?php echo count($calibraciones_proximas); ?></h3>
                    <p>Requieren Atención</p>
                </div>
            </div>
            <div class="summary-card upcoming">
                <div class="summary-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="summary-content">
                    <h3><?php echo $estadisticas['advertencias']; ?></h3>
                    <p>Próximos 30 Días</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ESTILOS ESPECÍFICOS PARA PRÓXIMAS CALIBRACIONES -->
<style>
/* Contenedor principal */
.calendar-container {
    margin-top: 1.5rem;
    width: 100%;
}

/* Panel de estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-icon.primary { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
.stat-icon.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
.stat-icon.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
.stat-icon.danger { background: rgba(231, 76, 60, 0.1); color: var(--danger); }
.stat-icon.info { background: rgba(52, 152, 219, 0.1); color: var(--primary); }

.stat-info h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-info p {
    margin: 0.25rem 0 0 0;
    color: var(--gray);
    font-size: 0.9rem;
}

/* Alertas mejoradas */
.alert {
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    border: 1px solid transparent;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.alert-danger {
    background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(231, 76, 60, 0.05));
    border-color: rgba(231, 76, 60, 0.3);
    color: var(--danger);
}

.alert-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
    font-size: 1.1rem;
    font-weight: 600;
}

.alert-header .badge {
    background: var(--danger);
    color: white;
}

/* Badges de prioridad */
.priority-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    min-width: 100px;
    justify-content: center;
}

.priority-badge.danger {
    background: rgba(231, 76, 60, 0.15);
    color: var(--danger);
    border: 1px solid rgba(231, 76, 60, 0.3);
    animation: pulse 2s infinite;
}

.priority-badge.warning {
    background: rgba(243, 156, 18, 0.15);
    color: var(--warning);
    border: 1px solid rgba(243, 156, 18, 0.3);
}

.priority-badge.info {
    background: rgba(52, 152, 219, 0.15);
    color: var(--primary);
    border: 1px solid rgba(52, 152, 219, 0.3);
}

/* Contador de días */
.dias-counter {
    font-weight: 600;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.8rem;
}

.dias-counter.danger {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger);
}

.dias-counter.warning {
    background: rgba(243, 156, 18, 0.1);
    color: var(--warning);
}

.dias-counter.info {
    background: rgba(52, 152, 219, 0.1);
    color: var(--primary);
}

/* Filas con prioridad */
.priority-vencida {
    background: rgba(231, 76, 60, 0.03) !important;
    border-left: 4px solid var(--danger);
}

.priority-critica {
    background: rgba(231, 76, 60, 0.02) !important;
    border-left: 4px solid var(--danger);
}

.priority-advertencia {
    background: rgba(243, 156, 18, 0.02) !important;
    border-left: 4px solid var(--warning);
}

.priority-normal {
    background: rgba(52, 152, 219, 0.02) !important;
    border-left: 4px solid var(--primary);
}

/* Estados vacíos mejorados */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray);
    border-radius: 12px;
    margin: 1rem 0;
}

.empty-state.success {
    background: rgba(39, 174, 96, 0.05);
    border: 1px solid rgba(39, 174, 96, 0.2);
}

.empty-state .empty-icon {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.7;
}

.empty-state.success .empty-icon {
    color: var(--success);
}

.empty-state h3 {
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.empty-state p {
    margin-bottom: 2rem;
    font-size: 1rem;
    opacity: 0.8;
}

/* Panel de resumen */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.summary-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.summary-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
}

.summary-card.total {
    border-top: 4px solid var(--primary);
}

.summary-card.attention {
    border-top: 4px solid var(--danger);
}

.summary-card.upcoming {
    border-top: 4px solid var(--warning);
}

.summary-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    background: var(--light);
}

.summary-card.total .summary-icon { color: var(--primary); }
.summary-card.attention .summary-icon { color: var(--danger); }
.summary-card.upcoming .summary-icon { color: var(--warning); }

.summary-content h3 {
    margin: 0;
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--dark);
}

.summary-content p {
    margin: 0.25rem 0 0 0;
    color: var(--gray);
    font-size: 0.85rem;
}

/* Tabla de datos */
.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin: 0;
}

.data-table th {
    background: var(--light);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    border-bottom: 2px solid var(--border);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
    vertical-align: middle;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background: rgba(52, 152, 219, 0.04);
}

/* Botones de acción */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--light);
    color: var(--dark);
    text-decoration: none;
    transition: all 0.3s ease;
}

.btn-icon:hover {
    transform: translateY(-2px);
}

.btn-icon.primary:hover {
    background: var(--primary);
    color: white;
}

.btn-icon.success:hover {
    background: var(--success);
    color: white;
}

/* Badges de estado */
.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    min-width: 80px;
    justify-content: center;
}

.status-badge.success {
    background: rgba(39, 174, 96, 0.15);
    color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.3);
}

.status-badge.secondary {
    background: rgba(149, 165, 166, 0.15);
    color: var(--gray);
    border: 1px solid rgba(149, 165, 166, 0.3);
}

/* Badge contador */
.badge {
    padding: 0.4rem 0.8rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Tabla responsive */
.table-responsive {
    overflow-x: auto;
    border-radius: 12px;
}

/* Colores de texto */
.text-danger { 
    color: var(--danger) !important; 
    font-weight: 600;
}
.text-warning { 
    color: var(--warning) !important; 
    font-weight: 600;
}
.text-success { 
    color: var(--success) !important; 
    font-weight: 600;
}
.text-muted { 
    color: var(--gray) !important; 
    opacity: 0.7;
}

/* Header del dashboard */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1.5rem 0;
    border-bottom: 1px solid var(--border);
}

.welcome-section h1 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-size: 1.8rem;
    font-weight: 700;
}

.dashboard-subtitle {
    margin: 0;
    color: var(--gray);
    font-size: 1rem;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

/* Card improvements */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--light);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    color: var(--dark);
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-body {
    padding: 1.5rem;
}

/* Botones */
.btn {
    padding: 0.875rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-color: var(--primary);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
}

/* Animaciones */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(231, 76, 60, 0); }
    100% { box-shadow: 0 0 0 0 rgba(231, 76, 60, 0); }
}

.card {
    animation: fadeIn 0.5s ease-out;
}

.data-table tr {
    animation: fadeIn 0.3s ease-out;
}

.stat-card, .summary-card {
    animation: fadeIn 0.5s ease-out;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .data-table {
        font-size: 0.85rem;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-cards {
        grid-template-columns: 1fr;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .card-actions {
        align-self: flex-start;
    }
}
</style>

<script>
// Funciones JavaScript para el módulo
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar contadores de días en tiempo real
    function actualizarContadoresDias() {
        const filas = document.querySelectorAll('.data-table tbody tr');
        filas.forEach(fila => {
            const celdaDias = fila.querySelector('.dias-counter');
            if (celdaDias) {
                const texto = celdaDias.textContent;
                // Aquí podrías agregar lógica para actualizar en tiempo real si es necesario
            }
        });
    }

    // Inicializar tooltips si se usan
    const tooltips = document.querySelectorAll('[title]');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            // Podrías implementar tooltips personalizados aquí
        });
    });

    // Auto-scroll a alertas críticas si existen
    const alertasCriticas = document.querySelector('.alert-danger');
    if (alertasCriticas) {
        alertasCriticas.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Configuración de notificaciones (ejemplo)
    function verificarNotificaciones() {
        const alertas = document.querySelectorAll('.priority-vencida, .priority-critica');
        if (alertas.length > 0 && Notification.permission === 'granted') {
            // Podrías mostrar notificaciones del navegador aquí
        }
    }

    // Solicitar permisos para notificaciones
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }

    // Inicializar
    actualizarContadoresDias();
    verificarNotificaciones();
});

// Función para exportar reporte
function exportarReporte() {
    // Implementar lógica de exportación aquí
    alert('Funcionalidad de exportación en desarrollo');
}

// Función para imprimir reporte
function imprimirReporte() {
    window.print();
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>