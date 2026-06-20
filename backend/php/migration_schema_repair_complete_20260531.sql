-- =====================================================================
-- CRM Internet — Single schema repair migration (2026-05-31)
--
-- Fixes legacy setup.php table shapes vs current API expectations:
--   chat_messages, attachments, calendar_events, contract_info,
--   lead_actions, idle_timeouts, prospects columns, custom fields,
--   migration module, teams, terminal stages.
--
-- Idempotent: safe to run multiple times on OVH MySQL / MariaDB 10.x.
-- Legacy tables with wrong columns are RENAMED (not dropped) when empty
-- or when a backup name is not already present.
--
-- After SQL, also run (optional, seeds permissions via PHP):
--   repair_terminal_migrations.php?token=crm-seed-2026
-- =====================================================================

START TRANSACTION;

-- ---------------------------------------------------------------------
-- 0) Track applied migrations
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crminternet_schema_migrations (
  filename   VARCHAR(160) NOT NULL,
  applied_at DATETIME     NOT NULL,
  PRIMARY KEY (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 1) Custom fields (legacy entity_type / field_name → entity / field_key)
-- ---------------------------------------------------------------------
SET @has_entity_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_fields' AND COLUMN_NAME = 'entity_type'
);
SET @has_entity := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_fields' AND COLUMN_NAME = 'entity'
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

