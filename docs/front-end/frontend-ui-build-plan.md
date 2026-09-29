# Pharma CRM — Frontend UI Build Plan (Mock Data Phase)

Status ka single source of truth: Pharma-CRM/docs/OPEN_ISSUES.md

**Scope of this document:** Frontend only. Backend ko touch nahi karna hai (alag team bana rahi hai).
**Goal:** Saare modules **UI level par complete** ho jayein — hardcoded / mock data ke saath.
**Constraint:** Existing UI theme, layout aur architecture change nahi karna. Sirf naye screens/components add karne hain.

**Source of truth:** `projectRequirement.md` + `docs/Pharma_CRM_Master_FRS_v2.3.md`
**Ye plan FRS v2.3 ke against likha gaya hai** — usme Distributor Onboarding (§38–39), DCR (§40) aur configurable Roles & Permissions (§44–47) sab included hain.
**Status:** Mock-data UI phase | Backend integration = baad ka phase

---

## 0. Working Rules (har AI coding session me ye follow karna)

1. **UI/theme/architecture modify nahi karna.** Existing `PageHeader`, `DataTable`, `FilterPanel`, `FormSection`, `StatusChip`, dark-header + red-nav theme — sab as-is reuse karna.
2. **Ek session = ek phase (ya ek module).** Pura plan ek saath mat generate karwana.
3. **Har module ke liye session me ye 3 cheezein feed karna:** (a) is file ka relevant phase section, (b) FRS ka relevant section, (c) mock data ka type definition.
4. **Data shape API-ready rakhna.** Mock data ka structure wahi ho jo backend baad me bhejega — taaki integration ke time sirf data source badle, component nahi.
5. **Business logic mock me minimal.** Jo calculation backend karega (pricing resolve, scheme apply, FEFO allocate, territory validate) — uska UI banao, par logic hardcode/simulate karo. Real logic backend se aayega.
6. **Har screen ke 4 state banane hain:** loading (skeleton), empty, error + retry, success. Ye optional nahi hai.
7. **Har phase ke baad:** `npm run build` + `npm run lint` pass hone chahiye, aur coverage checklist update karni hai.

---

## 1. HOLD LIST — abhi nahi banana

Ye items business decision ya backend par depend karte hain. Inka **UI placeholder** ban sakta hai, par actual logic abhi HOLD.

| # | Item | Kyu hold | UI me abhi kya karna |
|---|---|---|---|
| H1 | **FEFO allocation algorithm** | Batch selection rule + minimum shelf-life at dispatch decide nahi hua | "Allocate Batches" button + allocation preview table banao, rows mock se aayein |
| H2 | **Stock reservation / concurrency** | Purely backend transaction concern | Order form me "Reserved Qty" column dikhao (read-only mock value) |
| H3 | **Pricing overlap priority** | Party-specific vs tier-specific — konsa jeetega, decide nahi hua | Rate field auto-fill karo mock se; "rate source" ek chhoti label me dikhao |
| H4 | **Scheme stacking rules** | Stack ho sakti hain ya nahi, priority kya — pending | Ek hi scheme apply karo; stacking UI abhi nahi |
| H5 | **Credit limit enforcement** | Hard-block / warn / admin-approval — pending | Order form me sirf warning banner dikhao, block mat karo |
| H6 | **Unassigned pincode policy** | Block / review / approval — pending | Settings me ek radio group placeholder rakho (non-functional) |
| H7 | **Invoice number series + GST format** | Client se format pending | Generic invoice layout banao, number field editable rakho |
| H8 | **WhatsApp provider integration** | BSP account + template approval pending | Template list + message log + status chips ka UI banao, send action mock |
| H9 | **Webhook ingestion** | Portal API access pending | Webhook source config screen + failure log UI banao, data mock |
| H10 | **Distributor Portal auth method** | Decide nahi hua | Mock login (hardcoded distributor user) |
| H11 | **Invite OTP verification** | Mobile/email OTP mandatory hai ya nahi — pending (FRS D-3) | Registration form me OTP step ka UI banao, verify hamesha success return kare |
| H12 | **DCR geo-location capture** | Mandatory hai ya optional — pending (FRS D-8) | Visit entry me location field dikhao, mock coordinates |
| H13 | **Offline DCR entry** | Phase decide nahi hua (FRS D-11) | Abhi online-only banao |
| H14 | **Admin ko DCR visibility** | Company ko read-only access milega ya nahi — pending (FRS D-1) | Admin side par DCR ka koi screen mat banao |
| H15 | **Multi-role per user** | Ek user ke paas ek role ya multiple — pending (FRS D-16) | **Ek role per user** banao. UI aise rakho ki baad me multi-select karna easy ho |
| H16 | **Per-module scope override** | Ek role ka alag-alag module me alag scope — pending (FRS D-17) | Role level par ek hi scope rakho |
| H17 | **Permission change kab effective** | Turant ya next login — pending (FRS D-20) | Mock me turant apply karo |

**In sab ke liye rule:** UI bana do, `// TODO: backend integration` comment chhod do, aur logic simulate karo.
### Current HOLD Status Reconciliation (2026-09-27)

