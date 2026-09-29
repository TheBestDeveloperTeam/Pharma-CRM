# Comprehensive Screens & UI View Specifications

This document catalogs every individual screen, view, sub-view, tab, drawer, and modal across both the **CRM Backoffice (Admin/Sales)** and the **Distributor Self-Service Portal**, specifying their state machines, zero-local-data sources, and interactions.

---

## 1. CRM Backoffice Screens Directory

### 1.1 Auth & Access
* **SCR-01: Admin / Sales Login (`/login`)**
  * **View Type:** Full Screen Authenticator
  * **Zero-Local Source:** Auth POST `/api/v1/oauth/token`. JWT token contains decoded realm, user role, franchise ref, and permissions.
  * **States:** Idle, Validating, Authenticated, Invalid Credentials Error (401), Account Locked (423).
  * **Forms/Modals:** Login Form, Forgot Password Modal (`/api/v1/auth/forgot-password`).

* **SCR-02: Access Denied (`/access-denied`)**
  * **View Type:** Error & Redirection Page
  * **Zero-Local Source:** Triggered when user attempts to access a route for which their role lacks explicit capability in `auth_role_permissions`.

### 1.2 Dashboard & Overview
* **SCR-03: Executive Dashboard (`/`)**
  * **View Type:** Multi-Widget Analytics Grid
  * **Zero-Local Source:** GET `/api/v1/admin/reports/dashboard-stats` (Real-time DB counts: Total Active Leads, Orders in Processing, Uncollected Outstanding, Near-Expiry Value).
  * **Components:** 12 Recharts KPI widgets, Recent Activity Feed, Lead SLA Warning banner.

### 1.3 Leads & CRM Funnel
* **SCR-04: Leads Index (`/leads`)**
  * **View Type:** Paginated Master Table with Filter Bar
  * **Zero-Local Source:** GET `/api/v1/admin/leads?page=1&status=...&assigned_user_ref=...`. Dropdowns populated from `catalog_master_values` (`lead_source`, `lead_status`).
  * **Actions/Modals:**
    * Add Remark Modal (`POST /api/v1/admin/leads/{id}/remarks`)
    * Assign Sales Rep Modal (`PATCH /api/v1/admin/leads/{id}/assign`)
    * Convert to Party Drawer (`POST /api/v1/admin/leads/{id}/convert`)
    * Duplicate Lead Alert Dialog (`GET /api/v1/admin/leads/check-duplicate?mobile=...`)

* **SCR-05: Lead Create / Edit (`/leads/create`, `/leads/:id/edit`)**
  * **View Type:** Multi-Section Form (`FormSection`)
  * **Zero-Local Source:** Dynamic States from `/api/v1/geo/states`, Districts from `/api/v1/geo/districts?state_ref=...`, Sources from `/api/v1/admin/masters/catalog-values?entity=lead_source`.

* **SCR-06: Lead 360 Details (`/leads/:id`)**
  * **View Type:** Tabbed Record View
  * **Tabs:** Overview, Activity History, Follow-up Timeline, Territory Coverage Check.

* **SCR-07: Follow-ups Management (`/follow-ups`)**
  * **View Type:** Tabbed Calendar & Agenda (Today, Upcoming, Missed, Completed)
  * **Zero-Local Source:** GET `/api/v1/admin/follow-ups?bucket=today`.
  * **Modals:** Log Call Outcome Modal, Reschedule Modal.

### 1.4 Parties & Onboarding
* **SCR-08: Parties Master Table (`/parties`)**
  * **View Type:** Data Table with Status Badges (Active, Suspended, Archived)
  * **Zero-Local Source:** GET `/api/v1/admin/parties`.
  * **Modals:** Quick Territory Lock Modal, Credit Limit Override Modal.

* **SCR-09: Party Create / Edit (`/parties/create`, `/parties/:id/edit`)**
  * **View Type:** Comprehensive Legal & Commercial Form
  * **Sections:** Basic Firm Details, Statutory (GST/DL/PAN), Pricing Tier Selector, Territory Allocation (Districts/Pincodes), Credit Terms.

