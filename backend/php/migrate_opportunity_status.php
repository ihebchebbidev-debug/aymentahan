<?php
/**
 * Migrate opportunity statuses back to the legacy 4-stage workflow:
 *   nouveau | instance | rejeté | valide
 *
 * Usage:
 *   GET .../migrate_opportunity_status.php?token=crm-seed-2026
 *   GET .../migrate_opportunity_status.php?token=crm-seed-2026&dry=1
 */

require_once __DIR__ . '/config.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}
$dry = ($_GET['dry'] ?? '') === '1';

try {
    $db = (new Database())->getConnection();

    /**
     * Mapping: new (wrong) stage  ->  legacy stage
     *  Découverte / Qualification              => nouveau
     *  Proposition / Négociation               => instance
     *  Gagnée / Signature                      => valide
     *  Perdue                                  => rejeté
     */
    $map = [
        'Découverte'    => 'nouveau',
        'Decouverte'    => 'nouveau',
        'découverte'    => 'nouveau',
        'decouverte'    => 'nouveau',

        'Qualification' => 'nouveau',
        'qualification' => 'nouveau',

        'Proposition'   => 'instance',
        'proposition'   => 'instance',

        'Négociation'   => 'instance',
        'Negociation'   => 'instance',
        'négociation'   => 'instance',
        'negociation'   => 'instance',

        'Gagnée'        => 'valide',
        'Gagnee'        => 'valide',
        'gagnée'        => 'valide',
        'gagnee'        => 'valide',

        'Signature'     => 'valide',
        'signature'     => 'valide',

        'Perdue'        => 'rejeté',
        'perdue'        => 'rejeté',
        'Perdu'         => 'rejeté',
        'perdu'         => 'rejeté',
    ];

    $result = [
        'success'    => true,
        'dry_run'    => $dry,
        'before'     => [],
        'updates'    => [],
        'after'      => [],
        'total_rows' => 0,
        'updated'    => 0,
    ];

    // Snapshot BEFORE
    foreach ($db->query("SELECT stage, COUNT(*) AS c FROM crminternet_opportunities GROUP BY stage") as $row) {
        $key = $row['stage'] ?? '(null)';
        $result['before'][$key] = (int)$row['c'];
        $result['total_rows'] += (int)$row['c'];
    }

    // Apply mapping
    foreach ($map as $from => $to) {
        if ($dry) {
            $q = $db->prepare("SELECT COUNT(*) FROM crminternet_opportunities WHERE stage = :s");
            $q->execute([':s' => $from]);
            $n = (int)$q->fetchColumn();
        } else {
            $u = $db->prepare("UPDATE crminternet_opportunities SET stage = :to WHERE stage = :from");
            $u->execute([':to' => $to, ':from' => $from]);
            $n = $u->rowCount();
        }
        if ($n > 0) {
            $result['updates'][] = ['from' => $from, 'to' => $to, 'rows' => $n];
            $result['updated'] += $n;
        }
    }

    // Snapshot AFTER
    foreach ($db->query("SELECT stage, COUNT(*) AS c FROM crminternet_opportunities GROUP BY stage") as $row) {
        $key = $row['stage'] ?? '(null)';
        $result['after'][$key] = (int)$row['c'];
    }

    // Flag any rows still outside the legacy set
    $allowed = ['nouveau', 'instance', 'rejeté', 'valide'];
    $in = "'" . implode("','", $allowed) . "'";
    $stmt = $db->query("SELECT stage, COUNT(*) AS c FROM crminternet_opportunities
                        WHERE stage NOT IN ($in) OR stage IS NULL GROUP BY stage");
    $unknown = [];
    foreach ($stmt as $row) {
        $unknown[$row['stage'] ?? '(null)'] = (int)$row['c'];
    }
    $result['still_unknown'] = $unknown;

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine(),
    ], JSON_UNESCAPED_UNICODE);
}
