<?php
// ajax/detalle-prueba.php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';

$db = new Database();
$prueba_id = $_GET['id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// Simulación de datos - en una implementación real, obtendrías los datos de la base de datos
$prueba = [
    'id' => $prueba_id,
    'nivel_alcohol' => 0.025,
    'limite_permisible' => 0.000,
    'resultado' => 'reprobado',
    'fecha_prueba' => date('Y-m-d H:i:s'),
    'conductor_nombre' => 'Juan Pérez',
    'conductor_dni' => '12345678',
    'conductor_telefono' => '999888777',
    'supervisor_nombre' => 'María García',
    'alcoholimetro_nombre' => 'Alcoholímetro Principal',
    'alcoholimetro_serie' => 'ALC-001',
    'marca' => 'Toyota',
    'vehiculo_modelo' => 'Hilux',
    'placa' => 'ABC-123',
    'color' => 'Blanco',
    'observaciones' => 'Prueba realizada en el turno de la mañana. Conductor presentó niveles por encima del límite permisible.',
    'es_retest' => false
];

if (!$prueba) {
    echo "<div class='alert alert-danger'>Prueba no encontrada</div>";
    exit;
}
?>

<div class="detalle-prueba">
    <div class="info-grid" style="grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div class="info-section">
            <h4><i class="fas fa-user"></i> Información del Conductor</h4>
            <div class="info-item">
                <label>Nombre:</label>
                <span><?php echo htmlspecialchars($prueba['conductor_nombre']); ?></span>
            </div>
            <div class="info-item">
                <label>DNI:</label>
                <span><?php echo htmlspecialchars($prueba['conductor_dni']); ?></span>
            </div>
            <div class="info-item">
                <label>Teléfono:</label>
                <span><?php echo htmlspecialchars($prueba['conductor_telefono'] ?? 'No disponible'); ?></span>
            </div>
        </div>

        <div class="info-section">
            <h4><i class="fas fa-car"></i> Información del Vehículo</h4>
            <div class="info-item">
                <label>Vehículo:</label>
                <span><?php echo htmlspecialchars($prueba['marca'] . ' ' . $prueba['vehiculo_modelo']); ?></span>
            </div>
            <div class="info-item">
                <label>Placa:</label>
                <span class="placa-badge"><?php echo htmlspecialchars($prueba['placa']); ?></span>
            </div>
            <div class="info-item">
                <label>Color:</label>
                <span><?php echo htmlspecialchars($prueba['color'] ?? 'No especificado'); ?></span>
            </div>
        </div>

        <div class="info-section">
            <h4><i class="fas fa-tachometer-alt"></i> Detalles de la Prueba</h4>
            <div class="info-item">
                <label>Nivel de Alcohol:</label>
                <span class="nivel-alcohol <?php echo $prueba['nivel_alcohol'] > 0.000 ? 'text-danger' : 'text-success'; ?>">
                    <strong><?php echo number_format($prueba['nivel_alcohol'], 3); ?> g/L</strong>
                </span>
            </div>
            <div class="info-item">
                <label>Límite Permisible:</label>
                <span><?php echo number_format($prueba['limite_permisible'], 3); ?> g/L</span>
            </div>
            <div class="info-item">
                <label>Resultado:</label>
                <span class="status-badge estado-<?php echo $prueba['resultado']; ?>">
                    <?php echo $prueba['resultado'] === 'aprobado' ? 'Aprobado' : 'Reprobado'; ?>
                </span>
            </div>
        </div>

        <div class="info-section">
            <h4><i class="fas fa-info-circle"></i> Información Adicional</h4>
            <div class="info-item">
                <label>Fecha y Hora:</label>
                <span><?php echo date('d/m/Y H:i:s', strtotime($prueba['fecha_prueba'])); ?></span>
            </div>
            <div class="info-item">
                <label>Alcoholímetro:</label>
                <span><?php echo htmlspecialchars($prueba['alcoholimetro_nombre'] . ' (' . $prueba['alcoholimetro_serie'] . ')'); ?></span>
            </div>
            <div class="info-item">
                <label>Supervisor:</label>
                <span><?php echo htmlspecialchars($prueba['supervisor_nombre']); ?></span>
            </div>
        </div>
    </div>

    <?php if ($prueba['observaciones']): ?>
    <div class="info-section" style="margin-top: 1.5rem;">
        <h4><i class="fas fa-sticky-note"></i> Observaciones</h4>
        <div class="observaciones">
            <?php echo nl2br(htmlspecialchars($prueba['observaciones'])); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($prueba['es_retest']): ?>
    <div class="alert alert-info" style="margin-top: 1rem;">
        <i class="fas fa-redo"></i>
        <strong>Esta es una prueba de re-test</strong>
    </div>
    <?php endif; ?>
</div>