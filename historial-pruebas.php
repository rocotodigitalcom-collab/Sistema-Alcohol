<?php
// historial-pruebas.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Historial de Pruebas';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'historial-pruebas.php' => 'Historial de Pruebas'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// Obtener configuración del cliente para límites
$configuracion = $db->fetchOne("
    SELECT limite_alcohol_permisible, nivel_advertencia, nivel_critico, unidad_medida 
    FROM configuraciones 
    WHERE cliente_id = ?
", [$cliente_id]);

// FILTROS
$filtros = [];
$where_conditions = ["p.cliente_id = ?"];
$params = [$cliente_id];

// Filtro por fecha
if (!empty($_GET['fecha_desde'])) {
    $filtros['fecha_desde'] = $_GET['fecha_desde'];
    $where_conditions[] = "DATE(p.fecha_prueba) >= ?";
    $params[] = $_GET['fecha_desde'];
}

if (!empty($_GET['fecha_hasta'])) {
    $filtros['fecha_hasta'] = $_GET['fecha_hasta'];
    $where_conditions[] = "DATE(p.fecha_prueba) <= ?";
    $params[] = $_GET['fecha_hasta'];
}

// Filtro por conductor
if (!empty($_GET['conductor_id'])) {
    $filtros['conductor_id'] = $_GET['conductor_id'];
    $where_conditions[] = "p.conductor_id = ?";
    $params[] = $_GET['conductor_id'];
}

// Filtro por resultado
if (!empty($_GET['resultado'])) {
    $filtros['resultado'] = $_GET['resultado'];
    $where_conditions[] = "p.resultado = ?";
    $params[] = $_GET['resultado'];
}

// Filtro por alcoholímetro
if (!empty($_GET['alcoholimetro_id'])) {
    $filtros['alcoholimetro_id'] = $_GET['alcoholimetro_id'];
    $where_conditions[] = "p.alcoholimetro_id = ?";
    $params[] = $_GET['alcoholimetro_id'];
}

// Filtro por supervisor
if (!empty($_GET['supervisor_id'])) {
    $filtros['supervisor_id'] = $_GET['supervisor_id'];
    $where_conditions[] = "p.supervisor_id = ?";
    $params[] = $_GET['supervisor_id'];
}

// Construir WHERE
$where_sql = implode(' AND ', $where_conditions);

// Obtener datos para filtros
$conductores = $db->fetchAll("
    SELECT id, nombre, apellido, dni 
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor' AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

$alcoholimetros = $db->fetchAll("
    SELECT id, numero_serie, nombre_activo 
    FROM alcoholimetros 
    WHERE cliente_id = ? AND estado = 'activo'
    ORDER BY nombre_activo
", [$cliente_id]);

$supervisores = $db->fetchAll("
    SELECT id, nombre, apellido 
    FROM usuarios 
    WHERE cliente_id = ? AND rol IN ('supervisor', 'admin') AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

// Obtener estadísticas
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN resultado = 'aprobado' THEN 1 ELSE 0 END) as aprobadas,
        SUM(CASE WHEN resultado = 'reprobado' THEN 1 ELSE 0 END) as reprobadas,
        AVG(nivel_alcohol) as promedio_alcohol,
        MAX(nivel_alcohol) as maximo_alcohol
    FROM pruebas p
    WHERE $where_sql
", $params);

// Obtener lista de pruebas con filtros
$pruebas = $db->fetchAll("
    SELECT p.*, 
           a.nombre_activo as alcoholimetro_nombre,
           CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
           u_conductor.dni as conductor_dni,
           CONCAT(u_supervisor.nombre, ' ', u_supervisor.apellido) as supervisor_nombre,
           v.placa as vehiculo_placa,
           v.marca as vehiculo_marca,
           v.modelo as vehiculo_modelo
    FROM pruebas p
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
    LEFT JOIN usuarios u_supervisor ON p.supervisor_id = u_supervisor.id
    LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
    WHERE $where_sql
    ORDER BY p.fecha_prueba DESC
    LIMIT 500
", $params);

// Procesar exportación a CSV
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=historial_pruebas_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Headers CSV
    fputcsv($output, [
        'Fecha', 'Hora', 'Conductor', 'DNI', 'Alcoholímetro', 'Supervisor', 
        'Vehículo', 'Nivel Alcohol', 'Resultado', 'Observaciones', 'Ubicación'
    ]);
    
    // Datos
    foreach ($pruebas as $prueba) {
        fputcsv($output, [
            date('d/m/Y', strtotime($prueba['fecha_prueba'])),
            date('H:i', strtotime($prueba['fecha_prueba'])),
            $prueba['conductor_nombre'],
            $prueba['conductor_dni'],
            $prueba['alcoholimetro_nombre'],
            $prueba['supervisor_nombre'],
            $prueba['vehiculo_placa'] ?? 'N/A',
            number_format($prueba['nivel_alcohol'], 3) . ' ' . $configuracion['unidad_medida'],
            ucfirst($prueba['resultado']),
            $prueba['observaciones'] ?? '',
            ($prueba['latitud'] && $prueba['longitud']) ? $prueba['latitud'] . ', ' . $prueba['longitud'] : 'N/A'
        ]);
    }
    
    fclose($output);
    exit;
}
?>

<div class="content-body">
    <!-- HEADER IDÉNTICO -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Consulta y analiza el historial completo de pruebas</p>
        </div>
        <div class="header-actions">
            <a href="?exportar=csv" class="btn btn-outline">
                <i class="fas fa-download"></i>Exportar CSV
            </a>
            <a href="prueba-rapida.php" class="btn btn-primary">
                <i class="fas fa-vial"></i>Nueva Prueba
            </a>
        </div>
    </div>

    <div class="crud-container">
        <!-- CARD DE FILTROS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="account-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fecha_desde">Fecha Desde</label>
                            <input type="date" id="fecha_desde" name="fecha_desde" 
                                   value="<?php echo htmlspecialchars($_GET['fecha_desde'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="fecha_hasta">Fecha Hasta</label>
                            <input type="date" id="fecha_hasta" name="fecha_hasta" 
                                   value="<?php echo htmlspecialchars($_GET['fecha_hasta'] ?? ''); ?>" 
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
                            <label for="alcoholimetro_id">Alcoholímetro</label>
                            <select id="alcoholimetro_id" name="alcoholimetro_id" class="form-control">
                                <option value="">Todos los alcoholímetros</option>
                                <?php foreach ($alcoholimetros as $alcoholimetro): ?>
                                <option value="<?php echo $alcoholimetro['id']; ?>" 
                                    <?php echo ($_GET['alcoholimetro_id'] ?? '') == $alcoholimetro['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($alcoholimetro['nombre_activo'] . ' (' . $alcoholimetro['numero_serie'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="supervisor_id">Supervisor</label>
                            <select id="supervisor_id" name="supervisor_id" class="form-control">
                                <option value="">Todos los supervisores</option>
                                <?php foreach ($supervisores as $supervisor): ?>
                                <option value="<?php echo $supervisor['id']; ?>" 
                                    <?php echo ($_GET['supervisor_id'] ?? '') == $supervisor['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supervisor['nombre'] . ' ' . $supervisor['apellido']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="resultado">Resultado</label>
                            <select id="resultado" name="resultado" class="form-control">
                                <option value="">Todos los resultados</option>
                                <option value="aprobado" <?php echo ($_GET['resultado'] ?? '') == 'aprobado' ? 'selected' : ''; ?>>Aprobado</option>
                                <option value="reprobado" <?php echo ($_GET['resultado'] ?? '') == 'reprobado' ? 'selected' : ''; ?>>Reprobado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Aplicar Filtros
                        </button>
                        <a href="historial-pruebas.php" class="btn btn-outline">
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
                <h3><i class="fas fa-chart-bar"></i> Estadísticas del Período</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon total">
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
                            <h3><?php echo $estadisticas['aprobadas'] ?? 0; ?></h3>
                            <p>Pruebas Aprobadas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['reprobadas'] ?? 0; ?></h3>
                            <p>Pruebas Reprobadas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['promedio_alcohol'] ?? 0, 3); ?></h3>
                            <p>Promedio Alcohol</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon critical">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['maximo_alcohol'] ?? 0, 3); ?></h3>
                            <p>Máximo Registrado</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon info">
                            <i class="fas fa-percentage"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_pruebas'] > 0 ? number_format(($estadisticas['aprobadas'] / $estadisticas['total_pruebas']) * 100, 1) : 0; ?>%</h3>
                            <p>Tasa de Aprobación</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD DE RESULTADOS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Historial de Pruebas</h3>
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
                                <th>Fecha y Hora</th>
                                <th>Conductor</th>
                                <th>DNI</th>
                                <th>Alcoholímetro</th>
                                <th>Supervisor</th>
                                <th>Vehículo</th>
                                <th>Nivel Alcohol</th>
                                <th>Resultado</th>
                                <th>Observaciones</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pruebas as $prueba): ?>
                            <tr>
                                <td>
                                    <div class="fecha-hora">
                                        <div class="fecha"><?php echo date('d/m/Y', strtotime($prueba['fecha_prueba'])); ?></div>
                                        <div class="hora"><?php echo date('H:i', strtotime($prueba['fecha_prueba'])); ?></div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($prueba['conductor_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($prueba['conductor_dni']); ?></td>
                                <td><?php echo htmlspecialchars($prueba['alcoholimetro_nombre']); ?></td>
                                <td><?php echo htmlspecialchars($prueba['supervisor_nombre']); ?></td>
                                <td>
                                    <?php if ($prueba['vehiculo_placa']): ?>
                                        <div class="vehiculo-info">
                                            <div class="placa"><?php echo htmlspecialchars($prueba['vehiculo_placa']); ?></div>
                                            <div class="modelo"><?php echo htmlspecialchars($prueba['vehiculo_marca'] . ' ' . $prueba['vehiculo_modelo']); ?></div>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
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
                                <td>
                                    <?php if (!empty($prueba['observaciones'])): ?>
                                        <span class="observaciones-tooltip" title="<?php echo htmlspecialchars($prueba['observaciones']); ?>">
                                            <i class="fas fa-comment"></i>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="nueva-prueba.php?editar=<?php echo $prueba['id']; ?>" class="btn-icon" title="Ver Detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($prueba['resultado'] === 'reprobado'): ?>
                                        <a href="solicitar-retest.php?prueba_id=<?php echo $prueba['id']; ?>" class="btn-icon warning" title="Solicitar Re-test">
                                            <i class="fas fa-redo"></i>
                                        </a>
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
                        <i class="fas fa-history"></i>
                    </div>
                    <h3>No hay pruebas registradas</h3>
                    <p>No se encontraron pruebas con los filtros seleccionados</p>
                    <a href="prueba-rapida.php" class="btn btn-primary">
                        <i class="fas fa-vial"></i>
                        Registrar Primera Prueba
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ESTILOS CSS INTEGRADOS (Mismo patrón + mejoras para historial) -->
<style>
/* [Todos los estilos CSS del patrón aquí - idénticos a los módulos anteriores] */
.crud-container { margin-top: 1.5rem; width: 100%; }
.data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 0; }
.data-table th { background: var(--light); padding: 1rem; text-align: left; font-weight: 600; color: var(--dark); border-bottom: 2px solid var(--border); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
.data-table td { padding: 1rem; border-bottom: 1px solid var(--border); color: var(--dark); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover { background: rgba(52, 152, 219, 0.04); }
.action-buttons { display: flex; gap: 0.5rem; justify-content: center; }
.btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: var(--light); color: var(--dark); text-decoration: none; transition: all 0.3s ease; }
.btn-icon:hover { background: var(--primary); color: white; transform: translateY(-2px); }
.btn-icon.warning:hover { background: var(--warning); }
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

/* ESTILOS ESPECÍFICOS PARA HISTORIAL */
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

.stat-icon.total { background: rgba(52, 152, 219, 0.15); color: var(--primary); }
.stat-icon.success { background: rgba(39, 174, 96, 0.15); color: var(--success); }
.stat-icon.danger { background: rgba(231, 76, 60, 0.15); color: var(--danger); }
.stat-icon.warning { background: rgba(243, 156, 18, 0.15); color: var(--warning); }
.stat-icon.critical { background: rgba(231, 76, 60, 0.15); color: var(--danger); }
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

.fecha-hora .fecha {
    font-weight: 600;
    color: var(--dark);
}

.fecha-hora .hora {
    font-size: 0.8rem;
    color: var(--gray);
}

.vehiculo-info .placa {
    font-weight: 600;
    color: var(--dark);
}

.vehiculo-info .modelo {
    font-size: 0.8rem;
    color: var(--gray);
}

.observaciones-tooltip {
    cursor: help;
    color: var(--primary);
    font-size: 1rem;
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
}
</style>

<script>
// FUNCIONES JS PARA HISTORIAL
document.addEventListener('DOMContentLoaded', function() {
    // Establecer fechas por defecto (últimos 30 días)
    const fechaHasta = document.getElementById('fecha_hasta');
    const fechaDesde = document.getElementById('fecha_desde');
    
    if (fechaHasta && !fechaHasta.value) {
        const hoy = new Date();
        fechaHasta.value = hoy.toISOString().split('T')[0];
    }
    
    if (fechaDesde && !fechaDesde.value) {
        const hace30Dias = new Date();
        hace30Dias.setDate(hace30Dias.getDate() - 30);
        fechaDesde.value = hace30Dias.toISOString().split('T')[0];
    }

    // Tooltips para observaciones
    const tooltips = document.querySelectorAll('.observaciones-tooltip');
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function(e) {
            const title = this.getAttribute('title');
            if (title) {
                // Crear tooltip personalizado
                const tooltipElement = document.createElement('div');
                tooltipElement.className = 'custom-tooltip';
                tooltipElement.textContent = title;
                tooltipElement.style.cssText = `
                    position: absolute;
                    background: rgba(0,0,0,0.8);
                    color: white;
                    padding: 0.5rem 1rem;
                    border-radius: 6px;
                    font-size: 0.8rem;
                    z-index: 1000;
                    max-width: 300px;
                    word-wrap: break-word;
                `;
                document.body.appendChild(tooltipElement);
                
                const rect = this.getBoundingClientRect();
                tooltipElement.style.left = (rect.left + window.scrollX) + 'px';
                tooltipElement.style.top = (rect.top + window.scrollY - tooltipElement.offsetHeight - 10) + 'px';
                
                this._tooltipElement = tooltipElement;
            }
        });
        
        tooltip.addEventListener('mouseleave', function() {
            if (this._tooltipElement) {
                this._tooltipElement.remove();
                this._tooltipElement = null;
            }
        });
    });

    // Función para imprimir reporte
    window.imprimirReporte = function() {
        window.print();
    };

    // Función para exportar datos
    window.exportarDatos = function(formato) {
        if (formato === 'csv') {
            window.location.href = '?exportar=csv&' + new URLSearchParams(window.location.search).toString();
        } else if (formato === 'pdf') {
            alert('Exportación a PDF disponible próximamente');
        }
    };
});

// Confirmación antes de acciones importantes
function confirmarAccion(mensaje) {
    return confirm(mensaje);
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';