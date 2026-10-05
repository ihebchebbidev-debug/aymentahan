<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/schema_repair.php';
require_auth();
require_method('GET');
$db = (new Database())->getConnection();
ensure_reports_schema($db);

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');
$format = $_GET['format'] ?? 'json';
$team = isset($_GET['team']) ? trim((string)$_GET['team']) : '';
$entityId = isset($_GET['entityId']) ? trim((string)$_GET['entityId']) : '';
$agentId = isset($_GET['agentId']) ? trim((string)$_GET['agentId']) : '';
$role = isset($_GET['role']) ? trim((string)$_GET['role']) : '';
$teamIsNone = ($team === '__none__');

$hasGuichetEntityColumn = true;
try {
    $colCheck = $db->query("SHOW COLUMNS FROM crminternet_users LIKE 'guichet_entity_id'");
    if (!$colCheck->fetch()) {
        $db->exec("ALTER TABLE crminternet_users ADD COLUMN guichet_entity_id VARCHAR(40) NULL");
        try { $db->exec("ALTER TABLE crminternet_users ADD INDEX idx_users_guichet_entity (guichet_entity_id)"); } catch (Throwable $e) {}
    }
} catch (Throwable $e) {
    $hasGuichetEntityColumn = false;
}
if ($teamIsNone) {
    $teamFilter = " AND (t.name IS NULL OR t.name = '') ";
} elseif ($team !== '') {
    $teamFilter = ' AND t.name = :team ';
} else {
    $teamFilter = '';
}

// Use the real "Équipe" (crminternet_teams.name) assigned to each user via
// team_id — this is the dynamic list managed in Users > Équipe(s).
$NO_TEAM = 'Sans équipe';

$agentFilterSql = '';
$agentFilterParams = [];
if ($hasGuichetEntityColumn && $entityId !== '') {
    $agentFilterSql .= ' AND u.guichet_entity_id = :entityId ';
    $agentFilterParams[':entityId'] = $entityId;
}
if ($agentId !== '') {
    $agentFilterSql .= ' AND u.id = :agentId ';
    $agentFilterParams[':agentId'] = $agentId;
}
if ($role !== '') {
    $agentFilterSql .= ' AND u.role = :role ';
    $agentFilterParams[':role'] = $role;
}

// Per-agent KPIs
// Règles d'attribution demandées par le client :
//  - Prospects (leads / gagnés / perdus) : attribués à `updated_by` (Modifié par),
//    car la création des prospects est faite par l'admin/import, pas par l'agent.
//    La période (:from/:to) filtre sur la DATE DE DERNIÈRE MODIFICATION
//    (`updated_at`), pas sur la date de création — un lead créé en janvier
//    et gagné/perdu en août doit apparaître dans le rapport d'août, sous le
//    nom de l'agent qui a fait cette dernière modification (pas celui qui a
//    créé/importé la fiche à l'origine).
//  - Opportunités : attribuées à `created_by` (Créé par).
//  - Contrats & migrations : attribués à l'agent du prospect d'origine (updated_by),
//    afin que la victoire revienne à celui qui a réellement travaillé le prospect.
// Le rapprochement se fait sur username OU nom complet (insensible à la casse).
$MATCH = "(LOWER(TRIM(%s)) = LOWER(TRIM(u.username)) OR (u.full_name IS NOT NULL AND u.full_name <> '' AND LOWER(TRIM(%s)) = LOWER(TRIM(u.full_name))))";
$mProspect = sprintf($MATCH, 'p.updated_by', 'p.updated_by');
$mOwnerC   = sprintf($MATCH, 'po.updated_by', 'po.updated_by');
$mOwnerM   = sprintf($MATCH, 'pm.updated_by', 'pm.updated_by');
$mOpp      = sprintf($MATCH, 'o.created_by', 'o.created_by');

$WON_SQL  = "(p.outcome = 'won'  OR LOWER(TRIM(p.status)) IN ('vendu','ok'))";
$LOST_SQL = "(p.outcome = 'lost' OR LOWER(TRIM(p.status)) LIKE 'refus%')";

