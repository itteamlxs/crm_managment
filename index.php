<?php
// index.php - Enrutador principal con seguridad

require_once './config/constants.php';
require_once './core/db.php';
require_once './core/session.php';
require_once './core/security.php';
require_once './core/utils.php';
require_once './core/language.php';
require_once './core/nav.php';

// Módulos permitidos explícitamente
$allowedModules = [
    'dashboard', 'clients', 'products', 'quotes', 'reports', 'settings', 'users'
];

// Parámetros de la URL sanitizados
$module = isset($_GET['module']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['module']) : 'dashboard';
$action = isset($_GET['action']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $_GET['action']) : 'index';

// Validar módulo permitido
if (!in_array($module, $allowedModules)) {
    http_response_code(403);
    exit("Acceso denegado: módulo no permitido.");
}

// Cargar controlador del módulo
$controllerPath = "./modules/$module/{$module}Controller.php";
if (!file_exists($controllerPath)) {
    http_response_code(404);
    exit("Error: controlador no encontrado para el módulo '$module'.");
}
require_once $controllerPath;

// Instanciar clase del controlador
$controllerClass = ucfirst($module) . 'Controller';
if (!class_exists($controllerClass)) {
    http_response_code(500);
    exit("Error: clase del controlador '$controllerClass' no definida.");
}

$controller = new $controllerClass();

// Validar método público y no mágico
if (!method_exists($controller, $action) || strpos($action, '__') === 0) {
    http_response_code(400);
    exit("Error: acción '$action' inválida en el controlador.");
}

// Definir rutas públicas permitidas sin autenticación
$publicRoutes = [
    'users' => ['loginView', 'userLogin']
];

// Validar autenticación
$authenticated = isset($_SESSION['user_id']);
if (!$authenticated && (!isset($publicRoutes[$module]) || !in_array($action, $publicRoutes[$module]))) {
    header("Location: index.php?module=users&action=loginView");
    exit;
}

// Ejecutar acción del controlador
$controller->$action();
