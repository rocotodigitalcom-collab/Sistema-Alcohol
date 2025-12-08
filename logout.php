<?php
// logout.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

session_start();

// Verificar si ya está deslogueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;
$audit_error = null;
$success = false;

try {
    $db = new Database();
    
    // REGISTRAR LOGOUT EN AUDITORÍA
    try {
        // Verificar si la tabla auditoria existe
        $tableExists = $db->fetchOne("
            SELECT COUNT(*) as existe 
            FROM information_schema.tables 
            WHERE table_schema = DATABASE() 
            AND table_name = 'auditoria'
        ");
        
        // Si no existe, crearla
        if (!$tableExists || $tableExists['existe'] == 0) {
            $conn = $db->getConnection();
            $createSQL = "
                CREATE TABLE IF NOT EXISTS `auditoria` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `cliente_id` int(11) DEFAULT NULL,
                    `usuario_id` int(11) DEFAULT NULL,
                    `accion` varchar(50) DEFAULT NULL,
                    `tabla_afectada` varchar(50) DEFAULT NULL,
                    `registro_id` int(11) DEFAULT NULL,
                    `detalles` text DEFAULT NULL,
                    `ip_address` varchar(45) DEFAULT NULL,
                    `user_agent` text DEFAULT NULL,
                    `created_at` datetime DEFAULT current_timestamp(),
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ";
            
            $conn->exec($createSQL);
        }
        
        // Insertar registro de auditoría
        $db->execute("
            INSERT INTO auditoria (
                cliente_id, 
                usuario_id, 
                accion, 
                tabla_afectada, 
                registro_id, 
                detalles, 
                ip_address, 
                user_agent
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ", [
            $cliente_id,
            $user_id,
            'LOGOUT',
            'usuarios',
            $user_id,
            'Cierre de sesión del sistema',
            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500)
        ]);
        
        $success = true;
        
    } catch (Exception $e) {
        $audit_error = 'Error en auditoría: ' . $e->getMessage();
        error_log('Error en auditoría de logout: ' . $e->getMessage());
        // No detenemos el logout si falla la auditoría
    }
    
} catch (Exception $e) {
    $audit_error = 'Error general: ' . $e->getMessage();
    error_log('Error en logout.php: ' . $e->getMessage());
}

// Guardar información del usuario para logs
$user_info = [
    'id' => $_SESSION['user_id'] ?? 'unknown',
    'email' => $_SESSION['user_email'] ?? 'unknown',
    'nombre' => $_SESSION['user_nombre'] ?? 'unknown',
    'cliente' => $_SESSION['cliente_nombre'] ?? 'unknown'
];

// Destruir todas las variables de sesión
$_SESSION = array();

// Si se desea destruir la sesión completamente, borre también la cookie de sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000,
        $params["path"], 
        $params["domain"], 
        $params["secure"] ?? false, 
        $params["httponly"] ?? false
    );
}

// Finalmente, destruir la sesión
session_destroy();

// Log de éxito (opcional, solo si necesitas tracking)
if (file_exists(__DIR__ . '/logs') && is_writable(__DIR__ . '/logs')) {
    $log_message = date('Y-m-d H:i:s') . " - Logout exitoso - " . 
                   "User ID: " . $user_info['id'] . " - " .
                   "Email: " . $user_info['email'] . " - " .
                   "Nombre: " . $user_info['nombre'] . " - " .
                   "Cliente: " . $user_info['cliente'] . " - " .
                   "IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . PHP_EOL;
    
    file_put_contents(__DIR__ . '/logs/access.log', $log_message, FILE_APPEND);
}

// Redirigir al login con mensaje opcional
$redirect_url = 'login.php?logout=success&t=' . time();

// Si hubo error en auditoría pero el logout fue exitoso, podemos pasar un parámetro opcional
if ($audit_error && $success) {
    $redirect_url .= '&audit_warning=1';
}

header('Location: ' . $redirect_url);
exit;
?>