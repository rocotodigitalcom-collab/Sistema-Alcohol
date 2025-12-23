<?php
// index.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Dashboard';
$breadcrumbs = ['index.php' => 'Dashboard'];

require_once __DIR__ . '/includes/header.php';

// Helpers
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function pct_change($current, $previous){
    $current = (float)$current;
    $previous = (float)$previous;
    if ($previous <= 0) return ($current > 0) ? 100.0 : 0.0;
    return (($current - $previous) / $previous) * 100.0;
}

function fmt_pct($v){
    $v = (float)$v;
    $sign = ($v > 0) ? '+' : '';
    return $sign . number_format($v, 0) . '%';
}

// Obtener estadísticas reales desde la base de datos
$db = new Database();

$cliente_id  = $_SESSION['cliente_id'] ?? 0;
$user_nombre = $_SESSION['user_nombre'] ?? 'Usuario';

$dashboard_error = '';

try {

    // ====== KPIs BASE (corrigiendo tablas inexistentes) ======

    $pruebas_hoy = (int)($db->fetchOne(
        "SELECT COUNT(*) as total FROM pruebas WHERE DATE(fecha_prueba) = CURDATE() AND cliente_id = ?",
        [$cliente_id]
    )['total'] ?? 0);

    $pruebas_ayer = (int)($db->fetchOne(
        "SELECT COUNT(*) as total FROM pruebas WHERE DATE(fecha_prueba) = DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND cliente_id = ?",
        [$cliente_id]
    )['total'] ?? 0);

    // Semana ISO (modo 1)
    $pruebas_semana = (int)($db->fetchOne(
        "SELECT COUNT(*) as total FROM pruebas WHERE YEARWEEK(fecha_prueba, 1) = YEARWEEK(CURDATE(), 1) AND cliente_id = ?",
        [$cliente_id]
    )['total'] ?? 0);

    $pruebas_semana_anterior = (int)($db->fetchOne(
        "SELECT COUNT(*) as total FROM pruebas WHERE YEARWEEK(fecha_prueba, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1) AND cliente_id = ?",
        [$cliente_id]
    )['total'] ?? 0);

    $pruebas_mes = (int)($db->fetchOne(
        "SELECT COUNT(*) as total FROM pruebas WHERE YEAR(fecha_prueba) = YEAR(CURDATE()) AND MONTH(fecha_prueba) = MONTH(CURDATE()) AND cliente_id = ?",
        [$cliente_id]
    )['total'] ?? 0);

    $pruebas_mes_anterior = (int)($db->fetchOne(
        "SELECT COUNT(*) as total FROM pruebas WHERE YEAR(fecha_prueba) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(fecha_prueba) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND cliente_id = ?",
        [$cliente_id]
    )['total'] ?? 0);

    // En tu BD real, "conductores" no existe: se usan usuarios con rol conductor
    $conductores_activos = (int)($db->fetchOne(
        "SELECT COUNT(*) as total FROM usuarios WHERE estado = 1 AND rol = 'conductor' AND cliente_id = ?",
        [$cliente_id]
    )['total'] ?? 0);

    // En tu BD real, "alertas" no existe: usaremos solicitudes_retest pendientes asociadas a pruebas del cliente
    $alertas_pendientes = (int)($db->fetchOne(
        "SELECT COUNT(*) AS total
         FROM solicitudes_retest sr
         INNER JOIN pruebas p ON p.id = sr.prueba_original_id
         WHERE p.cliente_id = ?
           AND sr.estado = 'pendiente'",
        [$cliente_id]
    )['total'] ?? 0);

    $alcoholimetros_activos = (int)($db->fetchOne(
        "SELECT COUNT(*) as total FROM alcoholimetros WHERE estado = 'activo' AND cliente_id = ?",
        [$cliente_id]
    )['total'] ?? 0);

    $alcoholimetros_total = (int)($db->fetchOne(
        "SELECT COUNT(*) as total FROM alcoholimetros WHERE cliente_id = ?",
        [$cliente_id]
    )['total'] ?? 0);

    // ====== CALIBRACIONES (proxima_calibracion en alcoholimetros) ======
    // (Sin inventar columnas: se asume que proxima_calibracion existe tal como mencionaste)
    $calibraciones_vencidas = (int)($db->fetchOne(
        "SELECT COUNT(*) AS total
         FROM alcoholimetros
         WHERE cliente_id = ?
           AND proxima_calibracion IS NOT NULL
           AND proxima_calibracion < CURDATE()",
        [$cliente_id]
    )['total'] ?? 0);

    $calibraciones_proximas = (int)($db->fetchOne(
        "SELECT COUNT(*) AS total
         FROM alcoholimetros
         WHERE cliente_id = ?
           AND proxima_calibracion IS NOT NULL
           AND proxima_calibracion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)",
        [$cliente_id]
    )['total'] ?? 0);

    $calibraciones_pendientes = $calibraciones_vencidas + $calibraciones_proximas;

    $lista_calibraciones = $db->fetchAll(
        "SELECT nombre_activo, codigo, proxima_calibracion
         FROM alcoholimetros
         WHERE cliente_id = ?
           AND proxima_calibracion IS NOT NULL
           AND proxima_calibracion <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
         ORDER BY proxima_calibracion ASC
         LIMIT 5",
        [$cliente_id]
    );

    // ====== Registros recientes (corrigiendo join: conductores -> usuarios) ======
    $registros_recientes = $db->fetchAll("
        SELECT
            u.nombre as conductor_nombre,
            u.apellido as conductor_apellido,
            p.fecha_prueba,
            p.nivel_alcohol,
            p.resultado,
            a.nombre_activo as alcoholimetro_nombre
        FROM pruebas p
        LEFT JOIN usuarios u ON p.conductor_id = u.id
        LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
        WHERE p.cliente_id = ?
        ORDER BY p.fecha_prueba DESC
        LIMIT 10
    ", [$cliente_id]);

    // ====== Último registro (para "Última Sincronización") ======
    $ultima_prueba = $db->fetchOne(
        "SELECT MAX(fecha_prueba) AS last_date FROM pruebas WHERE cliente_id = ?",
        [$cliente_id]
    );
    $ultima_prueba_dt = $ultima_prueba['last_date'] ?? null;

    // ====== Trends reales (reemplaza +12% y +8% fijos) ======
    $trend_hoy = pct_change($pruebas_hoy, $pruebas_ayer);
    $trend_semana = pct_change($pruebas_semana, $pruebas_semana_anterior);
    $trend_mes = pct_change($pruebas_mes, $pruebas_mes_anterior);

    // ====== Datos reales para gráfico (últimos 7 días) ======
    $raw_chart = $db->fetchAll("
        SELECT
            DATE(fecha_prueba) AS dia,
            SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) AS aprobadas,
            SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) AS reprobadas
        FROM pruebas
        WHERE cliente_id = ?
          AND DATE(fecha_prueba) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(fecha_prueba)
        ORDER BY dia ASC
    ", [$cliente_id]);

    $map = [];
    foreach ($raw_chart as $r) {
        $map[$r['dia']] = [
            'aprobadas' => (int)($r['aprobadas'] ?? 0),
            'reprobadas' => (int)($r['reprobadas'] ?? 0),
        ];
    }

    $chart_labels = [];
    $chart_aprob = [];
    $chart_reprob = [];

    for ($i = 6; $i >= 0; $i--) {
        $dia = date('Y-m-d', strtotime("-{$i} day"));
        $chart_labels[] = date('d/m', strtotime($dia));
        $chart_aprob[] = $map[$dia]['aprobadas'] ?? 0;
        $chart_reprob[] = $map[$dia]['reprobadas'] ?? 0;
    }

    // ====== Stats (manteniendo estructura original del archivo) ======
    $stats = [
        'pruebas_hoy' => $pruebas_hoy,
        'pruebas_semana' => $pruebas_semana,
        'conductores_activos' => $conductores_activos,
        'alertas_pendientes' => $alertas_pendientes,
        'alcoholimetros_activos' => $alcoholimetros_activos,
        'alcoholimetros_total' => $alcoholimetros_total,
        'pruebas_mes' => $pruebas_mes,
        'calibraciones_pendientes' => $calibraciones_pendientes,
        'calibraciones_vencidas' => $calibraciones_vencidas,
        'calibraciones_proximas' => $calibraciones_proximas,
    ];

} catch (Exception $e) {
    $dashboard_error = "Error al cargar el dashboard: " . $e->getMessage();

    $stats = [
        'pruebas_hoy' => 0,
        'pruebas_semana' => 0,
        'conductores_activos' => 0,
        'alertas_pendientes' => 0,
        'alcoholimetros_activos' => 0,
        'alcoholimetros_total' => 0,
        'pruebas_mes' => 0,
        'calibraciones_pendientes' => 0,
        'calibraciones_vencidas' => 0,
        'calibraciones_proximas' => 0,
    ];

    $registros_recientes = [];
    $ultima_prueba_dt = null;

    $trend_hoy = 0;
    $trend_semana = 0;
    $trend_mes = 0;

    $chart_labels = [date('d/m')];
    $chart_aprob = [0];
    $chart_reprob = [0];

    $lista_calibraciones = [];
}
?>

