<?php
require_once CONFIG_PATH . '/constants.php';

class Security {
    // Configurar cabeceras HTTP de seguridad
    public static function setHeaders() {
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('X-Content-Type-Options: nosniff');
        header('Content-Security-Policy: default-src \'self\'; script-src \'self\' https://cdn.jsdelivr.net; style-src \'self\' https://cdn.jsdelivr.net');
    }

    // Escapar salida para prevenir XSS
    public static function escape($data) {
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // Sanitizar entrada según tipo
    public static function sanitize($data, $type) {
        switch ($type) {
            case 'string':
                return filter_var($data, FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
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
        switch ($type) {
            case 'string':
                return is_string($data) && ($maxLength ? strlen($data) <= $maxLength : true);
            case 'email':
                return filter_var($data, FILTER_VALIDATE_EMAIL) && ($maxLength ? strlen($data) <= $maxLength : true);
            case 'int':
                return filter_var($data, FILTER_VALIDATE_INT) !== false;
            case 'float':
                return filter_var($data, FILTER_VALIDATE_FLOAT) !== false;
            default:
                return false;
        }
    }
}
?>