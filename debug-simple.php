<?php
// debug-simple.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();
$cliente_id = $_SESSION['cliente_id'] ?? 0;

echo "<h1>DEBUG ULTRA SIMPLE</h1>";
echo "<pre>";

echo "=== SESIÓN ===\n";
echo "cliente_id: {$cliente_id}\n\n";

echo "=== QUERY SIN FILTRO ===\n";
$sql1 = "SELECT * FROM inventario_tomas";
$result1 = $db->fetchAll($sql1);
echo "Registros encontrados: " . count($result1) . "\n";
print_r($result1);

echo "\n=== QUERY CON FILTRO cliente_id={$cliente_id} ===\n";
$sql2 = "SELECT * FROM inventario_tomas WHERE cliente_id = ?";
$result2 = $db->fetchAll($sql2, [$cliente_id]);
echo "Registros encontrados: " . count($result2) . "\n";
print_r($result2);

echo "\n=== QUERY CON LEFT JOIN ===\n";
$sql3 = "SELECT t.*, u.nombre_completo 
         FROM inventario_tomas t
         LEFT JOIN usuarios u ON t.responsable_id = u.id
         WHERE t.cliente_id = ?";
$result3 = $db->fetchAll($sql3, [$cliente_id]);
echo "Registros encontrados: " . count($result3) . "\n";
print_r($result3);

echo "</pre>";
?>