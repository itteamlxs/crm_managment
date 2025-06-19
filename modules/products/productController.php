<?php
// Controlador para gestionar productos y categorías
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/session.php';
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/products/productModel.php';
    require_once dirname(__DIR__) . '/products/categoryModel.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

class ProductController {
    private $session;
    private $productModel;
    private $categoryModel;

    public function __construct() {
        try {
            $this->session = new Session();
            $this->productModel = new ProductModel();
            $this->categoryModel = new CategoryModel();
            Security::setHeaders();
        } catch (Exception $e) {
            error_log("Error initializing ProductController: " . $e->getMessage());
            die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    // Verificar autenticación y permisos
    private function checkAuth() {
        if (!$this->session->isLoggedIn()) {
            header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=session_expired');
            exit;
        }
    }

    // Obtener token CSRF para formularios
    public function getCsrfToken() {
        return $this->session->getCsrfToken();
    }

    // Verificar si está autenticado
    public function isAuthenticated() {
        return $this->session->isLoggedIn();
    }

    // Obtener rol del usuario actual
    public function getUserRole() {
        return $this->session->hasRole(ROLE_ADMIN) ? 'admin' : 'seller';
    }

    // === MÉTODOS PARA PRODUCTOS ===

    // Listar productos con filtros
    public function listProducts($search = '', $categoryId = null, $status = null, $lowStock = false, $page = 1, $perPage = 20) {
        $this->checkAuth();
        
        try {
            $offset = ($page - 1) * $perPage;
            $products = $this->productModel->getAll($search, $categoryId, $status, $lowStock, $perPage, $offset);
            $total = $this->productModel->count($search, $categoryId, $status, $lowStock);
            
            return [
                'products' => $products,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage),
                'search' => $search,
                'categoryId' => $categoryId,
                'status' => $status,
                'lowStock' => $lowStock
            ];
        } catch (Exception $e) {
            error_log("Error listing products: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Obtener producto por ID
    public function getProductById($id) {
        $this->checkAuth();
        
        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de producto no válido.'];
        }

        try {
            $product = $this->productModel->getById($id);
            return $product ? ['product' => $product] : ['error' => 'Producto no encontrado.'];
        } catch (Exception $e) {
            error_log("Error getting product: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Crear nuevo producto
    public function createProduct() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            // Obtener y validar datos
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $basePrice = (float)($_POST['base_price'] ?? 0);
            $taxRate = (float)($_POST['tax_rate'] ?? 0);
            $unit = trim($_POST['unit'] ?? '');
            $stock = !empty($_POST['stock']) ? (int)$_POST['stock'] : null;

            // Validaciones básicas
            if (empty($name) || empty($unit) || $categoryId <= 0 || $basePrice <= 0) {
                return ['error' => 'Nombre, categoría, precio base y unidad son requeridos.'];
            }

            if (strlen($name) > MAX_NAME_LENGTH) {
                return ['error' => 'El nombre es demasiado largo.'];
            }

            if ($basePrice < 0) {
                return ['error' => 'El precio base debe ser mayor a 0.'];
            }

            if ($taxRate < 0 || $taxRate > 100) {
                return ['error' => 'La tasa de impuesto debe estar entre 0% y 100%.'];
            }

            if (strlen($unit) > 20) {
                return ['error' => 'La unidad es demasiado larga.'];
            }

            if ($stock !== null && $stock < 0) {
                return ['error' => 'El stock debe ser mayor o igual a 0.'];
            }

            try {
                $productId = $this->productModel->create($name, $description, $categoryId, $basePrice, $taxRate, $unit, $stock);
                header('Location: ' . BASE_URL . '/modules/products/productList.php?success=created');
                exit;
            } catch (Exception $e) {
                error_log("Error creating product: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Actualizar producto
    public function updateProduct() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            // Obtener y validar datos
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $basePrice = (float)($_POST['base_price'] ?? 0);
            $taxRate = (float)($_POST['tax_rate'] ?? 0);
            $unit = trim($_POST['unit'] ?? '');
            $stock = !empty($_POST['stock']) ? (int)$_POST['stock'] : null;
            $status = (int)($_POST['status'] ?? STATUS_ACTIVE);

            // Validaciones básicas
            if ($id <= 0) {
                return ['error' => 'ID de producto no válido.'];
            }

            if (empty($name) || empty($unit) || $categoryId <= 0 || $basePrice <= 0) {
                return ['error' => 'Nombre, categoría, precio base y unidad son requeridos.'];
            }

            if (strlen($name) > MAX_NAME_LENGTH) {
                return ['error' => 'El nombre es demasiado largo.'];
            }

            if ($basePrice < 0) {
                return ['error' => 'El precio base debe ser mayor a 0.'];
            }

            if ($taxRate < 0 || $taxRate > 100) {
                return ['error' => 'La tasa de impuesto debe estar entre 0% y 100%.'];
            }

            if (strlen($unit) > 20) {
                return ['error' => 'La unidad es demasiado larga.'];
            }

            if ($stock !== null && $stock < 0) {
                return ['error' => 'El stock debe ser mayor o igual a 0.'];
            }

            if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
                return ['error' => 'Estado no válido.'];
            }

            try {
                $this->productModel->update($id, $name, $description, $categoryId, $basePrice, $taxRate, $unit, $stock, $status);
                header('Location: ' . BASE_URL . '/modules/products/productList.php?success=updated');
                exit;
            } catch (Exception $e) {
                error_log("Error updating product: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Cambiar estado del producto
    public function changeProductStatus($id, $status) {
        $this->checkAuth();
        
        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de producto no válido.'];
        }

        if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
            return ['error' => 'Estado no válido.'];
        }

        try {
            $this->productModel->changeStatus($id, $status);
            $action = $status == STATUS_ACTIVE ? 'activated' : 'deactivated';
            header('Location: ' . BASE_URL . '/modules/products/productList.php?success=' . $action);
            exit;
        } catch (Exception $e) {
            error_log("Error changing product status: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Eliminar producto
    public function deleteProduct($id) {
        $this->checkAuth();
        
        // Solo administradores pueden eliminar
        if (!$this->session->hasRole(ROLE_ADMIN)) {
            return ['error' => 'No tiene permisos para eliminar productos.'];
        }

        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de producto no válido.'];
        }

        try {
            // Verificar si el producto tiene relaciones
            if ($this->productModel->hasRelations($id)) {
                return ['error' => 'No se puede eliminar el producto porque tiene cotizaciones o ventas asociadas.'];
            }

            $this->productModel->delete($id);
            header('Location: ' . BASE_URL . '/modules/products/productList.php?success=deleted');
            exit;
        } catch (Exception $e) {
            error_log("Error deleting product: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Exportar productos a CSV
    public function exportProductsCsv($search = '', $categoryId = null, $status = null) {
        $this->checkAuth();
        
        try {
            $products = $this->productModel->getForExport($search, $categoryId, $status);
            
            // Headers para CSV
            $headers = [
                'ID',
                'Nombre',
                'Descripción',
                'Categoría',
                'Precio Base',
                'Tasa Impuesto (%)',
                'Precio Final',
                'Unidad',
                'Stock',
                'Estado',
                'Fecha de Creación',
                'Última Actualización'
            ];
            
            // Preparar datos
            $data = [$headers];
            foreach ($products as $product) {
                $finalPrice = $this->productModel->calculateFinalPrice($product['base_price'], $product['tax_rate']);
                
                $data[] = [
                    $product['id'],
                    $product['name'],
                    $product['description'] ?? '',
                    $product['category_name'] ?? '',
                    number_format($product['base_price'], 2),
                    number_format($product['tax_rate'], 2),
                    number_format($finalPrice, 2),
                    $product['unit'],
                    $product['stock'] ?? 'No aplica',
                    $product['status'] == STATUS_ACTIVE ? 'Activo' : 'Inactivo',
                    Utils::formatDateDisplay($product['created_at']),
                    $product['updated_at'] ? Utils::formatDateDisplay($product['updated_at']) : ''
                ];
            }
            
            // Generar nombre de archivo
            $filename = 'productos_' . date('Y-m-d_H-i-s') . '.csv';
            
            // Enviar CSV
            Utils::generateCsv($data, $filename);
            
        } catch (Exception $e) {
            error_log("Error exporting products: " . $e->getMessage());
            return ['error' => 'Error al exportar productos: ' . $e->getMessage()];
        }
    }

    // === MÉTODOS PARA CATEGORÍAS ===

    // Listar categorías
    public function listCategories($search = '', $status = null, $page = 1, $perPage = 20) {
        $this->checkAuth();
        
        try {
            $offset = ($page - 1) * $perPage;
            $categories = $this->categoryModel->getAll($search, $status, $perPage, $offset);
            $total = $this->categoryModel->count($search, $status);
            
            return [
                'categories' => $categories,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage),
                'search' => $search,
                'status' => $status
            ];
        } catch (Exception $e) {
            error_log("Error listing categories: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Obtener categorías activas (para selectores)
    public function getActiveCategories() {
        $this->checkAuth();
        
        try {
            return $this->categoryModel->getActive();
        } catch (Exception $e) {
            error_log("Error getting active categories: " . $e->getMessage());
            return [];
        }
    }

    // Obtener categoría por ID
    public function getCategoryById($id) {
        $this->checkAuth();
        
        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de categoría no válido.'];
        }

        try {
            $category = $this->categoryModel->getById($id);
            return $category ? ['category' => $category] : ['error' => 'Categoría no encontrada.'];
        } catch (Exception $e) {
            error_log("Error getting category: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Crear nueva categoría
    public function createCategory() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            // Obtener y validar datos
            $name = trim($_POST['name'] ?? '');

            // Validaciones básicas
            if (empty($name)) {
                return ['error' => 'El nombre de la categoría es requerido.'];
            }

            if (strlen($name) > 50) {
                return ['error' => 'El nombre es demasiado largo (máximo 50 caracteres).'];
            }

            try {
                $categoryId = $this->categoryModel->create($name);
                header('Location: ' . BASE_URL . '/modules/products/categoryList.php?success=created');
                exit;
            } catch (Exception $e) {
                error_log("Error creating category: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Actualizar categoría
    public function updateCategory() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            // Obtener y validar datos
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $status = (int)($_POST['status'] ?? STATUS_ACTIVE);

            // Validaciones básicas
            if ($id <= 0) {
                return ['error' => 'ID de categoría no válido.'];
            }

            if (empty($name)) {
                return ['error' => 'El nombre de la categoría es requerido.'];
            }

            if (strlen($name) > 50) {
                return ['error' => 'El nombre es demasiado largo (máximo 50 caracteres).'];
            }

            if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
                return ['error' => 'Estado no válido.'];
            }

            try {
                $this->categoryModel->update($id, $name, $status);
                header('Location: ' . BASE_URL . '/modules/products/categoryList.php?success=updated');
                exit;
            } catch (Exception $e) {
                error_log("Error updating category: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Eliminar categoría
    public function deleteCategory($id) {
        $this->checkAuth();
        
        // Solo administradores pueden eliminar
        if (!$this->session->hasRole(ROLE_ADMIN)) {
            return ['error' => 'No tiene permisos para eliminar categorías.'];
        }

        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de categoría no válido.'];
        }

        try {
            $this->categoryModel->delete($id);
            header('Location: ' . BASE_URL . '/modules/products/categoryList.php?success=deleted');
            exit;
        } catch (Exception $e) {
            error_log("Error deleting category: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Obtener estadísticas generales
    public function getGeneralStats() {
        $this->checkAuth();
        
        try {
            $productStats = $this->productModel->getGeneralStats();
            $categoryStats = $this->categoryModel->count();
            $lowStockProducts = $this->productModel->getLowStock();
            
            return [
                'products' => $productStats,
                'total_categories' => $categoryStats,
                'low_stock_products' => $lowStockProducts
            ];
        } catch (Exception $e) {
            error_log("Error getting general stats: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
?>