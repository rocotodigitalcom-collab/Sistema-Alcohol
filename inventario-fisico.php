<?php
// inventario-fisico.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db         = new Database();
$user_id    = $_SESSION['user_id']     ?? 0;
$cliente_id = $_SESSION['cliente_id']  ?? 0;
$rol        = $_SESSION['rol']         ?? '';

if (!$user_id) {
    header('Location: login.php');
    exit;
}

// --------------------------------------------------------
// VISTA ACTUAL
// --------------------------------------------------------
$view    = $_GET['view']    ?? '';
$toma_id = intval($_GET['toma_id'] ?? 0);
// pestaña interna cuando estamos en detalle
$tab     = $_GET['tab']     ?? 'resumen';

if ($view === '') {
    $view = in_array($rol, ['super_admin', 'admin', 'supervisor'])
        ? 'listado'
        : 'mis_tomas';
}

// --------------------------------------------------------
// FLASH MESSAGES
// --------------------------------------------------------
if (!isset($_SESSION['flash'])) {
    $_SESSION['flash'] = ['success' => [], 'error' => []];
}

function add_flash($type, $msg) {
    $_SESSION['flash'][$type][] = $msg;
}

// --------------------------------------------------------
// ACCIONES POST
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {

        // CREAR NUEVA TOMA
        if ($action === 'crear_toma') {

            $nombre_toma      = trim($_POST['nombre_toma'] ?? '');
            $fecha_programada = trim($_POST['fecha_programada'] ?? '');
            $ubicacion        = trim($_POST['ubicacion'] ?? '');
            $comentarios      = trim($_POST['comentarios'] ?? '');

            if ($nombre_toma === '' || $ubicacion === '') {
                add_flash('error', 'El nombre de la toma y la ubicación son obligatorios.');
                header('Location: inventario-fisico.php?view=nueva');
                exit;
            }

            $db->execute("
                INSERT INTO inventario_tomas
                (cliente_id, nombre_toma, fecha_programada, ubicacion, comentarios, estado, responsable_id, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, 'abierta', ?, NOW())
            ", [
                $cliente_id,
                $nombre_toma,
                $fecha_programada ?: null,
                $ubicacion,
                $comentarios,
                $user_id
            ]);

            $new_id = $db->lastInsertId();
            add_flash('success', 'Toma de inventario creada correctamente.');
            header('Location: inventario-fisico.php?view=detalle&toma_id=' . $new_id . '&tab=resumen');
            exit;
        }

        // AGREGAR ITEM
        if ($action === 'agregar_item') {

            $toma_id          = intval($_POST['toma_id'] ?? 0);
            $codigo_item      = trim($_POST['codigo_item'] ?? '');
            $descripcion_item = trim($_POST['descripcion_item'] ?? '');
            $ubicacion_fisica = trim($_POST['ubicacion_fisica'] ?? '');
            $conteo_fisico    = trim($_POST['conteo_fisico'] ?? '');
            $observaciones    = trim($_POST['observaciones'] ?? '');

            if ($toma_id <= 0) {
                add_flash('error', 'Toma de inventario no válida.');
                header('Location: inventario-fisico.php?view=listado');
                exit;
            }

            if ($codigo_item === '' && $descripcion_item === '') {
                add_flash('error', 'Debes ingresar al menos un código o una descripción del ítem.');
                header('Location: inventario-fisico.php?view=detalle&toma_id=' . $toma_id . '&tab=items');
                exit;
            }

            $db->execute("
                INSERT INTO inventario_detalles
                (toma_id, codigo_item, descripcion_item, ubicacion_fisica, conteo_fisico, observaciones, fecha_registro, usuario_registro)
                VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
            ", [
                $toma_id,
                $codigo_item,
                $descripcion_item,
                $ubicacion_fisica,
                $conteo_fisico !== '' ? floatval($conteo_fisico) : null,
                $observaciones,
                $user_id
            ]);

            add_flash('success', 'Ítem agregado correctamente.');
            header('Location: inventario-fisico.php?view=detalle&toma_id=' . $toma_id . '&tab=items');
            exit;
        }

        // ACTUALIZAR ITEM
        if ($action === 'actualizar_item') {

            $detalle_id       = intval($_POST['detalle_id'] ?? 0);
            $toma_id          = intval($_POST['toma_id'] ?? 0);
            $codigo_item      = trim($_POST['codigo_item'] ?? '');
            $descripcion_item = trim($_POST['descripcion_item'] ?? '');
            $ubicacion_fisica = trim($_POST['ubicacion_fisica'] ?? '');
            $conteo_fisico    = trim($_POST['conteo_fisico'] ?? '');
            $observaciones    = trim($_POST['observaciones'] ?? '');

            if ($detalle_id <= 0 || $toma_id <= 0) {
                add_flash('error', 'No se pudo actualizar el ítem.');
                header('Location: inventario-fisico.php?view=listado');
                exit;
            }

            $db->execute("
                UPDATE inventario_detalles
                SET codigo_item = ?,
                    descripcion_item = ?,
                    ubicacion_fisica = ?,
                    conteo_fisico = ?,
                    observaciones = ?,
                    fecha_actualizacion = NOW(),
                    usuario_actualizacion = ?
                WHERE id = ? AND toma_id = ?
            ", [
                $codigo_item,
                $descripcion_item,
                $ubicacion_fisica,
                $conteo_fisico !== '' ? floatval($conteo_fisico) : null,
                $observaciones,
                $user_id,
                $detalle_id,
                $toma_id
            ]);

            add_flash('success', 'Ítem actualizado.');
            header('Location: inventario-fisico.php?view=detalle&toma_id=' . $toma_id . '&tab=items');
            exit;
        }

        // ELIMINAR ITEM
        if ($action === 'eliminar_item') {

            $detalle_id = intval($_POST['detalle_id'] ?? 0);
            $toma_id    = intval($_POST['toma_id'] ?? 0);

            if ($detalle_id <= 0 || $toma_id <= 0) {
                add_flash('error', 'No se pudo eliminar el ítem.');
                header('Location: inventario-fisico.php?view=listado');
                exit;
            }

            $db->execute("
                DELETE FROM inventario_detalles
                WHERE id = ? AND toma_id = ?
            ", [$detalle_id, $toma_id]);

            add_flash('success', 'Ítem eliminado.');
            header('Location: inventario-fisico.php?view=detalle&toma_id=' . $toma_id . '&tab=items');
            exit;
        }

        // CERRAR TOMA
        if ($action === 'cerrar_toma') {

            $toma_id = intval($_POST['toma_id'] ?? 0);

            if ($toma_id <= 0) {
                add_flash('error', 'ID de toma no válido.');
                header('Location: inventario-fisico.php?view=listado');
                exit;
            }

            $db->execute("
                UPDATE inventario_tomas
                SET estado = 'cerrada',
                    fecha_cierre = NOW(),
                    usuario_cierre = ?
                WHERE id = ? AND cliente_id = ?
            ", [$user_id, $toma_id, $cliente_id]);

            add_flash('success', 'Toma cerrada correctamente.');
            header('Location: inventario-fisico.php?view=detalle&toma_id=' . $toma_id . '&tab=resumen');
            exit;
        }

        // EXPORTAR TOMA A XLS
        if ($action === 'exportar_toma') {

            $toma_id = intval($_POST['toma_id'] ?? 0);

            if ($toma_id <= 0) {
                add_flash('error', 'No se pudo exportar la toma.');
                header('Location: inventario-fisico.php?view=listado');
                exit;
            }

            $toma = $db->fetchOne("
                SELECT *
                FROM inventario_tomas
                WHERE id = ? AND cliente_id = ?
            ", [$toma_id, $cliente_id]);

            if (!$toma) {
                add_flash('error', 'La toma no existe.');
                header('Location: inventario-fisico.php?view=listado');
                exit;
            }

            $detalles = $db->fetchAll("
                SELECT *
                FROM inventario_detalles
                WHERE toma_id = ?
                ORDER BY id ASC
            ", [$toma_id]);

            header("Content-Type: application/vnd.ms-excel; charset=utf-8");
            header("Content-Disposition: attachment; filename=\"toma_inventario_$toma_id.xls\"");

            echo "ID Toma:\t" . $toma['id'] . "\n";
            echo "Nombre:\t" . $toma['nombre_toma'] . "\n";
            echo "Ubicación:\t" . $toma['ubicacion'] . "\n";
            echo "Fecha programada:\t" . $toma['fecha_programada'] . "\n";
            echo "Estado:\t" . $toma['estado'] . "\n\n";

            echo "ID Detalle\tCódigo\tDescripción\tUbicación\tConteo físico\tObservaciones\n";

            foreach ($detalles as $d) {
                echo $d['id'] . "\t"
                   . ($d['codigo_item'] ?? '') . "\t"
                   . ($d['descripcion_item'] ?? '') . "\t"
                   . ($d['ubicacion_fisica'] ?? '') . "\t"
                   . ($d['conteo_fisico'] !== null ? $d['conteo_fisico'] : '') . "\t"
                   . str_replace(["\r", "\n"], ' ', $d['observaciones'] ?? '') . "\n";
            }

            exit;
        }

    } catch (Exception $e) {
        add_flash('error', 'Error: ' . $e->getMessage());
        header('Location: inventario-fisico.php?view=' . urlencode($view));
        exit;
    }
}

// --------------------------------------------------------
// CARGA DE DATOS PARA LAS VISTAS
// --------------------------------------------------------
$tomas       = [];
$toma_actual = null;
$detalles    = [];

// LISTADO / HISTORIAL
if ($view === 'listado' || $view === 'historial') {

    $solo_cerradas = ($view === 'historial');

    $sql = "
        SELECT t.*, u.nombre AS responsable_nombre
        FROM inventario_tomas t
        LEFT JOIN usuarios u ON u.id = t.responsable_id
        WHERE t.cliente_id = ?
    ";
    $params = [$cliente_id];

    if (!in_array($rol, ['super_admin', 'admin', 'supervisor'])) {
        $sql .= " AND t.responsable_id = ? ";
        $params[] = $user_id;
    }

    if ($solo_cerradas) {
        $sql .= " AND t.estado = 'cerrada' ";
    }

    $sql .= " ORDER BY t.fecha_creacion DESC";

    $tomas = $db->fetchAll($sql, $params);
}

// DETALLE
if ($view === 'detalle' && $toma_id > 0) {

    $toma_actual = $db->fetchOne("
        SELECT t.*, u.nombre AS responsable_nombre
        FROM inventario_tomas t
        LEFT JOIN usuarios u ON u.id = t.responsable_id
        WHERE t.id = ? AND t.cliente_id = ?
    ", [$toma_id, $cliente_id]);

    if ($toma_actual) {
        $detalles = $db->fetchAll("
            SELECT *
            FROM inventario_detalles
            WHERE toma_id = ?
            ORDER BY id ASC
        ", [$toma_id]);
    }
}

// MIS TOMAS
if ($view === 'mis_tomas') {
    $tomas = $db->fetchAll("
        SELECT t.*, u.nombre AS responsable_nombre
        FROM inventario_tomas t
        LEFT JOIN usuarios u ON u.id = t.responsable_id
        WHERE t.cliente_id = ? AND t.responsable_id = ?
        ORDER BY t.fecha_creacion DESC
    ", [$cliente_id, $user_id]);
}

// --------------------------------------------------------
// HEADER Y BREADCRUMBS
// --------------------------------------------------------
$page_title = 'Toma de Inventario Físico';
$breadcrumbs = [
    'index.php' => 'Dashboard Logístico',
    'inventario-fisico.php' => 'Toma de Inventario Físico'
];

require_once __DIR__ . '/includes/header-logistica.php';
?>

<div class="app-content">
    <div class="container-fluid">

        <div class="row">
            <!-- CONTENIDO PRINCIPAL -->
            <div class="col-lg-12">

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h3 class="mb-0">
                            <i class="fas fa-clipboard-check"></i> Toma de Inventario Físico
                        </h3>
                        <div class="text-muted small">
                            Control y seguimiento avanzado de inventarios físicos por almacén.
                        </div>
                    </div>
                </div>

                <!-- TABS PRINCIPALES (ESTILO 4 CON ICONOS) -->
                <?php
                // Para marcar activo: detalle y mis_tomas pertenecen al tab "Tomas"
                $tab_main = 'listado';
                if ($view === 'nueva')      $tab_main = 'nueva';
                if ($view === 'historial')  $tab_main = 'historial';
                if (in_array($view, ['listado', 'detalle', 'mis_tomas'])) {
                    $tab_main = 'listado';
                }
                ?>
                <div class="mb-3">
                    <div class="btn-group" role="group" aria-label="Tabs inventario">
                        <a href="inventario-fisico.php?view=listado"
                           class="btn btn-sm <?php echo $tab_main === 'listado' ? 'btn-primary' : 'btn-outline-primary'; ?>">
                            <i class="fas fa-clipboard-list me-1"></i> Tomas
                        </a>
                        <?php if (in_array($rol, ['super_admin','admin','supervisor'])): ?>
                            <a href="inventario-fisico.php?view=nueva"
                               class="btn btn-sm <?php echo $tab_main === 'nueva' ? 'btn-success' : 'btn-outline-success'; ?>">
                                <i class="fas fa-plus-circle me-1"></i> Nueva Toma
                            </a>
                        <?php endif; ?>
                        <a href="inventario-fisico.php?view=historial"
                           class="btn btn-sm <?php echo $tab_main === 'historial' ? 'btn-secondary' : 'btn-outline-secondary'; ?>">
                            <i class="fas fa-folder-open me-1"></i> Historial
                        </a>
                    </div>
                </div>

                <!-- FLASH -->
                <?php foreach ($_SESSION['flash']['success'] as $m): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <?= htmlspecialchars($m); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($_SESSION['flash']['error'] as $m): ?>
                    <div class="alert alert-danger alert-dismissible fade show">
                        <?= htmlspecialchars($m); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endforeach; ?>
                <?php $_SESSION['flash'] = ['success'=>[],'error'=>[]]; ?>

                <?php
                // =========================================
                // VISTA: LISTADO DE TOMAS
                // =========================================
                if ($view === 'listado' || $view === 'mis_tomas'): ?>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <strong>
                                <?php if ($view === 'mis_tomas'): ?>
                                    Mis tomas asignadas
                                <?php else: ?>
                                    Tomas de inventario
                                <?php endif; ?>
                            </strong>
                            <?php if (in_array($rol, ['super_admin','admin','supervisor'])): ?>
                                <a href="inventario-fisico.php?view=nueva" class="btn btn-sm btn-primary">
                                    <i class="fas fa-plus-circle me-1"></i> Nueva toma
                                </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body table-responsive">
                            <?php if (!$tomas): ?>
                                <p class="text-muted mb-0">
                                    <?php if ($view === 'mis_tomas'): ?>
                                        No tienes tomas asignadas.
                                    <?php else: ?>
                                        No hay tomas registradas.
                                    <?php endif; ?>
                                </p>
                            <?php else: ?>
                                <table class="table table-sm table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Ubicación</th>
                                            <?php if ($view !== 'mis_tomas'): ?>
                                                <th>Responsable</th>
                                            <?php endif; ?>
                                            <th>Fecha prog.</th>
                                            <th>Estado</th>
                                            <th>Ítems</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($tomas as $t): ?>
                                        <?php
                                        $items = $db->fetchOne("
                                            SELECT COUNT(*) AS c
                                            FROM inventario_detalles
                                            WHERE toma_id = ?
                                        ", [$t['id']]);
                                        $badge = $t['estado'] === 'abierta' ? 'success' : 'dark';
                                        ?>
                                        <tr>
                                            <td>#<?= (int)$t['id']; ?></td>
                                            <td><?= htmlspecialchars($t['nombre_toma']); ?></td>
                                            <td><?= htmlspecialchars($t['ubicacion']); ?></td>
                                            <?php if ($view !== 'mis_tomas'): ?>
                                                <td><?= htmlspecialchars($t['responsable_nombre'] ?? ''); ?></td>
                                            <?php endif; ?>
                                            <td><?= htmlspecialchars($t['fecha_programada'] ?? ''); ?></td>
                                            <td>
                                                <span class="badge bg-<?= $badge; ?>">
                                                    <?= htmlspecialchars($t['estado']); ?>
                                                </span>
                                            </td>
                                            <td><?= (int)($items['c'] ?? 0); ?></td>
                                            <td>
                                                <a href="inventario-fisico.php?view=detalle&toma_id=<?= (int)$t['id']; ?>&tab=resumen"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-folder-open"></i> Abrir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php
                // =========================================
                // VISTA: HISTORIAL (TOMAS CERRADAS)
                // =========================================
                elseif ($view === 'historial'): ?>

                    <div class="card">
                        <div class="card-header">
                            <strong>Tomas cerradas</strong>
                        </div>
                        <div class="card-body table-responsive">
                            <?php if (!$tomas): ?>
                                <p class="text-muted mb-0">No hay tomas cerradas.</p>
                            <?php else: ?>
                                <table class="table table-sm table-hover align-middle">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Nombre</th>
                                            <th>Ubicación</th>
                                            <th>Responsable</th>
                                            <th>Fecha prog.</th>
                                            <th>Fecha cierre</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($tomas as $t): ?>
                                        <tr>
                                            <td>#<?= (int)$t['id']; ?></td>
                                            <td><?= htmlspecialchars($t['nombre_toma']); ?></td>
                                            <td><?= htmlspecialchars($t['ubicacion']); ?></td>
                                            <td><?= htmlspecialchars($t['responsable_nombre'] ?? ''); ?></td>
                                            <td><?= htmlspecialchars($t['fecha_programada'] ?? ''); ?></td>
                                            <td><?= htmlspecialchars($t['fecha_cierre'] ?? ''); ?></td>
                                            <td>
                                                <a href="inventario-fisico.php?view=detalle&toma_id=<?= (int)$t['id']; ?>&tab=resumen"
                                                   class="btn btn-sm btn-outline-primary mb-1">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                                <form method="post" class="d-inline">
                                                    <input type="hidden" name="action" value="exportar_toma">
                                                    <input type="hidden" name="toma_id" value="<?= (int)$t['id']; ?>">
                                                    <button class="btn btn-sm btn-outline-success">
                                                        <i class="fas fa-file-excel"></i> Exportar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php
                // =========================================
                // VISTA: NUEVA TOMA
                // =========================================
                elseif ($view === 'nueva' && in_array($rol,['super_admin','admin','supervisor'])): ?>

                    <div class="card">
                        <div class="card-header">
                            <strong>Nueva toma de inventario</strong>
                        </div>
                        <div class="card-body">
                            <form method="post" class="row g-3">
                                <input type="hidden" name="action" value="crear_toma">

                                <div class="col-md-6">
                                    <label class="form-label">Nombre de la toma *</label>
                                    <input type="text" name="nombre_toma" class="form-control form-control-sm" required>
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Fecha programada</label>
                                    <input type="date" name="fecha_programada" class="form-control form-control-sm">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label">Ubicación / Almacén *</label>
                                    <input type="text" name="ubicacion" class="form-control form-control-sm" required>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">Comentarios</label>
                                    <textarea name="comentarios" rows="3" class="form-control form-control-sm"
                                              placeholder="Instrucciones, alcance, turno, etc."></textarea>
                                </div>

                                <div class="col-12 text-end">
                                    <a href="inventario-fisico.php?view=listado"
                                       class="btn btn-outline-secondary btn-sm">
                                        Cancelar
                                    </a>
                                    <button class="btn btn-primary btn-sm">
                                        Guardar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                <?php
                // =========================================
                // VISTA: DETALLE DE TOMA (con tabs internos)
                // =========================================
                elseif ($view === 'detalle' && $toma_actual): ?>

                    <?php
                    // Stats básicos para pestaña "Diferencias / Avance"
                    $total_items  = count($detalles);
                    $con_conteo   = 0;
                    $sin_conteo   = 0;

                    foreach ($detalles as $d) {
                        if ($d['conteo_fisico'] !== null && $d['conteo_fisico'] !== '' && $d['conteo_fisico'] != 0) {
                            $con_conteo++;
                        } else {
                            $sin_conteo++;
                        }
                    }

                    $avance = $total_items > 0 ? round($con_conteo * 100 / $total_items, 2) : 0;
                    ?>

                    <!-- Tarjeta principal con info de la toma -->
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <div>
                                <strong>Toma #<?= (int)$toma_actual['id']; ?></strong>
                                <div class="small text-muted">
                                    <?= htmlspecialchars($toma_actual['nombre_toma']); ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <?php
                                $badge = $toma_actual['estado'] === 'abierta' ? 'success' : 'dark';
                                ?>
                                <div class="mb-2">
                                    <span class="badge bg-<?= $badge; ?>">
                                        <?= htmlspecialchars($toma_actual['estado']); ?>
                                    </span>
                                </div>

                                <?php if ($toma_actual['estado'] === 'abierta' && in_array($rol,['super_admin','admin','supervisor'])): ?>
                                    <form method="post" class="d-inline">
                                        <input type="hidden" name="action" value="cerrar_toma">
                                        <input type="hidden" name="toma_id" value="<?= (int)$toma_actual['id']; ?>">
                                        <button class="btn btn-danger btn-sm"
                                                onclick="return confirm('¿Cerrar esta toma? Ya no se podrán editar ítems.');">
                                            <i class="fas fa-lock"></i> Cerrar toma
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" class="d-inline">
                                    <input type="hidden" name="action" value="exportar_toma">
                                    <input type="hidden" name="toma_id" value="<?= (int)$toma_actual['id']; ?>">
                                    <button class="btn btn-outline-success btn-sm">
                                        <i class="fas fa-file-excel"></i> Exportar XLS
                                    </button>
                                </form>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <strong>Ubicación:</strong><br>
                                    <?= htmlspecialchars($toma_actual['ubicacion']); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Responsable:</strong><br>
                                    <?= htmlspecialchars($toma_actual['responsable_nombre'] ?? ''); ?>
                                </div>
                                <div class="col-md-4">
                                    <strong>Fecha programada:</strong><br>
                                    <?= htmlspecialchars($toma_actual['fecha_programada'] ?? ''); ?>
                                </div>
                            </div>
                            <?php if (!empty($toma_actual['comentarios'])): ?>
                                <div class="mt-2">
                                    <strong>Comentarios:</strong><br>
                                    <?= nl2br(htmlspecialchars($toma_actual['comentarios'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Tabs internos: Resumen / Ítems / Diferencias -->
                    <ul class="nav nav-tabs mb-3">
                        <li class="nav-item">
                            <a class="nav-link <?= $tab === 'resumen' ? 'active' : ''; ?>"
                               href="inventario-fisico.php?view=detalle&toma_id=<?= (int)$toma_actual['id']; ?>&tab=resumen">
                                Resumen
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $tab === 'items' ? 'active' : ''; ?>"
                               href="inventario-fisico.php?view=detalle&toma_id=<?= (int)$toma_actual['id']; ?>&tab=items">
                                Ítems
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= $tab === 'diferencias' ? 'active' : ''; ?>"
                               href="inventario-fisico.php?view=detalle&toma_id=<?= (int)$toma_actual['id']; ?>&tab=diferencias">
                                Diferencias / Avance
                            </a>
                        </li>
                    </ul>

                    <?php if ($tab === 'resumen'): ?>

                        <!-- RESUMEN GENERAL -->
                        <div class="card">
                            <div class="card-header">
                                <strong>Resumen de la toma</strong>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Ítems totales</div>
                                            <div class="fs-4 fw-bold"><?= (int)$total_items; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Ítems con conteo</div>
                                            <div class="fs-4 fw-bold text-success"><?= (int)$con_conteo; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Ítems sin conteo</div>
                                            <div class="fs-4 fw-bold text-danger"><?= (int)$sin_conteo; ?></div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="border rounded p-2 h-100">
                                            <div class="small text-muted">Avance de conteo</div>
                                            <div class="fs-4 fw-bold"><?= $avance; ?>%</div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($total_items > 0): ?>
                                    <div class="mt-3">
                                        <div class="small text-muted mb-1">Barra de avance</div>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: <?= $avance; ?>%;"
                                                 aria-valuenow="<?= $avance; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-3 text-muted small">
                                    * Las diferencias contra el sistema (faltantes/sobrantes reales) requieren integrar stock teórico
                                    en otra tabla (Kardex o stock). De momento se muestra el avance de conteo sobre los ítems listados.
                                </div>
                            </div>
                        </div>

                    <?php elseif ($tab === 'items'): ?>

                        <!-- FORMULARIO AGREGAR ÍTEM + LISTA DE ÍTEMS -->
                        <?php if ($toma_actual['estado'] === 'abierta'): ?>
                            <div class="card mb-3">
                                <div class="card-header">
                                    <strong>Agregar ítem a la toma</strong>
                                </div>
                                <div class="card-body">
                                    <form method="post" class="row g-2">
                                        <input type="hidden" name="action" value="agregar_item">
                                        <input type="hidden" name="toma_id" value="<?= (int)$toma_actual['id']; ?>">

                                        <div class="col-md-2">
                                            <label class="form-label">Código</label>
                                            <input type="text" name="codigo_item" class="form-control form-control-sm"
                                                   placeholder="Código / etiqueta / serie">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Descripción *</label>
                                            <input type="text" name="descripcion_item" class="form-control form-control-sm"
                                                   placeholder="Descripción del equipo / ítem" required>
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Ubicación física</label>
                                            <input type="text" name="ubicacion_fisica" class="form-control form-control-sm"
                                                   placeholder="Estante, rack, zona">
                                        </div>

                                        <div class="col-md-2">
                                            <label class="form-label">Conteo físico</label>
                                            <input type="number" step="0.01" min="0"
                                                   name="conteo_fisico"
                                                   class="form-control form-control-sm">
                                        </div>

                                        <div class="col-md-12">
                                            <label class="form-label">Observaciones</label>
                                            <input type="text" name="observaciones"
                                                   class="form-control form-control-sm"
                                                   placeholder="Daños, faltantes, estado del equipo, etc.">
                                        </div>

                                        <div class="col-12 text-end mt-2">
                                            <button class="btn btn-primary btn-sm">
                                                Agregar ítem
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="card">
                            <div class="card-header">
                                <strong>Ítems registrados</strong>
                            </div>
                            <div class="card-body table-responsive">
                                <?php if (!$detalles): ?>
                                    <p class="text-muted mb-0">Aún no se registran ítems en esta toma.</p>
                                <?php else: ?>
                                    <table class="table table-sm table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Código</th>
                                                <th>Descripción</th>
                                                <th>Ubicación</th>
                                                <th>Conteo</th>
                                                <th>Observaciones</th>
                                                <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                                    <th style="width: 170px;">Acciones</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($detalles as $d): ?>
                                            <tr>
                                                <td><?= (int)$d['id']; ?></td>
                                                <td><?= htmlspecialchars($d['codigo_item'] ?? ''); ?></td>
                                                <td><?= htmlspecialchars($d['descripcion_item'] ?? ''); ?></td>
                                                <td><?= htmlspecialchars($d['ubicacion_fisica'] ?? ''); ?></td>
                                                <td><?= $d['conteo_fisico'] !== null ? htmlspecialchars($d['conteo_fisico']) : ''; ?></td>
                                                <td class="small"><?= htmlspecialchars($d['observaciones'] ?? ''); ?></td>

                                                <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                                    <td>
                                                        <button type="button"
                                                                class="btn btn-sm btn-outline-secondary mb-1"
                                                                onclick="mostrarEditarItem(<?= (int)$d['id']; ?>)">
                                                            <i class="fas fa-edit"></i> Editar
                                                        </button>

                                                        <form method="post" class="d-inline">
                                                            <input type="hidden" name="action" value="eliminar_item">
                                                            <input type="hidden" name="toma_id" value="<?= (int)$toma_actual['id']; ?>">
                                                            <input type="hidden" name="detalle_id" value="<?= (int)$d['id']; ?>">
                                                            <button class="btn btn-sm btn-outline-danger mb-1"
                                                                    onclick="return confirm('¿Eliminar este ítem?');">
                                                                <i class="fas fa-trash-alt"></i> Eliminar
                                                            </button>
                                                        </form>
                                                    </td>
                                                <?php endif; ?>
                                            </tr>

                                            <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                                <tr id="fila-editar-<?= (int)$d['id']; ?>" style="display:none;">
                                                    <td colspan="7">
                                                        <form method="post" class="row g-2 bg-light border rounded p-2 mt-1">
                                                            <input type="hidden" name="action" value="actualizar_item">
                                                            <input type="hidden" name="toma_id" value="<?= (int)$toma_actual['id']; ?>">
                                                            <input type="hidden" name="detalle_id" value="<?= (int)$d['id']; ?>">

                                                            <div class="col-md-2">
                                                                <label class="form-label small mb-1">Código</label>
                                                                <input type="text" name="codigo_item"
                                                                       class="form-control form-control-sm"
                                                                       value="<?= htmlspecialchars($d['codigo_item'] ?? ''); ?>">
                                                            </div>

                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-1">Descripción</label>
                                                                <input type="text" name="descripcion_item"
                                                                       class="form-control form-control-sm"
                                                                       value="<?= htmlspecialchars($d['descripcion_item'] ?? ''); ?>">
                                                            </div>

                                                            <div class="col-md-2">
                                                                <label class="form-label small mb-1">Ubicación</label>
                                                                <input type="text" name="ubicacion_fisica"
                                                                       class="form-control form-control-sm"
                                                                       value="<?= htmlspecialchars($d['ubicacion_fisica'] ?? ''); ?>">
                                                            </div>

                                                            <div class="col-md-2">
                                                                <label class="form-label small mb-1">Conteo</label>
                                                                <input type="number" step="0.01" min="0"
                                                                       name="conteo_fisico"
                                                                       class="form-control form-control-sm"
                                                                       value="<?= $d['conteo_fisico'] !== null ? htmlspecialchars($d['conteo_fisico']) : ''; ?>">
                                                            </div>

                                                            <div class="col-md-12">
                                                                <label class="form-label small mb-1">Observaciones</label>
                                                                <input type="text" name="observaciones"
                                                                       class="form-control form-control-sm"
                                                                       value="<?= htmlspecialchars($d['observaciones'] ?? ''); ?>">
                                                            </div>

                                                            <div class="col-12 text-end mt-2">
                                                                <button type="button"
                                                                        class="btn btn-outline-secondary btn-sm"
                                                                        onclick="ocultarEditarItem(<?= (int)$d['id']; ?>)">
                                                                    Cancelar
                                                                </button>
                                                                <button class="btn btn-primary btn-sm">
                                                                    Guardar
                                                                </button>
                                                            </div>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>

                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php elseif ($tab === 'diferencias'): ?>

                        <!-- PESTAÑA DE DIFERENCIAS / AVANCE -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <strong>Diferencias / Avance de la toma</strong>
                            </div>
                            <div class="card-body">
                                <?php if ($total_items === 0): ?>
                                    <p class="text-muted mb-0">Aún no se han registrado ítems en esta toma.</p>
                                <?php else: ?>
                                    <div class="row g-3 mb-3">
                                        <div class="col-md-3">
                                            <div class="border rounded p-2 h-100">
                                                <div class="small text-muted">Ítems totales</div>
                                                <div class="fs-4 fw-bold"><?= (int)$total_items; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-2 h-100">
                                                <div class="small text-muted">Con conteo físico</div>
                                                <div class="fs-4 fw-bold text-success"><?= (int)$con_conteo; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-2 h-100">
                                                <div class="small text-muted">Sin conteo / 0</div>
                                                <div class="fs-4 fw-bold text-danger"><?= (int)$sin_conteo; ?></div>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="border rounded p-2 h-100">
                                                <div class="small text-muted">Avance de conteo</div>
                                                <div class="fs-4 fw-bold"><?= $avance; ?>%</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="small text-muted mb-1">Barra de avance</div>
                                        <div class="progress" style="height: 10px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: <?= $avance; ?>%;"
                                                 aria-valuenow="<?= $avance; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                        </div>
                                    </div>

                                    <h6 class="mt-3 mb-2">Ítems sin conteo o con conteo 0</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm table-hover align-middle">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Código</th>
                                                    <th>Descripción</th>
                                                    <th>Ubicación</th>
                                                    <th>Conteo físico</th>
                                                    <th>Observaciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach ($detalles as $d): ?>
                                                <?php
                                                $cf = $d['conteo_fisico'];
                                                if ($cf !== null && $cf !== '' && $cf != 0) {
                                                    continue;
                                                }
                                                ?>
                                                <tr>
                                                    <td><?= (int)$d['id']; ?></td>
                                                    <td><?= htmlspecialchars($d['codigo_item'] ?? ''); ?></td>
                                                    <td><?= htmlspecialchars($d['descripcion_item'] ?? ''); ?></td>
                                                    <td><?= htmlspecialchars($d['ubicacion_fisica'] ?? ''); ?></td>
                                                    <td><?= $cf !== null ? htmlspecialchars($cf) : ''; ?></td>
                                                    <td class="small"><?= htmlspecialchars($d['observaciones'] ?? ''); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-3 text-muted small">
                                        Nota: Esta vista muestra diferencias en términos de avance de conteo
                                        (ítems sin conteo, en cero, etc.). Para manejar faltantes/sobrantes reales
                                        contra stock de sistema se requiere enlazar este módulo con una tabla de stock
                                        teórico o Kardex.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    <?php endif; ?>

                <?php else: ?>

                    <div class="alert alert-warning">
                        Vista no válida. Usa el menú para navegar.
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
function mostrarEditarItem(id) {
    var fila = document.getElementById('fila-editar-' + id);
    if (fila) fila.style.display = '';
}
function ocultarEditarItem(id) {
    var fila = document.getElementById('fila-editar-' + id);
    if (fila) fila.style.display = 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
