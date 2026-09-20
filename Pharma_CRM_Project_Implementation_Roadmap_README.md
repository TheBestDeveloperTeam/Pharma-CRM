# Pharma CRM & Sales Force Automation
## Full & Final Project Implementation Roadmap / README

> **Project Type:** B2B Pharmaceutical CRM + PCD / Franchise Sales Automation + Order / Inventory / Billing / Dispatch + Distributor Self-Service Portal  
> **Implementation Model:** Custom lightweight Core PHP framework, custom MVVM architecture, no third-party runtime plugins  
> **Frontend:** Server-rendered responsive web UI + progressive enhancement with lightweight custom JavaScript modules  
> **Backend:** Core PHP custom framework + REST-style API layer + background workers / scheduled jobs  
> **API Documentation:** OpenAPI / Swagger-compatible contract, maintained as a first-class project artifact  
> **Database:** Relational SQL database with transaction-safe stock, order, billing and payment logic  
> **UX Target:** Mobile-first, responsive, fast, accessible, keyboard-friendly, low-bandwidth tolerant  
> **Primary Actors:** Admin, Sales Team, Franchise Partner / Distributor, external B2B lead sources  
> **Phase 1 Operational Functions:** Warehouse, Billing and Dispatch are operated under Admin permissions until separate roles are introduced  
> **Business Model:** PCD / Franchise pharmaceutical distribution  
> **Document Status:** Implementation Baseline / Build Roadmap

---

# 1. Purpose of This README

This document is the implementation blueprint for the Pharma CRM and Sales Force Automation system defined by the supplied Master Functional Requirement Specification (FRS).

The implementation goal is not to build a generic CRM with pharmaceutical labels. The product must behave as a purpose-built B2B pharmaceutical operating system centered on:

- franchise / distributor lead acquisition;
- lead ownership and rapid follow-up;
- party onboarding;
- district / pincode territory control;
- pharmaceutical product catalogue;
- B2B pricing and scheme calculations;
- batch-level inventory;
- FEFO allocation;
- order-to-invoice-to-dispatch workflow;
- payment and outstanding management;
- distributor self-service;
- WhatsApp / notification integration;
- B2B lead webhook ingestion;
- reporting;
- auditability;
- strict server-side business-rule enforcement.

The source FRS explicitly establishes these business capabilities and identifies Admin and Sales Team as internal roles, with Franchise Partner / Distributor as an external portal actor. Warehouse, Billing and Dispatch are operational functions under Admin in Phase 1.

**Source baseline:** the supplied FRS describes the project objective, scope and primary actors on its opening pages. fileciteturn0file0L3-L12

This README converts that functional baseline into an engineering delivery plan.

---

# 2. Source Baseline and Engineering Interpretation

## 2.1 Source-derived scope

The FRS defines:

1. Lead and follow-up management.
2. Party / Franchise Partner / Distributor management.
3. District and pincode territory allocation and violation blocking.
4. Product catalogue and CRUD.
5. MRP, PTS and Franchise / Net Rate pricing.
6. Quantity schemes such as 10+1 and 20+3.
7. Order, billing and invoice workflow.
8. Batch-wise inventory and FEFO allocation.
9. Near-expiry alerts.
10. Payments and outstanding.
11. Dispatch, transporter and LR tracking.
12. Distributor self-service portal.
13. WhatsApp notification integration.
14. B2B lead webhook integration.
15. Dashboard, reporting, audit and notifications.

The end-to-end flow in the FRS is:

`Lead Source / Manual Entry`
→ `Lead Creation / Duplicate Check`
→ `Sales Alert`
→ `Call / WhatsApp / Visit Remark`
→ `Next Follow-up`
→ `Interested Decision`
→ `Lead Conversion`
→ `Territory Allocation`
→ `Onboarding / Agreement / Pricing`
→ `Order Validation`
→ `FEFO Batch Allocation`
→ `Billing`
→ `Dispatch / LR`
→ `Distributor Portal / WhatsApp Tracking`
→ `Payment / Outstanding`
→ `Repeat Order / Retention`

Source workflow: fileciteturn0file0L61-L82

## 2.2 Source-derived business rules

The implementation must preserve the FRS rules for:

- active follow-ups requiring next action/date;
- duplicate detection;
- effective-dated territory allocation;
- pre-billing territory validation;
- Admin territory override with reason and audit;
- server-side pricing and scheme calculations;
- historical transactions retaining original pricing;
- FEFO stock allocation;
- exclusion of expired/recalled/quarantined/damaged stock;
- stock reservations for concurrency safety;
- effective-dated pricing;
- idempotent webhook processing;
- payment-safe outstanding updates;
- immutable audit history.

Source rules: fileciteturn0file0L488-L503

## 2.3 Engineering decisions added by this roadmap

The following are implementation decisions introduced to make the source requirements buildable and maintainable:

- custom lightweight Core PHP framework;
- custom MVVM layering;
- domain services between controllers and repositories;
- explicit policy / authorization layer;
- database transactions around financial and inventory state changes;
- optimistic locking and/or row-level locks where supported;
- idempotency keys for retryable write APIs;
- centralized validation;
- centralized business error codes;
- structured application logs;
- immutable audit event records;
- feature configuration instead of hard-coded commercial rules;
- background job abstraction without a framework dependency;
- cron-compatible workers;
- API versioning;
- OpenAPI contract-first documentation;
- deployment health checks;
- database backup / restore runbooks;
- automated regression test suites;
- mobile-first responsive UX;
- progressive enhancement to avoid a heavy JavaScript framework.

These are architecture decisions, not additional commercial requirements. Any commercial behavior not defined by the FRS must be finalized through business decision records before production sign-off.

---

# 3. Product Vision

## 3.1 Product statement

Build a fast, secure and scalable B2B pharmaceutical CRM that connects franchise lead generation, territory-controlled customer onboarding, product / price / scheme management, batch-aware order fulfillment, billing, dispatch, payment follow-up and self-service into one operational workflow.

## 3.2 Primary business outcomes

The system should reduce:

- lead leakage;
- delayed lead responses;
- duplicate sales effort;
- territory disputes;
- manual price calculation errors;
- scheme calculation errors;
- inventory expiry losses;
- order status support calls;
- invoice / dispatch coordination effort;
- outstanding follow-up effort;
- manual spreadsheet dependency.

## 3.3 What the system is not

This implementation is not intended to become:

- a generic enterprise ERP for every industry;
- a replacement for statutory accounting software unless a later scope explicitly adds that requirement;
- an autonomous medical decision system;
- a drug-prescription recommendation engine;
- a patient healthcare application;
- a guaranteed conversion-optimization engine.

The CRM may report business KPIs, but it must never hard-code unsupported performance promises.

---

# 4. Delivery Principles

## 4.1 Core principle

**Server-side business rules are authoritative.**

Frontend checks exist for usability only.

A user must never be able to bypass:

- territory restrictions;
- price eligibility;
- scheme eligibility;
- stock availability;
- credit rules;
- role permissions;
- audit controls;
- state transitions.

The FRS explicitly requires territory, pricing, scheme and FEFO logic to be enforced server-side. fileciteturn0file0L552-L571

## 4.2 Simplicity over framework weight

The custom PHP framework should provide the minimum capabilities required for:

- routing;
- controller lifecycle;
- request / response;
- middleware;
- authentication;
- authorization;
- dependency wiring;
- validation;
- database access;
- transactions;
- serialization;
- logging;
- error handling;
- configuration;
- caching;
- jobs;
- audit;
- API versioning.

Do not recreate a large framework inside the project.

## 4.3 Modular monolith first

The initial architecture should be a **modular monolith**, not microservices.

Reason:

- simpler deployment;
- easier transaction management;
- easier debugging;
- lower infrastructure cost;
- easier local development;
- fewer network failure modes;
- clear module boundaries;
- future extraction possible if scale requires it.

## 4.4 Mobile-first

The most frequent field and operational tasks must work comfortably on:

- 320px+ phone widths;
- 375px common mobile widths;
- 768px tablet;
- 1024px+ desktop;
- wide desktop.

No desktop-only workflow may be mandatory for routine sales operations.

## 4.5 Progressive enhancement

HTML should remain useful without heavy client-side orchestration.

Use JavaScript only for:

- dynamic search;
- asynchronous filters;
- modals / sheets;
- live totals;
- cart interactions;
- inline validation;
- notification updates;
- API-driven widgets;
- upload progress;
- charts / visualizations;
- portal interactions.

---

# 5. Technology Baseline

## 5.1 Backend

- PHP 8.x approved production version, pinned before deployment.
- Custom Core PHP framework.
- PSR-inspired internal interfaces where helpful, without requiring a third-party framework.
- Server-side rendered HTML views.
- Custom REST-style JSON API.
- SQL database connection through a controlled database abstraction.
- Cron / CLI workers for asynchronous operations.

## 5.2 Frontend

Preferred stack:

- semantic HTML5;
- custom CSS;
- CSS variables;
- responsive grid / flexbox;
- progressive JavaScript using ES modules;
- Fetch API;
- browser-native form APIs;
- custom reusable UI components;
- custom modal / drawer / toast patterns;
- no SPA framework dependency.

## 5.3 Database

Preferred default:

- MySQL-compatible relational database;
- InnoDB-equivalent transaction-safe storage engine;
- UTF-8 / utf8mb4;
- foreign keys enabled;
- strict SQL mode where available.

The actual production engine and version must be frozen in the deployment baseline.

## 5.4 API

- HTTPS only in production.
- `/api/v1/...` versioning.
- JSON request / response format.
- consistent pagination.
- consistent validation errors.
- consistent business errors.
- request correlation ID.
- idempotency support where applicable.
- API audit logging for sensitive actions.

## 5.5 API Documentation

The project must maintain:

- `docs/openapi.yaml`
- optional `docs/openapi.json`
- endpoint examples;
- authentication examples;
- error model;
- pagination contract;
- status code contract;
- business error code catalogue.

A third-party Swagger UI package is **not** required by the architecture.

Recommended documentation approach:

1. OpenAPI YAML is the source-of-truth API contract.
2. The repository contains a lightweight local API documentation page if required.
3. Any external Swagger UI assets, if later approved, are self-hosted and version-pinned.
4. The application runtime must not depend on a remote CDN for API documentation.

---

# 6. High-Level Architecture

```text
                         +----------------------------+
                         |       User Browsers        |
                         |----------------------------|
                         | Admin | Sales | Distributor|
                         +-------------+--------------+
                                       |
                                  HTTPS / HTML
                                       |
                              +--------v--------+
                              |  Web Entry     |
                              | public/index.php|
                              +--------+-------+
                                       |
                              +--------v--------+
                              | Router / HTTP   |
                              | Request Kernel  |
                              +--------+--------+
                                       |
                      +----------------+----------------+
                      |                                 |
             +--------v--------+                +-------v--------+
             | Web Controllers |                | API Controllers|
             +--------+--------+                +-------+--------+
                      |                                 |
                      +----------------+----------------+
                                       |
                              +--------v--------+
                              | Application     |
                              | Services        |
                              +--------+--------+
                                       |
             +-------------------------+-------------------------+
             |            |             |             |          |
       +-----v-----+ +----v----+ +------v-----+ +-----v----+ +---v---+
       | Policies  | |Validators| | Domain     | | Jobs     | | Audit |
       | /AuthZ    | | /Rules   | | Services  | | /Workers  | | Event |
       +-----------+ +----------+ +------------+ +----------+ +-------+
                                       |
                              +--------v--------+
                              | Repositories    |
                              | Query Services  |
                              +--------+--------+
                                       |
                              +--------v--------+
                              | SQL Database    |
                              +-----------------+

External:
  B2B Lead Source ---> Secure Webhook ---> Webhook Service ---> Lead Module
  WhatsApp Provider <--> Notification Adapter
  Transport Tracking <--> URL / Provider Adapter if enabled
```

---

# 7. Custom MVVM Architecture

## 7.1 MVVM interpretation for server-rendered PHP

The architecture will use:

### Model

Contains:

- entities;
- value objects;
- repositories;
- database mappings;
- domain state;
- business services.

### ViewModel

Contains:

- page-specific display models;
- normalized form values;
- dashboard card data;
- table row models;
- formatted status labels;
- permissions for UI;
- computed display flags.

The ViewModel must not execute database queries directly.

### View

Contains:

- HTML structure;
- reusable partials;
- template components;
- form controls;
- tables;
- status badges;
- responsive layouts.

The View should not implement business rules.

## 7.2 Controller responsibility

Controllers are orchestration points.

A controller may:

1. authenticate request;
2. resolve route parameters;
3. validate request;
4. call an application service;
5. build a ViewModel or API response;
6. render the view or return JSON.

Controllers must not:

- write SQL;
- perform FEFO allocation;
- calculate commercial schemes;
- decide permissions manually;
- alter inventory directly;
- update multiple tables without a service transaction.

---

# 8. Recommended Directory Structure

