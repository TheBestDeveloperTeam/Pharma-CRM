# Pharma CRM Project - Development Tasks

This is the main development task tracker for the Pharma CRM project. It follows a strictly ordered, phase-based (P0-P8) baby-step build approach.

## HOW TO USE THIS FILE
1. Start at the top. Do not skip tasks.
2. Update the status of a task to `[/]` when starting, and `[x]` when verified.
3. Commit after every task (atomic commits).
4. Never move to the next task until the current Verify criteria pass.

## GOLDEN ENGINEERING RULES (Non-Negotiable)
- **R01** Every tenant row carries org_ref + franchise_ref — no exceptions
- **R02** Every unique constraint on business data is tenant-scoped (composite, franchise-led)
- **R03** Every business write (POST/PUT/PATCH) passes IdempotencyMiddleware
- **R04** Every state change writes audit_logs (actor, tenant, request_id, before/after)
- **R05** No business rule lives only in JavaScript — always server-side enforced
- **R06** No stock reservation without FOR UPDATE + guarded UPDATE + reservation row
- **R07** No invoice/order/payment number without SequenceService (never MAX+1)
- **R08** No cross-tenant query without withoutTenantScope('reason') + audit log
- **R09** No color literal in views — theme CSS variables only
- **R10** No third-party runtime code: PHP, JS, CSS, fonts, icons, CDN. Every new dep = ADR
- **R11** No release without tested rollback and green agents on staging
- **R12** Schema change = new full schema file + re-seed on staging first. No migration tooling ever
- **R13** Tokens never in cookies; refresh token never in localStorage; access token never persisted
- **R14** Server-side computation of price, scheme, territory, credit, stock. Client values = display only
- **R15** Every new endpoint ships with: policy, validator, audit, agent case (incl. IDOR), OpenAPI entry
- **R16** PDO emulation OFF, ERRMODE_EXCEPTION, prepared statements only — no raw concatenated SQL ever

---

# PHASE P0: Foundation
**Goal**: Scaffold the application architecture without frameworks, establish strict rules and build core services.
**Duration**: 18 steps
**Gate**: All P0 test agents green.

## TASK P0-S01 — Repo Scaffold
Status: [x]
### Goal
Set up the initial directory tree and basic file structure.
### Why
A clean, framework-less structure requires predefined locations for classes, views, and public assets to maintain R10.
### Strategy
Create standard MVC/Service directories. Ensure public isolation.
### Baby Steps
1. Create directories: `app/`, `app/Core/`, `app/Http/Controllers/`, `app/Models/`, `app/Repositories/`, `app/Services/`, `bootstrap/`, `config/`, `database/schema/`, `public/`, `tests/`, `cli/`.
2. Create `.gitignore` ignoring vendor, .env, and logs.
3. Create `public/.htaccess` to route all requests to index.php.
4. Create `public/index.php` as a stub.
### Files Created/Modified
- `public/.htaccess`
- `public/index.php`
- `.gitignore`
### Verify
`php -S localhost:8000 -t public` and verify access to `index.php`.

## TASK P0-S02 — Autoloader
Status: [x]
### Goal
Implement PSR-4 compliant autoloader.
### Why
We are not using Composer for application code to strictly control dependencies (R10).
### Strategy
Map `App\` namespace to `app/` directory using `spl_autoload_register`.
### Baby Steps
1. Create `bootstrap/autoload.php`.
2. Write function to convert namespace backslashes to directory separators and `require` the file.
```php
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $base_dir = __DIR__ . '/../app/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
    if (file_exists($file)) require $file;
});
```
3. Include it in `public/index.php`.
### Files Created/Modified
- `bootstrap/autoload.php`
- `public/index.php`
### Verify
Create a dummy class `App\Core\Test` and instantiate it in `index.php`.

## TASK P0-S03 — Env + Config
Status: [x]
### Goal
Parse environment variables without external libraries and provide config via dot notation.
### Why
Security and flexibility (R10).
### Strategy
Read `.env` file manually, populate `$_ENV`, and create a Config singleton.
### Baby Steps
1. Create `app/Support/env.php` to parse `.env` line by line, ignoring comments `#`.
2. Create `app/Support/Config.php` holding array configuration and a `get('app.name')` method.
3. Create `config/app.php`.
### Files Created/Modified
- `app/Support/env.php`
- `app/Support/Config.php`
- `config/app.php`
- `.env.example`
### Verify
`php -r "require 'bootstrap/autoload.php'; echo \App\Support\Config::get('app.env');"`

