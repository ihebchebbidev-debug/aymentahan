<?php
/**
 * Terminal "Migration" module (peer to Contract) — schema, stages seed, permissions.
 */
require_once __DIR__ . '/schema_repair.php';

/** All migration-module permission keys (keep in sync with src/lib/permissions.ts). */
function crm_migration_permission_keys(): array
{
    return [
        'page.migrations',
        'migration.view',
        'migration.add',
        'migration.edit',
        'migration.delete',
        'migration.export',
        'migration.import',
        'migration.validate',
        'migration.revert',
        'migration.stages',
        'opportunity.convert_migration',
    ];
}

function user_can_migration_view(PDO $db, array $me): bool
{
    if (($me['role'] ?? '') === 'Administrateur') {
        return true;
    }
    return user_has_permission($db, $me, 'migration.view')
        || user_has_permission($db, $me, 'page.migrations');
}

function require_migration_view(PDO $db, array $me): void
{
    if (!user_can_migration_view($db, $me)) {
        fail('Forbidden', 403);
    }
}

function user_can_convert_opportunity_to_migration(PDO $db, array $me): bool
{
    if (($me['role'] ?? '') === 'Administrateur') {
        return true;
    }
    return user_has_permission($db, $me, 'opportunity.convert_migration')
        || user_has_permission($db, $me, 'opportunity.convert');
}

function require_convert_opportunity_to_migration(PDO $db, array $me): void
{
    if (!user_can_convert_opportunity_to_migration($db, $me)) {
        fail('Forbidden', 403);
    }
}

function crm_seed_migration_role_permissions(PDO $db): void
{
    $keys = crm_migration_permission_keys();
    $stmt = $db->prepare(
        'INSERT INTO crminternet_role_permissions (role, permission, enabled)
         VALUES (:r, :p, :e)
         ON DUPLICATE KEY UPDATE enabled = GREATEST(enabled, VALUES(enabled))'
    );

    $grant = function (array $roles, array $perms, int $enabled = 1) use ($stmt) {
        foreach ($roles as $role) {
            foreach ($perms as $p) {
                $stmt->execute([':r' => $role, ':p' => $p, ':e' => $enabled]);
            }
        }
    };

    $all = $keys;
    $grant(['Administrateur'], $all, 1);
    $grant(
        ['Manager', 'AgentSuivi', 'AgentActivation', 'Agent', 'AgentVente', 'Backoffice'],
        [
            'page.migrations', 'migration.view', 'migration.add', 'migration.edit',
            'migration.export', 'migration.validate', 'opportunity.convert_migration',
        ],
        1
    );
    $grant(['AgentSuivi', 'AgentActivation', 'AgentVente'], ['opportunity.convert_migration'], 1);

    // Production DBs seeded from dump: mirror contract/opportunity access → migration module.
    try {
        $contractRoles = $db->query(
            "SELECT DISTINCT role FROM crminternet_role_permissions
             WHERE permission IN ('page.contracts', 'contract.view') AND enabled = 1"
        )->fetchAll(PDO::FETCH_COLUMN);
        $mirror = [
            'page.migrations', 'migration.view', 'migration.edit', 'migration.export',
        ];
        foreach ($contractRoles as $role) {
            if ($role === 'Administrateur' || $role === '' || $role === null) {
                continue;
            }
            $grant([(string) $role], $mirror, 1);
        }

        $convertRoles = $db->query(
            "SELECT DISTINCT role FROM crminternet_role_permissions
             WHERE permission = 'opportunity.convert' AND enabled = 1"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($convertRoles as $role) {
            if ($role === 'Administrateur' || $role === '' || $role === null) {
                continue;
            }
            $grant([(string) $role], ['opportunity.convert_migration'], 1);
        }
    } catch (Throwable $e) {
        /* role_permissions table may be missing on partial installs */
    }
}

