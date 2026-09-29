Functional Requirement Specification (FRS)

Pharma CRM & Sales Force Automation — Master Requirement Document

Version 2.3 | Consolidated baseline combining the original Pharma CRM FRS with the PCD/Franchise, territory, inventory, pricing, dispatch, portal and automation requirements; extended in v2.2 with Distributor Onboarding and the Distributor-owned DCR module, and in v2.3 with configurable internal Roles and Permissions.

Internal CRM roles: Admin plus any roles the Admin configures. Franchise Partner/Distributor is an external portal actor with two portal roles (Owner and Team User).

> **SUPERSEDED-SECTION NOTICE — read this before implementing anything.**
> This document is cumulative; later addenda override earlier text. Where they conflict, the later section wins:
> - Section 3 (Roles) and Section 25 (Permission Matrix) are superseded by **Section 44 + 45.1 + 45.4** — internal roles are configurable, not a fixed list.
> - Section 21 (Sales Team Management) is superseded by **Section 44.7** — it manages all internal users.
> - Section 18 (Distributor Portal) is extended by **Section 39** — two portal roles, plus team management.
> - Section 33 (Release Plan) is revised by **Section 41.7 and 45.8**.
> - Section 34 decision on Warehouse/Dispatch/Billing roles is resolved by **Section 45.9**.
> - FIFO anywhere in this document means **FEFO** (Section 14.1).

## 1. Project Objective

Build a B2B pharmaceutical CRM for PCD/Franchise business covering lead acquisition, sales follow-up, party onboarding, territory control, products, pricing and schemes, orders, batch inventory, billing, dispatch, payments, distributor self-service, WhatsApp notifications, webhook lead ingestion and reporting.

## 2. Scope

- Lead and follow-up management
- Party/Franchise Partner/Distributor management
- District and pincode territory allocation and violation blocking
- Product catalogue and CRUD
- B2B pricing: MRP, PTS and Franchise/Net Rate
- Quantity schemes such as 10+1 and 20+3
- Order, billing and invoice workflow
- Batch-wise inventory and FEFO allocation
- Near-expiry alerts
- Payments and outstanding
- Dispatch, transporter and LR tracking
- Distributor self-service portal
- WhatsApp notification integration
- B2B lead webhook integration
- Dashboards, reports, audit logs and notifications

## 3. Roles and Actors

| Actor | Type | Responsibilities |
|---|---|---|
| Admin | Internal role | Full CRM access; users, masters, territories, pricing, products, inventory, orders, payments, dispatch, reports, overrides and audit. |
| Sales Team | Internal role | Own/assigned leads, follow-ups, parties, eligible orders/payments and customer activity. |
| Franchise Partner / Distributor | External portal actor | Catalogue, applicable pricing, order placement, order/dispatch/payment status and tracking. |
| B2B Lead Source | External system | IndiaMART/PharmaHopers or other approved source sends leads through webhook/API. |
| Billing/Warehouse/Dispatch | Operational functions under Admin in Phase 1 | Billing, batch allocation, stock and LR/transporter updates. |

## 4. End-to-End Workflow

Lead Source / Manual Entry
        ↓
Lead Created → Duplicate Check → Sales Team Alert
        ↓
Call / WhatsApp / Visit → Remark + Next Follow-up
        ↓
Interested?
  No → Lost/Rejected
  Yes → Convert to Party
        ↓
Territory Allocation (District + Pincode)
        ↓
Onboarding / Agreement → Pricing Tier
        ↓
Order → Territory + Pricing + Scheme + Stock Validation
        ↓
FEFO Batch Allocation → Billing → Dispatch/LR
        ↓
Distributor Portal + WhatsApp Tracking
        ↓
Payment / Outstanding → Repeat Order → Retention

## 5. Functional Modules

- Dashboard
- Lead Management
- Lead Webhooks & Automated Nurturing
- Follow-ups & Remarks
- Party/Franchise Partner Management
- Territory & Pincode Geo-Locking
- Product Management
- Pricing Matrix & Scheme Engine
- Order Management
- Inventory & Batch Management
- FEFO & Near-Expiry Management
- Billing/Invoice
- Dispatch & Tracking
- Payment & Outstanding
- Distributor Portal
- WhatsApp & Notifications
- Sales Team Management
- Masters
- Reports & Analytics
- Audit Logs

## 6. Lead Management

### 6.1 Lead Create/Edit

- Lead/contact name
- Firm name
- Mobile/WhatsApp
- Email
- State/District/City/Pincode
- Lead source
- Interested products/categories
- Business type
- Assigned Sales Team
- Priority
- Status
- Initial remark
- Next follow-up date/time

### 6.2 Lead Actions

- Create
- View
- Edit
- Archive
- Assign/Reassign
- Add Follow-up
- Add Remark
- View remark history
- Convert to Party
Admin can manage all leads. Sales Team can manage its self-created/assigned leads according to ownership rules. Duplicate detection must use configured identifiers.

## 7. Follow-up

- Add/Edit Follow-up
- Complete
- Reschedule
- Mark Missed
- Add/Edit Remark
- View Remark History
- Next Action
- Next Follow-up Date/Time
- Call/Visit/WhatsApp activity
Every active follow-up must have a next action/date unless explicitly closed as Lost, Rejected or Converted.

## 8. Lead Webhook & Automated Nurturing

External Portal → Secure Webhook → Signature/Auth Check
→ Payload Validation → External-ID Deduplication
→ Lead Create → Auto Assignment → CRM Alert
→ Optional approved WhatsApp first response

- Store external lead ID and source
- Idempotent processing
- Retry failed webhook with controlled backoff
- Log webhook failures
- Measure first-response SLA
- Optional approved catalogue/introductory WhatsApp message subject to consent/provider rules
- Never hard-code a guaranteed conversion uplift

## 9. Party / Franchise Partner

- Create/Edit/View/Archive/Restore/Activate-Deactivate
- Firm and contact details
- GSTIN
- Drug license details where applicable
- Billing and shipping address
- State/District/City/Area/Pincode
- Assigned territory
- Assigned Sales Team
- Pricing Tier
- Agreement/Monopoly start/end
- Credit limit
- Payment terms
- Opening outstanding
- Product interests
- Create Order
- Add Payment
- Add Follow-up
- Add Remark

## 10. Territory Infiltration / Area Violation

A Franchise Partner can be assigned one or more effective-dated districts and/or pincodes. The system validates the shipping pincode against the active allocation before order submission/billing.

Party → Shipping Pincode → Pincode Master → District
       ↓
Active Territory Check
       ↓
Allowed? ─ Yes → Continue
       │
       No
       ↓
Block Order/Billing → Show Reason
       ↓
