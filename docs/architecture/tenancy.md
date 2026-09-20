# Multi-Tenant Architecture

This document describes the multi-tenant architecture for the Pharma CRM project. The system isolates data securely using row-level tenancy, ensuring franchises only ever interact with their own data.

## 1. Tenancy Tree

The system is deployed on a single cloud instance (one cPanel account, one logical Database) and follows a strict hierarchy:

```text
CLOUD INSTANCE (one DB)
 ├── PLATFORM (Super Admin)                       tenant_scope = PLATFORM
 └── ORGANIZATION  org_ref = ORG-xxxxxxxxxxxxxxxx
       └── FRANCHISE  franchise_ref = FRN-xxxxxxxxxxxxxxxx  ← Row-level tenant key
             ├── FRANCHISE_ADMIN users
             ├── SALES users
             ├── DISTRIBUTOR users (linked to a party)
             └── leads · parties · products · prices · schemes · orders · batches
                 invoices · dispatches · payments · notifications · audit · jobs
```

The core isolation key is the `franchise_ref`. Every transactional and entity record belongs to a franchise.

---

## 2. Tenant Resolution Order

For every API request, the tenancy context is established before any business logic executes:

1. **Surface Identification**: The route surface determines the expected scope (e.g., `/super/*` expects `PLATFORM`, `/admin/*` expects `FRANCHISE`).
2. **JWT Verification**: The Bearer token is verified (signature, `exp`, `nbf`, `iss`, `aud`, `kid`, `typ=access`).
3. **User Validation**: The database is queried using the `user_ref` from the token. The user, their franchise, and their organization must all have an `ACTIVE` status.
4. **Claim Matching**: The claims inside the JWT (`org_ref`, `franchise_ref`, `role`) must match the current database state exactly. A mismatch throws a 401 and creates a `SECURITY` audit log.
5. **Context Construction**: An immutable `TenantContext` object is built.
6. **Header Validation (Optional)**: If the client passes an `X-Franchise-Ref` header, it *must* match the context. It is strictly validated and *never* used to actively select the tenant. A mismatch results in a 403 `TENANT_MISMATCH`.

---

## 3. Mandatory Tenant Columns

Every tenant-scoped table in the database must implement these standard columns:

```sql
org_ref         VARCHAR(24) NOT NULL,
franchise_ref   VARCHAR(24) NOT NULL,
created_by_ref  VARCHAR(24) NOT NULL,
updated_by_ref  VARCHAR(24) NULL,
created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
updated_at      DATETIME NULL ON UPDATE CURRENT_TIMESTAMP
```

---

## 4. Enforcement Rules (T01-T08)

The framework enforces tenancy at the lowest level possible.

- **T01**: Every tenant repository query automatically has `WHERE franchise_ref = :__tenant` injected by the `TenantScope` builder.
- **T02**: All `INSERT` operations pull `org_ref` and `franchise_ref` strictly from the `TenantContext`. Any tenant values passed in the request body are discarded.
- **T03**: `UPDATE` and `DELETE` queries automatically include `franchise_ref` in the `WHERE` clause. Ledger-style tables are status-change-only and disallow physical `DELETE`.
- **T04**: Child tables carry composite foreign keys: `(franchise_ref, parent_ref)`.
- **T05**: Super Admin cross-tenant reads are explicitly invoked using `->withoutTenantScope('reason')`. Every usage of this method generates an `audit_logs` entry with `action=TENANT_BYPASS`.
- **T06**: If a cross-tenant reference is passed in a URL or body and resolves to another tenant's data, the system responds with a 404 (Not Found) rather than 403 (Forbidden) to prevent data existence leaks, and logs a `SECURITY` audit event.
- **T07**: Writing raw SQL outside of repositories is strictly forbidden. A lint agent checks every repository query string for the presence of `franchise_ref`.
- **T08**: Reports and data exports utilize the exact same scoped query builder as the API.

---

## 5. The TenantContext Class

The application relies on this strongly-typed, immutable DTO:

