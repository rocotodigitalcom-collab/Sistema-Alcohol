<?php
// estadisticas-mensuales.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Estadísticas Mensuales';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'estadisticas-mensuales.php' => 'Estadísticas Mensuales'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// PARÁMETROS DE FILTRADO
$mes = $_GET['mes'] ?? date('Y-m');
$mes_anterior = date('Y-m', strtotime($mes . ' -1 month'));
$mes_siguiente = date('Y-m', strtotime($mes . ' +1 month'));

// VALIDAR MES
if (!preg_match('/^\d{4}-\d{2}$/', $mes)) {
    $mes = date('Y-m');
}

// OBTENER ESTADÍSTICAS DEL MES ACTUAL
$estadisticas_mes_actual = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        SUM(CASE WHEN es_retest = 1 THEN 1 ELSE 0 END) as retests_realizados,
        AVG(nivel_alcohol) as promedio_alcohol,
        MAX(nivel_alcohol) as maximo_alcohol,
        MIN(nivel_alcohol) as minimo_alcohol,
        COUNT(DISTINCT conductor_id) as conductores_activos,
        COUNT(DISTINCT vehiculo_id) as vehiculos_activos,
        COUNT(DISTINCT alcoholimetro_id) as alcoholimetros_utilizados,
        SUM(CASE WHEN nivel_alcohol > 0.000 AND nivel_alcohol <= 0.025 THEN 1 ELSE 0 END) as nivel_advertencia,
        SUM(CASE WHEN nivel_alcohol > 0.025 AND nivel_alcohol <= 0.080 THEN 1 ELSE 0 END) as nivel_alto,
        SUM(CASE WHEN nivel_alcohol > 0.080 THEN 1 ELSE 0 END) as nivel_critico
    FROM pruebas 
    WHERE cliente_id = ? 
    AND YEAR(fecha_prueba) = ? 
    AND MONTH(fecha_prueba) = ?
", [$cliente_id, explode('-', $mes)[0], explode('-', $mes)[1]]);

// OBTENER ESTADÍSTICAS DEL MES ANTERIOR (PARA COMPARACIÓN)
$estadisticas_mes_anterior = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        AVG(nivel_alcohol) as promedio_alcohol
    FROM pruebas 
    WHERE cliente_id = ? 
    AND YEAR(fecha_prueba) = ? 
    AND MONTH(fecha_prueba) = ?
", [$cliente_id, explode('-', $mes_anterior)[0], explode('-', $mes_anterior)[1]]);

