-- ============================================================================
-- PHARMA CRM & SALES FORCE AUTOMATION
-- Migration 002: Zero-Local-Data Architecture Engine & Dynamic Form Schemas
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 0;

-- 1. UI Form Schemas
CREATE TABLE IF NOT EXISTS `ui_form_schemas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `schema_ref` varchar(32) NOT NULL,
  `form_key` varchar(64) NOT NULL,
  `title` varchar(191) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `entity_type` varchar(64) NOT NULL,
  `version` int(11) NOT NULL DEFAULT 1,
  `status` enum('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_schema_ref` (`schema_ref`),
  UNIQUE KEY `uq_form_key` (`form_key`),
  KEY `idx_form_schema_status` (`status`, `entity_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. UI Form Field Definitions
CREATE TABLE IF NOT EXISTS `ui_form_fields` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `field_ref` varchar(32) NOT NULL,
  `form_key` varchar(64) NOT NULL,
  `field_name` varchar(64) NOT NULL,
  `label` varchar(191) NOT NULL,
  `field_type` varchar(32) NOT NULL,
  `placeholder` varchar(191) DEFAULT NULL,
  `default_value` text DEFAULT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `is_readonly` tinyint(1) NOT NULL DEFAULT 0,
  `validation_rules_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `options_source_type` enum('NONE','CATALOG_MASTER','API_ENDPOINT','STATIC_JSON') NOT NULL DEFAULT 'NONE',
  `options_source_key` varchar(191) DEFAULT NULL,
  `options_json` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `step_number` int(11) NOT NULL DEFAULT 1,
  `grid_width` int(11) NOT NULL DEFAULT 12,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_field_ref` (`field_ref`),
  UNIQUE KEY `uq_form_field` (`form_key`, `field_name`),
  KEY `idx_form_field_sort` (`form_key`, `step_number`, `sort_order`, `is_active`),
  CONSTRAINT `fk_form_field_schema` FOREIGN KEY (`form_key`) REFERENCES `ui_form_schemas` (`form_key`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Seed Catalog Master Values (Comprehensive Lookup Taxonomies)
INSERT INTO `catalog_master_values` (`master_ref`, `org_ref`, `franchise_ref`, `master_key`, `name`, `description`, `status`, `created_by_ref`) VALUES
-- Dosage Forms
('MAS-DOS-TAB00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DOSAGE_FORM', 'Tablet', 'Solid oral compressed dosage form', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-DOS-CAP00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DOSAGE_FORM', 'Capsule', 'Gelatin encased oral dosage form', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-DOS-SYR00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DOSAGE_FORM', 'Syrup', 'Liquid oral solution or suspension', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-DOS-INJ00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DOSAGE_FORM', 'Injectable', 'Sterile parenteral vial or ampoule', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-DOS-OIN00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DOSAGE_FORM', 'Ointment / Gel', 'Semisolid topical preparation', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-DOS-DRP00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'DOSAGE_FORM', 'Drops', 'Ophthalmic / Otic liquid drops', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Scheme Types
('MAS-SCH-QTY00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SCHEME_TYPE', 'Quantity Free Goods', 'Standard 10+1 or tiered free goods', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-SCH-VAL00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SCHEME_TYPE', 'Order Value Discount', 'Flat discount based on invoice gross total', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-SCH-PRO00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'SCHEME_TYPE', 'Product Promotional Gift', 'Non-medicinal promotional article with order', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Lead Sources
('MAS-LDS-IND00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_SOURCE', 'IndiaMART', 'B2B portal verified inquiry', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LDS-TRD00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_SOURCE', 'TradeIndia', 'Trade portal inquiry', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LDS-WEB00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_SOURCE', 'Website', 'Direct corporate web landing page form', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LDS-FLD00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_SOURCE', 'Field Visit', 'Doorstep prospecting by medical representative', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LDS-REF00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_SOURCE', 'Referral', 'Referral from existing doctor or stockist', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LDS-EXH00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_SOURCE', 'Pharma Expo / Conference', 'Exhibition stall contact capture', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Lead Statuses
('MAS-LST-NEW00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_STATUS', 'NEW', 'Fresh incoming lead uncontacted', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LST-CON00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_STATUS', 'CONTACTED', 'Initial discussion established', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LST-QUA00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_STATUS', 'QUALIFIED', 'Drug license and territory confirmed viable', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LST-PRO00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_STATUS', 'PROPOSAL_SENT', 'Price list and PCD contract shared', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LST-NEG00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_STATUS', 'NEGOTIATION', 'Commercial terms and exclusivity review', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LST-CVT00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_STATUS', 'CONVERTED', 'Successfully registered as billing party', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-LST-LST00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'LEAD_STATUS', 'LOST', 'Lead declined or dropped', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Party Types
('MAS-PTY-DIS00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PARTY_TYPE', 'Distributor', 'Authorized PCD franchise stockist', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PTY-WHL00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PARTY_TYPE', 'Wholesaler', 'Secondary pharma wholesale trader', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PTY-RET00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PARTY_TYPE', 'Retailer / Pharmacy', 'Licensed retail pharmacy shop', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PTY-HOS00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PARTY_TYPE', 'Hospital / Nursing Home', 'Institutional clinical healthcare account', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PTY-DOC00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PARTY_TYPE', 'Dispensing Doctor', 'Registered medical practitioner dispensing clinic', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Constitution Types
('MAS-CST-PRO00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CONSTITUTION_TYPE', 'Proprietorship', 'Sole proprietorship commercial enterprise', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-CST-PAR00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CONSTITUTION_TYPE', 'Partnership', 'Registered partnership firm under Indian Act', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-CST-PVT00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CONSTITUTION_TYPE', 'Pvt Ltd', 'Private Limited Indian corporate company', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-CST-LLP00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'CONSTITUTION_TYPE', 'LLP', 'Limited Liability Partnership', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Packaging Types
('MAS-PKG-BLS00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PACKAGING_TYPE', 'Blister', 'Alu-PVC Blister pack strip', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PKG-ALU00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PACKAGING_TYPE', 'Alu-Alu', 'Cold formable aluminium strip packaging', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PKG-BOT00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PACKAGING_TYPE', 'Bottle', 'Amber PET / Glass bottle', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PKG-VIA00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PACKAGING_TYPE', 'Vial', 'Sterile glass injection vial with flip-off seal', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PKG-AMP00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PACKAGING_TYPE', 'Ampoule', 'Hermetically sealed glass ampoule', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PKG-TUB00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PACKAGING_TYPE', 'Tube', 'Laminated collapsible ointment tube', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Units of Measure (UOM)
('MAS-UOM-BOX00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'UOM', 'BOX', 'Standard carton box of strips or vials', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-UOM-STR00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'UOM', 'STRIP', 'Individual unit strip', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-UOM-BOT00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'UOM', 'BOTTLE', 'Individual liquid or suspension unit', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-UOM-PCS00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'UOM', 'PIECE', 'Single piece item', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Payment Modes
('MAS-PAY-NEF00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAYMENT_MODE', 'NEFT', 'National Electronic Funds Transfer', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PAY-RTG00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAYMENT_MODE', 'RTGS', 'Real Time Gross Settlement', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PAY-IMP00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAYMENT_MODE', 'IMPS', 'Immediate Payment Service', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PAY-UPI00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAYMENT_MODE', 'UPI', 'Unified Payments Interface transfer', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PAY-CHQ00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAYMENT_MODE', 'CHEQUE', 'Standard bank clearing cheque', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PAY-PDC00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAYMENT_MODE', 'PDC', 'Post-Dated Cheque for maturity credit', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-PAY-CSH00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'PAYMENT_MODE', 'CASH', 'Hand-delivered physical cash (subject to statutory limits)', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- DCR Work Types
('MAS-WRK-FLD00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'WORK_TYPE', 'FIELD_WORK', 'Active field doctor / stockist territory calling', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-WRK-LEV00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'WORK_TYPE', 'LEAVE', 'Approved official medical or personal leave', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-WRK-HOL00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'WORK_TYPE', 'HOLIDAY', 'Gazetted or public company holiday', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-WRK-MTG00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'WORK_TYPE', 'MEETING', 'Sales review or internal headquarter meeting', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-WRK-TRN00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'WORK_TYPE', 'TRAINING', 'Product launch or medical training workshop', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Follow-Up Types
('MAS-FUP-CAL00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'FOLLOWUP_TYPE', 'CALL', 'Telephone voice conversation', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-FUP-VIS00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'FOLLOWUP_TYPE', 'VISIT', 'In-person clinic or shop meeting', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-FUP-WHT00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'FOLLOWUP_TYPE', 'WHATSAPP', 'Official business WhatsApp chat', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-FUP-EML00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'FOLLOWUP_TYPE', 'EMAIL', 'Formal email proposal dispatch', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Stock Adjustment Reasons
('MAS-SAR-DAM00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'STOCK_ADJUSTMENT_REASON', 'DAMAGED', 'Physical transit or storage breakage', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-SAR-EXP00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'STOCK_ADJUSTMENT_REASON', 'EXPIRED', 'Stock crossed expiry date quarantined', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-SAR-COR00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'STOCK_ADJUSTMENT_REASON', 'CORRECTION', 'Physical stock inventory audit reconciliation', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-SAR-SMP00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'STOCK_ADJUSTMENT_REASON', 'SAMPLE_TRANSFER', 'Transferred to MR physician sampling bag', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-SAR-RET00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'STOCK_ADJUSTMENT_REASON', 'RETURN_RESTOCK', 'Saleable goods return received and approved', 'ACTIVE', 'USR-SUPERADMIN0000001'),

-- Transporter Modes
('MAS-TRN-ROA00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'TRANSPORTER_MODE', 'ROAD', 'Surface logistics by truck / lorry', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-TRN-AIR00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'TRANSPORTER_MODE', 'AIR', 'Air cargo courier priority', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-TRN-RAI00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'TRANSPORTER_MODE', 'RAIL', 'Indian Railways parcel express', 'ACTIVE', 'USR-SUPERADMIN0000001'),
('MAS-TRN-HND00000000001', 'ORG-PLATFORM0000000001', 'FRN-MUMBAI000000000001', 'TRANSPORTER_MODE', 'HAND_DELIVERY', 'Direct local warehouse counter collection', 'ACTIVE', 'USR-SUPERADMIN0000001')

ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description);

-- 4. Seed Default System Settings
INSERT INTO `system_settings` (`franchise_ref`, `setting_key`, `setting_value`, `data_type`, `description`, `is_encrypted`, `updated_by_ref`) VALUES
('FRN-MUMBAI000000000001', 'INVENTORY_NEAR_EXPIRY_THRESHOLD_DAYS', '90', 'INT', 'Days before product batch expiry to flag as near expiry', 0, 'USR-FRNADMIN000000001'),
('FRN-MUMBAI000000000001', 'ORDER_AUTO_APPROVE_BELOW_VALUE', '10000', 'DECIMAL', 'Order total under which automatic confirmation can be configured', 0, 'USR-FRNADMIN000000001'),
('FRN-MUMBAI000000000001', 'DEFAULT_CREDIT_PERIOD_DAYS', '30', 'INT', 'Standard credit payment term days for new parties', 0, 'USR-FRNADMIN000000001'),
('FRN-MUMBAI000000000001', 'DEFAULT_CREDIT_LIMIT_AMOUNT', '50000', 'DECIMAL', 'Initial credit limit for newly converted parties', 0, 'USR-FRNADMIN000000001'),
('FRN-MUMBAI000000000001', 'INVOICE_INTEREST_RATE_OVERDUE', '18.00', 'DECIMAL', 'Annual interest percentage calculated on overdue invoices', 0, 'USR-FRNADMIN000000001'),
('FRN-MUMBAI000000000001', 'DCR_MANDATORY_LOCATION_CAPTURING', '1', 'BOOLEAN', 'Require GPS coordinates on field visits', 0, 'USR-FRNADMIN000000001'),
('FRN-MUMBAI000000000001', 'GST_E_INVOICE_MANDATORY', '0', 'BOOLEAN', 'Enforce NIC e-invoice generation on invoices above statutory limit', 0, 'USR-FRNADMIN000000001')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- 5. Seed Core UI Form Schemas
INSERT INTO `ui_form_schemas` (`schema_ref`, `form_key`, `title`, `description`, `entity_type`, `version`, `status`) VALUES
('SCH-FRM-LEAD0000000001', 'lead_create', 'Create New Sales Lead', 'Zero-local form schema for acquiring prospective doctor or stockist leads', 'lead', 1, 'ACTIVE'),
('SCH-FRM-PARTY000000001', 'party_create', 'Create Commercial Party', 'Zero-local form schema for registering PCD franchise distributors and retailers', 'party', 1, 'ACTIVE'),
('SCH-FRM-PROD0000000001', 'product_create', 'Create Pharmaceutical Product', 'Zero-local form schema for creating catalog SKU with composition, packaging, and HSN', 'product', 1, 'ACTIVE'),
('SCH-FRM-ORDER000000001', 'order_create', 'Create Sales Order', 'Zero-local commercial order header and entry schema', 'order', 1, 'ACTIVE'),
('SCH-FRM-DISP0000000001', 'dispatch_create', 'Create Consignment Dispatch', 'Zero-local dispatch note with transporter and LR assignment', 'dispatch', 1, 'ACTIVE'),
('SCH-FRM-PAY00000000001', 'payment_create', 'Record Payment Receipt', 'Zero-local bank and cash collection payment recording schema', 'payment', 1, 'ACTIVE'),
('SCH-FRM-PDC00000000001', 'pdc_create', 'Register Post-Dated Cheque', 'Zero-local PDC registration form with bank details and maturity date', 'pdc', 1, 'ACTIVE'),
('SCH-FRM-DCR00000000001', 'dcr_create', 'Daily Call Report', 'Zero-local field representative daily report with visit details', 'dcr', 1, 'ACTIVE'),
('SCH-FRM-ONB00000000001', 'onboarding_register', 'Distributor Self-Registration', 'Zero-local 4-step comprehensive KYC onboarding wizard', 'onboarding', 1, 'ACTIVE')
ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description);

-- 6. Seed UI Form Fields (Sample Master Field Mapping for Lead, Party, Product, Order)
INSERT INTO `ui_form_fields` (
  `field_ref`, `form_key`, `field_name`, `label`, `field_type`, `placeholder`,
  `default_value`, `is_required`, `is_readonly`, `validation_rules_json`,
  `options_source_type`, `options_source_key`, `step_number`, `grid_width`, `sort_order`, `is_active`
) VALUES
-- Lead Form Fields
('FLD-LED-001', 'lead_create', 'contact_name', 'Contact Person Name', 'text', 'e.g. Dr. Anil Kumar', NULL, 1, 0, '{"required":true,"min":3,"max":191}', 'NONE', NULL, 1, 6, 10, 1),
('FLD-LED-002', 'lead_create', 'firm_name', 'Firm / Clinic Name', 'text', 'e.g. Kumar Poly Clinic', NULL, 1, 0, '{"required":true,"min":3,"max":191}', 'NONE', NULL, 1, 6, 20, 1),
('FLD-LED-003', 'lead_create', 'mobile', 'Mobile Number', 'phone', '10-digit mobile number', NULL, 1, 0, '{"required":true,"pattern":"^[6-9][0-9]{9}$","message":"Enter valid 10-digit Indian mobile number"}', 'NONE', NULL, 1, 6, 30, 1),
('FLD-LED-004', 'lead_create', 'email', 'Email Address', 'email', 'e.g. doctor@example.com', NULL, 0, 0, '{"email":true}', 'NONE', NULL, 1, 6, 40, 1),
('FLD-LED-005', 'lead_create', 'lead_source', 'Lead Source', 'select', 'Select source', 'FIELD_VISIT', 1, 0, '{"required":true}', 'CATALOG_MASTER', 'LEAD_SOURCE', 1, 6, 50, 1),
('FLD-LED-006', 'lead_create', 'business_type', 'Business / Specialty Type', 'select', 'Select specialty', NULL, 0, 0, NULL, 'CATALOG_MASTER', 'PARTY_TYPE', 1, 6, 60, 1),
('FLD-LED-007', 'lead_create', 'state_ref', 'State', 'select', 'Select state', 'STA-MAHARASHTRA00001', 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/geo/states', 1, 6, 70, 1),
('FLD-LED-008', 'lead_create', 'district_ref', 'District', 'select', 'Select district', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/geo/districts', 1, 6, 80, 1),
('FLD-LED-009', 'lead_create', 'pincode', 'Pincode', 'text', '6-digit pincode', NULL, 1, 0, '{"required":true,"pattern":"^[1-9][0-9]{5}$","message":"Enter valid 6-digit Indian PIN code"}', 'NONE', NULL, 1, 6, 90, 1),
('FLD-LED-010', 'lead_create', 'priority', 'Lead Priority', 'select', 'Select priority', 'NORMAL', 1, 0, '{"required":true}', 'STATIC_JSON', '[{"code":"LOW","label":"Low"},{"code":"NORMAL","label":"Normal"},{"code":"HIGH","label":"High"},{"code":"URGENT","label":"Urgent"}]', 1, 6, 100, 1),
('FLD-LED-011', 'lead_create', 'initial_remark', 'Initial Discussion Notes', 'textarea', 'Doctor prescription habits, interested categories, competitors...', NULL, 0, 0, NULL, 'NONE', NULL, 1, 12, 110, 1),

-- Party Form Fields
('FLD-PTY-001', 'party_create', 'firm_name', 'Authorized Firm Name', 'text', 'e.g. Apollo Pharma Distributors', NULL, 1, 0, '{"required":true,"min":3,"max":191}', 'NONE', NULL, 1, 6, 10, 1),
('FLD-PTY-002', 'party_create', 'contact_name', 'Proprietor / Contact Name', 'text', 'e.g. Ramesh Patel', NULL, 1, 0, '{"required":true,"min":3,"max":191}', 'NONE', NULL, 1, 6, 20, 1),
('FLD-PTY-003', 'party_create', 'party_type', 'Party Commercial Category', 'select', 'Select party type', 'Distributor', 1, 0, '{"required":true}', 'CATALOG_MASTER', 'PARTY_TYPE', 1, 6, 30, 1),
('FLD-PTY-004', 'party_create', 'gstin', 'GSTIN (Tax ID)', 'text', '15-digit GSTIN', NULL, 1, 0, '{"required":true,"pattern":"^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$","message":"Enter valid 15-character GSTIN"}', 'NONE', NULL, 1, 6, 40, 1),
('FLD-PTY-005', 'party_create', 'drug_license_no', 'Drug Licence Number', 'text', 'e.g. MH-MZ1-123456, 123457', NULL, 1, 0, '{"required":true,"min":5}', 'NONE', NULL, 1, 6, 50, 1),
('FLD-PTY-006', 'party_create', 'drug_license_validity', 'Drug Licence Validity Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 60, 1),
('FLD-PTY-007', 'party_create', 'mobile', 'Official Mobile Number', 'phone', '10-digit mobile', NULL, 1, 0, '{"required":true,"pattern":"^[6-9][0-9]{9}$"}', 'NONE', NULL, 1, 6, 70, 1),
('FLD-PTY-008', 'party_create', 'tier_ref', 'Assigned Pricing Tier', 'select', 'Select pricing tier', 'TIER-DISTRIBUTOR00001', 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/tiers', 1, 6, 80, 1),
('FLD-PTY-009', 'party_create', 'credit_limit', 'Credit Limit (₹)', 'currency', '0.00', '50000.00', 1, 0, '{"required":true,"min":0}', 'NONE', NULL, 1, 6, 90, 1),
('FLD-PTY-010', 'party_create', 'payment_terms_days', 'Payment Terms (Days)', 'number', 'e.g. 30', '30', 1, 0, '{"required":true,"min":0,"max":180}', 'NONE', NULL, 1, 6, 100, 1),
('FLD-PTY-011', 'party_create', 'billing_address', 'Registered Billing Address', 'textarea', 'Complete address with landmarks', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 12, 110, 1),

-- Product Form Fields
('FLD-PRD-001', 'product_create', 'product_name', 'Brand / Product Name', 'text', 'e.g. Paracetamol 500mg Tablets', NULL, 1, 0, '{"required":true,"min":2}', 'NONE', NULL, 1, 6, 10, 1),
('FLD-PRD-002', 'product_create', 'generic_name', 'Generic / Salt Name', 'text', 'e.g. Paracetamol IP', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 20, 1),
('FLD-PRD-003', 'product_create', 'category_ref', 'Product Category', 'select', 'Select category', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/categories', 1, 6, 30, 1),
('FLD-PRD-004', 'product_create', 'dosage_form', 'Dosage Form', 'select', 'Select dosage form', 'Tablet', 1, 0, '{"required":true}', 'CATALOG_MASTER', 'DOSAGE_FORM', 1, 6, 40, 1),
('FLD-PRD-005', 'product_create', 'packing', 'Packaging Specification', 'text', 'e.g. 10x10 Blister Pack', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 50, 1),
('FLD-PRD-006', 'product_create', 'hsn_code', 'HSN Code', 'text', 'e.g. 30049099', '30049099', 1, 0, '{"required":true,"pattern":"^[0-9]{4,8}$"}', 'NONE', NULL, 1, 6, 60, 1),
('FLD-PRD-007', 'product_create', 'mrp', 'Maximum Retail Price (₹ MRP)', 'currency', '0.00', NULL, 1, 0, '{"required":true,"min":0.01}', 'NONE', NULL, 1, 4, 70, 1),
('FLD-PRD-008', 'product_create', 'pts', 'Price to Stockist (₹ PTS)', 'currency', '0.00', NULL, 1, 0, '{"required":true,"min":0.01}', 'NONE', NULL, 1, 4, 80, 1),
('FLD-PRD-009', 'product_create', 'ptr', 'Price to Retailer (₹ PTR)', 'currency', '0.00', NULL, 1, 0, '{"required":true,"min":0.01}', 'NONE', NULL, 1, 4, 90, 1),
('FLD-PRD-010', 'product_create', 'gst_rate', 'GST Slab (%)', 'select', 'Select GST %', '12', 1, 0, '{"required":true}', 'STATIC_JSON', '[{"code":"0","label":"0% Exempt"},{"code":"5","label":"5% GST"},{"code":"12","label":"12% Standard Pharma"},{"code":"18","label":"18% Speciality"},{"code":"28","label":"28% Luxury"}]', 1, 6, 100, 1),

-- Payment Form Fields
('FLD-PAY-001', 'payment_create', 'party_ref', 'Select Party', 'select', 'Choose distributor / stockist', NULL, 1, 0, '{"required":true}', 'API_ENDPOINT', '/api/v1/admin/parties', 1, 6, 10, 1),
('FLD-PAY-002', 'payment_create', 'payment_date', 'Payment Receipt Date', 'date', 'YYYY-MM-DD', NULL, 1, 0, '{"required":true}', 'NONE', NULL, 1, 6, 20, 1),
('FLD-PAY-003', 'payment_create', 'amount', 'Amount Received (₹)', 'currency', '0.00', NULL, 1, 0, '{"required":true,"min":1}', 'NONE', NULL, 1, 6, 30, 1),
('FLD-PAY-004', 'payment_create', 'mode', 'Payment Mode', 'select', 'Select mode', 'NEFT', 1, 0, '{"required":true}', 'CATALOG_MASTER', 'PAYMENT_MODE', 1, 6, 40, 1),
('FLD-PAY-005', 'payment_create', 'reference_no', 'UTR / Cheque / Transaction Ref', 'text', 'e.g. UTR1234567890', NULL, 0, 0, NULL, 'NONE', NULL, 1, 6, 50, 1),
('FLD-PAY-006', 'payment_create', 'remarks', 'Collection Remarks', 'textarea', 'Notes...', NULL, 0, 0, NULL, 'NONE', NULL, 1, 12, 60, 1)

ON DUPLICATE KEY UPDATE label = VALUES(label), validation_rules_json = VALUES(validation_rules_json), options_source_type = VALUES(options_source_type), options_source_key = VALUES(options_source_key);

SET FOREIGN_KEY_CHECKS = 1;
