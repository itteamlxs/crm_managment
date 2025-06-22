<?php
// Archivo de logout - guardar en la raíz del proyecto: /logout.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once 'core/session.php';
    require_once 'core/security.php';
    require_once 'config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

try {
    $session = new Session();
    $session->destroy();
    
    // Redirigir al login con mensaje de éxito
    header('Location: ' . BASE_URL . '/modules/users/loginView.php?success=logged_out');
    exit;
    
} catch (Exception $e) {
    error_log("Error during logout: " . $e->getMessage());
    
    // En caso de error, intentar destruir sesión manualmente
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    
    header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=logout_error');
    exit;
}
?>