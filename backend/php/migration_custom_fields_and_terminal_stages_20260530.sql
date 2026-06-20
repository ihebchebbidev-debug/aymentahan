-- =====================================================================
-- Single migration: custom fields schema repair + terminal stages
-- (Créer, Retour, Mes non connecté, Validé) for contracts & migrations.
--
-- Safe to run multiple times. Prefer also hitting:
--   repair_terminal_migrations.php?token=crm-seed-2026
-- which runs the same repairs via PHP (handles column renames safely).
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 1) Custom fields: legacy setup.php used entity_type / field_name …
--    Rename when present (ignore errors if already migrated).
-- ---------------------------------------------------------------------
SET @has_entity_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crminternet_custom_fields'
    AND COLUMN_NAME = 'entity_type'
);
SET @has_entity := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'crminternet_custom_fields'
    AND COLUMN_NAME = 'entity'
);

SET @sql := IF(@has_entity_type > 0 AND @has_entity = 0,
  'ALTER TABLE crminternet_custom_fields CHANGE COLUMN entity_type entity VARCHAR(20) NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_field_name := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_fields' AND COLUMN_NAME = 'field_name'
);
SET @has_field_key := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_fields' AND COLUMN_NAME = 'field_key'
);
SET @sql := IF(@has_field_name > 0 AND @has_field_key = 0,
  'ALTER TABLE crminternet_custom_fields CHANGE COLUMN field_name field_key VARCHAR(80) NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_field_label := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_fields' AND COLUMN_NAME = 'field_label'
);
SET @has_label := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_fields' AND COLUMN_NAME = 'label'
);
SET @sql := IF(@has_field_label > 0 AND @has_label = 0,
  'ALTER TABLE crminternet_custom_fields CHANGE COLUMN field_label label VARCHAR(160) NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @has_field_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_fields' AND COLUMN_NAME = 'field_type'
);
SET @has_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_fields' AND COLUMN_NAME = 'type'
);
SET @sql := IF(@has_field_type > 0 AND @has_type = 0,
  'ALTER TABLE crminternet_custom_fields CHANGE COLUMN field_type type VARCHAR(20) NOT NULL DEFAULT ''text''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Custom field values (legacy)
SET @v_has_entity_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_field_values' AND COLUMN_NAME = 'entity_type'
);
SET @v_has_entity := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_field_values' AND COLUMN_NAME = 'entity'
);
SET @sql := IF(@v_has_entity_type > 0 AND @v_has_entity = 0,
  'ALTER TABLE crminternet_custom_field_values CHANGE COLUMN entity_type entity VARCHAR(20) NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @v_has_field_value := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_field_values' AND COLUMN_NAME = 'field_value'
);
SET @v_has_value := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_field_values' AND COLUMN_NAME = 'value'
);
SET @sql := IF(@v_has_field_value > 0 AND @v_has_value = 0,
  'ALTER TABLE crminternet_custom_field_values CHANGE COLUMN field_value value TEXT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Backfill field_key on values from custom_field_id when legacy column exists
SET @has_cfid := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_field_values' AND COLUMN_NAME = 'custom_field_id'
);
SET @sql := IF(@has_cfid > 0,
  'UPDATE crminternet_custom_field_values v INNER JOIN crminternet_custom_fields f ON f.id = v.custom_field_id SET v.field_key = f.field_key, v.entity = f.entity WHERE (v.field_key IS NULL OR v.field_key = '''') AND v.custom_field_id IS NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2) Contract + migration terminal stages (same 4 statuses)
-- ---------------------------------------------------------------------
INSERT INTO crminternet_contract_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action) VALUES
  ('CS-1', 'Créer', 'info', 1, 1, 0, 0, 'none'),
  ('CS-2', 'Retour', 'destructive', 2, 0, 0, 1, 'revert_opportunity'),
  ('CS-3', 'Mes non connecté', 'warning', 3, 0, 0, 0, 'none'),
  ('CS-4', 'Validé', 'success', 4, 0, 1, 0, 'none')
ON DUPLICATE KEY UPDATE
  name = VALUES(name), color = VALUES(color), position = VALUES(position),
  is_initial = VALUES(is_initial), is_won = VALUES(is_won), is_lost = VALUES(is_lost);

INSERT INTO crminternet_migration_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action) VALUES
  ('MS-1', 'Créer', 'info', 1, 1, 0, 0, 'none'),
  ('MS-2', 'Retour', 'destructive', 2, 0, 0, 1, 'none'),
  ('MS-3', 'Mes non connecté', 'warning', 3, 0, 0, 0, 'none'),
  ('MS-4', 'Validé', 'success', 4, 0, 1, 0, 'none')
ON DUPLICATE KEY UPDATE
  name = VALUES(name), color = VALUES(color), position = VALUES(position),
  is_initial = VALUES(is_initial), is_won = VALUES(is_won), is_lost = VALUES(is_lost);

-- Remove obsolete migration stages (legacy MS-5 Annulé, old seed names)
UPDATE crminternet_migrations SET workflow_status = 'Retour', stage_id = 'MS-2'
 WHERE workflow_status = 'Annulé' OR stage_id = 'MS-5';

DELETE FROM crminternet_migration_stages
 WHERE id NOT IN ('MS-1', 'MS-2', 'MS-3', 'MS-4')
    OR name IN ('Annulé', 'Dossier ouvert', 'Pièces reçues', 'Envoyé opérateur', 'Effectué');

-- ---------------------------------------------------------------------
-- 3) Remap legacy billing / workflow values → new stage names
-- ---------------------------------------------------------------------
UPDATE crminternet_contracts SET billing_status = TRIM(billing_status);
UPDATE crminternet_migrations SET workflow_status = TRIM(workflow_status);

UPDATE crminternet_contracts SET billing_status = 'Créer'
 WHERE billing_status IN ('Pré-validé', 'Brouillon', 'En attente de validation', '');

UPDATE crminternet_contracts SET billing_status = 'Validé'
 WHERE billing_status IN ('Validé Confirmation', 'Validé', 'valide', 'Facturé');

UPDATE crminternet_contracts SET billing_status = 'Retour'
 WHERE billing_status IN ('Annuler la confirmation', 'Annulé', 'Retour');

UPDATE crminternet_migrations SET workflow_status = 'Créer'
 WHERE workflow_status IN ('Pré-validé', 'Dossier ouvert', 'MS-1 Nouvelle', '');

UPDATE crminternet_migrations SET workflow_status = 'Validé'
 WHERE workflow_status IN ('Effectué', 'MS-4 En cours', 'MS-5 Terminée', 'Validé Confirmation');

UPDATE crminternet_migrations SET workflow_status = 'Retour'
 WHERE workflow_status IN ('Annulé', 'MS-5');

UPDATE crminternet_contracts c
  JOIN crminternet_contract_stages s ON s.name = c.billing_status
   SET c.stage_id = s.id;

UPDATE crminternet_migrations m
  JOIN crminternet_migration_stages s ON s.name = m.workflow_status
   SET m.stage_id = s.id;

COMMIT;
