<?php
// nueva-prueba.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Gestión de Pruebas de Alcohol';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'nueva-prueba.php' => 'Pruebas'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// LÓGICA CRUD - PATRÓN ESTÁNDAR
$modo_edicion = false;
$prueba_actual = null;
$prueba_id = $_GET['editar'] ?? null;

// Obtener datos necesarios para los selects
$alcoholimetros = $db->fetchAll("
    SELECT id, numero_serie, nombre_activo 
    FROM alcoholimetros 
    WHERE cliente_id = ? AND estado = 'activo'
    ORDER BY nombre_activo
", [$cliente_id]);

$conductores = $db->fetchAll("
    SELECT id, nombre, apellido, dni 
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor' AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

$supervisores = $db->fetchAll("
    SELECT id, nombre, apellido 
    FROM usuarios 
    WHERE cliente_id = ? AND rol IN ('supervisor', 'admin') AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

$vehiculos = $db->fetchAll("
    SELECT id, placa, marca, modelo 
    FROM vehiculos 
    WHERE cliente_id = ? AND estado = 'activo'
    ORDER BY placa
", [$cliente_id]);

// Obtener configuración del cliente para límites
$configuracion = $db->fetchOne("
    SELECT limite_alcohol_permisible, nivel_advertencia, nivel_critico, unidad_medida 
    FROM configuraciones 
    WHERE cliente_id = ?
", [$cliente_id]);

$limite_permisible = $configuracion['limite_alcohol_permisible'] ?? 0.000;

// Obtener lista de pruebas
$pruebas = $db->fetchAll("
    SELECT p.*, 
           a.nombre_activo as alcoholimetro_nombre,
           CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
           CONCAT(u_supervisor.nombre, ' ', u_supervisor.apellido) as supervisor_nombre,
           v.placa as vehiculo_placa
    FROM pruebas p
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
    LEFT JOIN usuarios u_supervisor ON p.supervisor_id = u_supervisor.id
    LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
    WHERE p.cliente_id = ? 
    ORDER BY p.fecha_prueba DESC
", [$cliente_id]);

// Cargar prueba para editar
if ($prueba_id) {
    $prueba_actual = $db->fetchOne("
        SELECT * FROM pruebas 
        WHERE id = ? AND cliente_id = ?
    ", [$prueba_id, $cliente_id]);
    
    if ($prueba_actual) {
        $modo_edicion = true;
    }
}

// Procesar formulario CREAR/ACTUALIZAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alcoholimetro_id = $_POST['alcoholimetro_id'] ?? null;
    $conductor_id = $_POST['conductor_id'] ?? null;
    $supervisor_id = $_POST['supervisor_id'] ?? null;
    $vehiculo_id = $_POST['vehiculo_id'] ?? null;
    $nivel_alcohol = (float)($_POST['nivel_alcohol'] ?? 0);
    $latitud = $_POST['latitud'] ?? null;
    $longitud = $_POST['longitud'] ?? null;
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    // Calcular resultado automáticamente
    $resultado = $nivel_alcohol <= $limite_permisible ? 'aprobado' : 'reprobado';
    
    try {
        if ($modo_edicion && $prueba_actual) {
            // ACTUALIZAR
            $db->execute("
                UPDATE pruebas 
                SET alcoholimetro_id = ?, conductor_id = ?, supervisor_id = ?, 
                    vehiculo_id = ?, nivel_alcohol = ?, resultado = ?,
                    latitud = ?, longitud = ?, observaciones = ?
                WHERE id = ? AND cliente_id = ?
            ", [$alcoholimetro_id, $conductor_id, $supervisor_id, $vehiculo_id,
                $nivel_alcohol, $resultado, $latitud, $longitud, $observaciones,
                $prueba_id, $cliente_id]);
            
            $mensaje_exito = "Prueba actualizada correctamente";
        } else {
            // CREAR
            $db->execute("
                INSERT INTO pruebas 
                (cliente_id, alcoholimetro_id, conductor_id, supervisor_id, 
                 vehiculo_id, nivel_alcohol, limite_permisible, resultado,
                 latitud, longitud, observaciones) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ", [$cliente_id, $alcoholimetro_id, $conductor_id, $supervisor_id,
                $vehiculo_id, $nivel_alcohol, $limite_permisible, $resultado,
                $latitud, $longitud, $observaciones]);
            
            $mensaje_exito = "Prueba registrada correctamente";
            $modo_edicion = false;
        }
        
        // AUDITORÍA (patrón obligatorio)
        $accion = $modo_edicion ? 'ACTUALIZAR_PRUEBA' : 'CREAR_PRUEBA';
        $db->execute("
            INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, ?, ?, 'pruebas', ?, ?, ?, ?)
        ", [$cliente_id, $user_id, $accion, $prueba_id ?? $db->lastInsertId(), 
            "Prueba " . ($modo_edicion ? 'actualizada' : 'creada') . " - Nivel: " . $nivel_alcohol . " " . $configuracion['unidad_medida'], 
            $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
        // Recargar lista
        $pruebas = $db->fetchAll("
            SELECT p.*, 
                   a.nombre_activo as alcoholimetro_nombre,
                   CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
                   CONCAT(u_supervisor.nombre, ' ', u_supervisor.apellido) as supervisor_nombre,
                   v.placa as vehiculo_placa
            FROM pruebas p
            LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
            LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
            LEFT JOIN usuarios u_supervisor ON p.supervisor_id = u_supervisor.id
            LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
            WHERE p.cliente_id = ? 
            ORDER BY p.fecha_prueba DESC
        ", [$cliente_id]);
        
    } catch (Exception $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Procesar ELIMINACIÓN con verificación de dependencias
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];
    
    try {
        // Verificar dependencias en re-tests (patrón importante)
        $retests_asociados = $db->fetchOne("
            SELECT COUNT(*) as total FROM pruebas 
            WHERE prueba_padre_id = ? AND cliente_id = ?
        ", [$id_eliminar, $cliente_id]);
        
        $solicitudes_asociadas = $db->fetchOne("
            SELECT COUNT(*) as total FROM solicitudes_retest 
            WHERE prueba_original_id = ?
        ", [$id_eliminar]);
        
        if ($retests_asociados['total'] > 0 || $solicitudes_asociadas['total'] > 0) {
            $mensaje_error = "No se puede eliminar la prueba porque tiene re-tests o solicitudes asociadas.";
        } else {
            $db->execute("
                DELETE FROM pruebas 
                WHERE id = ? AND cliente_id = ?
            ", [$id_eliminar, $cliente_id]);
            
            $mensaje_exito = "Prueba eliminada correctamente";
            
            // Auditoría de eliminación
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'ELIMINAR_PRUEBA', 'pruebas', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $id_eliminar, "Prueba eliminada", 
                $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            // Recargar lista
            $pruebas = $db->fetchAll("
                SELECT p.*, 
                       a.nombre_activo as alcoholimetro_nombre,
                       CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
                       CONCAT(u_supervisor.nombre, ' ', u_supervisor.apellido) as supervisor_nombre,
                       v.placa as vehiculo_placa
                FROM pruebas p
                LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
                LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
                LEFT JOIN usuarios u_supervisor ON p.supervisor_id = u_supervisor.id
                LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
                WHERE p.cliente_id = ? 
                ORDER BY p.fecha_prueba DESC
            ", [$cliente_id]);
        }
    } catch (Exception $e) {
        $mensaje_error = "Error al eliminar: " . $e->getMessage();
    }
}
?>

<div class="content-body">
    <!-- HEADER IDÉNTICO -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona las pruebas de alcohol del sistema</p>
        </div>
        <div class="header-actions">
            <?php if ($modo_edicion): ?>
                <a href="nueva-prueba.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>Volver a la lista
                </a>
            <?php else: ?>
                <a href="?nuevo=true" class="btn btn-primary">
                    <i class="fas fa-vial"></i>Nueva Prueba
                </a>
            <?php endif; ?>
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
        <!-- VISTA LISTA (Principal) -->
        <?php if (!$modo_edicion && !isset($_GET['nuevo'])): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-vial"></i> Historial de Pruebas</h3>
                <div class="card-actions">
                    <span class="badge"><?php echo count($pruebas); ?> registros</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($pruebas)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Conductor</th>
                                <th>Alcoholímetro</th>
                                <th>Supervisor</th>
                                <th>Vehículo</th>
                                <th>Nivel Alcohol</th>
                                <th>Resultado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pruebas as $prueba): ?>
                            <tr>
                                <td><?php echo date('d/m/Y H:i', strtotime($prueba['fecha_prueba'])); ?></td>
                                <td><?php echo htmlspecialchars($prueba['conductor_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($prueba['alcoholimetro_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($prueba['supervisor_nombre'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($prueba['vehiculo_placa'] ?? 'N/A'); ?></td>
                                <td>
                                    <span class="<?php echo $prueba['nivel_alcohol'] > $configuracion['nivel_critico'] ? 'text-danger' : ($prueba['nivel_alcohol'] > $configuracion['nivel_advertencia'] ? 'text-warning' : 'text-success'); ?>">
                                        <?php echo number_format($prueba['nivel_alcohol'], 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $clase_resultado = $prueba['resultado'] === 'aprobado' ? 'success' : 'danger';
                                    ?>
                                    <span class="status-badge <?php echo $clase_resultado; ?>">
                                        <?php echo ucfirst($prueba['resultado']); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="?editar=<?php echo $prueba['id']; ?>" class="btn-icon" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?eliminar=<?php echo $prueba['id']; ?>" 
                                       class="btn-icon danger" 
                                       title="Eliminar"
                                       onclick="return confirm('¿Estás seguro de eliminar esta prueba?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-vial"></i>
                    </div>
                    <h3>No hay pruebas registradas</h3>
                    <p>Comienza registrando la primera prueba del sistema</p>
                    <a href="?nuevo=true" class="btn btn-primary">
                        <i class="fas fa-vial"></i>
                        Nueva Prueba
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- VISTA FORMULARIO (Crear/Editar) -->
        <?php if ($modo_edicion || isset($_GET['nuevo'])): ?>
        <div class="card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-<?php echo $modo_edicion ? 'edit' : 'vial'; ?>"></i>
                    <?php echo $modo_edicion ? 'Editar Prueba' : 'Registrar Nueva Prueba'; ?>
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" class="account-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="alcoholimetro_id">Alcoholímetro *</label>
                            <select id="alcoholimetro_id" name="alcoholimetro_id" class="form-control" required>
                                <option value="">Seleccionar alcoholímetro</option>
                                <?php foreach ($alcoholimetros as $alcoholimetro): ?>
                                <option value="<?php echo $alcoholimetro['id']; ?>" 
                                    <?php echo ($prueba_actual['alcoholimetro_id'] ?? '') == $alcoholimetro['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($alcoholimetro['nombre_activo'] . ' (' . $alcoholimetro['numero_serie'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="conductor_id">Conductor *</label>
                            <select id="conductor_id" name="conductor_id" class="form-control" required>
                                <option value="">Seleccionar conductor</option>
                                <?php foreach ($conductores as $conductor): ?>
                                <option value="<?php echo $conductor['id']; ?>" 
                                    <?php echo ($prueba_actual['conductor_id'] ?? '') == $conductor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($conductor['nombre'] . ' ' . $conductor['apellido'] . ' (' . $conductor['dni'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="supervisor_id">Supervisor *</label>
                            <select id="supervisor_id" name="supervisor_id" class="form-control" required>
                                <option value="">Seleccionar supervisor</option>
                                <?php foreach ($supervisores as $supervisor): ?>
                                <option value="<?php echo $supervisor['id']; ?>" 
                                    <?php echo ($prueba_actual['supervisor_id'] ?? '') == $supervisor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supervisor['nombre'] . ' ' . $supervisor['apellido']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="vehiculo_id">Vehículo</label>
                            <select id="vehiculo_id" name="vehiculo_id" class="form-control">
                                <option value="">Seleccionar vehículo (opcional)</option>
                                <?php foreach ($vehiculos as $vehiculo): ?>
                                <option value="<?php echo $vehiculo['id']; ?>" 
                                    <?php echo ($prueba_actual['vehiculo_id'] ?? '') == $vehiculo['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vehiculo['placa'] . ' - ' . $vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nivel_alcohol">Nivel de Alcohol (<?php echo $configuracion['unidad_medida']; ?>) *</label>
                            <input type="number" id="nivel_alcohol" name="nivel_alcohol" 
                                   value="<?php echo htmlspecialchars($prueba_actual['nivel_alcohol'] ?? '0.000'); ?>" 
                                   step="0.001" min="0" max="1" required class="form-control"
                                   placeholder="0.000">
                            <small class="form-text">
                                Límite permisible: <?php echo number_format($limite_permisible, 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="latitud">Latitud</label>
                            <input type="number" id="latitud" name="latitud" 
                                   value="<?php echo htmlspecialchars($prueba_actual['latitud'] ?? ''); ?>" 
                                   step="0.00000001" class="form-control"
                                   placeholder="Ej: -12.046374">
                        </div>
                        <div class="form-group">
                            <label for="longitud">Longitud</label>
                            <input type="number" id="longitud" name="longitud" 
                                   value="<?php echo htmlspecialchars($prueba_actual['longitud'] ?? ''); ?>" 
                                   step="0.00000001" class="form-control"
                                   placeholder="Ej: -77.042793">
                        </div>
                        <div class="form-group full-width">
                            <label for="observaciones">Observaciones</label>
                            <textarea id="observaciones" name="observaciones" class="form-control" 
                                      rows="3" placeholder="Observaciones adicionales de la prueba"><?php echo htmlspecialchars($prueba_actual['observaciones'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?php echo $modo_edicion ? 'Actualizar' : 'Registrar'; ?> Prueba
                        </button>
                        <a href="nueva-prueba.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ESTILOS CSS INTEGRADOS (Mismo patrón) -->
<style>
:root {
    --primary: #3498db;
    --primary-dark: #2980b9;
    --success: #27ae60;
    --danger: #e74c3c;
    --warning: #f39c12;
    --dark: #2c3e50;
    --light: #f8f9fa;
    --gray: #6c757d;
    --border: #dee2e6;
    --transition: all 0.3s ease;
}

.crud-container { margin-top: 1.5rem; width: 100%; }
.data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 0; }
.data-table th { background: var(--light); padding: 1rem; text-align: left; font-weight: 600; color: var(--dark); border-bottom: 2px solid var(--border); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
.data-table td { padding: 1rem; border-bottom: 1px solid var(--border); color: var(--dark); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover { background: rgba(52, 152, 219, 0.04); }
.action-buttons { display: flex; gap: 0.5rem; justify-content: center; }
.btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: var(--light); color: var(--dark); text-decoration: none; transition: all 0.3s ease; }
.btn-icon:hover { background: var(--primary); color: white; transform: translateY(-2px); }
.btn-icon.danger:hover { background: var(--danger); }
.status-badge { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; display: inline-block; text-align: center; min-width: 80px; }
.status-badge.success { background: rgba(39, 174, 96, 0.15); color: var(--success); border: 1px solid rgba(39, 174, 96, 0.3); }
.status-badge.danger { background: rgba(231, 76, 60, 0.15); color: var(--danger); border: 1px solid rgba(231, 76, 60, 0.3); }
.badge { padding: 0.4rem 0.8rem; background: linear-gradient(135deg, var(--primary), var(--primary-dark)); color: white; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.table-responsive { overflow-x: auto; border-radius: 12px; }
.empty-state { text-align: center; padding: 4rem 2rem; color: var(--gray); }
.empty-icon { font-size: 4rem; color: var(--light); margin-bottom: 1.5rem; opacity: 0.7; }
.empty-state h3 { color: var(--dark); margin-bottom: 0.5rem; font-weight: 600; }
.empty-state p { margin-bottom: 2rem; font-size: 1rem; opacity: 0.8; }
.text-danger { color: var(--danger) !important; font-weight: 600; }
.text-warning { color: var(--warning) !important; font-weight: 600; }
.text-success { color: var(--success) !important; font-weight: 600; }
.text-muted { color: var(--gray) !important; opacity: 0.7; }
.account-form .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
.account-form .form-group { display: flex; flex-direction: column; margin-bottom: 0; }
.account-form .form-group.full-width { grid-column: 1 / -1; }
.account-form .form-group label { font-weight: 600; color: var(--dark); margin-bottom: 0.5rem; font-size: 0.9rem; transition: var(--transition); }
.account-form .form-group:focus-within label { color: var(--primary); }
.account-form .form-control { padding: 0.875rem 1rem; border: 2px solid #e1e8ed; border-radius: 10px; font-size: 0.95rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02); color: var(--dark); width: 100%; box-sizing: border-box; }
.account-form .form-control:hover { border-color: #c8d1d9; background: linear-gradient(135deg, #f5f7fa 0%, #ffffff 100%); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04); }
.account-form .form-control:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1), 0 4px 12px rgba(0, 0, 0, 0.08); transform: translateY(-1px); }
.account-form textarea.form-control { resize: vertical; min-height: 80px; }
.account-form .form-text { margin-top: 0.5rem; font-size: 0.8rem; color: #6c757d; line-height: 1.4; }
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
.card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); overflow: hidden; }
.card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center; }
.card-header h3 { margin: 0; color: var(--dark); font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.card-body { padding: 1.5rem; }

/* Responsive */
@media (max-width: 1024px) {
    .data-table { font-size: 0.85rem; }
    .account-form .form-grid { grid-template-columns: 1fr; gap: 1rem; }
    .account-form .form-group.full-width { grid-column: 1; }
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
}
</style>

<script>
// FUNCIONES JS PARA MEJORAR UX
document.addEventListener('DOMContentLoaded', function() {
    // Obtener geolocalización automática
    const latitudInput = document.getElementById('latitud');
    const longitudInput = document.getElementById('longitud');
    
    if (navigator.geolocation && (!latitudInput.value || !longitudInput.value)) {
        const geoButton = document.createElement('button');
        geoButton.type = 'button';
        geoButton.className = 'btn btn-outline';
        geoButton.innerHTML = '<i class="fas fa-map-marker-alt"></i> Obtener Ubicación';
        geoButton.style.marginTop = '0.5rem';
        
        geoButton.addEventListener('click', function() {
            navigator.geolocation.getCurrentPosition(function(position) {
                latitudInput.value = position.coords.latitude.toFixed(8);
                longitudInput.value = position.coords.longitude.toFixed(8);
            }, function(error) {
                alert('No se pudo obtener la ubicación: ' + error.message);
            });
        });
        
        if (latitudInput.parentNode) {
            latitudInput.parentNode.appendChild(geoButton);
        }
    }
    
    // Validación de nivel de alcohol
    const nivelAlcoholInput = document.getElementById('nivel_alcohol');
    const form = document.querySelector('form');
    
    if (nivelAlcoholInput && form) {
        const limitePermisible = <?php echo $limite_permisible; ?>;
        
        form.addEventListener('submit', function(e) {
            const nivel = parseFloat(nivelAlcoholInput.value);
            
            if (isNaN(nivel) || nivel < 0) {
                e.preventDefault();
                alert('El nivel de alcohol debe ser un número válido mayor o igual a 0.');
                return false;
            }
            
            // Mostrar advertencia si supera el límite
            if (nivel > limitePermisible) {
                if (!confirm('⚠️ El nivel de alcohol supera el límite permisible. ¿Desea continuar registrando la prueba como REPROBADA?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Cambio en tiempo real del color del input según el nivel
        nivelAlcoholInput.addEventListener('input', function() {
            const nivel = parseFloat(this.value) || 0;
            const nivelAdvertencia = <?php echo $configuracion['nivel_advertencia'] ?? 0.025; ?>;
            const nivelCritico = <?php echo $configuracion['nivel_critico'] ?? 0.080; ?>;
            
            if (nivel > nivelCritico) {
                this.style.borderColor = 'var(--danger)';
                this.style.boxShadow = '0 0 0 4px rgba(231, 76, 60, 0.1)';
            } else if (nivel > nivelAdvertencia) {
                this.style.borderColor = 'var(--warning)';
                this.style.boxShadow = '0 0 0 4px rgba(243, 156, 18, 0.1)';
            } else {
                this.style.borderColor = '';
                this.style.boxShadow = '';
            }
        });
        
        // Disparar el evento para aplicar estilos iniciales
        nivelAlcoholInput.dispatchEvent(new Event('input'));
    }
});

function confirmarEliminacion(conductor, fecha) {
    return confirm(`¿Estás seguro de eliminar la prueba del conductor "${conductor}" del ${fecha}?`);
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>