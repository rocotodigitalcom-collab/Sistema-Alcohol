                </div><!-- .content-body -->
            </main><!-- .content-area -->
        </div><!-- .main-content -->

        <!-- Menú de Usuario Desplegable -->
        <div id="userDropdown" class="user-dropdown" style="display: none;">
            <div class="dropdown-content">
                <div class="dropdown-header">
                    <strong><?php echo htmlspecialchars($user_query['nombre'] . ' ' . $user_query['apellido']); ?></strong>
                    <span><?php echo htmlspecialchars($user_query['email']); ?></span>
                </div>
                <div class="dropdown-items">
                    <a href="<?php echo BASE_URL; ?>/modules/configuracion/perfil.php" class="dropdown-item">
                        <i class="fas fa-user"></i>
                        Mi Perfil
                    </a>
                    <a href="<?php echo BASE_URL; ?>/modules/configuracion/" class="dropdown-item">
                        <i class="fas fa-cog"></i>
                        Configuración
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?php echo BASE_URL; ?>/logout.php" class="dropdown-item logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Cerrar Sesión
                    </a>
                </div>
            </div>
        </div>

        <!-- Notificaciones -->
        <div id="notificationsPanel" class="notifications-panel" style="display: none;">
            <div class="notifications-header">
                <h3>Notificaciones</h3>
                <button class="btn-icon" onclick="markAllAsRead()">
                    Marcar todas como leídas
                </button>
            </div>
            <div class="notifications-list">
                <!-- Las notificaciones se cargarán via AJAX -->
                <div class="loading-notifications">Cargando notificaciones...</div>
            </div>
        </div>
    </div><!-- .app-container -->

    <!-- Scripts JavaScript -->
    <script>
        // Funciones del sidebar
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
            
            if (isCollapsed) {
                sidebar.classList.remove('sidebar-collapsed');
                document.cookie = "sidebar_collapsed=false; path=/; max-age=31536000";
            } else {
                sidebar.classList.add('sidebar-collapsed');
                document.cookie = "sidebar_collapsed=true; path=/; max-age=31536000";
            }
        }

        function toggleSubmenu(element) {
            const submenu = element.parentElement.querySelector('.submenu');
            const toggleIcon = element.querySelector('.submenu-toggle');
            
            if (submenu.style.display === 'block') {
                submenu.style.display = 'none';
                toggleIcon.classList.remove('fa-chevron-down');
                toggleIcon.classList.add('fa-chevron-right');
            } else {
                submenu.style.display = 'block';
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-down');
            }
        }

        // Funciones del usuario
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            const notifications = document.getElementById('notificationsPanel');
            
            if (dropdown.style.display === 'block') {
                dropdown.style.display = 'none';
            } else {
                dropdown.style.display = 'block';
                notifications.style.display = 'none';
            }
        }

        function toggleNotifications() {
            const notifications = document.getElementById('notificationsPanel');
            const dropdown = document.getElementById('userDropdown');
            
            if (notifications.style.display === 'block') {
                notifications.style.display = 'none';
            } else {
                notifications.style.display = 'block';
                dropdown.style.display = 'none';
                loadNotifications();
            }
        }

        function loadNotifications() {
            // Cargar notificaciones via AJAX
            fetch('<?php echo BASE_URL; ?>/includes/ajax/notifications.php')
                .then(response => response.json())
                .then(data => {
                    const container = document.querySelector('.notifications-list');
                    if (data.length > 0) {
                        container.innerHTML = data.map(notif => `
                            <div class="notification-item ${notif.estado}">
                                <div class="notification-content">
                                    <strong>${notif.asunto}</strong>
                                    <p>${notif.mensaje}</p>
                                    <small>${notif.fecha_envio}</small>
                                </div>
                            </div>
                        `).join('');
                    } else {
                        container.innerHTML = '<div class="no-notifications">No hay notificaciones</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                });
        }

        function markAllAsRead() {
            // Marcar todas como leídas via AJAX
            fetch('<?php echo BASE_URL; ?>/includes/ajax/notifications.php?action=mark_read')
                .then(() => {
                    loadNotifications();
                    // Actualizar contador
                    document.querySelectorAll('.notification-badge').forEach(badge => {
                        badge.style.display = 'none';
                    });
                });
        }

        // Cerrar dropdowns al hacer click fuera
        document.addEventListener('click', function(event) {
            const userDropdown = document.getElementById('userDropdown');
            const notificationsPanel = document.getElementById('notificationsPanel');
            const userMenuBtn = document.querySelector('.user-actions .btn-icon:last-child');
            const notificationsBtn = document.querySelector('.user-actions .btn-icon:first-child');

            if (!userDropdown.contains(event.target) && !userMenuBtn.contains(event.target)) {
                userDropdown.style.display = 'none';
            }

            if (!notificationsPanel.contains(event.target) && !notificationsBtn.contains(event.target)) {
                notificationsPanel.style.display = 'none';
            }
        });

        // Mostrar mensajes flash
        <?php if (isset($_SESSION['flash_message'])): ?>
        showNotification('<?php echo $_SESSION['flash_message']['text']; ?>', '<?php echo $_SESSION['flash_message']['type']; ?>');
        <?php unset($_SESSION['flash_message']); ?>
        <?php endif; ?>
    </script>
</body>
</html>