| HOLD | Current status |
|---|---|
| H1 FEFO allocation algorithm | RESOLVED (2026-09-27) - backend source owns FEFO/reservations; duplicate dispatch consumption remains BE-102. |
| H2 stock reservation/concurrency | RESOLVED (2026-09-27) for source reservation/allocation core; duplicate consumption remains BE-102. |
| H3 pricing overlap priority | RESOLVED (2026-09-26) - backend pricing hierarchy exists; PTS fallback remains BE-060. |
| H4 scheme stacking/free qty | BE-061 (DECISION). |
| H5 credit limit enforcement | BE-073 (DECISION). |
| H6 unassigned pincode policy | BE-050 plus decision D-4. |
| H7 invoice number/GST format | Invoice numbering is NOT APPLICABLE - server-generated. Order/cart GST split remains BE-071/BE-160; free goods billing BE-090; franchise state BE-091. |
| H8 WhatsApp provider integration | NOT APPLICABLE - product/provider HOLD placeholder; no frontend-blocking BE ID. |
| H9 webhook ingestion/config | Webhook source edit/status remains BE-015; permission check remains S-2. |
| H10 distributor portal auth method | RESOLVED (2026-09-26) - `crm-portal` auth exists; portal team split remains BE-167. |
| H11 invite OTP verification | NOT APPLICABLE - product HOLD placeholder; invite lookup/resubmit/upload/portal-login gaps are BE-151/BE-152/BE-153/BE-155. |
| H12 DCR geo-location capture | BE-177 for visit photo/location. |
| H13 offline DCR entry | NOT APPLICABLE - product HOLD placeholder; no backend requirement raised. |
| H14 Admin DCR visibility | BE-170 decision D-9. |
| H15 multi-role per user | NOT APPLICABLE - current contract is one role per user; no frontend-blocking BE ID. |
| H16 per-module scope override | NOT APPLICABLE - current role-scope model is accepted for frontend integration. |
| H17 permission change timing | NOT APPLICABLE - no frontend-blocking BE ID. |

---

## 1.5 CODEBASE REALITY (Session 0 audit ke baad — 18 Sep)

> Purani `requirement-coverage.md` **stale** thi. Actual codebase audit se ye nikla. Jahan yahan aur neeche ke phase sections me fark ho, **yahi section sahi hai**.

### Jo pehle se bana hua hai (plan me "read-only" likha tha — galat tha)

| Module | Actual status | Phase ka scope ab kya hai |
|---|---|---|
| Leads | Full CRUD + archive/restore + convert-to-party wired | F4a: sirf missing fields, duplicate detection, SLA badge, bulk filters |
| Parties | **Full CRUD** (create/edit/view/archive/restore/activate) | F5: sirf missing fields, detail tabs, **territory allocation** |
| Products | **Full CRUD** + usage-check delete | F6a: sirf pricing fields (MRP/PTS/net rate), scheme eligibility |
| Payments | **Full CRUD** | F10: sirf outstanding, ageing, allocation, PDC |
| Orders | Partial CRUD (create/edit-draft/cancel/delete-draft) | F7: line items, scheme/pricing wiring, validation UI |
| Sales Team users | **Full CRUD** | F3b: roles-aware banana + reporting manager + hierarchy |
| Follow-ups | Sirf add/edit remark + history | F4b: complete/reschedule/missed/tabs/calendar (jaisa plan me hai) |
| Dashboard | ~5 stat cards + 2 custom bar charts | F11a: 12 widgets + Recharts (jaisa plan me hai) |
| Masters, Reports, Settings | Static placeholders, koi CRUD nahi | Plan ke hisaab se hi |

### Tech debt jo F1 me clear karna hai

| # | Kya hai | Action |
|---|---|---|
| T1 | `src/mock/` aur `src/mocks/` dono zinda hain — `mocks/` ki files sirf 1-line re-export shim hain, asli data `mock/` me | Files sach me move karo, `mock/` delete karo. `mockDashboard.ts` ka `mocks/` me equivalent hi nahi hai |
| T2 | `src/redux/` aur `src/app/` dono me store setup hai — code `redux/` use karta hai, `app/` adhoora bana hai | Pehle pata karo kaunsa live hai, ek rakho, doosra delete |
| T3 | `src/pages/` me sirf README.md hai | Delete |
| T4 | `constants/routes.ts` aur `constants/routeConstants.ts` — duplicate | Merge karke ek rakho |
| T5 | `routes/RoleGuard.tsx` kahin use nahi ho raha — dead code | F1c me permission-based guard se replace |
| T6 | `constants/routeConfig.ts` define hai par `AppRouter.tsx` import hi nahi karta | F1c me wire karo ya delete |
| T7 | Party/Product/Order/Payment forms plain `useState` use karte hain, RHF+Zod nahi (sirf Lead/FollowUp me hai) | Apne-apne phase me RHF+Zod par convert karo |
| T8 | `ErrorState` me retry button nahi, `ConfirmationDialog` me reason input nahi | F1a me extend karo |
| T9 | `recharts`, `@mui/x-date-pickers` install hain par use nahi hue; `react-router` redundant hai (`react-router-dom` se hota hai) | F1a me date-picker use karo, F11a me recharts, `react-router` hatao |

### Rule-5 violations jo pehle se code me hain (F1c me theek karna hai)

`user.role === UserRole.Admin` / `UserRole.Sales` type direct role-string checks in files me hain:

