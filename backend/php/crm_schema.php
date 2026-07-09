<?php
/**
 * CRM schema — mirrors phpMyAdmin dump (May 2026), adjusted for MySQL 5.7 / MariaDB:
 * - utf8mb4_unicode_ci (no 8.0-only collations)
 * - No DEFAULT CURRENT_TIMESTAMP when a table has multiple DATETIME columns
 * - reclamations mois/annee as plain columns (no GENERATED — old MariaDB)
 */

function crm_drop_table_sql(): array {
    $tables = [
        'crminternet_user_permission_overrides', 'crminternet_user_grants',
        'crminternet_team_roles', 'crminternet_teams', 'crminternet_tasks',
        'crminternet_settings', 'crminternet_schema_migrations',
        'crminternet_reclamation_counter', 'crminternet_reclamations',
        'crminternet_prospect_types', 'crminternet_pipeline_transitions',
        'crminternet_payroll', 'crminternet_opportunity_stages', 'crminternet_opportunities',
        'crminternet_notifications', 'crminternet_login_otp',
        'crminternet_lead_stages', 'crminternet_lead_actions', 'crminternet_idle_timeouts',
        'crminternet_guichet_objectives', 'crminternet_guichet_entries',
        'crminternet_guichet_entities', 'crminternet_guichet_dossiers',
        'crminternet_filter_preset_user_choice', 'crminternet_filter_presets',
        'crminternet_commissions', 'crminternet_external_agents',
        'crminternet_contract_stages', 'crminternet_contract_info', 'crminternet_contracts',
        'crminternet_custom_field_values', 'crminternet_custom_fields',
        'crminternet_chat_message_reads', 'crminternet_chat_messages',
        'crminternet_chat_members', 'crminternet_chat_conversations',
        'crminternet_activity_log', 'crminternet_audit_log', 'crminternet_attendance',
        'crminternet_calendar_events', 'crminternet_attachments', 'crminternet_prospects',
        'crminternet_role_permissions', 'crminternet_roles', 'crminternet_users',
        // legacy tables from older setup.php
        'crminternet_user_permissions', 'crminternet_user_roles', 'crminternet_permissions',
        'crminternet_activities', 'crminternet_leads', 'crminternet_notes',
    ];
    return array_map(fn($t) => "DROP TABLE IF EXISTS `$t`", $tables);
}

