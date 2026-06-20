<?php
/**
 * Fix "Invalid default value for 'created_at'" (and similar) errors that
 * occur on older MySQL / MariaDB (< 5.6.5) or when SQL_MODE contains
 * NO_ZERO_DATE / NO_ZERO_IN_DATE / STRICT_TRANS_TABLES while a DATETIME
 * column was created with DEFAULT CURRENT_TIMESTAMP or '0000-00-00 00:00:00'.
 *
 * Strategy:
 *  - Relax sql_mode for this session (so ALTERs don't choke on existing rows).
 *  - For every crminternet_* table, inspect datetime columns. Most created_at
 *    columns are converted to TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *    except crminternet_audit_log which stays DATETIME NOT NULL for maximum
 *    compatibility. Other legacy DATETIME CURRENT_TIMESTAMP defaults are
 *    removed because MariaDB 5.5 rejects them and only permits one auto
 *    TIMESTAMP column per table.
 *  - Backfill any '0000-00-00 00:00:00' / NULL values with NOW() first so the
 *    ALTER does not violate NO_ZERO_DATE.
 *
 * Also (re)creates crminternet_audit_log with safe column types if missing
 * or broken.
 *
 * Usage:
 *   GET .../fix_datetime_defaults.php?token=crm-seed-2026
 *   GET .../fix_datetime_defaults.php?token=crm-seed-2026&dry=1   (preview)
 *
 * Idempotent: safe to run multiple times.
 */

require_once __DIR__ . '/config.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}
$dry = ($_GET['dry'] ?? '') === '1';

$db = (new Database())->getConnection();

$report = [
    'dry_run'       => $dry,
    'sql_mode'      => null,
    'mysql_version' => null,
    'fixed'         => [],
    'backfilled'    => [],
    'skipped'       => [],
    'recreated'     => [],
    'errors'        => [],
];