```
utils/accessControl.ts        features/party/PartiesPage.tsx
features/party/PartyFormPage.tsx    features/product/ProductsPage.tsx
features/payment/PaymentsPage.tsx   features/users/SalesTeamPage.tsx
services/mockCrmService.ts    constants/navigation.ts (roles[] arrays)
```

Inhe F1c me `can(module, action)` par migrate karna hai. Ye phase ka **mandatory hissa** hai, optional nahi — warna F4 se F14 tak har naya module isi galat pattern ko copy karega.

---

## 2. BUILD ORDER

Ye order dependency ke hisaab se hai — har phase apne se pehle wale phase ka output use karta hai. Isliye sequence badalni nahi chahiye.

```
F1  Foundation & Shared Components
F2  Masters (State → District → City → Pincode + baaki 16)
F3  Roles & Permissions + Internal Users
F4  Leads completion + Follow-ups completion
F5  Parties + Territory Allocation
F6  Products + Pricing + Schemes
F7  Orders
F8  Inventory & Batch + Near-Expiry
F9  Billing/Invoice + Dispatch
F10 Payments & Outstanding
F11 Dashboard (Admin + Sales) + Reports + Audit Logs
F12 Distributor Onboarding & Invitation  (admin side + public registration page)
F13 Distributor Portal (Owner)
F14 DCR — Daily Call Report (distributor ke andar)
F15 Responsive pass + Final UI QA
```

**Do important sequence decisions:**

- **Masters sabse pehle (F2)** — kyunki State/District/City/Pincode dropdowns leads, parties, territory, orders — sab me chahiye. Agar baad me banaye to har form dobara touch karna padega.
- **Dashboard aur Reports sabse baad me (F11)** — ye saare modules ka data aggregate karte hain. Pehle banaye to har naye module ke baad dashboard dobara likhna padega. Purana dashboard tab tak as-is chalta rahega.
- **Permission catalogue F1 me, role management F3 me** — catalogue constant (module × action) foundation me hi ban jayega taaki har module apne buttons pehle din se `PermissionGate` me wrap kare. Baad me retrofit kiya to saari 92 screens dobara kholni padengi.
- **Onboarding (F12) portal (F13) se pehle** — portal ka access approval step se banta hai. Pehle portal banaya to distributor user kahan se aayega, ye question khada ho jayega.
- **DCR (F14) portal ke baad** — DCR distributor ke portal ke andar ka module hai, uska layout aur auth context portal se aata hai.

---

## 3. PHASE F1 — Foundation & Shared Components

**Kyu pehle:** Aage ke 10 modules inhi components se banenge. Ye nahi banaye to har module apna-apna alag component banayega aur UI inconsistent ho jayega.

### 3.1 Shared components (naye banane hain)

| Component | Use |
|---|---|
| `FilterDrawer` | Advanced filters (status, source, owner, date range, territory) — har list page par |
| `DateRangePicker` | Reports, dashboards, list filters |
| `Timeline` | Lead activity, order status, dispatch tracking, territory history |
| `TableSkeleton` / `FormSkeleton` / `CardSkeleton` | Loading states |
| `ErrorState` (retry button ke saath) | API fail state |
| `ConfirmDialog` (reason input optional) | Archive, cancel, override — reason mandatory wali jagah |
| `CurrencyField` | Rate, amount, outstanding — INR formatting |
| `MobileCardList` | `DataTable` ka mobile fallback (table → cards) |
| `AppErrorBoundary` | Root level crash guard |
| `PermissionGate` | Button/action level permission wrapper |

### 3.2 Mock data restructure

- `src/mocks/` me **per-entity files** banao: `leads.ts`, `parties.ts`, `products.ts`, `pricing.ts`, `schemes.ts`, `orders.ts`, `invoices.ts`, `batches.ts`, `dispatches.ts`, `payments.ts`, `masters.ts`, `users.ts`, `notifications.ts`, `auditLogs.ts`, `whatsappMessages.ts`, `webhookEvents.ts`
- **Relational IDs consistent rakho** — `partyId`, `productId`, `orderId` cross-file match karein, warna detail pages me data mismatch dikhega
- Har file ka **TypeScript type export** karo (`src/types/`) — yahi type baad me API response type banega
- Legacy `src/mock` imports ko `src/mocks` par migrate karo (checklist me ye pending hai)

### 3.3 Permission model (ab dynamic — hardcoded roles nahi)

> FRS v2.3 §44. Roles ab **fixed nahi** hain — Admin khud banayega (Dispatch Team, Accounts Team, Manager, etc.). Isliye code me role name par check **kabhi mat likhna**.

- **Permission catalogue** `src/constants/permissions.ts` me define karo — FRS §44.3 ka module × action list (22 modules). Ye ek static constant hai, ise user nahi badal sakta.
- **Data scope enum:** `ALL | TERRITORY | TEAM | OWN | NONE` (FRS §44.4). Permission alag cheez hai, scope alag — dono chahiye.
- **`PermissionGate` component:** `<PermissionGate module="orders" action="create">` — permission na ho to **render hi na kare** (disable mat karo, hide karo).
- **Dynamic menu:** navigation config permissions se filter ho, hardcoded menu array nahi.
- **Dynamic route guard:** route config me required permission ho; na ho to Access Denied.
- **`useEffectivePermissions()` hook:** logged-in user ki effective permissions + scope ek jagah se aayein (mock me role object se).

