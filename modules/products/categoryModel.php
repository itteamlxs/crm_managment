<?php
// Modelo para gestionar categorías de productos en el sistema CRM
require_once dirname(__DIR__, 2) . '/core/db.php';
require_once dirname(__DIR__, 2) . '/core/security.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

class CategoryModel {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Error initializing Database: " . $e->getMessage());
            throw new Exception('No se pudo conectar a la base de datos.');
        }
    }

    // Crear una nueva categoría
    public function create($name) {
        // Validar entradas
        if (!Security::validate($name, 'string', 50)) {
            throw new Exception('Nombre de categoría no válido o demasiado largo (máximo 50 caracteres).');
        }

        // Verificar que el nombre no esté duplicado
        if ($this->nameExists($name)) {
            throw new Exception('Ya existe una categoría con este nombre.');
        }

        $query = "INSERT INTO categories (name, status) VALUES (?, ?)";
        $params = [
            Security::sanitize($name, 'string'),
            STATUS_ACTIVE
        ];

        try {
            return $this->db->insert($query, $params);
        } catch (Exception $e) {
            error_log("Error creating category: " . $e->getMessage());
            throw new Exception('Error al crear la categoría: ' . $e->getMessage());
        }
    }

    // Obtener categoría por ID
    public function getById($id) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de categoría no válido.');
        }

        $query = "SELECT * FROM categories WHERE id = ?";
        try {
            $result = $this->db->select($query, [(int)$id]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error fetching category: " . $e->getMessage());
            throw new Exception('Error al obtener la categoría: ' . $e->getMessage());
        }
    }

    // Obtener categoría por nombre
    public function getByName($name) {
        if (!Security::validate($name, 'string', 50)) {
            throw new Exception('Nombre no válido.');
        }

        $query = "SELECT * FROM categories WHERE name = ?";
        try {
            $result = $this->db->select($query, [Security::sanitize($name, 'string')]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error fetching category by name: " . $e->getMessage());
            throw new Exception('Error al obtener la categoría: ' . $e->getMessage());
        }
    }

    // Listar todas las categorías
    public function getAll($search = '', $status = null, $limit = null, $offset = 0) {
        $query = "SELECT * FROM categories WHERE 1=1";
        $params = [];

        // Filtro de búsqueda
        if (!empty($search)) {
            $query .= " AND name LIKE ?";
            $params[] = '%' . Security::sanitize($search, 'string') . '%';
        }

        // Filtro de estado
        if ($status !== null && Security::validate($status, 'int')) {
            $query .= " AND status = ?";
            $params[] = (int)$status;
        }

        // Ordenamiento
        $query .= " ORDER BY name ASC";

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
            error_log("Error listing categories: " . $e->getMessage());
            throw new Exception('Error al listar categorías: ' . $e->getMessage());
        }
    }

    // Listar solo categorías activas (para selectores)
    public function getActive() {
        $query = "SELECT id, name FROM categories WHERE status = ? ORDER BY name ASC";
        try {
            return $this->db->select($query, [STATUS_ACTIVE]);
        } catch (Exception $e) {
            error_log("Error listing active categories: " . $e->getMessage());
            throw new Exception('Error al listar categorías activas: ' . $e->getMessage());
        }
    }

    // Contar total de categorías
    public function count($search = '', $status = null) {
        $query = "SELECT COUNT(*) as total FROM categories WHERE 1=1";
        $params = [];

        // Filtro de búsqueda
        if (!empty($search)) {
            $query .= " AND name LIKE ?";
            $params[] = '%' . Security::sanitize($search, 'string') . '%';
        }

        // Filtro de estado
        if ($status !== null && Security::validate($status, 'int')) {
            $query .= " AND status = ?";
            $params[] = (int)$status;
        }

        try {
            $result = $this->db->select($query, $params);
            return $result[0]['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Error counting categories: " . $e->getMessage());
            throw new Exception('Error al contar categorías: ' . $e->getMessage());
        }
    }

    // Actualizar categoría
    public function update($id, $name, $status = STATUS_ACTIVE) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de categoría no válido.');
        }
        if (!Security::validate($name, 'string', 50)) {
            throw new Exception('Nombre de categoría no válido o demasiado largo.');
        }
        if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
            throw new Exception('Estado no válido.');
        }

        // Verificar que el nombre no esté duplicado (excepto la categoría actual)
        if ($this->nameExists($name, $id)) {
            throw new Exception('Ya existe otra categoría con este nombre.');
        }

        $query = "UPDATE categories SET name = ?, status = ? WHERE id = ?";
        $params = [
            Security::sanitize($name, 'string'),
            (int)$status,
            (int)$id
        ];

        try {
            return $this->db->execute($query, $params);
        } catch (Exception $e) {
            error_log("Error updating category: " . $e->getMessage());
            throw new Exception('Error al actualizar la categoría: ' . $e->getMessage());
        }
    }

    // Cambiar estado de la categoría
    public function changeStatus($id, $status) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de categoría no válido.');
        }
        if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
            throw new Exception('Estado no válido.');
        }

        $query = "UPDATE categories SET status = ? WHERE id = ?";
        try {
            return $this->db->execute($query, [(int)$status, (int)$id]);
        } catch (Exception $e) {
            error_log("Error changing category status: " . $e->getMessage());
            throw new Exception('Error al cambiar estado de la categoría: ' . $e->getMessage());
        }
    }

    // Eliminar categoría (solo si no tiene productos asociados)
    public function delete($id) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de categoría no válido.');
        }

        // Verificar si tiene productos asociados
        if ($this->hasProducts($id)) {
            throw new Exception('No se puede eliminar la categoría porque tiene productos asociados.');
        }

        $query = "UPDATE categories SET status = ? WHERE id = ?";
        try {
            return $this->db->execute($query, [STATUS_INACTIVE, (int)$id]);
        } catch (Exception $e) {
            error_log("Error deleting category: " . $e->getMessage());
            throw new Exception('Error al eliminar la categoría: ' . $e->getMessage());
        }
    }

    // Verificar si un nombre ya existe
    private function nameExists($name, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM categories WHERE name = ?";
        $params = [Security::sanitize($name, 'string')];

        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = (int)$excludeId;
        }

        try {
            $result = $this->db->select($query, $params);
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            error_log("Error checking name existence: " . $e->getMessage());
            return false;
        }
    }

    // Verificar si la categoría tiene productos asociados
    public function hasProducts($id) {
        if (!Security::validate($id, 'int')) {
            return false;
        }

        try {
            $query = "SELECT COUNT(*) as count FROM products WHERE category_id = ?";
            $result = $this->db->select($query, [(int)$id]);
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            error_log("Error checking category products: " . $e->getMessage());
            return true; // Ser conservador y no permitir eliminación si hay error
        }
    }

    // Obtener estadísticas de la categoría
    public function getStats($id) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de categoría no válido.');
        }

        try {
            $stats = [
                'total_products' => 0,
                'active_products' => 0,
                'inactive_products' => 0,
                'total_stock' => 0,
                'avg_price' => 0
            ];

            // Contar productos
            $query = "SELECT 
                        COUNT(*) as total_products,
                        SUM(CASE WHEN status = 1 THEN 1 ELSE 0 END) as active_products,
                        SUM(CASE WHEN status = 0 THEN 1 ELSE 0 END) as inactive_products,
                        SUM(CASE WHEN stock IS NOT NULL THEN stock ELSE 0 END) as total_stock,
                        AVG(base_price) as avg_price
                      FROM products 
                      WHERE category_id = ?";
            
            $result = $this->db->select($query, [(int)$id]);
            if ($result) {
                $stats = array_merge($stats, $result[0]);
                $stats['avg_price'] = round($stats['avg_price'] ?? 0, 2);
            }

            return $stats;
        } catch (Exception $e) {
            error_log("Error getting category stats: " . $e->getMessage());
            throw new Exception('Error al obtener estadísticas de la categoría: ' . $e->getMessage());
        }
    }
}
?>