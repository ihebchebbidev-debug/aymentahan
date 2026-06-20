<?php
/**
 * Production seed: roles, prospect types, permissions matrix, teams.
 * Data files in backend/php/data/ (generated from production DB dump).
 */

function production_seed_data_dir(): string
{
    return __DIR__ . '/data';
}

function production_seed_load(string $file): array
{
    $path = production_seed_data_dir() . '/' . $file;
    if (!is_file($path)) {
        return [];
    }
    return require $path;
}

/** @return array<int, array{status:string, step?:string, message?:string, count?:int}> */
function crm_apply_production_seed(PDO $db): array
{
    $results = [];

    // --- Roles (10) ---
    $roles = production_seed_load('production_roles.php');
    if ($roles) {
        try {
            $stmt = $db->prepare(
                'INSERT INTO crminternet_roles (name, label, description, color, is_system, sort_order, created_at, updated_at)
                 VALUES (:n,:l,:d,:c,:s,:o,:ca,:ua)
                 ON DUPLICATE KEY UPDATE
                   label=VALUES(label), description=VALUES(description), color=VALUES(color),
                   is_system=VALUES(is_system), sort_order=VALUES(sort_order), updated_at=VALUES(updated_at)'
            );
            foreach ($roles as $r) {
                $stmt->execute([
                    ':n' => $r['name'],
                    ':l' => $r['label'],
                    ':d' => $r['description'],
                    ':c' => $r['color'],
                    ':s' => $r['is_system'],
                    ':o' => $r['sort_order'],
                    ':ca' => $r['created_at'],
                    ':ua' => $r['updated_at'],
                ]);
            }
            $results[] = ['status' => 'ok', 'step' => 'roles', 'count' => count($roles)];
        } catch (Throwable $e) {
            $results[] = ['status' => 'error', 'step' => 'roles', 'message' => $e->getMessage()];
        }
    }

    // --- Teams (required before team_roles) ---
    $teams = [
        ['team_backoffice', 'Backoffice', 'Agent Vente + Agent Activation + Agent Suivi'],
        ['team_commercial', 'Commercial', 'Agent Guichet + Agent Technico-Commercial'],
        ['team_direction', 'Direction', 'Superviseur'],
    ];
    try {
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM crminternet_teams') as $c) {
            $cols[$c['Field']] = true;
        }
        if (isset($cols['created_at'])) {
            $ins = $db->prepare(
                'INSERT IGNORE INTO crminternet_teams (id, name, description, created_at) VALUES (:id,:n,:d,NOW())'
            );
        } else {
            $ins = $db->prepare('INSERT IGNORE INTO crminternet_teams (id, name, description) VALUES (:id,:n,:d)');
        }
        foreach ($teams as [$id, $name, $desc]) {
            $ins->execute([':id' => $id, ':n' => $name, ':d' => $desc]);
        }
        $results[] = ['status' => 'ok', 'step' => 'teams', 'count' => count($teams)];
    } catch (Throwable $e) {
        $results[] = ['status' => 'error', 'step' => 'teams', 'message' => $e->getMessage()];
    }

    // --- Team ↔ roles ---
    $teamRoles = production_seed_load('production_team_roles.php');
    if ($teamRoles) {
        try {
            $ins = $db->prepare('INSERT IGNORE INTO crminternet_team_roles (team_id, role) VALUES (:t,:r)');
            foreach ($teamRoles as [$teamId, $role]) {
                $ins->execute([':t' => $teamId, ':r' => $role]);
            }
            $results[] = ['status' => 'ok', 'step' => 'team_roles', 'count' => count($teamRoles)];
        } catch (Throwable $e) {
            $results[] = ['status' => 'error', 'step' => 'team_roles', 'message' => $e->getMessage()];
        }
    }

    // --- Prospect types (8) ---
    $types = production_seed_load('production_prospect_types.php');
    if ($types) {
        $results[] = production_seed_prospect_types($db, $types);
    }

    // --- Guichet entities (TTshop + franchises) ---
    $entities = production_seed_load('production_guichet_entities.php');
    if ($entities) {
        $results[] = production_seed_guichet_entities($db, $entities);
    }

    // --- Permissions matrix (~812 rows) ---
    $perms = production_seed_load('production_permissions.php');
    if ($perms) {
        $existingTypePerms = [];
        foreach ($perms as $row) {
            if (($row[1] ?? '') === 'prospect.type') {
                $existingTypePerms[(string)($row[0] ?? '')] = true;
            }
        }
        foreach ($perms as $row) {
            if (($row[1] ?? '') === 'prospect.edit' && ($row[2] ?? 0) === 1) {
                $role = (string)($row[0] ?? '');
                if ($role !== '' && !isset($existingTypePerms[$role])) {
                    $perms[] = [$role, 'prospect.type', 1];
                }
            }
        }
        try {
            $stmt = $db->prepare(
                'INSERT INTO crminternet_role_permissions (role, permission, enabled)
                 VALUES (:r,:p,:e)
                 ON DUPLICATE KEY UPDATE enabled=VALUES(enabled)'
            );
            $db->beginTransaction();
            $n = 0;
            foreach ($perms as $row) {
                [$role, $perm, $enabled] = $row;
                $stmt->execute([':r' => $role, ':p' => $perm, ':e' => $enabled]);
                $n++;
            }
            $db->commit();
            $results[] = ['status' => 'ok', 'step' => 'permissions', 'count' => $n];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $results[] = ['status' => 'error', 'step' => 'permissions', 'message' => $e->getMessage()];
        }
    }

    $counts = [];
    foreach (
        [
            'crminternet_roles',
            'crminternet_prospect_types',
            'crminternet_role_permissions',
            'crminternet_teams',
            'crminternet_team_roles',
            'crminternet_guichet_entities',
        ] as $table
    ) {
        try {
            $counts[$table] = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        } catch (Throwable $e) {
            $counts[$table] = -1;
        }
    }
    $results['counts'] = $counts;

    // Migration module permissions (idempotent; mirrors contract roles on production DB).
    require_once __DIR__ . '/crm_terminal_migration_schema.php';
    try {
        crm_seed_migration_role_permissions($db);
        $results[] = ['status' => 'ok', 'step' => 'migration_permissions'];
    } catch (Throwable $e) {
        $results[] = ['status' => 'error', 'step' => 'migration_permissions', 'message' => $e->getMessage()];
    }

    return $results;
}

