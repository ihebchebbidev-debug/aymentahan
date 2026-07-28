<?php
// =====================================================================
// Backoffice — Dashboard (targets + progress)
// GET ?month=YYYY-MM[&entityId=][&agentId=]
// =====================================================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/backoffice_schema.php';
$me = require_auth();
$db = (new Database())->getConnection();

ensure_backoffice_objectives_schema($db);

$month = (string)($_GET['month'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-\d{2}$/', $month)) fail('month (YYYY-MM) requis', 422);
$entityId = !empty($_GET['entityId']) ? (string)$_GET['entityId'] : null;
$agentId  = !empty($_GET['agentId'])  ? (string)$_GET['agentId']  : null;

// Basic permission: any authenticated user can read; non-admins see their own scope.
$role = $me['role'] ?? '';
$canAll = ($role === 'Administrateur' || $role === 'Manager')
       || (function_exists('user_has_permission') && user_has_permission($db, $me, 'backoffice.view_objectives'));
$currentUserId = trim((string)($me['sub'] ?? $me['id'] ?? ''));
if (!$canAll) {
    $agentId = $currentUserId;
}

// Lookup targets (priority: agent > entity > global)
$targetContracts = 0;
$targetMigrations = 0;
$workingDays = 26;
$notes = null;
$lookup = function(string $scope, ?string $a, ?string $e) use ($db, $month, &$targetContracts, &$targetMigrations, &$workingDays, &$notes) {
    if ($targetContracts || $targetMigrations) return;
    $st = $db->prepare("SELECT * FROM crminternet_backoffice_objectives
        WHERE scope=:s AND period_month=:p
        AND (agent_id <=> :a) AND (entity_id <=> :e) LIMIT 1");
    $st->execute([':s'=>$scope, ':p'=>$month, ':a'=>$a, ':e'=>$e]);
    $row = $st->fetch();
    if ($row) {
        $targetContracts = (int)($row['target_contracts'] ?? 0);
        $targetMigrations = (int)($row['target_migrations'] ?? 0);
        $workingDays = isset($row['working_days']) ? max(1, (int)$row['working_days']) : $workingDays;
        $notes = $row['notes'] ?? null;
    }
};
if ($agentId)  $lookup('agent',  $agentId,  null);
if ($entityId) $lookup('entity', null,      $entityId);
$lookup('global', null, null);

$monthStart = $month . '-01';
$today = date('Y-m-d');

// Contracts: count by signature_date
$cSql = "SELECT COUNT(*) FROM crminternet_contracts WHERE DATE_FORMAT(signature_date,'%Y-%m') = :m";
$cParams = [':m' => $month];
if ($agentId)  { $cSql .= ' AND assigned_to = :ag';  $cParams[':ag'] = $agentId; }
if ($entityId) { $cSql .= ' AND partner = :ent';      $cParams[':ent'] = $entityId; }
$cSt = $db->prepare($cSql); $cSt->execute($cParams); $contractsMonth = (int)$cSt->fetchColumn();

$cTodaySql = "SELECT COUNT(*) FROM crminternet_contracts WHERE signature_date = :today";
$cTodayParams = [':today' => $today];
if ($agentId)  { $cTodaySql .= ' AND assigned_to = :ag';  $cTodayParams[':ag'] = $agentId; }
if ($entityId) { $cTodaySql .= ' AND partner = :ent';      $cTodayParams[':ent'] = $entityId; }
$ct = $db->prepare($cTodaySql); $ct->execute($cTodayParams); $contractsToday = (int)$ct->fetchColumn();

// Migrations: count validated/completed migrations using validated_at or completed_date
$mSql = "SELECT COUNT(*) FROM crminternet_migrations WHERE (DATE_FORMAT(validated_at,'%Y-%m') = :m OR DATE_FORMAT(completed_date,'%Y-%m') = :m) AND (workflow_status = 'Validé' OR stage_id = 'MS-4')";
$mParams = [':m' => $month];
if ($agentId)  { $mSql .= ' AND assigned_to = :ag';  $mParams[':ag'] = $agentId; }
if ($entityId) { $mSql .= ' AND new_operator = :ent';  $mParams[':ent'] = $entityId; }
$mSt = $db->prepare($mSql); $mSt->execute($mParams); $migrationsMonth = (int)$mSt->fetchColumn();

$mTodaySql = "SELECT COUNT(*) FROM crminternet_migrations WHERE (validated_at LIKE :todayLike OR completed_date = :today) AND (workflow_status = 'Validé' OR stage_id = 'MS-4')";
$mTodayParams = [':todayLike' => $today . '%', ':today' => $today];
if ($agentId)  { $mTodaySql .= ' AND assigned_to = :ag';  $mTodayParams[':ag'] = $agentId; }
if ($entityId) { $mTodaySql .= ' AND new_operator = :ent';  $mTodayParams[':ent'] = $entityId; }
$mt = $db->prepare($mTodaySql); $mt->execute($mTodayParams); $migrationsToday = (int)$mt->fetchColumn();

$pct = function(float $n, float $t): int { return $t > 0 ? (int)min(100, round($n * 100 / $t)) : 0; };

ok([
    'month' => $month,
    'scope' => ['agentId' => $agentId, 'entityId' => $entityId],
    'targets' => [
        'contractsMonthly' => $targetContracts,
        'migrationsMonthly' => $targetMigrations,
        'workingDays' => $workingDays,
        'notes' => $notes,
    ],
    'progress' => [
        'contractsMonthly' => $pct($contractsMonth, $targetContracts),
        'migrationsMonthly' => $pct($migrationsMonth, $targetMigrations),
    ],
    'contracts' => ['today' => $contractsToday, 'month' => $contractsMonth],
    'migrations' => ['today' => $migrationsToday, 'month' => $migrationsMonth],
]);
