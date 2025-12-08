<?php
// includes/sidebar-logistica.php
if (!function_exists('hasPermission')) {
    require_once __DIR__ . '/functions.php';
}

// Determinar página actual para resaltar menú activo
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="app-sidebar">
    <div class="sidebar-content">
        <ul class="sidebar-menu">
            
            <!-- Dashboard Logístico -->
            <li class="menu-item">
                <a href="dashboard-logistico.php" class="menu-link <?php echo $current_page == 'dashboard-logistico.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span class="menu-text">Dashboard Logístico</span>
                </a>
            </li>

            <!-- ===== INVENTARIOS ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-boxes"></i>
                    <span class="menu-text">Inventarios</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item">
                        <a href="stock-tiempo-real.php" class="submenu-link <?php echo $current_page == 'stock-tiempo-real.php' ? 'active' : ''; ?>">
                            <i class="fas fa-warehouse"></i>
                            <span class="submenu-text">Stock en Tiempo Real</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="kardex.php" class="submenu-link <?php echo $current_page == 'kardex.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span class="submenu-text">Kardex</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="lotes-vencimiento.php" class="submenu-link <?php echo $current_page == 'lotes-vencimiento.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tags"></i>
                            <span class="submenu-text">Lotes / Vencimiento</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="ubicaciones.php" class="submenu-link <?php echo $current_page == 'ubicaciones.php' ? 'active' : ''; ?>">
                            <i class="fas fa-map"></i>
                            <span class="submenu-text">Ubicaciones</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="inventario-fisico.php" class="submenu-link <?php echo $current_page == 'inventario-fisico.php' ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-check"></i>
                            <span class="submenu-text">Inventario Físico</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="ajustes-inventario.php" class="submenu-link <?php echo $current_page == 'ajustes-inventario.php' ? 'active' : ''; ?>">
                            <i class="fas fa-edit"></i>
                            <span class="submenu-text">Ajustes de Inventario</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="transferencias.php" class="submenu-link <?php echo $current_page == 'transferencias.php' ? 'active' : ''; ?>">
                            <i class="fas fa-exchange-alt"></i>
                            <span class="submenu-text">Transferencias</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== COMPRAS ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-shopping-cart"></i>
                    <span class="menu-text">Compras</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item">
                        <a href="solicitudes-compra.php" class="submenu-link <?php echo $current_page == 'solicitudes-compra.php' ? 'active' : ''; ?>">
                            <i class="fas fa-file-alt"></i>
                            <span class="submenu-text">Solicitudes de Compra</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="ordenes-compra.php" class="submenu-link <?php echo $current_page == 'ordenes-compra.php' ? 'active' : ''; ?>">
                            <i class="fas fa-file-invoice-dollar"></i>
                            <span class="submenu-text">Órdenes de Compra</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="proveedores.php" class="submenu-link <?php echo $current_page == 'proveedores.php' ? 'active' : ''; ?>">
                            <i class="fas fa-handshake"></i>
                            <span class="submenu-text">Proveedores</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="cotizaciones.php" class="submenu-link <?php echo $current_page == 'cotizaciones.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calculator"></i>
                            <span class="submenu-text">Cotizaciones</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="recepciones.php" class="submenu-link <?php echo $current_page == 'recepciones.php' ? 'active' : ''; ?>">
                            <i class="fas fa-truck-loading"></i>
                            <span class="submenu-text">Recepciones</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="notas-credito.php" class="submenu-link <?php echo $current_page == 'notas-credito.php' ? 'active' : ''; ?>">
                            <i class="fas fa-file-invoice"></i>
                            <span class="submenu-text">Notas de Crédito</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== PEDIDOS ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-dolly"></i>
                    <span class="menu-text">Pedidos</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item">
                        <a href="pedidos-recibidos.php" class="submenu-link <?php echo $current_page == 'pedidos-recibidos.php' ? 'active' : ''; ?>">
                            <i class="fas fa-box-open"></i>
                            <span class="submenu-text">Pedidos Recibidos</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="picking.php" class="submenu-link <?php echo $current_page == 'picking.php' ? 'active' : ''; ?>">
                            <i class="fas fa-hand-holding-box"></i>
                            <span class="submenu-text">Picking</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="packing.php" class="submenu-link <?php echo $current_page == 'packing.php' ? 'active' : ''; ?>">
                            <i class="fas fa-box"></i>
                            <span class="submenu-text">Packing</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="despachos.php" class="submenu-link <?php echo $current_page == 'despachos.php' ? 'active' : ''; ?>">
                            <i class="fas fa-shipping-fast"></i>
                            <span class="submenu-text">Despachos</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="devoluciones.php" class="submenu-link <?php echo $current_page == 'devoluciones.php' ? 'active' : ''; ?>">
                            <i class="fas fa-exchange-alt"></i>
                            <span class="submenu-text">Devoluciones</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="pedidos-cancelados.php" class="submenu-link <?php echo $current_page == 'pedidos-cancelados.php' ? 'active' : ''; ?>">
                            <i class="fas fa-ban"></i>
                            <span class="submenu-text">Pedidos Cancelados</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== TRANSPORTE ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-truck"></i>
                    <span class="menu-text">Transporte</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item">
                        <a href="rutas.php" class="submenu-link <?php echo $current_page == 'rutas.php' ? 'active' : ''; ?>">
                            <i class="fas fa-route"></i>
                            <span class="submenu-text">Rutas</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="hojas-ruta.php" class="submenu-link <?php echo $current_page == 'hojas-ruta.php' ? 'active' : ''; ?>">
                            <i class="fas fa-map-marked-alt"></i>
                            <span class="submenu-text">Hojas de Ruta</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="tracking.php" class="submenu-link <?php echo $current_page == 'tracking.php' ? 'active' : ''; ?>">
                            <i class="fas fa-map-marker-alt"></i>
                            <span class="submenu-text">Track & Trace</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="pod.php" class="submenu-link <?php echo $current_page == 'pod.php' ? 'active' : ''; ?>">
                            <i class="fas fa-clipboard-check"></i>
                            <span class="submenu-text">POD (Proof of Delivery)</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="incidencias.php" class="submenu-link <?php echo $current_page == 'incidencias.php' ? 'active' : ''; ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span class="submenu-text">Incidencias</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="transportistas.php" class="submenu-link <?php echo $current_page == 'transportistas.php' ? 'active' : ''; ?>">
                            <i class="fas fa-truck-moving"></i>
                            <span class="submenu-text">Transportistas</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== FLOTA ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-car"></i>
                    <span class="menu-text">Flota</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item">
                        <a href="vehiculos-flota.php" class="submenu-link <?php echo $current_page == 'vehiculos-flota.php' ? 'active' : ''; ?>">
                            <i class="fas fa-car-side"></i>
                            <span class="submenu-text">Vehículos</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="conductores-flota.php" class="submenu-link <?php echo $current_page == 'conductores-flota.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-tie"></i>
                            <span class="submenu-text">Conductores</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="mantenimientos.php" class="submenu-link <?php echo $current_page == 'mantenimientos.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tools"></i>
                            <span class="submenu-text">Mantenimientos</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="combustible.php" class="submenu-link <?php echo $current_page == 'combustible.php' ? 'active' : ''; ?>">
                            <i class="fas fa-gas-pump"></i>
                            <span class="submenu-text">Combustible</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="documentos-vehiculos.php" class="submenu-link <?php echo $current_page == 'documentos-vehiculos.php' ? 'active' : ''; ?>">
                            <i class="fas fa-file-contract"></i>
                            <span class="submenu-text">Documentos</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="seguros-vehiculos.php" class="submenu-link <?php echo $current_page == 'seguros-vehiculos.php' ? 'active' : ''; ?>">
                            <i class="fas fa-shield-alt"></i>
                            <span class="submenu-text">Seguros</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== ALMACENES ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-warehouse"></i>
                    <span class="menu-text">Almacenes</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item">
                        <a href="almacenes.php" class="submenu-link <?php echo $current_page == 'almacenes.php' ? 'active' : ''; ?>">
                            <i class="fas fa-building"></i>
                            <span class="submenu-text">Lista de Almacenes</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="zonas-almacen.php" class="submenu-link <?php echo $current_page == 'zonas-almacen.php' ? 'active' : ''; ?>">
                            <i class="fas fa-th"></i>
                            <span class="submenu-text">Zonas y Estanterías</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="movimientos-almacen.php" class="submenu-link <?php echo $current_page == 'movimientos-almacen.php' ? 'active' : ''; ?>">
                            <i class="fas fa-arrows-alt"></i>
                            <span class="submenu-text">Movimientos</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="capacidad-almacen.php" class="submenu-link <?php echo $current_page == 'capacidad-almacen.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-area"></i>
                            <span class="submenu-text">Capacidad</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== REPORTES Y KPIs ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-file-alt"></i>
                    <span class="menu-text">Reportes & KPIs</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <!-- Reportes de Inventario -->
                    <li class="submenu-item">
                        <a href="reportes-inventario.php" class="submenu-link <?php echo $current_page == 'reportes-inventario.php' ? 'active' : ''; ?>">
                            <i class="fas fa-boxes"></i>
                            <span class="submenu-text">Reportes de Inventario</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="reportes-compras.php" class="submenu-link <?php echo $current_page == 'reportes-compras.php' ? 'active' : ''; ?>">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="submenu-text">Reportes de Compras</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="reportes-pedidos.php" class="submenu-link <?php echo $current_page == 'reportes-pedidos.php' ? 'active' : ''; ?>">
                            <i class="fas fa-dolly"></i>
                            <span class="submenu-text">Reportes de Pedidos</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="reportes-transporte.php" class="submenu-link <?php echo $current_page == 'reportes-transporte.php' ? 'active' : ''; ?>">
                            <i class="fas fa-truck"></i>
                            <span class="submenu-text">Reportes de Transporte</span>
                        </a>
                    </li>
                    
                    <!-- KPIs -->
                    <li class="submenu-item">
                        <a href="kpis-logisticos.php" class="submenu-link <?php echo $current_page == 'kpis-logisticos.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i>
                            <span class="submenu-text">KPIs Logísticos</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="dashboard-analytics.php" class="submenu-link <?php echo $current_page == 'dashboard-analytics.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-pie"></i>
                            <span class="submenu-text">Dashboard Analytics</span>
                        </a>
                    </li>
                    
                    <!-- Exportar -->
                    <li class="submenu-item">
                        <a href="exportar-datos-logistica.php" class="submenu-link <?php echo $current_page == 'exportar-datos-logistica.php' ? 'active' : ''; ?>">
                            <i class="fas fa-table"></i>
                            <span class="submenu-text">Exportar Datos</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== VOLVER AL SISTEMA PRINCIPAL ===== -->
            <li class="menu-item" style="margin-top: 2rem; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 1rem;">
                <a href="index.php" class="menu-link">
                    <i class="fas fa-arrow-left"></i>
                    <span class="menu-text">Volver al Dashboard Principal</span>
                </a>
            </li>

            <!-- ===== CERRAR SESIÓN ===== -->
            <li class="menu-item">
                <a href="logout.php" class="menu-link text-danger">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Cerrar Sesión</span>
                </a>
            </li>
        </ul>
    </div>
</nav>

<script>
// Toggle submenús
document.querySelectorAll('.menu-item.has-submenu .menu-link').forEach(link => {
    link.addEventListener('click', function(e) {
        // Solo si no es un enlace directo (si no tiene href o href es #)
        if (this.getAttribute('href') === '#' || !this.getAttribute('href')) {
            e.preventDefault();
            const parent = this.parentElement;
            parent.classList.toggle('open');
        }
    });
});

// Toggle sidebar en móviles
function toggleSidebar() {
    document.querySelector('.app-sidebar').classList.toggle('mobile-open');
}
</script>
