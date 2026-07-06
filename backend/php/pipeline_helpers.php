<?php
// Pipeline helper functions shared by contracts.php and opportunities.php.

/**
 * Returns all stages for the given entity type ('lead', 'opportunity', 'contract').
 * Result: ['list' => [...], 'byId' => [...], 'byName' => [...]]
 */
function pipeline_load_stages(PDO $db, string $entity): array {
    static $cache = [];
    if (isset($cache[$entity])) return $cache[$entity];

    if ($entity === 'lead')             $table = 'crminternet_lead_stages';
    elseif ($entity === 'opportunity')  $table = 'crminternet_opportunity_stages';
    elseif ($entity === 'contract')     $table = 'crminternet_contract_stages';
    else                                $table = null;
    if (!$table) return $cache[$entity] = ['list' => [], 'byId' => [], 'byName' => []];

    try {
        $rows = $db->query("SELECT * FROM $table ORDER BY position ASC, name ASC")
                   ->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $_e) {
        return $cache[$entity] = ['list' => [], 'byId' => [], 'byName' => []];
    }

    $byId   = [];
    $byName = [];
    foreach ($rows as $r) {
        $byId[$r['id']]     = $r;
        $byName[$r['name']] = $r;
    }
    return $cache[$entity] = ['list' => $rows, 'byId' => $byId, 'byName' => $byName];
}

/** Resolve a stage row by name (case-insensitive fallback). */
function pipeline_stage_by_name(array $stages, string $name): ?array {
    if ($name === '') return null;
    if (isset($stages['byName'][$name])) return $stages['byName'][$name];
    $lower = mb_strtolower($name);
    foreach ($stages['list'] as $s) {
        if (mb_strtolower((string) ($s['name'] ?? '')) === $lower) {
            return $s;
        }
    }
    return null;
}

/**
 * Asserts that a stage transition is allowed for the given pipeline.
 * If no transitions are configured, all moves are permitted (open mode).
 * Calls fail() with 422 if the transition is explicitly forbidden.
 */
