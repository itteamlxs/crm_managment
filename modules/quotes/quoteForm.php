<?php
// Vista del formulario de cotizaciones (crear/editar)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/quotes/quoteController.php';
    require_once dirname(__DIR__) . '/quotes/quoteModel.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

// Instanciar controlador
try {
    $controller = new QuoteController();
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
$quote = null;
$error = '';
$success = '';

// Si es edición, obtener datos de la cotización
if ($isEdit) {
    $quoteId = (int)$_GET['id'];
    $result = $controller->getQuoteById($quoteId);
    if (isset($result['error'])) {
        $error = $result['error'];
    } else {
        $quote = $result['quote'];
        // Verificar si se puede editar
        if (!$controller->canEditQuote($quote['status'])) {
            $error = 'No se puede editar una cotización en estado ' . (new QuoteModel())->getStatusName($quote['status']) . '.';
        }
    }
}

// Obtener datos necesarios para el formulario
$clients = $controller->getActiveClients();
$products = $controller->getActiveProducts();

// Verificar dependencias
if (empty($clients) && !$error) {
    $error = 'Debe crear al menos un cliente antes de crear cotizaciones.';
}
if (empty($products) && !$error) {
    $error = 'Debe crear al menos un producto antes de crear cotizaciones.';
}

// Procesar formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isEdit && $quote && !$error) {
        $result = $controller->updateQuote();
    } elseif (!$isEdit && !$error) {
        $result = $controller->createQuote();
    }
    
    if (isset($result['error'])) {
        $error = $result['error'];
    }
    // Si no hay error, el controlador ya redirigió
}

$pageTitle = $isEdit ? 'Editar Cotización' : 'Nueva Cotización';
$buttonText = $isEdit ? 'Actualizar Cotización' : 'Crear Cotización';

