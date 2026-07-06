<?php
/**
 * Backfill attachment rows for converted records (prospect → opportunity → contract/migration).
 * Idempotent — only inserts missing rows (same storage_path dedupe as attachment_clone_entity).
 *
 * GET .../repair_attachment_lineage.php?token=crm-seed-2026
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/attachment_helpers.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}

$me = require_auth(['Administrateur']);
$db = (new Database())->getConnection();

$stats = [
    'filenamesFixed' => attachment_repair_legacy_category_filenames($db),
    'opportunities' => 0,
    'contracts' => 0,
    'directContracts' => 0,
    'migrations' => 0,
];

foreach ($db->query('SELECT id, prospect_id FROM crminternet_opportunities WHERE prospect_id IS NOT NULL AND prospect_id <> \'\'') as $row) {
    $stats['opportunities'] += attachment_clone_entity(
        $db,
        'prospect',
        (string) $row['prospect_id'],
        'opportunity',
        (string) $row['id']
    );
}

foreach ($db->query('SELECT id, prospect_id, opportunity_id FROM crminternet_contracts WHERE opportunity_id IS NOT NULL AND opportunity_id <> \'\'') as $row) {
    $stats['contracts'] += attachment_clone_lineage(
        $db,
        'contract',
        (string) $row['id'],
        !empty($row['prospect_id']) ? (string) $row['prospect_id'] : null,
        (string) $row['opportunity_id']
    );
}

foreach ($db->query(
    'SELECT id, prospect_id FROM crminternet_contracts
     WHERE prospect_id IS NOT NULL AND prospect_id <> \'\'
       AND (opportunity_id IS NULL OR opportunity_id = \'\')'
) as $row) {
    $stats['directContracts'] += attachment_clone_entity(
        $db,
        'prospect',
        (string) $row['prospect_id'],
        'contract',
        (string) $row['id']
    );
}

foreach ($db->query('SELECT id, prospect_id, opportunity_id FROM crminternet_migrations WHERE deleted_at IS NULL') as $row) {
    $stats['migrations'] += attachment_clone_lineage(
        $db,
        'migration',
        (string) $row['id'],
        !empty($row['prospect_id']) ? (string) $row['prospect_id'] : null,
        !empty($row['opportunity_id']) ? (string) $row['opportunity_id'] : null
    );
}

ok([
    'message' => 'Attachment lineage repair completed',
    'rowsInserted' => $stats,
    'note' => 'Re-run safely anytime. Does not duplicate files on disk.',
]);
