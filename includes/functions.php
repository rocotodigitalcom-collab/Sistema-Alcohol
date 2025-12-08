<?php
// includes/functions.php

function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    return true;
}

function hasPermission($permission) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    // Permisos básicos por rol
    $permissions = [
        'admin' => ['all'],
        'supervisor' => ['view_reports', 'manage_users'],
        'user' => ['view_data', 'add_records']
    ];
    
    $userRole = $_SESSION['user_role'];
    
    if (!isset($permissions[$userRole])) {
        return false;
    }
    
    return in_array('all', $permissions[$userRole]) || 
           in_array($permission, $permissions[$userRole]);
}

// Función simple alternativa
function hasPermissionSimple($required_role) {
    if (!isset($_SESSION['user_role'])) {
        return false;
    }
    
    $user_role = $_SESSION['user_role'];
    $hierarchy = ['user' => 1, 'supervisor' => 2, 'admin' => 3];
    
    return isset($hierarchy[$user_role]) && 
           isset($hierarchy[$required_role]) && 
           ($hierarchy[$user_role] >= $hierarchy[$required_role]);
}
?>