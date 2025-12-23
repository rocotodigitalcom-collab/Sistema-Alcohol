<?php
// reportes-fecha.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Reporte de Pruebas por Fecha';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'reportes-fecha.php' => 'Reporte por Fecha'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$cliente_id = $_SESSION['cliente_id'] ?? 0;

/* ===============================
   FILTROS
================================ */
$fecha_inicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_POST['fecha_fin'] ?? date('Y-m-d');

$params = [
    $cliente_id,
    $fecha_inicio . ' 00:00:00',
    $fecha_fin . ' 23:59:59'
];

/* ===============================
   RESUMEN DIARIO
================================ */
$resumen = $db->fetchAll("
    SELECT 
        DATE(fecha_prueba) AS fecha,
        COUNT(*) AS total,
        SUM(resultado = 'aprobado') AS aprobadas,
        SUM(resultado = 'reprobado') AS reprobadas,
        AVG(CASE WHEN nivel_alcohol > 0 THEN nivel_alcohol END) AS promedio
    FROM pruebas
    WHERE cliente_id = ?
      AND fecha_prueba BETWEEN ? AND ?
    GROUP BY DATE(fecha_prueba)
    ORDER BY fecha DESC
", $params);

/* ===============================
   DETALLE
================================ */
$detalle = $db->fetchAll("
    SELECT 
        p.fecha_prueba,
        p.resultado,
        p.nivel_alcohol,
        CONCAT(u.nombre,' ',u.apellido) AS conductor,
        a.nombre AS alcoholimetro
    FROM pruebas p
    LEFT JOIN usuarios u ON u.id = p.conductor_id
    LEFT JOIN alcoholimetros a ON a.id = p.alcoholimetro_id
    WHERE p.cliente_id = ?
      AND p.fecha_prueba BETWEEN ? AND ?
    ORDER BY p.fecha_prueba DESC
    LIMIT 200
", $params);
?>

<div class="content-body">

<!-- HEADER -->
<div class="dashboard-header">
    <div>
        <h1><?= $page_title ?></h1>
        <p class="dashboard-subtitle">Análisis diario de pruebas de alcohol</p>
    </div>
    <a href="index.php" class="btn btn-outline">
        ← Volver
    </a>
</div>

<!-- FILTROS -->
<div class="card">
    <div class="card-header">
        <h3>📅 Filtro por Fecha</h3>
    </div>
    <div class="card-body">
        <form method="POST" class="filters-grid">
            <div>
                <label>Desde</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= $fecha_inicio ?>">
            </div>
            <div>
                <label>Hasta</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= $fecha_fin ?>">
            </div>
            <div class="filters-actions">
                <button class="btn btn-primary">
                    🔍 Filtrar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- RESUMEN -->
<div class="card">
    <div class="card-header">
        <h3>📊 Resumen Diario</h3>
    </div>
    <div class="card-body">

<?php if ($resumen): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Total</th>
            <th>Aprobadas</th>
            <th>Reprobadas</th>
            <th>Promedio Alcohol</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($resumen as $r): ?>
        <tr>
            <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
            <td><?= $r['total'] ?></td>
            <td><span class="badge success"><?= $r['aprobadas'] ?></span></td>
            <td><span class="badge danger"><?= $r['reprobadas'] ?></span></td>
            <td>
                <strong class="<?= $r['promedio'] > 0 ? 'text-danger' : 'text-success' ?>">
                    <?= number_format($r['promedio'], 3) ?> g/L
                </strong>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<div class="empty-state">
    <h3>No hay datos</h3>
    <p>No se encontraron pruebas en el rango seleccionado</p>
</div>
<?php endif; ?>

    </div>
</div>

<!-- DETALLE -->
<div class="card">
    <div class="card-header">
        <h3>📋 Detalle de Pruebas</h3>
        <span class="badge primary"><?= count($detalle) ?> registros</span>
    </div>
    <div class="card-body">

<?php if ($detalle): ?>
<table class="data-table">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Conductor</th>
            <th>Alcoholímetro</th>
            <th>Nivel</th>
            <th>Resultado</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($detalle as $d): ?>
        <tr>
            <td><?= date('d/m/Y H:i', strtotime($d['fecha_prueba'])) ?></td>
            <td><?= htmlspecialchars($d['conductor'] ?? 'N/A') ?></td>
            <td><?= htmlspecialchars($d['alcoholimetro'] ?? 'N/A') ?></td>
            <td class="<?= $d['nivel_alcohol'] > 0 ? 'text-danger' : 'text-success' ?>">
                <?= number_format($d['nivel_alcohol'], 3) ?> g/L
            </td>
            <td>
                <span class="badge <?= $d['resultado'] === 'reprobado' ? 'danger' : 'success' ?>">
                    <?= ucfirst($d['resultado']) ?>
                </span>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
<div class="empty-state">
    <h3>No hay registros</h3>
</div>
<?php endif; ?>

    </div>
</div>

</div>

<style>
/* ===== AJUSTES VISUALES SISTEMA ===== */
.filters-grid{
    display:grid;
    grid-template-columns:1fr 1fr auto;
    gap:1.5rem;
    align-items:end;
}
.filters-actions{
    padding-bottom:2px;
}
.data-table{
    width:100%;
    border-collapse:collapse;
}
.data-table th{
    background:#f5f6f7;
    padding:12px;
    font-size:13px;
    text-transform:uppercase;
}
.data-table td{
    padding:12px;
    border-top:1px solid #eee;
}
.card-header{
    display:flex;
    justify-content:space-between;
    align-items:center;
}
@media(max-width:768px){
    .filters-grid{
        grid-template-columns:1fr;
    }
}

/* ===============================
   FIX INPUTS FECHA – SOLO VISUAL
   NO ALTERA FUNCIONALIDAD
================================ */

.filters-grid input[type="date"] {
    height: 44px;
    padding: 0 14px;
    border-radius: 8px;
    border: 1px solid #dcdcdc;
    font-size: 14px;
    background-color: #fff;
    color: #333;
    box-shadow: none;
    display: flex;
    align-items: center;
}

/* Quita estilos raros del navegador */
.filters-grid input[type="date"]::-webkit-inner-spin-button,
.filters-grid input[type="date"]::-webkit-clear-button {
    display: none;
}

/* Corrige icono calendario */
.filters-grid input[type="date"]::-webkit-calendar-picker-indicator {
    opacity: 0.6;
    cursor: pointer;
}

/* Alinea inputs con el botón */
.filters-grid {
    align-items: center;
}

/* Label más consistente */
.filters-grid label {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 6px;
    display: block;
}

</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
