<?php
require_once CONFIG_PATH . '/database.php';

class Database {
    private $pdo;
    private $inTransaction = false;

    public function __construct() {
        $this->pdo = getDatabaseConnection();
    }

    // Ejecutar consulta SELECT y devolver resultados
    public function select($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Query failed: " . $e->getMessage());
            throw new Exception("Error en la consulta de datos.");
        }
    }

    // Ejecutar consulta INSERT y devolver el ID insertado
    public function insert($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Insert failed: " . $e->getMessage());
            throw new Exception("Error al insertar datos.");
        }
    }

    // Ejecutar consulta UPDATE o DELETE y devolver filas afectadas
    public function execute($query, $params = []) {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Execute failed: " . $e->getMessage());
            throw new Exception("Error al modificar datos.");
        }
    }

    // Iniciar transacción
    public function beginTransaction() {
        try {
            if (!$this->inTransaction) {
                $result = $this->pdo->beginTransaction();
                $this->inTransaction = $result;
                return $result;
            }
            return true; // Ya está en transacción
        } catch (PDOException $e) {
            error_log("Begin transaction failed: " . $e->getMessage());
            throw new Exception("Error al iniciar transacción.");
        }
    }

    // Confirmar transacción
    public function commit() {
        try {
            if ($this->inTransaction) {
                $result = $this->pdo->commit();
                $this->inTransaction = false;
                return $result;
            }
            return true; // No hay transacción activa
        } catch (PDOException $e) {
            error_log("Commit failed: " . $e->getMessage());
            throw new Exception("Error al confirmar transacción.");
        }
    }

    // Revertir transacción
    public function rollback() {
        try {
            if ($this->inTransaction) {
                $result = $this->pdo->rollBack();
                $this->inTransaction = false;
                return $result;
            }
            return true; // No hay transacción activa
        } catch (PDOException $e) {
            error_log("Rollback failed: " . $e->getMessage());
            throw new Exception("Error al revertir transacción.");
        }
    }

    // Verificar si está en transacción
    public function inTransaction() {
        return $this->inTransaction;
    }

    // Obtener la conexión PDO (para transacciones avanzadas)
    public function getConnection() {
        return $this->pdo;
    }

    // Ejecutar múltiples consultas en una transacción
    public function executeTransaction($queries) {
        try {
            $this->beginTransaction();
            
            $results = [];
            foreach ($queries as $query) {
                if (isset($query['type'])) {
                    switch ($query['type']) {
                        case 'select':
                            $results[] = $this->select($query['sql'], $query['params'] ?? []);
                            break;
                        case 'insert':
                            $results[] = $this->insert($query['sql'], $query['params'] ?? []);
                            break;
                        case 'update':
                        case 'delete':
                        default:
                            $results[] = $this->execute($query['sql'], $query['params'] ?? []);
                            break;
                    }
                } else {
                    // Fallback para compatibilidad
                    $results[] = $this->execute($query['sql'], $query['params'] ?? []);
                }
            }
            
            $this->commit();
            return $results;
            
        } catch (Exception $e) {
            $this->rollback();
            error_log("Transaction failed: " . $e->getMessage());
            throw new Exception("Error en la transacción: " . $e->getMessage());
        }
    }

    // Obtener el último ID insertado
    public function lastInsertId() {
        return $this->pdo->lastInsertId();
    }

    // Contar registros de una tabla con condiciones
    public function count($table, $conditions = [], $params = []) {
        try {
            $query = "SELECT COUNT(*) as total FROM " . $table;
            
            if (!empty($conditions)) {
                $query .= " WHERE " . implode(' AND ', $conditions);
            }
            
            $result = $this->select($query, $params);
            return $result[0]['total'] ?? 0;
        } catch (Exception $e) {
            error_log("Count failed: " . $e->getMessage());
            throw new Exception("Error al contar registros.");
        }
    }

    // Verificar si existe un registro
    public function exists($table, $conditions, $params = []) {
        try {
            $query = "SELECT 1 FROM " . $table . " WHERE " . implode(' AND ', $conditions) . " LIMIT 1";
            $result = $this->select($query, $params);
            return count($result) > 0;
        } catch (Exception $e) {
            error_log("Exists check failed: " . $e->getMessage());
            throw new Exception("Error al verificar existencia.");
        }
    }

    // Insertar o actualizar (UPSERT)
    public function insertOrUpdate($table, $data, $updateFields = []) {
        try {
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            $values = array_values($data);
            
            $query = "INSERT INTO " . $table . " (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            
            if (!empty($updateFields)) {
                $updateClauses = [];
                foreach ($updateFields as $field) {
                    $updateClauses[] = $field . " = VALUES(" . $field . ")";
                }
                $query .= " ON DUPLICATE KEY UPDATE " . implode(', ', $updateClauses);
            }
            
            return $this->execute($query, $values);
        } catch (Exception $e) {
            error_log("Insert or update failed: " . $e->getMessage());
            throw new Exception("Error en insertar o actualizar.");
        }
    }

    // Obtener información de la base de datos
    public function getDatabaseInfo() {
        try {
            $info = [];
            
            // Versión de MySQL
            $versionResult = $this->select("SELECT VERSION() as version");
            $info['mysql_version'] = $versionResult[0]['version'] ?? 'Desconocida';
            
            // Nombre de la base de datos
            $dbResult = $this->select("SELECT DATABASE() as db_name");
            $info['database_name'] = $dbResult[0]['db_name'] ?? 'Desconocida';
            
            // Tamaño de la base de datos
            $sizeResult = $this->select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $info['database_size_mb'] = $sizeResult[0]['size_mb'] ?? 0;
            
            // Número de tablas
            $tablesResult = $this->select("
                SELECT COUNT(*) as table_count 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ");
            $info['table_count'] = $tablesResult[0]['table_count'] ?? 0;
            
            return $info;
            
        } catch (Exception $e) {
            error_log("Get database info failed: " . $e->getMessage());
            return [
                'mysql_version' => 'Error',
                'database_name' => 'Error', 
                'database_size_mb' => 0,
                'table_count' => 0
            ];
        }
    }

    // Optimizar tablas de la base de datos
    public function optimizeTables() {
        try {
            $tablesResult = $this->select("
                SELECT table_name 
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_type = 'BASE TABLE'
            ");
            
            $optimized = [];
            foreach ($tablesResult as $table) {
                $tableName = $table['table_name'];
                $this->execute("OPTIMIZE TABLE " . $tableName);
                $optimized[] = $tableName;
            }
            
            return $optimized;
            
        } catch (Exception $e) {
            error_log("Optimize tables failed: " . $e->getMessage());
            throw new Exception("Error al optimizar tablas.");
        }
    }

    // Backup simple de la base de datos (estructura básica)
    public function createBackup() {
        try {
            $info = $this->getDatabaseInfo();
            $backup = [
                'created_at' => date('Y-m-d H:i:s'),
                'database_name' => $info['database_name'],
                'mysql_version' => $info['mysql_version'],
                'tables' => []
            ];
            
            // Obtener lista de tablas
            $tablesResult = $this->select("SHOW TABLES");
            
            foreach ($tablesResult as $table) {
                $tableName = array_values($table)[0];
                
                // Estructura de la tabla
                $createResult = $this->select("SHOW CREATE TABLE " . $tableName);
                $backup['tables'][$tableName] = [
                    'structure' => $createResult[0]['Create Table'] ?? '',
                    'row_count' => $this->count($tableName)
                ];
            }
            
            return $backup;
            
        } catch (Exception $e) {
            error_log("Create backup failed: " . $e->getMessage());
            throw new Exception("Error al crear backup.");
        }
    }

    // Limpiar logs y datos temporales
    public function cleanupDatabase() {
        try {
            $cleaned = [];
            
            // Aquí puedes agregar consultas para limpiar datos temporales
            // Por ejemplo, eliminar sesiones expiradas, logs antiguos, etc.
            
            // Ejemplo: Limpiar sesiones expiradas (si tienes tabla de sesiones)
            // $this->execute("DELETE FROM user_sessions WHERE expires_at < NOW()");
            // $cleaned[] = 'Sesiones expiradas eliminadas';
            
            return $cleaned;
            
        } catch (Exception $e) {
            error_log("Database cleanup failed: " . $e->getMessage());
            throw new Exception("Error al limpiar base de datos.");
        }
    }

    // Cerrar conexión explícitamente
    public function close() {
        if ($this->inTransaction) {
            $this->rollback();
        }
        $this->pdo = null;
    }

    // Destructor para asegurar limpieza
    public function __destruct() {
        if ($this->inTransaction) {
            error_log("Warning: Transaction was not properly closed");
            $this->rollback();
        }
    }
}
?>