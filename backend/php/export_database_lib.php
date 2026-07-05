<?php
/**
 * Shared MySQL → .sql export (schema + 100% row data).
 * Used by tools/export_database.php (CLI) and export_database.php (web download).
 */
declare(strict_types=1);

/**
 * @param array{
 *   prefix?:string,
 *   tables?:array<int,string>,
 *   method?:string,
 *   batch?:int,
 *   output?:string,
 *   progress?:callable|null,
 *   credentials?:array{host:string,user:string,pass:string,db:string}|null
 * } $options
 * @return array{path:string,method:string,tables:int,rows:int,bytes:int}
 */
function crm_export_database(PDO $pdo, array $options = []): array
{
    $prefix = (string) ($options['prefix'] ?? '');
    $explicitTables = $options['tables'] ?? [];
    $batchSize = max(1, (int) ($options['batch'] ?? 500));
    $outputPath = (string) ($options['output'] ?? '');
    $progress = $options['progress'] ?? null;
    $method = (string) ($options['method'] ?? 'auto');

    $dbName = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($dbName === '') {
        throw new RuntimeException('No database selected on connection');
    }

    $tables = crm_export_list_tables($pdo, $prefix, $explicitTables);
    if (!$tables) {
        throw new RuntimeException('No tables matched export filters');
    }

    if ($outputPath === '') {
        $outputPath = crm_export_default_output_path($dbName);
    }

    if ($method === 'auto') {
        $method = crm_export_find_mysqldump() !== null ? 'mysqldump' : 'php';
    }

    $notify = static function (string $message) use ($progress): void {
        if (is_callable($progress)) {
            $progress($message);
        }
    };

    $notify("Database: {$dbName}");
    $notify('Tables: ' . count($tables));
    $notify("Method: {$method}");

    if ($method === 'mysqldump') {
        $cfg = $options['credentials'] ?? crm_export_default_credentials($dbName);
        crm_export_with_mysqldump($cfg, $dbName, $tables, $outputPath);
    } else {
        $stats = crm_export_with_php($pdo, $dbName, $tables, $outputPath, $batchSize, $prefix, $notify);
        return [
            'path'    => $outputPath,
            'method'  => 'php',
            'tables'  => count($tables),
            'rows'    => $stats['rows'],
            'bytes'   => $stats['bytes'],
        ];
    }

    return [
        'path'    => $outputPath,
        'method'  => 'mysqldump',
        'tables'  => count($tables),
        'rows'    => 0,
        'bytes'   => is_file($outputPath) ? (int) filesize($outputPath) : 0,
    ];
}

/**
 * @param array<int,string> $explicitTables
 * @return array<int,string>
 */
function crm_export_list_tables(PDO $pdo, string $prefix, array $explicitTables): array
{
    if ($explicitTables) {
        $out = [];
        foreach ($explicitTables as $name) {
            $stmt = $pdo->prepare(
                "SELECT TABLE_NAME FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_TYPE = 'BASE TABLE'
                   AND TABLE_NAME = ?"
            );
            $stmt->execute([$name]);
            $found = $stmt->fetchColumn();
            if ($found) {
                $out[] = (string) $found;
            }
        }
        sort($out);
        return $out;
    }

    $sql = "SELECT TABLE_NAME FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = 'BASE TABLE'";
    $params = [];
    if ($prefix !== '' && $prefix !== '*') {
        $sql .= ' AND TABLE_NAME LIKE ? ESCAPE \'\\\\\'';
        $params[] = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';
    }
    $sql .= ' ORDER BY TABLE_NAME';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** @return array<int,string> */
function crm_export_list_views(PDO $pdo, string $prefix): array
{
    $sql = "SELECT TABLE_NAME FROM information_schema.VIEWS
            WHERE TABLE_SCHEMA = DATABASE()";
    $params = [];
    if ($prefix !== '' && $prefix !== '*') {
        $sql .= ' AND TABLE_NAME LIKE ? ESCAPE \'\\\\\'';
        $params[] = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefix) . '%';
    }
    $sql .= ' ORDER BY TABLE_NAME';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function crm_export_default_output_path(string $dbName): string
{
    $dir = __DIR__ . '/backups';
    $stamp = gmdate('Y-m-d_His');
    $safe = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $dbName) ?: 'database';
    return $dir . '/' . $safe . '_' . $stamp . '.sql';
}

