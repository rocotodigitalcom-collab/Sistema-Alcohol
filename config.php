<?php
// config.php - EN LA RAÍZ DEL SITIO

// Verificar si la sesión ya está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configuración de la base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'juegosd2_alcohol');
define('DB_USER', 'juegosd2_alcohol');
define('DB_PASS', '#Peru07128020@');

// Configuración del sitio
define('SITE_NAME', 'Sistema de Control de Alcohol');
define('BASE_URL', 'https://alcohol.rocotodigital.com');
define('DEFAULT_COLOR_PRIMARY', '#84061f');
define('DEFAULT_COLOR_SECONDARY', '#427420');

// 🔥 CORRECCIÓN: Ruta correcta desde la raíz
$functions_path = __DIR__ . '/includes/functions.php';

if (file_exists($functions_path)) {
    require_once $functions_path;
} else {
    // Si no existe el archivo, creamos funciones básicas
    function checkAuth() {
        if (!isset($_SESSION['user_id'])) {
            // Para testing, simular usuario logueado
            $_SESSION['user_id'] = 1;
            $_SESSION['user_role'] = 'admin';
        }
        return true;
    }
    
    function hasPermission($permission) {
        return true; // Permitir todo temporalmente
    }
}
?>