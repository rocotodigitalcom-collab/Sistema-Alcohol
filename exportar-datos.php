<?php
// exportar-datos.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

if (!isset($_SESSION['cliente_id'])) {
    die('Acceso no autorizado');
}

$db = new Database();
$cliente_id = $_SESSION['cliente_id'];

/* ============================
   PARÁMETROS
============================ */

$formato = $_GET['formato'] ?? 'csv';
$tipo    = $_GET['tipo'] ?? 'pruebas';

/* ============================
   FILTROS
============================ */

$fecha_inicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin    = $_GET['fecha_fin'] ?? date('Y-m-d');

$where = ["p.cliente_id = ?"];
$params = [$cliente_id];

$where[] = "p.fecha_prueba BETWEEN ? AND ?";
$params[] = $fecha_inicio . " 00:00:00";
$params[] = $fecha_fin . " 23:59:59";

if (!empty($_GET['conductor_id'])) {
    $where[] = "p.conductor_id = ?";
    $params[] = $_GET['conductor_id'];
}

if (!empty($_GET['alcoholimetro_id'])) {
    $where[] = "p.alcoholimetro_id = ?";
    $params[] = $_GET['alcoholimetro_id'];
}

if (!empty($_GET['resultado'])) {
    $where[] = "p.resultado = ?";
    $params[] = $_GET['resultado'];
}

$where_sql = implode(' AND ', $where);

/* ============================
   CONSULTA PRINCIPAL
============================ */

$sql = "
    SELECT
        DATE(p.fecha_prueba) AS fecha,
        TIME(p.fecha_prueba) AS hora,
        CONCAT(u.nombre,' ',u.apellido) AS conductor,
        u.dni,
        a.nombre AS alcoholimetro,
        a.numero_serie,
        p.nivel_alcohol,
        p.resultado,
        p.tipo_prueba,
        p.observaciones
    FROM pruebas p
    LEFT JOIN usuarios u ON u.id = p.conductor_id
    LEFT JOIN alcoholimetros a ON a.id = p.alcoholimetro_id
    WHERE $where_sql
    ORDER BY p.fecha_prueba DESC
";

$datos = $db->fetchAll($sql, $params);

/* ============================
   EXPORTAR CSV
============================ */

if ($formato === 'csv') {

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_pruebas_' . date('Ymd') . '.csv"');

    $output = fopen('php://output', 'w');

    // Encabezados
    fputcsv($output, [
        'Fecha',
        'Hora',
        'Conductor',
        'DNI',
        'Alcoholímetro',
        'Serie',
        'Nivel Alcohol (g/L)',
        'Resultado',
        'Tipo Prueba',
        'Observaciones'
    ], ';');

    foreach ($datos as $row) {
        fputcsv($output, [
            $row['fecha'],
            $row['hora'],
            $row['conductor'],
            $row['dni'],
            $row['alcoholimetro'],
            $row['numero_serie'],
            number_format($row['nivel_alcohol'], 3),
            ucfirst($row['resultado']),
            ucfirst($row['tipo_prueba']),
            $row['observaciones']
        ], ';');
    }

    fclose($output);
    exit;
}

/* ============================
   EXPORTAR JSON
============================ */

if ($formato === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'fecha_exportacion' => date('Y-m-d H:i:s'),
        'total_registros'   => count($datos),
        'datos'             => $datos
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/* ============================
   FORMATO NO SOPORTADO
============================ */

http_response_code(400);
echo 'Formato de exportación no soportado';
exit;
