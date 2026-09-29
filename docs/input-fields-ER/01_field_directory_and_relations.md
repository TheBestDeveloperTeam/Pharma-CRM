# Database-Driven Field Directory, Schemas & Entity Relations

This document defines the complete catalog of all input fields across all modules, forms, modals, and dialogs. Every field is mapped directly to its underlying database table, SQL column type, foreign key relation, server-side validation rule, and authoritative calculation source.

---

## 1. Authentication, Sessions & Password Reset

### Table: `users` / `oauth_refresh_tokens` / `password_resets`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Regex |
|---|---|---|---|---|
| Email Address | `users.email` | `varchar(191) NOT NULL UNIQUE` | DB Unique Index | `email`, max: 191, required |
| Password | `users.password_hash` | `varchar(255) NOT NULL` | Argon2id / Bcrypt hash | min: 8, upper, lower, number, special char |
| Remember Me | N/A (Session control) | N/A | Server TTL setting | Boolean (`0` or `1`) |
| Organization Key | `users.org_ref` | `varchar(24) NOT NULL FK` | `organizations.org_ref` | Exists in `organizations`, active |
| Franchise Key | `users.franchise_ref` | `varchar(24) NULL FK` | `franchises.franchise_ref` | Exists in `franchises`, active |
| Reset Token | `password_resets.token` | `varchar(64) NOT NULL` | SHA-256 token | 64-char hex, expiry < 30 mins |
| New Password | `users.password_hash` | `varchar(255) NOT NULL` | Password hash | min: 8, required, matching confirmation |

---

## 2. Lead Management & Conversion

### Table: `leads` / `lead_activities` / `follow_ups`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| Contact Person | `leads.contact_name` | `varchar(191) NOT NULL` | DB Column | min: 2, max: 191, sanitized string |
| Firm Name | `leads.firm_name` | `varchar(191) NOT NULL` | DB Column | min: 2, max: 191 |
| Mobile Phone | `leads.mobile` | `varchar(20) NOT NULL` | Duplicate check index | E.164 phone format, duplicate phone warning |
| WhatsApp Number | `leads.whatsapp_number` | `varchar(20) NULL` | DB Column | Valid phone regex, optional |
| Email Address | `leads.email` | `varchar(191) NULL` | DB Column | Valid email syntax, max: 191 |
| State | `leads.state_ref` | `varchar(24) NOT NULL FK` | `states.state_ref` | Foreign key validation |
| District | `leads.district_ref` | `varchar(24) NOT NULL FK` | `districts.district_ref` | FK matches `state_ref` |
| City | `leads.city_ref` | `varchar(24) NULL FK` | `cities.city_ref` | FK matches `district_ref` |
| Pincode | `leads.pincode` | `varchar(10) NULL` | `pincodes.pincode` | Exact 6 numeric digits (India) |
| Lead Source | `leads.source` | `varchar(50) NOT NULL` | `catalog_master_values('lead_source')` | Enum/Dynamic master ID |
| Business Type | `leads.business_type` | `varchar(50) NULL` | `catalog_master_values('business_type')` | Dynamic master lookup |
| Assigned Sales Rep | `leads.assigned_user_ref`| `varchar(24) NULL FK` | `users.user_ref WHERE role='SALES'` | Valid internal sales user in franchise |
| Priority | `leads.priority` | `enum('LOW','MEDIUM','HIGH','URGENT')` | DB Enum | In: `['LOW','MEDIUM','HIGH','URGENT']` |
| Lead Status | `leads.status` | `enum('NEW','CONTACTED','QUALIFIED','PROPOSAL','CONVERTED','LOST')` | DB State Machine Engine | Transition check: CONVERTED requires mandatory Party creation |
| Estimated Value | `leads.estimated_value` | `decimal(12,2) DEFAULT 0.00` | DB Column | Numeric >= 0.00 |
| Products of Interest| `party_product_interests`| `product_ref varchar(24) FK`| `products.product_ref` | Array of existing product IDs |

---

## 3. Party & Franchise Partner Management

