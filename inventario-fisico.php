<?php
// inventario-fisico.php - VERSIÓN FINAL: SQL CORREGIDO + ESTILOS MODERNOS
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

if (!$user_id) {
    header('Location: login.php');
    exit;
}

// Vista actual
$view = $_GET['view'] ?? 'listado';
$toma_id = intval($_GET['toma_id'] ?? 0);
$tab = $_GET['tab'] ?? 'items'; // Por defecto mostrar ítems

// Flash messages
if (!isset($_SESSION['flash'])) {
    $_SESSION['flash'] = ['success' => [], 'error' => []];
}

function flash_add($type, $msg) {
    $_SESSION['flash'][$type][] = $msg;
}

// PROCESAMIENTO POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        // CREAR TOMA
        if ($action === 'crear_toma') {
            $nombre_toma = trim($_POST['nombre_toma'] ?? '');
            $fecha_programada = trim($_POST['fecha_programada'] ?? '');
            $ubicacion = trim($_POST['ubicacion'] ?? '');
            $comentarios = trim($_POST['comentarios'] ?? '');

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

        // CERRAR TOMA
        if ($action === 'cerrar_toma') {
            $id = intval($_POST['toma_id'] ?? 0);
            $db->execute("UPDATE inventario_tomas SET estado = 'cerrada', fecha_cierre = NOW(), usuario_cierre = ? WHERE id = ? AND cliente_id = ?", [$user_id, $id, $cliente_id]);
            flash_add('success', 'Toma de inventario cerrada correctamente.');
            header("Location: inventario-fisico.php?view=listado");
            exit;
        }

        // AGREGAR ITEM
        if ($action === 'agregar_item') {
            $id_toma = intval($_POST['toma_id'] ?? 0);
            $codigo = trim($_POST['codigo_item'] ?? '');
            $desc = trim($_POST['descripcion_item'] ?? '');
            $ubicacion = trim($_POST['ubicacion_fisica'] ?? '');

            if ($codigo === '' || $desc === '') {
                flash_add('error', 'Código y descripción son obligatorios.');
                header("Location: inventario-fisico.php?view=detalle&toma_id={$id_toma}&tab=items");
                exit;
            }

            $db->execute("
                INSERT INTO inventario_detalles
                (toma_id, codigo_item, descripcion_item, ubicacion_fisica, usuario_registro, fecha_registro)
                VALUES (?, ?, ?, ?, ?, NOW())
            ", [$id_toma, $codigo, $desc, $ubicacion, $user_id]);

            flash_add('success', 'Ítem agregado correctamente.');
            header("Location: inventario-fisico.php?view=detalle&toma_id={$id_toma}&tab=items");
            exit;
        }

        // ACTUALIZAR CONTEO
        if ($action === 'actualizar_conteo') {
            $detalle_id = intval($_POST['detalle_id'] ?? 0);
            $conteo = floatval($_POST['conteo_fisico'] ?? 0);
            $observ = trim($_POST['observaciones'] ?? '');

            $db->execute("
                UPDATE inventario_detalles
                SET conteo_fisico = ?, observaciones = ?, fecha_actualizacion = NOW(), usuario_actualizacion = ?
                WHERE id = ?
            ", [$conteo, $observ, $user_id, $detalle_id]);

            $d = $db->fetchOne("SELECT toma_id FROM inventario_detalles WHERE id = ?", [$detalle_id]);
            flash_add('success', 'Conteo actualizado correctamente.');
            header("Location: inventario-fisico.php?view=detalle&toma_id={$d['toma_id']}&tab=items");
            exit;
        }

        // EXPORTAR
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
        flash_add('error', 'Ocurrió un error: ' . $e->getMessage());
        header("Location: inventario-fisico.php?view={$view}");
        exit;
    }
}

// OBTENER DATOS - CONSULTA CORREGIDA
$tomas = [];
$toma_actual = null;
$detalles = [];

if ($view === 'listado' || $view === 'mis_tomas' || $view === 'historial') {
    
    $sql = "
        SELECT t.*, u.nombre AS responsable_nombre
        FROM inventario_tomas t
        LEFT JOIN usuarios u ON u.id = t.responsable_id
        WHERE t.cliente_id = ?
    ";
    $params = [$cliente_id];

    if (!in_array($rol, ['super_admin', 'admin', 'supervisor']) || $view === 'mis_tomas') {
        $sql .= " AND t.responsable_id = ? ";
        $params[] = $user_id;
    }

    if ($view === 'historial') {
        $sql .= " AND t.estado = 'cerrada' ";
    }

    $sql .= " ORDER BY t.fecha_creacion DESC";
    $tomas = $db->fetchAll($sql, $params);
}

if ($view === 'detalle' && $toma_id > 0) {
    $toma_actual = $db->fetchOne("
        SELECT t.*, u.nombre AS responsable_nombre
        FROM inventario_tomas t
        LEFT JOIN usuarios u ON u.id = t.responsable_id
        WHERE t.id = ? AND t.cliente_id = ?
    ", [$toma_id, $cliente_id]);

    if ($toma_actual) {
        $detalles = $db->fetchAll("SELECT * FROM inventario_detalles WHERE toma_id = ? ORDER BY id", [$toma_id]);
    }
}

// HEADER
$page_title = 'Toma de Inventario Físico';
$breadcrumbs = [
    'dashboard-logistico.php' => 'Dashboard Logístico',
    'inventario-fisico.php' => 'Toma de Inventario Físico'
];

require_once __DIR__ . '/includes/header-logistica.php';
?>

<!-- CSS MODERNO -->
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

/* Header mejorado con gradiente */
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

/* Tabs modernos estilo píldoras */
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
    text-decoration: none;
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

/* Cards modernos */
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

/* Badges modernos */
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

/* Botones modernos */
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
    color: white;
}

.btn-modern-success {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
}

.btn-modern-success:hover {
    transform: translateY(-2px);
    color: white;
}

.btn-modern-danger {
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white;
}

.btn-modern-danger:hover {
    transform: translateY(-2px);
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

/* Info boxes */
.info-box-modern {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8fafc;
    border-radius: 12px;
    margin-bottom: 1rem;
}

.info-box-modern .icon {
    width: 50px;
    height: 50px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #667eea;
    font-size: 1.25rem;
}

.info-box-modern .content .label {
    font-size: 0.75rem;
    color: #64748b;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.05em;
}

.info-box-modern .content .value {
    font-size: 1.1rem;
    color: #1e293b;
    font-weight: 600;
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
    
    .kpi-card-modern .kpi-value {
        font-size: 2rem;
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
        <div class="nav-tabs-modern">
            <a href="inventario-fisico.php?view=listado"
               class="nav-link <?= $view === 'listado' ? 'active' : '' ?>">
                <i class="fas fa-list me-2"></i> Tomas
            </a>

            <?php if (in_array($rol, ['super_admin','admin','supervisor'])): ?>
            <a href="inventario-fisico.php?view=nueva"
               class="nav-link <?= $view === 'nueva' ? 'active' : '' ?>">
                <i class="fas fa-plus-circle me-2"></i> Nueva Toma
            </a>
            <?php endif; ?>

            <a href="inventario-fisico.php?view=mis_tomas"
               class="nav-link <?= $view === 'mis_tomas' ? 'active' : '' ?>">
                <i class="fas fa-user-check me-2"></i> Mis Tomas
            </a>

            <a href="inventario-fisico.php?view=historial"
               class="nav-link <?= $view === 'historial' ? 'active' : '' ?>">
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

        <?php if ($view === 'listado' || $view === 'mis_tomas'): ?>

            <div class="card-modern">
                <div class="card-header">
                    <strong>
                        <i class="fas fa-list"></i> 
                        <?= $view === 'mis_tomas' ? 'Mis Tomas Asignadas' : 'Tomas Registradas' ?>
                    </strong>
                </div>
                <div class="card-body">
                    <?php if (empty($tomas)): ?>
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
                                            <td><?= htmlspecialchars($t['responsable_nombre'] ?? 'Sin asignar') ?></td>
                                            <td><?= htmlspecialchars($t['fecha_programada']) ?></td>
                                            <td>
                                                <span class="badge-modern <?= $t['estado'] === 'abierta' ? 'success' : 'secondary' ?>">
                                                    <?= $t['estado'] ?>
                                                </span>
                                            </td>
                                            <td><?= $items['c'] ?></td>
                                            <td style="text-align: right;">
                                                <a href="inventario-fisico.php?view=detalle&toma_id=<?= $t['id'] ?>&tab=items"
                                                   class="btn btn-sm btn-modern btn-modern-primary">
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

        <?php if ($view === 'nueva' && in_array($rol, ['super_admin','admin','supervisor'])): ?>

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

        <?php if ($view === 'detalle' && $toma_actual): ?>

            <?php
            $total_items = count($detalles);
            $con_conteo = 0;
            $sin_conteo = 0;

            foreach ($detalles as $d) {
                if ($d['conteo_fisico'] > 0) {
                    $con_conteo++;
                } else {
                    $sin_conteo++;
                }
            }

            $avance = $total_items > 0 ? round($con_conteo * 100 / $total_items, 2) : 0;
            ?>

            <!-- Header Compacto de la Toma -->
            <div class="card-modern mb-3">
                <div class="card-body" style="padding: 1.5rem;">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                        
                        <!-- Título y Badge -->
                        <div>
                            <h4 class="mb-1">
                                <i class="fas fa-folder-open text-primary"></i> 
                                <strong>Toma #<?= $toma_actual['id'] ?>:</strong> <?= htmlspecialchars($toma_actual['nombre_toma']) ?>
                            </h4>
                            <div class="d-flex gap-3 align-items-center flex-wrap mt-2">
                                <span class="badge-modern <?= $toma_actual['estado'] === 'abierta' ? 'success' : 'secondary' ?>">
                                    <?= strtoupper($toma_actual['estado']) ?>
                                </span>
                                <span class="text-muted">
                                    <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($toma_actual['ubicacion']) ?>
                                </span>
                                <span class="text-muted">
                                    <i class="fas fa-user"></i> <?= htmlspecialchars($toma_actual['responsable_nombre'] ?? 'Sin asignar') ?>
                                </span>
                                <span class="text-muted">
                                    <i class="fas fa-calendar"></i> <?= htmlspecialchars($toma_actual['fecha_programada']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- Acciones Rápidas -->
                        <div class="d-flex gap-2 flex-wrap">
                            <a href="inventario-fisico.php?view=listado" class="btn btn-sm btn-modern btn-modern-outline">
                                <i class="fas fa-arrow-left me-1"></i> Volver
                            </a>
                            
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="exportar_toma">
                                <input type="hidden" name="toma_id" value="<?= $toma_actual['id'] ?>">
                                <button class="btn btn-sm btn-modern btn-modern-success">
                                    <i class="fas fa-file-excel me-1"></i> Exportar
                                </button>
                            </form>

                            <?php if ($toma_actual['estado'] === 'abierta' && in_array($rol, ['super_admin','admin','supervisor'])): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="action" value="cerrar_toma">
                                <input type="hidden" name="toma_id" value="<?= $toma_actual['id'] ?>">
                                <button class="btn btn-sm btn-modern btn-modern-danger"
                                        onclick="return confirm('¿Cerrar esta toma? No podrá modificarse luego.')">
                                    <i class="fas fa-lock me-1"></i> Cerrar Toma
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($toma_actual['comentarios'])): ?>
                    <div class="mt-3 p-2 bg-light rounded" style="border-left: 4px solid #667eea;">
                        <small class="text-muted"><i class="fas fa-comment-dots me-1"></i>Comentarios:</small>
                        <div><?= nl2br(htmlspecialchars($toma_actual['comentarios'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- KPIs Compactos en Línea -->
            <div class="card-modern mb-3">
                <div class="card-body" style="padding: 1rem;">
                    <div class="row g-3 text-center">
                        <div class="col-6 col-md-3">
                            <div style="padding: 0.5rem;">
                                <div style="font-size: 2rem; font-weight: 700; color: #1e293b;"><?= $total_items ?></div>
                                <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Total ítems</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="padding: 0.5rem;">
                                <div style="font-size: 2rem; font-weight: 700; color: #10b981;"><?= $con_conteo ?></div>
                                <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Contados</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="padding: 0.5rem;">
                                <div style="font-size: 2rem; font-weight: 700; color: #ef4444;"><?= $sin_conteo ?></div>
                                <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Pendientes</div>
                            </div>
                        </div>
                        <div class="col-6 col-md-3">
                            <div style="padding: 0.5rem;">
                                <div style="font-size: 2rem; font-weight: 700; color: #667eea;"><?= $avance ?>%</div>
                                <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600;">Avance</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabs arriba del contenido -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="nav-tabs-modern" style="margin-bottom: 0;">
                    <a class="nav-link <?= $tab === 'items' ? 'active' : '' ?>"
                       href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=items">
                       <i class="fas fa-th-list me-2"></i> Ítems (<?= $total_items ?>)
                    </a>
                    <a class="nav-link <?= $tab === 'resumen' ? 'active' : '' ?>"
                       href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=resumen">
                       <i class="fas fa-chart-pie me-2"></i> Resumen
                    </a>
                    <a class="nav-link <?= $tab === 'diferencias' ? 'active' : '' ?>"
                       href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=diferencias">
                       <i class="fas fa-exclamation-triangle me-2"></i> Diferencias
                    </a>
                </div>

                <!-- Botón agregar solo si estamos en ítems y está abierta -->
                <?php if ($tab === 'items' && $toma_actual['estado'] === 'abierta'): ?>
                <button class="btn btn-modern btn-modern-success" data-bs-toggle="modal" data-bs-target="#modalAgregarItem">
                    <i class="fas fa-plus me-1"></i> Agregar Ítem
                </button>
                <?php endif; ?>
            </div>

            <?php if ($tab === 'resumen'): ?>
            <div class="card-modern">
                <div class="card-body" style="padding: 1.5rem;">
                    
                    <?php if ($sin_conteo == 0): ?>
                        <div class="alert-modern success text-center" style="padding: 2rem;">
                            <i class="fas fa-check-circle fa-3x mb-3"></i>
                            <h4 class="mb-2">¡Conteo Completado!</h4>
                            <p class="mb-0">Todos los ítems han sido contados exitosamente</p>
                        </div>
                    <?php else: ?>
                        <div class="alert-modern" style="background: linear-gradient(135deg, #fff3cd, #ffeaa7); color: #856404; margin-bottom: 1.5rem;">
                            <div class="d-flex align-items-center gap-3">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                                <div>
                                    <strong style="font-size: 1.1rem;">Atención</strong>
                                    <p class="mb-0">Hay <strong><?= $sin_conteo ?></strong> ítem(s) pendiente(s) de conteo</p>
                                </div>
                            </div>
                        </div>

                        <h6 class="mb-3" style="color: #1e293b; font-weight: 600;">
                            <i class="fas fa-clipboard-list me-2"></i>Ítems Pendientes de Conteo
                        </h6>
                        
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th width="60">#</th>
                                        <th width="120">Código</th>
                                        <th>Descripción</th>
                                        <th width="150">Ubicación</th>
                                        <th width="120" class="text-center">Estado</th>
                                        <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                        <th width="100" class="text-center">Acción</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalles as $d): ?>
                                        <?php if ($d['conteo_fisico'] == 0 || $d['conteo_fisico'] === null): ?>
                                        <tr>
                                            <td><strong><?= $d['id'] ?></strong></td>
                                            <td><code style="font-size: 0.9rem; color: #667eea;"><?= htmlspecialchars($d['codigo_item']) ?></code></td>
                                            <td><?= htmlspecialchars($d['descripcion_item']) ?></td>
                                            <td>
                                                <?php if ($d['ubicacion_fisica']): ?>
                                                    <i class="fas fa-map-marker-alt text-muted"></i>
                                                    <?= htmlspecialchars($d['ubicacion_fisica']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge-modern warning">
                                                    <i class="fas fa-clock me-1"></i>Pendiente
                                                </span>
                                            </td>
                                            <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                            <td class="text-center">
                                                <a href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=items" 
                                                   class="btn btn-sm btn-modern btn-modern-primary">
                                                    <i class="fas fa-calculator"></i> Contar
                                                </a>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endif; ?>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <!-- Progreso Visual -->
                    <div class="mt-4 p-3" style="background: #f8fafc; border-radius: 12px;">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span style="font-weight: 600; color: #475569;">Progreso del Conteo</span>
                            <span style="font-weight: 700; color: #667eea; font-size: 1.2rem;"><?= $avance ?>%</span>
                        </div>
                        <div style="background: #e2e8f0; height: 20px; border-radius: 10px; overflow: hidden;">
                            <div style="background: linear-gradient(90deg, #667eea, #764ba2); width: <?= $avance ?>%; height: 100%; transition: width 0.3s ease;"></div>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="text-muted"><?= $con_conteo ?> de <?= $total_items ?> ítems contados</small>
                            <?php if ($sin_conteo > 0): ?>
                            <small class="text-danger"><i class="fas fa-exclamation-circle"></i> Faltan <?= $sin_conteo ?></small>
                            <?php else: ?>
                            <small class="text-success"><i class="fas fa-check-circle"></i> Completado</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($tab === 'items'): ?>
            <div class="card-modern">
                <div class="card-body" style="padding: 1.5rem;">
                    <?php if (empty($detalles)): ?>
                        <div class="empty-state-modern" style="padding: 3rem 2rem;">
                            <i class="fas fa-box-open"></i>
                            <h5>No hay ítems registrados</h5>
                            <p>Agrega ítems para comenzar el conteo de inventario</p>
                            <?php if ($toma_actual['estado'] === 'abierta'): ?>
                            <button class="btn btn-modern btn-modern-primary mt-3" data-bs-toggle="modal" data-bs-target="#modalAgregarItem">
                                <i class="fas fa-plus me-2"></i> Agregar Primer Ítem
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-modern">
                                <thead>
                                    <tr>
                                        <th width="60">#</th>
                                        <th width="120">Código</th>
                                        <th>Descripción</th>
                                        <th width="150">Ubicación</th>
                                        <th width="100" class="text-center">Conteo</th>
                                        <th width="200">Observaciones</th>
                                        <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                        <th width="80" class="text-center">Acción</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalles as $d): ?>
                                    <tr>
                                        <td><strong><?= $d['id'] ?></strong></td>
                                        <td><code style="font-size: 0.9rem; color: #667eea;"><?= htmlspecialchars($d['codigo_item']) ?></code></td>
                                        <td><?= htmlspecialchars($d['descripcion_item']) ?></td>
                                        <td>
                                            <?php if ($d['ubicacion_fisica']): ?>
                                                <i class="fas fa-map-marker-alt text-muted"></i>
                                                <?= htmlspecialchars($d['ubicacion_fisica']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($d['conteo_fisico'] > 0): ?>
                                                <span class="badge-modern success" style="font-size: 1rem;">
                                                    <?= $d['conteo_fisico'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-modern warning">
                                                    Pendiente
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($d['observaciones']): ?>
                                                <small><?= htmlspecialchars($d['observaciones']) ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-modern btn-modern-primary" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#modalEditarItem<?= $d['id'] ?>"
                                                    title="Editar conteo">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Modales para editar cada ítem -->
                        <?php if ($toma_actual['estado'] === 'abierta'): ?>
                            <?php foreach ($detalles as $d): ?>
                            <div class="modal fade" id="modalEditarItem<?= $d['id'] ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <form method="post">
                                            <input type="hidden" name="action" value="actualizar_conteo">
                                            <input type="hidden" name="detalle_id" value="<?= $d['id'] ?>">
                                            <div class="modal-header">
                                                <h5 class="modal-title">
                                                    <i class="fas fa-calculator"></i> Registrar Conteo
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                            </div>
                                            <div class="modal-body">
                                                <div class="alert alert-info">
                                                    <strong>Ítem:</strong> <?= htmlspecialchars($d['codigo_item']) ?> - <?= htmlspecialchars($d['descripcion_item']) ?>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label-modern">Cantidad Contada *</label>
                                                    <input type="number" 
                                                           step="0.01" 
                                                           name="conteo_fisico" 
                                                           class="form-control form-control-modern" 
                                                           value="<?= $d['conteo_fisico'] ?>"
                                                           required 
                                                           autofocus>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label-modern">Observaciones</label>
                                                    <textarea name="observaciones" 
                                                              rows="3" 
                                                              class="form-control form-control-modern"
                                                              placeholder="Ej: Producto dañado, embalaje abierto, etc."><?= htmlspecialchars($d['observaciones']) ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-modern btn-modern-outline" data-bs-dismiss="modal">
                                                    Cancelar
                                                </button>
                                                <button type="submit" class="btn btn-modern btn-modern-primary">
                                                    <i class="fas fa-save me-1"></i> Guardar Conteo
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Modal Agregar Item - Mejorado -->
            <div class="modal fade" id="modalAgregarItem" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border: none; border-radius: 16px; overflow: hidden;">
                        <form method="post">
                            <input type="hidden" name="action" value="agregar_item">
                            <input type="hidden" name="toma_id" value="<?= $toma_actual['id'] ?>">
                            
                            <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem;">
                                <h5 class="modal-title" style="font-weight: 600;">
                                    <i class="fas fa-plus-circle me-2"></i>Agregar Nuevo Ítem
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            
                            <div class="modal-body" style="padding: 2rem;">
                                <div class="mb-4">
                                    <label class="form-label-modern">
                                        <i class="fas fa-barcode me-2"></i>Código del Ítem *
                                    </label>
                                    <input type="text" 
                                           name="codigo_item" 
                                           class="form-control form-control-modern" 
                                           placeholder="Ej: PROD-001"
                                           required 
                                           autofocus>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label-modern">
                                        <i class="fas fa-tag me-2"></i>Descripción del Ítem *
                                    </label>
                                    <input type="text" 
                                           name="descripcion_item" 
                                           class="form-control form-control-modern" 
                                           placeholder="Ej: Laptop HP EliteBook 840"
                                           required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label-modern">
                                        <i class="fas fa-map-marker-alt me-2"></i>Ubicación Física
                                    </label>
                                    <input type="text" 
                                           name="ubicacion_fisica" 
                                           class="form-control form-control-modern"
                                           placeholder="Ej: Estante A3, Pasillo 2">
                                    <small class="text-muted">Opcional - Ayuda a localizar el ítem durante el conteo</small>
                                </div>
                            </div>
                            
                            <div class="modal-footer" style="background: #f8fafc; padding: 1.5rem;">
                                <button type="button" class="btn btn-modern btn-modern-outline" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </button>
                                <button type="submit" class="btn btn-modern btn-modern-success">
                                    <i class="fas fa-check me-1"></i> Agregar Ítem
                                </button>
                            </div>
                        </form>
                    </div>
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

        <?php endif; ?>

        <?php if ($view === 'historial'): ?>
            <div class="card-modern">
                <div class="card-header">
                    <strong><i class="fas fa-archive"></i> Historial de Tomas Cerradas</strong>
                </div>
                <div class="card-body">
                    <?php if (empty($tomas)): ?>
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
                                        <th>Fecha Cierre</th>
                                        <th style="text-align: right;">Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tomas as $t): ?>
                                        <tr>
                                            <td><strong>#<?= $t['id'] ?></strong></td>
                                            <td><?= htmlspecialchars($t['nombre_toma']) ?></td>
                                            <td><?= htmlspecialchars($t['ubicacion']) ?></td>
                                            <td><?= htmlspecialchars($t['responsable_nombre'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($t['fecha_cierre']) ?></td>
                                            <td style="text-align: right;">
                                                <a href="inventario-fisico.php?view=detalle&toma_id=<?= $t['id'] ?>"
                                                   class="btn btn-sm btn-modern btn-modern-outline">
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
