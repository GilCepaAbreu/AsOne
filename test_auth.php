<?php
// Teste do sistema de login
require_once 'config/database.php';
require_once 'classes/Auth.php';

echo "Testando sistema de autenticação...\n";

$auth = new Auth($pdo);

// Testar login com credenciais corretas
echo "Testando login com credenciais corretas...\n";
$result = $auth->login('admin@asone.pt', 'admin123');

if ($result['success']) {
    echo "✓ Login bem-sucedido!\n";
    
    // Verificar se está logado
    if ($auth->isLoggedIn()) {
        echo "✓ Verificação de sessão bem-sucedida!\n";
        
        // Obter informações do admin
        $adminInfo = $auth->getAdminInfo();
        echo "✓ Admin logado: " . $adminInfo['nome'] . " (" . $adminInfo['tipo'] . ")\n";
        
        // Testar permissões
        if ($auth->hasPermission('super_admin')) {
            echo "✓ Permissão de super_admin confirmada!\n";
        }
        
        // Logout
        $auth->logout();
        
        if (!$auth->isLoggedIn()) {
            echo "✓ Logout bem-sucedido!\n";
        }
    }
} else {
    echo "✗ Erro no login: " . $result['message'] . "\n";
}

// Testar login com credenciais incorretas
echo "\nTestando login com credenciais incorretas...\n";
$result = $auth->login('admin@asone.pt', 'senhaerrada');

if (!$result['success']) {
    echo "✓ Login rejeitado corretamente: " . $result['message'] . "\n";
}

echo "\nTeste concluído!\n";
?>
