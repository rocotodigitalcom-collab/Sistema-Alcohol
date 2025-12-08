<?php
// inventario-fisico.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Toma de Inventario Físico';
$breadcrumbs = [
    'dashboard-logistico.php' => 'Dashboard Logístico',
    'inventario-fisico.php' => 'Toma de Inventario Físico'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

// Obtener información del usuario actual
$usuario_actual = $db->fetchOne("
    SELECT u.*, c.nombre_empresa 
    FROM usuarios u
    LEFT JOIN clientes c ON u.cliente_id = c.id
    WHERE u.id = ?
", [$user_id]);

// Determinar la vista según el rol y parámetros
$view = $_GET['view'] ?? '';
$toma_id = $_GET['toma_id'] ?? 0;
$action_form = $_GET['action_form'] ?? ''; // Para mostrar formularios específicos
$detalle_id_form = $_GET['detalle_id'] ?? 0; // Para formularios específicos

// Si es empleado (conductor u operador) y no se especifica vista, mostrar asignaciones
if (in_array($rol, ['operador', 'conductor']) && $view == '') {
    $view = 'asignaciones';
}

// Si es supervisor o admin, por defecto mostrar el listado de tomas
if (in_array($rol, ['supervisor', 'admin', 'super_admin']) && $view == '') {
    $view = 'listado_tomas';
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'crear_toma':
            // Validar y crear nueva toma
            $almacen_id = intval($_POST['almacen_id']);
            $responsable_id = intval($_POST['responsable_id']);
            $fecha_planificada = $_POST['fecha_planificada'];
            $hora_inicio = $_POST['hora_inicio_planificada'];
            $hora_fin = $_POST['hora_fin_planificada'];
            $productos_seleccionados = $_POST['productos'] ?? [];
            
            // Generar código único
            $codigo_toma = 'INV-' . date('Ymd') . '-' . rand(100, 999);
            
            // Insertar toma
            $db->execute("
                INSERT INTO tomas_inventario 
                (cliente_id, almacen_id, codigo_toma, nombre, estado, responsable_id, supervisor_id, 
                 fecha_planificada, hora_inicio_planificada, hora_fin_planificada, total_productos)
                VALUES (?, ?, ?, ?, 'planificada', ?, ?, ?, ?, ?, ?)
            ", [
                $cliente_id, $almacen_id, $codigo_toma, $_POST['nombre_toma'], 
                $responsable_id, $user_id, $fecha_planificada, $hora_inicio, $hora_fin,
                count($productos_seleccionados)
            ]);
            
            $toma_id = $db->lastInsertId();
            
            // Insertar detalles
            foreach ($productos_seleccionados as $producto_id) {
                $producto = $db->fetchOne("SELECT stock_actual FROM productos_inventario WHERE id = ?", [$producto_id]);
                $db->execute("
                    INSERT INTO detalle_toma_inventario (toma_id, producto_id, cantidad_sistema)
                    VALUES (?, ?, ?)
                ", [$toma_id, $producto_id, $producto['stock_actual']]);
            }
            
            // Notificar al empleado
            $db->execute("
                INSERT INTO notificaciones_inventario (cliente_id, usuario_id, tipo, mensaje)
                VALUES (?, ?, 'asignacion', ?)
            ", [$cliente_id, $responsable_id, "Se te ha asignado una nueva toma de inventario: $codigo_toma"]);
            
            $_SESSION['success_message'] = "Toma de inventario creada exitosamente";
            header("Location: inventario-fisico.php?view=detalle_toma&toma_id=$toma_id");
            exit;
            break;
            
        case 'iniciar_toma':
            $toma_id = intval($_POST['toma_id']);
            $db->execute("
                UPDATE tomas_inventario 
                SET estado = 'en_proceso', fecha_inicio_real = NOW()
                WHERE id = ? AND cliente_id = ?
            ", [$toma_id, $cliente_id]);
            $_SESSION['success_message'] = "Toma de inventario iniciada";
            header("Location: inventario-fisico.php?view=detalle_toma&toma_id=$toma_id");
            exit;
            break;
            
        case 'registrar_conteo':
            $detalle_id = intval($_POST['detalle_id']);
            $cantidad_contada = floatval($_POST['cantidad_contada']);
            $observaciones = $_POST['observaciones'] ?? '';
            
            // Obtener detalle actual
            $detalle = $db->fetchOne("
                SELECT d.*, p.nombre as producto_nombre, t.responsable_id, t.estado as toma_estado
                FROM detalle_toma_inventario d
                JOIN productos_inventario p ON d.producto_id = p.id
                JOIN tomas_inventario t ON d.toma_id = t.id
                WHERE d.id = ? AND t.cliente_id = ?
            ", [$detalle_id, $cliente_id]);
            
            if ($detalle && $detalle['toma_estado'] == 'en_proceso') {
                $diferencia = $cantidad_contada - $detalle['cantidad_sistema'];
                $estado = ($diferencia == 0) ? 'verificado' : 'discrepancia';
                
                $db->execute("
                    UPDATE detalle_toma_inventario 
                    SET cantidad_contada = ?, diferencia = ?, estado = ?, 
                        observaciones = ?, fecha_conteo = NOW(), intentos_conteo = intentos_conteo + 1
                    WHERE id = ?
                ", [$cantidad_contada, $diferencia, $estado, $observaciones, $detalle_id]);
                
                // Actualizar contadores de la toma
                $db->execute("
                    UPDATE tomas_inventario 
                    SET productos_contados = (
                        SELECT COUNT(*) FROM detalle_toma_inventario 
                        WHERE toma_id = ? AND estado IN ('contado', 'verificado', 'discrepancia')
                    ),
                    productos_pendientes = (
                        SELECT COUNT(*) FROM detalle_toma_inventario 
                        WHERE toma_id = ? AND estado = 'pendiente'
                    ),
                    productos_discrepancia = (
                        SELECT COUNT(*) FROM detalle_toma_inventario 
                        WHERE toma_id = ? AND estado = 'discrepancia'
                    )
                    WHERE id = ?
                ", [$detalle['toma_id'], $detalle['toma_id'], $detalle['toma_id'], $detalle['toma_id']]);
                
                // Si hay discrepancia, notificar al supervisor
                if ($diferencia != 0) {
                    $db->execute("
                        INSERT INTO notificaciones_inventario (cliente_id, usuario_id, tipo, mensaje)
                        VALUES (?, ?, 'discrepancia', ?)
                    ", [$cliente_id, $detalle['responsable_id'], 
                        "Discrepancia en {$detalle['producto_nombre']}: Sistema: {$detalle['cantidad_sistema']}, Contado: $cantidad_contada"]);
                }
                
                $_SESSION['success_message'] = "Conteo registrado exitosamente";
                header("Location: inventario-fisico.php?view=detalle_toma&toma_id=" . $detalle['toma_id']);
                exit;
            }
            break;
            
        case 'marcar_no_encontrado':
            $detalle_id = intval($_POST['detalle_id']);
            $motivo = $_POST['motivo_no_encontrado'] ?? '';
            
            $db->execute("
                UPDATE detalle_toma_inventario 
                SET estado = 'no_encontrado', observaciones = ?, fecha_conteo = NOW()
                WHERE id = ?
            ", [$motivo, $detalle_id]);
            
            $_SESSION['success_message'] = "Producto marcado como no encontrado";
            header("Location: inventario-fisico.php?view=detalle_toma&toma_id=" . $_POST['toma_id']);
            exit;
            break;
            
        case 'solicitar_reconteo':
            $detalle_id = intval($_POST['detalle_id']);
            $motivo = $_POST['motivo_reconteo'] ?? '';
            
            $db->execute("
                UPDATE detalle_toma_inventario 
                SET necesita_reconteo = TRUE, motivo_reconteo = ?, estado = 'pendiente', intentos_conteo = 0
                WHERE id = ?
            ", [$motivo, $detalle_id]);
            
            // Notificar al empleado
            $detalle = $db->fetchOne("
                SELECT d.*, t.responsable_id, p.nombre as producto_nombre
                FROM detalle_toma_inventario d
                JOIN tomas_inventario t ON d.toma_id = t.id
                JOIN productos_inventario p ON d.producto_id = p.id
                WHERE d.id = ?
            ", [$detalle_id]);
            
            $db->execute("
                INSERT INTO notificaciones_inventario (cliente_id, usuario_id, tipo, mensaje)
                VALUES (?, ?, 'reconteo', ?)
            ", [$cliente_id, $detalle['responsable_id'], 
                "Se solicita reconteo de {$detalle['producto_nombre']}. Motivo: $motivo"]);
                
            $_SESSION['success_message'] = "Reconteo solicitado";
            header("Location: inventario-fisico.php?view=detalle_toma&toma_id=" . $detalle['toma_id']);
            exit;
            break;
            
        case 'aprobar_conteo':
            $detalle_id = intval($_POST['detalle_id']);
            
            $db->execute("
                UPDATE detalle_toma_inventario 
                SET estado = 'verificado', fecha_verificacion = NOW()
                WHERE id = ?
            ", [$detalle_id]);
            
            $_SESSION['success_message'] = "Conteo aprobado";
            header("Location: inventario-fisico.php?view=detalle_toma&toma_id=" . $_POST['toma_id']);
            exit;
            break;
            
        case 'ajustar_inventario':
            $toma_id = intval($_POST['toma_id']);
            $ajustar = $_POST['ajustar'] ?? [];
            
            foreach ($ajustar as $detalle_id) {
                $detalle = $db->fetchOne("SELECT * FROM detalle_toma_inventario WHERE id = ?", [$detalle_id]);
                if ($detalle && $detalle['cantidad_contada'] !== null) {
                    // Actualizar stock del producto
                    $db->execute("
                        UPDATE productos_inventario 
                        SET stock_actual = ?
                        WHERE id = ?
                    ", [$detalle['cantidad_contada'], $detalle['producto_id']]);
                    
                    // Registrar en kardex (ajuste por toma física)
                    $db->execute("
                        INSERT INTO kardex_inventario 
                        (cliente_id, producto_id, tipo_movimiento, cantidad, cantidad_anterior, cantidad_nueva, 
                         referencia_id, motivo, usuario_id)
                        VALUES (?, ?, 'ajuste_inventario', ?, ?, ?, ?, ?, ?)
                    ", [
                        $cliente_id, $detalle['producto_id'], 
                        $detalle['diferencia'],
                        $detalle['cantidad_sistema'],
                        $detalle['cantidad_contada'],
                        $toma_id,
                        'Ajuste por toma física de inventario',
                        $user_id
                    ]);
                    
                    // Marcar como verificado
                    $db->execute("
                        UPDATE detalle_toma_inventario 
                        SET estado = 'verificado', fecha_verificacion = NOW()
                        WHERE id = ?
                    ", [$detalle_id]);
                }
            }
            
            // Marcar toma como ajustada
            $db->execute("
                UPDATE tomas_inventario 
                SET estado = 'ajustada', fecha_fin_real = NOW()
                WHERE id = ?
            ", [$toma_id]);
            
            $_SESSION['success_message'] = "Inventario ajustado exitosamente";
            header("Location: inventario-fisico.php?view=detalle_toma&toma_id=$toma_id");
            exit;
            break;
    }
}

// Obtener datos según la vista
$data = [];
switch ($view) {
    case 'listado_tomas':
        $data['tomas'] = $db->fetchAll("
            SELECT ti.*, a.nombre as almacen_nombre, 
                   u_resp.nombre as responsable_nombre, u_sup.nombre as supervisor_nombre
            FROM tomas_inventario ti
            JOIN almacenes_inventario a ON ti.almacen_id = a.id
            JOIN usuarios u_resp ON ti.responsable_id = u_resp.id
            JOIN usuarios u_sup ON ti.supervisor_id = u_sup.id
            WHERE ti.cliente_id = ?
            ORDER BY ti.fecha_creacion DESC
            LIMIT 50
        ", [$cliente_id]);
        break;
        
    case 'detalle_toma':
        $toma_id = intval($_GET['toma_id']);
        $data['toma'] = $db->fetchOne("
            SELECT ti.*, a.nombre as almacen_nombre, 
                   u_resp.nombre as responsable_nombre, u_sup.nombre as supervisor_nombre,
                   u_resp.id as responsable_id
            FROM tomas_inventario ti
            JOIN almacenes_inventario a ON ti.almacen_id = a.id
            JOIN usuarios u_resp ON ti.responsable_id = u_resp.id
            JOIN usuarios u_sup ON ti.supervisor_id = u_sup.id
            WHERE ti.id = ? AND ti.cliente_id = ?
        ", [$toma_id, $cliente_id]);
        
        $data['detalles'] = $db->fetchAll("
            SELECT d.*, p.codigo, p.nombre as producto_nombre, p.ubicacion
            FROM detalle_toma_inventario d
            JOIN productos_inventario p ON d.producto_id = p.id
            WHERE d.toma_id = ?
            ORDER BY d.estado, p.nombre
        ", [$toma_id]);
        
        // Obtener detalle específico para formularios
        if ($detalle_id_form && $action_form) {
            $data['detalle_form'] = $db->fetchOne("
                SELECT d.*, p.codigo, p.nombre as producto_nombre, p.ubicacion
                FROM detalle_toma_inventario d
                JOIN productos_inventario p ON d.producto_id = p.id
                WHERE d.id = ? AND d.toma_id = ?
            ", [$detalle_id_form, $toma_id]);
        }
        break;
        
    case 'asignaciones':
        $data['tomas_asignadas'] = $db->fetchAll("
            SELECT ti.*, a.nombre as almacen_nombre, u_sup.nombre as supervisor_nombre
            FROM tomas_inventario ti
            JOIN almacenes_inventario a ON ti.almacen_id = a.id
            JOIN usuarios u_sup ON ti.supervisor_id = u_sup.id
            WHERE ti.responsable_id = ? AND ti.cliente_id = ? AND ti.estado IN ('planificada', 'en_proceso')
            ORDER BY ti.estado, ti.fecha_planificada DESC
        ", [$user_id, $cliente_id]);
        break;
        
    case 'nueva_toma':
        $data['almacenes'] = $db->fetchAll("
            SELECT * FROM almacenes_inventario 
            WHERE cliente_id = ? AND estado = 'activo'
        ", [$cliente_id]);
        
        $data['empleados'] = $db->fetchAll("
            SELECT * FROM usuarios 
            WHERE cliente_id = ? AND rol IN ('operador', 'conductor') AND estado = 1
        ", [$cliente_id]);
        break;
        
    case 'mis_conteos':
        $toma_id = intval($_GET['toma_id']);
        $data['detalles_pendientes'] = $db->fetchAll("
            SELECT d.*, p.codigo, p.nombre as producto_nombre, p.ubicacion,
                   ti.codigo_toma, a.nombre as almacen_nombre
            FROM detalle_toma_inventario d
            JOIN productos_inventario p ON d.producto_id = p.id
            JOIN tomas_inventario ti ON d.toma_id = ti.id
            JOIN almacenes_inventario a ON ti.almacen_id = a.id
            WHERE ti.responsable_id = ? AND ti.id = ? AND d.estado = 'pendiente'
            ORDER BY p.nombre
        ", [$user_id, $toma_id]);
        break;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Sistema de Logística</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* ===== ESTILOS PRINCIPALES ===== */
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --gray-light: #ecf0f1;
            --gray-medium: #bdc3c7;
            --gray-dark: #7f8c8d;
            
            --border-radius: 8px;
            --box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }
        
        body {
            background-color: #f5f7fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        
        .content-body {
            padding: 20px;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header del Dashboard */
        .dashboard-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .welcome-section h1 {
            color: var(--primary-color);
            margin: 0 0 5px 0;
            font-size: 1.8rem;
        }
        
        .dashboard-subtitle {
            color: var(--gray-dark);
            margin: 0;
            font-size: 0.95rem;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-badge {
            background: var(--primary-color);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Tarjetas */
        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
            border: none;
        }
        
        .card-header {
            background: white;
            border-bottom: 1px solid var(--gray-light);
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .card-header h3 {
            margin: 0;
            color: var(--primary-color);
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-actions {
            display: flex;
            gap: 10px;
        }
        
        .card-body {
            padding: 30px;
        }
        
        /* Botones */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
        }
        
        .btn-primary {
            background: var(--secondary-color);
            color: white;
        }
        
        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }
        
        .btn-success {
            background: var(--success-color);
            color: white;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: white;
        }
        
        .btn-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        /* Tablas */
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: var(--primary-color);
            color: white;
            border: none;
            font-weight: 600;
            padding: 15px;
        }
        
        .table tbody tr:hover {
            background: rgba(52, 152, 219, 0.05);
        }
        
        /* Badges de estado */
        .status-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.success {
            background: rgba(39, 174, 96, 0.15);
            color: var(--success-color);
        }
        
        .status-badge.info {
            background: rgba(52, 152, 219, 0.15);
            color: var(--secondary-color);
        }
        
        .status-badge.warning {
            background: rgba(243, 156, 18, 0.15);
            color: var(--warning-color);
        }
        
        .status-badge.danger {
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger-color);
        }
        
        /* Formularios */
        .form-label {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 8px;
        }
        
        .form-control {
            border: 1px solid var(--gray-medium);
            border-radius: var(--border-radius);
            padding: 10px 15px;
            transition: var(--transition);
        }
        
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        
        /* Estadísticas */
        .stat-card-sm {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }
        
        .stat-card-sm .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card-sm .stat-label {
            color: var(--gray-dark);
            font-size: 0.9rem;
        }
        
        /* Estado vacío */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--gray-medium);
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .empty-state h4 {
            color: var(--gray-dark);
            margin-bottom: 15px;
            font-weight: 400;
        }
        
        /* Alertas */
        .alert {
            border-radius: var(--border-radius);
            border: none;
            padding: 15px 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        
        .alert-success {
            background: rgba(39, 174, 96, 0.15);
            color: #155724;
            border-left: 4px solid var(--success-color);
        }
        
        .alert-info {
            background: rgba(52, 152, 219, 0.15);
            color: #0c5460;
            border-left: 4px solid var(--secondary-color);
        }
        
        .alert-warning {
            background: rgba(243, 156, 18, 0.15);
            color: #856404;
            border-left: 4px solid var(--warning-color);
        }
        
        .alert-danger {
            background: rgba(231, 76, 60, 0.15);
            color: #721c24;
            border-left: 4px solid var(--danger-color);
        }
        
        /* Formularios en línea */
        .inline-form-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border: 1px solid #e0e0e0;
        }
        
        .inline-form-title {
            color: var(--primary-color);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--secondary-color);
        }
        
        .form-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-body {
                padding: 20px;
            }
            
            .content-body {
                padding: 15px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="content-body">
        <!-- Header del Dashboard -->
        <div class="dashboard-header">
            <div class="welcome-section">
                <h1>Toma de Inventario Físico</h1>
                <p class="dashboard-subtitle">Control y seguimiento de inventarios - <?php echo date('d/m/Y'); ?></p>
            </div>
            <div class="header-actions">
                <span class="user-badge">
                    <i class="fas fa-user-circle"></i>
                    <?php echo htmlspecialchars($usuario_actual['rol'] ?? 'Usuario'); ?>
                </span>
                
                <?php if (in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>
                <a href="inventario-fisico.php?view=nueva_toma" class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i>
                    Nueva Toma
                </a>
                <?php endif; ?>
                
                <a href="inventario-fisico.php" class="btn btn-outline">
                    <i class="fas fa-sync-alt"></i>
                    Actualizar
                </a>
            </div>
        </div>

        <!-- Mensajes de éxito/error -->
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <div>
                <?php echo $_SESSION['success_message']; ?>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>

        <!-- Contenido según la vista -->
        <?php if ($view == 'nueva_toma' && in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>
            
            <!-- Vista: Crear nueva toma -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-check"></i> Crear Nueva Toma de Inventario</h3>
                    <div class="card-actions">
                        <a href="inventario-fisico.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-arrow-left"></i>
                            Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="crear_toma">
                        
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label for="nombre_toma" class="form-label">Nombre de la Toma</label>
                                <input type="text" class="form-control" id="nombre_toma" name="nombre_toma" 
                                       placeholder="Ej: Toma mensual Almacén Central" required>
                            </div>
                            <div class="col-md-6">
                                <label for="almacen_id" class="form-label">Almacén</label>
                                <select class="form-control" id="almacen_id" name="almacen_id" required>
                                    <option value="">Seleccionar almacén</option>
                                    <?php foreach ($data['almacenes'] as $almacen): ?>
                                    <option value="<?php echo $almacen['id']; ?>">
                                        <?php echo htmlspecialchars($almacen['nombre']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row mb-4">
                            <div class="col-md-4">
                                <label for="responsable_id" class="form-label">Responsable</label>
                                <select class="form-control" id="responsable_id" name="responsable_id" required>
                                    <option value="">Seleccionar empleado</option>
                                    <?php foreach ($data['empleados'] as $empleado): ?>
                                    <option value="<?php echo $empleado['id']; ?>">
                                        <?php echo htmlspecialchars($empleado['nombre'] . ' ' . ($empleado['apellido'] ?? '')); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="fecha_planificada" class="form-label">Fecha Planificada</label>
                                <input type="date" class="form-control" id="fecha_planificada" 
                                       name="fecha_planificada" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-2">
                                <label for="hora_inicio_planificada" class="form-label">Hora Inicio</label>
                                <input type="time" class="form-control" id="hora_inicio_planificada" 
                                       name="hora_inicio_planificada" value="08:00" required>
                            </div>
                            <div class="col-md-2">
                                <label for="hora_fin_planificada" class="form-label">Hora Fin</label>
                                <input type="time" class="form-control" id="hora_fin_planificada" 
                                       name="hora_fin_planificada" value="17:00" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label">Productos a Incluir (seleccione un almacén primero)</label>
                            <div id="productos-container" class="border rounded p-3">
                                <div class="text-center text-muted">
                                    Los productos se cargarán cuando se seleccione un almacén
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Crear Toma de Inventario
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
        <?php elseif ($view == 'detalle_toma' && isset($data['toma'])): ?>
            
            <!-- Vista: Detalle de una toma -->
            <div class="card">
                <div class="card-header">
                    <h3>
                        <i class="fas fa-clipboard-list"></i>
                        Toma: <?php echo htmlspecialchars($data['toma']['codigo_toma']); ?>
                        <small class="text-muted">- <?php echo htmlspecialchars($data['toma']['nombre']); ?></small>
                    </h3>
                    <div class="card-actions">
                        <a href="inventario-fisico.php" class="btn btn-outline btn-sm">
                            <i class="fas fa-arrow-left"></i>
                            Volver
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Información general -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card stat-card-sm">
                                <div class="card-body">
                                    <div class="info-label">Estado</div>
                                    <div class="info-value">
                                        <span class="status-badge <?php 
                                            echo $data['toma']['estado'] == 'ajustada' ? 'success' : 
                                                 ($data['toma']['estado'] == 'en_proceso' ? 'info' : 
                                                 ($data['toma']['estado'] == 'completada' ? 'success' : 'warning')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $data['toma']['estado'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card-sm">
                                <div class="card-body">
                                    <div class="info-label">Almacén</div>
                                    <div class="info-value"><?php echo htmlspecialchars($data['toma']['almacen_nombre']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card-sm">
                                <div class="card-body">
                                    <div class="info-label">Responsable</div>
                                    <div class="info-value"><?php echo htmlspecialchars($data['toma']['responsable_nombre']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stat-card-sm">
                                <div class="card-body">
                                    <div class="info-label">Supervisor</div>
                                    <div class="info-value"><?php echo htmlspecialchars($data['toma']['supervisor_nombre']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Estadísticas -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card-sm">
                                <div class="stat-number"><?php echo $data['toma']['total_productos']; ?></div>
                                <div class="stat-label">Total Productos</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-sm">
                                <div class="stat-number text-success"><?php echo $data['toma']['productos_contados']; ?></div>
                                <div class="stat-label">Contados</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-sm">
                                <div class="stat-number text-warning"><?php echo $data['toma']['productos_pendientes']; ?></div>
                                <div class="stat-label">Pendientes</div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card-sm">
                                <div class="stat-number text-danger"><?php echo $data['toma']['productos_discrepancia']; ?></div>
                                <div class="stat-label">Discrepancias</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- FORMULARIO EN LÍNEA (cuando se necesita) -->
                    <?php if ($action_form && $detalle_id_form && isset($data['detalle_form'])): ?>
                        <div class="inline-form-container">
                            <?php if ($action_form == 'conteo'): ?>
                                <h4 class="inline-form-title">Registrar Conteo</h4>
                                <p><strong>Producto:</strong> <?php echo htmlspecialchars($data['detalle_form']['producto_nombre']); ?> (<?php echo htmlspecialchars($data['detalle_form']['codigo']); ?>)</p>
                                <p><strong>Ubicación:</strong> <?php echo htmlspecialchars($data['detalle_form']['ubicacion']); ?></p>
                                <p><strong>Cantidad en sistema:</strong> <?php echo $data['detalle_form']['cantidad_sistema']; ?></p>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="registrar_conteo">
                                    <input type="hidden" name="detalle_id" value="<?php echo $data['detalle_form']['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="cantidad_contada" class="form-label">Cantidad Contada *</label>
                                        <input type="number" step="0.01" class="form-control" id="cantidad_contada" name="cantidad_contada" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="observaciones" class="form-label">Observaciones (opcional)</label>
                                        <textarea class="form-control" id="observaciones" name="observaciones" rows="3" placeholder="Ej: Producto dañado, embalaje abierto, etc."></textarea>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-success">Guardar Conteo</button>
                                        <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?php echo $toma_id; ?>" class="btn btn-outline">Cancelar</a>
                                    </div>
                                </form>
                                
                            <?php elseif ($action_form == 'no_encontrado'): ?>
                                <h4 class="inline-form-title">Marcar Producto como No Encontrado</h4>
                                <p><strong>Producto:</strong> <?php echo htmlspecialchars($data['detalle_form']['producto_nombre']); ?> (<?php echo htmlspecialchars($data['detalle_form']['codigo']); ?>)</p>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="marcar_no_encontrado">
                                    <input type="hidden" name="detalle_id" value="<?php echo $data['detalle_form']['id']; ?>">
                                    <input type="hidden" name="toma_id" value="<?php echo $toma_id; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="motivo_no_encontrado" class="form-label">Motivo / Observaciones *</label>
                                        <textarea class="form-control" id="motivo_no_encontrado" name="motivo_no_encontrado" rows="4" required placeholder="Explique por qué no se encontró el producto"></textarea>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-warning">Marcar como No Encontrado</button>
                                        <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?php echo $toma_id; ?>" class="btn btn-outline">Cancelar</a>
                                    </div>
                                </form>
                                
                            <?php elseif ($action_form == 'reconteo'): ?>
                                <h4 class="inline-form-title">Solicitar Reconteo</h4>
                                <p><strong>Producto:</strong> <?php echo htmlspecialchars($data['detalle_form']['producto_nombre']); ?> (<?php echo htmlspecialchars($data['detalle_form']['codigo']); ?>)</p>
                                <p><strong>Cantidad en sistema:</strong> <?php echo $data['detalle_form']['cantidad_sistema']; ?></p>
                                <p><strong>Cantidad contada:</strong> <?php echo $data['detalle_form']['cantidad_contada']; ?></p>
                                
                                <form method="POST" action="">
                                    <input type="hidden" name="action" value="solicitar_reconteo">
                                    <input type="hidden" name="detalle_id" value="<?php echo $data['detalle_form']['id']; ?>">
                                    
                                    <div class="mb-3">
                                        <label for="motivo_reconteo" class="form-label">Motivo del Reconteo *</label>
                                        <textarea class="form-control" id="motivo_reconteo" name="motivo_reconteo" rows="4" required placeholder="Explique por qué se necesita un reconteo"></textarea>
                                    </div>
                                    
                                    <div class="form-actions">
                                        <button type="submit" class="btn btn-info">Solicitar Reconteo</button>
                                        <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?php echo $toma_id; ?>" class="btn btn-outline">Cancelar</a>
                                    </div>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Acciones principales -->
                    <?php if ($data['toma']['estado'] == 'planificada' && in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>
                    <div class="d-flex mb-4">
                        <form method="POST" action="" class="me-2">
                            <input type="hidden" name="action" value="iniciar_toma">
                            <input type="hidden" name="toma_id" value="<?php echo $data['toma']['id']; ?>">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-play-circle"></i>
                                Iniciar Toma
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Si es empleado responsable, mostrar botón para ir a conteos -->
                    <?php if ($data['toma']['responsable_id'] == $user_id && $data['toma']['estado'] == 'en_proceso'): ?>
                    <div class="d-flex mb-4">
                        <a href="inventario-fisico.php?view=mis_conteos&toma_id=<?php echo $data['toma']['id']; ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-clipboard-check"></i>
                            Ir a Contar Productos
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Lista de productos -->
                    <h4 class="mb-3">Detalle de Productos</h4>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Ubicación</th>
                                    <th>Cant. Sistema</th>
                                    <th>Cant. Contada</th>
                                    <th>Diferencia</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['detalles'] as $detalle): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($detalle['codigo']); ?></td>
                                    <td><?php echo htmlspecialchars($detalle['producto_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($detalle['ubicacion']); ?></td>
                                    <td><?php echo $detalle['cantidad_sistema']; ?></td>
                                    <td>
                                        <?php if ($detalle['cantidad_contada'] !== null): ?>
                                        <?php echo $detalle['cantidad_contada']; ?>
                                        <?php else: ?>
                                        <span class="text-muted">No contado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($detalle['diferencia'] != 0): ?>
                                        <span class="text-danger"><?php echo $detalle['diferencia']; ?></span>
                                        <?php else: ?>
                                        <span class="text-success"><?php echo $detalle['diferencia']; ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php 
                                            echo $detalle['estado'] == 'verificado' ? 'success' : 
                                                 ($detalle['estado'] == 'discrepancia' ? 'danger' : 
                                                 ($detalle['estado'] == 'no_encontrado' ? 'warning' : 
                                                 ($detalle['estado'] == 'contado' ? 'info' : 'secondary'))); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $detalle['estado'])); ?>
                                        </span>
                                        <?php if ($detalle['necesita_reconteo']): ?>
                                        <span class="badge bg-warning">Reconteo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <!-- Acciones según rol y estado -->
                                        <?php if ($data['toma']['estado'] == 'en_proceso' && $user_id == $data['toma']['responsable_id'] && $detalle['estado'] == 'pendiente'): ?>
                                        <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?php echo $toma_id; ?>&action_form=conteo&detalle_id=<?php echo $detalle['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-edit"></i> Contar
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($detalle['estado'] == 'discrepancia' && in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>
                                        <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?php echo $toma_id; ?>&action_form=reconteo&detalle_id=<?php echo $detalle['id']; ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="fas fa-redo"></i> Reconteo
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($detalle['estado'] == 'discrepancia' && in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="aprobar_conteo">
                                            <input type="hidden" name="detalle_id" value="<?php echo $detalle['id']; ?>">
                                            <input type="hidden" name="toma_id" value="<?php echo $data['toma']['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success">
                                                <i class="fas fa-check"></i> Aprobar
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Botón para ajustar inventario (supervisor) -->
                    <?php if ($data['toma']['estado'] == 'en_proceso' && $data['toma']['productos_pendientes'] == 0 && 
                              in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>
                    <div class="mt-4">
                        <form method="POST" action="" id="form-ajustar">
                            <input type="hidden" name="action" value="ajustar_inventario">
                            <input type="hidden" name="toma_id" value="<?php echo $data['toma']['id']; ?>">
                            
                            <div class="alert alert-info">
                                <h5><i class="fas fa-info-circle"></i> Ajuste de Inventario</h5>
                                <p>Al hacer clic en "Ajustar Inventario", se actualizarán los stocks con las cantidades contadas.</p>
                                
                                <?php 
                                $discrepancias = array_filter($data['detalles'], function($d) {
                                    return $d['estado'] == 'discrepancia';
                                });
                                ?>
                                
                                <?php if (count($discrepancias) > 0): ?>
                                <div class="mt-3">
                                    <label class="form-label">Seleccionar discrepancias para ajustar:</label>
                                    <?php foreach ($discrepancias as $detalle): ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="ajustar[]" value="<?php echo $detalle['id']; ?>" checked>
                                        <label class="form-check-label">
                                            <?php echo htmlspecialchars($detalle['producto_nombre']); ?>:
                                            Sistema: <?php echo $detalle['cantidad_sistema']; ?>,
                                            Contado: <?php echo $detalle['cantidad_contada']; ?>
                                            (Diferencia: <?php echo $detalle['diferencia']; ?>)
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php else: ?>
                                <p class="mb-0">No hay discrepancias. El inventario está correcto.</p>
                                <?php endif; ?>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-check-double"></i>
                                Ajustar Inventario
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($view == 'asignaciones' && in_array($rol, ['operador', 'conductor'])): ?>
            
            <!-- Vista: Asignaciones para empleados -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Mis Tareas de Inventario</h3>
                </div>
                <div class="card-body">
                    <?php if (empty($data['tomas_asignadas'])): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check"></i>
                        <h4>No hay tareas asignadas</h4>
                        <p>No tienes tomas de inventario asignadas en este momento.</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Almacén</th>
                                    <th>Estado</th>
                                    <th>Fecha Planificada</th>
                                    <th>Productos</th>
                                    <th>Progreso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['tomas_asignadas'] as $toma): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($toma['codigo_toma']); ?></td>
                                    <td><?php echo htmlspecialchars($toma['almacen_nombre']); ?></td>
                                    <td>
                                        <span class="status-badge <?php 
                                            echo $toma['estado'] == 'completada' ? 'success' : 
                                                 ($toma['estado'] == 'en_proceso' ? 'info' : 'warning'); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $toma['estado'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($toma['fecha_planificada'])); ?></td>
                                    <td>
                                        <?php echo $toma['total_productos']; ?> productos
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <?php 
                                            $porcentaje = $toma['total_productos'] > 0 ? 
                                                ($toma['productos_contados'] / $toma['total_productos']) * 100 : 0;
                                            ?>
                                            <div class="progress-bar bg-success" role="progressbar" 
                                                 style="width: <?php echo $porcentaje; ?>%">
                                                <?php echo round($porcentaje); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($toma['estado'] == 'planificada' || $toma['estado'] == 'en_proceso'): ?>
                                        <a href="inventario-fisico.php?view=mis_conteos&toma_id=<?php echo $toma['id']; ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-play-circle"></i>
                                            <?php echo $toma['estado'] == 'planificada' ? 'Iniciar' : 'Continuar'; ?>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php elseif ($view == 'mis_conteos' && isset($data['detalles_pendientes'])): ?>
            
            <!-- Vista: Conteo de productos para empleados -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-boxes"></i> Conteo de Productos</h3>
                    <div class="card-actions">
                        <a href="inventario-fisico.php?view=asignaciones" class="btn btn-outline btn-sm">
                            <i class="fas fa-arrow-left"></i>
                            Volver a Asignaciones
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($data['detalles_pendientes'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <strong>¡Excelente!</strong> Has contado todos los productos asignados.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Instrucciones:</strong> Cuenta físicamente cada producto y registra la cantidad encontrada.
                        Si el producto no se encuentra, marca como "No Encontrado".
                    </div>
                    
                    <div class="row">
                        <?php foreach ($data['detalles_pendientes'] as $detalle): ?>
                        <div class="col-md-6 mb-4">
                            <div class="card" style="height: 100%;">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($detalle['producto_nombre']); ?></h5>
                                    <h6 class="card-subtitle mb-2 text-muted">Código: <?php echo htmlspecialchars($detalle['codigo']); ?></h6>
                                    
                                    <div class="mb-3">
                                        <div><strong>Ubicación:</strong> <?php echo htmlspecialchars($detalle['ubicacion']); ?></div>
                                        <div><strong>Almacén:</strong> <?php echo htmlspecialchars($detalle['almacen_nombre']); ?></div>
                                        <div><strong>Sistema espera:</strong> <span class="badge bg-primary"><?php echo $detalle['cantidad_sistema']; ?> unidades</span></div>
                                    </div>
                                    
                                    <div class="mt-3">
                                        <form method="POST" action="" class="conteo-form">
                                            <input type="hidden" name="action" value="registrar_conteo">
                                            <input type="hidden" name="detalle_id" value="<?php echo $detalle['id']; ?>">
                                            
                                            <div class="input-group mb-2">
                                                <span class="input-group-text">Cantidad Contada:</span>
                                                <input type="number" step="0.01" class="form-control" 
                                                       name="cantidad_contada" placeholder="0" required>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <textarea class="form-control form-control-sm" 
                                                          name="observaciones" 
                                                          placeholder="Observaciones (opcional)"></textarea>
                                            </div>
                                            
                                            <div class="d-flex justify-content-between">
                                                <button type="submit" class="btn btn-success btn-sm">
                                                    <i class="fas fa-check"></i> Registrar Conteo
                                                </button>
                                                
                                                <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?php echo $detalle['toma_id']; ?>&action_form=no_encontrado&detalle_id=<?php echo $detalle['id']; ?>" 
                                                   class="btn btn-warning btn-sm">
                                                    <i class="fas fa-times"></i> No Encontrado
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php else: ?>
            
            <!-- Vista por defecto: Listado de tomas (para supervisores) -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-list"></i> Toma de Inventarios</h3>
                    <div class="card-actions">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                                <i class="fas fa-filter"></i> Filtros
                            </button>
                            <div class="dropdown-menu">
                                <a class="dropdown-item" href="inventario-fisico.php?view=listado_tomas&estado=todas">Todas</a>
                                <a class="dropdown-item" href="inventario-fisico.php?view=listado_tomas&estado=en_proceso">En Proceso</a>
                                <a class="dropdown-item" href="inventario-fisico.php?view=listado_tomas&estado=completadas">Completadas</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($data['tomas'])): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-check"></i>
                        <h4>No hay tomas de inventario</h4>
                        <p>No se han creado tomas de inventario aún.</p>
                        <a href="inventario-fisico.php?view=nueva_toma" class="btn btn-primary">
                            <i class="fas fa-plus-circle"></i>
                            Crear Nueva Toma
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Almacén</th>
                                    <th>Responsable</th>
                                    <th>Estado</th>
                                    <th>Fecha Planificada</th>
                                    <th>Progreso</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($data['tomas'] as $toma): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($toma['codigo_toma']); ?></td>
                                    <td><?php echo htmlspecialchars($toma['almacen_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($toma['responsable_nombre']); ?></td>
                                    <td>
                                        <span class="status-badge <?php 
                                            echo $toma['estado'] == 'ajustada' ? 'success' : 
                                                 ($toma['estado'] == 'en_proceso' ? 'info' : 
                                                 ($toma['estado'] == 'completada' ? 'success' : 'warning')); 
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $toma['estado'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($toma['fecha_planificada'])); ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="progress flex-grow-1" style="height: 10px;">
                                                <?php 
                                                $porcentaje = $toma['total_productos'] > 0 ? 
                                                    ($toma['productos_contados'] / $toma['total_productos']) * 100 : 0;
                                                ?>
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?php echo $porcentaje; ?>%">
                                                </div>
                                            </div>
                                            <small class="ms-2"><?php echo round($porcentaje); ?>%</small>
                                        </div>
                                    </td>
                                    <td>
                                        <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?php echo $toma['id']; ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                            Ver
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        <?php endif; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    // Cargar productos cuando se selecciona un almacén
    document.getElementById('almacen_id')?.addEventListener('change', function() {
        const almacenId = this.value;
        const container = document.getElementById('productos-container');
        
        if (!almacenId) {
            container.innerHTML = '<div class="text-center text-muted">Seleccione un almacén para cargar los productos</div>';
            return;
        }
        
        container.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Cargando productos...</div>';
        
        // Simular carga de productos (en producción esto sería una llamada AJAX)
        setTimeout(() => {
            container.innerHTML = `
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="productos[]" value="1" id="producto_1" checked>
                            <label class="form-check-label" for="producto_1">
                                <strong>PROD-001</strong> - Producto Ejemplo 1
                                <br>
                                <small class="text-muted">
                                    Stock: 100 unidades | Ubicación: Estante A1
                                </small>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6 mb-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" 
                                   name="productos[]" value="2" id="producto_2" checked>
                            <label class="form-check-label" for="producto_2">
                                <strong>PROD-002</strong> - Producto Ejemplo 2
                                <br>
                                <small class="text-muted">
                                    Stock: 50 unidades | Ubicación: Estante B2
                                </small>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    En producción, esta lista se cargaría desde el servidor con los productos reales del almacén seleccionado.
                </div>
            `;
        }, 1000);
    });

    // Validar formularios de conteo
    document.querySelectorAll('.conteo-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            const cantidad = this.querySelector('[name="cantidad_contada"]').value;
            
            if (cantidad === '' || cantidad < 0) {
                e.preventDefault();
                alert('Por favor ingresa una cantidad válida');
                return false;
            }
            
            return true;
        });
    });

    // Validar formulario de ajuste de inventario
    document.getElementById('form-ajustar')?.addEventListener('submit', function(e) {
        const checkboxes = this.querySelectorAll('input[name="ajustar[]"]:checked');
        
        if (checkboxes.length === 0) {
            e.preventDefault();
            alert('Por favor selecciona al menos una discrepancia para ajustar');
            return false;
        }
        
        return confirm('¿Estás seguro de que deseas ajustar el inventario? Esta acción actualizará los stocks del sistema.');
    });

    // Inicializar dropdowns de Bootstrap
    document.addEventListener('DOMContentLoaded', function() {
        // Inicializar tooltips si los hubiera
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
        
        // Auto-enfocar en el primer campo de formulario cuando hay un formulario activo
        if (document.querySelector('.inline-form-container input')) {
            document.querySelector('.inline-form-container input').focus();
        }
    });

    // Scroll suave a formularios activos
    <?php if ($action_form && $detalle_id_form): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const formContainer = document.querySelector('.inline-form-container');
        if (formContainer) {
            formContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>