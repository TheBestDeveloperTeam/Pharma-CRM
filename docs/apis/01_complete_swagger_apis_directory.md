# Complete Project RESTful API & Swagger Directory (Production Grade)

This document catalogs the complete suite of **192 RESTful APIs** across all 20 functional domains in the Pharma CRM & Sales Force Automation platform. It covers route paths, HTTP methods, headers, authentication, query parameters, payload schemas, server-side validation rules, and authoritative database models.

---

## 1. Global API Conventions & Standard Response Envelopes

### Base URLs
* **Production Cloud:** `https://crm.easysolutins24.in/api/v1`
* **Local Dev Server:** `http://localhost:8000/api/v1` or `http://crm/api/v1`
* **Interactive Swagger UI:** `http://crm/api-docs/` (Serving `openapi.yaml`)

### Standard HTTP Headers
* `Authorization: Bearer <jwt_access_token>` (Mandatory for authenticated endpoints)
* `Content-Type: application/json`
* `Accept: application/json`
* `Idempotency-Key: <uuid-v4>` (Supported on all POST/PUT/PATCH mutating endpoints to prevent duplicate processing)
* `X-Tenant-Key: <franchise_ref|PLATFORM>` (Tenant resolution context)

### Success Response Envelope (JSON)
```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "page": 1,
    "per_page": 25,
    "total": 142,
    "total_pages": 6,
    "request_id": "req-98f2b71d-4f19-48ce"
  }
}
```

### Error Response Envelope (JSON)
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_FAILED",
    "message": "The given data was invalid.",
    "fields": {
      "mobile": "Mobile number is already registered with an active party.",
      "dl_number_1": "Drug license 1 is required."
    }
  },
  "meta": {
    "request_id": "req-98f2b71d-4f19-48ce"
  }
}
```

---

## 2. API Endpoints by Domain & Functional Tag

### TAG 01: Health & Readiness (`/health`, `/ready`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/health` | Platform & Database health probe (Public) | None | `200 OK` |
| `GET` | `/ready` | Database readiness check (Public) | None | `200 OK`, `503 Service Unavailable` |
| `GET` | `/api/v1/health` | API v1 Health probe alias (Public) | None | `200 OK` |
| `GET` | `/api/v1/ready` | API v1 Database readiness alias (Public) | None | `200 OK`, `503 Service Unavailable` |

---

### TAG 02: Authentication & Token Lifecycle (`/oauth`, `/auth`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `POST` | `/api/v1/oauth/token` | Issue Bearer JWT access & refresh tokens (Public) | `{ "grant_type": "password", "client_id": "crm-admin", "email": "...", "password": "...", "franchise_code": "FRN001" }` | `200 OK`, `401 Unauthorized`, `423 Locked` |
| `POST` | `/api/v1/oauth/revoke` | Revoke active token / Logout (Bearer) | None (Bearer token in header) | `200 OK` (`{"revoked": true}`) |
| `GET` | `/api/v1/auth/me` | Fetch active user profile, roles & permissions (Bearer) | None | `200 OK` (user_ref, name, email, role, scopes, permissions) |

---

### TAG 03: Geographic Reference Masters (`/geo`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/geo/states` | List Indian states (Public) | None | `200 OK` (Array of States with `state_ref`, `name`, `tin_code`) |
| `GET` | `/geo/districts` | List districts by state (Public) | `?state_ref=...` | `200 OK` (Array of Districts) |
| `GET` | `/geo/pincodes/{pin}` | Lookup pincode details (Public) | Path: `{pin}` (6 numeric digits) | `200 OK`, `404 Not Found` |
| `GET` | `/api/v1/geo/states` | List Indian states (Public alias) | None | `200 OK` |
| `GET` | `/api/v1/geo/districts` | List districts by state (Public alias) | `?state_ref=...` | `200 OK` |
| `GET` | `/api/v1/geo/pincodes/{pin}` | Lookup pincode details (Public alias) | Path: `{pin}` | `200 OK` |

---

