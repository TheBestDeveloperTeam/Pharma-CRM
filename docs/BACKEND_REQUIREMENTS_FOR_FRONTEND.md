Status ka single source of truth: Pharma-CRM/docs/OPEN_ISSUES.md. Backend ko dene wali list: docs/BACKEND_REQUIREMENTS_FOR_FRONTEND.md

# Backend requirements for the frontend

## FIX ORDER

Combined order by frontend blocking priority plus security/data-corruption severity:

1. **S-3 (P0)** - lead list/status authorization data leak.
2. **S-4 (P0)** - legacy `role=FRANCHISE_ADMIN` privilege escalation.
3. **S-1 (P0)** - payments list/detail/mutation record-scope leak.
4. **BE-102 (P0)** - duplicate FEFO reservation consumption / stock movement corruption risk.
5. **BE-001, BE-002 (P0)** - permission-key enforcement and missing grantable/Admin keys.
6. **BE-091, BE-090 (P0)** - invoice GST supplier state and free-goods overbilling.
7. **BE-070 (P0)** - reachable fulfilment path.
8. **BE-170, BE-171 (P0)** - portal DCR deploy grants/reachability and field customers.
9. **BE-004 (P0 production)** - production blocker, integration blocker nahi.


## Live post-deploy delta check — 2026-09-27

Smoke-tested against `https://crm.easysolutins24.in` with live ADMIN/SALES/PORTAL/SUPER tokens plus a
freshly-created custom role, FIX ORDER items only (not a full re-audit). Full narrative in
`OPEN_ISSUES.md` §"Live post-deploy delta check".

- **BE-001: RESOLVED, live-verified.** Custom role with only `masters.view` + `leads.view` (OWN): granted keys
  returned 200, ungranted keys (`masters.create`, `orders.*`, `products.view`) returned 403 with no legacy
  `isAdmin()`/role-name bypass. Regression pass on ADMIN/SALES/PORTAL/SUPER and the 4 live masters all correct.
- **S-3: list-authorization check RESOLVED, live-verified** (`PORTAL GET /admin/leads` → 403). The
  non-owner-status-change check is **inconclusive** — it returned `500`, not `403`, because of new defect BE-034
  below, not because it returned data.
- **S-4, BE-002, BE-091, BE-070, BE-004, BE-170, BE-171, BE-003: all still open**, each re-confirmed against the
  live server with the exact request in its §4 Verify steps (see `OPEN_ISSUES.md` for the per-item detail). BE-170
  now fails with `500` rather than being merely unreachable.
- **New defect — BE-034 (P0), widened 2026-09-27 during F16-6, widened again 2026-09-28 during F16-7: every
  single-*record* Leads and Follow-ups endpoint returns 500 for every role.** Leads: `GET /admin/leads/{ref}`,
  `PATCH /admin/leads/{ref}`, `POST /admin/leads/{ref}/status`, `POST /admin/leads/{ref}/assign` all 500 on every
  lead tried (fresh and pre-existing). Follow-ups: `POST /admin/follow-ups/{ref}/complete` and
  `.../reschedule` both 500 too, on a lead-linked follow-up, a party-linked one, a fresh one, and a pre-existing
  one. `GET/POST /admin/leads` and `GET/POST /admin/follow-ups` (list/create, both modules) all work, including a
  follow-up created *with* a `lead_ref` — which rules out the leads repository as the shared cause, since
  `FollowUpsController::complete` never touches the `leads` table and still 500s. Corrected guess: every broken
  method calls `AuthorizationService`/`CrmScopePolicy::canAccessLead()` or `::canAccessFollowUp()` — the
  per-record scope check `show`/`update`/`status`/`assign`/`complete`/`reschedule` all call, that `index`/`store`
  don't — worth checking there first, not six separate bugs. This is a regression: the coverage grid below still
  says Leads Get/Status/assign and Follow-ups complete/reschedule are SUPPORTED, but none of them are live. Needs
  a backend fix before Lead detail/edit/status/assign or Follow-up complete/reschedule can go live.
- **New defects — BE-034b/c (P0), found 2026-09-27 during F16-4 (frontend Parties module going live):**
  `POST /admin/parties` 500s for every payload (even a minimal valid one), and `PATCH /admin/parties/{ref}`
  always 422s `OPENING_OUTSTANDING_IMMUTABLE` regardless of what's sent, because `Validation::validate()`
  (`app/Core/Validation.php:16-44`) always sets every rule key in its result — defaulting to `null` when the
  field is absent — so `PartiesController::validateParty`'s `array_key_exists('opening_outstanding', $clean)`
  check is always true. Party list/get/status/archive/restore all still work live; create and update do not.
  This `array_key_exists`-on-`Validation::validate()` idiom is likely used elsewhere in the codebase with the
  same bug — worth a grep across controllers.
- **New defect — BE-190 (P0), found 2026-09-28 during F16-8 (frontend Inventory module going live), a
  data-integrity risk, not just a broken endpoint:** `POST /admin/inventory/receive` and
  `POST /admin/inventory/batches/{ref}/adjust` both 500 live — but `adjust`'s underlying write commits anyway.
  Verified: called `adjust` with `delta_qty: 5` on a batch with `on_hand_qty: 1500`, got `500`, then `GET` that
  same batch and it showed `on_hand_qty: 1505` with a real `ADJUST` row in its `movements`. A user or client
  retrying after the 500 (the natural response to a failure) would double-apply the adjustment. Batch
  list/get/near-expiry all still work live; only receive/adjust are affected. Needs a backend fix before batch
  receiving or stock adjustment can go live — and given the silent-apply behavior, this should be treated as more
  urgent than a typical 500.
