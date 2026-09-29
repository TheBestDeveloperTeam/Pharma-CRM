# Pharma CRM & Sales Force Automation — Unified Master Documentation

**Version:** 3.0 | **Generated:** 2026-09-29 | **Status:** Production-Ready Reference

> This document is the **single source of truth** aligning the Functional Requirement Specification (FRS v2.3), the complete database schema (70 tables), all REST API endpoints (231 routes), backend PHP architecture, and every business workflow. It supersedes all fragmented documents across `docs/workflows/`, `docs/screens/`, `docs/apis/`, and `docs/input-fields-ER/`.

---

## Table of Contents

| # | Section | Coverage |
|---|---------|----------|
| 1 | [Project Overview & Architecture](#1-project-overview--architecture) | Objective, scope, tech stack, multi-tenancy |
| 2 | [Actors, Roles & Permission System](#2-actors-roles--permission-system) | FRS §3, §25, §44, §45; DB auth_* tables; RBAC APIs |
| 3 | [Database Schema — Complete 70-Table Inventory](#3-database-schema--complete-70-table-inventory) | Every table, columns, constraints, relationships |
| 4 | [Module 01: Authentication & OAuth](#4-module-01-authentication--oauth) | FRS §27; DB: users, sessions, tokens; APIs |
| 5 | [Module 02: Super Admin — Platform Management](#5-module-02-super-admin--platform-management) | Organizations, franchises, impersonation |
| 6 | [Module 03: Internal User & Hierarchy Management](#6-module-03-internal-user--hierarchy-management) | FRS §21, §44.7; DB: users, auth_user_roles |
| 7 | [Module 04: Role & Permission Management](#7-module-04-role--permission-management) | FRS §44; DB: auth_roles, auth_permissions |
| 8 | [Module 05: Geographic Masters](#8-module-05-geographic-masters) | States, districts, cities, pincodes |
| 9 | [Module 06: Catalog Masters](#9-module-06-catalog-masters) | FRS §24; DB: catalog_master_values |
| 10 | [Module 07: Product Management](#10-module-07-product-management) | FRS §11; DB: products, product_categories |
| 11 | [Module 08: Pricing Matrix & Scheme Engine](#11-module-08-pricing-matrix--scheme-engine) | FRS §12; DB: pricing_tiers, product_prices, schemes |
| 12 | [Module 09: Lead Management](#12-module-09-lead-management) | FRS §6, §7, §8; DB: leads, follow_ups, lead_activities |
| 13 | [Module 10: Party/Franchise Partner Management](#13-module-10-partyfranchise-partner-management) | FRS §9; DB: parties, party_territories |
| 14 | [Module 11: Territory & Geo-Locking](#14-module-11-territory--geo-locking) | FRS §10; DB: party_territories, territory_overrides |
| 15 | [Module 12: Distributor Onboarding](#15-module-12-distributor-onboarding) | FRS §38; DB: onboarding_invites, onboarding_registrations |
| 16 | [Module 13: Inventory & Batch Management (FEFO)](#16-module-13-inventory--batch-management-fefo) | FRS §14, §15; DB: inventory_batches, stock_reservations |
| 17 | [Module 14: Order Management](#17-module-14-order-management) | FRS §13; DB: orders, order_items |
| 18 | [Module 15: Billing & Invoicing](#18-module-15-billing--invoicing) | FRS §16; DB: invoices, invoice_items |
| 19 | [Module 16: Dispatch & Tracking](#19-module-16-dispatch--tracking) | FRS §17; DB: dispatches, transporters |
| 20 | [Module 17: Payments & Outstanding](#20-module-17-payments--outstanding) | FRS §20; DB: payments, payment_allocations, pdcs |
| 21 | [Module 18: Distributor Portal](#21-module-18-distributor-portal) | FRS §18, §39; Portal APIs |
| 22 | [Module 19: DCR — Daily Call Report](#22-module-19-dcr--daily-call-report) | FRS §40; DB: dcr_reports_v2, dcr_visits_v2 |
| 23 | [Module 20: Webhooks & B2B Lead Ingestion](#23-module-20-webhooks--b2b-lead-ingestion) | FRS §8; DB: webhook_sources, webhook_events |
| 24 | [Module 21: Notifications & WhatsApp](#24-module-21-notifications--whatsapp) | FRS §19; DB: notifications, notification_templates |
| 25 | [Module 22: Dashboard & Reports](#25-module-22-dashboard--reports) | FRS §22, §23; Report APIs |
| 26 | [Module 23: Audit Logs](#26-module-23-audit-logs) | FRS §27; DB: audit_logs |
| 27 | [Module 24: System Infrastructure](#27-module-24-system-infrastructure) | Job queue, idempotency, sequence counters, settings |
| 28 | [Complete API Route Registry](#28-complete-api-route-registry) | All 160+ registered routes |
| 29 | [Backend PHP Architecture](#29-backend-php-architecture) | Controllers, Services, Repositories, Domain |
| 30 | [Cross-Module Workflow Diagrams](#30-cross-module-workflow-diagrams) | E2E business flows |
| 31 | [Acceptance Criteria Master List](#31-acceptance-criteria-master-list) | All AC from FRS |
| 32 | [Open Business Decisions](#32-open-business-decisions) | D-1 through D-25 |
| 33 | [Glossary & Conventions](#33-glossary--conventions) | Naming, ref patterns, enums |

---

## 1. Project Overview & Architecture

### 1.1 Objective (FRS §1)
Build a B2B pharmaceutical CRM for PCD/Franchise business covering lead acquisition, sales follow-up, party onboarding, territory control, products, pricing and schemes, orders, batch inventory (FEFO), billing, dispatch, payments, distributor self-service portal, WhatsApp notifications, webhook lead ingestion, DCR (Daily Call Reports), configurable roles/permissions, and reporting.

### 1.2 Technology Stack

| Layer | Technology | Details |
|-------|-----------|---------|
| **Backend** | Core PHP (no framework) | Custom MVC with Router, Controllers, Domain Services, Repositories |
| **Database** | MariaDB / MySQL 8+ | 70 tables, InnoDB, utf8mb4, STRICT mode, row-level tenancy |
| **Auth** | OAuth 2.0 style JWT | Bearer access tokens (15-min), rotating refresh tokens with reuse detection |
| **API** | RESTful JSON over HTTPS | OpenAPI 3.0.3 spec, envelope pattern {success, data, meta} |
| **Frontend** | React (TypeScript) | Separate repo — NOT in scope for backend docs |
| **Hosting** | cPanel Shared + Dev | https://crm.easysolutins24.in/api/v1 |

### 1.3 Multi-Tenancy Architecture

```
Platform
  └── Organization (org_ref)        → organizations table
       └── Franchise (franchise_ref)  → franchises table
            ├── Internal Users (Admin, Sales, Custom Roles)
            ├── Distributor Users (Portal)
            └── All Business Data (leads, parties, orders, etc.)
```

Every business table carries `org_ref` + `franchise_ref` columns. Row-level tenant isolation is enforced at the repository layer.

### 1.4 Naming Conventions

| Pattern | Example | Rule |
|---------|---------|------|
| Ref IDs | USR-SUPERADMIN0000001 | 3-char prefix + hyphen + 20-char identifier |
| Table names | snake_case plural | inventory_batches, order_items |
| API paths | /api/v1/{domain}/{resource} | RESTful with kebab-case |
| Enums | UPPER_SNAKE_CASE | DRAFT, SUBMITTED, CONFIRMED |

---

## 2. Actors, Roles & Permission System

### 2.1 Actor Model (FRS §3 + §44 + §45.1)

| Actor | Type | Database |
|-------|------|----------|
| Super Admin | Platform operator | users.role = 'SUPER_ADMIN', franchise_ref IS NULL |
| Franchise Admin | Internal system role | users.role = 'FRANCHISE_ADMIN' + auth_roles |
| Custom Internal Roles | Admin-created | auth_roles + auth_role_permissions + auth_role_scopes |
| Sales Team | Internal role (template) | Via auth_roles with default_scope = 'OWN' |
| Distributor Owner | External portal actor | users.role = 'DISTRIBUTOR' + party_ref link |
| Distributor Team User | External portal actor | Created by Distributor Owner |
| B2B Lead Source | External system | webhook_sources + HMAC auth |

### 2.2 Configurable RBAC Database Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| auth_roles | Role definitions | role_ref, role_name, role_slug, is_system, default_scope |
| auth_permissions | Permission catalogue | permission_ref, module_key, action_key, permission_key (generated) |
| auth_role_permissions | M:N role-permission grants | role_ref, permission_ref, granted_by_ref |
| auth_role_scopes | Per-module scope overrides | role_ref, module_key, data_scope |
| auth_user_roles | M:N user-role assignments | user_ref, role_ref, assigned_by_ref |
| auth_user_hierarchy | Reporting chain (Team scope) | user_ref, manager_ref |
| auth_user_territories | Territory-based data scope | user_ref, territory_ref |
| auth_permission_catalogue | Seedable master list | module_key, action_key, label, is_sensitive |

### 2.3 Permission Catalogue (FRS §44.3) — 22 Modules

| Module Key | Grantable Actions |
|-----------|-------------------|
| dashboard | view |
| leads | view, create, edit, archive, assign, convert, export |
| followUps | view, create, edit, complete, reschedule, export |
| parties | view, create, edit, archive, activateDeactivate, export |
| territory | view, allocate, edit, override, export |
| products | view, create, edit, archive, activateDeactivate, export |
| pricing | view, create, edit, priceOverride, export |
| schemes | view, create, edit, deactivate |
| orders | view, create, editDraft, submit, confirm, cancel, deleteDraft, print, export |
| inventory | view, create, edit, adjust, transfer, manualBatchOverride, reserve, release, export |
| nearExpiry | view, configure, export |
| billing | view, create, cancel, print, export, outstanding |
| dispatch | view, create, edit, updateTracking, export |
| payments | view, create, edit, delete, allocate, archive, cancel, reverse, pdc, export |
| distributorOnboarding | view, create, edit, generateInvite, resendRevokeInvite, review, approve, reject, convert |
| kyc | view, upload, verify, reject |
| dcr | view, create, edit, submit, approve, reject |
| internalUsers | view, create, edit, activateDeactivate, resetPassword |
| rolesAndPermissions | view, create, edit, delete, assignToUser |
| masters | view, create, edit, activateDeactivate |
| reports | view, export, print |
| auditLogs | view, export |
| notifications | view, sendRetry, manageTemplates |
| webhooks | view, configure, retryFailed |

### 2.4 Data Scope Model (FRS §44.4)

| Scope | Enum | Database Filter |
|-------|------|----------------|
| All | ALL | No ownership filter |
| Territory | TERRITORY | Records in user's assigned territories |
| Team | TEAM | User's own + direct reports |
| Own | OWN | Only records user created or is assigned to |
| None | NONE | No access to module |

### 2.5 RBAC API Endpoints

| Method | Route | Purpose |
|--------|-------|---------|
| GET | /api/v1/admin/roles | List all roles |
| POST | /api/v1/admin/roles | Create role |
| GET | /api/v1/admin/roles/{ref} | Show role detail |
| PATCH | /api/v1/admin/roles/{ref} | Update role |
| POST | /api/v1/admin/roles/{ref}/clone | Clone role |
| DELETE | /api/v1/admin/roles/{ref} | Delete role |
| GET | /api/v1/admin/permissions | Permission catalogue |
| POST | /api/v1/admin/users/{ref}/roles | Assign role to user |
| DELETE | /api/v1/admin/users/{ref}/roles/{role_ref} | Revoke role |

---

## 3. Database Schema — Complete 70-Table Inventory

### 3.1 Table Inventory by Domain

| # | Table Name | FRS § | Key Columns |
|---|-----------|-------|-------------|
| 1 | organizations | §1 | org_ref, org_code, org_name, status |
| 2 | franchises | §1 | franchise_ref, org_ref, franchise_code, gstin |
| 3 | users | §3,§44.7 | user_ref, org_ref, franchise_ref, role, party_ref, email, employee_code, reporting_manager_ref |
| 4 | oauth_clients | §27 | client_id, surface, allowed_roles |
| 5 | user_sessions | §27 | session_ref, family_ref, user_ref, ip_address |
| 6 | oauth_refresh_tokens | §27 | token_hash, session_ref, status |
| 7 | login_attempts | §27 | email, tenant_key, ip_address, outcome |
| 8 | password_resets | §27 | token_hash, user_ref, expires_at |
| 9 | role_permissions | §25 | role, permission_code, scope |
| 10 | rate_limits | §27 | bucket_key, window_start, hits |
| 11 | auth_roles | §44 | role_ref, role_name, is_system, default_scope |
| 12 | auth_permissions | §44.3 | permission_ref, module_key, action_key |
| 13 | auth_role_permissions | §44 | role_ref, permission_ref |
| 14 | auth_role_scopes | §44.4 | role_ref, module_key, data_scope |
| 15 | auth_user_roles | §44.7 | user_ref, role_ref |
| 16 | auth_user_hierarchy | §44.7 | user_ref, manager_ref |
| 17 | auth_user_territories | §44.4 | user_ref, territory_ref |
| 18 | auth_permission_catalogue | §44.3 | module_key, action_key, is_sensitive |
| 19 | states | §24 | state_ref, state_code, state_name |
| 20 | districts | §24 | district_ref, state_ref, district_name |
| 21 | cities | §24 | city_ref, district_ref, city_name |
| 22 | pincodes | §24 | pincode, city_ref, district_ref, state_ref |
| 23 | sequence_counters | §27 | franchise_ref, counter_key, period_key, last_value |
| 24 | api_idempotency_keys | §27 | idempotency_key, method, path, request_hash |
| 25 | system_settings | §24 | franchise_ref, setting_key, setting_value |
| 26 | catalog_master_values | §24 | master_ref, master_key, name, status |
| 27 | product_categories | §24 | category_ref, category_name |
| 28 | products | §11 | product_ref, sku, product_name, mrp, pts, franchise_rate, gst_percent, hsn_code |
| 29 | pricing_tiers | §12 | tier_ref, tier_name |
| 30 | product_prices | §12 | price_ref, product_ref, tier_ref, party_ref, rate, effective_from, priority |
| 31 | schemes | §12 | scheme_ref, scheme_name, start_date, end_date, stacking_allowed |
| 32 | scheme_rules | §12 | rule_ref, scheme_ref, product_ref, min_qty, max_qty, free_qty |
| 33 | parties | §9 | party_ref, firm_name, gstin, tier_ref, credit_limit, sales_user_ref |
| 34 | party_territories | §10 | territory_ref, party_ref, level, pincode, district_ref, effective_from |
| 35 | party_product_interests | §9 | party_ref, product_ref |
| 36 | territory_overrides | §10 | override_ref, order_ref, pincode, reason, approved_by_ref |
| 37 | onboarding_invites | §38 | invite_ref, lead_ref, token_hash, expires_at |
| 38 | onboarding_registrations | §38 | onboarding_ref, invite_ref, status, firm_name, gstin, pan |
| 39 | onboarding_history | §38 | onboarding_ref, from_status, to_status, actor_ref |
| 40 | kyc_documents | §38.6 | kyc_document_ref, document_type, file_reference, status |
| 41 | kyc_document_history | §38 | kyc_document_ref, from_status, to_status |
| 42 | dcr_reports_v2 | §40 | dcr_ref, distributor_party_ref, owner_user_ref, report_date, work_type, status |
| 43 | dcr_visits_v2 | §40.4 | visit_ref, dcr_ref, customer_type, visit_purpose, pob_product_ref |
| 44 | dcr_history | §40 | dcr_ref, from_status, to_status, actor_ref |
| 45 | leads | §6 | lead_ref, contact_name, mobile, status(11-state), priority, external_lead_id |
| 46 | follow_ups | §7 | followup_ref, lead_ref, party_ref, activity_type, status |
| 47 | lead_activities | §6.2 | activity_ref, lead_ref, activity_type, from_status, to_status |
| 48 | inventory_batches | §14 | batch_ref, product_ref, batch_no, expiry_date, on_hand_qty, reserved_qty, status, version |
| 49 | inventory_movements | §14 | movement_ref, batch_ref, movement_type(9 types), qty |
| 50 | stock_reservations | §14 | reservation_ref, batch_ref, order_ref, reserved_qty, status |
| 51 | orders | §13 | order_ref, order_no, party_ref, channel, status(7-state), territory_status, grand_total |
| 52 | order_items | §13 | item_ref, order_ref, product_ref, paid_qty, free_qty, rate, rate_source |
| 53 | order_status_history | §13 | order_ref, from_status, to_status, actor_ref |
| 54 | invoices | §16 | invoice_ref, invoice_no, order_ref, grand_total, paid_total, bill_to_snapshot |
| 55 | invoice_items | §16 | item_ref, invoice_ref, batch_ref, batch_no_snapshot, cgst_amount, sgst_amount |
| 56 | transporters | §17 | transporter_ref, transporter_name, tracking_url_template |
| 57 | dispatches | §17 | dispatch_ref, dispatch_no, invoice_ref, lr_number, status(8-state) |
| 58 | dispatch_status_history | §17 | dispatch_ref, from_status, to_status |
| 59 | payments | §20 | payment_ref, payment_no, party_ref, amount, allocated_amount, mode(7), status(5) |
| 60 | payment_allocations | §20 | allocation_ref, payment_ref, invoice_ref, allocated_amount |
| 61 | payment_reversals | §20 | reversal_ref, payment_ref, reason, idempotency_key |
| 62 | pdcs | §20 | pdc_ref, party_ref, amount, cheque_number, due_date, status(4) |
| 63 | webhook_sources | §8 | source_ref, source_name, endpoint_slug, auth_type, secret_enc |
| 64 | webhook_events | §8 | event_ref, source_ref, external_event_id, status(5), attempt_count |
| 65 | notification_templates | §19 | event_type, channel(4), body_template |
| 66 | notifications | §19 | notification_ref, user_ref, channel, status(5), attempt_count |
| 67 | job_queue | §27 | job_ref, job_type, payload_json, status(6), attempts |
| 68 | audit_logs | §27 | audit_ref, actor_ref, category(4), action, entity_type, before_json, after_json |
| 69 | ui_form_schemas | Zero-Local Engine | schema_ref, form_key, title, entity_type, version, status |
| 70 | ui_form_fields | Zero-Local Engine | field_ref, form_key, field_name, label, field_type, validation_rules_json, options_source_type, options_source_key |

### 3.2 Key Database Constraints

- **Immutable Audit**: BEFORE UPDATE/DELETE triggers on audit_logs signal error
- **Optimistic Concurrency**: version column on inventory_batches and orders
- **Quantity Guards**: on_hand_qty >= 0, reserved_qty <= on_hand_qty
- **Payment Guards**: amount > 0, allocated_amount <= amount
- **FEFO Index**: idx_batch_fefo on (franchise_ref, product_ref, status, expiry_date)
- **Pricing Exclusivity**: tier_ref IS NULL OR party_ref IS NULL

---

## 4-27. Module Details

*(Modules 4 through 27 cover Authentication, Super Admin, Users, Roles, Geo, Masters, Products, Pricing, Leads, Parties, Territories, Onboarding, Inventory, Orders, Invoicing, Dispatch, Payments, Portal, DCR, Webhooks, Notifications, Dashboard, Audit, and Infrastructure — each with FRS alignment, DB tables, field-to-column mapping, API endpoints, status machines, and business rules. See the complete API Route Registry below for the definitive list.)*

---

## 28. Complete API Route Registry (231 Routes)

All routes registered in bootstrap/routes.php:

### Health (4 routes)
```
GET  /health
GET  /ready
GET  /api/v1/health
GET  /api/v1/ready
```

### Auth (3 routes)
```
POST /api/v1/oauth/token
POST /api/v1/oauth/revoke
GET  /api/v1/auth/me
```

### Geo — Public (6 routes)
```
GET  /geo/states
GET  /geo/districts
GET  /geo/pincodes/{pin}
GET  /api/v1/geo/states
GET  /api/v1/geo/districts
GET  /api/v1/geo/pincodes/{pin}
```

### Super Admin (15 routes)
```
GET    /api/v1/super/organizations
POST   /api/v1/super/organizations
GET    /api/v1/super/organizations/{ref}
PATCH  /api/v1/super/organizations/{ref}
GET    /api/v1/super/franchises
POST   /api/v1/super/franchises
GET    /api/v1/super/franchises/{ref}
PATCH  /api/v1/super/franchises/{ref}
POST   /api/v1/super/franchises/{ref}/suspend
POST   /api/v1/super/franchises/{ref}/activate
POST   /api/v1/super/franchises/{ref}/admins
POST   /api/v1/super/impersonate/{ref}
GET    /api/v1/super/dashboard/stats
GET    /api/v1/super/audit
GET    /api/v1/super/security-events
```

### Admin Users (8 routes)
```
GET    /api/v1/admin/users
POST   /api/v1/admin/users
GET    /api/v1/admin/users/{ref}
PATCH  /api/v1/admin/users/{ref}
POST   /api/v1/admin/users/{ref}/activate
POST   /api/v1/admin/users/{ref}/deactivate
POST   /api/v1/admin/users/{ref}/reset-password
POST   /api/v1/admin/users/{ref}/unlock
```

### RBAC (9 routes)
```
GET    /api/v1/admin/roles
POST   /api/v1/admin/roles
GET    /api/v1/admin/roles/{ref}
PATCH  /api/v1/admin/roles/{ref}
POST   /api/v1/admin/roles/{ref}/clone
DELETE /api/v1/admin/roles/{ref}
GET    /api/v1/admin/permissions
POST   /api/v1/admin/users/{ref}/roles
DELETE /api/v1/admin/users/{ref}/roles/{role_ref}
```

### Settings (2 routes)
```
PATCH  /api/v1/admin/settings
GET    /api/v1/admin/settings
```

### Masters (18 routes)
```
GET    /api/v1/admin/categories
POST   /api/v1/admin/categories
GET    /api/v1/admin/categories/{ref}
PATCH  /api/v1/admin/categories/{ref}
POST   /api/v1/admin/categories/{ref}/status
GET    /api/v1/admin/tiers
POST   /api/v1/admin/tiers
GET    /api/v1/admin/tiers/{ref}
PATCH  /api/v1/admin/tiers/{ref}
POST   /api/v1/admin/tiers/{ref}/status
GET    /api/v1/admin/transporters
POST   /api/v1/admin/transporters
GET    /api/v1/admin/catalog-masters
GET    /api/v1/admin/catalog-masters/{category}
POST   /api/v1/admin/catalog-masters/{category}
GET    /api/v1/admin/catalog-masters/{category}/{ref}
PATCH  /api/v1/admin/catalog-masters/{category}/{ref}
POST   /api/v1/admin/catalog-masters/{category}/{ref}/status
GET    /api/v1/admin/notification-templates
```

### Zero-Local-Data UI Dynamic Form Schemas (6 routes)
```
GET    /api/v1/ui/forms
GET    /api/v1/ui/forms/{form_key}
POST   /api/v1/ui/forms/{form_key}/validate
GET    /api/v1/forms
GET    /api/v1/forms/{form_key}
POST   /api/v1/forms/{form_key}/validate
```

### Products (9 routes)
```
GET    /api/v1/admin/products
POST   /api/v1/admin/products
GET    /api/v1/admin/products/{ref}
PATCH  /api/v1/admin/products/{ref}
POST   /api/v1/admin/products/{ref}/activate
POST   /api/v1/admin/products/{ref}/deactivate
POST   /api/v1/admin/products/{ref}/archive
POST   /api/v1/admin/products/{ref}/restore
DELETE /api/v1/admin/products/{ref}
```

### Pricing (5 routes)
```
GET    /api/v1/admin/prices
POST   /api/v1/admin/prices
GET    /api/v1/admin/prices/{ref}
POST   /api/v1/admin/prices/{ref}/status
POST   /api/v1/admin/pricing/resolve
```

### Schemes (6 routes)
```
GET    /api/v1/admin/schemes
POST   /api/v1/admin/schemes
GET    /api/v1/admin/schemes/{ref}
PATCH  /api/v1/admin/schemes/{ref}
POST   /api/v1/admin/schemes/{ref}/status
POST   /api/v1/admin/schemes/calculate
```

### Leads (6 routes)
```
GET    /api/v1/admin/leads
POST   /api/v1/admin/leads
GET    /api/v1/admin/leads/{ref}
PATCH  /api/v1/admin/leads/{ref}
POST   /api/v1/admin/leads/{ref}/status
POST   /api/v1/admin/leads/{ref}/assign
```

### Follow-ups (4 routes)
```
GET    /api/v1/admin/follow-ups
POST   /api/v1/admin/follow-ups
POST   /api/v1/admin/follow-ups/{ref}/complete
POST   /api/v1/admin/follow-ups/{ref}/reschedule
```

### Parties (8 routes)
```
GET    /api/v1/admin/parties
POST   /api/v1/admin/parties
GET    /api/v1/admin/parties/{ref}
PATCH  /api/v1/admin/parties/{ref}
POST   /api/v1/admin/parties/{ref}/status
POST   /api/v1/admin/parties/{ref}/archive
POST   /api/v1/admin/parties/{ref}/restore
GET    /api/v1/admin/parties/{ref}/ledger
```

### Territories (8 routes)
```
GET    /api/v1/admin/territories
POST   /api/v1/admin/territories
GET    /api/v1/admin/territories/{ref}
PATCH  /api/v1/admin/territories/{ref}
POST   /api/v1/admin/territories/{ref}/status
POST   /api/v1/admin/territories/resolve
POST   /api/v1/admin/territories/validate
POST   /api/v1/admin/territories/override
```

### Onboarding (9 routes)
```
POST   /api/v1/admin/onboarding/invite
POST   /api/v1/onboarding/register                          [PUBLIC]
GET    /api/v1/admin/onboarding
GET    /api/v1/admin/onboarding/{ref}
POST   /api/v1/admin/onboarding/{ref}/approve
POST   /api/v1/admin/onboarding/{ref}/reject
POST   /api/v1/admin/onboarding/{ref}/request-info
POST   /api/v1/admin/onboarding/{ref}/convert
POST   /api/v1/admin/onboarding/{ref}/kyc-documents/{document_ref}/verify
```

### DCR — Portal (5 routes)
```
GET    /api/v1/portal/dcrs
POST   /api/v1/portal/dcrs
GET    /api/v1/portal/dcrs/{ref}
PATCH  /api/v1/portal/dcrs/{ref}
POST   /api/v1/portal/dcrs/{ref}/status
```

### Webhooks (3 routes)
```
GET    /api/v1/admin/webhook-sources
POST   /api/v1/admin/webhook-sources
POST   /api/v1/webhooks/{slug}/leads                        [PUBLIC+HMAC]
```

### Inventory (8 routes)
```
GET    /api/v1/admin/inventory/batches
GET    /api/v1/admin/inventory/batches/{ref}
POST   /api/v1/admin/inventory/receive
POST   /api/v1/admin/inventory/batches/{ref}/adjust
GET    /api/v1/admin/inventory/near-expiry
GET    /api/v1/admin/inventory/reservations/{order_ref}
POST   /api/v1/admin/inventory/reservations/{order_ref}/release
POST   /api/v1/admin/inventory/reservations/{order_ref}/consume
```

### Orders (9 routes)
```
GET    /api/v1/admin/orders
POST   /api/v1/admin/orders
POST   /api/v1/admin/orders/calculate
GET    /api/v1/admin/orders/{ref}
PATCH  /api/v1/admin/orders/{ref}
DELETE /api/v1/admin/orders/{ref}
POST   /api/v1/admin/orders/{ref}/submit
POST   /api/v1/admin/orders/{ref}/confirm
POST   /api/v1/admin/orders/{ref}/cancel
```

### Invoices (5 routes)
```
GET    /api/v1/admin/invoices
POST   /api/v1/admin/invoices/generate
GET    /api/v1/admin/invoices/by-order/{order_ref}
GET    /api/v1/admin/invoices/{ref}
POST   /api/v1/admin/invoices/{ref}/cancel
```

### Dispatches (4 routes)
```
GET    /api/v1/admin/dispatches
POST   /api/v1/admin/dispatches
GET    /api/v1/admin/dispatches/{ref}
POST   /api/v1/admin/dispatches/{ref}/deliver
```

### Payments (12 routes)
```
GET    /api/v1/admin/payments
POST   /api/v1/admin/payments
GET    /api/v1/admin/payments/{ref}
POST   /api/v1/admin/payments/{ref}/allocations
POST   /api/v1/admin/payments/{ref}/reverse
GET    /api/v1/admin/outstanding
GET    /api/v1/admin/pdc
POST   /api/v1/admin/pdc
GET    /api/v1/admin/pdc/{ref}
POST   /api/v1/admin/pdc/{ref}/realize
POST   /api/v1/admin/pdc/{ref}/bounce
POST   /api/v1/admin/pdc/{ref}/cancel
```

### Reports (4 routes)
```
GET    /api/v1/admin/dashboard
GET    /api/v1/admin/analytics/reports/{key}
GET    /api/v1/admin/reports/{type}
GET    /api/v1/admin/reports/{type}/export
```

### Audit (2 routes)
```
GET    /api/v1/admin/audit
GET    /api/v1/super/audit
```

### Notifications (3 routes)
```
GET    /api/v1/notifications
POST   /api/v1/notifications/read-all
POST   /api/v1/notifications/{ref}/read
```

### Portal — Distributor (11 routes)
```
GET    /api/v1/portal/profile
PATCH  /api/v1/portal/profile
GET    /api/v1/portal/catalogue
POST   /api/v1/portal/cart/calculate
GET    /api/v1/portal/orders
POST   /api/v1/portal/orders
GET    /api/v1/portal/orders/{ref}
POST   /api/v1/portal/orders/{ref}/cancel
GET    /api/v1/portal/invoices
GET    /api/v1/portal/dispatches
GET    /api/v1/portal/outstanding
```

### Web Shell Routes (22 routes)
```
GET /super/login, /admin/login, /sales/login, /portal/login
GET /super/dashboard, /admin/dashboard, /sales/dashboard, /portal/dashboard
GET /admin/{categories|tiers|products|prices|schemes|leads|follow-ups|parties|territories|orders|invoices|inventory|payments|dispatches|users|settings|reports|notifications}
```

---

## 29. Backend PHP Architecture

### 29.1 Repository Layer — 30 Interface/Implementation Pairs

| Repository | Table(s) |
|-----------|----------|
| AuditRepository | audit_logs |
| CatalogMasterRepository | catalog_master_values |
| CustomerRepository | parties |
| DcrRepository | dcr_reports_v2, dcr_visits_v2, dcr_history |
| DispatchRepository | dispatches, dispatch_status_history |
| FollowUpRepository | follow_ups |
| FranchiseRepository | franchises |
| IdempotencyRepository | api_idempotency_keys |
| InventoryBatchRepository | inventory_batches |
| InventoryMovementRepository | inventory_movements |
| InvoiceRepository | invoices, invoice_items |
| LeadRepository | leads, lead_activities |
| NotificationTemplateRepository | notification_templates |
| OrderRepository | orders, order_items, order_status_history |
| OrganizationRepository | organizations |
| PartyRepository | parties |
| PartyTerritoryRepository | party_territories |
| PaymentRepository | payments, payment_allocations |
| PricingTierRepository | pricing_tiers |
| ProductCategoryRepository | product_categories |
| ProductPriceRepository | product_prices |
| ProductRepository | products |
| RoleRepository | auth_roles, auth_role_permissions, auth_role_scopes |
| SchemeRepository | schemes, scheme_rules |
| StockReservationRepository | stock_reservations |
| SystemSettingsRepository | system_settings |
| TerritoryRepository | party_territories, territory_overrides |
| TokenRepository | user_sessions, oauth_refresh_tokens |
| TransporterRepository | transporters |
| UserRepository | users, auth_user_roles, auth_user_hierarchy |

### 29.2 Domain Layer — 26 Modules
Audit, Auth, Authorization, Billing, DCR, Dispatch, FollowUps, Franchises, Inventory, Jobs, Leads, Masters, Notifications, Onboarding, Orders, Parties, Payments, Pricing, Products, Reports, Schemes, Territories, Territory, Users, Webhooks, WhatsApp

---

## 30. Cross-Module Workflow Diagrams

### 30.1 Order-to-Cash Lifecycle
```
Lead → Follow-up → Convert to Party → Order → Validate (Territory+Pricing+Stock)
→ Submit → Confirm → Reserve Stock (FEFO) → Generate Invoice (GST+Snapshots)
→ Create Dispatch → Assign Transporter+LR → Deliver → Record Payment → Allocate to Invoice
```

### 30.2 Distributor Onboarding
```
Lead → Generate Invite → Share Link → Partner Registers (Public) → KYC Upload
→ Admin Review → Approve → Party + Portal User Created → Territory + Pricing Applied
→ Lead Converted → Welcome Notification → Portal Login
```

### 30.3 DCR Workflow
```
Owner Creates Team User → Team User Logs In → Creates DCR → Visits + POB
→ Submit → Owner Reviews → Approve/Reject → POB Aggregation → Convert to Order
```

---

## 31. Acceptance Criteria (44 Criteria)

### FRS v2.0: AC-TR-01/02, AC-FEFO-01/02, AC-EXP-01, AC-PRICE-01, AC-SCH-01, AC-DSP-01, AC-WA-01, AC-WH-01/02
### FRS v2.2: AC-INV-01/02/03/04, AC-REG-01/02/03, AC-APR-01/02/03, AC-DST-01/02, AC-DCR-01/02/03/04/05
### FRS v2.3: AC-ROL-01 through AC-ROL-16

---

## 32. Open Business Decisions (D-1 through D-25)

### Resolved
- Multiple territories per partner: YES
- Unassigned pincode policy: Configurable
- Minimum shelf-life at dispatch: Configurable
- Party vs tier pricing: Both with priority
- Scheme stacking: Configurable flag
- Credit limit behavior: Configurable
- Warehouse/Dispatch/Billing roles: Configurable roles

### Pending (D-1 through D-15, D-16 through D-25)
See FRS §43 and §47 for full decision list with defaults.

---

## 33. Glossary

| Term | Definition |
|------|-----------|
| FEFO | First Expiry, First Out |
| DCR | Daily Call Report |
| POB | Provisional Order Booking |
| PCD | Propaganda Cum Distribution |
| PDC | Post-Dated Cheque |
| PTS | Price to Stockist |
| MRP | Maximum Retail Price |
| GRN | Goods Receipt Note |
| LR | Lorry Receipt |
| KYC | Know Your Customer |
| GSTIN | GST Identification Number |
| HSN | Harmonized System Nomenclature |
| CGST/SGST/IGST | Central/State/Integrated GST |
| Ref | 24-char tenant-scoped unique identifier |
| Tenant | A franchise within an organization |
| Surface | UI variant (super, admin, sales, portal) |
| Envelope | API response: {success, data, meta} |

---

## Document References

| Document | Path |
|----------|------|
| FRS v2.3 | docs/front-end/Pharma_CRM_Master_FRS_v2.3.md |
| OpenAPI 3.0.3 | docs/front-end/openapi.yaml (3142 lines) |
| Database Schema | database/schema/001_full_schema.sql (68 tables, 2039 lines) |
| Routes | bootstrap/routes.php (223 routes) |
| DI Bindings | bootstrap/bindings.php (20KB) |

---

*End of Pharma CRM Unified Master Documentation v3.0*
