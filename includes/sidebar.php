<?php
// includes/sidebar.php
if (!function_exists('hasPermission')) {
    require_once __DIR__ . '/functions.php';
}

// Determinar página actual para resaltar menú activo
$current_page = basename($_SERVER['PHP_SELF']);
?>

<nav class="app-sidebar">
    <div class="sidebar-content">
        <ul class="sidebar-menu">
            
            <!-- Dashboard -->
            <li class="menu-item">
                <a href="index.php" class="menu-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>

            <!-- ===== MÓDULO PRINCIPAL: PRUEBAS DE ALCOHOL ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-vial"></i>
                    <span class="menu-text">Pruebas de Alcohol</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <!-- Realizar Pruebas -->
                    <li class="submenu-item">
                        <a href="nueva-prueba.php" class="submenu-link <?php echo $current_page == 'nueva-prueba.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i>
                            <span class="submenu-text">Nueva Prueba</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="prueba-rapida.php" class="submenu-link <?php echo $current_page == 'prueba-rapida.php' ? 'active' : ''; ?>">
                            <i class="fas fa-bolt"></i>
                            <span class="submenu-text">Prueba Rápida</span>
                        </a>
                    </li>
                    
                    <!-- Historial -->
                    <li class="submenu-item">
                        <a href="historial-pruebas.php" class="submenu-link <?php echo $current_page == 'historial-pruebas.php' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span class="submenu-text">Historial Completo</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="pruebas-aprobadas.php" class="submenu-link <?php echo $current_page == 'pruebas-aprobadas.php' ? 'active' : ''; ?>">
                            <i class="fas fa-check-circle"></i>
                            <span class="submenu-text">Pruebas Aprobadas</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="pruebas-reprobadas.php" class="submenu-link <?php echo $current_page == 'pruebas-reprobadas.php' ? 'active' : ''; ?>">
                            <i class="fas fa-times-circle"></i>
                            <span class="submenu-text">Pruebas Reprobadas</span>
                        </a>
                    </li>
                    
                    <!-- Pendientes -->
                    <li class="submenu-item">
                        <a href="pruebas-pendientes.php" class="submenu-link <?php echo $current_page == 'pruebas-pendientes.php' ? 'active' : ''; ?>">
                            <i class="fas fa-clock"></i>
                            <span class="submenu-text">Pruebas Pendientes</span>
                            <span class="badge badge-warning">3</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="retests-pendientes.php" class="submenu-link <?php echo $current_page == 'retests-pendientes.php' ? 'active' : ''; ?>">
                            <i class="fas fa-redo"></i>
                            <span class="submenu-text">Re-tests Pendientes</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== GESTIÓN DE CONDUCTORES ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-users"></i>
                    <span class="menu-text">Gestión de Conductores</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item">
                        <a href="conductores.php" class="submenu-link <?php echo $current_page == 'conductores.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span class="submenu-text">Lista de Conductores</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="registrar-conductor.php" class="submenu-link <?php echo $current_page == 'registrar-conductor.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-plus"></i>
                            <span class="submenu-text">Registrar Conductor</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="historial-conductor.php" class="submenu-link <?php echo $current_page == 'historial-conductor.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-clock"></i>
                            <span class="submenu-text">Historial por Conductor</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="conductores-bloqueados.php" class="submenu-link <?php echo $current_page == 'conductores-bloqueados.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-slash"></i>
                            <span class="submenu-text">Conductores Bloqueados</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="licencias.php" class="submenu-link <?php echo $current_page == 'licencias.php' ? 'active' : ''; ?>">
                            <i class="fas fa-id-card"></i>
                            <span class="submenu-text">Licencias y Documentos</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== VEHÍCULOS ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-car"></i>
                    <span class="menu-text">Vehículos</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item">
                        <a href="vehiculos.php" class="submenu-link <?php echo $current_page == 'vehiculos.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span class="submenu-text">Lista de Vehículos</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="registrar-vehiculo.php" class="submenu-link <?php echo $current_page == 'registrar-vehiculo.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i>
                            <span class="submenu-text">Registrar Vehículo</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="vehiculos-mantenimiento.php" class="submenu-link <?php echo $current_page == 'vehiculos-mantenimiento.php' ? 'active' : ''; ?>">
                            <i class="fas fa-tools"></i>
                            <span class="submenu-text">Vehículos en Mantenimiento</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="historial-vehiculo.php" class="submenu-link <?php echo $current_page == 'historial-vehiculo.php' ? 'active' : ''; ?>">
                            <i class="fas fa-history"></i>
                            <span class="submenu-text">Historial por Vehículo</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== ALCOHOLÍMETROS ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="menu-text">Alcoholímetros</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <li class="submenu-item">
                        <a href="alcoholimetros.php" class="submenu-link <?php echo $current_page == 'alcoholimetros.php' ? 'active' : ''; ?>">
                            <i class="fas fa-list"></i>
                            <span class="submenu-text">Inventario</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="registrar-alcoholimetro.php" class="submenu-link <?php echo $current_page == 'registrar-alcoholimetro.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plus-circle"></i>
                            <span class="submenu-text">Registrar Alcoholímetro</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="calibraciones.php" class="submenu-link <?php echo $current_page == 'calibraciones.php' ? 'active' : ''; ?>">
                            <i class="fas fa-wrench"></i>
                            <span class="submenu-text">Calibraciones</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="proximas-calibraciones.php" class="submenu-link <?php echo $current_page == 'proximas-calibraciones.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="submenu-text">Próximas Calibraciones</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="estados-alcoholimetros.php" class="submenu-link <?php echo $current_page == 'estados-alcoholimetros.php' ? 'active' : ''; ?>">
                            <i class="fas fa-info-circle"></i>
                            <span class="submenu-text">Estados y Mantenimiento</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="codigos-qr.php" class="submenu-link <?php echo $current_page == 'codigos-qr.php' ? 'active' : ''; ?>">
                            <i class="fas fa-qrcode"></i>
                            <span class="submenu-text">Códigos QR</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== REPORTES Y ANÁLISIS ===== -->
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-chart-bar"></i>
                    <span class="menu-text">Reportes y Análisis</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <!-- Reportes de Pruebas -->
                    <li class="submenu-item">
                        <a href="reportes-pruebas.php" class="submenu-link <?php echo $current_page == 'reportes-pruebas.php' ? 'active' : ''; ?>">
                            <i class="fas fa-file-contract"></i>
                            <span class="submenu-text">Reportes de Pruebas</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="reportes-fecha.php" class="submenu-link <?php echo $current_page == 'reportes-fecha.php' ? 'active' : ''; ?>">
                            <i class="fas fa-calendar"></i>
                            <span class="submenu-text">Por Fecha</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="reportes-conductor.php" class="submenu-link <?php echo $current_page == 'reportes-conductor.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user"></i>
                            <span class="submenu-text">Por Conductor</span>
                        </a>
                    </li>
                    
                    <!-- Reportes Gerenciales -->
                    <li class="submenu-item">
                        <a href="reportes-gerenciales.php" class="submenu-link <?php echo $current_page == 'reportes-gerenciales.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-line"></i>
                            <span class="submenu-text">Reportes Gerenciales</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="estadisticas-mensuales.php" class="submenu-link <?php echo $current_page == 'estadisticas-mensuales.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-pie"></i>
                            <span class="submenu-text">Estadísticas Mensuales</span>
                        </a>
                    </li>
                    <li class="submenu-item">
                        <a href="kpi.php" class="submenu-link <?php echo $current_page == 'kpi.php' ? 'active' : ''; ?>">
                            <i class="fas fa-chart-bar"></i>
                            <span class="submenu-text">Indicadores KPI</span>
                        </a>
                    </li>
                    
                    <!-- Exportar -->
                    <li class="submenu-item">
                        <a href="exportar-datos.php" class="submenu-link <?php echo $current_page == 'exportar-datos.php' ? 'active' : ''; ?>">
                            <i class="fas fa-download"></i>
                            <span class="submenu-text">Exportar Datos</span>
                        </a>
                    </li>
                </ul>
            </li>

            <!-- ===== ALERTAS ===== -->
            <li class="menu-item">
                <a href="alertas.php" class="menu-link <?php echo $current_page == 'alertas.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span class="menu-text">Alertas</span>
                    <?php if ($notificaciones_count > 0): ?>
                    <span class="badge badge-danger"><?php echo $notificaciones_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>

            <!-- ===== CONFIGURACIÓN (Solo Admin) ===== -->
            <?php if (hasPermission('admin')): ?>
            <li class="menu-item has-submenu">
                <a href="#" class="menu-link">
                    <i class="fas fa-cogs"></i>
                    <span class="menu-text">Configuración</span>
                    <i class="fas fa-chevron-down submenu-toggle"></i>
                </a>
                <ul class="submenu">
                    <!-- Configuración General -->
                    <li class="submenu-item">
                        <a href="configuracion-general.php" class="submenu-link <?php echo $current_page == 'configuracion-general.php' ? 'active' : ''; ?>">
                            <i class="fas fa-sliders-h"></i>
                            <span class="submenu-text">Configuración General</span>
                        </a>
                    </li>
                    
                    <!-- Usuarios y Roles -->
                    <li class="submenu-item">
                        <a href="usuarios.php" class="submenu-link <?php echo $current_page == 'usuarios.php' ? 'active' : ''; ?>">
                            <i class="fas fa-user-cog"></i>
                            <span class="submenu-text">Usuarios y Roles</span>
                        </a>
                    </li>
                    
                    <!-- Notificaciones -->
                    <li class="submenu-item">
                        <a href="configuracion-notificaciones.php" class="submenu-link <?php echo $current_page == 'configuracion-notificaciones.php' ? 'active' : ''; ?>">
                            <i class="fas fa-bell"></i>
                            <span class="submenu-text">Notificaciones</span>
                        </a>
                    </li>
                    
                    <!-- Personalización -->
                    <li class="submenu-item">
                        <a href="personalizacion.php" class="submenu-link <?php echo $current_page == 'personalizacion.php' ? 'active' : ''; ?>">
                            <i class="fas fa-palette"></i>
                            <span class="submenu-text">Personalización</span>
                        </a>
                    </li>
                    
                    <!-- Integraciones -->
                    <li class="submenu-item">
                        <a href="integraciones.php" class="submenu-link <?php echo $current_page == 'integraciones.php' ? 'active' : ''; ?>">
                            <i class="fas fa-plug"></i>
                            <span class="submenu-text">Integraciones</span>
                        </a>
                    </li>
                    
                    <!-- Seguridad y Auditoría -->
                    <li class="submenu-item">
                        <a href="auditoria.php" class="submenu-link <?php echo $current_page == 'auditoria.php' ? 'active' : ''; ?>">
                            <i class="fas fa-shield-alt"></i>
                            <span class="submenu-text">Seguridad y Auditoría</span>
                        </a>
                    </li>
                    
                    <!-- Backups -->
                    <li class="submenu-item">
                        <a href="backups.php" class="submenu-link <?php echo $current_page == 'backups.php' ? 'active' : ''; ?>">
                            <i class="fas fa-database"></i>
                            <span class="submenu-text">Backups</span>
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <!-- ===== MI CUENTA ===== -->
            <li class="menu-item">
                <a href="mi-cuenta.php" class="menu-link <?php echo $current_page == 'mi-cuenta.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i>
                    <span class="menu-text">Mi Cuenta</span>
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