<?php
// alcoholimetros.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Gestión de Alcoholímetros';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'alcoholimetros.php' => 'Alcoholímetros'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// LÓGICA CRUD - PATRÓN ESTÁNDAR
$modo_edicion = false;
$alcoholimetro_actual = null;
$alcoholimetro_id = $_GET['editar'] ?? null;

// Obtener lista de alcoholímetros
$alcoholimetros = $db->fetchAll("
    SELECT * FROM alcoholimetros 
    WHERE cliente_id = ? 
    ORDER BY fecha_creacion DESC
", [$cliente_id]);

// Cargar alcoholímetro para editar
if ($alcoholimetro_id) {
    $alcoholimetro_actual = $db->fetchOne("
        SELECT * FROM alcoholimetros 
        WHERE id = ? AND cliente_id = ?
    ", [$alcoholimetro_id, $cliente_id]);
    
    if ($alcoholimetro_actual) {
        $modo_edicion = true;
    }
}

// Procesar formulario CREAR/ACTUALIZAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero_serie = trim($_POST['numero_serie'] ?? '');
    $nombre_activo = trim($_POST['nombre_activo'] ?? '');
    $modelo = trim($_POST['modelo'] ?? '');
    $marca = trim($_POST['marca'] ?? '');
    $fecha_calibracion = $_POST['fecha_calibracion'] ?? null;
    $proxima_calibracion = $_POST['proxima_calibracion'] ?? null;
    $estado = $_POST['estado'] ?? 'activo';
    
    try {
        if ($modo_edicion && $alcoholimetro_actual) {
            // ACTUALIZAR
            $db->execute("
                UPDATE alcoholimetros 
                SET numero_serie = ?, nombre_activo = ?, modelo = ?, marca = ?, 
                    fecha_calibracion = ?, proxima_calibracion = ?, estado = ?,
                    fecha_actualizacion = NOW()
                WHERE id = ? AND cliente_id = ?
            ", [$numero_serie, $nombre_activo, $modelo, $marca, $fecha_calibracion, 
                $proxima_calibracion, $estado, $alcoholimetro_id, $cliente_id]);
            
            $mensaje_exito = "Alcoholímetro actualizado correctamente";
        } else {
            // CREAR
            $db->execute("
                INSERT INTO alcoholimetros 
                (cliente_id, numero_serie, nombre_activo, modelo, marca, 
                 fecha_calibracion, proxima_calibracion, estado) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ", [$cliente_id, $numero_serie, $nombre_activo, $modelo, $marca, 
                $fecha_calibracion, $proxima_calibracion, $estado]);
            
            $mensaje_exito = "Alcoholímetro registrado correctamente";
            $modo_edicion = false;
        }
        
        // AUDITORÍA (patrón obligatorio)
        $accion = $modo_edicion ? 'ACTUALIZAR_ALCOHOLIMETRO' : 'CREAR_ALCOHOLIMETRO';
        $db->execute("
            INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, ?, ?, 'alcoholimetros', ?, ?, ?, ?)
        ", [$cliente_id, $user_id, $accion, $alcoholimetro_id ?? $db->lastInsertId(), 
            "Alcoholímetro " . ($modo_edicion ? 'actualizado' : 'creado'), 
            $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
        // Recargar lista
        $alcoholimetros = $db->fetchAll("
            SELECT * FROM alcoholimetros 
            WHERE cliente_id = ? 
            ORDER BY fecha_creacion DESC
        ", [$cliente_id]);
        
    } catch (Exception $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// Procesar ELIMINACIÓN con verificación de dependencias
if (isset($_GET['eliminar'])) {
    $id_eliminar = $_GET['eliminar'];
    
    try {
        // Verificar dependencias en pruebas (patrón importante)
        $pruebas_asociadas = $db->fetchOne("
            SELECT COUNT(*) as total FROM pruebas 
            WHERE alcoholimetro_id = ? AND cliente_id = ?
        ", [$id_eliminar, $cliente_id]);
        
        if ($pruebas_asociadas['total'] > 0) {
            $mensaje_error = "No se puede eliminar el alcoholímetro porque tiene pruebas asociadas. Cambie el estado a 'inactivo' en su lugar.";
        } else {
            $db->execute("
                DELETE FROM alcoholimetros 
                WHERE id = ? AND cliente_id = ?
            ", [$id_eliminar, $cliente_id]);
            
            $mensaje_exito = "Alcoholímetro eliminado correctamente";
            
            // Auditoría de eliminación
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'ELIMINAR_ALCOHOLIMETRO', 'alcoholimetros', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $id_eliminar, "Alcoholímetro eliminado", 
                $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            // Recargar lista
            $alcoholimetros = $db->fetchAll("
                SELECT * FROM alcoholimetros 
                WHERE cliente_id = ? 
                ORDER BY fecha_creacion DESC
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
            <p class="dashboard-subtitle">Gestiona los alcoholímetros del sistema</p>
        </div>
        <div class="header-actions">
            <?php if ($modo_edicion): ?>
                <a href="alcoholimetros.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i>Volver a la lista
                </a>
            <?php else: ?>
                <a href="?nuevo=true" class="btn btn-primary">
                    <i class="fas fa-plus"></i>Nuevo Alcoholímetro
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
                <h3><i class="fas fa-tachometer-alt"></i> Lista de Alcoholímetros</h3>
                <div class="card-actions">
                    <span class="badge"><?php echo count($alcoholimetros); ?> registros</span>
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
                                <th>Modelo</th>
                                <th>Marca</th>
                                <th>Última Calibración</th>
                                <th>Próxima Calibración</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alcoholimetros as $alcoholimetro): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($alcoholimetro['numero_serie']); ?></td>
                                <td><?php echo htmlspecialchars($alcoholimetro['nombre_activo']); ?></td>
                                <td><?php echo htmlspecialchars($alcoholimetro['modelo'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($alcoholimetro['marca'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if ($alcoholimetro['fecha_calibracion']): ?>
                                        <?php echo date('d/m/Y', strtotime($alcoholimetro['fecha_calibracion'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No calibrado</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($alcoholimetro['proxima_calibracion']): ?>
                                        <?php 
                                        $proxima = strtotime($alcoholimetro['proxima_calibracion']);
                                        $hoy = strtotime('today');
                                        $clase = ($proxima < $hoy) ? 'text-danger' : (($proxima - $hoy) <= 30*24*60*60 ? 'text-warning' : 'text-success');
                                        ?>
                                        <span class="<?php echo $clase; ?>">
                                            <?php echo date('d/m/Y', $proxima); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">No programada</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $estado_clases = [
                                        'activo' => 'success',
                                        'inactivo' => 'secondary',
                                        'mantenimiento' => 'warning',
                                        'calibracion' => 'info'
                                    ];
                                    $clase_estado = $estado_clases[$alcoholimetro['estado']] ?? 'secondary';
                                    ?>
                                    <span class="status-badge <?php echo $clase_estado; ?>">
                                        <?php echo ucfirst($alcoholimetro['estado']); ?>
                                    </span>
                                </td>
                                <td class="action-buttons">
                                    <a href="?editar=<?php echo $alcoholimetro['id']; ?>" class="btn-icon" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?eliminar=<?php echo $alcoholimetro['id']; ?>" 
                                       class="btn-icon danger" 
                                       title="Eliminar"
                                       onclick="return confirm('¿Estás seguro de eliminar este alcoholímetro?')">
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
                        <i class="fas fa-tachometer-alt"></i>
                    </div>
                    <h3>No hay alcoholímetros registrados</h3>
                    <p>Comienza registrando el primer alcoholímetro del sistema</p>
                    <a href="?nuevo=true" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Registrar Alcoholímetro
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
                    <i class="fas fa-<?php echo $modo_edicion ? 'edit' : 'plus'; ?>"></i>
                    <?php echo $modo_edicion ? 'Editar Alcoholímetro' : 'Registrar Nuevo Alcoholímetro'; ?>
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" class="account-form">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="numero_serie">Número de Serie *</label>
                            <input type="text" id="numero_serie" name="numero_serie" 
                                   value="<?php echo htmlspecialchars($alcoholimetro_actual['numero_serie'] ?? ''); ?>" 
                                   required class="form-control" 
                                   placeholder="Ej: ALC-001">
                            <small class="form-text">Identificador único del equipo</small>
                        </div>
                        <div class="form-group">
                            <label for="nombre_activo">Nombre del Activo *</label>
                            <input type="text" id="nombre_activo" name="nombre_activo" 
                                   value="<?php echo htmlspecialchars($alcoholimetro_actual['nombre_activo'] ?? ''); ?>" 
                                   required class="form-control" 
                                   placeholder="Ej: Alcoholímetro Principal">
                        </div>
                        <div class="form-group">
                            <label for="marca">Marca</label>
                            <input type="text" id="marca" name="marca" 
                                   value="<?php echo htmlspecialchars($alcoholimetro_actual['marca'] ?? ''); ?>" 
                                   class="form-control" 
                                   placeholder="Ej: AlcoTest, Dräger">
                        </div>
                        <div class="form-group">
                            <label for="modelo">Modelo</label>
                            <input type="text" id="modelo" name="modelo" 
                                   value="<?php echo htmlspecialchars($alcoholimetro_actual['modelo'] ?? ''); ?>" 
                                   class="form-control" 
                                   placeholder="Ej: AL-3000, Alcotest 6820">
                        </div>
                        <div class="form-group">
                            <label for="fecha_calibracion">Última Calibración</label>
                            <input type="date" id="fecha_calibracion" name="fecha_calibracion" 
                                   value="<?php echo htmlspecialchars($alcoholimetro_actual['fecha_calibracion'] ?? ''); ?>" 
                                   class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="proxima_calibracion">Próxima Calibración</label>
                            <input type="date" id="proxima_calibracion" name="proxima_calibracion" 
                                   value="<?php echo htmlspecialchars($alcoholimetro_actual['proxima_calibracion'] ?? ''); ?>" 
                                   class="form-control">
                            <small class="form-text">Recomendado: 1 año después de la última calibración</small>
                        </div>
                        <div class="form-group">
                            <label for="estado">Estado del Equipo</label>
                            <select id="estado" name="estado" class="form-control" required>
                                <option value="activo" <?php echo ($alcoholimetro_actual['estado'] ?? 'activo') === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?php echo ($alcoholimetro_actual['estado'] ?? '') === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                                <option value="mantenimiento" <?php echo ($alcoholimetro_actual['estado'] ?? '') === 'mantenimiento' ? 'selected' : ''; ?>>En Mantenimiento</option>
                                <option value="calibracion" <?php echo ($alcoholimetro_actual['estado'] ?? '') === 'calibracion' ? 'selected' : ''; ?>>En Calibración</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            <?php echo $modo_edicion ? 'Actualizar' : 'Registrar'; ?> Alcoholímetro
                        </button>
                        <a href="alcoholimetros.php" class="btn btn-outline">
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
/* [Todos los estilos CSS del patrón aquí - idénticos al ejemplo anterior] */
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
.status-badge.warning { background: rgba(243, 156, 18, 0.15); color: var(--warning); border: 1px solid rgba(243, 156, 18, 0.3); }
.status-badge.info { background: rgba(52, 152, 219, 0.15); color: var(--primary); border: 1px solid rgba(52, 152, 219, 0.3); }
.status-badge.secondary { background: rgba(149, 165, 166, 0.15); color: var(--gray); border: 1px solid rgba(149, 165, 166, 0.3); }
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
    // Auto-calcular próxima calibración (1 año después)
    const fechaCalibracion = document.getElementById('fecha_calibracion');
    const proximaCalibracion = document.getElementById('proxima_calibracion');
    
    if (fechaCalibracion && proximaCalibracion) {
        fechaCalibracion.addEventListener('change', function() {
            if (this.value && !proximaCalibracion.value) {
                const fecha = new Date(this.value);
                fecha.setFullYear(fecha.getFullYear() + 1);
                const proxima = fecha.toISOString().split('T')[0];
                proximaCalibracion.value = proxima;
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
        });
    }
});

function confirmarEliminacion(nombre) {
    return confirm(`¿Estás seguro de eliminar el alcoholímetro "${nombre}"? Esta acción no se puede deshacer.`);
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>