# AsOne - Sistema de Gestão de Personal Training

## Configuração da Base de Dados

### 1. Importar a Base de Dados Principal
```sql
-- No phpMyAdmin ou MySQL, execute o arquivo:
asone_bd.sql
```

### 2. Configurar Tabelas de Administradores
```bash
# Execute o script de configuração (apenas uma vez):
php setup_database.php
```

### 3. Configuração da Base de Dados
Edite o arquivo `config/database.php` com as suas credenciais:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'asone_bd');
define('DB_USER', 'root');
define('DB_PASS', '');
```

## Sistema de Login

### Conta de Administrador Padrão
- **Email:** admin@asone.pt
- **Senha:** admin123

### Tipos de Administrador
- **super_admin:** Acesso total ao sistema
- **admin:** Acesso à maioria das funcionalidades
- **funcionario:** Acesso limitado

## Funcionalidades de Segurança

1. **Autenticação Segura:**
   - Senhas com hash bcrypt
   - Tokens de sessão únicos
   - Verificação de IP e User-Agent

2. **Proteção contra Ataques:**
   - Limite de tentativas de login
   - Bloqueio temporário por IP
   - Sessões com timeout automático

3. **Sessões Seguras:**
   - Tokens armazenados na base de dados
   - Regeneração de ID de sessão
   - Cookies seguros (HTTPS)

## Estrutura de Arquivos

```
AsOne/
├── index.php              # Página principal
├── process_login.php      # Processamento do login
├── admin/
│   ├── index.php         # Painel de administração
│   └── logout.php        # Logout
├── config/
│   └── database.php      # Configuração da BD
├── classes/
│   └── Auth.php          # Sistema de autenticação
├── asone_bd.sql          # Base de dados principal
├── admin_table.sql       # Tabelas de administradores
└── setup_database.php    # Script de configuração
```

## Como Usar

1. **Configurar a Base de Dados:**
   - Importe `asone_bd.sql` no MySQL
   - Execute `php setup_database.php`

2. **Aceder ao Sistema:**
   - Vá para `index.php`
   - Clique no botão de login (ícone de utilizador)
   - Use as credenciais padrão ou crie novos administradores

3. **Painel de Administração:**
   - Dashboard com estatísticas
   - Gestão de clientes, profissionais, marcações
   - Relatórios e configurações

## Segurança

- As senhas são armazenadas com hash bcrypt
- Sessões são validadas na base de dados
- Proteção contra CSRF, XSS e SQL Injection
- Headers de segurança configurados
- Arquivos sensíveis protegidos via .htaccess

## Notas Importantes

- Mude a senha padrão após a primeira instalação
- Use HTTPS em produção
- Configure adequadamente o servidor web
- Faça backups regulares da base de dados
