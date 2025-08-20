<?php
// Configurações da base de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'asone_bd');
define('DB_USER', 'root');
define('DB_PASS', '');

// Configurações de segurança
define('SESSION_TIMEOUT', 3600); // 1 hora
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_TIMEOUT', 900); // 15 minutos de bloqueio após tentativas falhadas

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch(PDOException $e) {
    // Log do erro
    error_log("Erro na conexão PDO: " . $e->getMessage());
    
    // Tentar com MySQLi como fallback
    try {
        $mysqli = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        if ($mysqli->connect_error) {
            throw new Exception("Erro MySQLi: " . $mysqli->connect_error);
        }
        $mysqli->set_charset("utf8mb4");
        
        // Criar adaptador PDO-like para MySQLi
        class MySQLiPDOAdapter {
            private $mysqli;
            
            public function __construct($mysqli) {
                $this->mysqli = $mysqli;
            }
            
            public function prepare($sql) {
                return new MySQLiStatementAdapter($this->mysqli->prepare($sql));
            }
            
            public function exec($sql) {
                return $this->mysqli->query($sql);
            }
            
            public function query($sql) {
                $result = $this->mysqli->query($sql);
                if ($result === false) {
                    throw new Exception("Erro na query: " . $this->mysqli->error);
                }
                return new MySQLiResultAdapter($result);
            }
        }
        
        class MySQLiStatementAdapter {
            private $stmt;
            
            public function __construct($stmt) {
                $this->stmt = $stmt;
            }
            
            public function execute($params = []) {
                if (!empty($params)) {
                    $types = str_repeat('s', count($params));
                    $this->stmt->bind_param($types, ...$params);
                }
                return $this->stmt->execute();
            }
            
            public function fetch() {
                $result = $this->stmt->get_result();
                return $result ? $result->fetch_assoc() : false;
            }
        }
        
        class MySQLiResultAdapter {
            private $result;
            
            public function __construct($result) {
                $this->result = $result;
            }
            
            public function fetch() {
                return $this->result->fetch_assoc();
            }
        }
        
        $pdo = new MySQLiPDOAdapter($mysqli);
        
    } catch(Exception $e2) {
        die("Erro fatal na conexão: " . $e2->getMessage());
    }
}
?>
