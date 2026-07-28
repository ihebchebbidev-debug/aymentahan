<?php
// =====================================================================
// Backoffice — Monthly objectives (contracts / migrations)
// GET    list (filters: scope, agentId, entityId, month)
// POST   upsert (UNIQUE scope+agent+entity+period)
// DELETE supprimer un objectif (?id=)
// =====================================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api_limits.php';
require_once __DIR__ . '/backoffice_schema.php';
$me = require_auth();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

ensure_backoffice_objectives_schema($db);

function row_to_obj_bo(array $r): array {
    return [
        'id' => $r['id'],
        'scope' => $r['scope'] ?? 'agent',
        'agentId' => $r['agent_id'] ?? null,
        'entityId' => $r['entity_id'] ?? null,
        'periodMonth' => $r['period_month'] ?? '',
        'targetContracts' => (int)($r['target_contracts'] ?? 0),
        'targetMigrations' => (int)($r['target_migrations'] ?? 0),
        'workingDays' => (int)($r['working_days'] ?? 26),
        'notes' => $r['notes'] ?? '',
    ];
}

if ($method === 'GET') {
    $where = []; $params = [];
    foreach (['scope'=>'scope','agentId'=>'agent_id','entityId'=>'entity_id'] as $q=>$col) {
        if (!empty($_GET[$q])) { $where[] = "$col = :$q"; $params[":$q"] = $_GET[$q]; }
    }
    if (!empty($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])) {
        $where[] = 'period_month = :m'; $params[':m'] = $_GET['month'];
    }
    $sql = 'SELECT * FROM crminternet_backoffice_objectives';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $listLimit = crm_list_limit($_GET['limit'] ?? null, CRM_LIST_DEFAULT_PER_PAGE);
    $sql .= " ORDER BY period_month DESC, scope, agent_id, entity_id LIMIT $listLimit";
    $s = $db->prepare($sql); $s->execute($params);
    ok(['objectives' => array_map('row_to_obj_bo', $s->fetchAll())]);
}

if ($method === 'POST') {
    require_permission($db, $me, 'backoffice.manage_objectives');
    $in = json_input();
    $scope = in_array(($in['scope'] ?? 'agent'), ['agent','entity','global'], true) ? $in['scope'] : 'agent';
    $period = (string)($in['periodMonth'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}$/', $period)) fail('periodMonth (YYYY-MM) requis', 422);
    $agent  = $scope === 'agent'  ? (string)($in['agentId']  ?? '') : null;
    $entity = $scope === 'entity' ? (string)($in['entityId'] ?? '') : null;
    if ($scope === 'agent'  && !$agent)  fail('agentId requis',  422);
    if ($scope === 'entity' && !$entity) fail('entityId requis', 422);

    $find = $db->prepare("SELECT id FROM crminternet_backoffice_objectives
        WHERE scope=:s AND period_month=:p
        AND (agent_id <=> :a) AND (entity_id <=> :e) LIMIT 1");
    $find->execute([':s'=>$scope, ':p'=>$period, ':a'=>$agent, ':e'=>$entity]);
    $existing = $find->fetchColumn();

    $tc = max(0, (int)($in['targetContracts'] ?? 0));
    $tm = max(0, (int)($in['targetMigrations'] ?? 0));
    $wdays = max(1, (int)($in['workingDays'] ?? 26));
    $notes = trim((string)($in['notes'] ?? ''));

    if ($existing) {
        $db->prepare("UPDATE crminternet_backoffice_objectives
            SET target_contracts=:tc, target_migrations=:tm, working_days=:wd, notes=:n
            WHERE id=:id")
           ->execute([':tc'=>$tc, ':tm'=>$tm, ':wd'=>$wdays, ':n'=>$notes, ':id'=>$existing]);
        audit_log($db, $me, 'backoffice_objective.update', 'backoffice_objective', $existing);
        ok(['id'=>$existing, 'updated'=>1]);
    }
    $id = 'BO-' . substr(bin2hex(random_bytes(6)), 0, 10);
    $db->prepare("INSERT INTO crminternet_backoffice_objectives
        (id, scope, agent_id, entity_id, period_month, target_contracts, target_migrations, working_days, notes)
        VALUES (:id,:s,:a,:e,:p,:tc,:tm,:wd,:n)")
       ->execute([
           ':id'=>$id, ':s'=>$scope, ':a'=>$agent, ':e'=>$entity, ':p'=>$period,
           ':tc'=>$tc, ':tm'=>$tm, ':wd'=>$wdays, ':n'=>$notes,
       ]);
    audit_log($db, $me, 'backoffice_objective.create', 'backoffice_objective', $id);
    ok(['id'=>$id, 'created'=>1], 201);
}

if ($method === 'DELETE') {
    require_permission($db, $me, 'backoffice.manage_objectives');
    $id = (string)($_GET['id'] ?? '');
    if ($id === '') fail('id requis', 422);
    $db->prepare('DELETE FROM crminternet_backoffice_objectives WHERE id = :id')->execute([':id'=>$id]);
    audit_log($db, $me, 'backoffice_objective.delete', 'backoffice_objective', $id);
    ok(['deleted'=>1]);
}

fail('Method not allowed', 405);
