<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/session.php';
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/users/userController.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Instanciar controlador
try {
    $controller = new UserController();
} catch (Exception $e) {
    die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Verificar autenticación y permisos de administrador
if (!$controller->isAuthenticated() || !$controller->isAdmin()) {
    header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=unauthorized');
    exit;
}

// Variables para mensajes
$error = '';
$success = '';

// Procesar acciones POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                $result = $controller->create();
                $error = $result['error'] ?? '';
                break;
            case 'update':
                $result = $controller->update();
                $error = $result['error'] ?? '';
                break;
        }
    }
}

// Procesar acciones GET
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $result = $controller->delete((int)$_GET['id']);
    $error = $result['error'] ?? '';
}

// Obtener lista de usuarios
$users = $controller->list();
if (isset($users['error'])) {
    $error = $users['error'];
    $users = [];
}

// Manejar mensajes de URL
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success = 'Usuario creado correctamente.';
            break;
        case 'updated':
            $success = 'Usuario actualizado correctamente.';
            break;
        case 'deleted':
            $success = 'Usuario eliminado correctamente.';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        function validateForm() {
            const username = document.getElementById('username').value.trim();
            const email = document.getElementById('email').value.trim();
            const name = document.getElementById('name').value.trim();
            const password = document.getElementById('password').value.trim();
            const action = document.getElementById('action').value;
            const errorElement = document.getElementById('form-error');
            
            // Limpiar error anterior
            errorElement.textContent = '';
            errorElement.classList.add('hidden');
            
            if (!username || !email || !name) {
                errorElement.textContent = 'Por favor, complete todos los campos requeridos.';
                errorElement.classList.remove('hidden');
                return false;
            }
            
            if (action === 'create' && !password) {
                errorElement.textContent = 'La contraseña es requerida para crear un usuario.';
                errorElement.classList.remove('hidden');
                return false;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                errorElement.textContent = 'Correo electrónico no válido.';
                errorElement.classList.remove('hidden');
                return false;
            }
            
            if (password && password.length < 8) {
                errorElement.textContent = 'La contraseña debe tener al menos 8 caracteres.';
                errorElement.classList.remove('hidden');
                return false;
            }
            
            return true;
        }

        function editUser(user) {
            document.getElementById('action').value = 'update';
            document.getElementById('id').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('name').value = user.name;
            document.getElementById('role').value = user.role;
            document.getElementById('status').value = user.status;
            document.getElementById('password').value = '';
            
            // Cambiar texto del botón
            document.getElementById('submit-btn').textContent = 'Actualizar Usuario';
            
            // Scroll al formulario
            document.getElementById('user-form').scrollIntoView({ behavior: 'smooth' });
        }

        function resetForm() {
            document.getElementById('action').value = 'create';
            document.getElementById('id').value = '';
            document.getElementById('user-form-element').reset();
            document.getElementById('submit-btn').textContent = 'Crear Usuario';
            
            // Limpiar errores
            const errorElement = document.getElementById('form-error');
            errorElement.textContent = '';
            errorElement.classList.add('hidden');
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php require_once dirname(__DIR__, 2) . '/core/nav.php'; ?>
    <div class="container mx-auto px-4 py-8">
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Gestión de Usuarios</h1>
                    <p class="text-gray-600 mt-2">Administrar usuarios del sistema CRM</p>
                </div>
                <div class="flex space-x-2">
                    <a href="<?php echo BASE_URL; ?>/modules/dashboard/dashboardView.php" 
                       class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        ← Dashboard
                    </a>
                    <button onclick="resetForm()" 
                            class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        Nuevo Usuario
                    </button>
                </div>
            </div>
        </div>

        <!-- Mensajes -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?php echo Security::escape($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo Security::escape($success); ?>
            </div>
        <?php endif; ?>

        <!-- Formulario para crear/editar usuario -->
        <div id="user-form" class="bg-white rounded-lg shadow-md p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Formulario de Usuario</h2>
            
            <!-- Error del formulario -->
            <div id="form-error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 hidden"></div>
            
            <form id="user-form-element" method="POST" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                <input type="hidden" id="action" name="action" value="create">
                <input type="hidden" id="id" name="id" value="">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="username" class="block text-sm font-medium text-gray-700 mb-2">
                            Usuario <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="username" 
                               name="username" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               maxlength="<?php echo MAX_NAME_LENGTH; ?>" 
                               placeholder="Nombre de usuario único"
                               required>
                    </div>
                    
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Correo Electrónico <span class="text-red-500">*</span>
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               maxlength="<?php echo MAX_EMAIL_LENGTH; ?>" 
                               placeholder="usuario@empresa.com"
                               required>
                    </div>
                    
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre Completo <span class="text-red-500">*</span>
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               maxlength="<?php echo MAX_NAME_LENGTH; ?>" 
                               placeholder="Juan Pérez García"
                               required>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700 mb-2">
                            Contraseña <span id="password-required" class="text-red-500">*</span>
                        </label>
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Mínimo 8 caracteres">
                        <p class="text-sm text-gray-600 mt-1">Dejar vacío para mantener contraseña actual (solo edición)</p>
                    </div>
                    
                    <div>
                        <label for="role" class="block text-sm font-medium text-gray-700 mb-2">
                            Rol <span class="text-red-500">*</span>
                        </label>
                        <select id="role" 
                                name="role" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                            <option value="<?php echo ROLE_ADMIN; ?>">Administrador</option>
                            <option value="<?php echo ROLE_SELLER; ?>" selected>Vendedor</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                            Estado <span class="text-red-500">*</span>
                        </label>
                        <select id="status" 
                                name="status" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                                required>
                            <option value="<?php echo STATUS_ACTIVE; ?>">Activo</option>
                            <option value="<?php echo STATUS_INACTIVE; ?>">Inactivo</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button type="button" 
                            onclick="resetForm()"
                            class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                        Limpiar
                    </button>
                    <button type="submit" 
                            id="submit-btn"
                            class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700">
                        Crear Usuario
                    </button>
                </div>
            </form>
        </div>

        <!-- Lista de usuarios -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Usuarios Registrados</h2>
            
            <?php if (empty($users)): ?>
                <div class="text-center py-8 text-gray-500">
                    <p>No hay usuarios registrados.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="bg-gray-50">
                                <th class="border border-gray-300 px-4 py-2 text-left">ID</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Usuario</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Correo</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Nombre</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Rol</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Estado</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="border border-gray-300 px-4 py-2"><?php echo Security::escape($user['id']); ?></td>
                                    <td class="border border-gray-300 px-4 py-2 font-medium"><?php echo Security::escape($user['username']); ?></td>
                                    <td class="border border-gray-300 px-4 py-2"><?php echo Security::escape($user['email']); ?></td>
                                    <td class="border border-gray-300 px-4 py-2"><?php echo Security::escape($user['name']); ?></td>
                                    <td class="border border-gray-300 px-4 py-2">
                                        <span class="px-2 py-1 rounded text-sm <?php echo $user['role'] == ROLE_ADMIN ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                            <?php echo Security::escape($user['role'] == ROLE_ADMIN ? 'Admin' : 'Vendedor'); ?>
                                        </span>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2">
                                        <span class="px-2 py-1 rounded text-sm <?php echo $user['status'] == STATUS_ACTIVE ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo Security::escape($user['status'] == STATUS_ACTIVE ? 'Activo' : 'Inactivo'); ?>
                                        </span>
                                    </td>
                                    <td class="border border-gray-300 px-4 py-2">
                                        <div class="flex space-x-2">
                                            <button onclick="editUser(<?php echo Security::escape(json_encode($user)); ?>)" 
                                                    class="bg-blue-500 text-white px-3 py-1 rounded text-sm hover:bg-blue-600">
                                                Editar
                                            </button>
                                            <a href="?action=delete&id=<?php echo Security::escape($user['id']); ?>" 
                                               onclick="return confirm('¿Está seguro de eliminar este usuario?')" 
                                               class="bg-red-500 text-white px-3 py-1 rounded text-sm hover:bg-red-600">
                                                Eliminar
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>