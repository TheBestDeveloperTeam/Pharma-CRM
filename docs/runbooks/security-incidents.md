# Security Incident Handling Runbook

This document details procedures for identifying, analyzing, and mitigating security-related incidents within the Pharma CRM platform.

## Security Overview

The system uses a robust authentication and logging architecture:
*   **Authentication:** Bearer-only JWT for short-lived access, paired with opaque, rotating refresh tokens stored in the database.
*   **Auditing:** Security events are logged to the `audit_logs` table and the JSON-lines flat files (`storage/logs/`).
*   **Isolation:** Multi-tenant data is logically separated; cross-tenant access attempts are strictly monitored.

---

## 1. Token Theft / Refresh Reuse (`REFRESH_REUSE_DETECTED`)

When a refresh token is used more than once, the system flags it as `REFRESH_REUSE_DETECTED`. Since refresh tokens rotate on use, a reuse attempt strongly suggests the token was copied or intercepted.

**System Automated Action:**
The system automatically revokes the *entire session family* (all tokens derived from the original login). The user is forcibly logged out.

**Investigation:**
1.  Query the audit logs for the event:
    ```sql
    SELECT user_ref, ip, user_agent, created_at 
    FROM audit_logs 
    WHERE action='REFRESH_REUSE_DETECTED' 
    ORDER BY created_at DESC LIMIT 10;
    ```
2.  Analyze the IP address and User-Agent. Does the IP match the user's normal geographical location or corporate network?
3.  Check for concurrent logins from anomalous IPs for that user.

**Remediation:**
*   The system has already mitigated the immediate threat by invalidating the session.
*   If the IP is suspicious, contact the user to reset their password.
*   Consider blocking the malicious IP at the cPanel firewall level.

---

## 2. Cross-Tenant Data Access Attempt (`CROSS_TENANT_ATTEMPT`)

A user authenticated under Franchise A attempts to access resources (e.g., invoices, patients, inventory) belonging to Franchise B.

**Investigation:**
1.  Identify the source:
    ```sql
    SELECT user_ref, details, ip, created_at 
    FROM audit_logs 
    WHERE action='CROSS_TENANT_ATTEMPT' 
    ORDER BY created_at DESC;
    ```
2.  Determine intent. Look at the `details` JSON.
    *   *Bug/Glitch:* Is the UI requesting sequentially numbered IDs? It might be a client-side bug.
    *   *Active Attack:* Are there hundreds of requests trying different UUIDs or sequential IDs? This indicates an Insecure Direct Object Reference (IDOR) probing attack.

**Remediation:**
*   If an active attack is underway, the application-level rate limiting should throttle the user.
*   If persistent, manually suspend the offending user:
    ```sql
    UPDATE users SET status='SUSPENDED' WHERE user_ref=?;
    ```
*   Block the IP address in the server firewall.

---

## 3. Webhook Tampering (`WEBHOOK_SIGNATURE_INVALID`)

Incoming webhook payloads failed HMAC signature verification.

**Investigation:**
1.  Check the webhook events table:
    ```sql
    SELECT source_ref, error_message, payload 
    FROM webhook_events 
    WHERE status='FAILED' AND error_message LIKE '%signature%';
    ```
2.  Verify with the third-party provider if they have rotated their signing keys.
3.  Analyze the payload. Is it garbage data, or does it look like a legitimate payload with a bad signature?

**Remediation:**
*   If the third party rotated their keys, update the webhook secret for the source:
    ```bash
    # Via API or DB
    UPDATE webhook_sources SET secret_key='NEW_SECRET' WHERE source_ref=?;
    ```
*   If malicious probing is suspected, block the offending IP address.

---

## 4. Brute Force / Credential Stuffing (`RATE_LIMIT_HIT`)

Excessive login attempts trigger the rate limiter, locking the account temporarily.

**Investigation:**
1.  Find the affected accounts and attacker IPs:
    ```sql
    SELECT user_ref, ip, COUNT(*) as attempts
    FROM audit_logs 
    WHERE action='RATE_LIMIT_HIT' AND category='SECURITY'
    GROUP BY user_ref, ip
    ORDER BY attempts DESC;
    ```
2.  Determine if it's a distributed attack (many IPs targeting one user) or a single IP targeting many users.

**Remediation:**
*   The system handles this gracefully by locking the account (see Incident Response Playbook for unlocking).
*   For persistent attacks from a single IP or subnet, block them via cPanel IP Blocker or `.htaccess`.

---

## 5. Privilege Escalation (`ROLE_ESCALATION_ATTEMPT`)

A standard user attempts to access Supervisor or Super Admin endpoints.

**Investigation:**
1.  Review logs:
    ```sql
    SELECT user_ref, details, ip 
    FROM audit_logs 
    WHERE action='ROLE_ESCALATION_ATTEMPT';
    ```
2.  Analyze the `details` to see which endpoint was targeted.

**Remediation:**
*   Similar to cross-tenant attempts, determine if it's a UI routing bug or an active directory traversal/API probing attack.
*   Suspend malicious users and block associated IPs.
