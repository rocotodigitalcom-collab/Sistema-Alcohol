<?php
// usuarios.php - VERSIÓN DEFINITIVA CORREGIDA
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Gestión de Usuarios y Roles';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'usuarios.php' => 'Gestión de Usuarios y Roles'
];

require_once __DIR__ . '/includes/header.php';

// Verificar y cargar Bootstrap si no está presente
if (!defined('BOOTSTRAP_LOADED')) {
    echo '
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    ';
    define('BOOTSTRAP_LOADED', true);
}

$db = new Database();
$user_id = $_SESSION['user_id'] ?? 0;
$cliente_id = $_SESSION['cliente_id'] ?? 0;

if ($user_id == 0) {
    echo "<div class='alert alert-danger'>Error: No has iniciado sesión</div>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Obtener lista de usuarios
try {
    $usuarios = $db->fetchAll("
        SELECT u.*, c.nombre_empresa 
        FROM usuarios u 
        LEFT JOIN clientes c ON u.cliente_id = c.id 
        WHERE u.cliente_id = ?
        ORDER BY u.fecha_creacion DESC
    ", [$cliente_id]);
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error en consulta: " . $e->getMessage() . "</div>";
    $usuarios = [];
}

// Obtener lista de roles
try {
    $roles = $db->fetchAll("SELECT * FROM roles WHERE estado = 1 ORDER BY nivel DESC");
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error cargando roles: " . $e->getMessage() . "</div>";
    $roles = [];
}

// Obtener lista de permisos
try {
    $permisos = $db->fetchAll("SELECT * FROM permisos WHERE estado = 1 ORDER BY modulo, nombre");
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>Error cargando permisos: " . $e->getMessage() . "</div>";
    $permisos = [];
}

// =============================================
// PROCESAMIENTO DE FORMULARIOS - USUARIOS
// =============================================

// Procesar crear usuario
if (isset($_POST['crear_usuario'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $rol = trim($_POST['rol'] ?? 'operador');
    
    if (empty($nombre) || empty($email)) {
        $mensaje_error = "El nombre y el email son obligatorios.";
    } else {
        try {
            $existe = $db->fetchOne("SELECT id FROM usuarios WHERE email = ?", [$email]);
            if ($existe) {
                $mensaje_error = "El email ya está registrado.";
            } else {
                $password_hash = password_hash('password123', PASSWORD_DEFAULT);
                
                $db->execute(
                    "INSERT INTO usuarios (cliente_id, nombre, apellido, email, password, telefono, dni, rol, estado, fecha_creacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())",
                    [$cliente_id, $nombre, $apellido, $email, $password_hash, $telefono, $dni, $rol]
                );
                
                $mensaje_exito = "Usuario creado correctamente. Contraseña temporal: <strong>password123</strong>";
                echo "<script>setTimeout(() => window.location.href = 'usuarios.php', 1500);</script>";
            }
        } catch (Exception $e) {
            $mensaje_error = "Error al crear el usuario: " . $e->getMessage();
        }
    }
}

// Procesar actualizar usuario
if (isset($_POST['actualizar_usuario'])) {
    $usuario_id = intval($_POST['usuario_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $dni = trim($_POST['dni'] ?? '');
    $rol = trim($_POST['rol'] ?? 'operador');
    $estado = intval($_POST['estado'] ?? 1);
    
    if (empty($nombre)) {
        $mensaje_error = "El nombre es obligatorio.";
    } else {
        try {
            // Verificar si el usuario existe antes de actualizar
            $usuario_existe = $db->fetchOne("SELECT id FROM usuarios WHERE id = ? AND cliente_id = ?", [$usuario_id, $cliente_id]);
            
            if (!$usuario_existe) {
                $mensaje_error = "El usuario no existe o no tienes permisos para editarlo.";
            } else {
                // Actualizar el usuario
                $db->execute(
                    "UPDATE usuarios SET nombre = ?, apellido = ?, telefono = ?, dni = ?, rol = ?, estado = ? WHERE id = ? AND cliente_id = ?",
                    [$nombre, $apellido, $telefono, $dni, $rol, $estado, $usuario_id, $cliente_id]
                );
                
                // Verificar si se actualizó consultando el registro
                $actualizaciones = $db->fetchOne(
                    "SELECT COUNT(*) as count FROM usuarios WHERE id = ? AND cliente_id = ? AND nombre = ? AND rol = ? AND estado = ?",
                    [$usuario_id, $cliente_id, $nombre, $rol, $estado]
                );
                
                if ($actualizaciones && $actualizaciones['count'] > 0) {
                    $mensaje_exito = "Usuario actualizado correctamente.";
                    echo "<script>setTimeout(() => window.location.href = 'usuarios.php', 1500);</script>";
                } else {
                    $mensaje_error = "No se pudo actualizar el usuario o no hubo cambios.";
                }
            }
        } catch (Exception $e) {
            $mensaje_error = "Error al actualizar el usuario: " . $e->getMessage();
        }
    }
}

// Procesar cambiar contraseña
if (isset($_POST['cambiar_password'])) {
    $usuario_id = intval($_POST['usuario_id'] ?? 0);
    $nueva_password = $_POST['nueva_password'] ?? '';
    $confirmar_password = $_POST['confirmar_password'] ?? '';
    
    if (empty($nueva_password) || empty($confirmar_password)) {
        $mensaje_error = "Ambos campos de contraseña son obligatorios.";
    } elseif ($nueva_password !== $confirmar_password) {
        $mensaje_error = "Las contraseñas no coinciden.";
    } elseif (strlen($nueva_password) < 6) {
        $mensaje_error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            // Verificar si el usuario existe
            $usuario_existe = $db->fetchOne("SELECT id FROM usuarios WHERE id = ? AND cliente_id = ?", [$usuario_id, $cliente_id]);
            
            if (!$usuario_existe) {
                $mensaje_error = "El usuario no existe o no tienes permisos para cambiarla.";
            } else {
                $password_hash = password_hash($nueva_password, PASSWORD_DEFAULT);
                
                // Obtener hash anterior para verificar si cambió
                $hash_anterior = $db->fetchOne("SELECT password FROM usuarios WHERE id = ?", [$usuario_id]);
                
                $db->execute(
                    "UPDATE usuarios SET password = ? WHERE id = ? AND cliente_id = ?",
                    [$password_hash, $usuario_id, $cliente_id]
                );
                
                // Verificar si la contraseña realmente cambió
                $nuevo_hash = $db->fetchOne("SELECT password FROM usuarios WHERE id = ?", [$usuario_id]);
                
                if ($nuevo_hash && $nuevo_hash['password'] !== $hash_anterior['password']) {
                    $mensaje_exito = "Contraseña actualizada correctamente.";
                    echo "<script>setTimeout(() => window.location.href = 'usuarios.php', 1500);</script>";
                } else {
                    $mensaje_error = "No se pudo actualizar la contraseña.";
                }
            }
        } catch (Exception $e) {
            $mensaje_error = "Error al cambiar la contraseña: " . $e->getMessage();
        }
    }
}

// =============================================
// PROCESAMIENTO DE FORMULARIOS - ROLES Y PERMISOS
// =============================================

// Procesar crear rol
if (isset($_POST['crear_rol'])) {
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $nivel = intval($_POST['nivel'] ?? 1);
    $permisos_seleccionados = $_POST['permisos'] ?? [];

    if (empty($nombre)) {
        $mensaje_error = "El nombre del rol es obligatorio.";
    } else {
        try {
            // Verificar si el rol ya existe
            $rol_existe = $db->fetchOne("SELECT id FROM roles WHERE nombre = ?", [$nombre]);
            
            if ($rol_existe) {
                $mensaje_error = "Ya existe un rol con ese nombre.";
            } else {
                // Insertar el nuevo rol
                $db->execute(
                    "INSERT INTO roles (nombre, descripcion, nivel, estado) VALUES (?, ?, ?, 1)",
                    [$nombre, $descripcion, $nivel]
                );
                
                $rol_id = $db->lastInsertId();

                // Asignar permisos
                foreach ($permisos_seleccionados as $permiso_id) {
                    $db->execute(
                        "INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)",
                        [$rol_id, intval($permiso_id)]
                    );
                }

                $mensaje_exito = "Rol creado correctamente.";
                echo "<script>setTimeout(() => window.location.href = 'usuarios.php', 1000);</script>";
            }
        } catch (Exception $e) {
            $mensaje_error = "Error al crear el rol: " . $e->getMessage();
        }
    }
}

// Procesar actualizar rol
if (isset($_POST['actualizar_rol'])) {
    $rol_id = intval($_POST['rol_id'] ?? 0);
    $nombre = trim($_POST['nombre'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $nivel = intval($_POST['nivel'] ?? 1);
    $estado = intval($_POST['estado'] ?? 1);
    $permisos_seleccionados = $_POST['permisos'] ?? [];

    if (empty($nombre)) {
        $mensaje_error = "El nombre del rol es obligatorio.";
    } else {
        try {
            // Verificar si el rol existe
            $rol_existe = $db->fetchOne("SELECT id FROM roles WHERE id = ?", [$rol_id]);
            
            if (!$rol_existe) {
                $mensaje_error = "El rol no existe.";
            } else {
                // Verificar si el nombre ya está en uso por otro rol
                $nombre_ocupado = $db->fetchOne("SELECT id FROM roles WHERE nombre = ? AND id != ?", [$nombre, $rol_id]);
                
                if ($nombre_ocupado) {
                    $mensaje_error = "Ya existe otro rol con ese nombre.";
                } else {
                    // Actualizar el rol
                    $db->execute(
                        "UPDATE roles SET nombre = ?, descripcion = ?, nivel = ?, estado = ? WHERE id = ?",
                        [$nombre, $descripcion, $nivel, $estado, $rol_id]
                    );

                    // Eliminar permisos actuales
                    $db->execute("DELETE FROM rol_permisos WHERE rol_id = ?", [$rol_id]);

                    // Asignar nuevos permisos
                    foreach ($permisos_seleccionados as $permiso_id) {
                        $db->execute(
                            "INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (?, ?)",
                            [$rol_id, intval($permiso_id)]
                        );
                    }

                    // Verificar si se actualizó
                    $rol_actualizado = $db->fetchOne(
                        "SELECT id FROM roles WHERE id = ? AND nombre = ? AND nivel = ? AND estado = ?",
                        [$rol_id, $nombre, $nivel, $estado]
                    );
                    
                    if ($rol_actualizado) {
                        $mensaje_exito = "Rol actualizado correctamente.";
                        echo "<script>setTimeout(() => window.location.href = 'usuarios.php', 1000);</script>";
                    } else {
                        $mensaje_error = "No se pudo actualizar el rol.";
                    }
                }
            }
        } catch (Exception $e) {
            $mensaje_error = "Error al actualizar el rol: " . $e->getMessage();
        }
    }
}

// =============================================
// FIN DE PROCESAMIENTO DE FORMULARIOS
// =============================================
?>

<!-- Bootstrap 5 JS (si no está en el footer) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Gestión de Usuarios y Roles</h1>
            <p class="text-muted mb-0">Administra usuarios y roles del sistema</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-crear-usuario">
                <i class="fas fa-user-plus me-2"></i>Nuevo Usuario
            </button>
            <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modal-crear-rol">
                <i class="fas fa-plus me-2"></i>Nuevo Rol
            </button>
        </div>
    </div>

    <!-- Mensajes -->
    <?php if (isset($mensaje_exito)): ?>
    <div class="alert alert-success d-flex align-items-center">
        <i class="fas fa-check-circle me-2"></i>
        <?php echo $mensaje_exito; ?>
    </div>
    <?php endif; ?>

    <?php if (isset($mensaje_error)): ?>
    <div class="alert alert-danger d-flex align-items-center">
        <i class="fas fa-exclamation-circle me-2"></i>
        <?php echo $mensaje_error; ?>
    </div>
    <?php endif; ?>

    <!-- Pestañas -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs card-header-tabs" id="myTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="usuarios-tab" data-bs-toggle="tab" data-bs-target="#usuarios" type="button" role="tab" aria-controls="usuarios" aria-selected="true">
                        <i class="fas fa-users me-2"></i>Usuarios
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="roles-tab" data-bs-toggle="tab" data-bs-target="#roles" type="button" role="tab" aria-controls="roles" aria-selected="false">
                        <i class="fas fa-user-tag me-2"></i>Roles y Permisos
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="myTabContent">
                <!-- Pestaña Usuarios -->
                <div class="tab-pane fade show active" id="usuarios" role="tabpanel" aria-labelledby="usuarios-tab">
                    <?php if (empty($usuarios)): ?>
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-users fa-3x text-muted"></i>
                        </div>
                        <h3 class="text-muted">No hay usuarios registrados</h3>
                        <p class="text-muted mb-4">Comienza agregando el primer usuario al sistema</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modal-crear-usuario">
                            <i class="fas fa-user-plus me-2"></i>Crear Primer Usuario
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Usuario</th>
                                    <th>Contacto</th>
                                    <th>Rol</th>
                                    <th>Estado</th>
                                    <th>Último Login</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']); ?></strong>
                                                <div class="text-muted small"><?php echo htmlspecialchars($usuario['email']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($usuario['telefono'] ?? 'N/A'); ?></div>
                                        <small class="text-muted">DNI: <?php echo htmlspecialchars($usuario['dni'] ?? 'N/A'); ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = [
                                            'super_admin' => 'bg-danger',
                                            'admin' => 'bg-primary',
                                            'supervisor' => 'bg-info',
                                            'operador' => 'bg-success',
                                            'conductor' => 'bg-warning',
                                            'auditor' => 'bg-secondary'
                                        ][$usuario['rol']] ?? 'bg-secondary';
                                        ?>
                                        <span class="badge <?php echo $badge_class; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $usuario['rol'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo ($usuario['estado'] ?? 0) == 1 ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo ($usuario['estado'] ?? 0) == 1 ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php echo $usuario['ultimo_login'] ? date('d/m/Y H:i', strtotime($usuario['ultimo_login'])) : 'Nunca'; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="cargarDatosEditarUsuario(
                                                        <?php echo $usuario['id']; ?>,
                                                        '<?php echo addslashes($usuario['nombre']); ?>',
                                                        '<?php echo addslashes($usuario['apellido'] ?? ''); ?>',
                                                        '<?php echo addslashes($usuario['email']); ?>',
                                                        '<?php echo addslashes($usuario['telefono'] ?? ''); ?>',
                                                        '<?php echo addslashes($usuario['dni'] ?? ''); ?>',
                                                        '<?php echo addslashes($usuario['rol']); ?>',
                                                        <?php echo $usuario['estado']; ?>
                                                    )">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-warning" 
                                                    onclick="cargarDatosCambiarPassword(<?php echo $usuario['id']; ?>, '<?php echo addslashes($usuario['nombre']); ?>')">
                                                <i class="fas fa-key"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Pestaña Roles -->
                <div class="tab-pane fade" id="roles" role="tabpanel" aria-labelledby="roles-tab">
                    <?php if (empty($roles)): ?>
                    <div class="text-center py-5">
                        <div class="mb-4">
                            <i class="fas fa-user-tag fa-3x text-muted"></i>
                        </div>
                        <h3 class="text-muted">No hay roles registrados</h3>
                        <p class="text-muted mb-4">Comienza agregando el primer rol al sistema</p>
                        <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#modal-crear-rol">
                            <i class="fas fa-plus me-2"></i>Crear Primer Rol
                        </button>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Rol</th>
                                    <th>Descripción</th>
                                    <th>Nivel</th>
                                    <th>Estado</th>
                                    <th>Permisos</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $permisos_por_rol = [];
                                try {
                                    $rol_permisos = $db->fetchAll("
                                        SELECT rp.rol_id, rp.permiso_id, p.codigo 
                                        FROM rol_permisos rp 
                                        JOIN permisos p ON rp.permiso_id = p.id 
                                        WHERE p.estado = 1
                                    ");
                                    
                                    foreach ($rol_permisos as $rp) {
                                        if (!isset($permisos_por_rol[$rp['rol_id']])) {
                                            $permisos_por_rol[$rp['rol_id']] = [];
                                        }
                                        $permisos_por_rol[$rp['rol_id']][] = $rp['permiso_id'];
                                    }
                                } catch (Exception $e) {
                                    // Error silenciado
                                }
                                
                                foreach ($roles as $rol): 
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($rol['nombre']); ?></strong>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($rol['descripcion'] ?? 'Sin descripción'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">Nivel <?php echo $rol['nivel']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $rol['estado'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $rol['estado'] ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        $permisos_rol = $permisos_por_rol[$rol['id']] ?? [];
                                        echo '<span class="badge bg-info">' . count($permisos_rol) . ' permiso(s)</span>';
                                        ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                onclick="cargarDatosEditarRol(
                                                    <?php echo $rol['id']; ?>,
                                                    '<?php echo addslashes($rol['nombre']); ?>',
                                                    '<?php echo addslashes($rol['descripcion'] ?? ''); ?>',
                                                    <?php echo $rol['nivel']; ?>,
                                                    <?php echo $rol['estado']; ?>,
                                                    [<?php echo implode(',', $permisos_por_rol[$rol['id']] ?? []); ?>]
                                                )">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Crear Usuario -->
<div class="modal fade" id="modal-crear-usuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Crear Nuevo Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellido</label>
                            <input type="text" class="form-control" name="apellido">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" name="telefono">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">DNI</label>
                            <input type="text" class="form-control" name="dni">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Rol *</label>
                            <select class="form-control" name="rol" required>
                                <option value="operador">Operador</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Admin Cliente</option>
                                <option value="conductor">Conductor</option>
                                <option value="auditor">Auditor</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_usuario" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Crear Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Usuario -->
<div class="modal fade" id="modal-editar-usuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Usuario</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="editar_usuario_id" name="usuario_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre *</label>
                            <input type="text" class="form-control" id="editar_nombre" name="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellido</label>
                            <input type="text" class="form-control" id="editar_apellido" name="apellido">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="editar_email" name="email" readonly style="background-color: #f8f9fa;">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="tel" class="form-control" id="editar_telefono" name="telefono">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">DNI</label>
                            <input type="text" class="form-control" id="editar_dni" name="dni">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rol *</label>
                            <select class="form-control" id="editar_rol" name="rol" required>
                                <option value="operador">Operador</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="admin">Admin Cliente</option>
                                <option value="conductor">Conductor</option>
                                <option value="auditor">Auditor</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select class="form-control" id="editar_estado" name="estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="actualizar_usuario" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Actualizar Usuario
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Cambiar Contraseña -->
<div class="modal fade" id="modal-cambiar-password" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-key me-2"></i>Cambiar Contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="password_usuario_id" name="usuario_id">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Cambiando contraseña para: <strong id="nombre_usuario_password"></strong>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nueva Contraseña *</label>
                        <input type="password" class="form-control" id="nueva_password" name="nueva_password" required minlength="6">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar Contraseña *</label>
                        <input type="password" class="form-control" id="confirmar_password" name="confirmar_password" required minlength="6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="cambiar_password" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Cambiar Contraseña
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Crear Rol -->
<div class="modal fade" id="modal-crear-rol" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus me-2"></i>Crear Nuevo Rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre del Rol *</label>
                            <input type="text" class="form-control" name="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nivel *</label>
                            <input type="number" class="form-control" name="nivel" min="1" max="10" value="1" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" name="descripcion" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Permisos</label>
                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <div class="row">
                                    <?php foreach ($permisos as $permiso): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permisos[]" value="<?php echo $permiso['id']; ?>" id="permiso_<?php echo $permiso['id']; ?>">
                                            <label class="form-check-label" for="permiso_<?php echo $permiso['id']; ?>">
                                                <?php echo htmlspecialchars($permiso['nombre']); ?>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($permiso['modulo']); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="crear_rol" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Crear Rol
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Editar Rol -->
<div class="modal fade" id="modal-editar-rol" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Editar Rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" id="editar_rol_id" name="rol_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nombre del Rol *</label>
                            <input type="text" class="form-control" id="editar_nombre_rol" name="nombre" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nivel *</label>
                            <input type="number" class="form-control" id="editar_nivel_rol" name="nivel" min="1" max="10" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea class="form-control" id="editar_descripcion_rol" name="descripcion" rows="2"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <select class="form-control" id="editar_estado_rol" name="estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Permisos</label>
                            <div class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                                <div class="row">
                                    <?php foreach ($permisos as $permiso): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input permiso-checkbox" type="checkbox" name="permisos[]" value="<?php echo $permiso['id']; ?>" id="editar_permiso_<?php echo $permiso['id']; ?>">
                                            <label class="form-check-label" for="editar_permiso_<?php echo $permiso['id']; ?>">
                                                <?php echo htmlspecialchars($permiso['nombre']); ?>
                                                <small class="text-muted d-block"><?php echo htmlspecialchars($permiso['modulo']); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="actualizar_rol" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Actualizar Rol
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript simplificado y funcional
function cargarDatosEditarUsuario(id, nombre, apellido, email, telefono, dni, rol, estado) {
    document.getElementById('editar_usuario_id').value = id;
    document.getElementById('editar_nombre').value = nombre || '';
    document.getElementById('editar_apellido').value = apellido || '';
    document.getElementById('editar_email').value = email || '';
    document.getElementById('editar_telefono').value = telefono || '';
    document.getElementById('editar_dni').value = dni || '';
    document.getElementById('editar_rol').value = rol || 'operador';
    document.getElementById('editar_estado').value = estado || 1;
    
    const modal = new bootstrap.Modal(document.getElementById('modal-editar-usuario'));
    modal.show();
}

function cargarDatosCambiarPassword(id, nombre) {
    document.getElementById('password_usuario_id').value = id;
    document.getElementById('nombre_usuario_password').textContent = nombre || 'Usuario';
    document.getElementById('nueva_password').value = '';
    document.getElementById('confirmar_password').value = '';
    
    const modal = new bootstrap.Modal(document.getElementById('modal-cambiar-password'));
    modal.show();
}

function cargarDatosEditarRol(id, nombre, descripcion, nivel, estado, permisos) {
    document.getElementById('editar_rol_id').value = id;
    document.getElementById('editar_nombre_rol').value = nombre || '';
    document.getElementById('editar_descripcion_rol').value = descripcion || '';
    document.getElementById('editar_nivel_rol').value = nivel || 1;
    document.getElementById('editar_estado_rol').value = estado || 1;
    
    document.querySelectorAll('.permiso-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    
    if (permisos && permisos.length > 0) {
        permisos.forEach(permisoId => {
            const checkbox = document.getElementById('editar_permiso_' + permisoId);
            if (checkbox) checkbox.checked = true;
        });
    }
    
    const modal = new bootstrap.Modal(document.getElementById('modal-editar-rol'));
    modal.show();
}

// Validación de contraseñas
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('form[action*="cambiar_password"]');
    if (form) {
        form.addEventListener('submit', function(e) {
            const nueva = document.getElementById('nueva_password').value;
            const confirmar = document.getElementById('confirmar_password').value;
            
            if (nueva !== confirmar) {
                e.preventDefault();
                alert('Las contraseñas no coinciden');
                return false;
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>