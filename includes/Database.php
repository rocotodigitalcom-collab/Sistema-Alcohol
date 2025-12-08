<?php
// includes/Database.php

class Database {
    private $host = DB_HOST;
    private $db_name = DB_NAME;
    private $username = DB_USER;
    private $password = DB_PASS;
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                $this->username, 
                $this->password
            );
            $this->conn->exec("set names utf8");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            error_log("Error de conexión: " . $exception->getMessage());
            return false;
        }
        return $this->conn;
    }

    public function fetchOne($query, $params = []) {
        if (!$this->conn) {
            $this->getConnection();
        }
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en fetchOne: " . $e->getMessage());
            return false;
        }
    }

    public function fetchAll($query, $params = []) {
        if (!$this->conn) {
            $this->getConnection();
        }
        
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en fetchAll: " . $e->getMessage());
            return false;
        }
    }

    public function execute($query, $params = []) {
        if (!$this->conn) {
            $this->getConnection();
        }
        
        try {
            $stmt = $this->conn->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error en execute: " . $e->getMessage());
            return false;
        }
    }

    public function lastInsertId() {
        return $this->conn ? $this->conn->lastInsertId() : null;
    }
}
?>