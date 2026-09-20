# Production Deployment Instructions

This guide outlines how to deploy the Pharma CRM backend API to the production server at `https://crm.easysolutins24.in`.

## Prerequisites
- SSH or FTP access to `public_html/web/easysolutions24.in/web/crm`.
- PHP 8.2+ installed on the server.
- Composer installed on the server.
- MariaDB / MySQL installed (using existing DB `ihgzplwh_crm`).

## Step 1: Upload Files
Upload all files from your local repository to `public_html/web/easysolutions24.in/web/crm`, **excluding**:
- `tests/`
- `cli/api_test_bot.php`
- `cli/masters_test_bot.php`
- `cli/run_all_tests.php`
- `.git/`
- `.git/`

## Step 2: Configure Environment
1. Copy `.env.production` and rename it to `.env` on the server.
2. The database credentials (`ihgzplwh_crm` / `n33d@L0v3`) are already pre-filled.

## Step 3: Install Dependencies
Run the following command via SSH:
```bash
composer install --no-dev --optimize-autoloader
```

## Step 4: Generate JWT Keys
To secure the authentication system, you must generate a production public/private RSA key pair.
Run this script via SSH:
```bash
php cli/generate_keys.php
```
This will output a Private Key, Public Key, and Passphrase. Update your `.env` file with these exact values:
```env
JWT_SECRET_KEY="-----BEGIN PRIVATE KEY-----\n..."
JWT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n..."
JWT_PASSPHRASE="your_generated_passphrase"
```

## Step 5: Database Migration & Seeding
Import the full schema and seed data into the database using PHPMyAdmin, cPanel, or the MySQL CLI:
```bash
# 1. Full Schema
mysql -u ihgzplwh_crm -p ihgzplwh_crm < database/schema/001_full_schema.sql

# 2. Seed Data (in sequence)
mysql -u ihgzplwh_crm -p ihgzplwh_crm < database/seeds/001_admin_seed.sql
mysql -u ihgzplwh_crm -p ihgzplwh_crm < database/seeds/002_masters_seed.sql
mysql -u ihgzplwh_crm -p ihgzplwh_crm < database/seeds/004_inventory_seed.sql
mysql -u ihgzplwh_crm -p ihgzplwh_crm < database/seeds/005_additional_seed.sql

# Or execute via CLI seeder:
php cli/seed.php
```

## Step 6: File Permissions
Ensure the web server (e.g. Apache/Nginx) has write access to the `storage/` directory for logs:
```bash
chmod -R 775 storage/
chown -R www-data:www-data storage/
```

## Verification & API Documentation
You can verify the deployment by navigating to `https://crm.easysolutins24.in/api/v1/auth/me` with a valid Bearer token.
Use the first-run admin seed (`admin@pharmacrm.local` / `Admin@1234`) to login initially and change your password.

### Swagger API Documentation
We have set up a Swagger UI frontend. Once deployed, you can view the complete API documentation by navigating your browser to:
**https://crm.easysolutins24.in/api-docs/index.html**
