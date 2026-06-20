<?php
/**
 * One-shot repair for crminternet_tasks (1067 created_at / legacy setup columns).
 * GET .../repair_tasks.php?token=crm-seed-2026
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/schema_repair.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}

$db = (new Database())->getConnection();
ensure_tasks_schema($db);

$count = 0;
try {
    $count = (int) $db->query('SELECT COUNT(*) FROM crminternet_tasks')->fetchColumn();
} catch (Throwable $e) {
    fail($e->getMessage(), 500);
}

ok([
    'ok' => true,
    'message' => 'Tasks table repaired',
    'tasks' => $count,
]);
