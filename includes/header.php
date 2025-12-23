<?php
// includes/header.php

checkAuth();

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/Database.php';

$database = new Database();
$conn = $database->getConnection();

if (!$conn) {
    die('Error de conexión a la base de datos');
}

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit;
}

// ===============================
// DATOS DEL USUARIO / CLIENTE
// ===============================
$user_query = $database->fetchOne("
    SELECT 
        u.*,
        c.nombre_empresa,
        c.logo,
        c.color_primario,
        c.color_secundario,
        p.nombre_plan
    FROM usuarios u
    LEFT JOIN clientes c ON u.cliente_id = c.id
    LEFT JOIN planes p ON c.plan_id = p.id
    WHERE u.id = ?
", [$user_id]);

if (!$user_query) {
    $user_query = [
        'nombre' => 'Usuario',
        'apellido' => 'Sistema',
        'rol' => 'usuario',
        'nombre_empresa' => 'Empresa',
        'logo' => '',
        'color_primario' => '#84061f',
        'color_secundario' => '#427420',
        'cliente_id' => null
    ];
}

// ===============================
// NOTIFICACIONES
// ===============================
$notificaciones_count = 0;

if (!empty($user_query['cliente_id'])) {
    $notif = $database->fetchOne("
        SELECT COUNT(*) AS total
        FROM logs_notificaciones
        WHERE cliente_id = ? AND estado = 'pendiente'
    ", [$user_query['cliente_id']]);

    $notificaciones_count = $notif['total'] ?? 0;
}

// ===============================
// VARIABLES POR DEFECTO
// ===============================
$page_title = $page_title ?? 'Sistema de Control de Alcohol';
$breadcrumbs = $breadcrumbs ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= SITE_NAME . ' - ' . $page_title ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/simple-style.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/responsive.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/dashboard-enhancements.css">

    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- JS -->
    <script src="<?= BASE_URL ?>/assets/js/main.js" defer></script>

    <style>
        :root {
            --color-primary: <?= $user_query['color_primario'] ?>;
            --color-secondary: <?= $user_query['color_secundario'] ?>;
        }
    </style>
</head>
<body>

<div class="app-container">

    <!-- ================= HEADER ================= -->
    <header class="app-header">
        <div class="header-content">

            <!-- BRAND -->
            <div class="brand-section">
                <button class="sidebar-toggle btn-icon d-md-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>

                <?php if (!empty($user_query['logo'])): ?>
                    <img src="<?= BASE_URL ?>/assets/uploads/<?= $user_query['logo'] ?>" class="brand-logo">
                <?php else: ?>
                    <div class="brand-logo default-logo">
                        <i class="fas fa-vial"></i>
                    </div>
                <?php endif; ?>

                <div class="brand-text">
                    <h1 class="app-name"><?= SITE_NAME ?></h1>
                    <span class="client-name"><?= htmlspecialchars($user_query['nombre_empresa']) ?></span>
                </div>
            </div>

            <!-- USER MENU -->
            <div class="user-menu">

                <div class="user-info">
                    <span class="user-name">
                        <?= htmlspecialchars($user_query['nombre'] . ' ' . $user_query['apellido']) ?>
                    </span>
                    <span class="user-role">
                        <?= ucfirst(str_replace('_', ' ', $user_query['rol'])) ?>
                    </span>
                </div>

                <div class="user-actions">
                    <button class="btn-icon" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificaciones_count > 0): ?>
                            <span class="notification-badge"><?= $notificaciones_count ?></span>
                        <?php endif; ?>
                    </button>

                    <button class="btn-icon" onclick="toggleUserMenu()">
                        <i class="fas fa-user"></i>
                    </button>
                </div>

                <!-- DROPDOWN -->
                <div class="user-menu-dropdown">
                    <a href="<?= BASE_URL ?>/mi-cuenta.php">
                        <i class="fas fa-user-circle"></i> Mi Perfil
                    </a>
                    <a href="<?= BASE_URL ?>/configuracion.php">
                        <i class="fas fa-cog"></i> Configuración
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="<?= BASE_URL ?>/logout.php">
                        <i class="fas fa-sign-out-alt"></i> Cerrar sesión
                    </a>
                </div>

            </div>
        </div>
    </header>

    <!-- ================= MAIN ================= -->
    <div class="main-content">

        <!-- SIDEBAR -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- CONTENT -->
        <main class="content-area">

            <div class="content-header">
                <div class="breadcrumb">
                    <?php
                    if ($breadcrumbs) {
                        $last = array_key_last($breadcrumbs);
                        foreach ($breadcrumbs as $url => $label) {
                            if ($url === $last) {
                                echo '<span class="breadcrumb-text">' . htmlspecialchars($label) . '</span>';
                            } else {
                                echo '<a href="' . $url . '" class="breadcrumb-link">' . htmlspecialchars($label) . '</a>';
                                echo '<span class="breadcrumb-separator">/</span>';
                            }
                        }
                    }
                    ?>
                </div>
            </div>

            <div class="content-body">
