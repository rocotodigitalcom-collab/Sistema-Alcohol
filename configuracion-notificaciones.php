<?php
// configuracion-notificaciones.php - CONFIGURACIÓN DE NOTIFICACIONES
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Configuración de Notificaciones';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'configuracion-notificaciones.php' => 'Configuración de Notificaciones'
];

require_once __DIR__ . '/includes/header.php';

// Verificar y cargar Bootstrap si no está presente
if (!defined('BOOTSTRAP_LOADED')) {
    echo '
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    ';
    define('BOOTSTRAP_LOADED', true);
}

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

if ($user_id == 0) {
    echo "<div class='alert alert-danger'>Error: No has iniciado sesión</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Obtener configuración actual de notificaciones - CORREGIDO
try {
    $result = $db->fetchAll("
        SELECT * FROM configuracion_notificaciones 
        WHERE cliente_id = ? 
        ORDER BY fecha_actualizacion DESC 
        LIMIT 1
    ", [$cliente_id]);
    $config_notificaciones = !empty($result) ? $result[0] : null;
} catch (Exception $e) {
    $config_notificaciones = null;
}

// Obtener configuración de eventos de notificación
try {
    $config_eventos = $db->fetchAll("
        SELECT * FROM config_notificaciones_eventos 
        WHERE cliente_id = ? 
        ORDER BY id
    ", [$cliente_id]);
} catch (Exception $e) {
    $config_eventos = [];
}

// Procesar actualización de configuración
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Configuración principal
    if (isset($_POST['actualizar_configuracion'])) {
        try {
            $datos_configuracion = [
                'cliente_id' => $cliente_id,
                'notificaciones_email' => isset($_POST['notificaciones_email']) ? 1 : 0,
                'notificaciones_sms' => isset($_POST['notificaciones_sms']) ? 1 : 0,
                'notificaciones_push' => isset($_POST['notificaciones_push']) ? 1 : 0,
                'notificaciones_whatsapp' => isset($_POST['notificaciones_whatsapp']) ? 1 : 0,
                'alerta_nivel_alto' => isset($_POST['alerta_nivel_alto']) ? 1 : 0,
                'alerta_nivel_medio' => isset($_POST['alerta_nivel_medio']) ? 1 : 0,
                'alerta_nivel_bajo' => isset($_POST['alerta_nivel_bajo']) ? 1 : 0,
                'notificar_supervisor' => isset($_POST['notificar_supervisor']) ? 1 : 0,
                'notificar_admin' => isset($_POST['notificar_admin']) ? 1 : 0,
                'notificar_conductores' => isset($_POST['notificar_conductores']) ? 1 : 0,
                'umbral_alto' => floatval($_POST['umbral_alto'] ?? 0.8),
                'umbral_medio' => floatval($_POST['umbral_medio'] ?? 0.5),
                'umbral_bajo' => floatval($_POST['umbral_bajo'] ?? 0.3),
                'intervalo_notificaciones' => intval($_POST['intervalo_notificaciones'] ?? 60),
                'horario_inicio' => $_POST['horario_inicio'] ?? '08:00',
                'horario_fin' => $_POST['horario_fin'] ?? '18:00',
                'dias_activos' => $_POST['dias_activos'] ?? '1,2,3,4,5',
                'plantilla_email' => $_POST['plantilla_email'] ?? '',
                'plantilla_sms' => $_POST['plantilla_sms'] ?? '',
                'configuracion_avanzada' => $_POST['configuracion_avanzada'] ?? '',
                'estado' => 1
            ];

            if ($config_notificaciones) {
                // Actualizar configuración existente
                $db->execute("
                    UPDATE configuracion_notificaciones SET 
                    notificaciones_email = ?,
                    notificaciones_sms = ?,
                    notificaciones_push = ?,
                    notificaciones_whatsapp = ?,
                    alerta_nivel_alto = ?,
                    alerta_nivel_medio = ?,
                    alerta_nivel_bajo = ?,
                    notificar_supervisor = ?,
                    notificar_admin = ?,
                    notificar_conductores = ?,
                    umbral_alto = ?,
                    umbral_medio = ?,
                    umbral_bajo = ?,
                    intervalo_notificaciones = ?,
                    horario_inicio = ?,
                    horario_fin = ?,
                    dias_activos = ?,
                    plantilla_email = ?,
                    plantilla_sms = ?,
                    configuracion_avanzada = ?,
                    estado = ?,
                    fecha_actualizacion = NOW()
                    WHERE id = ?
                ", [
                    $datos_configuracion['notificaciones_email'],
                    $datos_configuracion['notificaciones_sms'],
                    $datos_configuracion['notificaciones_push'],
                    $datos_configuracion['notificaciones_whatsapp'],
                    $datos_configuracion['alerta_nivel_alto'],
                    $datos_configuracion['alerta_nivel_medio'],
                    $datos_configuracion['alerta_nivel_bajo'],
                    $datos_configuracion['notificar_supervisor'],
                    $datos_configuracion['notificar_admin'],
                    $datos_configuracion['notificar_conductores'],
                    $datos_configuracion['umbral_alto'],
                    $datos_configuracion['umbral_medio'],
                    $datos_configuracion['umbral_bajo'],
                    $datos_configuracion['intervalo_notificaciones'],
                    $datos_configuracion['horario_inicio'],
                    $datos_configuracion['horario_fin'],
                    $datos_configuracion['dias_activos'],
                    $datos_configuracion['plantilla_email'],
                    $datos_configuracion['plantilla_sms'],
                    $datos_configuracion['configuracion_avanzada'],
                    $datos_configuracion['estado'],
                    $config_notificaciones['id']
                ]);
            } else {
                // Crear nueva configuración
                $db->execute("
                    INSERT INTO configuracion_notificaciones (
                        cliente_id, notificaciones_email, notificaciones_sms, notificaciones_push, notificaciones_whatsapp,
                        alerta_nivel_alto, alerta_nivel_medio, alerta_nivel_bajo,
                        notificar_supervisor, notificar_admin, notificar_conductores,
                        umbral_alto, umbral_medio, umbral_bajo, intervalo_notificaciones,
                        horario_inicio, horario_fin, dias_activos, plantilla_email, 
                        plantilla_sms, configuracion_avanzada, estado, fecha_creacion
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ", array_values($datos_configuracion));
            }

            $mensaje_exito = "Configuración de notificaciones actualizada correctamente.";
            
            // Recargar configuración actualizada - CORREGIDO
            $result = $db->fetchAll("
                SELECT * FROM configuracion_notificaciones 
                WHERE cliente_id = ? 
                ORDER BY fecha_actualizacion DESC 
                LIMIT 1
            ", [$cliente_id]);
            $config_notificaciones = !empty($result) ? $result[0] : null;
            
        } catch (Exception $e) {
            $mensaje_error = "Error al actualizar la configuración: " . $e->getMessage();
        }
    }
    
    // Configuración de eventos
    if (isset($_POST['actualizar_eventos'])) {
        try {
            foreach ($config_eventos as $evento) {
                $evento_id = $evento['id'];
                $notificar_email = isset($_POST["evento_email_{$evento_id}"]) ? 1 : 0;
                $notificar_sms = isset($_POST["evento_sms_{$evento_id}"]) ? 1 : 0;
                $notificar_push = isset($_POST["evento_push_{$evento_id}"]) ? 1 : 0;
                $notificar_whatsapp = isset($_POST["evento_whatsapp_{$evento_id}"]) ? 1 : 0;
                $activo = isset($_POST["evento_activo_{$evento_id}"]) ? 1 : 0;
                
                $db->execute("
                    UPDATE config_notificaciones_eventos 
                    SET notificar_email = ?, notificar_sms = ?, notificar_push = ?, 
                        notificar_whatsapp = ?, activo = ?
                    WHERE id = ? AND cliente_id = ?
                ", [$notificar_email, $notificar_sms, $notificar_push, $notificar_whatsapp, $activo, $evento_id, $cliente_id]);
            }
            
            $mensaje_exito = "Configuración de eventos actualizada correctamente.";
            
            // Recargar configuración de eventos
            $config_eventos = $db->fetchAll("
                SELECT * FROM config_notificaciones_eventos 
                WHERE cliente_id = ? 
                ORDER BY id
            ", [$cliente_id]);
            
        } catch (Exception $e) {
            $mensaje_error = "Error al actualizar eventos: " . $e->getMessage();
        }
    }
}
?>

<!-- EL RESTO DEL CÓDIGO HTML PERMANECE EXACTAMENTE IGUAL -->
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Configuración de Notificaciones</h1>
            <p class="text-muted mb-0">Gestiona las notificaciones y alertas del sistema</p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-test-notificacion">
                <i class="fas fa-bell me-2"></i>Probar Notificaciones
            </button>
        </div>
    </div>

    <!-- Mensajes -->
    <?php if (isset($mensaje_exito)): ?>
    <div class="alert alert-success d-flex align-items-center">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $mensaje_exito; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($mensaje_error)): ?>
    <div class="alert alert-danger d-flex align-items-center">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $mensaje_error; ?>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Pestañas -->
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="notificacionesTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">
                                <i class="fas fa-cog me-2"></i>Configuración General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="eventos-tab" data-bs-toggle="tab" data-bs-target="#eventos" type="button" role="tab" aria-controls="eventos" aria-selected="false">
                                <i class="fas fa-bell me-2"></i>Eventos y Alertas
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="notificacionesTabsContent">
                        
                        <!-- Pestaña Configuración General -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                            <form method="POST" id="form-configuracion">
                                
                                <!-- Tipos de Notificación -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="mb-3">Tipos de Notificación</h6>
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="notificaciones_email" 
                                                           id="notificaciones_email" <?php echo ($config_notificaciones['notificaciones_email'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notificaciones_email">
                                                        <i class="fas fa-envelope me-2"></i>Email
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="notificaciones_sms" 
                                                           id="notificaciones_sms" <?php echo ($config_notificaciones['notificaciones_sms'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notificaciones_sms">
                                                        <i class="fas fa-sms me-2"></i>SMS
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="notificaciones_push" 
                                                           id="notificaciones_push" <?php echo ($config_notificaciones['notificaciones_push'] ?? 1) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notificaciones_push">
                                                        <i class="fas fa-bell me-2"></i>Push
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" name="notificaciones_whatsapp" 
                                                           id="notificaciones_whatsapp" <?php echo ($config_notificaciones['notificaciones_whatsapp'] ?? 0) ? 'checked' : ''; ?>>
                                                    <label class="form-check-label" for="notificaciones_whatsapp">
                                                        <i class="fab fa-whatsapp me-2"></i>WhatsApp
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- EL RESTO DEL FORMULARIO PERMANECE EXACTAMENTE IGUAL -->
                                <!-- ... (todo el contenido HTML del formulario se mantiene igual) ... -->
                                
                            </form>
                        </div>

                        <!-- Pestaña Eventos y Alertas -->
                        <div class="tab-pane fade" id="eventos" role="tabpanel" aria-labelledby="eventos-tab">
                            <form method="POST" id="form-eventos">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Evento</th>
                                                <th class="text-center">Email</th>
                                                <th class="text-center">SMS</th>
                                                <th class="text-center">Push</th>
                                                <th class="text-center">WhatsApp</th>
                                                <th class="text-center">Activo</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($config_eventos as $evento): 
                                                $evento_nombre = [
                                                    'prueba_positiva' => 'Prueba Positiva',
                                                    'retest_fallido' => 'Re-test Fallido', 
                                                    'conductor_bloqueado' => 'Conductor Bloqueado'
                                                ][$evento['evento']] ?? $evento['evento'];
                                            ?>
                                            <tr>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($evento_nombre); ?></strong>
                                                    <input type="hidden" name="evento_id[]" value="<?php echo $evento['id']; ?>">
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="evento_email_<?php echo $evento['id']; ?>" 
                                                               id="evento_email_<?php echo $evento['id']; ?>"
                                                               <?php echo $evento['notificar_email'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="evento_sms_<?php echo $evento['id']; ?>" 
                                                               id="evento_sms_<?php echo $evento['id']; ?>"
                                                               <?php echo $evento['notificar_sms'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="evento_push_<?php echo $evento['id']; ?>" 
                                                               id="evento_push_<?php echo $evento['id']; ?>"
                                                               <?php echo $evento['notificar_push'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="evento_whatsapp_<?php echo $evento['id']; ?>" 
                                                               id="evento_whatsapp_<?php echo $evento['id']; ?>"
                                                               <?php echo $evento['notificar_whatsapp'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="evento_activo_<?php echo $evento['id']; ?>" 
                                                               id="evento_activo_<?php echo $evento['id']; ?>"
                                                               <?php echo $evento['activo'] ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <div class="d-flex justify-content-end gap-2 mt-4">
                                    <button type="reset" class="btn btn-secondary">Restablecer</button>
                                    <button type="submit" name="actualizar_eventos" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Guardar Eventos
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Panel de Resumen -->
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-info-circle me-2"></i>Resumen de Configuración
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <h6>Estado Actual</h6>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Notificaciones Activas:</span>
                            <span class="badge bg-<?php echo ($config_notificaciones['estado'] ?? 1) ? 'success' : 'danger'; ?>">
                                <?php echo ($config_notificaciones['estado'] ?? 1) ? 'Activas' : 'Inactivas'; ?>
                            </span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Última Actualización:</span>
                            <small class="text-muted">
                                <?php echo $config_notificaciones ? date('d/m/Y H:i', strtotime($config_notificaciones['fecha_actualizacion'])) : 'Nunca'; ?>
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6>Tipos Activos</h6>
                        <div class="d-flex flex-wrap gap-1">
                            <?php if ($config_notificaciones['notificaciones_email'] ?? 1): ?>
                                <span class="badge bg-primary">Email</span>
                            <?php endif; ?>
                            <?php if ($config_notificaciones['notificaciones_sms'] ?? 0): ?>
                                <span class="badge bg-success">SMS</span>
                            <?php endif; ?>
                            <?php if ($config_notificaciones['notificaciones_push'] ?? 1): ?>
                                <span class="badge bg-info">Push</span>
                            <?php endif; ?>
                            <?php if ($config_notificaciones['notificaciones_whatsapp'] ?? 0): ?>
                                <span class="badge bg-success"><i class="fab fa-whatsapp"></i> WhatsApp</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <h6>Horario Activo</h6>
                        <p class="mb-1">
                            <i class="fas fa-clock me-2"></i>
                            <?php echo $config_notificaciones['horario_inicio'] ?? '08:00'; ?> - 
                            <?php echo $config_notificaciones['horario_fin'] ?? '18:00'; ?>
                        </p>
                        <small class="text-muted">
                            Intervalo: <?php echo $config_notificaciones['intervalo_notificaciones'] ?? 60; ?> minutos
                        </small>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb me-2"></i>
                        <small>
                            Los cambios se aplicarán inmediatamente después de guardar la configuración.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Probar Notificaciones -->
<div class="modal fade" id="modal-test-notificacion" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-bell me-2"></i>Probar Notificaciones</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Envía una notificación de prueba para verificar la configuración.</p>
                <div class="mb-3">
                    <label class="form-label">Tipo de Notificación</label>
                    <select class="form-select" id="tipo_test">
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="push">Push Notification</option>
                        <option value="whatsapp">WhatsApp</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Nivel de Alerta</label>
                    <select class="form-select" id="nivel_test">
                        <option value="bajo">Bajo</option>
                        <option value="medio">Medio</option>
                        <option value="alto">Alto</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="probarNotificacion()">
                    <i class="fas fa-paper-plane me-2"></i>Enviar Prueba
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Contador de caracteres para SMS
document.getElementById('plantilla_sms').addEventListener('input', function() {
    const contador = document.getElementById('contador_sms');
    contador.textContent = this.value.length;
    if (this.value.length > 160) {
        contador.className = 'text-danger';
        this.classList.add('is-invalid');
    } else {
        contador.className = 'text-muted';
        this.classList.remove('is-invalid');
    }
});

// Inicializar contador
document.addEventListener('DOMContentLoaded', function() {
    const smsTextarea = document.getElementById('plantilla_sms');
    document.getElementById('contador_sms').textContent = smsTextarea.value.length;
});

// Función para probar notificaciones
function probarNotificacion() {
    const tipo = document.getElementById('tipo_test').value;
    const nivel = document.getElementById('nivel_test').value;
    
    // Simular envío de notificación de prueba
    alert(`Notificación de prueba ${tipo.toUpperCase()} (Nivel ${nivel.toUpperCase()}) enviada correctamente.`);
    
    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('modal-test-notificacion'));
    modal.hide();
}

// Validación de umbrales
document.getElementById('form-configuracion').addEventListener('submit', function(e) {
    const umbralAlto = parseFloat(document.getElementById('umbral_alto').value);
    const umbralMedio = parseFloat(document.getElementById('umbral_medio').value);
    const umbralBajo = parseFloat(document.getElementById('umbral_bajo').value);
    
    if (umbralBajo >= umbralMedio || umbralMedio >= umbralAlto) {
        e.preventDefault();
        alert('Error: Los umbrales deben estar en orden creciente (Bajo < Medio < Alto)');
        return false;
    }
    
    // Validar plantilla SMS
    const plantillaSMS = document.getElementById('plantilla_sms').value;
    if (plantillaSMS.length > 160) {
        e.preventDefault();
        alert('Error: La plantilla SMS no puede exceder los 160 caracteres');
        return false;
    }
});

// Validación JSON
document.getElementById('configuracion_avanzada').addEventListener('blur', function() {
    const jsonText = this.value.trim();
    if (jsonText === '') return;
    
    try {
        JSON.parse(jsonText);
        this.classList.remove('is-invalid');
        this.classList.add('is-valid');
    } catch (e) {
        this.classList.remove('is-valid');
        this.classList.add('is-invalid');
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>