<?php
/**
 * conversion_helpers.php
 *
 * Centralise les INSERT lors des conversions
 *   prospect → opportunité
 *   prospect → contrat (raccourci)
 *   opportunité → contrat
 *   opportunité → migration (terminal peer)
 *
 * Garantit que **toutes** les colonnes identité/contact/adresse/observation
 * sont propagées entre étapes (exigence client : 100% des infos prospect
 * doivent rester visibles côté opportunité et contrat).
 */

if (!function_exists('conv_v')) {
    /** Récupère une valeur de tableau associatif en tolérant la clé absente. */
    function conv_v(array $row, string $key, $default = null) {
        return array_key_exists($key, $row) ? $row[$key] : $default;
    }
}

/**
 * Insère une opportunité construite à partir d'un prospect (snapshot complet).
 * Les éventuelles surcharges (titre, montant, probabilité, stage, créateur)
 * passent par $extra.
 */
function conversion_insert_opportunity_from_prospect(PDO $db, string $oid, array $p, array $extra = []): void {
    $title = $extra['title'] ?? trim((string)conv_v($p, 'last_name', '').' '.(string)conv_v($p, 'first_name', ''));
    $stage = $extra['stage'] ?? 'Qualification';
    $sql = "INSERT INTO crminternet_opportunities
        (id, prospect_id, civility, last_name, first_name,
         phone, phone2, animateur, ancien_ligne, cin, birth_date, email,
         city, gouvernorat, delegation, zone, address, localisation_xy, code_postal,
         comment1, comment2, source, type_id, lead_status, lost_reason,
         title, stage, amount, probability, expected_close_date,
         assigned_to, notes, created_by)
        VALUES
        (:id, :pid, :civ, :ln, :fn,
         :ph, :ph2, :anim, :anc, :cin, :bd, :em,
         :ci, :gv, :dl, :zn, :ad, :gps, :cp,
         :c1, :c2, :src, :tid, :lst, :lr,
         :title, :stg, :amt, :prob, :ecd,
         :at, :notes, :cb)";
    $db->prepare($sql)->execute([
        ':id'    => $oid,
        ':pid'   => $p['id'] ?? null,
        ':civ'   => $p['civility'] ?? 'M',
        ':ln'    => $p['last_name'] ?? '',
        ':fn'    => $p['first_name'] ?? '',
        ':ph'    => $p['phone'] ?? '',
        ':ph2'   => $p['phone2'] ?? '',
        ':anim'  => $p['animateur'] ?? null,
        ':anc'   => $p['ancien_ligne'] ?? null,
        ':cin'   => ($p['cin'] ?? null) ?: null,
        ':bd'    => $p['birth_date'] ?? null,
        ':em'    => $p['email'] ?? '',
        ':ci'    => $p['city'] ?? '',
        ':gv'    => $p['gouvernorat'] ?? '',
        ':dl'    => $p['delegation'] ?? '',
        ':zn'    => $p['zone'] ?? '',
        ':ad'    => $p['address'] ?? '',
        ':gps'   => $p['localisation_xy'] ?? null,
        ':cp'    => $p['code_postal'] ?? null,
        ':c1'    => $p['comment'] ?? null,
        ':c2'    => $p['comment2'] ?? null,
        ':src'   => $p['source'] ?? '',
        ':tid'   => $p['type_id'] ?? null,
        ':lst'   => $p['status'] ?? null,
        ':lr'    => $p['lost_reason'] ?? null,
        ':title' => $title,
        ':stg'   => $stage,
        ':amt'   => (float)($extra['amount'] ?? 0),
        ':prob'  => (int)($extra['probability'] ?? 50),
        ':ecd'   => $extra['expected_close_date'] ?? null,
        ':at'    => $extra['assigned_to'] ?? ($p['assigned_to'] ?? null),
        ':notes' => $extra['notes'] ?? '',
        ':cb'    => $extra['created_by'] ?? null,
    ]);
}

/**
 * Insère un contrat construit à partir d'une opportunité (snapshot complet).
 * Les éventuelles surcharges spécifiques contrat (partner, cabinet, dates,
 * premium, billing/stage) passent par $extra.
 */
