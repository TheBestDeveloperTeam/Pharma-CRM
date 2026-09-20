# Incident Response Playbook

This playbook outlines the steps to diagnose and resolve common operational issues in the Pharma CRM system.

## 1. System Outage: `/ready` Endpoint Returning 503

The `/ready` endpoint performs critical system checks. If it returns a 503 Service Unavailable, the system is down or degraded.

**Symptoms:**
*   Monitoring alerts trigger for `/ready`.
*   Users report complete inability to use the system.

**Investigation & Resolution:**
1.  **Identify the Failing Component:** The response body of the `/ready` endpoint contains a JSON payload detailing which check failed (e.g., `{"status": "error", "component": "database"}`).
2.  **Database Failure:**
    *   Test connection manually: `mysql --host=localhost --user=cpuser_crm --password DB_NAME`
    *   Verify MySQL is running on the cPanel server.
    *   Check database user credentials in `.env`.
3.  **Storage Failure:**
    *   Check directory permissions: `ls -la storage/`
    *   Ensure the PHP process owner has write access to `storage/logs/`, `storage/cache/`, and `storage/backups/`.
4.  **JWT Key Missing:**
    *   Verify `.env` has `JWT_KEY_K1` set.
    *   If missing, regenerate (Note: invalidates existing tokens): `php cli/keys.php generate`
5.  **MySQL Version Issue:**
    *   The system requires MySQL >= 8.0.16. Run `SELECT VERSION();` to verify the host hasn't downgraded or migrated to an unsupported MariaDB version.

## 2. Job Queue Backlog (Jobs Piling Up)

**Symptoms:**
*   High number of `PENDING` jobs.
*   Background tasks (emails, Webhook retries) are delayed.

**Investigation & Resolution:**
1.  **Check Cron Jobs:** Ensure the worker cron is installed and active.
    ```bash
    crontab -l
    ```
    You should see an entry like `* * * * * php /path/to/crm/cli/worker.php --budget=50`.
2.  **Check Stalled Workers:** See if jobs are stuck in `RUNNING`.
    ```sql
    SELECT * FROM job_queue WHERE status='RUNNING' ORDER BY started_at DESC LIMIT 5;
    ```
3.  **Manual Trigger:** Try running the scheduler manually to see if it outputs errors.
    ```bash
    php cli/scheduler.php minute
    ```
4.  **Recover Stale Jobs:** The worker usually auto-recovers stale jobs (>10 mins). To do it manually:
    ```sql
    UPDATE job_queue 
    SET status='PENDING' 
    WHERE status='RUNNING' AND started_at < NOW() - INTERVAL 15 MINUTE;
    ```

## 3. DEAD Jobs (Permanent Failures)

**Symptoms:**
*   Super admin dashboard shows non-zero DEAD jobs count.
*   Specific background tasks continually fail.

**Investigation & Resolution:**
1.  **Identify DEAD jobs:**
    ```sql
    SELECT id, job_type, error_message, updated_at FROM job_queue WHERE status='DEAD' ORDER BY updated_at DESC;
    ```
2.  **Investigate:** Read the `error_message` column to determine the root cause (e.g., bad data, API timeout).
3.  **Re-queue:** Once the underlying issue is fixed, re-queue the jobs:
    ```sql
    UPDATE job_queue 
    SET status='PENDING', attempt_count=0, error_message=NULL 
    WHERE id=?; -- Use specific ID or WHERE status='DEAD'
    ```

## 4. User Locked Out

**Symptoms:**
*   User reports inability to log in due to "Account Locked" message.
*   Triggered by consecutive failed login attempts (Rate Limit hit).

**Investigation & Resolution:**
1.  **CLI Method (Preferred):**
    ```bash
    php cli/user.php unlock --email=user@example.com --franchise=FRN-xxx
    ```
2.  **SQL Method:**
    ```sql
    UPDATE users 
    SET locked_until=NULL, failed_login_count=0 
    WHERE email='user@example.com';
    ```

## 5. JWT Key Issues (Tokens Failing)

**Symptoms:**
*   All users are suddenly logged out.
*   API requests return 401 Unauthorized globally.

**Investigation & Resolution:**
1.  Check `/ready` endpoint for JWT key status.
2.  Verify `.env` contains a valid 64-character hex string for `JWT_KEY_K1`.
3.  **Emergency Reset:** If keys are lost or compromised.
    ```bash
    php cli/keys.php generate
    ```
    *WARNING: This will invalidate all currently active sessions system-wide.*

## 6. Backup Failure

**Symptoms:**
*   Daily cron report indicates backup failed.
*   `storage/backups/` does not contain recent archives.

**Investigation & Resolution:**
1.  **Check Permissions:** `ls -la storage/backups/` (Must be writable).
2.  **Check Disk Space:** `df -h`
3.  **Manual Test:** Run the verify command to pinpoint errors:
    ```bash
    php cli/backup.php --verify
    ```

## 7. Webhook Events Stuck in FAILED State

**Symptoms:**
*   External integrations are not receiving updates.

**Investigation & Resolution:**
1.  **Check Logs:**
    ```sql
    SELECT id, status, error_message FROM webhook_events WHERE status='FAILED' LIMIT 20;
    ```
2.  **Determine Cause:** Is it a permanent failure (e.g., 404 from target) or a retryable network error?
3.  **Re-queue:**
    ```sql
    UPDATE webhook_events SET status='RECEIVED', attempt_count=0 WHERE id=?;
    ```

## 8. Near-Expiry Batch Alerts Not Firing

**Symptoms:**
*   Franchises complain they aren't notified of expiring inventory.

**Investigation & Resolution:**
1.  **Check Daily Scheduler:** Did the 1:30 AM daily job run?
    ```sql
    SELECT * FROM job_queue WHERE job_type='NearExpiryScan' ORDER BY created_at DESC LIMIT 5;
    ```
2.  **Manual Trigger:** Run the daily scheduler manually to force execution.
    ```bash
    php cli/scheduler.php daily
    ```

## 9. Franchise Suspended But Tokens Still Working

**Symptoms:**
*   Admin suspends a franchise, but logged-in users continue making API calls.

**Investigation & Resolution:**
*   **Cause:** The FileCache retains user/tenant validation state for up to 30 seconds for performance.
*   **Resolution:** Wait 30 seconds for the cache to expire automatically.
*   **Emergency Cache Clear:** Delete the tenant's cache file in `storage/cache/` or force status via SQL:
    ```sql
    UPDATE franchises SET status='SUSPENDED' WHERE franchise_ref=?;
    -- Then clear cache directory
    ```

## 10. Storage Disk Full

**Symptoms:**
*   System crashes, unable to write logs or cache.
*   Database operations may fail.

**Investigation & Resolution:**
1.  **Check Space:** `df -h`
2.  **Clear Old Logs:** The daily scheduler should handle this, but if it failed:
    ```bash
    find storage/logs/ -name "*.log" -mtime +30 -delete
    ```
3.  **Check Exports & Backups:**
    *   Clean up old CSVs in `storage/exports/`.
    *   Ensure `storage/backups/` contains a maximum of 14 files.
