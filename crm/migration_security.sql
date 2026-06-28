-- migration_security.sql
-- Tabela para rate limiting de tentativas de login por IP

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id`          INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `ip`          VARCHAR(45)    NOT NULL,
  `tentativa_em` TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `idx_ip_tempo` (`ip`, `tentativa_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