## TASK P0-S04 — DI Container
Status: [x]
### Goal
Implement Dependency Injection Container with auto-wiring.
### Why
Essential for testability and managing core services without massive singletons.
### Strategy
Use PHP `ReflectionClass` to inspect constructors and recursively resolve dependencies.
### Baby Steps
1. Create `app/Core/Container.php` with `$bindings` and `$instances`.
2. Implement `bind()`, `singleton()`, and `make()`.
3. Implement reflection auto-wiring in `make()`.
### Files Created/Modified
- `app/Core/Container.php`
### Verify
Write a quick 3-level dependency script (A depends on B, B depends on C) and `make(A::class)`.

## TASK P0-S05 — Request/Response
Status: [x]
### Goal
Immutable Request object and standard Response factories.
### Why
Ensure predictable IO and enforce 1MB JSON limits.
### Strategy
Parse globals securely.
### Baby Steps
1. Create `app/Core/Request.php` mapping `$_SERVER`, `$_GET`, `$_POST`, and reading `php://input` up to 1MB.
2. Create `app/Core/Response.php` with static factories `json()`, `html()`, `error()`.
3. Create `app/Core/RequestId.php` for unique trace IDs.
### Files Created/Modified
- `app/Core/Request.php`
- `app/Core/Response.php`
- `app/Core/RequestId.php`
### Verify
Dump a Request object instantiated from globals in `index.php`.

## TASK P0-S06 — Router
Status: [x]
### Goal
Regex-based router with placeholder support `{param}` and middleware groups.
### Why
Efficient routing without external dependencies.
### Strategy
Store routes in an array grouped by HTTP method. Compile placeholders to regex.
### Baby Steps
1. Create `app/Core/Router.php`.
2. Implement `add()`, `get()`, `post()`, etc.
3. Implement `dispatch(Request $request)` to match regex and extract variables.
4. Return 404 or 405 appropriately.
### Files Created/Modified
- `app/Core/Router.php`
- `bootstrap/routes.php`
### Verify
Register a test route `/api/users/{id}` and dispatch a fake Request object.

## TASK P0-S07 — Middleware Pipeline
Status: [x]
### Goal
Onion-style middleware executor.
### Why
Intercept requests for auth, logging, and idempotency.
### Strategy
Use array_reduce or recursive closures to wrap handlers.
### Baby Steps
1. Create `app/Core/Pipeline.php`.
2. Implement `SecurityHeaders.php` middleware.
3. Assemble `bootstrap/app.php` kernel.
### Files Created/Modified
- `app/Core/Pipeline.php`
- `app/Http/Middleware/SecurityHeaders.php`
- `bootstrap/app.php`
### Verify
Pass request through pipeline and check headers in Response.

## TASK P0-S08 — Exceptions
Status: [x]
### Goal
Domain-specific exceptions mapped to HTTP codes.
### Why
Standardize error handling and API responses.
### Strategy
Create a base AppException and extend it.
### Baby Steps
1. Create `app/Core/Exceptions/AppException.php`.
2. Create `ValidationException` (422), `NotFoundException` (404), etc.
3. Add a global exception handler in the Kernel to catch these and return `Response::error()`.
### Files Created/Modified
- `app/Core/Exceptions/*.php`
### Verify
Throw a `NotFoundException` in a route and verify a 404 JSON response.

