SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- 002_masters_seed.sql
-- Master Reference Data: Geography, Categories, Products,
-- Pricing Tiers, Product Prices, Schemes, Parties, and Party Territories
-- Tenant: ACME Mumbai PCD Franchise (ORG-PLATFORM0000000001 / FRN-MUMBAI000000000001)
-- -------------------------------------------------------------

-- 1. Global Geographic Data: States, Districts, Cities, Pincodes
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

-- 2. Product Categories
INSERT INTO product_categories (category_ref, org_ref, franchise_ref, category_name, status, created_by_ref) VALUES
('CAT-ANALGESIC0000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Analgesics & Pain Relief', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('CAT-ANTIBIOTIC000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Antibiotics & Anti-Infectives', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('CAT-ANTIHIST00000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Antihistamines & Allergy', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('CAT-GASTRO0000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Gastrointestinal', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('CAT-NUTRITION0000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Vitamins & Supplements', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name), status = VALUES(status);

-- 3. Products
INSERT INTO products (
  product_ref, org_ref, franchise_ref, sku, product_name, category_ref,
  composition, pack_size, dosage_form, mrp, pts, franchise_rate,
  gst_percent, hsn_code, shelf_life_days, storage_requirement, scheme_eligible, status, created_by_ref
) VALUES
('PRD-PARA500000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PARA-500', 'Paracetamol 500mg Tablets', 'CAT-ANALGESIC0000001',
 'Paracetamol IP 500mg', '10x10 Tablets', 'Tablet', 25.50, 18.20, 15.00, 12.00, '30049060', 730, 'Store below 25°C', 1, 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRD-AMOX250000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'AMOX-250', 'Amoxicillin 250mg Capsules', 'CAT-ANTIBIOTIC000001',
 'Amoxicillin Trihydrate IP 250mg', '10x10 Capsules', 'Capsule', 45.00, 32.10, 27.50, 12.00, '30041010', 730, 'Store in cool and dry place', 1, 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRD-CETA1000000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CETA-10', 'Cetirizine 10mg Tablets', 'CAT-ANTIHIST00000001',
 'Cetirizine Hydrochloride IP 10mg', '10x10 Tablets', 'Tablet', 15.00, 10.50, 8.80, 12.00, '30049099', 1095, 'Store below 30°C', 1, 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRD-OMEP200000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'OMEP-20', 'Omeprazole 20mg Capsules', 'CAT-GASTRO0000000001',
 'Omeprazole IP 20mg', '10x10 Capsules', 'Capsule', 55.00, 39.00, 33.00, 12.00, '30049099', 730, 'Store protected from moisture', 1, 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRD-VITC100000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'VITC-1000', 'Vitamin C 1000mg Chewable', 'CAT-NUTRITION0000001',
 'Ascorbic Acid IP 1000mg', '30 Chewable Tablets', 'Chewable Tablet', 120.00, 85.00, 72.00, 18.00, '21069099', 540, 'Store below 25°C', 1, 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE
  product_name = VALUES(product_name), category_ref = VALUES(category_ref),
  mrp = VALUES(mrp), pts = VALUES(pts), franchise_rate = VALUES(franchise_rate),
  gst_percent = VALUES(gst_percent), status = VALUES(status);

-- 4. Pricing Tiers
INSERT INTO pricing_tiers (tier_ref, org_ref, franchise_ref, tier_name, status, created_by_ref) VALUES
('TIR-STOCKIST00000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Stockist / Wholesaler', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('TIR-DISTRIB000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Authorised Distributor', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('TIR-RETAIL0000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Retail Chemist / Pharmacy', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE tier_name = VALUES(tier_name), status = VALUES(status);

-- 5. Product Prices (Tier-based pricing)
INSERT INTO product_prices (
  price_ref, org_ref, franchise_ref, product_ref, tier_ref, party_ref,
  rate, priority, effective_from, status, created_by_ref
) VALUES
('PRC-PARA500-STK00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-PARA500000000001', 'TIR-STOCKIST00000001', NULL, 16.50, 10, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-PARA500-DST00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-PARA500000000001', 'TIR-DISTRIB000000001', NULL, 15.00, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-PARA500-RET00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-PARA500000000001', 'TIR-RETAIL0000000001', NULL, 18.20, 30, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRC-AMOX250-STK00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-AMOX250000000001', 'TIR-STOCKIST00000001', NULL, 29.50, 10, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-AMOX250-DST00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-AMOX250000000001', 'TIR-DISTRIB000000001', NULL, 27.50, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRC-CETA10-DST000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-CETA1000000000001', 'TIR-DISTRIB000000001', NULL, 8.80, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-OMEP20-DST000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-OMEP200000000001', 'TIR-DISTRIB000000001', NULL, 33.00, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('PRC-VITC10-DST000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-VITC100000000001', 'TIR-DISTRIB000000001', NULL, 72.00, 20, '2026-01-01', 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE rate = VALUES(rate), status = VALUES(status);

-- 6. Promotional Schemes
INSERT INTO schemes (
  scheme_ref, org_ref, franchise_ref, scheme_name,
  start_date, end_date, priority, stacking_allowed, tier_ref, status, created_by_ref
) VALUES
('SCH-MONSOON20260001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'Monsoon Health Campaign (Buy 10 Get 1 Free)',
 '2026-01-01', '2026-12-31', 10, 0, NULL, 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE scheme_name = VALUES(scheme_name), status = VALUES(status);

INSERT INTO scheme_rules (
  rule_ref, org_ref, franchise_ref, scheme_ref, product_ref,
  min_qty, max_qty, free_qty
) VALUES
('RUL-PARA500-10P10001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SCH-MONSOON20260001', 'PRD-PARA500000000001', 10, 100, 1),
('RUL-CETA10-10P100001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SCH-MONSOON20260001', 'PRD-CETA1000000000001', 10, 100, 1)
ON DUPLICATE KEY UPDATE free_qty = VALUES(free_qty);

-- 7. Seed Parties (Customers: Stockists, Distributors, Chemists)
INSERT INTO parties (
  party_ref, org_ref, franchise_ref, party_code, firm_name, contact_name,
  mobile, email, gstin, drug_license_no,
  billing_address, shipping_address,
  state_ref, district_ref, city_ref, pincode,
  tier_ref, sales_user_ref, agreement_from, agreement_to,
  credit_limit, payment_terms_days, opening_outstanding,
  status, created_by_ref
) VALUES
('PAR-APOLLODIST00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PTY-00001', 'Apollo Pharma Distributors', 'Rajesh Kulkarni',
 '9820012345', 'portal@pharmacrm.local', '27AABCA1234F1Z5', 'MH-MZ4-123456',
 'Shop 12, Commercial Hub, Fort, Mumbai', 'Shop 12, Commercial Hub, Fort, Mumbai',
 'STA-MAHARASHTRA00001', 'DST-MUMBAICITY000001', 'CTY-MUMBAI0000000001', '400001',
 'TIR-DISTRIB000000001', 'USR-SALESREP000000001', '2026-01-01', '2028-12-31',
 500000.00, 30, 0.00, 'ACTIVE', 'USR-FRNADMIN000000001'),

('PAR-GUPTAMEDICO0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PTY-00002', 'Gupta Medicos & Surgicals', 'Dr. Ramesh Gupta',
 '9820054321', 'ramesh.gupta@example.com', '27BBCDE5678G2Z4', 'MH-MZ4-654321',
 'Plot 45, Linking Road, Andheri West, Mumbai', 'Plot 45, Linking Road, Andheri West, Mumbai',
 'STA-MAHARASHTRA00001', 'DST-MUMBAISUBURB0001', 'CTY-ANDHERI000000001', '400053',
 'TIR-RETAIL0000000001', 'USR-SALESREP000000001', '2026-01-01', '2027-12-31',
 200000.00, 21, 0.00, 'ACTIVE', 'USR-FRNADMIN000000001'),

('PAR-DELHISTOCK00001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PTY-00003', 'Apex Lifesciences Wholesale', 'Sneha Verma',
 '9811122233', 'sneha.verma@example.com', '27CCCDE9876H3Z1', 'MH-TH2-789012',
 'Sector 3, Wagle Estate, Thane West', 'Sector 3, Wagle Estate, Thane West',
 'STA-MAHARASHTRA00001', 'DST-THANE00000000001', 'CTY-THANE00000000001', '400601',
 'TIR-STOCKIST00000001', 'USR-SALESREP000000001', '2026-01-01', '2028-12-31',
 1000000.00, 45, 0.00, 'ACTIVE', 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE
  firm_name = VALUES(firm_name), contact_name = VALUES(contact_name),
  credit_limit = VALUES(credit_limit), status = VALUES(status);

-- Link Distributor user account to party
UPDATE users
SET party_ref = 'PAR-APOLLODIST00001'
WHERE user_ref = 'USR-DISTRIBUTOR000001';

-- 8. Party Territory Assignments
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

SET FOREIGN_KEY_CHECKS = 1;
