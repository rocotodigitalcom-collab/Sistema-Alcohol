<?php
// registrar-vehiculo.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Registrar Vehículo';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'registrar-vehiculo.php' => 'Registrar Vehículo'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// OBTENER DATOS PARA FILTROS Y SELECTS
$vehiculos = $db->fetchAll("
    SELECT id, placa, marca, modelo, anio, color, estado
    FROM vehiculos 
    WHERE cliente_id = ?
    ORDER BY marca, modelo, placa
", [$cliente_id]);

// OBTENER ESTADÍSTICAS DE VEHÍCULOS
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_vehiculos,
        SUM(CASE WHEN estado = 'activo' THEN 1 ELSE 0 END) as activos,
        SUM(CASE WHEN estado = 'inactivo' THEN 1 ELSE 0 END) as inactivos,
        SUM(CASE WHEN estado = 'mantenimiento' THEN 1 ELSE 0 END) as mantenimiento,
        COUNT(DISTINCT marca) as marcas_diferentes,
        COUNT(DISTINCT modelo) as modelos_diferentes
    FROM vehiculos 
    WHERE cliente_id = ?
", [$cliente_id]);

// PROCESAR ACCIONES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_vehiculo'])) {
        $placa = trim($_POST['placa']);
        $marca = trim($_POST['marca']);
        $modelo = trim($_POST['modelo']);
        $anio = $_POST['anio'] ?? null;
        $color = trim($_POST['color']);
        $kilometraje = $_POST['kilometraje'] ?? null;
        $estado = $_POST['estado'] ?? 'activo';
        $vehiculo_id = $_POST['vehiculo_id'] ?? null;

        try {
            // Validaciones básicas
            if (empty($placa) || empty($marca) || empty($modelo)) {
                throw new Exception("Placa, marca y modelo son campos obligatorios");
            }

            // Verificar si la placa ya existe (excepto para edición)
            if ($vehiculo_id) {
                $existe_placa = $db->fetchOne("
                    SELECT id FROM vehiculos 
                    WHERE cliente_id = ? AND placa = ? AND id != ?
                ", [$cliente_id, $placa, $vehiculo_id]);
            } else {
                $existe_placa = $db->fetchOne("
                    SELECT id FROM vehiculos 
                    WHERE cliente_id = ? AND placa = ?
                ", [$cliente_id, $placa]);
            }

            if ($existe_placa) {
                throw new Exception("Ya existe un vehículo con la placa: " . $placa);
            }

            if ($vehiculo_id) {
                // ACTUALIZAR VEHÍCULO EXISTENTE
                $db->execute("
                    UPDATE vehiculos 
                    SET placa = ?, marca = ?, modelo = ?, anio = ?, 
                        color = ?, kilometraje = ?, estado = ?
                    WHERE id = ? AND cliente_id = ?
                ", [$placa, $marca, $modelo, $anio, $color, $kilometraje, $estado, $vehiculo_id, $cliente_id]);

                $mensaje_exito = "Vehículo actualizado correctamente";
                $accion_auditoria = 'ACTUALIZAR_VEHICULO';
                $registro_id = $vehiculo_id;
                
            } else {
                // CREAR NUEVO VEHÍCULO
                $result = $db->execute("
                    INSERT INTO vehiculos 
                    (cliente_id, placa, marca, modelo, anio, color, kilometraje, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ", [$cliente_id, $placa, $marca, $modelo, $anio, $color, $kilometraje, $estado]);

                $vehiculo_id = $db->lastInsertId();
                $mensaje_exito = "Vehículo registrado correctamente";
                $accion_auditoria = 'CREAR_VEHICULO';
                $registro_id = $vehiculo_id;
            }

            // AUDITORÍA
            $detalles = "Vehículo {$placa} - {$marca} {$modelo} - Estado: {$estado}";
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, ?, 'vehiculos', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $accion_auditoria, $registro_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            // Recargar datos
            $vehiculos = $db->fetchAll("
                SELECT id, placa, marca, modelo, anio, color, estado
                FROM vehiculos 
                WHERE cliente_id = ?
                ORDER BY marca, modelo, placa
            ", [$cliente_id]);

        } catch (Exception $e) {
            $mensaje_error = "Error al guardar el vehículo: " . $e->getMessage();
        }
    }

    // ELIMINAR VEHÍCULO
    if (isset($_POST['eliminar_vehiculo'])) {
        $vehiculo_id = $_POST['vehiculo_id'];
        
        try {
            // Verificar que el vehículo pertenece al cliente
            $vehiculo = $db->fetchOne("
                SELECT placa, marca, modelo FROM vehiculos 
                WHERE id = ? AND cliente_id = ?
            ", [$vehiculo_id, $cliente_id]);

            if (!$vehiculo) {
                throw new Exception("Vehículo no encontrado o no pertenece a su empresa");
            }

            // Verificar si el vehículo tiene pruebas asociadas
            $pruebas_asociadas = $db->fetchOne("
                SELECT COUNT(*) as total 
                FROM pruebas 
                WHERE vehiculo_id = ? AND cliente_id = ?
            ", [$vehiculo_id, $cliente_id]);

            if ($pruebas_asociadas['total'] > 0) {
                throw new Exception("No se puede eliminar el vehículo porque tiene pruebas asociadas");
            }

            // Eliminar vehículo
            $db->execute("
                DELETE FROM vehiculos 
                WHERE id = ? AND cliente_id = ?
            ", [$vehiculo_id, $cliente_id]);

            $mensaje_exito = "Vehículo eliminado correctamente";
            
            // AUDITORÍA
            $detalles = "Vehículo eliminado: {$vehiculo['placa']} - {$vehiculo['marca']} {$vehiculo['modelo']}";
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'ELIMINAR_VEHICULO', 'vehiculos', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $vehiculo_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            // Recargar datos
            $vehiculos = $db->fetchAll("
                SELECT id, placa, marca, modelo, anio, color, estado
                FROM vehiculos 
                WHERE cliente_id = ?
                ORDER BY marca, modelo, placa
            ", [$cliente_id]);

        } catch (Exception $e) {
            $mensaje_error = "Error al eliminar el vehículo: " . $e->getMessage();
        }
    }
}

// OBTENER DATOS PARA EDICIÓN SI SE SOLICITA
$vehiculo_editar = null;
if (isset($_GET['editar'])) {
    $vehiculo_editar = $db->fetchOne("
        SELECT * FROM vehiculos 
        WHERE id = ? AND cliente_id = ?
    ", [$_GET['editar'], $cliente_id]);
}
?>

<div class="content-body">
    <!-- HEADER IDÉNTICO -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona el registro y administración de vehículos de la empresa</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="mostrarModalVehiculo()">
                <i class="fas fa-plus"></i>Nuevo Vehículo
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
                <h3><i class="fas fa-chart-bar"></i> Estadísticas de Vehículos</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon primary">
                            <i class="fas fa-car"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_vehiculos'] ?? 0; ?></h3>
                            <p>Total Vehículos</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['activos'] ?? 0; ?></h3>
                            <p>Vehículos Activos</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-tools"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['mantenimiento'] ?? 0; ?></h3>
                            <p>En Mantenimiento</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-industry"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['marcas_diferentes'] ?? 0; ?></h3>
                            <p>Marcas Diferentes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon average">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['modelos_diferentes'] ?? 0; ?></h3>
                            <p>Modelos Diferentes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['inactivos'] ?? 0; ?></h3>
                            <p>Vehículos Inactivos</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD DE VEHÍCULOS REGISTRADOS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list-alt"></i> Vehículos Registrados</h3>
                <div class="card-actions">
                    <span class="badge primary"><?php echo count($vehiculos); ?> registros</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($vehiculos)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Placa</th>
                                <th>Marca</th>
                                <th>Modelo</th>
                                <th>Año</th>
                                <th>Color</th>
                                <th>Kilometraje</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehiculos as $vehiculo): ?>
                            <tr>
                                <td>
                                    <div class="vehiculo-placa">
                                        <strong><?php echo htmlspecialchars($vehiculo['placa']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($vehiculo['marca']); ?></td>
                                <td><?php echo htmlspecialchars($vehiculo['modelo']); ?></td>
                                <td><?php echo $vehiculo['anio'] ? htmlspecialchars($vehiculo['anio']) : '-'; ?></td>
                                <td>
                                    <?php if ($vehiculo['color']): ?>
                                    <span class="color-vehiculo" style="background-color: <?php echo htmlspecialchars($vehiculo['color']); ?>"></span>
                                    <?php echo htmlspecialchars($vehiculo['color']); ?>
                                    <?php else: ?>
                                    -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $vehiculo['kilometraje'] ? number_format($vehiculo['kilometraje']) . ' km' : '-'; ?>
                                </td>
                                <td>
                                    <span class="status-badge estado-<?php echo $vehiculo['estado']; ?>">
                                        <?php 
                                        $estados = [
                                            'activo' => 'Activo',
                                            'inactivo' => 'Inactivo', 
                                            'mantenimiento' => 'Mantenimiento'
                                        ];
                                        echo $estados[$vehiculo['estado']] ?? $vehiculo['estado'];
                                        ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-icon primary" 
                                            title="Editar Vehículo"
                                            onclick="editarVehiculo(<?php echo $vehiculo['id']; ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button type="button" class="btn-icon danger" 
                                            title="Eliminar Vehículo"
                                            onclick="eliminarVehiculo(<?php echo $vehiculo['id']; ?>, '<?php echo htmlspecialchars($vehiculo['placa']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <a href="historial-pruebas.php?vehiculo_id=<?php echo $vehiculo['id']; ?>" 
                                       class="btn-icon info" title="Ver Historial">
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
                        <i class="fas fa-car"></i>
                    </div>
                    <h3>No hay vehículos registrados</h3>
                    <p>Comienza registrando el primer vehículo de tu empresa</p>
                    <div class="empty-actions">
                        <button type="button" class="btn btn-primary" onclick="mostrarModalVehiculo()">
                            <i class="fas fa-plus"></i>Registrar Primer Vehículo
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA REGISTRAR/EDITAR VEHÍCULO -->
<div id="modalVehiculo" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-car"></i> <span id="modalTituloVehiculo">Registrar Nuevo Vehículo</span></h3>
            <button type="button" class="modal-close" onclick="cerrarModalVehiculo()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="formVehiculo" method="POST">
                <input type="hidden" name="vehiculo_id" id="vehiculo_id">
                <input type="hidden" name="guardar_vehiculo" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="placa">Placa *</label>
                        <input type="text" id="placa" name="placa" class="form-control" 
                               placeholder="Ej: ABC-123" required maxlength="20"
                               value="<?php echo $vehiculo_editar['placa'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="marca">Marca *</label>
                        <input type="text" id="marca" name="marca" class="form-control" 
                               placeholder="Ej: Toyota" required maxlength="50"
                               value="<?php echo $vehiculo_editar['marca'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="modelo">Modelo *</label>
                        <input type="text" id="modelo" name="modelo" class="form-control" 
                               placeholder="Ej: Hilux" required maxlength="50"
                               value="<?php echo $vehiculo_editar['modelo'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="anio">Año</label>
                        <input type="number" id="anio" name="anio" class="form-control" 
                               placeholder="Ej: 2023" min="1900" max="<?php echo date('Y') + 1; ?>"
                               value="<?php echo $vehiculo_editar['anio'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input type="text" id="color" name="color" class="form-control" 
                               placeholder="Ej: Blanco" maxlength="30"
                               value="<?php echo $vehiculo_editar['color'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="kilometraje">Kilometraje</label>
                        <input type="number" id="kilometraje" name="kilometraje" class="form-control" 
                               placeholder="Ej: 15000" min="0"
                               value="<?php echo $vehiculo_editar['kilometraje'] ?? ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="estado">Estado</label>
                        <select id="estado" name="estado" class="form-control" required>
                            <option value="activo" <?php echo ($vehiculo_editar['estado'] ?? 'activo') == 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo ($vehiculo_editar['estado'] ?? '') == 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="mantenimiento" <?php echo ($vehiculo_editar['estado'] ?? '') == 'mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="cerrarModalVehiculo()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" form="formVehiculo" class="btn btn-primary">
                <i class="fas fa-save"></i> <span id="textoBotonGuardar">Guardar Vehículo</span>
            </button>
        </div>
    </div>
</div>

<!-- FORMULARIO OCULTO PARA ELIMINAR -->
<form id="formEliminarVehiculo" method="POST" style="display: none;">
    <input type="hidden" name="vehiculo_id" id="vehiculo_eliminar_id">
    <input type="hidden" name="eliminar_vehiculo" value="1">
</form>

<style>
/* ESTILOS CSS INTEGRADOS (Mismo patrón + mejoras específicas para vehículos) */
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
.status-badge.estado-mantenimiento { background: rgba(243, 156, 18, 0.15); color: var(--warning); border: 1px solid rgba(243, 156, 18, 0.3); }
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
.dashboard-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding: 1.5rem 0; border-bottom: 1px solid var(--border); }
.welcome-section h1 { margin: 0 0 0.5rem 0; color: var(--dark); font-size: 1.8rem; font-weight: 700; }
.dashboard-subtitle { margin: 0; color: var(--gray); font-size: 1rem; }
.header-actions { display: flex; gap: 1rem; }
.card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); overflow: hidden; margin-bottom: 1.5rem; }
.card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center; }
.card-header h3 { margin: 0; color: var(--dark); font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.card-body { padding: 1.5rem; }

/* ESTILOS ESPECÍFICOS PARA VEHÍCULOS */
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

.stat-icon.primary { background: rgba(52, 152, 219, 0.15); color: var(--primary); }
.stat-icon.success { background: rgba(39, 174, 96, 0.15); color: var(--success); }
.stat-icon.warning { background: rgba(243, 156, 18, 0.15); color: var(--warning); }
.stat-icon.info { background: rgba(155, 89, 182, 0.15); color: #9b59b6; }
.stat-icon.average { background: rgba(230, 126, 34, 0.15); color: #e67e22; }
.stat-icon.danger { background: rgba(231, 76, 60, 0.15); color: var(--danger); }

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

.vehiculo-placa {
    font-weight: 600;
    color: var(--dark);
}

.color-vehiculo {
    display: inline-block;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    margin-right: 0.5rem;
    border: 1px solid var(--border);
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
    max-width: 600px;
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
// FUNCIONES JS PARA GESTIÓN DE VEHÍCULOS
document.addEventListener('DOMContentLoaded', function() {
    // Si hay un vehículo para editar (viene por GET), abrir modal automáticamente
    <?php if ($vehiculo_editar): ?>
    mostrarModalVehiculo(<?php echo $vehiculo_editar['id']; ?>);
    <?php endif; ?>
});

// Modal para vehículo
function mostrarModalVehiculo(vehiculoId = null) {
    const modal = document.getElementById('modalVehiculo');
    const titulo = document.getElementById('modalTituloVehiculo');
    const botonGuardar = document.getElementById('textoBotonGuardar');
    
    if (vehiculoId) {
        titulo.textContent = 'Editar Vehículo';
        botonGuardar.textContent = 'Actualizar Vehículo';
    } else {
        titulo.textContent = 'Registrar Nuevo Vehículo';
        botonGuardar.textContent = 'Guardar Vehículo';
        // Limpiar formulario si es nuevo
        document.getElementById('formVehiculo').reset();
        document.getElementById('vehiculo_id').value = '';
    }
    
    modal.classList.add('show');
}

function cerrarModalVehiculo() {
    document.getElementById('modalVehiculo').classList.remove('show');
    // Redirigir sin parámetros GET para limpiar la edición
    if (window.location.search.includes('editar=')) {
        window.location.href = 'registrar-vehiculo.php';
    }
}

// Cerrar modales al hacer clic fuera
document.addEventListener('click', function(event) {
    const modal = document.getElementById('modalVehiculo');
    if (event.target === modal) {
        cerrarModalVehiculo();
    }
});

// Función para editar vehículo (redirige con parámetro GET)
function editarVehiculo(vehiculoId) {
    window.location.href = 'registrar-vehiculo.php?editar=' + vehiculoId;
}

// Función para eliminar vehículo con confirmación
function eliminarVehiculo(vehiculoId, placa) {
    if (confirm(`¿Está seguro de eliminar el vehículo con placa ${placa}?\n\nEsta acción no se puede deshacer.`)) {
        document.getElementById('vehiculo_eliminar_id').value = vehiculoId;
        document.getElementById('formEliminarVehiculo').submit();
    }
}

// Validación del formulario antes de enviar
document.getElementById('formVehiculo').addEventListener('submit', function(e) {
    const placa = document.getElementById('placa').value.trim();
    const marca = document.getElementById('marca').value.trim();
    const modelo = document.getElementById('modelo').value.trim();
    
    if (!placa || !marca || !modelo) {
        e.preventDefault();
        alert('Por favor, complete los campos obligatorios: Placa, Marca y Modelo.');
        return false;
    }
    
    // Validar formato de placa (puede personalizarse según el país)
    const formatoPlaca = /^[A-Z0-9-]{4,10}$/i;
    if (!formatoPlaca.test(placa)) {
        e.preventDefault();
        alert('El formato de la placa no es válido. Use letras, números y guiones.');
        return false;
    }
    
    return true;
});

// Función para generar datos de ejemplo (solo en modo demo)
function generarVehiculoDemo() {
    if (confirm('¿Generar vehículo de ejemplo? Esto solo funciona en modo demo.')) {
        const marcas = ['Toyota', 'Nissan', 'Ford', 'Chevrolet', 'Hyundai', 'Kia'];
        const modelos = ['Hilux', 'Frontier', 'Ranger', 'Colorado', 'Tucson', 'Sportage'];
        const colores = ['Blanco', 'Negro', 'Rojo', 'Azul', 'Gris', 'Plateado'];
        
        const marca = marcas[Math.floor(Math.random() * marcas.length)];
        const modelo = modelos[Math.floor(Math.random() * modelos.length)];
        const color = colores[Math.floor(Math.random() * colores.length)];
        const placa = `DEMO-${Math.floor(100 + Math.random() * 900)}`;
        const anio = 2020 + Math.floor(Math.random() * 4);
        const kilometraje = Math.floor(10000 + Math.random() * 50000);
        
        document.getElementById('placa').value = placa;
        document.getElementById('marca').value = marca;
        document.getElementById('modelo').value = modelo;
        document.getElementById('anio').value = anio;
        document.getElementById('color').value = color;
        document.getElementById('kilometraje').value = kilometraje;
        
        alert('Datos de ejemplo generados. Puede modificarlos antes de guardar.');
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>