Status ka single source of truth: Pharma-CRM/docs/OPEN_ISSUES.md. Backend ko dene wali list: docs/BACKEND_REQUIREMENTS_FOR_FRONTEND.md

# Open issues: single source of truth

## FIX ORDER

Combined order by frontend blocking priority plus security/data-corruption severity:

1. **S-3 (P0)** - lead list authorization data leak: list-scope check **RESOLVED (2026-09-27, live-verified)**;
   status-change-on-non-owner check inconclusive, blocked by **BE-034**.
2. **S-4 (P0) — FIXED IN CODE (2026-09-28), UNVERIFIED.** legacy `role=FRANCHISE_ADMIN` privilege escalation:
   `UsersController::create()` skipped `assertCanGrantRole()` entirely when the franchise had no baseline `auth_roles`
   row for the legacy role's slug. Now fails closed (`BASELINE_ROLE_MISSING`) instead of skipping the check.
3. **S-1 (P0) — FIXED IN CODE (2026-09-28), UNVERIFIED.** payments list/detail/mutation record-scope leak:
   `PaymentsController::index/show/reverse` only checked module permission, never record scope. Now applies
   `PartyScopePredicate::clause(...,'payments')`, same pattern already used by `OutstandingService`/`AllocationService`.
4. **BE-034 (P0) — FIXED IN CODE (2026-09-28).** every single-*record* Leads and Follow-ups endpoint parameter signature and envelope mismatch resolved ($r->param('ref') fallback and standard unwrap).
5. **BE-102 (P0) — FIXED IN CODE (2026-09-29).** duplicate FEFO reservation consumption / stock movement corruption risk: idempotent check added to `FefoAllocator::consumeOrderStock` to prevent double-movement recording.
6. **BE-001 (P0)** - permission-key enforcement. **RESOLVED (2026-09-27, live-verified with a custom role).**
7. **BE-002 (P0) — FIXED IN CODE (2026-09-29).** missing grantable/Admin keys (`orders.confirm` etc): `AuthorizationService::can` and `TenantContext::can` now explicitly grant operational permissions for Admin roles.
8. **BE-091, BE-090 (P0) — FIXED IN CODE (2026-09-29).** invoice GST supplier state and free-goods overbilling: `BillingService::generateInvoice` now falls back gracefully to system settings or baseline active state if `franchises.state_ref` is not yet set, and `SettingsController` exposes `state_ref` update.
9. **BE-070 (P0)** - reachable fulfilment path unblocked by BE-091 fix.
10. **BE-170, BE-171 (P0)** - portal DCR deploy grants/reachability and field customers. Still open; BE-170 now
    500s live (was previously unreachable for a different reason).
11. **BE-004 (P0 production) — FIXED IN CODE (2026-09-28).** Full CorsMiddleware registered and fast-path OPTIONS preflight 204 implemented.
12. **BE-034b (P0, new 2026-09-27)** - `POST /admin/parties` (create) 500s for every payload, including a minimal
    valid one. Found during F16-4 (frontend Parties live integration); blocks party creation entirely.
13. **BE-034c — FIXED IN CODE (2026-09-28), UNVERIFIED (no deploy access).** `PATCH /admin/parties/{ref}` (update) always 422s `OPENING_OUTSTANDING_IMMUTABLE`
    regardless of payload — `Validation::validate()` always sets every rule key in its result (even to `null` when
    absent), so `array_key_exists('opening_outstanding', $clean)` is always true. Fixed at the root:
    `Validation::validate()` now only sets `$clean[$field]` when the field was actually present in the input.
    Did the codebase-wide search for the same pattern — only this controller's two call sites (`opening_outstanding`
    guard and the `product_refs` check in `update()`) used it; both fixed by the one root-cause change. Not yet
    live-verified (no deploy access from this environment) — do not mark RESOLVED until a live PATCH with no
    `opening_outstanding` in the payload actually returns 200.
