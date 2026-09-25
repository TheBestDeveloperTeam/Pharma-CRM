-- TASK-009: distributor onboarding, KYC metadata and online DCR workflow.
-- This migration is additive; it deliberately contains no KYC retention/deletion job.

ALTER TABLE onboarding_invites ADD COLUMN IF NOT EXISTS assigned_user_ref VARCHAR(24) NULL AFTER lead_ref;

CREATE TABLE IF NOT EXISTS onboarding_registrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  onboarding_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  invite_ref VARCHAR(24) NOT NULL,
  lead_ref VARCHAR(24) NULL,
  party_ref VARCHAR(24) NULL,
  assigned_user_ref VARCHAR(24) NULL,
  status ENUM('SUBMITTED','INFO_REQUESTED','APPROVED','REJECTED') NOT NULL DEFAULT 'SUBMITTED',
  firm_name VARCHAR(255) NOT NULL, constitution_type VARCHAR(32) NOT NULL,
  contact_name VARCHAR(255) NOT NULL, designation VARCHAR(128) NOT NULL,
  mobile VARCHAR(32) NOT NULL, email VARCHAR(255) NOT NULL,
  gstin VARCHAR(32) NOT NULL, drug_license_no VARCHAR(128) NOT NULL,
  drug_license_validity DATE NOT NULL, pan VARCHAR(32) NOT NULL,
  billing_address TEXT NOT NULL, shipping_address TEXT NOT NULL,
  state_ref VARCHAR(24) NULL, district_ref VARCHAR(24) NULL, city_ref VARCHAR(24) NULL,
  area VARCHAR(255) NULL, pincode VARCHAR(16) NOT NULL,
  bank_account_number VARCHAR(128) NULL, bank_ifsc VARCHAR(32) NULL, bank_name VARCHAR(255) NULL,
  preferred_product_categories_json JSON NOT NULL,
  expected_monthly_business DECIMAL(14,2) NULL,
  password_hash VARCHAR(255) NOT NULL,
  reviewer_remarks TEXT NULL, reviewed_by_ref VARCHAR(24) NULL, reviewed_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  UNIQUE KEY uq_onboarding_ref (onboarding_ref), UNIQUE KEY uq_onboarding_invite (invite_ref),
  INDEX idx_onboarding_tenant_status (franchise_ref, status), INDEX idx_onboarding_assignee (franchise_ref, assigned_user_ref),
  INDEX idx_onboarding_party (franchise_ref, party_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS onboarding_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  onboarding_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  from_status VARCHAR(32) NULL, to_status VARCHAR(32) NOT NULL, actor_ref VARCHAR(24) NOT NULL,
  remarks TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_onboarding_history (franchise_ref, onboarding_ref, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kyc_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kyc_document_ref VARCHAR(24) NOT NULL, onboarding_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  document_type ENUM('DRUG_LICENCE','GST_CERTIFICATE','PAN','CANCELLED_CHEQUE','INCORPORATION_CERTIFICATE') NOT NULL,
  file_reference VARCHAR(512) NOT NULL, original_filename VARCHAR(255) NOT NULL,
  status ENUM('PENDING','VERIFIED','REJECTED') NOT NULL DEFAULT 'PENDING',
  verification_remarks TEXT NULL, uploaded_by_ref VARCHAR(24) NOT NULL, uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  verified_by_ref VARCHAR(24) NULL, verified_at DATETIME NULL,
  UNIQUE KEY uq_kyc_document_ref (kyc_document_ref), UNIQUE KEY uq_kyc_document_type (onboarding_ref, document_type),
  INDEX idx_kyc_document_tenant (franchise_ref, onboarding_ref, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS kyc_document_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kyc_document_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  from_status VARCHAR(32) NULL, to_status VARCHAR(32) NOT NULL, actor_ref VARCHAR(24) NOT NULL,
  remarks TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_kyc_history (franchise_ref, kyc_document_ref, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dcr_reports_v2 (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dcr_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  distributor_party_ref VARCHAR(24) NOT NULL, owner_user_ref VARCHAR(24) NOT NULL,
  report_date DATE NOT NULL, work_type ENUM('FIELD_WORK','LEAVE','HOLIDAY','MEETING','TRAINING') NOT NULL,
  beat VARCHAR(255) NOT NULL, status ENUM('DRAFT','SUBMITTED','APPROVED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  submitted_at DATETIME NULL, reviewed_by_ref VARCHAR(24) NULL, reviewed_at DATETIME NULL, reviewer_remarks TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL,
  UNIQUE KEY uq_dcr_ref (dcr_ref), UNIQUE KEY uq_dcr_owner_date (owner_user_ref, report_date),
  INDEX idx_dcr_tenant_owner (franchise_ref, distributor_party_ref, owner_user_ref, report_date),
  INDEX idx_dcr_tenant_status (franchise_ref, status, report_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dcr_visits_v2 (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  visit_ref VARCHAR(24) NOT NULL, dcr_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  party_ref VARCHAR(24) NULL, lead_ref VARCHAR(24) NULL, customer_type VARCHAR(32) NOT NULL,
  visit_time TIME NOT NULL, visit_purpose VARCHAR(255) NOT NULL, products_promoted_json JSON NOT NULL,
  samples_given_json JSON NULL, pob_product_ref VARCHAR(24) NULL, pob_quantity INT UNSIGNED NULL, pob_value DECIMAL(14,2) NULL,
  feedback TEXT NULL, next_visit_date DATE NULL, photo_file_reference VARCHAR(512) NULL,
  CHECK ((party_ref IS NOT NULL) <> (lead_ref IS NOT NULL)),
  UNIQUE KEY uq_dcr_visit_ref (visit_ref), INDEX idx_dcr_visits_report (franchise_ref, dcr_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS dcr_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dcr_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  from_status VARCHAR(32) NULL, to_status VARCHAR(32) NOT NULL, actor_ref VARCHAR(24) NOT NULL,
  remarks TEXT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_dcr_history (franchise_ref, dcr_ref, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Permission catalogue only. Roles are assigned by the existing TASK-001 role management flow.
INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, action_label, is_sensitive) VALUES
('distributorOnboarding','create','Create registration',0),('distributorOnboarding','edit','Edit registration',0),('distributorOnboarding','convert','Convert to Party',1),
('kyc','view','View KYC',1),('kyc','upload','Register KYC document',1),('kyc','verify','Verify KYC document',1),('kyc','reject','Reject KYC document',1),
('dcr','view','View DCR',0),('dcr','create','Create DCR',0),('dcr','edit','Edit DCR',0),('dcr','submit','Submit DCR',0),('dcr','approve','Approve DCR',1),('dcr','reject','Reject DCR',0);
