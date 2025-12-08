<?php
// registrar-conductor.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Registrar Conductor';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'registrar-conductor.php' => 'Registrar Conductor'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// OBTENER DATOS PARA FILTROS Y SELECTS
$conductores = $db->fetchAll("
    SELECT id, nombre, apellido, dni, email, telefono, estado, ultimo_login, fecha_creacion
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor'
    ORDER BY nombre, apellido
", [$cliente_id]);

// OBTENER ESTADÍSTICAS DE CONDUCTORES
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_conductores,
        SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN estado = 0 THEN 1 ELSE 0 END) as inactivos,
        COUNT(DISTINCT dni) as dni_unicos,
        AVG(TIMESTAMPDIFF(DAY, fecha_creacion, NOW())) as antiguedad_promedio_dias,
        COUNT(CASE WHEN ultimo_login IS NOT NULL THEN 1 END) as con_acceso_sistema
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor'
", [$cliente_id]);

// OBTENER PRUEBAS RECIENTES DE CONDUCTORES PARA ESTADÍSTICAS ADICIONALES
$pruebas_estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        COUNT(DISTINCT conductor_id) as conductores_con_pruebas,
        AVG(nivel_alcohol) as promedio_nivel_alcohol,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        SUM(CASE WHEN es_retest = 1 THEN 1 ELSE 0 END) as retests_realizados
    FROM pruebas 
    WHERE cliente_id = ? AND conductor_id IN (
        SELECT id FROM usuarios WHERE cliente_id = ? AND rol = 'conductor'
    )
", [$cliente_id, $cliente_id]);

