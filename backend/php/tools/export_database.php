<?php
/**
 * CLI — export MySQL database to .sql (schema + 100% row data).
 *
 * Usage:
 *   php tools/export_database.php
 *   php tools/export_database.php --prefix=crminternet_
 *   php tools/export_database.php --method=php
 *   php tools/export_database.php --output=C:\backups\dump.sql
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only. For web download use export_database.php?token=...\n");
    exit(1);
}

require_once dirname(__DIR__) . '/export_database_lib.php';

$options = parse_cli_options($argv);
$cfg = resolve_cli_db_config($options);

try {
    $pdo = new PDO(
        "mysql:host={$cfg['host']};dbname={$cfg['db']};charset=utf8mb4",
        $cfg['user'],
        $cfg['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Connection failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$started = microtime(true);
try {
    $result = crm_export_database($pdo, [
        'prefix'      => $options['prefix'] === '*' ? '' : $options['prefix'],
        'tables'      => $options['tables'],
        'method'      => $options['method'],
        'batch'       => $options['batch'],
        'output'      => $options['output'],
        'credentials' => $cfg,
        'progress'    => static function (string $msg): void {
            fwrite(STDOUT, $msg . "\n");
        },
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, 'Export failed: ' . $e->getMessage() . "\n");
    exit(1);
}

$elapsed = round(microtime(true) - $started, 2);
$sizeMb = round($result['bytes'] / 1024 / 1024, 2);
fwrite(STDOUT, "Output   : {$result['path']}\n");
fwrite(STDOUT, "Done in {$elapsed}s — {$sizeMb} MB, {$result['rows']} rows exported.\n");

/**
 * @return array{
 *   host:string,user:string,pass:string,db:string,
 *   prefix:string,tables:array<int,string>,output:string,
 *   method:string,batch:int
 * }
 */
function parse_cli_options(array $argv): array
{
    $opts = [
        'host'   => '',
        'user'   => '',
        'pass'   => '',
        'db'     => '',
        'prefix' => '',
        'tables' => [],
        'output' => '',
        'method' => 'auto',
        'batch'  => 500,
    ];

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--help' || $arg === '-h') {
            print_cli_usage();
            exit(0);
        }
        if (preg_match('/^--host=(.+)$/', $arg, $m)) {
            $opts['host'] = $m[1];
            continue;
        }
        if (preg_match('/^--user=(.+)$/', $arg, $m)) {
            $opts['user'] = $m[1];
            continue;
        }
        if (preg_match('/^--pass=(.*)$/', $arg, $m)) {
            $opts['pass'] = $m[1];
            continue;
        }
        if (preg_match('/^--db=(.+)$/', $arg, $m)) {
            $opts['db'] = $m[1];
            continue;
        }
        if (preg_match('/^--prefix=(.*)$/', $arg, $m)) {
            $opts['prefix'] = $m[1];
            continue;
        }
        if (preg_match('/^--tables=(.+)$/', $arg, $m)) {
            $opts['tables'] = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
            continue;
        }
        if (preg_match('/^--output=(.+)$/', $arg, $m)) {
            $opts['output'] = $m[1];
            continue;
        }
        if (preg_match('/^--method=(auto|mysqldump|php)$/', $arg, $m)) {
            $opts['method'] = $m[1];
            continue;
        }
        if (preg_match('/^--batch=(\d+)$/', $arg, $m)) {
            $opts['batch'] = max(1, (int) $m[1]);
            continue;
        }
        fwrite(STDERR, "Unknown option: {$arg}\n");
        print_cli_usage();
        exit(1);
    }

    return $opts;
}

function print_cli_usage(): void
{
    fwrite(STDOUT, <<<'TXT'
Export MySQL database to .sql (schema + data).

Options:
  --host=HOST       MySQL host (default: localhost)
  --user=USER       MySQL user (default: ttshopvente)
  --pass=PASS       MySQL password
  --db=NAME         Database name (default: wordpress_18)
  --prefix=PREFIX   Only tables matching prefix (default: all; use * explicitly)
  --tables=a,b,c    Explicit table list (overrides --prefix)
  --output=PATH     Output .sql file (default: ../backups/DB_YYYY-MM-DD_HHMMSS.sql)
  --method=auto     auto | mysqldump | php
  --batch=N         PHP exporter row batch size (default: 500)

Web download:
  GET .../export_database.php?token=crm-seed-2026

TXT);
}

/** @param array<string,mixed> $options */
function resolve_cli_db_config(array $options): array
{
    return [
        'host' => $options['host'] !== '' ? $options['host'] : (getenv('CRM_DB_HOST') ?: 'localhost'),
        'user' => $options['user'] !== '' ? $options['user'] : (getenv('CRM_DB_USER') ?: 'ttshopvente'),
        'pass' => $options['pass'] !== '' ? $options['pass'] : (getenv('CRM_DB_PASS') ?: '8Jjs%1g23'),
        'db'   => $options['db'] !== '' ? $options['db'] : (getenv('CRM_DB_NAME') ?: 'wordpress_18'),
    ];
}
