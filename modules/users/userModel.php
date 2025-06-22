<?php
// Modelo para gestionar usuarios en el sistema CRM - CORREGIDO PARA BD EXISTENTE
require_once dirname(__DIR__, 2) . '/core/db.php';
require_once dirname(__DIR__, 2) . '/core/security.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

class UserModel {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
        } catch (Exception $e) {
            error_log("Error initializing Database: " . $e->getMessage());
            throw new Exception('No se pudo conectar a la base de datos.');
        }
    }

    // Crear un nuevo usuario
    public function create($username, $password, $role, $email, $name) {
        // Validar entradas
        if (!Security::validate($username, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre de usuario no válido o demasiado largo.');
        }
        if (!Security::validate($email, 'email', MAX_EMAIL_LENGTH)) {
            throw new Exception('Correo electrónico no válido.');
        }
        if (!Security::validate($name, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre no válido o demasiado largo.');
        }
        if (!in_array($role, [ROLE_ADMIN, ROLE_SELLER])) {
            throw new Exception('Rol no válido.');
        }
        if (strlen($password) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres.');
        }

        // Hash de la contraseña
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        if (!$hashedPassword) {
            throw new Exception('Error al generar el hash de la contraseña.');
        }

        // CORREGIDO: Usar nombres de columna correctos según schema.sql
        $query = "INSERT INTO users (username, password_hash, role, email, full_name, status) VALUES (?, ?, ?, ?, ?, ?)";
        $params = [
            Security::sanitize($username, 'string'),
            $hashedPassword,
            (int)$role,
            Security::sanitize($email, 'email'),
            Security::sanitize($name, 'string'),
            STATUS_ACTIVE
        ];

        try {
            return $this->db->insert($query, $params);
        } catch (Exception $e) {
            error_log("Error creating user: " . $e->getMessage());
            throw new Exception('Error al crear el usuario: ' . $e->getMessage());
        }
    }

    // Obtener usuario por ID
    public function getById($id) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de usuario no válido.');
        }

        // CORREGIDO: Usar nombres de columna correctos
        $query = "SELECT id, username, role, email, full_name, status, created_at, updated_at FROM users WHERE id = ?";
        try {
            $result = $this->db->select($query, [(int)$id]);
            if ($result) {
                // Mapear full_name a name para compatibilidad con el resto del código
                $result[0]['name'] = $result[0]['full_name'];
                return $result[0];
            }
            return null;
        } catch (Exception $e) {
            error_log("Error fetching user: " . $e->getMessage());
            throw new Exception('Error al obtener el usuario: ' . $e->getMessage());
        }
    }

    // Obtener usuario por nombre de usuario (para login)
    public function getByUsername($username) {
        if (!Security::validate($username, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre de usuario no válido.');
        }

        // CORREGIDO: Usar password_hash en lugar de password, full_name en lugar de name
        $query = "SELECT id, username, password_hash, role, email, full_name, status FROM users WHERE username = ? OR email = ?";
        try {
            $sanitizedUsername = Security::sanitize($username, 'string');
            $result = $this->db->select($query, [$sanitizedUsername, $sanitizedUsername]);
            if ($result) {
                // Mapear password_hash a password y full_name a name para compatibilidad
                $result[0]['password'] = $result[0]['password_hash'];
                $result[0]['name'] = $result[0]['full_name'];
                return $result[0];
            }
            return null;
        } catch (Exception $e) {
            error_log("Error fetching user by username: " . $e->getMessage());
            throw new Exception('Error al obtener el usuario: ' . $e->getMessage());
        }
    }

    // Listar todos los usuarios
    public function getAll() {
        // CORREGIDO: Usar nombres de columna correctos
        $query = "SELECT id, username, role, email, full_name, status, created_at, updated_at FROM users ORDER BY created_at DESC";
        try {
            $results = $this->db->select($query);
            // Mapear full_name a name para compatibilidad
            foreach ($results as &$user) {
                $user['name'] = $user['full_name'];
            }
            return $results;
        } catch (Exception $e) {
            error_log("Error listing users: " . $e->getMessage());
            throw new Exception('Error al listar usuarios: ' . $e->getMessage());
        }
    }

    // Actualizar usuario
    public function update($id, $username, $email, $name, $role, $status, $password = null) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de usuario no válido.');
        }
        if (!Security::validate($username, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre de usuario no válido o demasiado largo.');
        }
        if (!Security::validate($email, 'email', MAX_EMAIL_LENGTH)) {
            throw new Exception('Correo electrónico no válido.');
        }
        if (!Security::validate($name, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre no válido o demasiado largo.');
        }
        if (!in_array($role, [ROLE_ADMIN, ROLE_SELLER])) {
            throw new Exception('Rol no válido.');
        }
        if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
            throw new Exception('Estado no válido.');
        }
        if ($password && strlen($password) < 8) {
            throw new Exception('La contraseña debe tener al menos 8 caracteres.');
        }

        // CORREGIDO: Usar full_name en lugar de name
        $query = "UPDATE users SET username = ?, email = ?, full_name = ?, role = ?, status = ?";
        $params = [
            Security::sanitize($username, 'string'),
            Security::sanitize($email, 'email'),
            Security::sanitize($name, 'string'),
            (int)$role,
            (int)$status
        ];

        if ($password) {
            // CORREGIDO: Usar password_hash en lugar de password
            $query .= ", password_hash = ?";
            $params[] = password_hash($password, PASSWORD_DEFAULT);
        }
        
        $query .= " WHERE id = ?";
        $params[] = (int)$id;

        try {
            return $this->db->execute($query, $params);
        } catch (Exception $e) {
            error_log("Error updating user: " . $e->getMessage());
            throw new Exception('Error al actualizar el usuario: ' . $e->getMessage());
        }
    }

    // Eliminar usuario (cambio a estado inactivo)
    public function delete($id) {
        if (!Security::validate($id, 'int')) {
            throw new Exception('ID de usuario no válido.');
        }

        $query = "UPDATE users SET status = ? WHERE id = ?";
        try {
            return $this->db->execute($query, [STATUS_INACTIVE, (int)$id]);
        } catch (Exception $e) {
            error_log("Error deleting user: " . $e->getMessage());
            throw new Exception('Error al eliminar el usuario: ' . $e->getMessage());
        }
    }

    // Validar credenciales para login
    public function validateCredentials($username, $password) {
        if (!Security::validate($username, 'string', MAX_NAME_LENGTH)) {
            throw new Exception('Nombre de usuario no válido.');
        }
        if (strlen($password) < 8) {
            throw new Exception('Contraseña no válida.');
        }

        try {
            $user = $this->getByUsername($username);
            if ($user && $user['status'] == STATUS_ACTIVE && password_verify($password, $user['password'])) {
                // Actualizar último login
                $updateQuery = "UPDATE users SET last_login = NOW(), failed_login_attempts = 0 WHERE id = ?";
                $this->db->execute($updateQuery, [$user['id']]);
                return $user;
            }
            
            // Si el usuario existe pero falló la validación, incrementar intentos fallidos
            if ($user) {
                $updateQuery = "UPDATE users SET failed_login_attempts = failed_login_attempts + 1 WHERE id = ?";
                $this->db->execute($updateQuery, [$user['id']]);
            }
            
            return null;
        } catch (Exception $e) {
            error_log("Error validating credentials: " . $e->getMessage());
            throw new Exception('Error al validar credenciales: ' . $e->getMessage());
        }
    }
}