<?php
// calibraciones.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Gestión de Calibraciones';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'calibraciones.php' => 'Calibraciones'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// LÓGICA CRUD PARA CALIBRACIONES
$modo_edicion = false;
$alcoholimetro_actual = null;
$alcoholimetro_id = $_GET['editar'] ?? null;

// Obtener lista de alcoholímetros con información de calibración
$alcoholimetros = $db->fetchAll("
    SELECT *, 
           DATEDIFF(proxima_calibracion, CURDATE()) as dias_restantes,
           CASE 
               WHEN proxima_calibracion IS NULL THEN 'no_programada'
               WHEN proxima_calibracion < CURDATE() THEN 'vencida'
               WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 30 THEN 'proxima'
               ELSE 'vigente'
           END as estado_calibracion
    FROM alcoholimetros 
    WHERE cliente_id = ? 
    ORDER BY 
        CASE 
            WHEN proxima_calibracion IS NULL THEN 1
            WHEN proxima_calibracion < CURDATE() THEN 0
            ELSE 2
        END,
        proxima_calibracion ASC
", [$cliente_id]);

// Cargar alcoholímetro para editar calibración
if ($alcoholimetro_id) {
    $alcoholimetro_actual = $db->fetchOne("
        SELECT * FROM alcoholimetros 
        WHERE id = ? AND cliente_id = ?
    ", [$alcoholimetro_id, $cliente_id]);
    
    if ($alcoholimetro_actual) {
        $modo_edicion = true;
    }
}

// Procesar formulario ACTUALIZAR CALIBRACIÓN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_calibracion'])) {
    $fecha_calibracion = $_POST['fecha_calibracion'] ?? null;
    $proxima_calibracion = $_POST['proxima_calibracion'] ?? null;
    $observaciones_calibracion = trim($_POST['observaciones_calibracion'] ?? '');
    
    try {
        $db->execute("
            UPDATE alcoholimetros 
            SET fecha_calibracion = ?, proxima_calibracion = ?, fecha_actualizacion = NOW()
            WHERE id = ? AND cliente_id = ?
        ", [$fecha_calibracion, $proxima_calibracion, $alcoholimetro_id, $cliente_id]);
        
        $mensaje_exito = "Calibración actualizada correctamente";
        
        // Registrar en auditoría
        $db->execute("
            INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, ?, 'ACTUALIZAR_CALIBRACION', 'alcoholimetros', ?, ?, ?, ?)
        ", [$cliente_id, $user_id, $alcoholimetro_id, 
            "Calibración actualizada para alcoholímetro ID: $alcoholimetro_id. Observaciones: $observaciones_calibracion", 
            $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
        // Recargar lista
        $alcoholimetros = $db->fetchAll("
            SELECT *, 
                   DATEDIFF(proxima_calibracion, CURDATE()) as dias_restantes,
                   CASE 
                       WHEN proxima_calibracion IS NULL THEN 'no_programada'
                       WHEN proxima_calibracion < CURDATE() THEN 'vencida'
                       WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 30 THEN 'proxima'
                       ELSE 'vigente'
                   END as estado_calibracion
            FROM alcoholimetros 
            WHERE cliente_id = ? 
            ORDER BY 
                CASE 
                    WHEN proxima_calibracion IS NULL THEN 1
                    WHEN proxima_calibracion < CURDATE() THEN 0
                    ELSE 2
                END,
                proxima_calibracion ASC
        ", [$cliente_id]);
        
        // Salir del modo edición
        $modo_edicion = false;
        $alcoholimetro_actual = null;
        
    } catch (Exception $e) {
        $mensaje_error = "Error al actualizar la calibración: " . $e->getMessage();
    }
}

// Procesar CALIBRACIÓN RÁPIDA (desde la lista)
if (isset($_GET['calibrar'])) {
    $id_calibrar = $_GET['calibrar'];
    
    try {
        $hoy = date('Y-m-d');
        $proximo_ano = date('Y-m-d', strtotime('+1 year'));
        
        $db->execute("
            UPDATE alcoholimetros 
            SET fecha_calibracion = ?, proxima_calibracion = ?, fecha_actualizacion = NOW()
            WHERE id = ? AND cliente_id = ?
        ", [$hoy, $proximo_ano, $id_calibrar, $cliente_id]);
        
        $mensaje_exito = "Calibración registrada correctamente";
        
        // Auditoría
        $db->execute("
            INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, ?, 'CALIBRACION_RAPIDA', 'alcoholimetros', ?, ?, ?, ?)
        ", [$cliente_id, $user_id, $id_calibrar, "Calibración rápida realizada", 
            $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
        // Recargar lista
        $alcoholimetros = $db->fetchAll("
            SELECT *, 
                   DATEDIFF(proxima_calibracion, CURDATE()) as dias_restantes,
                   CASE 
                       WHEN proxima_calibracion IS NULL THEN 'no_programada'
                       WHEN proxima_calibracion < CURDATE() THEN 'vencida'
                       WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 30 THEN 'proxima'
                       ELSE 'vigente'
                   END as estado_calibracion
            FROM alcoholimetros 
            WHERE cliente_id = ? 
            ORDER BY 
                CASE 
                    WHEN proxima_calibracion IS NULL THEN 1
                    WHEN proxima_calibracion < CURDATE() THEN 0
                    ELSE 2
                END,
                proxima_calibracion ASC
        ", [$cliente_id]);
        
    } catch (Exception $e) {
        $mensaje_error = "Error al registrar calibración: " . $e->getMessage();
    }
}

// Estadísticas de calibración
$estadisticas = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN proxima_calibracion < CURDATE() THEN 1 ELSE 0 END) as vencidas,
        SUM(CASE WHEN proxima_calibracion IS NULL THEN 1 ELSE 0 END) as no_programadas,
        SUM(CASE WHEN DATEDIFF(proxima_calibracion, CURDATE()) BETWEEN 1 AND 30 THEN 1 ELSE 0 END) as proximas_vencer,
        SUM(CASE WHEN proxima_calibracion >= CURDATE() AND DATEDIFF(proxima_calibracion, CURDATE()) > 30 THEN 1 ELSE 0 END) as vigentes
    FROM alcoholimetros 
    WHERE cliente_id = ?
", [$cliente_id]);
?>

<div class="content-body">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Gestiona y programa las calibraciones de los alcoholímetros</p>
        </div>
        <div class="header-actions">
            <?php if ($modo_edicion): ?>
                <a href="calibraciones.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>
                    Volver al listado
                </a>
            <?php else: ?>
                <a href="registrar-alcoholimetro.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i>
                    Nuevo Alcoholímetro
                </a>
            <?php endif; ?>
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
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas['total']; ?></h3>
                <p>Total Alcoholímetros</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas['vencidas']; ?></h3>
                <p>Calibraciones Vencidas</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas['proximas_vencer']; ?></h3>
                <p>Próximas a Vencer</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas['vigentes']; ?></h3>
                <p>Calibraciones Vigentes</p>
            </div>
        </div>
    </div>

    <div class="crud-container">
        <!-- VISTA LISTA (Principal) -->
        <?php if (!$modo_edicion): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Programación de Calibraciones</h3>
                <div class="card-actions">
                    <span class="badge"><?php echo count($alcoholimetros); ?> equipos</span>
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
                                <th>Última Calibración</th>
                                <th>Próxima Calibración</th>
                                <th>Estado</th>
                                <th>Días Restantes</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alcoholimetros as $alcoholimetro): 
                                $dias_restantes = $alcoholimetro['dias_restantes'] ?? null;
                                $estado_calibracion = $alcoholimetro['estado_calibracion'] ?? 'no_programada';
                                
                                // Determinar clase y texto según estado
                                switch ($estado_calibracion) {
                                    case 'vencida':
                                        $clase_estado = 'danger';
                                        $texto_estado = 'Vencida';
                                        $icono_estado = 'fa-exclamation-triangle';
                                        break;
                                    case 'proxima':
                                        $clase_estado = 'warning';
                                        $texto_estado = 'Próxima';
                                        $icono_estado = 'fa-clock';
                                        break;
                                    case 'vigente':
                                        $clase_estado = 'success';
                                        $texto_estado = 'Vigente';
                                        $icono_estado = 'fa-check-circle';
                                        break;
                                    default:
                                        $clase_estado = 'secondary';
                                        $texto_estado = 'No Programada';
                                        $icono_estado = 'fa-question-circle';
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
                                    <?php if ($alcoholimetro['fecha_calibracion']): ?>
                                        <?php echo date('d/m/Y', strtotime($alcoholimetro['fecha_calibracion'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No calibrado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($alcoholimetro['proxima_calibracion']): ?>
                                        <?php echo date('d/m/Y', strtotime($alcoholimetro['proxima_calibracion'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No programada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $clase_estado; ?>">
                                        <i class="fas <?php echo $icono_estado; ?>"></i>
                                        <?php echo $texto_estado; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($dias_restantes !== null): ?>
                                        <?php if ($dias_restantes < 0): ?>
                                            <span class="text-danger">
                                                <i class="fas fa-exclamation-circle"></i>
                                                <?php echo abs($dias_restantes); ?> días de retraso
                                            </span>
                                        <?php else: ?>
                                            <span class="<?php echo $dias_restantes <= 30 ? 'text-warning' : 'text-success'; ?>">
                                                <?php echo $dias_restantes; ?> días
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="?editar=<?php echo $alcoholimetro['id']; ?>" class="btn-icon" title="Programar Calibración">
                                        <i class="fas fa-calendar-plus"></i>
                                    </a>
                                    <a href="?calibrar=<?php echo $alcoholimetro['id']; ?>" 
                                       class="btn-icon success" 
                                       title="Registrar Calibración Hoy"
                                       onclick="return confirm('¿Registrar calibración para hoy? La próxima calibración se programará para 1 año después.')">
                                        <i class="fas fa-sync-alt"></i>
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
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3>No hay alcoholímetros registrados</h3>
                    <p>Registra alcoholímetros para gestionar sus calibraciones</p>
                    <a href="registrar-alcoholimetro.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Registrar Alcoholímetro
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- VISTA FORMULARIO (Programar Calibración) -->
        <?php if ($modo_edicion && $alcoholimetro_actual): ?>
        <div class="card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-calendar-plus"></i>
                    Programar Calibración: <?php echo htmlspecialchars($alcoholimetro_actual['nombre_activo']); ?>
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" class="account-form">
                    <input type="hidden" name="actualizar_calibracion" value="1">
                    
                    <div class="calibration-info">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Número de Serie:</label>
                                <span><?php echo htmlspecialchars($alcoholimetro_actual['numero_serie']); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Modelo:</label>
                                <span><?php echo htmlspecialchars($alcoholimetro_actual['modelo'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Marca:</label>
                                <span><?php echo htmlspecialchars($alcoholimetro_actual['marca'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="info-item">
                                <label>Estado Actual:</label>
                                <span class="status-badge <?php echo $alcoholimetro_actual['estado'] === 'activo' ? 'success' : 'secondary'; ?>">
                                    <?php echo ucfirst($alcoholimetro_actual['estado']); ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="fecha_calibracion">Fecha de Última Calibración</label>
                            <input type="date" id="fecha_calibracion" name="fecha_calibracion" 
                                   value="<?php echo htmlspecialchars($alcoholimetro_actual['fecha_calibracion'] ?? ''); ?>" 
                                   class="form-control">
                            <small class="form-text">Fecha en que se realizó la última calibración</small>
                        </div>
                        <div class="form-group">
                            <label for="proxima_calibracion">Próxima Calibración Programada</label>
                            <input type="date" id="proxima_calibracion" name="proxima_calibracion" 
                                   value="<?php echo htmlspecialchars($alcoholimetro_actual['proxima_calibracion'] ?? ''); ?>" 
                                   class="form-control" required>
                            <small class="form-text">Fecha programada para la próxima calibración</small>
                        </div>
                        <div class="form-group full-width">
                            <label for="observaciones_calibracion">Observaciones de Calibración</label>
                            <textarea id="observaciones_calibracion" name="observaciones_calibracion" 
                                      class="form-control" rows="3" 
                                      placeholder="Observaciones sobre la calibración realizada..."></textarea>
                        </div>
                    </div>
                    
                    <div class="calibration-actions">
                        <div class="quick-actions">
                            <h4>Acciones Rápidas:</h4>
                            <div class="quick-buttons">
                                <button type="button" class="btn btn-outline btn-sm" onclick="setCalibrationToday()">
                                    <i class="fas fa-sync-alt"></i> Calibrar Hoy
                                </button>
                                <button type="button" class="btn btn-outline btn-sm" onclick="setNextYear()">
                                    <i class="fas fa-calendar-plus"></i> Programar para 1 Año
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Guardar Calibración
                        </button>
                        <a href="calibraciones.php" class="btn btn-outline">
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

<!-- ESTILOS ESPECÍFICOS PARA CALIBRACIONES -->
<style>
/* Contenedor principal */
.crud-container {
    margin-top: 1.5rem;
    width: 100%;
}

/* Panel de estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
}

.stat-card:hover {
    transform: translateY(-5px);
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

.stat-icon.primary { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
.stat-icon.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
.stat-icon.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
.stat-icon.danger { background: rgba(231, 76, 60, 0.1); color: var(--danger); }

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
}

/* Información de calibración */
.calibration-info {
    background: var(--light);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border-left: 4px solid var(--primary);
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
}

.info-item {
    display: flex;
    flex-direction: column;
}

.info-item label {
    font-weight: 600;
    color: var(--dark);
    font-size: 0.85rem;
    margin-bottom: 0.25rem;
}

.info-item span {
    color: var(--gray);
    font-size: 0.95rem;
}

/* Acciones rápidas */
.calibration-actions {
    background: rgba(52, 152, 219, 0.05);
    border-radius: 10px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    border: 1px solid rgba(52, 152, 219, 0.1);
}

.quick-actions h4 {
    margin: 0 0 1rem 0;
    color: var(--dark);
    font-size: 1rem;
}

.quick-buttons {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
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
}

.btn-icon:hover {
    background: var(--primary);
    color: white;
    transform: translateY(-2px);
}

.btn-icon.success:hover {
    background: var(--success);
}

.btn-icon.danger:hover {
    background: var(--danger);
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
    min-width: 100px;
    justify-content: center;
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

/* ========== ESTILOS PARA FORMULARIOS ========== */
/* Grid del formulario */
.account-form .form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.account-form .form-group {
    display: flex;
    flex-direction: column;
    margin-bottom: 0;
}

.account-form .form-group.full-width {
    grid-column: 1 / -1;
}

.account-form .form-group label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    transition: var(--transition);
}

.account-form .form-group:focus-within label {
    color: var(--primary);
}

/* Inputs mejorados */
.account-form .form-control {
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

.account-form .form-control:hover {
    border-color: #c8d1d9;
    background: linear-gradient(135deg, #f5f7fa 0%, #ffffff 100%);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04);
}

.account-form .form-control:focus {
    outline: none;
    border-color: var(--primary);
    background: white;
    box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1), 
                0 4px 12px rgba(0, 0, 0, 0.08);
    transform: translateY(-1px);
}

.account-form textarea.form-control {
    min-height: 100px;
    resize: vertical;
}

/* Botones del formulario */
.account-form .form-actions {
    display: flex;
    gap: 1rem;
    justify-content: flex-start;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
    margin-top: 1.5rem;
}

.account-form .btn {
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

.account-form .btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-color: var(--primary);
}

.account-form .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
}

.account-form .btn-outline {
    background: transparent;
    color: var(--dark);
    border-color: var(--border);
}

.account-form .btn-outline:hover {
    background: var(--light);
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

.account-form .btn-sm {
    padding: 0.5rem 1rem;
    font-size: 0.8rem;
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

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .data-table {
        font-size: 0.85rem;
    }
    
    .account-form .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
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
    
    .account-form .form-actions {
        flex-direction: column;
    }
    
    .account-form .btn {
        width: 100%;
        justify-content: center;
    }
    
    .quick-buttons {
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
}

/* Animaciones */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
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
</style>

<script>
// Funciones JavaScript para gestión de calibraciones
document.addEventListener('DOMContentLoaded', function() {
    // Auto-calcular próxima calibración (1 año después)
    const fechaCalibracion = document.getElementById('fecha_calibracion');
    const proximaCalibracion = document.getElementById('proxima_calibracion');
    
    if (fechaCalibracion && proximaCalibracion) {
        fechaCalibracion.addEventListener('change', function() {
            if (this.value && !proximaCalibracion.value) {
                setNextYear();
            }
        });
    }
    
    // Validación de fechas
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            const fechaCal = document.getElementById('fecha_calibracion').value;
            const fechaProx = document.getElementById('proxima_calibracion').value;
            
            if (fechaCal && fechaProx) {
                const cal = new Date(fechaCal);
                const prox = new Date(fechaProx);
                
                if (prox <= cal) {
                    e.preventDefault();
                    alert('La fecha de próxima calibración debe ser posterior a la última calibración.');
                    return false;
                }
            }
            
            if (!fechaProx) {
                e.preventDefault();
                alert('La fecha de próxima calibración es obligatoria.');
                return false;
            }
        });
    }
});

// Establecer calibración para hoy
function setCalibrationToday() {
    const hoy = new Date().toISOString().split('T')[0];
    document.getElementById('fecha_calibracion').value = hoy;
    setNextYear();
}

// Establecer próxima calibración para 1 año después
function setNextYear() {
    const fechaCalibracion = document.getElementById('fecha_calibracion').value;
    if (fechaCalibracion) {
        const fecha = new Date(fechaCalibracion);
        fecha.setFullYear(fecha.getFullYear() + 1);
        const proxima = fecha.toISOString().split('T')[0];
        document.getElementById('proxima_calibracion').value = proxima;
    } else {
        const hoy = new Date();
        hoy.setFullYear(hoy.getFullYear() + 1);
        const proxima = hoy.toISOString().split('T')[0];
        document.getElementById('proxima_calibracion').value = proxima;
    }
}

// Confirmación para calibración rápida
function confirmarCalibracionRapida(nombre) {
    return confirm(`¿Registrar calibración para el alcoholímetro "${nombre}"? Se establecerá la fecha de hoy como última calibración y se programará la próxima para 1 año después.`);
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>