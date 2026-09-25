# Pharma CRM Canonical API Contract

This is the shared contract for active `/api/v1` endpoints. Business modules may add fields, but must keep these conventions.

## Envelopes

Success responses use `{ success: true, data, meta }`. `meta.request_id` is returned on every JSON response. List responses put pagination in `meta` using `page`, `per_page`, `total`, and `total_pages`.

Errors use `{ success: false, error: { code, message, fields? }, meta: { request_id } }`. `fields` is a field-name map for validation errors and is omitted when there are no field errors.

## References and dates

Public API resources use stable `*_ref` values. Internal numeric database IDs are not public API identifiers. Date-only values use `YYYY-MM-DD`; date-time values use ISO-8601 with an explicit offset or `Z`. Existing business date meaning is unchanged.

## Query conventions

Paginated lists accept `page` (default `1`) and `per_page` (default `20`, maximum `100`). Shared optional query names are `search`, `status`, `date_from`, `date_to`, `sort_by`, and `sort_dir` (`asc` or `desc`). Unsupported pagination, date, or sort values return a validation error.

## Frozen shared enums

- Order statuses: `DRAFT`, `SUBMITTED`, `CONFIRMED`, `PROCESSING`, `DISPATCHED`, `DELIVERED`, `CANCELLED`.
- User and role statuses: `ACTIVE`, `INACTIVE`.
- Permission scopes: `ALL`, `TERRITORY`, `TEAM`, `OWN`, `NONE`.

GST calculation/jurisdiction, order transition authority, and payment allocation/PDC semantics remain governed by their approved module decisions; this contract only carries their API values.

## Idempotency

Mutating `/api/v1` requests may send `Idempotency-Key` (16–120 ASCII letters, digits, `_` or `-`). The key is tenant-scoped and fingerprinted to the authenticated actor, method, path, query and body. A matching completed request replays the original complete envelope with `Idempotent-Replay: true`; a different fingerprint returns `409 IDEMPOTENCY_MISMATCH`; concurrent processing returns `409 REQUEST_IN_PROGRESS`.

## Authority boundary

The backend is authoritative for persisted totals, prices, discounts, schemes, GST/tax, stock, outstanding, and allocations. Frontend values are inputs or previews only and must be recalculated/validated by the relevant business module.

## HTTP status convention

`200` is used for successful reads/updates, `201` for creation, and `204` only when no response body is intentionally needed. `400` is for malformed requests where appropriate, `401` for missing/invalid authentication, `403` for authorization or scope denial, `404` for unavailable or inaccessible resources, `409` for duplicate/conflict/state/idempotency conflicts, `422` for field validation, and `500` for unexpected server errors.
