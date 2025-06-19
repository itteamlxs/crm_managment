<?php
// Modelo para gestionar productos en el sistema CRM
require_once dirname(__DIR__, 2) . '/core/db.php';
require_once dirname(__DIR__, 2) . '/core/security.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

class ProductModel {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Error initializing Database: " . $e->getMessage());
            throw new Exception('No se pudo conectar a la base de datos.');
        }
    }

    // Crear un nuevo producto
    public function create($name, $description, $categoryId, $basePrice, $taxRate, $unit, $stock = null) {
        // Validar entradas
        if (!Security::validate($name, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre de producto no válido o demasiado largo.');
        }
        if (!Security::validate($categoryId, 'int')) {
            throw new Exception('Categoría no válida.');
        }
        if (!Security::validate($basePrice, 'float') || $basePrice < 0) {
            throw new Exception('Precio base no válido.');
        }
        if (!Security::validate($taxRate, 'float') || $taxRate < 0 || $taxRate > 100) {
            throw new Exception('Tasa de impuesto no válida (0-100%).');
        }
        if (!Security::validate($unit, 'string', 20)) {
            throw new Exception('Unidad no válida o demasiado larga.');
        }
        if ($stock !== null && (!Security::validate($stock, 'int') || $stock < 0)) {
            throw new Exception('Stock no válido.');
        }

        // Verificar que la categoría existe y está activa
        if (!$this->categoryExists($categoryId)) {
            throw new Exception('La categoría seleccionada no existe o está inactiva.');
        }

        $query = "INSERT INTO products (name, description, category_id, base_price, tax_rate, unit, stock, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $params = [
            Security::sanitize($name, 'string'),
            $description ? Security::sanitize($description, 'string') : null,
            (int)$categoryId,
            (float)$basePrice,
            (float)$taxRate,
            Security::sanitize($unit, 'string'),
            $stock !== null ? (int)$stock : null,
            STATUS_ACTIVE
        ];

        try {
            return $this->db->insert($query, $params);
        } catch (Exception $e) {
            error_log("Error creating product: " . $e->getMessage());
            throw new Exception('Error al crear el producto: ' . $e->getMessage());
        }
    }

    // Obtener producto por ID
    public function getById($id) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de producto no válido.');
        }

        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.id = ?";
        try {
            $result = $this->db->select($query, [(int)$id]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error fetching product: " . $e->getMessage());
            throw new Exception('Error al obtener el producto: ' . $e->getMessage());
        }
    }

    // Listar todos los productos con filtros
    public function getAll($search = '', $categoryId = null, $status = null, $lowStock = false, $limit = null, $offset = 0) {
        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE 1=1";
        $params = [];

        // Filtro de búsqueda
        if (!empty($search)) {
            $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.unit LIKE ?)";
            $searchTerm = '%' . Security::sanitize($search, 'string') . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filtro por categoría
        if ($categoryId && Security::validate($categoryId, 'int')) {
            $query .= " AND p.category_id = ?";
            $params[] = (int)$categoryId;
        }

        // Filtro de estado
        if ($status !== null && Security::validate($status, 'int')) {
            $query .= " AND p.status = ?";
            $params[] = (int)$status;
        }

        // Filtro de stock bajo
        if ($lowStock) {
            $query .= " AND p.stock IS NOT NULL AND p.stock <= 10";
        }

        // Ordenamiento
        $query .= " ORDER BY p.created_at DESC";

        // Límite y offset para paginación
        if ($limit && Security::validate($limit, 'int')) {
            $query .= " LIMIT ?";
            $params[] = (int)$limit;
            
            if ($offset && Security::validate($offset, 'int')) {
                $query .= " OFFSET ?";
                $params[] = (int)$offset;
            }
        }

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error listing products: " . $e->getMessage());
            throw new Exception('Error al listar productos: ' . $e->getMessage());
        }
    }

    // Listar solo productos activos (para cotizaciones/ventas)
    public function getActive($categoryId = null) {
        $query = "SELECT p.id, p.name, p.base_price, p.tax_rate, p.unit, p.stock, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.status = ? AND c.status = ?";
        $params = [STATUS_ACTIVE, STATUS_ACTIVE];

        if ($categoryId && Security::validate($categoryId, 'int')) {
            $query .= " AND p.category_id = ?";
            $params[] = (int)$categoryId;
        }

        $query .= " ORDER BY p.name ASC";

        try {
            return $this->db->select($query, $params);
        } catch (Exception $e) {
            error_log("Error listing active products: " . $e->getMessage());
            throw new Exception('Error al listar productos activos: ' . $e->getMessage());
        }
    }

    // Contar total de productos
    public function count($search = '', $categoryId = null, $status = null, $lowStock = false) {
        $query = "SELECT COUNT(*) as total FROM products p WHERE 1=1";
        $params = [];

        // Filtro de búsqueda
        if (!empty($search)) {
            $query .= " AND (p.name LIKE ? OR p.description LIKE ? OR p.unit LIKE ?)";
            $searchTerm = '%' . Security::sanitize($search, 'string') . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filtro por categoría
        if ($categoryId && Security::validate($categoryId, 'int')) {
            $query .= " AND p.category_id = ?";
            $params[] = (int)$categoryId;
        }

        // Filtro de estado
        if ($status !== null && Security::validate($status, 'int')) {
            $query .= " AND p.status = ?";
            $params[] = (int)$status;
        }

        // Filtro de stock bajo
        if ($lowStock) {
            $query .= " AND p.stock IS NOT NULL AND p.stock <= 10";
        }

        try {
            $result = $this->db->select($query, $params);
            return $result[0]['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error counting products: " . $e->getMessage());
            throw new Exception('Error al contar productos: ' . $e->getMessage());
        }
    }

    // Actualizar producto
    public function update($id, $name, $description, $categoryId, $basePrice, $taxRate, $unit, $stock = null, $status = STATUS_ACTIVE) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de producto no válido.');
        }
        if (!Security::validate($name, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre de producto no válido o demasiado largo.');
        }
        if (!Security::validate($categoryId, 'int')) {
            throw new Exception('Categoría no válida.');
        }
        if (!Security::validate($basePrice, 'float') || $basePrice < 0) {
            throw new Exception('Precio base no válido.');
        }
        if (!Security::validate($taxRate, 'float') || $taxRate < 0 || $taxRate > 100) {
            throw new Exception('Tasa de impuesto no válida (0-100%).');
        }
        if (!Security::validate($unit, 'string', 20)) {
            throw new Exception('Unidad no válida o demasiado larga.');
        }
        if ($stock !== null && (!Security::validate($stock, 'int') || $stock < 0)) {
            throw new Exception('Stock no válido.');
        }
        if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
            throw new Exception('Estado no válido.');
        }

        // Verificar que la categoría existe y está activa
        if (!$this->categoryExists($categoryId)) {
            throw new Exception('La categoría seleccionada no existe o está inactiva.');
        }

        $query = "UPDATE products SET name = ?, description = ?, category_id = ?, base_price = ?, tax_rate = ?, unit = ?, stock = ?, status = ?, updated_at = NOW() WHERE id = ?";
        $params = [
            Security::sanitize($name, 'string'),
            $description ? Security::sanitize($description, 'string') : null,
            (int)$categoryId,
            (float)$basePrice,
            (float)$taxRate,
            Security::sanitize($unit, 'string'),
            $stock !== null ? (int)$stock : null,
            (int)$status,
            (int)$id
        ];

        try {
            return $this->db->execute($query, $params);
        } catch (Exception $e) {
            error_log("Error updating product: " . $e->getMessage());
            throw new Exception('Error al actualizar el producto: ' . $e->getMessage());
        }
    }

    // Cambiar estado del producto
    public function changeStatus($id, $status) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de producto no válido.');
        }
        if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
            throw new Exception('Estado no válido.');
        }

        $query = "UPDATE products SET status = ?, updated_at = NOW() WHERE id = ?";
        try {
            return $this->db->execute($query, [(int)$status, (int)$id]);
        } catch (Exception $e) {
            error_log("Error changing product status: " . $e->getMessage());
            throw new Exception('Error al cambiar estado del producto: ' . $e->getMessage());
        }
    }

    // Eliminar producto (cambio a estado inactivo)
    public function delete($id) {
        return $this->changeStatus($id, STATUS_INACTIVE);
    }

    // Actualizar stock del producto
    public function updateStock($id, $newStock) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de producto no válido.');
        }
        if (!Security::validate($newStock, 'int') || $newStock < 0) {
            throw new Exception('Stock no válido.');
        }

        $query = "UPDATE products SET stock = ?, updated_at = NOW() WHERE id = ?";
        try {
            return $this->db->execute($query, [(int)$newStock, (int)$id]);
        } catch (Exception $e) {
            error_log("Error updating product stock: " . $e->getMessage());
            throw new Exception('Error al actualizar el stock del producto: ' . $e->getMessage());
        }
    }

    // Decrementar stock (para ventas)
    public function decrementStock($id, $quantity) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de producto no válido.');
        }
        if (!Security::validate($quantity, 'int') || $quantity <= 0) {
            throw new Exception('Cantidad no válida.');
        }

        // Verificar stock actual
        $product = $this->getById($id);
        if (!$product) {
            throw new Exception('Producto no encontrado.');
        }

        if ($product['stock'] === null) {
            // Si no se maneja stock, no hacer nada
            return true;
        }

        if ($product['stock'] < $quantity) {
            throw new Exception('Stock insuficiente. Disponible: ' . $product['stock'] . ', Solicitado: ' . $quantity);
        }

        $newStock = $product['stock'] - $quantity;
        return $this->updateStock($id, $newStock);
    }

    // Obtener productos con stock bajo
    public function getLowStock($threshold = 10) {
        $query = "SELECT p.*, c.name as category_name 
                  FROM products p 
                  LEFT JOIN categories c ON p.category_id = c.id 
                  WHERE p.stock IS NOT NULL AND p.stock <= ? AND p.status = ?
                  ORDER BY p.stock ASC";
        
        try {
            return $this->db->select($query, [(int)$threshold, STATUS_ACTIVE]);
        } catch (Exception $e) {
            error_log("Error getting low stock products: " . $e->getMessage());
            throw new Exception('Error al obtener productos con stock bajo: ' . $e->getMessage());
        }
    }

    // Verificar si una categoría existe y está activa
    private function categoryExists($categoryId) {
        try {
            $query = "SELECT COUNT(*) as count FROM categories WHERE id = ? AND status = ?";
            $result = $this->db->select($query, [(int)$categoryId, STATUS_ACTIVE]);
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            error_log("Error checking category existence: " . $e->getMessage());
            return false;
        }
    }

    // Obtener productos para exportar
    public function getForExport($search = '', $categoryId = null, $status = null) {
        return $this->getAll($search, $categoryId, $status); // Sin límite para exportar todos
    }

    // Calcular precio final con impuestos
    public function calculateFinalPrice($basePrice, $taxRate) {
        if (!Security::validate($basePrice, 'float') || !Security::validate($taxRate, 'float')) {
            throw new Exception('Precios no válidos para el cálculo.');
        }

        $taxAmount = ($basePrice * $taxRate) / 100;
        return round($basePrice + $taxAmount, 2);
    }

    // Verificar si el producto tiene relaciones (cotizaciones, ventas)
    public function hasRelations($id) {
        if (!Security::validate($id, 'int')) {
            return false;
        }

        try {
            // Verificar detalles de cotizaciones
            $quotesQuery = "SELECT COUNT(*) as count FROM quote_details WHERE product_id = ?";
            $quotesResult = $this->db->select($quotesQuery, [(int)$id]);
            if ($quotesResult[0]['count'] > 0) {
                return true;
            }

            // Verificar detalles de ventas
            $salesQuery = "SELECT COUNT(*) as count FROM sale_details WHERE product_id = ?";
            $salesResult = $this->db->select($salesQuery, [(int)$id]);
            if ($salesResult[0]['count'] > 0) {
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error checking product relations: " . $e->getMessage());
            return true; // Ser conservador y no permitir eliminación si hay error
        }
    }

    // Obtener estadísticas generales de productos
    public function getGeneralStats() {
        try {
            $query = "SELECT 
                        COUNT(*) as total_products,
                        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_products,
                        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_products,
                        SUM(CASE WHEN stock IS NOT NULL THEN stock ELSE 0 END) as total_stock,
                        AVG(base_price) as avg_price,
                        MIN(base_price) as min_price,
                        MAX(base_price) as max_price,
                        COUNT(CASE WHEN stock IS NOT NULL AND stock <= 10 THEN 1 END) as low_stock_count
                      FROM products";
            
            $result = $this->db->select($query);
            if ($result) {
                $stats = $result[0];
                $stats['avg_price'] = round($stats['avg_price'] ?? 0, 2);
                return $stats;
            }

            return [];
        } catch (Exception $e) {
            error_log("Error getting general stats: " . $e->getMessage());
            throw new Exception('Error al obtener estadísticas generales: ' . $e->getMessage());
        }
    }
}
?>