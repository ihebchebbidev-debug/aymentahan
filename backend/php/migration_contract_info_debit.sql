-- =====================================================================
-- Migration : ajout du champ "débit" (Mbps) à la fiche information contrat.
-- Idempotent : n'ajoute la colonne que si elle n'existe pas déjà.
-- =====================================================================

ALTER TABLE `crminternet_contract_info`
  ADD COLUMN IF NOT EXISTS `debit` INT UNSIGNED NULL COMMENT 'Débit internet en Mbps' AFTER `remarque`;
