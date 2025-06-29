<?php
// Modelo actualizado con debug para detectar el problema del slogan
// Reemplaza temporalmente tu settingsModel.php con esta versión

require_once dirname(__DIR__, 2) . '/core/db.php';
require_once dirname(__DIR__, 2) . '/core/security.php';
require_once dirname(__DIR__, 2) . '/config/constants.php';

class SettingsModel {
    private $db;

    public function __construct() {
        try {
            $this->db = new Database();
            $this->initializeSettingsTable();
        } catch (Exception $e) {
            error_log("Error initializing SettingsModel: " . $e->getMessage());
            throw new Exception('No se pudo conectar a la base de datos.');
        }
    }

    // Crear tabla de configuraciones si no existe
    private function initializeSettingsTable() {
        try {
            $query = "CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                company_name VARCHAR(255) NOT NULL DEFAULT 'Mi Empresa CRM',
                company_slogan TEXT,
                company_address TEXT,
                company_phone VARCHAR(50),
                company_email VARCHAR(255),
                company_website VARCHAR(255),
                company_logo VARCHAR(255),
                
                language VARCHAR(5) NOT NULL DEFAULT 'es',
                timezone VARCHAR(100) NOT NULL DEFAULT 'America/Mexico_City',
                currency_code VARCHAR(5) NOT NULL DEFAULT 'USD',
                currency_symbol VARCHAR(10) NOT NULL DEFAULT '$',
                
                tax_rate DECIMAL(5,2) NOT NULL DEFAULT 16.00,
                tax_name VARCHAR(50) NOT NULL DEFAULT 'IVA',
                
                theme VARCHAR(20) NOT NULL DEFAULT 'light',
                date_format VARCHAR(20) NOT NULL DEFAULT 'd/m/Y',
                
                smtp_host VARCHAR(255),
                smtp_port INT DEFAULT 587,
                smtp_username VARCHAR(255),
                smtp_password VARCHAR(255),
                smtp_security VARCHAR(10) DEFAULT 'tls',
                smtp_from_email VARCHAR(255),
                smtp_from_name VARCHAR(255),
                
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
                
                INDEX idx_language (language),
                INDEX idx_timezone (timezone)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $this->db->execute($query);
            
            // Verificar si existe la columna company_slogan, si no, agregarla
            $this->addSloganColumnIfNotExists();
            
            // Insertar configuración por defecto si no existe
            $this->createDefaultSettings();
            
        } catch (Exception $e) {
            error_log("Error creating settings table: " . $e->getMessage());
            throw new Exception('Error al inicializar tabla de configuraciones.');
        }
    }

    // Agregar columna de slogan si no existe
    private function addSloganColumnIfNotExists() {
        try {
            // Verificar si la columna existe
            $checkQuery = "SHOW COLUMNS FROM settings LIKE 'company_slogan'";
            $result = $this->db->select($checkQuery);
            
            // Si no existe, agregarla
            if (empty($result)) {
                $alterQuery = "ALTER TABLE settings ADD COLUMN company_slogan TEXT NULL AFTER company_name";
                $this->db->execute($alterQuery);
                error_log("Added company_slogan column to settings table");
            }
        } catch (Exception $e) {
            error_log("Error checking/adding company_slogan column: " . $e->getMessage());
        }
    }

    // Crear configuración por defecto
    private function createDefaultSettings() {
        try {
            $checkQuery = "SELECT COUNT(*) as count FROM settings";
            $result = $this->db->select($checkQuery);
            
            if ($result[0]['count'] == 0) {
                $query = "INSERT INTO settings (
                    company_name, company_slogan, language, timezone, currency_code, currency_symbol,
                    tax_rate, tax_name, theme, date_format
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $params = [
                    'Mi Empresa CRM',
                    'Tu socio confiable en crecimiento empresarial',
                    'es',
                    'America/Mexico_City', 
                    'USD',
                    '$',
                    16.00,
                    'IVA',
                    'light',
                    'd/m/Y'
                ];
                
                $this->db->insert($query, $params);
            }
        } catch (Exception $e) {
            error_log("Error creating default settings: " . $e->getMessage());
        }
    }

    // Obtener todas las configuraciones
    public function getAll() {
        try {
            $query = "SELECT * FROM settings WHERE id = 1";
            $result = $this->db->select($query);
            return $result ? $result[0] : $this->getDefaultSettings();
        } catch (Exception $e) {
            error_log("Error fetching settings: " . $e->getMessage());
            return $this->getDefaultSettings();
        }
    }