* **SCR-10: Party 360 Details (`/parties/:id`)**
  * **View Type:** Full Profile & Financial Ledger
  * **Tabs:** Profile & Documents, Orders History, Invoices & Ageing, Territory Lock Map, Payment Ledger.

* **SCR-11: Distributor Onboarding & KYC (`/distributor-onboarding`)**
  * **View Type:** Onboarding Applications Review
  * **Zero-Local Source:** GET `/api/v1/admin/parties/onboarding/registrations`.
  * **Modals:** Document Viewer Modal, KYC Approval/Rejection Dialog.

### 1.5 Products, Pricing & Schemes
* **SCR-12: Products Catalogue (`/products`)**
  * **View Type:** Product Grid & Table with Stock Badges
  * **Zero-Local Source:** GET `/api/v1/admin/products`. Categories from `/api/v1/admin/masters/categories`.

* **SCR-13: Product Create / Edit (`/products/create`, `/products/:id/edit`)**
  * **View Type:** Pharma SKU Spec Form
  * **Sections:** Identification (Name/SKU), Therapeutic Category, Composition & Strength, Packaging Unit, HSN & GST, Base Commercial (MRP/PTS).

* **SCR-14: Pricing Matrix Manager (`/pricing`)**
  * **View Type:** Matrix Grid by Tier and Party Override
  * **Zero-Local Source:** GET `/api/v1/admin/pricing-tiers` and GET `/api/v1/admin/products/pricing-matrix`.

* **SCR-15: Schemes & Free Goods (`/schemes`, `/schemes/create`, `/schemes/:id/edit`)**
  * **View Type:** Promotional Rule Builder
  * **Sections:** Scheme Validity Window, Applicable Product(s), Tier Restriction, Volume Breakpoints (`min_order_qty` -> `free_qty`).

### 1.6 Orders, Billing & Dispatches
* **SCR-16: Orders Index (`/orders`)**
  * **View Type:** Orders Processing Pipeline Table
  * **Filter Buckets:** Draft, Submitted, Confirmed, Processing, Dispatched, Cancelled.

* **SCR-17: Order Create / Edit (`/orders/create`, `/orders/:id/edit`)**
  * **View Type:** Dynamic Multi-Line Commercial Form
  * **Components:**
    * Party Selector with Real-time Credit Limit & DL Expiry status badge.
    * Territory Exclusivity Validator alert banner.
    * Live Dynamic Line Item Grid: calculates rate, applies best scheme, displays GST split via server endpoints `/pricing/calculate` and `/schemes/calculate`.

* **SCR-18: Order Details & Stock Reservation (`/orders/:id`)**
  * **View Type:** Order Fulfillment Console
  * **Actions/Modals:**
    * Confirm & Reserve Stock Modal (FEFO preview: batch no, available vs reserved qty).
    * Generate Tax Invoice Action.
    * Cancel Order Dialog (with mandatory reason log).

* **SCR-19: Invoices Master (`/invoices`)**
  * **View Type:** Tax Invoices Register & Ageing Table
  * **Zero-Local Source:** GET `/api/v1/admin/invoices`.

* **SCR-20: Tax Invoice Details & PDF (`/invoices/:id`)**
  * **View Type:** Printable Legal Tax Invoice (B2B compliant with HSN summary, CGST/SGST/IGST breakdown, Bank NEFT instructions).

* **SCR-21: Dispatch & Logistics Queue (`/dispatch`)**
  * **View Type:** Pending Fulfillment Register
  * **Zero-Local Source:** Orders in `CONFIRMED` or `PROCESSING` state lacking active dispatch.

* **SCR-22: Create Dispatch (`/dispatch/create/:orderId`)**
  * **View Type:** Transporter & Consignment Note Entry Form
  * **Fields:** Transporter Selection, Lorry Receipt (LR) Number, LR Date, Package Count, Physical Weight.

### 1.7 Inventory, Batches & Expiry
* **SCR-23: Inventory Batches (`/inventory`)**
  * **View Type:** FEFO Warehouse Table
  * **Columns:** SKU, Batch No, MFG Date, Expiry Date, Received Qty, Reserved Qty, Available Qty, Batch Status.