```text
project/
├── app/
│   ├── Config/
│   │   ├── app.php
│   │   ├── database.php
│   │   ├── auth.php
│   │   ├── security.php
│   │   ├── cache.php
│   │   ├── queue.php
│   │   └── integrations.php
│   │
│   ├── Core/
│   │   ├── Application.php
│   │   ├── Container.php
│   │   ├── Router.php
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Session.php
│   │   ├── Validation.php
│   │   ├── Database.php
│   │   ├── Transaction.php
│   │   ├── Logger.php
│   │   ├── Cache.php
│   │   ├── EventBus.php
│   │   ├── JobRunner.php
│   │   ├── Idempotency.php
│   │   └── Exceptions/
│   │
│   ├── Http/
│   │   ├── Middleware/
│   │   ├── Controllers/
│   │   │   ├── Web/
│   │   │   └── Api/
│   │   └── FormRequests/
│   │
│   ├── Domain/
│   │   ├── Auth/
│   │   ├── Users/
│   │   ├── Leads/
│   │   ├── FollowUps/
│   │   ├── Parties/
│   │   ├── Territories/
│   │   ├── Products/
│   │   ├── Pricing/
│   │   ├── Schemes/
│   │   ├── Orders/
│   │   ├── Inventory/
│   │   ├── Billing/
│   │   ├── Dispatch/
│   │   ├── Payments/
│   │   ├── Notifications/
│   │   ├── WhatsApp/
│   │   ├── Webhooks/
│   │   ├── Reports/
│   │   └── Audit/
│   │
│   ├── Repositories/
│   │   ├── Contracts/
│   │   └── Sql/
│   │
│   ├── Policies/
│   ├── Validators/
│   ├── ViewModels/
│   ├── Views/
│   │   ├── layouts/
│   │   ├── components/
│   │   ├── auth/
│   │   ├── dashboard/
│   │   ├── leads/
│   │   ├── followups/
│   │   ├── parties/
│   │   ├── territories/
│   │   ├── products/
│   │   ├── pricing/
│   │   ├── schemes/
│   │   ├── orders/
│   │   ├── inventory/
│   │   ├── billing/
│   │   ├── dispatch/
│   │   ├── payments/
│   │   ├── reports/
│   │   └── portal/
│   │
│   └── Support/
│
├── bootstrap/
│   ├── app.php
│   ├── routes.php
│   ├── middleware.php
│   └── bindings.php
│
├── cli/
│   ├── worker.php
│   ├── scheduler.php
│   ├── backup.php
│   └── maintenance.php
│
├── config/
│
├── database/
│   ├── migrations/
│   ├── seeds/
│   ├── fixtures/
│   └── queries/
│
├── docs/
│   ├── architecture/
│   ├── business-rules/
│   ├── workflows/
│   ├── test-cases/
│   ├── deployment/
│   ├── runbooks/
│   ├── api/
│   │   ├── openapi.yaml
│   │   └── examples/
│   └── decisions/
│
├── public/
│   ├── index.php
│   ├── assets/
│   │   ├── css/
│   │   ├── js/
│   │   ├── img/
│   │   └── icons/
│   ├── uploads/
│   └── robots.txt
│
├── storage/
│   ├── logs/
│   ├── cache/
│   ├── sessions/
│   ├── exports/
│   ├── temporary/
│   └── private/
│
├── tests/
│   ├── Unit/
│   ├── Feature/
│   ├── Integration/
│   ├── Security/
│   ├── API/
│   └── Performance/
│
├── .env.example
├── .gitignore
├── README.md
└── composer.json
```

## 8.1 No third-party plugin policy

The project should avoid runtime dependence on third-party plugins.

This does not prevent the use of PHP's standard runtime extensions or operating-system capabilities.

Every external dependency must be classified as:

- mandatory runtime dependency;
- build-time dependency;
- test-only dependency;
- optional integration dependency;
- rejected dependency.

For the core application:

**Allowed:**

- PHP core;
- standard PHP extensions;
- database driver;
- web server;
- cron;
- operating-system process scheduler;
- mail transport supplied by infrastructure;
- officially documented external service APIs.

**Avoid unless specifically approved:**

- full-stack frameworks;
- ORM packages;
- UI frameworks;
- admin panel packages;
- workflow engines;
- plugin-heavy authentication frameworks;
- SaaS-only CMS dependencies;
- third-party page builders.

---

# 9. Repository and Layer Rules

## 9.1 Dependency direction

```text
View
  ↓
ViewModel
  ↓
Controller
  ↓
Application Service
  ↓
Domain Service / Policy / Validator
  ↓
Repository Contract
  ↓
SQL Repository
  ↓
Database
```

External integrations:

```text
Domain/Application Service
        ↓
Integration Contract
        ↓
Provider Adapter
```

## 9.2 Forbidden dependency directions

- View → SQL
- View → repository
- Controller → raw SQL
- JavaScript → database
- Webhook controller → direct business table mutation
- Repository → HTML
- Domain service → HTTP request object
- Business rule → frontend-only implementation.

---

# 10. Core Modules

## 10.1 Authentication and Authorization

Features:

- Admin login;
- Sales Team login;
- Distributor portal login;
- logout;
- password reset;
- session management;
- account activation/deactivation;
- failed login tracking;
- authorization policies;
- route-level permissions;
- object-level ownership checks.

Recommended authorization model:

```text
Role
  └── Permission
        └── Policy Scope
              ├── Global
              ├── Assigned
              ├── Own
              └── Portal-Owned
```

## 10.2 Lead Management

The FRS requires:

- contact name;
- firm name;
- mobile / WhatsApp;
- email;
- state / district / city / pincode;
- lead source;
- interested products/categories;
- business type;
- assigned Sales Team;
- priority;
- status;
- initial remark;
- next follow-up date/time.

Lead actions:

- create;
- view;
- edit;
- archive;
- assign/reassign;
- add follow-up;
- add remark;
- history;
- convert to party.

Source: fileciteturn0file0L106-L132

Implementation requirements:

- duplicate detection service;
- configurable identifiers;
- phone normalization;
- email normalization;
- source normalization;
- external lead ID;
- ownership history;
- status transition log;
- conversion snapshot;
- import-safe idempotency.

## 10.3 Follow-up

Every active follow-up must have:

- next action;
- next date/time.

Actions:

- add;
- edit;
- complete;
- reschedule;
- missed;
- close;
- add remark;
- call;
- visit;
- WhatsApp activity.

Rule:

```text
ACTIVE FOLLOW-UP
    => next_action != null
    AND next_follow_up_at != null
```

Except:

- Lost;
- Rejected;
- Converted.

Source: fileciteturn0file0L135-L145

## 10.4 Lead Webhook / Automated Nurturing

Webhook processing stages:

```text
Receive
  ↓
Authentication / Signature Verification
  ↓
Request Size Validation
  ↓
Content-Type Validation
  ↓
Schema Validation
  ↓
External Event ID Check
  ↓
Idempotency Check
  ↓
Normalize
  ↓
Duplicate Business Check
  ↓
Lead Creation / Update
  ↓
Assignment
  ↓
CRM Notification
  ↓
Optional approved message
  ↓
Audit + Integration Log
  ↓
200/202 Response
```

Required behavior:

- store external lead ID;
- store source;
- idempotent processing;
- retry failed jobs with controlled backoff;
- failure log;
- first-response SLA measurement.

Source: fileciteturn0file0L146-L157

## 10.5 Party / Franchise Partner

Required capabilities:

- create;
- edit;
- view;
- archive;
- restore;
- activate/deactivate;
- firm details;
- GSTIN;
- applicable drug license details;
- billing address;
- shipping address;
- state / district / city / area / pincode;
- territory assignment;
- Sales Team assignment;
- pricing tier;
- agreement / monopoly dates;
- credit limit;
- payment terms;
- opening outstanding;
- product interests;
- order;
- payment;
- follow-up;
- remarks.

Source: fileciteturn0file0L158-L175

## 10.6 Territory and Pincode

A party may have one or more effective-dated:

- districts;
- pincodes.

Shipping pincode is validated:

1. during order creation;
2. again before billing / final submission.

Rules:

- do not trust free-text district;
- use pincode master mapping;
- configurable unassigned behavior;
- Admin override requires reason;
- every override is audited;
- historical orders remain unchanged.

Source: fileciteturn0file0L179-L197

## 10.7 Product Management

Fields include:

- SKU/Product Code;
- name;
- composition;
- pack size;
- dosage form;
- category;
- MRP;
- PTS;
- default Franchise/Net Rate;
- GST;
- scheme eligibility;
- storage requirement;
- shelf-life.

Capabilities:

- create;
- edit;
- view;
- activate/deactivate;
- archive;
- delete only when transaction references do not exist.

Source: fileciteturn0file0L198-L217

## 10.8 Pricing Matrix

Required:

- MRP;
- PTS;
- Franchise/Net Rate;
- effective dates;
- party-specific rates;
- tier-specific rates;
- overlap priority;
- historical price snapshot;
- Admin-only manual override;
- override reason.

Source: fileciteturn0file0L220-L240

Pricing resolution algorithm:

```text
Input:
  party
  product
  quantity
  transaction_date

1. Load active party.
2. Resolve pricing tier.
3. Find party-specific active rate.
4. If absent, find tier-specific rate.
5. If absent, use configured product default.
6. Validate effective_from <= date <= effective_to.
7. Resolve overlapping rules by explicit priority.
8. Return selected rate + source rule ID.
9. Freeze rate into order item at transaction confirmation.
```

## 10.9 Scheme Engine

Example:

```text
10 paid + 1 free
20 paid + 3 free
```

Scheme must be data-driven.

Fields:

- scheme;
- product;
- start date;
- end date;
- min quantity;
- max quantity;
- paid quantity;
- free quantity;
- stacking behavior;
- priority;
- customer/tier eligibility.

Calculation:

```text
qualifying_sets = floor(ordered_qty / min_qty)
free_qty = qualifying_sets * free_qty_per_set
```

The exact scheme rules remain configurable.

Source: fileciteturn0file0L228-L240

## 10.10 Order Management

Order states:

```text
DRAFT
→ VALIDATING
→ SUBMITTED
→ CONFIRMED
→ BILLING_PENDING
→ BILLED
→ PACKING
→ DISPATCH_READY
→ DISPATCHED
→ DELIVERED
→ COMPLETED
```

Exceptional states:

```text
CANCELLED
REJECTED
ON_HOLD
FAILED
```

Before confirmation:

- active party;
- territory;
- price;
- schemes;
- stock;
- credit rules;
- address;
- item validity.

Source: fileciteturn0file0L241-L262

## 10.11 Inventory and Batch Management

Batch data:

- batch number;
- manufacturing date;
- expiry date;
- received quantity;
- available quantity;
- reserved quantity;
- damaged/returned quantity;
- warehouse/location.

Movements:

- receipt;
- sale/dispatch;
- reservation;
- return;
- damage;
- expiry;
- adjustment;
- transfer.

Source: fileciteturn0file0L265-L281

## 10.12 FEFO

The supplied FRS explicitly corrects plain FIFO to FEFO.

Rule:

> First Expiry, First Out.

Allocation criteria:

1. product match;
2. active inventory;
3. not expired;
4. not recalled;
5. not quarantined;
6. not damaged;
7. sufficient available quantity;
8. optional minimum remaining shelf-life;
9. order by earliest expiry;
10. then deterministic secondary sort by batch ID/date.

Source: fileciteturn0file0L282-L291

## 10.13 Stock Reservation

Concurrency risk:

```text
Order A sees 100 units.
Order B sees 100 units.
Both reserve 80.
Actual stock = 100.
```

This must never lead to 160 committed units.

Required approach:

- transaction;
- row lock / safe update;
- reserve quantity;
- verify available - reserved;
- commit;
- release on cancellation / expiry.

Pseudo-flow:

```text
BEGIN
  SELECT eligible batches FOR UPDATE
  FOR each batch:
      reservable = available_qty - reserved_qty
      reserve = MIN(required_qty, reservable)
      UPDATE reserved_qty
  IF remaining > 0:
      ROLLBACK or use configured partial-order policy
  ELSE:
      create stock reservations
      COMMIT
```

## 10.14 Near-Expiry Management

Default dashboard alert:

- six months.

Configurable:

- 180 days;
- 90 days;
- 60 days;
- 30 days.

Show:

- product;
- batch;
- expiry;
- available quantity;
- stock value.

Source: fileciteturn0file0L292-L298

## 10.15 Billing

Invoice contains:

- invoice number;
- customer;
- shipping;
- product;
- batch;
- billed quantity;
- free quantity;
- rate;
- discount;
- GST;
- totals.

Capabilities:

- print;
- PDF-ready print layout;
- cancellation;
- audit.

Historical invoice amounts must not change when master pricing changes.

Source: fileciteturn0file0L299-L310

## 10.16 Dispatch

Fields:

- dispatch status;
- dispatch date;
- transporter;
- LR number;
- tracking URL;
- boxes / units;
- remarks.

Workflow:

```text
Confirmed
→ Billing
→ FEFO Allocation
→ Packed
→ Dispatch
→ Transporter/LR
→ Portal Update
→ WhatsApp Tracking
→ Delivered
```

Source: fileciteturn0file0L311-L321

## 10.17 Distributor Portal

Portal capabilities:

- dashboard;
- catalogue;
- applicable price;
- cart;
- order placement;
- order history;
- order timeline;
- invoice;
- dispatch details;
- transporter;
- LR;
- tracking;
- outstanding;
- payment status;
- profile;
- shipping address;
- support.

Source: fileciteturn0file0L322-L335

## 10.18 WhatsApp and Notifications

The system should integrate only through an approved official provider API.

Message lifecycle:

```text
QUEUED
→ SENT
→ DELIVERED
→ READ

OR

QUEUED
→ FAILED
```

Store provider message ID where available.

Required events:

- new lead;
- follow-up reminder;
- order confirmation;
- billing;
- dispatch/LR;
- payment reminder;
- payment received;
- near-expiry admin alert.

Source: fileciteturn0file0L336-L350

## 10.19 Payments and Outstanding

Payment:

- date;
- amount;
- mode;
- reference;
- remarks.

Accounting relationship:

```text
Payment
  └── Allocation
       ├── Invoice A
       ├── Invoice B
       └── Advance / Unallocated
```

Outstanding must be transaction-safe.

Never update an outstanding balance with blind arithmetic outside a transaction.

Recommended calculation:

```text
invoice_total
- allocated_payment_total
= invoice_outstanding
```

Party outstanding:

```text
SUM(invoice_outstanding)
+ approved debit adjustments
- approved credit adjustments
- unapplied credits according to policy
```

---

# 11. Database Design Blueprint

## 11.1 Core entities

Source FRS lists the following suggested entities:

```text
users
leads
lead_activities
follow_ups
parties
party_territories
states
districts
cities
pincodes
products
product_categories
pricing_tiers
product_prices
schemes
scheme_rules
orders
order_items
invoices
invoice_items
inventory_batches
inventory_movements
stock_reservations
dispatches
transporters
payments
payment_allocations
notifications
whatsapp_messages
webhook_sources
webhook_events
audit_logs
```

Source: fileciteturn0file0L517-L551

## 11.2 Additional infrastructure tables

Recommended technical tables:

```text
roles
permissions
role_permissions
user_roles
user_sessions
password_resets
login_attempts
api_tokens
api_idempotency_keys
request_logs
job_queue
job_attempts
system_settings
notification_templates
files
file_links
entity_status_history
integration_logs
failed_jobs
data_exports
report_runs
sequence_counters
```

## 11.3 Common columns

Most transactional tables should include:

```text
id
created_at
created_by
updated_at
updated_by
deleted_at
deleted_by
version
```

Do not add unnecessary soft-delete fields to immutable ledger-like records.

## 11.4 Primary keys

Preferred:

- BIGINT unsigned auto-increment for internal relational simplicity;

or