function crm_export_find_mysqldump(): ?string
{
    $candidates = ['mysqldump'];
    if (DIRECTORY_SEPARATOR === '\\') {
        $candidates = array_merge($candidates, [
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MariaDB 10.11\\bin\\mysqldump.exe',
        ]);
    }

    foreach ($candidates as $bin) {
        $cmd = escapeshellarg($bin) . ' --version 2>&1';
        $out = [];
        $code = 0;
        @exec($cmd, $out, $code);
        if ($code === 0) {
            return $bin;
        }
    }

    return null;
}

/** @return array{host:string,user:string,pass:string,db:string} */
function crm_export_default_credentials(string $dbName): array
{
    return [
        'host' => getenv('CRM_DB_HOST') ?: 'localhost',
        'user' => getenv('CRM_DB_USER') ?: 'ttshopvente',
        'pass' => getenv('CRM_DB_PASS') ?: '8Jjs%1g23',
        'db'   => $dbName,
    ];
}

/**
 * @param array{host:string,user:string,pass:string,db:string} $cfg
 * @param array<int,string> $tables
 */
function crm_export_with_mysqldump(array $cfg, string $dbName, array $tables, string $outputPath): void
{
    $mysqldump = crm_export_find_mysqldump();
    if ($mysqldump === null) {
        throw new RuntimeException('mysqldump not found in PATH');
    }

    $outputDir = dirname($outputPath);
    if ($outputPath !== 'php://output' && !is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
        throw new RuntimeException("Cannot create output directory: {$outputDir}");
    }

    $cnfPath = tempnam(sys_get_temp_dir(), 'mysqldump_');
    if ($cnfPath === false) {
        throw new RuntimeException('Cannot create temporary credentials file');
    }

    $cnf = "[client]\n"
        . 'host=' . $cfg['host'] . "\n"
        . 'user=' . $cfg['user'] . "\n"
        . 'password=' . $cfg['pass'] . "\n";
    file_put_contents($cnfPath, $cnf);

    $tableArgs = implode(' ', array_map(static fn(string $t): string => escapeshellarg($t), $tables));

    // --result-file avoids shell redirect issues on Windows.
    $cmd = escapeshellarg($mysqldump)
        . ' --defaults-extra-file=' . escapeshellarg($cnfPath)
        . ' --single-transaction --quick --hex-blob --routines --triggers --events'
        . ' --default-character-set=utf8mb4'
        . ' --add-drop-table --complete-insert'
        . ' --result-file=' . escapeshellarg($outputPath)
        . ' ' . escapeshellarg($dbName)
        . ' ' . $tableArgs;

    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    @unlink($cnfPath);

    if ($code !== 0 || !is_file($outputPath) || filesize($outputPath) === 0) {
        $detail = trim(implode("\n", $out));
        throw new RuntimeException('mysqldump failed' . ($detail !== '' ? ": {$detail}" : ''));
    }
}

/**
 * @param array<int,string> $tables
 * @param callable(string):void|null $progress
 * @return array{rows:int,bytes:int}
 */
