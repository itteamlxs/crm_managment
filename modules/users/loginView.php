<?php
// Vista para el formulario de login
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/session.php';
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__) . '/users/userController.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

try {
    $session = new Session();
    $controller = new UserController();
} catch (Exception $e) {
    die('Error al inicializar sesión o controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $result = $controller->login();
        $error = $result['error'] ?? '';
    } catch (Exception $e) {
        $error = 'Error al procesar el login: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
            const errorElement = document.getElementById('error');
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
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(username) && !/^[a-zA-Z0-9_]+$/.test(username)) {
                errorElement.textContent = 'El nombre de usuario no es válido.';
                errorElement.classList.remove('hidden');
                return false;
            }
            if (password.length < 8) {
                errorElement.textContent = 'La contraseña debe tener al menos 8 caracteres.';
                errorElement.classList.remove('hidden');
                return false;
            }
            errorElement.classList.add('hidden');
            return true;
        }
    </script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <h1 class="text-2xl font-bold mb-6 text-center">Iniciar Sesión</h1>
        <p id="error" class="text-red-500 mb-4 <?php echo $error ? '' : 'hidden'; ?>">
            <?php echo $error ? Security::escape($error) : ''; ?>
        </p>
        <form method="POST" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?php echo Security::escape($session->generateCsrfToken()); ?>">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700">Usuario</label>
                <input type="text" id="username" name="username" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" maxlength="<?php echo MAX_NAME_LENGTH; ?>" required>
            </div>
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                <input type="password" id="password" name="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500" required>
            </div>
            <button type="submit" class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Iniciar Sesión</button>
        </form>
    </div>
</body>
</html>
<?php
// Sin espacios ni cierres adicionales
?>