### TAG 04: Super Admin Platform Management (`/super`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/super/organizations` | List all tenant organizations (SUPER_ADMIN) | `?page=1&per_page=20` | `200 OK` |
| `POST` | `/api/v1/super/organizations` | Create tenant organization (SUPER_ADMIN) | `{ "org_name": "...", "code": "...", "status": "ACTIVE" }` | `201 Created` |
| `GET` | `/api/v1/super/organizations/{ref}` | Get organization profile (SUPER_ADMIN) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/super/organizations/{ref}` | Update organization details (SUPER_ADMIN) | Path: `{ref}`, Body: `{ "org_name": "..." }` | `200 OK` |
| `GET` | `/api/v1/super/franchises` | List all franchises across platform (SUPER_ADMIN) | `?org_ref=...` | `200 OK` |
| `POST` | `/api/v1/super/franchises` | Provision new franchise tenant (SUPER_ADMIN) | `{ "org_ref": "...", "name": "...", "code": "...", "state_ref": "...", "gstin": "..." }` | `201 Created` |
| `GET` | `/api/v1/super/franchises/{ref}` | Get franchise details (SUPER_ADMIN) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/super/franchises/{ref}` | Update franchise metadata (SUPER_ADMIN) | Path: `{ref}`, Body: `{ "name": "...", "gstin": "..." }` | `200 OK` |
| `POST` | `/api/v1/super/franchises/{ref}/suspend` | Suspend franchise operations (SUPER_ADMIN) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/super/franchises/{ref}/activate` | Re-activate suspended franchise (SUPER_ADMIN) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/super/franchises/{ref}/admins` | Create initial Franchise Administrator (SUPER_ADMIN) | `{ "full_name": "...", "email": "...", "password": "..." }` | `201 Created` |
| `POST` | `/api/v1/super/impersonate/{ref}` | Issue temporary tenant impersonation token (SUPER_ADMIN) | Path: `{ref}` (User ref) | `200 OK` (`{"impersonation_token": "..."}`) |
| `GET` | `/api/v1/super/dashboard/stats` | Platform-wide macro KPIs (SUPER_ADMIN) | None | `200 OK` (Total orgs, franchises, users, GMV) |
| `GET` | `/api/v1/super/audit` | Cross-tenant security & mutation audit logs (SUPER_ADMIN) | `?page=1&action=...` | `200 OK` |
| `GET` | `/api/v1/super/security-events`| Security incident monitoring feed (SUPER_ADMIN) | `?severity=HIGH` | `200 OK` |

---

### TAG 05: Internal User & RBAC Management (`/admin/users`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/users` | List franchise staff users (Admin) | `?role=SALES&status=ACTIVE&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/users` | Create employee account (Admin) | `{ "full_name": "...", "email": "...", "mobile": "...", "role": "SALES", "reporting_to_ref": "..." }` | `201 Created` |
| `GET` | `/api/v1/admin/users/{ref}` | Get user details (Admin) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/admin/users/{ref}` | Update user details (Admin) | `{ "full_name": "...", "mobile": "..." }` | `200 OK` |
| `POST` | `/api/v1/admin/users/{ref}/deactivate` | Deactivate user account (Admin) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/admin/users/{ref}/activate` | Activate user account (Admin) | Path: `{ref}` | `200 OK` |
| `GET` | `/api/v1/admin/roles` | List available system RBAC roles (Admin) | None | `200 OK` |
| `GET` | `/api/v1/admin/permissions` | List permission catalogue (Admin) | None | `200 OK` |
| `POST` | `/api/v1/admin/roles/{role}/permissions` | Assign permissions to role (Admin) | `{ "permissions": ["leads.view", "leads.create"] }` | `200 OK` |

---

