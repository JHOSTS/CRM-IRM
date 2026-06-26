-- Migration v3 — Campos de datas no contato
-- Execute no phpMyAdmin antes de subir os arquivos

ALTER TABLE `contatos`
  ADD COLUMN `data_nascimento`   DATE     DEFAULT NULL AFTER `origem`,
  ADD COLUMN `data_entrada`      DATETIME DEFAULT NULL AFTER `data_nascimento`,
  ADD COLUMN `data_ultima_compra` DATE    DEFAULT NULL AFTER `data_entrada`;

-- Preencher data_entrada para contatos já existentes (usa data_criacao)
UPDATE `contatos` SET `data_entrada` = `data_criacao` WHERE `data_entrada` IS NULL;
