# TASK-010 Final Backend Audit

## 1. Executive Summary

The backend has source foundations through TASK-009, but is not ready for broad frontend API integration. TASK-010 fixed three confirmed defects: webhook encryption no longer falls back to a source hard-coded key, webhook timestamps are mandatory, and report route handlers now read the route parameter correctly. A tenant/scope-protected audit list API was added.

## 2. Backend Architecture Status

The PHP application uses tenant context, normalized TASK-001 permissions/scopes, repositories/domain services, additive migrations, canonical JSON responses, audit records, and public references. Runtime verification is blocked because PHP, Composer, MySQL/MariaDB, and an OpenAPI validator are unavailable.

## 3. Complete Requirement Coverage Matrix

| Module | Backend | API | DB | Auth | OpenAPI | Status |
|---|---|---|---|---|---|---|
| Auth, users, roles, masters, parties, territory | Present | Present | Present | Scoped | Partial | PARTIAL |
| Orders, inventory, reservations | Present | Present | Present | Scoped | Partial | PARTIAL |
| Billing, invoices, dispatch | Safe policy gates | Present | Present | Scoped | Partial | BLOCKED_BY_DECISION |
| Payments, allocation, PDC | Foundation | Present | Present | Scoped | Partial | PARTIAL |
| Onboarding, KYC, DCR | Present | Present | Present | Scoped | Partial | PARTIAL |
| Dashboard | No confirmed API DTO | Mock-only frontend | N/A | N/A | N/A | MISSING |
| Reports | Legacy aggregate reports | Present | Existing | ALL-only fail-closed | Partial | PARTIAL |
| Audit | Append-only writes and list API | Present | Present | Scoped | Updated | PARTIAL |
| Webhooks | HMAC intake/job foundation | Present | Present | Source configuration | Partial | PARTIAL |

## 4. Dashboard Implementation

Frontend widgets are mock-backed and compute lead, follow-up, order, outstanding, near-expiry, and sales-team values locally. There is no approved backend dashboard DTO or query contract, so no guessed dashboard API was added.

## 5. Reports Implementation

The existing report service supports legacy sales, order, invoice, payment, outstanding, inventory, lead, scheme, audit, and activity reports. The frontend defines a different mock-only 16-report catalogue. Reports now require `reports.view` and fail closed unless the reports scope is ALL; module-level scoped SQL needs a confirmed adapter contract.

## 6. Audit Implementation

Successful business mutations use the append-only audit service. `GET /api/v1/admin/audit` was added with tenant predicates, pagination, filters, OWN/TEAM filtering, and fail-closed TERRITORY/NONE behavior. It returns metadata rather than before/after JSON to minimize sensitive disclosure.

## 7. Integrations / Webhooks

Webhook ingestion uses a generated source secret, HMAC, mandatory timestamp replay window, duplicate event protection, and queued processing. Provider-specific payload mapping/credentials remain `CONFIGURATION_REQUIRED`. The unsafe fallback encryption key was removed.

## 8. Notifications

Existing in-app notification list/read endpoints remain tenant/user constrained. Provider-backed WhatsApp delivery is not activated because no provider configuration is approved.

## 9. Authorization Audit

TASK-001 permission checks are used by current-task audit and report paths. Reports were corrected from role-name-only authorization to `reports.view`; non-ALL report scope is denied rather than aggregating data outside scope.

## 10. Scope Audit

TASK-009A enforces ALL/OWN/TEAM/TERRITORY/NONE on onboarding/KYC/DCR. Audit list enforces ALL/OWN/TEAM and denies unresolvable TERRITORY. Dashboard/report operational scope adapters remain incomplete, so they are not frontend-ready.

## 11. Tenant Isolation Audit

New audit list, webhook source/event use, and TASK-009 records all use franchise predicates. Existing legacy/untested routes need runtime and complete route-by-route review.

## 12. IDOR Audit

TASK-009A direct references use the same gate as lists. Audit list does not expose a direct detail endpoint. Report access remains tenant-bound and ALL-only.

## 13. API Contract Audit

Webhook oversized payload now uses the canonical error envelope. Reports route signatures were corrected. CSV export remains a deliberate non-JSON file response but has not been runtime-tested.

## 14. OpenAPI Audit

Added audit-list documentation. Existing OpenAPI is partial relative to the route table and needs a validator/runtime parity pass.

## 15. Database Integrity Audit

TASK-009 tables have tenant keys, public refs, indexes, histories, and duplicate guards. Migration execution was not possible. Existing migrations were not destructively changed.

## 16. Financial Precision Audit

Billing/GST policy is deliberately fail-closed. Legacy reports use SQL decimals but the party-ledger report uses PHP float arithmetic; it is not financial-authoritative and must not be used as a posting calculation.

## 17. Transaction / Concurrency / Idempotency Audit

Onboarding conversion, allocation, reservations, and webhook event insertion have source transaction/idempotency foundations. Invoice/dispatch remain dependent on unresolved GST policy; report generation is read-only.

## 20. Security / Secret Audit

Fixed `WebhookService` fallback encryption-key exposure. Remaining legacy TODO markers exist in old non-v1 user/role controllers and require consolidation before claiming backend-wide completion.

## 21. Unresolved Business Decision Register

