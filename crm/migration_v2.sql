-- =============================================================
-- CRM IRM — Migration v2
-- Execute via phpMyAdmin no banco irmcom33_crm_irm
-- =============================================================

-- Branding por empresa: logo e cores
ALTER TABLE `empresas`
  ADD COLUMN `logo` VARCHAR(255) DEFAULT NULL AFTER `nome`,
  ADD COLUMN `cor_primaria` VARCHAR(7) NOT NULL DEFAULT '#4361ee' AFTER `logo`,
  ADD COLUMN `cor_secundaria` VARCHAR(7) NOT NULL DEFAULT '#1a1d27' AFTER `cor_primaria`;

-- Atualizar empresa master com os valores padrão
UPDATE `empresas` SET cor_primaria = '#4361ee', cor_secundaria = '#1a1d27' WHERE id = 1;
