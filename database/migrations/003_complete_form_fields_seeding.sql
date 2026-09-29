-- ============================================================================
-- PHARMA CRM & SALES FORCE AUTOMATION
-- Migration 003: Complete Form Field Specifications for Zero-Local-Data Architecture
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Ensure all Form Schemas are registered
INSERT INTO `ui_form_schemas` (`schema_ref`, `form_key`, `title`, `description`, `entity_type`, `version`, `status`) VALUES
('SCH-FRM-ORDCR000000001', 'order_create', 'Create Commercial Sales Order', 'Header and line items for sales order booking', 'order', 1, 'ACTIVE'),
('SCH-FRM-DISPCR00000001', 'dispatch_create', 'Create Consignment Dispatch', 'Transporter assignment and LR generation for confirmed orders', 'dispatch', 1, 'ACTIVE'),
('SCH-FRM-INVREC00000001', 'inventory_receive', 'Receive Inventory Batch (GRN)', 'Goods receipt note and batch creation with FEFO dates', 'inventory', 1, 'ACTIVE'),
('SCH-FRM-INVADJ00000001', 'inventory_adjust', 'Physical Stock Adjustment', 'Stock audit reconciliation, damage, or sample transfers', 'inventory', 1, 'ACTIVE'),
('SCH-FRM-PDCCR000000001', 'pdc_create', 'Register Post-Dated Cheque', 'Register PDC for future maturity collection', 'payment', 1, 'ACTIVE'),
('SCH-FRM-PDCREA00000001', 'pdc_realize', 'Realize Matured Cheque', 'Mark cheque as cleared and record payment receipt', 'payment', 1, 'ACTIVE'),
('SCH-FRM-PDCBNC00000001', 'pdc_bounce', 'Record Cheque Bounce', 'Record dishonoured cheque with bank reason', 'payment', 1, 'ACTIVE'),
('SCH-FRM-DCRCR000000001', 'dcr_create', 'Daily Call Report Header', 'Field representative daily report submission', 'dcr', 1, 'ACTIVE'),
('SCH-FRM-DCRVIS00000001', 'dcr_visit', 'DCR Field Visit Log', 'Doctor / Stockist call log with POB and samples', 'dcr', 1, 'ACTIVE'),
('SCH-FRM-ONBREG00000001', 'onboarding_register', 'Distributor Self-Registration KYC Wizard', '4-step comprehensive KYC onboarding wizard', 'onboarding', 1, 'ACTIVE'),
('SCH-FRM-FUPCR000000001', 'followup_create', 'Log Scheduled Follow-up', 'Sales lead or party activity follow-up', 'followup', 1, 'ACTIVE'),
('SCH-FRM-TERCR000000001', 'territory_create', 'Allocate Territory Lock', 'Pincode or district exclusive territory allocation', 'territory', 1, 'ACTIVE'),
('SCH-FRM-TEROVR00000001', 'territory_override', 'Administrative Territory Override', 'Audited override for orders outside assigned territory', 'territory', 1, 'ACTIVE'),
('SCH-FRM-SCHCR000000001', 'scheme_create', 'Create Promotional Scheme', 'Free goods (10+1) or discount commercial scheme', 'scheme', 1, 'ACTIVE'),
('SCH-FRM-TIERCR00000001', 'tier_create', 'Create Pricing Tier', 'Commercial pricing tier categorization', 'pricing', 1, 'ACTIVE'),
('SCH-FRM-CATCR000000001', 'category_create', 'Create Product Category', 'Pharmaceutical therapeutic category', 'product', 1, 'ACTIVE')
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description);

