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
  </script>
</head>
<body>
  <div class="wrapper" id="app" hidden>
    <div id="impersonation-banner" class="impersonation-banner" style="display: none; background: var(--acc); color: #000; font-weight: 600; padding: 0.5rem 1rem; text-align: center;"></div>
    <header class="main-header">
      <div style="display: flex; align-items: center; gap: 1rem;">
        <button class="btn btn-ghost btn-sm" data-action="toggle-sidebar" aria-label="Toggle menu">☰</button>
        <span class="brand">Pharma CRM</span>
      </div>
      <div style="display: flex; align-items: center; gap: 1rem;">
        <span id="who" style="font-size: 0.875rem; font-weight: 500;"></span>
        <button class="btn btn-ghost btn-sm" data-action="logout">Logout</button>
      </div>
    </header>

    <aside class="main-sidebar">
      <nav id="nav" style="display: flex; flex-direction: column; gap: 0.5rem; padding: 1rem 0.5rem;">
        <a href="/<?= htmlspecialchars($surface, ENT_QUOTES) ?>/dashboard" class="btn btn-ghost" style="justify-content: flex-start;">Dashboard</a>
        <?php if ($surface === 'admin'): ?>
        <a href="/admin/leads" class="btn btn-ghost" style="justify-content: flex-start;">Leads</a>
        <a href="/admin/follow-ups" class="btn btn-ghost" style="justify-content: flex-start;">Follow-ups</a>
        <a href="/admin/parties" class="btn btn-ghost" style="justify-content: flex-start;">Parties</a>
        <a href="/admin/territories" class="btn btn-ghost" style="justify-content: flex-start;">Territories</a>
        <a href="/admin/categories" class="btn btn-ghost" style="justify-content: flex-start;">Categories</a>
        <a href="/admin/tiers" class="btn btn-ghost" style="justify-content: flex-start;">Pricing Tiers</a>
        <a href="/admin/products" class="btn btn-ghost" style="justify-content: flex-start;">Products</a>
        <a href="/admin/prices" class="btn btn-ghost" style="justify-content: flex-start;">Pricing Rules</a>
        <a href="/admin/schemes" class="btn btn-ghost" style="justify-content: flex-start;">Schemes</a>
        <?php elseif ($surface === 'sales'): ?>
        <a href="/sales/leads" class="btn btn-ghost" style="justify-content: flex-start;">My Leads</a>
        <a href="/sales/follow-ups" class="btn btn-ghost" style="justify-content: flex-start;">Follow-up Queue</a>
        <a href="/sales/parties" class="btn btn-ghost" style="justify-content: flex-start;">Assigned Parties</a>
        <?php endif; ?>
      </nav>
    </aside>

    <main class="content-wrapper">
      <section class="content-header">
        <h1 id="page-title"><?= htmlspecialchars($title, ENT_QUOTES) ?></h1>
      </section>
      <section class="content" id="view">
        <div class="card">
          <div class="card-header">Surface Active</div>
          <div class="card-body">
            <p>Welcome to the <strong><?= htmlspecialchars(strtoupper($surface), ENT_QUOTES) ?></strong> surface.</p>
            <p style="color: var(--mut); margin-top: 0.5rem;">Authenticated securely with Bearer JWT token in memory.</p>
          </div>
        </div>
      </section>
    </main>
  </div>

  <div id="toasts" aria-live="polite"></div>
  <script type="module" src="/assets/js/crm-ui.js"></script>
</body>
</html>
