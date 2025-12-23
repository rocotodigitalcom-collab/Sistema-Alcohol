<?php
// reportes-conductor.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Reporte de Pruebas por Conductor';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'reportes-conductor.php' => 'Reporte por Conductor'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$cliente_id = $_SESSION['cliente_id'] ?? 0;

/* =========================
   DATOS PARA FILTROS
========================= */

$conductores = $db->fetchAll("
    SELECT id, CONCAT(nombre,' ',apellido) AS nombre, dni
    FROM usuarios
    WHERE cliente_id = ? AND rol = 'conductor' AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

$filtros = [
    'conductor_id' => $_POST['conductor_id'] ?? '',
    'fecha_inicio' => $_POST['fecha_inicio'] ?? date('Y-m-01'),
    'fecha_fin'    => $_POST['fecha_fin'] ?? date('Y-m-d')
];

/* =========================
   CONSTRUCCIÓN WHERE
========================= */

$where = ["p.cliente_id = ?"];
$params = [$cliente_id];

if ($filtros['conductor_id']) {
    $where[] = "p.conductor_id = ?";
    $params[] = $filtros['conductor_id'];
}

$where[] = "p.fecha_prueba BETWEEN ? AND ?";
$params[] = $filtros['fecha_inicio'] . " 00:00:00";
$params[] = $filtros['fecha_fin'] . " 23:59:59";

$where_sql = implode(" AND ", $where);

/* =========================
   RESUMEN POR CONDUCTOR
========================= */

$resumen = $db->fetchAll("
    SELECT 
        u.id,
        CONCAT(u.nombre,' ',u.apellido) AS conductor,
        u.dni,
        COUNT(p.id) AS total,
        SUM(p.resultado = 'aprobado') AS aprobadas,
        SUM(p.resultado = 'reprobado') AS reprobadas,
        ROUND(AVG(CASE WHEN p.nivel_alcohol > 0 THEN p.nivel_alcohol END),3) AS promedio
    FROM pruebas p
    INNER JOIN usuarios u ON u.id = p.conductor_id
    WHERE $where_sql
    GROUP BY u.id, u.nombre, u.apellido, u.dni
    ORDER BY reprobadas DESC
", $params);

/* =========================
   DETALLE
========================= */

$detalle = $db->fetchAll("
    SELECT 
        p.fecha_prueba,
        CONCAT(u.nombre,' ',u.apellido) AS conductor,
        u.dni,
        a.nombre AS alcoholimetro,
        p.nivel_alcohol,
        p.resultado,
        p.tipo_prueba,
        p.observaciones
    FROM pruebas p
    LEFT JOIN usuarios u ON u.id = p.conductor_id
    LEFT JOIN alcoholimetros a ON a.id = p.alcoholimetro_id
    WHERE $where_sql
    ORDER BY p.fecha_prueba DESC
    LIMIT 100
", $params);
?>

<div class="content-body">

    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?= $page_title ?></h1>
            <p class="dashboard-subtitle">Análisis de pruebas agrupadas por conductor</p>
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
            <h3><i class="fas fa-filter"></i> Filtros</h3>
        </div>
        <div class="card-body">

            <form method="POST">

                <div class="form-grid">

                    <div class="form-group">
                        <label class="form-label">Conductor</label>
                        <select name="conductor_id" class="form-control input-uniforme">
                            <option value="">Todos los conductores</option>
                            <?php foreach ($conductores as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $filtros['conductor_id']==$c['id']?'selected':'' ?>>
                                    <?= htmlspecialchars($c['nombre'].' - '.$c['dni']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

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
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>

            </form>

        </div>
    </div>

    <!-- RESUMEN -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> Resumen por Conductor</h3>
        </div>
        <div class="card-body">
            <?php if ($resumen): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Conductor</th>
                            <th>DNI</th>
                            <th>Total</th>
                            <th>Aprobadas</th>
                            <th>Reprobadas</th>
                            <th>Promedio Alcohol</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($resumen as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['conductor']) ?></td>
                            <td><?= htmlspecialchars($r['dni']) ?></td>
                            <td><?= $r['total'] ?></td>
                            <td><span class="badge success"><?= $r['aprobadas'] ?></span></td>
                            <td><span class="badge danger"><?= $r['reprobadas'] ?></span></td>
                            <td><strong><?= number_format($r['promedio'],3) ?> g/L</strong></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No hay resultados</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DETALLE -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Detalle de Pruebas</h3>
            <span class="badge primary"><?= count($detalle) ?> registros</span>
        </div>
        <div class="card-body">
            <?php if ($detalle): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Conductor</th>
                            <th>Alcoholímetro</th>
                            <th>Nivel</th>
                            <th>Resultado</th>
                            <th>Tipo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detalle as $d): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($d['fecha_prueba'])) ?></td>
                            <td><?= htmlspecialchars($d['conductor']) ?></td>
                            <td><?= htmlspecialchars($d['alcoholimetro']) ?></td>
                            <td><?= number_format($d['nivel_alcohol'],3) ?> g/L</td>
                            <td>
                                <span class="badge <?= $d['resultado']=='reprobado'?'danger':'success' ?>">
                                    <?= ucfirst($d['resultado']) ?>
                                </span>
                            </td>
                            <td><?= ucfirst($d['tipo_prueba']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <h3>No hay pruebas</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<style>
/* SOLO AJUSTE DE INPUTS – NO TOCA NADA MÁS */
.input-uniforme {
    height: 44px;
    padding: 0.65rem 0.9rem;
    border-radius: 10px;
    font-size: 0.9rem;
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
