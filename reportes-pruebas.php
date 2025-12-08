<?php
// reportes-pruebas.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Reportes de Pruebas de Alcohol';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'reportes-pruebas.php' => 'Reportes de Pruebas de Alcohol'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// OBTENER DATOS PARA FILTROS
$alcoholimetros = $db->fetchAll("
    SELECT id, nombre, numero_serie 
    FROM alcoholimetros 
    WHERE cliente_id = ? AND estado = 1
    ORDER BY nombre
", [$cliente_id]);

$conductores = $db->fetchAll("
    SELECT id, CONCAT(nombre, ' ', apellido) as nombre_completo, dni
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor' AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

// PROCESAR FILTROS
$filtros = [
    'fecha_inicio' => $_POST['fecha_inicio'] ?? date('Y-m-01'),
    'fecha_fin' => $_POST['fecha_fin'] ?? date('Y-m-d'),
    'conductor_id' => $_POST['conductor_id'] ?? '',
    'alcoholimetro_id' => $_POST['alcoholimetro_id'] ?? '',
    'resultado' => $_POST['resultado'] ?? '',
    'tipo_prueba' => $_POST['tipo_prueba'] ?? ''
];

// CONSTRUIR CONSULTA BASE
$where_conditions = ["p.cliente_id = ?"];
$params = [$cliente_id];

if (!empty($filtros['fecha_inicio'])) {
    $where_conditions[] = "p.fecha_prueba >= ?";
    $params[] = $filtros['fecha_inicio'] . ' 00:00:00';
}

if (!empty($filtros['fecha_fin'])) {
    $where_conditions[] = "p.fecha_prueba <= ?";
    $params[] = $filtros['fecha_fin'] . ' 23:59:59';
}

if (!empty($filtros['conductor_id'])) {
    $where_conditions[] = "p.conductor_id = ?";
    $params[] = $filtros['conductor_id'];
}

if (!empty($filtros['alcoholimetro_id'])) {
    $where_conditions[] = "p.alcoholimetro_id = ?";
    $params[] = $filtros['alcoholimetro_id'];
}

if (!empty($filtros['resultado'])) {
    $where_conditions[] = "p.resultado = ?";
    $params[] = $filtros['resultado'];
}

if (!empty($filtros['tipo_prueba'])) {
    $where_conditions[] = "p.tipo_prueba = ?";
    $params[] = $filtros['tipo_prueba'];
}

$where_sql = implode(" AND ", $where_conditions);

// OBTENER ESTADÍSTICAS GENERALES
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        SUM(CASE WHEN resultado IS NULL OR resultado = '' THEN 1 ELSE 0 END) as no_realizadas,
        AVG(CASE WHEN nivel_alcohol > 0 THEN nivel_alcohol ELSE NULL END) as promedio_alcohol,
        COUNT(DISTINCT conductor_id) as conductores_evaluados
    FROM pruebas p
    WHERE $where_sql
", $params);

// OBTENER PRUEBAS POR HORA DEL DÍA
$pruebas_por_hora = $db->fetchAll("
    SELECT 
        HOUR(fecha_prueba) as hora,
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        AVG(CASE WHEN nivel_alcohol > 0 THEN nivel_alcohol ELSE 0 END) as promedio_alcohol
    FROM pruebas p
    WHERE $where_sql
    GROUP BY HOUR(fecha_prueba)
    ORDER BY hora
", $params);

// OBTENER PRUEBAS POR DÍA DE LA SEMANA
$pruebas_por_dia = $db->fetchAll("
    SELECT 
        DAYNAME(fecha_prueba) as dia_semana,
        DAYOFWEEK(fecha_prueba) as dia_numero,
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 1) as tasa_reprobacion
    FROM pruebas p
    WHERE $where_sql
    GROUP BY DAYNAME(fecha_prueba), DAYOFWEEK(fecha_prueba)
    ORDER BY DAYOFWEEK(fecha_prueba)
", $params);

// OBTENER EFICIENCIA DE ALCOHOLÍMETROS
$eficiencia_alcoholimetros = $db->fetchAll("
    SELECT 
        a.nombre as alcoholimetro,
        a.numero_serie,
        COUNT(p.id) as total_pruebas,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(p.id)) * 100, 1) as tasa_reprobacion,
        AVG(CASE WHEN p.nivel_alcohol > 0 THEN p.nivel_alcohol ELSE 0 END) as promedio_alcohol,
        MAX(p.fecha_prueba) as ultimo_uso
    FROM alcoholimetros a
    LEFT JOIN pruebas p ON a.id = p.alcoholimetro_id
    WHERE $where_sql AND a.id IS NOT NULL
    GROUP BY a.id, a.nombre, a.numero_serie
    ORDER BY total_pruebas DESC
", $params);

// OBTENER CONDUCTORES CON MÁS INCIDENCIAS
$conductores_incidencias = $db->fetchAll("
    SELECT 
        u.id,
        CONCAT(u.nombre, ' ', u.apellido) as conductor_nombre,
        u.dni,
        COUNT(p.id) as total_pruebas,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(p.id)) * 100, 1) as tasa_reprobacion,
        MAX(p.nivel_alcohol) as maximo_alcohol
    FROM usuarios u
    INNER JOIN pruebas p ON u.id = p.conductor_id
    WHERE $where_sql
    GROUP BY u.id, u.nombre, u.apellido, u.dni
    HAVING pruebas_reprobadas > 0
    ORDER BY pruebas_reprobadas DESC, tasa_reprobacion DESC
    LIMIT 10
", $params);

// OBTENER DETALLE DE PRUEBAS
$detalle_pruebas = $db->fetchAll("
    SELECT 
        p.*,
        CONCAT(u.nombre, ' ', u.apellido) as conductor_nombre,
        u.dni as conductor_dni,
        a.nombre as alcoholimetro_nombre,
        a.numero_serie
    FROM pruebas p
    LEFT JOIN usuarios u ON p.conductor_id = u.id
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    WHERE $where_sql
    ORDER BY p.fecha_prueba DESC
    LIMIT 100
", $params);
?>

<div class="content-body">
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Reportes detallados y estadísticas de las pruebas de alcohol realizadas</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="exportarReporte('excel')">
                <i class="fas fa-file-excel"></i> Exportar a Excel
            </button>
            <button type="button" class="btn btn-primary" onclick="exportarReporte('pdf')">
                <i class="fas fa-file-pdf"></i> Exportar a PDF
            </button>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Volver al Dashboard
            </a>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
        </div>
        <div class="card-body">
            <form method="POST" class="filters-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" 
                               value="<?php echo htmlspecialchars($filtros['fecha_inicio']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" 
                               value="<?php echo htmlspecialchars($filtros['fecha_fin']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="conductor_id" class="form-label">Conductor</label>
                        <select id="conductor_id" name="conductor_id" class="form-control">
                            <option value="">Todos los conductores</option>
                            <?php foreach ($conductores as $conductor): ?>
                            <option value="<?php echo $conductor['id']; ?>" 
                                <?php echo $filtros['conductor_id'] == $conductor['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($conductor['nombre_completo'] . ' - ' . $conductor['dni']); ?>
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
                                <?php echo $filtros['alcoholimetro_id'] == $alcoholimetro['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($alcoholimetro['nombre'] . ' - ' . $alcoholimetro['numero_serie']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="resultado" class="form-label">Resultado</label>
                        <select id="resultado" name="resultado" class="form-control">
                            <option value="">Todos los resultados</option>
                            <option value="aprobado" <?php echo $filtros['resultado'] == 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                            <option value="reprobado" <?php echo $filtros['resultado'] == 'reprobado' ? 'selected' : ''; ?>>Reprobado</option>
                            <option value="no_realizado" <?php echo $filtros['resultado'] == 'no_realizado' ? 'selected' : ''; ?>>No Realizado</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="tipo_prueba" class="form-label">Tipo de Prueba</label>
                        <select id="tipo_prueba" name="tipo_prueba" class="form-control">
                            <option value="">Todos los tipos</option>
                            <option value="aleatoria" <?php echo $filtros['tipo_prueba'] == 'aleatoria' ? 'selected' : ''; ?>>Aleatoria</option>
                            <option value="programada" <?php echo $filtros['tipo_prueba'] == 'programada' ? 'selected' : ''; ?>>Programada</option>
                            <option value="retest" <?php echo $filtros['tipo_prueba'] == 'retest' ? 'selected' : ''; ?>>Re-test</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Aplicar Filtros
                    </button>
                    <button type="button" class="btn btn-outline" onclick="limpiarFiltros()">
                        <i class="fas fa-broom"></i> Limpiar Filtros
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ESTADÍSTICAS GENERALES -->
    <div class="stats-section">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Resumen Estadístico</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-vial"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_pruebas'] ?? 0; ?></h3>
                            <p>Total Pruebas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['pruebas_aprobadas'] ?? 0; ?></h3>
                            <p>Pruebas Aprobadas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['pruebas_reprobadas'] ?? 0; ?></h3>
                            <p>Pruebas Reprobadas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['no_realizadas'] ?? 0; ?></h3>
                            <p>No Realizadas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['promedio_alcohol'] ?? 0, 3); ?> g/L</h3>
                            <p>Promedio Alcohol</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon secondary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['conductores_evaluados'] ?? 0; ?></h3>
                            <p>Conductores Evaluados</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- PRUEBAS POR HORA DEL DÍA -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-clock"></i> Pruebas por Hora del Día</h3>
            <div class="card-actions">
                <span class="badge primary">Últimos 30 días</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($pruebas_por_hora)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Promedio Alcohol</th>
                            <th>Tasa Reprobación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pruebas_por_hora as $hora): ?>
                        <tr>
                            <td><?php echo sprintf('%02d:00', $hora['hora']); ?></td>
                            <td><?php echo $hora['total_pruebas']; ?></td>
                            <td>
                                <span class="badge danger"><?php echo $hora['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="nivel-alcohol-mini <?php echo $hora['promedio_alcohol'] > 0.000 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo number_format($hora['promedio_alcohol'], 3); ?> g/L
                                </span>
                            </td>
                            <td>
                                <?php 
                                $tasa_reprobacion = $hora['total_pruebas'] > 0 ? 
                                    round(($hora['pruebas_reprobadas'] / $hora['total_pruebas']) * 100, 1) : 0;
                                $color_clase = $tasa_reprobacion > 20 ? 'danger' : ($tasa_reprobacion > 10 ? 'warning' : 'success');
                                ?>
                                <span class="badge <?php echo $color_clase; ?>">
                                    <?php echo $tasa_reprobacion; ?>%
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
                    <i class="fas fa-chart-line"></i>
                </div>
                <h3>No hay datos disponibles</h3>
                <p>No se encontraron pruebas que coincidan con los filtros seleccionados</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- PRUEBAS POR DÍA DE LA SEMANA -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-alt"></i> Pruebas por Día de la Semana</h3>
            <div class="card-actions">
                <span class="badge primary">Últimos 30 días</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($pruebas_por_dia)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Día</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Tasa Reprobación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pruebas_por_dia as $dia): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($dia['dia_semana']); ?></td>
                            <td><?php echo $dia['total_pruebas']; ?></td>
                            <td>
                                <span class="badge danger"><?php echo $dia['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $dia['tasa_reprobacion'] > 20 ? 'danger' : ($dia['tasa_reprobacion'] > 10 ? 'warning' : 'success'); ?>">
                                    <?php echo $dia['tasa_reprobacion']; ?>%
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
                    <i class="fas fa-calendar"></i>
                </div>
                <h3>No hay datos disponibles</h3>
                <p>No se encontraron pruebas que coincidan con los filtros seleccionados</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- CONDUCTORES CON MÁS INCIDENCIAS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Conductores con Más Incidencias</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($conductores_incidencias)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Conductor</th>
                            <th>DNI</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Tasa Reprobación</th>
                            <th>Máximo Alcohol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conductores_incidencias as $conductor): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($conductor['conductor_nombre']); ?></td>
                            <td><?php echo htmlspecialchars($conductor['dni']); ?></td>
                            <td><?php echo $conductor['total_pruebas']; ?></td>
                            <td>
                                <span class="badge danger"><?php echo $conductor['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $conductor['tasa_reprobacion'] > 30 ? 'danger' : 'warning'; ?>">
                                    <?php echo $conductor['tasa_reprobacion']; ?>%
                                </span>
                            </td>
                            <td>
                                <span class="nivel-alcohol-mini text-danger">
                                    <?php echo number_format($conductor['maximo_alcohol'], 3); ?> g/L
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
                    <i class="fas fa-user-check"></i>
                </div>
                <h3>No hay conductores con incidencias</h3>
                <p>No se encontraron conductores con pruebas reprobadas en el período seleccionado</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- EFICIENCIA DE ALCOHOLÍMETROS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tachometer-alt"></i> Eficiencia de Alcoholímetros</h3>
            <div class="card-actions">
                <span class="badge primary">Últimos 30 días</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($eficiencia_alcoholimetros)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Alcoholímetro</th>
                            <th>Nº Serie</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Reprobadas</th>
                            <th>Tasa Reprobación</th>
                            <th>Promedio Alcohol</th>
                            <th>Último Uso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eficiencia_alcoholimetros as $alcoholimetro): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($alcoholimetro['alcoholimetro']); ?></td>
                            <td><?php echo htmlspecialchars($alcoholimetro['numero_serie']); ?></td>
                            <td><?php echo $alcoholimetro['total_pruebas']; ?></td>
                            <td>
                                <span class="badge danger"><?php echo $alcoholimetro['pruebas_reprobadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $alcoholimetro['tasa_reprobacion'] > 20 ? 'danger' : ($alcoholimetro['tasa_reprobacion'] > 10 ? 'warning' : 'success'); ?>">
                                    <?php echo $alcoholimetro['tasa_reprobacion']; ?>%
                                </span>
                            </td>
                            <td>
                                <span class="nivel-alcohol-mini <?php echo $alcoholimetro['promedio_alcohol'] > 0.000 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo number_format($alcoholimetro['promedio_alcohol'], 3); ?> g/L
                                </span>
                            </td>
                            <td>
                                <?php if ($alcoholimetro['ultimo_uso']): ?>
                                <?php echo date('d/m/Y H:i', strtotime($alcoholimetro['ultimo_uso'])); ?>
                                <?php else: ?>
                                <span class="text-muted">Nunca</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <h3>No hay datos de alcoholímetros</h3>
                <p>No se encontraron pruebas realizadas con los alcoholímetros en el período seleccionado</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DETALLE DE PRUEBAS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Detalle de Pruebas</h3>
            <div class="card-actions">
                <span class="badge primary"><?php echo count($detalle_pruebas); ?> registros</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($detalle_pruebas)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Conductor</th>
                            <th>DNI</th>
                            <th>Alcoholímetro</th>
                            <th>Nivel Alcohol</th>
                            <th>Resultado</th>
                            <th>Tipo Prueba</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle_pruebas as $prueba): ?>
                        <tr>
                            <td>
                                <span class="ultimo-login">
                                    <?php echo date('d/m/Y H:i', strtotime($prueba['fecha_prueba'])); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($prueba['conductor_nombre'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($prueba['conductor_dni'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($prueba['alcoholimetro_nombre'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="nivel-alcohol-mini <?php echo $prueba['nivel_alcohol'] > 0.000 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo number_format($prueba['nivel_alcohol'], 3); ?> g/L
                                </span>
                            </td>
                            <td>
                                <?php 
                                $resultado_clase = '';
                                if ($prueba['resultado'] == 'aprobado') {
                                    $resultado_clase = 'success';
                                } elseif ($prueba['resultado'] == 'reprobado') {
                                    $resultado_clase = 'danger';
                                } else {
                                    $resultado_clase = 'warning';
                                }
                                ?>
                                <span class="badge <?php echo $resultado_clase; ?>">
                                    <?php echo ucfirst($prueba['resultado'] ?? 'No Realizado'); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge secondary">
                                    <?php echo ucfirst($prueba['tipo_prueba'] ?? 'N/A'); ?>
                                </span>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo htmlspecialchars($prueba['observaciones'] ?? ''); ?>
                                </small>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>No hay pruebas registradas</h3>
                <p>No se encontraron pruebas que coincidan con los filtros seleccionados</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* ===== ESTILOS PROFESIONALES PARA FORMULARIOS ===== */
.form-group {
    margin-bottom: 1.5rem;
    position: relative;
}

.form-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
    transition: var(--transition);
}

.form-control {
    width: 100%;
    padding: 0.875rem 1rem;
    border: 2px solid #e1e8ed;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: var(--transition);
    background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    color: var(--dark);
    font-family: inherit;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(132, 6, 31, 0.1), 
                0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.form-control:hover {
    border-color: #c8d1d9;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
}

/* Estilos específicos para inputs */
input[type="text"].form-control,
input[type="email"].form-control,
input[type="tel"].form-control,
input[type="date"].form-control,
input[type="number"].form-control {
    padding-right: 2.5rem;
}

/* Estilos específicos para selects */
select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px;
    padding-right: 2.5rem;
    cursor: pointer;
}

select.form-control:focus {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2384061f' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
}

select.form-control option {
    padding: 0.75rem;
    background: white;
    color: var(--dark);
}

select.form-control option:hover {
    background: var(--primary);
    color: white;
}

/* Estados de validación */
.form-control.is-valid {
    border-color: var(--success);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2327ae60' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M20 6L9 17l-5-5'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px;
    padding-right: 2.5rem;
}

.form-control.is-invalid {
    border-color: var(--danger);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23e74c3c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M18 6L6 18M6 6l12 12'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px;
    padding-right: 2.5rem;
}

/* Grupo de formulario con icono */
.form-group-with-icon {
    position: relative;
}

.form-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--gray);
    transition: var(--transition);
    z-index: 2;
}

.form-control:focus + .form-icon {
    color: var(--primary);
}

.form-group-with-icon .form-control {
    padding-left: 2.5rem;
}

/* Placeholders mejorados */
.form-control::placeholder {
    color: #a0a0a0;
    font-weight: 400;
    opacity: 0.7;
}

.form-control:focus::placeholder {
    opacity: 0.5;
}

/* ===== ESTILOS PARA ICONOS PROFESIONALES ===== */
.icon-wrapper {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: var(--transition);
}

.icon-sm {
    width: 16px;
    height: 16px;
    font-size: 0.875rem;
}

.icon-md {
    width: 20px;
    height: 20px;
    font-size: 1rem;
}

.icon-lg {
    width: 24px;
    height: 24px;
    font-size: 1.125rem;
}

.icon-xl {
    width: 32px;
    height: 32px;
    font-size: 1.5rem;
}

/* Iconos con fondo */
.icon-bg {
    border-radius: 8px;
    padding: 0.5rem;
}

.icon-bg-primary {
    background: rgba(132, 6, 31, 0.1);
    color: var(--primary);
}

.icon-bg-success {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success);
}

.icon-bg-danger {
    background: rgba(231, 76, 60, 0.1);
    color: var(--danger);
}

.icon-bg-warning {
    background: rgba(243, 156, 18, 0.1);
    color: var(--warning);
}

.icon-bg-info {
    background: rgba(52, 152, 219, 0.1);
    color: var(--info);
}

/* Iconos animados */
.icon-hover:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.icon-spin {
    animation: icon-spin 2s linear infinite;
}

@keyframes icon-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* ===== ESTILOS PARA BOTONES MEJORADOS ===== */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    padding: 0.75rem 1.5rem;
    border: 2px solid transparent;
    border-radius: 10px;
    font-weight: 600;
    font-size: 0.9rem;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    font-family: inherit;
}

.btn::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s;
}

.btn:hover::before {
    left: 100%;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    box-shadow: 0 4px 15px rgba(132, 6, 31, 0.3);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(132, 6, 31, 0.4);
}

.btn-outline {
    background: transparent;
    color: var(--dark);
    border-color: var(--border);
}

.btn-outline:hover {
    background: var(--light);
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

/* ===== GRID DE FORMULARIOS PROFESIONAL ===== */
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.form-grid-2 {
    grid-template-columns: repeat(2, 1fr);
}

.form-grid-3 {
    grid-template-columns: repeat(3, 1fr);
}

.form-grid-4 {
    grid-template-columns: repeat(4, 1fr);
}

/* ===== ESTILOS PARA FILTROS AVANZADOS ===== */
.filters-section {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
}

.filters-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--border);
}

.filters-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
}

