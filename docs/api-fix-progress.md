# Backend API Fix Progress

Tracking doc for the "fix all live API issues" phase. Started 2026-09-28.

**Verification model (per user decision on 2026-09-28):** this environment has no
PHP/Composer/MySQL and no deploy access to `crm.easysolutins24.in` (deployment is
manual SSH/FTP per `deploy_instructions.md`, no CI/CD). Live verification of every
fix (as the original NIYAM asked) is therefore not possible from here. The user
explicitly chose: **code-review only** — fixes are made and self-reviewed as a
senior dev would, committed per group, but marked `FIXED IN CODE — UNVERIFIED`
rather than `RESOLVED`. `RESOLVED` is reserved for items actually confirmed live
(by the user/QA after deploy), per the BE-140 lesson in the original brief.

Status legend: NOT STARTED / IN PROGRESS / DONE (code-complete, unverified) / BLOCKED

---

## Group 1 — Shared scope layer (Leads/Follow-ups/Dashboard/Reports/Audit)
**Status: IN PROGRESS**

Did a full static trace of the reported chain: `CrmScopePolicy` → `SalesLeadPolicy`
/ `SalesFollowUpPolicy` → `LeadsController` / `FollowUpsController` → `LeadService`
/ `FollowUpService` → `TenantContext` → `Container` (DI wiring in
`bootstrap/bindings.php`) → `Database` → `Exceptions\Handler`. All of it reads as
logically and structurally consistent — no syntax error, no type mismatch, no
missing DB column (`party_territories`/`leads`/`follow_ups` schema checked against
the SQL the policy builds), no container-resolution gap (`CrmScopePolicy` is
explicitly bound as a singleton in `bootstrap/bindings.php:270-272`), and
`ForbiddenException` maps to 403 correctly in the handler — so a plain 403 being
misreported as 500 is ruled out too.

**Found and fixed one confirmed, separate bug while tracing this:**
`app/Http/Middleware/Tenant.php` — when a Super Admin does header-based franchise
switching (SignInAs via `X-Franchise-Ref`/`X-Franchise-Code`), the middleware
rebuilt `TenantContext` without carrying over `roles`/`permissions`/`scopes`/
`teamUserRefs`/`territoryRefs` (all silently defaulted to `[]`). `isSuper()` still
bypasses `can()`/`scopeFor()` everywhere so this wasn't the direct cause of the
reported 500s (those happen on ADMIN/SALES tokens, which never hit this branch),
but it's a real defect for any super-admin SignInAs session. Fixed: now passes
all five fields through from the original context.

**Blocked on finding the actual root cause of the reported 500s.** Everything in
`recordInScope()` for ALL/OWN/TEAM scope (the scopes ADMIN/SALES test users
actually have) is pure in-memory comparison — no DB call, nothing that can throw.
The TERRITORY branch does call the DB but per the original repro notes the failing
calls are on ADMIN(ALL)/SALES(OWN) tokens. I cannot reproduce or single-step this
without a runtime, and reading the full call graph did not surface a defect.

**Needed to close this out:** the actual PHP error line/stack trace from
`storage/logs` on the live server for one failing call (e.g.
`POST /admin/follow-ups/{ref}/complete` or `GET /admin/audit` on an ADMIN token).
`Exceptions\Handler::render()` logs `exception class + file + line` for every
500 — that one log line will point at the real failing statement directly instead
of guessing. Asked the user for this.

## Group 2 — Inventory adjust/receive (500 but commits)
**Status: NOT STARTED**

## Group 3 — Validation::validate() array_key_exists bug + POST /admin/parties 500
**Status: NOT STARTED**

## Group 4 — Security (S-1, S-2, S-4, S-5)
**Status: NOT STARTED**

## Group 5 — Missing routes
**Status: NOT STARTED**

## Group 6 — Shape/behavior mismatches
**Status: NOT STARTED**

## Group 7 — Modules frontend never reached (BE-091 priority)
**Status: NOT STARTED**

## Group 8 — Cleanup + demo data seed
**Status: NOT STARTED**
