<?php
// Constantes globales del sistema CRM

// Rutas base
define('BASE_PATH', dirname(__DIR__)); // Ruta raíz del proyecto
define('CONFIG_PATH', BASE_PATH . '/config'); // Ruta a la carpeta config
define('CORE_PATH', BASE_PATH . '/core'); // Ruta a la carpeta core
define('MODULES_PATH', BASE_PATH . '/modules'); // Ruta a la carpeta modules
define('ASSETS_PATH', BASE_PATH . '/assets'); // Ruta a la carpeta assets

// URL base (ajustar según el entorno)
define('BASE_URL', 'http://localhost/crm'); // Cambiar en producción

// Roles de usuario
define('ROLE_ADMIN', 1); // Administrador
define('ROLE_SELLER', 2); // Vendedor

// Estados genéricos
define('STATUS_ACTIVE', 1); // Estado activo
define('STATUS_INACTIVE', 0); // Estado inactivo

// Estados de cotización
define('QUOTE_STATUS_DRAFT', 1);     // Borrador
define('QUOTE_STATUS_SENT', 2);      // Enviada
define('QUOTE_STATUS_APPROVED', 3);  // Aprobada
define('QUOTE_STATUS_REJECTED', 4);  // Rechazada
define('QUOTE_STATUS_EXPIRED', 5);   // Vencida
define('QUOTE_STATUS_CANCELLED', 6); // Cancelada

// Configuraciones por defecto
define('DEFAULT_LANGUAGE', 'es'); // Idioma por defecto: español
define('DEFAULT_CURRENCY', 'USD'); // Moneda por defecto
define('DEFAULT_CURRENCY_SYMBOL', '$'); // Símbolo de moneda por defecto
define('DEFAULT_TIMEZONE', 'America/Mexico_City'); // Zona horaria por defecto
define('DEFAULT_TAX_RATE', 16.00); // Tasa de impuesto por defecto (16%)
define('DEFAULT_TAX_NAME', 'IVA'); // Nombre del impuesto por defecto
define('DEFAULT_DATE_FORMAT', 'd/m/Y'); // Formato de fecha por defecto
define('DEFAULT_THEME', 'light'); // Tema por defecto

// Configuraciones de sesión
define('SESSION_TIMEOUT', 1800); // Tiempo de inactividad en segundos (30 minutos)
define('SESSION_REGENERATE_INTERVAL', 300); // Regenerar ID cada 5 minutos

// Límites de entrada
define('MAX_NAME_LENGTH', 100); // Longitud máxima para nombres
define('MAX_EMAIL_LENGTH', 255); // Longitud máxima para correos
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // Tamaño máximo de archivo (2MB)
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // Tamaño máximo de subida general (5MB)

