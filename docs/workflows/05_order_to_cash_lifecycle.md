# Complete Order-to-Cash & Fulfillment Lifecycle

## 1. Lifecycle Overview

The commercial lifecycle in the Pharma PCD CRM bridges Sales, Orders, Batch Inventory, Tax Invoicing, Logistics Fulfillment, and Payment Reconciliation.

```mermaid
flowchart TD
    subgraph OrderCapture ["Stage 1: Order Placement & Ingestion"]
        O1["Portal Booking<br/>(Distributor Self-Service)"]
        O2["Field Booking<br/>(Sales Representative)"]
        O3["Admin Direct Booking<br/>(Phone / Email Order)"]
    end

    subgraph Validation ["Stage 2: Validation & Commercial Checks"]
        V1["Territory Exclusivity Check<br/>(/territories/validate)"]
        V2["Credit Limit & Outstanding Verification<br/>(party.credit_limit vs outstanding)"]
        V3["Stock Availability Check<br/>(inventory_batches.available_qty)"]
    end

    subgraph Approval ["Stage 3: Franchise Review & Stock Reservation"]
        A1["Franchise Admin Confirmation<br/>(POST /api/v1/admin/orders/{ref}/confirm)"]
        A2["Status -> CONFIRMED<br/>Stock reserved in FIFO order"]
    end

    subgraph Invoicing ["Stage 4: Tax Invoicing & GST Accounting"]
        I1["Generate Tax Invoice<br/>(POST /api/v1/admin/invoices/generate)"]
        I2["Calculate GST Breakdown<br/>(CGST/SGST for intra-state or IGST for inter-state)"]
        I3["Generate Sequential Invoice No<br/>(e.g. INV-MUM-2026-0001)"]
    end

    subgraph Logistics ["Stage 5: Warehouse Dispatch & Fulfillment"]
        D1["Create Dispatch Entry<br/>(POST /api/v1/admin/dispatches)"]
        D2["Assign Transporter & LR Number<br/>(e.g. V-Trans, LR-987654)"]
        D3["Confirm Delivery<br/>(POST /api/v1/admin/dispatches/{ref}/deliver)"]
    end

    subgraph Reconciliation ["Stage 6: Payment & Ledger Settlement"]
        P1["Record Customer Payment<br/>(POST /api/v1/admin/payments)"]
        P2["Auto-Allocate to Invoices (FIFO)<br/>(Reduces invoice.balance_due)"]
        P3["Update Party Ledger Summary<br/>(Decreases current_outstanding)"]
    end

    O1 --> V1
    O2 --> V1
    O3 --> V1

    V1 --> V2 --> V3 --> A1 --> A2 --> I1 --> I2 --> I3 --> D1 --> D2 --> D3 --> P1 --> P2 --> P3
```

---

## 2. Detailed Multi-System Sequence Diagram

```mermaid
sequenceDiagram
    autonumber
    actor Buyer as Distributor / Sales Rep
    actor Admin as Franchise Admin
    participant OrderModule as Order Management
    participant InvModule as Inventory & Batches
    participant BillModule as Tax Invoicing
    participant DispModule as Logistics & Dispatches
    participant PayModule as Payments & Ledger
    participant DB as MariaDB

    Buyer->>OrderModule: POST /orders (SKU, Qty, Shipping Address)
    OrderModule->>DB: INSERT INTO orders (status='SUBMITTED')
    DB-->>OrderModule: order_ref created
    OrderModule-->>Buyer: 201 Created (Order Received)

    Admin->>OrderModule: POST /admin/orders/{ref}/confirm
    OrderModule->>InvModule: Reserve stock from earliest expiring batches (FIFO)
    InvModule->>DB: UPDATE inventory_batches (reserve allocated_qty)
    OrderModule->>DB: UPDATE orders SET status='CONFIRMED'
    OrderModule-->>Admin: 200 OK (Order Confirmed)

    Admin->>BillModule: POST /admin/invoices/generate (order_ref)
    BillModule->>DB: Calculate Tax Split (CGST/SGST/IGST based on party state)
    BillModule->>DB: INSERT INTO invoices (grand_total, status='POSTED')
    BillModule->>DB: UPDATE orders SET status='INVOICED'
    BillModule-->>Admin: 201 Created (Tax Invoice Posted)

    Admin->>DispModule: POST /admin/dispatches (invoice_ref, transporter, lr_number)
    DispModule->>DB: INSERT INTO dispatches (status='IN_TRANSIT')
    DispModule->>DB: UPDATE inventory_batches (permanently deduct stock)
    DispModule-->>Admin: 201 Created (Dispatched with Tracking)

    Admin->>DispModule: POST /admin/dispatches/{ref}/deliver
    DispModule->>DB: UPDATE dispatches SET status='DELIVERED', delivered_at=NOW()
    DispModule-->>Admin: 200 OK (Shipment Delivered)

    Admin->>PayModule: POST /admin/payments (party_ref, amount, mode=NEFT)
    PayModule->>DB: INSERT INTO payments (status='RECORDED')
    PayModule->>DB: Allocate payment against unpaid invoices (status='ALLOCATED')
    PayModule->>DB: Recalculate party outstanding balance
    PayModule-->>Admin: 201 Created (Ledger Reconciled)
```

---

## 3. Order Status State Machine

```mermaid
stateDiagram-v2
    [*] --> DRAFT : Created via Portal Cart
    [*] --> SUBMITTED : Booked by Sales Rep / Direct
    DRAFT --> SUBMITTED : Distributor Submits Order
    DRAFT --> CANCELLED : Cancelled by Distributor

    SUBMITTED --> CONFIRMED : Approved by Franchise Admin
    SUBMITTED --> REJECTED : Rejected by Franchise Admin (Credit/Territory)

    CONFIRMED --> INVOICED : Tax Invoice Generated
    CONFIRMED --> CANCELLED : Cancelled before dispatch

    INVOICED --> DISPATCHED : Packed & Handed to Transporter
    DISPATCHED --> DELIVERED : Confirmed Delivery by Transporter
    DELIVERED --> [*]
    CANCELLED --> [*]
    REJECTED --> [*]
```
