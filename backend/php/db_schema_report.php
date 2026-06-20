<?php
/**
 * Database structure report — lists tables, columns, indexes, foreign keys.
 *
 * GET .../db_schema_report.php?token=crm-seed-2026
 *   &prefix=crminternet_   (default; use prefix=* for every table in the DB)
 *   &counts=1               exact COUNT(*) per table (slower than TABLE_ROWS estimate)
 *   &ddl=1                  include SHOW CREATE TABLE DDL per table
 *   &table=crminternet_users  single-table detail only
 */
require_once __DIR__ . '/config.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}

$db = (new Database())->getConnection();

$prefixRaw = array_key_exists('prefix', $_GET) ? (string)$_GET['prefix'] : 'crminternet_';
$singleTable = trim((string)($_GET['table'] ?? ''));
$exactCounts = filter_var($_GET['counts'] ?? '0', FILTER_VALIDATE_BOOLEAN);
$includeDdl = filter_var($_GET['ddl'] ?? '0', FILTER_VALIDATE_BOOLEAN);

$likePattern = null;
if ($singleTable !== '') {
    $likePattern = $singleTable;
} elseif ($prefixRaw === '*' || $prefixRaw === '') {
    $likePattern = '%';
} else {
    $likePattern = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $prefixRaw) . '%';
}

