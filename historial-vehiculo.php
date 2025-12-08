<?php
// historial-vehiculo.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$vehiculo_id = $_GET['vehiculo_id'] ?? 0;

// VERIFICACIÓN MEJORADA DEL PARÁMETRO
if (!$vehiculo_id || !is_numeric($vehiculo_id)) {
    $page_title = 'Error - Vehículo no especificado';
    $breadcrumbs = [
        'index.php' => 'Dashboard',
        'vehiculos-mantenimiento.php' => 'Gestión de Vehículos'
    ];
    require_once __DIR__ . '/includes/header.php';
    
    echo "
    <div class='content-body'>
        <div class='alert alert-danger'>
            <i class='fas fa-exclamation-triangle'></i>
            <strong>Error:</strong> No se especificó el vehículo.
        </div>
        <div class='text-center' style='margin-top: 2rem;'>
            <a href='vehiculos-mantenimiento.php' class='btn btn-primary'>
                <i class='fas fa-arrow-left'></i> Volver a vehículos
            </a>
        </div>
    </div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$page_title = 'Historial de Vehículo';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'vehiculos-mantenimiento.php' => 'Gestión de Vehículos',
    'historial-vehiculo.php?vehiculo_id=' . $vehiculo_id => 'Historial de Vehículo'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// VERIFICAR QUE EL VEHÍCULO PERTENECE AL CLIENTE
$vehiculo = $db->fetchOne("
    SELECT v.*, 
           COUNT(p.id) as total_pruebas,
           SUM(CASE WHEN p.resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
           SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas
    FROM vehiculos v
    LEFT JOIN pruebas p ON v.id = p.vehiculo_id
    WHERE v.id = ? AND v.cliente_id = ?
    GROUP BY v.id
", [$vehiculo_id, $cliente_id]);

if (!$vehiculo) {
    echo "
    <div class='content-body'>
        <div class='alert alert-danger'>
            <i class='fas fa-exclamation-triangle'></i>
            <strong>Error:</strong> Vehículo no encontrado o no tiene acceso.
        </div>
        <div class='text-center' style='margin-top: 2rem;'>
            <a href='vehiculos-mantenimiento.php' class='btn btn-primary'>
                <i class='fas fa-arrow-left'></i> Volver a vehículos
            </a>
        </div>
    </div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// OBTENER HISTORIAL DE PRUEBAS DEL VEHÍCULO
$pruebas = $db->fetchAll("
    SELECT p.*, 
           a.numero_serie as alcoholimetro_serie,
           a.nombre_activo as alcoholimetro_nombre,
           CONCAT(u.nombre, ' ', u.apellido) as conductor_nombre,
           u.dni as conductor_dni,
           CONCAT(su.nombre, ' ', su.apellido) as supervisor_nombre
    FROM pruebas p
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    LEFT JOIN usuarios u ON p.conductor_id = u.id
    LEFT JOIN usuarios su ON p.supervisor_id = su.id
    WHERE p.vehiculo_id = ? AND p.cliente_id = ?
    ORDER BY p.fecha_prueba DESC
", [$vehiculo_id, $cliente_id]);

// OBTENER ESTADÍSTICAS DETALLADAS DEL VEHÍCULO
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN p.resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
        SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
        SUM(CASE WHEN p.es_retest = 1 THEN 1 ELSE 0 END) as retests_realizados,
        AVG(p.nivel_alcohol) as promedio_alcohol,
        MAX(p.nivel_alcohol) as maximo_alcohol,
        MIN(p.nivel_alcohol) as minimo_alcohol,
        COUNT(DISTINCT p.conductor_id) as conductores_diferentes,
        COUNT(DISTINCT p.alcoholimetro_id) as alcoholimetros_utilizados,
        MIN(p.fecha_prueba) as primera_prueba,
        MAX(p.fecha_prueba) as ultima_prueba
    FROM pruebas p
    WHERE p.vehiculo_id = ? AND p.cliente_id = ?
", [$vehiculo_id, $cliente_id]);

// OBTENER CONDUCTORES QUE HAN USADO ESTE VEHÍCULO
$conductores = $db->fetchAll("
    SELECT DISTINCT u.id, u.nombre, u.apellido, u.dni,
           COUNT(p.id) as pruebas_realizadas,
           SUM(CASE WHEN p.resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas
    FROM usuarios u
    INNER JOIN pruebas p ON u.id = p.conductor_id
    WHERE p.vehiculo_id = ? AND p.cliente_id = ?
    GROUP BY u.id, u.nombre, u.apellido, u.dni
    ORDER BY pruebas_realizadas DESC
", [$vehiculo_id, $cliente_id]);
?>

<div class="content-body">
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">
                Historial completo de pruebas para el vehículo 
                <strong><?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?></strong> - 
                Placa: <strong><?php echo htmlspecialchars($vehiculo['placa']); ?></strong>
            </p>
        </div>
        <div class="header-actions">
            <a href="vehiculos-mantenimiento.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>Volver a Vehículos
            </a>
            <button type="button" class="btn btn-primary" onclick="exportarHistorial()">
                <i class="fas fa-download"></i>Exportar Reporte
            </button>
        </div>
    </div>

    <!-- INFORMACIÓN DEL VEHÍCULO -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-car"></i> Información del Vehículo</h3>
        </div>
        <div class="card-body">
            <div class="info-grid">
                <div class="info-item">
                    <label>Placa:</label>
                    <span class="placa-badge"><?php echo htmlspecialchars($vehiculo['placa']); ?></span>
                </div>
                <div class="info-item">
                    <label>Marca - Modelo:</label>
                    <span><?php echo htmlspecialchars($vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?></span>
                </div>
                <div class="info-item">
                    <label>Año:</label>
                    <span><?php echo $vehiculo['anio'] ? htmlspecialchars($vehiculo['anio']) : 'No especificado'; ?></span>
                </div>
                <div class="info-item">
                    <label>Color:</label>
                    <span>
                        <?php if ($vehiculo['color']): ?>
                        <div class="color-info">
                            <span class="color-indicator" style="background-color: <?php echo htmlspecialchars(strtolower($vehiculo['color'])); ?>"></span>
                            <?php echo htmlspecialchars($vehiculo['color']); ?>
                        </div>
                        <?php else: ?>
                        No especificado
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-item">
                    <label>Kilometraje:</label>
                    <span><?php echo $vehiculo['kilometraje'] ? number_format($vehiculo['kilometraje'], 0) . ' km' : 'Sin datos'; ?></span>
                </div>
                <div class="info-item">
                    <label>Estado:</label>
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
                </div>
                <div class="info-item">
                    <label>Total Pruebas:</label>
                    <span class="badge primary"><?php echo $vehiculo['total_pruebas']; ?></span>
                </div>
                <div class="info-item">
                    <label>Tasa de Aprobación:</label>
                    <span>
                        <?php 
                        $tasa_aprobacion = $vehiculo['total_pruebas'] > 0 
                            ? round(($vehiculo['pruebas_aprobadas'] / $vehiculo['total_pruebas']) * 100, 1) 
                            : 0;
                        $color_clase = $tasa_aprobacion >= 80 ? 'success' : ($tasa_aprobacion >= 60 ? 'warning' : 'danger');
                        ?>
                        <span class="badge <?php echo $color_clase; ?>">
                            <?php echo $tasa_aprobacion; ?>%
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- ESTADÍSTICAS DEL VEHÍCULO -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Estadísticas de Pruebas</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-vial"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['total_pruebas'] ?? 0; ?></h3>
                        <p>Total Pruebas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['pruebas_aprobadas'] ?? 0; ?></h3>
                        <p>Pruebas Aprobadas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['pruebas_reprobadas'] ?? 0; ?></h3>
                        <p>Pruebas Reprobadas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-redo"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['retests_realizados'] ?? 0; ?></h3>
                        <p>Re-tests Realizados</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['conductores_diferentes'] ?? 0; ?></h3>
                        <p>Conductores Diferentes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($estadisticas['promedio_alcohol'] ?? 0, 3); ?> g/L</h3>
                        <p>Promedio Alcohol</p>
                    </div>
                </div>
            </div>

            <!-- RANGOS DE ALCOHOL -->
            <div class="stats-subgrid" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                <h4 style="margin-bottom: 1rem; color: var(--dark);">Rangos de Nivel de Alcohol</h4>
                <div class="stats-grid">
                    <div class="stat-card mini">
                        <div class="stat-icon secondary">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['maximo_alcohol'] ?? 0, 3); ?> g/L</h3>
                            <p>Nivel Más Alto</p>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <div class="stat-icon secondary">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['minimo_alcohol'] ?? 0, 3); ?> g/L</h3>
                            <p>Nivel Más Bajo</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PERIODO DE PRUEBAS -->
            <div class="stats-subgrid" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                <h4 style="margin-bottom: 1rem; color: var(--dark);">Período de Pruebas</h4>
                <div class="stats-grid">
                    <div class="stat-card mini">
                        <div class="stat-icon secondary">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="stat-info">
                            <h3>
                                <?php if ($estadisticas['primera_prueba']): ?>
                                    <?php echo date('d/m/Y', strtotime($estadisticas['primera_prueba'])); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </h3>
                            <p>Primera Prueba</p>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <div class="stat-icon secondary">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3>
                                <?php if ($estadisticas['ultima_prueba']): ?>
                                    <?php echo date('d/m/Y', strtotime($estadisticas['ultima_prueba'])); ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </h3>
                            <p>Última Prueba</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CONDUCTORES DEL VEHÍCULO -->
    <?php if (!empty($conductores)): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> Conductores de este Vehículo</h3>
            <div class="card-actions">
                <span class="badge primary"><?php echo count($conductores); ?> conductores</span>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Conductor</th>
                            <th>DNI</th>
                            <th>Total Pruebas</th>
                            <th>Pruebas Aprobadas</th>
                            <th>Tasa de Aprobación</th>
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
                                </div>
                            </td>
                            <td>
                                <span class="dni-badge"><?php echo htmlspecialchars($conductor['dni']); ?></span>
                            </td>
                            <td>
                                <span class="badge primary"><?php echo $conductor['pruebas_realizadas']; ?></span>
                            </td>
                            <td>
                                <span class="badge success"><?php echo $conductor['pruebas_aprobadas']; ?></span>
                            </td>
                            <td>
                                <?php 
                                $tasa_aprobacion = $conductor['pruebas_realizadas'] > 0 
                                    ? round(($conductor['pruebas_aprobadas'] / $conductor['pruebas_realizadas']) * 100, 1) 
                                    : 0;
                                $color_clase = $tasa_aprobacion >= 80 ? 'success' : ($tasa_aprobacion >= 60 ? 'warning' : 'danger');
                                ?>
                                <span class="badge <?php echo $color_clase; ?>">
                                    <?php echo $tasa_aprobacion; ?>%
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- HISTORIAL DE PRUEBAS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Historial de Pruebas</h3>
            <div class="card-actions">
                <span class="badge primary"><?php echo count($pruebas); ?> registros</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($pruebas)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Conductor</th>
                            <th>Alcoholímetro</th>
                            <th>Nivel Alcohol</th>
                            <th>Resultado</th>
                            <th>Tipo</th>
                            <th>Supervisor</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pruebas as $prueba): ?>
                        <tr>
                            <td>
                                <span class="fecha-registro">
                                    <?php echo date('d/m/Y H:i', strtotime($prueba['fecha_prueba'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="conductor-info">
                                    <div class="conductor-nombre">
                                        <strong><?php echo htmlspecialchars($prueba['conductor_nombre']); ?></strong>
                                    </div>
                                    <div class="conductor-detalles">
                                        <small class="text-muted">DNI: <?php echo htmlspecialchars($prueba['conductor_dni']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="alcoholimetro-info">
                                    <div class="alcoholimetro-nombre">
                                        <strong><?php echo htmlspecialchars($prueba['alcoholimetro_nombre']); ?></strong>
                                    </div>
                                    <div class="alcoholimetro-detalles">
                                        <small class="text-muted">Serie: <?php echo htmlspecialchars($prueba['alcoholimetro_serie']); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="nivel-alcohol <?php echo $prueba['nivel_alcohol'] > 0.000 ? 'text-danger' : 'text-success'; ?>">
                                    <strong><?php echo number_format($prueba['nivel_alcohol'], 3); ?> g/L</strong>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge estado-<?php echo $prueba['resultado']; ?>">
                                    <?php echo $prueba['resultado'] === 'aprobado' ? 'Aprobado' : 'Reprobado'; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($prueba['es_retest']): ?>
                                <span class="badge warning">
                                    <i class="fas fa-redo"></i> Re-test
                                </span>
                                <?php else: ?>
                                <span class="badge primary">
                                    <i class="fas fa-vial"></i> Normal
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="supervisor-info">
                                    <?php echo htmlspecialchars($prueba['supervisor_nombre']); ?>
                                </span>
                            </td>
                            <td class="action-buttons">
                                <button type="button" class="btn-icon info" 
                                        title="Ver Detalles de Prueba"
                                        onclick="verDetallePrueba(<?php echo $prueba['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($prueba['resultado'] === 'reprobado' && !$prueba['es_retest']): ?>
                                <button type="button" class="btn-icon warning" 
                                        title="Solicitar Re-test"
                                        onclick="solicitarRetest(<?php echo $prueba['id']; ?>)">
                                    <i class="fas fa-redo"></i>
                                </button>
                                <?php endif; ?>
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
                    <i class="fas fa-vial"></i>
                </div>
                <h3>No hay pruebas registradas</h3>
                <p>Este vehículo no tiene pruebas de alcohol registradas</p>
                <div class="empty-actions">
                    <a href="pruebas-alcohol.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>Realizar Primera Prueba
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- MODAL PARA DETALLES DE PRUEBA -->
<div id="modalDetallePrueba" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="cerrarModalDetalle()"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-eye"></i> 
                    <span id="modalTituloDetalle">Detalles de Prueba</span>
                </h3>
                <button type="button" class="modal-close" onclick="cerrarModalDetalle()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="detallePruebaContenido">
                    <!-- Contenido cargado via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModalDetalle()">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ESTILOS ADICIONALES PARA HISTORIAL */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.info-item label {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.9rem;
}

.info-item span {
    color: var(--dark);
    font-size: 1rem;
}

.nivel-alcohol {
    font-weight: 600;
    font-size: 0.9rem;
}

.conductor-detalles, .alcoholimetro-detalles {
    margin-top: 0.25rem;
}

.status-badge.estado-aprobado {
    background: rgba(39, 174, 96, 0.15);
    color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.3);
}

.status-badge.estado-reprobado {
    background: rgba(231, 76, 60, 0.15);
    color: var(--danger);
    border: 1px solid rgba(231, 76, 60, 0.3);
}

.loading-state {
    text-align: center;
    padding: 2rem;
    color: var(--gray);
}

.loading-state i {
    font-size: 2rem;
    margin-bottom: 1rem;
}

/* Mantener todos los estilos existentes del modal y tabla */
.modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 1050; overflow-x: hidden; overflow-y: auto; outline: 0; }
.modal-backdrop { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); z-index: 1040; }
.modal-dialog { position: relative; width: auto; margin: 1.75rem auto; max-width: 600px; pointer-events: none; z-index: 1060; }
.modal-dialog.modal-lg { max-width: 800px; }
.modal-content { position: relative; display: flex; flex-direction: column; width: 100%; pointer-events: auto; background-color: #fff; background-clip: padding-box; border: 1px solid rgba(0, 0, 0, 0.2); border-radius: 12px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3); outline: 0; }
.modal-header { display: flex; align-items: center; justify-content: space-between; padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--light); border-top-left-radius: 12px; border-top-right-radius: 12px; }
.modal-title { margin: 0; color: var(--dark); font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.modal-close { background: none; border: none; font-size: 1.25rem; color: var(--gray); cursor: pointer; padding: 0.5rem; border-radius: 6px; transition: all 0.3s ease; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
.modal-close:hover { background: var(--danger); color: white; }
.modal-body { position: relative; flex: 1 1 auto; padding: 1.5rem; }
.modal-footer { display: flex; align-items: center; justify-content: flex-end; padding: 1.5rem; border-top: 1px solid var(--border); gap: 1rem; }
</style>

<script>
// FUNCIONES JS PARA HISTORIAL DE VEHÍCULO
document.addEventListener('DOMContentLoaded', function() {
    console.log('Historial de vehículo cargado - Vehículo ID: <?php echo $vehiculo_id; ?>');
});

function exportarHistorial() {
    const vehiculoId = <?php echo $vehiculo_id; ?>;
    const placa = '<?php echo $vehiculo['placa']; ?>';
    const url = `exportar-historial.php?vehiculo_id=${vehiculoId}&tipo=pdf`;
    
    console.log('Exportando historial para vehículo:', placa);
    window.open(url, '_blank');
}

function verDetallePrueba(pruebaId) {
    console.log('Cargando detalles de prueba:', pruebaId);
    
    // Mostrar loading
    document.getElementById('detallePruebaContenido').innerHTML = `
        <div class="loading-state">
            <i class="fas fa-spinner fa-spin"></i>
            <p>Cargando detalles...</p>
        </div>
    `;
    
    // Mostrar modal inmediatamente
    document.getElementById('modalDetallePrueba').style.display = 'block';
    document.body.style.overflow = 'hidden';
    
    // Cargar detalles via AJAX
    fetch(`ajax/detalle-prueba.php?id=${pruebaId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.text();
        })
        .then(html => {
            document.getElementById('detallePruebaContenido').innerHTML = html;
            document.getElementById('modalTituloDetalle').textContent = 'Detalles de Prueba #' + pruebaId;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('detallePruebaContenido').innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    Error al cargar los detalles de la prueba.
                </div>
            `;
        });
}

function cerrarModalDetalle() {
    const modal = document.getElementById('modalDetallePrueba');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

function solicitarRetest(pruebaId) {
    if (confirm('¿Está seguro que desea solicitar un re-test para esta prueba?')) {
        console.log('Solicitando re-test para prueba:', pruebaId);
        
        // Mostrar mensaje de procesamiento
        alert('Solicitud de re-test enviada. Esta función estará disponible próximamente.');
        
        // Para una implementación real, descomenta el código siguiente:
        /*
        fetch('ajax/solicitar-retest.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `prueba_id=${pruebaId}&motivo=Solicitud desde historial de vehículo`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Solicitud de re-test enviada correctamente');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'No se pudo solicitar el re-test'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al solicitar el re-test');
        });
        */
    }
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalDetalle();
    }
});

// Funciones de filtrado (si se implementan)
function filtrarPorFecha(fecha) {
    console.log('Filtrando por fecha:', fecha);
    // Implementar filtrado por fecha si es necesario
}

function filtrarPorResultado(resultado) {
    console.log('Filtrando por resultado:', resultado);
    // Implementar filtrado por resultado si es necesario
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>