// "Gagné"/"Perdu" sont TOUJOURS comptés pour l'agent qui a fait la DERNIÈRE
// modification du lead (updated_by) et sur la date de cette dernière
// modification (updated_at) — jamais sur la date de création ni sur qui l'a
// créé/importé à l'origine. Ça vaut pour tous les signaux : le statut classique
// (outcome/status), un contrat/migration rattaché au lead (direct ou via son
// opportunité — même lien fiable que les colonnes Contrats/Migrations), et le
// retour d'une opportunité en prospect (reverted_from='opportunity' = perdu).
$agentSql = "
  SELECT u.username, u.full_name, COALESCE(t.name, '') AS team_name,
    (SELECT COUNT(*) FROM crminternet_prospects p
       WHERE p.created_at BETWEEN :from1 AND :to1 AND $mProspect) AS handled,
    (SELECT COUNT(*) FROM crminternet_prospects p
       WHERE $mProspect AND DATE(p.updated_at) BETWEEN :from1b AND :to1b AND (
         $WON_SQL
         OR EXISTS (SELECT 1 FROM crminternet_contracts wc WHERE wc.prospect_id = p.id)
         OR (p.opportunity_id IS NOT NULL AND p.opportunity_id <> '' AND EXISTS (
               SELECT 1 FROM crminternet_contracts wc2 WHERE wc2.opportunity_id = p.opportunity_id
             ))
         OR (p.opportunity_id IS NOT NULL AND p.opportunity_id <> '' AND EXISTS (
               SELECT 1 FROM crminternet_migrations wm WHERE wm.opportunity_id = p.opportunity_id AND wm.deleted_at IS NULL
             ))
       )) AS won,
    (SELECT COUNT(*) FROM crminternet_prospects p
       WHERE $mProspect AND DATE(p.updated_at) BETWEEN :from1c AND :to1c AND (
         $LOST_SQL OR p.reverted_from = 'opportunity'
       )) AS lost,
    (SELECT COUNT(*) FROM crminternet_opportunities o
       WHERE o.created_at BETWEEN :from5 AND :to5 AND $mOpp) AS opportunities_count,
    (SELECT COUNT(*) FROM crminternet_contracts c
       LEFT JOIN crminternet_opportunities oc ON oc.id = c.opportunity_id
       LEFT JOIN crminternet_prospects po ON po.id = COALESCE(c.prospect_id, oc.prospect_id)
       WHERE c.signature_date BETWEEN :from2 AND :to2 AND $mOwnerC) AS contracts_count,
    (SELECT COALESCE(SUM(c.premium),0) FROM crminternet_contracts c
       LEFT JOIN crminternet_opportunities oc ON oc.id = c.opportunity_id
       LEFT JOIN crminternet_prospects po ON po.id = COALESCE(c.prospect_id, oc.prospect_id)
       WHERE c.signature_date BETWEEN :from3 AND :to3 AND $mOwnerC) AS revenue,
    (SELECT COUNT(*) FROM crminternet_migrations mg
       LEFT JOIN crminternet_opportunities om ON om.id = mg.opportunity_id
       LEFT JOIN crminternet_prospects pm ON pm.id = COALESCE(mg.prospect_id, om.prospect_id)
       WHERE mg.deleted_at IS NULL AND DATE(mg.created_at) BETWEEN :from4 AND :to4 AND $mOwnerM) AS migrations_count
  FROM crminternet_users u
  LEFT JOIN crminternet_teams t ON t.id = u.team_id
  WHERE u.active = 1
  $teamFilter
  $agentFilterSql
  GROUP BY u.id
  ORDER BY contracts_count DESC, won DESC
