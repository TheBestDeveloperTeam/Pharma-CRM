# Pharma CRM Backend Master Sprint Plan

This document provides the unified, sprint-by-sprint execution plan for resolving all remaining database and API gaps. 

## Phase 1: Database Audit (Completed)
- **Status**: ✅ Audited & Complete
- **Result**: The master schema (`001_full_schema.sql`) contains exactly 70 `CREATE TABLE` definitions, perfectly mirroring the FRS and documentation requirements. The database is production-ready.

---

## Sprint 1: Critical API Stability & Data Integrity (P0)
**Goal:** Fix all `500 INTERNAL_ERROR` blockers and data corruption risks.

1. **BE-034 (Leads & Follow-ups)**: Fix `CrmScopePolicy`/`AuthorizationService` crashing single-record routes (`GET`, `PATCH`, `POST` status/assign).
2. **BE-034b (Parties)**: Fix `POST /admin/parties` crashing on creation despite valid payloads.
3. **BE-190 (Inventory)**: Fix `POST /admin/inventory/receive` & `adjust` crashing (and prevent adjust from silently committing data on 500).
4. **BE-102 (Orders/Dispatch)**: Fix duplicate FEFO reservation consumption between `DispatchService::create` and `deliver`.
5. **BE-070 / BE-090 / BE-091**: Unblock fulfilment path by validating GST state fallback logic and free-goods billing.

---

## Sprint 2: Missing Core API Routes & Features (P1/P2)
**Goal:** Implement missing controllers and refine existing endpoint shapes.

1. **Inventory**: Implement batch edit, stock transfer (BE-081/082), and movement ledger (BE-080).
2. **Follow-ups**: Implement mark missed, history, and remarks endpoints (BE-040/041).
3. **Leads**: Implement archive and restore routes (BE-032).
4. **Territory**: Implement territory override (BE-050).
5. **Schemes**: Inject `rules` array into `GET /admin/schemes` list view (BE-062).
6. **Pagination Mismatch**: Standardize `GET /admin/leads` and `follow-ups` to use `{success, data: [], meta: {}}` rather than the legacy structure (BE-003).

---

## Sprint 3: Security Validation & Distributor Portal (P1)
**Goal:** Ensure portal accessibility and verify all RBAC scopes.

1. **Portal DCR**: Implement/fix DCR deployment grants, reachability, and field customers (BE-170, BE-171).
2. **Security Checks**: Validate fixes for legacy `FRANCHISE_ADMIN` privilege escalation (S-4), payment record-scope leak (S-1), webhook-source creation permissions (S-2), and party restore scope (S-5).
3. **Cross-Module Verification**: Ensure all controllers accurately apply the `OWN`, `TEAM`, `TERRITORY`, and `ALL` data scopes.

---

## Sprint 4: Swagger / OpenAPI 3.0.3 Parity
**Goal:** Finalize documentation.

1. Audit `docs/front-end/openapi.yaml` against all 231 live registered routes.
2. Ensure request payloads and response envelopes exactly match the backend source of truth.
3. Lock documentation for v3.0 release.
