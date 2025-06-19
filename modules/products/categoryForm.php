<?php
// Vista del formulario de categorías (crear/editar)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/products/productController.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Instanciar controlador
try {
    $controller = new ProductController();
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
$category = null;
$error = '';
$success = '';

// Si es edición, obtener datos de la categoría
if ($isEdit) {
    $categoryId = (int)$_GET['id'];
    $result = $controller->getCategoryById($categoryId);
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $category = $result['category'];
    }
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEdit && $category) {
        $result = $controller->updateCategory();
    } else {
        $result = $controller->createCategory();
    }
    
    if (isset($result['error'])) {
        $error = $result['error'];
    }
    // Si no hay error, el controlador ya redirigió
}

$pageTitle = $isEdit ? 'Editar Categoría' : 'Nueva Categoría';
$buttonText = $isEdit ? 'Actualizar Categoría' : 'Crear Categoría';
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
            const errorElement = document.getElementById('js-error');
            
            // Limpiar errores previos
            errorElement.textContent = '';
            errorElement.classList.add('hidden');
            
            // Validar nombre requerido
            if (!name) {
                showError('El nombre de la categoría es requerido.');
                return false;
            }
            
            // Validar longitud del nombre
            if (name.length > 50) {
                showError('El nombre es demasiado largo (máximo 50 caracteres).');
                return false;
            }
            
            // Validar caracteres especiales (solo letras, números, espacios y algunos símbolos básicos)
            if (!/^[a-zA-Z0-9\s\-_&().]+$/.test(name)) {
                showError('El nombre contiene caracteres no válidos. Use solo letras, números, espacios, guiones y paréntesis.');
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
        
        // Función para confirmar cancelación si hay cambios
        function confirmCancel() {
            const form = document.getElementById('category-form');
            const formData = new FormData(form);
            let hasChanges = false;
            
            // Verificar si hay cambios en el formulario
            <?php if ($isEdit): ?>
                const originalData = {
                    name: '<?php echo Security::escape($category['name'] ?? ''); ?>',
                    status: '<?php echo $category['status'] ?? STATUS_ACTIVE; ?>'
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

        // Validación en tiempo real del nombre
        function validateName() {
            const name = document.getElementById('name').value.trim();
            const feedback = document.getElementById('name-feedback');
            
            if (!name) {
                feedback.textContent = '';
                return;
            }
            
            if (name.length > 50) {
                feedback.textContent = 'Demasiado largo (máximo 50 caracteres)';
                feedback.className = 'text-red-500 text-sm mt-1';
                return;
            }
            
            if (!/^[a-zA-Z0-9\s\-_&().]+$/.test(name)) {
                feedback.textContent = 'Contiene caracteres no válidos';
                feedback.className = 'text-red-500 text-sm mt-1';
                return;
            }
            
            feedback.textContent = 'Nombre válido';
            feedback.className = 'text-green-500 text-sm mt-1';
        }

        // Función para generar sugerencias de nombres
        function suggestNames() {
            const suggestions = [
                'Electrónicos', 'Ropa y Accesorios', 'Hogar y Jardín', 'Deportes y Fitness',
                'Libros y Educación', 'Salud y Belleza', 'Automóviles', 'Juguetes y Bebés',
                'Alimentos y Bebidas', 'Oficina y Papelería', 'Servicios Profesionales',
                'Construcción y Herramientas', 'Arte y Manualidades', 'Música e Instrumentos',
                'Mascotas y Animales', 'Viajes y Turismo', 'Tecnología', 'Moda',
                'Entretenimiento', 'Limpieza e Higiene'
            ];
            
            const nameInput = document.getElementById('name');
            const suggestionsList = document.getElementById('suggestions-list');
            
            // Mostrar/ocultar sugerencias
            if (suggestionsList.classList.contains('hidden')) {
                suggestionsList.innerHTML = '';
                suggestions.forEach(function(suggestion) {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'block w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-100 border-b';
                    button.textContent = suggestion;
                    button.onclick = function() {
                        nameInput.value = suggestion;
                        suggestionsList.classList.add('hidden');
                        validateName();
                    };
                    suggestionsList.appendChild(button);
                });
                suggestionsList.classList.remove('hidden');
            } else {
                suggestionsList.classList.add('hidden');
            }
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
                    <a href="categoryList.php" 
                       onclick="return confirmCancel()"
                       class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        ← Volver a Categorías
                    </a>
                    <a href="productList.php" 
                       class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Ver Productos
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
            <form id="category-form" method="POST" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $category['id']; ?>">
                <?php endif; ?>

                <div class="max-w-md mx-auto space-y-6">
                    
                    <!-- Nombre de la categoría -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                            Nombre de la Categoría <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                value="<?php echo Security::escape($category['name'] ?? ''); ?>"
                                maxlength="50"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                placeholder="Ingrese el nombre de la categoría"
                                onblur="validateName()"
                                onkeyup="validateName()"
                                required
                            >
                            <button 
                                type="button" 
                                onclick="suggestNames()"
                                class="absolute right-2 top-2 text-gray-400 hover:text-gray-600"
                                title="Ver sugerencias"
                            >
                                💡
                            </button>
                        </div>
                        <div id="name-feedback"></div>
                        <div class="text-gray-500 text-sm mt-1">
                            Máximo 50 caracteres. Use nombres descriptivos como "Electrónicos", "Ropa", etc.
                        </div>
                        
                        <!-- Lista de sugerencias -->
                        <div id="suggestions-list" class="hidden absolute z-10 w-full bg-white border border-gray-300 rounded-md shadow-lg mt-1 max-h-48 overflow-y-auto">
                            <!-- Las sugerencias se cargan dinámicamente -->
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
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            >
                                <option value="<?php echo STATUS_ACTIVE; ?>" <?php echo ($category['status'] ?? STATUS_ACTIVE) == STATUS_ACTIVE ? 'selected' : ''; ?>>
                                    Activa
                                </option>
                                <option value="<?php echo STATUS_INACTIVE; ?>" <?php echo ($category['status'] ?? STATUS_ACTIVE) == STATUS_INACTIVE ? 'selected' : ''; ?>>
                                    Inactiva
                                </option>
                            </select>
                            <div class="text-gray-500 text-sm mt-1">
                                Las categorías inactivas no aparecerán al crear productos
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Información adicional en edición -->
                    <?php if ($isEdit && $category): ?>
                        <div class="pt-6 border-t border-gray-200">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Registro</h3>
                            <div class="space-y-2 text-sm text-gray-600">
                                <div>
                                    <strong>ID de Categoría:</strong> <?php echo $category['id']; ?>
                                </div>
                                <div>
                                    <strong>Productos asociados:</strong> 
                                    <a href="productList.php?category_id=<?php echo $category['id']; ?>" class="text-blue-600 hover:underline">
                                        Ver productos de esta categoría →
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Botones -->
                    <div class="flex justify-end space-x-4 pt-6">
                        <a href="categoryList.php" 
                           onclick="return confirmCancel()"
                           class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button 
                            type="submit"
                            class="px-6 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500"
                        >
                            <?php echo $buttonText; ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Información y consejos -->
        <div class="bg-green-50 rounded-lg p-4 mt-6">
            <h3 class="text-lg font-medium text-green-900 mb-2">💡 Consejos para Categorías</h3>
            <ul class="text-green-800 text-sm space-y-1">
                <li>• Use nombres claros y descriptivos que faciliten la organización</li>
                <li>• Evite crear demasiadas categorías - mantenga una estructura simple</li>
                <li>• Las categorías ayudan a los clientes a encontrar productos más fácilmente</li>
                <li>• Puede cambiar el estado de una categoría en lugar de eliminarla</li>
                <?php if ($isEdit): ?>
                    <li>• Los productos asociados seguirán funcionando aunque la categoría se desactive</li>
                <?php else: ?>
                    <li>• Después de crear la categoría, podrá asignarle productos</li>
                <?php endif; ?>
            </ul>
        </div>

        <!-- Ejemplos de categorías -->
        <?php if (!$isEdit): ?>
            <div class="bg-blue-50 rounded-lg p-4 mt-4">
                <h3 class="text-lg font-medium text-blue-900 mb-2">📋 Ejemplos de Categorías</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2 text-blue-800 text-sm">
                    <div>• Electrónicos</div>
                    <div>• Ropa y Moda</div>
                    <div>• Hogar y Jardín</div>
                    <div>• Deportes</div>
                    <div>• Libros</div>
                    <div>• Salud y Belleza</div>
                    <div>• Automóviles</div>
                    <div>• Servicios</div>
                </div>
                <div class="text-blue-700 text-sm mt-2">
                    💡 Haga clic en el ícono de bombilla en el campo de nombre para ver más sugerencias
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Cerrar sugerencias al hacer clic fuera
        document.addEventListener('click', function(e) {
            const suggestionsList = document.getElementById('suggestions-list');
            const nameInput = document.getElementById('name');
            
            if (!nameInput.contains(e.target) && !suggestionsList.contains(e.target)) {
                suggestionsList.classList.add('hidden');
            }
        });
    </script>
</body>
</html>