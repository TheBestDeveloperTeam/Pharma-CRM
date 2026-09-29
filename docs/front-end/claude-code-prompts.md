# Claude Code — Step-by-Step Prompts (Pharma CRM Frontend)

Har prompt **ek session** ke liye hai. Neeche wale prompts seedha copy-paste karne ke liye hain.

## Session chalane ka tarika

1. Naya phase shuru karne se pehle **`/clear`** karo. Purana context saath le jaane se Claude Code confuse hota hai aur galat file touch karta hai.
2. Bade phases me pehle **plan mode** (Shift+Tab do baar) me prompt do — plan dekh lo, phir approve karo.
3. Phase khatam hone par build + lint chalwa lo, phir hi agle phase par jao.
4. Beech me kuch galat lage to turant roko — 10 files badalne ke baad rokna mehnga padta hai.

---

# SESSION 0 — Setup & Audit

```
Is repo me do naye documents add kiye gaye hain: docs/Pharma_CRM_Master_FRS_v2.3.md
aur docs/frontend-ui-build-plan.md. Root me CLAUDE.md bhi hai.

Teeno files padho. Phir current codebase ka audit karke batao:

1. Abhi kaun se modules actually working hain (CRUD chal raha hai) aur kaun se
   sirf read-only placeholder hain
2. Current folder structure vs CLAUDE.md me di gayi target structure — kya gap hai
3. src/mock aur src/mocks dono kahan-kahan use ho rahe hain
4. Existing shared components ki list — naam aur kya karte hain
5. Routing aur permission guard abhi kaise kaam kar raha hai

Sirf report do. Abhi koi code change mat karo.
```

---

# SESSION 1 — F1a: Shared Components

```
Build plan ka Phase F1 shuru kar rahe hain, uska pehla hissa: shared components.

docs/frontend-ui-build-plan.md ka section 3.1 padho aur wahan listed saare
components banao:
FilterDrawer, DateRangePicker, Timeline, TableSkeleton, FormSkeleton, CardSkeleton,
ErrorState, ConfirmDialog (optional reason input ke saath), CurrencyField,
MobileCardList, AppErrorBoundary, PermissionGate

Rules:
- Existing theme aur components ka style follow karo, naya design system mat banao
- Har component TypeScript typed ho, koi any nahi
- Ek demo page banao (/dev/components) jahan saare components render hokar dikhein,
  taaki main visually verify kar sakun
- Existing screens ko abhi touch mat karo

Khatam hone par npm run build aur npm run lint chalao.
```

---

# SESSION 2 — F1b: Mock Data + Types

```
Phase F1 ka doosra hissa: mock data restructure.

docs/frontend-ui-build-plan.md section 3.2 follow karo.

1. src/mocks/ me per-entity files banao: leads, parties, products, pricing, schemes,
   orders, invoices, batches, dispatches, payments, masters, users, roles,
   notifications, auditLogs, whatsappMessages, webhookEvents, distributorInvites,
   fieldCustomers, dcrReports
2. Har entity ka TypeScript type src/types/ me export karo — yahi types baad me
   API response types banenge
3. Relational IDs consistent rakho — partyId, productId, orderId files ke beech
   match karein
4. Realistic Indian pharma data use karo (firm names, GSTIN format, pincodes,
   compositions, batch numbers)
5. Legacy src/mock ke imports ko src/mocks par migrate karo, legacy folder hatao
6. src/services/ me data access layer banao — components mock se direct import
   na karein, services se lein

Har entity ke 15-25 records rakho taaki list, filter aur pagination sach me test ho sake.

Build aur lint pass karke batao.
```

---

# SESSION 3 — F1c: Permission System

