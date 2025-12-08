<?php
// estados-alcoholimetros.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Estados de Alcoholímetros';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'estados-alcoholimetros.php' => 'Estados'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// Obtener parámetros de filtro
$filtro_estado = $_GET['estado'] ?? 'todos';
$filtro_calibracion = $_GET['calibracion'] ?? 'todos';

// Construir consulta base con filtros
$where_conditions = ["cliente_id = ?"];
$params = [$cliente_id];

// Aplicar filtro de estado
if ($filtro_estado !== 'todos') {
    $where_conditions[] = "estado = ?";
    $params[] = $filtro_estado;
}

// Aplicar filtro de calibración
if ($filtro_calibracion !== 'todos') {
    switch ($filtro_calibracion) {
        case 'vencida':
            $where_conditions[] = "proxima_calibracion < CURDATE()";
            break;
        case 'proxima':
            $where_conditions[] = "DATEDIFF(proxima_calibracion, CURDATE()) BETWEEN 1 AND 30";
            break;
        case 'vigente':
            $where_conditions[] = "proxima_calibracion >= CURDATE() AND DATEDIFF(proxima_calibracion, CURDATE()) > 30";
            break;
        case 'no_programada':
            $where_conditions[] = "proxima_calibracion IS NULL";
            break;
    }
}

$where_clause = implode(" AND ", $where_conditions);

// Obtener alcoholímetros con filtros aplicados
$alcoholimetros = $db->fetchAll("
    SELECT *, 
           DATEDIFF(proxima_calibracion, CURDATE()) as dias_restantes,
           CASE 
               WHEN proxima_calibracion IS NULL THEN 'no_programada'
               WHEN proxima_calibracion < CURDATE() THEN 'vencida'
               WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 30 THEN 'proxima'
               ELSE 'vigente'
           END as estado_calibracion,
           CASE 
               WHEN estado = 'activo' AND proxima_calibracion >= CURDATE() THEN 'operativo'
               WHEN estado = 'activo' AND proxima_calibracion < CURDATE() THEN 'alerta'
               WHEN estado = 'mantenimiento' THEN 'mantenimiento'
               WHEN estado = 'calibracion' THEN 'calibracion'
               ELSE 'inactivo'
           END as estado_operativo
    FROM alcoholimetros 
    WHERE $where_clause
    ORDER BY 
        CASE estado
            WHEN 'activo' THEN 1
            WHEN 'mantenimiento' THEN 2
            WHEN 'calibracion' THEN 3
            WHEN 'inactivo' THEN 4
        END,
        fecha_actualizacion DESC
", $params);

// Estadísticas por estado
$estadisticas_estado = $db->fetchAll("
    SELECT 
        estado,
        COUNT(*) as total,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM alcoholimetros WHERE cliente_id = ?), 1) as porcentaje
    FROM alcoholimetros 
    WHERE cliente_id = ?
    GROUP BY estado
    ORDER BY 
        CASE estado
            WHEN 'activo' THEN 1
            WHEN 'mantenimiento' THEN 2
            WHEN 'calibracion' THEN 3
            WHEN 'inactivo' THEN 4
        END
", [$cliente_id, $cliente_id]);

// Estadísticas por calibración
$estadisticas_calibracion = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN proxima_calibracion < CURDATE() THEN 1 ELSE 0 END) as vencidas,
        SUM(CASE WHEN DATEDIFF(proxima_calibracion, CURDATE()) BETWEEN 1 AND 30 THEN 1 ELSE 0 END) as proximas,
        SUM(CASE WHEN proxima_calibracion >= CURDATE() AND DATEDIFF(proxima_calibracion, CURDATE()) > 30 THEN 1 ELSE 0 END) as vigentes,
        SUM(CASE WHEN proxima_calibracion IS NULL THEN 1 ELSE 0 END) as no_programadas
    FROM alcoholimetros 
    WHERE cliente_id = ?
", [$cliente_id]);

