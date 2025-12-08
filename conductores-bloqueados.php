<?php
// conductores-bloqueados.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Conductores Bloqueados';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'conductores-bloqueados.php' => 'Conductores Bloqueados'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// OBTENER CONFIGURACIÓN DE BLOQUEO
$configuracion = $db->fetchOne("
    SELECT bloqueo_conductor_horas, limite_alcohol_permisible
    FROM configuraciones 
    WHERE cliente_id = ?
", [$cliente_id]);

$horas_bloqueo = $configuracion['bloqueo_conductor_horas'] ?? 24;
$limite_alcohol = $configuracion['limite_alcohol_permisible'] ?? 0.000;

// VARIABLES PARA FILTROS
$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_GET['fecha_fin'] ?? date('Y-m-d');
$dni_filtro = $_GET['dni'] ?? '';

// OBTENER CONDUCTORES BLOQUEADOS
$params = [$cliente_id];
$where_conditions = ["u.cliente_id = ?", "u.rol = 'conductor'"];

// Filtrar por estado bloqueado (estado = 0 O con pruebas reprobadas recientes)
$where_conditions[] = "(u.estado = 0 OR EXISTS (
    SELECT 1 FROM pruebas p 
    WHERE p.conductor_id = u.id 
    AND p.resultado = 'reprobado' 
    AND p.fecha_prueba >= DATE_SUB(NOW(), INTERVAL ? HOUR)
))";
$params[] = $horas_bloqueo;

if ($fecha_inicio) {
    $where_conditions[] = "DATE(u.fecha_actualizacion) >= ?";
    $params[] = $fecha_inicio;
}

if ($fecha_fin) {
    $where_conditions[] = "DATE(u.fecha_actualizacion) <= ?";
    $params[] = $fecha_fin;
}

if ($dni_filtro) {
    $where_conditions[] = "u.dni LIKE ?";
    $params[] = "%$dni_filtro%";
}

$where_sql = implode(" AND ", $where_conditions);

