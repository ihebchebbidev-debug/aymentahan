<?php
require_once __DIR__ . '/config.php';

try {
    $stmt = $db->query("SELECT id, username, full_name, role, active, email FROM crminternet_users ORDER BY role ASC, username ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['users' => $users]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
