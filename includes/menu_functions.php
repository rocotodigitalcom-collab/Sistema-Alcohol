<?php
/**
 * Funciones auxiliares para el menú y sidebar
 */

/**
 * Obtener estadísticas para los badges del menú
 */
function getMenuStatistics($cliente_id) {
    $db = new Database();
    
    return [
        'pruebas_hoy' => $db->fetchOne("
            SELECT COUNT(*) as total 
            FROM pruebas 
            WHERE cliente_id = ? AND DATE(fecha_prueba) = CURDATE()
        ", [$cliente_id])['total'] ?? 0,
        
        'retests_pendientes' => $db->fetchOne("
            SELECT COUNT(*) as total 
            FROM pruebas 
            WHERE cliente_id = ? AND es_retest = 1 AND resultado = 'reprobado'
            AND prueba_padre_id IS NOT NULL
        ", [$cliente_id])['total'] ?? 0,
        
        'calibracion_pendiente' => $db->fetchOne("
            SELECT COUNT(*) as total 
            FROM alcoholimetros 
            WHERE cliente_id = ? AND estado = 'activo' 
            AND proxima_calibracion <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ", [$cliente_id])['total'] ?? 0
    ];
}

/**
 * Verificar si un ítem de menú está activo
 */
function isMenuItemActive($item_route, $current_page, $current_dir) {
    $current_path = $current_dir . '/' . $current_page;
    
    // Caso especial para dashboard
    if ($current_page === 'index.php' && $item_route === 'index.php') {
        return true;
    }
    
    // Verificar coincidencia de rutas
    return strpos($current_path, $item_route) !== false;
}

/**
 * Generar HTML para badges del menú
 */
function generateMenuBadge($count, $type = 'default') {
    if ($count <= 0) return '';
    
    $badge_classes = [
        'default' => 'badge-primary',
        'warning' => 'badge-warning',
        'danger' => 'badge-danger',
        'success' => 'badge-success'
    ];
    
    $class = $badge_classes[$type] ?? $badge_classes['default'];
    
    return '<span class="menu-badge ' . $class . '">' . $count . '</span>';
}
?>