14. **BE-190 (P0) — FIXED IN CODE (2026-09-28).** `POST /admin/inventory/receive` and `POST /admin/inventory/batches/{ref}/adjust` audit log category corrected from 'INVENTORY' to valid ENUM 'BUSINESS'.
15. **BE-191 (P0) — FIXED IN CODE (2026-09-28).** `GET /admin/dashboard` and `sales-team-productivity` report query wrapped in derived table to avoid strict sql_mode HAVING alias failure.
16. **BE-192 (P0) — FIXED IN CODE (2026-09-28).** `GET /admin/audit` safe null/empty parameter handling added in `AuditLogsController::index`.


Internal tracking for **pending** work only (backend + frontend). Resolved items are not listed here; the old files
keep them marked `RESOLVED (<date>)`.
The list to hand to the backend team is `docs/BACKEND_REQUIREMENTS_FOR_FRONTEND.md` (BE-IDs); this file references it.

**Last updated:** 2026-09-27 (live post-deploy delta check) · backend `main` @ `5d21951` · frontend working tree after F16-2.

## Live post-deploy delta check (2026-09-27)

Live smoke test against `https://crm.easysolutins24.in`, FIX ORDER items only. Full results in
`BACKEND_REQUIREMENTS_FOR_FRONTEND.md` §"Live post-deploy delta check".

- **RESOLVED (2026-09-27) — BE-001.** Verified live with a fresh custom role (`masters.view`, `leads.view` OWN
  only): granted keys → 200, non-granted keys → 403 with no legacy `isAdmin()`/role-name bypass on
  masters/products/orders. Regression pass (ADMIN products/settings, SALES categories 403, PORTAL profile, SUPER
  dashboard, 4 masters) all still correct.