Optional Admin Override → Reason + Audit → Continue

- Validate at order creation and again at billing/submission
- Do not rely on free-text district fields
- Unassigned pincode behavior must be configurable: block, Admin review or approval
- Admin override requires reason and audit
- Territory history is effective-dated and historical orders are not rewritten

## 11. Product Management

- Create Product
- Edit Product
- View Product
- Activate/Deactivate
- Archive
- Delete only when no transaction references exist
- SKU/Product Code
- Name
- Composition
- Pack Size
- Dosage Form
- Category
- MRP
- PTS
- Default Franchise/Net Rate
- GST
- Scheme eligibility
- Storage requirement
- Shelf-life

## 12. Pricing Matrix & Scheme Engine

| Price | Purpose |
|---|---|
| MRP | Reference retail price |
| PTS | Price to Stockist |
| Franchise/Net Rate | Customer/tier applicable B2B rate |

- Effective-dated pricing
- Party-specific and/or tier-specific rates
- Rule priority for overlaps
- Historical order retains original rate
- Admin-only manual price override with reason
- Automatic quantity scheme calculation
- Free quantity shown separately
- Scheme start/end dates
- Minimum/maximum quantity
- Product eligibility
- Scheme stacking/priority rules
Example: 10+1 means qualifying purchase quantity 10 generates 1 free unit. The exact commercial scheme rules must be configurable.

## 13. Order Management

- Create Order
- View
- Edit Draft
- Submit/Confirm
- Cancel
- Delete Draft where permitted
- Print/Export
- Party
- Shipping/Billing address
- Sales Team
- Products
- Quantity
- Free Quantity
- Rate
- Discount
- GST
- Net Amount
- Payment Terms
- Status
Before billing/submission the system must validate territory, pricing, schemes, stock, active party and configured credit rules.

## 14. Inventory & Batch Management

- Batch Number
- Manufacturing Date
- Expiry Date
- Received Quantity
- Available Quantity
- Reserved Quantity
- Damaged/Returned
- Warehouse/Location
- Receipt
- Sale/Dispatch
- Reservation
- Return
- Damage
- Expiry
- Adjustment
- Transfer

### 14.1 FEFO — Important BA Correction

The supplied requirement says FIFO. For pharmaceutical distribution, FEFO (First Expiry, First Out) is the more appropriate default: the batch due to expire first should be distributed first, subject to business controls. CDSCO good-distribution guidance explicitly describes first-expiry/first-out. citeturn0search24

- Allocate eligible batches by earliest expiry
- Never allocate expired stock
- Exclude quarantined/recalled/damaged/blocked stock
- Optional configurable minimum remaining shelf-life at dispatch
- Manual batch override only for Admin with audit reason
- Reservation required to avoid concurrent-order over-allocation

## 15. Near-Expiry Management

- Default dashboard alert: stock expiring within 6 months
- Configurable 180/90/60/30-day buckets
- Product, batch, expiry, available quantity and stock value
- Export near-expiry report
- Optional scheme/promotion workflow initiated by Admin
- Expired stock separated from saleable stock

## 16. Billing / Invoice

- Invoice number
- Customer and shipping details
- Product/batch
- Billed and free quantity
- Rate/discount/GST
- Totals
- Print/PDF
- Cancellation with audit
- Historical invoice values remain unchanged after master price changes

## 17. Dispatch & Order Tracking

- Dispatch status
- Dispatch date
- Transporter
- LR Number
- Tracking URL
- Boxes/units if required
- Remarks
Confirmed → Billing → FEFO Allocation → Packed
→ Dispatched → Transporter/LR → Portal Update
→ WhatsApp Tracking → Delivered

## 18. Distributor Self-Service Portal

- Dashboard
- Catalogue
- Applicable pricing
- Cart/order placement
- Order history
- Order status timeline
- Invoice view/download
- Dispatch details
- Transporter/LR
- Tracking link
- Outstanding/payment status
- Profile/shipping address
- Support/contact

## 19. WhatsApp & Notifications

Use an official WhatsApp Business API/provider integration. Transactional messages must follow applicable template, consent and provider rules. The CRM records message state rather than assuming delivery.

- New lead
- Follow-up reminder
- Order confirmation
- Billing
- Dispatch/LR
- Payment reminder
- Payment received
- Near-expiry admin alert
Message states: Queued → Sent → Delivered → Read / Failed. Failed messages must be visible for retry/error review.

## 20. Payments & Outstanding

- Create Payment
- Edit where permitted
- View Payment
- Payment history
- Invoice-wise outstanding
- Party-wise outstanding
- Payment due reminders
- PDC tracking if used
- Receipt/reference
- Payment mode/date/amount/remarks

## 21. Sales Team Management

- Admin creates/edits/activates/deactivates Sales Team
- Assign territory/area
- View assigned/self-created leads
- Manage follow-ups
- Manage own parties
- Create/update eligible orders/payments
- Cannot manage products, pricing, masters or territory overrides
Sales Team management should be a dedicated module rather than a static Master because it has authentication, ownership and historical relationships.

## 22. Dashboard

- New leads
- Unassigned leads
- First-response SLA
- Due/overdue follow-ups
- Lead conversion
- Orders and sales
- Outstanding and payments
- Near-expiry inventory
- Territory violation attempts
- Blocked orders
- Dispatch pending
- Sales Team productivity

## 23. Reports

- Lead source
- Response time
- Conversion
- Sales Team productivity
- Territory sales
- Party sales
- Product sales
- Scheme utilization
- Order status
- Dispatch/LR pending
- Payment/outstanding
- Batch inventory
- Near-expiry
- Territory violations
- Webhook failures
- WhatsApp delivery

## 24. Masters

- State
- District
- City/Area
- Pincode
- Lead Source
- Lead Status
- Follow-up Type
- Party Type
- Product Category
- Dosage Form
- Pricing Tier
- Scheme Type
- Payment Mode
- Order Status
- Dispatch Status
- Transporter
- Sales Team
- Territory Allocation
- Notification Template
- Webhook Source

## 25. Permission Matrix

| Function | Admin | Sales Team | Distributor Portal |
|---|---|---|---|
| Leads | Full | Own/assigned | No |
| Follow-ups | Full | Own/assigned | No |
| Parties | Full | Own/assigned | Own profile |
| Territory | Full + override | View assigned | View own |
| Products | Full CRUD | View | View |
| Pricing/Schemes | Full | Applicable view | Applicable view |
| Orders | Full | Own/eligible | Own |
| Inventory | Full | Availability view | Availability view |
| Billing | Full | No override | No |
| Dispatch | Full | View | Own |
| Payments | Full | Own/eligible | Own status |
| Sales Team | Full | No | No |
| Masters | Full | No | No |
| Audit | Full | Own activity where approved | No |

