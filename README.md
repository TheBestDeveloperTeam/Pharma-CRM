# 💊 Pharma CRM & Sales Force Automation

> **Version:** v3.0 (CR-Roadmap v3) &nbsp;|&nbsp; **Stack:** Core PHP 8.1+ · MySQL 8 · Custom MVVM &nbsp;|&nbsp; **Hosting:** cPanel Shared Hosting

![PHP](https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=flat-square&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square&logo=mysql)
![License](https://img.shields.io/badge/License-Proprietary-red?style=flat-square)
![Build](https://img.shields.io/badge/Build-P0--P8-blue?style=flat-square)
![Auth](https://img.shields.io/badge/Auth-JWT%20%2B%20OAuth2-orange?style=flat-square)
![Tenancy](https://img.shields.io/badge/Tenancy-Row--Level-green?style=flat-square)
![Zero CDN](https://img.shields.io/badge/CDN-Zero%20Runtime-critical?style=flat-square)

---

## Table of Contents

1. [What This Is](#1-what-this-is)
2. [Business Context — PCD Pharma Model](#2-business-context--pcd-pharma-model)
3. [Who It's For — Actors & Roles](#3-whos-for--actors--roles)
4. [Core Business Flow](#4-core-business-flow)
5. [Technology Stack](#5-technology-stack)
6. [Architecture Overview](#6-architecture-overview)
7. [Four Surfaces & Themes](#7-four-surfaces--themes)
8. [Tenancy Model](#8-tenancy-model)
9. [Authentication](#9-authentication)
10. [Modules](#10-modules)
11. [Project Structure](#11-project-structure)
12. [Quick Start — Local Development](#12-quick-start--local-development)
13. [CLI Commands](#13-cli-commands)
14. [Build Phases P0–P8](#14-build-phases-p0p8)
15. [Engineering Rules R01–R16](#15-engineering-rules-r01r16)
16. [Test Agents](#16-test-agents)
17. [Documentation Index](#17-documentation-index)
18. [Change Control](#18-change-control)

---

## 1. What This Is

**Pharma CRM & Sales Force Automation** is a **cloud-based, multi-tenant CRM platform** purpose-built for the Indian pharmaceutical industry's **PCD (Propaganda Cum Distribution) and Franchise** business model. It automates the complete lifecycle from lead acquisition through to payment collection, across all actors of the pharma distribution chain.

The system is delivered as a **white-label SaaS platform** operated by a single Super Admin (platform owner), hosting multiple independent pharma companies (Franchise Admins) and their downstream sales and distribution networks.

### Key Characteristics

| Characteristic | Detail |
|---|---|
| **Multi-Tenancy** | One codebase, one database. Row-level isolation via `org_ref` + `franchise_ref` on every tenant table. |
| **Zero Runtime Dependencies** | No external PHP libraries, no CDN-loaded JS/CSS, no third-party fonts or icon sets at runtime. 100% self-contained. |
| **Four Independent Surfaces** | Super Admin, Franchise Admin, Sales Team, Distributor/Partner — each with distinct UI themes and route namespaces. |
| **Pharma-Specific Domain Logic** | FEFO inventory (First-Expired, First-Out), scheme-based pricing, territory management, GST-compliant billing, PCD franchise workflows. |
| **Security-First Architecture** | JWT Bearer tokens, idempotency on all writes, full audit logging on every state change, IDOR-protected policies, CSP headers, no token persistence in cookies or localStorage. |
| **cPanel-Native Deployment** | Designed for shared hosting reality: Apache + PHP-FPM/LSAPI, MySQL 8, Cron. No Docker, no Redis, no Kubernetes. |
| **Structured Testing** | Six blocking test agents (E2E, tenancy, duplicates, concurrency, security, IDOR) that must all be green before any release. |

---

## 2. Business Context — PCD Pharma Model

### What is PCD / Franchise Pharma?

In India, a large segment of pharmaceutical sales runs through the **PCD (Propaganda Cum Distribution)** model. A pharma manufacturer or marketing company (the **Franchise Company**) grants exclusive distribution rights for specific product ranges and territories to independent **franchise partners** (typically chemists, stockists, or entrepreneurs). These partners act as quasi-independent distributors but order exclusively from the franchise company.

```
┌──────────────────────────────────────────────────────────────────┐
│                    PHARMA FRANCHISE COMPANY                      │
│               (Franchise Admin on this platform)                 │
│                                                                  │
│  ┌──────────────┐    ┌──────────────┐    ┌──────────────────┐   │
│  │  Head Office │    │  Sales Team  │    │   Product Mgmt   │   │
│  │   (Admin)    │    │  (Field BDE) │    │  Pricing/Schemes │   │
│  └──────┬───────┘    └──────┬───────┘    └──────────────────┘   │
│         │                   │                                    │
└─────────┼───────────────────┼────────────────────────────────────┘
          │                   │ Field Visits / Follow-ups
          │                   ▼
          │     ┌─────────────────────────────┐
          │     │   FRANCHISE PARTNERS        │
          │     │  (Distributor / Stockist)   │
          │     │  - Territory-exclusive      │
          │     │  - Orders via portal        │
          │     │  - Payment against invoices │
          │     └─────────────────────────────┘
          │
          ▼
    ┌───────────┐
    │ SUPER     │
    │ ADMIN     │   ← Platform owner, manages franchise companies as tenants
    └───────────┘
```

### Why a Dedicated System?

Generic CRMs (Salesforce, Zoho) do not understand:
- **FEFO inventory** (pharmaceutical batch expiry management)
- **Scheme-based pricing** (buy-X-get-Y, percentage discounts, cash discounts tied to product tiers)
- **Territory exclusivity** (a franchise partner owns a PIN-code or district range)
- **Credit limit enforcement** (outstanding invoices block new orders)
- **GST-compliant invoice generation** with HSN codes, CGST/SGST/IGST split
- **PCD onboarding workflows** (NDA, territory agreement, product category assignment)

This platform solves all of the above within a single, tightly integrated system.

---

## 3. Who It's For — Actors & Roles

### Actor Matrix

| Actor | Surface | Route Prefix | Theme | Description |
|---|---|---|---|---|
| **Super Admin** | Super Admin Panel | `/super/*` | Midnight Navy + Gold | Platform owner. Manages franchise companies as tenants, monitors platform health, manages subscriptions, views cross-tenant reports. |
| **Franchise Admin** | Franchise Admin Panel | `/admin/*` | Pharma Teal + Amber | Pharma company operations manager. Manages products, pricing, schemes, territories, distributor onboarding, sales team, orders, billing, and dispatch. |
| **Sales Team (BDE/MR)** | Sales App | `/sales/*` | Royal Blue + Orange | Field sales executives. Manages leads, follow-ups, party (doctor/chemist) visits, order entry on behalf of distributors, territory reporting. |
| **Distributor / Partner** | Partner Portal | `/portal/*` | Violet + Mint | Franchise partners. Self-service order placement, invoice download, payment recording, inventory stock view, scheme browsing. |

### Permission Model

Each actor is assigned a **role** (e.g., `super_admin`, `franchise_admin`, `sales_manager`, `sales_executive`, `distributor`) with granular **permissions** checked at both route (middleware) and resource (policy) level. Cross-tenant access is impossible by default — it requires explicit `withoutTenantScope()` invocations that are themselves audited.

---

## 4. Core Business Flow

The following is the end-to-end lifecycle from a new prospect to a paid invoice, showing which actor performs each step.

```
STAGE 1 — LEAD ACQUISITION
  [Sales Team]
    1.  Sales executive captures Lead (doctor, chemist, hospital, distributor prospect)
    2.  Lead is assigned a territory and product category interest
    3.  Follow-ups are scheduled (call, visit, demo, sample drop)
    4.  Follow-up outcomes update Lead status: New → Contacted → Interested → Negotiating

STAGE 2 — QUALIFICATION & ONBOARDING
  [Sales Team → Franchise Admin]
    5.  Qualified lead is converted to Party (Doctor / Chemist / Hospital)
        or promoted to Distributor onboarding workflow
    6.  Franchise Admin reviews Distributor application:
        - Territory assignment (exclusive PIN/district range)
        - Product category allocation
        - Credit limit setting
        - NDA / agreement upload
    7.  Distributor account activated → credentials dispatched

STAGE 3 — ORDER PLACEMENT
  [Distributor via Portal | Sales Team on behalf]
    8.  Distributor browses product catalogue with live scheme pricing
    9.  Cart is built; server-side computes:
        - Base price from price list (tier-based)
        - Applicable schemes (buy-X-get-Y, percentage, cash discount)
        - GST (CGST/SGST or IGST based on state)
        - Credit limit check (outstanding balance vs. limit)
    10. Order submitted → IdempotencyMiddleware guards duplicate submission
    11. Franchise Admin reviews & approves order (or auto-approves per policy)

STAGE 4 — INVENTORY & DISPATCH
  [Franchise Admin / Warehouse]
    12. Approved order triggers FEFO inventory pick:
        - Batches selected in First-Expired-First-Out order
        - Stock reserved with FOR UPDATE + reservation row (Rule R06)
    13. Invoice generated via SequenceService (never MAX+1) (Rule R07)
        - GST-compliant: HSN, CGST/SGST/IGST, batch/expiry on line items
    14. Dispatch created: courier, AWB, EDD recorded
    15. Distributor notified via in-app notification + webhook

STAGE 5 — PAYMENT & RECONCILIATION
  [Distributor via Portal | Franchise Admin]
    16. Distributor records payment against invoice(s) (RTGS/NEFT/UPI/cheque)
    17. Franchise Admin verifies and reconciles payment
    18. Credit limit freed proportional to payment
    19. Outstanding balance updated; aged-debt reports updated in real time

STAGE 6 — REPORTING & ANALYTICS
  [Franchise Admin | Super Admin]
    20. Sales performance by territory, product, executive, scheme
    21. Inventory movement, expiry alerts, slow-movers
    22. Collection efficiency, credit utilisation, overdue ageing
    23. Super Admin: cross-tenant platform KPIs
```

---

## 5. Technology Stack

### Decision Rationale

Every technology choice was made to maximize reliability on **cPanel shared hosting** while maintaining a clean, maintainable architecture. No decisions were made for developer comfort at the expense of hosting constraints.

| Layer | Technology | Version | Rationale |
|---|---|---|---|
| **Language** | PHP | 8.1+ | Required for fibers, enums, readonly properties, intersection types. Widely available on cPanel. |
| **Database** | MySQL / MariaDB | 8.0+ (InnoDB) | ACID transactions, row-level locking (`FOR UPDATE`), JSON columns, full-text indexes. Universally available on cPanel. |
| **HTTP Framework** | Custom MVVM | bespoke | Zero overhead, no composer autoload of unused code. Full control of middleware pipeline. |
| **Frontend** | Vanilla HTML/CSS/JS | ES2020+ | No build step, no Node.js dependency, no CDN. Works on cPanel without npm/webpack. |
| **Auth** | JWT (HS256) + Opaque Refresh Token | — | OAuth 2.0-style flow. Short-lived access tokens (900s). Rotating opaque refresh tokens stored server-side only (Rule R13). |
| **Caching** | File-based Cache (`FileCache.php`) | — | Redis not available on standard cPanel. File cache with atomic writes for rate limiting and idempotency keys. |
| **Job Queue** | DB-backed queue + `JobRunner.php` | — | No Beanstalkd/RabbitMQ on cPanel. Cron-driven worker ticks process queued jobs. |
| **Sessions** | Stateless (JWT only) | — | No PHP sessions; all state carried in signed JWT or stored server-side with opaque token reference. |
| **Email** | PHP `mail()` / SMTP via config | — | Cron-dispatched notification worker batches and sends emails. |
| **Logging** | Custom `Logger.php` → `storage/logs/` | — | PSR-3 compatible interface, daily rotation, structured JSON lines. |
| **Testing** | Custom agent harness | — | Six blocking test agents covering E2E, security, concurrency, IDOR, tenancy isolation, and duplicate prevention. |
| **Deployment** | cPanel + `.htaccess` + Cron | — | Apache `mod_rewrite` for front-controller. PHP-FPM or LSAPI. MySQL over localhost socket. |

### What Is Deliberately Excluded

| Excluded | Reason |
|---|---|
| Composer / third-party PHP packages | No runtime third-party code (Rule R10). All utilities are bespoke. |
| Laravel / Symfony / Slim | Framework overhead not justified; no composer on cPanel deploy path. |
| React / Vue / Angular | Build toolchain unavailable on cPanel. Vanilla JS is fully sufficient for the UI scope. |
| Redis | Not available on standard cPanel shared hosting. |
| Docker / Kubernetes | Not available on cPanel. Adds deployment complexity without benefit. |
| CDN-loaded assets | Rule R10 — zero CDN at runtime. All assets are local. |
| `eval()`, `exec()` in business logic | Security policy. CLI scripts use `proc_open` where needed, not in request path. |

---

## 6. Architecture Overview

### Layered Architecture

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           HTTP REQUEST                                  │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
                    ┌───────────▼────────────┐
                    │  public/index.php       │  Front Controller
                    │  Bootstrap + DI Wire   │
                    └───────────┬────────────┘
                                │
              ┌─────────────────▼──────────────────────┐
              │           Middleware Pipeline            │
              │  RequestId → SecurityHeaders → RateLimit│
              │  → BearerAuth → Tenant → Role           │
              │  → Idempotency (writes only)            │
              └─────────────────┬──────────────────────┘
                                │
              ┌─────────────────▼──────────────────────┐
              │              Router                     │
              │  /super/* /admin/* /sales/* /portal/*  │
              │  /api/v1/* (JSON API endpoints)        │
              └──────┬───────────────────┬─────────────┘
                     │                   │
          ┌──────────▼──────┐   ┌────────▼──────────┐
          │  Web Controller │   │  API V1 Controller │
          │  (HTML views)   │   │  (JSON responses)  │
          └──────┬──────────┘   └────────┬───────────┘
                 │                       │
         ┌───────▼───────────────────────▼───────┐
         │              Domain Layer              │
         │  Services · Policies · Validators     │
         │  Rules · ViewModels                   │
         └───────────────────┬───────────────────┘
                             │
         ┌───────────────────▼───────────────────┐
         │           Repository Layer             │
         │  Contracts (interfaces) + SQL impls   │
         │  Always tenant-scoped by default      │
         └───────────────────┬───────────────────┘
                             │
         ┌───────────────────▼───────────────────┐
         │         Core Infrastructure            │
         │  Database · TenantContext · Logger    │
         │  SequenceService · RefGenerator       │
         │  EventBus · JobRunner · FileCache     │
         └───────────────────┬───────────────────┘
                             │
         ┌───────────────────▼───────────────────┐
         │             MySQL 8 (InnoDB)           │
         │  PDO — emulation OFF — prepared stmts │
         └───────────────────────────────────────┘
```

### Request Lifecycle

```
Request arrives at public/index.php
  │
  ├─ Application::boot() — registers bindings, resolves config
  ├─ Middleware::handle() — chain executes in order:
  │    RequestId        → X-Request-ID header set
  │    SecurityHeaders  → CSP, HSTS, X-Frame-Options, etc.
  │    RateLimit        → file-cache sliding window per IP/token
  │    BearerAuth       → JWT validate + decode → inject AuthUser
  │    Tenant           → resolve org_ref + franchise_ref → TenantContext
  │    Role             → check route permission against actor role
  │    Idempotency      → POST/PUT/PATCH: check/store idempotency key
  │
  ├─ Router::dispatch() → Controller::action()
  │    FormRequest::validate() → throw ValidationException on failure
  │    Policy::authorize()     → throw ForbiddenException on failure
  │    Service::execute()      → business logic
  │    Repository::*(...)      → always tenant-scoped SQL
  │    AuditLog::write()       → every state change logged
  │
  └─ Response → JSON (API) or HTML (Web)
```

### DI Container

`Application.php` implements a lightweight **PSR-11-compatible container** with constructor injection. All core services (Database, Logger, TenantContext, SequenceService, EventBus, etc.) are registered as singletons in `bootstrap/bindings.php`. Domain services and repositories are bound as transient (new instance per resolution).

---

## 7. Four Surfaces & Themes

Each surface is a completely separate UI shell with its own layout, navigation, and color theme. All colors are **CSS custom properties only** — no color literals in any view file (Rule R09).

### Surface Overview

| Surface | Route Prefix | Actor | Theme Name | Description |
|---|---|---|---|---|
| **Super Admin** | `/super/*` | Super Admin | `theme-super` | Platform management: tenant CRUD, subscription, platform health, cross-tenant reports. |
| **Franchise Admin** | `/admin/*` | Franchise Admin | `theme-admin` | Company operations: products, pricing, orders, team, territories, billing, dispatch. |
| **Sales App** | `/sales/*` | Sales Executive / Manager | `theme-sales` | Field-facing: leads, follow-ups, party visits, mobile-optimized layout. |
| **Partner Portal** | `/portal/*` | Distributor / Partner | `theme-portal` | Self-service: catalogue browse, order placement, invoice download, payment recording. |

### Theme Color Tokens

| Token | `theme-super` | `theme-admin` | `theme-sales` | `theme-portal` |
|---|---|---|---|---|
| `--color-primary` | `#0D1B2A` (Midnight Navy) | `#00897B` (Pharma Teal) | `#1565C0` (Royal Blue) | `#6A0DAD` (Violet) |
| `--color-accent` | `#D4AF37` (Gold) | `#FFB300` (Amber) | `#E65100` (Orange) | `#3EB489` (Mint) |
| `--color-bg` | `#0F2031` | `#F5FAFA` | `#EDF3FB` | `#F8F5FF` |
| `--color-surface` | `#162840` | `#FFFFFF` | `#FFFFFF` | `#FFFFFF` |
| `--color-text` | `#E8EDF2` | `#1A2E2B` | `#0D1B3E` | `#1C0A35` |
| `--color-border` | `#2A3F55` | `#B2DFDB` | `#BBDEFB` | `#D1B3EF` |
| `--color-danger` | `#EF5350` | `#EF5350` | `#EF5350` | `#EF5350` |
| `--color-success` | `#66BB6A` | `#43A047` | `#43A047` | `#43A047` |
| `--color-warning` | `#FFA726` | `#FB8C00` | `#FB8C00` | `#FB8C00` |

### Theme Resolution

`ThemeResolver.php` determines the active theme from the route prefix and injects it into the layout template as a `<body data-theme="theme-super">` (or similar) attribute. The CSS file `public/assets/css/crm-ui.css` defines all four theme blocks as attribute selectors: `[data-theme="theme-admin"] { --color-primary: ... }`.

---

## 8. Tenancy Model

### Hierarchy

```
Platform (Super Admin)
  └── Organization (org_ref)          ← A pharma company group / holding
        └── Franchise (franchise_ref)  ← An independent PCD franchise / branch
              ├── Users               ← Sales team, admins
              ├── Products / Pricing
              ├── Territories
              ├── Parties / Leads
              ├── Orders / Invoices
              └── Distributors
```

### Row-Level Tenancy

Every tenant-owned table carries **both** `org_ref` (VARCHAR, references the organization) and `franchise_ref` (VARCHAR, references the franchise within the org). This two-level hierarchy allows:

- **Org-level aggregation**: A holding company can see consolidated data across its franchises.
- **Franchise-level isolation**: Each franchise branch operates independently with its own product list, pricing, territories, and team.

```sql
-- Example: leads table
CREATE TABLE leads (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    org_ref       VARCHAR(32)  NOT NULL,
    franchise_ref VARCHAR(32)  NOT NULL,
    ref           VARCHAR(32)  NOT NULL UNIQUE,
    name          VARCHAR(255) NOT NULL,
    -- ... business columns ...
    INDEX idx_tenant (org_ref, franchise_ref),
    UNIQUE KEY uq_ref_tenant (franchise_ref, ref)   -- R02: tenant-scoped unique
);
```

### TenantContext

`TenantContext.php` is a **request-scoped singleton** populated by the `Tenant` middleware after JWT validation. It exposes:

```php
TenantContext::orgRef()       // Current org_ref
TenantContext::franchiseRef() // Current franchise_ref
TenantContext::actor()        // AuthUser object
TenantContext::require()      // Throws if context not set (safety guard)
```

### TenantScope

`TenantScope.php` is a query-builder decorator automatically applied by all `Sql\*Repository` classes. It appends `WHERE org_ref = ? AND franchise_ref = ?` to every SELECT, UPDATE, and DELETE. To bypass (e.g., for Super Admin cross-tenant queries), the caller must explicitly call:

```php
$repo->withoutTenantScope('super_admin_report_cross_tenant');
// This call is itself logged to audit_logs with actor + reason
```

### Unique Constraint Policy (R02)

All unique constraints on business data are **franchise-led composite keys**:

```sql
-- Correct (R02 compliant):
UNIQUE KEY uq_product_sku (franchise_ref, sku)

-- Wrong (would allow cross-franchise duplicates to silently collide):
UNIQUE KEY uq_sku (sku)
```

---

## 9. Authentication

### Flow Overview

The system implements an **OAuth 2.0-style token flow** without the authorization server complexity (no third-party clients). All tokens are Bearer-only — no cookies, no sessions.

```
┌─────────────────────────────────────────────────────────────────┐
│                     AUTHENTICATION FLOW                         │
│                                                                 │
│  1. POST /api/v1/auth/login                                     │
│     { email, password, idempotency_key }                        │
│         │                                                       │
│         ▼                                                       │
│     TokenService::issue()                                       │
│     ├── Validate credentials (PasswordHasher::verify)           │
│     ├── Generate JWT access token (HS256, 900s TTL)             │
│     │     payload: { sub, org_ref, franchise_ref,               │
│     │                role, jti, iat, exp }                      │
│     ├── Generate opaque refresh token (128-bit random hex)      │
│     │     stored in: refresh_tokens table (hashed)              │
│     └── Return: { access_token, refresh_token, expires_in }     │
│                                                                 │
│  2. Every subsequent request:                                   │
│     Authorization: Bearer <access_token>                        │
│     BearerAuth middleware → Jwt::decode() → inject AuthUser     │
│                                                                 │
│  3. POST /api/v1/auth/refresh                                   │
│     { refresh_token }                                           │
│     ├── Lookup + verify hash in refresh_tokens table            │
│     ├── Rotate: invalidate old, issue new refresh token         │
│     └── Issue new JWT access token                              │
│                                                                 │
│  4. POST /api/v1/auth/logout                                    │
│     ├── Revoke refresh token (delete from table)                │
│     └── 204 No Content                                          │
└─────────────────────────────────────────────────────────────────┘
```

### Token Storage Rules (R13)

| Token | Storage Rule |
|---|---|
| **JWT Access Token** | Client memory only (JS variable). Never persisted to `localStorage`, `sessionStorage`, or cookies. |
| **Opaque Refresh Token** | `HttpOnly; Secure; SameSite=Strict` cookie on auth domain, or client-managed secure storage (not `localStorage`). Never in JWT payload. |
| **Server-side** | Refresh tokens stored as **bcrypt hash** in `refresh_tokens` table. Never plaintext. |

### JWT Payload

```json
{
  "sub":           "usr_01HXXX",
  "org_ref":       "org_pharma01",
  "franchise_ref": "frn_north01",
  "role":          "franchise_admin",
  "jti":           "unique-token-id",
  "iat":           1726843200,
  "exp":           1726844100
}
```

### Key Management

JWT signing keys are managed by `cli/keys.php`. Keys are stored in `storage/private/jwt.key` (outside `public/`). Rotation issues a new key and allows a grace window for outstanding tokens signed with the old key.

```bash
php cli/keys.php generate   # First-time key generation
php cli/keys.php rotate     # Graceful rotation with overlap window
```

---

## 10. Modules

The system is organized into **23 domain modules**, each encapsulating its Entity, Service, Policy, and Business Rules.

| # | Module | Surface(s) | Description |
|---|---|---|---|
| 1 | **Tenancy** | Super | Organization and franchise lifecycle. Create, suspend, reactivate tenants. Subscription management. |
| 2 | **Auth** | All | Login, logout, refresh, password reset, MFA scaffold. Token issuance and revocation. |
| 3 | **Users** | Super, Admin | User CRUD. Role assignment. Password management. Login history. Active session listing. |
| 4 | **Organizations** | Super | Top-level org management. Org settings, branding (logo, trade name), subscription tier. |
| 5 | **Franchises** | Super, Admin | Franchise branch management within an org. Settings, credit policies, GST registration. |
| 6 | **Leads** | Sales, Admin | Prospect capture (doctors, chemists, distributors). Status pipeline. Assignment to executives. |
| 7 | **FollowUps** | Sales, Admin | Scheduled activities against leads/parties. Outcome recording. Overdue alerts. Visit GPS tagging. |
| 8 | **Parties** | Sales, Admin | Converted leads. Doctors, chemists, hospitals. Relationship management. Sample tracking. |
| 9 | **Territories** | Admin, Sales | Geographic territory definition (state → district → PIN). Exclusive assignment to distributors. Overlap detection. |
| 10 | **Products** | Admin | Product master (molecule, brand name, pack size, HSN code, schedule). Category and sub-category. Division. |
| 11 | **Pricing** | Admin | Price lists by tier (distributor, retailer, MRP). Effective date ranges. Currency and GST rate. |
| 12 | **Schemes** | Admin | Promotional schemes: buy-X-get-Y (product or cash), percentage discount, flat discount. Date-bounded. Auto-apply rules. |
| 13 | **Orders** | Admin, Sales, Portal | Order placement, review, approval. Line-item level scheme application. Credit check. Status pipeline: Draft → Confirmed → Processing → Dispatched → Delivered. |
| 14 | **Inventory** | Admin | Batch-level stock management. FEFO picking logic. Expiry alerts. Minimum stock levels. Movement ledger. |
| 15 | **Billing** | Admin | GST-compliant invoice generation. HSN-wise tax summary. Credit note, debit note. E-invoice scaffold. |
| 16 | **Dispatch** | Admin | Dispatch creation against invoice. Courier integration (webhook-based tracking). AWB management. EDD tracking. |
| 17 | **Payments** | Admin, Portal | Payment recording (RTGS, NEFT, UPI, cheque, cash). Reconciliation. Credit limit recalculation on payment. Overdue aging. |
| 18 | **Notifications** | All | In-app notification feed. Email queue. Webhook event dispatch. User preference management. |
| 19 | **Webhooks** | Admin | Outbound webhook registration and dispatch. Signature (HMAC-SHA256). Retry with backoff. Event catalog. |
| 20 | **Reports** | Admin, Super | Sales performance, territory coverage, collection efficiency, expiry report, scheme utilization, inventory aging. CSV and PDF export. |
| 21 | **Audit** | Super, Admin | Immutable audit log viewer. Every state-changing operation logged with actor, tenant, before/after snapshot, request_id. |
| 22 | **Onboarding** | Admin, Sales | Distributor onboarding workflow. Document upload (NDA, drug license, GST cert). Approval pipeline. Credential dispatch. |
| 23 | **Settings** | Admin, Super | Franchise-level configuration: credit policy, order auto-approve thresholds, notification preferences, GST details, logo upload. |

---

## 11. Project Structure

```
e:\Projects\PHP\crm\
│
├── .ai/                              # AI-assisted development artifacts
│   ├── agents/                       # Agent definitions and prompts
│   ├── decisions/                    # Architecture decision records (ADRs)
│   ├── knowledge/                    # Domain knowledge base
│   └── planning/                     # Sprint planning, roadmap notes
│
├── app/
│   ├── Config/                       # All configuration files (no .env parsing at runtime)
│   │   ├── app.php                   # App name, URL, environment, debug flag
│   │   ├── database.php              # DSN, credentials, pool settings
│   │   ├── auth.php                  # JWT TTL, refresh TTL, algorithm
│   │   ├── tenancy.php               # Tenant header names, scope defaults
│   │   ├── theme.php                 # Surface-to-theme mapping
│   │   ├── security.php              # CSP policy, HSTS config, rate limits
│   │   ├── cache.php                 # File cache root, TTLs
│   │   ├── queue.php                 # Job queue table, worker batch size
│   │   └── integrations.php         # Webhook defaults, SMTP, SMS gateway
│   │
│   ├── Core/                         # Framework kernel — zero business logic
│   │   ├── Application.php           # Bootstrap, DI container, lifecycle
│   │   ├── Container.php             # PSR-11 DI container (singleton + transient)
│   │   ├── Router.php                # Route registration and dispatch
│   │   ├── Request.php               # HTTP request wrapper (headers, body, files)
│   │   ├── Response.php              # HTTP response (JSON, HTML, redirect, file)
│   │   ├── Validation.php            # Rule engine (required, type, regex, custom)
│   │   ├── Database.php              # PDO wrapper — emulation OFF, exceptions ON (R16)
│   │   ├── Transaction.php           # DB transaction helper with savepoints
│   │   ├── Logger.php                # PSR-3 logger → storage/logs/ (JSON lines)
│   │   ├── FileCache.php             # Atomic file-based key-value cache
│   │   ├── EventBus.php              # Synchronous event dispatch (listeners)
│   │   ├── JobRunner.php             # DB-backed async job queue worker
│   │   ├── Idempotency.php           # Idempotency key check/store (R03)
│   │   ├── TenantContext.php         # Request-scoped tenant state holder
│   │   ├── TenantScope.php           # Automatic tenant WHERE clause decorator
│   │   ├── ThemeResolver.php         # Route-prefix → theme-name mapping
│   │   ├── RefGenerator.php          # Collision-safe reference string generator
│   │   ├── SequenceService.php       # Monotonic invoice/order number sequences (R07)
│   │   ├── Security/
│   │   │   ├── Jwt.php               # JWT encode/decode (HS256, no third-party lib)
│   │   │   ├── PasswordHasher.php    # bcrypt hash and verify
│   │   │   ├── TokenService.php      # Access + refresh token lifecycle
│   │   │   ├── RateLimiter.php       # Sliding window rate limiter (file cache)
│   │   │   └── Csp.php               # Content Security Policy builder
│   │   └── Exceptions/
│   │       ├── AppException.php      # Base application exception
│   │       ├── ValidationException.php
│   │       ├── ForbiddenException.php
│   │       ├── NotFoundException.php
│   │       ├── ConflictException.php
│   │       └── BusinessRuleException.php
│   │
│   ├── Http/
│   │   ├── Middleware/
│   │   │   ├── RequestId.php         # Generates/propagates X-Request-ID
│   │   │   ├── SecurityHeaders.php   # CSP, HSTS, X-Content-Type-Options, etc.
│   │   │   ├── RateLimit.php         # Per-IP / per-token rate limiting
│   │   │   ├── BearerAuth.php        # JWT extraction and validation
│   │   │   ├── Tenant.php            # TenantContext population from JWT claims
│   │   │   ├── Role.php              # Route-level role/permission enforcement
│   │   │   └── Idempotency.php       # POST/PUT/PATCH idempotency guard
│   │   ├── Controllers/
│   │   │   ├── Web/                  # HTML-rendering controllers per surface
│   │   │   │   ├── Auth/             # Login, logout, password reset views
│   │   │   │   ├── Super/            # Super admin panel controllers
│   │   │   │   ├── Admin/            # Franchise admin controllers
│   │   │   │   ├── Sales/            # Sales app controllers
│   │   │   │   └── Portal/           # Distributor portal controllers
│   │   │   └── Api/V1/              # JSON API controllers (consumed by JS modules)
│   │   │       ├── Auth/
│   │   │       ├── Super/
│   │   │       ├── Admin/
│   │   │       ├── Sales/
│   │   │       ├── Portal/
│   │   │       └── Webhooks/
│   │   └── FormRequests/             # Validated input objects per endpoint
│   │
│   ├── Domain/                       # Business domain — one directory per module
│   │   ├── <Module>/
│   │   │   ├── <Entity>.php          # Domain entity / value object
│   │   │   ├── <Module>Service.php   # Business logic orchestration
│   │   │   ├── <Module>Policy.php    # Authorization checks (IDOR, role, tenant)
│   │   │   └── Rules/                # Isolated business rule classes
│   │   └── ... (23 module directories)
│   │
│   ├── Repositories/
│   │   ├── Contracts/                # Interfaces per module (e.g., LeadRepositoryInterface)
│   │   └── Sql/                      # MySQL PDO implementations (always tenant-scoped)
│   │
│   ├── Policies/                     # Cross-cutting authorization policies
│   ├── Validators/                   # Reusable validation rule sets
│   ├── ViewModels/                   # Data shaping for views (no raw entities in views)
│   └── Views/                        # PHP template files
│       ├── layouts/                  # Base layout per theme (super, admin, sales, portal)
│       ├── components/               # Shared UI components (table, modal, form, badge)
│       ├── auth/                     # Login, forgot password, MFA
│       ├── super/                    # Super admin page templates
│       ├── admin/                    # Franchise admin page templates
│       ├── sales/                    # Sales app page templates
│       ├── portal/                   # Distributor portal page templates
│       └── errors/                   # 403, 404, 422, 429, 500 error pages
│
├── bootstrap/
│   ├── app.php                       # Application factory — loads config, creates app
│   ├── routes.php                    # All route definitions (grouped by surface)
│   ├── middleware.php                # Middleware stack registration
│   └── bindings.php                  # DI bindings: interface → implementation
│
├── cli/                              # Command-line scripts (not web-accessible)
│   ├── install.php                   # Full install: schema + seed + super admin + keys
│   ├── migrate.php                   # Schema migration runner
│   ├── seed.php                      # Database seeder
│   ├── worker.php                    # Async job queue worker
│   ├── scheduler.php                 # Cron task dispatcher (minute/hourly/daily)
│   ├── backup.php                    # Backup + restore verification
│   ├── tenant.php                    # Tenant provisioning CLI
│   ├── user.php                      # User management CLI
│   ├── keys.php                      # JWT key generate/rotate
│   ├── test-agents.php               # Test agent runner
│   └── lint.php                      # Static code lint checker
│
├── database/
│   ├── schema/
│   │   └── 001_full_schema.sql       # Complete schema — every table, index, FK
│   ├── seeds/
│   │   └── 001_fresh_seed.sql        # Reference data + demo data seed
│   └── fixtures/                     # Test fixtures for agent test runs
│
├── docs/                             # Living documentation
│   ├── architecture/                 # System design, component diagrams, decisions
│   ├── business-rules/               # Domain rules per module
│   ├── workflows/                    # Step-by-step process flows
│   ├── decisions/                    # Architecture Decision Records (ADRs)
│   ├── deployment/                   # cPanel deployment runbooks
│   ├── runbooks/                     # Operational runbooks (backup, restore, scale)
│   └── api/                          # OpenAPI YAML per module
│
├── public/                           # Web root (Apache DocumentRoot)
│   ├── index.php                     # Front controller — only entry point
│   ├── .htaccess                     # mod_rewrite rules, security headers
│   ├── robots.txt
│   └── assets/
│       ├── css/
│       │   └── crm-ui.css            # All styles — four themes, components, utilities
│       ├── js/
│       │   ├── crm-ui.js             # Core JS (router, fetch wrapper, toast, modal)
│       │   └── modules/              # Per-feature JS modules (lazy-loaded)
│       ├── img/
│       │   └── icons.svg             # Inline SVG sprite — all icons, no icon font
│       └── tenant/                   # Tenant-specific uploaded assets (logos)
│
├── storage/                          # Writable runtime storage (outside web root)
│   ├── logs/                         # Application logs (daily rotation)
│   ├── cache/                        # FileCache data
│   ├── exports/                      # Generated CSV / PDF reports
│   ├── temporary/                    # Temp file uploads (pre-validation)
│   ├── private/                      # JWT keys, sensitive config
│   └── backups/                      # DB backup archives
│
└── tests/
    ├── Agents/                       # One file per test agent
    └── Support/
        ├── Harness.php               # Test runner bootstrap and assertions
        ├── Http.php                  # HTTP client for agent E2E requests
        ├── Seed.php                  # Test data factory
        └── Assert.php                # Custom assertion helpers
```

---

## 12. Quick Start — Local Development

### Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| XAMPP | 8.1+ | Apache + PHP 8.1 + MySQL 8 included |
| PHP CLI | 8.1+ | Must match XAMPP PHP version |
| MySQL | 8.0+ | Local: `localhost:3306`, user `root`, no password, DB `crm` |
| Apache | 2.4+ | `mod_rewrite` must be enabled |
| Git | Any | For version control |

### Setup Steps

**1. Clone and configure virtual host**

```bash
# Clone to XAMPP htdocs or configure a vhost pointing to project/public/
# Recommended: vhost at http://crm/

# Ensure Apache httpd-vhosts.conf contains:
# <VirtualHost *:80>
#     ServerName crm
#     DocumentRoot "E:/Projects/PHP/crm/public"
#     <Directory "E:/Projects/PHP/crm/public">
#         AllowOverride All
#         Require all granted
#     </Directory>
# </VirtualHost>
```

**2. Add host entry (Windows)**

```powershell
# Run as Administrator
Add-Content C:\Windows\System32\drivers\etc\hosts "127.0.0.1 crm"
```

**3. Create the database**

```sql
-- In MySQL / phpMyAdmin:
CREATE DATABASE crm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**4. Run the installer**

```bash
# From project root (E:\Projects\PHP\crm\)
php cli/install.php
```

This single command:
- Loads the full schema from `database/schema/001_full_schema.sql`
- Runs the fresh seed from `database/seeds/001_fresh_seed.sql`
- Creates the Super Admin user (credentials printed to console)
- Generates the JWT signing key in `storage/private/jwt.key`
- Creates required `storage/` subdirectories

**5. Verify installation**

```bash
php cli/test-agents.php --all
```

All six agents must report **GREEN** before proceeding with development.

**6. Access the application**

| Surface | URL | Default Credentials |
|---|---|---|
| Super Admin | `http://crm/super/login` | Printed by installer |
| Franchise Admin | `http://crm/admin/login` | Created via `cli/tenant.php` |
| Sales App | `http://crm/sales/login` | Created via `cli/user.php` |
| Partner Portal | `http://crm/portal/login` | Created via Onboarding module |

---

## 13. CLI Commands

All CLI scripts are in the `cli/` directory. Run from the project root with `php cli/<script>.php`.

> **Note:** CLI scripts must never be web-accessible. The `public/` directory is the only web root. All `cli/` scripts perform access checks to verify they are running from the CLI SAPI (`php_sapi_name() === 'cli'`).

### Full Command Reference

```bash
# ─────────────────────────────────────────────
# INSTALLATION & SCHEMA
# ─────────────────────────────────────────────

php cli/install.php
  # Full installation: schema + seed + super admin creation + JWT key generation.
  # Safe to run on a fresh DB only. Will abort if tables already exist.

php cli/migrate.php --fresh
  # DROP all tables and recreate from 001_full_schema.sql.
  # WARNING: Destroys all data. For dev/staging only.

php cli/migrate.php --check
  # Verify schema matches expected state without modifying.

# ─────────────────────────────────────────────
# SEEDING
# ─────────────────────────────────────────────

php cli/seed.php --fresh
  # Clear all tenant data and re-seed reference data (categories, GST rates, etc.)

php cli/seed.php --fresh --demo
  # As above, plus demo org/franchise/users/products/leads/orders for UI testing.

# ─────────────────────────────────────────────
# TENANT MANAGEMENT
# ─────────────────────────────────────────────

php cli/tenant.php create \
  --org-name="Alpha Pharma Ltd" \
  --org-ref="org_alpha" \
  --franchise-name="North Region" \
  --franchise-ref="frn_north" \
  --admin-email="admin@alphapharma.com"
  # Create an organization + franchise + franchise admin user in one step.

php cli/tenant.php suspend --franchise-ref="frn_north"
  # Soft-suspend a franchise (login blocked, data intact).

php cli/tenant.php reactivate --franchise-ref="frn_north"
  # Re-activate a suspended franchise.

# ─────────────────────────────────────────────
# USER MANAGEMENT
# ─────────────────────────────────────────────

php cli/user.php create \
  --franchise-ref="frn_north" \
  --role=sales_executive \
  --name="Ramesh Kumar" \
  --email="ramesh@alphapharma.com"
  # Create a user and print generated password.

php cli/user.php reset-password --email="ramesh@alphapharma.com"
  # Generate and print a new temporary password.

# ─────────────────────────────────────────────
# JWT KEY MANAGEMENT
# ─────────────────────────────────────────────

php cli/keys.php generate
  # Generate new JWT HMAC key. Stores in storage/private/jwt.key.
  # Run once on first install.

php cli/keys.php rotate
  # Rotate JWT key gracefully.
  # Old key retained for grace window (config: auth.key_grace_seconds).
  # Outstanding tokens signed with old key remain valid during grace period.

# ─────────────────────────────────────────────
# WORKER & SCHEDULER
# ─────────────────────────────────────────────

php cli/worker.php --once
  # Process one batch of queued jobs (notifications, webhook dispatches, exports).
  # Suitable for cron: run every minute.

php cli/worker.php --loop --sleep=5
  # Continuous worker loop (for local dev only — use cron in production).

php cli/scheduler.php minute
  # Dispatch all tasks scheduled to run every minute.
  # Cron: * * * * * php /path/to/cli/scheduler.php minute

php cli/scheduler.php hourly
  # Dispatch hourly tasks (expiry alerts, credit checks).
  # Cron: 0 * * * * php /path/to/cli/scheduler.php hourly

php cli/scheduler.php daily
  # Dispatch daily tasks (day-end reports, backup trigger, token cleanup).
  # Cron: 0 1 * * * php /path/to/cli/scheduler.php daily

# ─────────────────────────────────────────────
# BACKUP & RESTORE
# ─────────────────────────────────────────────

php cli/backup.php
  # Create timestamped DB dump in storage/backups/.

php cli/backup.php --verify
  # Create backup + restore to temp DB + verify row counts match.
  # Run this before every production deployment.

# ─────────────────────────────────────────────
# TESTING & QUALITY
# ─────────────────────────────────────────────

php cli/test-agents.php --all
  # Run all six test agents. All must be GREEN before any release.

php cli/test-agents.php --agent=security
  # Run a single named agent.

php cli/lint.php
  # Static lint: check for color literals in views (R09), raw SQL outside
  # repositories (R16), missing audit calls, hardcoded credentials, etc.
```

### Recommended cPanel Cron Configuration

```
# Every minute — job worker + minute scheduler
* * * * * /usr/local/bin/php /home/<user>/crm/cli/worker.php --once >> /dev/null 2>&1
* * * * * /usr/local/bin/php /home/<user>/crm/cli/scheduler.php minute >> /dev/null 2>&1

# Every hour — hourly scheduler
0 * * * * /usr/local/bin/php /home/<user>/crm/cli/scheduler.php hourly >> /dev/null 2>&1

# Daily at 1:00 AM — daily scheduler + backup
0 1 * * * /usr/local/bin/php /home/<user>/crm/cli/scheduler.php daily >> /dev/null 2>&1
0 2 * * * /usr/local/bin/php /home/<user>/crm/cli/backup.php --verify >> /dev/null 2>&1
```

---

## 14. Build Phases P0–P8

The project is structured into nine sequential build phases. Each phase produces a complete, testable vertical slice before the next begins.

| Phase | Name | Status | Key Deliverables |
|---|---|---|---|
| **P0** | Foundation | 🟡 In Progress | Core framework kernel: `Application`, `Container`, `Router`, `Request`, `Response`, `Database` (PDO, R16), `Validation`, `Logger`, `FileCache`, `EventBus`, `JobRunner`, `Idempotency`, `TenantContext`, `TenantScope`, `SequenceService`, `RefGenerator`. Test harness bootstrap. Schema skeleton. |
| **P1** | Auth + Tenancy + Themes | ⬜ Pending | `Jwt`, `PasswordHasher`, `TokenService`, `RateLimiter`, `Csp`. Auth middleware stack. Login/logout/refresh endpoints. Four surface shells with correct theme tokens. CLI install + keys. Org/franchise/user CRUD. agent-security + agent-tenancy green. |
| **P2** | Masters | ⬜ Pending | Products (categories, sub-categories, divisions, HSN). Pricing (tier-based price lists, date ranges). Schemes (buy-X-get-Y, percentage, flat). Admin UI for all masters. agent-duplicates green on master data. |
| **P3** | CRM | ⬜ Pending | Leads (capture, pipeline, assignment). FollowUps (scheduling, outcomes, overdue). Parties (converted leads, relationship). Territories (definition, exclusive assignment, overlap detection). Onboarding (distributor workflow, documents, approval). Webhooks (registration, HMAC dispatch, retry). agent-e2e green on CRM flow. |
| **P4** | Order-to-Cash | ⬜ Pending | Orders (placement, approval, pricing computation). Inventory (FEFO batch management, FOR UPDATE reservation, R06). Billing (GST invoice generation, credit/debit notes, R07). Dispatch (courier, AWB, EDD). Payments (recording, reconciliation, credit limit recalculation). agent-concurrency green on stock reservation. |
| **P5** | Notifications & Workers | ⬜ Pending | In-app notification feed. Email queue + worker. Webhook event dispatch + retry. Scheduler tasks (expiry alerts, overdue reminders, day-end). Push notification scaffold. |
| **P6** | Distributor Portal | ⬜ Pending | `/portal/*` surface complete. Self-service catalogue, cart, order placement. Invoice download. Payment recording. Scheme viewer. Stock visibility. agent-idor green on portal. |
| **P7** | Super Admin + Reports + Hardening | ⬜ Pending | `/super/*` surface complete. Cross-tenant reporting. Platform KPIs. Audit log viewer. Security hardening (CSP strict, HSTS, rate limit tuning). All six agents green. OpenAPI docs complete. |
| **P8** | cPanel Production Deployment | ⬜ Pending | Production `.htaccess`, `php.ini` overrides, `storage/` permissions. Cron configuration. Backup + verify run. DNS cutover runbook. Post-deploy smoke test script. |

---

## 15. Engineering Rules R01–R16

These rules are **non-negotiable**. Every pull request / code change is reviewed against this checklist. The `cli/lint.php` script automates checking for several of these rules.

### R01 — Tenant Columns on Every Tenant Table

> **Every row in every tenant-owned table carries both `org_ref` and `franchise_ref`.**

No exceptions. Tables that are platform-level only (e.g., `organizations`, `franchises`, `super_admin_users`) are excluded, but all business data tables must carry both columns. This enables deterministic tenant isolation without any ORM magic.

---

### R02 — Tenant-Scoped Unique Constraints

> **Every unique constraint on business data is a composite key, franchise-led.**

Example: `UNIQUE KEY uq_product_sku (franchise_ref, sku)`. A plain `UNIQUE KEY uq_sku (sku)` would mean two franchises can't have the same SKU — incorrect in a multi-tenant system. Franchise A's SKU `PAR-500` and Franchise B's `PAR-500` must coexist.

---

### R03 — Idempotency on Every Business Write

> **Every POST, PUT, and PATCH endpoint passes `IdempotencyMiddleware`.**

Clients must supply an `Idempotency-Key: <uuid>` header. The middleware checks a cache of recently processed keys. If the key is seen again within the window (e.g., 24 hours), the cached response is returned without re-executing the handler. This prevents duplicate orders, double invoices, and double payments from network retries.

---

### R04 — Audit Log on Every State Change

> **Every business write (create, update, delete, status change) writes an `audit_logs` row.**

Minimum audit entry contains: `actor_ref`, `org_ref`, `franchise_ref`, `request_id`, `entity_type`, `entity_ref`, `action`, `before_state` (JSON), `after_state` (JSON), `created_at`. The audit log table is **insert-only** — no UPDATE or DELETE is ever issued against it.

---

### R05 — No Business Rule in JavaScript Only

> **Every business rule enforced in the UI must also be enforced server-side.**

JavaScript validation is UX-only — it improves user experience but is never trusted. The server always re-validates: pricing, credit limits, scheme eligibility, territory exclusivity, stock availability, idempotency. A user bypassing JS validation via `curl` or Postman must get the same rejection.

---

### R06 — Stock Reservation with FOR UPDATE

> **No stock reservation without `SELECT ... FOR UPDATE`, a guarded `UPDATE`, and a reservation row.**

FEFO batch selection and stock decrement follow this exact pattern:
```sql
BEGIN;
SELECT id, quantity_available FROM inventory_batches
  WHERE ... AND quantity_available >= ?
  ORDER BY expiry_date ASC
  FOR UPDATE;            -- Lock the row, no other transaction can read-modify it
UPDATE inventory_batches SET quantity_available = quantity_available - ? WHERE id = ?;
INSERT INTO stock_reservations (...) VALUES (...);
COMMIT;
```
Anything less risks overselling under concurrent load.

---

### R07 — Sequences via SequenceService

> **No invoice, order, or payment number generated using `MAX(id) + 1` or similar.**

`SequenceService` uses a dedicated `sequences` table with pessimistic locking to issue monotonically increasing, gap-free, tenant-scoped numbers. Format: `INV-{franchise_ref}-{year}-{seq}` (e.g., `INV-NTH-2024-001847`).

```php
$invoiceNo = SequenceService::next('invoice', $franchiseRef);
```

---

### R08 — Cross-Tenant Query Requires Explicit Bypass + Audit

> **No cross-tenant query without calling `withoutTenantScope('reason')` and logging to audit.**

`TenantScope` is active by default on all repository methods. To issue a cross-tenant query (e.g., Super Admin aggregation), the caller must explicitly opt out. This opt-out is logged with the actor, reason, and request_id. Unintentional cross-tenant data leakage is prevented by the default-on scope.

---

### R09 — No Color Literals in Views

> **No `#FFFFFF`, `rgb(...)`, `color: red`, or any color value in any view or CSS file. CSS custom properties only.**

All views and component stylesheets reference `var(--color-primary)`, `var(--color-accent)`, etc. The four theme blocks in `crm-ui.css` define the actual values. This ensures that adding a fifth theme or changing a brand color requires editing exactly one place. `cli/lint.php` greps for color literals in the `Views/` and `assets/css/` directories.

---

### R10 — Zero Third-Party Runtime Code

> **No third-party PHP packages, no CDN-loaded JS/CSS, no web fonts from external URLs, no icon fonts.**

All dependencies are either bespoke (PHP) or bundled locally (JS/CSS). This eliminates:
- CDN outage risk
- Supply chain attacks via compromised npm/packagist packages
- GDPR consent requirements for Google Fonts / external font loading
- Internet dependency on cPanel servers (which often have outbound restrictions)

Icons are served from a local SVG sprite (`public/assets/img/icons.svg`).

---

### R11 — No Release Without Rollback Plan and Green Agents

> **Every release must include a tested rollback procedure and all six agents must be green on staging.**

Before any production deployment:
1. Run `php cli/backup.php --verify` on staging.
2. Run `php cli/test-agents.php --all` — all six must be GREEN.
3. Document the rollback steps (schema revert SQL if applicable, file rollback).
4. Only then proceed to production.

---

### R12 — Schema Change = New Full Schema File + Re-Seed on Staging

> **Schema changes ship as a new full `001_full_schema.sql` file, not as incremental patches.**

There is exactly one canonical schema file. When schema changes are needed:
1. Edit `database/schema/001_full_schema.sql` with the change.
2. Run `php cli/migrate.php --fresh && php cli/seed.php --fresh --demo` on staging.
3. Verify all agents green.
4. Deploy to production with the matching `migrate.php --fresh` step documented.

> ⚠️ **Note:** `--fresh` drops all data. Production deployment must include a data migration script if existing data must be preserved.

---

### R13 — Token Storage Rules

> **JWT access token: client memory only. Refresh token: never in localStorage. Access token: never persisted.**

| Token | Allowed Storage | Forbidden Storage |
|---|---|---|
| JWT Access Token | JS variable in memory | `localStorage`, `sessionStorage`, cookie, DB |
| Opaque Refresh Token | `HttpOnly; Secure` cookie OR secure native storage | `localStorage`, JWT payload, plaintext DB column |

Server-side: refresh tokens are stored as **bcrypt hashes** in `refresh_tokens` table. The plaintext is never logged.

---

### R14 — Server-Side Computation of Financial and Business Values

> **Price, scheme discount, territory eligibility, credit limit, and stock availability are always computed server-side.**

The client submits an order with product refs and quantities. The server independently fetches the applicable price list, computes scheme discounts, checks credit limit, checks stock, and produces the final price. Client-submitted prices are **ignored**. This prevents price manipulation via API.

---

### R15 — Every New Endpoint Ships Complete

> **Every new endpoint must ship with: a Policy, a Validator (FormRequest), an Audit write, an agent test case (including IDOR), and an OpenAPI entry.**

No endpoint is considered done until all five are present. The agent test must include:
- Happy path
- Unauthorized access (wrong role)
- IDOR attempt (correct role, wrong tenant's resource)
- Invalid input (validation rejection)

---

### R16 — PDO Configuration

> **PDO emulation OFF, ERRMODE_EXCEPTION, prepared statements only. No raw string interpolation in SQL.**

```php
// Required PDO configuration (enforced in Database.php):
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// All queries use prepared statements:
$stmt = $pdo->prepare('SELECT * FROM leads WHERE id = ? AND franchise_ref = ?');
$stmt->execute([$id, $franchiseRef]);

// Never:
$pdo->query("SELECT * FROM leads WHERE id = $id"); // ← SQL injection risk
```

---

## 16. Test Agents

All test agents are located in `tests/Agents/` and are run via `php cli/test-agents.php`. Every agent is **blocking** — a failure halts the release pipeline.

### Agent Overview

| Agent | File | What It Tests |
|---|---|---|
| **agent-e2e** | `AgentE2e.php` | Full end-to-end flows across all surfaces. |
| **agent-tenancy** | `AgentTenancy.php` | Tenant isolation. Cross-tenant data leakage detection. |
| **agent-duplicates** | `AgentDuplicates.php` | Idempotency and unique constraint enforcement. |
| **agent-concurrency** | `AgentConcurrency.php` | Race conditions on stock, sequences, and credit limits. |
| **agent-security** | `AgentSecurity.php` | Auth, token handling, CSP, rate limiting, input sanitation. |
| **agent-idor** | `AgentIdor.php` | Insecure Direct Object Reference across all resource types. |

### Detailed Test Cases

#### `agent-e2e`
1. Super Admin creates an org and franchise via CLI, then logs in to `/super/*`
2. Franchise Admin creates products, a price list, and a scheme
3. Franchise Admin creates a territory and assigns to distributor
4. Sales executive captures a lead, schedules follow-ups, converts to party
5. Distributor receives portal credentials, logs in, browses catalogue
6. Distributor places an order; server computes correct scheme price
7. Franchise Admin approves order, FEFO inventory picks correct batch
8. Invoice generated with correct GST split (CGST/SGST or IGST)
9. Dispatch created, distributor notified
10. Distributor records payment; credit limit recalculated

#### `agent-tenancy`
11. Franchise A cannot read Franchise B's leads (different `franchise_ref`)
12. Franchise A cannot read Franchise B's orders
13. Franchise A cannot place orders against Franchise B's products
14. Super Admin cross-tenant query is logged to `audit_logs`
15. `withoutTenantScope` without reason string is rejected at code level
16. Row counts in all tenant tables match expected tenant distribution

#### `agent-duplicates`
17. Duplicate order submission with same `Idempotency-Key` returns cached response, no double insert
18. Duplicate lead with same email + franchise is rejected by unique constraint
19. Duplicate invoice number never generated even under concurrent invoice creation
20. Duplicate product SKU within same franchise rejected; same SKU in different franchise allowed
21. Duplicate payment recording for same idempotency key returns original payment record
22. Sequence service never issues the same number twice for the same sequence + franchise

#### `agent-concurrency`
23. 10 concurrent order requests for a product with qty=1 in stock — only one succeeds
24. Concurrent FEFO picks on the same batch — no negative stock
25. Concurrent credit limit checks do not allow limit breach
26. Concurrent invoice number generation — all numbers unique (no gaps, no duplicates)
27. Concurrent refresh token rotation — only one succeeds; others get `401`

#### `agent-security`
28. Expired JWT rejected with `401`
29. Tampered JWT signature rejected with `401`
30. Missing `Authorization` header on protected route returns `401`
31. Rate limiter blocks after N requests per window (login endpoint)
32. `Content-Security-Policy` header present and correct on all HTML responses
33. `X-Frame-Options: DENY` present on all HTML responses
34. Password reset token is single-use; second use returns `400`
35. JWT claims cannot be inflated (role escalation via crafted token)
36. Refresh token cannot be reused after rotation

#### `agent-idor`
37. Sales executive A cannot read Sales executive B's leads (same franchise, different user)
38. Distributor A cannot read Distributor B's invoices (same franchise)
39. Distributor cannot read another franchise's invoices
40. Sales exec cannot access admin-only endpoints
41. Distributor cannot access sales-only endpoints
42. Super Admin cannot accidentally expose cross-tenant data via reports endpoint without explicit bypass
43. Order belonging to Franchise A cannot be approved by Franchise B's admin

---

## 17. Documentation Index

All technical documentation lives in `docs/`. The following table maps topics to their documentation files.

| Category | Document | Path |
|---|---|---|
| **Architecture** | System Architecture Overview | `docs/architecture/system-overview.md` |
| **Architecture** | Database Schema Reference | `docs/architecture/database-schema.md` |
| **Architecture** | DI Container & Bootstrap | `docs/architecture/di-container.md` |
| **Architecture** | Middleware Pipeline | `docs/architecture/middleware-pipeline.md` |
| **Architecture** | MVVM Pattern Guide | `docs/architecture/mvvm-pattern.md` |
| **Business Rules** | Lead & FollowUp Rules | `docs/business-rules/leads-followups.md` |
| **Business Rules** | Pricing & Scheme Computation | `docs/business-rules/pricing-schemes.md` |
| **Business Rules** | FEFO Inventory Rules | `docs/business-rules/fefo-inventory.md` |
| **Business Rules** | GST Billing Rules | `docs/business-rules/gst-billing.md` |
| **Business Rules** | Credit Limit Rules | `docs/business-rules/credit-limits.md` |
| **Business Rules** | Territory Exclusivity Rules | `docs/business-rules/territories.md` |
| **Workflows** | Distributor Onboarding | `docs/workflows/distributor-onboarding.md` |
| **Workflows** | Order-to-Cash | `docs/workflows/order-to-cash.md` |
| **Workflows** | Payment Reconciliation | `docs/workflows/payment-reconciliation.md` |
| **Workflows** | Scheme Auto-Application | `docs/workflows/scheme-auto-apply.md` |
| **Decisions** | ADR-001: No Third-Party Runtime | `docs/decisions/adr-001-no-third-party.md` |
| **Decisions** | ADR-002: Row-Level Tenancy | `docs/decisions/adr-002-row-level-tenancy.md` |
| **Decisions** | ADR-003: File Cache over Redis | `docs/decisions/adr-003-file-cache.md` |
| **Decisions** | ADR-004: JWT + Opaque Refresh | `docs/decisions/adr-004-token-strategy.md` |
| **Decisions** | ADR-005: Full Schema over Migrations | `docs/decisions/adr-005-full-schema.md` |
| **Deployment** | cPanel Deployment Runbook | `docs/deployment/cpanel-runbook.md` |
| **Deployment** | Environment Configuration | `docs/deployment/environment-config.md` |
| **Deployment** | SSL & .htaccess Setup | `docs/deployment/ssl-htaccess.md` |
| **Deployment** | Cron Configuration | `docs/deployment/cron-setup.md` |
| **Runbooks** | Backup & Restore | `docs/runbooks/backup-restore.md` |
| **Runbooks** | Key Rotation | `docs/runbooks/key-rotation.md` |
| **Runbooks** | Tenant Suspension | `docs/runbooks/tenant-suspension.md` |
| **Runbooks** | Incident Response | `docs/runbooks/incident-response.md` |
| **API** | Auth Endpoints | `docs/api/auth.yaml` |
| **API** | Admin API | `docs/api/admin.yaml` |
| **API** | Sales API | `docs/api/sales.yaml` |
| **API** | Portal API | `docs/api/portal.yaml` |
| **API** | Webhook Events Catalog | `docs/api/webhook-events.yaml` |

---

## 18. Change Control

### Making Changes

All significant changes to this project must follow the change control process:

1. **Document First**: Before writing code, document the change in `.ai/decisions/` (for architecture changes) or `docs/business-rules/` (for domain changes).
2. **Update Schema Separately**: Schema changes require a new full `001_full_schema.sql` — edit the file, do not append. See R12.
3. **Add Tests First**: Write the agent test case for the new behavior before implementing it.
4. **Run All Agents**: `php cli/test-agents.php --all` must be green before committing.
5. **Update OpenAPI**: Every new endpoint requires a corresponding entry in the relevant `docs/api/*.yaml` file.
6. **Run Lint**: `php cli/lint.php` must pass with zero errors.

### Engineering Rules Checklist

Use this checklist for every code review:

- [ ] R01: All new tables carry `org_ref` + `franchise_ref`
- [ ] R02: All unique constraints on business data are composite and franchise-led
- [ ] R03: All new POST/PUT/PATCH endpoints pass through `IdempotencyMiddleware`
- [ ] R04: All state-changing operations write to `audit_logs`
- [ ] R05: All business rules enforced server-side (not JS-only)
- [ ] R06: Stock reservations use `FOR UPDATE` + reservation row
- [ ] R07: All sequence numbers issued via `SequenceService`
- [ ] R08: Cross-tenant queries use `withoutTenantScope()` + audit log entry
- [ ] R09: No color literals in views or CSS files
- [ ] R10: No third-party runtime code introduced
- [ ] R11: Rollback procedure documented; agents green on staging
- [ ] R12: Schema changes reflected in full `001_full_schema.sql`
- [ ] R13: Token storage rules respected (no access token persistence)
- [ ] R14: Financial values computed server-side (client values ignored)
- [ ] R15: New endpoints have Policy + Validator + Audit + Agent case + OpenAPI entry
- [ ] R16: PDO emulation OFF, no raw SQL string interpolation

### Version History

| Version | Date | Summary |
|---|---|---|
| v3.0 | 2026-09-20 | CR-Roadmap v3: Full rebuild from scratch with R01-R16 rules, P0-P8 phases, six test agents. |
| v2.x | (internal) | Previous iteration — deprecated. |
| v1.x | (internal) | Prototype — deprecated. |

---

*Last updated: 2026-09-20 · Pharma CRM v3.0 (CR-Roadmap v3)*
