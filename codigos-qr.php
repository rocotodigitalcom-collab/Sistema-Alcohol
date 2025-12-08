<?php
// codigos-qr.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Códigos QR de Alcoholímetros';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'codigos-qr.php' => 'Códigos QR'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// Obtener alcoholímetros
$alcoholimetros = $db->fetchAll("
    SELECT *, 
           DATEDIFF(proxima_calibracion, CURDATE()) as dias_restantes,
           CASE 
               WHEN proxima_calibracion IS NULL THEN 'no_programada'
               WHEN proxima_calibracion < CURDATE() THEN 'vencida'
               WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 30 THEN 'proxima'
               ELSE 'vigente'
           END as estado_calibracion
    FROM alcoholimetros 
    WHERE cliente_id = ? 
    ORDER BY numero_serie ASC
", [$cliente_id]);

// Procesar generación de QR individual
if (isset($_GET['generar_qr'])) {
    $alcoholimetro_id = $_GET['generar_qr'];
    
    try {
        // Verificar que el alcoholímetro pertenece al cliente
        $alcoholimetro = $db->fetchOne("
            SELECT * FROM alcoholimetros 
            WHERE id = ? AND cliente_id = ?
        ", [$alcoholimetro_id, $cliente_id]);
        
        if ($alcoholimetro) {
            // Generar código QR (simulación - en producción usar librería QR)
            $qr_data = [
                'id' => $alcoholimetro['id'],
                'numero_serie' => $alcoholimetro['numero_serie'],
                'nombre_activo' => $alcoholimetro['nombre_activo'],
                'cliente_id' => $cliente_id,
                'timestamp' => time()
            ];
            
            $qr_content = json_encode($qr_data);
            $qr_filename = 'qr_' . $alcoholimetro['numero_serie'] . '_' . time() . '.png';
            
            // En un entorno real, aquí se generaría la imagen QR usando una librería como:
            // phpqrcode, endroid/qr-code, etc.
            // Por ahora, simulamos que se genera el QR
            
            // Actualizar base de datos con el nombre del archivo QR
            $db->execute("
                UPDATE alcoholimetros 
                SET qr_code = ?, fecha_actualizacion = NOW()
                WHERE id = ? AND cliente_id = ?
            ", [$qr_filename, $alcoholimetro_id, $cliente_id]);
            
            $mensaje_exito = "Código QR generado para " . $alcoholimetro['numero_serie'];
            
            // Auditoría
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'GENERAR_QR_INDIVIDUAL', 'alcoholimetros', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $alcoholimetro_id, 
                "Código QR generado: $qr_filename", 
                $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            // Recargar lista
            $alcoholimetros = $db->fetchAll("
                SELECT *, 
                       DATEDIFF(proxima_calibracion, CURDATE()) as dias_restantes,
                       CASE 
                           WHEN proxima_calibracion IS NULL THEN 'no_programada'
                           WHEN proxima_calibracion < CURDATE() THEN 'vencida'
                           WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 30 THEN 'proxima'
                           ELSE 'vigente'
                       END as estado_calibracion
                FROM alcoholimetros 
                WHERE cliente_id = ? 
                ORDER BY numero_serie ASC
            ", [$cliente_id]);
        }
        
    } catch (Exception $e) {
        $mensaje_error = "Error al generar QR: " . $e->getMessage();
    }
}

// Procesar generación masiva de QR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_qr_masivo'])) {
    try {
        $alcoholimetros_sin_qr = $db->fetchAll("
            SELECT * FROM alcoholimetros 
            WHERE cliente_id = ? AND (qr_code IS NULL OR qr_code = '')
        ", [$cliente_id]);
        
        $generados = 0;
        
        foreach ($alcoholimetros_sin_qr as $alcoholimetro) {
            // Generar código QR (simulación)
            $qr_data = [
                'id' => $alcoholimetro['id'],
                'numero_serie' => $alcoholimetro['numero_serie'],
                'nombre_activo' => $alcoholimetro['nombre_activo'],
                'cliente_id' => $cliente_id,
                'timestamp' => time()
            ];
            
            $qr_content = json_encode($qr_data);
            $qr_filename = 'qr_' . $alcoholimetro['numero_serie'] . '_' . time() . '.png';
            
            // Actualizar base de datos
            $db->execute("
                UPDATE alcoholimetros 
                SET qr_code = ?, fecha_actualizacion = NOW()
                WHERE id = ? AND cliente_id = ?
            ", [$qr_filename, $alcoholimetro['id'], $cliente_id]);
            
            $generados++;
        }
        
        if ($generados > 0) {
            $mensaje_exito = "Se generaron $generados códigos QR correctamente";
            
            // Auditoría
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, detalles, ip_address, user_agent)
                VALUES (?, ?, 'GENERAR_QR_MASIVO', 'alcoholimetros', ?, ?, ?)
            ", [$cliente_id, $user_id, 
                "Generación masiva de QR: $generados códigos generados", 
                $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
            
            // Recargar lista
            $alcoholimetros = $db->fetchAll("
                SELECT *, 
                       DATEDIFF(proxima_calibracion, CURDATE()) as dias_restantes,
                       CASE 
                           WHEN proxima_calibracion IS NULL THEN 'no_programada'
                           WHEN proxima_calibracion < CURDATE() THEN 'vencida'
                           WHEN DATEDIFF(proxima_calibracion, CURDATE()) <= 30 THEN 'proxima'
                           ELSE 'vigente'
                       END as estado_calibracion
                FROM alcoholimetros 
                WHERE cliente_id = ? 
                ORDER BY numero_serie ASC
            ", [$cliente_id]);
        } else {
            $mensaje_info = "Todos los alcoholímetros ya tienen códigos QR generados";
        }
        
    } catch (Exception $e) {
        $mensaje_error = "Error en generación masiva: " . $e->getMessage();
    }
}

// Procesar descarga de QR
if (isset($_GET['descargar_qr'])) {
    $alcoholimetro_id = $_GET['descargar_qr'];
    
    try {
        $alcoholimetro = $db->fetchOne("
            SELECT * FROM alcoholimetros 
            WHERE id = ? AND cliente_id = ?
        ", [$alcoholimetro_id, $cliente_id]);
        
        if ($alcoholimetro && $alcoholimetro['qr_code']) {
            // En producción, aquí se forzaría la descarga del archivo QR
            // Por ahora, simulamos la descarga
            $mensaje_exito = "Descarga iniciada para " . $alcoholimetro['numero_serie'];
            
            // Auditoría
            $db->execute("
                INSERT INTO auditoria (cliente_id, usuario_id, accion, tabla_afectada, registro_id, detalles, ip_address, user_agent)
                VALUES (?, ?, 'DESCARGAR_QR', 'alcoholimetros', ?, ?, ?, ?)
            ", [$cliente_id, $user_id, $alcoholimetro_id, 
                "Descarga de código QR: " . $alcoholimetro['qr_code'], 
                $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']]);
        } else {
            $mensaje_error = "El alcoholímetro no tiene código QR generado";
        }
        
    } catch (Exception $e) {
        $mensaje_error = "Error al descargar QR: " . $e->getMessage();
    }
}

// Estadísticas
$estadisticas_qr = $db->fetchOne("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN qr_code IS NOT NULL AND qr_code != '' THEN 1 ELSE 0 END) as con_qr,
        SUM(CASE WHEN qr_code IS NULL OR qr_code = '' THEN 1 ELSE 0 END) as sin_qr
    FROM alcoholimetros 
    WHERE cliente_id = ?
", [$cliente_id]);

$porcentaje_qr = $estadisticas_qr['total'] > 0 ? 
    round(($estadisticas_qr['con_qr'] / $estadisticas_qr['total']) * 100, 1) : 0;
?>

<div class="content-body">
    <!-- Header -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1><?php echo $page_title; ?></h1>
            <p class="dashboard-subtitle">Genera y gestiona códigos QR para identificación rápida de alcoholímetros</p>
        </div>
        <div class="header-actions">
            <form method="POST" style="display: inline;">
                <button type="submit" name="generar_qr_masivo" class="btn btn-primary">
                    <i class="fas fa-qrcode"></i>
                    Generar QR Masivo
                </button>
            </form>
        </div>
    </div>

    <!-- Alertas -->
    <?php if (isset($mensaje_exito)): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo $mensaje_exito; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($mensaje_error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo $mensaje_error; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($mensaje_info)): ?>
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <?php echo $mensaje_info; ?>
    </div>
    <?php endif; ?>

    <!-- Panel de Estadísticas -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon primary">
                <i class="fas fa-tachometer-alt"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas_qr['total']; ?></h3>
                <p>Total Alcoholímetros</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success">
                <i class="fas fa-qrcode"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas_qr['con_qr']; ?></h3>
                <p>Con QR Generado</p>
                <div class="stat-percentage">
                    <span class="percentage success"><?php echo $porcentaje_qr; ?>%</span>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="stat-info">
                <h3><?php echo $estadisticas_qr['sin_qr']; ?></h3>
                <p>Sin QR Generado</p>
            </div>
        </div>
    </div>

    <!-- Progreso de Generación -->
    <?php if ($estadisticas_qr['total'] > 0): ?>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Progreso de Generación de QR</h3>
        </div>
        <div class="card-body">
            <div class="progress-container">
                <div class="progress-info">
                    <span class="progress-text">Códigos QR Generados</span>
                    <span class="progress-percentage"><?php echo $porcentaje_qr; ?>%</span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $porcentaje_qr; ?>%"></div>
                </div>
                <div class="progress-stats">
                    <span><?php echo $estadisticas_qr['con_qr']; ?> de <?php echo $estadisticas_qr['total']; ?> alcoholímetros</span>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="crud-container">
        <!-- Lista de Alcoholímetros con QR -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Alcoholímetros y Códigos QR</h3>
                <div class="card-actions">
                    <span class="badge"><?php echo count($alcoholimetros); ?> equipos</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (!empty($alcoholimetros)): ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>N° Serie</th>
                                <th>Nombre Activo</th>
                                <th>Modelo/Marca</th>
                                <th>Estado</th>
                                <th>Código QR</th>
                                <th>Última Actualización</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alcoholimetros as $alcoholimetro): 
                                $tiene_qr = !empty($alcoholimetro['qr_code']);
                                $estado = $alcoholimetro['estado'];
                                
                                // Configuración estado
                                switch ($estado) {
                                    case 'activo':
                                        $clase_estado = 'success';
                                        $texto_estado = 'Activo';
                                        $icono_estado = 'fa-play-circle';
                                        break;
                                    case 'mantenimiento':
                                        $clase_estado = 'warning';
                                        $texto_estado = 'Mantenimiento';
                                        $icono_estado = 'fa-tools';
                                        break;
                                    case 'calibracion':
                                        $clase_estado = 'info';
                                        $texto_estado = 'En Calibración';
                                        $icono_estado = 'fa-sync-alt';
                                        break;
                                    case 'inactivo':
                                        $clase_estado = 'secondary';
                                        $texto_estado = 'Inactivo';
                                        $icono_estado = 'fa-pause-circle';
                                        break;
                                }
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($alcoholimetro['numero_serie']); ?></td>
                                <td><?php echo htmlspecialchars($alcoholimetro['nombre_activo']); ?></td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($alcoholimetro['modelo'] ?? 'N/A'); ?> / 
                                        <?php echo htmlspecialchars($alcoholimetro['marca'] ?? 'N/A'); ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo $clase_estado; ?>">
                                        <i class="fas <?php echo $icono_estado; ?>"></i>
                                        <?php echo $texto_estado; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($tiene_qr): ?>
                                        <div class="qr-status success">
                                            <i class="fas fa-check-circle"></i>
                                            <span>Generado</span>
                                            <small class="text-muted"><?php echo $alcoholimetro['qr_code']; ?></small>
                                        </div>
                                    <?php else: ?>
                                        <div class="qr-status warning">
                                            <i class="fas fa-exclamation-circle"></i>
                                            <span>Pendiente</span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('d/m/Y H:i', strtotime($alcoholimetro['fecha_actualizacion'])); ?>
                                    </small>
                                </td>
                                <td class="action-buttons">
                                    <?php if ($tiene_qr): ?>
                                        <a href="?descargar_qr=<?php echo $alcoholimetro['id']; ?>" class="btn-icon success" title="Descargar QR">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <a href="?generar_qr=<?php echo $alcoholimetro['id']; ?>" class="btn-icon primary" title="Regenerar QR">
                                            <i class="fas fa-sync-alt"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="?generar_qr=<?php echo $alcoholimetro['id']; ?>" class="btn-icon primary" title="Generar QR">
                                            <i class="fas fa-qrcode"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn-icon info" 
                                            onclick="mostrarInfoQR(<?php echo $alcoholimetro['id']; ?>, '<?php echo htmlspecialchars($alcoholimetro['numero_serie']); ?>')"
                                            title="Información QR">
                                        <i class="fas fa-info-circle"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3>No hay alcoholímetros registrados</h3>
                    <p>Registra alcoholímetros para generar sus códigos QR</p>
                    <a href="registrar-alcoholimetro.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Registrar Alcoholímetro
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Panel de Información QR -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-info-circle"></i> Información sobre Códigos QR</h3>
            </div>
            <div class="card-body">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon primary">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Escaneo Rápido</h4>
                            <p>Los códigos QR permiten acceso rápido a la información del alcoholímetro desde cualquier dispositivo móvil</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon success">
                            <i class="fas fa-bolt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Identificación Inmediata</h4>
                            <p>Identifique rápidamente cada alcoholímetro y acceda a su historial de calibraciones y pruebas</p>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon warning">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <div class="info-content">
                            <h4>Actualización Automática</h4>
                            <p>La información del código QR se actualiza automáticamente con los últimos datos del sistema</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Información QR -->
<div id="modalInfoQR" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-qrcode"></i> Información del Código QR</h3>
            <button type="button" class="modal-close" onclick="cerrarModalInfoQR()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-body">
            <div class="qr-info-container">
                <div class="qr-preview">
                    <div class="qr-placeholder">
                        <i class="fas fa-qrcode"></i>
                        <span>Vista previa del QR</span>
                    </div>
                </div>
                <div class="qr-details">
                    <h4 id="qrAlcoholimetroInfo"></h4>
                    <div class="detail-list">
                        <div class="detail-item">
                            <span class="detail-label">Contenido del QR:</span>
                            <span class="detail-value" id="qrContent"></span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Formato:</span>
                            <span class="detail-value">PNG (300x300px)</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Tamaño estimado:</span>
                            <span class="detail-value">~5 KB</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Compatibilidad:</span>
                            <span class="detail-value">Todos los lectores QR</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="qr-usage">
                <h5>¿Cómo usar el código QR?</h5>
                <ol>
                    <li>Descargue el código QR e imprímalo</li>
                    <li>Pegue el código en el alcoholímetro correspondiente</li>
                    <li>Use cualquier app lectora de QR para escanear</li>
                    <li>Acceda a la información actualizada del equipo</li>
                </ol>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="cerrarModalInfoQR()">
                <i class="fas fa-check"></i>
                Entendido
            </button>
        </div>
    </div>
</div>

<!-- ESTILOS ESPECÍFICOS PARA CÓDIGOS QR -->
<style>
/* Contenedor principal */
.crud-container {
    margin-top: 1.5rem;
    width: 100%;
}

/* Panel de estadísticas */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
    transition: transform 0.3s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.stat-icon.primary { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
.stat-icon.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
.stat-icon.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }
.stat-icon.danger { background: rgba(231, 76, 60, 0.1); color: var(--danger); }

.stat-info {
    flex: 1;
}

.stat-info h3 {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    color: var(--dark);
}

.stat-info p {
    margin: 0.25rem 0 0 0;
    color: var(--gray);
    font-size: 0.9rem;
    font-weight: 500;
}

.stat-percentage {
    margin-top: 0.5rem;
}

.percentage {
    padding: 0.25rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.percentage.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
.percentage.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }

/* Barra de progreso */
.progress-container {
    padding: 1rem 0;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.5rem;
}

.progress-text {
    font-weight: 600;
    color: var(--dark);
}

.progress-percentage {
    font-weight: 700;
    color: var(--primary);
    font-size: 1.1rem;
}

.progress-bar {
    height: 12px;
    background: var(--light);
    border-radius: 6px;
    overflow: hidden;
    margin-bottom: 0.5rem;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--success), var(--success-dark));
    border-radius: 6px;
    transition: width 0.5s ease;
}

.progress-stats {
    text-align: center;
    color: var(--gray);
    font-size: 0.85rem;
}

/* Estados QR */
.qr-status {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
    padding: 0.5rem;
    border-radius: 8px;
}

.qr-status.success {
    background: rgba(39, 174, 96, 0.1);
    border: 1px solid rgba(39, 174, 96, 0.2);
    color: var(--success);
}

.qr-status.warning {
    background: rgba(243, 156, 18, 0.1);
    border: 1px solid rgba(243, 156, 18, 0.2);
    color: var(--warning);
}

.qr-status i {
    font-size: 1.1rem;
    margin-right: 0.5rem;
}

/* Información QR */
.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 1.5rem;
}

.info-item {
    display: flex;
    gap: 1rem;
    align-items: flex-start;
}

.info-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    flex-shrink: 0;
    margin-top: 0.25rem;
}

.info-icon.primary { background: rgba(52, 152, 219, 0.1); color: var(--primary); }
.info-icon.success { background: rgba(39, 174, 96, 0.1); color: var(--success); }
.info-icon.warning { background: rgba(243, 156, 18, 0.1); color: var(--warning); }

.info-content h4 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-size: 1.1rem;
}

.info-content p {
    margin: 0;
    color: var(--gray);
    line-height: 1.5;
}

/* Modal QR */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(5px);
}

.modal-content {
    background-color: white;
    margin: 2% auto;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    animation: modalSlideIn 0.3s ease-out;
}

.modal-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--light);
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 12px 12px 0 0;
    position: sticky;
    top: 0;
    z-index: 10;
}