function pipeline_assert_transition(PDO $db, string $entity, string $currentStageName, string $newStageName): void {
    if ($currentStageName === $newStageName || $currentStageName === '') return;

    try {
        $s = $db->prepare('SELECT COUNT(*) FROM crminternet_pipeline_transitions WHERE pipeline = :p');
        $s->execute([':p' => $entity]);
        if ((int)$s->fetchColumn() === 0) return; // open mode — no rules configured
    } catch (Throwable $_e) {
        return; // table may not exist yet; allow the move
    }

    $stages = pipeline_load_stages($db, $entity);
    $fromRow = pipeline_stage_by_name($stages, $currentStageName);
    $toRow   = pipeline_stage_by_name($stages, $newStageName);
    $fromId = $fromRow['id'] ?? null;
    $toId   = $toRow['id'] ?? null;

    if (!$fromId || !$toId) return; // unknown stage names — allow

    try {
        $s = $db->prepare('SELECT 1 FROM crminternet_pipeline_transitions
                           WHERE pipeline = :p AND from_stage_id = :f AND to_stage_id = :t');
        $s->execute([':p' => $entity, ':f' => $fromId, ':t' => $toId]);
        if (!$s->fetchColumn()) {
            fail("Transition de '$currentStageName' vers '$newStageName' non autorisée.", 422);
        }
    } catch (Throwable $_e) {
        // If the table doesn't exist, allow the transition
    }
}

/**
 * Executes the auto_action defined on the destination stage (if any).
 * Runs server-side conversions (lead→opportunity, opportunity→contract).
 */
function pipeline_run_auto_action(PDO $db, string $entity, string $entityId, string $stageName, array $me): ?array {
    $stages = pipeline_load_stages($db, $entity);
    $stage  = pipeline_stage_by_name($stages, $stageName);
    if (!$stage) return null;

    $action = $stage['auto_action'] ?? 'none';
    if ($action === 'none' || $action === '') return null;

    require_once __DIR__ . '/conversion_helpers.php';

    switch ($action) {
        case 'convert_opportunity':
            if ($entity !== 'lead') {
                return ['action' => $action, 'skipped' => true, 'reason' => 'wrong_entity'];
            }
            if (!user_has_permission($db, $me, 'opportunity.convert')) {
                return ['action' => $action, 'error' => 'Permission opportunity.convert requise'];
            }
            $r = conversion_prospect_to_opportunity($db, $entityId, $me, [
                'source' => 'pipeline:' . $stageName,
                'checkAgent' => true,
            ]);
            return pipeline_auto_action_result($action, $r, 'opportunityId');

        case 'convert_contract':
            if ($entity === 'lead') {
                if (!user_has_permission($db, $me, 'opportunity.convert')) {
                    return ['action' => $action, 'error' => 'Permission opportunity.convert requise'];
                }
                $r = conversion_mark_won_to_contract($db, $entityId, $me, [
                    'source' => 'pipeline:' . $stageName,
                    'checkAgent' => true,
                ]);
                return pipeline_auto_action_result($action, $r, 'contractId');
            }
            if ($entity === 'opportunity') {
                if (!user_has_permission($db, $me, 'opportunity.convert')) {
                    return ['action' => $action, 'error' => 'Permission opportunity.convert requise'];
                }
                $r = conversion_opportunity_to_contract($db, $entityId, $me, [
                    'source' => 'pipeline:' . $stageName,
                ]);
                return pipeline_auto_action_result($action, $r, 'contractId');
            }
            return ['action' => $action, 'skipped' => true, 'reason' => 'wrong_entity'];

        case 'revert_lead':
            if ($entity !== 'opportunity') {
                return ['action' => $action, 'skipped' => true, 'reason' => 'wrong_entity'];
            }
            if (!user_has_permission($db, $me, 'opportunity.revert')) {
                return ['action' => $action, 'error' => 'Permission opportunity.revert requise'];
            }
            $r = conversion_revert_opportunity_to_prospect($db, $entityId, $me, [
                'source' => 'pipeline:' . $stageName,
            ]);
            return pipeline_auto_action_result($action, $r, 'prospectId');

        case 'revert_opportunity':
            if ($entity !== 'contract') {
                return ['action' => $action, 'skipped' => true, 'reason' => 'wrong_entity'];
            }
            if (!user_has_permission($db, $me, 'contract.revert')) {
                return ['action' => $action, 'error' => 'Permission contract.revert requise'];
            }
            $r = conversion_revert_contract_to_opportunity($db, $entityId, $me, [
                'source' => 'pipeline:' . $stageName,
            ]);
            return pipeline_auto_action_result($action, $r, 'opportunityId');

        default:
            return ['action' => $action, 'stage' => $stageName, 'entity' => $entity, 'id' => $entityId, 'skipped' => true];
    }
}

/** Normalize conversion helper result for pipeline auto-action API responses. */
function pipeline_auto_action_result(string $action, array $r, string $idKey): array {
    $created = $r['created'] ?? true;
    $executed = !empty($r['ok']) && $created !== false;
    $out = ['action' => $action] + $r + ['executed' => $executed];
    if (!empty($r[$idKey])) {
        $out[$idKey] = $r[$idKey];
    }
    if (!empty($r['error']) && empty($r['ok'])) {
        $out['error'] = $r['error'];
    }
    return $out;
}

/**
 * Returns the name of the initial lead stage to use when reverting a record back to a lead.
 * Falls back to 'Nouveau' if no stage is flagged is_initial.
 */
function pipeline_pick_revert_lead_status(PDO $db): string {
    $stages = pipeline_load_stages($db, 'lead');
    foreach ($stages['list'] as $s) {
        if (!empty($s['is_initial'])) return $s['name'];
    }
    // Fallback: first stage alphabetically, or hardcoded default
    return $stages['list'][0]['name'] ?? 'Nouveau';
}
