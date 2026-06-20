<?php
/**
 * Guichet module schema — repairs legacy setup.php tables to match install.sql / app code.
 * No DEFAULT CURRENT_TIMESTAMP on DATETIME (MySQL 5.7 / OVH safe).
 */

require_once __DIR__ . '/schema_repair.php';

/** @return array<string, true> */
function crm_table_columns(PDO $db, string $table): array
{
    $out = [];
    try {
        foreach ($db->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`') as $c) {
            $out[$c['Field']] = true;
        }
    } catch (Throwable $e) {
        /* table missing */
    }
    return $out;
}

function crm_table_row_count(PDO $db, string $table): int
{
    try {
        return (int) $db->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function crm_guichet_fk_checks_off(PDO $db): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 0');
}

function crm_guichet_fk_checks_on(PDO $db): void
{
    $db->exec('SET FOREIGN_KEY_CHECKS = 1');
}

/** Run DDL that may conflict with legacy setup.php foreign keys (shared hosting). */
function crm_guichet_exec_ddl(PDO $db, callable $fn): void
{
    crm_guichet_fk_checks_off($db);
    try {
        $fn();
    } finally {
        crm_guichet_fk_checks_on($db);
    }
}

/** Drop all FK constraints on a table that reference another CRM table. */
function crm_guichet_drop_foreign_keys_to(PDO $db, string $fromTable, string $referencedTable): void
{
    try {
        $st = $db->prepare(
            'SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
               AND REFERENCED_TABLE_NAME = :ref
               AND CONSTRAINT_NAME IS NOT NULL'
        );
        $st->execute([':t' => $fromTable, ':ref' => $referencedTable]);
        foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $name) {
            if ($name === '') {
                continue;
            }
            crm_try_alter($db, "ALTER TABLE `{$fromTable}` DROP FOREIGN KEY `{$name}`");
        }
    } catch (Throwable $e) {
        /* information_schema unavailable — best effort */
    }
}

function crm_guichet_drop_if_empty_legacy(PDO $db, string $table, callable $isLegacy): void
{
    $cols = crm_table_columns($db, $table);
    if (!$cols || !$isLegacy($cols)) {
        return;
    }
    if (crm_table_row_count($db, $table) > 0) {
        return;
    }
    crm_guichet_exec_ddl($db, function () use ($db, $table) {
        if ($table === 'crminternet_guichet_dossiers') {
            if (crm_table_row_count($db, 'crminternet_guichet_entries') === 0) {
                $db->exec('DROP TABLE IF EXISTS crminternet_guichet_entries');
            } else {
                crm_guichet_drop_foreign_keys_to($db, 'crminternet_guichet_entries', 'crminternet_guichet_dossiers');
            }
        }
        $db->exec("DROP TABLE IF EXISTS `{$table}`");
    });
}

/** Before CREATE dossiers: remove orphan FKs from setup.php (entries → dossiers). */
function crm_guichet_prepare_dossiers_table(PDO $db): void
{
    if (crm_table_columns($db, 'crminternet_guichet_dossiers')) {
        return;
    }
    $entriesCols = crm_table_columns($db, 'crminternet_guichet_entries');
    if (!$entriesCols) {
        return;
    }
    if (crm_table_row_count($db, 'crminternet_guichet_entries') === 0) {
        crm_guichet_exec_ddl($db, function () use ($db) {
            $db->exec('DROP TABLE IF EXISTS crminternet_guichet_entries');
        });
        return;
    }
    crm_guichet_drop_foreign_keys_to($db, 'crminternet_guichet_entries', 'crminternet_guichet_dossiers');
}

function ensure_users_guichet_column(PDO $db): void
{
    crm_try_alter($db, 'ALTER TABLE crminternet_users ADD COLUMN guichet_entity_id VARCHAR(40) NULL');
    crm_try_alter($db, 'ALTER TABLE crminternet_users ADD INDEX idx_users_guichet_entity (guichet_entity_id)');
}

function ensure_guichet_entities_schema(PDO $db): void
{
    $cols = crm_table_columns($db, 'crminternet_guichet_entities');
    if (!$cols) {
        $sql = "CREATE TABLE crminternet_guichet_entities (
            id VARCHAR(40) NOT NULL,
            name VARCHAR(120) NOT NULL,
            type ENUM('ttshop','franchise','autre') NOT NULL DEFAULT 'ttshop',
            city VARCHAR(120) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        crm_guichet_exec_ddl($db, function () use ($db, $sql) {
            $db->exec($sql);
        });
        return;
    }

    crm_try_alter(
        $db,
        "ALTER TABLE crminternet_guichet_entities
         ADD COLUMN type ENUM('ttshop','franchise','autre') NOT NULL DEFAULT 'ttshop'"
    );
    crm_try_alter($db, 'ALTER TABLE crminternet_guichet_entities ADD COLUMN city VARCHAR(120) NULL');
    crm_try_alter(
        $db,
        'ALTER TABLE crminternet_guichet_entities ADD COLUMN active TINYINT(1) NOT NULL DEFAULT 1'
    );
    if (isset($cols['created_at'])) {
        crm_try_alter($db, 'ALTER TABLE crminternet_guichet_entities MODIFY COLUMN created_at DATETIME NOT NULL');
    } else {
        crm_try_alter($db, 'ALTER TABLE crminternet_guichet_entities ADD COLUMN created_at DATETIME NOT NULL');
    }
    crm_try_alter($db, 'ALTER TABLE crminternet_guichet_entities MODIFY COLUMN name VARCHAR(120) NOT NULL');
}

function ensure_guichet_dossiers_schema(PDO $db): void
{
    crm_guichet_drop_if_empty_legacy($db, 'crminternet_guichet_dossiers', function (array $cols) {
        return isset($cols['reference']) && !isset($cols['ref']);
    });

    $cols = crm_table_columns($db, 'crminternet_guichet_dossiers');
    if (!$cols) {
        crm_guichet_prepare_dossiers_table($db);
        $createDossiers = "CREATE TABLE crminternet_guichet_dossiers (
            id VARCHAR(40) NOT NULL,
            ref VARCHAR(20) NOT NULL,
            entity_id VARCHAR(40) NOT NULL,
            agent_id VARCHAR(40) NOT NULL,
            client_name VARCHAR(160) NULL,
            client_cin VARCHAR(20) NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            validated_at DATETIME NULL,
            validated_by VARCHAR(40) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_gd_ref (ref),
            KEY idx_gd_entity (entity_id),
            KEY idx_gd_agent (agent_id),
            KEY idx_gd_status_date (status, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        crm_guichet_exec_ddl($db, function () use ($db, $createDossiers) {
            $db->exec($createDossiers);
        });
        return;
    }

    if (isset($cols['reference']) && !isset($cols['ref'])) {
        crm_try_alter(
            $db,
            'ALTER TABLE crminternet_guichet_dossiers CHANGE COLUMN reference ref VARCHAR(20) NOT NULL'
        );
    } elseif (!isset($cols['ref'])) {
        crm_try_alter($db, 'ALTER TABLE crminternet_guichet_dossiers ADD COLUMN ref VARCHAR(20) NOT NULL DEFAULT \'\'');
    }
    crm_try_alter($db, 'ALTER TABLE crminternet_guichet_dossiers ADD COLUMN validated_at DATETIME NULL');
    crm_try_alter($db, 'ALTER TABLE crminternet_guichet_dossiers ADD COLUMN validated_by VARCHAR(40) NULL');
    crm_try_alter($db, "ALTER TABLE crminternet_guichet_dossiers MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'draft'");
    try {
        $db->exec("UPDATE crminternet_guichet_dossiers SET status = 'draft'
            WHERE status IN ('open', 'pending', '') OR status IS NULL");
        $db->exec("UPDATE crminternet_guichet_dossiers SET status = 'valide' WHERE status IN ('closed', 'validated')");
    } catch (Throwable $e) {
        /* ignore */
    }
    if (!isset($cols['updated_at'])) {
        crm_try_alter($db, 'ALTER TABLE crminternet_guichet_dossiers ADD COLUMN updated_at DATETIME NOT NULL');
        try {
            $db->exec('UPDATE crminternet_guichet_dossiers SET updated_at = created_at WHERE updated_at IS NULL');
        } catch (Throwable $e) {
            /* ignore */
        }
    }
}

function ensure_guichet_entries_schema(PDO $db): void
{
    ensure_guichet_dossiers_schema($db);

    crm_guichet_drop_if_empty_legacy($db, 'crminternet_guichet_entries', function (array $cols) {
        return isset($cols['entry_type']) && !isset($cols['type']);
    });

    $cols = crm_table_columns($db, 'crminternet_guichet_entries');
    if (!$cols) {
        if (!crm_table_columns($db, 'crminternet_guichet_dossiers')) {
            ensure_guichet_dossiers_schema($db);
        }
        $createEntries = "CREATE TABLE crminternet_guichet_entries (
            id VARCHAR(40) NOT NULL,
            dossier_id VARCHAR(40) NOT NULL,
            type VARCHAR(32) NOT NULL,
            cin VARCHAR(20) NULL,
            numero VARCHAR(40) NULL,
            amount DECIMAL(12,3) NULL,
            offre VARCHAR(60) NULL,
            operator_source VARCHAR(60) NULL,
            label VARCHAR(160) NULL,
            op_date DATE NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_ge_type_status (type, status),
            KEY idx_ge_dossier (dossier_id),
            KEY idx_ge_op_date (op_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        crm_guichet_exec_ddl($db, function () use ($db, $createEntries) {
            $db->exec($createEntries);
        });
        return;
    }

    $adds = [
        'dossier_id' => 'VARCHAR(40) NOT NULL',
        'type' => "VARCHAR(32) NOT NULL DEFAULT 'divers'",
        'cin' => 'VARCHAR(20) NULL',
        'numero' => 'VARCHAR(40) NULL',
        'amount' => 'DECIMAL(12,3) NULL',
        'offre' => 'VARCHAR(60) NULL',
        'operator_source' => 'VARCHAR(60) NULL',
        'label' => 'VARCHAR(160) NULL',
        'op_date' => 'DATE NULL',
        'status' => "VARCHAR(32) NOT NULL DEFAULT 'draft'",
        'created_at' => 'DATETIME NOT NULL',
        'updated_at' => 'DATETIME NOT NULL',
    ];
    foreach ($adds as $field => $def) {
        if (!isset($cols[$field])) {
            crm_try_alter($db, "ALTER TABLE crminternet_guichet_entries ADD COLUMN {$field} {$def}");
        }
    }
}

function ensure_guichet_objectives_schema(PDO $db): void
{
    crm_guichet_drop_if_empty_legacy($db, 'crminternet_guichet_objectives', function (array $cols) {
        return isset($cols['objective_target']) && !isset($cols['target_sim']);
    });

    $cols = crm_table_columns($db, 'crminternet_guichet_objectives');
    if (!$cols) {
        $sql = "CREATE TABLE crminternet_guichet_objectives (
            id VARCHAR(40) NOT NULL,
            scope VARCHAR(16) NOT NULL DEFAULT 'agent',
            agent_id VARCHAR(40) NULL,
            entity_id VARCHAR(40) NULL,
            period_month CHAR(7) NOT NULL,
            target_sim INT NOT NULL DEFAULT 900,
            target_port INT NOT NULL DEFAULT 90,
            target_fancy INT NOT NULL DEFAULT 90,
            target_contracts_daily INT NOT NULL DEFAULT 25,
            target_contracts_monthly INT NOT NULL DEFAULT 650,
            working_days INT NOT NULL DEFAULT 26,
            budget_monthly_dt DECIMAL(10,2) NULL,
            budget_daily_dt DECIMAL(10,2) NULL,
            min_activation_pct DECIMAL(5,2) NOT NULL DEFAULT 25.00,
            challenge_bonus_dt DECIMAL(8,2) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_scope_period (scope, agent_id, entity_id, period_month),
            KEY idx_period (period_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        crm_guichet_exec_ddl($db, function () use ($db, $sql) {
            $db->exec($sql);
        });
        return;
    }

    $adds = [
        'scope' => "VARCHAR(16) NOT NULL DEFAULT 'entity'",
        'agent_id' => 'VARCHAR(40) NULL',
        'target_sim' => 'INT NOT NULL DEFAULT 900',
        'target_port' => 'INT NOT NULL DEFAULT 90',
        'target_fancy' => 'INT NOT NULL DEFAULT 90',
        'target_contracts_daily' => 'INT NOT NULL DEFAULT 25',
        'target_contracts_monthly' => 'INT NOT NULL DEFAULT 650',
        'working_days' => 'INT NOT NULL DEFAULT 26',
        'budget_monthly_dt' => 'DECIMAL(10,2) NULL',
        'budget_daily_dt' => 'DECIMAL(10,2) NULL',
        'min_activation_pct' => 'DECIMAL(5,2) NOT NULL DEFAULT 25.00',
        'challenge_bonus_dt' => 'DECIMAL(8,2) NULL',
        'notes' => 'TEXT NULL',
    ];
    foreach ($adds as $field => $def) {
        if (!isset($cols[$field])) {
            crm_try_alter($db, "ALTER TABLE crminternet_guichet_objectives ADD COLUMN {$field} {$def}");
        }
    }
    if (isset($cols['objective_budget']) && !isset($cols['budget_monthly_dt'])) {
        try {
            $db->exec('UPDATE crminternet_guichet_objectives SET budget_monthly_dt = objective_budget
                WHERE budget_monthly_dt IS NULL AND objective_budget IS NOT NULL');
        } catch (Throwable $e) {
            /* ignore */
        }
    }
}

/** Repair all guichet tables + users.guichet_entity_id. */
function ensure_guichet_schema(PDO $db): void
{
    try {
        ensure_users_guichet_column($db);
        ensure_guichet_entities_schema($db);
        ensure_guichet_dossiers_schema($db);
        ensure_guichet_entries_schema($db);
        ensure_guichet_objectives_schema($db);
    } catch (Throwable $e) {
        crm_guichet_fk_checks_on($db);
        throw $e;
    }
}

/**
 * @return array<string, int|string>
 */
function guichet_schema_status(PDO $db): array
{
    $status = [];
    foreach (
        [
            'crminternet_guichet_entities',
            'crminternet_guichet_dossiers',
            'crminternet_guichet_entries',
            'crminternet_guichet_objectives',
        ] as $table
    ) {
        $cols = crm_table_columns($db, $table);
        $status[$table] = [
            'exists' => (bool) $cols,
            'rows' => $cols ? crm_table_row_count($db, $table) : 0,
            'columns' => array_keys($cols),
        ];
    }
    $u = crm_table_columns($db, 'crminternet_users');
    $status['users_guichet_entity_id'] = isset($u['guichet_entity_id']);

    return $status;
}