try {
    $dbName = (string)$db->query('SELECT DATABASE()')->fetchColumn();

    $tableSql = '
        SELECT TABLE_NAME, ENGINE, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH,
               CREATE_TIME, UPDATE_TIME, TABLE_COLLATION, TABLE_COMMENT
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_TYPE = \'BASE TABLE\'
    ';
    $tableParams = [];
    if ($singleTable !== '') {
        $tableSql .= ' AND TABLE_NAME = ?';
        $tableParams[] = $singleTable;
    } elseif ($likePattern !== null) {
        $tableSql .= ' AND TABLE_NAME LIKE ? ESCAPE \'\\\\\'';
        $tableParams[] = $likePattern;
    }
    $tableSql .= ' ORDER BY TABLE_NAME';

    $tableStmt = $db->prepare($tableSql);
    $tableStmt->execute($tableParams);
    $tableRows = $tableStmt->fetchAll(PDO::FETCH_ASSOC);

    if ($singleTable !== '' && !$tableRows) {
        fail('Table not found: ' . $singleTable, 404);
    }

    $tableNames = array_column($tableRows, 'TABLE_NAME');
    $columnsByTable = [];
    $indexesByTable = [];
    $fksByTable = [];

    if ($tableNames) {
        $in = implode(',', array_fill(0, count($tableNames), '?'));

        $colStmt = $db->prepare("
            SELECT TABLE_NAME, COLUMN_NAME, ORDINAL_POSITION, COLUMN_DEFAULT,
                   IS_NULLABLE, DATA_TYPE, COLUMN_TYPE, COLUMN_KEY, EXTRA, COLUMN_COMMENT
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ($in)
            ORDER BY TABLE_NAME, ORDINAL_POSITION
        ");
        $colStmt->execute($tableNames);
        foreach ($colStmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
            $columnsByTable[$col['TABLE_NAME']][] = [
                'name'     => $col['COLUMN_NAME'],
                'position' => (int)$col['ORDINAL_POSITION'],
                'type'     => $col['COLUMN_TYPE'],
                'dataType' => $col['DATA_TYPE'],
                'nullable' => $col['IS_NULLABLE'] === 'YES',
                'default'  => $col['COLUMN_DEFAULT'],
                'key'      => $col['COLUMN_KEY'] ?: null,
                'extra'    => $col['EXTRA'] ?: null,
                'comment'  => $col['COLUMN_COMMENT'] ?: null,
            ];
        }

        $idxStmt = $db->prepare("
            SELECT TABLE_NAME, INDEX_NAME, NON_UNIQUE, SEQ_IN_INDEX, COLUMN_NAME,
                   COLLATION, SUB_PART, INDEX_TYPE
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME IN ($in)
            ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX
        ");
        $idxStmt->execute($tableNames);
        foreach ($idxStmt->fetchAll(PDO::FETCH_ASSOC) as $idx) {
            $t = $idx['TABLE_NAME'];
            $name = $idx['INDEX_NAME'];
            if (!isset($indexesByTable[$t][$name])) {
                $indexesByTable[$t][$name] = [
                    'name'     => $name,
                    'unique'   => (int)$idx['NON_UNIQUE'] === 0,
                    'type'     => $idx['INDEX_TYPE'],
                    'columns'  => [],
                ];
            }
            $indexesByTable[$t][$name]['columns'][] = [
                'name'      => $idx['COLUMN_NAME'],
                'seq'       => (int)$idx['SEQ_IN_INDEX'],
                'collation' => $idx['COLLATION'],
                'subPart'   => $idx['SUB_PART'] !== null ? (int)$idx['SUB_PART'] : null,
            ];
        }

        $fkStmt = $db->prepare("
            SELECT k.TABLE_NAME, k.CONSTRAINT_NAME, k.COLUMN_NAME,
                   k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME,
                   rc.UPDATE_RULE, rc.DELETE_RULE
            FROM information_schema.KEY_COLUMN_USAGE k
            JOIN information_schema.REFERENTIAL_CONSTRAINTS rc
              ON rc.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
             AND rc.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE k.CONSTRAINT_SCHEMA = DATABASE()
              AND k.TABLE_NAME IN ($in)
              AND k.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION
        ");
        $fkStmt->execute($tableNames);
        foreach ($fkStmt->fetchAll(PDO::FETCH_ASSOC) as $fk) {
            $t = $fk['TABLE_NAME'];
            $name = $fk['CONSTRAINT_NAME'];
            if (!isset($fksByTable[$t][$name])) {
                $fksByTable[$t][$name] = [
                    'name'           => $name,
                    'columns'        => [],
                    'referencedTable'=> $fk['REFERENCED_TABLE_NAME'],
                    'referencedCols' => [],
                    'onUpdate'       => $fk['UPDATE_RULE'],
                    'onDelete'       => $fk['DELETE_RULE'],
                ];
            }
            $fksByTable[$t][$name]['columns'][] = $fk['COLUMN_NAME'];
            $fksByTable[$t][$name]['referencedCols'][] = $fk['REFERENCED_COLUMN_NAME'];
        }
    }

    $tables = [];
    $totalColumns = 0;
    $totalEstimatedRows = 0;

    foreach ($tableRows as $tr) {
        $name = $tr['TABLE_NAME'];
        $cols = $columnsByTable[$name] ?? [];
        $totalColumns += count($cols);

        $estimatedRows = (int)($tr['TABLE_ROWS'] ?? 0);
        $totalEstimatedRows += $estimatedRows;

        $rowCount = $estimatedRows;
        if ($exactCounts) {
            try {
                $rowCount = (int)$db->query('SELECT COUNT(*) FROM `' . str_replace('`', '``', $name) . '`')->fetchColumn();
            } catch (Throwable $e) {
                $rowCount = null;
            }
        }

        $entry = [
            'name'           => $name,
            'engine'         => $tr['ENGINE'],
            'collation'      => $tr['TABLE_COLLATION'],
            'comment'        => $tr['TABLE_COMMENT'] ?: null,
            'rowCount'       => $rowCount,
            'rowCountSource' => $exactCounts ? 'exact' : 'estimate',
            'dataBytes'      => (int)($tr['DATA_LENGTH'] ?? 0),
            'indexBytes'     => (int)($tr['INDEX_LENGTH'] ?? 0),
            'createdAt'      => $tr['CREATE_TIME'],
            'updatedAt'      => $tr['UPDATE_TIME'],
            'columnCount'    => count($cols),
            'columns'        => $cols,
            'indexes'        => array_values($indexesByTable[$name] ?? []),
            'foreignKeys'    => array_values($fksByTable[$name] ?? []),
        ];

        if ($includeDdl) {
            try {
                $ddlRow = $db->query('SHOW CREATE TABLE `' . str_replace('`', '``', $name) . '`')->fetch(PDO::FETCH_NUM);
                $entry['createTable'] = $ddlRow[1] ?? null;
            } catch (Throwable $e) {
                $entry['createTable'] = null;
                $entry['createTableError'] = $e->getMessage();
            }
        }

        $tables[] = $entry;
    }

    ok([
        'database'    => $dbName,
        'generatedAt' => gmdate('c'),
        'filters'     => [
            'prefix'  => $prefixRaw,
            'table'   => $singleTable !== '' ? $singleTable : null,
            'counts'  => $exactCounts,
            'ddl'     => $includeDdl,
        ],
        'summary'     => [
            'tableCount'           => count($tables),
            'totalColumns'         => $totalColumns,
            'totalEstimatedRows'   => $totalEstimatedRows,
        ],
        'tables'      => $tables,
    ]);
} catch (Throwable $e) {
    fail('Schema report failed: ' . $e->getMessage(), 500);
}