.modal-header h3 {
    margin: 0;
    color: var(--dark);
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.modal-close {
    background: none;
    border: none;
    font-size: 1.25rem;
    color: var(--gray);
    cursor: pointer;
    padding: 0.25rem;
    border-radius: 4px;
    transition: var(--transition);
}

.modal-close:hover {
    color: var(--danger);
    background: rgba(231, 76, 60, 0.1);
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    background: var(--light);
    border-radius: 0 0 12px 12px;
}

/* Contenido del modal QR */
.qr-info-container {
    display: grid;
    grid-template-columns: 200px 1fr;
    gap: 1.5rem;
    margin-bottom: 1.5rem;
}

.qr-preview {
    display: flex;
    justify-content: center;
    align-items: center;
}

.qr-placeholder {
    width: 150px;
    height: 150px;
    border: 2px dashed var(--border);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
    color: var(--gray);
    background: var(--light);
}

.qr-placeholder i {
    font-size: 3rem;
    margin-bottom: 0.5rem;
}

.qr-details h4 {
    margin: 0 0 1rem 0;
    color: var(--dark);
    font-size: 1.2rem;
}

.detail-list {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
}

.detail-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 0.75rem;
    border-bottom: 1px solid var(--border-light);
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: var(--dark);
    flex: 1;
}