**Anti-pattern jo avoid karna hai:**

```ts
// GALAT — role name par check
if (user.role === 'admin') { ... }

// SAHI — permission par check
if (can('orders', 'cancel')) { ... }
```

Ye galti abhi ho gayi to har naye role ke liye code badalna padega — jo iss feature ka pura point hi khatam kar deta hai.

**Definition of Done:** sab components Storybook-style ek demo page par render ho rahe hon, build + lint pass.

---

## 4. PHASE F2 — Masters

**20 masters (FRS §24).** Sab ka pattern same hai: list + add/edit dialog + activate/deactivate + search.

### 4.1 Geography masters (ye pehle — baaki sab inpar depend)

- **State** → list + CRUD
- **District** → State se linked, cascading
- **City/Area** → District se linked
- **Pincode** → City/District se linked. **Ye sabse important master hai** — territory locking poori isi par khadi hai

Ek reusable `CascadingLocationSelect` component banao (State → District → City → Pincode) jo leads, parties, orders — sab forms me reuse ho.

### 4.2 Baaki masters (simple CRUD, same pattern)

Lead Source, Lead Status, Follow-up Type, Party Type, Product Category, Dosage Form, Pricing Tier, Scheme Type, Payment Mode, Order Status, Dispatch Status, Transporter, Notification Template, Webhook Source

> Note: FRS §24 me "Sales Team" aur "Territory Allocation" bhi masters list me hain, par ye dono alag modules hain (F3 aur F5) — inhe simple master mat banana.

**DoD:** har master ka list + create + edit + activate/deactivate kaam kare (mock state par), cascading dropdown working ho.

---

## 5. PHASE F3 — Roles & Permissions + Internal Users

> FRS v2.3 §44. Ye pehle Sales Team module tha — ab ye **sab internal users** manage karta hai, aur roles Admin khud banata hai.

**Kyu abhi:** (a) Leads/Parties/Orders sab me "Assigned To" dropdown chahiye, (b) har module ke buttons role ke hisaab se dikhne hain. Dono isi phase se aate hain.

**Order iske andar:** pehle Roles, phir Users (kyunki user form me role select karna hai).

### 5.1 Role management screens

| Screen | Detail |
|---|---|
| **Role list** | Role name, description, assigned users count, scope badge, active flag. System role (Admin) par delete/edit disabled + lock icon |
| **Create/Edit Role** | Name, description, **data scope** (All / Territory / Team / Own), active flag |
| **Permission selector** | Module-wise accordion — har module ke andar uske applicable actions ke checkboxes (FRS §44.3). "Select all in module" + "Select all" shortcuts |
| **Sensitive permission confirm** | Territory Override, Price Override, Invoice Cancel, Payment Delete, Manual Batch Override, Registration Approval, Role Edit, Audit Log — inhe tick karte waqt confirm dialog + warning chip |
| **Clone Role** | Template ya existing role se copy → naya naam |
| **Permission Matrix view** | Ek grid: rows = permissions, columns = roles, cells = tick. Read-only overview screen |
| **Delete role** | Users assigned hon to block + "pehle reassign karo" message |

**7 role templates** seed data me daal do (FRS §44.5): Admin, Manager, Sales Team, Order/Dispatch Team, Accounts Team, Inventory Team, Auditor. Demo me yahi sabse pehle dikhenge.

### 5.2 Internal user screens

- **User list:** name, employee code, email, mobile, role chip, reporting manager, territory, active status
- **Create/Edit user:** name, employee code, email, mobile, **role select**, **reporting manager select** (Team scope isi par chalega), department, designation, assigned territory/area, active flag
- **User detail tabs:** Assigned Leads | Follow-ups | Parties | Productivity (mock)
- **Actions:** create, edit, activate/deactivate, reset password
- `UserSelect` component banao (purana `SalesTeamSelect` iska hi generalized version hai) — aage har "Assigned To" field me yahi

### 5.3 Role-switch demo mode

Mock phase me **role switcher** rakho (header ya settings me) — ek click me Admin → Dispatch → Accounts → Sales me switch karke UI dikha sako. Client demo ke liye ye sabse kaam ki cheez hai, aur khud test karne ke liye bhi. Backend integration ke waqt is switcher ko hata dena.

### 5.4 Validation rules (UI level)

- Admin role edit/delete **block**
- Apne hi role ki permissions edit karne par block + message
- Jo permission khud ke paas nahi, wo grant karne ka option hi na dikhe
- Aakhri role-manager user ko deactivate karne par block

**DoD:** naya role banao (e.g. "Dispatch Team") → usme sirf Orders-View + Inventory + Dispatch permissions do → us role ka user banao → role switcher se us user me jao → **menu me sirf teen module dikhein**, baaki routes par Access Denied aaye.

---

## 6. PHASE F4 — Leads Completion + Follow-ups Completion

Ye dono modules pehle se hain — ab FRS ke hisaab se complete karne hain.

### 6.1 Lead form — missing fields (FRS §6.1)

