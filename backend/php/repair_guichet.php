<?php
/**
 * Repair all guichet tables (legacy setup.php → app schema) + optional entity seed.
 *
 * GET .../repair_guichet.php?token=crm-seed-2026
 * GET .../repair_guichet.php?token=crm-seed-2026&seed=1
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/guichet_schema.php';
require_once __DIR__ . '/production_seed.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}

$db = (new Database())->getConnection();
try {
    ensure_guichet_schema($db);
} catch (Throwable $e) {
    fail('Guichet schema repair failed: ' . $e->getMessage(), 500);
}

$seedResult = null;
if (($_GET['seed'] ?? '') === '1') {
    $entities = production_seed_load('production_guichet_entities.php');
    if ($entities) {
        $seedResult = production_seed_guichet_entities($db, $entities);
    }
}

$status = guichet_schema_status($db);
$entityCount = 0;
try {
    $entityCount = (int) $db->query('SELECT COUNT(*) FROM crminternet_guichet_entities')->fetchColumn();
} catch (Throwable $e) {
    /* ignore */
}

$hasType = in_array('type', $status['crminternet_guichet_entities']['columns'] ?? [], true);

ok([
    'ok' => $hasType,
    'message' => $hasType
        ? 'Guichet schema repaired'
        : 'Repair ran but type column still missing — check DB permissions',
    'entities' => $entityCount,
    'schema' => $status,
    'seed' => $seedResult,
]);