### Table: `parties` / `party_territories`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| Party Code | `parties.party_code` | `varchar(32) NOT NULL UNIQUE`| DB Atomic Sequence | Auto-generated via `sequence_counters` (e.g. PRT-2026-0001) |
| Legal Firm Name | `parties.firm_name` | `varchar(191) NOT NULL` | DB Column | min: 3, max: 191 |
| Proprietor / Contact| `parties.contact_person`| `varchar(191) NOT NULL` | DB Column | min: 2, max: 191 |
| Mobile Phone | `parties.mobile` | `varchar(20) NOT NULL UNIQUE`| DB Unique Key | E.164 phone format, mandatory unique |
| WhatsApp | `parties.whatsapp_number` | `varchar(20) NULL` | DB Column | Phone regex |
| Email | `parties.email` | `varchar(191) NOT NULL` | DB Column | Valid email |
| GSTIN | `parties.gstin` | `varchar(15) NULL` | Legal Validation | 15-char regex: `^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$` |
| Drug License No 1 | `parties.dl_number_1` | `varchar(64) NOT NULL` | Compliance Audit | Mandatory for pharma sale, max: 64 |
| Drug License No 2 | `parties.dl_number_2` | `varchar(64) NULL` | Compliance Audit | Optional additional form 20B/21B |
| Drug License Expiry | `parties.dl_expiry_date` | `date NULL` | Date check | Must be future date at order time |
| Credit Limit | `parties.credit_limit` | `decimal(12,2) DEFAULT 0.00` | DB Column | Value >= 0.00; evaluated during order approval |
| Credit Days | `parties.credit_days` | `int(11) DEFAULT 0` | DB Column | Integer >= 0; sets invoice due date |
| Pricing Tier | `parties.pricing_tier_ref`| `varchar(24) NOT NULL FK`| `pricing_tiers.tier_ref` | Foreign Key validation |
| Assigned Territory | `party_territories` | `district_ref`, `pincode_ref`| `districts`, `pincodes` | Enforces exclusivity lock; duplicate error if locked |
| Current Outstanding | `parties.current_outstanding`| `decimal(12,2) DEFAULT 0.00`| Atomic Sum of unpaid invoices | Server-side computed, read-only on client |

---

## 4. Product Catalog, Pricing & Schemes

### Table: `products` / `product_prices` / `schemes` / `scheme_rules`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| Product Name | `products.product_name` | `varchar(191) NOT NULL` | DB Column | min: 2, max: 191 |
| SKU / Item Code | `products.sku_code` | `varchar(64) NOT NULL UNIQUE`| DB Unique Constraint | Alphanumeric, uppercase |
| Category | `products.category_ref` | `varchar(24) NOT NULL FK` | `product_categories.category_ref` | Must exist and be active |
| Composition | `products.composition` | `text NULL` | Pharmacopeia specification | String |
| Packing Size | `products.packing_size` | `varchar(100) NOT NULL` | `catalog_master_values('packing')`| e.g. "10x10 Tablets", "100ml Syrup" |
| HSN Code | `products.hsn_code` | `varchar(16) NOT NULL` | GST Master | 4 to 8 digits numeric |
| GST Rate (%) | `products.gst_rate` | `decimal(5,2) NOT NULL` | Statutory rates | Enum/Value in: `[0.00, 5.00, 12.00, 18.00, 28.00]` |
| Maximum Retail Price| `products.mrp` | `decimal(10,2) NOT NULL` | Base Commercial | mrp > 0.00 |
| Base PTS | `products.pts` | `decimal(10,2) NOT NULL` | Price to Stockist | pts <= mrp |
| Tier / Party Price | `product_prices.price` | `decimal(10,2) NOT NULL` | `product_prices` | Resolved dynamically by pricing engine |
| Scheme Name | `schemes.scheme_name` | `varchar(191) NOT NULL` | DB Column | String |
| Scheme Min Qty | `scheme_rules.min_order_qty`| `int(11) NOT NULL` | DB Column | Integer >= 1 |
| Scheme Free Qty | `scheme_rules.free_qty` | `int(11) NOT NULL` | DB Column | Integer >= 1 |
| Valid From / To | `schemes.valid_from/to` | `date NOT NULL` | Date Range | `valid_to >= valid_from` |

---

## 5. Orders & Commercial Cart Calculation Engine

