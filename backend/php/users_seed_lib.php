<?php

require_once __DIR__ . '/production_seed.php';

/** Usernames / IDs never touched by the users seed. */
function users_seed_skip_ids(): array
{
    return ['U-ADMIN-1'];
}

function users_seed_skip_usernames(): array
{
    return ['AymenAdmin'];
}

function users_seed_load_users(): array
{
    return production_seed_load('production_users.php');
}

/**
 * Placeholder hash for accounts that cannot log in (e.g. legacy system user).
 */
function users_seed_disabled_password_hash(): string
{
    // bcrypt of random secret — account cannot log in
    return '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
}

/**
 * @return array<string, mixed>
 */
function crm_apply_users_seed(PDO $db): array
{
    $users = users_seed_load_users();
    if (!$users) {
        return [
            'status' => 'error',
            'step' => 'load',
            'message' => 'production_users.php missing — run php tools/build_production_users.php',
        ];
    }

    $cols = [];
    foreach ($db->query('SHOW COLUMNS FROM crminternet_users') as $c) {
        $cols[$c['Field']] = true;
    }

    $skipIds = users_seed_skip_ids();
    $skipNames = users_seed_skip_usernames();

    $inserted = 0;
    $updated = 0;
    $skipped = 0;
    $failed = 0;
    $errors = [];

    $byId = $db->prepare('SELECT id, username FROM crminternet_users WHERE id = :id LIMIT 1');
    $byUsername = $db->prepare('SELECT id, username FROM crminternet_users WHERE username = :u LIMIT 1');
    $byEmail = $db->prepare('SELECT id, username FROM crminternet_users WHERE email = :e LIMIT 1');

    foreach ($users as $u) {
        if (in_array($u['id'], $skipIds, true) || in_array($u['username'], $skipNames, true)) {
            $skipped++;
            continue;
        }

        $existing = null;
        $byId->execute([':id' => $u['id']]);
        $existing = $byId->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$existing) {
            $byUsername->execute([':u' => $u['username']]);
            $existing = $byUsername->fetch(PDO::FETCH_ASSOC) ?: null;
        }
        if (!$existing) {
            $byEmail->execute([':e' => $u['email']]);
            $existing = $byEmail->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if ($existing && in_array($existing['username'], $skipNames, true)) {
            $skipped++;
            continue;
        }

        $row = users_seed_normalize_row($u, $cols);
        if (($row['password_hash'] ?? '') === null || ($row['password_hash'] ?? '') === '') {
            $row['password_hash'] = users_seed_disabled_password_hash();
        } elseif (str_starts_with($row['password_hash'], '$2b$')) {
            $row['password_hash'] = '$2y$' . substr($row['password_hash'], 4);
        }

        try {
            if ($existing) {
                users_seed_update($db, $row, $cols, (string) $existing['id']);
                $updated++;
            } else {
                users_seed_insert($db, $row, $cols);
                $inserted++;
            }
        } catch (Throwable $e) {
            $failed++;
            $errors[] = [
                'status' => 'error',
                'username' => $u['username'],
                'id' => $u['id'],
                'message' => $e->getMessage(),
            ];
        }
    }

    $total = 0;
    try {
        $total = (int) $db->query('SELECT COUNT(*) FROM crminternet_users')->fetchColumn();
    } catch (Throwable $e) {
        /* ignore */
    }

    return array_merge(
        [
            'status' => count($errors) ? 'partial' : 'ok',
            'step' => 'users',
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'failed' => $failed,
            'counts' => ['crminternet_users' => $total],
        ],
        $errors ? ['errors' => $errors] : []
    );
}

/**
 * @param array<string, bool> $cols
 * @param array<string, mixed> $u
 * @return array<string, mixed>
 */
function users_seed_normalize_row(array $u, array $cols): array
{
    $all = [
        'id' => $u['id'],
        'username' => $u['username'],
        'full_name' => $u['full_name'] ?? $u['username'],
        'job_title' => $u['job_title'] ?? null,
        'birth_date' => users_seed_null_date($u['birth_date'] ?? null),
        'cin' => users_seed_null_str($u['cin'] ?? null),
        'company' => users_seed_null_str($u['company'] ?? null),
        'contract_type' => users_seed_null_str($u['contract_type'] ?? null),
        'salary' => $u['salary'] ?? null,
        'salary_increase' => $u['salary_increase'] ?? null,
        'contract_start' => users_seed_null_date($u['contract_start'] ?? null),
        'contract_end' => users_seed_null_date($u['contract_end'] ?? null),
        'renewal_start' => users_seed_null_date($u['renewal_start'] ?? null),
        'renewal_end' => users_seed_null_date($u['renewal_end'] ?? null),
        'observations' => users_seed_null_str($u['observations'] ?? null),
        'phone' => users_seed_null_str($u['phone'] ?? null),
        'rib' => users_seed_null_str($u['rib'] ?? null),
        'hire_date' => users_seed_null_date($u['hire_date'] ?? null),
        'email' => $u['email'],
        'password_hash' => ($u['password_hash'] ?? '') !== '' ? $u['password_hash'] : null,
        'role' => $u['role'] ?? 'Agent',
        'team' => $u['team'] ?? 'Lead-Actifs',
        'active' => (int) ($u['active'] ?? 1),
        'must_change_password' => (int) ($u['must_change_password'] ?? 0),
        'created_at' => $u['created_at'] ?? date('Y-m-d H:i:s'),
        'updated_at' => $u['updated_at'] ?? date('Y-m-d H:i:s'),
        'guichet_entity_id' => users_seed_null_str($u['guichet_entity_id'] ?? null),
        'team_id' => users_seed_null_str($u['team_id'] ?? null),
    ];

    return array_intersect_key($all, $cols);
}

function users_seed_null_str(?string $v): ?string
{
    if ($v === null || $v === '') {
        return null;
    }
    return $v;
}

function users_seed_null_date(?string $v): ?string
{
    if ($v === null || $v === '' || $v === '0000-00-00') {
        return null;
    }
    return $v;
}

/**
 * @param array<string, mixed> $row
 * @param array<string, bool> $cols
 */
function users_seed_insert(PDO $db, array $row, array $cols): void
{
    $keys = array_keys($row);
    $colsSql = implode(', ', $keys);
    $params = implode(', ', array_map(fn($k) => ':' . $k, $keys));
    $stmt = $db->prepare("INSERT INTO crminternet_users ({$colsSql}) VALUES ({$params})");
    $bind = [];
    foreach ($row as $k => $v) {
        $bind[':' . $k] = $v;
    }
    $stmt->execute($bind);
}

/**
 * @param array<string, mixed> $row
 * @param array<string, bool> $cols
 */
function users_seed_update(PDO $db, array $row, array $cols, string $existingId): void
{
    unset($row['id'], $row['created_at']);
    $sets = [];
    $bind = [':_id' => $existingId];
    foreach ($row as $k => $v) {
        $sets[] = "{$k} = :{$k}";
        $bind[":{$k}"] = $v;
    }
    if (!$sets) {
        return;
    }
    $sql = 'UPDATE crminternet_users SET ' . implode(', ', $sets) . ' WHERE id = :_id';
    $db->prepare($sql)->execute($bind);
}