```
Phase F1 ka teesra hissa: dynamic permission system.

Padho: docs/frontend-ui-build-plan.md section 3.3, aur
docs/Pharma_CRM_Master_FRS_v2.3.md ke sections 44.3 (permission catalogue)
aur 44.4 (data scope model).

Banao:
1. src/constants/permissions.ts — FRS 44.3 ka pura module x action catalogue
   (22 modules). Ye static constant hai.
2. Data scope enum: ALL | TERRITORY | TEAM | OWN | NONE
3. useEffectivePermissions() hook — logged-in user ki permissions + scope dega
4. can(module, action) helper
5. PermissionGate component — permission na ho to render hi na kare (disable nahi)
6. Navigation menu permissions se filter ho — hardcoded menu array hatao
7. Route guard me required permission ho, na ho to Access Denied page

Bahut zaroori: kahin bhi role NAME par check mat likhna (user.role === 'admin').
Hamesha permission par check karna. Roles runtime par Admin banayega.

Existing routes aur screens abhi wahi rahenge — bas guard mechanism badal raha hai.
Verify karo ki existing app abhi bhi chal raha hai.
```

---

# SESSION 4 — F2a: Geography Masters

```
Phase F2 shuru: masters. Pehle geography masters, kyunki inpar baaki sab depend karta hai.

docs/frontend-ui-build-plan.md section 4.1 follow karo.

Banao:
1. State master — list + create/edit + activate/deactivate
2. District master — State se linked
3. City/Area master — District se linked
4. Pincode master — City/District se linked
5. CascadingLocationSelect component (State > District > City > Pincode) —
   ye aage leads, parties, orders sab forms me reuse hoga

Realistic data: kam se kam 8 states, 40 districts, 100 cities, 300 pincodes.

Ek jagah cascading select ko test karke dikhao ki child dropdown parent ke
hisaab se filter ho raha hai.
```

---

# SESSION 5 — F2b: Baaki Masters

```
Phase F2 ka doosra hissa: baaki masters.

docs/frontend-ui-build-plan.md section 4.2 ke hisaab se ye masters banao:
Lead Source, Lead Status, Follow-up Type, Party Type, Product Category, Dosage Form,
Pricing Tier, Scheme Type, Payment Mode, Order Status, Dispatch Status, Transporter,
Notification Template, Webhook Source, Designation, Department, Field Customer Type,
Doctor Specialty, Beat/Area, Visit Purpose, Sample/Gift Item, KYC Document Type,
Customer Category

Sabka pattern same hai: list + add/edit dialog + activate/deactivate + search.
Isliye ek generic MasterListPage component banao jo config se chale —
har master ka alag page mat likhna.

Masters landing page par sab masters ka grid/menu ho.
```

---

# SESSION 6 — F3a: Roles & Permissions UI

```
Phase F3 shuru: role management.

Padho: docs/frontend-ui-build-plan.md section 5.1, aur
docs/Pharma_CRM_Master_FRS_v2.3.md sections 44.5, 44.6, 44.9.

Banao:
1. Role list — name, description, assigned users count, scope badge, active flag.
   Admin system role par delete/edit disabled + lock icon
2. Create/Edit Role — name, description, data scope (All/Territory/Team/Own), active
3. Permission selector — module-wise accordion, har module ke applicable actions ke
   checkboxes, "select all in module" shortcut
4. Sensitive permissions (FRS 44.9 ki list) tick karte waqt confirm dialog + warning chip
5. Clone Role
6. Permission Matrix view — rows = permissions, columns = roles, read-only grid
7. Delete role — users assigned hon to block

Seed data me FRS 44.5 ke 7 role templates daalo: Admin, Manager, Sales Team,
Order/Dispatch Team, Accounts Team, Inventory Team, Auditor.

UI validations (FRS 44.9): Admin role edit/delete block, apne role ki permission
edit block, jo permission khud ke paas nahi wo grant option na dikhe.
```

---

# SESSION 7 — F3b: Internal Users + Role Switcher

```
Phase F3 ka doosra hissa: internal user management.

docs/frontend-ui-build-plan.md sections 5.2 aur 5.3 follow karo.

1. User list — name, employee code, email, mobile, role chip, reporting manager,
   territory, active status
2. Create/Edit user — role select, reporting manager select (Team scope isi par
   chalega), department, designation, assigned territory, active flag
3. User detail tabs: Assigned Leads | Follow-ups | Parties | Productivity (mock)
4. Actions: create, edit, activate/deactivate, reset password
5. UserSelect component — aage har "Assigned To" field me yahi use hoga
6. Role switcher — header me dropdown se logged-in role switch ho jaye
   (sirf mock phase ke liye, clearly comment karke)

Purana Settings wala static user table hata do.

Acceptance test jo main dekhunga: naya role "Dispatch Team" banao jisme sirf
Orders-View + Inventory + Dispatch permissions hon, us role ka user banao,
role switcher se us user me jao — menu me sirf teen module dikhne chahiye,
baaki routes par Access Denied aana chahiye.
```

