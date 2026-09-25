# TASK-008 Implementation Report — Payments, Allocation, Outstanding, Ageing and PDC

## Executive Summary

TASK-008 is **PARTIALLY COMPLETE**. Payment create/list/detail foundations were retained and manual, transactional, money-safe payment-to-invoice allocation was added. The service prevents cross-party and concurrent over-allocation. Automatic allocation, payment reversal, PDC realization, ageing authority, and meaningful outstanding balances remain constrained by the TASK-006 GST invoice gate and unresolved business policy.

## Requirements and Frontend Reviewed

Reviewed payment create/detail/list, allocation/reversal UI, outstanding/PDC tabs, `paymentFormSchema.ts`, payment types, mocks, and `outstandingUtils.ts`.

| Frontend function | Backend treatment |
|---|---|
| party/date/amount/mode/reference | Server validates payment creation |
| manual allocation | Explicit allocation endpoint; partial and multi-invoice requests supported |
| automatic allocation | Not performed; policy unresolved |
| payment edit/cancel/reversal | Unsafe changes not exposed |
| PDC fields/tracking | Additive metadata foundation only |
| ageing | Deferred pending authoritative invoice/outstanding availability |

## Existing Backend Reused

TASK-001 authorization/audit, TASK-002 idempotency middleware, TASK-004 party validation/credit facts, TASK-006 invoices, `Money`, and transaction infrastructure are reused.

## Database Changes

`database/migrations/008_task008_payments_allocation.sql` adds PDC metadata, financial indexes, allocation reversal metadata, and payment permissions without resetting data.

## Payment APIs and Allocation Engine

Payment list/detail/create are permission guarded. `AllocationService` locks payment and invoice rows, uses paise arithmetic, verifies same tenant/party/state, validates payment availability and invoice outstanding, inserts immutable allocation history, and updates derived balances in the same transaction.

## Outstanding, Credit, PDC and Ageing

Outstanding remains derived from posted invoice `grand_total - paid_total`; cancelled invoices are excluded by allocation validation. Party credit continues through `PartyCreditService`; advance/opening/breach policy is not redefined. PDC metadata is stored, but realization/bounce/cancel transitions are intentionally absent. The frontend’s due-date buckets are reviewed but no backend ageing authority is invented.

## Safety and Policy

Manual allocation supports partial/multi-invoice allocation through separate explicit calls. FIFO/automatic allocation is `PENDING_DECISION`. Payment edits/cancellation/reversal are withheld because reversal policy is unresolved. Valid invoice creation remains blocked by TASK-006 GST policy, so live allocation is correspondingly constrained.

## OpenAPI and Validation

OpenAPI documents the manual allocation endpoint and idempotency. Static route/allocation checks and `git diff --check` passed. PHP, Composer, MySQL/MariaDB and parser validation are blocked/unavailable.

## Validation Matrix

| Check | Status | Evidence |
|---|---|---|
| Payment requirement mapping | PASS | Frontend mapping above |
| Payment create/detail/list | PASS | Existing service/controller plus permission checks |
| Payment modes | PASS | Existing approved schema enum reused |
| Allocation service | PASS | Central locked `AllocationService` |
| Partial allocation | PASS | Explicit decimal amount validation |
| Multi-invoice policy | PASS | Multiple explicit manual allocations supported |
| Cross-party protection | PASS | Locked party comparison |
| Over-allocation protection | PASS | Paise balance checks |
| Concurrency | PASS | Transaction and `FOR UPDATE` locks |
| Allocation history | PASS | Immutable allocation row |
| Invoice/party outstanding | BLOCKED | GST-valid invoices unavailable |
| Credit integration | PASS | Existing PartyCreditService reused |
| Advance handling | BLOCKED | Not reclassified as credit reduction pending policy |
| Payment edit safety | PASS | Unsafe edit not exposed |
| Reversal/cancel foundation | BLOCKED | Policy unresolved |
| PDC | PASS | Metadata foundation |
| PDC realization | BLOCKED | No approved policy |
| Ageing | BLOCKED | Authority/basis not frozen |
| Money precision | PASS | `Money` paise arithmetic |
| Transaction rollback | PASS | Allocation transaction |
| Idempotency | NOT RUN | Middleware exists; runtime unavailable |
| Authorization | PASS | Controller permission checks |
| Scope/IDOR | BLOCKED | Payment list/detail legacy scope needs runtime/complete adapter verification |
| Tenant isolation | PASS | Franchise-bound queries/locks |
| Audit | PASS | Payment create/allocation hooks |
| OpenAPI | PASS | Allocation contract added |
| Migration execution/PHP/tests | BLOCKED | Toolchain unavailable |
| TASK-001–007 recheck | BLOCKED | Prior runtime limitation persists |

## Remaining Issues

### Critical Source-Level Defects

NONE in the new manual allocation path.

### Runtime Validation

BLOCKED.

### Allocation Policy

PENDING_DECISION — manual allocation is supported; automatic FIFO/oldest-first is not assumed.

### Payment Reversal Policy

PENDING_DECISION.

### PDC Realization Policy

PENDING_DECISION.

### Ageing Authority

PENDING_DECISION.

### GST/Invoice Dependency

TASK-006 blocks valid invoice creation until GST jurisdiction/rounding policy is configured; live allocation/outstanding is therefore constrained.

## Previous Task Status

- TASK-001 through TASK-007: `PARTIALLY COMPLETE`

## TASK-008 FINAL STATUS

TASK-008 PARTIALLY COMPLETE