## TASK P0-S09 — Logger
Status: [x]
### Goal
JSON-lines structured logger.
### Why
Easily ingestible by log aggregators, safe redaction of secrets (R04).
### Strategy
Write to `storage/logs/app.log` and redact sensitive keys.
### Baby Steps
1. Create `app/Core/Logger.php`.
2. Implement `info()`, `error()` etc.
3. Implement recursive redaction logic for keys like `password`, `token`.
### Files Created/Modified
- `app/Core/Logger.php`
### Verify
Log an array containing a password and verify it writes `[REDACTED]` in the file.

## TASK P0-S10 — Database
Status: [x]
### Goal
Secure PDO wrapper (R16).
### Why
Prevent SQL injection and manage transactions seamlessly.
### Strategy
Enforce ERRMODE_EXCEPTION, disable emulated prepares.
### Baby Steps
1. Create `app/Core/Database.php`.
2. Create `app/Core/Transaction.php` handling nested transactions via savepoints.
### Files Created/Modified
- `app/Core/Database.php`
- `app/Core/Transaction.php`
- `config/database.php`
### Verify
Connect to DB and execute a SELECT 1 query.

## TASK P0-S11 — FileCache
Status: [x]
### Goal
Simple atomic file cache.
### Why
Idempotency and rate-limiting require cache, no Redis yet to keep stack simple.
### Strategy
Write to temp file, `rename()` for atomicity, use `flock`.
### Baby Steps
1. Create `app/Core/FileCache.php`.
2. Implement `get()`, `set()`, `delete()` using serialization and TTL checks.
### Files Created/Modified
- `app/Core/FileCache.php`
### Verify
Write a value, read it, let it expire, verify it returns null.

## TASK P0-S12 — Validation
Status: [x]
### Goal
Declarative validation array engine.
### Why
Avoid boilerplate ifs in controllers.
### Strategy
Parse string rules (`required|email|max:255`).
### Baby Steps
1. Create `app/Core/Validation.php`.
2. Implement rule parsing and error aggregation.
### Files Created/Modified
- `app/Core/Validation.php`
### Verify
Validate a fake payload and catch `ValidationException`.

## TASK P0-S13 — RefGenerator + SequenceService
Status: [x]
### Goal
Generate unique public IDs and atomic sequential numbers.
### Why
Never use auto-increment IDs in APIs. (R07)
### Strategy
Crockford Base32 for Refs. `INSERT ... ON DUPLICATE KEY UPDATE` for Sequences.
### Baby Steps
1. Create `app/Core/RefGenerator.php` (e.g. `ORD-7K3M...`).
2. Create `app/Core/SequenceService.php`.
```php
// INSERT INTO sequence_counters (tenant_ref, sequence_key, last_value) VALUES (?, ?, 1)
// ON DUPLICATE KEY UPDATE last_value = last_value + 1
```
### Files Created/Modified
- `app/Core/RefGenerator.php`
- `app/Core/SequenceService.php`
### Verify
Generate 10 references and 10 sequence numbers.

## TASK P0-S14 — TenantContext/TenantScope
Status: [x]
### Goal
Enforce multi-tenancy rules (R01, R08).
### Why
Prevent cross-tenant data leaks.
### Strategy
A Context object holds current `org_ref` and `franchise_ref`. Repositories auto-inject these in queries.
### Baby Steps
1. Create `app/Core/TenantContext.php`.
2. Create `app/Repositories/TenantRepository.php`.
### Files Created/Modified
- `app/Core/TenantContext.php`
- `app/Repositories/TenantRepository.php`
### Verify
Initialize TenantContext and verify Repository adds `WHERE franchise_ref = ?`.

## TASK P0-S15 — Schema File Part 1
Status: [x]
### Goal
Define base schema (R12).
### Why
Database structure for tenancy, users, and core counters.
### Strategy
Write raw SQL in a single file. Create a CLI script to run it.
### Baby Steps
1. Create `database/schema/001_full_schema.sql`.
2. Define tables: organizations, franchises, users, oauth_clients, sequence_counters.
3. Ensure composite FKs.
4. Create `cli/migrate.php` to execute the file.
### Files Created/Modified
- `database/schema/001_full_schema.sql`
- `cli/migrate.php`
### Verify
Run `php cli/migrate.php` and verify tables in DB.

