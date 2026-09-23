# Pharma CRM API Reference

## 1. Overview

The Pharma CRM API is a robust RESTful interface for managing franchises, sales teams, products, inventory, orders, and more. It is designed to support the complete workflow of pharmaceutical distribution and sales.

**Base URL:** `https://api.yourdomain.com/api/v1/`
**API Version:** `v3.0`
**Content Type:** `application/json` (both request and response)

All requests to protected endpoints require an access token. All POST, PUT, and PATCH requests require an Idempotency-Key.

---

## 2. Authentication & Headers

### Authentication
Authentication is handled via OAuth 2.0 (Password and Refresh Token grants). 
Include the JWT access token in the `Authorization` header as a Bearer token.

### Standard Request Headers

| Header | Required | Type | Description |
|--------|----------|------|-------------|
| `Authorization` | Yes (Protected) | String | `Bearer <access_jwt>` |
| `Content-Type` | Yes | String | Must be `application/json` |
| `Accept` | Yes | String | Must be `application/json` |
| `Idempotency-Key` | Yes (POST/PUT/PATCH)| String | Unique UUID string to prevent duplicate processing. |
| `X-Tenant-ID` | No | String | Can be used by Super Admins to force context. |

---

## 3. Response Envelope

All API responses follow a strict envelope format to ensure predictable parsing on the client side.

### Success Envelope

```json
{
  "success": true,
  "data": {
    "id": "ord_12345",
    "status": "CONFIRMED"
  },
  "meta": {
    "request_id": "req_8f73b2a",
    "page": 1,
    "per_page": 25,
    "total": 250
  }
}
```

