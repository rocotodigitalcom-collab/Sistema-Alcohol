<?php
// ============================================================================
// PROTOCOLO COMPLETO DE ALCOHOLEMIA - VERSIÓN CORREGIDA
// ============================================================================
// IMPORTANTE: No incluir header.php aquí para permitir redirecciones
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// CARGAR CONFIGURACIÓN
$config = $db->fetchOne("SELECT limite_alcohol_permisible, nivel_advertencia, nivel_critico FROM configuraciones WHERE cliente_id = ?", [$cliente_id]);

// VARIABLES DE ESTADO
$mensaje_exito = null;
$mensaje_error = null;

// ============================================================================
// PROCESAR FORMULARIOS POST (ANTES DEL HEADER PARA PERMITIR REDIRECCIONES)
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // PASO 1: GUARDAR OPERACIÓN
    if (isset($_POST['guardar_operacion'])) {
        try {
            $ubicacion_id = $_POST['ubicacion_id'] ?? null;
            $operador_id = $_POST['operador_id'] ?? null;
            if (empty($ubicacion_id) || empty($operador_id)) throw new Exception("Complete todos los campos obligatorios");

            $operacion_id = $_POST['operacion_id'] ?? null;
            $datos = [$ubicacion_id, trim($_POST['lugar_pruebas'] ?? ''), $_POST['fecha'] ?? date('Y-m-d'), $_POST['plan_motivo'] ?? 'diario', $_POST['hora_inicio'] ?? null, $_POST['hora_cierre'] ?? null, $operador_id, $_POST['estado_operacion'] ?? 'planificada'];

            if ($operacion_id) {
                $db->execute("UPDATE operaciones SET ubicacion_id=?, lugar_pruebas=?, fecha=?, plan_motivo=?, hora_inicio=?, hora_cierre=?, operador_id=?, estado=? WHERE id=? AND cliente_id=?", array_merge($datos, [$operacion_id, $cliente_id]));
            } else {
                $db->execute("INSERT INTO operaciones (cliente_id, ubicacion_id, lugar_pruebas, fecha, plan_motivo, hora_inicio, hora_cierre, operador_id, estado) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)", array_merge([$cliente_id], $datos));
                $operacion_id = $db->lastInsertId();
            }

            header("Location: protocolo_completo.php?operacion_id=$operacion_id&tab=checklist&msg=" . urlencode("✅ PASO 1 COMPLETADO - Operación #$operacion_id guardada. Ahora complete el CHECKLIST."));
            exit;
        } catch (Exception $e) { $mensaje_error = $e->getMessage(); }
    }

    // PASO 2: GUARDAR CHECKLIST
    if (isset($_POST['guardar_checklist'])) {
        try {
            $operacion_id = $_POST['operacion_id'] ?? null;
            $alcoholimetro_id = $_POST['alcoholimetro_id'] ?? null;
            if (empty($operacion_id) || empty($alcoholimetro_id)) throw new Exception("Seleccione un alcoholímetro");

            $checklist_id = $_POST['checklist_id'] ?? null;
            $campos = [$alcoholimetro_id, $_POST['estado_alcoholimetro'] ?? 'conforme', isset($_POST['fecha_hora_actualizada'])?1:0, isset($_POST['bateria_cargada'])?1:0, isset($_POST['enciende_condiciones'])?1:0, isset($_POST['impresora_operativa'])?1:0, isset($_POST['boquillas'])?1:0, isset($_POST['documentacion_disponible'])?1:0, isset($_POST['huellero'])?1:0, isset($_POST['lapicero'])?1:0];

            if ($checklist_id) {
                $db->execute("UPDATE checklists_operacion SET alcoholimetro_id=?, estado_alcoholimetro=?, fecha_hora_actualizada=?, bateria_cargada=?, enciende_condiciones=?, impresora_operativa=?, boquillas=?, documentacion_disponible=?, huellero=?, lapicero=? WHERE id=?", array_merge($campos, [$checklist_id]));
            } else {
                $db->execute("INSERT INTO checklists_operacion (operacion_id, alcoholimetro_id, estado_alcoholimetro, fecha_hora_actualizada, bateria_cargada, enciende_condiciones, impresora_operativa, boquillas, documentacion_disponible, huellero, lapicero) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", array_merge([$operacion_id], $campos));
            }

            $db->execute("UPDATE operaciones SET estado = 'en_proceso' WHERE id = ? AND estado = 'planificada'", [$operacion_id]);
            
            header("Location: protocolo_completo.php?operacion_id=$operacion_id&tab=acta&msg=" . urlencode("✅ PASO 2 COMPLETADO - Checklist guardado. Ahora registre al CONDUCTOR."));
            exit;
        } catch (Exception $e) { $mensaje_error = $e->getMessage(); }
    }

    // PASO 3: GUARDAR ACTA
    if (isset($_POST['guardar_acta'])) {
        try {
            $operacion_id = $_POST['operacion_id'] ?? null;
            $conductor_id = $_POST['conductor_id'] ?? null;
            if (empty($conductor_id)) throw new Exception("Debe seleccionar un conductor");

            $acta_id = $_POST['acta_id'] ?? null;
            $protocolo_id = $_POST['protocolo_id'] ?? null;

            if ($acta_id && $protocolo_id) {
                $db->execute("UPDATE actas_consentimiento SET conductor_id=?, objetivo_prueba=?, firma_conductor=? WHERE id=?", [$conductor_id, $_POST['objetivo_prueba'] ?? 'preventivo', $_POST['firma_conductor'] ?? '', $acta_id]);
                header("Location: protocolo_completo.php?protocolo_id=$protocolo_id&tab=encuesta&msg=" . urlencode("✅ Acta actualizada. Continue con la ENCUESTA."));
                exit;
            } else {
                $db->execute("INSERT INTO actas_consentimiento (operacion_id, conductor_id, objetivo_prueba, firma_conductor) VALUES (?, ?, ?, ?)", [$operacion_id, $conductor_id, $_POST['objetivo_prueba'] ?? 'preventivo', $_POST['firma_conductor'] ?? '']);
                $acta_id = $db->lastInsertId();
                $db->execute("INSERT INTO pruebas_protocolo (operacion_id, conductor_id, acta_id, paso_actual) VALUES (?, ?, ?, 3)", [$operacion_id, $conductor_id, $acta_id]);
                $protocolo_id = $db->lastInsertId();
                header("Location: protocolo_completo.php?protocolo_id=$protocolo_id&tab=encuesta&msg=" . urlencode("✅ PASO 3 COMPLETADO - Protocolo #$protocolo_id creado. Ahora complete la ENCUESTA."));
                exit;
            }
        } catch (Exception $e) { $mensaje_error = $e->getMessage(); }
    }

    // PASO 4: GUARDAR ENCUESTA
    if (isset($_POST['guardar_encuesta'])) {
        try {
            $acta_id = $_POST['acta_id'] ?? null;
            $protocolo_id = $_POST['protocolo_id'] ?? null;
            if (empty($acta_id) || empty($protocolo_id)) throw new Exception("Error: Sin acta o protocolo activo");

            $encuesta_id = $_POST['encuesta_id'] ?? null;
            $enfermedades = json_encode(['diabetes' => isset($_POST['enf_diabetes']), 'hipertension' => isset($_POST['enf_hipertension']), 'otros' => $_POST['enf_otros'] ?? '']);
            $elementos_boca = json_encode(['chiclets' => isset($_POST['boca_chiclets']), 'caramelos' => isset($_POST['boca_caramelos']), 'pastillas_mentoladas' => isset($_POST['boca_pastillas']), 'piercing_brackets' => isset($_POST['boca_piercing']), 'otros' => $_POST['boca_otros'] ?? '']);
            $actividades = json_encode(['fumado' => isset($_POST['act_fumado']), 'eructado' => isset($_POST['act_eructado']), 'splash_bucal' => isset($_POST['act_splash']), 'vomitado' => isset($_POST['act_vomitado']), 'enjuague_bucal' => isset($_POST['act_enjuague']), 'otros' => $_POST['act_otros'] ?? '']);

            if ($encuesta_id) {
                $db->execute("UPDATE encuestas_preliminares SET enfermedades=?, elementos_boca=?, actividades_recientes=?, observaciones=? WHERE id=?", [$enfermedades, $elementos_boca, $actividades, $_POST['encuesta_observaciones'] ?? '', $encuesta_id]);
            } else {
                $db->execute("INSERT INTO encuestas_preliminares (acta_id, enfermedades, elementos_boca, actividades_recientes, observaciones) VALUES (?, ?, ?, ?, ?)", [$acta_id, $enfermedades, $elementos_boca, $actividades, $_POST['encuesta_observaciones'] ?? '']);
                $encuesta_id = $db->lastInsertId();
                $db->execute("UPDATE pruebas_protocolo SET encuesta_id = ?, paso_actual = 4 WHERE id = ?", [$encuesta_id, $protocolo_id]);
            }

            header("Location: protocolo_completo.php?protocolo_id=$protocolo_id&tab=prueba&msg=" . urlencode("✅ PASO 4 COMPLETADO - Encuesta guardada. Ahora realice la PRUEBA."));
            exit;
        } catch (Exception $e) { $mensaje_error = $e->getMessage(); }
    }

    // PASO 5: GUARDAR PRUEBA
    if (isset($_POST['guardar_prueba'])) {
        try {
            $protocolo_id = $_POST['protocolo_id'] ?? null;
            $conductor_id = $_POST['conductor_id'] ?? null;
            $alcoholimetro_id = $_POST['alcoholimetro_id'] ?? null;
            if (empty($protocolo_id) || empty($conductor_id)) throw new Exception("Datos incompletos para la prueba");

            $nivel_alcohol = floatval($_POST['nivel_alcohol'] ?? 0);
            $limite = floatval($config['limite_alcohol_permisible'] ?? 0);
            $resultado = ($nivel_alcohol > $limite) ? 'reprobado' : 'aprobado';
            $prueba_id = $_POST['prueba_id'] ?? null;

            if ($prueba_id) {
                $db->execute("UPDATE pruebas SET nivel_alcohol=?, resultado=?, observaciones=? WHERE id=?", [$nivel_alcohol, $resultado, $_POST['observaciones_prueba'] ?? '', $prueba_id]);
            } else {
                $db->execute("INSERT INTO pruebas (cliente_id, alcoholimetro_id, conductor_id, supervisor_id, vehiculo_id, nivel_alcohol, limite_permisible, resultado, observaciones, latitud, longitud) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [$cliente_id, $alcoholimetro_id, $conductor_id, $user_id, $_POST['vehiculo_id'] ?: null, $nivel_alcohol, $limite, $resultado, $_POST['observaciones_prueba'] ?? '', $_POST['latitud'] ?? null, $_POST['longitud'] ?? null]);
                $prueba_id = $db->lastInsertId();
                $completada = ($resultado == 'aprobado') ? 1 : 0;
                $db->execute("UPDATE pruebas_protocolo SET prueba_alcohol_id = ?, paso_actual = 5, completada = ? WHERE id = ?", [$prueba_id, $completada, $protocolo_id]);
            }

            if ($resultado == 'aprobado') {
                header("Location: protocolo_completo.php?protocolo_id=$protocolo_id&tab=prueba&msg=" . urlencode("✅ RESULTADO: NEGATIVO ($nivel_alcohol g/L) - PROTOCOLO FINALIZADO."));
            } else {
                header("Location: protocolo_completo.php?protocolo_id=$protocolo_id&tab=widmark&msg=" . urlencode("⚠️ RESULTADO: POSITIVO ($nivel_alcohol g/L) - Continúe con WIDMARK."));
            }
            exit;
        } catch (Exception $e) { $mensaje_error = $e->getMessage(); }
    }

    // PASO 6: AGREGAR WIDMARK
    if (isset($_POST['agregar_widmark'])) {
        try {
            $prueba_id = $_POST['prueba_id'] ?? null;
            $protocolo_id = $_POST['protocolo_id'] ?? null;
            if (empty($prueba_id) || empty($protocolo_id)) throw new Exception("No hay prueba o protocolo activo");

            $bac = floatval($_POST['widmark_bac'] ?? 0);
            $tiempo = intval($_POST['widmark_tiempo'] ?? 0);
            
            $db->execute("INSERT INTO registros_widmark (prueba_id, hora, tiempo_minutos, bac, observacion, comportamiento_curva) VALUES (?, ?, ?, ?, ?, ?)", 
                [$prueba_id, $_POST['widmark_hora'], $tiempo, $bac, $_POST['widmark_observacion'] ?? '', $_POST['widmark_comportamiento'] ?? 'inicio']);

            $db->execute("UPDATE pruebas_protocolo SET paso_actual = 6 WHERE id = ?", [$protocolo_id]);
            
            $total = $db->fetchOne("SELECT COUNT(*) as total FROM registros_widmark WHERE prueba_id = ?", [$prueba_id]);
            header("Location: protocolo_completo.php?protocolo_id=$protocolo_id&tab=widmark&msg=" . urlencode("✅ Registro Widmark #" . $total['total'] . " agregado (BAC: $bac g/L)."));
            exit;
        } catch (Exception $e) { $mensaje_error = $e->getMessage(); }
    }

    // PASO 7: GUARDAR INFORME
    if (isset($_POST['guardar_informe'])) {
        try {
            $prueba_id = $_POST['prueba_id'] ?? null;
            $protocolo_id = $_POST['protocolo_id'] ?? null;
            if (empty($prueba_id) || empty($protocolo_id)) throw new Exception("Datos incompletos");

            $observaciones_examinado = json_encode(['seguridad_si_mismo' => isset($_POST['obs_seguridad']), 'euforia' => isset($_POST['obs_euforia']), 'trastornos_equilibrio' => isset($_POST['obs_equilibrio']), 'disminucion_autocritica' => isset($_POST['obs_autocritica']), 'perturbaciones_psicosensoriales' => isset($_POST['obs_psicosensoriales']), 'otros' => $_POST['obs_otros'] ?? '']);
            $adjuntos = json_encode(['ticket_alcoholimetro' => isset($_POST['adj_ticket']), 'registro_digital' => isset($_POST['adj_registro']), 'grabacion' => isset($_POST['adj_grabacion'])]);
            $informe_id = $_POST['informe_id'] ?? null;

            if ($informe_id) {
                $db->execute("UPDATE informes_positivos SET condicion_ambiental=?, observaciones_examinado=?, comentarios_accion=?, conclusion=?, adjuntos=? WHERE id=?", [$_POST['condicion_ambiental'] ?? 'controlado', $observaciones_examinado, $_POST['comentarios_accion'] ?? '', $_POST['conclusion'] ?? '', $adjuntos, $informe_id]);
            } else {
                $db->execute("INSERT INTO informes_positivos (prueba_id, condicion_ambiental, observaciones_examinado, comentarios_accion, conclusion, adjuntos) VALUES (?, ?, ?, ?, ?, ?)", [$prueba_id, $_POST['condicion_ambiental'] ?? 'controlado', $observaciones_examinado, $_POST['comentarios_accion'] ?? '', $_POST['conclusion'] ?? '', $adjuntos]);
                $informe_id = $db->lastInsertId();
                $db->execute("UPDATE pruebas_protocolo SET informe_positivo_id = ?, paso_actual = 7, completada = 1 WHERE id = ?", [$informe_id, $protocolo_id]);
            }

            header("Location: protocolo_completo.php?protocolo_id=$protocolo_id&tab=informe&msg=" . urlencode("✅ PROTOCOLO COMPLETADO - Informe guardado correctamente."));
            exit;
        } catch (Exception $e) { $mensaje_error = $e->getMessage(); }
    }
}

