-- ============================================================
-- Migration: Combined (Lead Statuses & Standard Audit Columns)
-- ============================================================

-- Renommer 'refuse' en 'deja migré' si 'refuse' existe encore
UPDATE crminternet_lead_stages SET name = 'deja migré' WHERE name = 'refuse';
UPDATE crminternet_prospects SET status = 'deja migré' WHERE status = 'refuse';

-- Ensures all 17 default lead call statuses exist in crminternet_lead_stages.
-- Idempotent: Only inserts statuses if their name/id does not already exist.

CREATE TABLE IF NOT EXISTS crminternet_lead_stages (
  id VARCHAR(40) PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  color VARCHAR(20) NOT NULL DEFAULT 'muted',
  position INT NOT NULL DEFAULT 0,
  is_initial TINYINT(1) NOT NULL DEFAULT 0,
  is_won TINYINT(1) NOT NULL DEFAULT 0,
  is_lost TINYINT(1) NOT NULL DEFAULT 0,
  auto_action VARCHAR(40) NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO crminternet_lead_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action)
SELECT * FROM (
  SELECT 'S-1' AS id, 'Ok' AS name, 'success' AS color, 1 AS position, 0 AS is_initial, 1 AS is_won, 0 AS is_lost, 'none' AS auto_action UNION ALL
  SELECT 'S-2', 'Att cin', 'warning', 2, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-3', 'Att confirmation', 'warning', 3, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-4', 'Rappel', 'info', 4, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-5', 'deja migré', 'destructive', 5, 0, 0, 1, 'none' UNION ALL
  SELECT 'S-6', 'migration', 'primary', 6, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-7', 'Basculement', 'primary', 7, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-8', 'Ing', 'info', 8, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-9', 'Nrp', 'muted', 9, 1, 0, 0, 'none' UNION ALL
  SELECT 'S-10', 'Pas de rep', 'muted', 10, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-11', 'Pas intersse', 'destructive', 11, 0, 0, 1, 'none' UNION ALL
  SELECT 'S-12', 'Déjà connecté', 'success', 12, 0, 1, 0, 'none' UNION ALL
  SELECT 'S-13', 'Autr dde encor', 'info', 13, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-14', 'Autre', 'muted', 14, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-15', 'A réinjecter', 'warning', 15, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-16', 'Réinjecté', 'success', 16, 0, 0, 0, 'none' UNION ALL
  SELECT 'S-17', 'facture impayé', 'destructive', 17, 0, 0, 1, 'none'
) AS tmp;