---

# SESSION 8 — F4a: Leads Completion

```
Phase F4: existing Lead module ko FRS ke hisaab se complete karna.

Padho: docs/frontend-ui-build-plan.md section 6.1 aur 6.2, aur
docs/Pharma_CRM_Master_FRS_v2.3.md section 6.

Lead form me missing fields add karo: firm name, WhatsApp number, email,
State/District/City/Pincode (F2 ka CascadingLocationSelect), lead source (master se),
interested products/categories (multi-select), business type, priority, initial remark.

Missing actions:
1. Duplicate detection — mobile/GSTIN blur par mock check, warning dialog with
   "existing lead dekho / phir bhi banao"
2. Convert to Party button (abhi sirf navigation + prefill, party module F5 me aayega)
3. Source badge + external lead ID read-only display
4. First-response SLA — received se first response ka duration + breach indicator
5. FilterDrawer se bulk filters: status, source, owner, priority, date range, territory

Existing lead CRUD logic todna nahi hai — sirf extend karna hai.
Saare actions PermissionGate me wrap karo.
```

---

# SESSION 9 — F4b: Follow-ups Completion

```
Phase F4 ka doosra hissa: follow-up module complete karna.

docs/frontend-ui-build-plan.md section 6.3 aur FRS section 7 padho.

Missing actions add karo:
1. Complete — completion remark dialog ke saath
2. Reschedule — date/time picker + reason
3. Mark Missed — button + overdue auto-flag
4. Activity type: Call / Visit / WhatsApp — selector + icon per row

Naye views:
5. Tabs: Today / Overdue / Upcoming / Completed
6. Calendar view — month grid with follow-up dots, date click par us din ki list

Validation: active follow-up ke liye next action + next date mandatory.

Existing add-followup aur remark history working hai — usko todna nahi.
```

---

# SESSION 10 — F5: Parties + Territory

```
Phase F5: Party module (abhi sirf read-only list hai) aur Territory allocation.

Padho: docs/frontend-ui-build-plan.md section 7, aur
docs/Pharma_CRM_Master_FRS_v2.3.md sections 9 aur 10.

Party module:
- List, create/edit, detail
- Saare fields FRS section 9 se: GSTIN, drug licence + validity, billing/shipping
  address, territory, pricing tier, agreement dates, credit limit, payment terms,
  opening outstanding, product interests
- Actions: create, edit, archive/restore, activate/deactivate
- Detail tabs: Overview | Territory | Orders | Payments | Follow-ups | Remarks | Activity
- Quick actions: Create Order, Add Payment, Add Follow-up, Add Remark
  (jo module abhi nahi bana, uska button disable + tooltip)

Territory:
- Party detail par Territory tab — district/pincode allocation with effective dates
- Add Allocation dialog
- Territory history timeline (purani allocation delete na ho)
- Violation block dialog component (F7 me order form se wire hoga)
- Admin Override modal — reason mandatory

HOLD: unassigned pincode policy ka logic mat likhna, Settings me sirf radio placeholder.
```

---

# SESSION 11 — F6a: Products

```
Phase F6 ka pehla hissa: Product module (abhi read-only hai).

docs/frontend-ui-build-plan.md section 8.1 aur FRS section 11 padho.

Full CRUD banao with fields: SKU/product code, name, composition, pack size,
dosage form, category, MRP, PTS, default franchise/net rate, GST%, scheme eligibility,
storage requirement, shelf-life.

Actions: create, edit, view, activate/deactivate, archive.
Delete button tabhi enable ho jab mock me koi transaction reference na ho,
warna disabled + tooltip.
```

---

# SESSION 12 — F6b: Pricing + Schemes

