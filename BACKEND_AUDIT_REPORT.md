# Pharma CRM — Backend Audit Report

**Audit scope:** PHP backend in `Pharma-CRM`, compared with `pharma-sales-crm` frontend, FRS v2.3, OpenAPI and the existing frontend/API contract audit. **No source code was changed.**

## Executive summary

The backend has a credible layered foundation: versioned routes, JWT/refresh-token tables, tenant columns, idempotency infrastructure, FEFO-related services/tables, domain services, and broad CRUD/read coverage. However, it is **not ready for production frontend integration** against the current FRS. The biggest blockers are:

- FRS v2.3 requires configurable internal roles and 22-module/90-action permissions, while the schema hard-codes four roles in `users.role` and `role_permissions.role`.
- DCR and distributor onboarding/document verification are not fully represented by the API; DCR routes/schema are absent from the audited route set.
- Most lifecycle write operations are missing: draft edit/delete/submit, invoice cancellation/re-invoice, payment update/cancel/reversal/allocation, dispatch update/cancel, stock transfer and movement-ledger operations.
- Invoice/tax contracts expose combined GST only; the frontend/FRS needs CGST/SGST/IGST semantics.
- Server-side filtering, ownership/team scope, permission enforcement and record-level authorization are not demonstrably complete from routes/contracts.
- Database design has useful indexes and constraints, but several business invariants still depend on application code and need transaction/locking tests before integration.

**Overall readiness:** NOT READY. Read-only Geo, health and parts of catalogue/auth can be integrated after contract verification. Orders, billing, inventory, payments, permissions, onboarding, portal team/DCR and reports must be fixed or explicitly re-scoped first.

## Module status

| Module | Backend status | Major gap | Severity |
|---|---|---|---|
| Auth/session | PARTIAL | OAuth/revoke/me exist; password reset/refresh contract and deactivated-user behavior need end-to-end proof | HIGH |
| Users/roles/permissions | INCOMPATIBLE | Fixed DB roles conflict with configurable FRS roles; no role CRUD/clone/permission APIs | CRITICAL |
| Leads | PARTIAL | CRUD/status/assign exist; archive, conversion and full activity/remark workflow not exposed consistently | HIGH |
| Follow-ups | PARTIAL | Create/complete/reschedule exist; edit/missed/remark-history actions absent | MEDIUM |
| Parties | PARTIAL | CRUD/archive/ledger exist; restore/activate/deactivate, territory history and onboarding state incomplete | HIGH |
| Territory | PARTIAL | list/create/validate/override exist; effective-dated update/deactivate/history and conflict enforcement need proof | HIGH |
| Products/pricing/schemes | PARTIAL | core read/create/resolve/calculate exists; lifecycle CRUD/history/overlap rules incomplete | HIGH |
| Orders | PARTIAL | create/list/show/confirm/cancel exist; draft edit/delete/submit and complete validation contract not proven | CRITICAL |
| Inventory/FEFO | PARTIAL | receive/adjust/batch/near-expiry exist; stock list, transfer, movement ledger, release reservation and concurrency proof missing | CRITICAL |
| Billing/invoices | PARTIAL | list/generate/show exist; cancel/re-invoice and GST split/batch allocation contract missing | CRITICAL |
| Dispatch | PARTIAL | list/create/show/deliver exist; update/cancel/LR lifecycle and strict invoice/stock guards need proof | HIGH |
| Payments/outstanding | PARTIAL | list/create/show exist; update/cancel/allocation/reversal/PDC lifecycle missing | CRITICAL |
| Reports/dashboard | PARTIAL | generic report endpoint exists; report catalogue/response schemas and scope/export authorization incomplete | HIGH |
| Audit logs | PARTIAL | audit infrastructure exists; immutable coverage for every sensitive mutation and query API need proof | HIGH |
| Notifications/webhooks | PARTIAL | notification read endpoints and webhook ingestion exist; provider config/retry/templates/observability incomplete | HIGH |
| Distributor onboarding/portal | PARTIAL | invite/register and core portal exist; documents, verification, team users and team DCR missing | HIGH |
| DCR | MISSING | No DCR API route found in `bootstrap/routes.php` | CRITICAL |

## API coverage

