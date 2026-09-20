# Pharma CRM Operations Runbooks

Welcome to the Operations Runbooks for the Multi-tenant Pharma CRM. This directory contains standard operating procedures, incident response playbooks, and maintenance guides for managing the application on our cPanel shared hosting environment.

## System Overview

*   **Platform:** cPanel Shared Hosting
*   **Tech Stack:** Core PHP 8.1+ · MySQL 8 InnoDB · Vanilla HTML/CSS/JS
*   **Infrastructure:** No Docker, no Redis, no background daemon (cron-driven)
*   **Authentication:** Bearer-only JWT + opaque rotating refresh tokens
*   **Storage:** File-based cache (atomic writes + flock), File-based logs (JSON lines, daily rotation)
*   **Background Jobs:** DB-backed `job_queue`, executed by `cli/worker.php` via cron every minute

## Runbooks Index

1.  **[Incident Response Playbook](./incident-response.md):** Step-by-step guides for resolving common production issues, downtime, and operational alerts.
2.  **[Security Incident Handling](./security-incidents.md):** Procedures for investigating and mitigating security events like token theft, cross-tenant access, and brute-force attacks.
3.  **[Database Operations](./database-operations.md):** Maintenance procedures, schema updates, backup/restore processes, and diagnostic queries.
4.  **[Cron & Background Jobs](./cron-and-jobs.md):** Managing the cron-driven worker system, scheduler scripts, and job queue troubleshooting.

## Quick Reference: CLI Tools

The following CLI tools are available in the project root (`e:\Projects\PHP\crm`). Execute them via the PHP CLI:

```bash
# System Installation & Setup
php cli/install.php                           # Full install
php cli/migrate.php --fresh [--i-understand]  # Schema refresh
php cli/seed.php --fresh [--demo]             # Fresh seed

# Worker & Scheduler
php cli/worker.php --once                     # Run one worker tick
php cli/worker.php --budget=50                # Run worker for 50 seconds
php cli/scheduler.php minute|five-minute|hourly|daily  # Manual cron trigger

# Backups
php cli/backup.php --out=storage/backups/     # Create backup
php cli/backup.php --verify                   # Backup + restore-test

# Security & Authentication
php cli/keys.php generate                     # Generate JWT keys
php cli/keys.php rotate                       # Zero-downtime key rotation
php cli/user.php unlock --email=... --franchise=FRN-...  # Unlock locked user

# Testing & Quality
php cli/test-agents.php --all                 # Run all test agents
php cli/test-agents.php --agent=agent-schema  # Run single agent
php cli/lint.php                              # Static lint checks
```

## System Monitoring Endpoints

*   **Health Check:** `GET /health` (Basic PHP alive check, returns 200 OK)
*   **Readiness Check:** `GET /ready` (Verifies DB connection, storage permissions, JWT keys, and MySQL version >=8.0.16)

## First Response: General Troubleshooting

When an issue occurs, follow these initial triaging steps before diving into specific runbooks:

1.  **Check Readiness:** Hit the `/ready` endpoint to ensure critical components (DB, Disk, Auth) are operational.
2.  **Check Logs:** Review today's application log for fatal errors.
    ```bash
    tail -f storage/logs/app-$(date +%Y-%m-%d).log | grep ERROR
    ```
3.  **Check Job Queue:** Ensure background jobs aren't piling up.
    ```sql
    SELECT status, COUNT(*) FROM job_queue GROUP BY status;
    ```
4.  **Check Super Admin Dashboard:** Log in as Super Admin and check `/super/health` and `/super/security-events`.

Proceed to the [Incident Response Playbook](./incident-response.md) for specific failure scenarios.