### Table: `orders` / `order_items` / `stock_reservations`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| Order Number | `orders.order_number` | `varchar(32) NOT NULL UNIQUE`| DB Atomic Sequence | Gapless auto-number (e.g. ORD-2026-0001) |
| Party | `orders.party_ref` | `varchar(24) NOT NULL FK` | `parties.party_ref` | Party must be ACTIVE with valid DL |
| Order Date | `orders.order_date` | `date NOT NULL` | System Date | Current date |
| Item Ordered Qty | `order_items.quantity` | `int(11) NOT NULL` | User input | Integer >= 1 |
| Resolved Unit Rate | `order_items.unit_price` | `decimal(10,2) NOT NULL` | `PriceResolver` | Party Custom Price -> Tier Price -> Base PTS |
| Scheme Free Qty | `order_items.free_quantity`| `int(11) DEFAULT 0` | `SchemeCalculator` | `floor(quantity / min_qty) * free_qty` |
| Taxable Amount | `order_items.taxable_amount`| `decimal(12,2) NOT NULL`| Calculation | `quantity * unit_price` (Free goods billed at 0) |
| CGST / SGST / IGST | `order_items.cgst/sgst/igst`| `decimal(10,2) NOT NULL`| `GstCalculator` | If `party.state == franchise.state` then CGST+SGST, else IGST |
| Line Total | `order_items.total_amount` | `decimal(12,2) NOT NULL`| Calculation | `taxable_amount + cgst + sgst + igst` |
| Credit Exposure Check| N/A | `decimal(12,2)` | `CreditRuleService` | `current_outstanding + order_total <= credit_limit` (`ALLOW` / `HOLD`) |
| Order Status | `orders.status` | `enum('DRAFT','SUBMITTED','CONFIRMED','PROCESSING','DISPATCHED','DELIVERED','CANCELLED')` | State Machine | Enforces role-based transition authority |

---

## 6. Inventory, Batches & FEFO Allocations

### Table: `inventory_batches` / `inventory_movements`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| Product Ref | `inventory_batches.product_ref`| `varchar(24) NOT NULL FK`| `products.product_ref` | Active pharma SKU |
| Batch Number | `inventory_batches.batch_no` | `varchar(64) NOT NULL` | Manufacturer Batch | Unique per product, uppercase |
| Manufacturing Date | `inventory_batches.mfg_date` | `date NOT NULL` | DB Date | Must be <= today |
| Expiry Date | `inventory_batches.expiry_date` | `date NOT NULL` | DB Date | Must be > mfg_date (FEFO sort key) |
| Received Quantity | `inventory_batches.received_qty`| `int(11) NOT NULL` | GRN entry | Integer >= 1 |
| Available Quantity | `inventory_batches.available_qty`| `int(11) NOT NULL` | Dynamic Balance | `received_qty - reserved_qty - dispatched_qty` |
| Reserved Quantity | `inventory_batches.reserved_qty` | `int(11) NOT NULL DEFAULT 0`| Order Confirmation Lock | Allocated atomically when Order is CONFIRMED |
| Purchase Rate | `inventory_batches.purchase_rate`| `decimal(10,2) NOT NULL`| Goods Receipt Note | Cost accounting |

---

## 7. Invoicing, Dispatch & Logistics

### Table: `invoices` / `dispatches` / `transporters`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| Invoice Number | `invoices.invoice_number` | `varchar(32) NOT NULL UNIQUE`| DB Atomic Sequence | Gapless legal series (e.g. INV-2026-0001) |
| Order Ref | `invoices.order_ref` | `varchar(24) NOT NULL FK` | `orders.order_ref` | Order must be CONFIRMED/PROCESSING |
| Invoice Date | `invoices.invoice_date` | `date NOT NULL` | System Date | Current date |
| Due Date | `invoices.due_date` | `date NOT NULL` | Calculation | `invoice_date + party.credit_days` |
| Transporter | `dispatches.transporter_ref`| `varchar(24) NOT NULL FK`| `transporters.transporter_ref` | Must exist in master |
| LR Number | `dispatches.lr_number` | `varchar(64) NOT NULL` | Consignment Note | Required before dispatch confirmation |
| LR Date | `dispatches.lr_date` | `date NOT NULL` | Date | Date consignment handed over |
| Number of Boxes | `dispatches.boxes_count` | `int(11) NOT NULL` | Physical packaging | Integer >= 1 |
| Weight (Kg) | `dispatches.weight_kg` | `decimal(8,2) NULL` | Physical measurement | Numeric >= 0.00 |

---

## 8. Payments, Allocations & Outstanding Sync