// EVOLUCIÓN DIARIA DEL MES
$evolucion_diaria = $db->fetchAll("
    SELECT 
        DATE(fecha_prueba) as fecha,
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        AVG(nivel_alcohol) as promedio_alcohol
    FROM pruebas 
    WHERE cliente_id = ? 
    AND YEAR(fecha_prueba) = ? 
    AND MONTH(fecha_prueba) = ?
    GROUP BY DATE(fecha_prueba)
    ORDER BY fecha
", [$cliente_id, explode('-', $mes)[0], explode('-', $mes)[1]]);

// DISTRIBUCIÓN POR DÍA DE LA SEMANA
$distribucion_semanal = $db->fetchAll("
    SELECT 
        DAYNAME(fecha_prueba) as dia_semana,
        DAYOFWEEK(fecha_prueba) as numero_dia,
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as tasa_reprobacion
    FROM pruebas 
    WHERE cliente_id = ? 
    AND YEAR(fecha_prueba) = ? 
    AND MONTH(fecha_prueba) = ?
    GROUP BY DAYNAME(fecha_prueba), DAYOFWEEK(fecha_prueba)
    ORDER BY DAYOFWEEK(fecha_prueba)
", [$cliente_id, explode('-', $mes)[0], explode('-', $mes)[1]]);

// HORARIOS MÁS ACTIVOS
$horarios_activos = $db->fetchAll("
    SELECT 
        HOUR(fecha_prueba) as hora,
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        AVG(nivel_alcohol) as promedio_alcohol
    FROM pruebas 
    WHERE cliente_id = ? 
    AND YEAR(fecha_prueba) = ? 
    AND MONTH(fecha_prueba) = ?
    GROUP BY HOUR(fecha_prueba)
    ORDER BY hora
", [$cliente_id, explode('-', $mes)[0], explode('-', $mes)[1]]);

// TOP 5 CONDUCTORES CON MÁS PRUEBAS
$top_conductores = $db->fetchAll("
    SELECT 
        u.id,
        CONCAT(u.nombre, ' ', u.apellido) as conductor_nombre,
        u.dni,
        COUNT(p.id) as total_pruebas,
        SUM(CASE WHEN p.resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(p.id)) * 100, 1) as tasa_reprobacion,
        AVG(p.nivel_alcohol) as promedio_alcohol
    FROM usuarios u
    INNER JOIN pruebas p ON u.id = p.conductor_id
    WHERE p.cliente_id = ? 
    AND YEAR(p.fecha_prueba) = ? 
    AND MONTH(p.fecha_prueba) = ?
    GROUP BY u.id, u.nombre, u.apellido, u.dni
    ORDER BY total_pruebas DESC
    LIMIT 5
", [$cliente_id, explode('-', $mes)[0], explode('-', $mes)[1]]);

// TOP 5 VEHÍCULOS MÁS UTILIZADOS
$top_vehiculos = $db->fetchAll("
    SELECT 
        v.id,
        v.placa,
        v.marca,
        v.modelo,
        COUNT(p.id) as total_pruebas,
        SUM(CASE WHEN p.resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        COUNT(DISTINCT p.conductor_id) as conductores_diferentes
    FROM vehiculos v
    INNER JOIN pruebas p ON v.id = p.vehiculo_id
    WHERE p.cliente_id = ? 
    AND YEAR(p.fecha_prueba) = ? 
    AND MONTH(p.fecha_prueba) = ?
    GROUP BY v.id, v.placa, v.marca, v.modelo
    ORDER BY total_pruebas DESC
    LIMIT 5
", [$cliente_id, explode('-', $mes)[0], explode('-', $mes)[1]]);

// ALCOHOLÍMETROS MÁS UTILIZADOS
$alcoholimetros_uso = $db->fetchAll("
    SELECT 
        a.id,
        a.nombre_activo,
        a.numero_serie,
        COUNT(p.id) as total_pruebas,
        AVG(p.nivel_alcohol) as promedio_alcohol,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas
    FROM alcoholimetros a
    LEFT JOIN pruebas p ON a.id = p.alcoholimetro_id
    WHERE p.cliente_id = ? 
    AND YEAR(p.fecha_prueba) = ? 
    AND MONTH(p.fecha_prueba) = ?
    GROUP BY a.id, a.nombre_activo, a.numero_serie
    ORDER BY total_pruebas DESC
    LIMIT 5
", [$cliente_id, explode('-', $mes)[0], explode('-', $mes)[1]]);

// TENDENCIA DE LOS ÚLTIMOS 6 MESES
$tendencia_meses = $db->fetchAll("
    SELECT 
        DATE_FORMAT(fecha_prueba, '%Y-%m') as mes,
        DATE_FORMAT(fecha_prueba, '%M %Y') as mes_nombre,
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as tasa_reprobacion,
        AVG(nivel_alcohol) as promedio_alcohol
    FROM pruebas 
    WHERE cliente_id = ? 
    AND fecha_prueba >= DATE_SUB(?, INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(fecha_prueba, '%Y-%m'), DATE_FORMAT(fecha_prueba, '%M %Y')
    ORDER BY mes DESC
    LIMIT 6
", [$cliente_id, $mes . '-01']);

// CALCULAR COMPARATIVAS
function calcularVariacion($actual, $anterior) {
    if ($anterior == 0) return $actual > 0 ? 100 : 0;
    return round((($actual - $anterior) / $anterior) * 100, 1);
}

$variacion_total = calcularVariacion(
    $estadisticas_mes_actual['total_pruebas'] ?? 0, 
    $estadisticas_mes_anterior['total_pruebas'] ?? 0
);

$variacion_aprobadas = calcularVariacion(
    $estadisticas_mes_actual['pruebas_aprobadas'] ?? 0, 
    $estadisticas_mes_anterior['pruebas_aprobadas'] ?? 0
);

$variacion_reprobadas = calcularVariacion(
    $estadisticas_mes_actual['pruebas_reprobadas'] ?? 0, 
    $estadisticas_mes_anterior['pruebas_reprobadas'] ?? 0
);

$variacion_promedio = calcularVariacion(
    $estadisticas_mes_actual['promedio_alcohol'] ?? 0, 
    $estadisticas_mes_anterior['promedio_alcohol'] ?? 0
);

// CALCULAR PORCENTAJES PARA BARRAS DE PROGRESO
$porcentaje_aprobadas = $estadisticas_mes_actual['total_pruebas'] > 0 
    ? round(($estadisticas_mes_actual['pruebas_aprobadas'] / $estadisticas_mes_actual['total_pruebas']) * 100, 1)
    : 0;

$porcentaje_reprobadas = $estadisticas_mes_actual['total_pruebas'] > 0 
    ? round(($estadisticas_mes_actual['pruebas_reprobadas'] / $estadisticas_mes_actual['total_pruebas']) * 100, 1)
    : 0;

// NOMBRE DEL MES EN ESPAÑOL
$meses_espanol = [
    'January' => 'Enero', 'February' => 'Febrero', 'March' => 'Marzo',
    'April' => 'Abril', 'May' => 'Mayo', 'June' => 'Junio',
    'July' => 'Julio', 'August' => 'Agosto', 'September' => 'Septiembre',
    'October' => 'Octubre', 'November' => 'Noviembre', 'December' => 'Diciembre'
];

$mes_nombre = $meses_espanol[date('F', strtotime($mes . '-01'))] . ' ' . date('Y', strtotime($mes . '-01'));
$mes_anterior_nombre = $meses_espanol[date('F', strtotime($mes_anterior . '-01'))] . ' ' . date('Y', strtotime($mes_anterior . '-01'));
?>

<div class="content-body">
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Análisis detallado y estadísticas mensuales del sistema de control de alcohol</p>
        </div>
        <div class="header-actions">
            <div class="month-navigator">
                <a href="?mes=<?php echo $mes_anterior; ?>" class="btn btn-outline">
                    <i class="fas fa-chevron-left"></i> Mes Anterior
                </a>
                <span class="current-month"><?php echo $mes_nombre; ?></span>
                <a href="?mes=<?php echo $mes_siguiente; ?>" class="btn btn-outline">
                    Mes Siguiente <i class="fas fa-chevron-right"></i>
                </a>
            </div>
            <button type="button" class="btn btn-primary" onclick="exportarEstadisticas()">
                <i class="fas fa-download"></i>Exportar Reporte
            </button>
        </div>
    </div>

    <!-- RESUMEN PRINCIPAL -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Resumen del Mes - <?php echo $mes_nombre; ?></h3>
            <div class="card-actions">
                <span class="badge <?php echo $variacion_total >= 0 ? 'success' : 'danger'; ?>">
                    <?php echo $variacion_total >= 0 ? '+' : ''; ?><?php echo $variacion_total; ?>% vs <?php echo $mes_anterior_nombre; ?>
                </span>
            </div>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-vial"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_mes_actual['total_pruebas'] ?? 0; ?></h3>
                        <p>Total Pruebas</p>
                        <div class="stat-variation <?php echo $variacion_total >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $variacion_total >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo $variacion_total; ?>%
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_mes_actual['pruebas_aprobadas'] ?? 0; ?></h3>
                        <p>Pruebas Aprobadas</p>
                        <div class="stat-variation <?php echo $variacion_aprobadas >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $variacion_aprobadas >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo $variacion_aprobadas; ?>%
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_mes_actual['pruebas_reprobadas'] ?? 0; ?></h3>
                        <p>Pruebas Reprobadas</p>
                        <div class="stat-variation <?php echo $variacion_reprobadas >= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $variacion_reprobadas >= 0 ? 'up' : 'down'; ?>"></i>
                            <?php echo $variacion_reprobadas; ?>%
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-redo"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_mes_actual['retests_realizados'] ?? 0; ?></h3>
                        <p>Re-tests Realizados</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_mes_actual['conductores_activos'] ?? 0; ?></h3>
                        <p>Conductores Activos</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_mes_actual['vehiculos_activos'] ?? 0; ?></h3>
                        <p>Vehículos Activos</p>
                    </div>
                </div>
            </div>

            <!-- BARRAS DE PROGRESO -->
            <div class="progress-stats" style="margin-top: 2rem;">
                <div class="progress-item">
                    <div class="progress-label">
                        <span>Tasa de Aprobación</span>
                        <span><?php echo $porcentaje_aprobadas; ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill success" style="width: <?php echo $porcentaje_aprobadas; ?>%"></div>
                    </div>
                </div>
                <div class="progress-item">
                    <div class="progress-label">
                        <span>Tasa de Reprobación</span>
                        <span><?php echo $porcentaje_reprobadas; ?>%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill danger" style="width: <?php echo $porcentaje_reprobadas; ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- DISTRIBUCIÓN DE NIVELES DE ALCOHOL -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Distribución de Niveles de Alcohol</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card mini">
                    <div class="stat-icon success">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo ($estadisticas_mes_actual['total_pruebas'] ?? 0) - ($estadisticas_mes_actual['nivel_advertencia'] ?? 0 + $estadisticas_mes_actual['nivel_alto'] ?? 0 + $estadisticas_mes_actual['nivel_critico'] ?? 0); ?></h3>
                        <p>Sin Alcohol (0.000 g/L)</p>
                    </div>
                </div>
                <div class="stat-card mini">
                    <div class="stat-icon warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_mes_actual['nivel_advertencia'] ?? 0; ?></h3>
                        <p>Nivel Advertencia (0.001-0.025 g/L)</p>
                    </div>
                </div>
                <div class="stat-card mini">
                    <div class="stat-icon danger">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_mes_actual['nivel_alto'] ?? 0; ?></h3>
                        <p>Nivel Alto (0.026-0.080 g/L)</p>
                    </div>
                </div>
                <div class="stat-card mini">
                    <div class="stat-icon dark">
                        <i class="fas fa-skull-crossbones"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_mes_actual['nivel_critico'] ?? 0; ?></h3>
                        <p>Nivel Crítico (>0.080 g/L)</p>
                    </div>
                </div>
            </div>

            <!-- RANGOS DE ALCOHOL -->
            <div class="stats-subgrid" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($estadisticas_mes_actual['promedio_alcohol'] ?? 0, 3); ?> g/L</h3>
                        <p>Promedio de Alcohol</p>
                        <div class="stat-variation <?php echo $variacion_promedio <= 0 ? 'positive' : 'negative'; ?>">
                            <i class="fas fa-arrow-<?php echo $variacion_promedio <= 0 ? 'down' : 'up'; ?>"></i>
                            <?php echo abs($variacion_promedio); ?>%
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($estadisticas_mes_actual['maximo_alcohol'] ?? 0, 3); ?> g/L</h3>
                        <p>Nivel Más Alto</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($estadisticas_mes_actual['minimo_alcohol'] ?? 0, 3); ?> g/L</h3>
                        <p>Nivel Más Bajo</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EVOLUCIÓN DIARIA -->
    <?php if (!empty($evolucion_diaria)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Evolución Diaria - <?php echo $mes_nombre; ?></h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Aprobadas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Promedio Alcohol</th>
                            <th>Tasa Reprobación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($evolucion_diaria as $dia): ?>
                        <tr>
                            <td>
                                <strong><?php echo date('d/m/Y', strtotime($dia['fecha'])); ?></strong>
                                <div class="text-muted"><?php echo date('l', strtotime($dia['fecha'])); ?></div>
                            </td>
                            <td>
                                <span class="badge primary"><?php echo $dia['total_pruebas']; ?></span>
                            </td>
                            <td>
                                <span class="badge success"><?php echo $dia['pruebas_aprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge danger"><?php echo $dia['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="nivel-alcohol <?php echo $dia['promedio_alcohol'] > 0.000 ? 'text-warning' : 'text-success'; ?>">
                                    <strong><?php echo number_format($dia['promedio_alcohol'] ?? 0, 3); ?> g/L</strong>
                                </span>
                            </td>
                            <td>
                                <?php 
                                $tasa_dia = $dia['total_pruebas'] > 0 
                                    ? round(($dia['pruebas_reprobadas'] / $dia['total_pruebas']) * 100, 1) 
                                    : 0;
                                ?>
                                <span class="badge <?php echo $tasa_dia > 10 ? 'danger' : ($tasa_dia > 5 ? 'warning' : 'success'); ?>">
                                    <?php echo $tasa_dia; ?>%
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

    <!-- DISTRIBUCIÓN SEMANAL -->
    <?php if (!empty($distribucion_semanal)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-week"></i> Distribución por Día de la Semana</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Día de la Semana</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Aprobadas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Tasa de Reprobación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($distribucion_semanal as $dia): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($dia['dia_semana']); ?></strong></td>
                            <td><?php echo $dia['total_pruebas']; ?></td>
                            <td>
                                <span class="badge success"><?php echo $dia['pruebas_aprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge danger"><?php echo $dia['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $dia['tasa_reprobacion'] > 15 ? 'danger' : ($dia['tasa_reprobacion'] > 8 ? 'warning' : 'success'); ?>">
                                    <?php echo $dia['tasa_reprobacion']; ?>%
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

    <!-- TOP CONDUCTORES -->
    <?php if (!empty($top_conductores)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-chart"></i> Top 5 Conductores - <?php echo $mes_nombre; ?></h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Conductor</th>
                            <th>DNI</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Aprobadas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Tasa Reprobación</th>
                            <th>Promedio Alcohol</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_conductores as $conductor): ?>
                        <tr>
                            <td>
                                <div class="conductor-info">
                                    <div class="conductor-nombre">
                                        <strong><?php echo htmlspecialchars($conductor['conductor_nombre']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="dni-badge"><?php echo htmlspecialchars($conductor['dni']); ?></span>
                            </td>
                            <td>
                                <span class="badge primary"><?php echo $conductor['total_pruebas']; ?></span>
                            </td>
                            <td>
                                <span class="badge success"><?php echo $conductor['pruebas_aprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge danger"><?php echo $conductor['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $conductor['tasa_reprobacion'] > 20 ? 'danger' : ($conductor['tasa_reprobacion'] > 10 ? 'warning' : 'success'); ?>">
                                    <?php echo $conductor['tasa_reprobacion']; ?>%
                                </span>
                            </td>
                            <td>
                                <span class="nivel-alcohol <?php echo $conductor['promedio_alcohol'] > 0.000 ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo number_format($conductor['promedio_alcohol'] ?? 0, 3); ?> g/L
                                </span>
                            </td>
                            <td class="action-buttons">
                                <a href="historial-conductor.php?conductor_id=<?php echo $conductor['id']; ?>" 
                                   class="btn-icon info" title="Ver Historial">
                                    <i class="fas fa-history"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TOP VEHÍCULOS -->
    <?php if (!empty($top_vehiculos)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-car-side"></i> Top 5 Vehículos - <?php echo $mes_nombre; ?></h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Vehículo</th>
                            <th>Placa</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Aprobadas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Conductores Diferentes</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_vehiculos as $vehiculo): ?>
                        <tr>
                            <td>
                                <div class="vehiculo-info">
                                    <div class="vehiculo-marca-modelo">
                                        <strong><?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="placa-badge"><?php echo htmlspecialchars($vehiculo['placa']); ?></span>
                            </td>
                            <td>
                                <span class="badge primary"><?php echo $vehiculo['total_pruebas']; ?></span>
                            </td>
                            <td>
                                <span class="badge success"><?php echo $vehiculo['pruebas_aprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge danger"><?php echo $vehiculo['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge info"><?php echo $vehiculo['conductores_diferentes']; ?></span>
                            </td>
                            <td class="action-buttons">
                                <a href="historial-vehiculo.php?vehiculo_id=<?php echo $vehiculo['id']; ?>" 
                                   class="btn-icon info" title="Ver Historial">
                                    <i class="fas fa-history"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- TENDENCIA MENSUAL -->
    <?php if (!empty($tendencia_meses)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-area"></i> Tendencia Mensual (Últimos 6 Meses)</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Mes</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Aprobadas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Tasa Reprobación</th>
                            <th>Promedio Alcohol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $mes_anterior_tendencia = null;
                        foreach ($tendencia_meses as $tendencia): 
                            $variacion_tendencia = '';
                            if ($mes_anterior_tendencia) {
                                $diferencia = $tendencia['tasa_reprobacion'] - $mes_anterior_tendencia;
                                if ($diferencia > 0) {
                                    $variacion_tendencia = '<span class="text-danger"><i class="fas fa-arrow-up"></i></span>';
                                } elseif ($diferencia < 0) {
                                    $variacion_tendencia = '<span class="text-success"><i class="fas fa-arrow-down"></i></span>';
                                }
                            }
                            $mes_anterior_tendencia = $tendencia['tasa_reprobacion'];
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($tendencia['mes_nombre']); ?></strong>
                                <?php echo $variacion_tendencia; ?>
                            </td>
                            <td><?php echo $tendencia['total_pruebas']; ?></td>
                            <td>
                                <span class="badge success"><?php echo $tendencia['pruebas_aprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge danger"><?php echo $tendencia['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $tendencia['tasa_reprobacion'] > 10 ? 'danger' : ($tendencia['tasa_reprobacion'] > 5 ? 'warning' : 'success'); ?>">
                                    <?php echo $tendencia['tasa_reprobacion']; ?>%
                                </span>
                            </td>
                            <td>
                                <span class="nivel-alcohol <?php echo $tendencia['promedio_alcohol'] > 0.000 ? 'text-warning' : 'text-success'; ?>">
                                    <?php echo number_format($tendencia['promedio_alcohol'] ?? 0, 3); ?> g/L
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

    <!-- SIN DATOS -->
    <?php if (empty($evolucion_diaria) && empty($top_conductores) && empty($top_vehiculos)): ?>
    <div class="empty-state">
        <div class="empty-icon">
            <i class="fas fa-chart-bar"></i>
        </div>
        <h3>No hay datos para <?php echo $mes_nombre; ?></h3>
        <p>No se encontraron pruebas registradas en este mes</p>
        <div class="empty-actions">
            <a href="?mes=<?php echo date('Y-m'); ?>" class="btn btn-primary">
                <i class="fas fa-calendar"></i>Ver Mes Actual
            </a>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* ESTILOS ESPECÍFICOS PARA ESTADÍSTICAS MENSUALES */
.month-navigator {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-right: 1rem;
}

.current-month {
    font-weight: 600;
    color: var(--dark);
    padding: 0.5rem 1rem;
    background: var(--light);
    border-radius: 6px;
    min-width: 150px;
    text-align: center;
}

.stat-variation {
    font-size: 0.75rem;
    font-weight: 600;
    margin-top: 0.25rem;
}

.stat-variation.positive {
    color: var(--success);
}

.stat-variation.negative {
    color: var(--danger);
}

.progress-stats {
    display: flex;
    flex-direction: column;
    gap: 1rem;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.progress-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    font-size: 0.9rem;
    font-weight: 600;
    color: var(--dark);
}

.progress-bar {
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    border-radius: 4px;
    transition: width 0.3s ease;
}

.progress-fill.success {
    background: linear-gradient(90deg, var(--success), #2ecc71);
}

.progress-fill.danger {
    background: linear-gradient(90deg, var(--danger), #e74c3c);
}

.progress-fill.warning {
    background: linear-gradient(90deg, var(--warning), #f39c12);
}

.stat-icon.dark {
    background: rgba(52, 58, 64, 0.15);
    color: #343a40;
}

/* Responsive */
@media (max-width: 768px) {
    .month-navigator {
        flex-direction: column;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }
    
    .current-month {
        order: -1;
    }
    
    .header-actions {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>

<script>
// FUNCIONES JS PARA ESTADÍSTICAS MENSUALES
document.addEventListener('DOMContentLoaded', function() {
    console.log('Módulo de estadísticas mensuales cargado - Mes: <?php echo $mes; ?>');
    
    // Inicializar tooltips si es necesario
    inicializarTooltips();
});

function exportarEstadisticas() {
    const mes = '<?php echo $mes; ?>';
    const url = `exportar-estadisticas.php?mes=${mes}&formato=excel`;
    
    console.log('Exportando estadísticas del mes:', mes);
    window.open(url, '_blank');
}

function inicializarTooltips() {
    // Inicializar tooltips de Bootstrap si están disponibles
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
}

// Función para cambiar mes rápidamente
function cambiarMes(direccion) {
    const mesActual = '<?php echo $mes; ?>';
    let nuevoMes;
    
    if (direccion === 'anterior') {
        nuevoMes = '<?php echo $mes_anterior; ?>';
    } else if (direccion === 'siguiente') {
        nuevoMes = '<?php echo $mes_siguiente; ?>';
    } else {
        nuevoMes = '<?php echo date('Y-m'); ?>';
    }
    
    window.location.href = `?mes=${nuevoMes}`;
}

// Navegación con teclado
document.addEventListener('keydown', function(event) {
    if (event.altKey) {
        switch(event.key) {
            case 'ArrowLeft':
                event.preventDefault();
                cambiarMes('anterior');
                break;
            case 'ArrowRight':
                event.preventDefault();
                cambiarMes('siguiente');
                break;
            case 'Home':
                event.preventDefault();
                cambiarMes('actual');
                break;
        }
    }
});

// Función para mostrar detalles de un día específico
function verDetalleDia(fecha) {
    console.log('Ver detalle del día:', fecha);
    // Podría abrir un modal con el detalle del día
    alert(`Detalles del día ${fecha} - Esta función estará disponible próximamente.`);
}

// Función para generar gráficos (para futura implementación)
function generarGraficos() {
    console.log('Generando gráficos para estadísticas mensuales...');
    // Aquí se puede integrar Chart.js para gráficos más avanzados
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>