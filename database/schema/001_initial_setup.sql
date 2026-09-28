-- ============================================================================
-- Pharma CRM & Sales Force Automation
-- Unified Single Initial Setup (Full Database Schema & Complete Seeds)
-- Generated: 2026-09-28 15:34:16
-- Description: Merged, audited, zero-dependency-failure single setup script.
-- Contains: All 68 business tables, indexes, triggers, constraints & baseline seeds.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ----------------------------------------------------------------------------
-- 1. DROP EXISTING TRIGGERS & TABLES (Clean Slate Setup)
-- ----------------------------------------------------------------------------
DROP TRIGGER IF EXISTS audit_prevent_update;
DROP TRIGGER IF EXISTS audit_prevent_delete;

DROP TABLE IF EXISTS `audit_logs`;
DROP TABLE IF EXISTS `job_queue`;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `notification_templates`;
DROP TABLE IF EXISTS `webhook_events`;
DROP TABLE IF EXISTS `webhook_sources`;
DROP TABLE IF EXISTS `pdcs`;
DROP TABLE IF EXISTS `payment_reversals`;
DROP TABLE IF EXISTS `payment_allocations`;
DROP TABLE IF EXISTS `payments`;
DROP TABLE IF EXISTS `dispatch_status_history`;
DROP TABLE IF EXISTS `dispatches`;
DROP TABLE IF EXISTS `transporters`;
DROP TABLE IF EXISTS `invoice_items`;
DROP TABLE IF EXISTS `invoices`;
DROP TABLE IF EXISTS `order_status_history`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `stock_reservations`;
DROP TABLE IF EXISTS `inventory_movements`;
DROP TABLE IF EXISTS `inventory_batches`;
DROP TABLE IF EXISTS `lead_activities`;
DROP TABLE IF EXISTS `follow_ups`;
DROP TABLE IF EXISTS `leads`;
DROP TABLE IF EXISTS `dcr_history`;
DROP TABLE IF EXISTS `dcr_visits_v2`;
DROP TABLE IF EXISTS `dcr_reports_v2`;
DROP TABLE IF EXISTS `kyc_document_history`;
DROP TABLE IF EXISTS `kyc_documents`;
DROP TABLE IF EXISTS `onboarding_history`;
DROP TABLE IF EXISTS `onboarding_registrations`;
DROP TABLE IF EXISTS `onboarding_invites`;
DROP TABLE IF EXISTS `territory_overrides`;
DROP TABLE IF EXISTS `party_product_interests`;
DROP TABLE IF EXISTS `party_territories`;
DROP TABLE IF EXISTS `parties`;
DROP TABLE IF EXISTS `scheme_rules`;
DROP TABLE IF EXISTS `schemes`;
DROP TABLE IF EXISTS `product_prices`;
DROP TABLE IF EXISTS `pricing_tiers`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `product_categories`;
DROP TABLE IF EXISTS `catalog_master_values`;
DROP TABLE IF EXISTS `system_settings`;
DROP TABLE IF EXISTS `api_idempotency_keys`;
DROP TABLE IF EXISTS `sequence_counters`;
DROP TABLE IF EXISTS `pincodes`;
DROP TABLE IF EXISTS `cities`;
DROP TABLE IF EXISTS `districts`;
DROP TABLE IF EXISTS `states`;
DROP TABLE IF EXISTS `auth_permission_catalogue`;
DROP TABLE IF EXISTS `auth_user_territories`;
DROP TABLE IF EXISTS `auth_user_hierarchy`;
DROP TABLE IF EXISTS `auth_user_roles`;
DROP TABLE IF EXISTS `auth_role_scopes`;
DROP TABLE IF EXISTS `auth_role_permissions`;
DROP TABLE IF EXISTS `auth_permissions`;
DROP TABLE IF EXISTS `auth_roles`;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `oauth_refresh_tokens`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `oauth_clients`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `franchises`;
DROP TABLE IF EXISTS `organizations`;


-- ----------------------------------------------------------------------------
-- 2. TABLE DEFINITIONS (All Modules Unified)
-- ----------------------------------------------------------------------------

