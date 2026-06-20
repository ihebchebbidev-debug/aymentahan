<?php
/**
 * Seed production users (skips AymenAdmin / U-ADMIN-1).
 *
 * GET .../users_seed.php?token=crm-seed-2026
 *
 * DELETE or protect on production after use.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/users_seed_lib.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden — add ?token=' . $token, 403);
}

$db = (new Database())->getConnection();
$results = crm_apply_users_seed($db);

$errors = $results['errors'] ?? [];
if (($results['status'] ?? '') === 'error' && isset($results['message'])) {
    $errors[] = $results;
}

ok([
    'ok'      => count($errors) === 0,
    'message' => 'Users seed completed (AymenAdmin not modified)',
    'summary' => [
        'inserted'  => (int) ($results['inserted'] ?? 0),
        'updated'   => (int) ($results['updated'] ?? 0),
        'skipped'   => (int) ($results['skipped'] ?? 0),
        'failed'    => (int) ($results['failed'] ?? 0),
        'total_db'  => (int) ($results['counts']['crminternet_users'] ?? 0),
        'errors'    => count($errors),
    ],
    'results' => $results,
    'next'    => 'Reload /users — log in with existing passwords from production.',
]);
