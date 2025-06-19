<?php
// Modelo para gestionar clientes en el sistema CRM
require_once dirname(__DIR__, 2) . '/core/db.php';
require_once dirname(__DIR__, 2) . '/core/security.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

class ClientModel {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Error initializing Database: " . $e->getMessage());
            throw new Exception('No se pudo conectar a la base de datos.');
        }
    }

    // Crear un nuevo cliente
    public function create($name, $email, $phone = null, $address = null) {
        // Validar entradas
        if (!Security::validate($name, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre no válido o demasiado largo.');
        }
        if (!Security::validate($email, 'email', MAX_EMAIL_LENGTH)) {
            throw new Exception('Correo electrónico no válido.');
        }
        if ($phone && strlen($phone) > 20) {
            throw new Exception('Teléfono demasiado largo.');
        }

        // Verificar que el email no esté duplicado
        if ($this->emailExists($email)) {
            throw new Exception('Ya existe un cliente con este correo electrónico.');
        }

        $query = "INSERT INTO clients (name, email, phone, address, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
        $params = [
            Security::sanitize($name, 'string'),
            Security::sanitize($email, 'email'),
            $phone ? Security::sanitize($phone, 'string') : null,
            $address ? Security::sanitize($address, 'string') : null,
            STATUS_ACTIVE
        ];

        try {
            return $this->db->insert($query, $params);
        } catch (Exception $e) {
            error_log("Error creating client: " . $e->getMessage());
            throw new Exception('Error al crear el cliente: ' . $e->getMessage());
        }
    }

    // Obtener cliente por ID
    public function getById($id) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de cliente no válido.');
        }

        $query = "SELECT * FROM clients WHERE id = ?";
        try {
            $result = $this->db->select($query, [(int)$id]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error fetching client: " . $e->getMessage());
            throw new Exception('Error al obtener el cliente: ' . $e->getMessage());
        }
    }

    // Obtener cliente por email
    public function getByEmail($email) {
        if (!Security::validate($email, 'email', MAX_EMAIL_LENGTH)) {
            throw new Exception('Email no válido.');
        }

        $query = "SELECT * FROM clients WHERE email = ?";
        try {
            $result = $this->db->select($query, [Security::sanitize($email, 'email')]);
            return $result ? $result[0] : null;
        } catch (Exception $e) {
            error_log("Error fetching client by email: " . $e->getMessage());
            throw new Exception('Error al obtener el cliente: ' . $e->getMessage());
        }
    }

    // Listar todos los clientes con filtros opcionales
    public function getAll($search = '', $status = null, $limit = null, $offset = 0) {
        $query = "SELECT * FROM clients WHERE 1=1";
        $params = [];

        // Filtro de búsqueda
        if (!empty($search)) {
            $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchTerm = '%' . Security::sanitize($search, 'string') . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        // Filtro de estado
        if ($status !== null && Security::validate($status, 'int')) {
            $query .= " AND status = ?";
            $params[] = (int)$status;
        }

        // Ordenamiento
        $query .= " ORDER BY created_at DESC";

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
            error_log("Error listing clients: " . $e->getMessage());
            throw new Exception('Error al listar clientes: ' . $e->getMessage());
        }
    }

    // Contar total de clientes (para paginación)
    public function count($search = '', $status = null) {
        $query = "SELECT COUNT(*) as total FROM clients WHERE 1=1";
        $params = [];

        // Filtro de búsqueda
        if (!empty($search)) {
            $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
            $searchTerm = '%' . Security::sanitize($search, 'string') . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
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
            error_log("Error counting clients: " . $e->getMessage());
            throw new Exception('Error al contar clientes: ' . $e->getMessage());
        }
    }

    // Actualizar cliente
    public function update($id, $name, $email, $phone = null, $address = null, $status = STATUS_ACTIVE) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de cliente no válido.');
        }
        if (!Security::validate($name, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre no válido o demasiado largo.');
        }
        if (!Security::validate($email, 'email', MAX_EMAIL_LENGTH)) {
            throw new Exception('Correo electrónico no válido.');
        }
        if ($phone && strlen($phone) > 20) {
            throw new Exception('Teléfono demasiado largo.');
        }
        if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
            throw new Exception('Estado no válido.');
        }

        // Verificar que el email no esté duplicado (excepto el cliente actual)
        if ($this->emailExists($email, $id)) {
            throw new Exception('Ya existe otro cliente con este correo electrónico.');
        }

        $query = "UPDATE clients SET name = ?, email = ?, phone = ?, address = ?, status = ?, updated_at = NOW() WHERE id = ?";
        $params = [
            Security::sanitize($name, 'string'),
            Security::sanitize($email, 'email'),
            $phone ? Security::sanitize($phone, 'string') : null,
            $address ? Security::sanitize($address, 'string') : null,
            (int)$status,
            (int)$id
        ];

        try {
            return $this->db->execute($query, $params);
        } catch (Exception $e) {
            error_log("Error updating client: " . $e->getMessage());
            throw new Exception('Error al actualizar el cliente: ' . $e->getMessage());
        }
    }

    // Cambiar estado del cliente (soft delete)
    public function changeStatus($id, $status) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de cliente no válido.');
        }
        if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
            throw new Exception('Estado no válido.');
        }

        $query = "UPDATE clients SET status = ?, updated_at = NOW() WHERE id = ?";
        try {
            return $this->db->execute($query, [(int)$status, (int)$id]);
        } catch (Exception $e) {
            error_log("Error changing client status: " . $e->getMessage());
            throw new Exception('Error al cambiar estado del cliente: ' . $e->getMessage());
        }
    }

    // Eliminar cliente (cambio a estado inactivo)
    public function delete($id) {
        return $this->changeStatus($id, STATUS_INACTIVE);
    }

    // Verificar si un email ya existe
    private function emailExists($email, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM clients WHERE email = ?";
        $params = [Security::sanitize($email, 'email')];

        if ($excludeId) {
            $query .= " AND id != ?";
            $params[] = (int)$excludeId;
        }

        try {
            $result = $this->db->select($query, $params);
            return $result[0]['count'] > 0;
        } catch (Exception $e) {
            error_log("Error checking email existence: " . $e->getMessage());
            return false;
        }
    }

    // Obtener clientes para exportar
    public function getForExport($search = '', $status = null) {
        return $this->getAll($search, $status); // Sin límite para exportar todos
    }

    // Verificar si el cliente tiene relaciones (cotizaciones, ventas)
    public function hasRelations($id) {
        if (!Security::validate($id, 'int')) {
            return false;
        }

        try {
            // Verificar cotizaciones
            $quotesQuery = "SELECT COUNT(*) as count FROM quotes WHERE client_id = ?";
            $quotesResult = $this->db->select($quotesQuery, [(int)$id]);
            if ($quotesResult[0]['count'] > 0) {
                return true;
            }

            // Verificar ventas
            $salesQuery = "SELECT COUNT(*) as count FROM sales WHERE client_id = ?";
            $salesResult = $this->db->select($salesQuery, [(int)$id]);
            if ($salesResult[0]['count'] > 0) {
                return true;
            }

            return false;
        } catch (Exception $e) {
            error_log("Error checking client relations: " . $e->getMessage());
            return true; // Ser conservador y no permitir eliminación si hay error
        }
    }
}
?>