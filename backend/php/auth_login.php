<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/otp_helpers.php';
require_once __DIR__ . '/ip_allowlist.php';
require_method('POST');

$in = json_input();
$username = trim($in['username'] ?? '');
$password = (string) ($in['password'] ?? '');
$newEmail = trim($in['newEmail'] ?? '');

if ($username === '' || $password === '') {
    fail('Identifiants requis', 422);
}
if (strlen($username) > 80 || strlen($password) > 200) {
    fail('Identifiants invalides', 422);
}

$db = (new Database())->getConnection();
ensure_must_change_column($db);
ensure_otp_table($db);

$stmt = $db->prepare('SELECT id, username, full_name, email, password_hash, role, COALESCE(team, NULL) AS team, active,
                             COALESCE(must_change_password, 0) AS must_change_password
                      FROM crminternet_users WHERE username = :username OR email = :email LIMIT 1');
$stmt->execute([':username' => $username, ':email' => $username]);
$user = $stmt->fetch();

if (!$user || !$user['active'] || !password_verify($password, $user['password_hash'])) {
    audit_log($db, null, 'login_failed', 'user', $username, [
        'reason' => !$user ? 'unknown_user' : (!$user['active'] ? 'disabled' : 'bad_password'),
    ], 401);
    fail('Identifiants invalides', 401);
}

$needsEmailSetup = bootstrap_admin_needs_real_email($user);

if ($needsEmailSetup) {
    if ($newEmail === '') {
        ok([
            'emailChangeRequired' => true,
            'currentEmail'        => $user['email'],
            'message'             => 'Veuillez renseigner votre adresse email réelle. Le code de vérification y sera envoyé.',
        ]);
    }
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 160) {
        fail('Adresse email invalide', 422);
    }
    if (strcasecmp($newEmail, 'admin@crminternet.local') === 0) {
        fail('Choisissez une adresse email réelle (pas l\'adresse par défaut).', 422);
    }
    $dup = $db->prepare('SELECT id FROM crminternet_users WHERE email = :e AND id <> :id LIMIT 1');
    $dup->execute([':e' => $newEmail, ':id' => $user['id']]);
    if ($dup->fetch()) {
        fail('Cette adresse email est déjà utilisée.', 409);
    }
    $db->prepare('UPDATE crminternet_users SET email = :e WHERE id = :id')
       ->execute([':e' => $newEmail, ':id' => $user['id']]);
    $user['email'] = $newEmail;
    audit_log($db, ['username' => $user['username'], 'role' => $user['role']], 'profile_update', 'user', $user['username'], [
        'field' => 'email',
        'from'  => 'admin@crminternet.local',
    ]);
}

$email = trim((string) ($user['email'] ?? ''));
$clientIp = ip_allowlist_client_ip();
if ($clientIp === '' && function_exists('client_ip')) {
    $clientIp = client_ip();
}
$onAllowlist = ip_is_allowlisted($db, $clientIp);
$skipOtp = otp_login_skip($db, $clientIp, $needsEmailSetup);

if ($skipOtp) {
    $token = jwt_sign([
        'sub'      => $user['id'],
        'username' => $user['username'],
        'role'     => $user['role'],
    ]);
    $loginMethod = $onAllowlist ? 'allowlist' : 'otp_disabled';
    audit_log($db, ['username' => $user['username'], 'role' => $user['role']], 'login', 'user', $user['username'], [
        'method'    => $loginMethod,
        'client_ip' => $clientIp,
    ]);
    ok([
        'token'       => $token,
        'user'        => otp_user_response($user),
        'loginMethod' => $loginMethod,
    ]);
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Aucune adresse email valide sur ce compte. Contactez un administrateur.', 422);
}

$tc = $db->prepare('SELECT COUNT(*) FROM crminternet_login_otp
                    WHERE user_id = :u AND created_at > (NOW() - INTERVAL 10 MINUTE)');
$tc->execute([':u' => $user['id']]);
if ((int) $tc->fetchColumn() >= 5) {
    fail('Trop de codes envoyés. Veuillez patienter quelques minutes.', 429);
}

$issued = otp_issue_code($db);
$challenge = 'OTP-' . bin2hex(random_bytes(12));

$ins = $db->prepare('INSERT INTO crminternet_login_otp
    (challenge, user_id, code_hash, expires_at, attempts, used, created_at)
    VALUES (:c, :u, :h, :e, 0, 0, NOW())');
$ins->execute([
    ':c' => $challenge,
    ':u' => $user['id'],
    ':h' => password_hash($issued['code'], PASSWORD_BCRYPT),
    ':e' => $issued['expiresDb'],
]);

try {
    otp_send_to_user($db, $user, $issued['code'], $clientIp);
} catch (Throwable $e) {
    $db->prepare('DELETE FROM crminternet_login_otp WHERE challenge = :c')->execute([':c' => $challenge]);
    fail("Impossible d'envoyer le code par email. " . $e->getMessage(), 502);
}

audit_log($db, ['username' => $user['username'], 'role' => $user['role']], 'otp_sent', 'user', $user['username'], [
    'challenge' => $challenge,
    'client_ip' => $clientIp,
    'allowlisted' => false,
]);

ok([
    'otpRequired' => true,
    'challenge'   => $challenge,
    'maskedEmail' => otp_mask_email($email),
    'expiresAt'   => $issued['expiresIso'],
    'codeLength'  => $issued['length'],
]);
