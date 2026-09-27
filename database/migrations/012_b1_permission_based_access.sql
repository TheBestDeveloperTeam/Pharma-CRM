-- B1: authorization by permission key instead of users.role.
-- Additive and idempotent (INSERT IGNORE only; nothing is updated or deleted).
--
-- What it does:
--   1. Adds the permission keys that had no equivalent (settings.*, portal.*).
--   2. Makes every catalogue key grantable (copies missing keys into auth_permissions;
--      grants NOTHING by itself — later migrations only ever inserted into the catalogue).
--   3. Ensures every active franchise has the three baseline roles
--      (admin / sales-team from migration 002, plus the new distributor role).
--   4. Grants ONLY what each existing user could already do through their role name:
--        admin       + settings.view, settings.edit        (was: FRANCHISE_ADMIN name gate)
--        distributor + portal.view/placeOrder/editProfile  (was: DISTRIBUTOR name gate)
--      Nothing else is added to existing roles (e.g. orders.confirm stays ungranted).
--   5. Backfills role assignments for users who never got one (users created after 002):
--        FRANCHISE_ADMIN → admin, SALES → sales-team, DISTRIBUTOR → distributor.
--      Users who already have ANY role keep exactly the roles they have.

-- 1. New catalogue keys -------------------------------------------------------
INSERT IGNORE INTO auth_permission_catalogue (module_key, action_key, label, is_sensitive) VALUES
('settings', 'view', 'View Settings', 0),
('settings', 'edit', 'Edit Settings', 1),
('portal', 'view', 'Portal: View own account', 0),
('portal', 'placeOrder', 'Portal: Cart & Orders', 0),
('portal', 'editProfile', 'Portal: Edit own profile', 0);

-- 2. Every catalogue key becomes grantable ----------------------------------
INSERT IGNORE INTO auth_permissions (permission_ref, module_key, action_key, label, is_sensitive)
SELECT CONCAT('PER-', UPPER(SUBSTRING(SHA2(CONCAT(module_key, ':', action_key), 256), 1, 20))),
       module_key, action_key, label, is_sensitive
FROM auth_permission_catalogue;

-- 3. Baseline roles for every active franchise -------------------------------
INSERT IGNORE INTO auth_roles
  (role_ref, org_ref, franchise_ref, role_name, role_slug, description, is_system, status, default_scope, created_by_ref)
SELECT CONCAT('ROLE-', UPPER(SUBSTRING(SHA2(CONCAT(f.franchise_ref, ':admin'), 256), 1, 20))),
       f.org_ref, f.franchise_ref, 'Admin', 'admin', 'Protected full-access system role', 1, 'ACTIVE', 'ALL', 'SYSTEM'
FROM franchises f WHERE f.status = 'ACTIVE';

INSERT IGNORE INTO auth_roles
  (role_ref, org_ref, franchise_ref, role_name, role_slug, description, is_system, status, default_scope, created_by_ref)
SELECT CONCAT('ROLE-', UPPER(SUBSTRING(SHA2(CONCAT(f.franchise_ref, ':sales-team'), 256), 1, 20))),
       f.org_ref, f.franchise_ref, 'Sales Team', 'sales-team', 'Default own-scope sales role', 0, 'ACTIVE', 'OWN', 'SYSTEM'
FROM franchises f WHERE f.status = 'ACTIVE';

INSERT IGNORE INTO auth_roles
  (role_ref, org_ref, franchise_ref, role_name, role_slug, description, is_system, status, default_scope, created_by_ref)
SELECT CONCAT('ROLE-', UPPER(SUBSTRING(SHA2(CONCAT(f.franchise_ref, ':distributor'), 256), 1, 20))),
       f.org_ref, f.franchise_ref, 'Distributor', 'distributor', 'Distributor portal self-service', 0, 'ACTIVE', 'OWN', 'SYSTEM'
FROM franchises f WHERE f.status = 'ACTIVE';

-- 4a. A franchise whose admin / sales-team role was created just now (step 3) gets
--     migration 002's grants; roles that already existed are left untouched here.
INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
SELECT r.role_ref, p.permission_ref, 'SYSTEM'
FROM auth_roles r JOIN auth_permissions p
WHERE r.role_slug = 'admin' AND r.created_by_ref = 'SYSTEM'
  AND NOT EXISTS (SELECT 1 FROM auth_role_permissions x WHERE x.role_ref = r.role_ref);

INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
SELECT r.role_ref, p.permission_ref, 'SYSTEM'
FROM auth_roles r JOIN auth_permissions p
WHERE r.role_slug = 'sales-team' AND r.created_by_ref = 'SYSTEM'
  AND NOT EXISTS (SELECT 1 FROM auth_role_permissions x WHERE x.role_ref = r.role_ref)
  AND (p.module_key, p.action_key) IN (
    ('leads','view'),('leads','create'),('leads','edit'),('leads','convert'),
    ('followUps','view'),('followUps','create'),('followUps','edit'),('followUps','complete'),('followUps','reschedule'),
    ('parties','view'),('parties','create'),('parties','edit'),
    ('orders','view'),('orders','create'),('orders','editDraft'),('orders','submit'),
    ('payments','view'),('payments','create'),('payments','edit'),
    ('dashboard','view'),('reports','view')
  );

-- 4b. Keys that replace a role-name gate — granted to exactly the role that used to pass that gate.
INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
SELECT r.role_ref, p.permission_ref, 'SYSTEM'
FROM auth_roles r JOIN auth_permissions p
WHERE r.role_slug = 'admin' AND (p.module_key, p.action_key) IN (('settings','view'),('settings','edit'));

INSERT IGNORE INTO auth_role_permissions (role_ref, permission_ref, granted_by_ref)
SELECT r.role_ref, p.permission_ref, 'SYSTEM'
FROM auth_roles r JOIN auth_permissions p
WHERE r.role_slug = 'distributor' AND p.module_key = 'portal' AND p.action_key IN ('view','placeOrder','editProfile');

-- 5. Backfill baseline role for users that have no role at all ---------------
INSERT IGNORE INTO auth_user_roles (user_ref, role_ref, assigned_by_ref)
SELECT u.user_ref, r.role_ref, 'SYSTEM'
FROM users u JOIN auth_roles r ON r.franchise_ref = u.franchise_ref AND r.role_slug = 'admin'
WHERE u.role = 'FRANCHISE_ADMIN'
  AND NOT EXISTS (SELECT 1 FROM auth_user_roles x WHERE x.user_ref = u.user_ref);

INSERT IGNORE INTO auth_user_roles (user_ref, role_ref, assigned_by_ref)
SELECT u.user_ref, r.role_ref, 'SYSTEM'
FROM users u JOIN auth_roles r ON r.franchise_ref = u.franchise_ref AND r.role_slug = 'sales-team'
WHERE u.role = 'SALES'
  AND NOT EXISTS (SELECT 1 FROM auth_user_roles x WHERE x.user_ref = u.user_ref);

-- Distributors never had any auth role; all of them get the portal role.
INSERT IGNORE INTO auth_user_roles (user_ref, role_ref, assigned_by_ref)
SELECT u.user_ref, r.role_ref, 'SYSTEM'
FROM users u JOIN auth_roles r ON r.franchise_ref = u.franchise_ref AND r.role_slug = 'distributor'
WHERE u.role = 'DISTRIBUTOR';
