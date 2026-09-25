# TASK-005 Implementation Report — Orders, Inventory, FEFO & Stock Reservation

## Executive Summary

TASK-005 is **PARTIALLY COMPLETE** at source level. The existing order and inventory foundations were extended for draft workflow, separate Submit/Confirm transitions, authoritative pricing and scheme revalidation, party/territory/credit validation, server totals, FEFO reservation, reservation release/consumption foundation, transaction boundaries, audit hooks, scope/tenant checks, and OpenAPI documentation.

Runtime/database/API verification could not be completed because PHP, Composer, MySQL/MariaDB, and an OpenAPI validator are unavailable in the execution environment. Billing, GST calculation policy, Invoice, Dispatch, Delivery, Payments, PDC, and frontend API integration were not implemented.

## Requirements Reviewed

Reviewed the approved FRS/project requirement material, frontend `CLAUDE.md`, frontend order/inventory screens and types, `REQUIREMENT_DECISION_VERIFICATION.md` where present, `FINAL_GAP_AND_IMPLEMENTATION_PLAN.md`, `Pharma-CRM/API_CONTRACT.md`, and TASK-001 through TASK-004 reports. Existing TASK-001 through TASK-004 implementation was reused.

## Frontend Order Functionality Reviewed

Reviewed `OrdersPage.tsx`, `OrderFormPage.tsx`, `OrderDetailsPage.tsx`, `orderFormSchema.ts`, `domain.ts`, and inventory batch/allocation screens.

The frontend supplies party, address, pincode, product, quantity, pricing display, scheme display, totals, credit display, remarks, and order actions. The current frontend status model is not identical to the approved backend lifecycle: it has Draft/Confirmed/Billed/Packed/Dispatched/Delivered/Cancelled and no Submitted/Processing. The backend therefore exposes separate Submit and Confirm operations and requires a frontend adapter before integration.

## Contract Mapping

| Frontend field | Approved/domain handling | Request/response handling |
|---|---|---|
| `partyId` / party reference | Same-tenant active Party | Request party reference; response includes party reference and validated Party |
| `items[].productId` | Same-tenant active Product | Request product reference and quantity; response returns persisted line items |
| `items[].quantity` | Positive server-validated quantity | Request quantity; server calculates line values |
| `rate`, `discount`, `lineTotal`, totals | Server-authoritative; frontend values are not trusted | Persisted line pricing snapshot and server totals returned |
| `schemeId`, `freeQty` | Server scheme resolution; stale scheme rejected | Scheme reference/free quantity returned from persisted result |
| address/pincode | TerritoryResolver result: MATCHED, UNASSIGNED, CONFLICT | Territory status returned; unresolved policies return controlled validation errors |
| credit display | PartyCreditService factual state | Credit facts are calculated server-side; breach policy remains pending |
| status/actions | DRAFT → SUBMITTED → CONFIRMED → PROCESSING; later statuses are future-module owned | Dedicated submit/confirm/cancel endpoints |
| order history | Immutable lifecycle history | Detail response includes status history |

`client_order_ref` remains an API/idempotency/client-correlation field where required by the canonical contract; the current frontend form needs an adapter for that field and for the separate Submit/Confirm lifecycle.

## Existing Backend Reused

Reused TASK-001 authorization and idempotency infrastructure, TASK-002 API/error conventions, TASK-003 product/price/scheme resolvers, and TASK-004 PartyCreditService, TerritoryResolver, Party/Territory repositories, audit, tenant, and scope foundations.

## Database Changes

Added `database/migrations/005_task005_orders_inventory.sql`:

- normalizes the order status enum to the approved TASK-005 statuses, including `PROCESSING`, without adding Billing/Dispatch workflow states;
- adds `UNASSIGNED` and `CONFLICT` territory states;
- adds billing address and pricing-tier references needed by the order contract;
- registers order confirm and inventory reserve/release permissions.

