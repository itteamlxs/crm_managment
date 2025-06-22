<?php
// Controlador para gestionar configuraciones del sistema CRM - CORREGIDO
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    require_once dirname(__DIR__, 2) . '/core/session.php';
    require_once dirname(__DIR__, 2) . '/core/security.php';
    require_once dirname(__DIR__, 2) . '/core/utils.php';
    require_once dirname(__DIR__) . '/settings/settingsModel.php';
    require_once dirname(__DIR__, 2) . '/config/constants.php';
} catch (Exception $e) {
    die('Error al cargar dependencias: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

class SettingsController {
    private $session;
    private $model;

    public function __construct() {
        try {
            $this->session = new Session();
            $this->model = new SettingsModel();
            Security::setHeaders();
        } catch (Exception $e) {
            error_log("Error initializing SettingsController: " . $e->getMessage());
            die('Error al inicializar controlador: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }

    // Verificar autenticación y permisos de administrador
    private function checkAdminAuth() {
        if (!$this->session->isLoggedIn()) {
            header('Location: ' . BASE_URL . '/modules/users/loginView.php?error=session_expired');
            exit;
        }
        
        if (!$this->session->hasRole(ROLE_ADMIN)) {
            header('Location: ' . BASE_URL . '/modules/dashboard/dashboardView.php?error=unauthorized');
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

    // Verificar si es administrador
    public function isAdmin() {
        return $this->session->hasRole(ROLE_ADMIN);
    }

    // Obtener todas las configuraciones
    public function getAllSettings() {
        $this->checkAdminAuth();
        
        try {
            return $this->model->getAll();
        } catch (Exception $e) {
            error_log("Error getting all settings: " . $e->getMessage());
            return [];
        }
    }

    // Obtener configuración específica (método público para otros módulos)
    public function getSetting($key) {
        try {
            return $this->model->get($key);
        } catch (Exception $e) {
            error_log("Error getting setting: " . $e->getMessage());
            return null;
        }
    }

    // Actualizar configuración general de la empresa - CORREGIDO CON COMPANY_SLOGAN
    public function updateGeneralSettings() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            try {
                $data = [
                    'company_name' => $_POST['company_name'] ?? '',
                    'company_slogan' => $_POST['company_slogan'] ?? '', // ← AGREGADO
                    'company_address' => $_POST['company_address'] ?? '',
                    'company_phone' => $_POST['company_phone'] ?? '',
                    'company_email' => $_POST['company_email'] ?? '',
                    'company_website' => $_POST['company_website'] ?? ''
                ];

                $this->model->updateGeneral($data);
                header('Location: ' . BASE_URL . '/modules/settings/settingsView.php?success=general_updated&tab=general');
                exit;

            } catch (Exception $e) {
                error_log("Error updating general settings: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Actualizar configuración regional
    public function updateRegionalSettings() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            try {
                $data = [
                    'language' => $_POST['language'] ?? 'es',
                    'timezone' => $_POST['timezone'] ?? 'America/Mexico_City',
                    'currency_code' => $_POST['currency_code'] ?? 'USD',
                    'currency_symbol' => $_POST['currency_symbol'] ?? '$',
                    'date_format' => $_POST['date_format'] ?? 'd/m/Y'
                ];

                $this->model->updateRegional($data);
                header('Location: ' . BASE_URL . '/modules/settings/settingsView.php?success=regional_updated&tab=regional');
                exit;

            } catch (Exception $e) {
                error_log("Error updating regional settings: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Actualizar configuración de impuestos
    public function updateTaxSettings() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            try {
                $data = [
                    'tax_rate' => $_POST['tax_rate'] ?? 0,
                    'tax_name' => $_POST['tax_name'] ?? 'IVA'
                ];

                $this->model->updateTax($data);
                header('Location: ' . BASE_URL . '/modules/settings/settingsView.php?success=tax_updated&tab=tax');
                exit;

            } catch (Exception $e) {
                error_log("Error updating tax settings: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Actualizar configuración de correo
    public function updateEmailSettings() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            try {
                $data = [
                    'smtp_host' => $_POST['smtp_host'] ?? '',
                    'smtp_port' => $_POST['smtp_port'] ?? 587,
                    'smtp_username' => $_POST['smtp_username'] ?? '',
                    'smtp_password' => $_POST['smtp_password'] ?? '',
                    'smtp_security' => $_POST['smtp_security'] ?? 'tls',
                    'smtp_from_email' => $_POST['smtp_from_email'] ?? '',
                    'smtp_from_name' => $_POST['smtp_from_name'] ?? ''
                ];

                $this->model->updateEmail($data);
                header('Location: ' . BASE_URL . '/modules/settings/settingsView.php?success=email_updated&tab=email');
                exit;

            } catch (Exception $e) {
                error_log("Error updating email settings: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Cambiar tema
    public function updateTheme() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            try {
                $theme = $_POST['theme'] ?? 'light';
                $this->model->updateTheme($theme);
                
                header('Location: ' . BASE_URL . '/modules/settings/settingsView.php?success=theme_updated&tab=appearance');
                exit;

            } catch (Exception $e) {
                error_log("Error updating theme: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Subir logo de la empresa
    public function uploadLogo() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                return ['error' => 'Error al subir el archivo.'];
            }

            try {
                // Crear directorio de logos si no existe
                $logoDir = dirname(__DIR__, 2) . '/assets/images/logos';
                if (!is_dir($logoDir)) {
                    mkdir($logoDir, 0755, true);
                }

                // Validar archivo
                $allowedTypes = ['image/png', 'image/jpeg', 'image/jpg', 'image/gif'];
                $maxSize = 2 * 1024 * 1024; // 2MB

                $uploadResult = Utils::uploadFile($_FILES['logo'], $logoDir, $allowedTypes, $maxSize);
                
                if (!$uploadResult['success']) {
                    return ['error' => $uploadResult['message']];
                }

                // Eliminar logo anterior si existe
                $currentSettings = $this->model->getAll();
                if ($currentSettings['company_logo'] && file_exists($logoDir . '/' . $currentSettings['company_logo'])) {
                    unlink($logoDir . '/' . $currentSettings['company_logo']);
                }

                // Actualizar ruta del logo en BD
                $logoPath = 'assets/images/logos/' . $uploadResult['filename'];
                $this->model->updateLogo($logoPath);

                header('Location: ' . BASE_URL . '/modules/settings/settingsView.php?success=logo_updated&tab=appearance');
                exit;

            } catch (Exception $e) {
                error_log("Error uploading logo: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Eliminar logo actual
    public function removeLogo() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            try {
                // Obtener logo actual
                $currentSettings = $this->model->getAll();
                if ($currentSettings['company_logo']) {
                    $logoPath = dirname(__DIR__, 2) . '/' . $currentSettings['company_logo'];
                    if (file_exists($logoPath)) {
                        unlink($logoPath);
                    }
                }

                // Limpiar ruta en BD
                $this->model->updateLogo('');

                header('Location: ' . BASE_URL . '/modules/settings/settingsView.php?success=logo_removed&tab=appearance');
                exit;

            } catch (Exception $e) {
                error_log("Error removing logo: " . $e->getMessage());
                return ['error' => $e->getMessage()];
            }
        }
        
        return [];
    }

    // Probar conexión de correo
    public function testEmailConnection() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                http_response_code(403);
                echo json_encode(['error' => 'Token CSRF inválido']);
                return;
            }

            header('Content-Type: application/json');

            try {
                // Usar configuración actual o datos del formulario
                $config = null;
                if (isset($_POST['test_current'])) {
                    $config = null; // Usar configuración actual
                } else {
                    $config = [
                        'host' => $_POST['smtp_host'] ?? '',
                        'port' => $_POST['smtp_port'] ?? 587,
                        'username' => $_POST['smtp_username'] ?? '',
                        'password' => $_POST['smtp_password'] ?? '',
                        'security' => $_POST['smtp_security'] ?? 'tls',
                        'from_email' => $_POST['smtp_from_email'] ?? '',
                        'from_name' => $_POST['smtp_from_name'] ?? ''
                    ];
                }

                $result = $this->model->testEmailConnection($config);
                echo json_encode($result);

            } catch (Exception $e) {
                error_log("Error testing email connection: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Error al probar conexión: ' . $e->getMessage()
                ]);
            }
            return;
        }
    }

    // Exportar configuración
    public function exportSettings() {
        $this->checkAdminAuth();
        
        try {
            $result = $this->model->exportSettings();
            
            if ($result['success']) {
                // Generar archivo JSON para descarga
                $filename = 'configuracion_crm_' . date('Y-m-d_H-i-s') . '.json';
                
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . strlen(json_encode($result['data'])));
                
                echo json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                exit;
            } else {
                return ['error' => $result['message']];
            }

        } catch (Exception $e) {
            error_log("Error exporting settings: " . $e->getMessage());
            return ['error' => 'Error al exportar configuración.'];
        }
    }

    // Importar configuración
    public function importSettings() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            if (!isset($_FILES['config_file']) || $_FILES['config_file']['error'] !== UPLOAD_ERR_OK) {
                return ['error' => 'Error al subir el archivo de configuración.'];
            }

            try {
                // Leer archivo JSON
                $fileContent = file_get_contents($_FILES['config_file']['tmp_name']);
                $configData = json_decode($fileContent, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    return ['error' => 'Archivo de configuración no válido (JSON malformado).'];
                }

                // Importar configuración
                $result = $this->model->importSettings($configData);
                
                if ($result['success']) {
                    header('Location: ' . BASE_URL . '/modules/settings/settingsView.php?success=config_imported');
                    exit;
                } else {
                    return ['error' => $result['message']];
                }

            } catch (Exception $e) {
                error_log("Error importing settings: " . $e->getMessage());
                return ['error' => 'Error al importar configuración: ' . $e->getMessage()];
            }
        }
        
        return [];
    }

    // Obtener zonas horarias para formularios
    public function getTimezones() {
        try {
            return $this->model->getTimezones();
        } catch (Exception $e) {
            error_log("Error getting timezones: " . $e->getMessage());
            return [];
        }
    }

    // Obtener monedas para formularios
    public function getCurrencies() {
        try {
            return $this->model->getCurrencies();
        } catch (Exception $e) {
            error_log("Error getting currencies: " . $e->getMessage());
            return [];
        }
    }

    // Resetear configuración a valores por defecto - ACTUALIZADO CON SLOGAN
    public function resetToDefaults() {
        $this->checkAdminAuth();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Validar token CSRF
            if (!$this->session->validateCsrfToken($_POST['csrf_token'] ?? '')) {
                return ['error' => 'Error de seguridad: Token CSRF inválido.'];
            }

            try {
                // Resetear configuraciones por secciones
                $defaultData = [
                    'company_name' => 'Mi Empresa CRM',
                    'company_slogan' => 'Tu socio confiable en crecimiento empresarial', // ← AGREGADO
                    'company_address' => '',
                    'company_phone' => '',
                    'company_email' => '',
                    'company_website' => '',
                    'language' => 'es',
                    'timezone' => 'America/Mexico_City',
                    'currency_code' => 'USD',
                    'currency_symbol' => '$',
                    'date_format' => 'd/m/Y',
                    'tax_rate' => 16.00,
                    'tax_name' => 'IVA'
                ];

                $this->model->updateGeneral($defaultData);
                $this->model->updateRegional($defaultData);
                $this->model->updateTax($defaultData);
                $this->model->updateTheme('light');

                header('Location: ' . BASE_URL . '/modules/settings/settingsView.php?success=reset_completed');
                exit;

            } catch (Exception $e) {
                error_log("Error resetting settings: " . $e->getMessage());
                return ['error' => 'Error al resetear configuración: ' . $e->getMessage()];
            }
        }
        
        return [];
    }

    // Obtener información del sistema para diagnóstico
    public function getSystemInfo() {
        $this->checkAdminAuth();
        
        try {
            return [
                'php_version' => PHP_VERSION,
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido',
                'max_upload_size' => ini_get('upload_max_filesize'),
                'max_execution_time' => ini_get('max_execution_time'),
                'memory_limit' => ini_get('memory_limit'),
                'mysql_version' => $this->getMysqlVersion(),
                'crm_version' => '1.0.0',
                'timezone_current' => date_default_timezone_get(),
                'disk_free_space' => $this->formatBytes(disk_free_space('.')),
                'extensions' => [
                    'pdo' => extension_loaded('pdo'),
                    'pdo_mysql' => extension_loaded('pdo_mysql'),
                    'gd' => extension_loaded('gd'),
                    'curl' => extension_loaded('curl'),
                    'openssl' => extension_loaded('openssl'),
                    'json' => extension_loaded('json')
                ]
            ];
        } catch (Exception $e) {
            error_log("Error getting system info: " . $e->getMessage());
            return [];
        }
    }

    // Obtener versión de MySQL - MÉTODO CORREGIDO
    private function getMysqlVersion() {
        try {
            // Intentar obtener la versión a través del modelo si tiene el método
            if (method_exists($this->model, 'getMysqlVersion')) {
                return $this->model->getMysqlVersion();
            }
            
            // Cargar configuración de base de datos primero
            $configPath = dirname(__DIR__, 2) . '/config/database.php';
            if (file_exists($configPath)) {
                require_once $configPath;
            }
            
            // Verificar si las constantes están definidas después de cargar el archivo
            if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
                $pdo = new PDO(
                    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, 
                    DB_USER, 
                    DB_PASS,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $stmt = $pdo->query("SELECT VERSION() as version");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result['version'] ?? 'Desconocida';
            }
            
            return 'No disponible - Config no encontrada';
            
        } catch (Exception $e) {
            error_log("Error getting MySQL version: " . $e->getMessage());
            return 'No disponible';
        }
    }

    // Formatear bytes para mostrar tamaño
    private function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
?>