- **New defect — BE-191 (P0), found 2026-09-28 during F16-9 (frontend Dashboards/Reports going live):**
  `GET /admin/dashboard` 500s for an ALL-scope token (ADMIN) but returns `200` fine for an OWN-scope token
  (SALES). The `sales-team-productivity` report 500s the same way for the same kind of user — almost certainly
  the same query, since `ScopedAnalyticsService::team()` (the dashboard's `sales_team` section) only runs when
  `scopeFor('internalUsers')==='ALL'`, and that report definition runs the identical correlated-subquery/`HAVING`
  shape over `users`/`leads`/`orders`. This blocks the dashboard for the role (Admin) it matters most for.
- **New defect — BE-192 (P0), found 2026-09-28 during F16-9:** `GET /admin/audit` 500s for ADMIN every time,
  with or without query params, on a token that does hold `auditLogs.view`. `GET /super/audit` (a different,
  platform-level controller) works fine and returns real rows, so this is specific to
  `AuditLogsController::index`, not the table or a shared audit-writing path. This corrects the earlier
  "BE-140 RESOLVED" note below, which only confirmed the route/controller exist in source, not a live `200`.
- **S-1 and BE-090: unverified**, not regressions — the live environment currently has 0 payments and 0 invoices,
  so there's no data to observe a scope leak or a free-goods overcharge against. Needs seeded test data or a
  written-through order→invoice→payment chain to check properly.

## Current-source reconciliation — 2026-09-27 (authoritative)

This reconciliation supersedes an earlier live-environment snapshot further below. It was made by reading the
current backend and frontend source at `main` `5d21951`, plus the uncommitted B1 permission draft. It does **not**
claim runtime validation: PHP/Composer/MariaDB are unavailable locally. Do not implement any historical statement
below where this section marks it resolved or replaces it with a BE/S item.

### Priority summary

| Priority | Count | Current source requirements |
|---|---:|---|
| P0 | 12 | S-3, S-4, S-1, BE-102, BE-001, BE-002, BE-091, BE-090, BE-070, BE-170, BE-171, BE-004 (production blocker, integration blocker nahi) |
| P1 | 34 | Contract, pagination, lifecycle, scope and missing-screen-flow items retained below, including BE-003, 007, 010, 012, 030-033, 040-041, 050, 061, 071-075, 080, 100, 110-113, 150-153, 155, 160-167, 172-177 and S-2. |
| P2 | 26 | Usability, optional filtering and policy-decision items retained below, including S-5. |

**Resolved in current source — do not raise as a backend implementation gap:**

- **RESOLVED (2026-09-27) — BE-120.** `GET /api/v1/admin/dashboard` calls
  `ScopedAnalyticsService::dashboard()` with `dashboard.view`; its outstanding widget calls the authoritative
  `OutstandingService`, not a placeholder.
- **RESOLVED (2026-09-27) — BE-140.** `GET /api/v1/admin/audit` has a protected controller and paginated audit
  query. Its row enrichment remains **BE-141**.
- **RESOLVED (2026-09-27) — BE-154.** `GET /api/v1/admin/onboarding` has tenant/scope filtering and pagination.
- **RESOLVED (2026-09-27) — PDC production wiring.** List/count/detail/register/realize/bounce/cancel use tenant
  filters, `PartyScopePredicate`, and mutation transactions with `FOR UPDATE`. This is source-ready for
  ALL/OWN/TEAM/TERRITORY/NONE; its B1 permission grant is still covered by BE-002.
- **RESOLVED (2026-09-27) — allocation and reversal core.** Allocation locks the payment and same-party invoice,
  prevents over-allocation, writes an active allocation and updates both running totals; reversal reverses active
  allocations transactionally. UI allocation history/detail remains BE-111.
- **RESOLVED (2026-09-27) — invoice outstanding authority.** `OutstandingService` is the sole invoice calculation
  used by outstanding, payment-outstanding and dashboard source paths. Fully allocated posted invoices are excluded;
  reversed/cancelled payments do not reduce a balance; only realized PDCs create a payment.
- **RESOLVED (2026-09-27) — credit confirmation path.** The production `OrderService::confirmOrder()` takes an
  order lock then `PartyCreditService::checkForConfirmation()` locks the party and blocks only
  `projected_exposure > credit_limit` (equality is allowed). Posted invoices and uninvoiced orders are separated;
  pending/bounced/cancelled PDCs are ignored.

### Current 19-module coverage grid

Every cell is the frontend contract status, not merely whether a SQL table exists. `SHAPE MISMATCH` includes an
endpoint that is present but returns the wrong DTO, misses a required filter, has incompatible pagination, or cannot
execute the frontend flow. Counts in this grid: **SUPPORTED 51, MISSING 39, SHAPE MISMATCH 24** (no cell is omitted).

| Module | List | Get | Create | Update | Status | Custom / detail |
|---|---|---|---|---|---|---|
| Masters + geography | SUPPORTED | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-010/012 | SUPPORTED | SHAPE MISMATCH BE-011/013/014/015 |
| Roles + permissions | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-001/002 |
| Internal users | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-020 |
| Leads | SHAPE MISMATCH BE-003/031 | SUPPORTED | SHAPE MISMATCH BE-030 | SHAPE MISMATCH BE-030 | SUPPORTED | MISSING BE-032/033 |
| Follow-ups | SHAPE MISMATCH BE-003 | MISSING BE-040 | SHAPE MISMATCH BE-041 | MISSING BE-040 | MISSING BE-040 | MISSING BE-041 |
| Parties | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED |
| Territory | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-050 |
| Products | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED |
| Pricing | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-060 |
| Schemes | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-061 |
| Orders | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-070 | SHAPE MISMATCH BE-071/072/073/074/075 |
| Inventory + near-expiry | SHAPE MISMATCH BE-085 | SUPPORTED | SUPPORTED | MISSING BE-081 | MISSING BE-081 | MISSING BE-080/082/086 |
| Billing + invoices | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-090/091 | MISSING | SUPPORTED | SUPPORTED |
| Dispatch + delivery | SUPPORTED | SUPPORTED | MISSING BE-070 | MISSING BE-100 | MISSING BE-070/102 | MISSING BE-101 |
| Payments + outstanding + PDC | SHAPE MISMATCH S-1/BE-112 | SHAPE MISMATCH S-1/BE-111 | SHAPE MISMATCH BE-110/S-1 | MISSING BE-110 | SUPPORTED | SHAPE MISMATCH BE-111/112/113 |
| Dashboard + reports + audit | SUPPORTED | SUPPORTED | MISSING | MISSING | MISSING | SHAPE MISMATCH BE-007/130/141 |
| Onboarding + KYC | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-150/153 | MISSING BE-152 | SUPPORTED | SHAPE MISMATCH BE-155 |
| Distributor portal | SHAPE MISMATCH BE-160/161 | SHAPE MISMATCH BE-162/163 | SHAPE MISMATCH BE-161 | SHAPE MISMATCH BE-166 | SHAPE MISMATCH BE-161 | MISSING BE-164/165/167 |
| DCR + settings/notifications/webhooks | SHAPE MISMATCH BE-170/S-2 | SHAPE MISMATCH BE-170 | SHAPE MISMATCH BE-170/171/172 | MISSING BE-172/173 | MISSING BE-174/176 | MISSING BE-175/177 |

### New current-source defect

**BE-102: Dispatch consumes the same reservation twice (P0)**

- **Evidence:** `DispatchService::create()` calls `FefoAllocator::consumeOrderStock()` after its order-state gate;
  `DispatchService::deliver()` calls it again for the same order. Once the BE-070 state-path defect is corrected,
  a successful dispatch followed by delivery either fails with a reservation-state/concurrency error or double-applies
  stock movements.
- **Needed:** choose exactly one stock-consumption point (normally dispatch creation), make the other path read-only,
  and preserve a single `SALE` movement per reservation in the same transaction.
- **Acceptance:** a confirmed, invoiced, dispatched and delivered order has each reservation consumed once; on-hand
  falls once, reserved falls once, and retrying delivery is idempotently rejected without another movement.

### Contract facts that remain important

- **Outstanding/ageing:** frontend currently has four buckets (`0-30`, `31-60`, `61-90`, `90+`) and locally invents a
  30-day due date. Backend returns `NOT_DUE`, `0-30`, `31-60`, `61-90`, `91-120`, `120+`, `UNCLASSIFIED`, using only
  persisted `invoice.due_date`; zero payment terms currently persist no due date. Resolve BE-112/113 rather than
  changing either side silently. Opening outstanding is party-level and is absent from `OutstandingService` rows.
- **Payment-outstanding:** route and scope-safe core exist (`GET /admin/analytics/reports/payment-outstanding`), with
  pagination and safe sorting from `OutstandingService`; the frontend DTO is already close. It still needs the agreed
  bucket mapping/filter names and the party/opening summary contract (BE-112/113), not a second calculation.
- **Credit:** `credit_limit = 0` blocks any positive projected exposure in the current strict comparison. That is
  BE-073 policy clarification, not a reason to add an unrestricted override.
- **Security:** S-3, S-4 and S-1 are P0 and appear in the main priority table. S-1 is broader than the list alone: payment list has no OWN/TEAM/TERRITORY predicate, and show, reverse and create do not assert the target payment/party record scope. S-2 remains P1: webhook-source list/create have tenant filtering but no permission check. S-5 remains P2. These need 200-in-scope and 403/404-out-of-scope tests.

### Frontend source coverage used for this audit

The review covered the 25 frontend contract groups spanning F1-F15: authentication/session; permissions/roles;
geography/reference masters; users; leads; follow-ups; parties; territory; products; pricing; schemes; orders;
inventory/batches; invoices; dispatch; payments; allocations; outstanding/ageing/PDC; dashboard; 16 reports; audit;
onboarding/KYC; distributor portal; portal team; and DCR. Mock service functions remain the contract until each is
replaced by an API adapter; this document does not authorize frontend integration work.

**Audience:** Pharma-CRM backend team · **Date:** 2026-09-27 · **Backend checked at:** `main` @ `5d21951`
(committed code only. The uncommitted B1 draft in the working tree is covered in §9 and is **not** counted as done).

## 1. What this is

This is every backend change the React frontend (`pharma-sales-crm`) needs before each module can switch from mock
data to the API. The frontend already implements the full FRS scope (phases F1–F15). Every screen and every data
operation in it is treated as a requirement.

**Built from:**
- **Frontend (the contract):** `src/services/*`, `src/redux/dataSlice.ts` (every mutation), `src/hooks/use*Queries.ts`,
  `src/types/domain.ts`, the feature pages, and `docs/requirement-coverage.md`.
- **Backend (current code):** `bootstrap/routes.php`, controllers, domain services, repositories, schema +
  migrations 002–011, `public/api-docs/openapi.yaml` (2427 lines).
- **Previous audits:** `docs/api-contract-gaps.md` (F16-0b, F16-1, F16-2 live tests on 2026-09-26/27). Every item
  from them was re-checked against `5d21951`. Items fixed by the four commits after `bed8f35` are **not** listed here.

**Rules applied**
- An item appears only if something in the frontend breaks without it. That something is named in each item.
- No architecture advice. The **Needed** part of each item is what the frontend consumes, and **Verify** is how to
  prove it.
- When the frontend and backend disagree on a business rule and neither the FRS nor the workflows settle it, the item
  is marked **DECISION**. It needs a product answer, not a guess.

**How to read it:** start with §2 (one line per item), then §3 (what's missing per module), then the §4 detail for
the items you're implementing. §5 lists things that must **not** change.

**Conventions used below:**
- Base URL is `/api/v1`. All requests are `Authorization: Bearer <access_token>` unless marked *public*.
- Tokens: `ADMIN`, `SALES`, `PORTAL`, `SUPER` are the four seed logins (franchise `MUMBAI`); `CUSTOM` is a user with
  a custom role.
- "Envelope" means `{success, data, meta:{request_id, page, per_page, total, total_pages}}` for lists.
- Errors are `{success:false, error:{code, message, fields?: {name: string[]}}, meta:{request_id}}`.

---

## 2. Priority summary

P0 = the module cannot be integrated at all · P1 = integrates, but one flow is broken · P2 = works with a workaround.

| ID | Module | What is needed (one line) | P | Frontend impact if missing |
|---|---|---|---|---|
| S-3 | Security | Lead status endpoint unauthorized; lead list returns all leads to non-SALES users including portal users | P0 | Data leak and unauthorized lead lifecycle mutation |
| S-4 | Security | Legacy `role=FRANCHISE_ADMIN` on user create bypasses escalation checks | P0 | Privilege escalation to franchise admin |
| S-1 | Security | Payments list/detail/mutation record scope is missing | P0 | Payment data leak and out-of-scope mutations |
| S-2 | Security | Webhook-sources have no permission check | P1 | Portal or unprivileged tokens can list/create webhook sources |
| S-5 | Security | Party restore skips record-scope check | P2 | Out-of-scope party restore is possible |
| BE-001 | Cross-cutting | Authorize by permission key, not role name, on masters/catalog/products/pricing/schemes/settings/leads/follow-ups/portal | P0 | Every custom role (Accounts Team, Dispatch Team…) gets 403 or wrong data; the Roles screen has no effect |
| BE-002 | Roles/permissions | Grant the post-002 keys to Admin and make them grantable (`orders.confirm`, `dispatch.confirm`, `payments.pdc`, `payments.reverse`, `kyc.*`, …) | P0 | Admin can't confirm orders, deliver, run PDCs, reverse payments, convert onboarding or verify KYC |
| BE-003 | Cross-cutting | Leads and follow-ups lists use the standard pagination shape | P1 | Two list screens need a one-off adapter; shared list code breaks |
| BE-004 | Cross-cutting | CORS: `OPTIONS` 204 + `Access-Control-Allow-Origin` + allowed headers | P0 production | Production blocker, integration blocker nahi; dev works through a proxy |
| BE-005 | Auth | Refresh keeps the original session's surface/aud | P2 | The frontend can't send `X-Surface`, and audience enforcement stays off |
| BE-006 | Cross-cutting | Admin gets access to all franchises with an optional franchise filter; no re-login switcher | P2 | A multi-location admin can filter by franchise without signing in again |
| BE-007 | Cross-cutting | OpenAPI: document 39 routes, add response schemas, fix the invalid block | P1 | The frontend can't generate types; every module is hand-typed from PHP |
| BE-008 | Cross-cutting | `error.fields` only ever holds `{field: string[]}` (429 puts `retry_after` there) | P2 | Form error mapping must special-case 429 |
| BE-010 | Masters | Catalog-master lists for 10 reference masters (lead source, party type, designation, …) | P1 | 10 Masters tabs and their form dropdowns stay mock |
| BE-011 | Masters | Read-only enum lists for 6 enum-backed masters | P2 | Those 6 Masters tabs can't show server values |
| BE-012 | Masters | City list (by district) and pincode list (by city/district) | P1 | The State→District→City→Pincode cascade (Masters, Lead/Party/Onboarding forms) stops at District |
| BE-013 | Masters | **DECISION**: who maintains geography; create/update/status if in scope | P2 | Geography tabs are read-only |
| BE-014 | Masters | Transporter update + status + contact person/phone | P2 | Transporter edit/toggle hidden; the contact columns are blank |
| BE-015 | Masters | Notification-template and webhook-source update/status | P2 | Those two Masters tabs can't edit/toggle |
| BE-020 | Internal users | Users list includes each user's role ref + name | P2 | The Users list role column needs N extra calls |
| BE-030 | Leads | Lead fields `whatsapp`, `address`, `expected_value` | P1 | The lead form silently drops three fields |
| BE-031 | Leads | Server filters: priority, source, assignee, district/city, date range | P1 | Leads filter panel only works on the current page |
| BE-032 | Leads | Archive/restore from any status | P1 | Archive/Restore buttons fail for most statuses; Restore has no endpoint |
| BE-033 | Leads | Convert lead → party endpoint | P1 | The "Convert to Party" flow can't link the lead or mark it CONVERTED |
| BE-040 | Follow-ups | Follow-up get/edit, discussion/customer-response/next-action fields, mark missed | P1 | The Follow-up detail/edit/"Mark Missed" dialogs have no endpoint |
| BE-041 | Follow-ups | Append-only remark history and reschedule history | P1 | The remark timeline and reschedule history are empty |
| BE-050 | Territory | A recorded override unblocks submit/confirm; party-level override | P1 | The violation → override → proceed flow ends in TERRITORY_CONFLICT |
| BE-060 | Pricing | Add PTS as the last fallback in price resolution | P2 | Products without a franchise rate return PRICE_NOT_FOUND instead of PTS |
| BE-061 | Schemes | **DECISION**: free-qty rule (flat vs repeating slabs; priority vs max-free) | P1 | The scheme badge/preview and order free qty disagree with the server |
| BE-070 | Orders | A reachable path CONFIRMED → (Packed/PROCESSING) → DISPATCHED, one endpoint per step | P0 | Dispatch creation always fails (INVALID_ORDER_TRANSITION); orders never reach Dispatched/Delivered |
| BE-071 | Orders | CGST/SGST/IGST at line and total level on orders (preview before invoicing) | P1 | Order form and detail can't show the tax split |
| BE-072 | Orders | FEFO confirm must not treat product shelf-life as minimum remaining life | P1 | Confirm fails with INSUFFICIENT_STOCK for any product with realistic shelf life |
| BE-073 | Orders | `credit_limit = 0` must not mean "no credit allowed" (or a DECISION) | P1 | Orders for parties without a limit can never be confirmed |
| BE-074 | Orders | **DECISION**: line discount on orders | P2 | The order form's discount column is ignored |
| BE-075 | Orders | `territory_status` filter on the orders list | P2 | The dashboard "Territory violation" drill-down filters client-side |
| BE-080 | Inventory | Stock-movement ledger list across batches | P1 | The Movement Ledger screen has no data source |
| BE-081 | Inventory | Batch update (location, status, damaged qty) | P2 | Batch edit form can't save |
| BE-082 | Inventory | Stock transfer between locations | P2 | The Transfer dialog can't save |
| BE-085 | Near-expiry | Near-expiry rows with `days_left`, `available_qty`, `stock_value`; expired list | P2 | The frontend must look up MRP to value stock; the Expired tab has no source |
| BE-086 | Near-expiry / Settings | Persist near-expiry thresholds | P2 | Threshold settings reset per browser |
| BE-090 | Billing | Invoice must not bill scheme free goods | P0 | Every invoice with a scheme is overcharged; the totals don't match the order |
| BE-091 | Billing | Franchise `state_ref` settable (and set for MUMBAI) | P0 | Every invoice generation fails with SUPPLIER_STATE_REQUIRED |
| BE-100 | Dispatch | Dispatch update: status, LR, tracking URL, transporter, boxes | P1 | The dispatch edit/tracking screen has no endpoint |
| BE-101 | Dispatch | List of invoices without a dispatch | P2 | The Dispatch Pending screen must use the analytics report |
| BE-110 | Payments | Payment create keeps cheque/bank fields; edit/cancel before allocation | P1 | Cheque details are lost; Edit/Cancel have no endpoint |
| BE-111 | Payments | Allocation history in payment detail and per invoice | P1 | The Payment detail allocation table and invoice "collected" are empty |
| BE-112 | Payments | Ageing buckets: Not Due + 0-30 / 31-60 / 61-90 / 90+ | P1 | The Outstanding tabs, chart and dashboard widget don't match |
| BE-113 | Payments | Party-wise outstanding summary (opening + buckets + total) | P1 | The Outstanding "Party-wise" tab has no source |
| BE-120 | Dashboards | RESOLVED (2026-09-27) - source route and financial DTO are present | RESOLVED | Runtime 200/403 verification only |
| BE-130 | Reports | `breached` filter on the response-time report | P2 | One report filter works on the current page only |
| BE-140 | Audit logs | RESOLVED (2026-09-27) - protected source route is present | RESOLVED | Runtime 200/403 verification only |
| BE-141 | Audit logs | Before/after summaries, record name, actor name in audit rows | P2 | The Previous/New value and name columns are blank |
| BE-150 | Onboarding | Invite with details + list + resend + revoke + status | P1 | The Invites list and Resend/Revoke have no endpoint; invite details are lost |
| BE-151 | Onboarding | Public "look up invite by token" (prefill + state) | P1 | The registration page can't prefill or show Invalid/Expired/Revoked/Submitted |
| BE-152 | Onboarding | Re-submit after INFO_REQUESTED (same record) | P1 | The "Info requested → edit → resubmit" loop is impossible |
| BE-153 | Onboarding | KYC document upload | P1 | Documents are names only; reviewers can't open a file |
| BE-154 | Onboarding | RESOLVED (2026-09-27) - scoped paginated source list is present | RESOLVED | Runtime 200/403 verification only |
| BE-155 | Onboarding | Approval creates the party **and** the distributor portal login | P1 | An approved distributor can't sign in to the portal |
| BE-160 | Portal | Catalogue/cart GST and pack size (wrong columns) | P1 | The portal cart shows GST 0; cart total ≠ order total |
| BE-161 | Portal | Portal can submit its own order | P1 | Portal orders stay DRAFT until someone in the CRM submits them |
| BE-162 | Portal | Portal invoice detail | P1 | The portal invoice detail page has no endpoint |
| BE-163 | Portal | Portal dispatch detail | P2 | The portal dispatch detail page reuses the list row |
| BE-164 | Portal | Portal payments with allocations | P2 | The portal Outstanding page can't list receipts |
| BE-165 | Portal | Portal active schemes/offers | P2 | The portal Offers page has no source |
| BE-166 | Portal | Profile edit: email, WhatsApp, billing address | P2 | Three editable profile fields can't be saved |
| BE-167 | Portal | Team users (Owner manages; Team User login; Owner vs Team in `/auth/me`) | P1 | My Team and every Team-User screen can't work |
| BE-170 | DCR | Source routes exist; B1 portal `dcr.*` grants are uncommitted/unverified; Admin does not see portal DCRs (FRS H14) | P0 | DCR is not deploy-ready for portal users |
| BE-171 | DCR | Field-customer master; visits reference a field customer | P0 | DCR entry has nothing to select; visits can't be saved as designed |
| BE-172 | DCR | Beats CRUD | P1 | The beats list and beat dropdowns are empty |
| BE-173 | DCR | Tour plan CRUD | P1 | The Tour Plan screen can't save |
| BE-174 | DCR | Missed-DCR compliance (cut-off time per distributor + missed list) | P2 | The Missed DCRs screen and compliance widget are computed client-side only |
| BE-175 | DCR | POB → order conversion (linked to visits) | P1 | "Convert POB to Order" can't create the order or mark visits converted |
| BE-176 | DCR | Reopen an approved DCR (with reason) | P2 | The Reopen action has no endpoint |
| BE-177 | DCR | Visit photo upload and optional location | P2 | The visit photo field is a file name only |

**Totals:** 74 rows including security and resolved markers. Current open fix order starts with 12 P0 production/security/frontend blockers; P1 = 34 and P2 = 26. BE-006 and BE-170 decisions are resolved below.

---

## 3. Coverage checklist

Columns are the operations the frontend performs.
- **SUPPORTED**: works as is (an adapter may rename fields).
- **MISSING**: no endpoint, or it's unusable, with its BE-ID.
- **SHAPE MISMATCH**: an endpoint exists but the frontend can't use it without a backend change, with its BE-ID.
- `n/a`: the frontend doesn't do this operation.

"Status" means activate/deactivate or archive/restore.

| Module | List | Get | Create | Update | Status | Custom actions |
|---|---|---|---|---|---|---|
| Masters: categories, tiers, dosage forms, scheme types | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | n/a |
| Masters: 10 reference lists (lead source, party type, designation, department, 6 DCR masters) | MISSING BE-010 | MISSING BE-010 | MISSING BE-010 | MISSING BE-010 | MISSING BE-010 | n/a |
| Masters: 6 enum-backed (lead status, follow-up type, KYC doc type, payment mode, order/dispatch status) | SHAPE MISMATCH BE-011 | n/a | n/a (server enum) | n/a | n/a | n/a |
| Masters: transporters | SUPPORTED | MISSING BE-014 | SUPPORTED | MISSING BE-014 | MISSING BE-014 | n/a |
| Masters: notification templates, webhook sources | SUPPORTED | n/a | SUPPORTED (webhook) / MISSING BE-015 (template) | MISSING BE-015 | MISSING BE-015 | n/a |
| Geography: state, district | SUPPORTED | n/a | MISSING BE-013 | MISSING BE-013 | MISSING BE-013 | n/a |
| Geography: city, pincode | MISSING BE-012 | SUPPORTED (pincode lookup) | MISSING BE-013 | MISSING BE-013 | MISSING BE-013 | cascade: MISSING BE-012 |
| Roles / permissions | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED (PATCH status) | clone, delete, catalogue, assign/revoke: SUPPORTED · grant post-002 keys: MISSING BE-002 |
| Internal users | SHAPE MISMATCH BE-020 | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | reset password, unlock: SUPPORTED |
| Leads | SHAPE MISMATCH BE-003, BE-031 | SUPPORTED | SHAPE MISMATCH BE-030 | SHAPE MISMATCH BE-030 | MISSING BE-032 | status change: SUPPORTED · assign: SHAPE MISMATCH BE-001 · convert to party: MISSING BE-033 |
| Follow-ups | SHAPE MISMATCH BE-003 | MISSING BE-040 | SHAPE MISMATCH BE-040 | MISSING BE-040 | n/a | complete, reschedule: SUPPORTED · mark missed: MISSING BE-040 · add remark / history: MISSING BE-041 |
| Parties | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | ledger/credit snapshot: SUPPORTED · product interests: SUPPORTED |
| Territory | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | resolve/validate: SUPPORTED · override: SHAPE MISMATCH BE-050 |
| Products | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | delete (with usage check): SUPPORTED |
| Pricing | SUPPORTED | SUPPORTED | SUPPORTED | n/a (append-only) | SUPPORTED | resolve: SHAPE MISMATCH BE-060 · override with reason: SUPPORTED |
| Schemes | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED | calculate free qty: SHAPE MISMATCH BE-061 |
| Orders | SUPPORTED | SUPPORTED | SUPPORTED | SUPPORTED (draft) | n/a | submit: SUPPORTED · confirm: MISSING BE-002 (key) · cancel, delete draft: SUPPORTED · advance to Packed/Dispatched: MISSING BE-070 · tax split: MISSING BE-071 |
| Inventory / batches | SUPPORTED | SUPPORTED | SUPPORTED (receive) | MISSING BE-081 | MISSING BE-081 | adjust: SUPPORTED · transfer: MISSING BE-082 · movement ledger: MISSING BE-080 · reservations view: SUPPORTED |
| Near-expiry | SHAPE MISMATCH BE-085 | n/a | n/a | n/a | n/a | configure thresholds: MISSING BE-086 |
| Billing / invoice | SUPPORTED | SUPPORTED | SHAPE MISMATCH BE-090, BE-091 | n/a | n/a | cancel: SUPPORTED · by-order: SUPPORTED |
| Dispatch | SUPPORTED | SUPPORTED | MISSING BE-070 (always fails) | MISSING BE-100 | MISSING BE-100 | deliver: MISSING BE-002 (key) · pending list: MISSING BE-101 |
| Payments / outstanding | SUPPORTED | SHAPE MISMATCH BE-111 | SHAPE MISMATCH BE-110 | MISSING BE-110 | MISSING BE-110 | allocate: SUPPORTED · reverse: MISSING BE-002 (key) · outstanding: SHAPE MISMATCH BE-112 · party-wise summary: MISSING BE-113 |
| PDC | SUPPORTED | SUPPORTED | SUPPORTED | n/a | n/a | realize, bounce, cancel: MISSING BE-002 (key) |
| Dashboards | SUPPORTED (RESOLVED 2026-09-27) | n/a | n/a | n/a | n/a | authoritative financial widget via OutstandingService |
| Reports (16) | SUPPORTED (15 analytics keys + payment-outstanding) | n/a | n/a | n/a | n/a | filters: SHAPE MISMATCH BE-130 · CSV: client-side |
| Audit logs | SUPPORTED (RESOLVED 2026-09-27) | n/a | n/a | n/a | n/a | value summaries: SHAPE MISMATCH BE-141 |
| Onboarding: invites | MISSING BE-150 | MISSING BE-151 (public) | SHAPE MISMATCH BE-150 | n/a | MISSING BE-150 (revoke) | resend: MISSING BE-150 |
| Onboarding: registrations | SUPPORTED (RESOLVED 2026-09-27) | SUPPORTED | SUPPORTED (public register) | MISSING BE-152 (resubmit) | n/a | approve/reject/request-info: SUPPORTED · document verify: MISSING BE-002 (key) · upload: MISSING BE-153 · approve → party + portal login: SHAPE MISMATCH BE-155 |
| Portal: profile | n/a | SUPPORTED | n/a | SHAPE MISMATCH BE-166 | n/a | n/a |
| Portal: catalogue / cart | SHAPE MISMATCH BE-160 | n/a | n/a | n/a | n/a | cart calculate: SHAPE MISMATCH BE-160 |
| Portal: orders | SUPPORTED | SUPPORTED | SUPPORTED | n/a | n/a | cancel: SUPPORTED · submit: MISSING BE-161 |
| Portal: invoices / dispatches | SUPPORTED | MISSING BE-162 / BE-163 | n/a | n/a | n/a | n/a |
| Portal: outstanding / payments | SUPPORTED (summary) | n/a | n/a | n/a | n/a | receipts list: MISSING BE-164 |
| Portal: schemes / offers | MISSING BE-165 | n/a | n/a | n/a | n/a | n/a |
| Portal: my team | MISSING BE-167 | MISSING BE-167 | MISSING BE-167 | MISSING BE-167 | MISSING BE-167 | team-user login: MISSING BE-167 |
| DCR: entries + approval | MISSING BE-170 (portal grants unverified) | SUPPORTED | SHAPE MISMATCH BE-171 | SUPPORTED | n/a | submit/approve/reject: SUPPORTED · reopen: MISSING BE-176 · photo: MISSING BE-177 |
| DCR: field customers | MISSING BE-171 | MISSING BE-171 | MISSING BE-171 | MISSING BE-171 | MISSING BE-171 | n/a |
| DCR: beats | MISSING BE-172 | n/a | MISSING BE-172 | MISSING BE-172 | MISSING BE-172 | n/a |
| DCR: tour plan | MISSING BE-173 | n/a | MISSING BE-173 | MISSING BE-173 | n/a | n/a |
| DCR: compliance / POB | MISSING BE-174 | n/a | n/a | n/a | n/a | POB → order: MISSING BE-175 |
| Settings | SUPPORTED (brand) | n/a | n/a | MISSING BE-086 | n/a | n/a |
| Notifications / WhatsApp | SUPPORTED (+ whatsapp-delivery report) | n/a | n/a | n/a | n/a | read, read-all: SUPPORTED |
| Webhooks | SUPPORTED (sources + webhook-failures report) | n/a | SUPPORTED | MISSING BE-015 | MISSING BE-015 | n/a |

**Grid totals (43 rows × 6 columns = 258 cells, each counted once; a cell containing any MISSING counts as
MISSING):** SUPPORTED 79 · MISSING 76 · SHAPE MISMATCH 21 · n/a 82. There are no empty cells.

---

## 4. Module detail

Every item follows the same layout.
- **Screen/flow:** what breaks on the frontend.
- **Now:** what the backend does, with file references.
- **Needed:** the exact contract.
- **Verify:** acceptance steps, with the 200 and 403 cases.

Unless an item says otherwise:
- responses use the standard envelope;
- validation failures are `422 VALIDATION_FAILED` with `fields`;
- a missing permission is `403 FORBIDDEN_PERMISSION`;
- an out-of-scope record is `404 RECORD_NOT_FOUND`.

### 4.0 Cross-cutting

**BE-001: Authorize by permission key, not by role name (P0)**
- **Screen/flow:** Roles & Permissions screen. Any user with a custom role:
  - gets 403 on Products, Pricing, Schemes and every Masters tab, even when granted `products.*` etc.;
  - sees only their own leads/follow-ups regardless of the role's scope;
  - can use lead assign only if their legacy role is FRANCHISE_ADMIN.
- **Now:**
  - Legacy `isAdmin()` gates run before the permission checks: `Admin/ProductsController.php:24`,
    `PricesController.php:28`, `SchemesController.php:27`, `MastersController.php:33`,
    `CatalogMastersController.php:22`, and `SettingsController.php:20` (which has no permission check at all).
  - Leads/follow-ups use `role === 'SALES'`: `LeadsController.php:30,74,123`, `FollowUpsController.php:29,53`,
    `Policies/SalesLeadPolicy.php`, `SalesFollowUpPolicy.php`.
  - Portal uses `role === 'DISTRIBUTOR'`: `Portal/PortalController.php:35`.
  - Lead round-robin picks `role='SALES'` users: `Domain/Leads/LeadAssignmentService.php:18`.
  - Transporters list/create, settings list and templates list check no key at all.
- **Needed:**
  - Every route checks the key in §4 of `docs/api-inventory`. The keys the frontend uses:
    - `masters.{view,create,edit,activateDeactivate}`, `products.*`, `pricing.*`, `schemes.*`;
    - `leads.{view,create,edit,assign,archive,convert}`;
    - `followUps.{view,create,edit,complete,reschedule}`;
    - `settings.{view,edit}` (new), `portal.{view,placeOrder,editProfile}` (new).
  - Leads/follow-ups data scope comes from the user's `leads`/`followUps` scope (ALL/TERRITORY/TEAM/OWN/NONE), the
    same as orders/parties already do.
  - `users.role` only chooses the login surface.
  - Super Admin keeps its bypass.
  - Existing Admin/Sales/Distributor users keep exactly their current reach (seed their roles; see BE-002).
- **Verify:**
  1. Create role "Dispatch Team" with `dispatch.view`, `masters.view`, `leads.view` (scope OWN). Assign it to a new
     user; log in via `crm-sales`.
  2. As that user: `GET /admin/categories` → 200; `POST /admin/categories` → 403; `GET /admin/leads` → 200 (own
     leads only); `GET /admin/orders` → 403.
  3. Regression:
     - ADMIN `GET /admin/products`, `PATCH /admin/settings` → 200;
     - SALES `GET /admin/leads` → own only, `GET /admin/categories` → 403;
     - PORTAL `GET /portal/profile` → 200, `GET /admin/leads` → 403;
     - SUPER `GET /super/dashboard/stats` → 200.
- Draft implementation: see §9.

**BE-003: One pagination shape (P1)**
- **Screen/flow:** Leads list and Follow-ups list (and every place that reuses the shared list helper).
- **Now:** `GET /admin/leads` and `/admin/follow-ups` return `data: {items, total, page, per_page, total_pages}` with
  only `request_id` in `meta` (`LeadsController::index`, `FollowUpsController::index`). Every other list returns
  `data: []` plus pagination in `meta`.
- **Needed:** `data: Lead[]`, `meta: {request_id, page, per_page, total, total_pages}`, the same as `/admin/orders`.
- **Verify:** `GET /admin/leads?per_page=2` as ADMIN returns `Array.isArray(data) === true` and `meta.total` ≥ 2.
  The same for `/admin/follow-ups`.

**BE-004: CORS (P0 production blocker; integration blocker nahi)**
- **Screen/flow:** every screen in production (dev works via the Vite proxy). This is a production blocker, integration blocker nahi.
- **Now:**
  - `OPTIONS` on any route → `405 METHOD_NOT_ALLOWED`.
  - No `Access-Control-Allow-Origin` on any response.
  - `Access-Control-Allow-Headers` (set by the server config) = `Content-Type, Authorization, X-Requested-With`.
  - `app/Http/Middleware/CorsMiddleware.php` exists but isn't registered in `bootstrap/middleware.php`, and uses APIs
    `Response` doesn't have.
- **Needed:**
  - `OPTIONS` → 204.
  - `Access-Control-Allow-Origin: <configured SPA origin>`.
  - `Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS`.
  - `Access-Control-Allow-Headers: Content-Type, Authorization, Idempotency-Key, X-Surface`.
  - `Access-Control-Expose-Headers: Idempotent-Replay`.
  - Origins come from config (e.g. `CORS_ALLOWED_ORIGINS`).
- **Verify:**
  `curl -i -X OPTIONS https://<api>/api/v1/oauth/token -H "Origin: https://<spa>" -H "Access-Control-Request-Method: POST" -H "Access-Control-Request-Headers: content-type,idempotency-key"`
  → 204 with the four headers. A disallowed origin → no `Allow-Origin` header.

**BE-005: Refresh preserves the token audience (P2)**
- **Screen/flow:** portal and sales sessions after a refresh. The frontend can't send `X-Surface`: with it, a
  refreshed portal token is rejected.
- **Now:** `AuthController::handleRefreshTokenGrant` takes the surface from `X-Surface`, defaulting to `admin`, so
  every refreshed token gets `aud: admin` (seen live). `/api/v1/*` only checks `aud` when `X-Surface` is present.
- **Needed:** the refreshed access token keeps the `aud`/client of the session being refreshed (store the surface on
  `user_sessions`).
- **Verify:** log in with `crm-portal`, refresh without any header, decode the new JWT → `aud: "portal"`. Then
  `GET /portal/profile` with `X-Surface: portal` → 200.

**BE-006: Admin all-franchise access with optional filter (P2)**
- **Screen/flow:** an Admin responsible for several locations can see all franchises and optionally filter by one.
- **Decision:** D-7 is resolved: Admin ko SAB franchises ka access; optional franchise filter; dobara login karke switch nahi.
- **Now:** `franchise_code` is required at login. Every query is `franchise_ref = token franchise`. No list takes a
  franchise filter. Super Admin can impersonate.
- **Needed:** Admin tokens can access all franchises in their organization; list endpoints accept optional `franchise_ref` filter; `/auth/me` exposes the franchises/filter options the user may select. Re-login switching is not the design.
- **Verify:** Admin signs in once, sees all-franchise totals by default, filters to one franchise via query param, and cannot query outside their organization.

**BE-007: OpenAPI completeness (P1)**
- **Screen/flow:** type generation for every module (`openapi-typescript` → `src/api/schema.d.ts`).
- **Now:** `public/api-docs/openapi.yaml` (2427 lines):
  - 39 routes aren't documented (list in `pharma-sales-crm/docs/api-contract-gaps.md` §1.1);
  - only 10 of 73 success responses have a schema;
  - 24 analytics schemas (`DashboardResponse`, `ReportResponse`, `*Row`) sit under the top-level `security:` key
    instead of `components.schemas`, so the file is invalid for generators;
  - `ReportResponse.rows.oneOf` uses flow-style `$ref` pairs.
- **Needed:**
  - every route in `bootstrap/routes.php` is documented, with request body and response schema (the fields in §4);
  - the analytics block is moved under `components.schemas`.
- **Verify:** `npx openapi-typescript public/api-docs/openapi.yaml -o /tmp/schema.d.ts` exits 0. A route-vs-spec diff
  shows 0 undocumented routes. Every `2xx` has `content.application/json.schema`.

**BE-008: `fields` is always field → messages (P2)**
- **Screen/flow:** the login form (and any form) maps `error.fields` into field errors.
- **Now:** `TooManyRequestsException` → `fields: {retry_after: 42}` (a scalar). The router 404 has
  `request_id: ""`.
- **Needed:** non-field data goes in `error.details` (or a `Retry-After` header); `fields` is only
  `{name: string[]}`. Every error carries a request id.
- **Verify:** six wrong logins for one email → 429 with no scalar under `fields`. `GET /api/v1/nope` → 404 with a
  non-empty `request_id`.

### 4.1 Masters (+ geography)

**BE-010: Catalog-master values for 10 reference masters (P1)**
- **Screen/flow:**
  - Masters tabs: Lead Source, Party Type, Designation, Department, Field Customer Type, Doctor Specialty, Beat/Area,
    Visit Purpose, Sample/Gift Item, Customer Category.
  - The dropdowns that read them: Lead form (source), Party form (type), User form (designation, department), DCR
    screens.
- **Now:** `Admin/CatalogMastersController.php:14` accepts only `dosageForms`, `schemeTypes`; anything else →
  `404 MASTER_NOT_FOUND`.
- **Needed:**
  - Extend the same endpoints (`GET/POST /admin/catalog-masters/{key}`, `GET/PATCH /admin/catalog-masters/{key}/{ref}`,
    `POST …/{ref}/status {status}`) to the keys `leadSources, partyTypes, designations, departments,
    fieldCustomerTypes, doctorSpecialties, beatAreas, visitPurposes, sampleGiftItems, customerCategories`.
  - Same row shape: `{master_ref, master_key, name, description|null, status}`.
  - Keys: `masters.*` for the admin tabs. For the DCR masters, the list must also be readable by portal users (e.g.
    with `dcr.view`).
- **Verify:** ADMIN `POST /admin/catalog-masters/leadSources {name:"Trade Fair"}` → 201. List → contains it. SALES
  `POST` → 403.

**BE-011: Server enum values for enum-backed masters (P2)**
- **Screen/flow:** Masters tabs Lead Status, Follow-up Type, KYC Document Type, Payment Mode, Order Status, Dispatch
  Status (and their dropdowns). In the backend these are fixed DB enums, so they can't be editable masters.
- **Needed:** `GET /meta/enums` → `{lead_status: string[], follow_up_activity_type: string[], kyc_document_type:
  string[], payment_mode: string[], order_status: string[], dispatch_status: string[]}` (any authenticated user).
  The frontend shows those tabs read-only.
- **Verify:** as SALES → 200; the values equal the DB enums.

**BE-012: City and pincode lists (P1)**
- **Screen/flow:**
  - `CascadingLocationSelect` (State → District → City → Pincode), used by the Masters City/Pincode tabs, Lead form,
    Party form, Territory allocation dialog, Onboarding invite, and the public registration wizard.
  - With only states and districts, the cascade can't offer cities or pincodes.
- **Now:** `GET /geo/states`, `GET /geo/districts?state_ref=`, and `GET /geo/pincodes/{pin}` (single lookup) only.
  `GeoController.php`.
- **Needed (public or authenticated, unpaginated is fine):**
  - `GET /geo/cities?district_ref=` → `[{city_ref, district_ref, city_name}]`;
  - `GET /geo/pincodes?city_ref=|district_ref=` → `[{pincode, city_ref, district_ref, state_ref}]`.
- **Verify:** `GET /geo/cities?district_ref=DST-MUMBAICITY000001` → contains Mumbai.
  `GET /geo/pincodes?district_ref=DST-MUMBAICITY000001` → contains `400001`.

**BE-013: Geography maintenance (P2, DECISION)**
- **Screen/flow:** the Masters geography tabs offer Add/Edit/Activate for State/District/City/Pincode. Live they're
  read-only.
- **Now:** geography tables are platform-wide with no franchise, no status column and no write endpoints.
- **Needed:** decide whether franchises may edit geography. If yes: create/update/status per level (with a `status`
  column) under a new key or `masters.*`. If no: the frontend keeps them read-only (no backend work).

**BE-014: Transporter update/status/contact (P2)**
- **Screen/flow:** Masters → Transporter edit and toggle (hidden in live mode). The Contact person and Phone columns
  are blank.
- **Now:** `MastersController` has list + create only; the table has no contact columns.
- **Needed:**
  - `GET/PATCH /admin/transporters/{ref}` with `{transporter_name?, tracking_url_template?, contact_person?, phone?}`;
  - `POST /admin/transporters/{ref}/status {status}`;
  - keys `masters.edit` / `masters.activateDeactivate`.
- **Verify:** ADMIN PATCH → 200 with the new values; status INACTIVE → the row shows it; SALES → 403.

**BE-015: Notification templates and webhook sources as editable masters (P2)**
- **Screen/flow:** Masters → Notification Template / Webhook Source (name + description, edit, activate).
- **Now:** `GET /admin/notification-templates` (list only), `GET/POST /admin/webhook-sources` (no update/status).
- **Needed:**
  - templates: `POST`, `PATCH /{ref}`, `POST /{ref}/status`, key `notifications.manageTemplates`;
  - webhook sources: `PATCH /{ref}`, `POST /{ref}/status`, key `webhooks.configure` (see also S-2).
- **Verify:** ADMIN create/edit/toggle each → 200/201; SALES → 403.

### 4.2 Roles / permissions

**BE-002: Grant the permission keys added after migration 002 (P0)**
- **Screen/flow:** Order detail → Confirm; Dispatch → Deliver; PDC realize/bounce/cancel; Payment → Reverse;
  Onboarding → Convert/Verify KYC. As Admin, all of these return 403. The Roles screen also can't grant them to
  custom roles.
- **Now:**
  - Migration 002 grants Admin every key in `auth_permissions` at that moment (`CROSS JOIN`). Later migrations
    (005–011) insert new keys only into `auth_permission_catalogue`, never into `auth_permissions`, and grant nothing.
  - Live `/auth/me` for Admin is missing `orders.confirm`, `payments.pdc`, `payments.reverse`, `inventory.reserve`,
    `inventory.release`, `billing.outstanding`, `distributorOnboarding.create/edit/convert`, `kyc.*`, `dcr.*`.
  - `dispatch.confirm` (needed by `POST /admin/dispatches/{ref}/deliver`) and `billing.generate` aren't in the live
    catalogue at all.
- **Needed:**
  - every catalogue key also exists in `auth_permissions`;
  - the protected Admin role holds every key;
  - `dispatch.confirm` exists;
  - portal users get `dcr.*` per BE-170.
- **Verify:** ADMIN `/auth/me.permissions.orders` contains `confirm`. ADMIN `POST /admin/orders/{ref}/confirm` on a
  SUBMITTED order → not 403. `PATCH /admin/roles/{custom}` granting `orders.confirm` → 200.

Everything else in this module (role list/get/create/update/clone/delete, catalogue, assign/revoke, scope overrides,
escalation guards) is SUPPORTED.

### 4.3 Internal users

**BE-020: Role on user list rows (P2)**
- **Screen/flow:** Internal Users list, Role column.
- **Now:** `GET /admin/users` returns `users.*` (legacy `role` only). `GET /admin/users/{ref}` adds `roles[]`
  (`UsersController::show`).
- **Needed:** each list row gets `roles: [{role_ref, role_name}]`.
- **Verify:** ADMIN `GET /admin/users` → every row has `roles`.

### 4.4 Leads

**BE-030: Lead fields (P1)**
- **Screen/flow:** the Lead form's WhatsApp, Address and Expected Value fields; Lead details shows them.
- **Now:** the `leads` table has no `whatsapp`, `address` or `expected_value` columns. Unknown keys sent to
  `POST/PATCH /admin/leads` are ignored.
- **Needed:** `whatsapp` (mobile format, optional), `address` (TEXT, optional), `expected_value` (decimal ≥ 0,
  optional), accepted on create/update and returned on list/get.
- **Verify:** POST with all three → GET returns them. Invalid WhatsApp → 422 `fields.whatsapp`.

**BE-031: Lead list filters (P1)**
- **Screen/flow:** Leads filter panel (status, priority, source, assigned to, city, created date range, territory).
- **Now:** `LeadsController::index` passes only `status`, `search`.
- **Needed:** query params `priority`, `lead_source`, `assigned_user_ref`, `district_ref`, `city_ref`, `date_from`,
  `date_to` (created_at), `sort_by ∈ {created_at, next_follow_up_at, priority}`, `sort_dir`.
- **Verify:** ADMIN `?priority=HIGH` → only HIGH rows, and `meta.total` matches.

**BE-032: Archive/restore (P1)**
- **Screen/flow:** Leads list/detail Archive and Restore buttons (`leads.archive`).
- **Now:** `ARCHIVED` is reachable only from `LOST`/`REJECTED` (`LeadStateMachine.php:17-18`), and there's no way
  back.
- **Needed:**
  - `POST /admin/leads/{ref}/archive {reason?}` from any non-CONVERTED status;
  - `POST /admin/leads/{ref}/restore` → the previous status;
  - key `leads.archive`.
- **Verify:** archive a NEW lead → 200 ARCHIVED; restore → NEW; SALES (no `archive`) → 403.

**BE-033: Convert lead → party (P1)**
- **Screen/flow:** Lead details → "Convert to Party". It opens the party form prefilled; on save the lead becomes
  Converted and links to the party.
- **Now:**
  - `POST /admin/parties` doesn't accept `converted_from_lead_ref` (`PartiesController::validateParty`).
  - The status transition to CONVERTED requires QUALIFIED and doesn't link a party.
  - Only onboarding's `convert` links leads.
- **Needed:** `POST /admin/leads/{ref}/convert` with the party create body. In one transaction it:
  - creates the party with `converted_from_lead_ref`;
  - sets lead `CONVERTED` + `converted_party_ref`.

  It returns `{lead_ref, party_ref}`. Key `leads.convert` (+ `parties.create`). **DECISION:** from which lead statuses
  conversion is allowed (the frontend allows it from any open status).
- **Verify:** convert → party exists with the link, lead is CONVERTED; converting again → 409.

### 4.5 Follow-ups

**BE-040: Follow-up detail, edit, missed (P1)**
- **Screen/flow:** the Follow-ups list/calendar and the Lead detail panel: open a follow-up, edit discussion /
  customer response / next action, "Mark Missed" with a reason.
- **Now:** only list, create, complete, reschedule. The `follow_ups` table has `next_action`, `remark`; no
  `discussion`, `customer_response`, `missed_reason`.
- **Needed:**
  - `GET /admin/follow-ups/{ref}`;
  - `PATCH /admin/follow-ups/{ref}` (`discussion`, `customer_response`, `next_action`, `remark`, `activity_type`);
  - `POST /admin/follow-ups/{ref}/missed {reason?}` → status MISSED.

  Create also accepts `discussion`, `customer_response`. Keys `followUps.view/edit`.
- **Verify:** SALES on own follow-up → 200; on another rep's → 404 (OWN scope); a SALES user without `edit` → 403.

**BE-041: Remark and reschedule history (P1)**
- **Screen/flow:** the "Add remark" dialog and the remark timeline; reschedule history on the follow-up.
- **Now:** no history tables; reschedule overwrites `next_follow_up_at`.
- **Needed:**
  - `POST /admin/follow-ups/{ref}/remarks {remark, discussion?, customer_response?, next_action?,
    next_follow_up_at?}` (append-only);
  - `GET /admin/follow-ups/{ref}` includes `remarks[] {remark_ref, remark, discussion, customer_response,
    next_action, next_follow_up_at, created_by, created_at}` and `reschedules[] {previous_at, new_at, reason,
    rescheduled_by, rescheduled_at}`.
- **Verify:** add two remarks and reschedule once → GET shows 2 remarks and 1 reschedule entry in order.

### 4.6 Parties

All operations are SUPPORTED: list/filters, get with credit snapshot + territories + product interests,
create/update, status, archive/restore, ledger. The cut-off time for DCR compliance is in BE-174. Scope issue on
restore: S-5.

### 4.7 Territory

**BE-050: Override must unblock the order; party-level override (P1)**
- **Screen/flow:** Order form → violation dialog → "Override with reason" → submit. Also Party → Territory tab →
  "Request override".
- **Now:**
  - `POST /admin/territories/override` records a row, but it requires an `order_ref`.
  - `OrderService::submitOrder/confirmOrder` re-resolve the territory and throw `TERRITORY_CONFLICT` /
    `TERRITORY_POLICY_PENDING` without reading overrides.
- **Needed:**
  - Submit/confirm accept an active override for (party, pincode), or for the order, and set
    `orders.territory_status = OVERRIDDEN`.
  - Override creation without `order_ref` (party + pincode or district + reason) for the Party screen.
  - Key `territory.override`.
- **Verify:** an order for a party whose pincode is CONFLICT: submit → 422; create the override → submit → 200 with
  `territory_status: OVERRIDDEN`. SALES (no `override`) → 403.

Unassigned-pincode policy: see §6.

### 4.8 Products

All SUPPORTED once BE-001 lands. Adapter notes: `shelf_life_days` (backend) ↔ `shelfLifeMonths` (frontend);
`pts` ↔ `ptr`; `franchise_rate` ↔ `netRate`.

### 4.9 Pricing

**BE-060: PTS as the last fallback (P2)**
- **Screen/flow:** Order form / portal catalogue rate for a product with no party/tier rate and no franchise rate.
- **Now:** `PriceResolver::resolve` does party → tier → `products.franchise_rate` → throws `PRICE_NOT_FOUND`.
- **Needed:** after `franchise_rate`, fall back to `products.pts` with `rate_source: "PTS"` (see §6).
- **Verify:** a product with franchise_rate 0 and pts 40 → `POST /admin/pricing/resolve` → `{rate: 40, rate_source:
  "PTS"}`.

### 4.10 Schemes

**BE-061: Free-quantity rule (P1, DECISION)**
- **Screen/flow:** the scheme list badge ("10+1 Free"), scheme form preview, order line free qty, portal cart free qty.
- **Now:** `SchemeCalculator::calculate` gives `floor(qty/min_qty) × free_qty` (repeating). Without stacking, the
  rule with the **highest free qty** wins (not the highest priority). A scheme is header + `rules[]` per product.
- **Frontend:** one flat `freeQty` when `minQty ≤ qty ≤ maxQty`; the highest **priority** scheme wins; a scheme has
  one min/max/free for all its products.
- **Needed:** a decision on (1) repeating vs flat and (2) priority vs max-free. `POST /admin/schemes/calculate` must
  return `{paid_qty, free_qty, scheme_ref, scheme_name}` under that rule. The header/rules shape is fine; the frontend
  adapter maps one rule per product.
- **Verify:** scheme min 10 / free 1 and order qty 25 → the decided result (2 or 1).

### 4.11 Orders

**BE-070: Reachable fulfilment path (P0)**
- **Screen/flow:** Order detail status stepper (Draft → Confirmed → Billed → Packed → Dispatched → Delivered) and
  Dispatch creation.
- **Now:**
  - `OrderStateMachine`: CONFIRMED → {PROCESSING, CANCELLED}, PROCESSING → {DISPATCHED, …}. Nothing sets PROCESSING.
  - `DispatchService::create` asserts CONFIRMED → DISPATCHED, so **every dispatch create fails** with
    `INVALID_ORDER_TRANSITION`.
  - Generating an invoice doesn't change the order status.
- **Needed:**
  - Either allow CONFIRMED → DISPATCHED at dispatch time, or add `POST /admin/orders/{ref}/pack` (→ PROCESSING,
    shown as "Packed") and make dispatch require PROCESSING.
  - The order detail must show "Billed" = has a POSTED invoice (`invoice_ref` on the order, or `billed: true`).
  - Keys: `orders.submit` (submit), `orders.confirm` (confirm), `dispatch.edit` (pack), `dispatch.create`.
- **Verify:** SUBMITTED → confirm → generate invoice → (pack) → create dispatch → deliver: each call 200, and the
  order status follows. SALES on confirm → 403.

**BE-071: Order-level and line-level GST split (P1)**
- **Screen/flow:** Order form footer and Order detail (Subtotal, CGST, SGST or IGST, Net), line tax column; the
  portal cart.
- **Now:**
  - `orders`/`order_items` store only `gst_total` / `gst_percent`, `line_total`.
  - The split exists only on invoices, computed in `BillingService` with the supplier state (`franchises.state_ref`)
    vs the state of `shipping_pincode`.
- **Needed:** order create/update/get and `POST /portal/cart/calculate` return:
  - per line: `taxable_amount, cgst_amount, sgst_amount, igst_amount, total_tax, line_total`;
  - totals: `taxable_total, cgst_total, sgst_total, igst_total, gst_total, grand_total, tax_policy_code`;
  - the same rule as the invoice (§6).
- **Verify:** a Maharashtra franchise + a 400001 shipping pincode → only CGST/SGST; a Delhi pincode → only IGST; the
  order totals equal the later invoice totals.

**BE-072: FEFO minimum shelf life (P1)**
- **Screen/flow:** Order Confirm.
- **Now:** `OrderService::confirmOrder` passes the product's `shelf_life_days` (its total shelf life) as the minimum
  **remaining** life (`getSaleableBatches(min_shelf_days)` → `expiry_date >= today + shelf_life_days`). A product with
  a 730-day shelf life can never be confirmed.
- **Needed:** use a separate "minimum remaining shelf life" setting (0 if not configured).
- **Verify:** a product with shelf_life_days 730 and a saleable batch expiring in 200 days → confirm → 200 with that
  batch reserved.

**BE-073: Zero credit limit (P1, DECISION)**
- **Screen/flow:** Order Confirm for a party whose credit limit is empty/0.
- **Now:** `PartyCreditService` → `credit_breached = projected > 0` when the limit is 0, so confirm throws
  `CREDIT_LIMIT_EXCEEDED`. (The legacy `CreditRuleService` treats 0 as "no limit".)
- **Needed:** decide what 0 means. If it means "no limit", skip the check.
- **Verify:** a party with credit_limit 0 → confirm → 200 (if "no limit").

**BE-074: Line discount (P2, DECISION)**
- **Screen/flow:** Order form Discount column.
- **Now:** `OrderService` always stores `discount = 0` and ignores any input.
- **Needed:** decide whether line discounts are allowed (and who may give them). If yes, accept
  `items[].discount` (amount ≥ 0, ≤ line gross), gated by `pricing.priceOverride`.

**BE-075: Territory filter on orders (P2)**
- **Screen/flow:** Dashboard "Territory violation attempts" → the Orders list pre-filtered.
- **Needed:** `GET /admin/orders?territory_status=OVERRIDDEN|CONFLICT|UNASSIGNED`.
- **Verify:** only matching rows are returned.

### 4.12 Inventory / batches

**BE-080: Movement ledger (P1)**
- **Screen/flow:** Inventory → Movement Ledger (all movements, filter by type/product/date).
- **Now:** movements are only returned inside `GET /admin/inventory/batches/{ref}`.
- **Needed:** `GET /admin/inventory/movements?type=&product_ref=&batch_ref=&date_from=&date_to=&page=&per_page=` →
  `[{movement_ref, batch_ref, batch_no, product_ref, product_name, movement_type, qty, location_code,
  reference_type, reference_ref, remarks, created_by, created_at}]`. Key `inventory.view`.
- **Verify:** after receive + adjust → 2 rows with the right types.

**BE-081: Batch update (P2)**
- **Screen/flow:** Batch edit form (location, status, damaged qty).
- **Needed:** `PATCH /admin/inventory/batches/{ref}` `{location_code?, status? (SALEABLE|QUARANTINE|RECALLED|DAMAGED),
  damaged_qty?}`, writing a movement row. Key `inventory.edit`.

**BE-082: Stock transfer (P2)**
- **Screen/flow:** Transfer dialog (quantity + destination location + reason).
- **Needed:** `POST /admin/inventory/batches/{ref}/transfer {to_location_code, qty, reason}` → moves the quantity
  (splitting the batch if partial) and records TRANSFER movements. Key `inventory.transfer`.

### 4.13 Near-expiry

**BE-085: Near-expiry rows with value (P2)**
- **Screen/flow:** the Near-Expiry tabs (bucketed by thresholds) + the Expired tab + stock value column.
- **Now:** `GET /admin/inventory/near-expiry?days=` returns raw batch rows (no value, no days-left) and excludes
  expired stock. The value calculation exists only in the analytics near-expiry report.
- **Needed:** add `days_left`, `available_qty` (on_hand − reserved), `stock_value` (= available_qty × MRP) to each
  row; `GET /admin/inventory/expired` (or `?include_expired=1`). Key `nearExpiry.view`.
- **Verify:** a batch with 10 available and MRP 45 → `stock_value: 450`.

**BE-086: Persist near-expiry thresholds (P2)**
- **Screen/flow:** Settings → Near-Expiry Thresholds (four day values).
- **Now:** `PATCH /admin/settings` accepts only `brand_accent_hex`.
- **Needed:** `GET/PATCH /admin/settings` with `near_expiry_thresholds: int[4]` (descending, 1–3650). Key
  `nearExpiry.configure`.
- **Verify:** PATCH `[180, 90, 60, 30]` → GET returns it; SALES → 403.

### 4.14 Billing / invoice

**BE-090: Free goods are not billed (P0)**
- **Screen/flow:** Invoice detail and every total downstream (outstanding, payments, reports).
- **Now:**
  - `confirmOrder` reserves `paid_qty + free_qty`.
  - `BillingService::generateInvoice` sets `taxable = rate × reserved_qty` and hard-codes `free_qty = 0` on the line,
    so free goods are charged and the invoice ≠ the order total.
- **Needed:** each invoice line has `paid_qty` and `free_qty` separately, and taxable = rate × **paid** qty only. Free
  qty is shown with amount 0 (batch-split lines keep the paid/free split proportionally or as separate zero-value
  lines).
- **Verify:** an order with paid 20 + free 2 at rate 10 → invoice taxable 200 (not 220), a line showing free 2,
  `grand_total` = the order's `grand_total`.

**BE-091: Franchise supplier state (P0)**
- **Screen/flow:** Generate Invoice (every invoice).
- **Now:** `BillingService` requires `franchises.state_ref`. Live MUMBAI has `null`, and neither
  `POST/PATCH /super/franchises` accepts `state_ref`, so it can't be set through the API.
- **Needed:** accept `state_ref` (validated against `states`) on franchise create/update (Super). Set it for MUMBAI in
  the seed.
- **Verify:** SUPER `PATCH /super/franchises/{ref} {state_ref}` → 200; ADMIN generate invoice for a CONFIRMED order
  with reservations → 201 with `tax_policy_code`.

The invoice list/get/by-order/cancel and the tax snapshot fields are SUPPORTED.

### 4.15 Dispatch

**BE-100: Dispatch update (P1)**
- **Screen/flow:** the Dispatch form in edit mode (status Packed/In Transit, LR number, tracking URL, transporter,
  boxes, remarks). Keys `dispatch.updateTracking` / `dispatch.edit`.
- **Now:** only create + deliver. Code only ever writes DISPATCHED and DELIVERED.
- **Needed:**
  - `PATCH /admin/dispatches/{ref}` `{lr_number?, tracking_url?, transporter_ref?, boxes?, remarks?}`;
  - `POST /admin/dispatches/{ref}/status {status: IN_TRANSIT|FAILED|RETURNED, reason?}` (writes
    `dispatch_status_history`);
  - `GET /admin/dispatches/{ref}` includes `history[]`.
- **Verify:** PATCH LR → 200; status IN_TRANSIT → 200 and deliver still works after it; SALES → 403.

**BE-101: Pending dispatch list (P2)**
- **Screen/flow:** Dispatch Pending (billed orders not yet dispatched).
- **Workaround:** the frontend can use `GET /admin/analytics/reports/dispatch-pending`.
- **Needed:** `GET /admin/invoices?dispatched=0`.

### 4.16 Payments / outstanding

**BE-110: Payment fields, edit, cancel (P1)**
- **Screen/flow:** Payment form (cheque number, cheque date, bank), Payment detail Edit / Cancel.
- **Now:** the `payments` table has `bank_name, cheque_number, cheque_date, pdc_due_date`, but
  `PaymentsController::store` never reads them. There's no edit or cancel endpoint.
- **Needed:**
  - create accepts `bank_name, cheque_number, cheque_date`;
  - `PATCH /admin/payments/{ref}` while no allocation exists (`payments.edit`);
  - `POST /admin/payments/{ref}/cancel {reason}` while unallocated (`payments.cancel`); once allocated → use
    reverse.
- **Verify:** create a cheque payment → GET returns the cheque fields; PATCH after allocation → 409.

**BE-111: Allocation history (P1)**
- **Screen/flow:** Payment detail → Allocations table; invoice "collected" and balance.
- **Now:** `GET /admin/payments/{ref}` returns the payment row only.
- **Needed:** `allocations[] {allocation_ref, invoice_ref, invoice_no, allocated_amount, status, created_by,
  created_at, reversed_at?, reversal_reason?}` in payment detail. `GET /admin/invoices/{ref}` includes the same list
  for that invoice.
- **Verify:** allocate 500 to INV-1 → both details show the row; reverse → the row shows REVERSED.

**BE-112: Ageing buckets (P1)**
- **Screen/flow:** Outstanding tabs, bucket chart, dashboard outstanding widget, Payment Outstanding report.
- **Now:** `OutstandingService` gives `NOT_DUE, 0-30, 31-60, 61-90, 91-120, 120+, UNCLASSIFIED`.
- **Needed:** `NOT_DUE, 0-30, 31-60, 61-90, 90+` (mutually exclusive, days past due date, upper bound inclusive), the
  same in `/admin/outstanding`, the dashboard and the report. Invoices with no due date: see §6 (DECISION).
- **Verify:** due dates 10, 45, 75 and 100 days ago plus one future → one row in each bucket.

**BE-113: Party-wise outstanding (P1)**
- **Screen/flow:** Outstanding → Party-wise tab (opening balance, invoice balance per bucket, total).
- **Now:** only per-invoice rows (`/admin/outstanding`) and per-party credit snapshots (`/parties/{ref}/ledger`),
  one party at a time.
- **Needed:** `GET /admin/outstanding/parties?page=&per_page=&search=` → `[{party_ref, party_name,
  opening_outstanding, not_due, b0_30, b31_60, b61_90, b90_plus, invoice_balance, total_outstanding}]`. Scope
  `payments`, key `payments.view`.
- **Verify:** a party with opening 1000 and one invoice balance 500 in 0-30 → total 1500, and opening is not inside
  any bucket.

### 4.17 PDC

SUPPORTED (register/list/get/realize/bounce/cancel) once BE-002 grants `payments.pdc`. Adapter note: the frontend
shows PDCs as payments with mode PDC; the backend keeps them in `pdcs` until realized. The frontend maps this.

### 4.18 Dashboards

**RESOLVED (2026-09-27) — BE-120: Dashboard source route and DTO**
- `GET /admin/dashboard` is protected by `dashboard.view` and returns the named widget groups from
  `ScopedAnalyticsService::dashboard`. Its financial widget delegates to `OutstandingService`.
- Runtime 200/403 validation remains pending the PHP/MariaDB environment; it is not a missing source endpoint.

### 4.19 Reports

**BE-130: Response-time `breached` filter (P2)**
- **Screen/flow:** Reports → Response Time → "SLA Breached" filter.
- **Now:** `report()` supports `from`, `to`, `status`, `sort_by`, `sort_dir`, `page`, `per_page`.
- **Needed:** `breached=Yes|No` on `response-time`.

The other 15 reports (analytics keys + `payment-outstanding`) are SUPPORTED. Their row fields already equal the
frontend report columns.

### 4.20 Audit logs

**RESOLVED (2026-09-27) — BE-140: Audit list source route**
- `GET /admin/audit` has a protected controller, filtering and pagination in current source. Keep BE-141 for the
  frontend's enrichment DTO; runtime validation remains pending.

**BE-141: Audit row content (P2)**
- **Screen/flow:** Audit Logs columns Record, Performed By, Previous value, New value.
- **Needed:** each row gets `actor_name`, `record_name` (where resolvable), `before_summary`, `after_summary` (short
  text from `before_json`/`after_json`).

### 4.21 Distributor onboarding

**BE-150: Invites (P1)**
- **Screen/flow:** Onboarding → Invites list, "Send Franchise Invite" dialog, Resend, Revoke, Copy link.
- **Now:** `POST /admin/onboarding/invite` stores only `lead_ref`, `assigned_user_ref` and returns
  `{invite_ref, token, expires_at}`. No list, resend or revoke.
- **Needed:**
  - Invite create accepts `firm_name, contact_name, mobile, email, proposed_state_ref, proposed_district_ref,
    proposed_city_ref, proposed_pincode, proposed_tier_ref, channel (WHATSAPP|EMAIL|LINK), lead_ref?, notes?`.
  - `GET /admin/onboarding/invites` (paginated, `status` filter) → rows with those fields + `status
    (SENT|OPENED|REGISTERED|APPROVED|REJECTED|EXPIRED|REVOKED), sent_at, expires_at, invited_by`.
  - `POST /admin/onboarding/invites/{ref}/resend` (new token + expiry) and `…/revoke`.
  - Keys `distributorOnboarding.generateInvite`, `distributorOnboarding.resendRevokeInvite`.
- **Verify:** create → list shows SENT; revoke → REVOKED, and the public lookup (BE-151) of that token → REVOKED.

**BE-151: Public invite lookup (P1)**
- **Screen/flow:** `/register/:token`, which prefills from the invite or registration and shows Invalid / Revoked /
  Expired / Already submitted / Approved / Not approved.
- **Needed:** *public* `GET /onboarding/invites/{token}` → `{state: VALID|INVALID|REVOKED|EXPIRED|SUBMITTED|
  INFO_REQUESTED|APPROVED|REJECTED, prefill: {firm_name, contact_name, mobile, email, …}, info_request_message?,
  rejection_reason?}`. It never reveals other data.
- **Verify:** a valid token → VALID with prefill; a random token → INVALID.

**BE-152: Re-submit after info request (P1)**
- **Screen/flow:** a registration in "Info Requested" → the applicant edits → resubmits the same record.
- **Now:** `register` marks the invite used; there's no update. `OnboardingLifecycle` allows INFO_REQUESTED →
  SUBMITTED, but no endpoint drives it.
- **Needed:** *public* `POST /onboarding/register/{token}/resubmit` (same body as register) → updates the same
  `onboarding_ref`, status SUBMITTED, history row.
- **Verify:** request-info → resubmit → the admin detail shows the updated fields and history.

**BE-153: KYC upload (P1)**
- **Screen/flow:** Registration wizard document step; reviewer opens each document.
- **Now:** `documents[]` are `{type, file_reference, file_name}` strings; nothing stores a file. Max size configured
  at 5 MB (`Config/security.php`).
- **Needed:**
  - *public (token-bound)* `POST /onboarding/uploads` multipart `file` → `{file_reference}` (pdf/jpg/png, ≤ 5 MB);
  - `GET /admin/onboarding/{ref}/kyc-documents/{doc}/file` (key `kyc.view`) streams it.
- **Verify:** upload a PDF → reference; register with it → the admin downloads the same bytes; a 6 MB file → 422.

**RESOLVED (2026-09-27) — BE-154: Onboarding list source route**
- `GET /admin/onboarding` calls `OnboardingService::listing()` with tenant, assignment scope and pagination. Its
  runtime migration/permission check is still required, but there is no missing source implementation.

**BE-155: Approval gives portal access (P1)**
- **Screen/flow:** Registration → Approve (creates the party + territory; the distributor can then log in to the
  portal).
- **Now:** `approve` only changes status. `convert` creates the party but no DISTRIBUTOR user, so the approved firm
  can't sign in. The registration `password_hash` is unused.
- **Needed:** convert (or approve) also creates the `users` row (role DISTRIBUTOR, `party_ref`, the registration's
  email and password hash). It accepts optional territory allocations `[{level, pincode|district_ref}]` and returns
  `{party_ref, portal_user_ref}`. Key `distributorOnboarding.convert`.
- **Verify:** approve + convert → log in with `crm-portal` using the registration email/password → 200.

### 4.22 Distributor portal

**BE-160: Catalogue/cart tax and pack size (P1)**
- **Screen/flow:** Portal Catalogue (GST %, pack) and Cart (GST, total).
- **Now:** `PortalController::catalogue/calculateCart` read the non-existent columns `gst_rate` and `packing`. Live,
  every product shows `gst_rate: 0`, `packing: null`. The cart uses float rounding while orders use paise.
- **Needed:** read `gst_percent` and `pack_size`. The cart uses the same calculation as order create (including the
  BE-071 split and BE-061 free qty), so cart total = placed order total.
- **Verify:** Paracetamol (GST 12) → catalogue `gst 12`; a 10-unit cart → grand total equals `POST /portal/orders`
  grand total.

**BE-161: Portal submit (P1)**
- **Screen/flow:** Portal Cart → Place order. The workflow says the order arrives SUBMITTED for Admin review.
- **Now:** `placeOrder` creates DRAFT; the portal has no submit; cancel works only in DRAFT.
- **Needed:** either `placeOrder` submits immediately, or `POST /portal/orders/{ref}/submit`. Cancel is allowed until
  CONFIRMED. Key `portal.placeOrder`.
- **Verify:** place an order → the admin list shows SUBMITTED.

**BE-162: Portal invoice detail (P1)**
- **Needed:** `GET /portal/invoices/{ref}`: the admin invoice detail shape, party-bound (another party's ref → 404).
- **Verify:** own invoice → 200; another party's → 404.

**BE-163: Portal dispatch detail (P2)**
- **Needed:** `GET /portal/dispatches/{ref}` (party-bound) with transporter, LR, tracking URL, history.

**BE-164: Portal receipts (P2)**
- **Screen/flow:** Portal Outstanding → payments received and their allocations.
- **Needed:** `GET /portal/payments` (party-bound, paginated) with `allocations[]` as in BE-111.

**BE-165: Portal offers (P2)**
- **Screen/flow:** Portal Offers (active schemes applicable to the distributor's tier).
- **Needed:** `GET /portal/schemes` → active schemes (tier NULL or the party's tier) with rules and product names.

**BE-166: Portal profile fields (P2)**
- **Now:** `PATCH /portal/profile` accepts `shipping_address, mobile, contact_name`.
- **Needed:** also `email`, `whatsapp`, `billing_address` (same validation as parties). Key `portal.editProfile`.

**BE-167: Distributor team users (P1)**
- **Screen/flow:** Portal My Team (Owner creates, edits and deactivates Team Users). Team Users sign in and see only
  the DCR screens (Owner-only routes are guarded by role).
- **Now:** no portal team endpoints. `/auth/me` for a portal user carries no Owner/Team distinction.
- **Needed:**
  - `GET/POST /portal/team`, `PATCH /portal/team/{ref}`, `POST /portal/team/{ref}/status`, all Owner only.
  - A team user is a `users` row bound to the same `party_ref` with a team role, `area`, `beat`.
  - `/auth/me` returns `portal_role: OWNER|TEAM_USER` (or keys that separate them).
  - Team users can log in via `crm-portal`.
- **Verify:** the Owner creates a team user → that user logs in → `GET /portal/dcrs` → 200, and
  `GET /portal/orders` → 403.

### 4.23 DCR

**BE-170: DCR reachable for portal users (P0)**
- **Screen/flow:** every DCR screen (list, entry, pending approvals).
- **Now:** current source exposes list/create/get/update/status and uses `dcr.*` permissions. The B1 role/permission
  migration is uncommitted and unverified, so portal Owner/Team User grants have not been proven deployed.
- **Needed:** after B1 is applied, Distributor Owner and Team User roles are seeded with:
  - Team User: `dcr.view, dcr.create, dcr.edit, dcr.submit`;
  - Owner: those plus `dcr.approve, dcr.reject`.
- **Decision:** D-9 is resolved: Admin ko portal DCR NAHI dikhega (FRS H14); DCR poora portal ke andar hai.
- **Verify:** PORTAL Owner/Team User `GET /portal/dcrs` → 200 with proper scope. CRM/Admin surfaces have no portal DCR visibility and should not gain an Admin DCR screen for this item.

**BE-171: Field customers (P0)**
- **Screen/flow:** Field Customers list/form; DCR entry picks field customers for each visit.
- **Now:** no field-customer entity. `dcr_visits_v2` requires `party_ref` XOR `lead_ref`.
- **Needed:**
  - `GET/POST /portal/field-customers`, `GET/PATCH /portal/field-customers/{ref}`, `POST …/{ref}/status`, fields
    `{name, type (DOCTOR|CHEMIST|STOCKIST|HOSPITAL|…), specialty?, clinic_or_firm_name, mobile, email?, address, beat,
    city, pincode, category (A|B|C), visit_frequency, date_of_birth?, anniversary_date?, remarks?}`, bound to the
    distributor party.
  - DCR visits accept `field_customer_ref`.
- **Verify:** create a customer → use it in a DCR visit → DCR detail shows the customer; another distributor's
  customer → 404.

**BE-172: Beats (P1)**
- **Needed:** `GET/POST /portal/beats`, `PATCH /portal/beats/{ref}`, `POST …/status`, fields `{name, description?}`.

**BE-173: Tour plan (P1)**
- **Needed:** `GET /portal/tour-plans?user_ref=&from=&to=`, `POST /portal/tour-plans`, `PATCH /portal/tour-plans/{ref}`
  with `{field_user_ref, date, planned_beat, planned_field_customer_refs[]}`. The Owner plans for team users; a team
  user sees their own.

**BE-174: Missed-DCR compliance (P2)**
- **Screen/flow:** Missed DCRs screen and the portal dashboard compliance widget.
- **Needed:** a party field `dcr_submission_cutoff_time` (HH:MM, editable by Admin on the party). Then
  `GET /portal/dcrs/missed?from=&to=` → `[{field_user_ref, name, date}]`: working days with no DCR submitted by the
  cut-off.

**BE-175: POB → order (P1)**
- **Screen/flow:** POB Summary → select visits → Convert to Order.
- **Needed:** `POST /portal/dcrs/pob/convert {visit_refs[]}`. It:
  - creates one order (DRAFT or SUBMITTED, per BE-161) for the distributor from the visits' POB product/qty;
  - marks those visits `pob_converted = true, pob_order_ref`;
  - puts `source_dcr_refs` on the order.

  Converting a visit twice → 409.

**BE-176: Reopen (P2)**
- **Screen/flow:** DCR entry → Owner "Reopen" on an APPROVED DCR.
- **Now:** `DcrService::transition` allows no transition out of APPROVED.
- **Needed:** APPROVED → DRAFT by the Owner with `dcr.approve` and a mandatory reason, recorded in history.

**BE-177: Visit photo and location (P2)**
- **Needed:** visit photo upload (same mechanism as BE-153, token → user-bound) → `photo_file_reference`; optional
  `location {lat, lng}` on the visit.

### 4.24 Settings · Notifications · Webhooks

- **Settings:** see BE-086 (the only persisted setting the frontend has besides theme, which stays local).
- **Notifications:** `GET /notifications`, `POST /notifications/{ref}/read`, `POST /notifications/read-all` are
  SUPPORTED. WhatsApp messages are shown only through the `whatsapp-delivery` report (SUPPORTED).
- **Webhooks:** sources list/create SUPPORTED; edit/status is BE-015. Failures come from the `webhook-failures` report
  (SUPPORTED).

---

## 5. Do not change: already used by the integrated frontend

A breaking change here breaks screens that are already live. Additive fields are fine.

| Area | Endpoints / shape the frontend depends on |
|---|---|
| Auth | `POST /oauth/token` password grant (`client_id` ∈ crm-admin/crm-sales/crm-super/crm-portal, `franchise_code` for non-super) and refresh grant → `{access_token, refresh_token, expires_in, token_type}`. Refresh-token rotation + `REFRESH_TOKEN_REUSED` family revoke. `POST /oauth/revoke` → `{revoked:true}`. `GET /auth/me` → `user_ref, name, email, status, role, roles[], permissions{module:[action]}, scopes{module:SCOPE}, org_ref, franchise_ref, party_ref, impersonator_ref`. Empty maps may be `[]` or `{}`. |
| Auth error codes | 401 `INVALID_CREDENTIALS`, `INVALID_CLIENT`, `TOKEN_*`, `SESSION_REVOKED`, `REFRESH_TOKEN_REUSED`, `REFRESH_TOKEN_EXPIRED`, `INVALID_REFRESH_TOKEN`, `USER_INACTIVE`; 422 `FRANCHISE_CODE_REQUIRED`, `VALIDATION_FAILED` (+ `fields`); 429 `RATE_LIMIT_EXCEEDED`. |
| Masters (live) | `GET/POST /admin/categories`, `GET/PATCH /admin/categories/{ref}`, `POST …/{ref}/status {status}` (`category_name`); the same for `/admin/tiers` (`tier_name`, stored uppercase) and `/admin/catalog-masters/{dosageForms,schemeTypes}` (`name`, `description`); `GET/POST /admin/transporters` (`transporter_name`, `tracking_url_template`); `GET /geo/states`, `GET /geo/districts?state_ref=`, `GET /geo/pincodes/{pin}`. List params `page, per_page, search, status`. Duplicate name → 409 `DUPLICATE_*`. |
| Envelope | `{success, data, meta:{request_id, page, per_page, total, total_pages}}`; `data` is an array for standard lists. Errors `{success:false, error:{code, message, fields?}, meta:{request_id}}`. Out-of-scope record → 404. |
| Permission keys | The module/action names in `auth_permission_catalogue`. The frontend's PermissionGate uses the same strings; renaming a key hides UI. |

---

## 6. Business rules both sides must agree on

Only rules the frontend displays.

| Rule | Frontend expects | Backend now | Needed |
|---|---|---|---|
| Pricing precedence | party rate > tier rate > net rate > PTS; shows `rate_source` | party > tier > `franchise_rate` (net rate) > error; `rate_source` PARTY/TIER/DEFAULT; within a level `priority ASC, effective_from DESC` | Add the PTS fallback (BE-060) with `rate_source: PTS`. Keep `rate_source` on resolve, order lines and the portal catalogue. |
| Scheme free qty | flat free qty; highest-priority scheme; no stacking | `floor(qty/min) × free`; highest free wins; stacking sums (capped at qty) | **DECISION** (BE-061). Then the backend alone computes it; the frontend only displays it. |
| GST split | CGST + SGST (intra-state) or IGST (inter-state), line **and** total | invoice only; line CGST = floor(tax/2), SGST = rest; place of supply = shipping-pincode state vs franchise state | Same rule on orders/cart (BE-071). The frontend never recomputes it. Free goods amount = 0 (BE-090). |
| Order totals / rounding | line = qty × rate − discount + tax; totals = sums; 2 decimals | paise math; tax per line rounded half-up; no rounding adjustment (0) | Keep the backend rule. Order, cart and invoice must produce identical totals (BE-071, BE-160). Discount: BE-074. |
| Ageing | base = due date (invoice date + payment terms); Not Due separate; 0-30 / 31-60 / 61-90 / 90+ mutually exclusive | base = due date ✓; 7 buckets incl. 91-120, 120+, UNCLASSIFIED (no terms → no due date) | 5 buckets (BE-112). **DECISION:** invoices of parties with 0 payment terms: due = invoice date, or UNCLASSIFIED? |
| Opening outstanding | separate line, never aged; party total = opening + invoice balances | stored on the party (create-only); excluded from ageing rows ✓; included in credit exposure and portal outstanding | Keep. Expose it in the party-wise summary (BE-113). |
| Credit limit | warning on the order form | confirm hard-blocks `CREDIT_LIMIT_EXCEEDED`; limit 0 = no credit | **DECISION** (BE-073): meaning of 0. Hard block vs warning is the backend's call; the frontend shows the error. |
| FEFO | server allocates at confirm; the frontend shows the reserved batches read-only | expiry ASC, mfg ASC; greedy multi-batch; reserves paid + free | Keep. Fix the shelf-life misuse (BE-072). |
| Near-expiry valuation | available qty × MRP | analytics report ✓; list endpoint has no value | Put the value on the list rows (BE-085). |
| Order status flow | Draft → Confirmed → Billed → Packed → Dispatched → Delivered (+ Cancelled) | DRAFT → SUBMITTED → CONFIRMED → PROCESSING → DISPATCHED → DELIVERED; nothing reaches PROCESSING; dispatch fails | BE-070: one endpoint per step. "Billed" = has a posted invoice. "Packed" = PROCESSING. The frontend maps SUBMITTED. |
| Territory | violation → Admin override with reason → order proceeds; unassigned pincode = HOLD (UI placeholder) | CONFLICT/UNASSIGNED block submit/confirm; override recorded but ignored | BE-050. **DECISION:** the unassigned-pincode mode (block / review / allow). |
| DCR approval | Team User submits; Owner approves/rejects/reopens with reason; one DCR per user per day; Admin does not see portal DCRs (FRS H14) | DRAFT → SUBMITTED → APPROVED/REJECTED; REJECTED → DRAFT; owner ≠ reviewer; one per user/day ✓ | BE-170 portal roles, BE-176 reopen. |
| POB → order | selected POB visits become one order; visits flagged converted | not implemented | BE-175. |
| Onboarding approval | Approve creates the party (+ territory) and portal login | approve = status only; convert = party only | BE-155. |

---

## 7. Cross-cutting (only what blocks the frontend)

| Topic | Item |
|---|---|
| Permission key vs role name | BE-001 (+ BE-002). Without these, the configurable Roles screen doesn't control anything for custom roles. |
| Pagination outlier | BE-003 (leads, follow-ups). |
| Error format | BE-008 (`fields` must stay field → messages). |
| CORS | BE-004 - production blocker, integration blocker nahi. |
| Refresh keeps the audience | BE-005. |
| Admin franchise scope | BE-006 - decided: all franchises with optional franchise filter; no re-login switcher. |
| OpenAPI | BE-007: 39 routes undocumented, 63 of 73 success responses without a schema, invalid analytics block. |

---

## 8. Security detail (already in the main priority table)

Security items are no longer a separate hidden queue. They are listed in §2 with priorities: **P0** S-3 lead authorization/data leak, **P0** S-4 privilege escalation, **P0** S-1 payment record-scope leak, **P1** S-2 webhook-source permission check, **P2** S-5 party restore scope.

Verification detail: S-3 needs PORTAL `GET /admin/leads` → 403 and SALES status change on another rep's lead → 403/404. S-4 needs a non-admin with `internalUsers.create` sending legacy `role=FRANCHISE_ADMIN` → 403 `PERMISSION_ESCALATION`. S-1 needs SALES (OWN) to see only payments for parties in scope, including list/detail/mutation checks. S-2 needs PORTAL → 403 and ADMIN with `webhooks.configure` → 200. S-5 needs an OWN-scope user restoring another rep's party → 404.

---

## 9. Draft code (untested)

A draft for BE-001, part of BE-002, S-3 and S-4 is in the backend working tree, **uncommitted, never executed or
linted** (no PHP runtime was available). Use it as a reference, or implement it your own way.

- Report + scan table: `Pharma-CRM/B1_ROLE_TO_PERMISSION_REPORT.md`
- Migration: `Pharma-CRM/database/migrations/012_b1_permission_based_access.sql`. It adds `settings.*` and `portal.*`
  and makes all catalogue keys grantable. It grants Admin only `settings.*`, so it does **not** yet grant the other
  BE-002 keys.
- New: `app/Domain/Authorization/CrmScopePolicy.php`, `app/Domain/Authorization/SystemRoles.php`
- Changed: `app/Core/TenantContext.php`, `app/Policies/Sales{Lead,FollowUp}Policy.php`,
  `app/Http/Controllers/Api/V1/Admin/{Leads,FollowUps,Masters,CatalogMasters,Products,Prices,Schemes,Settings,Orders,
  Invoices,Dispatches,Outstanding,Pdcs,Payments,Users}Controller.php`, `Portal/PortalController.php`,
  `Super/FranchisesController.php`, `app/Domain/Leads/LeadAssignmentService.php`,
  `app/Domain/Authorization/PartyScopePredicate.php`, `app/Domain/DCR/DcrService.php`, lead/follow-up repositories,
  `bootstrap/bindings.php`, `tests/Agents/agent-idor.php`
- Note: the draft was written on top of `bed8f35`. The later commits don't touch these files except
  `bootstrap/bindings.php` (the PaymentService binding) and `PaymentService`/`AllocationService`. Re-check that merge.