    // Obtener configuración específica
    public function get($key) {
        try {
            $settings = $this->getAll();
            return $settings[$key] ?? null;
        } catch (Exception $e) {
            error_log("Error getting setting: " . $e->getMessage());
            return null;
        }
    }

    // Actualizar configuración general - VERSION CON DEBUG DETALLADO
    public function updateGeneral($data) {
        // DEBUG: Escribir a un archivo de log lo que llega
        $debugFile = dirname(__DIR__, 2) . '/settings_debug.log';
        
        $debugData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'received_data' => $data,
            'post_data' => $_POST
        ];
        
        file_put_contents($debugFile, "=== UPDATEGENERAL DEBUG ===\n" . print_r($debugData, true) . "\n\n", FILE_APPEND);
        
        // Validar datos
        $companyName = Security::sanitize($data['company_name'] ?? '', 'string');
        $companySlogan = Security::sanitize($data['company_slogan'] ?? '', 'string');
        $companyAddress = Security::sanitize($data['company_address'] ?? '', 'string');
        $companyPhone = Security::sanitize($data['company_phone'] ?? '', 'string');
        $companyEmail = Security::sanitize($data['company_email'] ?? '', 'email');
        $companyWebsite = Security::sanitize($data['company_website'] ?? '', 'string');

        // DEBUG: Log después de sanitizar
        $sanitizedData = [
            'company_name' => $companyName,
            'company_slogan' => $companySlogan,
            'company_address' => $companyAddress,
            'company_phone' => $companyPhone,
            'company_email' => $companyEmail,
            'company_website' => $companyWebsite
        ];
        
        file_put_contents($debugFile, "SANITIZED DATA:\n" . print_r($sanitizedData, true) . "\n\n", FILE_APPEND);

        // Validaciones específicas
        if (strlen($companyName) < 2 || strlen($companyName) > 255) {
            throw new Exception('El nombre de la empresa debe tener entre 2 y 255 caracteres.');
        }

        if ($companySlogan && strlen($companySlogan) > 500) {
            throw new Exception('El slogan no puede exceder 500 caracteres.');
        }