.detail-value {
    color: var(--gray);
    flex: 2;
    text-align: right;
    word-break: break-all;
}

.qr-usage {
    background: var(--light);
    padding: 1.5rem;
    border-radius: 8px;
    border-left: 4px solid var(--primary);
}

.qr-usage h5 {
    margin: 0 0 1rem 0;
    color: var(--dark);
    font-size: 1.1rem;
}

.qr-usage ol {
    margin: 0;
    padding-left: 1.5rem;
    color: var(--gray);
}

.qr-usage li {
    margin-bottom: 0.5rem;
    line-height: 1.4;
}

.qr-usage li:last-child {
    margin-bottom: 0;
}

/* Tabla de datos */
.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    margin: 0;
}

.data-table th {
    background: var(--light);
    padding: 1rem;
    text-align: left;
    font-weight: 600;
    color: var(--dark);
    border-bottom: 2px solid var(--border);
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 1rem;
    border-bottom: 1px solid var(--border);
    color: var(--dark);
    vertical-align: middle;
}

.data-table tr:last-child td {
    border-bottom: none;
}

.data-table tr:hover {
    background: rgba(52, 152, 219, 0.04);
}

/* Botones de acción */
.action-buttons {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
}

.btn-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: var(--light);
    color: var(--dark);
    text-decoration: none;
    transition: all 0.3s ease;
    border: none;
    cursor: pointer;
}

