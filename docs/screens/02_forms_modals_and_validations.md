# Comprehensive UI Forms, Modals & Validation Catalog (Zero-Local Architecture)

In the Pharma CRM & Sales Force Automation platform, **every form, modal, and drawer is strictly driven by the database schema**. There are **zero hardcoded client-side form configurations**.
All forms are queried via `GET /api/v1/ui/forms/{form_key}` and validated on the server via `POST /api/v1/ui/forms/{form_key}/validate`.

---

## 1. Master Catalog of 20 Database-Driven Form Schemas

| # | `form_key` | Title | Entity Type | Wizard / Steps | Target Validation Endpoint |
|---|---|---|---|---|---|
| 01 | `lead_create` | Create New Sales Lead | `lead` | Single-step (Grid 12) | `POST /api/v1/ui/forms/lead_create/validate` |
| 02 | `party_create` | Create Commercial Party | `party` | Single-step (Grid 12) | `POST /api/v1/ui/forms/party_create/validate` |
| 03 | `product_create` | Create Pharmaceutical Product | `product` | Single-step (Grid 12) | `POST /api/v1/ui/forms/product_create/validate` |
| 04 | `order_create` | Create Commercial Sales Order | `order` | Multi-step / Calculation | `POST /api/v1/admin/orders/calculate` & `/ui/forms/order_create/validate` |
| 05 | `dispatch_create` | Create Consignment Dispatch | `dispatch` | Single-step | `POST /api/v1/ui/forms/dispatch_create/validate` |
| 06 | `payment_create` | Record Payment Receipt | `payment` | Single-step | `POST /api/v1/ui/forms/payment_create/validate` |
| 07 | `pdc_create` | Register Post-Dated Cheque | `payment` | Single-step | `POST /api/v1/ui/forms/pdc_create/validate` |
| 08 | `pdc_realize` | Realize Matured Cheque | `payment` | Modal Action | `POST /api/v1/admin/pdcs/{ref}/clear` |
| 09 | `pdc_bounce` | Record Cheque Bounce | `payment` | Modal Action | `POST /api/v1/admin/pdcs/{ref}/bounce` |
| 10 | `dcr_create` | Daily Call Report Header | `dcr` | Single-step | `POST /api/v1/ui/forms/dcr_create/validate` |
| 11 | `dcr_visit` | DCR Field Visit Log | `dcr` | Modal / Drawer | `POST /api/v1/admin/dcr/{ref}/visits` |
| 12 | `onboarding_register` | Distributor Self-Registration | `onboarding` | 4-Step Wizard | `POST /api/v1/ui/forms/onboarding_register/validate` |
| 13 | `inventory_receive` | Receive Inventory Batch (GRN) | `inventory` | Single-step | `POST /api/v1/ui/forms/inventory_receive/validate` |
| 14 | `inventory_adjust` | Physical Stock Adjustment | `inventory` | Modal / Dialog | `POST /api/v1/admin/inventory/adjust` |
| 15 | `followup_create` | Log Scheduled Follow-up | `followup` | Modal / Drawer | `POST /api/v1/ui/forms/followup_create/validate` |
| 16 | `territory_create` | Allocate Territory Lock | `territory` | Single-step | `POST /api/v1/ui/forms/territory_create/validate` |
| 17 | `territory_override` | Administrative Territory Override | `territory` | Modal Dialog | `POST /api/v1/admin/territories/validate` |
| 18 | `scheme_create` | Create Promotional Scheme | `scheme` | Single-step | `POST /api/v1/ui/forms/scheme_create/validate` |
| 19 | `tier_create` | Create Pricing Tier | `pricing` | Modal / Drawer | `POST /api/v1/ui/forms/tier_create/validate` |
| 20 | `category_create` | Create Product Category | `product` | Modal / Drawer | `POST /api/v1/ui/forms/category_create/validate` |

---

## 2. In-Depth Detailed Breakdown of Key Form Schemas & Field Rules

### FORM 01: `lead_create` (Create New Sales Lead)
* **Screen:** SCR-05 (`/leads/create`), SCR-06 (`/leads/:id`)
* **Database Table:** `leads`, `lead_activities`
* **Fields & Dynamic Configuration:**
  | Field Key | Label | Type | Width | Options Source | Validation JSON & Regex |
  |---|---|---|---|---|---|
  | `contact_name` | Contact Person Name | `text` | 6/12 | `NONE` | `{"required":true,"min":3,"max":191}` |
  | `firm_name` | Firm / Clinic Name | `text` | 6/12 | `NONE` | `{"required":true,"min":3,"max":191}` |
  | `mobile` | Mobile Number | `phone` | 6/12 | `NONE` | `{"required":true,"pattern":"^[6-9][0-9]{9}$","message":"Enter valid 10-digit Indian mobile"}` |
  | `email` | Email Address | `email` | 6/12 | `NONE` | `{"email":true}` |
  | `lead_source` | Lead Source | `select` | 6/12 | `CATALOG_MASTER: LEAD_SOURCE` | `{"required":true}` |
  | `business_type`| Business / Specialty Type | `select` | 6/12 | `CATALOG_MASTER: PARTY_TYPE` | Optional |
  | `state_ref` | State | `select` | 6/12 | `API_ENDPOINT: /api/v1/geo/states` | `{"required":true}` |
  | `district_ref` | District | `select` | 6/12 | `API_ENDPOINT: /api/v1/geo/districts` | `{"required":true}` |
  | `pincode` | Pincode | `text` | 6/12 | `NONE` | `{"required":true,"pattern":"^[1-9][0-9]{5}$","message":"Enter valid 6-digit Indian PIN"}` |
  | `priority` | Lead Priority | `select` | 6/12 | `STATIC_JSON: [LOW, NORMAL, HIGH, URGENT]` | `{"required":true}` |
  | `initial_remark`| Initial Discussion Notes | `textarea`| 12/12| `NONE` | Optional, max 1000 chars |