Existing orders, order lines, status history, inventory batches, movement ledger, reservations, versions, indexes, and foreign-key structures were reused. No database reset was performed.

## Order APIs

Implemented/extended:

- order list and detail;
- draft create, update, and delete;
- submit;
- confirm;
- cancel;
- order history in detail;
- batch listing and batch detail;
- reservation lookup, release, and consumption foundation.

All mutation controllers enforce server-side permissions and use existing idempotency middleware where routed by the application.

## Order Lifecycle

Implemented transitions are `DRAFT → SUBMITTED → CONFIRMED → PROCESSING`, plus permitted cancellation. Submit and Confirm remain separate operations. Submit/Confirm actor authority is kept in configurable permission infrastructure because the exact actor rule is not authoritatively resolved.

DISPATCHED and DELIVERED are retained as canonical/future states; no Dispatch or Delivery workflow was added.

## Pricing Integration

Order creation and draft update use TASK-003 centralized price resolution. Frontend MRP, PTS, net rate, discount, and totals are not trusted. Confirm/Submit re-resolve the current price and reject stale order pricing. Applied rate, source, price reference, and override-compatible snapshot fields are persisted on order lines.

## Scheme Integration

TASK-003 scheme calculation is used for draft pricing and authoritative transition validation. Eligibility, quantity, effective date, free quantity, and scheme references are server-derived. Existing ambiguous stacking behavior is not reinterpreted; stale or conflicting scheme state is surfaced rather than silently trusting frontend output. Final stacking/overlap semantics remain a narrow deferred decision where the approved requirements do not resolve them.

## Party Integration

Order transitions verify same-tenant Party existence and active status through the existing Party repository/service boundary. Party pricing tier and commercial data are used as authoritative resolution context.

## Territory Integration

Orders use the centralized TerritoryResolver and preserve MATCHED/UNASSIGNED/CONFLICT outcomes. UNASSIGNED is returned as a controlled pending-policy validation result; CONFLICT is rejected explicitly. No silent automatic allow/block policy was invented.

## Credit Integration

PartyCreditService calculates limit, outstanding, available credit, proposed exposure, and breach facts. A breach returns a controlled pending-policy result; no hard block, warning-only, approval workflow, or automatic override policy was invented.

## Server-Authoritative Totals

Line subtotal, discount baseline, GST amount currently represented by existing order pricing, and grand total are calculated server-side in minor units before persistence. This is not a final Billing/GST rules implementation; future billing owns final GST/invoice behavior.

## Inventory Model

Existing inventory batches support product, batch, manufacture/expiry dates, received/on-hand/reserved/damaged quantities, location code, status, version, tenant, and franchise references. Batch listing now exposes filtered available stock without adding speculative warehouse complexity.

## FEFO

`FefoAllocator` is the domain service for First-Expiry-First-Out allocation. It excludes expired, inactive/non-saleable, and unavailable stock, applies minimum shelf-life input, sorts deterministically, and locks selected rows with optimistic version protection.

## Stock Reservation

Confirm allocates reservations across one or more batches and records reservation movements. Release returns reserved quantity and marks active reservations released. Consumption foundation decrements on-hand and reserved quantities, marks reservations consumed, and records SALE movements. Reservation lookup/history endpoints were added.

Existing backend behavior reserves on Confirm; this was retained as the existing order/inventory convention. If the approved requirements later explicitly choose Submit timing, the trigger remains isolated in the lifecycle service.

## Concurrency Protection

Confirm locks the order and executes validation, FEFO allocation, reservation creation, status transition, and history in one transaction. Batch quantity changes use row locking/conditional version updates. Release and consumption also verify optimistic updates and fail on concurrent stock changes.

## Stock Movement Ledger

Existing immutable movement infrastructure is reused for reservation, release, and SALE/consumption foundation events. No unrelated transfer workflow was introduced.