// Procesar cambio de estado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    $alcoholimetro_id = $_POST['alcoholimetro_id'];
    $nuevo_estado = $_POST['nuevo_estado'];
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    try {
        // Obtener estado anterior
        $estado_anterior = $db->fetchOne("
            SELECT estado FROM alcoholimetros 
            WHERE id = ? AND cliente_id = ?
        ", [$alcoholimetro_id, $cliente_id]);
        
        if ($estado_anterior) {
            // Actualizar estado
            $db->execute("
                UPDATE alcoholimetros 
                SET estado = ?, fecha_actualizacion = NOW()
                WHERE id = ? AND cliente_id = ?
            ", [$nuevo_estado, $alcoholimetro_id, $cliente_id]);
            
            $mensaje_exito = "Estado del alcoholímetro actualizado correctamente";
            
            // Registrar en auditoría
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'CAMBIAR_ESTADO', 'alcoholimetros', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $alcoholimetro_id, 
                "Cambio de estado: {$estado_anterior['estado']} → $nuevo_estado. Observaciones: $observaciones", 
                $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            // Recargar datos
            $alcoholimetros = $db->fetchAll("
                SELECT *, 
                       DATEDIFF(proxima_calibracion, CURDATE()) as dias_restantes,
                       CASE 
                           WHEN proxima_calibracion IS NULL THEN 'no_programada'
                           WHEN proxima_calibracion < CURDATE() THEN 'vencida'
                           WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 30 THEN 'proxima'
                           ELSE 'vigente'
                       END as estado_calibracion,
                       CASE 
                           WHEN estado = 'activo' AND proxima_calibracion >= CURDATE() THEN 'operativo'
                           WHEN estado = 'activo' AND proxima_calibracion < CURDATE() THEN 'alerta'
                           WHEN estado = 'mantenimiento' THEN 'mantenimiento'
                           WHEN estado = 'calibracion' THEN 'calibracion'
                           ELSE 'inactivo'
                       END as estado_operativo
                FROM alcoholimetros 
                WHERE $where_clause
                ORDER BY 
                    CASE estado
                        WHEN 'activo' THEN 1
                        WHEN 'mantenimiento' THEN 2
                        WHEN 'calibracion' THEN 3
                        WHEN 'inactivo' THEN 4
                    END,
                    fecha_actualizacion DESC
            ", $params);
            
            // Actualizar estadísticas
            $estadisticas_estado = $db->fetchAll("
                SELECT 
                    estado,
                    COUNT(*) as total,
                    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM alcoholimetros WHERE cliente_id = ?), 1) as porcentaje
                FROM alcoholimetros 
                WHERE cliente_id = ?
                GROUP BY estado
                ORDER BY 
                    CASE estado
                        WHEN 'activo' THEN 1
                        WHEN 'mantenimiento' THEN 2
                        WHEN 'calibracion' THEN 3
                        WHEN 'inactivo' THEN 4
                    END
            ", [$cliente_id, $cliente_id]);
        }
        
    } catch (Exception $e) {
        $mensaje_error = "Error al cambiar el estado: " . $e->getMessage();
    }
}
?>

<div class="content-body">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Monitoreo y gestión del estado operativo de los alcoholímetros</p>
        </div>
        <div class="header-actions">
            <a href="registrar-alcoholimetro.php" class="btn btn-primary">
                <i class="fas fa-plus"></i>
                Nuevo Alcoholímetro
            </a>
        </div>
    </div>

    <!-- Alertas -->
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

    <!-- Panel de Estadísticas -->
    <div class="stats-grid">
        <?php foreach ($estadisticas_estado as $estadistica): 
            $estado = $estadistica['estado'];
            $total = $estadistica['total'];
            $porcentaje = $estadistica['porcentaje'];
            
            // Configuración por estado
            switch ($estado) {
                case 'activo':
                    $clase = 'success';
                    $icono = 'fa-play-circle';
                    $texto = 'Activos';
                    break;
                case 'mantenimiento':
                    $clase = 'warning';
                    $icono = 'fa-tools';
                    $texto = 'Mantenimiento';
                    break;
                case 'calibracion':
                    $clase = 'info';
                    $icono = 'fa-sync-alt';
                    $texto = 'En Calibración';
                    break;
                case 'inactivo':
                    $clase = 'secondary';
                    $icono = 'fa-pause-circle';
                    $texto = 'Inactivos';
                    break;
                default:
                    $clase = 'primary';
                    $icono = 'fa-question-circle';
                    $texto = ucfirst($estado);
            }
        ?>
        <div class="stat-card">
            <div class="stat-icon <?php echo $clase; ?>">
                <i class="fas <?php echo $icono; ?>"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $total; ?></h3>
                <p><?php echo $texto; ?></p>
                <div class="stat-percentage">
                    <span class="percentage <?php echo $clase; ?>"><?php echo $porcentaje; ?>%</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filtros -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-filter"></i> Filtros</h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="filtro_estado">Estado Operativo</label>
                        <select id="filtro_estado" name="estado" class="form-control" onchange="this.form.submit()">
                            <option value="todos" <?php echo $filtro_estado === 'todos' ? 'selected' : ''; ?>>Todos los Estados</option>
                            <option value="activo" <?php echo $filtro_estado === 'activo' ? 'selected' : ''; ?>>Activo</option>
                            <option value="inactivo" <?php echo $filtro_estado === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                            <option value="mantenimiento" <?php echo $filtro_estado === 'mantenimiento' ? 'selected' : ''; ?>>Mantenimiento</option>
                            <option value="calibracion" <?php echo $filtro_estado === 'calibracion' ? 'selected' : ''; ?>>En Calibración</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filtro_calibracion">Estado de Calibración</label>
                        <select id="filtro_calibracion" name="calibracion" class="form-control" onchange="this.form.submit()">
                            <option value="todos" <?php echo $filtro_calibracion === 'todos' ? 'selected' : ''; ?>>Todas las Calibraciones</option>
                            <option value="vencida" <?php echo $filtro_calibracion === 'vencida' ? 'selected' : ''; ?>>Calibración Vencida</option>
                            <option value="proxima" <?php echo $filtro_calibracion === 'proxima' ? 'selected' : ''; ?>>Próxima a Vencer (30 días)</option>
                            <option value="vigente" <?php echo $filtro_calibracion === 'vigente' ? 'selected' : ''; ?>>Calibración Vigente</option>
                            <option value="no_programada" <?php echo $filtro_calibracion === 'no_programada' ? 'selected' : ''; ?>>No Programada</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <a href="estados-alcoholimetros.php" class="btn btn-outline" style="margin-top: 1.75rem;">
                            <i class="fas fa-times"></i>
                            Limpiar Filtros
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="crud-container">
        <!-- Lista de Alcoholímetros -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Lista de Alcoholímetros</h3>
                <div class="card-actions">
                    <span class="badge"><?php echo count($alcoholimetros); ?> equipos</span>
                    <span class="badge filter-indicator">
                        <?php if ($filtro_estado !== 'todos' || $filtro_calibracion !== 'todos'): ?>
                        <i class="fas fa-filter"></i> Filtros activos
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($alcoholimetros)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>N° Serie</th>
                                <th>Nombre Activo</th>
                                <th>Modelo/Marca</th>
                                <th>Estado Operativo</th>
                                <th>Calibración</th>
                                <th>Última Actualización</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alcoholimetros as $alcoholimetro): 
                                $estado = $alcoholimetro['estado'];
                                $estado_calibracion = $alcoholimetro['estado_calibracion'];
                                $estado_operativo = $alcoholimetro['estado_operativo'];
                                
                                // Configuración estado operativo
                                switch ($estado) {
                                    case 'activo':
                                        $clase_estado = 'success';
                                        $texto_estado = 'Activo';
                                        $icono_estado = 'fa-play-circle';
                                        break;
                                    case 'mantenimiento':
                                        $clase_estado = 'warning';
                                        $texto_estado = 'Mantenimiento';
                                        $icono_estado = 'fa-tools';
                                        break;
                                    case 'calibracion':
                                        $clase_estado = 'info';
                                        $texto_estado = 'En Calibración';
                                        $icono_estado = 'fa-sync-alt';
                                        break;
                                    case 'inactivo':
                                        $clase_estado = 'secondary';
                                        $texto_estado = 'Inactivo';
                                        $icono_estado = 'fa-pause-circle';
                                        break;
                                }
                                
                                // Configuración estado calibración
                                switch ($estado_calibracion) {
                                    case 'vencida':
                                        $clase_calibracion = 'danger';
                                        $texto_calibracion = 'Vencida';
                                        $icono_calibracion = 'fa-exclamation-triangle';
                                        break;
                                    case 'proxima':
                                        $clase_calibracion = 'warning';
                                        $texto_calibracion = 'Próxima';
                                        $icono_calibracion = 'fa-clock';
                                        break;
                                    case 'vigente':
                                        $clase_calibracion = 'success';
                                        $texto_calibracion = 'Vigente';
                                        $icono_calibracion = 'fa-check-circle';
                                        break;
                                    case 'no_programada':
                                        $clase_calibracion = 'secondary';
                                        $texto_calibracion = 'No Programada';
                                        $icono_calibracion = 'fa-question-circle';
                                        break;
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($alcoholimetro['numero_serie']); ?></td>
                                <td><?php echo htmlspecialchars($alcoholimetro['nombre_activo']); ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($alcoholimetro['modelo'] ?? 'N/A'); ?> / 
                                        <?php echo htmlspecialchars($alcoholimetro['marca'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $clase_estado; ?>">
                                        <i class="fas <?php echo $icono_estado; ?>"></i>
                                        <?php echo $texto_estado; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $clase_calibracion; ?>">
                                        <i class="fas <?php echo $icono_calibracion; ?>"></i>
                                        <?php echo $texto_calibracion; ?>
                                    </span>
                                    <?php if ($alcoholimetro['proxima_calibracion'] && $estado_calibracion !== 'no_programada'): ?>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y', strtotime($alcoholimetro['proxima_calibracion'])); ?>
                                    </small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($alcoholimetro['fecha_actualizacion'])); ?>
                                    </small>
                                </td>
                                <td class="action-buttons">
                                    <button type="button" class="btn-icon primary" 
                                            onclick="mostrarModalEstado(<?php echo $alcoholimetro['id']; ?>, '<?php echo $alcoholimetro['numero_serie']; ?>', '<?php echo $alcoholimetro['estado']; ?>')"
                                            title="Cambiar Estado">
                                        <i class="fas fa-exchange-alt"></i>
                                    </button>
                                    <a href="calibraciones.php?editar=<?php echo $alcoholimetro['id']; ?>" class="btn-icon info" title="Gestionar Calibración">
                                        <i class="fas fa-calendar-alt"></i>
                                    </a>
                                    <a href="registrar-alcoholimetro.php?editar=<?php echo $alcoholimetro['id']; ?>" class="btn-icon" title="Editar">
                                        <i class="fas fa-edit"></i>
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
                        <i class="fas fa-search"></i>
                    </div>
                    <h3>No se encontraron alcoholímetros</h3>
                    <p>Intenta ajustar los filtros o registrar nuevos alcoholímetros</p>
                    <div class="empty-actions">
                        <a href="estados-alcoholimetros.php" class="btn btn-outline">
                            <i class="fas fa-times"></i>
                            Limpiar Filtros
                        </a>
                        <a href="registrar-alcoholimetro.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            Registrar Alcoholímetro
                        </a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Cambiar Estado -->
<div id="modalEstado" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-exchange-alt"></i> Cambiar Estado del Alcoholímetro</h3>
            <button type="button" class="modal-close" onclick="cerrarModalEstado()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <form id="formCambiarEstado" method="POST">
                <input type="hidden" name="cambiar_estado" value="1">
                <input type="hidden" id="alcoholimetro_id" name="alcoholimetro_id">
                
                <div class="form-group">
                    <label for="alcoholimetro_info">Alcoholímetro</label>
                    <input type="text" id="alcoholimetro_info" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label for="nuevo_estado">Nuevo Estado *</label>
                    <select id="nuevo_estado" name="nuevo_estado" class="form-control" required>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                        <option value="mantenimiento">En Mantenimiento</option>
                        <option value="calibracion">En Calibración</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="observaciones">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" class="form-control" rows="3" 
                              placeholder="Motivo del cambio de estado..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Guardar Cambio
                    </button>
                    <button type="button" class="btn btn-outline" onclick="cerrarModalEstado()">
                        <i class="fas fa-times"></i>
                        Cancelar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ESTILOS ESPECÍFICOS PARA ESTADOS -->
<style>
/* Contenedor principal */
.crud-container {
    margin-top: 1.5rem;
    width: 100%;
}

/* Panel de estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
    transition: transform 0.3s ease;
    position: relative;
    overflow: hidden;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
}

.stat-card .stat-icon.success ~ .stat-info::before { background: var(--success); }
.stat-card .stat-icon.warning ~ .stat-info::before { background: var(--warning); }
.stat-card .stat-icon.info ~ .stat-info::before { background: var(--primary); }
.stat-card .stat-icon.secondary ~ .stat-info::before { background: var(--gray); }

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-icon.primary { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
.stat-icon.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
.stat-icon.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
.stat-icon.danger { background: rgba(231, 76, 60, 0.1); color: var(--danger); }
.stat-icon.info { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
.stat-icon.secondary { background: rgba(149, 165, 166, 0.1); color: var(--gray); }

.stat-info {
    flex: 1;
}

.stat-info h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-info p {
    margin: 0.25rem 0 0 0;
    color: var(--gray);
    font-size: 0.9rem;
    font-weight: 500;
}

.stat-percentage {
    margin-top: 0.5rem;
}

.percentage {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.percentage.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
.percentage.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
.percentage.info { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
.percentage.secondary { background: rgba(149, 165, 166, 0.1); color: var(--gray); }

/* Formulario de filtros */
.filter-form {
    margin-bottom: 0;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    align-items: end;
}

.filter-indicator {
    background: var(--warning) !important;
    animation: pulse 2s infinite;
}

/* Badges de estado */
.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    min-width: 120px;
    justify-content: center;
    white-space: nowrap;
}

.status-badge.success {
    background: rgba(39, 174, 96, 0.15);
    color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.3);
}

