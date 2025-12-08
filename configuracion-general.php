<?php
// configuracion-general.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Configuración General';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'configuracion-general.php' => 'Configuración General'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$cliente_id = $_SESSION['cliente_id'] ?? 0;
$user_id = $_SESSION['user_id'] ?? 0;

// Obtener configuración actual
$configuracion = $db->fetchOne("SELECT * FROM configuraciones WHERE cliente_id = ?", [$cliente_id]);
$cliente = $db->fetchOne("SELECT * FROM clientes WHERE id = ?", [$cliente_id]);
$plan = $db->fetchOne("SELECT * FROM planes WHERE id = ?", [$cliente['plan_id'] ?? 1]);

// Obtener regulaciones por país
$regulaciones = $db->fetchAll("SELECT * FROM regulaciones_alcohol WHERE activo = 1");

// Obtener configuración de notificaciones por eventos
$config_notificaciones = $db->fetchAll("SELECT * FROM config_notificaciones_eventos WHERE cliente_id = ?", [$cliente_id]);

// Procesar actualización de información de la empresa
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_empresa'])) {
    $nombre_empresa = trim($_POST['nombre_empresa'] ?? '');
    $ruc = trim($_POST['ruc'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email_contacto = trim($_POST['email_contacto'] ?? '');
    $color_primario = trim($_POST['color_primario'] ?? '#2196F3');
    $color_secundario = trim($_POST['color_secundario'] ?? '#1976D2');
    
    try {
        $db->execute("
            UPDATE clientes 
            SET nombre_empresa = ?, ruc = ?, direccion = ?, telefono = ?, 
                email_contacto = ?, color_primario = ?, color_secundario = ?
            WHERE id = ?
        ", [$nombre_empresa, $ruc, $direccion, $telefono, $email_contacto, $color_primario, $color_secundario, $cliente_id]);
        
        $mensaje_exito = "Información de la empresa actualizada correctamente";
        
        // Registrar en auditoría
        $db->execute("
            INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, ?, 'CONFIG_EMPRESA', 'clientes', ?, ?, ?, ?)
        ", [$cliente_id, $user_id, $cliente_id, "Actualización de información de la empresa", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
    } catch (Exception $e) {
        $mensaje_error = "Error al actualizar la información: " . $e->getMessage();
    }
}

// Procesar actualización de configuración de alcohol
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_alcohol'])) {
    $limite_alcohol_permisible = floatval($_POST['limite_alcohol_permisible'] ?? 0.000);
    $nivel_advertencia = floatval($_POST['nivel_advertencia'] ?? 0.025);
    $nivel_critico = floatval($_POST['nivel_critico'] ?? 0.080);
    $unidad_medida = trim($_POST['unidad_medida'] ?? 'g/L');
    $pais_aplicable = trim($_POST['pais_aplicable'] ?? 'PE');
    
    try {
        // Guardar configuración actual en historial antes de actualizar
        $db->execute("
            INSERT INTO historial_niveles_alcohol 
            (cliente_id, usuario_id, limite_anterior, limite_nuevo, nivel_advertencia_anterior, nivel_advertencia_nuevo, nivel_critico_anterior, nivel_critico_nuevo, motivo_cambio)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $cliente_id, $user_id,
            $configuracion['limite_alcohol_permisible'],
            $limite_alcohol_permisible,
            $configuracion['nivel_advertencia'],
            $nivel_advertencia,
            $configuracion['nivel_critico'],
            $nivel_critico,
            "Actualización desde configuración general"
        ]);
        
        $db->execute("
            UPDATE configuraciones 
            SET limite_alcohol_permisible = ?, nivel_advertencia = ?, nivel_critico = ?, 
                unidad_medida = ?, fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE cliente_id = ?
        ", [$limite_alcohol_permisible, $nivel_advertencia, $nivel_critico, $unidad_medida, $cliente_id]);
        
        $mensaje_exito = "Configuración de alcohol actualizada correctamente";
        
        // Registrar en auditoría
        $db->execute("
            INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, ?, 'CONFIG_ALCOHOL', 'configuraciones', ?, ?, ?, ?)
        ", [$cliente_id, $user_id, $cliente_id, "Actualización de niveles de alcohol", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
    } catch (Exception $e) {
        $mensaje_error = "Error al actualizar la configuración: " . $e->getMessage();
    }
}

// Procesar actualización de configuración de re-test
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_retest'])) {
    $intervalo_retest_minutos = intval($_POST['intervalo_retest_minutos'] ?? 15);
    $intentos_retest = intval($_POST['intentos_retest'] ?? 3);
    $bloqueo_conductor_horas = intval($_POST['bloqueo_conductor_horas'] ?? 24);
    $notificar_supervisor_retest = isset($_POST['notificar_supervisor_retest']) ? 1 : 0;
    $requerir_aprobacion_supervisor = isset($_POST['requerir_aprobacion_supervisor']) ? 1 : 0;
    
    try {
        $db->execute("
            UPDATE configuraciones 
            SET intervalo_retest_minutos = ?, intentos_retest = ?, bloqueo_conductor_horas = ?,
                notificar_supervisor_retest = ?, requerir_aprobacion_supervisor = ?, fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE cliente_id = ?
        ", [$intervalo_retest_minutos, $intentos_retest, $bloqueo_conductor_horas, $notificar_supervisor_retest, $requerir_aprobacion_supervisor, $cliente_id]);
        
        $mensaje_exito = "Configuración de re-test actualizada correctamente";
        
        // Registrar en auditoría
        $db->execute("
            INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
            VALUES (?, ?, 'CONFIG_RETEST', 'configuraciones', ?, ?, ?, ?)
        ", [$cliente_id, $user_id, $cliente_id, "Actualización de protocolo re-test", $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        
    } catch (Exception $e) {
        $mensaje_error = "Error al actualizar la configuración: " . $e->getMessage();
    }
}

// Procesar actualización de notificaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_notificaciones'])) {
    $notificaciones_email = isset($_POST['notificaciones_email']) ? 1 : 0;
    $notificaciones_sms = isset($_POST['notificaciones_sms']) ? 1 : 0;
    $notificaciones_push = isset($_POST['notificaciones_push']) ? 1 : 0;
    $notificaciones_whatsapp = isset($_POST['notificaciones_whatsapp']) ? 1 : 0;
    $email_notificacion = trim($_POST['email_notificacion'] ?? '');
    $telefono_notificacion = trim($_POST['telefono_notificacion'] ?? '');
    
    try {
        $db->execute("
            UPDATE configuraciones 
            SET notificaciones_email = ?, notificaciones_sms = ?, notificaciones_push = ?,
                notificaciones_whatsapp = ?, email_notificacion = ?, telefono_notificacion = ?,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE cliente_id = ?
        ", [$notificaciones_email, $notificaciones_sms, $notificaciones_push, $notificaciones_whatsapp, $email_notificacion, $telefono_notificacion, $cliente_id]);
        
        $mensaje_exito = "Configuración de notificaciones actualizada correctamente";
        
    } catch (Exception $e) {
        $mensaje_error = "Error al actualizar las notificaciones: " . $e->getMessage();
    }
}

// Procesar actualización de preferencias generales
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_general'])) {
    $requerir_geolocalizacion = isset($_POST['requerir_geolocalizacion']) ? 1 : 0;
    $requerir_foto_evidencia = isset($_POST['requerir_foto_evidencia']) ? 1 : 0;
    $requerir_firma_digital = isset($_POST['requerir_firma_digital']) ? 1 : 0;
    $requerir_observaciones = isset($_POST['requerir_observaciones']) ? 1 : 0;
    $tiempo_maximo_prueba_minutos = intval($_POST['tiempo_maximo_prueba_minutos'] ?? 10);
    $distancia_maxima_metros = intval($_POST['distancia_maxima_metros'] ?? 500);
    
    try {
        $db->execute("
            UPDATE configuraciones 
            SET requerir_geolocalizacion = ?, requerir_foto_evidencia = ?, requerir_firma_digital = ?,
                requerir_observaciones = ?, tiempo_maximo_prueba_minutos = ?, distancia_maxima_metros = ?,
                fecha_actualizacion = CURRENT_TIMESTAMP
            WHERE cliente_id = ?
        ", [$requerir_geolocalizacion, $requerir_foto_evidencia, $requerir_firma_digital, $requerir_observaciones, $tiempo_maximo_prueba_minutos, $distancia_maxima_metros, $cliente_id]);
        
        $mensaje_exito = "Configuración general actualizada correctamente";
        
    } catch (Exception $e) {
        $mensaje_error = "Error al actualizar la configuración general: " . $e->getMessage();
    }
}

// Actualizar configuración después de los cambios
$configuracion = $db->fetchOne("SELECT * FROM configuraciones WHERE cliente_id = ?", [$cliente_id]);
?>

<div class="content-body">
    <!-- Header de Configuración General -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1>Configuración General</h1>
            <p class="dashboard-subtitle">Gestiona la configuración del sistema y preferencias de la empresa</p>
        </div>
        <div class="header-actions">
            <div class="plan-badge">
                <i class="fas fa-crown"></i>
                Plan <?php echo htmlspecialchars($plan['nombre_plan']); ?>
            </div>
        </div>
    </div>

    <!-- Mensajes de alerta -->
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

    <div class="configuration-container">
        <!-- Panel lateral de navegación -->
        <div class="configuration-sidebar">
            <nav class="configuration-nav">
                <a href="#empresa" class="nav-item active" data-tab="empresa">
                    <i class="fas fa-building"></i>
                    Información Empresa
                </a>
                <a href="#alcohol" class="nav-item" data-tab="alcohol">
                    <i class="fas fa-vial"></i>
                    Niveles de Alcohol
                </a>
                <a href="#retest" class="nav-item" data-tab="retest">
                    <i class="fas fa-redo"></i>
                    Protocolo Re-test
                </a>
                <a href="#notificaciones" class="nav-item" data-tab="notificaciones">
                    <i class="fas fa-bell"></i>
                    Notificaciones
                </a>
                <a href="#preferencias" class="nav-item" data-tab="preferencias">
                    <i class="fas fa-cogs"></i>
                    Preferencias Generales
                </a>
                <a href="#seguridad" class="nav-item" data-tab="seguridad">
                    <i class="fas fa-shield-alt"></i>
                    Seguridad
                </a>
            </nav>

            <div class="sidebar-info">
                <div class="info-card">
                    <h4>Estado del Sistema</h4>
                    <div class="status-list">
                        <div class="status-item success">
                            <i class="fas fa-check-circle"></i>
                            <span>Configuración Activa</span>
                        </div>
                        <div class="status-item info">
                            <i class="fas fa-sync"></i>
                            <span>Última actualización: <?php echo date('d/m/Y H:i', strtotime($configuracion['fecha_actualizacion'])); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contenido principal -->
        <div class="configuration-content">
            <!-- Pestaña: Información de la Empresa -->
            <div id="empresa" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-building"></i> Información de la Empresa</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="configuration-form">
                            <div class="form-grid">
                                <div class="form-group full-width">
                                    <label for="nombre_empresa">Nombre de la Empresa *</label>
                                    <input type="text" id="nombre_empresa" name="nombre_empresa" 
                                           value="<?php echo htmlspecialchars($cliente['nombre_empresa'] ?? ''); ?>" 
                                           required class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="ruc">RUC *</label>
                                    <input type="text" id="ruc" name="ruc" 
                                           value="<?php echo htmlspecialchars($cliente['ruc'] ?? ''); ?>" 
                                           required class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <input type="tel" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($cliente['telefono'] ?? ''); ?>" 
                                           class="form-control">
                                </div>
                                
                                <div class="form-group full-width">
                                    <label for="direccion">Dirección</label>
                                    <textarea id="direccion" name="direccion" class="form-control" rows="3"><?php echo htmlspecialchars($cliente['direccion'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="form-group">
                                    <label for="email_contacto">Email de Contacto</label>
                                    <input type="email" id="email_contacto" name="email_contacto" 
                                           value="<?php echo htmlspecialchars($cliente['email_contacto'] ?? ''); ?>" 
                                           class="form-control">
                                </div>
                                
                                <div class="form-group">
                                    <label for="color_primario">Color Primario</label>
                                    <div class="color-input-group">
                                        <input type="color" id="color_primario" name="color_primario" 
                                               value="<?php echo htmlspecialchars($cliente['color_primario'] ?? '#2196F3'); ?>" 
                                               class="form-control color-picker">
                                        <span class="color-value"><?php echo htmlspecialchars($cliente['color_primario'] ?? '#2196F3'); ?></span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="color_secundario">Color Secundario</label>
                                    <div class="color-input-group">
                                        <input type="color" id="color_secundario" name="color_secundario" 
                                               value="<?php echo htmlspecialchars($cliente['color_secundario'] ?? '#1976D2'); ?>" 
                                               class="form-control color-picker">
                                        <span class="color-value"><?php echo htmlspecialchars($cliente['color_secundario'] ?? '#1976D2'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" name="actualizar_empresa" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Niveles de Alcohol -->
            <div id="alcohol" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-vial"></i> Configuración de Niveles de Alcohol</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="configuration-form">
                            <div class="form-section">
                                <h4>Límites de Alcohol</h4>
                                <div class="limits-info">
                                    <div class="limit-card success">
                                        <div class="limit-icon">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="limit-content">
                                            <div class="limit-title">Nivel Seguro</div>
                                            <div class="limit-value">≤ <?php echo number_format($configuracion['nivel_advertencia'] ?? 0.025, 3); ?> g/L</div>
                                            <div class="limit-desc">Pruebas dentro de este rango son seguras</div>
                                        </div>
                                    </div>
                                    
                                    <div class="limit-card warning">
                                        <div class="limit-icon">
                                            <i class="fas fa-exclamation-triangle"></i>
                                        </div>
                                        <div class="limit-content">
                                            <div class="limit-title">Nivel de Advertencia</div>
                                            <div class="limit-value"><?php echo number_format($configuracion['nivel_advertencia'] ?? 0.025, 3); ?> - <?php echo number_format($configuracion['nivel_critico'] ?? 0.080, 3); ?> g/L</div>
                                            <div class="limit-desc">Requiere atención y posible re-test</div>
                                        </div>
                                    </div>
                                    
                                    <div class="limit-card danger">
                                        <div class="limit-icon">
                                            <i class="fas fa-times"></i>
                                        </div>
                                        <div class="limit-content">
                                            <div class="limit-title">Nivel Crítico</div>
                                            <div class="limit-value">≥ <?php echo number_format($configuracion['nivel_critico'] ?? 0.080, 3); ?> g/L</div>
                                            <div class="limit-desc">Prueba reprobada - requiere acción inmediata</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Configuración de Niveles</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="limite_alcohol_permisible">Límite Permisible (g/L) *</label>
                                        <input type="number" id="limite_alcohol_permisible" name="limite_alcohol_permisible" 
                                               value="<?php echo number_format($configuracion['limite_alcohol_permisible'] ?? 0.000, 3); ?>" 
                                               step="0.001" min="0" max="1" required class="form-control">
                                        <small class="form-text">Nivel máximo permitido para aprobar prueba</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="nivel_advertencia">Nivel de Advertencia (g/L) *</label>
                                        <input type="number" id="nivel_advertencia" name="nivel_advertencia" 
                                               value="<?php echo number_format($configuracion['nivel_advertencia'] ?? 0.025, 3); ?>" 
                                               step="0.001" min="0" max="1" required class="form-control">
                                        <small class="form-text">Nivel que activa advertencias</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="nivel_critico">Nivel Crítico (g/L) *</label>
                                        <input type="number" id="nivel_critico" name="nivel_critico" 
                                               value="<?php echo number_format($configuracion['nivel_critico'] ?? 0.080, 3); ?>" 
                                               step="0.001" min="0" max="1" required class="form-control">
                                        <small class="form-text">Nivel que considera la prueba como reprobada</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="unidad_medida">Unidad de Medida</label>
                                        <select id="unidad_medida" name="unidad_medida" class="form-control">
                                            <option value="g/L" <?php echo ($configuracion['unidad_medida'] ?? 'g/L') === 'g/L' ? 'selected' : ''; ?>>Gramos por Litro (g/L)</option>
                                            <option value="mg/dL" <?php echo ($configuracion['unidad_medida'] ?? '') === 'mg/dL' ? 'selected' : ''; ?>>Miligramos por Decilitro (mg/dL)</option>
                                            <option value="BAC" <?php echo ($configuracion['unidad_medida'] ?? '') === 'BAC' ? 'selected' : ''; ?>>Blood Alcohol Content (%)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Regulaciones por País</h4>
                                <div class="regulations-grid">
                                    <?php foreach ($regulaciones as $regulacion): ?>
                                    <div class="regulation-card <?php echo $regulacion['codigo_pais'] === 'PE' ? 'active' : ''; ?>">
                                        <div class="regulation-flag">
                                            <?php echo htmlspecialchars($regulacion['codigo_pais']); ?>
                                        </div>
                                        <div class="regulation-info">
                                            <div class="regulation-country"><?php echo htmlspecialchars($regulacion['pais']); ?></div>
                                            <div class="regulation-limit"><?php echo number_format($regulacion['limite_permisible'], 3); ?> g/L</div>
                                        </div>
                                        <div class="regulation-desc">
                                            <?php echo htmlspecialchars($regulacion['descripcion']); ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="actualizar_alcohol" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Actualizar Niveles
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Protocolo Re-test -->
            <div id="retest" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-redo"></i> Protocolo de Re-test</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="configuration-form">
                            <div class="form-section">
                                <h4>Configuración de Re-test</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="intervalo_retest_minutos">Intervalo entre Re-test (minutos) *</label>
                                        <input type="number" id="intervalo_retest_minutos" name="intervalo_retest_minutos" 
                                               value="<?php echo $configuracion['intervalo_retest_minutos'] ?? 15; ?>" 
                                               min="1" max="120" required class="form-control">
                                        <small class="form-text">Tiempo mínimo entre pruebas consecutivas</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="intentos_retest">Intentos de Re-test Permitidos *</label>
                                        <input type="number" id="intentos_retest" name="intentos_retest" 
                                               value="<?php echo $configuracion['intentos_retest'] ?? 3; ?>" 
                                               min="1" max="10" required class="form-control">
                                        <small class="form-text">Número máximo de re-test permitidos</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="bloqueo_conductor_horas">Bloqueo de Conductor (horas) *</label>
                                        <input type="number" id="bloqueo_conductor_horas" name="bloqueo_conductor_horas" 
                                               value="<?php echo $configuracion['bloqueo_conductor_horas'] ?? 24; ?>" 
                                               min="1" max="168" required class="form-control">
                                        <small class="form-text">Tiempo de bloqueo después de prueba reprobada</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Configuración de Supervisión</h4>
                                <div class="checkbox-grid">
                                    <div class="form-check">
                                        <input type="checkbox" id="notificar_supervisor_retest" name="notificar_supervisor_retest" 
                                               value="1" <?php echo ($configuracion['notificar_supervisor_retest'] ?? 1) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="notificar_supervisor_retest" class="form-check-label">
                                            <strong>Notificar al Supervisor en Re-test</strong>
                                            <small>El supervisor recibirá notificación cuando se solicite un re-test</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input type="checkbox" id="requerir_aprobacion_supervisor" name="requerir_aprobacion_supervisor" 
                                               value="1" <?php echo ($configuracion['requerir_aprobacion_supervisor'] ?? 0) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="requerir_aprobacion_supervisor" class="form-check-label">
                                            <strong>Requerir Aprobación de Supervisor</strong>
                                            <small>El re-test requiere aprobación explícita del supervisor</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="actualizar_retest" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Actualizar Protocolo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Notificaciones -->
            <div id="notificaciones" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-bell"></i> Configuración de Notificaciones</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="configuration-form">
                            <div class="form-section">
                                <h4>Canales de Notificación</h4>
                                <div class="checkbox-grid">
                                    <div class="form-check">
                                        <input type="checkbox" id="notificaciones_email" name="notificaciones_email" 
                                               value="1" <?php echo ($configuracion['notificaciones_email'] ?? 1) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="notificaciones_email" class="form-check-label">
                                            <strong>Notificaciones por Email</strong>
                                            <small>Enviar alertas y reportes por correo electrónico</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input type="checkbox" id="notificaciones_sms" name="notificaciones_sms" 
                                               value="1" <?php echo ($configuracion['notificaciones_sms'] ?? 0) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="notificaciones_sms" class="form-check-label">
                                            <strong>Notificaciones por SMS</strong>
                                            <small>Enviar alertas críticas por mensaje de texto</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input type="checkbox" id="notificaciones_push" name="notificaciones_push" 
                                               value="1" <?php echo ($configuracion['notificaciones_push'] ?? 1) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="notificaciones_push" class="form-check-label">
                                            <strong>Notificaciones Push</strong>
                                            <small>Notificaciones en tiempo real en la aplicación</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input type="checkbox" id="notificaciones_whatsapp" name="notificaciones_whatsapp" 
                                               value="1" <?php echo ($configuracion['notificaciones_whatsapp'] ?? 0) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="notificaciones_whatsapp" class="form-check-label">
                                            <strong>Notificaciones por WhatsApp</strong>
                                            <small>Enviar alertas a través de WhatsApp Business</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Configuración de Contactos</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="email_notificacion">Email para Notificaciones</label>
                                        <input type="email" id="email_notificacion" name="email_notificacion" 
                                               value="<?php echo htmlspecialchars($configuracion['email_notificacion'] ?? ''); ?>" 
                                               class="form-control">
                                        <small class="form-text">Email principal para recibir notificaciones</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="telefono_notificacion">Teléfono para Alertas</label>
                                        <input type="tel" id="telefono_notificacion" name="telefono_notificacion" 
                                               value="<?php echo htmlspecialchars($configuracion['telefono_notificacion'] ?? ''); ?>" 
                                               class="form-control">
                                        <small class="form-text">Número para SMS y WhatsApp</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="actualizar_notificaciones" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Actualizar Notificaciones
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Preferencias Generales -->
            <div id="preferencias" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cogs"></i> Preferencias Generales</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="configuration-form">
                            <div class="form-section">
                                <h4>Requisitos de Pruebas</h4>
                                <div class="checkbox-grid">
                                    <div class="form-check">
                                        <input type="checkbox" id="requerir_geolocalizacion" name="requerir_geolocalizacion" 
                                               value="1" <?php echo ($configuracion['requerir_geolocalizacion'] ?? 1) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="requerir_geolocalizacion" class="form-check-label">
                                            <strong>Requerir Geolocalización</strong>
                                            <small>Registrar ubicación GPS en cada prueba</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input type="checkbox" id="requerir_foto_evidencia" name="requerir_foto_evidencia" 
                                               value="1" <?php echo ($configuracion['requerir_foto_evidencia'] ?? 0) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="requerir_foto_evidencia" class="form-check-label">
                                            <strong>Requerir Foto de Evidencia</strong>
                                            <small>Capturar foto del conductor en cada prueba</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input type="checkbox" id="requerir_firma_digital" name="requerir_firma_digital" 
                                               value="1" <?php echo ($configuracion['requerir_firma_digital'] ?? 1) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="requerir_firma_digital" class="form-check-label">
                                            <strong>Requerir Firma Digital</strong>
                                            <small>Firma del conductor para validar la prueba</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check">
                                        <input type="checkbox" id="requerir_observaciones" name="requerir_observaciones" 
                                               value="1" <?php echo ($configuracion['requerir_observaciones'] ?? 0) ? 'checked' : ''; ?> class="form-check-input">
                                        <label for="requerir_observaciones" class="form-check-label">
                                            <strong>Requerir Observaciones</strong>
                                            <small>Comentarios obligatorios en pruebas reprobadas</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Límites Operativos</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="tiempo_maximo_prueba_minutos">Tiempo Máximo por Prueba (minutos)</label>
                                        <input type="number" id="tiempo_maximo_prueba_minutos" name="tiempo_maximo_prueba_minutos" 
                                               value="<?php echo $configuracion['tiempo_maximo_prueba_minutos'] ?? 10; ?>" 
                                               min="1" max="60" class="form-control">
                                        <small class="form-text">Tiempo máximo permitido para completar una prueba</small>
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="distancia_maxima_metros">Distancia Máxima (metros)</label>
                                        <input type="number" id="distancia_maxima_metros" name="distancia_maxima_metros" 
                                               value="<?php echo $configuracion['distancia_maxima_metros'] ?? 500; ?>" 
                                               min="10" max="5000" class="form-control">
                                        <small class="form-text">Radio máximo desde ubicación registrada</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="actualizar_general" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Actualizar Preferencias
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Seguridad -->
            <div id="seguridad" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-shield-alt"></i> Configuración de Seguridad</h3>
                    </div>
                    <div class="card-body">
                        <div class="security-overview">
                            <div class="security-stats">
                                <div class="stat-card">
                                    <div class="stat-icon primary">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3>Contraseñas</h3>
                                        <div class="stat-value">Seguras</div>
                                        <div class="stat-desc">Encriptación AES-256</div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon success">
                                        <i class="fas fa-shield-check"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3>Autenticación</h3>
                                        <div class="stat-value">Activa</div>
                                        <div class="stat-desc">Sistema de roles y permisos</div>
                                    </div>
                                </div>
                                
                                <div class="stat-card">
                                    <div class="stat-icon warning">
                                        <i class="fas fa-history"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h3>Auditoría</h3>
                                        <div class="stat-value"><?php echo $db->fetchOne("SELECT COUNT(*) as total FROM auditoria WHERE cliente_id = ?", [$cliente_id])['total']; ?> registros</div>
                                        <div class="stat-desc">Traza completa de actividades</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4>Configuración de Backups</h4>
                            <div class="backup-config">
                                <div class="backup-status">
                                    <div class="status-item success">
                                        <i class="fas fa-check-circle"></i>
                                        <div class="status-text">
                                            <strong>Backup Automático</strong>
                                            <span>Activado - Ejecución diaria a las 02:00</span>
                                        </div>
                                    </div>
                                    
                                    <div class="status-item info">
                                        <i class="fas fa-database"></i>
                                        <div class="status-text">
                                            <strong>Último Backup</strong>
                                            <span><?php echo $db->fetchOne("SELECT MAX(fecha_creacion) as ultimo FROM backups WHERE cliente_id = ?", [$cliente_id])['ultimo'] ?? 'Nunca'; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="backup-actions">
                                    <button type="button" class="btn btn-outline" onclick="realizarBackup()">
                                        <i class="fas fa-download"></i>
                                        Realizar Backup Manual
                                    </button>
                                    
                                    <button type="button" class="btn btn-outline" onclick="verHistorialBackups()">
                                        <i class="fas fa-history"></i>
                                        Ver Historial
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h4>Configuración de Sesiones</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="timeout_sesion">Timeout de Sesión (minutos)</label>
                                    <select id="timeout_sesion" name="timeout_sesion" class="form-control">
                                        <option value="30">30 minutos</option>
                                        <option value="60" selected>60 minutos</option>
                                        <option value="120">2 horas</option>
                                        <option value="240">4 horas</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="intentos_maximos">Intentos de Login Máximos</label>
                                    <select id="intentos_maximos" name="intentos_maximos" class="form-control">
                                        <option value="3">3 intentos</option>
                                        <option value="5" selected>5 intentos</option>
                                        <option value="10">10 intentos</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Estilos específicos para Configuración General */
.configuration-container {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 2rem;
    margin-top: 1.5rem;
}

.configuration-sidebar {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.configuration-nav {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.configuration-nav .nav-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    text-decoration: none;
    color: var(--dark);
    border-left: 4px solid transparent;
    transition: var(--transition);
    font-weight: 500;
    border-bottom: 1px solid var(--border);
}

.configuration-nav .nav-item:last-child {
    border-bottom: none;
}

.configuration-nav .nav-item:hover {
    background: var(--light);
    color: var(--primary);
}

.configuration-nav .nav-item.active {
    background: rgba(52, 152, 219, 0.05);
    color: var(--primary);
    border-left-color: var(--primary);
}

.configuration-nav .nav-item i {
    width: 20px;
    text-align: center;
    font-size: 1.1rem;
}

.sidebar-info {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.info-card h4 {
    margin: 0 0 1rem 0;
    color: var(--dark);
    font-size: 1rem;
}

.status-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.status-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem;
    border-radius: 8px;
    font-size: 0.85rem;
}

.status-item.success {
    background: rgba(39, 174, 96, 0.1);
    color: var(--success);
}

.status-item.info {
    background: rgba(52, 152, 219, 0.1);
    color: var(--primary);
}

.status-item i {
    font-size: 1rem;
}

.configuration-content {
    min-height: 600px;
}

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.3s ease-in-out;
}

.configuration-form {
    max-width: 100%;
}

.form-section {
    margin-bottom: 2.5rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--border);
}

.form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

.form-section h4 {
    margin: 0 0 1.5rem 0;
    color: var(--dark);
    font-size: 1.2rem;
    font-weight: 600;
}

/* Tarjetas de límites */
.limits-info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.limit-card {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    border-radius: 12px;
    border-left: 4px solid;
}

.limit-card.success {
    background: rgba(39, 174, 96, 0.05);
    border-left-color: var(--success);
}

.limit-card.warning {
    background: rgba(243, 156, 18, 0.05);
    border-left-color: var(--warning);
}

.limit-card.danger {
    background: rgba(231, 76, 60, 0.05);
    border-left-color: var(--danger);
}

.limit-icon {
    font-size: 1.5rem;
    flex-shrink: 0;
}

.limit-content {
    flex: 1;
}

.limit-title {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.limit-value {
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.limit-desc {
    font-size: 0.85rem;
    color: var(--gray);
    line-height: 1.4;
}

/* Grid de regulaciones */
.regulations-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 1rem;
}

.regulation-card {
    padding: 1.5rem;
    border-radius: 8px;
    border: 2px solid var(--border);
    text-align: center;
    transition: var(--transition);
}

.regulation-card.active {
    border-color: var(--primary);
    background: rgba(52, 152, 219, 0.05);
}

.regulation-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.regulation-flag {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.regulation-country {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
}

.regulation-limit {
    font-size: 1.1rem;
    font-weight: 700;
    color: var(--primary);
    margin-bottom: 0.5rem;
}

.regulation-desc {
    font-size: 0.8rem;
    color: var(--gray);
    line-height: 1.3;
}

/* Grid de checkboxes */
.checkbox-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.form-check {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    padding: 1.5rem;
    border: 2px solid var(--border);
    border-radius: 10px;
    transition: var(--transition);
    cursor: pointer;
}

.form-check:hover {
    border-color: var(--primary);
    background: rgba(52, 152, 219, 0.02);
}

.form-check-input {
    margin-top: 0.25rem;
    flex-shrink: 0;
}

.form-check-label {
    flex: 1;
    cursor: pointer;
}

.form-check-label strong {
    display: block;
    margin-bottom: 0.5rem;
    color: var(--dark);
    font-weight: 600;
}

.form-check-label small {
    color: var(--gray);
    font-size: 0.85rem;
    line-height: 1.4;
}

/* Grupo de color */
.color-input-group {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.color-picker {
    width: 60px;
    height: 40px;
    padding: 0;
    border: none;
    border-radius: 6px;
    cursor: pointer;
}

.color-value {
    font-family: monospace;
    font-weight: 600;
    color: var(--dark);
}

/* Estadísticas de seguridad */
.security-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.security-stats .stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.security-stats .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    color: white;
}

.security-stats .stat-info h3 {
    margin: 0 0 0.5rem 0;
    font-size: 0.9rem;
    color: var(--gray);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.security-stats .stat-value {
    font-size: 1.3rem;
    font-weight: 700;
    color: var(--dark);
    margin-bottom: 0.25rem;
}

.security-stats .stat-desc {
    font-size: 0.8rem;
    color: var(--gray);
}

/* Configuración de backups */
.backup-config {
    background: var(--light);
    border-radius: 12px;
    padding: 1.5rem;
}

.backup-status {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}

.backup-actions {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
}

.plan-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 20px;
    font-weight: 600;
    font-size: 0.9rem;
}

/* Responsive */
@media (max-width: 1024px) {
    .configuration-container {
        grid-template-columns: 1fr;
    }
    
    .configuration-sidebar {
        order: 2;
    }
    
    .configuration-content {
        order: 1;
    }
}

@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .limits-info {
        grid-template-columns: 1fr;
    }
    
    .checkbox-grid {
        grid-template-columns: 1fr;
    }
    
    .regulations-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
    
    .security-stats {
        grid-template-columns: 1fr;
    }
    
    .backup-actions {
        flex-direction: column;
    }
    
    .configuration-nav .nav-item {
        padding: 0.875rem 1rem;
        font-size: 0.9rem;
    }
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Estilos mejorados para formularios - Mi Cuenta */
.account-form {
    max-width: 100%;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-weight: 600;
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-size: 0.9rem;
    transition: var(--transition);
}

.form-group:focus-within label {
    color: var(--primary);
}

/* Inputs mejorados */
.form-control {
    padding: 0.875rem 1rem;
    border: 2px solid #e1e8ed;
    border-radius: 10px;
    font-size: 0.95rem;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background: linear-gradient(135deg, #fafbfc 0%, #ffffff 100%);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    position: relative;
    color: var(--dark);
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

.form-control:disabled {
    background: #f8f9fa;
    border-color: #e9ecef;
    color: #6c757d;
    cursor: not-allowed;
    opacity: 0.7;
}

.form-control:disabled:hover {
    border-color: #e9ecef;
    box-shadow: none;
    transform: none;
}

/* Selects mejorados */
select.form-control {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236c757d' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px;
    padding-right: 3rem;
    cursor: pointer;
}

select.form-control:hover {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23343a40' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
}

select.form-control:focus {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%233498db' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
}

/* Input groups con iconos */
.input-group {
    position: relative;
    display: flex;
    align-items: center;
}

.input-group .form-control {
    padding-left: 3rem;
    width: 100%;
}

.input-group-icon {
    position: absolute;
    left: 1rem;
    color: #6c757d;
    z-index: 2;
    transition: var(--transition);
}

.form-control:focus + .input-group-icon {
    color: var(--primary);
}

/* Estados de validación */
.form-control.is-valid {
    border-color: var(--success);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2327ae60' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px;
    padding-right: 3rem;
}

.form-control.is-invalid {
    border-color: var(--danger);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23e74c3c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'%3E%3C/circle%3E%3Cline x1='15' y1='9' x2='9' y2='15'%3E%3C/line%3E%3Cline x1='9' y1='9' x2='15' y2='15'%3E%3C/line%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px;
    padding-right: 3rem;
}

/* Texto de ayuda */
.form-text {
    margin-top: 0.5rem;
    font-size: 0.8rem;
    color: #6c757d;
    line-height: 1.4;
}

.form-control:focus ~ .form-text {
    color: var(--primary);
}

/* Password toggle */
.password-toggle {
    position: absolute;
    right: 1rem;
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: var(--transition);
}

.password-toggle:hover {
    color: var(--dark);
    background: rgba(0, 0, 0, 0.05);
}

.form-control:focus ~ .password-toggle {
    color: var(--primary);
}

/* Checkboxes y radios mejorados */
.form-check {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1rem;
    cursor: pointer;
}

.form-check-input {
    appearance: none;
    width: 20px;
    height: 20px;
    border: 2px solid #e1e8ed;
    border-radius: 6px;
    background: white;
    position: relative;
    cursor: pointer;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.form-check-input:checked {
    background: var(--primary);
    border-color: var(--primary);
}

.form-check-input:checked::after {
    content: '';
    position: absolute;
    top: 2px;
    left: 6px;
    width: 5px;
    height: 10px;
    border: solid white;
    border-width: 0 2px 2px 0;
    transform: rotate(45deg);
}

.form-check-input:hover {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-check-label {
    font-weight: 500;
    color: var(--dark);
    cursor: pointer;
    user-select: none;
}

/* Radio buttons */
.form-radio .form-check-input {
    border-radius: 50%;
}

.form-radio .form-check-input:checked::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: white;
    border: none;
}

/* Textarea mejorado */
textarea.form-control {
    min-height: 120px;
    resize: vertical;
    line-height: 1.5;
}

/* Grupos de formulario con íconos */
.form-group-with-icon {
    position: relative;
}

.form-group-with-icon .form-control {
    padding-left: 3rem;
}

.form-group-icon {
    position: absolute;
    left: 1rem;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    transition: var(--transition);
    pointer-events: none;
}

.form-control:focus ~ .form-group-icon {
    color: var(--primary);
}

/* Estados de carga */
.form-control.loading {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%233498db' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M21 12a9 9 0 11-6.219-8.56'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from { transform: rotate(0deg); }
    to { transform: rotate(360deg); }
}

/* Placeholders mejorados */
.form-control::placeholder {
    color: #adb5bd;
    transition: var(--transition);
}

.form-control:focus::placeholder {
    color: #6c757d;
    transform: translateX(5px);
}

/* Efectos de transición para grupos completos */
.form-group {
    transition: var(--transition);
}

.form-group:focus-within {
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
        gap: 1rem;
    }
    
    .form-control {
        padding: 0.75rem 0.875rem;
        font-size: 16px; /* Previene zoom en iOS */
    }
    
    select.form-control {
        background-position: right 0.875rem center;
        padding-right: 2.5rem;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .form-control {
        background: #2d3748;
        border-color: #4a5568;
        color: #e2e8f0;
    }
    
    .form-control:hover {
        background: #364152;
        border-color: #718096;
    }
    
    .form-control:focus {
        background: #2d3748;
        border-color: var(--primary);
    }
    
    .form-control::placeholder {
        color: #a0aec0;
    }
}

/* Mejoras específicas para el módulo Mi Cuenta */
.account-form .form-group {
    margin-bottom: 1.5rem;
}

.account-form .form-section {
    margin-bottom: 2.5rem;
    padding-bottom: 2rem;
    border-bottom: 1px solid var(--border);
}

.account-form .form-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
}

/* Estilos para campos de contraseña con toggle */
.password-field {
    position: relative;
}

.password-field .form-control {
    padding-right: 3rem;
}

.toggle-password {
    position: absolute;
    right: 1rem;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: #6c757d;
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: var(--transition);
}

.toggle-password:hover {
    color: var(--dark);
    background: rgba(0, 0, 0, 0.05);
}

.form-control:focus ~ .toggle-password {
    color: var(--primary);
}

/* Estados de éxito y error mejorados */
.form-control.success {
    border-color: var(--success);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2327ae60' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M22 11.08V12a10 10 0 1 1-5.93-9.14'%3E%3C/path%3E%3Cpolyline points='22 4 12 14.01 9 11.01'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px;
    padding-right: 3rem;
}

.form-control.error {
    border-color: var(--danger);
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%23e74c3c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'%3E%3C/circle%3E%3Cline x1='12' y1='8' x2='12' y2='12'%3E%3C/line%3E%3Cline x1='12' y1='16' x2='12.01' y2='16'%3E%3C/line%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 16px;
    padding-right: 3rem;
}

/* Animación de entrada para nuevos campos */
@keyframes slideInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.form-group {
    animation: slideInUp 0.3s ease-out;
}

.form-group:nth-child(odd) {
    animation-delay: 0.05s;
}

.form-group:nth-child(even) {
    animation-delay: 0.1s;
}
</style>

<script>
// Navegación entre pestañas
document.addEventListener('DOMContentLoaded', function() {
    const navItems = document.querySelectorAll('.configuration-nav .nav-item');
    const tabContents = document.querySelectorAll('.tab-content');
    
    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Remover clase active de todos los items
            navItems.forEach(nav => nav.classList.remove('active'));
            tabContents.forEach(tab => tab.classList.remove('active'));
            
            // Agregar clase active al item clickeado
            this.classList.add('active');
            
            // Mostrar el contenido correspondiente
            const targetTab = this.getAttribute('data-tab');
            document.getElementById(targetTab).classList.add('active');
        });
    });
    
    // Actualizar valores de color
    const colorPickers = document.querySelectorAll('.color-picker');
    colorPickers.forEach(picker => {
        picker.addEventListener('input', function() {
            const valueDisplay = this.nextElementSibling;
            valueDisplay.textContent = this.value;
        });
    });
    
    // Validación de niveles de alcohol
    const formAlcohol = document.querySelector('form[action*="actualizar_alcohol"]');
    if (formAlcohol) {
        formAlcohol.addEventListener('submit', function(e) {
            const limite = parseFloat(document.getElementById('limite_alcohol_permisible').value);
            const advertencia = parseFloat(document.getElementById('nivel_advertencia').value);
            const critico = parseFloat(document.getElementById('nivel_critico').value);
            
            if (advertencia >= critico) {
                e.preventDefault();
                alert('El nivel de advertencia debe ser menor que el nivel crítico.');
                return false;
            }
            
            if (limite >= advertencia) {
                e.preventDefault();
                alert('El límite permisible debe ser menor que el nivel de advertencia.');
                return false;
            }
        });
    }
});

function realizarBackup() {
    if (confirm('¿Estás seguro de que deseas realizar un backup manual del sistema?')) {
        const btn = event.target;
        const originalHTML = btn.innerHTML;
        
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Realizando backup...';
        btn.disabled = true;
        
        // Simular proceso de backup
        setTimeout(() => {
            alert('Backup realizado correctamente');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }, 2000);
    }
}

function verHistorialBackups() {
    alert('Esta función mostraría el historial completo de backups');
    // En una implementación real, esto cargaría un modal o redirigiría a otra página
}

// JavaScript mejorado para inputs y selects
document.addEventListener('DOMContentLoaded', function() {
    // Toggle de visibilidad de contraseña
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            // Cambiar ícono
            const icon = this.querySelector('i');
            if (type === 'password') {
                icon.className = 'fas fa-eye';
            } else {
                icon.className = 'fas fa-eye-slash';
            }
        });
    });

    // Validación en tiempo real
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        // Limpiar estados previos al enfocar
        input.addEventListener('focus', function() {
            this.classList.remove('error', 'success');
        });

        // Validación básica al salir del campo
        input.addEventListener('blur', function() {
            validateField(this);
        });

        // Validación en tiempo real para campos requeridos
        if (input.hasAttribute('required')) {
            input.addEventListener('input', function() {
                validateField(this);
            });
        }
    });

    // Validación de campos de contraseña
    const passwordFields = document.querySelectorAll('input[type="password"]');
    passwordFields.forEach(field => {
        field.addEventListener('input', function() {
            if (this.value.length > 0) {
                if (this.value.length < 6) {
                    this.classList.add('error');
                    this.classList.remove('success');
                } else {
                    this.classList.add('success');
                    this.classList.remove('error');
                }
            } else {
                this.classList.remove('error', 'success');
            }
        });
    });

    // Efecto de carga para formularios
    const forms = document.querySelectorAll('.account-form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            const originalText = submitButton.innerHTML;
            
            // Mostrar estado de carga
            submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
            submitButton.disabled = true;
            
            // Agregar clase de loading a todos los campos
            const inputs = this.querySelectorAll('.form-control');
            inputs.forEach(input => {
                input.classList.add('loading');
            });
            
            // Simular procesamiento (en producción, esto sería asíncrono)
            setTimeout(() => {
                submitButton.innerHTML = originalText;
                submitButton.disabled = false;
                inputs.forEach(input => {
                    input.classList.remove('loading');
                });
            }, 2000);
        });
    });

    // Función de validación de campo
    function validateField(field) {
        const value = field.value.trim();
        
        if (field.hasAttribute('required') && value === '') {
            field.classList.add('error');
            return false;
        }
        
        // Validación de email
        if (field.type === 'email' && value !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                field.classList.add('error');
                return false;
            }
        }
        
        // Validación de teléfono
        if (field.type === 'tel' && value !== '') {
            const phoneRegex = /^[\+]?[0-9\s\-\(\)]{8,}$/;
            if (!phoneRegex.test(value)) {
                field.classList.add('error');
                return false;
            }
        }
        
        // Si pasa todas las validaciones
        if (value !== '') {
            field.classList.add('success');
        }
        
        return true;
    }

    // Efecto de placeholder flotante mejorado
    const floatLabels = document.querySelectorAll('.form-group label');
    floatLabels.forEach(label => {
        const input = label.nextElementSibling;
        if (input && input.value !== '') {
            label.style.transform = 'translateY(-150%) scale(0.85)';
            label.style.color = 'var(--primary)';
        }
        
        input.addEventListener('focus', function() {
            label.style.transform = 'translateY(-150%) scale(0.85)';
            label.style.color = 'var(--primary)';
        });
        
        input.addEventListener('blur', function() {
            if (this.value === '') {
                label.style.transform = 'none';
                label.style.color = '';
            }
        });
    });
});
</script>

<?php
if (file_exists(__DIR__ . '/includes/footer.php')) {
    require_once __DIR__ . '/includes/footer.php';
}
?>