<div class="content-body">
    <!-- Header del Dashboard Mejorado -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1>Bienvenido, <?php echo h($user_nombre); ?></h1>
            <p class="dashboard-subtitle">Resumen general del sistema de control de alcohol - <?php echo date('d/m/Y'); ?></p>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline" onclick="refreshDashboard(event)">
                <i class="fas fa-sync-alt"></i>
                Actualizar
            </button>
        </div>
    </div>

    <?php if (!empty($dashboard_error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php echo h($dashboard_error); ?>
        </div>
    <?php endif; ?>

    <!-- Estadísticas Principales Mejoradas -->
    <div class="dashboard-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-vial"></i>
            </div>
            <div class="stat-info">
                <h3>Pruebas Hoy</h3>
                <div class="stat-number"><?php echo (int)$stats['pruebas_hoy']; ?></div>
                <div class="stat-trend <?php echo ($trend_hoy >= 0 ? 'positive' : 'negative'); ?>">
                    <i class="fas fa-<?php echo ($trend_hoy >= 0 ? 'arrow-up' : 'arrow-down'); ?>"></i>
                    <span><?php echo h(fmt_pct($trend_hoy)); ?> vs ayer</span>
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
                <div class="stat-number"><?php echo (int)$stats['pruebas_semana']; ?></div>
                <div class="stat-trend <?php echo ($trend_semana >= 0 ? 'positive' : 'negative'); ?>">
                    <i class="fas fa-<?php echo ($trend_semana >= 0 ? 'arrow-up' : 'arrow-down'); ?>"></i>
                    <span><?php echo h(fmt_pct($trend_semana)); ?> vs semana anterior</span>
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
                <div class="stat-number"><?php echo (int)$stats['conductores_activos']; ?></div>
                <div class="stat-trend neutral">
                    <i class="fas fa-minus"></i>
                    <span>Estado actual</span>
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
                <div class="stat-number"><?php echo (int)$stats['alertas_pendientes']; ?></div>
                <div class="stat-trend <?php echo ((int)$stats['alertas_pendientes'] > 0 ? 'negative' : 'positive'); ?>">
                    <i class="fas fa-<?php echo ((int)$stats['alertas_pendientes'] > 0 ? 'exclamation-circle' : 'check-circle'); ?>"></i>
                    <span><?php echo ((int)$stats['alertas_pendientes'] > 0 ? 'Requieren atención' : 'Sin pendientes'); ?></span>
                </div>
                <small>Solicitudes de retest pendientes</small>
            </div>
        </div>

        <!-- NUEVO KPI (sin romper estética): Calibraciones -->
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-tools"></i>
            </div>
            <div class="stat-info">
                <h3>Calibraciones</h3>
                <div class="stat-number"><?php echo (int)$stats['calibraciones_pendientes']; ?></div>
                <div class="stat-trend <?php echo ((int)$stats['calibraciones_pendientes'] > 0 ? 'negative' : 'positive'); ?>">
                    <i class="fas fa-<?php echo ((int)$stats['calibraciones_pendientes'] > 0 ? 'exclamation-circle' : 'check-circle'); ?>"></i>
                    <span>
                        🔴 <?php echo (int)$stats['calibraciones_vencidas']; ?> · 🟠 <?php echo (int)$stats['calibraciones_proximas']; ?>
                    </span>
                </div>
                <small>Vencidas / Próximas (30 días)</small>
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
                                <?php
                                    $cn = (string)($registro['conductor_nombre'] ?? '');
                                    $ca = (string)($registro['conductor_apellido'] ?? '');
                                    $ini1 = ($cn !== '') ? mb_substr($cn, 0, 1, 'UTF-8') : 'N';
                                    $ini2 = ($ca !== '') ? mb_substr($ca, 0, 1, 'UTF-8') : 'A';
                                    $nivel = (float)($registro['nivel_alcohol'] ?? 0);
                                    $fecha = $registro['fecha_prueba'] ?? null;
                                    $resultado = (string)($registro['resultado'] ?? '');
                                ?>
                                <tr>
                                    <td>
                                        <div class="user-avatar">
                                            <div class="avatar-initials">
                                                <?php echo h($ini1 . $ini2); ?>
                                            </div>
                                            <div class="user-info">
                                                <strong><?php echo h(trim($cn . ' ' . $ca) ?: 'N/A'); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo $fecha ? date('d/m/Y H:i', strtotime($fecha)) : 'N/A'; ?></td>
                                    <td><?php echo h($registro['alcoholimetro_nombre'] ?? 'N/A'); ?></td>
                                    <td>
                                        <span class="alcohol-level <?php echo $nivel > 0.3 ? 'high' : ($nivel > 0.1 ? 'medium' : 'low'); ?>">
                                            <?php echo number_format($nivel, 2); ?> g/L
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $resultado === 'aprobado' ? 'success' : 'danger'; ?>">
                                            <i class="fas fa-<?php echo $resultado === 'aprobado' ? 'check' : 'times'; ?>"></i>
                                            <?php echo h(ucfirst($resultado ?: 'N/A')); ?>
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

            <!-- Gráfico de Actividad -->
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

            <!-- NUEVO BLOQUE: Calibraciones Próximas (misma estética de cards) -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-tools"></i> Calibraciones Próximas</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($lista_calibraciones)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Alcoholímetro</th>
                                        <th>Código</th>
                                        <th>Fecha</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($lista_calibraciones as $cal): ?>
                                        <?php
                                            $f = $cal['proxima_calibracion'] ?? null;
                                            $is_vencida = $f ? (strtotime($f) < strtotime(date('Y-m-d'))) : false;
                                        ?>
                                        <tr>
                                            <td><?php echo h($cal['nombre_activo'] ?? 'N/A'); ?></td>
                                            <td><?php echo h($cal['codigo'] ?? ''); ?></td>
                                            <td>
                                                <span class="status-badge <?php echo $is_vencida ? 'danger' : 'warning'; ?>">
                                                    <i class="fas fa-<?php echo $is_vencida ? 'exclamation-circle' : 'clock'; ?>"></i>
                                                    <?php echo $f ? date('d/m/Y', strtotime($f)) : 'N/A'; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="empty-state small">
                            <i class="fas fa-check-circle success"></i>
                            <h4>Todo al día</h4>
                            <p>No hay calibraciones vencidas o próximas (30 días)</p>
                        </div>
                    <?php endif; ?>
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
                                    <div class="status-value"><?php echo (int)$stats['alcoholimetros_activos']; ?> de <?php echo (int)$stats['alcoholimetros_total']; ?></div>
                                </div>
                            </div>
                            <?php
                                $pct = 0;
                                if ((int)$stats['alcoholimetros_total'] > 0) {
                                    $pct = round(((int)$stats['alcoholimetros_activos'] / (int)$stats['alcoholimetros_total']) * 100);
                                }
                                $pct_class = ($pct >= 80 ? 'success' : ($pct >= 50 ? 'warning' : 'danger'));
                            ?>
                            <div class="status-badge <?php echo $pct_class; ?>"><?php echo (int)$pct; ?>%</div>
                        </div>
                        
                        <div class="status-item">
                            <div class="status-info">
                                <i class="fas fa-vial status-icon"></i>
                                <div class="status-text">
                                    <div class="status-title">Pruebas del Mes</div>
                                    <div class="status-value"><?php echo (int)$stats['pruebas_mes']; ?> realizadas</div>
                                </div>
                            </div>
                            <div class="status-badge info"><?php echo h(fmt_pct($trend_mes)); ?></div>
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
                                    <div class="status-value">
                                        <?php
                                            if (!empty($ultima_prueba_dt)) {
                                                $mins = (int)floor((time() - strtotime($ultima_prueba_dt)) / 60);
                                                if ($mins < 1) echo 'Hace instantes';
                                                elseif ($mins < 60) echo 'Hace ' . $mins . ' min';
                                                else {
                                                    $hrs = (int)floor($mins / 60);
                                                    echo 'Hace ' . $hrs . ' h';
                                                }
                                            } else {
                                                echo 'Sin registros';
                                            }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="status-badge warning"><?php echo !empty($ultima_prueba_dt) ? 'Activo' : 'N/A'; ?></div>
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
                    <?php if ((int)$stats['alertas_pendientes'] > 0): ?>
                    <div class="alert-list">
                        <div class="alert-item critical">
                            <div class="alert-icon">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="alert-content">
                                <div class="alert-title">Solicitudes Retest Pendientes</div>
                                <div class="alert-desc"><?php echo (int)$stats['alertas_pendientes']; ?> solicitudes requieren revisión</div>
                            </div>
                            <a href="solicitudes-retest.php" class="alert-action">
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
function refreshDashboard(ev) {
    if (ev) ev.preventDefault();
    const btn = ev && ev.currentTarget ? ev.currentTarget : null;
    if (btn) {
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
        btn.disabled = true;
        setTimeout(() => { location.reload(); }, 800);
    } else {
        location.reload();
    }
}

// Inicializar gráfico
document.addEventListener('DOMContentLoaded', function() {
    const canvas = document.getElementById('activityChart');
    if (!canvas || typeof Chart === 'undefined') return;

    const ctx = canvas.getContext('2d');

    // Datos reales para el gráfico (desde PHP)
    const labels = <?php echo json_encode($chart_labels ?? ['Hoy'], JSON_UNESCAPED_UNICODE); ?>;
    const aprobadas = <?php echo json_encode($chart_aprob ?? [0], JSON_UNESCAPED_UNICODE); ?>;
    const reprobadas = <?php echo json_encode($chart_reprob ?? [0], JSON_UNESCAPED_UNICODE); ?>;

    const data = {
        labels: labels,
        datasets: [
            {
                label: 'Pruebas Aprobadas',
                data: aprobadas,
                backgroundColor: 'rgba(39, 174, 96, 0.2)',
                borderColor: 'rgba(39, 174, 96, 1)',
                borderWidth: 2,
                tension: 0.4
            },
            {
                label: 'Pruebas Reprobadas',
                data: reprobadas,
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
