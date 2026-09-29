# Pharma CRM — API Documentation

> **Complete API Reference:** See [`PHARMA_CRM_UNIFIED_MASTER_DOCUMENTATION.md` §28](../PHARMA_CRM_UNIFIED_MASTER_DOCUMENTATION.md#28-complete-api-route-registry)

## Quick Reference

| Domain | Routes | Auth |
|--------|--------|------|
| Health | 4 | None |
| Auth/OAuth | 3 | client_id required |
| Geo | 6 | None (public) |
| Super Admin | 15 | SUPER_ADMIN JWT |
| Admin Users | 8 | FRANCHISE_ADMIN/custom role JWT |
| RBAC | 9 | rolesAndPermissions permission |
| Settings | 2 | Admin JWT |
| Masters | 18 | masters permission |
| Products | 9 | products permission |
| Pricing | 5 | pricing permission |
| Schemes | 6 | schemes permission |
| Leads | 6 | leads permission |
| Follow-ups | 4 | followUps permission |
| Parties | 8 | parties permission |
| Territories | 8 | territory permission |
| Onboarding | 9 | distributorOnboarding permission |
| DCR | 5 | Portal JWT |
| Webhooks | 3 | webhooks permission + HMAC |
| Inventory | 8 | inventory permission |
| Orders | 8 | orders permission |
| Invoices | 5 | billing permission |
| Dispatches | 4 | dispatch permission |
| Payments | 12 | payments permission |
| Reports | 4 | reports permission |
| Notifications | 3 | Authenticated JWT |
| Portal | 11 | DISTRIBUTOR JWT |
| Web Shells | 22 | Session-based |
| **Total** | **~223** | |

## OpenAPI Specification

The complete OpenAPI 3.0.3 specification is at:
- [`docs/front-end/openapi.yaml`](../front-end/openapi.yaml) (3,142 lines)

## Response Envelope

All API responses follow the standard envelope:

```json
{
  "success": true,
  "data": { ... },
  "meta": {
    "request_id": "01J9XKZM3QVRY4BPTDGWCNH7E2",
    "page": 1,
    "per_page": 20,
    "total": 145,
    "total_pages": 8
  }
}
```

## Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": { "field": ["error message"] }
  }
}
```
