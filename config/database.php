<?php
// Configuración de la conexión a la base de datos MySQL usando PDO
// Carga credenciales desde .env para configuración inicial y desde la tabla settings para configuraciones dinámicas

// Función para parsear archivo .env sin dependencias
function parseEnv($file) {
    $config = [];
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // Ignorar comentarios y líneas vacías
            if (strpos(trim($line), '#') === 0) continue;
            if (preg_match('/^([\w]+)=(.*)$/', $line, $matches)) {
                $config[$matches[1]] = $matches[2];
            }
        }
    }
    return $config;
}

// Cargar credenciales desde .env
$envFile = dirname(__DIR__) . '/.env';
$defaultDbConfig = parseEnv($envFile) ?: [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'crm_db',
    'DB_USER' => 'root',
    'DB_PASS' => '',
    'DB_CHARSET' => 'utf8mb4'
];

// Función para obtener credenciales desde la base de datos
function loadDbCredentials() {
    global $defaultDbConfig;
    try {
        // Intentar conexión inicial con credenciales de .env
        $dsn = "mysql:host={$defaultDbConfig['DB_HOST']};charset={$defaultDbConfig['DB_CHARSET']}";
        $pdo = new PDO($dsn, $defaultDbConfig['DB_USER'], $defaultDbConfig['DB_PASS'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]);

        // Crear base de datos si no existe
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$defaultDbConfig['DB_NAME']} CHARACTER SET {$defaultDbConfig['DB_CHARSET']} COLLATE {$defaultDbConfig['DB_CHARSET']}_unicode_ci");
        $pdo->exec("USE {$defaultDbConfig['DB_NAME']}");

        // Verificar si la tabla settings existe
        $result = $pdo->query("SHOW TABLES LIKE 'settings'");
        if ($result->rowCount() > 0) {
            $stmt = $pdo->query("SELECT db_host, db_name, db_user, db_pass, db_charset FROM settings WHERE id = 1");
            $config = $stmt->fetch();
            if ($config) {
                return [
                    'host' => $config['db_host'] ?? $defaultDbConfig['DB_HOST'],
                    'name' => $config['db_name'] ?? $defaultDbConfig['DB_NAME'],
                    'user' => $config['db_user'] ?? $defaultDbConfig['DB_USER'],
                    'pass' => $config['db_pass'] ?? $defaultDbConfig['DB_PASS'],
                    'charset' => $config['db_charset'] ?? $defaultDbConfig['DB_CHARSET']
                ];
            }
        }
    } catch (PDOException $e) {
        error_log("Failed to load DB credentials from settings: " . $e->getMessage());
    }
    // Fallback a credenciales de .env
    return [
        'host' => $defaultDbConfig['DB_HOST'],
        'name' => $defaultDbConfig['DB_NAME'],
        'user' => $defaultDbConfig['DB_USER'],
        'pass' => $defaultDbConfig['DB_PASS'],
        'charset' => $defaultDbConfig['DB_CHARSET']
    ];
}

// Obtener credenciales dinámicas
$dbConfig = loadDbCredentials();

// Configuración de opciones PDO
$dsn = "mysql:host={$dbConfig['host']};dbname={$dbConfig['name']};charset={$dbConfig['charset']}";
$pdoOptions = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false
];

// Función para obtener la conexión PDO
function getDatabaseConnection() {
    global $dsn, $pdoOptions, $dbConfig;
    try {
        return new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], $pdoOptions);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("Error de conexión a la base de datos. Por favor, configure las credenciales desde el panel de administración.");
    }
}
?>