<?php
require_once CORE_PATH . '/session.php';
require_once CORE_PATH . '/security.php';
require_once CORE_PATH . '/utils.php';
require_once MODULES_PATH . '/users/userController.php';
require_once CONFIG_PATH . '/constants.php';

$session = new Session();
if (!$session->isLoggedIn() || !$session->hasRole(ROLE_ADMIN)) {
    Utils::redirect('index.php?error=unauthorized');
}

$controller = new UserController();
$users = $controller->list();
$error = '';
$success = $_GET['success'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $result = $controller->create();
        $error = $result['error'] ?? '';
        if (!$error) {
            $users = $controller->list(); // Actualizar lista
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'update') {
        $result = $controller->update();
        $error = $result['error'] ?? '';
        if (!$error) {
            $users = $controller->list(); // Actualizar lista
        }
    } elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $result = $controller->delete((int)$_GET['id']);
        $error = $result['error'] ?? '';
        if (!$error) {
            $users = $controller->list(); // Actualizar lista
        }
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
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const name = document.getElementById('name').value;
            const password = document.getElementById('password').value;
            if (!username || !email || !name || (document.getElementById('action').value === 'create' && !password)) {
                document.getElementById('error').textContent = 'Por favor, complete todos los campos requeridos.';
                return false;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                document.getElementById('error').textContent = 'Correo electrónico no válido.';
                return false;
            }
            return true;
        }
    </script>
</head>
<body class="bg-gray-100 p-6">
    <div class="max-w-4xl mx-auto bg-white p-6 rounded-lg shadow-lg">
        <h1 class="text-2xl font-bold mb-6">Gestión de Usuarios</h1>
        <?php if ($error): ?>
            <p id="error" class="text-red-500 mb-4"><?php echo Security::escape($error); ?></p>
        <?php endif; ?>
        <?php if ($success): ?>
            <p class="text-green-500 mb-4">
                <?php echo Security::escape($success === 'created' ? 'Usuario creado.' : ($success === 'updated' ? 'Usuario actualizado.' : 'Usuario eliminado.')); ?>
            </p>
        <?php endif; ?>

        <!-- Formulario para crear/editar usuario -->
        <form method="POST" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?php echo Security::escape($session->generateCsrfToken()); ?>">
            <input type="hidden" id="action" name="action" value="create">
            <input type="hidden" id="id" name="id" value="">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700">Usuario</label>
                <input type="text" id="username" name="username" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" maxlength="<?php echo MAX_NAME_LENGTH; ?>" required>
            </div>
            <div class="mb-4">
                <label for="email" class="block text-sm font-medium text-gray-700">Correo</label>
                <input type="email" id="email" name="email" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" maxlength="<?php echo MAX_EMAIL_LENGTH; ?>" required>
            </div>
            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700">Nombre</label>
                <input type="text" id="name" name="name" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm" maxlength="<?php echo MAX_NAME_LENGTH; ?>" required>
            </div>
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                <input type="password" id="password" name="password" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
            </div>
            <div class="mb-4">
                <label for="role" class="block text-sm font-medium text-gray-700">Rol</label>
                <select id="role" name="role" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="<?php echo ROLE_ADMIN; ?>">Administrador</option>
                    <option value="<?php echo ROLE_SELLER; ?>">Vendedor</option>
                </select>
            </div>
            <div class="mb-4">
                <label for="status" class="block text-sm font-medium text-gray-700">Estado</label>
                <select id="status" name="status" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm">
                    <option value="<?php echo STATUS_ACTIVE; ?>">Activo</option>
                    <option value="<?php echo STATUS_INACTIVE; ?>">Inactivo</option>
                </select>
            </div>
            <button type="submit" class="bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700">Guardar</button>
        </form>

        <!-- Lista de usuarios -->
        <h2 class="text-xl font-bold mt-8 mb-4">Usuarios Registrados</h2>
        <table class="w-full border-collapse">
            <thead>
                <tr class="bg-gray-200">
                    <th class="border p-2">ID</th>
                    <th class="border p-2">Usuario</th>
                    <th class="border p-2">Correo</th>
                    <th class="border p-2">Nombre</th>
                    <th class="border p-2">Rol</th>
                    <th class="border p-2">Estado</th>
                    <th class="border p-2">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="border p-2"><?php echo Security::escape($user['id']); ?></td>
                        <td class="border p-2"><?php echo Security::escape($user['username']); ?></td>
                        <td class="border p-2"><?php echo Security::escape($user['email']); ?></td>
                        <td class="border p-2"><?php echo Security::escape($user['name']); ?></td>
                        <td class="border p-2"><?php echo Security::escape($user['role'] == ROLE_ADMIN ? 'Administrador' : 'Vendedor'); ?></td>
                        <td class="border p-2"><?php echo Security::escape($user['status'] == STATUS_ACTIVE ? 'Activo' : 'Inactivo'); ?></td>
                        <td class="border p-2">
                            <button onclick="editUser(<?php echo Security::escape(json_encode($user)); ?>)" class="bg-blue-500 text-white px-2 py-1 rounded">Editar</button>
                            <a href="?action=delete&id=<?php echo Security::escape($user['id']); ?>" onclick="return confirm('¿Confirmar eliminación?')" class="bg-red-500 text-white px-2 py-1 rounded">Eliminar</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script>
        function editUser(user) {
            document.getElementById('action').value = 'update';
            document.getElementById('id').value = user.id;
            document.getElementById('username').value = user.username;
            document.getElementById('email').value = user.email;
            document.getElementById('name').value = user.name;
            document.getElementById('role').value = user.role;
            document.getElementById('status').value = user.status;
            document.getElementById('password').value = '';
        }
    </script>
</body>
</html>
?>