---

### FORM 02: `party_create` (Create Commercial Party / Stockist)
* **Screen:** SCR-09 (`/parties/create`), SCR-10 (`/parties/:id`)
* **Database Table:** `parties`, `party_territories`
* **Fields & Dynamic Configuration:**
  | Field Key | Label | Type | Width | Options Source | Validation JSON & Rules |
  |---|---|---|---|---|---|
  | `firm_name` | Authorized Firm Name | `text` | 6/12 | `NONE` | `{"required":true,"min":3,"max":191}` |
  | `contact_name` | Proprietor / Contact Name | `text` | 6/12 | `NONE` | `{"required":true,"min":3,"max":191}` |
  | `party_type` | Party Commercial Category | `select` | 6/12 | `CATALOG_MASTER: PARTY_TYPE` | `{"required":true}` |
  | `gstin` | GSTIN (Tax ID) | `text` | 6/12 | `NONE` | `{"required":true,"pattern":"^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$"}` |
  | `drug_license_no` | Drug Licence Number | `text` | 6/12 | `NONE` | `{"required":true,"min":5}` |
  | `drug_license_validity`| Drug Licence Validity Date| `date` | 6/12 | `NONE` | `{"required":true}` (Must be future date) |
  | `mobile` | Official Mobile Number | `phone` | 6/12 | `NONE` | `{"required":true,"pattern":"^[6-9][0-9]{9}$"}` |
  | `tier_ref` | Assigned Pricing Tier | `select` | 6/12 | `API_ENDPOINT: /api/v1/admin/tiers` | `{"required":true}` |
  | `credit_limit` | Credit Limit (₹) | `currency` | 6/12 | `NONE` | `{"required":true,"min":0}` |
  | `payment_terms_days` | Payment Terms (Days) | `number` | 6/12 | `NONE` | `{"required":true,"min":0,"max":180}` |
  | `billing_address` | Registered Billing Address | `textarea`| 12/12| `NONE` | `{"required":true}` |

---

### FORM 03: `product_create` (Create Pharmaceutical Product SKU)
* **Screen:** SCR-13 (`/products/create`), SCR-14 (`/products/:id`)
* **Database Table:** `products`
* **Fields & Dynamic Configuration:**
  | Field Key | Label | Type | Width | Options Source | Validation JSON & Rules |
  |---|---|---|---|---|---|
  | `product_name` | Brand / Product Name | `text` | 6/12 | `NONE` | `{"required":true,"min":2}` |
  | `generic_name` | Generic / Salt Composition | `text` | 6/12 | `NONE` | `{"required":true}` |
  | `category_ref` | Product Category | `select` | 6/12 | `API_ENDPOINT: /api/v1/admin/categories` | `{"required":true}` |
  | `dosage_form` | Dosage Form | `select` | 6/12 | `CATALOG_MASTER: DOSAGE_FORM` | `{"required":true}` |
  | `packing` | Packaging Specification | `text` | 6/12 | `NONE` | `{"required":true}` (e.g. "10x10 Tablets") |
  | `hsn_code` | HSN Code | `text` | 6/12 | `NONE` | `{"required":true,"pattern":"^[0-9]{4,8}$"}` |
  | `mrp` | Maximum Retail Price (₹ MRP) | `currency`| 4/12 | `NONE` | `{"required":true,"min":0.01}` |
  | `pts` | Price to Stockist (₹ PTS) | `currency`| 4/12 | `NONE` | `{"required":true,"min":0.01}` (pts <= mrp) |
  | `ptr` | Price to Retailer (₹ PTR) | `currency`| 4/12 | `NONE` | `{"required":true,"min":0.01}` |
  | `gst_rate` | Statutory GST Slab (%) | `select` | 6/12 | `STATIC_JSON: [0, 5, 12, 18, 28]` | `{"required":true}` |

---

### FORM 04: `order_create` & Server Calculation (`/admin/orders/calculate`)
* **Screen:** SCR-17 (`/orders/create`), SCR-18 (`/orders/:id`)
* **Database Tables:** `orders`, `order_items`, `stock_reservations`
* **Header Fields:**
  * `party_ref`: Async select from `GET /api/v1/admin/parties`
  * `client_order_ref`: PO reference string
  * `channel`: `ADMIN`, `SALES`, or `PORTAL`
  * `shipping_pincode`: Destination PIN code (validated against territory exclusivity)
  * `shipping_address`: Consignment delivery address
