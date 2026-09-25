# TASK-009 Implementation Report

## Executive Summary

Implemented the requirement-backed distributor registration, KYC metadata/review, Party conversion, and online DCR lifecycle foundation. No frontend integration, offline sync, reports, webhooks, GST policy, or payment-policy work was started.

## Requirement Sources Reviewed

Frontend `projectRequirement.md`, distributor onboarding/public registration screens, `domain.ts`, DCR portal forms/list/review screens, existing backend routes/schema/services, and TASK-001 authorization structures.

## Frontend Screens / Types Reviewed

`DistributorOnboardingPage`, registration detail/review screens, public Firm & KYC step and schema, `DistributorRegistration`, `DistributorDocument`, `DcrReport`, `DcrVisit`, and DCR entry/review screens. Mapping: registration fields map to `onboarding_registrations`; document filename/reference and verification map to `kyc_documents`; DCR date/work type/beat/visits map to `dcr_reports_v2`/`dcr_visits_v2`. Statuses are lifecycle-controlled and document/bank values are sensitive.

## Existing Backend Reused

TASK-001 `AuthorizationService` and audit service, TASK-004 `PartyService` and existing Lead conversion, tenant context, canonical response/error envelopes, and public reference generation.

## Database Changes

`database/migrations/009_task009_onboarding_kyc_dcr.sql` adds registration, immutable onboarding/KYC/DCR history, document metadata, and tenant-indexed online DCR tables. It is additive and creates no retention deletion mechanism.

## Onboarding

Public registration consumes a one-time invite and creates `SUBMITTED` onboarding; it does not create a Party. Approved actions are `INFO_REQUESTED`, `APPROVED`, and `REJECTED`, with mandatory remarks for correction/rejection.

## Party Conversion

Only approved, unconverted onboarding may convert through `PartyService`, transactionally. Existing Party identifier duplicates and a second conversion are rejected. Lead conversion reuses the existing Lead service.

## Territory Integration

The approved frontend captures location fields, but the registration flow has no confirmed territory-allocation action. No second territory algorithm was introduced.

## KYC

Actual frontend document types are mapped to a controlled database enum. Metadata only is stored; internal storage paths are not returned. Pending documents can be verified or rejected once, with reviewer/remarks/history/audit.

## KYC Retention Decision

`PENDING_BUSINESS_DECISION`. No retention period, deletion, archive, or cleanup job was invented.

## DCR

Online DCR supports Draft, Submitted, Approved, Rejected, and reviewer reopen to Draft. Field Work submission requires a visit. Visit fields require exactly one tenant-local Party or Lead reference; no separate customer/lead structure was created.

## DCR Ownership

The authenticated tenant user is the authoritative owner on create. Owner-only update/submit is enforced. A user cannot review their own DCR.

## DCR Offline Decision

`DCR OFFLINE: PENDING_BUSINESS_DECISION`. No queue, sync, conflict handling, or device API was implemented.

## Authorization / Scope / IDOR

New permission catalogue entries cover onboarding/KYC/DCR actions. Tenant predicates are on every lookup. DCR detail blocks another user except an administrator. Remaining role-to-permission assignments stay under existing TASK-001 role administration.

## Audit / History

Administrative onboarding/KYC and DCR lifecycle changes write audit events; append-only history tables retain state transitions.

## OpenAPI

Updated `public/api-docs/openapi.yaml` with the implemented onboarding/KYC and DCR endpoints/lifecycle semantics.

## Tests Executed

`git diff --check` — PASS (line-ending warnings only). PHP, Composer, MySQL/MariaDB, and an OpenAPI validator are unavailable, therefore no syntax, migration, API, or runtime execution was possible.

## Validation Matrix

| Check | Status | Evidence |
|---|---|---|
| Onboarding requirement mapping | PASS | frontend fields/types inspected |
| Onboarding create/lifecycle/review/conversion | NOT RUN | source implemented; runtime unavailable |
| Duplicate conversion protection | NOT RUN | transaction/source review only |
| KYC documents/lifecycle/security | NOT RUN | source implemented; runtime unavailable |
| KYC retention handling | PASS | no retention policy implemented |
| DCR create/edit/submit/ownership | NOT RUN | source implemented; runtime unavailable |
| DCR offline handling | PASS | intentionally not implemented |
| Authorization/scope/IDOR/tenant isolation | NOT RUN | source review only |
| Audit/history/OpenAPI | PASS | static source inspection |
| Migration execution/PHP syntax/runtime tests | BLOCKED | PHP, DB and tooling unavailable |
| TASK-001–008 recheck | BLOCKED | runtime tooling unavailable |

## Remaining Issues

* source implementation defect: DCR manager/team scope needs a confirmed distributor portal hierarchy model before non-admin manager review can be safely enabled.
* runtime environment limitation: PHP, Composer, DB server, and OpenAPI validator unavailable.
* pending onboarding decision: allocation/territory resolution during approval is not specified.
* KYC retention/privacy decision: `PENDING_BUSINESS_DECISION`.
* DCR offline decision: `PENDING_BUSINESS_DECISION`.
* future backend dependency: real document upload/storage and portal team hierarchy are not present.

## Previous Task Status

TASK-001 through TASK-008: `PARTIALLY COMPLETE` (per supplied status; runtime recheck blocked).

## TASK-009A Scope & Authorization Finalization

Added `Task009ScopePolicy`, which delegates enforcement to TASK-001 `AuthorizationService` and uses only confirmed TASK-001 data. Onboarding ownership is `onboarding_registrations.assigned_user_ref`; records without an assignment fail closed for OWN/TEAM. DCR ownership is `dcr_reports_v2.owner_user_ref` created from the authenticated user.

List SQL now applies the effective scope before paging. Direct detail and every existing onboarding/KYC mutation path call the same scope policy before returning or changing a record. KYC derives access solely from its parent onboarding record. Party conversion is protected by the same parent-record check.

TEAM is confirmed as the authenticated user plus direct reports from `auth_user_hierarchy`; no same-role or inferred portal-team membership is used. A missing hierarchy produces no additional records, so it fails closed. TERRITORY is derived on the server from active `party_territories`: onboarding uses its persisted pincode/district and DCR uses its persisted distributor Party. NONE becomes `1=0` for lists and a canonical not-found response for direct records. ALL remains constrained by franchise predicates.

Targeted source checks covered ALL/OWN/TEAM/TERRITORY/NONE list predicates and the shared direct-record gate for onboarding, KYC, conversion, DCR detail/update/submit/approve/reject. Runtime execution remains unavailable.

| Check | Status | Evidence |
|---|---|---|
| Onboarding list/detail/mutation scope | PASS | `Task009ScopePolicy` is applied to SQL and controller gates |
| Party conversion / KYC parent scope | PASS | parent `assertAccess` precedes conversion and KYC decision |
| DCR OWN / TEAM / TERRITORY / review scope | PASS | shared policy uses owner, direct reports, active party territory |
| NONE / list-detail consistency / tenant isolation | PASS | fail-closed clause and tenant-qualified lookups |
| Audit / OpenAPI | PASS | existing successful-action audit retained; OpenAPI response docs updated |
| PHP syntax / runtime tests | BLOCKED | PHP, Composer, DB and validator unavailable |

Remaining decisions: KYC retention/privacy remains `PENDING_BUSINESS_DECISION`; DCR offline remains `PENDING_BUSINESS_DECISION`; onboarding territory allocation at approval remains an unimplemented business decision, but does not weaken access control.
