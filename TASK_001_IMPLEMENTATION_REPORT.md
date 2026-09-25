# TASK-001 Implementation Report

## Final status

`TASK-001 PARTIALLY COMPLETE`

The normalized authorization foundation, protected role APIs, permission/scope resolver, identity payload, tenant checks and deactivation session revocation have been implemented. The status is partial because this environment does not have PHP, MySQL or Composer available, so migration execution, PHP lint, HTTP tests and database-backed security tests could not be executed. The migration must be run and the test agents must pass before this task is accepted as complete.

## 1. Frontend reviewed

Relevant frontend files reviewed:

- `pharma-sales-crm/src/features/roles/RolesPage.tsx`
- `pharma-sales-crm/src/features/roles/RoleFormPage.tsx`
- `pharma-sales-crm/src/features/roles/PermissionMatrix.tsx`
- `pharma-sales-crm/src/features/roles/PermissionSelector.tsx`
- `pharma-sales-crm/src/features/users/InternalUsersPage.tsx`
- `pharma-sales-crm/src/features/users/InternalUserFormPage.tsx`
- `pharma-sales-crm/src/features/users/InternalUserDetailsPage.tsx`
- `pharma-sales-crm/src/constants/permissions.ts`
- `pharma-sales-crm/src/utils/permissions.ts`
- `pharma-sales-crm/src/hooks/useEffectivePermissions.ts`
- `pharma-sales-crm/src/hooks/useCurrentUser.ts`
- `pharma-sales-crm/src/types/domain.ts`
- `pharma-sales-crm/src/mocks/mockRoles.ts`
- `pharma-sales-crm/src/mocks/mockUsers.ts`

The frontend remains mock-backed as required. TASK-001 finalization changed only the approved missing `None` option in `RoleFormPage.tsx`; no API integration was started.

## 2. Requirement references

- `pharma-sales-crm/docs/Pharma_CRM_Master_FRS_v2.3.md`: §§44.3–44.10 and §§45.1, 45.6–45.7.
- `pharma-sales-crm/CLAUDE.md`: centralized permissions, no hard-coded role checks, backend integration boundary.
- `pharma-sales-crm/projectRequirement.md`: user roles/access, server-equivalent ownership expectations and security rules.
- `REQUIREMENT_DECISION_VERIFICATION.md`: confirms role/scope architecture is defined; multi-role and permission refresh remain isolated decisions.

## 3. Backend existing functionality reused

- JWT access tokens and rotating refresh-token/session infrastructure.
- `BearerAuth` middleware and `TenantContext`.
- `users`, `user_sessions`, `oauth_refresh_tokens` and append-only `audit_logs` tables.
- Existing `UserRepository`, `UsersController`, `AuditService`, request/response envelopes and `RefGenerator`.
- Existing route/container auto-wiring architecture.

The old `RoleService`/`RoleController` remains an unregistered legacy path. TASK-001 uses the active `/api/v1` architecture and additive `auth_*` tables instead of making that legacy implementation authoritative.

## 4. Backend changes

- Added `AuthorizationService` as the centralized normalized role/permission/scope resolver.
- Added normalized role, permission catalogue, role-permission, role-scope, user-role, hierarchy and territory-assignment migration structures.
- Added protected `/api/v1/admin/roles` CRUD/clone/list routes.
- Added `/api/v1/admin/permissions` catalogue route.
- Added user role assign/revoke routes.
- Added permission checks to active v1 user management operations.
- Added normalized roles, effective permissions and effective scopes to `/api/v1/auth/me`.
- TASK-002 contract verification also added the authenticated user's active `status` to `/api/v1/auth/me` so the identity payload is complete for frontend consumption.
- Added normalized role/permission/scope information to user detail/create responses.
- Added role grant validation preventing arbitrary permission names and privilege/scope escalation.
- Added protected system-role/own-role/last-privileged-user safeguards.
- Added immediate session revocation on user deactivation.
- Removed the deactivation-status cache path so inactive users are checked against DB on every protected request.
- Added internal-user hierarchy/profile fields and reporting-manager persistence support.
- Added OpenAPI documentation in `Pharma-CRM/public/api-docs/openapi.yaml`.

## 5. Database changes

New migration: `database/migrations/002_task001_authorization.sql`

It adds:

