<?php
// =====================================================================
// CRM MVP — Pointage / Présence (heures travaillées)
// =====================================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api_limits.php';

$me = require_auth();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// --- GET aggregates (per-day / range summary) -----------------------
if ($method === 'GET' && $action === 'aggregates') {
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    $username = $_GET['username'] ?? null;

    // If not provided, derive start/end from ?month=YYYY-MM
    if (!$start || !$end) {
        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) fail('month invalide', 422);
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
    }

    // Restrict to non-privileged users
    $isPriv = in_array($me['role'], ['Administrateur','Manager'], true);
    if (!$isPriv) $username = $me['username'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        fail('dates invalides', 422);
    }

    $params = [':start' => $start . ' 00:00:00', ':end' => $end . ' 23:59:59'];
    $sql = "SELECT DATE(login_at) as period, SUM(TIMESTAMPDIFF(SECOND, login_at, COALESCE(logout_at, NOW()))) as seconds, COUNT(*) as sessions
            FROM crminternet_attendance WHERE login_at BETWEEN :start AND :end";
    if ($username) { $sql .= " AND username = :u"; $params[':u'] = $username; }
    $sql .= " GROUP BY DATE(login_at) ORDER BY DATE(login_at) ASC";

    $st = $db->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll();

    $out = array_map(function($r){
        return [
            'period' => $r['period'],
            'seconds' => (int)$r['seconds'],
            'minutes' => (int)round(((int)$r['seconds'])/60),
            'sessions' => (int)$r['sessions'],
        ];
    }, $rows);

    ok(['aggregates' => $out, 'start' => $start, 'end' => $end]);
}

