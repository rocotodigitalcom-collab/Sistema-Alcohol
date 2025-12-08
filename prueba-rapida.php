<?php
// prueba-rapida.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Prueba Rápida de Alcohol';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'prueba-rapida.php' => 'Prueba Rápida'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// Obtener datos necesarios para los selects
$alcoholimetros = $db->fetchAll("
    SELECT id, numero_serie, nombre_activo 
    FROM alcoholimetros 
    WHERE cliente_id = ? AND estado = 'activo'
    ORDER BY nombre_activo
", [$cliente_id]);

$conductores = $db->fetchAll("
    SELECT id, nombre, apellido, dni 
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor' AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

$supervisores = $db->fetchAll("
    SELECT id, nombre, apellido 
    FROM usuarios 
    WHERE cliente_id = ? AND rol IN ('supervisor', 'admin') AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);

$vehiculos = $db->fetchAll("
    SELECT id, placa, marca, modelo 
    FROM vehiculos 
    WHERE cliente_id = ? AND estado = 'activo'
    ORDER BY placa
", [$cliente_id]);

// Obtener configuración del cliente para límites
$configuracion = $db->fetchOne("
    SELECT limite_alcohol_permisible, nivel_advertencia, nivel_critico, unidad_medida 
    FROM configuraciones 
    WHERE cliente_id = ?
", [$cliente_id]);

$limite_permisible = $configuracion['limite_alcohol_permisible'] ?? 0.000;

// Procesar formulario CREAR
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $alcoholimetro_id = $_POST['alcoholimetro_id'] ?? null;
    $conductor_id = $_POST['conductor_id'] ?? null;
    $supervisor_id = $_POST['supervisor_id'] ?? null;
    $vehiculo_id = $_POST['vehiculo_id'] ?? null;
    $nivel_alcohol = (float)($_POST['nivel_alcohol'] ?? 0);
    $latitud = $_POST['latitud'] ?? null;
    $longitud = $_POST['longitud'] ?? null;
    $observaciones = trim($_POST['observaciones'] ?? '');
    
    // Calcular resultado automáticamente
    $resultado = $nivel_alcohol <= $limite_permisible ? 'aprobado' : 'reprobado';
    
    try {
        // Validar que se hayan seleccionado los obligatorios
        if (empty($alcoholimetro_id) || empty($conductor_id) || empty($supervisor_id)) {
            throw new Exception("Todos los campos obligatorios deben ser seleccionados.");
        }

        // CREAR
        $db->execute("
            INSERT INTO pruebas 
            (cliente_id, alcoholimetro_id, conductor_id, supervisor_id, 
             vehiculo_id, nivel_alcohol, limite_permisible, resultado,
             latitud, longitud, observaciones) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [$cliente_id, $alcoholimetro_id, $conductor_id, $supervisor_id,
            $vehiculo_id, $nivel_alcohol, $limite_permisible, $resultado,
            $latitud, $longitud, $observaciones]);

        $mensaje_exito = "Prueba registrada correctamente. Resultado: " . strtoupper($resultado);

        // AUDITORÍA
        $db->execute("
            INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, ?, 'CREAR_PRUEBA_RAPIDA', 'pruebas', ?, ?, ?, ?)
        ", [$cliente_id, $user_id, $db->lastInsertId(), 
            "Prueba rápida creada - Nivel: " . $nivel_alcohol . " " . $configuracion['unidad_medida'] . " - Resultado: " . $resultado, 
            $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);

        // Limpiar el formulario después de guardar
        $alcoholimetro_id = $conductor_id = $supervisor_id = $vehiculo_id = $nivel_alcohol = $latitud = $longitud = $observaciones = null;
        
    } catch (Exception $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}
?>

<div class="content-body">
    <!-- HEADER IDÉNTICO -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Registro rápido de pruebas de alcohol</p>
        </div>
        <div class="header-actions">
            <a href="nueva-prueba.php" class="btn btn-outline">
                <i class="fas fa-list"></i>Ver Historial de Pruebas
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
        <!-- VISTA FORMULARIO (Siempre visible) -->
        <div class="card">
            <div class="card-header">
                <h3>
                    <i class="fas fa-vial"></i>
                    Registrar Nueva Prueba
                </h3>
            </div>
            <div class="card-body">
                <form method="POST" class="account-form" id="formPruebaRapida">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="alcoholimetro_id">Alcoholímetro *</label>
                            <select id="alcoholimetro_id" name="alcoholimetro_id" class="form-control" required>
                                <option value="">Seleccionar alcoholímetro</option>
                                <?php foreach ($alcoholimetros as $alcoholimetro): ?>
                                <option value="<?php echo $alcoholimetro['id']; ?>" 
                                    <?php echo (isset($alcoholimetro_id) && $alcoholimetro_id == $alcoholimetro['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($alcoholimetro['nombre_activo'] . ' (' . $alcoholimetro['numero_serie'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="conductor_id">Conductor *</label>
                            <select id="conductor_id" name="conductor_id" class="form-control" required>
                                <option value="">Seleccionar conductor</option>
                                <?php foreach ($conductores as $conductor): ?>
                                <option value="<?php echo $conductor['id']; ?>" 
                                    <?php echo (isset($conductor_id) && $conductor_id == $conductor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($conductor['nombre'] . ' ' . $conductor['apellido'] . ' (' . $conductor['dni'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="supervisor_id">Supervisor *</label>
                            <select id="supervisor_id" name="supervisor_id" class="form-control" required>
                                <option value="">Seleccionar supervisor</option>
                                <?php foreach ($supervisores as $supervisor): ?>
                                <option value="<?php echo $supervisor['id']; ?>" 
                                    <?php echo (isset($supervisor_id) && $supervisor_id == $supervisor['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($supervisor['nombre'] . ' ' . $supervisor['apellido']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="vehiculo_id">Vehículo</label>
                            <select id="vehiculo_id" name="vehiculo_id" class="form-control">
                                <option value="">Seleccionar vehículo (opcional)</option>
                                <?php foreach ($vehiculos as $vehiculo): ?>
                                <option value="<?php echo $vehiculo['id']; ?>" 
                                    <?php echo (isset($vehiculo_id) && $vehiculo_id == $vehiculo['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($vehiculo['placa'] . ' - ' . $vehiculo['marca'] . ' ' . $vehiculo['modelo']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="nivel_alcohol">Nivel de Alcohol (<?php echo $configuracion['unidad_medida']; ?>) *</label>
                            <input type="number" id="nivel_alcohol" name="nivel_alcohol" 
                                   value="<?php echo htmlspecialchars($nivel_alcohol ?? '0.000'); ?>" 
                                   step="0.001" min="0" max="1" required class="form-control"
                                   placeholder="0.000">
                            <small class="form-text">
                                Límite permisible: <?php echo number_format($limite_permisible, 3); ?> <?php echo $configuracion['unidad_medida']; ?>
                            </small>
                        </div>
                        <div class="form-group">
                            <label for="latitud">Latitud</label>
                            <input type="number" id="latitud" name="latitud" 
                                   value="<?php echo htmlspecialchars($latitud ?? ''); ?>" 
                                   step="0.00000001" class="form-control"
                                   placeholder="Ej: -12.046374">
                        </div>
                        <div class="form-group">
                            <label for="longitud">Longitud</label>
                            <input type="number" id="longitud" name="longitud" 
                                   value="<?php echo htmlspecialchars($longitud ?? ''); ?>" 
                                   step="0.00000001" class="form-control"
                                   placeholder="Ej: -77.042793">
                        </div>
                        <div class="form-group full-width">
                            <label for="observaciones">Observaciones</label>
                            <textarea id="observaciones" name="observaciones" class="form-control" 
                                      rows="3" placeholder="Observaciones adicionales de la prueba"><?php echo htmlspecialchars($observaciones ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Registrar Prueba
                        </button>
                        <button type="button" class="btn btn-outline" onclick="limpiarFormulario()">
                            <i class="fas fa-broom"></i>
                            Limpiar Formulario
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- PANEL DE RESULTADO EN TIEMPO REAL -->
        <div class="card" id="panelResultado" style="display: none;">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Resultado de la Prueba</h3>
            </div>
            <div class="card-body">
                <div class="resultado-panel">
                    <div class="resultado-indicador" id="indicadorResultado">
                        <div class="indicador-icono">
                            <i class="fas fa-question"></i>
                        </div>
                        <div class="indicador-texto">
                            <h4 id="textoResultado">Ingrese el nivel de alcohol</h4>
                            <p id="detalleResultado">El resultado se calculará automáticamente</p>
                        </div>
                    </div>
                    <div class="resultado-metricas">
                        <div class="metrica">
                            <span class="metrica-label">Nivel Actual</span>
                            <span class="metrica-valor" id="metricaNivel">0.000</span>
                        </div>
                        <div class="metrica">
                            <span class="metrica-label">Límite Permisible</span>
                            <span class="metrica-valor" id="metricaLimite"><?php echo number_format($limite_permisible, 3); ?></span>
                        </div>
                        <div class="metrica">
                            <span class="metrica-label">Diferencia</span>
                            <span class="metrica-valor" id="metricaDiferencia">0.000</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ESTILOS CSS INTEGRADOS (Mismo patrón + mejoras para prueba rápida) -->
<style>
/* [Todos los estilos CSS del patrón aquí - idénticos al módulo anterior] */
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
.account-form .form-group.full-width { grid-column: 1 / -1; }
.account-form .form-group label { font-weight: 600; color: var(--dark); margin-bottom: 0.5rem; font-size: 0.9rem; transition: var(--transition); }
.account-form .form-group:focus-within label { color: var(--primary); }
.account-form .form-control { padding: 0.875rem 1rem; border: 2px solid #e1e8ed; border-radius: 10px; font-size: 0.95rem; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02); color: var(--dark); width: 100%; box-sizing: border-box; }
.account-form .form-control:hover { border-color: #c8d1d9; background: linear-gradient(135deg, #f5f7fa 0%, #ffffff 100%); box-shadow: 0 4px 8px rgba(0, 0, 0, 0.04); }
.account-form .form-control:focus { outline: none; border-color: var(--primary); background: white; box-shadow: 0 0 0 4px rgba(52, 152, 219, 0.1), 0 4px 12px rgba(0, 0, 0, 0.08); transform: translateY(-1px); }
.account-form textarea.form-control { resize: vertical; min-height: 80px; }
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
.card { background: white; border-radius: 12px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); border: 1px solid var(--border); overflow: hidden; margin-bottom: 1.5rem; }
.card-header { padding: 1.5rem; border-bottom: 1px solid var(--border); background: var(--light); display: flex; justify-content: space-between; align-items: center; }
.card-header h3 { margin: 0; color: var(--dark); font-size: 1.3rem; font-weight: 600; display: flex; align-items: center; gap: 0.5rem; }
.card-body { padding: 1.5rem; }

/* ESTILOS ESPECÍFICOS PARA PRUEBA RÁPIDA */
.resultado-panel {
    text-align: center;
    padding: 1rem;
}

.resultado-indicador {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    margin-bottom: 2rem;
    padding: 1.5rem;
    border-radius: 12px;
    background: #f8f9fa;
    transition: all 0.3s ease;
}

.indicador-icono {
    font-size: 3rem;
    width: 80px;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: var(--light);
    color: var(--gray);
}

.indicador-texto h4 {
    margin: 0 0 0.5rem 0;
    font-size: 1.5rem;
    font-weight: 700;
}

.indicador-texto p {
    margin: 0;
    color: var(--gray);
    font-size: 1rem;
}

/* Estados del indicador */
.resultado-indicador.aprobado {
    background: rgba(39, 174, 96, 0.1);
    border: 2px solid rgba(39, 174, 96, 0.3);
}

.resultado-indicador.aprobado .indicador-icono {
    background: var(--success);
    color: white;
}

.resultado-indicador.reprobado {
    background: rgba(231, 76, 60, 0.1);
    border: 2px solid rgba(231, 76, 60, 0.3);
}

.resultado-indicador.reprobado .indicador-icono {
    background: var(--danger);
    color: white;
}

.resultado-indicador.advertencia {
    background: rgba(243, 156, 18, 0.1);
    border: 2px solid rgba(243, 156, 18, 0.3);
}

.resultado-indicador.advertencia .indicador-icono {
    background: var(--warning);
    color: white;
}

.resultado-metricas {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.metrica {
    padding: 1rem;
    background: white;
    border-radius: 8px;
    border: 1px solid var(--border);
    text-align: center;
}

.metrica-label {
    display: block;
    font-size: 0.8rem;
    color: var(--gray);
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    font-weight: 600;
}

.metrica-valor {
    display: block;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--dark);
}

.metrica .positivo { color: var(--success); }
.metrica .negativo { color: var(--danger); }
.metrica .neutral { color: var(--warning); }

/* Responsive */
@media (max-width: 1024px) {
    .data-table { font-size: 0.85rem; }
    .account-form .form-grid { grid-template-columns: 1fr; gap: 1rem; }
    .account-form .form-group.full-width { grid-column: 1; }
    .resultado-metricas { grid-template-columns: 1fr; }
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
    .resultado-indicador { flex-direction: column; text-align: center; gap: 1rem; }
    .indicador-icono { width: 60px; height: 60px; font-size: 2rem; }
}
</style>

<script>
// FUNCIONES JS MEJORADAS PARA PRUEBA RÁPIDA
document.addEventListener('DOMContentLoaded', function() {
    const nivelAlcoholInput = document.getElementById('nivel_alcohol');
    const panelResultado = document.getElementById('panelResultado');
    const indicadorResultado = document.getElementById('indicadorResultado');
    const textoResultado = document.getElementById('textoResultado');
    const detalleResultado = document.getElementById('detalleResultado');
    const metricaNivel = document.getElementById('metricaNivel');
    const metricaDiferencia = document.getElementById('metricaDiferencia');
    
    const limitePermisible = <?php echo $limite_permisible; ?>;
    const nivelAdvertencia = <?php echo $configuracion['nivel_advertencia'] ?? 0.025; ?>;
    const nivelCritico = <?php echo $configuracion['nivel_critico'] ?? 0.080; ?>;
    const unidadMedida = '<?php echo $configuracion['unidad_medida']; ?>';

    // Obtener geolocalización automática
    const latitudInput = document.getElementById('latitud');
    const longitudInput = document.getElementById('longitud');
    
    if (navigator.geolocation) {
        const geoButton = document.createElement('button');
        geoButton.type = 'button';
        geoButton.className = 'btn btn-outline';
        geoButton.innerHTML = '<i class="fas fa-map-marker-alt"></i> Obtener Ubicación Actual';
        geoButton.style.marginTop = '0.5rem';
        geoButton.style.width = '100%';
        
        geoButton.addEventListener('click', function() {
            geoButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Obteniendo ubicación...';
            geoButton.disabled = true;
            
            navigator.geolocation.getCurrentPosition(function(position) {
                latitudInput.value = position.coords.latitude.toFixed(8);
                longitudInput.value = position.coords.longitude.toFixed(8);
                geoButton.innerHTML = '<i class="fas fa-check-circle"></i> Ubicación obtenida';
                setTimeout(() => {
                    geoButton.innerHTML = '<i class="fas fa-map-marker-alt"></i> Obtener Ubicación Actual';
                    geoButton.disabled = false;
                }, 2000);
            }, function(error) {
                alert('No se pudo obtener la ubicación: ' + error.message);
                geoButton.innerHTML = '<i class="fas fa-map-marker-alt"></i> Obtener Ubicación Actual';
                geoButton.disabled = false;
            }, {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 60000
            });
        });
        
        if (latitudInput.parentNode) {
            latitudInput.parentNode.appendChild(geoButton);
        }
    }

    // Actualizar resultado en tiempo real
    function actualizarResultado() {
        const nivel = parseFloat(nivelAlcoholInput.value) || 0;
        const diferencia = nivel - limitePermisible;
        
        // Actualizar métricas
        metricaNivel.textContent = nivel.toFixed(3);
        metricaDiferencia.textContent = Math.abs(diferencia).toFixed(3);
        metricaDiferencia.className = diferencia > 0 ? 'negativo' : 'positivo';
        
        // Determinar estado y actualizar UI
        if (nivel === 0) {
            panelResultado.style.display = 'none';
            return;
        }
        
        panelResultado.style.display = 'block';
        
        if (nivel <= limitePermisible) {
            // APROBADO
            indicadorResultado.className = 'resultado-indicador aprobado';
            indicadorResultado.querySelector('.indicador-icono').innerHTML = '<i class="fas fa-check-circle"></i>';
            textoResultado.textContent = 'PRUEBA APROBADA';
            textoResultado.style.color = 'var(--success)';
            detalleResultado.textContent = 'El conductor está dentro de los límites permitidos';
        } else if (nivel <= nivelAdvertencia) {
            // ADVERTENCIA (ligeramente arriba del límite)
            indicadorResultado.className = 'resultado-indicador advertencia';
            indicadorResultado.querySelector('.indicador-icono').innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            textoResultado.textContent = 'PRUEBA REPROBADA - ADVERTENCIA';
            textoResultado.style.color = 'var(--warning)';
            detalleResultado.textContent = 'Nivel ligeramente superior al límite permitido';
        } else {
            // REPROBADO
            indicadorResultado.className = 'resultado-indicador reprobado';
            indicadorResultado.querySelector('.indicador-icono').innerHTML = '<i class="fas fa-times-circle"></i>';
            textoResultado.textContent = 'PRUEBA REPROBADA';
            textoResultado.style.color = 'var(--danger)';
            detalleResultado.textContent = 'Nivel significativamente superior al límite permitido';
        }
        
        // Cambiar color del input según el nivel
        if (nivel > nivelCritico) {
            nivelAlcoholInput.style.borderColor = 'var(--danger)';
            nivelAlcoholInput.style.boxShadow = '0 0 0 4px rgba(231, 76, 60, 0.1)';
        } else if (nivel > nivelAdvertencia) {
            nivelAlcoholInput.style.borderColor = 'var(--warning)';
            nivelAlcoholInput.style.boxShadow = '0 0 0 4px rgba(243, 156, 18, 0.1)';
        } else if (nivel > limitePermisible) {
            nivelAlcoholInput.style.borderColor = 'var(--warning)';
            nivelAlcoholInput.style.boxShadow = '0 0 0 4px rgba(243, 156, 18, 0.1)';
        } else {
            nivelAlcoholInput.style.borderColor = 'var(--success)';
            nivelAlcoholInput.style.boxShadow = '0 0 0 4px rgba(39, 174, 96, 0.1)';
        }
    }

    // Event listeners
    nivelAlcoholInput.addEventListener('input', actualizarResultado);
    nivelAlcoholInput.addEventListener('change', actualizarResultado);
    
    // Validación del formulario
    const form = document.getElementById('formPruebaRapida');
    if (form) {
        form.addEventListener('submit', function(e) {
            const nivel = parseFloat(nivelAlcoholInput.value);
            const conductor = document.getElementById('conductor_id').value;
            const alcoholimetro = document.getElementById('alcoholimetro_id').value;
            const supervisor = document.getElementById('supervisor_id').value;
            
            if (!conductor || !alcoholimetro || !supervisor) {
                e.preventDefault();
                alert('Por favor, complete todos los campos obligatorios.');
                return false;
            }
            
            if (isNaN(nivel) || nivel < 0) {
                e.preventDefault();
                alert('El nivel de alcohol debe ser un número válido mayor o igual a 0.');
                return false;
            }
            
            if (nivel > limitePermisible) {
                const conductorNombre = document.getElementById('conductor_id').options[document.getElementById('conductor_id').selectedIndex].text;
                if (!confirm(`⚠️ PRUEBA REPROBADA\n\nConductor: ${conductorNombre}\nNivel: ${nivel.toFixed(3)} ${unidadMedida}\nLímite: ${limitePermisible.toFixed(3)} ${unidadMedida}\n\n¿Desea registrar la prueba como REPROBADA?`)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    }
    
    // Inicializar resultado
    actualizarResultado();
});

function limpiarFormulario() {
    if (confirm('¿Estás seguro de limpiar el formulario? Se perderán todos los datos ingresados.')) {
        document.getElementById('formPruebaRapida').reset();
        document.getElementById('panelResultado').style.display = 'none';
        
        // Restablecer estilos del input
        const nivelAlcoholInput = document.getElementById('nivel_alcohol');
        if (nivelAlcoholInput) {
            nivelAlcoholInput.style.borderColor = '';
            nivelAlcoholInput.style.boxShadow = '';
        }
    }
}

// Función para escanear código QR (placeholder para futura implementación)
function escanearQR() {
    alert('Función de escaneo QR disponible próximamente. Por ahora, ingrese los datos manualmente.');
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>