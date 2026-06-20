<?php
/**
 * Quick deploy check — GET /health.php
 * Returns JSON listing whether key endpoint files look valid (start with <?php).
 */
require_once __DIR__ . '/config.php';
require_method('GET');

$files = [
    'opportunities.php',
    'prospects.php',
    'contracts.php',
    'list_query_helpers.php',
    'api_limits.php',
    'config.php',
];

$checks = [];
$allOk = true;
foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    $exists = is_file($path);
    $head = $exists ? (string)file_get_contents($path, false, null, 0, 32) : '';
    $valid = $exists && strpos(ltrim($head), '<?php') === 0;
    if (!$valid) $allOk = false;
    $checks[$f] = ['exists' => $exists, 'validPhp' => $valid];
}

ok([
    'ok'      => $allOk,
    'php'     => PHP_VERSION,
    'checks'  => $checks,
    'message' => $allOk
        ? 'Backend endpoint files look valid'
        : 'Some PHP files are missing or not valid PHP (wrong upload?). Re-deploy opportunities.php, prospects.php, contracts.php from the repo.',
]);