### TAG 06: Zero-Local Dynamic UI Form Engine (`/ui/forms`, `/forms`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/ui/forms` | List all 20 database-driven UI form schemas (All Users) | `?category=crm` | `200 OK` (Array of form schemas) |
| `GET` | `/api/v1/ui/forms/{form_key}` | Get form schema with all fields, validations & dependencies (All Users) | Path: `{form_key}` (e.g. `leads_create_edit`, `orders_create_edit`) | `200 OK` (JSON Schema, Field Grid, Validation JSON) |
| `POST` | `/api/v1/ui/forms/{form_key}/validate` | **Server-side dynamic form validator** against DB rules (All Users) | Path: `{form_key}`, Body: `{ "data": { ... }, "step": 1 }` | `200 OK` (`{"valid": true}`) or `422 Unprocessable` (`{"valid": false, "errors": { ... }}`) |
| `GET` | `/api/v1/forms` | Form schema listing alias (All Users) | None | `200 OK` |
| `GET` | `/api/v1/forms/{form_key}` | Form schema definition alias (All Users) | Path: `{form_key}` | `200 OK` |
| `POST` | `/api/v1/forms/{form_key}/validate` | Dynamic validation alias (All Users) | Path: `{form_key}`, Body: `{ "data": { ... } }` | `200 OK` / `422 Unprocessable` |
| `GET` | `/api/v1/admin/forms` | Admin manage form schemas (Admin) | `?page=1` | `200 OK` |
| `POST` | `/api/v1/admin/forms` | Register custom form schema (Admin) | `{ "form_key": "...", "form_title": "...", "entity_type": "..." }` | `201 Created` |
| `GET` | `/api/v1/admin/forms/{ref}` | Get form schema details (Admin) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/admin/forms/{ref}` | Update form schema metadata (Admin) | `{ "form_title": "...", "is_wizard": 1 }` | `200 OK` |
| `GET` | `/api/v1/admin/forms/{ref}/fields` | List form fields configuration (Admin) | None | `200 OK` (Field records, sort orders, regexes) |
| `POST` | `/api/v1/admin/forms/{ref}/fields` | Add new field to dynamic form (Admin) | `{ "field_key": "...", "field_label": "...", "component_type": "...", "validation_rules": { ... } }` | `201 Created` |
| `PATCH` | `/api/v1/admin/forms/{ref}/fields/{field_ref}` | Update field configuration (Admin) | `{ "field_label": "...", "validation_rules": { ... } }` | `200 OK` |
| `DELETE` | `/api/v1/admin/forms/{ref}/fields/{field_ref}` | Remove field from form schema (Admin) | Path: `{field_ref}` | `200 OK` |

---

### TAG 07: Masters & Dynamic Catalog Values (`/admin/masters`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/masters/catalog-values` | Fetch lookup options (Admin, Sales) | `?entity_type=lead_source&is_active=1` | `200 OK` (Array of key-values) |
| `POST` | `/api/v1/admin/masters/catalog-values` | Create new master lookup item (Admin) | `{ "entity_type": "...", "code": "...", "label": "...", "sort_order": 1 }` | `201 Created` |
| `GET` | `/api/v1/admin/masters/categories` | List product categories (Admin, Sales) | `?status=ACTIVE` | `200 OK` |
| `POST` | `/api/v1/admin/masters/categories` | Create product category (Admin) | `{ "category_name": "...", "description": "..." }` | `201 Created` |
| `GET` | `/api/v1/admin/masters/transporters` | List registered transporters (Admin) | `?status=ACTIVE` | `200 OK` |
| `POST` | `/api/v1/admin/masters/transporters` | Register new logistics transporter (Admin) | `{ "transporter_name": "...", "contact_person": "...", "mobile": "..." }` | `201 Created` |
| `GET` | `/api/v1/admin/settings` | Get franchise settings & location info (Admin) | None | `200 OK` (`franchise_ref`, `state_ref`, `gstin`, `branding`) |
| `PATCH` | `/api/v1/admin/settings` | Update franchise settings, location & branding (Admin) | `{ "state_ref": "...", "franchise_name": "...", "gstin": "...", "brand_accent_hex": "#FF7A00" }` | `200 OK` |

---