```
Phase F6 ka doosra hissa: pricing matrix aur scheme engine ka UI.

docs/frontend-ui-build-plan.md sections 8.2, 8.3 aur FRS section 12 padho.

Pricing:
1. Price list screen — product x pricing tier grid (MRP / PTS / Net Rate)
2. Party-specific rate add karna
3. Effective dating — effectiveFrom/effectiveTo, expired rows greyed out
4. Manual rate override with mandatory reason (sensitive permission ke peeche)
5. Rate history per product

Schemes:
6. Scheme CRUD — name, type, applicable products, min/max qty, free qty,
   start/end date, priority, active
7. Scheme list with active/expired filter
8. "10 + 1 free" readable badge

9. Ek helper function banao jo product + party + qty lekar applicable rate aur
   free qty return kare — F7 ka order form isi ko use karega

HOLD: pricing overlap priority ka real logic mat likhna — latest effective rate
use karo aur "rate source" label dikha do. Scheme stacking bhi HOLD — ek time
par ek hi scheme apply karo.
```

---

# SESSION 13 — F7: Orders

```
Phase F7: Order module. Ye sabse bada form module hai.

Padho: docs/frontend-ui-build-plan.md section 9, aur FRS section 13.

Order form:
- Header: party select se shipping/billing address, pricing tier, sales team,
  payment terms auto-fill
- Line items: product select se rate auto-fill (F6 ka helper), qty, free qty
  auto-calculate (alag column), discount, GST, line net amount
- Footer totals: gross, discount, GST breakup, net amount
- Actions: Save Draft | Submit | Cancel | Print/Export

Validation UI (mock flags se trigger ho sake):
- Territory violation — F5 ka block dialog + admin override option
- Stock short — line item red highlight + available qty
- Inactive party — banner + submit disabled
- Credit limit — sirf warning banner, block mat karo (HOLD item hai)

Order list + detail with status timeline
(Draft > Confirmed > Billed > Packed > Dispatched > Delivered).

Line item calculations par dhyan do — rounding aur GST split sahi hona chahiye.
```

---

# SESSION 14 — F8: Inventory, Batch & Near-Expiry

```
Phase F8: inventory aur batch management.

docs/frontend-ui-build-plan.md section 10 aur FRS sections 14, 15 padho.

1. Batch list — product, batch no, mfg date, expiry, received/available/reserved/
   damaged qty, warehouse, status (saleable/quarantined/recalled/expired)
2. Add/Edit batch form
3. Movement ledger — 8 types (Receipt, Sale/Dispatch, Reservation, Return, Damage,
   Expiry, Adjustment, Transfer), filterable
4. Stock adjustment + stock transfer forms (reason mandatory)
5. Near-expiry screen — 180/90/60/30 day buckets as tabs, columns: product, batch,
   expiry, available qty, stock value
6. Expired stock alag section
7. Export CSV

HOLD — FEFO: sirf "Allocate Batches" button + allocation preview table ka UI banao.
Preview rows mock se aayein (display ke liye earliest expiry first sort kar do).
Actual allocation rule backend decide karega — logic mat likhna, TODO comment chhodo.
Admin manual batch override ka UI + reason field bhi bana do.
```

---

# SESSION 15 — F9: Billing + Dispatch

```
Phase F9: invoice aur dispatch.

docs/frontend-ui-build-plan.md section 11 aur FRS sections 16, 17 padho.

Invoice:
1. Invoice screen — invoice number, date, party + shipping details, batch-wise line
   items, billed qty + free qty alag columns, rate, discount, GST breakup, totals
2. Print/PDF layout — alag print CSS, A4 optimized
3. Cancel invoice with mandatory reason
4. Invoice list with status filter

Dispatch:
5. Dispatch form — status, date, transporter (master se), LR number, tracking URL,
   boxes/units, remarks
6. Order tracking timeline
7. Dispatch pending list

HOLD: GST format aur invoice number series pending hai — generic layout banao,
number field editable rakho.
```

---

# SESSION 16 — F10: Payments & Outstanding

