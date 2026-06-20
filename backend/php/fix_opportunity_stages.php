<?php
/**
 * Reset opportunity pipeline back to the LEGACY 4-stage workflow:
 *   nouveau (initial)  |  instance  |  rejeté (lost)  |  valide (won)
 *
 * What it does:
 *   1. Replaces all rows in `crminternet_opportunity_stages` with the 4 legacy stages.
 *   2. Re-maps any existing data in `crminternet_opportunities.stage` from the
 *      new labels (Découverte, Qualification, Proposition, Négociation,
 *      Gagnée, Signature, Perdue) back to the legacy 4.
 *   3. Reports before/after counts and the new pipeline configuration.
 *
 * Usage:
 *   GET .../fix_opportunity_stages.php?token=crm-seed-2026
 *   GET .../fix_opportunity_stages.php?token=crm-seed-2026&dry=1
 */

require_once __DIR__ . '/config.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}
$dry = ($_GET['dry'] ?? '') === '1';

header('Content-Type: application/json; charset=utf-8');

try {
    $db = (new Database())->getConnection();

    // -------- Legacy pipeline definition --------
    // position keeps natural pipeline order
    // is_initial: first stage when a lead converts
    // is_won + auto_action=convert_contract => "valide" closes the deal as a contract
    // is_lost + auto_action=revert_lead     => "rejeté" sends the prospect back to the lead pool
    $legacy_stages = [
        ['OS-nouveau',  'nouveau',  'info',        10, 1, 0, 0, 'none'],
        ['OS-instance', 'instance', 'warning',     20, 0, 0, 0, 'none'],
        ['OS-rejete',   'rejeté',   'destructive', 30, 0, 0, 1, 'revert_lead'],
        ['OS-valide',   'valide',   'success',     40, 0, 1, 0, 'convert_contract'],
    ];

    // -------- Data remap (any opportunity rows on the new labels) --------
    $remap = [
        'Découverte' => 'nouveau',   'Decouverte' => 'nouveau',
        'Qualification' => 'nouveau',
        'Proposition' => 'instance',
        'Négociation' => 'instance', 'Negociation' => 'instance',
        'Gagnée' => 'valide', 'Gagnee' => 'valide',
        'Signature' => 'valide',
        'Perdue' => 'rejeté', 'Perdu' => 'rejeté',
    ];

    $report = [
        'success' => true,
        'dry_run' => $dry,
        'opportunity_stages_table' => [
            'before' => [],
            'after'  => [],
            'wiped'  => 0,
            'inserted' => 0,
        ],
        'opportunities_data' => [
            'before' => [],
            'after'  => [],
            'remapped' => 0,
            'total_rows' => 0,
        ],
        'still_unknown_data' => [],
    ];

    // ===== Snapshot stages BEFORE =====
    foreach ($db->query("SELECT id, name, position, is_initial, is_won, is_lost, auto_action
                         FROM crminternet_opportunity_stages ORDER BY position, name") as $r) {
        $report['opportunity_stages_table']['before'][] = $r;
    }

    // ===== Snapshot opportunities.stage BEFORE =====
    foreach ($db->query("SELECT stage, COUNT(*) c FROM crminternet_opportunities GROUP BY stage") as $r) {
        $k = $r['stage'] ?? '(null)';
        $report['opportunities_data']['before'][$k] = (int)$r['c'];
        $report['opportunities_data']['total_rows'] += (int)$r['c'];
    }

    if (!$dry) {
        $db->beginTransaction();

        // 1) Wipe & insert 4 legacy stages
        $wipe = $db->exec("DELETE FROM crminternet_opportunity_stages");
        $report['opportunity_stages_table']['wiped'] = (int)$wipe;

        $ins = $db->prepare(
            "INSERT INTO crminternet_opportunity_stages
               (id, name, color, position, is_initial, is_won, is_lost, auto_action)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($legacy_stages as $s) {
            $ins->execute($s);
            $report['opportunity_stages_table']['inserted']++;
        }

        // 2) Remap any opportunity rows on the new labels back to legacy
        $upd = $db->prepare("UPDATE crminternet_opportunities SET stage = :to WHERE stage = :from");
        foreach ($remap as $from => $to) {
            $upd->execute([':to' => $to, ':from' => $from]);
            $report['opportunities_data']['remapped'] += $upd->rowCount();
        }

        $db->commit();
    } else {
        // Dry: count what would be remapped
        $q = $db->prepare("SELECT COUNT(*) FROM crminternet_opportunities WHERE stage = ?");
        foreach ($remap as $from => $to) {
            $q->execute([$from]);
            $report['opportunities_data']['remapped'] += (int)$q->fetchColumn();
        }
    }

    // ===== Snapshot stages AFTER =====
    foreach ($db->query("SELECT id, name, position, is_initial, is_won, is_lost, auto_action
                         FROM crminternet_opportunity_stages ORDER BY position, name") as $r) {
        $report['opportunity_stages_table']['after'][] = $r;
    }

    // ===== Snapshot opportunities.stage AFTER =====
    foreach ($db->query("SELECT stage, COUNT(*) c FROM crminternet_opportunities GROUP BY stage") as $r) {
        $k = $r['stage'] ?? '(null)';
        $report['opportunities_data']['after'][$k] = (int)$r['c'];
    }

    // ===== Any opportunity rows still outside the 4 legacy stages =====
    $allowed = ['nouveau', 'instance', 'rejeté', 'valide'];
    $in = "'" . implode("','", $allowed) . "'";
    foreach ($db->query("SELECT stage, COUNT(*) c FROM crminternet_opportunities
                         WHERE stage NOT IN ($in) OR stage IS NULL GROUP BY stage") as $r) {
        $report['still_unknown_data'][$r['stage'] ?? '(null)'] = (int)$r['c'];
    }

    echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
}