### Table: `payments` / `payment_allocations` / `pdcs`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| Receipt Number | `payments.payment_number` | `varchar(32) NOT NULL UNIQUE`| DB Atomic Sequence | Gapless auto-number (e.g. PAY-2026-0001) |
| Party | `payments.party_ref` | `varchar(24) NOT NULL FK` | `parties.party_ref` | Active party |
| Payment Date | `payments.payment_date` | `date NOT NULL` | Transaction Date | Date <= today |
| Payment Mode | `payments.payment_mode` | `enum('NEFT','RTGS','IMPS','UPI','CHEQUE','CASH','PDC')` | `catalog_master_values` | Valid mode |
| Reference / UTR No | `payments.reference_no` | `varchar(100) NOT NULL` | Bank UTR / Cheque No | Unique check against double entry |
| Total Paid Amount | `payments.amount` | `decimal(12,2) NOT NULL` | Bank credit | Numeric > 0.00 |
| Invoice Allocation | `payment_allocations.amount`| `decimal(12,2) NOT NULL`| Invoices with balance > 0 | `sum(allocations) <= payment.amount` |
| Balance Due After | `invoices.balance_due` | `decimal(12,2) NOT NULL` | Dynamic Balance Update | `previous_balance - allocated_amount` |
| Outstanding Sync | `parties.current_outstanding`| `decimal(12,2) NOT NULL`| Atomic Ledger Update | Decremented by total cleared payment |

---

## 9. SFA & Daily Call Reports (DCR)

### Table: `daily_call_reports` / `dcr_doctor_visits` / `dcr_chemist_visits` / `dcr_sample_distributions`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| DCR Reference | `daily_call_reports.dcr_ref` | `varchar(24) NOT NULL PK` | Generated UUID | Auto-generated prefix `DCR-` |
| Work Date | `daily_call_reports.report_date`| `date NOT NULL` | Date | Must be <= today, unique per rep per day |
| Work Type | `daily_call_reports.work_type` | `enum('FIELD','OFFICE','TRANSIT','LEAVE')` | Master Enum | Required |
| Route / TP | `daily_call_reports.route_ref` | `varchar(24) NULL FK` | `tour_plans.route_ref` | Optional link to approved tour plan |
| Doctor Ref | `dcr_doctor_visits.doctor_ref` | `varchar(24) NOT NULL FK` | `doctors.doctor_ref` | Active doctor in assigned territory |
| Visit Time | `dcr_doctor_visits.visit_time` | `time NOT NULL` | Time | Valid time format `HH:MM` |
| Geolocation Lat | `dcr_doctor_visits.latitude` | `decimal(10,8) NULL` | Mobile GPS | Latitude -90 to +90 |
| Geolocation Lng | `dcr_doctor_visits.longitude`| `decimal(11,8) NULL` | Mobile GPS | Longitude -180 to +180 |
| POB Amount | `dcr_doctor_visits.pob_amount` | `decimal(10,2) DEFAULT 0.00`| Prescription Value | Numeric >= 0.00 |
| Sample Quantity | `dcr_sample_distributions.qty` | `int(11) NOT NULL` | Physical Handover | Integer >= 1; deducted from representative bag |

---

## 10. Post-Dated Cheques (PDC) & Financial Closures

### Table: `pdcs` / `financial_year_closures`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| PDC Reference | `pdcs.pdc_ref` | `varchar(24) NOT NULL PK` | Generated UUID | Auto-generated prefix `PDC-` |
| Cheque Number | `pdcs.cheque_number` | `varchar(32) NOT NULL` | Physical Instrument | 6 numeric digits |
| Maturity Date | `pdcs.cheque_date` | `date NOT NULL` | Date | Maturity date >= entry date |
| Cheque Amount | `pdcs.amount` | `decimal(12,2) NOT NULL` | Instrument Value | Numeric > 0.00 |
| Bank Name | `pdcs.bank_name` | `varchar(100) NOT NULL` | Drawee Bank | min: 2, max: 100 chars |
| PDC Status | `pdcs.status` | `enum('IN_HAND','DEPOSITED','CLEARED','BOUNCED','CANCELLED')` | State Machine | Triggers payment creation upon `CLEARED` |
| Fiscal Year | `financial_year_closures.fiscal_year`| `varchar(9) NOT NULL` | e.g. "2025-26" | Pattern `^[0-9]{4}-[0-9]{2}$` |
| Closing Date | `financial_year_closures.closing_date`| `date NOT NULL` | System Date | Sets ledger closing balance for fiscal year |
| Closing Surplus | `financial_year_closures.surplus_amount`| `decimal(14,2) NOT NULL`| Ledger Reconciliation | Net profit/loss carried forward to reserves |

