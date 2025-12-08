<?php
// dashboard-logistico.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Dashboard Logístico';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'dashboard-logistico.php' => 'Gestión Logística'
];

require_once __DIR__ . '/includes/header-logistica.php';

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

// Obtener datos del usuario actual
$usuario_actual = $db->fetchOne("
    SELECT u.*, c.nombre_empresa, p.nombre_plan 
    FROM usuarios u
    LEFT JOIN clientes c ON u.cliente_id = c.id
    LEFT JOIN planes p ON c.plan_id = p.id
    WHERE u.id = ?
", [$user_id]);

// KPIs de Logística
$kpi_rotacion = 12;
$kpi_fillrate = 96;
$kpi_stock_bajo = 8;
$kpi_entregas = 124;
$kpi_pedidos_pendientes = 35;
$kpi_ordenes_compra = 18;
$kpi_vehiculos = 15;
$kpi_conductores = 42;

// Datos de inventario crítico
$inventario_critico = [
    ['producto' => 'Producto A', 'stock' => 45, 'minimo' => 50, 'estado' => 'critico'],
    ['producto' => 'Producto B', 'stock' => 23, 'minimo' => 100, 'estado' => 'critico'],
    ['producto' => 'Producto C', 'stock' => 78, 'minimo' => 80, 'estado' => 'bajo'],
];

// Pedidos recientes
$pedidos_recientes = [
    ['id' => 'PED-001', 'cliente' => 'Cliente A', 'fecha' => '2024-12-06', 'estado' => 'En proceso', 'total' => 'S/. 1,250'],
    ['id' => 'PED-002', 'cliente' => 'Cliente B', 'fecha' => '2024-12-06', 'estado' => 'Entregado', 'total' => 'S/. 890'],
    ['id' => 'PED-003', 'cliente' => 'Cliente C', 'fecha' => '2024-12-05', 'estado' => 'Pendiente', 'total' => 'S/. 2,340'],
];
?>

<div class="content-body">
    <!-- Header del Dashboard -->
    <div class="dashboard-header">
        <div class="welcome-section">
            <h1>Gestión Logística</h1>
            <p class="dashboard-subtitle">Control total de inventarios, compras y transporte - <?php echo date('d/m/Y'); ?></p>
        </div>
        <div class="header-actions">
            <span class="user-badge">
                <i class="fas fa-user-circle"></i>
                <?php echo htmlspecialchars($usuario_actual['rol'] ?? 'Usuario'); ?>
            </span>
            <button class="btn btn-outline" onclick="refreshDashboard()">
                <i class="fas fa-sync-alt"></i>
                Actualizar
            </button>
        </div>
    </div>

    <!-- ÁREA DE DASHBOARD PRINCIPAL -->
    <div class="logistica-dashboard">
            
            <!-- Estadísticas Principales -->
            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <i class="fas fa-sync-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Rotación</h3>
                        <div class="stat-number"><?php echo $kpi_rotacion; ?></div>
                        <div class="stat-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+8% vs mes anterior</span>
                        </div>
                        <small>Veces por año</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Fill Rate</h3>
                        <div class="stat-number"><?php echo $kpi_fillrate; ?>%</div>
                        <div class="stat-trend positive">
                            <i class="fas fa-arrow-up"></i>
                            <span>+2% vs semana anterior</span>
                        </div>
                        <small>Tasa de cumplimiento</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning pulse">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Stock Crítico</h3>
                        <div class="stat-number"><?php echo $kpi_stock_bajo; ?></div>
                        <div class="stat-trend negative">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Requieren atención</span>
                        </div>
                        <small>Productos bajo mínimo</small>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon info">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-info">
                        <h3>Entregas Hoy</h3>
                        <div class="stat-number"><?php echo $kpi_entregas; ?></div>
                        <div class="stat-trend neutral">
                            <i class="fas fa-minus"></i>
                            <span>Sin cambios</span>
                        </div>
                        <small>Pedidos en ruta</small>
                    </div>
                </div>
            </div>

            <!-- Contenido del Dashboard -->
            <div class="dashboard-content-grid">
                
                <!-- Columna Izquierda -->
                <div class="content-column main-column">
                    
                    <!-- Inventario Crítico -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-exclamation-triangle"></i> Inventario Crítico</h3>
                            <div class="card-actions">
                                <a href="stock-tiempo-real.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-warehouse"></i>
                                    Ver Todo el Stock
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($inventario_critico)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Stock Actual</th>
                                            <th>Mínimo</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($inventario_critico as $item): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($item['producto']); ?></strong>
                                            </td>
                                            <td><?php echo $item['stock']; ?> unidades</td>
                                            <td><?php echo $item['minimo']; ?> unidades</td>
                                            <td>
                                                <span class="status-badge <?php echo $item['estado'] === 'critico' ? 'danger' : 'warning'; ?>">
                                                    <i class="fas fa-exclamation-circle"></i>
                                                    <?php echo ucfirst($item['estado']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <h4>Sin productos críticos</h4>
                                <p>Todos los productos tienen stock suficiente</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Pedidos Recientes -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-history"></i> Pedidos Recientes</h3>
                            <div class="card-actions">
                                <a href="pedidos-recibidos.php" class="btn btn-primary btn-sm">
                                    <i class="fas fa-list"></i>
                                    Ver Todos los Pedidos
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pedidos_recientes)): ?>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>ID Pedido</th>
                                            <th>Cliente</th>
                                            <th>Fecha</th>
                                            <th>Estado</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pedidos_recientes as $pedido): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($pedido['id']); ?></strong>
                                            </td>
                                            <td><?php echo htmlspecialchars($pedido['cliente']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($pedido['fecha'])); ?></td>
                                            <td>
                                                <span class="status-badge <?php 
                                                    echo $pedido['estado'] === 'Entregado' ? 'success' : 
                                                        ($pedido['estado'] === 'En proceso' ? 'info' : 'warning'); 
                                                ?>">
                                                    <?php echo $pedido['estado']; ?>
                                                </span>
                                            </td>
                                            <td><strong><?php echo $pedido['total']; ?></strong></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-inbox"></i>
                                <h4>No hay pedidos recientes</h4>
                                <p>No se encontraron pedidos en el sistema</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

                <!-- Columna Derecha -->
                <div class="content-column side-column">
                    
                    <!-- Accesos Rápidos -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-bolt"></i> Accesos Rápidos</h3>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions-grid">
                                <a href="solicitudes-compra.php" class="quick-action">
                                    <div class="action-icon">
                                        <i class="fas fa-file-alt"></i>
                                    </div>
                                    <div class="action-info">
                                        <div class="action-title">Nueva Solicitud</div>
                                        <div class="action-desc">Crear solicitud de compra</div>
                                    </div>
                                    <div class="action-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </a>

                                <a href="picking.php" class="quick-action">
                                    <div class="action-icon">
                                        <i class="fas fa-hand-holding-box"></i>
                                    </div>
                                    <div class="action-info">
                                        <div class="action-title">Picking</div>
                                        <div class="action-desc">Preparar pedidos</div>
                                    </div>
                                    <div class="action-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </a>

                                <a href="hojas-ruta.php" class="quick-action">
                                    <div class="action-icon">
                                        <i class="fas fa-map-marked-alt"></i>
                                    </div>
                                    <div class="action-info">
                                        <div class="action-title">Hoja de Ruta</div>
                                        <div class="action-desc">Crear nueva ruta</div>
                                    </div>
                                    <div class="action-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </a>

                                <a href="inventario-fisico.php" class="quick-action">
                                    <div class="action-icon">
                                        <i class="fas fa-clipboard-check"></i>
                                    </div>
                                    <div class="action-info">
                                        <div class="action-title">Inventario Físico</div>
                                        <div class="action-desc">Toma de inventario</div>
                                    </div>
                                    <div class="action-arrow">
                                        <i class="fas fa-chevron-right"></i>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Estado Operativo -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-heartbeat"></i> Estado Operativo</h3>
                        </div>
                        <div class="card-body">
                            <div class="system-status">
                                <div class="status-item">
                                    <div class="status-info">
                                        <i class="fas fa-dolly status-icon active"></i>
                                        <div class="status-text">
                                            <div class="status-title">Pedidos Pendientes</div>
                                            <div class="status-value"><?php echo $kpi_pedidos_pendientes; ?> pedidos</div>
                                        </div>
                                    </div>
                                    <div class="status-badge warning">Activo</div>
                                </div>
                                
                                <div class="status-item">
                                    <div class="status-info">
                                        <i class="fas fa-shopping-cart status-icon"></i>
                                        <div class="status-text">
                                            <div class="status-title">Órdenes de Compra</div>
                                            <div class="status-value"><?php echo $kpi_ordenes_compra; ?> activas</div>
                                        </div>
                                    </div>
                                    <div class="status-badge info">OK</div>
                                </div>
                                
                                <div class="status-item">
                                    <div class="status-info">
                                        <i class="fas fa-truck status-icon"></i>
                                        <div class="status-text">
                                            <div class="status-title">Vehículos Activos</div>
                                            <div class="status-value"><?php echo $kpi_vehiculos; ?> de 20</div>
                                        </div>
                                    </div>
                                    <div class="status-badge success"><?php echo round(($kpi_vehiculos / 20) * 100); ?>%</div>
                                </div>
                                
                                <div class="status-item">
                                    <div class="status-info">
                                        <i class="fas fa-user-tie status-icon"></i>
                                        <div class="status-text">
                                            <div class="status-title">Conductores</div>
                                            <div class="status-value"><?php echo $kpi_conductores; ?> disponibles</div>
                                        </div>
                                    </div>
                                    <div class="status-badge success">OK</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Gráfico de Actividad -->
                    <div class="card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Actividad Semanal</h3>
                        </div>
                        <div class="card-body">
                            <div class="chart-container" style="height: 220px;">
                                <canvas id="activityChart"></canvas>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </div>