| Decision | Classification | Impact |
|---|---|---|
| GST place of supply, tax split, rounding, invoice cancellation | BLOCKS_CURRENT_FLOW | Invoice/dispatch cannot proceed |
| Reservation consumption point | SAFE_FAIL_CLOSED | Dispatch behavior remains gated |
| Automatic allocation, reversal, PDC realization/bounce, ageing authority | BLOCKS_CURRENT_FLOW | Payment lifecycle is incomplete |
| KYC retention/privacy | FUTURE_FEATURE_ONLY | No deletion policy invented |
| DCR offline | FUTURE_FEATURE_ONLY | Online DCR remains available |
| Webhook provider payload/configuration | CONFIGURATION_REQUIRED | Generic HMAC endpoint only |

## 22. Frontend API Readiness Matrix

| Frontend Module | Required API | Backend Available | Contract Ready | Blocker |
|---|---|---|---|---|
| Auth/masters/parties/leads/orders | CRUD APIs | Mostly | PARTIAL | mock DTO adapters and route parity |
| Billing/dispatch/payments | lifecycle APIs | Foundation | NO | pending GST/payment decisions |
| Onboarding/KYC/DCR | lifecycle APIs | Yes | PARTIAL | frontend field/reference adapter |
| Dashboard/reports/audit | aggregate APIs | audit only | NO | dashboard/report DTOs remain mock-only |

## 23. Active Mock Inventory

`mockCrmService.ts`, `portalService.ts`, dashboard widgets, report views, audit page, and portal DCR views remain mock-backed. No frontend mock was replaced. Backend adapters are required before integration.

## 24. Frontend Mismatch Register

Existing order, invoice, and dispatch mismatches remain `BACKEND_ADAPTER_REQUIRED`; no frontend changes were made.

## 25. Runtime Validation

`BLOCKED`: PHP, Composer, MySQL/MariaDB, and OpenAPI validation tooling were unavailable.

## 26. Validation Matrix

| Area | Status | Evidence |
|---|---|---|
| Auth/permissions/scope | PARTIAL | TASK-009A plus report/audit fail-closed changes |
| Dashboard/Reports | PARTIAL | dashboard DTO missing; reports ALL-only |
| Audit/Webhooks | PARTIAL | audit route added; provider config blocked |
| Tenant/IDOR/API/OpenAPI | PARTIAL | static review only |
| Financial/DB/transactions/concurrency/idempotency | PARTIAL | source-only review; policy gates remain |
| Frontend readiness | PARTIAL | mock inventory and mismatches documented |
| Runtime validation | BLOCKED | executable/tooling unavailable |

## 27. Remaining Blockers

No broad frontend integration until dashboard/report adapters, OpenAPI parity, and the GST/payment decisions are completed and runtime-tested.

## 28. Final Recommendation for API Integration

Do not begin broad frontend API integration. A limited adapter design/review may start for stable CRUD modules only after runtime tooling is restored; billing, dispatch, payments, dashboard, and reports remain unsuitable.

## TASK-010A Follow-up

TASK-010A.1 added `ScopedAnalyticsService`, dashboard and report endpoints, and 15 registered non-financial report mappings. `payment-outstanding` remains blocked by the ageing/payment decision. Pagination/safe sort and full frontend DTO/OpenAPI alignment remain incomplete.

TASK-010A.3 replaces the generic mappings with explicit frontend DTO aliases, including dashboard chart series, response duration/SLA result, rep productivity, territory state, and MRP-based stock value. The dashboard/report OpenAPI schemas now enumerate those response fields. Payment/outstanding remains the sole business-decision-blocked report.

TASK-011D.4 finalizes the production invoice GST path: state-based jurisdiction, Product GST authority, component-rate/amount snapshots, immutable header totals, and static production-call-path verification. Invoice reservations remain untouched until Dispatch. PHP, Composer, and MySQL/MariaDB are unavailable for runtime validation.

## Backend closure addendum

The financial/dashboard/report closure is now implemented at source level. PDC list, count, direct detail, registration, realization, bounce, and cancellation use tenant predicates plus `PartyScopePredicate`; mutation targets are selected `FOR UPDATE`. Payment allocation is party-scoped, tenant-scoped, audited, and transaction-locked with partial/multi-invoice/multi-payment support and deterministic over-allocation rejection. Payment reversal remains the reverse-allocation authority.

`OutstandingService` supplies the only invoice-wise balance calculation for outstanding API, dashboard, and `payment-outstanding`. It respects active allocations, reversal state, fully paid invoice exclusion, due-date-only ageing, ALL/OWN/TEAM/TERRITORY/NONE, pagination, and a sorting allow-list. `payment-outstanding` is the sixteenth analytics report, returns the frontend row fields `invoiceNumber`, `partyName`, `dueDate`, `balance`, `bucket`, and `status`, and is protected by `payments.view`.

Credit exposure uses authoritative opening balance, posted invoice open balance, uninvoiced confirmed/reserved orders, and realized unallocated advances without invoice/order double counting. The actual order-confirmation transaction locks and rechecks party exposure, blocking only a strict limit breach. Dashboard financial output is sourced from the same service and is hidden from callers without `payments.view`.

OpenAPI now documents outstanding and payment-outstanding DTOs, ageing filters/sort fields, and the order-confirmation credit-limit response. Source contracts cover PDC scope, allocation/invariants, outstanding/ageing/report/dashboard wiring, credit enforcement, and route/OpenAPI DTO parity. Runtime validation remains BLOCKED only because PHP, Composer, and MySQL/MariaDB executables are absent in this environment.