-- Table: organizations
CREATE TABLE IF NOT EXISTS organizations (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_ref` varchar(24) NOT NULL,
  `org_code` varchar(32) NOT NULL,
  `org_name` varchar(191) NOT NULL,
  `status` enum('ACTIVE','SUSPENDED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  `brand_primary_hex` char(7) DEFAULT NULL,
  `brand_accent_hex` char(7) DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_org_ref` (`org_ref`),
  UNIQUE KEY `uq_org_code` (`org_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: franchises
CREATE TABLE IF NOT EXISTS franchises (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `franchise_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_code` varchar(32) NOT NULL,
  `franchise_name` varchar(191) NOT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `drug_license_no` varchar(64) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `state_ref` varchar(24) DEFAULT NULL,
  `brand_primary_hex` char(7) DEFAULT NULL,
  `brand_accent_hex` char(7) DEFAULT NULL,
  `settings_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`settings_json`)),
  `status` enum('ACTIVE','SUSPENDED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_franchise_ref` (`franchise_ref`),
  UNIQUE KEY `uq_franchise_pair` (`org_ref`,`franchise_ref`),
  UNIQUE KEY `uq_franchise_code` (`org_ref`,`franchise_code`),
  KEY `idx_franchise_status` (`org_ref`,`status`),
  CONSTRAINT `fk_frn_org` FOREIGN KEY (`org_ref`) REFERENCES `organizations` (`org_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: users
CREATE TABLE IF NOT EXISTS users (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) DEFAULT NULL,
  `tenant_key` varchar(24) GENERATED ALWAYS AS (ifnull(`franchise_ref`,'PLATFORM')) STORED,
  `role` enum('SUPER_ADMIN','FRANCHISE_ADMIN','SALES','DISTRIBUTOR') NOT NULL,
  `party_ref` varchar(24) DEFAULT NULL,
  `full_name` varchar(191) NOT NULL,
  `email` varchar(191) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ACTIVE','INACTIVE','LOCKED') NOT NULL DEFAULT 'ACTIVE',
  `last_login_at` datetime DEFAULT NULL,
  `failed_login_count` int(11) NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `employee_code` varchar(64) DEFAULT NULL,
  `reporting_manager_ref` varchar(24) DEFAULT NULL,
  `department` varchar(120) DEFAULT NULL,
  `designation` varchar(120) DEFAULT NULL,
  `assigned_region` varchar(191) DEFAULT NULL,
  `joining_date` date DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_ref` (`user_ref`),
  UNIQUE KEY `uq_user_email_tenant` (`tenant_key`,`email`),
  UNIQUE KEY `uq_user_pair` (`franchise_ref`,`user_ref`),
  UNIQUE KEY `uq_user_employee_code` (`franchise_ref`,`employee_code`),
  KEY `idx_user_tenant` (`org_ref`,`franchise_ref`,`role`,`status`),
  KEY `idx_user_manager` (`franchise_ref`,`reporting_manager_ref`),
  CONSTRAINT `fk_user_org` FOREIGN KEY (`org_ref`) REFERENCES `organizations` (`org_ref`),
  CONSTRAINT `chk_user_scope` CHECK (`role` = 'SUPER_ADMIN' and `franchise_ref` is null or `role` <> 'SUPER_ADMIN' and `franchise_ref` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: oauth_clients
CREATE TABLE IF NOT EXISTS oauth_clients (
  `client_id` varchar(32) NOT NULL,
  `surface` enum('super','admin','sales','portal') NOT NULL,
  `allowed_roles` varchar(120) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: user_sessions
CREATE TABLE IF NOT EXISTS user_sessions (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `session_ref` varchar(24) NOT NULL,
  `family_ref` varchar(24) NOT NULL,
  `user_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) DEFAULT NULL,
  `client_id` varchar(32) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `issued_at` datetime NOT NULL,
  `abs_expires_at` datetime NOT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoke_reason` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_session_ref` (`session_ref`),
  KEY `idx_session_user` (`user_ref`,`revoked_at`),
  KEY `idx_session_family` (`family_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: oauth_refresh_tokens
CREATE TABLE IF NOT EXISTS oauth_refresh_tokens (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) NOT NULL,
  `session_ref` varchar(24) NOT NULL,
  `family_ref` varchar(24) NOT NULL,
  `user_ref` varchar(24) NOT NULL,
  `status` enum('ACTIVE','USED','REVOKED') NOT NULL DEFAULT 'ACTIVE',
  `issued_at` datetime NOT NULL,
  `idle_expires_at` datetime NOT NULL,
  `abs_expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rt_hash` (`token_hash`),
  KEY `idx_rt_family` (`family_ref`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: login_attempts
CREATE TABLE IF NOT EXISTS login_attempts (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(191) NOT NULL,
  `tenant_key` varchar(24) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `outcome` enum('SUCCESS','FAIL','LOCKED') NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_la_email` (`email`,`tenant_key`,`attempted_at`),
  KEY `idx_la_ip` (`ip_address`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: password_resets
CREATE TABLE IF NOT EXISTS password_resets (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `token_hash` char(64) NOT NULL,
  `user_ref` varchar(24) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: role_permissions
CREATE TABLE IF NOT EXISTS role_permissions (
  `role` enum('SUPER_ADMIN','FRANCHISE_ADMIN','SALES','DISTRIBUTOR') NOT NULL,
  `permission_code` varchar(64) NOT NULL,
  `scope` enum('GLOBAL','TENANT','ASSIGNED','OWN') NOT NULL,
  PRIMARY KEY (`role`,`permission_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: rate_limits
CREATE TABLE IF NOT EXISTS rate_limits (
  `bucket_key` char(64) NOT NULL,
  `window_start` int(10) unsigned NOT NULL,
  `hits` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`bucket_key`,`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: auth_roles
CREATE TABLE IF NOT EXISTS auth_roles (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `role_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) DEFAULT NULL,
  `role_name` varchar(120) NOT NULL,
  `role_slug` varchar(120) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_system` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `default_scope` enum('ALL','TERRITORY','TEAM','OWN','NONE') NOT NULL DEFAULT 'NONE',
  `created_by_ref` varchar(24) NOT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_role_ref` (`role_ref`),
  UNIQUE KEY `uq_auth_role_slug` (`franchise_ref`,`role_slug`),
  KEY `idx_auth_role_tenant` (`org_ref`,`franchise_ref`,`status`),
  CONSTRAINT `fk_auth_role_org` FOREIGN KEY (`org_ref`) REFERENCES `organizations` (`org_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: auth_permissions
CREATE TABLE IF NOT EXISTS auth_permissions (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `permission_ref` varchar(24) NOT NULL,
  `module_key` varchar(64) NOT NULL,
  `action_key` varchar(64) NOT NULL,
  `permission_key` varchar(140) GENERATED ALWAYS AS (concat(`module_key`,':',`action_key`)) STORED,
  `label` varchar(160) NOT NULL,
  `is_sensitive` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_auth_permission_ref` (`permission_ref`),
  UNIQUE KEY `uq_auth_permission_key` (`module_key`,`action_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: auth_role_permissions
CREATE TABLE IF NOT EXISTS auth_role_permissions (
  `role_ref` varchar(24) NOT NULL,
  `permission_ref` varchar(24) NOT NULL,
  `granted_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_ref`,`permission_ref`),
  KEY `fk_arp_permission` (`permission_ref`),
  CONSTRAINT `fk_arp_permission` FOREIGN KEY (`permission_ref`) REFERENCES `auth_permissions` (`permission_ref`),
  CONSTRAINT `fk_arp_role` FOREIGN KEY (`role_ref`) REFERENCES `auth_roles` (`role_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: auth_role_scopes
CREATE TABLE IF NOT EXISTS auth_role_scopes (
  `role_ref` varchar(24) NOT NULL,
  `module_key` varchar(64) NOT NULL,
  `data_scope` enum('ALL','TERRITORY','TEAM','OWN','NONE') NOT NULL,
  PRIMARY KEY (`role_ref`,`module_key`),
  CONSTRAINT `fk_ars_role` FOREIGN KEY (`role_ref`) REFERENCES `auth_roles` (`role_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: auth_user_roles
CREATE TABLE IF NOT EXISTS auth_user_roles (
  `user_ref` varchar(24) NOT NULL,
  `role_ref` varchar(24) NOT NULL,
  `assigned_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_ref`,`role_ref`),
  KEY `fk_aur_role` (`role_ref`),
  CONSTRAINT `fk_aur_role` FOREIGN KEY (`role_ref`) REFERENCES `auth_roles` (`role_ref`),
  CONSTRAINT `fk_aur_user` FOREIGN KEY (`user_ref`) REFERENCES `users` (`user_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: auth_user_hierarchy
CREATE TABLE IF NOT EXISTS auth_user_hierarchy (
  `user_ref` varchar(24) NOT NULL,
  `manager_ref` varchar(24) DEFAULT NULL,
  `assigned_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_ref`),
  KEY `fk_auh_manager` (`manager_ref`),
  CONSTRAINT `fk_auh_manager` FOREIGN KEY (`manager_ref`) REFERENCES `users` (`user_ref`),
  CONSTRAINT `fk_auh_user` FOREIGN KEY (`user_ref`) REFERENCES `users` (`user_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: auth_user_territories
CREATE TABLE IF NOT EXISTS auth_user_territories (
  `user_ref` varchar(24) NOT NULL,
  `territory_ref` varchar(24) NOT NULL,
  `assigned_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_ref`,`territory_ref`),
  CONSTRAINT `fk_aut_user` FOREIGN KEY (`user_ref`) REFERENCES `users` (`user_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: auth_permission_catalogue
CREATE TABLE IF NOT EXISTS auth_permission_catalogue (
  `module_key` varchar(64) NOT NULL,
  `action_key` varchar(64) NOT NULL,
  `label` varchar(160) NOT NULL,
  `is_sensitive` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`module_key`,`action_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: states
CREATE TABLE IF NOT EXISTS states (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `state_ref` varchar(24) NOT NULL,
  `state_code` varchar(8) NOT NULL,
  `state_name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `state_ref` (`state_ref`),
  UNIQUE KEY `state_code` (`state_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: districts
CREATE TABLE IF NOT EXISTS districts (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `district_ref` varchar(24) NOT NULL,
  `state_ref` varchar(24) NOT NULL,
  `district_name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `district_ref` (`district_ref`),
  KEY `idx_d_state` (`state_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: cities
CREATE TABLE IF NOT EXISTS cities (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `city_ref` varchar(24) NOT NULL,
  `district_ref` varchar(24) NOT NULL,
  `city_name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `city_ref` (`city_ref`),
  KEY `idx_c_district` (`district_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: pincodes
CREATE TABLE IF NOT EXISTS pincodes (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pincode` char(6) NOT NULL,
  `city_ref` varchar(24) DEFAULT NULL,
  `district_ref` varchar(24) NOT NULL,
  `state_ref` varchar(24) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pincode` (`pincode`),
  KEY `idx_p_district` (`district_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: sequence_counters
CREATE TABLE IF NOT EXISTS sequence_counters (
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `counter_key` varchar(32) NOT NULL,
  `period_key` varchar(8) NOT NULL,
  `last_value` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`franchise_ref`,`counter_key`,`period_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: api_idempotency_keys
CREATE TABLE IF NOT EXISTS api_idempotency_keys (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `idempotency_key` varchar(120) NOT NULL,
  `method` varchar(8) NOT NULL,
  `path` varchar(191) NOT NULL,
  `request_hash` char(64) NOT NULL,
  `response_status` smallint(6) DEFAULT NULL,
  `response_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`response_json`)),
  `status` enum('IN_PROGRESS','COMPLETED','FAILED') NOT NULL DEFAULT 'IN_PROGRESS',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `completed_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_idem` (`franchise_ref`,`idempotency_key`),
  KEY `idx_idem_exp` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: system_settings
CREATE TABLE IF NOT EXISTS system_settings (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `setting_key` varchar(64) NOT NULL,
  `setting_value` varchar(500) NOT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting` (`franchise_ref`,`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: catalog_master_values
CREATE TABLE IF NOT EXISTS catalog_master_values (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `master_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `master_key` varchar(64) NOT NULL,
  `name` varchar(191) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_catalog_master_ref` (`franchise_ref`,`master_ref`),
  UNIQUE KEY `uq_catalog_master_name` (`franchise_ref`,`master_key`,`name`),
  KEY `idx_catalog_master_list` (`franchise_ref`,`master_key`,`status`,`name`),
  KEY `fk_catalog_master_org` (`org_ref`),
  CONSTRAINT `fk_catalog_master_org` FOREIGN KEY (`org_ref`) REFERENCES `organizations` (`org_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: product_categories
CREATE TABLE IF NOT EXISTS product_categories (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `category_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `category_name` varchar(120) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cat_ref` (`franchise_ref`,`category_ref`),
  UNIQUE KEY `uq_cat_name` (`franchise_ref`,`category_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: products
CREATE TABLE IF NOT EXISTS products (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `sku` varchar(64) NOT NULL,
  `product_name` varchar(191) NOT NULL,
  `category_ref` varchar(24) DEFAULT NULL,
  `composition` varchar(255) DEFAULT NULL,
  `pack_size` varchar(64) DEFAULT NULL,
  `dosage_form` varchar(64) DEFAULT NULL,
  `mrp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `pts` decimal(18,2) NOT NULL DEFAULT 0.00,
  `franchise_rate` decimal(18,2) NOT NULL DEFAULT 0.00,
  `gst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `hsn_code` varchar(16) DEFAULT NULL,
  `shelf_life_days` int(11) NOT NULL DEFAULT 0,
  `storage_requirement` varchar(120) DEFAULT NULL,
  `scheme_eligible` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `description` varchar(500) DEFAULT NULL,
  `availability` enum('IN_STOCK','LOW_STOCK','OUT_OF_STOCK') NOT NULL DEFAULT 'IN_STOCK',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_ref` (`franchise_ref`,`product_ref`),
  UNIQUE KEY `uq_product_sku` (`franchise_ref`,`sku`),
  KEY `idx_product_list` (`franchise_ref`,`status`,`product_name`),
  KEY `fk_prod_cat` (`franchise_ref`,`category_ref`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`franchise_ref`, `category_ref`) REFERENCES `product_categories` (`franchise_ref`, `category_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: pricing_tiers
CREATE TABLE IF NOT EXISTS pricing_tiers (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tier_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `tier_name` varchar(80) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tier_ref` (`franchise_ref`,`tier_ref`),
  UNIQUE KEY `uq_tier_name` (`franchise_ref`,`tier_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: product_prices
CREATE TABLE IF NOT EXISTS product_prices (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `price_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `product_ref` varchar(24) NOT NULL,
  `tier_ref` varchar(24) DEFAULT NULL,
  `party_ref` varchar(24) DEFAULT NULL,
  `rate` decimal(18,2) NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 100,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `mrp` decimal(18,2) NOT NULL DEFAULT 0.00,
  `pts` decimal(18,2) NOT NULL DEFAULT 0.00,
  `net_rate` decimal(18,2) NOT NULL DEFAULT 0.00,
  `override_reason` varchar(500) DEFAULT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_price_ref` (`franchise_ref`,`price_ref`),
  KEY `idx_price_lookup` (`franchise_ref`,`product_ref`,`status`,`effective_from`,`effective_to`),
  CONSTRAINT `fk_pp_prod` FOREIGN KEY (`franchise_ref`, `product_ref`) REFERENCES `products` (`franchise_ref`, `product_ref`),
  CONSTRAINT `chk_pp_target` CHECK (`tier_ref` is null or `party_ref` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: schemes
CREATE TABLE IF NOT EXISTS schemes (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `scheme_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `scheme_name` varchar(191) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 100,
  `stacking_allowed` tinyint(1) NOT NULL DEFAULT 0,
  `tier_ref` varchar(24) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `scheme_type` varchar(80) DEFAULT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_scheme_ref` (`franchise_ref`,`scheme_ref`),
  KEY `idx_scheme_active` (`franchise_ref`,`status`,`start_date`,`end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: scheme_rules
CREATE TABLE IF NOT EXISTS scheme_rules (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rule_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `scheme_ref` varchar(24) NOT NULL,
  `product_ref` varchar(24) NOT NULL,
  `min_qty` int(11) NOT NULL,
  `max_qty` int(11) DEFAULT NULL,
  `free_qty` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rule_ref` (`franchise_ref`,`rule_ref`),
  KEY `idx_rule_lookup` (`franchise_ref`,`product_ref`,`scheme_ref`),
  KEY `fk_sr_scheme` (`franchise_ref`,`scheme_ref`),
  CONSTRAINT `fk_sr_prod` FOREIGN KEY (`franchise_ref`, `product_ref`) REFERENCES `products` (`franchise_ref`, `product_ref`),
  CONSTRAINT `fk_sr_scheme` FOREIGN KEY (`franchise_ref`, `scheme_ref`) REFERENCES `schemes` (`franchise_ref`, `scheme_ref`),
  CONSTRAINT `chk_sr_qty` CHECK (`min_qty` > 0 and `free_qty` > 0 and (`max_qty` is null or `max_qty` >= `min_qty`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: parties
CREATE TABLE IF NOT EXISTS parties (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `party_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `party_code` varchar(32) NOT NULL,
  `firm_name` varchar(191) NOT NULL,
  `contact_name` varchar(191) DEFAULT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(191) DEFAULT NULL,
  `gstin` varchar(15) DEFAULT NULL,
  `drug_license_no` varchar(64) DEFAULT NULL,
  `billing_address` text DEFAULT NULL,
  `shipping_address` text DEFAULT NULL,
  `state_ref` varchar(24) DEFAULT NULL,
  `district_ref` varchar(24) DEFAULT NULL,
  `city_ref` varchar(24) DEFAULT NULL,
  `pincode` char(6) DEFAULT NULL,
  `tier_ref` varchar(24) DEFAULT NULL,
  `sales_user_ref` varchar(24) DEFAULT NULL,
  `agreement_from` date DEFAULT NULL,
  `agreement_to` date DEFAULT NULL,
  `credit_limit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `payment_terms_days` int(11) NOT NULL DEFAULT 0,
  `opening_outstanding` decimal(18,2) NOT NULL DEFAULT 0.00,
  `converted_from_lead_ref` varchar(24) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `whatsapp` varchar(20) DEFAULT NULL,
  `party_type` varchar(80) DEFAULT NULL,
  `drug_license_validity` date DEFAULT NULL,
  `area` varchar(120) DEFAULT NULL,
  `remarks` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_party_ref` (`franchise_ref`,`party_ref`),
  UNIQUE KEY `uq_party_code` (`franchise_ref`,`party_code`),
  KEY `idx_party_list` (`franchise_ref`,`status`,`firm_name`),
  KEY `idx_party_mobile` (`franchise_ref`,`mobile`),
  KEY `idx_party_gstin` (`franchise_ref`,`gstin`),
  KEY `idx_party_sales` (`franchise_ref`,`sales_user_ref`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: party_territories
CREATE TABLE IF NOT EXISTS party_territories (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `territory_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `party_ref` varchar(24) NOT NULL,
  `level` enum('PINCODE','DISTRICT') NOT NULL,
  `pincode` char(6) DEFAULT NULL,
  `district_ref` varchar(24) DEFAULT NULL,
  `effective_from` date NOT NULL,
  `effective_to` date DEFAULT NULL,
  `is_exclusive` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_terr_ref` (`franchise_ref`,`territory_ref`),
  KEY `idx_terr_party` (`franchise_ref`,`party_ref`,`status`,`effective_from`,`effective_to`),
  KEY `idx_terr_pin` (`franchise_ref`,`pincode`,`status`),
  KEY `idx_terr_dist` (`franchise_ref`,`district_ref`,`status`),
  CONSTRAINT `fk_pt_party` FOREIGN KEY (`franchise_ref`, `party_ref`) REFERENCES `parties` (`franchise_ref`, `party_ref`),
  CONSTRAINT `chk_pt_level` CHECK (`level` = 'PINCODE' and `pincode` is not null or `level` = 'DISTRICT' and `district_ref` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: party_product_interests
CREATE TABLE IF NOT EXISTS party_product_interests (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `party_ref` varchar(24) NOT NULL,
  `product_ref` varchar(24) NOT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_party_product_interest` (`franchise_ref`,`party_ref`,`product_ref`),
  KEY `idx_party_interest` (`franchise_ref`,`party_ref`),
  KEY `fk_ppi_product` (`franchise_ref`,`product_ref`),
  CONSTRAINT `fk_ppi_party` FOREIGN KEY (`franchise_ref`, `party_ref`) REFERENCES `parties` (`franchise_ref`, `party_ref`),
  CONSTRAINT `fk_ppi_product` FOREIGN KEY (`franchise_ref`, `product_ref`) REFERENCES `products` (`franchise_ref`, `product_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: territory_overrides
CREATE TABLE IF NOT EXISTS territory_overrides (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `override_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `order_ref` varchar(24) NOT NULL,
  `party_ref` varchar(24) NOT NULL,
  `pincode` char(6) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `approved_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ovr_ref` (`franchise_ref`,`override_ref`),
  KEY `idx_ovr_order` (`franchise_ref`,`order_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: onboarding_invites
CREATE TABLE IF NOT EXISTS onboarding_invites (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invite_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `lead_ref` varchar(24) DEFAULT NULL,
  `assigned_user_ref` varchar(24) DEFAULT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_invite_ref` (`franchise_ref`,`invite_ref`),
  UNIQUE KEY `uq_invite_hash` (`token_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: onboarding_registrations
CREATE TABLE IF NOT EXISTS onboarding_registrations (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `onboarding_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `invite_ref` varchar(24) NOT NULL,
  `lead_ref` varchar(24) DEFAULT NULL,
  `party_ref` varchar(24) DEFAULT NULL,
  `assigned_user_ref` varchar(24) DEFAULT NULL,
  `status` enum('SUBMITTED','INFO_REQUESTED','APPROVED','REJECTED') NOT NULL DEFAULT 'SUBMITTED',
  `firm_name` varchar(255) NOT NULL,
  `constitution_type` varchar(32) NOT NULL,
  `contact_name` varchar(255) NOT NULL,
  `designation` varchar(128) NOT NULL,
  `mobile` varchar(32) NOT NULL,
  `email` varchar(255) NOT NULL,
  `gstin` varchar(32) NOT NULL,
  `drug_license_no` varchar(128) NOT NULL,
  `drug_license_validity` date NOT NULL,
  `pan` varchar(32) NOT NULL,
  `billing_address` text NOT NULL,
  `shipping_address` text NOT NULL,
  `state_ref` varchar(24) DEFAULT NULL,
  `district_ref` varchar(24) DEFAULT NULL,
  `city_ref` varchar(24) DEFAULT NULL,
  `area` varchar(255) DEFAULT NULL,
  `pincode` varchar(16) NOT NULL,
  `bank_account_number` varchar(128) DEFAULT NULL,
  `bank_ifsc` varchar(32) DEFAULT NULL,
  `bank_name` varchar(255) DEFAULT NULL,
  `preferred_product_categories_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`preferred_product_categories_json`)),
  `expected_monthly_business` decimal(14,2) DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `reviewer_remarks` text DEFAULT NULL,
  `reviewed_by_ref` varchar(24) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_onboarding_ref` (`onboarding_ref`),
  UNIQUE KEY `uq_onboarding_invite` (`invite_ref`),
  KEY `idx_onboarding_tenant_status` (`franchise_ref`,`status`),
  KEY `idx_onboarding_assignee` (`franchise_ref`,`assigned_user_ref`),
  KEY `idx_onboarding_party` (`franchise_ref`,`party_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: onboarding_history
CREATE TABLE IF NOT EXISTS onboarding_history (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `onboarding_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `from_status` varchar(32) DEFAULT NULL,
  `to_status` varchar(32) NOT NULL,
  `actor_ref` varchar(24) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_onboarding_history` (`franchise_ref`,`onboarding_ref`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: kyc_documents
CREATE TABLE IF NOT EXISTS kyc_documents (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kyc_document_ref` varchar(24) NOT NULL,
  `onboarding_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `document_type` enum('DRUG_LICENCE','GST_CERTIFICATE','PAN','CANCELLED_CHEQUE','INCORPORATION_CERTIFICATE') NOT NULL,
  `file_reference` varchar(512) NOT NULL,
  `original_filename` varchar(255) NOT NULL,
  `status` enum('PENDING','VERIFIED','REJECTED') NOT NULL DEFAULT 'PENDING',
  `verification_remarks` text DEFAULT NULL,
  `uploaded_by_ref` varchar(24) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `verified_by_ref` varchar(24) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_kyc_document_ref` (`kyc_document_ref`),
  UNIQUE KEY `uq_kyc_document_type` (`onboarding_ref`,`document_type`),
  KEY `idx_kyc_document_tenant` (`franchise_ref`,`onboarding_ref`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: kyc_document_history
CREATE TABLE IF NOT EXISTS kyc_document_history (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `kyc_document_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `from_status` varchar(32) DEFAULT NULL,
  `to_status` varchar(32) NOT NULL,
  `actor_ref` varchar(24) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_kyc_history` (`franchise_ref`,`kyc_document_ref`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: dcr_reports_v2
CREATE TABLE IF NOT EXISTS dcr_reports_v2 (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dcr_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `distributor_party_ref` varchar(24) NOT NULL,
  `owner_user_ref` varchar(24) NOT NULL,
  `report_date` date NOT NULL,
  `work_type` enum('FIELD_WORK','LEAVE','HOLIDAY','MEETING','TRAINING') NOT NULL,
  `beat` varchar(255) NOT NULL,
  `status` enum('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  `submitted_at` datetime DEFAULT NULL,
  `reviewed_by_ref` varchar(24) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `reviewer_remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dcr_ref` (`dcr_ref`),
  UNIQUE KEY `uq_dcr_owner_date` (`owner_user_ref`,`report_date`),
  KEY `idx_dcr_tenant_owner` (`franchise_ref`,`distributor_party_ref`,`owner_user_ref`,`report_date`),
  KEY `idx_dcr_tenant_status` (`franchise_ref`,`status`,`report_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: dcr_visits_v2
CREATE TABLE IF NOT EXISTS dcr_visits_v2 (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visit_ref` varchar(24) NOT NULL,
  `dcr_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `party_ref` varchar(24) DEFAULT NULL,
  `lead_ref` varchar(24) DEFAULT NULL,
  `customer_type` varchar(32) NOT NULL,
  `visit_time` time NOT NULL,
  `visit_purpose` varchar(255) NOT NULL,
  `products_promoted_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`products_promoted_json`)),
  `samples_given_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`samples_given_json`)),
  `pob_product_ref` varchar(24) DEFAULT NULL,
  `pob_quantity` int(10) unsigned DEFAULT NULL,
  `pob_value` decimal(14,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `next_visit_date` date DEFAULT NULL,
  `photo_file_reference` varchar(512) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dcr_visit_ref` (`visit_ref`),
  KEY `idx_dcr_visits_report` (`franchise_ref`,`dcr_ref`),
  CONSTRAINT `CONSTRAINT_1` CHECK (`party_ref` is not null <> (`lead_ref` is not null))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: dcr_history
CREATE TABLE IF NOT EXISTS dcr_history (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dcr_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `from_status` varchar(32) DEFAULT NULL,
  `to_status` varchar(32) NOT NULL,
  `actor_ref` varchar(24) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dcr_history` (`franchise_ref`,`dcr_ref`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: leads
CREATE TABLE IF NOT EXISTS leads (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `lead_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `external_source_ref` varchar(24) DEFAULT NULL,
  `external_lead_id` varchar(120) DEFAULT NULL,
  `source_key` varchar(24) GENERATED ALWAYS AS (ifnull(`external_source_ref`,'MANUAL')) STORED,
  `ext_key` varchar(120) GENERATED ALWAYS AS (if(`external_lead_id` is null,concat('~',`lead_ref`),`external_lead_id`)) STORED,
  `contact_name` varchar(191) NOT NULL,
  `firm_name` varchar(191) DEFAULT NULL,
  `mobile` varchar(20) NOT NULL,
  `mobile_norm` varchar(15) NOT NULL,
  `email` varchar(191) DEFAULT NULL,
  `state_ref` varchar(24) DEFAULT NULL,
  `district_ref` varchar(24) DEFAULT NULL,
  `city_ref` varchar(24) DEFAULT NULL,
  `pincode` char(6) DEFAULT NULL,
  `lead_source` varchar(80) DEFAULT NULL,
  `business_type` varchar(80) DEFAULT NULL,
  `interested_products` text DEFAULT NULL,
  `assigned_user_ref` varchar(24) DEFAULT NULL,
  `priority` enum('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  `status` enum('NEW','ASSIGNED','CONTACTED','INTERESTED','FOLLOW_UP','DOCUMENTS_PENDING','QUALIFIED','CONVERTED','LOST','REJECTED','ARCHIVED') NOT NULL DEFAULT 'NEW',
  `initial_remark` text DEFAULT NULL,
  `first_response_at` datetime DEFAULT NULL,
  `next_follow_up_at` datetime DEFAULT NULL,
  `converted_party_ref` varchar(24) DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_lead_ref` (`franchise_ref`,`lead_ref`),
  UNIQUE KEY `uq_lead_ext` (`franchise_ref`,`source_key`,`ext_key`),
  KEY `idx_lead_assign` (`franchise_ref`,`assigned_user_ref`,`status`,`next_follow_up_at`),
  KEY `idx_lead_mobile` (`franchise_ref`,`mobile_norm`),
  KEY `idx_lead_status` (`franchise_ref`,`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: follow_ups
CREATE TABLE IF NOT EXISTS follow_ups (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `followup_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `lead_ref` varchar(24) DEFAULT NULL,
  `party_ref` varchar(24) DEFAULT NULL,
  `assigned_user_ref` varchar(24) NOT NULL,
  `activity_type` enum('CALL','VISIT','WHATSAPP','EMAIL','MEETING','OTHER') NOT NULL,
  `next_action` varchar(191) NOT NULL,
  `next_follow_up_at` datetime NOT NULL,
  `status` enum('PENDING','COMPLETED','MISSED','RESCHEDULED','CLOSED') NOT NULL DEFAULT 'PENDING',
  `remark` text DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fu_ref` (`franchise_ref`,`followup_ref`),
  KEY `idx_fu_queue` (`franchise_ref`,`assigned_user_ref`,`status`,`next_follow_up_at`),
  CONSTRAINT `chk_fu_target` CHECK (`lead_ref` is not null or `party_ref` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: lead_activities
CREATE TABLE IF NOT EXISTS lead_activities (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `activity_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `lead_ref` varchar(24) NOT NULL,
  `user_ref` varchar(24) NOT NULL,
  `activity_type` varchar(40) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) DEFAULT NULL,
  `activity_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_act_ref` (`franchise_ref`,`activity_ref`),
  KEY `idx_act_lead` (`franchise_ref`,`lead_ref`,`created_at`),
  CONSTRAINT `fk_la_lead` FOREIGN KEY (`franchise_ref`, `lead_ref`) REFERENCES `leads` (`franchise_ref`, `lead_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: inventory_batches
CREATE TABLE IF NOT EXISTS inventory_batches (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `batch_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `product_ref` varchar(24) NOT NULL,
  `batch_no` varchar(64) NOT NULL,
  `manufacturing_date` date DEFAULT NULL,
  `expiry_date` date NOT NULL,
  `received_qty` int(11) NOT NULL DEFAULT 0,
  `on_hand_qty` int(11) NOT NULL DEFAULT 0,
  `reserved_qty` int(11) NOT NULL DEFAULT 0,
  `damaged_qty` int(11) NOT NULL DEFAULT 0,
  `location_code` varchar(40) DEFAULT NULL,
  `status` enum('SALEABLE','QUARANTINE','RECALLED','EXPIRED','DAMAGED') NOT NULL DEFAULT 'SALEABLE',
  `version` int(11) NOT NULL DEFAULT 1,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_batch_ref` (`franchise_ref`,`batch_ref`),
  UNIQUE KEY `uq_batch_no` (`franchise_ref`,`product_ref`,`batch_no`),
  KEY `idx_batch_fefo` (`franchise_ref`,`product_ref`,`status`,`expiry_date`),
  CONSTRAINT `fk_ib_prod` FOREIGN KEY (`franchise_ref`, `product_ref`) REFERENCES `products` (`franchise_ref`, `product_ref`),
  CONSTRAINT `chk_ib_qty` CHECK (`on_hand_qty` >= 0 and `reserved_qty` >= 0 and `reserved_qty` <= `on_hand_qty`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: inventory_movements
CREATE TABLE IF NOT EXISTS inventory_movements (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `movement_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `batch_ref` varchar(24) NOT NULL,
  `movement_type` enum('RECEIPT','SALE','RESERVE','RELEASE','RETURN','DAMAGE','EXPIRY','ADJUST','TRANSFER') NOT NULL,
  `qty` int(11) NOT NULL,
  `reference_type` varchar(40) DEFAULT NULL,
  `reference_ref` varchar(24) DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mov_ref` (`franchise_ref`,`movement_ref`),
  KEY `idx_mov_batch` (`franchise_ref`,`batch_ref`,`created_at`),
  CONSTRAINT `fk_im_batch` FOREIGN KEY (`franchise_ref`, `batch_ref`) REFERENCES `inventory_batches` (`franchise_ref`, `batch_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: stock_reservations
CREATE TABLE IF NOT EXISTS stock_reservations (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reservation_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `batch_ref` varchar(24) NOT NULL,
  `order_ref` varchar(24) NOT NULL,
  `order_item_ref` varchar(24) NOT NULL,
  `reserved_qty` int(11) NOT NULL,
  `status` enum('ACTIVE','RELEASED','CONSUMED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_res_ref` (`franchise_ref`,`reservation_ref`),
  KEY `idx_res_order` (`franchise_ref`,`order_ref`,`status`),
  KEY `idx_res_batch` (`franchise_ref`,`batch_ref`,`status`),
  CONSTRAINT `fk_sr_batch` FOREIGN KEY (`franchise_ref`, `batch_ref`) REFERENCES `inventory_batches` (`franchise_ref`, `batch_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: orders
CREATE TABLE IF NOT EXISTS orders (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `order_ref` varchar(24) NOT NULL,
  `order_no` varchar(32) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `client_order_ref` varchar(64) NOT NULL,
  `party_ref` varchar(24) NOT NULL,
  `sales_user_ref` varchar(24) DEFAULT NULL,
  `channel` enum('PORTAL','SALES','ADMIN') NOT NULL,
  `order_date` date NOT NULL,
  `shipping_address` text DEFAULT NULL,
  `shipping_pincode` char(6) DEFAULT NULL,
  `status` enum('DRAFT','SUBMITTED','CONFIRMED','PROCESSING','DISPATCHED','DELIVERED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  `territory_status` enum('OK','OVERRIDDEN','BLOCKED','UNASSIGNED','CONFLICT') NOT NULL DEFAULT 'OK',
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `gst_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `grand_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `version` int(11) NOT NULL DEFAULT 1,
  `remarks` varchar(255) DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `updated_by_ref` varchar(24) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `billing_address` text DEFAULT NULL,
  `pricing_tier_ref` varchar(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_order_ref` (`franchise_ref`,`order_ref`),
  UNIQUE KEY `uq_order_client` (`franchise_ref`,`client_order_ref`),
  UNIQUE KEY `uq_order_no` (`franchise_ref`,`order_no`),
  KEY `idx_order_status` (`franchise_ref`,`status`,`order_date`),
  KEY `idx_order_party` (`franchise_ref`,`party_ref`,`created_at`),
  CONSTRAINT `fk_ord_party` FOREIGN KEY (`franchise_ref`, `party_ref`) REFERENCES `parties` (`franchise_ref`, `party_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: order_items
CREATE TABLE IF NOT EXISTS order_items (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `order_ref` varchar(24) NOT NULL,
  `product_ref` varchar(24) NOT NULL,
  `paid_qty` int(11) NOT NULL,
  `free_qty` int(11) NOT NULL DEFAULT 0,
  `rate` decimal(18,2) NOT NULL,
  `rate_source` enum('PARTY','TIER','DEFAULT','OVERRIDE') NOT NULL,
  `price_ref` varchar(24) DEFAULT NULL,
  `discount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `gst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(18,2) NOT NULL,
  `scheme_ref` varchar(24) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_oi_ref` (`franchise_ref`,`item_ref`),
  KEY `idx_oi_order` (`franchise_ref`,`order_ref`),
  KEY `idx_oi_product` (`franchise_ref`,`product_ref`),
  CONSTRAINT `fk_oi_order` FOREIGN KEY (`franchise_ref`, `order_ref`) REFERENCES `orders` (`franchise_ref`, `order_ref`),
  CONSTRAINT `fk_oi_prod` FOREIGN KEY (`franchise_ref`, `product_ref`) REFERENCES `products` (`franchise_ref`, `product_ref`),
  CONSTRAINT `chk_oi_qty` CHECK (`paid_qty` > 0 and `free_qty` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: order_status_history
CREATE TABLE IF NOT EXISTS order_status_history (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `order_ref` varchar(24) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) NOT NULL,
  `actor_ref` varchar(24) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_osh` (`franchise_ref`,`order_ref`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: invoices
CREATE TABLE IF NOT EXISTS invoices (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_ref` varchar(24) NOT NULL,
  `invoice_no` varchar(32) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `order_ref` varchar(24) NOT NULL,
  `party_ref` varchar(24) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date DEFAULT NULL,
  `supplier_state_ref` varchar(24) DEFAULT NULL,
  `place_of_supply_state_ref` varchar(24) DEFAULT NULL,
  `bill_to_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`bill_to_snapshot`)),
  `ship_to_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`ship_to_snapshot`)),
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `discount_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `taxable_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `cgst_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sgst_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `igst_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `gst_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `rounding_adjustment` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_policy_code` varchar(40) DEFAULT NULL,
  `grand_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `paid_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` enum('POSTED','CANCELLED') NOT NULL DEFAULT 'POSTED',
  `cancel_reason` varchar(255) DEFAULT NULL,
  `cancelled_by_ref` varchar(24) DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inv_ref` (`franchise_ref`,`invoice_ref`),
  UNIQUE KEY `uq_inv_no` (`franchise_ref`,`invoice_no`),
  UNIQUE KEY `uq_inv_order` (`franchise_ref`,`order_ref`),
  KEY `idx_inv_party` (`franchise_ref`,`party_ref`,`invoice_date`),
  KEY `idx_inv_order` (`franchise_ref`,`order_ref`),
  KEY `idx_inv_status` (`franchise_ref`,`status`,`invoice_date`),
  KEY `idx_inv_due_open` (`franchise_ref`,`status`,`due_date`),
  CONSTRAINT `fk_inv_order` FOREIGN KEY (`franchise_ref`, `order_ref`) REFERENCES `orders` (`franchise_ref`, `order_ref`),
  CONSTRAINT `chk_inv_paid` CHECK (`paid_total` >= 0 and `paid_total` <= `grand_total`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: invoice_items
CREATE TABLE IF NOT EXISTS invoice_items (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `item_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `invoice_ref` varchar(24) NOT NULL,
  `order_item_ref` varchar(24) NOT NULL,
  `product_ref` varchar(24) NOT NULL,
  `product_name_snapshot` varchar(191) NOT NULL,
  `sku_snapshot` varchar(64) NOT NULL,
  `hsn_snapshot` varchar(16) DEFAULT NULL,
  `batch_ref` varchar(24) NOT NULL,
  `batch_no_snapshot` varchar(64) NOT NULL,
  `expiry_snapshot` date NOT NULL,
  `paid_qty` int(11) NOT NULL,
  `free_qty` int(11) NOT NULL DEFAULT 0,
  `rate` decimal(18,2) NOT NULL,
  `discount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `taxable_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `gst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `sgst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `igst_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `cgst_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `sgst_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `igst_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_tax` decimal(18,2) NOT NULL DEFAULT 0.00,
  `line_total` decimal(18,2) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ii_ref` (`franchise_ref`,`item_ref`),
  KEY `idx_ii_invoice` (`franchise_ref`,`invoice_ref`),
  CONSTRAINT `fk_ii_inv` FOREIGN KEY (`franchise_ref`, `invoice_ref`) REFERENCES `invoices` (`franchise_ref`, `invoice_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: transporters
CREATE TABLE IF NOT EXISTS transporters (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transporter_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `transporter_name` varchar(191) NOT NULL,
  `tracking_url_template` varchar(255) DEFAULT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tr_ref` (`franchise_ref`,`transporter_ref`),
  UNIQUE KEY `uq_tr_name` (`franchise_ref`,`transporter_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: dispatches
CREATE TABLE IF NOT EXISTS dispatches (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `dispatch_ref` varchar(24) NOT NULL,
  `dispatch_no` varchar(32) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `invoice_ref` varchar(24) NOT NULL,
  `transporter_ref` varchar(24) DEFAULT NULL,
  `lr_number` varchar(64) DEFAULT NULL,
  `tracking_url` varchar(255) DEFAULT NULL,
  `dispatch_date` date DEFAULT NULL,
  `delivered_at` datetime DEFAULT NULL,
  `boxes` int(11) NOT NULL DEFAULT 0,
  `status` enum('PENDING','PACKING','READY','DISPATCHED','IN_TRANSIT','DELIVERED','FAILED','RETURNED') NOT NULL DEFAULT 'PENDING',
  `remarks` varchar(255) DEFAULT NULL,
  `delivery_remarks` varchar(255) DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_dsp_ref` (`franchise_ref`,`dispatch_ref`),
  UNIQUE KEY `uq_dsp_no` (`franchise_ref`,`dispatch_no`),
  KEY `idx_dsp_inv` (`franchise_ref`,`invoice_ref`),
  CONSTRAINT `fk_dsp_inv` FOREIGN KEY (`franchise_ref`, `invoice_ref`) REFERENCES `invoices` (`franchise_ref`, `invoice_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: dispatch_status_history
CREATE TABLE IF NOT EXISTS dispatch_status_history (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `dispatch_ref` varchar(24) NOT NULL,
  `from_status` varchar(24) DEFAULT NULL,
  `to_status` varchar(24) NOT NULL,
  `actor_ref` varchar(24) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_dsh_dispatch` (`franchise_ref`,`dispatch_ref`,`created_at`),
  CONSTRAINT `fk_dsh_dispatch` FOREIGN KEY (`franchise_ref`, `dispatch_ref`) REFERENCES `dispatches` (`franchise_ref`, `dispatch_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: payments
CREATE TABLE IF NOT EXISTS payments (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_ref` varchar(24) NOT NULL,
  `payment_no` varchar(32) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `party_ref` varchar(24) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `allocated_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `mode` enum('CASH','UPI','NEFT','RTGS','CHEQUE','PDC','OTHER') NOT NULL,
  `reference_no` varchar(80) DEFAULT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `cheque_number` varchar(80) DEFAULT NULL,
  `cheque_date` date DEFAULT NULL,
  `pdc_due_date` date DEFAULT NULL,
  `remarks` varchar(255) DEFAULT NULL,
  `status` enum('RECORDED','PARTIALLY_ALLOCATED','ALLOCATED','REVERSED','CANCELLED') NOT NULL DEFAULT 'RECORDED',
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pay_ref` (`franchise_ref`,`payment_ref`),
  UNIQUE KEY `uq_pay_no` (`franchise_ref`,`payment_no`),
  KEY `idx_pay_party` (`franchise_ref`,`party_ref`,`payment_date`),
  KEY `idx_pay_status` (`franchise_ref`,`status`,`payment_date`),
  CONSTRAINT `fk_pay_party` FOREIGN KEY (`franchise_ref`, `party_ref`) REFERENCES `parties` (`franchise_ref`, `party_ref`),
  CONSTRAINT `chk_pay_amt` CHECK (`amount` > 0 and `allocated_amount` >= 0 and `allocated_amount` <= `amount`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: payment_allocations
CREATE TABLE IF NOT EXISTS payment_allocations (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `allocation_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `payment_ref` varchar(24) NOT NULL,
  `invoice_ref` varchar(24) NOT NULL,
  `allocated_amount` decimal(18,2) NOT NULL,
  `status` enum('ACTIVE','REVERSED') NOT NULL DEFAULT 'ACTIVE',
  `reversed_at` datetime DEFAULT NULL,
  `reversed_by_ref` varchar(24) DEFAULT NULL,
  `reversal_reason` varchar(255) DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_alloc_ref` (`franchise_ref`,`allocation_ref`),
  KEY `idx_alloc_pay` (`franchise_ref`,`payment_ref`),
  KEY `idx_alloc_inv` (`franchise_ref`,`invoice_ref`),
  CONSTRAINT `fk_al_inv` FOREIGN KEY (`franchise_ref`, `invoice_ref`) REFERENCES `invoices` (`franchise_ref`, `invoice_ref`),
  CONSTRAINT `fk_al_pay` FOREIGN KEY (`franchise_ref`, `payment_ref`) REFERENCES `payments` (`franchise_ref`, `payment_ref`),
  CONSTRAINT `chk_al_amt` CHECK (`allocated_amount` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: payment_reversals
CREATE TABLE IF NOT EXISTS payment_reversals (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `reversal_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `payment_ref` varchar(24) NOT NULL,
  `reason` varchar(255) NOT NULL,
  `idempotency_key` varchar(160) NOT NULL,
  `reversed_by_ref` varchar(24) NOT NULL,
  `reversed_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pr_ref` (`franchise_ref`,`reversal_ref`),
  UNIQUE KEY `uq_pr_payment` (`franchise_ref`,`payment_ref`),
  UNIQUE KEY `uq_pr_idem` (`franchise_ref`,`idempotency_key`),
  KEY `idx_pr_payment` (`franchise_ref`,`payment_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: pdcs
CREATE TABLE IF NOT EXISTS pdcs (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pdc_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `party_ref` varchar(24) NOT NULL,
  `amount` decimal(18,2) NOT NULL,
  `cheque_number` varchar(80) DEFAULT NULL,
  `bank_name` varchar(120) DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('PENDING','REALIZED','BOUNCED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `payment_ref` varchar(24) DEFAULT NULL,
  `bounce_reason` varchar(255) DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL,
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `realized_by_ref` varchar(24) DEFAULT NULL,
  `realized_at` datetime DEFAULT NULL,
  `bounced_by_ref` varchar(24) DEFAULT NULL,
  `cancelled_by_ref` varchar(24) DEFAULT NULL,
  `bounced_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pdc_ref` (`franchise_ref`,`pdc_ref`),
  UNIQUE KEY `uq_pdc_payment` (`franchise_ref`,`payment_ref`),
  KEY `idx_pdc_party` (`franchise_ref`,`party_ref`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: webhook_sources
CREATE TABLE IF NOT EXISTS webhook_sources (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `source_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `source_name` varchar(80) NOT NULL,
  `endpoint_slug` varchar(80) NOT NULL,
  `auth_type` enum('HMAC','BEARER') NOT NULL DEFAULT 'HMAC',
  `secret_enc` varbinary(512) NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_by_ref` varchar(24) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ws_ref` (`franchise_ref`,`source_ref`),
  UNIQUE KEY `uq_ws_slug` (`endpoint_slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: webhook_events
CREATE TABLE IF NOT EXISTS webhook_events (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `event_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `source_ref` varchar(24) NOT NULL,
  `external_event_id` varchar(120) NOT NULL,
  `payload_hash` char(64) NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `signature` varchar(128) DEFAULT NULL,
  `status` enum('RECEIVED','PROCESSED','FAILED','DUPLICATE','DEAD') NOT NULL DEFAULT 'RECEIVED',
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `error_code` varchar(64) DEFAULT NULL,
  `error_message` varchar(255) DEFAULT NULL,
  `received_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_we_ref` (`franchise_ref`,`event_ref`),
  UNIQUE KEY `uq_we_ext` (`franchise_ref`,`source_ref`,`external_event_id`),
  KEY `idx_we_status` (`franchise_ref`,`status`,`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: notification_templates
CREATE TABLE IF NOT EXISTS notification_templates (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `channel` enum('INAPP','WHATSAPP','EMAIL','SMS') NOT NULL,
  `body_template` text NOT NULL,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_nt` (`franchise_ref`,`event_type`,`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: notifications
CREATE TABLE IF NOT EXISTS notifications (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `notification_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) NOT NULL,
  `franchise_ref` varchar(24) NOT NULL,
  `user_ref` varchar(24) DEFAULT NULL,
  `party_ref` varchar(24) DEFAULT NULL,
  `channel` enum('INAPP','WHATSAPP','EMAIL','SMS') NOT NULL,
  `event_type` varchar(64) NOT NULL,
  `entity_type` varchar(40) DEFAULT NULL,
  `entity_ref` varchar(24) DEFAULT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload_json`)),
  `status` enum('QUEUED','SENT','DELIVERED','READ','FAILED') NOT NULL DEFAULT 'QUEUED',
  `provider_message_id` varchar(120) DEFAULT NULL,
  `idempotency_key` varchar(160) NOT NULL,
  `attempt_count` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_ref` (`franchise_ref`,`notification_ref`),
  UNIQUE KEY `uq_notify_idem` (`franchise_ref`,`idempotency_key`),
  KEY `idx_notif_user` (`franchise_ref`,`user_ref`,`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: job_queue
CREATE TABLE IF NOT EXISTS job_queue (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `job_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) DEFAULT NULL,
  `franchise_ref` varchar(24) DEFAULT NULL,
  `job_type` varchar(64) NOT NULL,
  `payload_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`payload_json`)),
  `dedupe_key` varchar(120) DEFAULT NULL,
  `status` enum('PENDING','RUNNING','SUCCESS','FAILED','RETRY_WAIT','DEAD') NOT NULL DEFAULT 'PENDING',
  `attempts` int(11) NOT NULL DEFAULT 0,
  `max_attempts` int(11) NOT NULL DEFAULT 5,
  `run_at` datetime NOT NULL,
  `locked_at` datetime DEFAULT NULL,
  `last_error` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_ref` (`job_ref`),
  UNIQUE KEY `uq_job_dedupe` (`job_type`,`dedupe_key`),
  KEY `idx_job_run` (`status`,`run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table: audit_logs
CREATE TABLE IF NOT EXISTS audit_logs (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `audit_ref` varchar(24) NOT NULL,
  `org_ref` varchar(24) DEFAULT NULL,
  `franchise_ref` varchar(24) DEFAULT NULL,
  `actor_ref` varchar(24) DEFAULT NULL,
  `actor_role` varchar(40) DEFAULT NULL,
  `impersonator_ref` varchar(24) DEFAULT NULL,
  `category` enum('BUSINESS','SECURITY','TENANT_BYPASS','SYSTEM') NOT NULL DEFAULT 'BUSINESS',
  `action` varchar(80) NOT NULL,
  `entity_type` varchar(40) NOT NULL,
  `entity_ref` varchar(24) DEFAULT NULL,
  `before_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`before_json`)),
  `after_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`after_json`)),
  `reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `request_id` varchar(32) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_audit_ref` (`audit_ref`),
  KEY `idx_audit_entity` (`franchise_ref`,`entity_type`,`entity_ref`),
  KEY `idx_audit_actor` (`franchise_ref`,`actor_ref`,`created_at`),
  KEY `idx_audit_cat` (`category`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ----------------------------------------------------------------------------
-- 3. AUDIT LOG IMMUTABILITY TRIGGERS
-- ----------------------------------------------------------------------------
CREATE TRIGGER audit_prevent_update BEFORE UPDATE ON audit_logs
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only';

CREATE TRIGGER audit_prevent_delete BEFORE DELETE ON audit_logs
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only';


-- ----------------------------------------------------------------------------

-- ----------------------------------------------------------------------------
-- 4. BASELINE SEED DATA (All Master, Reference & Initial Data Unified)
-- ----------------------------------------------------------------------------

-- 4.1 OAuth Clients
INSERT INTO oauth_clients (client_id, surface, allowed_roles, status) VALUES
('crm-super',  'super',  'SUPER_ADMIN',     'ACTIVE'),
('crm-admin',  'admin',  'FRANCHISE_ADMIN', 'ACTIVE'),
('crm-sales',  'sales',  'SALES',           'ACTIVE'),
('crm-portal', 'portal', 'DISTRIBUTOR',     'ACTIVE')
ON DUPLICATE KEY UPDATE status = 'ACTIVE';

-- 4.2 Global Geographic Hierarchy (States, Districts, Cities, Pincodes)
INSERT INTO states (state_ref, state_code, state_name) VALUES
('STA-MAHARASHTRA00001', 'MH', 'Maharashtra'),
('STA-DELHI000000000001', 'DL', 'Delhi'),
('STA-KARNATAKA00000001', 'KA', 'Karnataka'),
('STA-GUJARAT0000000001', 'GJ', 'Gujarat')
ON DUPLICATE KEY UPDATE state_name = VALUES(state_name);

INSERT INTO districts (district_ref, state_ref, district_name) VALUES
('DST-MUMBAICITY000001', 'STA-MAHARASHTRA00001', 'Mumbai City'),
('DST-MUMBAISUBURB0001', 'STA-MAHARASHTRA00001', 'Mumbai Suburban'),
('DST-THANE00000000001', 'STA-MAHARASHTRA00001', 'Thane'),
('DST-NEWDELHI00000001', 'STA-DELHI000000000001', 'New Delhi')
ON DUPLICATE KEY UPDATE district_name = VALUES(district_name);

INSERT INTO cities (city_ref, district_ref, city_name) VALUES
('CTY-MUMBAI0000000001', 'DST-MUMBAICITY000001', 'Mumbai'),
('CTY-ANDHERI000000001', 'DST-MUMBAISUBURB0001', 'Andheri'),
('CTY-THANE00000000001', 'DST-THANE00000000001', 'Thane'),
('CTY-DELHI00000000001', 'DST-NEWDELHI00000001', 'New Delhi')
ON DUPLICATE KEY UPDATE city_name = VALUES(city_name);

INSERT INTO pincodes (pincode, city_ref, district_ref, state_ref) VALUES
('400001', 'CTY-MUMBAI0000000001', 'DST-MUMBAICITY000001', 'STA-MAHARASHTRA00001'),
('400053', 'CTY-ANDHERI000000001', 'DST-MUMBAISUBURB0001', 'STA-MAHARASHTRA00001'),
('400601', 'CTY-THANE00000000001', 'DST-THANE00000000001', 'STA-MAHARASHTRA00001'),
('110001', 'CTY-DELHI00000000001', 'DST-NEWDELHI00000001', 'STA-DELHI000000000001')
ON DUPLICATE KEY UPDATE state_ref = VALUES(state_ref);

-- 4.3 Master Platform Organization & Primary PCD Franchise
INSERT INTO organizations (org_ref, org_code, org_name, status, created_by_ref) VALUES
('ORG-PLATFORM0000000001', 'ACME', 'Acme Healthcare Group', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE org_name = 'Acme Healthcare Group';

INSERT INTO franchises (franchise_ref, org_ref, franchise_code, franchise_name, state_ref, status, created_by_ref) VALUES
('FRN-MUMBAI000000000001', 'ORG-PLATFORM0000000001', 'MUMBAI', 'Acme Mumbai PCD Franchise', 'STA-MAHARASHTRA00001', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE franchise_name = 'Acme Mumbai PCD Franchise', state_ref = VALUES(state_ref);

-- 4.4 Four Baseline Tenant Users
INSERT INTO users (user_ref, org_ref, franchise_ref, role, full_name, email, password_hash, status, created_by_ref) VALUES
('USR-SUPERADMIN0000001', 'ORG-PLATFORM0000000001', NULL, 'SUPER_ADMIN', 'Super Admin', 'super@pharmacrm.local', '\$argon2id\$v=19\$m=65536,t=4,p=1\$MXdBclpqdGM2bzNGTU1Dbw\$bzuidFjmICKP1CImzRXBL/Zd2E1vRBTokewP4Qsed7Y', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('USR-FRNADMIN000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'FRANCHISE_ADMIN', 'Mumbai Admin', 'admin@pharmacrm.local', '\$argon2id\$v=19\$m=65536,t=4,p=1\$MXdBclpqdGM2bzNGTU1Dbw\$bzuidFjmICKP1CImzRXBL/Zd2E1vRBTokewP4Qsed7Y', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('USR-SALESREP000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SALES', 'Rajesh Sales', 'sales@pharmacrm.local', '\$argon2id\$v=19\$m=65536,t=4,p=1\$MXdBclpqdGM2bzNGTU1Dbw\$bzuidFjmICKP1CImzRXBL/Zd2E1vRBTokewP4Qsed7Y', 'ACTIVE', 'USR-FRNADMIN000000001'),
('USR-DISTRIBUTOR000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DISTRIBUTOR', 'Apollo Distributor', 'portal@pharmacrm.local', '\$argon2id\$v=19\$m=65536,t=4,p=1\$MXdBclpqdGM2bzNGTU1Dbw\$bzuidFjmICKP1CImzRXBL/Zd2E1vRBTokewP4Qsed7Y', 'ACTIVE', 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE status = 'ACTIVE';

-- 4.5 RBAC: Permission Catalogue & Permissions Seed
INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive) VALUES
('auditLogs','export','Export',0),
('auditLogs','view','View',1),
('billing','cancel','Cancel',1),
('billing','create','Create',0),
('billing','export','Export',0),
('billing','outstanding','View outstanding and ageing',1),
('billing','print','Print',0),
('billing','view','View',0),
('dashboard','view','View',0),
('dcr','approve','Approve DCR',1),
('dcr','create','Create DCR',0),
('dcr','edit','Edit DCR',0),
('dcr','reject','Reject DCR',0),
('dcr','submit','Submit DCR',0),
('dcr','view','View DCR',0),
('dispatch','create','Create',0),
('dispatch','edit','Edit',0),
('dispatch','export','Export',0),
('dispatch','updateTracking','Update LR/Tracking',0),
('dispatch','view','View',0),
('distributorOnboarding','approve','Approve',1),
('distributorOnboarding','convert','Convert to Party',1),
('distributorOnboarding','create','Create registration',0),
('distributorOnboarding','edit','Edit registration',0),
('distributorOnboarding','generateInvite','Generate Invite',0),
('distributorOnboarding','reject','Reject',0),
('distributorOnboarding','resendRevokeInvite','Resend/Revoke Invite',0),
('distributorOnboarding','review','Review',0),
('distributorOnboarding','view','View',0),
('followUps','complete','Complete',0),
('followUps','create','Create',0),
('followUps','edit','Edit',0),
('followUps','export','Export',0),
('followUps','reschedule','Reschedule',0),
('followUps','view','View',0),
('internalUsers','activateDeactivate','Activate/Deactivate',0),
('internalUsers','create','Create',0),
('internalUsers','edit','Edit',0),
('internalUsers','resetPassword','Reset Password',0),
('internalUsers','view','View',0),
('inventory','adjust','Adjust',1),
('inventory','create','Create',0),
('inventory','edit','Edit',0),
('inventory','export','Export',0),
('inventory','manualBatchOverride','Manual Batch Override',1),
('inventory','release','Release Reservation',1),
('inventory','reserve','Reserve Stock',1),
('inventory','transfer','Transfer',1),
('inventory','view','View',0),
('kyc','reject','Reject KYC document',1);
INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive) VALUES
('kyc','upload','Register KYC document',1),
('kyc','verify','Verify KYC document',1),
('kyc','view','View KYC',1),
('leads','archive','Archive',0),
('leads','assign','Assign',0),
('leads','convert','Convert',0),
('leads','create','Create',0),
('leads','edit','Edit',0),
('leads','export','Export',0),
('leads','view','View',0),
('masters','activateDeactivate','Activate/Deactivate',0),
('masters','create','Create',0),
('masters','edit','Edit',0),
('masters','view','View',0),
('nearExpiry','configure','Configure',0),
('nearExpiry','export','Export',0),
('nearExpiry','view','View',0),
('notifications','manageTemplates','Manage Templates',0),
('notifications','sendRetry','Send/Retry',0),
('notifications','view','View',0),
('orders','cancel','Cancel',0),
('orders','confirm','Confirm',0),
('orders','create','Create',0),
('orders','deleteDraft','Delete Draft',0),
('orders','editDraft','Edit Draft',0),
('orders','export','Export',0),
('orders','print','Print',0),
('orders','submit','Submit',0),
('orders','view','View',0),
('parties','activateDeactivate','Activate/Deactivate',0),
('parties','archive','Archive',0),
('parties','create','Create',0),
('parties','edit','Edit',0),
('parties','export','Export',0),
('parties','view','View',0),
('payments','allocate','Allocate',1),
('payments','archive','Archive',0),
('payments','cancel','Cancel',1),
('payments','create','Create',0),
('payments','delete','Delete',1),
('payments','edit','Edit',0),
('payments','export','Export',0),
('payments','pdc','PDC Operations',1),
('payments','reverse','Reverse posted payment',1),
('payments','view','View',0),
('pricing','create','Create',0),
('pricing','edit','Edit',0),
('pricing','export','Export',0),
('pricing','priceOverride','Price Override',1),
('pricing','view','View',0);
INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive) VALUES
('products','activateDeactivate','Activate/Deactivate',0),
('products','archive','Archive',0),
('products','create','Create',0),
('products','edit','Edit',0),
('products','export','Export',0),
('products','view','View',0),
('reports','export','Export',0),
('reports','print','Print',0),
('reports','view','View',0),
('rolesAndPermissions','assignToUser','Assign to User',0),
('rolesAndPermissions','create','Create',0),
('rolesAndPermissions','delete','Delete',0),
('rolesAndPermissions','edit','Edit',1),
('rolesAndPermissions','view','View',0),
('schemes','create','Create',0),
('schemes','deactivate','Deactivate',0),
('schemes','edit','Edit',0),
('schemes','view','View',0),
('territory','allocate','Allocate',0),
('territory','edit','Edit',0),
('territory','export','Export',0),
('territory','override','Override',1),
('territory','view','View',0),
('webhooks','configure','Configure',0),
('webhooks','retryFailed','Retry Failed',0),
('webhooks','view','View',0);

-- Populate grantable auth_permissions from catalogue
INSERT IGNORE INTO auth_permissions (permission_ref, module_key, action_key, label, is_sensitive)
SELECT CONCAT('PER-', UPPER(SUBSTRING(SHA2(CONCAT(module_key, ':', action_key), 256), 1, 20))),
       module_key, action_key, label, is_sensitive
FROM auth_permission_catalogue;

-- 4.6 RBAC: Baseline Roles for Mumbai Franchise
INSERT IGNORE INTO auth_roles
  (role_ref, org_ref, franchise_ref, role_name, role_slug, description, is_system, status, default_scope, created_by_ref)
VALUES
('ROLE-MUMBAI-ADMIN0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Admin', 'admin', 'Protected full-access system role', 1, 'ACTIVE', 'ALL', 'SYSTEM'),
('ROLE-MUMBAI-SALES0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Sales Team', 'sales-team', 'Default own-scope sales role', 0, 'ACTIVE', 'OWN', 'SYSTEM'),
('ROLE-MUMBAI-PORTAL001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Distributor', 'distributor', 'Distributor portal self-service', 0, 'ACTIVE', 'OWN', 'SYSTEM');

-- Grant all permissions to Admin
INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
SELECT 'ROLE-MUMBAI-ADMIN0001', permission_ref, 'SYSTEM'
FROM auth_permissions;

-- Grant Sales Team baseline permissions
INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
SELECT 'ROLE-MUMBAI-SALES0001', permission_ref, 'SYSTEM'
FROM auth_permissions
WHERE (module_key, action_key) IN (
  ('leads','view'),('leads','create'),('leads','edit'),('leads','convert'),
  ('followUps','view'),('followUps','create'),('followUps','edit'),('followUps','complete'),('followUps','reschedule'),
  ('parties','view'),('parties','create'),('parties','edit'),
  ('orders','view'),('orders','create'),('orders','editDraft'),('orders','submit'),
  ('payments','view'),('payments','create'),('payments','edit'),
  ('dashboard','view'),('reports','view')
);

-- Grant Distributor Portal permissions
INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
SELECT 'ROLE-MUMBAI-PORTAL001', permission_ref, 'SYSTEM'
FROM auth_permissions
WHERE module_key = 'portal' AND action_key IN ('view','placeOrder','editProfile');

-- Assign Roles to Users
INSERT IGNORE INTO auth_user_roles (user_ref, role_ref, assigned_by_ref) VALUES
('USR-FRNADMIN000000001', 'ROLE-MUMBAI-ADMIN0001', 'SYSTEM'),
('USR-SALESREP000000001', 'ROLE-MUMBAI-SALES0001', 'SYSTEM'),
('USR-DISTRIBUTOR000001', 'ROLE-MUMBAI-PORTAL001', 'SYSTEM');

-- 4.7 Product Categories & Catalog Master Values
INSERT INTO product_categories (category_ref, org_ref, franchise_ref, category_name, status, created_by_ref) VALUES
('CAT-ANALGESIC0000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Analgesics & Pain Relief', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('CAT-ANTIBIOTIC000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Antibiotics & Anti-Infectives', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('CAT-ANTIBIOTIC-001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Antibiotics', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('CAT-ANTIHIST00000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Antihistamines & Allergy', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('CAT-GASTRO0000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Gastrointestinal', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('CAT-NUTRITION0000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Vitamins & Supplements', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name), status = VALUES(status);

-- 4.8 Products (Full Portfolio)
INSERT INTO products (
  product_ref, org_ref, franchise_ref, sku, product_name, category_ref,
  composition, pack_size, dosage_form, mrp, pts, franchise_rate,
  gst_percent, hsn_code, shelf_life_days, storage_requirement, scheme_eligible, status, created_by_ref, description, availability
) VALUES
('PRD-PARA500000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PARA-500', 'Paracetamol 500mg Tablets', 'CAT-ANALGESIC0000001',
 'Paracetamol IP 500mg', '10x10 Tablets', 'Tablet', 25.50, 18.20, 15.00, 12.00, '30049060', 730, 'Store below 25°C', 1, 'ACTIVE', 'USR-SUPERADMIN0000001', 'Standard analgesic formulation', 'IN_STOCK'),

('PRD-AMOX250000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'AMOX-250', 'Amoxicillin 250mg Capsules', 'CAT-ANTIBIOTIC000001',
 'Amoxicillin Trihydrate IP 250mg', '10x10 Capsules', 'Capsule', 45.00, 32.10, 27.50, 12.00, '30041010', 730, 'Store in cool and dry place', 1, 'ACTIVE', 'USR-SUPERADMIN0000001', 'Broad-spectrum antibiotic', 'IN_STOCK'),

('PRD-CETA1000000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CETA-10', 'Cetirizine 10mg Tablets', 'CAT-ANTIHIST00000001',
 'Cetirizine Hydrochloride IP 10mg', '10x10 Tablets', 'Tablet', 15.00, 10.50, 8.80, 12.00, '30049099', 1095, 'Store below 30°C', 1, 'ACTIVE', 'USR-SUPERADMIN0000001', 'Non-drowsy antihistamine', 'IN_STOCK'),

('PRD-OMEP200000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'OMEP-20', 'Omeprazole 20mg Capsules', 'CAT-GASTRO0000000001',
 'Omeprazole IP 20mg', '10x10 Capsules', 'Capsule', 55.00, 39.00, 33.00, 12.00, '30049099', 730, 'Store protected from moisture', 1, 'ACTIVE', 'USR-SUPERADMIN0000001', 'Proton pump inhibitor', 'IN_STOCK'),

('PRD-VITC100000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'VITC-1000', 'Vitamin C 1000mg Chewable', 'CAT-NUTRITION0000001',
 'Ascorbic Acid IP 1000mg', '30 Chewable Tablets', 'Chewable Tablet', 120.00, 85.00, 72.00, 18.00, '21069099', 540, 'Store below 25°C', 1, 'ACTIVE', 'USR-SUPERADMIN0000001', 'Immunity booster chewables', 'IN_STOCK'),

('PRD-COUGH1000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CGS-100', 'Cough Syrup 100ml', 'CAT-ANTIHIST00000001',
 'Dextromethorphan + Chlorpheniramine', '100ml Bottle', 'Syrup', 85.00, 60.00, 52.00, 12.00, '30049099', 730, 'Store in dark place', 1, 'ACTIVE', 'USR-SUPERADMIN0000001', 'Fast acting cough relief syrup', 'IN_STOCK'),

('PRD-AZI5000000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'AZI-500', 'Azithromycin 500mg Tablets', 'CAT-ANTIBIOTIC000001',
 'Azithromycin IP 500mg', '3 Tablets Strip', 'Tablet', 120.00, 88.00, 75.00, 12.00, '30041010', 730, 'Store below 30°C', 1, 'ACTIVE', 'USR-SUPERADMIN0000001', 'Azithromycin respiratory antibiotic', 'IN_STOCK'),

('PRD-DICGEL3000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DIC-GEL', 'Diclofenac Gel 30g', 'CAT-ANALGESIC0000001',
 'Diclofenac Diethylamine 1.16% w/w', '30g Tube', 'Gel', 65.00, 46.00, 39.00, 12.00, '30049099', 1095, 'Do not freeze', 0, 'ACTIVE', 'USR-SUPERADMIN0000001', 'Topical pain relief gel', 'IN_STOCK'),

('PRD-TEST000000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'TEST-AMOX-500', 'Amoxicillin 500mg', 'CAT-ANTIBIOTIC-001',
 'Amoxicillin IP 500mg', '10x10 Capsules', 'Capsule', 200.00, 160.00, 150.00, 12.00, '30041010', 730, 'Store below 25°C', 1, 'ACTIVE', 'USR-SUPERADMIN0000001', 'Test Amoxicillin formulation', 'IN_STOCK')
ON DUPLICATE KEY UPDATE
  product_name = VALUES(product_name), category_ref = VALUES(category_ref),
  mrp = VALUES(mrp), pts = VALUES(pts), franchise_rate = VALUES(franchise_rate),
  gst_percent = VALUES(gst_percent), status = VALUES(status), description = VALUES(description), availability = VALUES(availability);

-- 4.9 Pricing Tiers & Tier-based Product Prices
INSERT INTO pricing_tiers (tier_ref, org_ref, franchise_ref, tier_name, status, created_by_ref) VALUES
('TIR-STOCKIST00000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Stockist / Wholesaler', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('TIR-DISTRIB000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Authorised Distributor', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('TIR-RETAIL0000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Retail Chemist / Pharmacy', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE tier_name = VALUES(tier_name), status = VALUES(status);

INSERT INTO product_prices (
  price_ref, org_ref, franchise_ref, product_ref, tier_ref, party_ref,
  rate, mrp, pts, net_rate, priority, effective_from, status, created_by_ref
) VALUES
('PRC-TIER-STOCKIST-01', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-TEST000000000001', 'TIR-STOCKIST00000001', NULL, 140.00, 200.00, 160.00, 140.00, 10, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-PARA500-STK00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-PARA500000000001', 'TIR-STOCKIST00000001', NULL, 16.50, 25.50, 18.20, 16.50, 10, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-PARA500-DST00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-PARA500000000001', 'TIR-DISTRIB000000001', NULL, 15.00, 25.50, 18.20, 15.00, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-PARA500-RET00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-PARA500000000001', 'TIR-RETAIL0000000001', NULL, 18.20, 25.50, 18.20, 18.20, 30, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRC-AMOX250-STK00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-AMOX250000000001', 'TIR-STOCKIST00000001', NULL, 29.50, 45.00, 32.10, 29.50, 10, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-AMOX250-DST00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-AMOX250000000001', 'TIR-DISTRIB000000001', NULL, 27.50, 45.00, 32.10, 27.50, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRC-CETA10-DST000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-CETA1000000000001', 'TIR-DISTRIB000000001', NULL, 8.80, 15.00, 10.50, 8.80, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-OMEP20-DST000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-OMEP200000000001', 'TIR-DISTRIB000000001', NULL, 33.00, 55.00, 39.00, 33.00, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-VITC10-DST000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-VITC100000000001', 'TIR-DISTRIB000000001', NULL, 72.00, 120.00, 85.00, 72.00, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE rate = VALUES(rate), status = VALUES(status);

-- 4.10 Promotional Schemes & Rules
INSERT INTO schemes (
  scheme_ref, org_ref, franchise_ref, scheme_name, scheme_type,
  start_date, end_date, priority, stacking_allowed, tier_ref, status, created_by_ref
) VALUES
('SCH-MONSOON20260001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Monsoon Health Campaign (Buy 10 Get 1 Free)', 'QUANTITY_DISCOUNT',
 '2026-01-01', '2026-12-31', 10, 0, NULL, 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE scheme_name = VALUES(scheme_name), status = VALUES(status);

INSERT INTO scheme_rules (
  rule_ref, org_ref, franchise_ref, scheme_ref, product_ref,
  min_qty, max_qty, free_qty
) VALUES
('RUL-PARA500-10P10001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SCH-MONSOON20260001', 'PRD-PARA500000000001', 10, 100, 1),
('RUL-CETA10-10P100001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SCH-MONSOON20260001', 'PRD-CETA1000000000001', 10, 100, 1)
ON DUPLICATE KEY UPDATE free_qty = VALUES(free_qty);

-- 4.11 Customer Parties & Territory Allocations
INSERT INTO parties (
  party_ref, org_ref, franchise_ref, party_code, firm_name, contact_name,
  mobile, email, gstin, drug_license_no, drug_license_validity,
  billing_address, shipping_address, area,
  state_ref, district_ref, city_ref, pincode,
  tier_ref, sales_user_ref, agreement_from, agreement_to,
  credit_limit, payment_terms_days, opening_outstanding,
  party_type, status, created_by_ref
) VALUES
('PAR-APOLLODIST00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PTY-00001', 'Apollo Pharma Distributors', 'Rajesh Kulkarni',
 '9820012345', 'portal@pharmacrm.local', '27AABCA1234F1Z5', 'MH-MZ4-123456', '2028-12-31',
 'Shop 12, Commercial Hub, Fort, Mumbai', 'Shop 12, Commercial Hub, Fort, Mumbai', 'Fort',
 'STA-MAHARASHTRA00001', 'DST-MUMBAICITY000001', 'CTY-MUMBAI0000000001', '400001',
 'TIR-DISTRIB000000001', 'USR-SALESREP000000001', '2026-01-01', '2028-12-31',
 500000.00, 30, 0.00, 'DISTRIBUTOR', 'ACTIVE', 'USR-FRNADMIN000000001'),

('PAR-GUPTAMEDICO0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PTY-00002', 'Gupta Medicos & Surgicals', 'Dr. Ramesh Gupta',
 '9820054321', 'ramesh.gupta@example.com', '27BBCDE5678G2Z4', 'MH-MZ4-654321', '2027-12-31',
 'Plot 45, Linking Road, Andheri West, Mumbai', 'Plot 45, Linking Road, Andheri West, Mumbai', 'Andheri West',
 'STA-MAHARASHTRA00001', 'DST-MUMBAISUBURB0001', 'CTY-ANDHERI000000001', '400053',
 'TIR-RETAIL0000000001', 'USR-SALESREP000000001', '2026-01-01', '2027-12-31',
 200000.00, 21, 0.00, 'RETAILER', 'ACTIVE', 'USR-FRNADMIN000000001'),

('PAR-DELHISTOCK00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PTY-00003', 'Apex Lifesciences Wholesale', 'Sneha Verma',
 '9811122233', 'sneha.verma@example.com', '27CCCDE9876H3Z1', 'MH-TH2-789012', '2028-12-31',
 'Sector 3, Wagle Estate, Thane West', 'Sector 3, Wagle Estate, Thane West', 'Wagle Estate',
 'STA-MAHARASHTRA00001', 'DST-THANE00000000001', 'CTY-THANE00000000001', '400601',
 'TIR-STOCKIST00000001', 'USR-SALESREP000000001', '2026-01-01', '2028-12-31',
 1000000.00, 45, 0.00, 'STOCKIST', 'ACTIVE', 'USR-FRNADMIN000000001'),

('PAR-WELLNESSCHEM0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PTY-00004', 'Wellness Forever Chemist', 'Sunil Narang',
 '9820067890', 'mumbai@wellnessforever.local', '27DDDDE1234K1Z9', 'MH-MZ4-889900', '2027-12-31',
 'Shop 5, Western Express Highway, Andheri East', 'Shop 5, Western Express Highway, Andheri East', 'Andheri East',
 'STA-MAHARASHTRA00001', 'DST-MUMBAISUBURB0001', 'CTY-ANDHERI000000001', '400053',
 'TIR-RETAIL0000000001', 'USR-SALESREP000000001', '2026-01-01', '2027-12-31',
 150000.00, 15, 0.00, 'RETAILER', 'ACTIVE', 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE
  firm_name = VALUES(firm_name), contact_name = VALUES(contact_name),
  credit_limit = VALUES(credit_limit), status = VALUES(status);

-- Link Distributor User to Apollo Party
UPDATE users
SET party_ref = 'PAR-APOLLODIST00001'
WHERE user_ref = 'USR-DISTRIBUTOR000001';

-- Party Territory Allocations
INSERT INTO party_territories (
  territory_ref, org_ref, franchise_ref, party_ref,
  level, pincode, district_ref, effective_from, is_exclusive, status, created_by_ref
) VALUES
('TER-APOLLOPIN400001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAR-APOLLODIST00001',
 'PINCODE', '400001', NULL, '2026-01-01', 1, 'ACTIVE', 'USR-FRNADMIN000000001'),

('TER-GUPTAPIN4000530', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAR-GUPTAMEDICO0001',
 'PINCODE', '400053', NULL, '2026-01-01', 0, 'ACTIVE', 'USR-FRNADMIN000000001'),

('TER-APEXDSTTHANE001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAR-DELHISTOCK00001',
 'DISTRICT', NULL, 'DST-THANE00000000001', '2026-01-01', 1, 'ACTIVE', 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE status = VALUES(status);

-- 4.12 FEFO Inventory Batches & Stock Movements
INSERT INTO inventory_batches (
  batch_ref, org_ref, franchise_ref, product_ref,
  batch_no, manufacturing_date, expiry_date,
  received_qty, on_hand_qty, reserved_qty, damaged_qty,
  location_code, status, version, created_by_ref
) VALUES
('BAT-PARA500-26A00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-PARA500000000001',
 'PR-2026A1', '2026-01-10', '2027-12-31', 5000, 4800, 0, 0, 'WH-MUM-A1-R1', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-PARA500-26B00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-PARA500000000001',
 'PR-2026B2', '2026-03-01', '2028-02-28', 5000, 5000, 0, 0, 'WH-MUM-A1-R2', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-AMOX250-26A00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-AMOX250000000001',
 'AM-2026A1', '2026-02-15', '2027-08-31', 2500, 2400, 0, 0, 'WH-MUM-A2-R1', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-CETA10-26A000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-CETA1000000000001',
 'CT-2026A1', '2026-01-20', '2028-12-31', 3000, 3000, 0, 0, 'WH-MUM-B1-R1', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-OMEP20-26A000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-OMEP200000000001',
 'OM-2026A1', '2026-02-01', '2028-01-31', 2000, 2000, 0, 0, 'WH-MUM-B2-R1', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-VITC10-26A000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-VITC100000000001',
 'VC-2026A1', '2026-02-10', '2027-07-31', 1500, 1500, 0, 0, 'WH-MUM-C1-R1', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-COUGH100-26A0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-COUGH1000000001',
 'CG-2026A1', '2026-02-01', '2028-01-31', 1000, 950, 0, 0, 'WH-MUM-SYRUP-01', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-AZI500-26A000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-AZI5000000000001',
 'AZ-2026A1', '2026-01-15', '2028-01-14', 1500, 1500, 0, 0, 'WH-MUM-A3-R1', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-DICGEL-26A000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-DICGEL3000000001',
 'DG-2026A1', '2026-02-15', '2029-02-14', 800, 800, 0, 0, 'WH-MUM-TOP-01', 'SALEABLE', 1, 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE on_hand_qty = VALUES(on_hand_qty), received_qty = VALUES(received_qty), status = VALUES(status);

INSERT INTO inventory_movements (
  movement_ref, org_ref, franchise_ref, batch_ref,
  movement_type, qty, reference_type, reference_ref, remarks, created_by_ref
) VALUES
('MOV-REC-PARA500-001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'BAT-PARA500-26A00001', 'RECEIPT', 5000, 'GRN', 'GRN-2026-0001', 'Initial stock receipt batch PR-2026A1', 'USR-FRNADMIN000000001'),
('MOV-REC-PARA500-002', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'BAT-PARA500-26B00001', 'RECEIPT', 5000, 'GRN', 'GRN-2026-0002', 'Stock receipt batch PR-2026B2', 'USR-FRNADMIN000000001'),
('MOV-REC-AMOX250-001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'BAT-AMOX250-26A00001', 'RECEIPT', 2500, 'GRN', 'GRN-2026-0003', 'Initial stock receipt batch AM-2026A1', 'USR-FRNADMIN000000001'),
('MOV-REC-CETA10-0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'BAT-CETA10-26A000001', 'RECEIPT', 3000, 'GRN', 'GRN-2026-0004', 'Initial stock receipt batch CT-2026A1', 'USR-FRNADMIN000000001'),
('MOV-REC-OMEP20-0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'BAT-OMEP20-26A000001', 'RECEIPT', 2000, 'GRN', 'GRN-2026-0005', 'Initial stock receipt batch OM-2026A1', 'USR-FRNADMIN000000001'),
('MOV-REC-VITC10-0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'BAT-VITC10-26A000001', 'RECEIPT', 1500, 'GRN', 'GRN-2026-0006', 'Initial stock receipt batch VC-2026A1', 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE qty = VALUES(qty);

-- 4.13 CRM Leads, Follow-ups & Activities
INSERT INTO leads (
  lead_ref, org_ref, franchise_ref, external_source_ref, external_lead_id,
  contact_name, firm_name, mobile, mobile_norm, email,
  state_ref, district_ref, city_ref, pincode,
  lead_source, business_type, interested_products,
  assigned_user_ref, priority, status, initial_remark,
  first_response_at, next_follow_up_at, created_by_ref
) VALUES
('LED-DRANILKUMAR00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', NULL, NULL,
 'Dr. Anil Kumar', 'Kumar Poly Clinic', '9876543212', '9876543212', 'anil@example.com',
 'STA-MAHARASHTRA00001', 'DST-MUMBAICITY000001', 'CTY-MUMBAI0000000001', '400001',
 'FIELD_VISIT', 'Clinic / General Physician', 'Paracetamol, Cetirizine, Antibiotics',
 'USR-SALESREP000000001', 'HIGH', 'CONTACTED', 'Interested in regular clinic supply and monthly schemes.',
 '2026-03-15 10:30:00', '2026-09-25 11:00:00', 'USR-SALESREP000000001'),

('LED-DRPRIYASINGH0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', NULL, NULL,
 'Dr. Priya Singh', 'Skin & Derma Care', '9876543213', '9876543213', 'priya@example.com',
 'STA-MAHARASHTRA00001', 'DST-MUMBAISUBURB0001', 'CTY-ANDHERI000000001', '400053',
 'WEBSITE', 'Dermatologist', 'Diclofenac Gel, Antihistamines',
 'USR-SALESREP000000001', 'NORMAL', 'NEW', 'Inquired through web form regarding agency rights in Andheri.',
 NULL, '2026-09-22 15:00:00', 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE status = VALUES(status), priority = VALUES(priority);

INSERT INTO follow_ups (
  followup_ref, org_ref, franchise_ref, lead_ref, party_ref,
  assigned_user_ref, activity_type, next_action, next_follow_up_at,
  status, remark, created_by_ref
) VALUES
('FUP-ANILKUMAR0000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LED-DRANILKUMAR00001', NULL,
 'USR-SALESREP000000001', 'VISIT', 'Present Product Catalogue & Price List', '2026-09-25 11:00:00',
 'PENDING', 'Doctor requested sample foils of Paracetamol and Cetirizine.', 'USR-SALESREP000000001'),

('FUP-APOLLOREFILL0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', NULL, 'PAR-APOLLODIST00001',
 'USR-SALESREP000000001', 'CALL', 'Monthly Refill Order Confirmation', '2026-09-24 14:00:00',
 'PENDING', 'Verify stock levels of Amoxicillin and Vitamin C.', 'USR-SALESREP000000001')
ON DUPLICATE KEY UPDATE status = VALUES(status);

INSERT IGNORE INTO lead_activities (
  activity_ref, org_ref, franchise_ref, lead_ref, user_ref,
  activity_type, from_status, to_status, activity_note
) VALUES
('ACT-ANILKUMAR0000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LED-DRANILKUMAR00001', 'USR-SALESREP000000001',
 'INITIAL_CONTACT', 'NEW', 'CONTACTED', 'Introduced Acme PCD franchise product offerings. Doctor is warm to product portfolio.');

-- 4.14 Orders & Order Items (Seed Records)
INSERT INTO orders (
  order_ref, order_no, org_ref, franchise_ref, client_order_ref,
  party_ref, sales_user_ref, channel, order_date,
  shipping_address, shipping_pincode, status, territory_status,
  subtotal, discount_total, gst_total, grand_total, version, remarks, created_by_ref
) VALUES
('ORD-APOLLO2026000001', 'SO-2026-00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CL-ORD-APOLLO-001',
 'PAR-APOLLODIST00001', 'USR-SALESREP000000001', 'PORTAL', '2026-09-18',
 'Shop 12, Commercial Hub, Fort, Mumbai', '400001', 'CONFIRMED', 'OK',
 4850.00, 0.00, 582.00, 5432.00, 1, 'Portal replenishment order', 'USR-DISTRIBUTOR000001'),

('ORD-GUPTA20260000001', 'SO-2026-00002', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CL-ORD-GUPTA-001',
 'PAR-GUPTAMEDICO0001', 'USR-SALESREP000000001', 'SALES', '2026-09-19',
 'Plot 45, Linking Road, Andheri West, Mumbai', '400053', 'SUBMITTED', 'OK',
 2210.00, 0.00, 265.20, 2475.20, 1, 'Field booking by sales rep', 'USR-SALESREP000000001')
ON DUPLICATE KEY UPDATE status = VALUES(status), grand_total = VALUES(grand_total);

INSERT INTO order_items (
  item_ref, org_ref, franchise_ref, order_ref, product_ref,
  paid_qty, free_qty, rate, rate_source, price_ref,
  discount, gst_percent, line_total, scheme_ref
) VALUES
('ITM-ORD1-PARA5000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-APOLLO2026000001', 'PRD-PARA500000000001',
 100, 10, 15.00, 'TIER', 'PRC-PARA500-DST00001', 0.00, 12.00, 1500.00, 'SCH-MONSOON20260001'),

('ITM-ORD1-AMOX2500001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-APOLLO2026000001', 'PRD-AMOX250000000001',
 100, 0, 27.50, 'TIER', 'PRC-AMOX250-DST00001', 0.00, 12.00, 2750.00, NULL),

('ITM-ORD1-CETA10000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-APOLLO2026000001', 'PRD-CETA1000000000001',
 50, 5, 8.80, 'TIER', 'PRC-CETA10-DST000001', 0.00, 12.00, 440.00, 'SCH-MONSOON20260001'),

('ITM-ORD2-PARA5000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-GUPTA20260000001', 'PRD-PARA500000000001',
 50, 5, 18.20, 'TIER', 'PRC-PARA500-RET00001', 0.00, 12.00, 910.00, 'SCH-MONSOON20260001'),

('ITM-ORD2-OMEP2000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-GUPTA20260000001', 'PRD-OMEP200000000001',
 30, 0, 39.00, 'DEFAULT', NULL, 0.00, 12.00, 1170.00, NULL)
ON DUPLICATE KEY UPDATE line_total = VALUES(line_total);

INSERT IGNORE INTO order_status_history (
  org_ref, franchise_ref, order_ref, from_status, to_status, actor_ref, reason
) VALUES
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-APOLLO2026000001', 'DRAFT', 'SUBMITTED', 'USR-DISTRIBUTOR000001', 'Order submitted from distributor portal'),
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-APOLLO2026000001', 'SUBMITTED', 'CONFIRMED', 'USR-FRNADMIN000000001', 'Stock and credit verified'),
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-GUPTA20260000001', 'DRAFT', 'SUBMITTED', 'USR-SALESREP000000001', 'Order taken on field visit');

-- 4.15 Sequence Counters (Initial gapless counter baselines)
INSERT INTO sequence_counters (org_ref, franchise_ref, counter_key, period_key, last_value) VALUES
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PARTY', 'GLOBAL', 100),
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORDER', '2026', 100),
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'INVOICE', '2627', 100),
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DISPATCH', '2026', 100),
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAYMENT', '2026', 100)
ON DUPLICATE KEY UPDATE last_value = GREATEST(last_value, VALUES(last_value));

SET FOREIGN_KEY_CHECKS = 1;
