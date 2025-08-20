<?php
// Teste de conexão simples
echo "Testando conexão...\n";

// Verificar extensões PHP
echo "Extensões PHP carregadas:\n";
if (extension_loaded('pdo')) {
    echo "- PDO: OK\n";
} else {
    echo "- PDO: NÃO CARREGADO\n";
}

if (extension_loaded('pdo_mysql')) {
    echo "- PDO MySQL: OK\n";
} else {
    echo "- PDO MySQL: NÃO CARREGADO\n";
}

// Tentar conexão direta com mysqli
try {
    $mysqli = new mysqli('localhost', 'root', '', 'asone_bd');
    if ($mysqli->connect_error) {
        die('Erro na conexão MySQLi: ' . $mysqli->connect_error);
    }
    echo "Conexão MySQLi: OK\n";
    $mysqli->close();
} catch (Exception $e) {
    echo "Erro MySQLi: " . $e->getMessage() . "\n";
}

// Tentar conexão PDO com caminho específico do XAMPP
try {
    $pdo = new PDO("mysql:host=localhost;dbname=asone_bd;charset=utf8mb4", 'root', '');
    echo "Conexão PDO: OK\n";
} catch (PDOException $e) {
    echo "Erro PDO: " . $e->getMessage() . "\n";
}
?>
