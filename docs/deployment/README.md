# Pharma CRM — cPanel Deployment Guide

> **Stack:** PHP 8.1+ · MySQL 8.0.16+ · Apache + PHP-FPM/LSAPI · cPanel Shared Hosting
>
> **No Docker · No Redis · No Shell Daemons · No Composer · No npm**

---

## Table of Contents

1. [Overview](#1-overview)
2. [Prerequisites](#2-prerequisites)
3. [Directory Layout on cPanel](#3-directory-layout-on-cpanel)
4. [`.htaccess` Configuration](#4-htaccess-configuration)
5. [Environment Configuration (`.env`)](#5-environment-configuration-env)
6. [First-Time Installation](#6-first-time-installation)
7. [Cron Job Setup](#7-cron-job-setup)
8. [Release Procedure](#8-release-procedure)
9. [Schema Change Management](#9-schema-change-management)
10. [Rollback Procedure](#10-rollback-procedure)
11. [Backup Strategy](#11-backup-strategy)
12. [JWT Key Rotation](#12-jwt-key-rotation)
13. [Health Monitoring](#13-health-monitoring)
14. [Troubleshooting Common Issues](#14-troubleshooting-common-issues)
15. [cPanel-Specific Security Notes](#15-cpanel-specific-security-notes)
16. [CLI Reference](#16-cli-reference)
17. [Performance Considerations](#17-performance-considerations)
18. [Log Management](#18-log-management)

---

## 1. Overview

Pharma CRM is a multi-tenant pharmaceutical sales management system served from a **cPanel shared hosting** environment. The application exposes **four user-facing surfaces** (Super Admin, Org Admin, Franchise Admin, Sales Rep) over a single domain and uses a queue-based job system driven by **cPanel cron jobs** — no persistent worker daemons are required.

### Architecture at a Glance

```
Browser / Mobile App
        │
        ▼
  public_html/           ← Apache / LSAPI web root (only public files here)
    index.php            ← sole entry point, bootstraps app
    .htaccess            ← security + rewrite rules
    assets/              ← CSS, JS, SVG (served directly by Apache)
        │
        │  require __DIR__ . '/../pharma-crm/bootstrap/app.php'
        ▼
  pharma-crm/            ← application code (NOT web-accessible)
    app/                 ← controllers, models, services, middleware
    bootstrap/           ← app.php bootstrapper
    cli/                 ← command-line scripts (install, worker, scheduler…)
    database/            ← schema SQL + seed SQL
    storage/             ← logs, cache, exports, backups, private uploads
    .env                 ← secrets and configuration (NEVER in web root)
        │
        ▼
  MySQL 8.0.16+          ← cpuser_crm database
  cPanel Cron Jobs       ← drive the job queue and scheduler ticks
```

### Design Constraints

| Constraint | Implication |
|---|---|
| cPanel shared hosting | No root access; no systemd; no Docker |
| No Redis / Memcached | Queue backed by MySQL `SELECT … FOR UPDATE SKIP LOCKED` |
| No shell daemons | Worker is driven by a 1-minute cron job with a 50-second budget |
| No Composer / npm | All dependencies bundled in `app/vendor/`; assets pre-built |
| Shared IP / SSL | Must enforce HTTPS via `.htaccess` redirect |

---

## 2. Prerequisites

### Hosting Requirements

| Component | Minimum | Recommended |
|---|---|---|
| PHP | 8.1 | 8.2+ |
| MySQL | 8.0.16 (CHECK constraint required) | 8.0.32+ |
| Apache | 2.4+ with `mod_rewrite` | — |
| PHP Handler | PHP-FPM or LSAPI | LSAPI (better performance on LiteSpeed) |
| Disk Space | 500 MB | 2 GB+ |
| SSH Access | Required for install | — |
| cPanel Version | 98+ | Latest |

### Required PHP Extensions

Verify these are enabled in cPanel → **PHP Selector** or `php -m`:

```
pdo          pdo_mysql    mbstring     openssl
json         curl         fileinfo     intl
zip          gd           bcmath       sodium
```

Check from CLI:

```bash
php -r "
  \$required = ['pdo','pdo_mysql','mbstring','openssl','json','curl','fileinfo'];
  foreach (\$required as \$ext) {
    echo (\$ext . ': ' . (extension_loaded(\$ext) ? 'OK' : 'MISSING') . PHP_EOL);
  }
"
```

### MySQL Version Check

```sql
SELECT VERSION();
-- Must be >= 8.0.16 for CHECK constraint support
```

### SSH Access

All CLI commands (`cli/install.php`, `cli/worker.php`, etc.) **must be run over SSH**. cPanel Terminal (browser-based SSH) works for initial setup; a proper SSH client (PuTTY, OpenSSH) is recommended for day-to-day operations.

```bash
# Confirm the PHP binary path used by cPanel cron
which php
# Typically: /usr/local/bin/php
# Verify version:
/usr/local/bin/php -v
```

---

## 3. Directory Layout on cPanel

```
/home/USER/
│
├── pharma-crm/                          ← APPLICATION ROOT (above web root — not HTTP accessible)
│   ├── app/
│   │   ├── Controllers/                 ← Route handlers (Auth, Order, Customer, etc.)
│   │   ├── Models/                      ← Database models / entities
│   │   ├── Services/                    ← Business logic layer
│   │   ├── Middleware/                  ← Auth, tenant, rate-limit middleware
│   │   ├── Jobs/                        ← Queue job classes (Webhook, Notification, etc.)
│   │   └── vendor/                      ← Bundled third-party libraries (no Composer needed)
│   ├── bootstrap/
│   │   └── app.php                      ← Application bootstrapper (loaded by public_html/index.php)
│   ├── cli/
│   │   ├── install.php                  ← First-time installation script
│   │   ├── migrate.php                  ← Schema migration helper
│   │   ├── seed.php                     ← Database seeder
│   │   ├── tenant.php                   ← Tenant / org / franchise management
│   │   ├── user.php                     ← User management (create, unlock)
│   │   ├── worker.php                   ← Job queue worker
│   │   ├── scheduler.php                ← Cron dispatcher (minute/hourly/daily)
│   │   ├── backup.php                   ← Backup creation and verification
│   │   ├── keys.php                     ← JWT key generation and rotation
│   │   ├── lint.php                     ← Static code lint checks
│   │   ├── test-agents.php              ← Integration test agent runner
│   │   └── geo-import.php               ← India geo data importer
│   ├── database/
│   │   ├── schema/
│   │   │   └── 001_full_schema.sql      ← Complete schema (single source of truth)
│   │   └── seeds/
│   │       └── 001_fresh_seed.sql       ← OAuth clients, roles, geo data
│   ├── docs/
│   │   └── deployment/
│   │       └── README.md                ← This file
│   ├── storage/                         ← MUST be writable by PHP process
│   │   ├── logs/                        ← Application logs (rotated daily)
│   │   ├── cache/                       ← Route / config cache
│   │   ├── exports/                     ← CSV / PDF exports (temporary)
│   │   ├── backups/                     ← DB backups (.sql.gz, last 14 retained)
│   │   └── private/                     ← Uploaded documents (served via auth endpoint only)
│   ├── .env                             ← Secrets & config (NEVER commit; NEVER in web root)
│   └── .env.example                     ← Template for reference
│
└── public_html/                         ← WEB ROOT — the only directory Apache serves
    ├── index.php                        ← Single entry point
    ├── .htaccess                        ← Security, rewrites, caching headers
    └── assets/
        ├── css/
        │   └── crm-ui.css               ← Main stylesheet (versioned in build)
        ├── js/
        │   ├── crm-ui.js                ← Core JavaScript bundle
        │   └── modules/                 ← Lazy-loaded JS modules
        │       ├── orders.js
        │       ├── customers.js
        │       └── reports.js
        ├── img/
        │   └── icons.svg                ← SVG sprite sheet
        └── tenant/
            └── *.css                    ← Per-tenant theme overrides (slug-based filenames)
```

> [!IMPORTANT]
> **The `pharma-crm/` directory must NEVER be inside `public_html/`.** It lives one level above, making it completely inaccessible over HTTP. The only bridge between the web root and the application is `public_html/index.php`.

### Setting Up the Directory Structure via SSH

```bash
# 1. Navigate to home directory
cd /home/USER

# 2. Upload pharma-crm.tar.gz via cPanel File Manager or SCP
scp -P 22 pharma-crm.tar.gz USER@yourhost.com:/home/USER/

# 3. Extract
tar -xzf pharma-crm.tar.gz

# 4. Create public_html entry point and assets
# (copy from release package — see Release Procedure)
cp pharma-crm/public/index.php  public_html/index.php
cp pharma-crm/public/.htaccess  public_html/.htaccess
cp -r pharma-crm/public/assets/ public_html/assets/

# 5. Ensure storage directories exist and are writable
mkdir -p pharma-crm/storage/{logs,cache,exports,backups,private}
chmod 750 pharma-crm/storage
chmod 750 pharma-crm/storage/{logs,cache,exports,backups,private}

# 6. Create .env (never commit this file)
cp pharma-crm/.env.example pharma-crm/.env
nano pharma-crm/.env   # edit with production values
```

---

## 4. `.htaccess` Configuration

The production `.htaccess` belongs in `public_html/` only. It handles HTTPS enforcement, security restrictions, URL rewriting, and static asset caching.

### Full Production `.htaccess`

```apache
# ─────────────────────────────────────────────────────────────────
# 1. Disable directory listing and content negotiation
# ─────────────────────────────────────────────────────────────────
Options -Indexes -MultiViews

# ─────────────────────────────────────────────────────────────────
# 2. Enable Apache URL Rewrite Engine
# ─────────────────────────────────────────────────────────────────
RewriteEngine On

# ─────────────────────────────────────────────────────────────────
# 3. Force HTTPS — redirect all HTTP traffic to HTTPS
# ─────────────────────────────────────────────────────────────────
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# ─────────────────────────────────────────────────────────────────
# 4. Block direct access to sensitive file types
#    Covers dotfiles (e.g. .env, .git) and sensitive extensions
# ─────────────────────────────────────────────────────────────────
<FilesMatch "(^\.|\.(env|sql|md|log|ini|bak)$)">
  Require all denied
</FilesMatch>

# ─────────────────────────────────────────────────────────────────
# 5. Front-controller rewrite
#    Pass all requests that don't match a real file or directory
#    to index.php. QSA = append original query string.
# ─────────────────────────────────────────────────────────────────
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]

# ─────────────────────────────────────────────────────────────────
# 6. Pass Authorization header to PHP
#    PHP-FPM / LSAPI often strips this header; the env variable
#    ensures Bearer tokens reach the application correctly.
# ─────────────────────────────────────────────────────────────────
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

# ─────────────────────────────────────────────────────────────────
# 7. Browser caching for static assets
#    Reduces repeat requests; align cache busting with asset
#    versioning in your build process.
# ─────────────────────────────────────────────────────────────────
<IfModule mod_expires.c>
  ExpiresActive On
  ExpiresByType text/css               "access plus 7 days"
  ExpiresByType application/javascript "access plus 7 days"
  ExpiresByType image/svg+xml          "access plus 30 days"
</IfModule>
```

### Line-by-Line Explanation

| Directive | Purpose |
|---|---|
| `Options -Indexes` | Prevents Apache from showing a file listing when no `index.php` is present. Critical for `assets/` subdirectories. |
| `Options -MultiViews` | Disables content negotiation that could serve unintended files. |
| `RewriteEngine On` | Activates `mod_rewrite`; required for all `Rewrite*` directives below. |
| `RewriteCond %{HTTPS} !=on` | Matches requests that arrived over plain HTTP. |
| `RewriteRule ^ https://…` | 301 permanent redirect to HTTPS. Browsers cache this; use 302 during testing. |
| `<FilesMatch "(^\.|\…)">` | `^\.` matches any dotfile (`.env`, `.git`, `.htpasswd`). The extension list blocks `.sql`, `.log`, `.bak`, etc. |
| `Require all denied` | Apache 2.4 syntax to return 403 Forbidden for matched files. |
| `RewriteCond !-f` / `!-d` | Skips rewrite when the URL maps to a real file (CSS/JS/images) or real directory. |
| `RewriteRule ^ index.php` | Routes all other requests to the single entry point. |
| `HTTP_AUTHORIZATION` passthrough | Many shared hosts strip the `Authorization` header before PHP sees it. This env variable trick preserves it for JWT Bearer auth. |
| `mod_expires` block | Sets `Cache-Control` / `Expires` headers. 7 days for CSS/JS, 30 days for SVG sprites. Use query-string versioning (`crm-ui.css?v=20260920`) to bust cache on deploys. |

> [!WARNING]
> Do **not** place a `.htaccess` file inside `pharma-crm/`. That directory is not web-served and having Apache directives there serves no purpose. Keep all Apache configuration in `public_html/.htaccess`.

---

## 5. Environment Configuration (`.env`)

The `.env` file lives at `/home/USER/pharma-crm/.env` — **above the web root**. It is never accessible over HTTP. Never commit it to version control; use `.env.example` as a template.

### Production `.env`

```ini
# ── Application ─────────────────────────────────────────────────
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crm.yourdomain.com
APP_TIMEZONE=Asia/Kolkata
# 32 random bytes encoded as base64. Generate with:
#   php cli/keys.php generate   (populates this automatically)
APP_KEY=base64:REPLACE_WITH_32_RANDOM_BYTES_BASE64

# ── Database ─────────────────────────────────────────────────────
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cpuser_crm
DB_USER=cpuser_crm
# Use a strong password (20+ chars, mixed case, symbols)
DB_PASSWORD=REPLACE_WITH_STRONG_PASSWORD

# ── JWT Keys ─────────────────────────────────────────────────────
# K1 = current signing key (64 hex chars = 32 bytes)
# K0 = previous signing key (accepted during rotation window)
JWT_KEY_K1=REPLACE_WITH_64_HEX_CHARS
JWT_KEY_K0=
JWT_ACCESS_TTL=900

# ── Storage ──────────────────────────────────────────────────────
STORAGE_PATH=/home/USER/pharma-crm/storage

# ── Logging ──────────────────────────────────────────────────────
LOG_LEVEL=warning
```

### Local Development `.env`

```ini
# ── Application ─────────────────────────────────────────────────
APP_ENV=local
APP_DEBUG=true
APP_URL=http://crm
APP_TIMEZONE=Asia/Kolkata
APP_KEY=base64:dev-key-not-secure-change-in-production

# ── Database ─────────────────────────────────────────────────────
DB_HOST=localhost
DB_PORT=3306
DB_NAME=crm
DB_USER=root
DB_PASSWORD=

# ── JWT Keys ─────────────────────────────────────────────────────
JWT_KEY_K1=0000000000000000000000000000000000000000000000000000000000000001
JWT_KEY_K0=
JWT_ACCESS_TTL=900

# ── Storage ──────────────────────────────────────────────────────
STORAGE_PATH=E:/Projects/PHP/crm/storage

# ── Logging ──────────────────────────────────────────────────────
LOG_LEVEL=debug
```

### Key Differences: Production vs Local

| Setting | Production | Local |
|---|---|---|
| `APP_ENV` | `production` | `local` |
| `APP_DEBUG` | `false` — **never expose stack traces** | `true` |
| `APP_URL` | Full HTTPS domain | Local hostname or `localhost` |
| `APP_KEY` | Strong base64-encoded 32 bytes | Placeholder (run install.php to generate) |
| `DB_NAME` | cPanel-prefixed: `cpuser_crm` | Simple: `crm` |
| `DB_PASSWORD` | Strong password | Empty or simple |
| `JWT_KEY_K1` | 64 random hex chars | All-zeroes safe placeholder |
| `STORAGE_PATH` | Absolute Linux path | Absolute Windows path |
| `LOG_LEVEL` | `warning` or `error` | `debug` |

> [!CAUTION]
> Setting `APP_DEBUG=true` on production will expose exception stack traces, environment variables, and SQL queries to end users. **Always verify this is `false` before going live.**

### Generating Secret Keys

```bash
# Generate APP_KEY + JWT_KEY_K1 and write them directly into .env
php /home/USER/pharma-crm/cli/keys.php generate

# Or manually generate a 64-char hex key:
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"

# Or manually generate APP_KEY:
php -r "echo 'base64:' . base64_encode(random_bytes(32)) . PHP_EOL;"
```

### cPanel MySQL Database Naming

cPanel automatically prefixes database and user names with your cPanel username:

| What you type in cPanel | Actual name |
|---|---|
| `crm` (database) | `cpuser_crm` |
| `crm` (user) | `cpuser_crm` |

Use the **full prefixed name** in `.env`.

---

## 6. First-Time Installation

### Step 1 — Upload the Code

**Option A: SCP / SFTP** (recommended)

```bash
# Compress the project excluding dev artifacts
tar -czf pharma-crm-release.tar.gz \
  --exclude='.git' \
  --exclude='storage/logs/*' \
  --exclude='storage/cache/*' \
  --exclude='storage/backups/*' \
  pharma-crm/

# Upload
scp -P 22 pharma-crm-release.tar.gz USER@host.com:/home/USER/
```

**Option B: cPanel File Manager**

1. Go to **cPanel → File Manager**
2. Navigate to `/home/USER/`
3. Upload `pharma-crm-release.tar.gz`
4. Right-click → **Extract**

### Step 2 — Create the MySQL Database

1. **cPanel → MySQL Databases**
2. Create database: `crm` → becomes `cpuser_crm`
3. Create user: `crm` with a strong password → becomes `cpuser_crm`
4. Add user to database with permissions: **SELECT, INSERT, UPDATE, DELETE** only
   - ❌ Do NOT grant: DROP, CREATE, ALTER, FILE, SUPER, GRANT

### Step 3 — Create the `.env` File

```bash
cd /home/USER/pharma-crm
cp .env.example .env
nano .env   # fill in production values
```

Key values to set before install:
- `APP_URL` — your live domain with HTTPS
- `DB_NAME`, `DB_USER`, `DB_PASSWORD` — from Step 2
- Leave `APP_KEY` and `JWT_KEY_K1` blank — `install.php` will generate them

### Step 4 — Set Up Storage Directories

```bash
mkdir -p /home/USER/pharma-crm/storage/{logs,cache,exports,backups,private}
chmod 750 /home/USER/pharma-crm/storage
chmod 750 /home/USER/pharma-crm/storage/{logs,cache,exports,backups,private}
```

### Step 5 — Deploy the Public Entry Point

```bash
# Copy the public-facing files to the web root
cp /home/USER/pharma-crm/public/index.php  /home/USER/public_html/index.php
cp /home/USER/pharma-crm/public/.htaccess  /home/USER/public_html/.htaccess

# Copy compiled assets
cp -r /home/USER/pharma-crm/public/assets/ /home/USER/public_html/assets/
```

### Step 6 — Run the Installer

```bash
/usr/local/bin/php /home/USER/pharma-crm/cli/install.php
```

The installer performs these steps **automatically** in order:

| # | What `install.php` does |
|---|---|
| 1 | **PHP version check** — aborts if PHP < 8.1 |
| 2 | **MySQL version check** — aborts if MySQL < 8.0.16 (CHECK constraint support) |
| 3 | **`.env` validation** — verifies file exists and all required keys are present |
| 4 | **Schema installation** — drops all existing tables, then runs `database/schema/001_full_schema.sql` |
| 5 | **Seed data** — runs `database/seeds/001_fresh_seed.sql` (OAuth clients, role/permission matrix, India geo data) |
| 6 | **Super Admin creation** — prompts interactively for email + password; never stored in seed files |
| 7 | **Key generation** — generates `JWT_KEY_K1` and `APP_KEY` if not already set in `.env` |
| 8 | **Storage check** — verifies all `storage/` subdirectories exist and are writable by the PHP process |
| 9 | **Readiness check** — runs the `/ready` health check logic: DB connection, storage writable, JWT key present, MySQL version |

**Expected output on success:**

```
Pharma CRM Installer
====================
[OK] PHP 8.2.10 >= 8.1.0
[OK] MySQL 8.0.32 >= 8.0.16
[OK] .env loaded — all required keys present
[OK] Schema applied from database/schema/001_full_schema.sql
[OK] Seed data applied from database/seeds/001_fresh_seed.sql
Super Admin email: admin@yourdomain.com
Super Admin password: (hidden)
[OK] Super Admin created
[OK] APP_KEY generated and saved to .env
[OK] JWT_KEY_K1 generated and saved to .env
[OK] Storage directories verified
[OK] Readiness check passed
Installation complete. Visit https://crm.yourdomain.com
```

### Step 7 — Verify the Install

```bash
# Check the /ready endpoint
curl -I https://crm.yourdomain.com/ready
# Expected: HTTP/2 200

# Check the /health endpoint
curl -I https://crm.yourdomain.com/health
# Expected: HTTP/2 200
```

Browse to `https://crm.yourdomain.com` and log in with the Super Admin credentials created in Step 6.

---

## 7. Cron Job Setup

The application's background processing depends on **4 cron jobs** registered in cPanel. There are no persistent daemon processes.

### Cron Job Schedule

| Schedule | Command | Purpose |
|---|---|---|
| Every minute | `scheduler.php minute` | Runs the job queue worker (50-second budget) |
| Every 5 minutes | `scheduler.php five-minute` | Webhook retry sweep, notification dispatch |
| Every hour | `scheduler.php hourly` | Follow-up reminders, expired reservation release |
| Daily at 01:30 | `scheduler.php daily` | Near-expiry scan, payment reminders, rate-limit/idempotency purge, log rotation, backup |

### Registering Cron Jobs in cPanel

1. Log in to **cPanel**
2. Navigate to **Advanced → Cron Jobs**
3. Set **Email** at the top to receive cron error output (or leave blank to suppress)
4. Under **Add New Cron Job**, add each job below:

**Job 1 — Minute tick (queue worker)**

| Field | Value |
|---|---|
| Minute | `*` |
| Hour | `*` |
| Day | `*` |
| Month | `*` |
| Weekday | `*` |
| Command | `/usr/local/bin/php /home/USER/pharma-crm/cli/scheduler.php minute >> /home/USER/pharma-crm/storage/logs/cron.log 2>&1` |

**Job 2 — Five-minute sweep**

| Field | Value |
|---|---|
| Minute | `*/5` |
| Hour | `*` |
| Day | `*` |
| Month | `*` |
| Weekday | `*` |
| Command | `/usr/local/bin/php /home/USER/pharma-crm/cli/scheduler.php five-minute >> /home/USER/pharma-crm/storage/logs/cron.log 2>&1` |

**Job 3 — Hourly tasks**

| Field | Value |
|---|---|
| Minute | `0` |
| Hour | `*` |
| Day | `*` |
| Month | `*` |
| Weekday | `*` |
| Command | `/usr/local/bin/php /home/USER/pharma-crm/cli/scheduler.php hourly >> /home/USER/pharma-crm/storage/logs/cron.log 2>&1` |

**Job 4 — Daily tasks**

| Field | Value |
|---|---|
| Minute | `30` |
| Hour | `1` |
| Day | `*` |
| Month | `*` |
| Weekday | `*` |
| Command | `/usr/local/bin/php /home/USER/pharma-crm/cli/scheduler.php daily >> /home/USER/pharma-crm/storage/logs/cron.log 2>&1` |

> [!TIP]
> Replace `USER` with your actual cPanel username in all command paths. You can find this by running `echo $USER` in the cPanel terminal.

### Worker Behavior

The `minute` tick dispatches `worker.php` with a **50-second execution budget**, safely fitting within the 60-second cron interval:

```
0s ──────── worker starts ────────────────────────── 50s ── exits ── 60s (next cron)
```

The worker uses **`SELECT … FOR UPDATE SKIP LOCKED`** which makes it safe to run multiple overlapping cron ticks if the host fires them slightly early.

**Job Queue State Machine:**

```
PENDING → RUNNING → COMPLETED
                 ↘ FAILED (retry with backoff) → DEAD (after max_attempts)
```

**Retry backoff schedule:**

| Attempt | Delay |
|---|---|
| 1st retry | 30 seconds |
| 2nd retry | 2 minutes |
| 3rd retry | 10 minutes |
| 4th retry (final) | 30 minutes → DEAD |

**Stale job recovery:** Any job stuck in `RUNNING` state for more than **10 minutes** (e.g., due to a PHP process crash) is automatically re-queued to `PENDING` on the next worker tick.

### Scheduler Duties Summary

| Tick | Tasks |
|---|---|
| `minute` | Dispatch `worker.php --budget=50`; process up to N PENDING jobs |
| `five-minute` | Retry failed webhook deliveries; dispatch pending notifications |
| `hourly` | Send follow-up reminder notifications; release expired stock reservations |
| `daily` | Scan near-expiry products; send payment reminders; purge stale `rate_limits` and `idempotency_keys` rows; rotate and compress logs; run `backup.php` |

---

## 8. Release Procedure

Follow this checklist for every production deployment. Never skip the staging step.

### Pre-Release Checklist

- [ ] All changes are committed and the release tag is created in Git
- [ ] `.env.example` is updated if any new keys were added
- [ ] `database/schema/001_full_schema.sql` reflects all schema changes
- [ ] `database/seeds/001_fresh_seed.sql` is updated if seed data changed

### Full Release Procedure

#### Step 1 — Deploy to Staging

Upload the release package to a staging subfolder (e.g., `/home/USER/pharma-crm-staging/`):

```bash
scp pharma-crm-release.tar.gz USER@host.com:/home/USER/
ssh USER@host.com
cd /home/USER
tar -xzf pharma-crm-release.tar.gz -C pharma-crm-staging/ --strip-components=1
```

#### Step 2 — Configure Staging `.env`

```bash
cp /home/USER/pharma-crm-staging/.env.example /home/USER/pharma-crm-staging/.env
nano /home/USER/pharma-crm-staging/.env
# Use a staging DB (e.g. cpuser_crm_stg), staging APP_URL, etc.
```

#### Step 3 — Install on Staging

```bash
/usr/local/bin/php /home/USER/pharma-crm-staging/cli/install.php
```

This runs the full install (schema + seed + Super Admin creation). Verify it exits with no errors.

#### Step 4 — Run Test Agents

```bash
/usr/local/bin/php /home/USER/pharma-crm-staging/cli/test-agents.php \
  --all \
  --report=storage/exports/test-report.json
```

**All agents must exit 0.** If any agent fails, fix the issue and repeat from Step 3.

```bash
# Run specific groups if iterating on a fix:
php cli/test-agents.php --group=security --fail-fast
php cli/test-agents.php --group=tenancy  --fail-fast
```

#### Step 5 — Smoke Test (Manual)

Log in to all four surfaces using the staging URL and verify:

- [ ] **Super Admin** — Dashboard loads; tenant list visible; security events visible
- [ ] **Org Admin** — Franchise list loads; user management works
- [ ] **Franchise Admin** — Customer list, order management, stock view
- [ ] **Sales Rep** — Create a customer; place an order end-to-end

Complete one full **order-to-cash** cycle:
- [ ] Place order → Approve → Dispatch → Record payment
- [ ] Verify dispatch notification fired (check `storage/logs/` or job queue)
- [ ] Verify payment recorded and invoice generated

#### Step 6 — Swap Code to Production

```bash
# Option A: Rename directories (atomic on same filesystem)
mv /home/USER/pharma-crm         /home/USER/pharma-crm-old
mv /home/USER/pharma-crm-staging /home/USER/pharma-crm

# Option B: Rsync (safer if staging is on a different path)
rsync -av --delete \
  --exclude='.env' \
  --exclude='storage/' \
  /home/USER/pharma-crm-staging/ /home/USER/pharma-crm/
```

> [!IMPORTANT]
> When using `rsync`, always `--exclude='.env'` and `--exclude='storage/'` to avoid overwriting production secrets and data.

#### Step 7 — Update Production `.env` if Needed

```bash
nano /home/USER/pharma-crm/.env
# Add any new keys introduced in this release
# Cross-reference with .env.example
```

#### Step 8 — Apply Schema to Production

> [!CAUTION]
> This drops and recreates the entire database. Coordinate a **maintenance window** and take a full backup first.

```bash
# Take a backup before ANY schema change
php /home/USER/pharma-crm/cli/backup.php --out=storage/backups/

# Apply fresh schema (production)
php /home/USER/pharma-crm/cli/install.php
# OR if only schema needs re-applying (data migration separately handled):
php /home/USER/pharma-crm/cli/migrate.php --fresh --i-understand
```

#### Step 9 — Update Assets in Web Root

```bash
cp -r /home/USER/pharma-crm/public/assets/ /home/USER/public_html/assets/
# If index.php or .htaccess changed:
cp /home/USER/pharma-crm/public/index.php  /home/USER/public_html/index.php
cp /home/USER/pharma-crm/public/.htaccess  /home/USER/public_html/.htaccess
```

#### Step 10 — Re-register Cron Jobs

If cron commands changed (new schedule or path), update them in cPanel → Cron Jobs.

#### Step 11 — Post-Deploy Monitoring

Monitor for at least **24 hours** after production deploy:

- [ ] `/ready` endpoint returns 200
- [ ] `/super/security-events` — no unexpected spikes
- [ ] Job queue backlog in Super Admin dashboard — clearing normally
- [ ] Failed webhooks — check dashboard widget
- [ ] `storage/logs/app.log` — no unexpected ERRORs

---

## 9. Schema Change Management

### Policy: No Incremental Migrations

This application uses a **full-schema-replace** strategy rather than numbered up/down migration files. There is exactly **one authoritative schema file**:

```
database/schema/001_full_schema.sql
```

Every schema change is made directly to this file. The schema version is tracked by a comment at the top:

```sql
-- Pharma CRM Full Schema
-- Schema Version: 42
-- Last Updated: 2026-09-20
-- Requires: MySQL >= 8.0.16
```

### Workflow for Schema Changes

```
1. Modify database/schema/001_full_schema.sql locally
2. Modify database/seeds/001_fresh_seed.sql if seed data changes
3. Test locally:
       php cli/migrate.php --fresh
       php cli/seed.php --fresh
       php cli/test-agents.php --all
4. Commit both files together (schema + seed in same commit)
5. Deploy to staging → install.php → test agents → smoke test
6. Deploy to production (with maintenance window + backup)
```

### Production Schema Change Protocol

```
┌─────────────────────────────────────────────────────┐
│  1. Announce maintenance window to users             │
│  2. php cli/backup.php --out=storage/backups/        │
│  3. php cli/backup.php --verify   ← MUST PASS       │
│  4. Disable cron jobs in cPanel                      │
│  5. php cli/migrate.php --fresh --i-understand       │
│  6. php cli/seed.php --fresh                         │
│  7. Re-enable cron jobs                              │
│  8. Verify /ready returns 200                        │
│  9. Announce maintenance window closed               │
│ 10. Monitor for 1 hour                               │
└─────────────────────────────────────────────────────┘
```

**Rollback target: < 15 minutes** (restore from backup — see Section 10).

> [!NOTE]
> The `--i-understand` flag on `migrate.php --fresh` is a production safety guard. The script will print the current environment and require this flag to prevent accidental data loss. On `APP_ENV=local` the flag is not required.

---

## 10. Rollback Procedure

Use this procedure if a deployment causes critical failures.

### Step 1 — Restore Previous Code

```bash
# If you kept pharma-crm-old from the swap:
mv /home/USER/pharma-crm     /home/USER/pharma-crm-bad
mv /home/USER/pharma-crm-old /home/USER/pharma-crm
```

If no `pharma-crm-old` directory exists, redeploy from the last known-good release archive.

### Step 2 — Restore the Database

```bash
# List available backups
ls -lh /home/USER/pharma-crm/storage/backups/

# Restore from a specific backup
/usr/local/bin/php /home/USER/pharma-crm/cli/backup.php \
  --restore=storage/backups/backup-2026-09-20-013000.sql.gz
```

The restore command:
1. Decompresses the `.sql.gz` file
2. Drops all tables in `cpuser_crm`
3. Replays the full SQL dump
4. Reports row counts for key tables as a sanity check

### Step 3 — Verify the Rollback

```bash
/usr/local/bin/php /home/USER/pharma-crm/cli/test-agents.php \
  --agent=agent-schema \
  --agent=agent-tenancy
```

Both agents must exit 0 before the rollback is considered successful.

### Step 4 — Restore Cron Jobs

If cron jobs were modified during the failed deploy, revert them in cPanel → Cron Jobs to the previous schedule.

### Step 5 — Verify Health

```bash
curl -I https://crm.yourdomain.com/ready
# Must return: HTTP/2 200
```

### Rollback Time Budget

| Step | Target Time |
|---|---|
| Code restore | 2 min |
| DB restore | 5–8 min |
| Verification | 3 min |
| **Total** | **< 15 min** |

---

## 11. Backup Strategy

### Creating a Backup

```bash
# Standard backup (writes to storage/backups/)
/usr/local/bin/php /home/USER/pharma-crm/cli/backup.php \
  --out=storage/backups/

# Creates: storage/backups/backup-2026-09-20-133000.sql.gz
```

### Verified Backup

A verified backup restores the dump to a temporary test database and runs `agent-schema` to confirm structural integrity:

```bash
/usr/local/bin/php /home/USER/pharma-crm/cli/backup.php --verify
```

Use `--verify` for pre-deploy backups before any schema change. The command exits 0 only if the restored schema passes all schema-agent checks.

### Backup Retention

The daily cron automatically manages retention:
- **Keeps last 14 backups**
- Backups older than 14 are deleted by the daily scheduler tick
- Backup filenames are timestamped: `backup-YYYY-MM-DD-HHMMSS.sql.gz`

### Backup Storage

| Location | Purpose |
|---|---|
| `storage/backups/` | On-server backups (managed by daily scheduler) |
| Off-server storage | Download via SFTP and store externally weekly |

> [!WARNING]
> On-server backups are insufficient as the sole disaster recovery mechanism. Download backups to an external location (S3-compatible storage, local dev machine) at least weekly.

### Manual Backup Download

```bash
# From your local machine:
scp USER@host.com:/home/USER/pharma-crm/storage/backups/backup-2026-09-20-013000.sql.gz ./
```

---

## 12. JWT Key Rotation

The CRM uses a two-key JWT signing scheme (`K1` = current, `K0` = previous) to achieve **zero-downtime key rotation**.

### First-Time Key Generation

Run automatically by `install.php`. To regenerate manually (e.g., after a suspected key compromise):

```bash
/usr/local/bin/php /home/USER/pharma-crm/cli/keys.php generate
```

This generates a new 64-char hex `JWT_KEY_K1` and writes it to `.env`. All existing tokens signed with the old key become immediately invalid.

### Zero-Downtime Key Rotation

```bash
/usr/local/bin/php /home/USER/pharma-crm/cli/keys.php rotate
```

The rotation sequence:

```
Before rotation:
  K1 = <current-key>   ← signs all new tokens; validates tokens signed with K1
  K0 = (empty)

After rotation:
  K1 = <new-key>       ← signs all new tokens
  K0 = <former-K1>     ← still accepted for 15 minutes (= JWT_ACCESS_TTL = 900s)
```

**Timeline:**

```
t=0      rotate runs
t=0–15m  K0 window: existing sessions using old K1-signed tokens still work
t=15m    Old K1-signed tokens expire naturally (TTL = 900s = 15 min)
t=15m+   K0 is effectively dead; only K1-signed tokens are valid
```

After the 15-minute window has passed, you can optionally clear `K0` in `.env`:

```ini
JWT_KEY_K0=
```

### When to Rotate

| Event | Action |
|---|---|
| Routine (quarterly) | `keys.php rotate` |
| Suspected key compromise | `keys.php generate` (immediate invalidation of all sessions) |
| Post security incident | `keys.php generate` + audit `security_events` table |
| Staff offboarding | Consider `keys.php rotate` to force re-login |

---

## 13. Health Monitoring

### Health Endpoints

| Endpoint | Method | Purpose | Expected Response |
|---|---|---|---|
| `/health` | GET | Liveness probe — always 200 if PHP is responding | `200 OK` |
| `/ready` | GET | Readiness probe — checks all dependencies | `200 OK` or `503 Service Unavailable` |

**`/ready` checks:**
1. MySQL connection succeeds
2. MySQL version ≥ 8.0.16
3. `storage/` directories are writable
4. `JWT_KEY_K1` is set and non-empty

**`/ready` 503 response body (example):**
```json
{
  "status": "not_ready",
  "checks": {
    "db_connection": "OK",
    "mysql_version": "FAIL: 5.7.38 < 8.0.16",
    "storage_writable": "OK",
    "jwt_key": "OK"
  }
}
```

### Super Admin Dashboard Monitoring

Log in as Super Admin and check the dashboard for:

| Widget | What to Watch |
|---|---|
| Total Tenants / Users | Baseline; alert on unexpected changes |
| Job Queue Backlog | Should process within 1–2 minutes; investigate if > 100 |
| Failed Webhook Events | Should trend to zero; investigate persistently failing webhooks |
| Security Events (last 24h) | Watch for brute-force attempts, unusual auth patterns |

Navigate to `/super/security-events` for a full audit trail.

### Automated Monitoring (External)

Set up an external uptime monitor (UptimeRobot, Better Uptime, etc.) to poll `/ready` every 60 seconds:

- Alert threshold: 2 consecutive failures → PagerDuty/SMS
- Alert on response time > 5 seconds

---

## 14. Troubleshooting Common Issues

### `500 Internal Server Error` on All Pages

**Diagnosis:**
```bash
tail -50 /home/USER/pharma-crm/storage/logs/app.log
tail -50 /home/USER/public_html/.htaccess   # verify file is valid
```

**Common causes:**
- `.htaccess` syntax error → Check for typos; test with `apachectl -t` if available
- `.env` missing or malformed → Verify file exists at `/home/USER/pharma-crm/.env`
- `APP_KEY` not set → Run `php cli/keys.php generate`
- PHP extension missing → Check `php -m | grep pdo_mysql`

### `404 Not Found` for All API Routes

**Diagnosis:** `mod_rewrite` is not active or `.htaccess` is not being read.

**Fix:**
1. cPanel → **Apache mod_rewrite** → Enable (if available)
2. Verify `.htaccess` is in `public_html/` (not in a subdirectory)
3. Ensure `RewriteEngine On` is the first rewrite directive
4. Add `AllowOverride All` to the VirtualHost (requires support ticket on shared hosting)

### `Authorization` Header Not Reaching PHP

**Symptom:** API returns 401 even with a valid token.

**Fix:** Ensure this block is in `.htaccess`:
```apache
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```

Your app must read the token from `$_SERVER['HTTP_AUTHORIZATION']` **or** `$_SERVER['REDIRECT_HTTP_AUTHORIZATION']`.

### Cron Jobs Not Running

**Diagnosis:**
```bash
# Check cron log
tail -50 /home/USER/pharma-crm/storage/logs/cron.log

# Test manually
/usr/local/bin/php /home/USER/pharma-crm/cli/scheduler.php minute
```

**Common causes:**
- Wrong PHP binary path → verify with `which php`
- Wrong `USER` in cron command path
- `storage/logs/` not writable → `chmod 750 storage/logs/`
- cPanel cron not enabled for the account → Contact host support

### Job Queue Stuck (Backlog Growing)

**Diagnosis:**
```bash
# Run one worker tick manually
/usr/local/bin/php /home/USER/pharma-crm/cli/worker.php --once

# Check for stale RUNNING jobs
php cli/worker.php --budget=5  # let it requeue stale jobs
```

**Common causes:**
- Stale RUNNING jobs from a crashed PHP process → worker auto-requeues after 10 min
- `max_attempts` reached → jobs in DEAD state; review in dashboard
- DB connection pool exhausted → check MySQL `max_connections` with host

### MySQL `CHECK` Constraint Errors on Schema Apply

**Symptom:** `install.php` fails with `ERROR 3819 (HY000): Check constraint 'constraint_name' is violated`

**Cause:** MySQL version < 8.0.16 (CHECK constraints are parsed but not enforced before 8.0.16; they error at enforcement level).

**Fix:** Contact your host to upgrade MySQL. This is a hard requirement.

### `.env` File Accessible via Browser

If `https://crm.yourdomain.com/.env` returns content rather than 403:

1. Verify the `<FilesMatch>` block in `.htaccess` is correct
2. Check Apache version supports `Require all denied` (Apache 2.4+)
3. Temporarily rename `.htaccess` to verify it's being read (should break routing)
4. Contact host to confirm `AllowOverride` is not disabled at server level

### Storage Permissions Error

**Symptom:** `[ERROR] storage/logs/ is not writable`

```bash
# Check current permissions
ls -la /home/USER/pharma-crm/storage/

# Fix
chmod 750 /home/USER/pharma-crm/storage/logs
chmod 750 /home/USER/pharma-crm/storage/cache
chmod 750 /home/USER/pharma-crm/storage/exports
chmod 750 /home/USER/pharma-crm/storage/backups
chmod 750 /home/USER/pharma-crm/storage/private

# Verify the PHP process user owns the directories
# (on cPanel, PHP typically runs as the cPanel user)
ls -la /home/USER/pharma-crm/storage/
```

---

## 15. cPanel-Specific Security Notes

### Separation of Web Root and Application Code

The most critical security design decision: **application code lives outside the web root**.

```
/home/USER/pharma-crm/   ← NOT accessible via HTTP (above public_html/)
/home/USER/public_html/  ← Web root (Apache serves this)
```

An attacker who exploits a path traversal or misconfigured Apache cannot reach `.env`, SQL files, or PHP source code via HTTP.

### File Permission Model

| Path | Permissions | Why |
|---|---|---|
| `pharma-crm/` | `750` | Owner read/write; PHP group read |
| `pharma-crm/.env` | `640` | Owner only; PHP can read; world cannot |
| `pharma-crm/storage/` | `750` | PHP write; world no access |
| `public_html/index.php` | `644` | Apache must read; world cannot execute |
| `public_html/.htaccess` | `644` | Apache must read |
| `public_html/assets/` | `755` | Apache serves publicly |

```bash
# Apply recommended permissions
chmod 750 /home/USER/pharma-crm
chmod 640 /home/USER/pharma-crm/.env
chmod 750 /home/USER/pharma-crm/storage
chmod 644 /home/USER/public_html/index.php
chmod 644 /home/USER/public_html/.htaccess
```

### Database User Permissions

The application DB user (`cpuser_crm`) is granted **only the minimum required permissions**:

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON cpuser_crm.* TO 'cpuser_crm'@'localhost';
-- NO: DROP, CREATE, ALTER, INDEX, FILE, SUPER, GRANT OPTION
```

If an attacker achieves SQL injection, they cannot drop tables, read files from disk (`LOAD DATA INFILE`), or escalate privileges.

### PHP Configuration (cPanel PHP Selector)

Set these in cPanel → **MultiPHP INI Editor** → select your PHP version:

```ini
display_errors = Off          ; Never show errors to browser
log_errors = On               ; Log errors to storage/logs/
error_log = /home/USER/pharma-crm/storage/logs/php_errors.log
expose_php = Off              ; Don't reveal PHP version in headers
session.cookie_httponly = On  ; Protect session cookies from XSS
session.cookie_secure = On    ; Session cookies only over HTTPS
```

### Blocking Sensitive File Access

The `.htaccess` `<FilesMatch>` block blocks access to:

| Pattern | Blocks |
|---|---|
| `^\.` | Any dotfile: `.env`, `.git`, `.htpasswd`, `.user.ini` |
| `\.env$` | Explicit `.env` extension |
| `\.sql$` | Database dumps accidentally in web root |
| `\.log$` | Log files |
| `\.md$` | Documentation files |
| `\.ini$` | PHP ini override files |
| `\.bak$` | Editor backup files |

### Private Uploaded Documents

Files in `storage/private/` are **never served directly by Apache**. They are accessed only through an authorized PHP endpoint that:
1. Validates the user's JWT token
2. Verifies the user has permission to access the specific document
3. Streams the file content with appropriate headers

### Log Rotation and Purge

The daily cron:
1. Rotates `storage/logs/app.log` → `app.log.YYYY-MM-DD.gz`
2. Purges compressed logs older than 30 days

This prevents disk exhaustion on shared hosting where disk quotas are enforced.

### Rate Limiting and Idempotency

The `rate_limits` and `idempotency_keys` tables are purged nightly by the daily scheduler tick to prevent unbounded table growth. Shared hosting MySQL does not have the resources to manage millions of stale rows.

---

## 16. CLI Reference

All CLI commands are run via SSH with the full path to the PHP binary. Replace `USER` with your cPanel username.

```bash
PHPBIN=/usr/local/bin/php
APPDIR=/home/USER/pharma-crm
```

### Installation & Schema

```bash
# First-time full install (schema + seed + super admin + key generation)
$PHPBIN $APPDIR/cli/install.php

# Drop and recreate schema only (guarded: requires --i-understand in production)
$PHPBIN $APPDIR/cli/migrate.php --fresh [--i-understand]

# Seed the database (requires clean schema)
$PHPBIN $APPDIR/cli/seed.php --fresh

# Seed with demo/sample data (for staging/UAT environments)
$PHPBIN $APPDIR/cli/seed.php --fresh --demo
```

### Tenant and User Management

```bash
# Create a new organization, franchise, and first franchise admin in one command
$PHPBIN $APPDIR/cli/tenant.php create \
  --org-name="Acme Pharma" \
  --org-code=ACME \
  --franchise-name="Acme Mumbai" \
  --franchise-code=MUM \
  --admin-email=admin@acme.test

# Create an additional user within a franchise
$PHPBIN $APPDIR/cli/user.php create \
  --role=FRANCHISE_ADMIN \
  --franchise=FRN-XXXXXXXXXXXXXXXX \
  --email=newuser@acme.test \
  --name="Jane Doe"

# Unlock a locked user account (e.g., after too many failed logins)
$PHPBIN $APPDIR/cli/user.php unlock \
  --email=newuser@acme.test \
  --franchise=FRN-XXXXXXXXXXXXXXXX
```

### JWT Key Management

```bash
# Generate JWT_KEY_K1 and APP_KEY (first time or after compromise)
$PHPBIN $APPDIR/cli/keys.php generate

# Zero-downtime rotation: promotes K1→K0, generates new K1
$PHPBIN $APPDIR/cli/keys.php rotate
```

### Worker and Scheduler

```bash
# Run one single worker tick (process one job then exit)
$PHPBIN $APPDIR/cli/worker.php --once

# Run worker for a specified budget in seconds
$PHPBIN $APPDIR/cli/worker.php --budget=50

# Dispatch a specific scheduler tick manually (for testing)
$PHPBIN $APPDIR/cli/scheduler.php minute
$PHPBIN $APPDIR/cli/scheduler.php five-minute
$PHPBIN $APPDIR/cli/scheduler.php hourly
$PHPBIN $APPDIR/cli/scheduler.php daily
```

### Backup and Restore

```bash
# Create a compressed backup (output to storage/backups/)
$PHPBIN $APPDIR/cli/backup.php --out=storage/backups/

# Create a backup AND verify it (restore to test DB + run agent-schema)
$PHPBIN $APPDIR/cli/backup.php --verify

# Restore a specific backup file
$PHPBIN $APPDIR/cli/backup.php \
  --restore=storage/backups/backup-2026-09-20-013000.sql.gz
```

### Testing and Validation

```bash
# Run all test agents (use before every production deploy)
$PHPBIN $APPDIR/cli/test-agents.php --all

# Run all agents and write a JSON report
$PHPBIN $APPDIR/cli/test-agents.php \
  --all \
  --report=storage/exports/test-report.json

# Run a specific agent
$PHPBIN $APPDIR/cli/test-agents.php --agent=agent-schema
$PHPBIN $APPDIR/cli/test-agents.php --agent=agent-tenancy
$PHPBIN $APPDIR/cli/test-agents.php --agent=agent-auth

# Run a group of related agents
$PHPBIN $APPDIR/cli/test-agents.php --group=security
$PHPBIN $APPDIR/cli/test-agents.php --group=orders

# Stop on first failure (useful in CI or fast iteration)
$PHPBIN $APPDIR/cli/test-agents.php --all --fail-fast

# Static lint checks (PHP syntax + custom rules)
$PHPBIN $APPDIR/cli/lint.php
```

### Geo Data

```bash
# Import full India geographic data: states → districts → cities → pincodes
# Run after fresh seed if geo data is not included in 001_fresh_seed.sql
$PHPBIN $APPDIR/cli/geo-import.php
```

---

## 17. Performance Considerations

### PHP OpCache

Ensure OpCache is enabled in cPanel → **MultiPHP INI Editor**:

```ini
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0   ; Set to 0 in production (no live file reloads)
opcache.revalidate_freq = 0
```

> [!TIP]
> Set `opcache.validate_timestamps=0` in production for maximum performance. After each code deployment, you must reset OpCache. On cPanel with PHP-FPM, this happens automatically when PHP-FPM is restarted or when the process pool recycles. Some cPanel setups allow `php-fpm reload` via SSH or trigger it via cPanel → **PHP-FPM** → Restart.

### MySQL Query Performance

The application is designed for a shared MySQL server with modest resources:

- All foreign keys are indexed
- `SELECT … FOR UPDATE SKIP LOCKED` is used for queue polling (avoids table locks)
- Rate-limit and idempotency tables are purged nightly to stay small
- Avoid running large reports during peak hours (use `storage/exports/` async jobs instead)

### Static Asset Caching

Assets are served directly by Apache (not through PHP). The `.htaccess` sets cache headers:

| Asset Type | Cache Duration |
|---|---|
| CSS | 7 days |
| JavaScript | 7 days |
| SVG | 30 days |

Use **query-string cache busting** on each deploy:

```html
<link rel="stylesheet" href="/assets/css/crm-ui.css?v=20260920">
<script src="/assets/js/crm-ui.js?v=20260920"></script>
```

The deploy date or a build hash works well as the version parameter.

### Storage I/O

On shared hosting, disk I/O is a shared resource. Keep `storage/` tidy:
- `storage/exports/` — clean up exports older than 7 days (application-level)
- `storage/cache/` — clear stale cache entries after schema changes (`rm -f storage/cache/*.php`)
- `storage/backups/` — 14 backup max enforced by daily scheduler

### Shared Hosting Limits

Be aware of typical cPanel shared hosting limits that may affect performance:

| Resource | Typical Limit | Implication |
|---|---|---|
| PHP memory | 256–512 MB | Avoid loading large datasets into memory |
| PHP max execution time | 60–300 s | Long-running jobs must be chunked via queue |
| MySQL `max_connections` | 150–300 | Use persistent PDO connections judiciously |
| Cron frequency | 1/minute minimum | Worker budget = 50s per minute tick |
| Inodes / disk quota | Varies | Monitor `storage/` growth; rotate logs aggressively |

---

## 18. Log Management

### Log Files

| File | Contents | Rotation |
|---|---|---|
| `storage/logs/app.log` | Application-level log (errors, warnings, info) | Daily |
| `storage/logs/php_errors.log` | PHP engine errors (configure in cPanel PHP INI) | Daily |
| `storage/logs/cron.log` | stdout/stderr from all cron jobs | Weekly |
| `storage/logs/worker.log` | Job queue worker detailed output | Daily |

### Log Levels

Set `LOG_LEVEL` in `.env`:

| Level | When to Use |
|---|---|
| `debug` | Local development — logs everything including SQL queries |
| `info` | Staging — logs all requests and business events |
| `warning` | **Production (recommended)** — logs anomalies, recoverable errors |
| `error` | Production (minimal) — logs only hard errors |

> [!NOTE]
> Using `debug` or `info` in production on shared hosting will rapidly fill your disk quota. Stick to `warning` unless actively debugging a production issue.

### Daily Log Rotation (Automated)

The `daily` scheduler tick performs log rotation automatically:

1. Reads `storage/logs/app.log`
2. Renames to `storage/logs/app.log.YYYY-MM-DD`
3. Compresses: `gzip storage/logs/app.log.YYYY-MM-DD`
4. Purges `.gz` files older than 30 days
5. Creates a fresh `storage/logs/app.log`

### Checking Logs via SSH

```bash
# Live tail (for active debugging)
tail -f /home/USER/pharma-crm/storage/logs/app.log

# Last 100 error-level lines
grep " ERROR " /home/USER/pharma-crm/storage/logs/app.log | tail -100

# All cron output from last run
tail -50 /home/USER/pharma-crm/storage/logs/cron.log

# Check disk usage of storage directory
du -sh /home/USER/pharma-crm/storage/
du -sh /home/USER/pharma-crm/storage/logs/
du -sh /home/USER/pharma-crm/storage/backups/
```

### Disk Usage Alerts

cPanel provides disk quota monitoring under **cPanel → Disk Usage**. Set an alert threshold at 80% disk usage (cPanel → Contact Information → resource usage alerts).

If disk usage spikes unexpectedly:

```bash
# Find large files in storage
find /home/USER/pharma-crm/storage -name "*.log" -size +10M
find /home/USER/pharma-crm/storage -name "*.sql.gz" | head -20

# Manually purge old exports if needed
find /home/USER/pharma-crm/storage/exports -mtime +7 -delete

# Manually purge old compressed logs if rotation lagged
find /home/USER/pharma-crm/storage/logs -name "*.gz" -mtime +30 -delete
```

---

## Appendix A — Quick Reference Card

```
INSTALL     php cli/install.php
HEALTH      curl https://crm.yourdomain.com/ready
BACKUP      php cli/backup.php --out=storage/backups/
ROTATE KEY  php cli/keys.php rotate
RUN WORKER  php cli/worker.php --budget=50
RUN TESTS   php cli/test-agents.php --all --fail-fast
TAIL LOG    tail -f storage/logs/app.log
ADD TENANT  php cli/tenant.php create --org-name=... --org-code=... ...
UNLOCK USER php cli/user.php unlock --email=... --franchise=FRN-...
```

---

## Appendix B — Environment Variable Reference

| Variable | Required | Description |
|---|---|---|
| `APP_ENV` | Yes | `production` or `local` |
| `APP_DEBUG` | Yes | `false` in production |
| `APP_URL` | Yes | Full URL with scheme (e.g. `https://crm.example.com`) |
| `APP_TIMEZONE` | Yes | PHP timezone string (e.g. `Asia/Kolkata`) |
| `APP_KEY` | Yes | 32-byte base64-encoded application encryption key |
| `DB_HOST` | Yes | MySQL hostname (usually `localhost` on cPanel) |
| `DB_PORT` | Yes | MySQL port (usually `3306`) |
| `DB_NAME` | Yes | Database name (cPanel-prefixed: `cpuser_crm`) |
| `DB_USER` | Yes | Database username (cPanel-prefixed: `cpuser_crm`) |
| `DB_PASSWORD` | Yes | Database password |
| `JWT_KEY_K1` | Yes | Current JWT signing key (64 hex chars) |
| `JWT_KEY_K0` | No | Previous JWT signing key (set during rotation window) |
| `JWT_ACCESS_TTL` | Yes | Access token TTL in seconds (default: `900` = 15 min) |
| `STORAGE_PATH` | Yes | Absolute path to `storage/` directory |
| `LOG_LEVEL` | Yes | Minimum log level: `debug`, `info`, `warning`, `error` |

---

*Last updated: 2026-09-20 · Pharma CRM Deployment Guide v1.0*
