<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/crm_terminal_migration_schema.php';
$me = require_auth();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

ensure_migration_stages_schema($db);

function row_to_mstage(array $r): array
{
    return [
        'id' => $r['id'],
        'name' => $r['name'],
        'color' => $r['color'],
        'position' => (int) $r['position'],
        'isInitial' => !empty($r['is_initial']),
        'isWon' => !empty($r['is_won']),
        'isLost' => !empty($r['is_lost']),
        'autoAction' => $r['auto_action'] ?? 'none',
    ];
}

if ($method === 'GET') {
    require_migration_view($db, $me);
    $rows = $db->query('SELECT * FROM crminternet_migration_stages ORDER BY position, id')->fetchAll(PDO::FETCH_ASSOC);
    ok(['stages' => array_map('row_to_mstage', $rows)]);
}

require_permission($db, $me, 'migration.stages');

if ($method === 'POST') {
    $in = json_input();
    $name = trim($in['name'] ?? '');
    if ($name === '') {
        fail('name requis', 422);
    }
    $id = 'MS-' . substr(bin2hex(random_bytes(6)), 0, 8);
    $db->prepare(
        'INSERT INTO crminternet_migration_stages (id,name,color,position,is_initial,is_won,is_lost,auto_action)
         VALUES (:id,:n,:c,:p,:i,:w,:l,\'none\')'
    )->execute([
        ':id' => $id, ':n' => $name, ':c' => $in['color'] ?? 'muted', ':p' => (int) ($in['position'] ?? 0),
        ':i' => !empty($in['isInitial']) ? 1 : 0, ':w' => !empty($in['isWon']) ? 1 : 0,
        ':l' => !empty($in['isLost']) ? 1 : 0,
    ]);
    ok(['id' => $id], 201);
}

if ($method === 'PUT' || $method === 'PATCH') {
    $in = json_input();
    $id = $in['id'] ?? ($_GET['id'] ?? '');
    if (!$id) {
        fail('id requis', 422);
    }
    $map = [
        'name' => 'name', 'color' => 'color', 'position' => 'position',
        'isInitial' => 'is_initial', 'isWon' => 'is_won', 'isLost' => 'is_lost',
    ];
    $sets = [];
    $params = [':id' => $id];
    foreach ($map as $k => $col) {
        if (!array_key_exists($k, $in)) {
            continue;
        }
        $v = $in[$k];
        if ($k === 'position') {
            $v = (int) $v;
        } elseif (in_array($k, ['isInitial', 'isWon', 'isLost'], true)) {
            $v = $v ? 1 : 0;
        }
        $sets[] = "$col = :$k";
        $params[":$k"] = $v;
    }
    if (!$sets) {
        fail('Aucun champ', 422);
    }
    $db->prepare('UPDATE crminternet_migration_stages SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);
    ok(['message' => 'Étape mise à jour']);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (!$id) {
        fail('id requis', 422);
    }
    $db->prepare('DELETE FROM crminternet_migration_stages WHERE id = :id')->execute([':id' => $id]);
    ok(['deleted' => 1]);
}

fail('Method not allowed', 405);
