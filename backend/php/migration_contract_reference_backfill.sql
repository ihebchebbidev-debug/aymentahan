-- One-time fix: legacy crminternet_contracts.reference is UNIQUE NOT NULL but
-- conversions left it as empty string, blocking the second contract insert.
-- Safe to run multiple times.

UPDATE crminternet_contracts
   SET reference = id
 WHERE reference = '' OR reference IS NULL;