</div>

<!-- Incluir Chart.js desde CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
/* Dashboard Principal */
.logistica-dashboard {
    min-height: 100vh;
}
</style>

<script>
// Función para actualizar el dashboard
function refreshDashboard() {
    const btn = event.target.closest('button');
    const originalText = btn.innerHTML;
    
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';
    btn.disabled = true;
    
    // Simular actualización
    setTimeout(() => {
        location.reload();
    }, 1000);
}

// Inicializar gráfico de actividad
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('activityChart');
    if (ctx) {
        const activityChart = ctx.getContext('2d');
        
        const data = {
            labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
            datasets: [
                {
                    label: 'Pedidos Procesados',
                    data: [45, 52, 38, 65, 48, 35, 28],
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: 'rgba(52, 152, 219, 1)',
                    borderWidth: 2,
                    tension: 0.4
                },
                {
                    label: 'Entregas',
                    data: [38, 45, 32, 58, 42, 30, 25],
                    backgroundColor: 'rgba(39, 174, 96, 0.2)',
                    borderColor: 'rgba(39, 174, 96, 1)',
                    borderWidth: 2,
                    tension: 0.4
                }
            ]
        };

        const config = {
            type: 'line',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            boxWidth: 12,
                            padding: 10,
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        };

        new Chart(activityChart, config);
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
