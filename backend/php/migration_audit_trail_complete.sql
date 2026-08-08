-- =====================================================================
-- Migration : traçabilité complète (Synthèse)
--   created_at / created_by / updated_at / updated_by
-- sur prospects, opportunités, contrats et migrations.
-- Idempotente : peut être rejouée sans risque.
-- =====================================================================

DROP PROCEDURE IF EXISTS crm_add_col_audit;
DELIMITER $$
CREATE PROCEDURE crm_add_col_audit(IN tbl VARCHAR(64), IN col VARCHAR(64), IN ddl TEXT)
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = tbl AND column_name = col) = 0 THEN
    SET @sql := CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
    PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
  END IF;
END$$
DELIMITER ;

-- ---------- Prospects ----------
CALL crm_add_col_audit('crminternet_prospects', 'created_at', "`created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
CALL crm_add_col_audit('crminternet_prospects', 'created_by', "`created_by` VARCHAR(80) NULL");
CALL crm_add_col_audit('crminternet_prospects', 'updated_at', "`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
CALL crm_add_col_audit('crminternet_prospects', 'updated_by', "`updated_by` VARCHAR(80) NULL");

-- ---------- Opportunités ----------
CALL crm_add_col_audit('crminternet_opportunities', 'created_at', "`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP");
CALL crm_add_col_audit('crminternet_opportunities', 'created_by', "`created_by` VARCHAR(80) NULL");
CALL crm_add_col_audit('crminternet_opportunities', 'updated_at', "`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
CALL crm_add_col_audit('crminternet_opportunities', 'updated_by', "`updated_by` VARCHAR(80) NULL");

-- ---------- Contrats ----------
CALL crm_add_col_audit('crminternet_contracts', 'created_at', "`created_at` DATETIME NULL");
CALL crm_add_col_audit('crminternet_contracts', 'created_by', "`created_by` VARCHAR(80) NULL");
CALL crm_add_col_audit('crminternet_contracts', 'updated_at', "`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
CALL crm_add_col_audit('crminternet_contracts', 'updated_by', "`updated_by` VARCHAR(80) NULL");

-- ---------- Migrations ----------
CALL crm_add_col_audit('crminternet_migrations', 'created_at', "`created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP");
CALL crm_add_col_audit('crminternet_migrations', 'created_by', "`created_by` VARCHAR(80) NULL");
CALL crm_add_col_audit('crminternet_migrations', 'updated_at', "`updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
CALL crm_add_col_audit('crminternet_migrations', 'updated_by', "`updated_by` VARCHAR(80) NULL");

DROP PROCEDURE IF EXISTS crm_add_col_audit;

-- =====================================================================
-- Backfill des données historiques (best-effort, ne casse rien).
-- =====================================================================

-- Contrat : created_at depuis la date de signature quand absent.
UPDATE crminternet_contracts
   SET created_at = CONCAT(signature_date, ' 00:00:00')
 WHERE created_at IS NULL AND signature_date IS NOT NULL;

-- Contrat : créateur hérité de l'opportunité puis du prospect d'origine.
UPDATE crminternet_contracts c
  LEFT JOIN crminternet_opportunities o ON o.id = c.opportunity_id
  LEFT JOIN crminternet_prospects p ON p.id = COALESCE(c.prospect_id, o.prospect_id)
   SET c.created_by = COALESCE(NULLIF(c.created_by, ''), o.created_by, p.created_by)
 WHERE c.created_by IS NULL OR c.created_by = '';

-- Opportunité : créateur hérité du prospect d'origine.
UPDATE crminternet_opportunities o
  JOIN crminternet_prospects p ON p.id = o.prospect_id
   SET o.created_by = COALESCE(NULLIF(o.created_by, ''), p.created_by)
 WHERE o.created_by IS NULL OR o.created_by = '';

-- Migration : créateur hérité de l'opportunité d'origine.
UPDATE crminternet_migrations m
  LEFT JOIN crminternet_opportunities o ON o.id = m.opportunity_id
   SET m.created_by = COALESCE(NULLIF(m.created_by, ''), o.created_by)
 WHERE m.created_by IS NULL OR m.created_by = '';

-- "Modifié par" par défaut = "Créé par" tant qu'aucune modification n'a eu lieu.
UPDATE crminternet_prospects     SET updated_by = created_by WHERE (updated_by IS NULL OR updated_by = '') AND created_by IS NOT NULL;
UPDATE crminternet_opportunities SET updated_by = created_by WHERE (updated_by IS NULL OR updated_by = '') AND created_by IS NOT NULL;
UPDATE crminternet_contracts     SET updated_by = created_by WHERE (updated_by IS NULL OR updated_by = '') AND created_by IS NOT NULL;
UPDATE crminternet_migrations    SET updated_by = created_by WHERE (updated_by IS NULL OR updated_by = '') AND created_by IS NOT NULL;