function conversion_insert_contract_from_opportunity(PDO $db, string $cid, array $o, array $extra = []): void {
    $today = date('Y-m-d');
    $sql = "INSERT INTO crminternet_contracts
        (id, opportunity_id, prospect_id, civility, last_name, first_name,
         phone, phone2, animateur, ancien_ligne, cin, birth_date, email,
         city, gouvernorat, delegation, zone, address, localisation_xy, code_postal,
         comment1, comment2, source, type_id, lead_status,
         partner, cabinet, signature_date, effective_date,
         premium, billing_status, stage_id, assigned_to)
        VALUES
        (:id, :oid, :pid, :civ, :ln, :fn,
         :ph, :ph2, :anim, :anc, :cin, :bd, :em,
         :ci, :gv, :dl, :zn, :ad, :gps, :cp,
         :c1, :c2, :src, :tid, :lst,
         :pa, :ca, :sd, :ed,
         :pr, :bs, :sid, :at)";
    $db->prepare($sql)->execute([
        ':id'   => $cid,
        ':oid'  => $o['id'] ?? null,
        ':pid'  => $o['prospect_id'] ?? null,
        ':civ'  => $o['civility'] ?? 'M',
        ':ln'   => $o['last_name'] ?? '',
        ':fn'   => $o['first_name'] ?? '',
        ':ph'   => $o['phone'] ?? '',
        ':ph2'  => $o['phone2'] ?? '',
        ':anim' => $o['animateur'] ?? null,
        ':anc'  => $o['ancien_ligne'] ?? null,
        ':cin'  => ($o['cin'] ?? null) ?: null,
        ':bd'   => $o['birth_date'] ?? null,
        ':em'   => $o['email'] ?? '',
        ':ci'   => $o['city'] ?? '',
        ':gv'   => $o['gouvernorat'] ?? '',
        ':dl'   => $o['delegation'] ?? '',
        ':zn'   => $o['zone'] ?? '',
        ':ad'   => $o['address'] ?? '',
        ':gps'  => $o['localisation_xy'] ?? null,
        ':cp'   => $o['code_postal'] ?? null,
        ':c1'   => $o['comment1'] ?? null,
        ':c2'   => $o['comment2'] ?? null,
        ':src'  => $o['source'] ?: 'Web',
        ':tid'  => $o['type_id'] ?? null,
        ':lst'  => $o['lead_status'] ?? null,
        ':pa'   => $extra['partner']      ?? 'NEOLIANE',
        ':ca'   => $extra['cabinet']      ?? 'Cabinet Paris 1',
        ':sd'   => $extra['signature_date'] ?? $today,
        ':ed'   => $extra['effective_date'] ?? ($extra['signature_date'] ?? $today),
        ':pr'   => (float)($extra['premium'] ?? ($o['amount'] ?? 0)),
        ':bs'   => $extra['billing_status'] ?? 'Pré-validé',
        ':sid'  => $extra['stage_id']     ?? null,
        ':at'   => $extra['assigned_to']  ?? ($o['assigned_to'] ?? ''),
    ]);
}

/**
 * Insère un contrat directement depuis un prospect (raccourci lead → contrat).
 * On passe par un faux array "opportunité" pour réutiliser la même fonction.
 */
function conversion_insert_contract_from_prospect(PDO $db, string $cid, array $p, array $extra = []): void {
    $oFake = [
        'id'              => null,
        'prospect_id'     => $p['id'] ?? null,
        'civility'        => $p['civility'] ?? 'M',
        'last_name'       => $p['last_name'] ?? '',
        'first_name'      => $p['first_name'] ?? '',
        'phone'           => $p['phone'] ?? '',
        'phone2'          => $p['phone2'] ?? '',
        'animateur'       => $p['animateur'] ?? null,
        'ancien_ligne'    => $p['ancien_ligne'] ?? null,
        'cin'             => $p['cin'] ?? null,
        'birth_date'      => $p['birth_date'] ?? null,
        'email'           => $p['email'] ?? '',
        'city'            => $p['city'] ?? '',
        'gouvernorat'     => $p['gouvernorat'] ?? '',
        'delegation'      => $p['delegation'] ?? '',
        'zone'            => $p['zone'] ?? '',
        'address'         => $p['address'] ?? '',
        'localisation_xy' => $p['localisation_xy'] ?? null,
        'code_postal'     => $p['code_postal'] ?? null,
        'comment1'        => $p['comment'] ?? null,
        'comment2'        => $p['comment2'] ?? null,
        'source'          => $p['source'] ?? '',
        'type_id'         => $p['type_id'] ?? null,
        'lead_status'     => $p['status'] ?? null,
        'amount'          => 0,
        'assigned_to'     => $p['assigned_to'] ?? '',
    ];
    conversion_insert_contract_from_opportunity($db, $cid, $oFake, $extra);
}

