<?php
/**
 * Universal schema repair — adds all missing columns/indexes to
 * crminternet_prospects / opportunities / contracts / guichet tables.
 *
 * Usage:
 *   GET .../repair_schema.php?token=crm-seed-2026
 *   GET .../repair_schema.php?token=crm-seed-2026&dry=1   (preview only)
 *
 * Idempotent: safe to run multiple times.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/schema_repair.php';
require_once __DIR__ . '/guichet_schema.php';
require_once __DIR__ . '/conversion_helpers.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}
$dry = ($_GET['dry'] ?? '') === '1';

$db = (new Database())->getConnection();

function table_columns(PDO $db, string $table): array {
    $out = [];
    try {
        foreach ($db->query('SHOW COLUMNS FROM `' . $table . '`') as $c) {
            $out[$c['Field']] = true;
        }
    } catch (Throwable $e) { /* table missing */ }
    return $out;
}

function table_indexes(PDO $db, string $table): array {
    $out = [];
    try {
        foreach ($db->query('SHOW INDEX FROM `' . $table . '`') as $i) {
            $out[$i['Key_name']] = true;
        }
    } catch (Throwable $e) {}
    return $out;
}

/**
 * Spec of all required columns per table, taken from original database_empty.sql
 * (the reference production schema).
 */
$spec = [
    'crminternet_prospects' => [
        'phone2'          => "VARCHAR(40) NOT NULL DEFAULT ''",
        'address'         => "VARCHAR(255) NOT NULL DEFAULT ''",
        'gouvernorat'     => "VARCHAR(120) NOT NULL DEFAULT ''",
        'delegation'      => "VARCHAR(120) NOT NULL DEFAULT ''",
        'code_postal'     => "VARCHAR(16) NULL",
        'birth_date'      => "DATE NULL",
        'animateur'       => "VARCHAR(120) NULL",
        'ancien_ligne'    => "VARCHAR(40) NULL",
        'stage'           => "VARCHAR(80) NULL",
        'type_id'         => "VARCHAR(40) NULL",
        'comment'         => "TEXT NULL",
        'comment2'        => "TEXT NULL",
        'check_valeur'    => "ENUM('valid','invalid','pending') NOT NULL DEFAULT 'pending'",
        'converted'       => "TINYINT(1) NOT NULL DEFAULT 0",
        'converted_at'    => "DATETIME NULL",
        'opportunity_id'  => "VARCHAR(40) NULL",
        'reverted_at'     => "DATETIME NULL",
        'reverted_from'   => "VARCHAR(20) NULL",
        'lost_reason'     => "VARCHAR(255) NULL",
        'localisation_xy' => "VARCHAR(64) NULL",
        'deleted_at'      => "DATETIME NULL",
    ],
    'crminternet_opportunities' => [
        'deleted_at'      => "DATETIME NULL",
        'animateur'       => "VARCHAR(120) NULL",
        'ancien_ligne'    => "VARCHAR(40) NULL",
        'zone'            => "VARCHAR(120) NOT NULL DEFAULT ''",
        'gouvernorat'     => "VARCHAR(120) NOT NULL DEFAULT ''",
        'delegation'      => "VARCHAR(120) NOT NULL DEFAULT ''",
        'lost_reason'     => "VARCHAR(255) NULL",
        'lead_status'     => "VARCHAR(80) NULL",
    ],
    'crminternet_contracts' => [
        'ancien_ligne'    => "VARCHAR(40) NULL",
        'animateur'       => "VARCHAR(120) NULL",
        'zone'            => "VARCHAR(120) NOT NULL DEFAULT ''",
        'gouvernorat'     => "VARCHAR(120) NOT NULL DEFAULT ''",
        'delegation'      => "VARCHAR(120) NOT NULL DEFAULT ''",
        'source'          => "VARCHAR(80) NOT NULL DEFAULT 'Web'",
        'lead_status'     => "VARCHAR(80) NULL",
        'validation_date' => "DATE NULL",
        'prospect_id'     => "VARCHAR(40) NULL",
        'deleted_at'      => "DATETIME NULL",
    ],
];