.status-badge.warning {
    background: rgba(243, 156, 18, 0.15);
    color: var(--warning);
    border: 1px solid rgba(243, 156, 18, 0.3);
}

.status-badge.info {
    background: rgba(52, 152, 219, 0.15);
    color: var(--primary);
    border: 1px solid rgba(52, 152, 219, 0.3);
}

.status-badge.danger {
    background: rgba(231, 76, 60, 0.15);
    color: var(--danger);
    border: 1px solid rgba(231, 76, 60, 0.3);
}

.status-badge.secondary {
    background: rgba(149, 165, 166, 0.15);
    color: var(--gray);
    border: 1px solid rgba(149, 165, 166, 0.3);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 12px 12px 0 0;
}

.modal-header h3 {
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
    padding: 0.25rem;
    border-radius: 4px;
    transition: var(--transition);
}

.modal-close:hover {
    color: var(--danger);
    background: rgba(231, 76, 60, 0.1);
}

.modal-body {
    padding: 1.5rem;
}

/* Tabla de datos */
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
    background: rgba(52, 152, 219, 0.04);
}

/* Botones de acción */
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
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-icon:hover {
    transform: translateY(-2px);
}

.btn-icon.primary:hover {
    background: var(--primary);
    color: white;
}

.btn-icon.info:hover {
    background: var(--primary);
    color: white;
}

