<?php
// Vista del formulario de clientes (crear/editar)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/clients/clientController.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Instanciar controlador
try {
    $controller = new ClientController();
} catch (Exception $e) {
    die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Verificar autenticación
if (!$controller->isAuthenticated()) {
    header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=session_expired');
    exit;
}

// Determinar si es edición o creación
$isEdit = isset($_GET['id']) && is_numeric($_GET['id']);
$client = null;
$error = '';
$success = '';

// Si es edición, obtener datos del cliente
if ($isEdit) {
    $clientId = (int)$_GET['id'];
    $result = $controller->getById($clientId);
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $client = $result['client'];
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEdit && $client) {
        $result = $controller->update();
    } else {
        $result = $controller->create();
    }
    
    if (isset($result['error'])) {
        $error = $result['error'];
    }
    // Si no hay error, el controlador ya redirigió
}

$pageTitle = $isEdit ? 'Editar Cliente' : 'Nuevo Cliente';
$buttonText = $isEdit ? 'Actualizar Cliente' : 'Crear Cliente';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script>
        // Validaciones del lado del cliente
        function validateForm() {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            const errorElement = document.getElementById('js-error');
            
            // Limpiar errores previos
            errorElement.textContent = '';
            errorElement.classList.add('hidden');
            
            // Validar campos requeridos
            if (!name || !email) {
                showError('Nombre y correo electrónico son requeridos.');
                return false;
            }
            
            // Validar longitud del nombre
            if (name.length > <?php echo MAX_NAME_LENGTH; ?>) {
                showError('El nombre es demasiado largo (máximo <?php echo MAX_NAME_LENGTH; ?> caracteres).');
                return false;
            }
            
            // Validar formato de email
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showError('El formato del correo electrónico no es válido.');
                return false;
            }
            
            // Validar longitud del email
            if (email.length > <?php echo MAX_EMAIL_LENGTH; ?>) {
                showError('El correo electrónico es demasiado largo.');
                return false;
            }
            
            // Validar longitud del teléfono
            if (phone && phone.length > 20) {
                showError('El teléfono es demasiado largo (máximo 20 caracteres).');
                return false;
            }
            
            return true;
        }
        
        function showError(message) {
            const errorElement = document.getElementById('js-error');
            errorElement.textContent = message;
            errorElement.classList.remove('hidden');
            errorElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        
        // Validación en tiempo real del email
        function validateEmail() {
            const email = document.getElementById('email').value.trim();
            const feedback = document.getElementById('email-feedback');
            
            if (!email) {
                feedback.textContent = '';
                return;
            }
            
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                feedback.textContent = 'Formato de email no válido';
                feedback.className = 'text-red-500 text-sm mt-1';
                return;
            }
            
            // Aquí podrías hacer una validación AJAX para verificar duplicados
            feedback.textContent = 'Email válido';
            feedback.className = 'text-green-500 text-sm mt-1';
        }
        
        // Función para confirmar cancelación si hay cambios
        function confirmCancel() {
            const form = document.getElementById('client-form');
            const formData = new FormData(form);
            let hasChanges = false;
            
            // Verificar si hay cambios en el formulario
            <?php if ($isEdit): ?>
                const originalData = {
                    name: '<?php echo Security::escape($client['name'] ?? ''); ?>',
                    email: '<?php echo Security::escape($client['email'] ?? ''); ?>',
                    phone: '<?php echo Security::escape($client['phone'] ?? ''); ?>',
                    address: '<?php echo Security::escape($client['address'] ?? ''); ?>',
                    status: '<?php echo $client['status'] ?? STATUS_ACTIVE; ?>'
                };
                
                for (let [key, value] of Object.entries(originalData)) {
                    if (formData.get(key) !== value) {
                        hasChanges = true;
                        break;
                    }
                }
            <?php else: ?>
                // Para formulario nuevo, verificar si hay datos ingresados
                for (let [key, value] of formData.entries()) {
                    if (key !== 'csrf_token' && value.trim()) {
                        hasChanges = true;
                        break;
                    }
                }
            <?php endif; ?>
            
            if (hasChanges) {
                return confirm('¿Está seguro de cancelar? Los cambios no guardados se perderán.');
            }
            return true;
        }
    </script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
                <div class="flex space-x-2">
                    <a href="clientList.php" 
                       onclick="return confirmCancel()"
                       class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        ← Volver al Listado
                    </a>
                </div>
            </div>
        </div>

        <!-- Mensajes de error -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?php echo Security::escape($error); ?>
            </div>
        <?php endif; ?>

        <!-- Error de validación JavaScript -->
        <div id="js-error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 hidden"></div>

        <!-- Formulario -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form id="client-form" method="POST" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $client['id']; ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Nombre -->
                    <div class="md:col-span-2">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre Completo <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            id="name" 
                            name="name" 
                            value="<?php echo Security::escape($client['name'] ?? ''); ?>"
                            maxlength="<?php echo MAX_NAME_LENGTH; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Ingrese el nombre completo del cliente"
                            required
                        >
                        <div class="text-gray-500 text-sm mt-1">
                            Máximo <?php echo MAX_NAME_LENGTH; ?> caracteres
                        </div>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                            Correo Electrónico <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            value="<?php echo Security::escape($client['email'] ?? ''); ?>"
                            maxlength="<?php echo MAX_EMAIL_LENGTH; ?>"
                            onblur="validateEmail()"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="cliente@empresa.com"
                            required
                        >
                        <div id="email-feedback"></div>
                    </div>

                    <!-- Teléfono -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">
                            Teléfono
                        </label>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            value="<?php echo Security::escape($client['phone'] ?? ''); ?>"
                            maxlength="20"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="+1 234 567 8900"
                        >
                        <div class="text-gray-500 text-sm mt-1">
                            Opcional - Máximo 20 caracteres
                        </div>
                    </div>

                    <!-- Dirección -->
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-gray-700 mb-2">
                            Dirección
                        </label>
                        <textarea 
                            id="address" 
                            name="address" 
                            rows="3"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            placeholder="Dirección completa del cliente (opcional)"
                        ><?php echo Security::escape($client['address'] ?? ''); ?></textarea>
                        <div class="text-gray-500 text-sm mt-1">
                            Opcional - Dirección completa del cliente
                        </div>
                    </div>

                    <!-- Estado (solo en edición) -->
                    <?php if ($isEdit): ?>
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">
                                Estado
                            </label>
                            <select 
                                id="status" 
                                name="status"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            >
                                <option value="<?php echo STATUS_ACTIVE; ?>" <?php echo ($client['status'] ?? STATUS_ACTIVE) == STATUS_ACTIVE ? 'selected' : ''; ?>>
                                    Activo
                                </option>
                                <option value="<?php echo STATUS_INACTIVE; ?>" <?php echo ($client['status'] ?? STATUS_ACTIVE) == STATUS_INACTIVE ? 'selected' : ''; ?>>
                                    Inactivo
                                </option>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Información adicional en edición -->
                <?php if ($isEdit && $client): ?>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Registro</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm text-gray-600">
                            <div>
                                <strong>Creado:</strong> <?php echo Utils::formatDateDisplay($client['created_at']); ?>
                            </div>
                            <?php if ($client['updated_at']): ?>
                                <div>
                                    <strong>Última actualización:</strong> <?php echo Utils::formatDateDisplay($client['updated_at']); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Botones -->
                <div class="mt-8 flex justify-end space-x-4">
                    <a href="clientList.php" 
                       onclick="return confirmCancel()"
                       class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button 
                        type="submit"
                        class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <?php echo $buttonText; ?>
                    </button>
                </div>
            </form>
        </div>

        <!-- Ayuda -->
        <div class="bg-blue-50 rounded-lg p-4 mt-6">
            <h3 class="text-lg font-medium text-blue-900 mb-2">Ayuda</h3>
            <ul class="text-blue-800 text-sm space-y-1">
                <li>• Los campos marcados con <span class="text-red-500">*</span> son obligatorios</li>
                <li>• El correo electrónico debe ser único en el sistema</li>
                <li>• La dirección y teléfono son opcionales pero recomendados</li>
                <?php if ($isEdit): ?>
                    <li>• Cambiar el estado a "Inactivo" ocultará el cliente de las cotizaciones</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</body>
</html>