<?php
// auditoria.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Auditoría del Sistema';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'auditoria.php' => 'Auditoría'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$cliente_id = $_SESSION['cliente_id'] ?? 0;

/* ===============================
   FILTROS
================================ */
$f_accion = $_GET['accion'] ?? '';
$f_tabla  = $_GET['tabla'] ?? '';
$f_desde  = $_GET['desde'] ?? '';
$f_hasta  = $_GET['hasta'] ?? '';

$where = ['a.cliente_id = ?'];
$params = [$cliente_id];

if ($f_accion !== '') {
    $where[] = 'a.accion = ?';
    $params[] = $f_accion;
}

if ($f_tabla !== '') {
    $where[] = 'a.tabla_afectada = ?';
    $params[] = $f_tabla;
}

if ($f_desde !== '') {
    $where[] = 'DATE(a.fecha_creacion) >= ?';
    $params[] = $f_desde;
}

if ($f_hasta !== '') {
    $where[] = 'DATE(a.fecha_creacion) <= ?';
    $params[] = $f_hasta;
}

/* ===============================
   LISTADO AUDITORÍA
================================ */
$auditorias = $db->fetchAll("
    SELECT 
        a.id,
        a.accion,
        a.tabla_afectada,
        a.registro_id,
        a.detalles,
        a.ip_address,
        a.user_agent,
        a.fecha_creacion,
        u.nombre AS usuario
    FROM auditoria a
    LEFT JOIN usuarios u ON u.id = a.usuario_id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY a.fecha_creacion DESC
    LIMIT 500
", $params);

/* ===============================
   DATOS PARA SELECTS
================================ */
$acciones = $db->fetchAll("
    SELECT DISTINCT accion FROM auditoria
    WHERE cliente_id = ?
    ORDER BY accion
", [$cliente_id]);

$tablas = $db->fetchAll("
    SELECT DISTINCT tabla_afectada FROM auditoria
    WHERE cliente_id = ?
    ORDER BY tabla_afectada
", [$cliente_id]);
?>

<div class="content-body">

<div class="dashboard-header">
    <div class="welcome-section">
        <h1><?php echo $page_title; ?></h1>
        <p class="dashboard-subtitle">Registro de acciones y eventos del sistema</p>
    </div>
    <div class="header-actions">
        <a href="index.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i>Volver
        </a>
    </div>
</div>

<!-- FILTROS -->
<div class="card">
<div class="card-header">
    <h3><i class="fas fa-filter"></i> Filtros</h3>
</div>
<div class="card-body">
<form method="GET" class="form-grid">
    <div class="form-group">
        <label>Acción</label>
        <select name="accion" class="form-control">
            <option value="">Todas</option>
            <?php foreach ($acciones as $a): ?>
                <option value="<?php echo $a['accion']; ?>" <?php if ($f_accion === $a['accion']) echo 'selected'; ?>>
                    <?php echo $a['accion']; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Tabla</label>
        <select name="tabla" class="form-control">
            <option value="">Todas</option>
            <?php foreach ($tablas as $t): ?>
                <option value="<?php echo $t['tabla_afectada']; ?>" <?php if ($f_tabla === $t['tabla_afectada']) echo 'selected'; ?>>
                    <?php echo $t['tabla_afectada']; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-group">
        <label>Desde</label>
        <input type="date" name="desde" class="form-control" value="<?php echo htmlspecialchars($f_desde); ?>">
    </div>

    <div class="form-group">
        <label>Hasta</label>
        <input type="date" name="hasta" class="form-control" value="<?php echo htmlspecialchars($f_hasta); ?>">
    </div>

    <div class="form-group">
        <button class="btn btn-primary">
            <i class="fas fa-filter"></i> Filtrar
        </button>
        <a href="auditoria.php" class="btn btn-outline">
            Limpiar
        </a>
    </div>
</form>
</div>
</div>

<!-- LISTADO -->
<div class="card">
<div class="card-header">
    <h3><i class="fas fa-list"></i> Registros de Auditoría</h3>
    <span class="badge primary"><?php echo count($auditorias); ?> registros</span>
</div>
<div class="card-body">

<?php if ($auditorias): ?>
<div class="table-responsive">
<table class="data-table">
<thead>
<tr>
    <th>Fecha</th>
    <th>Usuario</th>
    <th>Acción</th>
    <th>Tabla</th>
    <th>ID</th>
    <th>Detalles</th>
    <th>IP</th>
</tr>
</thead>
<tbody>
<?php foreach ($auditorias as $a): ?>
<tr>
    <td><?php echo date('d/m/Y H:i', strtotime($a['fecha_creacion'])); ?></td>
    <td><?php echo htmlspecialchars($a['usuario'] ?? 'Sistema'); ?></td>
    <td><strong><?php echo htmlspecialchars($a['accion']); ?></strong></td>
    <td><?php echo htmlspecialchars($a['tabla_afectada']); ?></td>
    <td><?php echo (int)$a['registro_id']; ?></td>
    <td><?php echo htmlspecialchars($a['detalles']); ?></td>
    <td><?php echo htmlspecialchars($a['ip_address']); ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state">
    <div class="empty-icon">
        <i class="fas fa-clipboard-list"></i>
    </div>
    <h3>No hay registros de auditoría</h3>
    <p>No se encontraron acciones para los filtros aplicados</p>
</div>
<?php endif; ?>

</div>
</div>

</div>

<style>
/* === ESTILO MISMO PATRÓN === */
.form-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:1rem; margin-bottom:1rem; }
.form-group { display:flex; flex-direction:column; }
.form-group label { font-weight:600; margin-bottom:0.5rem; color:var(--dark); }
.form-control { padding:0.75rem; border:2px solid var(--border); border-radius:8px; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