function migration_pick_initial_stage_id(PDO $db): ?string
{
    try {
        $id = $db->query(
            'SELECT id FROM crminternet_migration_stages WHERE is_initial = 1 ORDER BY position, id LIMIT 1'
        )->fetchColumn();
        if ($id) {
            return (string) $id;
        }
        $id = $db->query(
            'SELECT id FROM crminternet_migration_stages ORDER BY position, id LIMIT 1'
        )->fetchColumn();
        return $id ? (string) $id : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Insère un dossier migration depuis une opportunité (snapshot + champs migration).
 */
function conversion_insert_migration_from_opportunity(PDO $db, string $mid, array $o, array $extra = []): void
{
    $now = date('Y-m-d H:i:s');
    $stageId = $extra['stage_id'] ?? migration_pick_initial_stage_id($db);
    $sql = "INSERT INTO crminternet_migrations
        (id, opportunity_id, prospect_id, type_id, civility, last_name, first_name,
         phone, phone2, animateur, ancien_ligne, cin, birth_date, email,
         city, gouvernorat, delegation, zone, address, localisation_xy, code_postal,
         comment1, comment2, source, lead_status,
         old_operator, new_operator, porting_number, migration_type,
         requested_date, completed_date, technical_status, external_ref,
         stage_id, workflow_status, assigned_to, validated_at, validated_by, notes,
         created_by, created_at, updated_at)
        VALUES
        (:id, :oid, :pid, :tid, :civ, :ln, :fn,
         :ph, :ph2, :anim, :anc, :cin, :bd, :em,
         :ci, :gv, :dl, :zn, :ad, :gps, :cp,
         :c1, :c2, :src, :lst,
         :oo, :no, :port, :mtype,
         :rd, :cd, :tstat, :eref,
         :sid, :ws, :at, :vat, :vby, :notes,
         :cb, :ca, :ua)";
    $db->prepare($sql)->execute([
        ':id' => $mid,
        ':oid' => $o['id'] ?? '',
        ':pid' => $o['prospect_id'] ?? null,
        ':tid' => $o['type_id'] ?? null,
        ':civ' => $o['civility'] ?? 'M',
        ':ln' => $o['last_name'] ?? '',
        ':fn' => $o['first_name'] ?? '',
        ':ph' => $o['phone'] ?? '',
        ':ph2' => $o['phone2'] ?? null,
        ':anim' => $o['animateur'] ?? null,
        ':anc' => $o['ancien_ligne'] ?? null,
        ':cin' => ($o['cin'] ?? null) ?: null,
        ':bd' => $o['birth_date'] ?? null,
        ':em' => $o['email'] ?? '',
        ':ci' => $o['city'] ?? '',
        ':gv' => $o['gouvernorat'] ?? '',
        ':dl' => $o['delegation'] ?? '',
        ':zn' => $o['zone'] ?? '',
        ':ad' => $o['address'] ?? '',
        ':gps' => $o['localisation_xy'] ?? null,
        ':cp' => $o['code_postal'] ?? null,
        ':c1' => $o['comment1'] ?? null,
        ':c2' => $o['comment2'] ?? null,
        ':src' => $o['source'] ?: 'Web',
        ':lst' => $o['lead_status'] ?? null,
        ':oo' => $extra['old_operator'] ?? ($o['ancien_ligne'] ?? null),
        ':no' => $extra['new_operator'] ?? null,
        ':port' => $extra['porting_number'] ?? null,
        ':mtype' => $extra['migration_type'] ?? null,
        ':rd' => $extra['requested_date'] ?? date('Y-m-d'),
        ':cd' => $extra['completed_date'] ?? null,
        ':tstat' => $extra['technical_status'] ?? 'En cours',
        ':eref' => $extra['external_ref'] ?? null,
        ':sid' => $stageId,
        ':ws' => $extra['workflow_status'] ?? 'Créer',
        ':at' => $extra['assigned_to'] ?? ($o['assigned_to'] ?? ''),
        ':vat' => null,
        ':vby' => null,
        ':notes' => $extra['notes'] ?? ($o['notes'] ?? ''),
        ':cb' => $extra['created_by'] ?? null,
        ':ca' => $now,
        ':ua' => $now,
    ]);
}

function opportunity_is_terminal(array $o): bool
{
    return !empty($o['converted_to_contract']) || !empty($o['converted_to_migration']);
}

/** Initial opportunity stage name from pipeline config. */
function conversion_pick_initial_opportunity_stage(PDO $db): string
{
    require_once __DIR__ . '/pipeline_helpers.php';
    $oppStages = pipeline_load_stages($db, 'opportunity');
    foreach ($oppStages['list'] as $s) {
        if (!empty($s['is_initial'])) {
            return (string) $s['name'];
        }
    }
    return $oppStages['list'][0]['name'] ?? 'nouveau';
}

/**
 * Prospect → opportunity (full snapshot + attachments/custom fields/contract info).
 * Returns ['ok'=>bool, 'opportunityId'=>?, 'created'=>bool, 'error'=>?, 'code'=>?].
 */
function conversion_prospect_to_opportunity(PDO $db, string $pid, array $me, array $opts = []): array
{
    require_once __DIR__ . '/pipeline_helpers.php';
    require_once __DIR__ . '/attachment_helpers.php';
    require_once __DIR__ . '/custom_field_helpers.php';
    require_once __DIR__ . '/contract_info_helpers.php';

    $username = (string) ($me['username'] ?? '');
    $role = (string) ($me['role'] ?? '');
    $isAgent = in_array($role, ['Agent', 'AgentSuivi', 'AgentActivation', 'AgentVente'], true);

    if (($opts['checkAgent'] ?? true) && $isAgent) {
        $own = $db->prepare('SELECT assigned_to FROM crminternet_prospects WHERE id = :id');
        $own->execute([':id' => $pid]);
        if ((string) $own->fetchColumn() !== $username) {
            return ['ok' => false, 'error' => 'Accès refusé', 'code' => 403];
        }
    }

    $p = $db->prepare('SELECT * FROM crminternet_prospects WHERE id = :id');
    $p->execute([':id' => $pid]);
    $row = $p->fetch();
    if (!$row) {
        return ['ok' => false, 'error' => 'Prospect introuvable', 'code' => 404];
    }

    if (($row['outcome'] ?? '') === 'won') {
        $existingContract = $db->prepare('SELECT id FROM crminternet_contracts WHERE prospect_id = :p LIMIT 1');
        $existingContract->execute([':p' => $pid]);
        $contractId = $existingContract->fetchColumn();
        return [
            'ok' => false,
            'error' => $contractId
                ? 'Lead déjà gagné — contrat ' . $contractId . ' existant'
                : 'Lead déjà marqué gagné',
            'code' => 409,
            'contractId' => $contractId ? (string) $contractId : null,
        ];
    }

    $existingContract = $db->prepare('SELECT id FROM crminternet_contracts WHERE prospect_id = :p LIMIT 1');
    $existingContract->execute([':p' => $pid]);
    $directContractId = $existingContract->fetchColumn();
    if ($directContractId) {
        return [
            'ok' => false,
            'error' => 'Contrat déjà existant pour ce lead (' . $directContractId . ')',
            'code' => 409,
            'contractId' => (string) $directContractId,
        ];
    }

    if (!empty($row['converted']) && !empty($row['opportunity_id'])) {
        return [
            'ok' => true,
            'opportunityId' => (string) $row['opportunity_id'],
            'created' => false,
            'message' => 'Déjà converti',
        ];
    }

    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT * FROM crminternet_prospects WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $pid]);
        $row = $lock->fetch();
        if (!$row) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Prospect introuvable', 'code' => 404];
        }
        if (!empty($row['converted']) && !empty($row['opportunity_id'])) {
            $db->commit();
            return [
                'ok' => true,
                'opportunityId' => (string) $row['opportunity_id'],
                'created' => false,
                'message' => 'Déjà converti',
            ];
        }
        if (!empty($row['converted']) && empty($row['opportunity_id'])) {
            $db->prepare('UPDATE crminternet_prospects SET converted = 0, converted_at = NULL, opportunity_id = NULL WHERE id = :id')
               ->execute([':id' => $pid]);
            $row['converted'] = 0;
            $row['opportunity_id'] = null;
        }

        $oppStages = pipeline_load_stages($db, 'opportunity');
        $initialName = conversion_pick_initial_opportunity_stage($db);
        if (!empty($opts['stage'])) {
            $wanted = (string) $opts['stage'];
            if (isset($oppStages['byName'][$wanted])) {
                $initialName = $wanted;
            }
        }

        $title = isset($opts['title']) ? trim((string) $opts['title']) : '';
        if ($title === '') {
            $title = trim((string) $row['last_name'] . ' ' . (string) $row['first_name']);
        }

        $oid = (string) ($opts['opportunityId'] ?? ('O-' . substr(bin2hex(random_bytes(6)), 0, 10)));

        conversion_insert_opportunity_from_prospect($db, $oid, $row, [
            'title'       => $title,
            'stage'       => $initialName,
            'amount'      => (float) ($opts['amount'] ?? 0),
            'probability' => (int) ($opts['probability'] ?? 50),
            'assigned_to' => $row['assigned_to'] ?: $username,
            'created_by'  => $username,
            'notes'       => (string) ($opts['notes'] ?? ''),
        ]);
        $db->prepare(
            'UPDATE crminternet_prospects SET converted = 1, converted_at = NOW(), opportunity_id = :oid WHERE id = :id'
        )->execute([':oid' => $oid, ':id' => $pid]);

        try { custom_field_clone_entity($db, 'prospect', $pid, 'opportunity', $oid); } catch (Throwable $e) {}
        try { attachment_clone_entity($db, 'prospect', $pid, 'opportunity', $oid); } catch (Throwable $e) {}
        try { contract_info_clone_entity($db, 'prospect', $pid, 'opportunity', $oid, $username); } catch (Throwable $e) {}

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'Erreur conversion: ' . $e->getMessage(), 'code' => 500];
    }

    $source = (string) ($opts['source'] ?? 'manual');
    log_field_changes(
        $db,
        'prospect',
        $pid,
        ['converted' => 0, 'opportunity_id' => ''],
        ['converted' => 1, 'opportunity_id' => $oid, 'via' => $source],
        $username
    );
    log_field_changes(
        $db,
        'opportunity',
        $oid,
        ['exists' => 0],
        ['exists' => 1, 'created_from' => 'lead:' . $pid, 'stage' => $initialName],
        $username
    );
    audit_log($db, $me, 'prospect.convert_to_opportunity', 'prospect', $pid, ['opportunityId' => $oid, 'source' => $source]);

    $owner = $row['assigned_to'] ?? '';
    if ($owner) {
        notify_user(
            $db,
            $owner,
            'Lead converti',
            "Opportunité $oid créée pour {$row['first_name']} {$row['last_name']}",
            '/opportunities'
        );
    }

    return [
        'ok' => true,
        'opportunityId' => $oid,
        'created' => true,
        'message' => 'Opportunité créée',
        'stage' => $initialName,
    ];
}

