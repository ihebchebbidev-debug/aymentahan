<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/schema_repair.php';
$me = require_auth();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

ensure_tasks_schema($db);

function task_to_arr(array $r): array
{
    return [
        'id'            => $r['id'],
        'title'         => $r['title'],
        'description'   => $r['description'] ?? null,
        'assignedTo'    => $r['assigned_to'] ?? '',
        'relatedEntity' => $r['related_entity'] ?? $r['entity_type'] ?? null,
        'relatedId'     => $r['related_id'] ?? $r['entity_id'] ?? null,
        'dueDate'       => $r['due_date'] ?? null,
        'priority'      => task_normalize_priority($r['priority'] ?? 'normal'),
        'status'        => task_normalize_status($r['status'] ?? 'todo'),
        'createdBy'     => $r['created_by'] ?? $r['assigned_to'] ?? '',
        'createdAt'     => $r['created_at'] ?? null,
        'completedAt'   => $r['completed_at'] ?? null,
    ];
}

function task_normalize_priority(mixed $p): string
{
    if (in_array($p, ['low', 'normal', 'high'], true)) {
        return $p;
    }
    if ($p === 0 || $p === '0') {
        return 'low';
    }
    if ($p === 1 || $p === '1') {
        return 'normal';
    }
    if ($p === 2 || $p === '2') {
        return 'high';
    }
    return 'normal';
}

function task_normalize_status(mixed $s): string
{
    if (in_array($s, ['todo', 'in_progress', 'done', 'cancelled'], true)) {
        return $s;
    }
    if ($s === 'pending') {
        return 'todo';
    }
    return 'todo';
}

if ($method === 'GET') {
    $isAdmin = (($me['role'] ?? '') === 'Administrateur');
    $mine = isset($_GET['mine']) && $_GET['mine'] === '1';
    $status = $_GET['status'] ?? null;
    $sql = 'SELECT * FROM crminternet_tasks WHERE 1=1';
    $params = [];
    if ($mine || !$isAdmin) {
        $sql .= ' AND (assigned_to = :u_assigned OR created_by = :u_created)';
        $params[':u_assigned'] = $me['username'];
        $params[':u_created']  = $me['username'];
    }
    if ($status) {
        $sql .= ' AND status = :s';
        $params[':s'] = $status;
    }
    $sql .= ' ORDER BY (status="done") ASC, due_date IS NULL, due_date ASC, priority DESC';
    $s = $db->prepare($sql);
    $s->execute($params);
    $tasks = array_map('task_to_arr', $s->fetchAll(PDO::FETCH_ASSOC));
    ok(['tasks' => $tasks]);
}

if ($method === 'POST') {
    $in = json_input();
    $title = trim($in['title'] ?? '');
    if ($title === '') {
        fail('title requis', 422);
    }
    $id = 'T-' . substr(bin2hex(random_bytes(6)), 0, 10);
    $assigned = $in['assignedTo'] ?? $me['username'];
    $priority = task_normalize_priority($in['priority'] ?? 'normal');
    $status = task_normalize_status($in['status'] ?? 'todo');
    $now = date('Y-m-d H:i:s');
    $s = $db->prepare(
        'INSERT INTO crminternet_tasks (id,title,description,assigned_to,related_entity,related_id,due_date,priority,status,created_by,created_at)
         VALUES (:id,:t,:d,:a,:re,:ri,:du,:p,:st,:cb,:ca)'
    );
    $s->execute([
        ':id' => $id,
        ':t' => $title,
        ':d' => $in['description'] ?? null,
        ':a' => $assigned,
        ':re' => $in['relatedEntity'] ?? null,
        ':ri' => $in['relatedId'] ?? null,
        ':du' => $in['dueDate'] ?? null,
        ':p' => $priority,
        ':st' => $status,
        ':cb' => $me['username'],
        ':ca' => $now,
    ]);
    if ($assigned !== $me['username']) {
        ensure_notifications_schema($db);
        $n = $db->prepare(
            'INSERT INTO crminternet_notifications (id,user_username,title,body,created_at)
             VALUES (:id,:u,:t,:b,:ca)'
        );
        $n->execute([
            ':id' => 'N-' . substr(bin2hex(random_bytes(6)), 0, 10),
            ':u' => $assigned,
            ':t' => 'Nouvelle tâche: ' . $title,
            ':b' => 'Assignée par ' . $me['username'],
            ':ca' => $now,
        ]);
    }
    ok(['id' => $id], 201);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $isAdmin = (($me['role'] ?? '') === 'Administrateur');
    $in = json_input();
    $id = $in['id'] ?? ($_GET['id'] ?? '');
    if (!$id) {
        fail('id requis', 422);
    }
    if (!$isAdmin) {
        $chk = $db->prepare('SELECT 1 FROM crminternet_tasks WHERE id=:id AND (assigned_to=:u1 OR created_by=:u2)');
        $chk->execute([':id' => $id, ':u1' => $me['username'], ':u2' => $me['username']]);
        if (!$chk->fetchColumn()) {
            fail('Accès refusé', 403);
        }
    }
    $sets = [];
    $params = [':id' => $id];
    $map = [
        'title' => 'title',
        'description' => 'description',
        'assignedTo' => 'assigned_to',
        'relatedEntity' => 'related_entity',
        'relatedId' => 'related_id',
        'dueDate' => 'due_date',
        'priority' => 'priority',
        'status' => 'status',
    ];
    foreach ($map as $k => $col) {
        if (!array_key_exists($k, $in)) {
            continue;
        }
        $v = $in[$k];
        if ($k === 'priority') {
            $v = task_normalize_priority($v);
        }
        if ($k === 'status') {
            $v = task_normalize_status($v);
        }
        if ($k === 'assignedTo' && !$isAdmin && $v !== $me['username']) {
            continue;
        }
        $sets[] = "$col = :$k";
        $params[":$k"] = $v;
        if ($k === 'status' && $v === 'done') {
            $sets[] = 'completed_at = :_completed_at';
            $params[':_completed_at'] = date('Y-m-d H:i:s');
        }
    }
    if (!$sets) {
        fail('Aucun champ', 422);
    }
    $db->prepare('UPDATE crminternet_tasks SET ' . implode(', ', $sets) . ' WHERE id=:id')->execute($params);
    ok(['message' => 'Tâche mise à jour']);
}

if ($method === 'DELETE') {
    $isAdmin = (($me['role'] ?? '') === 'Administrateur');
    $id = $_GET['id'] ?? '';
    if (!$id) {
        fail('id requis', 422);
    }
    if (!$isAdmin) {
        $chk = $db->prepare('SELECT 1 FROM crminternet_tasks WHERE id=:id AND (assigned_to=:u1 OR created_by=:u2)');
        $chk->execute([':id' => $id, ':u1' => $me['username'], ':u2' => $me['username']]);
        if (!$chk->fetchColumn()) {
            fail('Accès refusé', 403);
        }
    }
    $s = $db->prepare('DELETE FROM crminternet_tasks WHERE id = :id');
    $s->execute([':id' => $id]);
    ok(['deleted' => $s->rowCount()]);
}

fail('Method not allowed', 405);
