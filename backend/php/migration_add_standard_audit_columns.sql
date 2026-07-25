-- ============================================================
-- Migration: Add Standard Audit & Customer Columns
-- Adds created_at, created_by, updated_by, debit, and ancien_ligne
-- across crminternet_contracts, crminternet_opportunities,
-- crminternet_migrations, and crminternet_prospects.
-- ============================================================

-- 1. Contracts
ALTER TABLE crminternet_contracts ADD COLUMN IF NOT EXISTS created_at DATETIME NULL;
ALTER TABLE crminternet_contracts ADD COLUMN IF NOT EXISTS created_by VARCHAR(80) NULL;
ALTER TABLE crminternet_contracts ADD COLUMN IF NOT EXISTS updated_by VARCHAR(80) NULL;
ALTER TABLE crminternet_contracts ADD COLUMN IF NOT EXISTS debit INT UNSIGNED NULL;
ALTER TABLE crminternet_contracts ADD COLUMN IF NOT EXISTS ancien_ligne VARCHAR(40) NULL;

-- Backfill created_at from signature_date for existing contracts
UPDATE crminternet_contracts 
SET created_at = CONCAT(signature_date, ' 00:00:00') 
WHERE created_at IS NULL AND signature_date IS NOT NULL;

-- 2. Opportunities
ALTER TABLE crminternet_opportunities ADD COLUMN IF NOT EXISTS updated_by VARCHAR(80) NULL;
ALTER TABLE crminternet_opportunities ADD COLUMN IF NOT EXISTS debit INT UNSIGNED NULL;
ALTER TABLE crminternet_opportunities ADD COLUMN IF NOT EXISTS ancien_ligne VARCHAR(40) NULL;

-- 3. Migrations
ALTER TABLE crminternet_migrations ADD COLUMN IF NOT EXISTS updated_by VARCHAR(80) NULL;
ALTER TABLE crminternet_migrations ADD COLUMN IF NOT EXISTS debit INT UNSIGNED NULL;

-- 4. Prospects
ALTER TABLE crminternet_prospects ADD COLUMN IF NOT EXISTS updated_by VARCHAR(80) NULL;
ALTER TABLE crminternet_prospects ADD COLUMN IF NOT EXISTS debit INT UNSIGNED NULL;
