<?php
/**
 * Shared login OTP helpers (auth_login, auth_otp_resend, auth_otp_verify).
 */
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/schema_repair.php';

function ensure_otp_table(PDO $db): void {
    ensure_login_otp_schema($db);
}

/** Default setup admin still on placeholder email — must set a real address before OTP. */
function bootstrap_admin_needs_real_email(array $user): bool
{
    return strcasecmp(trim((string) ($user['username'] ?? '')), 'AymenAdmin') === 0
        && strcasecmp(trim((string) ($user['email'] ?? '')), 'admin@crminternet.local') === 0;
}

/**
 * Skip OTP on login when:
 * - IP is in otp_ip_allowlist (Settings → Sécurité), OR
 * - otp.enabled is off globally.
 * Outside the allowlist → OTP email is required.
 */
function otp_login_skip(PDO $db, string $clientIp, bool $needsEmailSetup): bool
{
    if ($needsEmailSetup) {
        return false;
    }
    if (!otp_setting_bool($db, 'otp.enabled', true)) {
        return true;
    }
    return ip_is_allowlisted($db, $clientIp);
}

function otp_setting_raw(PDO $db, string $key): ?string {
    try {
        if (function_exists('ensure_settings_schema')) {
            ensure_settings_schema($db);
        }
        $s = $db->prepare('SELECT value FROM crminternet_settings WHERE scope = "global" AND setting_key = :k LIMIT 1');
        $s->execute([':k' => $key]);
        $v = $s->fetchColumn();
        return $v === false ? null : (string)$v;
    } catch (Throwable $e) {
        return null;
    }
}

function otp_setting_bool(PDO $db, string $key, bool $default): bool {
    $v = otp_setting_raw($db, $key);
    if ($v === null) return $default;
    $v = strtolower(trim($v));
    if (in_array($v, ['1', 'true', 'yes', 'on'], true)) return true;
    if (in_array($v, ['0', 'false', 'no', 'off'], true)) return false;
    return $default;
}

function otp_setting_int(PDO $db, string $key, int $default): int {
    $v = otp_setting_raw($db, $key);
    if ($v === null || !is_numeric($v)) return $default;
    return (int)$v;
}

function otp_code_length(PDO $db): int {
    return max(4, min(8, otp_setting_int($db, 'otp.code_length', 4)));
}

function otp_ttl_minutes(PDO $db): int {
    return max(5, min(60, otp_setting_int($db, 'otp.ttl_minutes', 10)));
}

/** @return array{code:string,length:int,expiresDb:string,expiresIso:string} */
function otp_issue_code(PDO $db): array {
    $length = otp_code_length($db);
    $ttl    = otp_ttl_minutes($db);
    $max    = (10 ** $length) - 1;
    $code   = str_pad((string)random_int(0, $max), $length, '0', STR_PAD_LEFT);
    $dt     = new DateTime("+{$ttl} minutes");
    return [
        'code'       => $code,
        'length'     => $length,
        'expiresDb'  => $dt->format('Y-m-d H:i:s'),
        'expiresIso' => $dt->format(DateTime::ATOM),
    ];
}

function otp_mask_email(string $email): string {
    [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($domain === '') return $email;
    $visible = mb_substr($local, 0, 2);
    $masked  = $visible . str_repeat('•', max(2, mb_strlen($local) - 2));
    return $masked . '@' . $domain;
}

function otp_send_to_user(PDO $db, array $user, string $code, string $clientIp): void {
    $email = trim((string)($user['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Adresse email invalide sur ce compte');
    }
    $name = $user['full_name'] ?? $user['username'] ?? 'Utilisateur';
    [$subject, $html, $text] = build_otp_email($code, $name);
    crm_mail_send($email, $name, $subject, $html, $text);

    foreach (admin_copy_emails($db) as $adminEmail) {
        if (strcasecmp($adminEmail, $email) === 0) continue;
        try {
            $adminSubject = '[COPIE ADMIN] ' . $subject . ' — ' . ($user['username'] ?? '');
            $adminHtml = '<div style="background:#fef3c7;border:1px solid #f59e0b;padding:10px;margin-bottom:12px;border-radius:6px;font-family:sans-serif;font-size:12px;color:#92400e;">'
                . 'Copie administrateur — Code OTP pour <strong>' . htmlspecialchars((string)($user['username'] ?? '')) . '</strong> ('
                . htmlspecialchars($email) . ') depuis l\'IP <strong>' . htmlspecialchars($clientIp) . '</strong>.'
                . '</div>' . $html;
            crm_mail_send($adminEmail, 'Administrateur', $adminSubject, $adminHtml, $text);
        } catch (Throwable $e) { /* best-effort */ }
    }
}

function otp_user_response(array $user): array {
    return [
        'id'                 => $user['id'] ?? $user['uid'] ?? '',
        'username'           => $user['username'],
        'fullName'           => $user['full_name'] ?? $user['fullName'] ?? '',
        'email'              => $user['email'],
        'role'               => $user['role'],
        'team'               => $user['team'] ?? null,
        'active'             => (bool)($user['active'] ?? true),
        'mustChangePassword' => (bool)($user['must_change_password'] ?? $user['mustChangePassword'] ?? false),
    ];
}
