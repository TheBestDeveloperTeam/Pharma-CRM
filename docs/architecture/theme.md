# UI Theme & Frontend Architecture

This document outlines the design and implementation of the user interface for the Pharma CRM. The architecture supports 4 distinct visual surfaces while remaining entirely free of heavy frontend frameworks.

## 1. Core Rules

- **UI-01**: Unified Architecture using local AdminLTE / Bootstrap 4. No external CDNs (no Google Fonts, no FontAwesome). It uses a lightweight, self-contained SVG/Lottie vector system for all iconography and motion.
- **UI-02**: Asset minimalism. Only two shipped assets: `/assets/css/crm-ui.css` and `/assets/js/crm-ui.js`.
- **UI-03**: Iconography is implemented via a single inline SVG sprite (`/assets/img/icons.svg`), referenced dynamically using `<use href="#i-name">`.
- **UI-04**: Theming is handled entirely by CSS variables. No hard-coded colors exist outside of `:root[data-theme]` blocks.
- **UI-05**: Pages are lightweight PHP shells (layout + nav + empty containers). Data arrives exclusively via Bearer API calls and is rendered using DOM APIs.
- **UI-06**: Mobile-first design:
  - 320px absolute baseline.
  - Sidebar transitions to an off-canvas drawer below 992px.
  - Data tables collapse into stacked cards below 768px.
- **UI-07**: Accessibility (a11y) is mandatory:
  - Semantic HTML landmarks.
  - `focus-visible` styling.
  - Labels strictly tied to inputs.
  - `aria-live` toasts.
  - Contrast ratios of at least 4.5:1.
  - No color-only status indicators.

---

## 2. Theme Map

The application adapts its appearance based on the client surface being accessed.

| Surface | Key | Sidebar | Primary | Accent | Background | Text | Look |
|---------|-----|---------|---------|--------|------------|------|------|
| Super Admin | `theme-super` | `#0B1437` | `#3D5AFE` | `#F5B301` | `#0F1B4C` | `#FFFFFF` | Midnight navy + gold (dark) |
| Franchise Admin | `theme-admin` | `#0A5C4C` | `#0E7C66` | `#F2A93B` | `#F4FBF8` | `#0B1F1A` | Pharma teal + amber (light) |
| Sales Team | `theme-sales` | `#12306B` | `#1E5EFF` | `#FF7A00` | `#F5F8FF` | `#0B1730` | Royal blue + orange (light) |
| Distributor | `theme-portal` | `#3B2378` | `#6A3DE8` | `#00C2A8` | `#F7F5FF` | `#1A1030` | Violet + mint (light) |

### 2.1 CSS Variable Definitions

```css
:root[data-theme="theme-super"]  { --sb:#0B1437; --pri:#3D5AFE; --acc:#F5B301; --bg:#0F1B4C; --card:#16235F; --tx:#FFFFFF; --mut:#9AA4C7; --bd:#26356F; }
:root[data-theme="theme-admin"]  { --sb:#0A5C4C; --pri:#0E7C66; --acc:#F2A93B; --bg:#F4FBF8; --card:#FFFFFF; --tx:#0B1F1A; --mut:#5C7A72; --bd:#D6EBE4; }
:root[data-theme="theme-sales"]  { --sb:#12306B; --pri:#1E5EFF; --acc:#FF7A00; --bg:#F5F8FF; --card:#FFFFFF; --tx:#0B1730; --mut:#5B6B8C; --bd:#DCE4F7; }
:root[data-theme="theme-portal"] { --sb:#3B2378; --pri:#6A3DE8; --acc:#00C2A8; --bg:#F7F5FF; --card:#FFFFFF; --tx:#1A1030; --mut:#6B5E8C; --bd:#E4DDF7; }
```

### 2.2 Server-Side Resolution

```php
final class ThemeResolver {
    public static function forSurface(string $surface): string {
        return match ($surface) {
            'super' => 'theme-super', 
            'admin' => 'theme-admin',
            'sales' => 'theme-sales', 
            'portal' => 'theme-portal',
            default => 'theme-sales',
        };
    }
}
```

---

## 3. The Layout Shell

All pages load a minimal PHP shell. 

```php
<?php // app/Views/layouts/shell.php
declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES) ?>" data-surface="<?= htmlspecialchars($surface, ENT_QUOTES) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES) ?> · Pharma CRM</title>
  <link rel="stylesheet" href="/assets/css/crm-ui.css">
</head>
<body>
<div class="wrapper" id="app" hidden>
  <header class="main-header">...</header>
  <aside class="main-sidebar" aria-label="Primary"><nav class="nav-sidebar" id="nav"></nav></aside>
  <main class="content-wrapper">...</main>
</div>
<div id="toasts" aria-live="polite"></div>
<script type="module" src="/assets/js/crm-ui.js"></script>
</body></html>
```

---

## 4. Component Inventory

The CSS framework provides targeted, low-level components:
- **Layout**: wrapper, header, sidebar, content-wrapper, breadcrumb.
- **Display**: card, small-box (KPIs), info-box, table, mobile-card-list, badge, status-badge, callout, timeline, empty-state, skeleton loaders.
- **Input**: input, select, textarea, date, money, phone, search-select, checkbox, radio, file-upload.
- **Action**: btn (variants: primary, accent, ghost, danger), icon-btn, dropdown, tabs, pagination, modal, drawer, confirm-dialog, toast.
- **Data (JS-driven)**: DataTable (fetch → render → paginate → sort → filter), FormBuilder, Stepper, Cart, PriceSummary, Chart (inline SVG generation).

---

## 5. Tenant Branding

Franchises can have custom styling applied to the Admin, Sales, and Portal surfaces.
- **Configuration**: Franchise Admins may set `brand_accent_hex`. Super Admins may set both primary and accent.
- **Validation**: The server validates `#RRGGBB` inputs and automatically computes contrast against the surface `--bg`. It actively rejects combinations that fall below 4.5:1.
- **Delivery**: The custom CSS is generated as a physical file at `/assets/tenant/{franchise_ref}.css`. This avoids inline `<style>` tags, keeping the Content Security Policy (CSP) strictly locked down.

---

## 6. Frontend Application Lifecycle

### 6.1 Bootstrap Flow
1. The PHP Shell renders `<html data-theme="...">` server-side based on the URL surface. This prevents any flash of the wrong theme.
2. The `crm-ui.js` module boots and immediately calls `GET /api/v1/auth/me`.
3. If the returned user role does not map to the current surface, tokens are wiped and the user is redirected to the appropriate surface's login page.
4. The script renders the navigation tree, user profile widget, and brand settings based on the API response.

### 6.2 Module Responsibilities (`crm-ui.js`)
- **Token Manager**: Holds the access token in memory, and manages the refresh token in `sessionStorage`.
- **API Wrapper**: The `api()` function wraps standard `fetch`. It automatically refreshes the token on a `401 Unauthorized` response. It injects the `Authorization` and `Idempotency-Key` headers.
- **DOM Helpers**: Provides an `h()` function for declarative element building, strictly enforcing the rule against `innerHTML`.
- **Notifications**: Toast system utilizing `aria-live` for accessible announcements.
- **Modals**: Handles focus-trapping, `ESC` key closing, and overlay management.
- **Layout Management**: Handles sidebar toggling and off-canvas transitions for mobile viewports.
- **Cross-tab State**: Utilizes `BroadcastChannel('crm-auth')` to ensure a logout action in one tab propagates immediately to all others.