";
$s = $db->prepare($agentSql);
$to_dt = $to . ' 23:59:59';
$params = [
    ':from1'=>$from, ':to1'=>$to_dt,
    ':from1b'=>$from, ':to1b'=>$to_dt,
    ':from1c'=>$from, ':to1c'=>$to_dt,
    ':from2'=>$from, ':to2'=>$to_dt, ':from3'=>$from, ':to3'=>$to_dt,
    ':from4'=>$from, ':to4'=>$to_dt, ':from5'=>$from, ':to5'=>$to_dt,
];
if ($team !== '' && !$teamIsNone) $params[':team'] = $team;
$params = array_merge($params, $agentFilterParams);
$s->execute($params);
$agents = array_map(function($r) use ($NO_TEAM) {
    $h = (int)$r['handled'];
    $tn = (string)$r['team_name'];
    return [
        'username'  => $r['username'],
        'fullName'  => $r['full_name'],
        'team'      => $tn !== '' ? $tn : $NO_TEAM,
        'handled'   => $h,
        'won'       => (int)$r['won'],
        'lost'      => (int)$r['lost'],
        'opportunities' => (int)($r['opportunities_count'] ?? 0),
        'contracts' => (int)$r['contracts_count'],
        'migrations' => (int)($r['migrations_count'] ?? 0),
        'revenue'   => (float)$r['revenue'],
        'conversion' => $h > 0 ? round(((int)$r['won'] / $h) * 100, 1) : 0.0,
    ];
}, $s->fetchAll());



// Per-team (agence) aggregation derived from the agent rows above so the
// team filter is naturally honored.
$teamsAgg = [];
foreach ($agents as $a) {
    $t = $a['team'];
    if (!isset($teamsAgg[$t])) {
        $teamsAgg[$t] = ['team'=>$t,'agents'=>0,'handled'=>0,'won'=>0,'lost'=>0,'opportunities'=>0,'contracts'=>0,'migrations'=>0,'revenue'=>0.0];
    }
    $teamsAgg[$t]['agents']    += 1;
    $teamsAgg[$t]['handled']   += $a['handled'];
    $teamsAgg[$t]['won']       += $a['won'];
    $teamsAgg[$t]['lost']      += $a['lost'];
    $teamsAgg[$t]['opportunities'] += $a['opportunities'];
    $teamsAgg[$t]['contracts'] += $a['contracts'];
    $teamsAgg[$t]['migrations'] += $a['migrations'];
    $teamsAgg[$t]['revenue']   += $a['revenue'];
}
$teams = array_values(array_map(function($t){
    $t['conversion'] = $t['handled'] > 0 ? round($t['won'] / $t['handled'] * 100, 1) : 0.0;
    return $t;
}, $teamsAgg));
usort($teams, fn($a,$b) => $b['revenue'] <=> $a['revenue']);


// Funnel
$needsUserJoin = ($team !== '' || $entityId !== '' || $agentId !== '' || $role !== '');
if ($teamIsNone) {
    $funnelJoin = " LEFT JOIN crminternet_users u ON u.username = p.assigned_to LEFT JOIN crminternet_teams t ON t.id = u.team_id ";
    $funnelWhereTeam = " AND (t.name IS NULL OR t.name = '') ";
} elseif ($team !== '') {
    $funnelJoin = ' INNER JOIN crminternet_users u ON u.username = p.assigned_to INNER JOIN crminternet_teams t ON t.id = u.team_id AND t.name = :team ';
    $funnelWhereTeam = '';
} elseif ($needsUserJoin) {
    $funnelJoin = ' LEFT JOIN crminternet_users u ON u.username = p.assigned_to ';
    $funnelWhereTeam = '';
} else {
    $funnelJoin = '';
    $funnelWhereTeam = '';
}
$funnelWhereExtra = '';
$funnelExtraParams = [];
if ($hasGuichetEntityColumn && $entityId !== '') {
    $funnelWhereExtra .= ' AND u.guichet_entity_id = :entityId ';
    $funnelExtraParams[':entityId'] = $entityId;
}
if ($agentId !== '') {
    $funnelWhereExtra .= ' AND u.id = :agentId ';
    $funnelExtraParams[':agentId'] = $agentId;
}
if ($role !== '') {
    $funnelWhereExtra .= ' AND u.role = :role ';
    $funnelExtraParams[':role'] = $role;
}