// Preparar datos iniciales para JavaScript
$initialItems = [];
if ($isEdit && $quote && isset($quote['items'])) {
    foreach ($quote['items'] as $item) {
        $initialItems[] = [
            'product_id' => $item['product_id'],
            'product_name' => $item['product_name'],
            'quantity' => $item['quantity'],
            'unit_price' => $item['unit_price'],
            'discount' => $item['discount_percent'],
            'line_total' => $item['line_total_with_tax']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .item-row { transition: all 0.3s ease; }
        .item-row.removing { opacity: 0; transform: translateX(-100%); }
        .calculate-highlight { animation: highlight 0.5s ease; }
        @keyframes highlight { 0% { background-color: #fef3cd; } 100% { background-color: transparent; } }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center">
                <h1 class="text-3xl font-bold text-gray-800"><?php echo $pageTitle; ?></h1>
                <div class="flex space-x-2">
                    <a href="quoteList.php" 
                       onclick="return confirmCancel()"
                       class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        ← Volver al Listado
                    </a>
                    <?php if ($isEdit && $quote): ?>
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full <?php 
                            switch($quote['status']) {
                                case QUOTE_STATUS_DRAFT: echo 'bg-gray-100 text-gray-800'; break;
                                case QUOTE_STATUS_SENT: echo 'bg-blue-100 text-blue-800'; break;
                                case QUOTE_STATUS_APPROVED: echo 'bg-green-100 text-green-800'; break;
                                case QUOTE_STATUS_REJECTED: echo 'bg-red-100 text-red-800'; break;
                                case QUOTE_STATUS_EXPIRED: echo 'bg-yellow-100 text-yellow-800'; break;
                                case QUOTE_STATUS_CANCELLED: echo 'bg-gray-100 text-gray-600'; break;
                                default: echo 'bg-gray-100 text-gray-800';
                            }
                        ?>">
                            <?php echo (new QuoteModel())->getStatusName($quote['status']); ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mensajes de error -->
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <strong>Error:</strong> <?php echo Security::escape($error); ?>
                <?php if (empty($clients)): ?>
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>/modules/clients/clientForm.php" class="text-blue-600 underline">
                            Crear primer cliente →
                        </a>
                    </div>
                <?php endif; ?>
                <?php if (empty($products)): ?>
                    <div class="mt-2">
                        <a href="<?php echo BASE_URL; ?>/modules/products/productForm.php" class="text-blue-600 underline">
                            Crear primer producto →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Error de validación JavaScript -->
        <div id="js-error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4 hidden"></div>

        <?php if (!empty($clients) && !empty($products) && !$error): ?>
        <!-- Formulario -->
        <form id="quote-form" method="POST" onsubmit="return validateForm()">
            <input type="hidden" name="csrf_token" value="<?php echo Security::escape($controller->getCsrfToken()); ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?php echo $quote['id']; ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                
                <!-- Información básica de la cotización -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Datos principales -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Información de la Cotización</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Cliente -->
                            <div>
                                <label for="client_id" class="block text-sm font-medium text-gray-700 mb-2">
                                    Cliente <span class="text-red-500">*</span>
                                </label>
                                <select 
                                    id="client_id" 
                                    name="client_id"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required
                                    onchange="updateClientInfo()"
                                >
                                    <option value="">Seleccione un cliente</option>
                                    <?php foreach ($clients as $client): ?>
                                        <option value="<?php echo $client['id']; ?>" 
                                                data-email="<?php echo Security::escape($client['email']); ?>"
                                                data-phone="<?php echo Security::escape($client['phone'] ?? ''); ?>"
                                                <?php echo (isset($quote['client_id']) && $quote['client_id'] == $client['id']) ? 'selected' : ''; ?>>
                                            <?php echo Security::escape($client['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div id="client-info" class="mt-2 text-sm text-gray-600"></div>
                            </div>

                            <!-- Fecha de validez -->
                            <div>
                                <label for="valid_until" class="block text-sm font-medium text-gray-700 mb-2">
                                    Válida Hasta <span class="text-red-500">*</span>
                                </label>
                                <input 
                                    type="date" 
                                    id="valid_until" 
                                    name="valid_until" 
                                    value="<?php echo $quote['valid_until'] ?? date('Y-m-d', strtotime('+30 days')); ?>"
                                    min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                    required
                                >
                                <div class="text-gray-500 text-sm mt-1">
                                    Fecha límite para aceptar la cotización
                                </div>
                            </div>
                        </div>

                        <!-- Notas -->
                        <div class="mt-4">
                            <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                                Notas y Observaciones
                            </label>
                            <textarea 
                                id="notes" 
                                name="notes" 
                                rows="3"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Condiciones especiales, términos de pago, etc. (opcional)"
                            ><?php echo Security::escape($quote['notes'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <!-- Items de la cotización -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center border-b pb-2 mb-4">
                            <h3 class="text-lg font-semibold text-gray-800">Productos y Servicios</h3>
                            <button type="button" onclick="addItem()" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                + Agregar Producto
                            </button>
                        </div>

                        <!-- Encabezados de la tabla -->
                        <div class="hidden md:grid md:grid-cols-12 gap-2 mb-2 text-sm font-medium text-gray-600">
                            <div class="col-span-4">Producto</div>
                            <div class="col-span-2">Cantidad</div>
                            <div class="col-span-2">Precio Unit.</div>
                            <div class="col-span-2">Desc. %</div>
                            <div class="col-span-1">Total</div>
                            <div class="col-span-1">Acciones</div>
                        </div>

                        <!-- Container para items -->
                        <div id="items-container">
                            <!-- Los items se agregan dinámicamente -->
                        </div>

                        <!-- Mensaje cuando no hay items -->
                        <div id="no-items-message" class="text-center py-8 text-gray-500">
                            <p>No hay productos agregados</p>
                            <button type="button" onclick="addItem()" class="mt-2 text-blue-600 hover:text-blue-800">
                                Agregar primer producto →
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Resumen y totales -->
                <div class="space-y-6">
                    
                    <!-- Descuento global -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Descuentos</h3>
                        
                        <div>
                            <label for="discount" class="block text-sm font-medium text-gray-700 mb-2">
                                Descuento Global (%)
                            </label>
                            <input 
                                type="number" 
                                id="discount" 
                                name="discount" 
                                value="<?php echo $quote['discount_percent'] ?? '0'; ?>"
                                step="0.01"
                                min="0"
                                max="100"
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                placeholder="0.00"
                                onchange="calculateTotals()"
                                onkeyup="calculateTotals()"
                            >
                            <div class="text-gray-500 text-sm mt-1">
                                Descuento aplicado sobre el total
                            </div>
                        </div>
                    </div>

                    <!-- Totales -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold text-gray-800 border-b pb-2 mb-4">Resumen</h3>
                        
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span>Subtotal:</span>
                                <span id="subtotal-display" class="font-medium">$0.00</span>
                            </div>
                            <div class="flex justify-between text-green-600">
                                <span>Descuentos:</span>
                                <span id="discount-display" class="font-medium">-$0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span>Impuestos:</span>
                                <span id="tax-display" class="font-medium">$0.00</span>
                            </div>
                            <div class="border-t pt-3 flex justify-between text-lg font-bold text-blue-600">
                                <span>Total:</span>
                                <span id="total-display">$0.00</span>
                            </div>
                        </div>

                        <div class="mt-4 p-3 bg-blue-50 rounded text-sm text-blue-700">
                            <strong>Nota:</strong> Los totales se calculan automáticamente incluyendo impuestos por producto.
                        </div>
                    </div>

                    <!-- Información adicional en edición -->
                    <?php if ($isEdit && $quote): ?>
                        <div class="bg-gray-50 rounded-lg p-4">
                            <h3 class="text-lg font-medium text-gray-900 mb-3">Información del Registro</h3>
                            <div class="space-y-2 text-sm text-gray-600">
                                <div><strong>Número:</strong> <?php echo Security::escape($quote['quote_number']); ?></div>
                                <div><strong>Creada:</strong> <?php echo Utils::formatDateDisplay($quote['created_at']); ?></div>
                                <?php if ($quote['updated_at']): ?>
                                    <div><strong>Actualizada:</strong> <?php echo Utils::formatDateDisplay($quote['updated_at']); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Botones -->
                    <div class="flex flex-col space-y-2">
                        <button 
                            type="submit"
                            class="w-full px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 font-medium"
                        >
                            <?php echo $buttonText; ?>
                        </button>
                        <a href="quoteList.php" 
                           onclick="return confirmCancel()"
                           class="w-full px-6 py-3 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50 text-center">
                            Cancelar
                        </a>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <!-- Ayuda -->
        <div class="bg-blue-50 rounded-lg p-4 mt-6">
            <h3 class="text-lg font-medium text-blue-900 mb-2">💡 Ayuda</h3>
            <ul class="text-blue-800 text-sm space-y-1">
                <li>• Los campos marcados con <span class="text-red-500">*</span> son obligatorios</li>
                <li>• Los totales se calculan automáticamente al agregar productos</li>
                <li>• Puede aplicar descuentos individuales por producto y un descuento global</li>
                <li>• La fecha de validez debe ser posterior a hoy</li>
                <li>• Use las notas para condiciones especiales o términos de pago</li>
                <?php if (!empty($clients) && !empty($products)): ?>
                    <li>• Agregue productos con el botón verde "Agregar Producto"</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>

    <script>
        // Variables globales
        let itemCounter = 0;
        let products = <?php echo json_encode($products); ?>;
        let initialItems = <?php echo json_encode($initialItems); ?>;

        // Inicializar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            // Cargar items iniciales si estamos editando
            if (initialItems && initialItems.length > 0) {
                initialItems.forEach(function(item) {
                    addItemWithData(item);
                });
            }
            
            // Calcular totales iniciales
            calculateTotals();
            
            // Actualizar info del cliente si ya está seleccionado
            updateClientInfo();
        });

        // Agregar nuevo item vacío
        function addItem() {
            addItemWithData({});
        }

        // Agregar item con datos específicos
        function addItemWithData(itemData = {}) {
            itemCounter++;
            const container = document.getElementById('items-container');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'item-row bg-gray-50 p-4 rounded-lg mb-4';
            itemDiv.id = 'item-' + itemCounter;

            itemDiv.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-12 gap-2 items-end">
                    <!-- Producto -->
                    <div class="md:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1 md:hidden">Producto</label>
                        <select name="items[${itemCounter}][product_id]" class="product-select w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="updateProductInfo(${itemCounter})" required>
                            <option value="">Seleccione un producto</option>
                            ${products.map(product => `
                                <option value="${product.id}" 
                                        data-price="${product.base_price}" 
                                        data-tax="${product.tax_rate}"
                                        data-unit="${product.unit}"
                                        ${itemData.product_id == product.id ? 'selected' : ''}>
                                    ${product.name} (${product.unit})
                                </option>
                            `).join('')}
                        </select>
                        <div class="product-info-${itemCounter} text-xs text-gray-500 mt-1"></div>
                    </div>

                    <!-- Cantidad -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1 md:hidden">Cantidad</label>
                        <input type="number" 
                               name="items[${itemCounter}][quantity]" 
                               value="${itemData.quantity || '1'}"
                               min="1" 
                               step="1"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="1"
                               onchange="calculateItemTotal(${itemCounter})"
                               required>
                    </div>

                    <!-- Precio unitario -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1 md:hidden">Precio Unit.</label>
                        <input type="number" 
                               name="items[${itemCounter}][unit_price]" 
                               value="${itemData.unit_price || ''}"
                               step="0.01" 
                               min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="0.00"
                               onchange="calculateItemTotal(${itemCounter})"
                               required>
                    </div>

                    <!-- Descuento % -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1 md:hidden">Desc. %</label>
                        <input type="number" 
                               name="items[${itemCounter}][discount]" 
                               value="${itemData.discount || '0'}"
                               step="0.01" 
                               min="0" 
                               max="100"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                               placeholder="0.00"
                               onchange="calculateItemTotal(${itemCounter})">
                    </div>

                    <!-- Total del item -->
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700 mb-1 md:hidden">Total</label>
                        <div class="item-total-${itemCounter} text-sm font-medium text-gray-900 py-2 px-3 bg-white border rounded-md">
                            $0.00
                        </div>
                    </div>

                    <!-- Acciones -->
                    <div class="md:col-span-1">
                        <button type="button" 
                                onclick="removeItem(${itemCounter})" 
                                class="w-full px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 text-sm">
                            ✕
                        </button>
                    </div>
                </div>
            `;

            container.appendChild(itemDiv);
            updateNoItemsMessage();

            // Si tiene datos, actualizar información del producto y calcular
            if (itemData.product_id) {
                updateProductInfo(itemCounter);
                calculateItemTotal(itemCounter);
            }
        }

        // Remover item
        function removeItem(itemId) {
            const itemDiv = document.getElementById('item-' + itemId);
            if (itemDiv) {
                itemDiv.classList.add('removing');
                setTimeout(() => {
                    itemDiv.remove();
                    updateNoItemsMessage();
                    calculateTotals();
                }, 300);
            }
        }

        // Actualizar mensaje cuando no hay items
        function updateNoItemsMessage() {
            const container = document.getElementById('items-container');
            const message = document.getElementById('no-items-message');
            const hasItems = container.children.length > 0;
            
            message.style.display = hasItems ? 'none' : 'block';
        }

        // Actualizar información del producto seleccionado
        function updateProductInfo(itemId) {
            const select = document.querySelector(`select[name="items[${itemId}][product_id]"]`);
            const priceInput = document.querySelector(`input[name="items[${itemId}][unit_price]"]`);
            const infoDiv = document.querySelector(`.product-info-${itemId}`);
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const basePrice = parseFloat(option.getAttribute('data-price'));
                const taxRate = parseFloat(option.getAttribute('data-tax'));
                const unit = option.getAttribute('data-unit');
                
                // Actualizar precio si está vacío
                if (!priceInput.value) {
                    priceInput.value = basePrice.toFixed(2);
                }
                
                // Mostrar información adicional
                const finalPrice = basePrice + (basePrice * taxRate / 100);
                infoDiv.innerHTML = `Precio base: $${basePrice.toFixed(2)} + ${taxRate}% imp. = $${finalPrice.toFixed(2)}`;
            } else {
                infoDiv.innerHTML = '';
            }
            
            calculateItemTotal(itemId);
        }

        // Calcular total de un item específico
        function calculateItemTotal(itemId) {
            const select = document.querySelector(`select[name="items[${itemId}][product_id]"]`);
            const quantity = parseFloat(document.querySelector(`input[name="items[${itemId}][quantity]"]`).value) || 0;
            const unitPrice = parseFloat(document.querySelector(`input[name="items[${itemId}][unit_price]"]`).value) || 0;
            const discount = parseFloat(document.querySelector(`input[name="items[${itemId}][discount]"]`).value) || 0;
            const totalDiv = document.querySelector(`.item-total-${itemId}`);
            
            if (select.value && quantity > 0 && unitPrice > 0) {
                const option = select.options[select.selectedIndex];
                const taxRate = parseFloat(option.getAttribute('data-tax')) || 0;
                
                // Calcular subtotal
                const subtotal = quantity * unitPrice;
                
                // Aplicar descuento
                const discountAmount = (subtotal * discount) / 100;
                const afterDiscount = subtotal - discountAmount;
                
                // Aplicar impuestos
                const taxAmount = (afterDiscount * taxRate) / 100;
                const total = afterDiscount + taxAmount;
                
                totalDiv.textContent = '$' + total.toFixed(2);
                totalDiv.classList.add('calculate-highlight');
                setTimeout(() => totalDiv.classList.remove('calculate-highlight'), 500);
            } else {
                totalDiv.textContent = '$0.00';
            }
            
            // Recalcular totales generales
            calculateTotals();
        }

        // Calcular totales generales
        function calculateTotals() {
            let subtotal = 0;
            let totalDiscountAmount = 0;
            let totalTaxAmount = 0;
            
            // Recorrer todos los items
            document.querySelectorAll('.item-row').forEach(function(row) {
                const itemId = row.id.split('-')[1];
                const select = document.querySelector(`select[name="items[${itemId}][product_id]"]`);
                const quantity = parseFloat(document.querySelector(`input[name="items[${itemId}][quantity]"]`).value) || 0;
                const unitPrice = parseFloat(document.querySelector(`input[name="items[${itemId}][unit_price]"]`).value) || 0;
                const discount = parseFloat(document.querySelector(`input[name="items[${itemId}][discount]"]`).value) || 0;
                
                if (select && select.value && quantity > 0 && unitPrice > 0) {
                    const option = select.options[select.selectedIndex];
                    const taxRate = parseFloat(option.getAttribute('data-tax')) || 0;
                    
                    const lineSubtotal = quantity * unitPrice;
                    const lineDiscountAmount = (lineSubtotal * discount) / 100;
                    const lineAfterDiscount = lineSubtotal - lineDiscountAmount;
                    const lineTaxAmount = (lineAfterDiscount * taxRate) / 100;
                    
                    subtotal += lineSubtotal;
                    totalDiscountAmount += lineDiscountAmount;
                    totalTaxAmount += lineTaxAmount;
                }
            });
            
            // Aplicar descuento global
            const globalDiscount = parseFloat(document.getElementById('discount').value) || 0;
            const subtotalAfterLineDiscounts = subtotal - totalDiscountAmount;
            const globalDiscountAmount = (subtotalAfterLineDiscounts * globalDiscount) / 100;
            const finalSubtotal = subtotalAfterLineDiscounts - globalDiscountAmount;
            
            // Recalcular impuestos proporcionalmente
            const adjustedTaxAmount = subtotalAfterLineDiscounts > 0 ? 
                (totalTaxAmount * finalSubtotal) / subtotalAfterLineDiscounts : 0;
            
            const total = finalSubtotal + adjustedTaxAmount;
            const totalDiscountWithGlobal = totalDiscountAmount + globalDiscountAmount;
            
            // Actualizar display
            document.getElementById('subtotal-display').textContent = '$' + subtotal.toFixed(2);
            document.getElementById('discount-display').textContent = '-$' + totalDiscountWithGlobal.toFixed(2);
            document.getElementById('tax-display').textContent = '$' + adjustedTaxAmount.toFixed(2);
            document.getElementById('total-display').textContent = '$' + total.toFixed(2);
        }

        // Actualizar información del cliente
        function updateClientInfo() {
            const select = document.getElementById('client_id');
            const infoDiv = document.getElementById('client-info');
            
            if (select.value) {
                const option = select.options[select.selectedIndex];
                const email = option.getAttribute('data-email');
                const phone = option.getAttribute('data-phone');
                
                let info = '';
                if (email) info += '📧 ' + email;
                if (phone) info += (info ? ' • ' : '') + '📞 ' + phone;
                
                infoDiv.innerHTML = info;
            } else {
                infoDiv.innerHTML = '';
            }
        }

        // Validar formulario antes de enviar
        function validateForm() {
            const errorElement = document.getElementById('js-error');
            errorElement.classList.add('hidden');
            
            // Validar cliente
            const clientId = document.getElementById('client_id').value;
            if (!clientId) {
                showError('Debe seleccionar un cliente.');
                return false;
            }
            
            // Validar fecha
            const validUntil = document.getElementById('valid_until').value;
            if (!validUntil) {
                showError('La fecha de validez es requerida.');
                return false;
            }
            
            const validDate = new Date(validUntil);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (validDate <= today) {
                showError('La fecha de validez debe ser posterior a hoy.');
                return false;
            }
            
            // Validar que hay al menos un item
            const items = document.querySelectorAll('.item-row');
            if (items.length === 0) {
                showError('Debe agregar al menos un producto a la cotización.');
                return false;
            }
            
            // Validar cada item
            let hasValidItems = false;
            for (let i = 0; i < items.length; i++) {
                const row = items[i];
                const itemId = row.id.split('-')[1];
                const productId = document.querySelector(`select[name="items[${itemId}][product_id]"]`).value;
                const quantity = parseFloat(document.querySelector(`input[name="items[${itemId}][quantity]"]`).value);
                const unitPrice = parseFloat(document.querySelector(`input[name="items[${itemId}][unit_price]"]`).value);
                
                if (productId && quantity > 0 && unitPrice > 0) {
                    hasValidItems = true;
                } else if (productId || quantity || unitPrice) {
                    showError(`Item ${i + 1}: Debe completar producto, cantidad y precio unitario.`);
                    return false;
                }
            }
            
            if (!hasValidItems) {
                showError('Debe completar al menos un producto válido.');
                return false;
            }
            
            // Validar descuento global
            const discount = parseFloat(document.getElementById('discount').value);
            if (isNaN(discount) || discount < 0 || discount > 100) {
                showError('El descuento global debe estar entre 0% y 100%.');
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
            const items = document.querySelectorAll('.item-row');
            const clientId = document.getElementById('client_id').value;
            const notes = document.getElementById('notes').value.trim();
            
            if (items.length > 0 || clientId || notes) {
                return confirm('¿Está seguro de cancelar? Los cambios no guardados se perderán.');
            }
            return true;
        }

        // Atajos de teclado
        document.addEventListener('keydown', function(e) {
            // Ctrl + Enter para guardar
            if (e.ctrlKey && e.key === 'Enter') {
                document.getElementById('quote-form').submit();
            }
            
            // Ctrl + N para agregar producto
            if (e.ctrlKey && e.key.toLowerCase() === 'n') {
                e.preventDefault();
                addItem();
            }
        });
    </script>
</body>
</html>