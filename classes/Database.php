<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $charset;
    public $conn;

    public function __construct() {
        $this->host = DB_HOST;
        $this->db_name = DB_NAME;
        $this->username = DB_USER;
        $this->password = DB_PASS;
        $this->charset = DB_CHARSET;
    }

    public function getConnection() {
        $this->conn = null;
        
        // Usar MySQLi en lugar de PDO
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
        
        if ($this->conn->connect_error) {
            $error_msg = "Error de conexión MySQLi: " . $this->conn->connect_error;
            error_log($error_msg);
            
            if (DEBUG_MODE) {
                die($error_msg);
            } else {
                die("Error de conexión con la base de datos.");
            }
        }
        
        $this->conn->set_charset($this->charset);
        return $this->conn;
    }

    public function query($sql, $params = []) {
        if (!$this->conn) {
            $this->getConnection();
        }
        
        // Preparar statement si hay parámetros
        if (!empty($params)) {
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                error_log("Error preparando consulta: " . $this->conn->error);
                return false;
            }
            
            // Bind parameters
            $types = str_repeat('s', count($params));
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            return $stmt;
        } else {
            // Consulta simple
            $result = $this->conn->query($sql);
            if (!$result) {
                error_log("Error en consulta: " . $this->conn->error);
            }
            return $result;
        }
    }

    public function fetchAll($sql, $params = []) {
        $result = $this->query($sql, $params);
        
        if (!$result) {
            return [];
        }
        
        // Si es un statement preparado
        if ($result instanceof mysqli_stmt) {
            $result = $result->get_result();
        }
        
        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        return $data;
    }

    public function fetchOne($sql, $params = []) {
        $result = $this->query($sql, $params);
        
        if (!$result) {
            return false;
        }
        
        // Si es un statement preparado
        if ($result instanceof mysqli_stmt) {
            $result = $result->get_result();
        }
        
        return $result->fetch_assoc();
    }

    public function lastInsertId() {
        return $this->conn ? $this->conn->insert_id : false;
    }
    
    public function escape($value) {
        if (!$this->conn) {
            $this->getConnection();
        }
        return $this->conn->real_escape_string($value);
    }
}
?>