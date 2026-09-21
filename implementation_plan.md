# Pharma CRM — Remaining Development Implementation Plan

## Background

All P0–P8 tasks in [tasks.md](file:///e:/Projects/PHP/Pharma-CRM/tasks.md) are marked `[x]` (complete). However, a comprehensive codebase audit reveals **significant gaps** between what the task tracker claims and what actually exists. The backend API surface is largely complete (70+ endpoints, 31 repositories, 25 domain services, 26 test agents), but the **UI layer, config files, and several structural pieces** are stubs or empty.

---

## Gap Analysis Summary

| Area | Status | Gap |
|---|---|---|
| **Backend API (Controllers, Routes)** | ✅ ~95% complete | Minor: Sales-specific API routes missing |
| **Domain Services** | ✅ ~90% complete | `Products` domain empty, `WhatsApp` domain empty |
| **Repositories + Contracts** | ✅ Complete | 29 interfaces, 31 implementations |
| **DI Bindings** | ✅ ~90% complete | Missing: UserService, ReportService, DcrService bindings |
| **Middleware** | ✅ Complete | 12 middleware files |
| **Test Agents** | ✅ Complete | 26 agents |
| **Config Files** | ⚠️ ~30% complete | Only `app.php`, `auth.php`, `database.php` exist. Missing 5+ configs |
| **CSS/Theme System** | ⚠️ Minimal | crm-ui.css has basics, but no table/badge/modal/stat-card styles |
| **JS Modules (per-page)** | ⚠️ Only 2 exist | Missing modules for 15+ admin/sales/portal/super pages |
| **PHP View Templates** | ❌ Nearly empty | Only `layouts/shell.php` + `auth/login.php`. All surface-specific directories empty |
| **Shared UI Components** | ❌ Empty | `Views/components/` is empty |
| **Error Pages** | ❌ Empty | `Views/errors/` is empty |
| **FormRequests** | ❌ Empty | Input validation objects not created |
| **ViewModels** | ❌ Empty | No data shaping layer |
| **Icons** | ❌ Missing | No `icons.svg` sprite |
| **Web Controllers (per surface)** | ⚠️ Minimal | Only 2 generic Web controllers. No per-surface breakdown |
| **Admin sidebar nav** | ⚠️ Partial | Missing: Orders, Invoices, Dispatches, Payments, Inventory, Users, Settings, Reports, Notifications |

---

## Proposed Changes

Work is organized in 6 batches. Each batch is self-contained and builds on the previous.

> [!IMPORTANT]
> This is a large plan (~40 files). Each batch should be committed atomically.

---

### Batch 1 — Missing Config Files + Core Services

Fill the config directory and add missing core utilities referenced in README.

#### [NEW] `app/Config/tenancy.php`
Tenant header names, scope defaults, cross-tenant bypass config.

#### [NEW] `app/Config/theme.php`
Surface-to-theme mapping (`super → theme-super`, etc.).

#### [NEW] `app/Config/security.php`
CSP policy directives, HSTS config, rate limit defaults.

#### [NEW] `app/Config/cache.php`
File cache root path, default TTLs.

#### [NEW] `app/Config/queue.php`
Job queue table name, worker batch size, retry limits.

#### [NEW] `app/Config/integrations.php`
Webhook defaults, SMTP settings, SMS/WhatsApp gateway stubs.

#### [NEW] `app/Core/Security/Csp.php`
Content Security Policy builder — generates CSP header string from config.

#### [NEW] `app/Core/ThemeResolver.php`
Route-prefix → theme-name mapping used by shell layout.

---

### Batch 2 — CSS Enhancement + Icons + Error Pages

Expand the design system and add missing visual assets.

#### [MODIFY] `public/assets/css/crm-ui.css`
Add missing component styles:
- Data tables (`.data-table`, `.dt-header`, `.dt-row`, `.dt-cell`)
- Badges/Status pills (`.badge`, `.badge-success`, `.badge-warning`, `.badge-danger`)
- Modal overlay + dialog (`.modal-overlay`, `.modal-dialog`, `.modal-header`, `.modal-body`, `.modal-footer`)
- Stat cards (`.stat-card`, `.stat-value`, `.stat-label`, `.stat-icon`)
- Tabs (`.tabs`, `.tab-item`, `.tab-active`)
- Loading spinners (`.spinner`, `.skeleton`)
- Nav active state (`.btn-ghost.active`)
- Responsive table wrapper
- Print styles (`@media print`)
- Empty state component (`.empty-state`)
- Form select, textarea, checkbox styles
- Alert component (`.alert`, `.alert-info`, `.alert-warning`, `.alert-error`)

#### [NEW] `public/assets/img/icons.svg`
SVG sprite with all icons: dashboard, leads, parties, products, orders, invoices, payments, inventory, settings, users, reports, notifications, logout, search, filter, download, plus, edit, trash, check, x, bell, chart.

#### [NEW] `app/Views/errors/404.php`
Not Found error page using theme variables.

#### [NEW] `app/Views/errors/403.php`
Forbidden error page.

#### [NEW] `app/Views/errors/500.php`
Internal Server Error page.

#### [NEW] `app/Views/errors/422.php`
Validation Error page.

#### [NEW] `app/Views/errors/429.php`
Rate Limited error page.

---

### Batch 3 — Shared UI Components + Layout Enhancement

Build reusable PHP components and enhance the shell layout.

#### [NEW] `app/Views/components/data-table.php`
Server-rendered data table component with sortable headers, pagination, and search.

#### [NEW] `app/Views/components/modal.php`
Reusable modal dialog component.

#### [NEW] `app/Views/components/stat-card.php`
Dashboard stat card component (icon, value, label, trend).

#### [NEW] `app/Views/components/badge.php`
Status badge/pill component.

#### [NEW] `app/Views/components/form-group.php`
Form field component (label + input + error display).

#### [NEW] `app/Views/components/alert.php`
Alert/notification banner component.

#### [NEW] `app/Views/components/empty-state.php`
Empty state placeholder for lists with no data.

#### [NEW] `app/Views/components/pagination.php`
Pagination controls component.

#### [MODIFY] `app/Views/layouts/shell.php`
- Add full sidebar navigation with icons for all modules
- Add Orders, Invoices, Dispatches, Payments, Inventory, Users, Settings, Reports, Notifications nav items
- Add Super Admin navigation items
- Add Portal-specific navigation items
- Add active link highlighting based on current path
- Add notification bell indicator in header

---

### Batch 4 — JS Modules (Per-Page Feature Modules)

Each module handles fetching data from API, rendering into the `#view` container, and managing CRUD operations — all without innerHTML (using `h()` DOM helper).

#### [MODIFY] `public/assets/js/crm-ui.js`
- Add client-side router to load correct module based on URL path
- Add modal open/close controller
- Add loading state management
- Add confirmation dialog utility

#### [NEW] `public/assets/js/modules/dashboard.js`
Admin/super/sales dashboard — fetches stats, renders stat cards + recent activity.

#### [NEW] `public/assets/js/modules/leads.js`
Leads CRUD — list/search/filter, create form, status transitions, assign.

#### [NEW] `public/assets/js/modules/parties.js`
Parties list, create/edit, archive, view ledger.

#### [NEW] `public/assets/js/modules/products.js`
Products CRUD — list with category filter, create/edit, activate/deactivate/archive.

#### [NEW] `public/assets/js/modules/orders.js`
Orders list, create with line items, confirm/cancel state transitions.

#### [NEW] `public/assets/js/modules/invoices.js`
Invoice list, generate from order, view detail.

#### [NEW] `public/assets/js/modules/inventory.js`
Inventory batches list, receive stock, adjust, near-expiry view.

#### [NEW] `public/assets/js/modules/payments.js`
Payment recording, list with filters, allocation view.

#### [NEW] `public/assets/js/modules/dispatches.js`
Dispatch list, create from invoice, mark delivered.

#### [NEW] `public/assets/js/modules/users.js`
User management — list, create, activate/deactivate, reset password.

#### [NEW] `public/assets/js/modules/categories.js`
Category CRUD (simple list + inline create).

#### [NEW] `public/assets/js/modules/tiers.js`
Pricing tier CRUD.

#### [NEW] `public/assets/js/modules/prices.js`
Pricing rules — list, create, resolve preview.

#### [NEW] `public/assets/js/modules/schemes.js`
Scheme CRUD + calculator preview.

#### [NEW] `public/assets/js/modules/territories.js`
Territory assignment list, create, validate, override.

#### [NEW] `public/assets/js/modules/followups.js`
Follow-up queue — list, complete, reschedule.

#### [NEW] `public/assets/js/modules/settings.js`
Franchise settings management.

#### [NEW] `public/assets/js/modules/reports.js`
12 report types — selector, date range, render table, CSV export button.

#### [NEW] `public/assets/js/modules/notifications.js`
Notification list, mark read, mark all read.

#### [NEW] `public/assets/js/modules/portal-dashboard.js`
Portal surface — catalogue browse, cart, orders, outstanding.

#### [NEW] `public/assets/js/modules/super-dashboard.js`
Super admin — franchise management, platform stats, impersonation.

---

### Batch 5 — Web Routes, Controllers, and DI Gaps

Add missing web routes for admin pages that already have shell methods, plus sales/portal-specific routes.

#### [MODIFY] `bootstrap/routes.php`
Add missing admin web routes:
- `/admin/orders`, `/admin/invoices`, `/admin/dispatches`, `/admin/payments`
- `/admin/inventory`, `/admin/users`, `/admin/settings`, `/admin/reports`
- `/admin/notifications`
- `/super/franchises`, `/super/organizations`
- Sales routes: `/sales/leads`, `/sales/follow-ups`, `/sales/parties`
- Portal routes: `/portal/catalogue`, `/portal/orders`, `/portal/invoices`

#### [MODIFY] `app/Http/Controllers/Web/DashboardController.php`
Add methods for all new admin web routes (orders, invoices, dispatches, payments, inventory, users, settings, reports, notifications).

#### [MODIFY] `bootstrap/bindings.php`
Add missing service bindings:
- `UserService`
- `ReportService`
- `DcrService`
- `ProductService` (Domain level)

---

### Batch 6 — Tasks File Update

#### [MODIFY] `tasks.md`
Expand P1-S11 to P1-S20 into detailed tasks and mark them completed as work progresses. Add new tasks for any gaps discovered.

---

## Open Questions

> [!IMPORTANT]
> **Q1: Sales Surface API**
> The sales surface has web routes (`/sales/dashboard`, `/sales/leads`, `/sales/follow-ups`, `/sales/parties`) but NO dedicated Sales API controllers. Currently sales reps would hit the same `/api/v1/admin/*` endpoints. Should we create separate `/api/v1/sales/*` endpoints with stricter policies, or is the current approach (admin endpoints + role middleware filtering) acceptable?

> [!IMPORTANT]
> **Q2: WhatsApp Integration**
> The `Domain/WhatsApp/` directory is empty. The `integrations.php` config references WhatsApp gateway. Should we implement a real WhatsApp Business API adapter or leave it as a stub/future work?

> [!IMPORTANT]
> **Q3: DCR (Daily Call Report)**
> There's a `DcrService`, `DcrRepository`, and `DcrController` but NO web routes or tasks.md entry for DCR. Should DCR get full UI treatment in this batch?

> [!IMPORTANT]
> **Q4: Priority**
> Given the volume of work (~50+ new files), should we prioritize:
> - **A)** Full depth on Admin surface first (most used), then other surfaces
> - **B)** All surfaces equally with minimal viable pages
> - **C)** Backend gaps first (configs, bindings), then all UI simultaneously

---

## Verification Plan

### Automated Tests
```bash
php cli/test-agents.php --all
php cli/lint.php
```

### Manual Verification
- Navigate to each surface login → dashboard → all nav items
- Verify all CRUD flows: create, read, update, delete/archive
- Verify responsive layout (mobile sidebar toggle)
- Verify theme variables apply correctly per surface
- Verify error pages render correctly (404, 403, 500)
- Verify CSV export from reports page
