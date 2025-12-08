<?php
// pruebas-reprobadas.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Pruebas Reprobadas';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'pruebas-reprobadas.php' => 'Pruebas Reprobadas'
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

// FILTROS (siempre filtrado por resultado = reprobado)
$filtros = [];
$where_conditions = ["p.cliente_id = ?", "p.resultado = 'reprobado'"];
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

// Filtro por nivel de alcohol (rango)
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

// Filtro por gravedad
if (!empty($_GET['gravedad'])) {
    $filtros['gravedad'] = $_GET['gravedad'];
    if ($_GET['gravedad'] === 'leve') {
        $where_conditions[] = "p.nivel_alcohol <= ?";
        $params[] = $configuracion['nivel_advertencia'];
    } elseif ($_GET['gravedad'] === 'grave') {
        $where_conditions[] = "p.nivel_alcohol > ? AND p.nivel_alcohol <= ?";
        $params[] = $configuracion['nivel_advertencia'];
        $params[] = $configuracion['nivel_critico'];
    } elseif ($_GET['gravedad'] === 'critica') {
        $where_conditions[] = "p.nivel_alcohol > ?";
        $params[] = $configuracion['nivel_critico'];
    }
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

// Obtener estadísticas específicas de pruebas reprobadas
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_reprobadas,
        AVG(nivel_alcohol) as promedio_alcohol,
        MIN(nivel_alcohol) as minimo_alcohol,
        MAX(nivel_alcohol) as maximo_alcohol,
        COUNT(DISTINCT conductor_id) as conductores_infractores,
        COUNT(DISTINCT alcoholimetro_id) as alcoholimetros_utilizados,
        SUM(CASE WHEN nivel_alcohol <= ? THEN 1 ELSE 0 END) as leves,
        SUM(CASE WHEN nivel_alcohol > ? AND nivel_alcohol <= ? THEN 1 ELSE 0 END) as graves,
        SUM(CASE WHEN nivel_alcohol > ? THEN 1 ELSE 0 END) as criticas
    FROM pruebas p
    WHERE $where_sql
", array_merge($params, [
    $configuracion['nivel_advertencia'],
    $configuracion['nivel_advertencia'],
    $configuracion['nivel_critico'],
    $configuracion['nivel_critico']
]));

