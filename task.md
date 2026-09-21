# Pharma CRM — Remaining Development Tasks

## Batch 1 — Config Files + Core Services
- [x] `app/Config/tenancy.php`
- [x] `app/Config/theme.php`
- [x] `app/Config/security.php`
- [x] `app/Config/cache.php`
- [x] `app/Config/queue.php`
- [x] `app/Config/integrations.php`
- [x] `app/Core/Security/Csp.php`
- [x] `app/Core/ThemeResolver.php`

## Batch 2 — CSS Enhancement + Icons + Error Pages
- [x] Expand `public/assets/css/crm-ui.css` (tables, badges, modals, stat cards, tabs, spinners, alerts, print)
- [x] `public/assets/img/icons.svg`
- [x] `app/Views/errors/403.php`
- [x] `app/Views/errors/404.php`
- [x] `app/Views/errors/422.php`
- [x] `app/Views/errors/429.php`
- [x] `app/Views/errors/500.php`

## Batch 3 — Shared UI Components + Layout Enhancement
- [x] `app/Views/components/data-table.php`
- [x] `app/Views/components/modal.php`
- [x] `app/Views/components/stat-card.php`
- [x] `app/Views/components/badge.php`
- [x] `app/Views/components/form-group.php`
- [x] `app/Views/components/alert.php`
- [x] `app/Views/components/empty-state.php`
- [x] `app/Views/components/pagination.php`
- [x] Enhance `app/Views/layouts/shell.php` (full nav, icons, all surfaces)

## Batch 4 — JS Modules (Per-Page)
- [x] Enhance `public/assets/js/crm-ui.js` (client router, modal controller, loading states)
- [x] `public/assets/js/modules/dashboard.js`
- [x] `public/assets/js/modules/leads.js`
- [/] `public/assets/js/modules/parties.js`
- [ ] `public/assets/js/modules/products.js`
- [ ] `public/assets/js/modules/orders.js`
- [ ] `public/assets/js/modules/invoices.js`
- [ ] `public/assets/js/modules/inventory.js`
- [ ] `public/assets/js/modules/payments.js`
- [ ] `public/assets/js/modules/dispatches.js`
- [ ] `public/assets/js/modules/users.js`
- [ ] `public/assets/js/modules/categories.js`
- [ ] `public/assets/js/modules/tiers.js`
- [ ] `public/assets/js/modules/prices.js`
- [ ] `public/assets/js/modules/schemes.js`
- [ ] `public/assets/js/modules/territories.js`
- [ ] `public/assets/js/modules/followups.js`
- [ ] `public/assets/js/modules/settings.js`
- [ ] `public/assets/js/modules/reports.js`
- [ ] `public/assets/js/modules/notifications.js`
- [ ] `public/assets/js/modules/portal-dashboard.js`
- [ ] `public/assets/js/modules/super-dashboard.js`

## Batch 5 — Web Routes, Controllers, DI Gaps
- [ ] Expand `bootstrap/routes.php` (admin CRUD pages, sales, portal, super)
- [ ] Expand `app/Http/Controllers/Web/DashboardController.php` (all page methods)
- [ ] Expand `bootstrap/bindings.php` (UserService, ReportService, DcrService)

## Batch 6 — Tasks File Update
- [ ] Update `tasks.md` — expand P1-S11–P1-S20, mark all completed