-- 2. Seed UI Form Fields for All Core Workflows
INSERT INTO `ui_form_fields` (
  `field_ref`, `form_key`, `field_name`, `label`, `field_type`, `placeholder`,
  `default_value`, `is_required`, `is_readonly`, `validation_rules_json`,
  `options_source_type`, `options_source_key`, `step_number`, `grid_width`, `sort_order`, `is_active`
) VALUES
-- ============================================================================
-- Order Create Form Fields
-- ============================================================================
('FLD-ORD-001', 'order_create', 'party_ref', 'Select Commercial Party', 'select', 'Choose distributor / stockist', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/parties', 1, 6, 10, 1),
('FLD-ORD-002', 'order_create', 'client_order_ref', 'Client Order Reference / PO Number', 'text', 'e.g. PO-2026-089', NULL, 1, 0, '{"required":true,"min":2}', 'NONE', NULL, 1, 6, 20, 1),
('FLD-ORD-003', 'order_create', 'channel', 'Order Booking Channel', 'select', 'Select channel', 'ADMIN', 1, 0, '{"required":true}', 'STATIC_JSON', '[{"code":"ADMIN","label":"Admin Backoffice"},{"code":"SALES","label":"Sales Rep Field Booking"},{"code":"PORTAL","label":"Distributor Portal"}]', 1, 6, 30, 1),
('FLD-ORD-004', 'order_create', 'shipping_pincode', 'Shipping Destination Pincode', 'text', '6-digit PIN code', NULL, 0, 0, '{"pattern":"^[1-9][0-9]{5}$"}', 'NONE', NULL, 1, 6, 40, 1),
('FLD-ORD-005', 'order_create', 'shipping_address', 'Consignment Shipping Address', 'textarea', 'Delivery warehouse / shop address', NULL, 0, 0, NULL, 'NONE', NULL, 1, 12, 50, 1),
('FLD-ORD-006', 'order_create', 'remarks', 'Commercial Order Remarks', 'textarea', 'Special delivery or scheme instructions...', NULL, 0, 0, NULL, 'NONE', NULL, 1, 12, 60, 1),

-- ============================================================================
-- Dispatch Consignment Form Fields
-- ============================================================================
('FLD-DSP-001', 'dispatch_create', 'invoice_ref', 'Target Tax Invoice', 'select', 'Select posted invoice for dispatch', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/invoices?status=POSTED', 1, 6, 10, 1),
('FLD-DSP-002', 'dispatch_create', 'transporter_ref', 'Assigned Transporter / Logistics Carrier', 'select', 'Select carrier', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/transporters?status=ACTIVE', 1, 6, 20, 1),
('FLD-DSP-003', 'dispatch_create', 'mode', 'Transportation Mode', 'select', 'Select logistics mode', 'ROAD', 1, 0, '{"required":true}', 'CATALOG_MASTER', 'TRANSPORTER_MODE', 1, 6, 30, 1),
('FLD-DSP-004', 'dispatch_create', 'lr_number', 'Lorry Receipt (LR) / Consignment Note No.', 'text', 'e.g. LR-98765432', NULL, 1, 0, '{"required":true,"min":3}', 'NONE', NULL, 1, 6, 40, 1),
('FLD-DSP-005', 'dispatch_create', 'parcels_count', 'Number of Boxes / Cartons', 'number', 'e.g. 5', '1', 1, 0, '{"required":true,"min":1}', 'NONE', NULL, 1, 6, 50, 1),
('FLD-DSP-006', 'dispatch_create', 'gross_weight_kg', 'Consignment Gross Weight (KG)', 'number', 'e.g. 12.5', NULL, 0, 0, '{"min":0.1}', 'NONE', NULL, 1, 6, 60, 1),
('FLD-DSP-007', 'dispatch_create', 'eway_bill_no', 'GST E-Way Bill Number', 'text', '12-digit E-Way Bill No.', NULL, 0, 0, '{"pattern":"^[0-9]{12}$"}', 'NONE', NULL, 1, 6, 70, 1),
('FLD-DSP-008', 'dispatch_create', 'tracking_url', 'Live Consignment Tracking URL', 'text', 'https://...', NULL, 0, 0, '{"url":true}', 'NONE', NULL, 1, 6, 80, 1),

-- ============================================================================
-- Goods Receipt Note (GRN) & Inventory Receive Form Fields
-- ============================================================================
('FLD-REC-001', 'inventory_receive', 'product_ref', 'Pharmaceutical Product SKU', 'select', 'Select product SKU', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/products?status=ACTIVE', 1, 6, 10, 1),
('FLD-REC-002', 'inventory_receive', 'batch_no', 'Manufacturing Batch Number', 'text', 'e.g. PR-2026B1', NULL, 1, 0, '{"required":true,"min":2}', 'NONE', NULL, 1, 6, 20, 1),
('FLD-REC-003', 'inventory_receive', 'mfg_date', 'Manufacturing Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 30, 1),
('FLD-REC-004', 'inventory_receive', 'expiry_date', 'Product Expiry Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 40, 1),
('FLD-REC-005', 'inventory_receive', 'received_qty', 'Received Quantity (Packs/Strips)', 'number', 'e.g. 5000', NULL, 1, 0, '{"required":true,"min":1}', 'NONE', NULL, 1, 6, 50, 1),
('FLD-REC-006', 'inventory_receive', 'warehouse_location', 'Warehouse Bin / Rack Location', 'text', 'e.g. WH-MUM-B1-R2', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 60, 1),
('FLD-REC-007', 'inventory_receive', 'cost_price', 'Unit Cost / Purchase Price (₹)', 'currency', '0.00', NULL, 0, 0, '{"min":0}', 'NONE', NULL, 1, 6, 70, 1),
('FLD-REC-008', 'inventory_receive', 'remarks', 'Inward Receiving Remarks', 'textarea', 'Supplier invoice ref, physical condition check...', NULL, 0, 0, NULL, 'NONE', NULL, 1, 12, 80, 1),

-- ============================================================================
-- Physical Stock Adjustment Form Fields
-- ============================================================================
('FLD-ADJ-001', 'inventory_adjust', 'batch_ref', 'Inventory Batch', 'select', 'Select target batch', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/inventory/batches', 1, 6, 10, 1),
('FLD-ADJ-002', 'inventory_adjust', 'adjustment_type', 'Adjustment Operation', 'select', 'Select type', 'SUBTRACT', 1, 0, '{"required":true}', 'STATIC_JSON', '[{"code":"ADD","label":"Add Stock (Audit Surplus / Recovery)"},{"code":"SUBTRACT","label":"Subtract Stock (Damage / Breakage / Shortage)"}]', 1, 6, 20, 1),
('FLD-ADJ-003', 'inventory_adjust', 'quantity', 'Adjustment Quantity', 'number', 'e.g. 20', NULL, 1, 0, '{"required":true,"min":1}', 'NONE', NULL, 1, 6, 30, 1),
('FLD-ADJ-004', 'inventory_adjust', 'reason', 'Statutory Adjustment Reason', 'select', 'Select reason', 'DAMAGED', 1, 0, '{"required":true}', 'CATALOG_MASTER', 'STOCK_ADJUSTMENT_REASON', 1, 6, 40, 1),
('FLD-ADJ-005', 'inventory_adjust', 'remarks', 'Audit Reconciliation Remarks', 'textarea', 'Detailed justification for stock movement...', NULL, 1, 0, '{"required":true,"min":3}', 'NONE', NULL, 1, 12, 50, 1),

-- ============================================================================
-- Post-Dated Cheques Realization & Bounce Modals
-- ============================================================================
('FLD-PDC-001', 'pdc_create', 'party_ref', 'Party / Customer', 'select', 'Select drawer party', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/parties', 1, 6, 10, 1),
('FLD-PDC-002', 'pdc_create', 'cheque_number', 'Cheque Number', 'text', '6-digit cheque number', NULL, 1, 0, '{"required":true,"pattern":"^[0-9]{6}$","message":"Enter valid 6-digit cheque number"}', 'NONE', NULL, 1, 6, 20, 1),
('FLD-PDC-003', 'pdc_create', 'bank_name', 'Drawee Bank Name', 'text', 'e.g. HDFC Bank, Fort Branch', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 30, 1),
('FLD-PDC-004', 'pdc_create', 'amount', 'Cheque Value Amount (₹)', 'currency', '0.00', NULL, 1, 0, '{"required":true,"min":1}', 'NONE', NULL, 1, 6, 40, 1),
('FLD-PDC-005', 'pdc_create', 'due_date', 'Maturity / Cheque Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 50, 1),

('FLD-PDC-010', 'pdc_realize', 'realized_date', 'Bank Clearance Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 10, 1),
('FLD-PDC-011', 'pdc_realize', 'bank_account_ref', 'Deposit Bank Account', 'text', 'e.g. Current A/C #987654321', NULL, 0, 0, NULL, 'NONE', NULL, 1, 6, 20, 1),

('FLD-PDC-020', 'pdc_bounce', 'bounce_date', 'Cheque Return Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 10, 1),
('FLD-PDC-021', 'pdc_bounce', 'reason', 'Bank Return Memo Reason', 'text', 'e.g. Funds Insufficient / Signature Mismatch', NULL, 1, 0, '{"required":true,"min":3}', 'NONE', NULL, 1, 6, 20, 1),

-- ============================================================================
-- Daily Call Report (DCR) Header & Field Visit Forms
-- ============================================================================
('FLD-DCR-001', 'dcr_create', 'report_date', 'Report Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 10, 1),
('FLD-DCR-002', 'dcr_create', 'work_type', 'Day Work Type', 'select', 'Select work type', 'FIELD_WORK', 1, 0, '{"required":true}', 'CATALOG_MASTER', 'WORK_TYPE', 1, 6, 20, 1),
('FLD-DCR-003', 'dcr_create', 'beat', 'Territory Beat / Route Name', 'text', 'e.g. Andheri West Clinic Route', NULL, 1, 0, '{"required":true,"min":2}', 'NONE', NULL, 1, 6, 30, 1),

('FLD-DCV-001', 'dcr_visit', 'customer_type', 'Customer Classification', 'select', 'Select type', 'DOCTOR', 1, 0, '{"required":true}', 'STATIC_JSON', '[{"code":"DOCTOR","label":"Consulting / Dispensing Physician"},{"code":"CHEMIST","label":"Retail Chemist / Pharmacy"},{"code":"STOCKIST","label":"Authorized Stockist / Wholesaler"}]', 1, 6, 10, 1),
('FLD-DCV-002', 'dcr_visit', 'visit_time', 'Visit Time', 'text', 'HH:MM (e.g. 11:30 AM)', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 20, 1),
('FLD-DCV-003', 'dcr_visit', 'visit_purpose', 'Visit Objective', 'text', 'e.g. Routine Call / Launch Detailing', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 30, 1),
('FLD-DCV-004', 'dcr_visit', 'products_promoted', 'Products Detailed / Promoted', 'multiselect', 'Select products', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/products?status=ACTIVE', 1, 12, 40, 1),
('FLD-DCV-005', 'dcr_visit', 'pob_value', 'Personal Order Booking (₹ POB Value)', 'currency', '0.00', NULL, 0, 0, NULL, 'NONE', NULL, 1, 6, 50, 1),
('FLD-DCV-006', 'dcr_visit', 'next_visit_date', 'Next Scheduled Call Date', 'date', 'YYYY-MM-DD', NULL, 0, 0, NULL, 'NONE', NULL, 1, 6, 60, 1),
('FLD-DCV-007', 'dcr_visit', 'feedback', 'Doctor / Chemist Clinical Feedback', 'textarea', 'Prescription commitment, competitor brand mentions...', NULL, 0, 0, NULL, 'NONE', NULL, 1, 12, 70, 1),

-- ============================================================================
-- Follow-Up Form Fields
-- ============================================================================
('FLD-FUP-001', 'followup_create', 'lead_ref', 'Target Lead (Optional if Party)', 'select', 'Select lead', NULL, 0, 0, NULL, 'API_ENDPOINT', '/api/v1/admin/leads', 1, 6, 10, 1),
('FLD-FUP-002', 'followup_create', 'party_ref', 'Target Party (Optional if Lead)', 'select', 'Select party', NULL, 0, 0, NULL, 'API_ENDPOINT', '/api/v1/admin/parties', 1, 6, 20, 1),
('FLD-FUP-003', 'followup_create', 'activity_type', 'Activity / Interaction Channel', 'select', 'Select mode', 'CALL', 1, 0, '{"required":true}', 'CATALOG_MASTER', 'FOLLOWUP_TYPE', 1, 6, 30, 1),
('FLD-FUP-004', 'followup_create', 'next_follow_up_at', 'Next Follow-up Timestamp', 'text', 'YYYY-MM-DD HH:MM', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 40, 1),
('FLD-FUP-005', 'followup_create', 'next_action', 'Planned Action / Objective', 'text', 'e.g. Share quotation / Collect PO', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 50, 1),
('FLD-FUP-006', 'followup_create', 'remark', 'Discussion Notes', 'textarea', 'Detailed follow-up notes...', NULL, 0, 0, NULL, 'NONE', NULL, 1, 12, 60, 1),

-- ============================================================================
-- Territory Management & Overrides
-- ============================================================================
('FLD-TER-001', 'territory_create', 'party_ref', 'Commercial Party', 'select', 'Select distributor / partner', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/parties', 1, 6, 10, 1),
('FLD-TER-002', 'territory_create', 'level', 'Territory Granularity Level', 'select', 'Select level', 'PINCODE', 1, 0, '{"required":true}', 'STATIC_JSON', '[{"code":"PINCODE","label":"Pincode Level (Granular)"},{"code":"DISTRICT","label":"District Level (Broad)"}]', 1, 6, 20, 1),
('FLD-TER-003', 'territory_create', 'pincode', 'Postal Pincode', 'text', '6-digit pincode', NULL, 0, 0, '{"pattern":"^[1-9][0-9]{5}$"}', 'NONE', NULL, 1, 6, 30, 1),
('FLD-TER-004', 'territory_create', 'district_ref', 'District Reference', 'select', 'Select district', NULL, 0, 0, NULL, 'API_ENDPOINT', '/api/v1/geo/districts', 1, 6, 40, 1),
('FLD-TER-005', 'territory_create', 'effective_from', 'Exclusivity Effective From Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 50, 1),
('FLD-TER-006', 'territory_create', 'effective_to', 'Exclusivity Effective To Date', 'date', 'YYYY-MM-DD', NULL, 0, 0, NULL, 'NONE', NULL, 1, 6, 60, 1),
('FLD-TER-007', 'territory_create', 'is_exclusive', 'Exclusive Territory Lock (Monopoly)', 'checkbox', NULL, '1', 0, 0, NULL, 'NONE', NULL, 1, 6, 70, 1),

('FLD-OVR-001', 'territory_override', 'order_ref', 'Target Order Reference', 'select', 'Select order on hold', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/orders?status=HOLD', 1, 6, 10, 1),
('FLD-OVR-002', 'territory_override', 'party_ref', 'Target Party', 'select', 'Select party', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/parties', 1, 6, 20, 1),
('FLD-OVR-003', 'territory_override', 'pincode', 'Delivery Pincode', 'text', '6-digit pincode', NULL, 1, 0, '{"required":true,"pattern":"^[1-9][0-9]{5}$"}', 'NONE', NULL, 1, 6, 30, 1),
('FLD-OVR-004', 'territory_override', 'reason', 'Audited Administrative Justification', 'textarea', 'Explain exception approval rationale...', NULL, 1, 0, '{"required":true,"min":3}', 'NONE', NULL, 1, 12, 40, 1),

-- ============================================================================
-- Distributor 4-Step KYC Onboarding Wizard
-- ============================================================================
-- Step 1: Basic & Commercial Identity
('FLD-ONB-001', 'onboarding_register', 'firm_name', 'Authorized Firm / Company Name', 'text', 'e.g. Apex Healthcare Agencies', NULL, 1, 0, '{"required":true,"min":3}', 'NONE', NULL, 1, 6, 10, 1),
('FLD-ONB-002', 'onboarding_register', 'constitution_type', 'Constitution of Business', 'select', 'Select constitution', 'Proprietorship', 1, 0, '{"required":true}', 'CATALOG_MASTER', 'CONSTITUTION_TYPE', 1, 6, 20, 1),
('FLD-ONB-003', 'onboarding_register', 'contact_name', 'Authorized Signatory / Contact Person', 'text', 'e.g. Rajesh Sharma', NULL, 1, 0, '{"required":true,"min":3}', 'NONE', NULL, 1, 6, 30, 1),
('FLD-ONB-004', 'onboarding_register', 'designation', 'Designation / Capacity', 'text', 'e.g. Proprietor / Managing Partner', 'Proprietor', 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 40, 1),
('FLD-ONB-005', 'onboarding_register', 'mobile', 'Official Mobile Number', 'phone', '10-digit mobile number', NULL, 1, 0, '{"required":true,"pattern":"^[6-9][0-9]{9}$"}', 'NONE', NULL, 1, 6, 50, 1),
('FLD-ONB-006', 'onboarding_register', 'email', 'Official Business Email', 'email', 'e.g. accounts@apexhealth.in', NULL, 1, 0, '{"required":true,"email":true}', 'NONE', NULL, 1, 6, 60, 1),
('FLD-ONB-007', 'onboarding_register', 'password', 'Portal Account Password', 'password', 'Minimum 8 characters with symbols', NULL, 1, 0, '{"required":true,"min":8}', 'NONE', NULL, 1, 6, 70, 1),

-- Step 2: Statutory Licences & Tax IDs
('FLD-ONB-010', 'onboarding_register', 'gstin', 'Goods & Services Tax ID (GSTIN)', 'text', '15-digit GSTIN', NULL, 1, 0, '{"required":true,"pattern":"^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$"}', 'NONE', NULL, 2, 6, 80, 1),
('FLD-ONB-011', 'onboarding_register', 'pan', 'Permanent Account Number (PAN)', 'text', '10-character PAN', NULL, 1, 0, '{"required":true,"pattern":"^[A-Z]{5}[0-9]{4}[A-Z]{1}$"}', 'NONE', NULL, 2, 6, 90, 1),
('FLD-ONB-012', 'onboarding_register', 'drug_license_no', 'Form 20B / 21B Drug Licence Number', 'text', 'e.g. DL-MH-123456', NULL, 1, 0, '{"required":true,"min":5}', 'NONE', NULL, 2, 6, 100, 1),
('FLD-ONB-013', 'onboarding_register', 'drug_license_validity', 'Drug Licence Expiry Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 2, 6, 110, 1),
('FLD-ONB-014', 'onboarding_register', 'state_ref', 'State', 'select', 'Select state', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/geo/states', 2, 6, 120, 1),
('FLD-ONB-015', 'onboarding_register', 'district_ref', 'District', 'select', 'Select district', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/geo/districts', 2, 6, 130, 1),
('FLD-ONB-016', 'onboarding_register', 'pincode', 'Pincode', 'text', '6-digit PIN', NULL, 1, 0, '{"required":true,"pattern":"^[1-9][0-9]{5}$"}', 'NONE', NULL, 2, 6, 140, 1),
('FLD-ONB-017', 'onboarding_register', 'billing_address', 'Registered Address', 'textarea', 'Complete premises address...', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 2, 12, 150, 1),

-- Step 3: Banking & Commercial Preferences
('FLD-ONB-020', 'onboarding_register', 'bank_name', 'Bank Name', 'text', 'e.g. State Bank of India', NULL, 0, 0, NULL, 'NONE', NULL, 3, 6, 160, 1),
('FLD-ONB-021', 'onboarding_register', 'bank_account_number', 'Bank Account Number', 'text', 'Current account number', NULL, 0, 0, NULL, 'NONE', NULL, 3, 6, 170, 1),
('FLD-ONB-022', 'onboarding_register', 'bank_ifsc', 'Bank IFSC Code', 'text', '11-character IFSC', NULL, 0, 0, '{"pattern":"^[A-Z]{4}0[A-Z0-9]{6}$"}', 'NONE', NULL, 3, 6, 180, 1),
('FLD-ONB-023', 'onboarding_register', 'expected_monthly_business', 'Expected Monthly Business Volume (₹)', 'currency', '0.00', NULL, 0, 0, NULL, 'NONE', NULL, 3, 6, 190, 1),
('FLD-ONB-024', 'onboarding_register', 'preferred_product_categories', 'Primary Therapeutic Segments Desired', 'multiselect', 'Select categories', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/categories', 3, 12, 200, 1),

-- Step 4: Mandatory Statutory KYC Uploads
('FLD-ONB-030', 'onboarding_register', 'drug_licence_doc', 'Drug Licence Certificate (PDF / JPG)', 'file', 'Attach valid copy', NULL, 1, 0, '{"required":true,"max_size_mb":5}', 'NONE', NULL, 4, 6, 210, 1),
('FLD-ONB-031', 'onboarding_register', 'gst_certificate_doc', 'GST Registration Certificate (REG-06)', 'file', 'Attach REG-06', NULL, 1, 0, '{"required":true,"max_size_mb":5}', 'NONE', NULL, 4, 6, 220, 1),
('FLD-ONB-032', 'onboarding_register', 'pan_doc', 'PAN Card Copy', 'file', 'Attach firm / proprietor PAN', NULL, 1, 0, '{"required":true,"max_size_mb":5}', 'NONE', NULL, 4, 6, 230, 1),
('FLD-ONB-033', 'onboarding_register', 'cancelled_cheque_doc', 'Cancelled Cheque Leaf / Bank Statement', 'file', 'Attach bank proof', NULL, 1, 0, '{"required":true,"max_size_mb":5}', 'NONE', NULL, 4, 6, 240, 1),
('FLD-ONB-034', 'onboarding_register', 'incorporation_cert_doc', 'Partnership Deed / Incorporation Certificate', 'file', 'Attach if Partnership or Pvt Ltd', NULL, 0, 0, '{"max_size_mb":5}', 'NONE', NULL, 4, 6, 250, 1)

ON DUPLICATE KEY UPDATE label = VALUES(label), validation_rules_json = VALUES(validation_rules_json), options_source_type = VALUES(options_source_type), options_source_key = VALUES(options_source_key);

SET FOREIGN_KEY_CHECKS = 1;
