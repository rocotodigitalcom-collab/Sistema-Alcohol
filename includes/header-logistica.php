<?php
// includes/header.php

// Verificar autenticación
checkAuth();

// Obtener información del usuario y cliente
$database = new Database();
$conn = $database->getConnection();

// Verificar si la conexión fue exitosa
if (!$conn) {
    die("Error de conexión a la base de datos");
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit();
}

// 🔥 CORREGIR: Asegurar que $user_query siempre esté definida
$user_query = $database->fetchOne("
    SELECT u.*, c.nombre_empresa, c.logo, c.color_primario, c.color_secundario, p.nombre_plan 
    FROM usuarios u 
    LEFT JOIN clientes c ON u.cliente_id = c.id 
    LEFT JOIN planes p ON c.plan_id = p.id 
    WHERE u.id = ?
", [$user_id]);

// Si no se encuentra el usuario, redirigir al login
if (!$user_query) {
    // 🔥 CORREGIR: Crear un array básico para evitar errores
    $user_query = [
        'nombre_empresa' => 'Empresa',
        'logo' => '',
        'color_primario' => '#84061f',
        'color_secundario' => '#427420',
        'nombre' => 'Usuario',
        'apellido' => 'Sistema',
        'rol' => 'usuario',
        'cliente_id' => null
    ];
}

// 🔥 CORREGIR: Asegurar que $notificaciones_count siempre esté definida
$notificaciones_count = 0;
if (isset($user_query['cliente_id']) && $user_query['cliente_id']) {
    $notif_result = $database->fetchOne("
        SELECT COUNT(*) as count FROM logs_notificaciones 
        WHERE cliente_id = ? AND estado = 'pendiente'
    ", [$user_query['cliente_id']]);
    
    $notificaciones_count = $notif_result ? $notif_result['count'] : 0;
}

// 🔥 CORREGIR: Definir $page_title si no está definida
if (!isset($page_title)) {
    $page_title = 'Sistema de Control de Alcohol';
}

// 🔥 CORREGIR: Definir $breadcrumbs si no está definida
if (!isset($breadcrumbs)) {
    $breadcrumbs = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME . ' - ' . $page_title; ?></title>
    
    <!-- CSS -->
	<!-- En lugar de style.css, usamos simple-style.css -->
	<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/simple-style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/responsive.css">
	<link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/dashboard-enhancements.css">
    
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- JavaScript -->
    <script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>
    
    <style>
        :root {
            --color-primary: <?php echo $user_query['color_primario'] ?? '#84061f'; ?>;
            --color-secondary: <?php echo $user_query['color_secundario'] ?? '#427420'; ?>;
            --color-success: #27ae60;
            --color-error: #e74c3c;
            --color-warning: #f39c12;
            --color-info: #3498db;
        }
    </style>
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="header-content">
                <!-- Logo y Nombre -->
                <div class="brand-section">
                    <button class="sidebar-toggle btn-icon d-md-none" onclick="toggleSidebar()">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <?php if (!empty($user_query['logo'])): ?>
                    <img src="<?php echo BASE_URL . '/assets/uploads/' . $user_query['logo']; ?>" alt="Logo" class="brand-logo">
                    <?php else: ?>
                    <div class="brand-logo default-logo">
                        <i class="fas fa-vial"></i>
                    </div>
                    <?php endif; ?>
                    
                    <div class="brand-text">
                        <h1 class="app-name"><?php echo SITE_NAME; ?></h1>
                        <span class="client-name"><?php echo htmlspecialchars($user_query['nombre_empresa'] ?? 'Empresa'); ?></span>
                    </div>
                </div>

                <!-- Menú de Usuario -->
                <div class="user-menu">
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars(($user_query['nombre'] ?? 'Usuario') . ' ' . ($user_query['apellido'] ?? 'Sistema')); ?></span>
                        <span class="user-role"><?php echo ucfirst(str_replace('_', ' ', $user_query['rol'] ?? 'usuario')); ?></span>
                    </div>
                    <div class="user-actions">
                        <button class="btn-icon" onclick="toggleNotifications()">
                            <i class="fas fa-bell"></i>
                            <?php if ($notificaciones_count > 0): ?>
                            <span class="notification-badge"><?php echo $notificaciones_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <button class="btn-icon" onclick="toggleUserMenu()">
                            <i class="fas fa-user"></i>
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <div class="main-content">
            <!-- Sidebar -->
            <?php include 'includes/sidebar-logistica.php'; ?>

            <!-- Contenido Principal -->
            <main class="content-area">
                <div class="content-header">
                    <div class="breadcrumb">
                        <?php
                        if (!empty($breadcrumbs)) {
                            $last_key = array_key_last($breadcrumbs);
                            foreach ($breadcrumbs as $key => $crumb) {
                                if ($key === $last_key) {
                                    echo '<span class="breadcrumb-text">' . htmlspecialchars($crumb) . '</span>';
                                } else {
                                    echo '<a href="' . $key . '" class="breadcrumb-link">' . htmlspecialchars($crumb) . '</a>';
                                    echo '<span class="breadcrumb-separator">/</span>';
                                }
                            }
                        }
                        ?>
                    </div>
                    <div class="content-actions">
                        <?php if (isset($page_actions)) echo $page_actions; ?>
                    </div>
                </div>

                <div class="content-body">