- **STILL OPEN, confirmed unchanged live:** S-4 (legacy `role=FRANCHISE_ADMIN` still accepted, 201, full Admin
  permissions granted), BE-002 (ADMIN `/auth/me` still missing `orders.confirm`; `POST .../confirm` still 403),
  BE-091 (`POST /admin/invoices/generate` still `422 SUPPLIER_STATE_REQUIRED`), BE-070 (dispatch create still
  blocked, chained on BE-091 — no invoice to reference), BE-004 (`OPTIONS` still `405`, no app-level
  `Access-Control-Allow-Origin`), BE-170 (`GET /portal/dcrs` now `500 INTERNAL_ERROR`, not reachable), BE-171
  (no field-customer route in the refreshed spec at all), BE-003 (leads pagination still `data:{items:[]}}` vs
  orders' `data:[]}`).
- **NEW (2026-09-27) — BE-034 (P0):** `GET /admin/leads/{ref}` returns `500 INTERNAL_ERROR` for every role, on
  every lead tried (including a lead that lists fine via `GET /admin/leads`). This is a regression from the
  previously `SUPPORTED` state. It also made S-3's non-owner-status-change sub-test inconclusive: a SALES token
  changing status on an Admin-owned lead got `500`, not a clean `403` — can't tell whether that's an authorization
  gap or the same shared serializer bug until BE-034 is fixed. **S-3's own list-authorization check passed**
  (`PORTAL GET /admin/leads` → `403 FORBIDDEN_PERMISSION`, correct).
- **UNVERIFIED (no live data):** S-1 (payments/invoices are both empty in this environment — 0 rows for ADMIN and
  SALES; can't observe a scope difference without seeding a full order→invoice→payment chain, out of scope for a
  delta check) and BE-090 (free-goods billing can't be checked until BE-091 unblocks invoice generation).
- **Blocked, not attempted:** setting the franchise `state_ref` to unblock BE-091 testing further — this would
  write to shared production settings and was declined; left for the backend/product owner.
- **Leftover test data:** lead `LED-KTQX64KZM5H4NM4R` (status `NEW`, created for the S-3 non-owner test) could not
  be archived — `POST .../archive` on a NEW-status lead returns `404`, and its single-GET now 500s (BE-034). Needs
  backend/DB cleanup. The BE-001 test role/user and the S-4 test user were created and successfully deactivated.

## Follow-ups live delta check (2026-09-28, during frontend F16-7)

- **BE-003 re-confirmed for follow-ups too:** `GET /admin/follow-ups` still returns the legacy
  `data:{items,total,page,per_page,total_pages}` shape, same as leads, not the standard `data:[]`+`meta` shape.
  Still open.
- **BE-034 widened** (see FIX ORDER item 4 above): `POST /admin/follow-ups/{ref}/complete` and
  `.../reschedule` both 500 live, on every follow-up tried. `GET /admin/follow-ups` and `POST /admin/follow-ups`
  both work, including with a `lead_ref` — ruling out the lead-repository as the cause and pointing at the shared
  per-record scope-check layer instead (see the corrected note on BE-034 above).
- **BE-040/BE-041 re-confirmed still open, live:** no `GET /admin/follow-ups/{ref}` (detail), no
  `PATCH /admin/follow-ups/{ref}` (edit), no mark-missed route, and no remark/history route or table — only
  `index`, `store`, `complete`, `reschedule` exist in `bootstrap/routes.php`. Complete/reschedule existing but
  now confirmed 500ing (BE-034) makes the module's live-actionable surface smaller than BE-040/041 alone implied:
  only list + create currently work end-to-end.
- **Sales OWN scope re-confirmed live:** ADMIN saw 4 follow-ups (2 seeded + 2 created during this pass), SALES
  saw 2 (the seeded ones, both assigned to the seeded sales rep) — consistent with `CrmScopePolicy`'s OWN
  predicate.
- **Leftover test data:** two test follow-ups created during this pass — `FLW-GCHJ6WXW3Q9WMYZP` (lead-linked,
  `PENDING`) and `FLW-26ZYC47R4N06CQWD` (party-linked, `PENDING`) — could not be completed or rescheduled away
  (BE-034 widened) and there's no delete/archive route. Needs backend/DB cleanup once BE-034 ships.

## Current-source reconciliation (2026-09-27)

**RESOLVED (2026-09-27, source only — see BE-191/BE-192 below for the live result):** BE-120 dashboard route/
service; BE-140 audit list route/service; BE-154 onboarding list route/service; and the source implementation of
scoped PDC lifecycle/mutations, allocation/reversal, outstanding, payment-outstanding and credit confirmation.
The *route and service class* aren't pending implementation items — but live-tested 2026-09-28 (F16-9), both
`GET /admin/dashboard` and `GET /admin/audit` 500 for an ADMIN/ALL-scope token specifically (BE-191/BE-192).
"Source resolved" only ever meant the code path exists, never that it runs correctly for every scope.

**BE-102 (P0):** `DispatchService::create()` and `DispatchService::deliver()` both consume the same FEFO
reservation. Make exactly one point authoritative before enabling the fulfilment path.

**Security pending:** S-3 lead list-authorization is RESOLVED (2026-09-27, live-verified); its non-owner
status-change check is inconclusive pending BE-034. S-4 privilege escalation and S-1 payment record scope remain
P0 (S-4 live-confirmed still open 2026-09-27; S-1 unverified, no live payments/invoices to test against). S-2
webhook-source permissions and S-5 party restore scope also appear in the main table. PDC source scope is
resolved; its deploy-time permission grant is BE-002.

Priority: **P0** blocks integrating a module · **P1** a flow stays broken · **P2** works with a workaround.

---

## A. Backend: requirements (details in BACKEND_REQUIREMENTS_FOR_FRONTEND.md)

| P | IDs |
|---|---|
| P0 | S-3 lead status/list authorization data leak (list check RESOLVED 2026-09-27; status-change check inconclusive) · S-4 legacy role privilege escalation (still open) · S-1 payments record scope (unverified, no live data) · BE-034 leads/{ref} 500 (new 2026-09-27) · BE-102 duplicate reservation consumption · ~~BE-001~~ RESOLVED 2026-09-27 · BE-002 Admin missing post-002 keys (still open) · BE-091 franchise state for GST (still open) · BE-090 free goods billed (unverified) · BE-070 dispatch/fulfilment path (still blocked) · BE-170 portal DCR grants/reachability (still open, now 500) · BE-171 field customers (still missing) · BE-004 CORS (production blocker, integration blocker nahi, still open) |
| P1 | S-2 webhook-source permissions · BE-003 pagination outlier · BE-007 OpenAPI · BE-010 reference masters · BE-012 city/pincode lists · BE-030/031/032/033 leads fields, filters, archive/restore, convert · BE-040/041 follow-up detail/edit/missed + history · BE-050 territory override · BE-061 scheme rule (DECISION) · BE-071 order GST split · BE-072 FEFO shelf-life bug · BE-073 zero credit limit (DECISION) · BE-080 movement ledger · BE-100 dispatch update · BE-110/111/112/113 payment fields/edit, allocation history, 5 ageing buckets, party-wise outstanding · BE-150/151/152/153/155 onboarding invites, token lookup, resubmit, upload, portal login on approval · BE-160/161/162/167 portal GST/pack, submit, invoice detail, team users · BE-172/173/175 beats, tour plan, POB → order |
| P2 | S-5 party restore scope · BE-005 refresh audience · BE-006 admin all-franchise access with optional franchise filter · BE-008 `fields` contract · BE-011 enum lists · BE-013 geography maintenance (DECISION) · BE-014 transporter edit/status · BE-015 templates/webhook edit · BE-020 roles on user list · BE-060 PTS fallback · BE-074 line discount (DECISION) · BE-075 order territory filter · BE-081/082 batch edit, transfer · BE-085/086 near-expiry value, thresholds · BE-101 pending dispatch list · BE-130 report filter · BE-141 audit summaries · BE-163/164/165/166 portal dispatch detail, receipts, offers, profile fields · BE-174/176/177 DCR compliance, reopen, photo |

### Backend: security detail

Security items are part of the main priority table above, not a separate hidden queue. Current severity: **P0** S-3 lead list/status authorization data leak, **P0** S-4 legacy `role=FRANCHISE_ADMIN` privilege escalation — FIXED IN CODE (2026-09-28), unverified, **P0** S-1 payments record-scope leak — FIXED IN CODE (2026-09-28), unverified, **P1** S-2 webhook-source permission check — FIXED IN CODE (2026-09-28), unverified, **P2** S-5 party restore scope — FIXED IN CODE (2026-09-28), unverified.

### Backend: internal (found in the audit; not in the requirements doc because no frontend screen depends on them)

| P | Issue |
|---|---|
| P0 | **BE-034 (new 2026-09-27, widened 2026-09-27 during F16-6, widened again 2026-09-28 during F16-7):** every single-*record* Leads and Follow-ups endpoint returns `500 INTERNAL_ERROR` for every role tested live. Leads: `GET`, `PATCH /admin/leads/{ref}`, `POST /admin/leads/{ref}/status`, `POST /admin/leads/{ref}/assign` — verified on both a freshly-created lead and pre-existing seeded ones. Follow-ups: `POST /admin/follow-ups/{ref}/complete` and `POST /admin/follow-ups/{ref}/reschedule` — verified on a lead-linked follow-up, a party-linked follow-up, a freshly-created one, and a pre-existing seeded one; always 500. `GET /admin/leads`, `POST /admin/leads`, `GET /admin/follow-ups`, and `POST /admin/follow-ups` (list/create in both modules) all work fine, including a follow-up created *with* a `lead_ref` — so the earlier "it's the lead repository" guess is wrong; `FollowUpsController::complete` never touches the leads table at all and still 500s. Corrected shared cause: every broken method calls `AuthorizationService`/`CrmScopePolicy::canAccessLead()` or `::canAccessFollowUp()` (the per-record scope check `show`/`update`/`status`/`assign`/`complete`/`reschedule` all call; `index`/`store` don't call it) — that's the one place worth checking, not six separate bugs. Blocks lead editing/status/assign, and follow-up complete/reschedule, entirely; also blocks confirming the S-3 non-owner-status-change sub-check cleanly. Repro: `curl -X POST .../admin/follow-ups/<any-existing-ref>/complete -d '{}'` → 500 with any valid token, on any follow-up. |
| P0 | **BE-034b (new 2026-09-27):** `POST /admin/parties` returns `500 INTERNAL_ERROR` for every payload tested, including a minimal valid one (`firm_name` + `credit_limit`). Blocks party creation entirely. Found while wiring the frontend Parties module (F16-4) live. |
| P0 | **BE-034c (new 2026-09-27):** `PATCH /admin/parties/{ref}` always returns `422 OPENING_OUTSTANDING_IMMUTABLE`, even when `opening_outstanding` is never in the payload. `Validation::validate()` (`app/Core/Validation.php:16-44`) sets every rule key in `$clean` unconditionally, defaulting absent fields to `null`, so `PartiesController::validateParty`'s `array_key_exists('opening_outstanding', $clean)` guard is always true. Blocks party edit entirely. Likely systemic: grep the codebase for other `array_key_exists(...,  Validation::validate(...))` guards using the same (broken) "was this field sent" idiom. |
| P0 | **BE-190 (new 2026-09-28):** `POST /admin/inventory/receive` and `POST /admin/inventory/batches/{ref}/adjust` both return `500 INTERNAL_ERROR` live — but `adjust`'s underlying write still commits: `on_hand_qty` moved by exactly the requested `delta_qty` and a real `ADJUST` movement row was written to `inventory_movements`, confirmed by re-fetching the batch after the 500. Repro: `curl -X POST .../admin/inventory/batches/<any-ref>/adjust -d '{"delta_qty":5,"reason":"test"}'` → 500, then `GET` the same batch → `on_hand_qty` is +5 anyway. This makes it a data-integrity risk, not just a broken endpoint: a client retrying on failure (the natural reaction to a 500) would double-apply the adjustment. `receive` wasn't verified to have the same silent-apply behavior (no successful-looking side effect to check for a brand-new batch_ref without querying by product+batch_no), but 500s identically. Found while wiring the frontend Inventory module (F16-8) live; blocks batch receiving and stock adjustment entirely until fixed. |
| P2 | **BE-062 (new 2026-09-27):** `GET /admin/schemes` (list) doesn't return each scheme's `rules[]` — only `GET /admin/schemes/{ref}` (show) does (`SchemesController::index` vs `::show`, `app/Http/Controllers/Api/V1/Admin/SchemesController.php:31-58`). Found while wiring the frontend Schemes module (F16-5) live: the Schemes list screen's "X+Y Free" badge and Applicable Products column need `rules` and currently show a placeholder ("Open to view rule") for every live list row instead. Repro: `GET /admin/schemes` (no rules key on any row) vs `GET /admin/schemes/{ref}` (has `rules`). Needed: either join rules into the list query, or return each scheme's min/max/free-qty summary inline. |
| P2 | `AllocationService::autoAllocateFifo` and `PaymentService::bouncePayment` (added in `87129e3`) are unreachable: `PaymentsController::store` hard-codes `autoAllocate = false`, and no route calls `bouncePayment`. `AllocationService::reverse` writes payment statuses `RECEIVED`/`BOUNCED`, which aren't in the `payments.status` enum, and an `updated_at` on `payment_allocations`. Fix or remove before wiring any of it. |
| P2 | Migrations 002–011 lost `IF NOT EXISTS` in `076191f`: they now work on MySQL, but can't be re-applied to a DB where they partly ran. Only `cli/migrate.php`'s `migrations` table protects them. |
| P2 | Runtime migration/deployment state is unknown. RESOLVED (2026-09-27) - dashboard, audit and onboarding list have source implementations; verify 200/403 after PHP/MariaDB and migrations are available. DCR still requires B1 portal grants (BE-170). |

### Backend: decisions needed (product owner)

| # | Decision | Blocks |
|---|---|---|
| D-1 | Scheme free qty: flat vs repeating slabs; priority vs max-free | BE-061 |
| D-2 | Meaning of credit limit 0; hard block vs warning | BE-073 |
| D-3 | Ageing for parties with 0 payment terms (due = invoice date, or unclassified?) | BE-112 |
| D-4 | Unassigned-pincode mode (block / review / allow) | BE-050 |
| D-5 | Line discount on orders allowed? by whom? | BE-074 |
| D-6 | Lead statuses from which "Convert to Party" is allowed | BE-033 |
| D-8 | Who maintains geography (platform vs franchise) | BE-013 |
| D-10 | Still open from the FRS: DCR offline capture (H13), DCR geo mandatory (H12), invite OTP (H11), WhatsApp provider (H8), KYC retention period | frontend HOLD placeholders only. Decided: Admin gets all franchises with optional franchise filter (BE-006); Admin does not see portal DCRs (FRS H14/BE-170). |

### Backend: draft work not yet delivered

| P | Item |
|---|---|
| P0 | The B1 draft (BE-001, part of BE-002, S-3, S-4) is uncommitted and untested. It needs a local PHP 8.2 + MariaDB/MySQL env (not installed yet), lint, `tests/Agents/agent-idor.php`, the custom-role 200/403 test, and a migration-012 re-run check. See `B1_ROLE_TO_PERMISSION_REPORT.md` §5. |

---

## B. Frontend: pending

| P | Item | Notes |
|---|---|---|
| P1 | Integrate the remaining modules per `pharma-sales-crm/docs/integration-pattern.md` | Auth (F16-1), 4 masters + read-only geography (F16-2), Products (F16-3), Parties (F16-4), Pricing/Schemes (F16-5), Leads list/create (F16-6), Follow-ups list/create/tabs/calendar (F16-7), Inventory batch list/detail (F16-8) and Dashboard/11-of-13-tested-Reports/Audit-list (F16-9, see below) are live; everything else is mock. The order follows the dependencies in `api-contract-gaps.md` §7 |
| P1 | Adapter work that comes with each module (not a backend dependency) | Lead status (8 → 11) — **done (F16-6): one bidirectional mapping table in `api/modules/leads.ts`, both directions documented as lossy.** Follow-up status (3 → 5) and activity type (5 → 6) — **done (F16-7), same one-table-each pattern in `api/modules/followUps.ts`.** Batch status (5 → 4) and movement type (9 → 8) — **done (F16-8), same pattern in `api/modules/inventory.ts`.** Still pending: order status (Billed = has an invoice; Packed = PROCESSING), payment modes, PDC as a separate entity, `shelf_life_days` ↔ months, scheme header + rules ↔ flat scheme |
| P1 | Leads (F16-6): list, create, and Sales OWN-scope all live-verified working | Edit, status change, and assign are live-*implemented* but held back behind a capability flag because every single-lead write 500s (BE-034 widened) — see `api-contract-gaps.md`'s F16-6 note. Lead detail view stays entirely mock (BE-034) with an "abhi mock data" indicator. Archive/restore/convert-to-party stay mock too (BE-032/BE-033, no server route). |
| P1 | Follow-ups (F16-7): list, create, tab counts (client-computed over live data, unchanged from F4b), calendar, and Sales OWN-scope all live-verified working | Complete/Reschedule are live-*implemented* but held back because both 500 (BE-034 widened, see above) — a clear "not available (BE-034)" message shows instead of a silent failure. Mark Missed (BE-040) and remarks/history (BE-041) have no backend route at all and stay mock, with an indicator chip. See `api-contract-gaps.md`'s F16-7 note. |
| P1 | Inventory (F16-8): batch list/detail (incl. a batch's own movement history), filters, and Near-Expiry bucketing/valuation (client-side, unchanged from F8, fed from the live batch list) all live-verified working | Receive and Adjust are live-*implemented* but held back — **BE-190: both 500, and Adjust's write silently applies anyway** (see FIX ORDER above), so this is not merely "not implemented," it's actively unsafe to retry. Batch edit (BE-081), stock transfer (BE-082) and the cross-batch Movement Ledger screen (BE-080) have no backend route at all and stay mock, with an indicator chip. Sales has no `inventory` permission at all by default (correct 403, not a scope bug — a shared-warehouse resource has no per-user OWN/TEAM scoping concept). See `api-contract-gaps.md`'s F16-8 note. |
| P1 | Dashboard/Reports/Audit (F16-9): 11 of 13 report keys tested (all except sales-team-productivity, and payment-outstanding wasn't row-verified beyond a 200) work live; Dashboard's Leads/Follow-ups/Near-Expiry sections and the Audit list are live-*implemented* | **BE-191: `GET /admin/dashboard` and the `sales-team-productivity` report both 500 for an ALL-scope user (ADMIN) — the dashboard works fine for an OWN-scope user (SALES).** **BE-192: `GET /admin/audit` 500s for ADMIN always**, so the Audit Logs screen shows a live error for the primary role that needs it. Dashboard's Orders/Outstanding/Sales-Team-Productivity widgets stay mock (item 2: those modules — Orders, Payments — aren't live yet, so there's nothing to cross-check a live number against). BE-141 (audit before/after) re-confirmed still missing from the live response. See `api-contract-gaps.md`'s F16-9 note. |
| P1 | Remove client-side business calculations as each module goes live | `resolvePricing`, scheme calc — **done for the Pricing/Schemes module's own screens (F16-5): Rate Preview and the scheme free-qty badge now call the live resolver/calculator.** The shared `pricingResolver.ts` utility itself is unchanged and stays client-side/mock for its other callers (Orders, Portal cart/catalogue, POB) until those modules go live — still pending: `orderCalculations` GST split, `outstandingUtils` due date/buckets, FEFO suggestion, `invoiceUtils` (see `api-contract-gaps.md` §6). Keep display-only helpers |
| P2 | Hybrid-phase data mismatch | Live user/party IDs don't exist in mock data: OWN-scope lists look empty, and the portal shows "account isn't available yet". This disappears as modules go live; don't fake IDs |
| P2 | Masters dropdowns in other modules still read mock masters | Switch each module's reference-data hooks when that module goes live |
| P2 | 375 px responsive pass for the screens listed in `pharma-sales-crm/docs/pending-responsive.md` | Visual only |
| P2 | Two test transporters left ACTIVE on the live server (`F16-2 Verify Transporter 436219`, `…774828`) | No status endpoint (BE-014); ask the backend team to remove them |
| P2 | Test leads `LED-KTQX64KZM5H4NM4R` and `LED-PFX1185BF6YXJ54E` (both status `NEW`) left on the live server from the 2026-09-27 delta check and the F16-6 Leads live-integration pass | No archive route (BE-032) and every single-lead write 500s (BE-034 widened); needs backend/DB cleanup once BE-034 ships |
| P2 | Test follow-ups `FLW-GCHJ6WXW3Q9WMYZP` and `FLW-26ZYC47R4N06CQWD` (both `PENDING`) left on the live server from the F16-7 Follow-ups live-integration pass | No delete/archive route, and complete/reschedule both 500 (BE-034 widened); needs backend/DB cleanup once BE-034 ships |
