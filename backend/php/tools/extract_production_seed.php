<?php
/**
 * One-off: extract production seed tuples from agent transcript JSONL.
 * Usage: php tools/extract_production_seed.php
 */
$transcript = 'C:/Users/ihebc/.cursor/projects/c-Users-ihebc-OneDrive-Desktop-code-source/agent-transcripts/addd040a-efb5-422b-9ba8-ba9923535be4/addd040a-efb5-422b-9ba8-ba9923535be4.jsonl';
$raw = file_get_contents($transcript);
if ($raw === false) {
    fwrite(STDERR, "Cannot read transcript\n");
    exit(1);
}

$dataDir = dirname(__DIR__) . '/data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Permissions: ('Role', 'perm', 0|1)
if (!preg_match('/INSERT INTO `crminternet_role_permissions`[^V]*VALUES\s*(.*?)(?:INSERT INTO `crminternet_team_roles`|--\s*Indexes)/s', $raw, $m)) {
    fwrite(STDERR, "Permissions block not found\n");
    exit(1);
}
$block = $m[1];
preg_match_all("/\\('([^']+)',\\s*'([^']+)',\\s*(\\d)\\)/", $block, $all, PREG_SET_ORDER);
$perms = [];
foreach ($all as $x) {
    $perms[] = [$x[1], $x[2], (int) $x[3]];
}
file_put_contents($dataDir . '/production_permissions.php', "<?php\n// Auto-generated — " . count($perms) . " tuples\nreturn " . var_export($perms, true) . ";\n");
echo "permissions: " . count($perms) . "\n";

// Prospect types
if (preg_match('/INSERT INTO `crminternet_prospect_types`[^V]*VALUES\s*(.*?);/s', $raw, $m)) {
    preg_match_all("/\\('([^']+)',\\s*'([^']*)',\\s*'([^']*)',\\s*'([^']+)',\\s*(\\d+),\\s*(\\d+),\\s*'([^']+)'\\)/", $m[1], $pts, PREG_SET_ORDER);
    $types = [];
    foreach ($pts as $x) {
        $types[] = [
            'id' => $x[1],
            'name' => $x[2],
            'description' => $x[3],
            'color' => $x[4],
            'position' => (int) $x[5],
            'active' => (int) $x[6],
            'created_at' => $x[7],
        ];
    }
    file_put_contents($dataDir . '/production_prospect_types.php', "<?php\nreturn " . var_export($types, true) . ";\n");
    echo "prospect_types: " . count($types) . "\n";
}

// Roles
if (preg_match('/INSERT INTO `crminternet_roles`[^V]*VALUES\s*(.*?);/s', $raw, $m)) {
    preg_match_all(
        "/\\('([^']+)',\\s*'([^']*)',\\s*'((?:[^'\\\\]|\\\\'|[^'])*)',\\s*'([^']+)',\\s*(\\d),\\s*(\\d+),\\s*'([^']+)',\\s*'([^']+)'\\)/",
        $m[1],
        $roles,
        PREG_SET_ORDER
    );
    $out = [];
    foreach ($roles as $x) {
        $out[] = [
            'name' => $x[1],
            'label' => $x[2],
            'description' => stripcslashes($x[3]),
            'color' => $x[4],
            'is_system' => (int) $x[5],
            'sort_order' => (int) $x[6],
            'created_at' => $x[7],
            'updated_at' => $x[8],
        ];
    }
    file_put_contents($dataDir . '/production_roles.php', "<?php\nreturn " . var_export($out, true) . ";\n");
    echo "roles: " . count($out) . "\n";
}

// Team roles
if (preg_match('/INSERT INTO `crminternet_team_roles`[^V]*VALUES\s*(.*?);/s', $raw, $m)) {
    preg_match_all("/\\('([^']+)',\\s*'([^']+)'\\)/", $m[1], $tr, PREG_SET_ORDER);
    $teamRoles = array_map(fn($x) => [$x[1], $x[2]], $tr);
    file_put_contents($dataDir . '/production_team_roles.php', "<?php\nreturn " . var_export($teamRoles, true) . ";\n");
    echo "team_roles: " . count($teamRoles) . "\n";
}

echo "Done.\n";