// PROCESAR ACCIONES POST - CORREGIDO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_conductor'])) {
        $nombre = trim($_POST['nombre'] ?? '');
        $apellido = trim($_POST['apellido'] ?? '');
        $dni = trim($_POST['dni'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $telefono = trim($_POST['telefono'] ?? '');
        $estado = $_POST['estado'] ?? 1;
        $conductor_id = $_POST['conductor_id'] ?? null;

        // DEBUG: Ver qué datos llegan
        error_log("Datos recibidos - Nombre: $nombre, Apellido: $apellido, DNI: $dni");

        try {
            // Validaciones básicas MEJORADAS
            if (empty($nombre) || empty($apellido) || empty($dni)) {
                throw new Exception("Nombre, apellido y DNI son campos obligatorios");
            }

            // Validar formato de DNI
            if (!preg_match('/^[0-9]{8,15}$/', $dni)) {
                throw new Exception("El DNI debe contener solo números y tener entre 8 y 15 dígitos");
            }

            // Validar email si se proporciona
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
            if (!empty($email)) {
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
                    SET nombre = ?, apellido = ?, dni = ?, email = ?, 
                        telefono = ?, estado = ?
                    WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
                ", [$nombre, $apellido, $dni, $email, $telefono, $estado, $conductor_id, $cliente_id]);

                $mensaje_exito = "Conductor actualizado correctamente";
                $accion_auditoria = 'ACTUALIZAR_CONDUCTOR';
                $registro_id = $conductor_id;
                
            } else {
                // CREAR NUEVO CONDUCTOR - CORREGIDO
                // Generar password temporal más seguro
                $password_temporal = bin2hex(random_bytes(4)); // 8 caracteres hex
                $password_hash = password_hash($password_temporal, PASSWORD_DEFAULT);
                
                $result = $db->execute("
                    INSERT INTO usuarios 
                    (cliente_id, nombre, apellido, dni, email, telefono, password, rol, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'conductor', ?)
                ", [$cliente_id, $nombre, $apellido, $dni, $email, $telefono, $password_hash, $estado]);

                if ($result) {
                    $conductor_id = $db->lastInsertId();
                    $mensaje_exito = "Conductor registrado correctamente. Password temporal: <strong>" . $password_temporal . "</strong>";
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
                SELECT id, nombre, apellido, dni, email, telefono, estado, ultimo_login, fecha_creacion
                FROM usuarios 
                WHERE cliente_id = ? AND rol = 'conductor'
                ORDER BY nombre, apellido
            ", [$cliente_id]);

            // Limpiar datos de edición después de guardar
            $conductor_editar = null;

        } catch (Exception $e) {
            $mensaje_error = "Error al guardar el conductor: " . $e->getMessage();
            error_log("Error en registrar-conductor: " . $e->getMessage());
        }
    }

    // ELIMINAR CONDUCTOR - CORREGIDO
    if (isset($_POST['eliminar_conductor'])) {
        $conductor_id = $_POST['conductor_id'] ?? null;
        
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

                // Verificar si el conductor tiene pruebas asociadas
                $pruebas_asociadas = $db->fetchOne("
                    SELECT COUNT(*) as total 
                    FROM pruebas 
                    WHERE conductor_id = ? AND cliente_id = ?
                ", [$conductor_id, $cliente_id]);

                if ($pruebas_asociadas['total'] > 0) {
                    throw new Exception("No se puede eliminar el conductor porque tiene pruebas asociadas");
                }

                // Eliminar conductor (soft delete - cambiar estado a 0)
                $db->execute("
                    UPDATE usuarios 
                    SET estado = 0 
                    WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
                ", [$conductor_id, $cliente_id]);

                $mensaje_exito = "Conductor desactivado correctamente";
                
                // AUDITORÍA
                $detalles = "Conductor desactivado: {$conductor['nombre']} {$conductor['apellido']} - DNI: {$conductor['dni']}";
                $db->execute("
                    INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                    VALUES (?, ?, 'DESACTIVAR_CONDUCTOR', 'usuarios', ?, ?, ?, ?)
                ", [$cliente_id, $user_id, $conductor_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

                // Recargar datos
                $conductores = $db->fetchAll("
                    SELECT id, nombre, apellido, dni, email, telefono, estado, ultimo_login, fecha_creacion
                    FROM usuarios 
                    WHERE cliente_id = ? AND rol = 'conductor'
                    ORDER BY nombre, apellido
                ", [$cliente_id]);

            } catch (Exception $e) {
                $mensaje_error = "Error al eliminar el conductor: " . $e->getMessage();
            }
        }
    }

    // REACTIVAR CONDUCTOR - CORREGIDO
    if (isset($_POST['reactivar_conductor'])) {
        $conductor_id = $_POST['conductor_id'] ?? null;
        
        if ($conductor_id) {
            try {
                $db->execute("
                    UPDATE usuarios 
                    SET estado = 1 
                    WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
                ", [$conductor_id, $cliente_id]);

                $mensaje_exito = "Conductor reactivado correctamente";
                
                // Recargar datos
                $conductores = $db->fetchAll("
                    SELECT id, nombre, apellido, dni, email, telefono, estado, ultimo_login, fecha_creacion
                    FROM usuarios 
                    WHERE cliente_id = ? AND rol = 'conductor'
                    ORDER BY nombre, apellido
                ", [$cliente_id]);

            } catch (Exception $e) {
                $mensaje_error = "Error al reactivar el conductor: " . $e->getMessage();
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
    <!-- HEADER IDÉNTICO -->
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
                            <h3><?php echo $estadisticas['activos'] ?? 0; ?></h3>
                            <p>Conductores Activos</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['con_acceso_sistema'] ?? 0; ?></h3>
                            <p>Con Acceso al Sistema</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['dni_unicos'] ?? 0; ?></h3>
                            <p>DNI Únicos</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon average">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['antiguedad_promedio_dias'] ?? 0, 0); ?></h3>
                            <p>Días Antigüedad Promedio</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-user-slash"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['inactivos'] ?? 0; ?></h3>
                            <p>Conductores Inactivos</p>
                        </div>
                    </div>
                </div>

                <!-- ESTADÍSTICAS DE PRUEBAS -->
                <div class="stats-subgrid" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                    <h4 style="margin-bottom: 1rem; color: var(--dark);">Estadísticas de Pruebas</h4>
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
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $pruebas_estadisticas['conductores_con_pruebas'] ?? 0; ?></h3>
                                <p>Conductores con Pruebas</p>
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
                                <th>Nombre Completo</th>
                                <th>DNI</th>
                                <th>Contacto</th>
                                <th>Estado</th>
                                <th>Último Acceso</th>
                                <th>Fecha Registro</th>
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
                                        <?php if ($conductor['email']): ?>
                                        <div class="conductor-email">
                                            <small><?php echo htmlspecialchars($conductor['email']); ?></small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <span class="dni-badge"><?php echo htmlspecialchars($conductor['dni']); ?></span>
                                </td>
                                <td>
                                    <?php if ($conductor['telefono']): ?>
                                    <div class="contacto-info">
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($conductor['telefono']); ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">Sin teléfono</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge estado-<?php echo $conductor['estado'] ? 'activo' : 'inactivo'; ?>">
                                        <?php echo $conductor['estado'] ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($conductor['ultimo_login']): ?>
                                    <span class="ultimo-login" title="<?php echo date('d/m/Y H:i', strtotime($conductor['ultimo_login'])); ?>">
                                        <?php echo time_elapsed_string($conductor['ultimo_login']); ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">Nunca</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="fecha-registro">
                                        <?php echo date('d/m/Y', strtotime($conductor['fecha_creacion'])); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-icon primary" 
                                            title="Editar Conductor"
                                            onclick="editarConductor(<?php echo $conductor['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if ($conductor['estado']): ?>
                                    <button type="button" class="btn-icon danger" 
                                            title="Desactivar Conductor"
                                            onclick="desactivarConductor(<?php echo $conductor['id']; ?>, '<?php echo htmlspecialchars($conductor['nombre'] . ' ' . $conductor['apellido']); ?>')">
                                        <i class="fas fa-user-slash"></i>
                                    </button>
                                    <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="conductor_id" value="<?php echo $conductor['id']; ?>">
                                        <button type="submit" name="reactivar_conductor" class="btn-icon success" title="Reactivar Conductor">
                                            <i class="fas fa-user-check"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                    <a href="historial-pruebas.php?conductor_id=<?php echo $conductor['id']; ?>" 
                                       class="btn-icon info" title="Ver Historial de Pruebas">
                                        <i class="fas fa-history"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <!-- EMPTY STATE IDÉNTICO -->
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

<!-- MODAL CORREGIDO - MEJORADO LOS ESTILOS -->
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
                                   placeholder="Ej: Juan" required maxlength="100"
                                   value="<?php echo htmlspecialchars($conductor_editar['nombre'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="apellido" class="form-label">Apellido *</label>
                            <input type="text" id="apellido" name="apellido" class="form-control" 
                                   placeholder="Ej: Pérez" required maxlength="100"
                                   value="<?php echo htmlspecialchars($conductor_editar['apellido'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="dni" class="form-label">DNI *</label>
                            <input type="text" id="dni" name="dni" class="form-control" 
                                   placeholder="Ej: 12345678" required maxlength="15"
                                   pattern="[0-9]{8,15}" title="Solo números, entre 8 y 15 dígitos"
                                   value="<?php echo htmlspecialchars($conductor_editar['dni'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control" 
                                   placeholder="Ej: juan.perez@empresa.com" maxlength="255"
                                   value="<?php echo htmlspecialchars($conductor_editar['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="text" id="telefono" name="telefono" class="form-control" 
                                   placeholder="Ej: +51 987654321" maxlength="20"
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
                            <strong>Nota:</strong> Al crear un nuevo conductor, se generará automáticamente una contraseña temporal que se mostrará en pantalla.
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

<!-- FORMULARIO OCULTO PARA DESACTIVAR -->
<form id="formDesactivarConductor" method="POST" style="display: none;">
    <input type="hidden" name="conductor_id" id="conductor_desactivar_id">
    <input type="hidden" name="eliminar_conductor" value="1">
</form>

<?php
// Función helper para mostrar tiempo transcurrido
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'año',
        'm' => 'mes',
        'w' => 'semana',
        'd' => 'día',
        'h' => 'hora',
        'i' => 'minuto',
        's' => 'segundo',
    );
    
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? 'Hace ' . implode(', ', $string) : 'Recién';
}
?>

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
</style>

<script>
// FUNCIONES JS CORREGIDAS PARA GESTIÓN DE CONDUCTORES
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

// Modal para conductor - CORREGIDO
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
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden'; // Prevenir scroll del body
}

function cerrarModalConductor() {
    const modal = document.getElementById('modalConductor');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto'; // Restaurar scroll
    
    // Redirigir sin parámetros GET para limpiar la edición
    if (window.location.search.includes('editar=')) {
        window.location.href = 'registrar-conductor.php';
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
    window.location.href = 'registrar-conductor.php?editar=' + conductorId;
}

// Función para desactivar conductor con confirmación
function desactivarConductor(conductorId, nombreCompleto) {
    if (confirm(`¿Está seguro de desactivar al conductor ${nombreCompleto}?\n\nEl conductor no podrá realizar pruebas hasta que sea reactivado.`)) {
        document.getElementById('conductor_desactivar_id').value = conductorId;
        document.getElementById('formDesactivarConductor').submit();
    }
}

// Validación del formulario antes de enviar - CORREGIDA
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
    
    // Validar formato de DNI
    const formatoDNI = /^[0-9]{8,15}$/;
    if (!formatoDNI.test(dni)) {
        e.preventDefault();
        alert('El DNI debe contener solo números y tener entre 8 y 15 dígitos.');
        return false;
    }
    
    // Validar email si se proporciona
    if (email && !validateEmail(email)) {
        e.preventDefault();
        alert('El formato del email no es válido.');
        return false;
    }
    
    console.log('Formulario válido, enviando...');
    return true;
}

// Función auxiliar para validar email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Función para generar datos de ejemplo (solo en modo demo)
function generarConductorDemo() {
    if (confirm('¿Generar conductor de ejemplo? Esto solo funciona en modo demo.')) {
        const nombres = ['Juan', 'María', 'Carlos', 'Ana', 'Luis', 'Laura'];
        const apellidos = ['Pérez', 'Gómez', 'Rodríguez', 'López', 'Martínez', 'García'];
        const dominios = ['gmail.com', 'hotmail.com', 'empresa.com', 'outlook.com'];
        
        const nombre = nombres[Math.floor(Math.random() * nombres.length)];
        const apellido = apellidos[Math.floor(Math.random() * apellidos.length)];
        const dni = Math.floor(10000000 + Math.random() * 90000000).toString();
        const email = `${nombre.toLowerCase()}.${apellido.toLowerCase()}@${dominios[Math.floor(Math.random() * dominios.length)]}`;
        const telefono = `+51 9${Math.floor(10000000 + Math.random() * 90000000)}`;
        
        document.getElementById('nombre').value = nombre;
        document.getElementById('apellido').value = apellido;
        document.getElementById('dni').value = dni;
        document.getElementById('email').value = email;
        document.getElementById('telefono').value = telefono;
        
        alert('Datos de ejemplo generados. Puede modificarlos antes de guardar.');
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>