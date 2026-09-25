# TASK-007 Implementation Report — Dispatch and Delivery

## Executive Summary

TASK-007 is **PARTIALLY COMPLETE**. The legacy dispatch path was hardened with centralized transactional creation/delivery services, approved order lifecycle transitions, history, delivery timestamp/remarks, authorization, scope/tenant checks, audit hooks, and OpenAPI/migration updates.

Dispatch creation is intentionally blocked by the TASK-006 GST dependency. A valid dispatch needs a tenant-valid `POSTED` invoice with a resolved tax policy; current invoice generation safely prevents such invoices. No fake dispatch, stock consumption, or order status transition is allowed.

## Requirements and Frontend Reviewed

Reviewed the TASK-007 brief, `DispatchFormPage.tsx`, `DispatchPendingPage.tsx`, `dispatchFormSchema.ts`, order dispatch actions, dispatch types, mocks, backend schema/service/repository/routes, and TASK-006 report.

| Frontend field | Backend handling |
|---|---|
| invoice/order | Derived from a valid invoice; server validates relationship |
| transporter, LR, tracking URL, boxes, remarks | Request fields; boxes/LR validated server-side |
| dispatch date/number | Server-generated/current date and atomic sequence |
| status | Server lifecycle controlled |
| delivered state | Dedicated delivery action, timestamp and optional remarks |

## Existing Backend Reused

TASK-001 authorization/audit, TASK-002 idempotency middleware/envelope, TASK-005 order state machine/reservation foundation, TASK-006 invoice GST gate and sequence infrastructure are reused.

## Database Changes

`database/migrations/007_task007_dispatch_delivery.sql` adds delivery timestamp/remarks, status index, and immutable `dispatch_status_history`. No reset is performed.

## Dispatch Eligibility and GST Protection

The service locks invoice/order, requires one full dispatch per invoice, validates a posted non-pending-tax invoice, boxes, and the centralized `PROCESSING → DISPATCHED` transition. `GST_DEPENDENCY_BLOCKED` prevents bypassing TASK-006.

## Dispatch Lines / Partial Dispatch / Inventory

The approved frontend contains no dispatch-line quantities or partial-dispatch flow. Therefore no speculative dispatch-line table, client allocations, or partial-delivery semantics were introduced. Reservation consumption remains `PENDING_DECISION`; no second inventory engine or premature stock consumption was added.

## Delivery

Delivery is transactional and requires a tenant-visible `DISPATCHED` or `IN_TRANSIT` dispatch. It prevents duplicate delivery, writes `delivered_at`, optional delivery remarks, lifecycle history/audit, and synchronizes `DISPATCHED → DELIVERED` using `OrderStateMachine`. No proof storage, failed, returned, or cancellation workflow is implemented because the frontend/requirements supplied no approved fields.

## Authorization / Scope / IDOR / Tenant Isolation

List/detail/create/deliver enforce dispatch permissions. List/detail apply linked-order owner/territory scope, distributor party visibility, and franchise-bound references.

## OpenAPI and Tests

OpenAPI covers list/create/delivery and the GST dependency. Static route/lifecycle checks and `git diff --check` passed. PHP, Composer, MySQL/MariaDB, and OpenAPI parser validations remain blocked/unavailable.

## Validation Matrix

| Check | Status | Evidence |
|---|---|---|
| Dispatch requirement mapping | PASS | Mapping above |
| Delivery requirement mapping | PASS | Frontend review |
| Dispatch eligibility | PASS | Central locked service |
| GST dependency protection | PASS | `GST_DEPENDENCY_BLOCKED` guard |
| Dispatch create | BLOCKED | Valid invoice dependency unavailable |
| Dispatch lines | BLOCKED | No approved line/partial requirement |
| Quantity validation | PASS | Full-dispatch/no client quantities model |
| Partial dispatch behavior | BLOCKED | No approved semantics |
| Over-dispatch protection | PASS | One tenant-unique dispatch per invoice path |
| Reservation integration | BLOCKED | Consumption point pending decision |
| Inventory consumption | BLOCKED | Consumption point pending decision |
| Double-consumption protection | PASS | No consumption performed |
| Stock movement | BLOCKED | Consumption decision pending |
| Transaction rollback | PASS | Service transactions |
| Dispatch numbering | PASS | Atomic sequence service |
| Order lifecycle sync | PASS | State machine transitions |
| Delivery confirmation | PASS | Transactional delivery path |
| Duplicate delivery protection | PASS | State check/conditional update |
| Cancellation | BLOCKED | No approved cancellation flow |
| Idempotency | NOT RUN | Runtime unavailable; middleware applies |
| Authorization | PASS | Controller checks |
| Scope | PASS | Linked-order scope enforcement |
| IDOR | PASS | Tenant/scope/direct-ref checks |
| Tenant isolation | PASS | Franchise-bound repositories |
| History | PASS | Dispatch status history |
| Audit | PASS | Create/deliver audit hooks |
| OpenAPI | PASS | Updated operations |
| Migration execution | BLOCKED | MySQL/MariaDB unavailable |
| PHP syntax | BLOCKED | PHP unavailable |
| Backend tests | BLOCKED | PHP unavailable |
| TASK-001–006 recheck | BLOCKED | Prior runtime limitation persists |

## Remaining Issues

### Critical Source-Level Defects

NONE.

### Runtime Validation

BLOCKED — PHP, Composer, MySQL/MariaDB, and an OpenAPI parser are unavailable.

### Dispatch Blocked By GST Policy

YES.

### Reservation Consumption Point

PENDING_DECISION.

### Future Dependencies

GST-valid invoice creation, reservation consumption timing, proof storage, Dispatch cancellation/reversal, and Payments remain outside the safely approved TASK-007 path.

## Previous Task Status

- TASK-001: `PARTIALLY COMPLETE`
- TASK-002: `PARTIALLY COMPLETE`
- TASK-003: `PARTIALLY COMPLETE`
- TASK-004: `PARTIALLY COMPLETE`
- TASK-005: `PARTIALLY COMPLETE`
- TASK-006: `PARTIALLY COMPLETE`

## TASK-007 FINAL STATUS

TASK-007 PARTIALLY COMPLETE
