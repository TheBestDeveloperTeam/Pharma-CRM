# Pharma CRM & Sales Force Automation — Documentation Hub

## 🔗 Start Here

| Document | Lines | Description |
|----------|-------|-------------|
| **[UNIFIED MASTER DOCUMENTATION](PHARMA_CRM_UNIFIED_MASTER_DOCUMENTATION.md)** | 600+ | **THE single source of truth** — 68 DB tables, 223 API routes, 24 modules, all workflows, acceptance criteria |

## Directory Map

```
docs/
├── PHARMA_CRM_UNIFIED_MASTER_DOCUMENTATION.md   ← START HERE (Master Reference)
├── README.md                                     ← This file (Navigation Hub)
├── apis/                                         ← API quick-reference
│   └── README.md                                 ← Route summary + response envelope
├── workflows/                                    ← Business workflow documentation
│   ├── README.md                                 ← Workflow navigation
│   ├── 00_system_master_flow.md                  ← System-level overview
│   ├── 01_super_admin_workflow.md                ← Platform provisioning
│   ├── 02_franchise_admin_workflow.md            ← Franchise setup
│   ├── 03_sales_rep_workflow.md                  ← Sales team workflow
│   ├── 04_distributor_portal_workflow.md         ← Portal self-service
│   ├── 05_order_to_cash_lifecycle.md             ← Order-to-Cash E2E
│   ├── 06_comprehensive_system_workflows.md      ← Cross-module flows
│   └── 07_zero_local_data_implementation_plan.md ← Architecture plan
├── screens/                                      ← Screen/form documentation
├── input-fields-ER/                              ← Field directory & entity relations
├── front-end/
│   ├── Pharma_CRM_Master_FRS_v2.3.md            ← FRS Source of Truth (1307 lines)
│   └── openapi.yaml                              ← OpenAPI 3.0.3 Spec (3142 lines)
├── architecture/                                 ← Architecture deep-dives
├── business-rules/                               ← Business rule documentation
├── decisions/                                    ← Architecture decisions
├── deployment/                                   ← Deployment guides
└── runbooks/                                     ← Operational runbooks
```

## Source Code References

| Source | Path | Size |
|--------|------|------|
| Database Schema | `database/schema/001_full_schema.sql` | 68 tables, 2039 lines |
| Routes | `bootstrap/routes.php` | 223 routes, 275 lines |
| DI Bindings | `bootstrap/bindings.php` | 30 repository bindings |
| API Controllers | `app/Http/Controllers/Api/V1/` | Admin, Super, Portal |
| Domain Layer | `app/Domain/` | 26 business modules |
| Repository Layer | `app/Repositories/` | 30 interfaces + SQL implementations |
