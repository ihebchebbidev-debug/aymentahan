<?php
require_once __DIR__ . '/config.php';

function secret_effective_perms_for(PDO $db, string $username, string $role): array {
    $effective = [];
    try {
        if ($role === 'Administrateur') {
            return ['__admin__' => true];
        }
        $st = $db->prepare("SELECT permission FROM crminternet_role_permissions WHERE role = :r AND enabled = 1");
        $st->execute([':r' => $role]);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $effective[$r['permission']] = true;
        }

        if (function_exists('active_grants_for')) {
            $g = active_grants_for($db, $username);
            foreach ($g['roles'] as $extraRole) {
                $st->execute([':r' => $extraRole]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $effective[$r['permission']] = true;
                }
            }
            foreach ($g['permissions'] as $p) {
                $effective[$p] = true;
            }
        }

        if (function_exists('user_overrides_for')) {
            $ov = user_overrides_for($db, $username);
            foreach ($ov['allow'] as $p) $effective[$p] = true;
            foreach ($ov['deny']  as $p) $effective[$p] = false;
        }
    } catch (Throwable $e) {}
    return $effective;
}

try {
    $db = (new Database())->getConnection();
    
    $username = trim((string)($_GET['user'] ?? ''));
    $exportAll = !empty($_GET['export']);
    
    if ($exportAll) {
        $rolesStmt = $db->query("SELECT name, label FROM crminternet_roles ORDER BY name ASC");
        $roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $rolePerms = [];
        $rpStmt = $db->query("SELECT role, permission FROM crminternet_role_permissions WHERE enabled = 1");
        foreach ($rpStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $rolePerms[$row['role']][] = $row['permission'];
        }
        
        $usersStmt = $db->query("SELECT username, full_name, role, active FROM crminternet_users ORDER BY role ASC, username ASC");
        $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'roles' => $roles,
            'rolePermissions' => $rolePerms,
            'users' => $users,
        ]);
        exit;
    }
    
    if ($username !== '') {
        $st = $db->prepare("SELECT id, username, full_name, role, active, email, team FROM crminternet_users WHERE username = :u LIMIT 1");
        $st->execute([':u' => $username]);
        $user = $st->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            http_response_code(404);
            echo json_encode(['error' => 'User not found']);
            exit;
        }
        
        $role = $user['role'] ?? '';
        $effective = secret_effective_perms_for($db, $username, $role);
        $overrides = function_exists('user_overrides_for') ? user_overrides_for($db, $username) : ['allow' => [], 'deny' => []];
        $grants = function_exists('active_grants_for') ? active_grants_for($db, $username) : ['roles' => [], 'permissions' => []];
        
        echo json_encode([
            'user' => $user,
            'effectivePermissions' => $effective,
            'overrides' => $overrides,
            'grants' => $grants,
        ]);
        exit;
    }

    $stmt = $db->query("SELECT id, username, full_name, role, active, email FROM crminternet_users ORDER BY role ASC, username ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['users' => $users]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
