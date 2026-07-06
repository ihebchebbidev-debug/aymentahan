<?php
/**
 * CRM terminal migrations (dossiers migration) — peer to contracts.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pipeline_helpers.php';
require_once __DIR__ . '/list_query_helpers.php';
require_once __DIR__ . '/attachment_helpers.php';
require_once __DIR__ . '/contract_info_helpers.php';
require_once __DIR__ . '/crm_terminal_migration_schema.php';
require_once __DIR__ . '/conversion_helpers.php';
require_once __DIR__ . '/custom_field_helpers.php';

$me = require_auth();
$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

schema_ensure_once('terminal_migrations', '20260531', function () use ($db) {
    ensure_terminal_migration_schema($db);
});

function row_to_migration(array $r): array
{
    return [
        'id' => $r['id'] ?? '',
        'opportunityId' => $r['opportunity_id'] ?? null,
        'prospectId' => $r['prospect_id'] ?? null,
        'typeId' => $r['type_id'] ?? null,
        'civility' => $r['civility'] ?? 'M',
        'lastName' => $r['last_name'] ?? '',
        'firstName' => $r['first_name'] ?? '',
        'phone' => $r['phone'] ?? '',
        'phone2' => $r['phone2'] ?? '',
        'animateur' => $r['animateur'] ?? null,
        'ancienLigne' => $r['ancien_ligne'] ?? null,
        'cin' => $r['cin'] ?? '',
        'birthDate' => $r['birth_date'] ?? null,
        'email' => $r['email'] ?? '',
        'city' => $r['city'] ?? '',
        'gouvernorat' => $r['gouvernorat'] ?? '',
        'delegation' => $r['delegation'] ?? '',
        'zone' => $r['zone'] ?? '',
        'address' => $r['address'] ?? '',
        'localisationXy' => $r['localisation_xy'] ?? '',
        'codePostal' => $r['code_postal'] ?? '',
        'comment1' => $r['comment1'] ?? null,
        'comment2' => $r['comment2'] ?? null,
        'source' => $r['source'] ?? '',
        'leadStatus' => $r['lead_status'] ?? null,
        'oldOperator' => $r['old_operator'] ?? '',
        'newOperator' => $r['new_operator'] ?? '',
        'portingNumber' => $r['porting_number'] ?? '',
        'migrationType' => $r['migration_type'] ?? '',
        'requestedDate' => $r['requested_date'] ?? null,
        'completedDate' => $r['completed_date'] ?? null,
        'technicalStatus' => $r['technical_status'] ?? '',
        'externalRef' => $r['external_ref'] ?? '',
        'stageId' => $r['stage_id'] ?? null,
        'workflowStatus' => $r['workflow_status'] ?? '',
        'assignedTo' => $r['assigned_to'] ?? '',
        'validatedAt' => $r['validated_at'] ?? null,
        'validatedBy' => $r['validated_by'] ?? null,
        'notes' => $r['notes'] ?? null,
        'createdBy' => $r['created_by'] ?? null,
        'createdAt' => $r['created_at'] ?? null,
        'updatedAt' => $r['updated_at'] ?? null,
    ];
}

function migration_can_convert(PDO $db, array $me): bool
{
    return user_can_convert_opportunity_to_migration($db, $me);
}

$role = $me['role'] ?? '';
$isAgent = in_array($role, ['Agent', 'AgentSuivi', 'AgentActivation', 'AgentVente'], true);

if ($method === 'GET') {
    require_migration_view($db, $me);
    $id = $_GET['id'] ?? null;
    if ($id) {
        $s = $db->prepare('SELECT * FROM crminternet_migrations WHERE id = :id AND deleted_at IS NULL');
        $s->execute([':id' => $id]);
        $r = $s->fetch(PDO::FETCH_ASSOC);
        if (!$r) {
            fail('Not found', 404);
        }
        ok(['migration' => row_to_migration($r)]);
    }

    $params = parse_list_params([
        'sortable' => [
            'createdAt' => 'created_at',
            'requestedDate' => 'requested_date',
            'completedDate' => 'completed_date',
            'lastName' => 'last_name',
            'firstName' => 'first_name',
            'workflowStatus' => 'workflow_status',
            'technicalStatus' => 'technical_status',
            'assignedTo' => 'assigned_to',
            'phone' => 'phone',
            'cin' => 'cin',
            'oldOperator' => 'old_operator',
            'newOperator' => 'new_operator',
        ],
        'defaultSort' => 'createdAt',
        'defaultDir' => 'desc',
        'maxPerPage' => CRM_LIST_MAX_PER_PAGE,
    ]);

    [$whereSql, $bind] = build_list_where($params, [
        'searchable' => [
            'last_name', 'first_name', 'phone', 'phone2', 'cin', 'email',
            'old_operator', 'new_operator', 'porting_number', 'external_ref',
        ],
        'statusCol' => 'workflow_status',
        'assignedCol' => 'assigned_to',
        'dateCol' => 'created_at',
        'preWhere' => 'deleted_at IS NULL',
        'preParams' => [],
    ]);

    if (!empty($_GET['technicalStatus'])) {
        $whereSql .= ' AND technical_status = :tstat';
        $bind[':tstat'] = $_GET['technicalStatus'];
    }
    if (!empty($_GET['oldOperator'])) {
        $whereSql .= ' AND old_operator LIKE :oo';
        $bind[':oo'] = '%' . $_GET['oldOperator'] . '%';
    }
    if (!empty($_GET['newOperator'])) {
        $whereSql .= ' AND new_operator LIKE :no';
        $bind[':no'] = '%' . $_GET['newOperator'] . '%';
    }

    if ($params['count']) {
        $s = $db->prepare("SELECT COUNT(*) FROM crminternet_migrations WHERE $whereSql");
        $s->execute($bind);
        ok(['total' => (int) $s->fetchColumn()]);
    }

    $listCols = 'id, opportunity_id, prospect_id, last_name, first_name, phone, cin, old_operator, new_operator,
        workflow_status, technical_status, stage_id, assigned_to, requested_date, completed_date, created_at, type_id';
    $selectCols = $params['fields'] === 'list' ? $listCols : '*';
    $orderBy = build_list_order($params);

    if ($params['paginate']) {
        $countS = $db->prepare("SELECT COUNT(*) FROM crminternet_migrations WHERE $whereSql");
        $countS->execute($bind);
        $total = (int) $countS->fetchColumn();
        $sql = "SELECT $selectCols FROM crminternet_migrations WHERE $whereSql
                ORDER BY $orderBy LIMIT {$params['perPage']} OFFSET {$params['offset']}";
        $stmt = $db->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ok([
            'migrations' => array_map('row_to_migration', $rows),
            'page' => $params['page'],
            'per_page' => $params['perPage'],
            'total' => $total,
            'has_more' => ($params['offset'] + count($rows)) < $total,
            'sort' => $params['sortKey'],
            'dir' => strtolower($params['dir']),
        ]);
    }

    $sql = "SELECT $selectCols FROM crminternet_migrations WHERE $whereSql ORDER BY $orderBy";
    $stmt = $db->prepare($sql);
    $stmt->execute($bind);
    ok(['migrations' => array_map('row_to_migration', $stmt->fetchAll(PDO::FETCH_ASSOC))]);
}

if ($method === 'POST') {
    $in = json_input();
    $action = (string)($in['action'] ?? '');

    if ($action === 'revert_to_opportunity') {
        require_permission($db, $me, 'migration.revert');
        $mid = (string)($in['id'] ?? '');
        if ($mid === '') {
            fail('id requis', 422);
        }

        $cur = $db->prepare('SELECT * FROM crminternet_migrations WHERE id = :id AND deleted_at IS NULL');
        $cur->execute([':id' => $mid]);
        $row = $cur->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            fail('Migration introuvable', 404);
        }
        if ($isAgent && ($row['assigned_to'] ?? null) !== ($me['username'] ?? null)) {
            fail('Accès refusé', 403);
        }

        $opportunityId = $row['opportunity_id'] ?? null;
        if (!$opportunityId) {
            fail('Aucune opportunité source liée', 422);
        }

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE crminternet_opportunities
                SET converted_to_migration = 0, migration_id = NULL,
                    converted_at = NULL, reverted_at = NOW()
                WHERE id = :oid')->execute([':oid' => $opportunityId]);

            try {
                attachment_clone_entity($db, 'migration', $mid, 'opportunity', $opportunityId);
            } catch (Throwable $e) { /* best-effort */ }
            try {
                contract_info_clone_entity($db, 'migration', $mid, 'opportunity', $opportunityId, $me['username'] ?? '');
            } catch (Throwable $e) { /* best-effort */ }
            try {
                custom_field_clone_entity($db, 'migration', $mid, 'opportunity', $opportunityId);
            } catch (Throwable $e) { /* best-effort */ }

            $db->prepare('DELETE FROM crminternet_migrations WHERE id = :id')->execute([':id' => $mid]);
            conv_tx_commit($db);
        } catch (Throwable $e) {
            conv_tx_rollback($db);
            fail('Erreur revert: ' . $e->getMessage(), 500);
        }

        log_field_changes($db, 'migration', $mid, ['exists' => 1],
            ['exists' => 0, 'reason' => 'revert_to_opportunity', 'opportunity_id' => $opportunityId],
            $me['username'] ?? '');
        audit_log($db, $me, 'migration.revert', 'migration', $mid, ['opportunityId' => $opportunityId]);
        ok(['message' => 'Migration retournée en opportunité', 'opportunityId' => $opportunityId]);
    }

    fail('action invalide', 422);
}

