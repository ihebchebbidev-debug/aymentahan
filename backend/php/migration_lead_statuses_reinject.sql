-- Lead suivi statuses — réinjection (idempotent)
-- Run once per environment, or rely on stages.php auto-ensure on GET.

INSERT INTO crminternet_lead_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action)
SELECT 'S-reinject-1', 'A réinjecter', 'warning', 15, 0, 0, 0, 'none'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM crminternet_lead_stages WHERE name = 'A réinjecter');

INSERT INTO crminternet_lead_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action)
SELECT 'S-reinject-2', 'Réinjecté', 'success', 16, 0, 0, 0, 'none'
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM crminternet_lead_stages WHERE name = 'Réinjecté');
