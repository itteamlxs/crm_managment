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

// Configuraciones por defecto
define('DEFAULT_LANGUAGE', 'es'); // Idioma por defecto: español
define('DEFAULT_CURRENCY', 'USD'); // Moneda por defecto
define('DEFAULT_TIMEZONE', 'America/Mexico_City'); // Zona horaria por defecto
define('SESSION_TIMEOUT', 1800); // Tiempo de inactividad en segundos (30 minutos)

// Límites de entrada
define('MAX_NAME_LENGTH', 100); // Longitud máxima para nombres
define('MAX_EMAIL_LENGTH', 255); // Longitud máxima para correos
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // Tamaño máximo de archivo (2MB, para logo)

// Estados genéricos
define('STATUS_ACTIVE', 1); // Estado activo
define('STATUS_INACTIVE', 0); // Estado inactivo
?>