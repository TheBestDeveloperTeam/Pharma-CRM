# Pharma CRM & Sales Force Automation — CR-Roadmap v3.0
## Multi-Tenant Cloud Edition · cPanel Shared Hosting · Bearer-JWT (OAuth 2.0) · AdminLTE-Style Custom UI

> **Document type:** Change-Request Roadmap / Implementation Baseline / Baby-Step Build Plan
> **Supersedes:** v1 Roadmap, v2 Roadmap, Local README baseline
> **Stack:** Core PHP 8.1+ · MySQL 8 (InnoDB) · Custom MVVM · Vanilla HTML/CSS/JS · Zero runtime third-party code
> **Explicitly excluded:** Node.js, React, npm build chains, jQuery, Bootstrap, AdminLTE vendor bundle, FontAwesome, any CDN, any Composer runtime package
> **UI baseline:** AdminLTE (almsaeedstudio) *layout language* re-implemented from scratch as one custom CSS + one custom JS
> **Auth:** OAuth 2.0 style token issuance · JWT access token · opaque rotating refresh token · `Authorization: Bearer` ONLY (no cookie sessions, no CSRF surface)
> **Hosting:** cPanel shared hosting (Apache + PHP-FPM/LSAPI + MySQL 8 + Cron). No Docker, Redis, queue daemon, shell daemons
> **Tenancy:** One DB, one codebase, row-level tenancy via `org_ref` + `franchise_ref` on every tenant table
> **Data policy:** Fresh schema + fresh seed. **No migration from any earlier DB.**
> **Local dev:** `http://crm/` · DB `crm` · user `root` · no password · `localhost:3306` (XAMPP)

---

# 0. CHANGE REQUEST REGISTER (v2 → v3)

```text
CR-001  Tenancy is first-class: org_ref + franchise_ref on EVERY tenant table, enforced in SQL, code and tests.
CR-002  Whole database revamped. One schema file, one seed file. No migration tooling for legacy data.
CR-003  Duplicate policy is granular and tenant-scoped (see §3). Business duplicates allowed; reference duplicates blocked per franchise only.
CR-004  Composite foreign keys (franchise_ref, x_ref) so a child row can never point to another tenant's parent row (DB-level tenant integrity).
CR-005  Refs are unpredictable (prefix + random Crockford base32), never sequential, to kill enumeration/IDOR. Human numbers (order_no, invoice_no) are separate sequential columns per franchise.
CR-006  Bearer-only auth. No PHP sessions, no cookies. Access JWT (15 min) + rotating refresh token with reuse detection.
CR-007  Tenant comes from the verified JWT claims + DB re-validation. Request headers/body/query can NEVER choose a tenant.
CR-008  Four surfaces, four themes: Super Admin, Franchise/Org Admin, Sales Team, Distributor/Customer.
CR-009  UI is AdminLTE-style but 100% custom: one `crm-ui.css`, one `crm-ui.js`, inline SVG icon sprite. Strict CSP (no inline script, no CDN).
CR-010  Standalone core files: every module is an individual, dependency-light PHP file set. No ORM, no vendor.
CR-011  cPanel-native runtime: file cache, DB rate-limit, DB job queue, cron-driven worker.
CR-012  Sequence generation is atomic in ONE statement (INSERT … ON DUPLICATE KEY UPDATE LAST_INSERT_ID) — no nested transactions, no MAX()+1.
CR-013  Stock reservation writes real `stock_reservations` rows and locks batches in deterministic order (deadlock-safe).
CR-014  Every test is an in-process CLI "agent" (custom harness, no PHPUnit, no HTTP server needed).
CR-015  OpenAPI 3.0 contract regenerated for v3 and verified by an agent (route ⇄ spec parity).
CR-016  Fixes carried from v2 review: ref column widths (v2 refs were too long for CHAR(14)), nullable-tenant uniqueness gap on users, catalogue scope inconsistency, reservation/FEFO mismatch.
```

---

# 1. PRODUCT VISION

## 1.1 Statement

A **single cloud Pharma CRM** on cPanel shared hosting where:

- **Super Admin** owns the platform: creates organizations and franchises, suspends tenants, audits everything, impersonates with full audit.
- **Organization** = top tenant boundary (a pharma company / group).
- **Franchise** = operational tenant boundary (a PCD/franchise business unit under an organization). All transactional data is scoped to a franchise.
- **Franchise/Org Admin** runs one franchise: users, masters, leads, parties, pricing, schemes, orders, stock, billing, dispatch, payments, reports.
- **Sales Team** lives under a franchise and sees only assigned data.
- **Distributor/Customer** lives under a franchise and self-serves catalogue, orders, invoices, dispatch, outstanding.

No tenant can ever read or write another tenant's rows — enforced by (a) JWT claims, (b) repository scoping, (c) composite FKs, (d) agents that try to break it.

## 1.2 Tenancy Tree

```text
CLOUD INSTANCE (one cPanel account, one DB)
 ├── PLATFORM (Super Admin)                       tenant_scope = PLATFORM
 └── ORGANIZATION  org_ref = ORG-xxxxxxxxxxxxxxxx
       └── FRANCHISE  franchise_ref = FRN-xxxxxxxxxxxxxxxx     ← row-level tenant key
             ├── FRANCHISE_ADMIN users
             ├── SALES users
             ├── DISTRIBUTOR users (linked to a party)
             └── leads · parties · products · prices · schemes · orders · batches
                 invoices · dispatches · payments · notifications · audit · jobs
```

## 1.3 Non-Goals

Not a statutory accounting suite, not an ERP for other industries, not a patient app, no prescription recommendation, no unsupported KPI promises.

---

# 2. DUPLICATE POLICY (AUTHORITATIVE)

## 2.1 Principle

> **Business look-alikes are allowed. Reference collisions are blocked — but only inside the same franchise.**

```text
ALLOWED   Same product + qty + date + distributor, different client_order_ref            → two real orders
ALLOWED   Same client_order_ref in franchise A and franchise B                          → tenants never collide
ALLOWED   Same external_lead_id in franchise A and B                                    → tenants never collide
BLOCKED   Same (franchise_ref, client_order_ref)                                        → 409 DUPLICATE_ORDER_CLIENT_REF
BLOCKED   Same (franchise_ref, idempotency_key) with different request body             → 409 IDEMPOTENCY_MISMATCH
REPLAY    Same (franchise_ref, idempotency_key) with identical body                     → cached original response
BLOCKED   Same (franchise_ref, source_ref, external_lead_id)                            → lead deduped / 409
BLOCKED   Same (franchise_ref, invoice_no | dispatch_no | payment_no | order_no)        → impossible (sequence-generated)
```

## 2.2 Unique Constraint Matrix (all composite, all tenant-led)

```text
orders             UNIQUE (franchise_ref, client_order_ref)
orders             UNIQUE (franchise_ref, order_no)
invoices           UNIQUE (franchise_ref, invoice_no)
dispatches         UNIQUE (franchise_ref, dispatch_no)
payments           UNIQUE (franchise_ref, payment_no)
parties            UNIQUE (franchise_ref, party_code)
products           UNIQUE (franchise_ref, sku)
inventory_batches  UNIQUE (franchise_ref, product_ref, batch_no)
leads              UNIQUE (franchise_ref, source_key, external_lead_id)
webhook_events     UNIQUE (franchise_ref, source_ref, external_event_id)
users              UNIQUE (tenant_key, email)        -- tenant_key = COALESCE(franchise_ref,'PLATFORM')
api_idempotency    UNIQUE (franchise_ref, idempotency_key)
```

**Never** a global `UNIQUE(client_order_ref)`, `UNIQUE(invoice_no)`, `UNIQUE(sku)`, `UNIQUE(email)` (except platform-wide `*_ref` random tokens).

## 2.3 Why `leads.source_key`

MySQL treats `NULL` as distinct in unique indexes. `external_source_ref` may be NULL for manual leads, which would silently allow duplicates. `source_key` is a generated column: `IFNULL(external_source_ref,'MANUAL')`, and `external_lead_id` is stored as `''` when absent with the unique index applied only through a second generated column `ext_key = IF(external_lead_id IS NULL, CONCAT('~',lead_ref), external_lead_id)`. Manual leads therefore never collide; webhook leads always dedupe.

---

# 3. TENANCY MODEL

## 3.1 Tenant Resolution Order (per request)

```text
1. Route surface decides expected scope:  /super/* PLATFORM | /admin/* FRANCHISE | /sales/* FRANCHISE | /portal/* DISTRIBUTOR | /api/v1/* from token
2. Verify Bearer JWT (signature, exp, nbf, iss, aud, kid, typ=access)
3. Load user row by user_ref → status must be ACTIVE, franchise ACTIVE, org ACTIVE
4. Compare JWT claims (org_ref, franchise_ref, role) with DB row → mismatch = 401 + SECURITY audit
5. Build immutable TenantContext
6. If optional header X-Franchise-Ref is present it MUST equal context; else 403 TENANT_MISMATCH (never used to select tenant)
```

## 3.2 Mandatory Tenant Columns

```sql
org_ref         VARCHAR(24) NOT NULL,
franchise_ref   VARCHAR(24) NOT NULL,
created_by_ref  VARCHAR(24) NOT NULL,
updated_by_ref  VARCHAR(24) NULL,
created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
```

## 3.3 Enforcement Rules

```text
T01  Every tenant repository query has  WHERE franchise_ref = :__tenant  injected by TenantScope (cannot be omitted by callers).
T02  INSERT sets org_ref + franchise_ref from TenantContext only; request body values for these keys are discarded.
T03  UPDATE/DELETE include franchise_ref in WHERE. Deletes on ledger tables are forbidden (status changes only).
T04  Child tables carry composite FKs (franchise_ref, parent_ref) → parent(franchise_ref, parent_ref).
T05  Super Admin cross-tenant reads use ->withoutTenantScope('reason') ; every call writes audit_logs action=TENANT_BYPASS.
T06  Any cross-tenant attempt (foreign ref in URL/body resolving to another tenant) → 404 (not 403, to avoid existence leak) + SECURITY audit.
T07  Raw SQL outside repositories is forbidden; a static lint agent greps for `franchise_ref` presence in every Sql repository query.
T08  Reports and exports run through the same scoped query builder.
```

## 3.4 TenantContext

```php
<?php
declare(strict_types=1);
namespace App\Core;

final class TenantContext
{
    public function __construct(
        public readonly string $orgRef,
        public readonly ?string $franchiseRef,
        public readonly string $userRef,
        public readonly string $role,          // SUPER_ADMIN|FRANCHISE_ADMIN|SALES|DISTRIBUTOR
        public readonly string $scope,         // PLATFORM|FRANCHISE|DISTRIBUTOR
        public readonly ?string $partyRef,     // set for DISTRIBUTOR
        public readonly string $requestId,
        public readonly ?string $impersonatorRef = null,
    ) {}

    public function isSuper(): bool { return $this->role === 'SUPER_ADMIN'; }
    public function isAdmin(): bool { return $this->role === 'FRANCHISE_ADMIN'; }
    public function isSales(): bool { return $this->role === 'SALES'; }
    public function isDistributor(): bool { return $this->role === 'DISTRIBUTOR'; }
    public function requireFranchise(): string {
        if ($this->franchiseRef === null) { throw new \App\Core\Exceptions\ForbiddenException('FRANCHISE_REQUIRED'); }
        return $this->franchiseRef;
    }
}
```

---

# 4. AUTHENTICATION — JWT + OAuth 2.0, BEARER ONLY

## 4.1 Design

```text
Grant types supported (own implementation, RFC-inspired):
  password        → POST /api/v1/oauth/token   grant_type=password
  refresh_token   → POST /api/v1/oauth/token   grant_type=refresh_token
Revocation        → POST /api/v1/oauth/revoke
Introspection     → GET  /api/v1/auth/me
Client identity   → oauth_clients (one per surface: super, admin, sales, portal), public clients, no secret
```

## 4.2 Token Specs

```text
ACCESS TOKEN  (JWT, HS256 via hash_hmac, key ring with kid)
  header : { "alg":"HS256","typ":"JWT","kid":"k1" }
  claims : iss, aud(surface), sub(user_ref), org, frn, role, scp(scope), pty(party_ref|null),
           iat, nbf, exp(+900s), jti, sid(session_ref), typ:"access"
  TTL    : 900 seconds (15 min)

REFRESH TOKEN (opaque, 48 random bytes base64url, stored as SHA-256 hash only)
  TTL    : 14 days absolute, 8 hours idle
  ROTATE : every use issues a new pair; old token marked USED
  REUSE  : presenting a USED token revokes the entire token family (family_ref) + SECURITY audit
```

## 4.3 Validation Pipeline (every protected request)

```text
1. Header must match  ^Bearer [A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+\.[A-Za-z0-9\-_]+$
2. alg must equal "HS256" (reject "none", reject anything else)
3. hash_equals() signature compare with key selected by kid
4. exp/nbf/iat (60s leeway), iss, aud matches route surface, typ=access
5. Session check: user_sessions.session_ref not revoked and not expired
6. User/franchise/org status re-check (cached in FileCache 30s)
7. Role/claims equal DB row
8. Build TenantContext
```

## 4.4 Client Token Handling (no cookies)

```text
- Access token lives in a JS module-scoped variable (memory only).
- Refresh token lives in sessionStorage (tab-scoped, cleared on tab close).
- On page load the shell calls POST /oauth/token grant_type=refresh_token to obtain a fresh access token.
- Strict CSP (script-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none') is the XSS shield protecting the refresh token.
- All DOM writes use textContent / createElement; innerHTML is banned by lint agent.
- Multi-tab: BroadcastChannel('crm-auth') propagates logout.
- Logout: POST /oauth/revoke (revokes session family) then clear sessionStorage.
```

Trade-off (documented decision): bearer-only removes CSRF entirely and works with shared-hosting stateless PHP, at the cost of needing a strict CSP because tokens are JS-readable. Accepted.

## 4.5 Login Rules

```text
- Body: { grant_type, client_id, email, password, franchise_code? }
- client_id ∈ {crm-super, crm-admin, crm-sales, crm-portal}; user.role must match client surface, else generic 401 INVALID_CREDENTIALS
- franchise_code required for admin/sales/portal (resolves tenant_key); omitted for super
- password_verify() with Argon2id if available else bcrypt cost 12; dummy hash verify when user missing (timing equalization)
- Lockout: 5 fails / 15 min per (email,tenant_key) and 20 fails / 15 min per IP → 429 with Retry-After
- Generic error text always; never disclose whether email or franchise exists
- Password policy: ≥ 10 chars, not in built-in top-1000 list, not equal to email
```

## 4.6 JWT Key Ring (config)

```php
// app/Config/auth.php
return [
  'iss'            => 'pharma-crm',
  'access_ttl'     => 900,
  'refresh_abs'    => 1209600,
  'refresh_idle'   => 28800,
  'active_kid'     => 'k1',
  'keys'           => ['k1' => env('JWT_KEY_K1'), 'k0' => env('JWT_KEY_K0')], // k0 accepted, never signs
  'hash_algo'      => defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT,
  'clients'        => [
    'crm-super'  => ['aud' => 'super',  'roles' => ['SUPER_ADMIN']],
    'crm-admin'  => ['aud' => 'admin',  'roles' => ['FRANCHISE_ADMIN']],
    'crm-sales'  => ['aud' => 'sales',  'roles' => ['SALES']],
    'crm-portal' => ['aud' => 'portal', 'roles' => ['DISTRIBUTOR']],
  ],
];
```

## 4.7 Core JWT Service (standalone file skeleton)

