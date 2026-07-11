-- Add "débit" (Mbps) column to contracts
-- Idempotent-friendly: the schema_repair.php + runtime auto-heal both add it
-- as well, this file is the canonical one-shot migration.

ALTER TABLE `crminternet_contracts`
  ADD COLUMN `debit` INT UNSIGNED NULL COMMENT 'Débit internet en Mbps' AFTER `premium`;

CREATE INDEX `idx_contract_debit` ON `crminternet_contracts` (`debit`);