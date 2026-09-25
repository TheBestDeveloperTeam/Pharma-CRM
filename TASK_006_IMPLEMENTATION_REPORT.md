# TASK-006 Implementation Report — Billing, GST and Invoices

## Executive Summary

TASK-006 is **PARTIALLY COMPLETE**. The source now provides the billing/invoice API boundary, authoritative eligibility checks, immutable invoice and tax-snapshot storage, atomic number infrastructure reuse, cancellation safeguards, authorization/scope/tenant guards, audit hooks, and OpenAPI documentation.

Invoice posting and reservation consumption are deliberately blocked by `GST_POLICY_PENDING`: approved sources do not resolve place of supply, CGST/SGST versus IGST selection, or rounding, and the current franchise model supplies no trusted seller state. No invoice number, invoice record, or stock mutation occurs before that policy is resolved.

## Requirements Reviewed

Reviewed the TASK-006 brief, frontend billing screens, invoice types/mocks/utilities, TASK-001–005 reports, API contract, decision verification, existing billing code, schema, and invoice routes.

## Frontend Billing/Invoice Screens Reviewed

`InvoicesPage.tsx`, `InvoiceFormPage.tsx`, `InvoiceDetailsPage.tsx`, `InvoiceCancelDialog.tsx`, `invoiceFormSchema.ts`, `domain.ts`, `mockInvoices.ts`, and `invoiceUtils.ts` were reviewed.

| Frontend field | Backend treatment |
|---|---|
| invoice number/date | Server-owned sequence/date; frontend number input needs an adapter |
| party/order/address | Immutable invoice/order/party snapshots |
| batches | Existing reservation allocations; no client batch override is trusted |
| rate/discount/GST/total | Server-authoritative snapshots |
| Generated/Cancelled | Adapter maps backend `POSTED`/`CANCELLED` |
| cancellation reason | Required server validation and audit |

## Existing Backend Reused

TASK-001 authorization/audit, TASK-002 envelope/idempotency middleware, TASK-005 confirmed orders, reservations, inventory movement foundation, and `SequenceService` are reused.

## Database Changes

`database/migrations/006_task006_billing_gst_invoices.sql` adds immutable invoice and line tax-snapshot columns, cancellation actor/time, tenant-unique one-invoice-per-order enforcement, invoice indexes, and billing permissions. It is additive; no reset is performed.

## Billing Eligibility

Generation locks the order and requires same-tenant `CONFIRMED` status, an active party, non-empty order lines, and no existing invoice. GST policy validation occurs before number allocation or reservation consumption.

## GST Architecture

`GstCalculator` is a centralized, paise-based calculator supporting taxable value, GST rate, CGST, SGST, IGST, total tax, and totals. Jurisdiction is an explicit input, not guessed in a controller.

## GST Decisions Still Pending

- trusted place-of-supply/seller-state source;
- CGST/SGST versus IGST selection policy;
- rounding policy;
- cancellation number/reissue policy.

## Invoice Numbering and Transaction Safety

`SequenceService` uses an atomic database counter. The intended invoice transaction locks the order, validates duplication and party/lines, resolves tax, allocates number, writes invoice/snapshots, consumes reservation, writes lifecycle/audit, then commits. Current unresolved GST policy stops before irreversible steps.

## Invoice Lifecycle and Cancellation Foundation

Supported invoice statuses are existing `POSTED` and `CANCELLED`. Cancellation requires `billing.cancel`, a reason, a posted unpaid invoice, and no existing dispatch. Payment/dispatch reversals are deferred rather than invented.

## Authorization / Scope / IDOR / Tenant Isolation

List, detail, order lookup, generation and cancellation require billing permissions. Invoice reads apply the billing scope using the linked order owner and party territory; direct references are franchise constrained and distributor party constrained.

## Audit and OpenAPI

Generation and cancellation invoke the existing audit service. OpenAPI describes invoice list/detail/by-order/generate/cancel and explicitly documents `GST_POLICY_PENDING` behavior.

## Tests Executed

| Command/check | Result |
|---|---|
| TASK-006 route/lifecycle static check | PASS |
| `git diff --check` | PASS (line-ending warnings only) |
| PHP syntax/tests | BLOCKED — PHP unavailable |
| Composer validation | BLOCKED — Composer unavailable |
| Migration/database tests | BLOCKED — MySQL/MariaDB unavailable |
| OpenAPI parser | NOT RUN — parser unavailable |

## Validation Matrix

| Check | Status | Evidence |
|---|---|---|
| Billing requirement mapping | PASS | Mapping above |
| Invoice requirement mapping | PASS | Frontend review above |
| Billing eligibility | PASS | Locked server validation |
| Order snapshot reuse | PASS | Locked order/line read path |
| Party snapshot | PASS | Invoice snapshot structure |
| Product/line snapshot | PASS | Invoice-line snapshot schema/repository |
| GST service | PASS | Central `GstCalculator` |
| Tax snapshot | PASS | Additive invoice/invoice-item fields |
| Server totals | PASS | Central paise calculator architecture |
| Invoice numbering | PASS | Existing atomic sequence service |
| Number concurrency safety | PASS | Atomic sequence SQL; runtime not run |
| Invoice generation | BLOCKED | GST policy intentionally pending |
| Transaction rollback | PASS | Transaction boundaries in service |
| Duplicate prevention | PASS | Lock plus unique invoice/order key |
| Idempotency | NOT RUN | Runtime retry unavailable |
| Order ↔ Invoice relationship | PASS | Tenant unique order relationship |
| Reservation integration | BLOCKED | Must wait for approved GST/consumption policy |
| Invoice lifecycle | PASS | POSTED/CANCELLED cancellation foundation |
| Cancellation foundation | PASS | Unpaid/undispatched guard and audit |
| Authorization | PASS | Controller checks |
| Scope | PASS | Linked-order scope check |
| IDOR | PASS | Tenant/distributor/scope checks |
| Tenant isolation | PASS | Franchise-bound repository queries |
| Audit | PASS | Generate/cancel hooks |
| OpenAPI | PASS | Routes and GST-pending behavior documented |
| Migration execution | BLOCKED | Database runtime unavailable |
| PHP syntax | BLOCKED | PHP unavailable |
| Backend tests | BLOCKED | PHP/runtime unavailable |
| TASK-001–005 recheck | BLOCKED | Prior runtime limitation persists |

## Remaining Issues

### Critical Source-Level Defects

NONE. The legacy invoice path that guessed statuses/tax/stock mutation was replaced by controlled policy blocking.

### Runtime Validation

BLOCKED — PHP, Composer, MySQL/MariaDB, and an OpenAPI parser are unavailable.

### Pending Business Decisions

The GST jurisdiction/place-of-supply, rounding, and cancellation-number/reissue decisions constrain actual invoice posting. Reservation consumption timing also remains constrained by that tax decision and future Dispatch ownership.

### Future Dependencies

Dispatch reversal and payment reversal/credit-note behavior are intentionally not implemented.

## Previous Task Status

- TASK-001: `PARTIALLY COMPLETE`
- TASK-002: `PARTIALLY COMPLETE`
- TASK-003: `PARTIALLY COMPLETE`
- TASK-004: `PARTIALLY COMPLETE`
- TASK-005: `PARTIALLY COMPLETE`

## TASK-006 FINAL STATUS

TASK-006 PARTIALLY COMPLETE