## TASK P0-S16 — Test Harness
Status: [x]
### Goal
Custom testing utilities.
### Why
Zero dependencies testing.
### Strategy
Implement simple Assert methods and an HTTP client wrapper for local testing.
### Baby Steps
1. Create `tests/Support/Harness.php`.
2. Create `tests/Support/Http.php`.
3. Create `cli/test-agents.php`.
### Files Created/Modified
- `tests/Support/Harness.php`
- `tests/Support/Http.php`
- `cli/test-agents.php`
### Verify
Run `php cli/test-agents.php` (should report 0 tests run successfully).

## TASK P0-S17 — Health Endpoints
Status: [x]
### Goal
Add `/health` and `/ready` routes.
### Why
Infrastructure monitoring.
### Strategy
Return 200 OK. Check DB connection in `/ready`.
### Baby Steps
1. Create `app/Http/Controllers/Api/V1/HealthController.php`.
2. Wire routes in `bootstrap/routes.php`.
### Files Created/Modified
- `app/Http/Controllers/Api/V1/HealthController.php`
- `bootstrap/routes.php`
### Verify
Hit `/health` and `/ready` via curl.

## TASK P0-S18 — agent-lint v1
Status: [x]
### Goal
Enforce coding standards (R10).
### Why
Automated check for prohibited patterns.
### Strategy
Regex scans over PHP/JS files.
### Baby Steps
1. Create `tests/Agents/agent-lint.php`.
2. Ban `innerHTML`, `localStorage` (for tokens), CDN URLs.
### Files Created/Modified
- `tests/Agents/agent-lint.php`
### Verify
Run lint agent and ensure it passes on current codebase.

---

# PHASE P1: Auth + Tenancy + Themes + Shells
**Goal**: Secure endpoints, authenticate users, apply tenant scopes, and serve themed shells.
**Duration**: 20 steps
**Gate**: agent-auth, agent-tenancy, agent-theme, agent-users pass.

## TASK P1-S01 — Password Hasher
Status: [x]
### Goal
Secure password hashing using Argon2id or Bcrypt.
### Why
Security baseline.
### Strategy
Wrap `password_hash()` and `password_verify()`.
### Baby Steps
1. Create `app/Services/Auth/PasswordHasher.php`.
### Files Created/Modified
- `app/Services/Auth/PasswordHasher.php`
### Verify
Hash a password and verify it matches.

## TASK P1-S02 — JWT Service
Status: [x]
### Goal
Sign and verify JWTs natively.
### Why
Stateless authentication without external libs (R10, R13).
### Strategy
Base64Url encode headers/payload and HMAC SHA-256 sign.
### Baby Steps
1. Create `app/Services/Auth/JwtService.php`.
2. Implement sign and verify functions.
### Files Created/Modified
- `app/Services/Auth/JwtService.php`
### Verify
Sign a payload, verify it, tamper with it and ensure verification fails.

## TASK P1-S03 — Rate Limiter
Status: [x]
### Goal
Protect auth endpoints from brute force.
### Why
Security against credential stuffing.
### Strategy
Use FileCache to increment hits per IP/Identifier.
### Baby Steps
1. Create `app/Services/Security/RateLimiter.php`.
2. Add `hit()`, `tooManyAttempts()`, `clear()`.
### Files Created/Modified
- `app/Services/Security/RateLimiter.php`
### Verify
Hit it 5 times, ensure 6th time returns true for `tooManyAttempts()`.

## TASK P1-S04 — Token Service
Status: [x]
### Goal
Manage access and refresh tokens.
### Why
Implement refresh token rotation and family revocation.
### Strategy
Store refresh tokens in DB (oauth_refresh_tokens).
### Baby Steps
1. Create `app/Services/Auth/TokenService.php`.
### Files Created/Modified
- `app/Services/Auth/TokenService.php`
### Verify
Generate a token pair, store refresh token in DB.