if ($method === 'PATCH' || $method === 'PUT') {
    require_permission($db, $me, 'migration.edit');
    $in = json_input();
    $mid = $in['id'] ?? ($_GET['id'] ?? '');
    if ($mid === '') {
        fail('id requis', 422);
    }
    $cur = $db->prepare('SELECT * FROM crminternet_migrations WHERE id = :id AND deleted_at IS NULL');
    $cur->execute([':id' => $mid]);
    $existing = $cur->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        fail('Migration introuvable', 404);
    }
    if ($isAgent && ($existing['assigned_to'] ?? '') !== ($me['username'] ?? '')) {
        fail('Accès refusé', 403);
    }

    $sets = ['updated_at = NOW()'];
    $params = [':id' => $mid];
    $username = $me['username'] ?? '';

    /* ---- workflow stage (workflow_status + stage_id) ---------------- */
    $newWorkflow = null;
    if (array_key_exists('workflowStatus', $in)) {
        $newWorkflow = trim((string)$in['workflowStatus']);
    }
    if ($newWorkflow !== null && $newWorkflow !== '') {
        $exists = $db->prepare('SELECT 1 FROM crminternet_migration_stages WHERE name = :n');
        $exists->execute([':n' => $newWorkflow]);
        if (!$exists->fetchColumn()) {
            fail('Statut workflow invalide', 422);
        }

        $sets[] = 'workflow_status = :ws';
        $params[':ws'] = $newWorkflow;

        $sg = $db->prepare('SELECT id, is_won FROM crminternet_migration_stages WHERE name = :n');
        $sg->execute([':n' => $newWorkflow]);
        $stRow = $sg->fetch(PDO::FETCH_ASSOC) ?: [];

        $sets[] = 'stage_id = :sid';
        $params[':sid'] = $stRow['id'] ?? null;

        if (!empty($stRow['is_won'])) {
            $sets[] = 'validated_at = NOW()';
            $sets[] = 'validated_by = :vby';
            $params[':vby'] = $username;
        } else {
            $sets[] = 'validated_at = NULL';
            $sets[] = 'validated_by = NULL';
        }

        if (($existing['workflow_status'] ?? '') !== $newWorkflow) {
            try {
                log_action($db, 'migration', $mid, 'workflowStatus',
                    $existing['workflow_status'] ?? '', $newWorkflow, $username);
            } catch (Throwable $e) { /* best-effort */ }
        }
    }

    /* ---- generic editable fields ------------------------------------- */
    $editable = [
        'civility' => 'civility',
        'lastName' => 'last_name',
        'firstName' => 'first_name',
        'phone' => 'phone',
        'phone2' => 'phone2',
        'animateur' => 'animateur',
        'ancienLigne' => 'ancien_ligne',
        'cin' => 'cin',
        'birthDate' => 'birth_date',
        'email' => 'email',
        'city' => 'city',
        'gouvernorat' => 'gouvernorat',
        'delegation' => 'delegation',
        'zone' => 'zone',
        'address' => 'address',
        'localisationXy' => 'localisation_xy',
        'codePostal' => 'code_postal',
        'comment1' => 'comment1',
        'comment2' => 'comment2',
        'source' => 'source',
        'leadStatus' => 'lead_status',
        'oldOperator' => 'old_operator',
        'newOperator' => 'new_operator',
        'portingNumber' => 'porting_number',
        'migrationType' => 'migration_type',
        'requestedDate' => 'requested_date',
        'completedDate' => 'completed_date',
        'technicalStatus' => 'technical_status',
        'externalRef' => 'external_ref',
        'assignedTo' => 'assigned_to',
        'notes' => 'notes',
        'typeId' => 'type_id',
        'stageId' => 'stage_id',
    ];

    foreach ($editable as $k => $col) {
        if (!array_key_exists($k, $in)) {
            continue;
        }
        $val = $in[$k];

        if ($k === 'civility' && !in_array($val, ['M', 'Mme'], true)) {
            continue;
        }
        if (($k === 'city' || $k === 'gouvernorat') && is_string($val)) {
            $val = strtoupper(trim($val));
        }
        if ($k === 'cin') {
            $val = is_string($val) ? trim($val) : $val;
            if ($val === '' || $val === null) {
                $val = null;
            }
        }
        if (in_array($k, ['birthDate', 'requestedDate', 'completedDate'], true)) {
            if (is_string($val) && strlen($val) >= 10) {
                $val = substr($val, 0, 10);
            }
            if ($val !== null && $val !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$val)) {
                $val = null;
            }
        }
        if ($k === 'localisationXy') {
            $val = prospect_norm_xy($val);
        }
        if ($k === 'codePostal') {
            $val = prospect_norm_cp($val);
        }
        if ($val === '') {
            $val = null;
        }

        $sets[] = "$col = :f_$k";
        $params[":f_$k"] = $val;
    }

    if (count($sets) <= 1) {
        fail('Aucun champ', 422);
    }

    $db->prepare('UPDATE crminternet_migrations SET ' . implode(', ', $sets) . ' WHERE id = :id')->execute($params);

    $after = $db->prepare('SELECT * FROM crminternet_migrations WHERE id = :id');
    $after->execute([':id' => $mid]);
    $afterRow = $after->fetch(PDO::FETCH_ASSOC) ?: [];

    $beforeLog = [];
    $afterLog = [];
    foreach ($editable as $k => $col) {
        if (!array_key_exists($k, $in)) {
            continue;
        }
        $beforeLog[$k] = $existing[$col] ?? null;
        $afterLog[$k] = $afterRow[$col] ?? null;
    }
    if ($newWorkflow !== null && $newWorkflow !== '') {
        $beforeLog['workflowStatus'] = $existing['workflow_status'] ?? '';
        $afterLog['workflowStatus'] = $afterRow['workflow_status'] ?? '';
    }
    if ($beforeLog !== [] || $afterLog !== []) {
        log_field_changes($db, 'migration', $mid, $beforeLog, $afterLog, $username);
    }

    $fresh = $db->prepare('SELECT * FROM crminternet_migrations WHERE id = :id');
    $fresh->execute([':id' => $mid]);
    $freshRow = $fresh->fetch(PDO::FETCH_ASSOC);
    ok(['message' => 'Migration mise à jour', 'migration' => $freshRow ? row_to_migration($freshRow) : null]);
}

if ($method === 'DELETE') {
    require_permission($db, $me, 'migration.delete');
    $id = $_GET['id'] ?? '';
    if ($id === '') {
        fail('id requis', 422);
    }
    $db->prepare('UPDATE crminternet_migrations SET deleted_at = NOW() WHERE id = :id')->execute([':id' => $id]);
    ok(['deleted' => 1]);
}

fail('Method not allowed', 405);
