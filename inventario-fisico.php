<?php
// inventario-fisico.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Toma de Inventario Físico';
$breadcrumbs = [
    'index.php' => 'Dashboard Logístico',
    'inventario-fisico.php' => 'Toma de Inventario Físico'
];

require_once __DIR__ . '/includes/header.php';

$db         = new Database();
$user_id    = $_SESSION['user_id']     ?? 0;
$cliente_id = $_SESSION['cliente_id']  ?? 0;
$rol        = $_SESSION['rol']         ?? '';

// Obtener información del usuario actual
$usuario_actual = $db->fetchOne("
    SELECT u.*, c.nombre_empresa
    FROM usuarios u
    LEFT JOIN clientes c ON u.cliente_id = c.id
    WHERE u.id = ?
", [$user_id]);

// Determinar la vista según el rol y parámetros
$view            = $_GET['view']        ?? '';
$toma_id         = intval($_GET['toma_id'] ?? 0);
$action_form     = $_GET['action_form'] ?? ''; // Para mostrar formularios específicos (conteo, reconteo, etc.)
$detalle_id_form = intval($_GET['detalle_id'] ?? 0);

// Si es empleado (conductor u operador) y no se especifica vista, mostrar asignaciones
if (in_array($rol, ['operador', 'conductor']) && $view === '') {
    $view = 'asignaciones';
}

// Si es supervisor o admin, por defecto mostrar el listado de tomas
if (in_array($rol, ['supervisor', 'admin', 'super_admin']) && $view === '') {
    $view = 'listado_tomas';
}

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'crear_toma':
            // Validar y crear nueva toma
            $almacen_id          = intval($_POST['almacen_id'] ?? 0);
            $responsable_id      = intval($_POST['responsable_id'] ?? 0);
            $fecha_planificada   = $_POST['fecha_planificada']        ?? '';
            $hora_inicio         = $_POST['hora_inicio_planificada']  ?? '';
            $hora_fin            = $_POST['hora_fin_planificada']     ?? '';
            $productos_seleccionados = $_POST['productos'] ?? [];
            $nombre_toma         = trim($_POST['nombre_toma'] ?? '');

            if ($almacen_id <= 0 || $responsable_id <= 0 || $fecha_planificada === '' || $nombre_toma === '' || empty($productos_seleccionados)) {
                $_SESSION['error_message'] = "Faltan datos obligatorios para crear la toma de inventario.";
                header("Location: inventario-fisico.php?view=nueva_toma");
                exit;
            }

            // Generar código único
            $codigo_toma = 'INV-' . date('Ymd') . '-' . rand(100, 999);

            // Insertar toma
            $db->execute("
                INSERT INTO tomas_inventario (
                    cliente_id,
                    almacen_id,
                    codigo_toma,
                    nombre,
                    estado,
                    responsable_id,
                    supervisor_id,
                    fecha_planificada,
                    hora_inicio_planificada,
                    hora_fin_planificada,
                    total_productos,
                    productos_contados,
                    productos_pendientes,
                    productos_discrepancia
                ) VALUES (
                    ?, ?, ?, ?, 'planificada', ?, ?, ?, ?, ?, ?, 0, ?, 0
                )
            ", [
                $cliente_id,
                $almacen_id,
                $codigo_toma,
                $nombre_toma,
                $responsable_id,
                $user_id,
                $fecha_planificada,
                $hora_inicio,
                $hora_fin,
                count($productos_seleccionados),
                count($productos_seleccionados)
            ]);

            $toma_id = $db->lastInsertId();

            // Insertar detalles
            foreach ($productos_seleccionados as $producto_id) {
                $producto = $db->fetchOne("
                    SELECT stock_actual
                    FROM productos_inventario
                    WHERE id = ? AND cliente_id = ?
                ", [$producto_id, $cliente_id]);

                $stock_actual = $producto['stock_actual'] ?? 0;

                $db->execute("
                    INSERT INTO detalle_toma_inventario (
                        toma_id,
                        producto_id,
                        cantidad_sistema,
                        estado,
                        diferencia,
                        intentos_conteo
                    ) VALUES (
                        ?, ?, ?, 'pendiente', 0, 0
                    )
                ", [$toma_id, $producto_id, $stock_actual]);
            }

            // Notificar al empleado responsable
            $db->execute("
                INSERT INTO notificaciones_inventario (
                    cliente_id,
                    usuario_id,
                    tipo,
                    mensaje
                ) VALUES (?, ?, 'asignacion', ?)
            ", [
                $cliente_id,
                $responsable_id,
                "Se te ha asignado una nueva toma de inventario: $codigo_toma"
            ]);

            $_SESSION['success_message'] = "Toma de inventario creada exitosamente.";
            header("Location: inventario-fisico.php?view=detalle_toma&toma_id={$toma_id}");
            exit;

        case 'iniciar_toma':
            $toma_id = intval($_POST['toma_id'] ?? 0);

            if ($toma_id > 0) {
                $db->execute("
                    UPDATE tomas_inventario
                    SET estado = 'en_proceso',
                        fecha_inicio_real = NOW()
                    WHERE id = ? AND cliente_id = ?
                ", [$toma_id, $cliente_id]);

                $_SESSION['success_message'] = "Toma de inventario iniciada.";
            }

            header("Location: inventario-fisico.php?view=detalle_toma&toma_id={$toma_id}");
            exit;

        case 'registrar_conteo':
            $detalle_id       = intval($_POST['detalle_id'] ?? 0);
            $cantidad_contada = floatval($_POST['cantidad_contada'] ?? 0);
            $observaciones    = $_POST['observaciones'] ?? '';

            // Obtener detalle actual
            $detalle = $db->fetchOne("
                SELECT d.*, p.nombre AS producto_nombre, t.responsable_id, t.estado AS toma_estado, t.id AS toma_id
                FROM detalle_toma_inventario d
                JOIN productos_inventario p ON d.producto_id = p.id
                JOIN tomas_inventario t ON d.toma_id = t.id
                WHERE d.id = ? AND t.cliente_id = ?
            ", [$detalle_id, $cliente_id]);

            if ($detalle && in_array($detalle['toma_estado'], ['en_proceso', 'planificada'])) {
                $diferencia = $cantidad_contada - floatval($detalle['cantidad_sistema']);
                $estado     = ($diferencia == 0) ? 'verificado' : 'discrepancia';

                $db->execute("
                    UPDATE detalle_toma_inventario
                    SET cantidad_contada = ?,
                        diferencia       = ?,
                        estado           = ?,
                        observaciones    = ?,
                        fecha_conteo     = NOW(),
                        intentos_conteo  = intentos_conteo + 1
                    WHERE id = ?
                ", [
                    $cantidad_contada,
                    $diferencia,
                    $estado,
                    $observaciones,
                    $detalle_id
                ]);

                // Actualizar contadores de la toma
                $db->execute("
                    UPDATE tomas_inventario
                    SET productos_contados = (
                            SELECT COUNT(*)
                            FROM detalle_toma_inventario
                            WHERE toma_id = ? AND estado IN ('pendiente', 'contado', 'verificado', 'discrepancia', 'no_encontrado')
                        ),
                        productos_pendientes = (
                            SELECT COUNT(*)
                            FROM detalle_toma_inventario
                            WHERE toma_id = ? AND estado = 'pendiente'
                        ),
                        productos_discrepancia = (
                            SELECT COUNT(*)
                            FROM detalle_toma_inventario
                            WHERE toma_id = ? AND estado = 'discrepancia'
                        )
                    WHERE id = ?
                ", [
                    $detalle['toma_id'],
                    $detalle['toma_id'],
                    $detalle['toma_id'],
                    $detalle['toma_id']
                ]);

                // Si hay discrepancia, notificar al supervisor
                if ($diferencia != 0) {
                    $mensaje_discrepancia = sprintf(
                        "Discrepancia en %s: Sistema: %s, Contado: %s",
                        $detalle['producto_nombre'],
                        $detalle['cantidad_sistema'],
                        $cantidad_contada
                    );

                    // Notificación al supervisor (usuario actual se asume supervisor)
                    $db->execute("
                        INSERT INTO notificaciones_inventario (
                            cliente_id,
                            usuario_id,
                            tipo,
                            mensaje
                        ) VALUES (?, ?, 'discrepancia', ?)
                    ", [
                        $cliente_id,
                        $detalle['responsable_id'],
                        $mensaje_discrepancia
                    ]);
                }

                $_SESSION['success_message'] = "Conteo registrado exitosamente.";
            }

            header("Location: inventario-fisico.php?view=detalle_toma&toma_id={$detalle['toma_id']}");
            exit;

        case 'marcar_no_encontrado':
            $detalle_id = intval($_POST['detalle_id'] ?? 0);
            $toma_id    = intval($_POST['toma_id'] ?? 0);
            $motivo     = $_POST['motivo_no_encontrado'] ?? '';

            if ($detalle_id > 0) {
                $db->execute("
                    UPDATE detalle_toma_inventario
                    SET estado        = 'no_encontrado',
                        observaciones = ?,
                        fecha_conteo  = NOW()
                    WHERE id = ?
                ", [$motivo, $detalle_id]);

                $_SESSION['success_message'] = "Producto marcado como no encontrado.";
            }

            header("Location: inventario-fisico.php?view=detalle_toma&toma_id={$toma_id}");
            exit;

        case 'solicitar_reconteo':
            $detalle_id = intval($_POST['detalle_id'] ?? 0);
            $motivo     = $_POST['motivo_reconteo'] ?? '';

            if ($detalle_id > 0) {
                $db->execute("
                    UPDATE detalle_toma_inventario
                    SET necesita_reconteo = TRUE,
                        motivo_reconteo    = ?,
                        estado             = 'pendiente',
                        intentos_conteo    = 0
                    WHERE id = ?
                ", [$motivo, $detalle_id]);

                // Notificar al empleado responsable
                $detalle = $db->fetchOne("
                    SELECT d.*, t.responsable_id, p.nombre AS producto_nombre, t.id AS toma_id
                    FROM detalle_toma_inventario d
                    JOIN tomas_inventario t ON d.toma_id = t.id
                    JOIN productos_inventario p ON d.producto_id = p.id
                    WHERE d.id = ?
                ", [$detalle_id]);

                if ($detalle) {
                    $mensaje = "Se solicita reconteo de {$detalle['producto_nombre']}. Motivo: {$motivo}";

                    $db->execute("
                        INSERT INTO notificaciones_inventario (
                            cliente_id,
                            usuario_id,
                            tipo,
                            mensaje
                        ) VALUES (?, ?, 'reconteo', ?)
                    ", [
                        $cliente_id,
                        $detalle['responsable_id'],
                        $mensaje
                    ]);

                    $_SESSION['success_message'] = "Reconteo solicitado.";
                    header("Location: inventario-fisico.php?view=detalle_toma&toma_id={$detalle['toma_id']}");
                    exit;
                }
            }

            header("Location: inventario-fisico.php");
            exit;

        case 'aprobar_conteo':
            $detalle_id = intval($_POST['detalle_id'] ?? 0);
            $toma_id    = intval($_POST['toma_id'] ?? 0);

            if ($detalle_id > 0) {
                $db->execute("
                    UPDATE detalle_toma_inventario
                    SET estado            = 'verificado',
                        fecha_verificacion = NOW()
                    WHERE id = ?
                ", [$detalle_id]);

                $_SESSION['success_message'] = "Conteo aprobado.";
            }

            header("Location: inventario-fisico.php?view=detalle_toma&toma_id={$toma_id}");
            exit;

        case 'ajustar_inventario':
            $toma_id = intval($_POST['toma_id'] ?? 0);
            $ajustar = $_POST['ajustar'] ?? [];

            if ($toma_id > 0 && !empty($ajustar)) {
                foreach ($ajustar as $detalle_id) {
                    $detalle_id = intval($detalle_id);
                    $detalle = $db->fetchOne("
                        SELECT *
                        FROM detalle_toma_inventario
                        WHERE id = ?
                    ", [$detalle_id]);

                    if ($detalle && $detalle['cantidad_contada'] !== null) {
                        // Actualizar stock del producto
                        $db->execute("
                            UPDATE productos_inventario
                            SET stock_actual = ?
                            WHERE id = ?
                        ", [
                            $detalle['cantidad_contada'],
                            $detalle['producto_id']
                        ]);

                        // Registrar en kardex (ajuste por toma física)
                        $db->execute("
                            INSERT INTO kardex_inventario (
                                cliente_id,
                                producto_id,
                                tipo_movimiento,
                                cantidad,
                                cantidad_anterior,
                                cantidad_nueva,
                                referencia_id,
                                motivo,
                                usuario_id
                            ) VALUES (
                                ?, ?, 'ajuste_inventario', ?, ?, ?, ?, ?, ?
                            )
                        ", [
                            $cliente_id,
                            $detalle['producto_id'],
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
                            SET estado = 'verificado',
                                fecha_verificacion = NOW()
                            WHERE id = ?
                        ", [$detalle_id]);
                    }
                }

                // Marcar toma como ajustada
                $db->execute("
                    UPDATE tomas_inventario
                    SET estado        = 'ajustada',
                        fecha_fin_real = NOW()
                    WHERE id = ?
                ", [$toma_id]);

                $_SESSION['success_message'] = "Inventario ajustado exitosamente.";
            }

            header("Location: inventario-fisico.php?view=detalle_toma&toma_id={$toma_id}");
            exit;

        case 'exportar_excel':
            $toma_id = intval($_POST['toma_id'] ?? 0);

            if ($toma_id > 0) {
                $toma = $db->fetchOne("
                    SELECT ti.*, a.nombre AS almacen_nombre
                    FROM tomas_inventario ti
                    JOIN almacenes_inventario a ON ti.almacen_id = a.id
                    WHERE ti.id = ? AND ti.cliente_id = ?
                ", [$toma_id, $cliente_id]);

                $detalles = $db->fetchAll("
                    SELECT d.*, p.codigo, p.nombre AS producto_nombre, p.ubicacion
                    FROM detalle_toma_inventario d
                    JOIN productos_inventario p ON d.producto_id = p.id
                    WHERE d.toma_id = ?
                    ORDER BY p.nombre
                ", [$toma_id]);

                header('Content-Type: application/vnd.ms-excel; charset=utf-8');
                header('Content-Disposition: attachment; filename="inventario_toma_' . $toma_id . '.xls"');

                echo "Código Toma:\t" . ($toma['codigo_toma'] ?? '') . "\n";
                echo "Nombre Toma:\t" . ($toma['nombre'] ?? '') . "\n";
                echo "Almacén:\t" . ($toma['almacen_nombre'] ?? '') . "\n";
                echo "Estado:\t" . ($toma['estado'] ?? '') . "\n\n";

                echo "Código\tProducto\tUbicación\tCant. Sistema\tCant. Contada\tDiferencia\tEstado\tObservaciones\n";

                foreach ($detalles as $d) {
                    echo ($d['codigo'] ?? '') . "\t"
                        . ($d['producto_nombre'] ?? '') . "\t"
                        . ($d['ubicacion'] ?? '') . "\t"
                        . ($d['cantidad_sistema'] ?? 0) . "\t"
                        . ($d['cantidad_contada'] ?? 0) . "\t"
                        . ($d['diferencia'] ?? 0) . "\t"
                        . ($d['estado'] ?? '') . "\t"
                        . str_replace(["\n", "\r"], ' ', $d['observaciones'] ?? '') . "\n";
                }

                exit;
            }

            $_SESSION['error_message'] = "No se pudo exportar el inventario.";
            header("Location: inventario-fisico.php");
            exit;
    }
}

// Obtener datos según la vista
$data = [];

switch ($view) {
    case 'listado_tomas':
        // Filtros avanzados (estado, almacén, responsable)
        $estado_filtro      = $_GET['estado']      ?? '';
        $almacen_filtro     = intval($_GET['almacen_id'] ?? 0);
        $responsable_filtro = intval($_GET['responsable_id'] ?? 0);

        $where  = "ti.cliente_id = ?";
        $params = [$cliente_id];

        if ($estado_filtro !== '') {
            $where  .= " AND ti.estado = ?";
            $params[] = $estado_filtro;
        }

        if ($almacen_filtro > 0) {
            $where  .= " AND ti.almacen_id = ?";
            $params[] = $almacen_filtro;
        }

        if ($responsable_filtro > 0) {
            $where  .= " AND ti.responsable_id = ?";
            $params[] = $responsable_filtro;
        }

        $data['tomas'] = $db->fetchAll("
            SELECT ti.*, a.nombre AS almacen_nombre,
                   u_resp.nombre AS responsable_nombre,
                   u_sup.nombre  AS supervisor_nombre
            FROM tomas_inventario ti
            JOIN almacenes_inventario a ON ti.almacen_id = a.id
            JOIN usuarios u_resp ON ti.responsable_id = u_resp.id
            JOIN usuarios u_sup  ON ti.supervisor_id  = u_sup.id
            WHERE {$where}
            ORDER BY ti.fecha_creacion DESC
            LIMIT 100
        ", $params);

        $data['almacenes'] = $db->fetchAll("
            SELECT id, nombre
            FROM almacenes_inventario
            WHERE cliente_id = ? AND estado = 'activo'
            ORDER BY nombre
        ", [$cliente_id]);

        $data['empleados'] = $db->fetchAll("
            SELECT id, nombre
            FROM usuarios
            WHERE cliente_id = ? AND rol IN ('operador', 'conductor')
            ORDER BY nombre
        ", [$cliente_id]);

        break;

    case 'detalle_toma':
        $toma_id = intval($_GET['toma_id'] ?? 0);

        $data['toma'] = $db->fetchOne("
            SELECT ti.*, a.nombre AS almacen_nombre,
                   u_resp.nombre AS responsable_nombre,
                   u_sup.nombre  AS supervisor_nombre,
                   u_resp.id     AS responsable_id
            FROM tomas_inventario ti
            JOIN almacenes_inventario a ON ti.almacen_id = a.id
            JOIN usuarios u_resp ON ti.responsable_id = u_resp.id
            JOIN usuarios u_sup  ON ti.supervisor_id  = u_sup.id
            WHERE ti.id = ? AND ti.cliente_id = ?
        ", [$toma_id, $cliente_id]);

        $data['detalles'] = $db->fetchAll("
            SELECT d.*, p.codigo, p.nombre AS producto_nombre, p.ubicacion
            FROM detalle_toma_inventario d
            JOIN productos_inventario p ON d.producto_id = p.id
            WHERE d.toma_id = ?
            ORDER BY d.estado, p.nombre
        ", [$toma_id]);

        // Detalle específico para formularios (conteo, reconteo, etc.)
        if ($detalle_id_form && $action_form) {
            $data['detalle_form'] = $db->fetchOne("
                SELECT d.*, p.codigo, p.nombre AS producto_nombre, p.ubicacion
                FROM detalle_toma_inventario d
                JOIN productos_inventario p ON d.producto_id = p.id
                WHERE d.id = ? AND d.toma_id = ?
            ", [$detalle_id_form, $toma_id]);
        }

        break;

    case 'asignaciones':
        $data['tomas_asignadas'] = $db->fetchAll("
            SELECT ti.*, a.nombre AS almacen_nombre,
                   u_sup.nombre AS supervisor_nombre
            FROM tomas_inventario ti
            JOIN almacenes_inventario a ON ti.almacen_id = a.id
            JOIN usuarios u_sup ON ti.supervisor_id = u_sup.id
            WHERE ti.responsable_id = ?
              AND ti.cliente_id    = ?
              AND ti.estado IN ('planificada', 'en_proceso')
            ORDER BY ti.estado, ti.fecha_planificada DESC
        ", [$user_id, $cliente_id]);

        break;

    case 'nueva_toma':
        $data['almacenes'] = $db->fetchAll("
            SELECT *
            FROM almacenes_inventario
            WHERE cliente_id = ? AND estado = 'activo'
            ORDER BY nombre
        ", [$cliente_id]);

        $data['empleados'] = $db->fetchAll("
            SELECT *
            FROM usuarios
            WHERE cliente_id = ? AND rol IN ('operador', 'conductor') AND estado = 1
            ORDER BY nombre
        ", [$cliente_id]);

        // Productos activos para selección (se filtran por almacén vía JS)
        $data['productos'] = $db->fetchAll("
            SELECT p.*, a.nombre AS almacen_nombre
            FROM productos_inventario p
            LEFT JOIN almacenes_inventario a ON p.almacen_id = a.id
            WHERE p.cliente_id = ? AND p.estado = 'activo'
            ORDER BY a.nombre, p.nombre
        ", [$cliente_id]);

        break;

    case 'mis_conteos':
        $toma_id = intval($_GET['toma_id'] ?? 0);

        $data['detalles_pendientes'] = $db->fetchAll("
            SELECT d.*, p.codigo, p.nombre AS producto_nombre, p.ubicacion,
                   ti.codigo_toma, a.nombre AS almacen_nombre
            FROM detalle_toma_inventario d
            JOIN productos_inventario p ON d.producto_id = p.id
            JOIN tomas_inventario ti    ON d.toma_id = ti.id
            JOIN almacenes_inventario a ON ti.almacen_id = a.id
            WHERE ti.responsable_id = ?
              AND ti.id            = ?
              AND d.estado         = 'pendiente'
            ORDER BY p.nombre
        ", [$user_id, $toma_id]);

        break;
}

?>

<div class="container-fluid py-3">

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php
            echo htmlspecialchars($_SESSION['success_message']);
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php
            echo htmlspecialchars($_SESSION['error_message']);
            unset($_SESSION['error_message']);
            ?>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-0"><?= htmlspecialchars($page_title); ?></h1>
            <small class="text-muted">Control y seguimiento avanzado de inventarios físicos por almacén.</small>
        </div>

        <div class="d-flex gap-2">
            <?php if (in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>
                <a href="inventario-fisico.php?view=listado_tomas" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-list-ul"></i> Listado de Tomas
                </a>
                <a href="inventario-fisico.php?view=nueva_toma" class="btn btn-primary btn-sm">
                    <i class="bi bi-plus-circle"></i> Nueva Toma
                </a>
            <?php endif; ?>

            <?php if (in_array($rol, ['operador', 'conductor'])): ?>
                <a href="inventario-fisico.php?view=asignaciones" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-clipboard-check"></i> Mis Asignaciones
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($view === 'listado_tomas' && in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>

        <!-- LISTADO DE TOMAS CON FILTROS AVANZADOS -->
        <div class="card mb-3">
            <div class="card-header">
                <strong>Filtro de Tomas de Inventario</strong>
            </div>
            <div class="card-body">
                <form method="get" class="row g-2">
                    <input type="hidden" name="view" value="listado_tomas">

                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php
                            $estados = [
                                'planificada' => 'Planificada',
                                'en_proceso'  => 'En Proceso',
                                'ajustada'    => 'Ajustada'
                            ];
                            $estado_sel = $_GET['estado'] ?? '';
                            foreach ($estados as $k => $v): ?>
                                <option value="<?= htmlspecialchars($k); ?>" <?= $estado_sel === $k ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($v); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Almacén</label>
                        <select name="almacen_id" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php
                            $almacen_sel = intval($_GET['almacen_id'] ?? 0);
                            foreach ($data['almacenes'] as $alm): ?>
                                <option value="<?= (int)$alm['id']; ?>" <?= $almacen_sel === (int)$alm['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($alm['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Responsable</label>
                        <select name="responsable_id" class="form-select form-select-sm">
                            <option value="">Todos</option>
                            <?php
                            $resp_sel = intval($_GET['responsable_id'] ?? 0);
                            foreach ($data['empleados'] as $emp): ?>
                                <option value="<?= (int)$emp['id']; ?>" <?= $resp_sel === (int)$emp['id'] ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($emp['nombre']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm me-2">
                            <i class="bi bi-search"></i> Aplicar
                        </button>
                        <a href="inventario-fisico.php?view=listado_tomas" class="btn btn-outline-secondary btn-sm">
                            Limpiar
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <strong>Tomas de Inventario</strong>
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Almacén</th>
                            <th>Responsable</th>
                            <th>Supervisor</th>
                            <th>Fecha Planificada</th>
                            <th>Estado</th>
                            <th>Productos</th>
                            <th>Discrepancias</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($data['tomas'])): ?>
                        <?php foreach ($data['tomas'] as $toma): ?>
                            <tr>
                                <td><?= htmlspecialchars($toma['codigo_toma']); ?></td>
                                <td><?= htmlspecialchars($toma['nombre']); ?></td>
                                <td><?= htmlspecialchars($toma['almacen_nombre']); ?></td>
                                <td><?= htmlspecialchars($toma['responsable_nombre']); ?></td>
                                <td><?= htmlspecialchars($toma['supervisor_nombre']); ?></td>
                                <td><?= htmlspecialchars($toma['fecha_planificada']); ?></td>
                                <td>
                                    <?php
                                    $badge = 'secondary';
                                    if ($toma['estado'] === 'planificada') $badge = 'warning';
                                    if ($toma['estado'] === 'en_proceso')  $badge = 'info';
                                    if ($toma['estado'] === 'ajustada')    $badge = 'success';
                                    ?>
                                    <span class="badge bg-<?= $badge; ?>">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $toma['estado']))); ?>
                                    </span>
                                </td>
                                <td>
                                    <?= (int)$toma['productos_contados']; ?> /
                                    <?= (int)$toma['total_productos']; ?>
                                </td>
                                <td>
                                    <?php if ((int)$toma['productos_discrepancia'] > 0): ?>
                                        <span class="badge bg-danger">
                                            <?= (int)$toma['productos_discrepancia']; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">0</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?= (int)$toma['id']; ?>"
                                       class="btn btn-sm btn-outline-primary">
                                        Ver Detalle
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-muted">
                                No se encontraron tomas de inventario con los criterios seleccionados.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($view === 'nueva_toma' && in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>

        <!-- CREAR NUEVA TOMA -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <strong>Crear Nueva Toma de Inventario</strong>
                <a href="inventario-fisico.php?view=listado_tomas" class="btn btn-sm btn-outline-secondary">
                    Volver
                </a>
            </div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="crear_toma">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Nombre de la Toma *</label>
                            <input type="text" name="nombre_toma" class="form-control form-control-sm" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Almacén *</label>
                            <select name="almacen_id" id="almacen_id" class="form-select form-select-sm" required>
                                <option value="">Seleccionar almacén</option>
                                <?php foreach ($data['almacenes'] as $alm): ?>
                                    <option value="<?= (int)$alm['id']; ?>">
                                        <?= htmlspecialchars($alm['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">Responsable (operador/conductor) *</label>
                            <select name="responsable_id" class="form-select form-select-sm" required>
                                <option value="">Seleccionar empleado</option>
                                <?php foreach ($data['empleados'] as $emp): ?>
                                    <option value="<?= (int)$emp['id']; ?>">
                                        <?= htmlspecialchars($emp['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Fecha Planificada *</label>
                            <input type="date" name="fecha_planificada" class="form-control form-control-sm" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Hora Inicio</label>
                            <input type="time" name="hora_inicio_planificada" class="form-control form-control-sm">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Hora Fin</label>
                            <input type="time" name="hora_fin_planificada" class="form-control form-control-sm">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label">Búsqueda por Código / QR</label>
                            <input type="text" id="buscador_qr" class="form-control form-control-sm"
                                   placeholder="Escanee el código o escriba para filtrar">
                            <small class="text-muted">
                                Compatible con lector de código de barras / QR (input tipo texto).
                            </small>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Productos a Incluir *</label>
                            <div class="border rounded p-2" style="max-height: 300px; overflow-y: auto;">
                                <?php if (!empty($data['productos'])): ?>
                                    <table class="table table-sm mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 40px;"></th>
                                                <th>Código</th>
                                                <th>Producto</th>
                                                <th>Almacén</th>
                                                <th>Ubicación</th>
                                                <th>Stock Sistema</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabla-productos-inventario">
                                        <?php foreach ($data['productos'] as $prod): ?>
                                            <tr data-almacen-id="<?= (int)$prod['almacen_id']; ?>"
                                                data-codigo="<?= htmlspecialchars($prod['codigo'] ?? ''); ?>">
                                                <td>
                                                    <input type="checkbox" name="productos[]"
                                                           value="<?= (int)$prod['id']; ?>">
                                                </td>
                                                <td><?= htmlspecialchars($prod['codigo'] ?? ''); ?></td>
                                                <td><?= htmlspecialchars($prod['nombre'] ?? ''); ?></td>
                                                <td><?= htmlspecialchars($prod['almacen_nombre'] ?? ''); ?></td>
                                                <td><?= htmlspecialchars($prod['ubicacion'] ?? ''); ?></td>
                                                <td><?= htmlspecialchars($prod['stock_actual'] ?? 0); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                <?php else: ?>
                                    <p class="mb-0 text-muted">
                                        No hay productos activos configurados para este cliente.
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12 text-end">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="bi bi-save"></i> Crear Toma de Inventario
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <script>
            // Filtrar productos por almacén y por código/QR
            (function () {
                const selAlmacen = document.getElementById('almacen_id');
                const buscadorQR = document.getElementById('buscador_qr');
                const filas       = document.querySelectorAll('#tabla-productos-inventario tr');

                function aplicarFiltros() {
                    const almacenId = selAlmacen ? selAlmacen.value : '';
                    const textoQR   = (buscadorQR ? buscadorQR.value : '').toLowerCase();

                    filas.forEach(function (tr) {
                        const almRow  = tr.getAttribute('data-almacen-id') || '';
                        const codRow  = (tr.getAttribute('data-codigo') || '').toLowerCase();

                        let visible = true;

                        if (almacenId && almRow !== almacenId) {
                            visible = false;
                        }

                        if (textoQR && codRow.indexOf(textoQR) === -1) {
                            visible = false;
                        }

                        tr.style.display = visible ? '' : 'none';
                    });
                }

                if (selAlmacen) {
                    selAlmacen.addEventListener('change', aplicarFiltros);
                }
                if (buscadorQR) {
                    buscadorQR.addEventListener('keyup', aplicarFiltros);
                }
            })();
        </script>

    <?php elseif ($view === 'detalle_toma' && !empty($data['toma'])): ?>

        <?php $toma = $data['toma']; ?>

        <!-- DETALLE DE TOMA -->
        <div class="d-flex justify-content-between align-items-center mb-3">
            <a href="inventario-fisico.php?view=listado_tomas" class="btn btn-sm btn-outline-secondary">
                Volver
            </a>

            <div class="d-flex gap-2">
                <?php if ($toma['estado'] === 'planificada' && in_array($rol, ['supervisor', 'admin', 'super_admin'])): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="action" value="iniciar_toma">
                        <input type="hidden" name="toma_id" value="<?= (int)$toma['id']; ?>">
                        <button type="submit" class="btn btn-sm btn-warning">
                            Iniciar Toma
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (in_array($toma['estado'], ['planificada', 'en_proceso']) && $toma['responsable_id'] == $user_id): ?>
                    <a href="inventario-fisico.php?view=mis_conteos&toma_id=<?= (int)$toma['id']; ?>"
                       class="btn btn-sm btn-primary">
                        Ir a Contar Productos
                    </a>
                <?php endif; ?>

                <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="exportar_excel">
                    <input type="hidden" name="toma_id" value="<?= (int)$toma['id']; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-success">
                        Exportar a Excel
                    </button>
                </form>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">
                        Información de la Toma
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Código:</strong> <?= htmlspecialchars($toma['codigo_toma']); ?></p>
                        <p class="mb-1"><strong>Nombre:</strong> <?= htmlspecialchars($toma['nombre']); ?></p>
                        <p class="mb-1"><strong>Almacén:</strong> <?= htmlspecialchars($toma['almacen_nombre']); ?></p>
                        <p class="mb-1"><strong>Responsable:</strong> <?= htmlspecialchars($toma['responsable_nombre']); ?></p>
                        <p class="mb-1"><strong>Supervisor:</strong> <?= htmlspecialchars($toma['supervisor_nombre']); ?></p>
                        <p class="mb-1"><strong>Estado:</strong>
                            <span class="badge bg-secondary">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $toma['estado']))); ?>
                            </span>
                        </p>
                        <p class="mb-1"><strong>Fecha Planificada:</strong> <?= htmlspecialchars($toma['fecha_planificada']); ?></p>
                        <?php if (!empty($toma['fecha_inicio_real'])): ?>
                            <p class="mb-1"><strong>Inicio Real:</strong> <?= htmlspecialchars($toma['fecha_inicio_real']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($toma['fecha_fin_real'])): ?>
                            <p class="mb-1"><strong>Fin Real:</strong> <?= htmlspecialchars($toma['fecha_fin_real']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">
                        Resumen de Conteos
                    </div>
                    <div class="card-body">
                        <p class="mb-1"><strong>Total Productos:</strong> <?= (int)$toma['total_productos']; ?></p>
                        <p class="mb-1"><strong>Contados:</strong> <?= (int)$toma['productos_contados']; ?></p>
                        <p class="mb-1"><strong>Pendientes:</strong> <?= (int)$toma['productos_pendientes']; ?></p>
                        <p class="mb-1"><strong>Discrepancias:</strong>
                            <?php if ((int)$toma['productos_discrepancia'] > 0): ?>
                                <span class="badge bg-danger">
                                    <?= (int)$toma['productos_discrepancia']; ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success">0</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-header">
                        Ajuste de Inventario
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">
                            Al hacer clic en <strong>"Ajustar Inventario"</strong>, se actualizarán los stocks
                            con las cantidades contadas y se registrará el movimiento en el Kardex.
                        </p>
                        <?php if (in_array($rol, ['supervisor', 'admin', 'super_admin']) && $toma['estado'] !== 'ajustada'): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="ajustar_inventario">
                                <input type="hidden" name="toma_id" value="<?= (int)$toma['id']; ?>">

                                <div class="border rounded p-2 mb-2" style="max-height: 200px; overflow-y: auto;">
                                    <?php
                                    $discrepancias = array_filter($data['detalles'], function ($d) {
                                        return $d['estado'] === 'discrepancia';
                                    });
                                    ?>
                                    <?php if (!empty($discrepancias)): ?>
                                        <p class="small mb-1"><strong>Seleccionar discrepancias para ajustar:</strong></p>
                                        <?php foreach ($discrepancias as $d): ?>
                                            <div class="form-check small">
                                                <input class="form-check-input" type="checkbox"
                                                       name="ajustar[]" value="<?= (int)$d['id']; ?>" id="aj_<?= (int)$d['id']; ?>">
                                                <label class="form-check-label" for="aj_<?= (int)$d['id']; ?>">
                                                    <?= htmlspecialchars($d['producto_nombre']); ?>
                                                    (Sistema: <?= htmlspecialchars($d['cantidad_sistema']); ?>,
                                                    Contado: <?= htmlspecialchars($d['cantidad_contada']); ?>,
                                                    Dif: <?= htmlspecialchars($d['diferencia']); ?>)
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="small mb-0 text-muted">
                                            No hay discrepancias pendientes de ajuste.
                                        </p>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($discrepancias)): ?>
                                    <button type="submit" class="btn btn-sm btn-danger">
                                        Ajustar Inventario
                                    </button>
                                <?php endif; ?>
                            </form>
                        <?php else: ?>
                            <p class="small mb-0 text-muted">
                                La toma ya fue ajustada o no tienes permisos para ajustar.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- TABLA DE DETALLE DE PRODUCTOS -->
        <div class="card">
            <div class="card-header">
                Detalle de Productos
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Ubicación</th>
                            <th>Cant. Sistema</th>
                            <th>Cant. Contada</th>
                            <th>Diferencia</th>
                            <th>Estado</th>
                            <th>Observaciones</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($data['detalles'])): ?>
                        <?php foreach ($data['detalles'] as $d): ?>
                            <tr>
                                <td><?= htmlspecialchars($d['codigo'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($d['producto_nombre'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($d['ubicacion'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($d['cantidad_sistema'] ?? 0); ?></td>
                                <td><?= htmlspecialchars($d['cantidad_contada'] ?? 0); ?></td>
                                <td><?= htmlspecialchars($d['diferencia'] ?? 0); ?></td>
                                <td>
                                    <?php
                                    $badge = 'secondary';
                                    if ($d['estado'] === 'pendiente')      $badge = 'warning';
                                    if ($d['estado'] === 'discrepancia')   $badge = 'danger';
                                    if ($d['estado'] === 'verificado')     $badge = 'success';
                                    if ($d['estado'] === 'no_encontrado')  $badge = 'dark';
                                    ?>
                                    <span class="badge bg-<?= $badge; ?>">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $d['estado']))); ?>
                                    </span>
                                </td>
                                <td class="small">
                                    <?= htmlspecialchars($d['observaciones'] ?? ''); ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php if (in_array($toma['estado'], ['planificada', 'en_proceso'])): ?>
                                            <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?= (int)$toma['id']; ?>&action_form=conteo&detalle_id=<?= (int)$d['id']; ?>"
                                               class="btn btn-outline-primary">
                                                Contar
                                            </a>
                                            <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?= (int)$toma['id']; ?>&action_form=no_encontrado&detalle_id=<?= (int)$d['id']; ?>"
                                               class="btn btn-outline-dark">
                                                No Encontrado
                                            </a>
                                            <?php if ($d['estado'] === 'discrepancia'): ?>
                                                <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?= (int)$toma['id']; ?>&action_form=reconteo&detalle_id=<?= (int)$d['id']; ?>"
                                                   class="btn btn-outline-warning">
                                                    Solicitar Reconteo
                                                </a>
                                                <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?= (int)$toma['id']; ?>&action_form=aprobar&detalle_id=<?= (int)$d['id']; ?>"
                                                   class="btn btn-outline-success">
                                                    Aprobar
                                                </a>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small">Toma cerrada</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted">
                                No hay productos en esta toma de inventario.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- FORMULARIOS MODALES "SIMPLIFICADOS" EN LA MISMA PANTALLA -->
        <?php if (!empty($action_form) && !empty($data['detalle_form'])): ?>
            <?php $df = $data['detalle_form']; ?>
            <div class="card mt-3">
                <div class="card-header">
                    <?php if ($action_form === 'conteo'): ?>
                        Registrar Conteo
                    <?php elseif ($action_form === 'no_encontrado'): ?>
                        Marcar Producto como No Encontrado
                    <?php elseif ($action_form === 'reconteo'): ?>
                        Solicitar Reconteo
                    <?php elseif ($action_form === 'aprobar'): ?>
                        Aprobar Conteo
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($action_form === 'conteo'): ?>

                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="registrar_conteo">
                            <input type="hidden" name="detalle_id" value="<?= (int)$df['id']; ?>">

                            <div class="col-12">
                                <p class="mb-1"><strong>Producto:</strong> <?= htmlspecialchars($df['producto_nombre']); ?></p>
                                <p class="mb-1"><strong>Ubicación:</strong> <?= htmlspecialchars($df['ubicacion']); ?></p>
                                <p class="mb-1"><strong>Cantidad en sistema:</strong> <?= htmlspecialchars($df['cantidad_sistema']); ?></p>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Cantidad Contada *</label>
                                <input type="number" step="0.01" min="0" name="cantidad_contada"
                                       class="form-control form-control-sm" required>
                            </div>

                            <div class="col-md-8">
                                <label class="form-label">Observaciones (opcional)</label>
                                <input type="text" name="observaciones" class="form-control form-control-sm">
                            </div>

                            <div class="col-12 text-end">
                                <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?= (int)$toma['id']; ?>"
                                   class="btn btn-outline-secondary btn-sm">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-primary btn-sm">
                                    Guardar Conteo
                                </button>
                            </div>
                        </form>

                    <?php elseif ($action_form === 'no_encontrado'): ?>

                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="marcar_no_encontrado">
                            <input type="hidden" name="detalle_id" value="<?= (int)$df['id']; ?>">
                            <input type="hidden" name="toma_id" value="<?= (int)$toma['id']; ?>">

                            <div class="col-12">
                                <p class="mb-1"><strong>Producto:</strong> <?= htmlspecialchars($df['producto_nombre']); ?></p>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Motivo / Observaciones *</label>
                                <textarea name="motivo_no_encontrado" class="form-control form-control-sm" rows="2" required></textarea>
                            </div>

                            <div class="col-12 text-end">
                                <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?= (int)$toma['id']; ?>"
                                   class="btn btn-outline-secondary btn-sm">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-dark btn-sm">
                                    Marcar como No Encontrado
                                </button>
                            </div>
                        </form>

                    <?php elseif ($action_form === 'reconteo'): ?>

                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="solicitar_reconteo">
                            <input type="hidden" name="detalle_id" value="<?= (int)$df['id']; ?>">

                            <div class="col-12">
                                <p class="mb-1"><strong>Producto:</strong> <?= htmlspecialchars($df['producto_nombre']); ?></p>
                                <p class="mb-1"><strong>Cantidad en sistema:</strong> <?= htmlspecialchars($df['cantidad_sistema']); ?></p>
                                <p class="mb-1"><strong>Cantidad contada:</strong> <?= htmlspecialchars($df['cantidad_contada']); ?></p>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Motivo del Reconteo *</label>
                                <textarea name="motivo_reconteo" class="form-control form-control-sm" rows="2" required></textarea>
                            </div>

                            <div class="col-12 text-end">
                                <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?= (int)$toma['id']; ?>"
                                   class="btn btn-outline-secondary btn-sm">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-warning btn-sm">
                                    Solicitar Reconteo
                                </button>
                            </div>
                        </form>

                    <?php elseif ($action_form === 'aprobar'): ?>

                        <form method="post" class="row g-2">
                            <input type="hidden" name="action" value="aprobar_conteo">
                            <input type="hidden" name="detalle_id" value="<?= (int)$df['id']; ?>">
                            <input type="hidden" name="toma_id" value="<?= (int)$toma['id']; ?>">

                            <div class="col-12">
                                <p class="mb-1"><strong>Producto:</strong> <?= htmlspecialchars($df['producto_nombre']); ?></p>
                                <p class="mb-1"><strong>Cantidad en sistema:</strong> <?= htmlspecialchars($df['cantidad_sistema']); ?></p>
                                <p class="mb-1"><strong>Cantidad contada:</strong> <?= htmlspecialchars($df['cantidad_contada']); ?></p>
                                <p class="mb-1"><strong>Diferencia:</strong> <?= htmlspecialchars($df['diferencia']); ?></p>
                            </div>

                            <div class="col-12 text-end">
                                <a href="inventario-fisico.php?view=detalle_toma&toma_id=<?= (int)$toma['id']; ?>"
                                   class="btn btn-outline-secondary btn-sm">
                                    Cancelar
                                </a>
                                <button type="submit" class="btn btn-success btn-sm">
                                    Aprobar Conteo
                                </button>
                            </div>
                        </form>

                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

    <?php elseif ($view === 'asignaciones' && in_array($rol, ['operador', 'conductor'])): ?>

        <!-- ASIGNACIONES PARA OPERADORES / CONDUCTORES -->
        <div class="card">
            <div class="card-header">
                Tomas de Inventario Asignadas
            </div>
            <div class="card-body table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Almacén</th>
                            <th>Supervisor</th>
                            <th>Fecha Planificada</th>
                            <th>Estado</th>
                            <th>Productos Pendientes</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($data['tomas_asignadas'])): ?>
                        <?php foreach ($data['tomas_asignadas'] as $t): ?>
                            <tr>
                                <td><?= htmlspecialchars($t['codigo_toma']); ?></td>
                                <td><?= htmlspecialchars($t['nombre']); ?></td>
                                <td><?= htmlspecialchars($t['almacen_nombre']); ?></td>
                                <td><?= htmlspecialchars($t['supervisor_nombre']); ?></td>
                                <td><?= htmlspecialchars($t['fecha_planificada']); ?></td>
                                <td>
                                    <?php
                                    $badge = 'secondary';
                                    if ($t['estado'] === 'planificada') $badge = 'warning';
                                    if ($t['estado'] === 'en_proceso')  $badge = 'info';
                                    if ($t['estado'] === 'ajustada')    $badge = 'success';
                                    ?>
                                    <span class="badge bg-<?= $badge; ?>">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $t['estado']))); ?>
                                    </span>
                                </td>
                                <td><?= (int)$t['productos_pendientes']; ?></td>
                                <td>
                                    <a href="inventario-fisico.php?view=mis_conteos&toma_id=<?= (int)$t['id']; ?>"
                                       class="btn btn-sm btn-primary">
                                        Ir a Contar
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted">
                                No tienes tomas de inventario asignadas actualmente.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($view === 'mis_conteos' && in_array($rol, ['operador', 'conductor'])): ?>

        <!-- VISTA SIMPLIFICADA DE CONTEO PARA OPERADOR / CONDUCTOR -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Productos Pendientes por Contar</span>
                <a href="inventario-fisico.php?view=asignaciones" class="btn btn-sm btn-outline-secondary">
                    Volver a Asignaciones
                </a>
            </div>
            <div class="card-body">
                <?php if (!empty($data['detalles_pendientes'])): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th>Ubicación</th>
                                    <th>Cant. Sistema</th>
                                    <th>Conteo</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($data['detalles_pendientes'] as $d): ?>
                                <tr>
                                    <td><?= htmlspecialchars($d['codigo'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($d['producto_nombre'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($d['ubicacion'] ?? ''); ?></td>
                                    <td><?= htmlspecialchars($d['cantidad_sistema'] ?? 0); ?></td>
                                    <td style="min-width: 220px;">
                                        <form method="post" class="row g-1 align-items-center">
                                            <input type="hidden" name="action" value="registrar_conteo">
                                            <input type="hidden" name="detalle_id" value="<?= (int)$d['id']; ?>">

                                            <div class="col-6">
                                                <input type="number" step="0.01" min="0"
                                                       name="cantidad_contada"
                                                       class="form-control form-control-sm"
                                                       required>
                                            </div>
                                            <div class="col-6">
                                                <button type="submit" class="btn btn-primary btn-sm w-100">
                                                    Guardar
                                                </button>
                                            </div>
                                            <div class="col-12 mt-1">
                                                <input type="text" name="observaciones"
                                                       class="form-control form-control-sm"
                                                       placeholder="Obs. (opcional)">
                                            </div>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="mb-0 text-muted">
                        No tienes productos pendientes por contar en esta toma.
                    </p>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>

        <div class="alert alert-info">
            No se pudo determinar la vista de inventario. Selecciona una opción del menú.
        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
