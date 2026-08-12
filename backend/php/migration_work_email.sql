-- ============================================================================
-- Migration : séparation email personnel / email professionnel
--   `email`      = email personnel   → affiché dans le profil, modifiable par l'utilisateur
--   `work_email` = email professionnel → destinataire des codes OTP et mails système,
--                  visible uniquement par le titulaire, les Administrateurs et les
--                  profils ayant la permission `user.view_work_email` (RH, Manager).
--
-- Les DOUBLONS d'adresses (personnelles comme professionnelles) sont AUTORISÉS :
-- aucune contrainte d'unicité n'est posée et les anciennes sont supprimées.
-- MySQL 5.7 / MariaDB compatible. Idempotent via les procédures ci-dessous.
-- ============================================================================

-- 1) Ajouter la colonne work_email si absente ------------------------------
DROP PROCEDURE IF EXISTS crm_add_work_email;
DELIMITER //
CREATE PROCEDURE crm_add_work_email()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'crminternet_users'
       AND COLUMN_NAME  = 'work_email'
  ) THEN
    ALTER TABLE crminternet_users ADD COLUMN work_email VARCHAR(160) NULL AFTER email;
  END IF;
END //
DELIMITER ;
CALL crm_add_work_email();
DROP PROCEDURE crm_add_work_email;

-- 2) Backfill : l'ancien email servait à la connexion + OTP → devient le pro
UPDATE crminternet_users
   SET work_email = email
 WHERE (work_email IS NULL OR work_email = '')
   AND email IS NOT NULL AND email <> ''
   AND email <> 'admin@crminternet.local';

-- 3) Supprimer TOUTE contrainte d'unicité sur email / work_email -----------
--    (doublons autorisés) puis poser des index simples pour la recherche.
DROP PROCEDURE IF EXISTS crm_relax_email_unique;
DELIMITER //
CREATE PROCEDURE crm_relax_email_unique()
BEGIN
  DECLARE done INT DEFAULT 0;
  DECLARE idx  VARCHAR(64);
  DECLARE cur CURSOR FOR
    SELECT DISTINCT INDEX_NAME FROM information_schema.STATISTICS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'crminternet_users'
       AND NON_UNIQUE   = 0
       AND INDEX_NAME  <> 'PRIMARY'
       AND COLUMN_NAME IN ('email', 'work_email');
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = 1;
  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO idx;
    IF done = 1 THEN LEAVE read_loop; END IF;
    SET @s = CONCAT('ALTER TABLE crminternet_users DROP INDEX `', idx, '`');
    PREPARE st FROM @s; EXECUTE st; DEALLOCATE PREPARE st;
  END LOOP;
  CLOSE cur;

  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_users'
                    AND INDEX_NAME = 'idx_users_email') THEN
    ALTER TABLE crminternet_users ADD INDEX idx_users_email (email);
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.STATISTICS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'crminternet_users'
                    AND INDEX_NAME = 'idx_users_work_email') THEN
    ALTER TABLE crminternet_users ADD INDEX idx_users_work_email (work_email);
  END IF;
END //
DELIMITER ;
CALL crm_relax_email_unique();
DROP PROCEDURE crm_relax_email_unique;

-- 4) Permission dédiée : voir / modifier l'email professionnel des autres --
INSERT INTO crminternet_role_permissions (role, permission, enabled) VALUES
  ('Administrateur',   'user.view_work_email', 1),
  ('RessourceHumaine', 'user.view_work_email', 1),
  ('Manager',          'user.view_work_email', 1)
ON DUPLICATE KEY UPDATE enabled = 1;

-- 5) Contrôle -------------------------------------------------------------
-- SELECT username, email, work_email FROM crminternet_users ORDER BY username;