// Mostrar mensaje de URL si existe
if (isset($_GET['msg'])) {
    $mensaje_exito = $_GET['msg'];
}

// ============================================================================
// CARGAR DATOS PARA LA PÁGINA
// ============================================================================
$ubicaciones = $db->fetchAll("SELECT id, nombre_ubicacion, tipo FROM ubicaciones_cliente WHERE cliente_id = ? AND estado = 1 ORDER BY tipo, nombre_ubicacion", [$cliente_id]);
$alcoholimetros = $db->fetchAll("SELECT id, numero_serie, nombre_activo, marca, modelo FROM alcoholimetros WHERE cliente_id = ? AND estado = 'activo' ORDER BY nombre_activo", [$cliente_id]);
$operadores = $db->fetchAll("SELECT id, nombre, apellido, dni, rol FROM usuarios WHERE cliente_id = ? AND rol IN ('supervisor', 'operador', 'admin') AND estado = 1 ORDER BY nombre", [$cliente_id]);
$conductores = $db->fetchAll("SELECT id, nombre, apellido, dni FROM usuarios WHERE cliente_id = ? AND rol = 'conductor' AND estado = 1 ORDER BY nombre", [$cliente_id]);
$vehiculos = $db->fetchAll("SELECT id, placa, marca, modelo FROM vehiculos WHERE cliente_id = ? AND estado = 'activo'", [$cliente_id]);
$empresa = $db->fetchOne("SELECT nombre_empresa FROM clientes WHERE id = ?", [$cliente_id]);

// VARIABLES DE ESTADO
$operacion_activa = null;
$checklist_activo = null;
$protocolo_activo = null;
$acta_activa = null;
$encuesta_activa = null;
$prueba_activa = null;
$registros_widmark = [];
$informe_activo = null;

// CARGAR DATOS SEGÚN PARÁMETROS DE URL
if (isset($_GET['operacion_id'])) {
    $op_id = intval($_GET['operacion_id']);
    $operacion_activa = $db->fetchOne("SELECT * FROM operaciones WHERE id = ? AND cliente_id = ?", [$op_id, $cliente_id]);
    if ($operacion_activa) {
        $checklist_activo = $db->fetchOne("SELECT * FROM checklists_operacion WHERE operacion_id = ?", [$op_id]);
    }
}

