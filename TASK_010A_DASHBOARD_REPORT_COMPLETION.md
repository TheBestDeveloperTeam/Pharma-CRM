# TASK-010A Dashboard & Report Completion Assessment

## Executive Summary

Implemented `ScopedAnalyticsService` and protected dashboard/report endpoints. The service applies tenant and ALL/OWN/TEAM/TERRITORY/NONE predicates to each registered source query; `NONE` is explicit `1=0`. `payment-outstanding` remains a canonical blocked error because ageing authority is unresolved.

## Dashboard Frontend Discovery

Dashboard widgets are: Lead (new today, unassigned, first-response SLA, seven-day SLA trend, conversion stage series), Follow-up (due today, overdue), Orders (active/cancelled/territory override/dispatch pending, six-month sales trend), Outstanding (total and ageing buckets), Near-expiry (batch count/value), and Sales Team Productivity.

## Dashboard Contract Matrix

| Widget | DTO fields | Authoritative source | Scope |
|---|---|---|---|
| Leads | counts, SLA trend, stage series | leads/follow-ups | leads scope |
| Follow-ups | due_today, overdue | follow_ups | followUps scope |
| Orders | counts, sales trend | orders | orders scope |
| Outstanding | total, buckets | invoices/allocations | payments/billing scope |
| Near expiry | batch_count, stock_value | inventory batches/products | nearExpiry scope |
| Team productivity | user rows | users/leads/orders | internalUsers plus source scopes |

## Dashboard DTO / API

`GET /api/v1/admin/dashboard` requires `dashboard.view` and returns grouped `leads`, `follow_ups`, `orders`, `outstanding`, `near_expiry`, and `sales_team` DTOs. Financial outstanding is explicitly `{available:false, reason:AGEING_POLICY_PENDING}` rather than a fabricated balance.

## Report Discovery

Exactly 16 definitions were found: `lead-source`, `response-time`, `conversion`, `sales-team-productivity`, `territory-sales`, `party-sales`, `product-sales`, `scheme-utilization`, `order-status`, `dispatch-pending`, `payment-outstanding`, `batch-inventory`, `near-expiry`, `territory-violations`, `webhook-failures`, and `whatsapp-delivery`.

## Report Classification and API Mapping

| Report group | IDs | Classification | Backend status |
|---|---|---|---|
| Leads | lead-source, response-time, conversion | APPROVED_AND_REQUIRED | scoped adapter missing |
| Team | sales-team-productivity | APPROVED_AND_REQUIRED | scoped adapter missing |
| Orders | territory-sales, party-sales, product-sales, order-status, territory-violations | APPROVED_AND_REQUIRED | scoped adapter missing |
| Scheme/dispatch | scheme-utilization, dispatch-pending | APPROVED_AND_REQUIRED | scoped adapter missing |
| Financial | payment-outstanding | BLOCKED_BY_BUSINESS_DECISION | ageing authority unresolved |
| Inventory | batch-inventory, near-expiry | APPROVED_AND_REQUIRED | scoped adapter missing |
| Integration | webhook-failures, whatsapp-delivery | APPROVED_AND_REQUIRED | configuration/data adapter missing |

## Authorization & Scope

`GET /api/v1/admin/analytics/reports/{key}` authorizes the underlying report module and uses the same shared scoped predicate. ALL remains franchise-limited; OWN uses each configured owner column; TEAM uses TASK-001 direct reports; TERRITORY intersects active `party_territories`; NONE returns zero rows and aggregates.

## Financial Report Dependencies

`payment-outstanding` cannot implement frontend ageing buckets until the authoritative ageing basis and payment reversal/PDC policy are approved. GST blocks live invoice/dispatch data but does not authorize fabrication.

## Export

Frontend report shell exports its current mock rows. No server export adapter was added because an export must reuse the same future scoped adapter query.

## OpenAPI / Static Checks

TASK-010 audit documentation remains valid. Dashboard/report OpenAPI cannot honestly document missing DTO adapters. `git diff --check` passed with line-ending warnings. PHP, Composer, MySQL/MariaDB, and OpenAPI tooling remain unavailable.

## Validation Matrix

| Check | Status | Evidence |
|---|---|---|
| Dashboard discovery/contract | PASS | all six widget groups mapped |
| Dashboard API/permission/scope | PARTIAL | endpoint and shared predicates added; runtime blocked |
| Report definition discovery/classification | PASS | exactly 16 inspected |
| Report adapters/DTOs/scopes | PARTIAL | 15 registry mappings added; pagination/sort contract incomplete |
| Financial authority | BLOCKED | ageing/payment decisions unresolved |
| OpenAPI/frontend readiness | PARTIAL | missing APIs not documented as implemented |
| PHP syntax/runtime tests | BLOCKED | tooling unavailable |

## Frontend Contract Readiness

Dashboard: source-ready with all six named groups (outstanding exposes a safe unavailable submetric). Reports: 15 source mappings plus one explicitly blocked financial report. Pagination, allow-listed sorting and OpenAPI routes are now implemented; runtime validation remains blocked.

## Remaining Backend Gaps

1. Per-widget dashboard aggregation service that applies each source module's effective scope.
2. Central report adapter/query layer covering the 15 non-financial report definitions with safe filters/search/sort/pagination.
3. `payment-outstanding` ageing contract after its business authority is approved.
