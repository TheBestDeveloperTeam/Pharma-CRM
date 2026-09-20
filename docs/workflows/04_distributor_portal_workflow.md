# Distributor Partner - Portal Self-Service Workflow

## 1. Actor Profile
* **Client Surface**: `crm-portal`
* **OAuth Role**: `DISTRIBUTOR`
* **Access Scope**: `DISTRIBUTOR` / `PARTY` (Strict isolation to the specific partner account `party_ref`)
* **Core Responsibilities**: Browse real-time catalogue, tier-specific pricing, placing restocking orders, tracking shipments, reviewing invoices, and monitoring credit limit and outstanding ledger.

---

## 2. Distributor Portal Operations Flow Graph

```mermaid
flowchart TD
    LoginPortal(["Distributor Login (/api/v1/oauth/token with crm-portal)"]) --> PortalHome["Distributor Home Dashboard"]

    PortalHome --> ViewProfile["View & Update Profile<br/>• GET /api/v1/portal/profile<br/>• PATCH /api/v1/portal/profile (Shipping Address/Mobile)"]

    PortalHome --> BrowseProducts["Product Catalogue & Tier Pricing<br/>• GET /api/v1/portal/catalogue<br/>(Dynamic Tier Pricing & Schemes Applied)"]

    BrowseProducts --> CartCalculation["Cart Scheme & Tax Calculation<br/>• POST /api/v1/portal/cart/calculate<br/>(Calculates Slab Discounts, GST, Free Goods)"]

    CartCalculation --> OrderPlacement["Place Self-Service Order<br/>• POST /api/v1/portal/orders<br/>• Track Order: GET /api/v1/portal/orders<br/>• Cancel Draft: POST .../{ref}/cancel"]

    PortalHome --> FulfillmentTracking["Shipment & Dispatches Tracking<br/>• GET /api/v1/portal/dispatches<br/>(LR Number, Transporter, Status)"]

    PortalHome --> InvoicesAndLedger["Billing & Ledger Balance<br/>• Tax Invoices: GET /api/v1/portal/invoices<br/>• Outstanding Balance: GET /api/v1/portal/outstanding<br/>(Credit Limit, Invoiced, Payments, Balance)"]

    OrderPlacement --> InvoicesAndLedger
    FulfillmentTracking --> InvoicesAndLedger
    InvoicesAndLedger --> EndDistributor([Distributor Session Complete])
```

---

## 3. Dynamic Pricing Resolution & Cart Sequence

```mermaid
sequenceDiagram
    autonumber
    actor DP as Distributor Partner
    participant PortalCtrl as Portal Controller
    participant PriceResolver as Price Resolver Engine
    participant SchemeCalc as Scheme Calculator
    participant OrderService as Order Domain
    participant DB as MariaDB

    DP->>PortalCtrl: GET /api/v1/portal/catalogue
    PortalCtrl->>PriceResolver: Resolve price for (franchiseRef, productRef, tierRef)
    PriceResolver->>DB: Check custom contract price -> tier price -> default rate
    DB-->>PriceResolver: Returns Resolved Rate (e.g. 27.50, rate_source=TIER)
    PriceResolver-->>PortalCtrl: Price Matrix
    PortalCtrl-->>DP: 200 OK (Catalogue with personalized rates)

    DP->>PortalCtrl: POST /api/v1/portal/cart/calculate (SKU: AMOX-250, Qty: 50)
    PortalCtrl->>SchemeCalc: Calculate active volume schemes (e.g. 10+1 free)
    SchemeCalc-->>PortalCtrl: Free Units: 5, GST: 12%, Subtotal: 1375.00
    PortalCtrl-->>DP: 200 OK (Validated Cart Breakdown)

    DP->>PortalCtrl: POST /api/v1/portal/orders (client_order_ref=PO-2026-OCT-01)
    PortalCtrl->>OrderService: Create Order (channel=PORTAL, partyRef=PAR-APOLLODIST...)
    OrderService->>DB: INSERT INTO orders & order_items
    DB-->>OrderService: Created (ORD-APOLLO...)
    OrderService-->>DP: 201 Created (Order Confirmed)
```

---

## 4. Distributor Portal Endpoints Reference

| Purpose | Method | Endpoint | Description |
| :--- | :---: | :--- | :--- |
| **Profile** | `GET` | `/api/v1/portal/profile` | Firm details, GSTIN, drug license, credit limit |
| **Update Info** | `PATCH` | `/api/v1/portal/profile` | Update delivery address, contact person, mobile |
| **Catalogue** | `GET` | `/api/v1/portal/catalogue` | Products with active tier pricing and GST rates |
| **Cart Calc** | `POST` | `/api/v1/portal/cart/calculate` | Real-time scheme and tax breakdown before booking |
| **List Orders**| `GET` | `/api/v1/portal/orders` | Historical orders and current fulfillment stages |
| **Place Order**| `POST` | `/api/v1/portal/orders` | Place restocking purchase order directly |
| **Order Details**| `GET` | `/api/v1/portal/orders/{ref}` | View ordered items, free units, and totals |
| **Cancel Draft**| `POST` | `/api/v1/portal/orders/{ref}/cancel` | Cancel order before confirmation by franchise |
| **Invoices** | `GET` | `/api/v1/portal/invoices` | Download and inspect official tax invoices |
| **Dispatches**| `GET` | `/api/v1/portal/dispatches` | Track LR number, vehicle, courier, and delivery status |
| **Ledger** | `GET` | `/api/v1/portal/outstanding` | Real-time opening, invoiced, paid, and current balance |
