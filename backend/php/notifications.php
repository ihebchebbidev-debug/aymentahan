<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api_limits.php';
require_once __DIR__ . '/schema_repair.php';
$me = require_auth();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

ensure_notifications_schema($db);

function notif_to_arr(array $r): array {
    $readAt = $r['read_at'] ?? null;
    if ($readAt === null && array_key_exists('is_read', $r)) {
        $readAt = !empty($r['is_read']) ? ($r['created_at'] ?? date('Y-m-d H:i:s')) : null;
    }
    return [
        'id'        => $r['id'],
        'user'      => $r['user_username'],
        'title'     => $r['title'],
        'body'      => $r['body'] ?? $r['message'] ?? null,
        'link'      => $r['link'] ?? null,
        'read'      => $readAt !== null,
        'readAt'    => $readAt,
        'createdAt' => $r['created_at'],
    ];
}

if ($method === 'GET') {
    $unread = isset($_GET['unread']) && $_GET['unread'] === '1';
    $limit = crm_list_limit($_GET['limit'] ?? null, CRM_LIST_DEFAULT_PER_PAGE);
    $offset = crm_list_offset($_GET['offset'] ?? 0);
    $sql = 'SELECT * FROM crminternet_notifications WHERE user_username = :u' . ($unread ? ' AND read_at IS NULL' : '') .
           " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
    $s = $db->prepare($sql);
    $s->execute([':u' => $me['username']]);
    $rows = array_map('notif_to_arr', $s->fetchAll());
    $count = $db->prepare('SELECT COUNT(*) FROM crminternet_notifications WHERE user_username=:u AND read_at IS NULL');
    $count->execute([':u'=>$me['username']]);
    ok(['notifications' => $rows, 'unread' => (int)$count->fetchColumn()]);
}

if ($method === 'POST') {
    // Create a notification (admin/manager can target other crminternet_users; everyone can self-notify)
    $in = json_input();
    $title = trim($in['title'] ?? '');
    if ($title === '') fail('title requis', 422);
    $target = $in['user'] ?? $me['username'];
    if ($target !== $me['username'] && !in_array($me['role'] ?? '', ['Administrateur','Manager'], true)) {
        fail('Forbidden', 403);
    }
    $id = 'N-' . substr(bin2hex(random_bytes(6)), 0, 10);
    $s = $db->prepare('INSERT INTO crminternet_notifications (id,user_username,title,body,link,created_at) VALUES (:id,:u,:t,:b,:l,NOW())');
    $s->execute([':id'=>$id, ':u'=>$target, ':t'=>$title, ':b'=>$in['body']??null, ':l'=>$in['link']??null]);
    ok(['id' => $id], 201);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $in = json_input();
    $id = $in['id'] ?? ($_GET['id'] ?? null);
    $all = !empty($in['all']);
    if ($all) {
        $s = $db->prepare('UPDATE crminternet_notifications SET read_at = NOW() WHERE user_username = :u AND read_at IS NULL');
        $s->execute([':u'=>$me['username']]);
        ok(['updated' => $s->rowCount()]);
    }
    if (!$id) fail('id requis', 422);
    $s = $db->prepare('UPDATE crminternet_notifications SET read_at = NOW() WHERE id = :id AND user_username = :u');
    $s->execute([':id'=>$id, ':u'=>$me['username']]);
    ok(['updated' => $s->rowCount()]);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) fail('id requis', 422);
    $s = $db->prepare('DELETE FROM crminternet_notifications WHERE id = :id AND user_username = :u');
    $s->execute([':id'=>$id, ':u'=>$me['username']]);
    ok(['deleted' => $s->rowCount()]);
}

fail('Method not allowed', 405);
