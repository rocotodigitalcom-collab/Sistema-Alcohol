<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;
$rol = $_SESSION['rol'] ?? '';

// Misma consulta que el archivo original
$sql = "
    SELECT t.*, u.nombre AS responsable_nombre
    FROM inventario_tomas t
    LEFT JOIN usuarios u ON u.id = t.responsable_id
    WHERE t.cliente_id = ?
    ORDER BY t.fecha_creacion DESC
";

$tomas = $db->fetchAll($sql, [$cliente_id]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ver Tomas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">
                    <i class="fas fa-clipboard-check"></i> 
                    Tomas de Inventario - Prueba Simple
                </h3>
            </div>
            <div class="card-body">
                
                <!-- Info de Sesión -->
                <div class="alert alert-info">
                    <strong>Sesión Actual:</strong><br>
                    Usuario ID: <?= $user_id ?><br>
                    Cliente ID: <?= $cliente_id ?><br>
                    Rol: <?= $rol ?>
                </div>

                <!-- Resultado del Query -->
                <div class="alert alert-secondary">
                    <strong>Resultado del Query:</strong><br>
                    Total de tomas encontradas: <strong><?= count($tomas) ?></strong>
                </div>

                <?php if (empty($tomas)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        No se encontraron tomas de inventario para el cliente_id = <?= $cliente_id ?>
                    </div>
                <?php else: ?>
                    
                    <h4>Tomas Encontradas:</h4>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>ID</th>
                                    <th>Cliente ID</th>
                                    <th>Nombre Toma</th>
                                    <th>Ubicación</th>
                                    <th>Responsable</th>
                                    <th>Responsable ID</th>
                                    <th>Fecha Programada</th>
                                    <th>Estado</th>
                                    <th>Fecha Creación</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tomas as $t): ?>
                                <tr>
                                    <td><strong>#<?= $t['id'] ?></strong></td>
                                    <td><?= $t['cliente_id'] ?></td>
                                    <td><?= htmlspecialchars($t['nombre_toma']) ?></td>
                                    <td><?= htmlspecialchars($t['ubicacion']) ?></td>
                                    <td><?= htmlspecialchars($t['responsable_nombre'] ?? 'Sin asignar') ?></td>
                                    <td><?= $t['responsable_id'] ?></td>
                                    <td><?= $t['fecha_programada'] ?></td>
                                    <td>
                                        <?php if ($t['estado'] === 'abierta'): ?>
                                            <span class="badge bg-success">Abierta</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Cerrada</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $t['fecha_creacion'] ?></td>
                                    <td>
                                        <a href="inventario-fisico.php?view=detalle&toma_id=<?= $t['id'] ?>" 
                                           class="btn btn-sm btn-primary">
                                            <i class="fas fa-folder-open"></i> Ver
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Debug: Imprimir array completo -->
                    <details class="mt-4">
                        <summary class="btn btn-secondary btn-sm">Ver datos completos (Debug)</summary>
                        <pre class="mt-3 p-3 bg-light border"><?php print_r($tomas); ?></pre>
                    </details>

                <?php endif; ?>

                <hr>

                <!-- Links útiles -->
                <div class="d-flex gap-2">
                    <a href="inventario-fisico.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left"></i> Ir a Inventario Completo
                    </a>
                    <a href="inventario-fisico.php?view=nueva" class="btn btn-success">
                        <i class="fas fa-plus"></i> Nueva Toma
                    </a>
                    <a href="debug-simple.php" class="btn btn-info">
                        <i class="fas fa-bug"></i> Debug Simple
                    </a>
                    <a href="test-inventario.php" class="btn btn-warning">
                        <i class="fas fa-cog"></i> Diagnóstico Completo
                    </a>
                </div>

            </div>
        </div>

        <!-- Instrucciones -->
        <div class="card shadow mt-4">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> ¿Qué muestra este archivo?</h5>
            </div>
            <div class="card-body">
                <p><strong>Este archivo hace lo siguiente:</strong></p>
                <ol>
                    <li>Lee tu sesión actual (user_id, cliente_id, rol)</li>
                    <li>Ejecuta exactamente la misma consulta SQL que usa el archivo original</li>
                    <li>Muestra TODOS los datos en una tabla simple</li>
                    <li>Si no aparecen datos aquí, entonces el problema está en la consulta SQL o en la sesión</li>
                </ol>
                
                <div class="alert alert-warning mt-3">
                    <strong>Si ves tomas aquí pero no en inventario-fisico.php:</strong><br>
                    El problema es el archivo PHP de inventario, no la base de datos.
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>