<?php
// integraciones.php (RAÍZ) - Patrón idéntico a registrar-vehiculo.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Integraciones';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'integraciones.php' => 'Integraciones'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

/* ===============================
   OBTENER LISTADO
================================ */
$integraciones = $db->fetchAll("
    SELECT id, nombre, evento, url, secret_key, reintentos, activo
    FROM webhooks
    WHERE cliente_id = ?
    ORDER BY nombre
", [$cliente_id]);

/* ===============================
   ESTADÍSTICAS
================================ */
$estadisticas = $db->fetchOne("
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos,
        COUNT(DISTINCT evento) as eventos
    FROM webhooks
    WHERE cliente_id = ?
", [$cliente_id]);

/* ===============================
   POST ACTIONS
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // GUARDAR (CREAR/EDITAR)
    if (isset($_POST['guardar_integracion'])) {

        $integracion_id = $_POST['integracion_id'] ?? null;
        $nombre = trim($_POST['nombre'] ?? '');
        $evento = trim($_POST['evento'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $secret_key = trim($_POST['secret_key'] ?? '');
        $reintentos = (int)($_POST['reintentos'] ?? 3);
        $activo = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;

        try {
            if ($nombre === '' || $evento === '' || $url === '') {
                throw new Exception('Nombre, evento y URL son obligatorios');
            }

            if ($reintentos < 0) $reintentos = 0;
            if ($reintentos > 20) $reintentos = 20;
            if ($activo !== 0 && $activo !== 1) $activo = 1;

            // Duplicado (cliente_id + evento + url)
            if ($integracion_id) {
                $dup = $db->fetchOne("
                    SELECT id FROM webhooks
                    WHERE cliente_id = ? AND evento = ? AND url = ? AND id != ?
                ", [$cliente_id, $evento, $url, $integracion_id]);
            } else {
                $dup = $db->fetchOne("
                    SELECT id FROM webhooks
                    WHERE cliente_id = ? AND evento = ? AND url = ?
                ", [$cliente_id, $evento, $url]);
            }

            if ($dup) {
                throw new Exception('Ya existe una integración con ese evento y URL');
            }

            if ($integracion_id) {
                // ACTUALIZAR
                $db->execute("
                    UPDATE webhooks
                    SET nombre = ?, evento = ?, url = ?, secret_key = ?, reintentos = ?, activo = ?
                    WHERE id = ? AND cliente_id = ?
                ", [$nombre, $evento, $url, ($secret_key !== '' ? $secret_key : null), $reintentos, $activo, $integracion_id, $cliente_id]);

                $mensaje_exito = 'Integración actualizada correctamente';
                $accion = 'ACTUALIZAR_INTEGRACION';
                $registro_id = $integracion_id;

            } else {
                // CREAR
                $db->execute("
                    INSERT INTO webhooks
                    (cliente_id, nombre, evento, url, secret_key, reintentos, activo)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ", [$cliente_id, $nombre, $evento, $url, ($secret_key !== '' ? $secret_key : null), $reintentos, $activo]);

                $registro_id = $db->lastInsertId();
                $mensaje_exito = 'Integración creada correctamente';
                $accion = 'CREAR_INTEGRACION';
            }

            // AUDITORÍA (si tu tabla auditoria existe, mismo patrón que vehículos)
            try {
                $detalles = "Integración {$nombre} - Evento: {$evento} - Estado: " . ($activo ? 'Activo' : 'Inactivo');
                $db->execute("
                    INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                    VALUES (?, ?, ?, 'webhooks', ?, ?, ?, ?)
                ", [
                    $cliente_id,
                    $user_id,
                    $accion,
                    $registro_id,
                    $detalles,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {
                // Sin romper flujo
            }

            // Recargar listado
            $integraciones = $db->fetchAll("
                SELECT id, nombre, evento, url, secret_key, reintentos, activo
                FROM webhooks
                WHERE cliente_id = ?
                ORDER BY nombre
            ", [$cliente_id]);

        } catch (Exception $e) {
            $mensaje_error = $e->getMessage();
        }
    }

    // ELIMINAR
    if (isset($_POST['eliminar_integracion'])) {
        $integracion_id = $_POST['integracion_id'] ?? null;

        try {
            if (!$integracion_id) {
                throw new Exception('ID inválido');
            }

            // Verificar pertenencia antes de eliminar (misma idea que vehículos)
            $integracion = $db->fetchOne("
                SELECT nombre, evento FROM webhooks
                WHERE id = ? AND cliente_id = ?
            ", [$integracion_id, $cliente_id]);

            if (!$integracion) {
                throw new Exception('Integración no encontrada o no pertenece a su empresa');
            }

            $db->execute("
                DELETE FROM webhooks
                WHERE id = ? AND cliente_id = ?
            ", [$integracion_id, $cliente_id]);

            $mensaje_exito = 'Integración eliminada correctamente';

            // Auditoría
            try {
                $detalles = "Integración eliminada: {$integracion['nombre']} - Evento: {$integracion['evento']}";
                $db->execute("
                    INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                    VALUES (?, ?, 'ELIMINAR_INTEGRACION', 'webhooks', ?, ?, ?, ?)
                ", [
                    $cliente_id,
                    $user_id,
                    $integracion_id,
                    $detalles,
                    $_SERVER['REMOTE_ADDR'] ?? '',
                    $_SERVER['HTTP_USER_AGENT'] ?? ''
                ]);
            } catch (Exception $e) {}

            // Recargar listado
            $integraciones = $db->fetchAll("
                SELECT id, nombre, evento, url, secret_key, reintentos, activo
                FROM webhooks
                WHERE cliente_id = ?
                ORDER BY nombre
            ", [$cliente_id]);

        } catch (Exception $e) {
            $mensaje_error = $e->getMessage();
        }
    }
}

/* ===============================
   EDICIÓN POR GET
================================ */
$integracion_editar = null;
if (isset($_GET['editar'])) {
    $integracion_editar = $db->fetchOne("
        SELECT * FROM webhooks
        WHERE id = ? AND cliente_id = ?
    ", [$_GET['editar'], $cliente_id]);
}
?>

<div class="content-body">
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona integraciones y webhooks del sistema</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="mostrarModalIntegracion()">
                <i class="fas fa-plus"></i>Nueva Integración
            </button>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>Volver al Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($mensaje_exito)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $mensaje_exito; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($mensaje_error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $mensaje_error; ?>
    </div>
    <?php endif; ?>

    <div class="crud-container">

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Estadísticas de Integraciones</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-plug"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total'] ?? 0; ?></h3>
                            <p>Total Integraciones</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['activos'] ?? 0; ?></h3>
                            <p>Activas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['inactivos'] ?? 0; ?></h3>
                            <p>Inactivas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['eventos'] ?? 0; ?></h3>
                            <p>Eventos distintos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- LISTADO -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list-alt"></i> Integraciones Registradas</h3>
                <div class="card-actions">
                    <span class="badge primary"><?php echo count($integraciones); ?> registros</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($integraciones)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Evento</th>
                                <th>URL</th>
                                <th>Reintentos</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($integraciones as $i): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($i['nombre']); ?></strong></td>
                                <td><?php echo htmlspecialchars($i['evento']); ?></td>
                                <td><?php echo htmlspecialchars($i['url']); ?></td>
                                <td><?php echo (int)$i['reintentos']; ?></td>
                                <td>
                                    <span class="status-badge estado-<?php echo ((int)$i['activo']===1) ? 'activo' : 'inactivo'; ?>">
                                        <?php echo ((int)$i['activo']===1) ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-icon primary" title="Editar"
                                            onclick="editarIntegracion(<?php echo (int)$i['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn-icon danger" title="Eliminar"
                                            onclick="eliminarIntegracion(<?php echo (int)$i['id']; ?>, '<?php echo htmlspecialchars($i['nombre']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-plug"></i>
                    </div>
                    <h3>No hay integraciones</h3>
                    <p>Registra tu primera integración</p>
                    <div class="empty-actions">
                        <button type="button" class="btn btn-primary" onclick="mostrarModalIntegracion()">
                            <i class="fas fa-plus"></i>Crear Integración
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<!-- MODAL (CUSTOM) -->
<div id="modalIntegracion" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-plug"></i> <span id="modalTituloIntegracion">Nueva Integración</span></h3>
            <button type="button" class="modal-close" onclick="cerrarModalIntegracion()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="formIntegracion" method="POST">
                <input type="hidden" name="integracion_id" id="integracion_id">
                <input type="hidden" name="guardar_integracion" value="1">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre">Nombre *</label>
                        <input type="text" id="nombre" name="nombre" class="form-control" required maxlength="80"
                               value="<?php echo $integracion_editar['nombre'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="evento">Evento *</label>
                        <input type="text" id="evento" name="evento" class="form-control" required maxlength="80"
                               value="<?php echo $integracion_editar['evento'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="url">URL *</label>
                        <input type="url" id="url" name="url" class="form-control" required maxlength="255"
                               value="<?php echo $integracion_editar['url'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="secret_key">Secret Key</label>
                        <input type="text" id="secret_key" name="secret_key" class="form-control" maxlength="255"
                               value="<?php echo $integracion_editar['secret_key'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="reintentos">Reintentos</label>
                        <input type="number" id="reintentos" name="reintentos" class="form-control"
                               min="0" max="20"
                               value="<?php echo $integracion_editar['reintentos'] ?? 3; ?>">
                    </div>
                    <div class="form-group">
                        <label for="activo">Estado</label>
                        <select id="activo" name="activo" class="form-control" required>
                            <option value="1" <?php echo (($integracion_editar['activo'] ?? 1) == 1) ? 'selected' : ''; ?>>Activo</option>
                            <option value="0" <?php echo (($integracion_editar['activo'] ?? 1) == 0) ? 'selected' : ''; ?>>Inactivo</option>
                        </select>
                    </div>
                </div>

                <!-- BOTÓN REAL (IMPORTANTE): ENVÍA name="guardar_integracion" -->
                <button type="submit" name="guardar_integracion" value="1" style="display:none;"></button>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="cerrarModalIntegracion()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" form="formIntegracion" name="guardar_integracion" value="1" class="btn btn-primary">
                <i class="fas fa-save"></i> <span id="textoBotonGuardarIntegracion">Guardar Integración</span>
            </button>
        </div>
    </div>
</div>

<!-- FORM OCULTO ELIMINAR -->
<form id="formEliminarIntegracion" method="POST" style="display:none;">
    <input type="hidden" name="integracion_id" id="integracion_eliminar_id">
    <input type="hidden" name="eliminar_integracion" value="1">
</form>

<style>
/* ESTILOS CSS INTEGRADOS (mismo patrón que registrar-vehiculo.php) */
.crud-container { margin-top: 1.5rem; width: 100%; }
.data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 0; }
.data-table th { background: var(--light); padding: 1rem; text-align: left; font-weight: 600; color: var(--dark); border-bottom: 2px solid var(--border); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
.data-table td { padding: 1rem; border-bottom: 1px solid var(--border); color: var(--dark); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover { background: rgba(243, 156, 18, 0.04); }
.action-buttons { display: flex; gap: 0.5rem; justify-content: center; }
.btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: var(--light); color: var(--dark); text-decoration: none; transition: all 0.3s ease; border: none; cursor: pointer; }
.btn-icon:hover { background: var(--primary); color: white; transform: translateY(-2px); }
.btn-icon.danger:hover { background: var(--danger); }
.btn-icon.primary:hover { background: var(--primary); }
.status-badge { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; display: inline-block; text-align: center; min-width: 80px; }
.status-badge.estado-activo { background: rgba(39, 174, 96, 0.15); color: var(--success); border: 1px solid rgba(39, 174, 96, 0.3); }
.status-badge.estado-inactivo { background: rgba(108, 117, 125, 0.15); color: #6c757d; border: 1px solid rgba(108, 117, 125, 0.3); }
.badge { padding: 0.4rem 0.8rem; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.badge.primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); }
.table-responsive { overflow-x: auto; border-radius: 12px; }
.empty-state { text-align: center; padding: 4rem 2rem; color: var(--gray); }
.empty-icon { font-size: 4rem; color: var(--light); margin-bottom: 1.5rem; opacity: 0.7; }
.empty-state h3 { color: var(--dark); margin-bottom: 0.5rem; font-weight: 600; }
.empty-state p { margin-bottom: 2rem; font-size: 1rem; opacity: 0.8; }
.empty-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
.alert { padding: 1rem 1.5rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border: 1px solid transparent; }
.alert-success { background: rgba(39, 174, 96, 0.1); border-color: rgba(39, 174, 96, 0.2); color: var(--success); }
.alert-danger { background: rgba(231, 76, 60, 0.1); border-color: rgba(231, 76, 60, 0.2); color: var(--danger); }
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 1.5rem 0; border-bottom: 1px solid var(--border); }
.welcome-section h1 { margin: 0 0 0.5rem 0; color: var(--dark); font-size: 1.8rem; font-weight: 700; }
.dashboard-subtitle { margin: 0; color: var(--gray); font-size: 1rem; }
.header-actions { display: flex; gap: 1rem; }
.card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); overflow: hidden; margin-bottom: 1.5rem; }
.card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center; }
.card-header h3 { margin: 0; color: var(--dark); font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.card-body { padding: 1.5rem; }

.stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
.stat-card { display: flex; align-items: center; gap: 1rem; padding: 1.5rem; background: white; border-radius: 10px; border: 1px solid var(--border); transition: all 0.3s ease; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
.stat-icon { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; }
.stat-icon.primary { background: rgba(52, 152, 219, 0.15); color: var(--primary); }
.stat-icon.success { background: rgba(39, 174, 96, 0.15); color: var(--success); }
.stat-icon.danger { background: rgba(231, 76, 60, 0.15); color: var(--danger); }
.stat-icon.info { background: rgba(155, 89, 182, 0.15); color: #9b59b6; }
.stat-info h3 { margin: 0 0 0.25rem 0; font-size: 1.5rem; font-weight: 700; color: var(--dark); }
.stat-info p { margin: 0; color: var(--gray); font-size: 0.85rem; }

/* Modal (CLAVE: OCULTO POR DEFECTO) */
.modal {
    display: none;          /* <-- IMPORTANTE */
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}
.modal.show { display: flex; }
.modal-content { background: white; border-radius: 12px; width: 90%; max-width: 650px; max-height: 90vh; overflow: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
.modal-header { padding: 1.5rem; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--light); }
.modal-header h3 { margin: 0; color: var(--dark); display: flex; align-items: center; gap: 0.5rem; }
.modal-close { background: none; border: none; font-size: 1.25rem; color: var(--gray); cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: all 0.3s ease; }
.modal-close:hover { background: var(--danger); color: white; }
.modal-body { padding: 1.5rem; }
.modal-footer { padding: 1.5rem; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 1rem; }

/* Form */
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
.form-group { display: flex; flex-direction: column; }
.form-group label { font-weight: 600; color: var(--dark); margin-bottom: 0.5rem; font-size: 0.9rem; }
.form-control { padding: 0.875rem 1rem; border: 2px solid #e1e8ed; border-radius: 10px; font-size: 0.95rem; background: #fff; color: var(--dark); width: 100%; box-sizing: border-box; }
.form-control:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 4px rgba(52,152,219,0.1); }

/* Responsive */
@media (max-width: 1024px) { .stats-grid { grid-template-columns: repeat(2, 1fr); } .form-grid{ grid-template-columns: 1fr; } }
@media (max-width: 768px) {
    .dashboard-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .header-actions { width: 100%; justify-content: flex-start; }
    .card-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .stats-grid { grid-template-columns: 1fr; }
    .stat-card { flex-direction: column; text-align: center; gap: 0.75rem; }
    .stat-icon { width: 50px; height: 50px; font-size: 1.25rem; }
    .modal-content { width: 95%; margin: 1rem; }
    .modal-footer { flex-direction: column; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Si viene edición por GET, abrir modal y setear modo edición
    <?php if ($integracion_editar): ?>
        prepararEdicionIntegracion();
        mostrarModalIntegracion();
    <?php endif; ?>
});

// Mostrar modal
function mostrarModalIntegracion() {
    const modal = document.getElementById('modalIntegracion');
    modal.classList.add('show');

    // Si no es edición, reset
    <?php if (!$integracion_editar): ?>
    document.getElementById('formIntegracion').reset();
    document.getElementById('integracion_id').value = '';
    document.getElementById('modalTituloIntegracion').textContent = 'Nueva Integración';
    document.getElementById('textoBotonGuardarIntegracion').textContent = 'Guardar Integración';
    <?php endif; ?>
}

// Cerrar modal
function cerrarModalIntegracion() {
    document.getElementById('modalIntegracion').classList.remove('show');
    if (window.location.search.includes('editar=')) {
        window.location.href = 'integraciones.php';
    }
}

// Click fuera para cerrar
document.addEventListener('click', function(e) {
    const modal = document.getElementById('modalIntegracion');
    if (e.target === modal) cerrarModalIntegracion();
});

// Preparar edición (texto)
function prepararEdicionIntegracion() {
    document.getElementById('modalTituloIntegracion').textContent = 'Editar Integración';
    document.getElementById('textoBotonGuardarIntegracion').textContent = 'Actualizar Integración';
    document.getElementById('integracion_id').value = '<?php echo $integracion_editar['id'] ?? ''; ?>';
}

// Redirigir para editar
function editarIntegracion(id) {
    window.location.href = 'integraciones.php?editar=' + id;
}

// Eliminar
function eliminarIntegracion(id, nombre) {
    if (confirm('¿Está seguro de eliminar la integración: ' + nombre + '?\n\nEsta acción no se puede deshacer.')) {
        document.getElementById('integracion_eliminar_id').value = id;
        document.getElementById('formEliminarIntegracion').submit();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
