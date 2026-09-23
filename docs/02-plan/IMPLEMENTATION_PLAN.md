# Implementation Plan

## Milestone 1: Core Backend Framework (Week 1)
- **EPIC 1**: Setup Directory structure, routing engine, and config (`.env`).
- **EPIC 2**: Implement core Database / PDO wrappers and simple Migration/Seed runners.
- **EPIC 3**: Implement Auth (JWT) + Tenancy middleware.

## Milestone 2: Domain Logic & APIs (Week 2)
- **EPIC 4**: Users, Roles, Permissions API.
- **EPIC 5**: Masters API (Doctors, Chemists, Products, Territories).
- **EPIC 6**: Core Workflows API (Batches, Inventory, Orders, Prescriptions, Samples).
- **EPIC 7**: Audit Logging System and Reporting Endpoints.

## Milestone 3: Frontend Refactoring (Week 3)
- **EPIC 8**: Delete React/Vite source code.
- **EPIC 9**: Setup AdminLTE local assets, layouts, CSS/JS structure.
- **EPIC 10**: Rebuild Auth, Dashboard, and Masters UI screens using vanilla JS.
- **EPIC 11**: Rebuild Orders, Prescriptions, and Inventory UI screens.

## Milestone 4: Finalization & Destructive Step (Week 4)
- **EPIC 12**: Execute End-to-End integration tests.
- **EPIC 13**: Code review, security audit against `SECURITY_CHECKLIST.md`.
- **EPIC 14**: Archive backup and completely wipe the old `crmpharma-main` codebase.

## Risk Register
1. **Scope Creep**: Enforce strict mapping to current capabilities. Avoid adding "nice to have" features.
2. **Security**: Ensure JWT secret is rotated and not exposed.
3. **Data Migration**: Not applicable as there is no production data to migrate yet.