.btn-icon.success:hover {
    background: var(--success);
    color: white;
}

/* Badge contador */
.badge {
    padding: 0.4rem 0.8rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Tabla responsive */
.table-responsive {
    overflow-x: auto;
    border-radius: 12px;
}

/* Estados vacíos */
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

/* Colores de texto */
.text-danger { 
    color: var(--danger) !important; 
    font-weight: 600;
}
.text-warning { 
    color: var(--warning) !important; 
    font-weight: 600;
}
.text-success { 
    color: var(--success) !important; 
    font-weight: 600;
}
.text-muted { 
    color: var(--gray) !important; 
    opacity: 0.7;
}

/* Header del dashboard */
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
}

/* Card improvements */
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

/* Botones */
.btn {
    padding: 0.875rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
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
    box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
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

/* Formularios */
.form-group {
    display: flex;
    flex-direction: column;
    margin-bottom: 1.5rem;
}

.form-group label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
}

.form-control {
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

.form-control:hover {
    border-color: #c8d1d9;
    background: linear-gradient(135deg, #f5f7fa 0%, #ffffff 100%);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1), 
                0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
    margin-top: 1.5rem;
}

/* Alertas mejoradas */
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

/* Animaciones */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-50px) scale(0.9); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0.4); }
    70% { box-shadow: 0 0 0 10px rgba(243, 156, 18, 0); }
    100% { box-shadow: 0 0 0 0 rgba(243, 156, 18, 0); }
}

