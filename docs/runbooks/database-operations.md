# Database Operations Runbook

This runbook covers schema management, backups, restorations, and health monitoring for the MySQL 8 database.

## Database Architecture Overview

*   **Engine:** MySQL 8.0+ (InnoDB)
*   **Collation:** `utf8mb4_unicode_ci` for full Unicode support.
*   **Access:** Application accesses the database via PDO, utilizing a dedicated user (`cpuser_crm`).
*   **Migrations:** Custom PHP-based migration system located in `cli/migrate.php`.

---

## Schema Management

Changes to the database schema must be handled carefully, especially in production.

### Development & Staging

In non-production environments, you can rapidly refresh the schema:
```bash
# Drops all tables and re-runs all migrations
php cli/migrate.php --fresh
```

### Production Schema Changes

**WARNING:** Running `--fresh` in production will destroy all data. The CLI tool requires the `--i-understand` flag to execute in production mode, but this should *never* be used for routine updates.

To apply a production schema change, a maintenance window is required:

1.  **Schedule Window:** Notify tenants of upcoming downtime.
2.  **Backup:** Create and verify a snapshot before touching the schema.
    ```bash
    php cli/backup.php --verify
    ```
3.  **Staging Test:** Apply the migration to the staging environment first.
    ```bash
    php cli/migrate.php --fresh  # Assuming staging uses fresh builds
    php cli/test-agents.php --all
    ```
4.  **Execute:** During the maintenance window, run the specific new migration scripts manually or via a non-destructive update command if implemented in the migrator.
5.  **Rollback (If needed):**
    ```bash
    php cli/backup.php --restore=storage/backups/backup-TIMESTAMP.sql.gz
    ```

---

## Backup Procedures

Automated backups run daily at 1:30 AM via the scheduler.

### Manual Backup Creation
To trigger a manual backup (creates a gzipped SQL dump in `storage/backups/`):
```bash
php cli/backup.php --out=storage/backups/
```

### Backup Verification
A critical operational practice is verifying backups. This command creates a dump, restores it to a temporary test database, and runs the `agent-schema` test to ensure data integrity:
```bash
php cli/backup.php --verify
```

### Retention Policy
The system automatically keeps the last **14 days** of backups. Older backups are purged by the daily cleanup cron job.

---

## Key Diagnostic Queries

Use these queries via phpMyAdmin or the MySQL CLI to monitor database health and application state.

### 1. Job Queue Health
Monitor active and pending background jobs:
```sql
SELECT status, COUNT(*) FROM job_queue GROUP BY status;
```

Investigate permanently failed (DEAD) jobs:
```sql
SELECT job_type, error_message, created_at 
FROM job_queue 
WHERE status='DEAD' 
ORDER BY created_at DESC LIMIT 20;
```

### 2. Security Monitoring
View recent security events (failed logins, token reuse):
```sql
SELECT action, user_ref, ip, created_at 
FROM audit_logs 
WHERE category='SECURITY' 
ORDER BY created_at DESC LIMIT 50;
```

### 3. Integration Health (Webhooks)
Check for failing inbound webhooks:
```sql
SELECT source_ref, error_code, error_message, attempt_count 
FROM webhook_events 
WHERE status IN ('FAILED','DEAD') 
LIMIT 20;
```

### 4. Business Metrics: Expiring Inventory
Find batches expiring within the next 90 days:
```sql
SELECT franchise_ref, product_ref, batch_no, expiry_date, on_hand_qty 
FROM inventory_batches 
WHERE expiry_date <= DATE_ADD(NOW(), INTERVAL 90 DAY) 
  AND status='SALEABLE' 
ORDER BY expiry_date ASC;
```

### 5. Business Metrics: Unpaid Invoices
Identify overdue invoices:
```sql
SELECT franchise_ref, invoice_no, party_ref, due_date, grand_total-paid_total AS outstanding 
FROM invoices 
WHERE due_date < CURDATE() 
  AND paid_total < grand_total 
  AND status='POSTED' 
ORDER BY due_date ASC;
```

### 6. Storage & Bloat Monitoring
Check if utility tables are growing too large (cleanup crons might be failing):
```sql
-- Check rate limit table size
SELECT COUNT(*) FROM rate_limits;

-- Check for stale idempotency keys (> 7 days old)
SELECT COUNT(*) FROM api_idempotency_keys 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY);
```
