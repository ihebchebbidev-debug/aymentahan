<?php
/**
 * Extract users from agent transcript → backend/php/data/production_users.php
 * Usage: php tools/build_production_users.php
 */
require __DIR__ . '/build_production_users_parser.php';

$transcript = 'C:/Users/ihebc/.cursor/projects/c-Users-ihebc-OneDrive-Desktop-code-source/agent-transcripts/addd040a-efb5-422b-9ba8-ba9923535be4/addd040a-efb5-422b-9ba8-ba9923535be4.jsonl';
$raw = file_get_contents($transcript);
if ($raw === false) {
    fwrite(STDERR, "Cannot read transcript\n");
    exit(1);
}

if (!preg_match('/INSERT INTO `crminternet_users`[^V]*VALUES\s*(.*)/s', $raw, $m)) {
    fwrite(STDERR, "Users INSERT not found in transcript\n");
    exit(1);
}

$block = $m[1];
// JSONL stores literal \n in the dump
$block = str_replace(['\\n', '\\r', '\\t'], ["\n", "\r", "\t"], $block);
$block = preg_replace('/\)\s*;\s*.*$/s', ')', $block);

$users = users_seed_parse_values_block($block);
$skip = ['U-ADMIN-1', 'AymenAdmin'];
$users = array_values(array_filter($users, function ($u) use ($skip) {
    return !in_array($u['id'], $skip, true) && !in_array($u['username'], $skip, true);
}));

$out = dirname(__DIR__) . '/data/production_users.php';
file_put_contents(
    $out,
    "<?php\n// " . count($users) . " users (AymenAdmin excluded)\nreturn " . var_export($users, true) . ";\n"
);
echo "Wrote " . count($users) . " users to {$out}\n";
