# Super Admin - Platform Operations & Governance Workflow

## 1. Actor Profile
* **Client Surface**: `crm-super`
* **OAuth Role**: `SUPER_ADMIN`
* **Access Scope**: `PLATFORM` (Global unconstrained platform authority)
* **Key Feature**: Native **Auto "SignInAs" Impersonation** (allows executing Franchise and Portal APIs seamlessly)

---

## 2. Platform Operations Flow Graph

```mermaid
flowchart TD
    Start(["Super Admin Login (/api/v1/oauth/token)"]) --> SuperDashboard["View Global Metrics<br/>(/api/v1/super/dashboard/stats)"]

    SuperDashboard --> ManageOrgs{"Manage Organizations?"}
    ManageOrgs -->|Yes| OrgCRUD["Orgs Management<br/>• GET /api/v1/super/organizations<br/>• POST /api/v1/super/organizations<br/>• PATCH /api/v1/super/organizations/{ref}"]
    ManageOrgs -->|No| ManageFranchises{"Manage Franchises?"}

    OrgCRUD --> ManageFranchises

    ManageFranchises -->|Yes| FranchiseOps["Franchise Lifecycle<br/>• GET /api/v1/super/franchises<br/>• POST /api/v1/super/franchises<br/>• POST /api/v1/super/franchises/{ref}/admins<br/>• POST /api/v1/super/franchises/{ref}/suspend<br/>• POST /api/v1/super/franchises/{ref}/activate"]
    ManageFranchises -->|No| SecurityAudit{"Review Security & Audits?"}

    FranchiseOps --> SecurityAudit

    SecurityAudit -->|Yes| AuditLogs["Security & Telemetry<br/>• GET /api/v1/super/audit<br/>• GET /api/v1/super/security-events"]
    SecurityAudit -->|No| LowerApiAccess{"Access Franchise/Portal APIs?"}

    AuditLogs --> LowerApiAccess

    LowerApiAccess -->|Yes| DynamicSignInAs["Tenant Middleware 'SignInAs'<br/>• Default: First active Franchise & Party<br/>• Targeted: X-Franchise-Code & X-Party-Ref<br/>• Access all Admin & Portal routes"]
    LowerApiAccess -->|No| EndSuper([Super Admin Session Complete])

    DynamicSignInAs --> EndSuper
```

---

## 3. Detailed Use Cases & Sequence

```mermaid
sequenceDiagram
    autonumber
    actor SA as Super Admin
    participant Gateway as API Gateway / Auth
    participant SuperCtrl as Super Controllers
    participant TenantMW as Tenant Middleware
    participant AdminCtrl as Franchise/Portal Controllers
    participant DB as MariaDB

    SA->>Gateway: POST /api/v1/oauth/token (crm-super)
    Gateway-->>SA: 200 OK (JWT with role=SUPER_ADMIN, scp=PLATFORM)

    SA->>SuperCtrl: GET /api/v1/super/dashboard/stats
    SuperCtrl->>DB: Query total orgs, franchises, users, queues
    DB-->>SuperCtrl: Aggregated counts
    SuperCtrl-->>SA: 200 OK (Platform Health & Totals)

    SA->>SuperCtrl: POST /api/v1/super/franchises (Create Mumbai PCD)
    SuperCtrl->>DB: INSERT INTO franchises
    DB-->>SuperCtrl: Created
    SuperCtrl-->>SA: 201 Created (FRN-MUMBAI...)

    SA->>Gateway: GET /api/v1/admin/categories (No Franchise Header)
    Gateway->>TenantMW: Verify Bearer Token
    TenantMW->>TenantMW: Detect SUPER_ADMIN & Empty franchiseRef
    TenantMW->>DB: SELECT first active franchise (FRN-MUMBAI...)
    TenantMW->>TenantMW: Rebuild TenantContext (SignInAs MUMBAI)
    TenantMW->>AdminCtrl: Forward request with populated context
    AdminCtrl->>DB: SELECT * FROM product_categories WHERE franchise_ref = ?
    DB-->>AdminCtrl: Categories list
    AdminCtrl-->>SA: 200 OK (Seamless Admin Bypass)
```

---

## 4. API Endpoints Reference

| Operation | Method | Endpoint | Description |
| :--- | :---: | :--- | :--- |
| **Login** | `POST` | `/api/v1/oauth/token` | Authenticate with `crm-super` client ID |
| **Metrics** | `GET` | `/api/v1/super/dashboard/stats` | Global tenant count, queue health, system time |
| **Audit** | `GET` | `/api/v1/super/audit` | Platform immutable security & action audit logs |
| **Security** | `GET` | `/api/v1/super/security-events` | Token rejections, IP anomalies, auth violations |
| **Orgs List** | `GET` | `/api/v1/super/organizations` | List all pharmaceutical tenant groups |
| **Org Create** | `POST` | `/api/v1/super/organizations` | Create tenant organization |
| **Franchises** | `GET` | `/api/v1/super/franchises` | List PCD franchise divisions |
| **Franchise Create**| `POST` | `/api/v1/super/franchises` | Provision new PCD franchise |
| **Suspend** | `POST` | `/api/v1/super/franchises/{ref}/suspend` | Lock franchise access |
| **Activate** | `POST` | `/api/v1/super/franchises/{ref}/activate` | Re-enable suspended franchise |
