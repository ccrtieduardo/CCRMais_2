<?php
// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'ccrmais2');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8');

// Configurações de pool de conexões (simulado em PDO)
define('DB_MAX_CONNECTIONS', 20);
define('DB_IDLE_TIMEOUT', 60);

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            $this->pdo = new PDO(
                "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=".DB_CHARSET, 
                DB_USER, 
                DB_PASS, 
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => true, // Conexões persistentes para melhor performance
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone='+00:00'"
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new Exception("Database connection error");
        }
    }
    
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function closeConnection() {
        $this->pdo = null;
        self::$instance = null;
    }
}

// Função helper para obter conexão
function getDBConnection() {
    return Database::getInstance()->getConnection();
}

// Registrar função para fechar conexão ao final do script
register_shutdown_function(function() {
    if (Database::getInstance()) {
        Database::getInstance()->closeConnection();
    }
});
?>