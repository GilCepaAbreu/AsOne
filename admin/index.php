<?php
require_once '../config/database.php';
require_once '../classes/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();

$adminInfo = $auth->getAdminInfo();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de Administração - AsOne</title>
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

        /* Dashboard Cards */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .dashboard-card {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            border-left: 4px solid var(--primary-color);
            transition: all 0.3s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .card-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .card-title {
            font-size: 1.1rem;
            color: var(--text-light);
            font-weight: 500;
            margin-bottom: 0.5rem;
        }

        .card-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-dark);
            font-family: 'Oswald', sans-serif;
        }

        .card-subtitle {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Quick Actions */
        .quick-actions {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-soft);
            margin-bottom: 2rem;
        }

        .section-title {
            font-family: 'Oswald', sans-serif;
            font-size: 1.5rem;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            font-weight: 600;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            padding: 2rem 1rem;
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border: 2px solid transparent;
            border-radius: var(--border-radius);
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .action-btn:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            transform: translateY(-4px);
            box-shadow: var(--shadow-medium);
        }

        .action-btn i {
            font-size: 2rem;
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
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <a href="#" class="logo-admin">
                    <div class="logo-img-admin"></div>
                    AsOne Admin
                </a>
            </div>
            
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="clientes.php" class="nav-link">
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
                <h1 class="header-title">Dashboard</h1>
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

            <!-- Dashboard Cards -->
            <div class="dashboard-grid">
                <?php
                // Buscar estatísticas
                try {
                    // Total de clientes ativos
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM clientes WHERE ativo = 1");
                    $totalClientes = $stmt->fetch()['total'];

                    // Marcações hoje
                    $stmt = $pdo->query("SELECT COUNT(*) as total FROM marcacoes WHERE data_marcacao = CURDATE()");
                    $marcacoesHoje = $stmt->fetch()['total'];

                    // Subscrições ativas (apenas de clientes ativos)
                    $stmt = $pdo->query("
                        SELECT COUNT(*) as total 
                        FROM subscricoes s
                        INNER JOIN clientes c ON s.cliente_id = c.id
                        WHERE s.ativa = 1 AND c.ativo = 1
                    ");
                    $subscricoesAtivas = $stmt->fetch()['total'];

                    // Receita mensal estimada (apenas de clientes ativos)
                    $stmt = $pdo->query("
                        SELECT SUM(s.preco_pago) as total 
                        FROM subscricoes s
                        INNER JOIN clientes c ON s.cliente_id = c.id
                        WHERE s.ativa = 1 AND c.ativo = 1
                    ");
                    $receitaMensal = $stmt->fetch()['total'] ?? 0;
                } catch (Exception $e) {
                    $totalClientes = $marcacoesHoje = $subscricoesAtivas = $receitaMensal = 0;
                }
                ?>

                <div class="dashboard-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Clientes Ativos</div>
                            <div class="card-value"><?= $totalClientes ?></div>
                            <div class="card-subtitle">Total de clientes registados</div>
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Marcações Hoje</div>
                            <div class="card-value"><?= $marcacoesHoje ?></div>
                            <div class="card-subtitle">Sessões agendadas para hoje</div>
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Subscrições Ativas</div>
                            <div class="card-value"><?= $subscricoesAtivas ?></div>
                            <div class="card-subtitle">Planos de clientes ativos</div>
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">Receita Mensal</div>
                            <div class="card-value">€<?= number_format($receitaMensal, 0) ?></div>
                            <div class="card-subtitle">Receita de clientes ativos</div>
                        </div>
                        <div class="card-icon">
                            <i class="fas fa-euro-sign"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions">
                <h2 class="section-title">Ações Rápidas</h2>
                <div class="actions-grid">
                    <a href="clientes.php" class="action-btn">
                        <i class="fas fa-user-plus"></i>
                        Novo Cliente
                    </a>
                    <a href="#" class="action-btn">
                        <i class="fas fa-calendar-plus"></i>
                        Nova Marcação
                    </a>
                    <a href="#" class="action-btn">
                        <i class="fas fa-file-invoice"></i>
                        Nova Subscrição
                    </a>
                    <a href="#" class="action-btn">
                        <i class="fas fa-chart-line"></i>
                        Ver Relatórios
                    </a>
                </div>
            </div>
        </main>
    </div>
</body>
</html>