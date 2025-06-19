<?php
// Vista del formulario de productos (crear/editar)
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
$product = null;
$error = '';
$success = '';

// Si es edición, obtener datos del producto
if ($isEdit) {
    $productId = (int)$_GET['id'];
    $result = $controller->getProductById($productId);
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $product = $result['product'];
    }
}

// Obtener categorías activas para el select
$categories = $controller->getActiveCategories();

// Si no hay categorías, redirigir a crear una
if (empty($categories) && !$error) {
    $error = 'Debe crear al menos una categoría antes de crear productos.';
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEdit && $product) {
        $result = $controller->updateProduct();
    } else {
        $result = $controller->createProduct();
    }
    
    if (isset($result['error'])) {
        $error = $result['error'];
    }
    // Si no hay error, el controlador ya redirigió
}

$pageTitle = $isEdit ? 'Editar Producto' : 'Nuevo Producto';
$buttonText = $isEdit ? 'Actualizar Producto' : 'Crear Producto';

// Unidades comunes para el datalist
$commonUnits = [
    'pieza', 'piezas', 'unidad', 'unidades',
    'kg', 'kilogramo', 'kilogramos', 'gramo', 'gramos',
    'litro', 'litros', 'ml', 'mililitro', 'mililitros',
    'metro', 'metros', 'cm', 'centímetro', 'centímetros',
    'hora', 'horas', 'día', 'días', 'mes', 'meses',
    'servicio', 'servicios', 'consulta', 'consultas'
];
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
            const categoryId = document.getElementById('category_id').value;
            const basePrice = parseFloat(document.getElementById('base_price').value);
            const taxRate = parseFloat(document.getElementById('tax_rate').value);
            const unit = document.getElementById('unit').value.trim();
            const stock = document.getElementById('stock').value.trim();
            const errorElement = document.getElementById('js-error');
            
            // Limpiar errores previos
            errorElement.textContent = '';
            errorElement.classList.add('hidden');
            
            // Validar campos requeridos
            if (!name || !categoryId || !basePrice || !unit) {
                showError('Nombre, categoría, precio base y unidad son requeridos.');
                return false;
            }
            
            // Validar longitud del nombre
            if (name.length > <?php echo MAX_NAME_LENGTH; ?>) {
                showError('El nombre es demasiado largo (máximo <?php echo MAX_NAME_LENGTH; ?> caracteres).');
                return false;
            }
            
            // Validar precio base
            if (isNaN(basePrice) || basePrice <= 0) {
                showError('El precio base debe ser un número mayor a 0.');
                return false;
            }
            
            // Validar tasa de impuesto
            if (isNaN(taxRate) || taxRate < 0 || taxRate > 100) {
                showError('La tasa de impuesto debe estar entre 0% y 100%.');
                return false;
            }
            
            // Validar unidad
            if (unit.length > 20) {
                showError('La unidad es demasiado larga (máximo 20 caracteres).');
                return false;
            }
            
            // Validar stock si se proporciona
            if (stock && (isNaN(parseInt(stock)) || parseInt(stock) < 0)) {
                showError('El stock debe ser un número entero mayor o igual a 0.');
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
        
        // Calcular precio final en tiempo real
        function calculateFinalPrice() {
            const basePrice = parseFloat(document.getElementById('base_price').value) || 0;
            const taxRate = parseFloat(document.getElementById('tax_rate').value) || 0;
            
            const taxAmount = (basePrice * taxRate) / 100;
            const finalPrice = basePrice + taxAmount;
            
            document.getElementById('final_price_display').textContent = '$' + finalPrice.toFixed(2);
            document.getElementById('tax_amount_display').textContent = '$' + taxAmount.toFixed(2);
        }
        
        // Función para confirmar cancelación si hay cambios
        function confirmCancel() {
            const form = document.getElementById('product-form');
            const formData = new FormData(form);
            let hasChanges = false;
            
            // Verificar si hay cambios en el formulario
            <?php if ($isEdit): ?>
                const originalData = {
                    name: '<?php echo Security::escape($product['name'] ?? ''); ?>',
                    description: '<?php echo Security::escape($product['description'] ?? ''); ?>',
                    category_id: '<?php echo $product['category_id'] ?? ''; ?>',
                    base_price: '<?php echo $product['base_price'] ?? ''; ?>',
                    tax_rate: '<?php echo $product['tax_rate'] ?? ''; ?>',
                    unit: '<?php echo Security::escape($product['unit'] ?? ''); ?>',
                    stock: '<?php echo $product['stock'] ?? ''; ?>',
                    status: '<?php echo $product['status'] ?? STATUS_ACTIVE; ?>'
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

        // Validación en tiempo real del stock
        function validateStock() {
            const stockInput = document.getElementById('stock');
            const stockValue = stockInput.value.trim();
            const stockFeedback = document.getElementById('stock-feedback');
            
            if (!stockValue) {
                stockFeedback.textContent = '';
                return;
            }
            
            const stock = parseInt(stockValue);
            if (isNaN(stock) || stock < 0) {
                stockFeedback.textContent = 'El stock debe ser un número entero mayor o igual a 0';
                stockFeedback.className = 'text-red-500 text-sm mt-1';
            } else if (stock <= 10) {
                stockFeedback.textContent = 'Advertencia: Stock bajo (≤10 unidades)';
                stockFeedback.className = 'text-yellow-600 text-sm mt-1';
            } else {
                stockFeedback.textContent = 'Stock disponible';
                stockFeedback.className = 'text-green-500 text-sm mt-1';
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
                    <a href="productList.php" 
                       onclick="return confirmCancel()"
                       class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        ← Volver al Listado
                    </a>
                    <a href="categoryList.php" 
                       class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        Gestionar Categorías
                    </a>
                </div>
            </div>
        </div>

        <!-- Mensajes de error -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?php echo Security::escape($error); ?>
                <?php if (empty($categories)): ?>
                    <div class="mt-2">
                        <a href="categoryForm.php" class="text-blue-600 underline">
                            Crear primera categoría →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Error de validación JavaScript -->
        <div id="js-error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 hidden"></div>

        <?php if (!empty($categories)): ?>
        <!-- Formulario -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <form id="product-form" method="POST" onsubmit="return validateForm()">
                <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?php echo $product['id']; ?>">
                <?php endif; ?>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    
                    <!-- Información básica -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Información Básica</h3>
                        
                        <!-- Nombre -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                Nombre del Producto <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="name" 
                                name="name" 
                                value="<?php echo Security::escape($product['name'] ?? ''); ?>"
                                maxlength="<?php echo MAX_NAME_LENGTH; ?>"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Ingrese el nombre del producto"
                                required
                            >
                            <div class="text-gray-500 text-sm mt-1">
                                Máximo <?php echo MAX_NAME_LENGTH; ?> caracteres
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 mb-2">
                                Descripción
                            </label>
                            <textarea 
                                id="description" 
                                name="description" 
                                rows="4"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Descripción detallada del producto (opcional)"
                            ><?php echo Security::escape($product['description'] ?? ''); ?></textarea>
                            <div class="text-gray-500 text-sm mt-1">
                                Opcional - Descripción completa del producto
                            </div>
                        </div>

                        <!-- Categoría -->
                        <div>
                            <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Categoría <span class="text-red-500">*</span>
                            </label>
                            <select 
                                id="category_id" 
                                name="category_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                required
                            >
                                <option value="">Seleccione una categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>" 
                                            <?php echo (isset($product['category_id']) && $product['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                        <?php echo Security::escape($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="text-gray-500 text-sm mt-1">
                                <a href="categoryForm.php" class="text-blue-600 hover:underline">
                                    ¿No encuentra la categoría? Crear nueva →
                                </a>
                            </div>
                        </div>

                        <!-- Unidad -->
                        <div>
                            <label for="unit" class="block text-sm font-medium text-gray-700 mb-2">
                                Unidad de Medida <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                id="unit" 
                                name="unit" 
                                value="<?php echo Security::escape($product['unit'] ?? ''); ?>"
                                maxlength="20"
                                list="common-units"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="ej: pieza, kg, litro, hora"
                                required
                            >
                            <datalist id="common-units">
                                <?php foreach ($commonUnits as $unit): ?>
                                    <option value="<?php echo $unit; ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <div class="text-gray-500 text-sm mt-1">
                                Máximo 20 caracteres (ej: pieza, kg, litro, servicio)
                            </div>
                        </div>
                    </div>

                    <!-- Información de precios e inventario -->
                    <div class="space-y-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Precios e Inventario</h3>
                        
                        <!-- Precio base -->
                        <div>
                            <label for="base_price" class="block text-sm font-medium text-gray-700 mb-2">
                                Precio Base <span class="text-red-500">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-2 text-gray-500">$</span>
                                <input 
                                    type="number" 
                                    id="base_price" 
                                    name="base_price" 
                                    value="<?php echo $product['base_price'] ?? ''; ?>"
                                    step="0.01"
                                    min="0"
                                    class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    placeholder="0.00"
                                    onchange="calculateFinalPrice()"
                                    onkeyup="calculateFinalPrice()"
                                    required
                                >
                            </div>
                            <div class="text-gray-500 text-sm mt-1">
                                Precio sin impuestos
                            </div>
                        </div>

                        <!-- Tasa de impuesto -->
                        <div>
                            <label for="tax_rate" class="block text-sm font-medium text-gray-700 mb-2">
                                Tasa de Impuesto (%)
                            </label>
                            <input 
                                type="number" 
                                id="tax_rate" 
                                name="tax_rate" 
                                value="<?php echo $product['tax_rate'] ?? '0'; ?>"
                                step="0.01"
                                min="0"
                                max="100"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="0.00"
                                onchange="calculateFinalPrice()"
                                onkeyup="calculateFinalPrice()"
                            >
                            <div class="text-gray-500 text-sm mt-1">
                                Entre 0% y 100%. Ej: 16 para IVA del 16%
                            </div>
                        </div>

                        <!-- Cálculo automático de precio final -->
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                            <h4 class="font-medium text-blue-800 mb-2">Cálculo de Precio</h4>
                            <div class="space-y-1 text-sm">
                                <div class="flex justify-between">
                                    <span>Impuesto:</span>
                                    <span id="tax_amount_display" class="font-medium">$0.00</span>
                                </div>
                                <div class="flex justify-between text-lg font-bold text-blue-800 border-t pt-1">
                                    <span>Precio Final:</span>
                                    <span id="final_price_display">$0.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- Stock -->
                        <div>
                            <label for="stock" class="block text-sm font-medium text-gray-700 mb-2">
                                Stock Inicial
                            </label>
                            <input 
                                type="number" 
                                id="stock" 
                                name="stock" 
                                value="<?php echo $product['stock'] ?? ''; ?>"
                                min="0"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Dejar vacío si no se maneja inventario"
                                onblur="validateStock()"
                                onkeyup="validateStock()"
                            >
                            <div id="stock-feedback"></div>
                            <div class="text-gray-500 text-sm mt-1">
                                Opcional - Dejar vacío para productos sin control de inventario
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
                                    <option value="<?php echo STATUS_ACTIVE; ?>" <?php echo ($product['status'] ?? STATUS_ACTIVE) == STATUS_ACTIVE ? 'selected' : ''; ?>>
                                        Activo
                                    </option>
                                    <option value="<?php echo STATUS_INACTIVE; ?>" <?php echo ($product['status'] ?? STATUS_ACTIVE) == STATUS_INACTIVE ? 'selected' : ''; ?>>
                                        Inactivo
                                    </option>
                                </select>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Información adicional en edición -->
                <?php if ($isEdit && $product): ?>
                    <div class="mt-6 pt-6 border-t border-gray-200">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Información del Registro</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm text-gray-600">
                            <div>
                                <strong>Creado:</strong> <?php echo Utils::formatDateDisplay($product['created_at']); ?>
                            </div>
                            <?php if ($product['updated_at']): ?>
                                <div>
                                    <strong>Última actualización:</strong> <?php echo Utils::formatDateDisplay($product['updated_at']); ?>
                                </div>
                            <?php endif; ?>
                            <div>
                                <strong>Categoría actual:</strong> <?php echo Security::escape($product['category_name'] ?? 'Sin categoría'); ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Botones -->
                <div class="mt-8 flex justify-end space-x-4">
                    <a href="productList.php" 
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
        <?php endif; ?>

        <!-- Ayuda -->
        <div class="bg-blue-50 rounded-lg p-4 mt-6">
            <h3 class="text-lg font-medium text-blue-900 mb-2">Ayuda</h3>
            <ul class="text-blue-800 text-sm space-y-1">
                <li>• Los campos marcados con <span class="text-red-500">*</span> son obligatorios</li>
                <li>• El precio final se calcula automáticamente sumando impuestos al precio base</li>
                <li>• El stock es opcional - úselo solo si maneja inventario físico</li>
                <li>• Los productos inactivos no aparecerán en cotizaciones ni ventas</li>
                <?php if (!empty($categories)): ?>
                    <li>• Puede gestionar las categorías desde el botón "Categorías" en la parte superior</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <script>
        // Inicializar cálculos al cargar la página
        document.addEventListener('DOMContentLoaded', function() {
            calculateFinalPrice();
            validateStock();
        });
    </script>
</body>
</html>