- UUID/ULID only where distributed identity exposure or external correlation truly benefits.

Avoid UUID everywhere if there is no technical requirement; large indexed relational tables benefit from compact keys.

---

# 12. Database Integrity Rules

## 12.1 Foreign keys

Use foreign keys for core relational integrity.

## 12.2 Unique indexes

Potential unique constraints:

```text
users.email
users.username
products.sku
parties.gstin where configured and not empty
external lead source + external lead id
warehouse + batch + product where business definition permits
invoice number
order number
payment receipt/reference where uniqueness is explicitly required
```

Do not create a database unique constraint for a business identifier if the actual rule is configurable and supports duplicates under controlled conditions.

## 12.3 Numeric precision

Use fixed decimal types for money.

Example:

```sql
DECIMAL(18,2)
```

or the approved financial precision used by the client.

Never use floating-point fields for money.

## 12.4 Dates

Use:

- `DATE` for business dates;
- `DATETIME` / `TIMESTAMP` for event time;
- UTC storage where practical;
- application timezone for display.

Document the canonical business timezone.

---

# 13. Transaction Boundaries

## 13.1 Order confirmation transaction

A single logical transaction should cover:

- order validation;
- price snapshot;
- scheme calculation;
- stock reservation;
- order status change;
- audit event.

If any required step fails, the transaction must not leave a partially committed order.

## 13.2 Billing transaction

Must cover:

- invoice creation;
- invoice item snapshot;
- batch allocation;
- stock movement;
- reservation consumption;
- order state update;
- audit.

## 13.3 Payment transaction

Must cover:

- payment record;
- allocation;
- outstanding impact;
- audit;
- notification event creation.

## 13.4 Territory override transaction

Must cover:

- override action;
- order transition;
- reason;
- user ID;
- audit event.

---

# 14. Business Rule Engine

The application must centralize rules into explicit services.

Examples:

```text
LeadDuplicateService
LeadAssignmentService
FollowUpPolicy
TerritoryValidationService
TerritoryOverridePolicy
PriceResolutionService
SchemeCalculationService
CreditRuleService
StockAvailabilityService
StockReservationService
FefoAllocationService
OrderValidationService
BillingService
DispatchService
OutstandingService
NotificationService
WebhookProcessingService
AuditService
```

Each service should expose deterministic operations.

Example:

```php
$result = $territoryService->validateOrderTerritory(
    partyId: $partyId,
    shippingPincodeId: $pincodeId,
    effectiveAt: $orderDate
);
```

The service returns a domain result rather than writing directly to the HTTP response.

---

# 15. State Machines

## 15.1 Lead state machine

Example baseline:

```text
NEW
ASSIGNED
CONTACTED
INTERESTED
FOLLOW_UP
DOCUMENTS_PENDING
QUALIFIED
CONVERTED
LOST
REJECTED
ARCHIVED
```

Transitions must be configurable only where business-approved.

Every transition should record:

- from status;
- to status;
- actor;
- time;
- reason where required.

## 15.2 Order state machine

```text
DRAFT
SUBMITTED
UNDER_REVIEW
CONFIRMED
ON_HOLD
BILLING_PENDING
BILLED
PACKED
DISPATCH_READY
DISPATCHED
DELIVERED
COMPLETED
CANCELLED
REJECTED
```

## 15.3 Payment status

```text
RECORDED
PARTIALLY_ALLOCATED
ALLOCATED
REVERSED
CANCELLED
```

## 15.4 Dispatch status

```text
PENDING
PACKING
READY
DISPATCHED
IN_TRANSIT
DELIVERED
FAILED
RETURNED
```

The exact commercial lifecycle must be confirmed during Definition of Ready.

---

# 16. API Architecture

## 16.1 Base URL

```text
https://your-domain.example/api/v1
```

## 16.2 API response envelope

Success example:

```json
{
  "success": true,
  "data": {
    "id": 123
  },
  "meta": {
    "request_id": "req_..."
  }
}
```

Collection:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "request_id": "req_...",
    "page": 1,
    "per_page": 25,
    "total": 250,
    "pages": 10
  }
}
```

Validation error:

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "One or more fields are invalid.",
    "fields": {
      "mobile": [
        "Mobile number is required."
      ]
    }
  },
  "meta": {
    "request_id": "req_..."
  }
}
```

Business-rule error:

```json
{
  "success": false,
  "error": {
    "code": "TERRITORY_NOT_ALLOWED",
    "message": "Shipping pincode is outside the active territory."
  }
}
```

## 16.3 HTTP status policy

```text
200 OK
201 Created
202 Accepted
204 No Content
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
429 Too Many Requests
500 Internal Server Error
502/503 Integration or availability error
```

Never expose PHP stack traces to clients.

---

# 17. API Endpoint Catalogue

## 17.1 Authentication

```text
POST   /auth/login
POST   /auth/logout
POST   /auth/refresh
POST   /auth/forgot-password
POST   /auth/reset-password
GET    /auth/me
```

## 17.2 Users and roles

```text
GET    /users
POST   /users
GET    /users/{id}
PATCH  /users/{id}
POST   /users/{id}/activate
POST   /users/{id}/deactivate

GET    /roles
GET    /permissions
```

## 17.3 Leads

```text
GET    /leads
POST   /leads
GET    /leads/{id}
PATCH  /leads/{id}
POST   /leads/{id}/assign
POST   /leads/{id}/convert
POST   /leads/{id}/archive
GET    /leads/{id}/activities
POST   /leads/{id}/remarks
GET    /leads/{id}/follow-ups
```

## 17.4 Follow-ups

```text
GET    /follow-ups
POST   /follow-ups
GET    /follow-ups/{id}
PATCH  /follow-ups/{id}
POST   /follow-ups/{id}/complete
POST   /follow-ups/{id}/reschedule
POST   /follow-ups/{id}/miss
```

## 17.5 Parties

```text
GET    /parties
POST   /parties
GET    /parties/{id}
PATCH  /parties/{id}
POST   /parties/{id}/activate
POST   /parties/{id}/deactivate
GET    /parties/{id}/ledger
GET    /parties/{id}/orders
GET    /parties/{id}/payments
```

## 17.6 Territory

```text
GET    /territories
POST   /territories
GET    /territories/{id}
PATCH  /territories/{id}
GET    /pincodes/{pincode}/allocation
POST   /territories/validate
POST   /territories/override
GET    /parties/{id}/territories
```

## 17.7 Products

```text
GET    /products
POST   /products
GET    /products/{id}
PATCH  /products/{id}
POST   /products/{id}/activate
POST   /products/{id}/deactivate
```

## 17.8 Pricing

```text
GET    /pricing/tiers
POST   /pricing/tiers
GET    /prices
POST   /prices
PATCH  /prices/{id}
POST   /pricing/resolve
```

## 17.9 Schemes

```text
GET    /schemes
POST   /schemes
GET    /schemes/{id}
PATCH  /schemes/{id}
POST   /schemes/calculate
```

## 17.10 Orders

```text
GET    /orders
POST   /orders
GET    /orders/{id}
PATCH  /orders/{id}
POST   /orders/{id}/submit
POST   /orders/{id}/confirm
POST   /orders/{id}/cancel
POST   /orders/{id}/reserve
POST   /orders/{id}/release-reservation
```

## 17.11 Inventory

```text
GET    /inventory/batches
POST   /inventory/receipts
GET    /inventory/batches/{id}
POST   /inventory/reserve
POST   /inventory/release
POST   /inventory/adjust
POST   /inventory/transfer
POST   /inventory/allocate-fefo
GET    /inventory/near-expiry
```

## 17.12 Billing

```text
GET    /invoices
POST   /invoices
GET    /invoices/{id}
POST   /invoices/{id}/cancel
GET    /invoices/{id}/print
GET    /invoices/{id}/pdf
```

## 17.13 Dispatch

```text
GET    /dispatches
POST   /dispatches
GET    /dispatches/{id}
PATCH  /dispatches/{id}
POST   /dispatches/{id}/dispatch
POST   /dispatches/{id}/deliver
```

## 17.14 Payments

```text
GET    /payments
POST   /payments
GET    /payments/{id}
PATCH  /payments/{id}
POST   /payments/{id}/allocate
POST   /payments/{id}/reverse
```

## 17.15 Notifications

```text
GET    /notifications
POST   /notifications/{id}/read
POST   /notifications/{id}/retry
```

## 17.16 Webhooks

```text
POST   /webhooks/{source}/leads
POST   /webhooks/{source}/status
```

## 17.17 Reports

```text
GET /reports/leads
GET /reports/follow-ups
GET /reports/conversion
GET /reports/sales
GET /reports/territory
GET /reports/products
GET /reports/schemes
GET /reports/inventory
GET /reports/near-expiry
GET /reports/outstanding
GET /reports/dispatch
GET /reports/webhooks
GET /reports/whatsapp
```

---

# 18. Swagger / OpenAPI Contract

## 18.1 Required documentation files

```text
/docs/api/openapi.yaml
/docs/api/examples/
```

The OpenAPI document must include:

- API title;
- version;
- server URL;
- authentication;
- tags;
- endpoint paths;
- parameters;
- request schemas;
- response schemas;
- business errors;
- validation errors;
- pagination;
- webhook contracts;
- examples.

## 18.2 Security scheme

Preferred:

```yaml
components:
  securitySchemes:
    bearerAuth:
      type: http
      scheme: bearer
```

For browser session endpoints, use secure server sessions.

Do not store long-lived authentication tokens in localStorage.

## 18.3 OpenAPI tagging

Recommended tags:

```text
Auth
Users
Leads
FollowUps
Parties
Territories
Products
Pricing
Schemes
Orders
Inventory
Billing
Dispatch
Payments
Notifications
WhatsApp
Webhooks
Reports
Audit
```

## 18.4 Swagger validation gate

Every release candidate must verify:

- all API routes documented;
- request schema matches implementation;
- response schema matches implementation;
- authentication rules match;
- error codes match;
- no undocumented production endpoint;
- no obsolete endpoint remains documented.

---

# 19. Authentication Security

## 19.1 Session security

Use:

- Secure cookies;
- HttpOnly;
- SameSite;
- session rotation after authentication;
- session timeout;
- inactivity timeout;
- server-side invalidation;
- logout invalidation;
- session fixation protection.

## 19.2 Password rules

Store passwords using a modern PHP password hashing API.

Never:

- store plain password;
- store reversible encrypted password;
- log passwords;
- email raw passwords.

## 19.3 Brute-force controls

Track:

- IP;
- user;
- timestamp;
- outcome.

Implement:

- progressive delay;
- rate limits;
- temporary lock;
- administrative unlock process.

---

# 20. Authorization Design

## 20.1 Admin

The FRS gives Admin full access to:

- users;
- masters;
- territories;
- pricing;
- products;
- inventory;
- orders;
- payments;
- dispatch;
- reports;
- overrides;
- audit.

## 20.2 Sales Team

Sales Team can access:

- own / assigned leads;
- follow-ups;
- eligible parties;
- own activity;
- permitted orders/payments.

Sales Team cannot manage:

- products;
- pricing;
- masters;
- territory overrides.

## 20.3 Distributor Portal

Distributor can:

- view own account;
- view applicable catalogue;
- view applicable pricing;
- place own orders;
- view own invoices;
- view dispatch;
- view own outstanding;
- view own payments/status.

Source permission matrix: fileciteturn0file0L425-L484

---

# 21. Security Architecture

## 21.1 Application perimeter

Production:

```text
Internet
  ↓
TLS
  ↓
Web Server
  ↓
PHP Application
  ↓
Database
```

Database must never be directly exposed to the public internet.

## 21.2 Input security

Validate:

- type;
- length;
- encoding;
- requiredness;
- domain constraints;
- enum values;
- numeric range;
- dates;
- ownership;
- file extension;
- MIME;
- upload size.

## 21.3 SQL security

- prepared statements;
- parameter binding;
- never concatenate raw user values into SQL;
- whitelist sort fields;
- whitelist filter fields;
- limit maximum page size.

## 21.4 HTML security

Escape output by default.

Only allow raw HTML through explicit safe-render methods.

## 21.5 CSRF

All cookie-authenticated state-changing web requests require CSRF protection.

API calls using bearer authentication must have an appropriate anti-replay / origin strategy.

## 21.6 CORS

Do not use:

```text
Access-Control-Allow-Origin: *
```

for authenticated production APIs.

Use explicit origins.

## 21.7 File uploads

Documents may be required for party onboarding.

Controls:

- allowed types;
- max size;
- MIME verification;
- random storage filename;
- private storage;
- download authorization;
- antivirus / malware scanning where infrastructure permits;
- no execution permission;
- no direct script access.

---

# 22. Audit System

## 22.1 Auditable events

Audit:

- login/security events;
- lead assignment;
- lead conversion;
- party changes;
- territory changes;
- territory override;
- price changes;
- scheme changes;
- order confirmation;
- order cancellation;
- stock adjustment;
- batch status;
- invoice cancellation;
- dispatch changes;
- payment changes;
- payment reversal;
- user role changes;
- permission changes;
- integration configuration.

## 22.2 Audit structure

```text
audit_logs
------------
id
actor_type
actor_id
action
entity_type
entity_id
before_json
after_json
reason
ip_address
user_agent
request_id
created_at
```

Audit should be append-only from application behavior.

---

# 23. Request Tracing

Every HTTP request should receive:

```text
X-Request-ID
```

or equivalent.

Log:

- request ID;
- user ID;
- route;
- method;
- status;
- duration;
- IP;
- error code;
- integration ID where applicable.

Never log:

- password;
- session secret;
- API secret;
- payment credentials;
- sensitive token values.

---

# 24. Logging Strategy

Log levels:

```text
DEBUG
INFO
NOTICE
WARNING
ERROR
CRITICAL
```

Production default:

```text
INFO / WARNING / ERROR / CRITICAL
```

Structured log example:

```json
{
  "level": "ERROR",
  "timestamp": "2026-01-01T00:00:00Z",
  "request_id": "req_123",
  "user_id": 99,
  "module": "inventory",
  "action": "fefo_allocate",
  "error_code": "STOCK_RESERVATION_CONFLICT",
  "message": "Unable to reserve requested quantity."
}
```

---

# 25. Notification Architecture

Use an internal notification event abstraction:

```text
Business Event
    ↓
Notification Dispatcher
    ├── In-App Notification
    ├── WhatsApp Adapter
    ├── Email Adapter if approved
    └── Future channel
```

