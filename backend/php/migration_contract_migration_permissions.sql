-- =====================================================================
-- Add missing field-level permissions for contract/migration and preserve
-- opportunity assign/type permissions.
-- Idempotent migration for production databases.
-- =====================================================================

SET NAMES utf8mb4;

-- Ensure Administrateur explicitly has the new permissions.
INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled) VALUES
  ('Administrateur','contract.assign',1),
  ('Administrateur','contract.type',1),
  ('Administrateur','migration.assign',1),
  ('Administrateur','migration.type',1),
  ('Administrateur','opportunity.assign_prospect',1),
  ('Administrateur','opportunity.change_prospect_type',1),
  ('Administrateur','opportunity.convert_migration',1);

-- Grant contract field permissions to all roles that already can edit contracts.
INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled)
SELECT DISTINCT role, 'contract.assign', 1
FROM crminternet_role_permissions
WHERE permission = 'contract.edit' AND enabled = 1;

INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled)
SELECT DISTINCT role, 'contract.type', 1
FROM crminternet_role_permissions
WHERE permission = 'contract.edit' AND enabled = 1;

-- Grant migration field permissions to roles that already have contract module access
-- or explicit migration edit rights.
INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled)
SELECT DISTINCT role, 'migration.assign', 1
FROM crminternet_role_permissions
WHERE permission IN ('page.contracts', 'contract.view', 'migration.edit') AND enabled = 1;

INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled)
SELECT DISTINCT role, 'migration.type', 1
FROM crminternet_role_permissions
WHERE permission IN ('page.contracts', 'contract.view', 'migration.edit') AND enabled = 1;

-- Preserve opportunity assign/type permissions for roles that can edit opportunities.
INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled)
SELECT DISTINCT role, 'opportunity.assign_prospect', 1
FROM crminternet_role_permissions
WHERE permission = 'opportunity.edit' AND enabled = 1;

INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled)
SELECT DISTINCT role, 'opportunity.change_prospect_type', 1
FROM crminternet_role_permissions
WHERE permission = 'opportunity.edit' AND enabled = 1;

-- Mirror conversion permission for roles that already have opportunity conversion.
INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled)
SELECT DISTINCT role, 'opportunity.convert_migration', 1
FROM crminternet_role_permissions
WHERE permission = 'opportunity.convert' AND enabled = 1;