if (isset($_GET['protocolo_id'])) {
    $prot_id = intval($_GET['protocolo_id']);
    $protocolo_activo = $db->fetchOne("SELECT pp.*, CONCAT(u.nombre, ' ', u.apellido) as conductor_nombre, u.dni as conductor_dni FROM pruebas_protocolo pp LEFT JOIN usuarios u ON pp.conductor_id = u.id WHERE pp.id = ?", [$prot_id]);
    
    if ($protocolo_activo) {
        $operacion_activa = $db->fetchOne("SELECT * FROM operaciones WHERE id = ?", [$protocolo_activo['operacion_id']]);
        $checklist_activo = $db->fetchOne("SELECT * FROM checklists_operacion WHERE operacion_id = ?", [$protocolo_activo['operacion_id']]);
        if ($protocolo_activo['acta_id']) $acta_activa = $db->fetchOne("SELECT * FROM actas_consentimiento WHERE id = ?", [$protocolo_activo['acta_id']]);
        if ($protocolo_activo['encuesta_id']) $encuesta_activa = $db->fetchOne("SELECT * FROM encuestas_preliminares WHERE id = ?", [$protocolo_activo['encuesta_id']]);
        if ($protocolo_activo['prueba_alcohol_id']) {
            $prueba_activa = $db->fetchOne("SELECT * FROM pruebas WHERE id = ?", [$protocolo_activo['prueba_alcohol_id']]);
            $registros_widmark = $db->fetchAll("SELECT * FROM registros_widmark WHERE prueba_id = ? ORDER BY tiempo_minutos ASC", [$protocolo_activo['prueba_alcohol_id']]);
        }
        if ($protocolo_activo['informe_positivo_id']) $informe_activo = $db->fetchOne("SELECT * FROM informes_positivos WHERE id = ?", [$protocolo_activo['informe_positivo_id']]);
    }
}

// Cargar operaciones recientes
$operaciones = $db->fetchAll("SELECT o.*, u.nombre_ubicacion, CONCAT(op.nombre, ' ', op.apellido) as operador_nombre, (SELECT COUNT(*) FROM pruebas_protocolo pp WHERE pp.operacion_id = o.id) as total_pruebas FROM operaciones o LEFT JOIN ubicaciones_cliente u ON o.ubicacion_id = u.id LEFT JOIN usuarios op ON o.operador_id = op.id WHERE o.cliente_id = ? ORDER BY o.fecha DESC LIMIT 15", [$cliente_id]);

// Determinar tab activo
$tab_activo = $_GET['tab'] ?? 'operacion';

// ============================================================================
// AHORA SÍ INCLUIR EL HEADER (después de procesar POST y redirecciones)
// ============================================================================
$page_title = 'Protocolo Completo de Alcoholemia';
$breadcrumbs = ['index.php' => 'Dashboard', 'protocolo_completo.php' => 'Protocolo Completo'];
require_once __DIR__ . '/includes/header.php';
?>