This prevents business logic from depending directly on WhatsApp API details.

---

# 26. Webhook Architecture

## 26.1 Source onboarding

For every external source:

```text
WebhookSource
  ├── source_name
  ├── endpoint_identifier
  ├── authentication_type
  ├── secret_reference
  ├── active
  └── configuration
```

## 26.2 Event table

```text
webhook_events
---------------
id
source_id
external_event_id
request_hash
payload_json
signature
status
attempt_count
received_at
processed_at
error_code
error_message
```

## 26.3 Idempotency

Processing key:

```text
(source_id, external_event_id)
```

If external event ID is unavailable:

```text
source + normalized payload hash + bounded time window
```

The fallback must be documented because payload-hash deduplication can produce false positives or negatives.

## 26.4 Retry policy

Example:

```text
Attempt 1: immediate
Attempt 2: +30 sec
Attempt 3: +2 min
Attempt 4: +10 min
Attempt 5: +30 min
```

Do not retry permanent validation failures forever.

---

# 27. FEFO Algorithm Detailed

Input:

```text
product_id
warehouse_id
required_qty
minimum_remaining_shelf_life_days
```

Eligibility:

```text
available_qty > 0
expiry_date >= today + minimum_remaining_shelf_life_days
status = SALEABLE
quarantine = false
recalled = false
expired = false
```

Sort:

```text
expiry_date ASC
manufacturing_date ASC
batch_id ASC
```

Pseudo-code:

```text
remaining = required_qty
allocations = []

BEGIN TRANSACTION

lock eligible batch rows

for batch in sorted eligible batches:
    available = batch.available_qty - batch.reserved_qty

    if available <= 0:
        continue

    allocation = min(available, remaining)

    create reservation(batch, allocation)
    increment reserved_qty by allocation

    append allocation to result

    remaining -= allocation

    if remaining == 0:
        break

if remaining > 0:
    rollback or apply configured partial-order policy
else:
    commit

return allocations
```

Never:

- allocate expired batch;
- bypass recalled/quarantined stock;
- over-reserve;
- rely on frontend stock quantity.

---

# 28. Pricing Resolution Algorithm

Inputs:

```text
party_id
product_id
transaction_date
quantity
```

Sequence:

```text
1. Validate active party.
2. Resolve party pricing tier.
3. Resolve party-specific active rate.
4. Else resolve tier-specific active rate.
5. Else product default rate.
6. Resolve effective dates.
7. Resolve priority for overlap.
8. Return price and rule source.
9. Store immutable order-item price snapshot at confirmation.
```

Historical orders must not be recalculated because a master price changed.

---

# 29. Scheme Calculation Algorithm

Example rule:

```text
buy_qty = 10
free_qty = 1
order_qty = 26
```

Calculation:

```text
sets = floor(26 / 10) = 2
free = 2 * 1 = 2
paid = 26
total fulfillment = 28
```

The UI must show:

```text
Paid Qty: 26
Free Qty: 2
Total Fulfillment Qty: 28
```

Never hide free quantity inside paid quantity.

For stacked schemes:

1. identify eligible schemes;
2. sort by explicit priority;
3. evaluate stacking rule;
4. apply only according to configured behavior;
5. record applied scheme IDs on transaction.

---

# 30. Territory Validation Algorithm

Inputs:

```text
party_id
shipping_pincode
transaction_date
```

Sequence:

```text
1. Normalize pincode.
2. Resolve pincode master.
3. Resolve district through master relation.
4. Load party active territory assignments.
5. Filter by effective dates.
6. Match pincode-level allocation.
7. If not found, check district-level allocation.
8. If found => ALLOWED.
9. Else apply unassigned policy:
   - BLOCK
   - ADMIN_REVIEW
   - APPROVAL_REQUIRED
10. If Admin override:
   - reason required;
   - actor recorded;
   - audit event written.
```

Territory checks must occur both:

```text
Order Creation
Billing / Final Submission
```

---

# 31. Outstanding Calculation

Recommended transactional model:

```text
Invoice
  ↓
Invoice Outstanding
  ↓
Payment Allocation
  ↓
Remaining Outstanding
```

Never mutate an invoice total after posting.

If a payment is reversed:

1. create reversal record;
2. reverse allocations;
3. recalculate outstanding;
4. audit;
5. trigger notification if configured.

---

# 32. Dashboard Design

## 32.1 Admin dashboard

Cards:

- new leads;
- unassigned leads;
- first-response SLA;
- due follow-ups;
- overdue follow-ups;
- conversions;
- orders;
- sales;
- outstanding;
- payments;
- near-expiry;
- territory violations;
- blocked orders;
- dispatch pending;
- Sales Team productivity.

Source: fileciteturn0file0L372-L384

## 32.2 Sales dashboard

Prioritize:

- today's follow-ups;
- overdue;
- new assigned leads;
- lead aging;
- upcoming callbacks;
- interested leads;
- conversion pipeline;
- own party orders;
- payment reminders.

## 32.3 Distributor dashboard

Prioritize:

- active cart;
- recent orders;
- dispatch status;
- outstanding;
- invoices;
- payment status;
- reorder opportunities;
- support.

---

# 33. Responsive UX Architecture

## 33.1 Mobile navigation

Use:

- bottom navigation for high-frequency tasks;
- compact header;
- contextual action buttons;
- drawer for secondary navigation.

Primary Sales mobile actions:

```text
Home
Leads
Follow-ups
Parties
Orders
More
```

## 33.2 Desktop navigation

Use:

```text
Dashboard
CRM
  Leads
  Follow-ups
  Parties
Territory
Catalogue
  Products
  Pricing
  Schemes
Sales
  Orders
Inventory
Billing
Dispatch
Payments
Reports
Masters
Settings
Audit
```

## 33.3 Mobile tables

Do not force horizontally scrolling 20-column tables for core operations.

Use:

- stacked cards;
- compact summary rows;
- expandable detail;
- bottom-sheet actions;
- filters in drawer.

---

# 34. Accessibility

Target:

- semantic HTML;
- keyboard navigation;
- focus-visible indicators;
- sufficient text contrast;
- labels for every control;
- error messages tied to fields;
- accessible modal behavior;
- no color-only status communication;
- touch targets suitable for mobile;
- reduced-motion support where applicable.

---

# 35. Performance Architecture

## 35.1 Application

- lazy-load expensive reports;
- paginated lists;
- indexed filters;
- select only required columns;
- avoid N+1 queries;
- cache static master data;
- cache permission lookups;
- avoid loading full transaction history on every page.

## 35.2 Database

Indexes required for common search dimensions:

```text
leads(status, assigned_to, next_follow_up_at)
leads(mobile)
leads(external_source_id, external_lead_id)
parties(gstin)
parties(status, sales_team_id)
pincodes(pincode)
party_territories(party_id, effective_from, effective_to)
product_prices(product_id, pricing_tier_id, effective_from, effective_to)
inventory_batches(product_id, warehouse_id, expiry_date, status)
orders(party_id, status, created_at)
payments(party_id, payment_date)
```

Index strategy must be validated using actual query plans.

---

# 36. Reporting Architecture

Reports should use query services separate from CRUD repositories.

Example:

```text
LeadReportQuery
SalesReportQuery
InventoryReportQuery
OutstandingReportQuery
TerritoryReportQuery
DispatchReportQuery
WebhookReportQuery
NotificationReportQuery
```

Report endpoints must enforce authorization.

Large reports should support:

- filters;
- date ranges;
- pagination;
- CSV export;
- asynchronous export for large datasets.

---

# 37. Export Architecture

Do not generate very large exports during a normal web request.

Use:

```text
POST /reports/exports
        ↓
Create report job
        ↓
Background worker
        ↓
Generate file
        ↓
Store private file
        ↓
Notify user
        ↓
Authorized download
```

---

# 38. Background Jobs

Recommended job types:

```text
ProcessWebhookEvent
SendNotification
SendWhatsAppMessage
RetryFailedMessage
GenerateReport
GenerateExport
NearExpiryScan
FollowUpReminderScan
PaymentReminderScan
CleanTemporaryFiles
DatabaseBackup
HealthSnapshot
```

Job status:

```text
PENDING
RUNNING
SUCCESS
FAILED
RETRY_WAIT
DEAD
```

---

# 39. Scheduler

Cron examples:

```text
*/1 * * * * php cli/scheduler.php minute
*/5 * * * * php cli/scheduler.php five-minute
0 * * * *  php cli/scheduler.php hourly
0 2 * * *  php cli/scheduler.php daily
```

Actual schedules should be infrastructure-configured.

Daily jobs:

- near-expiry scan;
- follow-up reminder generation;
- payment reminder candidates;
- cleanup;
- backup;
- report housekeeping.

---

# 40. Frontend Component Library

Create reusable custom components:

```text
Button
IconButton
Input
Textarea
Select
SearchSelect
MultiSelect
DateInput
DateTimeInput
MoneyInput
PhoneInput
Badge
StatusBadge
Alert
Toast
Modal
Drawer
ConfirmDialog
DataTable
MobileList
Pagination
FilterBar
EmptyState
Skeleton
Tabs
Timeline
Stepper
FileUpload
Autocomplete
CartItem
PriceSummary
MetricCard
ChartContainer
```

No business logic in generic components.

---

# 41. Form Architecture

Every form should define:

```text
fields
labels
rules
server error mapping
help text
dirty state
submit state
success state
```

Frontend validation improves UX.

Backend validation remains authoritative.

---

# 42. Error Handling

User-facing message:

```text
"We couldn't save the order because the selected batch is no longer available."
```

Developer detail belongs in logs.

Error categories:

```text
VALIDATION
AUTHENTICATION
AUTHORIZATION
NOT_FOUND
CONFLICT
BUSINESS_RULE
INTEGRATION
SYSTEM
DATABASE
```

---

# 43. Business Error Catalogue

Examples:

```text
LEAD_DUPLICATE
LEAD_NOT_ASSIGNABLE
FOLLOWUP_NEXT_ACTION_REQUIRED
PARTY_INACTIVE
PARTY_TERRITORY_REQUIRED
TERRITORY_NOT_ALLOWED
TERRITORY_OVERRIDE_REASON_REQUIRED
PRICE_NOT_FOUND
PRICE_NOT_EFFECTIVE
SCHEME_NOT_ELIGIBLE
STOCK_NOT_AVAILABLE
STOCK_RESERVATION_CONFLICT
NO_ELIGIBLE_FEFO_BATCH
EXPIRED_STOCK
RECALLED_STOCK
QUARANTINED_STOCK
CREDIT_LIMIT_EXCEEDED
ORDER_STATE_INVALID
INVOICE_ALREADY_CANCELLED
PAYMENT_ALLOCATION_EXCEEDS_OUTSTANDING
WEBHOOK_SIGNATURE_INVALID
WEBHOOK_DUPLICATE
INTEGRATION_RETRY_LIMIT_REACHED
```

---

# 44. QA Strategy

The FRS explicitly calls for:

- CRUD and permission tests;
- duplicate lead/party tests;
- territory scenarios;
- pricing effective dates;
- scheme rules;
- stock concurrency;
- FEFO;
- expiry restrictions;
- cancellation / stock release;
- payment / outstanding;
- dispatch/LR;
- webhook authentication and retry;
- WhatsApp failure;
- audit;
- API authorization;
- large-list / inventory performance.

Source QA requirements: fileciteturn0file0L624-L642

---

# 45. Test Pyramid

## 45.1 Unit tests

Test pure services:

- phone normalization;
- price resolution;
- scheme calculation;
- territory resolution;
- FEFO allocation ordering;
- outstanding calculation;
- permission rules.

## 45.2 Integration tests

Test:

- database repositories;
- transactions;
- webhooks;
- notification adapter;
- file storage;
- queue system.

## 45.3 Feature tests

Test end-to-end application behavior:

```text
Lead -> Follow-up -> Conversion -> Party
Party -> Order -> Billing -> Dispatch
Order -> Payment -> Outstanding
Webhook -> Lead -> Assignment -> Notification
```

## 45.4 Security tests

Test:

- unauthenticated endpoints;
- role escalation;
- ownership bypass;
- CSRF;
- XSS;
- SQL injection;
- mass assignment;
- IDOR;
- path traversal;
- upload abuse;
- webhook spoofing;
- replay;
- brute force.

---

# 46. Critical Test Scenarios

## Lead

```text
1. Create valid lead.
2. Duplicate mobile blocks/requires configured behavior.
3. Duplicate GST/business identifier follows policy.
4. Assignment works.
5. Reassignment logged.
6. Active follow-up without next action fails.
7. Converted lead retains history.
```

## Territory

```text
1. Allowed pincode order succeeds.
2. Blocked pincode fails.
3. Unassigned pincode follows policy.
4. Admin override requires reason.
5. Override creates audit.
6. Historical order remains unchanged.
7. Expired territory allocation is not considered active.
```

## Pricing

```text
1. Party-specific rate wins over tier rate when configured.
2. Tier rate wins over product default.
3. Inactive price ignored.
4. Overlapping prices follow priority.
5. Historical order retains original price.
```

## Scheme

```text
10+1 at qty 9 => 0 free
10+1 at qty 10 => 1 free
10+1 at qty 20 => 2 free
10+1 at qty 21 => 2 free
```

## FEFO

```text
Batch A expires 2027-01
Batch B expires 2026-11
Batch C expires 2028-03

Required 10.

Order:
B first
then A
then C
```

## Inventory concurrency

Simulate two simultaneous orders attempting to reserve the same batch.

Expected:

- total reservations never exceed available quantity.

## Payment

```text
Invoice = 100,000
Payment = 60,000
Outstanding = 40,000
```

Second payment:

```text
40,000
Outstanding = 0
```

Over-allocation must fail unless explicit advance handling is implemented.

---

# 47. UAT Strategy

## UAT actors

- Business Owner / Admin;
- Sales Team representative;
- Billing operator;
- Warehouse operator or Admin;
- Dispatch operator;
- Distributor / Franchise Partner;
- Technical administrator.

## UAT environments

```text
LOCAL
→ DEV
→ QA
→ UAT
→ STAGING
→ PRODUCTION
```

No direct development-to-production flow.

## UAT evidence

Every critical requirement should have:

- test case;
- tester;
- timestamp;
- result;
- screenshot or output where appropriate;
- defect ID if failed;
- sign-off status.

---

# 48. Definition of Ready

A requirement is Ready only when:

