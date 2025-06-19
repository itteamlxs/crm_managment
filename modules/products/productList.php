<?php
// Vista del listado de productos
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

// Procesar acciones
$error = '';
$success = '';

// Obtener parámetros de URL
$search = $_GET['search'] ?? '';
$categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== '' ? (int)$_GET['category_id'] : null;
$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$lowStock = isset($_GET['low_stock']) && $_GET['low_stock'] === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Procesar exportar CSV
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    $controller->exportProductsCsv($search, $categoryId, $status);
    exit;
}

// Procesar cambio de estado
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    $newStatus = (int)($_GET['new_status'] ?? STATUS_INACTIVE);
    $result = $controller->changeProductStatus($productId, $newStatus);
    if (isset($result['error'])) {
        $error = $result['error'];
    }
}

// Procesar eliminación
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    $result = $controller->deleteProduct($productId);
    if (isset($result['error'])) {
        $error = $result['error'];
    }
}

// Obtener listado de productos
$listResult = $controller->listProducts($search, $categoryId, $status, $lowStock, $page, $perPage);
if (isset($listResult['error'])) {
    $error = $listResult['error'];
    $products = [];
    $pagination = ['total' => 0, 'page' => 1, 'totalPages' => 1];
} else {
    $products = $listResult['products'];
    $pagination = [
        'total' => $listResult['total'],
        'page' => $listResult['page'],
        'totalPages' => $listResult['totalPages'],
        'perPage' => $listResult['perPage']
    ];
}

// Obtener categorías para el filtro
$categories = $controller->getActiveCategories();

// Manejar mensajes de URL
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success = 'Producto creado correctamente.';
            break;
        case 'updated':
            $success = 'Producto actualizado correctamente.';
            break;
        case 'deleted':
            $success = 'Producto eliminado correctamente.';
            break;
        case 'activated':
            $success = 'Producto activado correctamente.';
            break;
        case 'deactivated':
            $success = 'Producto desactivado correctamente.';
            break;
    }
}

