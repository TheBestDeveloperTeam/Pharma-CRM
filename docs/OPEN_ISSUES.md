Status ka single source of truth: Pharma-CRM/docs/OPEN_ISSUES.md. Backend ko dene wali list: docs/BACKEND_REQUIREMENTS_FOR_FRONTEND.md

# Open issues: single source of truth

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


Internal tracking for **pending** work only (backend + frontend). Resolved items are not listed here; the old files
keep them marked `RESOLVED (<date>)`.
The list to hand to the backend team is `docs/BACKEND_REQUIREMENTS_FOR_FRONTEND.md` (BE-IDs); this file references it.

**Last updated:** 2026-09-27 · backend `main` @ `5d21951` · frontend working tree after F16-2.

## Current-source reconciliation (2026-09-27)

**RESOLVED (2026-09-27):** BE-120 dashboard route/service; BE-140 audit list route/service; BE-154 onboarding
list route/service; and the source implementation of scoped PDC lifecycle/mutations, allocation/reversal,
outstanding, payment-outstanding and credit confirmation. They are not pending implementation items.

**BE-102 (P0):** `DispatchService::create()` and `DispatchService::deliver()` both consume the same FEFO
reservation. Make exactly one point authoritative before enabling the fulfilment path.

**Security pending:** S-3 lead authorization/data leak, S-4 privilege escalation and S-1 payment record scope are P0 and appear in the main table. S-2 webhook-source permissions and S-5 party restore scope also appear in the main table. PDC source scope is resolved; its deploy-time permission grant is BE-002.

Priority: **P0** blocks integrating a module · **P1** a flow stays broken · **P2** works with a workaround.

---

## A. Backend: requirements (details in BACKEND_REQUIREMENTS_FOR_FRONTEND.md)

| P | IDs |
|---|---|
| P0 | S-3 lead status/list authorization data leak · S-4 legacy role privilege escalation · S-1 payments record scope · BE-102 duplicate reservation consumption · BE-001 permission keys (not role names) · BE-002 Admin missing post-002 keys · BE-091 franchise state for GST · BE-090 free goods billed · BE-070 dispatch/fulfilment path · BE-170 portal DCR grants/reachability · BE-171 field customers · BE-004 CORS (production blocker, integration blocker nahi) |
| P1 | S-2 webhook-source permissions · BE-003 pagination outlier · BE-007 OpenAPI · BE-010 reference masters · BE-012 city/pincode lists · BE-030/031/032/033 leads fields, filters, archive/restore, convert · BE-040/041 follow-up detail/edit/missed + history · BE-050 territory override · BE-061 scheme rule (DECISION) · BE-071 order GST split · BE-072 FEFO shelf-life bug · BE-073 zero credit limit (DECISION) · BE-080 movement ledger · BE-100 dispatch update · BE-110/111/112/113 payment fields/edit, allocation history, 5 ageing buckets, party-wise outstanding · BE-150/151/152/153/155 onboarding invites, token lookup, resubmit, upload, portal login on approval · BE-160/161/162/167 portal GST/pack, submit, invoice detail, team users · BE-172/173/175 beats, tour plan, POB → order |
| P2 | S-5 party restore scope · BE-005 refresh audience · BE-006 admin all-franchise access with optional franchise filter · BE-008 `fields` contract · BE-011 enum lists · BE-013 geography maintenance (DECISION) · BE-014 transporter edit/status · BE-015 templates/webhook edit · BE-020 roles on user list · BE-060 PTS fallback · BE-074 line discount (DECISION) · BE-075 order territory filter · BE-081/082 batch edit, transfer · BE-085/086 near-expiry value, thresholds · BE-101 pending dispatch list · BE-130 report filter · BE-141 audit summaries · BE-163/164/165/166 portal dispatch detail, receipts, offers, profile fields · BE-174/176/177 DCR compliance, reopen, photo |

### Backend: security detail

Security items are part of the main priority table above, not a separate hidden queue. Current severity: **P0** S-3 lead list/status authorization data leak, **P0** S-4 legacy `role=FRANCHISE_ADMIN` privilege escalation, **P0** S-1 payments record-scope leak, **P1** S-2 webhook-source permission check, **P2** S-5 party restore scope.

### Backend: internal (found in the audit; not in the requirements doc because no frontend screen depends on them)

| P | Issue |
|---|---|
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
| P1 | Integrate the remaining modules per `pharma-sales-crm/docs/integration-pattern.md` | Auth (F16-1) and 4 masters + read-only geography (F16-2) are live; everything else is mock. The order follows the dependencies in `api-contract-gaps.md` §7 |
| P1 | Adapter work that comes with each module (not a backend dependency) | Lead status (8 → 11), order status (Billed = has an invoice; Packed = PROCESSING), payment modes, PDC as a separate entity, `shelf_life_days` ↔ months, scheme header + rules ↔ flat scheme |
| P1 | Remove client-side business calculations as each module goes live | `resolvePricing`, scheme calc, `orderCalculations` GST split, `outstandingUtils` due date/buckets, FEFO suggestion, `invoiceUtils` (see `api-contract-gaps.md` §6). Keep display-only helpers |
| P2 | Hybrid-phase data mismatch | Live user/party IDs don't exist in mock data: OWN-scope lists look empty, and the portal shows "account isn't available yet". This disappears as modules go live; don't fake IDs |
| P2 | Masters dropdowns in other modules still read mock masters | Switch each module's reference-data hooks when that module goes live |
| P2 | 375 px responsive pass for the screens listed in `pharma-sales-crm/docs/pending-responsive.md` | Visual only |
| P2 | Two test transporters left ACTIVE on the live server (`F16-2 Verify Transporter 436219`, `…774828`) | No status endpoint (BE-014); ask the backend team to remove them |
