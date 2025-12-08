<?php
// protocolo.php - Archivo principal del módulo de protocolo
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';

$page_title = 'Protocolo Completo de Pruebas';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'protocolo.php' => 'Protocolo de Pruebas'
];

require_once __DIR__ . '/../includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// ============================================
// VARIABLES DE CONTROL DE FLUJO
// ============================================
$protocolo_id = $_GET['id'] ?? null;
$tab_actual = $_GET['tab'] ?? 1;
$prueba_actual = $_GET['prueba'] ?? null;

// Si es nuevo protocolo, crear registro inicial
if (!$protocolo_id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    try {
        $result = $db->execute("
            INSERT INTO operaciones (cliente_id, operador_id, estado, fecha_creacion)
            VALUES (?, ?, 'iniciado', NOW())
        ", [$cliente_id, $user_id]);
        
        $protocolo_id = $db->lastInsertId();
        
        // AUDITORÍA
        $db->execute("
            INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, ?, 'INICIAR_PROTOCOLO', 'operaciones', ?, 'Nuevo protocolo iniciado', ?, ?)
        ", [$cliente_id, $user_id, $protocolo_id, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
        header("Location: protocolo.php?id={$protocolo_id}&tab=1");
        exit;
    } catch (Exception $e) {
        $mensaje_error = "Error al crear protocolo: " . $e->getMessage();
    }
}

// ============================================
// PROCESAR FORMULARIOS POST
// ============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['guardar_operacion'])) {
            $ubicacion_id = $_POST['ubicacion_id'] ?? null;
            $lugar_pruebas = trim($_POST['lugar_pruebas'] ?? '');
            $fecha = $_POST['fecha'] ?? date('Y-m-d');
            $plan_motivo = $_POST['plan_motivo'] ?? 'diario';
            $hora_inicio = $_POST['hora_inicio'] ?? '08:00';
            $hora_cierre = $_POST['hora_cierre'] ?? '18:00';
            
            // Validaciones
            if (empty($lugar_pruebas)) {
                throw new Exception("El lugar de pruebas es obligatorio");
            }
            
            $db->execute("
                UPDATE operaciones 
                SET ubicacion_id = ?, lugar_pruebas = ?, fecha = ?, 
                    plan_motivo = ?, hora_inicio = ?, hora_cierre = ?,
                    estado = 'en_progreso', fecha_actualizacion = NOW()
                WHERE id = ? AND cliente_id = ?
            ", [$ubicacion_id, $lugar_pruebas, $fecha, $plan_motivo, $hora_inicio, $hora_cierre, $protocolo_id, $cliente_id]);
            
            $mensaje_exito = "Datos de operación guardados";
            
            // AUDITORÍA
            $detalles = "Operación: {$lugar_pruebas} - Plan: {$plan_motivo}";
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'GUARDAR_OPERACION', 'operaciones', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $protocolo_id, $detalles, $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            // Redirigir al siguiente tab
            header("Location: protocolo.php?id={$protocolo_id}&tab=2");
            exit;
        }
        
        if (isset($_POST['guardar_checklist'])) {
            // Lógica para guardar checklist
            $alcoholimetro_id = $_POST['alcoholimetro_id'] ?? null;
            $estado_alcoholimetro = $_POST['estado_alcoholimetro'] ?? 'conforme';
            
            // Verificar si ya existe checklist
            $checklist_existente = $db->fetchOne("
                SELECT id FROM checklists_operacion WHERE operacion_id = ?
            ", [$protocolo_id]);
            
            if ($checklist_existente) {
                // Actualizar
                $db->execute("
                    UPDATE checklists_operacion 
                    SET alcoholimetro_id = ?, estado_alcoholimetro = ?,
                        fecha_hora_actualizada = ?, bateria_cargada = ?,
                        enciende_condiciones = ?, impresora_operativa = ?,
                        boquillas = ?, documentacion_disponible = ?,
                        huellero = ?, lapicero = ?
                    WHERE operacion_id = ?
                ", [
                    $alcoholimetro_id,
                    $estado_alcoholimetro,
                    $_POST['fecha_hora_actualizada'] ?? 0,
                    $_POST['bateria_cargada'] ?? 0,
                    $_POST['enciende_condiciones'] ?? 0,
                    $_POST['impresora_operativa'] ?? 0,
                    $_POST['boquillas'] ?? 0,
                    $_POST['documentacion_disponible'] ?? 0,
                    $_POST['huellero'] ?? 0,
                    $_POST['lapicero'] ?? 0,
                    $protocolo_id
                ]);
            } else {
                // Insertar nuevo
                $db->execute("
                    INSERT INTO checklists_operacion 
                    (operacion_id, alcoholimetro_id, estado_alcoholimetro,
                     fecha_hora_actualizada, bateria_cargada, enciende_condiciones,
                     impresora_operativa, boquillas, documentacion_disponible,
                     huellero, lapicero)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ", [
                    $protocolo_id,
                    $alcoholimetro_id,
                    $estado_alcoholimetro,
                    $_POST['fecha_hora_actualizada'] ?? 0,
                    $_POST['bateria_cargada'] ?? 0,
                    $_POST['enciende_condiciones'] ?? 0,
                    $_POST['impresora_operativa'] ?? 0,
                    $_POST['boquillas'] ?? 0,
                    $_POST['documentacion_disponible'] ?? 0,
                    $_POST['huellero'] ?? 0,
                    $_POST['lapicero'] ?? 0
                ]);
            }
            
            $mensaje_exito = "Checklist guardado correctamente";
            
            // Redirigir al siguiente tab
            header("Location: protocolo.php?id={$protocolo_id}&tab=3");
            exit;
        }
        
        if (isset($_POST['guardar_consentimiento'])) {
            $conductor_id = $_POST['conductor_id'] ?? null;
            $objetivo_prueba = $_POST['objetivo_prueba'] ?? 'preventivo';
            $firma_conductor = $_POST['firma_conductor'] ?? null;
            
            if (!$conductor_id) {
                throw new Exception("Debe seleccionar un conductor");
            }
            
            // Crear acta de consentimiento
            $db->execute("
                INSERT INTO actas_consentimiento 
                (operacion_id, conductor_id, objetivo_prueba, firma_conductor)
                VALUES (?, ?, ?, ?)
            ", [$protocolo_id, $conductor_id, $objetivo_prueba, $firma_conductor]);
            
            $acta_id = $db->lastInsertId();
            
            // Crear registro en pruebas_protocolo
            $db->execute("
                INSERT INTO pruebas_protocolo 
                (operacion_id, conductor_id, acta_id, paso_actual)
                VALUES (?, ?, ?, 2)
            ", [$protocolo_id, $conductor_id, $acta_id]);
            
            $prueba_protocolo_id = $db->lastInsertId();
            
            $mensaje_exito = "Consentimiento registrado para el conductor";
            
            // Redirigir a encuesta para esta prueba
            header("Location: protocolo.php?id={$protocolo_id}&tab=4&prueba={$prueba_protocolo_id}");
            exit;
        }
        
    } catch (Exception $e) {
        $mensaje_error = "Error: " . $e->getMessage();
    }
}

