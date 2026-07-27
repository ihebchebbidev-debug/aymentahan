<?php
/**
 * cleanup_prospect_types.php
 * One-shot script: deletes "Migration" and "Nouveau" prospect types
 * from the live database.
 *
 * HOW TO RUN:
 *   https://erp.ttshop.pro/backend/php/cleanup_prospect_types.php
 *
 * DELETE THIS FILE AFTER RUNNING.
 */

require_once __DIR__ . '/config.php';

$db = (new Database())->getConnection();

$idsToDelete = [
    'PT-399b1635a0', // Nouveau
    'PT-690492a9e5', // Migration
];

$deleted = 0;
foreach ($idsToDelete as $id) {
    // Remove associated custom fields first (FK safety)
    $db->prepare('DELETE FROM crminternet_custom_fields WHERE type_id = :id')->execute([':id' => $id]);
    $stmt = $db->prepare('DELETE FROM crminternet_prospect_types WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $deleted += $stmt->rowCount();
}

header('Content-Type: application/json');
echo json_encode([
    'ok'      => true,
    'deleted' => $deleted,
    'ids'     => $idsToDelete,
    'message' => "Supprime $deleted type(s) (Migration + Nouveau). Supprimez ce fichier maintenant.",
]);
