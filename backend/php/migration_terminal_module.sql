-- =====================================================================
-- Module MIGRATION (terminal peer to Contract) — OVH MySQL
-- Run once, or use repair_terminal_migrations.php?token=crm-seed-2026
-- =====================================================================

CREATE TABLE IF NOT EXISTS crminternet_migration_stages (
  id VARCHAR(40) NOT NULL,
  name VARCHAR(80) NOT NULL,
  color VARCHAR(20) NOT NULL DEFAULT 'muted',
  position INT NOT NULL DEFAULT 0,
  is_initial TINYINT(1) NOT NULL DEFAULT 0,
  is_won TINYINT(1) NOT NULL DEFAULT 0,
  is_lost TINYINT(1) NOT NULL DEFAULT 0,
  auto_action VARCHAR(40) NOT NULL DEFAULT 'none',
  PRIMARY KEY (id),
  UNIQUE KEY uniq_migration_stage_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO crminternet_migration_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action) VALUES
  ('MS-1', 'Créer', 'info', 1, 1, 0, 0, 'none'),
  ('MS-2', 'Retour', 'destructive', 2, 0, 0, 1, 'none'),
  ('MS-3', 'Mes non connecté', 'warning', 3, 0, 0, 0, 'none'),
  ('MS-4', 'Validé', 'success', 4, 0, 1, 0, 'none')
ON DUPLICATE KEY UPDATE name=VALUES(name), color=VALUES(color), position=VALUES(position);

DELETE FROM crminternet_migration_stages WHERE id = 'MS-5' OR name = 'Annulé';

CREATE TABLE IF NOT EXISTS crminternet_migrations (
  id VARCHAR(40) NOT NULL,
  opportunity_id VARCHAR(40) NOT NULL,
  prospect_id VARCHAR(40) NULL,
  type_id VARCHAR(40) NULL,
  civility ENUM('M','Mme') NOT NULL DEFAULT 'M',
  last_name VARCHAR(120) NOT NULL DEFAULT '',
  first_name VARCHAR(120) NOT NULL DEFAULT '',
  phone VARCHAR(40) NOT NULL DEFAULT '',
  phone2 VARCHAR(40) NULL,
  animateur VARCHAR(120) NULL,
  ancien_ligne VARCHAR(40) NULL,
  cin VARCHAR(40) NULL,
  birth_date DATE NULL,
  email VARCHAR(160) NOT NULL DEFAULT '',
  city VARCHAR(120) NOT NULL DEFAULT '',
  gouvernorat VARCHAR(120) NOT NULL DEFAULT '',
  delegation VARCHAR(120) NOT NULL DEFAULT '',
  zone VARCHAR(120) NOT NULL DEFAULT '',
  address VARCHAR(255) NOT NULL DEFAULT '',
  localisation_xy VARCHAR(64) NULL,
  code_postal VARCHAR(20) NULL,
  comment1 TEXT NULL,
  comment2 TEXT NULL,
  source VARCHAR(80) NOT NULL DEFAULT '',
  lead_status VARCHAR(80) NULL,
  old_operator VARCHAR(80) NULL,
  new_operator VARCHAR(80) NULL,
  porting_number VARCHAR(40) NULL,
  migration_type VARCHAR(40) NULL,
  requested_date DATE NULL,
  completed_date DATE NULL,
  technical_status VARCHAR(80) NULL,
  external_ref VARCHAR(80) NULL,
  stage_id VARCHAR(40) NULL,
  workflow_status VARCHAR(80) NOT NULL DEFAULT 'Pré-validé',
  assigned_to VARCHAR(80) NOT NULL DEFAULT '',
  validated_at DATETIME NULL,
  validated_by VARCHAR(80) NULL,
  notes TEXT NULL,
  created_by VARCHAR(80) NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  deleted_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_migration_opportunity (opportunity_id),
  KEY idx_migration_prospect (prospect_id),
  KEY idx_migration_assigned (assigned_to),
  KEY idx_migration_stage (stage_id),
  KEY idx_migration_workflow (workflow_status),
  KEY idx_migration_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE crminternet_opportunities
  ADD COLUMN IF NOT EXISTS converted_to_migration TINYINT(1) NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS migration_id VARCHAR(40) NULL;

-- Permissions (Administrateur = full set; adjust roles as needed)
INSERT INTO crminternet_role_permissions (role, permission, enabled) VALUES
  ('Administrateur', 'page.migrations', 1),
  ('Administrateur', 'migration.view', 1),
  ('Administrateur', 'migration.add', 1),
  ('Administrateur', 'migration.edit', 1),
  ('Administrateur', 'migration.delete', 1),
  ('Administrateur', 'migration.export', 1),
  ('Administrateur', 'migration.import', 1),
  ('Administrateur', 'migration.validate', 1),
  ('Administrateur', 'migration.revert', 1),
  ('Administrateur', 'migration.stages', 1),
  ('Administrateur', 'opportunity.convert_migration', 1)
ON DUPLICATE KEY UPDATE enabled = GREATEST(enabled, VALUES(enabled));
