<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Vehículos';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'vehiculos.php' => 'Vehículos'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// ==========================
// FILTROS
// ==========================
$estado = $_GET['estado'] ?? '';
$buscar = trim($_GET['buscar'] ?? '');

$where = ['cliente_id = ?'];
$params = [$cliente_id];

if ($estado !== '') {
    $where[] = 'estado = ?';
    $params[] = $estado;
}

if ($buscar !== '') {
    $where[] = '(placa LIKE ? OR marca LIKE ? OR modelo LIKE ?)';
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
    $params[] = "%$buscar%";
}

$where_sql = implode(' AND ', $where);

// ==========================
// CONSULTA REAL (SIN CAMPOS INVENTADOS)
// ==========================
$vehiculos = $db->fetchAll("
    SELECT 
        id,
        placa,
        marca,
        modelo,
        anio,
        color,
        kilometraje,
        estado
    FROM vehiculos
    WHERE $where_sql
    ORDER BY marca, modelo, placa
", $params);
?>

<div class="content-body">

    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?= $page_title ?></h1>
            <p class="dashboard-subtitle">Listado general de vehículos registrados</p>
        </div>
        <div class="header-actions">
            <a href="registrar-vehiculo.php" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Nuevo Vehículo
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
                    <label>Buscar</label>
                    <input type="text" name="buscar" class="form-control"
                           value="<?= htmlspecialchars($buscar) ?>"
                           placeholder="Placa, marca o modelo">
                </div>

                <div class="form-group">
                    <label>Estado</label>
                    <select name="estado" class="form-control">
                        <option value="">Todos</option>
                        <option value="activo" <?= $estado === 'activo' ? 'selected' : '' ?>>Activo</option>
                        <option value="inactivo" <?= $estado === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                        <option value="mantenimiento" <?= $estado === 'mantenimiento' ? 'selected' : '' ?>>Mantenimiento</option>
                    </select>
                </div>

                <div class="form-group d-flex align-items-end">
                    <button class="btn btn-primary btn-sm">
                        <i class="fas fa-search"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLA -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-car"></i> Vehículos</h3>
            <span class="badge primary"><?= count($vehiculos) ?> registros</span>
        </div>
        <div class="card-body">
            <?php if ($vehiculos): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                        <tr>
                            <th>Placa</th>
                            <th>Marca</th>
                            <th>Modelo</th>
                            <th>Año</th>
                            <th>Kilometraje</th>
                            <th>Estado</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($vehiculos as $v): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($v['placa']) ?></strong></td>
                                <td><?= htmlspecialchars($v['marca']) ?></td>
                                <td><?= htmlspecialchars($v['modelo']) ?></td>
                                <td><?= $v['anio'] ?: '-' ?></td>
                                <td><?= $v['kilometraje'] ? number_format($v['kilometraje']) . ' km' : '-' ?></td>
                                <td>
                                    <span class="badge <?= $v['estado'] === 'activo' ? 'success' : ($v['estado'] === 'mantenimiento' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($v['estado']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-car"></i></div>
                    <h3>No hay vehículos</h3>
                    <p>No se encontraron registros con los filtros aplicados</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
