<?php
// vehiculos-mantenimiento.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Gestión de Vehículos';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'vehiculos-mantenimiento.php' => 'Gestión de Vehículos'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// OBTENER DATOS DE VEHÍCULOS
$vehiculos = $db->fetchAll("
    SELECT id, placa, marca, modelo, anio, color, kilometraje, estado, fecha_creacion
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
        SUM(CASE WHEN estado = 'mantenimiento' THEN 1 ELSE 0 END) as en_mantenimiento,
        AVG(kilometraje) as kilometraje_promedio,
        COUNT(DISTINCT marca) as marcas_unicas,
        MIN(anio) as anio_mas_antiguo,
        MAX(anio) as anio_mas_nuevo
    FROM vehiculos 
    WHERE cliente_id = ?
", [$cliente_id]);

// OBTENER ESTADÍSTICAS DE PRUEBAS POR VEHÍCULO
$pruebas_estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        COUNT(DISTINCT vehiculo_id) as vehiculos_con_pruebas,
        AVG(nivel_alcohol) as promedio_nivel_alcohol,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        SUM(CASE WHEN es_retest = 1 THEN 1 ELSE 0 END) as retests_realizados
    FROM pruebas 
    WHERE cliente_id = ? AND vehiculo_id IS NOT NULL
", [$cliente_id]);

// PROCESAR ACCIONES POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['guardar_vehiculo'])) {
        $placa = trim($_POST['placa'] ?? '');
        $marca = trim($_POST['marca'] ?? '');
        $modelo = trim($_POST['modelo'] ?? '');
        $anio = $_POST['anio'] ?? null;
        $color = trim($_POST['color'] ?? '');
        $kilometraje = $_POST['kilometraje'] ?? 0;
        $estado = $_POST['estado'] ?? 'activo';
        $vehiculo_id = $_POST['vehiculo_id'] ?? null;

        try {
            // Validaciones básicas
            if (empty($placa) || empty($marca) || empty($modelo)) {
                throw new Exception("Placa, marca y modelo son campos obligatorios");
            }

            // Validar formato de placa (ejemplo: ABC-123 o ABC123)
            if (!preg_match('/^[A-Z0-9-]{4,10}$/i', $placa)) {
                throw new Exception("El formato de la placa no es válido");
            }

            // Validar año
            if ($anio && ($anio < 1900 || $anio > date('Y') + 1)) {
                throw new Exception("El año debe ser válido");
            }

            // Validar kilometraje
            if ($kilometraje < 0) {
                throw new Exception("El kilometraje no puede ser negativo");
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

                if ($result) {
                    $vehiculo_id = $db->lastInsertId();
                    $mensaje_exito = "Vehículo registrado correctamente";
                    $accion_auditoria = 'CREAR_VEHICULO';
                    $registro_id = $vehiculo_id;
                } else {
                    throw new Exception("Error al insertar en la base de datos");
                }
            }

            // AUDITORÍA
            $detalles = "Vehículo {$marca} {$modelo} - Placa: {$placa} - Estado: {$estado}";
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, ?, 'vehiculos', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $accion_auditoria, $registro_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            // Recargar datos después de guardar
            $vehiculos = $db->fetchAll("
                SELECT id, placa, marca, modelo, anio, color, kilometraje, estado, fecha_creacion
                FROM vehiculos 
                WHERE cliente_id = ?
                ORDER BY marca, modelo, placa
            ", [$cliente_id]);

            // Limpiar datos de edición después de guardar
            $vehiculo_editar = null;

        } catch (Exception $e) {
            $mensaje_error = "Error al guardar el vehículo: " . $e->getMessage();
            error_log("Error en vehiculos-mantenimiento: " . $e->getMessage());
        }
    }

    // CAMBIAR ESTADO VEHÍCULO
    if (isset($_POST['cambiar_estado'])) {
        $vehiculo_id = $_POST['vehiculo_id'] ?? null;
        $nuevo_estado = $_POST['nuevo_estado'] ?? 'activo';
        
        if ($vehiculo_id) {
            try {
                // Verificar que el vehículo pertenece al cliente
                $vehiculo = $db->fetchOne("
                    SELECT placa, marca, modelo FROM vehiculos 
                    WHERE id = ? AND cliente_id = ?
                ", [$vehiculo_id, $cliente_id]);

                if (!$vehiculo) {
                    throw new Exception("Vehículo no encontrado o no pertenece a su empresa");
                }

                // Actualizar estado
                $db->execute("
                    UPDATE vehiculos 
                    SET estado = ? 
                    WHERE id = ? AND cliente_id = ?
                ", [$nuevo_estado, $vehiculo_id, $cliente_id]);

                $estados = [
                    'activo' => 'activado',
                    'inactivo' => 'desactivado', 
                    'mantenimiento' => 'colocado en mantenimiento'
                ];
                
                $mensaje_exito = "Vehículo {$estados[$nuevo_estado]} correctamente";
                
                // AUDITORÍA
                $detalles = "Vehículo {$vehiculo['marca']} {$vehiculo['modelo']} - Placa: {$vehiculo['placa']} - Nuevo estado: {$nuevo_estado}";
                $db->execute("
                    INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                    VALUES (?, ?, 'CAMBIAR_ESTADO_VEHICULO', 'vehiculos', ?, ?, ?, ?)
                ", [$cliente_id, $user_id, $vehiculo_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

                // Recargar datos
                $vehiculos = $db->fetchAll("
                    SELECT id, placa, marca, modelo, anio, color, kilometraje, estado, fecha_creacion
                    FROM vehiculos 
                    WHERE cliente_id = ?
                    ORDER BY marca, modelo, placa
                ", [$cliente_id]);

            } catch (Exception $e) {
                $mensaje_error = "Error al cambiar estado del vehículo: " . $e->getMessage();
            }
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
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona el registro y administración de vehículos de la empresa</p>
        </div>
        <div class="header-actions">
            <button type="button" class="btn btn-primary" onclick="mostrarModalVehiculo()">
                <i class="fas fa-car"></i>Nuevo Vehículo
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
                            <i class="fas fa-car-side"></i>
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
                            <h3><?php echo $estadisticas['en_mantenimiento'] ?? 0; ?></h3>
                            <p>En Mantenimiento</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-industry"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['marcas_unicas'] ?? 0; ?></h3>
                            <p>Marcas Diferentes</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon average">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['kilometraje_promedio'] ?? 0, 0); ?></h3>
                            <p>Km Promedio</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-car-crash"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['inactivos'] ?? 0; ?></h3>
                            <p>Vehículos Inactivos</p>
                        </div>
                    </div>
                </div>

                <!-- RANGO DE AÑOS -->
                <div class="stats-subgrid" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                    <h4 style="margin-bottom: 1rem; color: var(--dark);">Rango de Años</h4>
                    <div class="stats-grid">
                        <div class="stat-card mini">
                            <div class="stat-icon secondary">
                                <i class="fas fa-calendar-minus"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $estadisticas['anio_mas_antiguo'] ?? 'N/A'; ?></h3>
                                <p>Año Más Antiguo</p>
                            </div>
                        </div>
                        <div class="stat-card mini">
                            <div class="stat-icon secondary">
                                <i class="fas fa-calendar-plus"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $estadisticas['anio_mas_nuevo'] ?? 'N/A'; ?></h3>
                                <p>Año Más Nuevo</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ESTADÍSTICAS DE PRUEBAS -->
                <div class="stats-subgrid" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                    <h4 style="margin-bottom: 1rem; color: var(--dark);">Estadísticas de Pruebas por Vehículo</h4>
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
                                <i class="fas fa-car-side"></i>
                            </div>
                            <div class="stat-info">
                                <h3><?php echo $pruebas_estadisticas['vehiculos_con_pruebas'] ?? 0; ?></h3>
                                <p>Vehículos con Pruebas</p>
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
                                <th>Vehículo</th>
                                <th>Placa</th>
                                <th>Año</th>
                                <th>Color</th>
                                <th>Kilometraje</th>
                                <th>Estado</th>
                                <th>Fecha Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vehiculos as $vehiculo): ?>
                            <tr>
                                <td>
                                    <div class="vehiculo-info">
                                        <div class="vehiculo-marca-modelo">
                                            <strong><?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?></strong>
                                        </div>
                                        <div class="vehiculo-detalles">
                                            <small class="text-muted">ID: <?php echo $vehiculo['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="placa-badge"><?php echo htmlspecialchars($vehiculo['placa']); ?></span>
                                </td>
                                <td>
                                    <?php if ($vehiculo['anio']): ?>
                                    <span class="anio-vehiculo"><?php echo htmlspecialchars($vehiculo['anio']); ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">No especificado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($vehiculo['color']): ?>
                                    <div class="color-info">
                                        <span class="color-indicator" style="background-color: <?php echo htmlspecialchars(strtolower($vehiculo['color'])); ?>"></span>
                                        <?php echo htmlspecialchars($vehiculo['color']); ?>
                                    </div>
                                    <?php else: ?>
                                    <span class="text-muted">No especificado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($vehiculo['kilometraje']): ?>
                                    <span class="kilometraje-info">
                                        <?php echo number_format($vehiculo['kilometraje'], 0); ?> km
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">Sin datos</span>
                                    <?php endif; ?>
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
                                <td>
                                    <span class="fecha-registro">
                                        <?php echo date('d/m/Y', strtotime($vehiculo['fecha_creacion'])); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-icon primary" 
                                            title="Editar Vehículo"
                                            onclick="editarVehiculo(<?php echo $vehiculo['id']; ?>)">
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
                                            <?php if ($vehiculo['estado'] !== 'activo'): ?>
                                            <form method="POST" class="dropdown-form">
                                                <input type="hidden" name="vehiculo_id" value="<?php echo $vehiculo['id']; ?>">
                                                <input type="hidden" name="nuevo_estado" value="activo">
                                                <button type="submit" name="cambiar_estado" class="dropdown-item success">
                                                    <i class="fas fa-check"></i> Activar
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($vehiculo['estado'] !== 'inactivo'): ?>
                                            <form method="POST" class="dropdown-form">
                                                <input type="hidden" name="vehiculo_id" value="<?php echo $vehiculo['id']; ?>">
                                                <input type="hidden" name="nuevo_estado" value="inactivo">
                                                <button type="submit" name="cambiar_estado" class="dropdown-item danger">
                                                    <i class="fas fa-times"></i> Desactivar
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                            
                                            <?php if ($vehiculo['estado'] !== 'mantenimiento'): ?>
                                            <form method="POST" class="dropdown-form">
                                                <input type="hidden" name="vehiculo_id" value="<?php echo $vehiculo['id']; ?>">
                                                <input type="hidden" name="nuevo_estado" value="mantenimiento">
                                                <button type="submit" name="cambiar_estado" class="dropdown-item warning">
                                                    <i class="fas fa-tools"></i> Mantenimiento
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <a href="historial-pruebas.php?vehiculo_id=<?php echo $vehiculo['id']; ?>" 
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
                <!-- EMPTY STATE -->
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-car"></i>
                    </div>
                    <h3>No hay vehículos registrados</h3>
                    <p>Comienza registrando el primer vehículo de tu empresa</p>
                    <div class="empty-actions">
                        <button type="button" class="btn btn-primary" onclick="mostrarModalVehiculo()">
                            <i class="fas fa-car"></i>Registrar Primer Vehículo
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- MODAL PARA VEHÍCULOS -->
<div id="modalVehiculo" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="cerrarModalVehiculo()"></div>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-car"></i> 
                    <span id="modalTituloVehiculo">Registrar Nuevo Vehículo</span>
                </h3>
                <button type="button" class="modal-close" onclick="cerrarModalVehiculo()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formVehiculo" method="POST" class="modal-form">
                    <input type="hidden" name="vehiculo_id" id="vehiculo_id">
                    <input type="hidden" name="guardar_vehiculo" value="1">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="placa" class="form-label">Placa *</label>
                            <input type="text" id="placa" name="placa" class="form-control" 
                                   placeholder="Ej: ABC-123" required maxlength="20"
                                   pattern="[A-Z0-9-]{4,10}" title="Formato: ABC-123 o ABC123"
                                   value="<?php echo htmlspecialchars($vehiculo_editar['placa'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="marca" class="form-label">Marca *</label>
                            <input type="text" id="marca" name="marca" class="form-control" 
                                   placeholder="Ej: Toyota" required maxlength="50"
                                   value="<?php echo htmlspecialchars($vehiculo_editar['marca'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="modelo" class="form-label">Modelo *</label>
                            <input type="text" id="modelo" name="modelo" class="form-control" 
                                   placeholder="Ej: Hilux" required maxlength="50"
                                   value="<?php echo htmlspecialchars($vehiculo_editar['modelo'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="anio" class="form-label">Año</label>
                            <input type="number" id="anio" name="anio" class="form-control" 
                                   placeholder="Ej: 2023" min="1900" max="<?php echo date('Y') + 1; ?>"
                                   value="<?php echo htmlspecialchars($vehiculo_editar['anio'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="color" class="form-label">Color</label>
                            <input type="text" id="color" name="color" class="form-control" 
                                   placeholder="Ej: Blanco" maxlength="30"
                                   value="<?php echo htmlspecialchars($vehiculo_editar['color'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="kilometraje" class="form-label">Kilometraje</label>
                            <input type="number" id="kilometraje" name="kilometraje" class="form-control" 
                                   placeholder="Ej: 15000" min="0" step="1"
                                   value="<?php echo htmlspecialchars($vehiculo_editar['kilometraje'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="estado" class="form-label">Estado</label>
                            <select id="estado" name="estado" class="form-control" required>
                                <option value="activo" <?php echo ($vehiculo_editar['estado'] ?? 'activo') === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo ($vehiculo_editar['estado'] ?? '') === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                <option value="mantenimiento" <?php echo ($vehiculo_editar['estado'] ?? '') === 'mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-nota">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            <strong>Nota:</strong> Los campos marcados con * son obligatorios.
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
</div>

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
// FUNCIONES JS PARA GESTIÓN DE VEHÍCULOS
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de vehículos cargada');
    
    // Si hay un vehículo para editar (viene por GET), abrir modal automáticamente
    <?php if ($vehiculo_editar): ?>
    console.log('Editando vehículo: <?php echo $vehiculo_editar['id']; ?>');
    mostrarModalVehiculo(<?php echo $vehiculo_editar['id']; ?>);
    <?php endif; ?>

    // Agregar event listener para el formulario
    const formVehiculo = document.getElementById('formVehiculo');
    if (formVehiculo) {
        formVehiculo.addEventListener('submit', function(e) {
            console.log('Formulario enviado');
            return validarFormularioVehiculo(e);
        });
    }
});

// Modal para vehículo
function mostrarModalVehiculo(vehiculoId = null) {
    const modal = document.getElementById('modalVehiculo');
    const titulo = document.getElementById('modalTituloVehiculo');
    const botonGuardar = document.getElementById('textoBotonGuardar');
    
    console.log('Mostrando modal para vehículo:', vehiculoId);
    
    if (vehiculoId) {
        titulo.textContent = 'Editar Vehículo';
        botonGuardar.textContent = 'Actualizar Vehículo';
        document.getElementById('vehiculo_id').value = vehiculoId;
    } else {
        titulo.textContent = 'Registrar Nuevo Vehículo';
        botonGuardar.textContent = 'Guardar Vehículo';
        // Limpiar formulario si es nuevo
        document.getElementById('formVehiculo').reset();
        document.getElementById('vehiculo_id').value = '';
    }
    
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function cerrarModalVehiculo() {
    const modal = document.getElementById('modalVehiculo');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Redirigir sin parámetros GET para limpiar la edición
    if (window.location.search.includes('editar=')) {
        window.location.href = 'vehiculos-mantenimiento.php';
    }
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalVehiculo();
    }
});

// Función para editar vehículo (redirige con parámetro GET)
function editarVehiculo(vehiculoId) {
    console.log('Editando vehículo ID:', vehiculoId);
    window.location.href = 'vehiculos-mantenimiento.php?editar=' + vehiculoId;
}

// Validación del formulario antes de enviar
function validarFormularioVehiculo(e) {
    const placa = document.getElementById('placa').value.trim();
    const marca = document.getElementById('marca').value.trim();
    const modelo = document.getElementById('modelo').value.trim();
    const anio = document.getElementById('anio').value.trim();
    const kilometraje = document.getElementById('kilometraje').value.trim();
    
    console.log('Validando formulario:', { placa, marca, modelo, anio, kilometraje });
    
    if (!placa || !marca || !modelo) {
        e.preventDefault();
        alert('Por favor, complete los campos obligatorios: Placa, Marca y Modelo.');
        return false;
    }
    
    // Validar formato de placa
    const formatoPlaca = /^[A-Z0-9-]{4,10}$/i;
    if (!formatoPlaca.test(placa)) {
        e.preventDefault();
        alert('El formato de la placa no es válido. Use formato: ABC-123 o ABC123');
        return false;
    }
    
    // Validar año si se proporciona
    if (anio) {
        const anioActual = new Date().getFullYear();
        if (anio < 1900 || anio > anioActual + 1) {
            e.preventDefault();
            alert('El año debe ser válido (entre 1900 y ' + (anioActual + 1) + ')');
            return false;
        }
    }
    
    // Validar kilometraje si se proporciona
    if (kilometraje && kilometraje < 0) {
        e.preventDefault();
        alert('El kilometraje no puede ser negativo.');
        return false;
    }
    
    console.log('Formulario válido, enviando...');
    return true;
}

// Función para generar datos de ejemplo (solo en modo demo)
function generarVehiculoDemo() {
    if (confirm('¿Generar vehículo de ejemplo? Esto solo funciona en modo demo.')) {
        const marcas = ['Toyota', 'Nissan', 'Ford', 'Chevrolet', 'Hyundai', 'Kia'];
        const modelos = {
            'Toyota': ['Hilux', 'Corolla', 'RAV4', 'Camry'],
            'Nissan': ['Frontier', 'Sentra', 'X-Trail', 'Versa'],
            'Ford': ['Ranger', 'Fiesta', 'Escape', 'Focus'],
            'Chevrolet': ['S10', 'Spark', 'Tracker', 'Onix'],
            'Hyundai': ['Tucson', 'Accent', 'Creta', 'Elantra'],
            'Kia': ['Sportage', 'Rio', 'Seltos', 'Forte']
        };
        const colores = ['Blanco', 'Negro', 'Rojo', 'Azul', 'Gris', 'Plateado'];
        
        const marca = marcas[Math.floor(Math.random() * marcas.length)];
        const modelo = modelos[marca][Math.floor(Math.random() * modelos[marca].length)];
        const placa = generarPlacaAleatoria();
        const anio = Math.floor(2018 + Math.random() * 7);
        const color = colores[Math.floor(Math.random() * colores.length)];
        const kilometraje = Math.floor(5000 + Math.random() * 50000);
        
        document.getElementById('placa').value = placa;
        document.getElementById('marca').value = marca;
        document.getElementById('modelo').value = modelo;
        document.getElementById('anio').value = anio;
        document.getElementById('color').value = color;
        document.getElementById('kilometraje').value = kilometraje;
        
        alert('Datos de ejemplo generados. Puede modificarlos antes de guardar.');
    }
}

function generarPlacaAleatoria() {
    const letras = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const numeros = '0123456789';
    
    let placa = '';
    // Formato: ABC-123
    for (let i = 0; i < 3; i++) {
        placa += letras.charAt(Math.floor(Math.random() * letras.length));
    }
    placa += '-';
    for (let i = 0; i < 3; i++) {
        placa += numeros.charAt(Math.floor(Math.random() * numeros.length));
    }
    
    return placa;
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>