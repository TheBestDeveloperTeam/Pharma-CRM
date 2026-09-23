# Backend Rebuild Spec

## 1. Modules and Components
This backend consists of fully modularized entities.

### 1.1 Auth Module
- **Models**: `User`, `OAuthToken`, `Role`, `Permission`
- **Controllers**: `AuthController` (login, refresh, logout, profile)
- **Services**: `AuthService` (authenticates via password hash verification, generates JWT)
- **Routes**:
  - `POST /api/v1/auth/token`
  - `POST /api/v1/auth/refresh`
  - `POST /api/v1/auth/revoke`
  - `GET /api/v1/auth/me`

### 1.2 Tenant Module
- **Models**: `Tenant`
- **Controllers**: `TenantController`
- **Services**: `TenantService` (manages multi-tenant lifecycle)
- **Routes**: `CRUD /api/v1/tenants`

### 1.3 Master Data (Doctors, Chemists, Hospitals, Products)
- **Models**: `Doctor`, `Chemist`, `Hospital`, `Product`
- **Controllers**: `MasterController`, `ProductController`
- **Services**: `MasterDataService`
- **Routes**:
  - `CRUD /api/v1/doctors`
  - `CRUD /api/v1/chemists`
  - `CRUD /api/v1/hospitals`
  - `CRUD /api/v1/products`

### 1.4 Core Pharma Modules
#### 1.4.1 Batches & Inventory
- **Models**: `Batch`, `Inventory`
- **Controllers**: `BatchController`, `InventoryController`
- **Services**: `InventoryService` (handles stock mutations, expiry checks)
- **Routes**: `CRUD /api/v1/batches`, `GET /api/v1/inventory`

#### 1.4.2 Prescriptions & Orders
- **Models**: `Prescription`, `Order`, `OrderItem`, `Invoice`
- **Controllers**: `PrescriptionController`, `OrderController`, `InvoiceController`
- **Services**: `OrderToCashService` (handles draft -> invoiced workflow)
- **Routes**: `CRUD /api/v1/prescriptions`, `CRUD /api/v1/orders`, `CRUD /api/v1/invoices`

#### 1.4.3 Field Operations (Territories, Reps, Visits, Samples)
- **Models**: `Territory`, `Visit`, `SampleDistribution`
- **Controllers**: `TerritoryController`, `VisitController`, `SampleController`
- **Services**: `FieldOpsService`
- **Routes**: `CRUD /api/v1/visits`, `CRUD /api/v1/samples`

## 2. Validation & Security
All requests are piped through generic `Validator` utilities ensuring the rules detailed in `VALIDATION_MATRIX.csv` are applied. 
- Inputs are sanitized using `htmlspecialchars()` on output or prepared statements on insert.
- Roles and permissions are evaluated inside Controllers/Middleware via `Gate::allows('permission_name')`.
