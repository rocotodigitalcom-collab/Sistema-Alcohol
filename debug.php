<?php
// Habilitar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "=== DEBUG MODE ===<br>";
echo "PHP Version: " . phpversion() . "<br>";
echo "Server: " . $_SERVER['SERVER_SOFTWARE'] . "<br><br>";

// Verificar extensiones necesarias
$extensions = ['pdo', 'pdo_mysql', 'session', 'json'];
foreach ($extensions as $ext) {
    echo "Extension $ext: " . (extension_loaded($ext) ? '✅' : '❌') . "<br>";
}

echo "<br>=== CONFIG CHECK ===<br>";

// Verificar si podemos escribir en el directorio
echo "Uploads directory writable: " . (is_writable('assets/uploads') ? '✅' : '❌') . "<br>";

// Verificar sesiones
echo "Sessions working: " . (session_start() ? '✅' : '❌') . "<br>";

// Probar base de datos básica
try {
    $test_pdo = new PDO("mysql:host=localhost", "juegosd2_alcohol", "#Peru07128020@");
    echo "Database connection: ✅<br>";
} catch (Exception $e) {
    echo "Database connection: ❌ - " . $e->getMessage() . "<br>";
}

echo "<br>=== FINISHED ===<br>";
?>