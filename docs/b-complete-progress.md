Status ka single source of truth: Pharma-CRM/docs/OPEN_ISSUES.md. Backend ko dene wali list: docs/BACKEND_REQUIREMENTS_FOR_FRONTEND.md

# B-COMPLETE: progress tracker

Update this file before every session ends. The next session resumes from "Next action".

## Reconciliation (2026-09-27)

**RESOLVED (2026-09-27):** dashboard source wiring, audit list source wiring, onboarding list source wiring, scoped PDC
mutations, allocation/reversal core, outstanding/payment-outstanding source wiring, and production credit confirmation.
**Still open:** the current backend handoff's BE/S items. In particular, BE-070 lifecycle, BE-090 free-goods billing,
BE-091 supplier state, BE-102 duplicate stock consumption, BE-170/171 DCR and S-1/S-2 security.

**Last updated:** 2026-09-27
**Status:** ⛔ BLOCKED on the local environment (precondition not met)

## Precondition (from the phase brief)
- [ ] Local PHP 8.2 + MariaDB running. **Not installed yet**: no `C:\xampp`, no `php` on PATH, nothing on port 3306.
- [ ] B1 (role-name → permission keys) verified locally. The code and migration 012 are written, but not run and not
      linted. See `B1_ROLE_TO_PERMISSION_REPORT.md` §5.

### Next action (owner: developer)
1. Install **XAMPP for Windows, PHP 8.2.x** (bundles MariaDB 10.4, which is required: the migrations use MariaDB-only
   `ADD COLUMN IF NOT EXISTS`).
2. Start "MySQL" in the XAMPP Control Panel.
3. Send the output of `C:\xampp\php\php.exe -v` and `C:\xampp\php\php.exe -m` (needs `pdo_mysql`, `openssl`, `curl`,
   `mbstring`).

### Then (owner: Claude, in this order)
1. Create a local-only `Pharma-CRM/.env` (git-ignored; the live server keeps its own). Create DB `crm_local`, then run
   schema → migrations 002–012 → seeds. Report before running each step.
2. B1 verification: lint the changed files, run `agent-idor`, the "Dispatch Team" custom role 200/403 checks,
   Admin/Sales/Portal regression, and re-run 012 (it must be idempotent).
3. Start the B-COMPLETE modules below, one at a time.

## Modules

| # | Module | Status | Notes / what's left |
|---|---|---|---|
| 0 | B1 permission keys | IN PROGRESS | code written; verification pending the env |
| 1 | Masters (+ geography) | NOT STARTED | known gaps: 17 simple masters have no endpoint; no city/pincode list; no transporter update/status; no DELETE (intended) |
| 2 | Roles / permissions | NOT STARTED | endpoints exist; Admin role missing post-002 keys (D4) |
| 3 | Leads | NOT STARTED | legacy pagination shape; B1 moved them to permission keys |
| 4 | Follow-ups | NOT STARTED | legacy pagination shape; no edit/missed |
| 5 | Parties | NOT STARTED | |
| 6 | Territory | NOT STARTED | override not consumed by orders (D3) |
| 7 | Products | NOT STARTED | |
| 8 | Pricing / schemes | NOT STARTED | the brief's precedence includes a PTS fallback; the backend has none → needs a decision |
| 9 | Orders | NOT STARTED | D1, D3, D10, D14; no order-level GST split |
| 10 | Inventory / batches | NOT STARTED | D10 shelf-life semantics |
| 11 | Billing / invoice | NOT STARTED | D2 free goods billed; D6 franchise state |
| 12 | Dispatch | NOT STARTED | BE-070 lifecycle remains; BE-102 duplicate reservation consumption added by current-source audit |
| 13 | Payments / outstanding | NOT STARTED | D7 list scope; brief asks 4 buckets vs backend 7 → needs a decision |
| 14 | Dashboards | RESOLVED (2026-09-27) | source route/service is implemented; runtime verification remains blocked |
| 15 | Reports | NOT STARTED | |
| 16 | Audit logs | RESOLVED (2026-09-27) | source route/service is implemented; audit row DTO is still BE-141 |
| 17 | Onboarding | IN PROGRESS | list source is RESOLVED (2026-09-27); upload, re-submit, invite list/resend/revoke remain |
| 18 | Distributor portal | NOT STARTED | D9 GST 0 / packing; D11 no portal submit |
| 19 | DCR | NOT STARTED | source routes exist; B1 portal grants remain BE-170; field customer / beat / tour plan / compliance missing |
| — | Cross-cutting | NOT STARTED | CORS, refresh aud preservation, webhook-sources permission (D8), pagination unification, OpenAPI sync, seeds |

## Business-rule decisions needed (will be logged as BLOCKERs, not guessed)
- Pricing: should a PTS fallback exist after net rate (the brief says yes; the backend's default today is
  `franchise_rate` only)?
- Ageing buckets: 4 (0-30/31-60/61-90/90+) per the brief vs the backend's 7 (incl. NOT_DUE, 91-120, 120+,
  UNCLASSIFIED). Where does "not yet due" go?
- Admin franchise scope: a switcher (re-login per franchise) or cross-franchise read access?
