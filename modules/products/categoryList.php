<?php
// Vista del listado de categorías
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
$status = isset($_GET['status']) && $_GET['status'] !== '' ? (int)$_GET['status'] : null;
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// Procesar eliminación
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $categoryId = (int)$_GET['id'];
    $result = $controller->deleteCategory($categoryId);
    if (isset($result['error'])) {
        $error = $result['error'];
    }
}

// Obtener listado de categorías
$listResult = $controller->listCategories($search, $status, $page, $perPage);
if (isset($listResult['error'])) {
    $error = $listResult['error'];
    $categories = [];
    $pagination = ['total' => 0, 'page' => 1, 'totalPages' => 1];
} else {
    $categories = $listResult['categories'];
    $pagination = [
        'total' => $listResult['total'],
        'page' => $listResult['page'],
        'totalPages' => $listResult['totalPages'],
        'perPage' => $listResult['perPage']
    ];
}

// Manejar mensajes de URL
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'created':
            $success = 'Categoría creada correctamente.';
            break;
        case 'updated':
            $success = 'Categoría actualizada correctamente.';
            break;
        case 'deleted':
            $success = 'Categoría eliminada correctamente.';
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
    <title>Gestión de Categorías - CRM</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-3xl font-bold text-gray-800">Gestión de Categorías</h1>
                <div class="flex space-x-2">
                    <a href="categoryForm.php" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        + Nueva Categoría
                    </a>
                    <a href="productList.php" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                        ← Productos
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
                        placeholder="Nombre de categoría..."
                        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Estado</label>
                    <select id="status" name="status" class="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="">Todos</option>
                        <option value="<?php echo STATUS_ACTIVE; ?>" <?php echo $status === STATUS_ACTIVE ? 'selected' : ''; ?>>Activas</option>
                        <option value="<?php echo STATUS_INACTIVE; ?>" <?php echo $status === STATUS_INACTIVE ? 'selected' : ''; ?>>Inactivas</option>
                    </select>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                        Buscar
                    </button>
                    <a href="categoryList.php" class="bg-gray-500 text-white px-4 py-2 rounded-md hover:bg-gray-600">
                        Limpiar
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
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600"><?php echo number_format($pagination['total']); ?></div>
                    <div class="text-gray-600">Total de Categorías</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600">
                        <?php 
                        $activeCategories = array_filter($categories, fn($c) => $c['status'] == STATUS_ACTIVE);
                        echo count($activeCategories);
                        ?>
                    </div>
                    <div class="text-gray-600">Categorías Activas</div>
                </div>
                <div class="text-center">
                    <div class="text-2xl font-bold text-gray-600">
                        Página <?php echo $pagination['page']; ?> de <?php echo $pagination['totalPages']; ?>
                    </div>
                    <div class="text-gray-600">Paginación</div>
                </div>
            </div>
        </div>

        <!-- Tabla de categorías -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <?php if (empty($categories)): ?>
                <div class="p-8 text-center text-gray-500">
                    <p class="text-xl">No se encontraron categorías</p>
                    <p class="mt-2">Las categorías le ayudan a organizar sus productos</p>
                    <a href="categoryForm.php" class="inline-block mt-4 bg-green-600 text-white px-6 py-3 rounded-md hover:bg-green-700">
                        Crear Primera Categoría
                    </a>
                </div>
            <?php else: ?>
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoría</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Productos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($categories as $category): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?php echo Security::escape($category['name']); ?>
                                    </div>
                                    <div class="text-sm text-gray-500">
                                        ID: <?php echo $category['id']; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <!-- Aquí se mostraría el conteo de productos por categoría -->
                                        <span class="text-gray-500">
                                            <a href="productList.php?category_id=<?php echo $category['id']; ?>" 
                                               class="text-blue-600 hover:text-blue-800">
                                                Ver productos →
                                            </a>
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($category['status'] == STATUS_ACTIVE): ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                            Activa
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">
                                            Inactiva
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <button type="button" 
                                               class="text-green-600 hover:text-green-900 view-category-btn" 
                                               data-category='<?php echo htmlspecialchars(json_encode($category), ENT_QUOTES, 'UTF-8'); ?>'>
                                            Ver
                                        </button>
                                        <a href="categoryForm.php?id=<?php echo $category['id']; ?>" 
                                           class="text-blue-600 hover:text-blue-900">
                                            Editar
                                        </a>
                                        
                                        <?php if ($userRole === 'admin'): ?>
                                            <a href="?action=delete&id=<?php echo $category['id']; ?>" 
                                               class="text-red-600 hover:text-red-900 confirm-action"
                                               data-message="¿Está seguro de eliminar esta categoría? Esta acción no se puede deshacer.">
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
                        de <?php echo $pagination['total']; ?> categorías
                    </div>
                    <div class="flex space-x-1">
                        <?php if ($pagination['page'] > 1): ?>
                            <a href="?page=<?php echo $pagination['page'] - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?>" 
                               class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                Anterior
                            </a>
                        <?php endif; ?>

                        <?php for ($i = max(1, $pagination['page'] - 2); $i <= min($pagination['totalPages'], $pagination['page'] + 2); $i++): ?>
                            <?php if ($i == $pagination['page']): ?>
                                <span class="px-3 py-2 bg-green-800 text-white rounded-md"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?>" 
                                   class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                    <?php echo $i; ?>
                                </a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($pagination['page'] < $pagination['totalPages']): ?>
                            <a href="?page=<?php echo $pagination['page'] + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $status !== null ? '&status=' . $status : ''; ?>" 
                               class="px-3 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                Siguiente
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal integrado para ver categoría -->
    <div id="categoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg max-w-lg w-full">
                
                <!-- Header del modal -->
                <div class="bg-green-600 text-white p-4 rounded-t-lg flex justify-between items-center">
                    <h2 class="text-xl font-bold">Información de la Categoría</h2>
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
                                <h1 id="modalCategoryName" class="text-2xl font-bold text-gray-800"></h1>
                                <div class="mt-2">
                                    <span id="modalCategoryStatus" class="inline-flex px-3 py-1 text-sm font-semibold rounded-full"></span>
                                </div>
                            </div>
                            <div class="text-right text-sm text-gray-500">
                                <div>ID: <span id="modalCategoryId"></span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Estadísticas de productos -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Productos en esta Categoría</h3>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600">0</div>
                            <div class="text-sm text-gray-600">Total de Productos</div>
                            <div class="mt-2">
                                <a href="#" id="modalViewProductsLink" class="text-blue-600 hover:text-blue-800 text-sm">
                                    Ver productos de esta categoría →
                                </a>
                            </div>
                        </div>
                        <div class="text-center mt-2 text-sm text-gray-500">
                            <em>Las estadísticas se actualizarán cuando haya productos asignados</em>
                        </div>
                    </div>

                    <!-- Botones de acción -->
                    <div class="flex justify-between items-center pt-4 border-t">
                        <div class="flex space-x-2">
                            <button type="button" id="modalEditBtn" 
                                    class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                Editar Categoría
                            </button>
                            <button type="button" id="modalCreateProductBtn" 
                                    class="bg-green-600 text-white px-4 py-2 rounded-md hover:bg-green-700">
                                Crear Producto
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
        let currentCategory = null;

        // Función para abrir el modal de la categoría
        function viewCategory(category) {
            currentCategory = category;
            console.log('Abriendo modal para categoría:', category);
            
            // Llenar datos del modal
            document.getElementById('modalCategoryId').textContent = category.id;
            document.getElementById('modalCategoryName').textContent = category.name;
            
            // Estado
            const statusElement = document.getElementById('modalCategoryStatus');
            if (category.status == '<?php echo STATUS_ACTIVE; ?>') {
                statusElement.className = 'inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800';
                statusElement.textContent = '✓ Categoría Activa';
            } else {
                statusElement.className = 'inline-flex px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800';
                statusElement.textContent = '✗ Categoría Inactiva';
            }
            
            // Actualizar enlaces
            document.getElementById('modalViewProductsLink').href = 'productList.php?category_id=' + category.id;
            
            // Mostrar modal
            document.getElementById('categoryModal').classList.remove('hidden');
        }

        // Función para cerrar el modal
        function closeCategoryModal() {
            document.getElementById('categoryModal').classList.add('hidden');
            currentCategory = null;
        }

        // Función para editar desde el modal
        function editCategoryFromModal() {
            if (currentCategory) {
                window.location.href = 'categoryForm.php?id=' + currentCategory.id;
            }
        }

        // Función para crear producto en esta categoría
        function createProductInCategory() {
            if (currentCategory) {
                window.location.href = 'productForm.php?category_id=' + currentCategory.id;
            }
        }

        // Event listeners cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            
            // Event listeners para botones "Ver"
            document.querySelectorAll('.view-category-btn').forEach(function(button) {
                button.addEventListener('click', function() {
                    const categoryData = JSON.parse(this.getAttribute('data-category'));
                    viewCategory(categoryData);
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
            document.getElementById('closeModalBtn').addEventListener('click', closeCategoryModal);
            document.getElementById('modalEditBtn').addEventListener('click', editCategoryFromModal);
            document.getElementById('modalCreateProductBtn').addEventListener('click', createProductInCategory);
            document.getElementById('modalCloseBtn').addEventListener('click', closeCategoryModal);
            
            // Cerrar modal al hacer clic fuera
            document.getElementById('categoryModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeCategoryModal();
                }
            });

            // Cerrar modal con tecla ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeCategoryModal();
                }
            });
        });
    </script>
</body>
</html>