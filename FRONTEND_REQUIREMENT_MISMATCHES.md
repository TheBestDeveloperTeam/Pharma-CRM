# Frontend vs Approved Requirement Mismatches — TASK-001

## Confirmed mismatch — FIXED

### Data Scope selector omits `None`

- **Module:** Roles and Permissions
- **Frontend file:** `pharma-sales-crm/src/features/roles/RoleFormPage.tsx`
- **Previous frontend behavior:** `scopeOptions` contained `All`, `Territory`, `Team` and `Own`, but did not render the approved `None` scope option.
- **Approved requirement:** FRS v2.3 §44.4 defines five scopes: `All`, `Territory`, `Team`, `Own` and `None`. `None` means no access to the module.
- **Requirement source:** `pharma-sales-crm/docs/Pharma_CRM_Master_FRS_v2.3.md`, §44.4; `src/constants/permissions.ts` already declares `DataScope.None`.
- **Exact difference:** The frontend type/constant supports `None`, but the role-create/edit selector does not allow choosing it.
- **Applied correction:** Added `DataScope.None` to the frontend selector. Backend continues to accept and enforce `NONE` independently of the UI.
- **Status:** FIXED in `pharma-sales-crm/src/features/roles/RoleFormPage.tsx`.
- **Backend impact:** No backend blocker; normalized `auth_roles.default_scope` and `auth_role_scopes` support `NONE`.
- **Blocks API development:** No.

## No other confirmed functional mismatch found

The single-role-per-user frontend shape and permission-refresh behavior are not classified as mismatches because the approved requirements leave those points unresolved. Current mock data and DTO naming differences are not requirement mismatches.

## TASK-005 confirmed mismatch — frontend adapter required

### Order lifecycle contains Billing/Dispatch-only states

- **Module:** Orders
- **Frontend files:** `pharma-sales-crm/src/types/domain.ts`, `src/features/order/OrderDetailsPage.tsx`, and `src/mocks/mockOrders.ts`.
- **Frontend behavior:** The mock workflow advances `Draft → Confirmed → Billed → Packed → Dispatched → Delivered` and does not represent `SUBMITTED` or `PROCESSING`.
- **Approved requirement:** TASK-005 defines `DRAFT`, `SUBMITTED`, `CONFIRMED`, `PROCESSING`, `DISPATCHED`, `DELIVERED`, and `CANCELLED`, and explicitly excludes Billing, Invoice, Dispatch, Delivery, and Payment implementation.
- **Backend handling:** The TASK-005 API preserves separate Submit and Confirm endpoints and does not introduce Billing/Dispatch transition states. The frontend needs an adapter when API integration is started; no frontend integration was performed in TASK-005.
- **Status:** OPEN — does not block backend source implementation.
