<?php
// Teste simples do process_login.php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "POST recebido\n";
    echo "Email: " . ($_POST['email'] ?? 'não definido') . "\n";
    echo "Password: " . (isset($_POST['password']) ? 'definida' : 'não definida') . "\n";
} else {
    echo "Método: " . $_SERVER['REQUEST_METHOD'] . "\n";
}

// Testar se os arquivos existem
$files = [
    'config/database.php',
    'classes/Auth.php'
];

foreach ($files as $file) {
    echo "Arquivo $file: " . (file_exists($file) ? 'existe' : 'NÃO EXISTE') . "\n";
}

// Testar conexão
try {
    require_once 'config/database.php';
    echo "Conexão com BD: OK\n";
} catch (Exception $e) {
    echo "Erro na conexão: " . $e->getMessage() . "\n";
}
?>
