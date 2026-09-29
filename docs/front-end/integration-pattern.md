# Live API integration pattern

Established in F16-2 (Masters) on top of F16-1 (auth + API client). **Every module from F16-3 on follows this guide.** If
something here doesn't fit your module, update this document in the same change. Don't invent a second pattern.

Reference implementation: Masters.
- `src/api/modules/masters.ts`
- `src/services/mastersService.ts`
- `src/hooks/useLiveMasters.ts`
- `src/features/masters/LiveMasterSection.tsx`
- `src/features/masters/sections/*`

---

## 1. Folder structure

```
src/
  config/env.ts                 # isMockModule('<module>') — the ONLY place that reads VITE_USE_MOCK*
  api/
    client.ts                   # axios per realm, envelope unwrap, AppError, 401 → refresh+retry (F16-1)
    errors.ts                   # AppError, toAppError, remapErrorFields
    pagination.ts               # Page<T>, ListParams, toListQuery, toPage (both list shapes), pageFromArray
    schema.d.ts                 # generated from docs/openapi.yaml — never edited by hand
    modules/<module>.ts         # DTOs + adapters + service functions   ← NEW per module
  services/<module>Service.ts   # data-source seam: live vs mock decision, per-entity "source" objects ← NEW/CHANGED per module
  hooks/
    useServerListState.ts       # page / pageSize / debounced search → ListParams (generic)
    use<Module>….ts             # React Query hooks over the service                                ← NEW per module
  features/<module>/…           # pages/components — only the data wiring may change (see §6)
```

## 2. `src/api/modules/<module>.ts`

Order inside the file: request DTOs → response DTOs → adapters → service functions.

**DTO types**
- **Request bodies:** use `paths['/…']['post']['requestBody']['content']['application/json']` from `schema.d.ts`
  where the spec defines one. Where it doesn't (most PATCH bodies today), write a **Zod schema from the backend
  source**. Take the rules from the controller's `Validation::validate([...])` (required, min/max, enum, format) plus
  the column length in `database/schema` / `migrations` for anything without an explicit max. Put a one-line comment
  above each schema citing the controller method and column. Never guess a rule.
- When both exist, add a compile-time check that the Zod type is assignable to the spec type (see `specCategoryCheck`
  in `masters.ts`). A spec change then breaks the build instead of production.
- **Responses:** the spec currently documents **no response schemas** for most endpoints (`content?: never`), so every
  response row is validated with a Zod schema at runtime. A contract break then fails loudly (`BAD_RESPONSE`) instead
  of rendering `undefined`.

**Adapters: the one rule.** The app's existing model type (`src/types/domain.ts`) does **not** change. Everything
backend-specific stops in the adapter: `*_ref` ids, snake_case names, `ACTIVE/INACTIVE` → `active: boolean`,
money/number strings, `null` → `undefined`, and fields the backend lacks (left `undefined`, documented in a comment).
- `dtoToModel(row: unknown): Model` parses with the response Zod schema, then maps.
- `modelToDto(model): RequestDto` maps, then parses with the request Zod schema. The same schema is used for client
  validation (§4), so the form and the server never disagree.
- `fieldMap: { backend_field: 'modelField' }` translates server 422 `fields` for the form.

**Service functions:** `list(params: ListParams): Promise<Page<Model>>`, `get(id)`, `create(model)`,
`update(id, model)`, `setActive(id, active)` (or `remove(id)` if the backend really has DELETE). They:
- return app models only, never DTOs;
- throw `AppError` only (the client already normalises everything);
- wrap mutations so 422 `fields` come back in **model** field names (`remapErrorFields`), and map 409 `DUPLICATE_*`
  onto the field it concerns (see `withFieldMap` in `masters.ts`);
- use `toPage(result, dtoToModel, params)` for lists. It accepts the standard shape (`data[] + meta`), the legacy shape
  (`data.items + total/page…`, leads and follow-ups), and plain arrays. For unpaginated endpoints, use `pageFromArray`,
  so every screen sees the same `Page<T>`;
- throw `unsupportedOperation('…')` for any verb the backend lacks, and declare it in the source's `capabilities` (§3)
  so the UI hides it.

## 3. `src/services/<module>Service.ts`: the data-source seam

- Decides **live vs mock**: `isMockModule('<module>')`, **and** whether the backend actually has the endpoint. An
  entity with no endpoint stays on its mock path even when the flag says live. List those entities in the module's
  F16-x note.
