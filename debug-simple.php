<?php
// debug-simple.php - SIN INCLUDES, SOLO PHP PURO
header('Content-Type: text/plain; charset=utf-8');

echo "🔧 DIAGNÓSTICO INICIADO\n";
echo "=======================\n";

// Verificar archivos
$archivos = [
    'config.php' => __DIR__ . '/config.php',
    'Database.php' => __DIR__ . '/includes/Database.php', 
    'header.php' => __DIR__ . '/includes/header.php',
    'footer.php' => __DIR__ . '/includes/footer.php'
];

foreach ($archivos as $nombre => $ruta) {
    if (file_exists($ruta)) {
        echo "✅ $nombre - EXISTE\n";
        
        // Verificar si el archivo es legible
        if (is_readable($ruta)) {
            echo "   📖 Legible\n";
        } else {
            echo "   ❌ NO legible (problema de permisos)\n";
        }
        
        // Verificar tamaño
        echo "   📊 Tamaño: " . filesize($ruta) . " bytes\n";
        
    } else {
        echo "❌ $nombre - NO EXISTE\n";
    }
    echo "---\n";
}

// Verificar sesión
echo "🔐 INFORMACIÓN DE SESIÓN:\n";
echo "Estado sesión: " . session_status() . "\n";
if (isset($_SESSION)) {
    foreach ($_SESSION as $key => $value) {
        echo "SESSION['$key'] = $value\n";
    }
} else {
    echo "No hay sesión activa\n";
}

echo "=======================\n";
echo "🎯 VERIFICACIÓN DE ERRORES PHP:\n";

// Forzar algunos errores para ver si se muestran
$undefined_variable = $variable_inexistente; // Esto debería generar warning

echo "✅ Si ves este mensaje, PHP está funcionando básicamente\n";

// Probar sintaxis compleja
try {
    $test_array = ['a' => 1, 'b' => 2];
    echo "✅ Arrays funcionan\n";
} catch (Exception $e) {
    echo "❌ Error en arrays: " . $e->getMessage() . "\n";
}
?>