function crm_animacom_terminal_stage_rows(): array
{
    return [
        ['CS-1', 'MS-1', 'Créer', 'info', 1, 1, 0, 0],
        ['CS-2', 'MS-2', 'Retour', 'destructive', 2, 0, 0, 1],
        ['CS-3', 'MS-3', 'Mes non connecté', 'warning', 3, 0, 0, 0],
        ['CS-4', 'MS-4', 'Validé', 'success', 4, 0, 1, 0],
    ];
}

/** Contract + migration terminal statuses (Créer, Retour, Mes non connecté, Validé). */
function crm_seed_animacom_terminal_stages(PDO $db): void
{
    $cStmt = $db->prepare(
        'INSERT INTO crminternet_contract_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action)
         VALUES (:id, :n, :c, :p, :i, :w, :l, \'none\')
         ON DUPLICATE KEY UPDATE name=VALUES(name), color=VALUES(color), position=VALUES(position),
             is_initial=VALUES(is_initial), is_won=VALUES(is_won), is_lost=VALUES(is_lost)'
    );
    $mStmt = $db->prepare(
        'INSERT INTO crminternet_migration_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action)
         VALUES (:id, :n, :c, :p, :i, :w, :l, \'none\')
         ON DUPLICATE KEY UPDATE name=VALUES(name), color=VALUES(color), position=VALUES(position),
             is_initial=VALUES(is_initial), is_won=VALUES(is_won), is_lost=VALUES(is_lost)'
    );
    foreach (crm_animacom_terminal_stage_rows() as [$csId, $msId, $name, $color, $pos, $ini, $won, $lost]) {
        $cStmt->execute([
            ':id' => $csId, ':n' => $name, ':c' => $color, ':p' => $pos,
            ':i' => $ini, ':w' => $won, ':l' => $lost,
        ]);
        $mStmt->execute([
            ':id' => $msId, ':n' => $name, ':c' => $color, ':p' => $pos,
            ':i' => $ini, ':w' => $won, ':l' => $lost,
        ]);
    }
    crm_purge_obsolete_migration_stages($db);
}

/** Drop legacy migration stages (e.g. Annulé / MS-5) so list matches contracts. */
function crm_purge_obsolete_migration_stages(PDO $db): void
{
    $keepIds = array_map(fn($row) => $row[1], crm_animacom_terminal_stage_rows());
    $keepNames = array_map(fn($row) => $row[2], crm_animacom_terminal_stage_rows());
    $legacyNames = [
        'Annulé', 'Dossier ouvert', 'Pièces reçues', 'Envoyé opérateur', 'Effectué',
        'MS-5 Terminée', 'Pré-validé', 'Validé Confirmation',
    ];

    try {
        $db->exec("UPDATE crminternet_migrations SET workflow_status = 'Retour', stage_id = 'MS-2'
            WHERE workflow_status IN ('Annulé', 'Annuler la confirmation') OR stage_id = 'MS-5'");
        $db->exec("UPDATE crminternet_migrations SET workflow_status = 'Validé', stage_id = 'MS-4'
            WHERE workflow_status IN ('Effectué', 'Validé Confirmation')");
        $db->exec("UPDATE crminternet_migrations SET workflow_status = 'Créer', stage_id = 'MS-1'
            WHERE workflow_status IN ('Dossier ouvert', 'Pièces reçues', 'Envoyé opérateur', 'Pré-validé')");

        $inIds = implode(',', array_map(fn($id) => $db->quote($id), $keepIds));
        $inNames = implode(',', array_map(fn($n) => $db->quote($n), $keepNames));
        $db->exec("DELETE FROM crminternet_migration_stages
            WHERE id NOT IN ($inIds) AND name NOT IN ($inNames)");
        $legacyIn = implode(',', array_map(fn($n) => $db->quote($n), $legacyNames));
        $db->exec("DELETE FROM crminternet_migration_stages WHERE name IN ($legacyIn)");
    } catch (Throwable $e) {
        /* best-effort */
    }
}

function crm_sync_migration_stages_from_contract_stages(PDO $db): void
{
    ensure_migration_stages_schema($db);
    try {
        $rows = $db->query('SELECT * FROM crminternet_contract_stages ORDER BY position, id')->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $rows = [];
    }
    if (!$rows) {
        crm_seed_animacom_terminal_stages($db);
        return;
    }

    $stmt = $db->prepare(
        'INSERT INTO crminternet_migration_stages (id, name, color, position, is_initial, is_won, is_lost, auto_action)
         VALUES (:id, :n, :c, :p, :i, :w, :l, \'none\')
         ON DUPLICATE KEY UPDATE name=VALUES(name), color=VALUES(color), position=VALUES(position),
             is_initial=VALUES(is_initial), is_won=VALUES(is_won), is_lost=VALUES(is_lost)'
    );
    $pos = 0;
    foreach ($rows as $r) {
        $pos++;
        $csId = (string)($r['id'] ?? '');
        $msId = preg_match('/^CS-(\d+)$/', $csId, $m) ? ('MS-' . $m[1]) : ('MS-' . $pos);
        $stmt->execute([
            ':id' => $msId,
            ':n' => $r['name'] ?? '',
            ':c' => $r['color'] ?? 'muted',
            ':p' => (int)($r['position'] ?? $pos),
            ':i' => !empty($r['is_initial']) ? 1 : 0,
            ':w' => !empty($r['is_won']) ? 1 : 0,
            ':l' => !empty($r['is_lost']) ? 1 : 0,
        ]);
    }
}

function crm_seed_migration_stages(PDO $db): void
{
    crm_seed_animacom_terminal_stages($db);
}

function ensure_migration_stages_schema(PDO $db): void
{
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS crminternet_migration_stages (
            id VARCHAR(40) NOT NULL,
            name VARCHAR(80) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT 'muted',
            position INT NOT NULL DEFAULT 0,
            is_initial TINYINT(1) NOT NULL DEFAULT 0,
            is_won TINYINT(1) NOT NULL DEFAULT 0,
            is_lost TINYINT(1) NOT NULL DEFAULT 0,
            auto_action VARCHAR(40) NOT NULL DEFAULT 'none',
            PRIMARY KEY (id),
            UNIQUE KEY uniq_migration_stage_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        /* ignore */
    }
    crm_try_alter($db, 'ALTER TABLE crminternet_migration_stages ADD COLUMN is_initial TINYINT(1) NOT NULL DEFAULT 0');
    crm_try_alter($db, 'ALTER TABLE crminternet_migration_stages ADD COLUMN is_won TINYINT(1) NOT NULL DEFAULT 0');
    crm_try_alter($db, 'ALTER TABLE crminternet_migration_stages ADD COLUMN is_lost TINYINT(1) NOT NULL DEFAULT 0');
    crm_seed_migration_stages($db);
}

function ensure_crm_migrations_table_schema(PDO $db): void
{
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS crminternet_migrations (
            id VARCHAR(40) NOT NULL,
            opportunity_id VARCHAR(40) NOT NULL,
            prospect_id VARCHAR(40) NULL,
            type_id VARCHAR(40) NULL,
            civility ENUM('M','Mme') NOT NULL DEFAULT 'M',
            last_name VARCHAR(120) NOT NULL DEFAULT '',
            first_name VARCHAR(120) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            phone2 VARCHAR(40) NULL,
            animateur VARCHAR(120) NULL,
            ancien_ligne VARCHAR(40) NULL,
            cin VARCHAR(40) NULL,
            birth_date DATE NULL,
            email VARCHAR(160) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            gouvernorat VARCHAR(120) NOT NULL DEFAULT '',
            delegation VARCHAR(120) NOT NULL DEFAULT '',
            zone VARCHAR(120) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            localisation_xy VARCHAR(64) NULL,
            code_postal VARCHAR(20) NULL,
            comment1 TEXT NULL,
            comment2 TEXT NULL,
            source VARCHAR(80) NOT NULL DEFAULT '',
            lead_status VARCHAR(80) NULL,
            old_operator VARCHAR(80) NULL,
            new_operator VARCHAR(80) NULL,
            porting_number VARCHAR(40) NULL,
            migration_type VARCHAR(40) NULL,
            requested_date DATE NULL,
            completed_date DATE NULL,
            technical_status VARCHAR(80) NULL,
            external_ref VARCHAR(80) NULL,
            stage_id VARCHAR(40) NULL,
            workflow_status VARCHAR(80) NOT NULL DEFAULT 'Créer',
            assigned_to VARCHAR(80) NOT NULL DEFAULT '',
            validated_at DATETIME NULL,
            validated_by VARCHAR(80) NULL,
            notes TEXT NULL,
            created_by VARCHAR(80) NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_migration_opportunity (opportunity_id),
            KEY idx_migration_prospect (prospect_id),
            KEY idx_migration_assigned (assigned_to),
            KEY idx_migration_stage (stage_id),
            KEY idx_migration_workflow (workflow_status),
            KEY idx_migration_created (created_at),
            KEY idx_migration_completed (completed_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {
        /* ignore */
    }

    $alters = [
        'ALTER TABLE crminternet_migrations ADD COLUMN opportunity_id VARCHAR(40) NOT NULL',
        'ALTER TABLE crminternet_migrations ADD COLUMN old_operator VARCHAR(80) NULL',
        'ALTER TABLE crminternet_migrations ADD COLUMN new_operator VARCHAR(80) NULL',
        'ALTER TABLE crminternet_migrations ADD COLUMN porting_number VARCHAR(40) NULL',
        'ALTER TABLE crminternet_migrations ADD COLUMN workflow_status VARCHAR(80) NOT NULL DEFAULT \'Créer\'',
        'ALTER TABLE crminternet_migrations ADD COLUMN stage_id VARCHAR(40) NULL',
        'ALTER TABLE crminternet_migrations ADD COLUMN deleted_at DATETIME NULL',
    ];
    foreach ($alters as $sql) {
        crm_try_alter($db, $sql);
    }
}

function ensure_opportunity_migration_columns(PDO $db): void
{
    crm_try_alter($db, 'ALTER TABLE crminternet_opportunities ADD COLUMN converted_to_migration TINYINT(1) NOT NULL DEFAULT 0');
    crm_try_alter($db, 'ALTER TABLE crminternet_opportunities ADD COLUMN migration_id VARCHAR(40) NULL');
    crm_try_alter($db, 'ALTER TABLE crminternet_opportunities ADD INDEX idx_opp_migration (migration_id)');
}

/** Extend contract_info + attachments entity types to include migration. */
function ensure_migration_entity_support(PDO $db): void
{
    crm_try_alter(
        $db,
        "ALTER TABLE crminternet_contract_info MODIFY COLUMN entity_type VARCHAR(20) NOT NULL"
    );
    crm_try_alter(
        $db,
        "ALTER TABLE crminternet_custom_field_values MODIFY COLUMN entity VARCHAR(20) NOT NULL"
    );
}

function ensure_terminal_migration_schema(PDO $db): void
{
    ensure_migration_stages_schema($db);
    ensure_crm_migrations_table_schema($db);
    ensure_opportunity_migration_columns($db);
    ensure_migration_entity_support($db);
    crm_seed_migration_role_permissions($db);
}

/**
 * @return array<string, mixed>
 */
function terminal_migration_schema_status(PDO $db): array
{
    $status = [];
    foreach (['crminternet_migrations', 'crminternet_migration_stages'] as $table) {
        try {
            $status[$table] = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        } catch (Throwable $e) {
            $status[$table] = -1;
        }
    }
    try {
        $cols = $db->query('SHOW COLUMNS FROM crminternet_opportunities LIKE \'converted_to_migration\'')->fetch();
        $status['opportunity_migration_columns'] = (bool) $cols;
    } catch (Throwable $e) {
        $status['opportunity_migration_columns'] = false;
    }
    return $status;
}