| Operation | Backend evidence | Status | Gap |
|---|---|---|---|
| Login/revoke/me | `bootstrap/routes.php:12-14` | PARTIAL | Refresh-token endpoint is not registered; verify frontend OAuth contract and permissions payload. |
| Product CRUD | `bootstrap/routes.php:77-84` | PARTIAL | No delete endpoint; category/masters lifecycle writes are incomplete. |
| Lead lifecycle | `bootstrap/routes.php:95-100` | PARTIAL | No explicit archive/convert/activity-history endpoint. |
| Follow-up lifecycle | `bootstrap/routes.php:103-107` | PARTIAL | No edit or mark-missed endpoint. |
| Party lifecycle | `bootstrap/routes.php:110-115` | PARTIAL | No restore/activate/deactivate endpoint. |
| Territory allocation | `bootstrap/routes.php:118-121` | PARTIAL | No update/deactivate/history endpoint. |
| Onboarding | `bootstrap/routes.php:124-125` | PARTIAL | Invite/register exist, but document upload/verification/status APIs are absent. |
| Inventory | `bootstrap/routes.php:133-136` | PARTIAL | No general batch list, transfer, reservation release or movement ledger route. |
| Orders | `bootstrap/routes.php:139-144` | PARTIAL | No draft update/delete/submit; lifecycle name/state mapping must be aligned. |
| Invoices | `bootstrap/routes.php:147-149` | PARTIAL | No cancel/re-invoice; generation must expose tax and allocation details. |
| Dispatch | `bootstrap/routes.php:152-156` | PARTIAL | No edit/cancel/track update. |
| Payments | `bootstrap/routes.php:159-161` | PARTIAL | No update/cancel/allocation/reversal/PDC APIs. |
| DCR | route search | MISSING | No route/controller integration found. |
| Portal team | portal routes | MISSING | Profile/catalogue/cart/orders/invoices/dispatches/outstanding exist; team-user management and DCR absent. |

## Backend findings

### BE-F1 — Configurable permission model is incompatible

- **Severity:** CRITICAL; **Module:** Authorization
- **Requirement:** FRS v2.3 sections 44/45 require admin-configurable roles, permissions and scopes.
- **Evidence:** `database/schema/001_full_schema.sql` defines `users.role ENUM('SUPER_ADMIN','FRANCHISE_ADMIN','SALES','DISTRIBUTOR')` and `role_permissions.role` with the same fixed enum. `bootstrap/routes.php` contains no role/permission CRUD routes.
- **Risk:** Frontend permission gates can be bypassed or cannot be persisted; authorization behavior cannot match approved requirements.
- **Correction:** Introduce a versioned role/permission model, APIs, scope evaluation and protected admin operations; add last-admin protection and authorization tests.

### BE-F2 — DCR backend is missing

- **Severity:** CRITICAL; **Module:** DCR
- **Requirement:** Internal/Distributor DCR workflows, submission, approval, missed reports and reporting.
- **Evidence:** No DCR route appears in `bootstrap/routes.php`; no DCR controller/service/table was found in the audited backend route/schema set.
- **Risk:** Frontend DCR screens have no integration target.
- **Correction:** Define schema, role model, CRUD/submission/approval APIs, ownership rules, attachments and audit events.

### BE-F3 — Distributor onboarding is incomplete

- **Severity:** HIGH; **Module:** Onboarding/KYC
- **Evidence:** Only invite/register routes are registered. `onboarding_invites` stores token and dates, but no document/KYC verification entities or approval/rejection endpoints are evident.
- **Risk:** Registration cannot complete the FRS onboarding workflow and sensitive documents lack a defined secure lifecycle.
- **Correction:** Add document metadata/storage abstraction, upload validation, verification states, reviewer actions, expiry/revocation and audit trail.

### BE-F4 — Order lifecycle and server-side business validation are incomplete

- **Severity:** CRITICAL; **Module:** Orders
- **Evidence:** Routes expose create/confirm/cancel only (`bootstrap/routes.php:139-144`). No draft update/delete/submit endpoint is registered. FRS requires party/product/pricing/scheme/territory/credit/stock/billing eligibility checks.
- **Risk:** Invalid or stale orders can enter confirmation/billing; frontend-only checks are bypassable.
- **Correction:** Define explicit state machine transitions, idempotent confirm, authoritative server pricing/scheme/credit/territory/stock checks and transition audit events.

### BE-F5 — Inventory and FEFO mutation surface is incomplete

