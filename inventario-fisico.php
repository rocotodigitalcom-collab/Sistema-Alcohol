<?php
// inventario-fisico.php - VERSIÓN DEFINITIVA ULTRA SIMPLE
// MISMA FUNCIONALIDAD - DISEÑO INLINE DIRECTO
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

$view = $_GET['view'] ?? 'listado';
$toma_id = intval($_GET['toma_id'] ?? 0);
$tab = $_GET['tab'] ?? 'items';

if (!isset($_SESSION['flash'])) {
    $_SESSION['flash'] = ['success' => [], 'error' => []];
}

function flash_add($type, $msg) {
    $_SESSION['flash'][$type][] = $msg;
}

// [TODO EL CÓDIGO DE PROCESAMIENTO POST SE MANTIENE IGUAL]
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'crear_toma') {
            $nombre = trim($_POST['nombre_toma'] ?? '');
            $fecha = trim($_POST['fecha_programada'] ?? '');
            $ubicacion = trim($_POST['ubicacion'] ?? '');
            $comentarios = trim($_POST['comentarios'] ?? '');
            if (!$nombre || !$ubicacion) {
                flash_add('error', 'Nombre y ubicación son obligatorios.');
                header("Location: inventario-fisico.php?view=nueva");
                exit;
            }
            $db->execute("INSERT INTO inventario_tomas (cliente_id, nombre_toma, fecha_programada, ubicacion, comentarios, estado, responsable_id, fecha_creacion) VALUES (?, ?, ?, ?, ?, 'abierta', ?, NOW())", [$cliente_id, $nombre, $fecha, $ubicacion, $comentarios, $user_id]);
            flash_add('success', 'Toma creada exitosamente.');
            header("Location: inventario-fisico.php");
            exit;
        }
        if ($action === 'cerrar_toma') {
            $id = intval($_POST['toma_id'] ?? 0);
            $db->execute("UPDATE inventario_tomas SET estado = 'cerrada', fecha_cierre = NOW(), usuario_cierre = ? WHERE id = ? AND cliente_id = ?", [$user_id, $id, $cliente_id]);
            flash_add('success', 'Toma cerrada correctamente.');
            header("Location: inventario-fisico.php");
            exit;
        }
        if ($action === 'reabrir_toma') {
            $id = intval($_POST['toma_id'] ?? 0);
            $db->execute("UPDATE inventario_tomas SET estado = 'abierta', fecha_cierre = NULL, usuario_cierre = NULL WHERE id = ? AND cliente_id = ?", [$id, $cliente_id]);
            flash_add('success', 'Toma reabierta correctamente.');
            header("Location: inventario-fisico.php?view=detalle&toma_id={$id}");
            exit;
        }
        if ($action === 'agregar_item') {
            $id_toma = intval($_POST['toma_id'] ?? 0);
            $codigo = trim($_POST['codigo_item'] ?? '');
            $desc = trim($_POST['descripcion_item'] ?? '');
            $ubicacion = trim($_POST['ubicacion_fisica'] ?? '');
            if (!$codigo || !$desc) {
                flash_add('error', 'Código y descripción obligatorios.');
                header("Location: inventario-fisico.php?view=detalle&toma_id={$id_toma}");
                exit;
            }
            $db->execute("INSERT INTO inventario_detalles (toma_id, codigo_item, descripcion_item, ubicacion_fisica, usuario_registro, fecha_registro) VALUES (?, ?, ?, ?, ?, NOW())", [$id_toma, $codigo, $desc, $ubicacion, $user_id]);
            flash_add('success', 'Ítem agregado.');
            header("Location: inventario-fisico.php?view=detalle&toma_id={$id_toma}&tab=items");
            exit;
        }
        if ($action === 'actualizar_conteo') {
            $detalle_id = intval($_POST['detalle_id'] ?? 0);
            $conteo = floatval($_POST['conteo_fisico'] ?? 0);
            $observ = trim($_POST['observaciones'] ?? '');
            $db->execute("UPDATE inventario_detalles SET conteo_fisico = ?, observaciones = ?, fecha_actualizacion = NOW(), usuario_actualizacion = ? WHERE id = ?", [$conteo, $observ, $user_id, $detalle_id]);
            $d = $db->fetchOne("SELECT toma_id FROM inventario_detalles WHERE id = ?", [$detalle_id]);
            flash_add('success', 'Conteo actualizado.');
            header("Location: inventario-fisico.php?view=detalle&toma_id={$d['toma_id']}&tab=items");
            exit;
        }
        if ($action === 'eliminar_item') {
            $detalle_id = intval($_POST['detalle_id'] ?? 0);
            $d = $db->fetchOne("SELECT toma_id FROM inventario_detalles WHERE id = ?", [$detalle_id]);
            if ($d) {
                $db->execute("DELETE FROM inventario_detalles WHERE id = ?", [$detalle_id]);
                flash_add('success', 'Ítem eliminado correctamente.');
                header("Location: inventario-fisico.php?view=detalle&toma_id={$d['toma_id']}&tab=items");
            } else {
                flash_add('error', 'Ítem no encontrado.');
                header("Location: inventario-fisico.php");
            }
            exit;
        }
        if ($action === 'exportar_toma') {
            $id = intval($_POST['toma_id'] ?? 0);
            $toma = $db->fetchOne("SELECT * FROM inventario_tomas WHERE id = ? AND cliente_id = ?", [$id, $cliente_id]);
            $detalles = $db->fetchAll("SELECT * FROM inventario_detalles WHERE toma_id = ? ORDER BY id", [$id]);
            
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="inventario_toma_'.$id.'_'.date('YmdHis').'.csv"');
            
            $out = fopen('php://output', 'w');
            
            // UTF-8 BOM para Excel
            fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Información de la toma
            fputcsv($out, ['TOMA DE INVENTARIO FÍSICO']);
            fputcsv($out, ['Toma ID', $toma['id']]);
            fputcsv($out, ['Nombre', $toma['nombre_toma']]);
            fputcsv($out, ['Ubicación', $toma['ubicacion']]);
            fputcsv($out, ['Fecha Programada', $toma['fecha_programada']]);
            fputcsv($out, ['Estado', $toma['estado']]);
            fputcsv($out, ['Fecha Creación', $toma['fecha_creacion']]);
            if ($toma['fecha_cierre']) {
                fputcsv($out, ['Fecha Cierre', $toma['fecha_cierre']]);
            }
            if ($toma['comentarios']) {
                fputcsv($out, ['Comentarios', $toma['comentarios']]);
            }
            fputcsv($out, []);
            
            // Estadísticas
            $total = count($detalles);
            $contados = 0;
            foreach($detalles as $d) {
                if ($d['conteo_fisico'] > 0) $contados++;
            }
            $pendientes = $total - $contados;
            $avance = $total > 0 ? round($contados * 100 / $total) : 0;
            
            fputcsv($out, ['ESTADÍSTICAS']);
            fputcsv($out, ['Total Ítems', $total]);
            fputcsv($out, ['Ítems Contados', $contados]);
            fputcsv($out, ['Ítems Pendientes', $pendientes]);
            fputcsv($out, ['Avance (%)', $avance]);
            fputcsv($out, []);
            
            // Detalles de ítems
            fputcsv($out, ['DETALLE DE ÍTEMS']);
            fputcsv($out, ['ID','Código','Descripción','Ubicación Física','Conteo Físico','Observaciones','Fecha Registro','Usuario']);
            
            foreach($detalles as $d){
                $usuario_nombre = '';
                if ($d['usuario_registro']) {
                    $u = $db->fetchOne("SELECT nombre FROM usuarios WHERE id = ?", [$d['usuario_registro']]);
                    $usuario_nombre = $u['nombre'] ?? '';
                }
                
                fputcsv($out, [
                    $d['id'], 
                    $d['codigo_item'], 
                    $d['descripcion_item'], 
                    $d['ubicacion_fisica'], 
                    $d['conteo_fisico'], 
                    $d['observaciones'],
                    $d['fecha_registro'],
                    $usuario_nombre
                ]);
            }
            
            fclose($out);
            exit;
        }
    } catch (Exception $e) {
        flash_add('error', 'Error: ' . $e->getMessage());
        header("Location: inventario-fisico.php?view={$view}");
        exit;
    }
}

