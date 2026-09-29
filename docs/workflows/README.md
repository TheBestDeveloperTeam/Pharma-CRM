# Pharma CRM Documentation Hub

> **Single Source of Truth:** [`PHARMA_CRM_UNIFIED_MASTER_DOCUMENTATION.md`](../PHARMA_CRM_UNIFIED_MASTER_DOCUMENTATION.md)

## Documentation Structure

| Document | Description |
|----------|-------------|
| **[Unified Master Documentation](../PHARMA_CRM_UNIFIED_MASTER_DOCUMENTATION.md)** | Complete project reference: 68 DB tables, 223 API routes, 24 modules, all workflows |
| [00_system_master_flow.md](00_system_master_flow.md) | System-level master flow overview |
| [01_super_admin_workflow.md](01_super_admin_workflow.md) | Platform provisioning & franchise management |
| [02_franchise_admin_workflow.md](02_franchise_admin_workflow.md) | Franchise admin onboarding & configuration |
| [03_sales_rep_workflow.md](03_sales_rep_workflow.md) | Sales team lead-to-order workflow |
| [04_distributor_portal_workflow.md](04_distributor_portal_workflow.md) | Distributor self-service portal workflow |
| [05_order_to_cash_lifecycle.md](05_order_to_cash_lifecycle.md) | End-to-end order lifecycle |
| [06_comprehensive_system_workflows.md](06_comprehensive_system_workflows.md) | Cross-module workflow diagrams |
| [07_zero_local_data_implementation_plan.md](07_zero_local_data_implementation_plan.md) | Zero-local-data architecture plan |

## Key Architecture Principles

1. **Zero-Local-Data**: All business data, field options, validations, and calculations come from the database/backend
2. **Multi-Tenancy**: Row-level isolation via `org_ref` + `franchise_ref` on every business table
3. **FEFO**: First-Expiry-First-Out for pharmaceutical inventory allocation
4. **Configurable RBAC**: 22 permission modules × granular actions with data scoping (ALL/TERRITORY/TEAM/OWN/NONE)
5. **Idempotent Mutations**: `api_idempotency_keys` table prevents duplicate writes
6. **Immutable Audit**: Append-only `audit_logs` with DB-level triggers blocking UPDATE/DELETE
