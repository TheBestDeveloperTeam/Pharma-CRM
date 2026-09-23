# Gap Register - Pharma CRM

| ID | Area | Requirement | Current State | Gap | Severity | Root Cause | Proposed Fix | Affected Files | Effort | Depends On |
|---|---|---|---|---|---|---|---|---|---|---|
| GAP-001 | Architecture | Core custom PHP backend | No PHP backend exists in the repo | Missing complete API backend layer | Blocker | Started as frontend-first | Build unified PHP API + Controllers | All new backend files | XL | None |
| GAP-002 | Frontend | Vanilla JS + CSS with AdminLTE | Uses React, TypeScript, and Vite | Stack violation | Blocker | Previous technical direction | Rewrite frontend using PHP Views + AdminLTE + Vanilla JS/CSS | `crmpharma-main/src/*` | XL | GAP-001 |
| GAP-003 | Authentication | OAuth 2.0 Bearer (JWT) | None (mocked/React based) | Missing secure authentication | Critical | Missing backend | Implement custom OAuth2 Provider / JWT token generator | `Auth/` | L | GAP-001 |
| GAP-004 | Security | RBAC & ABAC Permissions | Hardcoded logic in React routes | No server-side enforcement | Critical | Missing backend | Implement RBAC matrix, Gates, and Policies in PHP | DB, Controllers | L | GAP-003 |
| GAP-005 | Security | Rate limiting, brute-force lockout, Audit logs | None | Security vulnerabilities | Major | Missing backend | Implement middleware/hooks for logging and limiting | Middleware | M | GAP-001 |
| GAP-006 | Pharma Features | Batch/Expiry, Prescription handling, Traceability | Missing entirely | Missing domain logic | Major | Unimplemented | Create DB schemas and modules for Batch, Prescription, Traceability | DB, Models | L | GAP-001 |
| GAP-007 | Pharma Features | Territory & Rep management, Visit reporting | Missing entirely | Missing domain logic | Major | Unimplemented | Create DB schemas and modules for Reps, Territories, Visits | DB, Models | L | GAP-001 |
| GAP-008 | Database | Defined schema, migrations, seeders | No DB schema or scripts | No persistence layer | Blocker | Missing backend | Design and write custom PHP migrator and seeders | `database/migrations` | L | None |
| GAP-009 | Infrastructure | Multi-tenant readiness | Hardcoded single-tenant | Not SaaS ready | Major | Unimplemented | Implement `tenant_id` scope on all models and views | All queries | M | GAP-008 |
| GAP-010 | Asset Pipeline | Local vendored AdminLTE | Uses npm for everything | Constraint violation | Major | Frontend stack mismatch | Manually download and vendor AdminLTE + dependencies | `public/assets/` | S | GAP-002 |
