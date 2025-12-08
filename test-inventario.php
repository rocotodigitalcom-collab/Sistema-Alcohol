<?php
// test-inventario.php - DIAGN脫STICO
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagn贸stico Inventario</title>
    <style>
        body {
            font-family: monospace;
            padding: 20px;
            background: #f0f0f0;
        }
        .box {
            background: white;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        h2 {
            color: #333;
            border-bottom: 3px solid #007bff;
            padding-bottom: 10px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        table th, table td {
            padding: 12px;
            text-align: left;
            border: 1px solid #ddd;
        }
        table th {
            background: #007bff;
            color: white;
        }
        table tr:nth-child(even) {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <h1>馃攳 Diagn贸stico de Inventario F铆sico</h1>

    <div class="box">
        <h2>1. Informaci贸n de Sesi贸n</h2>
        <?php
        $user_id = $_SESSION['user_id'] ?? 'NO DEFINIDO';
        $cliente_id = $_SESSION['cliente_id'] ?? 'NO DEFINIDO';
        $rol = $_SESSION['rol'] ?? 'NO DEFINIDO';
        ?>
        <pre><?php
        echo "user_id:    {$user_id}\n";
        echo "cliente_id: {$cliente_id}\n";
        echo "rol:        {$rol}\n";
        ?></pre>
        
        <?php if ($user_id === 'NO DEFINIDO'): ?>
            <div class="error">
                鈿狅笍 <strong>ERROR:</strong> No hay sesi贸n iniciada. Debes iniciar sesi贸n primero.
            </div>
        <?php else: ?>
            <div class="success">
                鉁?Sesi贸n activa detectada
            </div>
        <?php endif; ?>
    </div>

    <div class="box">
        <h2>2. Verificar Tablas en Base de Datos</h2>
        <?php
        try {
            // Verificar tabla inventario_tomas
            $result1 = $db->fetchAll("SHOW TABLES LIKE 'inventario_tomas'");
            $result2 = $db->fetchAll("SHOW TABLES LIKE 'inventario_detalles'");
            
            if (count($result1) > 0) {
                echo '<div class="success">鉁?Tabla "inventario_tomas" existe</div>';
            } else {
                echo '<div class="error">鉂?Tabla "inventario_tomas" NO existe</div>';
            }
            
            if (count($result2) > 0) {
                echo '<div class="success">鉁?Tabla "inventario_detalles" existe</div>';
            } else {
                echo '<div class="error">鉂?Tabla "inventario_detalles" NO existe</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">鉂?Error: ' . $e->getMessage() . '</div>';
        }
        ?>
    </div>

    <div class="box">
        <h2>3. Contar Registros en inventario_tomas</h2>
        <?php
        try {
            $count = $db->fetchOne("SELECT COUNT(*) as total FROM inventario_tomas");
            echo '<div class="info">';
            echo '<strong>Total de tomas en la BD:</strong> ' . $count['total'];
            echo '</div>';
            
            if ($count['total'] > 0) {
                echo '<div class="success">鉁?Hay tomas de inventario registradas</div>';
            } else {
                echo '<div class="error">鈿狅笍 No hay tomas registradas todav铆a</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">鉂?Error: ' . $e->getMessage() . '</div>';
        }
        ?>
    </div>

    <div class="box">
        <h2>4. TODAS las Tomas de Inventario (sin filtro)</h2>
        <?php
        try {
            $todas = $db->fetchAll("
                SELECT t.*, u.nombre_completo AS responsable_nombre
                FROM inventario_tomas t
                LEFT JOIN usuarios u ON t.responsable_id = u.id
                ORDER BY t.id DESC
            ");
            
            if (count($todas) > 0) {
                echo '<table>';
                echo '<tr>
                    <th>ID</th>
                    <th>Cliente ID</th>
                    <th>Nombre</th>
                    <th>Ubicaci贸n</th>
                    <th>Responsable</th>
                    <th>Estado</th>
                    <th>Fecha Creaci贸n</th>
                </tr>';
                
                foreach ($todas as $t) {
                    echo '<tr>';
                    echo '<td>' . $t['id'] . '</td>';
                    echo '<td>' . $t['cliente_id'] . '</td>';
                    echo '<td>' . htmlspecialchars($t['nombre_toma']) . '</td>';
                    echo '<td>' . htmlspecialchars($t['ubicacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($t['responsable_nombre'] ?? 'N/A') . '</td>';
                    echo '<td>' . $t['estado'] . '</td>';
                    echo '<td>' . $t['fecha_creacion'] . '</td>';
                    echo '</tr>';
                }
                
                echo '</table>';
            } else {
                echo '<div class="error">鉂?No hay tomas en la base de datos</div>';
            }
        } catch (Exception $e) {
            echo '<div class="error">鉂?Error: ' . $e->getMessage() . '</div>';
        }
        ?>
    </div>

    <div class="box">
        <h2>5. Tomas Filtradas por TU Cliente ID (<?= $cliente_id ?>)</h2>
        <?php
        if ($cliente_id !== 'NO DEFINIDO') {
            try {
                $filtradas = $db->fetchAll("
                    SELECT t.*, u.nombre_completo AS responsable_nombre
                    FROM inventario_tomas t
                    LEFT JOIN usuarios u ON t.responsable_id = u.id
                    WHERE t.cliente_id = ?
                    ORDER BY t.id DESC
                ", [$cliente_id]);
                
                if (count($filtradas) > 0) {
                    echo '<div class="success">鉁?Encontradas ' . count($filtradas) . ' tomas para tu cliente</div>';
                    echo '<table>';
                    echo '<tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Ubicaci贸n</th>
                        <th>Responsable</th>
                        <th>Estado</th>
                    </tr>';
                    
                    foreach ($filtradas as $t) {
                        echo '<tr>';
                        echo '<td>' . $t['id'] . '</td>';
                        echo '<td>' . htmlspecialchars($t['nombre_toma']) . '</td>';
                        echo '<td>' . htmlspecialchars($t['ubicacion']) . '</td>';
                        echo '<td>' . htmlspecialchars($t['responsable_nombre'] ?? 'N/A') . '</td>';
                        echo '<td>' . $t['estado'] . '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</table>';
                } else {
                    echo '<div class="error">鈿狅笍 <strong>PROBLEMA ENCONTRADO:</strong><br>';
                    echo 'Hay tomas en la BD, pero ninguna pertenece al cliente_id=' . $cliente_id . '<br>';
                    echo 'Las tomas que existen tienen otros cliente_id (ver tabla anterior)</div>';
                    
                    echo '<div class="info">';
                    echo '<strong>SOLUCI脫N:</strong><br>';
                    echo '1. Verifica que est谩s iniciando sesi贸n con el usuario correcto<br>';
                    echo '2. O actualiza el cliente_id de las tomas existentes a tu cliente_id actual<br>';
                    echo '3. O crea nuevas tomas con el cliente_id correcto';
                    echo '</div>';
                }
            } catch (Exception $e) {
                echo '<div class="error">鉂?Error: ' . $e->getMessage() . '</div>';
            }
        } else {
            echo '<div class="error">鉂?No se puede filtrar porque no hay cliente_id en sesi贸n</div>';
        }
        ?>
    </div>

    <div class="box">
        <h2>6. Informaci贸n del Usuario en Sesi贸n</h2>
        <?php
        if ($user_id !== 'NO DEFINIDO') {
            try {
                $usuario = $db->fetchOne("SELECT * FROM usuarios WHERE id = ?", [$user_id]);
                if ($usuario) {
                    echo '<pre>';
                    echo "ID:              {$usuario['id']}\n";
                    echo "Nombre:          {$usuario['nombre_completo']}\n";
                    echo "Email:           {$usuario['email']}\n";
                    echo "Rol:             {$usuario['rol']}\n";
                    echo "Cliente ID:      {$usuario['cliente_id']}\n";
                    echo '</pre>';
                    
                    if ($usuario['cliente_id'] != $cliente_id) {
                        echo '<div class="error">';
                        echo '鈿狅笍 <strong>INCONSISTENCIA DETECTADA:</strong><br>';
                        echo 'El cliente_id en la sesi贸n (' . $cliente_id . ') NO coincide con el cliente_id del usuario (' . $usuario['cliente_id'] . ')';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="error">鉂?Usuario no encontrado en la BD</div>';
                }
            } catch (Exception $e) {
                echo '<div class="error">鉂?Error: ' . $e->getMessage() . '</div>';
            }
        }
        ?>
    </div>

    <div class="box">
        <h2>7. Recomendaciones</h2>
        <div class="info">
            <?php if ($cliente_id !== 'NO DEFINIDO'): ?>
                <strong>鉁?PUEDES PROCEDER:</strong><br>
                1. Ve a: <a href="inventario-fisico-mejorado.php">inventario-fisico-mejorado.php</a><br>
                2. O usa el archivo original que te envi茅<br>
                3. Si sigues sin ver datos, ejecuta esta consulta en phpMyAdmin:<br>
                <pre>UPDATE inventario_tomas SET cliente_id = <?= $cliente_id ?> WHERE id = 1;</pre>
            <?php else: ?>
                <strong>鈿狅笍 PRIMERO DEBES:</strong><br>
                1. Iniciar sesi贸n en el sistema<br>
                2. Luego volver a esta p谩gina de diagn贸stico
            <?php endif; ?>
        </div>
    </div>

    <div class="box">
        <h2>8. Links 脷tiles</h2>
        <ul>
            <li><a href="inventario-fisico.php">inventario-fisico.php (original)</a></li>
            <li><a href="inventario-fisico-mejorado.php">inventario-fisico-mejorado.php (nuevo)</a></li>
            <li><a href="dashboard-logistico.php">Dashboard Log铆stico</a></li>
            <li><a href="index.php">Dashboard Principal</a></li>
        </ul>
    </div>
</body>
</html>