.btn-icon:hover {
    transform: translateY(-2px);
}

.btn-icon.primary:hover {
    background: var(--primary);
    color: white;
}

.btn-icon.success:hover {
    background: var(--success);
    color: white;
}

.btn-icon.info:hover {
    background: var(--primary);
    color: white;
}

/* Badges de estado */
.status-badge {
    padding: 0.4rem 0.8rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: capitalize;
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    min-width: 120px;
    justify-content: center;
}

.status-badge.success {
    background: rgba(39, 174, 96, 0.15);
    color: var(--success);
    border: 1px solid rgba(39, 174, 96, 0.3);
}

.status-badge.warning {
    background: rgba(243, 156, 18, 0.15);
    color: var(--warning);
    border: 1px solid rgba(243, 156, 18, 0.3);
}

.status-badge.info {
    background: rgba(52, 152, 219, 0.15);
    color: var(--primary);
    border: 1px solid rgba(52, 152, 219, 0.3);
}

.status-badge.secondary {
    background: rgba(149, 165, 166, 0.15);
    color: var(--gray);
    border: 1px solid rgba(149, 165, 166, 0.3);
}

/* Badge contador */
.badge {
    padding: 0.4rem 0.8rem;
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-radius: 20px;
    font-size: 0.8rem;
    font-weight: 600;
}

