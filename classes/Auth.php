<?php
class Auth {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        
        // Configurações de sessão seguras
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_strict_mode', 1);
        
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
    }
    
    public function login($email, $password) {
        try {
            // Verificar se o IP não está bloqueado
            if ($this->isIpBlocked()) {
                return ['success' => false, 'message' => 'IP bloqueado temporariamente devido a tentativas excessivas.'];
            }
            
            // Buscar administrador
            $stmt = $this->pdo->prepare("SELECT * FROM administradores WHERE email = ? AND ativo = 1");
            $stmt->execute([$email]);
            $admin = $stmt->fetch();
            
            if (!$admin || !password_verify($password, $admin['password_hash'])) {
                $this->logFailedAttempt();
                return ['success' => false, 'message' => 'Email ou senha inválidos.'];
            }
            
            // Login bem-sucedido
            $this->createSession($admin);
            $this->updateLastLogin($admin['id']);
            $this->clearFailedAttempts();
            
            return ['success' => true, 'message' => 'Login realizado com sucesso.'];
            
        } catch (Exception $e) {
            error_log("Erro no login: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erro interno. Tente novamente.'];
        }
    }
    
    private function createSession($admin) {
        // Regenerar ID da sessão por segurança
        session_regenerate_id(true);
        
        // Criar token de sessão único
        $sessionToken = bin2hex(random_bytes(32));
        
        // Armazenar na base de dados
        $stmt = $this->pdo->prepare("
            INSERT INTO admin_sessoes (admin_id, session_token, ip_address, user_agent, expires_at) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $expiresAt = date('Y-m-d H:i:s', time() + SESSION_TIMEOUT);
        $stmt->execute([
            $admin['id'],
            $sessionToken,
            $_SERVER['REMOTE_ADDR'] ?? '',
            $_SERVER['HTTP_USER_AGENT'] ?? '',
            $expiresAt
        ]);
        
        // Armazenar na sessão PHP
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_nome'] = $admin['nome'];
        $_SESSION['admin_tipo'] = $admin['tipo_admin'];
        $_SESSION['session_token'] = $sessionToken;
        $_SESSION['login_time'] = time();
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['admin_id']) || !isset($_SESSION['session_token'])) {
            return false;
        }
        
        // Verificar se a sessão ainda é válida na base de dados
        $stmt = $this->pdo->prepare("
            SELECT id FROM admin_sessoes 
            WHERE admin_id = ? AND session_token = ? AND expires_at > NOW()
        ");
        $stmt->execute([$_SESSION['admin_id'], $_SESSION['session_token']]);
        
        if (!$stmt->fetch()) {
            $this->logout();
            return false;
        }
        
        // Verificar timeout da sessão
        if (time() - $_SESSION['login_time'] > SESSION_TIMEOUT) {
            $this->logout();
            return false;
        }
        
        return true;
    }
    
    public function logout() {
        if (isset($_SESSION['session_token'])) {
            // Remover sessão da base de dados
            $stmt = $this->pdo->prepare("DELETE FROM admin_sessoes WHERE session_token = ?");
            $stmt->execute([$_SESSION['session_token']]);
        }
        
        // Limpar sessão PHP
        session_unset();
        session_destroy();
        
        // Iniciar nova sessão
        session_start();
        session_regenerate_id(true);
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /CodeCraftStudio/AsOne/index.php');
            exit;
        }
    }
    
    public function hasPermission($requiredLevel) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $levels = ['funcionario' => 1, 'admin' => 2, 'super_admin' => 3];
        $userLevel = $levels[$_SESSION['admin_tipo']] ?? 0;
        $required = $levels[$requiredLevel] ?? 999;
        
        return $userLevel >= $required;
    }
    
    private function logFailedAttempt() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (empty($ip)) return;
        
        // Criar tabela se não existir
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_login_attempts (
                ip_address VARCHAR(45) PRIMARY KEY,
                attempts INT DEFAULT 1,
                last_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                blocked_until TIMESTAMP NULL
            )
        ");
        
        $stmt = $this->pdo->prepare("
            INSERT INTO admin_login_attempts (ip_address, attempts, last_attempt) 
            VALUES (?, 1, NOW())
            ON DUPLICATE KEY UPDATE 
            attempts = attempts + 1, 
            last_attempt = NOW()
        ");
        
        $stmt->execute([$ip]);
        
        // Verificar se deve bloquear
        $stmt = $this->pdo->prepare("SELECT attempts FROM admin_login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
        $result = $stmt->fetch();
        
        if ($result && $result['attempts'] >= MAX_LOGIN_ATTEMPTS) {
            $stmt = $this->pdo->prepare("
                UPDATE admin_login_attempts 
                SET blocked_until = DATE_ADD(NOW(), INTERVAL ? SECOND) 
                WHERE ip_address = ?
            ");
            $stmt->execute([LOGIN_TIMEOUT, $ip]);
        }
    }
    
    private function isIpBlocked() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (empty($ip)) return false;
        
        $stmt = $this->pdo->prepare("
            SELECT blocked_until FROM admin_login_attempts 
            WHERE ip_address = ? AND blocked_until > NOW()
        ");
        $stmt->execute([$ip]);
        
        return $stmt->fetch() !== false;
    }
    
    private function clearFailedAttempts() {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (empty($ip)) return;
        
        $stmt = $this->pdo->prepare("DELETE FROM admin_login_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
    }
    
    private function updateLastLogin($adminId) {
        $stmt = $this->pdo->prepare("UPDATE administradores SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$adminId]);
    }
    
    public function getAdminInfo() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['admin_id'],
            'email' => $_SESSION['admin_email'],
            'nome' => $_SESSION['admin_nome'],
            'tipo' => $_SESSION['admin_tipo']
        ];
    }
}
?>