## TASK P1-S05 — OAuth Endpoint
Status: [x]
### Goal
Implement POST `/oauth/token`.
### Why
Standard OAuth2 password grant flow.
### Strategy
Validate credentials, rate limit, issue JWT.
### Baby Steps
1. Create `app/Http/Controllers/Api/V1/OAuthController.php`.
2. Bind to routes.
### Files Created/Modified
- `app/Http/Controllers/Api/V1/OAuthController.php`
### Verify
Send invalid creds -> 401. Valid creds -> 200 with tokens.

## TASK P1-S06 — BearerAuth Middleware
Status: [x]
### Goal
Validate JWTs on protected routes.
### Why
Guard APIs.
### Strategy
Extract Bearer token, verify signature, set User in Request.
### Baby Steps
1. Create `app/Http/Middleware/BearerAuth.php`.
### Files Created/Modified
- `app/Http/Middleware/BearerAuth.php`
### Verify
Access protected route without token -> 401. With token -> 200.

## TASK P1-S07 — Tenant Middleware
Status: [x]
### Goal
Extract Tenant Context from authenticated user (R01).
### Why
Ensure all requests run in correct tenant scope.
### Strategy
Read `franchise_ref` from User/Token and set in `TenantContext`.
### Baby Steps
1. Create `app/Http/Middleware/TenantScopeMiddleware.php`.
### Files Created/Modified
- `app/Http/Middleware/TenantScopeMiddleware.php`
### Verify
Hit endpoint, ensure `TenantContext` has correct refs.

## TASK P1-S08 — Role Middleware
Status: [x]
### Goal
RBAC at the route level.
### Why
Differentiate Admin vs Sales reps.
### Strategy
Pass allowed roles to middleware.
### Baby Steps
1. Create `app/Http/Middleware/RequireRole.php`.
### Files Created/Modified
- `app/Http/Middleware/RequireRole.php`
### Verify
Access admin route with sales token -> 403 Forbidden.

## TASK P1-S09 — Auth Endpoints
Status: [x]
### Goal
Implement logout and me endpoints.
### Why
Session management and UI hydration.
### Strategy
Delete refresh token on logout.
### Baby Steps
1. Add `logout` and `me` to `OAuthController.php`.
### Files Created/Modified
- `app/Http/Controllers/Api/V1/OAuthController.php`
### Verify
Call /logout, verify token revoked.

## TASK P1-S10 — Audit Service
Status: [x]
### Goal
Log all state changes (R04).
### Why
Compliance and traceability.
### Strategy
Service to insert into `audit_logs`.
### Baby Steps
1. Create `app/Services/Audit/AuditService.php`.
### Files Created/Modified
- `app/Services/Audit/AuditService.php`
### Verify
Create a test record, verify audit log row is created.

## TASK P1-S11 to P1-S20 (UI, Shells, Themes, Impersonation, Agents)
*(Detailed similarly in practice, implementing CSS variables, Shell HTML, Role-based Dashboards, and specific agents: agent-auth, agent-tenancy, agent-theme, agent-users).*

---

# PHASE P2: Masters
**Goal**: Core configuration: Geo, Categories, Products, Pricing, Schemes.
**Duration**: 8 steps
**Gate**: agent-pricing, agent-schemes pass.

## TASK P2-S01 — Schema Masters
Status: [x]
### Goal
Tables for catalogue, pricing, territories.
### Why
Foundation for CRM and Orders.
### Strategy
Append to `001_full_schema.sql` (re-seed allowed per R12).
### Baby Steps
1. Add tables: categories, products, product_prices, schemes.
### Files Created/Modified
- `database/schema/001_full_schema.sql`
### Verify
Migrate fresh and check table existence.

