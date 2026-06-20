<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/otp_helpers.php';
require_once __DIR__ . '/ip_allowlist.php';
require_method('POST');

$in = json_input();
$challenge = trim($in['challenge'] ?? '');
if ($challenge === '' || strlen($challenge) > 40) fail('Requête invalide', 422);

$db = (new Database())->getConnection();
ensure_otp_table($db);

$s = $db->prepare("SELECT o.challenge, o.user_id, o.created_at, o.used,
                          u.email, u.full_name, u.username, u.active
                   FROM crminternet_login_otp o
                   JOIN crminternet_users u ON u.id = o.user_id
                   WHERE o.challenge = :c LIMIT 1");
$s->execute([':c' => $challenge]);
$row = $s->fetch();
if (!$row) fail('Session expirée, veuillez vous reconnecter', 401);
if ((int)$row['used'] === 1) fail('Code déjà utilisé, reconnectez-vous', 401);
if (!$row['active']) fail('Compte désactivé', 403);

$email = trim((string)($row['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Aucune adresse email associée', 422);
}

if (time() - strtotime($row['created_at']) < 30) {
    fail('Veuillez patienter avant de redemander un code.', 429);
}

$tc = $db->prepare("SELECT COUNT(*) FROM crminternet_login_otp
                    WHERE user_id = :u AND created_at > (NOW() - INTERVAL 10 MINUTE)");
$tc->execute([':u' => $row['user_id']]);
if ((int)$tc->fetchColumn() >= 5) {
    fail('Trop de codes envoyés. Veuillez patienter quelques minutes.', 429);
}

$issued = otp_issue_code($db);
$db->prepare("UPDATE crminternet_login_otp
              SET code_hash = :h, expires_at = :e, attempts = 0, used = 0, created_at = NOW()
              WHERE challenge = :c")
   ->execute([
       ':h' => password_hash($issued['code'], PASSWORD_BCRYPT),
       ':e' => $issued['expiresDb'],
       ':c' => $challenge,
   ]);

try {
    otp_send_to_user($db, $row, $issued['code'], client_ip());
} catch (Throwable $e) {
    fail("Échec de l'envoi du code : " . $e->getMessage(), 502);
}

ok(['expiresAt' => $issued['expiresIso'], 'codeLength' => $issued['length']]);