---

## 11. Zero-Local UI Form Schemas & Field Catalog

### Table: `ui_form_schemas` / `ui_form_fields`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| Schema Ref | `ui_form_schemas.schema_ref` | `varchar(24) NOT NULL PK` | Generated UUID | Prefix `SCH-FRM-` |
| Form Key | `ui_form_schemas.form_key` | `varchar(64) NOT NULL UNIQUE`| System Form Identifier | e.g. `lead_create`, `order_create` |
| Form Title | `ui_form_schemas.title` | `varchar(191) NOT NULL` | UI Display Header | Clean descriptive title |
| Entity Type | `ui_form_schemas.entity_type` | `varchar(50) NOT NULL` | Business Domain Model | e.g. `lead`, `party`, `order`, `inventory` |
| Field Ref | `ui_form_fields.field_ref` | `varchar(24) NOT NULL PK` | Generated UUID | Prefix `FLD-` |
| Field Name | `ui_form_fields.field_name` | `varchar(64) NOT NULL` | Payload JSON Key | Alphanumeric + underscore |
| Field Label | `ui_form_fields.label` | `varchar(191) NOT NULL` | Input Field Label | Label displayed above input |
| Component Type | `ui_form_fields.field_type` | `varchar(32) NOT NULL` | UI Renderer Type | `text`,`select`,`phone`,`email`,`currency`,`date`,`textarea` |
| Is Required | `ui_form_fields.is_required` | `tinyint(1) DEFAULT 0` | Mandatory Check | `0` or `1` |
| Validation Rules | `ui_form_fields.validation_rules_json`| `json NULL` | Dynamic Validator | JSON object with `required`,`min`,`max`,`pattern`,`email` |
| Options Source Type| `ui_form_fields.options_source_type`| `enum('NONE','API_ENDPOINT','CATALOG_MASTER','STATIC_JSON')` | Lookup Engine | Source routing for dropdown select options |
| Options Source Key | `ui_form_fields.options_source_key` | `varchar(191) NULL` | Lookup Parameter | URL path, catalog entity name, or inline JSON string |
| Step Number | `ui_form_fields.step_number` | `int(11) DEFAULT 1` | Multi-step Wizard Step | Wizard step index |
| Grid Width | `ui_form_fields.grid_width` | `int(11) DEFAULT 12` | Grid Column Span | `1` to `12` columns in 12-column grid layout |
| Sort Order | `ui_form_fields.sort_order` | `int(11) DEFAULT 0` | Display Order | Sequential rendering index |

---

## 12. Security Audit, Events & Notifications

### Table: `audit_logs` / `security_events` / `notifications`
| Field Label | Database Column | SQL Type & Constraints | DB Master / Dynamic Source | Server Validation & Calculation Rule |
|---|---|---|---|---|
| User Ref | `audit_logs.user_ref` | `varchar(24) NULL FK` | `users.user_ref` | Actor initiating the operation |
| Action | `audit_logs.action` | `varchar(50) NOT NULL` | Action identifier | e.g. `CREATE`, `UPDATE`, `CONFIRM`, `APPROVE` |
| Table Name | `audit_logs.table_name` | `varchar(64) NOT NULL` | Target table | Database entity modified |
| Record Ref | `audit_logs.record_ref` | `varchar(64) NOT NULL` | Target primary key | Primary key identifier |
| Old Values JSON | `audit_logs.old_values` | `json NULL` | Pre-mutation state | Captured before update |
| New Values JSON | `audit_logs.new_values` | `json NULL` | Post-mutation state | Captured after update |
| IP Address | `audit_logs.ip_address` | `varchar(45) NOT NULL` | Client IP | IPv4 or IPv6 string |
| Notification Type | `notifications.type` | `varchar(50) NOT NULL` | System alert category | `ORDER_CONFIRMED`, `LOW_STOCK`, `PAYMENT_RECEIVED` |
| Read Status | `notifications.read_at` | `timestamp NULL` | State tracker | NULL if unread, timestamp when read |
