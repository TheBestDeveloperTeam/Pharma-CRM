Status ka single source of truth: Pharma-CRM/docs/OPEN_ISSUES.md. Backend ko dene wali list: docs/BACKEND_REQUIREMENTS_FOR_FRONTEND.md

# B1: role-name checks → permission keys

Status: **code + migration written, NOT deployed / NOT run.** There is no PHP or MySQL runtime on the dev machine, so
nothing here is executed or linted yet. See "Verification" below.

## 1. Scan: every place a decision was made on the role name

Legend. **Fix**: changed in B1. **Keep**: intentionally unchanged, with the reason. **Dead**: not reachable (not in
`bootstrap/routes.php` / `middleware.php`).

| File:line (before B1) | What it checked | Should be | Risk if changed | Action |
|---|---|---|---|---|
| `Http/Controllers/Api/V1/Admin/MastersController.php:33` (`getCtx`) | `isAdmin() \|\| isSuper()` for every masters endpoint | the existing per-method `masters.*` key | Low: Admin role already holds `masters.*`; Sales holds none | **Fix**: gate removed |
| `MastersController::listTransporters / createTransporter` | role gate **only**, no permission | `masters.view` / `masters.create` | Low | **Fix**: keys added |
| `MastersController::listSettings` | role gate only | `settings.view` (**new**) | Low: granted to Admin by migration 012 | **Fix** |
| `MastersController::listTemplates` | role gate only | `notifications.manageTemplates` (existing) | Low: Admin holds it | **Fix** |
| `Admin/CatalogMastersController.php:22` | `isAdmin() \|\| isSuper()` | existing per-method `masters.*` | Low | **Fix**: gate removed |
| `Admin/ProductsController.php:24` | `isAdmin() \|\| isSuper()` | existing per-method `products.*` | Low | **Fix**: gate removed |
| `Admin/PricesController.php:28` | `isAdmin() \|\| isSuper()` | existing per-method `pricing.*` | Low | **Fix**: gate removed |
| `Admin/SchemesController.php:27` | `isAdmin() \|\| isSuper()` | existing per-method `schemes.*` | Low | **Fix**: gate removed |
| `Admin/SettingsController.php:20` | `isAdmin() \|\| isSuper()` | `settings.edit` (**new**) | Low: granted to Admin | **Fix** |
| `Admin/LeadsController.php:30` (index) | `role === 'SALES'` → own leads, else **all** | `leads.view` + `leads` scope | **Medium**: portal/other users previously saw **all** leads (no permission check); now 403 unless granted | **Fix** |
| `LeadsController.php:74` (store) | `role === 'SALES'` → self-assign | `leads.create` + scope ≠ ALL → self-assign | Low for seeded roles | **Fix** |
| `LeadsController.php:123` (assign) | `isFranchiseAdmin() \|\| isSuperAdmin()` | `leads.assign` | Low: Admin holds it, Sales Team doesn't | **Fix** (same `FORBIDDEN` code) |
| `LeadsController::show / update` | `SalesLeadPolicy` (role names) | `leads.view` / `leads.edit` + scope | Low | **Fix** |
| `LeadsController::status` | **no check at all** | `leads.edit` + scope | **Medium**: any logged-in user (even portal) could change any lead's status | **Fix** |
| `Admin/FollowUpsController.php:29, 53` | `role === 'SALES'` → own / self | `followUps.view/create/complete/reschedule` + `followUps` scope | Medium: same "no permission check" leak as leads | **Fix** |
| `Policies/SalesLeadPolicy.php:11,15` | `isSuperAdmin/isFranchiseAdmin/role === 'SALES'` | `CrmScopePolicy` (ALL/TERRITORY/TEAM/OWN/NONE) | Low | **Fix** (static API kept for tests) |
| `Policies/SalesFollowUpPolicy.php:11,15` | same | same | Low | **Fix** |
| `Admin/OrdersController.php:36` | `isSales()`: default salesperson for ALL-scope creator | ALL scope → only explicit `sales_user_ref` | Low: seeded Sales is OWN (forced self, unchanged); only a *custom* ALL-scope role changes (no longer auto-self) | **Fix** |
| `Admin/OrdersController.php:20, 69`; `InvoicesController.php:22, 68`; `DispatchesController.php:51`; `OutstandingController`; `PdcsController:6,7`; `Domain/Authorization/PartyScopePredicate`; `Domain/DCR/DcrService:6` | `isDistributor()`: restrict to own party | `isPartyBound()`: user linked to a party (data binding, not role) | Low: every DISTRIBUTOR user has `party_ref`; Super (SignInAs party) explicitly excluded, as before | **Fix** |
| `Admin/PaymentsController.php:34` | `role === 'DISTRIBUTOR'` → own party | `isPartyBound()` | Low | **Fix** |
| `Portal/PortalController.php:35` | `role === 'DISTRIBUTOR' && partyRef` (or Super) | party-bound **and** `portal.view / placeOrder / editProfile` (**new**) | Medium: distributors need the new Distributor role (migration 012 backfills all of them) | **Fix** |
| `Domain/Leads/LeadAssignmentService.php:18` | round-robin over `role = 'SALES'` | users with `leads.edit` via an active role **and** effective leads scope ≠ ALL | Low: same set for seeded roles; custom field roles now included | **Fix** |
| `Admin/UsersController::resolveRoleForCreate:284-296` | legacy `role` → surface; custom role → `SALES` | unchanged (it's the **login surface**, not authorization) | — | **Keep**: plus B1 now assigns the matching baseline role and runs the escalation check |
| `Super/FranchisesController.php:217` (createAdmin) | writes `users.role = 'FRANCHISE_ADMIN'` | + assign Admin role | High if not fixed: new franchise admins would get **zero** permissions | **Fix**: `SystemRoles::assignForLegacyRole` |
| `Core/TenantContext.php:30-35` | helper definitions | `isSuper()` kept; `isPartyBound()` added; the rest `@deprecated` | — | **Fix** |
| `AuthorizationService.php:89,107,145`, `AuditLogsController:18-21`, `ReportsController:22`, `AuthorizationController` (many), `UsersController` (franchise checks), `Task009ScopePolicy:43`, `ScopedAnalyticsService:36`, `Middleware/Tenant.php:23,69` | `isSuper()` / `isSuperAdmin()` | unchanged | — | **Keep**: Super Admin is the platform operator; its bypass must stay (requirement 3) |
| `Core/Security/TokenService.php:36,155`, `Middleware/BearerAuth.php:117`, `Super/ImpersonationController.php:36-49` | `SUPER_ADMIN` → `scp=PLATFORM`; role → surface/aud | unchanged | — | **Keep**: platform scope and login surface, not permissions |
| `Http/Controllers/Api/V1/AuthController.php:95`, `Config/auth.php:16-19` | user's role must be allowed for the OAuth client | unchanged | — | **Keep**: which *login surface* a user may use |
| `Config/theme.php:24-27` | role → UI theme | unchanged | — | **Keep**: presentation only |
| `Domain/Audit/AuditService.php:50` | records `actor_role` | unchanged | — | **Keep**: audit data |
| `Repositories/Sql/UserRepository.php:43` | `?role=` list filter | unchanged | — | **Keep**: a filter the caller chose |
| `Domain/Franchises/FranchiseDefaultsService.php:29` | tier named `DISTRIBUTOR` | — | — | **Keep**: pricing tier name, not a role |
| `Http/Controllers/Api/Orders/OrderController.php:27,64`, `Api/UserController.php:61,80`, `Api/RoleController`, `Api/AuthController`, `Domain/Users/AuthUser.php:41`, `Http/Middleware/Role.php:23`, `Http/Middleware/AuthMiddleware.php`, `Policies/Policy.php:17`, `Domain/Users/UserService.php:63` | `AuthUser::isAdmin()`, `Role` middleware, … | — | — | **Dead**: none of these classes is routed or registered. Left untouched; recommend deleting in a cleanup phase |

## 2. What changed, file by file

- `app/Core/TenantContext.php`: `isPartyBound()` added; `isAdmin/isFranchiseAdmin/isSales/isDistributor` marked
  deprecated (no routed caller left).
- `app/Domain/Authorization/CrmScopePolicy.php` (**new**): list predicates and record checks for leads/follow-ups by
  data scope (ALL / TERRITORY via `party_territories` / TEAM via `auth_user_hierarchy` / OWN / NONE). Unique
  placeholders, because PDO runs with emulated prepares off.
- `app/Domain/Authorization/SystemRoles.php` (**new**): provisions the admin / sales-team / distributor roles per
  franchise (deterministic refs identical to migrations 002/012). It grants **only when the role is created**, so
  existing roles are never topped up by code. It also assigns the baseline role for a legacy surface.
- `app/Policies/SalesLeadPolicy.php`, `SalesFollowUpPolicy.php`: now delegate to `CrmScopePolicy`; static API kept;
  `listClause()` added.
- `app/Http/Controllers/Api/V1/Admin/LeadsController.php`, `FollowUpsController.php`: a permission key on every
  action, scope-based list/record checks, and self-assignment by scope. `leads/{ref}/status` is now authorized.
- `MastersController.php`, `CatalogMastersController.php`, `ProductsController.php`, `PricesController.php`,
  `SchemesController.php`: legacy admin gate removed. Transporters, settings list and templates list get keys.
- `SettingsController.php`: `settings.edit`.
- `OrdersController.php`, `InvoicesController.php`, `DispatchesController.php`, `OutstandingController.php`,
  `PdcsController.php`, `PaymentsController.php`, `Domain/Authorization/PartyScopePredicate.php`,
  `Domain/DCR/DcrService.php`: `isDistributor()` / `role === 'DISTRIBUTOR'` → `isPartyBound()`. Orders: ALL-scope
  default salesperson no longer depends on `isSales()`.
- `Portal/PortalController.php`: party-bound + `portal.*` key per action; Super bypass unchanged.
- `Domain/Leads/LeadAssignmentService.php` + `bootstrap/bindings.php`: permission/scope-based round-robin candidates.
- `Admin/UsersController.php`: a legacy-role create assigns the baseline role and runs `assertCanGrantRole`
  (closes "anyone with internalUsers.create can mint an Admin by passing role=FRANCHISE_ADMIN").
- `Super/FranchisesController.php`: the first franchise admin gets the Admin role (and the franchise gets its
  baseline roles).
- `Repositories/Contracts|Sql/{Lead,FollowUp}Repository`: optional `$scopeSql, $scopeParams` on `list()` (existing
  callers unaffected).
- `bootstrap/bindings.php`: `CrmScopePolicy`, `SystemRoles` bindings; `LeadAssignmentService` gets
  `AuthorizationService`.
- `database/migrations/012_b1_permission_based_access.sql` (**new**, not run).
- `tests/Agents/agent-idor.php`: contexts now carry scopes; two new cases prove the decision follows scope, not
  `users.role`.

## 3. New permission keys (no existing key covered these)

| Key | Replaces | Granted by 012 to |
|---|---|---|
| `settings.view` | FRANCHISE_ADMIN gate on `GET /admin/settings` | admin |
| `settings.edit` | FRANCHISE_ADMIN gate on `PATCH /admin/settings` | admin |
| `portal.view` | DISTRIBUTOR gate on portal read endpoints | distributor (new role) |
| `portal.placeOrder` | DISTRIBUTOR gate on cart / place / cancel | distributor |
| `portal.editProfile` | DISTRIBUTOR gate on `PATCH /portal/profile` | distributor |

Everything else reuses keys already returned by `/auth/me` (`masters.*`, `products.*`, `pricing.*`, `schemes.*`,
`leads.*`, `followUps.*`, `notifications.manageTemplates`).

## 4. Seed parity (requirement 5)

| Role | Before (by role name) | After (by keys) |
|---|---|---|
| Admin (`FRANCHISE_ADMIN`) | masters/products/pricing/schemes/settings via name gate + existing keys | same keys + `settings.view/edit`; **no other key added** (e.g. `orders.confirm` still not granted: separate blocker) |
| Sales Team (`SALES`) | leads/follow-ups by name (own only) | seeded `leads.view/create/edit/convert`, `followUps.view/create/edit/complete/reschedule`, OWN scope: same reach |
| Distributor (`DISTRIBUTOR`) | portal by name; no auth role | new Distributor role with `portal.*` only; still no `dcr.*` (unchanged: DCR was already inaccessible to them) |
| Super Admin | bypass | bypass (unchanged) |
| Users with no auth role (created after 002) | worked through the name gates | backfilled by 012 to the matching baseline role |

## 5. Verification: pending, needs a runtime

Not possible from this machine (no PHP/MySQL; the live server runs the committed code). Steps once deployed:
1. Run migration 012 (see the "what it does" header in the file).
2. As Admin: `POST /admin/roles` "B1 Dispatch Team" (`dispatch.view`, `masters.view`, `leads.view`, scope OWN). Then
   `POST /admin/users` with that `role_ref`.
3. Log in as that user (`crm-sales`):
   - expect 200: `GET /admin/dispatches`, `GET /admin/categories`, `GET /admin/leads`;
   - expect 403: `POST /admin/categories`, `GET /admin/orders`, `GET /admin/payments`.
4. Regression:
   - Admin: `GET /admin/categories`, `PATCH /admin/settings` → 200.
   - Sales: `GET /admin/leads` (own only), `GET /admin/categories` → 403.
   - Portal: `GET /portal/profile` → 200, `GET /admin/leads` → 403 (was a data leak).
   - Super: `GET /super/dashboard/stats` → 200.
5. Cleanup: deactivate the test user (`POST /admin/users/{ref}/deactivate`) and the test role (`DELETE /admin/roles/{ref}`
   once unassigned).
6. Unit: `tests/Agents/agent-idor.php`.
