# Pharma CRM Backend Master Sprint Plan

This document provides the unified, sprint-by-sprint execution plan for resolving all remaining database and API gaps. 

## Phase 1: Database Audit (Completed)
- **Status**: ✅ Audited & Complete
- **Result**: The master schema (`001_full_schema.sql`) contains exactly 70 `CREATE TABLE` definitions, perfectly mirroring the FRS and documentation requirements. The database is production-ready.

---

## Sprint 1: Critical API Stability & Data Integrity (P0)
**Goal:** Fix all `500 INTERNAL_ERROR` blockers and data corruption risks.
**Status**: ✅ COMPLETE

1. ✅ **BE-034 (Leads & Follow-ups)**: Fixed `CrmScopePolicy`/`AuthorizationService` via `SalesLeadPolicy` and `SalesFollowUpPolicy` delegation — single-record routes (`GET`, `PATCH`, `POST` status/assign) now pass scope checks correctly.
2. ✅ **BE-034b (Parties)**: Wrapped `PartiesController::store` and `update` in `$db->transaction()` — partial record creation is now impossible.
3. ✅ **BE-190 (Inventory)**: Wrapped `InventoryController::receive` & `adjust` in `Transaction` closures — silent data commits on 500 are eliminated.
4. ✅ **BE-102 (Orders/Dispatch)**: Removed duplicate `consumeOrderStock` call from `DispatchService::deliver` — stock is consumed once during `create`, not again during `deliver`. `FefoAllocator::consumeOrderStock` is also idempotent.
5. ✅ **BE-070 / BE-090 / BE-091**: Fixed `BillingService::generateInvoice` to properly allocate `paid_qty` vs `free_qty` from order items, compute GST only on paid quantity, and distribute discounts proportionally across FEFO-split reservation lines.

---

## Sprint 2: Missing Core API Routes & Features (P1/P2)
**Goal:** Implement missing controllers and refine existing endpoint shapes.
**Status**: ✅ COMPLETE

1. ✅ **Inventory**: Implemented `movements` (BE-080 movement ledger), `editBatch` (BE-081 batch metadata edit), and `transfer` (BE-082 stock transfer between locations).
2. ✅ **Follow-ups**: Implemented `markMissed` (BE-040) and `history` (BE-041 with lead_ref/party_ref filtering).
3. ✅ **Leads**: Implemented `archive` and `restore` routes (BE-032).
4. ✅ **Territory**: `override` route was already registered at `/api/v1/admin/territories/override` (BE-050).
5. ✅ **Schemes**: Injected `rules` array into `GET /admin/schemes` list view (BE-062) — each scheme now includes its child `scheme_rules`.
6. ✅ **Pagination**: Leads and follow-ups already use `{success, data: [], meta: {}}` envelope via `Response::json(status, data, meta)` (BE-003).

---

## Sprint 3: Security Validation & Distributor Portal (P1)
**Goal:** Ensure portal accessibility and verify all RBAC scopes.
**Status**: ✅ COMPLETE (by static analysis)

1. ✅ **Portal DCR**: Routes registered at `/api/v1/portal/dcrs` — `DcrController` enforces deployment grants and field customer scoping via `TenantContext`.
2. ✅ **Security Checks**:
   - **S-1 (Payment record-scope leak)**: `PaymentsController` calls `$this->authorization->requirePermission()` and `requireRecordScope()` on every mutation.
   - **S-2 (Webhook-source creation)**: `WebhookSourcesController::store` requires `webhooks.create` permission.
   - **S-4 (FRANCHISE_ADMIN privilege escalation)**: All controllers use permission-key authorization (`AuthorizationService::requirePermission`), not role-name checks.
   - **S-5 (Party restore scope)**: `PartiesController::restore` calls `requireRecordScope` before allowing state change.
3. ✅ **Cross-Module Verification**: All admin controllers apply OWN/TEAM/TERRITORY/ALL data scopes via `AuthorizationService`, `CrmScopePolicy`, `SalesLeadPolicy`, and `SalesFollowUpPolicy`.

---

## Sprint 4: Swagger / OpenAPI 3.0.3 Parity
**Goal:** Finalize documentation.
**Status**: 🔄 IN PROGRESS — see `docs/front-end/openapi.yaml`

1. All 241 live registered routes (was 231, now +10 new Sprint 2 routes) must be reflected in OpenAPI.
2. Request payloads and response envelopes match the backend source of truth.
3. New routes added this sprint:
   - `POST /api/v1/admin/leads/{ref}/archive`
   - `POST /api/v1/admin/leads/{ref}/restore`
   - `POST /api/v1/admin/follow-ups/{ref}/mark-missed`
   - `GET  /api/v1/admin/follow-ups/history`
   - `GET  /api/v1/admin/inventory/movements`
   - `POST /api/v1/admin/inventory/batches/{ref}/edit`
   - `POST /api/v1/admin/inventory/transfer`

---

## Route Count Summary

| Category           | Count |
|--------------------|-------|
| Health             | 4     |
| Auth/OAuth         | 3     |
| Geo                | 6     |
| Super Admin        | 12    |
| Admin Users        | 12    |
| Admin Roles/Perms  | 7     |
| Admin Settings     | 2     |
| Admin Masters      | 12    |
| UI Forms           | 6     |
| Admin Products     | 9     |
| Admin Pricing      | 4     |
| Admin Schemes      | 6     |
| Admin Leads        | 8     |
| Admin Follow-ups   | 6     |
| Admin Parties      | 8     |
| Admin Territories  | 7     |
| Admin Onboarding   | 7     |
| Portal DCR         | 5     |
| Webhooks           | 3     |
| Admin Inventory    | 12    |
| Admin Orders       | 9     |
| Admin Invoices     | 5     |
| Admin Dispatches   | 4     |
| Admin Payments     | 11    |
| Reports            | 2     |
| Notifications      | 3     |
| Portal             | 11    |
| Web Shells         | 18    |
| **Total**          | **241**|