```
Phase F10: payments.

docs/frontend-ui-build-plan.md section 12 aur FRS section 20 padho.

1. Payment CRUD — party, amount, payment mode, date, reference/receipt no, remarks
2. Invoice-wise outstanding — invoice, amount, paid, balance, ageing buckets
   (0-30 / 31-60 / 61-90 / 90+)
3. Party-wise outstanding summary
4. Payment allocation UI — ek payment multiple invoices par allocate
5. PDC tracking list
6. Payment reminder list — overdue payments
7. Payment history per party

Ageing calculation aur allocation math sahi hona chahiye — ye demo me pakka
check hoga.
```

---

# SESSION 17 — F11a: Dashboards

```
Phase F11 ka pehla hissa: dashboards.

docs/frontend-ui-build-plan.md section 13.1 aur FRS section 22 padho.

Admin aur Sales ke alag dashboards banao, 12 widgets:
new leads, unassigned leads, first-response SLA, due/overdue follow-ups,
lead conversion, orders & sales, outstanding & payments, near-expiry inventory
(stock value ke saath), territory violation attempts, blocked orders,
dispatch pending, sales team productivity.

- Charts Recharts se (dependency already hai, ab tak use nahi hui)
- Har stat card clickable ho — corresponding filtered list page par le jaye
- Widgets permissions ke hisaab se dikhein — jis module ka permission nahi,
  us module ka widget nahi
- Purana dashboard replace kar do

Data scope respect karo: Own scope wale user ko sirf apna data dikhe.
```

---

# SESSION 18 — F11b: Reports + Audit Logs

```
Phase F11 ka doosra hissa: reports aur audit log viewer.

docs/frontend-ui-build-plan.md sections 13.2, 13.3 aur FRS section 23 padho.

1. Ek generic report shell banao: filters (date range + entity filters) > table >
   CSV export > print layout
2. FRS section 23 ke 16 reports usi shell ke config se banao — har report ka
   alag page mat likhna
3. Audit log viewer — filterable (entity, action, user, date range) with
   old value / new value diff display

Reports permissions ke hisaab se list me dikhein.
```

---

# SESSION 19 — F12a: Invite & Onboarding (Admin side)

```
Phase F12: distributor onboarding ka admin side.

Padho: docs/frontend-ui-build-plan.md section 14.1, aur
docs/Pharma_CRM_Master_FRS_v2.3.md section 38.

1. Invite list — firm, contact, mobile, invited by, channel, status chip, expiry.
   Filters: status, invited by, date range
2. Generate Invite dialog — FRS 38.4 ke fields, share options WhatsApp/Email/Copy Link
3. Lead detail par "Send Franchise Invite" button — lead reference auto-link ho
4. Invite actions: Resend, Revoke, Copy link
5. Registration review queue
6. Registration detail — submitted form read-only + document preview + verification
   checklist + duplicate warning + territory conflict warning
7. Approve dialog — territory, pricing tier, credit limit, payment terms,
   assigned sales team, opening outstanding
8. Reject dialog — reason mandatory
9. Request more info action

Permission rule: approve/reject ka button sirf us role ko dikhe jiske paas
"Distributor Onboarding: Approve" permission hai. Baaki roles ko dikhna hi nahi chahiye.
```

---

# SESSION 20 — F12b: Public Registration Page

```
Phase F12 ka doosra hissa: public registration page (login ke bina).

docs/frontend-ui-build-plan.md section 14.2 aur FRS section 38.6 padho.

1. Alag layout — CRM ka header/nav nahi, simple branded page
2. Route /register/:token
3. Invalid / expired / already-used token ke alag states
4. Multi-step form: contact verification > firm & KYC details > address >
   document upload > review & submit
   Fields FRS 38.6 se: firm name, constitution type, contact person, mobile, email,
   password, GSTIN, drug licence + validity, PAN, billing/shipping address,
   location, bank details, product categories, declaration
5. Document upload with preview + size/type validation
6. OTP step ka UI (HOLD — verify hamesha success return kare)
7. Submit success screen with tracking reference

Ye page mobile par bhi theek dikhna chahiye — distributor aksar mobile se bharega.
```

---

# SESSION 21 — F13: Distributor Portal