// --- GET summary by user / role / team -------------------------------
if ($method === 'GET' && $action === 'summary') {
    $start = $_GET['start'] ?? null;
    $end = $_GET['end'] ?? null;
    $username = $_GET['username'] ?? null;

    if (!$start || !$end) {
        $month = $_GET['month'] ?? date('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) fail('month invalide', 422);
        $start = $month . '-01';
        $end = date('Y-m-t', strtotime($start));
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
        fail('dates invalides', 422);
    }

    $isPriv = in_array($me['role'], ['Administrateur','Manager'], true);
    if (!$isPriv) $username = $me['username'];

    $where = 'a.login_at BETWEEN :start AND :end';
    $params = [':start' => $start . ' 00:00:00', ':end' => $end . ' 23:59:59'];
    if ($username) { $where .= ' AND a.username = :u'; $params[':u'] = $username; }

    $userSql = "SELECT a.user_id, a.username, COALESCE(u.role, '') AS role, COALESCE(u.team, '') AS team,\n"
             . "       SUM(TIMESTAMPDIFF(SECOND, a.login_at, COALESCE(a.logout_at, NOW()))) AS seconds,\n"
             . "       COUNT(*) AS sessions\n"
             . "FROM crminternet_attendance a\n"
             . "LEFT JOIN crminternet_users u ON u.id = a.user_id\n"
             . "WHERE $where\n"
             . "GROUP BY a.user_id, a.username, role, team\n"
             . "ORDER BY a.username ASC";

    $st = $db->prepare($userSql);
    $st->execute($params);
    $users = array_map(function($r){
        $seconds = (int)$r['seconds'];
        $sessions = (int)$r['sessions'];
        return [
            'userId' => $r['user_id'],
            'username' => $r['username'],
            'role' => $r['role'],
            'team' => $r['team'],
            'seconds' => $seconds,
            'minutes' => (int)round($seconds / 60),
            'sessions' => $sessions,
            'avgMinutes' => $sessions ? round($seconds / 60 / $sessions, 1) : 0,
        ];
    }, $st->fetchAll());

    $groupSql = function(string $field, string $label) use ($where, $params, $db) {
        $sql = "SELECT COALESCE(u.$field, '') AS group_label, "
             . "       SUM(TIMESTAMPDIFF(SECOND, a.login_at, COALESCE(a.logout_at, NOW()))) AS seconds, "
             . "       COUNT(*) AS sessions "
             . "FROM crminternet_attendance a "
             . "LEFT JOIN crminternet_users u ON u.id = a.user_id "
             . "WHERE $where "
             . "GROUP BY group_label "
             . "ORDER BY group_label ASC";
        $st = $db->prepare($sql);
        $st->execute($params);
        return array_map(function($r) {
            $seconds = (int)$r['seconds'];
            $sessions = (int)$r['sessions'];
            return [
                'group' => $r['group_label'] ?: 'N/A',
                'seconds' => $seconds,
                'minutes' => (int)round($seconds / 60),
                'sessions' => $sessions,
                'avgMinutes' => $sessions ? round($seconds / 60 / $sessions, 1) : 0,
            ];
        }, $st->fetchAll());
    };

    $roles = $groupSql('role', 'role');
    $teams = $groupSql('team', 'team');

    ok(['users' => $users, 'roles' => $roles, 'teams' => $teams, 'start' => $start, 'end' => $end]);
}

function ensure_attendance(PDO $db): void {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS crminternet_attendance (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(40) NOT NULL,
            username VARCHAR(80) NOT NULL,
            login_at DATETIME NOT NULL,
            logout_at DATETIME NULL,
            total_minutes INT NOT NULL DEFAULT 0,
            ip VARCHAR(64) NULL,
            user_agent VARCHAR(255) NULL,
            INDEX idx_user_date (user_id, login_at),
            INDEX idx_username  (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}
}
ensure_attendance($db);

function att_to_arr(array $r): array {
    return [
        'id'           => (int)$r['id'],
        'userId'       => $r['user_id'],
        'username'     => $r['username'],
        'loginAt'      => $r['login_at'],
        'logoutAt'     => $r['logout_at'],
        'totalMinutes' => (int)$r['total_minutes'],
        'ip'           => $r['ip'],
    ];
}

// --- POST clock-in (peut être appelé en post-login) -------------------
if ($method === 'POST' && $action === 'clock_in') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    // Évite les double-clock-in : si une session ouverte existe, retourne-la
    $s = $db->prepare("SELECT id FROM crminternet_attendance
                       WHERE user_id=:u AND logout_at IS NULL ORDER BY id DESC LIMIT 1");
    $s->execute([':u' => $me['sub']]);
    $open = $s->fetchColumn();
    if ($open) ok(['id' => (int)$open, 'reused' => true]);
    $i = $db->prepare("INSERT INTO crminternet_attendance
        (user_id, username, login_at, ip, user_agent)
        VALUES (:u, :n, NOW(), :ip, :ua)");
    $i->execute([':u' => $me['sub'], ':n' => $me['username'], ':ip' => $ip, ':ua' => $ua]);
    ok(['id' => (int)$db->lastInsertId()], 201);
}

// --- POST clock-out ---------------------------------------------------
if ($method === 'POST' && $action === 'clock_out') {
    $s = $db->prepare("SELECT id, login_at FROM crminternet_attendance
                       WHERE user_id=:u AND logout_at IS NULL ORDER BY id DESC LIMIT 1");
    $s->execute([':u' => $me['sub']]);
    $row = $s->fetch();
    if (!$row) ok(['message' => 'Aucune session ouverte']);
    $u = $db->prepare("UPDATE crminternet_attendance
        SET logout_at = NOW(),
            total_minutes = TIMESTAMPDIFF(MINUTE, login_at, NOW())
        WHERE id = :id");
    $u->execute([':id' => $row['id']]);
    ok(['id' => (int)$row['id'], 'message' => 'Pointage fermé']);
}

// --- GET liste / synthèse --------------------------------------------
if ($method === 'GET') {
    $month = $_GET['month'] ?? date('Y-m');
    $username = $_GET['username'] ?? null;
    if (!preg_match('/^\d{4}-\d{2}$/', $month)) fail('month invalide', 422);

    // Restreindre aux non-Admin/Manager : ils ne voient qu'eux-mêmes
    $isPriv = in_array($me['role'], ['Administrateur','Manager'], true);
    if (!$isPriv) $username = $me['username'];

    $params = [':m' => $month . '%'];
    $sql = "SELECT * FROM crminternet_attendance WHERE login_at LIKE :m";
    if ($username) { $sql .= " AND username = :u"; $params[':u'] = $username; }
    $listLimit = crm_list_limit($_GET['limit'] ?? null, CRM_LIST_DEFAULT_PER_PAGE);
    $sql .= " ORDER BY login_at DESC LIMIT $listLimit";
    $st = $db->prepare($sql); $st->execute($params);
    $rows = array_map('att_to_arr', $st->fetchAll());

    // Synthèse par utilisateur
    $sumSql = "SELECT username, SUM(total_minutes) AS total, COUNT(*) AS days
               FROM crminternet_attendance WHERE login_at LIKE :m";
    $sumP = [':m' => $month . '%'];
    if ($username) { $sumSql .= " AND username = :u"; $sumP[':u'] = $username; }
    $sumSql .= " GROUP BY username ORDER BY username";
    $sm = $db->prepare($sumSql); $sm->execute($sumP);
    $summary = array_map(function($r){
        return [
            'username' => $r['username'],
            'totalMinutes' => (int)$r['total'],
            'totalHours' => round(((int)$r['total'])/60, 2),
            'sessions' => (int)$r['days'],
        ];
    }, $sm->fetchAll());

    ok(['attendance' => $rows, 'summary' => $summary, 'month' => $month]);
}

fail('Méthode non supportée', 405);
