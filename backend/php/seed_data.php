<?php
/**
 * Idempotent CRM seed data (roles, permissions, stages, prospect types, settings).
 *
 * Run once after setup.php or on empty DB:
 *   GET .../seed_data.php?token=crm-seed-2026
 *
 * DELETE this file from production after use (or protect with a strong token).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crm_schema.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden — add ?token=' . $token, 403);
}

$db = (new Database())->getConnection();
$results = crm_apply_seed_data($db);

$userCount = (int) ($results['counts']['crminternet_users'] ?? 0);
$roleCount = (int) ($results['counts']['crminternet_roles'] ?? 0);
$typeCount = (int) ($results['counts']['crminternet_prospect_types'] ?? 0);

$errors = array_values(array_filter($results, function ($r) {
    return is_array($r) && isset($r['status']) && $r['status'] === 'error';
}));

$counts = $results['counts'] ?? [];
$prodCounts = $results['production_counts'] ?? [];

ok([
    'ok'       => count($errors) === 0,
    'message'  => 'Seed completed (roles, types, permissions, teams, guichet entities)',
    'summary'  => [
        'roles'             => (int) ($counts['crminternet_roles'] ?? 0),
        'prospect_types'    => (int) ($counts['crminternet_prospect_types'] ?? 0),
        'permissions'       => (int) ($prodCounts['crminternet_role_permissions'] ?? $counts['crminternet_role_permissions'] ?? 0),
        'teams'             => (int) ($prodCounts['crminternet_teams'] ?? 0),
        'guichet_entities'  => (int) ($prodCounts['crminternet_guichet_entities'] ?? 0),
        'users'             => (int) ($counts['crminternet_users'] ?? 0),
        'errors'            => count($errors),
    ],
    'results'  => $results,
    'next'     => 'Reload /users in the CRM. If users still empty, upload fixed users.php.',
]);
