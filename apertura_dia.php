<?php
// apertura_dia.php
// ============================================================================
// APERTURA DE DÍA - Gestión de Jornadas de Pruebas de Alcoholemia
// ============================================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Apertura de Día';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'apertura_dia.php' => 'Apertura de Día'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// ============================================================================
// OBTENER DATOS INICIALES
// ============================================================================

// Ubicaciones disponibles
$ubicaciones = $db->fetchAll("
    SELECT id, nombre_ubicacion, tipo 
    FROM ubicaciones_cliente 
    WHERE cliente_id = ? AND estado = 1 
    ORDER BY nombre_ubicacion
", [$cliente_id]);

// Jornada activa de hoy
$jornada_activa = $db->fetchOne("
    SELECT j.*, 
           uc.nombre_ubicacion,
           CONCAT(u.nombre, ' ', u.apellido) as operador_nombre
    FROM jornadas_prueba j
    LEFT JOIN ubicaciones_cliente uc ON j.ubicacion_id = uc.id
    LEFT JOIN usuarios u ON j.operador_id = u.id
    WHERE j.cliente_id = ? 
      AND j.fecha = CURDATE() 
      AND j.estado IN ('abierta', 'pendiente_cierre')
    ORDER BY j.id DESC 
    LIMIT 1
", [$cliente_id]);

// Contar protocolos pendientes
$protocolos_pendientes = 0;
if ($jornada_activa && $jornada_activa['pruebas_positivas'] > 0) {
    $pendientes = $db->fetchOne("
        SELECT COUNT(*) as total 
        FROM pruebas_protocolo pp
        INNER JOIN pruebas p ON pp.prueba_alcohol_id = p.id
        WHERE p.cliente_id = ? 
          AND DATE(p.fecha_prueba) = ? 
          AND p.resultado = 'reprobado' 
          AND pp.completada = 0
    ", [$cliente_id, $jornada_activa['fecha']]);
    $protocolos_pendientes = intval($pendientes['total'] ?? 0);
}

// Estadísticas del mes
$estadisticas_mes = $db->fetchOne("
    SELECT 
        COUNT(*) as total_jornadas,
        COALESCE(SUM(total_pruebas), 0) as total_pruebas,
        COALESCE(SUM(pruebas_negativas), 0) as total_negativos,
        COALESCE(SUM(pruebas_positivas), 0) as total_positivos,
        SUM(CASE WHEN estado = 'cerrada' THEN 1 ELSE 0 END) as jornadas_cerradas,
        SUM(CASE WHEN estado IN ('abierta', 'pendiente_cierre') THEN 1 ELSE 0 END) as jornadas_abiertas
    FROM jornadas_prueba 
    WHERE cliente_id = ? 
      AND MONTH(fecha) = MONTH(CURDATE()) 
      AND YEAR(fecha) = YEAR(CURDATE())
", [$cliente_id]);

// Historial de jornadas
$historial_jornadas = $db->fetchAll("
    SELECT j.*, 
           uc.nombre_ubicacion,
           CONCAT(u.nombre, ' ', u.apellido) as operador_nombre
    FROM jornadas_prueba j
    LEFT JOIN ubicaciones_cliente uc ON j.ubicacion_id = uc.id
    LEFT JOIN usuarios u ON j.operador_id = u.id
    WHERE j.cliente_id = ?
    ORDER BY j.fecha DESC, j.hora_inicio DESC
    LIMIT 30
", [$cliente_id]);

// Último número de prueba
$ultima_prueba = $db->fetchOne("
    SELECT numero_prueba_fin 
    FROM jornadas_prueba 
    WHERE cliente_id = ? AND estado = 'cerrada' 
    ORDER BY fecha DESC, id DESC 
    LIMIT 1
", [$cliente_id]);
$siguiente_numero = ($ultima_prueba['numero_prueba_fin'] ?? 0) + 1;

// ============================================================================
// PROCESAR ACCIONES POST
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ABRIR NUEVA JORNADA
    if (isset($_POST['abrir_jornada'])) {
        $ubicacion_id = $_POST['ubicacion_id'] ?? null;
        $numero_inicio = intval($_POST['numero_prueba_inicio'] ?? 1);
        $observaciones = trim($_POST['observaciones'] ?? '');

        try {
            $existe = $db->fetchOne("
                SELECT id FROM jornadas_prueba 
                WHERE cliente_id = ? AND fecha = CURDATE() AND ubicacion_id = ? 
                  AND estado IN ('abierta', 'pendiente_cierre')
            ", [$cliente_id, $ubicacion_id]);

            if ($existe) {
                throw new Exception("Ya existe una jornada abierta para hoy en esta ubicación");
            }

            $db->execute("
                INSERT INTO jornadas_prueba 
                (cliente_id, operador_id, ubicacion_id, fecha, hora_inicio, numero_prueba_inicio, observaciones, estado)
                VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?, 'abierta')
            ", [$cliente_id, $user_id, $ubicacion_id, $numero_inicio, $observaciones]);

            $jornada_id = $db->lastInsertId();

            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'ABRIR_JORNADA', 'jornadas_prueba', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $jornada_id, "Jornada #$jornada_id abierta", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT'] ?? '']);

            $mensaje_exito = "✅ Jornada abierta correctamente. ¡Puede iniciar las pruebas!";

            $jornada_activa = $db->fetchOne("
                SELECT j.*, uc.nombre_ubicacion, CONCAT(u.nombre, ' ', u.apellido) as operador_nombre
                FROM jornadas_prueba j
                LEFT JOIN ubicaciones_cliente uc ON j.ubicacion_id = uc.id
                LEFT JOIN usuarios u ON j.operador_id = u.id
                WHERE j.id = ?
            ", [$jornada_id]);

        } catch (Exception $e) {
            $mensaje_error = "Error: " . $e->getMessage();
        }
    }

    // REGISTRAR RESULTADOS
    if (isset($_POST['registrar_resultados'])) {
        $jornada_id = intval($_POST['jornada_id'] ?? 0);
        $negativos = intval($_POST['pruebas_negativas'] ?? 0);
        $positivos = intval($_POST['pruebas_positivas'] ?? 0);
        $numero_fin = intval($_POST['numero_prueba_fin'] ?? 0);
        $observaciones = trim($_POST['observaciones'] ?? '');

        try {
            $jornada = $db->fetchOne("
                SELECT * FROM jornadas_prueba 
                WHERE id = ? AND cliente_id = ? AND estado IN ('abierta', 'pendiente_cierre')
            ", [$jornada_id, $cliente_id]);

            if (!$jornada) {
                throw new Exception("Jornada no encontrada o ya está cerrada");
            }

            $total = $negativos + $positivos;
            $nuevo_estado = 'abierta';
            $hora_fin = null;

            if ($positivos > 0) {
                $nuevo_estado = 'pendiente_cierre';
                $hora_fin = date('H:i:s');
            }

            $db->execute("
                UPDATE jornadas_prueba SET 
                    pruebas_negativas = ?,
                    pruebas_positivas = ?,
                    total_pruebas = ?,
                    numero_prueba_fin = ?,
                    hora_fin = ?,
                    protocolos_pendientes = ?,
                    observaciones = ?,
                    estado = ?
                WHERE id = ?
            ", [$negativos, $positivos, $total, $numero_fin ?: null, $hora_fin, $positivos, $observaciones, $nuevo_estado, $jornada_id]);

            $mensaje_exito = "✅ Resultados guardados correctamente.";

            $jornada_activa = $db->fetchOne("
                SELECT j.*, uc.nombre_ubicacion, CONCAT(u.nombre, ' ', u.apellido) as operador_nombre
                FROM jornadas_prueba j
                LEFT JOIN ubicaciones_cliente uc ON j.ubicacion_id = uc.id
                LEFT JOIN usuarios u ON j.operador_id = u.id
                WHERE j.id = ?
            ", [$jornada_id]);

            // Recalcular pendientes
            if ($positivos > 0) {
                $pendientes = $db->fetchOne("
                    SELECT COUNT(*) as total FROM pruebas_protocolo pp
                    INNER JOIN pruebas p ON pp.prueba_alcohol_id = p.id
                    WHERE p.cliente_id = ? AND DATE(p.fecha_prueba) = ? 
                      AND p.resultado = 'reprobado' AND pp.completada = 0
                ", [$cliente_id, $jornada['fecha']]);
                $protocolos_pendientes = intval($pendientes['total'] ?? $positivos);
            }

        } catch (Exception $e) {
            $mensaje_error = "Error: " . $e->getMessage();
        }
    }

    // CERRAR JORNADA
    if (isset($_POST['cerrar_jornada'])) {
        $jornada_id = intval($_POST['jornada_id'] ?? 0);

        try {
            $jornada = $db->fetchOne("
                SELECT * FROM jornadas_prueba WHERE id = ? AND cliente_id = ?
            ", [$jornada_id, $cliente_id]);

            if (!$jornada) {
                throw new Exception("Jornada no encontrada");
            }

            if ($jornada['pruebas_positivas'] > 0) {
                $pendientes = $db->fetchOne("
                    SELECT COUNT(*) as total FROM pruebas_protocolo pp
                    INNER JOIN pruebas p ON pp.prueba_alcohol_id = p.id
                    WHERE p.cliente_id = ? AND DATE(p.fecha_prueba) = ? 
                      AND p.resultado = 'reprobado' AND pp.completada = 0
                ", [$cliente_id, $jornada['fecha']]);

                if (intval($pendientes['total'] ?? 0) > 0) {
                    throw new Exception("No se puede cerrar. Hay " . $pendientes['total'] . " protocolo(s) pendiente(s).");
                }
            }

            $db->execute("
                UPDATE jornadas_prueba SET 
                    estado = 'cerrada',
                    hora_fin = COALESCE(hora_fin, CURTIME()),
                    protocolos_pendientes = 0
                WHERE id = ?
            ", [$jornada_id]);

            $mensaje_exito = "✅ Jornada cerrada correctamente.";
            $jornada_activa = null;

        } catch (Exception $e) {
            $mensaje_error = $e->getMessage();
        }
    }

    // CANCELAR JORNADA
    if (isset($_POST['cancelar_jornada'])) {
        $jornada_id = intval($_POST['jornada_id'] ?? 0);
        $motivo = trim($_POST['motivo_cancelacion'] ?? 'Sin motivo');

        try {
            $db->execute("
                UPDATE jornadas_prueba SET 
                    estado = 'cancelada',
                    hora_fin = CURTIME(),
                    observaciones = CONCAT(COALESCE(observaciones, ''), ' [CANCELADA] ', ?)
                WHERE id = ? AND cliente_id = ?
            ", [$motivo, $jornada_id, $cliente_id]);

            $mensaje_exito = "⚠️ Jornada cancelada.";
            $jornada_activa = null;

        } catch (Exception $e) {
            $mensaje_error = "Error: " . $e->getMessage();
        }
    }

    // Recargar historial
    $historial_jornadas = $db->fetchAll("
        SELECT j.*, uc.nombre_ubicacion, CONCAT(u.nombre, ' ', u.apellido) as operador_nombre
        FROM jornadas_prueba j
        LEFT JOIN ubicaciones_cliente uc ON j.ubicacion_id = uc.id
        LEFT JOIN usuarios u ON j.operador_id = u.id
        WHERE j.cliente_id = ?
        ORDER BY j.fecha DESC, j.hora_inicio DESC
        LIMIT 30
    ", [$cliente_id]);

    $estadisticas_mes = $db->fetchOne("
        SELECT COUNT(*) as total_jornadas, COALESCE(SUM(total_pruebas), 0) as total_pruebas,
               COALESCE(SUM(pruebas_negativas), 0) as total_negativos, COALESCE(SUM(pruebas_positivas), 0) as total_positivos,
               SUM(CASE WHEN estado = 'cerrada' THEN 1 ELSE 0 END) as jornadas_cerradas,
               SUM(CASE WHEN estado IN ('abierta', 'pendiente_cierre') THEN 1 ELSE 0 END) as jornadas_abiertas
        FROM jornadas_prueba WHERE cliente_id = ? AND MONTH(fecha) = MONTH(CURDATE()) AND YEAR(fecha) = YEAR(CURDATE())
    ", [$cliente_id]);
}

// Actualizar protocolos pendientes
if ($jornada_activa && $jornada_activa['pruebas_positivas'] > 0) {
    $pendientes = $db->fetchOne("
        SELECT COUNT(*) as total FROM pruebas_protocolo pp
        INNER JOIN pruebas p ON pp.prueba_alcohol_id = p.id
        WHERE p.cliente_id = ? AND DATE(p.fecha_prueba) = ? 
          AND p.resultado = 'reprobado' AND pp.completada = 0
    ", [$cliente_id, $jornada_activa['fecha']]);
    $protocolos_pendientes = intval($pendientes['total'] ?? 0);
}
?>

<div class="content-body">
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><i class="fas fa-calendar-check"></i> <?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestión de jornadas diarias para pruebas de alcoholemia</p>
        </div>
        <div class="header-actions">
            <?php if (!$jornada_activa): ?>
            <button type="button" class="btn btn-primary" onclick="mostrarModalAbrir()">
                <i class="fas fa-play-circle"></i> Abrir Jornada
            </button>
            <?php endif; ?>
            <a href="protocolo_completo.php" class="btn btn-outline">
                <i class="fas fa-clipboard-check"></i> Ir a Protocolo
            </a>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Volver
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

    <div class="crud-container">
        
        <!-- JORNADA ACTIVA -->
        <?php if ($jornada_activa): ?>
        <div class="card <?php echo $jornada_activa['estado'] == 'pendiente_cierre' ? 'card-warning' : 'card-success'; ?>">
            <div class="card-header card-header-colored">
                <h3>
                    <i class="fas fa-door-open"></i> 
                    Jornada Activa - <?php echo date('d/m/Y', strtotime($jornada_activa['fecha'])); ?>
                </h3>
                <div class="card-actions">
                    <?php if ($jornada_activa['estado'] == 'abierta'): ?>
                    <span class="status-badge estado-abierta">🟢 Abierta</span>
                    <?php else: ?>
                    <span class="status-badge estado-pendiente">🟡 Pendiente Cierre</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <!-- Info -->
                <div class="info-grid">
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <span><strong>Inicio:</strong> <?php echo date('H:i', strtotime($jornada_activa['hora_inicio'])); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><strong>Ubicación:</strong> <?php echo htmlspecialchars($jornada_activa['nombre_ubicacion'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user"></i>
                        <span><strong>Operador:</strong> <?php echo htmlspecialchars($jornada_activa['operador_nombre']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-hashtag"></i>
                        <span><strong>Prueba #:</strong> <?php echo $jornada_activa['numero_prueba_inicio']; ?></span>
                    </div>
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary"><i class="fas fa-vial"></i></div>
                        <div class="stat-info"><h3><?php echo $jornada_activa['total_pruebas']; ?></h3><p>Total</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-info"><h3><?php echo $jornada_activa['pruebas_negativas']; ?></h3><p>Negativos</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-info"><h3><?php echo $jornada_activa['pruebas_positivas']; ?></h3><p>Positivos</p></div>
                    </div>
                    <?php if ($protocolos_pendientes > 0): ?>
                    <div class="stat-card stat-warning">
                        <div class="stat-icon warning"><i class="fas fa-hourglass-half"></i></div>
                        <div class="stat-info"><h3><?php echo $protocolos_pendientes; ?></h3><p>Prot. Pendientes</p></div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($protocolos_pendientes > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>¡Atención!</strong> Hay <?php echo $protocolos_pendientes; ?> protocolo(s) pendiente(s). No podrá cerrar hasta completarlos.
                    <a href="protocolo_completo.php" class="btn btn-warning btn-sm">Completar</a>
                </div>
                <?php endif; ?>

                <!-- Formulario -->
                <div class="form-section">
                    <h4><i class="fas fa-edit"></i> Registrar Resultados</h4>
                    <form method="POST">
                        <input type="hidden" name="jornada_id" value="<?php echo $jornada_activa['id']; ?>">
                        
                        <div class="form-grid-4">
                            <div class="form-group">
                                <label class="form-label">Negativos *</label>
                                <input type="number" name="pruebas_negativas" class="form-control input-lg" 
                                       min="0" required value="<?php echo $jornada_activa['pruebas_negativas']; ?>"
                                       onchange="calcularTotal()">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Positivos *</label>
                                <input type="number" name="pruebas_positivas" class="form-control input-lg input-danger" 
                                       min="0" required value="<?php echo $jornada_activa['pruebas_positivas']; ?>"
                                       onchange="calcularTotal()">
                            </div>
                            <div class="form-group">
                                <label class="form-label"># Final</label>
                                <input type="number" name="numero_prueba_fin" class="form-control" 
                                       min="<?php echo $jornada_activa['numero_prueba_inicio']; ?>"
                                       value="<?php echo $jornada_activa['numero_prueba_fin'] ?? ''; ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Total</label>
                                <div class="total-box" id="totalBox"><?php echo $jornada_activa['total_pruebas']; ?></div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Observaciones</label>
                            <textarea name="observaciones" class="form-control" rows="2"><?php echo htmlspecialchars($jornada_activa['observaciones'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="registrar_resultados" class="btn btn-primary">
                                <i class="fas fa-save"></i> Guardar
                            </button>
                            <?php if ($protocolos_pendientes == 0): ?>
                            <button type="submit" name="cerrar_jornada" class="btn btn-success"
                                    onclick="return confirm('¿Cerrar jornada?')">
                                <i class="fas fa-door-closed"></i> Cerrar Jornada
                            </button>
                            <?php else: ?>
                            <button type="button" class="btn btn-disabled" disabled>
                                <i class="fas fa-lock"></i> Cerrar Jornada
                            </button>
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-danger" onclick="mostrarModalCancelar()">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <?php else: ?>
        <!-- SIN JORNADA -->
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-calendar-plus"></i></div>
                    <h3>No hay jornada activa hoy</h3>
                    <p>Abra una nueva jornada para comenzar</p>
                    <button type="button" class="btn btn-primary btn-lg" onclick="mostrarModalAbrir()">
                        <i class="fas fa-play-circle"></i> Abrir Jornada
                    </button>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ESTADÍSTICAS DEL MES -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Estadísticas del Mes</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary"><i class="fas fa-calendar-alt"></i></div>
                        <div class="stat-info"><h3><?php echo $estadisticas_mes['total_jornadas'] ?? 0; ?></h3><p>Jornadas</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info"><i class="fas fa-vial"></i></div>
                        <div class="stat-info"><h3><?php echo $estadisticas_mes['total_pruebas'] ?? 0; ?></h3><p>Pruebas</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                        <div class="stat-info"><h3><?php echo $estadisticas_mes['total_negativos'] ?? 0; ?></h3><p>Negativos</p></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger"><i class="fas fa-exclamation-triangle"></i></div>
                        <div class="stat-info"><h3><?php echo $estadisticas_mes['total_positivos'] ?? 0; ?></h3><p>Positivos</p></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- HISTORIAL -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Historial de Jornadas</h3>
                <span class="badge primary"><?php echo count($historial_jornadas); ?> registros</span>
            </div>
            <div class="card-body">
                <?php if (!empty($historial_jornadas)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Ubicación</th>
                                <th>Horario</th>
                                <th>Total</th>
                                <th>Neg.</th>
                                <th>Pos.</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($historial_jornadas as $j): ?>
                            <tr class="<?php echo in_array($j['estado'], ['abierta', 'pendiente_cierre']) ? 'row-active' : ''; ?>">
                                <td><strong><?php echo date('d/m/Y', strtotime($j['fecha'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($j['nombre_ubicacion'] ?? 'N/A'); ?></td>
                                <td><?php echo date('H:i', strtotime($j['hora_inicio'])); ?><?php echo $j['hora_fin'] ? ' - '.date('H:i', strtotime($j['hora_fin'])) : ''; ?></td>
                                <td><span class="badge primary"><?php echo $j['total_pruebas']; ?></span></td>
                                <td><span class="badge success"><?php echo $j['pruebas_negativas']; ?></span></td>
                                <td><span class="badge <?php echo $j['pruebas_positivas'] > 0 ? 'danger' : 'success'; ?>"><?php echo $j['pruebas_positivas']; ?></span></td>
                                <td>
                                    <?php
                                    $estados = ['abierta'=>'🟢 Abierta','pendiente_cierre'=>'🟡 Pendiente','cerrada'=>'✅ Cerrada','cancelada'=>'❌ Cancelada'];
                                    echo '<span class="status-badge estado-'.$j['estado'].'">'.$estados[$j['estado']].'</span>';
                                    ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state small">
                    <i class="fas fa-inbox"></i>
                    <p>No hay jornadas registradas</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL: ABRIR JORNADA -->
<div id="modalAbrir" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; background:rgba(0,0,0,0.5);">
    <div style="position:relative; width:100%; max-width:500px; margin:50px auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
        <div style="padding:1.5rem; border-bottom:1px solid #dee2e6; background:#f8f9fa; border-radius:12px 12px 0 0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.2rem; display:flex; align-items:center; gap:0.5rem;">
                <i class="fas fa-play-circle" style="color:#84061f;"></i> Abrir Nueva Jornada
            </h3>
            <button type="button" onclick="cerrarModalAbrir()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#6c757d;">&times;</button>
        </div>
        <div style="padding:1.5rem;">
            <form id="formAbrir" method="POST">
                <input type="hidden" name="abrir_jornada" value="1">
                
                <div style="background:rgba(52,152,219,0.1); border:1px solid rgba(52,152,219,0.3); border-radius:8px; padding:1rem; margin-bottom:1.5rem;">
                    <p style="margin:0.25rem 0;"><i class="fas fa-calendar" style="color:#3498db; width:20px;"></i> <strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></p>
                    <p style="margin:0.25rem 0;"><i class="fas fa-clock" style="color:#3498db; width:20px;"></i> <strong>Hora:</strong> <?php echo date('H:i'); ?></p>
                    <p style="margin:0.25rem 0;"><i class="fas fa-user" style="color:#3498db; width:20px;"></i> <strong>Operador:</strong> <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Usuario'); ?></p>
                </div>
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Ubicación / Sede *</label>
                    <select name="ubicacion_id" style="width:100%; padding:0.75rem; border:2px solid #dee2e6; border-radius:8px; font-size:1rem;" required>
                        <option value="">-- Seleccione --</option>
                        <?php foreach ($ubicaciones as $u): ?>
                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['nombre_ubicacion']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Número Prueba Inicial *</label>
                    <input type="number" name="numero_prueba_inicio" style="width:100%; padding:0.75rem; border:2px solid #dee2e6; border-radius:8px; font-size:1rem; box-sizing:border-box;" min="1" required value="<?php echo $siguiente_numero; ?>">
                    <small style="color:#6c757d; font-size:0.8rem;">Sugerido: siguiente al último registrado</small>
                </div>
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Observaciones</label>
                    <textarea name="observaciones" style="width:100%; padding:0.75rem; border:2px solid #dee2e6; border-radius:8px; font-size:1rem; box-sizing:border-box;" rows="2"></textarea>
                </div>
            </form>
        </div>
        <div style="padding:1rem 1.5rem; border-top:1px solid #dee2e6; display:flex; justify-content:flex-end; gap:1rem;">
            <button type="button" onclick="cerrarModalAbrir()" class="btn btn-outline">Cancelar</button>
            <button type="submit" form="formAbrir" class="btn btn-primary"><i class="fas fa-play-circle"></i> Abrir Jornada</button>
        </div>
    </div>
</div>

<!-- MODAL: CANCELAR JORNADA -->
<div id="modalCancelar" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:9999; background:rgba(0,0,0,0.5);">
    <div style="position:relative; width:100%; max-width:400px; margin:50px auto; background:#fff; border-radius:12px; box-shadow:0 10px 30px rgba(0,0,0,0.3);">
        <div style="padding:1.5rem; border-bottom:1px solid #dee2e6; background:linear-gradient(135deg,#e74c3c,#c0392b); border-radius:12px 12px 0 0; display:flex; justify-content:space-between; align-items:center;">
            <h3 style="margin:0; font-size:1.2rem; color:white; display:flex; align-items:center; gap:0.5rem;">
                <i class="fas fa-times-circle"></i> Cancelar Jornada
            </h3>
            <button type="button" onclick="cerrarModalCancelar()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:white;">&times;</button>
        </div>
        <div style="padding:1.5rem;">
            <form id="formCancelar" method="POST">
                <input type="hidden" name="cancelar_jornada" value="1">
                <input type="hidden" name="jornada_id" value="<?php echo $jornada_activa['id'] ?? ''; ?>">
                
                <div class="alert alert-danger" style="margin-bottom:1rem;">
                    <i class="fas fa-exclamation-triangle"></i>
                    Esta acción no se puede deshacer.
                </div>
                
                <div style="margin-bottom:1rem;">
                    <label style="display:block; font-weight:600; margin-bottom:0.5rem;">Motivo *</label>
                    <textarea name="motivo_cancelacion" style="width:100%; padding:0.75rem; border:2px solid #dee2e6; border-radius:8px; font-size:1rem; box-sizing:border-box;" rows="3" required></textarea>
                </div>
            </form>
        </div>
        <div style="padding:1rem 1.5rem; border-top:1px solid #dee2e6; display:flex; justify-content:flex-end; gap:1rem;">
            <button type="button" onclick="cerrarModalCancelar()" class="btn btn-outline">Volver</button>
            <button type="submit" form="formCancelar" class="btn btn-danger"><i class="fas fa-times"></i> Confirmar</button>
        </div>
    </div>
</div>

<style>
/* ===== ESTILOS APERTURA DE DÍA ===== */

/* Cards coloreadas */
.card-success { border: 2px solid var(--success); }
.card-success .card-header-colored { background: linear-gradient(135deg, var(--success), #2ecc71); color: white; }
.card-success .card-header-colored h3 { color: white; }
.card-success .card-header-colored h3 i { color: white; }

.card-warning { border: 2px solid var(--warning); }
.card-warning .card-header-colored { background: linear-gradient(135deg, var(--warning), #e67e22); color: white; }
.card-warning .card-header-colored h3 { color: white; }
.card-warning .card-header-colored h3 i { color: white; }

/* Info grid */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 1rem;
    padding: 1rem;
    background: var(--light);
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.info-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-item i {
    color: var(--primary);
    width: 20px;
}

/* Stat warning */
.stat-warning {
    border-color: var(--warning);
    background: rgba(243, 156, 18, 0.05);
}

/* Form section */
.form-section {
    margin-top: 1.5rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.form-section h4 {
    margin: 0 0 1rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.form-section h4 i {
    color: var(--primary);
}

/* Form grid 4 */
.form-grid-4 {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
    margin-bottom: 1rem;
}

@media (max-width: 768px) {
    .form-grid-4 { grid-template-columns: repeat(2, 1fr); }
}

@media (max-width: 480px) {
    .form-grid-4 { grid-template-columns: 1fr; }
}

/* Input large */
.input-lg {
    font-size: 1.5rem;
    font-weight: 700;
    text-align: center;
    padding: 1rem;
}

.input-danger:focus {
    border-color: var(--danger);
    box-shadow: 0 0 0 4px rgba(231, 76, 60, 0.1);
}

/* Total box */
.total-box {
    background: var(--light);
    border: 2px solid var(--border);
    border-radius: 10px;
    padding: 1rem;
    font-size: 1.5rem;
    font-weight: 700;
    text-align: center;
    color: var(--primary);
}

/* Form actions */
.form-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
}

/* Botones adicionales */
.btn-outline-danger {
    background: transparent;
    color: var(--danger);
    border: 2px solid var(--danger);
}

.btn-outline-danger:hover {
    background: var(--danger);
    color: white;
}

.btn-disabled {
    background: var(--gray);
    color: white;
    border-color: var(--gray);
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.8rem;
}

/* Status badges */
.status-badge.estado-abierta { background: rgba(39,174,96,0.15); color: var(--success); }
.status-badge.estado-pendiente { background: rgba(243,156,18,0.15); color: #d68910; }
.status-badge.estado-cerrada { background: rgba(108,117,125,0.15); color: var(--gray); }
.status-badge.estado-cancelada { background: rgba(231,76,60,0.15); color: var(--danger); }

/* Row active */
.row-active { background: rgba(39, 174, 96, 0.1) !important; }

/* Empty state small */
.empty-state.small {
    padding: 2rem;
}

.empty-state.small i {
    font-size: 2rem;
    color: var(--gray);
    opacity: 0.5;
}

/* ===== MODAL (mismo estilo que conductores.php) ===== */
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
    display: none;
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
    max-width: 500px;
    pointer-events: none;
    z-index: 1060;
}

.modal-dialog.modal-sm {
    max-width: 400px;
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

.modal-header-danger {
    background: linear-gradient(135deg, var(--danger), #c0392b);
    color: white;
}

.modal-header-danger .modal-title { color: white; }
.modal-header-danger .modal-close { color: white; }

.modal-title {
    margin: 0;
    color: var(--dark);
    font-size: 1.2rem;
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
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
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
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    gap: 1rem;
}

/* Info box modal */
.info-box-modal {
    background: rgba(52, 152, 219, 0.1);
    border: 1px solid rgba(52, 152, 219, 0.3);
    border-radius: 8px;
    padding: 1rem;
    margin-bottom: 1.5rem;
}

.info-box-modal p {
    margin: 0.25rem 0;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.info-box-modal i {
    color: var(--info);
    width: 20px;
}

/* Form hint */
.form-hint {
    font-size: 0.8rem;
    color: var(--gray);
    margin-top: 0.25rem;
}
</style>

<script>
// ===== FUNCIONES MODAL =====
function mostrarModalAbrir() {
    var modal = document.getElementById('modalAbrir');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    } else {
        alert('Error: Modal no encontrado');
    }
}

function cerrarModalAbrir() {
    var modal = document.getElementById('modalAbrir');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

function mostrarModalCancelar() {
    var modal = document.getElementById('modalCancelar');
    if (modal) {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
}

function cerrarModalCancelar() {
    var modal = document.getElementById('modalCancelar');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Cerrar con ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        cerrarModalAbrir();
        cerrarModalCancelar();
    }
});

// Cerrar al hacer clic fuera
document.getElementById('modalAbrir')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModalAbrir();
});

document.getElementById('modalCancelar')?.addEventListener('click', function(e) {
    if (e.target === this) cerrarModalCancelar();
});

// ===== CALCULAR TOTAL =====
function calcularTotal() {
    var neg = parseInt(document.querySelector('input[name="pruebas_negativas"]')?.value) || 0;
    var pos = parseInt(document.querySelector('input[name="pruebas_positivas"]')?.value) || 0;
    var total = neg + pos;
    var box = document.getElementById('totalBox');
    if (box) box.textContent = total;
}

// Init
document.addEventListener('DOMContentLoaded', function() {
    calcularTotal();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>