SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -------------------------------------------------------------
-- 005_additional_seed.sql
-- Demonstration & Transactional Data: Extra Products, Extra Parties,
-- Leads, Follow-ups, Lead Activities, Sample Orders & Items
-- Tenant: ACME Mumbai PCD Franchise (ORG-PLATFORM0000000001 / FRN-MUMBAI000000000001)
-- -------------------------------------------------------------

-- 1. Extra Products
INSERT INTO products (
  product_ref, org_ref, franchise_ref, sku, product_name, category_ref,
  composition, pack_size, dosage_form, mrp, pts, franchise_rate,
  gst_percent, hsn_code, shelf_life_days, storage_requirement, scheme_eligible, status, created_by_ref
) VALUES
('PRD-COUGH1000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CGS-100', 'Cough Syrup 100ml', 'CAT-ANTIHIST00000001',
 'Dextromethorphan + Chlorpheniramine', '100ml Bottle', 'Syrup', 85.00, 60.00, 52.00, 12.00, '30049099', 730, 'Store in dark place', 1, 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRD-AZI5000000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'AZI-500', 'Azithromycin 500mg Tablets', 'CAT-ANTIBIOTIC000001',
 'Azithromycin IP 500mg', '3 Tablets Strip', 'Tablet', 120.00, 88.00, 75.00, 12.00, '30041010', 730, 'Store below 30°C', 1, 'ACTIVE', 'USR-SUPERADMIN0000001'),

('PRD-DICGEL3000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DIC-GEL', 'Diclofenac Gel 30g', 'CAT-ANALGESIC0000001',
 'Diclofenac Diethylamine 1.16% w/w', '30g Tube', 'Gel', 65.00, 46.00, 39.00, 12.00, '30049099', 1095, 'Do not freeze', 0, 'ACTIVE', 'USR-SUPERADMIN0000001')
ON DUPLICATE KEY UPDATE
  product_name = VALUES(product_name), category_ref = VALUES(category_ref),
  mrp = VALUES(mrp), pts = VALUES(pts), franchise_rate = VALUES(franchise_rate),
  status = VALUES(status);

-- Extra Inventory Batches for Extra Products
INSERT INTO inventory_batches (
  batch_ref, org_ref, franchise_ref, product_ref,
  batch_no, manufacturing_date, expiry_date,
  received_qty, on_hand_qty, reserved_qty, damaged_qty,
  location_code, status, version, created_by_ref
) VALUES
('BAT-COUGH100-26A0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-COUGH1000000001',
 'CG-2026A1', '2026-02-01', '2028-01-31', 1000, 950, 0, 0, 'WH-MUM-SYRUP-01', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-AZI500-26A000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-AZI5000000000001',
 'AZ-2026A1', '2026-01-15', '2028-01-14', 1500, 1500, 0, 0, 'WH-MUM-A3-R1', 'SALEABLE', 1, 'USR-FRNADMIN000000001'),

('BAT-DICGEL-26A000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PRD-DICGEL3000000001',
 'DG-2026A1', '2026-02-15', '2029-02-14', 800, 800, 0, 0, 'WH-MUM-TOP-01', 'SALEABLE', 1, 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE on_hand_qty = VALUES(on_hand_qty);

-- 2. Extra Parties (Healthcare Providers & Retailers)
INSERT INTO parties (
  party_ref, org_ref, franchise_ref, party_code, firm_name, contact_name,
  mobile, email, gstin, drug_license_no,
  billing_address, shipping_address,
  state_ref, district_ref, city_ref, pincode,
  tier_ref, sales_user_ref, agreement_from, agreement_to,
  credit_limit, payment_terms_days, opening_outstanding,
  status, created_by_ref
) VALUES
('PAR-WELLNESSCHEM0001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PTY-00004', 'Wellness Forever Chemist', 'Sunil Narang',
 '9820067890', 'mumbai@wellnessforever.local', '27DDDDE1234K1Z9', 'MH-MZ4-889900',
 'Shop 5, Western Express Highway, Andheri East', 'Shop 5, Western Express Highway, Andheri East',
 'STA-MAHARASHTRA00001', 'DST-MUMBAISUBURB0001', 'CTY-ANDHERI000000001', '400053',
 'TIR-RETAIL0000000001', 'USR-SALESREP000000001', '2026-01-01', '2027-12-31',
 150000.00, 15, 0.00, 'ACTIVE', 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE firm_name = VALUES(firm_name);

-- 3. CRM Leads
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

-- 4. Follow-ups
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

-- 5. Lead Activity History
INSERT INTO lead_activities (
  activity_ref, org_ref, franchise_ref, lead_ref, user_ref,
  activity_type, from_status, to_status, activity_note
) VALUES
('ACT-ANILKUMAR0000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LED-DRANILKUMAR00001', 'USR-SALESREP000000001',
 'INITIAL_CONTACT', 'NEW', 'CONTACTED', 'Introduced Acme PCD franchise product offerings. Doctor is warm to product portfolio.');

-- 6. Sample Orders & Order Items
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

-- Order Items for Order 1 (Apollo)
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
 50, 5, 8.80, 'TIER', 'PRC-CETA10-DST000001', 0.00, 12.00, 440.00, 'SCH-MONSOON20260001')
ON DUPLICATE KEY UPDATE line_total = VALUES(line_total);

-- Order Items for Order 2 (Gupta Medicos)
INSERT INTO order_items (
  item_ref, org_ref, franchise_ref, order_ref, product_ref,
  paid_qty, free_qty, rate, rate_source, price_ref,
  discount, gst_percent, line_total, scheme_ref
) VALUES
('ITM-ORD2-PARA5000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-GUPTA20260000001', 'PRD-PARA500000000001',
 50, 5, 18.20, 'TIER', 'PRC-PARA500-RET00001', 0.00, 12.00, 910.00, 'SCH-MONSOON20260001'),

('ITM-ORD2-OMEP2000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-GUPTA20260000001', 'PRD-OMEP200000000001',
 30, 0, 39.00, 'DEFAULT', NULL, 0.00, 12.00, 1170.00, NULL)
ON DUPLICATE KEY UPDATE line_total = VALUES(line_total);

-- 7. Order Status History
INSERT INTO order_status_history (
  org_ref, franchise_ref, order_ref, from_status, to_status, actor_ref, reason
) VALUES
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-APOLLO2026000001', 'DRAFT', 'SUBMITTED', 'USR-DISTRIBUTOR000001', 'Order submitted from distributor portal'),
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-APOLLO2026000001', 'SUBMITTED', 'CONFIRMED', 'USR-FRNADMIN000000001', 'Stock and credit verified'),
('ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'ORD-GUPTA20260000001', 'DRAFT', 'SUBMITTED', 'USR-SALESREP000000001', 'Order taken on field visit');

SET FOREIGN_KEY_CHECKS = 1;