```
Phase F13: distributor portal (owner).

Padho: docs/frontend-ui-build-plan.md section 15, aur
docs/Pharma_CRM_Master_FRS_v2.3.md section 39.

1. Alag layout (DistributorLayout) — internal CRM ka dark/red header nahi,
   simpler customer-facing header
2. Mock login (HOLD — hardcoded distributor owner)
3. Screens: dashboard, catalogue, applicable pricing (sirf uska tier, MRP/PTS nahi),
   cart + order placement, order history + status timeline, invoice view/download,
   dispatch details + LR + tracking link, outstanding + ageing, offers & schemes,
   profile + shipping address, My Team (distributor apne team users banaye), support

Bahut zaroori — data isolation: distributor ko sirf apna data dikhna chahiye.
Mock filtering me ye enforce karo. Internal CRM ke screens me distributor ke
team users kahin nahi dikhne chahiye.
```

---

# SESSION 22 — F14a: DCR Masters & Entry

```
Phase F14: DCR module ka pehla hissa.

Padho: docs/frontend-ui-build-plan.md sections 16.1, 16.2, 16.3, aur
docs/Pharma_CRM_Master_FRS_v2.3.md section 40.

1. Field customer master (distributor ke under) — Doctor/Chemist/Stockist/Hospital,
   specialty, category A/B/C, area/beat, visit frequency. Duplicate check on mobile.
2. Beat/area master + monthly tour plan calendar grid
3. DCR entry screen — report date, work type, beat, aur repeatable visit entries
   (customer, visit time, purpose, products promoted, samples/gifts, POB, remark,
   next visit date)
4. Day summary bar — total calls, doctors, chemists, stockists, POB value, live calculate
5. Save Draft | Submit

Sabse zaroori: DCR entry screen MOBILE-FIRST design karo. Field user poore din
mobile par hi bharega. Pehle mobile layout banao, desktop baad me.

HOLD: geo-location capture ka field dikhao par mock coordinates use karo.
Offline support abhi nahi.
```

---

# SESSION 23 — F14b: DCR Approval, POB & Reports

```
Phase F14 ka doosra hissa.

docs/frontend-ui-build-plan.md sections 16.4, 16.5, 16.6 aur FRS 40.5-40.7 padho.

1. Pending approvals list (distributor owner ke liye) — user, date, calls, POB value
2. DCR detail read-only + Approve / Reject with remark
3. Approved DCR locked — edit disabled, sirf "Reopen with reason" button
4. Missed DCR list — cut-off tak submit nahi hue
5. POB summary screen > select entries > "Convert POB to Order" > F7 ka order form
   pre-filled khule, source DCR reference ke saath
6. DCR reports — F11 wala generic report shell reuse karo, naye report pages mat banao

Yaad rahe: DCR ka koi bhi screen company Admin ke side par nahi banana.
Ye poora module distributor portal ke andar hai.
```

---

# SESSION 24 — F15: Responsive Pass + Final QA

```
Phase F15: final responsive pass aur QA.

docs/frontend-ui-build-plan.md section 17 follow karo.

1. Har list page par MobileCardList fallback wire karo
2. Har form mobile par verify — grid single column ho jaye
3. Dashboard widgets mobile stacking
4. Distributor portal mobile check
5. DCR screens ka mobile check sabse zaroori hai
6. Saare empty / loading / error states verify karo — koi screen missing na ho
7. Navigation aur route guards har role ke liye test karo

Ek final report do: kaun si screens abhi bhi incomplete hain, aur
docs/requirement-coverage.md update kar do.

npm run build aur npm run lint pass hone chahiye.
```

---

# Beech me kaam aane wale prompts

**Kuch galat ho gaya:**
```
Pichle change me [X] toot gaya. Sirf usko theek karo, aur kuch mat badlo.
Batao ki kya galat hua tha.
```

**Phase khatam hone ke baad:**
```
Is phase ka kaam review karo aur docs/requirement-coverage.md update karo —
kya bana, kya bacha, kya HOLD par hai. Phir npm run build aur npm run lint chalao.
```

**Scope creep rokne ke liye:**
```
Ye is phase ke scope me nahi hai. Build plan me jo phase bola gaya hai bas wahi karo.
```

**Demo se pehle:**
```
Poore app ka smoke test karo — har route kholo aur batao kahan error aa raha hai,
kahan blank screen hai, kahan mock data missing hai. Sirf list do, fix mat karo.
```