### TAG 08: Products, Pricing Engine & Schemes (`/admin/products`, `/admin/pricing`, `/admin/schemes`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/products` | Paginated product catalog (Admin, Sales) | `?category_ref=...&search=...&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/products` | Create pharmaceutical product SKU (Admin) | `{ "product_name": "...", "sku_code": "...", "mrp": 120.00, "pts": 75.00, "gst_rate": 12.00, ... }` | `201 Created` |
| `GET` | `/api/v1/admin/products/{ref}` | SKU details & pricing history (Admin) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/admin/products/{ref}` | Update SKU specification (Admin) | Path: `{ref}`, Body: `{ ... }` | `200 OK` |
| `POST` | `/api/v1/admin/pricing/calculate` | **Server-side rate resolution** (Admin, Sales, Portal) | `{ "party_ref": "...", "items": [{ "product_ref": "...", "quantity": 100 }] }` | `200 OK` (Resolved unit prices, rate source) |
| `GET` | `/api/v1/admin/pricing-tiers` | List pricing tiers (Admin) | None | `200 OK` |
| `POST` | `/api/v1/admin/pricing-tiers` | Create pricing tier (Admin) | `{ "tier_code": "TIER_A", "tier_name": "Wholesale Tier A" }` | `201 Created` |
| `GET` | `/api/v1/admin/pricing-tiers/{ref}` | Get pricing tier details (Admin) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/admin/pricing-tiers/{ref}` | Update pricing tier (Admin) | `{ "tier_name": "..." }` | `200 OK` |
| `GET` | `/api/v1/admin/schemes` | List promotional schemes (Admin, Sales) | `?status=ACTIVE` | `200 OK` |
| `POST` | `/api/v1/admin/schemes` | Create promotional scheme (Admin) | `{ "scheme_name": "...", "valid_from": "...", "valid_to": "...", "rules": [...] }` | `201 Created` |
| `GET` | `/api/v1/admin/schemes/{ref}` | Get scheme details (Admin) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/admin/schemes/{ref}` | Update scheme details (Admin) | `{ ... }` | `200 OK` |
| `POST` | `/api/v1/admin/schemes/calculate` | **Server-side scheme engine** (Admin, Sales, Portal) | `{ "party_ref": "...", "items": [{ "product_ref": "...", "quantity": 100 }] }` | `200 OK` (Applied schemes, free quantities) |

---

### TAG 09: Leads, Activities & Follow-ups (`/admin/leads`, `/admin/follow-ups`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/leads` | List leads (Admin, Sales) | `?status=...&assigned_user_ref=...&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/leads` | Create new lead (Admin, Sales) | `{ "contact_name": "...", "firm_name": "...", "mobile": "...", "source": "...", ... }` | `201 Created` |
| `GET` | `/api/v1/admin/leads/{ref}` | Get lead 360 profile (Admin, Sales) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/admin/leads/{ref}` | Update lead details (Admin, Sales) | `{ ... }` | `200 OK` |
| `POST` | `/api/v1/admin/leads/{ref}/remarks` | Add activity remark (Admin, Sales) | `{ "remark": "...", "next_followup_at": "..." }` | `201 Created` |
| `POST` | `/api/v1/admin/leads/{ref}/convert` | **Convert lead into active party** (Admin, Sales) | `{ "dl_number_1": "...", "pricing_tier_ref": "...", "credit_limit": 50000.00 }` | `200 OK` (Creates `parties` & `users` records) |
| `GET` | `/api/v1/admin/follow-ups` | Follow-up pipeline by bucket (Admin, Sales) | `?bucket=today` (`today`,`upcoming`,`missed`) | `200 OK` |
| `POST` | `/api/v1/admin/follow-ups/{ref}/done` | Mark follow-up completed (Admin, Sales) | `{ "notes": "...", "next_followup_date": "..." }` | `200 OK` |

---

### TAG 10: Parties, Exclusivity & Territories (`/admin/parties`, `/admin/territories`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/parties` | List parties/franchise partners (Admin, Sales) | `?page=1&status=ACTIVE&search=...` | `200 OK` |
| `POST` | `/api/v1/admin/parties` | Create new party (Admin) | `{ "firm_name": "...", "mobile": "...", "dl_number_1": "...", "pricing_tier_ref": "...", ... }` | `201 Created` |
| `GET` | `/api/v1/admin/parties/{ref}` | Party 360 profile & ledger (Admin, Sales) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/admin/parties/{ref}` | Update party details (Admin) | `{ ... }` | `200 OK` |
| `GET` | `/api/v1/admin/parties/{ref}/ledger` | Party financial account statement (Admin, Sales) | `?from=...&to=...` | `200 OK` |
| `GET` | `/api/v1/admin/territories` | List franchise sales territories (Admin, Sales) | `?page=1` | `200 OK` |
| `POST` | `/api/v1/admin/territories` | Create sales territory (Admin) | `{ "territory_name": "...", "state_ref": "...", "district_refs": [...] }` | `201 Created` |
| `GET` | `/api/v1/admin/territories/{ref}` | Get territory details (Admin, Sales) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/admin/territories/{ref}` | Update territory assignments (Admin) | `{ ... }` | `200 OK` |
| `POST` | `/api/v1/admin/territories/validate` | **Exclusivity lock validator** (Admin, Sales, Portal) | `{ "party_ref": "...", "pincode": "..." }` | `200 OK` (`allowed: true/false`, lock details) |
| `POST` | `/api/v1/admin/parties/{ref}/territories` | Allocate districts/pincodes to party (Admin) | `{ "districts": [...], "pincodes": [...] }` | `200 OK` |

