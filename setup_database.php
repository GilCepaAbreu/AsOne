<?php
// Script para configurar a base de dados com as tabelas de administradores
require_once 'config/database.php';

try {
    echo "Iniciando configuração da base de dados...\n";
    
    // Ler e executar o SQL para criar as tabelas de administradores
    $sql = file_get_contents('admin_table.sql');
    
    // Dividir as queries e executar uma por uma
    $queries = explode(';', $sql);
    
    foreach ($queries as $query) {
        $query = trim($query);
        if (!empty($query)) {
            $pdo->exec($query);
            echo "Query executada com sucesso.\n";
        }
    }
    
    echo "Base de dados configurada com sucesso!\n";
    echo "Conta de administrador criada:\n";
    echo "Email: admin@asone.pt\n";
    echo "Senha: admin123\n";
    
} catch (PDOException $e) {
    echo "Erro na configuração da base de dados: " . $e->getMessage() . "\n";
}
?>
