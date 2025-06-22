<?php
// Clase para gestionar sesiones seguras
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__) . '/config/constants.php';
} catch (Exception $e) {
    error_log("Error loading constants.php: " . $e->getMessage());
    die('Error al cargar configuraciones: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

class Session {
    private $timeout;
    private $userName = null;

    public function __construct() {
        // Si hay una sesión activa, NO destruirla para permitir continuidad
        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->timeout = 1800;
            $this->regenerateIdIfNeeded();
            $this->checkTimeout();
            // Generar token CSRF al inicio de la sesión si no existe
            if (!isset($_SESSION['csrf_token'])) {
                $this->generateCsrfToken();
            }
            return;
        }

        // Configurar opciones de sesión solo si no está activa
        if (session_status() === PHP_SESSION_NONE) {
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 0); // Cambiar a 1 en producción con HTTPS
            ini_set('session.cookie_samesite', 'Strict');
            session_name('crm_session');
        }

        // Iniciar sesión
        if (session_status() === PHP_SESSION_NONE) {
            if (!session_start()) {
                error_log("Error starting session");
                throw new Exception('No se pudo iniciar la sesión.');
            }
        }

        $this->timeout = 1800; // Valor fijo para timeout
        $this->regenerateIdIfNeeded();
        $this->checkTimeout();

        // Generar token CSRF al inicio de la sesión si no existe
        if (!isset($_SESSION['csrf_token'])) {
            $this->generateCsrfToken();
        }
    }

    // Regenerar ID de sesión si es necesario
    private function regenerateIdIfNeeded() {
        if (!isset($_SESSION['last_regeneration']) || time() - $_SESSION['last_regeneration'] > 300) {
            session_regenerate_id(true);
            $_SESSION['last_regeneration'] = time();
        }
    }

    // ✅ CORRECCIÓN: Verificar timeout solo si el usuario está logueado
    private function checkTimeout() {
        // SOLO verificar timeout si hay un usuario logueado
        if ($this->isLoggedIn()) {
            if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $this->timeout)) {
                $this->destroy();
                throw new Exception('Sesión expirada por inactividad.');
            }
        }
        
        // Actualizar actividad siempre (incluso para usuarios no logueados)
        $_SESSION['last_activity'] = time();
    }

    // Iniciar sesión de usuario

    public function login($userId, $userRole, $userName = 'Usuario') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $_SESSION['user_id'] = $userId;
    $_SESSION['user_role'] = $userRole;
    $_SESSION['user_name'] = $userName;
    $_SESSION['login_time'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    $this->userId = $userId;
    $this->userRole = $userRole;
    $this->userName = $userName;
    
    // Regenerar ID de sesión por seguridad
    session_regenerate_id(true);
    
    return true;
}

// Agregar el método getUserName()
public function getUserName() {
    if ($this->userName) {
        return $this->userName;
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    return $_SESSION['user_name'] ?? 'Usuario';
}

// Modificar el método checkSession() para cargar el nombre del usuario
private function checkSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
        $this->userId = $_SESSION['user_id'];
        $this->userRole = $_SESSION['user_role'];
        $this->userName = $_SESSION['user_name'] ?? 'Usuario';
        return true;
    }
    
    return false;
}





    // Verificar si el usuario está autenticado
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    // Verificar si el usuario tiene un rol específico
    public function hasRole($role) {
        return $this->isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === (int)$role;
    }

    // Obtener ID del usuario
    public function getUserId() {
        return $this->isLoggedIn() ? $_SESSION['user_id'] : null;
    }

    // Generar token CSRF
    public function generateCsrfToken() {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            error_log("CSRF token generated: " . $_SESSION['csrf_token']); // Depuración
            return $_SESSION['csrf_token'];
        } catch (Exception $e) {
            error_log("Error generating CSRF token: " . $e->getMessage());
            throw new Exception('Error al generar token CSRF.');
        }
    }

    // Obtener token CSRF actual (sin regenerar)
    public function getCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            return $this->generateCsrfToken();
        }
        return $_SESSION['csrf_token'];
    }

    // ✅ CORRECCIÓN: Validar token CSRF correctamente
    public function validateCsrfToken($token) {
        $storedToken = $_SESSION['csrf_token'] ?? '';
        error_log("Validating CSRF token: provided=$token, stored=$storedToken"); // Depuración
        
        $isValid = !empty($storedToken) && !empty($token) && hash_equals($storedToken, $token);
        
        // CRÍTICO: Solo generar nuevo token DESPUÉS de devolver el resultado
        // y solo si la validación fue exitosa
        if ($isValid) {
            $this->generateCsrfToken();
        }
        
        return $isValid;
    }

    // Destruir sesión
    public function destroy() {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }
}