Add karo: **Firm name, WhatsApp number, Email, State/District/City/Pincode (F2 ka cascading component), Lead source (master), Interested products/categories (multi-select), Business type, Priority, Initial remark**

### 6.2 Lead — missing actions

| Action | UI |
|---|---|
| **Duplicate detection** | Mobile/GSTIN blur par mock check → warning dialog: "Ye number already exist karta hai [lead dekho] / [phir bhi banao]" |
| **Convert to Party** | Detail page par button → pre-filled party form kholo (F5 ke baad wire hoga, abhi navigation + prefill) |
| **Source badge** | Webhook source wale leads par source chip + external lead ID (read-only) |
| **First-response SLA** | Detail page par "Received → First response" duration + breach indicator (red/green) |
| **Bulk filters** | `FilterDrawer` se: status, source, owner, priority, date range, territory |

### 6.3 Follow-ups — missing actions

| Action | UI |
|---|---|
| **Complete** | Button + completion remark dialog |
| **Reschedule** | Date/time picker dialog + reason |
| **Mark Missed** | Button + auto-flag jo overdue ho gaye |
| **Activity type** | Call / Visit / WhatsApp — selector + icon per row |
| **Views** | Tabs: **Today / Overdue / Upcoming / Completed** |
| **Calendar view** | Month view with follow-up dots, date click → us din ki list |
| **Validation** | Active follow-up ke liye next action + next date mandatory (form level) |

**DoD:** lead create se lekar follow-up complete tak ka pura flow mock data par end-to-end chal jaye.

---

## 7. PHASE F5 — Parties + Territory Allocation

### 7.1 Party module (abhi sirf read-only list hai)

**Screens:** list, create/edit, detail

**Fields (FRS §9):** firm name, contact person, mobile, email, **GSTIN**, **drug license number + validity**, billing address, shipping address, State/District/City/Area/Pincode, assigned territory, assigned sales team, **pricing tier**, **agreement/monopoly start-end date**, **credit limit**, payment terms, opening outstanding, product interests

**Actions:** create, edit, view, archive/restore, activate/deactivate

**Detail page tabs:** Overview | Territory | Orders | Payments & Outstanding | Follow-ups | Remarks | Activity Timeline

**Quick actions (detail page header):** Create Order, Add Payment, Add Follow-up, Add Remark

### 7.2 Territory Allocation UI

- Party detail par **Territory tab**: assigned districts + pincodes ki list, **effective from / to** dates ke saath
- **Add Allocation** dialog: district multi-select ya pincode multi-select + effective date range
- **Territory History** timeline — purani allocations dikhein, delete na hon (historical record)
- **Violation block dialog** (order form ke liye, F7 me wire hoga): "Ye pincode [X] party [Y] ke territory me nahi hai" + reason dikhana
- **Admin Override modal**: reason textarea **mandatory** + confirm → override audit entry mock

> Hold: unassigned pincode policy (H6) — abhi Settings me sirf radio placeholder.

**DoD:** party CRUD + territory allocation mock par kaam kare, override modal reason ke bina submit na ho.

---

## 8. PHASE F6 — Products + Pricing + Schemes

### 8.1 Products (abhi read-only)

**Fields (FRS §11):** SKU/product code, name, composition, pack size, dosage form, category, **MRP, PTS, Default Franchise/Net Rate**, GST%, scheme eligibility flag, storage requirement, shelf-life

**Actions:** create, edit, view, activate/deactivate, archive. Delete button tabhi enable ho jab mock me koi transaction reference na ho (warna disabled + tooltip).

### 8.2 Pricing Matrix

- **Price list screen:** product × pricing tier ka grid — MRP / PTS / Net Rate columns
- **Party-specific rate:** party detail se ya pricing screen se special rate add karna
- **Effective dating:** har rate row me `effectiveFrom` / `effectiveTo`; expired rows greyed out
- **Admin-only manual override:** rate edit par reason field mandatory
- **Rate history** per product — read-only list

> Hold: overlap priority resolution (H3) — mock me simply latest effective rate use karo aur "rate source" label dikha do.

### 8.3 Scheme Engine (UI)

- **Scheme CRUD:** name, scheme type (master), applicable products (multi-select), **min qty / max qty**, **free qty** (e.g. 10+1 → qty 10, free 1), start date, end date, priority, active flag
- **Scheme list** with active/expired filter
- Scheme preview: "10 + 1 free" ka readable badge

> Hold: stacking rules (H4) — ek time par ek hi scheme apply karo.

**DoD:** product CRUD + pricing grid + scheme CRUD mock par complete; order form ke liye rate aur free-qty resolve karne wala helper function ready ho (mock logic).

---

## 9. PHASE F7 — Orders

**Sabse bada form module.** Ye F2–F6 ka sab kuch consume karta hai — isliye inke baad.

### 9.1 Order form

- **Header:** party select → auto-fill shipping/billing address, pricing tier, sales team, payment terms
- **Line items table:** product select → rate auto-fill (F6 helper se) → qty input → **free qty auto-calculate (scheme se, alag column)** → discount → GST → line net amount
- **Footer totals:** gross, discount, GST breakup, net amount
- **Actions:** Save Draft | Submit | Cancel | Print/Export

### 9.2 Validation UI (mock — real validation backend karega)

Submit par ye messages dikhane ka UI ready rakho:

