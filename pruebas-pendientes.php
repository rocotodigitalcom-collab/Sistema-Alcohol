<?php
// pruebas-pendientes.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Pruebas Pendientes';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'pruebas-pendientes.php' => 'Pruebas Pendientes'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// Obtener configuración del cliente
$configuracion = $db->fetchOne("
    SELECT limite_alcohol_permisible, nivel_advertencia, nivel_critico, unidad_medida,
           requerir_aprobacion_supervisor, intervalo_retest_minutos
    FROM configuraciones 
    WHERE cliente_id = ?
", [$cliente_id]);

// Obtener diferentes tipos de pruebas pendientes

// 1. Pruebas reprobadas sin re-test
$pruebas_sin_retest = $db->fetchAll("
    SELECT p.*, 
           a.nombre_activo as alcoholimetro_nombre,
           CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
           u_conductor.dni as conductor_dni,
           CONCAT(u_supervisor.nombre, ' ', u_supervisor.apellido) as supervisor_nombre,
           v.placa as vehiculo_placa
    FROM pruebas p
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
    LEFT JOIN usuarios u_supervisor ON p.supervisor_id = u_supervisor.id
    LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
    WHERE p.cliente_id = ? 
    AND p.resultado = 'reprobado'
    AND NOT EXISTS (
        SELECT 1 FROM pruebas pr 
        WHERE pr.prueba_padre_id = p.id AND pr.cliente_id = p.cliente_id
    )
    AND NOT EXISTS (
        SELECT 1 FROM solicitudes_retest sr 
        WHERE sr.prueba_original_id = p.id AND sr.estado = 'pendiente'
    )
    ORDER BY p.fecha_prueba DESC
    LIMIT 50
", [$cliente_id]);

// 2. Solicitudes de re-test pendientes
$solicitudes_retest_pendientes = $db->fetchAll("
    SELECT sr.*,
           p.nivel_alcohol as nivel_original,
           p.fecha_prueba as fecha_prueba_original,
           CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
           u_conductor.dni as conductor_dni,
           CONCAT(u_solicitante.nombre, ' ', u_solicitante.apellido) as solicitante_nombre,
           a.nombre_activo as alcoholimetro_nombre
    FROM solicitudes_retest sr
    LEFT JOIN pruebas p ON sr.prueba_original_id = p.id
    LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
    LEFT JOIN usuarios u_solicitante ON sr.solicitado_por = u_solicitante.id
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    WHERE p.cliente_id = ? 
    AND sr.estado = 'pendiente'
    ORDER BY sr.fecha_solicitud DESC
    LIMIT 50
", [$cliente_id]);

// 3. Pruebas que requieren aprobación del supervisor (si está configurado)
$pruebas_por_aprobar = [];
if ($configuracion['requerir_aprobacion_supervisor']) {
    $pruebas_por_aprobar = $db->fetchAll("
        SELECT p.*,
               a.nombre_activo as alcoholimetro_nombre,
               CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
               u_conductor.dni as conductor_dni,
               CONCAT(u_supervisor.nombre, ' ', u_supervisor.apellido) as supervisor_nombre,
               v.placa as vehiculo_placa
        FROM pruebas p
        LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
        LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
        LEFT JOIN usuarios u_supervisor ON p.supervisor_id = u_supervisor.id
        LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
        WHERE p.cliente_id = ? 
        AND p.aprobado_por_supervisor = 0
        AND p.resultado = 'reprobado'
        ORDER BY p.fecha_prueba DESC
        LIMIT 50
    ", [$cliente_id]);
}

// 4. Pruebas con observaciones pendientes (sin observaciones completadas)
$pruebas_sin_observaciones = $db->fetchAll("
    SELECT p.*,
           a.nombre_activo as alcoholimetro_nombre,
           CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
           u_conductor.dni as conductor_dni,
           CONCAT(u_supervisor.nombre, ' ', u_supervisor.apellido) as supervisor_nombre
    FROM pruebas p
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
    LEFT JOIN usuarios u_supervisor ON p.supervisor_id = u_supervisor.id
    WHERE p.cliente_id = ? 
    AND (p.observaciones IS NULL OR p.observaciones = '')
    AND p.resultado = 'reprobado'
    ORDER BY p.fecha_prueba DESC
    LIMIT 50
", [$cliente_id]);

// Calcular totales
$total_pendientes = count($pruebas_sin_retest) + count($solicitudes_retest_pendientes) + 
                   count($pruebas_por_aprobar) + count($pruebas_sin_observaciones);

// Procesar acciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['aprobar_retest'])) {
        $solicitud_id = $_POST['solicitud_id'];
        $accion = $_POST['accion_retest']; // 'aprobar' o 'rechazar'
        $observaciones = trim($_POST['observaciones_aprobacion'] ?? '');
        
        try {
            $nuevo_estado = $accion === 'aprobar' ? 'aprobado' : 'rechazado';
            
            $db->execute("
                UPDATE solicitudes_retest 
                SET estado = ?, aprobado_por = ?, fecha_resolucion = NOW(),
                    observaciones_aprobacion = ?
                WHERE id = ?
            ", [$nuevo_estado, $user_id, $observaciones, $solicitud_id]);
            
            $mensaje_exito = "Solicitud de re-test " . ($accion === 'aprobar' ? 'aprobada' : 'rechazada') . " correctamente";
            
            // Recargar datos
            $solicitudes_retest_pendientes = $db->fetchAll("
                SELECT sr.*,
                       p.nivel_alcohol as nivel_original,
                       p.fecha_prueba as fecha_prueba_original,
                       CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
                       u_conductor.dni as conductor_dni,
                       CONCAT(u_solicitante.nombre, ' ', u_solicitante.apellido) as solicitante_nombre,
                       a.nombre_activo as alcoholimetro_nombre
                FROM solicitudes_retest sr
                LEFT JOIN pruebas p ON sr.prueba_original_id = p.id
                LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
                LEFT JOIN usuarios u_solicitante ON sr.solicitado_por = u_solicitante.id
                LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
                WHERE p.cliente_id = ? 
                AND sr.estado = 'pendiente'
                ORDER BY sr.fecha_solicitud DESC
                LIMIT 50
            ", [$cliente_id]);
            
        } catch (Exception $e) {
            $mensaje_error = "Error al procesar la solicitud: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['agregar_observaciones'])) {
        $prueba_id = $_POST['prueba_id'];
        $observaciones = trim($_POST['nuevas_observaciones'] ?? '');
        
        try {
            $db->execute("
                UPDATE pruebas 
                SET observaciones = ?
                WHERE id = ? AND cliente_id = ?
            ", [$observaciones, $prueba_id, $cliente_id]);
            
            $mensaje_exito = "Observaciones agregadas correctamente";
            
            // Recargar datos
            $pruebas_sin_observaciones = $db->fetchAll("
                SELECT p.*,
                       a.nombre_activo as alcoholimetro_nombre,
                       CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
                       u_conductor.dni as conductor_dni,
                       CONCAT(u_supervisor.nombre, ' ', u_supervisor.apellido) as supervisor_nombre
                FROM pruebas p
                LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
                LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
                LEFT JOIN usuarios u_supervisor ON p.supervisor_id = u_supervisor.id
                WHERE p.cliente_id = ? 
                AND (p.observaciones IS NULL OR p.observaciones = '')
                AND p.resultado = 'reprobado'
                ORDER BY p.fecha_prueba DESC
                LIMIT 50
            ", [$cliente_id]);
            
        } catch (Exception $e) {
            $mensaje_error = "Error al agregar observaciones: " . $e->getMessage();
        }
    }
}
?>

<div class="content-body">
    <!-- HEADER IDÉNTICO -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona las pruebas que requieren atención inmediata</p>
        </div>
        <div class="header-actions">
            <a href="prueba-rapida.php" class="btn btn-primary">
                <i class="fas fa-vial"></i>Nueva Prueba
            </a>
        </div>
    </div>

    <!-- ALERTAS MISMO ESTILO -->
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
        <!-- RESUMEN DE PENDIENTES -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-tasks"></i> Resumen de Pendientes</h3>
            </div>
            <div class="card-body">
                <div class="pendientes-summary">
                    <div class="summary-grid">
                        <div class="summary-item <?php echo count($pruebas_sin_retest) > 0 ? 'pending' : 'completed'; ?>">
                            <div class="summary-icon">
                                <i class="fas fa-redo"></i>
                            </div>
                            <div class="summary-info">
                                <h3><?php echo count($pruebas_sin_retest); ?></h3>
                                <p>Pruebas sin Re-test</p>
                                <small>Reprobadas sin re-test registrado</small>
                            </div>
                        </div>
                        <div class="summary-item <?php echo count($solicitudes_retest_pendientes) > 0 ? 'pending' : 'completed'; ?>">
                            <div class="summary-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="summary-info">
                                <h3><?php echo count($solicitudes_retest_pendientes); ?></h3>
                                <p>Solicitudes de Re-test</p>
                                <small>Esperando aprobación</small>
                            </div>
                        </div>
                        <div class="summary-item <?php echo count($pruebas_por_aprobar) > 0 ? 'pending' : 'completed'; ?>">
                            <div class="summary-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="summary-info">
                                <h3><?php echo count($pruebas_por_aprobar); ?></h3>
                                <p>Por Aprobar</p>
                                <small>Esperando validación</small>
                            </div>
                        </div>
                        <div class="summary-item <?php echo count($pruebas_sin_observaciones) > 0 ? 'pending' : 'completed'; ?>">
                            <div class="summary-icon">
                                <i class="fas fa-comment"></i>
                            </div>
                            <div class="summary-info">
                                <h3><?php echo count($pruebas_sin_observaciones); ?></h3>
                                <p>Sin Observaciones</p>
                                <small>Falta información</small>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($total_pendientes === 0): ?>
                    <div class="all-caught-up">
                        <div class="caught-up-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h3>¡Todo al día!</h3>
                        <p>No hay pruebas pendientes que requieran atención inmediata.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PRUEBAS SIN RE-TEST -->
        <?php if (!empty($pruebas_sin_retest)): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-redo"></i> Pruebas Reprobadas sin Re-test</h3>
                <div class="card-actions">
                    <span class="badge warning"><?php echo count($pruebas_sin_retest); ?> pendientes</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Conductor</th>
                                <th>Alcoholímetro</th>
                                <th>Nivel Alcohol</th>
                                <th>Tiempo Transcurrido</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pruebas_sin_retest as $prueba): 
                                $tiempo_transcurrido = time() - strtotime($prueba['fecha_prueba']);
                                $horas_transcurridas = floor($tiempo_transcurrido / 3600);
                                $dias_transcurridos = floor($horas_transcurridas / 24);
                            ?>
                            <tr>
                                <td>
                                    <div class="fecha-hora">
                                        <div class="fecha"><?php echo date('d/m/Y', strtotime($prueba['fecha_prueba'])); ?></div>
                                        <div class="hora"><?php echo date('H:i', strtotime($prueba['fecha_prueba'])); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($prueba['conductor_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($prueba['alcoholimetro_nombre']); ?></td>
                                <td>
                                    <span class="nivel-alcohol danger">
                                        <?php echo number_format($prueba['nivel_alcohol'], 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="tiempo-transcurrido <?php echo $dias_transcurridos > 1 ? 'text-danger' : 'text-warning'; ?>">
                                        <?php echo $dias_transcurridos > 0 ? $dias_transcurridos . ' días' : $horas_transcurridas . ' horas'; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="nueva-prueba.php?editar=<?php echo $prueba['id']; ?>" class="btn-icon" title="Ver Detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn-icon warning" 
                                            title="Solicitar Re-test" 
                                            onclick="solicitarRetest(<?php echo $prueba['id']; ?>, '<?php echo htmlspecialchars($prueba['conductor_nombre']); ?>')">
                                        <i class="fas fa-redo"></i>
                                    </button>
                                    <a href="prueba-rapida.php?conductor=<?php echo $prueba['conductor_id']; ?>&alcoholimetro=<?php echo $prueba['alcoholimetro_id']; ?>" 
                                       class="btn-icon success" title="Realizar Re-test">
                                        <i class="fas fa-vial"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- SOLICITUDES DE RE-TEST PENDIENTES -->
        <?php if (!empty($solicitudes_retest_pendientes)): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clock"></i> Solicitudes de Re-test Pendientes</h3>
                <div class="card-actions">
                    <span class="badge warning"><?php echo count($solicitudes_retest_pendientes); ?> pendientes</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha Solicitud</th>
                                <th>Conductor</th>
                                <th>Solicitado Por</th>
                                <th>Nivel Original</th>
                                <th>Motivo</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes_retest_pendientes as $solicitud): ?>
                            <tr>
                                <td>
                                    <div class="fecha-hora">
                                        <div class="fecha"><?php echo date('d/m/Y', strtotime($solicitud['fecha_solicitud'])); ?></div>
                                        <div class="hora"><?php echo date('H:i', strtotime($solicitud['fecha_solicitud'])); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($solicitud['conductor_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?></td>
                                <td>
                                    <span class="nivel-alcohol danger">
                                        <?php echo number_format($solicitud['nivel_original'], 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="motivo-solicitud" title="<?php echo htmlspecialchars($solicitud['motivo']); ?>">
                                        <?php echo strlen($solicitud['motivo']) > 50 ? substr($solicitud['motivo'], 0, 50) . '...' : $solicitud['motivo']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-icon success" 
                                            title="Aprobar Re-test"
                                            onclick="mostrarModalAprobacion(<?php echo $solicitud['id']; ?>, 'aprobar')">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn-icon danger" 
                                            title="Rechazar Re-test"
                                            onclick="mostrarModalAprobacion(<?php echo $solicitud['id']; ?>, 'rechazar')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <a href="nueva-prueba.php?editar=<?php echo $solicitud['prueba_original_id']; ?>" class="btn-icon" title="Ver Prueba Original">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PRUEBAS POR APROBAR -->
        <?php if (!empty($pruebas_por_aprobar)): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-user-check"></i> Pruebas por Aprobar</h3>
                <div class="card-actions">
                    <span class="badge warning"><?php echo count($pruebas_por_aprobar); ?> pendientes</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Conductor</th>
                                <th>Supervisor</th>
                                <th>Nivel Alcohol</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pruebas_por_aprobar as $prueba): ?>
                            <tr>
                                <td>
                                    <div class="fecha-hora">
                                        <div class="fecha"><?php echo date('d/m/Y', strtotime($prueba['fecha_prueba'])); ?></div>
                                        <div class="hora"><?php echo date('H:i', strtotime($prueba['fecha_prueba'])); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($prueba['conductor_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($prueba['supervisor_nombre']); ?></td>
                                <td>
                                    <span class="nivel-alcohol danger">
                                        <?php echo number_format($prueba['nivel_alcohol'], 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="nueva-prueba.php?editar=<?php echo $prueba['id']; ?>" class="btn-icon" title="Ver Detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn-icon success" 
                                            title="Aprobar Prueba"
                                            onclick="aprobarPrueba(<?php echo $prueba['id']; ?>)">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <button type="button" class="btn-icon warning" 
                                            title="Solicitar Aclaraciones"
                                            onclick="solicitarAclaraciones(<?php echo $prueba['id']; ?>)">
                                        <i class="fas fa-comment-medical"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- PRUEBAS SIN OBSERVACIONES -->
        <?php if (!empty($pruebas_sin_observaciones)): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-comment"></i> Pruebas sin Observaciones</h3>
                <div class="card-actions">
                    <span class="badge warning"><?php echo count($pruebas_sin_observaciones); ?> pendientes</span>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Conductor</th>
                                <th>Supervisor</th>
                                <th>Nivel Alcohol</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pruebas_sin_observaciones as $prueba): ?>
                            <tr>
                                <td>
                                    <div class="fecha-hora">
                                        <div class="fecha"><?php echo date('d/m/Y', strtotime($prueba['fecha_prueba'])); ?></div>
                                        <div class="hora"><?php echo date('H:i', strtotime($prueba['fecha_prueba'])); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($prueba['conductor_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($prueba['supervisor_nombre']); ?></td>
                                <td>
                                    <span class="nivel-alcohol danger">
                                        <?php echo number_format($prueba['nivel_alcohol'], 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="nueva-prueba.php?editar=<?php echo $prueba['id']; ?>" class="btn-icon" title="Ver Detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn-icon primary" 
                                            title="Agregar Observaciones"
                                            onclick="mostrarModalObservaciones(<?php echo $prueba['id']; ?>, '<?php echo htmlspecialchars($prueba['conductor_nombre']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- MODALES PARA ACCIONES -->
<!-- Modal para aprobación de re-test -->
<div id="modalAprobacionRetest" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> <span id="modalTitulo">Aprobar Re-test</span></h3>
            <button type="button" class="modal-close" onclick="cerrarModalAprobacion()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="formAprobacionRetest" method="POST">
                <input type="hidden" name="solicitud_id" id="solicitud_aprobacion_id">
                <input type="hidden" name="accion_retest" id="accion_retest">
                <div class="form-group">
                    <label for="observaciones_aprobacion">Observaciones</label>
                    <textarea id="observaciones_aprobacion" name="observaciones_aprobacion" class="form-control" rows="4" 
                              placeholder="Agregue observaciones sobre la decisión..."></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="cerrarModalAprobacion()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" form="formAprobacionRetest" name="aprobar_retest" class="btn" id="botonAprobacion">
                <i class="fas fa-paper-plane"></i> <span id="textoBoton">Aprobar</span>
            </button>
        </div>
    </div>
</div>

<!-- Modal para observaciones -->
<div id="modalObservaciones" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Agregar Observaciones</h3>
            <button type="button" class="modal-close" onclick="cerrarModalObservaciones()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="formObservaciones" method="POST">
                <input type="hidden" name="prueba_id" id="prueba_observaciones_id">
                <div class="form-group">
                    <label for="conductor_observaciones">Conductor</label>
                    <input type="text" id="conductor_observaciones" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="nuevas_observaciones">Observaciones *</label>
                    <textarea id="nuevas_observaciones" name="nuevas_observaciones" class="form-control" rows="4" 
                              placeholder="Describa las observaciones de la prueba..." required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="cerrarModalObservaciones()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" form="formObservaciones" name="agregar_observaciones" class="btn btn-primary">
                <i class="fas fa-save"></i> Guardar Observaciones
            </button>
        </div>
    </div>
</div>

<!-- ESTILOS CSS INTEGRADOS (Mismo patrón + mejoras para pruebas pendientes) -->
<style>
/* [Todos los estilos CSS del patrón aquí - idénticos a los módulos anteriores] */
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
.btn-icon.primary:hover { background: var(--primary); }
.status-badge { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; display: inline-block; text-align: center; min-width: 80px; }
.badge { padding: 0.4rem 0.8rem; background: linear-gradient(135deg, var(--warning), #e67e22); color: white; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.badge.warning { background: linear-gradient(135deg, var(--warning), #e67e22); }
.table-responsive { overflow-x: auto; border-radius: 12px; }
.empty-state { text-align: center; padding: 4rem 2rem; color: var(--gray); }
.empty-icon { font-size: 4rem; color: var(--light); margin-bottom: 1.5rem; opacity: 0.7; }
.empty-state h3 { color: var(--dark); margin-bottom: 0.5rem; font-weight: 600; }
.empty-state p { margin-bottom: 2rem; font-size: 1rem; opacity: 0.8; }
.empty-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
.text-danger { color: var(--danger) !important; font-weight: 600; }
.text-warning { color: var(--warning) !important; font-weight: 600; }
.text-success { color: var(--success) !important; font-weight: 600; }
.text-muted { color: var(--gray) !important; opacity: 0.7; }
.account-form .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
.account-form .form-group { display: flex; flex-direction: column; margin-bottom: 0; }
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
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 1.5rem 0; border-bottom: 1px solid var(--border); }
.welcome-section h1 { margin: 0 0 0.5rem 0; color: var(--dark); font-size: 1.8rem; font-weight: 700; }
.dashboard-subtitle { margin: 0; color: var(--gray); font-size: 1rem; }
.header-actions { display: flex; gap: 1rem; }
.card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); overflow: hidden; margin-bottom: 1.5rem; }
.card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center; }
.card-header h3 { margin: 0; color: var(--dark); font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.card-body { padding: 1.5rem; }

/* ESTILOS ESPECÍFICOS PARA PRUEBAS PENDIENTES */
.pendientes-summary {
    padding: 1rem 0;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: 12px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
    background: white;
}

.summary-item.pending {
    border-color: var(--warning);
    background: rgba(243, 156, 18, 0.05);
}

.summary-item.completed {
    border-color: var(--success);
    background: rgba(39, 174, 96, 0.05);
    opacity: 0.7;
}

.summary-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 15px rgba(0, 0, 0, 0.1);
}

.summary-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.summary-item.pending .summary-icon {
    background: rgba(243, 156, 18, 0.15);
    color: var(--warning);
}

.summary-item.completed .summary-icon {
    background: rgba(39, 174, 96, 0.15);
    color: var(--success);
}

.summary-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.8rem;
    font-weight: 700;
}

.summary-item.pending .summary-info h3 {
    color: var(--warning);
}

.summary-item.completed .summary-info h3 {
    color: var(--success);
}

.summary-info p {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
    color: var(--dark);
}

.summary-info small {
    color: var(--gray);
    font-size: 0.75rem;
}

.all-caught-up {
    text-align: center;
    padding: 3rem 2rem;
    background: rgba(39, 174, 96, 0.05);
    border-radius: 12px;
    border: 2px dashed rgba(39, 174, 96, 0.3);
}

.caught-up-icon {
    font-size: 4rem;
    color: var(--success);
    margin-bottom: 1.5rem;
    opacity: 0.7;
}

.all-caught-up h3 {
    margin: 0 0 1rem 0;
    color: var(--success);
    font-weight: 600;
}

.all-caught-up p {
    margin: 0;
    color: var(--gray);
    font-size: 1rem;
}

/* Estilos específicos de la tabla */
.fecha-hora .fecha {
    font-weight: 600;
    color: var(--dark);
}

.fecha-hora .hora {
    font-size: 0.8rem;
    color: var(--gray);
}

.nivel-alcohol {
    font-weight: 700;
}

.nivel-alcohol.danger { color: var(--danger); }

.tiempo-transcurrido {
    font-weight: 600;
    font-size: 0.85rem;
}

.motivo-solicitud {
    cursor: help;
    color: var(--dark);
    font-size: 0.9rem;
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1000;
    align-items: center;
    justify-content: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow: auto;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--light);
}

.modal-header h3 {
    margin: 0;
    color: var(--dark);
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
}

.modal-close:hover {
    background: var(--danger);
    color: white;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

/* Responsive */
@media (max-width: 1024px) {
    .data-table { font-size: 0.85rem; }
    .summary-grid { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 768px) {
    .dashboard-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .header-actions { width: 100%; justify-content: flex-start; }
    .data-table { font-size: 0.8rem; }
    .data-table th, .data-table td { padding: 0.75rem 0.5rem; }
    .action-buttons { flex-direction: column; gap: 0.25rem; }
    .btn-icon { width: 32px; height: 32px; }
    .card-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
    .card-actions { align-self: flex-start; }
    .summary-grid { grid-template-columns: 1fr; }
    .summary-item { flex-direction: column; text-align: center; gap: 0.75rem; }
    .summary-icon { width: 50px; height: 50px; font-size: 1.25rem; }
    .empty-actions { flex-direction: column; }
    .modal-content { width: 95%; margin: 1rem; }
    .modal-footer { flex-direction: column; }
}
</style>

<script>
// FUNCIONES JS PARA PRUEBAS PENDIENTES
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar contadores en tiempo real
    actualizarContadores();
});

function actualizarContadores() {
    // Podríamos implementar actualización en tiempo real con AJAX
    // Por ahora solo mostramos los contadores estáticos
    const totalPendientes = <?php echo $total_pendientes; ?>;
    
    if (totalPendientes > 0) {
        document.title = `(${totalPendientes}) ${document.title}`;
    }
}

// Modal para aprobación de re-test
function mostrarModalAprobacion(solicitudId, accion) {
    const modal = document.getElementById('modalAprobacionRetest');
    const titulo = document.getElementById('modalTitulo');
    const boton = document.getElementById('botonAprobacion');
    const textoBoton = document.getElementById('textoBoton');
    
    document.getElementById('solicitud_aprobacion_id').value = solicitudId;
    document.getElementById('accion_retest').value = accion;
    document.getElementById('observaciones_aprobacion').value = '';
    
    if (accion === 'aprobar') {
        titulo.textContent = 'Aprobar Re-test';
        textoBoton.textContent = 'Aprobar';
        boton.className = 'btn btn-success';
    } else {
        titulo.textContent = 'Rechazar Re-test';
        textoBoton.textContent = 'Rechazar';
        boton.className = 'btn btn-danger';
    }
    
    modal.classList.add('show');
}

function cerrarModalAprobacion() {
    document.getElementById('modalAprobacionRetest').classList.remove('show');
}

// Modal para observaciones
function mostrarModalObservaciones(pruebaId, conductorNombre) {
    const modal = document.getElementById('modalObservaciones');
    
    document.getElementById('prueba_observaciones_id').value = pruebaId;
    document.getElementById('conductor_observaciones').value = conductorNombre;
    document.getElementById('nuevas_observaciones').value = '';
    
    modal.classList.add('show');
}

function cerrarModalObservaciones() {
    document.getElementById('modalObservaciones').classList.remove('show');
}

// Cerrar modales al hacer clic fuera
document.addEventListener('click', function(event) {
    const modals = ['modalAprobacionRetest', 'modalObservaciones'];
    modals.forEach(modalId => {
        const modal = document.getElementById(modalId);
        if (event.target === modal) {
            modal.classList.remove('show');
        }
    });
});

// Funciones de acción
function solicitarRetest(pruebaId, conductorNombre) {
    if (confirm(`¿Solicitar re-test para el conductor "${conductorNombre}"?`)) {
        // Redirigir a la página de solicitud de re-test
        window.location.href = `solicitar-retest.php?prueba_id=${pruebaId}`;
    }
}

function aprobarPrueba(pruebaId) {
    if (confirm('¿Está seguro de aprobar esta prueba?')) {
        // Aquí iría la lógica para aprobar la prueba
        alert('Función de aprobación de prueba disponible próximamente.');
    }
}

function solicitarAclaraciones(pruebaId) {
    const observaciones = prompt('Ingrese las aclaraciones requeridas:');
    if (observaciones) {
        alert(`Aclaraciones solicitadas para la prueba ${pruebaId}: ${observaciones}`);
    }
}

// Función para marcar como resuelto
function marcarComoResuelto(tipo, id) {
    if (confirm('¿Marcar este elemento como resuelto?')) {
        // Aquí iría la lógica para marcar como resuelto
        alert(`Elemento ${id} del tipo ${tipo} marcado como resuelto.`);
        location.reload();
    }
}

// Función para filtrar por tipo de pendiente
function filtrarPorTipo(tipo) {
    const elementos = document.querySelectorAll('.card');
    elementos.forEach(elemento => {
        if (tipo === 'todos') {
            elemento.style.display = 'block';
        } else {
            const header = elemento.querySelector('.card-header h3');
            if (header && header.textContent.toLowerCase().includes(tipo)) {
                elemento.style.display = 'block';
            } else {
                elemento.style.display = 'none';
            }
        }
    });
}

// Función para exportar reporte de pendientes
function exportarPendientes() {
    if (confirm('¿Exportar reporte de pruebas pendientes?')) {
        alert('Generando reporte de pruebas pendientes...');
        // Aquí iría la lógica de exportación
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>