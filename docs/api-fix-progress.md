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
**Status: DONE (code-complete, unverified) for the array_key_exists bug; BLOCKED on the POST /admin/parties 500**

**Fixed (root cause, not a per-call-site patch):** `app/Core/Validation.php::validate()`
always set `$clean[$field]` for every rule key, defaulting to `null` when the field
was absent from the payload. That breaks any `array_key_exists($field, $clean)`
"was this actually sent" check downstream. Did the codebase-wide search the user
asked for (`array_key_exists\(.*\$clean\)` across `app/`) — only
`PartiesController::validateParty` (`opening_outstanding` immutability guard,
line 97) and `PartiesController::update` (`product_refs`, line 63) rely on this
idiom; both are fixed by the one root-cause change instead of patching each site.
Checked every other direct `$clean['field']` read in the codebase (Products,
Prices, Schemes, Orders, Territories, Payments, Users, Franchises, etc.) — all of
them read fields marked `required` in their own rule set, so they were always
present anyway and are unaffected by no longer defaulting absent optional fields
to `null`.

**Not fixed — BE-034b, `POST /admin/parties` 500s on every payload including a
minimal valid one.** Traced `PartiesController::store` → `validateParty()` →
`partyData()` → `PartyService::create()` → `SqlPartyRepository::create()` (explicit
named-param INSERT, extra `$data` keys like `product_refs` are harmless - not the
cause) → `SequenceService::nextNumber()` (auto party_code) → `RefGenerator::generate()`.
Nothing here reads as defective by static inspection (schema columns match the
INSERT list, `sequence_counters` upsert is a standard atomic counter). Same
situation as Group 1: need the actual `storage/logs` error line (exception class +
file + line, which `Exceptions\Handler::render()` always logs) for one
`POST /admin/parties` 500 to pinpoint this instead of guessing further. Asked the
user for this alongside the Group 1 log request.

## Group 4 — Security (S-1, S-2, S-4, S-5)
**Status: DONE (code-complete, unverified)**

- **S-4 (legacy `role=FRANCHISE_ADMIN` privilege escalation) — fixed.**
  `UsersController::create()` used the legacy `role` string (not `role_ref`) to
  create a user, and ran `assertCanGrantRole()` against the franchise's baseline
  role row for that legacy role - but only `if ($baselineRoleRef)` was truthy.
  If a franchise had no `auth_roles` row for that slug (a provisioning gap), the
  escalation check was silently skipped entirely - any caller with just
  `internalUsers.create` could pass `role: "FRANCHISE_ADMIN"` and get a full
  admin created with no permission check. Now throws `BASELINE_ROLE_MISSING`
  instead of skipping the check (fail closed, not fail open).
- **S-5 (party restore scope check missing) — fixed.** `PartiesController::restore()`
  had `requirePermission('parties','archive')` but, unlike `show`/`update`/`archive`/
  `ledger`/`status`, no `requireRecordScope()` call - an OWN/TEAM/TERRITORY-scoped
  user with `parties.archive` could restore any archived party in the franchise,
  not just ones in their own scope. Added the same `requireRecordScope()` call the
  sibling methods already have.
- **S-2 (webhook-sources no permission check) — fixed.** `WebhookSourcesController::index()`
  and `::store()` had no `requirePermission()` call at all - any authenticated user
  of any role could list webhook sources or create one (which issues a new
  source/signing credential). Added `requirePermission($ctx,'webhooks','view')` and
  `'configure'` respectively, using the existing `webhooks.view`/`webhooks.configure`
  permission keys from `auth_permission_catalogue` (migration 002) — these keys
  existed but nothing referenced them yet.
- **S-1 (payments list/detail/mutation record-scope leak) — fixed.**
  `PaymentsController::index()`/`show()`/`reverse()` only checked the module-level
  `payments.view`/`reverse` permission, never the caller's record *scope* — an
  OWN-scope user could list and open every payment in the franchise, not just
  their own parties'. `allocate()` was already safe (`AllocationService::allocate`
  applies `PartyScopePredicate` internally). Applied the same
  `PartyScopePredicate::clause($ctx, 'pt', ..., 'payments')` pattern
  `OutstandingService`/`AllocationService` already use elsewhere for payments:
  extended `PaymentRepositoryInterface`/`SqlPaymentRepository`'s `list()` and
  `findByRef()` with an optional scope-SQL + params pair (backward compatible —
  existing callers that don't pass them are unaffected), and wired it through
  `index()`, `show()`, and `reverse()`.

Not live-verified (no deploy access). All four need a live pass before RESOLVED:
S-4 (try creating a user with `role: FRANCHISE_ADMIN` and no `role_ref` from a
non-super, no-baseline-role franchise → should now 403, not 201), S-5 (OWN-scope
user restoring another sales rep's party → should now 404), S-2 (non-privileged
role hitting `GET/POST /admin/webhooks/sources` → should now 403), S-1 (OWN-scope
user listing/opening a payment outside their parties → should now be excluded/404).

## Group 5 — Missing routes
**Status: NOT STARTED**

## Group 6 — Shape/behavior mismatches
**Status: NOT STARTED**

## Group 7 — Modules frontend never reached (BE-091 priority)
**Status: NOT STARTED**

## Group 8 — Cleanup + demo data seed
**Status: NOT STARTED**