// Pending/total reflect leads that EXIST in the period (created_at, a volume
// metric). Won/lost are ALWAYS gated by the lead's last-modification date
// (updated_at) — same rule as the per-agent table above, applied uniformly
// to every signal (status, linked contract/migration, or an opportunity
// reverted back to a lead), not just the outcome field. EMULATE_PREPARES is
// off, so each :param can only be bound once per query — hence the f1/f2/f3/f4
// suffixes even though they all carry the same $from/$to values.
$funnel = $db->prepare("
  SELECT
    SUM(CASE WHEN p.outcome='pending' AND p.created_at BETWEEN :f1 AND :t1 THEN 1 ELSE 0 END) pending,
    SUM(CASE WHEN DATE(p.updated_at) BETWEEN :f2 AND :t2 AND (
          p.outcome='won'
          OR EXISTS (SELECT 1 FROM crminternet_contracts wc WHERE wc.prospect_id = p.id)
          OR (p.opportunity_id IS NOT NULL AND p.opportunity_id <> '' AND EXISTS (
                SELECT 1 FROM crminternet_contracts wc2 WHERE wc2.opportunity_id = p.opportunity_id
              ))
          OR (p.opportunity_id IS NOT NULL AND p.opportunity_id <> '' AND EXISTS (
                SELECT 1 FROM crminternet_migrations wm WHERE wm.opportunity_id = p.opportunity_id AND wm.deleted_at IS NULL
              ))
        ) THEN 1 ELSE 0 END) won,
    SUM(CASE WHEN DATE(p.updated_at) BETWEEN :f3 AND :t3 AND (
          p.outcome='lost' OR p.reverted_from = 'opportunity'
        ) THEN 1 ELSE 0 END) lost,
    SUM(CASE WHEN p.created_at BETWEEN :f4 AND :t4 THEN 1 ELSE 0 END) total
  FROM crminternet_prospects p
  $funnelJoin
  WHERE 1=1
  $funnelWhereTeam
  $funnelWhereExtra
");
$fp = [
    ':f1'=>$from, ':t1'=>$to_dt,
    ':f2'=>$from, ':t2'=>$to_dt,
    ':f3'=>$from, ':t3'=>$to_dt,
    ':f4'=>$from, ':t4'=>$to_dt,
];
if ($team !== '' && !$teamIsNone) $fp[':team'] = $team;
$fp = array_merge($fp, $funnelExtraParams);
$funnel->execute($fp);
$f = $funnel->fetch();

// Monthly revenue (12 buckets back from `to`)
if ($teamIsNone) {
    $monthlyJoin = " LEFT JOIN crminternet_users u ON u.username = c.assigned_to LEFT JOIN crminternet_teams t ON t.id = u.team_id ";
    $monthlyWhereTeam = " AND (t.name IS NULL OR t.name = '') ";
} elseif ($team !== '') {
    $monthlyJoin = ' INNER JOIN crminternet_users u ON u.username = c.assigned_to INNER JOIN crminternet_teams t ON t.id = u.team_id AND t.name = :team ';
    $monthlyWhereTeam = '';
} elseif ($needsUserJoin) {
    $monthlyJoin = ' LEFT JOIN crminternet_users u ON u.username = c.assigned_to ';
    $monthlyWhereTeam = '';
} else {
    $monthlyJoin = '';
    $monthlyWhereTeam = '';
}
$monthlyWhereExtra = '';
$monthlyExtraParams = [];
if ($hasGuichetEntityColumn && $entityId !== '') {
    $monthlyWhereExtra .= ' AND u.guichet_entity_id = :entityId ';
    $monthlyExtraParams[':entityId'] = $entityId;
}
if ($agentId !== '') {
    $monthlyWhereExtra .= ' AND u.id = :agentId ';
    $monthlyExtraParams[':agentId'] = $agentId;
}

if ($role !== '') {
    $monthlyWhereExtra .= ' AND u.role = :role ';
    $monthlyExtraParams[':role'] = $role;
}

$monthly = $db->prepare("
  SELECT DATE_FORMAT(c.signature_date,'%Y-%m') ym, COUNT(*) cnt, SUM(c.premium) rev
  FROM crminternet_contracts c
  $monthlyJoin
  WHERE c.signature_date >= DATE_SUB(:t, INTERVAL 12 MONTH)
  $monthlyWhereTeam
  $monthlyWhereExtra
  GROUP BY ym ORDER BY ym
");
$mp = [':t'=>$to];
if ($team !== '' && !$teamIsNone) $mp[':team'] = $team;
$mp = array_merge($mp, $monthlyExtraParams);
$monthly->execute($mp);
$months = array_map(fn($r)=>['month'=>$r['ym'],'contracts'=>(int)$r['cnt'],'revenue'=>(float)$r['rev']], $monthly->fetchAll());

// Per prospect type (the values shown in the prospect-type filters, e.g. Client guichet, Swap gpon)
if ($teamIsNone) {
    $srcJoin = " LEFT JOIN crminternet_users u ON u.username = p.assigned_to LEFT JOIN crminternet_teams t ON t.id = u.team_id ";
    $srcWhereTeam = " AND (t.name IS NULL OR t.name = '') ";
} elseif ($team !== '') {
    $srcJoin = ' INNER JOIN crminternet_users u ON u.username = p.assigned_to INNER JOIN crminternet_teams t ON t.id = u.team_id AND t.name = :team ';
    $srcWhereTeam = '';
} elseif ($needsUserJoin) {
    $srcJoin = ' LEFT JOIN crminternet_users u ON u.username = p.assigned_to ';
    $srcWhereTeam = '';
} else {
    $srcJoin = '';
    $srcWhereTeam = '';
}

$srcWhereExtra = '';
$srcExtraParams = [];
if ($hasGuichetEntityColumn && $entityId !== '') {
    $srcWhereExtra .= ' AND u.guichet_entity_id = :entityId ';
    $srcExtraParams[':entityId'] = $entityId;
}
if ($agentId !== '') {
    $srcWhereExtra .= ' AND u.id = :agentId ';
    $srcExtraParams[':agentId'] = $agentId;
}
if ($role !== '') {
    $srcWhereExtra .= ' AND u.role = :role ';
    $srcExtraParams[':role'] = $role;
}

$src = $db->prepare("
  SELECT CASE
           WHEN COALESCE(NULLIF(pt.name, ''), NULLIF(pt.label, '')) IS NOT NULL THEN COALESCE(NULLIF(pt.name, ''), NULLIF(pt.label, ''))
           WHEN p.type_id IS NOT NULL AND p.type_id <> '' AND p.type_id NOT LIKE 'PT-%' THEN p.type_id
           ELSE 'Type non défini'
         END AS type_label,
         COUNT(*) total,
         SUM(CASE WHEN p.outcome='won' THEN 1 ELSE 0 END) won
  FROM crminternet_prospects p
  $srcJoin
  LEFT JOIN crminternet_prospect_types pt ON pt.id = p.type_id
  WHERE p.created_at BETWEEN :f AND :t
  $srcWhereTeam
  $srcWhereExtra
  GROUP BY type_label ORDER BY total DESC
");
$sp = [':f'=>$from, ':t'=>$to_dt];
if ($team !== '' && !$teamIsNone) $sp[':team'] = $team;
$sp = array_merge($sp, $srcExtraParams);
$src->execute($sp);
$sources = array_map(fn($r)=>[
    'source'=>$r['type_label'], 'total'=>(int)$r['total'], 'won'=>(int)$r['won'],
    'conversion'=> (int)$r['total']>0 ? round((int)$r['won']/(int)$r['total']*100,1) : 0.0,
], $src->fetchAll());

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    $tag = $team !== '' ? '_'.preg_replace('/[^A-Za-z0-9_-]/','',$team) : '';
    header('Content-Disposition: attachment; filename="report_agents_'.$from.'_'.$to.$tag.'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Agent','Username','Agence','Leads traités','Gagnés','Perdus','Opportunités','Contrats','Migrations','Revenue','Conversion %']);
    foreach ($agents as $a) {
        $teamLabel = ($a['team'] !== '' && $a['team'] !== null) ? $a['team'] : $NO_TEAM;
        fputcsv($out, [$a['fullName'],$a['username'],$teamLabel,$a['handled'],$a['won'],$a['lost'],$a['opportunities'],$a['contracts'],$a['migrations'],$a['revenue'],$a['conversion']]);
    }
    fclose($out);
    exit;
}

ok([
    'period'  => ['from'=>$from,'to'=>$to,'team'=>$team],
    'agents'  => $agents,
    'teams'   => $teams,
    'funnel'  => ['pending'=>(int)$f['pending'],'won'=>(int)$f['won'],'lost'=>(int)$f['lost'],'total'=>(int)$f['total']],
    'monthly' => $months,
    'sources' => $sources,
]);
