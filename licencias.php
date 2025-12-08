<?php
// licencias.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Gestión de Licencias';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'licencias.php' => 'Gestión de Licencias'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// OBTENER LICENCIAS Y CONDUCTORES
$licencias = $db->fetchAll("
    SELECT 
        l.*,
        u.nombre as conductor_nombre,
        u.apellido as conductor_apellido,
        u.dni as conductor_dni,
        u.email as conductor_email,
        u.telefono as conductor_telefono
    FROM licencias l
    INNER JOIN usuarios u ON l.conductor_id = u.id
    WHERE l.cliente_id = ? AND u.cliente_id = ? AND u.rol = 'conductor'
    ORDER BY l.fecha_vencimiento ASC
", [$cliente_id, $cliente_id]);

// OBTENER CONDUCTORES PARA FORMULARIOS
$conductores = $db->fetchAll("
    SELECT id, nombre, apellido, dni, estado
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor' AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

// ESTADÍSTICAS DE LICENCIAS
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_licencias,
        SUM(CASE WHEN l.estado = 'activa' THEN 1 ELSE 0 END) as licencias_activas,
        SUM(CASE WHEN l.estado = 'vencida' THEN 1 ELSE 0 END) as licencias_vencidas,
        SUM(CASE WHEN l.estado = 'suspendida' THEN 1 ELSE 0 END) as licencias_suspendidas,
        SUM(CASE WHEN l.estado = 'inactiva' THEN 1 ELSE 0 END) as licencias_inactivas,
        COUNT(DISTINCT l.conductor_id) as conductores_con_licencia,
        AVG(DATEDIFF(l.fecha_vencimiento, CURDATE())) as dias_promedio_vencimiento,
        SUM(CASE WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 30 THEN 1 ELSE 0 END) as por_vencer_30_dias,
        SUM(CASE WHEN DATEDIFF(l.fecha_vencimiento, CURDATE()) <= 7 THEN 1 ELSE 0 END) as por_vencer_7_dias
    FROM licencias l
    INNER JOIN usuarios u ON l.conductor_id = u.id
    WHERE l.cliente_id = ? AND u.cliente_id = ?
", [$cliente_id, $cliente_id]);

// CATEGORÍAS DE LICENCIA (según normativa peruana)
$categorias = [
    'A-I' => 'A-I - Motocicletas',
    'A-IIa' => 'A-IIa - Motocicletas > 125cc',
    'A-IIb' => 'A-IIb - Motocicletas > 190cc',
    'A-IIIa' => 'A-IIIa - Mototaxis',
    'A-IIIb' => 'A-IIIb - Motocarros',
    'B-I' => 'B-I - Vehículos particulares',
    'B-IIa' => 'B-IIa - Vehículos particulares avanzado',
    'B-IIb' => 'B-IIb - Vehículos de transporte',
    'C-I' => 'C-I - Vehículos de carga',
    'C-II' => 'C-II - Vehículos de carga pesada',
    'D-I' => 'D-I - Maquinaria agrícola',
    'E-I' => 'E-I - Vehículos especiales'
];

// PROCESAR ACCIONES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_licencia'])) {
        $conductor_id = $_POST['conductor_id'] ?? null;
        $numero_licencia = trim($_POST['numero_licencia'] ?? '');
        $categoria = $_POST['categoria'] ?? '';
        $fecha_emision = $_POST['fecha_emision'] ?? '';
        $fecha_vencimiento = $_POST['fecha_vencimiento'] ?? '';
        $estado = $_POST['estado'] ?? 'activa';
        $restricciones = trim($_POST['restricciones'] ?? '');
        $licencia_id = $_POST['licencia_id'] ?? null;

        try {
            // Validaciones
            if (empty($conductor_id) || empty($numero_licencia) || empty($categoria) || empty($fecha_emision) || empty($fecha_vencimiento)) {
                throw new Exception("Todos los campos obligatorios deben ser completados");
            }

            // Validar fechas
            if (strtotime($fecha_vencimiento) <= strtotime($fecha_emision)) {
                throw new Exception("La fecha de vencimiento debe ser posterior a la fecha de emisión");
            }

            // Verificar que el conductor pertenece al cliente
            $conductor_valido = $db->fetchOne("
                SELECT id FROM usuarios 
                WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
            ", [$conductor_id, $cliente_id]);

            if (!$conductor_valido) {
                throw new Exception("Conductor no válido");
            }

            // Verificar si el número de licencia ya existe (excepto para edición)
            if ($licencia_id) {
                $existe_licencia = $db->fetchOne("
                    SELECT id FROM licencias 
                    WHERE cliente_id = ? AND numero_licencia = ? AND id != ?
                ", [$cliente_id, $numero_licencia, $licencia_id]);
            } else {
                $existe_licencia = $db->fetchOne("
                    SELECT id FROM licencias 
                    WHERE cliente_id = ? AND numero_licencia = ?
                ", [$cliente_id, $numero_licencia]);
            }

            if ($existe_licencia) {
                throw new Exception("Ya existe una licencia con el número: " . $numero_licencia);
            }

            if ($licencia_id) {
                // ACTUALIZAR LICENCIA EXISTENTE
                $db->execute("
                    UPDATE licencias 
                    SET conductor_id = ?, numero_licencia = ?, categoria = ?, 
                        fecha_emision = ?, fecha_vencimiento = ?, estado = ?, 
                        restricciones = ?, fecha_actualizacion = NOW()
                    WHERE id = ? AND cliente_id = ?
                ", [$conductor_id, $numero_licencia, $categoria, $fecha_emision, $fecha_vencimiento, $estado, $restricciones, $licencia_id, $cliente_id]);

                $mensaje_exito = "Licencia actualizada correctamente";
                $accion_auditoria = 'ACTUALIZAR_LICENCIA';
                $registro_id = $licencia_id;
                
            } else {
                // CREAR NUEVA LICENCIA
                $result = $db->execute("
                    INSERT INTO licencias 
                    (cliente_id, conductor_id, numero_licencia, categoria, 
                     fecha_emision, fecha_vencimiento, estado, restricciones)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ", [$cliente_id, $conductor_id, $numero_licencia, $categoria, $fecha_emision, $fecha_vencimiento, $estado, $restricciones]);

                if ($result) {
                    $licencia_id = $db->lastInsertId();
                    $mensaje_exito = "Licencia registrada correctamente";
                    $accion_auditoria = 'CREAR_LICENCIA';
                    $registro_id = $licencia_id;
                } else {
                    throw new Exception("Error al insertar en la base de datos");
                }
            }

            // AUDITORÍA
            $detalles = "Licencia {$numero_licencia} - Categoría: {$categoria} - Estado: {$estado} - Vence: {$fecha_vencimiento}";
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, ?, 'licencias', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $accion_auditoria, $registro_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            // Recargar datos
            header("Location: licencias.php?guardado=1");
            exit;

        } catch (Exception $e) {
            $mensaje_error = "Error al guardar la licencia: " . $e->getMessage();
        }
    }

    // ELIMINAR LICENCIA
    if (isset($_POST['eliminar_licencia'])) {
        $licencia_id = $_POST['licencia_id'] ?? null;
        
        if ($licencia_id) {
            try {
                // Verificar que la licencia pertenece al cliente
                $licencia = $db->fetchOne("
                    SELECT l.numero_licencia, u.nombre, u.apellido 
                    FROM licencias l
                    INNER JOIN usuarios u ON l.conductor_id = u.id
                    WHERE l.id = ? AND l.cliente_id = ?
                ", [$licencia_id, $cliente_id]);

                if (!$licencia) {
                    throw new Exception("Licencia no encontrada");
                }

                // Eliminar licencia
                $db->execute("
                    DELETE FROM licencias 
                    WHERE id = ? AND cliente_id = ?
                ", [$licencia_id, $cliente_id]);

                // AUDITORÍA
                $detalles = "Licencia eliminada: {$licencia['numero_licencia']} - Conductor: {$licencia['nombre']} {$licencia['apellido']}";
                $db->execute("
                    INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                    VALUES (?, ?, 'ELIMINAR_LICENCIA', 'licencias', ?, ?, ?, ?)
                ", [$cliente_id, $user_id, $licencia_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

                $mensaje_exito = "Licencia eliminada correctamente";
                
                // Recargar datos
                header("Location: licencias.php?eliminado=1");
                exit;

            } catch (Exception $e) {
                $mensaje_error = "Error al eliminar la licencia: " . $e->getMessage();
            }
        }
    }
}

// Mostrar mensajes de éxito
if (isset($_GET['guardado'])) {
    $mensaje_exito = "Licencia guardada correctamente";
}

if (isset($_GET['eliminado'])) {
    $mensaje_exito = "Licencia eliminada correctamente";
}

// OBTENER DATOS PARA EDICIÓN SI SE SOLICITA
$licencia_editar = null;
if (isset($_GET['editar'])) {
    $licencia_editar = $db->fetchOne("
        SELECT l.*, u.nombre as conductor_nombre, u.apellido as conductor_apellido
        FROM licencias l
        INNER JOIN usuarios u ON l.conductor_id = u.id
        WHERE l.id = ? AND l.cliente_id = ?
    ", [$_GET['editar'], $cliente_id]);
}
?>

<div class="content-body">
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona las licencias de conducir de tus conductores</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="mostrarModalLicencia()">
                <i class="fas fa-id-card"></i>Nueva Licencia
            </button>
            <a href="registrar-conductor.php" class="btn btn-outline">
                <i class="fas fa-users"></i>Gestionar Conductores
            </a>
        </div>
    </div>

    <!-- ALERTAS -->
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

    <!-- ESTADÍSTICAS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Estadísticas de Licencias</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['total_licencias'] ?? 0; ?></h3>
                        <p>Total Licencias</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['licencias_activas'] ?? 0; ?></h3>
                        <p>Licencias Activas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['licencias_vencidas'] ?? 0; ?></h3>
                        <p>Licencias Vencidas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['por_vencer_30_dias'] ?? 0; ?></h3>
                        <p>Por Vencer (30 días)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['conductores_con_licencia'] ?? 0; ?></h3>
                        <p>Conductores con Licencia</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon average">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($estadisticas['dias_promedio_vencimiento'] ?? 0, 0); ?></h3>
                        <p>Días Promedio Vencimiento</p>
                    </div>
                </div>
            </div>

            <!-- ALERTAS DE VENCIMIENTO -->
            <?php if (($estadisticas['por_vencer_7_dias'] ?? 0) > 0): ?>
            <div class="alert alert-warning" style="margin-top: 1rem;">
                <i class="fas fa-exclamation-circle"></i>
                <strong>Alerta:</strong> Tienes <?php echo $estadisticas['por_vencer_7_dias']; ?> licencias por vencer en menos de 7 días.
            </div>
            <?php endif; ?>

            <?php if (($estadisticas['licencias_vencidas'] ?? 0) > 0): ?>
            <div class="alert alert-danger" style="margin-top: 1rem;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Urgente:</strong> Tienes <?php echo $estadisticas['licencias_vencidas']; ?> licencias vencidas que requieren atención inmediata.
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- LISTA DE LICENCIAS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list-alt"></i> Licencias Registradas</h3>
            <div class="card-actions">
                <span class="badge primary"><?php echo count($licencias); ?> registros</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($licencias)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Conductor</th>
                            <th>N° Licencia</th>
                            <th>Categoría</th>
                            <th>Fechas</th>
                            <th>Estado</th>
                            <th>Días Restantes</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($licencias as $licencia): ?>
                        <?php
                            $dias_restantes = floor((strtotime($licencia['fecha_vencimiento']) - time()) / (60 * 60 * 24));
                            $clase_estado = '';
                            $icono_estado = '';
                            
                            if ($licencia['estado'] === 'vencida') {
                                $clase_estado = 'danger';
                                $icono_estado = 'fa-exclamation-triangle';
                            } elseif ($licencia['estado'] === 'activa') {
                                if ($dias_restantes <= 30) {
                                    $clase_estado = 'warning';
                                    $icono_estado = 'fa-clock';
                                } else {
                                    $clase_estado = 'success';
                                    $icono_estado = 'fa-check-circle';
                                }
                            } elseif ($licencia['estado'] === 'suspendida') {
                                $clase_estado = 'warning';
                                $icono_estado = 'fa-pause-circle';
                            } else {
                                $clase_estado = 'secondary';
                                $icono_estado = 'fa-ban';
                            }
                        ?>
                        <tr>
                            <td>
                                <div class="conductor-info">
                                    <div class="conductor-nombre">
                                        <strong><?php echo htmlspecialchars($licencia['conductor_nombre'] . ' ' . $licencia['conductor_apellido']); ?></strong>
                                    </div>
                                    <div class="conductor-dni">
                                        <small>DNI: <?php echo htmlspecialchars($licencia['conductor_dni']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="licencia-numero">
                                    <strong><?php echo htmlspecialchars($licencia['numero_licencia']); ?></strong>
                                </span>
                            </td>
                            <td>
                                <span class="categoria-badge">
                                    <?php echo htmlspecialchars($licencia['categoria']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="fechas-licencia">
                                    <div class="fecha-emision">
                                        <small>Emisión: <?php echo date('d/m/Y', strtotime($licencia['fecha_emision'])); ?></small>
                                    </div>
                                    <div class="fecha-vencimiento <?php echo $dias_restantes < 0 ? 'text-danger' : ($dias_restantes <= 30 ? 'text-warning' : ''); ?>">
                                        <small>Vence: <?php echo date('d/m/Y', strtotime($licencia['fecha_vencimiento'])); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge estado-<?php echo $clase_estado; ?>">
                                    <i class="fas <?php echo $icono_estado; ?>"></i>
                                    <?php echo ucfirst($licencia['estado']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($licencia['estado'] === 'activa'): ?>
                                    <span class="dias-restantes <?php echo $dias_restantes < 0 ? 'text-danger' : ($dias_restantes <= 30 ? 'text-warning' : 'text-success'); ?>">
                                        <strong><?php echo $dias_restantes; ?> días</strong>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <button type="button" class="btn-icon primary" 
                                        title="Editar Licencia"
                                        onclick="editarLicencia(<?php echo $licencia['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn-icon info" 
                                        title="Ver Detalles"
                                        onclick="verDetallesLicencia(<?php echo $licencia['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" class="btn-icon danger" 
                                        title="Eliminar Licencia"
                                        onclick="eliminarLicencia(<?php echo $licencia['id']; ?>, '<?php echo htmlspecialchars($licencia['numero_licencia']); ?>')">
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
                    <i class="fas fa-id-card"></i>
                </div>
                <h3>No hay licencias registradas</h3>
                <p>Comienza registrando la primera licencia de conductor</p>
                <div class="empty-actions">
                    <button type="button" class="btn btn-primary" onclick="mostrarModalLicencia()">
                        <i class="fas fa-id-card"></i>Registrar Primera Licencia
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL LICENCIA -->
<div id="modalLicencia" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="cerrarModalLicencia()"></div>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-id-card"></i> 
                    <span id="modalTituloLicencia">Registrar Nueva Licencia</span>
                </h3>
                <button type="button" class="modal-close" onclick="cerrarModalLicencia()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formLicencia" method="POST" class="modal-form">
                    <input type="hidden" name="licencia_id" id="licencia_id">
                    <input type="hidden" name="guardar_licencia" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="conductor_id" class="form-label">Conductor *</label>
                            <select id="conductor_id" name="conductor_id" class="form-control" required>
                                <option value="">Seleccionar conductor</option>
                                <?php foreach ($conductores as $conductor): ?>
                                <option value="<?php echo $conductor['id']; ?>" 
                                    <?php echo ($licencia_editar && $licencia_editar['conductor_id'] == $conductor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($conductor['nombre'] . ' ' . $conductor['apellido'] . ' - ' . $conductor['dni']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="numero_licencia" class="form-label">Número de Licencia *</label>
                            <input type="text" id="numero_licencia" name="numero_licencia" class="form-control" 
                                   placeholder="Ej: A12345678" required maxlength="20"
                                   value="<?php echo htmlspecialchars($licencia_editar['numero_licencia'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="categoria" class="form-label">Categoría *</label>
                            <select id="categoria" name="categoria" class="form-control" required>
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categorias as $key => $value): ?>
                                <option value="<?php echo $key; ?>" 
                                    <?php echo ($licencia_editar && $licencia_editar['categoria'] == $key) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($value); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="fecha_emision" class="form-label">Fecha de Emisión *</label>
                            <input type="date" id="fecha_emision" name="fecha_emision" class="form-control" 
                                   required
                                   value="<?php echo htmlspecialchars($licencia_editar['fecha_emision'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="fecha_vencimiento" class="form-label">Fecha de Vencimiento *</label>
                            <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" class="form-control" 
                                   required
                                   value="<?php echo htmlspecialchars($licencia_editar['fecha_vencimiento'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="estado" class="form-label">Estado</label>
                            <select id="estado" name="estado" class="form-control" required>
                                <option value="activa" <?php echo ($licencia_editar['estado'] ?? 'activa') === 'activa' ? 'selected' : ''; ?>>Activa</option>
                                <option value="vencida" <?php echo ($licencia_editar['estado'] ?? '') === 'vencida' ? 'selected' : ''; ?>>Vencida</option>
                                <option value="suspendida" <?php echo ($licencia_editar['estado'] ?? '') === 'suspendida' ? 'selected' : ''; ?>>Suspendida</option>
                                <option value="inactiva" <?php echo ($licencia_editar['estado'] ?? '') === 'inactiva' ? 'selected' : ''; ?>>Inactiva</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="restricciones" class="form-label">Restricciones/Observaciones</label>
                        <textarea id="restricciones" name="restricciones" class="form-control" 
                                  rows="3" placeholder="Restricciones médicas, observaciones, etc."
                                  maxlength="500"><?php echo htmlspecialchars($licencia_editar['restricciones'] ?? ''); ?></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModalLicencia()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" form="formLicencia" class="btn btn-primary">
                    <i class="fas fa-save"></i> <span id="textoBotonGuardar">Guardar Licencia</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- FORMULARIO OCULTO PARA ELIMINAR -->
<form id="formEliminarLicencia" method="POST" style="display: none;">
    <input type="hidden" name="licencia_id" id="licencia_eliminar_id">
    <input type="hidden" name="eliminar_licencia" value="1">
</form>

<style>
/* ESTILOS CSS CORREGIDOS PARA EL MODAL */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1050;
    overflow-x: hidden;
    overflow-y: auto;
    outline: 0;
}

.modal-backdrop {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 1040;
}

.modal-dialog {
    position: relative;
    width: auto;
    margin: 1.75rem auto;
    max-width: 600px;
    pointer-events: none;
    z-index: 1060;
}

.modal-content {
    position: relative;
    display: flex;
    flex-direction: column;
    width: 100%;
    pointer-events: auto;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    outline: 0;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--light);
    border-top-left-radius: 12px;
    border-top-right-radius: 12px;
}

.modal-title {
    margin: 0;
    color: var(--dark);
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--gray);
    cursor: pointer;
    padding: 0.5rem;
    border-radius: 6px;
    transition: all 0.3s ease;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-close:hover {
    background: var(--danger);
    color: white;
}

.modal-body {
    position: relative;
    flex: 1 1 auto;
    padding: 1.5rem;
}

.modal-form .form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.modal-form .form-group {
    display: flex;
    flex-direction: column;
    margin-bottom: 0;
}

.modal-form .form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.modal-form .form-control {
    padding: 0.875rem 1rem;
    border: 2px solid #e1e8ed;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    color: var(--dark);
    width: 100%;
    box-sizing: border-box;
}

.modal-form .form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1), 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    gap: 1rem;
}

.form-nota {
    margin-top: 1rem;
}

/* Resto de estilos permanecen iguales... */
.crud-container { margin-top: 1.5rem; width: 100%; }
.data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 0; }
.data-table th { background: var(--light); padding: 1rem; text-align: left; font-weight: 600; color: var(--dark); border-bottom: 2px solid var(--border); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
.data-table td { padding: 1rem; border-bottom: 1px solid var(--border); color: var(--dark); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover { background: rgba(243, 156, 18, 0.04); }
.action-buttons { display: flex; gap: 0.5rem; justify-content: center; }
.btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: var(--light); color: var(--dark); text-decoration: none; transition: all 0.3s ease; border: none; cursor: pointer; }
.btn-icon:hover { background: var(--primary); color: white; transform: translateY(-2px); }
.btn-icon.warning:hover { background: var(--warning); }
.btn-icon.danger:hover { background: var(--danger); }
.btn-icon.success:hover { background: var(--success); }
.btn-icon.info:hover { background: var(--info); }
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
.text-danger { color: var(--danger) !important; font-weight: 600; }
.text-warning { color: var(--warning) !important; font-weight: 600; }
.text-success { color: var(--success) !important; font-weight: 600; }
.text-info { color: var(--info) !important; font-weight: 600; }
.text-muted { color: var(--gray) !important; opacity: 0.7; }
.account-form .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
.account-form .form-group { display: flex; flex-direction: column; margin-bottom: 0; }
.account-form .form-group.compact { margin-bottom: 1rem; }
.account-form .form-group label { font-weight: 600; color: var(--dark); margin-bottom: 0.5rem; font-size: 0.9rem; transition: var(--transition); }
.account-form .form-group:focus-within label { color: var(--primary); }
.account-form .form-control { padding: 0.875rem 1rem; border: 2px solid #e1e8ed; border-radius: 10px; font-size: 0.95rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02); color: var(--dark); width: 100%; box-sizing: border-box; }
.account-form .form-control:hover { border-color: #c8d1d9; background: linear-gradient(135deg, #f5f7fa 0%, #ffffff 100%); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04); }
.account-form .form-control:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1), 0 4px 12px rgba(0, 0, 0, 0.08); transform: translateY(-1px); }
.account-form .form-actions { display: flex; gap: 1rem; justify-content: flex-start; padding-top: 1.5rem; border-top: 1px solid var(--border); margin-top: 1.5rem; }
.account-form .btn { padding: 0.875rem 1.5rem; border-radius: 10px; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease; border: 2px solid transparent; cursor: pointer; font-size: 0.9rem; }
.account-form .btn-primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-color: var(--primary); }
.account-form .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3); }
.account-form .btn-outline { background: transparent; color: var(--dark); border-color: var(--border); }
.account-form .btn-outline:hover { background: var(--light); border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
.alert { padding: 1rem 1.5rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; border: 1px solid transparent; }
.alert-success { background: rgba(39, 174, 96, 0.1); border-color: rgba(39, 174, 96, 0.2); color: var(--success); }
.alert-danger { background: rgba(231, 76, 60, 0.1); border-color: rgba(231, 76, 60, 0.2); color: var(--danger); }
.alert-info { background: rgba(52, 152, 219, 0.1); border-color: rgba(52, 152, 219, 0.2); color: var(--primary); }
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 1.5rem 0; border-bottom: 1px solid var(--border); }
.welcome-section h1 { margin: 0 0 0.5rem 0; color: var(--dark); font-size: 1.8rem; font-weight: 700; }
.dashboard-subtitle { margin: 0; color: var(--gray); font-size: 1rem; }
.header-actions { display: flex; gap: 1rem; }
.card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); overflow: hidden; margin-bottom: 1.5rem; }
.card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center; }
.card-header h3 { margin: 0; color: var(--dark); font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.card-body { padding: 1.5rem; }

/* ESTILOS ESPECÍFICOS PARA CONDUCTORES */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: white;
    border-radius: 10px;
    border: 1px solid var(--border);
    transition: all 0.3s ease;
}

.stat-card.mini {
    padding: 1rem;
    gap: 0.75rem;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.stat-card.mini .stat-icon {
    width: 50px;
    height: 50px;
    font-size: 1.25rem;
}

.stat-icon.primary { background: rgba(52, 152, 219, 0.15); color: var(--primary); }
.stat-icon.success { background: rgba(39, 174, 96, 0.15); color: var(--success); }
.stat-icon.warning { background: rgba(243, 156, 18, 0.15); color: var(--warning); }
.stat-icon.info { background: rgba(155, 89, 182, 0.15); color: #9b59b6; }
.stat-icon.average { background: rgba(230, 126, 34, 0.15); color: #e67e22; }
.stat-icon.danger { background: rgba(231, 76, 60, 0.15); color: var(--danger); }
.stat-icon.secondary { background: rgba(108, 117, 125, 0.15); color: #6c757d; }

.stat-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-card.mini .stat-info h3 {
    font-size: 1.25rem;
}

.stat-info p {
    margin: 0;
    color: var(--gray);
    font-size: 0.85rem;
}

.conductor-info .conductor-nombre {
    font-weight: 600;
    color: var(--dark);
}

.conductor-info .conductor-email {
    font-size: 0.8rem;
    color: var(--gray);
}

.dni-badge {
    background: #f8f9fa;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-family: monospace;
    font-weight: 600;
    color: var(--dark);
    border: 1px solid var(--border);
}

.contacto-info {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    color: var(--dark);
}

.ultimo-login {
    font-size: 0.85rem;
    color: var(--gray);
}

.fecha-registro {
    font-size: 0.85rem;
    color: var(--dark);
}

.stats-subgrid h4 {
    font-size: 1.1rem;
    font-weight: 600;
}

/* Responsive */
@media (max-width: 1024px) {
    .data-table { font-size: 0.85rem; }
    .account-form .form-grid { grid-template-columns: 1fr; gap: 1rem; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .modal-form .form-grid { grid-template-columns: 1fr; }
}

@media (max-width: 768px) {
    .dashboard-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .header-actions { width: 100%; justify-content: flex-start; }
    .data-table { font-size: 0.8rem; }
    .data-table th, .data-table td { padding: 0.75rem 0.5rem; }
    .action-buttons { flex-direction: column; gap: 0.25rem; }
    .btn-icon { width: 32px; height: 32px; }
    .account-form .form-actions { flex-direction: column; }
    .account-form .btn { width: 100%; justify-content: center; }
    .card-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .card-actions { align-self: flex-start; }
    .stats-grid { grid-template-columns: 1fr; }
    .stat-card { flex-direction: column; text-align: center; gap: 0.75rem; }
    .stat-icon { width: 50px; height: 50px; font-size: 1.25rem; }
    .empty-actions { flex-direction: column; }
    .modal-dialog { margin: 0.5rem; max-width: calc(100% - 1rem); }
    .modal-footer { flex-direction: column; }
    .modal-form .form-grid { grid-template-columns: 1fr; }
}

/* ESTILOS ESPECÍFICOS PARA FILTROS - CORREGIDOS */
.filter-form .form-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-form .form-group {
    margin-bottom: 0;
}

.filter-form .form-label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-size: 0.85rem;
    display: block;
}

.filter-form .form-control {
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 0.9rem;
    background: white;
    width: 100%;
    box-sizing: border-box;
    transition: border-color 0.3s ease;
}

.filter-form .form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(132, 6, 31, 0.1);
    transform: none;
}

.filter-form select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%23333' viewBox='0 0 16 16'%3E%3Cpath d='M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 12px;
    padding-right: 2.5rem;
}

.filter-form input[type="date"].form-control {
    position: relative;
}

.filter-form input[type="date"].form-control::-webkit-calendar-picker-indicator {
    position: absolute;
    right: 0.75rem;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
}

.filter-form .form-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-start;
    margin-top: 0;
    padding-top: 0;
    border-top: none;
}

.filter-form .btn {
    padding: 0.75rem 1.5rem;
    font-size: 0.9rem;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.filter-form .btn-primary {
    background: linear-gradient(135deg, var(--color-primario, #84061f), #a30827);
    border: 1px solid var(--color-primario, #84061f);
    color: white;
}

.filter-form .btn-primary:hover {
    background: linear-gradient(135deg, #a30827, #c00a2f);
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(132, 6, 31, 0.3);
}

.filter-form .btn-outline {
    background: white;
    border: 1px solid #ddd;
    color: var(--dark);
}

.filter-form .btn-outline:hover {
    background: #f8f9fa;
    border-color: var(--color-primario, #84061f);
    color: var(--color-primario, #84061f);
    transform: translateY(-1px);
}

/* Responsive para filtros */
@media (max-width: 1200px) {
    .filter-form .form-grid {
        grid-template-columns: 1fr 1fr;
    }
}

@media (max-width: 768px) {
    .filter-form .form-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-form .form-actions {
        flex-direction: column;
    }
    
    .filter-form .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// FUNCIONES JS PARA LICENCIAS
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de licencias cargada');
    
    // Si hay una licencia para editar, abrir modal automáticamente
    <?php if ($licencia_editar): ?>
    console.log('Editando licencia: <?php echo $licencia_editar['id']; ?>');
    mostrarModalLicencia(<?php echo $licencia_editar['id']; ?>);
    <?php endif; ?>

    // Validar formulario de licencia
    const formLicencia = document.getElementById('formLicencia');
    if (formLicencia) {
        formLicencia.addEventListener('submit', function(e) {
            return validarFormularioLicencia(e);
        });
    }

    // Calcular automáticamente estado basado en fechas
    const fechaVencimiento = document.getElementById('fecha_vencimiento');
    if (fechaVencimiento) {
        fechaVencimiento.addEventListener('change', function() {
            calcularEstadoLicencia();
        });
    }
});

// Modal para licencia
function mostrarModalLicencia(licenciaId = null) {
    const modal = document.getElementById('modalLicencia');
    const titulo = document.getElementById('modalTituloLicencia');
    const botonGuardar = document.getElementById('textoBotonGuardar');
    
    console.log('Mostrando modal para licencia:', licenciaId);
    
    if (licenciaId) {
        titulo.textContent = 'Editar Licencia';
        botonGuardar.textContent = 'Actualizar Licencia';
        document.getElementById('licencia_id').value = licenciaId;
    } else {
        titulo.textContent = 'Registrar Nueva Licencia';
        botonGuardar.textContent = 'Guardar Licencia';
        // Limpiar formulario si es nuevo
        document.getElementById('formLicencia').reset();
        document.getElementById('licencia_id').value = '';
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function cerrarModalLicencia() {
    const modal = document.getElementById('modalLicencia');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Redirigir sin parámetros GET para limpiar la edición
    if (window.location.search.includes('editar=')) {
        window.location.href = 'licencias.php';
    }
}

// Función para editar licencia (redirige con parámetro GET)
function editarLicencia(licenciaId) {
    console.log('Editando licencia ID:', licenciaId);
    window.location.href = 'licencias.php?editar=' + licenciaId;
}

// Función para ver detalles de licencia
function verDetallesLicencia(licenciaId) {
    console.log('Ver detalles de licencia ID:', licenciaId);
    // En una implementación real, esto abriría un modal con detalles completos
    alert('Funcionalidad de ver detalles en desarrollo para la licencia ID: ' + licenciaId);
}

// Función para eliminar licencia con confirmación
function eliminarLicencia(licenciaId, numeroLicencia) {
    if (confirm(`¿Está seguro de eliminar la licencia ${numeroLicencia}?\n\nEsta acción no se puede deshacer.`)) {
        document.getElementById('licencia_eliminar_id').value = licenciaId;
        document.getElementById('formEliminarLicencia').submit();
    }
}

// Validación del formulario antes de enviar
function validarFormularioLicencia(e) {
    const conductorId = document.getElementById('conductor_id').value;
    const numeroLicencia = document.getElementById('numero_licencia').value.trim();
    const categoria = document.getElementById('categoria').value;
    const fechaEmision = document.getElementById('fecha_emision').value;
    const fechaVencimiento = document.getElementById('fecha_vencimiento').value;
    
    console.log('Validando formulario de licencia');
    
    if (!conductorId || !numeroLicencia || !categoria || !fechaEmision || !fechaVencimiento) {
        e.preventDefault();
        alert('Por favor, complete todos los campos obligatorios.');
        return false;
    }
    
    // Validar fechas
    const fechaInicio = new Date(fechaEmision);
    const fechaFin = new Date(fechaVencimiento);
    
    if (fechaFin <= fechaInicio) {
        e.preventDefault();
        alert('La fecha de vencimiento debe ser posterior a la fecha de emisión.');
        return false;
    }
    
    // Validar que la fecha de vencimiento no sea en el pasado (solo advertencia)
    const hoy = new Date();
    if (fechaFin < hoy) {
        if (!confirm('La fecha de vencimiento es anterior a la fecha actual. ¿Desea continuar?')) {
            e.preventDefault();
            return false;
        }
    }
    
    console.log('Formulario válido, enviando...');
    return true;
}

// Calcular estado automáticamente basado en fechas
function calcularEstadoLicencia() {
    const fechaVencimiento = document.getElementById('fecha_vencimiento').value;
    const selectEstado = document.getElementById('estado');
    
    if (!fechaVencimiento) return;
    
    const vencimiento = new Date(fechaVencimiento);
    const hoy = new Date();
    
    if (vencimiento < hoy) {
        // Si la fecha ya pasó, sugerir estado "vencida"
        if (selectEstado.value === 'activa') {
            if (confirm('La fecha de vencimiento es anterior a hoy. ¿Desea cambiar el estado a "Vencida"?')) {
                selectEstado.value = 'vencida';
            }
        }
    } else {
        // Si la fecha es futura, sugerir estado "activa"
        if (selectEstado.value === 'vencida') {
            selectEstado.value = 'activa';
        }
    }
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalLicencia();
    }
});

// Función para generar datos de ejemplo (solo en modo demo)
function generarLicenciaDemo() {
    if (confirm('¿Generar licencia de ejemplo? Esto solo funciona en modo demo.')) {
        const conductores = document.getElementById('conductor_id').options;
        if (conductores.length > 1) {
            const categorias = ['B-I', 'B-IIa', 'B-IIb', 'C-I', 'C-II'];
            
            document.getElementById('conductor_id').selectedIndex = 1;
            document.getElementById('numero_licencia').value = 'B' + Math.floor(10000000 + Math.random() * 90000000);
            document.getElementById('categoria').value = categorias[Math.floor(Math.random() * categorias.length)];
            
            // Fecha de emisión hace 1-2 años
            const fechaEmision = new Date();
            fechaEmision.setFullYear(fechaEmision.getFullYear() - Math.floor(Math.random() * 2) - 1);
            document.getElementById('fecha_emision').value = fechaEmision.toISOString().split('T')[0];
            
            // Fecha de vencimiento en 1-3 años
            const fechaVencimiento = new Date(fechaEmision);
            fechaVencimiento.setFullYear(fechaVencimiento.getFullYear() + Math.floor(Math.random() * 3) + 1);
            document.getElementById('fecha_vencimiento').value = fechaVencimiento.toISOString().split('T')[0];
            
            alert('Datos de ejemplo generados. Puede modificarlos antes de guardar.');
        } else {
            alert('Primero debe tener conductores registrados.');
        }
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>