* **SCR-24: Batch Goods Receipt (GRN) (`/inventory/create`)**
  * **View Type:** Physical Inward Entry Form
  * **Zero-Local Source:** Submits to `POST /api/v1/admin/inventory/batches`.

* **SCR-25: Inventory Movement Ledger (`/inventory-ledger`)**
  * **View Type:** Immutable Stock Audit Trail (Inward, Reserved, Dispatched, Adjusted).

* **SCR-26: Near-Expiry Management (`/near-expiry`)**
  * **View Type:** Risk Assessment Table
  * **Thresholds:** < 90 Days (Critical Red), < 180 Days (Amber), Expired (Black).

### 1.8 Payments & Financial Ledger
* **SCR-27: Payments Register (`/payments`)**
  * **View Type:** Receipts Table
  * **Zero-Local Source:** GET `/api/v1/admin/payments`.

* **SCR-28: Record Payment (`/payments/create`)**
  * **View Type:** Bank Receipt Entry Form
  * **Fields:** Party Selector, Payment Date, Payment Mode (NEFT/RTGS/Cheque/UPI), UTR/Ref No, Cheque Date, Bank Name, Total Amount.

* **SCR-29: Invoice Payment Allocation (`/payments/:id/allocate`)**
  * **View Type:** Interactive Unpaid Invoices Allocation Matrix
  * **Features:** Auto FIFO Allocate Button, Manual Partial Allocation Inputs, Real-time Unallocated Balance Indicator.

* **SCR-30: Outstanding & Ledger (`/outstanding`)**
  * **View Type:** Party Financial Aging Grid (0-30 days, 31-60 days, 61-90 days, 90+ days).

### 1.9 Masters, Roles & Settings
* **SCR-31: Master Data Management (`/masters`)**
  * **View Type:** Unified Tabbed Configuration View
  * **Tabs:** Product Categories, Transporters, Catalog Values (Lead Sources, Payment Modes, Units), Notification Templates.

* **SCR-32: Role & Permission Matrix (`/roles`, `/roles/create`, `/roles/:id/edit`)**
  * **View Type:** Fine-grained Module-to-Action Permission Grid (View, Create, Edit, Delete, Approve, Export).

* **SCR-33: Internal Users Management (`/internal-users`)**
  * **View Type:** Employee & Sales Rep Directory with Reporting Hierarchy.

* **SCR-34: System Settings (`/settings`)**
  * **View Type:** Franchise & Platform Global Settings (FEFO rules, credit policy, branding).

* **SCR-35: Audit Logs (`/audit-logs`)**
  * **View Type:** Security & Mutation Audit Trail.

---

## 2. Distributor Self-Service Portal Screens Directory

* **SCR-P01: Portal Login (`/distributor/login`)**
  * **Zero-Local Source:** Authenticates against `users` table where role is `DISTRIBUTOR`, linked to `parties` record.

* **SCR-P02: Distributor Portal Dashboard (`/distributor`)**
  * **Widgets:** Active Credit Limit vs Utilized Outstanding, Orders in Transit, Recent Scheme Announcements.

* **SCR-P03: Self-Service Product Catalogue (`/distributor/catalogue`)**
  * **Features:** Live stock availability indicator, personalized net rate (based on assigned tier/custom price), Active Scheme Badges.

* **SCR-P04: Portal Dynamic Cart (`/distributor/cart`)**
  * **Features:** Live server calculation of GST and free goods as quantities change.

* **SCR-P05: My Orders & Tracking (`/distributor/orders`, `/distributor/orders/:id`)**
  * **Features:** Live status timeline (Submitted -> Confirmed -> Dispatched with Transporter LR -> Delivered).

* **SCR-P06: My Invoices & Ledger (`/distributor/outstanding`, `/distributor/invoices/:id`)**
  * **Features:** Printable tax invoice download, account statement ledger.

* **SCR-P07: Daily Call Report (DCR) Portal (`/distributor/dcr`)**
  * **Sub-Screens:** Field Customers (`/distributor/field-customers`), Tour Plan (`/distributor/tour-plan`), Daily Visit Entry (`/distributor/dcr/new`), Pending Approvals (`/distributor/dcr-pending`).
