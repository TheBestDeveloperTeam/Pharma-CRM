# Dependencies

This project adheres strictly to the "Core Custom PHP Only" and "Vanilla CSS/JS" constraint. Only minimal, vetted, and necessary vendor libraries are allowed.

## PHP Backend (Composer)
| Library | Version | License | Purpose | Justification for Not Hand-Rolling |
|---|---|---|---|---|
| `firebase/php-jwt` | `^6.8` | BSD-3-Clause | JWT encoding/decoding | Crypto logic (HMAC, RSA) is error-prone and dangerous to hand-roll. This is the industry standard minimal library. |
| `phpmailer/phpmailer` | `^6.8` | LGPL-2.1 | Email Sending | Hand-rolling SMTP protocols and MIME attachments correctly across edge cases is complex and unnecessary. |
| `mpdf/mpdf` | `^8.1` | GPL-2.0 | PDF Generation | Creating complex PDFs (Invoices, prescriptions) via raw string manipulation is non-viable. |
| `vlucas/phpdotenv` | `^5.5` | BSD-3-Clause | Env variable loading | Simplifies managing `.env` configuration securely without polluting global state unnecessarily. |
| `phpunit/phpunit` | `^10.0` | BSD-3-Clause | Testing Framework | Standard for unit/integration tests; hand-rolling a test runner adds no business value. |

## Frontend (Vendored Locally)
All frontend dependencies must be downloaded and stored locally in `/public/assets/plugins/`. No CDN dependencies are allowed.

| Library | Version | License | Purpose | Justification |
|---|---|---|---|---|
| `AdminLTE` | `3.2.0` | MIT | UI Framework | Mandated by requirements. |
| `Bootstrap` | `4.6.x` | MIT | UI Grid/Components | Required by AdminLTE 3.x. |
| `jQuery` | `3.6.x` | MIT | DOM Manipulation | Required by AdminLTE 3.x. |
| `DataTables` | `1.13.x` | MIT | Advanced Data Tables | Too complex to build an accessible, sortable, paginatable table from scratch. |
| `Select2` | `4.0.13` | MIT | Advanced Dropdowns | Required for searchable dropdowns (e.g., Doctor selection). |
| `SweetAlert2` | `11.7.x` | MIT | Modals / Toasts | Superior UX for confirmations and alerts over native `alert()`. |
| `Chart.js` | `3.9.x` | MIT | Analytics Charts | Necessary for dashboard data visualization. |
| `FontAwesome` | `5.15.4` | SIL OFL | Icons | Required by AdminLTE 3.x. |
