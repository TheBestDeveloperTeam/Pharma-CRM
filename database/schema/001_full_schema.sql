SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Drop existing tables in case of fresh migration
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS job_queue;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS notification_templates;
DROP TABLE IF EXISTS webhook_events;
DROP TABLE IF EXISTS webhook_sources;
DROP TABLE IF EXISTS payment_allocations;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS dispatches;
DROP TABLE IF EXISTS transporters;
DROP TABLE IF EXISTS invoice_items;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS stock_reservations;
DROP TABLE IF EXISTS inventory_movements;
DROP TABLE IF EXISTS inventory_batches;
DROP TABLE IF EXISTS lead_activities;
DROP TABLE IF EXISTS follow_ups;
DROP TABLE IF EXISTS leads;
DROP TABLE IF EXISTS onboarding_invites;
DROP TABLE IF EXISTS territory_overrides;
DROP TABLE IF EXISTS party_territories;
DROP TABLE IF EXISTS parties;
DROP TABLE IF EXISTS scheme_rules;
DROP TABLE IF EXISTS schemes;
DROP TABLE IF EXISTS product_prices;
DROP TABLE IF EXISTS pricing_tiers;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS api_idempotency_keys;
DROP TABLE IF EXISTS sequence_counters;
DROP TABLE IF EXISTS pincodes;
DROP TABLE IF EXISTS cities;
DROP TABLE IF EXISTS districts;
DROP TABLE IF EXISTS states;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS oauth_refresh_tokens;
DROP TABLE IF EXISTS user_sessions;
DROP TABLE IF EXISTS oauth_clients;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS franchises;
DROP TABLE IF EXISTS organizations;