/**
 * Opportunity → contract (full snapshot + lineage attachments/custom fields/contract info).
 */
function conversion_opportunity_to_contract(PDO $db, string $oid, array $me, array $opts = []): array
{
    require_once __DIR__ . '/attachment_helpers.php';
    require_once __DIR__ . '/custom_field_helpers.php';
    require_once __DIR__ . '/contract_info_helpers.php';

    $username = (string) ($me['username'] ?? '');

    $s = $db->prepare('SELECT * FROM crminternet_opportunities WHERE id = :id');
    $s->execute([':id' => $oid]);
    $o = $s->fetch();
    if (!$o) {
        return ['ok' => false, 'error' => 'Opportunité introuvable', 'code' => 404];
    }
    if (!empty($o['converted_to_contract']) && !empty($o['contract_id'])) {
        return [
            'ok' => true,
            'contractId' => (string) $o['contract_id'],
            'created' => false,
            'message' => 'Déjà converti en contrat',
        ];
    }
    if (!empty($o['converted_to_migration'])) {
        return ['ok' => false, 'error' => 'Opportunité déjà transformée en migration', 'code' => 409];
    }

    $partner = (string) ($opts['partner'] ?? 'NEOLIANE');
    $cabinet = (string) ($opts['cabinet'] ?? 'Cabinet Paris 1');
    $signatureDate = (string) ($opts['signature_date'] ?? date('Y-m-d'));
    $effectiveDate = (string) ($opts['effective_date'] ?? $signatureDate);
    $premium = array_key_exists('premium', $opts)
        ? (float) $opts['premium']
        : (float) ($o['amount'] ?? 0);
    $cid = (string) ($opts['contractId'] ?? ('C-' . substr(bin2hex(random_bytes(6)), 0, 10)));

    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT * FROM crminternet_opportunities WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $oid]);
        $o = $lock->fetch();
        if (!$o) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Opportunité introuvable', 'code' => 404];
        }
        if (!empty($o['converted_to_contract']) && !empty($o['contract_id'])) {
            $db->commit();
            return [
                'ok' => true,
                'contractId' => (string) $o['contract_id'],
                'created' => false,
                'message' => 'Déjà converti en contrat',
            ];
        }
        if (!empty($o['converted_to_migration'])) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Opportunité déjà transformée en migration', 'code' => 409];
        }

        conversion_insert_contract_from_opportunity($db, $cid, $o, [
            'partner'        => $partner,
            'cabinet'        => $cabinet,
            'signature_date' => $signatureDate,
            'effective_date' => $effectiveDate,
            'premium'        => $premium,
            'billing_status' => (string) ($opts['billing_status'] ?? 'Pré-validé'),
            'assigned_to'    => (string) ($opts['assigned_to'] ?? ''),
        ]);
        $db->prepare(
            'UPDATE crminternet_opportunities SET converted_to_contract = 1, contract_id = :cid, converted_at = NOW() WHERE id = :id'
        )->execute([':cid' => $cid, ':id' => $oid]);

        $prospectId = !empty($o['prospect_id']) ? (string) $o['prospect_id'] : null;
        try { attachment_clone_lineage($db, 'contract', $cid, $prospectId, $oid); } catch (Throwable $e) {}
        try {
            custom_field_clone_entity($db, 'opportunity', $oid, 'contract', $cid);
            if ($prospectId) {
                custom_field_clone_entity($db, 'prospect', $prospectId, 'contract', $cid);
            }
        } catch (Throwable $e) {}
        try {
            $cloned = contract_info_clone_entity($db, 'opportunity', $oid, 'contract', $cid, $username);
            if (!$cloned && $prospectId) {
                contract_info_clone_entity($db, 'prospect', $prospectId, 'contract', $cid, $username);
            }
        } catch (Throwable $e) {}

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'Erreur: ' . $e->getMessage(), 'code' => 500];
    }

    $source = (string) ($opts['source'] ?? 'manual');
    log_field_changes(
        $db,
        'opportunity',
        $oid,
        ['converted_to_contract' => 0],
        ['converted_to_contract' => 1, 'contract_id' => $cid, 'via' => $source],
        $username
    );
    audit_log($db, $me, 'opportunity.convert_to_contract', 'opportunity', $oid, ['contractId' => $cid, 'source' => $source]);

    return [
        'ok' => true,
        'contractId' => $cid,
        'created' => true,
        'message' => 'Opportunité convertie en contrat',
    ];
}