// ============================================
// FUNCIONES DE CONSULTA
// ============================================
function obtenerOperacion($db, $protocolo_id, $cliente_id) {
    return $db->fetchOne("
        SELECT o.*, u.nombre as operador_nombre, u.apellido as operador_apellido,
               uc.nombre_ubicacion
        FROM operaciones o
        LEFT JOIN usuarios u ON o.operador_id = u.id
        LEFT JOIN ubicaciones_cliente uc ON o.ubicacion_id = uc.id
        WHERE o.id = ? AND o.cliente_id = ?
    ", [$protocolo_id, $cliente_id]);
}

function obtenerChecklist($db, $protocolo_id) {
    return $db->fetchOne("
        SELECT c.*, a.numero_serie, a.nombre_activo
        FROM checklists_operacion c
        LEFT JOIN alcoholimetros a ON c.alcoholimetro_id = a.id
        WHERE c.operacion_id = ?
    ", [$protocolo_id]);
}

function obtenerPruebasProtocolo($db, $protocolo_id) {
    return $db->fetchAll("
        SELECT 
            pp.id as prueba_protocolo_id,
            pp.conductor_id,
            pp.paso_actual,
            pp.completada,
            u.nombre as conductor_nombre,
            u.apellido as conductor_apellido,
            u.dni as conductor_dni,
            a.objetivo_prueba,
            p.resultado,
            p.nivel_alcohol,
            p.fecha_prueba
        FROM pruebas_protocolo pp
        LEFT JOIN usuarios u ON pp.conductor_id = u.id
        LEFT JOIN actas_consentimiento a ON pp.acta_id = a.id
        LEFT JOIN pruebas p ON pp.prueba_alcohol_id = p.id
        WHERE pp.operacion_id = ?
        ORDER BY pp.fecha_creacion DESC
    ", [$protocolo_id]);
}

function obtenerMetricasRealtime($db, $protocolo_id) {
    return $db->fetchOne("
        SELECT 
            COUNT(*) as total_pruebas,
            SUM(CASE WHEN p.resultado = 'reprobado' THEN 1 ELSE 0 END) as positivos,
            SUM(CASE WHEN p.resultado = 'aprobado' THEN 1 ELSE 0 END) as negativos,
            SUM(CASE WHEN p.resultado IS NULL THEN 1 ELSE 0 END) as pendientes,
            AVG(p.nivel_alcohol) as promedio_alcohol
        FROM pruebas_protocolo pp
        LEFT JOIN pruebas p ON pp.prueba_alcohol_id = p.id
        WHERE pp.operacion_id = ?
    ", [$protocolo_id]);
}

// ============================================
// OBTENER DATOS PARA LA VISTA
// ============================================
$operacion = obtenerOperacion($db, $protocolo_id, $cliente_id);
$checklist = obtenerChecklist($db, $protocolo_id);
$pruebas = obtenerPruebasProtocolo($db, $protocolo_id);
$metricas = obtenerMetricasRealtime($db, $protocolo_id);

// Obtener ubicaciones del cliente
$ubicaciones = $db->fetchAll("
    SELECT * FROM ubicaciones_cliente 
    WHERE cliente_id = ? AND estado = 1
    ORDER BY nombre_ubicacion
", [$cliente_id]);

// Obtener alcoholímetros activos
$alcoholimetros = $db->fetchAll("
    SELECT id, numero_serie, nombre_activo 
    FROM alcoholimetros 
    WHERE cliente_id = ? AND estado = 'activo'
    ORDER BY nombre_activo
", [$cliente_id]);

// Obtener conductores activos
$conductores = $db->fetchAll("
    SELECT id, nombre, apellido, dni 
    FROM usuarios 
    WHERE cliente_id = ? AND rol = 'conductor' AND estado = 1
    ORDER BY nombre, apellido
", [$cliente_id]);
?>

<!-- ============================================
     HTML - ESTRUCTURA PRINCIPAL
     ============================================ -->
<div class="content-body">
    <!-- HEADER CON PROGRESO -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">
                Protocolo ID: <strong><?php echo $protocolo_id; ?></strong> | 
                Estado: <span class="badge <?php echo $operacion['estado'] == 'completado' ? 'success' : ($operacion['estado'] == 'cancelado' ? 'danger' : 'warning'); ?>">
                    <?php echo ucfirst($operacion['estado'] ?? 'iniciado'); ?>
                </span>
            </p>
        </div>
        
        <!-- BARRA DE PROGRESO -->
        <div class="progress-steps">
            <?php 
            $tabs = ['Operación', 'Checklist', 'Consentimiento', 'Encuesta', 'Pruebas', 'Widmark', 'Informe', 'Resumen'];
            for($i = 1; $i <= 8; $i++): 
                $clase = '';
                if ($i == $tab_actual) {
                    $clase = 'active';
                } elseif ($i < $tab_actual) {
                    $clase = 'completed';
                }
            ?>
            <div class="step <?php echo $clase; ?>" onclick="cambiarTab(<?php echo $i; ?>)">
                <span class="step-number"><?php echo $i; ?></span>
                <span class="step-label"><?php echo $tabs[$i-1]; ?></span>
            </div>
            <?php endfor; ?>
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

    <div class="protocolo-container">
        <!-- SIDEBAR CON MÉTRICAS Y LISTA -->
        <div class="protocolo-sidebar">
            <!-- CARD DE MÉTRICAS -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Métricas en Tiempo Real</h3>
                </div>
                <div class="card-body">
                    <div id="metricasRealtime">
                        <div class="metric-grid">
                            <div class="metric-item">
                                <div class="metric-value"><?php echo $metricas['total_pruebas'] ?? 0; ?></div>
                                <div class="metric-label">Total Pruebas</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-value text-success"><?php echo $metricas['negativos'] ?? 0; ?></div>
                                <div class="metric-label">Negativos</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-value text-danger"><?php echo $metricas['positivos'] ?? 0; ?></div>
                                <div class="metric-label">Positivos</div>
                            </div>
                            <div class="metric-item">
                                <div class="metric-value text-warning"><?php echo $metricas['pendientes'] ?? 0; ?></div>
                                <div class="metric-label">Pendientes</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CARD DE PRUEBAS -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list-alt"></i> Lista de Pruebas</h3>
                    <span class="badge primary"><?php echo count($pruebas); ?></span>
                </div>
                <div class="card-body">
                    <div id="listaPruebas">
                        <?php if (!empty($pruebas)): ?>
                            <?php foreach ($pruebas as $prueba): ?>
                            <div class="prueba-item <?php echo $prueba_actual == $prueba['prueba_protocolo_id'] ? 'active' : ''; ?>"
                                 onclick="seleccionarPrueba(<?php echo $prueba['prueba_protocolo_id']; ?>)">
                                <div class="prueba-info">
                                    <strong><?php echo htmlspecialchars($prueba['conductor_nombre'] . ' ' . $prueba['conductor_apellido']); ?></strong>
                                    <small>DNI: <?php echo htmlspecialchars($prueba['conductor_dni']); ?></small>
                                </div>
                                <div class="prueba-estado">
                                    <?php if ($prueba['completada']): ?>
                                        <span class="badge <?php echo $prueba['resultado'] == 'reprobado' ? 'danger' : 'success'; ?>">
                                            <?php echo $prueba['resultado'] == 'reprobado' ? '⚠️ Positivo' : '✅ Negativo'; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge warning">
                                            Paso <?php echo $prueba['paso_actual']; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state-mini">
                                <i class="fas fa-users"></i>
                                <p>No hay pruebas registradas</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($tab_actual >= 3): ?>
                    <button type="button" class="btn btn-primary btn-block mt-2" onclick="nuevaPrueba()">
                        <i class="fas fa-user-plus"></i> Agregar Nuevo Conductor
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- CONTENIDO PRINCIPAL - TABS -->
        <div class="protocolo-content">
            <div class="tabs-container">
                <!-- TAB 1: OPERACIÓN -->
                <div class="tab-pane <?php echo $tab_actual == 1 ? 'active' : ''; ?>" id="tab1">
                    <?php include 'tabs/operacion.php'; ?>
                </div>
                
                <!-- TAB 2: CHECKLIST -->
                <div class="tab-pane <?php echo $tab_actual == 2 ? 'active' : ''; ?>" id="tab2">
                    <?php include 'tabs/checklist.php'; ?>
                </div>
                
                <!-- TAB 3: CONSENTIMIENTO -->
                <div class="tab-pane <?php echo $tab_actual == 3 ? 'active' : ''; ?>" id="tab3">
                    <?php include 'tabs/consentimiento.php'; ?>
                </div>
                
                <!-- TAB 4: ENCUESTA -->
                <div class="tab-pane <?php echo $tab_actual == 4 ? 'active' : ''; ?>" id="tab4">
                    <?php include 'tabs/encuesta.php'; ?>
                </div>
                
                <!-- TAB 5: PRUEBAS -->
                <div class="tab-pane <?php echo $tab_actual == 5 ? 'active' : ''; ?>" id="tab5">
                    <?php include 'tabs/pruebas-lista.php'; ?>
                </div>
                
                <!-- TAB 6: WIDMARK -->
                <div class="tab-pane <?php echo $tab_actual == 6 ? 'active' : ''; ?>" id="tab6">
                    <?php include 'tabs/widmark.php'; ?>
                </div>
                
                <!-- TAB 7: INFORME POSITIVO -->
                <div class="tab-pane <?php echo $tab_actual == 7 ? 'active' : ''; ?>" id="tab7">
                    <?php include 'tabs/informe-positivo.php'; ?>
                </div>
                
                <!-- TAB 8: RESUMEN -->
                <div class="tab-pane <?php echo $tab_actual == 8 ? 'active' : ''; ?>" id="tab8">
                    <?php include 'tabs/resumen.php'; ?>
                </div>
            </div>
            
            <!-- NAVEGACIÓN ENTRE TABS -->
            <div class="tab-navigation">
                <div class="nav-left">
                    <?php if ($tab_actual > 1): ?>
                    <button type="button" class="btn btn-outline" onclick="cambiarTab(<?php echo $tab_actual - 1; ?>)">
                        <i class="fas fa-arrow-left"></i> Anterior
                    </button>
                    <?php endif; ?>
                    
                    <?php if ($tab_actual == 5 && $prueba_actual): ?>
                    <button type="button" class="btn btn-outline" onclick="regresarALista()">
                        <i class="fas fa-list"></i> Volver a Lista
                    </button>
                    <?php endif; ?>
                </div>
                
                <div class="nav-center">
                    <?php if ($tab_actual == 8): ?>
                    <div class="final-actions">
                        <button type="button" class="btn btn-success" onclick="finalizarProtocolo()">
                            <i class="fas fa-flag-checkered"></i> Finalizar Protocolo
                        </button>
                        <button type="button" class="btn btn-primary" onclick="generarPDFs()">
                            <i class="fas fa-file-pdf"></i> Generar PDFs
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="nav-right">
                    <?php if ($tab_actual < 8): ?>
                    <button type="button" class="btn btn-primary" onclick="guardarYContinuar()">
                        Guardar y Continuar <i class="fas fa-arrow-right"></i>
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     MODALES Y COMPONENTES ADICIONALES
     ============================================ -->
<!-- MODAL PARA NUEVA PRUEBA -->
<div id="modalNuevaPrueba" class="modal" style="display: none;">
    <div class="modal-backdrop" onclick="cerrarModalNuevaPrueba()"></div>
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">
                    <i class="fas fa-user-plus"></i> Nueva Prueba
                </h3>
                <button type="button" class="modal-close" onclick="cerrarModalNuevaPrueba()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="formNuevaPrueba" method="POST">
                    <input type="hidden" name="protocolo_id" value="<?php echo $protocolo_id; ?>">
                    <input type="hidden" name="guardar_consentimiento" value="1">
                    
                    <div class="form-group">
                        <label for="nuevo_conductor_id" class="form-label">Seleccionar Conductor *</label>
                        <select id="nuevo_conductor_id" name="conductor_id" class="form-control" required>
                            <option value="">Seleccionar conductor</option>
                            <?php foreach ($conductores as $conductor): ?>
                            <option value="<?php echo $conductor['id']; ?>">
                                <?php echo htmlspecialchars($conductor['nombre'] . ' ' . $conductor['apellido'] . ' - DNI: ' . $conductor['dni']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="nuevo_objetivo_prueba" class="form-label">Objetivo de la prueba *</label>
                        <select id="nuevo_objetivo_prueba" name="objetivo_prueba" class="form-control" required>
                            <option value="preventivo">Preventivo</option>
                            <option value="descarte">Descarte</option>
                            <option value="estudio_positivo">Estudio de Positivo</option>
                            <option value="confirmatorio">Confirmatorio de Positivo</option>
                            <option value="otros">Otros</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="cerrarModalNuevaPrueba()">
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" onclick="crearNuevaPrueba()">
                    Crear Prueba
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================
     JAVASCRIPT PARA PROTOCOLO
     ============================================ -->
<script src="js/protocolo.js?v=<?php echo time(); ?>"></script>
<script>
// VARIABLES GLOBALES
let protocoloId = <?php echo $protocolo_id; ?>;
let tabActual = <?php echo $tab_actual; ?>;
let pruebaActual = <?php echo $prueba_actual ? "'{$prueba_actual}'" : 'null'; ?>;
let baseUrl = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '');

// FUNCIONES PRINCIPALES
function cambiarTab(nuevoTab, pruebaId = null) {
    let url = `protocolo.php?id=${protocoloId}&tab=${nuevoTab}`;
    if (pruebaId) {
        url += `&prueba=${pruebaId}`;
    }
    window.location.href = url;
}

function seleccionarPrueba(pruebaId) {
    // Dependiendo del tab actual, redirigir a la acción adecuada
    if (tabActual >= 4 && tabActual <= 7) {
        cambiarTab(tabActual, pruebaId);
    }
}

function regresarALista() {
    cambiarTab(5);
}

function guardarYContinuar() {
    const form = document.querySelector(`#tab${tabActual} form`);
    if (form) {
        // Validar formulario
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        // Mostrar loading
        const btn = document.querySelector('.tab-navigation .btn-primary');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';
        btn.disabled = true;
        
        // Enviar formulario
        form.submit();
    } else {
        // Si no hay formulario, solo avanzar
        cambiarTab(tabActual + 1);
    }
}

function nuevaPrueba() {
    const modal = document.getElementById('modalNuevaPrueba');
    modal.style.display = 'block';
    document.body.style.overflow = 'hidden';
}

function cerrarModalNuevaPrueba() {
    const modal = document.getElementById('modalNuevaPrueba');
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
}

function crearNuevaPrueba() {
    const form = document.getElementById('formNuevaPrueba');
    if (form.checkValidity()) {
        form.submit();
    } else {
        form.reportValidity();
    }
}

function finalizarProtocolo() {
    if (confirm('¿Está seguro de finalizar este protocolo? Esta acción no se puede deshacer.')) {
        // AJAX para finalizar protocolo
        fetch('ajax/finalizar-protocolo.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `protocolo_id=${protocoloId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Protocolo finalizado correctamente');
                window.location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al finalizar protocolo');
        });
    }
}

function generarPDFs() {
    // PDF completo
    window.open(`pdf/generar-pdf.php?tipo=completo&id=${protocoloId}`, '_blank');
    
    // Opcional: PDF individual por prueba
    if (confirm('¿Desea también generar PDFs individuales para cada prueba?')) {
        // Obtener lista de pruebas y generar PDFs
        fetch(`ajax/obtener-pruebas.php?protocolo_id=${protocoloId}`)
            .then(response => response.json())
            .then(pruebas => {
                pruebas.forEach((prueba, index) => {
                    if (prueba.completada) {
                        setTimeout(() => {
                            window.open(`pdf/generar-pdf.php?tipo=individual&id=${prueba.id}`, '_blank');
                        }, index * 1000);
                    }
                });
            });
    }
}

// ACTUALIZAR MÉTRICAS EN TIEMPO REAL
function actualizarMetricas() {
    fetch(`ajax/obtener-metricas.php?protocolo_id=${protocoloId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const metricas = data.metricas;
                document.getElementById('metricasRealtime').innerHTML = `
                    <div class="metric-grid">
                        <div class="metric-item">
                            <div class="metric-value">${metricas.total_pruebas || 0}</div>
                            <div class="metric-label">Total Pruebas</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value text-success">${metricas.negativos || 0}</div>
                            <div class="metric-label">Negativos</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value text-danger">${metricas.positivos || 0}</div>
                            <div class="metric-label">Positivos</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value text-warning">${metricas.pendientes || 0}</div>
                            <div class="metric-label">Pendientes</div>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error actualizando métricas:', error);
        });
}

// INICIALIZAR
document.addEventListener('DOMContentLoaded', function() {
    // Actualizar métricas cada 10 segundos
    actualizarMetricas();
    setInterval(actualizarMetricas, 10000);
    
    // Cerrar modales con ESC
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            cerrarModalNuevaPrueba();
        }
    });
    
    // Si hay una prueba específica seleccionada, resaltarla
    if (pruebaActual) {
        const pruebaElement = document.querySelector(`.prueba-item[onclick*="${pruebaActual}"]`);
        if (pruebaElement) {
            pruebaElement.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }
});

// FUNCIÓN PARA FIRMA DIGITAL (si se implementa)
function inicializarFirma() {
    // Código para inicializar canvas de firma
    console.log('Inicializando sistema de firma digital...');
}
</script>

<!-- ============================================
     ESTILOS ESPECÍFICOS PARA PROTOCOLO
     ============================================ -->
<link rel="stylesheet" href="css/protocolo.css?v=<?php echo time(); ?>">
<style>
/* ESTILOS BASE (complementarios a los existentes) */
.protocolo-container {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 1.5rem;
    margin-top: 1.5rem;
}

.protocolo-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.protocolo-content {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    border: 1px solid var(--border);
}

.progress-steps {
    display: flex;
    justify-content: space-between;
    margin-top: 1rem;
    padding: 0.5rem;
    background: var(--light);
    border-radius: 10px;
    cursor: pointer;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.25rem;
    flex: 1;
    position: relative;
    padding: 0.5rem;
    border-radius: 8px;
    transition: var(--transition);
}

.step:hover:not(.active) {
    background: rgba(132, 6, 31, 0.05);
}

.step-number {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    background: var(--border);
    color: var(--gray);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 0.9rem;
    transition: var(--transition);
}

.step.active .step-number {
    background: var(--primary);
    color: white;
    transform: scale(1.1);
}

.step.completed .step-number {
    background: var(--success);
    color: white;
}

.step-label {
    font-size: 0.7rem;
    text-align: center;
    color: var(--gray);
    font-weight: 500;
    transition: var(--transition);
}

.step.active .step-label {
    color: var(--primary);
    font-weight: 600;
}

.tabs-container {
    min-height: 500px;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.tab-navigation {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px solid var(--border);
}

.nav-left, .nav-right {
    flex: 1;
}

.nav-center {
    flex: 2;
    text-align: center;
}

.final-actions {
    display: flex;
    gap: 1rem;
    justify-content: center;
}

.metric-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 0.75rem;
}

.metric-item {
    text-align: center;
    padding: 0.75rem;
    background: var(--light);
    border-radius: 8px;
    border: 1px solid var(--border);
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--dark);
}

.metric-label {
    font-size: 0.75rem;
    color: var(--gray);
    margin-top: 0.25rem;
}

.prueba-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0.75rem;
    border-radius: 8px;
    border: 1px solid var(--border);
    margin-bottom: 0.5rem;
    cursor: pointer;
    transition: var(--transition);
    background: white;
}