- business objective is clear;
- actor identified;
- fields defined;
- validation defined;
- business rules defined;
- permission impact defined;
- database impact identified;
- API impact identified;
- UI impact identified;
- acceptance criteria written;
- edge cases documented.

Source Definition of Ready: fileciteturn0file0L679-L687

---

# 49. Definition of Done

A feature is Done only when:

- frontend complete;
- backend/API complete;
- database changes complete;
- server-side permissions complete;
- validation and errors complete;
- audit complete;
- integrations tested;
- QA passed;
- critical/high defects closed or accepted;
- BA/UAT passed;
- documentation updated.

Source Definition of Done: fileciteturn0file0L688-L699

---

# 50. Change Control

Every new request must be evaluated against:

```text
UI
Backend
Database
Permission
Audit
Integration
Performance
Security
QA
UAT
Documentation
Deployment
```

The FRS defines this as the baseline impact rule.

Source: fileciteturn0file0L700-L703

No developer should quietly add a field or rule that changes transaction behavior without updating the affected artifacts.

---

# 51. Detailed Implementation Roadmap

## Phase 0 — Discovery, Freeze and Foundation

### Objective

Convert the FRS into an engineering-ready baseline.

### Deliverables

```text
01. Business rule register
02. Role-permission matrix
03. State transition matrix
04. Entity relationship model
05. API inventory
06. UI sitemap
07. Acceptance test inventory
08. Open business decision log
09. Non-functional requirements
10. Deployment topology
11. Environment definition
12. Coding standards
```

### Exit gate

No implementation begins for a module until its critical Definition of Ready items are complete.

---

# 52. Phase 1 — Core Platform, Auth, Dashboard, Leads, Follow-ups, Parties, Sales Team, Masters

The source release plan places:

- Auth;
- Admin/Sales Team;
- Dashboard;
- Leads;
- Follow-ups;
- Parties;
- Sales Team;
- Masters

in Phase 1.

Source: fileciteturn0file0L657-L660

## 52.1 Workstream A — Framework Core

Tasks:

- bootstrap application;
- request/response;
- router;
- controller base;
- middleware;
- DI container;
- config loader;
- database abstraction;
- transaction helper;
- logger;
- exception handler;
- validation framework;
- pagination;
- JSON response standard;
- session abstraction.

### Acceptance

A protected route can:

```text
request
→ authenticate
→ authorize
→ execute service
→ render ViewModel
→ render View
```

and API can:

```text
request
→ authenticate
→ authorize
→ execute service
→ return standard JSON
```

## 52.2 Workstream B — Authentication

Deliver:

- Admin login;
- Sales Team login;
- session management;
- password recovery;
- permission middleware.

## 52.3 Workstream C — Sales Team

Deliver:

- create;
- edit;
- activate/deactivate;
- territory assignment;
- ownership.

## 52.4 Workstream D — Masters

Deliver:

- states;
- districts;
- cities;
- pincodes;
- lead source;
- lead status;
- follow-up types;
- party type;
- product category placeholders;
- pricing tier;
- payment mode;
- order status;
- dispatch status;
- transporter;
- notification template;
- webhook source.

## 52.5 Workstream E — Leads

Deliver all core CRUD, assignment, duplicate handling, activities and conversion preparation.

## 52.6 Workstream F — Follow-up

Deliver task list, overdue queue, reschedule, complete, miss, remarks, next actions.

## 52.7 Workstream G — Parties

Deliver partner profile, addresses, ownership, territory placeholders, pricing tier placeholders, credit setup.

### Phase 1 exit gate

```text
Admin can manage users/masters.
Sales Team can receive and manage assigned leads.
Lead can convert to party.
Follow-up discipline is enforced.
All security controls are server-side.
Audit exists for sensitive changes.
```

---

# 53. Phase 2 — Products, Pricing, Schemes, Orders, Payments, Reports

The source release plan places:

- Products;
- Pricing;
- Schemes;
- Orders;
- Payments;
- Reports

in Phase 2.

Source: fileciteturn0file0L660-L661

## 53.1 Products

Build:

- product CRUD;
- archive;
- activation;
- category;
- pricing fields;
- storage;
- shelf-life.

## 53.2 Pricing

Build:

- pricing tiers;
- effective dates;
- party-specific rules;
- tier-specific rules;
- priority;
- price resolver;
- audit;
- historical snapshots.

## 53.3 Scheme Engine

Build:

- configurable rules;
- quantity scheme;
- eligibility;
- priority;
- stacking configuration;
- cart calculator;
- transaction snapshot.

## 53.4 Orders

Build:

- draft;
- cart;
- validation;
- submit;
- confirm;
- cancel;
- price snapshot;
- scheme snapshot;
- credit validation;
- stock pre-check.

## 53.5 Payments

Build:

- record payment;
- allocation;
- invoice outstanding;
- party outstanding;
- reference number;
- receipt;
- reversal workflow.

## 53.6 Reports

Build:

- lead source;
- response time;
- conversion;
- Sales Team productivity;
- territory;
- party sales;
- product sales;
- scheme utilization;
- order status;
- payment;
- outstanding.

Source report list: fileciteturn0file0L385-L403

### Phase 2 exit gate

A fully validated order can be created and confirmed with correct:

```text
party
territory prerequisite
price
scheme
credit rule
audit
payment state
```

---

# 54. Phase 3 — Territory Lock, Inventory, FEFO, Near Expiry, Billing, Dispatch

The source release plan places:

- territory lock;
- inventory/batches;
- FEFO;
- near-expiry;
- billing;
- dispatch/LR

in Phase 3.

Source: fileciteturn0file0L661-L663

## 54.1 Territory Lock

Build:

- effective-dated territory assignments;
- pincode resolver;
- district resolver;
- blocked-order rule;
- Admin override;
- audit;
- unassigned behavior.

## 54.2 Inventory

Build:

- batch receipts;
- adjustments;
- transfers;
- damage;
- return;
- quarantine;
- recall state;
- reservations.

## 54.3 FEFO

Build:

- eligible batch query;
- locking;
- allocation;
- release;
- override;
- audit.

## 54.4 Near-expiry

Build:

- configurable threshold;
- dashboard;
- export;
- alerts.

## 54.5 Billing

Build:

- invoice numbering;
- batch mapping;
- billing transaction;
- invoice print;
- cancellation.

## 54.6 Dispatch

Build:

- packing;
- transporter;
- LR;
- tracking URL;
- dispatch state;
- portal status update;
- notification trigger.

### Phase 3 exit gate

No order may proceed to billing unless all required:

```text
territory
price
scheme
stock
batch
credit
party state
```

checks pass.

---

# 55. Phase 4 — Distributor Portal, WhatsApp, Webhooks, Automation

The source release plan places:

- Distributor Portal;
- WhatsApp;
- Webhooks;
- Automation

in Phase 4.

Source: fileciteturn0file0L663-L664

## 55.1 Distributor Portal

Build:

- separate authentication boundary;
- own-profile scope;
- catalogue;
- applicable pricing;
- cart;
- order placement;
- order timeline;
- invoice;
- dispatch;
- tracking;
- outstanding;
- payment history;
- support.

## 55.2 Webhooks

Build:

- secure endpoint;
- signature/auth;
- normalization;
- deduplication;
- retry;
- failure dashboard;
- assignment;
- notification.

## 55.3 WhatsApp

Build provider adapter.

Provider-specific code must not leak into domain services.

Example:

```text
WhatsAppProviderContract
  ├── queueTemplate()
  ├── sendTemplate()
  ├── getStatus()
  └── normalizeStatus()
```

## 55.4 Automation

Automate:

- follow-up reminders;
- lead assignment alerts;
- order confirmation;
- invoice notifications;
- dispatch notifications;
- payment reminders;
- near-expiry alerts;
- webhook failures.

### Phase 4 exit gate

Every integration can be disabled without breaking core CRM transactions.

---

# 56. Phase 5 — QA, Security, Performance, UAT, Production

The source release plan ends with:

- QA;
- Security;
- Performance;
- UAT;
- Production.

Source: fileciteturn0file0L663-L664

## 56.1 QA hardening

- regression;
- data integrity;
- concurrency;
- authorization;
- mobile UI;
- exports;
- notifications.

## 56.2 Security hardening

- headers;
- TLS;
- access control;
- CSRF;
- XSS;
- SQLi;
- IDOR;
- upload security;
- webhook replay;
- secrets.

## 56.3 Performance

Benchmark:

- dashboard;
- leads list;
- order list;
- inventory;
- search;
- reports;
- FEFO reservation;
- webhook ingestion.

## 56.4 UAT

Freeze release candidate.

Only critical acceptance defects should block production.

## 56.5 Production

Perform:

- database migration;
- secrets;
- backup;
- monitoring;
- cron;
- job runner;
- health checks;
- rollback test;
- smoke test.

---

# 57. Sprint-Level Breakdown

Suggested sprint sequence:

```text
Sprint 01
Project foundation
Framework core
Routing
Config
Logging
Database layer

Sprint 02
Authentication
Roles
Permissions
Users
Audit foundation

Sprint 03
Masters
Sales Team
Territories foundation

Sprint 04
Lead management
Assignment
Duplicate rules

Sprint 05
Follow-ups
Activity history
Dashboard baseline

Sprint 06
Party / franchise partner
Onboarding
Addresses
Documents

Sprint 07
Products
Categories
Catalogue

Sprint 08
Pricing engine
Pricing tier
Effective dates
Overrides

Sprint 09
Scheme engine
Cart pricing
Scheme tests

Sprint 10
Orders
Order states
Credit validation

Sprint 11
Payments
Outstanding
Allocation

Sprint 12
Territory lock
Pincode validation
Overrides

Sprint 13
Inventory
Batch
Receipt
Reservation

Sprint 14
FEFO
Near expiry
Inventory reports

Sprint 15
Billing
Invoice
Cancellation

Sprint 16
Dispatch
Transporter
LR
Tracking

Sprint 17
Distributor Portal
Portal order flow

Sprint 18
Webhook
Lead ingestion
Idempotency

Sprint 19
Notifications
WhatsApp adapter
Automation

Sprint 20
Reports
Exports
Dashboards

Sprint 21
Security hardening
Performance

Sprint 22
Full regression
UAT preparation

Sprint 23
UAT
Defect remediation

Sprint 24
Production
Hypercare
Operational documentation
```

This is a planning baseline, not a contractual duration. Actual estimates depend on finalized requirements, environments, data quality, integration availability and client decisions.

---

# 58. Dependency Map

```text
Framework Core
    ↓
Auth / Authorization
    ↓
Masters
    ↓
Sales Team
    ↓
Leads
    ↓
Follow-ups
    ↓
Parties
    ↓
Products
    ↓
Pricing
    ↓
Schemes
    ↓
Orders
    ↓
Territory + Inventory
    ↓
FEFO
    ↓
Billing
    ↓
Dispatch
    ↓
Payments / Outstanding
    ↓
Portal
    ↓
WhatsApp / Webhooks
    ↓
Reports / Automation
    ↓
QA / Security / UAT / Production
```

---

# 59. Parallel Workstreams

The following can be developed in parallel after the foundation is stable:

```text
UI System
API Contract
Database Migrations
Unit Tests
Documentation
Audit
Security Controls
Reporting Queries
```

Do not parallelize tightly coupled commercial logic before its acceptance criteria are frozen.

---

# 60. Environment Strategy

## Local

Purpose:

- developer workstation;
- synthetic data;
- integration mocks.

## DEV

Purpose:

- active development;
- team integration.

## QA

Purpose:

- automated and manual QA.

## UAT

Purpose:

- business validation.

## STAGING

Purpose:

- production-like smoke tests;
- deployment rehearsals.

## PRODUCTION

Purpose:

- real business data.

---

# 61. Configuration Strategy

Environment variables:

```text
APP_ENV
APP_DEBUG
APP_URL
APP_TIMEZONE

DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASSWORD

SESSION_SECRET
CSRF_SECRET

WHATSAPP_PROVIDER
WHATSAPP_BASE_URL
WHATSAPP_SECRET

WEBHOOK_SECRET

MAIL_HOST
MAIL_PORT
MAIL_USERNAME
MAIL_PASSWORD

STORAGE_PATH
PRIVATE_STORAGE_PATH

LOG_LEVEL
QUEUE_DRIVER
```

No secrets in source control.

---

# 62. Secret Management

Development:

```text
.env
```

Production:

- operating-system environment;
- secure secret manager if available;
- file permissions;
- rotation process.

Never:

- hard-code API keys;
- commit production secrets;
- include provider passwords in logs;
- send secrets to browser JavaScript.

---

# 63. Deployment Architecture

Recommended:

```text
                 Internet
                    |
                  HTTPS
                    |
              Web Server
                    |
              PHP Application
              /             \
        Static assets       App code
                    |
                 Database
                    |
              Worker/Cron
                    |
         External Integrations
```

Production should isolate:

- public files;
- application source;
- private files;
- logs;
- database.

---

# 64. Deployment Checklist

## Before deployment

```text
[ ] Code reviewed
[ ] Automated tests pass
[ ] Migration tested
[ ] Backup verified
[ ] Environment variables set
[ ] Debug disabled
[ ] Error display disabled
[ ] HTTPS active
[ ] Cron configured
[ ] Worker configured
[ ] Health check passing
[ ] API docs updated
[ ] Rollback plan confirmed
```

## After deployment

```text
[ ] Login
[ ] Lead creation
[ ] Party lookup
[ ] Product lookup
[ ] Order draft
[ ] Report load
[ ] Webhook health
[ ] Notification health
[ ] Database connectivity
[ ] Queue processing
[ ] Logs clean
```

---

# 65. Backup and Recovery

Minimum policy:

```text
Daily full database backup
+
Frequent incremental/binlog-style recovery strategy if infrastructure supports it
+
Off-server backup
+
Retention policy
+
Restore test
```

A backup is not considered valid until a restore has been tested.

Recovery documentation must include:

- database restore;
- private-file restore;
- environment reconfiguration;
- cron restart;
- worker restart;
- smoke test.

---

# 66. Health Checks

## Application health

```text
GET /health
```

Should return:

```json
{
  "status": "ok"
}
```

## Readiness

```text
GET /ready
```

Checks:

- database reachable;
- application config loaded;
- required storage writable;
- queue subsystem reachable if configured.

Do not expose secret/config data.

---

# 67. Monitoring

Monitor:

