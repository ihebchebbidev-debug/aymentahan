<?php
/**
 * Create/repair terminal migration tables + seed stages & role permissions.
 * GET .../repair_terminal_migrations.php?token=crm-seed-2026
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/schema_repair.php';
require_once __DIR__ . '/crm_terminal_migration_schema.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}

$db = (new Database())->getConnection();
try {
    ensure_custom_fields_schema($db);
    ensure_terminal_migration_schema($db);
    crm_seed_animacom_terminal_stages($db);
} catch (Throwable $e) {
    fail('Schema repair failed: ' . $e->getMessage(), 500);
}

$customFieldsCols = [];
try {
    foreach ($db->query('SHOW COLUMNS FROM crminternet_custom_fields') as $c) {
        $customFieldsCols[] = $c['Field'];
    }
} catch (Throwable $e) {
    $customFieldsCols = ['error' => $e->getMessage()];
}

$stageNames = [];
try {
    $stageNames['contracts'] = $db->query('SELECT name FROM crminternet_contract_stages ORDER BY position')->fetchAll(PDO::FETCH_COLUMN);
    $stageNames['migrations'] = $db->query('SELECT name FROM crminternet_migration_stages ORDER BY position')->fetchAll(PDO::FETCH_COLUMN);
} catch (Throwable $e) {
    $stageNames['error'] = $e->getMessage();
}

$permKeys = crm_migration_permission_keys();
$permCheck = [];
try {
    $in = implode(',', array_fill(0, count($permKeys), '?'));
    $sql = "SELECT permission, enabled FROM crminternet_role_permissions
            WHERE role = ? AND permission IN ($in)";
    foreach (['Administrateur', 'Manager', 'AgentSuivi', 'Backoffice'] as $role) {
        $s = $db->prepare($sql);
        $s->execute(array_merge([$role], $permKeys));
        $permCheck[$role] = $s->fetchAll(PDO::FETCH_KEY_PAIR);
    }
} catch (Throwable $e) {
    $permCheck['error'] = $e->getMessage();
}

ok([
    'ok' => true,
    'message' => 'Terminal migration module + custom fields schema ready',
    'status' => terminal_migration_schema_status($db),
    'custom_fields_columns' => $customFieldsCols,
    'terminal_stages' => $stageNames,
    'permissions' => $permKeys,
    'role_permission_sample' => $permCheck,
]);