* **Line Items & Server Calculation:**
  * Client sends array: `[{ "product_ref": "...", "quantity": 100 }]`
  * Server computes:
    1. **PriceResolver:** Resolves party custom rate -> tier rate -> base PTS.
    2. **SchemeCalculator:** Resolves free goods based on active slabs (e.g. 10+1 free).
    3. **GstCalculator:** Evaluates place of supply vs franchise origin state. Intrastate splits into CGST (50%) + SGST (50%); Interstate applies IGST (100%).
    4. **CreditRuleService:** Checks party's `credit_limit`, `current_outstanding`, and projected balance. Returns `ALLOW` or `HOLD` decision.

---

### FORM 05: `dispatch_create` (Consignment Dispatch & Logistics)
* **Screen:** SCR-22 (`/dispatch/create/:orderId`)
* **Database Table:** `dispatches`, `transporters`
* **Fields & Validations:**
  * `order_ref`: Confirmed order reference
  * `transporter_ref`: Registered logistics partner (`GET /api/v1/admin/transporters`)
  * `lr_number`: Lorry Receipt / Airway bill number (`min: 3, max: 64`)
  * `lr_date`: Handover date (`date <= today`)
  * `boxes_count`: Number of cartons/cases (`integer >= 1`)
  * `weight_kg`: Total consignment weight (`decimal >= 0.00`)

---

### FORM 06: `payment_create` (Bank Collection & Ledger Receipt)
* **Screen:** SCR-28 (`/payments/create`), SCR-29 (`/payments/:id/allocate`)
* **Database Tables:** `payments`, `payment_allocations`
* **Fields & Validations:**
  * `party_ref`: Commercial party (`GET /api/v1/admin/parties`)
  * `payment_date`: Receipt date (`date <= today`)
  * `amount`: Collected amount (`min: 1.00`)
  * `mode`: Mode enum: `NEFT`, `RTGS`, `IMPS`, `UPI`, `CHEQUE`, `CASH`, `PDC`
  * `reference_no`: Bank UTR or Cheque number (validated against double submission)
  * `remarks`: Collection notes

---

### FORM 07: `pdc_create` (Post-Dated Cheque Registration)
* **Screen:** SCR-30 (`/pdcs/create`)
* **Database Table:** `pdcs`
* **Fields & Validations:**
  * `party_ref`: Drawing party
  * `cheque_number`: 6-digit cheque instrument number
  * `cheque_date`: Future maturity date (`date >= today`)
  * `amount`: Cheque amount (`min: 1.00`)
  * `bank_name`: Drawee bank name
  * `branch_name`: Drawee bank branch

---

### FORM 10 & 11: `dcr_create` & `dcr_visit` (Field SFA Daily Call Report)
* **Screen:** SCR-34 (`/dcr/create`), SCR-35 (`/dcr/:id/visit`)
* **Database Tables:** `daily_call_reports`, `dcr_doctor_visits`, `dcr_chemist_visits`
* **Fields & Validations:**
  * `report_date`: Work date (defaults to today)
  * `work_type`: `FIELD`, `OFFICE`, `TRANSIT`, `LEAVE`
  * `route_ref`: Assigned tour route
  * `visits`: Doctor/chemist calls with GPS geolocation coordinates, products detailed, and sample distributions.

---

### FORM 12: `onboarding_register` (4-Step KYC Onboarding Wizard)
* **Screen:** SCR-37 (`/onboarding`)
* **Wizard Steps:**
  1. **Step 1: Firm Demographics & Contact** (`firm_name`, `contact_person`, `mobile`, `email`, `state_ref`, `district_ref`, `pincode`)
  2. **Step 2: Statutory & Tax Compliance** (`gstin`, `pan_number`, `dl_number_1`, `dl_number_2`, `dl_expiry_date`)
  3. **Step 3: Territory & Commercial Selection** (`requested_districts`, `requested_categories`, `annual_turnover`)
  4. **Step 4: Bank Account & Verification** (`bank_name`, `account_number`, `ifsc_code`, `cancelled_cheque_upload`)
* **Dynamic Validation:** `POST /api/v1/ui/forms/onboarding_register/validate` supports filtering by `step` parameter (`1`, `2`, `3`, or `4`), verifying each step independently before wizard advancement.

---

## 3. Server-Side Dynamic Form Validator Architecture

The validator engine `App\Domain\Masters\DynamicFormValidator` dynamically pulls constraints from `ui_form_fields` where `form_key = :key AND is_active = 1`.
Supported rule assertions:
* `required`: Verifies presence and non-empty string/number.
* `min`: String minimum character length or numeric minimum value.
* `max`: String maximum character length or numeric maximum value.
* `pattern`: Standard PCRE regular expression (e.g. mobile `^[6-9][0-9]{9}$`, PIN `^[1-9][0-9]{5}$`, GSTIN `^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$`).
* `email`: RFC-compliant email syntax check.
* `url`: URL syntax check.
* `in`: Array membership validation.
* `message`: Custom localized error message override.
* `step`: Multi-step form filtering by `step_number`.
