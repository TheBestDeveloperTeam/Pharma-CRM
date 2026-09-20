# Cron & Background Jobs Runbook

This document details the configuration and management of the background task system. 

## Architecture

Because the application is hosted on a cPanel shared environment, we do not have access to long-running daemons like Supervisor, Redis, or RabbitMQ. 
Instead, we use a **cron-driven worker pattern**:
*   Jobs are serialized and stored in the MySQL `job_queue` table.
*   A cron job triggers a PHP script (`cli/worker.php`) every minute.
*   The script runs for a defined "budget" (e.g., 50 seconds) to process as many jobs as possible before exiting, preventing process overlap.

## Cron Configuration

The server's crontab should be configured exactly as follows. (Accessible via cPanel Cron Jobs interface or `crontab -e`).

```text
# Run worker every minute (processes the job_queue)
* * * * * php /path/to/crm/cli/worker.php --budget=50 > /dev/null 2>&1

# Run five-minute tasks (Webhook retries, quick notifications)
*/5 * * * * php /path/to/crm/cli/scheduler.php five-minute > /dev/null 2>&1

# Run hourly tasks (Follow-up reminders, release expired reservations)
0 * * * * php /path/to/crm/cli/scheduler.php hourly > /dev/null 2>&1

# Run daily tasks at 1:30 AM (Maintenance, backups, near-expiry scans)
30 1 * * * php /path/to/crm/cli/scheduler.php daily > /dev/null 2>&1
```

## Scheduled Task Breakdown

### The `worker.php` Script
This script is the engine of the background system. It continuously polls the `job_queue` table for `PENDING` jobs, changes their state to `RUNNING`, executes the associated class, and marks them `DONE`.
*   `--once`: Runs exactly one job and exits. Useful for debugging.
*   `--budget=50`: Runs in a loop for 50 seconds. This is the production standard, allowing 10 seconds of breathing room before the next cron minute fires.

### The `scheduler.php` Script
This script does not execute jobs itself; rather, it *dispatches* predefined tasks based on the schedule, or performs system maintenance.

*   **five-minute**: 
    *   Sweeps `webhook_events` for transient failures and re-queues them.
    *   Dispatches queued email/SMS notifications.
*   **hourly**: 
    *   Releases inventory reservations that have expired (e.g., cart abandonment).
    *   Generates follow-up reminders for CRM tasks.
*   **daily (1:30 AM)**: 
    *   Near-expiry scan (`NearExpiryScan` job dispatched).
    *   Payment reminders dispatched.
    *   **Cleanup:** Purges old `rate_limits` and `api_idempotency_keys`.
    *   **Log Rotation:** Compresses logs older than 1 day, deletes logs older than 30 days.
    *   **Backup:** Executes the database backup routine.

## Managing the Job Queue

The `job_queue` table tracks the lifecycle of every background task.

### Job States
1.  **PENDING:** Awaiting execution by the worker.
2.  **RUNNING:** Picked up by a worker currently processing it.
3.  **DONE:** Successfully executed. (Usually purged periodically).
4.  **RETRY_WAIT:** Failed temporarily, waiting for backoff timer to expire.
5.  **DEAD:** Exceeded maximum retries or encountered a fatal, non-retryable error. Requires manual intervention.

### Troubleshooting Scenarios

**Scenario A: Jobs are not processing at all**
1.  Verify the cron daemon is running.
2.  Check for PHP fatal errors in `storage/logs/` that might be crashing the worker instantly.
3.  Manually run `php cli/worker.php --once` in the terminal to observe output directly.

**Scenario B: A specific job is stuck in RUNNING**
If a job is interrupted (e.g., server restart) while `RUNNING`, it will remain in that state.
The worker has built-in logic to auto-recover jobs that have been `RUNNING` for over 10-15 minutes. 
To manually recover:
```sql
UPDATE job_queue SET status='PENDING' WHERE status='RUNNING' AND started_at < NOW() - INTERVAL 15 MINUTE;
```

**Scenario C: Resurrecting DEAD jobs**
Once you have deployed a fix for the code or data issue that caused a job to die:
```sql
-- View the errors
SELECT id, job_type, error_message FROM job_queue WHERE status='DEAD';

-- Re-queue them for processing
UPDATE job_queue SET status='PENDING', attempt_count=0, error_message=NULL WHERE status='DEAD';
```

## Log Rotation & Cleanup

Because we rely on flat-file JSON line logs, managing disk space is crucial. The `daily` scheduler handles this:
1.  Current day's log is `app-YYYY-MM-DD.log`.
2.  Older logs are ignored by the app.
3.  The daily cron deletes files older than 30 days.

If the cron fails and the disk fills up, manually purge:
```bash
find storage/logs/ -name "*.log" -mtime +30 -delete
```