## TASK P2-S02 — Pricing Resolver
Status: [x]
### Goal
Implement the strict priority pricing algorithm.
### Algorithm
1. Party-specific price (rate_source = PARTY)
2. Party's tier price (rate_source = TIER)
3. Product's franchise_rate (rate_source = DEFAULT)
### Baby Steps
1. Create `app/Services/Pricing/PriceResolver.php`.
2. Implement priority query.
### Files Created/Modified
- `app/Services/Pricing/PriceResolver.php`
### Verify
Unit test the priority logic.

## TASK P2-S03 to P2-S08 (Masters UI, Schemes, Money Helper)
*(Implement scheme calculator with 10+1 logic, Money Helper for precise decimal math, CRUD endpoints for products).*

---

# PHASE P3: CRM
**Goal**: Leads, Parties, Territories, Webhooks, Job Queue.
**Duration**: 15 steps
**Gate**: agent-leads, agent-territory, agent-webhooks pass.

## TASK P3-S01 — Territory Validator
Status: [x]
### Goal
Validate if a party can be served in a region.
### Algorithm
1. Resolve pincode → city → district → state
2. Load active territory rows for party
3. Check PINCODE match → ALLOWED
4. Check exclusive conflicts → BLOCKED_EXCLUSIVE
### Baby Steps
1. Create `app/Services/CRM/TerritoryValidator.php`.
### Files Created/Modified
- `app/Services/CRM/TerritoryValidator.php`
### Verify
Test matching and exclusive block scenarios.

## TASK P3-S02 — Lead State Machine
Status: [x]
### Goal
Strict transitions for Leads.
### Algorithm
NEW → ASSIGNED → CONTACTED → INTERESTED → ... → CONVERTED
### Baby Steps
1. Create `app/Services/CRM/LeadStateMachine.php`.
### Files Created/Modified
- `app/Services/CRM/LeadStateMachine.php`
### Verify
Try illegal transition (NEW -> CONVERTED) -> Throws exception.

## TASK P3-S03 to P3-S15 (Webhooks, Jobs, UI)
*(Implement HMAC verified webhooks, simple DB-backed job queue `SKIP LOCKED`, and CRM UI).*

---

# PHASE P4: Order-to-Cash
**Goal**: Inventory, Orders, FEFO allocation, Billing, Dispatch, Payments.
**Duration**: 15 steps
**Gate**: agent-fefo, agent-concurrency, agent-orders, agent-e2e pass.

## TASK P4-S01 — Schema Order-to-Cash
Status: [x]
### Goal
Add tables for inventory, orders, billing, payments.
### Why
Required for transaction processing.
### Strategy
Append to schema.
### Files Created/Modified
- `database/schema/001_full_schema.sql`

## TASK P4-S02 — Idempotency Middleware
Status: [x]
### Goal
Prevent double POSTs (R03).
### Why
Network retries shouldn't create 2 orders.
### Strategy
Check `Idempotency-Key` header, lock in FileCache, store response.
### Baby Steps
1. Create `app/Http/Middleware/IdempotencyMiddleware.php`.

## TASK P4-S03 — FEFO Allocator (CRITICAL)
Status: [x]
### Goal
Allocate inventory First-Expiry-First-Out (R06).
### Algorithm
1. SELECT batches FOR UPDATE (expiry ASC, id ASC).
2. Take available qty, UPDATE reserved_qty.
3. If remaining > 0 -> Rollback, throw 422.
### Baby Steps
1. Create `app/Services/Inventory/FefoAllocator.php`.
2. Implement strict DB locks.
### Files Created/Modified
- `app/Services/Inventory/FefoAllocator.php`
### Verify
Run concurrency agent. 2 parallel processes trying to reserve 80 of 100 must result in one success, one failure.

## TASK P4-S04 — Order State Machine
Status: [x]
### Goal
Manage order lifecycle.
### Algorithm
DRAFT → SUBMITTED → ... → DELIVERED
### Baby Steps
1. Create `app/Services/Orders/OrderStateMachine.php`.

## TASK P4-S05 to P4-S15 (Billing, Dispatch, Payments, e2e)
*(Detailed steps for precise invoice generation, tracking dispatch, allocating payments, and creating the end-to-end agent).*

