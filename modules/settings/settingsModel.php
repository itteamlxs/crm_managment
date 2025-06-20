<?php
// Modelo para gestionar configuraciones del sistema CRM
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
            
            // Insertar configuración por defecto si no existe
            $this->createDefaultSettings();
            
        } catch (Exception $e) {
            error_log("Error creating settings table: " . $e->getMessage());
            throw new Exception('Error al inicializar tabla de configuraciones.');
        }
    }

    // Crear configuración por defecto
    private function createDefaultSettings() {
        try {
            $checkQuery = "SELECT COUNT(*) as count FROM settings";
            $result = $this->db->select($checkQuery);
            
            if ($result[0]['count'] == 0) {
                $query = "INSERT INTO settings (
                    company_name, language, timezone, currency_code, currency_symbol,
                    tax_rate, tax_name, theme, date_format
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $params = [
                    'Mi Empresa CRM',
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

    // Actualizar configuración general
    public function updateGeneral($data) {
        // Validar datos
        $companyName = Security::sanitize($data['company_name'] ?? '', 'string');
        $companyAddress = Security::sanitize($data['company_address'] ?? '', 'string');
        $companyPhone = Security::sanitize($data['company_phone'] ?? '', 'string');
        $companyEmail = Security::sanitize($data['company_email'] ?? '', 'email');
        $companyWebsite = Security::sanitize($data['company_website'] ?? '', 'string');

        // Validaciones específicas
        if (strlen($companyName) < 2 || strlen($companyName) > 255) {
            throw new Exception('El nombre de la empresa debe tener entre 2 y 255 caracteres.');
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
            $query = "UPDATE settings SET 
                      company_name = ?, company_address = ?, company_phone = ?, 
                      company_email = ?, company_website = ?, updated_at = NOW() 
                      WHERE id = 1";
            
            $params = [$companyName, $companyAddress, $companyPhone, $companyEmail, $companyWebsite];
            
            return $this->db->execute($query, $params);
        } catch (Exception $e) {
            error_log("Error updating general settings: " . $e->getMessage());
            throw new Exception('Error al actualizar configuración general.');
        }
    }

    // Actualizar configuración regional
    public function updateRegional($data) {
        $language = Security::sanitize($data['language'] ?? 'es', 'string');
        $timezone = Security::sanitize($data['timezone'] ?? 'America/Mexico_City', 'string');
        $currencyCode = Security::sanitize($data['currency_code'] ?? 'USD', 'string');
        $currencySymbol = Security::sanitize($data['currency_symbol'] ?? '$', 'string');
        $dateFormat = Security::sanitize($data['date_format'] ?? 'd/m/Y', 'string');

        // Validaciones
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

    // Actualizar configuración de impuestos
    public function updateTax($data) {
        $taxRate = (float)($data['tax_rate'] ?? 0);
        $taxName = Security::sanitize($data['tax_name'] ?? 'IVA', 'string');

        // Validaciones
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

    // Actualizar configuración de correo
    public function updateEmail($data) {
        $smtpHost = Security::sanitize($data['smtp_host'] ?? '', 'string');
        $smtpPort = (int)($data['smtp_port'] ?? 587);
        $smtpUsername = Security::sanitize($data['smtp_username'] ?? '', 'string');
        $smtpPassword = $data['smtp_password'] ?? ''; // No sanitizar passwords
        $smtpSecurity = Security::sanitize($data['smtp_security'] ?? 'tls', 'string');
        $smtpFromEmail = Security::sanitize($data['smtp_from_email'] ?? '', 'email');
        $smtpFromName = Security::sanitize($data['smtp_from_name'] ?? '', 'string');

        // Validaciones
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

    // Actualizar tema
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

    // Actualizar logo de la empresa
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

    // Obtener configuraciones por defecto
    private function getDefaultSettings() {
        return [
            'id' => 1,
            'company_name' => 'Mi Empresa CRM',
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

    // Obtener zonas horarias disponibles
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

    // Obtener monedas comunes
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

    // Probar conexión de correo
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

            // Validar configuración mínima
            if (empty($config['host']) || empty($config['from_email'])) {
                throw new Exception('Configuración de correo incompleta.');
            }

            // Aquí implementarías la prueba real de conexión SMTP
            // Por ahora, solo validamos que los datos estén completos
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

    // Exportar configuración completa
    public function exportSettings() {
        try {
            $settings = $this->getAll();
            
            // Remover datos sensibles para exportación
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

    // Importar configuración (sin contraseñas)
    public function importSettings($data) {
        try {
            // Validar estructura de datos
            $requiredFields = ['company_name', 'language', 'timezone', 'currency_code'];
            foreach ($requiredFields as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("Campo requerido faltante: $field");
                }
            }

            // Actualizar configuraciones por secciones
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