function crm_export_with_php(
    PDO $pdo,
    string $dbName,
    array $tables,
    string $outputPath,
    int $batchSize,
    string $prefix = '',
    ?callable $progress = null
): array {
    @ini_set('memory_limit', '1024M');
    @set_time_limit(0);

    if ($outputPath !== 'php://output') {
        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir) && !mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            throw new RuntimeException("Cannot create output directory: {$outputDir}");
        }
    }

    if ($outputPath === 'php://output') {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    $fh = fopen($outputPath, 'wb');
    if ($fh === false) {
        throw new RuntimeException("Cannot open output: {$outputPath}");
    }

    crm_export_write_line($fh, '-- CRM database export (PHP)');
    crm_export_write_line($fh, '-- Database: ' . $dbName);
    crm_export_write_line($fh, '-- Generated: ' . gmdate('c'));
    crm_export_write_line($fh, 'SET NAMES utf8mb4;');
    crm_export_write_line($fh, 'SET FOREIGN_KEY_CHECKS=0;');
    crm_export_write_line($fh, 'SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";');
    crm_export_write_line($fh, 'SET time_zone = "+00:00";');
    crm_export_write_line($fh, '');

    $totalRows = 0;
    foreach ($tables as $table) {
        if ($progress) {
            $progress("Exporting table {$table}...");
        }

        $quotedTable = crm_export_quote_identifier($table);
        $ddl = $pdo->query('SHOW CREATE TABLE ' . $quotedTable)->fetch(PDO::FETCH_NUM);
        if (!$ddl || !isset($ddl[1])) {
            fclose($fh);
            throw new RuntimeException("SHOW CREATE TABLE failed for {$table}");
        }

        crm_export_write_line($fh, '-- --------------------------------------------------------');
        crm_export_write_line($fh, '-- Table structure for `' . $table . '`');
        crm_export_write_line($fh, '-- --------------------------------------------------------');
        crm_export_write_line($fh, '');
        crm_export_write_line($fh, 'DROP TABLE IF EXISTS ' . $quotedTable . ';');
        crm_export_write_line($fh, $ddl[1] . ';');
        crm_export_write_line($fh, '');

        $columns = crm_export_table_columns($pdo, $table);
        if ($columns) {
            $colList = implode(', ', array_map(static fn(string $c): string => crm_export_quote_identifier($c), $columns));
            $rowCount = crm_export_table_rows($pdo, $fh, $table, $columns, $colList, $batchSize);
            $totalRows += $rowCount;
            if ($progress) {
                $progress("  {$table}: " . number_format($rowCount) . ' rows');
            }
        }

        crm_export_table_triggers($pdo, $fh, $table);
        crm_export_write_line($fh, '');

        if ($outputPath === 'php://output') {
            fflush($fh);
            if (function_exists('flush')) {
                flush();
            }
        }
    }

    $views = crm_export_list_views($pdo, $prefix === '' ? '*' : $prefix);
    if ($views) {
        crm_export_write_line($fh, '-- --------------------------------------------------------');
        crm_export_write_line($fh, '-- Views');
        crm_export_write_line($fh, '-- --------------------------------------------------------');
        crm_export_write_line($fh, '');
        foreach ($views as $view) {
            try {
                $quotedView = crm_export_quote_identifier($view);
                $ddl = $pdo->query('SHOW CREATE VIEW ' . $quotedView)->fetch(PDO::FETCH_ASSOC);
                if (!$ddl || empty($ddl['Create View'])) {
                    continue;
                }
                crm_export_write_line($fh, 'DROP VIEW IF EXISTS ' . $quotedView . ';');
                crm_export_write_line($fh, $ddl['Create View'] . ';');
                crm_export_write_line($fh, '');
            } catch (Throwable $e) {
                crm_export_write_line($fh, '-- Skipped view `' . $view . '`: ' . $e->getMessage());
                crm_export_write_line($fh, '');
            }
        }
    }

    crm_export_routines($pdo, $fh);

    crm_export_write_line($fh, 'SET FOREIGN_KEY_CHECKS=1;');
    crm_export_write_line($fh, '-- Export complete — ' . count($tables) . ' tables, ' . $totalRows . ' rows');

    fclose($fh);

    $bytes = $outputPath === 'php://output'
        ? 0
        : (int) (is_file($outputPath) ? filesize($outputPath) : 0);

    return ['rows' => $totalRows, 'bytes' => $bytes];
}

/** @return array<int,string> */
function crm_export_table_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->query('SHOW COLUMNS FROM ' . crm_export_quote_identifier($table));
    $cols = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cols[] = (string) $row['Field'];
    }
    return $cols;
}

/**
 * @param array<int,string> $columns
 */
function crm_export_table_rows(
    PDO $pdo,
    $fh,
    string $table,
    array $columns,
    string $colList,
    int $batchSize
): int {
    $quotedTable = crm_export_quote_identifier($table);
    $total = (int) $pdo->query('SELECT COUNT(*) FROM ' . $quotedTable)->fetchColumn();
    if ($total === 0) {
        return 0;
    }

    crm_export_write_line($fh, '-- Dumping data for table `' . $table . '` (' . $total . ' rows)');
    crm_export_write_line($fh, '');

    $offset = 0;
    $exported = 0;

    while ($offset < $total) {
        $sql = 'SELECT ' . $colList . ' FROM ' . $quotedTable
            . ' LIMIT ' . (int) $batchSize . ' OFFSET ' . (int) $offset;
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            break;
        }

        $valuesChunks = [];
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $col) {
                $values[] = crm_export_sql_value($pdo, $row[$col] ?? null);
            }
            $valuesChunks[] = '(' . implode(', ', $values) . ')';
        }

        crm_export_write_line($fh, 'INSERT INTO ' . $quotedTable . ' (' . $colList . ') VALUES');
        crm_export_write_line($fh, implode(",\n", $valuesChunks) . ';');
        crm_export_write_line($fh, '');

        $batchCount = count($rows);
        $exported += $batchCount;
        $offset += $batchCount;
    }

    return $exported;
}