---

# PHASE P5: Notifications + Workers
**Goal**: Event-driven emails, in-app bells, background tasks.
**Duration**: 7 steps
**Gate**: agent-jobs, notification service, and scanner tests pass.

- [x] **P5-S01 Notification Service & Adapters** — Created `NotificationService` with SHA-256 idempotency key hashing and pluggable adapters (`LogAdapter`, `EmailAdapter`, `WhatsAppAdapter`).
- [x] **P5-S02 Notification Endpoints** — `GET /api/v1/notifications`, `POST /api/v1/notifications/{ref}/read`, `POST /api/v1/notifications/read-all`.
- [x] **P5-S03 Reminder Scanner Service** — `ReminderScannerService` scanning due follow-ups (2hr window), overdue invoices, near-expiry batches (180 days), and expired stock reservation release.
- [x] **P5-S04 CLI Scheduler** — `cli/scheduler.php` supporting cPanel cron targets: `minute`, `five-minute`, `hourly`, and `daily`.

---

# PHASE P6: Distributor Portal
**Goal**: External portal for B2B ordering.
**Duration**: 7 steps
**Gate**: Portal authentication and distributor self-service endpoints pass.

- [x] **P6-S01 Portal Security & Context** — Strict `DISTRIBUTOR` role policy and `party_ref` isolation.
- [x] **P6-S02 Portal Catalogue & Pricing** — `GET /api/v1/portal/catalogue` returning applicable products with party-resolved tier pricing.
- [x] **P6-S03 Server-Side Cart Calculation** — `POST /api/v1/portal/cart/calculate` evaluating unit rates, 10+1 schemes, and GST breakdown.
- [x] **P6-S04 Portal Order Placement & Cancellation** — `POST /api/v1/portal/orders` with idempotency & deduplication, and `POST /api/v1/portal/orders/{ref}/cancel` (pre-confirm only).
- [x] **P6-S05 Portal Fulfillment & Ledger** — `GET /api/v1/portal/invoices`, `GET /api/v1/portal/dispatches`, `GET /api/v1/portal/outstanding`, and `GET/PATCH /api/v1/portal/profile`.

---

# PHASE P7: Super Admin + Reports + Hardening
**Goal**: Multi-tenant management, analytics, security checks.
**Duration**: 7 steps
**Gate**: Super admin stats, 12 reports, and contract parity pass.

- [x] **P7-S01 Super Metrics & Platform Audit** — `GET /api/v1/super/dashboard/stats`, `GET /api/v1/super/audit`, `GET /api/v1/super/security-events`.
- [x] **P7-S02 12 Report Services** — `ReportService` & `ReportsController` (`/api/v1/admin/reports/{type}`) covering sales summary, orders, invoices, payments, outstanding statement, party ledger, inventory status, near-expiry, lead funnel, scheme utilization, audit log, and tenant activity.
- [x] **P7-S03 Report CSV Streaming** — `GET /api/v1/admin/reports/{type}/export` generating streamed RFC 4180 CSV downloads.
- [x] **P7-S04 Contract Testing & Parity** — Created `tests/Agents/agent-openapi.php` verifying that 100% of routes match `openapi.yaml`.

---

# PHASE P8: cPanel Production
**Goal**: Production deployment readiness, backup drills, and installer tooling.
**Duration**: 8 steps
**Gate**: Backup verification drill and installer check pass.

- [x] **P8-S01 Production Health & Installer** — Created `cli/install.php` verifying PHP 8.1+, extensions, permissions, database, and schema.
- [x] **P8-S02 mysqldump-free Backup Utility** — Created `cli/backup.php` streaming chunked MySQL inserts into gzip compressed archives with `--verify`.
- [x] **P8-S03 Professional OpenAPI 3.0 / Swagger Overhaul** — Exhaustive update to `public/api-docs/openapi.yaml` documenting all 70+ endpoints across all surfaces with rich schemas, error envelope models, and parameters.

---
**End of File.**

