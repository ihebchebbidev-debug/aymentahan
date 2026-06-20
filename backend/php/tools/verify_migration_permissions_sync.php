<?php
/**
 * CLI: verify backend migration permission keys match frontend catalog.
 * php backend/php/tools/verify_migration_permissions_sync.php
 */
require_once dirname(__DIR__) . '/crm_terminal_migration_schema.php';

$backend = crm_migration_permission_keys();
sort($backend);

$permsTs = dirname(__DIR__, 3) . '/src/lib/permissions.ts';
$text = is_file($permsTs) ? file_get_contents($permsTs) : '';
if (!preg_match('/export const MIGRATION_PERMISSION_KEYS = \[([\s\S]*?)\] as const;/', $text, $m)) {
    fwrite(STDERR, "Could not parse MIGRATION_PERMISSION_KEYS from permissions.ts\n");
    exit(1);
}
preg_match_all("/\"([a-z0-9_.]+)\"/", $m[1], $found);
$frontend = $found[1] ?? [];
sort($frontend);

$missingInFront = array_diff($backend, $frontend);
$missingInBack = array_diff($frontend, $backend);

if ($missingInFront || $missingInBack) {
    echo "MISMATCH\n";
    if ($missingInFront) {
        echo "  In PHP only: " . implode(', ', $missingInFront) . "\n";
    }
    if ($missingInBack) {
        echo "  In TS only: " . implode(', ', $missingInBack) . "\n";
    }
    exit(1);
}

echo "OK — " . count($backend) . " migration permission keys in sync.\n";
exit(0);
