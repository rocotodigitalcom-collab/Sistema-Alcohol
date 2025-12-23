<?php
// kpi.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Indicadores KPI';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'kpi.php' => 'Indicadores KPI'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$cliente_id = $_SESSION['cliente_id'] ?? 0;

/* =========================
   FILTROS
========================= */

$filtros = [
    'fecha_inicio' => $_POST['fecha_inicio'] ?? date('Y-m-01'),
    'fecha_fin'    => $_POST['fecha_fin'] ?? date('Y-m-d')
];

$where = ["p.cliente_id = ?"];
$params = [$cliente_id];

$where[] = "p.fecha_prueba BETWEEN ? AND ?";
$params[] = $filtros['fecha_inicio'] . " 00:00:00";
$params[] = $filtros['fecha_fin'] . " 23:59:59";

$where_sql = implode(" AND ", $where);

/* =========================
   KPIs GENERALES
========================= */

$kpis = $db->fetchOne("
    SELECT
        COUNT(*) AS total_pruebas,
        SUM(p.resultado = 'aprobado') AS aprobadas,
        SUM(p.resultado = 'reprobado') AS reprobadas,
        ROUND(
            (SUM(p.resultado = 'reprobado') / COUNT(*)) * 100, 1
        ) AS tasa_reprobacion,
        ROUND(AVG(CASE WHEN p.nivel_alcohol > 0 THEN p.nivel_alcohol END),3) AS promedio_alcohol,
        COUNT(DISTINCT p.conductor_id) AS conductores_evaluados
    FROM pruebas p
    WHERE $where_sql
", $params);

/* =========================
   KPI POR DÍA
========================= */

$kpi_diario = $db->fetchAll("
    SELECT
        DATE(p.fecha_prueba) AS fecha,
        COUNT(*) AS total,
        SUM(p.resultado = 'reprobado') AS reprobadas,
        ROUND(AVG(CASE WHEN p.nivel_alcohol > 0 THEN p.nivel_alcohol END),3) AS promedio
    FROM pruebas p
    WHERE $where_sql
    GROUP BY DATE(p.fecha_prueba)
    ORDER BY fecha DESC
", $params);

/* =========================
   TOP CONDUCTORES
========================= */

$top_conductores = $db->fetchAll("
    SELECT
        CONCAT(u.nombre,' ',u.apellido) AS conductor,
        u.dni,
        COUNT(p.id) AS total,
        SUM(p.resultado = 'reprobado') AS reprobadas
    FROM pruebas p
    INNER JOIN usuarios u ON u.id = p.conductor_id
    WHERE $where_sql
    GROUP BY u.id, u.nombre, u.apellido, u.dni
    HAVING reprobadas > 0
    ORDER BY reprobadas DESC
    LIMIT 5
", $params);
?>

<div class="content-body">

    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?= $page_title ?></h1>
            <p class="dashboard-subtitle">Indicadores clave de desempeño del sistema</p>
        </div>
        <div class="header-actions">
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filtro por Fecha</h3>
        </div>
        <div class="card-body">

            <form method="POST">
                <div class="form-grid">

                    <div class="form-group">
                        <label class="form-label">Desde</label>
                        <input type="date"
                               name="fecha_inicio"
                               class="form-control input-uniforme"
                               value="<?= htmlspecialchars($filtros['fecha_inicio']) ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Hasta</label>
                        <input type="date"
                               name="fecha_fin"
                               class="form-control input-uniforme"
                               value="<?= htmlspecialchars($filtros['fecha_fin']) ?>">
                    </div>

                </div>

                <div class="filter-actions">
                    <button class="btn btn-primary">
                        <i class="fas fa-search"></i> Aplicar
                    </button>
                </div>
            </form>

        </div>
    </div>

    <!-- KPIs -->
    <div class="stats-grid">

        <div class="stat-card">
            <div class="stat-icon primary"><i class="fas fa-vial"></i></div>
            <div class="stat-info">
                <h3><?= $kpis['total_pruebas'] ?? 0 ?></h3>
                <p>Total Pruebas</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <h3><?= $kpis['aprobadas'] ?? 0 ?></h3>
                <p>Aprobadas</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon danger"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info">
                <h3><?= $kpis['reprobadas'] ?? 0 ?></h3>
                <p>Reprobadas</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon warning"><i class="fas fa-percentage"></i></div>
            <div class="stat-info">
                <h3><?= $kpis['tasa_reprobacion'] ?? 0 ?>%</h3>
                <p>Tasa Reprobación</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon info"><i class="fas fa-tachometer-alt"></i></div>
            <div class="stat-info">
                <h3><?= number_format($kpis['promedio_alcohol'] ?? 0,3) ?> g/L</h3>
                <p>Promedio Alcohol</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon secondary"><i class="fas fa-users"></i></div>
            <div class="stat-info">
                <h3><?= $kpis['conductores_evaluados'] ?? 0 ?></h3>
                <p>Conductores Evaluados</p>
            </div>
        </div>

    </div>

    <!-- KPI DIARIO -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-calendar"></i> KPI Diario</h3>
        </div>
        <div class="card-body">
            <?php if ($kpi_diario): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Reprobadas</th>
                            <th>Promedio Alcohol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kpi_diario as $d): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($d['fecha'])) ?></td>
                            <td><?= $d['total'] ?></td>
                            <td><span class="badge danger"><?= $d['reprobadas'] ?></span></td>
                            <td><?= number_format($d['promedio'],3) ?> g/L</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state"><h3>No hay datos</h3></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TOP CONDUCTORES -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-exclamation-triangle"></i> Top Conductores con Incidencias</h3>
        </div>
        <div class="card-body">
            <?php if ($top_conductores): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Conductor</th>
                            <th>DNI</th>
                            <th>Total Pruebas</th>
                            <th>Reprobadas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_conductores as $c): ?>
                        <tr>
                            <td><?= htmlspecialchars($c['conductor']) ?></td>
                            <td><?= htmlspecialchars($c['dni']) ?></td>
                            <td><?= $c['total'] ?></td>
                            <td><span class="badge danger"><?= $c['reprobadas'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state"><h3>Sin incidencias</h3></div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
/* SOLO INPUTS – MISMO FIX QUE REPORTES */
.input-uniforme{
    height:44px;
    padding:0.65rem 0.9rem;
    border-radius:10px;
    font-size:0.9rem;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