```php
<?php
declare(strict_types=1);
namespace App\Core\Security;

final class Jwt
{
    public function __construct(private array $keys, private string $activeKid, private string $iss) {}

    public function sign(array $claims): string {
        $h = ['alg' => 'HS256', 'typ' => 'JWT', 'kid' => $this->activeKid];
        $p = $claims + ['iss' => $this->iss];
        $seg = self::b64(json_encode($h, JSON_UNESCAPED_SLASHES)) . '.' . self::b64(json_encode($p, JSON_UNESCAPED_SLASHES));
        return $seg . '.' . self::b64(hash_hmac('sha256', $seg, $this->keys[$this->activeKid], true));
    }

    public function verify(string $jwt, string $aud): array {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) throw new \RuntimeException('TOKEN_MALFORMED');
        [$h64, $p64, $s64] = $parts;
        $h = json_decode(self::unb64($h64), true);
        if (!is_array($h) || ($h['alg'] ?? '') !== 'HS256') throw new \RuntimeException('TOKEN_ALG');
        $key = $this->keys[$h['kid'] ?? ''] ?? null;
        if ($key === null) throw new \RuntimeException('TOKEN_KID');
        $calc = hash_hmac('sha256', $h64 . '.' . $p64, $key, true);
        if (!hash_equals($calc, self::unb64($s64))) throw new \RuntimeException('TOKEN_SIGNATURE');
        $c = json_decode(self::unb64($p64), true);
        $now = time();
        if (($c['typ'] ?? '') !== 'access') throw new \RuntimeException('TOKEN_TYPE');
        if (($c['iss'] ?? '') !== $this->iss) throw new \RuntimeException('TOKEN_ISS');
        if (($c['aud'] ?? '') !== $aud) throw new \RuntimeException('TOKEN_AUD');
        if (($c['exp'] ?? 0) < $now - 60) throw new \RuntimeException('TOKEN_EXPIRED');
        if (($c['nbf'] ?? 0) > $now + 60) throw new \RuntimeException('TOKEN_NBF');
        return $c;
    }

    private static function b64(string $s): string { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
    private static function unb64(string $s): string { return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)) ?: ''; }
}
```

---

# 5. UI SYSTEM — ADMINLTE-STYLE, 100% CUSTOM, FOUR THEMES

## 5.1 Rules

```text
UI-01  No AdminLTE/Bootstrap/jQuery/FontAwesome files. Only the *layout vocabulary* is reproduced:
       .wrapper .main-header .main-sidebar .content-wrapper .content-header .content .card .small-box
       .info-box .table .btn .badge .form-control .callout .modal .toast .breadcrumb .nav-sidebar
UI-02  Two shipped assets only:  /assets/css/crm-ui.css   /assets/js/crm-ui.js  (+ per-module js in /assets/js/modules/)
UI-03  Icons: single inline SVG sprite  /assets/img/icons.svg  referenced with <use href="#i-name">
UI-04  Theme = CSS variables. No hard-coded colors outside :root[data-theme] blocks.
UI-05  Pages are PHP *shells*: layout + nav + empty containers. Data arrives via bearer API and is rendered with DOM APIs (textContent).
UI-06  Mobile-first: 320px baseline; sidebar becomes off-canvas drawer < 992px; tables collapse to stacked cards < 768px.
UI-07  Accessibility: semantic landmarks, focus-visible, labels tied to inputs, aria-live toasts, contrast ≥ 4.5:1, no color-only status.
```

## 5.2 Theme Map (unique per surface)

```text
SURFACE            KEY            SIDEBAR      PRIMARY   ACCENT    BG         TEXT      LOOK
Super Admin        theme-super    #0B1437      #3D5AFE   #F5B301   #0F1B4C    #FFFFFF   Midnight navy + gold (platform authority, dark)
Franchise Admin    theme-admin    #0A5C4C      #0E7C66   #F2A93B   #F4FBF8    #0B1F1A   Pharma teal/green + amber (clinical, clean)
Sales Team         theme-sales    #12306B      #1E5EFF   #FF7A00   #F5F8FF    #0B1730   Royal blue + orange (energetic, field-ready)
Distributor        theme-portal   #3B2378      #6A3DE8   #00C2A8   #F7F5FF    #1A1030   Violet + mint (customer-friendly, premium)
```

```css
:root[data-theme="theme-super"]  { --sb:#0B1437; --pri:#3D5AFE; --acc:#F5B301; --bg:#0F1B4C; --card:#16235F; --tx:#FFFFFF; --mut:#9AA4C7; --bd:#26356F; }
:root[data-theme="theme-admin"]  { --sb:#0A5C4C; --pri:#0E7C66; --acc:#F2A93B; --bg:#F4FBF8; --card:#FFFFFF; --tx:#0B1F1A; --mut:#5C7A72; --bd:#D6EBE4; }
:root[data-theme="theme-sales"]  { --sb:#12306B; --pri:#1E5EFF; --acc:#FF7A00; --bg:#F5F8FF; --card:#FFFFFF; --tx:#0B1730; --mut:#5B6B8C; --bd:#DCE4F7; }
:root[data-theme="theme-portal"] { --sb:#3B2378; --pri:#6A3DE8; --acc:#00C2A8; --bg:#F7F5FF; --card:#FFFFFF; --tx:#1A1030; --mut:#6B5E8C; --bd:#E4DDF7; }
```

## 5.3 ThemeResolver (surface → theme; role verified by API)

```php
final class ThemeResolver {
    public static function forSurface(string $surface): string {
        return match ($surface) {
            'super' => 'theme-super', 'admin' => 'theme-admin',
            'sales' => 'theme-sales', 'portal' => 'theme-portal',
            default => 'theme-sales',
        };
    }
}
```

The shell renders `<html data-theme="…">` server-side from the URL surface (no flash of wrong theme). The JS boot then calls `/auth/me`; if `role` does not belong to the surface it wipes tokens and redirects to that surface's login.

## 5.4 Optional Tenant Branding

`franchises.brand_accent_hex` (Franchise Admin may set accent only). Super Admin may set primary + accent per franchise. Server validates `#RRGGBB` and computes contrast against `--bg`; rejects < 4.5:1. Applied as a scoped `<style nonce>`-free override via a generated CSS file `/assets/tenant/{franchise_ref}.css` (file, not inline, to keep CSP strict).

## 5.5 Layout Skeleton (PHP shell)

```php
<?php // app/Views/layouts/shell.php  — variables: $surface $theme $title $navItems
declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES) ?>" data-surface="<?= htmlspecialchars($surface, ENT_QUOTES) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES) ?> · Pharma CRM</title>
  <link rel="stylesheet" href="/assets/css/crm-ui.css">
</head>
<body>
<div class="wrapper" id="app" hidden>
  <header class="main-header">
    <button class="nav-toggle" aria-label="Toggle menu" data-action="toggle-sidebar"><svg><use href="/assets/img/icons.svg#i-menu"/></svg></button>
    <span class="brand" id="brand"></span>
    <div class="header-right"><span id="who"></span><button class="btn btn-sm" data-action="logout">Logout</button></div>
  </header>
  <aside class="main-sidebar" aria-label="Primary"><nav class="nav-sidebar" id="nav"></nav></aside>
  <main class="content-wrapper"><section class="content-header"><h1 id="page-title"></h1></section><section class="content" id="view"></section></main>
</div>
<div id="toasts" aria-live="polite"></div>
<script type="module" src="/assets/js/crm-ui.js"></script>
</body></html>
```

## 5.6 Component Inventory (all hand-written)

```text
Layout     wrapper, header, sidebar, content-wrapper, breadcrumb
Display    card, small-box (KPI), info-box, table, mobile-card-list, badge/status-badge, callout, timeline, empty-state, skeleton
Input      input, select, textarea, date, money, phone, search-select, checkbox, radio, file-upload
Action     btn (primary/accent/ghost/danger), icon-btn, dropdown, tabs, pagination, modal, drawer, confirm-dialog, toast
Data       DataTable(fetch→render→paginate→sort→filter), FormBuilder(rules→errors→submit), Stepper, Cart, PriceSummary, Chart (inline SVG bars/lines, own code)
```

---

# 6. ARCHITECTURE

```text
Internet ─HTTPS─ Apache(.htaccess) ─ public/index.php
                     │
             Kernel (Router → Middleware pipeline)
   RequestId → SecurityHeaders → RateLimit → BearerAuth → Tenant → Role/Policy → Idempotency → Controller
                     │
   Controllers (Super | Admin | Sales | Portal | Api\V1)      Web shells (PHP views, no data)
                     │
   Application Services (transaction boundaries, orchestration)
        │            │             │              │
   Domain Services  Policies    Validators    Events → Audit / Notifications / Jobs
        │
   Tenant-scoped Repositories (PDO prepared only)
        │
   MySQL 8 InnoDB (row-level tenancy, composite FKs)

Cron → cli/scheduler.php → job_queue → cli/worker.php (bounded loop, SKIP LOCKED)
```

## 6.1 Layer Rules

```text
View → (nothing)  ·  ViewModel → pure data  ·  Controller → Service only
Service → Domain/Policy/Validator/Repository contract  ·  Repository → PDO only
Forbidden: controller SQL, view SQL, repository HTML, service reading $_SERVER/$_POST, business rule only in JS, webhook controller writing business tables directly
```

## 6.2 Modular Monolith Modules

```text
Tenancy, Auth, Users, Organizations, Franchises, Leads, FollowUps, Parties, Territories, Products, Pricing,
Schemes, Orders, Inventory, Billing, Dispatch, Payments, Notifications, Webhooks, Reports, Audit, Onboarding
```

---

# 7. DIRECTORY STRUCTURE (v3)

```text
project/
├── .ai/                       agents/ decisions/ knowledge/ planning/
├── app/
│   ├── Config/                app.php database.php auth.php tenancy.php theme.php security.php cache.php queue.php integrations.php
│   ├── Core/
│   │   ├── Application.php Container.php Router.php Request.php Response.php Validation.php
│   │   ├── Database.php Transaction.php Logger.php FileCache.php EventBus.php JobRunner.php
│   │   ├── Idempotency.php TenantContext.php TenantScope.php ThemeResolver.php RefGenerator.php SequenceService.php
│   │   ├── Security/ Jwt.php PasswordHasher.php TokenService.php RateLimiter.php Csp.php
│   │   └── Exceptions/ (AppException, ValidationException, ForbiddenException, NotFoundException, ConflictException, BusinessRuleException)
│   ├── Http/
│   │   ├── Middleware/ RequestId.php SecurityHeaders.php RateLimit.php BearerAuth.php Tenant.php Role.php Idempotency.php
│   │   ├── Controllers/ Web/ Api/V1/ (Auth, Super, Admin, Sales, Portal, Webhooks)
│   │   └── FormRequests/
│   ├── Domain/                <Module>/{Entity, Service, Policy, Rules}
│   ├── Repositories/          Contracts/ Sql/
│   ├── Policies/ Validators/ ViewModels/
│   ├── Views/                 layouts/ components/ auth/ super/ admin/ sales/ portal/ errors/
│   └── Support/               helpers.php env.php
├── bootstrap/                 app.php routes.php middleware.php bindings.php
├── cli/                       install.php migrate.php seed.php worker.php scheduler.php backup.php tenant.php user.php keys.php test-agents.php lint.php
├── database/                  schema/001_full_schema.sql  seeds/001_fresh_seed.sql  fixtures/
├── docs/                      architecture/ tenancy/ theme/ business-rules/ workflows/ test-cases/ deployment/ runbooks/ decisions/
│   └── api/                   openapi.yaml examples/
├── public/                    index.php .htaccess robots.txt
│   └── assets/                css/crm-ui.css  js/crm-ui.js  js/modules/*.js  img/icons.svg  tenant/
├── storage/                   logs/ cache/ exports/ temporary/ private/ backups/
├── tests/                     Agents/ Support/ (Harness.php, Http.php, Seed.php, Assert.php)
├── .env.example  .gitignore  README.md  CR-Roadmap.md
```

`composer.json` is optional and contains **only** the PSR-4 autoload map (no `require` packages). A hand-written `bootstrap/autoload.php` is the default so Composer is never required on cPanel.

---

# 8. FRESH DATABASE SCHEMA (v3)

> One file: `database/schema/001_full_schema.sql`. `cli/migrate.php --fresh` drops and recreates. No incremental migrations exist.
> Ref format: `PREFIX-` + 16 chars Crockford base32 (e.g. `ORD-7K3M9Q2XW4T8HBEA`) → `VARCHAR(24)`.

```sql
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
```

## 8.1 Tenancy Core

```sql
CREATE TABLE organizations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL,
  org_code VARCHAR(32) NOT NULL,
  org_name VARCHAR(191) NOT NULL,
  status ENUM('ACTIVE','SUSPENDED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  brand_primary_hex CHAR(7) NULL,
  brand_accent_hex CHAR(7) NULL,
  created_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_org_ref (org_ref),
  UNIQUE KEY uq_org_code (org_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE franchises (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  franchise_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL,
  franchise_code VARCHAR(32) NOT NULL,
  franchise_name VARCHAR(191) NOT NULL,
  gstin VARCHAR(15) NULL,
  drug_license_no VARCHAR(64) NULL,
  address TEXT NULL,
  brand_primary_hex CHAR(7) NULL,
  brand_accent_hex CHAR(7) NULL,
  settings_json JSON NULL,
  status ENUM('ACTIVE','SUSPENDED','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_franchise_ref (franchise_ref),
  UNIQUE KEY uq_franchise_pair (org_ref, franchise_ref),
  UNIQUE KEY uq_franchise_code (org_ref, franchise_code),
  INDEX idx_franchise_status (org_ref, status),
  CONSTRAINT fk_frn_org FOREIGN KEY (org_ref) REFERENCES organizations(org_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
```

## 8.2 Users, OAuth, Sessions