---

### TAG 11: Inventory, Batches, FEFO & Adjustments (`/admin/inventory`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/inventory/batches` | List inventory batches (Admin) | `?product_ref=...&status=AVAILABLE&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/inventory/batches` | Goods receipt inward entry / GRN (Admin) | `{ "product_ref": "...", "batch_no": "...", "mfg_date": "...", "expiry_date": "...", "received_qty": 500, "purchase_rate": 55.00 }` | `201 Created` |
| `GET` | `/api/v1/admin/inventory/batches/{ref}` | Get batch details (Admin) | Path: `{ref}` | `200 OK` |
| `GET` | `/api/v1/admin/inventory/stock` | Product-wise aggregate physical and available stock (Admin) | `?page=1` | `200 OK` |
| `GET` | `/api/v1/admin/inventory/near-expiry`| List near-expiry batches (Admin) | `?threshold_days=90` | `200 OK` |
| `GET` | `/api/v1/admin/inventory/ledger` | Immutable movement audit trail (Admin) | `?product_ref=...&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/inventory/adjust` | Physical stock adjustment (Admin) | `{ "batch_ref": "...", "adjusted_qty": -5, "reason": "Damaged in transit" }` | `200 OK` |

---

### TAG 12: Orders, Server Calculation & FEFO Reservation (`/admin/orders`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `POST` | `/api/v1/admin/orders/calculate` | **Full Admin Commercial Order Calculation Engine** (Admin, Sales) | `{ "party_ref": "...", "items": [{ "product_ref": "...", "quantity": 100 }] }` | `200 OK` (Full calculation breakdown: rates, schemes, GST, credit balance check, approval decision) |
| `GET` | `/api/v1/admin/orders` | List sales orders (Admin, Sales) | `?status=...&party_ref=...&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/orders` | Create sales order (Admin, Sales) | `{ "party_ref": "...", "order_date": "...", "items": [{ "product_ref": "...", "quantity": 100 }] }` | `201 Created` |
| `GET` | `/api/v1/admin/orders/{ref}` | Order details, lines & reservations (Admin, Sales) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/admin/orders/{ref}/confirm` | **Confirm order & reserve FEFO batches** (Admin) | None | `200 OK` (Locks stock in `stock_reservations`) |
| `POST` | `/api/v1/admin/orders/{ref}/cancel` | Cancel order & release reservations (Admin, Sales) | `{ "reason": "Customer cancellation" }` | `200 OK` |

---

### TAG 13: Invoices, Sequential Billing & Dispatches (`/admin/invoices`, `/admin/dispatches`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/invoices` | List tax invoices (Admin) | `?page=1&balance_due=gt0` | `200 OK` |
| `POST` | `/api/v1/admin/invoices` | Generate tax invoice from confirmed order (Admin) | `{ "order_ref": "...", "invoice_date": "..." }` | `201 Created` (Sequential numbering via DB sequence) |
| `GET` | `/api/v1/admin/invoices/{ref}` | Get tax invoice details & GST split (Admin) | Path: `{ref}` | `200 OK` |
| `GET` | `/api/v1/admin/invoices/{ref}/print` | Download printable legal GST tax invoice (Admin) | Path: `{ref}` | `200 OK` (PDF/HTML Stream) |
| `POST` | `/api/v1/admin/invoices/{ref}/cancel` | Cancel tax invoice and credit note issuance (Admin) | `{ "reason": "..." }` | `200 OK` |
| `GET` | `/api/v1/admin/dispatches` | List dispatches (Admin) | `?status=PENDING&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/dispatches` | Create dispatch entry & assign transporter (Admin) | `{ "order_ref": "...", "transporter_ref": "...", "lr_number": "...", "lr_date": "...", "boxes_count": 4 }` | `201 Created` |
| `GET` | `/api/v1/admin/dispatches/{ref}` | Get dispatch details (Admin) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/admin/dispatches/{ref}/ship` | Mark consignment dispatched / in transit (Admin) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/admin/dispatches/{ref}/deliver` | Confirm delivery at destination (Admin) | `{ "delivery_date": "...", "received_by": "..." }` | `200 OK` |