        if ($companyEmail && !filter_var($companyEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Correo electrónico de la empresa no válido.');
        }

        if ($companyWebsite && !empty($companyWebsite)) {
            if (!preg_match('/^https?:\/\/.+/', $companyWebsite)) {
                $companyWebsite = 'https://' . $companyWebsite;
            }
            if (!filter_var($companyWebsite, FILTER_VALIDATE_URL)) {
                throw new Exception('URL del sitio web no válida.');
            }
        }

        try {
            // Verificar primero si la columna company_slogan existe
            $checkColumn = "SHOW COLUMNS FROM settings LIKE 'company_slogan'";
            $columnExists = $this->db->select($checkColumn);
            
            if (empty($columnExists)) {
                // Si no existe la columna, agregarla
                $addColumn = "ALTER TABLE settings ADD COLUMN company_slogan TEXT NULL AFTER company_name";
                $this->db->execute($addColumn);
                file_put_contents($debugFile, "COLUMN ADDED AUTOMATICALLY\n\n", FILE_APPEND);
            }
            
            $query = "UPDATE settings SET 
                      company_name = ?, 
                      company_slogan = ?, 
                      company_address = ?, 
                      company_phone = ?, 
                      company_email = ?, 
                      company_website = ?, 
                      updated_at = NOW() 
                      WHERE id = 1";
            
            $params = [$companyName, $companySlogan, $companyAddress, $companyPhone, $companyEmail, $companyWebsite];
            
            // DEBUG: Log de la consulta y parámetros
            file_put_contents($debugFile, "QUERY: $query\n", FILE_APPEND);
            file_put_contents($debugFile, "PARAMS: " . print_r($params, true) . "\n", FILE_APPEND);
            
            $result = $this->db->execute($query, $params);
            
            // DEBUG: Verificar que se guardó
            $verification = $this->db->select("SELECT company_name, company_slogan FROM settings WHERE id = 1");
            file_put_contents($debugFile, "VERIFICATION RESULT: " . print_r($verification, true) . "\n", FILE_APPEND);
            file_put_contents($debugFile, "=====================================\n\n", FILE_APPEND);
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Error updating general settings: " . $e->getMessage());
            file_put_contents($debugFile, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($debugFile, "TRACE: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            throw new Exception('Error al actualizar configuración general: ' . $e->getMessage());
        }
    }

    // Resto de métodos sin cambios...
    public function updateRegional($data) {
        $language = Security::sanitize($data['language'] ?? 'es', 'string');
        $timezone = Security::sanitize($data['timezone'] ?? 'America/Mexico_City', 'string');
        $currencyCode = Security::sanitize($data['currency_code'] ?? 'USD', 'string');
        $currencySymbol = Security::sanitize($data['currency_symbol'] ?? '$', 'string');
        $dateFormat = Security::sanitize($data['date_format'] ?? 'd/m/Y', 'string');

        if (!in_array($language, ['es', 'en'])) {
            throw new Exception('Idioma no válido.');
        }

        if (!in_array($timezone, timezone_identifiers_list())) {
            throw new Exception('Zona horaria no válida.');
        }

        if (strlen($currencyCode) !== 3) {
            throw new Exception('Código de moneda debe tener 3 caracteres.');
        }

        try {
            $query = "UPDATE settings SET 
                      language = ?, timezone = ?, currency_code = ?, 
                      currency_symbol = ?, date_format = ?, updated_at = NOW() 
                      WHERE id = 1";
            
            $params = [$language, $timezone, $currencyCode, $currencySymbol, $dateFormat];
            
            return $this->db->execute($query, $params);
        } catch (Exception $e) {
            error_log("Error updating regional settings: " . $e->getMessage());
            throw new Exception('Error al actualizar configuración regional.');
        }
    }

    public function updateTax($data) {
        $taxRate = (float)($data['tax_rate'] ?? 0);
        $taxName = Security::sanitize($data['tax_name'] ?? 'IVA', 'string');

        if ($taxRate < 0 || $taxRate > 100) {
            throw new Exception('La tasa de impuesto debe estar entre 0% y 100%.');
        }

        if (strlen($taxName) < 2 || strlen($taxName) > 50) {
            throw new Exception('El nombre del impuesto debe tener entre 2 y 50 caracteres.');
        }

        try {
            $query = "UPDATE settings SET tax_rate = ?, tax_name = ?, updated_at = NOW() WHERE id = 1";
            return $this->db->execute($query, [$taxRate, $taxName]);
        } catch (Exception $e) {
            error_log("Error updating tax settings: " . $e->getMessage());
            throw new Exception('Error al actualizar configuración de impuestos.');
        }
    }

    public function updateEmail($data) {
        $smtpHost = Security::sanitize($data['smtp_host'] ?? '', 'string');
        $smtpPort = (int)($data['smtp_port'] ?? 587);
        $smtpUsername = Security::sanitize($data['smtp_username'] ?? '', 'string');
        $smtpPassword = $data['smtp_password'] ?? '';
        $smtpSecurity = Security::sanitize($data['smtp_security'] ?? 'tls', 'string');
        $smtpFromEmail = Security::sanitize($data['smtp_from_email'] ?? '', 'email');
        $smtpFromName = Security::sanitize($data['smtp_from_name'] ?? '', 'string');

        if ($smtpFromEmail && !filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Email remitente no válido.');
        }

        if (!in_array($smtpSecurity, ['none', 'ssl', 'tls'])) {
            throw new Exception('Tipo de seguridad SMTP no válido.');
        }

        if ($smtpPort < 1 || $smtpPort > 65535) {
            throw new Exception('Puerto SMTP no válido.');
        }

        try {
            $query = "UPDATE settings SET 
                      smtp_host = ?, smtp_port = ?, smtp_username = ?, smtp_password = ?,
                      smtp_security = ?, smtp_from_email = ?, smtp_from_name = ?, updated_at = NOW()
                      WHERE id = 1";
            
            $params = [
                $smtpHost, $smtpPort, $smtpUsername, $smtpPassword,
                $smtpSecurity, $smtpFromEmail, $smtpFromName
            ];
            
            return $this->db->execute($query, $params);
        } catch (Exception $e) {
            error_log("Error updating email settings: " . $e->getMessage());
            throw new Exception('Error al actualizar configuración de correo.');
        }
    }

    public function updateTheme($theme) {
        $theme = Security::sanitize($theme, 'string');
        
        if (!in_array($theme, ['light', 'dark'])) {
            throw new Exception('Tema no válido.');
        }

        try {
            $query = "UPDATE settings SET theme = ?, updated_at = NOW() WHERE id = 1";
            return $this->db->execute($query, [$theme]);
        } catch (Exception $e) {
            error_log("Error updating theme: " . $e->getMessage());
            throw new Exception('Error al actualizar tema.');
        }
    }

    public function updateLogo($logoPath) {
        $logoPath = Security::sanitize($logoPath, 'string');

        try {
            $query = "UPDATE settings SET company_logo = ?, updated_at = NOW() WHERE id = 1";
            return $this->db->execute($query, [$logoPath]);
        } catch (Exception $e) {
            error_log("Error updating logo: " . $e->getMessage());
            throw new Exception('Error al actualizar logo.');
        }
    }

    // Obtener configuraciones por defecto - ACTUALIZADA CON SLOGAN
    private function getDefaultSettings() {
        return [
            'id' => 1,
            'company_name' => 'Mi Empresa CRM',
            'company_slogan' => 'Tu socio confiable en crecimiento empresarial',
            'company_address' => '',
            'company_phone' => '',
            'company_email' => '',
            'company_website' => '',
            'company_logo' => '',
            'language' => 'es',
            'timezone' => 'America/Mexico_City',
            'currency_code' => 'USD',
            'currency_symbol' => '$',
            'tax_rate' => 16.00,
            'tax_name' => 'IVA',
            'theme' => 'light',
            'date_format' => 'd/m/Y',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_security' => 'tls',
            'smtp_from_email' => '',
            'smtp_from_name' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => null
        ];
    }

    // Métodos adicionales sin cambios...
    public function getTimezones() {
        $timezones = [];
        $regions = [
            'America' => DateTimeZone::AMERICA,
            'Europe' => DateTimeZone::EUROPE,
            'Asia' => DateTimeZone::ASIA,
            'Pacific' => DateTimeZone::PACIFIC,
            'Atlantic' => DateTimeZone::ATLANTIC,
            'Indian' => DateTimeZone::INDIAN,
            'Antarctica' => DateTimeZone::ANTARCTICA,
            'Arctic' => DateTimeZone::ARCTIC
        ];

        foreach ($regions as $name => $region) {
            $timezones[$name] = DateTimeZone::listIdentifiers($region);
        }

        return $timezones;
    }

    public function getCurrencies() {
        return [
            'USD' => ['name' => 'Dólar Estadounidense', 'symbol' => '$'],
            'EUR' => ['name' => 'Euro', 'symbol' => '€'],
            'GBP' => ['name' => 'Libra Esterlina', 'symbol' => '£'],
            'JPY' => ['name' => 'Yen Japonés', 'symbol' => '¥'],
            'CAD' => ['name' => 'Dólar Canadiense', 'symbol' => 'C$'],
            'AUD' => ['name' => 'Dólar Australiano', 'symbol' => 'A$'],
            'CHF' => ['name' => 'Franco Suizo', 'symbol' => 'CHF'],
            'CNY' => ['name' => 'Yuan Chino', 'symbol' => '¥'],
            'MXN' => ['name' => 'Peso Mexicano', 'symbol' => '$'],
            'BRL' => ['name' => 'Real Brasileño', 'symbol' => 'R$'],
            'ARS' => ['name' => 'Peso Argentino', 'symbol' => '$'],
            'COP' => ['name' => 'Peso Colombiano', 'symbol' => '$'],
            'CLP' => ['name' => 'Peso Chileno', 'symbol' => '$'],
            'PEN' => ['name' => 'Sol Peruano', 'symbol' => 'S/']
        ];
    }

    public function testEmailConnection($config = null) {
        try {
            if (!$config) {
                $settings = $this->getAll();
                $config = [
                    'host' => $settings['smtp_host'],
                    'port' => $settings['smtp_port'],
                    'username' => $settings['smtp_username'],
                    'password' => $settings['smtp_password'],
                    'security' => $settings['smtp_security'],
                    'from_email' => $settings['smtp_from_email'],
                    'from_name' => $settings['smtp_from_name']
                ];
            }

            if (empty($config['host']) || empty($config['from_email'])) {
                throw new Exception('Configuración de correo incompleta.');
            }

            return [
                'success' => true,
                'message' => 'Configuración de correo válida.'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function exportSettings() {
        try {
            $settings = $this->getAll();
            unset($settings['smtp_password']);
            
            return [
                'success' => true,
                'data' => $settings,
                'exported_at' => date('Y-m-d H:i:s')
            ];
        } catch (Exception $e) {
            error_log("Error exporting settings: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error al exportar configuración.'
            ];
        }
    }

    public function importSettings($data) {
        try {
            $requiredFields = ['company_name', 'language', 'timezone', 'currency_code'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Campo requerido faltante: $field");
                }
            }

            $this->updateGeneral($data);
            $this->updateRegional($data);
            $this->updateTax($data);

            return [
                'success' => true,
                'message' => 'Configuración importada correctamente.'
            ];

        } catch (Exception $e) {
            error_log("Error importing settings: " . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
?>