.filter-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.advanced-filters {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

/* ===== ESTILOS PARA TABLAS PROFESIONALES ===== */
.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
}

.data-table th {
    background: linear-gradient(135deg, var(--light), #f8f9fa);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    border-bottom: 2px solid var(--border);
    font-size: 0.85rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    position: sticky;
    top: 0;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
    vertical-align: middle;
    transition: var(--transition);
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover td {
    background: rgba(132, 6, 31, 0.03);
}

/* ===== ESTILOS PARA CARDS PROFESIONALES ===== */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
    overflow: hidden;
    margin-bottom: 1.5rem;
    transition: var(--transition);
}

.card:hover {
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: linear-gradient(135deg, var(--light), #f8f9fa);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-title {
    font-size: 1.3rem;
    font-weight: 600;
    color: var(--dark);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-body {
    padding: 1.5rem;
}

/* ===== ESTILOS PARA BADGES PROFESIONALES ===== */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    transition: var(--transition);
}

.badge-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
}

.badge-success {
    background: linear-gradient(135deg, var(--success), #2ecc71);
    color: white;
}

.badge-danger {
    background: linear-gradient(135deg, var(--danger), #c0392b);
    color: white;
}

.badge-warning {
    background: linear-gradient(135deg, var(--warning), #e67e22);
    color: white;
}

.badge-info {
    background: linear-gradient(135deg, var(--info), #2980b9);
    color: white;
}

/* ===== ESTILOS RESPONSIVOS ===== */
@media (max-width: 1024px) {
    .form-grid-4 {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-grid-3 {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .form-grid,
    .form-grid-2,
    .form-grid-3,
    .form-grid-4 {
        grid-template-columns: 1fr;
    }
    
    .filters-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .filter-actions {
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.75rem 0.5rem;
    }
}

/* ===== UTILIDADES ===== */
.text-center { text-align: center; }
.text-left { text-align: left; }
.text-right { text-align: right; }

.mb-0 { margin-bottom: 0; }
.mb-1 { margin-bottom: 0.5rem; }
.mb-2 { margin-bottom: 1rem; }
.mb-3 { margin-bottom: 1.5rem; }

.mt-0 { margin-top: 0; }
.mt-1 { margin-top: 0.5rem; }
.mt-2 { margin-top: 1rem; }
.mt-3 { margin-top: 1.5rem; }

.p-0 { padding: 0; }
.p-1 { padding: 0.5rem; }
.p-2 { padding: 1rem; }
.p-3 { padding: 1.5rem; }

.d-flex { display: flex; }
.d-grid { display: grid; }
.d-none { display: none; }
.d-block { display: block; }

.justify-content-between { justify-content: space-between; }
.align-items-center { align-items: center; }
.gap-1 { gap: 0.5rem; }
.gap-2 { gap: 1rem; }
.gap-3 { gap: 1.5rem; }
</style>

<script>
// FUNCIONES JS PARA REPORTES
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de reportes cargada');
    
    // Configurar fechas por defecto
    const hoy = new Date().toISOString().split('T')[0];
    const primerDiaMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    
    if (!document.getElementById('fecha_inicio').value) {
        document.getElementById('fecha_inicio').value = primerDiaMes;
    }
    if (!document.getElementById('fecha_fin').value) {
        document.getElementById('fecha_fin').value = hoy;
    }
});

function limpiarFiltros() {
    const hoy = new Date().toISOString().split('T')[0];
    const primerDiaMes = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
    
    document.getElementById('fecha_inicio').value = primerDiaMes;
    document.getElementById('fecha_fin').value = hoy;
    document.getElementById('conductor_id').value = '';
    document.getElementById('alcoholimetro_id').value = '';
    document.getElementById('resultado').value = '';
    document.getElementById('tipo_prueba').value = '';
    
    // Enviar formulario automáticamente
    document.querySelector('form').submit();
}

function exportarReporte(formato) {
    // Obtener los filtros actuales
    const form = document.querySelector('form');
    const formData = new FormData(form);
    
    // Construir URL de exportación
    let url = `exportar-reporte.php?formato=${formato}`;
    
    for (let [key, value] of formData) {
        if (value) {
            url += `&${key}=${encodeURIComponent(value)}`;
        }
    }
    
    console.log('Exportando reporte:', url);
    
    // Descargar el reporte
    window.open(url, '_blank');
    
    // Para una implementación real, usaríamos:
    /*
    fetch('ajax/exportar-reporte.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `reporte-pruebas-${new Date().toISOString().split('T')[0]}.${formato}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
    })
    .catch(error => {
        console.error('Error al exportar:', error);
        alert('Error al exportar el reporte');
    });
    */
}

// Función para mostrar/ocultar secciones
function toggleSeccion(seccionId) {
    const seccion = document.getElementById(seccionId);
    if (seccion) {
        seccion.style.display = seccion.style.display === 'none' ? 'block' : 'none';
    }
}

// Función para actualizar estadísticas en tiempo real (ejemplo)
function actualizarEstadisticas() {
    console.log('Actualizando estadísticas...');
    // En una implementación real, esto haría una petición AJAX
    // para actualizar las estadísticas sin recargar la página
}

// Configurar actualización automática cada 5 minutos
setInterval(actualizarEstadisticas, 300000);
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>