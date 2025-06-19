<?php
// Controlador para gestionar autenticación y usuarios
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

    // Procesar login
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            $csrfToken = $_POST['csrf_token'] ?? '';
            if (!$this->session->validateCsrfToken($csrfToken)) {
                error_log("CSRF validation failed for login attempt");
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            $username = Security::sanitize($_POST['username'] ?? '', 'string');
            $password = $_POST['password'] ?? '';

            try {
                $user = $this->model->validateCredentials($username, $password);
                if ($user) {
                    $this->session->login($user['id'], $user['role']);
                    header('Location: ' . BASE_URL . '/index.php?success=logged_in');
                    exit;
                } else {
                    return ['error' => 'Credenciales inválidas o usuario inactivo.'];
                }
            } catch (Exception $e) {
                error_log("Error during login: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        return [];
    }

    // Procesar logout
    public function logout() {
        try {
            $this->session->destroy();
            Utils::redirect('index.php');
        } catch (Exception $e) {
            error_log("Error during logout: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    // Listar usuarios (solo admin)
    public function list() {
        if (!$this->session->isLoggedIn() || !$this->session->hasRole(ROLE_ADMIN)) {
            Utils::redirect('index.php?error=unauthorized');
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
            Utils::redirect('index.php?error=unauthorized');
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
                Utils::redirect('modules/users/userManagement.php?success=created');
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
            Utils::redirect('index.php?error=unauthorized');
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
                Utils::redirect('modules/users/userManagement.php?success=updated');
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
            Utils::redirect('index.php?error=unauthorized');
        }
        try {
            $this->model->delete($id);
            Utils::redirect('modules/users/userManagement.php?success=deleted');
        } catch (Exception $e) {
            error_log("Error deleting user: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
?>