try {
    $report['mysql_version'] = (string)$db->query('SELECT VERSION()')->fetchColumn();
    $report['sql_mode']      = (string)$db->query('SELECT @@SESSION.sql_mode')->fetchColumn();

    if (!$dry) {
        // Make this session permissive so ALTERs succeed on legacy data.
        try { $db->exec("SET SESSION sql_mode = ''"); } catch (Throwable $e) {}
    }

    // 1) List crminternet_* tables
    $tables = $db->query(
        "SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_TYPE = 'BASE TABLE'
           AND TABLE_NAME LIKE 'crminternet\\_%' ESCAPE '\\\\'
         ORDER BY TABLE_NAME"
    )->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $colStmt = $db->prepare(
            "SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
        );
        $colStmt->execute([$table]);
        $cols = $colStmt->fetchAll(PDO::FETCH_ASSOC);
        $byName = [];
        foreach ($cols as $c) $byName[strtolower($c['COLUMN_NAME'])] = $c;

        foreach ($byName as $col => $c) {
            $c = $byName[$col];
            $dataType = strtolower($c['DATA_TYPE']);
            if (!in_array($dataType, ['datetime', 'timestamp', 'date'], true)) continue;

            $isCreated = ($col === 'created_at');
            $isUpdated = ($col === 'updated_at');
            $isRequiredManual = in_array($col, ['starts_at', 'assigned_at', 'joined_at', 'login_at', 'read_at'], true);
            $isForceNullable = in_array($col, ['deleted_at', 'reverted_at', 'converted_at', 'validation_date', 'revoked_at', 'logout_at', 'last_read_at', 'last_message_at', 'end_time', 'paid_at'], true);
            $isDate    = ($dataType === 'date');
            $currentExtra = strtolower($c['EXTRA'] ?? '');
            $currentDefault = $c['COLUMN_DEFAULT'];

            // Backfill zero/NULL on columns that must stay NOT NULL first.
            if ($isCreated || $isUpdated || $isRequiredManual) {
                try {
                    if (!$dry) {
                        $n1 = $db->exec("UPDATE `$table` SET `$col` = NOW()
                                         WHERE `$col` IS NULL OR `$col` = '0000-00-00 00:00:00'");
                        if ($n1 > 0) $report['backfilled'][] = "$table.$col rows=$n1";
                    }
                } catch (Throwable $e) {
                    $report['errors'][] = "backfill $table.$col → " . $e->getMessage();
                }
            }

            // Build target definition
            if ($isCreated) {
                $newDef = $table === 'crminternet_audit_log'
                    ? "DATETIME NOT NULL"
                    : "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
            } elseif ($isUpdated) {
                // MariaDB 5.5 allows only ONE TIMESTAMP column with
                // CURRENT_TIMESTAMP per table — created_at already owns it.
                // Use DATETIME NULL so app code can set it manually.
                $newDef = "DATETIME NULL DEFAULT NULL";
            } elseif ($isRequiredManual) {
                // These values are supplied by app code / seed data; do not use
                // DATETIME DEFAULT CURRENT_TIMESTAMP on MariaDB 5.5.
                $newDef = "DATETIME NOT NULL";
            } elseif ($isForceNullable) {
                $newDef = $isDate ? "DATE NULL DEFAULT NULL" : "DATETIME NULL DEFAULT NULL";
            } else {
                // For other datetime columns, preserve nullability while removing
                // MariaDB-5.5-incompatible defaults / ON UPDATE clauses.
                $nullDef = ($c['IS_NULLABLE'] === 'YES') ? "NULL DEFAULT NULL" : "NOT NULL";
                $newDef = $isDate ? "DATE $nullDef" : "DATETIME $nullDef";
            }

            // Decide if change is needed
            $currentType = strtolower($c['COLUMN_TYPE']);
            $needs = false;
            if ($isCreated) {
                if ($dataType !== 'timestamp') $needs = true;
                if (strtoupper((string)$currentDefault) !== 'CURRENT_TIMESTAMP') $needs = true;
                if ($c['IS_NULLABLE'] === 'YES') $needs = true;
            } elseif ($isUpdated) {
                if ($dataType !== 'datetime') $needs = true;
                if ($c['IS_NULLABLE'] !== 'YES') $needs = true;
                if (strpos($currentExtra, 'on update') !== false) $needs = true;
            } elseif ($isRequiredManual) {
                if ($dataType !== 'datetime') $needs = true;
                if ($c['IS_NULLABLE'] !== 'NO') $needs = true;
                if ($currentDefault !== null) $needs = true;
                if (strpos($currentExtra, 'on update') !== false) $needs = true;
            } elseif ($isForceNullable) {
                $defaultUpper = strtoupper((string)$currentDefault);
                if ($c['IS_NULLABLE'] !== 'YES') $needs = true;
                if (strpos($defaultUpper, 'CURRENT_TIMESTAMP') !== false) $needs = true;
                if (strpos($currentExtra, 'on update') !== false) $needs = true;
            } else {
                $defaultUpper = strtoupper((string)$currentDefault);
                if (strpos($defaultUpper, 'CURRENT_TIMESTAMP') !== false) $needs = true;
                if (strpos($currentExtra, 'on update') !== false) $needs = true;
            }

            if (!$needs) { $report['skipped'][] = "$table.$col"; continue; }

            $sql = "ALTER TABLE `$table` MODIFY COLUMN `$col` $newDef";
            if ($dry) { $report['fixed'][] = "[DRY] $sql"; continue; }
            try {
                $db->exec($sql);
                $report['fixed'][] = "$table.$col → $newDef";
            } catch (Throwable $e) {
                $report['errors'][] = "$table.$col → " . $e->getMessage();
            }
        }
    }

    // 2) (Re)create audit_log with a legacy-safe created_at definition if missing
    $hasAudit = in_array('crminternet_audit_log', $tables, true);
    $needsRecreate = false;
    if ($hasAudit) {
        $row = $db->query("SELECT DATA_TYPE, COLUMN_DEFAULT FROM information_schema.COLUMNS
                           WHERE TABLE_SCHEMA = DATABASE()
                             AND TABLE_NAME = 'crminternet_audit_log'
                             AND COLUMN_NAME = 'created_at'")->fetch(PDO::FETCH_ASSOC);
        if (!$row || strtolower($row['DATA_TYPE']) !== 'timestamp') {
            // already handled by loop above via MODIFY — only recreate if it's still broken
        }
    } else {
        $needsRecreate = true;
    }
    if ($needsRecreate) {
        $sql = "CREATE TABLE IF NOT EXISTS crminternet_audit_log (
            id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            created_at    DATETIME    NOT NULL,
            user_username VARCHAR(80) NULL,
            user_role     VARCHAR(40) NULL,
            action        VARCHAR(80) NOT NULL,
            entity_type   VARCHAR(40) NULL,
            entity_id     VARCHAR(80) NULL,
            method        VARCHAR(10) NULL,
            path          VARCHAR(255) NULL,
            ip            VARCHAR(64) NULL,
            user_agent    VARCHAR(255) NULL,
            status_code   SMALLINT    NULL,
            details       TEXT        NULL,
            INDEX idx_created (created_at),
            INDEX idx_user (user_username),
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        if ($dry) {
            $report['recreated'][] = "[DRY] crminternet_audit_log";
        } else {
            try { $db->exec($sql); $report['recreated'][] = 'crminternet_audit_log'; }
            catch (Throwable $e) { $report['errors'][] = 'audit_log create → ' . $e->getMessage(); }
        }
    }

    ok([
        'ok'      => empty($report['errors']),
        'message' => $dry ? 'Dry run complete' : 'Datetime defaults repaired',
        'summary' => [
            'tables_scanned'  => count($tables),
            'fixed_columns'   => count($report['fixed']),
            'backfilled'      => count($report['backfilled']),
            'skipped'         => count($report['skipped']),
            'recreated'       => count($report['recreated']),
            'errors'          => count($report['errors']),
        ],
        'details' => $report,
    ]);
} catch (Throwable $e) {
    fail('Repair failed: ' . $e->getMessage(), 500);
}