## 26. Core Business Rules

- Duplicate mobile/GST/business identifiers restricted by configured rules
- Active follow-up requires next action/date
- Converted lead retains history
- Territory allocation is enforced before billing
- Admin territory override requires reason/audit
- Pricing is effective-dated
- Schemes calculate automatically
- Historical transaction pricing never changes
- FEFO is default batch allocation
- Expired/recalled/quarantined stock cannot dispatch
- Near-expiry thresholds configurable
- Webhook processing is idempotent
- External lead IDs stored
- Payment changes update outstanding transaction-safely
- Historical transactions remain auditable

## 27. Security, Audit & Non-Functional Requirements

- HTTPS and authenticated APIs
- Server-side authorization for every business rule
- Secure secret storage
- Indexed DB queries and pagination
- Transactional stock/order/payment updates
- Webhook signature/authentication
- Idempotency and retries
- Audit old/new values for critical actions
- Backup/restore
- Health monitoring
- Responsive web UI
- Error logging and request tracing

## 28. Suggested Core Entities

- users
- leads
- lead_activities
- follow_ups
- parties
- party_territories
- states
- districts
- cities
- pincodes
- products
- product_categories
- pricing_tiers
- product_prices
- schemes
- scheme_rules
- orders
- order_items
- invoices
- invoice_items
- inventory_batches
- inventory_movements
- stock_reservations
- dispatches
- transporters
- payments
- payment_allocations
- notifications
- whatsapp_messages
- webhook_sources
- webhook_events
- audit_logs

## 29. API / Integration Requirements

- Auth
- Lead CRUD
- Lead Webhook
- Follow-up
- Party CRUD
- Territory/Pincode
- Products
- Pricing/Scheme calculation
- Cart/Orders
- Stock/FEFO allocation
- Billing/Invoice
- Dispatch/LR
- Payments/Outstanding
- Notifications
- WhatsApp
- Reports
- Audit
Territory, pricing, scheme and FEFO calculations must be enforced server-side; frontend checks are not the security/business-rule boundary.

## 30. Acceptance Criteria — New Requirements

| ID | Feature | Acceptance |
|---|---|---|
| AC-TR-01 | Territory Lock | Out-of-territory order is blocked before billing according to configured policy. |
| AC-TR-02 | Override | Admin override requires reason and audit entry. |
| AC-FEFO-01 | Batch Allocation | Eligible earliest-expiry batch is selected first. |
| AC-FEFO-02 | Expiry | Expired/quarantined/recalled stock cannot be allocated. |
| AC-EXP-01 | Near Expiry | Dashboard shows configurable expiry windows and stock value. |
| AC-PRICE-01 | Pricing | Current party/product/effective-date rate is selected automatically. |
| AC-SCH-01 | Scheme | Free quantity is calculated and shown separately. |
| AC-DSP-01 | Dispatch | LR/transporter update changes portal status. |
| AC-WA-01 | WhatsApp | Approved notification is queued/logged and delivery state stored. |
| AC-WH-01 | Webhook | Retrying the same external lead does not create duplicates. |
| AC-WH-02 | Alert | New webhook lead generates CRM notification for assigned Sales Team. |

## 31. QA Test Plan

- CRUD and permission tests
- Duplicate lead/party tests
- Territory allowed/blocked/override tests
- Unassigned pincode tests
- Pricing overlap/effective-date tests
- Scheme threshold/stacking tests
- Stock reservation/concurrency tests
- FEFO selection tests
- Expired/quarantined/recalled stock tests
- Near-expiry thresholds
- Order cancellation/stock release
- Payment/outstanding
- Dispatch/LR
- Webhook auth/dedup/retry/failure
- WhatsApp delivery/failure
- Audit completeness
- API authorization
- Large-list/inventory performance

## 32. BA Review & Recommended Improvements

- Use FEFO rather than plain FIFO for medicine batch allocation; CDSCO guidance supports first-expiry/first-out distribution controls. citeturn0search24
- Make territory assignments effective-dated and retain history.
- Use configurable policy for unassigned pincodes instead of hard-coded behavior.
- Do not put a guaranteed '40% conversion increase' into the product requirement; measure response time, contact rate and conversion as KPIs.
- Add configurable minimum remaining shelf-life before dispatch.
- Use server-side pricing/scheme calculations.
- Add stock reservation to prevent two orders consuming the same batch quantity.
- Treat webhook ingestion as a monitored integration subsystem with idempotency and retries.
- Treat WhatsApp as a provider integration with template/consent constraints and delivery tracking.
- Keep Distributor Portal as an external actor; add a separate internal role only if operational teams later need direct login.
- Show blocked-order reason and near-expiry stock value on Admin dashboard.

## 33. Release Plan

Phase 1: Auth → Admin/Sales Team → Dashboard → Leads → Follow-ups
         → Parties → Sales Team → Masters
Phase 2: Products → Pricing → Schemes → Orders → Payments → Reports
Phase 3: Territory Lock → Inventory/Batches → FEFO → Near-Expiry
         → Billing → Dispatch/LR
Phase 4: Distributor Portal → WhatsApp → Webhooks → Automation
Phase 5: QA → Security → Performance → UAT → Production

## 34. Open Business Decisions

- Can one Franchise Partner own multiple districts/pincodes?
- What happens when a pincode is unassigned?
- What minimum shelf-life must remain at dispatch?
- Is pricing party-specific, tier-specific or both?
- Can schemes stack? If yes, what is priority?
- Should credit limit hard-block, warn or require Admin approval?
- What invoice/GST format is required?
- Which official WhatsApp provider and templates will be used?
- Which B2B portals will provide official webhook/API access?
- How will Distributor Portal authenticate?
- Are separate Warehouse/Dispatch/Billing roles required in a later phase?

## 35. Definition of Ready

- Business objective clear
- Actor identified
- Fields defined
- Validation/business rules defined
- Permission impact defined
- DB/API/UI impact identified
- Acceptance criteria written
- Edge cases documented

## 36. Definition of Done

- Frontend complete
- Backend/API complete
- Database changes complete
- Server-side permissions complete
- Validation/error handling complete
- Audit complete
- Integrations tested
- QA passed
- Critical/high defects closed or accepted
- BA/UAT passed
- Documentation updated

## 37. Change-Control Rule

This FRS is the consolidated baseline. Any new feature, field, status, role, API, integration, pricing rule, territory rule, inventory rule or notification must be evaluated for UI, backend, database, permissions, audit, QA and UAT impact before becoming part of the baseline.

End of Pharma CRM Master FRS.

## 5. High Inquiry Drop-out Due to Slow Follow-ups

