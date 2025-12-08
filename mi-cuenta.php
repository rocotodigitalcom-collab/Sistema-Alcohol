<?php
// INICIAR SESIÓN PRIMERO
session_start();

// mi-cuenta.php - VERSIÓN COMPLETA CON GESTIÓN DE PLANES PARA SUPER ADMIN
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

// ====================================================================
// FUNCIÓN HELPER PARA CONVERTIR HEX A RGB
// ====================================================================
function hexToRgb($hex) {
    $hex = str_replace('#', '', $hex);
    if(strlen($hex) == 3) {
        $r = hexdec(substr($hex,0,1).substr($hex,0,1));
        $g = hexdec(substr($hex,1,1).substr($hex,1,1));
        $b = hexdec(substr($hex,2,1).substr($hex,2,1));
    } else {
        $r = hexdec(substr($hex,0,2));
        $g = hexdec(substr($hex,2,2));
        $b = hexdec(substr($hex,4,2));
    }
    return $r . ', ' . $g . ', ' . $b;
}

// ====================================================================
// ENDPOINT AJAX PARA OBTENER DATOS DE UN PLAN
// ====================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'obtener_plan' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    
    $db = new Database();
    $plan_id = intval($_GET['id']);
    
    $plan = $db->fetchOne("SELECT * FROM planes WHERE id = ?", [$plan_id]);
    
    if ($plan) {
        echo json_encode([
            'success' => true,
            'plan' => $plan
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Plan no encontrado'
        ]);
    }
    exit;
}

// ====================================================================
// ENDPOINT AJAX PARA OBTENER DATOS DE UN CLIENTE
// ====================================================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'obtener_cliente' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    
    $db = new Database();
    $cliente_id = intval($_GET['id']);
    
    $cliente = $db->fetchOne("
        SELECT c.*, p.nombre_plan 
        FROM clientes c 
        LEFT JOIN planes p ON c.plan_id = p.id 
        WHERE c.id = ?
    ", [$cliente_id]);
    
    if ($cliente) {
        echo json_encode([
            'success' => true,
            'cliente' => $cliente
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Cliente no encontrado'
        ]);
    }
    exit;
}

// ====================================================================
// PROCESAMIENTO PRINCIPAL
// ====================================================================
$page_title = 'Mi Cuenta';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'mi-cuenta.php' => 'Mi Cuenta'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;
$rol_usuario = $_SESSION['user_role'] ?? '';

// Verificar si es super admin
$es_super_admin = ($rol_usuario === 'super_admin');

// ====================================================================
// PROCESAMIENTO DE FORMULARIOS POST
// ====================================================================

$mensaje_exito = '';
$mensaje_error = '';

// 1. ACTUALIZAR PERFIL
if (isset($_POST['actualizar_perfil'])) {
    $nombre = $_POST['nombre'] ?? '';
    $apellido = $_POST['apellido'] ?? '';
    $telefono = $_POST['telefono'] ?? '';
    $dni = $_POST['dni'] ?? '';
    
    if (empty($nombre) || empty($apellido)) {
        $mensaje_error = 'El nombre y apellido son obligatorios.';
    } else {
        try {
            $db->execute(
                "UPDATE usuarios SET nombre = ?, apellido = ?, telefono = ?, dni = ? WHERE id = ?",
                [$nombre, $apellido, $telefono, $dni, $user_id]
            );
            
            // Refrescar los datos después de actualizar
            $_SESSION['user_nombre'] = $nombre;
            $_SESSION['user_apellido'] = $apellido;
            
            $mensaje_exito = 'Perfil actualizado correctamente.';
        } catch (Exception $e) {
            $mensaje_error = 'Error al actualizar el perfil: ' . $e->getMessage();
        }
    }
}