$userRole = $controller->getUserRole();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-3xl font-bold text-gray-800">Gestión de Productos</h1>
                <div class="flex space-x-2">
                    <a href="productForm.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        + Nuevo Producto
                    </a>
                    <a href="categoryList.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        Categorías
                    </a>
                    <a href="<?php echo BASE_URL; ?>/modules/clients/clientList.php" class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                        Clientes
                    </a>
                </div>
            </div>

            <!-- Filtros y búsqueda -->
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-64">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        value="<?php echo Security::escape($search); ?>"
                        placeholder="Nombre, descripción o unidad..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                </div>
                <div>
                    <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">Categoría</label>
                    <select id="category_id" name="category_id" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo $categoryId === $category['id'] ? 'selected' : ''; ?>>
                                <?php echo Security::escape($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select id="status" name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Todos</option>
                        <option value="<?php echo STATUS_ACTIVE; ?>" <?php echo $status === STATUS_ACTIVE ? 'selected' : ''; ?>>Activos</option>
                        <option value="<?php echo STATUS_INACTIVE; ?>" <?php echo $status === STATUS_INACTIVE ? 'selected' : ''; ?>>Inactivos</option>
                    </select>
                </div>
                <div class="flex items-center">
                    <input 
                        type="checkbox" 
                        id="low_stock" 
                        name="low_stock" 
                        value="1"
                        <?php echo $lowStock ? 'checked' : ''; ?>
                        class="mr-2"
                    >
                    <label for="low_stock" class="text-sm text-gray-700">Solo stock bajo</label>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        Buscar
                    </button>
                    <a href="productList.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Limpiar
                    </a>
                    <a href="?action=export<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryId ? '&category_id=' . $categoryId : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?><?php echo $lowStock ? '&low_stock=1' : ''; ?>" 
                       class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        Exportar CSV
                    </a>
                </div>
            </form>
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

        <!-- Estadísticas -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?php echo number_format($pagination['total']); ?></div>
                    <div class="text-gray-600">Total de Productos</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">
                        <?php 
                        $activeProducts = array_filter($products, fn($p) => $p['status'] == STATUS_ACTIVE);
                        echo count($activeProducts);
                        ?>
                    </div>
                    <div class="text-gray-600">Productos Activos</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-yellow-600">
                        <?php 
                        $lowStockCount = array_filter($products, fn($p) => $p['stock'] !== null && $p['stock'] <= 10);
                        echo count($lowStockCount);
                        ?>
                    </div>
                    <div class="text-gray-600">Stock Bajo</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-600">
                        Página <?php echo $pagination['page']; ?> de <?php echo $pagination['totalPages']; ?>
                    </div>
                    <div class="text-gray-600">Paginación</div>
                </div>
            </div>
        </div>

        <!-- Tabla de productos -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <?php if (empty($products)): ?>
                <div class="p-8 text-center text-gray-500">
                    <p class="text-xl">No se encontraron productos</p>
                    <p class="mt-2">Intente cambiar los filtros o crear un nuevo producto</p>
                    <a href="productForm.php" class="inline-block mt-4 bg-blue-600 text-white px-6 py-3 rounded-md hover:bg-blue-700">
                        Crear Primer Producto
                    </a>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Producto</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoría</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Precio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Stock</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($products as $product): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div>
                                        <div class="text-sm font-medium text-gray-900">
                                            <?php echo Security::escape($product['name']); ?>
                                        </div>
                                        <div class="text-sm text-gray-500">
                                            <?php echo Security::escape($product['unit']); ?>
                                            <?php if ($product['description']): ?>
                                                • <?php echo Security::escape(substr($product['description'], 0, 50)); ?>
                                                <?php echo strlen($product['description']) > 50 ? '...' : ''; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?php echo Security::escape($product['category_name'] ?? 'Sin categoría'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        $<?php echo number_format($product['base_price'], 2); ?>
                                        <?php if ($product['tax_rate'] > 0): ?>
                                            <div class="text-xs text-gray-500">
                                                +<?php echo number_format($product['tax_rate'], 1); ?>% impuesto
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($product['stock'] !== null): ?>
                                        <?php if ($product['stock'] <= 10): ?>
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                                <?php echo $product['stock']; ?> (Bajo)
                                            </span>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-900"><?php echo $product['stock']; ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-sm text-gray-500">No aplica</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($product['status'] == STATUS_ACTIVE): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button type="button" 
                                               class="text-green-600 hover:text-green-900 view-product-btn" 
                                               data-product='<?php echo htmlspecialchars(json_encode($product), ENT_QUOTES, 'UTF-8'); ?>'>
                                            Ver
                                        </button>
                                        <a href="productForm.php?id=<?php echo $product['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900">
                                            Editar
                                        </a>
                                        
                                        <?php if ($product['status'] == STATUS_ACTIVE): ?>
                                            <a href="?action=toggle_status&id=<?php echo $product['id']; ?>&new_status=<?php echo STATUS_INACTIVE; ?>" 
                                               class="text-yellow-600 hover:text-yellow-900 confirm-action"
                                               data-message="¿Desactivar este producto?">
                                                Desactivar
                                            </a>
                                        <?php else: ?>
                                            <a href="?action=toggle_status&id=<?php echo $product['id']; ?>&new_status=<?php echo STATUS_ACTIVE; ?>" 
                                               class="text-green-600 hover:text-green-900">
                                                Activar
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($userRole === 'admin'): ?>
                                            <a href="?action=delete&id=<?php echo $product['id']; ?>" 
                                               class="text-red-600 hover:text-red-900 confirm-action"
                                               data-message="¿Está seguro de eliminar este producto? Esta acción no se puede deshacer.">
                                                Eliminar
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Paginación -->
        <?php if ($pagination['totalPages'] > 1): ?>
            <div class="bg-white rounded-lg shadow-md p-4 mt-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-700">
                        Mostrando <?php echo (($pagination['page'] - 1) * $pagination['perPage']) + 1; ?> 
                        a <?php echo min($pagination['page'] * $pagination['perPage'], $pagination['total']); ?> 
                        de <?php echo $pagination['total']; ?> productos
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($pagination['page'] > 1): ?>
                            <a href="?page=<?php echo $pagination['page'] - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryId ? '&category_id=' . $categoryId : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?><?php echo $lowStock ? '&low_stock=1' : ''; ?>" 
                               class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Anterior
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['totalPages'], $pagination['page'] + 2); $i++): ?>
                            <?php if ($i == $pagination['page']): ?>
                                <span class="px-3 py-2 bg-blue-800 text-white rounded-md"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryId ? '&category_id=' . $categoryId : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?><?php echo $lowStock ? '&low_stock=1' : ''; ?>" 
                                   class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pagination['page'] < $pagination['totalPages']): ?>
                            <a href="?page=<?php echo $pagination['page'] + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $categoryId ? '&category_id=' . $categoryId : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?><?php echo $lowStock ? '&low_stock=1' : ''; ?>" 
                               class="px-3 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                                Siguiente
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal integrado para ver producto -->
    <div id="productModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-2xl w-full max-h-screen overflow-auto">
                
                <!-- Header del modal -->
                <div class="bg-blue-600 text-white p-4 rounded-t-lg flex justify-between items-center">
                    <h2 class="text-xl font-bold">Información del Producto</h2>
                    <button type="button" id="closeModalBtn" class="text-white hover:text-gray-200 text-2xl">
                        ×
                    </button>
                </div>

                <!-- Contenido del modal -->
                <div class="p-6">
                    
                    <!-- Información principal -->
                    <div class="mb-6">
                        <div class="flex justify-between items-start mb-4">
                            <div>
                                <h1 id="modalProductName" class="text-2xl font-bold text-gray-800"></h1>
                                <div class="mt-2 flex space-x-2">
                                    <span id="modalProductStatus" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"></span>
                                    <span id="modalProductCategory" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800"></span>
                                </div>
                            </div>
                            <div class="text-right text-sm text-gray-500">
                                <div>ID: <span id="modalProductId"></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Detalles del producto -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        
                        <!-- Información del producto -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                                Información del Producto
                            </h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Descripción</label>
                                <div id="modalProductDescription" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Unidad</label>
                                <div id="modalProductUnit" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Stock</label>
                                <div id="modalProductStock" class="mt-1 text-gray-900"></div>
                            </div>
                        </div>

                        <!-- Información de precios -->
                        <div class="space-y-4">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">
                                Información de Precios
                            </h3>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-600">Precio Base</label>
                                <div id="modalProductBasePrice" class="mt-1 text-gray-900 text-lg font-semibold"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Tasa de Impuesto</label>
                                <div id="modalProductTaxRate" class="mt-1 text-gray-900"></div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-600">Precio Final (con impuestos)</label>
                                <div id="modalProductFinalPrice" class="mt-1 text-green-600 text-lg font-bold"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Fechas del sistema -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Información del Sistema</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span class="font-medium text-gray-600">Fecha de Creación:</span>
                                <div id="modalProductCreated" class="text-gray-900"></div>
                            </div>
                            <div>
                                <span class="font-medium text-gray-600">Última Actualización:</span>
                                <div id="modalProductUpdated" class="text-gray-900"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-between items-center pt-4 border-t">
                        <div class="flex space-x-2">
                            <button type="button" id="modalEditBtn" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                Editar Producto
                            </button>
                            <button type="button" id="modalPrintBtn" 
                                    class="bg-gray-600 text-white px-4 py-2 rounded-md hover:bg-gray-700">
                                Imprimir
                            </button>
                        </div>
                        <button type="button" id="modalCloseBtn" 
                                class="bg-gray-300 text-gray-700 px-4 py-2 rounded-md hover:bg-gray-400">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentProduct = null;

        // Función para abrir el modal del producto
        function viewProduct(product) {
            currentProduct = product;
            console.log('Abriendo modal para producto:', product);
            
            // Llenar datos del modal
            document.getElementById('modalProductId').textContent = product.id;
            document.getElementById('modalProductName').textContent = product.name;
            
            // Descripción
            const descElement = document.getElementById('modalProductDescription');
            if (product.description) {
                descElement.textContent = product.description;
            } else {
                descElement.innerHTML = '<span class="text-gray-500 italic">Sin descripción</span>';
            }
            
            // Unidad
            document.getElementById('modalProductUnit').textContent = product.unit;
            
            // Stock
            const stockElement = document.getElementById('modalProductStock');
            if (product.stock !== null) {
                const stockClass = product.stock <= 10 ? 'text-red-600 font-semibold' : 'text-gray-900';
                const stockText = product.stock <= 10 ? `${product.stock} (Stock Bajo)` : product.stock;
                stockElement.innerHTML = `<span class="${stockClass}">${stockText}</span>`;
            } else {
                stockElement.innerHTML = '<span class="text-gray-500 italic">No se maneja stock</span>';
            }
            
            // Precios
            document.getElementById('modalProductBasePrice').textContent = '$' + parseFloat(product.base_price).toFixed(2);
            document.getElementById('modalProductTaxRate').textContent = parseFloat(product.tax_rate).toFixed(1) + '%';
            
            // Calcular precio final
            const basePrice = parseFloat(product.base_price);
            const taxRate = parseFloat(product.tax_rate);
            const finalPrice = basePrice + (basePrice * taxRate / 100);
            document.getElementById('modalProductFinalPrice').textContent = '$' + finalPrice.toFixed(2);
            
            // Categoría
            const categoryElement = document.getElementById('modalProductCategory');
            categoryElement.textContent = product.category_name || 'Sin categoría';
            
            // Estado
            const statusElement = document.getElementById('modalProductStatus');
            if (product.status == '<?php echo STATUS_ACTIVE; ?>') {
                statusElement.className = 'inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800';
                statusElement.textContent = '✓ Producto Activo';
            } else {
                statusElement.className = 'inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800';
                statusElement.textContent = '✗ Producto Inactivo';
            }
            
            // Fechas
            document.getElementById('modalProductCreated').textContent = formatDate(product.created_at);
            document.getElementById('modalProductUpdated').textContent = product.updated_at ? formatDate(product.updated_at) : 'No actualizado';
            
            // Mostrar modal
            document.getElementById('productModal').classList.remove('hidden');
        }

        // Función para cerrar el modal
        function closeProductModal() {
            document.getElementById('productModal').classList.add('hidden');
            currentProduct = null;
        }

        // Función para editar desde el modal
        function editProductFromModal() {
            if (currentProduct) {
                window.location.href = 'productForm.php?id=' + currentProduct.id;
            }
        }

        // Función para imprimir
        function printProductModal() {
            window.print();
        }

        // Función para formatear fechas
        function formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Event listeners cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            
            // Event listeners para botones "Ver"
            document.querySelectorAll('.view-product-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    const productData = JSON.parse(this.getAttribute('data-product'));
                    viewProduct(productData);
                });
            });

            // Event listeners para acciones que requieren confirmación
            document.querySelectorAll('.confirm-action').forEach(function(link) {
                link.addEventListener('click', function(e) {
                    const message = this.getAttribute('data-message');
                    if (!confirm(message)) {
                        e.preventDefault();
                    }
                });
            });

            // Event listeners para botones del modal
            document.getElementById('closeModalBtn').addEventListener('click', closeProductModal);
            document.getElementById('modalEditBtn').addEventListener('click', editProductFromModal);
            document.getElementById('modalPrintBtn').addEventListener('click', printProductModal);
            document.getElementById('modalCloseBtn').addEventListener('click', closeProductModal);
            
            // Cerrar modal al hacer clic fuera
            document.getElementById('productModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeProductModal();
                }
            });

            // Cerrar modal con tecla ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeProductModal();
                }
            });
        });
    </script>

    <style>
        @media print {
            body * { visibility: hidden; }
            #productModal, #productModal * { visibility: visible; }
            #productModal { 
                position: static !important; 
                background: white !important; 
            }
            .no-print { display: none !important; }
        }
    </style>
</body>
</html>