| Check | UI behaviour |
|---|---|
| Territory violation | Block dialog + reason + Admin override option (F5 ka modal) |
| Stock short | Line item par red highlight + available qty |
| Inactive party | Banner + submit disabled |
| Credit limit | **Warning banner only** (H5 — block mat karo) |

### 9.3 Order list + detail

- List: order no, party, date, amount, status chip, dispatch status
- Detail: line items, totals, **status timeline** (Draft → Confirmed → Billed → Packed → Dispatched → Delivered), linked invoice + dispatch + payments
- Actions: view, edit draft, submit, cancel, delete draft, print

**DoD:** order create → line items → scheme free qty → totals → submit, sab mock data par chale; validation dialogs trigger ho sakein (mock flags se).

---

## 10. PHASE F8 — Inventory & Batch + Near-Expiry

### 10.1 Batch management

- **Batch list:** product, batch no, mfg date, expiry date, received qty, available qty, reserved qty, damaged/returned, warehouse/location, status (saleable / quarantined / recalled / expired)
- **Add/Edit batch** form
- **Movement ledger:** 8 types — Receipt, Sale/Dispatch, Reservation, Return, Damage, Expiry, Adjustment, Transfer. Filterable list per batch/product.
- **Stock adjustment** form + **Stock transfer** form (reason mandatory)

### 10.2 Near-Expiry

- **Near-expiry screen:** buckets **180 / 90 / 60 / 30 days** — tabs ya segmented control
- Columns: product, batch, expiry, available qty, **stock value**
- Configurable threshold setting (Settings me)
- **Export CSV** button
- Expired stock **alag section** (saleable se separate)

> Hold: **FEFO allocation logic (H1)** — is phase me sirf **"Allocate Batches" button + allocation preview table** ka UI banao. Preview rows mock se aayein (earliest expiry first sort kar do display ke liye). Actual selection rule backend decide karega. Admin manual batch override ka UI + reason field bhi bana do.

**DoD:** batch CRUD + movement ledger + near-expiry buckets + export UI complete. FEFO preview screen bana ho par logic simulated.

---

## 11. PHASE F9 — Billing/Invoice + Dispatch

### 11.1 Invoice

- **Invoice screen:** invoice number, date, party + shipping details, **batch-wise line items**, billed qty + free qty (alag columns), rate, discount, GST breakup, totals
- **Print/PDF layout** — alag print CSS, A4 optimized
- **Cancel invoice** — reason mandatory + audit entry
- Invoice list with status filter

> Hold: exact GST format aur number series (H7) — generic layout, number field editable.

### 11.2 Dispatch

- **Dispatch form:** dispatch status, dispatch date, **transporter (master se)**, **LR number**, **tracking URL**, boxes/units, remarks
- **Order tracking timeline:** Confirmed → Billing → Allocated → Packed → Dispatched → Delivered
- Dispatch pending list (jo orders billed hain par dispatch nahi hue)

**DoD:** invoice generate (mock) + print preview + dispatch entry + tracking timeline — sab UI complete.

---

## 12. PHASE F10 — Payments & Outstanding

- **Payment CRUD:** party, amount, payment mode (master), date, reference/receipt no, remarks
- **Invoice-wise outstanding** view: invoice, amount, paid, balance, aging (0-30 / 31-60 / 61-90 / 90+)
- **Party-wise outstanding** summary
- **Payment allocation UI:** ek payment ko multiple invoices par allocate karna
- **PDC tracking** list (cheque no, due date, status)
- **Payment reminder** list — overdue payments
- Payment history per party

**DoD:** payment entry se outstanding update hote dikhe (mock calculation), aging buckets sahi render hon.

---

## 13. PHASE F11 — Dashboard + Reports + Audit Logs

**Ab banao — kyunki ab saare modules ka data shape final hai.**

### 13.1 Dashboard (Admin + Sales split)

12 widgets (FRS §22):

| Widget | Type |
|---|---|
| New leads | stat card (clickable → filtered list) |
| Unassigned leads | stat card |
| **First-response SLA** | stat + trend chart |
| Due / Overdue follow-ups | stat cards |
| Lead conversion | funnel ya bar chart |
| Orders & sales | line chart (Recharts) |
| Outstanding & payments | stat + bar chart |
| **Near-expiry inventory (stock value ke saath)** | stat card + drill-down |
| **Territory violation attempts** | stat card |
| **Blocked orders** | stat card |
| Dispatch pending | stat card |
| Sales team productivity | table ya bar chart |

- **Admin** = sab kuch; **Sales** = sirf apna ownership-filtered data
- Har stat card **clickable** → corresponding filtered list page
- Recharts use karo (dependency already hai, chart abhi tak use nahi hua)

### 13.2 Reports (16 reports, FRS §23)

Lead source, Response time, Conversion, Sales team productivity, Territory sales, Party sales, Product sales, Scheme utilization, Order status, Dispatch/LR pending, Payment/outstanding, Batch inventory, Near-expiry, Territory violations, Webhook failures, WhatsApp delivery

Ek **generic report shell** banao: filters (date range + entity filters) → table → **CSV export** → **print layout**. Har report usi shell ka config ho, alag-alag page mat banana.

### 13.3 Audit Logs