```php
final class TenantContext {
    public function __construct(
        public readonly string $orgRef,
        public readonly ?string $franchiseRef,
        public readonly string $userRef,
        public readonly string $role,
        public readonly string $scope,
        public readonly ?string $partyRef,
        public readonly string $requestId,
        public readonly ?string $impersonatorRef = null,
    ) {}

    public function isSuper(): bool { return $this->role === 'SUPER_ADMIN'; }
    public function isAdmin(): bool { return $this->role === 'FRANCHISE_ADMIN'; }
    public function isSales(): bool { return $this->role === 'SALES'; }
    public function isDistributor(): bool { return $this->role === 'DISTRIBUTOR'; }

    public function requireFranchise(): string {
        if ($this->franchiseRef === null) { 
            throw new ForbiddenException('FRANCHISE_REQUIRED'); 
        }
        return $this->franchiseRef;
    }
}
```

---

## 6. Composite Unique Constraints

To allow different franchises to use the same SKUs, order numbers, or party codes, all unique constraints include the `franchise_ref`:

```sql
orders             UNIQUE (franchise_ref, client_order_ref)
orders             UNIQUE (franchise_ref, order_no)
invoices           UNIQUE (franchise_ref, invoice_no)
dispatches         UNIQUE (franchise_ref, dispatch_no)
payments           UNIQUE (franchise_ref, payment_no)
parties            UNIQUE (franchise_ref, party_code)
products           UNIQUE (franchise_ref, sku)
inventory_batches  UNIQUE (franchise_ref, product_ref, batch_no)
leads              UNIQUE (franchise_ref, source_key, ext_key)
webhook_events     UNIQUE (franchise_ref, source_ref, external_event_id)
users              UNIQUE (tenant_key, email)  -- tenant_key = COALESCE(franchise_ref,'PLATFORM')
api_idempotency    UNIQUE (franchise_ref, idempotency_key)
```

**Duplicate Policy:**
- **ALLOWED**: The exact same product SKU or party code can exist in different franchises.
- **BLOCKED**: Submitting the same `client_order_ref` within the same franchise returns `409 DUPLICATE_ORDER_CLIENT_REF`.
- **BLOCKED**: Submitting the same `idempotency_key` with a *different* request body returns `409 IDEMPOTENCY_MISMATCH`.
- **REPLAY**: Submitting the same `idempotency_key` with the *identical* body replays the cached API response safely.

---

## 7. TenantRepository Base Class

All domain repositories extend `TenantRepository`.
- Its constructor mandates a `TenantContext`.
- It injects `AND franchise_ref = :__tenant` into every query string it generates.
- It provides the `withoutTenantScope()` escape hatch, which logs its usage heavily.

---

## 8. Reference & Sequence System

**System References (Primary Keys):**
- Format: `PREFIX-XXXXXXXXXXXXXXXX` (e.g., `FRN-8H7Q2W4M...`).
- Built using Crockford Base32 encoding.
- 80 bits of entropy.
- Stored as `VARCHAR(24)`.
- Never sequential; astronomically low collision probability.

**Human-Readable Numbers (Invoices, Orders):**
- Format: `PREFIX/PERIOD/NNNNNN` (e.g., `INV/2026/000123`).
- Generation is atomic: `INSERT...ON DUPLICATE KEY UPDATE last_value = LAST_INSERT_ID(last_value + 1)`.

---

## 9. Composite Foreign Keys

Because primary records are isolated by `franchise_ref`, child tables must enforce constraints using composite foreign keys.

Examples:
```sql
CONSTRAINT fk_oi_order FOREIGN KEY (franchise_ref, order_ref) 
    REFERENCES orders(franchise_ref, order_ref)

CONSTRAINT fk_oi_prod FOREIGN KEY (franchise_ref, product_ref) 
    REFERENCES products(franchise_ref, product_ref)

CONSTRAINT fk_inv_order FOREIGN KEY (franchise_ref, order_ref) 
    REFERENCES orders(franchise_ref, order_ref)
```
