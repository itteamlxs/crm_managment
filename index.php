<?php
// Router simple para Sistema CRM
// Reemplazar el index.php existente en la raíz

// Obtener la URL solicitada
$request = $_SERVER['REQUEST_URI'];

// Remover query string si existe
$path = parse_url($request, PHP_URL_PATH);

// Remover la ruta base si está en subdirectorio
$basePath = '/crm'; // Cambiar según tu instalación
if (strpos($path, $basePath) === 0) {
    $path = substr($path, strlen($basePath));
}

// Limpiar la ruta
$path = trim($path, '/');

// Si está vacía, ir al login
if (empty($path)) {
    $path = 'login';
}

// Dividir la ruta en partes
$segments = explode('/', $path);
$route = $segments[0] ?? 'login';
$action = $segments[1] ?? null;
$id = $segments[2] ?? null;

// Rutas disponibles
$routes = [
    // Públicas
    'login' => 'modules/users/loginView.php',
    'logout' => 'logout.php',
    
    // Protegidas
    'dashboard' => 'modules/dashboard/dashboardView.php',
    'clients' => 'modules/clients/clientList.php',
    'products' => 'modules/products/productList.php',
    'quotes' => 'modules/quotes/quoteList.php',
    'reports' => 'modules/reports/reportView.php',
    'settings' => 'modules/settings/settingsView.php',
    'users' => 'modules/users/userManagement.php'
];

// Rutas con acciones específicas
if ($action) {
    switch ($route) {
        case 'clients':
            if ($action === 'new' || $action === 'create') {
                $file = 'modules/clients/clientForm.php';
            } elseif ($action === 'edit' && $id) {
                $_GET['id'] = $id;
                $file = 'modules/clients/clientForm.php';
            } elseif ($action === 'view' && $id) {
                $_GET['id'] = $id;
                $file = 'modules/clients/viewCliente.php';
            } else {
                $file = 'modules/clients/clientList.php';
            }
            break;
            
        case 'products':
            if ($action === 'new' || $action === 'create') {
                $file = 'modules/products/productForm.php';
            } elseif ($action === 'edit' && $id) {
                $_GET['id'] = $id;
                $file = 'modules/products/productForm.php';
            } else {
                $file = 'modules/products/productList.php';
            }
            break;
            
        case 'quotes':
            if ($action === 'new' || $action === 'create') {
                $file = 'modules/quotes/quoteForm.php';
            } elseif ($action === 'edit' && $id) {
                $_GET['id'] = $id;
                $file = 'modules/quotes/quoteForm.php';
            } elseif ($action === 'view' && $id) {
                $_GET['id'] = $id;
                $file = 'modules/quotes/quoteView.php';
            } elseif ($action === 'print' && $id) {
                $_GET['id'] = $id;
                $file = 'modules/quotes/printQuote.php';
            } else {
                $file = 'modules/quotes/quoteList.php';
            }
            break;
            
        case 'users':
            if ($action === 'new' || $action === 'create') {
                $file = 'modules/users/userForm.php';
            } elseif ($action === 'edit' && $id) {
                $_GET['id'] = $id;
                $file = 'modules/users/userForm.php';
            } else {
                $file = 'modules/users/userManagement.php';
            }
            break;
            
        default:
            $file = $routes[$route] ?? null;
    }
} else {
    $file = $routes[$route] ?? null;
}

// Si no encontramos archivo, mostrar 404
if (!$file || !file_exists($file)) {
    http_response_code(404);
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>404 - Página no encontrada</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; margin-top: 100px; }
            h1 { color: #666; }
        </style>
    </head>
    <body>
        <h1>404 - Página no encontrada</h1>
        <p>La página que buscas no existe.</p>
        <a href="/crm/dashboard">← Volver al Dashboard</a>
    </body>
    </html>';
    exit;
}

// Incluir el archivo
include $file;
?>