SET @has_cfid := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_custom_field_values' AND COLUMN_NAME = 'custom_field_id'
);
SET @sql := IF(@has_cfid > 0,
  'UPDATE crminternet_custom_field_values v
     INNER JOIN crminternet_custom_fields f ON f.id = v.custom_field_id
     SET v.field_key = f.field_key, v.entity = f.entity
   WHERE (v.field_key IS NULL OR v.field_key = '''') AND v.custom_field_id IS NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 2) Prospects — columns expected by prospects.php (legacy setup.php gap)
-- ---------------------------------------------------------------------
ALTER TABLE crminternet_prospects
  ADD COLUMN IF NOT EXISTS phone2          VARCHAR(40)  NOT NULL DEFAULT '' AFTER phone,
  ADD COLUMN IF NOT EXISTS ancien_ligne    VARCHAR(40)  NULL,
  ADD COLUMN IF NOT EXISTS animateur       VARCHAR(120) NULL,
  ADD COLUMN IF NOT EXISTS birth_date      DATE         NULL,
  ADD COLUMN IF NOT EXISTS address         VARCHAR(255) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS gouvernorat     VARCHAR(120) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS delegation      VARCHAR(120) NOT NULL DEFAULT '',
  ADD COLUMN IF NOT EXISTS localisation_xy VARCHAR(64)  NULL,
  ADD COLUMN IF NOT EXISTS code_postal     VARCHAR(20)  NULL,
  ADD COLUMN IF NOT EXISTS comment         TEXT         NULL,
  ADD COLUMN IF NOT EXISTS comment2        TEXT         NULL,
  ADD COLUMN IF NOT EXISTS lost_reason     TEXT         NULL,
  ADD COLUMN IF NOT EXISTS check_valeur    ENUM('valid','invalid','pending') NOT NULL DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS converted       TINYINT(1)   NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS converted_at    DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS opportunity_id  VARCHAR(40)  NULL,
  ADD COLUMN IF NOT EXISTS type_id         VARCHAR(40)  NULL,
  ADD COLUMN IF NOT EXISTS reverted_at      DATETIME     NULL,
  ADD COLUMN IF NOT EXISTS reverted_from   VARCHAR(20)  NULL;

UPDATE crminternet_prospects SET gouvernorat = city WHERE (gouvernorat IS NULL OR gouvernorat = '') AND city <> '';
UPDATE crminternet_prospects SET delegation  = zone  WHERE (delegation  IS NULL OR delegation  = '') AND zone  <> '';
UPDATE crminternet_prospects SET comment = notes WHERE comment IS NULL AND notes IS NOT NULL AND notes <> '';

-- ---------------------------------------------------------------------
-- 3) Attachments — rename legacy setup.php columns → API columns
-- ---------------------------------------------------------------------
SET @att_entity_type := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_attachments' AND COLUMN_NAME = 'entity_type'
);
SET @att_entity := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_attachments' AND COLUMN_NAME = 'entity'
);
SET @sql := IF(@att_entity_type > 0 AND @att_entity = 0,
  'ALTER TABLE crminternet_attachments CHANGE COLUMN entity_type entity VARCHAR(20) NOT NULL',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @att_fn := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_attachments' AND COLUMN_NAME = 'file_name');
SET @att_filename := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_attachments' AND COLUMN_NAME = 'filename');
SET @sql := IF(@att_fn > 0 AND @att_filename = 0,
  'ALTER TABLE crminternet_attachments CHANGE COLUMN file_name filename VARCHAR(255) NOT NULL DEFAULT ''''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @att_fp := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_attachments' AND COLUMN_NAME = 'file_path');
SET @att_sp := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_attachments' AND COLUMN_NAME = 'storage_path');
SET @sql := IF(@att_fp > 0 AND @att_sp = 0,
  'ALTER TABLE crminternet_attachments CHANGE COLUMN file_path storage_path VARCHAR(500) NOT NULL DEFAULT ''''',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @att_fs := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_attachments' AND COLUMN_NAME = 'file_size');
SET @att_sb := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_attachments' AND COLUMN_NAME = 'size_bytes');
SET @sql := IF(@att_fs > 0 AND @att_sb = 0,
  'ALTER TABLE crminternet_attachments CHANGE COLUMN file_size size_bytes BIGINT NOT NULL DEFAULT 0',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE crminternet_attachments
  ADD COLUMN IF NOT EXISTS sha256     CHAR(64)     NULL,
  ADD COLUMN IF NOT EXISTS deleted_at DATETIME     NULL;

-- Create attachments table if missing entirely
CREATE TABLE IF NOT EXISTS crminternet_attachments (
  id           VARCHAR(40)  NOT NULL,
  entity       VARCHAR(20)  NOT NULL,
  entity_id    VARCHAR(40)  NOT NULL,
  filename     VARCHAR(255) NOT NULL,
  mime_type    VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
  size_bytes   BIGINT       NOT NULL DEFAULT 0,
  storage_path VARCHAR(500) NOT NULL,
  uploaded_by  VARCHAR(80)  NOT NULL,
  created_at   DATETIME     NOT NULL,
  sha256       CHAR(64)     NULL,
  deleted_at   DATETIME     NULL,
  PRIMARY KEY (id),
  KEY idx_entity (entity, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4) Chat messages — legacy DM schema → Messenger schema
-- ---------------------------------------------------------------------
SET @chat_legacy := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_chat_messages' AND COLUMN_NAME = 'sender_id'
);
SET @chat_new := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_chat_messages' AND COLUMN_NAME = 'conversation_id'
);
SET @chat_backup_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_chat_messages_legacy_setup'
);
SET @sql := IF(@chat_legacy > 0 AND @chat_new = 0 AND @chat_backup_exists = 0,
  'RENAME TABLE crminternet_chat_messages TO crminternet_chat_messages_legacy_setup',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS crminternet_chat_messages (
  id                  VARCHAR(40)  NOT NULL,
  conversation_id     VARCHAR(40)  NOT NULL,
  sender_username     VARCHAR(80)  NULL,
  body                TEXT         NOT NULL,
  is_system           TINYINT(1)   NOT NULL DEFAULT 0,
  attachment_id       VARCHAR(40)  NULL,
  attachment_filename VARCHAR(255) NULL,
  attachment_mime     VARCHAR(120) NULL,
  attachment_size     INT          NULL,
  created_at          DATETIME(3)  NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (id),
  KEY idx_conv_created (conversation_id, created_at),
  KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crminternet_chat_message_reads (
  message_id    VARCHAR(40) NOT NULL,
  user_username VARCHAR(80) NOT NULL,
  read_at       DATETIME(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  PRIMARY KEY (message_id, user_username),
  KEY idx_user (user_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE crminternet_chat_conversations
  ADD COLUMN IF NOT EXISTS post_policy ENUM('all','admins') NOT NULL DEFAULT 'all';

-- ---------------------------------------------------------------------
-- 5) Calendar — legacy Google-style → CRM simple (date/time/agent)
-- ---------------------------------------------------------------------
SET @cal_legacy := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_calendar_events' AND COLUMN_NAME = 'start_time'
);
SET @cal_new := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_calendar_events' AND COLUMN_NAME = 'date'
);
SET @cal_backup_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_calendar_events_legacy_setup'
);
SET @sql := IF(@cal_legacy > 0 AND @cal_new = 0 AND @cal_backup_exists = 0,
  'RENAME TABLE crminternet_calendar_events TO crminternet_calendar_events_legacy_setup',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS crminternet_calendar_events (
  id    VARCHAR(40)  NOT NULL,
  title VARCHAR(160) NOT NULL,
  date  DATE         NOT NULL,
  time  VARCHAR(8)   NOT NULL,
  type  ENUM('rdv','rappel','signature') NOT NULL DEFAULT 'rdv',
  agent VARCHAR(80)  NOT NULL,
  PRIMARY KEY (id),
  KEY idx_date (date),
  KEY idx_agent (agent)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6) Contract info — legacy key/value → wide technical row
-- ---------------------------------------------------------------------
SET @ci_legacy := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_contract_info' AND COLUMN_NAME = 'info_key'
);
SET @ci_new := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_contract_info' AND COLUMN_NAME = 'type_conn'
);
SET @ci_backup_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_contract_info_legacy_kv'
);
SET @sql := IF(@ci_legacy > 0 AND @ci_new = 0 AND @ci_backup_exists = 0,
  'RENAME TABLE crminternet_contract_info TO crminternet_contract_info_legacy_kv',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS crminternet_contract_info (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  entity_type     VARCHAR(20)     NOT NULL,
  entity_id       VARCHAR(40)     NOT NULL,
  type_conn       VARCHAR(255)    NOT NULL DEFAULT '',
  reference_tt    VARCHAR(120)    NOT NULL DEFAULT '',
  tel_ligne       VARCHAR(60)     NOT NULL DEFAULT '',
  date_activation DATE            NULL,
  etape           VARCHAR(60)     NOT NULL DEFAULT '',
  interface_type  VARCHAR(255)    NOT NULL DEFAULT '',
  fsi             VARCHAR(60)     NOT NULL DEFAULT '',
  motif_retour_tt VARCHAR(255)    NOT NULL DEFAULT '',
  etat            ENUM('','En cours','Basculement','Rejete','Valide') NOT NULL DEFAULT '',
  remarque        TEXT            NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by      VARCHAR(64)     NULL,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by      VARCHAR(64)     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY ux_entity (entity_type, entity_id),
  KEY idx_entity_id (entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7) Lead actions — legacy action_type → CRM timeline schema
-- ---------------------------------------------------------------------
SET @la_legacy := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_lead_actions' AND COLUMN_NAME = 'action_type'
);
SET @la_new := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_lead_actions' AND COLUMN_NAME = 'agent_username'
);
SET @la_backup_exists := (
  SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_lead_actions_legacy_setup'
);
SET @sql := IF(@la_legacy > 0 AND @la_new = 0 AND @la_backup_exists = 0,
  'RENAME TABLE crminternet_lead_actions TO crminternet_lead_actions_legacy_setup',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS crminternet_lead_actions (
  id             VARCHAR(40) NOT NULL,
  prospect_id    VARCHAR(40) NOT NULL,
  agent_username VARCHAR(80) NOT NULL,
  type           ENUM('appel','visite','relance','note','terrain','reseaux','technicien') NOT NULL DEFAULT 'note',
  comment        TEXT        NULL,
  created_at     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_prospect (prospect_id),
  KEY idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8) Idle timeouts — timeout_seconds → timeout_minutes (idle_timeouts.php)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crminternet_idle_timeouts (
  role             VARCHAR(64)      NOT NULL,
  timeout_minutes  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by       VARCHAR(64)      NULL,
  PRIMARY KEY (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @idle_old := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_idle_timeouts' AND COLUMN_NAME = 'timeout_seconds'
);
SET @idle_new := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_idle_timeouts' AND COLUMN_NAME = 'timeout_minutes'
);
SET @sql := IF(@idle_old > 0 AND @idle_new = 0,
  'ALTER TABLE crminternet_idle_timeouts ADD COLUMN timeout_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@idle_old > 0 AND @idle_new = 0,
  'UPDATE crminternet_idle_timeouts SET timeout_minutes = GREATEST(1, ROUND(timeout_seconds / 60))',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(@idle_old > 0,
  'ALTER TABLE crminternet_idle_timeouts DROP COLUMN timeout_seconds',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

ALTER TABLE crminternet_idle_timeouts
  ADD COLUMN IF NOT EXISTS updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_by VARCHAR(64) NULL;

INSERT IGNORE INTO crminternet_idle_timeouts (role, timeout_minutes) VALUES
  ('Administrateur', 60),
  ('Manager', 45),
  ('Agent', 30),
  ('AgentSuivi', 30),
  ('AgentActivation', 30),
  ('AgentVente', 30),
  ('Backoffice', 45);

-- ---------------------------------------------------------------------
-- 9) Teams module (configuration → Équipes)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crminternet_teams (
  id          VARCHAR(40)  NOT NULL PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  description TEXT         NULL,
  created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_team_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crminternet_team_roles (
  team_id VARCHAR(40) NOT NULL,
  role    VARCHAR(80) NOT NULL,
  PRIMARY KEY (team_id, role),
  KEY idx_team_roles_team (team_id),
  KEY idx_team_roles_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE crminternet_users
  ADD COLUMN IF NOT EXISTS guichet_entity_id VARCHAR(40) NULL,
  ADD COLUMN IF NOT EXISTS team_id VARCHAR(40) NULL;

INSERT IGNORE INTO crminternet_teams (id, name, description) VALUES
  ('team_backoffice',  'Backoffice',  'Agent Vente + Agent Activation + Agent Suivi'),
  ('team_commercial',  'Commercial',  'Agent Guichet + Agent Technico-Commercial'),
  ('team_direction',   'Direction',   'Superviseur + Ressource Humaine');

INSERT IGNORE INTO crminternet_team_roles (team_id, role) VALUES
  ('team_backoffice', 'AgentVente'),
  ('team_backoffice', 'AgentActivation'),
  ('team_backoffice', 'AgentSuivi'),
  ('team_commercial', 'AgentGuichet'),
  ('team_commercial', 'AgentTechnicoCommercial'),
  ('team_direction',  'Manager'),
  ('team_direction',  'RessourceHumaine');

-- ---------------------------------------------------------------------
-- 10) Migration terminal module (tables + opportunity link columns)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS crminternet_migration_stages (
  id          VARCHAR(40) NOT NULL,
  name        VARCHAR(80) NOT NULL,
  color       VARCHAR(20) NOT NULL DEFAULT 'muted',
  position    INT         NOT NULL DEFAULT 0,
  is_initial  TINYINT(1)  NOT NULL DEFAULT 0,
  is_won      TINYINT(1)  NOT NULL DEFAULT 0,
  is_lost     TINYINT(1)  NOT NULL DEFAULT 0,
  auto_action VARCHAR(40) NOT NULL DEFAULT 'none',
  PRIMARY KEY (id),
  UNIQUE KEY uniq_migration_stage_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS crminternet_migrations (
  id               VARCHAR(40) NOT NULL,
  opportunity_id   VARCHAR(40) NOT NULL,
  prospect_id      VARCHAR(40) NULL,
  type_id          VARCHAR(40) NULL,
  civility         ENUM('M','Mme') NOT NULL DEFAULT 'M',
  last_name        VARCHAR(120) NOT NULL DEFAULT '',
  first_name       VARCHAR(120) NOT NULL DEFAULT '',
  phone            VARCHAR(40) NOT NULL DEFAULT '',
  phone2           VARCHAR(40) NULL,
  animateur        VARCHAR(120) NULL,
  ancien_ligne     VARCHAR(40) NULL,
  cin              VARCHAR(40) NULL,
  birth_date       DATE NULL,
  email            VARCHAR(160) NOT NULL DEFAULT '',
  city             VARCHAR(120) NOT NULL DEFAULT '',
  gouvernorat      VARCHAR(120) NOT NULL DEFAULT '',
  delegation       VARCHAR(120) NOT NULL DEFAULT '',
  zone             VARCHAR(120) NOT NULL DEFAULT '',
  address          VARCHAR(255) NOT NULL DEFAULT '',
  localisation_xy  VARCHAR(64) NULL,
  code_postal      VARCHAR(20) NULL,
  comment1         TEXT NULL,
  comment2         TEXT NULL,
  source           VARCHAR(80) NOT NULL DEFAULT '',
  lead_status      VARCHAR(80) NULL,
  old_operator     VARCHAR(80) NULL,
  new_operator     VARCHAR(80) NULL,
  porting_number   VARCHAR(40) NULL,
  migration_type   VARCHAR(40) NULL,
  requested_date   DATE NULL,
  completed_date   DATE NULL,
  technical_status VARCHAR(80) NULL,
  external_ref     VARCHAR(80) NULL,
  stage_id         VARCHAR(40) NULL,
  workflow_status  VARCHAR(80) NOT NULL DEFAULT 'Créer',
  assigned_to      VARCHAR(80) NOT NULL DEFAULT '',
  validated_at     DATETIME NULL,
  validated_by     VARCHAR(80) NULL,
  notes            TEXT NULL,
  created_by       VARCHAR(80) NULL,
  created_at       DATETIME NOT NULL,
  updated_at       DATETIME NOT NULL,
  deleted_at       DATETIME NULL,
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

-- ---------------------------------------------------------------------
-- 11) Terminal stages (contracts + migrations) + legacy cleanup
-- ---------------------------------------------------------------------
INSERT INTO crminternet_contract_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action) VALUES
  ('CS-1', 'Créer',            'info',        1, 1, 0, 0, 'none'),
  ('CS-2', 'Retour',           'destructive', 2, 0, 0, 1, 'revert_opportunity'),
  ('CS-3', 'Mes non connecté', 'warning',     3, 0, 0, 0, 'none'),
  ('CS-4', 'Validé',           'success',     4, 0, 1, 0, 'none')
ON DUPLICATE KEY UPDATE
  name = VALUES(name), color = VALUES(color), position = VALUES(position),
  is_initial = VALUES(is_initial), is_won = VALUES(is_won), is_lost = VALUES(is_lost);

INSERT INTO crminternet_migration_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action) VALUES
  ('MS-1', 'Créer',            'info',        1, 1, 0, 0, 'none'),
  ('MS-2', 'Retour',           'destructive', 2, 0, 0, 1, 'none'),
  ('MS-3', 'Mes non connecté', 'warning',     3, 0, 0, 0, 'none'),
  ('MS-4', 'Validé',           'success',     4, 0, 1, 0, 'none')
ON DUPLICATE KEY UPDATE
  name = VALUES(name), color = VALUES(color), position = VALUES(position),
  is_initial = VALUES(is_initial), is_won = VALUES(is_won), is_lost = VALUES(is_lost);

DELETE FROM crminternet_migration_stages
 WHERE id NOT IN ('MS-1', 'MS-2', 'MS-3', 'MS-4')
    OR name IN ('Annulé', 'Dossier ouvert', 'Pièces reçues', 'Envoyé opérateur', 'Effectué');

UPDATE crminternet_migrations SET workflow_status = 'Retour', stage_id = 'MS-2'
 WHERE workflow_status = 'Annulé' OR stage_id = 'MS-5';

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

-- ---------------------------------------------------------------------
-- 12) Migration module permissions (SQL seed; PHP repair adds role mirrors)
-- ---------------------------------------------------------------------
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
  ('Administrateur', 'opportunity.convert_migration', 1),
  ('Manager', 'page.migrations', 1),
  ('Manager', 'migration.view', 1),
  ('Manager', 'migration.add', 1),
  ('Manager', 'migration.edit', 1),
  ('Manager', 'migration.export', 1),
  ('Manager', 'migration.validate', 1),
  ('Manager', 'opportunity.convert_migration', 1),
  ('AgentSuivi', 'page.migrations', 1),
  ('AgentSuivi', 'migration.view', 1),
  ('AgentSuivi', 'migration.add', 1),
  ('AgentSuivi', 'migration.edit', 1),
  ('AgentSuivi', 'migration.export', 1),
  ('AgentSuivi', 'opportunity.convert_migration', 1),
  ('Backoffice', 'page.migrations', 1),
  ('Backoffice', 'migration.view', 1),
  ('Backoffice', 'migration.edit', 1),
  ('Backoffice', 'migration.export', 1)
ON DUPLICATE KEY UPDATE enabled = GREATEST(enabled, VALUES(enabled));

-- ---------------------------------------------------------------------
-- Done
-- ---------------------------------------------------------------------
INSERT INTO crminternet_schema_migrations (filename, applied_at) VALUES
  ('migration_schema_repair_complete_20260531.sql', NOW())
ON DUPLICATE KEY UPDATE applied_at = VALUES(applied_at);

COMMIT;