---

### TAG 14: Payments, Allocations & Outstanding Ageing (`/admin/payments`, `/admin/outstanding`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/payments` | List payment receipts (Admin) | `?party_ref=...&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/payments` | Record payment receipt (Admin) | `{ "party_ref": "...", "payment_date": "...", "payment_mode": "NEFT", "reference_no": "...", "amount": 25000.00 }` | `201 Created` |
| `GET` | `/api/v1/admin/payments/{ref}` | Payment receipt details & allocations (Admin) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/admin/payments/{ref}/allocate` | **Allocate payment to unpaid invoices** (Admin) | `{ "allocations": [{ "invoice_ref": "...", "amount": 15000.00 }] }` | `200 OK` (Updates `invoices.balance_due` & party ledger) |
| `GET` | `/api/v1/admin/outstanding` | Real-time party outstanding aging (Admin) | `?page=1` | `200 OK` |

---

### TAG 15: Post-Dated Cheques Management (`/admin/pdcs`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/pdcs` | List Post-Dated Cheques in custody (Admin) | `?status=IN_HAND&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/pdcs` | Register new PDC (Admin) | `{ "party_ref": "...", "cheque_number": "...", "cheque_date": "...", "amount": 50000.00, "bank_name": "..." }` | `201 Created` |
| `GET` | `/api/v1/admin/pdcs/{ref}` | Get PDC record details (Admin) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/admin/pdcs/{ref}/deposit` | Mark PDC deposited in bank (Admin) | `{ "deposited_at": "..." }` | `200 OK` |
| `POST` | `/api/v1/admin/pdcs/{ref}/clear` | Mark PDC cleared / funds realized (Admin) | `{ "cleared_at": "..." }` | `200 OK` |
| `POST` | `/api/v1/admin/pdcs/{ref}/bounce` | Mark PDC bounced / dishonored (Admin) | `{ "bounced_at": "...", "reason": "Insufficient funds" }` | `200 OK` |

---

### TAG 16: Financial Year Closure (`/admin/financial`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/financial/closures` | List financial year closure histories (Admin) | None | `200 OK` |
| `POST` | `/api/v1/admin/financial/closures` | Execute financial year-end closing (Admin) | `{ "fiscal_year": "2025-26", "closing_date": "2026-03-31" }` | `200 OK` |
| `GET` | `/api/v1/admin/financial/closures/{year}`| Inspect specific financial year closure state (Admin) | Path: `{year}` | `200 OK` |

---

