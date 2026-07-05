<?php
/**
 * Web download — full database export as .sql (schema + 100% row data).
 *
 * GET .../export_database.php?token=crm-seed-2026
 *   &prefix=*              all tables (default)
 *   &prefix=crminternet_   CRM tables only
 *   &method=php             force PHP exporter (default on web — works on shared hosting)
 *   &method=mysqldump       use mysqldump to temp file then stream (needs exec + mysqldump)
 *   &method=auto            try mysqldump, else PHP
 *
 * DELETE or protect this file on production (same token as seed_data.php).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/export_database_lib.php';
require_method('GET');

$token = getenv('CRM_EXPORT_TOKEN') ?: (getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026');
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden — add ?token=' . $token, 403);
}

@set_time_limit(0);
@ini_set('memory_limit', '1024M');

$prefixRaw = array_key_exists('prefix', $_GET) ? (string) $_GET['prefix'] : '*';
$prefix = $prefixRaw === '*' ? '' : $prefixRaw;
$method = (string) ($_GET['method'] ?? 'php');
if (!in_array($method, ['auto', 'mysqldump', 'php'], true)) {
    fail('Invalid method — use auto, mysqldump, or php', 400);
}

$db = (new Database())->getConnection();
$dbName = (string) $db->query('SELECT DATABASE()')->fetchColumn();
if ($dbName === '') {
    fail('No database selected', 500);
}

$stamp = gmdate('Y-m-d_His');
$filename = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $dbName) . '_' . $stamp . '.sql';

try {
    if ($method === 'mysqldump' || ($method === 'auto' && crm_export_find_mysqldump() !== null)) {
        $tmp = tempnam(sys_get_temp_dir(), 'crm_sql_');
        if ($tmp === false) {
            throw new RuntimeException('Cannot create temporary export file');
        }
        $sqlPath = $tmp . '.sql';
        @unlink($tmp);

        crm_export_database($db, [
            'prefix' => $prefix,
            'method' => 'mysqldump',
            'output' => $sqlPath,
        ]);

        if (!is_file($sqlPath) || filesize($sqlPath) === 0) {
            throw new RuntimeException('Export file is empty');
        }

        header_remove('Content-Type');
        header_remove('Cache-Control');
        header_remove('Pragma');
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($sqlPath));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');

        readfile($sqlPath);
        @unlink($sqlPath);
        exit;
    }

    header_remove('Content-Type');
    header_remove('Cache-Control');
    header_remove('Pragma');
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');

    crm_export_database($db, [
        'prefix' => $prefix,
        'method' => 'php',
        'output' => 'php://output',
        'batch'  => 500,
    ]);
    exit;
} catch (Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'success' => false,
            'message' => 'Export failed: ' . $e->getMessage(),
        ]);
    }
    exit;
}
