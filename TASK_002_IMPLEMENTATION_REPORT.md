# TASK-002 Implementation Report — Canonical API Contracts

## Executive Summary

TASK-002 canonicalized the active `/api/v1` response, error, validation, reference, pagination, query, enum, authorization, idempotency and server-authority conventions. OpenAPI and a standalone contract reference were updated. No frontend API integration or business-module implementation was started. Runtime acceptance remains pending because PHP, Composer and MySQL are unavailable.

## 1. Frontend Contract Sources Reviewed

Targeted contract-only review was performed in `pharma-sales-crm`:

- `src/types/common.ts` and `src/types/domain.ts`
- `src/constants/statusConstants.ts` and `src/constants/permissions.ts`
- `src/services/mockCrmService.ts` and portal services
- list/table pages and forms for orders, products, users, parties, payments and invoices
- `src/hooks/useCurrentUser.ts` and `src/utils/permissions.ts`
- frontend `CLAUDE.md`, FRS v2.3, `projectRequirement.md`, and build-plan contract notes

No broad frontend audit and no API integration were started.

## 2. Requirement Sources Reviewed

- Success/error JSON envelopes and request tracing are required by the existing backend architecture and FRS non-functional requirements.
- Stable public references are the active `/api/v1` convention; internal numeric IDs remain database-only.
- Order status values are `DRAFT`, `SUBMITTED`, `CONFIRMED`, `PROCESSING`, `DISPATCHED`, `DELIVERED`, `CANCELLED`.
- TASK-001 scopes are `ALL`, `TERRITORY`, `TEAM`, `OWN`, `NONE`.
- Date-only values are represented as ISO `YYYY-MM-DD`; date-time values use ISO-8601.
- Idempotency, pagination, server-side authorization and server-authoritative calculated values are required.
- GST calculation semantics, order transition authority and payment/PDC allocation rules remain pending module decisions and were not invented here.

## 3. Existing Backend Contract

The active backend already had `Response`, `RequestId`, exception classes, request JSON parsing, `/api/v1` stable refs, idempotency middleware/repository, and many list repositories. It also had inconsistent legacy response call styles: some endpoints returned nested `data`, some used `pages`, and idempotency replay stored only the inner data payload. The older non-v1 controller tree was not made authoritative.

## 4. Canonical Contract Implemented

- Success: `{ success: true, data, meta }` with `meta.request_id`.
- Errors: `{ success: false, error: { code, message, fields? }, meta: { request_id } }`.
- Backward-compatible `Response::json` normalization unwraps existing `['data' => ...]`, nested repository list results, and full-envelope call styles without changing endpoint payload intent.
- Validation fields remain a field-name-to-message-array map.
- Pagination: `page`, `per_page`, `total`, `total_pages`; defaults `1` and `20`, maximum `100`.
- Shared query names: `search`, `status`, `date_from`, `date_to`, `sort_by`, `sort_dir` (`asc`/`desc`).
- Public resources use stable `*_ref` values; internal numeric IDs are not exposed by active v1 resources.
- Date-only values use `YYYY-MM-DD`; date-times use ISO-8601 with an explicit offset or `Z`.
- Frozen shared enums are centralized in `ApiContract` and documented in OpenAPI.
- `/auth/me` exposes active `status`, normalized `roles`, `permissions`, effective `scopes`, and tenant/franchise context from TASK-001.
- Server authority is documented for totals, prices, discounts, schemes, GST/tax, stock, outstanding and allocations.

## 5. Backend Files Changed

Added:

- `app/Core/ApiContract.php`
- `app/Core/ApiErrorCodes.php`
- `app/Core/Pagination.php`
- `app/Core/QueryParams.php`
- `Pharma-CRM/API_CONTRACT.md`

Updated:

- `Response` envelope normalization and replay support.
- Exception handler to use the current `AppException` API and canonical error envelope.
- `NotFoundException` to support both legacy message-only calls and explicit error codes.
- Idempotency fingerprinting to include actor, method, path, query and body; replay now returns the complete original envelope with `Idempotent-Replay: true`.
- Representative active v1 Products, Orders, Payments and Portal list paths to use shared query/pagination conventions.
- Active list metadata from `pages` to `total_pages` where the existing repository contract exposed that key.

No business module was newly implemented. No frontend API integration or database migration was started.

## 6. OpenAPI Changes

Updated `public/api-docs/openapi.yaml` with:

- success/error envelope metadata and validation fields;
- stable public reference, order status and permission scope schemas;
- `/auth/me` identity, normalized roles, module/action permissions and effective scope schema;
- common search/date/sort query parameters;
- `total_pages` pagination metadata;
- canonical idempotency behavior and replay/conflict documentation;
- approved order status list without deciding transition authority.

The API contract detail is also recorded in `API_CONTRACT.md`.

## 7. Frontend Compatibility Matrix