// [TODO EL CÓDIGO DE OBTENER DATOS SE MANTIENE IGUAL]
$tomas = [];
$toma_actual = null;
$detalles = [];

if ($view === 'listado' || $view === 'mis_tomas' || $view === 'historial') {
    $sql = "SELECT t.*, u.nombre AS responsable_nombre FROM inventario_tomas t LEFT JOIN usuarios u ON u.id = t.responsable_id WHERE t.cliente_id = ?";
    $params = [$cliente_id];
    if (!in_array($rol, ['super_admin', 'admin', 'supervisor']) || $view === 'mis_tomas') {
        $sql .= " AND t.responsable_id = ? ";
        $params[] = $user_id;
    }
    if ($view === 'historial') {
        $sql .= " AND t.estado = 'cerrada' ";
    } else {
        $sql .= " AND t.estado = 'abierta' ";
    }
    $sql .= " ORDER BY t.fecha_creacion DESC";
    $tomas = $db->fetchAll($sql, $params);
}

if ($view === 'detalle' && $toma_id > 0) {
    $toma_actual = $db->fetchOne("SELECT t.*, u.nombre AS responsable_nombre FROM inventario_tomas t LEFT JOIN usuarios u ON u.id = t.responsable_id WHERE t.id = ? AND t.cliente_id = ?", [$toma_id, $cliente_id]);
    if ($toma_actual) {
        $detalles = $db->fetchAll("SELECT * FROM inventario_detalles WHERE toma_id = ? ORDER BY id", [$toma_id]);
    }
}

