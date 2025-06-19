<?php
// Cargar constants.php con ruta relativa directa
require_once dirname(__DIR__) . '/config/constants.php';

class Utils {
    // Redirigir a una URL
    public static function redirect($url) {
        // Limpiar cualquier salida previa
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Si la URL ya es completa, usarla tal como está
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            header('Location: ' . $url);
        } else {
            // Construir URL relativa
            $redirectUrl = BASE_URL . '/' . ltrim($url, '/');
            header('Location: ' . $redirectUrl);
        }
        exit;
    }

    // Formatear fecha según zona horaria
    public static function formatDate($date, $format = 'Y-m-d H:i:s') {
        try {
            $timezone = new DateTimeZone(DEFAULT_TIMEZONE);
            
            // Si $date es string, crear DateTime
            if (is_string($date)) {
                $datetime = new DateTime($date, $timezone);
            } elseif ($date instanceof DateTime) {
                $datetime = $date;
                $datetime->setTimezone($timezone);
            } else {
                return '';
            }
            
            return $datetime->format($format);
        } catch (Exception $e) {
            error_log("Error formatting date: " . $e->getMessage());
            return $date; // Retornar original si hay error
        }
    }

    // Formatear fecha para mostrar (más legible)
    public static function formatDateDisplay($date, $includeTime = true) {
        $format = $includeTime ? 'd/m/Y H:i' : 'd/m/Y';
        return self::formatDate($date, $format);
    }

    // Validar formato de correo
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= MAX_EMAIL_LENGTH;
    }

    // Manejar subida de archivos (para logo)
    public static function uploadFile($file, $targetDir, $allowedTypes = ['image/png', 'image/jpeg'], $maxSize = null) {
        $maxSize = $maxSize ?? MAX_FILE_SIZE;
        
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error al subir el archivo.'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'El archivo es demasiado grande.'];
        }
        
        // Verificar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return ['success' => false, 'message' => 'Tipo de archivo no permitido.'];
        }
        
        // Crear directorio si no existe
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }
        
        // Generar nombre único
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '.' . $extension;
        $targetPath = $targetDir . '/' . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => true, 'path' => $targetPath, 'filename' => $fileName];
        }
        
        return ['success' => false, 'message' => 'Error al mover el archivo.'];
    }

    // Generar CSV desde un array
    public static function generateCsv($data, $filename, $headers = []) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Escribir BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Escribir headers si se proporcionan
        if (!empty($headers)) {
            fputcsv($output, $headers);
        }
        
        // Escribir datos
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }

    // Sanitizar nombre de archivo
    public static function sanitizeFilename($filename) {
        // Remover caracteres peligrosos
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
        // Limitar longitud
        return substr($filename, 0, 100);
    }

    // Generar token aleatorio
    public static function generateToken($length = 32) {
        try {
            return bin2hex(random_bytes($length));
        } catch (Exception $e) {
            // Fallback si random_bytes falla
            return md5(uniqid(mt_rand(), true));
        }
    }

    // Convertir bytes a formato legible
    public static function formatBytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}
?>