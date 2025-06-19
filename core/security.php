<?php
// Cargar constants.php con ruta relativa directa (evitar dependencia circular)
require_once dirname(__DIR__) . '/config/constants.php';

class Security {
    // Configurar cabeceras HTTP de seguridad
    public static function setHeaders() {
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://cdn.jsdelivr.net; style-src \'self\' https://cdn.jsdelivr.net \'unsafe-inline\'; font-src \'self\' https://cdn.jsdelivr.net');
    }

    // Escapar salida para prevenir XSS
    public static function escape($data) {
        if (is_null($data)) {
            return '';
        }
        return htmlspecialchars((string)$data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Sanitizar entrada según tipo
    public static function sanitize($data, $type) {
        if (is_null($data)) {
            return null;
        }
        
        switch ($type) {
            case 'string':
                return filter_var(trim($data), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            case 'email':
                return filter_var(trim($data), FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            default:
                return null;
        }
    }

    // Validar entrada según tipo y longitud
    public static function validate($data, $type, $maxLength = null) {
        if (is_null($data)) {
            return false;
        }
        
        switch ($type) {
            case 'string':
                $isValid = is_string($data) && strlen(trim($data)) > 0;
                return $isValid && ($maxLength ? strlen($data) <= $maxLength : true);
            case 'email':
                $isValid = filter_var($data, FILTER_VALIDATE_EMAIL) !== false;
                return $isValid && ($maxLength ? strlen($data) <= $maxLength : true);
            case 'int':
                return filter_var($data, FILTER_VALIDATE_INT) !== false;
            case 'float':
                return filter_var($data, FILTER_VALIDATE_FLOAT) !== false;
            default:
                return false;
        }
    }

    // Validar formato de username (alfanumérico, guiones, puntos, @)
    public static function validateUsername($username) {
        if (!self::validate($username, 'string', MAX_NAME_LENGTH)) {
            return false;
        }
        // Permitir letras, números, @, puntos, guiones y guiones bajos
        return preg_match('/^[a-zA-Z0-9@._-]+$/', $username);
    }

    // Validar longitud mínima de contraseña
    public static function validatePassword($password) {
        return is_string($password) && strlen($password) >= 8;
    }
}
?>