$page_title = 'Inventario Físico';
$breadcrumbs = [
    'dashboard-logistico.php' => 'Dashboard',
    'inventario-fisico.php' => 'Inventario Físico'
];

require_once __DIR__ . '/includes/header-logistica.php';
?>

<div style="max-width: 1400px; margin: 0 auto; padding: 1.5rem;">

    <!-- Flash Messages -->
    <?php foreach ($_SESSION['flash']['success'] as $m): ?>
        <div style="background: #d1fae5; color: #065f46; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-check-circle"></i>
            <span><?= htmlspecialchars($m) ?></span>
        </div>
    <?php endforeach; ?>

    <?php foreach ($_SESSION['flash']['error'] as $m): ?>
        <div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.75rem;">
            <i class="fas fa-exclamation-circle"></i>
            <span><?= htmlspecialchars($m) ?></span>
        </div>
    <?php endforeach; ?>
    <?php $_SESSION['flash'] = ['success'=>[],'error'=>[]]; ?>

    <?php if ($view === 'listado' || $view === 'mis_tomas' || $view === 'historial'): ?>

        <!-- Header -->
        <div style="background: white; padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <h2 style="margin: 0; font-size: 1.5rem; color: #1e293b;">
                <i class="fas fa-clipboard-check"></i> Inventario Físico
            </h2>
            <?php if (in_array($rol, ['super_admin','admin','supervisor']) && $view !== 'historial'): ?>
            <a href="inventario-fisico.php?view=nueva" style="padding: 0.5rem 1rem; border-radius: 6px; background: #10b981; color: white; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem;">
                <i class="fas fa-plus"></i> Nueva Toma
            </a>
            <?php endif; ?>
        </div>

        <!-- Tabs -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0; flex-wrap: wrap;">
            <a href="inventario-fisico.php?view=listado" 
               style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid <?= $view === 'listado' ? '#667eea' : 'transparent' ?>; color: <?= $view === 'listado' ? '#667eea' : '#64748b' ?>; text-decoration: none; font-weight: 600; margin-bottom: -2px; white-space: nowrap;">
                <i class="fas fa-list"></i> Todas las Tomas
            </a>
            <a href="inventario-fisico.php?view=mis_tomas" 
               style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid <?= $view === 'mis_tomas' ? '#667eea' : 'transparent' ?>; color: <?= $view === 'mis_tomas' ? '#667eea' : '#64748b' ?>; text-decoration: none; font-weight: 600; margin-bottom: -2px; white-space: nowrap;">
                <i class="fas fa-user-check"></i> Mis Tomas
            </a>
            <a href="inventario-fisico.php?view=historial" 
               style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid <?= $view === 'historial' ? '#667eea' : 'transparent' ?>; color: <?= $view === 'historial' ? '#667eea' : '#64748b' ?>; text-decoration: none; font-weight: 600; margin-bottom: -2px; white-space: nowrap;">
                <i class="fas fa-archive"></i> Historial
            </a>
        </div>

        <!-- Lista tomas -->
        <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <?php if (empty($tomas)): ?>
                <div style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                    <i class="fas fa-clipboard-list" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.4;"></i>
                    <h4>No hay tomas registradas</h4>
                </div>
            <?php else: ?>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0;">
                            <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">#</th>
                            <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">Nombre</th>
                            <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">Ubicación</th>
                            <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">Responsable</th>
                            <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">Fecha</th>
                            <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">Estado</th>
                            <th style="text-align: center; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">Ítems</th>
                            <th style="text-align: right; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem; text-transform: uppercase;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tomas as $t): ?>
                            <?php $items = $db->fetchOne("SELECT COUNT(*) AS c FROM inventario_detalles WHERE toma_id = ?", [$t['id']]); ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 1rem 0.75rem;"><strong>#<?= $t['id'] ?></strong></td>
                                <td style="padding: 1rem 0.75rem;"><strong><?= htmlspecialchars($t['nombre_toma']) ?></strong></td>
                                <td style="padding: 1rem 0.75rem;"><?= htmlspecialchars($t['ubicacion']) ?></td>
                                <td style="padding: 1rem 0.75rem;"><?= htmlspecialchars($t['responsable_nombre'] ?? '-') ?></td>
                                <td style="padding: 1rem 0.75rem;"><?= date('d/m/Y', strtotime($t['fecha_programada'])) ?></td>
                                <td style="padding: 1rem 0.75rem;">
                                    <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: <?= $t['estado'] === 'abierta' ? '#d1fae5' : '#f1f5f9' ?>; color: <?= $t['estado'] === 'abierta' ? '#065f46' : '#475569' ?>;">
                                        <?= strtoupper($t['estado']) ?>
                                    </span>
                                </td>
                                <td style="padding: 1rem 0.75rem; text-align: center;"><?= $items['c'] ?></td>
                                <td style="padding: 1rem 0.75rem; text-align: right;">
                                    <a href="inventario-fisico.php?view=detalle&toma_id=<?= $t['id'] ?>" 
                                       style="padding: 0.375rem 0.75rem; border-radius: 6px; background: #667eea; color: white; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;">
                                        <i class="fas fa-folder-open"></i> Abrir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            <?php endif ?>
        </div>

    <?php endif; ?>

    <?php if ($view === 'nueva' && in_array($rol, ['super_admin','admin','supervisor'])): ?>

        <div style="background: white; padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <h2 style="margin: 0; font-size: 1.5rem; color: #1e293b;">
                <i class="fas fa-plus-circle"></i> Nueva Toma de Inventario
            </h2>
        </div>

        <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <form method="post">
                <input type="hidden" name="action" value="crear_toma">
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569; font-size: 0.875rem;">Nombre de la toma *</label>
                    <input type="text" name="nombre_toma" required placeholder="Ej: Inventario Anual 2025" style="width: 100%; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9375rem;">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569; font-size: 0.875rem;">Ubicación / Almacén *</label>
                        <input type="text" name="ubicacion" required placeholder="Ej: Almacén Central" style="width: 100%; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9375rem;">
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569; font-size: 0.875rem;">Fecha programada</label>
                        <input type="date" name="fecha_programada" value="<?= date('Y-m-d') ?>" style="width: 100%; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9375rem;">
                    </div>
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569; font-size: 0.875rem;">Comentarios</label>
                    <textarea name="comentarios" rows="3" placeholder="Instrucciones, notas o restricciones especiales..." style="width: 100%; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.9375rem;"></textarea>
                </div>

                <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                    <a href="inventario-fisico.php" style="padding: 0.5rem 1rem; border-radius: 6px; background: white; border: 1px solid #e2e8f0; color: #64748b; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                        Cancelar
                    </a>
                    <button type="submit" style="padding: 0.5rem 1rem; border-radius: 6px; background: #10b981; color: white; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem;">
                        <i class="fas fa-save"></i> Crear Toma
                    </button>
                </div>
            </form>
        </div>

    <?php endif; ?>

    <?php if ($view === 'detalle' && $toma_actual): ?>

        <?php
        $total = count($detalles);
        $contados = 0;
        foreach ($detalles as $d) {
            if ($d['conteo_fisico'] > 0) $contados++;
        }
        $pendientes = $total - $contados;
        $avance = $total > 0 ? round($contados * 100 / $total) : 0;
        ?>

        <!-- Header toma + Stats EN UNA SOLA CARD -->
        <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 1.5rem;">
            
            <!-- Título y acciones -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h3 style="margin: 0 0 0.5rem 0; font-size: 1.25rem; color: #1e293b;">
                        <i class="fas fa-folder-open"></i> 
                        Toma #<?= $toma_actual['id'] ?>: <?= htmlspecialchars($toma_actual['nombre_toma']) ?>
                    </h3>
                    <div style="display: flex; gap: 1.5rem; color: #64748b; font-size: 0.875rem; flex-wrap: wrap;">
                        <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: <?= $toma_actual['estado'] === 'abierta' ? '#d1fae5' : '#f1f5f9' ?>; color: <?= $toma_actual['estado'] === 'abierta' ? '#065f46' : '#475569' ?>;">
                            <?= strtoupper($toma_actual['estado']) ?>
                        </span>
                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($toma_actual['ubicacion']) ?></span>
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($toma_actual['responsable_nombre'] ?? 'Sin asignar') ?></span>
                        <span><i class="fas fa-calendar"></i> <?= date('d/m/Y', strtotime($toma_actual['fecha_programada'])) ?></span>
                    </div>
                </div>
                <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                    <a href="inventario-fisico.php" style="padding: 0.375rem 0.75rem; border-radius: 6px; background: white; border: 1px solid #e2e8f0; color: #64748b; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;">
                        <i class="fas fa-arrow-left"></i> Volver
                    </a>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="exportar_toma">
                        <input type="hidden" name="toma_id" value="<?= $toma_actual['id'] ?>">
                        <button style="padding: 0.375rem 0.75rem; border-radius: 6px; background: #10b981; color: white; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;">
                            <i class="fas fa-file-excel"></i> Exportar
                        </button>
                    </form>
                    <?php if ($toma_actual['estado'] === 'abierta' && in_array($rol, ['super_admin','admin','supervisor'])): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="cerrar_toma">
                        <input type="hidden" name="toma_id" value="<?= $toma_actual['id'] ?>">
                        <button onclick="return confirm('¿Cerrar esta toma?')" style="padding: 0.375rem 0.75rem; border-radius: 6px; background: #ef4444; color: white; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;">
                            <i class="fas fa-lock"></i> Cerrar
                        </button>
                    </form>
                    <?php elseif ($toma_actual['estado'] === 'cerrada' && in_array($rol, ['super_admin','admin'])): ?>
                    <form method="post" style="display: inline;">
                        <input type="hidden" name="action" value="reabrir_toma">
                        <input type="hidden" name="toma_id" value="<?= $toma_actual['id'] ?>">
                        <button onclick="return confirm('¿Reabrir esta toma para modificaciones?')" style="padding: 0.375rem 0.75rem; border-radius: 6px; background: #667eea; color: white; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;">
                            <i class="fas fa-unlock"></i> Reabrir
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- STATS HORIZONTAL - INLINE STYLE DIRECTO -->
            <div style="display: flex; flex-direction: row; justify-content: space-around; align-items: center; gap: 2rem; padding: 1.5rem; background: #f8fafc; border-radius: 8px; flex-wrap: wrap;">
                <div style="text-align: center; min-width: 100px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #1e293b; line-height: 1; margin-bottom: 0.5rem;"><?= $total ?></div>
                    <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Total Ítems</div>
                </div>
                <div style="text-align: center; min-width: 100px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #10b981; line-height: 1; margin-bottom: 0.5rem;"><?= $contados ?></div>
                    <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Contados</div>
                </div>
                <div style="text-align: center; min-width: 100px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #ef4444; line-height: 1; margin-bottom: 0.5rem;"><?= $pendientes ?></div>
                    <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Pendientes</div>
                </div>
                <div style="text-align: center; min-width: 100px;">
                    <div style="font-size: 2rem; font-weight: 700; color: #667eea; line-height: 1; margin-bottom: 0.5rem;"><?= $avance ?>%</div>
                    <div style="font-size: 0.75rem; color: #64748b; text-transform: uppercase; font-weight: 600; letter-spacing: 0.05em;">Avance</div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div style="display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 2px solid #e2e8f0; padding-bottom: 0; flex-wrap: wrap;">
            <a href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=items" 
               style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid <?= $tab === 'items' ? '#667eea' : 'transparent' ?>; color: <?= $tab === 'items' ? '#667eea' : '#64748b' ?>; text-decoration: none; font-weight: 600; margin-bottom: -2px;">
                <i class="fas fa-th-list"></i> Ítems (<?= $total ?>)
            </a>
            <a href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=resumen" 
               style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid <?= $tab === 'resumen' ? '#667eea' : 'transparent' ?>; color: <?= $tab === 'resumen' ? '#667eea' : '#64748b' ?>; text-decoration: none; font-weight: 600; margin-bottom: -2px;">
                <i class="fas fa-chart-pie"></i> Resumen
            </a>
            <a href="inventario-fisico.php?view=detalle&toma_id=<?= $toma_actual['id'] ?>&tab=diferencias" 
               style="padding: 0.75rem 1.5rem; background: none; border: none; border-bottom: 3px solid <?= $tab === 'diferencias' ? '#667eea' : 'transparent' ?>; color: <?= $tab === 'diferencias' ? '#667eea' : '#64748b' ?>; text-decoration: none; font-weight: 600; margin-bottom: -2px;">
                <i class="fas fa-exclamation-triangle"></i> Diferencias
            </a>
        </div>

        <?php if ($tab === 'items'): ?>
        <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h4 style="margin: 0;"><i class="fas fa-th-list"></i> Lista de Ítems</h4>
                <?php if ($toma_actual['estado'] === 'abierta'): ?>
                <button onclick="document.getElementById('modalAgregar').style.display='flex'" 
                        style="padding: 0.375rem 0.75rem; border-radius: 6px; background: #10b981; color: white; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;">
                    <i class="fas fa-plus"></i> Agregar Ítem
                </button>
                <?php endif; ?>
            </div>

            <?php if (empty($detalles)): ?>
                <div style="text-align: center; padding: 3rem 1rem; color: #94a3b8;">
                    <i class="fas fa-box-open" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.4;"></i>
                    <h4>No hay ítems registrados</h4>
                </div>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0;">
                                <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem;">#</th>
                                <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem;">CÓDIGO</th>
                                <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem;">DESCRIPCIÓN</th>
                                <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem;">UBICACIÓN</th>
                                <th style="text-align: center; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem;">CONTEO</th>
                                <th style="text-align: left; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem;">OBSERVACIONES</th>
                                <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                <th style="text-align: center; padding: 0.75rem; color: #64748b; font-weight: 600; font-size: 0.875rem;">ACCIONES</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $d): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 1rem 0.75rem;"><?= $d['id'] ?></td>
                                <td style="padding: 1rem 0.75rem;"><code style="color: #667eea; font-weight: 600;"><?= htmlspecialchars($d['codigo_item']) ?></code></td>
                                <td style="padding: 1rem 0.75rem;"><?= htmlspecialchars($d['descripcion_item']) ?></td>
                                <td style="padding: 1rem 0.75rem;"><?= htmlspecialchars($d['ubicacion_fisica']) ?: '-' ?></td>
                                <td style="padding: 1rem 0.75rem; text-align: center;">
                                    <?php if ($d['conteo_fisico'] > 0): ?>
                                        <span style="display: inline-block; padding: 0.375rem 0.75rem; border-radius: 12px; font-size: 1rem; font-weight: 600; background: #d1fae5; color: #065f46;">
                                            <?= $d['conteo_fisico'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="display: inline-block; padding: 0.375rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: #fef3c7; color: #92400e;">Pendiente</span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding: 1rem 0.75rem;">
                                    <small><?= htmlspecialchars($d['observaciones']) ?: '-' ?></small>
                                </td>
                                <?php if ($toma_actual['estado'] === 'abierta'): ?>
                                <td style="padding: 1rem 0.75rem; text-align: center;">
                                    <button onclick="abrirModalEditar(<?= $d['id'] ?>, '<?= addslashes($d['codigo_item']) ?>', '<?= addslashes($d['descripcion_item']) ?>', '<?= $d['conteo_fisico'] ?>', '<?= addslashes($d['observaciones']) ?>')"
                                            style="padding: 0.375rem 0.75rem; border-radius: 6px; background: #667eea; color: white; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; margin-right: 0.25rem;">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="if(confirm('¿Eliminar este ítem?')) { document.getElementById('deleteForm<?= $d['id'] ?>').submit(); }"
                                            style="padding: 0.375rem 0.75rem; border-radius: 6px; background: #ef4444; color: white; border: none; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem;">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <form id="deleteForm<?= $d['id'] ?>" method="post" style="display: none;">
                                        <input type="hidden" name="action" value="eliminar_item">
                                        <input type="hidden" name="detalle_id" value="<?= $d['id'] ?>">
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($tab === 'resumen'): ?>
        <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <?php if ($pendientes == 0): ?>
                <div style="background: #d1fae5; color: #065f46; padding: 2rem; border-radius: 8px; text-align: center;">
                    <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h3 style="margin: 0;">¡Conteo Completado!</h3>
                    <p style="margin: 0.5rem 0 0 0;">Todos los ítems han sido contados</p>
                </div>
            <?php else: ?>
                <div style="background: #fef3c7; color: #92400e; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1rem;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 2rem;"></i>
                    <div>
                        <strong>Atención:</strong> Hay <strong><?= $pendientes ?></strong> ítem(s) pendiente(s)
                    </div>
                </div>
                <h5 style="margin-bottom: 1rem;">Ítems Pendientes</h5>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0;">
                                <th style="text-align: left; padding: 0.75rem; color: #64748b;">#</th>
                                <th style="text-align: left; padding: 0.75rem; color: #64748b;">CÓDIGO</th>
                                <th style="text-align: left; padding: 0.75rem; color: #64748b;">DESCRIPCIÓN</th>
                                <th style="text-align: center; padding: 0.75rem; color: #64748b;">ESTADO</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($detalles as $d): ?>
                                <?php if ($d['conteo_fisico'] == 0 || $d['conteo_fisico'] === null): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 1rem 0.75rem;"><?= $d['id'] ?></td>
                                    <td style="padding: 1rem 0.75rem;"><code style="color: #667eea;"><?= htmlspecialchars($d['codigo_item']) ?></code></td>
                                    <td style="padding: 1rem 0.75rem;"><?= htmlspecialchars($d['descripcion_item']) ?></td>
                                    <td style="padding: 1rem 0.75rem; text-align: center;">
                                        <span style="display: inline-block; padding: 0.25rem 0.75rem; border-radius: 12px; font-size: 0.75rem; font-weight: 600; background: #fef3c7; color: #92400e;">Pendiente</span>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($tab === 'diferencias'): ?>
        <div style="background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
            <div style="background: #dbeafe; color: #1e40af; padding: 1rem; border-radius: 8px; display: flex; align-items: center; gap: 1rem;">
                <i class="fas fa-info-circle" style="font-size: 2rem;"></i>
                <div>
                    <strong>Análisis de Diferencias</strong><br>
                    Esta sección mostraría las diferencias entre el inventario teórico y el conteo físico.<br>
                    <small>Requiere integración con el sistema de inventario teórico.</small>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Modal Agregar -->
        <div id="modalAgregar" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 12px; width: 90%; max-width: 500px;">
                <form method="post">
                    <input type="hidden" name="action" value="agregar_item">
                    <input type="hidden" name="toma_id" value="<?= $toma_actual['id'] ?>">
                    <div style="padding: 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;"><i class="fas fa-plus-circle"></i> Agregar Ítem</h3>
                        <button type="button" onclick="document.getElementById('modalAgregar').style.display='none'" 
                                style="border: none; background: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569;">Código *</label>
                            <input type="text" name="codigo_item" required style="width: 100%; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569;">Descripción *</label>
                            <input type="text" name="descripcion_item" required style="width: 100%; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569;">Ubicación Física</label>
                            <input type="text" name="ubicacion_fisica" style="width: 100%; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                        </div>
                    </div>
                    <div style="padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; display: flex; gap: 0.5rem; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('modalAgregar').style.display='none'" 
                                style="padding: 0.5rem 1rem; border-radius: 6px; background: white; border: 1px solid #e2e8f0; color: #64748b; font-weight: 600; cursor: pointer;">Cancelar</button>
                        <button type="submit" style="padding: 0.5rem 1rem; border-radius: 6px; background: #10b981; color: white; border: none; font-weight: 600; cursor: pointer;">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal Editar -->
        <div id="modalEditar" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center;">
            <div style="background: white; border-radius: 12px; width: 90%; max-width: 500px;">
                <form method="post">
                    <input type="hidden" name="action" value="actualizar_conteo">
                    <input type="hidden" name="detalle_id" id="edit_detalle_id">
                    <div style="padding: 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                        <h3 style="margin: 0;"><i class="fas fa-calculator"></i> Registrar Conteo</h3>
                        <button type="button" onclick="document.getElementById('modalEditar').style.display='none'" 
                                style="border: none; background: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
                    </div>
                    <div style="padding: 1.5rem;">
                        <div style="background: #dbeafe; padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                            <strong id="edit_item_info"></strong>
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569;">Cantidad Contada *</label>
                            <input type="number" step="0.01" name="conteo_fisico" id="edit_conteo" required style="width: 100%; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 6px;">
                        </div>
                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: #475569;">Observaciones</label>
                            <textarea name="observaciones" id="edit_observaciones" rows="3" style="width: 100%; padding: 0.625rem; border: 1px solid #e2e8f0; border-radius: 6px;"></textarea>
                        </div>
                    </div>
                    <div style="padding: 1rem 1.5rem; border-top: 1px solid #e2e8f0; display: flex; gap: 0.5rem; justify-content: flex-end;">
                        <button type="button" onclick="document.getElementById('modalEditar').style.display='none'" 
                                style="padding: 0.5rem 1rem; border-radius: 6px; background: white; border: 1px solid #e2e8f0; color: #64748b; font-weight: 600; cursor: pointer;">Cancelar</button>
                        <button type="submit" style="padding: 0.5rem 1rem; border-radius: 6px; background: #667eea; color: white; border: none; font-weight: 600; cursor: pointer;">Guardar</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function abrirModalEditar(id, codigo, desc, conteo, obs) {
            document.getElementById('edit_detalle_id').value = id;
            document.getElementById('edit_item_info').textContent = codigo + ' - ' + desc;
            document.getElementById('edit_conteo').value = conteo || '';
            document.getElementById('edit_observaciones').value = obs || '';
            document.getElementById('modalEditar').style.display = 'flex';
        }
        </script>

    <?php endif; ?>

</div>

<div id="logistica-sidebar-content" style="display: none;">
    <?php require_once __DIR__ . '/includes/sidebar-logistica.php'; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const currentSidebar = document.querySelector('.app-sidebar');
    const logisticaSidebarContent = document.getElementById('logistica-sidebar-content');
    if (currentSidebar && logisticaSidebarContent) {
        const newSidebar = logisticaSidebarContent.querySelector('.app-sidebar');
        if (newSidebar) {
            currentSidebar.innerHTML = newSidebar.innerHTML;
            logisticaSidebarContent.remove();
        }
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>