### Error Envelope

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The request payload failed validation.",
    "fields": {
      "quantity": ["Must be greater than 0"]
    }
  },
  "meta": {
    "request_id": "req_8f73b2a"
  }
}
```

---

## 4. HTTP Status Codes

| Status Code | Description | Usage |
|-------------|-------------|-------|
| `200 OK` | Success | Used for GET, PATCH, and state-change POSTs. |
| `201 Created` | Created | Used for POST requests that create a new resource. |
| `202 Accepted` | Accepted | Request accepted and enqueued for async processing. |
| `204 No Content` | No Content | Successful request but no body returned (e.g., Delete). |
| `400 Bad Request` | Bad Request | Malformed JSON or invalid syntax. |
| `401 Unauthorized` | Unauthorized | Missing or invalid authentication token. |
| `403 Forbidden` | Forbidden | Authenticated, but lacks required permissions. |
| `404 Not Found` | Not Found | Resource not found, or cross-tenant reference denied. |
| `409 Conflict` | Conflict | Duplicate key, idempotency mismatch, or concurrent update. |
| `422 Unprocessable`| Validation | Payload failed business validation rules. |
| `429 Rate Limit` | Rate Limited | Too many requests. |
| `500 Server Error`| Internal Error| An unexpected server error occurred. |
| `503 Unavailable` | Unavailable | Service temporarily down or under maintenance. |

---

## 5. Core Concepts

### 5.1 Idempotency
To prevent accidental duplicate executions (e.g., from network retries), all mutating requests (POST, PUT, PATCH) require an `Idempotency-Key` header.
- If a request is retried with the same key before the first completes, a `409 Conflict` with `REQUEST_IN_PROGRESS` is returned.
- If retried after success, the original response is re-transmitted (status `200` or `201`).
- If retried after a failure, a `409 Conflict` with `PREVIOUS_ATTEMPT_FAILED` or the original error is returned.
- If the payload differs for the same key, a `409 Conflict` with `IDEMPOTENCY_MISMATCH` is returned.

### 5.2 Pagination
List endpoints return paginated results using standard offset pagination.
Send `?page=1&per_page=25` in the query string.
The response `meta` object will contain `page`, `per_page`, and `total`.

### 5.3 Rate Limiting
APIs are rate-limited per IP and per User.
Limits are returned in the following response headers:
- `X-RateLimit-Limit`: Maximum requests per window.
- `X-RateLimit-Remaining`: Remaining requests in current window.
- `X-RateLimit-Reset`: Unix timestamp when the window resets.

---

## 6. Authorization Matrix

| Capability | SUPER_ADMIN | FRANCHISE_ADMIN | SALES | DISTRIBUTOR |
|---|---|---|---|---|
| Organizations / Franchises | ✅ | ❌ | ❌ | ❌ |
| Franchise users | ✅ | ✅ | ❌ | ❌ |
| Products / Pricing / Schemes | ✅ (audited) | ✅ | Read only | Applicable price only |
| Leads | ✅ | ✅ | Assigned only | ❌ |
| Parties | ✅ | ✅ | Assigned only | Own profile |
| Territory override | ✅ | ✅ | ❌ | ❌ |
| Orders | ✅ | ✅ | Assigned parties | Own orders |
| Inventory / Billing / Dispatch | ✅ | ✅ | ❌ | View own |
| Payments | ✅ | ✅ | View assigned | View own |
| Audit | ✅ | Own tenant | ❌ | ❌ |
| Impersonation | ✅ | ❌ | ❌ | ❌ |

---

## 7. Endpoint Catalog (Overview)

### Authentication
- `POST /api/v1/oauth/token` - Get access token (password or refresh_token)
- `POST /api/v1/oauth/revoke` - Revoke token
- `GET /api/v1/auth/me` - Get current user profile
- `POST /api/v1/auth/change-password` - Change password
- `POST /api/v1/auth/forgot-password` - Request password reset
- `POST /api/v1/auth/reset-password` - Reset password

### Super Admin (`/super/*`)
- **Dashboard & Health:** `GET /super/dashboard`, `GET /super/health`
- **Security:** `GET /super/audit`, `GET /super/security-events`
- **Organizations:** `GET/POST /super/organizations`, `PATCH /super/organizations/{ref}`
- **Franchises:** `GET/POST /super/franchises`, `PATCH /super/franchises/{ref}`
  - `POST /super/franchises/{ref}/suspend|activate|admins`
- **Users:** `GET /super/users`, `POST /super/users/{ref}/unlock`
- **Impersonate:** `POST /super/impersonate/{user_ref}`

### Franchise Admin (`/admin/*`)
- **Dashboard:** `GET /admin/dashboard`
- **Users:** `GET/POST /admin/users`, `PATCH /admin/users/{ref}`, `activate|deactivate|reset-password`
- **Masters:** Categories, Tiers, Transporters, Settings, Notification Templates.
- **Products:** `GET/POST /admin/products`, `PATCH /admin/products/{ref}`, `activate|deactivate|archive`
- **Pricing:** `GET/POST /admin/prices`, `PATCH /admin/prices/{ref}`, `POST /admin/pricing/resolve`
- **Schemes:** `GET/POST /admin/schemes`, `PATCH /{ref}`, `POST /admin/schemes/calculate`
- **Leads:** `GET/POST /admin/leads`, `assign|convert|archive`, `GET /admin/leads/{ref}/activities`
- **Follow-ups:** `GET/POST /admin/follow-ups`, `complete|reschedule`
- **Parties:** `GET/POST /admin/parties`, `PATCH /{ref}`, `activate|deactivate`, `ledger|orders|payments`
- **Territories:** `GET/POST /admin/territories`, `PATCH /{ref}`, `POST /admin/territories/validate`, `override`
- **Orders:** `GET/POST /admin/orders`, `GET /{ref}`, `confirm|hold|reject|cancel|reserve|release`
- **Inventory:** `GET batches|near-expiry`, `POST receipts|adjust|status|allocate-fefo`
- **Billing:** `GET /admin/invoices`, `POST (from order_ref)`, `GET /{ref}`, `cancel|print`
- **Dispatch:** `GET/POST /admin/dispatches`, `PATCH /{ref}`, `dispatch|deliver`
- **Payments:** `GET/POST /admin/payments`, `GET /{ref}`, `allocate|reverse`
- **Reports:** Leads, followups, conversion, sales, territory, products, schemes, inventory, outstanding, dispatch. `POST /admin/reports/exports`

### Geographic Data (Read-only)
- `GET /geo/states`
- `GET /geo/districts?state_ref=`
- `GET /geo/pincodes/{pin}`

### Sales (`/sales/*`)
- **Dashboard:** `GET /sales/dashboard`
- **Leads:** `GET/POST /sales/leads`, `PATCH /{ref}`, `remarks|status`
- **Follow-ups:** `GET/POST /sales/follow-ups`, `complete|reschedule|miss`
- **Orders:** `GET /sales/orders`, `POST /sales/orders` (Assigned parties)
- **Catalogue & Parties:** `GET /sales/catalogue`, `GET /sales/parties`, `GET /sales/outstanding`

### Distributor Portal (`/portal/*`)
- **Profile & Catalogue:** `GET /portal/profile`, `PATCH /portal/profile`, `GET /portal/catalogue`
- **Cart Calculation:** `POST /portal/cart/calculate` (Server-side price, scheme & tax evaluation)
- **Orders:** `GET/POST /portal/orders`, `GET /portal/orders/{ref}`, `POST /portal/orders/{ref}/cancel`
- **Fulfillment & Ledger:** `GET /portal/invoices`, `GET /portal/dispatches`, `GET /portal/outstanding`

### Notifications (`/notifications/*`)
- **List & Bell Poll:** `GET /notifications?limit=20`
- **Mark Read:** `POST /notifications/{ref}/read`, `POST /notifications/read-all`

### Super Admin (`/super/*`)
- **Platform Stats:** `GET /super/dashboard/stats`
- **Platform Audit:** `GET /super/audit`
- **Security Events:** `GET /super/security-events`
- **Organizations:** `GET/POST /super/organizations`, `PATCH /super/organizations/{ref}`
- **Franchises:** `GET/POST /super/franchises`, `PATCH /super/franchises/{ref}`, `suspend|activate|admins`
- **Impersonate:** `POST /super/impersonate/{user_ref}`

### Webhooks
- `POST /webhooks/{endpoint_slug}/leads` - Ingest external leads (HMAC-SHA256 verified)
- `POST /webhooks/{endpoint_slug}/status` - Status callbacks

---

## 8. Detailed API Reference (Key Endpoints)

### 8.1 Authentication: Generate Token
**Endpoint:** `POST /api/v1/oauth/token`
**Description:** Exchange credentials or refresh token for a JWT access token.

**Headers:**
- `Content-Type: application/json`
- `Idempotency-Key: <uuid>`

**Request Body:**
```json
{
  "grant_type": "password",
  "username": "admin@franchise.com",
  "password": "securepassword123"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "access_token": "eyJhbG...",
    "refresh_token": "def502...",
    "token_type": "Bearer",
    "expires_in": 3600
  },
  "meta": { "request_id": "req_123" }
}
```

### 8.2 Create Order
**Endpoint:** `POST /admin/orders` (also available via `/sales/orders` and `/portal/orders`)
**Description:** Creates a new sales order for a party. Subject to credit limit, territory validation, and price resolution.

**Headers:**
- `Authorization: Bearer <token>`
- `Idempotency-Key: <uuid>`

**Request Body:**
```json
{
  "party_ref": "pty_9921",
  "client_reference": "PO-2023-1001",
  "expected_delivery_date": "2023-11-01",
  "items": [
    {
      "product_ref": "prd_8831",
      "quantity": 100
    },
    {
      "product_ref": "prd_8832",
      "quantity": 50
    }
  ]
}
```

**Validation Rules:**
- `party_ref`: Must be active and accessible to user.
- `items`: Cannot be empty. `quantity` must be integer > 0.
- `client_reference`: Must be unique per party to avoid `DUPLICATE_ORDER_CLIENT_REF`.

**Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "order_ref": "ord_882931",
    "status": "DRAFT",
    "total_amount": 15000.00
  },
  "meta": { "request_id": "req_124" }
}
```

### 8.3 FEFO Allocation (Dry Run)
**Endpoint:** `POST /admin/inventory/allocate-fefo`
**Description:** Calculates the First-Expire-First-Out batch allocation for a list of products and quantities without actually reserving stock. Useful for previewing dispatch.

**Headers:**
- `Authorization: Bearer <token>`
- `Idempotency-Key: <uuid>`

**Request Body:**
```json
{
  "warehouse_ref": "wh_01",
  "items": [
    { "product_ref": "prd_8831", "quantity": 150 }
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "allocations": [
      {
        "product_ref": "prd_8831",
        "requested": 150,
        "allocated": 150,
        "batches": [
          { "batch_ref": "b_2023_01", "quantity": 100, "expiry": "2024-01-01" },
          { "batch_ref": "b_2023_02", "quantity": 50, "expiry": "2024-03-01" }
        ]
      }
    ]
  },
  "meta": { "request_id": "req_125" }
}
```
**Errors:**
- `NO_ELIGIBLE_FEFO_BATCH`: Returned if sufficient unexpired stock is unavailable.

### 8.4 Generate Invoice (Billing)
**Endpoint:** `POST /admin/invoices`
**Description:** Converts a confirmed order into a tax invoice. Triggers financial ledger entries and reserves final stock.

**Request Body:**
```json
{
  "order_ref": "ord_882931",
  "invoice_date": "2023-10-15"
}
```

**Errors:**
- `ORDER_STATE_INVALID`: If order is not CONFIRMED or already invoiced.
- `CREDIT_LIMIT_EXCEEDED`: If invoicing this pushes party over limit.

### 8.5 Allocate Payment
**Endpoint:** `POST /admin/payments/{ref}/allocate`
**Description:** Allocates an unassigned payment receipt against one or more outstanding invoices.

**Request Body:**
```json
{
  "allocations": [
    { "invoice_ref": "inv_1029", "amount": 5000.00 },
    { "invoice_ref": "inv_1030", "amount": 2500.00 }
  ]
}
```

**Errors:**
- `PAYMENT_ALLOCATION_EXCEEDS_OUTSTANDING`: Allocation amount > outstanding invoice balance.
- `VALIDATION_FAILED`: Allocation amount > unallocated payment balance.

### 8.6 Create Lead
**Endpoint:** `POST /admin/leads` (or `/sales/leads`)
**Description:** Create a new CRM lead for tracking potential business.

**Request Body:**
```json
{
  "name": "City Care Pharmacy",
  "contact_person": "Jane Doe",
  "phone": "+1234567890",
  "pincode": "10001",
  "source": "FIELD_VISIT",
  "assigned_to": "usr_991"
}
```

### 8.7 Webhook Lead Ingestion
**Endpoint:** `POST /webhooks/{endpoint_slug}/leads`
**Description:** Public endpoint (secured via HMAC) for third-party systems (like marketing landing pages) to push leads into the CRM.

**Headers:**
- `X-Webhook-Signature`: HMAC SHA256 of the payload.

**Request Body:**
```json
{
  "external_id": "lead_ext_001",
  "company": "Global Pharma",
  "email": "contact@globalpharma.com",
  "phone": "9998887776",
  "campaign": "Q4_Promo"
}
```

**Response (202 Accepted):**
```json
{ "success": true, "meta": { "request_id": "req_128" } }
```

### 8.8 Validate Territory
**Endpoint:** `POST /admin/territories/validate`
**Description:** Checks if a specific pin code falls within a defined sales territory.

**Request Body:**
```json
{
  "territory_ref": "terr_north_01",
  "pincode": "110001"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "is_valid": true,
    "state": "DL",
    "district": "New Delhi"
  },
  "meta": { "request_id": "req_129" }
}
```
**Errors:**
- `TERRITORY_PINCODE_UNKNOWN`

### 8.9 Pricing Resolve (Dry Run)
**Endpoint:** `POST /admin/pricing/resolve`
**Description:** Calculates the effective price of a product for a specific party, applying tier discounts, active schemes, and taxation.

**Request Body:**
```json
{
  "party_ref": "pty_9921",
  "items": [
    { "product_ref": "prd_8831", "quantity": 100 }
  ]
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "resolved_items": [
      {
        "product_ref": "prd_8831",
        "base_price": 100.00,
        "discount_percentage": 5.0,
        "scheme_applied": "sch_q4_volume",
        "final_unit_price": 95.00,
        "tax_rate": 12.0,
        "total_amount": 10640.00
      }
    ]
  },
  "meta": { "request_id": "req_130" }
}
```
**Errors:**
- `PRICE_NOT_FOUND`: If no active price list covers this product.

---

## 9. Error Codes Reference

| Error Code | Meaning | Resolution |
|------------|---------|------------|
| `DUPLICATE_ORDER_CLIENT_REF` | Order PO number already exists. | Use a unique client_reference per party. |
| `IDEMPOTENCY_MISMATCH` | Idempotency key reused with different payload. | Use a new key for a new request. |
| `REQUEST_IN_PROGRESS` | Concurrent request with same idempotency key is processing. | Wait and retry. |
| `PREVIOUS_ATTEMPT_FAILED`| Retry of a previously failed idempotent request. | Generate a new key if payload is fixed. |
| `TENANT_MISMATCH` | Resource belongs to a different franchise. | Check references. |
| `INVALID_CREDENTIALS` | Wrong username or password. | Verify credentials. |
| `TOKEN_EXPIRED` | JWT has expired. | Use refresh token to get a new access token. |
| `TOKEN_REVOKED` | Token manually revoked. | Log in again. |
| `TERRITORY_NOT_ALLOWED` | Sales rep cannot operate in this region. | Check assignment. |
| `CREDIT_LIMIT_EXCEEDED` | Action pushes party over approved credit. | Collect payment or request limit override. |
| `NO_ELIGIBLE_FEFO_BATCH` | Not enough unexpired stock available. | Check inventory expiry reports. |
| `STOCK_RESERVATION_CONFLICT`| Concurrent order reserved the stock. | Retry order creation. |
| `ORDER_STATE_INVALID` | Action invalid for current order state. | Refresh order to check current state. |
| `PAYMENT_ALLOCATION_EXCEEDS_OUTSTANDING` | Allocating more than invoice due. | Adjust allocation amount. |
| `PRICE_NOT_FOUND` | Product missing from price list. | Update pricing masters. |
| `IDEMPOTENCY_KEY_REQUIRED` | Missing required header on POST/PUT/PATCH. | Add header. |
| `TERRITORY_PINCODE_UNKNOWN`| Pin code not mapped in geo database. | Update master geo data. |
| `NOT_FOUND` | Generic resource not found. | Verify the reference IDs. |
| `FORBIDDEN` | Lack permissions for action. | Request admin access. |
| `VALIDATION_FAILED` | Payload structurally or logically invalid. | Check `fields` object in error response. |

---

## 10. The 9 Complete User Journeys (E2E)

This CRM maps out the entire pharmaceutical lifecycle via 9 distinct, sequential, and interconnected API use cases (journeys).

### Journey 1: Platform Provisioning (Super Admin)
**Actor:** `SUPER_ADMIN`
1. Login via `/api/v1/oauth/token` (grant: password, client_id: crm-super)
2. View Platform Stats: `GET /api/v1/super/dashboard/stats`
3. Provision Organization: `POST /api/v1/super/organizations`
4. Setup Franchise under Organization: `POST /api/v1/super/franchises`
5. Review global audit logs: `GET /api/v1/super/audit`

### Journey 2: Franchise Onboarding & Setup (Franchise Admin)
**Actor:** `FRANCHISE_ADMIN`
1. Login via `/api/v1/oauth/token` (grant: password, client_id: crm-admin)
2. Setup product catalog: `POST /api/v1/admin/products`
3. Define territory structures: `POST /api/v1/admin/territories`
4. Setup pricing tiers & schemes: `POST /api/v1/admin/pricing`
5. Create Sales & Distributor users: `POST /api/v1/admin/users`

### Journey 3: Lead & Prospecting (Sales Representative)
**Actor:** `SALES`
1. Login via `/api/v1/oauth/token` (grant: password, client_id: crm-sales)
2. Check assigned territories: `GET /api/v1/sales/territories`
3. Capture new lead: `POST /api/v1/sales/leads`
4. Schedule next follow-up: `POST /api/v1/sales/leads/{id}/followups`
5. Convert lead to Party (Distributor/Stockist): `PATCH /api/v1/sales/leads/{id}/convert`

### Journey 4: Inventory & Warehouse Management (Admin / Ops)
**Actor:** `FRANCHISE_ADMIN`
1. Create Good Receipt Note (GRN): `POST /api/v1/admin/inventory/receipts`
2. Check on-hand stock: `GET /api/v1/admin/inventory/stock`
3. Identify near-expiry batches: `GET /api/v1/admin/inventory/near-expiry`
4. Perform physical stock adjustment: `POST /api/v1/admin/inventory/adjustments`

### Journey 5: Portal Catalog & Cart (Distributor Portal)
**Actor:** `DISTRIBUTOR`
1. Login via `/api/v1/oauth/token` (grant: password, client_id: crm-portal)
2. Browse available products & current stock: `GET /api/v1/portal/catalogue`
3. Check tier-specific pricing/schemes: `POST /api/v1/portal/cart/calculate`
4. Add items to cart (Draft Order): `POST /api/v1/portal/orders`

### Journey 6: Sales Order Processing (Sales / Admin / Portal)
**Actor:** `SALES` or `DISTRIBUTOR` or `FRANCHISE_ADMIN`
1. Submit Order for approval: `POST /api/v1/orders` (or via portal)
2. Admin reviews order (credit limit check): `GET /api/v1/admin/orders/{id}`
3. Admin approves order (reserves FEFO stock): `PATCH /api/v1/admin/orders/{id}/status` (CONFIRMED)
4. System automatically deducts available stock and reserves `reserved_qty` on batches.

### Journey 7: Invoicing & Billing (Finance / Ops)
**Actor:** `FRANCHISE_ADMIN`
1. Generate Tax Invoice from Confirmed Order: `POST /api/v1/admin/invoices`
2. System takes snapshot of prices, applies GST (CGST/SGST/IGST).
3. System moves `reserved_qty` out of inventory permanently.
4. Invoice updates Party Ledger (increases outstanding balance).
5. Distributor downloads Invoice: `GET /api/v1/portal/invoices/{id}/pdf`

### Journey 8: Logistics & Dispatch (Warehouse)
**Actor:** `FRANCHISE_ADMIN`
1. Select transporter and create Dispatch: `POST /api/v1/admin/dispatches`
2. Update Lorry Receipt (LR) tracking info: `PATCH /api/v1/admin/dispatches/{id}/lr`
3. Mark as delivered: `PATCH /api/v1/admin/dispatches/{id}/status` (DELIVERED)
4. Distributor tracks shipment on portal: `GET /api/v1/portal/dispatches`

### Journey 9: Payments & Ledger Reconciliation (Finance)
**Actor:** `FRANCHISE_ADMIN`
1. Record incoming NEFT/RTGS payment: `POST /api/v1/admin/payments`
2. Allocate payment to specific open invoices: `POST /api/v1/admin/payments/{id}/allocate`
3. System clears invoice due amounts and updates global party outstanding limit.
4. Distributor views outstanding statement: `GET /api/v1/portal/outstanding`

---
*End of API Reference*
