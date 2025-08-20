<?php
require_once '../config/database.php';
require_once '../classes/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();

$adminInfo = $auth->getAdminInfo();

// Processar ações AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'create_client':
                $stmt = $pdo->prepare("
                    INSERT INTO clientes (nome, email, password_hash, telefone, data_nascimento, nif, morada, codigo_postal, cidade, distrito) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt->execute([
                    $_POST['nome'],
                    $_POST['email'],
                    $password_hash,
                    $_POST['telefone'],
                    $_POST['data_nascimento'],
                    $_POST['nif'],
                    $_POST['morada'],
                    $_POST['codigo_postal'],
                    $_POST['cidade'] ?: 'Esposende',
                    $_POST['distrito'] ?: 'Braga'
                ]);
                
                $cliente_id = $pdo->lastInsertId();
                
                // Criar subscrição se plano foi selecionado
                if (!empty($_POST['plano_id'])) {
                    // GARANTIR: Desativar qualquer subscrição ativa existente (caso existam dados inconsistentes)
                    $stmt = $pdo->prepare("UPDATE subscricoes SET ativa = 0 WHERE cliente_id = ? AND ativa = 1");
                    $stmt->execute([$cliente_id]);
                    
                    $stmt = $pdo->prepare("SELECT preco_mensal FROM planos_treino WHERE id = ?");
                    $stmt->execute([$_POST['plano_id']]);
                    $plano = $stmt->fetch();
                    
                    if ($plano) {
                        $data_inicio = $_POST['data_inicio'] ?: date('Y-m-d');
                        $ultimo_pagamento = $data_inicio;
                        $proximo_pagamento = date('Y-m-d', strtotime($data_inicio . ' +1 month'));
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO subscricoes (cliente_id, plano_treino_id, data_inicio, ultimo_pagamento, proximo_pagamento, preco_pago, ativa) 
                            VALUES (?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$cliente_id, $_POST['plano_id'], $data_inicio, $ultimo_pagamento, $proximo_pagamento, $plano['preco_mensal']]);
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'Cliente criado com sucesso!']);
                break;
                
            case 'update_client':
                $stmt = $pdo->prepare("
                    UPDATE clientes SET nome=?, email=?, telefone=?, data_nascimento=?, nif=?, morada=?, codigo_postal=?, cidade=?, distrito=? 
                    WHERE id=?
                ");
                $stmt->execute([
                    $_POST['nome'],
                    $_POST['email'],
                    $_POST['telefone'],
                    $_POST['data_nascimento'],
                    $_POST['nif'],
                    $_POST['morada'],
                    $_POST['codigo_postal'],
                    $_POST['cidade'],
                    $_POST['distrito'],
                    $_POST['cliente_id']
                ]);
                
                // Atualizar ou criar subscrição se plano foi selecionado
                if (!empty($_POST['plano_id'])) {
                    // GARANTIR: Primeiro desativar TODAS as subscrições ativas deste cliente
                    $stmt = $pdo->prepare("UPDATE subscricoes SET ativa = 0 WHERE cliente_id = ? AND ativa = 1");
                    $stmt->execute([$_POST['cliente_id']]);
                    
                    // Buscar informações do plano
                    $stmt = $pdo->prepare("SELECT preco_mensal FROM planos_treino WHERE id = ?");
                    $stmt->execute([$_POST['plano_id']]);
                    $plano = $stmt->fetch();
                    
                    if ($plano) {
                        $data_inicio = $_POST['data_inicio'] ?: date('Y-m-d');
                        $ultimo_pagamento = $data_inicio;
                        $proximo_pagamento = date('Y-m-d', strtotime($data_inicio . ' +1 month'));
                        
                        // Criar nova subscrição ativa (única)
                        $stmt = $pdo->prepare("
                            INSERT INTO subscricoes (cliente_id, plano_treino_id, data_inicio, ultimo_pagamento, proximo_pagamento, preco_pago, ativa) 
                            VALUES (?, ?, ?, ?, ?, ?, 1)
                        ");
                        $stmt->execute([$_POST['cliente_id'], $_POST['plano_id'], $data_inicio, $ultimo_pagamento, $proximo_pagamento, $plano['preco_mensal']]);
                    }
                } else {
                    // Se não foi selecionado plano, desativar TODAS as subscrições ativas
                    $stmt = $pdo->prepare("UPDATE subscricoes SET ativa = 0 WHERE cliente_id = ? AND ativa = 1");
                    $stmt->execute([$_POST['cliente_id']]);
                }
                
                echo json_encode(['success' => true, 'message' => 'Cliente atualizado com sucesso!']);
                break;
                
            case 'registar_pagamento':
                $stmt = $pdo->prepare("
                    UPDATE subscricoes 
                    SET ultimo_pagamento = CURDATE(), 
                        proximo_pagamento = DATE_ADD(CURDATE(), INTERVAL 1 MONTH),
                        updated_at = CURRENT_TIMESTAMP
                    WHERE cliente_id = ? AND ativa = 1
                ");
                $stmt->execute([$_POST['cliente_id']]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Pagamento registado com sucesso!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro: Cliente não tem subscrição ativa.']);
                }
                break;
                
            case 'toggle_client':
                $stmt = $pdo->prepare("UPDATE clientes SET ativo = !ativo WHERE id = ?");
                $stmt->execute([$_POST['cliente_id']]);
                
                echo json_encode(['success' => true, 'message' => 'Status do cliente atualizado!']);
                break;
                
            case 'get_client':
                $stmt = $pdo->prepare("
                    SELECT c.*, 
                           s.plano_treino_id as plano_atual_id, 
                           s.data_inicio as data_inicio_subscricao,
                           s.ultimo_pagamento,
                           s.proximo_pagamento
                    FROM clientes c 
                    LEFT JOIN subscricoes s ON c.id = s.cliente_id AND s.ativa = 1
                    WHERE c.id = ?
                ");
                $stmt->execute([$_POST['cliente_id']]);
                $cliente = $stmt->fetch();
                
                echo json_encode(['success' => true, 'cliente' => $cliente]);
                break;
                
            case 'get_client_full':
                $stmt = $pdo->prepare("
                    SELECT c.*, 
                           s.plano_treino_id as plano_atual_id, 
                           s.data_inicio as data_inicio_subscricao,
                           s.ultimo_pagamento,
                           s.proximo_pagamento,
                           s.preco_pago,
                           tp.nome as plano_nome,
                           p.frequencia_semanal
                    FROM clientes c 
                    LEFT JOIN subscricoes s ON c.id = s.cliente_id AND s.ativa = 1
                    LEFT JOIN planos_treino p ON s.plano_treino_id = p.id
                    LEFT JOIN tipos_plano tp ON p.tipo_plano_id = tp.id
                    WHERE c.id = ?
                ");
                $stmt->execute([$_POST['cliente_id']]);
                $cliente = $stmt->fetch();
                
                echo json_encode(['success' => true, 'cliente' => $cliente]);
                break;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
    }
    exit;
}

// Buscar clientes
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(nome LIKE ? OR email LIKE ? OR telefone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($status === 'active') {
    $where_conditions[] = "ativo = 1";
} elseif ($status === 'inactive') {
    $where_conditions[] = "ativo = 0";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(s.id) as total_subscricoes,
           SUM(CASE WHEN s.ativa = 1 THEN 1 ELSE 0 END) as subscricoes_ativas,
           MAX(CASE WHEN s.ativa = 1 THEN s.proximo_pagamento END) as proximo_pagamento
    FROM clientes c 
    LEFT JOIN subscricoes s ON c.id = s.cliente_id 
    $where_clause
    GROUP BY c.id 
    ORDER BY c.created_at DESC
");
$stmt->execute($params);
$clientes = $stmt->fetchAll();

// Buscar planos de treino para o formulário
$stmt = $pdo->query("
    SELECT pt.*, tp.nome as tipo_nome 
    FROM planos_treino pt 
    JOIN tipos_plano tp ON pt.tipo_plano_id = tp.id 
    WHERE pt.ativo = 1
    ORDER BY tp.nome, pt.frequencia_semanal
");
$planos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestão de Clientes - AsOne</title>
    <link rel="icon" type="image/png" href="../image.png">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Oswald:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e293b;
            --accent-color: #3b82f6;
            --dark-bg: #0f172a;
            --light-bg: #f8fafc;
            --text-dark: #1e293b;
            --text-light: #64748b;
            --white: #ffffff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --border-radius: 12px;
            --shadow-soft: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-medium: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: var(--light-bg);
            color: var(--text-dark);
            line-height: 1.6;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: var(--white);
            box-shadow: var(--shadow-medium);
            border-right: 1px solid #e2e8f0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: center;
        }

        .logo-admin {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            font-family: 'Oswald', sans-serif;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-decoration: none;
        }

        .logo-img-admin {
            width: 40px;
            height: 40px;
            background-image: url('../image.png');
            background-size: cover;
            background-position: center;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem 1.25rem;
            color: var(--text-dark);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            transform: translateX(4px);
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
        }

        .header {
            background: var(--white);
            padding: 1.5rem 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-family: 'Oswald', sans-serif;
            font-size: 2rem;
            color: var(--text-dark);
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .user-details h4 {
            color: var(--text-dark);
            font-weight: 600;
        }

        .user-details p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .logout-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        /* Content Area */
        .content-area {
            background: var(--white);
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            overflow: hidden;
        }

        .content-header {
            padding: 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .content-title {
            font-family: 'Oswald', sans-serif;
            font-size: 1.5rem;
            color: var(--text-dark);
            font-weight: 600;
        }

        .toolbar {
            display: flex;
            gap: 1rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            width: 300px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-light);
        }

        .filter-select {
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            background: white;
            cursor: pointer;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-warning {
            background: var(--warning);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .table th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            color: var(--text-dark);
            font-size: 0.9rem;
        }

        .table tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #dcfce7;
            color: #166534;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 0;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: var(--shadow-medium);
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-family: 'Oswald', sans-serif;
            font-size: 1.3rem;
            color: var(--text-dark);
            font-weight: 600;
        }

        .close {
            color: var(--text-light);
            font-size: 1.5rem;
            font-weight: bold;
            cursor: pointer;
            border: none;
            background: none;
            padding: 0.5rem;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close:hover {
            background: #f3f4f6;
            color: var(--text-dark);
        }

        .modal-body {
            padding: 2rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text-dark);
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            height: 100px;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .alert {
            padding: 1rem;
            border-radius: var(--border-radius);
            margin-bottom: 1rem;
            font-weight: 500;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Modal de Visualização */
        .client-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            max-height: 60vh;
            overflow-y: auto;
        }

        .info-section {
            background: #f8fafc;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .section-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--primary-color);
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: var(--text-dark);
            min-width: 120px;
            flex-shrink: 0;
        }

        .info-value {
            color: var(--text-light);
            text-align: right;
            word-break: break-word;
            flex: 1;
            margin-left: 1rem;
        }

        .info-value.status-active {
            color: var(--success);
            font-weight: 600;
        }

        .info-value.status-inactive {
            color: var(--danger);
            font-weight: 600;
        }

        .info-value.subscription-active {
            color: var(--primary-color);
            font-weight: 600;
        }

        /* Estilos do Modal de Pagamento */
        .payment-confirmation {
            text-align: center;
        }

        .client-info-card {
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            text-align: left;
        }

        .client-avatar {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .client-details h4 {
            margin: 0;
            color: var(--text-dark);
            font-size: 1.1em;
            font-weight: 600;
        }

        .subscription-badge {
            background: var(--primary-color);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .payment-details {
            background: #f8fafc;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }

        .payment-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .payment-row:last-child {
            border-bottom: none;
        }

        .payment-label {
            color: var(--text-light);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-value {
            font-weight: 600;
            color: var(--text-dark);
        }

        .payment-value.highlight {
            color: var(--success);
            font-size: 1.1em;
        }

        .payment-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 15px;
        }

        .payment-warning p {
            margin: 0;
            color: #856404;
            font-size: 0.9em;
            display: flex;
            align-items: flex-start;
            gap: 8px;
        }

        .payment-warning .fas {
            margin-top: 2px;
            color: #f39c12;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .toolbar {
                flex-direction: column;
                width: 100%;
            }

            .search-box input {
                width: 100%;
            }

            .table-container {
                font-size: 0.8rem;
            }

            .actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="index.php" class="logo-admin">
                    <div class="logo-img-admin"></div>
                    AsOne Admin
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="index.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="clientes.php" class="nav-link active">
                        <i class="fas fa-users"></i>
                        Clientes
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-user-tie"></i>
                        Profissionais
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        Marcações
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-credit-card"></i>
                        Subscrições
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        Relatórios
                    </a>
                </div>
                <?php if ($adminInfo['tipo'] === 'super_admin'): ?>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Configurações
                    </a>
                </div>
                <?php endif; ?>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <header class="header">
                <h1 class="header-title">Gestão de Clientes</h1>
                <div class="user-info">
                    <div class="user-avatar">
                        <?= strtoupper(substr($adminInfo['nome'], 0, 2)) ?>
                    </div>
                    <div class="user-details">
                        <h4><?= htmlspecialchars($adminInfo['nome']) ?></h4>
                        <p><?= ucfirst($adminInfo['tipo']) ?></p>
                    </div>
                    <a href="logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Sair
                    </a>
                </div>
            </header>

            <div class="content-area">
                <div class="content-header">
                    <h2 class="content-title">Lista de Clientes</h2>
                    <div class="toolbar">
                        <div class="search-box">
                            <i class="fas fa-search" id="searchIcon"></i>
                            <input type="text" id="searchInput" placeholder="Pesquisar clientes..." value="<?= htmlspecialchars($search) ?>">
                            <i class="fas fa-spinner fa-spin" id="searchLoading" style="display: none; position: absolute; right: 35px; top: 50%; transform: translateY(-50%); color: var(--primary-color);"></i>
                            <?php if (!empty($search)): ?>
                            <button type="button" id="clearSearch" onclick="clearSearch()" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-light); cursor: pointer; padding: 5px;" title="Limpar pesquisa">
                                <i class="fas fa-times"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        <select id="statusFilter" class="filter-select">
                            <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>Todos os Status</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Ativos</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inativos</option>
                        </select>
                        <button class="btn btn-primary" onclick="openCreateModal()">
                            <i class="fas fa-plus"></i>
                            Novo Cliente
                        </button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Email</th>
                                <th>Telefone</th>
                                <th>Subscrição Atual</th>
                                <th>Próximo Pagamento</th>
                                <th>Status</th>
                                <th>Data de Inscrição</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clientes as $cliente): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($cliente['nome']) ?></strong>
                                    <?php if ($cliente['nif']): ?>
                                    <br><small>NIF: <?= htmlspecialchars($cliente['nif']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($cliente['email']) ?></td>
                                <td><?= htmlspecialchars($cliente['telefone'] ?? '-') ?></td>
                                <td>
                                    <?php if ($cliente['subscricoes_ativas'] > 0): ?>
                                        <span class="status-badge status-active">
                                            Com Subscrição
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">
                                            Sem Subscrição
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($cliente['total_subscricoes'] > 0): ?>
                                    <br><small><?= $cliente['total_subscricoes'] ?> no histórico</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($cliente['proximo_pagamento']): ?>
                                        <?php 
                                        $proximoPagamento = new DateTime($cliente['proximo_pagamento']);
                                        $hoje = new DateTime();
                                        $diferenca = $hoje->diff($proximoPagamento);
                                        $diasRestantes = $diferenca->days;
                                        $vencido = $proximoPagamento < $hoje;
                                        ?>
                                        <div style="font-size: 0.9em;">
                                            <?= date('d/m/Y', strtotime($cliente['proximo_pagamento'])) ?>
                                            <?php if ($vencido): ?>
                                                <br><span style="color: #dc3545; font-weight: bold;">Vencido há <?= $diasRestantes ?> dias</span>
                                            <?php elseif ($diasRestantes <= 7): ?>
                                                <br><span style="color: #fd7e14; font-weight: bold;">Vence em <?= $diasRestantes ?> dias</span>
                                            <?php else: ?>
                                                <br><span style="color: #28a745;">Faltam <?= $diasRestantes ?> dias</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="color: var(--text-light); font-style: italic;">Sem subscrição</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge <?= $cliente['ativo'] ? 'status-active' : 'status-inactive' ?>">
                                        <?= $cliente['ativo'] ? 'Ativo' : 'Inativo' ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($cliente['data_inscricao'])) ?></td>
                                <td>
                                    <div class="actions">
                                        <button class="btn btn-sm btn-info" onclick="viewClient(<?= $cliente['id'] ?>)" title="Visualizar informações">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-primary" onclick="editClient(<?= $cliente['id'] ?>)" title="Editar cliente">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($cliente['subscricoes_ativas'] > 0): ?>
                                        <button class="btn btn-sm btn-success" onclick="registarPagamento(<?= $cliente['id'] ?>)" title="Registar pagamento">
                                            <i class="fas fa-money-bill-wave"></i>
                                        </button>
                                        <?php endif; ?>
                                        <button class="btn btn-sm <?= $cliente['ativo'] ? 'btn-warning' : 'btn-success' ?>" 
                                                onclick="toggleClient(<?= $cliente['id'] ?>)" title="<?= $cliente['ativo'] ? 'Desativar' : 'Ativar' ?> cliente">
                                            <i class="fas fa-<?= $cliente['ativo'] ? 'pause' : 'play' ?>"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($clientes)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 3rem; color: var(--text-light);">
                                    <i class="fas fa-users" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.3;"></i>
                                    <br>Nenhum cliente encontrado
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- Modal Visualizar Cliente -->
    <div id="viewClientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Informações do Cliente</h3>
                <button class="close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="client-info-grid">
                    <!-- Informações Pessoais -->
                    <div class="info-section">
                        <h4 class="section-title">
                            <i class="fas fa-user"></i>
                            Informações Pessoais
                        </h4>
                        <div class="info-row">
                            <span class="info-label">Nome Completo:</span>
                            <span class="info-value" id="viewNome">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Email:</span>
                            <span class="info-value" id="viewEmail">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Telefone:</span>
                            <span class="info-value" id="viewTelefone">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Data de Nascimento:</span>
                            <span class="info-value" id="viewDataNascimento">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">NIF:</span>
                            <span class="info-value" id="viewNif">-</span>
                        </div>
                    </div>

                    <!-- Informações de Localização -->
                    <div class="info-section">
                        <h4 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            Localização
                        </h4>
                        <div class="info-row">
                            <span class="info-label">Morada:</span>
                            <span class="info-value" id="viewMorada">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Código Postal:</span>
                            <span class="info-value" id="viewCodigoPostal">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Cidade:</span>
                            <span class="info-value" id="viewCidade">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Distrito:</span>
                            <span class="info-value" id="viewDistrito">-</span>
                        </div>
                    </div>

                    <!-- Informações de Sistema -->
                    <div class="info-section">
                        <h4 class="section-title">
                            <i class="fas fa-cog"></i>
                            Sistema
                        </h4>
                        <div class="info-row">
                            <span class="info-label">Data de Inscrição:</span>
                            <span class="info-value" id="viewDataInscricao">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Status:</span>
                            <span class="info-value" id="viewStatus">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">ID do Cliente:</span>
                            <span class="info-value" id="viewClienteId">-</span>
                        </div>
                    </div>

                    <!-- Informações de Subscrição -->
                    <div class="info-section">
                        <h4 class="section-title">
                            <i class="fas fa-credit-card"></i>
                            Subscrição Atual
                        </h4>
                        <div class="info-row">
                            <span class="info-label">Plano:</span>
                            <span class="info-value" id="viewPlano">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Data de Início:</span>
                            <span class="info-value" id="viewDataInicioSub">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Próximo Pagamento:</span>
                            <span class="info-value" id="viewDataFimSub">-</span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Preço:</span>
                            <span class="info-value" id="viewPrecoSub">-</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewModal()">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="editClientFromView()">
                    <i class="fas fa-edit"></i>
                    Editar Cliente
                </button>
            </div>
        </div>
    </div>

    <!-- Modal de Confirmação de Pagamento -->
    <div id="paymentModal" class="modal">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h3>
                    <i class="fas fa-money-bill-wave" style="color: #28a745; margin-right: 10px;"></i>
                    Confirmar Pagamento
                </h3>
                <button class="close" onclick="closePaymentModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="payment-confirmation">
                    <div class="client-info-card">
                        <div class="client-avatar">
                            <i class="fas fa-user" style="font-size: 2em; color: var(--primary-color);"></i>
                        </div>
                        <div class="client-details">
                            <h4 id="paymentClientName">Nome do Cliente</h4>
                            <p id="paymentClientEmail" style="color: var(--text-light); margin: 5px 0;"></p>
                            <div class="subscription-info">
                                <span class="subscription-badge" id="paymentSubscriptionInfo">
                                    <i class="fas fa-dumbbell"></i>
                                    Plano de Treino
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="payment-details">
                        <div class="payment-row">
                            <span class="payment-label">
                                <i class="fas fa-calendar-alt"></i>
                                Último Pagamento:
                            </span>
                            <span class="payment-value" id="paymentLastDate">-</span>
                        </div>
                        <div class="payment-row">
                            <span class="payment-label">
                                <i class="fas fa-calendar-check"></i>
                                Próximo Pagamento:
                            </span>
                            <span class="payment-value" id="paymentNextDate">-</span>
                        </div>
                        <div class="payment-row">
                            <span class="payment-label">
                                <i class="fas fa-euro-sign"></i>
                                Valor Mensal:
                            </span>
                            <span class="payment-value highlight" id="paymentAmount">€0.00</span>
                        </div>
                    </div>

                    <div class="payment-warning">
                        <p>
                            <i class="fas fa-info-circle"></i>
                            Ao confirmar, será registado o pagamento de <strong>hoje (<?= date('d/m/Y') ?>)</strong> 
                            e o próximo pagamento será agendado para <strong><?= date('d/m/Y', strtotime('+1 month')) ?></strong>.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closePaymentModal()">
                    <i class="fas fa-times"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-success" onclick="confirmPayment()" id="confirmPaymentBtn">
                    <i class="fas fa-check"></i>
                    Confirmar Pagamento
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Criar/Editar Cliente -->
    <div id="clientModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Novo Cliente</h3>
                <button class="close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form id="clientForm">
                    <input type="hidden" id="clientId" name="cliente_id">
                    <input type="hidden" id="formAction" name="action" value="create_client">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nome">Nome Completo *</label>
                            <input type="text" id="nome" name="nome" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                    </div>

                    <div class="form-row" id="passwordRow">
                        <div class="form-group">
                            <label for="password">Senha *</label>
                            <input type="password" id="password" name="password">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="telefone">Telefone</label>
                            <input type="tel" id="telefone" name="telefone">
                        </div>
                        <div class="form-group">
                            <label for="data_nascimento">Data de Nascimento</label>
                            <input type="date" id="data_nascimento" name="data_nascimento">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="nif">NIF</label>
                            <input type="text" id="nif" name="nif">
                        </div>
                        <div class="form-group">
                            <label for="morada">Morada</label>
                            <textarea id="morada" name="morada"></textarea>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="codigo_postal">Código Postal</label>
                            <input type="text" id="codigo_postal" name="codigo_postal" placeholder="4740-305">
                        </div>
                        <div class="form-group">
                            <label for="cidade">Cidade</label>
                            <input type="text" id="cidade" name="cidade" value="Esposende">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="distrito">Distrito</label>
                            <input type="text" id="distrito" name="distrito" value="Braga">
                        </div>
                        <div class="form-group" id="planoGroup">
                            <label for="plano_id" id="planoLabel">Plano (Opcional)</label>
                            <select id="plano_id" name="plano_id">
                                <option value="">Sem plano</option>
                                <?php foreach ($planos as $plano): ?>
                                <option value="<?= $plano['id'] ?>">
                                    <?= htmlspecialchars($plano['tipo_nome']) ?> - 
                                    <?= $plano['frequencia_semanal'] ?>x/semana - 
                                    €<?= number_format($plano['preco_mensal'], 0) ?>/mês
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="planoHelp" style="color: var(--text-light); font-size: 0.8rem; margin-top: 0.5rem; display: block;"></small>
                        </div>
                    </div>

                    <div class="form-group" id="dataInicioGroup" style="display: none;">
                        <label for="data_inicio">Data de Início da Subscrição</label>
                        <input type="date" id="data_inicio" name="data_inicio" value="<?= date('Y-m-d') ?>">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="saveClient()">
                    <i class="fas fa-save"></i>
                    Guardar
                </button>
            </div>
        </div>
    </div>

    <script>
        // Variável para controlar o delay da pesquisa
        let searchTimeout;

        // Pesquisa e filtros com debounce
        document.getElementById('searchInput').addEventListener('input', function() {
            // Limpar timeout anterior se existir
            clearTimeout(searchTimeout);
            
            // Mostrar indicador de loading se há texto
            const searchValue = this.value.trim();
            const searchIcon = document.getElementById('searchIcon');
            const searchLoading = document.getElementById('searchLoading');
            
            if (searchValue.length > 0) {
                searchIcon.style.display = 'none';
                searchLoading.style.display = 'block';
            } else {
                searchIcon.style.display = 'block';
                searchLoading.style.display = 'none';
            }
            
            // Definir novo timeout de 800ms para dar tempo ao utilizador
            searchTimeout = setTimeout(function() {
                // Ocultar loading antes de executar pesquisa
                searchIcon.style.display = 'block';
                searchLoading.style.display = 'none';
                updateFilters();
            }, 800);
            
            // Se o campo ficou vazio, executar pesquisa imediatamente
            if (searchValue.length === 0) {
                clearTimeout(searchTimeout);
                updateFilters();
            }
        });

        document.getElementById('statusFilter').addEventListener('change', function() {
            // Filtro de status executa imediatamente
            updateFilters();
        });

        function updateFilters() {
            const search = document.getElementById('searchInput').value;
            const status = document.getElementById('statusFilter').value;
            const url = new URL(window.location);
            
            if (search) {
                url.searchParams.set('search', search);
            } else {
                url.searchParams.delete('search');
            }
            
            if (status !== 'all') {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            
            window.location = url;
        }

        // Função para limpar pesquisa rapidamente
        function clearSearch() {
            document.getElementById('searchInput').value = '';
            document.getElementById('searchIcon').style.display = 'block';
            document.getElementById('searchLoading').style.display = 'none';
            updateFilters();
        }

        // Modal functions
        function openCreateModal() {
            document.getElementById('modalTitle').textContent = 'Novo Cliente';
            document.getElementById('formAction').value = 'create_client';
            document.getElementById('clientForm').reset();
            document.getElementById('clientId').value = '';
            document.getElementById('passwordRow').style.display = 'grid';
            document.getElementById('password').required = true;
            document.getElementById('planoGroup').style.display = 'block';
            document.getElementById('planoLabel').textContent = 'Plano Inicial (Opcional)';
            document.getElementById('planoHelp').textContent = 'Selecione um plano para criar uma subscrição inicial. Cada cliente pode ter apenas uma subscrição ativa.';
            document.getElementById('dataInicioGroup').style.display = 'none';
            document.getElementById('clientModal').style.display = 'block';
        }

        function editClient(clientId) {
            document.getElementById('modalTitle').textContent = 'Editar Cliente';
            document.getElementById('formAction').value = 'update_client';
            document.getElementById('clientId').value = clientId;
            document.getElementById('passwordRow').style.display = 'none';
            document.getElementById('password').required = false;
            document.getElementById('planoGroup').style.display = 'block';
            document.getElementById('planoLabel').textContent = 'Alterar Plano (Opcional)';
            document.getElementById('planoHelp').textContent = 'Selecione um novo plano para substituir a subscrição atual. Deixe vazio para cancelar a subscrição.';
            document.getElementById('dataInicioGroup').style.display = 'block';
            
            // Buscar dados do cliente
            fetch('clientes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_client&cliente_id=' + clientId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cliente = data.cliente;
                    
                    // Preencher todos os campos disponíveis no modal
                    document.getElementById('nome').value = cliente.nome || '';
                    document.getElementById('email').value = cliente.email || '';
                    document.getElementById('telefone').value = cliente.telefone || '';
                    document.getElementById('data_nascimento').value = cliente.data_nascimento || '';
                    document.getElementById('nif').value = cliente.nif || '';
                    document.getElementById('morada').value = cliente.morada || '';
                    document.getElementById('codigo_postal').value = cliente.codigo_postal || '';
                    document.getElementById('cidade').value = cliente.cidade || '';
                    document.getElementById('distrito').value = cliente.distrito || '';
                    
                    // Selecionar plano atual se existir
                    if (cliente.plano_atual_id) {
                        document.getElementById('plano_id').value = cliente.plano_atual_id;
                        document.getElementById('dataInicioGroup').style.display = 'block';
                        // Preencher data de início da subscrição atual se existir
                        if (cliente.data_inicio_subscricao) {
                            document.getElementById('data_inicio').value = cliente.data_inicio_subscricao;
                        }
                    } else {
                        document.getElementById('plano_id').value = '';
                        document.getElementById('dataInicioGroup').style.display = 'none';
                        // Data padrão para nova subscrição
                        document.getElementById('data_inicio').value = '<?= date('Y-m-d') ?>';
                    }
                    
                    document.getElementById('clientModal').style.display = 'block';
                }
            });
        }

        function closeModal() {
            document.getElementById('clientModal').style.display = 'none';
        }

        function saveClient() {
            const form = document.getElementById('clientForm');
            const formData = new FormData(form);
            
            fetch('clientes.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    window.location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro na comunicação com o servidor');
                console.error('Error:', error);
            });
        }

        function toggleClient(clientId) {
            if (confirm('Tem certeza que deseja alterar o status deste cliente?')) {
                fetch('clientes.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'action=toggle_client&cliente_id=' + clientId
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Erro: ' + data.message);
                    }
                });
            }
        }

        function registarPagamento(clientId) {
            // Buscar dados do cliente para mostrar no modal
            fetch('clientes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_client_full&cliente_id=' + clientId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cliente = data.cliente;
                    
                    // Preencher dados no modal
                    document.getElementById('paymentClientName').textContent = cliente.nome;
                    document.getElementById('paymentClientEmail').textContent = cliente.email;
                    
                    if (cliente.plano_nome) {
                        document.getElementById('paymentSubscriptionInfo').innerHTML = 
                            `<i class="fas fa-dumbbell"></i> ${cliente.plano_nome} (${cliente.frequencia_semanal}x/semana)`;
                        document.getElementById('paymentAmount').textContent = 
                            cliente.preco_pago ? `€${parseFloat(cliente.preco_pago).toFixed(2)}` : '€0.00';
                        document.getElementById('paymentLastDate').textContent = 
                            cliente.ultimo_pagamento ? formatDate(cliente.ultimo_pagamento) : 'Nenhum';
                        document.getElementById('paymentNextDate').textContent = 
                            cliente.proximo_pagamento ? formatDate(cliente.proximo_pagamento) : '-';
                    }
                    
                    // Guardar ID do cliente para confirmação
                    document.getElementById('confirmPaymentBtn').setAttribute('data-client-id', clientId);
                    
                    // Mostrar modal
                    document.getElementById('paymentModal').style.display = 'block';
                } else {
                    alert('Erro ao carregar dados do cliente: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erro ao carregar dados do cliente.');
            });
        }

        function closePaymentModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        function confirmPayment() {
            const clientId = document.getElementById('confirmPaymentBtn').getAttribute('data-client-id');
            
            fetch('clientes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=registar_pagamento&cliente_id=' + clientId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closePaymentModal();
                    // Mostrar notificação de sucesso
                    showSuccessMessage('Pagamento registado com sucesso!');
                    // Recarregar página após delay para mostrar notificação
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Erro ao registar pagamento.');
            });
        }

        // Funções do Modal de Visualização
        function viewClient(clientId) {
            // Buscar dados completos do cliente
            fetch('clientes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_client_full&cliente_id=' + clientId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const cliente = data.cliente;
                    
                    // Preencher informações pessoais
                    document.getElementById('viewNome').textContent = cliente.nome || '-';
                    document.getElementById('viewEmail').textContent = cliente.email || '-';
                    document.getElementById('viewTelefone').textContent = cliente.telefone || '-';
                    document.getElementById('viewDataNascimento').textContent = 
                        cliente.data_nascimento ? formatDate(cliente.data_nascimento) : '-';
                    document.getElementById('viewNif').textContent = cliente.nif || '-';
                    
                    // Preencher localização
                    document.getElementById('viewMorada').textContent = cliente.morada || '-';
                    document.getElementById('viewCodigoPostal').textContent = cliente.codigo_postal || '-';
                    document.getElementById('viewCidade').textContent = cliente.cidade || '-';
                    document.getElementById('viewDistrito').textContent = cliente.distrito || '-';
                    
                    // Preencher informações de sistema
                    document.getElementById('viewDataInscricao').textContent = 
                        cliente.data_inscricao ? formatDate(cliente.data_inscricao) : '-';
                    
                    const statusEl = document.getElementById('viewStatus');
                    statusEl.textContent = cliente.ativo == 1 ? 'Ativo' : 'Inativo';
                    statusEl.className = 'info-value ' + (cliente.ativo == 1 ? 'status-active' : 'status-inactive');
                    
                    document.getElementById('viewClienteId').textContent = cliente.id || '-';
                    
                    // Preencher informações de subscrição
                    if (cliente.plano_nome) {
                        document.getElementById('viewPlano').textContent = 
                            `${cliente.plano_nome} (${cliente.frequencia_semanal}x/semana)`;
                        document.getElementById('viewPlano').className = 'info-value subscription-active';
                        
                        document.getElementById('viewDataInicioSub').textContent = 
                            cliente.data_inicio_subscricao ? formatDate(cliente.data_inicio_subscricao) : '-';
                        document.getElementById('viewDataFimSub').textContent = 
                            cliente.proximo_pagamento ? formatDate(cliente.proximo_pagamento) : '-';
                        document.getElementById('viewPrecoSub').textContent = 
                            cliente.preco_pago ? `€${parseFloat(cliente.preco_pago).toFixed(2)}` : '-';
                    } else {
                        document.getElementById('viewPlano').textContent = 'Sem subscrição ativa';
                        document.getElementById('viewPlano').className = 'info-value';
                        document.getElementById('viewDataInicioSub').textContent = '-';
                        document.getElementById('viewDataFimSub').textContent = '-';
                        document.getElementById('viewPrecoSub').textContent = '-';
                    }
                    
                    // Armazenar ID para o botão de editar
                    window.currentClientId = clientId;
                    
                    // Mostrar modal
                    document.getElementById('viewClientModal').style.display = 'block';
                } else {
                    alert('Erro ao carregar informações do cliente: ' + data.message);
                }
            })
            .catch(error => {
                alert('Erro na comunicação com o servidor');
                console.error('Error:', error);
            });
        }

        function closeViewModal() {
            document.getElementById('viewClientModal').style.display = 'none';
        }

        function editClientFromView() {
            closeViewModal();
            editClient(window.currentClientId);
        }

        function formatDate(dateString) {
            if (!dateString) return '-';
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-PT');
        }

        function showSuccessMessage(message) {
            // Criar elemento de notificação
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
                border-radius: 8px;
                padding: 15px 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 500;
                animation: slideIn 0.3s ease-out;
            `;
            
            notification.innerHTML = `
                <i class="fas fa-check-circle" style="color: #28a745;"></i>
                ${message}
            `;
            
            // Adicionar animação CSS
            if (!document.getElementById('notification-styles')) {
                const style = document.createElement('style');
                style.id = 'notification-styles';
                style.textContent = `
                    @keyframes slideIn {
                        from {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                        to {
                            transform: translateX(0);
                            opacity: 1;
                        }
                    }
                    @keyframes slideOut {
                        from {
                            transform: translateX(0);
                            opacity: 1;
                        }
                        to {
                            transform: translateX(100%);
                            opacity: 0;
                        }
                    }
                `;
                document.head.appendChild(style);
            }
            
            document.body.appendChild(notification);
            
            // Remover após 3 segundos
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-in';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Mostrar/ocultar data de início quando plano é selecionado
        document.getElementById('plano_id').addEventListener('change', function() {
            const dataInicioGroup = document.getElementById('dataInicioGroup');
            if (this.value) {
                dataInicioGroup.style.display = 'block';
            } else {
                dataInicioGroup.style.display = 'none';
            }
        });

        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            const clientModal = document.getElementById('clientModal');
            const viewClientModal = document.getElementById('viewClientModal');
            const paymentModal = document.getElementById('paymentModal');
            
            if (event.target == clientModal) {
                closeModal();
            }
            if (event.target == viewClientModal) {
                closeViewModal();
            }
            if (event.target == paymentModal) {
                closePaymentModal();
            }
        }
    </script>
</body>
</html>