.prueba-item:hover {
    background: var(--light);
    border-color: var(--primary);
}

.prueba-item.active {
    border-color: var(--primary);
    background: rgba(132, 6, 31, 0.05);
}

.prueba-info {
    flex: 1;
}

.prueba-info strong {
    display: block;
    font-size: 0.9rem;
    color: var(--dark);
}

.prueba-info small {
    font-size: 0.7rem;
    color: var(--gray);
}

.prueba-estado {
    min-width: 80px;
    text-align: center;
}

.empty-state-mini {
    text-align: center;
    padding: 1rem;
    color: var(--gray);
}

.empty-state-mini i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

.empty-state-mini p {
    margin: 0;
    font-size: 0.85rem;
}

/* MODALES */
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
    max-width: 500px;
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
    transition: var(--transition);
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

.modal-footer {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    padding: 1.5rem;
    border-top: 1px solid var(--border);
    gap: 1rem;
}

/* RESPONSIVE */
@media (max-width: 1024px) {
    .protocolo-container {
        grid-template-columns: 1fr;
    }
    
    .progress-steps {
        overflow-x: auto;
    }
    
    .step {
        min-width: 80px;
    }
    
    .tab-navigation {
        flex-direction: column;
        gap: 1rem;
    }
    
    .nav-left, .nav-center, .nav-right {
        width: 100%;
        text-align: center;
    }
}

@media (max-width: 768px) {
    .protocolo-content {
        padding: 1rem;
    }
    
    .metric-grid {
        grid-template-columns: 1fr;
    }
    
    .progress-steps {
        padding: 0.25rem;
    }
    
    .step-label {
        font-size: 0.6rem;
    }
    
    .final-actions {
        flex-direction: column;
    }
}
</style>

<?php
require_once __DIR__ . '/../includes/footer.php';
?>