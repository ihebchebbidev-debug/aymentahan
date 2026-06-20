<?php
require_once __DIR__ . '/config.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}

$dry = ($_GET['dry'] ?? '') === '1';
$db = (new Database())->getConnection();

$report = [
    'dry_run' => $dry,
    'actions' => [],
    'errors' => [],
];

try {
    $exists = (bool)$db->query("SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'crminternet_audit_log'
        LIMIT 1")->fetchColumn();

    if (!$exists) {
        $sql = "CREATE TABLE crminternet_audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL,
            user_username VARCHAR(80) NULL,
            user_role VARCHAR(40) NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(40) NULL,
            entity_id VARCHAR(80) NULL,
            method VARCHAR(10) NULL,
            path VARCHAR(255) NULL,
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            status_code SMALLINT NULL,
            details TEXT NULL,
            INDEX idx_created (created_at),
            INDEX idx_user (user_username),
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

        if ($dry) {
            $report['actions'][] = '[DRY] create crminternet_audit_log';
        } else {
            $db->exec($sql);
            $report['actions'][] = 'created crminternet_audit_log';
        }
    } else {
        $col = $db->query("SELECT DATA_TYPE, IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'crminternet_audit_log'
              AND COLUMN_NAME = 'created_at'")->fetch(PDO::FETCH_ASSOC);

        if (!$col) {
            if ($dry) {
                $report['actions'][] = '[DRY] add created_at column';
            } else {
                $db->exec("ALTER TABLE crminternet_audit_log ADD COLUMN created_at DATETIME NULL AFTER id");
                $report['actions'][] = 'added created_at column';
            }
            $col = ['DATA_TYPE' => 'datetime', 'IS_NULLABLE' => 'YES'];
        }

        if ($dry) {
            $report['actions'][] = '[DRY] backfill NULL/zero created_at values';
        } else {
            $db->exec("UPDATE crminternet_audit_log
                SET created_at = NOW()
                WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");
            $report['actions'][] = 'backfilled invalid created_at values';
        }

        if (strtolower((string)($col['DATA_TYPE'] ?? '')) !== 'datetime'
            || (string)($col['IS_NULLABLE'] ?? '') === 'YES') {
            if ($dry) {
                $report['actions'][] = '[DRY] modify created_at to DATETIME NOT NULL';
            } else {
                $db->exec("ALTER TABLE crminternet_audit_log MODIFY COLUMN created_at DATETIME NOT NULL");
                $report['actions'][] = 'modified created_at to DATETIME NOT NULL';
            }
        }
    }
} catch (Throwable $e) {
    $report['errors'][] = $e->getMessage();
}

ok([
    'ok' => empty($report['errors']),
    'report' => $report,
]);