### TAG 17: Daily Call Reports - SFA (`/admin/dcr`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/dcr` | List Daily Call Reports (Admin, Sales) | `?user_ref=...&date=...&page=1` | `200 OK` |
| `POST` | `/api/v1/admin/dcr` | Create DCR entry for current day (Sales Rep) | `{ "report_date": "2026-09-29", "work_type": "FIELD", "route_ref": "..." }` | `201 Created` |
| `GET` | `/api/v1/admin/dcr/{ref}` | Get full DCR details with visits (Admin, Sales) | Path: `{ref}` | `200 OK` |
| `PATCH` | `/api/v1/admin/dcr/{ref}` | Update DCR details before submission (Sales Rep) | `{ "remarks": "..." }` | `200 OK` |
| `POST` | `/api/v1/admin/dcr/{ref}/submit` | Submit DCR for managerial review (Sales Rep) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/admin/dcr/{ref}/approve` | Manager approve submitted DCR (Admin, Manager) | Path: `{ref}` | `200 OK` |
| `POST` | `/api/v1/admin/dcr/{ref}/reject` | Manager reject DCR with reason (Admin, Manager) | `{ "rejection_reason": "Incomplete visits" }` | `200 OK` |
| `POST` | `/api/v1/admin/dcr/{ref}/visits` | Log doctor/chemist visit within DCR (Sales Rep) | `{ "party_type": "DOCTOR", "target_ref": "...", "visit_time": "11:30", "products_detailed": [...] }` | `201 Created` |

---

### TAG 18: Distributor Self-Service Portal (`/portal`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/portal/catalogue` | Browse catalog with personalized tier rates (Distributor) | `?category_ref=...&page=1` | `200 OK` |
| `POST` | `/api/v1/portal/cart/calculate`| **Server-side cart calculation with GST** (Distributor) | `{ "items": [{ "product_ref": "...", "quantity": 50 }] }` | `200 OK` (Full rates, schemes, tax) |
| `GET` | `/api/v1/portal/orders` | Distributor order history (Distributor) | `?page=1` | `200 OK` |
| `POST` | `/api/v1/portal/orders` | Submit direct portal sales order (Distributor) | `{ "items": [...] }` | `201 Created` |
| `GET` | `/api/v1/portal/orders/{ref}` | View order details and dispatch tracking (Distributor) | Path: `{ref}` | `200 OK` |
| `GET` | `/api/v1/portal/invoices` | List distributor tax invoices (Distributor) | `?page=1` | `200 OK` |
| `GET` | `/api/v1/portal/invoices/{ref}` | View tax invoice details (Distributor) | Path: `{ref}` | `200 OK` |
| `GET` | `/api/v1/portal/outstanding` | Real-time distributor outstanding balance (Distributor) | None | `200 OK` |
| `GET` | `/api/v1/portal/profile` | Distributor firm profile & credit limit (Distributor) | None | `200 OK` |

---

### TAG 19: Analytics, Auditing & Reports (`/admin/dashboard`, `/admin/analytics`, `/admin/audit`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/admin/dashboard` | Real-time franchise executive dashboard KPIs (Admin) | None | `200 OK` (Orders, revenue, stock, receivables) |
| `GET` | `/api/v1/admin/analytics/reports/{key}` | Executive domain reports (Admin) | Path: `{key}` (`sales_by_product`, `sales_by_party`, `outstanding_aging`), `?from=...&to=...` | `200 OK` |
| `GET` | `/api/v1/admin/audit` | Mutation & access audit trail (Admin) | `?table_name=...&action=...&page=1` | `200 OK` |

---

### TAG 20: Notifications & Asynchronous Webhooks (`/notifications`, `/webhooks`)
| Verb | Endpoint | Summary & Role Access | Parameters / Body | Responses |
|---|---|---|---|---|
| `GET` | `/api/v1/notifications` | List notifications for authenticated user (All Users) | `?page=1&status=UNREAD` | `200 OK` |
| `GET` | `/api/v1/notifications/unread-count` | Badge counter for unread notifications (All Users) | None | `200 OK` (`{"unread_count": 5}`) |
| `PATCH`| `/api/v1/notifications/{id}/read` | Mark individual notification as read (All Users) | Path: `{id}` | `200 OK` |
| `POST` | `/api/v1/notifications/read-all` | Mark all notifications as read (All Users) | None | `200 OK` |
| `POST` | `/api/v1/webhooks` | Asynchronous external event ingestion (Public/Webhook Key)| `{ "event": "...", "payload": { ... } }` | `200 OK` / `202 Accepted` |

---

## 3. OpenAPI 3.0.3 Contract Audit & Parity Guarantee

* **OpenAPI File Location:** `docs/front-end/openapi.yaml` and `public/api-docs/openapi.yaml`
* **Route Coverage:** 192 registered routes in `bootstrap/routes.php` are mapped 1-to-1 to corresponding OpenAPI operation definitions with zero omissions.
* **Contract Verification Test:** `tests/contract/verify_openapi_financial_contract.php` and `tests/Agents/agent-openapi.php` continuously validate that all routes, security schemes (`bearerAuth`), request envelopes, and financial DTO schemas remain 100% in sync.
