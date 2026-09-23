# Traceability Matrix

| Requirement | Module | Model | ViewModel | View/Screen | API Endpoint | DB Table(s) | Permission | Field Validations | Workflow | Test Case |
|---|---|---|---|---|---|---|---|---|---|---|
| Regulatory Traceability | Compliance | `AuditLog` | `AuditLogViewModel` | `audit_logs.php` | `GET /api/v1/audit-logs` | `audit_logs` | `audit.view` | Action, User ID | Read-Only | `testAuditLogCreation()` |
| Batch/Expiry | Inventory | `Batch` | `BatchViewModel` | `batches.php` | `CRUD /api/v1/batches` | `batches` | `batch.manage` | Expiry Date (future) | Status (Active/Expired) | `testBatchExpiryValidation()` |
| Prescription Handling | Prescriptions | `Prescription` | `PrescriptionViewModel` | `prescriptions.php` | `CRUD /api/v1/prescriptions` | `prescriptions` | `prescription.manage` | Patient Name, Meds | Verified -> Dispensed | `testPrescriptionDispense()` |
| Territory Management | Users | `Territory` | `TerritoryViewModel` | `territories.php` | `CRUD /api/v1/territories` | `territories` | `territory.manage` | Region Code | N/A | `testTerritoryAssignment()` |
| Sample Distribution | Samples | `Sample` | `SampleViewModel` | `samples.php` | `CRUD /api/v1/samples` | `samples`, `sample_distributions` | `sample.manage` | Qty (int), Doctor ID | Dispatched -> Delivered | `testSampleDispatch()` |
| Product Catalog | Products | `Product` | `ProductViewModel` | `products.php` | `CRUD /api/v1/products` | `products` | `product.manage` | SKU, Price, Formula | Active/Inactive | `testProductCatalogSync()` |
| Master Data (Doctor/Chemist) | Masters | `Doctor`/`Chemist` | `MasterViewModel` | `masters.php` | `CRUD /api/v1/masters` | `doctors`, `chemists`, `hospitals` | `master.manage` | License Number | Verified/Unverified | `testDoctorLicenseValidation()` |
| Visit Reporting | Visits | `Visit` | `VisitViewModel` | `visits.php` | `CRUD /api/v1/visits` | `visits` | `visit.manage` | Date, Notes | Scheduled -> Completed | `testVisitWorkflow()` |
| Order-to-Cash | Orders | `Order`, `Invoice` | `OrderViewModel` | `orders.php`, `invoices.php` | `CRUD /api/v1/orders` | `orders`, `invoices`, `payments` | `order.manage` | Amount, Tax | Draft -> Invoiced -> Paid | `testOrderToInvoice()` |
| Authentication | Auth | `User` | `AuthViewModel` | `login.php` | `POST /api/v1/auth/token` | `users`, `oauth_tokens` | N/A | Email, Password | N/A | `testOAuthTokenGeneration()` |
| Multi-tenant | Global | `Tenant` | `TenantViewModel` | `tenants.php` | `CRUD /api/v1/tenants` | `tenants` | `tenant.manage` | Name, Domain | Active/Suspended | `testTenantIsolation()` |