- **Severity:** CRITICAL; **Module:** Inventory
- **Evidence:** Routes cover receive, adjust, batch show and near-expiry only (`bootstrap/routes.php:133-136`). No transfer, reservation release, movement-ledger, stock list or allocation-preview route is registered.
- **Risk:** Stock cannot be reconciled or operationally managed; FEFO behavior is not observable and concurrent orders may oversell.
- **Correction:** Add movement ledger, transfer, reservation lifecycle and allocation details; use transactional row locks/consistent isolation and concurrency tests.

### BE-F6 — Billing/tax contract is insufficient

- **Severity:** CRITICAL; **Module:** Billing
- **Evidence:** Product schema stores only `gst_percent`; existing API audit identifies combined `gst_total` without CGST/SGST/IGST fields. Invoice routes only list/generate/show.
- **Risk:** Indian tax invoice screens and legal tax calculations cannot be reliably rendered; rounding/inter-state rules are ambiguous.
- **Correction:** Define tax jurisdiction inputs and immutable invoice tax snapshot with CGST, SGST, IGST, taxable value, rounding and place-of-supply rules; add cancellation/reissue policy.

### BE-F7 — Payment allocation lifecycle is missing

- **Severity:** CRITICAL; **Module:** Payments
- **Evidence:** Only list/create/show payment routes exist (`bootstrap/routes.php:159-161`). No allocation, reversal, cancellation, edit or PDC status endpoints are registered.
- **Risk:** Outstanding can diverge from invoices/payments; over-allocation and post-allocation edits may corrupt balances.
- **Correction:** Use one authoritative ledger/calculation model, transactional allocation rows, immutable posted payments, controlled reversal and idempotent balance recalculation.

### BE-F8 — Scope/record authorization is not demonstrable

- **Severity:** CRITICAL; **Module:** Security
- **Evidence:** FRS requires own/team/territory/all/none scopes. Schema has only fixed role scopes, while route definitions do not show per-operation policy declarations. Existing frontend audit also flags undocumented `assigned_user_ref` filtering.
- **Risk:** IDOR and cross-sales-user data exposure through direct API calls.
- **Correction:** Centralize policy checks in middleware/service layer for every read/write/export; test own/team/territory/all/none and cross-tenant references.

### BE-F9 — Lifecycle APIs and contracts are under-specified

- **Severity:** HIGH; **Module:** API integration
- **Evidence:** Existing `docs/api-contract-gaps.md` records missing update/delete/cancel/archive/activate actions across resources and unclear response schemas/filtering. OpenAPI is broad but does not make all frontend operations executable.
- **Risk:** UI cannot replace mocks reliably; pagination/filter/sort and error mapping will be inconsistent.
- **Correction:** Publish operation-specific request/response schemas, stable error codes/field errors, enums, pagination/filter/sort rules and idempotency requirements.

### BE-F10 — Webhook/notification operational workflow is incomplete

- **Severity:** HIGH; **Module:** Integrations
- **Evidence:** Webhook routes exist, but FRS requires HMAC validation, external-ID deduplication, controlled retries, failure logs and optional consented messaging. Notification routes only expose read/read-all operations; provider/template management is absent.
- **Risk:** Duplicate leads, silent integration failures and non-auditable outbound messaging.
- **Correction:** Verify HMAC/replay protection, persist attempts/status, queue retries with backoff, expose failure observability, and add provider/template controls.

### BE-F11 — Database integrity does not cover all business invariants

- **Severity:** HIGH; **Module:** Database
- **Evidence:** Schema has useful tenant indexes/FKs and `api_idempotency_keys`, but reservations, invoice uniqueness, payment allocation limits, effective-date overlaps and exclusive territory conflicts require application/transaction enforcement; no database-level proof/tests were found in this audit.
- **Risk:** Race conditions and duplicate financial/stock records under concurrent requests.
- **Correction:** Add unique constraints where deterministic, transactional locking, FK coverage review, check constraints compatible with deployment MySQL version, and repeatable concurrency tests.

## Security issues

1. Fixed role model cannot satisfy least privilege or configurable permission requirements (BE-F1).
2. Record-level scope enforcement is not evidenced for every endpoint (BE-F8).
3. Upload/KYC security policy is undefined (BE-F3): MIME/size/content checks, private storage, authorization and malware scanning must be specified.
4. Webhook replay/deduplication and provider secret rotation require verification (BE-F10).
5. Ensure production errors never expose SQL, tokens, passwords or internal stack traces; verify the shared error handler and logs in an integration environment.

