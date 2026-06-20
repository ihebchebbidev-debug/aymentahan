<?php
/**
 * Database Setup Script
 * Creates all necessary tables and initializes the database with default admin user
 * MySQL 5.7+ compatible
 * 
 * Usage: Visit http://your-server/backend/php/setup.php in your browser
 * After successful setup, delete or disable this file for security
 */

header('Content-Type: application/json; charset=UTF-8');

// Database credentials
$host = 'localhost';
$username = 'ttshopvente';
$password = '8Jjs%1g23';
$database = 'wordpress_18';

// Admin credentials
$admin_username = 'AymenAdmin';
$admin_password = 'Admin@2026';
$admin_email = 'admin@crminternet.local';
$admin_fullname = 'Administrateur Systeme';

try {
    // Create PDO connection
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $results = [];
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ============================================================
    // DROP EXISTING TABLES IF EXISTS (FOR SAFE RE-INITIALIZATION)
    // ============================================================
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
    
    $drop_tables = [
        'DROP TABLE IF EXISTS crminternet_user_permission_overrides',
        'DROP TABLE IF EXISTS crminternet_user_grants',
        'DROP TABLE IF EXISTS crminternet_team_roles',
        'DROP TABLE IF EXISTS crminternet_teams',
        'DROP TABLE IF EXISTS crminternet_tasks',
        'DROP TABLE IF EXISTS crminternet_settings',
        'DROP TABLE IF EXISTS crminternet_schema_migrations',
        'DROP TABLE IF EXISTS crminternet_reclamation_counter',
        'DROP TABLE IF EXISTS crminternet_reclamations',
        'DROP TABLE IF EXISTS crminternet_prospect_types',
        'DROP TABLE IF EXISTS crminternet_pipeline_transitions',
        'DROP TABLE IF EXISTS crminternet_payroll',
        'DROP TABLE IF EXISTS crminternet_opportunity_stages',
        'DROP TABLE IF EXISTS crminternet_opportunities',
        'DROP TABLE IF EXISTS crminternet_notifications',
        'DROP TABLE IF EXISTS crminternet_login_otp',
        'DROP TABLE IF EXISTS crminternet_lead_stages',
        'DROP TABLE IF EXISTS crminternet_lead_actions',
        'DROP TABLE IF EXISTS crminternet_idle_timeouts',
        'DROP TABLE IF EXISTS crminternet_guichet_objectives',
        'DROP TABLE IF EXISTS crminternet_guichet_entries',
        'DROP TABLE IF EXISTS crminternet_guichet_entities',
        'DROP TABLE IF EXISTS crminternet_guichet_dossiers',
        'DROP TABLE IF EXISTS crminternet_filter_preset_user_choice',
        'DROP TABLE IF EXISTS crminternet_filter_presets',
        'DROP TABLE IF EXISTS crminternet_commissions',
        'DROP TABLE IF EXISTS crminternet_external_agents',
        'DROP TABLE IF EXISTS crminternet_contract_stages',
        'DROP TABLE IF EXISTS crminternet_contract_info',
        'DROP TABLE IF EXISTS crminternet_chat_message_reads',
        'DROP TABLE IF EXISTS crminternet_chat_members',
        'DROP TABLE IF EXISTS crminternet_chat_conversations',
        'DROP TABLE IF EXISTS crminternet_activity_log',
        'DROP TABLE IF EXISTS crminternet_audit_log',
        'DROP TABLE IF EXISTS crminternet_attendance',
        'DROP TABLE IF EXISTS crminternet_role_permissions',
        'DROP TABLE IF EXISTS crminternet_user_permissions',
        'DROP TABLE IF EXISTS crminternet_user_roles',
        'DROP TABLE IF EXISTS crminternet_permissions',
        'DROP TABLE IF EXISTS crminternet_roles',
        'DROP TABLE IF EXISTS crminternet_users',
        'DROP TABLE IF EXISTS crminternet_attachments',
        'DROP TABLE IF EXISTS crminternet_activities',
        'DROP TABLE IF EXISTS crminternet_contracts',
        'DROP TABLE IF EXISTS crminternet_prospects',
        'DROP TABLE IF EXISTS crminternet_custom_field_values',
        'DROP TABLE IF EXISTS crminternet_custom_fields',
        'DROP TABLE IF EXISTS crminternet_leads',
        'DROP TABLE IF EXISTS crminternet_notes',
        'DROP TABLE IF EXISTS crminternet_calendar_events',
        'DROP TABLE IF EXISTS crminternet_chat_messages',
        'DROP TABLE IF EXISTS user_permission_overrides',
        'DROP TABLE IF EXISTS user_grants',
        'DROP TABLE IF EXISTS team_roles',
        'DROP TABLE IF EXISTS teams',
        'DROP TABLE IF EXISTS tasks',
        'DROP TABLE IF EXISTS settings',
        'DROP TABLE IF EXISTS schema_migrations',
        'DROP TABLE IF EXISTS reclamation_counter',
        'DROP TABLE IF EXISTS reclamations',
        'DROP TABLE IF EXISTS prospect_types',
        'DROP TABLE IF EXISTS pipeline_transitions',
        'DROP TABLE IF EXISTS payroll',
        'DROP TABLE IF EXISTS opportunity_stages',
        'DROP TABLE IF EXISTS opportunities',
        'DROP TABLE IF EXISTS notifications',
        'DROP TABLE IF EXISTS login_otp',
        'DROP TABLE IF EXISTS lead_stages',
        'DROP TABLE IF EXISTS lead_actions',
        'DROP TABLE IF EXISTS idle_timeouts',
        'DROP TABLE IF EXISTS guichet_objectives',
        'DROP TABLE IF EXISTS guichet_entries',
        'DROP TABLE IF EXISTS guichet_entities',
        'DROP TABLE IF EXISTS guichet_dossiers',
        'DROP TABLE IF EXISTS filter_preset_user_choice',
        'DROP TABLE IF EXISTS filter_presets',
        'DROP TABLE IF EXISTS external_agents',
        'DROP TABLE IF EXISTS contract_stages',
        'DROP TABLE IF EXISTS contract_info',
        'DROP TABLE IF EXISTS commissions',
        'DROP TABLE IF EXISTS chat_message_reads',
        'DROP TABLE IF EXISTS chat_members',
        'DROP TABLE IF EXISTS chat_conversations',
        'DROP TABLE IF EXISTS activity_log',
        'DROP TABLE IF EXISTS audit_log',
        'DROP TABLE IF EXISTS attendance',
        'DROP TABLE IF EXISTS role_permissions',
        'DROP TABLE IF EXISTS user_permissions',
        'DROP TABLE IF EXISTS user_roles',
        'DROP TABLE IF EXISTS permissions',
        'DROP TABLE IF EXISTS roles',
        'DROP TABLE IF EXISTS users',
        'DROP TABLE IF EXISTS attachments',
        'DROP TABLE IF EXISTS activities',
        'DROP TABLE IF EXISTS contracts',
        'DROP TABLE IF EXISTS prospects',
        'DROP TABLE IF EXISTS custom_field_values',
        'DROP TABLE IF EXISTS custom_fields',
        'DROP TABLE IF EXISTS leads',
        'DROP TABLE IF EXISTS notes',
        'DROP TABLE IF EXISTS calendar_events',
        'DROP TABLE IF EXISTS chat_messages',
    ];

    foreach ($drop_tables as $drop_sql) {
        try {
            $pdo->exec($drop_sql);
            $table_name = str_replace(['DROP TABLE IF EXISTS ', '`'], '', $drop_sql);
            $results['drop_' . strtolower($table_name)] = ['status' => 'dropped', 'message' => "$table_name table dropped if existed"];
        } catch (Exception $e) {
            $results['drop_' . strtolower(str_replace(['DROP TABLE IF EXISTS ', '`'], '', $drop_sql))] = ['status' => 'skip', 'message' => $e->getMessage()];
        }
    }

    // ============================================================
    // RE-ENABLE FOREIGN KEY CHECKS
    // ============================================================
    $pdo->exec("SET FOREIGN_KEY_CHECKS=1");

    // ============================================================
    // CREATE USERS TABLE
    // ============================================================
    $create_users_sql = "
        CREATE TABLE crminternet_users (
            id VARCHAR(36) PRIMARY KEY,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(120) UNIQUE NOT NULL,
            full_name VARCHAR(255),
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(50),
            team VARCHAR(80),
            active BOOLEAN DEFAULT TRUE,
            must_change_password BOOLEAN DEFAULT FALSE,
            job_title VARCHAR(255),
            birth_date DATE,
            cin VARCHAR(50),
            company VARCHAR(255),
            contract_type VARCHAR(50),
            salary DECIMAL(12,2),
            salary_increase DECIMAL(12,2),
            contract_start DATE,
            contract_end DATE,
            renewal_start DATE,
            renewal_end DATE,
            observations TEXT,
            phone VARCHAR(40),
            rib VARCHAR(100),
            hire_date DATE,
            guichet_entity_id VARCHAR(40),
            team_id VARCHAR(40),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_username (username),
            INDEX idx_email (email),
            INDEX idx_active (active),
            INDEX idx_team (team),
            INDEX idx_guichet_entity (guichet_entity_id),
            INDEX idx_team_id (team_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_users_sql);
        $results['users_table'] = ['status' => 'created', 'message' => 'Users table created successfully'];
    } catch (Exception $e) {
        $results['users_table'] = ['status' => 'error', 'message' => $e->getMessage()];
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create users table',
            'message' => $e->getMessage(),
            'setup_results' => $results
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ============================================================
    // CREATE ROLES TABLE
    // ============================================================
    $create_roles_sql = "
        CREATE TABLE crminternet_roles (
            name VARCHAR(100) PRIMARY KEY,
            label VARCHAR(255) NOT NULL,
            description TEXT,
            color VARCHAR(7) DEFAULT '#000000',
            is_system BOOLEAN DEFAULT FALSE,
            sort_order INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_sort_order (sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_roles_sql);
        $results['roles_table'] = ['status' => 'created', 'message' => 'Roles table created successfully'];
    } catch (Exception $e) {
        $results['roles_table'] = ['status' => 'error', 'message' => $e->getMessage()];
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create roles table',
            'message' => $e->getMessage(),
            'setup_results' => $results
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ============================================================
    // CREATE PERMISSIONS TABLE
    // ============================================================
    $create_permissions_sql = "
        CREATE TABLE crminternet_permissions (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(100) UNIQUE NOT NULL,
            description TEXT,
            resource VARCHAR(100),
            action VARCHAR(50),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_name (name),
            INDEX idx_resource (resource),
            INDEX idx_action (action)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_permissions_sql);
        $results['permissions_table'] = ['status' => 'created', 'message' => 'Permissions table created successfully'];
    } catch (Exception $e) {
        $results['permissions_table'] = ['status' => 'error', 'message' => $e->getMessage()];
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create permissions table',
            'message' => $e->getMessage(),
            'setup_results' => $results
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ============================================================
    // CREATE USER ROLES JUNCTION TABLE
    // ============================================================
    $create_user_roles_sql = "
        CREATE TABLE crminternet_user_roles (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            role VARCHAR(100) NOT NULL,
            assigned_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES crminternet_users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_role (user_id, role),
            INDEX idx_user_id (user_id),
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_user_roles_sql);
        $results['user_roles_table'] = ['status' => 'created', 'message' => 'User roles junction table created successfully'];
    } catch (Exception $e) {
        $results['user_roles_table'] = ['status' => 'error', 'message' => $e->getMessage()];
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create user_roles table',
            'message' => $e->getMessage(),
            'setup_results' => $results
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ============================================================
    // CREATE ROLE PERMISSIONS JUNCTION TABLE
    // ============================================================
    $create_role_permissions_sql = "
        CREATE TABLE crminternet_role_permissions (
            role VARCHAR(64) NOT NULL,
            permission VARCHAR(100) NOT NULL,
            enabled BOOLEAN DEFAULT TRUE,
            PRIMARY KEY (role, permission),
            INDEX idx_role (role),
            INDEX idx_permission (permission)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_role_permissions_sql);
        $results['role_permissions_table'] = ['status' => 'created', 'message' => 'Role permissions junction table created successfully'];
    } catch (Exception $e) {
        $results['role_permissions_table'] = ['status' => 'error', 'message' => $e->getMessage()];
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create role_permissions table',
            'message' => $e->getMessage(),
            'setup_results' => $results
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ============================================================
    // CREATE USER PERMISSIONS JUNCTION TABLE
    // ============================================================
    $create_user_permissions_sql = "
        CREATE TABLE crminternet_user_permissions (
            id VARCHAR(36) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            permission_id VARCHAR(36) NOT NULL,
            assigned_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES crminternet_users(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES crminternet_permissions(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_permission (user_id, permission_id),
            INDEX idx_user_id (user_id),
            INDEX idx_permission_id (permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_user_permissions_sql);
        $results['user_permissions_table'] = ['status' => 'created', 'message' => 'User permissions junction table created successfully'];
    } catch (Exception $e) {
        $results['user_permissions_table'] = ['status' => 'error', 'message' => $e->getMessage()];
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create user_permissions table',
            'message' => $e->getMessage(),
            'setup_results' => $results
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ============================================================
    // CREATE PROSPECTS TABLE
    // ============================================================
    $create_prospects_sql = "
        CREATE TABLE crminternet_prospects (
            id VARCHAR(36) PRIMARY KEY,
            nom VARCHAR(255) NOT NULL,
            email VARCHAR(120),
            phone VARCHAR(40),
            cin VARCHAR(50),
            source VARCHAR(100),
            status VARCHAR(50) DEFAULT 'nouveau',
            agent_id VARCHAR(36),
            notes TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (agent_id) REFERENCES crminternet_users(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_agent (agent_id),
            INDEX idx_email (email),
            INDEX idx_cin (cin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_prospects_sql);
        $results['prospects_table'] = ['status' => 'created', 'message' => 'Prospects table created successfully'];
    } catch (Exception $e) {
        $results['prospects_table'] = ['status' => 'error', 'message' => $e->getMessage()];
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to create prospects table',
            'message' => $e->getMessage(),
            'setup_results' => $results
        ], JSON_PRETTY_PRINT);
        exit;
    }

    // ============================================================
    // CREATE CONTRACTS TABLE
    // ============================================================
    $create_contracts_sql = "
        CREATE TABLE crminternet_contracts (
            id VARCHAR(40) PRIMARY KEY,
            reference VARCHAR(100) UNIQUE NOT NULL,
            prospect_id VARCHAR(40),
            civility ENUM('M','Mme') NOT NULL DEFAULT 'M',
            last_name VARCHAR(120) NOT NULL,
            first_name VARCHAR(120) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            phone2 VARCHAR(40) NOT NULL DEFAULT '',
            cin VARCHAR(40),
            birth_date DATE,
            email VARCHAR(160) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            gouvernorat VARCHAR(120) NOT NULL DEFAULT '',
            delegation VARCHAR(120) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            localisation_xy VARCHAR(64),
            code_postal VARCHAR(20),
            status VARCHAR(50) DEFAULT 'brouillon',
            type VARCHAR(50),
            amount DECIMAL(15,2),
            currency VARCHAR(3) DEFAULT 'TND',
            start_date DATE,
            end_date DATE,
            owner_id VARCHAR(40),
            notes TEXT,
            comment1 TEXT,
            comment2 TEXT,
            stage_id VARCHAR(40),
            opportunity_id VARCHAR(40),
            type_id VARCHAR(40),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (prospect_id) REFERENCES crminternet_prospects(id) ON DELETE SET NULL,
            FOREIGN KEY (owner_id) REFERENCES crminternet_users(id) ON DELETE SET NULL,
            INDEX idx_reference (reference),
            INDEX idx_status (status),
            INDEX idx_prospect (prospect_id),
            INDEX idx_owner (owner_id),
            INDEX idx_opportunity (opportunity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_contracts_sql);
        $results['contracts_table'] = ['status' => 'created', 'message' => 'Contracts table created successfully'];
    } catch (Exception $e) {
        $results['contracts_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE ACTIVITIES TABLE
    // ============================================================
    $create_activities_sql = "
        CREATE TABLE crminternet_activities (
            id VARCHAR(36) PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            entity_type VARCHAR(50),
            entity_id VARCHAR(36),
            user_id VARCHAR(36),
            description TEXT,
            status VARCHAR(50),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES crminternet_users(id) ON DELETE SET NULL,
            INDEX idx_type (type),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_activities_sql);
        $results['activities_table'] = ['status' => 'created', 'message' => 'Activities table created successfully'];
    } catch (Exception $e) {
        $results['activities_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE ATTACHMENTS TABLE
    // ============================================================
    $create_attachments_sql = "
        CREATE TABLE crminternet_attachments (
            id VARCHAR(36) PRIMARY KEY,
            entity_type VARCHAR(50),
            entity_id VARCHAR(36),
            file_name VARCHAR(255),
            file_path TEXT,
            file_size INT,
            mime_type VARCHAR(100),
            uploaded_by VARCHAR(36),
            created_at DATETIME NOT NULL,
            FOREIGN KEY (uploaded_by) REFERENCES crminternet_users(id) ON DELETE SET NULL,
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_uploaded_by (uploaded_by),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_attachments_sql);
        $results['attachments_table'] = ['status' => 'created', 'message' => 'Attachments table created successfully'];
    } catch (Exception $e) {
        $results['attachments_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE CUSTOM FIELDS TABLE
    // ============================================================
    $create_custom_fields_sql = "
        CREATE TABLE crminternet_custom_fields (
            id VARCHAR(36) PRIMARY KEY,
            entity_type VARCHAR(50),
            field_name VARCHAR(100),
            field_label VARCHAR(255),
            field_type VARCHAR(50),
            required BOOLEAN DEFAULT FALSE,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_entity_type (entity_type),
            INDEX idx_field_name (field_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_custom_fields_sql);
        $results['custom_fields_table'] = ['status' => 'created', 'message' => 'Custom fields table created successfully'];
    } catch (Exception $e) {
        $results['custom_fields_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE CUSTOM FIELD VALUES TABLE
    // ============================================================
    $create_custom_field_values_sql = "
        CREATE TABLE crminternet_custom_field_values (
            id VARCHAR(36) PRIMARY KEY,
            custom_field_id VARCHAR(36),
            entity_type VARCHAR(50),
            entity_id VARCHAR(36),
            field_value TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (custom_field_id) REFERENCES crminternet_custom_fields(id) ON DELETE CASCADE,
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_field (custom_field_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_custom_field_values_sql);
        $results['custom_field_values_table'] = ['status' => 'created', 'message' => 'Custom field values table created successfully'];
    } catch (Exception $e) {
        $results['custom_field_values_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE LEADS TABLE
    // ============================================================
    $create_leads_sql = "
        CREATE TABLE crminternet_leads (
            id VARCHAR(36) PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(120),
            phone VARCHAR(40),
            status VARCHAR(50) DEFAULT 'nouveau',
            assigned_to VARCHAR(36),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (assigned_to) REFERENCES crminternet_users(id) ON DELETE SET NULL,
            INDEX idx_status (status),
            INDEX idx_assigned_to (assigned_to),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_leads_sql);
        $results['leads_table'] = ['status' => 'created', 'message' => 'Leads table created successfully'];
    } catch (Exception $e) {
        $results['leads_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE NOTES TABLE
    // ============================================================
    $create_notes_sql = "
        CREATE TABLE crminternet_notes (
            id VARCHAR(36) PRIMARY KEY,
            entity_type VARCHAR(50),
            entity_id VARCHAR(36),
            author_id VARCHAR(36),
            content TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (author_id) REFERENCES crminternet_users(id) ON DELETE SET NULL,
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_author (author_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_notes_sql);
        $results['notes_table'] = ['status' => 'created', 'message' => 'Notes table created successfully'];
    } catch (Exception $e) {
        $results['notes_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE AUDIT LOGS TABLE
    // ============================================================
    $create_audit_logs_sql = "
        CREATE TABLE crminternet_audit_logs (
            id VARCHAR(36) PRIMARY KEY,
            action VARCHAR(100),
            entity_type VARCHAR(50),
            entity_id VARCHAR(36),
            user_id VARCHAR(36),
            changes LONGTEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES crminternet_users(id) ON DELETE SET NULL,
            INDEX idx_action (action),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_user (user_id),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    // Note: audit_logs_table is created later as crminternet_audit_log (without 's')
    // Skipping duplicate creation here to avoid conflicts

    // ============================================================
    // CREATE CALENDAR EVENTS TABLE
    // ============================================================
    $create_calendar_events_sql = "
        CREATE TABLE crminternet_calendar_events (
            id VARCHAR(36) PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            start_time DATETIME NOT NULL,
            end_time DATETIME,
            all_day BOOLEAN DEFAULT FALSE,
            organizer_id VARCHAR(36),
            entity_type VARCHAR(50),
            entity_id VARCHAR(36),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (organizer_id) REFERENCES crminternet_users(id) ON DELETE SET NULL,
            INDEX idx_start_time (start_time),
            INDEX idx_organizer (organizer_id),
            INDEX idx_entity (entity_type, entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_calendar_events_sql);
        $results['calendar_events_table'] = ['status' => 'created', 'message' => 'Calendar events table created successfully'];
    } catch (Exception $e) {
        $results['calendar_events_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE CHAT MESSAGES TABLE
    // ============================================================
    $create_chat_messages_sql = "
        CREATE TABLE crminternet_chat_messages (
            id VARCHAR(36) PRIMARY KEY,
            sender_id VARCHAR(36),
            recipient_id VARCHAR(36),
            message TEXT,
            is_read BOOLEAN DEFAULT FALSE,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (sender_id) REFERENCES crminternet_users(id) ON DELETE CASCADE,
            FOREIGN KEY (recipient_id) REFERENCES crminternet_users(id) ON DELETE CASCADE,
            INDEX idx_sender (sender_id),
            INDEX idx_recipient (recipient_id),
            INDEX idx_is_read (is_read),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_chat_messages_sql);
        $results['chat_messages_table'] = ['status' => 'created', 'message' => 'Chat messages table created successfully'];
    } catch (Exception $e) {
        $results['chat_messages_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE CHAT CONVERSATIONS TABLE
    // ============================================================
    $create_chat_conversations_sql = "
        CREATE TABLE crminternet_chat_conversations (
            id VARCHAR(40) PRIMARY KEY,
            type ENUM('dm','group','broadcast') NOT NULL DEFAULT 'group',
            name VARCHAR(160),
            created_by VARCHAR(80),
            post_policy ENUM('all','admins') NOT NULL DEFAULT 'all',
            created_at DATETIME NOT NULL,
            last_message_at DATETIME,
            INDEX idx_type (type),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_chat_conversations_sql);
        $results['chat_conversations_table'] = ['status' => 'created', 'message' => 'Chat conversations table created successfully'];
    } catch (Exception $e) {
        $results['chat_conversations_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE CHAT MEMBERS TABLE
    // ============================================================
    $create_chat_members_sql = "
        CREATE TABLE crminternet_chat_members (
            conversation_id VARCHAR(40) NOT NULL,
            user_username VARCHAR(80) NOT NULL,
            role ENUM('admin','member') NOT NULL DEFAULT 'member',
            joined_at DATETIME NOT NULL,
            last_read_at DATETIME,
            muted BOOLEAN NOT NULL DEFAULT FALSE,
            hidden BOOLEAN NOT NULL DEFAULT FALSE,
            PRIMARY KEY (conversation_id, user_username),
            INDEX idx_user (user_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_chat_members_sql);
        $results['chat_members_table'] = ['status' => 'created', 'message' => 'Chat members table created successfully'];
    } catch (Exception $e) {
        $results['chat_members_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE CHAT MESSAGE READS TABLE
    // ============================================================
    $create_chat_message_reads_sql = "
        CREATE TABLE crminternet_chat_message_reads (
            message_id VARCHAR(40) NOT NULL,
            user_username VARCHAR(80) NOT NULL,
            read_at DATETIME NOT NULL,
            PRIMARY KEY (message_id, user_username),
            INDEX idx_user (user_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_chat_message_reads_sql);
        $results['chat_message_reads_table'] = ['status' => 'created', 'message' => 'Chat message reads table created successfully'];
    } catch (Exception $e) {
        $results['chat_message_reads_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE ACTIVITY LOG TABLE
    // ============================================================
    $create_activity_log_sql = "
        CREATE TABLE crminternet_activity_log (
            id VARCHAR(40) PRIMARY KEY,
            entity_type VARCHAR(32) NOT NULL,
            entity_id VARCHAR(40) NOT NULL,
            contract_id VARCHAR(40) DEFAULT '',
            field VARCHAR(40) NOT NULL,
            previous_value VARCHAR(255) NOT NULL,
            new_value VARCHAR(255) NOT NULL,
            user_username VARCHAR(80) NOT NULL,
            created_at DATETIME NOT NULL,
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_contract (contract_id),
            INDEX idx_user (user_username),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_activity_log_sql);
        $results['activity_log_table'] = ['status' => 'created', 'message' => 'Activity log table created successfully'];
    } catch (Exception $e) {
        $results['activity_log_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE ATTENDANCE TABLE
    // ============================================================
    $create_attendance_sql = "
        CREATE TABLE crminternet_attendance (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id VARCHAR(40) NOT NULL,
            username VARCHAR(80) NOT NULL,
            login_at DATETIME NOT NULL,
            logout_at DATETIME,
            total_minutes INT NOT NULL DEFAULT 0,
            ip VARCHAR(64),
            user_agent VARCHAR(255),
            INDEX idx_user (user_id),
            INDEX idx_username (username),
            INDEX idx_login (login_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_attendance_sql);
        $results['attendance_table'] = ['status' => 'created', 'message' => 'Attendance table created successfully'];
    } catch (Exception $e) {
        $results['attendance_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE AUDIT LOG TABLE (Enhanced)
    // ============================================================
    $create_audit_log_sql = "
        CREATE TABLE crminternet_audit_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            created_at DATETIME NOT NULL,
            user_username VARCHAR(80),
            user_role VARCHAR(64),
            action VARCHAR(80) NOT NULL,
            entity_type VARCHAR(40),
            entity_id VARCHAR(80),
            method VARCHAR(8),
            path VARCHAR(255),
            ip VARCHAR(64),
            user_agent VARCHAR(255),
            status_code SMALLINT,
            details TEXT,
            INDEX idx_action (action),
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_user (user_username),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_audit_log_sql);
        $results['audit_log_table'] = ['status' => 'created', 'message' => 'Audit log table created successfully'];
    } catch (Exception $e) {
        $results['audit_log_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE COMMISSIONS TABLE
    // ============================================================
    $create_commissions_sql = "
        CREATE TABLE crminternet_commissions (
            id VARCHAR(40) PRIMARY KEY,
            external_agent_id VARCHAR(40) NOT NULL,
            prospect_id VARCHAR(40),
            contract_id VARCHAR(40),
            amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            basis DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
            earned_at DATE NOT NULL,
            paid_at DATETIME,
            paid_by VARCHAR(80),
            payment_ref VARCHAR(120),
            notes TEXT,
            created_at DATETIME NOT NULL,
            INDEX idx_agent (external_agent_id),
            INDEX idx_prospect (prospect_id),
            INDEX idx_contract (contract_id),
            INDEX idx_status (status),
            INDEX idx_earned (earned_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_commissions_sql);
        $results['commissions_table'] = ['status' => 'created', 'message' => 'Commissions table created successfully'];
    } catch (Exception $e) {
        $results['commissions_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE CONTRACT INFO TABLE
    // ============================================================
    $create_contract_info_sql = "
        CREATE TABLE crminternet_contract_info (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            entity_type VARCHAR(50) NOT NULL,
            entity_id VARCHAR(40) NOT NULL,
            info_key VARCHAR(100) NOT NULL,
            info_value TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            created_by VARCHAR(80),
            updated_by VARCHAR(64),
            UNIQUE KEY uniq_entity_key (entity_type, entity_id, info_key),
            INDEX idx_entity (entity_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_contract_info_sql);
        $results['contract_info_table'] = ['status' => 'created', 'message' => 'Contract info table created successfully'];
    } catch (Exception $e) {
        $results['contract_info_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE CONTRACT STAGES TABLE
    // ============================================================
    $create_contract_stages_sql = "
        CREATE TABLE crminternet_contract_stages (
            id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            label VARCHAR(255),
            description TEXT,
            position INT DEFAULT 0,
            color VARCHAR(7) DEFAULT '#000000',
            is_final BOOLEAN DEFAULT FALSE,
            auto_action ENUM('none','revert_opportunity') NOT NULL DEFAULT 'none',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_position (position),
            INDEX idx_is_final (is_final)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_contract_stages_sql);
        $results['contract_stages_table'] = ['status' => 'created', 'message' => 'Contract stages table created successfully'];
    } catch (Exception $e) {
        $results['contract_stages_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE EXTERNAL AGENTS TABLE
    // ============================================================
    $create_external_agents_sql = "
        CREATE TABLE crminternet_external_agents (
            id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(120),
            phone VARCHAR(40),
            address VARCHAR(255),
            city VARCHAR(100),
            postal_code VARCHAR(20),
            country VARCHAR(100),
            company VARCHAR(255),
            commission_rate DECIMAL(5,2),
            active BOOLEAN DEFAULT TRUE,
            notes TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_active (active),
            INDEX idx_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_external_agents_sql);
        $results['external_agents_table'] = ['status' => 'created', 'message' => 'External agents table created successfully'];
    } catch (Exception $e) {
        $results['external_agents_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE FILTER PRESETS TABLE
    // ============================================================
    $create_filter_presets_sql = "
        CREATE TABLE crminternet_filter_presets (
            id VARCHAR(40) PRIMARY KEY,
            scope VARCHAR(80) NOT NULL,
            name VARCHAR(160) NOT NULL,
            description TEXT,
            filter_data TEXT NOT NULL,
            is_default BOOLEAN DEFAULT FALSE,
            default_role VARCHAR(100),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            UNIQUE KEY uniq_scope_name (scope, name),
            INDEX idx_scope (scope),
            INDEX idx_default (is_default),
            INDEX idx_role_default (scope, default_role, is_default)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_filter_presets_sql);
        $results['filter_presets_table'] = ['status' => 'created', 'message' => 'Filter presets table created successfully'];
    } catch (Exception $e) {
        $results['filter_presets_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE FILTER PRESET USER CHOICE TABLE
    // ============================================================
    $create_filter_preset_user_choice_sql = "
        CREATE TABLE crminternet_filter_preset_user_choice (
            username VARCHAR(80) NOT NULL,
            scope VARCHAR(80) NOT NULL,
            preset_id VARCHAR(40) NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (username, scope),
            INDEX idx_preset (preset_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_filter_preset_user_choice_sql);
        $results['filter_preset_user_choice_table'] = ['status' => 'created', 'message' => 'Filter preset user choice table created successfully'];
    } catch (Exception $e) {
        $results['filter_preset_user_choice_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE GUICHET DOSSIERS TABLE
    // ============================================================
    $create_guichet_dossiers_sql = "
        CREATE TABLE crminternet_guichet_dossiers (
            id VARCHAR(40) PRIMARY KEY,
            entity_id VARCHAR(40) NOT NULL,
            reference VARCHAR(100) NOT NULL UNIQUE,
            client_cin VARCHAR(50),
            client_name VARCHAR(255),
            status VARCHAR(50) NOT NULL DEFAULT 'open',
            agent_id VARCHAR(40),
            notes TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_entity (entity_id),
            INDEX idx_status (status),
            INDEX idx_agent (agent_id),
            INDEX idx_gd_cin (client_cin)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_guichet_dossiers_sql);
        $results['guichet_dossiers_table'] = ['status' => 'created', 'message' => 'Guichet dossiers table created successfully'];
    } catch (Exception $e) {
        $results['guichet_dossiers_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE GUICHET ENTITIES TABLE
    // ============================================================
    $create_guichet_entities_sql = "
        CREATE TABLE crminternet_guichet_entities (
            id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_guichet_entities_sql);
        $results['guichet_entities_table'] = ['status' => 'created', 'message' => 'Guichet entities table created successfully'];
    } catch (Exception $e) {
        $results['guichet_entities_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE GUICHET ENTRIES TABLE
    // ============================================================
    $create_guichet_entries_sql = "
        CREATE TABLE crminternet_guichet_entries (
            id VARCHAR(40) PRIMARY KEY,
            dossier_id VARCHAR(40) NOT NULL,
            entry_type VARCHAR(50),
            entry_value VARCHAR(255),
            entry_description TEXT,
            op_date DATE,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (dossier_id) REFERENCES crminternet_guichet_dossiers(id) ON DELETE CASCADE,
            INDEX idx_dossier (dossier_id),
            INDEX idx_ge_op_date (op_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_guichet_entries_sql);
        $results['guichet_entries_table'] = ['status' => 'created', 'message' => 'Guichet entries table created successfully'];
    } catch (Exception $e) {
        $results['guichet_entries_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE GUICHET OBJECTIVES TABLE
    // ============================================================
    $create_guichet_objectives_sql = "
        CREATE TABLE crminternet_guichet_objectives (
            id VARCHAR(40) PRIMARY KEY,
            entity_id VARCHAR(40) NOT NULL,
            period_month VARCHAR(7) NOT NULL,
            objective_target INT NOT NULL DEFAULT 0,
            objective_budget DECIMAL(12,2) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_entity (entity_id),
            INDEX idx_period (period_month),
            UNIQUE KEY uniq_entity_month (entity_id, period_month)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_guichet_objectives_sql);
        $results['guichet_objectives_table'] = ['status' => 'created', 'message' => 'Guichet objectives table created successfully'];
    } catch (Exception $e) {
        $results['guichet_objectives_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE IDLE TIMEOUTS TABLE
    // ============================================================
    $create_idle_timeouts_sql = "
        CREATE TABLE crminternet_idle_timeouts (
            role VARCHAR(64) PRIMARY KEY,
            timeout_seconds INT NOT NULL DEFAULT 1800,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            updated_by VARCHAR(64)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_idle_timeouts_sql);
        $results['idle_timeouts_table'] = ['status' => 'created', 'message' => 'Idle timeouts table created successfully'];
    } catch (Exception $e) {
        $results['idle_timeouts_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE LEAD ACTIONS TABLE
    // ============================================================
    $create_lead_actions_sql = "
        CREATE TABLE crminternet_lead_actions (
            id VARCHAR(40) PRIMARY KEY,
            prospect_id VARCHAR(40) NOT NULL,
            action_type VARCHAR(50),
            action_description TEXT,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (prospect_id) REFERENCES crminternet_prospects(id) ON DELETE CASCADE,
            INDEX idx_prospect (prospect_id),
            INDEX idx_type (action_type),
            INDEX idx_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_lead_actions_sql);
        $results['lead_actions_table'] = ['status' => 'created', 'message' => 'Lead actions table created successfully'];
    } catch (Exception $e) {
        $results['lead_actions_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE LEAD STAGES TABLE
    // ============================================================
    $create_lead_stages_sql = "
        CREATE TABLE crminternet_lead_stages (
            id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            label VARCHAR(255),
            description TEXT,
            position INT DEFAULT 0,
            color VARCHAR(7) DEFAULT '#000000',
            is_final BOOLEAN DEFAULT FALSE,
            auto_action ENUM('none','convert_opportunity','convert_contract') NOT NULL DEFAULT 'none',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_position (position),
            INDEX idx_is_final (is_final)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_lead_stages_sql);
        $results['lead_stages_table'] = ['status' => 'created', 'message' => 'Lead stages table created successfully'];
    } catch (Exception $e) {
        $results['lead_stages_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE LOGIN OTP TABLE
    // ============================================================
    $create_login_otp_sql = "
        CREATE TABLE crminternet_login_otp (
            challenge VARCHAR(40) PRIMARY KEY,
            user_id VARCHAR(40) NOT NULL,
            code_hash VARCHAR(255) NOT NULL,
            expires_at DATETIME NOT NULL,
            attempts TINYINT NOT NULL DEFAULT 0,
            used TINYINT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX idx_user (user_id),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_login_otp_sql);
        $results['login_otp_table'] = ['status' => 'created', 'message' => 'Login OTP table created successfully'];
    } catch (Exception $e) {
        $results['login_otp_table'] = ['status' => 'error', 'message' => $e->getMessage()];
        // MySQL 5.7 / MariaDB: retry via shared repair (no DEFAULT on created_at)
        try {
            require_once __DIR__ . '/schema_repair.php';
            ensure_login_otp_schema($pdo);
            $results['login_otp_table'] = ['status' => 'created', 'message' => 'Login OTP table created via schema repair'];
        } catch (Exception $e2) {
            $results['login_otp_table']['repair_error'] = $e2->getMessage();
        }
    }

    // ============================================================
    // CREATE NOTIFICATIONS TABLE
    // ============================================================
    $create_notifications_sql = "
        CREATE TABLE crminternet_notifications (
            id VARCHAR(40) PRIMARY KEY,
            user_username VARCHAR(80) NOT NULL,
            title VARCHAR(255),
            message TEXT,
            type VARCHAR(50),
            is_read BOOLEAN DEFAULT FALSE,
            related_entity_type VARCHAR(50),
            related_entity_id VARCHAR(40),
            created_at DATETIME NOT NULL,
            INDEX idx_user (user_username),
            INDEX idx_is_read (is_read),
            INDEX idx_user_created (user_username, created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_notifications_sql);
        $results['notifications_table'] = ['status' => 'created', 'message' => 'Notifications table created successfully'];
    } catch (Exception $e) {
        $results['notifications_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE OPPORTUNITIES TABLE
    // ============================================================
    $create_opportunities_sql = "
        CREATE TABLE crminternet_opportunities (
            id VARCHAR(40) PRIMARY KEY,
            prospect_id VARCHAR(40),
            civility ENUM('M','Mme') NOT NULL DEFAULT 'M',
            last_name VARCHAR(120) NOT NULL,
            first_name VARCHAR(120) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            phone2 VARCHAR(40) NOT NULL DEFAULT '',
            email VARCHAR(160) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            source VARCHAR(80) NOT NULL DEFAULT '',
            title VARCHAR(200) NOT NULL DEFAULT '',
            stage VARCHAR(80) NOT NULL DEFAULT 'Qualification',
            amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            probability TINYINT NOT NULL DEFAULT 50,
            expected_close_date DATE,
            assigned_to VARCHAR(80),
            notes TEXT,
            created_at DATETIME NOT NULL,
            created_by VARCHAR(80),
            converted_to_contract TINYINT(1) NOT NULL DEFAULT 0,
            contract_id VARCHAR(40),
            converted_at DATETIME,
            reverted_at DATETIME,
            cin VARCHAR(40),
            birth_date DATE,
            gouvernorat VARCHAR(120) NOT NULL DEFAULT '',
            delegation VARCHAR(120) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            localisation_xy VARCHAR(64),
            code_postal VARCHAR(20),
            comment1 TEXT,
            comment2 TEXT,
            type_id VARCHAR(40),
            lead_status VARCHAR(80),
            lost_reason TEXT,
            zone VARCHAR(120),
            animateur VARCHAR(80),
            ancien_ligne VARCHAR(80),
            INDEX idx_stage (stage),
            INDEX idx_assigned (assigned_to),
            INDEX idx_prospect (prospect_id),
            INDEX idx_converted_contract (converted_to_contract),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_opportunities_sql);
        $results['opportunities_table'] = ['status' => 'created', 'message' => 'Opportunities table created successfully'];
    } catch (Exception $e) {
        $results['opportunities_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE OPPORTUNITY STAGES TABLE
    // ============================================================
    $create_opportunity_stages_sql = "
        CREATE TABLE crminternet_opportunity_stages (
            id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            label VARCHAR(255),
            description TEXT,
            position INT DEFAULT 0,
            color VARCHAR(7) DEFAULT '#000000',
            is_final BOOLEAN DEFAULT FALSE,
            auto_action ENUM('none','convert_contract','revert_lead') NOT NULL DEFAULT 'none',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_position (position),
            INDEX idx_is_final (is_final)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_opportunity_stages_sql);
        $results['opportunity_stages_table'] = ['status' => 'created', 'message' => 'Opportunity stages table created successfully'];
    } catch (Exception $e) {
        $results['opportunity_stages_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE PAYROLL TABLE
    // ============================================================
    $create_payroll_sql = "
        CREATE TABLE crminternet_payroll (
            id VARCHAR(40) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            period_month VARCHAR(7) NOT NULL,
            base_salary DECIMAL(10,2),
            commission DECIMAL(10,2) DEFAULT 0,
            deductions DECIMAL(10,2) DEFAULT 0,
            net_salary DECIMAL(10,2),
            status VARCHAR(50) DEFAULT 'pending',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES crminternet_users(id) ON DELETE CASCADE,
            UNIQUE KEY uniq_user_period (user_id, period_month),
            INDEX idx_user (user_id),
            INDEX idx_period (period_month),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_payroll_sql);
        $results['payroll_table'] = ['status' => 'created', 'message' => 'Payroll table created successfully'];
    } catch (Exception $e) {
        $results['payroll_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE PIPELINE TRANSITIONS TABLE
    // ============================================================
    $create_pipeline_transitions_sql = "
        CREATE TABLE crminternet_pipeline_transitions (
            id VARCHAR(40) PRIMARY KEY,
            pipeline VARCHAR(50) NOT NULL,
            from_stage_id VARCHAR(40) NOT NULL,
            to_stage_id VARCHAR(40) NOT NULL,
            INDEX idx_pipeline (pipeline),
            INDEX idx_from_stage (from_stage_id),
            INDEX idx_to_stage (to_stage_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_pipeline_transitions_sql);
        $results['pipeline_transitions_table'] = ['status' => 'created', 'message' => 'Pipeline transitions table created successfully'];
    } catch (Exception $e) {
        $results['pipeline_transitions_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE PROSPECT TYPES TABLE
    // ============================================================
    $create_prospect_types_sql = "
        CREATE TABLE crminternet_prospect_types (
            id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            label VARCHAR(255),
            icon VARCHAR(100),
            active BOOLEAN DEFAULT TRUE,
            position INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            INDEX idx_active_pos (active, position)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_prospect_types_sql);
        $results['prospect_types_table'] = ['status' => 'created', 'message' => 'Prospect types table created successfully'];
    } catch (Exception $e) {
        $results['prospect_types_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE RECLAMATIONS TABLE
    // ============================================================
    $create_reclamations_sql = "
        CREATE TABLE crminternet_reclamations (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(32) NOT NULL UNIQUE,
            tel_adsl VARCHAR(32),
            ref_demand VARCHAR(64),
            cin_client VARCHAR(32),
            gsm_client VARCHAR(32),
            client_name VARCHAR(160),
            service ENUM('Technique','Facturation','Commercial','Autre') NOT NULL DEFAULT 'Technique',
            description TEXT,
            statut_crm VARCHAR(80),
            statut_tt VARCHAR(80),
            audit_status ENUM('en_cours','resolu','annule') NOT NULL DEFAULT 'en_cours',
            localisation VARCHAR(160),
            etat VARCHAR(80),
            remarques TEXT,
            date_creation DATETIME NOT NULL,
            date_resolution DATETIME,
            assigned_to VARCHAR(80),
            created_by VARCHAR(80),
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            INDEX idx_rec_audit (audit_status),
            INDEX idx_rec_service (service),
            INDEX idx_rec_tel (tel_adsl),
            INDEX idx_rec_cin (cin_client),
            INDEX idx_rec_gsm (gsm_client),
            INDEX idx_rec_assigned (assigned_to),
            INDEX idx_rec_created (date_creation),
            UNIQUE KEY uniq_rec_reference (reference)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_reclamations_sql);
        $results['reclamations_table'] = ['status' => 'created', 'message' => 'Reclamations table created successfully'];
    } catch (Exception $e) {
        $results['reclamations_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE RECLAMATION COUNTER TABLE
    // ============================================================
    $create_reclamation_counter_sql = "
        CREATE TABLE crminternet_reclamation_counter (
            period CHAR(6) PRIMARY KEY,
            last_seq INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_reclamation_counter_sql);
        $results['reclamation_counter_table'] = ['status' => 'created', 'message' => 'Reclamation counter table created successfully'];
    } catch (Exception $e) {
        $results['reclamation_counter_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE SCHEMA MIGRATIONS TABLE
    // ============================================================
    $create_schema_migrations_sql = "
        CREATE TABLE crminternet_schema_migrations (
            filename VARCHAR(160) PRIMARY KEY,
            applied_at DATETIME NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_schema_migrations_sql);
        $results['schema_migrations_table'] = ['status' => 'created', 'message' => 'Schema migrations table created successfully'];
    } catch (Exception $e) {
        $results['schema_migrations_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE SETTINGS TABLE
    // ============================================================
    $create_settings_sql = "
        CREATE TABLE crminternet_settings (
            scope VARCHAR(80) NOT NULL DEFAULT 'global',
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            description TEXT,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (scope, setting_key)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_settings_sql);
        $results['settings_table'] = ['status' => 'created', 'message' => 'Settings table created successfully'];
    } catch (Exception $e) {
        $results['settings_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE TASKS TABLE
    // ============================================================
    $create_tasks_sql = "
        CREATE TABLE crminternet_tasks (
            id VARCHAR(40) PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            entity_type VARCHAR(50),
            entity_id VARCHAR(40),
            assigned_to VARCHAR(80),
            status VARCHAR(50) DEFAULT 'pending',
            priority INT DEFAULT 0,
            due_date DATE,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            completed_at DATETIME,
            INDEX idx_entity (entity_type, entity_id),
            INDEX idx_assigned (assigned_to),
            INDEX idx_status (status),
            INDEX idx_tasks_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_tasks_sql);
        $results['tasks_table'] = ['status' => 'created', 'message' => 'Tasks table created successfully'];
    } catch (Exception $e) {
        $results['tasks_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE TEAMS TABLE
    // ============================================================
    $create_teams_sql = "
        CREATE TABLE crminternet_teams (
            id VARCHAR(40) PRIMARY KEY,
            name VARCHAR(100) NOT NULL UNIQUE,
            description TEXT,
            created_at DATETIME NOT NULL,
            INDEX idx_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_teams_sql);
        $results['teams_table'] = ['status' => 'created', 'message' => 'Teams table created successfully'];
    } catch (Exception $e) {
        $results['teams_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE TEAM ROLES TABLE
    // ============================================================
    $create_team_roles_sql = "
        CREATE TABLE crminternet_team_roles (
            team_id VARCHAR(40) NOT NULL,
            role VARCHAR(100) NOT NULL,
            PRIMARY KEY (team_id, role),
            INDEX idx_team_roles_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_team_roles_sql);
        $results['team_roles_table'] = ['status' => 'created', 'message' => 'Team roles table created successfully'];
    } catch (Exception $e) {
        $results['team_roles_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE USER GRANTS TABLE
    // ============================================================
    $create_user_grants_sql = "
        CREATE TABLE crminternet_user_grants (
            id VARCHAR(40) PRIMARY KEY,
            user_id VARCHAR(36) NOT NULL,
            user_username VARCHAR(80) NOT NULL,
            permission VARCHAR(100) NOT NULL,
            expires_at DATETIME,
            revoked BOOLEAN DEFAULT FALSE,
            created_at DATETIME NOT NULL,
            FOREIGN KEY (user_id) REFERENCES crminternet_users(id) ON DELETE CASCADE,
            INDEX idx_user (user_username),
            INDEX idx_active (user_username, expires_at, revoked)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_user_grants_sql);
        $results['user_grants_table'] = ['status' => 'created', 'message' => 'User grants table created successfully'];
    } catch (Exception $e) {
        $results['user_grants_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE USER PERMISSION OVERRIDES TABLE
    // ============================================================
    $create_user_permission_overrides_sql = "
        CREATE TABLE crminternet_user_permission_overrides (
            user_username VARCHAR(80) NOT NULL,
            permission VARCHAR(100) NOT NULL,
            override_value BOOLEAN NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY (user_username, permission),
            INDEX idx_user (user_username)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";

    try {
        $pdo->exec($create_user_permission_overrides_sql);
        $results['user_permission_overrides_table'] = ['status' => 'created', 'message' => 'User permission overrides table created successfully'];
    } catch (Exception $e) {
        $results['user_permission_overrides_table'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // CREATE DEFAULT ADMIN USER IN crminternet_users TABLE
    // ============================================================
    
    $check_admin_sql = "SELECT id FROM crminternet_users WHERE username = ? LIMIT 1";
    $stmt = $pdo->prepare($check_admin_sql);
    $stmt->execute([$admin_username]);
    $existing_admin = $stmt->fetch();

    if ($existing_admin) {
        $results['admin_user'] = [
            'status' => 'exists',
            'message' => "Admin user '$admin_username' already exists",
            'user_id' => $existing_admin['id']
        ];
    } else {
        $hashed_password = password_hash($admin_password, PASSWORD_BCRYPT, ['cost' => 12]);
        $admin_uuid = sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );

        try {
            $insert_admin_sql = "
                INSERT INTO crminternet_users (id, username, email, full_name, password_hash, role, team, active, must_change_password, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'Administrateur', NULL, TRUE, FALSE, NOW(), NOW())
            ";
            $stmt = $pdo->prepare($insert_admin_sql);
            $stmt->execute([$admin_uuid, $admin_username, $admin_email, $admin_fullname, $hashed_password]);

            $results['admin_user'] = [
                'status' => 'created',
                'message' => "Admin user '$admin_username' created successfully",
                'user_id' => $admin_uuid,
                'credentials' => [
                    'username' => $admin_username,
                    'password' => $admin_password,
                    'email' => $admin_email
                ]
            ];
        } catch (Exception $e) {
            $results['admin_user'] = [
                'status' => 'error',
                'message' => 'Failed to create admin user: ' . $e->getMessage()
            ];
        }
    }

    // ============================================================
    // SEED DEFAULT DATA (roles, types, stages, permissions, settings)
    // ============================================================
    try {
        require_once __DIR__ . '/crm_schema.php';
        $results['seed_data'] = crm_apply_seed_data($pdo);
    } catch (Throwable $e) {
        $results['seed_data'] = ['status' => 'error', 'message' => $e->getMessage()];
    }

    // ============================================================
    // SUCCESS RESPONSE
    // ============================================================
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Database setup completed successfully',
        'setup_results' => $results,
        'admin_credentials' => [
            'username' => $admin_username,
            'password' => $admin_password,
            'email' => $admin_email,
            'important' => 'Change these credentials in production immediately after first login!'
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => $e->getMessage(),
        'config' => [
            'host' => $host,
            'database' => $database,
            'user' => $username,
            'tip' => 'Verify database credentials and that MySQL is running'
        ]
    ], JSON_PRETTY_PRINT);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Setup failed',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    exit;
}
?>
