-- =============================================================
-- CRM Multi-Cliente — Script de criação do banco de dados
-- Executar via phpMyAdmin ou linha de comando MySQL
-- =============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- -------------------------------------------------------------
-- Tabela: empresas
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `empresas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` VARCHAR(150) NOT NULL,
  `status` ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: usuarios
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `email` VARCHAR(200) NOT NULL,
  `senha_hash` VARCHAR(255) NOT NULL,
  `cargo` ENUM('master','gerente','atendente') NOT NULL DEFAULT 'atendente',
  `status` ENUM('ativo','inativo') NOT NULL DEFAULT 'ativo',
  `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ultimo_login` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_email` (`email`),
  KEY `idx_empresa_id` (`empresa_id`),
  CONSTRAINT `fk_usuarios_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: etapas_funil
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `etapas_funil` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(100) NOT NULL,
  `ordem` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `cor` VARCHAR(7) NOT NULL DEFAULT '#6c757d',
  PRIMARY KEY (`id`),
  KEY `idx_empresa_ordem` (`empresa_id`, `ordem`),
  CONSTRAINT `fk_etapas_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: contatos
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `contatos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT UNSIGNED NOT NULL,
  `nome` VARCHAR(150) NOT NULL,
  `telefone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(200) DEFAULT NULL,
  `origem` VARCHAR(100) DEFAULT NULL,
  `criado_por` INT UNSIGNED NOT NULL,
  `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_contatos_empresa` (`empresa_id`),
  KEY `idx_contatos_criado_por` (`criado_por`),
  CONSTRAINT `fk_contatos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_contatos_usuario` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: negociacoes
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `negociacoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT UNSIGNED NOT NULL,
  `contato_id` INT UNSIGNED NOT NULL,
  `etapa_id` INT UNSIGNED NOT NULL,
  `responsavel_id` INT UNSIGNED NOT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `valor_estimado` DECIMAL(12,2) DEFAULT NULL,
  `status` ENUM('em_andamento','ganho','perdido') NOT NULL DEFAULT 'em_andamento',
  `motivo_perda` TEXT DEFAULT NULL,
  `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `data_atualizacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_neg_empresa_etapa` (`empresa_id`, `etapa_id`),
  KEY `idx_neg_contato` (`contato_id`),
  KEY `idx_neg_responsavel` (`responsavel_id`),
  CONSTRAINT `fk_neg_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_neg_contato` FOREIGN KEY (`contato_id`) REFERENCES `contatos` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_neg_etapa` FOREIGN KEY (`etapa_id`) REFERENCES `etapas_funil` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_neg_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: interacoes
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `interacoes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `contato_id` INT UNSIGNED NOT NULL,
  `negociacao_id` INT UNSIGNED DEFAULT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('ligacao','email','reuniao','whatsapp','observacao','outro') NOT NULL DEFAULT 'observacao',
  `descricao` TEXT NOT NULL,
  `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inter_contato` (`contato_id`),
  KEY `idx_inter_negociacao` (`negociacao_id`),
  CONSTRAINT `fk_inter_contato` FOREIGN KEY (`contato_id`) REFERENCES `contatos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inter_negociacao` FOREIGN KEY (`negociacao_id`) REFERENCES `negociacoes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inter_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: atividades (tarefas)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `atividades` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT UNSIGNED NOT NULL,
  `negociacao_id` INT UNSIGNED DEFAULT NULL,
  `responsavel_id` INT UNSIGNED NOT NULL,
  `titulo` VARCHAR(200) NOT NULL,
  `descricao` TEXT DEFAULT NULL,
  `data_vencimento` DATETIME DEFAULT NULL,
  `concluida` TINYINT(1) NOT NULL DEFAULT 0,
  `data_conclusao` DATETIME DEFAULT NULL,
  `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_atv_empresa` (`empresa_id`),
  KEY `idx_atv_responsavel_venc` (`responsavel_id`, `data_vencimento`),
  KEY `idx_atv_negociacao` (`negociacao_id`),
  CONSTRAINT `fk_atv_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_atv_negociacao` FOREIGN KEY (`negociacao_id`) REFERENCES `negociacoes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_atv_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: log_atividades (auditoria)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `log_atividades` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT UNSIGNED NOT NULL,
  `usuario_id` INT UNSIGNED NOT NULL,
  `acao` VARCHAR(50) NOT NULL,
  `referencia_id` INT UNSIGNED DEFAULT NULL,
  `detalhes` JSON DEFAULT NULL,
  `data_criacao` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_empresa_data` (`empresa_id`, `data_criacao`),
  KEY `idx_log_usuario` (`usuario_id`),
  CONSTRAINT `fk_log_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_log_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: campos_customizados
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `campos_customizados` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `empresa_id` INT UNSIGNED NOT NULL,
  `entidade` ENUM('contato','negociacao') NOT NULL,
  `nome_campo` VARCHAR(100) NOT NULL,
  `tipo_campo` ENUM('texto','numero','data','selecao') NOT NULL DEFAULT 'texto',
  `opcoes` TEXT DEFAULT NULL,
  `obrigatorio` TINYINT(1) NOT NULL DEFAULT 0,
  `ordem` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_campos_empresa` (`empresa_id`),
  CONSTRAINT `fk_campos_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- Tabela: valores_campos_customizados
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `valores_campos_customizados` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `campo_id` INT UNSIGNED NOT NULL,
  `registro_id` INT UNSIGNED NOT NULL,
  `valor` TEXT DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vcc_campo` (`campo_id`),
  KEY `idx_vcc_registro` (`registro_id`),
  CONSTRAINT `fk_vcc_campo` FOREIGN KEY (`campo_id`) REFERENCES `campos_customizados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- Dados iniciais: empresa master + usuário master
-- Senha padrão: Master@2024 (trocar após primeiro login)
-- =============================================================

INSERT INTO `empresas` (`id`, `nome`, `status`) VALUES
(1, 'IRM Comunicação (Master)', 'ativo');

INSERT INTO `usuarios` (`empresa_id`, `nome`, `email`, `senha_hash`, `cargo`, `status`) VALUES
(1, 'Administrador Master', 'irmcomunicacao@gmail.com',
 '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uHpwg396O',
 'master', 'ativo');
-- Senha acima corresponde a: Master@2024

INSERT INTO `etapas_funil` (`empresa_id`, `nome`, `ordem`, `cor`) VALUES
(1, 'Novo Lead',         1, '#4361ee'),
(1, 'Contato Feito',    2, '#7209b7'),
(1, 'Proposta Enviada', 3, '#f48c06'),
(1, 'Em Negociação',   4, '#3a86ff'),
(1, 'Ganho',            5, '#2dc653'),
(1, 'Perdido',          6, '#ef233c');
