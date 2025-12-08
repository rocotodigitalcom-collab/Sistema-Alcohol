<?php
// ajax/obtener-metricas.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = new Database();
$protocolo_id = $_GET['protocolo_id'] ?? 0;

$metricas = $db->fetchOne("
    SELECT 
        COUNT(*) as total_pruebas,
        SUM(CASE WHEN pr.resultado = 'reprobado' THEN 1 ELSE 0 END) as positivos,
        SUM(CASE WHEN pr.resultado = 'aprobado' THEN 1 ELSE 0 END) as negativos,
        AVG(pr.nivel_alcohol) as promedio_alcohol
    FROM pruebas_protocolo pp
    LEFT JOIN pruebas pr ON pp.prueba_alcohol_id = pr.id
    WHERE pp.operacion_id = ?
", [$protocolo_id]);

header('Content-Type: application/json');
echo json_encode($metricas);
?>