<?php
// backups.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Database.php';

$page_title = 'Backups del Sistema';
$breadcrumbs = [
    'index.php' => 'Dashboard',
    'backups.php' => 'Backups'
];

require_once __DIR__ . '/includes/header.php';

$db = new Database();
$cliente_id = $_SESSION['cliente_id'] ?? 0;
$usuario_id = $_SESSION['user_id'] ?? 0;

$backup_dir = __DIR__ . '/storage/backups/';
if (!is_dir($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

/* ===============================
   CREAR BACKUP
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_backup'])) {
    try {
        $nombre = 'backup_' . date('Ymd_His') . '.sql';
        $ruta = $backup_dir . $nombre;

        $db_name = DB_NAME;
        $db_user = DB_USER;
        $db_pass = DB_PASS;
        $db_host = DB_HOST;

        $cmd = "mysqldump --user={$db_user} --password={$db_pass} --host={$db_host} {$db_name} > {$ruta}";
        exec($cmd, $output, $result);

        if ($result !== 0) {
            throw new Exception('No se pudo generar el backup');
        }

        $db->execute("
            INSERT INTO auditoria 
            (cliente_id, usuario_id, accion, tabla_afectada, detalles, ip_address, user_agent)
            VALUES (?, ?, 'CREAR_BACKUP', 'sistema', ?, ?, ?)
        ", [
            $cliente_id,
            $usuario_id,
            'Backup creado: ' . $nombre,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $mensaje_exito = 'Backup generado correctamente';

    } catch (Exception $e) {
        $mensaje_error = 'Error al crear backup: ' . $e->getMessage();
    }
}

/* ===============================
   ELIMINAR BACKUP
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_backup'])) {
    $archivo = basename($_POST['archivo']);

    $ruta = $backup_dir . $archivo;
    if (file_exists($ruta)) {
        unlink($ruta);

        $db->execute("
            INSERT INTO auditoria 
            (cliente_id, usuario_id, accion, tabla_afectada, detalles, ip_address, user_agent)
            VALUES (?, ?, 'ELIMINAR_BACKUP', 'sistema', ?, ?, ?)
        ", [
            $cliente_id,
            $usuario_id,
            'Backup eliminado: ' . $archivo,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT']
        ]);

        $mensaje_exito = 'Backup eliminado correctamente';
    }
}

/* ===============================
   LISTAR BACKUPS
================================ */
$backups = [];
$files = glob($backup_dir . '*.sql');
foreach ($files as $f) {
    $backups[] = [
        'archivo' => basename($f),
        'peso' => filesize($f),
        'fecha' => date('d/m/Y H:i', filemtime($f))
    ];
}
?>

<div class="content-body">

<div class="dashboard-header">
    <div class="welcome-section">
        <h1><?php echo $page_title; ?></h1>
        <p class="dashboard-subtitle">Gestión de respaldos de la base de datos</p>
    </div>
    <div class="header-actions">
        <form method="POST">
            <button type="submit" name="crear_backup" class="btn btn-primary">
                <i class="fas fa-database"></i> Crear Backup
            </button>
        </form>
    </div>
</div>

<?php if (isset($mensaje_exito)): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?php echo $mensaje_exito; ?>
</div>
<?php endif; ?>

<?php if (isset($mensaje_error)): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <?php echo $mensaje_error; ?>
</div>
<?php endif; ?>

<div class="card">
<div class="card-header">
    <h3><i class="fas fa-archive"></i> Backups Disponibles</h3>
    <span class="badge primary"><?php echo count($backups); ?> archivos</span>
</div>
<div class="card-body">

<?php if ($backups): ?>
<div class="table-responsive">
<table class="data-table">
<thead>
<tr>
    <th>Archivo</th>
    <th>Fecha</th>
    <th>Peso</th>
    <th>Acciones</th>
</tr>
</thead>
<tbody>
<?php foreach ($backups as $b): ?>
<tr>
    <td><?php echo htmlspecialchars($b['archivo']); ?></td>
    <td><?php echo $b['fecha']; ?></td>
    <td><?php echo round($b['peso']/1024, 2); ?> KB</td>
    <td class="action-buttons">
        <a class="btn-icon info" href="storage/backups/<?php echo urlencode($b['archivo']); ?>" download>
            <i class="fas fa-download"></i>
        </a>
        <form method="POST" onsubmit="return confirm('¿Eliminar backup?')" style="display:inline">
            <input type="hidden" name="archivo" value="<?php echo htmlspecialchars($b['archivo']); ?>">
            <button type="submit" name="eliminar_backup" class="btn-icon danger">
                <i class="fas fa-trash"></i>
            </button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>
<?php else: ?>
<div class="empty-state">
    <div class="empty-icon"><i class="fas fa-database"></i></div>
    <h3>No hay backups</h3>
    <p>Crea el primer respaldo de la base de datos</p>
</div>
<?php endif; ?>

</div>
</div>

</div>

<style>
.action-buttons { display:flex; gap:.5rem; justify-content:center; }
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