-- 8.1 Tenancy Core
CREATE TABLE organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL,
  org_code VARCHAR(32) NOT NULL,
  org_name VARCHAR(191) NOT NULL,
  status ENUM('ACTIVE','SUSPENDED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  brand_primary_hex CHAR(7) NULL,
  brand_accent_hex CHAR(7) NULL,
  created_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_org_ref (org_ref),
  UNIQUE KEY uq_org_code (org_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE franchises (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  franchise_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL,
  franchise_code VARCHAR(32) NOT NULL,
  franchise_name VARCHAR(191) NOT NULL,
  gstin VARCHAR(15) NULL,
  drug_license_no VARCHAR(64) NULL,
  address TEXT NULL,
  brand_primary_hex CHAR(7) NULL,
  brand_accent_hex CHAR(7) NULL,
  settings_json JSON NULL,
  status ENUM('ACTIVE','SUSPENDED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_franchise_ref (franchise_ref),
  UNIQUE KEY uq_franchise_pair (org_ref, franchise_ref),
  UNIQUE KEY uq_franchise_code (org_ref, franchise_code),
  INDEX idx_franchise_status (org_ref, status),
  CONSTRAINT fk_frn_org FOREIGN KEY (org_ref) REFERENCES organizations(org_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8.2 Users, OAuth, Sessions
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NULL,
  tenant_key VARCHAR(24) GENERATED ALWAYS AS (IFNULL(franchise_ref,'PLATFORM')) STORED,
  role ENUM('SUPER_ADMIN','FRANCHISE_ADMIN','SALES','DISTRIBUTOR') NOT NULL,
  party_ref VARCHAR(24) NULL,
  full_name VARCHAR(191) NOT NULL,
  email VARCHAR(191) NOT NULL,
  mobile VARCHAR(20) NULL,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','INACTIVE','LOCKED') NOT NULL DEFAULT 'ACTIVE',
  last_login_at DATETIME NULL,
  failed_login_count INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_by_ref VARCHAR(24) NOT NULL,
  updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_ref (user_ref),
  UNIQUE KEY uq_user_email_tenant (tenant_key, email),
  UNIQUE KEY uq_user_pair (franchise_ref, user_ref),
  INDEX idx_user_tenant (org_ref, franchise_ref, role, status),
  CONSTRAINT fk_user_org FOREIGN KEY (org_ref) REFERENCES organizations(org_ref),
  CONSTRAINT chk_user_scope CHECK (
    (role = 'SUPER_ADMIN' AND franchise_ref IS NULL) OR (role <> 'SUPER_ADMIN' AND franchise_ref IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE oauth_clients (
  client_id VARCHAR(32) PRIMARY KEY,
  surface ENUM('super','admin','sales','portal') NOT NULL,
  allowed_roles VARCHAR(120) NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_ref VARCHAR(24) NOT NULL,
  family_ref VARCHAR(24) NOT NULL,
  user_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NULL,
  client_id VARCHAR(32) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NULL,
  issued_at DATETIME NOT NULL,
  abs_expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  revoke_reason VARCHAR(40) NULL,
  UNIQUE KEY uq_session_ref (session_ref),
  INDEX idx_session_user (user_ref, revoked_at),
  INDEX idx_session_family (family_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE oauth_refresh_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  session_ref VARCHAR(24) NOT NULL,
  family_ref VARCHAR(24) NOT NULL,
  user_ref VARCHAR(24) NOT NULL,
  status ENUM('ACTIVE','USED','REVOKED') NOT NULL DEFAULT 'ACTIVE',
  issued_at DATETIME NOT NULL,
  idle_expires_at DATETIME NOT NULL,
  abs_expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  UNIQUE KEY uq_rt_hash (token_hash),
  INDEX idx_rt_family (family_ref, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191) NOT NULL,
  tenant_key VARCHAR(24) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  outcome ENUM('SUCCESS','FAIL','LOCKED') NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_la_email (email, tenant_key, attempted_at),
  INDEX idx_la_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  user_ref VARCHAR(24) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  UNIQUE KEY uq_pr_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_permissions (
  role ENUM('SUPER_ADMIN','FRANCHISE_ADMIN','SALES','DISTRIBUTOR') NOT NULL,
  permission_code VARCHAR(64) NOT NULL,
  scope ENUM('GLOBAL','TENANT','ASSIGNED','OWN') NOT NULL,
  PRIMARY KEY (role, permission_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rate_limits (
  bucket_key CHAR(64) NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket_key, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.3 Global Reference Data
CREATE TABLE states (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  state_ref VARCHAR(24) NOT NULL UNIQUE,
  state_code VARCHAR(8) NOT NULL UNIQUE,
  state_name VARCHAR(120) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE districts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  district_ref VARCHAR(24) NOT NULL UNIQUE,
  state_ref VARCHAR(24) NOT NULL,
  district_name VARCHAR(120) NOT NULL,
  INDEX idx_d_state (state_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  city_ref VARCHAR(24) NOT NULL UNIQUE,
  district_ref VARCHAR(24) NOT NULL,
  city_name VARCHAR(120) NOT NULL,
  INDEX idx_c_district (district_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pincodes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pincode CHAR(6) NOT NULL,
  city_ref VARCHAR(24) NULL,
  district_ref VARCHAR(24) NOT NULL,
  state_ref VARCHAR(24) NOT NULL,
  UNIQUE KEY uq_pincode (pincode),
  INDEX idx_p_district (district_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.4 Sequences, Idempotency, Settings
CREATE TABLE sequence_counters (
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  counter_key VARCHAR(32) NOT NULL,
  period_key VARCHAR(8) NOT NULL,
  last_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (franchise_ref, counter_key, period_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE api_idempotency_keys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  idempotency_key VARCHAR(120) NOT NULL,
  method VARCHAR(8) NOT NULL,
  path VARCHAR(191) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  response_status SMALLINT NULL,
  response_json JSON NULL,
  status ENUM('IN_PROGRESS','COMPLETED','FAILED') NOT NULL DEFAULT 'IN_PROGRESS',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uq_idem (franchise_ref, idempotency_key),
  INDEX idx_idem_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE system_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  setting_key VARCHAR(64) NOT NULL,
  setting_value VARCHAR(500) NOT NULL,
  updated_by_ref VARCHAR(24) NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_setting (franchise_ref, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.5 Catalogue, Pricing, Schemes
CREATE TABLE product_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  category_name VARCHAR(120) NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat_ref (franchise_ref, category_ref),
  UNIQUE KEY uq_cat_name (franchise_ref, category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  sku VARCHAR(64) NOT NULL,
  product_name VARCHAR(191) NOT NULL,
  category_ref VARCHAR(24) NULL,
  composition VARCHAR(255) NULL, pack_size VARCHAR(64) NULL, dosage_form VARCHAR(64) NULL,
  mrp DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  pts DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  franchise_rate DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  hsn_code VARCHAR(16) NULL,
  shelf_life_days INT NOT NULL DEFAULT 0,
  storage_requirement VARCHAR(120) NULL,
  scheme_eligible TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_ref (franchise_ref, product_ref),
  UNIQUE KEY uq_product_sku (franchise_ref, sku),
  INDEX idx_product_list (franchise_ref, status, product_name),
  CONSTRAINT fk_prod_cat FOREIGN KEY (franchise_ref, category_ref) REFERENCES product_categories(franchise_ref, category_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pricing_tiers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tier_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  tier_name VARCHAR(80) NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tier_ref (franchise_ref, tier_ref),
  UNIQUE KEY uq_tier_name (franchise_ref, tier_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_prices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  price_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  product_ref VARCHAR(24) NOT NULL,
  tier_ref VARCHAR(24) NULL,
  party_ref VARCHAR(24) NULL,
  rate DECIMAL(18,2) NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  effective_from DATE NOT NULL, effective_to DATE NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_price_ref (franchise_ref, price_ref),
  INDEX idx_price_lookup (franchise_ref, product_ref, status, effective_from, effective_to),
  CONSTRAINT fk_pp_prod FOREIGN KEY (franchise_ref, product_ref) REFERENCES products(franchise_ref, product_ref),
  CONSTRAINT chk_pp_target CHECK (NOT (tier_ref IS NOT NULL AND party_ref IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE schemes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scheme_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  scheme_name VARCHAR(191) NOT NULL,
  start_date DATE NOT NULL, end_date DATE NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  stacking_allowed TINYINT(1) NOT NULL DEFAULT 0,
  tier_ref VARCHAR(24) NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_scheme_ref (franchise_ref, scheme_ref),
  INDEX idx_scheme_active (franchise_ref, status, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scheme_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  scheme_ref VARCHAR(24) NOT NULL, product_ref VARCHAR(24) NOT NULL,
  min_qty INT NOT NULL, max_qty INT NULL, free_qty INT NOT NULL,
  UNIQUE KEY uq_rule_ref (franchise_ref, rule_ref),
  INDEX idx_rule_lookup (franchise_ref, product_ref, scheme_ref),
  CONSTRAINT fk_sr_scheme FOREIGN KEY (franchise_ref, scheme_ref) REFERENCES schemes(franchise_ref, scheme_ref),
  CONSTRAINT fk_sr_prod FOREIGN KEY (franchise_ref, product_ref) REFERENCES products(franchise_ref, product_ref),
  CONSTRAINT chk_sr_qty CHECK (min_qty > 0 AND free_qty > 0 AND (max_qty IS NULL OR max_qty >= min_qty))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.6 Parties, Territory, Onboarding
CREATE TABLE parties (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  party_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  party_code VARCHAR(32) NOT NULL,
  firm_name VARCHAR(191) NOT NULL, contact_name VARCHAR(191) NULL,
  mobile VARCHAR(20) NULL, email VARCHAR(191) NULL,
  gstin VARCHAR(15) NULL, drug_license_no VARCHAR(64) NULL,
  billing_address TEXT NULL, shipping_address TEXT NULL,
  state_ref VARCHAR(24) NULL, district_ref VARCHAR(24) NULL, city_ref VARCHAR(24) NULL, pincode CHAR(6) NULL,
  tier_ref VARCHAR(24) NULL,
  sales_user_ref VARCHAR(24) NULL,
  agreement_from DATE NULL, agreement_to DATE NULL,
  credit_limit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  payment_terms_days INT NOT NULL DEFAULT 0,
  opening_outstanding DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  converted_from_lead_ref VARCHAR(24) NULL,
  status ENUM('ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_party_ref (franchise_ref, party_ref),
  UNIQUE KEY uq_party_code (franchise_ref, party_code),
  INDEX idx_party_list (franchise_ref, status, firm_name),
  INDEX idx_party_mobile (franchise_ref, mobile),
  INDEX idx_party_gstin (franchise_ref, gstin),
  INDEX idx_party_sales (franchise_ref, sales_user_ref, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE party_territories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  territory_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  party_ref VARCHAR(24) NOT NULL,
  level ENUM('PINCODE','DISTRICT') NOT NULL,
  pincode CHAR(6) NULL, district_ref VARCHAR(24) NULL,
  effective_from DATE NOT NULL, effective_to DATE NULL,
  is_exclusive TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_terr_ref (franchise_ref, territory_ref),
  INDEX idx_terr_party (franchise_ref, party_ref, status, effective_from, effective_to),
  INDEX idx_terr_pin (franchise_ref, pincode, status),
  INDEX idx_terr_dist (franchise_ref, district_ref, status),
  CONSTRAINT fk_pt_party FOREIGN KEY (franchise_ref, party_ref) REFERENCES parties(franchise_ref, party_ref),
  CONSTRAINT chk_pt_level CHECK ((level='PINCODE' AND pincode IS NOT NULL) OR (level='DISTRICT' AND district_ref IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE territory_overrides (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  override_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  order_ref VARCHAR(24) NOT NULL, party_ref VARCHAR(24) NOT NULL, pincode CHAR(6) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  approved_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ovr_ref (franchise_ref, override_ref),
  INDEX idx_ovr_order (franchise_ref, order_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE onboarding_invites (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invite_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  lead_ref VARCHAR(24) NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL, used_at DATETIME NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_invite_ref (franchise_ref, invite_ref),
  UNIQUE KEY uq_invite_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.7 Leads & Follow-ups
CREATE TABLE leads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  external_source_ref VARCHAR(24) NULL,
  external_lead_id VARCHAR(120) NULL,
  source_key VARCHAR(24) GENERATED ALWAYS AS (IFNULL(external_source_ref,'MANUAL')) STORED,
  ext_key VARCHAR(120) GENERATED ALWAYS AS (IF(external_lead_id IS NULL, CONCAT('~',lead_ref), external_lead_id)) STORED,
  contact_name VARCHAR(191) NOT NULL, firm_name VARCHAR(191) NULL,
  mobile VARCHAR(20) NOT NULL, mobile_norm VARCHAR(15) NOT NULL, email VARCHAR(191) NULL,
  state_ref VARCHAR(24) NULL, district_ref VARCHAR(24) NULL, city_ref VARCHAR(24) NULL, pincode CHAR(6) NULL,
  lead_source VARCHAR(80) NULL, business_type VARCHAR(80) NULL, interested_products TEXT NULL,
  assigned_user_ref VARCHAR(24) NULL,
  priority ENUM('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  status ENUM('NEW','ASSIGNED','CONTACTED','INTERESTED','FOLLOW_UP','DOCUMENTS_PENDING','QUALIFIED','CONVERTED','LOST','REJECTED','ARCHIVED') NOT NULL DEFAULT 'NEW',
  initial_remark TEXT NULL,
  first_response_at DATETIME NULL,
  next_follow_up_at DATETIME NULL,
  converted_party_ref VARCHAR(24) NULL,
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lead_ref (franchise_ref, lead_ref),
  UNIQUE KEY uq_lead_ext (franchise_ref, source_key, ext_key),
  INDEX idx_lead_assign (franchise_ref, assigned_user_ref, status, next_follow_up_at),
  INDEX idx_lead_mobile (franchise_ref, mobile_norm),
  INDEX idx_lead_status (franchise_ref, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE follow_ups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  followup_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  lead_ref VARCHAR(24) NULL, party_ref VARCHAR(24) NULL,
  assigned_user_ref VARCHAR(24) NOT NULL,
  activity_type ENUM('CALL','VISIT','WHATSAPP','EMAIL','MEETING','OTHER') NOT NULL,
  next_action VARCHAR(191) NOT NULL,
  next_follow_up_at DATETIME NOT NULL,
  status ENUM('PENDING','COMPLETED','MISSED','RESCHEDULED','CLOSED') NOT NULL DEFAULT 'PENDING',
  remark TEXT NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fu_ref (franchise_ref, followup_ref),
  INDEX idx_fu_queue (franchise_ref, assigned_user_ref, status, next_follow_up_at),
  CONSTRAINT chk_fu_target CHECK (lead_ref IS NOT NULL OR party_ref IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  activity_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  lead_ref VARCHAR(24) NOT NULL, user_ref VARCHAR(24) NOT NULL,
  activity_type VARCHAR(40) NOT NULL, from_status VARCHAR(24) NULL, to_status VARCHAR(24) NULL,
  activity_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_act_ref (franchise_ref, activity_ref),
  INDEX idx_act_lead (franchise_ref, lead_ref, created_at),
  CONSTRAINT fk_la_lead FOREIGN KEY (franchise_ref, lead_ref) REFERENCES leads(franchise_ref, lead_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.8 Inventory
CREATE TABLE inventory_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  product_ref VARCHAR(24) NOT NULL,
  batch_no VARCHAR(64) NOT NULL,
  manufacturing_date DATE NULL, expiry_date DATE NOT NULL,
  received_qty INT NOT NULL DEFAULT 0,
  on_hand_qty INT NOT NULL DEFAULT 0,
  reserved_qty INT NOT NULL DEFAULT 0,
  damaged_qty INT NOT NULL DEFAULT 0,
  location_code VARCHAR(40) NULL,
  status ENUM('SALEABLE','QUARANTINE','RECALLED','EXPIRED','DAMAGED') NOT NULL DEFAULT 'SALEABLE',
  version INT NOT NULL DEFAULT 1,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_batch_ref (franchise_ref, batch_ref),
  UNIQUE KEY uq_batch_no (franchise_ref, product_ref, batch_no),
  INDEX idx_batch_fefo (franchise_ref, product_ref, status, expiry_date),
  CONSTRAINT fk_ib_prod FOREIGN KEY (franchise_ref, product_ref) REFERENCES products(franchise_ref, product_ref),
  CONSTRAINT chk_ib_qty CHECK (on_hand_qty >= 0 AND reserved_qty >= 0 AND reserved_qty <= on_hand_qty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  movement_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  batch_ref VARCHAR(24) NOT NULL,
  movement_type ENUM('RECEIPT','SALE','RESERVE','RELEASE','RETURN','DAMAGE','EXPIRY','ADJUST','TRANSFER') NOT NULL,
  qty INT NOT NULL,
  reference_type VARCHAR(40) NULL, reference_ref VARCHAR(24) NULL,
  remarks VARCHAR(255) NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mov_ref (franchise_ref, movement_ref),
  INDEX idx_mov_batch (franchise_ref, batch_ref, created_at),
  CONSTRAINT fk_im_batch FOREIGN KEY (franchise_ref, batch_ref) REFERENCES inventory_batches(franchise_ref, batch_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  batch_ref VARCHAR(24) NOT NULL, order_ref VARCHAR(24) NOT NULL, order_item_ref VARCHAR(24) NOT NULL,
  reserved_qty INT NOT NULL,
  status ENUM('ACTIVE','RELEASED','CONSUMED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_res_ref (franchise_ref, reservation_ref),
  INDEX idx_res_order (franchise_ref, order_ref, status),
  INDEX idx_res_batch (franchise_ref, batch_ref, status),
  CONSTRAINT fk_sr_batch FOREIGN KEY (franchise_ref, batch_ref) REFERENCES inventory_batches(franchise_ref, batch_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.9 Orders
CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_ref VARCHAR(24) NOT NULL,
  order_no VARCHAR(32) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  client_order_ref VARCHAR(64) NOT NULL,
  party_ref VARCHAR(24) NOT NULL,
  sales_user_ref VARCHAR(24) NULL,
  channel ENUM('PORTAL','SALES','ADMIN') NOT NULL,
  order_date DATE NOT NULL,
  shipping_address TEXT NULL, shipping_pincode CHAR(6) NULL,
  status ENUM('DRAFT','SUBMITTED','CONFIRMED','PROCESSING','DISPATCHED','DELIVERED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  territory_status ENUM('OK','OVERRIDDEN','BLOCKED') NOT NULL DEFAULT 'OK',
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  version INT NOT NULL DEFAULT 1,
  remarks VARCHAR(255) NULL,
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_ref (franchise_ref, order_ref),
  UNIQUE KEY uq_order_client (franchise_ref, client_order_ref),
  UNIQUE KEY uq_order_no (franchise_ref, order_no),
  INDEX idx_order_status (franchise_ref, status, order_date),
  INDEX idx_order_party (franchise_ref, party_ref, created_at),
  CONSTRAINT fk_ord_party FOREIGN KEY (franchise_ref, party_ref) REFERENCES parties(franchise_ref, party_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  order_ref VARCHAR(24) NOT NULL, product_ref VARCHAR(24) NOT NULL,
  paid_qty INT NOT NULL, free_qty INT NOT NULL DEFAULT 0,
  rate DECIMAL(18,2) NOT NULL, rate_source ENUM('PARTY','TIER','DEFAULT','OVERRIDE') NOT NULL, price_ref VARCHAR(24) NULL,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(18,2) NOT NULL,
  scheme_ref VARCHAR(24) NULL,
  UNIQUE KEY uq_oi_ref (franchise_ref, item_ref),
  INDEX idx_oi_order (franchise_ref, order_ref),
  INDEX idx_oi_product (franchise_ref, product_ref),
  CONSTRAINT fk_oi_order FOREIGN KEY (franchise_ref, order_ref) REFERENCES orders(franchise_ref, order_ref),
  CONSTRAINT fk_oi_prod FOREIGN KEY (franchise_ref, product_ref) REFERENCES products(franchise_ref, product_ref),
  CONSTRAINT chk_oi_qty CHECK (paid_qty > 0 AND free_qty >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  order_ref VARCHAR(24) NOT NULL, from_status VARCHAR(24) NULL, to_status VARCHAR(24) NOT NULL,
  actor_ref VARCHAR(24) NOT NULL, reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_osh (franchise_ref, order_ref, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.10 Billing, Dispatch, Payments
CREATE TABLE invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_ref VARCHAR(24) NOT NULL, invoice_no VARCHAR(32) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  order_ref VARCHAR(24) NOT NULL, party_ref VARCHAR(24) NOT NULL,
  invoice_date DATE NOT NULL, due_date DATE NULL,
  bill_to_snapshot JSON NOT NULL, ship_to_snapshot JSON NOT NULL,
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0.00, discount_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  taxable_total DECIMAL(18,2) NOT NULL DEFAULT 0.00, cgst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00, sgst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00, igst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00, rounding_adjustment DECIMAL(18,2) NOT NULL DEFAULT 0.00, tax_policy_code VARCHAR(40) NULL, grand_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  paid_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  status ENUM('POSTED','CANCELLED') NOT NULL DEFAULT 'POSTED',
  cancel_reason VARCHAR(255) NULL, cancelled_by_ref VARCHAR(24) NULL, cancelled_at DATETIME NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inv_ref (franchise_ref, invoice_ref),
  UNIQUE KEY uq_inv_no (franchise_ref, invoice_no),
  INDEX idx_inv_party (franchise_ref, party_ref, invoice_date),
  UNIQUE KEY uq_inv_order (franchise_ref, order_ref),
  INDEX idx_inv_order (franchise_ref, order_ref), INDEX idx_inv_status (franchise_ref, status, invoice_date),
  CONSTRAINT fk_inv_order FOREIGN KEY (franchise_ref, order_ref) REFERENCES orders(franchise_ref, order_ref),
  CONSTRAINT chk_inv_paid CHECK (paid_total >= 0 AND paid_total <= grand_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  invoice_ref VARCHAR(24) NOT NULL, order_item_ref VARCHAR(24) NOT NULL, product_ref VARCHAR(24) NOT NULL,
  product_name_snapshot VARCHAR(191) NOT NULL, sku_snapshot VARCHAR(64) NOT NULL, hsn_snapshot VARCHAR(16) NULL,
  batch_ref VARCHAR(24) NOT NULL, batch_no_snapshot VARCHAR(64) NOT NULL, expiry_snapshot DATE NOT NULL,
  paid_qty INT NOT NULL, free_qty INT NOT NULL DEFAULT 0,
  rate DECIMAL(18,2) NOT NULL, discount DECIMAL(18,2) NOT NULL DEFAULT 0.00, taxable_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00, cgst_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00, sgst_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00, igst_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00, total_tax DECIMAL(18,2) NOT NULL DEFAULT 0.00, line_total DECIMAL(18,2) NOT NULL,
  UNIQUE KEY uq_ii_ref (franchise_ref, item_ref),
  INDEX idx_ii_invoice (franchise_ref, invoice_ref),
  CONSTRAINT fk_ii_inv FOREIGN KEY (franchise_ref, invoice_ref) REFERENCES invoices(franchise_ref, invoice_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transporters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transporter_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  transporter_name VARCHAR(191) NOT NULL, tracking_url_template VARCHAR(255) NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  UNIQUE KEY uq_tr_ref (franchise_ref, transporter_ref),
  UNIQUE KEY uq_tr_name (franchise_ref, transporter_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dispatches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispatch_ref VARCHAR(24) NOT NULL, dispatch_no VARCHAR(32) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  invoice_ref VARCHAR(24) NOT NULL, transporter_ref VARCHAR(24) NULL,
  lr_number VARCHAR(64) NULL, tracking_url VARCHAR(255) NULL,
  dispatch_date DATE NULL, delivered_at DATETIME NULL, boxes INT NOT NULL DEFAULT 0,
  status ENUM('PENDING','PACKING','READY','DISPATCHED','IN_TRANSIT','DELIVERED','FAILED','RETURNED') NOT NULL DEFAULT 'PENDING',
  remarks VARCHAR(255) NULL, delivery_remarks VARCHAR(255) NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dsp_ref (franchise_ref, dispatch_ref),
  UNIQUE KEY uq_dsp_no (franchise_ref, dispatch_no),
  INDEX idx_dsp_inv (franchise_ref, invoice_ref),
  CONSTRAINT fk_dsp_inv FOREIGN KEY (franchise_ref, invoice_ref) REFERENCES invoices(franchise_ref, invoice_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dispatch_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  dispatch_ref VARCHAR(24) NOT NULL, from_status VARCHAR(24) NULL, to_status VARCHAR(24) NOT NULL,
  actor_ref VARCHAR(24) NOT NULL, reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dsh_dispatch (franchise_ref, dispatch_ref, created_at),
  CONSTRAINT fk_dsh_dispatch FOREIGN KEY (franchise_ref, dispatch_ref) REFERENCES dispatches(franchise_ref, dispatch_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_ref VARCHAR(24) NOT NULL, payment_no VARCHAR(32) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  party_ref VARCHAR(24) NOT NULL,
  payment_date DATE NOT NULL, amount DECIMAL(18,2) NOT NULL,
  allocated_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  mode ENUM('CASH','UPI','NEFT','RTGS','CHEQUE','PDC','OTHER') NOT NULL,
  reference_no VARCHAR(80) NULL, remarks VARCHAR(255) NULL,
  status ENUM('RECORDED','PARTIALLY_ALLOCATED','ALLOCATED','REVERSED','CANCELLED') NOT NULL DEFAULT 'RECORDED',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pay_ref (franchise_ref, payment_ref),
  UNIQUE KEY uq_pay_no (franchise_ref, payment_no),
  INDEX idx_pay_party (franchise_ref, party_ref, payment_date),
  CONSTRAINT fk_pay_party FOREIGN KEY (franchise_ref, party_ref) REFERENCES parties(franchise_ref, party_ref),
  CONSTRAINT chk_pay_amt CHECK (amount > 0 AND allocated_amount >= 0 AND allocated_amount <= amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payment_allocations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  allocation_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  payment_ref VARCHAR(24) NOT NULL, invoice_ref VARCHAR(24) NOT NULL,
  allocated_amount DECIMAL(18,2) NOT NULL,
  status ENUM('ACTIVE','REVERSED') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alloc_ref (franchise_ref, allocation_ref),
  INDEX idx_alloc_pay (franchise_ref, payment_ref),
  INDEX idx_alloc_inv (franchise_ref, invoice_ref),
  CONSTRAINT fk_al_pay FOREIGN KEY (franchise_ref, payment_ref) REFERENCES payments(franchise_ref, payment_ref),
  CONSTRAINT fk_al_inv FOREIGN KEY (franchise_ref, invoice_ref) REFERENCES invoices(franchise_ref, invoice_ref),
  CONSTRAINT chk_al_amt CHECK (allocated_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8.11 Integrations, Notifications, Jobs, Audit
CREATE TABLE webhook_sources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  source_name VARCHAR(80) NOT NULL, endpoint_slug VARCHAR(80) NOT NULL,
  auth_type ENUM('HMAC','BEARER') NOT NULL DEFAULT 'HMAC',
  secret_enc VARBINARY(512) NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ws_ref (franchise_ref, source_ref),
  UNIQUE KEY uq_ws_slug (endpoint_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  source_ref VARCHAR(24) NOT NULL, external_event_id VARCHAR(120) NOT NULL,
  payload_hash CHAR(64) NOT NULL, payload_json JSON NOT NULL, signature VARCHAR(128) NULL,
  status ENUM('RECEIVED','PROCESSED','FAILED','DUPLICATE','DEAD') NOT NULL DEFAULT 'RECEIVED',
  attempt_count INT NOT NULL DEFAULT 0, error_code VARCHAR(64) NULL, error_message VARCHAR(255) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, processed_at DATETIME NULL,
  UNIQUE KEY uq_we_ref (franchise_ref, event_ref),
  UNIQUE KEY uq_we_ext (franchise_ref, source_ref, external_event_id),
  INDEX idx_we_status (franchise_ref, status, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notification_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  event_type VARCHAR(64) NOT NULL, channel ENUM('INAPP','WHATSAPP','EMAIL','SMS') NOT NULL,
  body_template TEXT NOT NULL, status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  UNIQUE KEY uq_nt (franchise_ref, event_type, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  user_ref VARCHAR(24) NULL, party_ref VARCHAR(24) NULL,
  channel ENUM('INAPP','WHATSAPP','EMAIL','SMS') NOT NULL, event_type VARCHAR(64) NOT NULL,
  entity_type VARCHAR(40) NULL, entity_ref VARCHAR(24) NULL, payload_json JSON NULL,
  status ENUM('QUEUED','SENT','DELIVERED','READ','FAILED') NOT NULL DEFAULT 'QUEUED',
  provider_message_id VARCHAR(120) NULL,
  idempotency_key VARCHAR(160) NOT NULL, attempt_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, read_at DATETIME NULL,
  UNIQUE KEY uq_notif_ref (franchise_ref, notification_ref),
  UNIQUE KEY uq_notify_idem (franchise_ref, idempotency_key),
  INDEX idx_notif_user (franchise_ref, user_ref, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE job_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NULL, franchise_ref VARCHAR(24) NULL,
  job_type VARCHAR(64) NOT NULL, payload_json JSON NOT NULL,
  dedupe_key VARCHAR(120) NULL,
  status ENUM('PENDING','RUNNING','SUCCESS','FAILED','RETRY_WAIT','DEAD') NOT NULL DEFAULT 'PENDING',
  attempts INT NOT NULL DEFAULT 0, max_attempts INT NOT NULL DEFAULT 5,
  run_at DATETIME NOT NULL, locked_at DATETIME NULL, last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_ref (job_ref),
  UNIQUE KEY uq_job_dedupe (job_type, dedupe_key),
  INDEX idx_job_run (status, run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  audit_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NULL, franchise_ref VARCHAR(24) NULL,
  actor_ref VARCHAR(24) NULL, actor_role VARCHAR(40) NULL, impersonator_ref VARCHAR(24) NULL,
  category ENUM('BUSINESS','SECURITY','TENANT_BYPASS','SYSTEM') NOT NULL DEFAULT 'BUSINESS',
  action VARCHAR(80) NOT NULL, entity_type VARCHAR(40) NOT NULL, entity_ref VARCHAR(24) NULL,
  before_json JSON NULL, after_json JSON NULL, reason VARCHAR(255) NULL,
  ip_address VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, request_id VARCHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_audit_ref (audit_ref),
  INDEX idx_audit_entity (franchise_ref, entity_type, entity_ref),
  INDEX idx_audit_actor (franchise_ref, actor_ref, created_at),
  INDEX idx_audit_cat (category, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TRIGGER IF EXISTS audit_prevent_update;
DROP TRIGGER IF EXISTS audit_prevent_delete;

CREATE TRIGGER audit_prevent_update BEFORE UPDATE ON audit_logs
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only';

CREATE TRIGGER audit_prevent_delete BEFORE DELETE ON audit_logs
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_logs is append-only';

SET FOREIGN_KEY_CHECKS = 1;