/**
 * @param array<int, array<string, mixed>> $types
 * @return array{status:string, step?:string, message?:string, count?:int}
 */
function production_seed_prospect_types(PDO $db, array $types): array
{
    try {
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM crminternet_prospect_types') as $c) {
            $cols[$c['Field']] = true;
        }

        if (isset($cols['description'], $cols['color'])) {
            $stmt = $db->prepare(
                'INSERT INTO crminternet_prospect_types (id, name, description, color, position, active, created_at)
                 VALUES (:id,:name,:desc,:color,:pos,:active,:ca)
                 ON DUPLICATE KEY UPDATE
                   name=VALUES(name), description=VALUES(description), color=VALUES(color),
                   position=VALUES(position), active=VALUES(active)'
            );
            foreach ($types as $t) {
                $created = $t['created_at'] ?? date('Y-m-d H:i:s');
                $stmt->execute([
                    ':id' => $t['id'],
                    ':name' => $t['name'],
                    ':desc' => $t['description'] ?? '',
                    ':color' => $t['color'] ?? 'primary',
                    ':pos' => (int) ($t['position'] ?? 0),
                    ':active' => (int) ($t['active'] ?? 1),
                    ':ca' => $created,
                ]);
            }
        } elseif (isset($cols['label'])) {
            $stmt = $db->prepare(
                'INSERT INTO crminternet_prospect_types (id, name, label, active, position, created_at)
                 VALUES (:id,:name,:label,:active,:pos,:ca)
                 ON DUPLICATE KEY UPDATE
                   name=VALUES(name), label=VALUES(label), position=VALUES(position), active=VALUES(active)'
            );
            $now = date('Y-m-d H:i:s');
            foreach ($types as $t) {
                $stmt->execute([
                    ':id' => $t['id'],
                    ':name' => $t['name'],
                    ':label' => $t['description'] !== '' ? $t['description'] : $t['name'],
                    ':active' => (int) ($t['active'] ?? 1),
                    ':pos' => (int) ($t['position'] ?? 0),
                    ':ca' => $t['created_at'] ?? $now,
                ]);
            }
        } else {
            $stmt = $db->prepare(
                'INSERT INTO crminternet_prospect_types (id, name) VALUES (:id,:name)
                 ON DUPLICATE KEY UPDATE name=VALUES(name)'
            );
            foreach ($types as $t) {
                $stmt->execute([':id' => $t['id'], ':name' => $t['name']]);
            }
        }

        return ['status' => 'ok', 'step' => 'prospect_types', 'count' => count($types)];
    } catch (Throwable $e) {
        return ['status' => 'error', 'step' => 'prospect_types', 'message' => $e->getMessage()];
    }
}

/**
 * @param array<int, array<string, mixed>> $entities
 * @return array{status:string, step?:string, message?:string, count?:int}
 */
function production_seed_guichet_entities(PDO $db, array $entities): array
{
    require_once __DIR__ . '/guichet_schema.php';
    ensure_guichet_schema($db);

    $allowedTypes = ['ttshop', 'franchise', 'autre'];

    try {
        $stmt = $db->prepare(
            'INSERT INTO crminternet_guichet_entities (id, name, type, city, active, created_at)
             VALUES (:id,:name,:type,:city,:active,:ca)
             ON DUPLICATE KEY UPDATE
               name=VALUES(name), type=VALUES(type), city=VALUES(city), active=VALUES(active)'
        );
        foreach ($entities as $e) {
            $type = $e['type'] ?? 'ttshop';
            if (!in_array($type, $allowedTypes, true)) {
                $type = 'autre';
            }
            $stmt->execute([
                ':id' => $e['id'],
                ':name' => $e['name'],
                ':type' => $type,
                ':city' => $e['city'] ?? null,
                ':active' => (int) ($e['active'] ?? 1),
                ':ca' => $e['created_at'] ?? date('Y-m-d H:i:s'),
            ]);
        }

        return ['status' => 'ok', 'step' => 'guichet_entities', 'count' => count($entities)];
    } catch (Throwable $e) {
        return ['status' => 'error', 'step' => 'guichet_entities', 'message' => $e->getMessage()];
    }
}
