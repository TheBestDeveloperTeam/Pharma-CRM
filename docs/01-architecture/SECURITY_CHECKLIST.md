# Security & Compliance Checklist

## 1. Authentication & Authorization
- [ ] **JWT Tokens:** Issued with short expiration (15 mins), secure Refresh Tokens used for long-lived sessions.
- [ ] **Password Hashing:** `password_hash()` using `PASSWORD_ARGON2ID` (fallback `PASSWORD_BCRYPT`).
- [ ] **RBAC/ABAC Enforcement:** Every API controller enforces `Gate::allows('permission')`.
- [ ] **Tenant Isolation:** All DB queries append `AND tenant_id = ?` dynamically via Repository abstraction (prevents IDOR across tenants).

## 2. Input & Output Security
- [ ] **SQL Injection:** All DB queries use strict PDO Prepared Statements. No string interpolation in queries.
- [ ] **XSS (Cross-Site Scripting):** All outputs rendered in HTML use `htmlspecialchars($string, ENT_QUOTES, 'UTF-8')`.
- [ ] **CSRF:** API is stateless (Bearer tokens). Web UI login/forms use secure anti-CSRF tokens injected into hidden inputs.
- [ ] **Mass Assignment:** Model abstractions strictly define `$fillable` arrays. No blind `$model->update($_POST)` allowed.
- [ ] **File Uploads:** Validated against allow-list of MIME types and extensions. Files stored outside web root where possible.

## 3. Infrastructure & Transport
- [ ] **HTTPS / HSTS:** Forced TLS for all endpoints.
- [ ] **CORS:** Strict CORS policy allowing only authorized domains.
- [ ] **Rate Limiting:** Login/Token endpoints heavily rate-limited (e.g., max 5 attempts/minute) to prevent brute-force.
- [ ] **Secret Management:** No credentials hardcoded in files. All passwords/keys reside in `.env`.

## 4. Compliance (Pharma Specific)
- [ ] **Audit Trail:** Every mutation (INSERT/UPDATE/DELETE) logged to `audit_logs` table with timestamp, user_id, action, and JSON diffs.
- [ ] **Data Retention / Soft Deletes:** Sensitive entities (Orders, Prescriptions, Users) use `deleted_at` (soft deletes) instead of hard deletions.
- [ ] **Traceability:** Batches are strictly tracked. Expiry dates strictly enforced at the application level.