<div class="content-body">
    <div class="page-header">
        <div>
            <h1><i class="fas fa-clipboard-check"></i> Protocolo Completo de Alcoholemia</h1>
            <p>Sistema paso a paso para control de alcoholemia laboral</p>
        </div>
        <a href="protocolo_completo.php" class="btn btn-primary"><i class="fas fa-plus"></i> Nueva Operación</a>
    </div>

    <?php if ($mensaje_exito): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i><div><strong>¡Éxito!</strong><p><?php echo htmlspecialchars($mensaje_exito); ?></p></div></div>
    <?php endif; ?>

    <?php if ($mensaje_error): ?>
    <div class="alert alert-danger"><i class="fas fa-times-circle"></i><div><strong>Error</strong><p><?php echo htmlspecialchars($mensaje_error); ?></p></div></div>
    <?php endif; ?>

    <div class="tabs-container">
        <div class="tabs-header">
            <button class="tab-btn <?php echo $tab_activo=='operacion'?'active':''; ?>" data-tab="operacion">
                <span class="tab-num <?php echo $operacion_activa?'done':''; ?>">1</span><span>Operación</span>
            </button>
            <button class="tab-btn <?php echo $tab_activo=='checklist'?'active':''; ?>" data-tab="checklist" <?php echo !$operacion_activa?'disabled':''; ?>>
                <span class="tab-num <?php echo $checklist_activo?'done':''; ?>">2</span><span>Checklist</span>
            </button>
            <button class="tab-btn <?php echo $tab_activo=='acta'?'active':''; ?>" data-tab="acta" <?php echo !$checklist_activo?'disabled':''; ?>>
                <span class="tab-num <?php echo $acta_activa?'done':''; ?>">3</span><span>Acta</span>
            </button>
            <button class="tab-btn <?php echo $tab_activo=='encuesta'?'active':''; ?>" data-tab="encuesta" <?php echo !$acta_activa?'disabled':''; ?>>
                <span class="tab-num <?php echo $encuesta_activa?'done':''; ?>">4</span><span>Encuesta</span>
            </button>
            <button class="tab-btn <?php echo $tab_activo=='prueba'?'active':''; ?>" data-tab="prueba" <?php echo !$encuesta_activa?'disabled':''; ?>>
                <span class="tab-num <?php echo $prueba_activa?'done':''; ?>">5</span><span>Prueba</span>
            </button>
            <button class="tab-btn <?php echo $tab_activo=='widmark'?'active':''; ?>" data-tab="widmark" <?php echo (!$prueba_activa||$prueba_activa['resultado']!='reprobado')?'disabled':''; ?>>
                <span class="tab-num <?php echo count($registros_widmark)>0?'done':''; ?>">6</span><span>Widmark</span>
            </button>
            <button class="tab-btn <?php echo $tab_activo=='informe'?'active':''; ?>" data-tab="informe" <?php echo (!$prueba_activa||$prueba_activa['resultado']!='reprobado')?'disabled':''; ?>>
                <span class="tab-num <?php echo $informe_activo?'done':''; ?>">7</span><span>Informe</span>
            </button>
        </div>

        <div class="tabs-content">

            <!-- ===================== TAB 1: OPERACIÓN ===================== -->
            <div class="tab-panel <?php echo $tab_activo=='operacion'?'active':''; ?>" id="tab-operacion">
                <div class="guide-box">
                    <div class="guide-icon">1</div>
                    <div class="guide-text">
                        <h3>PASO 1: Crear o Seleccionar Operación</h3>
                        <p><strong>¿Qué es?</strong> Una operación agrupa todas las pruebas de una jornada.</p>
                        <p><strong>¿Qué hacer?</strong> Complete el formulario o seleccione una existente.</p>
                        <p><strong>Siguiente:</strong> Checklist del equipo (Paso 2).</p>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-header">
                        <h3><i class="fas fa-edit"></i> Datos de la Operación</h3>
                        <?php if($operacion_activa): ?><span class="badge-ok">✓ ID #<?php echo $operacion_activa['id']; ?></span><?php endif; ?>
                    </div>
                    <form method="POST" class="form-body">
                        <input type="hidden" name="operacion_id" value="<?php echo $operacion_activa['id'] ?? ''; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Empresa</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($empresa['nombre_empresa'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label>Sede/Área <span class="req">*</span></label>
                                <select name="ubicacion_id" class="form-control" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach($ubicaciones as $u): ?>
                                    <option value="<?php echo $u['id']; ?>" <?php echo ($operacion_activa['ubicacion_id'] ?? '')==$u['id']?'selected':''; ?>><?php echo htmlspecialchars($u['nombre_ubicacion'].' ('.$u['tipo'].')'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Lugar de Pruebas</label>
                                <input type="text" name="lugar_pruebas" class="form-control" placeholder="Ej: Entrada principal" value="<?php echo htmlspecialchars($operacion_activa['lugar_pruebas'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Fecha <span class="req">*</span></label>
                                <input type="date" name="fecha" class="form-control" required value="<?php echo $operacion_activa['fecha'] ?? date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Plan/Motivo <span class="req">*</span></label>
                                <select name="plan_motivo" class="form-control" required>
                                    <option value="diario" <?php echo ($operacion_activa['plan_motivo'] ?? '')=='diario'?'selected':''; ?>>Diario</option>
                                    <option value="aleatorio" <?php echo ($operacion_activa['plan_motivo'] ?? '')=='aleatorio'?'selected':''; ?>>Aleatorio</option>
                                    <option value="semanal" <?php echo ($operacion_activa['plan_motivo'] ?? '')=='semanal'?'selected':''; ?>>Semanal</option>
                                    <option value="mensual" <?php echo ($operacion_activa['plan_motivo'] ?? '')=='mensual'?'selected':''; ?>>Mensual</option>
                                    <option value="sospecha" <?php echo ($operacion_activa['plan_motivo'] ?? '')=='sospecha'?'selected':''; ?>>Sospecha</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Hora Inicio</label>
                                <input type="time" name="hora_inicio" class="form-control" value="<?php echo $operacion_activa['hora_inicio'] ?? ''; ?>">
                            </div>
                            <div class="form-group">
                                <label>Hora Cierre</label>
                                <input type="time" name="hora_cierre" class="form-control" value="<?php echo $operacion_activa['hora_cierre'] ?? ''; ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Operador Responsable <span class="req">*</span></label>
                                <select name="operador_id" class="form-control" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach($operadores as $op): ?>
                                    <option value="<?php echo $op['id']; ?>" <?php echo ($operacion_activa['operador_id'] ?? '')==$op['id']?'selected':''; ?>><?php echo htmlspecialchars($op['nombre'].' '.$op['apellido'].' - '.$op['dni']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Estado</label>
                                <select name="estado_operacion" class="form-control">
                                    <option value="planificada" <?php echo ($operacion_activa['estado'] ?? '')=='planificada'?'selected':''; ?>>Planificada</option>
                                    <option value="en_proceso" <?php echo ($operacion_activa['estado'] ?? '')=='en_proceso'?'selected':''; ?>>En Proceso</option>
                                    <option value="completada" <?php echo ($operacion_activa['estado'] ?? '')=='completada'?'selected':''; ?>>Completada</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="guardar_operacion" class="btn btn-primary btn-lg">
                                <i class="fas fa-arrow-right"></i> Guardar y Continuar al Checklist
                            </button>
                        </div>
                    </form>
                </div>

                <div class="form-card">
                    <div class="form-header"><h3><i class="fas fa-history"></i> Operaciones Existentes</h3></div>
                    <div class="table-container">
                    <?php if(!empty($operaciones)): ?>
                    <table class="data-table">
                        <thead><tr><th>ID</th><th>Fecha</th><th>Ubicación</th><th>Operador</th><th>Pruebas</th><th>Estado</th><th>Acción</th></tr></thead>
                        <tbody>
                        <?php foreach($operaciones as $op): ?>
                        <tr class="<?php echo ($operacion_activa && $operacion_activa['id']==$op['id'])?'row-active':''; ?>">
                            <td><strong>#<?php echo $op['id']; ?></strong></td>
                            <td><?php echo date('d/m/Y', strtotime($op['fecha'])); ?></td>
                            <td><?php echo htmlspecialchars($op['nombre_ubicacion'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($op['operador_nombre'] ?? 'N/A'); ?></td>
                            <td><span class="badge"><?php echo $op['total_pruebas']; ?></span></td>
                            <td><span class="status-<?php echo $op['estado']; ?>"><?php echo ucfirst(str_replace('_', ' ', $op['estado'])); ?></span></td>
                            <td><a href="?operacion_id=<?php echo $op['id']; ?>&tab=acta" class="btn btn-sm btn-info">Continuar</a></td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="empty"><i class="fas fa-inbox"></i><p>No hay operaciones registradas</p></div>
                    <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- ===================== TAB 2: CHECKLIST ===================== -->
            <div class="tab-panel <?php echo $tab_activo=='checklist'?'active':''; ?>" id="tab-checklist">
                <?php if(!$operacion_activa): ?>
                <div class="blocked"><i class="fas fa-lock"></i><h3>Paso Bloqueado</h3><p>Primero complete el Paso 1: Operación</p><button class="btn btn-primary" onclick="irTab('operacion')">Ir al Paso 1</button></div>
                <?php else: ?>
                <div class="guide-box">
                    <div class="guide-icon">2</div>
                    <div class="guide-text">
                        <h3>PASO 2: Verificar Equipo (Checklist)</h3>
                        <p><strong>¿Qué es?</strong> Verificación del alcoholímetro y materiales.</p>
                        <p><strong>¿Qué hacer?</strong> Seleccione el equipo y marque los ítems conformes.</p>
                        <p><strong>Siguiente:</strong> Registrar conductores (Paso 3).</p>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-header">
                        <h3><i class="fas fa-tasks"></i> Lista de Chequeo</h3>
                        <?php if($checklist_activo): ?><span class="badge-ok">✓ Guardado</span><?php endif; ?>
                    </div>
                    <form method="POST" class="form-body">
                        <input type="hidden" name="operacion_id" value="<?php echo $operacion_activa['id']; ?>">
                        <input type="hidden" name="checklist_id" value="<?php echo $checklist_activo['id'] ?? ''; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Alcoholímetro <span class="req">*</span></label>
                                <select name="alcoholimetro_id" class="form-control" required>
                                    <option value="">-- Seleccione --</option>
                                    <?php foreach($alcoholimetros as $a): ?>
                                    <option value="<?php echo $a['id']; ?>" <?php echo ($checklist_activo['alcoholimetro_id'] ?? '')==$a['id']?'selected':''; ?>><?php echo htmlspecialchars($a['nombre_activo'].' - '.$a['numero_serie']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Estado del Equipo <span class="req">*</span></label>
                                <select name="estado_alcoholimetro" class="form-control" required>
                                    <option value="conforme" <?php echo ($checklist_activo['estado_alcoholimetro'] ?? '')=='conforme'?'selected':''; ?>>✅ Conforme</option>
                                    <option value="no_conforme" <?php echo ($checklist_activo['estado_alcoholimetro'] ?? '')=='no_conforme'?'selected':''; ?>>❌ No Conforme</option>
                                </select>
                            </div>
                        </div>
                        <h4><i class="fas fa-clipboard-check"></i> Verificación del Equipo</h4>
                        <div class="check-grid">
                            <label class="check-item"><input type="checkbox" name="fecha_hora_actualizada" <?php echo ($checklist_activo['fecha_hora_actualizada'] ?? 0)?'checked':''; ?>><span>Fecha/hora actualizada</span></label>
                            <label class="check-item"><input type="checkbox" name="bateria_cargada" <?php echo ($checklist_activo['bateria_cargada'] ?? 0)?'checked':''; ?>><span>Batería cargada</span></label>
                            <label class="check-item"><input type="checkbox" name="enciende_condiciones" <?php echo ($checklist_activo['enciende_condiciones'] ?? 0)?'checked':''; ?>><span>Enciende correctamente</span></label>
                            <label class="check-item"><input type="checkbox" name="impresora_operativa" <?php echo ($checklist_activo['impresora_operativa'] ?? 0)?'checked':''; ?>><span>Impresora operativa</span></label>
                            <label class="check-item"><input type="checkbox" name="boquillas" <?php echo ($checklist_activo['boquillas'] ?? 0)?'checked':''; ?>><span>Boquillas disponibles</span></label>
                            <label class="check-item"><input type="checkbox" name="documentacion_disponible" <?php echo ($checklist_activo['documentacion_disponible'] ?? 0)?'checked':''; ?>><span>Documentación</span></label>
                            <label class="check-item"><input type="checkbox" name="huellero" <?php echo ($checklist_activo['huellero'] ?? 0)?'checked':''; ?>><span>Huellero</span></label>
                            <label class="check-item"><input type="checkbox" name="lapicero" <?php echo ($checklist_activo['lapicero'] ?? 0)?'checked':''; ?>><span>Lapicero</span></label>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="guardar_checklist" class="btn btn-primary btn-lg">
                                <i class="fas fa-arrow-right"></i> Guardar y Continuar al Acta
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===================== TAB 3: ACTA ===================== -->
            <div class="tab-panel <?php echo $tab_activo=='acta'?'active':''; ?>" id="tab-acta">
                <?php if(!$checklist_activo): ?>
                <div class="blocked"><i class="fas fa-lock"></i><h3>Paso Bloqueado</h3><p>Primero complete el Paso 2: Checklist</p><button class="btn btn-primary" onclick="irTab('checklist')">Ir al Paso 2</button></div>
                <?php else: ?>
                <div class="guide-box">
                    <div class="guide-icon">3</div>
                    <div class="guide-text">
                        <h3>PASO 3: Acta de Consentimiento</h3>
                        <p><strong>¿Qué es?</strong> Registro del conductor y su aceptación voluntaria.</p>
                        <p><strong>¿Qué hacer?</strong> Seleccione al conductor y obtenga su firma.</p>
                        <p><strong>Importante:</strong> Esto crea un PROTOCOLO individual.</p>
                    </div>
                </div>

                <?php if($acta_activa && $protocolo_activo): ?>
                <div class="info-bar success"><i class="fas fa-check-circle"></i><span>Protocolo #<?php echo $protocolo_activo['id']; ?> - <strong><?php echo $protocolo_activo['conductor_nombre']; ?></strong></span></div>
                <?php endif; ?>

                <div class="form-card">
                    <div class="form-header"><h3><i class="fas fa-user-check"></i> Datos del Conductor</h3></div>
                    <form method="POST" class="form-body">
                        <input type="hidden" name="operacion_id" value="<?php echo $operacion_activa['id']; ?>">
                        <input type="hidden" name="protocolo_id" value="<?php echo $protocolo_activo['id'] ?? ''; ?>">
                        <input type="hidden" name="acta_id" value="<?php echo $acta_activa['id'] ?? ''; ?>">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Conductor <span class="req">*</span></label>
                                <select name="conductor_id" class="form-control" required>
                                    <option value="">-- Seleccione un conductor --</option>
                                    <?php foreach($conductores as $c): ?>
                                    <option value="<?php echo $c['id']; ?>" <?php echo ($acta_activa['conductor_id'] ?? ($protocolo_activo['conductor_id'] ?? ''))==$c['id']?'selected':''; ?>><?php echo htmlspecialchars($c['nombre'].' '.$c['apellido'].' - DNI: '.$c['dni']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Objetivo de la Prueba</label>
                                <select name="objetivo_prueba" class="form-control">
                                    <option value="preventivo" <?php echo ($acta_activa['objetivo_prueba'] ?? '')=='preventivo'?'selected':''; ?>>Preventivo</option>
                                    <option value="descarte" <?php echo ($acta_activa['objetivo_prueba'] ?? '')=='descarte'?'selected':''; ?>>Descarte</option>
                                    <option value="estudio_positivo" <?php echo ($acta_activa['objetivo_prueba'] ?? '')=='estudio_positivo'?'selected':''; ?>>Estudio Positivo</option>
                                    <option value="confirmatorio" <?php echo ($acta_activa['objetivo_prueba'] ?? '')=='confirmatorio'?'selected':''; ?>>Confirmatorio</option>
                                </select>
                            </div>
                        </div>
                        <div class="consent-box">
                            <h4><i class="fas fa-file-signature"></i> Declaración de Consentimiento</h4>
                            <p>El evaluado declara que acepta voluntariamente someterse a la prueba de alcoholemia.</p>
                            <div class="form-group">
                                <label>Firma del Conductor (Nombre Completo)</label>
                                <input type="text" name="firma_conductor" class="form-control signature" placeholder="Escriba su nombre completo" value="<?php echo htmlspecialchars($acta_activa['firma_conductor'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="guardar_acta" class="btn btn-primary btn-lg">
                                <i class="fas fa-arrow-right"></i> Guardar y Continuar a Encuesta
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===================== TAB 4: ENCUESTA ===================== -->
            <div class="tab-panel <?php echo $tab_activo=='encuesta'?'active':''; ?>" id="tab-encuesta">
                <?php if(!$acta_activa || !$protocolo_activo): ?>
                <div class="blocked"><i class="fas fa-lock"></i><h3>Paso Bloqueado</h3><p>Primero complete el Paso 3: Acta</p><button class="btn btn-primary" onclick="irTab('acta')">Ir al Paso 3</button></div>
                <?php else: 
                    $enc_enf = $encuesta_activa ? json_decode($encuesta_activa['enfermedades'] ?? '{}', true) : [];
                    $enc_boca = $encuesta_activa ? json_decode($encuesta_activa['elementos_boca'] ?? '{}', true) : [];
                    $enc_act = $encuesta_activa ? json_decode($encuesta_activa['actividades_recientes'] ?? '{}', true) : [];
                ?>
                <div class="info-bar"><i class="fas fa-user"></i><span>Conductor: <strong><?php echo $protocolo_activo['conductor_nombre']; ?></strong> | DNI: <?php echo $protocolo_activo['conductor_dni']; ?></span></div>

                <div class="guide-box">
                    <div class="guide-icon">4</div>
                    <div class="guide-text">
                        <h3>PASO 4: Encuesta Preliminar</h3>
                        <p><strong>¿Qué es?</strong> Preguntas de salud para detectar factores que afecten la prueba.</p>
                        <p class="warning-text"><i class="fas fa-exclamation-triangle"></i> Si fumó, usó enjuague bucal o vomitó, espere 15 minutos.</p>
                    </div>
                </div>

                <div class="form-card">
                    <form method="POST" class="form-body">
                        <input type="hidden" name="acta_id" value="<?php echo $acta_activa['id']; ?>">
                        <input type="hidden" name="protocolo_id" value="<?php echo $protocolo_activo['id']; ?>">
                        <input type="hidden" name="encuesta_id" value="<?php echo $encuesta_activa['id'] ?? ''; ?>">
                        
                        <h4><i class="fas fa-heartbeat"></i> ¿Sufre alguna enfermedad?</h4>
                        <div class="check-grid">
                            <label class="check-item"><input type="checkbox" name="enf_diabetes" <?php echo ($enc_enf['diabetes'] ?? false)?'checked':''; ?>><span>Diabetes</span></label>
                            <label class="check-item"><input type="checkbox" name="enf_hipertension" <?php echo ($enc_enf['hipertension'] ?? false)?'checked':''; ?>><span>Hipertensión</span></label>
                        </div>
                        <div class="form-group"><label>Otras:</label><input type="text" name="enf_otros" class="form-control" placeholder="Especifique..." value="<?php echo htmlspecialchars($enc_enf['otros'] ?? ''); ?>"></div>

                        <h4><i class="fas fa-tooth"></i> ¿Tiene algo en la boca?</h4>
                        <div class="check-grid">
                            <label class="check-item"><input type="checkbox" name="boca_chiclets" <?php echo ($enc_boca['chiclets'] ?? false)?'checked':''; ?>><span>Chicles</span></label>
                            <label class="check-item"><input type="checkbox" name="boca_caramelos" <?php echo ($enc_boca['caramelos'] ?? false)?'checked':''; ?>><span>Caramelos</span></label>
                            <label class="check-item"><input type="checkbox" name="boca_pastillas" <?php echo ($enc_boca['pastillas_mentoladas'] ?? false)?'checked':''; ?>><span>Pastillas mentoladas</span></label>
                            <label class="check-item"><input type="checkbox" name="boca_piercing" <?php echo ($enc_boca['piercing_brackets'] ?? false)?'checked':''; ?>><span>Piercing/Brackets</span></label>
                        </div>
                        <div class="form-group"><label>Otros:</label><input type="text" name="boca_otros" class="form-control" value="<?php echo htmlspecialchars($enc_boca['otros'] ?? ''); ?>"></div>

                        <h4><i class="fas fa-clock"></i> En los últimos 15 minutos, ¿ha...?</h4>
                        <div class="check-grid">
                            <label class="check-item warn"><input type="checkbox" name="act_fumado" <?php echo ($enc_act['fumado'] ?? false)?'checked':''; ?>><span>⚠️ Fumado</span></label>
                            <label class="check-item"><input type="checkbox" name="act_eructado" <?php echo ($enc_act['eructado'] ?? false)?'checked':''; ?>><span>Eructado</span></label>
                            <label class="check-item warn"><input type="checkbox" name="act_splash" <?php echo ($enc_act['splash_bucal'] ?? false)?'checked':''; ?>><span>⚠️ Splash bucal</span></label>
                            <label class="check-item warn"><input type="checkbox" name="act_vomitado" <?php echo ($enc_act['vomitado'] ?? false)?'checked':''; ?>><span>⚠️ Vomitado</span></label>
                            <label class="check-item warn"><input type="checkbox" name="act_enjuague" <?php echo ($enc_act['enjuague_bucal'] ?? false)?'checked':''; ?>><span>⚠️ Enjuague bucal</span></label>
                        </div>
                        <div class="form-group"><label>Otros:</label><input type="text" name="act_otros" class="form-control" value="<?php echo htmlspecialchars($enc_act['otros'] ?? ''); ?>"></div>
                        <div class="form-group"><label>Observaciones:</label><textarea name="encuesta_observaciones" class="form-control" rows="2"><?php echo htmlspecialchars($encuesta_activa['observaciones'] ?? ''); ?></textarea></div>

                        <div class="form-actions">
                            <button type="submit" name="guardar_encuesta" class="btn btn-primary btn-lg">
                                <i class="fas fa-arrow-right"></i> Guardar y Continuar a la Prueba
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===================== TAB 5: PRUEBA ===================== -->
            <div class="tab-panel <?php echo $tab_activo=='prueba'?'active':''; ?>" id="tab-prueba">
                <?php if(!$encuesta_activa || !$protocolo_activo): ?>
                <div class="blocked"><i class="fas fa-lock"></i><h3>Paso Bloqueado</h3><p>Primero complete el Paso 4: Encuesta</p><button class="btn btn-primary" onclick="irTab('encuesta')">Ir al Paso 4</button></div>
                <?php else: ?>
                <div class="info-bar"><i class="fas fa-user"></i><span>Conductor: <strong><?php echo $protocolo_activo['conductor_nombre']; ?></strong> | Protocolo #<?php echo $protocolo_activo['id']; ?></span></div>

                <div class="guide-box">
                    <div class="guide-icon">5</div>
                    <div class="guide-text">
                        <h3>PASO 5: Prueba de Alcoholemia</h3>
                        <p><strong>¿Qué es?</strong> Registro del resultado de la medición.</p>
                        <div class="limits-display">
                            <span class="limit-item ok"><i class="fas fa-check-circle"></i> Límite: <?php echo number_format($config['limite_alcohol_permisible'] ?? 0, 3); ?> g/L</span>
                            <span class="limit-item warn"><i class="fas fa-exclamation-triangle"></i> Advertencia: <?php echo number_format($config['nivel_advertencia'] ?? 0.025, 3); ?> g/L</span>
                            <span class="limit-item bad"><i class="fas fa-times-circle"></i> Crítico: <?php echo number_format($config['nivel_critico'] ?? 0.080, 3); ?> g/L</span>
                        </div>
                    </div>
                </div>

                <?php if($prueba_activa): ?>
                <div class="result-box <?php echo $prueba_activa['resultado']; ?>">
                    <div class="result-icon"><?php echo $prueba_activa['resultado']=='aprobado'?'<i class="fas fa-check-circle"></i>':'<i class="fas fa-times-circle"></i>'; ?></div>
                    <div class="result-text">
                        <h3>RESULTADO: <?php echo $prueba_activa['resultado']=='aprobado'?'NEGATIVO':'POSITIVO'; ?></h3>
                        <p>Nivel: <strong><?php echo number_format($prueba_activa['nivel_alcohol'], 3); ?> g/L</strong></p>
                    </div>
                    <div class="result-action">
                        <?php if($prueba_activa['resultado']=='aprobado'): ?>
                        <p class="result-msg ok">✅ PROTOCOLO FINALIZADO</p>
                        <a href="?operacion_id=<?php echo $operacion_activa['id']; ?>&tab=acta" class="btn btn-success"><i class="fas fa-plus"></i> Otro Conductor</a>
                        <?php else: ?>
                        <p class="result-msg bad">⚠️ Continúe al Widmark</p>
                        <button type="button" class="btn btn-danger" onclick="irTab('widmark')"><i class="fas fa-arrow-right"></i> Ir a Widmark</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-card">
                    <div class="form-header"><h3><i class="fas fa-vial"></i> Registrar Resultado</h3></div>
                    <form method="POST" class="form-body">
                        <input type="hidden" name="protocolo_id" value="<?php echo $protocolo_activo['id']; ?>">
                        <input type="hidden" name="conductor_id" value="<?php echo $protocolo_activo['conductor_id']; ?>">
                        <input type="hidden" name="alcoholimetro_id" value="<?php echo $checklist_activo['alcoholimetro_id']; ?>">
                        <input type="hidden" name="prueba_id" value="<?php echo $prueba_activa['id'] ?? ''; ?>">
                        <input type="hidden" name="latitud" id="lat">
                        <input type="hidden" name="longitud" id="lng">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Nivel de Alcohol (g/L) <span class="req">*</span></label>
                                <input type="number" name="nivel_alcohol" class="form-control nivel-input" step="0.001" min="0" max="5" required value="<?php echo $prueba_activa['nivel_alcohol'] ?? '0.000'; ?>" oninput="previewResultado(this.value)">
                                <div id="preview-resultado"></div>
                            </div>
                            <div class="form-group">
                                <label>Vehículo (opcional)</label>
                                <select name="vehiculo_id" class="form-control">
                                    <option value="">-- Sin vehículo --</option>
                                    <?php foreach($vehiculos as $v): ?>
                                    <option value="<?php echo $v['id']; ?>" <?php echo ($prueba_activa['vehiculo_id'] ?? '')==$v['id']?'selected':''; ?>><?php echo htmlspecialchars($v['placa'].' - '.$v['marca']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Observaciones</label>
                            <textarea name="observaciones_prueba" class="form-control" rows="2"><?php echo htmlspecialchars($prueba_activa['observaciones'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="guardar_prueba" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Registrar Resultado
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- ===================== TAB 6: WIDMARK ===================== -->
            <div class="tab-panel <?php echo $tab_activo=='widmark'?'active':''; ?>" id="tab-widmark">
                <?php if(!$prueba_activa || $prueba_activa['resultado']!='reprobado'): ?>
                <div class="blocked info"><i class="fas fa-info-circle"></i><h3>Tab No Requerido</h3><p>Solo para resultados POSITIVOS</p></div>
                <?php else: ?>
                <div class="info-bar danger"><i class="fas fa-exclamation-triangle"></i><span>CASO POSITIVO: <strong><?php echo $protocolo_activo['conductor_nombre']; ?></strong> | Nivel inicial: <strong><?php echo number_format($prueba_activa['nivel_alcohol'], 3); ?> g/L</strong></span></div>

                <div class="guide-box warn">
                    <div class="guide-icon">6</div>
                    <div class="guide-text">
                        <h3>PASO 6: Curva Widmark (Seguimiento)</h3>
                        <p><strong>¿Qué es?</strong> Monitoreo de la eliminación del alcohol cada 10-15 minutos.</p>
                        <p><strong>Instrucciones:</strong> El primer registro es "Inicio" (tiempo 0). Continue hasta llegar a 0.000 g/L.</p>
                    </div>
                </div>

                <div class="form-card">
                    <div class="form-header">
                        <h3><i class="fas fa-plus-circle"></i> Agregar Registro Widmark</h3>
                        <span class="badge badge-info"><?php echo count($registros_widmark); ?> registros</span>
                    </div>
                    <form method="POST" class="form-body">
                        <input type="hidden" name="prueba_id" value="<?php echo $prueba_activa['id']; ?>">
                        <input type="hidden" name="protocolo_id" value="<?php echo $protocolo_activo['id']; ?>">
                        <div class="form-row four">
                            <div class="form-group">
                                <label>Hora <span class="req">*</span></label>
                                <input type="time" name="widmark_hora" class="form-control" required value="<?php echo date('H:i'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Tiempo (min) <span class="req">*</span></label>
                                <input type="number" name="widmark_tiempo" class="form-control" required min="0" step="1" value="<?php echo count($registros_widmark) == 0 ? '0' : ''; ?>" placeholder="<?php echo count($registros_widmark) == 0 ? '0' : 'Ej: 15, 30...'; ?>">
                            </div>
                            <div class="form-group">
                                <label>BAC (g/L) <span class="req">*</span></label>
                                <input type="number" name="widmark_bac" class="form-control" required step="0.001" min="0" max="5" placeholder="0.000">
                            </div>
                            <div class="form-group">
                                <label>Comportamiento <span class="req">*</span></label>
                                <select name="widmark_comportamiento" class="form-control" required>
                                    <option value="inicio" <?php echo count($registros_widmark) == 0 ? 'selected' : ''; ?>>🔵 Inicio / Punto Inicial</option>
                                    <option value="descendente">📉 Descendente</option>
                                    <option value="ascendente">📈 Ascendente</option>
                                    <option value="estable">➡️ Estable</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Observación</label>
                            <input type="text" name="widmark_observacion" class="form-control" placeholder="Ej: Toma de muestra de pie">
                        </div>
                        <div class="form-actions">
                            <button type="submit" name="agregar_widmark" class="btn btn-warning btn-lg">
                                <i class="fas fa-plus"></i> Agregar Registro
                            </button>
                            <button type="button" class="btn btn-danger btn-lg" onclick="irTab('informe')">
                                <i class="fas fa-arrow-right"></i> Continuar al Informe
                            </button>
                        </div>
                    </form>
                </div>

                <?php if(!empty($registros_widmark)): ?>
                <div class="form-card">
                    <div class="form-header">
                        <h3><i class="fas fa-chart-line"></i> Registros de la Curva</h3>
                        <span class="badge badge-success"><?php echo count($registros_widmark); ?> registro(s)</span>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>#</th><th>Hora</th><th>Tiempo</th><th>BAC</th><th>Comportamiento</th><th>Observación</th></tr></thead>
                            <tbody>
                            <?php 
                            $num = 1;
                            $comportamientos = ['inicio' => '🔵 Inicio', 'descendente' => '📉 Descendente', 'ascendente' => '📈 Ascendente', 'estable' => '➡️ Estable'];
                            foreach($registros_widmark as $r): ?>
                            <tr>
                                <td><strong><?php echo $num++; ?></strong></td>
                                <td><?php echo $r['hora']; ?></td>
                                <td><?php echo $r['tiempo_minutos']; ?> min</td>
                                <td><span class="badge <?php echo $r['bac'] > 0 ? 'badge-danger' : 'badge-success'; ?>"><?php echo number_format($r['bac'], 3); ?></span></td>
                                <td><?php echo $comportamientos[$r['comportamiento_curva']] ?? ucfirst($r['comportamiento_curva']); ?></td>
                                <td><?php echo htmlspecialchars($r['observacion'] ?? '-'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- ===================== TAB 7: INFORME ===================== -->
            <div class="tab-panel <?php echo $tab_activo=='informe'?'active':''; ?>" id="tab-informe">
                <?php if(!$prueba_activa || $prueba_activa['resultado']!='reprobado'): ?>
                <div class="blocked info"><i class="fas fa-info-circle"></i><h3>Tab No Requerido</h3><p>Solo para resultados POSITIVOS</p></div>
                <?php else: 
                    $inf_obs = $informe_activo ? json_decode($informe_activo['observaciones_examinado'] ?? '{}', true) : [];
                    $inf_adj = $informe_activo ? json_decode($informe_activo['adjuntos'] ?? '{}', true) : [];
                ?>
                <div class="info-bar danger"><i class="fas fa-file-medical-alt"></i><span>INFORME POSITIVO: <strong><?php echo $protocolo_activo['conductor_nombre']; ?></strong></span></div>

                <div class="guide-box danger">
                    <div class="guide-icon">7</div>
                    <div class="guide-text">
                        <h3>PASO 7: Informe Final de Caso Positivo</h3>
                        <p><strong>¿Qué es?</strong> Documentación completa del caso para el expediente.</p>
                        <p><strong>Al guardar:</strong> El protocolo se marcará como COMPLETADO.</p>
                    </div>
                </div>

                <?php if($protocolo_activo && $protocolo_activo['completada']): ?>
                <div class="complete-box">
                    <i class="fas fa-flag-checkered"></i>
                    <h3>¡PROTOCOLO COMPLETADO!</h3>
                    <p>El caso ha sido documentado correctamente.</p>
                    <a href="?operacion_id=<?php echo $operacion_activa['id']; ?>&tab=acta" class="btn btn-primary btn-lg"><i class="fas fa-plus"></i> Agregar Otro Conductor</a>
                </div>
                <?php endif; ?>

                <div class="form-card">
                    <div class="form-header">
                        <h3><i class="fas fa-file-medical"></i> Informe de Resultado Positivo</h3>
                        <?php if($informe_activo): ?><span class="badge-ok">✓ Guardado</span><?php endif; ?>
                    </div>
                    <form method="POST" class="form-body">
                        <input type="hidden" name="prueba_id" value="<?php echo $prueba_activa['id']; ?>">
                        <input type="hidden" name="protocolo_id" value="<?php echo $protocolo_activo['id']; ?>">
                        <input type="hidden" name="informe_id" value="<?php echo $informe_activo['id'] ?? ''; ?>">
                        
                        <h4><i class="fas fa-cloud-sun"></i> Condición Ambiental</h4>
                        <div class="form-group">
                            <select name="condicion_ambiental" class="form-control">
                                <option value="controlado" <?php echo ($informe_activo['condicion_ambiental'] ?? '')=='controlado'?'selected':''; ?>>Ambiente Controlado</option>
                                <option value="libre_alcohol" <?php echo ($informe_activo['condicion_ambiental'] ?? '')=='libre_alcohol'?'selected':''; ?>>Ambiente Libre de Alcohol</option>
                            </select>
                        </div>

                        <h4><i class="fas fa-user-md"></i> Observaciones Clínicas</h4>
                        <div class="check-grid">
                            <label class="check-item"><input type="checkbox" name="obs_seguridad" <?php echo ($inf_obs['seguridad_si_mismo'] ?? false)?'checked':''; ?>><span>Mayor seguridad, incoordinación leve</span></label>
                            <label class="check-item"><input type="checkbox" name="obs_euforia" <?php echo ($inf_obs['euforia'] ?? false)?'checked':''; ?>><span>Euforia, reflejos disminuidos</span></label>
                            <label class="check-item"><input type="checkbox" name="obs_equilibrio" <?php echo ($inf_obs['trastornos_equilibrio'] ?? false)?'checked':''; ?>><span>Trastornos del equilibrio</span></label>
                            <label class="check-item"><input type="checkbox" name="obs_autocritica" <?php echo ($inf_obs['disminucion_autocritica'] ?? false)?'checked':''; ?>><span>Disminución de autocrítica</span></label>
                            <label class="check-item"><input type="checkbox" name="obs_psicosensoriales" <?php echo ($inf_obs['perturbaciones_psicosensoriales'] ?? false)?'checked':''; ?>><span>Perturbaciones psicosensoriales</span></label>
                        </div>
                        <div class="form-group"><label>Otras:</label><input type="text" name="obs_otros" class="form-control" value="<?php echo htmlspecialchars($inf_obs['otros'] ?? ''); ?>"></div>

                        <h4><i class="fas fa-comment-medical"></i> Comentarios / Acción Inmediata</h4>
                        <div class="form-group"><textarea name="comentarios_accion" class="form-control" rows="2"><?php echo htmlspecialchars($informe_activo['comentarios_accion'] ?? ''); ?></textarea></div>

                        <h4><i class="fas fa-gavel"></i> Conclusión</h4>
                        <div class="form-group"><textarea name="conclusion" class="form-control" rows="2"><?php echo htmlspecialchars($informe_activo['conclusion'] ?? 'Resultado POSITIVO. Se recomienda seguir protocolo interno de seguridad.'); ?></textarea></div>

                        <h4><i class="fas fa-paperclip"></i> Adjuntos</h4>
                        <div class="check-grid">
                            <label class="check-item"><input type="checkbox" name="adj_ticket" <?php echo ($inf_adj['ticket_alcoholimetro'] ?? false)?'checked':''; ?>><span>📄 Foto del ticket</span></label>
                            <label class="check-item"><input type="checkbox" name="adj_registro" <?php echo ($inf_adj['registro_digital'] ?? false)?'checked':''; ?>><span>💾 Registro digital</span></label>
                            <label class="check-item"><input type="checkbox" name="adj_grabacion" <?php echo ($inf_adj['grabacion'] ?? false)?'checked':''; ?>><span>🎥 Grabación</span></label>
                        </div>

                        <div class="form-actions">
                            <button type="submit" name="guardar_informe" class="btn btn-danger btn-lg">
                                <i class="fas fa-save"></i> Guardar Informe y Finalizar
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

        </div><!-- /.tabs-content -->
    </div><!-- /.tabs-container -->
</div><!-- /.content-body -->

<style>
:root{--primary:#84061f;--primary-dark:#6a0519;--success:#27ae60;--danger:#e74c3c;--warning:#f39c12;--info:#3498db;--light:#f8f9fa;--dark:#2c3e50;--gray:#6c757d;--border:#dee2e6;--shadow:0 2px 15px rgba(0,0,0,0.08);--radius:10px}
*{box-sizing:border-box;margin:0;padding:0}
.content-body{padding:1.5rem;max-width:1200px;margin:0 auto}
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:2px solid var(--border);flex-wrap:wrap;gap:1rem}
.page-header h1{font-size:1.5rem;color:var(--dark);display:flex;align-items:center;gap:.5rem}
.page-header h1 i{color:var(--primary)}
.page-header p{color:var(--gray);font-size:.9rem;margin-top:.25rem}
.btn{padding:.65rem 1.2rem;border-radius:var(--radius);font-weight:600;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border:none;cursor:pointer;transition:all .2s;font-size:.9rem}
.btn:hover{transform:translateY(-2px)}
.btn-lg{padding:.85rem 1.6rem;font-size:1rem}
.btn-sm{padding:.4rem .8rem;font-size:.8rem}
.btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-dark));color:#fff;box-shadow:0 3px 10px rgba(132,6,31,.25)}
.btn-success{background:linear-gradient(135deg,var(--success),#2ecc71);color:#fff}
.btn-danger{background:linear-gradient(135deg,var(--danger),#c0392b);color:#fff}
.btn-warning{background:linear-gradient(135deg,var(--warning),#e67e22);color:#fff}
.btn-info{background:linear-gradient(135deg,var(--info),#5dade2);color:#fff}
.alert{display:flex;align-items:flex-start;gap:1rem;padding:1rem 1.5rem;border-radius:var(--radius);margin-bottom:1.5rem;border-left:5px solid;animation:slideIn .3s ease}
@keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.alert i{font-size:1.5rem;margin-top:.1rem;flex-shrink:0}
.alert div{flex:1}
.alert strong{display:block;margin-bottom:.25rem}
.alert p{margin:0;font-size:.9rem}
.alert-success{background:#d4edda;border-color:var(--success);color:#155724}
.alert-success i{color:var(--success)}
.alert-danger{background:#f8d7da;border-color:var(--danger);color:#721c24}
.alert-danger i{color:var(--danger)}
.tabs-container{background:#fff;border-radius:12px;box-shadow:var(--shadow);overflow:hidden}
.tabs-header{display:flex;background:linear-gradient(to bottom,#f8f9fa,#e9ecef);border-bottom:2px solid var(--border);overflow-x:auto}
.tab-btn{flex:1;min-width:85px;padding:.9rem .5rem;background:transparent;border:none;cursor:pointer;display:flex;flex-direction:column;align-items:center;gap:.4rem;color:var(--gray);font-weight:600;font-size:.75rem;border-bottom:4px solid transparent;transition:all .2s}
.tab-btn:hover:not(:disabled){background:rgba(132,6,31,.05);color:var(--primary)}
.tab-btn.active{color:var(--primary);background:#fff;border-bottom-color:var(--primary)}
.tab-btn:disabled{opacity:.4;cursor:not-allowed}
.tab-num{width:28px;height:28px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:700;transition:all .2s}
.tab-btn.active .tab-num{background:var(--primary);color:#fff;box-shadow:0 2px 8px rgba(132,6,31,.3)}
.tab-num.done{background:var(--success);color:#fff}
.tab-panel{display:none;padding:1.5rem;animation:fadeIn .3s ease}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.tab-panel.active{display:block}
.blocked{text-align:center;padding:4rem 2rem;color:var(--gray)}
.blocked i{font-size:4rem;margin-bottom:1rem;opacity:.4}
.blocked h3{margin:0 0 .5rem;color:var(--dark);font-size:1.3rem}
.blocked p{margin-bottom:1.5rem}
.blocked.info i{color:var(--info);opacity:.6}
.guide-box{display:flex;gap:1.25rem;padding:1.25rem;background:linear-gradient(135deg,#f8f9fa,#e9ecef);border:2px solid var(--border);border-radius:var(--radius);margin-bottom:1.5rem}
.guide-box.warn{background:linear-gradient(135deg,#fff8e1,#ffecb3);border-color:var(--warning)}
.guide-box.danger{background:linear-gradient(135deg,#ffebee,#ffcdd2);border-color:var(--danger)}
.guide-icon{width:48px;height:48px;background:var(--primary);color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:1.2rem;font-weight:700;flex-shrink:0;box-shadow:0 3px 10px rgba(132,6,31,.25)}
.guide-box.warn .guide-icon{background:var(--warning)}
.guide-box.danger .guide-icon{background:var(--danger)}
.guide-text h3{margin:0 0 .5rem;font-size:1.05rem;color:var(--dark)}
.guide-text p{margin:.3rem 0;font-size:.9rem;color:var(--dark);line-height:1.5}
.guide-text .warning-text{background:rgba(243,156,18,.2);padding:.5rem .75rem;border-radius:6px;border-left:4px solid var(--warning);margin-top:.5rem;display:flex;align-items:center;gap:.5rem}
.limits-display{display:flex;flex-wrap:wrap;gap:.5rem;margin:.75rem 0}
.limit-item{padding:.4rem .7rem;border-radius:6px;font-size:.85rem;font-weight:600;display:flex;align-items:center;gap:.4rem}
.limit-item.ok{background:rgba(39,174,96,.15);color:var(--success)}
.limit-item.warn{background:rgba(243,156,18,.15);color:#d68910}
.limit-item.bad{background:rgba(231,76,60,.15);color:var(--danger)}
.info-bar{display:flex;align-items:center;gap:1rem;padding:.8rem 1.2rem;background:linear-gradient(135deg,var(--info),#2980b9);color:#fff;border-radius:var(--radius);margin-bottom:1.5rem;font-size:.95rem;flex-wrap:wrap}
.info-bar i{font-size:1.2rem}
.info-bar.success{background:linear-gradient(135deg,var(--success),#2ecc71)}
.info-bar.danger{background:linear-gradient(135deg,var(--danger),#c0392b)}
.form-card{background:#fff;border:1px solid var(--border);border-radius:var(--radius);margin-bottom:1.5rem;overflow:hidden;box-shadow:0 1px 5px rgba(0,0,0,.05)}
.form-header{display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;background:linear-gradient(to bottom,#fafafa,#f5f5f5);border-bottom:1px solid var(--border);flex-wrap:wrap;gap:.5rem}
.form-header h3{margin:0;font-size:1rem;display:flex;align-items:center;gap:.5rem;color:var(--dark)}
.form-header h3 i{color:var(--primary)}
.form-body{padding:1.25rem}
.form-body h4{margin:1.5rem 0 .8rem;font-size:.95rem;color:var(--dark);display:flex;align-items:center;gap:.5rem;padding-top:1rem;border-top:1px solid var(--border)}
.form-body h4:first-of-type{margin-top:0;padding-top:0;border-top:none}
.form-body h4 i{color:var(--primary)}
.form-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem;margin-bottom:1rem}
.form-row.four{grid-template-columns:repeat(4,1fr)}
.form-group{display:flex;flex-direction:column}
.form-group label{font-weight:600;margin-bottom:.4rem;font-size:.9rem;color:var(--dark)}
.req{color:var(--danger)}
.form-control{padding:.7rem 1rem;border:2px solid var(--border);border-radius:8px;font-size:.95rem;transition:all .2s;width:100%}
.form-control:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 4px rgba(132,6,31,.1)}
textarea.form-control{resize:vertical;min-height:70px}
.form-actions{display:flex;gap:1rem;justify-content:flex-end;padding-top:1.25rem;border-top:1px solid var(--border);margin-top:1rem;flex-wrap:wrap}
.check-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:.6rem;margin-bottom:.8rem}
.check-item{display:flex;align-items:center;gap:.7rem;padding:.65rem 1rem;background:var(--light);border-radius:8px;cursor:pointer;transition:all .2s;border:2px solid transparent}
.check-item:hover{background:#e9ecef}
.check-item.warn{border-color:rgba(243,156,18,.4);background:rgba(243,156,18,.1)}
.check-item input[type="checkbox"]{width:18px;height:18px;cursor:pointer;accent-color:var(--success)}
.check-item span{font-size:.9rem;line-height:1.3}
.consent-box{background:linear-gradient(to right,var(--light),#fff);padding:1.25rem;border-radius:var(--radius);border-left:5px solid var(--primary);margin-bottom:1rem}
.consent-box h4{margin:0 0 .5rem!important;padding:0!important;border:none!important}
.consent-box p{margin:0 0 1rem;line-height:1.5;font-size:.95rem}
.signature{font-family:'Brush Script MT',cursive;font-size:1.2rem;text-align:center;background:#fffef0}
.nivel-input{font-size:1.4rem;font-weight:700;text-align:center;letter-spacing:2px}
#preview-resultado{margin-top:.5rem;padding:.5rem .75rem;border-radius:6px;text-align:center;font-weight:600;font-size:.9rem;display:none}
#preview-resultado.show{display:block}
#preview-resultado.neg{background:rgba(39,174,96,.15);color:var(--success)}
#preview-resultado.pos{background:rgba(231,76,60,.15);color:var(--danger)}
.result-box{display:flex;align-items:center;gap:1.5rem;padding:1.5rem;border-radius:12px;margin-bottom:1.5rem;flex-wrap:wrap}
.result-box.aprobado{background:linear-gradient(135deg,rgba(39,174,96,.1),rgba(46,204,113,.05));border:2px solid var(--success)}
.result-box.reprobado{background:linear-gradient(135deg,rgba(231,76,60,.1),rgba(192,57,43,.05));border:2px solid var(--danger)}
.result-icon{font-size:3.5rem}
.result-box.aprobado .result-icon{color:var(--success)}
.result-box.reprobado .result-icon{color:var(--danger)}
.result-text h3{margin:0 0 .4rem;font-size:1.4rem}
.result-box.aprobado .result-text h3{color:var(--success)}
.result-box.reprobado .result-text h3{color:var(--danger)}
.result-text p{margin:.2rem 0;font-size:.95rem}
.result-action{margin-left:auto;text-align:right}
.result-msg{margin:0 0 .7rem;font-weight:700;font-size:1rem}
.result-msg.ok{color:var(--success)}
.result-msg.bad{color:var(--danger)}
.complete-box{text-align:center;padding:2.5rem;background:linear-gradient(135deg,rgba(39,174,96,.1),rgba(39,174,96,.03));border:3px solid var(--success);border-radius:12px;margin-bottom:1.5rem}
.complete-box i{font-size:4rem;color:var(--success);margin-bottom:1rem;display:block}
.complete-box h3{margin:0 0 .5rem;color:var(--success);font-size:1.5rem}
.complete-box p{margin:0 0 1.5rem;color:var(--dark)}
.table-container{overflow-x:auto}
.data-table{width:100%;border-collapse:collapse;min-width:500px}
.data-table th{background:linear-gradient(to bottom,#f8f9fa,#e9ecef);padding:.8rem 1rem;text-align:left;font-weight:700;font-size:.8rem;text-transform:uppercase;color:var(--gray);border-bottom:2px solid var(--border)}
.data-table td{padding:.8rem 1rem;border-bottom:1px solid var(--border);vertical-align:middle;font-size:.9rem}
.data-table tbody tr:hover{background:rgba(132,6,31,.03)}
.data-table tbody tr.row-active{background:rgba(132,6,31,.08)}
.badge{display:inline-flex;align-items:center;padding:.3rem .65rem;border-radius:20px;font-size:.75rem;font-weight:600;background:var(--light);color:var(--gray)}
.badge-ok,.badge-success{background:rgba(39,174,96,.15);color:var(--success)}
.badge-danger{background:rgba(231,76,60,.15);color:var(--danger)}
.badge-warning{background:rgba(243,156,18,.15);color:#d68910}
.badge-info{background:rgba(52,152,219,.15);color:var(--info)}
.status-planificada{color:var(--info);font-weight:600}
.status-en_proceso{color:var(--warning);font-weight:600}
.status-completada{color:var(--success);font-weight:600}
.empty{text-align:center;padding:3rem 2rem;color:var(--gray)}
.empty i{font-size:3rem;opacity:.3;display:block;margin-bottom:.75rem}
@media(max-width:992px){.form-row.four{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){.content-body{padding:1rem}.page-header{flex-direction:column;align-items:flex-start}.form-row,.form-row.four{grid-template-columns:1fr}.check-grid{grid-template-columns:1fr}.form-actions{flex-direction:column}.form-actions .btn{width:100%}.result-box{flex-direction:column;text-align:center}.result-action{margin-left:0;margin-top:1rem;text-align:center}.guide-box{flex-direction:column}.guide-icon{align-self:flex-start}.info-bar{flex-direction:column;text-align:center}.tab-btn{min-width:70px;padding:.75rem .3rem;font-size:.7rem}.tab-num{width:24px;height:24px;font-size:.75rem}.limits-display{flex-direction:column}}
@media(max-width:480px){.tab-btn span:last-child{display:none}.tab-btn{min-width:45px}}
</style>

<script>
function irTab(tabId){
    var btn=document.querySelector('.tab-btn[data-tab="'+tabId+'"]');
    if(btn&&!btn.disabled)btn.click();
}
document.addEventListener('DOMContentLoaded',function(){
    document.querySelectorAll('.tab-btn').forEach(function(btn){
        btn.addEventListener('click',function(){
            if(this.disabled)return;
            var tabId=this.getAttribute('data-tab');
            document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active')});
            document.querySelectorAll('.tab-panel').forEach(function(p){p.classList.remove('active')});
            this.classList.add('active');
            var panel=document.getElementById('tab-'+tabId);
            if(panel)panel.classList.add('active');
            var url=new URL(window.location.href);
            url.searchParams.set('tab',tabId);
            window.history.replaceState({},'',url.toString());
        });
    });
    if(navigator.geolocation){
        navigator.geolocation.getCurrentPosition(function(pos){
            var lat=document.getElementById('lat');
            var lng=document.getElementById('lng');
            if(lat)lat.value=pos.coords.latitude;
            if(lng)lng.value=pos.coords.longitude;
        });
    }
    setTimeout(function(){
        document.querySelectorAll('.alert').forEach(function(a){
            a.style.transition='opacity 0.5s';
            a.style.opacity='0';
            setTimeout(function(){a.style.display='none'},500);
        });
    },8000);
});
function previewResultado(valor){
    var limite=<?php echo floatval($config['limite_alcohol_permisible'] ?? 0); ?>;
    var preview=document.getElementById('preview-resultado');
    if(!preview)return;
    var nivel=parseFloat(valor)||0;
    if(valor!==''&&nivel>=0){
        preview.classList.add('show');
        if(nivel>limite){
            preview.className='show pos';
            preview.innerHTML='<i class="fas fa-exclamation-triangle"></i> POSITIVO - Supera '+limite.toFixed(3)+' g/L';
        }else{
            preview.className='show neg';
            preview.innerHTML='<i class="fas fa-check-circle"></i> NEGATIVO - Dentro del límite';
        }
    }else{preview.classList.remove('show')}
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