// Configuraciones de archivos
define('ALLOWED_IMAGE_TYPES', ['image/png', 'image/jpeg', 'image/jpg', 'image/gif']);
define('ALLOWED_DOCUMENT_TYPES', ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Configuraciones de correo por defecto
define('DEFAULT_SMTP_PORT', 587);
define('DEFAULT_SMTP_SECURITY', 'tls');

// Configuraciones de paginación
define('DEFAULT_PAGE_SIZE', 20); // Registros por página por defecto
define('MAX_PAGE_SIZE', 100); // Máximo registros por página

// Configuraciones de validación
define('MIN_PASSWORD_LENGTH', 8); // Longitud mínima de contraseña
define('MAX_LOGIN_ATTEMPTS', 5); // Intentos de login antes de bloquear
define('LOCKOUT_DURATION', 900); // Duración del bloqueo en segundos (15 min)

// Configuraciones de stock
define('LOW_STOCK_THRESHOLD', 10); // Umbral de stock bajo
define('STOCK_WARNING_THRESHOLD', 5); // Umbral de advertencia de stock

// Configuraciones del sistema
define('SYSTEM_VERSION', '1.0.0'); // Versión del sistema CRM
define('SYSTEM_NAME', 'Sistema CRM'); // Nombre del sistema
define('SYSTEM_DESCRIPTION', 'Sistema de Gestión de Relaciones con Clientes'); // Descripción

// Configuraciones de cache
define('CACHE_ENABLED', false); // Cache habilitado/deshabilitado
define('CACHE_DURATION', 3600); // Duración del cache en segundos (1 hora)

// Configuraciones de logs
define('LOG_ENABLED', true); // Logs habilitados
define('LOG_LEVEL', 'ERROR'); // Nivel de log: DEBUG, INFO, WARNING, ERROR
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // Tamaño máximo de archivo de log (10MB)

// Configuraciones de backup
define('BACKUP_ENABLED', true); // Backups automáticos habilitados
define('BACKUP_FREQUENCY', 'daily'); // Frecuencia: daily, weekly, monthly
define('BACKUP_RETENTION', 30); // Días de retención de backups

// Configuraciones de exportación
define('EXPORT_MAX_RECORDS', 10000); // Máximo registros por exportación
define('EXPORT_FORMATS', ['csv', 'pdf', 'excel']); // Formatos de exportación soportados

// Configuraciones de notificaciones
define('NOTIFICATIONS_ENABLED', true); // Notificaciones habilitadas
define('EMAIL_NOTIFICATIONS_ENABLED', false); // Notificaciones por email
define('SYSTEM_NOTIFICATIONS_ENABLED', true); // Notificaciones del sistema

// Configuraciones de seguridad avanzada
define('CSRF_TOKEN_LIFETIME', 3600); // Duración del token CSRF (1 hora)
define('PASSWORD_HASH_ALGORITHM', PASSWORD_DEFAULT); // Algoritmo de hash para contraseñas
define('ENCRYPTION_METHOD', 'AES-256-CBC'); // Método de cifrado

// Configuraciones de API (para futuras expansiones)
define('API_ENABLED', false); // API REST habilitada
define('API_VERSION', 'v1'); // Versión de la API
define('API_RATE_LIMIT', 100); // Límite de peticiones por hora

// Configuraciones de mantenimiento
define('MAINTENANCE_MODE', false); // Modo mantenimiento
define('MAINTENANCE_MESSAGE', 'Sistema en mantenimiento. Intente más tarde.'); // Mensaje de mantenimiento

// Configuraciones de módulos
define('MODULES_ENABLED', [
    'users' => true,
    'clients' => true,
    'products' => true,
    'quotes' => true,
    'sales' => false, // Por implementar
    'reports' => false, // Por implementar
    'settings' => true,
    'dashboard' => true
]);

// Configuraciones de fecha y hora
define('DATE_FORMAT_OPTIONS', [
    'd/m/Y' => 'DD/MM/YYYY',
    'm/d/Y' => 'MM/DD/YYYY',
    'Y-m-d' => 'YYYY-MM-DD',
    'd-m-Y' => 'DD-MM-YYYY'
]);

// Configuraciones de moneda
define('CURRENCY_OPTIONS', [
    'USD' => ['name' => 'Dólar Estadounidense', 'symbol' => '$'],
    'EUR' => ['name' => 'Euro', 'symbol' => '€'],
    'GBP' => ['name' => 'Libra Esterlina', 'symbol' => '£'],
    'MXN' => ['name' => 'Peso Mexicano', 'symbol' => '$'],
    'CAD' => ['name' => 'Dólar Canadiense', 'symbol' => 'C$'],
    'AUD' => ['name' => 'Dólar Australiano', 'symbol' => 'A$']
]);

// Configuraciones de idiomas soportados
define('SUPPORTED_LANGUAGES', [
    'es' => ['name' => 'Español', 'flag' => '🇪🇸'],
    'en' => ['name' => 'English', 'flag' => '🇺🇸']
]);

// Configuraciones de zona horaria
define('TIMEZONE_REGIONS', [
    'America' => 'América',
    'Europe' => 'Europa',
    'Asia' => 'Asia',
    'Pacific' => 'Pacífico',
    'Atlantic' => 'Atlántico',
    'Indian' => 'Índico',
    'Antarctica' => 'Antártida',
    'Arctic' => 'Ártico'
]);

// Configuraciones de temas
define('AVAILABLE_THEMES', [
    'light' => ['name' => 'Claro', 'icon' => '☀️'],
    'dark' => ['name' => 'Oscuro', 'icon' => '🌙']
]);

// Mensajes del sistema
define('SYSTEM_MESSAGES', [
    'welcome' => 'Bienvenido al Sistema CRM',
    'goodbye' => 'Gracias por usar el Sistema CRM',
    'maintenance' => 'El sistema está en mantenimiento',
    'error_generic' => 'Ha ocurrido un error. Intente nuevamente.',
    'success_generic' => 'Operación completada exitosamente.',
    'unauthorized' => 'No tiene permisos para realizar esta acción.',
    'session_expired' => 'Su sesión ha expirado. Inicie sesión nuevamente.'
]);

// Configuraciones de desarrollo
define('DEBUG_MODE', false); // Modo debug (solo para desarrollo)
define('DISPLAY_ERRORS', false); // Mostrar errores en pantalla
define('LOG_QUERIES', false); // Registrar consultas SQL
define('PROFILING_ENABLED', false); // Perfilado de rendimiento

// Rutas de archivos importantes
define('LOG_PATH', BASE_PATH . '/logs');
define('BACKUP_PATH', BASE_PATH . '/backups');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('TEMP_PATH', BASE_PATH . '/temp');

// Crear directorios si no existen (solo en instalación)
if (!defined('INSTALLATION_COMPLETE')) {
    $directories = [LOG_PATH, BACKUP_PATH, UPLOAD_PATH, TEMP_PATH];
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }
}
?>