# Sales Representative - Field Operations & Scoped Workflows

## 1. Actor Profile
* **Client Surface**: `crm-sales`
* **OAuth Role**: `SALES`
* **Access Scope**: `FRANCHISE` (Strictly scoped to their **own assigned records** via SQL policy isolation)
* **Core Responsibilities**: Lead acquisition, doctor/chemist visits, follow-up scheduling, field order booking, and territory compliance.

---

## 2. Sales Rep Operations Flow Graph

```mermaid
flowchart TD
    LoginSales(["Sales Rep Login (/api/v1/oauth/token with crm-sales)"]) --> IdentityVerify["Verify Scope & User Profile<br/>(GET /api/v1/auth/me)"]

    IdentityVerify --> DailyAgenda{"Execute Daily Tasks?"}

    DailyAgenda -->|Leads & Prospects| LeadPipeline["Lead Engagement<br/>• View Assigned: GET /api/v1/admin/leads<br/>• Create Field Lead: POST /api/v1/admin/leads<br/>• Update Stage: POST .../status"]

    DailyAgenda -->|Doctor / Chemist Visits| FollowUps["Follow-Up & Visit Execution<br/>• View Today's Schedule: GET .../admin/follow-ups<br/>• Log Visit Notes: POST .../{ref}/complete<br/>• Reschedule: POST .../{ref}/reschedule"]

    DailyAgenda -->|Field Booking| OrderTaking["Order Placement & Parties<br/>• View Territory Chemist: GET .../admin/parties<br/>• Validate Pin & Area: POST .../territories/validate<br/>• Book Sales Order: POST .../admin/orders<br/>• Check History: GET .../admin/orders"]

    LeadPipeline --> SummaryReport
    FollowUps --> SummaryReport
    OrderTaking --> SummaryReport

    SummaryReport["Review Personal Performance & Commissions<br/>(GET /api/v1/admin/reports/sales)"] --> EndSales([End Field Tour])
```

---

## 3. Territory Enforcement & Field Booking Sequence

```mermaid
sequenceDiagram
    autonumber
    actor SR as Sales Representative
    participant Gateway as API Gateway
    participant LeadPolicy as SalesLeadPolicy / Middleware
    participant TerritoryService as Territory Validator
    participant OrderService as Order Domain
    participant DB as MariaDB

    SR->>Gateway: GET /api/v1/admin/leads (Bearer JWT)
    Gateway->>LeadPolicy: Check user role (SALES)
    LeadPolicy->>DB: SELECT * FROM sales_leads WHERE franchise_ref = ? AND assigned_user_ref = ?
    Note over LeadPolicy,DB: Automated row-level scoping applies
    DB-->>LeadPolicy: Filtered Leads
    LeadPolicy-->>SR: 200 OK (Only assigned leads returned)

    SR->>Gateway: POST /api/v1/admin/territories/validate (pincode=400053, party_ref=PAR-GUPTA...)
    Gateway->>TerritoryService: Validate boundary conflict
    TerritoryService-->>SR: 200 OK (Status: OK / No Exclusive Violation)

    SR->>Gateway: POST /api/v1/admin/orders (Field Order Booking)
    Gateway->>OrderService: Create Order (channel=SALES, salesUserRef=USR-SALESREP...)
    OrderService->>DB: INSERT INTO orders (status=SUBMITTED)
    DB-->>OrderService: Created (order_ref)
    OrderService-->>SR: 201 Created (Order submitted for Franchise Admin review)
```

---

## 4. Sales Rep Endpoints Reference

| Operation | Method | Endpoint | Scoping Constraint |
| :--- | :---: | :--- | :--- |
| **Profile** | `GET` | `/api/v1/auth/me` | Returns role `SALES` and franchise identifier |
| **List Leads** | `GET` | `/api/v1/admin/leads` | Automatically scoped to `assigned_user_ref` |
| **Create Lead** | `POST` | `/api/v1/admin/leads` | Auto-stamped with creator and assigned to self |
| **Follow-Ups** | `GET` | `/api/v1/admin/follow-ups` | Only returns visits assigned to the sales rep |
| **Complete Visit** | `POST` | `/api/v1/admin/follow-ups/{ref}/complete` | Records call notes, doctor samples handed over |
| **Parties** | `GET` | `/api/v1/admin/parties` | Displays assigned doctors, stockists, chemists |
| **Territory Check**| `POST` | `/api/v1/admin/territories/validate` | Pre-checks pincode availability before booking |
| **Book Order** | `POST` | `/api/v1/admin/orders` | Books order with `channel=SALES` |
| **View Orders** | `GET` | `/api/v1/admin/orders` | Scoped to orders booked by the sales rep |
