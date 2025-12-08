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

// ------------------------------------------------------
// MODO DEBUG – puedes activarlo cuando algo falle
// ------------------------------------------------------
define('INV_DEBUG', true);

function debug_log($msg) {
    if (INV_DEBUG) {
        file_put_contents(__DIR__ . '/debug_inventario.log', date('Y-m-d H:i:s') . " => " . $msg . "\n", FILE_APPEND);
    }
}

debug_log("=== CARGANDO inventario-fisico.php ===");
debug_log("USER_ID={$user_id} CLIENTE_ID={$cliente_id} ROL={$rol}");

// Si no hay rol en sesión lo obtenemos de la BD
if ($user_id && !$rol) {
    $r = $db->fetchOne("SELECT rol FROM usuarios WHERE id = ?", [$user_id]);
    if ($r && !empty($r['rol'])) {
        $rol = $r['rol'];
        $_SESSION['rol'] = $rol;
    }
}

if (!$user_id) {
    header('Location: login.php');
    exit;
}

// ------------------------------------------------------
// Obtención de vista actual
// ------------------------------------------------------
$view    = $_GET['view']    ?? '';
$toma_id = intval($_GET['toma_id'] ?? 0);
$tab     = $_GET['tab']     ?? 'resumen';

if ($view === '') {
    $view = in_array($rol, ['super_admin', 'admin', 'supervisor'])
        ? 'listado'
        : 'mis_tomas';
}

debug_log("VIEW={$view} TOMA_ID={$toma_id} TAB={$tab}");

// ------------------------------------------------------
// Flash messages
// ------------------------------------------------------
if (!isset($_SESSION['flash'])) {
    $_SESSION['flash'] = ['success' => [], 'error' => []];
}

function flash_add($type, $msg) {
    $_SESSION['flash'][$type][] = $msg;
    debug_log("FLASH {$type}: {$msg}");
}