/* Tabla responsive */
.table-responsive {
    overflow-x: auto;
    border-radius: 12px;
}

/* Estados vacíos */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: var(--gray);
}

.empty-icon {
    font-size: 4rem;
    color: var(--light);
    margin-bottom: 1.5rem;
    opacity: 0.7;
}

.empty-state h3 {
    color: var(--dark);
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.empty-state p {
    margin-bottom: 2rem;
    font-size: 1rem;
    opacity: 0.8;
}

/* Colores de texto */
.text-danger { 
    color: var(--danger) !important; 
    font-weight: 600;
}
.text-warning { 
    color: var(--warning) !important; 
    font-weight: 600;
}
.text-success { 
    color: var(--success) !important; 
    font-weight: 600;
}
.text-muted { 
    color: var(--gray) !important; 
    opacity: 0.7;
}

/* Header del dashboard */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding: 1.5rem 0;
    border-bottom: 1px solid var(--border);
}

.welcome-section h1 {
    margin: 0 0 0.5rem 0;
    color: var(--dark);
    font-size: 1.8rem;
    font-weight: 700;
}

.dashboard-subtitle {
    margin: 0;
    color: var(--gray);
    font-size: 1rem;
}

.header-actions {
    display: flex;
    gap: 1rem;
}

/* Card improvements */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--border);
    overflow: hidden;
    margin-bottom: 1.5rem;
}