- Filterable viewer: entity, action, user, date range
- **Old value / new value diff** display (side-by-side ya inline)
- Read-only

**DoD:** dashboard dono roles ke liye render ho, saare 16 reports shell se generate hon, export + print kaam kare.

---

## 14. PHASE F12 — Distributor Onboarding & Invitation

> FRS v2.2 §38. Ye portal se **pehle** banega, kyunki portal ka user isi approval se create hota hai.

### 14.1 Admin/Sales side screens (CRM ke andar)

| Screen | Detail |
|---|---|
| **Invite list** | Sab invites: firm name, contact, mobile, invited by, channel, status chip, sent date, expiry. Filters: status, invited by, date range |
| **Generate Invite** dialog | Fields: prospect/firm name, contact person, mobile/WhatsApp, email, proposed State/District/City/Pincode, proposed pricing tier (Admin only), linked lead ref, expiry date, notes. Share options: **WhatsApp / Email / Copy Link** |
| **Lead detail par "Send Franchise Invite" button** | Lead se invite generate ho to lead reference auto-link ho jaye (approval par lead → party convert hoga) |
| **Invite actions** | Resend (naya token, purana invalid), Revoke, Copy link |
| **Registration review queue** | Submitted registrations: firm, GSTIN, submitted date, assigned sales team, status |
| **Registration detail / review screen** | Poora submitted form read-only + **uploaded documents preview** + verification checklist per document + duplicate warning banner (GSTIN/mobile match) + territory conflict warning |
| **Approve dialog** | Admin yahan set karega: assigned territory (district/pincode + effective from), pricing tier, credit limit, payment terms, assigned sales team, opening outstanding |
| **Reject dialog** | Reason **mandatory** |
| **Request more info** | Form wapas applicant ko bhejne ka action + message |

**Permission UI rule:** Sales Team ko invite generate/share/resend dikhna chahiye, par **Approve/Reject button dikhna hi nahi chahiye** (`PermissionGate` se hide karo, sirf disable mat karo).

### 14.2 Public registration page (login ke bina)

- **Alag layout** — CRM ka header/nav nahi, simple branded page
- Invite token URL se aayega: `/register/:token`
- **Invalid / expired / already-used token** ke liye alag states
- **Multi-step form:** (1) Contact verification → (2) Firm & KYC details → (3) Address → (4) Document upload → (5) Review & submit
  - Fields: firm name, constitution type, contact person + designation, mobile, email, password create, GSTIN, drug licence no + validity, PAN, billing address, shipping address, State/District/City/Area/Pincode, bank details (optional), preferred product categories, declaration checkbox
  - Uploads: drug licence, GST certificate, PAN, cancelled cheque — file preview + size/type validation
- **OTP step ka UI** (H11) — verify hamesha success return kare
- **Submit success screen:** "Aapki registration Admin review me hai" + status tracking reference

**DoD:** Sales user lead se invite bhej sake → link se registration form bhare → Admin review queue me dikhe → approve/reject ho → approved party list me dikhe. Pura flow mock par.

---

## 15. PHASE F13 — Distributor Portal (Owner)

**Ye practically ek alag application hai** — alag layout, alag navigation, alag auth context.

- **Alag layout** (`DistributorLayout`) — internal CRM ka dark/red header nahi, simpler customer-facing header
- **Mock login** (H10) — hardcoded distributor owner user
- **Screens:**
  - Dashboard (orders summary, outstanding, recent dispatches, payments due, **own team DCR compliance** — F14 ke baad wire hoga)
  - Catalogue (products grid + search + category filter)
  - **Applicable pricing** (sirf uske tier ka rate — MRP/PTS nahi dikhana)
  - Cart + order placement
  - Order history + **status timeline**
  - Invoice view / download
  - Dispatch details + transporter/LR + **tracking link**
  - Outstanding / payment status + ageing
  - **Offers & Schemes** — applicable running schemes with validity
  - Profile + shipping address
  - **My Team** — distributor apne DCR team users create/edit/deactivate kare, area/beat assign kare
  - Support / contact

**Important:** Distributor ko **sirf apna data** dikhna chahiye — mock data filtering me ye enforce karo, warna demo me galat data leak dikhega. Company Admin ke screens me distributor ke team users kahin nahi dikhne chahiye.

**DoD:** distributor login → catalogue → cart → order place → order track → outstanding → team user create, sab mock par chale.

---

## 16. PHASE F14 — DCR (Daily Call Report)

> FRS v2.2 §40. **Ye distributor ke andar ka module hai — company Admin ke side par iska koi screen nahi banega** (H14).

**Do portal roles ka fark yahan dikhta hai:** Distributor Owner review/approve karta hai, Distributor Team User (field user) entry karta hai. Dono ke liye alag menu.

### 16.1 Field Customer Master (distributor ke under)

- List + create/edit: name, type (**Doctor / Chemist / Stockist / Hospital**), specialty (doctor ke liye), clinic/firm name, mobile, email, address, area/beat, city, pincode, **category A/B/C**, visit frequency, DOB/anniversary, remarks, active flag
- Duplicate check mobile par (distributor scope me)
- Type ke hisaab se form fields conditionally dikhein

### 16.2 Beat / Area + Tour Plan

- Beat master (area grouping)
- **Monthly tour plan** — calendar grid: date → planned beat/customers, per team user
- Plan vs actual indicator

