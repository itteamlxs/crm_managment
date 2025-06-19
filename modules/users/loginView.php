<?php
// Vista para el formulario de login - SOLO VISTA, sin lógica de negocio
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__) . '/users/userController.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Instanciar controlador UNA SOLA VEZ
try {
    $controller = new UserController();
} catch (Exception $e) {
    die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Verificar si ya está autenticado
if ($controller->isAuthenticated()) {
    header('Location: ' . BASE_URL . '/modules/dashboard/dashboardView.php');
    exit;
}

// Variables para la vista
$error = '';
$success = '';

// Procesar formulario solo si es POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $controller->login();
    $error = $result['error'] ?? '';
    // Si no hay error, el controlador ya redirigió
}

// Manejar mensajes de URL
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'unauthorized':
            $error = 'Acceso no autorizado. Inicie sesión.';
            break;
        case 'session_expired':
            $error = 'Su sesión ha expirado. Inicie sesión nuevamente.';
            break;
    }
}

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'logged_out':
            $success = 'Sesión cerrada correctamente.';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        function validateForm() {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            const errorElement = document.getElementById('js-error');
            
            // Limpiar error anterior
            errorElement.textContent = '';
            errorElement.classList.add('hidden');
            
            // Validaciones cliente
            if (!username || !password) {
                errorElement.textContent = 'Por favor, complete todos los campos.';
                errorElement.classList.remove('hidden');
                return false;
            }
            
            if (username.length > <?php echo MAX_NAME_LENGTH; ?>) {
                errorElement.textContent = 'El nombre de usuario es demasiado largo.';
                errorElement.classList.remove('hidden');
                return false;
            }
            
            // Validación básica de formato (no seguridad real)
            if (!/^[a-zA-Z0-9@._-]+$/.test(username)) {
                errorElement.textContent = 'El nombre de usuario contiene caracteres no válidos.';
                errorElement.classList.remove('hidden');
                return false;
            }
            
            if (password.length < 8) {
                errorElement.textContent = 'La contraseña debe tener al menos 8 caracteres.';
                errorElement.classList.remove('hidden');
                return false;
            }
            
            return true;
        }

        // Limpiar mensajes después de 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const messages = document.querySelectorAll('.auto-hide');
            messages.forEach(function(msg) {
                setTimeout(function() {
                    msg.style.transition = 'opacity 0.5s';
                    msg.style.opacity = '0';
                    setTimeout(function() { msg.remove(); }, 500);
                }, 5000);
            });
        });
    </script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <div class="text-center mb-6">
            <h1 class="text-3xl font-bold text-gray-800">CRM Sistema</h1>
            <p class="text-gray-600 mt-2">Iniciar Sesión</p>
        </div>

        <!-- Mensajes de error -->
        <?php if ($error): ?>
            <div id="server-error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 auto-hide">
                <strong>Error:</strong> <?php echo Security::escape($error); ?>
            </div>
        <?php endif; ?>

        <!-- Mensajes de éxito -->
        <?php if ($success): ?>
            <div id="success-msg" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4 auto-hide">
                <?php echo Security::escape($success); ?>
            </div>
        <?php endif; ?>

        <!-- Error de validación JavaScript -->
        <div id="js-error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 hidden"></div>

        <!-- Formulario de login -->
        <form method="POST" onsubmit="return validateForm()" autocomplete="on">
            <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
            
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                    Usuario o Email
                </label>
                <input 
                    type="text" 
                    id="username" 
                    name="username" 
                    autocomplete="username"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
                    maxlength="<?php echo MAX_NAME_LENGTH; ?>" 
                    placeholder="Ingrese su usuario o email"
                    required
                >
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                    Contraseña
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    autocomplete="current-password"
                    class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" 
                    placeholder="Ingrese su contraseña"
                    required
                >
            </div>
            
            <button 
                type="submit" 
                class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition duration-200"
            >
                Iniciar Sesión
            </button>
        </form>

        <div class="mt-6 text-center text-sm text-gray-600">
            <p>Sistema CRM - Gestión de Relaciones con Clientes</p>
        </div>
    </div>
</body>
</html>