<?php
// Controlador para gestionar autenticación y usuarios - CORREGIDO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/session.php';
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/users/userModel.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

class UserController {
    private $session;
    private $model;

    public function __construct() {
        try {
            $this->session = new Session();
            $this->model = new UserModel();
            Security::setHeaders();
        } catch (Exception $e) {
            error_log("Error initializing UserController: " . $e->getMessage());
            die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    // Procesar login - MÉTODO PRINCIPAL CORREGIDO
    
    public function login() {
    // Solo procesar si es POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['error' => ''];
    }

    try {
        // Validar token CSRF PRIMERO
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!$this->session->validateCsrfToken($csrfToken)) {
            error_log("CSRF validation failed for login attempt");
            return ['error' => 'Error de seguridad: Token CSRF inválido. Intente nuevamente.'];
        }

        // Obtener y validar datos del formulario
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validaciones básicas
        if (empty($username) || empty($password)) {
            return ['error' => 'Por favor, complete todos los campos.'];
        }

        if (strlen($username) > MAX_NAME_LENGTH) {
            return ['error' => 'El nombre de usuario es demasiado largo.'];
        }

        if (strlen($password) < 8) {
            return ['error' => 'La contraseña debe tener al menos 8 caracteres.'];
        }

        // Sanitizar username
        $username = Security::sanitize($username, 'string');
        if (!$username) {
            return ['error' => 'Nombre de usuario no válido.'];
        }

        // Intentar validar credenciales
        $user = $this->model->validateCredentials($username, $password);
        
        if ($user) {
            // Login exitoso - AQUÍ ESTÁ EL CAMBIO PRINCIPAL
            // Pasamos el nombre del usuario como tercer parámetro
            $userName = $user['name'] ?? $user['full_name'] ?? $username;
            $this->session->login($user['id'], $user['role'], $userName);
            
            // Redirigir según el rol
            if ($user['role'] == ROLE_ADMIN) {
                header('Location: ' . BASE_URL . '/modules/dashboard/dashboardView.php?success=logged_in');
            } else {
                header('Location: ' . BASE_URL . '/modules/dashboard/dashboardView.php?success=logged_in');
            }
            exit;
        } else {
            return ['error' => 'Credenciales inválidas o usuario inactivo.'];
        }

    } catch (Exception $e) {
        error_log("Error during login: " . $e->getMessage());
        return ['error' => 'Error del sistema. Intente nuevamente.'];
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

    // Verificar si es administrador
    public function isAdmin() {
        return $this->session->hasRole(ROLE_ADMIN);
    }

    // Procesar logout
    public function logout() {
        try {
            $this->session->destroy();
            header('Location: ' . BASE_URL . '/modules/users/loginView.php?success=logged_out');
            exit;
        } catch (Exception $e) {
            error_log("Error during logout: " . $e->getMessage());
            return ['error' => 'Error al cerrar sesión.'];
        }
    }

    // Listar usuarios (solo admin)
    public function list() {
        if (!$this->session->isLoggedIn() || !$this->session->hasRole(ROLE_ADMIN)) {
            header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=unauthorized');
            exit;
        }
        try {
            return $this->model->getAll();
        } catch (Exception $e) {
            error_log("Error listing users: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Crear usuario (solo admin)
    public function create() {
        if (!$this->session->isLoggedIn() || !$this->session->hasRole(ROLE_ADMIN)) {
            header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=unauthorized');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            $username = Security::sanitize($_POST['username'] ?? '', 'string');
            $password = $_POST['password'] ?? '';
            $role = (int)($_POST['role'] ?? ROLE_SELLER);
            $email = Security::sanitize($_POST['email'] ?? '', 'email');
            $name = Security::sanitize($_POST['name'] ?? '', 'string');

            try {
                $this->model->create($username, $password, $role, $email, $name);
                header('Location: ' . BASE_URL . '/modules/users/userManagement.php?success=created');
                exit;
            } catch (Exception $e) {
                error_log("Error creating user: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        return [];
    }

    // Actualizar usuario (solo admin)
    public function update() {
        if (!$this->session->isLoggedIn() || !$this->session->hasRole(ROLE_ADMIN)) {
            header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=unauthorized');
            exit;
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            $id = (int)($_POST['id'] ?? 0);
            $username = Security::sanitize($_POST['username'] ?? '', 'string');
            $email = Security::sanitize($_POST['email'] ?? '', 'email');
            $name = Security::sanitize($_POST['name'] ?? '', 'string');
            $role = (int)($_POST['role'] ?? ROLE_SELLER);
            $status = (int)($_POST['status'] ?? STATUS_ACTIVE);
            $password = !empty($_POST['password']) ? $_POST['password'] : null;

            try {
                $this->model->update($id, $username, $email, $name, $role, $status, $password);
                header('Location: ' . BASE_URL . '/modules/users/userManagement.php?success=updated');
                exit;
            } catch (Exception $e) {
                error_log("Error updating user: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        return [];
    }

    // Eliminar usuario (solo admin)
    public function delete($id) {
        if (!$this->session->isLoggedIn() || !$this->session->hasRole(ROLE_ADMIN)) {
            header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=unauthorized');
            exit;
        }
        
        try {
            $this->model->delete($id);
            header('Location: ' . BASE_URL . '/modules/users/userManagement.php?success=deleted');
            exit;
        } catch (Exception $e) {
            error_log("Error deleting user: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}