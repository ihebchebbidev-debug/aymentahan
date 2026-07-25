<?php
require_once __DIR__ . '/config.php';
$me = require_auth();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

function ensure_lead_stages_runtime_schema(PDO $db): void {
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS crminternet_lead_stages (
            id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(80) NOT NULL UNIQUE,
            color VARCHAR(20) NOT NULL DEFAULT 'muted',
            position INT NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    } catch (Throwable $e) {}

    foreach ([
        "ALTER TABLE crminternet_lead_stages ADD COLUMN is_initial TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE crminternet_lead_stages ADD COLUMN is_won TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE crminternet_lead_stages ADD COLUMN is_lost TINYINT(1) NOT NULL DEFAULT 0",
        "ALTER TABLE crminternet_lead_stages ADD COLUMN auto_action VARCHAR(40) NOT NULL DEFAULT 'none'",
    ] as $sql) {
        try { $db->exec($sql); } catch (Throwable $e) {}
    }

    ensure_lead_stages_complete_seed($db);
}

/** Idempotent — seeds all 16 default lead call statuses. */
function ensure_lead_stages_complete_seed(PDO $db): void {
    $rows = [
        ['Ok', 'success', 1, 0, 1, 0],
        ['Att cin', 'warning', 2, 0, 0, 0],
        ['Att confirmation', 'warning', 3, 0, 0, 0],
        ['Rappel', 'info', 4, 0, 0, 0],
        ['deja migré', 'destructive', 5, 0, 0, 1],
        ['migration', 'primary', 6, 0, 0, 0],
        ['Basculement', 'primary', 7, 0, 0, 0],
        ['Ing', 'info', 8, 0, 0, 0],
        ['Nrp', 'muted', 9, 1, 0, 0],
        ['Pas de rep', 'muted', 10, 0, 0, 0],
        ['Pas intersse', 'destructive', 11, 0, 0, 1],
        ['Déjà connecté', 'success', 12, 0, 1, 0],
        ['Autr dde encor', 'info', 13, 0, 0, 0],
        ['Autre', 'muted', 14, 0, 0, 0],
        ['A réinjecter', 'warning', 15, 0, 0, 0],
        ['Réinjecté', 'success', 16, 0, 0, 0],
        ['facture impayé', 'destructive', 17, 0, 0, 1],
    ];

    $chk = $db->prepare('SELECT 1 FROM crminternet_lead_stages WHERE name = :n LIMIT 1');
    $ins = $db->prepare('INSERT INTO crminternet_lead_stages
        (id, name, color, position, is_initial, is_won, is_lost, auto_action)
        VALUES (:id, :n, :c, :p, :i, :w, :l, \'none\')');

    foreach ($rows as [$name, $color, $pos, $isInit, $isWon, $isLost]) {
        try {
            $chk->execute([':n' => $name]);
            if ($chk->fetchColumn()) continue;
            $ins->execute([
                ':id' => 'S-' . $pos,
                ':n' => $name,
                ':c' => $color,
                ':p' => $pos,
                ':i' => $isInit,
                ':w' => $isWon,
                ':l' => $isLost,
            ]);
        } catch (Throwable $e) { /* best effort */ }
    }
}

ensure_lead_stages_runtime_schema($db);

function row_to_lstage(array $r, array $usageMap = []): array {
    return [
        'id'         => $r['id'],
        'name'       => $r['name'],
        'color'      => $r['color'],
        'position'   => (int)$r['position'],
        'isInitial'  => !empty($r['is_initial']),
        'isWon'      => !empty($r['is_won']),
        'isLost'     => !empty($r['is_lost']),
        'autoAction' => $r['auto_action'] ?? 'none',
        'usageCount' => (int)($usageMap[$r['name']] ?? 0),
    ];
}

if ($method === 'GET') {
    $usageMap = [];
    try {
        $uRows = $db->query("SELECT status, COUNT(*) as cnt FROM crminternet_prospects GROUP BY status")->fetchAll();
        foreach ($uRows as $ur) {
            if ($ur['status']) $usageMap[$ur['status']] = (int)$ur['cnt'];
        }
    } catch (Throwable $e) {}

    $rows = $db->query('SELECT * FROM crminternet_lead_stages ORDER BY position ASC, id ASC')->fetchAll();
    ok(['stages' => array_map(fn($r) => row_to_lstage($r, $usageMap), $rows)]);
}

$VALID_AUTO = ['none','convert_opportunity','convert_contract'];

if ($method === 'POST') {
    require_permission($db, $me, 'stage.manage');
    $in = json_input();
    $name = trim($in['name'] ?? '');
    if ($name === '') fail('Le nom du statut est requis', 422);

    $auto = $in['autoAction'] ?? 'none';
    if (!in_array($auto, $VALID_AUTO, true)) $auto = 'none';

    // Auto calculate position if not provided
    $pos = isset($in['position']) ? (int)$in['position'] : 0;
    if ($pos <= 0) {
        $maxPos = (int)$db->query('SELECT MAX(position) FROM crminternet_lead_stages')->fetchColumn();
        $pos = $maxPos + 1;
    }

    $id = 'S-' . substr(bin2hex(random_bytes(4)), 0, 8);
    try {
        $s = $db->prepare('INSERT INTO crminternet_lead_stages
            (id,name,color,position,is_initial,is_won,is_lost,auto_action)
            VALUES (:id,:n,:c,:p,:i,:w,:l,:a)');
        $s->execute([
            ':id'=>$id, ':n'=>$name,
            ':c'=>$in['color'] ?? 'muted', ':p'=>$pos,
            ':i'=> !empty($in['isInitial']) ? 1 : 0,
            ':w'=> !empty($in['isWon']) ? 1 : 0,
            ':l'=> !empty($in['isLost']) ? 1 : 0,
            ':a'=> $auto,
        ]);
        ok(['id'=>$id, 'name'=>$name, 'message' => 'Statut d\'appel créé avec succès'], 201);
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') fail('Un statut avec ce nom existe déjà', 409);
        fail('Erreur: ' . $e->getMessage(), 500);
    }
}

if ($method === 'PUT' || $method === 'PATCH') {
    require_permission($db, $me, 'stage.manage');
    $in = json_input();

    // Reorder action endpoint: PUT /stages.php?action=reorder with body { items: [{ id, position }] }
    if (($_GET['action'] ?? '') === 'reorder' && isset($in['items']) && is_array($in['items'])) {
        $stmt = $db->prepare('UPDATE crminternet_lead_stages SET position = :p WHERE id = :id');
        foreach ($in['items'] as $item) {
            if (!empty($item['id'])) {
                $stmt->execute([':id' => $item['id'], ':p' => (int)($item['position'] ?? 0)]);
            }
        }
        ok(['message' => 'Ordre mis à jour']);
    }

    $id = $in['id'] ?? ($_GET['id'] ?? '');
    if (!$id) fail('id requis', 422);

    // If updating name, optionally update prospects if requested
    $oldStage = $db->prepare('SELECT name FROM crminternet_lead_stages WHERE id = :id');
    $oldStage->execute([':id' => $id]);
    $oldName = $oldStage->fetchColumn();

    $map = [
        'name'=>'name','color'=>'color','position'=>'position',
        'isInitial'=>'is_initial','isWon'=>'is_won','isLost'=>'is_lost',
        'autoAction'=>'auto_action',
    ];
    $sets = []; $params = [':id'=>$id];
    foreach ($map as $k=>$col) {
        if (!array_key_exists($k,$in)) continue;
        $v = $in[$k];
        if ($k==='position') $v = (int)$v;
        elseif (in_array($k, ['isInitial','isWon','isLost'], true)) $v = $v ? 1 : 0;
        elseif ($k==='autoAction' && !in_array($v, $VALID_AUTO, true)) continue;
        $sets[] = "$col = :$k"; $params[":$k"] = $v;
    }
    if (!$sets) fail('Aucun champ à mettre à jour', 422);

    $db->prepare('UPDATE crminternet_lead_stages SET '.implode(', ',$sets).' WHERE id = :id')->execute($params);

    // Cascade name rename to prospects if name was changed
    $newName = trim($in['name'] ?? '');
    if ($oldName && $newName !== '' && $newName !== $oldName) {
        try {
            $uProp = $db->prepare('UPDATE crminternet_prospects SET status = :new WHERE status = :old');
            $uProp->execute([':new' => $newName, ':old' => $oldName]);
        } catch (Throwable $e) {}
    }

    ok(['message' => 'Statut d\'appel mis à jour']);
}

if ($method === 'DELETE') {
    require_permission($db, $me, 'stage.manage');
    $id = $_GET['id'] ?? '';
    if (!$id) fail('id requis', 422);

    $stmt = $db->prepare('SELECT name FROM crminternet_lead_stages WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $stageName = $stmt->fetchColumn();

    if (!$stageName) fail('Statut introuvable', 444);

    $force = !empty($_GET['force']);
    if (!$force) {
        $cStmt = $db->prepare('SELECT COUNT(*) FROM crminternet_prospects WHERE status = :n');
        $cStmt->execute([':n' => $stageName]);
        $cnt = (int)$cStmt->fetchColumn();
        if ($cnt > 0) {
            fail("Ce statut est actuellement utilisé par $cnt prospect(s).", 409, [
                'inUse' => true,
                'prospectCount' => $cnt,
                'name' => $stageName
            ]);
        }
    }

    $s = $db->prepare('DELETE FROM crminternet_lead_stages WHERE id = :id');
    $s->execute([':id'=>$id]);
    ok(['deleted' => $s->rowCount(), 'message' => 'Statut d\'appel supprimé']);
}

fail('Method not allowed', 405);