## Transaction/concurrency risks

- Confirm/order reservation, FEFO allocation, invoice generation, dispatch deduction and payment allocation must be atomic.
- Idempotency infrastructure exists in schema, but every mutating route must actually apply it consistently.
- Invoice/dispatch/payment number generation must lock sequence rows and define rollback/gap policy.
- Prevent two confirmations from reserving the same final batch and prevent two invoices for one order.
- Prevent payment allocation exceeding invoice/party outstanding under concurrent requests.

## Frontend audit correlation

| Frontend finding/theme | Classification | Backend implication |
|---|---|---|
| Mock services for DCR, onboarding, inventory movement and integrations | BE/BOTH | Backend APIs and persistence are missing or incomplete; frontend mocks are not evidence of support. |
| Permission matrix and configurable roles | BE/BOTH | Backend must implement authoritative role/permission/scope enforcement; frontend hiding is insufficient. |
| GST split/tax invoice display | BOTH | Backend must return immutable tax breakdown; frontend can only render it. |
| Filter/sort/pagination UI | BOTH | Backend must support documented server-side query semantics; frontend maps controls to them. |
| Visual/layout/accessibility issues | FE | Do not assign to backend unless API data/validation causes the issue. |

## Missing APIs

Role/permission CRUD and assignment; DCR CRUD/submit/approve/missed/reporting; onboarding documents/KYC review; lead archive/convert/activity history; follow-up edit/missed/remarks; party restore/status; territory history/deactivate; draft order update/delete/submit; batch list/transfer/movement ledger/reservation release; invoice cancel/reissue/tax breakdown; dispatch update/cancel; payment allocation/update/cancel/reversal/PDC; portal team-user management; notification templates/provider/retry; report-specific schemas and missing report types.

## Business decisions required

1. Is franchise switching required, or is one franchise fixed per login?
2. Exact configurable role/scope model and whether legacy four roles remain system roles.
3. GST place-of-supply and CGST/SGST/IGST calculation/rounding rules.
4. Order lifecycle names and whether “submit” precedes “confirm”.
5. Scheme stacking/priority and price override approval policy.
6. Unassigned pincode behavior: block, review or admin override.
7. Payment allocation reversal/PDC policy and ageing authority.
8. DCR ownership: internal sales, distributor owner, team user, or all.
9. Invoice numbering gap policy after rollback/cancellation.

## Recommended fix priority

**P0:** BE-F1, BE-F2, BE-F4, BE-F5, BE-F6, BE-F7, BE-F8; define contracts and run authorization/concurrency tests.

**P1:** BE-F3, BE-F9, BE-F10; complete lifecycle APIs, onboarding/KYC and integration error contracts.

**P2:** Report parity, portal team management, audit query/detail improvements, configurable near-expiry buckets and operational dashboards.

**P3:** API documentation polish, cleanup and non-blocking UX/maintainability improvements.

## Final integration decision

1. **Ready now?** No; backend is not ready for full frontend integration.
2. **Immediate candidates:** health/readiness, Geo, authenticated identity after contract verification, and limited read-only product/catalogue flows.
3. **Fix first:** authorization model/scope, order-stock-billing-payment integrity, DCR/onboarding, and API contracts.
4. **Completely missing:** DCR API surface, configurable roles/permissions, payment allocation/reversal, several inventory and lifecycle operations, onboarding documents/KYC and portal team APIs.
5. **Unsupported workflows:** DCR, full onboarding, draft-to-submit order flow, FEFO reservation observability/release, invoice cancel/reissue, dispatch cancellation/update, payment allocation/reversal and team-user portal workflows.
6. **Backend-owned frontend findings:** permission enforcement, tax breakdown, server filtering/pagination, authoritative pricing/schemes/credit/territory/stock, auditability and security.
7. **Security blockers:** yes—authorization/scope enforcement and configurable permissions are unresolved.
8. **Data-integrity risks:** yes—stock, invoice numbering, payment allocation and concurrent mutations require proof.
9. **Decisions:** role model, franchise scope, tax rules, order states, schemes, pincode exceptions, payment/PDC and DCR ownership.
10. **Integration order:** finalize decisions → implement/test auth + permissions/scope → Geo/masters/products/pricing → parties/territories → orders + inventory transactions → billing/dispatch → payments/outstanding → onboarding/portal → DCR/webhooks/notifications → reports/audit → contract-driven frontend integration.

