<?php
// Verificar conexión a la base de datos
echo "<h2>Verificando Conexión a Base de Datos</h2>";

$host = 'localhost';
$dbname = 'juegosd2_alcohol';
$username = 'juegosd2_alcohol'; // Cambia por tu usuario
$password = '#Peru07128020@'; // Cambia por tu password

// Intentar conexión MySQLi
$mysqli = new mysqli($host, $username, $password, $dbname);

if ($mysqli->connect_error) {
    echo "<div style='color: red;'>❌ Error MySQLi: " . $mysqli->connect_error . "</div>";
} else {
    echo "<div style='color: green;'>✅ Conexión MySQLi exitosa</div>";
    
    // Verificar tablas
    $result = $mysqli->query("SHOW TABLES");
    echo "<h3>Tablas en la base de datos:</h3>";
    while ($row = $result->fetch_array()) {
        echo "- " . $row[0] . "<br>";
    }
    
    $mysqli->close();
}

// Verificar si existe el usuario admin
$mysqli = new mysqli($host, $username, $password, $dbname);
$result = $mysqli->query("SELECT * FROM usuarios WHERE email = 'admin@demo.com'");
if ($result->num_rows > 0) {
    echo "<div style='color: green;'>✅ Usuario admin encontrado</div>";
} else {
    echo "<div style='color: orange;'>⚠️ Usuario admin no encontrado</div>";
}
$mysqli->close();
?>