Problem: IndiaMART ya PharmaHopers jaise B2B portals se naye distributor inquiries regularly aa sakti hain. Manual Excel-based monitoring ke karan response delay, missed calls aur missed follow-ups ho sakte hain. Objective lead response time reduce karna aur timely nurturing enable karna hai.

Solution: Instant Webhook Integration & Automated Lead Nurturing

- B2B Lead Webhook Integration: IndiaMART, PharmaHopers ya supported B2B sources se incoming lead webhook ke through CRM me receive ki ja sake.
- Instant Lead Creation: Valid webhook receive hote hi lead automatically CRM me create ho aur source, external lead ID, received timestamp aur available contact/business details store hon.
- Duplicate Prevention: External lead ID/source combination ke basis par duplicate leads ko prevent/detect kiya jaye.
- Instant Sales Executive Alert: New lead create hone par applicable Sales Team member ko CRM popup/in-app notification diya jaye.
- Automated WhatsApp Nurturing: Configured workflow ke according new lead ko company ka interactive digital product catalog aur applicable price-list information WhatsApp par send ki ja sake.
- WhatsApp Compliance & Logging: WhatsApp communication official API/provider ke through ho; template, delivery/failure status aur timestamp CRM me log kiye jayein.
- Follow-up Automation: New lead ke liye first follow-up task/reminder automatically create kiya ja sake, with configurable response/follow-up SLA.
- Webhook Reliability: Webhook authentication/signature validation, retry mechanism, idempotency, failure logging aur monitoring support kiya jaye.
- Lead Response KPI: System lead received time se first sales response time calculate kare aur dashboard/report me show kare.

### 5.1 Business KPI / Measurement

- Target first-response time: configurable; recommended operational target ≤ 10 minutes.
- Lead-to-Contact Rate
- Lead-to-Qualified Rate
- Lead-to-Conversion Rate
- WhatsApp Catalog Delivery Rate
- Average First Response Time
- Missed/Overdue Follow-up Rate
Important BA Note: “Conversion rate 40% tak badh jata hai” ko guaranteed system outcome ke roop me specify nahi kiya gaya hai. 40% ko measurable business KPI/target ke roop me validate karna hoga using actual CRM data and baseline comparison.

### 5.2 Acceptance Criteria

- Supported B2B portal se valid webhook receive hone par lead CRM me automatically create ho.
- Lead source aur external lead ID preserve ho.
- Duplicate webhook receive hone par duplicate lead create na ho.
- Applicable Sales Team member ko instant in-app/popup notification mile.
- Configured WhatsApp workflow trigger ho aur catalog/price-list message status CRM me log ho.
- Webhook failure par retry/failure log available ho.
- New lead ke liye follow-up task/reminder automatically create ho according to configured SLA.
- Admin dashboard/report me first response time aur conversion-related KPIs available hon.

## Addendum — Version 2.2 Additions

This addendum extends the consolidated baseline. It is subject to the Change-Control Rule in Section 37. Sections 38 to 40 define new functionality. Section 41 lists the amendments this addendum makes to existing sections of this document. Sections 42 and 43 carry the new acceptance criteria and the new open business decisions.

## 38. Distributor Onboarding, Invitation and Registration

### 38.1 Objective

Enable a prospective Franchise Partner/Distributor to be onboarded through the CRM itself: an invitation is generated and shared from the CRM, the partner self-registers through a secure link, and the company Admin reviews and approves the registration. Portal access is granted only after Admin approval.

### 38.2 Actors and Authority

| Actor | Can generate/share invite | Can approve | Notes |
|---|---|---|---|
| Admin | Yes | Yes | Only Admin can approve or reject a registration. |
| Sales Team | Yes, for own/assigned leads | No | The Sales Team member in contact with the lead shares the invite link; approval authority stays with Admin. |
| Prospective Franchise Partner | No | No | Receives the link and submits the registration form without a login. |

### 38.3 Onboarding Flow

Lead / Prospect        ↓Invite Generated (Sales Team or Admin)        ↓Invite Shared (WhatsApp / Email / Copy Link)        ↓Partner Opens Secure Link        ↓Registration Form + KYC Documents Submitted        ↓Admin Review        ↓Approve → Party Created + Portal Access Granted → Welcome Notification        ↓Partner Login        ↓Reject → Reason Recorded → Notification → Optional Re-invite

### 38.4 Invite Generation Fields

- Prospect/firm name
- Contact person name
- Mobile/WhatsApp number
- Email
- Proposed State/District/City/Pincode
- Proposed Pricing Tier (Admin only, optional)
- Linked Lead reference (when generated from a Lead)
- Invite expiry date
- Internal notes
- Share channel: WhatsApp, Email or Copy Link
An invite generated from a Lead must retain the Lead reference so that the Lead can be converted on approval without losing history.

### 38.5 Invitation States

- Draft
- Sent
- Opened
- Submitted
- Under Review
- Approved
- Rejected
- Expired
- Revoked
- Resent
State transitions must be recorded with timestamp and actor.

### 38.6 Registration Form (Self-service, no login)

- Firm name
- Constitution type (Proprietorship/Partnership/Pvt Ltd/LLP)
- Contact person name and designation
- Mobile/WhatsApp
- Email
- Password creation
- GSTIN
- Drug licence number(s) and validity
- PAN
- Billing address
- Shipping address
- State/District/City/Area/Pincode
- Bank details (optional)
- Preferred product categories
- Expected monthly business (optional)
- Declaration and terms acceptance
Document uploads (configurable mandatory list): drug licence, GST certificate, PAN, cancelled cheque, partnership deed or incorporation certificate where applicable.

### 38.7 Admin Review and Approval

- Registration review queue with status, source, submitted date and assigned Sales Team filters
- Duplicate check against existing parties on GSTIN, mobile and email
- Territory availability check against active allocations, with conflict warning
- Document verification checklist per uploaded document
- Request-more-information action that returns the form to the applicant for correction
- On approval Admin sets: assigned territory (district/pincode with effective-from date), pricing tier, credit limit, payment terms, assigned Sales Team and opening outstanding
- Rejection requires a mandatory reason
On approval the system must create the Party record, create the Distributor Owner portal user, apply the territory and pricing configuration, convert the linked Lead to a Party while retaining its history, and send the welcome/credentials notification.

### 38.8 Business Rules