- Exposes per-entity **source objects**: `{ queryKey, capabilities: {create, edit, toggle}, list, create, update,
  setActive, validate }`. Components talk to these, never to `api/modules/*` directly.
- `validate(values)` runs the adapter's `modelToDto` and turns a `ZodError` into `{ modelField: message }`.

## 4. Errors and validation

- **Client first:** `validate` (the same Zod rules the adapter sends) → messages under the fields, no request sent.
- **Server second:** a failed mutation rejects with `AppError`:
  - `fields` (already in model names) → `setError`/`helperText` under each field;
  - anything else → one message in the form/dialog (`AppError.message`);
  - list/load failures → the screen's `ErrorState` (with retry where the screen has one);
  - toasts only for success and for row actions without a form (toggle).
- 401 / refresh / logout are handled by `client.ts`. **Modules never catch 401.**
- Don't show raw codes to users. `AppError.requestId` is kept for support/logging.

## 5. Flag naming

- `VITE_USE_MOCK=true|false` is the global default (`true` until the migration ends).
- `VITE_USE_MOCK_<MODULE>=true|false` is a per-module override. `<MODULE>` is the `MockableModule` key upper-cased,
  with camelCase split by `_` (`followUps` → `VITE_USE_MOCK_FOLLOW_UPS`). Add the key to `MockableModule` in
  `src/config/env.ts`, and add the variable to **both** `.env.example` and `.env.local`.
- Only `isMockModule()` reads these. `true` must restore the exact pre-integration behaviour: **the mock path is kept
  unchanged, never deleted** (mock sections are renamed `Mock…`, not rewritten).

## 6. What you may touch / what you must not

| ✅ May change | ❌ Must not change |
|---|---|
| `src/api/modules/<module>.ts`, `src/services/<module>Service.ts`, the module's hooks (new) | Any other module's services, hooks, pages or data |
| The module's **data wiring** in its own feature files: where rows come from, what `onCreate/onUpdate` call. Keep the existing component as the `Mock…` branch and add a `Live…` branch next to it | JSX layout, styling, theme, navigation, copy of existing mock screens |
| **Additive, optional** props on shared components when the live source genuinely needs them (e.g. `DataTable.serverPagination`, `MasterCrudSection.server/validate/capabilities`), defaulting to today's behaviour so every existing caller is untouched | Changing a shared component's existing props or default behaviour |
| `PermissionGate` *usage* stays the same. Extra hiding is only for verbs the backend lacks (`capabilities`) | `PermissionGate`'s interface; permission checks by role name |
| `src/types/domain.ts`: **only** additive optional fields, if unavoidable, and noted in docs | Renaming or removing model fields (the adapter absorbs differences) |
| `src/config/env.ts`, `.env.example`, `.env.local` | Mock data files (never delete them) |
| `docs/api-contract-gaps.md` (F16-x note), `docs/requirement-coverage.md`, this file | Anything in `../Pharma-CRM` (backend) — read it, never write it |

**Shared reference data:** hooks consumed by *other* modules (e.g. `useSimpleMastersQuery`, `useStatesQuery`) keep
their current source until **those** modules go live, because their records hold mock ids. When a module goes live,
switch the reference-data dropdowns it uses at the same time.

## 7. Hybrid phase (some modules live, some mock)

Live records (users, parties, ids) won't exist in mock modules. Never leave a screen spinning. Use
`components/common/HybridDataUnavailable` (see `routes/DistributorProtectedRoute.tsx`) or the screen's `EmptyState`,
gated on `isLiveAuth() && isMockModule('<module>')`, so it disappears by itself once the module is switched.

## 8. Checklist for each new module

1. Read the backend controllers, validation, and repository `list()` (filters/sort it really supports) and write down
   the gaps first.
2. `api/modules/<module>.ts` (DTOs, adapters, services), then `services/<module>Service.ts` (sources + capabilities),
   then hooks, then the `Live…` branches.
3. Server pagination via `useServerListState` + `DataTable serverPagination`. Only send filters/sorts the backend
   repository really applies. Otherwise keep the control client-side over the current page and note it.
4. Flag in `env.ts` + both `.env*` files.
5. `npm run build`, `npm run lint`, `tsc -b` all clean.
6. Live verification against the dev proxy: list/create/edit/status, one server error shown under a field, a role
   without the permission sees no actions, and the flag set to `true` restores mock.
7. A note in `../docs/PROJECT_STATUS.md` §3 (what's live, what stays mock and why, new issues) — this replaced
   `api-contract-gaps.md`/`requirement-coverage.md` (deleted 2026-09-28, consolidated into one status doc).
