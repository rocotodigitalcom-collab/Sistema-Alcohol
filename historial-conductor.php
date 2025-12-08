<?php
// historial-conductor.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Historial de Pruebas por Conductor';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'historial-conductor.php' => 'Historial por Conductor'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// OBTENER LISTA DE CONDUCTORES PARA EL FILTRO
$conductores = $db->fetchAll("
    SELECT id, nombre, apellido, dni, estado
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor'
    ORDER BY nombre, apellido
", [$cliente_id]);

// VARIABLES PARA FILTROS
$conductor_id = $_GET['conductor_id'] ?? null;
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$resultado_filtro = $_GET['resultado'] ?? '';

// OBTENER HISTORIAL DE PRUEBAS SI HAY CONDUCTOR SELECCIONADO
$historial = [];
$estadisticas_conductor = null;
$info_conductor = null;

if ($conductor_id) {
    // VERIFICAR QUE EL CONDUCTOR PERTENECE AL CLIENTE
    $info_conductor = $db->fetchOne("
        SELECT id, nombre, apellido, dni, email, telefono, estado
        FROM usuarios 
        WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
    ", [$conductor_id, $cliente_id]);

    if ($info_conductor) {
        // CONSTRUIR CONSULTA CON FILTROS
        $params = [$cliente_id, $conductor_id];
        $where_conditions = ["p.cliente_id = ?", "p.conductor_id = ?"];
        
        if ($fecha_inicio) {
            $where_conditions[] = "DATE(p.fecha_prueba) >= ?";
            $params[] = $fecha_inicio;
        }
        
        if ($fecha_fin) {
            $where_conditions[] = "DATE(p.fecha_prueba) <= ?";
            $params[] = $fecha_fin;
        }
        
        if ($resultado_filtro) {
            $where_conditions[] = "p.resultado = ?";
            $params[] = $resultado_filtro;
        }

        $where_sql = implode(" AND ", $where_conditions);

        // OBTENER HISTORIAL DE PRUEBAS
        $historial = $db->fetchAll("
            SELECT 
                p.id,
                p.nivel_alcohol,
                p.limite_permisible,
                p.resultado,
                p.es_retest,
                p.intento_numero,
                p.motivo_retest,
                p.fecha_prueba,
                p.latitud,
                p.longitud,
                p.observaciones,
                a.nombre_activo as alcoholimetro,
                a.numero_serie,
                v.placa,
                v.marca,
                v.modelo,
                u_sup.nombre as supervisor_nombre,
                u_sup.apellido as supervisor_apellido,
                p_prueba_original.nivel_alcohol as nivel_original
            FROM pruebas p
            LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
            LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
            LEFT JOIN usuarios u_sup ON p.supervisor_id = u_sup.id
            LEFT JOIN pruebas p_prueba_original ON p.prueba_padre_id = p_prueba_original.id
            WHERE {$where_sql}
            ORDER BY p.fecha_prueba DESC
        ", $params);

        // OBTENER ESTADÍSTICAS DEL CONDUCTOR
        $estadisticas_conductor = $db->fetchOne("
            SELECT 
                COUNT(*) as total_pruebas,
                SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as pruebas_aprobadas,
                SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as pruebas_reprobadas,
                SUM(CASE WHEN es_retest = 1 THEN 1 ELSE 0 END) as retests_realizados,
                AVG(nivel_alcohol) as promedio_alcohol,
                MAX(nivel_alcohol) as maximo_alcohol,
                MIN(nivel_alcohol) as minimo_alcohol,
                COUNT(DISTINCT DATE(fecha_prueba)) as dias_con_pruebas,
                COUNT(DISTINCT alcoholimetro_id) as alcoholimetros_utilizados,
                COUNT(DISTINCT vehiculo_id) as vehiculos_utilizados
            FROM pruebas 
            WHERE cliente_id = ? AND conductor_id = ?
            AND DATE(fecha_prueba) BETWEEN ? AND ?
        ", [$cliente_id, $conductor_id, $fecha_inicio, $fecha_fin]);

        // OBTENER ESTADÍSTICAS DE RETESTS
        $estadisticas_retests = $db->fetchOne("
            SELECT 
                COUNT(*) as total_retests,
                SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as retests_aprobados,
                AVG(p.nivel_alcohol) as promedio_retest,
                AVG(TIMESTAMPDIFF(MINUTE, p_orig.fecha_prueba, p.fecha_prueba)) as tiempo_promedio_retest
            FROM pruebas p
            JOIN pruebas p_orig ON p.prueba_padre_id = p_orig.id
            WHERE p.cliente_id = ? AND p.conductor_id = ? 
            AND p.es_retest = 1
            AND DATE(p.fecha_prueba) BETWEEN ? AND ?
        ", [$cliente_id, $conductor_id, $fecha_inicio, $fecha_fin]);
    }
}

// PROCESAR EXPORTACIÓN SI SE SOLICITA
if (isset($_GET['exportar']) && $conductor_id && $info_conductor) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=historial_conductor_' . $info_conductor['dni'] . '_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // HEADER CSV
    fputcsv($output, [
        'Fecha y Hora', 'Nivel Alcohol', 'Límite Permisible', 'Resultado', 
        'Es Re-test', 'Intento', 'Alcoholímetro', 'Vehículo', 'Supervisor', 'Observaciones'
    ]);
    
    // DATOS
    foreach ($historial as $prueba) {
        $vehiculo = $prueba['placa'] ? $prueba['marca'] . ' ' . $prueba['modelo'] . ' (' . $prueba['placa'] . ')' : 'No especificado';
        $supervisor = $prueba['supervisor_nombre'] . ' ' . $prueba['supervisor_apellido'];
        
        fputcsv($output, [
            date('d/m/Y H:i', strtotime($prueba['fecha_prueba'])),
            $prueba['nivel_alcohol'] . ' g/L',
            $prueba['limite_permisible'] . ' g/L',
            ucfirst($prueba['resultado']),
            $prueba['es_retest'] ? 'Sí' : 'No',
            $prueba['intento_numero'],
            $prueba['alcoholimetro'] . ' (' . $prueba['numero_serie'] . ')',
            $vehiculo,
            $supervisor,
            $prueba['observaciones'] ?? ''
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<div class="content-body">
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Consulta el historial completo de pruebas por conductor</p>
        </div>
        <div class="header-actions">
            <a href="registrar-conductor.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>Volver a Conductores
            </a>
            <?php if ($conductor_id && $info_conductor): ?>
            <a href="?exportar=1&conductor_id=<?php echo $conductor_id; ?>&fecha_inicio=<?php echo $fecha_inicio; ?>&fecha_fin=<?php echo $fecha_fin; ?>&resultado=<?php echo $resultado_filtro; ?>" 
               class="btn btn-primary">
                <i class="fas fa-download"></i>Exportar CSV
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="conductor_id" class="form-label">Conductor *</label>
                        <select id="conductor_id" name="conductor_id" class="form-control" required>
                            <option value="">Seleccionar conductor</option>
                            <?php foreach ($conductores as $conductor): ?>
                            <option value="<?php echo $conductor['id']; ?>" 
                                <?php echo ($conductor_id == $conductor['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($conductor['nombre'] . ' ' . $conductor['apellido'] . ' - ' . $conductor['dni']); ?>
                                <?php echo !$conductor['estado'] ? ' (Inactivo)' : ''; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" 
                               value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin" class="form-label">Fecha Fin</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" 
                               value="<?php echo htmlspecialchars($fecha_fin); ?>">
                    </div>
                    <div class="form-group">
                        <label for="resultado" class="form-label">Resultado</label>
                        <select id="resultado" name="resultado" class="form-control">
                            <option value="">Todos los resultados</option>
                            <option value="aprobado" <?php echo ($resultado_filtro == 'aprobado') ? 'selected' : ''; ?>>Aprobado</option>
                            <option value="reprobado" <?php echo ($resultado_filtro == 'reprobado') ? 'selected' : ''; ?>>Reprobado</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="historial-conductor.php" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($conductor_id && $info_conductor): ?>
    
    <!-- INFORMACIÓN DEL CONDUCTOR -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user"></i> Información del Conductor</h3>
        </div>
        <div class="card-body">
            <div class="conductor-detail-grid">
                <div class="detail-item">
                    <span class="detail-label">Nombre Completo:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($info_conductor['nombre'] . ' ' . $info_conductor['apellido']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">DNI:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($info_conductor['dni']); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Email:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($info_conductor['email'] ?? 'No especificado'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Teléfono:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($info_conductor['telefono'] ?? 'No especificado'); ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Estado:</span>
                    <span class="status-badge estado-<?php echo $info_conductor['estado'] ? 'activo' : 'inactivo'; ?>">
                        <?php echo $info_conductor['estado'] ? 'Activo' : 'Inactivo'; ?>
                    </span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Período:</span>
                    <span class="detail-value"><?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- ESTADÍSTICAS DEL CONDUCTOR -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Estadísticas del Conductor</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-vial"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_conductor['total_pruebas'] ?? 0; ?></h3>
                        <p>Total Pruebas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_conductor['pruebas_aprobadas'] ?? 0; ?></h3>
                        <p>Pruebas Aprobadas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_conductor['pruebas_reprobadas'] ?? 0; ?></h3>
                        <p>Pruebas Reprobadas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-redo"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_conductor['retests_realizados'] ?? 0; ?></h3>
                        <p>Re-tests Realizados</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($estadisticas_conductor['promedio_alcohol'] ?? 0, 3); ?></h3>
                        <p>Promedio Alcohol (g/L)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon average">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas_conductor['dias_con_pruebas'] ?? 0; ?></h3>
                        <p>Días con Pruebas</p>
                    </div>
                </div>
            </div>

            <!-- ESTADÍSTICAS ADICIONALES DE RETESTS -->
            <?php if (($estadisticas_retests['total_retests'] ?? 0) > 0): ?>
            <div class="stats-subgrid" style="margin-top: 2rem; padding-top: 2rem; border-top: 1px solid var(--border);">
                <h4 style="margin-bottom: 1rem; color: var(--dark);">Estadísticas de Re-tests</h4>
                <div class="stats-grid">
                    <div class="stat-card mini">
                        <div class="stat-icon secondary">
                            <i class="fas fa-sync"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas_retests['total_retests'] ?? 0; ?></h3>
                            <p>Total Re-tests</p>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <div class="stat-icon success">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas_retests['retests_aprobados'] ?? 0; ?></h3>
                            <p>Re-tests Aprobados</p>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <div class="stat-icon info">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas_retests['promedio_retest'] ?? 0, 3); ?></h3>
                            <p>Promedio Re-test (g/L)</p>
                        </div>
                    </div>
                    <div class="stat-card mini">
                        <div class="stat-icon warning">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas_retests['tiempo_promedio_retest'] ?? 0, 0); ?></h3>
                            <p>Min. Promedio Re-test</p>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- HISTORIAL DE PRUEBAS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Historial de Pruebas</h3>
            <div class="card-actions">
                <span class="badge primary"><?php echo count($historial); ?> registros</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($historial)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Fecha y Hora</th>
                            <th>Nivel Alcohol</th>
                            <th>Resultado</th>
                            <th>Tipo</th>
                            <th>Alcoholímetro</th>
                            <th>Vehículo</th>
                            <th>Supervisor</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($historial as $prueba): ?>
                        <tr>
                            <td>
                                <span class="fecha-hora">
                                    <?php echo date('d/m/Y H:i', strtotime($prueba['fecha_prueba'])); ?>
                                </span>
                            </td>
                            <td>
                                <div class="nivel-alcohol-container">
                                    <span class="nivel-alcohol <?php echo $prueba['nivel_alcohol'] > $prueba['limite_permisible'] ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo number_format($prueba['nivel_alcohol'], 3); ?> g/L
                                    </span>
                                    <?php if ($prueba['es_retest'] && $prueba['nivel_original']): ?>
                                    <div class="nivel-original">
                                        <small>Original: <?php echo number_format($prueba['nivel_original'], 3); ?> g/L</small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="status-badge resultado-<?php echo $prueba['resultado']; ?>">
                                    <?php echo ucfirst($prueba['resultado']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($prueba['es_retest']): ?>
                                <span class="badge warning" title="Re-test - Intento <?php echo $prueba['intento_numero']; ?>">
                                    <i class="fas fa-redo"></i> Re-test
                                </span>
                                <?php if ($prueba['motivo_retest']): ?>
                                <br><small><?php echo htmlspecialchars($prueba['motivo_retest']); ?></small>
                                <?php endif; ?>
                                <?php else: ?>
                                <span class="badge primary">Prueba Inicial</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="equipo-info">
                                    <strong><?php echo htmlspecialchars($prueba['alcoholimetro']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($prueba['numero_serie']); ?></small>
                                </div>
                            </td>
                            <td>
                                <?php if ($prueba['placa']): ?>
                                <div class="vehiculo-info">
                                    <strong><?php echo htmlspecialchars($prueba['placa']); ?></strong>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($prueba['marca'] . ' ' . $prueba['modelo']); ?></small>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">No especificado</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($prueba['supervisor_nombre']): ?>
                                <span class="supervisor-info">
                                    <?php echo htmlspecialchars($prueba['supervisor_nombre'] . ' ' . $prueba['supervisor_apellido']); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">No registrado</span>
                                <?php endif; ?>
                            </td>
                            <td class="action-buttons">
                                <button type="button" class="btn-icon info" 
                                        title="Ver Detalles"
                                        onclick="verDetallesPrueba(<?php echo $prueba['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($prueba['latitud'] && $prueba['longitud']): ?>
                                <button type="button" class="btn-icon primary" 
                                        title="Ver Ubicación"
                                        onclick="verUbicacion(<?php echo $prueba['latitud']; ?>, <?php echo $prueba['longitud']; ?>)">
                                    <i class="fas fa-map-marker-alt"></i>
                                </button>
                                <?php endif; ?>
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
                <p>No se encontraron pruebas para este conductor en el período seleccionado</p>
                <div class="empty-actions">
                    <a href="?conductor_id=<?php echo $conductor_id; ?>" class="btn btn-primary">
                        <i class="fas fa-refresh"></i>Ver Todo el Historial
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($conductor_id && !$info_conductor): ?>
    
    <!-- ERROR SI EL CONDUCTOR NO EXISTE -->
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        El conductor seleccionado no existe o no pertenece a su empresa.
    </div>

    <?php else: ?>
    
    <!-- ESTADO INICIAL -->
    <div class="card">
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-search"></i>
                </div>
                <h3>Seleccione un conductor</h3>
                <p>Utilice los filtros para seleccionar un conductor y ver su historial de pruebas</p>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<!-- MODAL PARA DETALLES DE PRUEBA -->
<div id="modalDetallesPrueba" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="cerrarModalDetalles()"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-eye"></i> 
                    <span id="modalTituloDetalles">Detalles de Prueba</span>
                </h3>
                <button type="button" class="modal-close" onclick="cerrarModalDetalles()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="detallesPruebaContent">
                    <!-- Los detalles se cargarán aquí via AJAX -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModalDetalles()">
                    <i class="fas fa-times"></i> Cerrar
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
// FUNCIONES JS PARA HISTORIAL
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de historial de conductor cargada');
    
    // Validar fechas en el formulario
    const formFiltros = document.querySelector('.filter-form');
    if (formFiltros) {
        formFiltros.addEventListener('submit', function(e) {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            const conductorId = document.getElementById('conductor_id').value;
            
            if (!conductorId) {
                e.preventDefault();
                alert('Por favor, seleccione un conductor.');
                return false;
            }
            
            if (fechaInicio && fechaFin) {
                const inicio = new Date(fechaInicio);
                const fin = new Date(fechaFin);
                
                if (inicio > fin) {
                    e.preventDefault();
                    alert('La fecha de inicio no puede ser mayor a la fecha de fin.');
                    return false;
                }
            }
            
            return true;
        });
    }
});

// Función para ver detalles de prueba (simulada - en producción cargaría via AJAX)
function verDetallesPrueba(pruebaId) {
    console.log('Ver detalles de prueba ID:', pruebaId);
    
    // En una implementación real, esto cargaría los detalles via AJAX
    const detallesContent = `
        <div class="detalles-grid">
            <div class="detalle-item">
                <label>ID de Prueba:</label>
                <span>${pruebaId}</span>
            </div>
            <div class="detalle-item">
                <label>Fecha y Hora:</label>
                <span>26/11/2024 14:30</span>
            </div>
            <div class="detalle-item">
                <label>Nivel de Alcohol:</label>
                <span class="text-success">0.000 g/L</span>
            </div>
            <div class="detalle-item">
                <label>Resultado:</label>
                <span class="status-badge resultado-aprobado">Aprobado</span>
            </div>
            <div class="detalle-item">
                <label>Alcoholímetro:</label>
                <span>Alcoholímetro Principal (ALC-001)</span>
            </div>
            <div class="detalle-item">
                <label>Vehículo:</label>
                <span>Toyota Hilux (ABC-123)</span>
            </div>
            <div class="detalle-item">
                <label>Supervisor:</label>
                <span>Admin Demo</span>
            </div>
            <div class="detalle-item full-width">
                <label>Observaciones:</label>
                <span>Prueba realizada de forma satisfactoria. Conductor en óptimas condiciones.</span>
            </div>
        </div>
    `;
    
    document.getElementById('detallesPruebaContent').innerHTML = detallesContent;
    document.getElementById('modalDetallesPrueba').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function cerrarModalDetalles() {
    document.getElementById('modalDetallesPrueba').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Función para ver ubicación en mapa
function verUbicacion(latitud, longitud) {
    console.log('Ver ubicación:', latitud, longitud);
    
    // En una implementación real, esto abriría un mapa con la ubicación
    const url = `https://www.google.com/maps?q=${latitud},${longitud}`;
    window.open(url, '_blank');
}

// Cerrar modales con ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalDetalles();
    }
});

// Auto-seleccionar conductor si viene por parámetro
<?php if (isset($_GET['conductor_id']) && !$conductor_id): ?>
alert('El conductor seleccionado no existe o no tiene permisos para verlo.');
<?php endif; ?>
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>