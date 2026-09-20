# Pharma CRM - End-to-End System Architecture & Workflows

## 1. System Overview & Core Actors

```mermaid
flowchart TD
    subgraph Users ["Actors & Surfaces"]
        SA["Super Admin<br/>(crm-super / Platform Scope)"]
        FA["Franchise Admin<br/>(crm-admin / Franchise Scope)"]
        SR["Sales Representative<br/>(crm-sales / Field Scope)"]
        DP["Distributor Partner<br/>(crm-portal / Party Scope)"]
    end

    subgraph Gateway ["Authentication & Middleware Gateway"]
        AUTH["/api/v1/oauth/token<br/>JWT HS256 Issuance"]
        SEC["RateLimiter & BearerAuth Middleware"]
        TEN["TenantContext Middleware<br/>(Auto SignInAs & Scope Injection)"]
    end

    subgraph CoreModules ["Business Logic & Domain Layer"]
        MOD_SUPER["Platform Management<br/>(Orgs, Franchises, Global Audit)"]
        MOD_CATALOG["Catalog & Pricing<br/>(Products, Tiers, Schemes, Matrix)"]
        MOD_CRM["CRM & Lead Funnel<br/>(Leads, FollowUps, Conversion)"]
        MOD_ORDERS["Order Management System<br/>(Cart, Orders, Approval, Cancel)"]
        MOD_INVENTORY["Inventory & Batches<br/>(Batches, Expiry, Adjustments)"]
        MOD_BILLING["Billing & Fulfillment<br/>(Tax Invoices, Dispatches, Payments)"]
        MOD_PORTAL["Distributor Self-Service<br/>(Catalogue, Cart, Orders, Ledger)"]
    end

    subgraph DataStore ["Database & Persistence (MariaDB)"]
        DB[(MariaDB Multi-Tenant Schema)]
    end

    SA --> AUTH
    FA --> AUTH
    SR --> AUTH
    DP --> AUTH

    AUTH --> SEC --> TEN

    TEN -->|SUPER_ADMIN| MOD_SUPER
    TEN -->|SUPER_ADMIN & FRANCHISE_ADMIN| MOD_CATALOG
    TEN -->|FRANCHISE_ADMIN & SALES| MOD_CRM
    TEN -->|ALL ROLES| MOD_ORDERS
    TEN -->|FRANCHISE_ADMIN| MOD_INVENTORY
    TEN -->|FRANCHISE_ADMIN| MOD_BILLING
    TEN -->|DISTRIBUTOR & SUPER_ADMIN| MOD_PORTAL

    MOD_SUPER --> DB
    MOD_CATALOG --> DB
    MOD_CRM --> DB
    MOD_ORDERS --> DB
    MOD_INVENTORY --> DB
    MOD_BILLING --> DB
    MOD_PORTAL --> DB
```

---

## 2. Global Role-Based Access Control (RBAC) Matrix

| Module / Resource | Super Admin | Franchise Admin | Sales Rep | Distributor Partner |
| :--- | :---: | :---: | :---: | :---: |
| **Organizations & Global Franchises** | Full (CRUD) | None (403) | None (403) | None (403) |
| **Global Security Audit Logs** | Full (Read) | None (403) | None (403) | None (403) |
| **Franchise User Management** | Read / SignInAs | Full (CRUD) | None (403) | None (403) |
| **Products & Categories Master** | Full / SignInAs | Full (CRUD) | Read Only | Read Only (Portal) |
| **Pricing Tiers & Custom Schemes** | Full / SignInAs | Full (CRUD) | Read Only | Read (Resolved Price) |
| **Sales Leads & Conversions** | Full / SignInAs | Full (All Leads) | Scoped (Assigned Only) | None (403) |
| **Follow-Ups & Call Logs** | Full / SignInAs | Full (All Reps) | Scoped (Self Only) | None (403) |
| **Distributor Parties Master** | Full / SignInAs | Full (CRUD) | Scoped (Territory) | Read Own Profile |
| **Territory Rules & Overrides** | Full / SignInAs | Full (CRUD) | Validate Only | None (403) |
| **Inventory & Batch Tracking** | Full / SignInAs | Full (Receive/Adjust) | Read Near-Expiry | None (403) |
| **Order Placement & Management** | Full / SignInAs | Full (Approve/Cancel) | Field Booking | Self-Service Order |
| **Tax Invoices & Billing** | Full / SignInAs | Full (Post/Cancel) | View Invoices | View & Download Own |
| **Dispatches & LR Tracking** | Full / SignInAs | Full (Create/Deliver) | View Dispatches | Track Own Shipments |
| **Payment Collection & Allocation** | Full / SignInAs | Full (Record/Allocate) | View Collections | View Ledger Balance |
| **BI Reports & CSV Exports** | Full / SignInAs | Full (Franchise Level) | Scoped Performance | None (403) |

---

## 3. End-to-End Enterprise Workflows Directory

1. [Super Admin Workflow Graph](file:///e:/Projects/PHP/crm/docs/workflows/01_super_admin_workflow.md)
2. [Franchise Admin Workflow Graph](file:///e:/Projects/PHP/crm/docs/workflows/02_franchise_admin_workflow.md)
3. [Sales Representative Workflow Graph](file:///e:/Projects/PHP/crm/docs/workflows/03_sales_rep_workflow.md)
4. [Distributor Partner Portal Workflow Graph](file:///e:/Projects/PHP/crm/docs/workflows/04_distributor_portal_workflow.md)
5. [Complete Order-to-Cash & Fulfillment Lifecycle Graph](file:///e:/Projects/PHP/crm/docs/workflows/05_order_to_cash_lifecycle.md)