```text
HTTP error rate
slow requests
database errors
failed jobs
webhook failures
notification failures
stock reservation conflicts
login failures
disk usage
backup status
database size
queue depth
```

---

# 68. Operational Runbooks

Create:

```text
RUNBOOK-01 Application restart
RUNBOOK-02 Database restore
RUNBOOK-03 Failed webhook replay
RUNBOOK-04 Failed WhatsApp retry
RUNBOOK-05 Stuck job cleanup
RUNBOOK-06 Payment reversal
RUNBOOK-07 Territory override investigation
RUNBOOK-08 Stock reconciliation
RUNBOOK-09 Invoice cancellation
RUNBOOK-10 User access deactivation
RUNBOOK-11 Security incident response
RUNBOOK-12 Production rollback
```

---

# 69. Data Migration Strategy

Possible legacy sources:

- Excel;
- CSV;
- previous CRM;
- manual master sheets.

Import process:

```text
Raw File
→ Staging Table
→ Normalize
→ Validate
→ Duplicate Detect
→ Business Rule Check
→ Review
→ Commit
→ Import Report
```

Never import directly into final tables without validation.

---

# 70. Import Validation

For leads:

- mobile;
- email;
- source;
- duplicate;
- state;
- district;
- pincode.

For parties:

- GSTIN;
- firm name;
- contact;
- address;
- pincode;
- territory;
- pricing tier.

For products:

- SKU;
- product name;
- pack size;
- GST;
- pricing.

For inventory:

- product;
- batch;
- expiry;
- quantity;
- warehouse;
- saleable status.

---

# 71. Reconciliation Controls

Inventory reconciliation:

```text
Opening
+ Receipts
+ Returns
+ Transfers In
- Sales/Dispatch
- Damage
- Expiry
- Transfers Out
+/- Adjustments
= Closing
```

Payment reconciliation:

```text
Invoice Total
- Allocated Payments
= Outstanding
```

Territory reconciliation:

```text
Active Allocation
vs
Order Shipping Pincodes
```

---

# 72. Business Reports

Required baseline reports:

1. Lead source.
2. Response time.
3. Conversion.
4. Sales Team productivity.
5. Territory sales.
6. Party sales.
7. Product sales.
8. Scheme utilization.
9. Order status.
10. Dispatch / LR pending.
11. Payments.
12. Outstanding.
13. Batch inventory.
14. Near-expiry.
15. Territory violations.
16. Webhook failures.
17. WhatsApp delivery.

Source: fileciteturn0file0L385-L403

---

# 73. KPI Definitions

## First response time

```text
first_response_at - lead_received_at
```

## Lead conversion rate

```text
converted_leads / eligible_leads * 100
```

## Follow-up overdue count

Number of active follow-ups where:

```text
next_follow_up_at < now
```

## Outstanding

```text
invoice due - allocated payments
```

## Inventory near expiry

A batch belongs in a configured alert bucket when:

```text
expiry_date <= today + configured_days
```

---

# 74. Business Decision Register

The source FRS leaves the following decisions open:

- Can one Franchise Partner own multiple districts/pincodes?
- What happens when a pincode is unassigned?
- What minimum shelf-life must remain at dispatch?
- Is pricing party-specific, tier-specific or both?
- Can schemes stack?
- If schemes stack, what is priority?
- Should credit limit hard-block, warn or require Admin approval?
- What invoice/GST format is required?
- Which official WhatsApp provider and templates are approved?
- Which B2B portals provide official webhook/API access?
- How will Distributor Portal authenticate?
- Are separate Warehouse / Dispatch / Billing roles required later?

Source open-decision list: fileciteturn0file0L667-L678

## Mandatory implementation rule

No developer should guess one of these commercial policies in production code.

Each must receive a Business Decision Record:

```text
BDR-001
Title:
Decision:
Owner:
Date:
Approved By:
Impacted Modules:
UI Impact:
API Impact:
Database Impact:
QA Impact:
```

---

# 75. Business Decision Impact Matrix

| Decision | Lead | Party | Territory | Pricing | Order | Inventory | Billing | Portal |
|---|---:|---:|---:|---:|---:|---:|---:|---:|
| Multiple territories per partner |  | ✓ | ✓ |  | ✓ |  | ✓ | ✓ |
| Unassigned pincode behavior |  |  | ✓ |  | ✓ |  | ✓ | ✓ |
| Minimum shelf-life |  |  |  |  | ✓ | ✓ | ✓ | ✓ |
| Party/tier pricing |  | ✓ |  | ✓ | ✓ |  | ✓ | ✓ |
| Scheme stacking |  |  |  | ✓ | ✓ |  | ✓ | ✓ |
| Credit action |  | ✓ |  |  | ✓ |  | ✓ | ✓ |
| Invoice/GST format |  |  |  |  |  |  | ✓ | ✓ |
| WhatsApp provider | ✓ | ✓ |  |  | ✓ |  | ✓ | ✓ |
| Webhook sources | ✓ |  |  |  |  |  |  |  |
| Portal authentication |  | ✓ |  |  | ✓ |  |  | ✓ |
| Separate operational roles |  |  |  |  | ✓ | ✓ | ✓ |  |

---

# 76. Traceability Matrix

Every FRS requirement should map to:

```text
Requirement ID
↓
Module
↓
Database table(s)
↓
API endpoint(s)
↓
UI screen(s)
↓
Business rule
↓
Test case
↓
Acceptance criterion
↓
Release phase
```

Example:

```text
AC-TR-01
Territory Lock
    ↓
TerritoryValidationService
    ↓
party_territories + pincodes
    ↓
POST /orders
    ↓
Order Create Screen
    ↓
Block outside territory
    ↓
TC-TR-001
    ↓
UAT-TR-001
    ↓
Phase 3
```

---

# 77. Acceptance Criteria Traceability

Source FRS acceptance criteria include:

```text
AC-TR-01  Territory Lock
AC-TR-02  Override
AC-FEFO-01 Batch Allocation
AC-FEFO-02 Expiry
AC-EXP-01 Near Expiry
AC-PRICE-01 Pricing
AC-SCH-01 Scheme
AC-DSP-01 Dispatch
AC-WA-01 WhatsApp
AC-WH-01 Webhook Dedup
AC-WH-02 New Webhook Alert
```

Source: fileciteturn0file0L574-L620

Each acceptance criterion must have:

- automated test where feasible;
- manual UAT scenario;
- implementation reference.

---

# 78. Example Acceptance Test: Territory

```text
Given:
  Party P has pincode 396191 assigned.

When:
  order shipping pincode = 396191

Then:
  order validation = ALLOWED
```

Blocked:

```text
Given:
  Party P has pincode 396191.

When:
  order shipping pincode = 110001

Then:
  order validation = BLOCKED
  error code = TERRITORY_NOT_ALLOWED
```

Override:

```text
Given:
  Admin attempts override.

When:
  reason is empty.

Then:
  override is rejected.
```

---

# 79. Example Acceptance Test: FEFO

```text
Given:
  Batch A expires later.
  Batch B expires earlier.
  Both are saleable.

When:
  required quantity can be fulfilled from either.

Then:
  Batch B is allocated before Batch A.
```

Blocked stock:

```text
Given:
  Batch B is recalled.

Then:
  Batch B is not eligible.
```

---

# 80. Example Acceptance Test: Webhook

```text
Given:
  external_event_id = ABC-123

When:
  webhook is received twice.

Then:
  only one lead record is created.
```

Failure:

```text
Given:
  invalid signature.

Then:
  request rejected.
  no lead created.
  event logged.
```

---

# 81. Example Acceptance Test: Pricing

```text
Given:
  party has tier Silver.
  product default = 100.
  Silver rate = 90.
  Party-specific rate = 85.

When:
  order is created.

Then:
  selected rate = 85.
```

After rate update:

```text
Existing confirmed order
    keeps 85
New order
    uses new applicable rate
```

---

# 82. Example Acceptance Test: Scheme

```text
Given:
  scheme = 10+1

When:
  order quantity = 20

Then:
  paid = 20
  free = 2
```

---

# 83. API Contract Governance

Rules:

1. Never silently remove an endpoint.
2. Never change response field type without versioning.
3. Never rename error codes after public consumption without migration.
4. Additive changes should remain backward compatible where possible.
5. Major breaking changes require `/v2`.
6. OpenAPI must be updated in the same pull request as API behavior.

---

# 84. Coding Standards

## PHP

- strict types where adopted;
- typed parameters;
- typed return values;
- small methods;
- one responsibility;
- explicit exceptions;
- no hidden global state;
- no business logic in templates;
- no SQL in controllers.

## JavaScript

- ES modules;
- no global namespace pollution;
- event delegation where useful;
- API client abstraction;
- explicit loading/error states;
- graceful degradation.

## CSS

- tokenized spacing;
- component classes;
- mobile-first breakpoints;
- no inline style dependencies;
- no page-level duplication.

---

# 85. Code Review Checklist

Before merge:

```text
[ ] Requirement linked
[ ] Business rule linked
[ ] Security reviewed
[ ] Authorization reviewed
[ ] Validation reviewed
[ ] Transaction reviewed
[ ] Audit reviewed
[ ] Error handling reviewed
[ ] SQL reviewed
[ ] Index impact reviewed
[ ] API docs updated
[ ] UI responsive
[ ] Tests added
[ ] Logs safe
```

---

# 86. Pull Request Structure

Each change should include:

```text
1. Objective
2. Requirement IDs
3. Business rules
4. Database changes
5. API changes
6. UI changes
7. Security impact
8. Testing performed
9. Migration/rollback notes
10. Documentation changes
```

---

# 87. Database Migration Governance

Rules:

- one purpose per migration;
- reversible where practical;
- no destructive migration without backup;
- large-table changes planned;
- index creation reviewed;
- migration tested against production-like data volume.

---

# 88. Seed Data

Seed only stable baseline data:

- system roles;
- permissions;
- status catalogues;
- default settings;
- generic notification templates.

Never seed:

- real customer data;
- production passwords;
- real provider secrets.

---

# 89. Data Retention

Retention must be approved for:

- audit;
- webhook payloads;
- logs;
- WhatsApp states;
- user sessions;
- deleted business objects;
- exports.

Do not keep personal or sensitive data indefinitely without a business/legal basis.

---

# 90. Privacy and Sensitive Information

Potentially sensitive data includes:

- personal contact details;
- business identifiers;
- GSTIN;
- drug-license-related documents;
- login activity;
- payment information;
- integration payloads.

Protect with:

- least privilege;
- private storage;
- access logging;
- masking in UI where appropriate;
- masked logs;
- controlled exports.

---

# 91. Mobile Field Workflow

Sales Team mobile-first flow:

```text
Login
  ↓
Today's Follow-ups
  ↓
Lead / Party
  ↓
Call / Visit / WhatsApp Activity
  ↓
Remark
  ↓
Next Action
  ↓
Next Follow-up
  ↓
Optional Order
  ↓
Save
```

The workflow must minimize typing.

Use:

- quick action buttons;
- recent values;
- smart defaults;
- mobile keyboard types;
- voice-compatible fields where browser support is useful;
- compressed information layout.

---

# 92. Distributor Mobile Workflow

```text
Login
  ↓
Catalogue
  ↓
Product Search
  ↓
Product Details
  ↓
Add Quantity
  ↓
Scheme Preview
  ↓
Cart
  ↓
Address
  ↓
Territory Validation
  ↓
Credit / Payment Rule
  ↓
Place Order
  ↓
Order Timeline
```

---

# 93. Admin Workflow

```text
Dashboard
  ↓
New Leads
  ↓
Assignment
  ↓
Territory
  ↓
Party Approval
  ↓
Products/Pricing/Schemes
  ↓
Orders
  ↓
Inventory
  ↓
Billing
  ↓
Dispatch
  ↓
Payments
  ↓
Reports
  ↓
Audit
```

---

# 94. Integration Adapter Pattern

Do not hard-code provider-specific code inside business services.

Example:

```php
interface LeadSourceAdapter
{
    public function verify(Request $request): VerificationResult;

    public function parse(Request $request): ExternalLeadData;

    public function acknowledge(): IntegrationResponse;
}
```

Provider-specific:

```text
IndiaMartAdapter
PharmaPortalAdapter
FutureLeadSourceAdapter
```

The domain consumes normalized data:

```text
ExternalLeadData
```

not provider-specific field names.

---

# 95. WhatsApp Adapter Pattern

```php
interface MessagingProvider
{
    public function sendTemplate(
        string $recipient,
        string $template,
        array $parameters
    ): MessageResult;
}
```

Provider adapter translates:

```text
Internal Template
→
Provider Payload
→
Provider Response
→
Internal MessageState
```

---

# 96. Notification Idempotency

Every outbound transaction should have a logical idempotency key:

```text
event_type + entity_type + entity_id + template_version
```

Example:

```text
ORDER_CONFIRMED:ORDER:10001:1
```

This prevents duplicate sends when workers retry.

---

# 97. Audit vs Log

Do not confuse them.

**Application log:**

- technical diagnosis;
- temporary operational troubleshooting.

**Audit:**

- business action history;
- who changed what;
- before/after;
- reason.

Audit records must remain understandable even after technical logs are rotated.

---

# 98. Reporting Data Freshness

Operational dashboards:

- near-real-time from transactional tables.

Heavy analytics:

- may use summary queries or precomputed snapshots.

Do not build a separate analytics warehouse during Phase 1 unless scale requires it.

---

# 99. Scalability Strategy

Start with:

```text
1 web app
1 SQL database
1 worker
1 scheduled job process
```

Scale vertically first.

When needed:

```text
Load Balancer
   |
Web 1
Web 2
Web 3
   |
Database
   |
Worker 1
Worker 2
```

Application must keep state outside local process memory.

Use shared storage for:

- sessions if horizontally scaled;
- private generated files;
- queue state if required.

---

# 100. Caching Strategy

Cache stable data:

- state;
- district;
- pincode;
- product categories;
- permissions;
- notification template metadata.

Do not cache:

- live stock quantities without strict invalidation;
- current outstanding;
- mutable pricing in a way that can cause stale financial transactions.

For transactional pricing:

- cache for display if desired;
- resolve again server-side at confirmation.

---

# 101. Concurrency Protection

Sensitive operations:

```text
Order confirmation
Stock reservation
FEFO allocation
Invoice posting
Payment allocation
Territory override
Sequence generation
```

must have explicit concurrency controls.

Use:

