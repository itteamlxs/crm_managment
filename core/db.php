<?php
require_once CONFIG_PATH . '/database.php';

class Database {
    private $pdo;

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

    // Obtener la conexión PDO (para transacciones avanzadas)
    public function getConnection() {
        return $this->pdo;
    }
}
?>