- `auth_roles`
- `auth_permissions`
- `auth_permission_catalogue`
- `auth_role_permissions`
- `auth_role_scopes`
- `auth_user_roles`
- `auth_user_hierarchy`
- `auth_user_territories`
- additive internal-user profile fields

The migration seeds the approved permission catalogue, protected Admin role per active franchise, default Sales Team role, existing-user role mappings and Sales Team grants. Existing `users.role` is retained as a compatibility field for surface/client authentication; normalized tables are the authorization source.

No destructive reset, delete or data rewrite was performed.

## 6. APIs

| Method | Endpoint | Frontend use | Permission | Scope | Status |
|---|---|---|---|---|---|
| GET | `/api/v1/admin/roles` | Roles list | `rolesAndPermissions.view` | Tenant | Implemented |
| POST | `/api/v1/admin/roles` | Create role | `.create` | Actor-grantable | Implemented |
| GET | `/api/v1/admin/roles/{ref}` | Role detail/edit | `.view` | Tenant/IDOR protected | Implemented |
| PATCH | `/api/v1/admin/roles/{ref}` | Edit role | `.edit` | Tenant/own-role protected | Implemented |
| POST | `/api/v1/admin/roles/{ref}/clone` | Clone role | `.create` | Actor-grantable | Implemented |
| DELETE | `/api/v1/admin/roles/{ref}` | Delete role | `.delete` | Tenant/assigned-user protected | Implemented |
| GET | `/api/v1/admin/permissions` | Permission selector/matrix | `.view` | Tenant-independent catalogue | Implemented |
| POST | `/api/v1/admin/users/{ref}/roles` | Assign role | `.assignToUser` | Same tenant + escalation protected | Implemented |
| DELETE | `/api/v1/admin/users/{ref}/roles/{role_ref}` | Revoke role | `.assignToUser` | Same tenant + last-admin protected | Implemented |
| GET | `/api/v1/admin/users` | Internal user list | `internalUsers.view` | Franchise-scoped | Protected existing route |
| POST | `/api/v1/admin/users` | Create user | `internalUsers.create` | Franchise-scoped | Protected and normalized role-aware |
| PATCH | `/api/v1/admin/users/{ref}` | Edit user | `internalUsers.edit` | Franchise/IDOR protected | Protected existing route |
| POST | `/api/v1/admin/users/{ref}/activate` | Activate user | `internalUsers.activateDeactivate` | Franchise-scoped | Protected existing route |
| POST | `/api/v1/admin/users/{ref}/deactivate` | Deactivate user | `internalUsers.activateDeactivate` | Franchise/session protected | Protected and revokes sessions |
| GET | `/api/v1/auth/me` | Current identity | Authenticated session | Current tenant | Extended with roles/permissions/scopes |

`NONE` is accepted in the backend and is now exposed by the frontend role selector. The corrected mismatch is documented in `FRONTEND_REQUIREMENT_MISMATCHES.md`.

## 7. Frontend compatibility

| Workflow | Status | Notes |
|---|---|---|
| Role list/detail | READY_WITH_FRONTEND_ADAPTER | Endpoint uses stable `role_ref`; frontend currently uses mock `id`. |
| Role create/edit | READY_WITH_FRONTEND_ADAPTER | Map `name`, `description`, `default_scope`, permission map and scope overrides. |
| Role clone | READY_WITH_FRONTEND_ADAPTER | Use clone endpoint and send optional name. |
| Permission catalogue | READY_WITH_FRONTEND_ADAPTER | Map server catalogue to current module/action constants. |
| User role assignment | READY_WITH_FRONTEND_ADAPTER | Frontend currently stores one `roleId`; multi-role cardinality remains unresolved. |
| Internal user CRUD | READY_WITH_FRONTEND_ADAPTER | Map `roleId` to `role_ref`, `region` to `assigned_region`, and hierarchy refs. |
| `/auth/me` | READY_WITH_FRONTEND_ADAPTER | Response now contains authoritative roles, permissions and scopes. |
| Record-level authorization for future modules | BLOCKED | Infrastructure exists, but each future module must call the centralized policy with its owner/territory fields. |

No API integration was started.

## 8. Frontend requirement mismatches

See `FRONTEND_REQUIREMENT_MISMATCHES.md`.