.card-header {
    padding: 1.5rem;
    border-bottom: 1px solid var(--border);
    background: var(--light);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header h3 {
    margin: 0;
    color: var(--dark);
    font-size: 1.3rem;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.card-body {
    padding: 1.5rem;
}

/* Botones */
.btn {
    padding: 0.875rem 1.5rem;
    border-radius: 10px;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    transition: all 0.3s ease;
    border: 2px solid transparent;
    cursor: pointer;
    font-size: 0.9rem;
}

.btn-primary {
    background: linear-gradient(135deg, var(--primary), var(--primary-dark));
    color: white;
    border-color: var(--primary);
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(52, 152, 219, 0.3);
}

.btn-outline {
    background: transparent;
    color: var(--dark);
    border-color: var(--border);
}

.btn-outline:hover {
    background: var(--light);
    border-color: var(--primary);
    color: var(--primary);
    transform: translateY(-2px);
}

/* Alertas mejoradas */
.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border: 1px solid transparent;
}

.alert-success {
    background: rgba(39, 174, 96, 0.1);
    border-color: rgba(39, 174, 96, 0.2);
    color: var(--success);
}

.alert-danger {
    background: rgba(231, 76, 60, 0.1);
    border-color: rgba(231, 76, 60, 0.2);
    color: var(--danger);
}

.alert-info {
    background: rgba(52, 152, 219, 0.1);
    border-color: rgba(52, 152, 219, 0.2);
    color: var(--primary);
}

/* Animaciones */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-50px) scale(0.9); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

