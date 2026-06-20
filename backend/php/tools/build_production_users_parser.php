<?php

function users_seed_parse_values_block(string $body): array
{
    $fields = [
        'id', 'username', 'full_name', 'job_title', 'birth_date', 'cin', 'company', 'contract_type',
        'salary', 'salary_increase', 'contract_start', 'contract_end', 'renewal_start', 'renewal_end',
        'observations', 'phone', 'rib', 'hire_date', 'email', 'password_hash', 'role', 'team',
        'active', 'must_change_password', 'created_at', 'updated_at', 'guichet_entity_id', 'team_id',
    ];

    $body = trim($body);
    $body = rtrim($body, ';');
    $rows = preg_split('/\)\s*,\s*\(/', $body);
    $users = [];

    foreach ($rows as $raw) {
        $vals = users_seed_parse_tuple($raw);
        if (count($vals) < count($fields)) {
            continue;
        }
        $u = array_combine($fields, array_slice($vals, 0, count($fields)));
        foreach (['salary', 'salary_increase'] as $n) {
            if ($u[$n] === null || $u[$n] === '') {
                $u[$n] = null;
            } else {
                $u[$n] = (float) $u[$n];
            }
        }
        foreach (['active', 'must_change_password'] as $n) {
            $u[$n] = (int) $u[$n];
        }
        if (($u['password_hash'] ?? '') === '') {
            $u['password_hash'] = null;
        }
        $users[] = $u;
    }

    return $users;
}

function users_seed_parse_tuple(string $row): array
{
    $row = trim($row, " \t\n\r()");
    $out = [];
    $len = strlen($row);
    $i = 0;
    while ($i < $len) {
        while ($i < $len && ($row[$i] === ' ' || $row[$i] === ',')) {
            $i++;
        }
        if ($i >= $len) {
            break;
        }
        if (strtoupper(substr($row, $i, 4)) === 'NULL') {
            $out[] = null;
            $i += 4;
            continue;
        }
        if ($row[$i] === "'") {
            $i++;
            $buf = '';
            while ($i < $len) {
                if ($row[$i] === '\\' && $i + 1 < $len) {
                    $buf .= $row[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($row[$i] === "'") {
                    if ($i + 1 < $len && $row[$i + 1] === "'") {
                        $buf .= "'";
                        $i += 2;
                        continue;
                    }
                    $i++;
                    break;
                }
                $buf .= $row[$i];
                $i++;
            }
            $out[] = $buf;
            continue;
        }
        $start = $i;
        while ($i < $len && $row[$i] !== ',') {
            $i++;
        }
        $out[] = trim(substr($row, $start, $i - $start));
    }
    return $out;
}
