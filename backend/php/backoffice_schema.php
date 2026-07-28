<?php
require_once __DIR__ . '/schema_repair.php';

function crm_table_columns_backoffice(PDO $db, string $table): array
{
    $out = [];
    try {
        foreach ($db->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`') as $c) {
            $out[$c['Field']] = true;
        }
    } catch (Throwable $e) {
        // missing
    }
    return $out;
}

function ensure_backoffice_objectives_schema(PDO $db): void
{
    $cols = crm_table_columns($db, 'crminternet_backoffice_objectives');
    if (!$cols) {
        $sql = "CREATE TABLE crminternet_backoffice_objectives (
            id VARCHAR(40) NOT NULL,
            scope VARCHAR(16) NOT NULL DEFAULT 'agent',
            agent_id VARCHAR(40) NULL,
            entity_id VARCHAR(40) NULL,
            role_name VARCHAR(80) NULL,
            period_month CHAR(7) NOT NULL,
            target_contracts INT NOT NULL DEFAULT 0,
            target_migrations INT NOT NULL DEFAULT 0,
            working_days INT NOT NULL DEFAULT 26,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_scope_period (scope, agent_id, entity_id, period_month),
            KEY idx_period (period_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        try {
            $db->exec($sql);
        } catch (Throwable $e) {
            // best effort
        }
        return;
    }

    $adds = [
        'scope' => "VARCHAR(16) NOT NULL DEFAULT 'agent'",
        'agent_id' => 'VARCHAR(40) NULL',
        'role_name' => 'VARCHAR(80) NULL',
        'target_contracts' => 'INT NOT NULL DEFAULT 0',
        'target_migrations' => 'INT NOT NULL DEFAULT 0',
        'working_days' => 'INT NOT NULL DEFAULT 26',
        'notes' => 'TEXT NULL',
    ];
    foreach ($adds as $field => $def) {
        if (!isset($cols[$field])) {
            crm_try_alter($db, "ALTER TABLE crminternet_backoffice_objectives ADD COLUMN {$field} {$def}");
        }
    }

    try {
        $db->query("SHOW INDEX FROM crminternet_backoffice_objectives WHERE Key_name='uniq_scope_role_period'");
    } catch (Throwable $e) {
        crm_try_alter($db, "ALTER TABLE crminternet_backoffice_objectives ADD UNIQUE KEY uniq_scope_role_period (scope, role_name, period_month)");
    }
}