- transactions;
- locks;
- unique constraints;
- version checks;
- idempotency keys.

---

# 102. Numbering Strategy

Business references:

```text
LEAD-2026-000001
PARTY-2026-000001
ORD-2026-000001
INV-2026-000001
PAY-2026-000001
DSP-2026-000001
```

The actual numbering format must be client-approved.

Do not use the database primary key as the only business reference if human-readable numbering is required.

---

# 103. Sequence Safety

Never generate invoice numbers with:

```text
SELECT MAX(invoice_no) + 1
```

Use:

- dedicated sequence table;
- transaction lock;
- database sequence where supported;
- controlled counter service.

---

# 104. Search Architecture

Global search should support:

```text
Lead
Party
Product
Order
Invoice
Payment
LR
```

Each domain should expose its own query service.

Search should not perform wildcard scans over millions of rows.

---

# 105. Pagination Standards

Default:

```text
25 rows
```

Allowed:

```text
10
25
50
100
```

Maximum:

```text
100
```

unless export flow is used.

Use indexed ordering.

---

# 106. Filtering Standards

Common:

```text
date_from
date_to
status
assigned_to
territory
district
pincode
party
product
source
```

Validate filter fields against allowlists.

---

# 107. Sorting Standards

Never accept arbitrary SQL column names.

Map:

```text
sort=created_at
```

to an approved internal column.

Reject:

```text
sort=some_sql_payload
```

---

# 108. Soft Delete Strategy

Use soft delete for:

- masters;
- products;
- users;
- parties;
- leads where historically necessary.

Avoid soft deleting:

- payments;
- inventory movements;
- invoice ledger rows;
- audit events.

For financial and inventory records, use explicit reversal / cancellation state.

---

# 109. Historical Immutability

Historical transaction snapshots must preserve:

- product name;
- SKU;
- batch;
- rate;
- discount;
- GST;
- quantity;
- free quantity;
- customer details required by invoice rules.

Do not reconstruct an old invoice from current masters.

---

# 110. Invoice Rendering

Invoice must be generated from immutable invoice data.

Render:

```text
header
customer
billing/shipping
items
batch
qty
free qty
rate
discount
GST
subtotal
total
payment status
terms
```

PDF printing should use a dedicated print template.

---

# 111. Dispatch Tracking

The system should support:

```text
Transporter
LR Number
Tracking URL
Dispatch Date
Status
Remarks
```

Tracking URL may be manually entered initially.

Provider integrations should be optional.

---

# 112. Document Management

Documents potentially required:

- GST certificate;
- drug license;
- agreement;
- onboarding document;
- payment proof.

Structure:

```text
files
  ↓
file_links
  ↓
entity
```

Access is authorized by entity ownership/permission.

---

# 113. Master Data Governance

Masters must have:

- active/inactive;
- display order where useful;
- code;
- name;
- effective dates where required.

Never hard-code status labels in business logic.

---

# 114. Configuration vs Code

Put in configuration:

- near-expiry thresholds;
- unassigned pincode policy;
- scheme rules;
- notification templates;
- credit behavior;
- minimum shelf-life;
- pagination defaults.

Keep in code:

- transaction safety;
- security;
- core domain invariants;
- database integrity.

---

# 115. Feature Flags

Use simple database/application configuration flags.

Example:

```text
enable_distributor_portal
enable_whatsapp
enable_webhooks
enable_credit_block
enable_manual_territory_override
enable_pdc_tracking
```

Feature flags must not bypass authorization or security.

---

# 116. PDC Tracking

The FRS mentions PDC tracking if used.

If enabled later, define:

```text
PDC Number
Cheque Date
Bank
Amount
Party
Invoice Link
Deposit Date
Clearing Date
Status
Bounce Reason
```

State:

```text
RECEIVED
HELD
DEPOSITED
CLEARED
BOUNCED
CANCELLED
```

This should be implemented only after business confirmation because it expands the payment domain.

---

# 117. Reports Export Security

Exports may contain sensitive party and financial data.

Rules:

- authorization at export creation;
- authorization at download;
- private file storage;
- expiring download token;
- export owner recorded;
- export audit logged.

---

# 118. API Rate Limiting

Protect:

```text
login
password reset
webhook
public/partner API
report export
search endpoints
```

Rate limits should be configurable by endpoint category.

---

# 119. Webhook Rate Limiting

External webhook sources should have:

- request size limit;
- per-source rate limit;
- replay detection;
- signature validation;
- IP restriction if provider documentation supports stable source addresses.

Do not rely only on IP restrictions if provider supports cryptographic signing.

---

# 120. Secure Headers

Production should consider:

```text
Content-Security-Policy
Strict-Transport-Security
X-Content-Type-Options
Referrer-Policy
Permissions-Policy
frame-ancestors policy
```

Exact values should be validated against application behavior before deployment.

---

# 121. Content Security Strategy

Avoid inline scripts where possible.

Prefer:

```text
static JS modules
nonce-based scripts if necessary
strict asset origins
```

Do not allow unrestricted:

```text
unsafe-inline
unsafe-eval
```

unless there is a documented exception.

---

# 122. Production Debugging Rules

Production:

```text
APP_DEBUG = false
display_errors = false
```

Errors go to structured logs.

User receives:

```text
Something went wrong. Reference ID: REQ-...
```

---

# 123. Disaster Recovery Targets

Define with client:

```text
RPO = maximum acceptable data loss
RTO = maximum acceptable recovery time
```

Do not invent commercial targets.

Document:

- database backup frequency;
- restore time;
- backup location;
- emergency contacts;
- rollback method.

---

# 124. Performance Acceptance Baseline

Before production define target budgets for:

```text
Dashboard initial load
Lead list
Party list
Product search
Order confirmation
FEFO allocation
Invoice render
Report query
Webhook acknowledgment
```

The actual numeric targets must be agreed based on infrastructure and dataset.

---

# 125. Load Test Scenarios

At minimum:

```text
1. 100 concurrent users browsing CRM.
2. 20 concurrent order operations.
3. concurrent stock reservation.
4. lead webhook burst.
5. report generation under transaction load.
6. large inventory listing.
```

The final test scale must reflect actual expected business volume.

---

# 126. Security Test Matrix

```text
Authentication:
  [ ] brute force
  [ ] session fixation
  [ ] session reuse
  [ ] password reset abuse

Authorization:
  [ ] horizontal access
  [ ] vertical privilege escalation
  [ ] object ID tampering
  [ ] portal-to-admin access

Input:
  [ ] SQL injection
  [ ] XSS
  [ ] HTML injection
  [ ] path traversal
  [ ] malformed JSON

Files:
  [ ] executable upload
  [ ] oversized upload
  [ ] private file access

API:
  [ ] replay
  [ ] missing auth
  [ ] invalid tokens
  [ ] rate limiting

Webhooks:
  [ ] fake signature
  [ ] duplicate event
  [ ] malformed payload
  [ ] replayed event
```

---

# 127. Release Checklist

## Feature complete

```text
[ ] All scoped features implemented
[ ] Open business decisions resolved
[ ] No unresolved critical ambiguity
```

## Engineering complete

```text
[ ] Unit tests
[ ] Integration tests
[ ] Feature tests
[ ] Security tests
[ ] Performance test
[ ] Migration test
```

## Documentation complete

```text
[ ] README
[ ] Architecture
[ ] OpenAPI
[ ] Business rules
[ ] Runbooks
[ ] UAT scripts
[ ] Deployment
```

## Operational complete

```text
[ ] Backups
[ ] Monitoring
[ ] Health checks
[ ] Worker
[ ] Scheduler
[ ] Error logs
```

---

# 128. Production Smoke Test

Immediately after deployment:

```text
1. Open login.
2. Login as Admin.
3. Open dashboard.
4. Open leads.
5. Create test lead if allowed.
6. Assign test lead.
7. Create test follow-up.
8. Open party.
9. Open product.
10. Open order.
11. Verify API health.
12. Verify queue.
13. Verify webhook endpoint health.
14. Verify logs.
15. Verify backup job.
```

Never use real transactions for smoke tests unless a dedicated test account is configured.

---

# 129. Rollback Strategy

Rollback layers:

```text
Application code
Database migration
Configuration
Assets
```

A rollback must specify:

- trigger condition;
- responsible person;
- code version;
- database compatibility;
- data impact;
- notification plan;
- smoke test.

Never automatically rollback a database migration if data may have already changed irreversibly.

---

# 130. Hypercare

Post-production monitoring should focus on:

```text
Lead ingestion
Order failures
Stock reservations
Invoice generation
Payment allocation
Dispatch updates
WhatsApp failures
Portal login
Performance
```

Capture:

- defect;
- severity;
- business impact;
- reproduction;
- fix;
- regression test.

---

# 131. Severity Matrix

## Critical

Examples:

- financial corruption;
- stock over-allocation;
- unauthorized access;
- invoice corruption;
- production-wide outage.

## High

Examples:

- core order flow blocked;
- territory bypass;
- payment allocation failure;
- major integration failure.

## Medium

Examples:

- report incorrect for one filter;
- non-critical screen error.

## Low

Examples:

- visual alignment;
- wording;
- minor usability.

---

# 132. Technical Debt Policy

Every known shortcut must have:

```text
Debt ID
Description
Risk
Reason
Workaround
Target phase
Owner
```

Do not silently accumulate architecture debt.

---

# 133. Documentation Set

The final repository should contain:

```text
README.md
docs/architecture/system-architecture.md
docs/architecture/module-map.md
docs/architecture/database.md
docs/architecture/security.md
docs/api/openapi.yaml
docs/business-rules/business-rules.md
docs/business-rules/pricing.md
docs/business-rules/schemes.md
docs/business-rules/territory.md
docs/business-rules/fefo.md
docs/workflows/order-to-dispatch.md
docs/workflows/lead-to-party.md
docs/workflows/payment.md
docs/test-cases/
docs/deployment/
docs/runbooks/
docs/decisions/
```

---

# 134. Final Module Dependency Matrix

| Module | Depends On | Produces |
|---|---|---|
| Auth | Core | Identity |
| Users/Roles | Auth | Permissions |
| Masters | Core | Reference data |
| Sales Team | Auth + Masters | Ownership |
| Leads | Masters + Sales Team | Prospects |
| Follow-ups | Leads | Tasks |
| Parties | Leads + Masters | Customers |
| Territory | Parties + Pincode | Area rules |
| Products | Masters | Catalogue |
| Pricing | Products + Party | Rates |
| Schemes | Products + Pricing | Commercial rules |
| Orders | Party + Product + Pricing + Scheme + Territory | Transactions |
| Inventory | Products + Warehouses | Stock |
| FEFO | Inventory + Orders | Batch allocation |
| Billing | Orders + Inventory | Invoices |
| Payments | Billing + Party | Outstanding |
| Dispatch | Billing + Inventory | Shipment |
| Portal | Parties + Orders + Billing + Dispatch + Payments | Self-service |
| Webhooks | Leads + Integration | Lead ingestion |
| WhatsApp | Notifications + Integration | Messaging |
| Reports | All transactional modules | Analytics |
| Audit | All modules | Traceability |

---

# 135. Recommended Implementation Order at File Level

When writing code, implement in this order:

```text
01. bootstrap/
02. app/Core/
03. app/Config/
04. database/migrations/
05. Auth/
06. Roles/Permissions/
07. Users/
08. Masters/
09. Sales Team/
10. Leads/
11. Follow-ups/
12. Parties/
13. Territory/
14. Products/
15. Pricing/
16. Schemes/
17. Orders/
18. Inventory/
19. FEFO/
20. Billing/
21. Payments/
22. Dispatch/
23. Notifications/
24. Webhooks/
25. WhatsApp/
26. Portal/
27. Reports/
28. Exports/
29. Security hardening/
30. Performance hardening/
31. UAT/
32. Production/
```

---

# 136. Recommended First Database Migration Set

Suggested order:

```text
001_users
002_roles
003_permissions
004_role_permissions
005_user_roles
006_user_sessions
007_states
008_districts
009_cities
010_pincodes
011_lead_sources
012_lead_statuses
013_follow_up_types
014_party_types
015_product_categories
016_pricing_tiers
017_payment_modes
018_order_statuses
019_dispatch_statuses
020_transporters
021_leads
022_lead_activities
023_follow_ups
024_parties
025_party_addresses
026_party_territories
027_products
028_product_prices
029_schemes
030_scheme_rules
031_orders
032_order_items
033_inventory_batches
034_inventory_movements
035_stock_reservations
036_invoices
037_invoice_items
038_dispatches
039_payments
040_payment_allocations
041_notifications
042_whatsapp_messages
043_webhook_sources
044_webhook_events
045_audit_logs
046_api_idempotency_keys
047_job_queue
048_job_attempts
049_files
050_file_links
```

---

# 137. Suggested API Versioning Policy

```text
/api/v1
```

Use v1 for the initial release.

Create v2 only when:

- response semantics materially change;
- authentication model changes;
- resource shape breaks clients;
- state transition semantics become incompatible.

Do not create a new version merely because a new field is added.

---

# 138. Recommended Internal Service Names

```text
AuthService
PermissionService
LeadService
LeadAssignmentService
LeadConversionService
FollowUpService
PartyService
TerritoryService
TerritoryValidationService
ProductService
PricingService
SchemeService
OrderService
OrderValidationService
InventoryService
StockReservationService
FefoAllocationService
BillingService
InvoiceService
DispatchService
PaymentService
OutstandingService
NotificationService
WebhookService
WebhookProcessingService
WhatsAppService
ReportService
ExportService
AuditService
FileService
```

---

# 139. Recommended Policy Names

```text
LeadPolicy
FollowUpPolicy
PartyPolicy
ProductPolicy
PricingPolicy
SchemePolicy
OrderPolicy
InventoryPolicy
InvoicePolicy
DispatchPolicy
PaymentPolicy
ReportPolicy
PortalPolicy
```

---

# 140. Recommended Repository Contracts

```text
UserRepository
RoleRepository
LeadRepository
FollowUpRepository
PartyRepository
TerritoryRepository
PincodeRepository
ProductRepository
PricingRepository
SchemeRepository
OrderRepository
InventoryBatchRepository
StockReservationRepository
InvoiceRepository
DispatchRepository
PaymentRepository
NotificationRepository
WebhookEventRepository
AuditRepository
FileRepository
```

---

# 141. Recommended Query Services

