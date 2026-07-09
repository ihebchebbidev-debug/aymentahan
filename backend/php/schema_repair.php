<?php
/**
 * Idempotent column/table repairs for installs created by an older setup.php.
 * MySQL 5.7 / MariaDB: no DEFAULT CURRENT_TIMESTAMP on multiple DATETIME columns.
 */

function crm_try_alter(PDO $db, string $sql): void {
    try {
        $db->exec($sql);
    } catch (Throwable $e) {
        // column exists or incompatible — ignore
    }
}

function ensure_settings_schema(PDO $db): void {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS crminternet_settings (
            scope VARCHAR(80) NOT NULL DEFAULT 'global',
            setting_key VARCHAR(120) NOT NULL,
            value LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (scope, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        crm_try_alter($db, "ALTER TABLE crminternet_settings ADD COLUMN value LONGTEXT NOT NULL");
        crm_try_alter($db, "ALTER TABLE crminternet_settings CHANGE COLUMN setting_value value LONGTEXT NOT NULL");
        crm_try_alter($db, "ALTER TABLE crminternet_settings CHANGE COLUMN `key` value LONGTEXT NOT NULL");
    } catch (Throwable $e) {}
}

function ensure_reports_schema(PDO $db): void {
    $prospectCols = [
        "ALTER TABLE crminternet_prospects ADD COLUMN civility ENUM('M','Mme') NOT NULL DEFAULT 'M'",
        "ALTER TABLE crminternet_prospects ADD COLUMN last_name VARCHAR(120) NOT NULL DEFAULT ''",
        "ALTER TABLE crminternet_prospects ADD COLUMN first_name VARCHAR(120) NOT NULL DEFAULT ''",
        "ALTER TABLE crminternet_prospects ADD COLUMN assigned_to VARCHAR(80) NULL",
        "ALTER TABLE crminternet_prospects ADD COLUMN outcome ENUM('pending','won','lost') NOT NULL DEFAULT 'pending'",
        "ALTER TABLE crminternet_prospects ADD COLUMN source VARCHAR(80) NOT NULL DEFAULT 'Terrain'",
        "ALTER TABLE crminternet_prospects ADD COLUMN city VARCHAR(120) NOT NULL DEFAULT ''",
        "ALTER TABLE crminternet_prospects ADD COLUMN zone VARCHAR(120) NOT NULL DEFAULT ''",
        "ALTER TABLE crminternet_prospects CHANGE COLUMN nom last_name VARCHAR(120) NOT NULL",
        "ALTER TABLE crminternet_prospects CHANGE COLUMN agent_id assigned_to VARCHAR(80) NULL",
    ];
    $contractCols = [
        "ALTER TABLE crminternet_contracts ADD COLUMN signature_date DATE NULL",
        "ALTER TABLE crminternet_contracts ADD COLUMN premium DECIMAL(10,2) NOT NULL DEFAULT 0",
        "ALTER TABLE crminternet_contracts ADD COLUMN assigned_to VARCHAR(80) NOT NULL DEFAULT ''",
        "ALTER TABLE crminternet_contracts ADD COLUMN partner VARCHAR(80) NOT NULL DEFAULT ''",
        "ALTER TABLE crminternet_contracts ADD COLUMN cabinet VARCHAR(120) NOT NULL DEFAULT ''",
        "ALTER TABLE crminternet_contracts ADD COLUMN effective_date DATE NULL",
        "ALTER TABLE crminternet_contracts ADD COLUMN billing_status VARCHAR(80) NOT NULL DEFAULT 'Pré-validé'",
        "ALTER TABLE crminternet_contracts CHANGE COLUMN owner_id assigned_to VARCHAR(80) NOT NULL DEFAULT ''",
        "ALTER TABLE crminternet_contracts CHANGE COLUMN amount premium DECIMAL(10,2) NOT NULL DEFAULT 0",
        "ALTER TABLE crminternet_contracts CHANGE COLUMN start_date signature_date DATE NULL",
    ];
    foreach (array_merge($prospectCols, $contractCols) as $sql) {
        crm_try_alter($db, $sql);
    }
}

/**
 * crminternet_login_otp: older setup.php used username + otp_code;
 * auth_login / auth_otp_* expect user_id + code_hash + attempts + used.
 */
function ensure_login_otp_schema(PDO $db): void {
    $hasTable = false;
    $cols = [];
    try {
        $rows = $db->query('SHOW COLUMNS FROM crminternet_login_otp')->fetchAll(PDO::FETCH_ASSOC);
        $hasTable = count($rows) > 0;
        foreach ($rows as $r) {
            $cols[$r['Field']] = true;
        }
    } catch (Throwable $e) {
        $hasTable = false;
    }

    // No DEFAULT CURRENT_TIMESTAMP on DATETIME — breaks on MySQL 5.7 / some MariaDB hosts.
    $create = "CREATE TABLE IF NOT EXISTS crminternet_login_otp (
        challenge   VARCHAR(40)  PRIMARY KEY,
        user_id     VARCHAR(40)  NOT NULL,
        code_hash   VARCHAR(255) NOT NULL,
        expires_at  DATETIME     NOT NULL,
        attempts    TINYINT      NOT NULL DEFAULT 0,
        used        TINYINT      NOT NULL DEFAULT 0,
        created_at  DATETIME     NOT NULL,
        INDEX idx_user (user_id),
        INDEX idx_expires (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$hasTable) {
        $db->exec($create);
        return;
    }

    // Legacy schema from setup.php (username, otp_code) — drop & recreate (OTP rows are ephemeral).
    if (isset($cols['username']) && !isset($cols['user_id'])) {
        try {
            $db->exec('DROP TABLE crminternet_login_otp');
        } catch (Throwable $e) {
            /* ignore */
        }
        $db->exec($create);
        return;
    }

    crm_try_alter($db, 'ALTER TABLE crminternet_login_otp ADD COLUMN user_id VARCHAR(40) NULL');
    crm_try_alter($db, 'ALTER TABLE crminternet_login_otp ADD COLUMN code_hash VARCHAR(255) NULL');
    crm_try_alter($db, 'ALTER TABLE crminternet_login_otp ADD COLUMN attempts TINYINT NOT NULL DEFAULT 0');
    crm_try_alter($db, 'ALTER TABLE crminternet_login_otp ADD COLUMN used TINYINT NOT NULL DEFAULT 0');
    crm_try_alter($db, 'ALTER TABLE crminternet_login_otp ADD COLUMN created_at DATETIME NOT NULL');

    try {
        if (isset($cols['username'])) {
            $db->exec('UPDATE crminternet_login_otp o
                INNER JOIN crminternet_users u ON u.username = o.username
                SET o.user_id = u.id
                WHERE o.user_id IS NULL OR o.user_id = ""');
        }
        if (isset($cols['otp_code'])) {
            $db->exec('UPDATE crminternet_login_otp
                SET code_hash = otp_code
                WHERE (code_hash IS NULL OR code_hash = "") AND otp_code IS NOT NULL AND otp_code <> ""');
        }
    } catch (Throwable $e) {
        /* best-effort */
    }

    crm_try_alter($db, 'ALTER TABLE crminternet_login_otp MODIFY COLUMN user_id VARCHAR(40) NOT NULL');
    crm_try_alter($db, 'ALTER TABLE crminternet_login_otp MODIFY COLUMN code_hash VARCHAR(255) NOT NULL');
}

/**
 * Tasks table: no DEFAULT CURRENT_TIMESTAMP on DATETIME (MySQL 5.7 / OVH).
 * Migrates legacy setup.php columns (entity_type → related_entity, etc.).
 */
function ensure_tasks_schema(PDO $db): void
{
    $cols = [];
    $hasTable = false;
    try {
        foreach ($db->query('SHOW COLUMNS FROM crminternet_tasks') as $c) {
            $hasTable = true;
            $cols[$c['Field']] = $c;
        }
    } catch (Throwable $e) {
        $hasTable = false;
    }

    $create = "CREATE TABLE IF NOT EXISTS crminternet_tasks (
        id              VARCHAR(40)  NOT NULL,
        title           VARCHAR(200) NOT NULL,
        description     TEXT         NULL,
        assigned_to     VARCHAR(80)  NOT NULL DEFAULT '',
        related_entity  VARCHAR(20)  NULL,
        related_id      VARCHAR(40)  NULL,
        due_date        DATE         NULL,
        priority        VARCHAR(20)  NOT NULL DEFAULT 'normal',
        status          VARCHAR(32)  NOT NULL DEFAULT 'todo',
        created_by      VARCHAR(80)  NOT NULL DEFAULT '',
        created_at      DATETIME     NOT NULL,
        completed_at    DATETIME     NULL,
        PRIMARY KEY (id),
        KEY idx_assigned (assigned_to, status),
        KEY idx_due (due_date),
        KEY idx_tasks_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    if (!$hasTable) {
        try {
            $db->exec($create);
        } catch (Throwable $e) {
            /* ignore */
        }
        return;
    }

    foreach (['created_at', 'updated_at', 'completed_at'] as $col) {
        if (!isset($cols[$col])) {
            continue;
        }
        $null = $col === 'completed_at' ? 'NULL' : 'NOT NULL';
        crm_try_alter($db, "ALTER TABLE crminternet_tasks MODIFY COLUMN {$col} DATETIME {$null}");
    }

    crm_try_alter($db, 'ALTER TABLE crminternet_tasks ADD COLUMN related_entity VARCHAR(20) NULL');
    crm_try_alter($db, 'ALTER TABLE crminternet_tasks ADD COLUMN related_id VARCHAR(40) NULL');
    crm_try_alter($db, 'ALTER TABLE crminternet_tasks ADD COLUMN created_by VARCHAR(80) NOT NULL DEFAULT \'\'');
    crm_try_alter($db, 'ALTER TABLE crminternet_tasks ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT \'normal\'');
    crm_try_alter($db, 'ALTER TABLE crminternet_tasks CHANGE COLUMN entity_type related_entity VARCHAR(20) NULL');
    crm_try_alter($db, 'ALTER TABLE crminternet_tasks CHANGE COLUMN entity_id related_id VARCHAR(40) NULL');

    try {
        if (isset($cols['entity_type']) && isset($cols['related_entity'])) {
            $db->exec("UPDATE crminternet_tasks SET related_entity = entity_type
                WHERE (related_entity IS NULL OR related_entity = '') AND entity_type IS NOT NULL AND entity_type <> ''");
        }
        if (isset($cols['entity_id']) && isset($cols['related_id'])) {
            $db->exec("UPDATE crminternet_tasks SET related_id = entity_id
                WHERE (related_id IS NULL OR related_id = '') AND entity_id IS NOT NULL AND entity_id <> ''");
        }
        if (isset($cols['assigned_to'])) {
            $db->exec("UPDATE crminternet_tasks SET created_by = assigned_to
                WHERE (created_by IS NULL OR created_by = '') AND assigned_to IS NOT NULL AND assigned_to <> ''");
        }
        $db->exec("UPDATE crminternet_tasks SET status = 'todo' WHERE status IN ('pending', '') OR status IS NULL");
        $db->exec("UPDATE crminternet_tasks SET priority = 'normal'
            WHERE priority IS NULL OR priority = '' OR priority = '0'");
    } catch (Throwable $e) {
        /* best-effort */
    }

    crm_try_alter(
        $db,
        "ALTER TABLE crminternet_tasks MODIFY COLUMN status VARCHAR(32) NOT NULL DEFAULT 'todo'"
    );
    crm_try_alter(
        $db,
        "ALTER TABLE crminternet_tasks MODIFY COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'normal'"
    );
    crm_try_alter(
        $db,
        "ALTER TABLE crminternet_tasks MODIFY COLUMN assigned_to VARCHAR(80) NOT NULL DEFAULT ''"
    );
}

function ensure_notifications_schema(PDO $db): void
{
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS crminternet_notifications (
            id VARCHAR(40) PRIMARY KEY,
            user_username VARCHAR(80) NOT NULL,
            title VARCHAR(200) NOT NULL,
            body TEXT NULL,
            link VARCHAR(500) NULL,
            read_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_user_read (user_username, read_at),
            INDEX idx_created (created_at),
            INDEX idx_user_created (user_username, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $repair = [
            'ALTER TABLE crminternet_notifications ADD COLUMN body TEXT NULL',
            'ALTER TABLE crminternet_notifications ADD COLUMN link VARCHAR(500) NULL',
            'ALTER TABLE crminternet_notifications ADD COLUMN read_at DATETIME NULL',
            'ALTER TABLE crminternet_notifications CHANGE COLUMN message body TEXT NULL',
        ];
        foreach ($repair as $sql) {
            crm_try_alter($db, $sql);
        }
        crm_try_alter($db, 'ALTER TABLE crminternet_notifications MODIFY COLUMN created_at DATETIME NOT NULL');
    } catch (Throwable $e) {
        /* ignore */
    }
}

function ensure_user_permission_overrides_schema(PDO $db): void {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS crminternet_user_permission_overrides (
            user_username VARCHAR(80) NOT NULL,
            permission VARCHAR(80) NOT NULL,
            effect ENUM('allow','deny') NOT NULL,
            updated_by VARCHAR(80) NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_username, permission),
            INDEX idx_user (user_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        crm_try_alter($db, "ALTER TABLE crminternet_user_permission_overrides ADD COLUMN effect ENUM('allow','deny') NOT NULL DEFAULT 'allow'");
        crm_try_alter($db, "ALTER TABLE crminternet_user_permission_overrides ADD COLUMN updated_by VARCHAR(80) NULL");
        crm_try_alter($db, "ALTER TABLE crminternet_user_permission_overrides ADD COLUMN updated_at DATETIME NOT NULL");
        crm_try_alter($db, "ALTER TABLE crminternet_user_permission_overrides CHANGE COLUMN override_value effect ENUM('allow','deny') NOT NULL");
    } catch (Throwable $e) {}
}

/**
 * Legacy setup.php used entity_type / field_name / field_label / field_type.
 * Current API expects entity / field_key / label / type (see custom_fields.php).
 */
function crm_norm_custom_field_entity(?string $raw): string
{
    $v = strtolower(trim((string)($raw ?? '')));
    $map = [
        'prospect' => 'prospect', 'lead' => 'prospect', 'leads' => 'prospect',
        'contract' => 'contract', 'contrat' => 'contract', 'contrats' => 'contract',
        'opportunity' => 'opportunity', 'opportunite' => 'opportunity', 'opportunité' => 'opportunity',
        'user' => 'user', 'utilisateur' => 'user', 'users' => 'user',
        'migration' => 'migration', 'migrations' => 'migration',
    ];
    return $map[$v] ?? $v;
}

function ensure_custom_fields_schema(PDO $db): void
{
    $createFields = "CREATE TABLE IF NOT EXISTS crminternet_custom_fields (
        id VARCHAR(40) NOT NULL,
        entity VARCHAR(20) NOT NULL,
        field_key VARCHAR(80) NOT NULL,
        label VARCHAR(160) NOT NULL,
        type VARCHAR(20) NOT NULL DEFAULT 'text',
        options TEXT NULL,
        required TINYINT(1) NOT NULL DEFAULT 0,
        position INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL,
        type_id VARCHAR(40) NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_entity_key (entity, field_key)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $createValues = "CREATE TABLE IF NOT EXISTS crminternet_custom_field_values (
        id BIGINT NOT NULL AUTO_INCREMENT,
        entity VARCHAR(20) NOT NULL,
        entity_id VARCHAR(40) NOT NULL,
        field_key VARCHAR(80) NOT NULL,
        value TEXT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_entity_field (entity, entity_id, field_key),
        KEY idx_entity (entity, entity_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $fieldCols = [];
    $hasFieldsTable = false;
    try {
        foreach ($db->query('SHOW COLUMNS FROM crminternet_custom_fields') as $c) {
            $hasFieldsTable = true;
            $fieldCols[$c['Field']] = true;
        }
    } catch (Throwable $e) {
        $hasFieldsTable = false;
    }

    if (!$hasFieldsTable) {
        try {
            $db->exec($createFields);
        } catch (Throwable $e) { /* ignore */ }
    } else {
        // Rename legacy columns (setup.php schema).
        if (isset($fieldCols['entity_type']) && !isset($fieldCols['entity'])) {
            crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields CHANGE COLUMN entity_type entity VARCHAR(20) NOT NULL');
            $fieldCols['entity'] = true;
            unset($fieldCols['entity_type']);
        }
        if (isset($fieldCols['field_name']) && !isset($fieldCols['field_key'])) {
            crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields CHANGE COLUMN field_name field_key VARCHAR(80) NOT NULL');
            $fieldCols['field_key'] = true;
        }
        if (isset($fieldCols['field_label']) && !isset($fieldCols['label'])) {
            crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields CHANGE COLUMN field_label label VARCHAR(160) NOT NULL');
            $fieldCols['label'] = true;
        }
        if (isset($fieldCols['field_type']) && !isset($fieldCols['type'])) {
            crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields CHANGE COLUMN field_type type VARCHAR(20) NOT NULL DEFAULT \'text\'');
            $fieldCols['type'] = true;
        }

        crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields ADD COLUMN entity VARCHAR(20) NOT NULL DEFAULT \'prospect\'');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields ADD COLUMN field_key VARCHAR(80) NOT NULL DEFAULT \'field\'');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields ADD COLUMN label VARCHAR(160) NOT NULL DEFAULT \'\'');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT \'text\'');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields ADD COLUMN options TEXT NULL');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields ADD COLUMN required TINYINT(1) NOT NULL DEFAULT 0');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields ADD COLUMN position INT NOT NULL DEFAULT 0');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields ADD COLUMN created_at DATETIME NOT NULL');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_fields ADD COLUMN type_id VARCHAR(40) NULL');

        try {
            if (isset($fieldCols['entity_type'])) {
                $db->exec('UPDATE crminternet_custom_fields SET entity = LOWER(TRIM(entity_type))
                    WHERE (entity IS NULL OR entity = \'\') AND entity_type IS NOT NULL AND entity_type <> \'\'');
            }
            $db->exec("UPDATE crminternet_custom_fields SET entity = 'prospect' WHERE entity IS NULL OR entity = ''");
            $db->exec("UPDATE crminternet_custom_fields SET field_key = CONCAT('field_', id)
                WHERE field_key IS NULL OR field_key = ''");
            $db->exec("UPDATE crminternet_custom_fields SET label = field_key
                WHERE label IS NULL OR label = ''");
            $db->exec("UPDATE crminternet_custom_fields SET type = 'text'
                WHERE type IS NULL OR type = ''");
            $db->exec("UPDATE crminternet_custom_fields SET created_at = NOW()
                WHERE created_at IS NULL OR created_at = '0000-00-00 00:00:00'");
        } catch (Throwable $e) { /* best-effort */ }
    }

    $valueCols = [];
    $hasValuesTable = false;
    try {
        foreach ($db->query('SHOW COLUMNS FROM crminternet_custom_field_values') as $c) {
            $hasValuesTable = true;
            $valueCols[$c['Field']] = true;
        }
    } catch (Throwable $e) {
        $hasValuesTable = false;
    }

    if (!$hasValuesTable) {
        try {
            $db->exec($createValues);
        } catch (Throwable $e) { /* ignore */ }
    } else {
        if (isset($valueCols['entity_type']) && !isset($valueCols['entity'])) {
            crm_try_alter($db, 'ALTER TABLE crminternet_custom_field_values CHANGE COLUMN entity_type entity VARCHAR(20) NOT NULL');
            $valueCols['entity'] = true;
        }
        if (isset($valueCols['field_value']) && !isset($valueCols['value'])) {
            crm_try_alter($db, 'ALTER TABLE crminternet_custom_field_values CHANGE COLUMN field_value value TEXT NULL');
            $valueCols['value'] = true;
        }

        crm_try_alter($db, 'ALTER TABLE crminternet_custom_field_values ADD COLUMN entity VARCHAR(20) NOT NULL DEFAULT \'prospect\'');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_field_values ADD COLUMN entity_id VARCHAR(40) NOT NULL DEFAULT \'\'');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_field_values ADD COLUMN field_key VARCHAR(80) NOT NULL DEFAULT \'\'');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_field_values ADD COLUMN value TEXT NULL');
        crm_try_alter($db, 'ALTER TABLE crminternet_custom_field_values ADD COLUMN updated_at DATETIME NOT NULL');

        try {
            if (isset($valueCols['entity_type'])) {
                $db->exec('UPDATE crminternet_custom_field_values SET entity = LOWER(TRIM(entity_type))
                    WHERE (entity IS NULL OR entity = \'\') AND entity_type IS NOT NULL AND entity_type <> \'\'');
            }
            if (isset($valueCols['custom_field_id']) && isset($valueCols['field_key'])) {
                $db->exec('UPDATE crminternet_custom_field_values v
                    INNER JOIN crminternet_custom_fields f ON f.id = v.custom_field_id
                    SET v.field_key = f.field_key, v.entity = f.entity
                    WHERE (v.field_key IS NULL OR v.field_key = \'\') AND v.custom_field_id IS NOT NULL');
            }
            $db->exec("UPDATE crminternet_custom_field_values SET updated_at = NOW()
                WHERE updated_at IS NULL OR updated_at = '0000-00-00 00:00:00'");
        } catch (Throwable $e) { /* best-effort */ }
    }
}
