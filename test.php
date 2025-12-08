<?php
// test.php - ARCHIVO MÍNIMO DE PRUEBA
echo "=== INICIANDO PRUEBA ===<br>";

// Paso 1: Verificar PHP básico
echo "Paso 1: PHP funciona ✅<br>";

// Paso 2: Verificar sesión
session_start();
echo "Paso 2: Sesión iniciada ✅<br>";

// Paso 3: Verificar archivo config.php
if (file_exists(__DIR__ . '/config.php')) {
    echo "Paso 3: config.php existe ✅<br>";
    
    // Intentar cargar config.php
    try {
        require_once __DIR__ . '/config.php';
        echo "Paso 4: config.php cargado ✅<br>";
    } catch (Exception $e) {
        echo "Paso 4: ERROR cargando config.php: " . $e->getMessage() . " ❌<br>";
    }
} else {
    echo "Paso 3: config.php NO existe ❌<br>";
}

// Paso 4: Verificar Database.php
if (file_exists(__DIR__ . '/includes/Database.php')) {
    echo "Paso 5: Database.php existe ✅<br>";
    
    try {
        require_once __DIR__ . '/includes/Database.php';
        echo "Paso 6: Database.php cargado ✅<br>";
        
        // Probar conexión
        $db = new Database();
        echo "Paso 7: Conexión BD exitosa ✅<br>";
        
    } catch (Exception $e) {
        echo "Paso 6-7: ERROR Database: " . $e->getMessage() . " ❌<br>";
    }
} else {
    echo "Paso 5: Database.php NO existe ❌<br>";
}

echo "=== PRUEBA COMPLETADA ===<br>";
?>