The confirmed `None` data-scope mismatch in `RoleFormPage.tsx` is fixed. `DataScope.None` exists in the frontend constants, is now rendered by the role selector, and matches FRS §44.4. This did not require API integration.

No other confirmed functional mismatch was identified in the relevant TASK-001 scope. Multi-role user support and permission-refresh timing remain unresolved requirements, not confirmed frontend mismatches.

## 9. Security implementation

- Normalized permission catalogue is server-owned; arbitrary module/action keys are rejected.
- A caller cannot grant permissions they do not hold.
- A caller cannot grant a broader module scope than their effective scope.
- System Admin role cannot be edited, deactivated or deleted.
- A user cannot modify their own role or role assignment.
- Roles assigned to users cannot be deleted.
- The last active user with Roles and Permissions edit capability cannot be removed/demoted through these APIs.
- Role/user references are tenant-filtered; unauthorized cross-tenant references return not-found behavior.
- User deactivation revokes all active sessions for that user.
- Every protected request checks current DB user status; deactivation is not delayed by a status cache.
- Role, permission, scope and role-assignment mutations are audit logged with actor/entity/before/after data where applicable.
- Effective scopes support ALL, TERRITORY, TEAM, OWN and NONE, with hierarchy/territory reference tables available for record-level policy resolution.

## 10. Validation Matrix

| Validation | Status | Evidence / limitation |
|---|---|---|
| Frontend exposes ALL, TERRITORY, TEAM, OWN and NONE | PASS | `DataScope` enum and `RoleFormPage.tsx` selector contain all five values. |
| Frontend build | BLOCKED | `npm.cmd run build` reached TypeScript build but could not write existing `node_modules/.tmp/*.tsbuildinfo` files due to `EPERM`; no source error was reported. |
| Frontend lint | PASS | `npm.cmd run lint` completed with 0 errors and 5 pre-existing warnings outside TASK-001. |
| Backend migration structure and five scope values | PASS | Static review confirms migration enum and seed paths contain all five values. |
| Migration execution | NOT RUN | MySQL is unavailable in this environment. |
| PHP syntax validation | NOT RUN | PHP is unavailable in this environment. |
| TASK-001 PHP/DB agent tests | NOT RUN | PHP/MySQL are unavailable in this environment. |
| HTTP, IDOR, cross-tenant, inactive-user and concurrency checks | BLOCKED | Require PHP runtime, MySQL fixtures and executable API environment. |
| OpenAPI parser/runtime validation | BLOCKED | Static OpenAPI update is present; no backend runtime/toolchain is available. |

## 11. Tests executed

### Added

- `tests/Agents/agent-task001.php`: context permission/scope assertions and migration structure assertions.

### Attempted

- `php -l app/Domain/Authorization/AuthorizationService.php`
- `php -l app/Http/Controllers/Api/V1/Admin/AuthorizationController.php`
- `php -l app/Http/Controllers/Api/V1/Admin/UsersController.php`
- `php -l app/Http/Middleware/BearerAuth.php`
- `php cli/test-agents.php --agent=agent-task001`
- `php cli/migrate.php`

### Result

Not executed: `php` is not installed/available in the environment. MySQL/Composer are also unavailable, so migration and DB-backed tests could not run. `git diff --check` completed without whitespace errors; only normal LF/CRLF warnings were reported.

## 12. Remaining TASK-001 issues

1. Run the migration against a MySQL 8 environment and verify it on a copy of production-like data.
2. Verify `ALTER TABLE ... IF NOT EXISTS` support on the deployment MySQL version; if unsupported, convert those statements to the project’s deployment-safe schema migration convention.
3. Add HTTP integration tests for every route and real DB fixtures for each scope.
4. Apply centralized record-scope checks to each future module as it is implemented; TASK-001 provides the reusable service but does not retrofit all business controllers.
5. Decide and isolate multi-role cardinality and permission-refresh timing.

## 13. Deferred decisions

- Whether one internal user can hold multiple roles.
- Permission refresh on next request versus next login.
- Franchise switching; no switching endpoint was added.

## 14. Final status

`TASK-001 PARTIALLY COMPLETE`

Code, migration, routes, OpenAPI and targeted test scaffolding are in place. Acceptance is pending actual PHP/MySQL execution, migration verification, route tests and authorization/concurrency security tests.
