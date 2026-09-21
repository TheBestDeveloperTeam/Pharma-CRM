<!DOCTYPE html>
<html lang="en" data-theme="<?= htmlspecialchars($theme, ENT_QUOTES) ?>" data-surface="<?= htmlspecialchars($surface, ENT_QUOTES) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title, ENT_QUOTES) ?> · Pharma CRM</title>
  <link rel="stylesheet" href="/assets/css/crm-ui.css">
  <?php if (!empty($franchiseRef)): ?>
  <link rel="stylesheet" href="/assets/tenant/<?= htmlspecialchars($franchiseRef, ENT_QUOTES) ?>.css">
  <?php endif; ?>
  <script type="module">
    window.CRM_LOGIN_URL = '/<?= htmlspecialchars($surface, ENT_QUOTES) ?>/login';
    window.CRM_SURFACE   = '<?= htmlspecialchars($surface, ENT_QUOTES) ?>';
    window.CRM_PAGE      = '<?= htmlspecialchars($page ?? 'dashboard', ENT_QUOTES) ?>';
  </script>
</head>
<body>
  <!-- SVG icon sprite (hidden) -->
  <?php
    $iconsPath = dirname(__DIR__, 3) . '/public/assets/img/icons.svg';
    if (file_exists($iconsPath)) { echo file_get_contents($iconsPath); }
  ?>

  <div class="wrapper" id="app" hidden>
    <div id="impersonation-banner" class="impersonation-banner" style="display: none;"></div>

    <!-- ── Header ──────────────────────────────────────────── -->
    <header class="main-header">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <button class="btn btn-ghost btn-sm" data-action="toggle-sidebar" aria-label="Toggle menu">☰</button>
        <span class="brand">Pharma CRM</span>
      </div>
      <div style="display: flex; align-items: center; gap: 1rem;">
        <button class="btn btn-ghost btn-icon" data-action="notifications" aria-label="Notifications" style="position: relative;">
          <svg width="20" height="20"><use href="#icon-bell"/></svg>
          <span id="notif-badge" class="badge badge-danger" style="position: absolute; top: -2px; right: -2px; display: none; font-size: 0.6rem; padding: 0.1rem 0.35rem;">0</span>
        </button>
        <span id="who" style="font-size: 0.875rem; font-weight: 500;"></span>
        <button class="btn btn-ghost btn-sm" data-action="logout">
          <svg width="16" height="16"><use href="#icon-logout"/></svg> Logout
        </button>
      </div>
    </header>

    <!-- ── Sidebar ─────────────────────────────────────────── -->
    <aside class="main-sidebar">
      <nav id="nav">

        <?php if ($surface === 'super'): ?>
        <!-- SUPER ADMIN NAV -->
        <div class="nav-section">
          <div class="nav-section-title">Platform</div>
          <a href="/super/dashboard" class="nav-link<?= ($page ?? '') === 'dashboard' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-dashboard"/></svg> Dashboard
          </a>
          <a href="/super/organizations" class="nav-link<?= ($page ?? '') === 'organizations' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-franchise"/></svg> Organizations
          </a>
          <a href="/super/franchises" class="nav-link<?= ($page ?? '') === 'franchises' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-franchise"/></svg> Franchises
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-section-title">Monitoring</div>
          <a href="/super/audit" class="nav-link<?= ($page ?? '') === 'audit' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-shield"/></svg> Audit Log
          </a>
          <a href="/super/security" class="nav-link<?= ($page ?? '') === 'security' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-shield"/></svg> Security Events
          </a>
        </div>

        <?php elseif ($surface === 'admin'): ?>
        <!-- FRANCHISE ADMIN NAV -->
        <div class="nav-section">
          <div class="nav-section-title">Overview</div>
          <a href="/admin/dashboard" class="nav-link<?= ($page ?? '') === 'dashboard' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-dashboard"/></svg> Dashboard
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-section-title">CRM</div>
          <a href="/admin/leads" class="nav-link<?= ($page ?? '') === 'leads' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-leads"/></svg> Leads
          </a>
          <a href="/admin/follow-ups" class="nav-link<?= ($page ?? '') === 'follow-ups' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-followups"/></svg> Follow-ups
          </a>
          <a href="/admin/parties" class="nav-link<?= ($page ?? '') === 'parties' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-parties"/></svg> Parties
          </a>
          <a href="/admin/territories" class="nav-link<?= ($page ?? '') === 'territories' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-territories"/></svg> Territories
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-section-title">Catalogue</div>
          <a href="/admin/categories" class="nav-link<?= ($page ?? '') === 'categories' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-categories"/></svg> Categories
          </a>
          <a href="/admin/products" class="nav-link<?= ($page ?? '') === 'products' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-products"/></svg> Products
          </a>
          <a href="/admin/tiers" class="nav-link<?= ($page ?? '') === 'tiers' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-tiers"/></svg> Pricing Tiers
          </a>
          <a href="/admin/prices" class="nav-link<?= ($page ?? '') === 'prices' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-payments"/></svg> Pricing Rules
          </a>
          <a href="/admin/schemes" class="nav-link<?= ($page ?? '') === 'schemes' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-schemes"/></svg> Schemes
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-section-title">Orders &amp; Billing</div>
          <a href="/admin/orders" class="nav-link<?= ($page ?? '') === 'orders' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-orders"/></svg> Orders
          </a>
          <a href="/admin/invoices" class="nav-link<?= ($page ?? '') === 'invoices' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-invoices"/></svg> Invoices
          </a>
          <a href="/admin/dispatches" class="nav-link<?= ($page ?? '') === 'dispatches' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-dispatches"/></svg> Dispatches
          </a>
          <a href="/admin/payments" class="nav-link<?= ($page ?? '') === 'payments' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-payments"/></svg> Payments
          </a>
          <a href="/admin/inventory" class="nav-link<?= ($page ?? '') === 'inventory' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-inventory"/></svg> Inventory
          </a>
        </div>
        <div class="nav-section">
          <div class="nav-section-title">System</div>
          <a href="/admin/users" class="nav-link<?= ($page ?? '') === 'users' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-users"/></svg> Users
          </a>
          <a href="/admin/reports" class="nav-link<?= ($page ?? '') === 'reports' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-reports"/></svg> Reports
          </a>
          <a href="/admin/notifications" class="nav-link<?= ($page ?? '') === 'notifications' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-bell"/></svg> Notifications
          </a>
          <a href="/admin/settings" class="nav-link<?= ($page ?? '') === 'settings' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-settings"/></svg> Settings
          </a>
        </div>

        <?php elseif ($surface === 'sales'): ?>
        <!-- SALES REP NAV -->
        <div class="nav-section">
          <div class="nav-section-title">My Work</div>
          <a href="/sales/dashboard" class="nav-link<?= ($page ?? '') === 'dashboard' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-dashboard"/></svg> Dashboard
          </a>
          <a href="/sales/leads" class="nav-link<?= ($page ?? '') === 'leads' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-leads"/></svg> My Leads
          </a>
          <a href="/sales/follow-ups" class="nav-link<?= ($page ?? '') === 'follow-ups' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-followups"/></svg> Follow-up Queue
          </a>
          <a href="/sales/parties" class="nav-link<?= ($page ?? '') === 'parties' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-parties"/></svg> Assigned Parties
          </a>
          <a href="/sales/orders" class="nav-link<?= ($page ?? '') === 'orders' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-orders"/></svg> Orders
          </a>
        </div>

        <?php elseif ($surface === 'portal'): ?>
        <!-- DISTRIBUTOR PORTAL NAV -->
        <div class="nav-section">
          <div class="nav-section-title">My Account</div>
          <a href="/portal/dashboard" class="nav-link<?= ($page ?? '') === 'dashboard' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-dashboard"/></svg> Dashboard
          </a>
          <a href="/portal/catalogue" class="nav-link<?= ($page ?? '') === 'catalogue' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-catalogue"/></svg> Catalogue
          </a>
          <a href="/portal/orders" class="nav-link<?= ($page ?? '') === 'orders' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-orders"/></svg> My Orders
          </a>
          <a href="/portal/invoices" class="nav-link<?= ($page ?? '') === 'invoices' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-invoices"/></svg> Invoices
          </a>
          <a href="/portal/dispatches" class="nav-link<?= ($page ?? '') === 'dispatches' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-dispatches"/></svg> Dispatches
          </a>
          <a href="/portal/outstanding" class="nav-link<?= ($page ?? '') === 'outstanding' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-outstanding"/></svg> Outstanding
          </a>
          <a href="/portal/profile" class="nav-link<?= ($page ?? '') === 'profile' ? ' active' : '' ?>">
            <svg class="nav-icon" width="20" height="20"><use href="#icon-settings"/></svg> Profile
          </a>
        </div>
        <?php endif; ?>

      </nav>
    </aside>

    <!-- ── Content ─────────────────────────────────────────── -->
    <main class="content-wrapper">
      <section class="content-header">
        <h1 id="page-title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>
        <div id="page-actions" class="btn-group"></div>
      </section>
      <section class="content" id="view">
        <div class="loading-overlay" id="page-loading">
          <div class="spinner spinner-lg"></div>
          <span>Loading...</span>
        </div>
      </section>
    </main>
  </div>

  <!-- Modal container (populated by JS) -->
  <div id="modal-root"></div>

  <div id="toasts" aria-live="polite"></div>
  <script type="module" src="/assets/js/crm-ui.js"></script>
</body>
</html>
