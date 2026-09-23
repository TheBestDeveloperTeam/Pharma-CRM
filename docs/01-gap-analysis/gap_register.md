# Gap Register - Pharma CRM

| ID | Area | Requirement | Current State | Gap | Severity | Root Cause | Proposed Fix | Affected Files | Effort | Depends On |
|---|---|---|---|---|---|---|---|---|---|---|
| GAP-001 | Architecture | Core custom PHP backend | **Implemented** (API structure built) | None | Blocker | Started as frontend-first | Build unified PHP API + Controllers | All new backend files | XL | None |
| GAP-002 | Frontend | Vanilla JS + CSS with AdminLTE | **Implemented** (Vanilla JS + PHP Views) | None | Blocker | Previous technical direction | Rewrite frontend using PHP Views + AdminLTE + Vanilla JS/CSS | `views/*` | XL | GAP-001 |
| GAP-003 | Authentication | OAuth 2.0 Bearer (JWT) | **Implemented** (AuthMiddleware & JWTUtils) | None | Critical | Missing backend | Implement custom OAuth2 Provider / JWT token generator | `Auth/` | L | GAP-001 |
| GAP-004 | Security | RBAC & ABAC Permissions | Hardcoded logic in React routes | No server-side enforcement | Critical | Missing backend | Implement RBAC matrix, Gates, and Policies in PHP | DB, Controllers | L | GAP-003 |
| GAP-005 | Security | Rate limiting, brute-force lockout, Audit logs | Partially Implemented (Audit in DB.php) | Missing rate limiting | Major | Missing backend | Implement middleware/hooks for logging and limiting | Middleware | M | GAP-001 |
| GAP-006 | Pharma Features | Batch/Expiry, Prescription handling, Traceability | Missing entirely | Missing domain logic | Major | Unimplemented | Create DB schemas and modules for Batch, Prescription, Traceability | DB, Models | L | GAP-001 |
| GAP-007 | Pharma Features | Territory & Rep management, Visit reporting | Missing entirely (Planned TSK-009B) | Missing domain logic | Major | Unimplemented | Create DB schemas and modules for Reps, Territories, Visits | DB, Models | L | GAP-001 |
| GAP-008 | Database | Defined schema, migrations, seeders | **Implemented** (Core schema SQL exists) | None | Blocker | Missing backend | Design and write custom PHP migrator and seeders | `database/migrations` | L | None |
| GAP-009 | Infrastructure | Multi-tenant readiness | **Implemented** (Row-level org_ref) | None | Major | Unimplemented | Implement `tenant_id` scope on all models and views | All queries | M | GAP-008 |
| GAP-010 | Asset Pipeline | Local vendored AdminLTE | **Implemented** (Zero external CDNs) | None | Major | Frontend stack mismatch | Manually download and vendor AdminLTE + dependencies | `public/assets/` | S | GAP-002 |
