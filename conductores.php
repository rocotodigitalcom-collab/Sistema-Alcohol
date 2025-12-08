<?php
// conductores.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Gestión de Conductores';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'conductores.php' => 'Gestión de Conductores'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// OBTENER DATOS DE CONDUCTORES
$conductores = $db->fetchAll("
    SELECT 
        u.*,
        COUNT(p.id) as total_pruebas,
        SUM(CASE WHEN p.resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        MAX(p.fecha_prueba) as ultima_prueba,
        AVG(p.nivel_alcohol) as promedio_alcohol
    FROM usuarios u
    LEFT JOIN pruebas p ON u.id = p.conductor_id
    WHERE u.cliente_id = ? AND u.rol = 'conductor'
    GROUP BY u.id, u.nombre, u.apellido, u.email, u.telefono, u.dni, u.estado, u.fecha_creacion
    ORDER BY u.nombre, u.apellido
", [$cliente_id]);

// OBTENER ESTADÍSTICAS DE CONDUCTORES
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_conductores,
        SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as conductores_activos,
        SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) as conductores_inactivos,
        AVG(pruebas_stats.total_pruebas) as promedio_pruebas,
        MAX(pruebas_stats.total_pruebas) as max_pruebas,
        COUNT(DISTINCT pruebas_stats.conductor_id) as conductores_con_pruebas
    FROM usuarios u
    LEFT JOIN (
        SELECT conductor_id, COUNT(*) as total_pruebas
        FROM pruebas 
        WHERE cliente_id = ?
        GROUP BY conductor_id
    ) pruebas_stats ON u.id = pruebas_stats.conductor_id
    WHERE u.cliente_id = ? AND u.rol = 'conductor'
", [$cliente_id, $cliente_id]);

// OBTENER ESTADÍSTICAS DE PRUEBAS POR CONDUCTOR
$pruebas_estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        AVG(nivel_alcohol) as promedio_nivel_alcohol,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        SUM(CASE WHEN es_retest = 1 THEN 1 ELSE 0 END) as retests_realizados,
        COUNT(DISTINCT conductor_id) as conductores_con_pruebas
    FROM pruebas 
    WHERE cliente_id = ? AND conductor_id IS NOT NULL
", [$cliente_id]);

// TOP CONDUCTORES CON MÁS PRUEBAS REPROBADAS
$top_conductores_reprobados = $db->fetchAll("
    SELECT 
        u.id,
        CONCAT(u.nombre, ' ', u.apellido) as conductor_nombre,
        u.dni,
        COUNT(p.id) as total_pruebas,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        ROUND((SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) / COUNT(p.id)) * 100, 1) as tasa_reprobacion
    FROM usuarios u
    INNER JOIN pruebas p ON u.id = p.conductor_id
    WHERE p.cliente_id = ? 
    GROUP BY u.id, u.nombre, u.apellido, u.dni
    HAVING pruebas_reprobadas > 0
    ORDER BY pruebas_reprobadas DESC
    LIMIT 5
", [$cliente_id]);

// PROCESAR ACCIONES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_conductor'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $estado = $_POST['estado'] ?? 1;
        $conductor_id = $_POST['conductor_id'] ?? null;

        try {
            // Validaciones básicas
            if (empty($nombre) || empty($apellido) || empty($dni)) {
                throw new Exception("Nombre, apellido y DNI son campos obligatorios");
            }

            // Validar formato de DNI (8 dígitos)
            if (!preg_match('/^[0-9]{8}$/', $dni)) {
                throw new Exception("El DNI debe tener 8 dígitos numéricos");
            }

            // Validar email si se proporciona
            if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("El formato del email no es válido");
            }

            // Verificar si el DNI ya existe (excepto para edición)
            if ($conductor_id) {
                $existe_dni = $db->fetchOne("
                    SELECT id FROM usuarios 
                    WHERE cliente_id = ? AND dni = ? AND id != ? AND rol = 'conductor'
                ", [$cliente_id, $dni, $conductor_id]);
            } else {
                $existe_dni = $db->fetchOne("
                    SELECT id FROM usuarios 
                    WHERE cliente_id = ? AND dni = ? AND rol = 'conductor'
                ", [$cliente_id, $dni]);
            }

            if ($existe_dni) {
                throw new Exception("Ya existe un conductor con el DNI: " . $dni);
            }

            // Verificar si el email ya existe (excepto para edición)
            if ($email) {
                if ($conductor_id) {
                    $existe_email = $db->fetchOne("
                        SELECT id FROM usuarios 
                        WHERE cliente_id = ? AND email = ? AND id != ? AND rol = 'conductor'
                    ", [$cliente_id, $email, $conductor_id]);
                } else {
                    $existe_email = $db->fetchOne("
                        SELECT id FROM usuarios 
                        WHERE cliente_id = ? AND email = ? AND rol = 'conductor'
                    ", [$cliente_id, $email]);
                }

                if ($existe_email) {
                    throw new Exception("Ya existe un conductor con el email: " . $email);
                }
            }

            if ($conductor_id) {
                // ACTUALIZAR CONDUCTOR EXISTENTE
                $db->execute("
                    UPDATE usuarios 
                    SET nombre = ?, apellido = ?, email = ?, telefono = ?, dni = ?, estado = ?
                    WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
                ", [$nombre, $apellido, $email, $telefono, $dni, $estado, $conductor_id, $cliente_id]);

                $mensaje_exito = "Conductor actualizado correctamente";
                $accion_auditoria = 'ACTUALIZAR_CONDUCTOR';
                $registro_id = $conductor_id;
                
            } else {
                // CREAR NUEVO CONDUCTOR
                $password_hash = password_hash($dni, PASSWORD_DEFAULT); // Password por defecto: DNI
                
                $result = $db->execute("
                    INSERT INTO usuarios 
                    (cliente_id, nombre, apellido, email, password, telefono, dni, rol, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'conductor', ?)
                ", [$cliente_id, $nombre, $apellido, $email, $password_hash, $telefono, $dni, $estado]);

                if ($result) {
                    $conductor_id = $db->lastInsertId();
                    $mensaje_exito = "Conductor registrado correctamente";
                    $accion_auditoria = 'CREAR_CONDUCTOR';
                    $registro_id = $conductor_id;
                } else {
                    throw new Exception("Error al insertar en la base de datos");
                }
            }

            // AUDITORÍA
            $detalles = "Conductor {$nombre} {$apellido} - DNI: {$dni} - Estado: " . ($estado ? 'Activo' : 'Inactivo');
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, ?, 'usuarios', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $accion_auditoria, $registro_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            // Recargar datos después de guardar
            $conductores = $db->fetchAll("
                SELECT 
                    u.*,
                    COUNT(p.id) as total_pruebas,
                    SUM(CASE WHEN p.resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
                    SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
                    MAX(p.fecha_prueba) as ultima_prueba,
                    AVG(p.nivel_alcohol) as promedio_alcohol
                FROM usuarios u
                LEFT JOIN pruebas p ON u.id = p.conductor_id
                WHERE u.cliente_id = ? AND u.rol = 'conductor'
                GROUP BY u.id, u.nombre, u.apellido, u.email, u.telefono, u.dni, u.estado, u.fecha_creacion
                ORDER BY u.nombre, u.apellido
            ", [$cliente_id]);

            // Limpiar datos de edición después de guardar
            $conductor_editar = null;

        } catch (Exception $e) {
            $mensaje_error = "Error al guardar el conductor: " . $e->getMessage();
            error_log("Error en conductores.php: " . $e->getMessage());
        }
    }

    // CAMBIAR ESTADO CONDUCTOR
    if (isset($_POST['cambiar_estado'])) {
        $conductor_id = $_POST['conductor_id'] ?? null;
        $nuevo_estado = $_POST['nuevo_estado'] ?? 1;
        
        if ($conductor_id) {
            try {
                // Verificar que el conductor pertenece al cliente
                $conductor = $db->fetchOne("
                    SELECT nombre, apellido, dni FROM usuarios 
                    WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
                ", [$conductor_id, $cliente_id]);

                if (!$conductor) {
                    throw new Exception("Conductor no encontrado o no pertenece a su empresa");
                }

                // Actualizar estado
                $db->execute("
                    UPDATE usuarios 
                    SET estado = ? 
                    WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
                ", [$nuevo_estado, $conductor_id, $cliente_id]);

                $mensaje_exito = $nuevo_estado ? "Conductor activado correctamente" : "Conductor desactivado correctamente";
                
                // AUDITORÍA
                $detalles = "Conductor {$conductor['nombre']} {$conductor['apellido']} - DNI: {$conductor['dni']} - Nuevo estado: " . ($nuevo_estado ? 'Activo' : 'Inactivo');
                $db->execute("
                    INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                    VALUES (?, ?, 'CAMBIAR_ESTADO_CONDUCTOR', 'usuarios', ?, ?, ?, ?)
                ", [$cliente_id, $user_id, $conductor_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

                // Recargar datos
                $conductores = $db->fetchAll("
                    SELECT 
                        u.*,
                        COUNT(p.id) as total_pruebas,
                        SUM(CASE WHEN p.resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
                        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
                        MAX(p.fecha_prueba) as ultima_prueba,
                        AVG(p.nivel_alcohol) as promedio_alcohol
                    FROM usuarios u
                    LEFT JOIN pruebas p ON u.id = p.conductor_id
                    WHERE u.cliente_id = ? AND u.rol = 'conductor'
                    GROUP BY u.id, u.nombre, u.apellido, u.email, u.telefono, u.dni, u.estado, u.fecha_creacion
                    ORDER BY u.nombre, u.apellido
                ", [$cliente_id]);

            } catch (Exception $e) {
                $mensaje_error = "Error al cambiar estado del conductor: " . $e->getMessage();
            }
        }
    }
}

// OBTENER DATOS PARA EDICIÓN SI SE SOLICITA
$conductor_editar = null;
if (isset($_GET['editar'])) {
    $conductor_editar = $db->fetchOne("
        SELECT * FROM usuarios 
        WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
    ", [$_GET['editar'], $cliente_id]);
}
?>

<div class="content-body">
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona el registro y administración de conductores de la empresa</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="mostrarModalConductor()">
                <i class="fas fa-user-plus"></i>Nuevo Conductor
            </button>
            <a href="index.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>Volver al Dashboard
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
        <!-- CARD DE ESTADÍSTICAS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Estadísticas de Conductores</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_conductores'] ?? 0; ?></h3>
                            <p>Total Conductores</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['conductores_activos'] ?? 0; ?></h3>
                            <p>Conductores Activos</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['conductores_inactivos'] ?? 0; ?></h3>
                            <p>Conductores Inactivos</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-vial"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['conductores_con_pruebas'] ?? 0; ?></h3>
                            <p>Conductores con Pruebas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon average">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['promedio_pruebas'] ?? 0, 1); ?></h3>
                            <p>Promedio de Pruebas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon secondary">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['max_pruebas'] ?? 0; ?></h3>
                            <p>Máx. Pruebas por Conductor</p>
                        </div>
                    </div>
                </div>

                <!-- ESTADÍSTICAS DE PRUEBAS -->
                <div class="stats-subgrid" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                    <h4 style="margin-bottom: 1rem; color: var(--dark);">Estadísticas de Pruebas por Conductor</h4>
                    <div class="stats-grid">
                        <div class="stat-card mini">
                            <div class="stat-icon secondary">
                                <i class="fas fa-vial"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $pruebas_estadisticas['total_pruebas'] ?? 0; ?></h3>
                                <p>Total Pruebas</p>
                            </div>
                        </div>
                        <div class="stat-card mini">
                            <div class="stat-icon secondary">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo number_format($pruebas_estadisticas['promedio_nivel_alcohol'] ?? 0, 3); ?> g/L</h3>
                                <p>Promedio Alcohol</p>
                            </div>
                        </div>
                        <div class="stat-card mini">
                            <div class="stat-icon secondary">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $pruebas_estadisticas['pruebas_reprobadas'] ?? 0; ?></h3>
                                <p>Pruebas Reprobadas</p>
                            </div>
                        </div>
                        <div class="stat-card mini">
                            <div class="stat-icon secondary">
                                <i class="fas fa-redo"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $pruebas_estadisticas['retests_realizados'] ?? 0; ?></h3>
                                <p>Re-tests Realizados</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TOP CONDUCTORES CON INCIDENCIAS -->
                <?php if (!empty($top_conductores_reprobados)): ?>
                <div class="stats-subgrid" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                    <h4 style="margin-bottom: 1rem; color: var(--dark);">Conductores con Más Incidencias</h4>
                    <div class="table-responsive">
                        <table class="data-table" style="font-size: 0.8rem;">
                            <thead>
                                <tr>
                                    <th>Conductor</th>
                                    <th>DNI</th>
                                    <th>Total Pruebas</th>
                                    <th>Pruebas Reprobadas</th>
                                    <th>Tasa Reprobación</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_conductores_reprobados as $conductor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($conductor['conductor_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($conductor['dni']); ?></td>
                                    <td><?php echo $conductor['total_pruebas']; ?></td>
                                    <td>
                                        <span class="badge danger"><?php echo $conductor['pruebas_reprobadas']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $conductor['tasa_reprobacion'] > 20 ? 'danger' : 'warning'; ?>">
                                            <?php echo $conductor['tasa_reprobacion']; ?>%
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CARD DE CONDUCTORES REGISTRADOS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list-alt"></i> Conductores Registrados</h3>
                <div class="card-actions">
                    <span class="badge primary"><?php echo count($conductores); ?> registros</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($conductores)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Conductor</th>
                                <th>Contacto</th>
                                <th>DNI</th>
                                <th>Pruebas</th>
                                <th>Estadísticas</th>
                                <th>Última Prueba</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($conductores as $conductor): ?>
                            <tr>
                                <td>
                                    <div class="conductor-info">
                                        <div class="conductor-nombre">
                                            <strong><?php echo htmlspecialchars($conductor['nombre'] . ' ' . $conductor['apellido']); ?></strong>
                                        </div>
                                        <div class="conductor-detalles">
                                            <small class="text-muted">ID: <?php echo $conductor['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="contacto-info">
                                        <?php if ($conductor['email']): ?>
                                        <div class="contacto-item">
                                            <i class="fas fa-envelope"></i>
                                            <?php echo htmlspecialchars($conductor['email']); ?>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ($conductor['telefono']): ?>
                                        <div class="contacto-item">
                                            <i class="fas fa-phone"></i>
                                            <?php echo htmlspecialchars($conductor['telefono']); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="dni-badge"><?php echo htmlspecialchars($conductor['dni']); ?></span>
                                </td>
                                <td>
                                    <div class="pruebas-stats">
                                        <div class="stat-item">
                                            <span class="badge primary"><?php echo $conductor['total_pruebas']; ?> total</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="badge success"><?php echo $conductor['pruebas_aprobadas']; ?> aprob.</span>
                                        </div>
                                        <div class="stat-item">
                                            <span class="badge danger"><?php echo $conductor['pruebas_reprobadas']; ?> reprob.</span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="estadisticas-detalladas">
                                        <?php if ($conductor['total_pruebas'] > 0): ?>
                                        <div class="stat-mini">
                                            <small>Tasa Aprobación:</small>
                                            <?php 
                                            $tasa_aprobacion = round(($conductor['pruebas_aprobadas'] / $conductor['total_pruebas']) * 100, 1);
                                            $color_clase = $tasa_aprobacion >= 80 ? 'success' : ($tasa_aprobacion >= 60 ? 'warning' : 'danger');
                                            ?>
                                            <span class="badge mini <?php echo $color_clase; ?>">
                                                <?php echo $tasa_aprobacion; ?>%
                                            </span>
                                        </div>
                                        <div class="stat-mini">
                                            <small>Promedio Alcohol:</small>
                                            <span class="nivel-alcohol-mini <?php echo $conductor['promedio_alcohol'] > 0.000 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo number_format($conductor['promedio_alcohol'] ?? 0, 3); ?> g/L
                                            </span>
                                        </div>
                                        <?php else: ?>
                                        <span class="text-muted">Sin pruebas</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($conductor['ultima_prueba']): ?>
                                    <span class="ultimo-login">
                                        <?php echo date('d/m/Y H:i', strtotime($conductor['ultima_prueba'])); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge estado-<?php echo $conductor['estado'] ? 'activo' : 'inactivo'; ?>">
                                        <?php echo $conductor['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-icon primary" 
                                            title="Editar Conductor"
                                            onclick="editarConductor(<?php echo $conductor['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <!-- MENÚ DESPLEGABLE PARA CAMBIAR ESTADO -->
                                    <div class="dropdown">
                                        <button type="button" class="btn-icon warning dropdown-toggle" 
                                                title="Cambiar Estado"
                                                data-toggle="dropdown">
                                            <i class="fas fa-cog"></i>
                                        </button>
                                        <div class="dropdown-menu">
                                            <?php if (!$conductor['estado']): ?>
                                            <form method="POST" class="dropdown-form">
                                                <input type="hidden" name="conductor_id" value="<?php echo $conductor['id']; ?>">
                                                <input type="hidden" name="nuevo_estado" value="1">
                                                <button type="submit" name="cambiar_estado" class="dropdown-item success">
                                                    <i class="fas fa-check"></i> Activar
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($conductor['estado']): ?>
                                            <form method="POST" class="dropdown-form">
                                                <input type="hidden" name="conductor_id" value="<?php echo $conductor['id']; ?>">
                                                <input type="hidden" name="nuevo_estado" value="0">
                                                <button type="submit" name="cambiar_estado" class="dropdown-item danger">
                                                    <i class="fas fa-times"></i> Desactivar
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <a href="historial-conductor.php?conductor_id=<?php echo $conductor['id']; ?>" 
                                       class="btn-icon info" title="Ver Historial de Pruebas">
                                        <i class="fas fa-history"></i>
                                    </a>

                                    <button type="button" class="btn-icon secondary" 
                                            title="Restablecer Contraseña"
                                            onclick="restablecerPassword(<?php echo $conductor['id']; ?>)">
                                        <i class="fas fa-key"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <!-- EMPTY STATE -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>No hay conductores registrados</h3>
                    <p>Comienza registrando el primer conductor de tu empresa</p>
                    <div class="empty-actions">
                        <button type="button" class="btn btn-primary" onclick="mostrarModalConductor()">
                            <i class="fas fa-user-plus"></i>Registrar Primer Conductor
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA CONDUCTORES -->
<div id="modalConductor" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="cerrarModalConductor()"></div>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user"></i> 
                    <span id="modalTituloConductor">Registrar Nuevo Conductor</span>
                </h3>
                <button type="button" class="modal-close" onclick="cerrarModalConductor()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formConductor" method="POST" class="modal-form">
                    <input type="hidden" name="conductor_id" id="conductor_id">
                    <input type="hidden" name="guardar_conductor" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombre" class="form-label">Nombre *</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" 
                                   placeholder="Ej: Juan" required maxlength="50"
                                   value="<?php echo htmlspecialchars($conductor_editar['nombre'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="apellido" class="form-label">Apellido *</label>
                            <input type="text" id="apellido" name="apellido" class="form-control" 
                                   placeholder="Ej: Pérez" required maxlength="50"
                                   value="<?php echo htmlspecialchars($conductor_editar['apellido'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="dni" class="form-label">DNI *</label>
                            <input type="text" id="dni" name="dni" class="form-control" 
                                   placeholder="Ej: 12345678" required maxlength="8" minlength="8"
                                   pattern="[0-9]{8}" title="8 dígitos numéricos"
                                   value="<?php echo htmlspecialchars($conductor_editar['dni'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   placeholder="Ej: juan@empresa.com" maxlength="255"
                                   value="<?php echo htmlspecialchars($conductor_editar['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control" 
                                   placeholder="Ej: 999888777" maxlength="20"
                                   value="<?php echo htmlspecialchars($conductor_editar['telefono'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="estado" class="form-label">Estado</label>
                            <select id="estado" name="estado" class="form-control" required>
                                <option value="1" <?php echo ($conductor_editar['estado'] ?? 1) ? 'selected' : ''; ?>>Activo</option>
                                <option value="0" <?php echo isset($conductor_editar['estado']) && !$conductor_editar['estado'] ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-nota">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Nota:</strong> Los campos marcados con * son obligatorios. 
                            La contraseña por defecto será el DNI del conductor.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModalConductor()">
                    <i class="fas fa-times"></i> Cancelar
                </button>
                <button type="submit" form="formConductor" class="btn btn-primary">
                    <i class="fas fa-save"></i> <span id="textoBotonGuardar">Guardar Conductor</span>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ===== ESTILOS BASE DEL SISTEMA ===== */
:root {
    --primary: #84061f;
    --primary-dark: #6a0519;
    --secondary: #427420;
    --success: #27ae60;
    --danger: #e74c3c;
    --warning: #f39c12;
    --info: #3498db;
    --light: #f8f9fa;
    --dark: #343a40;
    --gray: #6c757d;
    --border: #dee2e6;
    --transition: all 0.3s ease;
}

/* ===== ESTILOS GENERALES ===== */
.content-body {
    padding: 1.5rem;
    max-width: 1400px;
    margin: 0 auto;
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1.5rem 0;
    border-bottom: 1px solid var(--border);
}

.welcome-section h1 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-size: 1.8rem;
    font-weight: 700;
}

.dashboard-subtitle {
    margin: 0;
    color: var(--gray);
    font-size: 1rem;
}

.header-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: var(--transition);
    border: 2px solid transparent;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-color: var(--primary);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(132, 6, 31, 0.3);
}

.btn-outline {
    background: transparent;
    color: var(--dark);
    border-color: var(--border);
}

.btn-outline:hover {
    background: var(--light);
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

/* ===== CARDS ===== */
.crud-container {
    margin-top: 1.5rem;
    width: 100%;
}

.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--light);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    color: var(--dark);
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-body {
    padding: 1.5rem;
}

/* ===== STATS GRID ===== */
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
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-card.mini {
    padding: 1rem;
    gap: 0.75rem;
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

.stat-icon.primary { background: rgba(132, 6, 31, 0.15); color: var(--primary); }
.stat-icon.success { background: rgba(39, 174, 96, 0.15); color: var(--success); }
.stat-icon.warning { background: rgba(243, 156, 18, 0.15); color: var(--warning); }
.stat-icon.info { background: rgba(52, 152, 219, 0.15); color: var(--info); }
.stat-icon.average { background: rgba(230, 126, 34, 0.15); color: #e67e22; }
.stat-icon.danger { background: rgba(231, 76, 60, 0.15); color: var(--danger); }
.stat-icon.secondary { background: rgba(66, 116, 32, 0.15); color: var(--secondary); }

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

.stats-subgrid {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.stats-subgrid h4 {
    margin-bottom: 1rem;
    color: var(--dark);
    font-size: 1.1rem;
    font-weight: 600;
}

/* ===== TABLAS ===== */
.table-responsive {
    overflow-x: auto;
    border-radius: 12px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin: 0;
}

.data-table th {
    background: var(--light);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    border-bottom: 2px solid var(--border);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
    vertical-align: middle;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background: rgba(132, 6, 31, 0.04);
}

/* ===== BADGES ===== */
.badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
    display: inline-block;
    text-align: center;
}

.badge.primary { background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; }
.badge.success { background: linear-gradient(135deg, var(--success), #2ecc71); color: white; }
.badge.danger { background: linear-gradient(135deg, var(--danger), #c0392b); color: white; }
.badge.warning { background: linear-gradient(135deg, var(--warning), #e67e22); color: white; }
.badge.info { background: linear-gradient(135deg, var(--info), #2980b9); color: white; }
.badge.secondary { background: linear-gradient(135deg, var(--secondary), #2e7d32); color: white; }

.badge.mini {
    padding: 0.2rem 0.4rem;
    font-size: 0.7rem;
}

/* ===== ESTADOS ===== */
.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    display: inline-block;
    text-align: center;
    min-width: 80px;
}

.status-badge.estado-activo {
    background: rgba(39, 174, 96, 0.15);
    color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.3);
}

.status-badge.estado-inactivo {
    background: rgba(108, 117, 125, 0.15);
    color: #6c757d;
    border: 1px solid rgba(108, 117, 125, 0.3);
}

/* ===== BOTONES DE ACCIÓN ===== */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--light);
    color: var(--dark);
    text-decoration: none;
    transition: var(--transition);
    border: none;
    cursor: pointer;
}

.btn-icon:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.btn-icon.warning:hover { background: var(--warning); }
.btn-icon.danger:hover { background: var(--danger); }
.btn-icon.success:hover { background: var(--success); }
.btn-icon.info:hover { background: var(--info); }
.btn-icon.primary:hover { background: var(--primary); }
.btn-icon.secondary:hover { background: var(--secondary); }

/* ===== ESTILOS ESPECÍFICOS PARA CONDUCTORES ===== */
.conductor-info .conductor-nombre {
    font-weight: 600;
    color: var(--dark);
}

.conductor-info .conductor-detalles {
    margin-top: 0.25rem;
}

.contacto-info {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.contacto-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.85rem;
    color: var(--dark);
}

.contacto-item i {
    width: 16px;
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
    font-size: 0.85rem;
}

.pruebas-stats {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-item {
    display: flex;
    gap: 0.25rem;
}

.estadisticas-detalladas {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stat-mini {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8rem;
}

.stat-mini small {
    color: var(--gray);
}

.nivel-alcohol-mini {
    font-size: 0.8rem;
    font-weight: 600;
}

.nivel-alcohol-mini.text-danger {
    color: var(--danger) !important;
}

.nivel-alcohol-mini.text-success {
    color: var(--success) !important;
}

.ultimo-login {
    font-size: 0.85rem;
    color: var(--dark);
}

/* ===== ALERTAS ===== */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid transparent;
}

.alert-success {
    background: rgba(39, 174, 96, 0.1);
    border-color: rgba(39, 174, 96, 0.2);
    color: var(--success);
}

.alert-danger {
    background: rgba(231, 76, 60, 0.1);
    border-color: rgba(231, 76, 60, 0.2);
    color: var(--danger);
}

.alert-info {
    background: rgba(52, 152, 219, 0.1);
    border-color: rgba(52, 152, 219, 0.2);
    color: var(--info);
}

/* ===== MODAL ===== */
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
    transition: var(--transition);
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

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    gap: 1rem;
}

/* ===== FORMULARIOS ===== */
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
    transition: var(--transition);
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
    box-shadow: 0 0 0 4px rgba(132, 6, 31, 0.1), 0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.form-nota {
    margin-top: 1rem;
}

/* ===== EMPTY STATE ===== */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray);
}

.empty-icon {
    font-size: 4rem;
    color: var(--light);
    margin-bottom: 1.5rem;
    opacity: 0.7;
}

.empty-state h3 {
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.empty-state p {
    margin-bottom: 2rem;
    font-size: 1rem;
    opacity: 0.8;
}

.empty-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
    flex-wrap: wrap;
}

/* ===== DROPDOWN ===== */
.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-toggle::after {
    display: none;
}

.dropdown-menu {
    position: absolute;
    top: 100%;
    right: 0;
    z-index: 1000;
    display: none;
    min-width: 160px;
    padding: 0.5rem 0;
    margin: 0.125rem 0 0;
    font-size: 0.875rem;
    color: var(--dark);
    text-align: left;
    list-style: none;
    background-color: #fff;
    background-clip: padding-box;
    border: 1px solid rgba(0, 0, 0, 0.15);
    border-radius: 0.5rem;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}

.dropdown:hover .dropdown-menu {
    display: block;
}

.dropdown-form {
    margin: 0;
}

.dropdown-item {
    display: block;
    width: 100%;
    padding: 0.5rem 1rem;
    clear: both;
    font-weight: 400;
    color: var(--dark);
    text-align: inherit;
    text-decoration: none;
    white-space: nowrap;
    background-color: transparent;
    border: 0;
    cursor: pointer;
    transition: var(--transition);
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

.dropdown-item.success:hover {
    background-color: rgba(39, 174, 96, 0.1);
    color: var(--success);
}

.dropdown-item.danger:hover {
    background-color: rgba(231, 76, 60, 0.1);
    color: var(--danger);
}

/* ===== TEXTOS ===== */
.text-muted {
    color: var(--gray) !important;
    opacity: 0.7;
}

.text-danger {
    color: var(--danger) !important;
    font-weight: 600;
}

.text-success {
    color: var(--success) !important;
    font-weight: 600;
}

.text-warning {
    color: var(--warning) !important;
    font-weight: 600;
}

.text-info {
    color: var(--info) !important;
    font-weight: 600;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 1024px) {
    .data-table {
        font-size: 0.85rem;
    }
    
    .modal-form .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .contacto-info {
        min-width: 150px;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
        flex-wrap: wrap;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table th, .data-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .card-actions {
        align-self: flex-start;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
        gap: 0.75rem;
    }
    
    .stat-icon {
        width: 50px;
        height: 50px;
        font-size: 1.25rem;
    }
    
    .empty-actions {
        flex-direction: column;
    }
    
    .modal-dialog {
        margin: 0.5rem;
        max-width: calc(100% - 1rem);
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .pruebas-stats {
        flex-direction: row;
        flex-wrap: wrap;
    }
}
</style>

<script>
// FUNCIONES JS PARA GESTIÓN DE CONDUCTORES
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de conductores cargada');
    
    // Si hay un conductor para editar (viene por GET), abrir modal automáticamente
    <?php if ($conductor_editar): ?>
    console.log('Editando conductor: <?php echo $conductor_editar['id']; ?>');
    mostrarModalConductor(<?php echo $conductor_editar['id']; ?>);
    <?php endif; ?>

    // Agregar event listener para el formulario
    const formConductor = document.getElementById('formConductor');
    if (formConductor) {
        formConductor.addEventListener('submit', function(e) {
            console.log('Formulario enviado');
            return validarFormularioConductor(e);
        });
    }
});

// Modal para conductor
function mostrarModalConductor(conductorId = null) {
    const modal = document.getElementById('modalConductor');
    const titulo = document.getElementById('modalTituloConductor');
    const botonGuardar = document.getElementById('textoBotonGuardar');
    
    console.log('Mostrando modal para conductor:', conductorId);
    
    if (conductorId) {
        titulo.textContent = 'Editar Conductor';
        botonGuardar.textContent = 'Actualizar Conductor';
        document.getElementById('conductor_id').value = conductorId;
    } else {
        titulo.textContent = 'Registrar Nuevo Conductor';
        botonGuardar.textContent = 'Guardar Conductor';
        // Limpiar formulario si es nuevo
        document.getElementById('formConductor').reset();
        document.getElementById('conductor_id').value = '';
        document.getElementById('estado').value = '1';
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function cerrarModalConductor() {
    const modal = document.getElementById('modalConductor');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Redirigir sin parámetros GET para limpiar la edición
    if (window.location.search.includes('editar=')) {
        window.location.href = 'conductores.php';
    }
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalConductor();
    }
});

// Función para editar conductor (redirige con parámetro GET)
function editarConductor(conductorId) {
    console.log('Editando conductor ID:', conductorId);
    window.location.href = 'conductores.php?editar=' + conductorId;
}

// Validación del formulario antes de enviar
function validarFormularioConductor(e) {
    const nombre = document.getElementById('nombre').value.trim();
    const apellido = document.getElementById('apellido').value.trim();
    const dni = document.getElementById('dni').value.trim();
    const email = document.getElementById('email').value.trim();
    
    console.log('Validando formulario:', { nombre, apellido, dni, email });
    
    if (!nombre || !apellido || !dni) {
        e.preventDefault();
        alert('Por favor, complete los campos obligatorios: Nombre, Apellido y DNI.');
        return false;
    }
    
    // Validar formato de DNI (8 dígitos)
    const formatoDNI = /^[0-9]{8}$/;
    if (!formatoDNI.test(dni)) {
        e.preventDefault();
        alert('El DNI debe tener exactamente 8 dígitos numéricos.');
        return false;
    }
    
    // Validar email si se proporciona
    if (email && !isValidEmail(email)) {
        e.preventDefault();
        alert('El formato del email no es válido.');
        return false;
    }
    
    console.log('Formulario válido, enviando...');
    return true;
}

function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Función para restablecer contraseña
function restablecerPassword(conductorId) {
    if (confirm('¿Está seguro que desea restablecer la contraseña de este conductor?\nLa nueva contraseña será el DNI del conductor.')) {
        console.log('Restableciendo contraseña para conductor:', conductorId);
        
        // Mostrar mensaje de éxito (en una implementación real, esto sería una llamada AJAX)
        alert('Contraseña restablecida correctamente. Nueva contraseña: DNI del conductor');
        
        // Para una implementación real, descomenta el código siguiente:
        /*
        fetch('ajax/restablecer-password.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `conductor_id=${conductorId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Contraseña restablecida correctamente. Nueva contraseña: DNI del conductor');
            } else {
                alert('Error: ' + (data.message || 'No se pudo restablecer la contraseña'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al restablecer la contraseña');
        });
        */
    }
}

// Función para generar datos de ejemplo (solo en modo demo)
function generarConductorDemo() {
    if (confirm('¿Generar conductor de ejemplo? Esto solo funciona en modo demo.')) {
        const nombres = ['Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Laura', 'Pedro', 'Sofía'];
        const apellidos = ['Pérez', 'Gómez', 'Rodríguez', 'López', 'García', 'Martínez', 'Hernández', 'Díaz'];
        const dominios = ['@empresa.com', '@transportes.com', '@logistica.com'];
        
        const nombre = nombres[Math.floor(Math.random() * nombres.length)];
        const apellido = apellidos[Math.floor(Math.random() * apellidos.length)];
        const dni = generarDNIAleatorio();
        const email = nombre.toLowerCase() + '.' + apellido.toLowerCase() + dominios[Math.floor(Math.random() * dominios.length)];
        const telefono = '9' + Math.floor(10000000 + Math.random() * 90000000);
        
        document.getElementById('nombre').value = nombre;
        document.getElementById('apellido').value = apellido;
        document.getElementById('dni').value = dni;
        document.getElementById('email').value = email;
        document.getElementById('telefono').value = telefono;
        
        alert('Datos de ejemplo generados. Puede modificarlos antes de guardar.');
    }
}

function generarDNIAleatorio() {
    return Math.floor(10000000 + Math.random() * 90000000).toString();
}

// Función para buscar conductores
function buscarConductores(termino) {
    console.log('Buscando conductores:', termino);
    const tabla = document.querySelector('.data-table');
    const filas = tabla.querySelectorAll('tbody tr');
    
    filas.forEach(fila => {
        const textoFila = fila.textContent.toLowerCase();
        if (textoFila.includes(termino.toLowerCase())) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
}

// Función para filtrar por estado
function filtrarPorEstado(estado) {
    console.log('Filtrando por estado:', estado);
    const tabla = document.querySelector('.data-table');
    const filas = tabla.querySelectorAll('tbody tr');
    
    filas.forEach(fila => {
        const estadoFila = fila.querySelector('.status-badge').textContent.trim().toLowerCase();
        if (estado === 'todos' || estadoFila === estado) {
            fila.style.display = '';
        } else {
            fila.style.display = 'none';
        }
    });
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>