.card {
    animation: fadeIn 0.5s ease-out;
}

.data-table tr {
    animation: fadeIn 0.3s ease-out;
}

.stat-card {
    animation: fadeIn 0.5s ease-out;
}

/* Responsive */
@media (max-width: 1024px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
    
    .qr-info-container {
        grid-template-columns: 1fr;
        text-align: center;
    }
    
    .data-table {
        font-size: 0.85rem;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .header-actions {
        width: 100%;
        justify-content: flex-start;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .data-table {
        font-size: 0.8rem;
    }
    
    .data-table th,
    .data-table td {
        padding: 0.75rem 0.5rem;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 0.25rem;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
    }
    
    .modal-content {
        margin: 5% auto;
        width: 95%;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 1rem;
    }
    
    .card-actions {
        align-self: flex-start;
    }
}
</style>

<script>
// Funciones JavaScript para el módulo de QR
function mostrarInfoQR(id, numeroSerie) {
    document.getElementById('qrAlcoholimetroInfo').textContent = 'Alcoholímetro: ' + numeroSerie;
    
    // Simular contenido del QR
    const qrContent = JSON.stringify({
        alcoholimetro_id: id,
        numero_serie: numeroSerie,
        tipo: 'alcoholimetro',
        cliente_id: <?php echo $cliente_id; ?>,
        timestamp: new Date().getTime()
    }, null, 2);
    
    document.getElementById('qrContent').textContent = qrContent;
    document.getElementById('modalInfoQR').style.display = 'block';
}

function cerrarModalInfoQR() {
    document.getElementById('modalInfoQR').style.display = 'none';
}

// Cerrar modal al hacer clic fuera
window.onclick = function(event) {
    const modal = document.getElementById('modalInfoQR');
    if (event.target === modal) {
        cerrarModalInfoQR();
    }
}

// Cerrar modal con ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        cerrarModalInfoQR();
    }
});

// Confirmación para regenerar QR
function confirmarRegenerarQR(numeroSerie) {
    return confirm('¿Estás seguro de que deseas regenerar el código QR para ' + numeroSerie + '? El código anterior será reemplazado.');
}

// Función para previsualizar QR (simulación)
function previsualizarQR(id) {
    alert('Función de previsualización en desarrollo para el alcoholímetro ID: ' + id);
}

// Función para imprimir códigos QR
function imprimirCodigosQR() {
    // En producción, esto generaría un PDF con todos los códigos QR
    alert('Función de impresión masiva en desarrollo');
}

// Función para exportar lista de QR
function exportarListaQR() {
    // En producción, esto exportaría un CSV con la información de QR
    alert('Función de exportación en desarrollo');
}

// Inicializar
document.addEventListener('DOMContentLoaded', function() {
    // Agregar event listeners para confirmaciones
    const linksRegenerar = document.querySelectorAll('a[href*="generar_qr"]');
    linksRegenerar.forEach(link => {
        if (link.querySelector('.fa-sync-alt')) {
            link.addEventListener('click', function(e) {
                const numeroSerie = this.closest('tr').querySelector('td:first-child').textContent;
                if (!confirmarRegenerarQR(numeroSerie)) {
                    e.preventDefault();
                }
            });
        }
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>