.card {
    animation: fadeIn 0.5s ease-out;
}

.data-table tr {
    animation: fadeIn 0.3s ease-out;
}

.stat-card {
    animation: fadeIn 0.5s ease-out;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .data-table {
        font-size: 0.85rem;
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
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table th,
    .data-table td {
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
    
    .form-actions {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        justify-content: center;
    }
    
    .empty-actions {
        flex-direction: column;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .card-actions {
        align-self: flex-start;
    }
    
    .modal-content {
        margin: 10% auto;
        width: 95%;
    }
}
</style>

<script>
// Funciones JavaScript para el módulo de estados
function mostrarModalEstado(id, numeroSerie, estadoActual) {
    document.getElementById('alcoholimetro_id').value = id;
    document.getElementById('alcoholimetro_info').value = numeroSerie;
    document.getElementById('nuevo_estado').value = estadoActual;
    document.getElementById('observaciones').value = '';
    
    document.getElementById('modalEstado').style.display = 'block';
}

function cerrarModalEstado() {
    document.getElementById('modalEstado').style.display = 'none';
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modalEstado');
    if (event.target === modal) {
        cerrarModalEstado();
    }
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalEstado();
    }
});

// Validación del formulario de cambio de estado
document.getElementById('formCambiarEstado').addEventListener('submit', function(e) {
    const nuevoEstado = document.getElementById('nuevo_estado').value;
    const estadoActual = '<?php echo $filtro_estado !== "todos" ? $filtro_estado : ""; ?>';
    
    if (nuevoEstado === estadoActual && estadoActual !== '') {
        e.preventDefault();
        alert('El nuevo estado debe ser diferente al estado actual.');
        return false;
    }
    
    const observaciones = document.getElementById('observaciones').value.trim();
    if (observaciones === '') {
        if (!confirm('¿Continuar sin agregar observaciones?')) {
            e.preventDefault();
            return false;
        }
    }
});

// Auto-enfoque en el campo de observaciones al abrir el modal
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('modalEstado');
    modal.addEventListener('shown', function() {
        document.getElementById('observaciones').focus();
    });
});

// Función para aplicar filtros automáticamente
function aplicarFiltro(tipo, valor) {
    const url = new URL(window.location.href);
    url.searchParams.set(tipo, valor);
    window.location.href = url.toString();
}

// Función para exportar reporte de estados
function exportarReporteEstados() {
    // Implementar lógica de exportación aquí
    alert('Funcionalidad de exportación en desarrollo');
}

// Función para actualizar estadísticas en tiempo real
function actualizarEstadisticas() {
    // Podría implementar actualización AJAX aquí si es necesario
    console.log('Estadísticas actualizadas');
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    actualizarEstadisticas();
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>