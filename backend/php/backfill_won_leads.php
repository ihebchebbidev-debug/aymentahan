<?php
/**
 * One-time repair: mark the ORIGINATING prospect as won for every existing
 * contract/migration that was created via the opportunity pipeline
 * (prospect → opportunity → contract/migration), AND backdate updated_at to
 * the real closing date (contract signature_date / migration created_at).
 *
 * Without the updated_at fix, these prospects still show outcome='won' but
 * their updated_at is frozen at whenever they were converted to an
 * opportunity (often months before the contract existed) — since reports.php
 * now always gates won/lost by "updated_at falls in the selected period",
 * every one of these leads was invisible no matter which period was picked.
 * This backfill puts them back on their real closing month.
 *
 * See conversion_opportunity_to_contract() / conversion_opportunity_to_migration()
 * in conversion_helpers.php for the (now fixed) live-path equivalent, which
 * stamps outcome/status/updated_by/updated_at correctly at the moment a deal
 * actually closes going forward.
 *
 * Fully idempotent — no WHERE guard on outcome, safe to re-run anytime;
 * MySQL/PDO only reports a row as "affected" when a value actually changes,
 * and updated_at is never moved backwards (GREATEST keeps the later date).
 *
 * GET .../backfill_won_leads.php?token=crm-seed-2026
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/pipeline_helpers.php';
require_method('GET');

$token = getenv('CRM_SEED_TOKEN') ?: 'crm-seed-2026';
if (($_GET['token'] ?? '') !== $token) {
    fail('Forbidden', 403);
}

// No JWT check — token in the URL is the only gate, so this can be run by
// pasting the URL directly in a browser. Delete this file once you've run it.
$me = ['username' => 'backfill-script', 'role' => 'Administrateur'];
$db = (new Database())->getConnection();

$wonStatus = pipeline_pick_won_lead_status($db);

$db->beginTransaction();
try {
    // 1) Direct prospect -> contract (mark_won shortcut, or any contract
    //    linked straight to a prospect without going through an opportunity).
    $direct = $db->prepare("
        UPDATE crminternet_prospects p
        INNER JOIN crminternet_contracts c ON c.prospect_id = p.id
        SET p.outcome = 'won',
            p.status  = :st1,
            p.updated_by = COALESCE(NULLIF(p.updated_by, ''), NULLIF(c.created_by, ''), p.updated_by),
            p.updated_at = GREATEST(COALESCE(p.updated_at, c.signature_date), c.signature_date)
    ");
    $direct->execute([':st1' => $wonStatus]);
    $directCount = $direct->rowCount();

    // 2) Prospect -> opportunity -> contract.
    $viaOppContract = $db->prepare("
        UPDATE crminternet_prospects p
        INNER JOIN crminternet_opportunities o ON o.id = p.opportunity_id
        INNER JOIN crminternet_contracts c ON c.opportunity_id = o.id
        SET p.outcome = 'won',
            p.status  = :st2,
            p.updated_by = COALESCE(NULLIF(p.updated_by, ''), NULLIF(c.created_by, ''), NULLIF(o.created_by, ''), p.updated_by),
            p.updated_at = GREATEST(COALESCE(p.updated_at, c.signature_date), c.signature_date)
    ");
    $viaOppContract->execute([':st2' => $wonStatus]);
    $viaOppContractCount = $viaOppContract->rowCount();

    // 3) Prospect -> opportunity -> migration.
    $viaOppMigration = $db->prepare("
        UPDATE crminternet_prospects p
        INNER JOIN crminternet_opportunities o ON o.id = p.opportunity_id
        INNER JOIN crminternet_migrations mg ON mg.opportunity_id = o.id AND mg.deleted_at IS NULL
        SET p.outcome = 'won',
            p.status  = :st3,
            p.updated_by = COALESCE(NULLIF(p.updated_by, ''), NULLIF(mg.created_by, ''), NULLIF(o.created_by, ''), p.updated_by),
            p.updated_at = GREATEST(COALESCE(p.updated_at, mg.created_at), mg.created_at)
    ");
    $viaOppMigration->execute([':st3' => $wonStatus]);
    $viaOppMigrationCount = $viaOppMigration->rowCount();

    $db->commit();
} catch (Throwable $e) {
    $db->rollBack();
    fail('Erreur backfill: ' . $e->getMessage(), 500);
}

$total = $directCount + $viaOppContractCount + $viaOppMigrationCount;
audit_log($db, $me, 'backfill_won_leads', 'prospect', 'bulk', [
    'directContracts'         => $directCount,
    'viaOpportunityContracts' => $viaOppContractCount,
    'viaOpportunityMigrations'=> $viaOppMigrationCount,
    'total'                   => $total,
]);

ok([
    'message'                  => 'Backfill terminé',
    'directContracts'          => $directCount,
    'viaOpportunityContracts'  => $viaOppContractCount,
    'viaOpportunityMigrations' => $viaOppMigrationCount,
    'totalProspectsFixed'      => $total,
    'note'                     => 'Idempotent — peut être relancé sans risque. Chaque lead gagné est maintenant daté (updated_at) sur son vrai mois de clôture (signature du contrat / date de migration), pas sur aujourd\'hui.',
]);
