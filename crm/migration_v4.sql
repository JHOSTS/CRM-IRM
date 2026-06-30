-- migration_v4.sql
-- Adiciona suporte a venda compartilhada por empresa

ALTER TABLE `empresas`
  ADD COLUMN `venda_compartilhada` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT 'Quando ativo, o resultado (ganho/perda) é atribuído a quem moveu o card por último'
  AFTER `cor_secundaria`;
