<?php
// reportes-gerenciales.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Reportes Gerenciales';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'reportes-gerenciales.php' => 'Reportes Gerenciales'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// PARÁMETROS DE FILTRADO
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01'); // Primer día del mes
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-t'); // Último día del mes
$tipo_reporte = $_GET['tipo_reporte'] ?? 'general';
$conductor_id = $_GET['conductor_id'] ?? '';
$vehiculo_id = $_GET['vehiculo_id'] ?? '';
$alcoholimetro_id = $_GET['alcoholimetro_id'] ?? '';

// VALIDAR FECHAS
if (!strtotime($fecha_inicio)) $fecha_inicio = date('Y-m-01');
if (!strtotime($fecha_fin)) $fecha_fin = date('Y-m-t');

// OBTENER DATOS PARA FILTROS
$conductores = $db->fetchAll("
    SELECT id, CONCAT(nombre, ' ', apellido) as nombre_completo, dni 
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor' AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

$vehiculos = $db->fetchAll("
    SELECT id, placa, marca, modelo 
    FROM vehiculos 
    WHERE cliente_id = ? AND estado = 'activo'
    ORDER BY marca, modelo
", [$cliente_id]);

$alcoholimetros = $db->fetchAll("
    SELECT id, nombre_activo, numero_serie 
    FROM alcoholimetros 
    WHERE cliente_id = ? AND estado = 'activo'
    ORDER BY nombre_activo
", [$cliente_id]);

// ESTADÍSTICAS GENERALES DEL PERÍODO
$estadisticas_generales = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        SUM(CASE WHEN es_retest = 1 THEN 1 ELSE 0 END) as retests_realizados,
        AVG(nivel_alcohol) as promedio_alcohol,
        MAX(nivel_alcohol) as maximo_alcohol,
        COUNT(DISTINCT conductor_id) as conductores_activos,
        COUNT(DISTINCT vehiculo_id) as vehiculos_activos,
        COUNT(DISTINCT alcoholimetro_id) as alcoholimetros_utilizados
    FROM pruebas 
    WHERE cliente_id = ? 
    AND DATE(fecha_prueba) BETWEEN ? AND ?
", [$cliente_id, $fecha_inicio, $fecha_fin]);

// REPORTE POR CONDUCTORES (TOP 10 CON MÁS PRUEBAS REPROBADAS)
$top_conductores_reprobados = $db->fetchAll("
    SELECT 
        u.id,
        CONCAT(u.nombre, ' ', u.apellido) as conductor_nombre,
        u.dni,
        COUNT(p.id) as total_pruebas,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(p.id)) * 100, 1) as tasa_reprobacion
    FROM usuarios u
    INNER JOIN pruebas p ON u.id = p.conductor_id
    WHERE p.cliente_id = ? 
    AND DATE(p.fecha_prueba) BETWEEN ? AND ?
    GROUP BY u.id, u.nombre, u.apellido, u.dni
    HAVING pruebas_reprobadas > 0
    ORDER BY pruebas_reprobadas DESC
    LIMIT 10
", [$cliente_id, $fecha_inicio, $fecha_fin]);

// REPORTE POR VEHÍCULOS (MÁS UTILIZADOS)
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
    AND DATE(p.fecha_prueba) BETWEEN ? AND ?
    GROUP BY v.id, v.placa, v.marca, v.modelo
    ORDER BY total_pruebas DESC
    LIMIT 10
", [$cliente_id, $fecha_inicio, $fecha_fin]);

// REPORTE POR ALCOHOLÍMETROS
$alcoholimetros_uso = $db->fetchAll("
    SELECT 
        a.id,
        a.nombre_activo,
        a.numero_serie,
        COUNT(p.id) as total_pruebas,
        AVG(p.nivel_alcohol) as promedio_alcohol,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        MAX(p.fecha_prueba) as ultimo_uso
    FROM alcoholimetros a
    LEFT JOIN pruebas p ON a.id = p.alcoholimetro_id
    WHERE p.cliente_id = ? 
    AND DATE(p.fecha_prueba) BETWEEN ? AND ?
    GROUP BY a.id, a.nombre_activo, a.numero_serie
    ORDER BY total_pruebas DESC
", [$cliente_id, $fecha_inicio, $fecha_fin]);

// TENDENCIA MENSUAL (ÚLTIMOS 6 MESES)
$tendencia_mensual = $db->fetchAll("
    SELECT 
        DATE_FORMAT(fecha_prueba, '%Y-%m') as mes,
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as tasa_reprobacion
    FROM pruebas 
    WHERE cliente_id = ? 
    AND fecha_prueba >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(fecha_prueba, '%Y-%m')
    ORDER BY mes DESC
    LIMIT 6
", [$cliente_id]);

// DISTRIBUCIÓN POR DÍA DE LA SEMANA
$distribucion_dias = $db->fetchAll("
    SELECT 
        DAYNAME(fecha_prueba) as dia_semana,
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas
    FROM pruebas 
    WHERE cliente_id = ? 
    AND DATE(fecha_prueba) BETWEEN ? AND ?
    GROUP BY DAYNAME(fecha_prueba), DAYOFWEEK(fecha_prueba)
    ORDER BY DAYOFWEEK(fecha_prueba)
", [$cliente_id, $fecha_inicio, $fecha_fin]);

// HORARIOS CON MÁS INCIDENCIAS
$horarios_incidencias = $db->fetchAll("
    SELECT 
        HOUR(fecha_prueba) as hora,
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as tasa_reprobacion
    FROM pruebas 
    WHERE cliente_id = ? 
    AND DATE(fecha_prueba) BETWEEN ? AND ?
    GROUP BY HOUR(fecha_prueba)
    ORDER BY pruebas_reprobadas DESC
    LIMIT 8
", [$cliente_id, $fecha_inicio, $fecha_fin]);
?>

<div class="content-body">
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Reportes avanzados y análisis gerencial del sistema de control de alcohol</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="exportarReporteCompleto()">
                <i class="fas fa-file-export"></i>Exportar Reporte
            </button>
            <button type="button" class="btn btn-outline" onclick="imprimirReporte()">
                <i class="fas fa-print"></i>Imprimir
            </button>
        </div>
    </div>

    <!-- FILTROS AVANZADOS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filtros del Reporte</h3>
        </div>
        <div class="card-body">
            <form id="formFiltros" method="GET" class="filter-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" 
                               value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" 
                               value="<?php echo htmlspecialchars($fecha_fin); ?>">
                    </div>
                    <div class="form-group">
                        <label for="tipo_reporte" class="form-label">Tipo de Reporte</label>
                        <select id="tipo_reporte" name="tipo_reporte" class="form-control">
                            <option value="general" <?php echo $tipo_reporte === 'general' ? 'selected' : ''; ?>>General</option>
                            <option value="conductores" <?php echo $tipo_reporte === 'conductores' ? 'selected' : ''; ?>>Por Conductores</option>
                            <option value="vehiculos" <?php echo $tipo_reporte === 'vehiculos' ? 'selected' : ''; ?>>Por Vehículos</option>
                            <option value="alcoholimetros" <?php echo $tipo_reporte === 'alcoholimetros' ? 'selected' : ''; ?>>Por Alcoholímetros</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="conductor_id" class="form-label">Conductor</label>
                        <select id="conductor_id" name="conductor_id" class="form-control">
                            <option value="">Todos los conductores</option>
                            <?php foreach ($conductores as $conductor): ?>
                            <option value="<?php echo $conductor['id']; ?>" 
                                    <?php echo $conductor_id == $conductor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($conductor['nombre_completo'] . ' - ' . $conductor['dni']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="vehiculo_id" class="form-label">Vehículo</label>
                        <select id="vehiculo_id" name="vehiculo_id" class="form-control">
                            <option value="">Todos los vehículos</option>
                            <?php foreach ($vehiculos as $vehiculo): ?>
                            <option value="<?php echo $vehiculo['id']; ?>" 
                                    <?php echo $vehiculo_id == $vehiculo['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo'] . ' - ' . $vehiculo['placa']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="alcoholimetro_id" class="form-label">Alcoholímetro</label>
                        <select id="alcoholimetro_id" name="alcoholimetro_id" class="form-control">
                            <option value="">Todos los alcoholímetros</option>
                            <?php foreach ($alcoholimetros as $alcoholimetro): ?>
                            <option value="<?php echo $alcoholimetro['id']; ?>" 
                                    <?php echo $alcoholimetro_id == $alcoholimetro['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($alcoholimetro['nombre_activo'] . ' - ' . $alcoholimetro['numero_serie']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Generar Reporte
                    </button>
                    <button type="button" class="btn btn-outline" onclick="limpiarFiltros()">
                        <i class="fas fa-eraser"></i> Limpiar Filtros
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- RESUMEN EJECUTIVO -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Resumen Ejecutivo</h3>
            <div class="card-actions">
                <span class="badge primary">Período: <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></span>
            </div>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-vial"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_generales['total_pruebas'] ?? 0; ?></h3>
                        <p>Total Pruebas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_generales['pruebas_aprobadas'] ?? 0; ?></h3>
                        <p>Pruebas Aprobadas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_generales['pruebas_reprobadas'] ?? 0; ?></h3>
                        <p>Pruebas Reprobadas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-info">
                        <h3>
                            <?php 
                            $tasa_aprobacion = $estadisticas_generales['total_pruebas'] > 0 
                                ? round(($estadisticas_generales['pruebas_aprobadas'] / $estadisticas_generales['total_pruebas']) * 100, 1) 
                                : 0;
                            echo $tasa_aprobacion;
                            ?>%
                        </h3>
                        <p>Tasa de Aprobación</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_generales['conductores_activos'] ?? 0; ?></h3>
                        <p>Conductores Activos</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-car"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_generales['vehiculos_activos'] ?? 0; ?></h3>
                        <p>Vehículos Activos</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TOP CONDUCTORES CON MÁS PRUEBAS REPROBADAS -->
    <?php if (!empty($top_conductores_reprobados)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Conductores con Más Incidencias</h3>
            <div class="card-actions">
                <span class="badge danger">Top 10</span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Conductor</th>
                            <th>DNI</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Tasa de Reprobación</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_conductores_reprobados as $conductor): ?>
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
                                <span class="badge danger"><?php echo $conductor['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $conductor['tasa_reprobacion'] > 20 ? 'danger' : 'warning'; ?>">
                                    <?php echo $conductor['tasa_reprobacion']; ?>%
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

    <!-- TOP VEHÍCULOS MÁS UTILIZADOS -->
    <?php if (!empty($top_vehiculos)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-car-side"></i> Vehículos Más Utilizados</h3>
            <div class="card-actions">
                <span class="badge primary">Top 10</span>
            </div>
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

    <!-- USO DE ALCOHOLÍMETROS -->
    <?php if (!empty($alcoholimetros_uso)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tachometer-alt"></i> Uso de Alcoholímetros</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Alcoholímetro</th>
                            <th>Número de Serie</th>
                            <th>Total Pruebas</th>
                            <th>Promedio Alcohol</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Último Uso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alcoholimetros_uso as $alcoholimetro): ?>
                        <tr>
                            <td>
                                <div class="alcoholimetro-info">
                                    <div class="alcoholimetro-nombre">
                                        <strong><?php echo htmlspecialchars($alcoholimetro['nombre_activo']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge secondary"><?php echo htmlspecialchars($alcoholimetro['numero_serie']); ?></span>
                            </td>
                            <td>
                                <span class="badge primary"><?php echo $alcoholimetro['total_pruebas']; ?></span>
                            </td>
                            <td>
                                <span class="nivel-alcohol <?php echo $alcoholimetro['promedio_alcohol'] > 0.000 ? 'text-warning' : 'text-success'; ?>">
                                    <strong><?php echo number_format($alcoholimetro['promedio_alcohol'] ?? 0, 3); ?> g/L</strong>
                                </span>
                            </td>
                            <td>
                                <span class="badge danger"><?php echo $alcoholimetro['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="fecha-registro">
                                    <?php echo $alcoholimetro['ultimo_uso'] ? date('d/m/Y H:i', strtotime($alcoholimetro['ultimo_uso'])) : 'Nunca'; ?>
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

    <!-- TENDENCIA MENSUAL -->
    <?php if (!empty($tendencia_mensual)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Tendencia Mensual (Últimos 6 Meses)</h3>
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
                            <th>Tasa de Reprobación</th>
                            <th>Evolución</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $mes_anterior = null;
                        foreach ($tendencia_mensual as $mes): 
                            $nombre_mes = date('F Y', strtotime($mes['mes'] . '-01'));
                            $tasa_reprobacion = $mes['tasa_reprobacion'];
                            
                            // Calcular evolución
                            $evolucion = '';
                            if ($mes_anterior) {
                                $diferencia = $tasa_reprobacion - $mes_anterior;
                                if ($diferencia > 0) {
                                    $evolucion = '<span class="text-danger"><i class="fas fa-arrow-up"></i> +' . abs($diferencia) . '%</span>';
                                } elseif ($diferencia < 0) {
                                    $evolucion = '<span class="text-success"><i class="fas fa-arrow-down"></i> -' . abs($diferencia) . '%</span>';
                                } else {
                                    $evolucion = '<span class="text-muted"><i class="fas fa-minus"></i> 0%</span>';
                                }
                            }
                            $mes_anterior = $tasa_reprobacion;
                        ?>
                        <tr>
                            <td><strong><?php echo $nombre_mes; ?></strong></td>
                            <td><?php echo $mes['total_pruebas']; ?></td>
                            <td>
                                <span class="badge success"><?php echo $mes['pruebas_aprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge danger"><?php echo $mes['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $tasa_reprobacion > 10 ? 'danger' : ($tasa_reprobacion > 5 ? 'warning' : 'success'); ?>">
                                    <?php echo $tasa_reprobacion; ?>%
                                </span>
                            </td>
                            <td>
                                <?php echo $evolucion ?: '<span class="text-muted">N/A</span>'; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- DISTRIBUCIÓN POR DÍA DE LA SEMANA -->
    <?php if (!empty($distribucion_dias)): ?>
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
                            <th>Pruebas Reprobadas</th>
                            <th>Tasa de Reprobación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($distribucion_dias as $dia): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($dia['dia_semana']); ?></strong></td>
                            <td><?php echo $dia['total_pruebas']; ?></td>
                            <td>
                                <span class="badge danger"><?php echo $dia['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <?php 
                                $tasa_dia = $dia['total_pruebas'] > 0 
                                    ? round(($dia['pruebas_reprobadas'] / $dia['total_pruebas']) * 100, 1) 
                                    : 0;
                                ?>
                                <span class="badge <?php echo $tasa_dia > 15 ? 'danger' : ($tasa_dia > 8 ? 'warning' : 'success'); ?>">
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

    <!-- HORARIOS CON MÁS INCIDENCIAS -->
    <?php if (!empty($horarios_incidencias)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Horarios con Más Incidencias</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Hora del Día</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Tasa de Reprobación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($horarios_incidencias as $hora): ?>
                        <tr>
                            <td><strong><?php echo $hora['hora'] . ':00 - ' . ($hora['hora'] + 1) . ':00'; ?></strong></td>
                            <td><?php echo $hora['total_pruebas']; ?></td>
                            <td>
                                <span class="badge danger"><?php echo $hora['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $hora['tasa_reprobacion'] > 20 ? 'danger' : ($hora['tasa_reprobacion'] > 10 ? 'warning' : 'success'); ?>">
                                    <?php echo $hora['tasa_reprobacion']; ?>%
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
    <?php if (empty($top_conductores_reprobados) && empty($top_vehiculos) && empty($alcoholimetros_uso)): ?>
    <div class="empty-state">
        <div class="empty-icon">
            <i class="fas fa-chart-bar"></i>
        </div>
        <h3>No hay datos para mostrar</h3>
        <p>No se encontraron pruebas en el período seleccionado</p>
        <div class="empty-actions">
            <button type="button" class="btn btn-primary" onclick="limpiarFiltros()">
                <i class="fas fa-eraser"></i>Ver todos los datos
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
/* ESTILOS ESPECÍFICOS PARA REPORTES */
.filter-form .form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.filter-form .form-group {
    margin-bottom: 0;
}

.filter-form .form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    display: block;
}

.filter-form .form-control {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
    background: white;
    width: 100%;
    box-sizing: border-box;
    transition: border-color 0.3s ease;
}

.filter-form .form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(132, 6, 31, 0.1);
}

.filter-form select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 12px;
    padding-right: 2.5rem;
}

.filter-form .form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-start;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid var(--border);
}

/* Responsive para filtros */
@media (max-width: 768px) {
    .filter-form .form-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-form .form-actions {
        flex-direction: column;
    }
    
    .filter-form .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Mejoras visuales para tablas de reportes */
.data-table th {
    background: var(--light);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.8rem;
}

.nivel-alcohol.text-warning {
    color: var(--warning) !important;
    font-weight: 600;
}
</style>

<script>
// FUNCIONES JS PARA REPORTES GERENCIALES
document.addEventListener('DOMContentLoaded', function() {
    console.log('Módulo de reportes gerenciales cargado');
    
    // Actualizar automáticamente al cambiar tipo de reporte
    document.getElementById('tipo_reporte').addEventListener('change', function() {
        document.getElementById('formFiltros').submit();
    });
});

function exportarReporteCompleto() {
    const params = new URLSearchParams(window.location.search);
    const url = `exportar-reporte.php?${params.toString()}&formato=excel`;
    
    console.log('Exportando reporte completo');
    window.open(url, '_blank');
}

function imprimirReporte() {
    console.log('Imprimiendo reporte');
    window.print();
}

function limpiarFiltros() {
    // Redirigir sin parámetros
    window.location.href = 'reportes-gerenciales.php';
}

// Función para generar gráficos (para futura implementación)
function generarGraficos() {
    console.log('Generando gráficos...');
    // Aquí se puede integrar Chart.js o otra librería de gráficos
}

// Función para descargar reporte específico
function descargarReporte(tipo) {
    const params = new URLSearchParams(window.location.search);
    const url = `exportar-reporte.php?${params.toString()}&tipo=${tipo}&formato=pdf`;
    
    console.log(`Descargando reporte ${tipo}`);
    window.open(url, '_blank');
}

// Validación de fechas
document.getElementById('formFiltros').addEventListener('submit', function(e) {
    const fechaInicio = document.getElementById('fecha_inicio').value;
    const fechaFin = document.getElementById('fecha_fin').value;
    
    if (fechaInicio && fechaFin) {
        const inicio = new Date(fechaInicio);
        const fin = new Date(fechaFin);
        
        if (inicio > fin) {
            e.preventDefault();
            alert('La fecha de inicio no puede ser mayor a la fecha de fin');
            return false;
        }
    }
    
    return true;
});

// Cargar más datos (para paginación infinita)
function cargarMasDatos(seccion) {
    console.log(`Cargando más datos para: ${seccion}`);
    // Implementar carga paginada si es necesario
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>