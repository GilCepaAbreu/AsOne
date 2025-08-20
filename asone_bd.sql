-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Tempo de geração: 20-Ago-2025 às 17:32
-- Versão do servidor: 10.4.32-MariaDB
-- versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `asone_bd`
--

-- --------------------------------------------------------

--
-- Estrutura da tabela `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `nif` varchar(20) DEFAULT NULL,
  `morada` text DEFAULT NULL,
  `codigo_postal` varchar(10) DEFAULT NULL,
  `cidade` varchar(50) DEFAULT 'Esposende',
  `distrito` varchar(50) DEFAULT 'Braga',
  `data_inscricao` date DEFAULT curdate(),
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `clientes_ativos`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `clientes_ativos` (
`id` int(11)
,`nome` varchar(100)
,`email` varchar(150)
,`telefone` varchar(20)
,`tipo_plano` varchar(50)
,`frequencia_semanal` int(11)
,`data_inicio` date
,`data_fim` date
,`sessoes_restantes` int(11)
);

-- --------------------------------------------------------

--
-- Estrutura da tabela `configuracoes`
--

CREATE TABLE `configuracoes` (
  `id` int(11) NOT NULL,
  `chave` varchar(100) NOT NULL,
  `valor` text NOT NULL,
  `descricao` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `configuracoes`
--

INSERT INTO `configuracoes` (`id`, `chave`, `valor`, `descricao`, `updated_at`) VALUES
(1, 'horario_abertura', '07:00', 'Horário de abertura do estúdio', '2025-08-20 15:30:43'),
(2, 'horario_encerramento', '22:00', 'Horário de encerramento do estúdio', '2025-08-20 15:30:43'),
(3, 'duracao_sessao', '60', 'Duração padrão de cada sessão em minutos', '2025-08-20 15:30:43'),
(4, 'antecedencia_minima', '24', 'Antecedência mínima para marcação em horas', '2025-08-20 15:30:43'),
(5, 'cancelamento_maximo', '24', 'Tempo máximo para cancelamento sem penalização em horas', '2025-08-20 15:30:43'),
(6, 'nome_estudio', 'AsOne', 'Nome do estúdio', '2025-08-20 15:30:43'),
(7, 'endereco', 'R. João Conde, 4740-305 Esposende, Braga, Portugal', 'Endereço do estúdio', '2025-08-20 15:30:43'),
(8, 'email_contato', 'info@estudiofitness.pt', 'Email de contato', '2025-08-20 15:30:43'),
(9, 'telefone_contato', '+351 912 798 296', 'Telefone de contato', '2025-08-20 15:30:43');

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `disponibilidade_profissionais`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `disponibilidade_profissionais` (
`profissional_id` int(11)
,`profissional` varchar(100)
,`dia_semana` enum('Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo')
,`hora_inicio` time
,`hora_fim` time
);

-- --------------------------------------------------------

--
-- Estrutura da tabela `especialidades`
--

CREATE TABLE `especialidades` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `especialidades`
--

INSERT INTO `especialidades` (`id`, `nome`, `descricao`, `created_at`) VALUES
(1, 'Personal Training', 'Treino personalizado individual', '2025-08-20 15:30:43'),
(2, 'Psicólogo(a)', 'Psicologia', '2025-08-20 15:30:43'),
(3, 'Nutricionista', 'Nutrição', '2025-08-20 15:30:43');

-- --------------------------------------------------------

--
-- Estrutura da tabela `horarios_trabalho`
--

CREATE TABLE `horarios_trabalho` (
  `id` int(11) NOT NULL,
  `profissional_id` int(11) NOT NULL,
  `dia_semana` enum('Segunda','Terça','Quarta','Quinta','Sexta','Sábado','Domingo') NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `marcacoes`
--

CREATE TABLE `marcacoes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `profissional_id` int(11) NOT NULL,
  `subscricao_id` int(11) NOT NULL,
  `data_marcacao` date NOT NULL,
  `hora_inicio` time NOT NULL,
  `hora_fim` time NOT NULL,
  `status` enum('Agendada','Confirmada','Concluída','Cancelada','Falta') DEFAULT 'Agendada',
  `observacoes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Acionadores `marcacoes`
--
DELIMITER $$
CREATE TRIGGER `atualizar_sessoes_utilizadas` AFTER UPDATE ON `marcacoes` FOR EACH ROW BEGIN
    IF NEW.status = 'Concluída' AND OLD.status != 'Concluída' THEN
        UPDATE subscricoes 
        SET sessoes_utilizadas = sessoes_utilizadas + 1,
            sessoes_restantes = sessoes_restantes - 1
        WHERE id = NEW.subscricao_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `restaurar_sessao_cancelada` AFTER UPDATE ON `marcacoes` FOR EACH ROW BEGIN
    IF NEW.status = 'Cancelada' AND OLD.status = 'Concluída' THEN
        UPDATE subscricoes 
        SET sessoes_utilizadas = sessoes_utilizadas - 1,
            sessoes_restantes = sessoes_restantes + 1
        WHERE id = NEW.subscricao_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura stand-in para vista `marcacoes_hoje`
-- (Veja abaixo para a view atual)
--
CREATE TABLE `marcacoes_hoje` (
`id` int(11)
,`cliente` varchar(100)
,`profissional` varchar(100)
,`hora_inicio` time
,`hora_fim` time
,`status` enum('Agendada','Confirmada','Concluída','Cancelada','Falta')
,`tipo_plano` varchar(50)
);

-- --------------------------------------------------------

--
-- Estrutura da tabela `planos_treino`
--

CREATE TABLE `planos_treino` (
  `id` int(11) NOT NULL,
  `tipo_plano_id` int(11) NOT NULL,
  `frequencia_semanal` int(11) NOT NULL CHECK (`frequencia_semanal` between 1 and 5),
  `preco_mensal` decimal(10,2) NOT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `planos_treino`
--

INSERT INTO `planos_treino` (`id`, `tipo_plano_id`, `frequencia_semanal`, `preco_mensal`, `ativo`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 110.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(2, 1, 2, 190.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(3, 1, 3, 260.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(4, 1, 4, 330.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(5, 1, 5, 400.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(6, 2, 1, 150.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(7, 2, 2, 250.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(8, 2, 3, 350.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(9, 2, 4, 450.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(10, 2, 5, 550.00, 1, '2025-08-20 15:30:43', '2025-08-20 15:30:43');

-- --------------------------------------------------------

--
-- Estrutura da tabela `profissionais`
--

CREATE TABLE `profissionais` (
  `id` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `telefone` varchar(20) DEFAULT NULL,
  `data_nascimento` date DEFAULT NULL,
  `numero_cedula` varchar(50) DEFAULT NULL,
  `ativo` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `profissional_especialidades`
--

CREATE TABLE `profissional_especialidades` (
  `id` int(11) NOT NULL,
  `profissional_id` int(11) NOT NULL,
  `especialidade_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura da tabela `subscricoes`
