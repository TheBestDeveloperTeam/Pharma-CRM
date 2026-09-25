-- TASK-001: additive normalized authorization model.
-- Safe to run after 001_full_schema.sql. Existing users.role remains as a
-- compatibility field for surface authentication while auth_* tables become
-- the source of truth for permissions and scopes.

ALTER TABLE users ADD COLUMN IF NOT EXISTS employee_code VARCHAR(64) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS reporting_manager_ref VARCHAR(24) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS department VARCHAR(120) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS designation VARCHAR(120) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS assigned_region VARCHAR(191) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS joining_date DATE NULL;
ALTER TABLE users ADD UNIQUE KEY IF NOT EXISTS uq_user_employee_code (franchise_ref, employee_code);
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_user_manager (franchise_ref, reporting_manager_ref);

CREATE TABLE IF NOT EXISTS auth_roles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NULL,
  role_name VARCHAR(120) NOT NULL,
  role_slug VARCHAR(120) NOT NULL,
  description VARCHAR(500) NULL,
  is_system TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  default_scope ENUM('ALL','TERRITORY','TEAM','OWN','NONE') NOT NULL DEFAULT 'NONE',
  created_by_ref VARCHAR(24) NOT NULL,
  updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_auth_role_ref (role_ref),
  UNIQUE KEY uq_auth_role_slug (franchise_ref, role_slug),
  INDEX idx_auth_role_tenant (org_ref, franchise_ref, status),
  CONSTRAINT fk_auth_role_org FOREIGN KEY (org_ref) REFERENCES organizations(org_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_permissions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  permission_ref VARCHAR(24) NOT NULL,
  module_key VARCHAR(64) NOT NULL,
  action_key VARCHAR(64) NOT NULL,
  permission_key VARCHAR(140) AS (CONCAT(module_key, ':', action_key)) STORED,
  label VARCHAR(160) NOT NULL,
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  UNIQUE KEY uq_auth_permission_ref (permission_ref),
  UNIQUE KEY uq_auth_permission_key (module_key, action_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_role_permissions (
  role_ref VARCHAR(24) NOT NULL,
  permission_ref VARCHAR(24) NOT NULL,
  granted_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (role_ref, permission_ref),
  CONSTRAINT fk_arp_role FOREIGN KEY (role_ref) REFERENCES auth_roles(role_ref),
  CONSTRAINT fk_arp_permission FOREIGN KEY (permission_ref) REFERENCES auth_permissions(permission_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_role_scopes (
  role_ref VARCHAR(24) NOT NULL,
  module_key VARCHAR(64) NOT NULL,
  data_scope ENUM('ALL','TERRITORY','TEAM','OWN','NONE') NOT NULL,
  PRIMARY KEY (role_ref, module_key),
  CONSTRAINT fk_ars_role FOREIGN KEY (role_ref) REFERENCES auth_roles(role_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_user_roles (
  user_ref VARCHAR(24) NOT NULL,
  role_ref VARCHAR(24) NOT NULL,
  assigned_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_ref, role_ref),
  CONSTRAINT fk_aur_user FOREIGN KEY (user_ref) REFERENCES users(user_ref),
  CONSTRAINT fk_aur_role FOREIGN KEY (role_ref) REFERENCES auth_roles(role_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_user_hierarchy (
  user_ref VARCHAR(24) NOT NULL PRIMARY KEY,
  manager_ref VARCHAR(24) NULL,
  assigned_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_auh_user FOREIGN KEY (user_ref) REFERENCES users(user_ref),
  CONSTRAINT fk_auh_manager FOREIGN KEY (manager_ref) REFERENCES users(user_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_user_territories (
  user_ref VARCHAR(24) NOT NULL,
  territory_ref VARCHAR(24) NOT NULL,
  assigned_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_ref, territory_ref),
  CONSTRAINT fk_aut_user FOREIGN KEY (user_ref) REFERENCES users(user_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_permission_catalogue (
  module_key VARCHAR(64) NOT NULL,
  action_key VARCHAR(64) NOT NULL,
  label VARCHAR(160) NOT NULL,
  is_sensitive TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (module_key, action_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive) VALUES
('dashboard','view','View',0),
('leads','view','View',0),('leads','create','Create',0),('leads','edit','Edit',0),('leads','archive','Archive',0),('leads','assign','Assign',0),('leads','convert','Convert',0),('leads','export','Export',0),
('followUps','view','View',0),('followUps','create','Create',0),('followUps','edit','Edit',0),('followUps','complete','Complete',0),('followUps','reschedule','Reschedule',0),('followUps','export','Export',0),
('parties','view','View',0),('parties','create','Create',0),('parties','edit','Edit',0),('parties','archive','Archive',0),('parties','activateDeactivate','Activate/Deactivate',0),('parties','export','Export',0),
('territory','view','View',0),('territory','allocate','Allocate',0),('territory','edit','Edit',0),('territory','override','Override',1),('territory','export','Export',0),
('products','view','View',0),('products','create','Create',0),('products','edit','Edit',0),('products','archive','Archive',0),('products','activateDeactivate','Activate/Deactivate',0),('products','export','Export',0),
('pricing','view','View',0),('pricing','create','Create',0),('pricing','edit','Edit',0),('pricing','priceOverride','Price Override',1),('pricing','export','Export',0),
('schemes','view','View',0),('schemes','create','Create',0),('schemes','edit','Edit',0),('schemes','deactivate','Deactivate',0),
('orders','view','View',0),('orders','create','Create',0),('orders','editDraft','Edit Draft',0),('orders','deleteDraft','Delete Draft',0),('orders','submit','Submit',0),('orders','cancel','Cancel',0),('orders','print','Print',0),('orders','export','Export',0),
('inventory','view','View',0),('inventory','create','Create',0),('inventory','edit','Edit',0),('inventory','adjust','Adjust',1),('inventory','transfer','Transfer',1),('inventory','manualBatchOverride','Manual Batch Override',1),('inventory','export','Export',0),
('nearExpiry','view','View',0),('nearExpiry','export','Export',0),('nearExpiry','configure','Configure',0),
('billing','view','View',0),('billing','create','Create',0),('billing','cancel','Cancel',1),('billing','print','Print',0),('billing','export','Export',0),
('dispatch','view','View',0),('dispatch','create','Create',0),('dispatch','edit','Edit',0),('dispatch','updateTracking','Update LR/Tracking',0),('dispatch','export','Export',0),
('payments','view','View',0),('payments','create','Create',0),('payments','edit','Edit',0),('payments','cancel','Cancel',1),('payments','archive','Archive',0),('payments','delete','Delete',1),('payments','allocate','Allocate',1),('payments','export','Export',0),
('distributorOnboarding','view','View',0),('distributorOnboarding','generateInvite','Generate Invite',0),('distributorOnboarding','resendRevokeInvite','Resend/Revoke Invite',0),('distributorOnboarding','review','Review',0),('distributorOnboarding','approve','Approve',1),('distributorOnboarding','reject','Reject',0),
('internalUsers','view','View',0),('internalUsers','create','Create',0),('internalUsers','edit','Edit',0),('internalUsers','activateDeactivate','Activate/Deactivate',0),('internalUsers','resetPassword','Reset Password',0),
('rolesAndPermissions','view','View',0),('rolesAndPermissions','create','Create',0),('rolesAndPermissions','edit','Edit',1),('rolesAndPermissions','delete','Delete',0),('rolesAndPermissions','assignToUser','Assign to User',0),
('masters','view','View',0),('masters','create','Create',0),('masters','edit','Edit',0),('masters','activateDeactivate','Activate/Deactivate',0),
('reports','view','View',0),('reports','export','Export',0),('reports','print','Print',0),('auditLogs','view','View',1),('auditLogs','export','Export',0),
('notifications','view','View',0),('notifications','sendRetry','Send/Retry',0),('notifications','manageTemplates','Manage Templates',0),('webhooks','view','View',0),('webhooks','configure','Configure',0),('webhooks','retryFailed','Retry Failed',0);

INSERT IGNORE INTO auth_permissions (permission_ref, module_key, action_key, label, is_sensitive)
SELECT CONCAT('PER-', UPPER(SUBSTRING(SHA2(CONCAT(module_key, ':', action_key), 256), 1, 20)), '') , module_key, action_key, label, is_sensitive
FROM auth_permission_catalogue;

-- Seed the protected Admin role for each existing franchise. Existing users are
-- mapped below without changing users.role, so legacy surface checks continue to work.
INSERT IGNORE INTO auth_roles
  (role_ref, org_ref, franchise_ref, role_name, role_slug, description, is_system, status, default_scope, created_by_ref)
SELECT CONCAT('ROLE-', UPPER(SUBSTRING(SHA2(CONCAT(f.franchise_ref, ':admin'), 256), 1, 20))),
       f.org_ref, f.franchise_ref, 'Admin', 'admin', 'Protected full-access system role', 1, 'ACTIVE', 'ALL',
       COALESCE((SELECT u.user_ref FROM users u WHERE u.franchise_ref = f.franchise_ref AND u.role = 'FRANCHISE_ADMIN' ORDER BY u.created_at LIMIT 1), 'SYSTEM')
FROM franchises f WHERE f.status = 'ACTIVE';

INSERT IGNORE INTO auth_user_roles (user_ref, role_ref, assigned_by_ref)
SELECT u.user_ref, r.role_ref, u.user_ref
FROM users u JOIN auth_roles r ON r.franchise_ref = u.franchise_ref AND r.role_slug = 'admin'
WHERE u.role = 'FRANCHISE_ADMIN';

INSERT IGNORE INTO auth_roles
  (role_ref, org_ref, franchise_ref, role_name, role_slug, description, is_system, status, default_scope, created_by_ref)
SELECT CONCAT('ROLE-', UPPER(SUBSTRING(SHA2(CONCAT(f.franchise_ref, ':sales-team'), 256), 1, 20))),
       f.org_ref, f.franchise_ref, 'Sales Team', 'sales-team', 'Default own-scope sales role', 0, 'ACTIVE', 'OWN',
       COALESCE((SELECT u.user_ref FROM users u WHERE u.franchise_ref = f.franchise_ref ORDER BY u.created_at LIMIT 1), 'SYSTEM')
FROM franchises f WHERE f.status = 'ACTIVE';

INSERT IGNORE INTO auth_user_roles (user_ref, role_ref, assigned_by_ref)
SELECT u.user_ref, r.role_ref, u.user_ref
FROM users u JOIN auth_roles r ON r.franchise_ref = u.franchise_ref AND r.role_slug = 'sales-team'
WHERE u.role = 'SALES';

INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
SELECT r.role_ref, p.permission_ref, COALESCE((SELECT u.user_ref FROM users u WHERE u.franchise_ref = r.franchise_ref ORDER BY u.created_at LIMIT 1), 'SYSTEM')
FROM auth_roles r JOIN auth_permissions p
WHERE r.role_slug = 'sales-team'
  AND (p.module_key, p.action_key) IN (
    ('leads','view'),('leads','create'),('leads','edit'),('leads','convert'),
    ('followUps','view'),('followUps','create'),('followUps','edit'),('followUps','complete'),('followUps','reschedule'),
    ('parties','view'),('parties','create'),('parties','edit'),
    ('orders','view'),('orders','create'),('orders','editDraft'),('orders','submit'),
    ('payments','view'),('payments','create'),('payments','edit'),
    ('dashboard','view'),('reports','view')
  );

INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
SELECT r.role_ref, p.permission_ref, COALESCE((SELECT u.user_ref FROM users u WHERE u.franchise_ref = r.franchise_ref AND u.role = 'FRANCHISE_ADMIN' ORDER BY u.created_at LIMIT 1), 'SYSTEM')
FROM auth_roles r CROSS JOIN auth_permissions p WHERE r.role_slug = 'admin';