- Only Admin can approve or reject a registration; Sales Team can generate, share, resend and revoke invites only
- The invite token must be single-use, signed and expiring; default validity configurable (recommended 7 days)
- Resending an invite issues a new token and invalidates the previous one
- The registration link must work without authentication; submitted data must not grant any portal access before approval
- Duplicate GSTIN/mobile/email registration is restricted according to configured rules
- An invite generated from a Lead converts that Lead to a Party on approval and retains the full lead history
- Territory allocation is applied at approval; approval without territory must follow configured policy (block or warn)
- All invite, registration, review, approval and rejection actions are audited with actor, timestamp and old/new values
- A rejected registration retains its record and documents for audit; re-invitation is a new invite

### 38.9 Security Requirements

- Cryptographically signed, single-use, non-guessable invite token
- Token bound to the invited mobile/email
- OTP verification of mobile and/or email before submission (recommended)
- Rate limiting and bot protection on the public registration endpoint
- Uploaded KYC documents stored access-controlled and not publicly addressable; virus scanning applied
- Password policy enforced; forced change on first login when credentials are system generated
- Full audit trail with IP address and timestamp for public-facing actions

## 39. Distributor Portal — Extended Role Model and Scope

### 39.1 Portal Role Model

The Distributor Portal now carries two distinct roles. Both belong to the Distributor, not to the company.

| Portal Role | Created by | Scope |
|---|---|---|
| Distributor Owner (Franchise Partner) | System, on Admin approval of the registration | Full access to the distributor's own data: catalogue, applicable pricing, orders, order tracking, invoices, dispatch, outstanding, offers/schemes, profile — plus management of the distributor's own DCR team. |
| Distributor Team User (Field/DCR User) | Distributor Owner | Field-level access only: field customer master, tour/beat plan, DCR entry and POB, own DCR history and reports. No access to company masters, pricing configuration or any other distributor's data. |

The company Admin does not create, manage or approve Distributor Team Users. This is explicitly the Distributor Owner's responsibility.

### 39.2 Distributor Owner — Portal Capabilities

- Dashboard: order summary, outstanding, recent dispatches, payments due, own team DCR compliance
- Catalogue with applicable pricing for the assigned tier only
- Cart and order placement
- Order history and order status tracking timeline
- Invoice view and download
- Dispatch details with transporter, LR number and tracking link
- Outstanding: invoice-wise, ageing and payment history
- Applicable offers and schemes with validity period
- Profile and shipping address management
- Distributor team user management: create, edit, activate/deactivate, assign area/beat
- DCR review and approval for own team
- Own DCR reports
- Support/contact

### 39.3 Distributor Team User — Portal Capabilities

- Login with restricted portal view
- Field customer master for own customers, subject to Owner-configured approval
- Tour/beat plan view
- DCR entry and submission
- POB entry within DCR
- Own DCR history and personal reports
- No access to company pricing, masters, other distributors' data or company admin screens

### 39.4 Data Isolation Requirement

Each distributor is a separate data boundary. A Distributor Owner and its team users must only be able to read or write data belonging to that distributor. This must be enforced server-side on every request, not by the frontend.

## 40. DCR — Daily Call Report (Distributor-owned Module)

### 40.1 Ownership

DCR is owned and operated by the Distributor/Franchise Partner. The Distributor Owner creates the field team, and the team files daily call reports against that distributor. The company Admin does not manage a distributor's DCR team or its day-to-day entries. Whether the company receives aggregate read-only visibility of DCR data is an open business decision recorded in Section 43; the Phase 1 default is no company access.

### 40.2 Field Customer Master (under Distributor)

- Customer types: Doctor, Chemist/Retailer, Stockist, Hospital/Institution
- Name
- Type
- Specialty (for doctors)
- Clinic/firm name
- Mobile
- Email
- Address
- Area/Beat
- City
- Pincode
- Category (A/B/C)
- Preferred visit frequency
- Date of birth/anniversary (optional)
- Remarks
- Active flag
Actions: create, edit, view, activate/deactivate. Duplicate detection on mobile within the distributor scope.

### 40.3 Beat/Area and Tour Plan

- Beat/area master maintained under the distributor
- Monthly or weekly tour plan per team user mapping dates to beats/customers
- Plan versus actual comparison in reports
- Tour plan may be enabled or disabled per distributor by configuration

### 40.4 DCR Entry

- Report date (one DCR per field user per date)
- Field user (derived from login)
- Beat/area worked
- Work type: field work, leave, holiday, meeting or training
Each DCR contains one or more visit entries with the following fields:

