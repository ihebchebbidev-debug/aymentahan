-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: luccybcdb.mysql.db
-- Generation Time: May 30, 2026 at 12:13 PM
-- Server version: 8.0.45-36
-- PHP Version: 8.1.33

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `luccybcdb`
--

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_activity_log`
--

CREATE TABLE `crminternet_activity_log` (
  `id` varchar(40) NOT NULL,
  `entity_type` varchar(32) NOT NULL,
  `entity_id` varchar(40) NOT NULL,
  `contract_id` varchar(40) NOT NULL DEFAULT '',
  `field` varchar(40) NOT NULL,
  `previous_value` varchar(255) NOT NULL,
  `new_value` varchar(255) NOT NULL,
  `user_username` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_attachments`
--

CREATE TABLE `crminternet_attachments` (
  `id` varchar(40) NOT NULL,
  `entity` varchar(20) NOT NULL,
  `entity_id` varchar(40) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `mime_type` varchar(120) NOT NULL DEFAULT 'application/octet-stream',
  `size_bytes` bigint NOT NULL DEFAULT '0',
  `storage_path` varchar(500) NOT NULL,
  `uploaded_by` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sha256` char(64) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_attendance`
--

CREATE TABLE `crminternet_attendance` (
  `id` bigint NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `username` varchar(80) NOT NULL,
  `login_at` datetime NOT NULL,
  `logout_at` datetime DEFAULT NULL,
  `total_minutes` int NOT NULL DEFAULT '0',
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_audit_log`
--

CREATE TABLE `crminternet_audit_log` (
  `id` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_username` varchar(80) DEFAULT NULL,
  `user_role` varchar(64) DEFAULT NULL,
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_id` varchar(80) DEFAULT NULL,
  `method` varchar(8) DEFAULT NULL,
  `path` varchar(255) DEFAULT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `status_code` smallint DEFAULT NULL,
  `details` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_calendar_events`
--

CREATE TABLE `crminternet_calendar_events` (
  `id` varchar(40) NOT NULL,
  `title` varchar(160) NOT NULL,
  `date` date NOT NULL,
  `time` varchar(8) NOT NULL,
  `type` enum('rdv','rappel','signature') NOT NULL DEFAULT 'rdv',
  `agent` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_chat_conversations`
--

CREATE TABLE `crminternet_chat_conversations` (
  `id` varchar(40) NOT NULL,
  `type` enum('dm','group','broadcast') NOT NULL DEFAULT 'group',
  `name` varchar(160) DEFAULT NULL,
  `created_by` varchar(80) DEFAULT NULL,
  `post_policy` enum('all','admins') NOT NULL DEFAULT 'all',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_message_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_chat_members`
--

CREATE TABLE `crminternet_chat_members` (
  `conversation_id` varchar(40) NOT NULL,
  `user_username` varchar(80) NOT NULL,
  `role` enum('admin','member') NOT NULL DEFAULT 'member',
  `joined_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_read_at` datetime DEFAULT NULL,
  `muted` tinyint(1) NOT NULL DEFAULT '0',
  `hidden` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_chat_messages`
--

CREATE TABLE `crminternet_chat_messages` (
  `id` varchar(40) NOT NULL,
  `conversation_id` varchar(40) NOT NULL,
  `sender_username` varchar(80) DEFAULT NULL,
  `body` text NOT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `attachment_id` varchar(40) DEFAULT NULL,
  `attachment_filename` varchar(255) DEFAULT NULL,
  `attachment_mime` varchar(120) DEFAULT NULL,
  `attachment_size` int DEFAULT NULL,
  `created_at` datetime(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3),
  `edited_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `reply_to_id` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_chat_message_reads`
--

CREATE TABLE `crminternet_chat_message_reads` (
  `message_id` varchar(40) NOT NULL,
  `user_username` varchar(80) NOT NULL,
  `read_at` timestamp(3) NOT NULL DEFAULT CURRENT_TIMESTAMP(3)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_commissions`
--

CREATE TABLE `crminternet_commissions` (
  `id` varchar(40) NOT NULL,
  `external_agent_id` varchar(40) NOT NULL,
  `prospect_id` varchar(40) DEFAULT NULL,
  `contract_id` varchar(40) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `basis` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('pending','paid','cancelled') NOT NULL DEFAULT 'pending',
  `earned_at` date NOT NULL,
  `paid_at` datetime DEFAULT NULL,
  `paid_by` varchar(80) DEFAULT NULL,
  `payment_ref` varchar(120) DEFAULT NULL,
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_contracts`
--

CREATE TABLE `crminternet_contracts` (
  `id` varchar(40) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `first_name` varchar(120) NOT NULL DEFAULT '',
  `city` varchar(120) NOT NULL DEFAULT '',
  `partner` varchar(80) NOT NULL DEFAULT 'NEOLIANE',
  `cabinet` varchar(120) NOT NULL DEFAULT 'Cabinet Paris 1',
  `signature_date` date NOT NULL,
  `effective_date` date NOT NULL,
  `validation_date` date DEFAULT NULL,
  `premium` decimal(10,2) NOT NULL DEFAULT '0.00',
  `billing_status` varchar(80) NOT NULL DEFAULT 'Pré-validé',
  `source` varchar(80) NOT NULL DEFAULT 'Web',
  `assigned_to` varchar(80) NOT NULL DEFAULT '',
  `stage_id` varchar(40) DEFAULT NULL,
  `opportunity_id` varchar(40) DEFAULT NULL,
  `prospect_id` varchar(40) DEFAULT NULL,
  `type_id` varchar(40) DEFAULT NULL,
  `civility` enum('M','Mme') NOT NULL DEFAULT 'M',
  `phone` varchar(40) NOT NULL DEFAULT '',
  `phone2` varchar(40) DEFAULT '',
  `cin` varchar(40) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `email` varchar(160) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `gouvernorat` varchar(120) NOT NULL DEFAULT '',
  `delegation` varchar(120) NOT NULL DEFAULT '',
  `comment1` text,
  `comment2` text,
  `code_postal` varchar(16) DEFAULT NULL,
  `localisation_xy` varchar(64) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `animateur` varchar(120) DEFAULT NULL,
  `ancien_ligne` varchar(40) DEFAULT NULL,
  `zone` varchar(120) NOT NULL DEFAULT '',
  `lead_status` varchar(80) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_contract_info`
--

CREATE TABLE `crminternet_contract_info` (
  `id` bigint UNSIGNED NOT NULL,
  `entity_type` enum('prospect','opportunity','contract') COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type_conn` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reference_tt` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `tel_ligne` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date_activation` date DEFAULT NULL,
  `etape` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `interface_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fsi` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `motif_retour_tt` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `etat` enum('','En cours','Basculement','Rejete','Valide') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `remarque` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_contract_stages`
--

CREATE TABLE `crminternet_contract_stages` (
  `id` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'muted',
  `position` int NOT NULL DEFAULT '0',
  `is_initial` tinyint(1) NOT NULL DEFAULT '0',
  `is_won` tinyint(1) NOT NULL DEFAULT '0',
  `is_lost` tinyint(1) NOT NULL DEFAULT '0',
  `auto_action` enum('none','revert_opportunity') NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_custom_fields`
--

CREATE TABLE `crminternet_custom_fields` (
  `id` varchar(40) NOT NULL,
  `entity` varchar(20) NOT NULL,
  `field_key` varchar(80) NOT NULL,
  `label` varchar(160) NOT NULL,
  `type` varchar(20) NOT NULL DEFAULT 'text',
  `options` text,
  `required` tinyint(1) NOT NULL DEFAULT '0',
  `position` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `type_id` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_custom_field_values`
--

CREATE TABLE `crminternet_custom_field_values` (
  `id` bigint NOT NULL,
  `entity` varchar(20) NOT NULL,
  `entity_id` varchar(40) NOT NULL,
  `field_key` varchar(80) NOT NULL,
  `value` text,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_external_agents`
--

CREATE TABLE `crminternet_external_agents` (
  `id` varchar(40) NOT NULL,
  `full_name` varchar(160) NOT NULL,
  `phone` varchar(40) NOT NULL DEFAULT '',
  `email` varchar(160) NOT NULL DEFAULT '',
  `cin` varchar(40) NOT NULL DEFAULT '',
  `commission_rate` decimal(6,2) NOT NULL DEFAULT '0.00',
  `fixed_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_filter_presets`
--

CREATE TABLE `crminternet_filter_presets` (
  `id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` enum('prospects','opportunities','contracts') COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `filters_json` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_shared` tinyint(1) NOT NULL DEFAULT '1',
  `is_default` tinyint(1) NOT NULL DEFAULT '0',
  `default_role` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  `created_by` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_filter_preset_user_choice`
--

CREATE TABLE `crminternet_filter_preset_user_choice` (
  `username` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `scope` enum('prospects','opportunities','contracts') COLLATE utf8mb4_unicode_ci NOT NULL,
  `preset_id` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_guichet_dossiers`
--

CREATE TABLE `crminternet_guichet_dossiers` (
  `id` varchar(40) NOT NULL,
  `ref` varchar(20) NOT NULL,
  `entity_id` varchar(40) NOT NULL,
  `agent_id` varchar(40) NOT NULL,
  `client_name` varchar(160) DEFAULT NULL,
  `client_cin` varchar(20) DEFAULT NULL,
  `status` enum('draft','valide') NOT NULL DEFAULT 'draft',
  `validated_at` datetime DEFAULT NULL,
  `validated_by` varchar(40) DEFAULT NULL,
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_guichet_entities`
--

CREATE TABLE `crminternet_guichet_entities` (
  `id` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `type` enum('ttshop','franchise','autre') NOT NULL DEFAULT 'ttshop',
  `city` varchar(120) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_guichet_entries`
--

CREATE TABLE `crminternet_guichet_entries` (
  `id` varchar(40) NOT NULL,
  `dossier_id` varchar(40) NOT NULL,
  `type` enum('sim','port','swp','divers','facture_tt','facture_topnet') NOT NULL,
  `cin` varchar(20) DEFAULT NULL,
  `numero` varchar(40) DEFAULT NULL,
  `amount` decimal(12,3) DEFAULT NULL,
  `offre` varchar(60) DEFAULT NULL,
  `operator_source` varchar(60) DEFAULT NULL,
  `label` varchar(160) DEFAULT NULL,
  `op_date` date DEFAULT NULL,
  `status` enum('draft','valide') NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_guichet_objectives`
--

CREATE TABLE `crminternet_guichet_objectives` (
  `id` varchar(40) NOT NULL,
  `scope` enum('agent','entity','global') NOT NULL DEFAULT 'agent',
  `agent_id` varchar(40) DEFAULT NULL,
  `entity_id` varchar(40) DEFAULT NULL,
  `period_month` char(7) NOT NULL,
  `target_sim` int NOT NULL DEFAULT '900',
  `target_port` int NOT NULL DEFAULT '90',
  `target_fancy` int NOT NULL DEFAULT '90',
  `target_contracts_daily` int NOT NULL DEFAULT '25',
  `target_contracts_monthly` int NOT NULL DEFAULT '650',
  `working_days` int NOT NULL DEFAULT '26',
  `budget_monthly_dt` decimal(10,2) DEFAULT NULL,
  `budget_daily_dt` decimal(10,2) DEFAULT NULL,
  `min_activation_pct` decimal(5,2) NOT NULL DEFAULT '25.00',
  `challenge_bonus_dt` decimal(8,2) DEFAULT NULL,
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_idle_timeouts`
--

CREATE TABLE `crminternet_idle_timeouts` (
  `role` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timeout_minutes` smallint UNSIGNED NOT NULL DEFAULT '30' COMMENT '0 = désactivé, sinon 1..720 minutes',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `updated_by` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_lead_actions`
--

CREATE TABLE `crminternet_lead_actions` (
  `id` varchar(40) NOT NULL,
  `prospect_id` varchar(40) NOT NULL,
  `agent_username` varchar(80) NOT NULL,
  `type` enum('appel','visite','relance','note','terrain','reseaux','technicien') NOT NULL DEFAULT 'note',
  `comment` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_lead_stages`
--

CREATE TABLE `crminternet_lead_stages` (
  `id` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'muted',
  `position` int NOT NULL DEFAULT '0',
  `is_initial` tinyint(1) NOT NULL DEFAULT '0',
  `is_won` tinyint(1) NOT NULL DEFAULT '0',
  `is_lost` tinyint(1) NOT NULL DEFAULT '0',
  `auto_action` enum('none','convert_opportunity','convert_contract') NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_login_otp`
--

CREATE TABLE `crminternet_login_otp` (
  `challenge` varchar(40) NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `code_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `attempts` tinyint NOT NULL DEFAULT '0',
  `used` tinyint NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_notifications`
--

CREATE TABLE `crminternet_notifications` (
  `id` varchar(40) NOT NULL,
  `user_username` varchar(80) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text,
  `link` varchar(500) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_opportunities`
--

CREATE TABLE `crminternet_opportunities` (
  `id` varchar(40) NOT NULL,
  `prospect_id` varchar(40) DEFAULT NULL,
  `civility` enum('M','Mme') NOT NULL DEFAULT 'M',
  `last_name` varchar(120) NOT NULL,
  `first_name` varchar(120) NOT NULL DEFAULT '',
  `phone` varchar(40) NOT NULL DEFAULT '',
  `email` varchar(160) NOT NULL DEFAULT '',
  `city` varchar(120) NOT NULL DEFAULT '',
  `source` varchar(80) NOT NULL DEFAULT '',
  `title` varchar(200) NOT NULL DEFAULT '',
  `stage` varchar(80) NOT NULL DEFAULT 'Qualification',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `probability` tinyint NOT NULL DEFAULT '50',
  `expected_close_date` date DEFAULT NULL,
  `assigned_to` varchar(80) DEFAULT NULL,
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` varchar(80) DEFAULT NULL,
  `converted_to_contract` tinyint(1) NOT NULL DEFAULT '0',
  `contract_id` varchar(40) DEFAULT NULL,
  `converted_at` datetime DEFAULT NULL,
  `reverted_at` datetime DEFAULT NULL,
  `type_id` varchar(40) DEFAULT NULL,
  `phone2` varchar(40) DEFAULT '',
  `animateur` varchar(120) DEFAULT NULL,
  `ancien_ligne` varchar(40) DEFAULT NULL,
  `cin` varchar(40) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `address` varchar(255) NOT NULL DEFAULT '',
  `gouvernorat` varchar(120) NOT NULL DEFAULT '',
  `delegation` varchar(120) NOT NULL DEFAULT '',
  `zone` varchar(120) NOT NULL DEFAULT '',
  `comment1` text,
  `comment2` text,
  `code_postal` varchar(16) DEFAULT NULL,
  `localisation_xy` varchar(64) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `lost_reason` varchar(255) DEFAULT NULL,
  `lead_status` varchar(80) DEFAULT NULL COMMENT 'Statut d''appel du lead source au moment de la conversion'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_opportunity_stages`
--

CREATE TABLE `crminternet_opportunity_stages` (
  `id` varchar(40) NOT NULL,
  `name` varchar(80) NOT NULL,
  `color` varchar(20) NOT NULL DEFAULT 'muted',
  `position` int NOT NULL DEFAULT '0',
  `is_won` tinyint(1) NOT NULL DEFAULT '0',
  `is_lost` tinyint(1) NOT NULL DEFAULT '0',
  `is_initial` tinyint(1) NOT NULL DEFAULT '0',
  `auto_action` enum('none','convert_contract','revert_lead') NOT NULL DEFAULT 'none'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_payroll`
--

CREATE TABLE `crminternet_payroll` (
  `id` varchar(40) NOT NULL,
  `user_id` varchar(40) NOT NULL,
  `username` varchar(80) NOT NULL,
  `period` char(7) NOT NULL,
  `base_salary` decimal(10,2) NOT NULL DEFAULT '0.00',
  `hours_worked` decimal(7,2) NOT NULL DEFAULT '0.00',
  `hourly_rate` decimal(8,2) NOT NULL DEFAULT '0.00',
  `bonus` decimal(10,2) NOT NULL DEFAULT '0.00',
  `deductions` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','validated','paid') NOT NULL DEFAULT 'draft',
  `paid_at` datetime DEFAULT NULL,
  `notes` text,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_pipeline_transitions`
--

CREATE TABLE `crminternet_pipeline_transitions` (
  `id` varchar(40) NOT NULL,
  `pipeline` enum('lead','opportunity','contract') NOT NULL,
  `from_stage_id` varchar(40) NOT NULL,
  `to_stage_id` varchar(40) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_prospects`
--

CREATE TABLE `crminternet_prospects` (
  `id` varchar(40) NOT NULL,
  `civility` enum('M','Mme') NOT NULL DEFAULT 'M',
  `last_name` varchar(120) NOT NULL,
  `first_name` varchar(120) NOT NULL DEFAULT '',
  `phone` varchar(40) NOT NULL DEFAULT '',
  `phone2` varchar(40) NOT NULL DEFAULT '',
  `ancien_ligne` varchar(40) DEFAULT NULL,
  `animateur` varchar(120) DEFAULT NULL,
  `cin` varchar(40) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `email` varchar(160) NOT NULL DEFAULT '',
  `source` varchar(80) NOT NULL DEFAULT 'Terrain',
  `status` varchar(80) NOT NULL DEFAULT 'Nouveau',
  `stage` varchar(80) DEFAULT NULL,
  `assigned_to` varchar(80) DEFAULT NULL,
  `created_at` date NOT NULL,
  `city` varchar(120) NOT NULL DEFAULT '',
  `address` varchar(255) NOT NULL DEFAULT '',
  `zone` varchar(120) NOT NULL DEFAULT '',
  `outcome` enum('pending','won','lost') NOT NULL DEFAULT 'pending',
  `lost_reason` varchar(255) DEFAULT NULL,
  `comment` text,
  `comment2` text,
  `check_valeur` enum('valid','invalid','pending') NOT NULL DEFAULT 'pending',
  `converted` tinyint(1) NOT NULL DEFAULT '0',
  `converted_at` datetime DEFAULT NULL,
  `opportunity_id` varchar(40) DEFAULT NULL,
  `type_id` varchar(40) DEFAULT NULL,
  `gouvernorat` varchar(120) NOT NULL DEFAULT '',
  `delegation` varchar(120) NOT NULL DEFAULT '',
  `code_postal` varchar(16) DEFAULT NULL,
  `localisation_xy` varchar(64) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `reverted_at` datetime DEFAULT NULL,
  `reverted_from` varchar(20) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_prospect_types`
--

CREATE TABLE `crminternet_prospect_types` (
  `id` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `color` varchar(32) NOT NULL DEFAULT 'primary',
  `position` int NOT NULL DEFAULT '100',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_reclamations`
--

CREATE TABLE `crminternet_reclamations` (
  `id` bigint UNSIGNED NOT NULL,
  `reference` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tel_adsl` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ref_demand` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cin_client` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `gsm_client` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `client_name` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `service` enum('Technique','Facturation','Commercial','Autre') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Technique',
  `description` text COLLATE utf8mb4_unicode_ci,
  `statut_crm` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `statut_tt` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `audit_status` enum('en_cours','resolu','annule') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en_cours',
  `localisation` varchar(160) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `etat` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarques` text COLLATE utf8mb4_unicode_ci,
  `date_creation` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `date_resolution` datetime DEFAULT NULL,
  `mois` tinyint UNSIGNED GENERATED ALWAYS AS (month(`date_creation`)) STORED,
  `annee` smallint UNSIGNED GENERATED ALWAYS AS (year(`date_creation`)) STORED,
  `assigned_to` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `subject` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `priority` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_reclamation_counter`
--

CREATE TABLE `crminternet_reclamation_counter` (
  `period` char(6) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_seq` int UNSIGNED NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_roles`
--

CREATE TABLE `crminternet_roles` (
  `name` varchar(64) NOT NULL,
  `label` varchar(120) NOT NULL,
  `description` varchar(255) NOT NULL DEFAULT '',
  `color` varchar(32) NOT NULL DEFAULT 'primary',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `sort_order` int NOT NULL DEFAULT '100',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_role_permissions`
--

CREATE TABLE `crminternet_role_permissions` (
  `role` varchar(64) NOT NULL,
  `permission` varchar(80) NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_schema_migrations`
--

CREATE TABLE `crminternet_schema_migrations` (
  `filename` varchar(160) NOT NULL,
  `applied_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_settings`
--

CREATE TABLE `crminternet_settings` (
  `scope` varchar(80) NOT NULL DEFAULT 'global',
  `setting_key` varchar(120) NOT NULL,
  `value` longtext NOT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_tasks`
--

CREATE TABLE `crminternet_tasks` (
  `id` varchar(40) NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text,
  `assigned_to` varchar(80) NOT NULL,
  `related_entity` varchar(20) DEFAULT NULL,
  `related_id` varchar(40) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `priority` enum('low','normal','high') NOT NULL DEFAULT 'normal',
  `status` enum('todo','in_progress','done','cancelled') NOT NULL DEFAULT 'todo',
  `created_by` varchar(80) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_teams`
--

CREATE TABLE `crminternet_teams` (
  `id` varchar(40) NOT NULL,
  `name` varchar(120) NOT NULL,
  `description` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_team_roles`
--

CREATE TABLE `crminternet_team_roles` (
  `team_id` varchar(40) NOT NULL,
  `role` varchar(80) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_users`
--

CREATE TABLE `crminternet_users` (
  `id` varchar(40) NOT NULL,
  `username` varchar(80) NOT NULL,
  `full_name` varchar(120) NOT NULL,
  `job_title` varchar(120) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `cin` varchar(40) DEFAULT NULL,
  `company` varchar(120) DEFAULT NULL,
  `contract_type` varchar(40) DEFAULT NULL,
  `salary` decimal(10,3) DEFAULT NULL,
  `salary_increase` decimal(10,3) DEFAULT NULL,
  `contract_start` date DEFAULT NULL,
  `contract_end` date DEFAULT NULL,
  `renewal_start` date DEFAULT NULL,
  `renewal_end` date DEFAULT NULL,
  `observations` text,
  `phone` varchar(40) DEFAULT NULL,
  `rib` varchar(40) DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `email` varchar(160) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(64) NOT NULL DEFAULT 'Agent',
  `team` varchar(80) NOT NULL DEFAULT 'Lead-Actifs',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `guichet_entity_id` varchar(40) DEFAULT NULL,
  `team_id` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_user_grants`
--

CREATE TABLE `crminternet_user_grants` (
  `id` varchar(40) NOT NULL,
  `user_username` varchar(80) NOT NULL,
  `grant_type` enum('role','permission') NOT NULL,
  `grant_value` varchar(120) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `granted_by` varchar(80) NOT NULL,
  `starts_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT '0',
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by` varchar(80) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --------------------------------------------------------

--
-- Table structure for table `crminternet_user_permission_overrides`
--

CREATE TABLE `crminternet_user_permission_overrides` (
  `user_username` varchar(80) NOT NULL,
  `permission` varchar(80) NOT NULL,
  `effect` enum('allow','deny') NOT NULL,
  `updated_by` varchar(80) DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `crminternet_activity_log`
--
ALTER TABLE `crminternet_activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_contract` (`contract_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `crminternet_attachments`
--
ALTER TABLE `crminternet_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entity` (`entity`,`entity_id`),
  ADD KEY `idx_sha` (`sha256`);

--
-- Indexes for table `crminternet_attendance`
--
ALTER TABLE `crminternet_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_date` (`user_id`,`login_at`),
  ADD KEY `idx_username` (`username`);

--
-- Indexes for table `crminternet_audit_log`
--
ALTER TABLE `crminternet_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_username`,`created_at`),
  ADD KEY `idx_action` (`action`,`created_at`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `crminternet_calendar_events`
--
ALTER TABLE `crminternet_calendar_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_agent` (`agent`);

--
-- Indexes for table `crminternet_chat_conversations`
--
ALTER TABLE `crminternet_chat_conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_lastmsg` (`last_message_at`);

--
-- Indexes for table `crminternet_chat_members`
--
ALTER TABLE `crminternet_chat_members`
  ADD PRIMARY KEY (`conversation_id`,`user_username`),
  ADD KEY `idx_user` (`user_username`);

--
-- Indexes for table `crminternet_chat_messages`
--
ALTER TABLE `crminternet_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conv_created` (`conversation_id`,`created_at`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_reply` (`reply_to_id`);

--
-- Indexes for table `crminternet_chat_message_reads`
--
ALTER TABLE `crminternet_chat_message_reads`
  ADD PRIMARY KEY (`message_id`,`user_username`),
  ADD KEY `idx_msg` (`message_id`),
  ADD KEY `idx_user` (`user_username`);

--
-- Indexes for table `crminternet_commissions`
--
ALTER TABLE `crminternet_commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_agent` (`external_agent_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_earned` (`earned_at`);

--
-- Indexes for table `crminternet_contracts`
--
ALTER TABLE `crminternet_contracts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_signdate` (`signature_date`),
  ADD KEY `idx_billing` (`billing_status`),
  ADD KEY `ix_contract_cin` (`cin`),
  ADD KEY `idx_deleted` (`deleted_at`),
  ADD KEY `idx_contract_prospect` (`prospect_id`),
  ADD KEY `idx_contract_animateur` (`animateur`),
  ADD KEY `idx_billing_signature` (`billing_status`,`signature_date`),
  ADD KEY `idx_assigned_signature` (`assigned_to`,`signature_date`),
  ADD KEY `idx_opportunity` (`opportunity_id`),
  ADD KEY `idx_prospect` (`prospect_id`),
  ADD KEY `idx_cin` (`cin`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_signature_date` (`signature_date`),
  ADD KEY `idx_stage_id` (`stage_id`);

--
-- Indexes for table `crminternet_contract_info`
--
ALTER TABLE `crminternet_contract_info`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_entity_id` (`entity_id`);

--
-- Indexes for table `crminternet_contract_stages`
--
ALTER TABLE `crminternet_contract_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `crminternet_custom_fields`
--
ALTER TABLE `crminternet_custom_fields`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_entity_key` (`entity`,`field_key`);

--
-- Indexes for table `crminternet_custom_field_values`
--
ALTER TABLE `crminternet_custom_field_values`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_entity_field` (`entity`,`entity_id`,`field_key`),
  ADD KEY `idx_entity` (`entity`,`entity_id`);

--
-- Indexes for table `crminternet_external_agents`
--
ALTER TABLE `crminternet_external_agents`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `crminternet_filter_presets`
--
ALTER TABLE `crminternet_filter_presets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_scope` (`scope`),
  ADD KEY `idx_default` (`scope`,`is_default`),
  ADD KEY `idx_role_default` (`scope`,`default_role`,`is_default`);

--
-- Indexes for table `crminternet_filter_preset_user_choice`
--
ALTER TABLE `crminternet_filter_preset_user_choice`
  ADD PRIMARY KEY (`username`,`scope`),
  ADD KEY `idx_preset` (`preset_id`);

--
-- Indexes for table `crminternet_guichet_dossiers`
--
ALTER TABLE `crminternet_guichet_dossiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ref` (`ref`),
  ADD KEY `idx_gd_entity` (`entity_id`),
  ADD KEY `idx_gd_agent` (`agent_id`),
  ADD KEY `idx_gd_status_date` (`status`,`created_at`),
  ADD KEY `idx_gd_cin` (`client_cin`);

--
-- Indexes for table `crminternet_guichet_entities`
--
ALTER TABLE `crminternet_guichet_entities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `crminternet_guichet_entries`
--
ALTER TABLE `crminternet_guichet_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ge_type_status` (`type`,`status`),
  ADD KEY `idx_ge_dossier` (`dossier_id`),
  ADD KEY `idx_ge_offre` (`offre`),
  ADD KEY `idx_ge_op_date` (`op_date`);

--
-- Indexes for table `crminternet_guichet_objectives`
--
ALTER TABLE `crminternet_guichet_objectives`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_scope_period` (`scope`,`agent_id`,`entity_id`,`period_month`),
  ADD KEY `idx_period` (`period_month`);

--
-- Indexes for table `crminternet_idle_timeouts`
--
ALTER TABLE `crminternet_idle_timeouts`
  ADD PRIMARY KEY (`role`);

--
-- Indexes for table `crminternet_lead_actions`
--
ALTER TABLE `crminternet_lead_actions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prospect` (`prospect_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `crminternet_lead_stages`
--
ALTER TABLE `crminternet_lead_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `crminternet_login_otp`
--
ALTER TABLE `crminternet_login_otp`
  ADD PRIMARY KEY (`challenge`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- Indexes for table `crminternet_notifications`
--
ALTER TABLE `crminternet_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_read` (`user_username`,`read_at`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_user_created` (`user_username`,`created_at`);

--
-- Indexes for table `crminternet_opportunities`
--
ALTER TABLE `crminternet_opportunities`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_prospect` (`prospect_id`),
  ADD KEY `idx_stage` (`stage`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_contract` (`contract_id`),
  ADD KEY `idx_converted_contract` (`converted_to_contract`),
  ADD KEY `ix_opp_cin` (`cin`),
  ADD KEY `idx_deleted` (`deleted_at`),
  ADD KEY `idx_opp_animateur` (`animateur`),
  ADD KEY `idx_opp_ancien_ligne` (`ancien_ligne`),
  ADD KEY `idx_assigned_created` (`assigned_to`,`created_at`),
  ADD KEY `idx_cin` (`cin`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `crminternet_opportunity_stages`
--
ALTER TABLE `crminternet_opportunity_stages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `crminternet_payroll`
--
ALTER TABLE `crminternet_payroll`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_period` (`user_id`,`period`),
  ADD KEY `idx_period` (`period`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `crminternet_pipeline_transitions`
--
ALTER TABLE `crminternet_pipeline_transitions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_transition` (`pipeline`,`from_stage_id`,`to_stage_id`),
  ADD KEY `idx_pipeline` (`pipeline`);

--
-- Indexes for table `crminternet_prospects`
--
ALTER TABLE `crminternet_prospects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assigned` (`assigned_to`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_outcome` (`outcome`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `ix_prospect_cin` (`cin`),
  ADD KEY `idx_deleted` (`deleted_at`),
  ADD KEY `idx_prospects_reverted_at` (`reverted_at`),
  ADD KEY `idx_prospects_ancien_ligne` (`ancien_ligne`),
  ADD KEY `idx_prospects_animateur` (`animateur`),
  ADD KEY `idx_status_created` (`status`,`created_at`),
  ADD KEY `idx_assigned_created` (`assigned_to`,`created_at`),
  ADD KEY `idx_converted_created` (`converted`,`created_at`),
  ADD KEY `idx_cin` (`cin`),
  ADD KEY `idx_phone` (`phone`),
  ADD KEY `idx_phone2` (`phone2`),
  ADD KEY `idx_type_id` (`type_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `crminternet_prospect_types`
--
ALTER TABLE `crminternet_prospect_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_active_pos` (`active`,`position`);

--
-- Indexes for table `crminternet_reclamations`
--
ALTER TABLE `crminternet_reclamations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_rec_reference` (`reference`),
  ADD KEY `idx_rec_audit` (`audit_status`),
  ADD KEY `idx_rec_service` (`service`),
  ADD KEY `idx_rec_tel` (`tel_adsl`),
  ADD KEY `idx_rec_cin` (`cin_client`),
  ADD KEY `idx_rec_gsm` (`gsm_client`),
  ADD KEY `idx_rec_assigned` (`assigned_to`),
  ADD KEY `idx_rec_period` (`annee`,`mois`),
  ADD KEY `idx_rec_created` (`date_creation`);

--
-- Indexes for table `crminternet_reclamation_counter`
--
ALTER TABLE `crminternet_reclamation_counter`
  ADD PRIMARY KEY (`period`);

--
-- Indexes for table `crminternet_roles`
--
ALTER TABLE `crminternet_roles`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `crminternet_role_permissions`
--
ALTER TABLE `crminternet_role_permissions`
  ADD PRIMARY KEY (`role`,`permission`);

--
-- Indexes for table `crminternet_schema_migrations`
--
ALTER TABLE `crminternet_schema_migrations`
  ADD PRIMARY KEY (`filename`);

--
-- Indexes for table `crminternet_settings`
--
ALTER TABLE `crminternet_settings`
  ADD PRIMARY KEY (`scope`,`setting_key`);

--
-- Indexes for table `crminternet_tasks`
--
ALTER TABLE `crminternet_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_assigned` (`assigned_to`,`status`),
  ADD KEY `idx_due` (`due_date`),
  ADD KEY `idx_tasks_assigned` (`assigned_to`),
  ADD KEY `idx_tasks_created` (`created_by`),
  ADD KEY `idx_tasks_status` (`status`);

--
-- Indexes for table `crminternet_teams`
--
ALTER TABLE `crminternet_teams`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_team_name` (`name`);

--
-- Indexes for table `crminternet_team_roles`
--
ALTER TABLE `crminternet_team_roles`
  ADD PRIMARY KEY (`team_id`,`role`),
  ADD KEY `idx_team_roles_team` (`team_id`),
  ADD KEY `idx_team_roles_role` (`role`);

--
-- Indexes for table `crminternet_users`
--
ALTER TABLE `crminternet_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `uniq_users_cin` (`cin`),
  ADD KEY `idx_users_company` (`company`),
  ADD KEY `idx_users_contract_end` (`contract_end`),
  ADD KEY `idx_users_guichet_entity` (`guichet_entity_id`),
  ADD KEY `idx_users_team` (`team_id`);

--
-- Indexes for table `crminternet_user_grants`
--
ALTER TABLE `crminternet_user_grants`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_username`),
  ADD KEY `idx_active` (`user_username`,`expires_at`,`revoked`);

--
-- Indexes for table `crminternet_user_permission_overrides`
--
ALTER TABLE `crminternet_user_permission_overrides`
  ADD PRIMARY KEY (`user_username`,`permission`),
  ADD KEY `idx_user` (`user_username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `crminternet_attendance`
--
ALTER TABLE `crminternet_attendance`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crminternet_audit_log`
--
ALTER TABLE `crminternet_audit_log`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crminternet_contract_info`
--
ALTER TABLE `crminternet_contract_info`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crminternet_custom_field_values`
--
ALTER TABLE `crminternet_custom_field_values`
  MODIFY `id` bigint NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `crminternet_reclamations`
--
ALTER TABLE `crminternet_reclamations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `crminternet_chat_members`
--
ALTER TABLE `crminternet_chat_members`
  ADD CONSTRAINT `fk_mem_conv` FOREIGN KEY (`conversation_id`) REFERENCES `crminternet_chat_conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crminternet_chat_messages`
--
ALTER TABLE `crminternet_chat_messages`
  ADD CONSTRAINT `fk_msg_conv` FOREIGN KEY (`conversation_id`) REFERENCES `crminternet_chat_conversations` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crminternet_commissions`
--
ALTER TABLE `crminternet_commissions`
  ADD CONSTRAINT `fk_comm_agent` FOREIGN KEY (`external_agent_id`) REFERENCES `crminternet_external_agents` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `crminternet_contracts`
--
ALTER TABLE `crminternet_contracts`
  ADD CONSTRAINT `fk_contract_opp` FOREIGN KEY (`opportunity_id`) REFERENCES `crminternet_opportunities` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `crminternet_guichet_entries`
--
ALTER TABLE `crminternet_guichet_entries`
  ADD CONSTRAINT `fk_ge_dossier` FOREIGN KEY (`dossier_id`) REFERENCES `crminternet_guichet_dossiers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crminternet_lead_actions`
--
ALTER TABLE `crminternet_lead_actions`
  ADD CONSTRAINT `fk_la_prospect` FOREIGN KEY (`prospect_id`) REFERENCES `crminternet_prospects` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `crminternet_opportunities`
--
ALTER TABLE `crminternet_opportunities`
  ADD CONSTRAINT `fk_opp_prospect` FOREIGN KEY (`prospect_id`) REFERENCES `crminternet_prospects` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