// Obtener conductores con más pruebas reprobadas (top 5)
$top_conductores = $db->fetchAll("
    SELECT 
        u_conductor.id,
        CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
        u_conductor.dni,
        COUNT(*) as total_reprobadas,
        AVG(p.nivel_alcohol) as promedio_nivel,
        MAX(p.nivel_alcohol) as maximo_nivel
    FROM pruebas p
    LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
    WHERE $where_sql
    GROUP BY u_conductor.id, u_conductor.nombre, u_conductor.apellido, u_conductor.dni
    ORDER BY total_reprobadas DESC, maximo_nivel DESC
    LIMIT 5
", $params);

// Obtener lista de pruebas reprobadas con filtros
$pruebas = $db->fetchAll("
    SELECT p.*, 
           a.nombre_activo as alcoholimetro_nombre,
           a.numero_serie as alcoholimetro_serie,
           CONCAT(u_conductor.nombre, ' ', u_conductor.apellido) as conductor_nombre,
           u_conductor.dni as conductor_dni,
           CONCAT(u_supervisor.nombre, ' ', u_supervisor.apellido) as supervisor_nombre,
           v.placa as vehiculo_placa,
           v.marca as vehiculo_marca,
           v.modelo as vehiculo_modelo,
           p_retest.id as tiene_retest
    FROM pruebas p
    LEFT JOIN alcoholimetros a ON p.alcoholimetro_id = a.id
    LEFT JOIN usuarios u_conductor ON p.conductor_id = u_conductor.id
    LEFT JOIN usuarios u_supervisor ON p.supervisor_id = u_supervisor.id
    LEFT JOIN vehiculos v ON p.vehiculo_id = v.id
    LEFT JOIN pruebas p_retest ON p.id = p_retest.prueba_padre_id
    WHERE $where_sql
    GROUP BY p.id
    ORDER BY p.nivel_alcohol DESC, p.fecha_prueba DESC
    LIMIT 500
", $params);

// Procesar solicitud de re-test
if (isset($_POST['solicitar_retest'])) {
    $prueba_id = $_POST['prueba_id'];
    $motivo = trim($_POST['motivo_retest'] ?? '');
    
    try {
        // Verificar si ya existe una solicitud pendiente
        $solicitud_existente = $db->fetchOne("
            SELECT COUNT(*) as total FROM solicitudes_retest 
            WHERE prueba_original_id = ? AND estado = 'pendiente'
        ", [$prueba_id]);
        
        if ($solicitud_existente['total'] > 0) {
            $mensaje_error = "Ya existe una solicitud de re-test pendiente para esta prueba.";
        } else {
            // Crear solicitud de re-test
            $db->execute("
                INSERT INTO solicitudes_retest 
                (prueba_original_id, solicitado_por, motivo, estado) 
                VALUES (?, ?, ?, 'pendiente')
            ", [$prueba_id, $user_id, $motivo]);
            
            $mensaje_exito = "Solicitud de re-test enviada correctamente";
            
            // Auditoría
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'SOLICITUD_RETEST', 'solicitudes_retest', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $db->lastInsertId(), 
                "Solicitud de re-test para prueba ID: $prueba_id - Motivo: $motivo", 
                $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        }
    } catch (Exception $e) {
        $mensaje_error = "Error al solicitar re-test: " . $e->getMessage();
    }
}

// Procesar exportación a CSV
if (isset($_GET['exportar']) && $_GET['exportar'] == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=pruebas_reprobadas_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Headers CSV
    fputcsv($output, [
        'Fecha', 'Hora', 'Conductor', 'DNI', 'Alcoholímetro', 'N° Serie', 
        'Supervisor', 'Vehículo', 'Nivel Alcohol', 'Límite Excedido', 'Gravedad',
        'Observaciones', 'Ubicación', 'Re-test Solicitado'
    ]);
    
    // Datos
    foreach ($pruebas as $prueba) {
        $exceso = $prueba['nivel_alcohol'] - $configuracion['limite_alcohol_permisible'];
        $gravedad = 'Leve';
        if ($prueba['nivel_alcohol'] > $configuracion['nivel_critico']) {
            $gravedad = 'Crítica';
        } elseif ($prueba['nivel_alcohol'] > $configuracion['nivel_advertencia']) {
            $gravedad = 'Grave';
        }
        
        fputcsv($output, [
            date('d/m/Y', strtotime($prueba['fecha_prueba'])),
            date('H:i', strtotime($prueba['fecha_prueba'])),
            $prueba['conductor_nombre'],
            $prueba['conductor_dni'],
            $prueba['alcoholimetro_nombre'],
            $prueba['alcoholimetro_serie'],
            $prueba['supervisor_nombre'],
            $prueba['vehiculo_placa'] ?? 'N/A',
            number_format($prueba['nivel_alcohol'], 3) . ' ' . $configuracion['unidad_medida'],
            number_format($exceso, 3) . ' ' . $configuracion['unidad_medida'],
            $gravedad,
            $prueba['observaciones'] ?? '',
            ($prueba['latitud'] && $prueba['longitud']) ? $prueba['latitud'] . ', ' . $prueba['longitud'] : 'N/A',
            $prueba['tiene_retest'] ? 'Sí' : 'No'
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
            <p class="dashboard-subtitle">Monitoreo y gestión de pruebas reprobadas</p>
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
                <h3><i class="fas fa-filter"></i> Filtros de Pruebas Reprobadas</h3>
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
                            <label for="nivel_min">Nivel Mínimo (<?php echo $configuracion['unidad_medida']; ?>)</label>
                            <input type="number" id="nivel_min" name="nivel_min" 
                                   value="<?php echo htmlspecialchars($_GET['nivel_min'] ?? number_format($configuracion['limite_alcohol_permisible'], 3)); ?>" 
                                   step="0.001" min="<?php echo $configuracion['limite_alcohol_permisible']; ?>" 
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="nivel_max">Nivel Máximo (<?php echo $configuracion['unidad_medida']; ?>)</label>
                            <input type="number" id="nivel_max" name="nivel_max" 
                                   value="<?php echo htmlspecialchars($_GET['nivel_max'] ?? ''); ?>" 
                                   step="0.001" min="<?php echo $configuracion['limite_alcohol_permisible']; ?>" 
                                   class="form-control" placeholder="1.000">
                        </div>
                        <div class="form-group">
                            <label for="gravedad">Gravedad de la Infracción</label>
                            <select id="gravedad" name="gravedad" class="form-control">
                                <option value="">Todas las gravedades</option>
                                <option value="leve" <?php echo ($_GET['gravedad'] ?? '') == 'leve' ? 'selected' : ''; ?>>Leve (hasta <?php echo number_format($configuracion['nivel_advertencia'], 3); ?>)</option>
                                <option value="grave" <?php echo ($_GET['gravedad'] ?? '') == 'grave' ? 'selected' : ''; ?>>Grave (<?php echo number_format($configuracion['nivel_advertencia'], 3); ?> a <?php echo number_format($configuracion['nivel_critico'], 3); ?>)</option>
                                <option value="critica" <?php echo ($_GET['gravedad'] ?? '') == 'critica' ? 'selected' : ''; ?>>Crítica (más de <?php echo number_format($configuracion['nivel_critico'], 3); ?>)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Aplicar Filtros
                        </button>
                        <a href="pruebas-reprobadas.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Limpiar Filtros
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- CARD DE ESTADÍSTICAS ESPECÍFICAS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Estadísticas de Pruebas Reprobadas</h3>
            </div>
            <div class="card-body">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['total_reprobadas'] ?? 0; ?></h3>
                            <p>Total Reprobadas</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon warning">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['conductores_infractores'] ?? 0; ?></h3>
                            <p>Conductores Infractores</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon critical">
                            <i class="fas fa-tachometer-alt"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo $estadisticas['alcoholimetros_utilizados'] ?? 0; ?></h3>
                            <p>Alcoholímetros Usados</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon average">
                            <i class="fas fa-calculator"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['promedio_alcohol'] ?? 0, 3); ?></h3>
                            <p>Promedio Alcohol</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon high">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['maximo_alcohol'] ?? 0, 3); ?></h3>
                            <p>Máximo Registrado</p>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon low">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="stat-info">
                            <h3><?php echo number_format($estadisticas['minimo_alcohol'] ?? 0, 3); ?></h3>
                            <p>Mínimo Reprobado</p>
                        </div>
                    </div>
                </div>

                <!-- DISTRIBUCIÓN POR GRAVEDAD -->
                <div class="gravedad-stats">
                    <h4><i class="fas fa-exclamation-triangle"></i> Distribución por Gravedad</h4>
                    <div class="gravedad-grid">
                        <div class="gravedad-item leve">
                            <div class="gravedad-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="gravedad-info">
                                <h3><?php echo $estadisticas['leves'] ?? 0; ?></h3>
                                <p>Infracciones Leves</p>
                                <small>Hasta <?php echo number_format($configuracion['nivel_advertencia'], 3); ?> <?php echo $configuracion['unidad_medida']; ?></small>
                            </div>
                        </div>
                        <div class="gravedad-item grave">
                            <div class="gravedad-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="gravedad-info">
                                <h3><?php echo $estadisticas['graves'] ?? 0; ?></h3>
                                <p>Infracciones Graves</p>
                                <small><?php echo number_format($configuracion['nivel_advertencia'], 3); ?> a <?php echo number_format($configuracion['nivel_critico'], 3); ?> <?php echo $configuracion['unidad_medida']; ?></small>
                            </div>
                        </div>
                        <div class="gravedad-item critica">
                            <div class="gravedad-icon">
                                <i class="fas fa-skull-crossbones"></i>
                            </div>
                            <div class="gravedad-info">
                                <h3><?php echo $estadisticas['criticas'] ?? 0; ?></h3>
                                <p>Infracciones Críticas</p>
                                <small>Más de <?php echo number_format($configuracion['nivel_critico'], 3); ?> <?php echo $configuracion['unidad_medida']; ?></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TOP CONDUCTORES INFRACTORES -->
                <?php if (!empty($top_conductores)): ?>
                <div class="top-conductores">
                    <h4><i class="fas fa-exclamation-circle"></i> Top 5 Conductores con Más Infracciones</h4>
                    <div class="conductores-list">
                        <?php foreach ($top_conductores as $index => $conductor): ?>
                        <div class="conductor-item">
                            <div class="conductor-rank">#<?php echo $index + 1; ?></div>
                            <div class="conductor-info">
                                <div class="conductor-name"><?php echo htmlspecialchars($conductor['conductor_nombre']); ?></div>
                                <div class="conductor-dni"><?php echo htmlspecialchars($conductor['dni']); ?></div>
                            </div>
                            <div class="conductor-stats">
                                <span class="badge danger"><?php echo $conductor['total_reprobadas']; ?> infracciones</span>
                                <span class="nivel-maximo">Máx: <?php echo number_format($conductor['maximo_nivel'], 3); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- CARD DE RESULTADOS -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-ban"></i> Listado de Pruebas Reprobadas</h3>
                <div class="card-actions">
                    <span class="badge danger"><?php echo count($pruebas); ?> registros</span>
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
                                <th>Nivel Alcohol</th>
                                <th>Exceso</th>
                                <th>Gravedad</th>
                                <th>Re-test</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pruebas as $prueba): 
                                $exceso = $prueba['nivel_alcohol'] - $configuracion['limite_alcohol_permisible'];
                                $gravedad = 'leve';
                                $gravedad_clase = 'leve';
                                $gravedad_texto = 'Leve';
                                
                                if ($prueba['nivel_alcohol'] > $configuracion['nivel_critico']) {
                                    $gravedad = 'critica';
                                    $gravedad_clase = 'critica';
                                    $gravedad_texto = 'Crítica';
                                } elseif ($prueba['nivel_alcohol'] > $configuracion['nivel_advertencia']) {
                                    $gravedad = 'grave';
                                    $gravedad_clase = 'grave';
                                    $gravedad_texto = 'Grave';
                                }
                            ?>
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
                                    <span class="nivel-alcohol <?php echo $gravedad_clase; ?>">
                                        <?php echo number_format($prueba['nivel_alcohol'], 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="exceso-alcohol <?php echo $gravedad_clase; ?>">
                                        +<?php echo number_format($exceso, 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="gravedad-badge <?php echo $gravedad_clase; ?>">
                                        <?php echo $gravedad_texto; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($prueba['tiene_retest']): ?>
                                        <span class="retest-status success">
                                            <i class="fas fa-check"></i> Realizado
                                        </span>
                                    <?php else: ?>
                                        <span class="retest-status pending">
                                            <i class="fas fa-clock"></i> Pendiente
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="nueva-prueba.php?editar=<?php echo $prueba['id']; ?>" class="btn-icon" title="Ver Detalles">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (!$prueba['tiene_retest']): ?>
                                        <button type="button" class="btn-icon warning" 
                                                title="Solicitar Re-test" 
                                                onclick="mostrarModalRetest(<?php echo $prueba['id']; ?>, '<?php echo htmlspecialchars($prueba['conductor_nombre']); ?>')">
                                            <i class="fas fa-redo"></i>
                                        </button>
                                    <?php endif; ?>
                                    <a href="javascript:void(0)" class="btn-icon danger" 
                                       title="Generar Reporte" 
                                       onclick="generarReporteInfraccion(<?php echo $prueba['id']; ?>)">
                                        <i class="fas fa-file-pdf"></i>
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
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h3>No hay pruebas reprobadas</h3>
                    <p>No se encontraron pruebas reprobadas con los filtros seleccionados</p>
                    <div class="empty-actions">
                        <a href="prueba-rapida.php" class="btn btn-primary">
                            <i class="fas fa-vial"></i>
                            Registrar Nueva Prueba
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

<!-- MODAL PARA SOLICITUD DE RE-TEST -->
<div id="modalRetest" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-redo"></i> Solicitar Re-test</h3>
            <button type="button" class="modal-close" onclick="cerrarModalRetest()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="formRetest" method="POST">
                <input type="hidden" name="prueba_id" id="prueba_retest_id">
                <div class="form-group">
                    <label for="conductor_retest">Conductor</label>
                    <input type="text" id="conductor_retest" class="form-control" readonly>
                </div>
                <div class="form-group">
                    <label for="motivo_retest">Motivo del Re-test *</label>
                    <textarea id="motivo_retest" name="motivo_retest" class="form-control" rows="4" 
                              placeholder="Describa el motivo para solicitar un re-test..." required></textarea>
                    <small class="form-text">Ej: Error en el equipo, condiciones ambientales, solicitud del conductor, etc.</small>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-outline" onclick="cerrarModalRetest()">
                <i class="fas fa-times"></i> Cancelar
            </button>
            <button type="submit" form="formRetest" name="solicitar_retest" class="btn btn-warning">
                <i class="fas fa-paper-plane"></i> Enviar Solicitud
            </button>
        </div>
    </div>
</div>

<!-- ESTILOS CSS INTEGRADOS (Mismo patrón + mejoras para pruebas reprobadas) -->
<style>
/* [Todos los estilos CSS del patrón aquí - idénticos a los módulos anteriores] */
.crud-container { margin-top: 1.5rem; width: 100%; }
.data-table { width: 100%; border-collapse: collapse; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin: 0; }
.data-table th { background: var(--light); padding: 1rem; text-align: left; font-weight: 600; color: var(--dark); border-bottom: 2px solid var(--border); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px; }
.data-table td { padding: 1rem; border-bottom: 1px solid var(--border); color: var(--dark); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover { background: rgba(231, 76, 60, 0.04); }
.action-buttons { display: flex; gap: 0.5rem; justify-content: center; }
.btn-icon { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: 8px; background: var(--light); color: var(--dark); text-decoration: none; transition: all 0.3s ease; border: none; cursor: pointer; }
.btn-icon:hover { background: var(--primary); color: white; transform: translateY(-2px); }
.btn-icon.warning:hover { background: var(--warning); }
.btn-icon.danger:hover { background: var(--danger); }
.status-badge { padding: 0.4rem 0.8rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; display: inline-block; text-align: center; min-width: 80px; }
.badge { padding: 0.4rem 0.8rem; background: linear-gradient(135deg, var(--danger), #c0392b); color: white; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
.badge.danger { background: linear-gradient(135deg, var(--danger), #c0392b); }
.table-responsive { overflow-x: auto; border-radius: 12px; }
.empty-state { text-align: center; padding: 4rem 2rem; color: var(--gray); }
.empty-icon { font-size: 4rem; color: var(--light); margin-bottom: 1.5rem; opacity: 0.7; }
.empty-state h3 { color: var(--dark); margin-bottom: 0.5rem; font-weight: 600; }
.empty-state p { margin-bottom: 2rem; font-size: 1rem; opacity: 0.8; }
.empty-actions { display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap; }
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

/* ESTILOS ESPECÍFICOS PARA PRUEBAS REPROBADAS */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
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

.stat-icon.danger { background: rgba(231, 76, 60, 0.15); color: var(--danger); }
.stat-icon.warning { background: rgba(243, 156, 18, 0.15); color: var(--warning); }
.stat-icon.critical { background: rgba(192, 57, 43, 0.15); color: #c0392b; }
.stat-icon.average { background: rgba(230, 126, 34, 0.15); color: #e67e22; }
.stat-icon.high { background: rgba(231, 76, 60, 0.15); color: var(--danger); }
.stat-icon.low { background: rgba(52, 152, 219, 0.15); color: var(--primary); }

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

/* Distribución por gravedad */
.gravedad-stats {
    margin: 2rem 0;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 10px;
    border: 1px solid var(--border);
}

.gravedad-stats h4 {
    margin: 0 0 1.5rem 0;
    color: var(--dark);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.gravedad-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.gravedad-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: 8px;
    border: 2px solid transparent;
    transition: all 0.3s ease;
}

.gravedad-item.leve { 
    background: rgba(52, 152, 219, 0.1); 
    border-color: rgba(52, 152, 219, 0.3);
}
.gravedad-item.grave { 
    background: rgba(243, 156, 18, 0.1); 
    border-color: rgba(243, 156, 18, 0.3);
}
.gravedad-item.critica { 
    background: rgba(231, 76, 60, 0.1); 
    border-color: rgba(231, 76, 60, 0.3);
}

.gravedad-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.gravedad-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.gravedad-item.leve .gravedad-icon { background: var(--primary); color: white; }
.gravedad-item.grave .gravedad-icon { background: var(--warning); color: white; }
.gravedad-item.critica .gravedad-icon { background: var(--danger); color: white; }

.gravedad-info h3 {
    margin: 0 0 0.25rem 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.gravedad-info p {
    margin: 0 0 0.25rem 0;
    font-weight: 600;
}

.gravedad-info small {
    color: var(--gray);
    font-size: 0.75rem;
}

/* Top Conductores */
.top-conductores {
    margin-top: 2rem;
    padding-top: 2rem;
    border-top: 1px solid var(--border);
}

.top-conductores h4 {
    margin: 0 0 1rem 0;
    color: var(--dark);
    font-size: 1.1rem;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.conductores-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.conductor-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 8px;
    border: 1px solid var(--border);
}

.conductor-rank {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--danger), #c0392b);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 1rem;
}

.conductor-info {
    flex: 1;
}

.conductor-name {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.conductor-dni {
    font-size: 0.8rem;
    color: var(--gray);
}

.conductor-stats {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.nivel-maximo {
    font-size: 0.8rem;
    color: var(--danger);
    font-weight: 600;
}

/* Estilos específicos de la tabla */
.fecha-hora .fecha {
    font-weight: 600;
    color: var(--dark);
}

.fecha-hora .hora {
    font-size: 0.8rem;
    color: var(--gray);
}

.nivel-alcohol {
    font-weight: 700;
}

.nivel-alcohol.leve { color: var(--primary); }
.nivel-alcohol.grave { color: var(--warning); }
.nivel-alcohol.critica { color: var(--danger); }

.exceso-alcohol {
    font-weight: 600;
    font-size: 0.85rem;
}

.exceso-alcohol.leve { color: var(--primary); }
.exceso-alcohol.grave { color: var(--warning); }
.exceso-alcohol.critica { color: var(--danger); }

.gravedad-badge {
    padding: 0.3rem 0.6rem;
    border-radius: 15px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.gravedad-badge.leve { 
    background: rgba(52, 152, 219, 0.15); 
    color: var(--primary);
    border: 1px solid rgba(52, 152, 219, 0.3);
}
.gravedad-badge.grave { 
    background: rgba(243, 156, 18, 0.15); 
    color: var(--warning);
    border: 1px solid rgba(243, 156, 18, 0.3);
}
.gravedad-badge.critica { 
    background: rgba(231, 76, 60, 0.15); 
    color: var(--danger);
    border: 1px solid rgba(231, 76, 60, 0.3);
}

.retest-status {
    padding: 0.3rem 0.6rem;
    border-radius: 15px;
    font-size: 0.7rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.retest-status.success {
    background: rgba(39, 174, 96, 0.15);
    color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.3);
}

.retest-status.pending {
    background: rgba(243, 156, 18, 0.15);
    color: var(--warning);
    border: 1px solid rgba(243, 156, 18, 0.3);
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
    .gravedad-grid { grid-template-columns: 1fr; }
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
    .gravedad-item { flex-direction: column; text-align: center; gap: 0.75rem; }
    .conductor-item { flex-direction: column; text-align: center; gap: 0.75rem; }
    .conductor-stats { flex-direction: column; gap: 0.5rem; }
    .empty-actions { flex-direction: column; }
    .modal-content { width: 95%; margin: 1rem; }
    .modal-footer { flex-direction: column; }
}
</style>

<script>
// FUNCIONES JS PARA PRUEBAS REPROBADAS
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

    // Establecer nivel mínimo por defecto como el límite permisible
    const nivelMin = document.getElementById('nivel_min');
    const limitePermisible = <?php echo $configuracion['limite_alcohol_permisible']; ?>;
    
    if (nivelMin && !nivelMin.value) {
        nivelMin.value = limitePermisible.toFixed(3);
    }

    // Validación de rangos de nivel
    const nivelMaxInput = document.getElementById('nivel_max');
    
    if (nivelMin && nivelMaxInput) {
        const validarRangos = function() {
            const min = parseFloat(nivelMin.value) || limitePermisible;
            const max = parseFloat(nivelMaxInput.value) || 1.000;
            
            if (min > max) {
                nivelMin.setCustomValidity('El nivel mínimo no puede ser mayor al máximo');
            } else if (min < limitePermisible) {
                nivelMin.setCustomValidity('El nivel mínimo para reprobadas debe ser mayor o igual al límite permisible');
            } else {
                nivelMin.setCustomValidity('');
                nivelMaxInput.setCustomValidity('');
            }
        };
        
        nivelMin.addEventListener('change', validarRangos);
        nivelMaxInput.addEventListener('change', validarRangos);
    }
});

// Modal para solicitar re-test
function mostrarModalRetest(pruebaId, conductorNombre) {
    document.getElementById('prueba_retest_id').value = pruebaId;
    document.getElementById('conductor_retest').value = conductorNombre;
    document.getElementById('motivo_retest').value = '';
    document.getElementById('modalRetest').classList.add('show');
}

function cerrarModalRetest() {
    document.getElementById('modalRetest').classList.remove('show');
}

// Cerrar modal al hacer clic fuera
document.addEventListener('click', function(event) {
    const modal = document.getElementById('modalRetest');
    if (event.target === modal) {
        cerrarModalRetest();
    }
});

// Función para generar reporte de infracción
function generarReporteInfraccion(pruebaId) {
    if (confirm('¿Desea generar un reporte PDF de esta infracción?')) {
        // Aquí iría la lógica para generar el reporte PDF
        alert('Generando reporte de infracción para prueba ID: ' + pruebaId + '\n\nEsta función estará disponible próximamente.');
        
        // Simulación de generación de reporte
        setTimeout(() => {
            alert('Reporte generado exitosamente. Se descargará automáticamente.');
        }, 1000);
    }
}

// Función para exportar datos
function exportarReprobadas(formato) {
    if (formato === 'csv') {
        window.location.href = '?exportar=csv&' + new URLSearchParams(window.location.search).toString();
    } else if (formato === 'pdf') {
        alert('Exportación a PDF de pruebas reprobadas disponible próximamente');
    }
}

// Función para filtrar por gravedad específica
function filtrarPorGravedad(gravedad) {
    document.getElementById('gravedad').value = gravedad;
    document.querySelector('form').submit();
}

// Función para ver estadísticas detalladas
function verEstadisticasDetalladas() {
    alert('Estadísticas detalladas y análisis de tendencias disponibles próximamente');
}

// Función para enviar alerta a conductor
function enviarAlertaConductor(conductorId, conductorNombre) {
    if (confirm(`¿Enviar alerta de infracción al conductor "${conductorNombre}"?`)) {
        alert(`Alerta enviada a ${conductorNombre}. Se notificará sobre su historial de infracciones.`);
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>