```sql
CREATE TABLE users (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NULL,
  tenant_key VARCHAR(24) GENERATED ALWAYS AS (IFNULL(franchise_ref,'PLATFORM')) STORED,
  role ENUM('SUPER_ADMIN','FRANCHISE_ADMIN','SALES','DISTRIBUTOR') NOT NULL,
  party_ref VARCHAR(24) NULL,
  full_name VARCHAR(191) NOT NULL,
  email VARCHAR(191) NOT NULL,
  mobile VARCHAR(20) NULL,
  password_hash VARCHAR(255) NOT NULL,
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','INACTIVE','LOCKED') NOT NULL DEFAULT 'ACTIVE',
  last_login_at DATETIME NULL,
  failed_login_count INT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  created_by_ref VARCHAR(24) NOT NULL,
  updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_user_ref (user_ref),
  UNIQUE KEY uq_user_email_tenant (tenant_key, email),
  UNIQUE KEY uq_user_pair (franchise_ref, user_ref),
  INDEX idx_user_tenant (org_ref, franchise_ref, role, status),
  CONSTRAINT fk_user_org FOREIGN KEY (org_ref) REFERENCES organizations(org_ref),
  CONSTRAINT chk_user_scope CHECK (
    (role = 'SUPER_ADMIN' AND franchise_ref IS NULL) OR (role <> 'SUPER_ADMIN' AND franchise_ref IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE oauth_clients (
  client_id VARCHAR(32) PRIMARY KEY,
  surface ENUM('super','admin','sales','portal') NOT NULL,
  allowed_roles VARCHAR(120) NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_sessions (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_ref VARCHAR(24) NOT NULL,
  family_ref VARCHAR(24) NOT NULL,
  user_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NULL,
  client_id VARCHAR(32) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  user_agent VARCHAR(255) NULL,
  issued_at DATETIME NOT NULL,
  abs_expires_at DATETIME NOT NULL,
  revoked_at DATETIME NULL,
  revoke_reason VARCHAR(40) NULL,
  UNIQUE KEY uq_session_ref (session_ref),
  INDEX idx_session_user (user_ref, revoked_at),
  INDEX idx_session_family (family_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE oauth_refresh_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  session_ref VARCHAR(24) NOT NULL,
  family_ref VARCHAR(24) NOT NULL,
  user_ref VARCHAR(24) NOT NULL,
  status ENUM('ACTIVE','USED','REVOKED') NOT NULL DEFAULT 'ACTIVE',
  issued_at DATETIME NOT NULL,
  idle_expires_at DATETIME NOT NULL,
  abs_expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  UNIQUE KEY uq_rt_hash (token_hash),
  INDEX idx_rt_family (family_ref, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191) NOT NULL,
  tenant_key VARCHAR(24) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  outcome ENUM('SUCCESS','FAIL','LOCKED') NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_la_email (email, tenant_key, attempted_at),
  INDEX idx_la_ip (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE password_resets (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token_hash CHAR(64) NOT NULL,
  user_ref VARCHAR(24) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME NULL,
  UNIQUE KEY uq_pr_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE role_permissions (
  role ENUM('SUPER_ADMIN','FRANCHISE_ADMIN','SALES','DISTRIBUTOR') NOT NULL,
  permission_code VARCHAR(64) NOT NULL,
  scope ENUM('GLOBAL','TENANT','ASSIGNED','OWN') NOT NULL,
  PRIMARY KEY (role, permission_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE rate_limits (
  bucket_key CHAR(64) NOT NULL,
  window_start INT UNSIGNED NOT NULL,
  hits INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (bucket_key, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 8.3 Global Reference Data (shared, read-only, no tenant)

```sql
CREATE TABLE states    (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, state_ref VARCHAR(24) NOT NULL UNIQUE, state_code VARCHAR(8) NOT NULL UNIQUE, state_name VARCHAR(120) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE districts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, district_ref VARCHAR(24) NOT NULL UNIQUE, state_ref VARCHAR(24) NOT NULL, district_name VARCHAR(120) NOT NULL, INDEX idx_d_state (state_ref)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE cities    (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, city_ref VARCHAR(24) NOT NULL UNIQUE, district_ref VARCHAR(24) NOT NULL, city_name VARCHAR(120) NOT NULL, INDEX idx_c_district (district_ref)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE pincodes  (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, pincode CHAR(6) NOT NULL, city_ref VARCHAR(24) NULL, district_ref VARCHAR(24) NOT NULL, state_ref VARCHAR(24) NOT NULL, UNIQUE KEY uq_pincode (pincode), INDEX idx_p_district (district_ref)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 8.4 Sequences, Idempotency, Settings

```sql
CREATE TABLE sequence_counters (
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  counter_key VARCHAR(32) NOT NULL,      -- ORDER | INVOICE | DISPATCH | PAYMENT | PARTY | LEAD ...
  period_key VARCHAR(8) NOT NULL,        -- '2026' or 'FY2627' (per-franchise setting)
  last_value BIGINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (franchise_ref, counter_key, period_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE api_idempotency_keys (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  idempotency_key VARCHAR(120) NOT NULL,
  method VARCHAR(8) NOT NULL,
  path VARCHAR(191) NOT NULL,
  request_hash CHAR(64) NOT NULL,
  response_status SMALLINT NULL,
  response_json JSON NULL,
  status ENUM('IN_PROGRESS','COMPLETED','FAILED') NOT NULL DEFAULT 'IN_PROGRESS',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at DATETIME NULL,
  expires_at DATETIME NOT NULL,
  UNIQUE KEY uq_idem (franchise_ref, idempotency_key),
  INDEX idx_idem_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE system_settings (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL,
  franchise_ref VARCHAR(24) NOT NULL,
  setting_key VARCHAR(64) NOT NULL,
  setting_value VARCHAR(500) NOT NULL,
  updated_by_ref VARCHAR(24) NULL,
  updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_setting (franchise_ref, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 8.5 Catalogue, Pricing, Schemes (franchise-scoped)

```sql
CREATE TABLE product_categories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  category_name VARCHAR(120) NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cat_ref (franchise_ref, category_ref),
  UNIQUE KEY uq_cat_name (franchise_ref, category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE products (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  sku VARCHAR(64) NOT NULL,
  product_name VARCHAR(191) NOT NULL,
  category_ref VARCHAR(24) NULL,
  composition VARCHAR(255) NULL, pack_size VARCHAR(64) NULL, dosage_form VARCHAR(64) NULL,
  mrp DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  pts DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  franchise_rate DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  hsn_code VARCHAR(16) NULL,
  shelf_life_days INT NOT NULL DEFAULT 0,
  storage_requirement VARCHAR(120) NULL,
  scheme_eligible TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_product_ref (franchise_ref, product_ref),
  UNIQUE KEY uq_product_sku (franchise_ref, sku),
  INDEX idx_product_list (franchise_ref, status, product_name),
  CONSTRAINT fk_prod_cat FOREIGN KEY (franchise_ref, category_ref) REFERENCES product_categories(franchise_ref, category_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE pricing_tiers (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tier_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  tier_name VARCHAR(80) NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_tier_ref (franchise_ref, tier_ref),
  UNIQUE KEY uq_tier_name (franchise_ref, tier_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE product_prices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  price_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  product_ref VARCHAR(24) NOT NULL,
  tier_ref VARCHAR(24) NULL,
  party_ref VARCHAR(24) NULL,
  rate DECIMAL(18,2) NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  effective_from DATE NOT NULL, effective_to DATE NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_price_ref (franchise_ref, price_ref),
  INDEX idx_price_lookup (franchise_ref, product_ref, status, effective_from, effective_to),
  CONSTRAINT fk_pp_prod FOREIGN KEY (franchise_ref, product_ref) REFERENCES products(franchise_ref, product_ref),
  CONSTRAINT chk_pp_target CHECK (NOT (tier_ref IS NOT NULL AND party_ref IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE schemes (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  scheme_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  scheme_name VARCHAR(191) NOT NULL,
  start_date DATE NOT NULL, end_date DATE NOT NULL,
  priority INT NOT NULL DEFAULT 100,
  stacking_allowed TINYINT(1) NOT NULL DEFAULT 0,
  tier_ref VARCHAR(24) NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_scheme_ref (franchise_ref, scheme_ref),
  INDEX idx_scheme_active (franchise_ref, status, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE scheme_rules (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  rule_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  scheme_ref VARCHAR(24) NOT NULL, product_ref VARCHAR(24) NOT NULL,
  min_qty INT NOT NULL, max_qty INT NULL, free_qty INT NOT NULL,
  UNIQUE KEY uq_rule_ref (franchise_ref, rule_ref),
  INDEX idx_rule_lookup (franchise_ref, product_ref, scheme_ref),
  CONSTRAINT fk_sr_scheme FOREIGN KEY (franchise_ref, scheme_ref) REFERENCES schemes(franchise_ref, scheme_ref),
  CONSTRAINT fk_sr_prod FOREIGN KEY (franchise_ref, product_ref) REFERENCES products(franchise_ref, product_ref),
  CONSTRAINT chk_sr_qty CHECK (min_qty > 0 AND free_qty > 0 AND (max_qty IS NULL OR max_qty >= min_qty))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 8.6 Parties, Territory, Onboarding

```sql
CREATE TABLE parties (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  party_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  party_code VARCHAR(32) NOT NULL,
  firm_name VARCHAR(191) NOT NULL, contact_name VARCHAR(191) NULL,
  mobile VARCHAR(20) NULL, email VARCHAR(191) NULL,
  gstin VARCHAR(15) NULL, drug_license_no VARCHAR(64) NULL,
  billing_address TEXT NULL, shipping_address TEXT NULL,
  state_ref VARCHAR(24) NULL, district_ref VARCHAR(24) NULL, city_ref VARCHAR(24) NULL, pincode CHAR(6) NULL,
  tier_ref VARCHAR(24) NULL,
  sales_user_ref VARCHAR(24) NULL,
  agreement_from DATE NULL, agreement_to DATE NULL,
  credit_limit DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  payment_terms_days INT NOT NULL DEFAULT 0,
  opening_outstanding DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  converted_from_lead_ref VARCHAR(24) NULL,
  status ENUM('ACTIVE','INACTIVE','ARCHIVED') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_party_ref (franchise_ref, party_ref),
  UNIQUE KEY uq_party_code (franchise_ref, party_code),
  INDEX idx_party_list (franchise_ref, status, firm_name),
  INDEX idx_party_mobile (franchise_ref, mobile),
  INDEX idx_party_gstin (franchise_ref, gstin),
  INDEX idx_party_sales (franchise_ref, sales_user_ref, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE party_territories (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  territory_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  party_ref VARCHAR(24) NOT NULL,
  level ENUM('PINCODE','DISTRICT') NOT NULL,
  pincode CHAR(6) NULL, district_ref VARCHAR(24) NULL,
  effective_from DATE NOT NULL, effective_to DATE NULL,
  is_exclusive TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_terr_ref (franchise_ref, territory_ref),
  INDEX idx_terr_party (franchise_ref, party_ref, status, effective_from, effective_to),
  INDEX idx_terr_pin (franchise_ref, pincode, status),
  INDEX idx_terr_dist (franchise_ref, district_ref, status),
  CONSTRAINT fk_pt_party FOREIGN KEY (franchise_ref, party_ref) REFERENCES parties(franchise_ref, party_ref),
  CONSTRAINT chk_pt_level CHECK ((level='PINCODE' AND pincode IS NOT NULL) OR (level='DISTRICT' AND district_ref IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE territory_overrides (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  override_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  order_ref VARCHAR(24) NOT NULL, party_ref VARCHAR(24) NOT NULL, pincode CHAR(6) NOT NULL,
  reason VARCHAR(255) NOT NULL,
  approved_by_ref VARCHAR(24) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ovr_ref (franchise_ref, override_ref),
  INDEX idx_ovr_order (franchise_ref, order_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE onboarding_invites (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invite_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  lead_ref VARCHAR(24) NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL, used_at DATETIME NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_invite_ref (franchise_ref, invite_ref),
  UNIQUE KEY uq_invite_hash (token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 8.7 Leads & Follow-ups

```sql
CREATE TABLE leads (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  lead_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  external_source_ref VARCHAR(24) NULL,
  external_lead_id VARCHAR(120) NULL,
  source_key VARCHAR(24) GENERATED ALWAYS AS (IFNULL(external_source_ref,'MANUAL')) STORED,
  ext_key VARCHAR(120) GENERATED ALWAYS AS (IF(external_lead_id IS NULL, CONCAT('~',lead_ref), external_lead_id)) STORED,
  contact_name VARCHAR(191) NOT NULL, firm_name VARCHAR(191) NULL,
  mobile VARCHAR(20) NOT NULL, mobile_norm VARCHAR(15) NOT NULL, email VARCHAR(191) NULL,
  state_ref VARCHAR(24) NULL, district_ref VARCHAR(24) NULL, city_ref VARCHAR(24) NULL, pincode CHAR(6) NULL,
  lead_source VARCHAR(80) NULL, business_type VARCHAR(80) NULL, interested_products TEXT NULL,
  assigned_user_ref VARCHAR(24) NULL,
  priority ENUM('LOW','NORMAL','HIGH','URGENT') NOT NULL DEFAULT 'NORMAL',
  status ENUM('NEW','ASSIGNED','CONTACTED','INTERESTED','FOLLOW_UP','DOCUMENTS_PENDING','QUALIFIED','CONVERTED','LOST','REJECTED','ARCHIVED') NOT NULL DEFAULT 'NEW',
  initial_remark TEXT NULL,
  first_response_at DATETIME NULL,
  next_follow_up_at DATETIME NULL,
  converted_party_ref VARCHAR(24) NULL,
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_lead_ref (franchise_ref, lead_ref),
  UNIQUE KEY uq_lead_ext (franchise_ref, source_key, ext_key),
  INDEX idx_lead_assign (franchise_ref, assigned_user_ref, status, next_follow_up_at),
  INDEX idx_lead_mobile (franchise_ref, mobile_norm),
  INDEX idx_lead_status (franchise_ref, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE follow_ups (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  followup_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  lead_ref VARCHAR(24) NULL, party_ref VARCHAR(24) NULL,
  assigned_user_ref VARCHAR(24) NOT NULL,
  activity_type ENUM('CALL','VISIT','WHATSAPP','EMAIL','MEETING','OTHER') NOT NULL,
  next_action VARCHAR(191) NOT NULL,
  next_follow_up_at DATETIME NOT NULL,
  status ENUM('PENDING','COMPLETED','MISSED','RESCHEDULED','CLOSED') NOT NULL DEFAULT 'PENDING',
  remark TEXT NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fu_ref (franchise_ref, followup_ref),
  INDEX idx_fu_queue (franchise_ref, assigned_user_ref, status, next_follow_up_at),
  CONSTRAINT chk_fu_target CHECK (lead_ref IS NOT NULL OR party_ref IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE lead_activities (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  activity_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  lead_ref VARCHAR(24) NOT NULL, user_ref VARCHAR(24) NOT NULL,
  activity_type VARCHAR(40) NOT NULL, from_status VARCHAR(24) NULL, to_status VARCHAR(24) NULL,
  activity_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_act_ref (franchise_ref, activity_ref),
  INDEX idx_act_lead (franchise_ref, lead_ref, created_at),
  CONSTRAINT fk_la_lead FOREIGN KEY (franchise_ref, lead_ref) REFERENCES leads(franchise_ref, lead_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 8.8 Inventory

```sql
CREATE TABLE inventory_batches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  batch_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  product_ref VARCHAR(24) NOT NULL,
  batch_no VARCHAR(64) NOT NULL,
  manufacturing_date DATE NULL, expiry_date DATE NOT NULL,
  received_qty INT NOT NULL DEFAULT 0,
  on_hand_qty INT NOT NULL DEFAULT 0,          -- physical stock
  reserved_qty INT NOT NULL DEFAULT 0,         -- promised to confirmed orders
  damaged_qty INT NOT NULL DEFAULT 0,
  location_code VARCHAR(40) NULL,
  status ENUM('SALEABLE','QUARANTINE','RECALLED','EXPIRED','DAMAGED') NOT NULL DEFAULT 'SALEABLE',
  version INT NOT NULL DEFAULT 1,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_batch_ref (franchise_ref, batch_ref),
  UNIQUE KEY uq_batch_no (franchise_ref, product_ref, batch_no),
  INDEX idx_batch_fefo (franchise_ref, product_ref, status, expiry_date),
  CONSTRAINT fk_ib_prod FOREIGN KEY (franchise_ref, product_ref) REFERENCES products(franchise_ref, product_ref),
  CONSTRAINT chk_ib_qty CHECK (on_hand_qty >= 0 AND reserved_qty >= 0 AND reserved_qty <= on_hand_qty)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- free_qty = on_hand_qty - reserved_qty  (derived, never stored)

CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  movement_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  batch_ref VARCHAR(24) NOT NULL,
  movement_type ENUM('RECEIPT','SALE','RESERVE','RELEASE','RETURN','DAMAGE','EXPIRY','ADJUST','TRANSFER') NOT NULL,
  qty INT NOT NULL,                             -- signed effect on on_hand (RESERVE/RELEASE affect reserved; qty stored positive with type)
  reference_type VARCHAR(40) NULL, reference_ref VARCHAR(24) NULL,
  remarks VARCHAR(255) NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_mov_ref (franchise_ref, movement_ref),
  INDEX idx_mov_batch (franchise_ref, batch_ref, created_at),
  CONSTRAINT fk_im_batch FOREIGN KEY (franchise_ref, batch_ref) REFERENCES inventory_batches(franchise_ref, batch_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE stock_reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  reservation_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  batch_ref VARCHAR(24) NOT NULL, order_ref VARCHAR(24) NOT NULL, order_item_ref VARCHAR(24) NOT NULL,
  reserved_qty INT NOT NULL,
  status ENUM('ACTIVE','RELEASED','CONSUMED','EXPIRED') NOT NULL DEFAULT 'ACTIVE',
  expires_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_res_ref (franchise_ref, reservation_ref),
  INDEX idx_res_order (franchise_ref, order_ref, status),
  INDEX idx_res_batch (franchise_ref, batch_ref, status),
  CONSTRAINT fk_sr_batch FOREIGN KEY (franchise_ref, batch_ref) REFERENCES inventory_batches(franchise_ref, batch_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 8.9 Orders (duplicate-safe)

```sql
CREATE TABLE orders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_ref VARCHAR(24) NOT NULL,
  order_no VARCHAR(32) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  client_order_ref VARCHAR(64) NOT NULL,
  party_ref VARCHAR(24) NOT NULL,
  sales_user_ref VARCHAR(24) NULL,
  channel ENUM('PORTAL','SALES','ADMIN') NOT NULL,
  order_date DATE NOT NULL,
  shipping_address TEXT NULL, shipping_pincode CHAR(6) NULL,
  status ENUM('DRAFT','SUBMITTED','UNDER_REVIEW','CONFIRMED','ON_HOLD','BILLING_PENDING','BILLED','PACKED','DISPATCH_READY','DISPATCHED','DELIVERED','COMPLETED','CANCELLED','REJECTED') NOT NULL DEFAULT 'DRAFT',
  territory_status ENUM('OK','OVERRIDDEN','BLOCKED') NOT NULL DEFAULT 'OK',
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  discount_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  grand_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  version INT NOT NULL DEFAULT 1,
  remarks VARCHAR(255) NULL,
  created_by_ref VARCHAR(24) NOT NULL, updated_by_ref VARCHAR(24) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_order_ref (franchise_ref, order_ref),
  UNIQUE KEY uq_order_client (franchise_ref, client_order_ref),
  UNIQUE KEY uq_order_no (franchise_ref, order_no),
  INDEX idx_order_status (franchise_ref, status, order_date),
  INDEX idx_order_party (franchise_ref, party_ref, created_at),
  CONSTRAINT fk_ord_party FOREIGN KEY (franchise_ref, party_ref) REFERENCES parties(franchise_ref, party_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  order_ref VARCHAR(24) NOT NULL, product_ref VARCHAR(24) NOT NULL,
  paid_qty INT NOT NULL, free_qty INT NOT NULL DEFAULT 0,
  rate DECIMAL(18,2) NOT NULL, rate_source ENUM('PARTY','TIER','DEFAULT','OVERRIDE') NOT NULL, price_ref VARCHAR(24) NULL,
  discount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  line_total DECIMAL(18,2) NOT NULL,
  scheme_ref VARCHAR(24) NULL,
  UNIQUE KEY uq_oi_ref (franchise_ref, item_ref),
  INDEX idx_oi_order (franchise_ref, order_ref),
  INDEX idx_oi_product (franchise_ref, product_ref),
  CONSTRAINT fk_oi_order FOREIGN KEY (franchise_ref, order_ref) REFERENCES orders(franchise_ref, order_ref),
  CONSTRAINT fk_oi_prod FOREIGN KEY (franchise_ref, product_ref) REFERENCES products(franchise_ref, product_ref),
  CONSTRAINT chk_oi_qty CHECK (paid_qty > 0 AND free_qty >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- NOTE: two lines with the same product in one order are ALLOWED (granular duplicates permitted).

CREATE TABLE order_status_history (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  order_ref VARCHAR(24) NOT NULL, from_status VARCHAR(24) NULL, to_status VARCHAR(24) NOT NULL,
  actor_ref VARCHAR(24) NOT NULL, reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_osh (franchise_ref, order_ref, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 8.10 Billing, Dispatch, Payments

```sql
CREATE TABLE invoices (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_ref VARCHAR(24) NOT NULL, invoice_no VARCHAR(32) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  order_ref VARCHAR(24) NOT NULL, party_ref VARCHAR(24) NOT NULL,
  invoice_date DATE NOT NULL, due_date DATE NULL,
  bill_to_snapshot JSON NOT NULL, ship_to_snapshot JSON NOT NULL,
  subtotal DECIMAL(18,2) NOT NULL DEFAULT 0.00, discount_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_total DECIMAL(18,2) NOT NULL DEFAULT 0.00, grand_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  paid_total DECIMAL(18,2) NOT NULL DEFAULT 0.00,   -- maintained only inside payment transaction
  status ENUM('POSTED','CANCELLED') NOT NULL DEFAULT 'POSTED',
  cancel_reason VARCHAR(255) NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_inv_ref (franchise_ref, invoice_ref),
  UNIQUE KEY uq_inv_no (franchise_ref, invoice_no),
  INDEX idx_inv_party (franchise_ref, party_ref, invoice_date),
  INDEX idx_inv_order (franchise_ref, order_ref),
  CONSTRAINT fk_inv_order FOREIGN KEY (franchise_ref, order_ref) REFERENCES orders(franchise_ref, order_ref),
  CONSTRAINT chk_inv_paid CHECK (paid_total >= 0 AND paid_total <= grand_total)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE invoice_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  invoice_ref VARCHAR(24) NOT NULL, order_item_ref VARCHAR(24) NOT NULL, product_ref VARCHAR(24) NOT NULL,
  product_name_snapshot VARCHAR(191) NOT NULL, sku_snapshot VARCHAR(64) NOT NULL, hsn_snapshot VARCHAR(16) NULL,
  batch_ref VARCHAR(24) NOT NULL, batch_no_snapshot VARCHAR(64) NOT NULL, expiry_snapshot DATE NOT NULL,
  paid_qty INT NOT NULL, free_qty INT NOT NULL DEFAULT 0,
  rate DECIMAL(18,2) NOT NULL, discount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  gst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00, line_total DECIMAL(18,2) NOT NULL,
  UNIQUE KEY uq_ii_ref (franchise_ref, item_ref),
  INDEX idx_ii_invoice (franchise_ref, invoice_ref),
  CONSTRAINT fk_ii_inv FOREIGN KEY (franchise_ref, invoice_ref) REFERENCES invoices(franchise_ref, invoice_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE transporters (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transporter_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  transporter_name VARCHAR(191) NOT NULL, tracking_url_template VARCHAR(255) NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  UNIQUE KEY uq_tr_ref (franchise_ref, transporter_ref),
  UNIQUE KEY uq_tr_name (franchise_ref, transporter_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE dispatches (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  dispatch_ref VARCHAR(24) NOT NULL, dispatch_no VARCHAR(32) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  invoice_ref VARCHAR(24) NOT NULL, transporter_ref VARCHAR(24) NULL,
  lr_number VARCHAR(64) NULL, tracking_url VARCHAR(255) NULL,
  dispatch_date DATE NULL, boxes INT NOT NULL DEFAULT 0,
  status ENUM('PENDING','PACKING','READY','DISPATCHED','IN_TRANSIT','DELIVERED','FAILED','RETURNED') NOT NULL DEFAULT 'PENDING',
  remarks VARCHAR(255) NULL,
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_dsp_ref (franchise_ref, dispatch_ref),
  UNIQUE KEY uq_dsp_no (franchise_ref, dispatch_no),
  INDEX idx_dsp_inv (franchise_ref, invoice_ref),
  CONSTRAINT fk_dsp_inv FOREIGN KEY (franchise_ref, invoice_ref) REFERENCES invoices(franchise_ref, invoice_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payments (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_ref VARCHAR(24) NOT NULL, payment_no VARCHAR(32) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  party_ref VARCHAR(24) NOT NULL,
  payment_date DATE NOT NULL, amount DECIMAL(18,2) NOT NULL,
  allocated_amount DECIMAL(18,2) NOT NULL DEFAULT 0.00,
  mode ENUM('CASH','UPI','NEFT','RTGS','CHEQUE','PDC','OTHER') NOT NULL,
  reference_no VARCHAR(80) NULL, remarks VARCHAR(255) NULL,
  status ENUM('RECORDED','PARTIALLY_ALLOCATED','ALLOCATED','REVERSED','CANCELLED') NOT NULL DEFAULT 'RECORDED',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_pay_ref (franchise_ref, payment_ref),
  UNIQUE KEY uq_pay_no (franchise_ref, payment_no),
  INDEX idx_pay_party (franchise_ref, party_ref, payment_date),
  CONSTRAINT fk_pay_party FOREIGN KEY (franchise_ref, party_ref) REFERENCES parties(franchise_ref, party_ref),
  CONSTRAINT chk_pay_amt CHECK (amount > 0 AND allocated_amount >= 0 AND allocated_amount <= amount)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE payment_allocations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  allocation_ref VARCHAR(24) NOT NULL,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  payment_ref VARCHAR(24) NOT NULL, invoice_ref VARCHAR(24) NOT NULL,
  allocated_amount DECIMAL(18,2) NOT NULL,
  status ENUM('ACTIVE','REVERSED') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_alloc_ref (franchise_ref, allocation_ref),
  INDEX idx_alloc_pay (franchise_ref, payment_ref),
  INDEX idx_alloc_inv (franchise_ref, invoice_ref),
  CONSTRAINT fk_al_pay FOREIGN KEY (franchise_ref, payment_ref) REFERENCES payments(franchise_ref, payment_ref),
  CONSTRAINT fk_al_inv FOREIGN KEY (franchise_ref, invoice_ref) REFERENCES invoices(franchise_ref, invoice_ref),
  CONSTRAINT chk_al_amt CHECK (allocated_amount > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 8.11 Integrations, Notifications, Jobs, Audit

```sql
CREATE TABLE webhook_sources (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  source_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  source_name VARCHAR(80) NOT NULL, endpoint_slug VARCHAR(80) NOT NULL,
  auth_type ENUM('HMAC','BEARER') NOT NULL DEFAULT 'HMAC',
  secret_enc VARBINARY(512) NOT NULL,                -- AES-256-GCM (openssl) using APP_KEY; never plaintext
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_by_ref VARCHAR(24) NOT NULL, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_ws_ref (franchise_ref, source_ref),
  UNIQUE KEY uq_ws_slug (endpoint_slug)              -- slug is random, globally unique so the public URL can resolve the tenant
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE webhook_events (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  event_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  source_ref VARCHAR(24) NOT NULL, external_event_id VARCHAR(120) NOT NULL,
  payload_hash CHAR(64) NOT NULL, payload_json JSON NOT NULL, signature VARCHAR(128) NULL,
  status ENUM('RECEIVED','PROCESSED','FAILED','DUPLICATE','DEAD') NOT NULL DEFAULT 'RECEIVED',
  attempt_count INT NOT NULL DEFAULT 0, error_code VARCHAR(64) NULL, error_message VARCHAR(255) NULL,
  received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, processed_at DATETIME NULL,
  UNIQUE KEY uq_we_ref (franchise_ref, event_ref),
  UNIQUE KEY uq_we_ext (franchise_ref, source_ref, external_event_id),
  INDEX idx_we_status (franchise_ref, status, received_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notification_templates (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  event_type VARCHAR(64) NOT NULL, channel ENUM('INAPP','WHATSAPP','EMAIL','SMS') NOT NULL,
  body_template TEXT NOT NULL, status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  UNIQUE KEY uq_nt (franchise_ref, event_type, channel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE notifications (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  notification_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NOT NULL, franchise_ref VARCHAR(24) NOT NULL,
  user_ref VARCHAR(24) NULL, party_ref VARCHAR(24) NULL,
  channel ENUM('INAPP','WHATSAPP','EMAIL','SMS') NOT NULL, event_type VARCHAR(64) NOT NULL,
  entity_type VARCHAR(40) NULL, entity_ref VARCHAR(24) NULL, payload_json JSON NULL,
  status ENUM('QUEUED','SENT','DELIVERED','READ','FAILED') NOT NULL DEFAULT 'QUEUED',
  provider_message_id VARCHAR(120) NULL,
  idempotency_key VARCHAR(160) NOT NULL, attempt_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, read_at DATETIME NULL,
  UNIQUE KEY uq_notif_ref (franchise_ref, notification_ref),
  UNIQUE KEY uq_notify_idem (franchise_ref, idempotency_key),
  INDEX idx_notif_user (franchise_ref, user_ref, status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE job_queue (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  job_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NULL, franchise_ref VARCHAR(24) NULL,
  job_type VARCHAR(64) NOT NULL, payload_json JSON NOT NULL,
  dedupe_key VARCHAR(120) NULL,
  status ENUM('PENDING','RUNNING','SUCCESS','FAILED','RETRY_WAIT','DEAD') NOT NULL DEFAULT 'PENDING',
  attempts INT NOT NULL DEFAULT 0, max_attempts INT NOT NULL DEFAULT 5,
  run_at DATETIME NOT NULL, locked_at DATETIME NULL, last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_job_ref (job_ref),
  UNIQUE KEY uq_job_dedupe (job_type, dedupe_key),
  INDEX idx_job_run (status, run_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  audit_ref VARCHAR(24) NOT NULL, org_ref VARCHAR(24) NULL, franchise_ref VARCHAR(24) NULL,
  actor_ref VARCHAR(24) NULL, actor_role VARCHAR(40) NULL, impersonator_ref VARCHAR(24) NULL,
  category ENUM('BUSINESS','SECURITY','TENANT_BYPASS','SYSTEM') NOT NULL DEFAULT 'BUSINESS',
  action VARCHAR(80) NOT NULL, entity_type VARCHAR(40) NOT NULL, entity_ref VARCHAR(24) NULL,
  before_json JSON NULL, after_json JSON NULL, reason VARCHAR(255) NULL,
  ip_address VARCHAR(45) NULL, user_agent VARCHAR(255) NULL, request_id VARCHAR(32) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_audit_ref (audit_ref),
  INDEX idx_audit_entity (franchise_ref, entity_type, entity_ref),
  INDEX idx_audit_actor (franchise_ref, actor_ref, created_at),
  INDEX idx_audit_cat (category, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- Append-only: the app DB user is granted INSERT,SELECT on audit_logs (no UPDATE/DELETE) where cPanel privilege granularity allows; otherwise a BEFORE UPDATE/DELETE trigger raises SIGNAL.

SET FOREIGN_KEY_CHECKS = 1;
```

## 8.12 Schema Rules

```text
S01  money DECIMAL(18,2); no FLOAT/DOUBLE anywhere
S02  every tenant table has org_ref + franchise_ref (agent-schema greps information_schema to prove it)
S03  every tenant table has UNIQUE (franchise_ref, <entity>_ref)
S04  every child→parent link is a composite FK including franchise_ref
S05  ledger tables (invoices, payments, allocations, movements, audit, status history) are never DELETEd
S06  timestamps stored in UTC; APP_TIMEZONE=Asia/Kolkata used for display and business dates
S07  CHECK constraints (MySQL 8.0.16+) protect quantity and money invariants
```

---

# 9. REFERENCE & SEQUENCE GENERATION

## 9.1 Unpredictable Refs

```php
final class RefGenerator {
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ'; // Crockford base32
    private const PREFIXES = ['ORG','FRN','USR','PRD','CAT','TIR','PRC','SCH','RUL','PAR','TER','OVR','INV_','LED','FUP','ACT',
        'ORD','ITM','BAT','MOV','RSV','IVC','IVI','DSP','TRN','PAY','ALC','SRC','EVT','NTF','JOB','AUD','SES','FAM','KEY'];

    public static function make(string $prefix): string {
        $bytes = random_bytes(10);                   // 80 bits → 16 base32 chars
        $bits = ''; foreach (str_split($bytes) as $b) $bits .= str_pad(decbin(ord($b)), 8, '0', STR_PAD_LEFT);
        $out = ''; foreach (str_split($bits, 5) as $chunk) $out .= self::ALPHABET[bindec($chunk)];
        return $prefix . '-' . $out;                 // e.g. ORD-7K3M9Q2XW4T8HBEA (20 chars)
    }
}
```

Refs are collision-checked implicitly by the `(franchise_ref, *_ref)` unique key; on the astronomically rare duplicate-key error the service retries once.

## 9.2 Human Sequence Numbers (atomic, single statement)

```php
final class SequenceService {
    public function __construct(private \PDO $pdo) {}

    /** Returns e.g. INV/2026/000123. Safe inside or outside a caller transaction. */
    public function next(string $orgRef, string $franchiseRef, string $key, string $period, string $prefix, int $pad = 6): string {
        $st = $this->pdo->prepare(
            "INSERT INTO sequence_counters (org_ref, franchise_ref, counter_key, period_key, last_value)
             VALUES (:o,:f,:k,:p,LAST_INSERT_ID(1))
             ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)"
        );
        $st->execute([':o'=>$orgRef, ':f'=>$franchiseRef, ':k'=>$key, ':p'=>$period]);
        $n = (int)$this->pdo->lastInsertId();
        return sprintf('%s/%s/%0' . $pad . 'd', $prefix, $period, $n);
    }
}
```

Rules: never `MAX()+1`; never generate a number outside the business transaction that consumes it (a rolled-back transaction may leave a gap — gaps are documented as acceptable for order/payment numbers; invoice numbers use the same call **inside** the billing transaction and therefore roll back together with the row lock on the counter row, guaranteeing gapless numbering per franchise/period).

---

# 10. ALGORITHMS

## 10.1 Tenant-Scoped Query Builder (T01–T04)

```php
final class TenantScope {
    public function __construct(private TenantContext $ctx) {}

    /** Appends tenant predicate; caller passes named params only. */
    public function where(string $alias = ''): array {
        $col = ($alias ? $alias . '.' : '') . 'franchise_ref';
        return [" $col = :__tenant ", [':__tenant' => $this->ctx->requireFranchise()]];
    }
    public function insertDefaults(): array {
        return ['org_ref' => $this->ctx->orgRef, 'franchise_ref' => $this->ctx->requireFranchise(), 'created_by_ref' => $this->ctx->userRef];
    }
}
```

Every `Sql*Repository` extends `TenantRepository` whose `find/list/insert/update` methods are the **only** way to touch the DB; concrete repos supply table + column whitelist. Sort and filter fields are whitelisted; page size capped at 100.

## 10.2 Order Creation Algorithm (duplicate-safe, idempotent, transactional)

```text
INPUT  ctx, headers(Idempotency-Key), body{client_order_ref, party_ref?, shipping_pincode, items[{product_ref, qty}]}
1  Middleware Idempotency: hash = sha256(method + path + canonical_json(body))
   a) row exists COMPLETED & hash equal   → return stored response (Idempotent-Replay: true)
   b) row exists COMPLETED & hash differs → 409 IDEMPOTENCY_MISMATCH
   c) row exists IN_PROGRESS              → 409 REQUEST_IN_PROGRESS (Retry-After: 2)
   d) row absent → INSERT IN_PROGRESS (unique key arbitrates races; loser gets 409)
2  Resolve party: DISTRIBUTOR → ctx.partyRef only; SALES → party must be assigned to them; ADMIN → any in franchise
3  Validate party ACTIVE, agreement window valid
4  BEGIN
5    INSERT orders(... client_order_ref ...) → duplicate-key on uq_order_client → ROLLBACK → 409 DUPLICATE_ORDER_CLIENT_REF
       (the UNIQUE KEY is the arbiter; no SELECT-then-INSERT race)
6    order_no = SequenceService.next(ORDER)
7    For each item (lines with same product are allowed and NOT merged):
         price   = PriceResolver.resolve(...)               (§10.3)
         scheme  = SchemeCalculator.calculate(...)          (§10.4)
         lineTotal, gst computed with DECIMAL math (bcmath-free integer paise arithmetic)
         INSERT order_items (rate, rate_source, price_ref, scheme_ref frozen here)
8    Territory check (§10.5) → BLOCKED → status stays DRAFT/ON_HOLD + 422 TERRITORY_NOT_ALLOWED (admin may override later)
9    Credit check: outstanding + this order grand_total ≤ credit_limit else ON_HOLD + CREDIT_LIMIT_EXCEEDED (config: BLOCK|HOLD)
10   Status → SUBMITTED (or CONFIRMED if channel=ADMIN or auto-confirm setting)
11   If CONFIRMED: FEFO reserve (§10.6) inside the SAME transaction
12   INSERT order_status_history, audit_logs, enqueue notification job (dedupe_key = 'ordconf:'+order_ref)
13 COMMIT
14 Idempotency row → COMPLETED + response_json
15 On any exception: ROLLBACK; idempotency row → FAILED (a retry with a NEW key is required; same key returns PREVIOUS_ATTEMPT_FAILED with original error code)
```

Duplicate matrix proof (also asserted by agent-duplicates): same content + different `client_order_ref` → 201 twice; same `client_order_ref` in another franchise → 201; same in same franchise → 409.

## 10.3 Price Resolution

```text
INPUT franchise_ref, party_ref, product_ref, date
1  candidates = product_prices WHERE franchise_ref=:f AND product_ref=:p AND status='ACTIVE'
             AND effective_from <= :d AND (effective_to IS NULL OR effective_to >= :d)
2  partyRule  = candidates WHERE party_ref = :party            ORDER BY priority ASC, effective_from DESC, id DESC LIMIT 1 → source PARTY
3  else tierRule = candidates WHERE tier_ref = party.tier_ref  ORDER BY priority ASC, effective_from DESC, id DESC LIMIT 1 → source TIER
4  else product.franchise_rate (if > 0)                                                                                → source DEFAULT
5  else throw PRICE_NOT_FOUND
6  Manual override (ADMIN only, reason required, audited) → source OVERRIDE
7  Snapshot rate + source + price_ref into order_items. Historical rows are NEVER recomputed.
```

## 10.4 Scheme Calculation

```text
INPUT franchise_ref, party (tier), product, qty, date
1  rules = scheme_rules JOIN schemes WHERE active on date AND (schemes.tier_ref IS NULL OR = party.tier_ref) AND product matches
          AND qty >= min_qty AND (max_qty IS NULL OR qty <= max_qty)
2  per rule: sets = floor(qty / min_qty); free = sets * free_qty
3  if schemes.stacking_allowed = 0 for the winning scheme: pick the rule with highest free; tie → lowest schemes.priority, then lowest id
4  if stacking allowed: apply in schemes.priority order; each next rule computed on the ORIGINAL qty; cap total free at configured max_free_ratio (default 100%)
5  return { paid_qty: qty, free_qty, total_fulfil: qty + free, scheme_ref[] }
```

Examples asserted by agent: 10+1 → qty 9→0, 10→1, 20→2, 21→2, 26→2; 20+3 tier beats 10+1 when higher free.

## 10.5 Territory Validation

```text
INPUT franchise_ref, party_ref, pincode, date
1  pin = pincodes[pincode] else TERRITORY_PINCODE_UNKNOWN
2  active = party_territories WHERE franchise_ref, party_ref, status='ACTIVE', effective_from<=d, (effective_to IS NULL OR >= d)
3  if any row level=PINCODE and pincode matches → ALLOWED
4  else if any row level=DISTRICT and district_ref = pin.district_ref → ALLOWED
5  else conflict check: another party in same franchise holds EXCLUSIVE territory for this pincode/district → BLOCKED_EXCLUSIVE
6  else apply franchise setting territory_unassigned_policy: BLOCK | ADMIN_REVIEW | ALLOW
7  ADMIN override: reason (≥10 chars) mandatory → territory_overrides row + audit + orders.territory_status=OVERRIDDEN
8  Validate at order creation AND again inside billing transaction (re-check with invoice date)
```

(v2 bug fixed: v2 allowed `pincode IS NULL` rows to match everything and ignored district-level allocation.)

## 10.6 FEFO Reservation (real reservations, deadlock-safe)

```text
INPUT franchise_ref, order_ref, order_item_ref, product_ref, qty, min_shelf_days
PRECONDITION: caller already holds an open transaction (service-level BEGIN)
1  SELECT batch_ref, on_hand_qty, reserved_qty, expiry_date
   FROM inventory_batches
   WHERE franchise_ref=:f AND product_ref=:p AND status='SALEABLE'
     AND expiry_date >= DATE_ADD(CURDATE(), INTERVAL :d DAY)
     AND (on_hand_qty - reserved_qty) > 0
   ORDER BY expiry_date ASC, manufacturing_date ASC, id ASC
   FOR UPDATE                                   -- deterministic ordering => consistent lock order => no deadlock between orders
2  remaining = qty; for each batch: take = min(on_hand-reserved, remaining)
      UPDATE inventory_batches SET reserved_qty = reserved_qty + :take, version = version + 1
      WHERE franchise_ref=:f AND batch_ref=:b AND (on_hand_qty - reserved_qty) >= :take      -- guard against races
      → affected rows must be 1 else throw STOCK_RESERVATION_CONFLICT
      INSERT stock_reservations(order_item_ref, batch_ref, reserved_qty, status=ACTIVE)
      INSERT inventory_movements(type=RESERVE)
3  remaining > 0 → throw NO_ELIGIBLE_FEFO_BATCH (caller rolls back whole order transaction; or partial policy per franchise setting)
4  free_qty (scheme) is reserved as part of total_fulfil quantity
NOTE  Multi-product orders: sort items by product_ref before reserving so lock order is globally consistent.
```

Release path (cancel/reject/expiry): `reserved_qty -= r.reserved_qty`, reservation → RELEASED, movement RELEASE, inside a transaction.
Consume path (billing): `on_hand_qty -= q; reserved_qty -= q`, reservation → CONSUMED, movement SALE.

## 10.7 Billing Transaction

```text
BEGIN
  lock order row FOR UPDATE; status must be CONFIRMED|BILLING_PENDING else ORDER_STATE_INVALID
  re-validate territory (unless OVERRIDDEN), party active, credit (config)
  invoice_no = SequenceService.next(INVOICE)               -- gapless, rolls back with this tx
  INSERT invoices (bill_to/ship_to JSON snapshots)
  For each ACTIVE reservation of the order: INSERT invoice_items with product/batch/expiry snapshots; consume stock
  order.status = BILLED; history; audit; enqueue notification
COMMIT
```

Cancellation: only if no dispatch DISPATCHED; status → CANCELLED with reason; restock movement RETURN; allocations must be reversed first (else PAYMENT_ALLOCATION_EXISTS).

## 10.8 Payment & Outstanding

```text
Record payment: INSERT payments(status RECORDED) with payment_no from sequence.
Allocate (POST /payments/{ref}/allocate  body: [{invoice_ref, amount}]):
BEGIN
  SELECT payment FOR UPDATE; SELECT each invoice FOR UPDATE (ordered by invoice_ref to keep lock order stable)
  for each: amount ≤ (grand_total - paid_total) else PAYMENT_ALLOCATION_EXCEEDS_OUTSTANDING
  sum(amounts) ≤ payment.amount - payment.allocated_amount
  INSERT payment_allocations; UPDATE invoices.paid_total += amt; UPDATE payments.allocated_amount += sum; status → PARTIALLY_ALLOCATED|ALLOCATED
  audit + notification job
COMMIT
Reverse: mark allocations REVERSED, subtract from invoices.paid_total / payments.allocated_amount, payment REVERSED, audit.
Invoice outstanding = grand_total - paid_total
Party outstanding  = opening_outstanding + SUM(POSTED invoice outstanding) - SUM(payment.amount - payment.allocated_amount WHERE status IN (RECORDED,PARTIALLY_ALLOCATED))
```

(`paid_total` is a maintained cache guarded by CHECK + row locks; agent-payments recomputes it from `payment_allocations` and asserts equality.)

## 10.9 Idempotency Middleware

```php
final class IdempotencyMiddleware {
    public function handle(Request $r, TenantContext $ctx, callable $next): Response {
        if (!in_array($r->method, ['POST','PUT','PATCH'], true) || $r->isPublicWebhook()) return $next($r);
        $key = $r->header('Idempotency-Key');
        if (!$key || !preg_match('/^[A-Za-z0-9_\-]{16,120}$/', $key)) throw new ValidationException('IDEMPOTENCY_KEY_REQUIRED');
        $hash = hash('sha256', $r->method . '|' . $r->path . '|' . Json::canonical($r->body));
        $row = $this->repo->find($ctx->requireFranchise(), $key);
        if ($row) {
            if ($row['request_hash'] !== $hash) throw new ConflictException('IDEMPOTENCY_MISMATCH');
            if ($row['status'] === 'COMPLETED')   return Response::replay((int)$row['response_status'], $row['response_json']);
            if ($row['status'] === 'IN_PROGRESS') throw new ConflictException('REQUEST_IN_PROGRESS');
            throw new ConflictException('PREVIOUS_ATTEMPT_FAILED');
        }
        if (!$this->repo->tryInsertInProgress($ctx, $key, $hash, $r)) throw new ConflictException('REQUEST_IN_PROGRESS'); // lost race
        try { $resp = $next($r); $this->repo->complete($ctx, $key, $resp); return $resp; }
        catch (\Throwable $e) { $this->repo->fail($ctx, $key, $e); throw $e; }
    }
}
```

## 10.10 Webhook Lead Ingestion

```text
POST /api/v1/webhooks/{endpoint_slug}/leads       (public route, no bearer; auth = HMAC)
1  Resolve source by endpoint_slug (random 32-char) → gives org_ref + franchise_ref (tenant is derived from the SOURCE, never the payload)
2  Body ≤ 256 KB, Content-Type application/json
3  Verify header X-Signature: sha256=hex(hmac_sha256(secret, raw_body)) with hash_equals; timestamp header within ±300 s (anti-replay)
4  Validate schema; normalize mobile (digits, strip +91/0), email lowercase
5  INSERT webhook_events (unique franchise+source+external_event_id) → duplicate → 200 {status:"duplicate"}
6  Enqueue job ProcessWebhookEvent (fast 202 response)
7  Worker: INSERT leads with unique (franchise_ref, source_key, ext_key); duplicate → mark event DUPLICATE
8  Business duplicate by mobile_norm in same franchise → configurable: LINK (append activity) | CREATE_ANYWAY | REJECT
9  Auto-assign (round-robin among ACTIVE sales users, pointer in system_settings); notify assignee; record first_response SLA clock
10 Failure → RETRY_WAIT with backoff 30s, 2m, 10m, 30m; permanent validation errors → DEAD (no infinite retry)
```

## 10.11 Rate Limiting (DB-backed, shared-hosting safe)

```text
bucket = sha256(scope + ':' + key)      scope∈{login-ip, login-user, api-user, webhook-source}
window = floor(now / 60)
INSERT … ON DUPLICATE KEY UPDATE hits = hits + 1  → if hits > limit → 429 + Retry-After
Defaults: login-ip 20/15min · login-user 5/15min · api-user 240/min · webhook-source 120/min
Old windows purged by daily cron.
```

---

# 11. WORKFLOWS

## 11.1 Platform Bootstrap

```text
cli/install.php → create schema → seed → create Super Admin (prompted password, never stored in seed) → generate JWT key k1 + APP_KEY → write .env → readiness check
```

## 11.2 Tenant Creation (Super Admin)

```text
Super Admin → POST /super/organizations → POST /super/franchises (org_ref) → auto-seed franchise defaults
(sequence counters, default tiers, notification templates, default settings) → POST /super/franchises/{ref}/admins (first Franchise Admin, must_change_password=1)
```

## 11.3 Lead → Distributor Onboarding

```text
Webhook/Manual Lead → Assign → Contact/Follow-up (next_action + next_follow_up_at mandatory)
→ INTERESTED → DOCUMENTS_PENDING → QUALIFIED → Admin issues onboarding invite (single-use, 72h, hashed token)
→ Distributor completes profile → party created (converted_from_lead_ref) → territory + tier + credit set
→ Admin creates DISTRIBUTOR user linked to party_ref → portal access → Lead status CONVERTED (history preserved)
```

## 11.4 Order-to-Cash

```text
Portal/Sales/Admin creates order (client_order_ref + Idempotency-Key)
→ validate (party, territory, price, scheme, credit) → CONFIRMED + FEFO reservations
→ Admin bills (invoice_no, snapshots, stock consumed) → PACKED → DISPATCH_READY → DISPATCHED (transporter + LR)
→ Portal shows timeline; notification queued → DELIVERED → COMPLETED
→ Payment recorded → allocated to invoices → outstanding recalculated
```

## 11.5 Order State Machine (enforced by `OrderStateMachine`)

```text
DRAFT → SUBMITTED → UNDER_REVIEW → CONFIRMED → BILLING_PENDING → BILLED → PACKED → DISPATCH_READY → DISPATCHED → DELIVERED → COMPLETED
ON_HOLD  ⇄ (SUBMITTED | UNDER_REVIEW | CONFIRMED)
CANCELLED ← (DRAFT|SUBMITTED|UNDER_REVIEW|CONFIRMED|ON_HOLD|BILLING_PENDING)   (BILLED+ requires invoice cancel path)
REJECTED  ← (SUBMITTED|UNDER_REVIEW)
Every transition: role check + reason where required + order_status_history + audit.
```

## 11.6 Lead State Machine

```text
NEW → ASSIGNED → CONTACTED → INTERESTED → FOLLOW_UP ⇄ INTERESTED → DOCUMENTS_PENDING → QUALIFIED → CONVERTED
any non-terminal → LOST | REJECTED (reason required) ; LOST/REJECTED → ARCHIVED ; terminal: CONVERTED, ARCHIVED
Rule: status ∈ {ASSIGNED,CONTACTED,INTERESTED,FOLLOW_UP,DOCUMENTS_PENDING,QUALIFIED} ⇒ exactly one PENDING follow-up with next_action + next_follow_up_at
```

## 11.7 Theme/Login Flow

```text
GET /super/login | /admin/login | /sales/login | /portal/login  → shell with data-theme by surface
POST /api/v1/oauth/token (client_id per surface) → tokens → GET /auth/me → role must match surface → render surface dashboard
```

---

# 12. API (v1) — BEARER ONLY

## 12.1 Headers

```text
Authorization: Bearer <access_jwt>            (all except /oauth/*, /webhooks/*, /health, /ready)
Idempotency-Key: <16–120 chars>               (required: POST/PUT/PATCH on business writes)
X-Request-ID: <uuid>                          (optional; server issues one)
X-Franchise-Ref: FRN-…                        (optional; if sent must equal token tenant, else 403 TENANT_MISMATCH)
Content-Type: application/json
```

## 12.2 Envelope

```json
{ "success": true,  "data": {}, "meta": { "request_id": "req_…", "page": 1, "per_page": 25, "total": 250 } }
{ "success": false, "error": { "code": "DUPLICATE_ORDER_CLIENT_REF", "message": "…", "fields": {} }, "meta": { "request_id": "req_…" } }
```

Status policy: 200 201 202 204 400 401 403 404 409 422 429 500 503. Never expose stack traces; `APP_DEBUG=false` in production.

## 12.3 Endpoint Catalogue

```text
OAUTH / AUTH
  POST /oauth/token            grant_type=password|refresh_token
  POST /oauth/revoke
  GET  /auth/me
  POST /auth/change-password
  POST /auth/forgot-password   POST /auth/reset-password

SUPER  (role SUPER_ADMIN, scope PLATFORM)
  GET/POST   /super/organizations            PATCH /super/organizations/{ref}
  GET/POST   /super/franchises               PATCH /super/franchises/{ref}   POST /super/franchises/{ref}/suspend|activate
  POST       /super/franchises/{ref}/admins
  GET        /super/users                    POST /super/users/{ref}/unlock
  POST       /super/impersonate/{user_ref}   (time-boxed 15 min, banner in UI, every call audited with impersonator_ref)
  GET        /super/audit                    GET /super/security-events
  GET        /super/dashboard                GET /super/health

ADMIN  (role FRANCHISE_ADMIN)
  Users:       GET/POST /admin/users · PATCH /admin/users/{ref} · POST …/activate|deactivate|reset-password
  Masters:     /admin/categories · /admin/tiers · /admin/transporters · /admin/settings · /admin/notification-templates
  Geo (read):  GET /geo/states · /geo/districts?state_ref · /geo/pincodes/{pin}
  Leads:       GET /admin/leads · POST …/{ref}/assign|convert|archive · GET …/{ref}/activities
  Parties:     GET/POST /admin/parties · PATCH · POST …/activate|deactivate · GET …/{ref}/ledger|orders|payments
  Territory:   GET/POST /admin/territories · POST /admin/territories/validate · POST /admin/territories/override
  Onboarding:  POST /admin/onboarding/invites
  Products:    GET/POST /admin/products · PATCH · POST …/activate|deactivate|archive
  Pricing:     GET/POST /admin/prices · PATCH /admin/prices/{ref} · POST /admin/pricing/resolve
  Schemes:     GET/POST /admin/schemes · POST /admin/schemes/calculate
  Orders:      GET/POST /admin/orders · GET …/{ref} · POST …/confirm|hold|reject|cancel|reserve|release
  Inventory:   GET /admin/inventory/batches · POST …/receipts|adjust|status · GET …/near-expiry · POST …/allocate-fefo (dry-run)
  Billing:     GET /admin/invoices · POST /admin/invoices (from order_ref) · POST …/{ref}/cancel · GET …/{ref}/print
  Dispatch:    GET/POST /admin/dispatches · PATCH · POST …/dispatch|deliver
  Payments:    GET/POST /admin/payments · POST …/{ref}/allocate|reverse
  Reports:     GET /admin/reports/{leads|followups|conversion|sales|territory|products|schemes|inventory|near-expiry|outstanding|dispatch|webhooks} · POST /admin/reports/exports
  Webhooks:    GET/POST /admin/webhook-sources · POST …/{ref}/rotate-secret
  Audit:       GET /admin/audit
  Dashboard:   GET /admin/dashboard

SALES  (role SALES; ASSIGNED/OWN scope)
  GET /sales/dashboard · GET /sales/leads · PATCH /sales/leads/{ref} · POST …/{ref}/remarks|status
  GET/POST /sales/follow-ups · POST …/{ref}/complete|reschedule|miss
  GET /sales/parties · GET /sales/parties/{ref}
  GET/POST /sales/orders (only for assigned parties)
  GET /sales/catalogue · GET /sales/outstanding

PORTAL (role DISTRIBUTOR; party-owned scope)
  GET /portal/dashboard · GET /portal/profile · PATCH /portal/profile (shipping address only)
  GET /portal/catalogue · GET /portal/prices · POST /portal/cart/calculate
  GET/POST /portal/orders · GET /portal/orders/{ref} · POST /portal/orders/{ref}/cancel (only pre-CONFIRMED)
  GET /portal/invoices · GET /portal/dispatches · GET /portal/outstanding · GET /portal/payments
  GET /portal/notifications · POST /portal/notifications/{ref}/read

NOTIFICATIONS (all authenticated roles, own only)
  GET /notifications · POST /notifications/{ref}/read

WEBHOOKS (public, HMAC)
  POST /webhooks/{endpoint_slug}/leads
  POST /webhooks/{endpoint_slug}/status

OPS
  GET /health   GET /ready
```

## 12.4 Authorization Matrix (summary; full matrix lives in `role_permissions` seed)

```text
Capability                    SUPER   F-ADMIN   SALES        DISTRIBUTOR
Organizations/Franchises      ✔       ✖         ✖            ✖
Franchise users               ✔       ✔         ✖            ✖
Masters/Products/Pricing      ✔ (audited bypass) ✔ ✖ (read)   ✖ (applicable price only)
Leads                         ✔       ✔         ASSIGNED     ✖
Parties                       ✔       ✔         ASSIGNED     OWN (profile)
Territory override            ✔       ✔         ✖            ✖
Orders                        ✔       ✔         ASSIGNED     OWN
Inventory/Billing/Dispatch    ✔       ✔         ✖            OWN (view)
Payments                      ✔       ✔         view ASSIGNED OWN (view)
Audit                         ✔       ✔ (own tenant) ✖       ✖
```

Object-level checks are mandatory in policies (`SalesPartyPolicy::canView($ctx,$party)` etc.). IDOR agent iterates every `{ref}` endpoint with a foreign-tenant ref and a same-tenant-but-unassigned ref.

## 12.5 OpenAPI

`docs/api/openapi.yaml` (3.0.3): `securitySchemes.bearerAuth {type: http, scheme: bearer, bearerFormat: JWT}`, tags per module, reusable `Error`, `Pagination`, `IdempotencyKey` components, examples under `docs/api/examples/`. `agent-openapi` parses the YAML with a tiny custom parser (own file `tests/Support/MiniYaml.php`) and asserts route ⇄ spec parity both ways. A static local docs page `/docs` (own HTML, no Swagger UI) renders the spec via `crm-ui.js` — optional and disabled in production.

---

# 13. SECURITY

## 13.1 HTTP Hardening (`SecurityHeaders` middleware + `.htaccess`)

```text
Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'
Strict-Transport-Security: max-age=31536000; includeSubDomains
X-Content-Type-Options: nosniff        Referrer-Policy: no-referrer        Permissions-Policy: geolocation=(), camera=(), microphone=()
Cache-Control: no-store  (on all /api/* and authenticated shells)
CORS: same-origin only; no Access-Control-Allow-Origin: * ever
```

## 13.2 Input / Output

```text
- PDO prepared statements only, emulation OFF (ATTR_EMULATE_PREPARES=false), ERRMODE_EXCEPTION
- Whitelisted sort/filter columns; per_page ≤ 100
- Central Validation rules (type, length, enum, range, date, regex for GSTIN/pincode/mobile)
- JSON body max 1 MB (webhooks 256 KB); reject unknown Content-Type
- Mass-assignment: FormRequests declare allowed keys; org_ref/franchise_ref/*_by_ref/status-machine fields never accepted from body
- Output: JSON via json_encode(JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); DOM via textContent only
- Uploads (party documents): allowlist ext + finfo MIME, random filename, stored in storage/private, served through authorized endpoint, size ≤ 5 MB, never executable
```

## 13.3 Secrets & Crypto

```text
- .env outside web root; permissions 0600; never committed
- Webhook secrets encrypted with AES-256-GCM (openssl) using APP_KEY; shown once on creation/rotation
- Refresh tokens & invite/reset tokens stored as SHA-256 hashes only
- Passwords: Argon2id (fallback bcrypt cost 12); password_needs_rehash on login
- JWT keys rotatable via `php cli/keys.php rotate` (k1 → k0 accepted for 15 min then removed)
```

## 13.4 Security Event Catalogue (audit category SECURITY)

```text
LOGIN_FAILED, LOGIN_LOCKED, REFRESH_REUSE_DETECTED, TOKEN_TAMPERED, TENANT_MISMATCH, CROSS_TENANT_ATTEMPT,
ROLE_ESCALATION_ATTEMPT, WEBHOOK_SIGNATURE_INVALID, RATE_LIMIT_HIT, IMPERSONATION_START/END, TENANT_BYPASS
```

## 13.5 cPanel-Specific

```text
- Document root = public_html contents of /public only; app lives above web root
- .htaccess: Options -Indexes, deny dotfiles, deny *.sql|*.md|*.env|*.log even if misplaced
- DB user has only the needed privileges on its own DB
- PHP display_errors=Off, log_errors=On to storage/logs (rotated by scheduler)
- If MySQL < 8.0.16 CHECK is ignored → agent-schema fails deploy readiness (minimum enforced)
```

---

# 14. cPanel DEPLOYMENT

## 14.1 Layout

```text
/home/USER/pharma-crm/        ← everything except public (NOT web accessible)
/home/USER/public_html/       ← contents of project/public/  (index.php, .htaccess, assets/)
index.php → require __DIR__ . '/../pharma-crm/bootstrap/app.php';
```

## 14.2 `.htaccess` (public)

```apache
Options -Indexes -MultiViews
RewriteEngine On
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

<FilesMatch "(^\.|\.(env|sql|md|log|ini|bak)$)">
  Require all denied
</FilesMatch>

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

# Authorization header passthrough on LSAPI/FastCGI
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css "access plus 7 days"
  ExpiresByType application/javascript "access plus 7 days"
  ExpiresByType image/svg+xml "access plus 30 days"
</IfModule>
```

## 14.3 `.env` (production)

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crm.example.com
APP_TIMEZONE=Asia/Kolkata
APP_KEY=base64:REPLACE_32_BYTES
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cpuser_crm
DB_USER=cpuser_crm
DB_PASSWORD=REPLACE
JWT_KEY_K1=REPLACE_64_HEX
JWT_KEY_K0=
JWT_ACCESS_TTL=900
STORAGE_PATH=/home/USER/pharma-crm/storage
LOG_LEVEL=warning
```

Local dev `.env`: `APP_URL=http://crm`, `DB_HOST=localhost`, `DB_PORT=3306`, `DB_NAME=crm`, `DB_USER=root`, `DB_PASSWORD=` (empty), `APP_DEBUG=true`.

## 14.4 Cron

```text
*   * * * *  /usr/local/bin/php /home/USER/pharma-crm/cli/scheduler.php minute
*/5 * * * *  /usr/local/bin/php /home/USER/pharma-crm/cli/scheduler.php five-minute
0   * * * *  /usr/local/bin/php /home/USER/pharma-crm/cli/scheduler.php hourly
30  1 * * *  /usr/local/bin/php /home/USER/pharma-crm/cli/scheduler.php daily
```

Worker behaviour: each `minute` tick runs `worker.php` with a 50 s budget; `SELECT … FROM job_queue WHERE status IN ('PENDING','RETRY_WAIT') AND run_at <= NOW() ORDER BY run_at LIMIT 10 FOR UPDATE SKIP LOCKED`; stale RUNNING jobs (> 10 min) re-queued; exponential backoff via `run_at`; jobs tenant-tagged and executed inside a synthetic TenantContext.

Scheduler duties: minute→worker; five-minute→webhook retry sweep, notification dispatch; hourly→follow-up reminders, expired reservations release; daily→near-expiry scan, payment reminders, rate_limits/idempotency purge, log rotation, `backup.php`.

## 14.5 Backups

`cli/backup.php` → `mysqldump`-free PHP dumper (chunked SELECT → gzip file in `storage/backups/`, 14-day retention) plus documented cPanel Backup Wizard fallback. `--verify` restores into `DB_NAME_restore_test` and runs `agent-schema`.

## 14.6 Release Procedure

```text
1 Upload code → 2 `.env` → 3 `php cli/install.php` (first time) → 4 `php cli/test-agents.php --all --env=staging` → 5 smoke test 4 logins → 6 cron installed
7 Rollback = restore previous code folder + DB backup taken pre-release (fresh-schema policy: schema changes ship as a new full schema + planned re-seed on staging first; production data changes go through documented change-control)
```

---

# 15. TEST AGENTS (custom harness, CLI-only)

## 15.1 Harness

```text
tests/Support/Harness.php   → boots the app kernel in-process against DB `crm_test` (or DB_NAME_TEST), rebuilds schema+seed per agent group
tests/Support/Http.php      → builds Request objects, sets Authorization, dispatches through the full middleware stack, returns Response (status, headers, json)
tests/Support/Seed.php      → deterministic fixtures: 1 super, 2 orgs (A,B), 2 franchises per… (A1, B1, plus A2), each with admin, 2 sales, 2 distributors, products, tiers, prices, schemes, batches
tests/Support/Assert.php    → eq, true, status, jsonPath, notContains, throws, sqlCount, tenantOnly(list, franchise_ref)
Each agent: agents/*.php returns ['name','scope','pre','steps'=>[callable…],'cleanup']
```

```bash
php cli/test-agents.php --list
php cli/test-agents.php --agent=agent-tenancy
php cli/test-agents.php --all --report=storage/exports/test-report.json --fail-fast
php cli/test-agents.php --group=security
```

Exit code 0 only if every agent passes. Report JSON: agent, step, status, duration, assertion count.

## 15.2 Agent Contract (mirrored in `.ai/agents/*.md`)

```text
## AGENT:        name
## SCOPE:        module
## PRE-CONDITIONS: fixtures needed
## STEPS:        ordered actions
## ASSERTIONS:   expected results (status + body + DB state)
## TENANT-SAFETY: cross-tenant probes
## CLEANUP:      teardown
```

## 15.3 Agent Catalogue

```text
agent-schema        info_schema: every tenant table has org_ref+franchise_ref; all uniques tenant-led; composite FKs present; DECIMAL money; MySQL ≥ 8.0.16; no FLOAT
agent-lint          static: no innerHTML in assets/js, no SQL outside Repositories/Sql, every Sql query contains franchise_ref (tenant repos), no localStorage token, no CDN URLs, no inline <script>
agent-openapi       route table ⇄ openapi.yaml parity; response codes documented; bearerAuth on every non-public path
agent-auth          password grant per surface; wrong-surface role → 401; lockout after 5; refresh rotation; reuse detection revokes family; expired/none-alg/tampered/wrong-aud/wrong-kid tokens → 401; revoked session → 401
agent-tenancy       A-admin lists only A rows for every list endpoint; B-admin only B; foreign ref in URL → 404 + SECURITY audit; body-supplied franchise_ref ignored; X-Franchise-Ref mismatch → 403; JWT claim vs DB mismatch → 401; super bypass audited
agent-composite-fk  direct SQL attempt to attach a franchise-B product to a franchise-A order_item → FK violation
agent-theme         shells for /super,/admin,/sales,/portal return data-theme super/admin/sales/portal; wrong-role token on a surface redirected; no hard-coded hex in Views/*.php; contrast ≥ 4.5 for each theme token pair
agent-duplicates    (1) A: client_order_ref X → 201  (2) A: X again (new idem key) → 409 DUPLICATE_ORDER_CLIENT_REF  (3) B: X → 201  (4) A: different ref same product/qty/party/date → 201  (5) same product twice in one order → 201, two lines  (6) external_lead_id same in A and B → both created; twice in A → deduped
agent-idempotency   same key+body → identical cached response, 1 order row; same key+different body → 409 IDEMPOTENCY_MISMATCH; concurrent same key → one 201 + one 409 REQUEST_IN_PROGRESS; missing key → 422
agent-sequences     100 parallel next() calls → unique gapless 1..100 per franchise; two franchises independent; rollback keeps invoice numbering gapless
agent-users         create/activate/deactivate; email unique per tenant (same email allowed in A and B); super email unique on PLATFORM; password policy
agent-leads         create, mobile normalization, duplicate policy, assignment, reassign history, follow-up mandatory fields, state machine, conversion snapshot
agent-followups     add/complete/reschedule/miss; queue ordering; overdue detection; sales sees only own
agent-parties       CRUD, GSTIN/mobile format, code unique per franchise (same code in B allowed), archive/restore, sales scoping
agent-territory     pincode allowed; district allowed; expired window ignored; exclusive conflict; unassigned policies; override needs reason + audit; historical order unchanged; re-check at billing
agent-pricing       party > tier > default; effective dates; priority overlap; inactive ignored; snapshot immutability after master change
agent-schemes       10+1 table (9→0,10→1,20→2,21→2,26→2); max_qty; stacking off/on; tier-limited scheme; expired scheme ignored
agent-orders        happy path; state machine legal/illegal; credit limit; hold/reject/cancel; totals decimal exactness; portal party isolation; sales assigned-only
agent-inventory     receipt; batch unique per franchise+product (same batch_no in B allowed); adjust; near-expiry list; status changes exclude from FEFO
agent-fefo          B(2026-11) → A(2027-01) → C(2028-03) order; expired/recalled/quarantine/damaged never touched; min shelf days; partial fill fails atomically; reservation rows + movements written
agent-concurrency   2 parallel PHP processes reserve 80 of 100 → exactly one succeeds, other STOCK_RESERVATION_CONFLICT/NO_ELIGIBLE_FEFO_BATCH; reserved ≤ on_hand; multi-product lock order no deadlock across 20 runs
agent-billing       invoice from confirmed order; gapless invoice_no; snapshots; stock consumed; master price change doesn't alter invoice; cancellation reverses stock; territory re-check
agent-dispatch      transitions; LR/transporter; portal visibility; tracking URL template
agent-payments      partial + full allocation; over-allocation rejected; reversal; outstanding formula; paid_total equals SUM(allocations); concurrent allocations serialised
agent-webhooks      HMAC valid/invalid; replay (old timestamp) rejected; duplicate event; payload-size limit; retry backoff schedule; DEAD after max attempts; tenant derived from source only
agent-notifications templates render; idempotency key blocks double send; provider failure → FAILED + retry; own-only read
agent-jobs          SKIP LOCKED with two workers no double-run; stale RUNNING recovered; dedupe_key uniqueness
agent-onboarding    invite single-use, expiry, hashed token; lead→party→user linkage; converted lead history intact
agent-super         create org/franchise/admin; suspend franchise → its tokens rejected within cache TTL; impersonation time-box + audit; cross-tenant reports
agent-security      401/403 matrix for every route × role; SQLi payloads in filter/sort → 400/422 with no SQL error text; XSS payload stored → returned JSON-escaped and rendered via textContent; JWT alg none/tampered; CORS wildcard absent; headers present; no stack traces; upload abuse; path traversal
agent-idor          for each `{ref}` route: foreign-tenant ref → 404; same tenant but not-assigned (sales) / not-own (portal) → 404
agent-audit         every state-changing endpoint writes audit row with actor/tenant/request_id; UPDATE/DELETE on audit_logs rejected
agent-performance   seed 50k leads, 20k orders, 5k batches: list endpoints < 300 ms (local), EXPLAIN uses indexes on hot queries, no N+1 (query-count assertion)
agent-cli           migrate --fresh, seed --fresh, tenant create, user create, backup --verify, worker --once all exit 0
agent-e2e           full journey: webhook lead → assign → follow-up → invite → party → order (portal) → confirm → invoice → dispatch → payment → outstanding 0, then repeat in second franchise and assert zero leakage
```

**Release gate:** all agents green on staging; `agent-e2e`, `agent-tenancy`, `agent-duplicates`, `agent-concurrency`, `agent-security`, `agent-idor` are *blocking* and re-run after every change to Core, repositories or schema.

---

# 16. SEED (fresh only) — `database/seeds/001_fresh_seed.sql`

```text
Static, safe baseline:
  oauth_clients (crm-super, crm-admin, crm-sales, crm-portal)
  role_permissions (full matrix)
  states / districts / cities / pincodes (India sample set; CSV import tool cli/geo-import.php for full data)
  Enumerations documented (lead sources, activity types, payment modes, statuses live as ENUMs)

NOT in seed.sql (created by CLI so no secrets ship in git):
  Super Admin user (cli/install.php prompts password)
  Demo tenants only when --demo flag: ORG A (F-A1, F-A2), ORG B (F-B1) with admin/sales/distributor users, sample products, tiers, schemes, batches
  (demo passwords generated randomly and printed once)

Franchise defaults auto-created on franchise creation (service, not seed):
  sequence_counters, default pricing tiers (RETAIL, STOCKIST, DISTRIBUTOR), notification templates, system_settings
  (territory_unassigned_policy=BLOCK, credit_policy=HOLD, near_expiry_days=180, min_shelf_days=0, auto_confirm=0, lead_dup_policy=LINK, fy_start_month=4)
```

Never seed real customers, GSTINs, passwords, provider secrets.

---

# 17. CLI

```bash
php cli/install.php                                  # schema + seed + super admin + keys + .env check
php cli/migrate.php --fresh                          # DROP + CREATE from 001_full_schema.sql (guarded by --i-understand in production)
php cli/seed.php --fresh [--demo]
php cli/tenant.php create --org-name="Acme Pharma" --org-code=ACME --franchise-name="Acme Mumbai" --franchise-code=MUM --admin-email=admin@acme.test
php cli/user.php create --role=FRANCHISE_ADMIN --franchise=FRN-… --email=… --name=…
php cli/user.php unlock --email=… --franchise=FRN-…
php cli/keys.php generate|rotate
php cli/worker.php --once | --budget=50
php cli/scheduler.php minute|five-minute|hourly|daily
php cli/backup.php --out=storage/backups/ [--verify]
php cli/lint.php
php cli/test-agents.php --all
```

---

# 18. BABY-STEP BUILD PLAN

> Rules: one step = one small, verifiable outcome. Never start a step until the previous step's **Verify** passes. Commit after every step. Tick the box only when Verify is green. Steps are labelled `P<phase>-S<nn>`.

## PHASE 0 — FOUNDATION (no business logic yet)

- [ ] **P0-S01 Repo scaffold** — create the directory tree from §7, `.gitignore` (`.env`, `storage/*`, `vendor/`), empty `README.md`. **Verify:** tree matches §7; `git status` clean after commit.
- [ ] **P0-S02 Autoloader** — `bootstrap/autoload.php` PSR-4 (`App\` → `app/`), no Composer required. **Verify:** `php -r "require 'bootstrap/autoload.php'; var_dump(class_exists('App\Core\Container'));"` after S03.
- [ ] **P0-S03 Env + config loader** — `app/Support/env.php` (parse `.env`, no libs), `Config` reader. **Verify:** `php -r` prints `APP_ENV`.
- [ ] **P0-S04 Container** — tiny DI container with `bind/singleton/make` and constructor auto-wiring via Reflection. **Verify:** unit agent resolves a 3-level dependency.
- [ ] **P0-S05 Request/Response** — immutable `Request` (method, path, query, JSON body, headers incl. `Authorization`, IP), `Response::json/html/empty`. **Verify:** JSON body over 1 MB rejected; header case-insensitive.
- [ ] **P0-S06 Router** — method+pattern routes with `{param}`, groups, middleware lists, route table exportable for openapi parity. **Verify:** 404/405 correct; params bound.
- [ ] **P0-S07 Middleware pipeline** — onion executor; `RequestId`, `SecurityHeaders` first. **Verify:** every response has CSP, HSTS, `X-Request-ID`.
- [ ] **P0-S08 Exceptions + error mapper** — `AppException` family → envelope JSON; unknown Throwable → 500 generic + log. **Verify:** no stack trace with `APP_DEBUG=false`.
- [ ] **P0-S09 Logger** — JSON lines to `storage/logs/app-YYYYMMDD.log`, levels, redaction of `password|token|secret|authorization`. **Verify:** logged secret appears as `***`.
- [ ] **P0-S10 Database wrapper** — PDO (emulation off, exceptions), `Transaction` helper with savepoint depth counter. **Verify:** nested transaction rollback semantics test.
- [ ] **P0-S11 FileCache** — atomic write (temp+rename), TTL, `flock`. **Verify:** two processes writing same key never corrupt.
- [ ] **P0-S12 Validation engine** — rules: required, string, int, decimal, enum, regex, date, min/max, email, mobile, gstin, pincode, array-of. **Verify:** field-level error map.
- [ ] **P0-S13 RefGenerator + SequenceService** (§9). **Verify:** agent-sequences (single-process part).
- [ ] **P0-S14 TenantContext/TenantScope/TenantRepository base** (§3.4, §10.1). **Verify:** repo without tenant context throws.
- [ ] **P0-S15 Schema file part 1** — §8.1–8.4. `cli/migrate.php --fresh`. **Verify:** tables exist; `agent-schema` (partial) passes.
- [ ] **P0-S16 Test harness** — Harness/Http/Assert/Seed skeleton; `cli/test-agents.php --list/--all`. **Verify:** dummy agent passes; failing agent gives exit 1.
- [ ] **P0-S17 Health endpoints** — `/health`, `/ready` (DB, storage writable, JWT key present, MySQL ≥ 8.0.16). **Verify:** both 200 locally.
- [ ] **P0-S18 `agent-lint` v1** — bans (`innerHTML`, CDN URL, inline script, `localStorage.setItem(...token`). **Verify:** planted violation fails.
- [ ] **P0-GATE** — all P0 agents green, `git tag p0`.

## PHASE 1 — AUTH, TENANCY, THEMES, SHELLS

- [ ] **P1-S01 Schema part 2 users/oauth** (§8.2) + geo tables (§8.3). **Verify:** agent-schema green for these tables.
- [ ] **P1-S02 PasswordHasher** (Argon2id/bcrypt, rehash, policy checker). **Verify:** hash/verify/rehash unit steps.
- [ ] **P1-S03 Jwt service** (§4.7) + key ring. **Verify:** none-alg, tampered, wrong kid, expired all rejected.
- [ ] **P1-S04 TokenService** — issue pair, session + family rows, refresh rotate, reuse detection. **Verify:** agent-auth refresh cases.
- [ ] **P1-S05 RateLimiter (DB)** (§10.11). **Verify:** 6th login in window → 429.
- [ ] **P1-S06 OAuth token endpoint** `POST /oauth/token` password grant per client/surface. **Verify:** wrong-surface role → generic 401.
- [ ] **P1-S07 BearerAuth + Tenant middleware** (§3.1, §4.3). **Verify:** claim/DB mismatch → 401; X-Franchise-Ref mismatch → 403.
- [ ] **P1-S08 Role middleware + Policy base**. **Verify:** 401/403 matrix for stub routes.
- [ ] **P1-S09 `/auth/me`, `/oauth/revoke`, change-password, forgot/reset (hashed tokens)**. **Verify:** revoked session token → 401.
- [ ] **P1-S10 Organizations/Franchises tables + Super endpoints** (create/list/suspend). **Verify:** agent-super basics.
- [ ] **P1-S11 Franchise defaults service** (counters, tiers, templates, settings). **Verify:** new franchise has all defaults.
- [ ] **P1-S12 Users module** (super creates first admin; admin creates sales/distributor users). **Verify:** agent-users; same email in two tenants OK.
- [ ] **P1-S13 Audit service + `audit_logs` writer + SECURITY events**. **Verify:** agent-audit for auth/tenant events; UPDATE/DELETE blocked.
- [ ] **P1-S14 `crm-ui.css` core** — reset, grid, wrapper/header/sidebar/content, card, small-box, table, btn, form-control, badge, toast; 4 theme variable blocks (§5.2). **Verify:** static page at 320/768/1280 px screenshots reviewed manually.
- [ ] **P1-S15 `icons.svg` sprite + `crm-ui.js`** — boot, token manager (memory + sessionStorage refresh), `api()` fetch wrapper with auto-refresh + Idempotency-Key helper, DOM helpers (`h()` element builder), toast, modal, sidebar toggle, BroadcastChannel logout. **Verify:** agent-lint clean.
- [ ] **P1-S16 Four login pages + four dashboard shells** (PHP shells, `data-theme` by surface). **Verify:** agent-theme (all 4).
- [ ] **P1-S17 Theme contrast check tool** in agent-theme (WCAG luminance math in PHP). **Verify:** ≥ 4.5 for text/bg, primary/on-primary.
- [ ] **P1-S18 Tenant branding override** (accent hex, generated `/assets/tenant/{ref}.css`). **Verify:** low-contrast rejected.
- [ ] **P1-S19 Impersonation** (super, 15 min, audited, UI banner). **Verify:** every impersonated write has `impersonator_ref`.
- [ ] **P1-S20 agent-auth, agent-tenancy (partial: users/franchises), agent-security (auth part)**. **Verify:** all green.
- [ ] **P1-GATE** — 4 logins working locally on `http://crm/`, 4 themes correct, agents green, tag `p1`.

## PHASE 2 — MASTERS, CATALOGUE, PRICING, SCHEMES

- [ ] **P2-S01 Schema part 3** (§8.5). **Verify:** agent-schema + agent-composite-fk (product→category).
- [ ] **P2-S02 Geo seed + read endpoints** (`/geo/*`). **Verify:** pincode lookup returns district/state.
- [ ] **P2-S03 Categories, tiers, transporters, settings, templates CRUD** (admin). **Verify:** same names allowed in two franchises.
- [ ] **P2-S04 Products CRUD** (activate/deactivate/archive; delete forbidden once referenced). **Verify:** SKU unique per franchise only.
- [ ] **P2-S05 Prices CRUD + PriceResolver** (§10.3). **Verify:** agent-pricing all cases.
- [ ] **P2-S06 Schemes + rules CRUD + SchemeCalculator** (§10.4). **Verify:** agent-schemes table.
- [ ] **P2-S07 Money helper** — integer-paise arithmetic, half-up rounding, GST split. **Verify:** 1,000 randomized totals equal reference computation.
- [ ] **P2-S08 UI: masters + products + pricing + schemes pages** (DataTable + FormBuilder components). **Verify:** manual mobile + keyboard pass; agent-lint clean.
- [ ] **P2-GATE** — agents green; tag `p2`.

## PHASE 3 — CRM (LEADS, FOLLOW-UPS, PARTIES, TERRITORY)

- [ ] **P3-S01 Schema part 4** (§8.6–8.7). **Verify:** generated columns `source_key/ext_key` behave (manual leads never collide).
- [ ] **P3-S02 Lead create/list/view/edit/archive** + mobile normalization + duplicate policy setting. **Verify:** agent-leads (CRUD + dup).
- [ ] **P3-S03 Assignment + reassignment history + round-robin pointer**. **Verify:** history rows.
- [ ] **P3-S04 Lead state machine + activities**. **Verify:** illegal transition 422.
- [ ] **P3-S05 Follow-ups** with mandatory next_action/date rule. **Verify:** agent-followups.
- [ ] **P3-S06 Sales scoping policies** (ASSIGNED/OWN). **Verify:** agent-idor (leads, follow-ups).
- [ ] **P3-S07 Parties CRUD** + party_code sequence + GSTIN/DL validation + archive/restore. **Verify:** agent-parties.
- [ ] **P3-S08 Territories CRUD** (PINCODE/DISTRICT, effective-dated, exclusive flag). **Verify:** overlapping exclusive rejected.
- [ ] **P3-S09 TerritoryValidator** (§10.5) + override endpoint + audit. **Verify:** agent-territory.
- [ ] **P3-S10 Onboarding invites + lead conversion → party (+ distributor user)**. **Verify:** agent-onboarding.
- [ ] **P3-S11 Webhook sources CRUD (secret encrypted, shown once, rotate)**. **Verify:** secret never returned again.
- [ ] **P3-S12 Webhook endpoint + event store** (§10.10 steps 1–6). **Verify:** agent-webhooks (auth/replay/dup/size).
- [ ] **P3-S13 Job queue + worker + scheduler skeleton** (§14.4). **Verify:** agent-jobs.
- [ ] **P3-S14 ProcessWebhookEvent job** (normalize, dedupe, assign, retry/backoff/DEAD). **Verify:** agent-webhooks complete.
- [ ] **P3-S15 UI: leads, follow-up queue (mobile cards), parties, territories, sales dashboard**. **Verify:** manual mobile pass.
- [ ] **P3-GATE** — agents green (leads, followups, parties, territory, onboarding, webhooks, jobs, idor); tag `p3`.

## PHASE 4 — ORDER-TO-CASH

- [ ] **P4-S01 Schema part 5** (§8.8–8.10). **Verify:** agent-schema, agent-composite-fk (order→party, item→product, allocation→invoice).
- [ ] **P4-S02 Idempotency middleware + repo** (§10.9). **Verify:** agent-idempotency.
- [ ] **P4-S03 Inventory receipts/batches/adjust/status + movements**. **Verify:** agent-inventory.
- [ ] **P4-S04 FEFO allocator + reservations** (§10.6). **Verify:** agent-fefo.
- [ ] **P4-S05 Concurrency proof** — two-process reservation test harness (`proc_open`). **Verify:** agent-concurrency ×20 runs green.
- [ ] **P4-S06 Order service: create/validate/totals** (§10.2 steps 1–10, without stock). **Verify:** agent-duplicates full matrix.
- [ ] **P4-S07 Order state machine + confirm/hold/reject/cancel + reservation release**. **Verify:** agent-orders.
- [ ] **P4-S08 Credit rule service** (BLOCK|HOLD) + outstanding query. **Verify:** boundary values (=, +0.01).
- [ ] **P4-S09 Billing service** (§10.7) with gapless invoice_no and snapshots. **Verify:** agent-billing, agent-sequences rollback case.
- [ ] **P4-S10 Invoice print layout** (print CSS in `crm-ui.css`, A4). **Verify:** browser print preview manual.
- [ ] **P4-S11 Dispatch service + transporter + LR + tracking**. **Verify:** agent-dispatch.
- [ ] **P4-S12 Payments + allocation + reversal + outstanding** (§10.8). **Verify:** agent-payments.
- [ ] **P4-S13 Near-expiry query + daily scan job + dashboard cards**. **Verify:** 180/90/60/30 thresholds.
- [ ] **P4-S14 Sales order entry + admin order desk UI (mobile-first)**. **Verify:** manual.
- [ ] **P4-S15 agent-e2e (admin/sales path)**. **Verify:** green.
- [ ] **P4-GATE** — blocking agents green; tag `p4`.

## PHASE 5 — NOTIFICATIONS & WORKERS

- [ ] **P5-S01 Schema part 6** (§8.11 remainder). **Verify:** agent-schema.
- [ ] **P5-S02 Notification service + templates + idempotency keys**. **Verify:** agent-notifications.
- [ ] **P5-S03 Adapter contracts** (`WhatsAppAdapter`, `EmailAdapter`, `LogAdapter` default) — provider wired only through official API config; **no provider code required to ship**. **Verify:** LogAdapter round-trip.
- [ ] **P5-S04 Event → notification mapping** (new lead, follow-up reminder, order confirmed, billed, dispatched, payment received, near-expiry). **Verify:** each event produces exactly one queued notification.
- [ ] **P5-S05 Reminder scanners** (follow-up, payment, reservation expiry). **Verify:** dedupe_key prevents repeats.
- [ ] **P5-S06 In-app notification bell UI**. **Verify:** unread count, mark read, own-only.
- [ ] **P5-S07 Backup job + log rotation + purge jobs**. **Verify:** agent-cli backup --verify.
- [ ] **P5-GATE** — tag `p5`.

## PHASE 6 — DISTRIBUTOR PORTAL (theme-portal)

- [ ] **P6-S01 Portal policies (party-owned)**. **Verify:** agent-idor portal cases.
- [ ] **P6-S02 Catalogue + applicable price + cart calculate (server-side)**. **Verify:** price/scheme identical to order service result.
- [ ] **P6-S03 Portal order placement** (client_order_ref = UUID from client, Idempotency-Key). **Verify:** double-click submit → one order.
- [ ] **P6-S04 Orders list/detail/timeline + cancel pre-confirm**. **Verify:** state rules.
- [ ] **P6-S05 Invoices, dispatch/LR tracking, outstanding, payments views**. **Verify:** own-only.
- [ ] **P6-S06 Portal UI (violet/mint), mobile bottom-nav, cart drawer**. **Verify:** 320 px manual pass.
- [ ] **P6-S07 agent-e2e (portal path)**. **Verify:** green.
- [ ] **P6-GATE** — tag `p6`.

## PHASE 7 — SUPER ADMIN, REPORTS, HARDENING

- [ ] **P7-S01 Super dashboard** (tenants, users, jobs backlog, failed webhooks, security events). **Verify:** agent-super.
- [ ] **P7-S02 Reports query services** (12 reports) with pagination + CSV. **Verify:** tenant-only rows; totals reconcile with source tables.
- [ ] **P7-S03 Async export job + private file + authorized download**. **Verify:** other tenant cannot download.
- [ ] **P7-S04 Security hardening pass** — run agent-security + agent-idor + agent-lint; fix findings; review CSP in browser console (zero violations).
- [ ] **P7-S05 Performance pass** — agent-performance with 50k/20k/5k data; add/adjust indexes from EXPLAIN.
- [ ] **P7-S06 OpenAPI completion** — every route + examples; agent-openapi green.
- [ ] **P7-S07 Docs** — `docs/architecture`, `tenancy`, `theme`, `business-rules`, `workflows`, `runbooks`, `deployment`, `.ai/agents/*.md`.
- [ ] **P7-GATE** — full `--all` green on clean DB; tag `p7-rc1`.

## PHASE 8 — cPANEL PRODUCTION

- [ ] **P8-S01 Staging on cPanel** (subdomain) with fresh schema + seed via `install.php`. **Verify:** `/ready` green.
- [ ] **P8-S02 Cron install + worker tick observed for 24 h**. **Verify:** no stuck RUNNING jobs.
- [ ] **P8-S03 Run `test-agents.php --all` on staging**. **Verify:** exit 0.
- [ ] **P8-S04 Backup + restore drill**. **Verify:** restored DB passes agent-schema + agent-tenancy.
- [ ] **P8-S05 Rollback rehearsal**. **Verify:** documented time-to-restore.
- [ ] **P8-S06 Production install** — fresh schema, Super Admin created, first org/franchise created via UI.
- [ ] **P8-S07 Smoke test** — 4 logins/4 themes, one full order-to-cash on a test franchise, then suspend/archive test tenant.
- [ ] **P8-S08 Hypercare 14 days** — daily review of `/super/security-events`, failed jobs, slow query log.

---

# 19. FINAL CHECKLISTS

## 19.1 Tenancy

```text
[ ] Every tenant table has org_ref + franchise_ref                          (agent-schema)
[ ] Every unique constraint is tenant-led                                    (agent-schema)
[ ] Composite FKs on every child→parent                                      (agent-composite-fk)
[ ] Tenant only from verified JWT + DB revalidation                          (agent-tenancy)
[ ] Body/query/header can't select tenant                                    (agent-tenancy)
[ ] Foreign refs → 404 + SECURITY audit                                      (agent-idor)
[ ] Super bypass explicit + audited                                          (agent-super)
```

## 19.2 Duplicates

```text
[ ] Same-franchise same client_order_ref → 409
[ ] Cross-franchise same client_order_ref → 201
[ ] Same content, different client_order_ref → 201
[ ] Same product twice in one order → allowed
[ ] Idempotency replay/mismatch/in-progress correct
[ ] Lead external id dedupe per franchise+source only
[ ] Invoice/dispatch/payment/order numbers sequence-generated per franchise
```

## 19.3 Auth & Security

```text
[ ] Bearer-only; no cookies set anywhere (agent-security checks Set-Cookie absent)
[ ] alg=none / tampered / wrong aud / expired / revoked → 401
[ ] Refresh rotation + reuse detection
[ ] Lockout + rate limits
[ ] Strict CSP, zero inline scripts, zero CDN
[ ] No innerHTML in JS (agent-lint)
[ ] Webhook HMAC + replay window + size cap
[ ] Secrets encrypted / hashed; nothing sensitive logged
```

## 19.4 UI / Theme

```text
[ ] 4 themes via CSS variables only; no hard-coded colors in views
[ ] data-theme correct per surface; wrong-role token bounced
[ ] Contrast ≥ 4.5:1
[ ] 320/375/768/1024/wide verified; tables → cards on mobile
[ ] Keyboard navigation + focus-visible + aria labels + aria-live toasts
```

## 19.5 Stock / Money

```text
[ ] FEFO order proven; excluded statuses never allocated
[ ] Concurrency proof passes ×20
[ ] reserved_qty ≤ on_hand_qty (CHECK + agent)
[ ] Invoices immutable; snapshots intact after master changes
[ ] paid_total == SUM(active allocations)
[ ] All money DECIMAL / integer-paise math
```

## 19.6 Release

```text
[ ] Fresh schema + fresh seed on production
[ ] Cron installed; worker tick verified
[ ] Backup + restore verified; rollback tested
[ ] /health and /ready green; APP_DEBUG=false
[ ] No secrets in git
[ ] All agents green on staging
[ ] OpenAPI parity green
```

---

# 20. NON-NEGOTIABLE ENGINEERING RULES

```text
R01  Every tenant row carries org_ref + franchise_ref.
R02  Every unique constraint on business data is tenant-scoped.
R03  Every business write passes IdempotencyMiddleware.
R04  Every state change writes audit_logs (actor, tenant, request_id).
R05  No business rule lives only in JavaScript.
R06  No stock reservation without FOR UPDATE + guarded UPDATE + reservation row.
R07  No invoice/order/payment number without SequenceService.
R08  No cross-tenant query without withoutTenantScope('reason') + audit.
R09  No color literal in views; theme variables only.
R10  No third-party runtime code (PHP, JS, CSS, fonts, icons, CDNs). New dependency = architecture decision record.
R11  No release without tested rollback and green agents.
R12  Schema change = new full schema file + re-seed on staging first. No legacy migration. Ever.
R13  Tokens never in cookies; refresh token never in localStorage; access token never persisted.
R14  Server-side computation of price, scheme, territory, credit, stock. Client values are display-only.
R15  Every new endpoint ships with: policy, validator, audit, agent case (incl. IDOR), OpenAPI entry.
R16  No silent scope change — change control (§21).
```

---

# 21. CHANGE CONTROL

Any change touching tenancy, duplicate policy, unique constraints, schema, auth, themes, pricing/scheme/territory/inventory/billing/payment rules, or integrations requires a written decision record in `docs/decisions/DR-NNN.md` covering: UI, backend, database, permissions, audit, integration, performance, security, agents (new/changed), OpenAPI, deployment/rollback. Definition of Done: feature complete across UI + API + DB; policies + validators + audit in place; agents written and green (including tenant-safety and IDOR cases); OpenAPI updated; docs updated; manual mobile + keyboard pass for UI work.

---

# 22. OPEN DECISIONS (resolve before Phase 4 starts)

```text
OD-01  Financial-year sequence reset vs calendar-year (default: FY, starts April; setting fy_start_month)
OD-02  Credit policy default BLOCK vs HOLD (default HOLD)
OD-03  Lead duplicate policy default LINK vs REJECT (default LINK)
OD-04  Partial fulfilment when stock short: fail whole order vs partial (default fail whole order)
OD-05  GST intra/inter-state split (CGST+SGST vs IGST) rules and rounding mode
OD-06  WhatsApp provider selection and template approval process
OD-07  Whether Franchise Admin may create Sales users only, or also other Franchise Admins (default: also, audited)
OD-08  Data retention for audit_logs and webhook payloads (default: 24 months / 90 days)
```

---

# END OF DOCUMENT

**Status:** Build-ready baseline (v3.0). **Method:** fresh schema, fresh seed, agent-verified, tenant-isolated, bearer-only, cPanel-native, zero third-party runtime code.