// ------------------------------------------------------
// PROCESAMIENTO POST
// ------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';
    debug_log("POST ACTION={$action}");

    try {

        // ============================================================
        // CREAR TOMA
        // ============================================================
        if ($action === 'crear_toma') {

            $nombre_toma      = trim($_POST['nombre_toma'] ?? '');
            $fecha_programada = trim($_POST['fecha_programada'] ?? '');
            $ubicacion        = trim($_POST['ubicacion'] ?? '');
            $comentarios      = trim($_POST['comentarios'] ?? '');

            if ($nombre_toma === '' || $ubicacion === '') {
                flash_add('error', 'El nombre y la ubicación son obligatorios.');
                header("Location: inventario-fisico.php?view=nueva");
                exit;
            }

            $db->execute("
                INSERT INTO inventario_tomas
                (cliente_id, nombre_toma, fecha_programada, ubicacion, comentarios,
                 estado, responsable_id, fecha_creacion)
                VALUES (?, ?, ?, ?, ?, 'abierta', ?, NOW())
            ", [$cliente_id, $nombre_toma, $fecha_programada, $ubicacion, $comentarios, $user_id]);

            flash_add('success', 'Toma de inventario creada exitosamente.');
            header("Location: inventario-fisico.php?view=listado");
            exit;
        }

        // ============================================================
        // CERRAR TOMA
        // ============================================================
        if ($action === 'cerrar_toma') {
            $id = intval($_POST['toma_id'] ?? 0);
            $db->execute("UPDATE inventario_tomas SET estado = 'cerrada', fecha_cierre = NOW() WHERE id = ? AND cliente_id = ?", [$id, $cliente_id]);
            flash_add('success', 'Toma de inventario cerrada correctamente.');
            header("Location: inventario-fisico.php?view=listado");
            exit;
        }

        // ============================================================
        // AGREGAR ITEM
        // ============================================================
        if ($action === 'agregar_item') {
            $id_toma    = intval($_POST['toma_id'] ?? 0);
            $codigo     = trim($_POST['codigo_item'] ?? '');
            $desc       = trim($_POST['descripcion_item'] ?? '');
            $ubicacion  = trim($_POST['ubicacion_fisica'] ?? '');

            if ($codigo === '' || $desc === '') {
                flash_add('error', 'Código y descripción son obligatorios.');
                header("Location: inventario-fisico.php?view=detalle&toma_id={$id_toma}&tab=items");
                exit;
            }

            $db->execute("
                INSERT INTO inventario_detalles
                (toma_id, codigo_item, descripcion_item, ubicacion_fisica)
                VALUES (?, ?, ?, ?)
            ", [$id_toma, $codigo, $desc, $ubicacion]);

            flash_add('success', 'Ítem agregado correctamente.');
            header("Location: inventario-fisico.php?view=detalle&toma_id={$id_toma}&tab=items");
            exit;
        }

        // ============================================================
        // ACTUALIZAR CONTEO
        // ============================================================
        if ($action === 'actualizar_conteo') {
            $detalle_id = intval($_POST['detalle_id'] ?? 0);
            $conteo     = floatval($_POST['conteo_fisico'] ?? 0);
            $observ     = trim($_POST['observaciones'] ?? '');

            $db->execute("
                UPDATE inventario_detalles
                SET conteo_fisico = ?, observaciones = ?, fecha_conteo = NOW()
                WHERE id = ?
            ", [$conteo, $observ, $detalle_id]);

            $d = $db->fetchOne("SELECT toma_id FROM inventario_detalles WHERE id = ?", [$detalle_id]);
            flash_add('success', 'Conteo actualizado correctamente.');
            header("Location: inventario-fisico.php?view=detalle&toma_id={$d['toma_id']}&tab=items");
            exit;
        }

        // ============================================================
        // ELIMINAR ITEM
        // ============================================================
        if ($action === 'eliminar_item') {
            $detalle_id = intval($_POST['detalle_id'] ?? 0);
            $d = $db->fetchOne("SELECT toma_id FROM inventario_detalles WHERE id = ?", [$detalle_id]);
            $db->execute("DELETE FROM inventario_detalles WHERE id = ?", [$detalle_id]);
            flash_add('success', 'Ítem eliminado correctamente.');
            header("Location: inventario-fisico.php?view=detalle&toma_id={$d['toma_id']}&tab=items");
            exit;
        }

        // ============================================================
        // EXPORTAR
        // ============================================================
        if ($action === 'exportar_toma') {
            $id = intval($_POST['toma_id'] ?? 0);
            $toma = $db->fetchOne("SELECT * FROM inventario_tomas WHERE id = ? AND cliente_id = ?", [$id, $cliente_id]);
            $detalles = $db->fetchAll("SELECT * FROM inventario_detalles WHERE toma_id = ? ORDER BY id", [$id]);

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="toma_inventario_'.$id.'.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID','Código','Descripción','Ubicación','Conteo','Observaciones']);
            foreach($detalles as $d){
                fputcsv($out, [$d['id'], $d['codigo_item'], $d['descripcion_item'], $d['ubicacion_fisica'], $d['conteo_fisico'], $d['observaciones']]);
            }
            fclose($out);
            exit;
        }

    } catch (Exception $e) {
        debug_log("ERROR: " . $e->getMessage());
        flash_add('error', 'Ocurrió un error: ' . $e->getMessage());
        header("Location: inventario-fisico.php?view={$view}");
        exit;
    }
}

// ------------------------------------------------------
// OBTENER DATOS
// ------------------------------------------------------
$tomas = [];
$toma_actual = null;
$detalles = [];

if ($view === 'listado' || $view === 'mis_tomas') {
    $sql = "SELECT t.*, u.nombre_completo AS responsable_nombre
            FROM inventario_tomas t
            LEFT JOIN usuarios u ON t.responsable_id = u.id
            WHERE t.cliente_id = ?";
    
    if ($view === 'mis_tomas') {
        $sql .= " AND t.responsable_id = ?";
        $tomas = $db->fetchAll($sql, [$cliente_id, $user_id]);
    } else {
        $tomas = $db->fetchAll($sql, [$cliente_id]);
    }
}

if ($view === 'detalle' && $toma_id > 0) {
    $toma_actual = $db->fetchOne("
        SELECT t.*, u.nombre_completo AS responsable_nombre
        FROM inventario_tomas t
        LEFT JOIN usuarios u ON t.responsable_id = u.id
        WHERE t.id = ? AND t.cliente_id = ?
    ", [$toma_id, $cliente_id]);

    if ($toma_actual) {
        $detalles = $db->fetchAll("SELECT * FROM inventario_detalles WHERE toma_id = ? ORDER BY id", [$toma_id]);
    }
}

if ($view === 'historial') {
    $tomas = $db->fetchAll("
        SELECT t.*, u.nombre_completo AS responsable_nombre
        FROM inventario_tomas t
        LEFT JOIN usuarios u ON t.responsable_id = u.id
        WHERE t.cliente_id = ? AND t.estado = 'cerrada'
        ORDER BY t.fecha_cierre DESC
    ", [$cliente_id]);
}

// ------------------------------------------------------
// HTML
// ------------------------------------------------------
require_once __DIR__ . '/includes/header-logistica.php';
?>

<!-- CSS MEJORADO -->
<style>
/* Contenedor principal */
.app-content {
    background: #f5f7fa;
    min-height: 100vh;
    padding: 2rem;
}

.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
}

/* Header mejorado */
.page-header-modern {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 16px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
}

.page-header-modern h3 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
}

.page-header-modern .subtitle {
    opacity: 0.95;
    font-size: 1rem;
}

/* Tabs mejorados */
.nav-tabs-modern {
    background: white;
    padding: 0.5rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
    border: none;
    display: inline-flex;
    gap: 0.5rem;
    margin-bottom: 2rem;
}

.nav-tabs-modern .nav-link {
    border: none;
    border-radius: 8px;
    padding: 0.75rem 1.5rem;
    font-weight: 600;
    color: #64748b;
    transition: all 0.3s ease;
}

.nav-tabs-modern .nav-link:hover {
    background: #f1f5f9;
    color: #475569;
}

.nav-tabs-modern .nav-link.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

/* Cards mejorados */
.card-modern {
    background: white;
    border: none;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    margin-bottom: 2rem;
    overflow: hidden;
}

.card-modern .card-header {
    background: linear-gradient(to right, #f8fafc, #f1f5f9);
    border-bottom: 2px solid #e2e8f0;
    padding: 1.5rem;
}

.card-modern .card-header strong {
    font-size: 1.2rem;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.card-modern .card-header i {
    color: #667eea;
}

.card-modern .card-body {
    padding: 2rem;
}

/* Tabla mejorada */
.table-modern {
    margin: 0;
}

.table-modern thead {
    background: #f8fafc;
}

.table-modern thead th {
    border: none;
    color: #64748b;
    font-weight: 700;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.05em;
    padding: 1rem;
}

.table-modern tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: all 0.2s ease;
}

.table-modern tbody tr:hover {
    background: #fafbfc;
    transform: translateX(4px);
}

.table-modern tbody td {
    padding: 1.25rem 1rem;
    vertical-align: middle;
    color: #334155;
}

/* Badges mejorados */
.badge-modern {
    padding: 0.5rem 1rem;
    border-radius: 50px;
    font-weight: 600;
    font-size: 0.8rem;
    letter-spacing: 0.025em;
}

.badge-modern.success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.badge-modern.warning {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: white;
}

.badge-modern.secondary {
    background: linear-gradient(135deg, #6b7280, #4b5563);
    color: white;
}

/* Botones mejorados */
.btn-modern {
    padding: 0.625rem 1.25rem;
    border-radius: 10px;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
}

.btn-modern-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.btn-modern-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
}

.btn-modern-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-modern-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-modern-outline {
    background: white;
    border: 2px solid #e2e8f0;
    color: #64748b;
}

.btn-modern-outline:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
}

/* Formularios mejorados */
.form-control-modern {
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    padding: 0.75rem 1rem;
    transition: all 0.3s ease;
}

.form-control-modern:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    outline: none;
}

.form-label-modern {
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

/* KPI Cards */
.kpi-card-modern {
    background: white;
    border-radius: 16px;
    padding: 1.5rem;
    text-align: center;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.kpi-card-modern:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    border-color: #667eea;
}

.kpi-card-modern .kpi-label {
    color: #64748b;
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 0.75rem;
}

.kpi-card-modern .kpi-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.kpi-card-modern .kpi-value.success {
    background: linear-gradient(135deg, #10b981, #059669);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.kpi-card-modern .kpi-value.danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

/* Alertas mejoradas */
.alert-modern {
    border: none;
    border-radius: 12px;
    padding: 1rem 1.5rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
}

.alert-modern.success {
    background: linear-gradient(135deg, #d1fae5, #a7f3d0);
    color: #065f46;
}

.alert-modern.danger {
    background: linear-gradient(135deg, #fee2e2, #fecaca);
    color: #991b1b;
}

/* Empty state */
.empty-state-modern {
    text-align: center;
    padding: 4rem 2rem;
    color: #94a3b8;
}

.empty-state-modern i {
    font-size: 4rem;
    margin-bottom: 1.5rem;
    opacity: 0.3;
}

.empty-state-modern h5 {
    color: #64748b;
    margin-bottom: 0.5rem;
}

/* Responsive */
@media (max-width: 768px) {
    .app-content {
        padding: 1rem;
    }
    
    .page-header-modern {
        padding: 1.5rem;
    }
    
    .nav-tabs-modern {
        flex-wrap: wrap;
    }
}
</style>

<div class="app-content">
    <div class="container-fluid">

        <!-- Header Moderno -->
        <div class="page-header-modern">
            <h3>
                <i class="fas fa-clipboard-check"></i> Toma de Inventario Físico
            </h3>
            <div class="subtitle">
                Control y seguimiento avanzado de inventarios físicos por almacén
            </div>
        </div>

        <!-- Navegación Principal -->
        <?php
        $tab_main = 'listado';
        if ($view === 'nueva')      $tab_main = 'nueva';
        if ($view === 'historial')  $tab_main = 'historial';
        if ($view === 'mis_tomas')  $tab_main = 'mis_tomas';
        ?>

        <div class="nav-tabs-modern">
            <a href="inventario-fisico.php?view=listado"
               class="nav-link <?= $tab_main === 'listado' ? 'active' : '' ?>">
                <i class="fas fa-list me-2"></i> Tomas
            </a>

            <?php if (in_array($rol, ['super_admin','admin','supervisor'])): ?>
            <a href="inventario-fisico.php?view=nueva"
               class="nav-link <?= $tab_main === 'nueva' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle me-2"></i> Nueva Toma
            </a>
            <?php endif; ?>

            <a href="inventario-fisico.php?view=mis_tomas"
               class="nav-link <?= $tab_main === 'mis_tomas' ? 'active' : '' ?>">
                <i class="fas fa-user-check me-2"></i> Mis Tomas
            </a>

            <a href="inventario-fisico.php?view=historial"
               class="nav-link <?= $tab_main === 'historial' ? 'active' : '' ?>">
                <i class="fas fa-archive me-2"></i> Historial
            </a>
        </div>

        <!-- Flash Messages -->
        <?php foreach ($_SESSION['flash']['success'] as $m): ?>
            <div class="alert-modern success">
                <i class="fas fa-check-circle me-2"></i>
                <?= htmlspecialchars($m) ?>
            </div>
        <?php endforeach; ?>

        <?php foreach ($_SESSION['flash']['error'] as $m): ?>
            <div class="alert-modern danger">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($m) ?>
            </div>
        <?php endforeach; ?>
        <?php $_SESSION['flash'] = ['success'=>[],'error'=>[]]; ?>

        <?php
        // ============================================================
        // VISTA: LISTADO
        // ============================================================
        if ($view === 'listado'): ?>

            <div class="card-modern">
                <div class="card-header">
                    <strong><i class="fas fa-list"></i> Tomas Registradas</strong>
                </div>
                <div class="card-body">
                    <?php if (!$tomas): ?>
                        <div class="empty-state-modern">
                            <i class="fas fa-clipboard-list"></i>
                            <h5>No hay tomas registradas</h5>
                            <p>Comienza creando una nueva toma de inventario</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Ubicación</th>
                                        <th>Responsable</th>
                                        <th>Fecha prog.</th>
                                        <th>Estado</th>
                                        <th>Ítems</th>
                                        <th style="text-align: right;">Acción</th>
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
                                        ?>
                                        <tr>
                                            <td><strong>#<?= $t['id'] ?></strong></td>
                                            <td><?= htmlspecialchars($t['nombre_toma']) ?></td>
                                            <td><?= htmlspecialchars($t['ubicacion']) ?></td>
                                            <td><?= htmlspecialchars($t['responsable_nombre']) ?></td>
                                            <td><?= htmlspecialchars($t['fecha_programada']) ?></td>
                                            <td>
                                                <span class="badge-modern <?= $t['estado'] === 'abierta' ? 'success' : 'secondary' ?>">
                                                    <?= $t['estado'] ?>
                                                </span>
                                            </td>
                                            <td><?= $items['c'] ?></td>
                                            <td style="text-align: right;">
                                                <a href="inventario-fisico.php?view=detalle&toma_id=<?= $t['id'] ?>&tab=resumen"
                                                   class="btn btn-modern btn-modern-primary btn-sm">
                                                    <i class="fas fa-folder-open me-1"></i> Abrir
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif ?>
                </div>
            </div>

        <?php endif; ?>

        <?php if ($view === 'mis_tomas'): ?>

            <div class="card-modern">
                <div class="card-header">
                    <strong><i class="fas fa-user-check"></i> Mis Tomas Asignadas</strong>
                </div>
                <div class="card-body">
                    <?php if (!$tomas): ?>
                        <div class="empty-state-modern">
                            <i class="fas fa-user-clock"></i>
                            <h5>No tienes tomas asignadas</h5>
                            <p>Cuando te asignen tomas aparecerán aquí</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Ubicación</th>
                                        <th>Fecha prog.</th>
                                        <th>Estado</th>
                                        <th style="text-align: right;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tomas as $t): ?>
                                        <tr>
                                            <td><strong>#<?= $t['id'] ?></strong></td>
                                            <td><?= htmlspecialchars($t['nombre_toma']) ?></td>
                                            <td><?= htmlspecialchars($t['ubicacion']) ?></td>
                                            <td><?= htmlspecialchars($t['fecha_programada']) ?></td>
                                            <td>
                                                <span class="badge-modern <?= $t['estado'] === 'abierta' ? 'success' : 'secondary' ?>">
                                                    <?= $t['estado'] ?>
                                                </span>
                                            </td>
                                            <td style="text-align: right;">
                                                <a href="inventario-fisico.php?view=detalle&toma_id=<?= $t['id'] ?>"
                                                   class="btn btn-modern btn-modern-primary btn-sm">
                                                    <i class="fas fa-eye me-1"></i> Ver
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif ?>
                </div>
            </div>

        <?php endif; ?>

<?php
// ============================================================
// VISTA: NUEVA TOMA
// ============================================================
if ($view === 'nueva' && in_array($rol, ['super_admin','admin','supervisor'])): ?>

    <div class="card-modern">
        <div class="card-header">
            <strong><i class="fas fa-plus-circle"></i> Registrar Nueva Toma</strong>
        </div>
        <div class="card-body">
            <form method="post" class="row g-4">
                <input type="hidden" name="action" value="crear_toma">

                <div class="col-md-6">
                    <label class="form-label-modern">Nombre de la toma *</label>
                    <input type="text" name="nombre_toma" class="form-control form-control-modern" required>
                </div>

                <div class="col-md-3">
                    <label class="form-label-modern">Fecha programada</label>
                    <input type="date" name="fecha_programada" class="form-control form-control-modern">
                </div>

                <div class="col-md-3">
                    <label class="form-label-modern">Ubicación / Almacén *</label>
                    <input type="text" name="ubicacion" class="form-control form-control-modern" required placeholder="Ej: Local, Almacén A">
                </div>

                <div class="col-12">
                    <label class="form-label-modern">Comentarios</label>
                    <textarea name="comentarios" rows="4" class="form-control form-control-modern"
                              placeholder="Notas adicionales, instrucciones o restricciones..."></textarea>
                </div>

                <div class="col-12" style="text-align: right;">
                    <a href="inventario-fisico.php?view=listado"
                       class="btn btn-modern btn-modern-outline">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </a>
                    <button class="btn btn-modern btn-modern-success ms-2">
                        <i class="fas fa-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php endif; ?>


<?php
// ============================================================
// VISTA: DETALLE DE TOMA
// ============================================================
if ($view === 'detalle' && $toma_actual): ?>

    <?php
    // Contar estados
    $total_items = count($detalles);
    $con_conteo  = 0;
    $sin_conteo  = 0;

    foreach ($detalles as $d) {
        if ($d['conteo_fisico'] > 0) {
            $con_conteo++;
        } else {
            $sin_conteo++;
        }
    }

    $avance = $total_items > 0 ? round($con_conteo * 100 / $total_items, 2) : 0;
    ?>

    <div class="card-modern mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <strong><i class="fas fa-folder-open"></i> Toma #<?= $toma_actual['id'] ?></strong>
                <div class="small text-muted mt-1"><?= htmlspecialchars($toma_actual['nombre_toma']) ?></div>
            </div>
            <div class="d-flex gap-2">
                <span class="badge-modern <?= $toma_actual['estado'] === 'abierta' ? 'success' : 'secondary' ?>">
                    <?= $toma_actual['estado'] ?>
                </span>

                <?php if ($toma_actual['estado'] === 'abierta' && in_array($rol, ['super_admin','admin','supervisor'])): ?>
                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="cerrar_toma">
                    <input type="hidden" name="toma_id" value="<?= $toma_actual['id'] ?>">
                    <button class="btn btn-modern btn-modern-danger btn-sm"
                            onclick="return confirm('¿Cerrar esta toma? No podrá modificarse luego.')">
                        <i class="fas fa-lock me-1"></i> Cerrar
                    </button>
                </form>
                <?php endif; ?>

                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="exportar_toma">
                    <input type="hidden" name="toma_id" value="<?= $toma_actual['id'] ?>">
                    <button class="btn btn-modern btn-modern-success btn-sm">
                        <i class="fas fa-file-excel me-1"></i> Exportar
                    </button>
                </form>
            </div>
        </div>

        <div class="card-body">
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-light rounded-circle">
                            <i class="fas fa-map-marker-alt text-primary"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Ubicación</div>
                            <strong><?= htmlspecialchars($toma_actual['ubicacion']) ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-light rounded-circle">
                            <i class="fas fa-user text-primary"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Responsable</div>
                            <strong><?= htmlspecialchars($toma_actual['responsable_nombre']) ?></strong>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex align-items-center gap-3">
                        <div class="p-3 bg-light rounded-circle">
                            <i class="fas fa-calendar text-primary"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Fecha programada</div>
                            <strong><?= htmlspecialchars($toma_actual['fecha_programada']) ?></strong>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($toma_actual['comentarios'])): ?>
            <div class="mt-4 p-3 bg-light rounded">
                <strong class="d-block mb-2"><i class="fas fa-comment-dots me-2"></i>Comentarios:</strong>
                <?= nl2br(htmlspecialchars($toma_actual['comentarios'])) ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- KPIs de la toma -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="kpi-card-modern">
                <div class="kpi-label">Total ítems</div>
                <div class="kpi-value"><?= $total_items ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card-modern">
                <div class="kpi-label">Con conteo</div>
                <div class="kpi-value success"><?= $con_conteo ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card-modern">
                <div class="kpi-label">Sin conteo</div>
                <div class="kpi-value danger"><?= $sin_conteo ?></div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="kpi-card-modern">
                <div class="kpi-label">Avance</div>
                <div class="kpi-value"><?= $avance ?>%</div>
            </div>
        </div>
    </div>

    <!-- Tabs internos -->
    <ul class="nav-tabs-modern mb-4">
        <a class="nav-link <?= $tab === 'resumen' ? 'active' : '' ?>"
           href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=resumen">
           <i class="fas fa-chart-pie me-2"></i> Resumen
        </a>
        <a class="nav-link <?= $tab === 'items' ? 'active' : '' ?>"
           href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=items">
           <i class="fas fa-th-list me-2"></i> Ítems
        </a>
        <a class="nav-link <?= $tab === 'diferencias' ? 'active' : '' ?>"
           href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=diferencias">
           <i class="fas fa-exclamation-triangle me-2"></i> Diferencias
        </a>
    </ul>

    <?php if ($tab === 'resumen'): ?>
    <div class="card-modern">
        <div class="card-header">
            <strong><i class="fas fa-chart-pie"></i> Resumen</strong>
        </div>
        <div class="card-body">
            <h6 class="mb-3">Ítems sin conteo</h6>
            <?php if ($sin_conteo == 0): ?>
                <div class="alert-modern success">
                    <i class="fas fa-check-circle me-2"></i>
                    Todos los ítems han sido contados
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Ubicación</th>
                                <th>Conteo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $d): ?>
                                <?php if ($d['conteo_fisico'] == 0 || $d['conteo_fisico'] === null): ?>
                                <tr>
                                    <td><?= $d['id'] ?></td>
                                    <td><?= htmlspecialchars($d['codigo_item']) ?></td>
                                    <td><?= htmlspecialchars($d['descripcion_item']) ?></td>
                                    <td><?= htmlspecialchars($d['ubicacion_fisica']) ?></td>
                                    <td><span class="badge-modern warning">Pendiente</span></td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'items'): ?>
    <div class="card-modern">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong><i class="fas fa-th-list"></i> Ítems</strong>
            <?php if ($toma_actual['estado'] === 'abierta'): ?>
            <button class="btn btn-modern btn-modern-success btn-sm" onclick="alert('Funcionalidad para agregar ítems')">
                <i class="fas fa-plus me-1"></i> Agregar Ítem
            </button>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (!$detalles): ?>
                <div class="empty-state-modern">
                    <i class="fas fa-box-open"></i>
                    <h5>No hay ítems registrados</h5>
                    <p>Agrega ítems para comenzar el conteo</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Código</th>
                                <th>Descripción</th>
                                <th>Ubicación</th>
                                <th>Conteo</th>
                                <th>Observaciones</th>
                                <th style="text-align: right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $d): ?>
                            <tr>
                                <td><?= $d['id'] ?></td>
                                <td><strong><?= htmlspecialchars($d['codigo_item']) ?></strong></td>
                                <td><?= htmlspecialchars($d['descripcion_item']) ?></td>
                                <td><?= htmlspecialchars($d['ubicacion_fisica']) ?></td>
                                <td>
                                    <?php if ($d['conteo_fisico'] > 0): ?>
                                        <span class="badge-modern success"><?= $d['conteo_fisico'] ?></span>
                                    <?php else: ?>
                                        <span class="badge-modern warning">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($d['observaciones']) ?></td>
                                <td style="text-align: right;">
                                    <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                    <button class="btn btn-modern btn-modern-primary btn-sm" onclick="alert('Funcionalidad para editar')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($tab === 'diferencias'): ?>
    <div class="card-modern">
        <div class="card-header">
            <strong><i class="fas fa-exclamation-triangle"></i> Diferencias del Conteo</strong>
        </div>
        <div class="card-body">
            <div class="alert-modern" style="background: linear-gradient(135deg, #fef3c7, #fde68a); color: #92400e;">
                <i class="fas fa-info-circle me-2"></i>
                Aquí se mostrarían las diferencias entre el inventario teórico y el conteo físico
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php endif; // FIN DETALLE ?>

<?php if ($view === 'historial'): ?>

    <div class="card-modern">
        <div class="card-header">
            <strong><i class="fas fa-archive"></i> Historial de Tomas Cerradas</strong>
        </div>
        <div class="card-body">
            <?php if (!$tomas): ?>
                <div class="empty-state-modern">
                    <i class="fas fa-archive"></i>
                    <h5>No hay tomas cerradas</h5>
                    <p>Las tomas cerradas aparecerán aquí</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-modern">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Ubicación</th>
                                <th>Responsable</th>
                                <th>Fecha cierre</th>
                                <th style="text-align: right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tomas as $t): ?>
                                <tr>
                                    <td><strong>#<?= $t['id'] ?></strong></td>
                                    <td><?= htmlspecialchars($t['nombre_toma']) ?></td>
                                    <td><?= htmlspecialchars($t['ubicacion']) ?></td>
                                    <td><?= htmlspecialchars($t['responsable_nombre']) ?></td>
                                    <td><?= htmlspecialchars($t['fecha_cierre']) ?></td>
                                    <td style="text-align: right;">
                                        <a href="inventario-fisico.php?view=detalle&toma_id=<?= $t['id'] ?>"
                                           class="btn btn-modern btn-modern-outline btn-sm">
                                            <i class="fas fa-eye me-1"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif ?>
        </div>
    </div>

<?php endif; ?>

</div>
</div>

<script>
function mostrarEditarItem(id) {
    document.getElementById('fila-editar-' + id).style.display = '';
}
function ocultarEditarItem(id) {
    document.getElementById('fila-editar-' + id).style.display = 'none';
}
</script>

<!-- Sidebar de Logística -->
<div id="logistica-sidebar-content" style="display: none;">
    <?php require_once __DIR__ . '/includes/sidebar-logistica.php'; ?>
</div>

<script>
// Cargar sidebar de logística
document.addEventListener('DOMContentLoaded', function() {
    const currentSidebar = document.querySelector('.app-sidebar');
    const logisticaSidebarContent = document.getElementById('logistica-sidebar-content');
    
    if (currentSidebar && logisticaSidebarContent) {
        const newSidebar = logisticaSidebarContent.querySelector('.app-sidebar');
        if (newSidebar) {
            currentSidebar.innerHTML = newSidebar.innerHTML;
            logisticaSidebarContent.remove();
            
            document.querySelectorAll('.menu-item.has-submenu .menu-link').forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.getAttribute('href') === '#' || !this.getAttribute('href')) {
                        e.preventDefault();
                        this.parentElement.classList.toggle('open');
                    }
                });
            });
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