--

CREATE TABLE `subscricoes` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `plano_treino_id` int(11) NOT NULL,
  `data_inicio` date NOT NULL,
  `data_fim` date NOT NULL,
  `preco_pago` decimal(10,2) NOT NULL,
  `sessoes_utilizadas` int(11) DEFAULT 0,
  `sessoes_restantes` int(11) NOT NULL,
  `ativa` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Acionadores `subscricoes`
--
DELIMITER $$
CREATE TRIGGER `calcular_sessoes_restantes` BEFORE INSERT ON `subscricoes` FOR EACH ROW BEGIN
    DECLARE freq INT;
    DECLARE dias_mes INT DEFAULT 30;
    
    SELECT frequencia_semanal INTO freq
    FROM planos_treino 
    WHERE id = NEW.plano_treino_id;
    
    SET NEW.sessoes_restantes = (freq * 4); -- 4 semanas por mês aproximadamente
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura da tabela `tipos_plano`
--

CREATE TABLE `tipos_plano` (
  `id` int(11) NOT NULL,
  `nome` varchar(50) NOT NULL,
  `descricao` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Extraindo dados da tabela `tipos_plano`
--

INSERT INTO `tipos_plano` (`id`, `nome`, `descricao`, `created_at`, `updated_at`) VALUES
(1, 'Individual', 'Treino personalizado individual', '2025-08-20 15:30:43', '2025-08-20 15:30:43'),
(2, 'Pares', 'Treino em dupla (2 pessoas)', '2025-08-20 15:30:43', '2025-08-20 15:30:43');

-- --------------------------------------------------------

--
-- Estrutura para vista `clientes_ativos`
--
DROP TABLE IF EXISTS `clientes_ativos`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `clientes_ativos`  AS SELECT `c`.`id` AS `id`, `c`.`nome` AS `nome`, `c`.`email` AS `email`, `c`.`telefone` AS `telefone`, `tp`.`nome` AS `tipo_plano`, `pt`.`frequencia_semanal` AS `frequencia_semanal`, `s`.`data_inicio` AS `data_inicio`, `s`.`data_fim` AS `data_fim`, `s`.`sessoes_restantes` AS `sessoes_restantes` FROM (((`clientes` `c` join `subscricoes` `s` on(`c`.`id` = `s`.`cliente_id`)) join `planos_treino` `pt` on(`s`.`plano_treino_id` = `pt`.`id`)) join `tipos_plano` `tp` on(`pt`.`tipo_plano_id` = `tp`.`id`)) WHERE `c`.`ativo` = 1 AND `s`.`ativa` = 1 AND `s`.`data_fim` >= curdate() ;

-- --------------------------------------------------------

--
-- Estrutura para vista `disponibilidade_profissionais`
--
DROP TABLE IF EXISTS `disponibilidade_profissionais`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `disponibilidade_profissionais`  AS SELECT `p`.`id` AS `profissional_id`, `p`.`nome` AS `profissional`, `ht`.`dia_semana` AS `dia_semana`, `ht`.`hora_inicio` AS `hora_inicio`, `ht`.`hora_fim` AS `hora_fim` FROM (`profissionais` `p` join `horarios_trabalho` `ht` on(`p`.`id` = `ht`.`profissional_id`)) WHERE `p`.`ativo` = 1 AND `ht`.`ativo` = 1 ORDER BY `p`.`nome` ASC, `ht`.`dia_semana` ASC, `ht`.`hora_inicio` ASC ;

-- --------------------------------------------------------

--
-- Estrutura para vista `marcacoes_hoje`
--
DROP TABLE IF EXISTS `marcacoes_hoje`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `marcacoes_hoje`  AS SELECT `m`.`id` AS `id`, `c`.`nome` AS `cliente`, `p`.`nome` AS `profissional`, `m`.`hora_inicio` AS `hora_inicio`, `m`.`hora_fim` AS `hora_fim`, `m`.`status` AS `status`, `tp`.`nome` AS `tipo_plano` FROM (((((`marcacoes` `m` join `clientes` `c` on(`m`.`cliente_id` = `c`.`id`)) join `profissionais` `p` on(`m`.`profissional_id` = `p`.`id`)) join `subscricoes` `s` on(`m`.`subscricao_id` = `s`.`id`)) join `planos_treino` `pt` on(`s`.`plano_treino_id` = `pt`.`id`)) join `tipos_plano` `tp` on(`pt`.`tipo_plano_id` = `tp`.`id`)) WHERE `m`.`data_marcacao` = curdate() ORDER BY `m`.`hora_inicio` ASC ;

--
-- Índices para tabelas despejadas
--

--
-- Índices para tabela `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `nif` (`nif`),
  ADD KEY `idx_clientes_ativo` (`ativo`);

--
-- Índices para tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `chave` (`chave`);

--
-- Índices para tabela `especialidades`
--
ALTER TABLE `especialidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- Índices para tabela `horarios_trabalho`
--
ALTER TABLE `horarios_trabalho`
  ADD PRIMARY KEY (`id`),
  ADD KEY `profissional_id` (`profissional_id`);

--
-- Índices para tabela `marcacoes`
--
ALTER TABLE `marcacoes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_prof_datetime` (`profissional_id`,`data_marcacao`,`hora_inicio`),
  ADD KEY `subscricao_id` (`subscricao_id`),
  ADD KEY `idx_profissional_data` (`profissional_id`,`data_marcacao`),
  ADD KEY `idx_cliente_data` (`cliente_id`,`data_marcacao`),
  ADD KEY `idx_data_hora` (`data_marcacao`,`hora_inicio`),
  ADD KEY `idx_marcacoes_status` (`status`);

--
-- Índices para tabela `planos_treino`
--
ALTER TABLE `planos_treino`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_plano` (`tipo_plano_id`,`frequencia_semanal`);

--
-- Índices para tabela `profissionais`
--
ALTER TABLE `profissionais`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `numero_cedula` (`numero_cedula`),
  ADD KEY `idx_profissionais_ativo` (`ativo`);

--
-- Índices para tabela `profissional_especialidades`
--
ALTER TABLE `profissional_especialidades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_prof_esp` (`profissional_id`,`especialidade_id`),
  ADD KEY `especialidade_id` (`especialidade_id`);

--
-- Índices para tabela `subscricoes`
--
ALTER TABLE `subscricoes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `plano_treino_id` (`plano_treino_id`),
  ADD KEY `idx_cliente_ativa` (`cliente_id`,`ativa`),
  ADD KEY `idx_data_fim` (`data_fim`),
  ADD KEY `idx_subscricoes_ativa_data_fim` (`ativa`,`data_fim`);

--
-- Índices para tabela `tipos_plano`
--
ALTER TABLE `tipos_plano`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nome` (`nome`);

--
-- AUTO_INCREMENT de tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `configuracoes`
--
ALTER TABLE `configuracoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT de tabela `especialidades`
--
ALTER TABLE `especialidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de tabela `horarios_trabalho`
--
ALTER TABLE `horarios_trabalho`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `marcacoes`
--
ALTER TABLE `marcacoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `planos_treino`
--
ALTER TABLE `planos_treino`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT de tabela `profissionais`
--
ALTER TABLE `profissionais`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `profissional_especialidades`
--
ALTER TABLE `profissional_especialidades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `subscricoes`
--
ALTER TABLE `subscricoes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `tipos_plano`
--
ALTER TABLE `tipos_plano`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- Restrições para despejos de tabelas
--

--
-- Limitadores para a tabela `horarios_trabalho`
--
ALTER TABLE `horarios_trabalho`
  ADD CONSTRAINT `horarios_trabalho_ibfk_1` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `marcacoes`
--
ALTER TABLE `marcacoes`
  ADD CONSTRAINT `marcacoes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `marcacoes_ibfk_2` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`),
  ADD CONSTRAINT `marcacoes_ibfk_3` FOREIGN KEY (`subscricao_id`) REFERENCES `subscricoes` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `planos_treino`
--
ALTER TABLE `planos_treino`
  ADD CONSTRAINT `planos_treino_ibfk_1` FOREIGN KEY (`tipo_plano_id`) REFERENCES `tipos_plano` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `profissional_especialidades`
--
ALTER TABLE `profissional_especialidades`
  ADD CONSTRAINT `profissional_especialidades_ibfk_1` FOREIGN KEY (`profissional_id`) REFERENCES `profissionais` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `profissional_especialidades_ibfk_2` FOREIGN KEY (`especialidade_id`) REFERENCES `especialidades` (`id`) ON DELETE CASCADE;

--
-- Limitadores para a tabela `subscricoes`
--
ALTER TABLE `subscricoes`
  ADD CONSTRAINT `subscricoes_ibfk_1` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `subscricoes_ibfk_2` FOREIGN KEY (`plano_treino_id`) REFERENCES `planos_treino` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