// 2. CAMBIAR CONTRASEÑA
if (isset($_POST['cambiar_password'])) {
    $password_actual = $_POST['password_actual'] ?? '';
    $nuevo_password = $_POST['nuevo_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    
    if (empty($password_actual) || empty($nuevo_password) || empty($confirmar_password)) {
        $mensaje_error = 'Todos los campos de contraseña son obligatorios.';
    } elseif ($nuevo_password !== $confirmar_password) {
        $mensaje_error = 'Las nuevas contraseñas no coinciden.';
    } elseif (strlen($nuevo_password) < 6) {
        $mensaje_error = 'La nueva contraseña debe tener al menos 6 caracteres.';
    } else {
        $usuario = $db->fetchOne("SELECT password FROM usuarios WHERE id = ?", [$user_id]);
        if ($usuario && password_verify($password_actual, $usuario['password'])) {
            $hash_nuevo = password_hash($nuevo_password, PASSWORD_DEFAULT);
            try {
                $db->execute(
                    "UPDATE usuarios SET password = ? WHERE id = ?",
                    [$hash_nuevo, $user_id]
                );
                $mensaje_exito = 'Contraseña cambiada correctamente.';
            } catch (Exception $e) {
                $mensaje_error = 'Error al cambiar la contraseña: ' . $e->getMessage();
            }
        } else {
            $mensaje_error = 'La contraseña actual es incorrecta.';
        }
    }
}

// 3. ACTUALIZAR PREFERENCIAS
if (isset($_POST['actualizar_preferencias'])) {
    $timezone = $_POST['timezone'] ?? 'America/Lima';
    $idioma = $_POST['idioma'] ?? 'es';
    $formato_fecha = $_POST['formato_fecha'] ?? 'd/m/Y';
    
    try {
        $existe = $db->fetchOne("SELECT id FROM configuraciones_usuario WHERE user_id = ?", [$user_id]);
        
        if ($existe) {
            $db->execute(
                "UPDATE configuraciones_usuario SET timezone = ?, idioma = ?, formato_fecha = ? WHERE user_id = ?",
                [$timezone, $idioma, $formato_fecha, $user_id]
            );
        } else {
            $db->execute(
                "INSERT INTO configuraciones_usuario (user_id, timezone, idioma, formato_fecha) VALUES (?, ?, ?, ?)",
                [$user_id, $timezone, $idioma, $formato_fecha]
            );
        }
        
        $mensaje_exito = 'Preferencias actualizadas correctamente.';
    } catch (Exception $e) {
        $mensaje_error = 'Error al actualizar las preferencias: ' . $e->getMessage();
    }
}

// 4. ACTUALIZAR TEMA PERSONALIZADO
if (isset($_POST['actualizar_tema'])) {
    $color_primario = $_POST['color_primario'] ?? '#2c3e50';
    $color_secundario = $_POST['color_secundario'] ?? '#3498db';
    $color_exito = $_POST['color_exito'] ?? '#27ae60';
    $color_error = $_POST['color_error'] ?? '#e74c3c';
    $color_advertencia = $_POST['color_advertencia'] ?? '#f39c12';
    $fuente_principal = $_POST['fuente_principal'] ?? 'Roboto';
    $tamanio_fuente = intval($_POST['tamanio_fuente'] ?? 14);
    $border_radius = intval($_POST['border_radius'] ?? 4);
    
    try {
        $db->execute(
            "UPDATE temas_personalizados SET activo = 0 WHERE cliente_id = ?",
            [$cliente_id]
        );
        
        $db->execute(
            "INSERT INTO temas_personalizados (cliente_id, color_primario, color_secundario, color_exito, color_error, color_advertencia, fuente_principal, tamanio_fuente, border_radius, activo) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
            [$cliente_id, $color_primario, $color_secundario, $color_exito, $color_error, $color_advertencia, $fuente_principal, $tamanio_fuente, $border_radius]
        );
        
        $mensaje_exito = 'Tema personalizado actualizado correctamente.';
    } catch (Exception $e) {
        $mensaje_error = 'Error al actualizar el tema: ' . $e->getMessage();
    }
}

// 5. CREAR NUEVO PLAN (solo super admin)
if (isset($_POST['crear_plan']) && $es_super_admin) {
    $nombre_plan = $_POST['nombre_plan'] ?? '';
    $precio_mensual = floatval($_POST['precio_mensual'] ?? 0);
    $descripcion_plan = $_POST['descripcion_plan'] ?? '';
    $limite_usuarios = intval($_POST['limite_usuarios'] ?? 5);
    $limite_conductores = intval($_POST['limite_conductores'] ?? 50);
    $limite_vehiculos = intval($_POST['limite_vehiculos'] ?? 50);
    $limite_pruebas_mes = intval($_POST['limite_pruebas_mes'] ?? 1000);
    $limite_alcoholimetros = intval($_POST['limite_alcoholimetros'] ?? 10);
    $almacenamiento_fotos = intval($_POST['almacenamiento_fotos'] ?? 100);
    $retencion_datos_meses = intval($_POST['retencion_datos_meses'] ?? 12);
    $reportes_avanzados = isset($_POST['reportes_avanzados']) ? 1 : 0;
    $soporte_prioritario = isset($_POST['soporte_prioritario']) ? 1 : 0;
    $acceso_api = isset($_POST['acceso_api']) ? 1 : 0;
    $backup_automatico = isset($_POST['backup_automatico']) ? 1 : 0;
    $integraciones = isset($_POST['integraciones']) ? 1 : 0;
    $multi_sede = isset($_POST['multi_sede']) ? 1 : 0;
    $personalizacion = isset($_POST['personalizacion']) ? 1 : 0;
    
    if (empty($nombre_plan)) {
        $mensaje_error = 'El nombre del plan es obligatorio.';
    } elseif ($precio_mensual < 0) {
        $mensaje_error = 'El precio mensual no puede ser negativo.';
    } else {
        try {
            $db->execute(
                "INSERT INTO planes (nombre_plan, precio_mensual, descripcion, limite_usuarios, limite_conductores, limite_vehiculos, limite_pruebas_mes, limite_alcoholimetros, almacenamiento_fotos, retencion_datos_meses, reportes_avanzados, soporte_prioritario, acceso_api, backup_automatico, integraciones, multi_sede, personalizacion, estado) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)",
                [$nombre_plan, $precio_mensual, $descripcion_plan, $limite_usuarios, $limite_conductores, $limite_vehiculos, $limite_pruebas_mes, $limite_alcoholimetros, $almacenamiento_fotos, $retencion_datos_meses, $reportes_avanzados, $soporte_prioritario, $acceso_api, $backup_automatico, $integraciones, $multi_sede, $personalizacion]
            );
            $mensaje_exito = 'Plan creado correctamente.';
        } catch (Exception $e) {
            $mensaje_error = 'Error al crear el plan: ' . $e->getMessage();
        }
    }
}

// 6. EDITAR PLAN (solo super admin) - CORRECCIÓN 1: Permitir precio 0 para plan Free
if (isset($_POST['editar_plan']) && $es_super_admin) {
    $plan_id = intval($_POST['plan_id_editar'] ?? 0);
    $nombre_plan = $_POST['nombre_plan_editar'] ?? '';
    $precio_mensual = floatval($_POST['precio_mensual_editar'] ?? 0);
    $descripcion_plan = $_POST['descripcion_plan_editar'] ?? '';
    $limite_usuarios = intval($_POST['limite_usuarios_editar'] ?? 5);
    $limite_conductores = intval($_POST['limite_conductores_editar'] ?? 50);
    $limite_vehiculos = intval($_POST['limite_vehiculos_editar'] ?? 50);
    $limite_pruebas_mes = intval($_POST['limite_pruebas_mes_editar'] ?? 1000);
    $limite_alcoholimetros = intval($_POST['limite_alcoholimetros_editar'] ?? 10);
    $almacenamiento_fotos = intval($_POST['almacenamiento_fotos_editar'] ?? 100);
    $retencion_datos_meses = intval($_POST['retencion_datos_meses_editar'] ?? 12);
    $reportes_avanzados = isset($_POST['reportes_avanzados_editar']) ? 1 : 0;
    $soporte_prioritario = isset($_POST['soporte_prioritario_editar']) ? 1 : 0;
    $acceso_api = isset($_POST['acceso_api_editar']) ? 1 : 0;
    $backup_automatico = isset($_POST['backup_automatico_editar']) ? 1 : 0;
    $integraciones = isset($_POST['integraciones_editar']) ? 1 : 0;
    $multi_sede = isset($_POST['multi_sede_editar']) ? 1 : 0;
    $personalizacion = isset($_POST['personalizacion_editar']) ? 1 : 0;
    $estado_plan = isset($_POST['estado_plan_editar']) ? 1 : 0;
    
    if (empty($nombre_plan)) {
        $mensaje_error = 'El nombre del plan es obligatorio.';
    } elseif ($precio_mensual < 0) {
        $mensaje_error = 'El precio mensual no puede ser negativo.';
    } else {
        try {
            $db->execute(
                "UPDATE planes SET 
                    nombre_plan = ?, 
                    precio_mensual = ?, 
                    descripcion = ?, 
                    limite_usuarios = ?, 
                    limite_conductores = ?, 
                    limite_vehiculos = ?, 
                    limite_pruebas_mes = ?, 
                    limite_alcoholimetros = ?, 
                    almacenamiento_fotos = ?, 
                    retencion_datos_meses = ?, 
                    reportes_avanzados = ?, 
                    soporte_prioritario = ?, 
                    acceso_api = ?, 
                    backup_automatico = ?, 
                    integraciones = ?, 
                    multi_sede = ?, 
                    personalizacion = ?, 
                    estado = ? 
                WHERE id = ?",
                [$nombre_plan, $precio_mensual, $descripcion_plan, $limite_usuarios, $limite_conductores, $limite_vehiculos, $limite_pruebas_mes, $limite_alcoholimetros, $almacenamiento_fotos, $retencion_datos_meses, $reportes_avanzados, $soporte_prioritario, $acceso_api, $backup_automatico, $integraciones, $multi_sede, $personalizacion, $estado_plan, $plan_id]
            );
            $mensaje_exito = 'Plan actualizado correctamente.';
        } catch (Exception $e) {
            $mensaje_error = 'Error al actualizar el plan: ' . $e->getMessage();
        }
    }
}

// 7. CREAR NUEVA EMPRESA (solo super admin) - CORRECCIÓN 2: Nueva funcionalidad
if (isset($_POST['crear_cliente']) && $es_super_admin) {
    $nombre_empresa = $_POST['nombre_empresa_nuevo'] ?? '';
    $ruc = $_POST['ruc_nuevo'] ?? '';
    $email_contacto = $_POST['email_contacto_nuevo'] ?? '';
    $telefono_contacto = $_POST['telefono_contacto_nuevo'] ?? '';
    $plan_id_nuevo = intval($_POST['plan_id_nuevo'] ?? 0);
    
    if (empty($nombre_empresa) || empty($ruc) || empty($email_contacto)) {
        $mensaje_error = 'Nombre de empresa, RUC y email de contacto son obligatorios.';
    } else {
        try {
            $db->execute(
                "INSERT INTO clientes (nombre_empresa, ruc, email_contacto, telefono_contacto, plan_id, estado, fecha_registro) 
                 VALUES (?, ?, ?, ?, ?, 'activo', NOW())",
                [$nombre_empresa, $ruc, $email_contacto, $telefono_contacto, $plan_id_nuevo > 0 ? $plan_id_nuevo : NULL]
            );
            
            // Registrar en auditoría
            $db->execute(
                "INSERT INTO auditoria (usuario_id, accion, detalles, ip_address) VALUES (?, ?, ?, ?)",
                [$user_id, 'CREAR_EMPRESA', 'Nueva empresa creada: ' . $nombre_empresa, $_SERVER['REMOTE_ADDR'] ?? '']
            );
            
            $mensaje_exito = 'Empresa creada correctamente.';
        } catch (Exception $e) {
            $mensaje_error = 'Error al crear la empresa: ' . $e->getMessage();
        }
    }
}

// 8. ASIGNAR PLAN A CLIENTE (solo super admin)
if (isset($_POST['asignar_plan']) && $es_super_admin) {
    $cliente_id_asignar = intval($_POST['cliente_id'] ?? 0);
    $plan_id_asignar = intval($_POST['plan_id'] ?? 0);
    $motivo_asignacion = $_POST['motivo_asignacion'] ?? '';
    
    if (empty($cliente_id_asignar) || empty($plan_id_asignar)) {
        $mensaje_error = 'Debe seleccionar una empresa y un plan.';
    } else {
        try {
            // AÑADIR VALIDACIÓN ANTES DEL UPDATE:
            $cliente_existente = $db->fetchOne("SELECT id FROM clientes WHERE id = ?", [$cliente_id_asignar]);
            $plan_existente = $db->fetchOne("SELECT id FROM planes WHERE id = ?", [$plan_id_asignar]);
            
            if (!$cliente_existente) {
                $mensaje_error = 'La empresa seleccionada no existe.';
            } elseif (!$plan_existente) {
                $mensaje_error = 'El plan seleccionado no existe.';
            } else {
                $db->execute(
                    "UPDATE clientes SET plan_id = ? WHERE id = ?",
                    [$plan_id_asignar, $cliente_id_asignar]
                );
                
                // Registrar en auditoría
                $db->execute(
                    "INSERT INTO auditoria (usuario_id, accion, detalles, ip_address) VALUES (?, ?, ?, ?)",
                    [$user_id, 'ASIGNAR_PLAN', 'Plan ' . $plan_id_asignar . ' asignado a empresa ' . $cliente_id_asignar . ' - Motivo: ' . $motivo_asignacion, $_SERVER['REMOTE_ADDR'] ?? '']
                );
                
                $mensaje_exito = 'Plan asignado correctamente.';
            }
        } catch (Exception $e) {
            $mensaje_error = 'Error al asignar el plan: ' . $e->getMessage();
        }
    }
}

// 9. EDITAR CLIENTE (solo super admin) - CORRECCIÓN 2: Actualizar con plan
if (isset($_POST['editar_cliente']) && $es_super_admin) {
    $cliente_id_editar = intval($_POST['cliente_id_editar'] ?? 0);
    $nombre_empresa = $_POST['nombre_empresa_editar'] ?? '';
    $ruc = $_POST['ruc_editar'] ?? '';
    $email_contacto = $_POST['email_contacto_editar'] ?? '';
    $telefono_contacto = $_POST['telefono_contacto_editar'] ?? '';
    $plan_id_editar = intval($_POST['plan_id_editar'] ?? 0);
    $estado_cliente = $_POST['estado_cliente_editar'] ?? 'activo';
    
    if (empty($nombre_empresa) || empty($ruc) || empty($email_contacto)) {
        $mensaje_error = 'Nombre de empresa, RUC y email de contacto son obligatorios.';
    } else {
        try {
            $db->execute(
                "UPDATE clientes SET nombre_empresa = ?, ruc = ?, email_contacto = ?, telefono_contacto = ?, plan_id = ?, estado = ? WHERE id = ?",
                [$nombre_empresa, $ruc, $email_contacto, $telefono_contacto, $plan_id_editar > 0 ? $plan_id_editar : NULL, $estado_cliente, $cliente_id_editar]
            );
            $mensaje_exito = 'Empresa actualizada correctamente.';
        } catch (Exception $e) {
            $mensaje_error = 'Error al actualizar la empresa: ' . $e->getMessage();
        }
    }
}

// 10. ELIMINAR PLAN (solo super admin)
if (isset($_POST['eliminar_plan']) && $es_super_admin) {
    $plan_id_eliminar = intval($_POST['plan_id_eliminar'] ?? 0);
    $motivo_eliminacion = $_POST['motivo_eliminacion'] ?? '';
    
    if (empty($plan_id_eliminar) || empty($motivo_eliminacion)) {
        $mensaje_error = 'Debe proporcionar un motivo para la eliminación.';
    } else {
        $clientes_con_plan = $db->fetchAll("SELECT id FROM clientes WHERE plan_id = ?", [$plan_id_eliminar]);
        if (count($clientes_con_plan) > 0) {
            $mensaje_error = 'No se puede eliminar el plan porque hay clientes asignados a él. Cambie primero a esos clientes a otro plan.';
        } else {
            try {
                $db->execute("DELETE FROM planes WHERE id = ?", [$plan_id_eliminar]);
                $mensaje_exito = 'Plan eliminado correctamente.';
            } catch (Exception $e) {
                $mensaje_error = 'Error al eliminar el plan: ' . $e->getMessage();
            }
        }
    }
}

// ====================================================================
// OBTENER DATOS ACTUALIZADOS DESPUÉS DE PROCESAR FORMULARIOS
// ====================================================================

// Obtener información del usuario actual (refrescar después de posibles actualizaciones)
$usuario_actual = $db->fetchOne("
    SELECT u.*, c.nombre_empresa, c.plan_id, p.nombre_plan, c.estado as estado_cliente
    FROM usuarios u 
    LEFT JOIN clientes c ON u.cliente_id = c.id 
    LEFT JOIN planes p ON c.plan_id = p.id 
    WHERE u.id = ?
", [$user_id]);

// Obtener configuración del usuario
$configuracion = $db->fetchOne("SELECT * FROM configuraciones_usuario WHERE user_id = ?", [$user_id]);

// Obtener historial de actividad reciente
$historial_actividad = $db->fetchAll("
    SELECT accion, detalles, fecha_accion, ip_address 
    FROM auditoria 
    WHERE usuario_id = ? 
    ORDER BY fecha_accion DESC 
    LIMIT 10
", [$user_id]);

// Obtener todos los planes (para super admin)
$planes = [];
if ($es_super_admin) {
    $planes = $db->fetchAll("SELECT * FROM planes ORDER BY precio_mensual ASC");
}

// Obtener todos los clientes (para super admin)
$clientes_todos = [];
if ($es_super_admin) {
    $clientes_todos = $db->fetchAll("
        SELECT c.*, p.nombre_plan 
        FROM clientes c 
        LEFT JOIN planes p ON c.plan_id = p.id 
        ORDER BY c.nombre_empresa ASC
    ");
}

// Obtener tema personalizado actual
$tema_actual = $db->fetchOne("
    SELECT * FROM temas_personalizados 
    WHERE cliente_id = ? AND activo = 1 
    ORDER BY fecha_creacion DESC 
    LIMIT 1
", [$cliente_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Cuenta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    /* ESTILOS COMPLETOS PARA MI CUENTA */
    :root {
        --color-primario: <?php echo htmlspecialchars($tema_actual['color_primario'] ?? '#2c3e50'); ?>;
        --color-secundario: <?php echo htmlspecialchars($tema_actual['color_secundario'] ?? '#3498db'); ?>;
        --color-exito: <?php echo htmlspecialchars($tema_actual['color_exito'] ?? '#27ae60'); ?>;
        --color-error: <?php echo htmlspecialchars($tema_actual['color_error'] ?? '#e74c3c'); ?>;
        --color-advertencia: <?php echo htmlspecialchars($tema_actual['color_advertencia'] ?? '#f39c12'); ?>;
        --font-primary: 'Segoe UI', Roboto, Arial, sans-serif;
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: var(--font-primary);
        background: #f5f7fa;
        color: #333;
        line-height: 1.6;
    }

    .content-body {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }

    .dashboard-header {
        background: white;
        border-radius: 12px;
        padding: 2rem;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        flex-wrap: wrap;
        gap: 1.5rem;
    }

    .welcome-section h1 {
        font-size: 2rem;
        color: var(--color-primario);
        margin-bottom: 0.5rem;
    }

    .dashboard-subtitle {
        color: #6c757d;
        font-size: 1.1rem;
        margin-bottom: 1rem;
    }

    .super-admin-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
        padding: 0.75rem 1.25rem;
        border-radius: 20px;
        font-size: 0.95rem;
        font-weight: 500;
        margin-top: 0.75rem;
        border: 2px solid rgba(231, 76, 60, 0.2);
    }

    .header-actions {
        display: flex;
        gap: 1rem;
        align-items: center;
        flex-wrap: wrap;
    }

    .user-badge {
        background: var(--color-secundario);
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .stats-badge {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.9rem;
        color: #495057;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .account-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 2rem;
        margin-top: 1rem;
    }

    @media (max-width: 1024px) {
        .account-container {
            grid-template-columns: 1fr;
        }
    }

    .account-sidebar {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        height: fit-content;
    }

    .user-profile-card {
        padding: 2rem;
        text-align: center;
        border-bottom: 1px solid #eee;
    }

    .avatar-initials-large {
        width: 100px;
        height: 100px;
        background: linear-gradient(135deg, var(--color-primario) 0%, var(--color-secundario) 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        color: white;
        font-weight: bold;
        margin: 0 auto 1rem;
    }

    .user-info-summary h3 {
        color: var(--color-primario);
        margin-bottom: 0.5rem;
    }

    .user-email {
        color: #6c757d;
        margin-bottom: 1rem;
    }

    .role-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .role-badge.super_admin {
        background: rgba(231, 76, 60, 0.1);
        color: #e74c3c;
    }

    .role-badge.admin {
        background: rgba(52, 152, 219, 0.1);
        color: #3498db;
    }

    .user-stats {
        margin-top: 1.5rem;
        padding-top: 1.5rem;
        border-top: 1px solid #eee;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 0.75rem;
        color: #495057;
    }

    .stat-item i {
        color: var(--color-secundario);
        width: 20px;
    }

    .account-nav {
        padding: 1rem 0;
    }

    .nav-section-divider {
        padding: 1rem 1.5rem;
        color: #6c757d;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-top: 1px solid #eee;
        margin-top: 0.5rem;
    }

    .nav-item {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        color: #495057;
        text-decoration: none;
        transition: all 0.3s ease;
        border-left: 4px solid transparent;
        position: relative;
        cursor: pointer;
        gap: 0.75rem;
    }

    .nav-item:hover {
        background: #f8f9fa;
        color: var(--color-primario);
    }

    .nav-item.active {
        background: rgba(52, 152, 219, 0.1);
        color: var(--color-secundario);
        border-left-color: var(--color-secundario);
        font-weight: 500;
    }

    .nav-badge {
        background: var(--color-secundario);
        color: white;
        padding: 0.25rem 0.5rem;
        border-radius: 10px;
        font-size: 0.75rem;
        margin-left: auto;
    }

    .tab-content {
        display: none;
        animation: fadeIn 0.3s ease;
    }

    .tab-content.active {
        display: block;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
        overflow: hidden;
    }

    .card-header {
        padding: 1.5rem;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .card-header h3 {
        color: var(--color-primario);
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .card-body {
        padding: 1.5rem;
    }

    .account-form {
        max-width: 100%;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1.5rem;
        margin-bottom: 1.5rem;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--color-primario);
    }

    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        font-size: 1rem;
        transition: border-color 0.3s;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--color-secundario);
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
    }

    .form-actions {
        display: flex;
        gap: 1rem;
        margin-top: 2rem;
        padding-top: 1.5rem;
        border-top: 1px solid #eee;
    }

    .btn {
        padding: 0.75rem 1.5rem;
        border: none;
        border-radius: 8px;
        font-size: 1rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary {
        background: var(--color-secundario);
        color: white;
    }

    .btn-primary:hover {
        background: #2980b9;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(52, 152, 219, 0.3);
    }

    .btn-outline {
        background: white;
        color: var(--color-secundario);
        border: 2px solid var(--color-secundario);
    }

    .btn-outline:hover {
        background: var(--color-secundario);
        color: white;
    }

    .btn-danger {
        background: var(--color-error);
        color: white;
    }

    .btn-danger:hover {
        background: #c0392b;
    }

    /* ESTILOS PARA SUPER ADMIN */
    .admin-stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .admin-stat {
        background: white;
        border-radius: 10px;
        padding: 1.5rem;
        border: 1px solid #dee2e6;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .admin-stat-icon {
        width: 50px;
        height: 50px;
        background: rgba(52, 152, 219, 0.1);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-secundario);
        font-size: 1.5rem;
    }

    .admin-stat-info h4 {
        font-size: 1.5rem;
        color: var(--color-primario);
        margin-bottom: 0.25rem;
    }

    .admin-stat-info span {
        color: #6c757d;
        font-size: 0.9rem;
    }

    .admin-planes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 1.5rem;
        margin-top: 2rem;
    }

    .admin-plan-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        border: 2px solid #dee2e6;
        transition: all 0.3s ease;
    }

    .admin-plan-card.activo {
        border-top-color: var(--color-exito);
    }

    .admin-plan-card.inactivo {
        border-top-color: #6c757d;
        opacity: 0.7;
    }

    .admin-plan-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1.5rem;
    }

    .admin-plan-title h4 {
        color: var(--color-primario);
        margin-bottom: 0.5rem;
    }

    .admin-plan-price {
        font-size: 1.75rem;
        font-weight: 700;
        color: var(--color-secundario);
        text-align: right;
    }

    .admin-plan-price small {
        font-size: 0.9rem;
        color: #6c757d;
    }

    .admin-plan-features {
        margin-bottom: 1.5rem;
    }

    .feature-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.75rem;
        color: #495057;
    }

    .premium-features {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 1rem;
        padding-top: 1rem;
        border-top: 1px solid #eee;
    }

    .premium-badge {
        background: rgba(52, 152, 219, 0.1);
        color: var(--color-secundario);
        padding: 0.25rem 0.5rem;
        border-radius: 6px;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
    }

    .admin-plan-actions {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .admin-plan-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-top: 1rem;
        border-top: 1px solid #eee;
        font-size: 0.9rem;
    }

    .plan-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-weight: 500;
        font-size: 0.8rem;
    }

    .plan-status.activo {
        background: rgba(39, 174, 96, 0.1);
        color: var(--color-exito);
    }

    .plan-status.inactivo {
        background: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }

    /* TABLAS */
    .table-responsive {
        overflow-x: auto;
        margin-bottom: 1.5rem;
    }

    .admin-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .admin-table th {
        background: #f8f9fa;
        padding: 1rem;
        text-align: left;
        font-weight: 600;
        color: var(--color-primario);
        border-bottom: 2px solid #dee2e6;
    }

    .admin-table td {
        padding: 1rem;
        border-bottom: 1px solid #eee;
    }

    .admin-table tr:hover {
        background: #f8f9fa;
    }

    .status-tag {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .status-tag.activo {
        background: rgba(39, 174, 96, 0.1);
        color: var(--color-exito);
    }

    .status-tag.inactivo {
        background: rgba(108, 117, 125, 0.1);
        color: #6c757d;
    }

    /* MODALES */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
        padding: 1rem;
    }

    .modal-content {
        background: white;
        border-radius: 12px;
        max-width: 800px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    }

    .modal-lg {
        max-width: 1000px;
    }

    .modal-sm {
        max-width: 500px;
    }

    .modal-header {
        padding: 1.5rem;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .modal-header h4 {
        color: var(--color-primario);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .modal-close {
        background: none;
        border: none;
        font-size: 1.5rem;
        color: #6c757d;
        cursor: pointer;
        padding: 0.25rem;
        line-height: 1;
    }

    .modal-body {
        padding: 1.5rem;
    }

    .modal-footer {
        padding: 1.5rem;
        border-top: 1px solid #eee;
        display: flex;
        justify-content: flex-end;
        gap: 1rem;
    }

    /* ALERTAS */
    .alert {
        padding: 1rem 1.5rem;
        border-radius: 8px;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .alert-success {
        background: rgba(39, 174, 96, 0.1);
        border-left: 4px solid var(--color-exito);
        color: #155724;
    }

    .alert-danger {
        background: rgba(231, 76, 60, 0.1);
        border-left: 4px solid var(--color-error);
        color: #721c24;
    }

    /* ESTADOS VACÍOS */
    .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6c757d;
    }

    .empty-icon {
        font-size: 3rem;
        color: #dee2e6;
        margin-bottom: 1rem;
    }

    .empty-state h3 {
        color: var(--color-primario);
        margin-bottom: 0.5rem;
    }

    /* ACTIVITY TIMELINE */
    .activity-timeline {
        border-left: 2px solid #dee2e6;
        margin-left: 1rem;
        padding-left: 2rem;
    }

    .activity-item {
        position: relative;
        padding-bottom: 2rem;
    }

    .activity-item:last-child {
        padding-bottom: 0;
    }

    .activity-icon {
        position: absolute;
        left: -2.7rem;
        top: 0;
        background: white;
        border: 2px solid #dee2e6;
        width: 2rem;
        height: 2rem;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .activity-content {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 8px;
        border: 1px solid #dee2e6;
    }

    .activity-title {
        font-weight: 600;
        color: var(--color-primario);
        margin-bottom: 0.5rem;
    }

    .activity-meta {
        display: flex;
        gap: 1.5rem;
        margin-top: 0.75rem;
        font-size: 0.85rem;
        color: #6c757d;
    }

    .activity-meta i {
        margin-right: 0.25rem;
    }

    /* FORMULARIOS ESPECIALES */
    .password-field {
        position: relative;
    }

    .toggle-password {
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: #6c757d;
        cursor: pointer;
    }

    .color-picker-group {
        display: flex;
        gap: 0.5rem;
    }

    .color-input {
        width: 60px;
        height: 45px;
        padding: 0;
        border: none;
        cursor: pointer;
    }

    .color-text {
        flex: 1;
    }

    .range-input {
        width: 100%;
        height: 8px;
        border-radius: 4px;
        background: #dee2e6;
        outline: none;
        -webkit-appearance: none;
    }

    .range-value {
        text-align: center;
        margin-top: 0.5rem;
        font-weight: 500;
        color: var(--color-primario);
    }

    .theme-preview {
        border: 1px solid #dee2e6;
        border-radius: 8px;
        overflow: hidden;
        margin-top: 1rem;
    }

    .preview-header {
        background: var(--color-primario);
        padding: 1rem;
        color: white;
    }

    .preview-body {
        padding: 1rem;
        display: flex;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .preview-btn {
        padding: 0.5rem 1rem;
        border-radius: 6px;
    }

    .preview-alert {
        padding: 0.75rem 1rem;
        border-left: 4px solid;
        border-radius: 4px;
        display: flex;
        align-items: center;
        gap: 0.75rem;
        width: 100%;
    }

    /* RESPONSIVE */
    @media (max-width: 768px) {
        .dashboard-header {
            flex-direction: column;
            text-align: center;
        }
        
        .header-actions {
            justify-content: center;
        }
        
        .admin-planes-grid {
            grid-template-columns: 1fr;
        }
        
        .form-grid {
            grid-template-columns: 1fr;
        }
        
        .card-header {
            flex-direction: column;
            gap: 1rem;
            text-align: center;
        }
        
        .card-actions {
            width: 100%;
            justify-content: center;
        }
        
        .modal-content {
            margin: 1rem;
        }
    }

    /* BOTONES PEQUEÑOS */
    .btn-sm {
        padding: 0.5rem 1rem;
        font-size: 0.9rem;
    }

    .btn-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        background: none;
        border: 1px solid #dee2e6;
        color: #6c757d;
        cursor: pointer;
        transition: all 0.3s;
    }

    .btn-icon:hover {
        background: #f8f9fa;
        color: var(--color-secundario);
    }

    /* ADMIN FILTROS */
    .admin-filtros {
        display: flex;
        gap: 1rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .admin-filtros .form-control {
        flex: 1;
        min-width: 200px;
    }

    /* REPORTES */
    .reportes-dashboard {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .reporte-card {
        background: white;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid #dee2e6;
    }

    .reporte-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
        border-bottom: 1px solid #eee;
        padding-bottom: 1rem;
    }

    .reporte-header h4 {
        color: var(--color-primario);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .distribucion-item {
        margin-bottom: 1rem;
    }

    .distribucion-info {
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.5rem;
    }

    .distribucion-bar {
        height: 8px;
        background: #dee2e6;
        border-radius: 4px;
        overflow: hidden;
    }

    .bar-fill {
        height: 100%;
        background: var(--color-secundario);
        border-radius: 4px;
        transition: width 0.3s ease;
    }

    .ingresos-table {
        width: 100%;
        border-collapse: collapse;
    }

    .ingresos-table th,
    .ingresos-table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid #eee;
    }

    .ingresos-table th {
        background: #f8f9fa;
        font-weight: 600;
        color: var(--color-primario);
    }

    .total-row {
        background: #f8f9fa;
        font-weight: 600;
    }

    .crecimiento-chart {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        height: 200px;
        padding: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        margin-bottom: 1.5rem;
    }

    .mes-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        flex: 1;
        height: 100%;
    }

    .mes-bar {
        background: var(--color-secundario);
        width: 30px;
        border-radius: 4px 4px 0 0;
        position: relative;
        transition: height 0.3s ease;
    }

    .bar-value {
        position: absolute;
        top: -25px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 0.8rem;
        color: var(--color-primario);
        font-weight: 600;
    }

    .mes-label {
        margin-top: 0.5rem;
        font-size: 0.8rem;
        color: #6c757d;
    }

    .metricas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
    }

    .metrica-item {
        text-align: center;
        padding: 1rem;
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }

    .metrica-icon {
        font-size: 2rem;
        color: var(--color-secundario);
        margin-bottom: 0.5rem;
    }

    .metrica-info h5 {
        font-size: 1.5rem;
        color: var(--color-primario);
        margin-bottom: 0.25rem;
    }

    .metrica-info span {
        font-size: 0.85rem;
        color: #6c757d;
    }

    /* CARACTERÍSTICAS CHECKBOX */
    .caracteristicas-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .caracteristica-checkbox {
        position: relative;
    }

    .caracteristica-input {
        position: absolute;
        opacity: 0;
        width: 0;
        height: 0;
    }

    .caracteristica-label {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s;
    }

    .caracteristica-input:checked + .caracteristica-label {
        border-color: var(--color-secundario);
        background: rgba(52, 152, 219, 0.05);
    }

    .caracteristica-icon {
        width: 40px;
        height: 40px;
        background: rgba(52, 152, 219, 0.1);
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--color-secundario);
        font-size: 1.2rem;
    }

    .caracteristica-text strong {
        display: block;
        color: var(--color-primario);
        margin-bottom: 0.25rem;
    }

    .caracteristica-text small {
        display: block;
        color: #6c757d;
        font-size: 0.85rem;
    }

    /* SWITCH */
    .form-switch {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .form-switch .form-check-input {
        width: 50px;
        height: 26px;
        margin: 0;
    }

    /* PLACEHOLDER ANIMATION */
    @keyframes shimmer {
        0% { background-position: -1000px 0; }
        100% { background-position: 1000px 0; }
    }

    .loading-placeholder {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 1000px 100%;
        animation: shimmer 2s infinite linear;
    }
    </style>
</head>
<body>
<div class="content-body">
    <!-- Header de Mi Cuenta -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1>Mi Cuenta</h1>
            <p class="dashboard-subtitle">Gestiona tu información personal, seguridad y preferencias</p>
            <?php if ($es_super_admin): ?>
            <div class="super-admin-badge">
                <i class="fas fa-user-shield"></i>
                <span>Super Administrador - Acceso completo al sistema</span>
            </div>
            <?php endif; ?>
        </div>
        <div class="header-actions">
            <span class="user-badge <?php echo htmlspecialchars($usuario_actual['rol'] ?? ''); ?>">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $usuario_actual['rol'] ?? 'Usuario'))); ?>
            </span>
            <?php if ($es_super_admin): ?>
            <span class="stats-badge" title="Empresas registradas">
                <i class="fas fa-building"></i>
                <?php echo count($clientes_todos); ?> empresas
            </span>
            <span class="stats-badge" title="Planes activos">
                <i class="fas fa-cubes"></i>
                <?php 
                $planes_activos = array_filter($planes, function($plan) {
                    return ($plan['estado'] ?? 0) == 1;
                });
                echo count($planes_activos); 
                ?> planes
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mensajes de alerta -->
    <?php if (!empty($mensaje_exito)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $mensaje_exito; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($mensaje_error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $mensaje_error; ?>
    </div>
    <?php endif; ?>

    <div class="account-container">
        <!-- Panel lateral de navegación -->
        <div class="account-sidebar">
            <div class="user-profile-card">
                <div class="user-avatar-large">
                    <div class="avatar-initials-large">
                        <?php 
                        $iniciales = substr($usuario_actual['nombre'] ?? 'U', 0, 1) . 
                                    substr($usuario_actual['apellido'] ?? 'S', 0, 1);
                        echo strtoupper($iniciales);
                        ?>
                    </div>
                </div>
                <div class="user-info-summary">
                    <h3><?php echo htmlspecialchars(($usuario_actual['nombre'] ?? '') . ' ' . ($usuario_actual['apellido'] ?? '')); ?></h3>
                    <p class="user-email"><?php echo htmlspecialchars($usuario_actual['email'] ?? ''); ?></p>
                    <p class="user-role">
                        <span class="role-badge <?php echo htmlspecialchars($usuario_actual['rol'] ?? ''); ?>">
                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $usuario_actual['rol'] ?? 'Usuario'))); ?>
                        </span>
                    </p>
                </div>
                <div class="user-stats">
                    <div class="stat-item">
                        <i class="fas fa-building"></i>
                        <span><?php echo htmlspecialchars($usuario_actual['nombre_empresa'] ?? 'Empresa Demo'); ?></span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-cube"></i>
                        <span>Plan <?php echo htmlspecialchars($usuario_actual['nombre_plan'] ?? 'Free'); ?></span>
                    </div>
                    <div class="stat-item">
                        <i class="fas fa-calendar"></i>
                        <span>Miembro desde <?php echo date('M Y', strtotime($usuario_actual['fecha_creacion'] ?? 'now')); ?></span>
                    </div>
                </div>
            </div>

            <nav class="account-nav">
                <a href="#perfil" class="nav-item active" data-tab="perfil">
                    <i class="fas fa-user"></i>
                    Información Personal
                </a>
                <a href="#seguridad" class="nav-item" data-tab="seguridad">
                    <i class="fas fa-shield-alt"></i>
                    Seguridad
                </a>
                <a href="#preferencias" class="nav-item" data-tab="preferencias">
                    <i class="fas fa-cog"></i>
                    Preferencias
                </a>
                
                <!-- SECCIÓN PARA SUPER ADMIN -->
                <?php if ($es_super_admin): ?>
                <div class="nav-section-divider">
                    <span>ADMINISTRACIÓN DEL SISTEMA</span>
                </div>
                <a href="#admin_planes" class="nav-item" data-tab="admin_planes">
                    <i class="fas fa-cubes"></i>
                    Gestión de Planes
                    <span class="nav-badge"><?php echo count($planes); ?></span>
                </a>
                <a href="#admin_clientes" class="nav-item" data-tab="admin_clientes">
                    <i class="fas fa-users"></i>
                    Empresas & Asignaciones
                    <span class="nav-badge"><?php echo count($clientes_todos); ?></span>
                </a>
                <a href="#admin_crear_plan" class="nav-item" data-tab="admin_crear_plan">
                    <i class="fas fa-plus-circle"></i>
                    Crear Nuevo Plan
                </a>
                <a href="#admin_reportes" class="nav-item" data-tab="admin_reportes">
                    <i class="fas fa-chart-bar"></i>
                    Reportes del Sistema
                </a>
                <?php endif; ?>
                <!-- FIN SECCIÓN SUPER ADMIN -->
                
                <a href="#personalizacion" class="nav-item" data-tab="personalizacion">
                    <i class="fas fa-palette"></i>
                    Personalización
                </a>
                <a href="#actividad" class="nav-item" data-tab="actividad">
                    <i class="fas fa-history"></i>
                    Actividad Reciente
                </a>
            </nav>
        </div>

        <!-- Contenido principal -->
        <div class="account-content">
            <!-- Pestaña: Información Personal -->
            <div id="perfil" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-edit"></i> Información Personal</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="account-form">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="nombre">Nombre *</label>
                                    <input type="text" id="nombre" name="nombre" 
                                           value="<?php echo htmlspecialchars($usuario_actual['nombre'] ?? ''); ?>" 
                                           required class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="apellido">Apellido *</label>
                                    <input type="text" id="apellido" name="apellido" 
                                           value="<?php echo htmlspecialchars($usuario_actual['apellido'] ?? ''); ?>" 
                                           required class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($usuario_actual['email'] ?? ''); ?>" 
                                           disabled class="form-control">
                                    <small class="form-text">El email no puede ser modificado</small>
                                </div>
                                <div class="form-group">
                                    <label for="telefono">Teléfono</label>
                                    <input type="tel" id="telefono" name="telefono" 
                                           value="<?php echo htmlspecialchars($usuario_actual['telefono'] ?? ''); ?>" 
                                           class="form-control">
                                </div>
                                <div class="form-group">
                                    <label for="dni">DNI</label>
                                    <input type="text" id="dni" name="dni" 
                                           value="<?php echo htmlspecialchars($usuario_actual['dni'] ?? ''); ?>" 
                                           class="form-control">
                                </div>
                                <div class="form-group full-width">
                                    <label for="rol">Rol en el Sistema</label>
                                    <input type="text" id="rol" 
                                           value="<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $usuario_actual['rol'] ?? 'Usuario'))); ?>" 
                                           disabled class="form-control">
                                </div>
                            </div>
                            <div class="form-actions">
                                <button type="submit" name="actualizar_perfil" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Guardar Cambios
                                </button>
                                <button type="reset" class="btn btn-outline">
                                    <i class="fas fa-undo"></i>
                                    Restablecer
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
                        <h3><i class="fas fa-shield-alt"></i> Seguridad y Contraseña</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="account-form">
                            <div class="security-info">
                                <div class="security-status">
                                    <div class="status-item success">
                                        <i class="fas fa-check-circle"></i>
                                        <div class="status-text">
                                            <strong>Último acceso</strong>
                                            <span><?php echo $usuario_actual['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario_actual['ultimo_login'])) : 'Nunca'; ?></span>
                                        </div>
                                    </div>
                                    <div class="status-item <?php echo (($usuario_actual['estado'] ?? 0) == 1) ? 'success' : 'warning'; ?>">
                                        <i class="fas fa-<?php echo (($usuario_actual['estado'] ?? 0) == 1) ? 'check' : 'exclamation'; ?>-circle"></i>
                                        <div class="status-text">
                                            <strong>Estado de cuenta</strong>
                                            <span><?php echo (($usuario_actual['estado'] ?? 0) == 1) ? 'Activa' : 'Inactiva'; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Cambiar Contraseña</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="password_actual">Contraseña Actual *</label>
                                        <div class="password-field">
                                            <input type="password" id="password_actual" name="password_actual" 
                                                   required class="form-control" minlength="6">
                                            <button type="button" class="toggle-password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="nuevo_password">Nueva Contraseña *</label>
                                        <div class="password-field">
                                            <input type="password" id="nuevo_password" name="nuevo_password" 
                                                   required class="form-control" minlength="6">
                                            <button type="button" class="toggle-password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <small class="form-text">Mínimo 6 caracteres</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirmar_password">Confirmar Nueva Contraseña *</label>
                                        <div class="password-field">
                                            <input type="password" id="confirmar_password" name="confirmar_password" 
                                                   required class="form-control" minlength="6">
                                            <button type="button" class="toggle-password">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="cambiar_password" class="btn btn-primary">
                                    <i class="fas fa-key"></i>
                                    Cambiar Contraseña
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Preferencias -->
            <div id="preferencias" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cog"></i> Preferencias del Sistema</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="account-form">
                            <div class="form-section">
                                <h4>Configuración Regional</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="timezone">Zona Horaria</label>
                                        <select id="timezone" name="timezone" class="form-control">
                                            <option value="America/Lima" <?php echo ($configuracion['timezone'] ?? 'America/Lima') === 'America/Lima' ? 'selected' : ''; ?>>Lima, Perú (UTC-5)</option>
                                            <option value="America/Bogota" <?php echo ($configuracion['timezone'] ?? '') === 'America/Bogota' ? 'selected' : ''; ?>>Bogotá, Colombia (UTC-5)</option>
                                            <option value="America/Mexico_City" <?php echo ($configuracion['timezone'] ?? '') === 'America/Mexico_City' ? 'selected' : ''; ?>>Ciudad de México (UTC-6)</option>
                                            <option value="America/Argentina/Buenos_Aires" <?php echo ($configuracion['timezone'] ?? '') === 'America/Argentina/Buenos_Aires' ? 'selected' : ''; ?>>Buenos Aires, Argentina (UTC-3)</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="idioma">Idioma</label>
                                        <select id="idioma" name="idioma" class="form-control">
                                            <option value="es" <?php echo ($configuracion['idioma'] ?? 'es') === 'es' ? 'selected' : ''; ?>>Español</option>
                                            <option value="en" <?php echo ($configuracion['idioma'] ?? '') === 'en' ? 'selected' : ''; ?>>English</option>
                                            <option value="pt" <?php echo ($configuracion['idioma'] ?? '') === 'pt' ? 'selected' : ''; ?>>Português</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="formato_fecha">Formato de Fecha</label>
                                        <select id="formato_fecha" name="formato_fecha" class="form-control">
                                            <option value="d/m/Y" <?php echo ($configuracion['formato_fecha'] ?? 'd/m/Y') === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (25/12/2024)</option>
                                            <option value="m/d/Y" <?php echo ($configuracion['formato_fecha'] ?? '') === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (12/25/2024)</option>
                                            <option value="Y-m-d" <?php echo ($configuracion['formato_fecha'] ?? '') === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2024-12-25)</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Unidades de Medida</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="unidad_medida">Unidad de Alcohol</label>
                                        <select id="unidad_medida" name="unidad_medida" class="form-control" disabled>
                                            <option value="g/L" selected>Gramos por Litro (g/L)</option>
                                            <option value="mg/dL">Miligramos por Decilitro (mg/dL)</option>
                                            <option value="BAC">Blood Alcohol Content (%)</option>
                                        </select>
                                        <small class="form-text">Configuración a nivel de empresa</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="actualizar_preferencias" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Guardar Preferencias
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- PESTAÑAS PARA SUPER ADMIN - GESTIÓN DE PLANES -->
            <?php if ($es_super_admin): ?>
            
            <!-- Pestaña: Administración de Planes -->
            <div id="admin_planes" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-cubes"></i> Gestión de Planes del Sistema</h3>
                        <div class="card-actions">
                            <button class="btn btn-primary" onclick="mostrarCrearPlan()">
                                <i class="fas fa-plus"></i>
                                Nuevo Plan
                            </button>
                            <button class="btn btn-outline" onclick="actualizarVistaPlanes()">
                                <i class="fas fa-sync-alt"></i>
                                Actualizar
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Estadísticas rápidas -->
                        <div class="admin-stats-grid">
                            <div class="admin-stat">
                                <div class="admin-stat-icon">
                                    <i class="fas fa-cube"></i>
                                </div>
                                <div class="admin-stat-info">
                                    <h4><?php 
                                        $planes_activos = array_filter($planes, function($plan) {
                                            return ($plan['estado'] ?? 0) == 1;
                                        });
                                        echo count($planes_activos); 
                                    ?></h4>
                                    <span>Planes Activos</span>
                                </div>
                            </div>
                            <div class="admin-stat">
                                <div class="admin-stat-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="admin-stat-info">
                                    <h4><?php echo count($clientes_todos); ?></h4>
                                    <span>Empresas</span>
                                </div>
                            </div>
                            <div class="admin-stat">
                                <div class="admin-stat-icon">
                                    <i class="fas fa-money-bill-wave"></i>
                                </div>
                                <div class="admin-stat-info">
                                    <h4>$<?php 
                                        $ingreso_total = 0;
                                        foreach ($clientes_todos as $cliente) {
                                            foreach ($planes as $plan) {
                                                if ($plan['id'] == $cliente['plan_id']) {
                                                    $ingreso_total += ($plan['precio_mensual'] ?? 0);
                                                    break;
                                                }
                                            }
                                        }
                                        echo number_format($ingreso_total, 2);
                                    ?></h4>
                                    <span>Ingreso Mensual</span>
                                </div>
                            </div>
                        </div>

                        <!-- Lista de Planes -->
                        <div class="admin-planes-grid">
                            <?php foreach ($planes as $plan): ?>
                            <div class="admin-plan-card <?php echo ($plan['estado'] ?? 0) ? 'activo' : 'inactivo'; ?>">
                                <div class="admin-plan-header">
                                    <div class="admin-plan-title">
                                        <h4><?php echo htmlspecialchars($plan['nombre_plan'] ?? ''); ?></h4>
                                        <small><?php echo htmlspecialchars($plan['descripcion'] ?? 'Sin descripción'); ?></small>
                                    </div>
                                    <div class="admin-plan-price">
                                        $<?php echo number_format($plan['precio_mensual'] ?? 0, 2); ?>
                                        <small>/mes</small>
                                    </div>
                                </div>
                                
                                <div class="admin-plan-features">
                                    <div class="feature-row">
                                        <span><i class="fas fa-users"></i> <?php echo number_format($plan['limite_usuarios'] ?? 0); ?> usuarios</span>
                                        <span><i class="fas fa-car"></i> <?php echo number_format($plan['limite_vehiculos'] ?? 0); ?> vehículos</span>
                                    </div>
                                    <div class="feature-row">
                                        <span><i class="fas fa-vial"></i> <?php echo number_format($plan['limite_pruebas_mes'] ?? 0); ?> pruebas/mes</span>
                                        <span><i class="fas fa-microchip"></i> <?php echo number_format($plan['limite_alcoholimetros'] ?? 0); ?> dispositivos</span>
                                    </div>
                                    
                                    <?php if (($plan['reportes_avanzados'] ?? 0) || ($plan['soporte_prioritario'] ?? 0) || ($plan['acceso_api'] ?? 0)): ?>
                                    <div class="premium-features">
                                        <?php if ($plan['reportes_avanzados'] ?? 0): ?>
                                        <span class="premium-badge"><i class="fas fa-chart-line"></i> Reportes Avanzados</span>
                                        <?php endif; ?>
                                        <?php if ($plan['soporte_prioritario'] ?? 0): ?>
                                        <span class="premium-badge"><i class="fas fa-headset"></i> Soporte Prioritario</span>
                                        <?php endif; ?>
                                        <?php if ($plan['acceso_api'] ?? 0): ?>
                                        <span class="premium-badge"><i class="fas fa-code"></i> API Acceso</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="admin-plan-actions">
                                    <button class="btn btn-sm btn-outline" onclick="editarPlan(<?php echo $plan['id'] ?? 0; ?>)">
                                        <i class="fas fa-edit"></i> Editar
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="asignarPlan(<?php echo $plan['id'] ?? 0; ?>, '<?php echo htmlspecialchars($plan['nombre_plan'] ?? ''); ?>')">
                                        <i class="fas fa-share"></i> Asignar
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="eliminarPlan(<?php echo $plan['id'] ?? 0; ?>, '<?php echo htmlspecialchars($plan['nombre_plan'] ?? ''); ?>')">
                                        <i class="fas fa-trash"></i> Eliminar
                                    </button>
                                </div>
                                
                                <div class="admin-plan-footer">
                                    <span class="plan-status <?php echo ($plan['estado'] ?? 0) ? 'activo' : 'inactivo'; ?>">
                                        <?php echo ($plan['estado'] ?? 0) ? 'ACTIVO' : 'INACTIVO'; ?>
                                    </span>
                                    <span class="plan-clients-count">
                                        <?php 
                                        $clientes_con_plan = array_filter($clientes_todos, function($cliente) use ($plan) {
                                            return $cliente['plan_id'] == $plan['id'];
                                        });
                                        echo count($clientes_con_plan) . ' empresas';
                                        ?>
                                    </span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <?php if (empty($planes)): ?>
                            <div class="empty-state">
                                <div class="empty-icon">
                                    <i class="fas fa-cubes"></i>
                                </div>
                                <h3>No hay planes creados</h3>
                                <p>Crea tu primer plan para empezar a gestionar el sistema</p>
                                <button class="btn btn-primary" onclick="mostrarCrearPlan()">
                                    <i class="fas fa-plus"></i>
                                    Crear Primer Plan
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Empresas & Asignaciones - CORRECCIÓN 2: Agregar botón crear empresa -->
            <div id="admin_clientes" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-building"></i> Gestión de Empresas</h3>
                        <div class="card-actions">
                            <button class="btn btn-primary" onclick="mostrarCrearEmpresa()">
                                <i class="fas fa-plus"></i>
                                Nueva Empresa
                            </button>
                            <button class="btn btn-outline" onclick="exportarEmpresas()">
                                <i class="fas fa-download"></i>
                                Exportar
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Filtros -->
                        <div class="admin-filtros">
                            <input type="text" id="filtro-empresa" placeholder="Buscar empresa..." class="form-control">
                            <select id="filtro-plan-cliente" class="form-control">
                                <option value="">Todos los planes</option>
                                <?php foreach ($planes as $plan): ?>
                                <?php if ($plan['estado'] ?? 0): ?>
                                <option value="<?php echo $plan['id']; ?>">
                                    <?php echo htmlspecialchars($plan['nombre_plan'] ?? ''); ?>
                                </option>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <select id="filtro-estado" class="form-control">
                                <option value="">Todos los estados</option>
                                <option value="activo">Activo</option>
                                <option value="inactivo">Inactivo</option>
                                <option value="prueba">Prueba</option>
                                <option value="suspendido">Suspendido</option>
                            </select>
                            <button class="btn btn-primary" onclick="filtrarEmpresas()">
                                <i class="fas fa-filter"></i>
                                Filtrar
                            </button>
                            <button class="btn btn-outline" onclick="limpiarFiltros()">
                                <i class="fas fa-times"></i>
                                Limpiar
                            </button>
                        </div>

                        <!-- Tabla de Empresas -->
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>Empresa</th>
                                        <th>RUC</th>
                                        <th>Plan</th>
                                        <th>Estado</th>
                                        <th>Contacto</th>
                                        <th>Registro</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($clientes_todos as $cliente): ?>
                                    <tr>
                                        <td>
                                            <div class="cliente-info">
                                                <strong><?php echo htmlspecialchars($cliente['nombre_empresa'] ?? ''); ?></strong>
                                                <small>ID: <?php echo $cliente['id'] ?? 0; ?></small>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($cliente['ruc'] ?? ''); ?></td>
                                        <td>
                                            <div class="plan-asignado">
                                                <span class="plan-tag <?php echo strtolower(str_replace(' ', '-', $cliente['nombre_plan'] ?? 'free')); ?>">
                                                    <?php echo htmlspecialchars($cliente['nombre_plan'] ?? 'Sin plan'); ?>
                                                </span>
                                                <button class="btn-icon btn-sm" onclick="cambiarPlan(<?php echo $cliente['id'] ?? 0; ?>, '<?php echo htmlspecialchars($cliente['nombre_empresa'] ?? ''); ?>')" title="Cambiar Plan">
                                                    <i class="fas fa-exchange-alt"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-tag <?php echo htmlspecialchars($cliente['estado'] ?? ''); ?>">
                                                <?php echo htmlspecialchars(ucfirst($cliente['estado'] ?? '')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="contacto-info">
                                                <small><?php echo htmlspecialchars($cliente['email_contacto'] ?? 'Sin email'); ?></small>
                                                <?php if ($cliente['telefono_contacto'] ?? ''): ?>
                                                <br><small><?php echo htmlspecialchars($cliente['telefono_contacto'] ?? ''); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y', strtotime($cliente['fecha_registro'] ?? 'now')); ?>
                                        </td>
                                        <td>
                                            <div class="acciones-empresa">
                                                <button class="btn-icon btn-sm" onclick="editarCliente(<?php echo $cliente['id'] ?? 0; ?>)" title="Editar">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn-icon btn-sm" onclick="verDetallesCliente(<?php echo $cliente['id'] ?? 0; ?>)" title="Ver Detalles">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="btn-icon btn-sm" onclick="suspenderCliente(<?php echo $cliente['id'] ?? 0; ?>, '<?php echo htmlspecialchars($cliente['nombre_empresa'] ?? ''); ?>')" title="Suspender">
                                                    <i class="fas fa-pause"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if (empty($clientes_todos)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">
                                            <div class="empty-state">
                                                <i class="fas fa-building"></i>
                                                <h4>No hay empresas registradas</h4>
                                                <button class="btn btn-primary" onclick="mostrarCrearEmpresa()">
                                                    <i class="fas fa-plus"></i>
                                                    Crear Primera Empresa
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Resumen -->
                        <div class="admin-resumen">
                            <div class="resumen-item">
                                <span>Total Empresas:</span>
                                <strong><?php echo count($clientes_todos); ?></strong>
                            </div>
                            <div class="resumen-item">
                                <span>Empresas Activas:</span>
                                <strong>
                                    <?php 
                                    $activas = array_filter($clientes_todos, function($c) {
                                        return ($c['estado'] ?? '') == 'activo';
                                    });
                                    echo count($activas);
                                    ?>
                                </strong>
                            </div>
                            <div class="resumen-item">
                                <span>En Prueba:</span>
                                <strong>
                                    <?php 
                                    $prueba = array_filter($clientes_todos, function($c) {
                                        return ($c['estado'] ?? '') == 'prueba';
                                    });
                                    echo count($prueba);
                                    ?>
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Crear Nuevo Plan -->
            <div id="admin_crear_plan" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus-circle"></i> Crear Nuevo Plan</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="admin-form">
                            <div class="form-section">
                                <h4>Información Básica del Plan</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="nombre_plan">Nombre del Plan *</label>
                                        <input type="text" id="nombre_plan" name="nombre_plan" required class="form-control" placeholder="Ej: Plan Básico, Plan Premium, Plan Free">
                                    </div>
                                    <div class="form-group">
                                        <label for="precio_mensual">Precio Mensual ($) *</label>
                                        <input type="number" id="precio_mensual" name="precio_mensual" step="0.01" min="0" required class="form-control" placeholder="0.00" value="0">
                                        <small class="form-text">Para planes gratuitos como "Free", colocar 0</small>
                                    </div>
                                    <div class="form-group full-width">
                                        <label for="descripcion_plan">Descripción</label>
                                        <textarea id="descripcion_plan" name="descripcion_plan" class="form-control" rows="2" placeholder="Describe las características principales del plan"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Límites y Capacidades</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="limite_usuarios">Número de Usuarios</label>
                                        <input type="number" id="limite_usuarios" name="limite_usuarios" value="5" min="1" class="form-control">
                                        <small class="form-text">Cantidad máxima de usuarios que pueden acceder</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="limite_conductores">Conductores</label>
                                        <input type="number" id="limite_conductores" name="limite_conductores" value="50" min="0" class="form-control">
                                        <small class="form-text">Cantidad máxima de conductores registrados</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="limite_vehiculos">Vehículos</label>
                                        <input type="number" id="limite_vehiculos" name="limite_vehiculos" value="50" min="0" class="form-control">
                                        <small class="form-text">Cantidad máxima de vehículos registrados</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="limite_pruebas_mes">Pruebas por Mes</label>
                                        <input type="number" id="limite_pruebas_mes" name="limite_pruebas_mes" value="1000" min="0" class="form-control">
                                        <small class="form-text">Límite mensual de pruebas de alcohol</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="limite_alcoholimetros">Alcoholímetros</label>
                                        <input type="number" id="limite_alcoholimetros" name="limite_alcoholimetros" value="10" min="0" class="form-control">
                                        <small class="form-text">Cantidad máxima de dispositivos conectados</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="almacenamiento_fotos">Almacenamiento Fotos (MB)</label>
                                        <input type="number" id="almacenamiento_fotos" name="almacenamiento_fotos" value="100" min="0" class="form-control">
                                        <small class="form-text">Espacio para almacenar fotos de pruebas</small>
                                    </div>
                                    <div class="form-group">
                                        <label for="retencion_datos_meses">Retención Datos (meses)</label>
                                        <input type="number" id="retencion_datos_meses" name="retencion_datos_meses" value="12" min="1" class="form-control">
                                        <small class="form-text">Tiempo que se conservan los datos históricos</small>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Características Adicionales</h4>
                                <div class="caracteristicas-grid">
                                    <div class="caracteristica-checkbox">
                                        <input type="checkbox" id="reportes_avanzados" name="reportes_avanzados" class="caracteristica-input">
                                        <label for="reportes_avanzados" class="caracteristica-label">
                                            <div class="caracteristica-icon">
                                                <i class="fas fa-chart-line"></i>
                                            </div>
                                            <div class="caracteristica-text">
                                                <strong>Reportes Avanzados</strong>
                                                <small>Reportes detallados y análisis estadísticos</small>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="caracteristica-checkbox">
                                        <input type="checkbox" id="soporte_prioritario" name="soporte_prioritario" class="caracteristica-input">
                                        <label for="soporte_prioritario" class="caracteristica-label">
                                            <div class="caracteristica-icon">
                                                <i class="fas fa-headset"></i>
                                            </div>
                                            <div class="caracteristica-text">
                                                <strong>Soporte Prioritario</strong>
                                                <small>Atención prioritaria 24/7</small>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="caracteristica-checkbox">
                                        <input type="checkbox" id="acceso_api" name="acceso_api" class="caracteristica-input">
                                        <label for="acceso_api" class="caracteristica-label">
                                            <div class="caracteristica-icon">
                                                <i class="fas fa-code"></i>
                                            </div>
                                            <div class="caracteristica-text">
                                                <strong>Acceso API</strong>
                                                <small>Integración con otros sistemas</small>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="caracteristica-checkbox">
                                        <input type="checkbox" id="backup_automatico" name="backup_automatico" checked class="caracteristica-input">
                                        <label for="backup_automatico" class="caracteristica-label">
                                            <div class="caracteristica-icon">
                                                <i class="fas fa-database"></i>
                                            </div>
                                            <div class="caracteristica-text">
                                                <strong>Backup Automático</strong>
                                                <small>Copia de seguridad diaria automática</small>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="caracteristica-checkbox">
                                        <input type="checkbox" id="integraciones" name="integraciones" class="caracteristica-input">
                                        <label for="integraciones" class="caracteristica-label">
                                            <div class="caracteristica-icon">
                                                <i class="fas fa-plug"></i>
                                            </div>
                                            <div class="caracteristica-text">
                                                <strong>Integraciones</strong>
                                                <small>Conexión con sistemas externos</small>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="caracteristica-checkbox">
                                        <input type="checkbox" id="multi_sede" name="multi_sede" class="caracteristica-input">
                                        <label for="multi_sede" class="caracteristica-label">
                                            <div class="caracteristica-icon">
                                                <i class="fas fa-building"></i>
                                            </div>
                                            <div class="caracteristica-text">
                                                <strong>Multi Sede</strong>
                                                <small>Gestión de múltiples sucursales</small>
                                            </div>
                                        </label>
                                    </div>
                                    
                                    <div class="caracteristica-checkbox">
                                        <input type="checkbox" id="personalizacion" name="personalizacion" class="caracteristica-input">
                                        <label for="personalizacion" class="caracteristica-label">
                                            <div class="caracteristica-icon">
                                                <i class="fas fa-palette"></i>
                                            </div>
                                            <div class="caracteristica-text">
                                                <strong>Personalización</strong>
                                                <small>Personalización de marca y colores</small>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="crear_plan" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i>
                                    Crear Plan
                                </button>
                                <button type="reset" class="btn btn-outline">
                                    <i class="fas fa-undo"></i>
                                    Limpiar Formulario
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Reportes del Sistema -->
            <div id="admin_reportes" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Reportes del Sistema</h3>
                        <div class="card-actions">
                            <select id="periodo_reporte" class="form-control">
                                <option value="mes">Último Mes</option>
                                <option value="trimestre">Último Trimestre</option>
                                <option value="semestre">Último Semestre</option>
                                <option value="anio">Último Año</option>
                            </select>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="reportes-dashboard">
                            <!-- Gráfico de distribución de planes -->
                            <div class="reporte-card">
                                <div class="reporte-header">
                                    <h4><i class="fas fa-chart-pie"></i> Distribución de Planes</h4>
                                    <button class="btn-icon" onclick="exportarGrafico('distribucion')">
                                        <i class="fas fa-download"></i>
                                    </button>
                                </div>
                                <div class="reporte-content">
                                    <div class="distribucion-planes">
                                        <?php 
                                        $distribucion = [];
                                        foreach ($clientes_todos as $cliente) {
                                            $plan_nombre = $cliente['nombre_plan'] ?? 'Sin plan';
                                            $distribucion[$plan_nombre] = ($distribucion[$plan_nombre] ?? 0) + 1;
                                        }
                                        arsort($distribucion);
                                        ?>
                                        <?php foreach ($distribucion as $plan => $cantidad): ?>
                                        <div class="distribucion-item">
                                            <div class="distribucion-info">
                                                <span class="distribucion-plan"><?php echo htmlspecialchars($plan); ?></span>
                                                <span class="distribucion-cantidad"><?php echo $cantidad; ?> empresas</span>
                                            </div>
                                            <div class="distribucion-bar">
                                                <div class="bar-fill" style="width: <?php echo ($cantidad / max(1, count($clientes_todos))) * 100; ?>%"></div>
                                            </div>
                                            <span class="distribucion-porcentaje">
                                                <?php echo round(($cantidad / max(1, count($clientes_todos))) * 100, 1); ?>%
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Ingresos por plan -->
                            <div class="reporte-card">
                                <div class="reporte-header">
                                    <h4><i class="fas fa-money-bill-wave"></i> Ingresos Mensuales por Plan</h4>
                                </div>
                                <div class="reporte-content">
                                    <table class="ingresos-table">
                                        <thead>
                                            <tr>
                                                <th>Plan</th>
                                                <th>Precio</th>
                                                <th>Clientes</th>
                                                <th>Ingreso Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $ingresos_por_plan = [];
                                            $total_ingresos = 0;
                                            
                                            foreach ($planes as $plan) {
                                                if ($plan['estado'] ?? 0) {
                                                    $clientes_con_plan = array_filter($clientes_todos, function($cliente) use ($plan) {
                                                        return $cliente['plan_id'] == $plan['id'];
                                                    });
                                                    $cantidad = count($clientes_con_plan);
                                                    $ingreso = ($plan['precio_mensual'] ?? 0) * $cantidad;
                                                    $ingresos_por_plan[] = [
                                                        'plan' => $plan['nombre_plan'] ?? '',
                                                        'precio' => $plan['precio_mensual'] ?? 0,
                                                        'clientes' => $cantidad,
                                                        'ingreso' => $ingreso
                                                    ];
                                                    $total_ingresos += $ingreso;
                                                }
                                            }
                                            
                                            usort($ingresos_por_plan, function($a, $b) {
                                                return $b['ingreso'] <=> $a['ingreso'];
                                            });
                                            ?>
                                            
                                            <?php foreach ($ingresos_por_plan as $item): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($item['plan']); ?></td>
                                                <td>$<?php echo number_format($item['precio'], 2); ?></td>
                                                <td><?php echo $item['clientes']; ?></td>
                                                <td><strong>$<?php echo number_format($item['ingreso'], 2); ?></strong></td>
                                            </tr>
                                            <?php endforeach; ?>
                                            
                                            <tr class="total-row">
                                                <td colspan="3"><strong>Total Mensual:</strong></td>
                                                <td><strong class="text-primary">$<?php echo number_format($total_ingresos, 2); ?></strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Crecimiento de empresas -->
                            <div class="reporte-card">
                                <div class="reporte-header">
                                    <h4><i class="fas fa-chart-line"></i> Crecimiento de Empresas</h4>
                                </div>
                                <div class="reporte-content">
                                    <div class="crecimiento-stats">
                                        <?php 
                                        // Simular datos de crecimiento
                                        $meses = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
                                        $crecimiento = [];
                                        $total_acumulado = 0;
                                        
                                        for ($i = 0; $i < 12; $i++) {
                                            $nuevas = rand(1, 5);
                                            $total_acumulado += $nuevas;
                                            $crecimiento[] = [
                                                'mes' => $meses[$i],
                                                'nuevas' => $nuevas,
                                                'total' => $total_acumulado
                                            ];
                                        }
                                        ?>
                                        
                                        <div class="crecimiento-chart">
                                            <?php foreach ($crecimiento as $mes): ?>
                                            <div class="mes-item">
                                                <div class="mes-bar" style="height: <?php echo ($mes['nuevas'] / 5) * 100; ?>%">
                                                    <span class="bar-value"><?php echo $mes['nuevas']; ?></span>
                                                </div>
                                                <span class="mes-label"><?php echo $mes['mes']; ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                        
                                        <div class="crecimiento-resumen">
                                            <div class="resumen-item">
                                                <span>Nuevas este año:</span>
                                                <strong><?php echo array_sum(array_column($crecimiento, 'nuevas')); ?></strong>
                                            </div>
                                            <div class="resumen-item">
                                                <span>Total acumulado:</span>
                                                <strong><?php echo count($clientes_todos); ?></strong>
                                            </div>
                                            <div class="resumen-item">
                                                <span>Crecimiento mensual:</span>
                                                <strong><?php echo round(array_sum(array_column($crecimiento, 'nuevas')) / 12, 1); ?> empresas/mes</strong>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Reportes rápidos -->
                            <div class="reporte-card">
                                <div class="reporte-header">
                                    <h4><i class="fas fa-tachometer-alt"></i> Métricas Rápidas</h4>
                                </div>
                                <div class="reporte-content">
                                    <div class="metricas-grid">
                                        <div class="metrica-item">
                                            <div class="metrica-icon">
                                                <i class="fas fa-user-plus"></i>
                                            </div>
                                            <div class="metrica-info">
                                                <h5><?php 
                                                    $nuevas_ultimo_mes = rand(2, 8);
                                                    echo $nuevas_ultimo_mes;
                                                ?></h5>
                                                <span>Nuevas empresas (último mes)</span>
                                            </div>
                                        </div>
                                        <div class="metrica-item">
                                            <div class="metrica-icon">
                                                <i class="fas fa-exchange-alt"></i>
                                            </div>
                                            <div class="metrica-info">
                                                <h5><?php echo rand(1, 5); ?></h5>
                                                <span>Cambios de plan (último mes)</span>
                                            </div>
                                        </div>
                                        <div class="metrica-item">
                                            <div class="metrica-icon">
                                                <i class="fas fa-ban"></i>
                                            </div>
                                            <div class="metrica-info">
                                                <h5><?php 
                                                    $suspendidas = array_filter($clientes_todos, function($c) {
                                                        return ($c['estado'] ?? '') == 'suspendido';
                                                    });
                                                    echo count($suspendidas);
                                                ?></h5>
                                                <span>Empresas suspendidas</span>
                                            </div>
                                        </div>
                                        <div class="metrica-item">
                                            <div class="metrica-icon">
                                                <i class="fas fa-clock"></i>
                                            </div>
                                            <div class="metrica-info">
                                                <h5><?php echo rand(70, 95); ?>%</h5>
                                                <span>Tasa de retención</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php endif; ?>
            <!-- FIN PESTAÑAS PARA SUPER ADMIN -->

            <!-- Pestaña: Personalización -->
            <div id="personalizacion" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-palette"></i> Personalización de Tema</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="account-form">
                            <div class="form-section">
                                <h4>Colores Principales</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="color_primario">Color Primario</label>
                                        <div class="color-picker-group">
                                            <input type="color" id="color_primario" name="color_primario" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_primario'] ?? '#2c3e50'); ?>" 
                                                   class="form-control color-input">
                                            <input type="text" id="color_primario_text" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_primario'] ?? '#2c3e50'); ?>" 
                                                   class="form-control color-text">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="color_secundario">Color Secundario</label>
                                        <div class="color-picker-group">
                                            <input type="color" id="color_secundario" name="color_secundario" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_secundario'] ?? '#3498db'); ?>" 
                                                   class="form-control color-input">
                                            <input type="text" id="color_secundario_text" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_secundario'] ?? '#3498db'); ?>" 
                                                   class="form-control color-text">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="color_exito">Color de Éxito</label>
                                        <div class="color-picker-group">
                                            <input type="color" id="color_exito" name="color_exito" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_exito'] ?? '#27ae60'); ?>" 
                                                   class="form-control color-input">
                                            <input type="text" id="color_exito_text" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_exito'] ?? '#27ae60'); ?>" 
                                                   class="form-control color-text">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="color_error">Color de Error</label>
                                        <div class="color-picker-group">
                                            <input type="color" id="color_error" name="color_error" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_error'] ?? '#e74c3c'); ?>" 
                                                   class="form-control color-input">
                                            <input type="text" id="color_error_text" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_error'] ?? '#e74c3c'); ?>" 
                                                   class="form-control color-text">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Otros Colores y Estilos</h4>
                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="color_advertencia">Color de Advertencia</label>
                                        <div class="color-picker-group">
                                            <input type="color" id="color_advertencia" name="color_advertencia" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_advertencia'] ?? '#f39c12'); ?>" 
                                                   class="form-control color-input">
                                            <input type="text" id="color_advertencia_text" 
                                                   value="<?php echo htmlspecialchars($tema_actual['color_advertencia'] ?? '#f39c12'); ?>" 
                                                   class="form-control color-text">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="fuente_principal">Fuente Principal</label>
                                        <select id="fuente_principal" name="fuente_principal" class="form-control">
                                            <option value="Roboto" <?php echo ($tema_actual['fuente_principal'] ?? 'Roboto') === 'Roboto' ? 'selected' : ''; ?>>Roboto</option>
                                            <option value="Arial" <?php echo ($tema_actual['fuente_principal'] ?? '') === 'Arial' ? 'selected' : ''; ?>>Arial</option>
                                            <option value="Helvetica" <?php echo ($tema_actual['fuente_principal'] ?? '') === 'Helvetica' ? 'selected' : ''; ?>>Helvetica</option>
                                            <option value="Segoe UI" <?php echo ($tema_actual['fuente_principal'] ?? '') === 'Segoe UI' ? 'selected' : ''; ?>>Segoe UI</option>
                                            <option value="Open Sans" <?php echo ($tema_actual['fuente_principal'] ?? '') === 'Open Sans' ? 'selected' : ''; ?>>Open Sans</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="tamanio_fuente">Tamaño de Fuente Base (px)</label>
                                        <input type="range" id="tamanio_fuente" name="tamanio_fuente" 
                                               min="12" max="18" step="1" 
                                               value="<?php echo htmlspecialchars($tema_actual['tamanio_fuente'] ?? 14); ?>" 
                                               class="form-control range-input">
                                        <div class="range-value">
                                            <span id="tamanio_fuente_value"><?php echo htmlspecialchars($tema_actual['tamanio_fuente'] ?? 14); ?></span> px
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="border_radius">Radio de Borde (px)</label>
                                        <input type="range" id="border_radius" name="border_radius" 
                                               min="0" max="12" step="1" 
                                               value="<?php echo htmlspecialchars($tema_actual['border_radius'] ?? 4); ?>" 
                                               class="form-control range-input">
                                        <div class="range-value">
                                            <span id="border_radius_value"><?php echo htmlspecialchars($tema_actual['border_radius'] ?? 4); ?></span> px
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-section">
                                <h4>Vista Previa</h4>
                                <div class="theme-preview">
                                    <div class="preview-header" style="background: <?php echo htmlspecialchars($tema_actual['color_primario'] ?? '#2c3e50'); ?>;">
                                        <h4 style="color: white;">Encabezado de Vista Previa</h4>
                                    </div>
                                    <div class="preview-body">
                                        <button class="btn preview-btn" style="background: <?php echo htmlspecialchars($tema_actual['color_secundario'] ?? '#3498db'); ?>; color: white;">
                                            Botón Primario
                                        </button>
                                        <button class="btn preview-btn-outline" style="border-color: <?php echo htmlspecialchars($tema_actual['color_secundario'] ?? '#3498db'); ?>; color: <?php echo htmlspecialchars($tema_actual['color_secundario'] ?? '#3498db'); ?>;">
                                            Botón Secundario
                                        </button>
                                        <div class="preview-alert success" style="background: rgba(<?php echo hexToRgb($tema_actual['color_exito'] ?? '#27ae60'); ?>, 0.1); border-left-color: <?php echo htmlspecialchars($tema_actual['color_exito'] ?? '#27ae60'); ?>;">
                                            <i class="fas fa-check-circle" style="color: <?php echo htmlspecialchars($tema_actual['color_exito'] ?? '#27ae60'); ?>;"></i>
                                            Mensaje de éxito
                                        </div>
                                        <div class="preview-alert error" style="background: rgba(<?php echo hexToRgb($tema_actual['color_error'] ?? '#e74c3c'); ?>, 0.1); border-left-color: <?php echo htmlspecialchars($tema_actual['color_error'] ?? '#e74c3c'); ?>;">
                                            <i class="fas fa-exclamation-circle" style="color: <?php echo htmlspecialchars($tema_actual['color_error'] ?? '#e74c3c'); ?>;"></i>
                                            Mensaje de error
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" name="actualizar_tema" class="btn btn-primary">
                                    <i class="fas fa-save"></i>
                                    Guardar Tema Personalizado
                                </button>
                                <button type="button" class="btn btn-outline" onclick="resetearTema()">
                                    <i class="fas fa-undo"></i>
                                    Restablecer Tema
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Pestaña: Actividad Reciente -->
            <div id="actividad" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-history"></i> Actividad Reciente</h3>
                        <div class="card-actions">
                            <button class="btn btn-outline btn-sm" onclick="refreshActivity()">
                                <i class="fas fa-sync-alt"></i>
                                Actualizar
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($historial_actividad)): ?>
                        <div class="activity-timeline">
                            <?php foreach ($historial_actividad as $actividad): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php
                                    $icono = 'fa-info-circle';
                                    $color = 'primary';
                                    if (strpos($actividad['accion'] ?? '', 'LOGIN') !== false) {
                                        $icono = 'fa-sign-in-alt';
                                        $color = 'success';
                                    } elseif (strpos($actividad['accion'] ?? '', 'LOGOUT') !== false) {
                                        $icono = 'fa-sign-out-alt';
                                        $color = 'warning';
                                    } elseif (strpos($actividad['accion'] ?? '', 'UPDATE') !== false || strpos($actividad['accion'] ?? '', 'CREATE') !== false) {
                                        $icono = 'fa-edit';
                                        $color = 'info';
                                    }
                                    ?>
                                    <i class="fas <?php echo $icono; ?> text-<?php echo $color; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-title">
                                        <?php echo htmlspecialchars($actividad['accion'] ?? ''); ?>
                                    </div>
                                    <div class="activity-desc">
                                        <?php echo htmlspecialchars($actividad['detalles'] ?? 'Sin detalles'); ?>
                                    </div>
                                    <div class="activity-meta">
                                        <span class="activity-time">
                                            <i class="fas fa-clock"></i>
                                            <?php echo date('d/m/Y H:i', strtotime($actividad['fecha_accion'] ?? 'now')); ?>
                                        </span>
                                        <span class="activity-ip">
                                            <i class="fas fa-globe"></i>
                                            <?php echo htmlspecialchars($actividad['ip_address'] ?? 'IP no disponible'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <div class="empty-icon">
                                <i class="fas fa-history"></i>
                            </div>
                            <h3>No hay actividad registrada</h3>
                            <p>Tu actividad en el sistema aparecerá aquí</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MODALES PARA SUPER ADMIN -->
<?php if ($es_super_admin): ?>

<!-- Modal para Crear Nueva Empresa - CORRECCIÓN 2: Nueva funcionalidad -->
<div id="modalCrearEmpresa" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-building"></i> Crear Nueva Empresa</h4>
            <button class="modal-close" onclick="cerrarModal('modalCrearEmpresa')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="formCrearEmpresa">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre_empresa_nuevo">Nombre de la Empresa *</label>
                        <input type="text" id="nombre_empresa_nuevo" name="nombre_empresa_nuevo" required class="form-control" placeholder="Ej: Mi Empresa SAC">
                    </div>
                    <div class="form-group">
                        <label for="ruc_nuevo">RUC *</label>
                        <input type="text" id="ruc_nuevo" name="ruc_nuevo" required class="form-control" placeholder="12345678901">
                    </div>
                    <div class="form-group">
                        <label for="email_contacto_nuevo">Email de Contacto *</label>
                        <input type="email" id="email_contacto_nuevo" name="email_contacto_nuevo" required class="form-control" placeholder="contacto@empresa.com">
                    </div>
                    <div class="form-group">
                        <label for="telefono_contacto_nuevo">Teléfono de Contacto</label>
                        <input type="tel" id="telefono_contacto_nuevo" name="telefono_contacto_nuevo" class="form-control" placeholder="+51 123 456 789">
                    </div>
                    <div class="form-group">
                        <label for="plan_id_nuevo">Asignar Plan</label>
                        <select id="plan_id_nuevo" name="plan_id_nuevo" class="form-control">
                            <option value="0">-- Sin plan (Gratuito) --</option>
                            <?php foreach ($planes as $plan): ?>
                            <?php if ($plan['estado'] ?? 0): ?>
                            <option value="<?php echo $plan['id']; ?>">
                                <?php echo htmlspecialchars($plan['nombre_plan'] ?? ''); ?> ($<?php echo number_format($plan['precio_mensual'] ?? 0, 2); ?>/mes)
                            </option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text">Puede asignar un plan ahora o más tarde</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalCrearEmpresa')">Cancelar</button>
                    <button type="submit" name="crear_cliente" class="btn btn-primary">
                        <i class="fas fa-save"></i> Crear Empresa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Editar Plan -->
<div id="modalEditarPlan" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h4><i class="fas fa-edit"></i> Editar Plan</h4>
            <button class="modal-close" onclick="cerrarModal('modalEditarPlan')">&times;</button>
        </div>
        <div class="modal-body">
            <div id="editarPlanLoading" class="text-center py-5" style="display: none;">
                <i class="fas fa-spinner fa-spin fa-3x text-primary"></i>
                <p class="mt-3">Cargando datos del plan...</p>
            </div>
            
            <form method="POST" class="admin-form" id="formEditarPlan">
                <input type="hidden" id="plan_id_editar" name="plan_id_editar">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre_plan_editar">Nombre del Plan *</label>
                        <input type="text" id="nombre_plan_editar" name="nombre_plan_editar" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="precio_mensual_editar">Precio Mensual ($) *</label>
                        <input type="number" id="precio_mensual_editar" name="precio_mensual_editar" step="0.01" min="0" required class="form-control">
                        <small class="form-text">Para planes gratuitos como "Free", colocar 0</small>
                    </div>
                    <div class="form-group full-width">
                        <label for="descripcion_plan_editar">Descripción</label>
                        <textarea id="descripcion_plan_editar" name="descripcion_plan_editar" class="form-control" rows="2"></textarea>
                    </div>
                </div>

                <div class="form-section">
                    <h5>Límites del Plan</h5>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="limite_pruebas_mes_editar">Pruebas por Mes</label>
                            <input type="number" id="limite_pruebas_mes_editar" name="limite_pruebas_mes_editar" min="0" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="limite_usuarios_editar">Usuarios</label>
                            <input type="number" id="limite_usuarios_editar" name="limite_usuarios_editar" min="1" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="limite_conductores_editar">Conductores</label>
                            <input type="number" id="limite_conductores_editar" name="limite_conductores_editar" min="0" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="limite_vehiculos_editar">Vehículos</label>
                            <input type="number" id="limite_vehiculos_editar" name="limite_vehiculos_editar" min="0" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="limite_alcoholimetros_editar">Alcoholímetros</label>
                            <input type="number" id="limite_alcoholimetros_editar" name="limite_alcoholimetros_editar" min="0" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="almacenamiento_fotos_editar">Almacenamiento Fotos (MB)</label>
                            <input type="number" id="almacenamiento_fotos_editar" name="almacenamiento_fotos_editar" min="0" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="retencion_datos_meses_editar">Retención Datos (meses)</label>
                            <input type="number" id="retencion_datos_meses_editar" name="retencion_datos_meses_editar" min="1" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5>Características Adicionales</h5>
                    <div class="caracteristicas-grid modal-grid">
                        <div class="caracteristica-checkbox">
                            <input type="checkbox" id="reportes_avanzados_editar" name="reportes_avanzados_editar" class="caracteristica-input">
                            <label for="reportes_avanzados_editar" class="caracteristica-label">
                                <div class="caracteristica-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="caracteristica-text">
                                    <strong>Reportes Avanzados</strong>
                                </div>
                            </label>
                        </div>
                        <div class="caracteristica-checkbox">
                            <input type="checkbox" id="soporte_prioritario_editar" name="soporte_prioritario_editar" class="caracteristica-input">
                            <label for="soporte_prioritario_editar" class="caracteristica-label">
                                <div class="caracteristica-icon">
                                    <i class="fas fa-headset"></i>
                                </div>
                                <div class="caracteristica-text">
                                    <strong>Soporte Prioritario</strong>
                                </div>
                            </label>
                        </div>
                        <div class="caracteristica-checkbox">
                            <input type="checkbox" id="acceso_api_editar" name="acceso_api_editar" class="caracteristica-input">
                            <label for="acceso_api_editar" class="caracteristica-label">
                                <div class="caracteristica-icon">
                                    <i class="fas fa-code"></i>
                                </div>
                                <div class="caracteristica-text">
                                    <strong>Acceso API</strong>
                                </div>
                            </label>
                        </div>
                        <div class="caracteristica-checkbox">
                            <input type="checkbox" id="backup_automatico_editar" name="backup_automatico_editar" class="caracteristica-input">
                            <label for="backup_automatico_editar" class="caracteristica-label">
                                <div class="caracteristica-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                                <div class="caracteristica-text">
                                    <strong>Backup Automático</strong>
                                </div>
                            </label>
                        </div>
                        <div class="caracteristica-checkbox">
                            <input type="checkbox" id="integraciones_editar" name="integraciones_editar" class="caracteristica-input">
                            <label for="integraciones_editar" class="caracteristica-label">
                                <div class="caracteristica-icon">
                                    <i class="fas fa-plug"></i>
                                </div>
                                <div class="caracteristica-text">
                                    <strong>Integraciones</strong>
                                </div>
                            </label>
                        </div>
                        <div class="caracteristica-checkbox">
                            <input type="checkbox" id="multi_sede_editar" name="multi_sede_editar" class="caracteristica-input">
                            <label for="multi_sede_editar" class="caracteristica-label">
                                <div class="caracteristica-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="caracteristica-text">
                                    <strong>Multi Sede</strong>
                                </div>
                            </label>
                        </div>
                        <div class="caracteristica-checkbox">
                            <input type="checkbox" id="personalizacion_editar" name="personalizacion_editar" class="caracteristica-input">
                            <label for="personalizacion_editar" class="caracteristica-label">
                                <div class="caracteristica-icon">
                                    <i class="fas fa-palette"></i>
                                </div>
                                <div class="caracteristica-text">
                                    <strong>Personalización</strong>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="form-section">
                    <h5>Estado del Plan</h5>
                    <div class="estado-plan">
                        <div class="form-check form-switch">
                            <input type="checkbox" id="estado_plan_editar" name="estado_plan_editar" class="form-check-input">
                            <label for="estado_plan_editar" class="form-check-label">Plan Activo</label>
                        </div>
                        <small class="form-text">Los planes inactivos no se pueden asignar a nuevas empresas</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarPlan')">Cancelar</button>
                    <button type="submit" name="editar_plan" class="btn btn-primary">
                        <i class="fas fa-save"></i> Actualizar Plan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Asignar Plan a Cliente -->
<div id="modalAsignarPlan" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-exchange-alt"></i> Asignar Plan a Empresa</h4>
            <button class="modal-close" onclick="cerrarModal('modalAsignarPlan')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="formAsignarPlan">
                <input type="hidden" id="cliente_id" name="cliente_id">
                <input type="hidden" id="plan_id_asignar" name="plan_id">
                <div class="form-group">
                    <label for="cliente_nombre">Empresa</label>
                    <input type="text" id="cliente_nombre" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label for="plan_actual">Plan Actual</label>
                    <input type="text" id="plan_actual" class="form-control" disabled>
                </div>
                <div class="form-group">
                    <label for="nuevo_plan">Seleccionar Nuevo Plan *</label>
                    <select id="nuevo_plan" required class="form-control" onchange="document.getElementById('plan_id_asignar').value = this.value">
                        <option value="">-- Seleccionar Plan --</option>
                        <?php foreach ($planes as $plan): ?>
                        <?php if ($plan['estado'] ?? 0): ?>
                        <option value="<?php echo $plan['id']; ?>">
                            <?php echo htmlspecialchars($plan['nombre_plan'] ?? ''); ?> ($<?php echo number_format($plan['precio_mensual'] ?? 0, 2); ?>/mes)
                        </option>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="motivo_asignacion">Motivo del Cambio</label>
                    <textarea id="motivo_asignacion" name="motivo_asignacion" class="form-control" rows="3" placeholder="Ej: Actualización de plan solicitada por el cliente, cambio a plan superior, etc."></textarea>
                </div>
                <div class="info-alert">
                    <i class="fas fa-info-circle"></i>
                    <span>Este cambio se registrará en el historial y será notificado al administrador de la empresa.</span>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalAsignarPlan')">Cancelar</button>
                    <button type="submit" name="asignar_plan" class="btn btn-primary">
                        <i class="fas fa-check"></i> Confirmar Asignación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Editar Cliente - CORRECCIÓN 2: Agregar selector de plan -->
<div id="modalEditarCliente" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h4><i class="fas fa-building"></i> Editar Empresa</h4>
            <button class="modal-close" onclick="cerrarModal('modalEditarCliente')">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="formEditarCliente">
                <input type="hidden" id="cliente_id_editar" name="cliente_id_editar">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre_empresa_editar">Nombre de la Empresa *</label>
                        <input type="text" id="nombre_empresa_editar" name="nombre_empresa_editar" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="ruc_editar">RUC *</label>
                        <input type="text" id="ruc_editar" name="ruc_editar" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="email_contacto_editar">Email de Contacto *</label>
                        <input type="email" id="email_contacto_editar" name="email_contacto_editar" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="telefono_contacto_editar">Teléfono de Contacto</label>
                        <input type="tel" id="telefono_contacto_editar" name="telefono_contacto_editar" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="plan_id_editar">Plan Asignado</label>
                        <select id="plan_id_editar" name="plan_id_editar" class="form-control">
                            <option value="0">-- Sin plan (Gratuito) --</option>
                            <?php foreach ($planes as $plan): ?>
                            <?php if ($plan['estado'] ?? 0): ?>
                            <option value="<?php echo $plan['id']; ?>">
                                <?php echo htmlspecialchars($plan['nombre_plan'] ?? ''); ?> ($<?php echo number_format($plan['precio_mensual'] ?? 0, 2); ?>/mes)
                            </option>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="estado_cliente_editar">Estado</label>
                        <select id="estado_cliente_editar" name="estado_cliente_editar" class="form-control">
                            <option value="activo">Activo</option>
                            <option value="inactivo">Inactivo</option>
                            <option value="prueba">Prueba</option>
                            <option value="suspendido">Suspendido</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEditarCliente')">Cancelar</button>
                    <button type="submit" name="editar_cliente" class="btn btn-primary">
                        <i class="fas fa-save"></i> Guardar Cambios
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para Eliminar Plan -->
<div id="modalEliminarPlan" class="modal">
    <div class="modal-content modal-sm">
        <div class="modal-header">
            <h4><i class="fas fa-exclamation-triangle"></i> Confirmar Eliminación</h4>
            <button class="modal-close" onclick="cerrarModal('modalEliminarPlan')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="confirmacion-contenido">
                <div class="confirmacion-icon">
                    <i class="fas fa-trash"></i>
                </div>
                <h5 id="titulo-eliminar-plan">¿Eliminar plan?</h5>
                <p id="descripcion-eliminar-plan">Esta acción desactivará el plan. Los clientes con este plan deberán ser cambiados a otro plan primero.</p>
                
                <form method="POST" id="formEliminarPlan">
                    <input type="hidden" id="plan_id_eliminar" name="plan_id_eliminar">
                    <div class="form-group">
                        <label for="motivo_eliminacion">Motivo de la eliminación</label>
                        <textarea id="motivo_eliminacion" name="motivo_eliminacion" class="form-control" rows="2" required placeholder="Explique por qué está eliminando este plan..."></textarea>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline" onclick="cerrarModal('modalEliminarPlan')">Cancelar</button>
                        <button type="submit" name="eliminar_plan" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Confirmar Eliminación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
<!-- FIN MODALES PARA SUPER ADMIN -->

<script>
// ====================================================================
// JAVASCRIPT PARA FUNCIONALIDADES
// ====================================================================

// Navegación entre pestañas
document.addEventListener('DOMContentLoaded', function() {
    const navItems = document.querySelectorAll('.nav-item');
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
            const targetElement = document.getElementById(targetTab);
            if (targetElement) {
                targetElement.classList.add('active');
                // Scroll suave al inicio del contenido
                targetElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
    
    // Inicializar funcionalidades
    inicializarFunciones();
    
    // Verificar si hay un hash en la URL y activar esa pestaña
    const hash = window.location.hash.substring(1);
    if (hash) {
        const targetNav = document.querySelector(`.nav-item[data-tab="${hash}"]`);
        if (targetNav) {
            targetNav.click();
        }
    }
});

function inicializarFunciones() {
    // Toggle de visibilidad de contraseña
    const toggleButtons = document.querySelectorAll('.toggle-password');
    toggleButtons.forEach(button => {
        button.addEventListener('click', function() {
            const input = this.previousElementSibling;
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
            
            const icon = this.querySelector('i');
            if (type === 'password') {
                icon.className = 'fas fa-eye';
            } else {
                icon.className = 'fas fa-eye-slash';
            }
        });
    });
    
    // Sincronizar campos de color
    const colorInputs = document.querySelectorAll('.color-input');
    colorInputs.forEach(input => {
        const textId = input.id + '_text';
        const textInput = document.getElementById(textId);
        
        if (textInput) {
            input.addEventListener('input', function() {
                textInput.value = this.value;
                actualizarPreview();
            });
            
            textInput.addEventListener('input', function() {
                if (this.value.match(/^#[0-9A-F]{6}$/i)) {
                    input.value = this.value;
                    actualizarPreview();
                }
            });
        }
    });
    
    // Sincronizar sliders de rango
    const rangeInputs = document.querySelectorAll('.range-input');
    rangeInputs.forEach(input => {
        const valueId = input.id + '_value';
        const valueSpan = document.getElementById(valueId);
        
        if (valueSpan) {
            input.addEventListener('input', function() {
                valueSpan.textContent = this.value;
                actualizarPreview();
            });
        }
    });
    
    // Cambios en selects de tema
    const themeSelects = document.querySelectorAll('select[name="fuente_principal"]');
    themeSelects.forEach(select => {
        select.addEventListener('change', actualizarPreview);
    });
}

<?php if ($es_super_admin): ?>
// FUNCIONES PARA SUPER ADMIN

function mostrarCrearPlan() {
    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));
    
    const crearPlanNav = document.querySelector('[data-tab="admin_crear_plan"]');
    const crearPlanTab = document.getElementById('admin_crear_plan');
    
    if (crearPlanNav && crearPlanTab) {
        crearPlanNav.classList.add('active');
        crearPlanTab.classList.add('active');
        crearPlanTab.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
}

function mostrarCrearEmpresa() {
    // Limpiar formulario primero
    document.getElementById('formCrearEmpresa').reset();
    document.getElementById('modalCrearEmpresa').style.display = 'flex';
}

function actualizarVistaPlanes() {
    location.reload();
}

// FUNCIÓN CORREGIDA: CARGAR DATOS DEL PLAN VÍA AJAX
function editarPlan(planId) {
    const modal = document.getElementById('modalEditarPlan');
    const loading = document.getElementById('editarPlanLoading');
    const form = document.getElementById('formEditarPlan');
    
    // Mostrar modal
    modal.style.display = 'flex';
    
    // Mostrar indicador de carga y ocultar formulario
    loading.style.display = 'block';
    form.style.display = 'none';
    
    // Hacer petición AJAX para obtener los datos del plan
    fetch(`mi-cuenta.php?ajax=obtener_plan&id=${planId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                alert('Error: ' + (data.message || 'No se pudieron cargar los datos del plan'));
                cerrarModal('modalEditarPlan');
                return;
            }
            
            const plan = data.plan;
            
            // Llenar el formulario con los datos del plan
            document.getElementById('plan_id_editar').value = plan.id || '';
            document.getElementById('nombre_plan_editar').value = plan.nombre_plan || '';
            document.getElementById('precio_mensual_editar').value = plan.precio_mensual || 0;
            document.getElementById('descripcion_plan_editar').value = plan.descripcion || '';
            document.getElementById('limite_usuarios_editar').value = plan.limite_usuarios || 0;
            document.getElementById('limite_conductores_editar').value = plan.limite_conductores || 0;
            document.getElementById('limite_vehiculos_editar').value = plan.limite_vehiculos || 0;
            document.getElementById('limite_pruebas_mes_editar').value = plan.limite_pruebas_mes || 0;
            document.getElementById('limite_alcoholimetros_editar').value = plan.limite_alcoholimetros || 0;
            document.getElementById('almacenamiento_fotos_editar').value = plan.almacenamiento_fotos || 0;
            document.getElementById('retencion_datos_meses_editar').value = plan.retencion_datos_meses || 0;
            
            // Checkboxes de características
            const checkboxes = {
                'reportes_avanzados_editar': plan.reportes_avanzados,
                'soporte_prioritario_editar': plan.soporte_prioritario,
                'acceso_api_editar': plan.acceso_api,
                'backup_automatico_editar': plan.backup_automatico,
                'integraciones_editar': plan.integraciones,
                'multi_sede_editar': plan.multi_sede,
                'personalizacion_editar': plan.personalizacion
            };
            
            Object.keys(checkboxes).forEach(key => {
                const element = document.getElementById(key);
                if (element) {
                    element.checked = (checkboxes[key] == 1);
                }
            });
            
            // Estado del plan
            const estadoPlan = document.getElementById('estado_plan_editar');
            if (estadoPlan) {
                estadoPlan.checked = (plan.estado == 1);
            }
            
            // Ocultar indicador de carga y mostrar formulario
            loading.style.display = 'none';
            form.style.display = 'block';
        })
        .catch(error => {
            console.error('Error al cargar el plan:', error);
            alert('Error al cargar los datos del plan: ' + error.message + '\n\nDetalles: ' + JSON.stringify(error));
            cerrarModal('modalEditarPlan');
        });
}

function asignarPlan(planId, planNombre) {
    document.getElementById('plan_id_asignar').value = planId;
    document.getElementById('modalAsignarPlan').style.display = 'flex';
}

function cambiarPlan(clienteId, clienteNombre) {
    // Obtener el plan actual del cliente
    fetch(`mi-cuenta.php?ajax=obtener_cliente&id=${clienteId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const cliente = data.cliente;
                document.getElementById('cliente_id').value = clienteId;
                document.getElementById('cliente_nombre').value = clienteNombre;
                document.getElementById('plan_actual').value = cliente.nombre_plan || 'Sin plan';
                
                // Establecer el plan actual como valor por defecto en el select
                const nuevoPlanSelect = document.getElementById('nuevo_plan');
                nuevoPlanSelect.value = cliente.plan_id || '';
                
                // Mostrar el modal
                document.getElementById('modalAsignarPlan').style.display = 'flex';
            } else {
                alert('Error: ' + (data.message || 'No se pudieron cargar los datos del cliente'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar datos del cliente: ' + error.message);
        });
}

function eliminarPlan(planId, planNombre) {
    document.getElementById('plan_id_eliminar').value = planId;
    document.getElementById('modalEliminarPlan').style.display = 'flex';
}

function editarCliente(clienteId) {
    // Hacer petición AJAX para obtener los datos del cliente
    fetch(`mi-cuenta.php?ajax=obtener_cliente&id=${clienteId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Error en la respuesta del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                const cliente = data.cliente;
                document.getElementById('cliente_id_editar').value = cliente.id || '';
                document.getElementById('nombre_empresa_editar').value = cliente.nombre_empresa || '';
                document.getElementById('ruc_editar').value = cliente.ruc || '';
                document.getElementById('email_contacto_editar').value = cliente.email_contacto || '';
                document.getElementById('telefono_contacto_editar').value = cliente.telefono_contacto || '';
                document.getElementById('plan_id_editar').value = cliente.plan_id || 0;
                document.getElementById('estado_cliente_editar').value = cliente.estado || 'activo';
                document.getElementById('modalEditarCliente').style.display = 'flex';
            } else {
                alert('Error: ' + (data.message || 'No se pudieron cargar los datos del cliente'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error al cargar datos del cliente: ' + error.message);
        });
}

function verDetallesCliente(clienteId) {
    alert('Funcionalidad de ver detalles del cliente - ID: ' + clienteId);
}

function suspenderCliente(clienteId, clienteNombre) {
    if (confirm(`¿Estás seguro de suspender a la empresa "${clienteNombre}"?`)) {
        fetch(`mi-cuenta.php?ajax=suspender_cliente&id=${clienteId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cliente suspendido correctamente');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al suspender el cliente');
            });
    }
}

function exportarEmpresas() {
    alert('Funcionalidad de exportar empresas - Esta funcionalidad se implementará completamente');
}

function filtrarEmpresas() {
    const filtroEmpresa = document.getElementById('filtro-empresa').value.toLowerCase();
    const filtroPlan = document.getElementById('filtro-plan-cliente').value;
    const filtroEstado = document.getElementById('filtro-estado').value;
    
    console.log('Filtrar empresas:', { filtroEmpresa, filtroPlan, filtroEstado });
    alert('Funcionalidad de filtrado - Esta funcionalidad se implementará completamente');
}

function limpiarFiltros() {
    document.getElementById('filtro-empresa').value = '';
    document.getElementById('filtro-plan-cliente').value = '';
    document.getElementById('filtro-estado').value = '';
}

function exportarGrafico(tipo) {
    alert(`Exportar gráfico de ${tipo} - Esta funcionalidad se implementará completamente`);
}

function cerrarModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Cerrar modales al hacer clic fuera
window.onclick = function(event) {
    const modales = document.querySelectorAll('.modal');
    modales.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}
<?php endif; ?>

// FUNCIONES COMUNES
function refreshActivity() {
    location.reload();
}

function resetearTema() {
    if (confirm('¿Estás seguro de restablecer el tema a los valores por defecto?')) {
        document.getElementById('color_primario').value = '#2c3e50';
        document.getElementById('color_primario_text').value = '#2c3e50';
        document.getElementById('color_secundario').value = '#3498db';
        document.getElementById('color_secundario_text').value = '#3498db';
        document.getElementById('color_exito').value = '#27ae60';
        document.getElementById('color_exito_text').value = '#27ae60';
        document.getElementById('color_error').value = '#e74c3c';
        document.getElementById('color_error_text').value = '#e74c3c';
        document.getElementById('color_advertencia').value = '#f39c12';
        document.getElementById('color_advertencia_text').value = '#f39c12';
        document.getElementById('fuente_principal').value = 'Roboto';
        document.getElementById('tamanio_fuente').value = '14';
        document.getElementById('tamanio_fuente_value').textContent = '14';
        document.getElementById('border_radius').value = '4';
        document.getElementById('border_radius_value').textContent = '4';
        
        actualizarPreview();
    }
}

function actualizarPreview() {
    // Esta función actualizaría la vista previa en tiempo real
    console.log('Actualizando vista previa del tema');
}
</script>
</body>
</html>