```text
LeadQuery
DashboardQuery
PartyQuery
ProductQuery
OrderQuery
InventoryQuery
InvoiceQuery
DispatchQuery
PaymentQuery
OutstandingQuery
ReportQuery
AuditQuery
```

Separate read-oriented queries from write-oriented repositories when report complexity grows.

---

# 142. Business Rule Priority

When multiple rules apply, use this order:

```text
1. Security / authorization
2. Data integrity
3. Regulatory / statutory configuration
4. Territory
5. Party state
6. Pricing eligibility
7. Scheme eligibility
8. Credit rules
9. Inventory
10. Workflow state
11. Notifications
```

This ordering is an engineering precedence model, not a statutory legal hierarchy.

---

# 143. Rule Execution During Order Confirmation

```text
AUTHORIZATION
      ↓
PARTY ACTIVE?
      ↓
ADDRESS VALID?
      ↓
TERRITORY ALLOWED?
      ↓
PRICING RESOLVED?
      ↓
SCHEME CALCULATED?
      ↓
CREDIT RULE PASSED?
      ↓
STOCK AVAILABLE?
      ↓
RESERVATION CREATED?
      ↓
ORDER CONFIRMED
      ↓
AUDIT
      ↓
NOTIFICATION EVENT
```

---

# 144. Failure Handling During Order Confirmation

If any step fails:

```text
No partial confirmation
No orphan reservation
No incorrect invoice
No false notification
No missing audit
```

The user must see a human-readable error.

The technical log must retain the request ID and error category.

---

# 145. Failure Handling During Billing

If batch allocation succeeds but invoice creation fails:

```text
Entire transaction rolls back
```

If external notification fails after invoice commit:

```text
Invoice remains committed.
Notification is queued for retry.
```

This distinction is essential:

**Core transaction failure != notification failure.**

---

# 146. Failure Handling During Dispatch

If LR save succeeds but WhatsApp notification fails:

```text
Dispatch stays committed.
WhatsApp enters FAILED/RETRY state.
```

Never roll back a completed dispatch only because a notification provider failed.

---

# 147. Failure Handling During Webhook

If lead creation succeeds but notification fails:

```text
Lead remains created.
Notification enters retry queue.
Webhook may return successful processing.
```

If database processing fails:

```text
Webhook event remains retryable.
No duplicate lead on subsequent retry.
```

---

# 148. Data Consistency Rules

Never allow:

```text
Order total != sum(order items)
Invoice total != invoice item snapshot
Reserved stock > available
Allocated payment > allowable amount
Party outstanding < 0 without explicit advance policy
```

Add reconciliation jobs to detect anomalies.

---

# 149. Reconciliation Jobs

Daily:

```text
Reconcile invoice totals
Reconcile outstanding
Reconcile stock reservations
Reconcile inventory movement balances
Reconcile webhook event states
Reconcile notification delivery states
```

Any discrepancy should create an operational alert.

---

# 150. Production Data Safety

Before migrations involving:

- invoice tables;
- payments;
- inventory;
- order items;
- party identifiers;

perform:

```text
backup
migration dry-run
row-count validation
constraint validation
application smoke test
```

---

# 151. Project Governance

## Technical ownership

Responsibilities:

- architecture;
- code quality;
- security;
- deployment;
- technical documentation.

## Business ownership

Responsibilities:

- pricing;
- scheme;
- credit;
- territory policy;
- workflow approval;
- commercial acceptance.

## QA ownership

Responsibilities:

- test evidence;
- defect tracking;
- regression.

## UAT ownership

Responsibilities:

- business acceptance.

---

# 152. Stakeholder Sign-Off Gates

Required sign-offs:

```text
Gate 01 — Scope Baseline
Gate 02 — UX Baseline
Gate 03 — Database Baseline
Gate 04 — API Baseline
Gate 05 — Business Rules
Gate 06 — Phase Acceptance
Gate 07 — UAT
Gate 08 — Production Release
```

---

# 153. Scope Protection

Every new request must state:

```text
New Requirement
Reason
Business Value
Affected Screens
Affected APIs
Affected Tables
Security Impact
Test Impact
Estimated Complexity
Release Impact
```

No hidden scope should enter the implementation backlog.

---

# 154. Release Candidate Criteria

A release candidate is allowed only when:

```text
[ ] Scope locked
[ ] Open decisions resolved
[ ] Migrations tested
[ ] API contract complete
[ ] Tests pass
[ ] Security review complete
[ ] Performance baseline met
[ ] UAT evidence available
[ ] Rollback tested
[ ] Deployment runbook complete
```

---

# 155. Final Product Acceptance Checklist

## Functional

```text
[ ] Leads
[ ] Follow-ups
[ ] Parties
[ ] Territory
[ ] Products
[ ] Pricing
[ ] Schemes
[ ] Orders
[ ] Inventory
[ ] FEFO
[ ] Near expiry
[ ] Billing
[ ] Dispatch
[ ] Payments
[ ] Portal
[ ] Notifications
[ ] Webhooks
[ ] Reports
[ ] Audit
```

## Technical

```text
[ ] Core PHP framework
[ ] Custom MVVM
[ ] Responsive mobile-first UI
[ ] REST API
[ ] OpenAPI/Swagger contract
[ ] Secure authentication
[ ] Server-side authorization
[ ] Transaction-safe financial operations
[ ] Transaction-safe stock operations
[ ] Structured logging
[ ] Error tracking
[ ] Background jobs
[ ] Backup / restore
[ ] Health checks
```

## Quality

```text
[ ] Unit
[ ] Integration
[ ] Feature
[ ] Security
[ ] Performance
[ ] UAT
[ ] Regression
```

---

# 156. Final Architecture Summary

```text
                 PHARMA CRM
                     |
     +---------------+----------------+
     |               |                |
   CRM Core       Commerce         Operations
     |               |                |
 Leads           Products          Inventory
 Follow-ups      Pricing           FEFO
 Parties         Schemes           Billing
 Sales Team      Orders            Dispatch
 Territory                        Payments
     |               |                |
     +---------------+----------------+
                     |
                Integrations
                     |
       +-------------+-------------+
       |                           |
   Webhooks                    WhatsApp
       |                           |
    Lead Intake                 Notifications
                     |
                Distributor Portal
                     |
                 Reporting
                     |
                   Audit
```

---

# 157. Final Engineering Position

The architecture should remain a **modular, secure, transaction-safe custom PHP application** rather than a collection of page-level CRUD scripts.

The most sensitive domain areas are:

1. Territory enforcement.
2. Pricing.
3. Schemes.
4. Stock reservation.
5. FEFO.
6. Billing.
7. Payments.
8. Authorization.
9. Webhooks.
10. Audit.

These areas should receive the strongest automated test coverage and code-review scrutiny.

The frontend must remain responsive and lightweight, but the frontend is never the authority for business rules.

The API contract must be maintained alongside the implementation.

The database must preserve historical commercial truth.

Every integration must be isolated behind an adapter.

Every state-changing business action must be auditable.

Every production deployment must be recoverable.

---

# 158. Final Build Definition

The project is implementation-complete only when all of the following are simultaneously true:

```text
FUNCTIONAL
+ SECURITY
+ DATA INTEGRITY
+ PERFORMANCE
+ AUDITABILITY
+ TESTABILITY
+ DOCUMENTATION
+ OPERABILITY
+ UAT ACCEPTANCE
= PRODUCTION READY
```

The FRS remains the functional baseline. Any change to scope, field, workflow, status, role, integration, pricing rule, territory rule, inventory rule or notification behavior must go through the defined change-control process.

---

# 159. Appendix A — Source Requirement Coverage

| FRS Area | Roadmap Coverage |
|---|---|
| Project Objective | Sections 1–3 |
| Scope | Sections 2–3 |
| Roles | Sections 20, 152 |
| End-to-End Workflow | Sections 3, 143 |
| Lead Management | Sections 10.2, 30, 52 |
| Follow-ups | Sections 10.3, 52 |
| Lead Webhooks | Sections 10.4, 26, 54 |
| Party Management | Section 10.5 |
| Territory | Sections 10.6, 29, 53 |
| Products | Section 10.7 |
| Pricing | Section 10.8 |
| Schemes | Section 10.9 |
| Orders | Section 10.10 |
| Inventory | Section 10.11 |
| FEFO | Section 10.12, 27 |
| Near Expiry | Section 10.14 |
| Billing | Section 10.15 |
| Dispatch | Section 10.16 |
| Distributor Portal | Section 10.17 |
| WhatsApp | Section 10.18 |
| Payments | Section 10.19 |
| Sales Team | Section 10.1 / 52 |
| Dashboard | Section 32 |
| Reports | Sections 36, 72 |
| Masters | Section 52 |
| Permission Matrix | Section 20 |
| Core Business Rules | Sections 14–15, 48–49 |
| Security / NFR | Sections 19–24, 120–122 |
| Entities | Section 11 |
| API | Sections 16–18 |
| Acceptance Criteria | Sections 77–82 |
| QA | Sections 44–47 |
| Release Plan | Sections 51–56 |
| Open Decisions | Section 74 |
| Definition of Ready | Section 48 |
| Definition of Done | Section 49 |
| Change Control | Section 153 |

---

# 160. Appendix B — Recommended Initial Backlog Epics

```text
EPIC-01 Platform Core
EPIC-02 Authentication & Authorization
EPIC-03 Users & Sales Team
EPIC-04 Masters
EPIC-05 Lead Management
EPIC-06 Follow-ups
EPIC-07 Party / Franchise Management
EPIC-08 Territory & Pincode
EPIC-09 Products
EPIC-10 Pricing
EPIC-11 Scheme Engine
EPIC-12 Orders
EPIC-13 Inventory
EPIC-14 FEFO
EPIC-15 Near Expiry
EPIC-16 Billing
EPIC-17 Dispatch
EPIC-18 Payments
EPIC-19 Distributor Portal
EPIC-20 Notifications
EPIC-21 WhatsApp
EPIC-22 Webhooks
EPIC-23 Reports
EPIC-24 Audit
EPIC-25 Security Hardening
EPIC-26 Performance
EPIC-27 QA/UAT
EPIC-28 Deployment/Operations
```

---

# 161. Appendix C — Recommended Story Format

```text
Story ID:
Title:

Business Objective:

Actor:

Preconditions:

User Flow:

Acceptance Criteria:

Business Rules:

Validation:

Permission:

Database Impact:

API Impact:

UI Impact:

Audit Impact:

Integration Impact:

Error States:

Edge Cases:

Test Cases:

Definition of Done:
```

---

# 162. Appendix D — Recommended Technical Task Format

```text
Task ID:
Epic:
Module:

Objective:

Files / Components:

Database:
- migration:
- indexes:
- constraints:

Backend:
- controller:
- service:
- repository:
- policy:
- validator:

Frontend:
- view:
- viewmodel:
- js:
- css:

API:
- endpoint:
- method:
- request:
- response:
- errors:

Security:
- authentication:
- authorization:
- validation:
- audit:

Testing:
- unit:
- feature:
- integration:
- security:

Documentation:
- OpenAPI:
- README:
- business rules:

Rollback:
```

---

# 163. Appendix E — Non-Negotiable Engineering Rules

```text
RULE-001
No business rule is frontend-only.

RULE-002
No financial operation without transaction control.

RULE-003
No stock reservation without concurrency protection.

RULE-004
No historical invoice recalculation from current masters.

RULE-005
No permission decision based only on hidden UI controls.

RULE-006
No webhook processing without authentication/signature verification when supported.

RULE-007
No duplicate external event creation.

RULE-008
No production secrets in source control.

RULE-009
No raw SQL in controllers or views.

RULE-010
No API behavior change without API documentation update.

RULE-011
No production release without tested rollback procedure.

RULE-012
No critical commercial rule is implemented from assumption.

RULE-013
No destructive data change without backup/recovery validation.

RULE-014
No third-party runtime plugin may be introduced without architecture approval.

RULE-015
No change to scope bypasses change control.
```

---

# 164. Appendix F — Final Implementation Sequence

```text
DISCOVER
   ↓
FREEZE BASELINE
   ↓
DEFINE DECISIONS
   ↓
DESIGN ARCHITECTURE
   ↓
DESIGN DATABASE
   ↓
DESIGN API
   ↓
BUILD CORE PHP
   ↓
BUILD AUTH
   ↓
BUILD CRM
   ↓
BUILD COMMERCE
   ↓
BUILD INVENTORY
   ↓
BUILD OPERATIONS
   ↓
BUILD PORTAL
   ↓
BUILD INTEGRATIONS
   ↓
BUILD REPORTS
   ↓
SECURITY HARDENING
   ↓
PERFORMANCE HARDENING
   ↓
REGRESSION
   ↓
UAT
   ↓
PRODUCTION
   ↓
HYPERCARE
   ↓
CONTROLLED ITERATION
```

---

# 165. Appendix G — Final Technical Stack Statement

```text
Frontend:
- HTML5
- CSS3
- Responsive Mobile-First UI
- Lightweight Vanilla JavaScript / ES Modules
- Custom reusable component system

Backend:
- Core PHP
- Custom lightweight framework
- Custom MVVM
- REST-style API
- Server-side rendered views

Database:
- MySQL-compatible relational database
- Transaction-safe storage
- Foreign keys
- Indexed queries

API Documentation:
- OpenAPI / Swagger-compatible contract
- Versioned API
- Local/self-hosted documentation approach

Infrastructure:
- HTTPS
- Web server
- PHP runtime
- SQL database
- Cron scheduler
- Background worker
- Backup system
- Log/monitoring system

Architecture:
- Modular monolith
- Service layer
- Repository layer
- Policy layer
- Validation layer
- Audit layer
- Integration adapter layer

Security:
- Session security
- CSRF
- Prepared statements
- Output escaping
- RBAC
- Object-level authorization
- Rate limiting
- Audit
- Secure file handling
- Secret isolation

Quality:
- Unit tests
- Integration tests
- Feature tests
- API tests
- Security tests
- Performance tests
- UAT
- Regression
```

---

# 166. End of Document

This README is the implementation roadmap and engineering operating baseline for the Pharma CRM / PCD Franchise CRM system.

**Primary source:** Pharma CRM Master FRS v2.0 supplied with the project.  
**Implementation style requested:** Core PHP + custom lightweight framework + custom MVVM + secure/balanced/scalable/reliable responsive mobile-first web application + API + OpenAPI/Swagger-compatible documentation + no third-party runtime plugins.