// CONDUCTORES BLOQUEADOS
$conductores_bloqueados = $db->fetchAll("
    SELECT 
        u.id,
        u.nombre,
        u.apellido,
        u.dni,
        u.email,
        u.telefono,
        u.estado,
        u.ultimo_login,
        u.fecha_creacion,
        u.fecha_actualizacion,
        -- Última prueba reprobada
        (SELECT p.fecha_prueba 
         FROM pruebas p 
         WHERE p.conductor_id = u.id 
         AND p.resultado = 'reprobado' 
         ORDER BY p.fecha_prueba DESC 
         LIMIT 1) as ultima_prueba_reprobada,
        -- Nivel de alcohol de la última prueba reprobada
        (SELECT p.nivel_alcohol 
         FROM pruebas p 
         WHERE p.conductor_id = u.id 
         AND p.resultado = 'reprobado' 
         ORDER BY p.fecha_prueba DESC 
         LIMIT 1) as ultimo_nivel_alcohol,
        -- Total de pruebas reprobadas en período de bloqueo
        (SELECT COUNT(*) 
         FROM pruebas p 
         WHERE p.conductor_id = u.id 
         AND p.resultado = 'reprobado' 
         AND p.fecha_prueba >= DATE_SUB(NOW(), INTERVAL ? HOUR)
        ) as pruebas_reprobadas_recientes,
        -- Tiempo restante de bloqueo
        CASE 
            WHEN u.estado = 0 THEN NULL
            ELSE TIMESTAMPDIFF(HOUR, 
                (SELECT MAX(p.fecha_prueba) 
                 FROM pruebas p 
                 WHERE p.conductor_id = u.id 
                 AND p.resultado = 'reprobado'),
                NOW()
            )
        END as horas_desde_ultima_reprobada
    FROM usuarios u
    WHERE {$where_sql}
    ORDER BY u.fecha_actualizacion DESC
", array_merge($params, [$horas_bloqueo]));

// ESTADÍSTICAS DE CONDUCTORES BLOQUEADOS
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_bloqueados,
        SUM(CASE WHEN u.estado = 0 THEN 1 ELSE 0 END) as bloqueados_permanentes,
        SUM(CASE WHEN u.estado = 1 THEN 1 ELSE 0 END) as bloqueados_temporales,
        AVG(ultimo_nivel_alcohol) as promedio_nivel_alcohol,
        MAX(ultimo_nivel_alcohol) as maximo_nivel_alcohol,
        COUNT(DISTINCT DATE(u.fecha_actualizacion)) as dias_con_bloqueos,
        SUM(pruebas_reprobadas_recientes) as total_pruebas_reprobadas
    FROM (
        SELECT 
            u.id,
            u.estado,
            u.fecha_actualizacion,
            (SELECT p.nivel_alcohol 
             FROM pruebas p 
             WHERE p.conductor_id = u.id 
             AND p.resultado = 'reprobado' 
             ORDER BY p.fecha_prueba DESC 
             LIMIT 1) as ultimo_nivel_alcohol,
            (SELECT COUNT(*) 
             FROM pruebas p 
             WHERE p.conductor_id = u.id 
             AND p.resultado = 'reprobado' 
             AND p.fecha_prueba >= DATE_SUB(NOW(), INTERVAL ? HOUR)
            ) as pruebas_reprobadas_recientes
        FROM usuarios u
        WHERE {$where_sql}
    ) as subquery
", array_merge([$horas_bloqueo], $params));

// PROCESAR REACTIVACIÓN DE CONDUCTOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reactivar_conductor'])) {
    $conductor_id = $_POST['conductor_id'] ?? null;
    
    if ($conductor_id) {
        try {
            // Verificar que el conductor pertenece al cliente
            $conductor = $db->fetchOne("
                SELECT nombre, apellido, dni, estado 
                FROM usuarios 
                WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
            ", [$conductor_id, $cliente_id]);

            if (!$conductor) {
                throw new Exception("Conductor no encontrado");
            }

            // Reactivar conductor
            $db->execute("
                UPDATE usuarios 
                SET estado = 1, fecha_actualizacion = NOW()
                WHERE id = ? AND cliente_id = ? AND rol = 'conductor'
            ", [$conductor_id, $cliente_id]);

            // AUDITORÍA
            $detalles = "Conductor reactivado: {$conductor['nombre']} {$conductor['apellido']} - DNI: {$conductor['dni']}";
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'REACTIVAR_CONDUCTOR', 'usuarios', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $conductor_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

            $mensaje_exito = "Conductor reactivado correctamente";
            
            // Recargar datos
            header("Location: conductores-bloqueados.php?reactivado=1");
            exit;

        } catch (Exception $e) {
            $mensaje_error = "Error al reactivar el conductor: " . $e->getMessage();
        }
    }
}

// Mostrar mensaje de éxito si se reactivó
if (isset($_GET['reactivado'])) {
    $mensaje_exito = "Conductor reactivado correctamente";
}
?>

<div class="content-body">
    <!-- HEADER -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona conductores bloqueados por pruebas de alcohol reprobadas</p>
        </div>
        <div class="header-actions">
            <a href="registrar-conductor.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i>Volver a Conductores
            </a>
            <a href="reportes.php?tipo=bloqueados" class="btn btn-primary">
                <i class="fas fa-chart-bar"></i>Reporte Detallado
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

    <!-- FILTROS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filtros de Búsqueda</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="dni" class="form-label">DNI</label>
                        <input type="text" id="dni" name="dni" class="form-control" 
                               placeholder="Buscar por DNI"
                               value="<?php echo htmlspecialchars($dni_filtro); ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_inicio" class="form-label">Fecha Inicio Bloqueo</label>
                        <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" 
                               value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                    </div>
                    <div class="form-group">
                        <label for="fecha_fin" class="form-label">Fecha Fin Bloqueo</label>
                        <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" 
                               value="<?php echo htmlspecialchars($fecha_fin); ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Configuración Actual</label>
                        <div class="config-info">
                            <small>Bloqueo: <?php echo $horas_bloqueo; ?> horas</small><br>
                            <small>Límite: <?php echo number_format($limite_alcohol, 3); ?> g/L</small>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                    <a href="conductores-bloqueados.php" class="btn btn-outline">
                        <i class="fas fa-undo"></i> Limpiar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- ESTADÍSTICAS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Estadísticas de Bloqueos</h3>
        </div>
        <div class="card-body">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon danger">
                        <i class="fas fa-user-slash"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['total_bloqueados'] ?? 0; ?></h3>
                        <p>Total Bloqueados</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon warning">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['bloqueados_temporales'] ?? 0; ?></h3>
                        <p>Bloqueo Temporal</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-ban"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['bloqueados_permanentes'] ?? 0; ?></h3>
                        <p>Bloqueo Permanente</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-vial"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $estadisticas['total_pruebas_reprobadas'] ?? 0; ?></h3>
                        <p>Pruebas Reprobadas</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($estadisticas['promedio_nivel_alcohol'] ?? 0, 3); ?></h3>
                        <p>Promedio Alcohol (g/L)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon average">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo number_format($estadisticas['maximo_nivel_alcohol'] ?? 0, 3); ?></h3>
                        <p>Máximo Alcohol (g/L)</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- LISTA DE CONDUCTORES BLOQUEADOS -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-slash"></i> Conductores Bloqueados</h3>
            <div class="card-actions">
                <span class="badge danger"><?php echo count($conductores_bloqueados); ?> bloqueados</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (!empty($conductores_bloqueados)): ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Conductor</th>
                            <th>DNI</th>
                            <th>Última Prueba</th>
                            <th>Nivel Alcohol</th>
                            <th>Tipo Bloqueo</th>
                            <th>Tiempo Restante</th>
                            <th>Pruebas Recientes</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conductores_bloqueados as $conductor): ?>
                        <?php
                            $es_bloqueo_permanente = $conductor['estado'] == 0;
                            $horas_restantes = $horas_bloqueo - ($conductor['horas_desde_ultima_reprobada'] ?? 0);
                            $puede_reactivar = $es_bloqueo_permanente || $horas_restantes <= 0;
                        ?>
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
                                <?php if ($conductor['ultima_prueba_reprobada']): ?>
                                <span class="fecha-hora">
                                    <?php echo date('d/m/Y H:i', strtotime($conductor['ultima_prueba_reprobada'])); ?>
                                </span>
                                <?php else: ?>
                                <span class="text-muted">No disponible</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($conductor['ultimo_nivel_alcohol'] !== null): ?>
                                <span class="nivel-alcohol text-danger">
                                    <strong><?php echo number_format($conductor['ultimo_nivel_alcohol'], 3); ?> g/L</strong>
                                </span>
                                <div class="nivel-comparacion">
                                    <small>Límite: <?php echo number_format($limite_alcohol, 3); ?> g/L</small>
                                </div>
                                <?php else: ?>
                                <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($es_bloqueo_permanente): ?>
                                <span class="status-badge estado-inactivo">
                                    <i class="fas fa-ban"></i> Permanente
                                </span>
                                <?php else: ?>
                                <span class="status-badge estado-bloqueado-temporal">
                                    <i class="fas fa-clock"></i> Temporal
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$es_bloqueo_permanente && $horas_restantes > 0): ?>
                                <div class="tiempo-bloqueo">
                                    <span class="tiempo-restante text-warning">
                                        <strong><?php echo $horas_restantes; ?>h</strong>
                                    </span>
                                    <div class="progreso-bloqueo">
                                        <div class="barra-progreso">
                                            <div class="progreso" style="width: <?php echo (($horas_bloqueo - $horas_restantes) / $horas_bloqueo) * 100; ?>%"></div>
                                        </div>
                                        <small><?php echo $horas_bloqueo - $horas_restantes; ?>h / <?php echo $horas_bloqueo; ?>h</small>
                                    </div>
                                </div>
                                <?php elseif (!$es_bloqueo_permanente): ?>
                                <span class="text-success">
                                    <i class="fas fa-check"></i> Listo para reactivar
                                </span>
                                <?php else: ?>
                                <span class="text-muted">Indefinido</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="pruebas-recientes">
                                    <span class="cantidad-pruebas <?php echo $conductor['pruebas_reprobadas_recientes'] > 1 ? 'text-danger' : 'text-warning'; ?>">
                                        <strong><?php echo $conductor['pruebas_reprobadas_recientes']; ?></strong>
                                    </span>
                                    <small>en <?php echo $horas_bloqueo; ?>h</small>
                                </div>
                            </td>
                            <td class="action-buttons">
                                <a href="historial-conductor.php?conductor_id=<?php echo $conductor['id']; ?>" 
                                   class="btn-icon info" title="Ver Historial">
                                    <i class="fas fa-history"></i>
                                </a>
                                <?php if ($puede_reactivar): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="conductor_id" value="<?php echo $conductor['id']; ?>">
                                    <button type="submit" name="reactivar_conductor" class="btn-icon success" 
                                            title="Reactivar Conductor"
                                            onclick="return confirm('¿Está seguro de reactivar a <?php echo htmlspecialchars($conductor['nombre'] . ' ' . $conductor['apellido']); ?>?')">
                                        <i class="fas fa-user-check"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                <button type="button" class="btn-icon secondary" disabled
                                        title="Bloqueo activo - <?php echo $horas_restantes; ?> horas restantes">
                                    <i class="fas fa-clock"></i>
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
                    <i class="fas fa-user-check"></i>
                </div>
                <h3>No hay conductores bloqueados</h3>
                <p>No se encontraron conductores bloqueados con los filtros aplicados</p>
                <div class="empty-actions">
                    <a href="conductores-bloqueados.php" class="btn btn-primary">
                        <i class="fas fa-refresh"></i>Ver Todos los Conductores
                    </a>
                </div>
            </div>
            <?php endif; ?>
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
document.addEventListener('DOMContentLoaded', function() {
    console.log('Página de conductores bloqueados cargada');
    
    // Validar fechas en el formulario
    const formFiltros = document.querySelector('.filter-form');
    if (formFiltros) {
        formFiltros.addEventListener('submit', function(e) {
            const fechaInicio = document.getElementById('fecha_inicio').value;
            const fechaFin = document.getElementById('fecha_fin').value;
            
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

    // Actualizar automáticamente el tiempo restante cada minuto
    setInterval(() => {
        const tiempoElements = document.querySelectorAll('.tiempo-restante');
        tiempoElements.forEach(element => {
            const horasText = element.textContent;
            const horas = parseInt(horasText);
            if (!isNaN(horas) && horas > 0) {
                element.textContent = (horas - 1) + 'h';
            }
        });
    }, 60000); // Actualizar cada minuto
});

// Función para reactivar conductor con confirmación
function confirmarReactivacion(nombre) {
    return confirm(`¿Está seguro de reactivar a ${nombre}?\n\nEl conductor podrá realizar pruebas nuevamente.`);
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>