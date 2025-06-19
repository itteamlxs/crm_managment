<?php
// Controlador para gestionar clientes
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/session.php';
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/clients/clientModel.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

class ClientController {
    private $session;
    private $model;

    public function __construct() {
        try {
            $this->session = new Session();
            $this->model = new ClientModel();
            Security::setHeaders();
        } catch (Exception $e) {
            error_log("Error initializing ClientController: " . $e->getMessage());
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

    // Listar clientes con filtros
    public function list($search = '', $status = null, $page = 1, $perPage = 20) {
        $this->checkAuth();
        
        try {
            $offset = ($page - 1) * $perPage;
            $clients = $this->model->getAll($search, $status, $perPage, $offset);
            $total = $this->model->count($search, $status);
            
            return [
                'clients' => $clients,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage,
                'totalPages' => ceil($total / $perPage),
                'search' => $search,
                'status' => $status
            ];
        } catch (Exception $e) {
            error_log("Error listing clients: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Obtener cliente por ID
    public function getById($id) {
        $this->checkAuth();
        
        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de cliente no válido.'];
        }

        try {
            $client = $this->model->getById($id);
            return $client ? ['client' => $client] : ['error' => 'Cliente no encontrado.'];
        } catch (Exception $e) {
            error_log("Error getting client: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Crear nuevo cliente
    public function create() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            // Obtener y validar datos
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');

            // Validaciones básicas
            if (empty($name) || empty($email)) {
                return ['error' => 'Nombre y correo electrónico son requeridos.'];
            }

            if (strlen($name) > MAX_NAME_LENGTH) {
                return ['error' => 'El nombre es demasiado largo.'];
            }

            if (!Utils::isValidEmail($email)) {
                return ['error' => 'Correo electrónico no válido.'];
            }

            if (!empty($phone) && strlen($phone) > 20) {
                return ['error' => 'El teléfono es demasiado largo.'];
            }

            try {
                $clientId = $this->model->create($name, $email, $phone, $address);
                header('Location: ' . BASE_URL . '/modules/clients/clientList.php?success=created');
                exit;
            } catch (Exception $e) {
                error_log("Error creating client: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Actualizar cliente
    public function update() {
        $this->checkAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            // Obtener y validar datos
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $status = (int)($_POST['status'] ?? STATUS_ACTIVE);

            // Validaciones básicas
            if ($id <= 0) {
                return ['error' => 'ID de cliente no válido.'];
            }

            if (empty($name) || empty($email)) {
                return ['error' => 'Nombre y correo electrónico son requeridos.'];
            }

            if (strlen($name) > MAX_NAME_LENGTH) {
                return ['error' => 'El nombre es demasiado largo.'];
            }

            if (!Utils::isValidEmail($email)) {
                return ['error' => 'Correo electrónico no válido.'];
            }

            if (!empty($phone) && strlen($phone) > 20) {
                return ['error' => 'El teléfono es demasiado largo.'];
            }

            if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
                return ['error' => 'Estado no válido.'];
            }

            try {
                $this->model->update($id, $name, $email, $phone, $address, $status);
                header('Location: ' . BASE_URL . '/modules/clients/clientList.php?success=updated');
                exit;
            } catch (Exception $e) {
                error_log("Error updating client: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Cambiar estado del cliente
    public function changeStatus($id, $status) {
        $this->checkAuth();
        
        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de cliente no válido.'];
        }

        if (!in_array($status, [STATUS_ACTIVE, STATUS_INACTIVE])) {
            return ['error' => 'Estado no válido.'];
        }

        try {
            $this->model->changeStatus($id, $status);
            $action = $status == STATUS_ACTIVE ? 'activated' : 'deactivated';
            header('Location: ' . BASE_URL . '/modules/clients/clientList.php?success=' . $action);
            exit;
        } catch (Exception $e) {
            error_log("Error changing client status: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Eliminar cliente
    public function delete($id) {
        $this->checkAuth();
        
        // Solo administradores pueden eliminar
        if (!$this->session->hasRole(ROLE_ADMIN)) {
            return ['error' => 'No tiene permisos para eliminar clientes.'];
        }

        if (!Security::validate($id, 'int')) {
            return ['error' => 'ID de cliente no válido.'];
        }

        try {
            // Verificar si el cliente tiene relaciones
            if ($this->model->hasRelations($id)) {
                return ['error' => 'No se puede eliminar el cliente porque tiene cotizaciones o ventas asociadas.'];
            }

            $this->model->delete($id);
            header('Location: ' . BASE_URL . '/modules/clients/clientList.php?success=deleted');
            exit;
        } catch (Exception $e) {
            error_log("Error deleting client: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Exportar clientes a CSV
    public function exportCsv($search = '', $status = null) {
        $this->checkAuth();
        
        try {
            $clients = $this->model->getForExport($search, $status);
            
            // Headers para CSV
            $headers = [
                'ID',
                'Nombre',
                'Correo Electrónico', 
                'Teléfono',
                'Dirección',
                'Estado',
                'Fecha de Creación',
                'Última Actualización'
            ];
            
            // Preparar datos
            $data = [$headers];
            foreach ($clients as $client) {
                $data[] = [
                    $client['id'],
                    $client['name'],
                    $client['email'],
                    $client['phone'] ?? '',
                    $client['address'] ?? '',
                    $client['status'] == STATUS_ACTIVE ? 'Activo' : 'Inactivo',
                    Utils::formatDateDisplay($client['created_at']),
                    $client['updated_at'] ? Utils::formatDateDisplay($client['updated_at']) : ''
                ];
            }
            
            // Generar nombre de archivo
            $filename = 'clientes_' . date('Y-m-d_H-i-s') . '.csv';
            
            // Enviar CSV
            Utils::generateCsv($data, $filename);
            
        } catch (Exception $e) {
            error_log("Error exporting clients: " . $e->getMessage());
            return ['error' => 'Error al exportar clientes: ' . $e->getMessage()];
        }
    }

    // Buscar clientes (AJAX)
    public function search($term) {
        $this->checkAuth();
        
        if (strlen($term) < 2) {
            return ['clients' => []];
        }

        try {
            $clients = $this->model->getAll($term, STATUS_ACTIVE, 10); // Máximo 10 resultados
            return ['clients' => $clients];
        } catch (Exception $e) {
            error_log("Error searching clients: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Validar datos de cliente (AJAX)
    public function validateData($field, $value, $excludeId = null) {
        $this->checkAuth();
        
        try {
            switch ($field) {
                case 'email':
                    if (!Utils::isValidEmail($value)) {
                        return ['valid' => false, 'message' => 'Correo electrónico no válido.'];
                    }
                    
                    $existing = $this->model->getByEmail($value);
                    if ($existing && (!$excludeId || $existing['id'] != $excludeId)) {
                        return ['valid' => false, 'message' => 'Este correo ya está registrado.'];
                    }
                    
                    return ['valid' => true];
                    
                case 'name':
                    if (strlen($value) > MAX_NAME_LENGTH) {
                        return ['valid' => false, 'message' => 'El nombre es demasiado largo.'];
                    }
                    
                    if (strlen(trim($value)) === 0) {
                        return ['valid' => false, 'message' => 'El nombre es requerido.'];
                    }
                    
                    return ['valid' => true];
                    
                default:
                    return ['valid' => false, 'message' => 'Campo no válido.'];
            }
        } catch (Exception $e) {
            error_log("Error validating data: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Error de validación.'];
        }
    }
}
?>