/** @return array<string,string> table_key => CREATE TABLE SQL */
function crm_create_tables_sql(): array {
    return [
        'users' => "CREATE TABLE crminternet_users (
            id VARCHAR(40) NOT NULL,
            username VARCHAR(80) NOT NULL,
            full_name VARCHAR(120) NOT NULL,
            job_title VARCHAR(120) DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            cin VARCHAR(40) DEFAULT NULL,
            company VARCHAR(120) DEFAULT NULL,
            contract_type VARCHAR(40) DEFAULT NULL,
            salary DECIMAL(10,3) DEFAULT NULL,
            salary_increase DECIMAL(10,3) DEFAULT NULL,
            contract_start DATE DEFAULT NULL,
            contract_end DATE DEFAULT NULL,
            renewal_start DATE DEFAULT NULL,
            renewal_end DATE DEFAULT NULL,
            observations TEXT,
            phone VARCHAR(40) DEFAULT NULL,
            rib VARCHAR(40) DEFAULT NULL,
            hire_date DATE DEFAULT NULL,
            email VARCHAR(160) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(64) NOT NULL DEFAULT 'Agent',
            team VARCHAR(80) NOT NULL DEFAULT 'Lead-Actifs',
            active TINYINT(1) NOT NULL DEFAULT 1,
            must_change_password TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            guichet_entity_id VARCHAR(40) DEFAULT NULL,
            team_id VARCHAR(40) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY username (username),
            UNIQUE KEY email (email),
            UNIQUE KEY uniq_users_cin (cin),
            KEY idx_users_company (company),
            KEY idx_users_contract_end (contract_end),
            KEY idx_users_guichet_entity (guichet_entity_id),
            KEY idx_users_team (team_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'roles' => "CREATE TABLE crminternet_roles (
            name VARCHAR(64) NOT NULL,
            label VARCHAR(120) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            color VARCHAR(32) NOT NULL DEFAULT 'primary',
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 100,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'role_permissions' => "CREATE TABLE crminternet_role_permissions (
            role VARCHAR(64) NOT NULL,
            permission VARCHAR(80) NOT NULL,
            enabled TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (role, permission)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'prospect_types' => "CREATE TABLE crminternet_prospect_types (
            id VARCHAR(40) NOT NULL,
            name VARCHAR(120) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT '',
            color VARCHAR(32) NOT NULL DEFAULT 'primary',
            position INT NOT NULL DEFAULT 100,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY name (name),
            KEY idx_active_pos (active, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'lead_stages' => "CREATE TABLE crminternet_lead_stages (
            id VARCHAR(40) NOT NULL,
            name VARCHAR(80) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT 'muted',
            position INT NOT NULL DEFAULT 0,
            is_initial TINYINT(1) NOT NULL DEFAULT 0,
            is_won TINYINT(1) NOT NULL DEFAULT 0,
            is_lost TINYINT(1) NOT NULL DEFAULT 0,
            auto_action ENUM('none','convert_opportunity','convert_contract') NOT NULL DEFAULT 'none',
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'opportunity_stages' => "CREATE TABLE crminternet_opportunity_stages (
            id VARCHAR(40) NOT NULL,
            name VARCHAR(80) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT 'muted',
            position INT NOT NULL DEFAULT 0,
            is_won TINYINT(1) NOT NULL DEFAULT 0,
            is_lost TINYINT(1) NOT NULL DEFAULT 0,
            is_initial TINYINT(1) NOT NULL DEFAULT 0,
            auto_action ENUM('none','convert_contract','revert_lead') NOT NULL DEFAULT 'none',
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'contract_stages' => "CREATE TABLE crminternet_contract_stages (
            id VARCHAR(40) NOT NULL,
            name VARCHAR(80) NOT NULL,
            color VARCHAR(20) NOT NULL DEFAULT 'muted',
            position INT NOT NULL DEFAULT 0,
            is_initial TINYINT(1) NOT NULL DEFAULT 0,
            is_won TINYINT(1) NOT NULL DEFAULT 0,
            is_lost TINYINT(1) NOT NULL DEFAULT 0,
            auto_action ENUM('none','revert_opportunity') NOT NULL DEFAULT 'none',
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'prospects' => "CREATE TABLE crminternet_prospects (
            id VARCHAR(40) NOT NULL,
            civility ENUM('M','Mme') NOT NULL DEFAULT 'M',
            last_name VARCHAR(120) NOT NULL,
            first_name VARCHAR(120) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            phone2 VARCHAR(40) NOT NULL DEFAULT '',
            ancien_ligne VARCHAR(40) DEFAULT NULL,
            animateur VARCHAR(120) DEFAULT NULL,
            cin VARCHAR(40) DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            email VARCHAR(160) NOT NULL DEFAULT '',
            source VARCHAR(80) NOT NULL DEFAULT 'Terrain',
            status VARCHAR(80) NOT NULL DEFAULT 'Nouveau',
            stage VARCHAR(80) DEFAULT NULL,
            assigned_to VARCHAR(80) DEFAULT NULL,
            created_at DATE NOT NULL,
            city VARCHAR(120) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            zone VARCHAR(120) NOT NULL DEFAULT '',
            outcome ENUM('pending','won','lost') NOT NULL DEFAULT 'pending',
            lost_reason VARCHAR(255) DEFAULT NULL,
            comment TEXT,
            comment2 TEXT,
            check_valeur ENUM('valid','invalid','pending') NOT NULL DEFAULT 'pending',
            converted TINYINT(1) NOT NULL DEFAULT 0,
            converted_at DATETIME DEFAULT NULL,
            opportunity_id VARCHAR(40) DEFAULT NULL,
            type_id VARCHAR(40) DEFAULT NULL,
            gouvernorat VARCHAR(120) NOT NULL DEFAULT '',
            delegation VARCHAR(120) NOT NULL DEFAULT '',
            code_postal VARCHAR(16) DEFAULT NULL,
            localisation_xy VARCHAR(64) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            reverted_at DATETIME DEFAULT NULL,
            reverted_from VARCHAR(20) DEFAULT NULL,
            updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_assigned (assigned_to),
            KEY idx_status (status),
            KEY idx_outcome (outcome),
            KEY idx_created (created_at),
            KEY ix_prospect_cin (cin),
            KEY idx_deleted (deleted_at),
            KEY idx_prospects_reverted_at (reverted_at),
            KEY idx_prospects_ancien_ligne (ancien_ligne),
            KEY idx_prospects_animateur (animateur),
            KEY idx_status_created (status, created_at),
            KEY idx_assigned_created (assigned_to, created_at),
            KEY idx_converted_created (converted, created_at),
            KEY idx_cin (cin),
            KEY idx_phone (phone),
            KEY idx_phone2 (phone2),
            KEY idx_type_id (type_id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'opportunities' => "CREATE TABLE crminternet_opportunities (
            id VARCHAR(40) NOT NULL,
            prospect_id VARCHAR(40) DEFAULT NULL,
            civility ENUM('M','Mme') NOT NULL DEFAULT 'M',
            last_name VARCHAR(120) NOT NULL,
            first_name VARCHAR(120) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            email VARCHAR(160) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            source VARCHAR(80) NOT NULL DEFAULT '',
            title VARCHAR(200) NOT NULL DEFAULT '',
            stage VARCHAR(80) NOT NULL DEFAULT 'Qualification',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            probability TINYINT NOT NULL DEFAULT 50,
            expected_close_date DATE DEFAULT NULL,
            assigned_to VARCHAR(80) DEFAULT NULL,
            notes TEXT,
            created_at DATETIME NOT NULL,
            created_by VARCHAR(80) DEFAULT NULL,
            converted_to_contract TINYINT(1) NOT NULL DEFAULT 0,
            contract_id VARCHAR(40) DEFAULT NULL,
            converted_at DATETIME DEFAULT NULL,
            reverted_at DATETIME DEFAULT NULL,
            type_id VARCHAR(40) DEFAULT NULL,
            phone2 VARCHAR(40) DEFAULT '',
            animateur VARCHAR(120) DEFAULT NULL,
            ancien_ligne VARCHAR(40) DEFAULT NULL,
            cin VARCHAR(40) DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            address VARCHAR(255) NOT NULL DEFAULT '',
            gouvernorat VARCHAR(120) NOT NULL DEFAULT '',
            delegation VARCHAR(120) NOT NULL DEFAULT '',
            zone VARCHAR(120) NOT NULL DEFAULT '',
            comment1 TEXT,
            comment2 TEXT,
            code_postal VARCHAR(16) DEFAULT NULL,
            localisation_xy VARCHAR(64) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            lost_reason VARCHAR(255) DEFAULT NULL,
            lead_status VARCHAR(80) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_prospect (prospect_id),
            KEY idx_stage (stage),
            KEY idx_assigned (assigned_to),
            KEY idx_contract (contract_id),
            KEY idx_converted_contract (converted_to_contract),
            KEY ix_opp_cin (cin),
            KEY idx_deleted (deleted_at),
            KEY idx_opp_animateur (animateur),
            KEY idx_opp_ancien_ligne (ancien_ligne),
            KEY idx_assigned_created (assigned_to, created_at),
            KEY idx_cin (cin),
            KEY idx_phone (phone),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'contracts' => "CREATE TABLE crminternet_contracts (
            id VARCHAR(40) NOT NULL,
            last_name VARCHAR(120) NOT NULL,
            first_name VARCHAR(120) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            partner VARCHAR(80) NOT NULL DEFAULT 'NEOLIANE',
            cabinet VARCHAR(120) NOT NULL DEFAULT 'Cabinet Paris 1',
            signature_date DATE NOT NULL,
            effective_date DATE NOT NULL,
            validation_date DATE DEFAULT NULL,
            premium DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            billing_status VARCHAR(80) NOT NULL DEFAULT 'Pré-validé',
            source VARCHAR(80) NOT NULL DEFAULT 'Web',
            assigned_to VARCHAR(80) NOT NULL DEFAULT '',
            stage_id VARCHAR(40) DEFAULT NULL,
            opportunity_id VARCHAR(40) DEFAULT NULL,
            prospect_id VARCHAR(40) DEFAULT NULL,
            type_id VARCHAR(40) DEFAULT NULL,
            civility ENUM('M','Mme') NOT NULL DEFAULT 'M',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            phone2 VARCHAR(40) DEFAULT '',
            cin VARCHAR(40) DEFAULT NULL,
            birth_date DATE DEFAULT NULL,
            email VARCHAR(160) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            gouvernorat VARCHAR(120) NOT NULL DEFAULT '',
            delegation VARCHAR(120) NOT NULL DEFAULT '',
            comment1 TEXT,
            comment2 TEXT,
            code_postal VARCHAR(16) DEFAULT NULL,
            localisation_xy VARCHAR(64) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            animateur VARCHAR(120) DEFAULT NULL,
            ancien_ligne VARCHAR(40) DEFAULT NULL,
            zone VARCHAR(120) NOT NULL DEFAULT '',
            lead_status VARCHAR(80) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_assigned (assigned_to),
            KEY idx_signdate (signature_date),
            KEY idx_billing (billing_status),
            KEY ix_contract_cin (cin),
            KEY idx_deleted (deleted_at),
            KEY idx_contract_prospect (prospect_id),
            KEY idx_contract_animateur (animateur),
            KEY idx_billing_signature (billing_status, signature_date),
            KEY idx_assigned_signature (assigned_to, signature_date),
            KEY idx_opportunity (opportunity_id),
            KEY idx_prospect (prospect_id),
            KEY idx_cin (cin),
            KEY idx_phone (phone),
            KEY idx_signature_date (signature_date),
            KEY idx_stage_id (stage_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'lead_actions' => "CREATE TABLE crminternet_lead_actions (
            id VARCHAR(40) NOT NULL,
            prospect_id VARCHAR(40) NOT NULL,
            agent_username VARCHAR(80) NOT NULL,
            type ENUM('appel','visite','relance','note','terrain','reseaux','technicien') NOT NULL DEFAULT 'note',
            comment TEXT,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_prospect (prospect_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'pipeline_transitions' => "CREATE TABLE crminternet_pipeline_transitions (
            id VARCHAR(40) NOT NULL,
            pipeline ENUM('lead','opportunity','contract') NOT NULL,
            from_stage_id VARCHAR(40) NOT NULL,
            to_stage_id VARCHAR(40) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_transition (pipeline, from_stage_id, to_stage_id),
            KEY idx_pipeline (pipeline)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'contract_info' => "CREATE TABLE crminternet_contract_info (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            entity_type ENUM('prospect','opportunity','contract') NOT NULL,
            entity_id VARCHAR(40) NOT NULL,
            type_conn VARCHAR(255) NOT NULL DEFAULT '',
            reference_tt VARCHAR(120) NOT NULL DEFAULT '',
            tel_ligne VARCHAR(60) NOT NULL DEFAULT '',
            date_activation DATE DEFAULT NULL,
            etape VARCHAR(60) NOT NULL DEFAULT '',
            interface_type VARCHAR(255) NOT NULL DEFAULT '',
            fsi VARCHAR(60) NOT NULL DEFAULT '',
            motif_retour_tt VARCHAR(255) NOT NULL DEFAULT '',
            etat ENUM('','En cours','Basculement','Rejete','Valide') NOT NULL DEFAULT '',
            remarque TEXT,
            created_at DATETIME NOT NULL,
            created_by VARCHAR(64) DEFAULT NULL,
            updated_at DATETIME NOT NULL,
            updated_by VARCHAR(64) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ux_entity (entity_type, entity_id),
            KEY idx_entity_id (entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'custom_fields' => "CREATE TABLE crminternet_custom_fields (
            id VARCHAR(40) NOT NULL,
            entity VARCHAR(20) NOT NULL,
            field_key VARCHAR(80) NOT NULL,
            label VARCHAR(160) NOT NULL,
            type VARCHAR(20) NOT NULL DEFAULT 'text',
            options TEXT,
            required TINYINT(1) NOT NULL DEFAULT 0,
            position INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            type_id VARCHAR(40) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_entity_key (entity, field_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'custom_field_values' => "CREATE TABLE crminternet_custom_field_values (
            id BIGINT NOT NULL AUTO_INCREMENT,
            entity VARCHAR(20) NOT NULL,
            entity_id VARCHAR(40) NOT NULL,
            field_key VARCHAR(80) NOT NULL,
            value TEXT,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_entity_field (entity, entity_id, field_key),
            KEY idx_entity (entity, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'attachments' => "CREATE TABLE crminternet_attachments (
            id VARCHAR(40) NOT NULL,
            entity VARCHAR(20) NOT NULL,
            entity_id VARCHAR(40) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(120) NOT NULL DEFAULT 'application/octet-stream',
            size_bytes BIGINT NOT NULL DEFAULT 0,
            storage_path VARCHAR(500) NOT NULL,
            uploaded_by VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL,
            sha256 CHAR(64) DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_entity (entity, entity_id),
            KEY idx_sha (sha256)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'activity_log' => "CREATE TABLE crminternet_activity_log (
            id VARCHAR(40) NOT NULL,
            entity_type VARCHAR(32) NOT NULL,
            entity_id VARCHAR(40) NOT NULL,
            contract_id VARCHAR(40) NOT NULL DEFAULT '',
            field VARCHAR(40) NOT NULL,
            previous_value VARCHAR(255) NOT NULL,
            new_value VARCHAR(255) NOT NULL,
            user_username VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_contract (contract_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'audit_log' => "CREATE TABLE crminternet_audit_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL,
            user_username VARCHAR(80) DEFAULT NULL,
            user_role VARCHAR(64) DEFAULT NULL,
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(40) DEFAULT NULL,
            entity_id VARCHAR(80) DEFAULT NULL,
            method VARCHAR(8) DEFAULT NULL,
            path VARCHAR(255) DEFAULT NULL,
            ip VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            status_code SMALLINT DEFAULT NULL,
            details TEXT,
            PRIMARY KEY (id),
            KEY idx_user (user_username, created_at),
            KEY idx_action (action, created_at),
            KEY idx_entity (entity_type, entity_id),
            KEY idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'attendance' => "CREATE TABLE crminternet_attendance (
            id BIGINT NOT NULL AUTO_INCREMENT,
            user_id VARCHAR(40) NOT NULL,
            username VARCHAR(80) NOT NULL,
            login_at DATETIME NOT NULL,
            logout_at DATETIME DEFAULT NULL,
            total_minutes INT NOT NULL DEFAULT 0,
            ip VARCHAR(64) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_user_date (user_id, login_at),
            KEY idx_username (username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'calendar_events' => "CREATE TABLE crminternet_calendar_events (
            id VARCHAR(40) NOT NULL,
            title VARCHAR(160) NOT NULL,
            date DATE NOT NULL,
            time VARCHAR(8) NOT NULL,
            type ENUM('rdv','rappel','signature') NOT NULL DEFAULT 'rdv',
            agent VARCHAR(80) NOT NULL,
            PRIMARY KEY (id),
            KEY idx_date (date),
            KEY idx_agent (agent)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'tasks' => "CREATE TABLE crminternet_tasks (
            id VARCHAR(40) NOT NULL,
            title VARCHAR(200) NOT NULL,
            description TEXT,
            assigned_to VARCHAR(80) NOT NULL,
            related_entity VARCHAR(20) DEFAULT NULL,
            related_id VARCHAR(40) DEFAULT NULL,
            due_date DATE DEFAULT NULL,
            priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
            status ENUM('todo','in_progress','done','cancelled') NOT NULL DEFAULT 'todo',
            created_by VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_assigned (assigned_to, status),
            KEY idx_due (due_date),
            KEY idx_tasks_assigned (assigned_to),
            KEY idx_tasks_created (created_by),
            KEY idx_tasks_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'notifications' => "CREATE TABLE crminternet_notifications (
            id VARCHAR(40) NOT NULL,
            user_username VARCHAR(80) NOT NULL,
            title VARCHAR(200) NOT NULL,
            body TEXT,
            link VARCHAR(500) DEFAULT NULL,
            read_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user_read (user_username, read_at),
            KEY idx_created (created_at),
            KEY idx_user_created (user_username, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'login_otp' => "CREATE TABLE crminternet_login_otp (
            challenge VARCHAR(40) NOT NULL,
            user_id VARCHAR(40) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts TINYINT NOT NULL DEFAULT 0,
            used TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (challenge),
            KEY idx_user (user_id),
            KEY idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'external_agents' => "CREATE TABLE crminternet_external_agents (
            id VARCHAR(40) NOT NULL,
            full_name VARCHAR(160) NOT NULL,
            phone VARCHAR(40) NOT NULL DEFAULT '',
            email VARCHAR(160) NOT NULL DEFAULT '',
            cin VARCHAR(40) NOT NULL DEFAULT '',
            commission_rate DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            fixed_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            notes TEXT,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'commissions' => "CREATE TABLE crminternet_commissions (
            id VARCHAR(40) NOT NULL,
            external_agent_id VARCHAR(40) NOT NULL,
            prospect_id VARCHAR(40) DEFAULT NULL,
            contract_id VARCHAR(40) DEFAULT NULL,
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            basis DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
            earned_at DATE NOT NULL,
            paid_at DATETIME DEFAULT NULL,
            paid_by VARCHAR(80) DEFAULT NULL,
            payment_ref VARCHAR(120) DEFAULT NULL,
            notes TEXT,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_agent (external_agent_id),
            KEY idx_status (status),
            KEY idx_earned (earned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'payroll' => "CREATE TABLE crminternet_payroll (
            id VARCHAR(40) NOT NULL,
            user_id VARCHAR(40) NOT NULL,
            username VARCHAR(80) NOT NULL,
            period CHAR(7) NOT NULL,
            base_salary DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            hours_worked DECIMAL(7,2) NOT NULL DEFAULT 0.00,
            hourly_rate DECIMAL(8,2) NOT NULL DEFAULT 0.00,
            bonus DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            deductions DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('draft','validated','paid') NOT NULL DEFAULT 'draft',
            paid_at DATETIME DEFAULT NULL,
            notes TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_user_period (user_id, period),
            KEY idx_period (period),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'chat_conversations' => "CREATE TABLE crminternet_chat_conversations (
            id VARCHAR(40) NOT NULL,
            type ENUM('dm','group','broadcast') NOT NULL DEFAULT 'group',
            name VARCHAR(160) DEFAULT NULL,
            created_by VARCHAR(80) DEFAULT NULL,
            post_policy ENUM('all','admins') NOT NULL DEFAULT 'all',
            created_at DATETIME NOT NULL,
            last_message_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_type (type),
            KEY idx_lastmsg (last_message_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'chat_members' => "CREATE TABLE crminternet_chat_members (
            conversation_id VARCHAR(40) NOT NULL,
            user_username VARCHAR(80) NOT NULL,
            role ENUM('admin','member') NOT NULL DEFAULT 'member',
            joined_at DATETIME NOT NULL,
            last_read_at DATETIME DEFAULT NULL,
            muted TINYINT(1) NOT NULL DEFAULT 0,
            hidden TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (conversation_id, user_username),
            KEY idx_user (user_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'chat_messages' => "CREATE TABLE crminternet_chat_messages (
            id VARCHAR(40) NOT NULL,
            conversation_id VARCHAR(40) NOT NULL,
            sender_username VARCHAR(80) DEFAULT NULL,
            body TEXT NOT NULL,
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            attachment_id VARCHAR(40) DEFAULT NULL,
            attachment_filename VARCHAR(255) DEFAULT NULL,
            attachment_mime VARCHAR(120) DEFAULT NULL,
            attachment_size INT DEFAULT NULL,
            created_at DATETIME(3) NOT NULL,
            edited_at DATETIME DEFAULT NULL,
            deleted_at DATETIME DEFAULT NULL,
            reply_to_id VARCHAR(40) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_conv_created (conversation_id, created_at),
            KEY idx_created (created_at),
            KEY idx_reply (reply_to_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'chat_message_reads' => "CREATE TABLE crminternet_chat_message_reads (
            message_id VARCHAR(40) NOT NULL,
            user_username VARCHAR(80) NOT NULL,
            read_at DATETIME(3) NOT NULL,
            PRIMARY KEY (message_id, user_username),
            KEY idx_msg (message_id),
            KEY idx_user (user_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'filter_presets' => "CREATE TABLE crminternet_filter_presets (
            id VARCHAR(40) NOT NULL,
            scope ENUM('prospects','opportunities','contracts') NOT NULL,
            name VARCHAR(120) NOT NULL,
            description VARCHAR(255) DEFAULT NULL,
            filters_json LONGTEXT NOT NULL,
            is_shared TINYINT(1) NOT NULL DEFAULT 1,
            is_default TINYINT(1) NOT NULL DEFAULT 0,
            default_role VARCHAR(60) DEFAULT NULL,
            position INT NOT NULL DEFAULT 0,
            created_by VARCHAR(80) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_scope (scope),
            KEY idx_default (scope, is_default),
            KEY idx_role_default (scope, default_role, is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'filter_preset_user_choice' => "CREATE TABLE crminternet_filter_preset_user_choice (
            username VARCHAR(80) NOT NULL,
            scope ENUM('prospects','opportunities','contracts') NOT NULL,
            preset_id VARCHAR(40) NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (username, scope),
            KEY idx_preset (preset_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'guichet_entities' => "CREATE TABLE crminternet_guichet_entities (
            id VARCHAR(40) NOT NULL,
            name VARCHAR(120) NOT NULL,
            type ENUM('ttshop','franchise','autre') NOT NULL DEFAULT 'ttshop',
            city VARCHAR(120) DEFAULT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'guichet_dossiers' => "CREATE TABLE crminternet_guichet_dossiers (
            id VARCHAR(40) NOT NULL,
            ref VARCHAR(20) NOT NULL,
            entity_id VARCHAR(40) NOT NULL,
            agent_id VARCHAR(40) NOT NULL,
            client_name VARCHAR(160) DEFAULT NULL,
            client_cin VARCHAR(20) DEFAULT NULL,
            status ENUM('draft','valide') NOT NULL DEFAULT 'draft',
            validated_at DATETIME DEFAULT NULL,
            validated_by VARCHAR(40) DEFAULT NULL,
            notes TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY ref (ref),
            KEY idx_gd_entity (entity_id),
            KEY idx_gd_agent (agent_id),
            KEY idx_gd_status_date (status, created_at),
            KEY idx_gd_cin (client_cin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'guichet_entries' => "CREATE TABLE crminternet_guichet_entries (
            id VARCHAR(40) NOT NULL,
            dossier_id VARCHAR(40) NOT NULL,
            type ENUM('sim','port','swp','divers','facture_tt','facture_topnet') NOT NULL,
            cin VARCHAR(20) DEFAULT NULL,
            numero VARCHAR(40) DEFAULT NULL,
            amount DECIMAL(12,3) DEFAULT NULL,
            offre VARCHAR(60) DEFAULT NULL,
            operator_source VARCHAR(60) DEFAULT NULL,
            label VARCHAR(160) DEFAULT NULL,
            op_date DATE DEFAULT NULL,
            status ENUM('draft','valide') NOT NULL DEFAULT 'draft',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_ge_type_status (type, status),
            KEY idx_ge_dossier (dossier_id),
            KEY idx_ge_offre (offre),
            KEY idx_ge_op_date (op_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'guichet_objectives' => "CREATE TABLE crminternet_guichet_objectives (
            id VARCHAR(40) NOT NULL,
            scope ENUM('agent','entity','global') NOT NULL DEFAULT 'agent',
            agent_id VARCHAR(40) DEFAULT NULL,
            entity_id VARCHAR(40) DEFAULT NULL,
            period_month CHAR(7) NOT NULL,
            target_sim INT NOT NULL DEFAULT 900,
            target_port INT NOT NULL DEFAULT 90,
            target_fancy INT NOT NULL DEFAULT 90,
            target_contracts_daily INT NOT NULL DEFAULT 25,
            target_contracts_monthly INT NOT NULL DEFAULT 650,
            working_days INT NOT NULL DEFAULT 26,
            budget_monthly_dt DECIMAL(10,2) DEFAULT NULL,
            budget_daily_dt DECIMAL(10,2) DEFAULT NULL,
            min_activation_pct DECIMAL(5,2) NOT NULL DEFAULT 25.00,
            challenge_bonus_dt DECIMAL(8,2) DEFAULT NULL,
            notes TEXT,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_scope_period (scope, agent_id, entity_id, period_month),
            KEY idx_period (period_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'idle_timeouts' => "CREATE TABLE crminternet_idle_timeouts (
            role VARCHAR(64) NOT NULL,
            timeout_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
            updated_at DATETIME NOT NULL,
            updated_by VARCHAR(64) DEFAULT NULL,
            PRIMARY KEY (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'reclamations' => "CREATE TABLE crminternet_reclamations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            reference VARCHAR(32) NOT NULL,
            tel_adsl VARCHAR(32) DEFAULT NULL,
            ref_demand VARCHAR(64) DEFAULT NULL,
            cin_client VARCHAR(32) DEFAULT NULL,
            gsm_client VARCHAR(32) DEFAULT NULL,
            client_name VARCHAR(160) DEFAULT NULL,
            service ENUM('Technique','Facturation','Commercial','Autre') NOT NULL DEFAULT 'Technique',
            description TEXT,
            statut_crm VARCHAR(80) DEFAULT NULL,
            statut_tt VARCHAR(80) DEFAULT NULL,
            audit_status ENUM('en_cours','resolu','annule') NOT NULL DEFAULT 'en_cours',
            localisation VARCHAR(160) DEFAULT NULL,
            etat VARCHAR(80) DEFAULT NULL,
            remarques TEXT,
            date_creation DATETIME NOT NULL,
            date_resolution DATETIME DEFAULT NULL,
            mois TINYINT UNSIGNED NULL,
            annee SMALLINT UNSIGNED NULL,
            assigned_to VARCHAR(80) DEFAULT NULL,
            created_by VARCHAR(80) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            subject VARCHAR(255) DEFAULT NULL,
            status VARCHAR(32) NOT NULL DEFAULT 'open',
            priority VARCHAR(16) NOT NULL DEFAULT 'normal',
            PRIMARY KEY (id),
            UNIQUE KEY uniq_rec_reference (reference),
            KEY idx_rec_audit (audit_status),
            KEY idx_rec_service (service),
            KEY idx_rec_tel (tel_adsl),
            KEY idx_rec_cin (cin_client),
            KEY idx_rec_gsm (gsm_client),
            KEY idx_rec_assigned (assigned_to),
            KEY idx_rec_period (annee, mois),
            KEY idx_rec_created (date_creation)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'reclamation_counter' => "CREATE TABLE crminternet_reclamation_counter (
            period CHAR(6) NOT NULL,
            last_seq INT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'schema_migrations' => "CREATE TABLE crminternet_schema_migrations (
            filename VARCHAR(160) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (filename)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'settings' => "CREATE TABLE crminternet_settings (
            scope VARCHAR(80) NOT NULL DEFAULT 'global',
            setting_key VARCHAR(120) NOT NULL,
            value LONGTEXT NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (scope, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'teams' => "CREATE TABLE crminternet_teams (
            id VARCHAR(40) NOT NULL,
            name VARCHAR(120) NOT NULL,
            description TEXT,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_team_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'team_roles' => "CREATE TABLE crminternet_team_roles (
            team_id VARCHAR(40) NOT NULL,
            role VARCHAR(80) NOT NULL,
            PRIMARY KEY (team_id, role),
            KEY idx_team_roles_team (team_id),
            KEY idx_team_roles_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'user_grants' => "CREATE TABLE crminternet_user_grants (
            id VARCHAR(40) NOT NULL,
            user_username VARCHAR(80) NOT NULL,
            grant_type ENUM('role','permission') NOT NULL,
            grant_value VARCHAR(120) NOT NULL,
            reason VARCHAR(255) DEFAULT NULL,
            granted_by VARCHAR(80) NOT NULL,
            starts_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            revoked TINYINT(1) NOT NULL DEFAULT 0,
            revoked_at DATETIME DEFAULT NULL,
            revoked_by VARCHAR(80) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_user (user_username),
            KEY idx_active (user_username, expires_at, revoked)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

        'user_permission_overrides' => "CREATE TABLE crminternet_user_permission_overrides (
            user_username VARCHAR(80) NOT NULL,
            permission VARCHAR(80) NOT NULL,
            effect ENUM('allow','deny') NOT NULL,
            updated_by VARCHAR(80) DEFAULT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_username, permission),
            KEY idx_user (user_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ];
}

function crm_foreign_keys_sql(): array {
    return [
        "ALTER TABLE crminternet_chat_members ADD CONSTRAINT fk_mem_conv FOREIGN KEY (conversation_id) REFERENCES crminternet_chat_conversations (id) ON DELETE CASCADE",
        "ALTER TABLE crminternet_chat_messages ADD CONSTRAINT fk_msg_conv FOREIGN KEY (conversation_id) REFERENCES crminternet_chat_conversations (id) ON DELETE CASCADE",
        "ALTER TABLE crminternet_commissions ADD CONSTRAINT fk_comm_agent FOREIGN KEY (external_agent_id) REFERENCES crminternet_external_agents (id) ON DELETE RESTRICT",
        "ALTER TABLE crminternet_contracts ADD CONSTRAINT fk_contract_opp FOREIGN KEY (opportunity_id) REFERENCES crminternet_opportunities (id) ON DELETE SET NULL",
        "ALTER TABLE crminternet_guichet_entries ADD CONSTRAINT fk_ge_dossier FOREIGN KEY (dossier_id) REFERENCES crminternet_guichet_dossiers (id) ON DELETE CASCADE",
        "ALTER TABLE crminternet_lead_actions ADD CONSTRAINT fk_la_prospect FOREIGN KEY (prospect_id) REFERENCES crminternet_prospects (id) ON DELETE CASCADE",
        "ALTER TABLE crminternet_opportunities ADD CONSTRAINT fk_opp_prospect FOREIGN KEY (prospect_id) REFERENCES crminternet_prospects (id) ON DELETE SET NULL",
    ];
}

function crm_seed_sql(): array {
    return [
        "INSERT IGNORE INTO crminternet_roles (name, label, description, color, is_system, sort_order, created_at, updated_at) VALUES
            ('Administrateur','Administrateur','Accès complet','primary',1,1,NOW(),NOW()),
            ('Manager','Superviseur','Pilotage d''équipe','info',0,2,NOW(),NOW()),
            ('Agent','Commercial','Gestion des leads','success',0,3,NOW(),NOW()),
            ('Backoffice','Backoffice','Validation contrats','warning',0,4,NOW(),NOW()),
            ('AgentSuivi','Agent Suivi','Prospection + Opportunité + Contrat','success',0,5,NOW(),NOW()),
            ('AgentActivation','Agent Activation','Prospection + Opportunité','info',0,6,NOW(),NOW()),
            ('AgentVente','Agent Vente','Vente terrain','success',0,7,NOW(),NOW()),
            ('AgentGuichet','Agent Guichet','Saisie guichet','info',0,8,NOW(),NOW())",
        "INSERT IGNORE INTO crminternet_settings (scope, setting_key, value, updated_at) VALUES
            ('global','company.name','\"Protection CRM\"',NOW()),
            ('global','company.currency','\"TND\"',NOW()),
            ('global','otp.enabled','true',NOW()),
            ('global','otp.code_length','4',NOW()),
            ('global','otp.ttl_minutes','10',NOW())",
    ];
}

/** Adaptive prospect types (setup.php vs install.sql). */
function crm_seed_prospect_types(PDO $db): array
{
    $out = [];
    $now = date('Y-m-d H:i:s');
    try {
        $cols = [];
        foreach ($db->query('SHOW COLUMNS FROM crminternet_prospect_types') as $c) {
            $cols[$c['Field']] = true;
        }
        if (isset($cols['description']) && isset($cols['color'])) {
            $db->exec("INSERT IGNORE INTO crminternet_prospect_types (id, name, description, color, position, active) VALUES
                ('PT-DEFAULT','Standard','Type par défaut pour tous les prospects','primary',1,1)");
            $out[] = ['status' => 'ok', 'sql' => 'prospect_types (install)'];
        } elseif (isset($cols['label'])) {
            $db->exec("INSERT IGNORE INTO crminternet_prospect_types
                (id, name, label, active, position, created_at) VALUES
                ('PT-DEFAULT','Standard','Type par défaut',1,1,'{$now}')");
            $out[] = ['status' => 'ok', 'sql' => 'prospect_types (setup)'];
        } else {
            $db->exec("INSERT IGNORE INTO crminternet_prospect_types (id, name) VALUES ('PT-DEFAULT','Standard')");
            $out[] = ['status' => 'ok', 'sql' => 'prospect_types (minimal)'];
        }
    } catch (Throwable $e) {
        $out[] = ['status' => 'error', 'sql' => 'prospect_types', 'message' => $e->getMessage()];
    }
    return $out;
}

/** Adaptive stage seeds (setup.php vs install.sql schemas). */
function crm_seed_pipeline_stages(PDO $db): array
{
    $out = [];
    $now = date('Y-m-d H:i:s');
    $run = function (string $label, string $sql) use ($db, &$out) {
        try {
            $db->exec($sql);
            $out[] = ['status' => 'ok', 'sql' => $label];
        } catch (Throwable $e) {
            $out[] = ['status' => 'error', 'sql' => $label, 'message' => $e->getMessage()];
        }
    };

    $leadCols = [];
    try {
        foreach ($db->query('SHOW COLUMNS FROM crminternet_lead_stages') as $c) {
            $leadCols[$c['Field']] = true;
        }
    } catch (Throwable $e) {
        return $out;
    }

    if (isset($leadCols['is_initial'])) {
        $run('lead_stages (install)', "INSERT IGNORE INTO crminternet_lead_stages (id, name, color, position, is_initial) VALUES
            ('S-1','Nouveau','info',1,1),('S-2','En cours','primary',2,0),('S-3','Rappel','warning',3,0),
            ('S-4','Refus','destructive',4,0),('S-5','Vendu','success',5,0)");
    } elseif (isset($leadCols['created_at'])) {
        $run('lead_stages (setup)', "INSERT IGNORE INTO crminternet_lead_stages
            (id,name,label,position,color,is_final,auto_action,created_at,updated_at) VALUES
            ('S-1','Nouveau','Nouveau',1,'#6366f1',0,'none','{$now}','{$now}'),
            ('S-2','En cours','En cours',2,'#2563eb',0,'none','{$now}','{$now}'),
            ('S-3','Rappel','Rappel',3,'#f59e0b',0,'none','{$now}','{$now}'),
            ('S-4','Refus','Refus',4,'#ef4444',0,'none','{$now}','{$now}'),
            ('S-5','Vendu','Vendu',5,'#22c55e',1,'convert_opportunity','{$now}','{$now}')");
    }

    $oppCols = [];
    try {
        foreach ($db->query('SHOW COLUMNS FROM crminternet_opportunity_stages') as $c) {
            $oppCols[$c['Field']] = true;
        }
        if (isset($oppCols['is_initial'])) {
            $run('opportunity_stages (install)', "INSERT IGNORE INTO crminternet_opportunity_stages
                (id,name,color,position,is_initial,is_won,is_lost) VALUES
                ('OS-1','Qualification','info',1,1,0,0),('OS-2','Proposition','primary',2,0,0,0),
                ('OS-3','Négociation','warning',3,0,0,0),('OS-4','Gagnée','success',4,0,1,0),
                ('OS-5','Perdue','destructive',5,0,0,1)");
        } elseif (isset($oppCols['created_at'])) {
            $run('opportunity_stages (setup)', "INSERT IGNORE INTO crminternet_opportunity_stages
                (id,name,label,position,color,is_final,auto_action,created_at,updated_at) VALUES
                ('OS-1','Qualification','Qualification',1,'#6366f1',0,'none','{$now}','{$now}'),
                ('OS-2','Proposition','Proposition',2,'#2563eb',0,'none','{$now}','{$now}'),
                ('OS-3','Négociation','Négociation',3,'#f59e0b',0,'none','{$now}','{$now}'),
                ('OS-4','Gagnée','Gagnée',4,'#22c55e',1,'convert_contract','{$now}','{$now}'),
                ('OS-5','Perdue','Perdue',5,'#ef4444',1,'revert_lead','{$now}','{$now}')");
        }
    } catch (Throwable $e) { /* table missing */ }

    $csCols = [];
    try {
        foreach ($db->query('SHOW COLUMNS FROM crminternet_contract_stages') as $c) {
            $csCols[$c['Field']] = true;
        }
        if (isset($csCols['is_initial'])) {
            $run('contract_stages (install)', "INSERT IGNORE INTO crminternet_contract_stages
                (id,name,color,position,is_initial) VALUES
                ('CS-1','Brouillon','muted',1,1),('CS-2','Pré-validé','warning',2,0),
                ('CS-3','Validé','success',3,0),('CS-4','Facturé','info',4,0)");
        } elseif (isset($csCols['created_at'])) {
            $run('contract_stages (setup)', "INSERT IGNORE INTO crminternet_contract_stages
                (id,name,label,position,color,is_final,auto_action,created_at,updated_at) VALUES
                ('CS-1','Brouillon','Brouillon',1,'#94a3b8',0,'none','{$now}','{$now}'),
                ('CS-2','Pré-validé','Pré-validé',2,'#f59e0b',0,'none','{$now}','{$now}'),
                ('CS-3','Validé','Validé',3,'#22c55e',1,'none','{$now}','{$now}'),
                ('CS-4','Facturé','Facturé',4,'#6366f1',1,'none','{$now}','{$now}')");
        }
    } catch (Throwable $e) { /* table missing */ }

    return $out;
}

/** Run all idempotent seed SQL statements. Safe to call multiple times. */
function crm_apply_seed_data(PDO $db): array
{
    require_once __DIR__ . '/schema_repair.php';
    ensure_settings_schema($db);
    ensure_reports_schema($db);
    ensure_login_otp_schema($db);

    $results = [];
    $statements = array_merge(crm_seed_sql(), crm_seed_permissions_sql());
    foreach ($statements as $i => $sql) {
        $label = trim(preg_replace('/\s+/', ' ', substr($sql, 0, 72)));
        try {
            $db->exec($sql);
            $results[] = ['index' => $i, 'status' => 'ok', 'sql' => $label];
        } catch (Throwable $e) {
            $results[] = ['index' => $i, 'status' => 'error', 'sql' => $label, 'message' => $e->getMessage()];
        }
    }

    foreach (crm_seed_prospect_types($db) as $row) {
        $results[] = $row;
    }

    foreach (crm_seed_pipeline_stages($db) as $row) {
        $results[] = $row;
    }

    require_once __DIR__ . '/guichet_schema.php';
    ensure_guichet_schema($db);

    require_once __DIR__ . '/crm_terminal_migration_schema.php';
    try {
        ensure_terminal_migration_schema($db);
        $results['terminal_migrations'] = terminal_migration_schema_status($db);
    } catch (Throwable $e) {
        $results['terminal_migrations'] = ['error' => $e->getMessage()];
    }

    require_once __DIR__ . '/production_seed.php';
    $prod = crm_apply_production_seed($db);
    foreach ($prod as $k => $row) {
        if ($k === 'counts') {
            continue;
        }
        if (is_array($row)) {
            $results[] = $row;
        }
    }
    if (!empty($prod['counts'])) {
        $results['production_counts'] = $prod['counts'];
    }

    // Re-apply after production matrix so migration grants are never dropped by the dump.
    try {
        crm_seed_migration_role_permissions($db);
        $results['migration_permissions_reseed'] = 'ok';
    } catch (Throwable $e) {
        $results['migration_permissions_reseed'] = $e->getMessage();
    }

    $counts = [];
    foreach (
        [
            'crminternet_roles',
            'crminternet_prospect_types',
            'crminternet_role_permissions',
            'crminternet_lead_stages',
            'crminternet_users',
        ] as $table
    ) {
        try {
            $counts[$table] = (int) $db->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        } catch (Throwable $e) {
            $counts[$table] = -1;
        }
    }
    $results['counts'] = $counts;
    $results['ok'] = !array_reduce($results, function ($bad, $r) {
        return $bad || (is_array($r) && ($r['status'] ?? '') === 'error');
    }, false);

    return $results;
}

function crm_seed_permissions_sql(): array {
    return [
        "INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled) VALUES
            ('Administrateur','page.dashboard',1),('Administrateur','page.prospects',1),
            ('Administrateur','page.opportunities',1),('Administrateur','page.contracts',1),
            ('Administrateur','page.migrations',1),
            ('Administrateur','page.calendar',1),('Administrateur','page.tasks',1),
            ('Administrateur','page.notifications',1),('Administrateur','page.dispatch',1),
            ('Administrateur','page.backoffice',1),('Administrateur','page.pipelines',1),
            ('Administrateur','page.stages',1),('Administrateur','page.reports',1),
            ('Administrateur','page.reconciliation',1),('Administrateur','page.objectives',1),
            ('Administrateur','page.profile',1),('Administrateur','page.documentation',1),
            ('Administrateur','page.configuration',1),('Administrateur','page.users',1),
            ('Administrateur','page.roles',1),('Administrateur','page.audit',1),
            ('Administrateur','page.security',1),('Administrateur','page.hr.attendance',1),
            ('Administrateur','page.hr.payroll',1),('Administrateur','page.hr.commissions',1),
            ('Administrateur','page.hr.external-agents',1),
            ('Administrateur','audit.view',1),('Administrateur','report.view',1),
            ('Administrateur','report.export',1),
            ('Manager','page.dashboard',1),('Manager','page.prospects',1),
            ('Manager','page.opportunities',1),('Manager','page.contracts',1),
            ('Manager','page.migrations',1),
            ('Manager','page.calendar',1),('Manager','page.tasks',1),
            ('Manager','page.notifications',1),('Manager','page.reports',1),
            ('Manager','page.audit',1),('Manager','page.hr.attendance',1),
            ('Manager','page.hr.payroll',1),('Manager','page.hr.commissions',1),
            ('Manager','audit.view',1),('Manager','report.view',1),('Manager','report.export',1),
            ('Agent','page.dashboard',1),('Agent','page.prospects',1),
            ('Agent','page.calendar',1),('Agent','page.tasks',1),
            ('Agent','page.notifications',1),('Agent','page.profile',1),
            ('AgentSuivi','page.dashboard',1),('AgentSuivi','page.prospects',1),
            ('AgentSuivi','page.opportunities',1),('AgentSuivi','page.contracts',1),
            ('AgentSuivi','page.migrations',1),
            ('Backoffice','page.dashboard',1),('Backoffice','page.contracts',1),
            ('Backoffice','page.migrations',1),
            ('Backoffice','page.backoffice',1)",
        "INSERT IGNORE INTO crminternet_role_permissions (role, permission, enabled) VALUES
            ('Manager','prospect.view',1),('Manager','prospect.edit',1),('Manager','prospect.type',1),('Manager','prospect.export',1),
            ('Manager','opportunity.view',1),('Manager','opportunity.convert_migration',1),
            ('Manager','contract.view',1),('Manager','migration.view',1),('Manager','migration.edit',1),
            ('Manager','migration.export',1),
            ('Manager','hr.payroll.edit',1),('Manager','hr.commissions.edit',1),
            ('Agent','prospect.view',1),('Agent','prospect.add',1),('Agent','prospect.edit',1),('Agent','prospect.type',1),
            ('Agent','hr.attendance.clock',1),
            ('AgentSuivi','prospect.view',1),('AgentSuivi','opportunity.view',1),
            ('AgentSuivi','opportunity.convert_migration',1),('AgentSuivi','contract.view',1),
            ('AgentSuivi','migration.view',1),('AgentSuivi','migration.edit',1),
            ('AgentSuivi','hr.attendance.clock',1),
            ('Backoffice','contract.view',1),('Backoffice','contract.validate',1),
            ('Backoffice','migration.view',1),('Backoffice','migration.edit',1),
            ('Backoffice','hr.attendance.clock',1)",
    ];
}
