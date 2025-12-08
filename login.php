<?php
// Mostrar todos los errores desde el principio
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

try {
    session_start();
    
    // Redirigir si ya está logueado
    if (isset($_SESSION['user_id'])) {
        header('Location: index.php');
        exit;
    }
    
    $error = '';
    $success = '';
    $step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
    
    // Procesar login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['reset_request'])) {
            // Paso 1: Solicitar correo para restablecer
            $email = trim($_POST['reset_email']);
            
            if (!empty($email)) {
                try {
                    require_once 'config.php';
                    require_once __DIR__ . '/includes/Database.php';
                    
                    $db = new Database();
                    $conn = $db->getConnection();
                    
                    // Verificar si el email existe
                    $user = $db->fetchOne("SELECT id, email, nombre FROM usuarios WHERE email = ?", [$email]);
                    
                    if ($user) {
                        // Guardar email en sesión para el siguiente paso
                        $_SESSION['reset_email'] = $email;
                        $step = 2; // Ir al paso 2
                        $success = 'Por favor, ingrese su nueva contraseña.';
                    } else {
                        $error = 'El correo electrónico no está registrado en el sistema.';
                    }
                } catch (Exception $e) {
                    $error = 'Error del sistema: ' . $e->getMessage();
                }
            } else {
                $error = 'Por favor, ingrese su correo electrónico.';
            }
        } 
        elseif (isset($_POST['reset_password'])) {
            // Paso 2: Cambiar contraseña
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (!empty($new_password) && !empty($confirm_password)) {
                if ($new_password === $confirm_password) {
                    if (strlen($new_password) >= 6) {
                        try {
                            require_once 'config.php';
                            require_once __DIR__ . '/includes/Database.php';
                            
                            $db = new Database();
                            $conn = $db->getConnection();
                            
                            // Obtener email de la sesión
                            $email = $_SESSION['reset_email'] ?? '';
                            
                            if (!empty($email)) {
                                // Hashear nueva contraseña
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                
                                // Actualizar contraseña en la base de datos
                                $result = $db->execute(
                                    "UPDATE usuarios SET password = ? WHERE email = ?",
                                    [$hashed_password, $email]
                                );
                                
                                if ($result) {
                                    // Limpiar sesión de recuperación
                                    unset($_SESSION['reset_email']);
                                    $step = 1;
                                    $success = '¡Contraseña actualizada correctamente! Ahora puede iniciar sesión con su nueva contraseña.';
                                } else {
                                    $error = 'Error al actualizar la contraseña.';
                                }
                            } else {
                                $error = 'Sesión expirada. Por favor, solicite nuevamente el restablecimiento.';
                                $step = 1;
                            }
                        } catch (Exception $e) {
                            $error = 'Error del sistema: ' . $e->getMessage();
                        }
                    } else {
                        $error = 'La contraseña debe tener al menos 6 caracteres.';
                    }
                } else {
                    $error = 'Las contraseñas no coinciden.';
                }
            } else {
                $error = 'Por favor, complete todos los campos.';
            }
        }
        else {
            // Procesar login normal
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            
            if (!empty($email) && !empty($password)) {
                try {
                    require_once 'config.php';
                    require_once __DIR__ . '/includes/Database.php';
                    
                    $db = new Database();
                    $conn = $db->getConnection();
                    
                    // Buscar usuario
                    $user = $db->fetchOne("
                        SELECT u.*, c.nombre_empresa, c.plan_id, c.estado as cliente_estado 
                        FROM usuarios u 
                        LEFT JOIN clientes c ON u.cliente_id = c.id 
                        WHERE u.email = ? AND u.estado = 1
                    ", [$email]);
                    
                    if ($user && password_verify($password, $user['password'])) {
                        // Establecer sesión
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_email'] = $user['email'];
                        $_SESSION['user_nombre'] = $user['nombre'];
                        $_SESSION['user_apellido'] = $user['apellido'];
                        $_SESSION['user_role'] = $user['rol'];
                        $_SESSION['cliente_id'] = $user['cliente_id'];
                        $_SESSION['cliente_nombre'] = $user['nombre_empresa'];
                        $_SESSION['user_permissions'] = ['all']; // Temporal
                        
                        header('Location: index.php');
                        exit;
                    } else {
                        $error = 'Credenciales incorrectas.';
                    }
                } catch (PDOException $e) {
                    $error = 'Error de base de datos: ' . $e->getMessage();
                } catch (Exception $e) {
                    $error = 'Error del sistema: ' . $e->getMessage();
                }
            } else {
                $error = 'Por favor, complete todos los campos.';
            }
        }
    }
} catch (Exception $e) {
    $error = 'Error inicial: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $step === 2 ? 'Nueva Contraseña' : ($step === 1 && isset($_GET['forgot']) ? 'Recuperar Contraseña' : 'Login'); ?> - AlcoholControl</title>
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #1c3f95 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
            padding: 40px;
            position: relative;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #2a5298, #1e3c72);
        }
        
        .error { 
            background: #ffe8e8; 
            color: #b30000; 
            padding: 12px; 
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #ffb3b3;
            font-size: 14px;
        }
        
        .success { 
            background: #e8f7ef; 
            color: #006622; 
            padding: 12px; 
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #b3e6cc;
            font-size: 14px;
        }
        
        .info { 
            background: #e8f4ff; 
            color: #0056b3; 
            padding: 12px; 
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #b3d7ff;
            font-size: 14px;
        }
        
        .form-group { 
            margin-bottom: 25px; 
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            color: #333;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #2a5298;
        }
        
        input[type="email"] {
            background: #f8f8f8;
            color: #0066cc;
        }
        
        input[type="password"] {
            letter-spacing: 3px;
            font-size: 20px;
        }
        
        input[type="password"]::placeholder {
            letter-spacing: 1px;
            color: #999;
        }
        
        button {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #2a5298, #1e3c72);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            margin-top: 10px;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(42, 82, 152, 0.3);
        }
        
        .secondary-button {
            background: linear-gradient(135deg, #f0f0f0, #e0e0e0);
            color: #333;
        }
        
        .secondary-button:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .forgot-password {
            text-align: center;
            margin: 20px 0;
        }
        
        .forgot-password a {
            color: #2a5298;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .forgot-password a:hover {
            color: #1e3c72;
            text-decoration: underline;
        }
        
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-to-login a {
            color: #2a5298;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .back-to-login a:hover {
            color: #1e3c72;
            text-decoration: underline;
        }
        
        .demo-credentials {
            background: #f0f8ff;
            border-radius: 8px;
            padding: 20px;
            margin-top: 25px;
            border-left: 4px solid #2a5298;
        }
        
        .demo-credentials h3 {
            color: #333;
            font-size: 16px;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .demo-credentials p {
            color: #666;
            font-size: 14px;
            margin: 5px 0;
            font-family: monospace;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #1e3c72;
            font-size: 24px;
            margin-bottom: 8px;
            font-weight: 700;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
            position: relative;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .header p::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, #2a5298, #1e3c72);
        }
        
        .form-divider {
            border: none;
            height: 1px;
            background: linear-gradient(90deg, transparent, #e0e0e0, transparent);
            margin: 25px 0;
        }
        
        .instructions {
            background: #f5f9ff;
            border-radius: 8px;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
            color: #555;
            line-height: 1.5;
            border: 1px solid #e0e0e0;
        }
        
        .password-requirements {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin: 10px 0;
            font-size: 13px;
            color: #666;
            line-height: 1.4;
            border: 1px solid #e9ecef;
        }
        
        .password-requirements ul {
            margin-left: 20px;
            margin-top: 5px;
        }
        
        .password-requirements li {
            margin-bottom: 3px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .step {
            flex: 1;
            text-align: center;
            padding: 10px;
            position: relative;
        }
        
        .step-number {
            width: 30px;
            height: 30px;
            background: #e0e0e0;
            color: #666;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
        }
        
        .step.active .step-number {
            background: #2a5298;
            color: white;
        }
        
        .step-label {
            font-size: 12px;
            color: #666;
        }
        
        .step.active .step-label {
            color: #2a5298;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="header">
            <h1>Sistema Integral de Alcoholímetros</h1>
            <p>Sistema de Gestión de Alcoholmetría</p>
        </div>
        
        <hr class="form-divider">
        
        <?php if ($error): ?>
        <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['forgot']) || $step === 2): ?>
        <!-- Formulario de recuperación de contraseña -->
        <?php if ($step === 2 && isset($_SESSION['reset_email'])): ?>
        <!-- Paso 2: Nueva contraseña -->
        <div class="step-indicator">
            <div class="step active">
                <div class="step-number">1</div>
                <div class="step-label">Verificar Email</div>
            </div>
            <div class="step active">
                <div class="step-number">2</div>
                <div class="step-label">Nueva Contraseña</div>
            </div>
        </div>
        
        <div class="instructions">
            <strong>Establecer nueva contraseña:</strong> Para el correo <strong><?php echo htmlspecialchars($_SESSION['reset_email']); ?></strong>
        </div>
        
        <div class="password-requirements">
            <strong>Requisitos de la contraseña:</strong>
            <ul>
                <li>Mínimo 6 caracteres</li>
                <li>Se recomienda usar mayúsculas, minúsculas y números</li>
            </ul>
        </div>
        
        <form method="POST">
            <input type="hidden" name="reset_password" value="1">
            
            <div class="form-group">
                <label for="new_password">Nueva Contraseña</label>
                <input type="password" id="new_password" name="new_password" placeholder="············" required>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirmar Contraseña</label>
                <input type="password" id="confirm_password" name="confirm_password" placeholder="············" required>
            </div>
            
            <button type="submit">Cambiar Contraseña</button>
        </form>
        
        <div class="back-to-login">
            <a href="login.php">← Volver al inicio de sesión</a>
        </div>
        
        <?php else: ?>
        <!-- Paso 1: Solicitar correo -->
        <div class="step-indicator">
            <div class="step active">
                <div class="step-number">1</div>
                <div class="step-label">Verificar Email</div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-label">Nueva Contraseña</div>
            </div>
        </div>
        
        <div class="instructions">
            <strong>Recuperación de contraseña:</strong> Ingrese su correo electrónico registrado para restablecer su contraseña.
        </div>
        
        <form method="POST">
            <input type="hidden" name="reset_request" value="1">
            
            <div class="form-group">
                <label for="reset_email">Correo Electrónico</label>
                <input type="email" id="reset_email" name="reset_email" placeholder="correo@empresa.com" required>
            </div>
            
            <button type="submit">Continuar</button>
        </form>
        
        <div class="back-to-login">
            <a href="login.php">← Volver al inicio de sesión</a>
        </div>
        <?php endif; ?>
        
        <?php else: ?>
        <!-- Formulario de login normal -->
        <form method="POST">
            <div class="form-group">
                <label for="email">Correo Electrónico</label>
                <input type="email" id="email" name="email" placeholder="correo@empresa.com" required>
            </div>
            
            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" placeholder="············" required>
            </div>
            
            <button type="submit">Iniciar Sesión</button>
        </form>
        
        <div class="forgot-password">
            <a href="login.php?forgot=1">¿Olvidaste tu contraseña?</a>
        </div>
        
        <hr class="form-divider">
        
        <div class="demo-credentials">
            <h3>Credenciales Demos</h3>
            <p>Email: admin@demos.com</p>
            <p>Contraseña: password</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>