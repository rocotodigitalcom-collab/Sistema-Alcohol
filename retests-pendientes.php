<?php
// retests-pendientes.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Re-tests Pendientes';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'retests-pendientes.php' => 'Re-tests Pendientes'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// Obtener configuración del cliente
$configuracion = $db->fetchOne("
    SELECT limite_alcohol_permisible, nivel_advertencia, nivel_critico, unidad_medida,
           intervalo_retest_minutos, intentos_retest
    FROM configuraciones 
    WHERE cliente_id = ?
", [$cliente_id]);

// FILTROS
$filtros = [];
$where_conditions = ["sr.estado = 'pendiente'"];
$params = [];

// Filtro por fecha de solicitud
if (!empty($_GET['fecha_solicitud_desde'])) {
    $filtros['fecha_solicitud_desde'] = $_GET['fecha_solicitud_desde'];
    $where_conditions[] = "DATE(sr.fecha_solicitud) >= ?";
    $params[] = $_GET['fecha_solicitud_desde'];
}

if (!empty($_GET['fecha_solicitud_hasta'])) {
    $filtros['fecha_solicitud_hasta'] = $_GET['fecha_solicitud_hasta'];
    $where_conditions[] = "DATE(sr.fecha_solicitud) <= ?";
    $params[] = $_GET['fecha_solicitud_hasta'];
}

// Filtro por conductor
if (!empty($_GET['conductor_id'])) {
    $filtros['conductor_id'] = $_GET['conductor_id'];
    $where_conditions[] = "p.conductor_id = ?";
    $params[] = $_GET['conductor_id'];
}

// Filtro por solicitante
if (!empty($_GET['solicitante_id'])) {
    $filtros['solicitante_id'] = $_GET['solicitante_id'];
    $where_conditions[] = "sr.solicitado_por = ?";
    $params[] = $_GET['solicitante_id'];
}

// Filtro por nivel de alcohol original
if (!empty($_GET['nivel_min'])) {
    $filtros['nivel_min'] = $_GET['nivel_min'];
    $where_conditions[] = "p.nivel_alcohol >= ?";
    $params[] = $_GET['nivel_min'];
}

if (!empty($_GET['nivel_max'])) {
    $filtros['nivel_max'] = $_GET['nivel_max'];
    $where_conditions[] = "p.nivel_alcohol <= ?";
    $params[] = $_GET['nivel_max'];
}

// Construir WHERE
$where_sql = implode(' AND ', $where_conditions);

// Obtener datos para filtros
$conductores = $db->fetchAll("
    SELECT DISTINCT u.id, u.nombre, u.apellido, u.dni 
    FROM usuarios u
    INNER JOIN pruebas p ON u.id = p.conductor_id
    INNER JOIN solicitudes_retest sr ON p.id = sr.prueba_original_id
    WHERE u.cliente_id = ? AND u.rol = 'conductor' AND u.estado = 1
    ORDER BY u.nombre, u.apellido
", [$cliente_id]);

$solicitantes = $db->fetchAll("
    SELECT DISTINCT u.id, u.nombre, u.apellido, u.rol
    FROM usuarios u
    INNER JOIN solicitudes_retest sr ON u.id = sr.solicitado_por
    WHERE u.cliente_id = ? AND u.estado = 1
    ORDER BY u.nombre, u.apellido
", [$cliente_id]);

// Obtener estadísticas de re-tests pendientes
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pendientes,
        COUNT(DISTINCT p.conductor_id) as conductores_afectados,
        AVG(p.nivel_alcohol) as promedio_nivel_original,
        MIN(p.nivel_alcohol) as minimo_nivel,
        MAX(p.nivel_alcohol) as maximo_nivel,
        AVG(TIMESTAMPDIFF(HOUR, sr.fecha_solicitud, NOW())) as promedio_horas_espera
    FROM solicitudes_retest sr
    INNER JOIN pruebas p ON sr.prueba_original_id = p.id
    WHERE $where_sql
    AND p.cliente_id = ?
", array_merge($params, [$cliente_id]));

// Obtener solicitudes de re-test pendientes
$solicitudes_pendientes = $db->fetchAll("
    SELECT 
        sr.*,
        p.nivel_alcohol as nivel_original,
        p.fecha_prueba as fecha_prueba_original,
        p.observaciones as observaciones_original,
        CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
        u_conductor.dni as conductor_dni,
        CONCAT(u_solicitante.nombre, ' ', u_solicitante.apellido) as solicitante_nombre,
        u_solicitante.rol as solicitante_rol,
        a.nombre_activo as alcoholimetro_nombre,
        a.numero_serie as alcoholimetro_serie,
        v.placa as vehiculo_placa,
        v.marca as vehiculo_marca,
        v.modelo as vehiculo_modelo,
        TIMESTAMPDIFF(HOUR, sr.fecha_solicitud, NOW()) as horas_espera,
        TIMESTAMPDIFF(DAY, sr.fecha_solicitud, NOW()) as dias_espera,
        (SELECT COUNT(*) FROM pruebas pr WHERE pr.prueba_padre_id = p.id) as retests_realizados
    FROM solicitudes_retest sr
    INNER JOIN pruebas p ON sr.prueba_original_id = p.id
    LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
    LEFT JOIN usuarios u_solicitante ON sr.solicitado_por = u_solicitante.id
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
    WHERE $where_sql
    AND p.cliente_id = ?
    ORDER BY sr.fecha_solicitud ASC
    LIMIT 100
", array_merge($params, [$cliente_id]));

// Procesar acciones de aprobación/rechazo
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['procesar_solicitud'])) {
        $solicitud_id = $_POST['solicitud_id'];
        $accion = $_POST['accion']; // 'aprobar' o 'rechazar'
        $observaciones = trim($_POST['observaciones_decision'] ?? '');
        $intervalo_minutos = $_POST['intervalo_retest'] ?? $configuracion['intervalo_retest_minutos'];
        
        try {
            if ($accion === 'aprobar') {
                // Aprobar solicitud
                $db->execute("
                    UPDATE solicitudes_retest 
                    SET estado = 'aprobado', aprobado_por = ?, fecha_resolucion = NOW(),
                        observaciones_aprobacion = ?
                    WHERE id = ?
                ", [$user_id, $observaciones, $solicitud_id]);
                
                // Obtener información de la solicitud para el re-test
                $solicitud = $db->fetchOne("
                    SELECT sr.*, p.* 
                    FROM solicitudes_retest sr
                    INNER JOIN pruebas p ON sr.prueba_original_id = p.id
                    WHERE sr.id = ?
                ", [$solicitud_id]);
                
                if ($solicitud) {
                    // Crear registro de re-test programado
                    $fecha_retest = date('Y-m-d H:i:s', strtotime("+$intervalo_minutos minutes"));
                    
                    $db->execute("
                        INSERT INTO pruebas 
                        (cliente_id, alcoholimetro_id, conductor_id, supervisor_id, 
                         vehiculo_id, nivel_alcohol, limite_permisible, resultado,
                         es_retest, prueba_padre_id, intento_numero, estado_retest,
                         fecha_programada_retest)
                        VALUES (?, ?, ?, ?, ?, 0, ?, 'pendiente',
                                1, ?, ?, 'programado', ?)
                    ", [
                        $cliente_id, 
                        $solicitud['alcoholimetro_id'], 
                        $solicitud['conductor_id'],
                        $user_id, // supervisor que aprueba
                        $solicitud['vehiculo_id'],
                        $configuracion['limite_alcohol_permisible'],
                        $solicitud['prueba_original_id'],
                        ($solicitud['retests_realizados'] ?? 0) + 1,
                        $fecha_retest
                    ]);
                }
                
                $mensaje_exito = "Solicitud aprobada correctamente. Re-test programado.";
                
            } else {
                // Rechazar solicitud
                $db->execute("
                    UPDATE solicitudes_retest 
                    SET estado = 'rechazado', aprobado_por = ?, fecha_resolucion = NOW(),
                        observaciones_aprobacion = ?
                    WHERE id = ?
                ", [$user_id, $observaciones, $solicitud_id]);
                
                $mensaje_exito = "Solicitud rechazada correctamente.";
            }
            
            // Auditoría
            $accion_auditoria = $accion === 'aprobar' ? 'APROBAR_RETEST' : 'RECHAZAR_RETEST';
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, ?, 'solicitudes_retest', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $accion_auditoria, $solicitud_id, 
                "Solicitud de re-test {$accion}da - " . ($observaciones ?: "Sin observaciones"), 
                $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            // Recargar datos
            $solicitudes_pendientes = $db->fetchAll("
                SELECT 
                    sr.*,
                    p.nivel_alcohol as nivel_original,
                    p.fecha_prueba as fecha_prueba_original,
                    p.observaciones as observaciones_original,
                    CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
                    u_conductor.dni as conductor_dni,
                    CONCAT(u_solicitante.nombre, ' ', u_solicitante.apellido) as solicitante_nombre,
                    u_solicitante.rol as solicitante_rol,
                    a.nombre_activo as alcoholimetro_nombre,
                    a.numero_serie as alcoholimetro_serie,
                    v.placa as vehiculo_placa,
                    v.marca as vehiculo_marca,
                    v.modelo as vehiculo_modelo,
                    TIMESTAMPDIFF(HOUR, sr.fecha_solicitud, NOW()) as horas_espera,
                    TIMESTAMPDIFF(DAY, sr.fecha_solicitud, NOW()) as dias_espera,
                    (SELECT COUNT(*) FROM pruebas pr WHERE pr.prueba_padre_id = p.id) as retests_realizados
                FROM solicitudes_retest sr
                INNER JOIN pruebas p ON sr.prueba_original_id = p.id
                LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
                LEFT JOIN usuarios u_solicitante ON sr.solicitado_por = u_solicitante.id
                LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
                LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
                WHERE sr.estado = 'pendiente'
                AND p.cliente_id = ?
                ORDER BY sr.fecha_solicitud ASC
                LIMIT 100
            ", [$cliente_id]);
            
        } catch (Exception $e) {
            $mensaje_error = "Error al procesar la solicitud: " . $e->getMessage();
        }
    }
}

// Procesar acción múltiple
if (isset($_POST['accion_multiple'])) {
    $solicitudes_seleccionadas = $_POST['solicitudes_seleccionadas'] ?? [];
    $accion_multiple = $_POST['accion_multiple'];
    $observaciones_multiple = trim($_POST['observaciones_multiple'] ?? '');
    
    if (!empty($solicitudes_seleccionadas)) {
        try {
            $placeholders = str_repeat('?,', count($solicitudes_seleccionadas) - 1) . '?';
            
            if ($accion_multiple === 'aprobar') {
                $db->execute("
                    UPDATE solicitudes_retest 
                    SET estado = 'aprobado', aprobado_por = ?, fecha_resolucion = NOW(),
                        observaciones_aprobacion = ?
                    WHERE id IN ($placeholders)
                ", array_merge([$user_id, $observaciones_multiple], $solicitudes_seleccionadas));
                
                $mensaje_exito = count($solicitudes_seleccionadas) . " solicitudes aprobadas correctamente.";
                
            } elseif ($accion_multiple === 'rechazar') {
                $db->execute("
                    UPDATE solicitudes_retest 
                    SET estado = 'rechazado', aprobado_por = ?, fecha_resolucion = NOW(),
                        observaciones_aprobacion = ?
                    WHERE id IN ($placeholders)
                ", array_merge([$user_id, $observaciones_multiple], $solicitudes_seleccionadas));
                
                $mensaje_exito = count($solicitudes_seleccionadas) . " solicitudes rechazadas correctamente.";
            }
            
            // Recargar datos
            $solicitudes_pendientes = $db->fetchAll("
                SELECT 
                    sr.*,
                    p.nivel_alcohol as nivel_original,
                    p.fecha_prueba as fecha_prueba_original,
                    p.observaciones as observaciones_original,
                    CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
                    u_conductor.dni as conductor_dni,
                    CONCAT(u_solicitante.nombre, ' ', u_solicitante.apellido) as solicitante_nombre,
                    u_solicitante.rol as solicitante_rol,
                    a.nombre_activo as alcoholimetro_nombre,
                    a.numero_serie as alcoholimetro_serie,
                    v.placa as vehiculo_placa,
                    v.marca as vehiculo_marca,
                    v.modelo as vehiculo_modelo,
                    TIMESTAMPDIFF(HOUR, sr.fecha_solicitud, NOW()) as horas_espera,
                    TIMESTAMPDIFF(DAY, sr.fecha_solicitud, NOW()) as dias_espera,
                    (SELECT COUNT(*) FROM pruebas pr WHERE pr.prueba_padre_id = p.id) as retests_realizados
                FROM solicitudes_retest sr
                INNER JOIN pruebas p ON sr.prueba_original_id = p.id
                LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
                LEFT JOIN usuarios u_solicitante ON sr.solicitado_por = u_solicitante.id
                LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
                LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
                WHERE sr.estado = 'pendiente'
                AND p.cliente_id = ?
                ORDER BY sr.fecha_solicitud ASC
                LIMIT 100
            ", [$cliente_id]);
            
        } catch (Exception $e) {
            $mensaje_error = "Error en acción múltiple: " . $e->getMessage();
        }
    } else {
        $mensaje_error = "No se seleccionaron solicitudes para procesar.";
    }
}
?>

<div class="content-body">
    <!-- HEADER IDÉNTICO -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona las solicitudes de re-test pendientes de aprobación</p>
        </div>
        <div class="header-actions">
            <a href="pruebas-pendientes.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>Volver a Pendientes
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
        <!-- CARD DE FILTROS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filtros de Solicitudes</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="account-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fecha_solicitud_desde">Fecha Solicitud Desde</label>
                            <input type="date" id="fecha_solicitud_desde" name="fecha_solicitud_desde" 
                                   value="<?php echo htmlspecialchars($_GET['fecha_solicitud_desde'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="fecha_solicitud_hasta">Fecha Solicitud Hasta</label>
                            <input type="date" id="fecha_solicitud_hasta" name="fecha_solicitud_hasta" 
                                   value="<?php echo htmlspecialchars($_GET['fecha_solicitud_hasta'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="conductor_id">Conductor</label>
                            <select id="conductor_id" name="conductor_id" class="form-control">
                                <option value="">Todos los conductores</option>
                                <?php foreach ($conductores as $conductor): ?>
                                <option value="<?php echo $conductor['id']; ?>" 
                                    <?php echo ($_GET['conductor_id'] ?? '') == $conductor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($conductor['nombre'] . ' ' . $conductor['apellido'] . ' (' . $conductor['dni'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="solicitante_id">Solicitado Por</label>
                            <select id="solicitante_id" name="solicitante_id" class="form-control">
                                <option value="">Todos los solicitantes</option>
                                <?php foreach ($solicitantes as $solicitante): ?>
                                <option value="<?php echo $solicitante['id']; ?>" 
                                    <?php echo ($_GET['solicitante_id'] ?? '') == $solicitante['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($solicitante['nombre'] . ' ' . $solicitante['apellido'] . ' (' . $solicitante['rol'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nivel_min">Nivel Mínimo Original (<?php echo $configuracion['unidad_medida']; ?>)</label>
                            <input type="number" id="nivel_min" name="nivel_min" 
                                   value="<?php echo htmlspecialchars($_GET['nivel_min'] ?? ''); ?>" 
                                   step="0.001" min="0" max="1" 
                                   class="form-control" placeholder="0.000">
                        </div>
                        <div class="form-group">
                            <label for="nivel_max">Nivel Máximo Original (<?php echo $configuracion['unidad_medida']; ?>)</label>
                            <input type="number" id="nivel_max" name="nivel_max" 
                                   value="<?php echo htmlspecialchars($_GET['nivel_max'] ?? ''); ?>" 
                                   step="0.001" min="0" max="1" 
                                   class="form-control" placeholder="1.000">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Aplicar Filtros
                        </button>
                        <a href="retests-pendientes.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Limpiar Filtros
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- CARD DE ESTADÍSTICAS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Estadísticas de Re-tests Pendientes</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_pendientes'] ?? 0; ?></h3>
                            <p>Solicitudes Pendientes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['conductores_afectados'] ?? 0; ?></h3>
                            <p>Conductores Afectados</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon average">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['promedio_nivel_original'] ?? 0, 3); ?></h3>
                            <p>Promedio Nivel Original</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon critical">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['maximo_nivel'] ?? 0, 3); ?></h3>
                            <p>Máximo Nivel Original</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon low">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['minimo_nivel'] ?? 0, 3); ?></h3>
                            <p>Mínimo Nivel Original</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-hourglass-half"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['promedio_horas_espera'] ?? 0, 1); ?></h3>
                            <p>Horas de Espera Promedio</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD DE SOLICITUDES PENDIENTES -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list-alt"></i> Solicitudes de Re-test Pendientes</h3>
                <div class="card-actions">
                    <span class="badge warning"><?php echo count($solicitudes_pendientes); ?> pendientes</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($solicitudes_pendientes)): ?>
                <!-- ACCIONES MÚLTIPLES -->
                <div class="multiple-actions">
                    <form method="POST" id="formMultiple" class="multiple-form">
                        <div class="multiple-controls">
                            <div class="form-group compact">
                                <label for="accion_multiple">Acción Múltiple:</label>
                                <select id="accion_multiple" name="accion_multiple" class="form-control" required>
                                    <option value="">Seleccionar acción...</option>
                                    <option value="aprobar">Aprobar Seleccionados</option>
                                    <option value="rechazar">Rechazar Seleccionados</option>
                                </select>
                            </div>
                            <div class="form-group compact">
                                <label for="observaciones_multiple">Observaciones:</label>
                                <input type="text" id="observaciones_multiple" name="observaciones_multiple" 
                                       class="form-control" placeholder="Observaciones para todas las solicitudes...">
                            </div>
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-play-circle"></i> Ejecutar Acción
                            </button>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th width="30">
                                    <input type="checkbox" id="selectAll" title="Seleccionar todos">
                                </th>
                                <th>Solicitud</th>
                                <th>Conductor</th>
                                <th>Solicitado Por</th>
                                <th>Prueba Original</th>
                                <th>Nivel Original</th>
                                <th>Motivo</th>
                                <th>Tiempo Espera</th>
                                <th>Re-tests Previos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($solicitudes_pendientes as $solicitud): 
                                $gravedad_clase = 'leve';
                                if ($solicitud['nivel_original'] > $configuracion['nivel_critico']) {
                                    $gravedad_clase = 'critica';
                                } elseif ($solicitud['nivel_original'] > $configuracion['nivel_advertencia']) {
                                    $gravedad_clase = 'grave';
                                }
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="solicitudes_seleccionadas[]" 
                                           value="<?php echo $solicitud['id']; ?>" 
                                           class="solicitud-checkbox">
                                </td>
                                <td>
                                    <div class="solicitud-info">
                                        <div class="solicitud-id">#<?php echo $solicitud['id']; ?></div>
                                        <div class="solicitud-fecha">
                                            <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_solicitud'])); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="conductor-info">
                                        <div class="conductor-nombre"><?php echo htmlspecialchars($solicitud['conductor_nombre']); ?></div>
                                        <div class="conductor-dni"><?php echo htmlspecialchars($solicitud['conductor_dni']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="solicitante-info">
                                        <div class="solicitante-nombre"><?php echo htmlspecialchars($solicitud['solicitante_nombre']); ?></div>
                                        <div class="solicitante-rol"><?php echo htmlspecialchars($solicitud['solicitante_rol']); ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="prueba-original">
                                        <div class="prueba-fecha">
                                            <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha_prueba_original'])); ?>
                                        </div>
                                        <div class="alcoholimetro">
                                            <?php echo htmlspecialchars($solicitud['alcoholimetro_nombre']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="nivel-alcohol <?php echo $gravedad_clase; ?>">
                                        <?php echo number_format($solicitud['nivel_original'], 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="motivo-solicitud" title="<?php echo htmlspecialchars($solicitud['motivo']); ?>">
                                        <?php echo strlen($solicitud['motivo']) > 60 ? substr($solicitud['motivo'], 0, 60) . '...' : $solicitud['motivo']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="tiempo-espera <?php echo $solicitud['dias_espera'] > 1 ? 'text-danger' : ($solicitud['horas_espera'] > 12 ? 'text-warning' : 'text-info'); ?>">
                                        <?php 
                                        if ($solicitud['dias_espera'] > 0) {
                                            echo $solicitud['dias_espera'] . ' día' . ($solicitud['dias_espera'] > 1 ? 's' : '');
                                        } else {
                                            echo $solicitud['horas_espera'] . ' hora' . ($solicitud['horas_espera'] > 1 ? 's' : '');
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="retests-previos <?php echo $solicitud['retests_realizados'] >= $configuracion['intentos_retest'] ? 'text-danger' : 'text-muted'; ?>">
                                        <?php echo $solicitud['retests_realizados']; ?> / <?php echo $configuracion['intentos_retest']; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-icon success" 
                                            title="Aprobar Re-test"
                                            onclick="mostrarModalDecision(<?php echo $solicitud['id']; ?>, 'aprobar', <?php echo $configuracion['intervalo_retest_minutos']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="button" class="btn-icon danger" 
                                            title="Rechazar Re-test"
                                            onclick="mostrarModalDecision(<?php echo $solicitud['id']; ?>, 'rechazar')">
                                        <i class="fas fa-times"></i>
                                    </button>
                                    <a href="nueva-prueba.php?editar=<?php echo $solicitud['prueba_original_id']; ?>" 
                                       class="btn-icon" title="Ver Prueba Original">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button type="button" class="btn-icon info" 
                                            title="Ver Detalles Completo"
                                            onclick="mostrarDetallesSolicitud(<?php echo $solicitud['id']; ?>)">
                                        <i class="fas fa-info-circle"></i>
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
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>No hay re-tests pendientes</h3>
                    <p>No se encontraron solicitudes de re-test pendientes de aprobación</p>
                    <div class="empty-actions">
                        <a href="pruebas-pendientes.php" class="btn btn-primary">
                            <i class="fas fa-tasks"></i>
                            Ver Otras Pendientes
                        </a>
                        <a href="historial-pruebas.php" class="btn btn-outline">
                            <i class="fas fa-history"></i>
                            Ver Historial Completo
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODALES -->
<!-- Modal para decisión de re-test -->
<div id="modalDecisionRetest" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-check-circle"></i> <span id="modalTituloDecision">Aprobar Re-test</span></h3>
            <button type="button" class="modal-close" onclick="cerrarModalDecision()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="formDecisionRetest" method="POST">
                <input type="hidden" name="solicitud_id" id="solicitud_decision_id">
                <input type="hidden" name="accion" id="accion_decision">
                
                <div id="seccionAprobacion" style="display: none;">
                    <div class="form-group">
                        <label for="intervalo_retest">Intervalo para Re-test (minutos)</label>
                        <input type="number" id="intervalo_retest" name="intervalo_retest" 
                               value="<?php echo $configuracion['intervalo_retest_minutos']; ?>" 
                               min="1" max="1440" class="form-control" required>
                        <small class="form-text">Tiempo de espera mínimo antes de realizar el re-test</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="observaciones_decision">Observaciones de la Decisión</label>
                    <textarea id="observaciones_decision" name="observaciones_decision" class="form-control" rows="4" 
                              placeholder="Agregue observaciones sobre su decisión..." required></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="cerrarModalDecision()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" form="formDecisionRetest" name="procesar_solicitud" class="btn" id="botonDecision">
                <i class="fas fa-paper-plane"></i> <span id="textoBotonDecision">Aprobar</span>
            </button>
        </div>
    </div>
</div>

<!-- ESTILOS CSS INTEGRADOS (Mismo patrón + mejoras para re-tests pendientes) -->
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
.btn-icon.info:hover { background: var(--info); }
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
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 1.5rem 0; border-bottom: 1px solid var(--border); }
.welcome-section h1 { margin: 0 0 0.5rem 0; color: var(--dark); font-size: 1.8rem; font-weight: 700; }
.dashboard-subtitle { margin: 0; color: var(--gray); font-size: 1rem; }
.header-actions { display: flex; gap: 1rem; }
.card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); overflow: hidden; margin-bottom: 1.5rem; }
.card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center; }
.card-header h3 { margin: 0; color: var(--dark); font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.card-body { padding: 1.5rem; }

/* ESTILOS ESPECÍFICOS PARA RE-TESTS PENDIENTES */
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

.stat-icon.warning { background: rgba(243, 156, 18, 0.15); color: var(--warning); }
.stat-icon.primary { background: rgba(52, 152, 219, 0.15); color: var(--primary); }
.stat-icon.average { background: rgba(230, 126, 34, 0.15); color: #e67e22; }
.stat-icon.critical { background: rgba(231, 76, 60, 0.15); color: var(--danger); }
.stat-icon.low { background: rgba(52, 152, 219, 0.15); color: var(--primary); }
.stat-icon.info { background: rgba(155, 89, 182, 0.15); color: #9b59b6; }

.stat-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-info p {
    margin: 0;
    color: var(--gray);
    font-size: 0.85rem;
}

/* Acciones múltiples */
.multiple-actions {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border);
}

.multiple-form .multiple-controls {
    display: flex;
    align-items: end;
    gap: 1rem;
    flex-wrap: wrap;
}

.multiple-form .form-group.compact {
    flex: 1;
    min-width: 200px;
    margin-bottom: 0;
}

.multiple-form .form-group.compact label {
    font-size: 0.8rem;
    margin-bottom: 0.25rem;
}

/* Estilos específicos de la tabla */
.data-table input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.solicitud-info .solicitud-id {
    font-weight: 600;
    color: var(--dark);
}

.solicitud-info .solicitud-fecha {
    font-size: 0.8rem;
    color: var(--gray);
}

.conductor-info .conductor-nombre {
    font-weight: 600;
    color: var(--dark);
}

.conductor-info .conductor-dni {
    font-size: 0.8rem;
    color: var(--gray);
}

.solicitante-info .solicitante-nombre {
    font-weight: 600;
    color: var(--dark);
}

.solicitante-info .solicitante-rol {
    font-size: 0.8rem;
    color: var(--gray);
    text-transform: capitalize;
}

.prueba-original .prueba-fecha {
    font-weight: 600;
    color: var(--dark);
}

.prueba-original .alcoholimetro {
    font-size: 0.8rem;
    color: var(--gray);
}

.nivel-alcohol {
    font-weight: 700;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    font-size: 0.85rem;
}

.nivel-alcohol.leve { 
    background: rgba(52, 152, 219, 0.15); 
    color: var(--primary);
}
.nivel-alcohol.grave { 
    background: rgba(243, 156, 18, 0.15); 
    color: var(--warning);
}
.nivel-alcohol.critica { 
    background: rgba(231, 76, 60, 0.15); 
    color: var(--danger);
}

.motivo-solicitud {
    cursor: help;
    color: var(--dark);
    font-size: 0.9rem;
    line-height: 1.3;
}

.tiempo-espera {
    font-weight: 600;
    font-size: 0.85rem;
}

.retests-previos {
    font-weight: 600;
    font-size: 0.85rem;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    background: #f8f9fa;
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
    .account-form .form-grid { grid-template-columns: 1fr; gap: 1rem; }
    .stats-grid { grid-template-columns: repeat(2, 1fr); }
    .multiple-form .multiple-controls { flex-direction: column; align-items: stretch; }
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
    .modal-content { width: 95%; margin: 1rem; }
    .modal-footer { flex-direction: column; }
}
</style>

<script>
// FUNCIONES JS PARA RE-TESTS PENDIENTES
document.addEventListener('DOMContentLoaded', function() {
    // Seleccionar/deseleccionar todos los checkboxes
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.solicitud-checkbox');
    
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
        });
    }
    
    // Actualizar el estado de "select all" cuando se cambian checkboxes individuales
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const someChecked = Array.from(checkboxes).some(cb => cb.checked);
            
            if (selectAll) {
                selectAll.checked = allChecked;
                selectAll.indeterminate = someChecked && !allChecked;
            }
        });
    });
    
    // Validar formulario de acción múltiple
    const formMultiple = document.getElementById('formMultiple');
    if (formMultiple) {
        formMultiple.addEventListener('submit', function(e) {
            const selectedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            const accion = document.getElementById('accion_multiple').value;
            
            if (selectedCount === 0) {
                e.preventDefault();
                alert('Por favor, seleccione al menos una solicitud para procesar.');
                return false;
            }
            
            if (!accion) {
                e.preventDefault();
                alert('Por favor, seleccione una acción.');
                return false;
            }
            
            if (!confirm(`¿Está seguro de ${accion === 'aprobar' ? 'aprobar' : 'rechazar'} ${selectedCount} solicitud(es) seleccionada(s)?`)) {
                e.preventDefault();
                return false;
            }
        });
    }
});

// Modal para decisión de re-test
function mostrarModalDecision(solicitudId, accion, intervaloDefault) {
    const modal = document.getElementById('modalDecisionRetest');
    const titulo = document.getElementById('modalTituloDecision');
    const boton = document.getElementById('botonDecision');
    const textoBoton = document.getElementById('textoBotonDecision');
    const seccionAprobacion = document.getElementById('seccionAprobacion');
    
    document.getElementById('solicitud_decision_id').value = solicitudId;
    document.getElementById('accion_decision').value = accion;
    document.getElementById('observaciones_decision').value = '';
    
    if (accion === 'aprobar') {
        titulo.textContent = 'Aprobar Re-test';
        textoBoton.textContent = 'Aprobar';
        boton.className = 'btn btn-success';
        seccionAprobacion.style.display = 'block';
        document.getElementById('intervalo_retest').value = intervaloDefault;
    } else {
        titulo.textContent = 'Rechazar Re-test';
        textoBoton.textContent = 'Rechazar';
        boton.className = 'btn btn-danger';
        seccionAprobacion.style.display = 'none';
    }
    
    modal.classList.add('show');
}

function cerrarModalDecision() {
    document.getElementById('modalDecisionRetest').classList.remove('show');
}

// Cerrar modales al hacer clic fuera
document.addEventListener('click', function(event) {
    const modal = document.getElementById('modalDecisionRetest');
    if (event.target === modal) {
        cerrarModalDecision();
    }
});

// Funciones de acción rápida
function mostrarDetallesSolicitud(solicitudId) {
    // Aquí podríamos mostrar un modal con detalles completos
    alert(`Mostrando detalles completos de la solicitud #${solicitudId}\n\nEsta función mostrará información detallada en un modal.`);
}

function aprobarRapido(solicitudId) {
    if (confirm('¿Aprobar esta solicitud de re-test?')) {
        // Simulación de aprobación rápida
        document.getElementById('solicitud_decision_id').value = solicitudId;
        document.getElementById('accion_decision').value = 'aprobar';
        document.getElementById('observaciones_decision').value = 'Aprobado mediante acción rápida';
        document.getElementById('formDecisionRetest').submit();
    }
}

function rechazarRapido(solicitudId) {
    if (confirm('¿Rechazar esta solicitud de re-test?')) {
        // Simulación de rechazo rápido
        document.getElementById('solicitud_decision_id').value = solicitudId;
        document.getElementById('accion_decision').value = 'rechazar';
        document.getElementById('observaciones_decision').value = 'Rechazado mediante acción rápida';
        document.getElementById('formDecisionRetest').submit();
    }
}

// Función para exportar reporte
function exportarReporteRetests() {
    if (confirm('¿Exportar reporte de re-tests pendientes?')) {
        alert('Generando reporte de re-tests pendientes...');
        // Aquí iría la lógica de exportación
    }
}

// Función para filtrar por urgencia
function filtrarPorUrgencia(urgencia) {
    const filas = document.querySelectorAll('.data-table tbody tr');
    filas.forEach(fila => {
        const tiempoEspera = fila.querySelector('.tiempo-espera');
        if (tiempoEspera) {
            const texto = tiempoEspera.textContent.toLowerCase();
            let mostrar = false;
            
            if (urgencia === 'todos') {
                mostrar = true;
            } else if (urgencia === 'urgente' && tiempoEspera.classList.contains('text-danger')) {
                mostrar = true;
            } else if (urgencia === 'medio' && tiempoEspera.classList.contains('text-warning')) {
                mostrar = true;
            } else if (urgencia === 'bajo' && tiempoEspera.classList.contains('text-info')) {
                mostrar = true;
            }
            
            fila.style.display = mostrar ? '' : 'none';
        }
    });
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>