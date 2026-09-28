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
