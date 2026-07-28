-- Ensure chat conversation and member constraints + useful indexes
-- Run this once (idempotent where possible).

-- Ensure conversations table exists (safety)
CREATE TABLE IF NOT EXISTS crminternet_chat_conversations (
  id VARCHAR(40) NOT NULL PRIMARY KEY,
  type ENUM('dm','group','broadcast') NOT NULL DEFAULT 'group',
  name VARCHAR(255) NULL,
  created_by VARCHAR(80) NULL,
  created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  last_message_at TIMESTAMP(3) NULL,
  post_policy ENUM('all','admins') NOT NULL DEFAULT 'all'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure members table exists
CREATE TABLE IF NOT EXISTS crminternet_chat_members (
  conversation_id VARCHAR(40) NOT NULL,
  user_username VARCHAR(80) NOT NULL,
  role ENUM('admin','member') NOT NULL DEFAULT 'member',
  muted TINYINT(1) NOT NULL DEFAULT 0,
  hidden TINYINT(1) NOT NULL DEFAULT 0,
  last_read_at TIMESTAMP(3) NULL,
  PRIMARY KEY (conversation_id, user_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Ensure messages table exists
CREATE TABLE IF NOT EXISTS crminternet_chat_messages (
  id VARCHAR(40) NOT NULL PRIMARY KEY,
  conversation_id VARCHAR(40) NOT NULL,
  sender_username VARCHAR(80) NULL,
  body TEXT NULL,
  is_system TINYINT(1) NULL DEFAULT 0,
  created_at TIMESTAMP(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  attachment_id VARCHAR(40) NULL,
  attachment_filename VARCHAR(255) NULL,
  attachment_mime VARCHAR(120) NULL,
  attachment_size INT NULL,
  INDEX idx_conv_created_at (conversation_id, created_at),
  INDEX idx_sender (sender_username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Useful indexes
ALTER TABLE crminternet_chat_members ADD INDEX IF NOT EXISTS idx_member_user (user_username);
ALTER TABLE crminternet_chat_conversations ADD INDEX IF NOT EXISTS idx_last_message_at (last_message_at);
ALTER TABLE crminternet_chat_messages ADD INDEX IF NOT EXISTS idx_attachment_id (attachment_id);

-- Note: Some MySQL versions may not support CREATE INDEX IF NOT EXISTS. Run these statements manually if necessary.
