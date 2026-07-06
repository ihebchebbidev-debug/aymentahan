-- Allow contract_info rows on migration dossiers (opportunity → migration conversion).
ALTER TABLE crminternet_contract_info
  MODIFY entity_type ENUM('prospect','opportunity','contract','migration') NOT NULL;
