<?php
// Habilitar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Diagnóstico Detallado del Sistema</h1>";

// 1. Verificar sesiones
echo "<h2>1. Verificación de Sesiones</h2>";
try {
    session_start();
    echo "✅ Sesiones funcionando correctamente<br>";
} catch (Exception $e) {
    echo "❌ Error en sesiones: " . $e->getMessage() . "<br>";
}

// 2. Verificar inclusión de archivos
echo "<h2>2. Verificación de Archivos de Configuración</h2>";
$config_files = ['config/config.php', 'classes/Database.php'];
foreach ($config_files as $file) {
    if (file_exists($file)) {
        echo "✅ $file existe<br>";
    } else {
        echo "❌ $file NO existe<br>";
    }
}

// 3. Verificar configuración de base de datos
echo "<h2>3. Verificación de Configuración BD</h2>";
try {
    require_once 'config/config.php';
    echo "✅ Config.php cargado correctamente<br>";
    
    // Verificar constantes
    $constants = ['DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS'];
    foreach ($constants as $constant) {
        if (defined($constant)) {
            echo "✅ $constant = " . constant($constant) . "<br>";
        } else {
            echo "❌ $constant NO definida<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error cargando config.php: " . $e->getMessage() . "<br>";
}

// 4. Verificar conexión a BD
echo "<h2>4. Verificación de Conexión a Base de Datos</h2>";
try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "✅ Conexión PDO establecida<br>";
    
    // Verificar tablas
    $tables = $db->fetchAll("SHOW TABLES");
    echo "✅ Tablas en la base de datos: " . count($tables) . "<br>";
    
} catch (PDOException $e) {
    echo "❌ Error PDO: " . $e->getMessage() . "<br>";
    echo "Código de error: " . $e->getCode() . "<br>";
}

// 5. Verificar datos de usuario demo
echo "<h2>5. Verificación de Usuarios Demo</h2>";
try {
    $db = new Database();
    $user = $db->fetchOne("SELECT * FROM usuarios WHERE email = 'admin@demo.com'");
    if ($user) {
        echo "✅ Usuario admin@demo.com encontrado<br>";
        echo "📧 Email: " . $user['email'] . "<br>";
        echo "👤 Nombre: " . $user['nombre'] . " " . $user['apellido'] . "<br>";
        echo "🔑 Estado: " . ($user['estado'] ? 'Activo' : 'Inactivo') . "<br>";
    } else {
        echo "❌ Usuario admin@demo.com NO encontrado<br>";
    }
} catch (Exception $e) {
    echo "❌ Error verificando usuario: " . $e->getMessage() . "<br>";
}

echo "<h2>✅ Diagnóstico completado</h2>";
?>