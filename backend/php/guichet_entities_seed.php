<?php
/**
 * Seed guichet entities (repairs schema first).
 *
 * GET .../guichet_entities_seed.php?token=crm-seed-2026
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/guichet_schema.php';
require_once __DIR__ . '/production_seed.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden — add ?token=' . $token, 403);
}

$db = (new Database())->getConnection();
ensure_guichet_schema($db);

$entities = production_seed_load('production_guichet_entities.php');
if (!$entities) {
    fail('production_guichet_entities.php missing', 500);
}

$result = production_seed_guichet_entities($db, $entities);
$count = 0;
$cols = [];
try {
    $count = (int) $db->query('SELECT COUNT(*) FROM crminternet_guichet_entities')->fetchColumn();
    foreach ($db->query('SHOW COLUMNS FROM crminternet_guichet_entities') as $c) {
        $cols[] = $c['Field'];
    }
} catch (Throwable $e) {
    /* ignore */
}

ok([
    'ok' => ($result['status'] ?? '') === 'ok',
    'message' => 'Guichet entities seed completed',
    'summary' => [
        'seeded' => (int) ($result['count'] ?? 0),
        'total_db' => $count,
        'columns' => $cols,
        'has_type_column' => in_array('type', $cols, true),
    ],
    'result' => $result,
]);