## Authorization / Scope / Tenant Isolation

Order view/create/edit-draft/delete-draft/submit/confirm/cancel and inventory view/create/adjust/reserve/release actions are checked server-side. Order list/detail applies effective scope and direct references are scope-checked. Repository queries carry franchise/tenant boundaries, and order/product/party/price/scheme/territory/inventory/batch/reservation references are resolved within the current tenant boundary.

## Audit

Existing audit infrastructure is used for draft create/update/delete, submit, confirm, cancel, batch mutations, reservation release/consumption, and inventory actions. Lifecycle history records from status, to status, actor, timestamp, and reason.

## OpenAPI

`public/api-docs/openapi.yaml` was updated for order list/detail, draft update/delete, Submit, Confirm, Cancel, batch list/detail, batch mutation routes, and reservation routes. Billing, Invoice, Dispatch, Delivery, Payment, PDC, and unrelated future endpoints were not documented as completed.

An OpenAPI parser was not available, so parser validation is NOT RUN.

## Frontend Compatibility Matrix

| Area | Status | Evidence/notes |
|---|---|---|
| Order list/detail | READY_WITH_FRONTEND_ADAPTER | Routes and DTO shape exist; frontend status naming differs |
| Create/edit draft | READY_WITH_FRONTEND_ADAPTER | Backend supports draft; frontend currently models submit differently |
| Submit | READY_WITH_FRONTEND_ADAPTER | Dedicated endpoint exists |
| Confirm | READY_WITH_FRONTEND_ADAPTER | Dedicated endpoint and reservation path exist |
| Pricing/scheme display | READY_WITH_FRONTEND_ADAPTER | Server values must replace mock/frontend calculations |
| Party/product selection | READY_WITH_FRONTEND_ADAPTER | Existing TASK-003/TASK-004 APIs reused |
| Territory/credit display | READY_WITH_FRONTEND_ADAPTER | Controlled pending-policy outcomes must be mapped |
| Inventory availability | READY_WITH_FRONTEND_ADAPTER | Batch/availability routes exist; FEFO remains backend-owned |

No major frontend functionality was silently changed and frontend API integration was not started.

## Deferred Narrow Decisions

- exact Submit versus Confirm actor authority;
- scheme stacking/overlap semantics where multiple eligible schemes remain ambiguous;
- UNASSIGNED territory allow/block/override behavior;
- credit breach action;
- cancellation cutoff after future Billing/Dispatch ownership;
- final reservation timing if approved requirements differ from the existing Confirm convention.

## Tests Executed

Commands and actual results:

| Command | Result |
|---|---|
| `Get-Command php,composer,mysql,mariadb` | NOT RUN: no supported executable was available in the environment |
| `php -v` | BLOCKED: PHP unavailable |
| `composer validate` | BLOCKED: Composer unavailable |
| `mysql --version` / `mariadb --version` | BLOCKED: database client unavailable |
| OpenAPI parser validation | NOT RUN: no validator/package available |
| `npm.cmd run build` in `pharma-sales-crm` | BLOCKED: TypeScript could not write incremental metadata in `node_modules/.tmp` (`EPERM`) |
| `git -C Pharma-CRM diff --check` | PASS: no whitespace errors; Git emitted only existing line-ending warnings |
| PowerShell duplicate OpenAPI path-key scan | PASS: duplicate TASK-005 path keys removed |
| Frontend source inspection with `rg` / file review | PASS: Order and Inventory screens/types/forms reviewed |

Focused runtime order/inventory tests, migration execution, and end-to-end API tests were not run because the required runtime/database toolchain is unavailable. Static inspection is not treated as runtime test PASS.

## Validation Matrix