- Field customer (from the distributor's field customer master)
- Customer type
- Visit time
- Visit purpose: call, follow-up, order collection, payment collection or sampling
- Products promoted (from the company catalogue, read-only)
- Samples/gifts issued with item and quantity
- POB — product, quantity and value
- Feedback/remark
- Next visit date
- Optional geo-location capture
- Optional photo/selfie
- Optional travel distance and expense
Day summary: total calls, doctors covered, chemists covered, stockists covered and total POB value.

### 40.5 DCR Workflow

Field User creates DCR → Submit → Distributor Owner reviews → Approve or Reject with remark        ↓Approved DCR is locked for editing        ↓Owner may reopen with a mandatory reason and audit entry

- DCR status: Draft, Submitted, Approved, Rejected
- Missed DCR is auto-flagged when nothing is submitted by the configured cut-off time
- Submission cut-off time and backdated-entry window are configurable per distributor

### 40.6 POB to Order Linkage

- POB captured within DCR is an intent record, not an order
- POB does not reserve stock and does not affect inventory
- The Distributor Owner can aggregate POB entries into a CRM order through a Convert POB to Order action
- The generated order retains a reference back to the source DCR entries

### 40.7 DCR Reports (Distributor Scope)

- Daily and monthly call summary per team user
- Average calls per day
- Coverage percentage: customers visited versus assigned
- Doctor-wise and chemist-wise visit history
- Product-wise promotion count
- POB summary by product, user and customer
- Missed DCR and compliance percentage
- Plan versus actual (when tour plan is enabled)
- Sample and gift issuance summary
- Expense summary (when enabled)

### 40.8 Business Rules

- One DCR per field user per date
- An approved DCR is immutable unless reopened by the Distributor Owner with a reason and audit entry
- Backdated DCR entry is permitted only within the configured window
- Field customers belong to the distributor and are not shared across distributors
- Products promoted are read from the company catalogue and cannot be edited by the distributor
- Samples and gifts are recorded as distributor issuance records; company inventory impact is out of Phase 1 scope
- All DCR submissions, approvals, rejections and reopenings are audited

### 40.9 Non-Functional Notes

- DCR entry is primarily a mobile activity; all DCR screens must be usable on small screens
- Offline DCR capture is out of Phase 1 scope and is recorded as an open decision
- DCR listing and reports must be paginated and indexed for daily-volume growth

## 41. Amendments to Existing Sections

### 41.1 Section 3 — Roles and Actors

Add the following actors:

| Actor | Type | Responsibilities |
|---|---|---|
| Distributor Owner (Franchise Partner) | External portal actor | Own orders, tracking, invoices, outstanding, offers; manages own DCR team and approves their DCRs. |
| Distributor Team User (Field/DCR User) | External portal actor, created by Distributor Owner | Field customer master, tour plan, DCR entry and POB within the distributor scope only. |

The existing "Franchise Partner / Distributor" actor is superseded by the Distributor Owner definition above.

### 41.2 Section 5 — Functional Modules

Add the following modules:

- Distributor Onboarding and Invitation
- Distributor Team (DCR User) Management
- DCR — Daily Call Report

### 41.3 Section 24 — Masters

Add the following masters:

- Field Customer Type
- Doctor Specialty
- Beat/Area
- Visit Purpose
- Sample/Gift Item
- Invite Template
- KYC Document Type
- Customer Category (A/B/C)

### 41.4 Section 25 — Permission Matrix

Add the following rows. The Distributor column of the existing matrix is now read as Distributor Owner, and a Distributor Team User column is added.

| Function | Admin | Sales Team | Distributor Owner | Distributor Team User |
|---|---|---|---|---|
| Invite generation | Full | Own/assigned leads | No | No |
| Invite revoke/resend | Full | Own generated invites | No | No |
| Registration review | Full | View status only | No | No |
| Registration approval/rejection | Full (exclusive) | No | No | No |
| Distributor team users | No | No | Full (own team) | No |
| Field customer master | No | No | Full (own) | Create/edit own, per configuration |
| Tour/beat plan | No | No | Full (own team) | View own |
| DCR entry | No | No | View own team | Create/submit own |
| DCR approval | No | No | Full (own team) | No |
| DCR reports | Aggregate read-only, only if enabled by decision D-1 | No | Full (own team) | Own only |
| POB to order conversion | No | No | Full (own) | No |
| Offers/Schemes | Full | Applicable view | Applicable view | No |

### 41.5 Section 28 — Core Entities

Add the following entities:

- distributor_invites
- distributor_registrations
- onboarding_documents
- distributor_users
- field_customers
- field_customer_categories
- beats
- tour_plans
- dcr_reports
- dcr_visits
- dcr_visit_products
- dcr_pob
- dcr_samples
- dcr_expenses

### 41.6 Section 29 — API / Integration Requirements

Add the following API groups:

- Invite: generate, resend, revoke, validate token
- Public registration: fetch invite context, submit registration, upload document, OTP request/verify
- Admin review: list, detail, request information, approve, reject
- Distributor user management: CRUD for the distributor's own team
- Field customer: CRUD within distributor scope
- Tour plan: CRUD and plan-versus-actual
- DCR: create, update draft, submit, approve, reject, reopen
- POB: list and convert to order
- DCR reports
Distributor data isolation, invite token validation, approval authority and DCR immutability must be enforced server-side.

### 41.7 Section 33 — Release Plan

Revised placement of the new scope:

- Phase 4a: Distributor Onboarding and Invitation, Admin approval workflow, Distributor Portal access provisioning
- Phase 4b: Distributor Portal core (catalogue, orders, tracking, invoices, outstanding, offers)
- Phase 4c: Distributor team management and DCR (field customer master, DCR entry, approval, reports)
- Phase 5: unchanged — QA, security, performance, UAT and production
Onboarding must precede the portal, because portal access is created by the approval step.

## 42. Acceptance Criteria — Onboarding and DCR

| ID | Feature | Acceptance |
|---|---|---|
| AC-INV-01 | Invite generation | A Sales Team member can generate and share an invite for an own/assigned lead; the lead reference is stored on the invite. |
| AC-INV-02 | Invite authority | A Sales Team member cannot approve a registration; the approval action is available to Admin only. |
| AC-INV-03 | Token security | An invite link is single-use and expires; a used, expired or revoked link cannot open the registration form. |
| AC-INV-04 | Resend | Resending an invite invalidates the previous token. |
| AC-REG-01 | Self registration | A prospective partner can open the link without logging in and submit the registration form with the configured mandatory KYC documents. |
| AC-REG-02 | No premature access | A submitted but unapproved registration grants no portal access. |
| AC-REG-03 | Duplicate check | A registration whose GSTIN or mobile already exists as a party is flagged to the Admin before approval. |
| AC-APR-01 | Approval effect | On approval the system creates the party, creates the Distributor Owner portal user, applies territory, pricing tier, credit limit and payment terms, and sends the welcome notification. |
| AC-APR-02 | Lead conversion | When the invite originated from a lead, approval converts that lead to a party and retains its history. |
| AC-APR-03 | Rejection | Rejection requires a reason, records it, notifies the applicant and leaves an audit entry. |
| AC-DST-01 | Team creation | A Distributor Owner can create, edit and deactivate its own team users; the company Admin cannot. |
| AC-DST-02 | Isolation | A distributor and its team users cannot read or write any other distributor's data. |
| AC-DCR-01 | DCR entry | A Distributor Team User can file one DCR per date with multiple visit entries, products promoted and POB. |
| AC-DCR-02 | Approval | A submitted DCR can be approved or rejected with remark by the Distributor Owner; an approved DCR cannot be edited unless reopened with a reason. |
| AC-DCR-03 | Missed DCR | A DCR not submitted by the configured cut-off is flagged as missed and appears in the compliance report. |
| AC-DCR-04 | POB conversion | POB entries can be aggregated into a CRM order that references the source DCR entries. |
| AC-DCR-05 | Catalogue read-only | Products promoted are selected from the company catalogue and cannot be created or edited by the distributor. |

## 43. New Open Business Decisions

| ID | Decision required |
|---|---|
| D-1 | Does the company Admin receive read-only or aggregate visibility of distributor DCR data? Default assumed in this addendum: no company access. |
| D-2 | What is the invite link validity period? |
| D-3 | Is mobile and/or email OTP verification mandatory before registration submission? |
| D-4 | Which KYC documents are mandatory versus optional? |
| D-5 | Can a distributor be approved without a territory allocation, or is territory mandatory at approval? |
| D-6 | Is there a limit on the number of team users a distributor may create? |
| D-7 | Can a Distributor Team User place orders, or is the team restricted to POB only? |
| D-8 | Is geo-location capture mandatory for each DCR visit entry? |
| D-9 | What backdated-entry window is allowed for DCR? |
| D-10 | What is the daily DCR submission cut-off time? |
| D-11 | Is offline DCR capture required, and in which phase? |
| D-12 | Are samples and gifts deducted from company inventory, or tracked only as a distributor record? |
| D-13 | Can the same field customer be recorded by two distributors operating in the same area? |
| D-14 | Is a TA/DA expense claim workflow in scope for DCR? |
| D-15 | Who supports a distributor team user who cannot log in — company Admin or Distributor Owner? |

End of Version 2.2 Addendum.

## Addendum — Version 2.3 Additions

This addendum introduces configurable internal roles and permissions. It supersedes the fixed two-role internal model (Admin and Sales Team) used in Sections 3 and 25, and it answers the open decision in Section 34 regarding separate Warehouse, Dispatch and Billing roles: such roles are not hard-coded, they are created by the Admin as required.

## 44. Internal Role and Permission Management

### 44.1 Objective

Allow the company Admin to define internal roles beyond Sales Team — for example Order/Dispatch Team, Accounts Team, Inventory Team or Manager — and to control exactly what each role can see and do in the CRM. Menus, screens, actions and data visibility are all derived from the role assigned to the logged-in user.

Scope note: this section covers internal company users only. Distributor Owner and Distributor Team User remain external portal roles as defined in Section 39 and are not created through this module.

### 44.2 Concepts

| Concept | Definition |
|---|---|
| Permission | A single module-and-action pair, for example "Orders: Create" or "Invoice: Cancel". Permissions are defined by the system and cannot be created by users. |
| Role | A named, Admin-created set of permissions plus a data scope, for example "Dispatch Team". |
| System Role | A role shipped with the product that cannot be deleted. Admin is a system role and always holds all permissions. |
| Custom Role | Any role created by the Admin. Fully editable and deletable when no user is assigned to it. |
| Data Scope | The breadth of records a role may act on, independent of its permissions. See 44.4. |
| Reporting Hierarchy | The reports-to relationship between internal users, which the Team data scope depends on. |

### 44.3 Permission Catalogue

Permissions are defined per module. Only the actions listed for a module are grantable for that module.

| Module | Grantable Actions |
|---|---|
| Dashboard | View |
| Leads | View, Create, Edit, Archive, Assign, Convert, Export |
| Follow-ups | View, Create, Edit, Complete, Reschedule, Export |
| Parties | View, Create, Edit, Archive, Activate/Deactivate, Export |
| Territory | View, Allocate, Edit, Override, Export |
| Products | View, Create, Edit, Archive, Activate/Deactivate, Export |
| Pricing | View, Create, Edit, Price Override, Export |
| Schemes | View, Create, Edit, Deactivate |
| Orders | View, Create, Edit Draft, Submit, Cancel, Print, Export |
| Inventory and Batches | View, Create, Edit, Adjust, Transfer, Manual Batch Override, Export |
| Near-Expiry | View, Export |
| Billing/Invoice | View, Create, Cancel, Print, Export |
| Dispatch | View, Create, Edit, Update LR/Tracking, Export |
| Payments | View, Create, Edit, Delete, Allocate, Export |
| Distributor Onboarding | View, Generate Invite, Resend/Revoke Invite, Review, Approve, Reject |
| Internal Users | View, Create, Edit, Activate/Deactivate, Reset Password |
| Roles and Permissions | View, Create, Edit, Delete, Assign to User |
| Masters | View, Create, Edit, Activate/Deactivate |
| Reports | View, Export, Print (grantable per report group) |
| Audit Logs | View, Export |
| Notifications and WhatsApp | View, Send/Retry, Manage Templates |
| Webhooks | View, Configure, Retry Failed |

### 44.4 Data Scope Model

A permission states what a role may do. A data scope states which records it may do it to. Both are required; granting "Leads: View" without a scope is not meaningful.

| Scope | Meaning | Typical use |
|---|---|---|
| All | Every record in the company | Admin, Accounts, Dispatch, Auditor |
| Territory | Records belonging to the territories assigned to the user | Regional roles |
| Team | The user's own records plus those of users reporting to them | Manager |
| Own | Only records the user created or is assigned to | Sales Team |
| None | No access to this module | Any role where the module is not applicable |

Data scope is configured per role. A per-module scope override may be configured where a role needs different breadth in different modules, for example a Manager with Team scope on Leads but All scope on Reports.

### 44.5 Predefined Role Templates

The system ships the following templates. Admin may use them as-is, clone and modify them, or create roles from scratch. Templates are a starting point, not a fixed list.

| Role Template | Typical access | Default scope |
|---|---|---|
| Admin (system role) | All modules and all actions, including role management and all overrides | All |
| Manager | View and report across the team; approve where configured; no master, pricing or role changes | Team |
| Sales Team | Leads, follow-ups, parties, eligible orders and payments | Own |
| Order/Dispatch Team | Confirmed orders (view), inventory and batches, batch allocation, dispatch, transporter and LR updates; no leads, pricing or billing | All |
| Accounts Team | Invoices, payments, outstanding, credit information, financial reports; view orders and parties; no inventory or pricing edits | All |
| Inventory Team | Batches, stock movements, adjustments, transfers, near-expiry; no billing, pricing or leads | All |
| Auditor / Read-only | View and export across modules; no create, edit, delete or override anywhere | All |

### 44.6 Role Management Actions

- Create role: name, description, permission selection, data scope, active flag
- Clone role from an existing role or template
- Edit role permissions and scope
- Activate/deactivate role
- Delete role, permitted only when no user is assigned
- View role detail with assigned user count
- View permission matrix across all roles in a single grid

### 44.7 Internal User and Hierarchy Management

- Create/edit internal user: name, employee code, email, mobile, role, reporting manager, assigned territory/area, active flag
- Assign or change a user's role; the change is audited
- Reporting manager field establishes the hierarchy that the Team data scope uses
- Activate/deactivate user; deactivation revokes access immediately and ends active sessions
- Reset password
- Record ownership is retained when a user is deactivated; reassignment of open records is an explicit action
Section 21 (Sales Team Management) is superseded by this module, which manages all internal users rather than Sales Team alone.

### 44.8 Access Enforcement

- Navigation menu is generated from the logged-in user's effective permissions; modules without View permission are not rendered
- Route access is guarded; direct URL entry to a module without permission returns Access Denied
- Action-level controls (buttons, row actions, bulk actions) are hidden when the permission is absent, not merely disabled
- Every API request is authorized server-side against the user's role; the frontend is not the enforcement boundary
- List and detail queries are filtered by the role's data scope server-side
- Reports and exports respect the same scope as the underlying module
- Permission changes take effect on the user's next request or next login; the chosen refresh behaviour must be stated in the technical design

### 44.9 Business Rules

- A permission cannot exist without a data scope for that module
- The Admin system role cannot be edited, deactivated or deleted
- At least one active user holding the Roles and Permissions: Edit permission must exist at all times; the last such user cannot be deactivated or demoted
- A user cannot grant a permission they do not themselves hold
- A user cannot modify the permissions of their own role
- A role with assigned users cannot be deleted; users must be reassigned first
- Deactivating a role blocks login for its users until a new role is assigned
- Ownership-based rules defined elsewhere in this document are expressed through the Own data scope, not hard-coded per role
- All role creation, permission changes, scope changes and user-role assignments are audited with old and new values
- Sensitive permissions are marked in the UI and require explicit confirmation when granted: Territory Override, Price Override, Invoice Cancel, Payment Delete, Manual Batch Override, Distributor Registration Approval, Roles and Permissions Edit, Audit Log access

### 44.10 Security Safeguards

- Privilege escalation prevention: permission grants are validated server-side against the granting user's own permissions
- Lockout prevention: the system refuses any change that would leave no active user able to manage roles
- Session invalidation on user deactivation or role deactivation
- Role and permission changes are high-severity audit events and must be retained
- Sensitive permission grants trigger a notification to other Admin users

## 45. Amendments to Existing Sections (v2.3)

### 45.1 Section 3 — Roles and Actors

The internal actor list is replaced by the following model:

| Actor | Type | Notes |
|---|---|---|
| Admin | Internal system role | Full access including role and permission management. Cannot be deleted. |
| Configurable internal roles | Internal, created by Admin | Any number of roles such as Manager, Order/Dispatch Team, Accounts Team, Inventory Team, Auditor. Access is defined by permissions and data scope, not by a fixed list in this document. |
| Distributor Owner | External portal actor | Unchanged, per Section 39. |
| Distributor Team User | External portal actor | Unchanged, per Section 39. |

The earlier note stating that Billing, Warehouse and Dispatch are operational functions under Admin in Phase 1 is withdrawn. These are now configurable roles.

### 45.2 Section 5 — Functional Modules

Add: Role and Permission Management. Rename Sales Team Management to Internal User Management.

### 45.3 Section 21 — Sales Team Management

Superseded by Section 44.7. The module manages all internal users and their roles, not Sales Team alone. The ownership and territory behaviour previously described for Sales Team is now expressed as the Own data scope.

### 45.4 Section 25 — Permission Matrix

The existing matrix is retained as the default configuration of the shipped role templates, not as a fixed permission model. The authoritative model is the permission catalogue in Section 44.3 combined with the data scope model in Section 44.4. Where this document elsewhere states that an action is restricted to Admin, that restriction is implemented as a permission that is granted to the Admin role by default and may be granted to other roles by the Admin, except where Section 44.9 marks it as non-transferable.

### 45.5 Section 24 — Masters

Add: Designation, Department. Role is not a master; it is managed through Section 44.

### 45.6 Section 28 — Core Entities

- roles
- permissions
- role_permissions
- role_data_scopes
- user_roles
- user_hierarchy
- permission_modules
The users entity gains: employee code, reporting manager reference, department, designation and role reference.

### 45.7 Section 29 — API / Integration Requirements

- Role CRUD and clone
- Permission catalogue fetch
- Role permission and scope assignment
- User role assignment
- Effective permissions of the current session user
- Internal user CRUD, activate/deactivate and password reset
- Reporting hierarchy fetch
The effective-permissions endpoint is what the frontend uses to build navigation and action visibility. It is a convenience for the interface only; every business API must independently authorize the request.

### 45.8 Section 33 — Release Plan

Role and Permission Management moves into Phase 1, immediately after authentication and before the module build-out, because every subsequent module depends on it for menu, route, action and data-scope behaviour. Building modules first and retrofitting roles afterwards requires every screen to be revisited.

### 45.9 Section 34 — Open Business Decisions

The decision "Are separate Warehouse/Dispatch/Billing roles required in a later phase?" is resolved: such roles are supported from Phase 1 as configurable roles rather than hard-coded actors.

## 46. Acceptance Criteria — Roles and Permissions

| ID | Feature | Acceptance |
|---|---|---|
| AC-ROL-01 | Role creation | Admin can create a role, select permissions per module, set a data scope and save it. |
| AC-ROL-02 | Clone | Admin can clone a role template and modify it without affecting the original. |
| AC-ROL-03 | Menu derivation | A user assigned a role sees only the modules for which the role holds View permission. |
| AC-ROL-04 | Route guard | Direct URL access to a module the role cannot view returns Access Denied. |
| AC-ROL-05 | Action visibility | Buttons and row actions for permissions the role does not hold are not rendered. |
| AC-ROL-06 | Server authorization | An API request for an action the role does not hold is rejected server-side even when the request bypasses the interface. |
| AC-ROL-07 | Data scope Own | A user with Own scope on Leads cannot read or modify another user's lead. |
| AC-ROL-08 | Data scope Team | A manager with Team scope sees their own records and those of their reportees, and no others. |
| AC-ROL-09 | Dispatch role | A user with the Dispatch role can update dispatch, transporter and LR details but cannot view pricing or leads. |
| AC-ROL-10 | Accounts role | A user with the Accounts role can record payments and view outstanding but cannot edit inventory or product pricing. |
| AC-ROL-11 | Escalation | A user cannot grant a permission they do not themselves hold. |
| AC-ROL-12 | Self-role | A user cannot modify the permissions of the role assigned to them. |
| AC-ROL-13 | Lockout prevention | The system refuses any change that would leave no active user able to manage roles. |
| AC-ROL-14 | Role deletion | A role with assigned users cannot be deleted until those users are reassigned. |
| AC-ROL-15 | Deactivation | Deactivating a user ends their access immediately and retains their historical record ownership. |
| AC-ROL-16 | Audit | Every role creation, permission change, scope change and user-role assignment is recorded in the audit log with old and new values. |

## 47. New Open Business Decisions (v2.3)

| ID | Decision required |
|---|---|
| D-16 | Can a user hold more than one role at a time, or exactly one? Default assumed in this addendum: one role per user. |
| D-17 | Is a per-module data scope override required in Phase 1, or is a single scope per role sufficient? |
| D-18 | Which roles, if any, may approve a distributor registration besides Admin? |
| D-19 | Should approval workflows (order, invoice cancellation, price override) route to a Manager role, or remain Admin-only? |
| D-20 | Do permission changes apply immediately to logged-in users, or from their next login? |
| D-21 | Is a maker-checker (four-eyes) approval required for sensitive permission grants? |
| D-22 | Should the Dispatch role see order values and pricing, or quantities only? |
| D-23 | Does the Accounts role require access to party credit limit changes, or view only? |
| D-24 | Is territory-based scope required for internal roles in Phase 1, or only Own/Team/All? |
| D-25 | When a user is deactivated, are their open leads and follow-ups auto-reassigned or left for manual reassignment? |

End of Version 2.3 Addendum.