/** @param resource $fh */
function crm_export_table_triggers(PDO $pdo, $fh, string $table): void
{
    $stmt = $pdo->prepare(
        "SELECT TRIGGER_NAME FROM information_schema.TRIGGERS
         WHERE TRIGGER_SCHEMA = DATABASE() AND EVENT_OBJECT_TABLE = ?"
    );
    $stmt->execute([$table]);
    $names = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!$names) {
        return;
    }

    crm_export_write_line($fh, '-- Triggers for `' . $table . '`');
    foreach ($names as $triggerName) {
        $quoted = crm_export_quote_identifier((string) $triggerName);
        $row = $pdo->query('SHOW CREATE TRIGGER ' . $quoted)->fetch(PDO::FETCH_ASSOC);
        $statement = $row['SQL Original Statement']
            ?? $row['Statement']
            ?? $row['Create Trigger']
            ?? null;
        if (!$statement) {
            continue;
        }
        crm_export_write_line($fh, 'DROP TRIGGER IF EXISTS ' . $quoted . ';');
        crm_export_write_line($fh, 'DELIMITER ;;');
        crm_export_write_line($fh, $statement . ';;');
        crm_export_write_line($fh, 'DELIMITER ;');
        crm_export_write_line($fh, '');
    }
}

/** @param resource $fh */
function crm_export_routines(PDO $pdo, $fh): void
{
    $procs = $pdo->query(
        "SELECT ROUTINE_NAME, ROUTINE_TYPE FROM information_schema.ROUTINES
         WHERE ROUTINE_SCHEMA = DATABASE()
         ORDER BY ROUTINE_TYPE, ROUTINE_NAME"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (!$procs) {
        return;
    }

    crm_export_write_line($fh, '-- --------------------------------------------------------');
    crm_export_write_line($fh, '-- Stored procedures & functions');
    crm_export_write_line($fh, '-- --------------------------------------------------------');
    crm_export_write_line($fh, '');

    foreach ($procs as $proc) {
        $name = (string) $proc['ROUTINE_NAME'];
        $type = strtoupper((string) $proc['ROUTINE_TYPE']);
        $quoted = crm_export_quote_identifier($name);
        $sql = $type === 'FUNCTION' ? 'SHOW CREATE FUNCTION ' . $quoted : 'SHOW CREATE PROCEDURE ' . $quoted;
        $row = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);
        $createKey = $type === 'FUNCTION' ? 'Create Function' : 'Create Procedure';
        if (!$row || empty($row[$createKey])) {
            continue;
        }
        crm_export_write_line($fh, 'DROP ' . $type . ' IF EXISTS ' . $quoted . ';');
        crm_export_write_line($fh, 'DELIMITER ;;');
        crm_export_write_line($fh, $row[$createKey] . ';;');
        crm_export_write_line($fh, 'DELIMITER ;');
        crm_export_write_line($fh, '');
    }
}

/** @param mixed $value */
function crm_export_sql_value(PDO $pdo, $value): string
{
    if ($value === null) {
        return 'NULL';
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if (is_resource($value)) {
        $contents = stream_get_contents($value);
        return $contents === false ? "''" : crm_export_quote_string($pdo, $contents, true);
    }

    $str = (string) $value;
    if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $str)) {
        return crm_export_quote_string($pdo, $str, true);
    }

    return crm_export_quote_string($pdo, $str, false);
}

function crm_export_quote_string(PDO $pdo, string $value, bool $forceHexBlob): string
{
    if ($forceHexBlob) {
        return '0x' . bin2hex($value);
    }

    $quoted = $pdo->quote($value);
    if ($quoted === false) {
        return "'" . str_replace(["\\", "'"], ["\\\\", "''"], $value) . "'";
    }

    return $quoted;
}

function crm_export_quote_identifier(string $name): string
{
    return '`' . str_replace('`', '``', $name) . '`';
}

/** @param resource $fh */
function crm_export_write_line($fh, string $line): void
{
    fwrite($fh, $line . "\n");
}
