<?php
/**
 * One-shot repair: create crminternet_login_otp on hosts where setup failed (MySQL 5.7).
 * GET http://.../backend/php/repair_login_otp.php
 * DELETE this file after use.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/schema_repair.php';
require_method('GET');

$db = (new Database())->getConnection();
ensure_login_otp_schema($db);

$cols = [];
foreach ($db->query('SHOW COLUMNS FROM crminternet_login_otp') as $r) {
    $cols[] = $r['Field'];
}

ok([
    'ok'      => in_array('user_id', $cols, true),
    'columns' => $cols,
    'message' => in_array('user_id', $cols, true)
        ? 'crminternet_login_otp is ready for OTP login'
        : 'Table repair failed — check columns',
]);