/**
 * Prospect → contract shortcut (mark_won / direct sale).
 * Clones categorized attachments (CIN Recto/Verso, etc.) via attachment_clone_entity.
 */
function conversion_mark_won_to_contract(PDO $db, string $pid, array $me, array $opts = []): array
{
    require_once __DIR__ . '/attachment_helpers.php';
    require_once __DIR__ . '/custom_field_helpers.php';
    require_once __DIR__ . '/contract_info_helpers.php';

    $username = (string) ($me['username'] ?? '');
    $role = (string) ($me['role'] ?? '');
    $isAgent = in_array($role, ['Agent', 'AgentSuivi', 'AgentActivation', 'AgentVente'], true);

    if (($opts['checkAgent'] ?? true) && $isAgent) {
        $own = $db->prepare('SELECT assigned_to FROM crminternet_prospects WHERE id = :id');
        $own->execute([':id' => $pid]);
        if ((string) $own->fetchColumn() !== $username) {
            return ['ok' => false, 'error' => 'Accès refusé', 'code' => 403];
        }
    }

    $db->beginTransaction();
    try {
        $lock = $db->prepare('SELECT * FROM crminternet_prospects WHERE id = :id FOR UPDATE');
        $lock->execute([':id' => $pid]);
        $p = $lock->fetch();
        if (!$p) {
            $db->rollBack();
            return ['ok' => false, 'error' => 'Prospect introuvable', 'code' => 404];
        }

        if (!empty($p['opportunity_id'])) {
            $db->rollBack();
            return [
                'ok' => false,
                'error' => 'Lead déjà converti en opportunité — passez par le pipeline opportunité ou révertissez d\'abord',
                'code' => 409,
                'opportunityId' => (string) $p['opportunity_id'],
            ];
        }

        if (($p['outcome'] ?? '') === 'won') {
            $existing = $db->prepare('SELECT id FROM crminternet_contracts WHERE prospect_id = :p LIMIT 1');
            $existing->execute([':p' => $pid]);
            $existingId = $existing->fetchColumn();
            if ($existingId) {
                $db->commit();
                return [
                    'ok' => true,
                    'contractId' => (string) $existingId,
                    'created' => false,
                    'message' => 'Contrat déjà créé pour ce lead',
                ];
            }
        }

        $partner = (string) ($opts['partner'] ?? 'NEOLIANE');
        $premium = (float) ($opts['premium'] ?? 950);
        $cid = (string) ($opts['contractId'] ?? ('C-' . substr(bin2hex(random_bytes(6)), 0, 10)));

        $db->prepare("UPDATE crminternet_prospects SET outcome='won', status='Vendu' WHERE id = :id")
           ->execute([':id' => $pid]);

        conversion_insert_contract_from_prospect($db, $cid, $p, [
            'partner'        => $partner,
            'cabinet'        => (string) ($opts['cabinet'] ?? 'Cabinet Paris 1'),
            'premium'        => $premium,
            'billing_status' => (string) ($opts['billing_status'] ?? 'Pré-validé'),
            'assigned_to'    => $p['assigned_to'] ?? '—',
        ]);

        try { attachment_clone_entity($db, 'prospect', $pid, 'contract', $cid); } catch (Throwable $e) {}
        try { custom_field_clone_entity($db, 'prospect', $pid, 'contract', $cid); } catch (Throwable $e) {}
        try { contract_info_clone_entity($db, 'prospect', $pid, 'contract', $cid, $username); } catch (Throwable $e) {}

        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        return ['ok' => false, 'error' => 'Erreur: ' . $e->getMessage(), 'code' => 500];
    }

    log_field_changes(
        $db,
        'prospect',
        $pid,
        ['outcome' => 'pending', 'status' => ''],
        ['outcome' => 'won', 'status' => 'Vendu'],
        $username
    );
    audit_log($db, $me, 'prospect.mark_won', 'prospect', $pid, ['contractId' => $cid, 'premium' => $premium]);

    $owner = $p['assigned_to'] ?? '';
    if ($owner) {
        notify_user($db, $owner, 'Vente confirmée', "Contrat $cid créé pour {$p['first_name']} {$p['last_name']}", "/contracts/$cid");
    }

    return [
        'ok' => true,
        'contractId' => $cid,
        'created' => true,
        'message' => 'Contrat créé',
    ];
}