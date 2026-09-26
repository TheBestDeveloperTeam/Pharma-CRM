# TASK-011 Invoice/GST closure

The production invoice route calls `BillingService`, which validates franchise supplier state and normalized shipping-pincode place of supply before allocating an invoice number. It derives jurisdiction from normalized state references, uses Product GST through `GstCalculator`, and persists immutable invoice/header and line snapshots through `SqlInvoiceRepository`.

Invoice lines retain taxable amount, GST/component rates, component amounts, total tax, HSN/product/batch snapshots, and line total. Reservations are not consumed during invoicing; Dispatch is the consumption point. Cancellation retains the invoice, number, lines, tax history, reason, actor, and timestamp.

`tests/contract/verify_invoice_gst_contract.php` verifies the production call path and snapshot mappings. Runtime validation is blocked because PHP, Composer, and MySQL/MariaDB are unavailable.

## Financial core closure

PDC registration and every lifecycle mutation now select the target party/PDC through `PartyScopePredicate` inside the same transaction that takes its row lock. ALL, OWN, TEAM, TERRITORY and NONE are enforced by the shared predicate; distributor access is constrained to its party. A pending, bounced, or cancelled PDC has no payment or outstanding effect. Realization creates the only linked PDC payment and is protected from duplicate realization by the locked PDC state and unique payment link.

Manual allocation is tenant- and party-scope-protected before its payment and invoice rows are locked. It supports partial allocation, many invoices per payment, and many payments per invoice, while rejecting cross-party references, reversed/cancelled payment states, and either payment or invoice over-allocation. The controller records an allocation audit event. Existing reversal remains the authoritative reversal path, reversing active allocations and restoring invoice paid totals.

`OutstandingService` is the single invoice-open-balance read authority for the protected `/api/v1/admin/outstanding` API, dashboard outstanding widget, and `payment-outstanding` report. It considers only POSTED invoices and ACTIVE allocations backed by a non-reversed/non-cancelled payment. Fully paid invoices are excluded. Invoice `due_date` is the only ageing authority; a missing due date remains `UNCLASSIFIED` rather than being fabricated. The operational buckets are NOT_DUE, 0-30, 31-60, 61-90, 91-120, and 120+.

`PartyCreditService` now calculates opening balance (when stored authoritatively), unpaid posted invoices, uninvoiced confirmed/reserved orders, and actual unallocated advances. Posted invoices exclude their order from the confirmed-order component, so order/invoice exposure is not double counted. Pending/bounced/cancelled PDCs are ignored; PDC advance is recognized only after a REALIZED PDC created its payment. The production `OrderService::confirmOrder` locks the party and recomputes projected exposure immediately before FEFO reservation: it blocks only when `projectedExposure > creditLimit`, so equality is allowed. No override path is present.

Focused source contracts are in `tests/contract/verify_financial_closure_contract.php`, `tests/contract/verify_openapi_financial_contract.php`, and the expanded analytics/PDC contracts. Runtime execution remains blocked solely by unavailable PHP, Composer, and MySQL/MariaDB.