$indexSpec = [
    'crminternet_prospects' => [
        'idx_prospect_phone2'      => 'phone2',
        'idx_prospect_opportunity' => 'opportunity_id',
        'idx_prospect_deleted'     => 'deleted_at',
        'idx_prospect_converted'   => 'converted',
        'ix_prospect_cin'          => 'cin',
    ],
    'crminternet_opportunities' => [
        'idx_opp_deleted'      => 'deleted_at',
        'idx_opp_animateur'    => 'animateur',
        'idx_opp_ancien_ligne' => 'ancien_ligne',
    ],
    'crminternet_contracts' => [
        'idx_contract_deleted'   => 'deleted_at',
        'idx_contract_prospect'  => 'prospect_id',
        'idx_contract_animateur' => 'animateur',
    ],
];

$report = ['added' => [], 'skipped' => [], 'errors' => [], 'indexes' => [], 'guichet' => null, 'dry_run' => $dry];

foreach ($spec as $table => $cols) {
    $existing = table_columns($db, $table);
    if (!$existing) {
        $report['errors'][] = "Table $table does not exist";
        continue;
    }
    foreach ($cols as $col => $def) {
        if (isset($existing[$col])) {
            $report['skipped'][] = "$table.$col";
            continue;
        }
        $sql = "ALTER TABLE `$table` ADD COLUMN `$col` $def";
        if ($dry) {
            $report['added'][] = "[DRY] $sql";
            continue;
        }
        try {
            $db->exec($sql);
            $report['added'][] = "$table.$col";
        } catch (Throwable $e) {
            $report['errors'][] = "$table.$col → " . $e->getMessage();
        }
    }
}

// Indexes
foreach ($indexSpec as $table => $idx) {
    if (!table_columns($db, $table)) continue;
    $existingIdx = table_indexes($db, $table);
    $existingCols = table_columns($db, $table);
    foreach ($idx as $name => $col) {
        if (isset($existingIdx[$name])) continue;
        if (!$col || !isset($existingCols[$col])) continue;
        $sql = "ALTER TABLE `$table` ADD INDEX `$name` (`$col`)";
        if ($dry) { $report['indexes'][] = "[DRY] $sql"; continue; }
        try {
            $db->exec($sql);
            $report['indexes'][] = "$table.$name";
        } catch (Throwable $e) {
            $report['errors'][] = "$table.$name → " . $e->getMessage();
        }
    }
}

// Best-effort backfill (only when not dry and prospects has the new cols)
if (!$dry) {
    try {
        $db->exec("UPDATE crminternet_prospects SET gouvernorat = UPPER(city)
                   WHERE (gouvernorat IS NULL OR gouvernorat = '') AND city IS NOT NULL AND city <> ''");
    } catch (Throwable $e) { /* ignore */ }
    try {
        $db->exec("UPDATE crminternet_prospects SET delegation = zone
                   WHERE (delegation IS NULL OR delegation = '') AND zone IS NOT NULL AND zone <> ''");
    } catch (Throwable $e) { /* ignore */ }
    try {
        $db->exec("UPDATE crminternet_prospects SET cin = NULL WHERE cin = ''");
    } catch (Throwable $e) { /* ignore */ }
    try {
        conv_backfill_contract_references($db);
        $report['contract_references'] = 'backfilled';
    } catch (Throwable $e) {
        $report['errors'][] = 'contract_references → ' . $e->getMessage();
    }
}

// Guichet repair (idempotent)
try {
    if (!$dry) {
        ensure_guichet_schema($db);
        $report['guichet'] = 'repaired';
    } else {
        $report['guichet'] = 'skipped (dry)';
    }
} catch (Throwable $e) {
    $report['errors'][] = 'guichet → ' . $e->getMessage();
}

ok([
    'ok' => empty($report['errors']),
    'message' => $dry ? 'Dry run complete' : 'Schema repair complete',
    'summary' => [
        'added_columns'  => count($report['added']),
        'skipped_columns'=> count($report['skipped']),
        'added_indexes'  => count($report['indexes']),
        'errors'         => count($report['errors']),
    ],
    'details' => $report,
]);
