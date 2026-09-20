# Pharma CRM Business Rules Core

This directory contains the authoritative business rules for the Pharma CRM platform. These rules govern the core logic of the application, ensuring consistency, data integrity, and correct business outcomes across all modules.

## Table of Contents
- [Core Architecture Rules](#core-architecture-rules)
  - [Tenancy & Data Isolation](#tenancy--data-isolation)
  - [Reference & Number Generation](#reference--number-generation)
- [Data Integrity Rules](#data-integrity-rules)
  - [Duplicate Policy](#duplicate-policy)
  - [Idempotency Rules](#idempotency-rules)
- [Financial Rules](#financial-rules)
  - [Money Arithmetic](#money-arithmetic)
  - [Credit Rules](#credit-rules)

---

## Core Architecture Rules

### Tenancy & Data Isolation

The Pharma CRM is a multi-tenant system designed to serve multiple organizations and franchises from a single database schema. Strict data isolation is the most critical requirement of the platform.

1. **Composite Tenant Keys:**
   Every tenant-scoped table MUST carry both `org_ref` and `franchise_ref` on every row. This denormalization avoids complex joins when enforcing isolation.

2. **Query Scoping:**
   No application query is permitted to touch another tenant's data. A global `TenantScope` (e.g., an Eloquent Global Scope or Doctrine Filter) MUST automatically inject `WHERE franchise_ref = :__tenant` into every `SELECT`, `UPDATE`, and `DELETE` query.

3. **Tenant Resolution:**
   The active tenant is derived **ONLY** from a cryptographically verified JWT, followed by database revalidation of the user's active status within that franchise. 
   - The tenant MUST NEVER be derived from the request body (`POST` data).
   - The tenant MUST NEVER be derived from the query string or URL parameters.

4. **Cross-Tenant References:**
   If a user attempts to access a reference (e.g., `order_ref`, `lead_ref`) that belongs to another tenant, the system MUST return a `404 Not Found` response. 
   - It MUST NOT return a `403 Forbidden` response. Returning 403 leaks the existence of the resource in the database, which is a security violation in multi-tenant SaaS environments.

### Reference & Number Generation

The system uses two distinct types of identifiers: System References (for primary/foreign keys and API interactions) and Human Numbers (for UI display and printed documents).

#### System References
- **Format:** `PREFIX-XXXXXXXXXXXXXXXX` (e.g., `ORD-3F8A9B2C4D1E5G7H`)
- **Generation:** Uses Crockford base32 encoding to ensure URL-safety and avoid ambiguous characters (like I, L, 1, 0, O).
- **Entropy:** 80 bits of random entropy per reference to prevent guessing attacks.
- **Usage:** Used as primary keys, API path parameters, and foreign key relations.

#### Human Numbers
- **Format:** `PREFIX/PERIOD/NNNNNN` (e.g., `INV/2026/000123`, `ORD/FY2627/000045`)
- **Generation:** Generated via an atomic sequence table.
- **Atomicity Rule:** `INSERT...ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)`. This ensures race-condition-free increments.
- **Gap Tolerance:**
  - Gaps are **ACCEPTABLE** for Order Numbers and Payment Numbers. If a transaction rolls back, the incremented sequence number is lost.
  - Gaps are **STRICTLY PROHIBITED** for Invoice Numbers due to tax and auditing compliance.
- **Gapless Invoice Generation:** To achieve gapless invoice sequences, the sequence increment MUST occur *inside* the final billing database transaction. If the billing fails and rolls back, the sequence lock is released and the number is not consumed.

---

## Data Integrity Rules

### Duplicate Policy

The CRM handles duplication attempts differently based on the domain context and exact payload.

- **ALLOWED:** The same underlying data (e.g., a customer name or a product configuration) can exist across different franchises. Franchises are completely isolated universes.
- **BLOCKED (Client Order Ref):** If an order is submitted with the same `(franchise_ref, client_order_ref)` as an existing order, the system returns `409 DUPLICATE_ORDER_CLIENT_REF`. This prevents accidental double-submission from offline mobile clients.
- **BLOCKED (Idempotency Mismatch):** If a request has the same `(franchise_ref, idempotency_key)` but a *different* request body hash, the system returns `409 IDEMPOTENCY_MISMATCH`.
- **REPLAY (Idempotent Recovery):** If a request has the same `(franchise_ref, idempotency_key)` and the *exact same* request body hash, the system serves the cached original response.
- **BLOCKED (Lead Deduplication):** If a lead arrives with the same `(franchise_ref, source_key, external_lead_id)`, the system applies the franchise lead duplication policy (either merging or rejecting with `409`).
- **IMPOSSIBLE:** The database schema enforces `UNIQUE` constraints on all sequence-generated human numbers (`invoice_no`, `dispatch_no`, `payment_no`, `order_no`), making duplication at the database level impossible.

### Idempotency Rules

To prevent duplicate actions on unreliable networks (e.g., field sales mobile apps on spotty 3G/4G), all `POST`, `PUT`, and `PATCH` business write endpoints enforce idempotency.

1. **Header Requirement:** Requests MUST include the `Idempotency-Key` header.
   - Allowed format: 16-120 characters, matching regex `^[A-Za-z0-9_-]{16,120}$`.
2. **State Machine:**
   - **Key Absent:** Return `422 IDEMPOTENCY_KEY_REQUIRED`.
   - **Key In Progress:** If another thread is currently processing the key, return `409 REQUEST_IN_PROGRESS` with a `Retry-After: 2` header.
   - **Previous Attempt Failed:** If the previous attempt resulted in a 5xx error, return `409 PREVIOUS_ATTEMPT_FAILED`. The client MUST generate a new key to try again.
   - **Successful Replay:** Same key + same body hash → return `200/201` with the cached response JSON.
   - **Mismatch:** Same key + different body hash → return `409 IDEMPOTENCY_MISMATCH`.

---

## Financial Rules

### Money Arithmetic

All financial calculations must strictly adhere to the following rules to prevent floating-point drift and ensure accounting compliance.

1. **Database Storage:** All money values MUST be stored as `DECIMAL(18,2)`.
2. **In-Memory Calculation:** All in-memory mathematical operations MUST use integer-paise arithmetic.
   - Multiply the `DECIMAL` value by 100 upon retrieval.
   - Perform all additions, subtractions, multiplications, and tax calculations using integers.
   - Divide by 100 and format to 2 decimal places immediately before database persistence or API JSON serialization.
3. **Tax Application:** GST is always applied *after* all discounts are deducted.
   - Intra-state orders apply CGST + SGST.
   - Inter-state orders apply IGST.
   - State matching is determined by comparing the franchise's GST state code with the shipping destination state code.
4. **Rounding Policy:** Standard half-up rounding (`ROUND(value, 0)` on the integer paise value) is applied on the **final line total**, not on individual sub-components.
5. **Invoice Totals:** The grand total of an invoice is strictly the `SUM` of its line totals.
6. **Immutability:** Historical order and invoice line totals are **NEVER** recomputed. The `rate`, `rate_source`, and `price_ref` are frozen into the `order_items` and `invoice_items` tables at the exact time of transaction.

### Credit Rules

Franchises can configure credit policies for their distributor and retailer parties. The policy dictates what happens when an order would push the party's outstanding balance past their configured limit.

| Setting | Behavior |
|---|---|
| `credit_policy = BLOCK` | The order is immediately rejected (`422 CREDIT_LIMIT_EXCEEDED`) if `party_outstanding + order_total > party_credit_limit`. |
| `credit_policy = HOLD` | The order is accepted but placed in `ON_HOLD` status (default behavior). An admin must manually review, override, and confirm the order. |
| `credit_limit = 0` | The party has effectively unlimited credit. No limit checks are performed during order placement. |
| `payment_terms_days` | Determines the due date of the invoice: `invoice_due_date = invoice_date + payment_terms_days`. |

**Outstanding Calculation Formula:**
Party Outstanding = `party.opening_outstanding` + `SUM(POSTED invoice.outstanding)` - `SUM(unallocated payments)`

Where `invoice.outstanding = invoice.grand_total - invoice.paid_total`.

---
*End of README.md*