| Check | Status | Evidence |
|---|---|---|
| Frontend Order mapping | PASS | Source mapping documented above |
| Draft create/edit/delete | PASS | Controller, service, repository paths implemented |
| Submit | PASS | Dedicated transition/service path |
| Confirm | PASS | Transactional validation, allocation, reservation, transition |
| Lifecycle validation | PASS | State machine includes Submit/Confirm/Processing boundaries |
| Server pricing | PASS | Central price resolver used |
| Price snapshot | PASS | Order line pricing fields persisted and revalidated |
| Price override security | PASS | Existing TASK-003 resolver/authorization boundary reused |
| Scheme resolution | PASS | Existing calculator integrated and stale state checked |
| Party validation | PASS | Active same-tenant Party checks |
| Territory resolution | PASS | Central TerritoryResolver integrated |
| Credit check | PASS | PartyCreditService integrated |
| Server totals | PASS | Service calculates persisted totals |
| Order history | PASS | Status history read/write paths |
| Cancellation | PASS | Permission, state validation, transactional reservation release |
| Inventory model | PASS | Existing batch model reused and list added |
| FEFO | PASS | Domain allocator with expiry ordering/locking |
| Reservation | PASS | Multi-batch reservation creation |
| Reservation release | PASS | Release path with optimistic protection |
| Concurrency safety | PASS | Transaction, row locks, version checks in source |
| Movement ledger | PASS | Reserve/release/SALE movement writes |
| Transactions | PASS | Confirm and cancellation transaction boundaries |
| Idempotency | NOT RUN | Runtime retry verification unavailable |
| Authorization | PASS | Controller permission checks |
| Data scope | PASS | List/detail scope checks |
| IDOR | PASS | Direct order/batch reference scope and tenant checks |
| Tenant isolation | PASS | Franchise/tenant repository constraints |
| Audit | PASS | Existing audit service calls |
| OpenAPI | NOT RUN | Parser unavailable; document updated |
| Frontend compatibility | PASS | Compatibility matrix completed; adapter required |
| Migration execution | BLOCKED | MySQL/MariaDB unavailable |
| PHP syntax | BLOCKED | PHP unavailable |
| Backend tests | BLOCKED | PHP/runtime unavailable |
| TASK-001 recheck | BLOCKED | Runtime toolchain unavailable; prior status retained |
| TASK-002 recheck | BLOCKED | Runtime/OpenAPI toolchain unavailable; prior status retained |
| TASK-003 recheck | BLOCKED | Runtime/database toolchain unavailable; prior status retained |
| TASK-004 recheck | BLOCKED | Runtime/database toolchain unavailable; prior status retained |

## Remaining Issues

### Implementation defects

- Focused executable TASK-005 tests still need to be run/added in the PHP test environment.
- The frontend's mock lifecycle includes Billing/Dispatch-only states and needs an adapter for the approved `SUBMITTED` and `PROCESSING` states; this is recorded in `FRONTEND_REQUIREMENT_MISMATCHES.md`.
- Frontend adapter is required for Submitted status, separate Submit/Confirm actions, canonical field names, and server totals.

### Runtime environment limitations

- PHP, Composer, MySQL/MariaDB, and an OpenAPI validator are unavailable.
- Consequently migration execution, PHP syntax checks, integration tests, concurrency tests, and API contract parsing remain unverified.

### Unresolved business decisions

Only the narrow decisions listed in “Deferred Narrow Decisions” remain policy-dependent. The implementation returns controlled errors for unsafe unresolved cases rather than silently selecting policy.

### Future Billing/Dispatch/Payment dependency

Final GST/invoice rules, invoice lifecycle, dispatch/delivery transitions, payment allocation/reversal/PDC, and post-invoice cancellation restrictions remain outside TASK-005.

## Previous Task Status

- TASK-001: `PARTIALLY COMPLETE`
- TASK-002: `PARTIALLY COMPLETE`
- TASK-003: `PARTIALLY COMPLETE`
- TASK-004: `PARTIALLY COMPLETE`

## TASK-005 FINAL STATUS

TASK-005 PARTIALLY COMPLETE
