<?php
require_once CONFIG_PATH . '/constants.php';

class Utils {
    // Redirigir a una URL
    public static function redirect($url) {
        header('Location: ' . BASE_URL . '/' . ltrim($url, '/'));
        exit;
    }

    // Formatear fecha según zona horaria
    public static function formatDate($date, $format = 'Y-m-d H:i:s') {
        $timezone = new DateTimeZone(DEFAULT_TIMEZONE);
        $datetime = new DateTime($date, $timezone);
        return $datetime->format($format);
    }

    // Validar formato de correo
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) && strlen($email) <= MAX_EMAIL_LENGTH;
    }

    // Manejar subida de archivos (para logo)
    public static function uploadFile($file, $targetDir, $allowedTypes = ['image/png', 'image/jpeg'], $maxSize = MAX_FILE_SIZE) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Error al subir el archivo.'];
        }
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'El archivo es demasiado grande.'];
        }
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Tipo de archivo no permitido.'];
        }
        $targetPath = $targetDir . '/' . basename($file['name']);
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            return ['success' => true, 'path' => $targetPath];
        }
        return ['success' => false, 'message' => 'Error al mover el archivo.'];
    }

    // Generar CSV desde un array
    public static function generateCsv($data, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }
}
?>