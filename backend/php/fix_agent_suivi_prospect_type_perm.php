<?php
/**
 * fix_agent_suivi_prospect_type_perm.php
 * One-shot: explicitly sets prospect_type.edit = 0 and prospect_type.delete = 0
 * for the AgentSuivi role in the live database.
 *
 * HOW TO RUN:
 *   https://erp.ttshop.pro/backend/php/fix_agent_suivi_prospect_type_perm.php
 *
 * DELETE THIS FILE AFTER RUNNING.
 */

require_once __DIR__ . '/config.php';
$db = (new Database())->getConnection();

$role = 'AgentSuivi';
$permsToRevoke = ['prospect_type.edit', 'prospect_type.delete'];

$results = [];
foreach ($permsToRevoke as $perm) {
    // Upsert: if row exists update it, otherwise insert with enabled=0
    $check = $db->prepare("SELECT COUNT(*) FROM crminternet_role_permissions WHERE role = :r AND permission = :p");
    $check->execute([':r' => $role, ':p' => $perm]);
    if ((int)$check->fetchColumn() > 0) {
        $db->prepare("UPDATE crminternet_role_permissions SET enabled = 0 WHERE role = :r AND permission = :p")
           ->execute([':r' => $role, ':p' => $perm]);
        $results[$perm] = 'updated -> disabled';
    } else {
        $db->prepare("INSERT INTO crminternet_role_permissions (role, permission, enabled) VALUES (:r, :p, 0)")
           ->execute([':r' => $role, ':p' => $perm]);
        $results[$perm] = 'inserted -> disabled';
    }
}

header('Content-Type: application/json');
echo json_encode([
    'ok'      => true,
    'role'    => $role,
    'changes' => $results,
    'message' => 'prospect_type.edit et prospect_type.delete sont maintenant desactives pour AgentSuivi. Supprimez ce fichier.',
]);