| Contract area | Classification | Notes |
|---|---|---|
| Success/error envelopes | READY_WITH_ADAPTER | Frontend mock services are not integrated; adapter maps `data`, `meta`, and field errors. |
| Stable refs | READY_WITH_ADAPTER | Frontend mock IDs map to server `*_ref` values at the integration boundary. |
| Pagination/query names | READY_WITH_ADAPTER | Existing tables can map `page`/`per_page` and shared query fields. |
| Date/date-time | READY_WITH_ADAPTER | Forms already use date-only strings; adapter parses ISO date-times. |
| Permission/scope payload | READY_WITH_ADAPTER | `/auth/me` exposes roles, module/action permissions and effective scopes. |
| Order status enum | BLOCKED_BY_PENDING_BUSINESS_DECISION | Contract is frozen to approved values, but current frontend mock enum uses `Billed`/`Packed` while project requirements list `Submitted`/`Processing`; no frontend change was made in TASK-002. |
| GST/tax structures | BLOCKED_BY_PENDING_BUSINESS_DECISION | DTOs may carry tax components, but jurisdiction/rounding semantics remain pending. |

The fixed TASK-001 `NONE` scope mismatch was not reopened.

## 8. Pending Business Decisions

Only the already-isolated decisions remain pending:

- GST place-of-supply/jurisdiction, rounding and cancellation numbering semantics.
- Which actor owns each order transition and cancellation cutoff.
- Payment allocation ordering, reversal, ageing authority and PDC transitions.
- Frontend/display reconciliation for the approved order status names where the existing mock uses a different intermediate vocabulary.

These do not block the shared envelope, error, reference, pagination, date, permission/scope or idempotency contract.

## 9. Tests Executed

Attempted environment checks:

- `Get-Command php,composer,mysql,mariadb,node,npm.cmd`: PHP, Composer, MySQL and MariaDB unavailable; Node/npm available.
- `git diff --check`: completed; only normal LF/CRLF warnings were emitted.
- `npm.cmd run lint` in `pharma-sales-crm`: completed with exit code `0`, 0 errors and 5 existing React Compiler warnings in unrelated files.
- `npm.cmd run build` in `pharma-sales-crm`: blocked by `EPERM` while TypeScript attempted to write existing `node_modules/.tmp/*.tsbuildinfo`; no source diagnostic was emitted.

Not executed because the tools are unavailable:

- PHP syntax checks
- Composer checks
- MySQL migration
- PHP/DB agent tests
- HTTP, OpenAPI parser, idempotency, authorization and pagination runtime tests

## 10. Validation Matrix

| Check | Status | Evidence |
|---|---|---|
| Success contract | PASS | `Response` normalizes active endpoint call styles to one success envelope; static source verification. |
| Error contract | PASS | `Response::error` and exception handler emit code/message/fields/request ID; static source verification. |
| Validation errors | PASS | `ValidationException` carries field maps; `QueryParams` returns field-level pagination/date/sort errors. |
| Stable refs | PASS | Active TASK-001 and v1 resources use `*_ref`; legacy numeric internals remain outside the canonical v1 boundary. |
| Pagination | PASS | Shared defaults/max and `total_pages` helper implemented; representative v1 list paths updated. |
| Search/filter convention | PASS | Shared `QueryParams` defines `search`, `status`, date range; business-specific filters remain module-owned. |
| Sorting | PASS | `sort_by` allow-list and `sort_dir` validation implemented. |
| Date/time | PASS | Shared date-only validation and ISO-8601 contract documented; runtime parsing not executed. |
| Enums | PASS | `ApiContract` and OpenAPI freeze order statuses, user/role statuses and scopes. |
| Permission/scope contract | PASS | TASK-001 `/auth/me` shape and five scopes preserved and documented. |
| Idempotency | PASS | Actor-aware fingerprint, complete-envelope replay and conflict behavior implemented; DB runtime not executed. |
| OpenAPI | BLOCKED | Updated statically; parser/runtime validation unavailable. |
| Frontend compatibility | BLOCKED | Lint passed, build blocked by environment permissions, and order-name adapter decision remains isolated. |
| Backend lint/syntax | NOT RUN | PHP unavailable. |
| Backend tests | NOT RUN | PHP/MySQL unavailable. |
| TASK-001 runtime recheck | NOT RUN | PHP/MySQL/Composer remain unavailable; TASK-001 stays partial. |

## 11. Known Issues

1. Execute PHP syntax, OpenAPI parser, migration and DB-backed API tests in the backend runtime environment.
2. Verify all active v1 list endpoints against the canonical query helper and metadata contract during module integration.
3. Resolve only the documented order display vocabulary/business transition ownership before order module integration.
4. Confirm the existing idempotency schema/runtime behavior in MySQL, including expiry cleanup and duplicate-key races.

## 12. TASK-001 Current Status

`TASK-001 PARTIALLY COMPLETE`

The TASK-001 implementation was not reworked. It remains partial solely because PHP/MySQL/Composer runtime validation is still unavailable; the approved `NONE` frontend mismatch remains fixed.

## 13. TASK-002 Final Status

`TASK-002 PARTIALLY COMPLETE`

The shared contract, reusable infrastructure and OpenAPI documentation are implemented. Final completion is pending executable PHP/MySQL/OpenAPI validation and the environment-blocked frontend build. No TASK-003 or business-module implementation was started.