### 16.3 DCR Entry (field user) — mobile-first

Ye screen primarily mobile par use hogi, isliye **mobile layout pehle design karo, desktop baad me**.

- **Header:** report date (ek din ka ek DCR), work type (field work / leave / holiday / meeting / training), beat/area
- **Visit entries** (repeatable block — add/remove):
  - field customer select (master se), customer type auto
  - visit time, visit purpose (call / follow-up / order collection / payment collection / sampling)
  - **products promoted** (company catalogue se multi-select, read-only catalogue)
  - samples/gifts issued (item + qty)
  - **POB** — product, qty, value
  - feedback/remark, next visit date
  - optional: location capture (H12), photo
- **Day summary bar:** total calls, doctors, chemists, stockists, total POB value — live calculate ho
- **Actions:** Save Draft | Submit
- Status chip: Draft / Submitted / Approved / Rejected

### 16.4 DCR Approval (distributor owner)

- **Pending approvals list** — user, date, calls, POB value
- DCR detail read-only view + **Approve / Reject with remark**
- Approved DCR **locked** — edit disabled, sirf "Reopen with reason" button (Owner ke liye)
- **Missed DCR** list — jo cut-off tak submit nahi hue, auto-flag

### 16.5 POB → Order

- POB summary screen → select entries → **"Convert POB to Order"** → F7 ka order form pre-filled khule
- Order par source DCR reference dikhe

### 16.6 DCR Reports (distributor scope)

Daily/monthly call summary per user, average calls per day, coverage %, doctor/chemist-wise visit history, product-wise promotion count, POB summary (product/user/customer wise), missed DCR & compliance %, plan vs actual, sample/gift issuance, expense summary

F11 wala generic report shell reuse karo — naye report pages mat banao.

**DoD:** field user login → field customer add → DCR entry → submit → owner approve → report me dikhe → POB se order create ho. Pura flow mock par, aur **mobile par usable ho**.

---

## 17. PHASE F15 — Responsive Pass + Final UI QA

- **Har list page** par mobile card fallback (`MobileCardList`) wire karo
- **Har form** ko mobile par verify karo (grid → single column)
- Dashboard widgets mobile stacking
- Distributor portal mobile-first check (distributor zyada tar mobile par hoga)
- **DCR screens ka mobile check sabse zaroori** — field user poore din mobile par hi DCR bharega
- **Screen-by-screen visual QA** — checklist me ye pending mark hai
- Saare empty/loading/error states verify
- Final `npm run build` + `npm run lint` + coverage checklist update

---

## 18. Screen Count Estimate

| Phase | Naye screens | Naye components |
|---|---:|---:|
| F1 Foundation | 0 | ~10 |
| F2 Masters | ~20 | 2 |
| F3 Roles & Permissions + Users | 9 | 4 |
| F4 Leads + Follow-ups | 3 | 4 |
| F5 Parties + Territory | 4 | 3 |
| F6 Products + Pricing + Schemes | 7 | 3 |
| F7 Orders | 4 | 3 |
| F8 Inventory + Near-Expiry | 6 | 2 |
| F9 Billing + Dispatch | 5 | 2 |
| F10 Payments | 5 | 2 |
| F11 Dashboard + Reports + Audit | 5 | 5 |
| F12 Onboarding & Invitation | 8 | 3 |
| F13 Distributor Portal | 12 | 3 |
| F14 DCR | 10 | 4 |
| **Total** | **~98** | **~50** |

---

## 19. Backend Integration (baad ka phase — abhi sirf tayari)

Abhi kuch nahi karna, bas **ye 2 discipline follow karo** taaki integration ke time rework na ho:

1. **Mock data ka shape = API response ka shape.** Types `src/types/` me rakho, mock aur API dono usi type ko satisfy karein.
2. **Data access ek jagah se ho.** Components direct mock import na karein — `src/services/` ke through jayein. Baad me usi service file me localStorage ki jagah HTTP call daalna hai, components untouched rahenge.

**Team se jab mile to maang lena:** endpoint list + request/response shape + error format. Jitni jaldi mile, utna accurate mock ban payega.

---

## 20. Progress Tracker

| Phase | Status | Notes |
|---|---|---|
| F1 Foundation | ⬜ Not started | |
| F2 Masters | ⬜ Not started | |
| F3 Roles & Permissions + Users | ⬜ Not started | Multi-role HOLD |
| F4 Leads + Follow-ups | ⬜ Not started | |
| F5 Parties + Territory | ⬜ Not started | |
| F6 Products + Pricing + Schemes | ⬜ Not started | |
| F7 Orders | ⬜ Not started | |
| F8 Inventory + Near-Expiry | ⬜ Not started | FEFO logic HOLD |
| F9 Billing + Dispatch | ⬜ Not started | GST format HOLD |
| F10 Payments | ⬜ Not started | |
| F11 Dashboard + Reports + Audit | ⬜ Not started | |
| F12 Onboarding & Invitation | ⬜ Not started | OTP HOLD |
| F13 Distributor Portal | ⬜ Not started | Auth HOLD |
| F14 DCR | ⬜ Not started | Geo-location + offline HOLD |
| F15 Responsive + QA | ⬜ Not started | |
